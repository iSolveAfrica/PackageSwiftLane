<?php
/**
 * Hand-out Log (Warehouse menu): every package handed over at the warehouse, who handed it over,
 * and per-staff totals for any period. Source: cdb_warehouse_handovers (helpers/handover_log.php).
 * GET: range=today|7d|30d|month|lastmonth|custom  from= to= (Y-m-d)  staff=  method=code|skipped|legacy  mode=air|sea  q=  page=  export=csv
 */
require_once __DIR__ . '/ops_common.php';
require_once CDP_ROOT . '/helpers/handover_log.php';

/** [from, to, label] for the selected range (inclusive dates). */
function sl_hlRange(): array
{
    $r = (string) ($_GET['range'] ?? '7d');
    $today = date('Y-m-d');
    switch ($r) {
        case 'today': return ['today', $today, $today, 'Today'];
        case '30d': return ['30d', date('Y-m-d', strtotime('-29 days')), $today, 'Last 30 days'];
        case 'month': return ['month', date('Y-m-01'), $today, date('F Y')];
        case 'lastmonth': return ['lastmonth', date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month')), date('F Y', strtotime('first day of last month'))];
        case 'custom':
            $f = (string) ($_GET['from'] ?? ''); $t = (string) ($_GET['to'] ?? '');
            $ok = fn($x) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $x) && strtotime($x);
            if ($ok($f) && $ok($t)) { if ($f > $t) [$f, $t] = [$t, $f]; return ['custom', $f, $t, date('M j, Y', strtotime($f)) . ' – ' . date('M j, Y', strtotime($t))]; }
            // fall through to the default
        default: return ['7d', date('Y-m-d', strtotime('-6 days')), $today, 'Last 7 days'];
    }
}

