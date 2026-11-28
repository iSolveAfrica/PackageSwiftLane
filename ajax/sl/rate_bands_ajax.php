<?php
/**
 * Route prices (rate_bands.php): add, change or delete a public weight band in cdb_shipping_fees.
 *   op=save    id (0 = new), origin, destiny, category, from, to, price, divisor, min_charge, valid_from, valid_until
 *   op=delete  id
 * Only public rows (client_id 0/NULL). Bands of one route and category may not overlap, so a weight always
 * has exactly one price.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
if (sl_userIs('client') || sl_userIs('driver') || !$user->cdp_hasPermission('manage_taxes_and_fees')) {
    sl_json(['success' => false, 'message' => 'You do not have permission to change prices'], 403);
}
$num = fn(string $k) => is_numeric($_POST[$k] ?? null) ? (float) $_POST[$k] : null;
$date = function (string $k) {
    $v = trim((string) ($_POST[$k] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : null;
};
$log = function (string $verb, string $summary, int $id) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'settings', 'action' => 'rate_band.' . $verb, 'label' => 'Route price', 'verb' => $verb === 'delete' ? 'delete' : ($verb === 'create' ? 'create' : 'update'), 'outcome' => 'success', 'summary' => $summary, 'entity_type' => 'shipping_fee', 'entity_id' => $id]);
    }
};
$op = (string) ($_POST['op'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$existing = $id > 0 ? sl_row('SELECT * FROM cdb_shipping_fees WHERE id = :id AND (client_id = 0 OR client_id IS NULL)', [':id' => $id]) : null;
if ($id > 0 && !$existing) sl_json(['success' => false, 'message' => 'That route price no longer exists'], 404);

if ($op === 'delete') {
    if (!$existing) sl_json(['success' => false, 'message' => 'Choose a route price to delete'], 422);
    $db = new Conexion();
    $db->cdp_query('DELETE FROM cdb_shipping_fees WHERE id = :id AND (client_id = 0 OR client_id IS NULL)');
    $db->bind(':id', $id);
    if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not delete it. Try again.'], 500);
    $log('delete', 'Deleted route price #' . $id, $id);
    sl_json(['success' => true, 'message' => 'Route price deleted', 'redirect' => 'rate_bands.php']);
}
if ($op !== 'save') sl_json(['success' => false, 'message' => 'Unknown action'], 400);

$origin = (int) ($_POST['origin'] ?? 0);
$destiny = (int) ($_POST['destiny'] ?? 0);
$cat = max(0, (int) ($_POST['category'] ?? 0));
$from = $num('from');
$to = $num('to');
$price = $num('price');
$divisor = (int) ($_POST['divisor'] ?? 5000);
$min = $num('min_charge') ?? 0.0;
$vf = $date('valid_from');
$vu = $date('valid_until');
$errors = [];
$isCountry = fn(int $c) => $c > 0 && (int) sl_scalar('SELECT COUNT(*) FROM cdb_countries WHERE id = :c', [':c' => $c]) > 0;
if (!$isCountry($origin)) $errors['origin'] = 'Choose where parcels are shipped from';
if (!$isCountry($destiny)) $errors['destiny'] = 'Choose where parcels are shipped to';
if ($cat > 0 && (int) sl_scalar('SELECT COUNT(*) FROM cdb_category WHERE id = :c', [':c' => $cat]) === 0) $errors['category'] = 'Choose a category from the list';
if ($from === null || $from < 0) $errors['from'] = 'Enter the lowest weight, 0 or more';
if ($to === null || ($from !== null && $to <= $from)) $errors['to'] = 'The highest weight must be more than the lowest';
if ($price === null || $price <= 0) $errors['price'] = 'Enter a price above 0';
if ($divisor < 100 || $divisor > 10000) $errors['divisor'] = 'Use a volumetric divisor between 100 and 10000';
if ($min < 0) $errors['min_charge'] = 'The minimum cannot be negative';
if ($vf && $vu && $vu < $vf) $errors['valid_until'] = 'The end date is before the start date';
if ($errors) sl_json(['success' => false, 'message' => reset($errors), 'errors' => $errors], 422);

// One price per weight: no overlapping band on the same route and category (excluding this one).
$clash = sl_row('SELECT id, initial_range, final_range FROM cdb_shipping_fees
    WHERE (client_id = 0 OR client_id IS NULL) AND origin = :o AND destiny = :d AND COALESCE(order_item_category, 0) = :c
      AND id <> :id AND initial_range < :to AND final_range > :from LIMIT 1',
    [':o' => $origin, ':d' => $destiny, ':c' => $cat, ':id' => $id, ':to' => $to, ':from' => $from]);
if ($clash) {
    sl_json(['success' => false, 'message' => 'This overlaps the ' . rtrim(rtrim(number_format((float) $clash->initial_range, 2), '0'), '.') . '–' . rtrim(rtrim(number_format((float) $clash->final_range, 2), '0'), '.') . ' band on the same route. Change the weights or edit that band.', 'errors' => ['from' => 'Overlaps another band']], 422);
}
$db = new Conexion();
if ($existing) {
    $db->cdp_query('UPDATE cdb_shipping_fees SET origin = :o, destiny = :d, order_item_category = :c, initial_range = :f, final_range = :t, price = :p,
        volumetric_percentage = :v, min_charge = :m, valid_from = :vf, valid_until = :vu WHERE id = :id AND (client_id = 0 OR client_id IS NULL)');
    $db->bind(':id', $id);
} else {
    $db->cdp_query('INSERT INTO cdb_shipping_fees (client_id, origin, destiny, order_item_category, initial_range, final_range, price, volumetric_percentage, min_charge, valid_from, valid_until)
        VALUES (0, :o, :d, :c, :f, :t, :p, :v, :m, :vf, :vu)');
}
$db->bind(':o', $origin); $db->bind(':d', $destiny); $db->bind(':c', $cat ?: null); $db->bind(':f', $from); $db->bind(':t', $to);
$db->bind(':p', $price); $db->bind(':v', $divisor); $db->bind(':m', $min); $db->bind(':vf', $vf); $db->bind(':vu', $vu);
if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not save the route price. Try again.'], 500);
$newId = $existing ? $id : (int) $db->dbh->lastInsertId();
$log($existing ? 'update' : 'create', ($existing ? 'Changed' : 'Added') . ' route price #' . $newId . ': ' . $from . '–' . $to . ' at ' . $price . ' per unit', $newId);
sl_json(['success' => true, 'message' => $existing ? 'Route price saved' : 'Route price added', 'redirect' => 'rate_bands.php']);
