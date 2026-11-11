<?php
/** Permission, agency/date scope and zero-filled time series without a database. */
require dirname(__DIR__) . '/views/sl/data/report_charts.php';
$queries = []; $finance = true; $money = true;
function sl_navAgencyWhere($alias) { return " AND $alias.agency = 7"; }
function cdp_financeEnabled() { return $GLOBALS['finance']; }
function cdp_canViewMoney($user, $context) { return $GLOBALS['money']; }
function sl_rows($sql, $bind) {
    if (!str_contains($sql, 'a.agency = 7') || $bind[':f'] !== $GLOBALS['testFrom'] || $bind[':t'] !== $GLOBALS['testTo'] . ' 23:59:59') throw new RuntimeException('Missing report scope');
    $GLOBALS['queries'][] = $sql;
    if (str_contains($sql, 'DATE_FORMAT')) return [(object) ['bucket'=> str_contains($sql, "'%Y-%m-%d'") ? '2026-11-02' : '2026-11', 'n'=>5]];
    if (str_contains($sql, 'DATEDIFF')) return [(object) ['bucket'=>1,'n'=>3]];
    return [(object) ['label'=>'Accra','n'=>6]];
}
class ReportChartUser {
    function __construct(public array $permissions) {}
    function cdp_hasPermission($permissions) { return (bool) array_intersect((array) $permissions, $this->permissions); }
}
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$testFrom = '2026-11-01'; $testTo = '2026-11-03';
check(sl_reportCharts(new ReportChartUser([]), $testFrom, $testTo) === [] && !$queries, 'No permission must mean no queries');
$user = new ReportChartUser(['view_general_shipments','warehouse_view','view_received_payments']);
$charts = sl_reportCharts($user, $testFrom, $testTo);
check(count($charts) === 4, 'Expected four authorized charts');
check($charts[0]['series'][0]['values'] === [0,5,0], 'Missing days must stay zero');
check($charts[2]['series'][0]['values'] === [0,3,0,0], 'Missing age buckets must stay zero');
$finance = false; check(count(sl_reportCharts($user, $testFrom, $testTo)) === 3, 'Finance switch must hide payment status');
$finance = true; $money = false; check(count(sl_reportCharts($user, $testFrom, $testTo)) === 3, 'Restricted roles must not get payment data');
$queries = []; $sea = sl_reportCharts(new ReportChartUser(['view_general_package_records']), $testFrom, $testTo);
check(count($sea) === 1 && count($queries) === 1 && str_contains($queries[0], 'cdb_customers_packages'), 'Sea-only role must query only sea data');
$testFrom='2026-01-01'; $testTo='2026-12-31';
$charts=sl_reportCharts($user,$testFrom,$testTo);
check(count($charts[0]['labels']) === 12, 'Longer ranges should group by month');
echo "PASS: report chart permissions, agency/date scope, zero-filled buckets and monthly grouping.\n";
