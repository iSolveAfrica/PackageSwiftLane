<?php
/**
 * Modules: switch one module on/off, or everything on.
 * POST + CSRF (require_login), assigned super admin with the "modules" developer section
 * (cdp_devRequireSection), console not locked. Every change is written to cdb_activity_log.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/devops.php';
require_once __DIR__ . '/../../helpers/sl_modules.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
cdp_devRequireSection('modules', true);
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
if (!sl_tableExists('cdb_sl_settings')) {
    sl_json(['success' => false, 'message' => 'Run the database upgrade first (cdb_sl_settings is missing).'], 409);
}
$uid = (int) $_SESSION['userid'];
$mods = sl_modules();
$before = sl_moduleStates(true);
$op = (string) ($_POST['op'] ?? 'toggle');

if ($op === 'all_on') {
    $changed = [];
    foreach ($mods as $id => $m) {
        if (empty($m['core']) && !$before[$id] && sl_moduleSet($id, true, $uid)) {
            $changed[$id] = ['from' => 'off', 'to' => 'on'];
        }
    }
    cdp_activityLog(['module' => 'modules', 'action' => 'modules.all_on', 'verb' => 'update', 'outcome' => 'ok',
        'summary' => $changed ? 'switched every module on (' . implode(', ', array_map(fn($i) => $mods[$i]['label'], array_keys($changed))) . ')' : 'switched every module on (all were on)',
        'changes' => $changed, 'entity_type' => 'module', 'entity_label' => 'all modules']);
    sl_json(['success' => true, 'message' => 'Every module is on']);
}

if ($op !== 'toggle') {
    sl_json(['success' => false, 'message' => 'Unknown action'], 422);
}
$id = (string) ($_POST['key'] ?? '');
if (!isset($mods[$id]) || !empty($mods[$id]['core'])) {
    sl_json(['success' => false, 'message' => 'This module cannot be switched off here'], 422);
}
$on = !empty($_POST['value']) && $_POST['value'] !== '0';
if ($id === 'finance') {
    require_once __DIR__ . '/../../helpers/finance_switch.php';
    if (cdp_financePinnedByConfig()) {
        sl_json(['success' => false, 'message' => 'Financial is pinned by CDP_FINANCE_ENABLED in config/config.php'], 409);
    }
}
$ok = sl_moduleSet($id, $on, $uid);
cdp_activityLog(['module' => 'modules', 'action' => 'modules.toggle', 'verb' => 'update', 'outcome' => $ok ? 'ok' : 'fail',
    'summary' => ($on ? 'switched on ' : 'switched off ') . $mods[$id]['label'],
    'changes' => ['state' => ['from' => $before[$id] ? 'on' : 'off', 'to' => $on ? 'on' : 'off']],
    'entity_type' => 'module', 'entity_id' => $id, 'entity_label' => $mods[$id]['label']]);
sl_json(['success' => $ok, 'message' => $ok ? $mods[$id]['label'] . ($on ? ' is on' : ' is off') : 'Could not save']);
