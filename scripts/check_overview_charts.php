<?php
/** Dashboard chart permission, scope and date-bucket checks without a database. */
require dirname(__DIR__) . '/views/sl/data/overview_charts.php';

$queries = [];
$agencyId = 7;
function sl_navAgencyWhere(string $alias): string { return ' AND ' . $alias . '.agency = 7'; }
function cdp_getAgencyContext(): array { return ['is_restricted' => true, 'agency_id' => $GLOBALS['agencyId']]; }
function sl_pct($part, $whole): int { return $whole ? (int) round($part / $whole * 100) : 0; }
function sl_heatLevel($n, $peak): string { return $n ? 'h2' : 'h0'; }
function sl_conic($segments): string { return 'conic-gradient(test)'; }
function sl_rows(string $sql, array $binds): array
{
    $GLOBALS['queries'][] = [$sql, $binds];
    if (str_contains($sql, 'CASE')) return [(object) ['label' => 'Delivered', 'n' => 2]];
    if (str_contains($sql, 'recipient_city')) return [(object) ['city' => 'Accra', 'n' => 2]];
    if (str_contains($sql, 'GROUP BY DATE(a.order_date)')) {
        return [(object) ['day' => date('Y-m-d'), 'n' => 2]];
    }
    return [];
}
class OverviewChartUser {
    public function __construct(private array $permissions) {}
    public function cdp_hasPermission($permissions): bool { return (bool) array_intersect((array) $permissions, $this->permissions); }
}
function check($ok, string $message): void { if (!$ok) throw new RuntimeException($message); }

check(sl_overviewChartsData(new OverviewChartUser([])) === [] && !$queries, 'No access must produce no chart queries');
$sea = sl_overviewChartsData(new OverviewChartUser(['view_package_list']));
check(count($queries) === 1 && str_contains($queries[0][0], 'cdb_customers_packages'), 'Package-only staff must not query air data');
check(count($sea['current']) === 14 && $sea['total'] === 2 && $sea['current'][13] === 2 && $sea['current'][0] === 0, 'Daily series must fill missing dates');
check($sea['status'] === [] && $sea['destinations'] === [], 'Package-only staff must not receive air metrics');
$queries = [];
$air = sl_overviewChartsData(new OverviewChartUser(['view_shipment_list']));
check($air['total'] === 2 && $air['statusTotal'] === 2 && $air['destinations'][0]['city'] === 'Accra' && count($air['heat']) === 84, 'Air metrics must use the registered shipments');
check(count($queries) === 4 && array_reduce($queries, fn($ok, $q) => $ok && str_contains($q[0], 'a.agency = 7') && isset($q[1][':from'], $q[1][':to']), true), 'Every query needs agency and date bounds');
$agencyId = null; $queries = [];
sl_overviewChartsData(new OverviewChartUser(['view_shipment_list']));
check(array_reduce($queries, fn($ok, $q) => $ok && str_contains($q[0], 'AND 1=0'), true), 'Restricted staff without an agency must see no data');
echo "PASS: overview charts respect permissions, agency and dates.\n";
