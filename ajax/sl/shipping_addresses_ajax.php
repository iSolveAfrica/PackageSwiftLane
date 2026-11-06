<?php
/** Shipping addresses (cdb_virtual_mailbox_addresses): add, edit, show/hide, delete. */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
require_permission(['manage_shipping_addresses', 'view_tools']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
$hasExtra = sl_columnExists('cdb_virtual_mailbox_addresses', 'is_active');
$op = (string) ($_POST['op'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$db = new Conexion();
$row = $id ? sl_row('SELECT * FROM cdb_virtual_mailbox_addresses WHERE id = :id', [':id' => $id]) : null;
if ($id && !$row) {
    sl_json(['success' => false, 'message' => 'Address not found'], 404);
}
$audit = fn($a, $s, $l) => cdp_activityLog(['module' => 'settings', 'action' => 'mailbox_' . $a, 'verb' => $a === 'delete' ? 'delete' : 'update', 'outcome' => 'ok', 'summary' => $s, 'entity_type' => 'mailbox_address', 'entity_id' => $id, 'entity_label' => $l]);
switch ($op) {
    case 'save':
        $countryId = (int) ($_POST['cdb_countries_id'] ?? 0);
        $country = $countryId ? (string) sl_scalar('SELECT name FROM cdb_countries WHERE id = :id', [':id' => $countryId], '') : '';
        $f = ['address' => trim((string) ($_POST['address'] ?? '')), 'city' => mb_substr(trim((string) ($_POST['city'] ?? '')), 0, 190), 'postcode' => mb_substr(trim((string) ($_POST['postcode'] ?? '')), 0, 40),
            'label' => mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 120), 'mode' => in_array($_POST['shipping_mode'] ?? '', ['air', 'sea', 'express'], true) ? $_POST['shipping_mode'] : 'air', 'transit' => mb_substr(trim((string) ($_POST['transit_days'] ?? '')), 0, 40)];
        $errors = [];
        if ($country === '') $errors['cdb_countries_id'] = 'Pick the country';
        if ($f['address'] === '') $errors['address'] = 'Enter the address';
        if ($errors) sl_json(['success' => false, 'message' => reset($errors), 'errors' => $errors], 422);
        $extra = $hasExtra ? ', label = :l, shipping_mode = :m, transit_days = :t, updated_at = NOW()' : '';
        if ($row) {
            $db->cdp_query("UPDATE cdb_virtual_mailbox_addresses SET cdb_countries_id = :ci, country = :co, city = :c, postcode = :p, address = :a $extra WHERE id = :id");
            $db->bind(':id', $id);
        } else {
            $cols = $hasExtra ? ', label, shipping_mode, transit_days, is_active, updated_at' : '';
            $vals = $hasExtra ? ', :l, :m, :t, 1, NOW()' : '';
            $db->cdp_query("INSERT INTO cdb_virtual_mailbox_addresses (cdb_countries_id, country, city, postcode, address, default_address $cols) VALUES (:ci, :co, :c, :p, :a, 0 $vals)");
        }
        $db->bind(':ci', $countryId); $db->bind(':co', $country); $db->bind(':c', $f['city']); $db->bind(':p', $f['postcode']); $db->bind(':a', $f['address']);
        if ($hasExtra) { $db->bind(':l', $f['label'] ?: $country); $db->bind(':m', $f['mode']); $db->bind(':t', $f['transit']); }
        $ok = $db->cdp_execute();
        if ($ok) $audit($row ? 'edit' : 'add', ($row ? 'edited' : 'added') . " shipping address $country " . $f['label'], $f['label'] ?: $country);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Saved and shown to customers' : 'Could not save', 'redirect' => 'shipping_addresses.php']);
        break;
    case 'toggle':
        if (!$row || !$hasExtra) sl_json(['success' => false, 'message' => $hasExtra ? 'Address not found' : 'Run the database upgrade first'], 409);
        $on = (int) !empty($_POST['value']);
        $db->cdp_query('UPDATE cdb_virtual_mailbox_addresses SET is_active = :o, updated_at = NOW() WHERE id = :id');
        $db->bind(':o', $on); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) $audit('toggle', ($on ? 'showed ' : 'hid ') . 'shipping address ' . ($row->label ?: $row->country), (string) ($row->label ?: $row->country));
        sl_json(['success' => (bool) $ok, 'message' => $ok ? ($on ? 'Shown to customers' : 'Hidden from customers') : 'Could not save']);
        break;
    case 'delete':
        if (!$row) sl_json(['success' => false, 'message' => 'Address not found'], 404);
        $db->cdp_query('DELETE FROM cdb_virtual_mailbox_addresses WHERE id = :id');
        $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) $audit('delete', 'deleted shipping address ' . ($row->label ?? $row->country), (string) $row->country);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Address deleted' : 'Could not delete']);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
