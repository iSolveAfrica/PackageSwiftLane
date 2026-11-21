<?php
/**
 * Printer settings: save my own (anyone who prints labels) or the team default (view_tools).
 * POST scope=me|team, the fields of sl_printerClean(); reset=1 removes my own so the team default applies.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../views/sl/data/labels.php';
require_login();
require_permission(['print_label', 'view_tools']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
if (!sl_tableExists('cdb_sl_settings')) sl_json(['success' => false, 'message' => 'Run the database upgrade first (cdb_sl_settings is missing)'], 409);
global $user;
$uid = (int) $_SESSION['userid'];
$scope = ($_POST['scope'] ?? 'me') === 'team' ? 'team' : 'me';
if ($scope === 'team' && !$user->cdp_hasPermission('view_tools')) sl_json(['success' => false, 'message' => 'Only administrators can change the team default'], 403);
$key = $scope === 'team' ? 'printer_options' : 'printer_options_u' . $uid;
$db = new Conexion();
if (!empty($_POST['reset']) && $scope === 'me') {
    $db->cdp_query('DELETE FROM cdb_sl_settings WHERE setting_key = :k');
    $db->bind(':k', $key);
    $ok = $db->cdp_execute();
    sl_json(['success' => (bool) $ok, 'message' => 'Using the team printer settings']);
}
$opt = sl_printerClean($_POST);
$db->cdp_query('INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, :u, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
$db->bind(':k', $key);
$db->bind(':v', json_encode($opt));
$db->bind(':u', $uid);
$ok = $db->cdp_execute();
if ($ok) cdp_activityLog(['module' => 'settings', 'verb' => 'update', 'action' => 'labels.printer', 'action_label' => 'Changed printer settings', 'entity_type' => 'setting', 'entity_id' => $key, 'summary' => ($scope === 'team' ? 'Team' : 'Personal') . ' printer settings: ' . $opt['name']]);
sl_json(['success' => (bool) $ok, 'message' => $ok ? ($scope === 'team' ? 'Team printer settings saved' : 'Your printer settings saved') : 'Not saved', 'options' => $opt]);