function sl_handoverLogData($user, $core): array
{
    $okTable = cdp_handoverLogEnsure();
    [$range, $from, $to, $rangeL] = sl_hlRange();
    $staff = (int) ($_GET['staff'] ?? 0);
    $method = (string) ($_GET['method'] ?? '');
    if (!in_array($method, ['code', 'skipped', 'legacy'], true)) $method = '';
    $mode = (string) ($_GET['mode'] ?? '');
    if (!in_array($mode, ['air', 'sea'], true)) $mode = '';
    $q = trim((string) ($_GET['q'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 30;
    // Staff without the log permission see only their own hand-overs.
    $all = $user->cdp_hasPermission(['view_handover_log', 'view_staff_activity']) || sl_userIs('admin') || sl_userIs('superadmin');
    $me = (int) ($_SESSION['userid'] ?? 0);
    if (!$all) $staff = $me;

    $w = 'h.created_at BETWEEN :f AND :t';
    $b = [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59'];
    $ctx = cdp_getAgencyContext();
    if (!empty($ctx['is_restricted']) && !empty($ctx['agency_id'])) $w .= ' AND h.agency = ' . (int) $ctx['agency_id'];
    $base = $w; $bBase = $b;          // period (+ agency) only: for the staff table and the staff picker
    if ($staff) { $w .= ' AND h.handed_by = :s'; $b[':s'] = $staff; }
    if ($method) { $w .= ' AND h.code_method = :m'; $b[':m'] = $method; }
    if ($mode) { $w .= ' AND h.module = :md'; $b[':md'] = $mode; }
    if ($q !== '') { $w .= " AND (h.tracking LIKE :q OR h.sender_id IN (SELECT id FROM cdb_users WHERE CONCAT(fname, ' ', lname) LIKE :q2 OR locker LIKE :q3))"; $b[':q'] = $b[':q2'] = $b[':q3'] = '%' . $q . '%'; }

    $d = ['range' => $range, 'from' => $from, 'to' => $to, 'rangeL' => $rangeL, 'staff' => $staff, 'method' => $method, 'mode' => $mode, 'q' => $q, 'page' => $page, 'per' => $per, 'all' => $all, 'ok' => $okTable,
        'ranges' => [['today', 'Today'], ['7d', '7 days'], ['30d', '30 days'], ['month', 'This month'], ['lastmonth', 'Last month']]];
    if (!$okTable) return $d + ['kpi' => [], 'staffRows' => [], 'rows' => [], 'count' => 0, 'days' => [], 'hours' => [], 'staffOpts' => []];

    $T = 'FROM cdb_warehouse_handovers h WHERE ';
    $k = sl_row("SELECT COUNT(*) AS n, COUNT(DISTINCT h.sender_id) AS cust, COUNT(DISTINCT h.handed_by) AS staff, SUM(h.code_method = 'skipped') AS sk, SUM(h.code_method = 'code') AS cd,
            SUM(h.paid = 0) AS unpaid, SUM(h.module = 'air') AS air, SUM(h.module = 'sea') AS sea, COALESCE(SUM(h.weight), 0) AS w, COUNT(DISTINCT DATE(h.created_at)) AS dn $T $w", $b);
    $n = (int) ($k->n ?? 0);
    $days = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
    // previous period of the same length, for the trend
    $pf = date('Y-m-d', strtotime($from . ' -' . $days . ' days')); $pt = date('Y-m-d', strtotime($from . ' -1 day'));
    $prevW = str_replace('h.created_at BETWEEN :f AND :t', 'h.created_at BETWEEN :pf AND :pt', $w);
    $pb = $b; unset($pb[':f'], $pb[':t']); $pb[':pf'] = $pf . ' 00:00:00'; $pb[':pt'] = $pt . ' 23:59:59';
    $prev = (int) sl_scalar("SELECT COUNT(*) $T $prevW", $pb);
    $trend = $prev ? round(($n - $prev) / $prev * 100) : null;

    // per day (bars) and per hour
    $byDay = [];
    foreach (sl_rows("SELECT DATE(h.created_at) AS d, COUNT(*) AS n $T $w GROUP BY DATE(h.created_at)", $b) as $r) $byDay[$r->d] = (int) $r->n;
    $dayList = []; $mx = max(1, ...array_values($byDay ?: [0])); $busy = ['—', 0];
    $span = min($days, 62);
    for ($i = $span - 1; $i >= 0; $i--) {
        $dt = date('Y-m-d', strtotime($to . " -$i days"));
        $v = $byDay[$dt] ?? 0;
        if ($v > $busy[1]) $busy = [date('D M j', strtotime($dt)), $v];
        $dayList[] = ['d' => $dt, 'l' => $span <= 8 ? date('D', strtotime($dt)) : ($span <= 16 || $i % 5 === 0 ? date('j', strtotime($dt)) : ''), 'n' => $v, 'h' => sl_pct($v, $mx), 'tip' => date('D M j', strtotime($dt)) . ': ' . $v . ' handed out'];
    }
    $hours = array_fill(7, 14, 0);
    foreach (sl_rows("SELECT HOUR(h.created_at) AS hr, COUNT(*) AS n $T $w GROUP BY HOUR(h.created_at)", $b) as $r) if (isset($hours[(int) $r->hr])) $hours[(int) $r->hr] = (int) $r->n;
    $hmx = max(1, ...array_values($hours));
    $peakH = array_search(max($hours), $hours);

    // per staff member: period + this week + this month + today
    $wk = date('Y-m-d', strtotime('monday this week')); $mo = date('Y-m-01'); $td = date('Y-m-d');
    $staffRows = [];
    $agency = !empty($ctx['is_restricted']) && !empty($ctx['agency_id']) ? ' AND h.agency = ' . (int) $ctx['agency_id'] : '';
    $sw = $base . ($method ? ' AND h.code_method = :m' : '') . ($mode ? ' AND h.module = :md' : '');
    $sb = $bBase + ($method ? [':m' => $method] : []) + ($mode ? [':md' => $mode] : []);
    if (!$all) { $sw .= ' AND h.handed_by = :s'; $sb[':s'] = $me; }
    $rowsS = sl_rows("SELECT h.handed_by AS uid, COUNT(*) AS n, COUNT(DISTINCT h.sender_id) AS cust, SUM(h.code_method = 'skipped') AS sk, SUM(h.paid = 0) AS unpaid, MAX(h.created_at) AS last,
            COUNT(DISTINCT DATE(h.created_at)) AS dn $T $sw GROUP BY h.handed_by ORDER BY n DESC", $sb);
    $ids = array_map(fn($r) => (int) $r->uid, $rowsS);
    $extra = [];
    if ($ids) {
        foreach (sl_rows("SELECT h.handed_by AS uid, SUM(h.created_at >= :td) AS t, SUM(h.created_at >= :wk) AS w, SUM(h.created_at >= :mo) AS m FROM cdb_warehouse_handovers h WHERE h.created_at >= :min $agency AND h.handed_by IN (" . implode(',', $ids) . ') GROUP BY h.handed_by', [':td' => $td, ':wk' => $wk, ':mo' => $mo, ':min' => min($wk, $mo)]) as $r) $extra[(int) $r->uid] = $r;
    }
    $staffUsers = $ids ? sl_rows('SELECT id, fname, lname, userlevel FROM cdb_users WHERE id IN (' . implode(',', $ids) . ')') : [];
    $names = [];
    foreach ($staffUsers as $u) $names[(int) $u->id] = trim($u->fname . ' ' . $u->lname);
    $top = max(1, ...array_map(fn($r) => (int) $r->n, $rowsS ?: [(object) ['n' => 1]]));
    foreach ($rowsS as $r) {
        $id = (int) $r->uid; $x = $extra[$id] ?? null;
        $nm = $names[$id] ?? ($id ? 'Staff #' . $id : 'Unknown (older record)');
        $staffRows[] = ['id' => $id, 'name' => $nm, 'ini' => sl_initials($nm), 'n' => (int) $r->n, 'cust' => (int) $r->cust, 'sk' => (int) $r->sk, 'unpaid' => (int) $r->unpaid,
            'perDay' => number_format((int) $r->n / max(1, (int) $r->dn), 1), 'today' => (int) ($x->t ?? 0), 'week' => (int) ($x->w ?? 0), 'month' => (int) ($x->m ?? 0),
            'share' => sl_pct((int) $r->n, max(1, $n)), 'bar' => sl_pct((int) $r->n, $top), 'last' => sl_ago($r->last), 'on' => $id === $staff];
    }

    // the list
    $count = (int) sl_scalar("SELECT COUNT(*) $T $w", $b);
    $export = ($_GET['export'] ?? '') === 'csv';
    $lim = $export ? ' LIMIT 20000' : ' LIMIT ' . (($page - 1) * $per) . ", $per";
    $list = sl_rows("SELECT h.* $T $w ORDER BY h.created_at DESC, h.id DESC$lim", $b);
    $people = sl_usersById(array_merge(array_map(fn($r) => $r->sender_id, $list), array_map(fn($r) => $r->handed_by, $list)));
    $rows = [];
    foreach ($list as $r) {
        $c = $people[(int) $r->sender_id] ?? null; $s = $people[(int) $r->handed_by] ?? null;
        $rows[] = ['when' => sl_date($r->created_at, 'M j, H:i'), 'dt' => $r->created_at, 'trk' => $r->tracking, 'module' => $r->module, 'oid' => (int) $r->order_id,
            'href' => $r->module === 'sea' ? sl_route('PackageDetail', ['id' => (int) $r->order_id]) : sl_route('ShipmentDetail', ['id' => (int) $r->order_id]),
            'cust' => sl_userName($c), 'lock' => $c->locker ?? '', 'by' => $s ? sl_userName($s) : ((int) $r->handed_by ? 'Staff #' . (int) $r->handed_by : '—'), 'byId' => (int) $r->handed_by,
            'kind' => (int) $r->status_courier === 8 ? 'Delivered' : 'Picked up', 'method' => $r->code_method, 'methodL' => ['code' => 'Code checked', 'skipped' => 'Code skipped', 'legacy' => 'Before the log'][$r->code_method] ?? $r->code_method,
            'reason' => (string) ($r->skip_reason ?? ''), 'paid' => (int) $r->paid === 1, 'w' => sl_weight($r->weight)];
    }
    $staffOpts = [];
    if ($all) {
        foreach (sl_rows("SELECT DISTINCT h.handed_by AS uid FROM cdb_warehouse_handovers h WHERE h.created_at >= CURDATE() - INTERVAL 400 DAY $agency") as $r) $staffOpts[(int) $r->uid] = 1;
        $su = $staffOpts ? sl_rows('SELECT id, fname, lname FROM cdb_users WHERE id IN (' . implode(',', array_keys($staffOpts)) . ') ORDER BY fname') : [];
        $staffOpts = array_map(fn($u) => ['id' => (int) $u->id, 'name' => trim($u->fname . ' ' . $u->lname)], $su);
    }
    $sk = (int) ($k->sk ?? 0);
    return $d + [
        'n' => $n, 'count' => $count, 'rows' => $rows, 'export' => $export, 'staffRows' => $staffRows, 'staffOpts' => $staffOpts,
        'kpi' => [
            ['l' => 'Packages handed out', 'v' => sl_num($n), 's' => $trend === null ? $rangeL : ($trend >= 0 ? '▲ ' : '▼ ') . abs($trend) . '% vs previous ' . $days . ' day' . ($days === 1 ? '' : 's'), 'icon' => 'i-box'],
            ['l' => 'Customers served', 'v' => sl_num((int) ($k->cust ?? 0)), 's' => $n ? number_format($n / max(1, (int) $k->cust), 1) . ' packages each' : '—', 'icon' => 'i-users'],
            ['l' => 'Per day', 'v' => number_format($n / $days, 1), 's' => 'busiest ' . $busy[0] . ($busy[1] ? ' (' . $busy[1] . ')' : ''), 'icon' => 'i-calendar'],
            ['l' => 'Staff handing out', 'v' => sl_num((int) ($k->staff ?? 0)), 's' => 'peak hour ' . ($n ? sprintf('%02d:00', $peakH) : '—'), 'icon' => 'i-user'],
            ['l' => 'Code checked', 'v' => sl_num((int) ($k->cd ?? 0)), 's' => sl_pct((int) ($k->cd ?? 0), max(1, $n)) . '% of hand-outs', 'icon' => 'i-key'],
            ['l' => 'Code skipped', 'v' => sl_num($sk), 's' => $sk ? 'by managers · see reasons below' : 'none in this period', 'icon' => 'i-alert', 'warn' => $sk > 0, 'f' => 'skipped'],
            ['l' => 'Unpaid at hand-out', 'v' => sl_num((int) ($k->unpaid ?? 0)), 's' => 'balance collected at the counter', 'icon' => 'i-receipt'],
            ['l' => 'Air · Sea', 'v' => sl_num((int) ($k->air ?? 0)) . ' · ' . sl_num((int) ($k->sea ?? 0)), 's' => sl_weight($k->w ?? 0, 0) . ' handed out', 'icon' => 'i-layers'],
        ],
        'days' => $dayList, 'hours' => array_map(fn($h, $v) => ['l' => sprintf('%02d', $h), 'n' => $v, 'h' => sl_pct($v, $hmx)], array_keys($hours), array_values($hours)),
    ];
}
