<?php
/**
 * Rider overview (index.php, dashboard_type 'driver'): the rider's own workload only.
 * The working tool stays the Rider app (DriverApp); this page summarises the day and links into it.
 */
require_once __DIR__ . '/ops_common.php';
require_once __DIR__ . '/overview_hero.php';
require_once __DIR__ . '/track_console.php';

function sl_riderHomeData($user, $core, $userData): array
{
    $uid = (int) ($_SESSION['userid'] ?? 0);
    $own = 'a.is_deleted = 0 AND a.driver_id = ' . $uid;
    $n = fn(string $w) => (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE $own AND $w");
    $deliveredOn = "EXISTS (SELECT 1 FROM cdb_courier_track t WHERE t.order_track = CONCAT(a.order_prefix, a.order_no) AND t.status_courier IN (8,15) AND t.t_date >= %s)";
    $daily = sl_ovZeroDays();
    foreach (sl_rows("SELECT DATE(t.t_date) AS day, COUNT(DISTINCT a.order_id) AS n FROM cdb_add_order a JOIN cdb_courier_track t ON t.order_track = CONCAT(a.order_prefix, a.order_no)
            WHERE $own AND t.status_courier IN (8,15) AND t.t_date >= :d GROUP BY DATE(t.t_date)", [':d' => array_key_first($daily)]) as $r) {
        if (isset($daily[$r->day])) $daily[$r->day] = (int) $r->n;
    }
    $h = (int) date('G');
    $d = [
        'greet' => ($h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening')) . ' · ' . date('l j M'),
        'first' => (string) ($userData->fname ?? ''),
        'collect' => $n('a.status_courier = 14'),
        'onRoute' => $n('a.status_courier = 7'),
        'pickups' => $n('a.is_pickup = 1 AND a.status_courier IN (1,14)'),
        'today' => $n(sprintf($deliveredOn, 'CURDATE()')),
        'month' => $n(sprintf($deliveredOn, "DATE_FORMAT(CURDATE(), '%Y-%m-01')")),
        'assignedMonth' => $n("a.order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
        'bars' => sl_sparkBars(array_values($daily), 4, array_map(fn($x) => date('D j M', strtotime($x)), array_keys($daily))),
        'week' => array_sum(array_slice($daily, -7)),
    ];
    $d['tracking'] = sl_trackConsoleData($user, $core, 'shipments', 10);
    return $d;
}
