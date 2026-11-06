<?php
/** Module Controller: switch a module on/off, or everything on (super admin only). */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/sl_modules.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
if (!sl_userIs('superadmin')) {
    sl_json(['success' => false, 'message' => 'Only a super admin can switch modules'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
$uid = (int) $_SESSION['userid'];
$mods = sl_modules();
if (($_POST['op'] ?? '') === 'all_on') {
    foreach ($mods as $id => $m) sl_moduleSet($id, true, $uid);
    cdp_activityLog(['module' => 'modules', 'action' => 'all_on', 'verb' => 'update', 'outcome' => 'ok', 'summary' => 'switched every module on']);
    sl_json(['success' => true, 'message' => 'Every module is on']);
}
$id = (string) ($_POST['key'] ?? '');
if (!isset($mods[$id]) || !empty($mods[$id]['core'])) {
    sl_json(['success' => false, 'message' => 'This module cannot be switched off'], 422);
}
$on = !empty($_POST['value']);
$ok = sl_moduleSet($id, $on, $uid);
if ($ok) {
    cdp_activityLog(['module' => 'modules', 'action' => 'toggle', 'verb' => 'update', 'outcome' => 'ok', 'summary' => ($on ? 'switched on ' : 'switched off ') . $mods[$id]['label'], 'entity_type' => 'module', 'entity_id' => $id, 'entity_label' => $mods[$id]['label']]);
}
sl_json(['success' => $ok, 'message' => $ok ? $mods[$id]['label'] . ($on ? ' on' : ' off') : 'Could not save']);
