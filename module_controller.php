<?php
/**
 * Modules (developer console, section "modules"): switch business modules fully on or off.
 * Enforcement: helpers/sl_modules.php (nav, pages, AJAX, cron, API). Writes: ajax/sl/modules_ajax.php.
 * Access: assigned super admin (cdp_devCanUse) with the "modules" developer section; locked console -> unlock form.
 */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'helpers/devops.php';
cdp_devRequireSection('modules');
require_once 'helpers/sl_modules.php';
require_once 'helpers/sl_nav.php';
require_once 'helpers/finance_switch.php';

$mods = sl_modules();
// Is the central guard wired (loader.php calls sl_moduleGuardRequest('any'))? Without it only pages that
// call sl_gate() are refused; AJAX, public pages, cron jobs and the API keep answering. Say so, exactly.
$central = sl_moduleCentralGuard();
$gated = function (string $rel) use ($central): bool {
    if ($central) {
        return true;
    }
    $src = @file_get_contents(CDP_ROOT . '/' . $rel, false, null, 0, 6000);
    return is_string($src) && strpos($src, 'sl_gate(') !== false;
};
$states = sl_moduleStates(true);
$changed = sl_moduleChanged();
$q = trim((string) ($_GET['q'] ?? ''));
$filter = in_array($_GET['filter'] ?? '', ['all', 'on', 'off'], true) ? $_GET['filter'] : 'all';
$groups = [];
$on = $switchable = $offN = 0;
$custOn = $custTotal = 0;
$skipJobs = [];
foreach ($mods as $id => $m) {
    $core_ = !empty($m['core']);
    $enabled = $core_ || $states[$id];
    $switchable += $core_ ? 0 : 1;
    $on += ($enabled && !$core_) ? 1 : 0;
    $offN += $enabled ? 0 : 1;
    if ($m['customer']) { $custTotal++; $custOn += $enabled ? 1 : 0; }
    $imp = sl_moduleImpact($id);
    if (!$enabled && $central) {
        foreach ($imp['jobs'] as $j) $skipJobs[] = basename($j, '.php');
    }
    if ($q !== '' && stripos($m['label'] . ' ' . $m['desc'], $q) === false) continue;
    if ($filter === 'on' && !$enabled) continue;
    if ($filter === 'off' && $enabled) continue;
    $tags = [];
    if ($imp['nav']) $tags[] = count($imp['nav']) . ' menu item' . (count($imp['nav']) === 1 ? '' : 's');
    if ($imp['pages']) $tags[] = count($imp['pages']) . ' page' . (count($imp['pages']) === 1 ? '' : 's');
    if ($imp['ajax']) $tags[] = count($imp['ajax']) . ' endpoint' . (count($imp['ajax']) === 1 ? '' : 's');
    if ($imp['jobs']) $tags[] = count($imp['jobs']) . ' scheduled job' . (count($imp['jobs']) === 1 ? '' : 's');
    if ($m['customer']) $tags[] = 'Customer portal';
    if ($m['api']) $tags[] = 'API';
    $pinned = $id === 'finance' && cdp_financePinnedByConfig();
    $what = $notYet = [];
    if ($imp['nav']) $what[] = count($imp['nav']) . ' menu item' . (count($imp['nav']) === 1 ? '' : 's') . ' (' . implode(', ', array_slice($imp['nav'], 0, 6)) . (count($imp['nav']) > 6 ? ', ...' : '') . ') disappear';
    if ($imp['pages']) {
        $g = count(array_filter($imp['pages'], $gated));
        if ($g) $what[] = ($g === count($imp['pages']) ? $g : $g . ' of ' . count($imp['pages'])) . ' pages answer "This module is switched off"';
        if ($g < count($imp['pages'])) $notYet[] = (count($imp['pages']) - $g) . ' pages (public or legacy ones that do not call sl_gate) still open';
    }
    if ($imp['ajax']) {
        if ($central) $what[] = count($imp['ajax']) . ' AJAX endpoints refuse with module_disabled';
        else $notYet[] = count($imp['ajax']) . ' AJAX endpoints still answer';
    }
    if ($imp['jobs']) {
        $jn = implode(', ', array_map(fn($j) => basename($j, '.php'), $imp['jobs']));
        if ($central) $what[] = 'scheduled jobs skip: ' . $jn;
        else $notYet[] = 'scheduled jobs still run: ' . $jn;
    }
    if ($m['api']) {
        if ($central) $what[] = 'its API resources answer module_disabled';
        else $notYet[] = 'its API resources still answer';
    }
    $notYet[] = 'dashboards still show its panels where they have any';
    $confirm = $enabled
        ? 'Switch off ' . $m['label'] . " for everyone?\n\n- " . ($what ? implode("\n- ", $what) : 'its menu items disappear')
            . "\n\nNot covered" . ($central ? '' : ' until loader.php runs the central module guard') . ":\n- " . implode("\n- ", $notYet)
            . "\n\nNothing is deleted. You can switch it back on here at any time."
        : 'Switch ' . $m['label'] . ' back on? Its menus, pages, endpoints and jobs return for everyone with permission.';
    $ch = $changed[$id] ?? null;
    $groups[$m['group']]['items'][] = [
        'id' => $id, 'name' => $m['label'], 'desc' => $m['desc'], 'tags' => $tags, 'on' => $enabled, 'core' => $core_, 'pinned' => $pinned,
        'confirm' => $confirm, 'imp' => $imp,
        'changed' => $ch && $ch['at'] ? ($enabled ? 'On' : 'Off') . ' since ' . sl_ago($ch['at']) . ($ch['by'] !== '' ? ' · ' . $ch['by'] : '') : ($core_ ? 'Always on' : 'On since install'),
    ];
}
$out = [];
foreach (sl_moduleGroups() as $k => $g) {
    if (empty($groups[$k])) continue;
    $n = count(array_filter($groups[$k]['items'], fn($i) => $i['on']));
    $out[] = $g + ['key' => $k, 'items' => $groups[$k]['items'], 'count' => $n . ' / ' . count($groups[$k]['items']) . ' on'];
}
$log = array_map(fn($r) => ['who' => (string) $r->actor_name, 'ini' => sl_initials((string) $r->actor_name), 'what' => (string) $r->summary, 'when' => sl_ago($r->created_at), 'at' => (string) $r->created_at, 'bad' => (string) $r->outcome !== 'ok'],
    sl_tableExists('cdb_activity_log') ? sl_rows("SELECT actor_name, summary, outcome, created_at FROM cdb_activity_log WHERE module = 'modules' ORDER BY id DESC LIMIT 8") : []);

sl_render('ModuleController', ['id' => 'devmod', 'title' => 'Modules', 'board' => 'ModuleController', 'theme' => 'dk dvx', 'styles' => ['assets/swiftlane/dev_theme.css']], [
    'groups' => $out, 'onCount' => $on, 'switchable' => $switchable, 'offCount' => $offN, 'onPct' => sl_pct($on, $switchable),
    'custOn' => $custOn, 'custTotal' => $custTotal, 'custPct' => sl_pct($custOn, $custTotal),
    'skipJobs' => array_values(array_unique($skipJobs)), 'log' => $log, 'q' => $q, 'filter' => $filter,
    'staffN' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel NOT IN (1) AND active = 1'),
    'custN' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel = 1 AND active = 1'),
    'filters' => [['f' => 'all', 'label' => 'All'], ['f' => 'on', 'label' => 'On'], ['f' => 'off', 'label' => 'Off']],
    'financePinned' => cdp_financePinnedByConfig(), 'central' => $central,
]);
