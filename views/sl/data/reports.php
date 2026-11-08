<?php
/** Data for the Reports hub (Reports.dc.html): every legacy report_* page grouped as in the design, with a shared date range. */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_charts.php';
require_once CDP_ROOT . '/helpers/finance_switch.php';
require_once CDP_ROOT . '/helpers/rbac.php';
require_once CDP_ROOT . '/helpers/sl_nav.php';

/** Report catalogue: group => [title, sub, list page, excel page|null, print page|null, permission, finance?, by-links]. */
function sl_reportCatalogue(): array
{
    return [
        ['id' => 'ship', 'icon' => 'i-box', 'title' => 'Packages and shipments', 'items' => [
            ['General shipments', 'Every air shipment registered', 'report_general.php', 'report_general_excel.php', 'report_general_print.php', 'view_general_shipments', false, [['By branch', 'report_agency.php'], ['By driver', 'report_driver_list.php'], ['By employee', 'report_employees.php'], ['By customer', 'report_customer.php']], 'cdb_add_order'],
            ['Packages registered', 'Customer packages entered (sea / locker)', 'report_packages_registered.php', 'report_packages_registered_excel.php', 'report_packages_registered_print.php', 'view_general_package_records', false, [['By branch', 'report_packages_registered_agency.php'], ['By driver', 'report_packages_registered_driver.php'], ['By employee', 'report_packages_registered_employee.php']], 'cdb_customers_packages'],
            ['Shipments by branch', 'Agency totals', 'report_agency.php', 'report_agency_excel.php', 'report_agency_print.php', 'view_shipment_by_agencies', false, [], null],
            ['Shipments by customer', 'Per customer', 'report_customer.php', 'report_customer_excel.php', 'report_customer_print.php', 'view_shipment_by_clients', false, [], null],
            ['Shipments by employee', 'Who registered what', 'report_employees.php', 'report_employees_excel.php', 'report_employees_print.php', 'view_shipment_by_employees', false, [], null],
            ['Shipments by driver', 'Deliveries per rider', 'report_driver_list.php', 'report_driver_excel.php', 'report_driver_print.php', 'view_shipment_by_drivers', false, [], null],
            ['Top users · air', 'Biggest air customers', 'report_top_users_air.php', null, null, 'view_top_users_air', false, [], null],
            ['Top users · sea', 'Biggest sea customers', 'report_top_users_sea.php', null, null, 'view_top_users_sea', false, [], null],
        ]],
        ['id' => 'cons', 'icon' => 'i-layers', 'title' => 'Consolidation', 'items' => [
            ['Consolidated shipments', 'Air consolidations', 'report_consolidate_general_list.php', 'report_consolidate_general_excel.php', 'report_consolidate_general_print.php', 'view_general_consolidated_shipments', false, [['By branch', 'report_consolidate_agency_list.php'], ['By customer', 'report_consolidate_customers_list.php'], ['By driver', 'report_consolidate_driver_list.php'], ['By employee', 'report_consolidate_employees_list.php']], 'cdb_consolidate'],
            ['Consolidated packages', 'Sea consolidations', 'report_consolidate_packages_general_list.php', 'report_consolidate_packages_general_excel.php', 'report_consolidate_packages_general_print.php', 'view_general_consolidated_shipments', false, [['By branch', 'report_consolidate_packages_agency_list.php'], ['By customer', 'report_consolidate_packages_customers_list.php'], ['By driver', 'report_consolidate_packages_driver_list.php'], ['By employee', 'report_consolidate_packages_employees_list.php']], 'cdb_consolidate_packages'],
        ]],
        ['id' => 'pick', 'icon' => 'i-truck', 'title' => 'Pickups and riders', 'items' => [
            ['Pickups', 'Every pickup request', 'report_pickup_general_list.php', 'report_pickup_general_excel.php', 'report_pickup_general_print.php', 'view_general_pickups', false, [['By branch', 'report_pickup_agency_list.php'], ['By customer', 'report_pickup_customers_list.php'], ['By driver', 'report_pickup_driver_list.php'], ['By employee', 'report_pickup_employees_list.php']], 'pickup'],
            ['Pickup aging', 'Unclaimed parcels by age', 'pickup_aging.php', null, null, 'view_pickup_aging', false, [], null],
        ]],
        ['id' => 'fin', 'icon' => 'i-dollar', 'title' => 'Money', 'items' => [
            ['Payments received', 'Cash and gateway payments', 'report_payments_received_list.php', 'report_payments_received_excel.php', 'report_payments_received_print.php', 'view_received_payments', true, [], 'pay'],
            ['Customer balances', 'What each customer owes', 'report_customers_balance_list.php', 'report_customers_balance_excel.php', 'report_customers_balance_print.php', 'view_client_balance', true, [], null],
            ['Accounts summary', 'Billed, paid and outstanding', 'report_summary_list.php', 'report_summary_excel.php', 'report_summary_print.php', 'view_accounts_summary', true, [], null],
            ['Transactions', 'Every payment in and out', 'transactions.php', 'transactions.php?export=csv', null, 'view_transactions', true, [], null],
            ['Accounts receivable', 'Aging of unpaid invoices', 'accounts_receivable.php', 'accounts_receivable.php?export=csv', null, 'view_receivable_accounts', true, [], null],
            ['Financial overview', 'Revenue, cost and margin', 'financial_overview.php', null, null, 'view_financial_overview', true, [], null],
            ['Operating costs', 'What it costs to run', 'cost_settings.php', null, null, 'superadmin', false, [], null],
        ]],
        ['id' => 'sys', 'icon' => 'i-activity', 'title' => 'People, messages and system', 'items' => [
            ['Customers', 'Accounts and activity', 'customers_list.php', 'customers_list.php?export=csv', null, 'view_client_list', false, [], null],
            ['Staff productivity', 'Actions per person', 'staff_productivity.php', null, null, 'view_staff_productivity', false, [], null],
            ['Activity log', 'Audit trail', 'activity_logs.php', null, null, 'view_activity_logs', false, [], null],
            ['Message logs', 'Every SMS, WhatsApp and e-mail', 'message_logs.php', null, null, 'view_message_logs', false, [], null],
            ['System logs', 'Errors and security', 'system_logs.php', null, null, 'view_system_logs', false, [], null],
        ]],
    ];
}

