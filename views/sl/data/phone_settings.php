<?php
/**
 * Small read-only figures for the phone screens of the insights and settings group (views/sl/phone/*).
 * Only used on phones; every query is bound and scoped like the web report it summarises.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';

/** Date range of a phone report: ?from=Y-m-d&to=Y-m-d (default: this month). */
function sl_phRange(): array
{
    $from = (string) ($_GET['from'] ?? '');
    $to = (string) ($_GET['to'] ?? '');
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && strtotime($from) ? $from : date('Y-m-01');
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) && strtotime($to) ? $to : date('Y-m-d');
    if ($from > $to) [$from, $to] = [$to, $from];
    return [$from, $to];
}

/**
 * Count, weight and a bar series for one report of helpers/sl_reports.php, with the same filters the
 * web runner sends (status_courier, agency, customer_id, employee_id). Null for reports with no
 * parcel table behind them (money, top customers): the phone then shows the exports only.
 */
function sl_phReportStats(array $rep, string $from, string $to): ?array
{
    $map = [
        'Air shipments' => ['cdb_add_order', 'order_date', 'a.is_deleted = 0 AND a.is_pickup = 0', 'Shipments', 'i-plane'],
        'Pickups' => ['cdb_add_order', 'order_date', 'a.is_deleted = 0 AND a.is_pickup = 1', 'Pickups', 'i-truck'],
        'Registered packages' => ['cdb_customers_packages', 'order_date', '1=1', 'Packages', 'i-box'],
        'Air consolidations' => ['cdb_consolidate', 'c_date', '1=1', 'Consolidations', 'i-layers'],
        'Sea consolidations' => ['cdb_consolidate_packages', 'c_date', '1=1', 'Consolidations', 'i-layers'],
    ];
    if (!isset($map[$rep['group']])) {
        return null;
    }
    [$table, $col, $where, $noun, $icon] = $map[$rep['group']];
    $b = [':f' => $from, ':t' => $to . ' 23:59:59'];
    $where .= " AND a.$col BETWEEN :f AND :t";
    $p = $rep['params'];
    $int = fn($k) => (int) ($_GET[$k] ?? 0);
    if (in_array('status_courier', $p, true) && $int('status_courier') > 0) { $where .= ' AND a.status_courier = :st'; $b[':st'] = $int('status_courier'); }
    if ((in_array('agency', $p, true) || in_array('agency_courier', $p, true)) && $int('agency') > 0) { $where .= ' AND a.agency = :ag'; $b[':ag'] = $int('agency'); }
    if (in_array('customer_id', $p, true) && $int('customer_id') > 0) { $where .= ' AND a.sender_id = :cu'; $b[':cu'] = $int('customer_id'); }
    if (in_array('employee_id', $p, true) && !empty($rep['drivers']) && $int('employee_id') > 0) { $where .= ' AND a.driver_id = :dr'; $b[':dr'] = $int('employee_id'); }
    if ($table === 'cdb_add_order' && function_exists('sl_navAgencyWhere')) {
        $where .= ' ' . sl_navAgencyWhere('a');
    }
    $tot = sl_row("SELECT COUNT(*) n, COALESCE(SUM(a.total_weight), 0) w FROM $table a WHERE $where", $b);
    $days = [];
    foreach (sl_rows("SELECT DATE(a.$col) d, COUNT(*) n FROM $table a WHERE $where GROUP BY DATE(a.$col)", $b) as $r) {
        $days[$r->d] = (int) $r->n;
    }
    $span = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
    $bars = [];
    if ($span > 56) {
        for ($t = strtotime(date('Y-m-01', strtotime($from))); $t <= strtotime($to); $t = strtotime('+1 month', $t)) {
            $bars[date('Y-m', $t)] = ['l' => date('M', $t), 'n' => 0];
        }
        foreach ($days as $d => $n) { $k = substr($d, 0, 7); if (isset($bars[$k])) $bars[$k]['n'] += $n; }
        $bars = array_slice(array_values($bars), -8);
    } else {
        for ($i = 0; $i < (int) ceil($span / 7); $i++) $bars[$i] = ['l' => 'W' . ($i + 1), 'n' => 0];
        foreach ($days as $d => $n) { $i = intdiv((int) round((strtotime($d) - strtotime($from)) / 86400), 7); if (isset($bars[$i])) $bars[$i]['n'] += $n; }
        $bars = array_values($bars);
    }
    $mx = max(1, max(array_column($bars, 'n') ?: [1]));
    foreach ($bars as &$x) $x['h'] = max(6, (int) round($x['n'] / $mx * 100));
    unset($x);
    return ['noun' => $noun, 'icon' => $icon, 'n' => (int) $tot->n, 'w' => (float) $tot->w, 'bars' => $bars, 'per' => $span > 56 ? 'Per month' : 'Per week'];
}

/** Calls per hour for the last 12 hours, inbound API + outbound provider calls, as .mx-sp bar heights. */
function sl_phTrafficSpark(): array
{
    $hrs = array_fill(0, 12, ['n' => 0, 'e' => 0]);
    $add = function (string $sql) use (&$hrs) {
        foreach (sl_rows($sql) as $r) {
            $i = 11 - (int) $r->ago;
            if ($i >= 0 && $i < 12) { $hrs[$i]['n'] += (int) $r->n; $hrs[$i]['e'] += (int) $r->e; }
        }
    };
    if (sl_tableExists('cdb_api_request_log')) {
        $add('SELECT TIMESTAMPDIFF(HOUR, created_at, NOW()) ago, COUNT(*) n, SUM(status >= 400) e FROM cdb_api_request_log WHERE created_at >= NOW() - INTERVAL 12 HOUR GROUP BY ago');
    }
    if (sl_tableExists('cdb_message_log')) {
        $add("SELECT TIMESTAMPDIFF(HOUR, created_at, NOW()) ago, COUNT(*) n, SUM(status = 'failed') e FROM cdb_message_log WHERE created_at >= NOW() - INTERVAL 12 HOUR GROUP BY ago");
    }
    $mn = max(1, max(array_column($hrs, 'n')));
    $me = max(1, max(array_column($hrs, 'e')));
    return [
        'calls' => array_map(fn($x) => max(8, (int) round($x['n'] / $mn * 100)), $hrs),
        'errors' => array_map(fn($x) => max(8, (int) round($x['e'] / $me * 100)), $hrs),
    ];
}

/** Bars of an .mx-sp sparkline (last one lit). */
function sl_phSpark(array $heights): string
{
    $out = '<div class="mx-sp" aria-hidden="true">';
    $last = count($heights) - 1;
    foreach (array_values($heights) as $i => $h) {
        $out .= '<i' . ($i === $last ? ' class="now"' : '') . ' style="height:' . (int) $h . '%"></i>';
    }
    return $out . '</div>';
}
