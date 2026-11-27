<?php
/**
 * Developer access (developer console): who may open which developer section, and the locked shutdown.
 * While the console is locked this is the only developer page that answers: assigned super admins see
 * the unlock form, everyone else gets 403. Writes: ajax/sl/dev_access_ajax.php. Logic: helpers/dev_access.php.
 */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'helpers/devops.php';
header('Cache-Control: no-store');
$page = ['id' => 'devaccess', 'title' => 'Developer access', 'board' => 'DevAccess', 'theme' => 'dk dvx', 'styles' => ['assets/swiftlane/dev_theme.css']];

if (cdp_devLocked()) {
    if (!cdp_devAssigned($user)) {
        header('Location: error403.php');
        exit;
    }
    $info = cdp_devLockInfo();
    $by = !empty($info['by']) ? cdp_devOne('SELECT fname, lname, username FROM cdb_users WHERE id = :i', [':i' => (int) $info['by']]) : null;
    $wait = cdp_devUnlockWait((int) $user->uid);
    $attempts = array_map(fn($a) => ['who' => trim(($a->fname ?? '') . ' ' . ($a->lname ?? '')) ?: (string) ($a->username ?? 'unknown'), 'ok' => (int) $a->ok === 1, 'ip' => (string) $a->ip, 'when' => sl_ago($a->created_at), 'at' => (string) $a->created_at], cdp_devUnlockAttempts(6));
    sl_render('DevAccess', $page, [
        'mode' => 'locked', 'by' => $by ? (trim($by->fname . ' ' . $by->lname) ?: $by->username) : ($info['by_name'] ?? 'a super admin'),
        'at' => (string) ($info['at'] ?? ''), 'ago' => sl_ago($info['at'] ?? ''), 'note' => (string) ($info['note'] ?? ''),
        'wait' => $wait, 'left' => cdp_devUnlockLeft((int) $user->uid), 'attempts' => $attempts,
    ]);
    exit;
}

// Console on: the page needs the "access" or the "shutdown" section.
if (!cdp_devCanUse($user) || (!cdp_devSectionAllowed($user, 'access') && !cdp_devSectionAllowed($user, 'shutdown'))) {
    header('Location: error403.php');
    exit;
}
cdp_devAccessEnsure();
$canAccess = cdp_devSectionAllowed($user, 'access');
$isOwner = cdp_devIsOwner($user);
$me = (int) $user->uid;
$sections = cdp_devSections();
$rows = [];
$devN = 0;
foreach ($canAccess ? cdp_devAccessMatrix() : [] as $u) {
    $devN += $u['assigned'] ? 1 : 0;
    $cells = [];
    foreach ($sections as $k => $s) {
        $has = in_array($k, $u['sections'], true);
        $lockedWhy = '';
        if ($u['owner']) $lockedWhy = 'The owner always has every section';
        elseif ($u['id'] === $me) $lockedWhy = 'You cannot change your own access';
        elseif (!$u['assigned']) $lockedWhy = 'Add this super admin to the console first';
        elseif (in_array($k, cdp_devOwnerOnlySections(), true) && !$isOwner) $lockedWhy = 'Only the owner grants this section';
        $cells[] = ['k' => $k, 'on' => $has, 'lock' => $lockedWhy];
    }
    $rows[] = $u + ['cells' => $cells, 'ini' => sl_initials($u['name']), 'n' => count($u['sections']), 'me' => $u['id'] === $me,
        'since' => $u['granted_at'] ? sl_date($u['granted_at']) : '', 'changedAgo' => $u['changed_at'] ? sl_ago($u['changed_at']) : ''];
}
$ownerId = cdp_devOwnerId();
$ownerRow = $ownerId ? cdp_devOne('SELECT fname, lname, username FROM cdb_users WHERE id = :i', [':i' => $ownerId]) : null;
$groups = [];
foreach ($sections as $k => $s) {
    $groups[$s[1]][] = ['k' => $k, 'label' => $s[0], 'desc' => $s[2]];
}
$mine = cdp_devUserSections($me);
$audit = array_map(fn($r) => ['who' => (string) $r->actor_name, 'ini' => sl_initials((string) $r->actor_name), 'what' => (string) $r->summary, 'when' => sl_ago($r->created_at), 'at' => (string) $r->created_at, 'bad' => (string) $r->outcome !== 'ok'],
    sl_tableExists('cdb_activity_log') ? sl_rows("SELECT actor_name, summary, outcome, created_at FROM cdb_activity_log WHERE module = 'devaccess' ORDER BY id DESC LIMIT 12") : []);
$auditWeek = sl_tableExists('cdb_activity_log') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE module = 'devaccess' AND created_at >= :s", [':s' => cdp_devAppNow(-7 * 86400)]) : 0;
$last = cdp_devState('console_lock');
sl_render('DevAccess', $page, [
    'mode' => 'manage', 'canAccess' => $canAccess, 'canShutdown' => cdp_devSectionAllowed($user, 'shutdown'), 'isOwner' => $isOwner,
    'owner' => $ownerRow ? (trim($ownerRow->fname . ' ' . $ownerRow->lname) ?: $ownerRow->username) : '—',
    'sections' => $sections, 'groups' => $groups, 'rows' => $rows, 'devN' => $devN, 'superN' => count($rows),
    'mine' => $mine, 'mineN' => count($mine), 'total' => count($sections), 'audit' => $audit, 'auditWeek' => $auditWeek,
    'ownerOnly' => cdp_devOwnerOnlySections(),
    'short' => ['overview' => 'Overview', 'services' => 'Services', 'logs' => 'Logs', 'metrics' => 'Metrics', 'infrastructure' => 'Infra', 'overrides' => 'Overrides',
        'alerts' => 'Alerts', 'deployments' => 'Deploys', 'github' => 'GitHub', 'database' => 'Database', 'issues' => 'Issues', 'modules' => 'Modules', 'access' => 'Access', 'shutdown' => 'Shutdown'],
    'lastUnlock' => is_array($last) && !empty($last['unlocked_at']) ? sl_ago($last['unlocked_at']) . (!empty($last['via']) ? ' (server recovery)' : '') : '',
]);
