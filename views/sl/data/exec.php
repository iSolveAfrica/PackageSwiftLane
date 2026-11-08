<?php
/** Data for the Management dashboard (ExecDashboard.dc.html): air first, sea as an option. */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_charts.php';
require_once CDP_ROOT . '/helpers/finance_switch.php';
require_once CDP_ROOT . '/helpers/rbac.php';
require_once CDP_ROOT . '/helpers/sl_nav.php';

function sl_execData($user, $core): array
{
    $d = [];
    $mode = (string) ($_GET['mode'] ?? 'air');
    if (!in_array($mode, ['air', 'sea', 'all'], true)) $mode = 'air';
    $per = (string) ($_GET['per'] ?? '30d');
    $days = ['7d' => 7, '30d' => 30, '90d' => 90, '1y' => 365][$per] ?? 30;
    $d['modes'] = array_map(fn($m) => ['f' => $m[0], 'label' => $m[1], 'cls' => $m[0] === $mode ? 'on' : ''], [['air', 'Air'], ['sea', 'Sea'], ['all', 'Air + sea']]);
    $d['pers'] = array_map(fn($p) => ['f' => $p, 'label' => $p, 'cls' => $p === $per ? 'on' : ''], ['7d', '30d', '90d', '1y']);
    $d['modeL'] = ['air' => 'Air freight', 'sea' => 'Sea freight', 'all' => 'Air and sea'][$mode];
    $money = cdp_canViewMoney($user, 'dashboard');
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $pfrom = date('Y-m-d', strtotime($from . " -$days days"));
    $agency = sl_navAgencyWhere('a');
    // one source query per mode: (table, date col, extra where)
    $src = [];
    if ($mode !== 'sea') $src[] = ['cdb_add_order', 'order_date', ' AND a.is_deleted = 0 AND a.status_courier <> 21' . $agency];
    if ($mode !== 'air') $src[] = ['cdb_customers_packages', 'order_date', ''];
    $agg = function (string $sel, string $a, string $b) use ($src) {
        $t = 0.0;
        foreach ($src as [$tbl, $col, $w]) $t += (float) sl_scalar("SELECT $sel FROM $tbl a WHERE a.$col >= :a AND a.$col < :b $w", [':a' => $a, ':b' => $b]);
        return $t;
    };
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $n = $agg('COUNT(*)', $from, $tomorrow); $pn = $agg('COUNT(*)', $pfrom, $from);
    $rev = $agg('COALESCE(SUM(a.total_order),0)', $from, $tomorrow); $prev = $agg('COALESCE(SUM(a.total_order),0)', $pfrom, $from);
    $kg = $agg('COALESCE(SUM(a.total_weight),0)', $from, $tomorrow);
    $cost = $agg('COALESCE(SUM(a.price_purchase),0)', $from, $tomorrow);
    if (sl_tableExists('cdb_operating_costs')) {
        $rate = (float) ($core->exchange_rate ?? 0);
        foreach (sl_rows("SELECT amount, currency, frequency FROM cdb_operating_costs WHERE is_active = 1 AND on_billing = 1 AND frequency <> 'once'") as $c) {
            $m = (float) $c->amount * (['yearly' => 1 / 12, 'weekly' => 52 / 12, 'daily' => 365 / 12, 'monthly' => 1][$c->frequency] ?? 1);
            $cost += (strtoupper($c->currency) === 'GHS' && $rate > 0 ? $m / $rate : $m) * $days / 30 * ($mode === 'all' ? 1 : ($mode === 'air' ? 0.6 : 0.4));
        }
    }
    $deliv = $mode !== 'sea' ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (8,15) AND a.order_date >= :f $agency", [':f' => $from]) : (int) sl_scalar("SELECT COUNT(*) FROM cdb_customers_packages a WHERE a.status_courier IN (8,15) AND a.order_date >= :f", [':f' => $from]);
    $late = $mode !== 'sea' ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (8,15) AND a.due_date IS NOT NULL AND a.payment_date > a.due_date AND a.order_date >= :f $agency", [':f' => $from]) : 0;
    $custs = $agg('COUNT(DISTINCT a.sender_id)', $from, $tomorrow);
    $chg = fn($a, $b) => $b > 0 ? (($a >= $b ? '▲ ' : '▼ ') . number_format(abs($a - $b) / $b * 100, 1) . '% vs previous') : 'no previous period';
    $weekly = [];
    for ($w = 11; $w >= 0; $w--) {
        $a = date('Y-m-d', strtotime('monday this week -' . $w . ' weeks'));
        $b = date('Y-m-d', strtotime($a . ' +7 days'));
        $weekly[] = ['a' => $a, 'n' => $agg('COUNT(*)', $a, $b)];
    }
    $wmax = max(1, max(array_column($weekly, 'n')));
    $spark = sl_sparkBars(array_column($weekly, 'n'), 4, array_map(fn($x) => 'Week of ' . sl_date($x['a']), $weekly));
    $hid = '••••';
    $d['kpis'] = [
        ['icon' => $mode === 'sea' ? 'i-ship' : 'i-plane', 'l' => 'Shipments', 'v' => sl_num($n), 's' => $chg($n, $pn), 'sp' => $spark],
        ['icon' => 'i-dollar', 'l' => 'Revenue', 'v' => $money ? sl_money($rev) : $hid, 's' => $money ? $chg($rev, $prev) : 'hidden for your role', 'sp' => []],
        ['icon' => 'i-chart', 'l' => 'Profit per shipment', 'v' => $money ? sl_money($n ? ($rev - $cost) / $n : 0) : $hid, 's' => $money ? 'after carrier and running costs' : 'hidden for your role', 'sp' => []],
        ['icon' => 'i-check', 'l' => 'On time', 'v' => ($deliv ? round(($deliv - $late) / $deliv * 100) : 100) . '%', 's' => sl_num($deliv) . ' delivered', 'sp' => []],
        ['icon' => 'i-users', 'l' => 'Active customers', 'v' => sl_num($custs), 's' => 'shipped in the period', 'sp' => []],
        ['icon' => 'i-box', 'l' => 'Weight moved', 'v' => sl_num($kg) . ' lb', 's' => $n ? number_format($kg / $n, 1) . ' lb average' : '—', 'sp' => []],
    ];
    $d['bars'] = array_map(fn($x, $i) => ['c' => $i === 11 ? 'on' : '', 'h' => 'height:' . max(3, sl_pct($x['n'], $wmax)) . '%', 't' => 'Week of ' . sl_date($x['a'], 'M j') . ': ' . $x['n']], $weekly, array_keys($weekly));
    $peakW = $weekly[array_search($wmax, array_column($weekly, 'n'))] ?? null;
    $d['peak'] = $peakW && $peakW['n'] ? sl_num($peakW['n']) . ' · week of ' . sl_date($peakW['a'], 'M j') : '—';
    $d['chartT'] = 'Volume, last 12 weeks';
    // branches
    $br = [];
    foreach ($src as [$tbl, $col, $w]) {
        foreach (sl_rows("SELECT COALESCE(o.name_off, 'Unassigned') AS n, COUNT(*) AS s, COALESCE(SUM(a.total_order),0) AS r, COALESCE(SUM(a.price_purchase),0) AS c FROM $tbl a LEFT JOIN cdb_offices o ON o.id = a.origin_off WHERE a.$col >= :f $w GROUP BY n", [':f' => $from]) as $r) {
            $k = html_entity_decode((string) $r->n);
            $br[$k] = ['n' => $k, 's' => ($br[$k]['s'] ?? 0) + (int) $r->s, 'r' => ($br[$k]['r'] ?? 0) + (float) $r->r, 'c' => ($br[$k]['c'] ?? 0) + (float) $r->c];
        }
    }
    usort($br, fn($a, $b) => $b['s'] <=> $a['s']);
    $d['br'] = array_map(fn($b) => ['n' => $b['n'], 's' => sl_num($b['s']), 'r' => $money ? sl_money($b['r']) : $hid, 'c' => $money ? sl_money($b['c']) : $hid, 'p' => $money ? sl_money($b['s'] ? ($b['r'] - $b['c']) / $b['s'] : 0) : $hid], array_slice($br, 0, 6));
    // watch list
    $held = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (12,25,28,29) $agency");
    $overdue = cdp_financeEnabled() ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_invoice = 23 AND a.due_date < CURDATE() $agency") : 0;
    $aging = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (6,32) AND a.order_date < CURDATE() - INTERVAL 30 DAY $agency");
    $unassigned = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND a.status_courier IN (1,14) AND (a.driver_id IS NULL OR a.driver_id = 0) $agency");
    $d['watch'] = [];
    if ($held) $d['watch'][] = ['d' => 'e', 't' => "$held parcels on hold", 's' => 'Rejected, held or not shipped', 'href' => sl_route('Warehouse', ['filter' => 'exceptions'])];
    if ($overdue) $d['watch'][] = ['d' => 'w', 't' => "$overdue invoices past due", 's' => 'Send reminders from Accounts Receivable', 'href' => sl_route('AccountsReceivable', ['filter' => 'overdue'])];
    if ($aging) $d['watch'][] = ['d' => 'w', 't' => "$aging parcels waiting over 30 days", 's' => 'Ready for pickup but not collected', 'href' => sl_route('PickupAging')];
    if ($unassigned) $d['watch'][] = ['d' => 'w', 't' => "$unassigned pickups without a rider", 's' => 'Assign on the dispatch board', 'href' => sl_route('RiderDispatch')];
    if (!$d['watch']) $d['watch'][] = ['d' => '', 't' => 'Nothing on the watch list', 's' => 'No exceptions right now', 'href' => sl_route('ActivityLogs')];
    // insights: rule-based sentences from the figures above
    $ins = [];
    if ($pn > 0) $ins[] = $d['modeL'] . ' volume is ' . ($n >= $pn ? 'up' : 'down') . ' ' . number_format(abs($n - $pn) / $pn * 100, 1) . "% on the previous $days days ($n vs $pn).";
    if ($br) $ins[] = $br[0]['n'] . ' handled ' . sl_pct($br[0]['s'], max(1, $n)) . '% of shipments in the period.';
    if ($n) $ins[] = 'The average shipment weighs ' . number_format($kg / $n, 1) . ' lb.';
    if ($deliv && $late) $ins[] = "$late of $deliv deliveries were paid after their due date.";
    $d['ins'] = $ins ?: ['Not enough data in this period yet.'];
    // reports to export
    $d['reps'] = [
        ['t' => 'General shipments', 's' => 'Excel for the period', 'f' => 'XLSX', 'href' => 'report_general_excel.php?range=' . rawurlencode(date('Y/m/d', strtotime($from)) . ' - ' . date('Y/m/d'))],
        ['t' => 'Packages registered', 's' => 'Excel for the period', 'f' => 'XLSX', 'href' => 'report_packages_registered_excel.php?range=' . rawurlencode(date('Y/m/d', strtotime($from)) . ' - ' . date('Y/m/d'))],
        ['t' => 'Accounts summary', 's' => 'Print or save as PDF', 'f' => 'PDF', 'href' => 'report_summary_print.php?range=' . rawurlencode(date('Y/m/d', strtotime($from)) . ' - ' . date('Y/m/d'))],
    ];
    if (!$money) $d['reps'] = array_slice($d['reps'], 0, 2);
    // where things live
    $cov = [
        ['Shipments and volume', 'Air Shipments', sl_route('AirShipments')], ['Sea packages', 'Sea Packages', sl_route('SeaPackages')], ['Consolidations', 'Consolidations', sl_route('Consolidations')],
        ['Revenue and margin', 'Financial Overview', sl_route('FinancialOverview')], ['Payments', 'Payments', sl_route('Payments')], ['Receivables', 'Accounts Receivable', sl_route('AccountsReceivable')],
        ['Operating costs', 'Operating costs', sl_route('CostSettings')], ['Warehouse', 'Warehouse', sl_route('Warehouse')],
        ['Riders', 'Rider dispatch', sl_route('RiderDispatch')], ['Customers', 'Customers', sl_route('Customers')], ['Staff productivity', 'Staff productivity', sl_route('StaffProductivity')],
        ['Support', 'Support reports', sl_route('CsReports')], ['Messages', 'Message logs', sl_route('MessageLogs')], ['All reports', 'Reports', sl_route('Reports')],
    ];
    $d['cov'] = array_map(fn($c, $i) => ['n' => $i + 1, 't' => $c[0], 'where' => $c[1], 'href' => $c[2], 'off' => '', 'pc' => 'delivered', 'st' => 'Live'], $cov, array_keys($cov));
    $d['money'] = $money;
    return $d;
}
