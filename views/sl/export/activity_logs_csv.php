<?php
/** CSV export of the filtered activity log (same filters as the page; capped). */
$d = sl_activityLogsData($user, $core);
if (function_exists('cdp_activityLog')) {
    cdp_activityLog(['module' => 'reports', 'action' => 'export', 'action_label' => 'Exported activity log', 'verb' => 'export', 'outcome' => 'ok', 'summary' => 'exported activity log (' . $d['count'] . ' rows, filter ' . $d['filter'] . ')']);
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="activity-log-' . date('Ymd-His') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['When', 'User', 'Module', 'Action', 'Summary', 'Target', 'IP address']);
$per = 5000;
$pages = min(10, (int) ceil($d['count'] / $per));
for ($p = 1; $p <= max(1, $pages); $p++) {
    $_GET['page'] = $p;
    $chunk = $p === 1 && $d['per'] >= $per ? $d : null;
    // re-query with a bigger page size: temporarily widen the provider's page
    $rows = sl_activityExportRows($p, $per);
    foreach ($rows as $r) {
        fputcsv($out, [$r->created_at, $r->actor_name ?: $r->actor_username, cdp_activityModuleLabel($r->module), $r->action_label ?: cdp_activityVerbLabel($r->verb), $r->summary, $r->entity_label ?: $r->entity_id, $r->ip]);
    }
    if (count($rows) < $per) break;
}
fclose($out);

function sl_activityExportRows(int $page, int $per): array
{
    $f = cdp_alFilters();
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q !== '' && $f['search'] === '') $f['search'] = mb_substr($q, 0, 120);
    [$where, $binds] = cdp_alWhere($f);
    $segs = sl_alSegments();
    $filter = (string) ($_GET['filter'] ?? 'all');
    if (!empty($segs[$filter]['modules'])) {
        $in = [];
        foreach ($segs[$filter]['modules'] as $i => $m) { $in[] = ":seg$i"; $binds[":seg$i"] = $m; }
        $where .= ' AND l.module IN (' . implode(',', $in) . ')';
    }
    return sl_rows("SELECT l.* FROM cdb_activity_log l WHERE $where ORDER BY l.created_at DESC, l.id DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds);
}
