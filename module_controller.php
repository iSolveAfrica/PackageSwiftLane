<?php
/** Module Controller (super admin): switch modules on or off (cdb_sl_settings module_<id>). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (!sl_userIs('superadmin')) {
    header('Location: error403.php');
    exit;
}
require_once 'helpers/sl_modules.php';
$mods = sl_modules();
$states = sl_moduleStates();
$q = trim((string) ($_GET['q'] ?? ''));
$filter = (string) ($_GET['filter'] ?? 'all');
$groups = [];
$on = 0;
$custOn = 0;
$custTotal = 0;
foreach ($mods as $id => $m) {
    $enabled = !empty($m['core']) || $states[$id];
    $on += $enabled ? 1 : 0;
    if ($m['customer']) { $custTotal++; $custOn += $enabled ? 1 : 0; }
    if ($q !== '' && stripos($m['label'] . ' ' . $m['desc'], $q) === false) continue;
    if ($filter === 'on' && !$enabled) continue;
    if ($filter === 'off' && $enabled) continue;
    $tags = [];
    if ($m['nav']) $tags[] = ['cls' => '', 'label' => count($m['nav']) . ' menu items'];
    if ($m['customer']) $tags[] = ['cls' => '', 'label' => 'Customer portal'];
    if ($m['api']) $tags[] = ['cls' => '', 'label' => 'API'];
    if (!empty($m['core'])) $tags[] = ['cls' => '', 'label' => 'Always on'];
    $groups[$m['group']]['items'][] = ['id' => $id, 'name' => $m['label'], 'desc' => $m['desc'], 'tags' => $tags, 'on' => $enabled, 'lock' => !empty($m['core']), 'cls' => $enabled ? '' : 'off', 'state' => $enabled ? 'On' : 'Off'];
}
$gm = sl_moduleGroups();
$out = [];
foreach ($gm as $k => $g) {
    if (empty($groups[$k])) continue;
    $n = count(array_filter($groups[$k]['items'], fn($i) => $i['on']));
    $out[] = $g + ['key' => $k, 'items' => $groups[$k]['items'], 'count' => $n . ' / ' . count($groups[$k]['items']) . ' on'];
}
$log = array_map(fn($r) => ['who' => sl_initials((string) $r->actor_name), 'what' => $r->summary, 'when' => sl_ago($r->created_at)],
    sl_tableExists('cdb_activity_log') ? sl_rows("SELECT actor_name, summary, created_at FROM cdb_activity_log WHERE module = 'modules' ORDER BY id DESC LIMIT 6") : []);
$total = count($mods);
$apiAll = 0; $apiOn = 0;
foreach ($mods as $id => $m) { foreach ($m['api'] as $a) { $apiAll++; $apiOn += ($states[$id] || !empty($m['core'])) ? 1 : 0; } }
sl_render('ModuleController', ['id' => 'devmod', 'title' => 'Module Controller', 'board' => 'ModuleController'], [
    'groups' => $out, 'onCount' => $on, 'total' => $total, 'offCount' => $total - $on, 'onW' => 'width:' . sl_pct($on, $total) . '%',
    'custOn' => $custOn, 'custTotal' => $custTotal, 'custW' => 'width:' . sl_pct($custOn, $custTotal) . '%', 'apiOn' => $apiOn, 'apiAll' => $apiAll, 'apiW' => 'width:' . sl_pct($apiOn, $apiAll) . '%',
    'staffN' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel NOT IN (1) AND active = 1'), 'log' => $log, 'q' => $q, 'filter' => $filter,
    'filters' => [['f' => 'all', 'label' => 'All'], ['f' => 'on', 'label' => 'On'], ['f' => 'off', 'label' => 'Off']],
]);
