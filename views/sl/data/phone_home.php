<?php
/**
 * Extra figures for the staff phone home screen (views/sl/phone/Main.php, design M-Home) that the web
 * Overview data (views/sl/data/overview.php) does not already hold: the system exchange rate, money paid
 * today, 12-day sparklines for the KPI tiles, parcels received this week (air / sea) and live activity
 * without page views. Same agency scope as the Overview.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_nav.php';

/** Daily counts (or sums) for the last $days days, oldest first, scoped by $where. */
function sl_phHomeDaily(string $sql, int $days): array
{
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $map = [];
    foreach (sl_rows($sql, [':from' => $from]) as $r) {
        $map[(string) $r->d] = (float) $r->n;
    }
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $out[$day] = $map[$day] ?? 0.0;
    }
    return $out;
}

/** 12 sparkline bars (.mx-sp) from a series; the last bar is "now". */
function sl_phHomeSpark(array $values): array
{
    $values = array_slice(array_values($values), -12);
    $peak = max(1, max($values ?: [0]));
    $out = [];
    foreach ($values as $i => $v) {
        $out[] = ['h' => 'height:' . max(6, (int) round($v / $peak * 100)) . '%', 'c' => $i === count($values) - 1 ? 'now' : ''];
    }
    return $out;
}

function sl_phoneHomeData($user): array
{
    $agency = sl_navAgencyWhere('a');
    $p = [];

    // Exchange rate set by the administrator (cdb_settings.exchange_rate, GH₵ per USD). Hidden when not set.
    $rate = function_exists('sl_exchangeRateValue') ? (float) sl_exchangeRateValue() : (float) sl_scalar('SELECT exchange_rate FROM cdb_settings LIMIT 1', [], 0);
    $p['fx'] = $rate > 0 ? number_format($rate, 2) : '';

    // Sparklines, 12 days
    $p['sparkAir'] = sl_phHomeSpark(sl_phHomeDaily("SELECT DATE(a.order_date) AS d, COUNT(*) AS n FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date >= :from $agency GROUP BY DATE(a.order_date)", 12));
    $sea = sl_phHomeDaily("SELECT DATE(a.order_date) AS d, COUNT(*) AS n FROM cdb_customers_packages a WHERE a.order_date >= :from $agency GROUP BY DATE(a.order_date)", 12);
    $p['sparkSea'] = sl_phHomeSpark($sea);
    $trk = 'FROM cdb_courier_track t' . ($agency !== '' ? ' JOIN cdb_add_order a ON t.order_track = CONCAT(a.order_prefix, a.order_no)' : '') . ' WHERE t.status_courier IN (8,15)' . ($agency !== '' ? " $agency" : '');
    $p['sparkOut'] = sl_phHomeSpark(sl_phHomeDaily("SELECT DATE(t.t_date) AS d, COUNT(DISTINCT t.order_track) AS n $trk AND t.t_date >= :from GROUP BY DATE(t.t_date)", 12));

    // Money paid today: recorded charges on air shipments (USD). Only for people who may see money.
    $p['paid'] = null;
    if (function_exists('cdp_financeEnabled') && cdp_financeEnabled() && function_exists('cdp_canViewMoney') && cdp_canViewMoney($user, 'dashboard')) {
        $join = $agency !== '' ? ' JOIN cdb_add_order a ON a.order_id = c.order_id' : '';
        $paid = sl_phHomeDaily("SELECT c.charge_date AS d, SUM(c.total) AS n FROM cdb_charges_order c$join WHERE c.charge_date >= :from $agency GROUP BY c.charge_date", 12);
        $vals = array_values($paid);
        $today = (float) end($vals);
        $yday = (float) ($vals[count($vals) - 2] ?? 0);
        $p['paid'] = ['today' => $today, 'spark' => sl_phHomeSpark($paid), 'note' => $yday > 0 ? (($today >= $yday ? '+' : '') . round(($today - $yday) / $yday * 100) . '% vs ' . date('D', strtotime('-1 day'))) : 'Recorded payments', 'up' => $today > $yday];
    }

    // Parcels received this week: air shipments + sea packages per day, last 7 days.
    $air7 = array_slice(sl_phHomeDaily("SELECT DATE(a.order_date) AS d, COUNT(*) AS n FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date >= :from $agency GROUP BY DATE(a.order_date)", 7), 0, 7, true);
    $sea7 = array_slice($sea, -7, 7, true);
    $bars = [];
    $peak = 1;
    foreach ($air7 as $day => $n) $peak = max($peak, $n + ($sea7[$day] ?? 0));
    $peak *= 1.15;
    $i = 0;
    foreach ($air7 as $day => $n) {
        $v = $n + ($sea7[$day] ?? 0);
        $bars[] = ['h' => 'height:' . max(6, (int) round($v / $peak * 100)) . '%', 'l' => substr(date('D', strtotime($day)), 0, 1), 'c' => ++$i === 7 ? 'now' : '', 't' => date('D j M', strtotime($day)) . ': ' . (int) $v];
    }
    $airN = (int) array_sum($air7);
    $seaN = (int) array_sum($sea7);
    $p['week'] = ['bars' => $bars, 'total' => $airN + $seaN, 'split' => $airN + $seaN > 0 ? 'Air ' . sl_pct($airN, $airN + $seaN) . '% · Sea ' . sl_pct($seaN, $airN + $seaN) . '%' : 'Nothing received yet'];

    // Live activity: real work only (no page views or sign-ins).
    $p['activity'] = [];
    if (sl_tableExists('cdb_activity_log')) {
        foreach (sl_rows("SELECT actor_name, summary, entity_label, module, created_at, outcome FROM cdb_activity_log WHERE action <> 'page_view' AND action NOT LIKE 'system.%' AND action NOT LIKE 'auth.%' AND (verb IS NULL OR verb <> 'view') AND summary NOT LIKE 'Opened %' ORDER BY id DESC LIMIT 4") as $r) {
            $p['activity'][] = ['who' => (string) ($r->actor_name ?: 'System'), 'ini' => sl_initials($r->actor_name ?: 'S'), 'what' => (string) $r->summary, 'sub' => trim((string) ($r->entity_label ?: ucfirst((string) $r->module))), 'at' => sl_ago($r->created_at), 'bad' => $r->outcome === 'fail'];
        }
    }
    $p['unread'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0', [':u' => (int) ($_SESSION['userid'] ?? 0)]);
    return $p;
}
