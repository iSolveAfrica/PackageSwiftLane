<?php
/** Receipt Studio: save the receipt layout (cdb_sl_settings receipt_options). POST options=JSON, or reset=1. */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../views/sl/data/labels.php';
require_login();
require_permission('view_tools');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
if (!sl_tableExists('cdb_sl_settings')) {
    sl_json(['success' => false, 'message' => 'Run the database upgrade first (cdb_sl_settings is missing)'], 409);
}
$reset = !empty($_POST['reset']);
$in = $reset ? [] : json_decode((string) ($_POST['options'] ?? ''), true);
if (!$reset && !is_array($in)) {
    sl_json(['success' => false, 'message' => 'Nothing to save'], 422);
}
$opt = sl_receiptOptionsClean($in);
$db = new Conexion();
$db->cdp_query('INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, :u, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
$db->bind(':k', 'receipt_options');
$db->bind(':v', json_encode($opt));
$db->bind(':u', (int) $_SESSION['userid']);
$ok = $db->cdp_execute();
if ($ok) {
    $hidden = array_keys($opt['hide']);
    cdp_activityLog(['module' => 'settings', 'verb' => 'update', 'action' => 'receipts.layout', 'action_label' => 'Changed receipt layout', 'entity_type' => 'setting', 'entity_id' => 'receipt_options',
        'summary' => $reset ? 'Receipt layout reset to default' : 'Receipt order: ' . implode(', ', $opt['order']) . ($hidden ? ' · hidden: ' . implode(', ', $hidden) : '')]);
}
sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Receipt layout saved' : 'Not saved', 'options' => $opt]);
