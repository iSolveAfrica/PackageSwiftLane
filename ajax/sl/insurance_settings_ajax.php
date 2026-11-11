<?php
/** Insurance charge used by the air shipment quick-load workflow. */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_auth.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
require_permission('view_tools');
$mode = (string) ($_POST['insurance_mode'] ?? '');
$amount = filter_var($_POST['insurance_fixed'] ?? null, FILTER_VALIDATE_FLOAT);
if (!in_array($mode, ['percentage', 'fixed'], true) || $amount === false || $amount < 0 || $amount > 100000) {
    sl_json(['success' => false, 'message' => 'Choose a valid insurance mode and fixed amount'], 422);
}
$uid = (int) $_SESSION['userid'];
if (!sl_settingSet('shipment_insurance_mode', $mode, $uid) || !sl_settingSet('shipment_insurance_fixed', (string) $amount, $uid)) {
    sl_json(['success' => false, 'message' => 'Could not save insurance settings'], 500);
}
sl_json(['success' => true, 'message' => 'Insurance settings saved']);
