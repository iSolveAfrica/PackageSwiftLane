<?php
/** Save staff productivity settings (cdp_spSaveSettings) for the Swift Lane page. */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/staff_activity.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
require_permission('view_staff_productivity');
if (!cdp_spCanView($user)) sl_json(['success' => false, 'message' => 'Admins only'], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
if (!cdp_spSettingsTableReady()) sl_json(['success' => false, 'message' => 'Settings table missing (run the upgrade)'], 409);
$before = cdp_spSettings();
ob_start();
$after = cdp_spSaveSettings($_POST, (int) $_SESSION['userid']);
ob_end_clean();
$bits = [];
foreach ((array) $after as $k => $v) if ((string) ($before[$k] ?? '') !== (string) $v) $bits[] = "$k " . ($before[$k] ?? '') . " → $v";
if ($bits) cdp_activityLog(['module' => 'reports', 'verb' => 'update', 'action' => 'staff_productivity.settings', 'action_label' => 'Changed productivity settings', 'outcome' => 'ok', 'summary' => 'staff productivity settings: ' . implode(', ', $bits)]);
sl_json(['success' => true, 'message' => $bits ? 'Settings saved' : 'Nothing changed']);
