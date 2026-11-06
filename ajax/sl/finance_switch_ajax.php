<?php
/** Billing hub master switch: writes cdb_sl_settings.finance_enabled (read by cdp_financeEnabled when no constant is defined). */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/finance_switch.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
$user = new User();
if (!sl_userIs('superadmin') && !$user->cdp_hasPermission('manage_finance_switch')) sl_json(['success' => false, 'message' => 'Only a super admin can switch finance on or off'], 403);
if (!sl_tableExists('cdb_sl_settings')) sl_json(['success' => false, 'message' => 'Run the database upgrade first'], 409);
$on = in_array((string) ($_POST['value'] ?? $_POST['on'] ?? ''), ['1', 'true', 'on'], true);
$db = new Conexion();
$db->cdp_query('INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_by, updated_at) VALUES (\'finance_enabled\', :v, :u, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
$db->bind(':v', $on ? '1' : '0'); $db->bind(':u', (int) $_SESSION['userid']);
$ok = $db->cdp_execute();
cdp_activityLog(['module' => 'finance', 'action' => 'finance_switch', 'action_label' => 'Finance switched ' . ($on ? 'on' : 'off'), 'verb' => 'update', 'outcome' => $ok ? 'ok' : 'fail', 'summary' => 'switched the finance module ' . ($on ? 'on' : 'off'), 'entity_type' => 'setting', 'entity_label' => 'finance_enabled']);
$pinned = cdp_financePinnedByConfig();
sl_json(['success' => (bool) $ok, 'message' => 'Finance ' . ($on ? 'on' : 'off') . ($pinned ? ' saved, but CDP_FINANCE_ENABLED in config/config.php overrides it' : ''), 'reload' => true]);
