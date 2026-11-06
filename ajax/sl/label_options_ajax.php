<?php
/** Label Studio: save one label option (cdb_sl_settings label_options / label_small_options). */
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
$group = ($_POST['group'] ?? '') === 'small' ? 'small' : 'big';
$key = (string) ($_POST['key'] ?? '');
$raw = (string) ($_POST['value'] ?? '');
$opt = sl_labelOptions()[$group];
$flags = $group === 'big' ? ['sender', 'fragile', 'phone', 'qr', 'contents', 'value', 'customs', 'code', 'postage', 'care', 'ret'] : ['tag', 'barcode', 'code'];
if (in_array($key, $flags, true)) {
    $opt[$key] = (int) !empty($raw);
} elseif ($group === 'big' && $key === 'size' && in_array($raw, ['46', '44', '12'], true)) {
    $opt[$key] = $raw;
} elseif ($group === 'big' && $key === 'copies') {
    $opt[$key] = max(1, min(10, (int) $raw));
} elseif ($group === 'big' && $key === 'printer') {
    $opt[$key] = mb_substr(trim(strip_tags($raw)), 0, 80) ?: 'Default printer';
} elseif ($group === 'small' && $key === 'use' && in_array($raw, ['locker', 'track', 'carrier'], true)) {
    $opt[$key] = $raw;
} elseif ($group === 'small' && $key === 'size' && in_array($raw, ['14', '17', '20'], true)) {
    $opt[$key] = $raw;
} else {
    sl_json(['success' => false, 'message' => 'Unknown option'], 422);
}
$settingKey = $group === 'small' ? 'label_small_options' : 'label_options';
$db = new Conexion();
$db->cdp_query('INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, :u, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
$db->bind(':k', $settingKey);
$db->bind(':v', json_encode($opt));
$db->bind(':u', (int) $_SESSION['userid']);
$ok = $db->cdp_execute();
if ($ok) {
    cdp_activityLog(['module' => 'settings', 'verb' => 'update', 'action' => 'labels.options', 'action_label' => 'Changed label options', 'entity_type' => 'setting', 'entity_id' => $settingKey, 'summary' => "label option $key = " . json_encode($opt[$key])]);
}
sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Label options saved' : 'Not saved']);
