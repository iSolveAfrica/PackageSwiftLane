<?php
// *************************************************************************
// * Customer Service — reports.                                           *
// *                                                                       *
// * GET action=report from to [channel category priority team_id         *
// *                            assignee_id]        JSON metrics           *
// * GET action=export  same filters                CSV of the tickets     *
// *                                                                       *
// * Staff only. Numbers come from the helpdesk tables (helpers/support.php *
// * cdp_csReport); an empty period returns zeros and empty lists.         *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/support.php');
require_login();

if (!cdp_csIsAgent($user) || !cdp_csEnsureTables()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Reports are for staff accounts.']);
    exit;
}

$d = function ($v, $def) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : $def; };
$from = $d($_GET['from'] ?? '', date('Y-m-d', strtotime('-29 days')));
$to   = $d($_GET['to'] ?? '', date('Y-m-d'));
if ($from > $to) {
    list($from, $to) = [$to, $from];
}
$f = [];
foreach (['channel', 'category', 'priority', 'team_id', 'assignee_id'] as $k) {
    if (!empty($_GET[$k])) {
        $f[$k] = (string) $_GET[$k];
    }
}

if (($_GET['action'] ?? 'report') === 'export') {
    $b = [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59'];
    $w = "t.created_at BETWEEN :f AND :t";
    foreach (['channel', 'category', 'priority'] as $k) {
        if (isset($f[$k])) {
            $w .= " AND t.$k = :$k";
            $b[":$k"] = $f[$k];
        }
    }
    if (isset($f['team_id'])) {
        $w .= ' AND t.team_id = :team';
        $b[':team'] = (int) $f['team_id'];
    }
    if (isset($f['assignee_id'])) {
        $w .= ' AND t.assignee_id = :asg';
        $b[':asg'] = (int) $f['assignee_id'];
    }
    $rows = cdp_csQ("SELECT t.*, tm.name AS team_name FROM cdb_cs_tickets t LEFT JOIN cdb_cs_teams tm ON tm.id = t.team_id WHERE $w ORDER BY t.id LIMIT 20000", $b);
    cdp_csEvent('report.exported', null, null, 'staff', cdp_csUid(), ['from' => $from, 'to' => $to, 'rows' => count($rows)]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="support-tickets-' . $from . '-to-' . $to . '.csv"');
    $cols = ['Reference', 'Created', 'Subject', 'Customer', 'Channel', 'Category', 'Priority', 'Status', 'Disposition', 'Team', 'Assignee', 'Language',
        'First response', 'Human first response', 'Resolved', 'Paused minutes', 'First response SLA due', 'First response breached', 'Resolution SLA due',
        'Resolution breached', 'Reopened', 'Rating', 'Internal'];
    echo "\xEF\xBB\xBF" . implode(',', array_map('cdp_csCsvCell', $cols)) . "\r\n";
    foreach ($rows as $t) {
        echo implode(',', array_map('cdp_csCsvCell', [
            $t->public_ref, $t->created_at, $t->subject, $t->contact_name, $t->channel, $t->category, $t->priority, $t->status, $t->disposition,
            $t->team_name, cdp_csUserName($t->assignee_id), $t->language, $t->first_response_at, $t->human_first_response_at, $t->resolved_at,
            $t->sla_paused_minutes, $t->sla_first_due, $t->first_breached_at ? 'yes' : 'no', $t->sla_resolve_due, $t->resolve_breached_at ? 'yes' : 'no',
            $t->reopen_count, $t->csat_score, $t->is_internal ? 'yes' : 'no',
        ])) . "\r\n";
    }
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$cur = cdp_csReport($from, $to, $f);
// Previous period of the same length, for comparison.
$days = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
$prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
$prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));
$prev = cdp_csReport($prevFrom, $prevTo, $f);
echo json_encode(['ok' => true, 'report' => $cur, 'previous' => [
    'from' => $prevFrom, 'to' => $prevTo, 'tickets' => $prev['tickets'], 'resolved' => $prev['resolved'],
    'human_first_median' => $prev['human_first_response']['median'], 'resolution_median' => $prev['resolution']['median'],
    'csat' => $prev['csat'], 'sla_resolve' => $prev['sla_resolve'], 'ai' => $prev['ai'],
]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
