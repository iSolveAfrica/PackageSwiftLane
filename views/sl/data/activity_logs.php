<?php
/** Data for Activity Logs (ActivityLogs.dc.html). Reuses helpers/activity_log_query.php. */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/activity_log_query.php';

/** Map the design's segmented control onto module groups. */
function sl_alSegments(): array
{
    return [
        'all' => ['label' => 'All', 'modules' => []],
        'shipments' => ['label' => 'Shipments', 'modules' => ['shipments', 'packages', 'consolidations', 'pickups', 'warehouse', 'prealerts', 'locker']],
        'payments' => ['label' => 'Payments', 'modules' => ['finance', 'billing', 'payments']],
        'security' => ['label' => 'Security', 'modules' => ['auth', 'access', 'users', 'system']],
    ];
}

function sl_activityLogsData($user, $core): array
{
    $d = ['ready' => cdp_activityTableReady()];
    $f = cdp_alFilters();
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q !== '' && $f['search'] === '') {
        $f['search'] = mb_substr($q, 0, 120);
    }
    $segs = sl_alSegments();
    $filter = (string) ($_GET['filter'] ?? 'all');
    if (!isset($segs[$filter])) {
        $filter = 'all';
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $d += ['q' => $f['search'], 'filter' => $filter, 'page' => $page, 'per' => $per, 'count' => 0, 'rows' => [], 'feed' => [], 'users' => [], 'user_id' => $f['user_id'],
        'filters' => array_map(fn($k, $s) => ['f' => $k, 'label' => $s['label']], array_keys($segs), $segs),
        'canExport' => $user->cdp_hasPermission('export_activity_logs')];
    if (!$d['ready']) {
        return $d;
    }
    [$where, $binds] = cdp_alWhere($f);
    if ($segs[$filter]['modules']) {
        $in = [];
        foreach ($segs[$filter]['modules'] as $i => $m) {
            $in[] = ":seg$i";
            $binds[":seg$i"] = $m;
        }
        $where .= ' AND l.module IN (' . implode(',', $in) . ')';
        if ($filter === 'security') {
            $where .= " OR l.outcome IN ('denied','failure')";
            $where = "($where)";
        }
    }
    $d['count'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log l WHERE $where", $binds);
    $rows = sl_rows("SELECT l.id, l.created_at, l.user_id, l.actor_name, l.actor_username, l.module, l.action_label, l.verb, l.outcome, l.summary, l.entity_label, l.entity_id, l.ip
        FROM cdb_activity_log l WHERE $where ORDER BY l.created_at DESC, l.id DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds);
    $today = date('Y-m-d');
    foreach ($rows as $r) {
        $name = $r->actor_name ?: ($r->actor_username ?: 'Unknown');
        $d['rows'][] = [
            'id' => (int) $r->id,
            't' => $r->created_at ? (substr($r->created_at, 0, 10) === $today ? date('H:i:s', strtotime($r->created_at)) : date('M j H:i', strtotime($r->created_at))) : '—',
            'u' => $name, 'ini' => $name === 'Unknown' ? '?' : sl_initials($name), 'uid' => (int) $r->user_id,
            'a' => $r->action_label ?: cdp_activityVerbLabel($r->verb), 'b' => sl_alBadge($r->outcome, $r->verb),
            'tg' => $r->entity_label ?: ($r->entity_id ?: '—'), 'ip' => $r->ip ?: '—', 'summary' => $r->summary,
            'module' => cdp_activityModuleLabel($r->module),
        ];
    }
    foreach (sl_rows("SELECT l.created_at, l.actor_name, l.actor_username, l.summary, l.action_label, l.verb, l.outcome FROM cdb_activity_log l WHERE l.verb <> 'view' ORDER BY l.id DESC LIMIT 8") as $r) {
        $name = $r->actor_name ?: ($r->actor_username ?: 'System');
        $first = explode(' ', trim($name))[0] ?: 'System';
        $d['feed'][] = ['u' => $first, 'a' => $r->summary ?: strtolower((string) $r->action_label), 'w' => sl_ago($r->created_at), 'c' => sl_alFeedDot($r->outcome, $r->verb)];
    }
    $d['users'] = array_map(fn($r) => ['id' => (int) $r->user_id, 'name' => $r->actor_name ?: $r->actor_username], sl_rows("SELECT l.user_id, MAX(l.actor_name) AS actor_name, MAX(l.actor_username) AS actor_username FROM cdb_activity_log l WHERE l.user_id > 0 GROUP BY l.user_id ORDER BY actor_name LIMIT 200"));
    return $d;
}

function sl_alBadge($outcome, $verb): string
{
    if ($outcome === 'denied' || $outcome === 'failure') return 'warn';
    if (in_array($verb, ['deliver', 'payment', 'create'], true)) return 'ok';
    if ($verb === 'delete') return 'warn';
    return '';
}

function sl_alFeedDot($outcome, $verb): string
{
    if ($outcome === 'denied' || $outcome === 'failure') return 'r';
    if (in_array($verb, ['deliver', 'payment'], true)) return 'g';
    return '';
}
