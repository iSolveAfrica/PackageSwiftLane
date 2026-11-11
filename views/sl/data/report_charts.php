<?php
/** Additional report charts. Queries use the selected registration dates and agency scope. */
function sl_reportCharts($user, string $from, string $to): array
{
    $scope = sl_navAgencyWhere('a');
    $bind = [':f' => $from, ':t' => $to . ' 23:59:59'];
    $air = $user->cdp_hasPermission(['view_general_shipments', 'view_shipment_list']);
    $sea = $user->cdp_hasPermission(['view_general_package_records', 'view_package_list']);
    $pick = $user->cdp_hasPermission(['view_general_pickups', 'view_pickup_list']);
    $charts = [];
    if ($air || $sea || $pick) {
        $days = (strtotime($to) - strtotime($from)) / 86400 + 1;
        $format = $days > 3660 ? '%Y' : ($days > 90 ? '%Y-%m' : '%Y-%m-%d');
        $phpFormat = $days > 3660 ? 'Y' : ($days > 90 ? 'Y-m' : 'Y-m-d');
        $step = $days > 3660 ? '+1 year' : ($days > 90 ? '+1 month' : '+1 day');
        $start = $days > 3660 ? substr($from, 0, 4) . '-01-01' : ($days > 90 ? substr($from, 0, 7) . '-01' : $from);
        $keys = []; $labels = [];
        for ($date = new DateTimeImmutable($start); $date <= new DateTimeImmutable($to); $date = $date->modify($step)) {
            $keys[] = $date->format($phpFormat);
            $labels[] = $date->format($days > 3660 ? 'Y' : ($days > 90 ? 'M Y' : 'M j, Y'));
        }
        $series = [];
        foreach ([[$air, 'Air shipments', 'cdb_add_order', ' AND a.is_deleted = 0 AND a.is_pickup = 0', '#D9792B'], [$sea, 'Sea packages', 'cdb_customers_packages', '', '#2F7FC8'], [$pick, 'Pickups', 'cdb_add_order', ' AND a.is_deleted = 0 AND a.is_pickup = 1', '#8865B2']] as [$allowed, $name, $table, $where, $color]) {
            if (!$allowed) continue;
            $rows = sl_rows("SELECT DATE_FORMAT(a.order_date, '$format') AS bucket, COUNT(*) AS n FROM $table a WHERE a.order_date BETWEEN :f AND :t $where $scope GROUP BY bucket ORDER BY bucket", $bind);
            $map = []; foreach ($rows as $row) $map[$row->bucket] = (int) $row->n;
            $series[] = ['name' => $name, 'color' => $color, 'values' => array_map(fn($key) => $map[$key] ?? 0, $keys)];
        }
        $charts[] = ['title' => 'Registration trend', 'sub' => ($days > 3660 ? 'Yearly' : ($days > 90 ? 'Monthly' : 'Daily')) . ' totals within the selected dates', 'kind' => 'line', 'labels' => $labels, 'series' => $series];
    }
    if ($air) {
        $rows = sl_rows("SELECT COALESCE(NULLIF(TRIM(s.recipient_city), ''), 'Not recorded') AS label, COUNT(DISTINCT a.order_id) AS n FROM cdb_add_order a LEFT JOIN cdb_address_shipments s ON s.order_id = a.order_id WHERE a.is_deleted = 0 AND a.is_pickup = 0 AND a.order_date BETWEEN :f AND :t $scope GROUP BY label ORDER BY n DESC, label LIMIT 8", $bind);
        $charts[] = ['title' => 'Top destinations', 'sub' => 'Top 8 recipient cities for air shipments registered in the period', 'kind' => 'bars', 'labels' => array_map(fn($r) => $r->label, $rows), 'series' => [['name' => 'Shipments', 'color' => '#2F7FC8', 'values' => array_map(fn($r) => (int) $r->n, $rows)]]];
    }
    if ($air && $user->cdp_hasPermission(['warehouse_view', 'view_warehouse_delivery', 'view_pickup_aging'])) {
        $rows = sl_rows("SELECT CASE WHEN DATEDIFF(CURDATE(), a.order_date) <= 7 THEN 0 WHEN DATEDIFF(CURDATE(), a.order_date) <= 14 THEN 1 WHEN DATEDIFF(CURDATE(), a.order_date) <= 30 THEN 2 ELSE 3 END AS bucket, COUNT(*) AS n FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (4,6,32) AND a.order_date BETWEEN :f AND :t $scope GROUP BY bucket", $bind);
        $values = [0,0,0,0]; foreach ($rows as $row) $values[(int) $row->bucket] = (int) $row->n;
        $charts[] = ['title' => 'Warehouse age today', 'sub' => 'Air parcels currently in warehouse, from the selected registration dates · age since registration', 'kind' => 'bars', 'labels' => ['0–7 days','8–14 days','15–30 days','31+ days'], 'series' => [['name' => 'Parcels', 'color' => '#B88632', 'values' => $values]]];
    }
    if ($air && cdp_financeEnabled() && $user->cdp_hasPermission(['view_received_payments','view_accounts_summary','view_financial_overview']) && (cdp_canViewMoney($user, 'dashboard') || cdp_canViewMoney($user, 'finance'))) {
        $rows = sl_rows("SELECT COALESCE(s.mod_style, 'Not recorded') AS label, COUNT(*) AS n FROM cdb_add_order a LEFT JOIN cdb_styles s ON s.id = a.status_invoice WHERE a.is_deleted = 0 AND a.order_date BETWEEN :f AND :t $scope GROUP BY a.status_invoice, s.mod_style ORDER BY n DESC", $bind);
        $charts[] = ['title' => 'Payment status', 'sub' => 'Current invoice status of air shipments registered in the period · counts, not amounts', 'kind' => 'bars', 'labels' => array_map(fn($r) => ucwords(str_replace('_', ' ', $r->label)), $rows), 'series' => [['name' => 'Shipments', 'color' => '#0E9A70', 'values' => array_map(fn($r) => (int) $r->n, $rows)]]];
    }
    return $charts;
}
