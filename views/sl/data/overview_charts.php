<?php
/** Permission and agency scoped chart data for the staff and admin dashboard. */
function sl_overviewChartsData($user): array
{
    $canShip = $user->cdp_hasPermission(['view_shipment_list', 'view_general_shipments']);
    $canPackages = $user->cdp_hasPermission(['view_package_list', 'view_general_package_records']);
    if (!$canShip && !$canPackages) return [];

    $days = (int) ($_GET['chart_days'] ?? 14);
    if (!in_array($days, [7, 14, 30], true)) $days = 14;
    $today = new DateTimeImmutable('today');
    $start = $today->modify('-' . ($days - 1) . ' days');
    $previousStart = $start->modify('-' . $days . ' days');
    $end = $today->modify('+1 day');
    $scope = sl_navAgencyWhere('a');
    if (function_exists('cdp_getAgencyContext')) {
        $agencyContext = cdp_getAgencyContext();
        if (!empty($agencyContext['is_restricted']) && empty($agencyContext['agency_id'])) $scope = ' AND 1=0';
    }
    $sources = [];
    if ($canShip) $sources[] = ['key' => 'air', 'label' => 'Air shipments', 'table' => 'cdb_add_order', 'where' => 'AND a.is_deleted = 0 AND a.is_pickup = 0'];
    if ($canPackages) $sources[] = ['key' => 'sea', 'label' => 'Sea packages', 'table' => 'cdb_customers_packages', 'where' => ''];

    $dates = [];
    for ($i = 0; $i < $days; $i++) $dates[] = $start->modify('+' . $i . ' days')->format('Y-m-d');
    $series = []; $current = array_fill(0, $days, 0); $previous = array_fill(0, $days, 0);
    foreach ($sources as $source) {
        $rows = sl_rows("SELECT DATE(a.order_date) AS day, COUNT(*) AS n FROM {$source['table']} a WHERE a.order_date >= :from AND a.order_date < :to {$source['where']} $scope GROUP BY DATE(a.order_date)", [':from' => $previousStart->format('Y-m-d'), ':to' => $end->format('Y-m-d')]);
        $map = []; foreach ($rows as $row) $map[$row->day] = (int) $row->n;
        $values = [];
        foreach ($dates as $i => $date) {
            $values[] = $map[$date] ?? 0;
            $current[$i] += $map[$date] ?? 0;
            $previousDate = $previousStart->modify('+' . $i . ' days')->format('Y-m-d');
            $previous[$i] += $map[$previousDate] ?? 0;
        }
        $series[] = ['name' => $source['label'], 'values' => $values, 'total' => array_sum($values), 'key' => $source['key']];
    }
    $total = array_sum($current); $priorTotal = array_sum($previous);
    $result = [
        'days' => $days,
        'range' => $start->format('M j') . ' – ' . $today->format('M j, Y'),
        'labels' => array_map(fn($date) => date('M j', strtotime($date)), $dates),
        'current' => $current,
        'previous' => $previous,
        'series' => $series,
        'total' => $total,
        'priorTotal' => $priorTotal,
        'change' => $priorTotal > 0 ? round(($total - $priorTotal) / $priorTotal * 100, 1) : null,
        'peak' => max(1, max($current), max($previous)),
        'status' => [], 'destinations' => [], 'heat' => [],
    ];

    if ($canShip) {
        $statusRows = sl_rows("SELECT CASE
            WHEN a.status_courier IN (8,15) THEN 'Delivered'
            WHEN a.status_courier IN (3,5) THEN 'In transit'
            WHEN a.status_courier IN (2,4,6,32) THEN 'At warehouse'
            WHEN a.status_courier = 7 THEN 'Out for delivery'
            ELSE 'Other' END AS label, COUNT(*) AS n
            FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 0
            AND a.order_date >= :from AND a.order_date < :to $scope GROUP BY label",
            [':from' => $start->format('Y-m-d'), ':to' => $end->format('Y-m-d')]);
        $counts = []; foreach ($statusRows as $row) $counts[$row->label] = (int) $row->n;
        foreach (['Delivered' => 'var(--ch-1)', 'In transit' => 'var(--ch-2)', 'At warehouse' => 'var(--ch-4)', 'Out for delivery' => 'var(--ch-3)', 'Other' => 'var(--ch-5)'] as $label => $color) {
            $result['status'][] = ['label' => $label, 'count' => $counts[$label] ?? 0, 'color' => $color];
        }
        $result['statusTotal'] = array_sum(array_column($result['status'], 'count'));
        $result['statusConic'] = sl_conic(array_map(fn($row) => [$row['color'], $row['count']], $result['status']));

        // every recipient city (with its state) for the Ghana map; the top six also feed the ranked list
        $destRows = sl_rows("SELECT COALESCE(NULLIF(TRIM(s.recipient_city),''), 'Not recorded') AS city, MAX(TRIM(s.recipient_state)) AS state, COUNT(DISTINCT a.order_id) AS n
            FROM cdb_add_order a LEFT JOIN cdb_address_shipments s ON s.order_id = a.order_id
            WHERE a.is_deleted = 0 AND a.is_pickup = 0 AND a.order_date >= :from AND a.order_date < :to $scope
            GROUP BY city ORDER BY n DESC, city LIMIT 400",
            [':from' => $start->format('Y-m-d'), ':to' => $end->format('Y-m-d')]);
        $destPeak = $destRows ? max(1, (int) $destRows[0]->n) : 1;
        foreach (array_slice($destRows, 0, 6) as $row) $result['destinations'][] = ['city' => $row->city, 'count' => (int) $row->n, 'pct' => sl_pct($row->n, $destPeak)];
        require_once __DIR__ . '/ghana_map.php';
        $result['ghana'] = sl_ghDestinations($destRows);

        $heatStart = $today->modify('-83 days');
        $heatRows = sl_rows("SELECT DATE(a.order_date) AS day, COUNT(*) AS n FROM cdb_add_order a
            WHERE a.is_deleted = 0 AND a.is_pickup = 0 AND a.order_date >= :from AND a.order_date < :to $scope
            GROUP BY DATE(a.order_date)", [':from' => $heatStart->format('Y-m-d'), ':to' => $end->format('Y-m-d')]);
        $heatMap = []; foreach ($heatRows as $row) $heatMap[$row->day] = (int) $row->n;
        $heatPeak = max(1, max($heatMap ?: [0]));
        for ($i = 0; $i < 84; $i++) {
            $date = $heatStart->modify('+' . $i . ' days');
            $n = $heatMap[$date->format('Y-m-d')] ?? 0;
            $result['heat'][] = ['date' => $date->format('M j, Y'), 'count' => $n, 'level' => sl_heatLevel($n, $heatPeak)];
        }
        $result['heatPeak'] = max($heatMap ?: [0]);
    }
    return $result;
}