function sl_reportsData($user, $core): array
{
    $d = [];
    $range = (string) ($_GET['range'] ?? '30d');
    $days = ['7d' => 7, '30d' => 30, '90d' => 90, '1y' => 365][$range] ?? 30;
    $from = trim((string) ($_GET['from'] ?? '')) ?: date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $to = trim((string) ($_GET['to'] ?? '')) ?: date('Y-m-d');
    if (!strtotime($from) || !strtotime($to)) { $from = date('Y-m-d', strtotime('-29 days')); $to = date('Y-m-d'); }
    $from = date('Y-m-d', strtotime($from)); $to = date('Y-m-d', strtotime($to));
    $d['from'] = $from; $d['to'] = $to; $d['range'] = $range;
    $d['ranges'] = array_map(fn($r) => ['f' => $r, 'cls' => $r === $range && empty($_GET['from']) ? 'on' : ''], ['7d', '30d', '90d', '1y']);
    $legacyRange = date('Y/m/d', strtotime($from)) . ' - ' . date('Y/m/d', strtotime($to));
    $d['legacyRange'] = $legacyRange;
    $agency = sl_navAgencyWhere('a');
    $b = [':f' => $from, ':t' => $to . ' 23:59:59'];
    $finance = cdp_financeEnabled();
    $money = cdp_canViewMoney($user, 'dashboard') || cdp_canViewMoney($user, 'finance');
    $len = max(1, (strtotime($to) - strtotime($from)) / 86400 + 1);
    $pb = [':f' => date('Y-m-d', strtotime($from . ' -' . (int) $len . ' days')), ':t' => date('Y-m-d 23:59:59', strtotime($from . ' -1 day'))];
    $cnt = fn($bb) => (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date BETWEEN :f AND :t $agency", $bb) + (int) sl_scalar("SELECT COUNT(*) FROM cdb_customers_packages a WHERE a.order_date BETWEEN :f AND :t", $bb);
    $parcels = $cnt($b); $prevP = $cnt($pb);
    $rev = (float) sl_scalar("SELECT COALESCE(SUM(a.total_order),0) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date BETWEEN :f AND :t $agency", $b) + (float) sl_scalar("SELECT COALESCE(SUM(a.total_order),0) FROM cdb_customers_packages a WHERE a.order_date BETWEEN :f AND :t", $b);
    $prevR = (float) sl_scalar("SELECT COALESCE(SUM(a.total_order),0) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date BETWEEN :f AND :t $agency", $pb);
    $deliv = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (8,15) AND a.order_date BETWEEN :f AND :t $agency", $b);
    $late = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (8,15) AND a.due_date IS NOT NULL AND a.payment_date > a.due_date AND a.order_date BETWEEN :f AND :t $agency", $b);
    $tickets = sl_tableExists('cdb_cs_tickets') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE status NOT IN ('closed','resolved')") : 0;
    $delta = fn($a, $p) => $p > 0 ? (($a >= $p ? '▲ ' : '▼ ') . number_format(abs($a - $p) / $p * 100, 1) . '% vs last period') : 'no previous data';
    $spark = function (string $table, string $col, string $where = '', string $expr = 'COUNT(*)') use ($from, $to) {
        $rows = sl_rows("SELECT DATE($col) AS d, $expr AS c FROM $table WHERE $col BETWEEN :f AND :t $where GROUP BY DATE($col)", [':f' => $from, ':t' => $to . ' 23:59:59']);
        $m = []; foreach ($rows as $r) $m[$r->d] = (float) $r->c;
        $v = []; for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) $v[] = $m[date('Y-m-d', $t)] ?? 0;
        if (count($v) > 60) { $v = array_map(fn($c) => array_sum($c), array_chunk($v, (int) ceil(count($v) / 60))); }
        return [sl_svgAreaPath($v, 240, 46, 2), sl_svgLinePath($v, 240, 46, 2), $v];
    };
    $d['kpi'] = [
        ['t' => 'Parcels handled', 'v' => sl_num($parcels), 'd' => $delta($parcels, $prevP), 'ok' => $parcels >= $prevP, 'sp' => $spark('cdb_add_order', 'order_date', ' AND is_deleted = 0')],
        ['t' => 'Revenue', 'v' => $money && $finance ? sl_money($rev) : '••••', 'd' => $money && $finance ? $delta($rev, $prevR) : 'hidden for your role', 'ok' => $rev >= $prevR, 'sp' => $money && $finance ? $spark('cdb_add_order', 'order_date', ' AND is_deleted = 0', 'COALESCE(SUM(total_order),0)') : ['', '']],
        ['t' => 'On-time delivery', 'v' => ($deliv ? round(($deliv - $late) / $deliv * 100, 1) : 100) . '%', 'd' => sl_num($deliv) . ' delivered', 'ok' => true, 'sp' => $spark('cdb_add_order', 'order_date', ' AND is_deleted = 0 AND status_courier IN (8,15)')],
        ['t' => 'Open tickets', 'v' => sl_num($tickets), 'd' => 'customer service', 'ok' => $tickets < 20, 'sp' => ['', '']],
    ];
    // monthly volume + status mix (shipments section)
    $year = (int) date('Y', strtotime($to));
    $ship = sl_monthlyCounts('cdb_add_order', 'order_date', $year, ' AND is_deleted = 0 AND is_pickup = 0');
    $pick = sl_monthlyCounts('cdb_add_order', 'order_date', $year, ' AND is_deleted = 0 AND is_pickup = 1');
    $sea = sl_monthlyCounts('cdb_customers_packages', 'order_date', $year);
    $mx = max(1, max($ship), max($pick), max($sea));
    $d['monthly'] = ['values' => [['name' => 'Shipments', 'values' => $ship], ['name' => 'Pickups', 'values' => $pick], ['name' => 'Sea packages', 'values' => $sea]], 'year' => $year, 'mx' => $mx, 'ship' => sl_svgLinePath($ship, 634, 160, 10, $mx, 44), 'shipA' => sl_svgAreaPath($ship, 634, 160, 10, $mx, 44), 'pick' => sl_svgLinePath($pick, 634, 160, 10, $mx, 44), 'sea' => sl_svgLinePath($sea, 634, 160, 10, $mx, 44)];
    $rows = sl_rows("SELECT COALESCE(s.mod_style,'Other') AS l, COALESCE(s.color,'#94a3b8') AS c, COUNT(*) AS n FROM cdb_add_order a LEFT JOIN cdb_styles s ON s.id = a.status_courier WHERE a.is_deleted = 0 AND a.order_date BETWEEN :f AND :t $agency GROUP BY a.status_courier ORDER BY n DESC LIMIT 6", $b);
    $tot = array_sum(array_map(fn($r) => (int) $r->n, $rows)) ?: 1;
    $d['mix'] = ['conic' => sl_conic(array_map(fn($r) => [$r->c, (int) $r->n], $rows ?: [])), 'rows' => array_map(fn($r) => ['l' => ucwords(str_replace('_', ' ', $r->l)), 'c' => $r->c, 'pct' => sl_pct($r->n, $tot), 'n' => (int) $r->n], $rows), 'total' => array_sum(array_map(fn($r) => (int) $r->n, $rows))];
    // catalogue, permission filtered, with a count per report where cheap
    $counts = [
        'cdb_add_order' => (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date BETWEEN :f AND :t $agency", $b),
        'cdb_customers_packages' => (int) sl_scalar("SELECT COUNT(*) FROM cdb_customers_packages a WHERE a.order_date BETWEEN :f AND :t", $b),
        'cdb_consolidate' => (int) sl_scalar("SELECT COUNT(*) FROM cdb_consolidate a WHERE a.c_date BETWEEN :f AND :t", $b),
        'cdb_consolidate_packages' => (int) sl_scalar("SELECT COUNT(*) FROM cdb_consolidate_packages a WHERE a.c_date BETWEEN :f AND :t", $b),
        'pickup' => (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND a.order_date BETWEEN :f AND :t $agency", $b),
        'pay' => $money && $finance ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_charges_order WHERE charge_date BETWEEN :f AND :t", $b) + (int) sl_scalar("SELECT COUNT(*) FROM cdb_payments_gateway WHERE date_payment BETWEEN :f AND :t", $b) : null,
    ];
    $groups = []; $excel = [];
    foreach (sl_reportCatalogue() as $g) {
        $items = [];
        foreach ($g['items'] as $it) {
            [$title, $sub, $list, $xls, $prn, $perm, $fin, $byl, $countKey] = $it;
            if ($fin && !$finance) continue;
            if ($perm === 'superadmin' ? !sl_userIs('superadmin') : !$user->cdp_hasPermission($perm)) continue;
            $q = (strpos((string) $xls, '?') === false ? '?' : '&') . 'range=' . rawurlencode($legacyRange) . '&status_courier=0&method=0&agency=0&customer_id=0&driver_id=0&employee_id=0';
            $items[] = ['title' => $title, 'sub' => $sub, 'href' => $list, 'xls' => $xls ? $xls . (strpos($xls, 'export=csv') !== false ? '' : $q) : null, 'prn' => $prn ? $prn . '?range=' . rawurlencode($legacyRange) . '&status_courier=0&method=0&agency=0&customer_id=0&driver_id=0&employee_id=0' : null, 'by' => array_map(fn($x) => ['label' => $x[0], 'href' => $x[1]], $byl), 'n' => $countKey !== null && isset($counts[$countKey]) && $counts[$countKey] !== null ? sl_num($counts[$countKey]) : ''];
            if ($xls) $excel[] = ['title' => $title, 'href' => end($items)['xls']];
        }
        if ($items) $groups[] = ['id' => $g['id'], 'icon' => $g['icon'], 'title' => $g['title'], 'items' => $items];
    }
    $d['groups'] = $groups; $d['excel'] = $excel;
    $d['reportN'] = array_sum(array_map(fn($g) => count($g['items']), $groups));
    return $d;
}
