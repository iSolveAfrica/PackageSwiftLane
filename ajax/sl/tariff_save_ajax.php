<?php
/**
 * Create, update or delete a cdb_shipping_fees row from the Rate editor. Same
 * validation and query helpers as ajax/tools/ship_tariffs/ship_tariffs_{add,edit,delete}_ajax.php
 * (cdp_verifyRangeTariffsExist, cdp_insertTariffs, cdp_updateTariffs, cdp_deleteTariffs),
 * plus the min_charge / validity columns added by the finance migration.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
$op = (string) ($_POST['op'] ?? 'save');
$id = (int) ($_POST['id'] ?? 0);
$num = fn($k) => is_numeric(str_replace(',', '.', trim((string) ($_POST[$k] ?? '')))) ? (float) str_replace(',', '.', trim((string) $_POST[$k])) : null;
if ($op === 'delete') {
    require_permission('delete_shipping_tariff');
    if (!$id || !sl_row('SELECT id FROM cdb_shipping_fees WHERE id = :id', [':id' => $id])) sl_json(['success' => false, 'message' => 'Rate not found'], 404);
    $ok = cdp_deleteTariffs($id);
    cdp_activityLog(['module' => 'tariffs', 'action' => 'delete', 'action_label' => 'Deleted rate', 'verb' => 'delete', 'outcome' => $ok ? 'ok' : 'fail', 'summary' => "deleted shipping rate #$id", 'entity_type' => 'tariff', 'entity_id' => $id]);
    sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Rate deleted' : 'Could not delete', 'redirect' => sl_route('Tariffs')]);
}
require_permission($id ? 'edit_shipping_tariff' : 'create_shipping_tariff');
$errors = [];
foreach (['country_origin' => 'Pick the origin', 'country_destiny' => 'Pick the destination', 'state_destinystates' => 'Pick a state', 'city_destinycities' => 'Pick a city', 'ship_mode' => 'Pick a service'] as $k => $msg) {
    if ((int) ($_POST[$k] ?? 0) <= 0) $errors[$k] = $msg;
}
$from = $num('initial_range'); $to = $num('final_range'); $price = $num('tariff_price'); $vol = $num('volumetric_percentage'); $mile = $num('price_mile') ?? 0.0; $min = $num('min_charge') ?? 0.0;
if ($from === null || $from < 0) $errors['initial_range'] = 'Enter where the band starts';
if ($to === null || $to <= 0) $errors['final_range'] = 'Enter where the band ends';
if ($from !== null && $to !== null && $to < $from) $errors['final_range'] = 'The band must end after it starts';
if ($price === null || $price <= 0) $errors['tariff_price'] = 'Enter the price';
if ($vol === null || $vol <= 0) $errors['volumetric_percentage'] = 'Enter the volume divisor';
if ($errors) sl_json(['success' => false, 'message' => 'Check the highlighted fields', 'errors' => $errors], 422);
if ($id && !sl_row('SELECT id FROM cdb_shipping_fees WHERE id = :id', [':id' => $id])) sl_json(['success' => false, 'message' => 'Rate not found'], 404);
$data = [
    'tariff_price' => $price, 'initial_range' => $from, 'final_range' => $to, 'country_origin' => (int) $_POST['country_origin'], 'country_destiny' => (int) $_POST['country_destiny'],
    'state_destinystates' => (int) $_POST['state_destinystates'], 'city_destinycities' => (int) $_POST['city_destinycities'], 'ship_mode' => (int) $_POST['ship_mode'],
    'volumetric_percentage' => $vol, 'price_mile' => $mile, 'client_id' => !empty($_POST['client_id']) ? (int) $_POST['client_id'] : null,
];
if (cdp_verifyRangeTariffsExist($data['country_origin'], $data['country_destiny'], $from, $to, $id ?: null, $data['state_destinystates'], $data['city_destinycities'], $data['ship_mode'], $data['client_id'])) {
    sl_json(['success' => false, 'message' => 'Another rate already covers part of this band on this route', 'errors' => ['initial_range' => 'Overlaps an existing band']], 422);
}
if ($id) { $data['id'] = $id; $ok = cdp_updateTariffs($data); }
else { $ok = cdp_insertTariffs($data); $id = $ok ? (int) sl_scalar('SELECT MAX(id) FROM cdb_shipping_fees') : 0; }
if (!$ok) sl_json(['success' => false, 'message' => 'Could not save the rate'], 500);
if (sl_columnExists('cdb_shipping_fees', 'min_charge')) {
    $vf = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['valid_from'] ?? '')) ? $_POST['valid_from'] : null;
    $vt = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['valid_until'] ?? '')) ? $_POST['valid_until'] : null;
    $db = new Conexion();
    $db->cdp_query('UPDATE cdb_shipping_fees SET min_charge = :m, valid_from = :f, valid_until = :t WHERE id = :id');
    $db->bind(':m', max(0, $min)); $db->bind(':f', $vf); $db->bind(':t', $vt); $db->bind(':id', $id);
    $db->cdp_execute();
}
cdp_activityLog(['module' => 'tariffs', 'action' => empty($_POST['id']) ? 'create' : 'update', 'action_label' => empty($_POST['id']) ? 'Added rate' : 'Updated rate', 'verb' => empty($_POST['id']) ? 'create' : 'update', 'outcome' => 'ok', 'summary' => "rate #{$id}: {$from} to {$to} at {$price}", 'entity_type' => 'tariff', 'entity_id' => $id]);
sl_json(['success' => true, 'message' => 'Rate saved', 'redirect' => sl_route('Tariffs')]);
