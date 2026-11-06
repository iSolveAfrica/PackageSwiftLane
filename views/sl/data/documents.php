<?php
/**
 * Documents and exports hub (Documents.dc.html): every CSV/Excel/PDF export the
 * app has, linked to the existing generators, and the documents generated
 * recently (from the document audit trail, cdp_activityLogDocument()).
 */
require_once CDP_ROOT . '/helpers/sl_view.php';

/** The catalogue of exports. 'range' = takes ?range=Y/m/d - Y/m/d; 'perm' = permission to show it. */
function sl_documentCatalogue(): array
{
    $rep = 'view_general_reports';
    $x = [
        // Shipments (air)
        ['g' => 'Shipments', 'file' => 'report_general_excel.php', 'label' => 'Shipments · all', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Shipments', 'file' => 'report_customer_excel.php', 'label' => 'Shipments by customer', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Shipments', 'file' => 'report_agency_excel.php', 'label' => 'Shipments by agency', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Shipments', 'file' => 'report_driver_excel.php', 'label' => 'Shipments by driver', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Shipments', 'file' => 'report_employees_excel.php', 'label' => 'Shipments by employee', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Shipments', 'file' => 'courier_list.php?export=csv', 'label' => 'Air shipment list (current filters)', 'fmt' => 'CSV', 'range' => false, 'perm' => 'view_shipment_list'],
        ['g' => 'Shipments', 'file' => 'top_users_air_excel.php', 'label' => 'Top customers · air', 'fmt' => 'Excel', 'range' => true, 'perm' => 'top_users_air_print'],
        // Packages (sea)
        ['g' => 'Packages', 'file' => 'report_packages_registered_excel.php', 'label' => 'Packages registered', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Packages', 'file' => 'report_packages_registered_agency_excel.php', 'label' => 'Packages by agency', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Packages', 'file' => 'report_packages_registered_driver_excel.php', 'label' => 'Packages by driver', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Packages', 'file' => 'report_packages_registered_employee_excel.php', 'label' => 'Packages by employee', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Packages', 'file' => 'top_users_sea_excel.php', 'label' => 'Top customers · sea', 'fmt' => 'Excel', 'range' => true, 'perm' => 'top_users_sea_print'],
        // Pickups
        ['g' => 'Pickups', 'file' => 'report_pickup_general_excel.php', 'label' => 'Pickups · all', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Pickups', 'file' => 'report_pickup_customers_excel.php', 'label' => 'Pickups by customer', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Pickups', 'file' => 'report_pickup_agency_excel.php', 'label' => 'Pickups by agency', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Pickups', 'file' => 'report_pickup_driver_excel.php', 'label' => 'Pickups by driver', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Pickups', 'file' => 'report_pickup_employees_excel.php', 'label' => 'Pickups by employee', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        // Consolidations / manifests
        ['g' => 'Manifests', 'file' => 'report_consolidate_general_excel.php', 'label' => 'Air consolidations', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Manifests', 'file' => 'report_consolidate_customers_excel.php', 'label' => 'Air consolidations by customer', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Manifests', 'file' => 'report_consolidate_agency_excel.php', 'label' => 'Air consolidations by agency', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Manifests', 'file' => 'report_consolidate_driver_excel.php', 'label' => 'Air consolidations by driver', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Manifests', 'file' => 'report_consolidate_employees_excel.php', 'label' => 'Air consolidations by employee', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Manifests', 'file' => 'report_consolidate_packages_general_excel.php', 'label' => 'Sea consolidations', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Manifests', 'file' => 'report_consolidate_packages_customers_excel.php', 'label' => 'Sea consolidations by customer', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Manifests', 'file' => 'report_consolidate_packages_agency_excel.php', 'label' => 'Sea consolidations by agency', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Manifests', 'file' => 'report_consolidate_packages_driver_excel.php', 'label' => 'Sea consolidations by driver', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Manifests', 'file' => 'report_consolidate_packages_employees_excel.php', 'label' => 'Sea consolidations by employee', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep],
        ['g' => 'Manifests', 'file' => 'consolidate_parking_list_excel.php', 'label' => 'Packing list (open a consolidation first)', 'fmt' => 'Excel', 'range' => false, 'perm' => ['view_consolidate_list', 'view_consolidate_package_list'], 'needsId' => true],
        // Finance
        ['g' => 'Finance', 'file' => 'report_summary_excel.php', 'label' => 'Summary', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep, 'finance' => true],
        ['g' => 'Finance', 'file' => 'report_payments_received_excel.php', 'label' => 'Payments received', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep, 'finance' => true],
        ['g' => 'Finance', 'file' => 'report_customers_balance_excel.php', 'label' => 'Customer balances', 'fmt' => 'Excel', 'range' => true, 'perm' => $rep, 'finance' => true],
        // People
        ['g' => 'People', 'file' => 'customers_list.php?export=csv', 'label' => 'Customer list', 'fmt' => 'CSV', 'range' => false, 'perm' => 'view_client_list'],
        ['g' => 'People', 'file' => 'activity_logs.php?export=csv', 'label' => 'Activity log', 'fmt' => 'CSV', 'range' => false, 'perm' => 'view_activity_logs'],
    ];
    return $x;
}

/** Print documents (one record each) and where they are opened from. */
function sl_documentPrints(): array
{
    return [
        ['label' => 'Shipping label 4 × 6', 'file' => 'print_label_ship.php', 'from' => 'Air shipment row menu', 'icon' => 'i-tag', 'cat' => 'labels'],
        ['label' => 'Shelf label 1 × 2', 'file' => 'print_label_ship.php?size=12', 'from' => 'Air shipment row menu', 'icon' => 'i-tag', 'cat' => 'labels'],
        ['label' => 'Package label', 'file' => 'print_label_package.php', 'from' => 'Sea package page', 'icon' => 'i-tag', 'cat' => 'labels'],
        ['label' => 'Consolidation label', 'file' => 'print_label_consolidate.php', 'from' => 'Consolidation page', 'icon' => 'i-tag', 'cat' => 'labels'],
        ['label' => 'Shipment invoice', 'file' => 'print_inv_ship.php', 'from' => 'Air shipment row menu', 'icon' => 'i-receipt', 'cat' => 'invoices'],
        ['label' => 'Package receipt (paid / not paid)', 'file' => 'print_invoice_package.php', 'from' => 'Package or shipment page', 'icon' => 'i-receipt', 'cat' => 'receipts'],
        ['label' => 'Charge receipt', 'file' => 'print_charge.php', 'from' => 'Payments', 'icon' => 'i-receipt', 'cat' => 'receipts'],
        ['label' => 'Consolidation manifest', 'file' => 'print_consolidate.php', 'from' => 'Consolidation page', 'icon' => 'i-layers', 'cat' => 'manifests'],
        ['label' => 'Tracking sheet', 'file' => 'print_inv_ship_track.php', 'from' => 'Shipment tracking', 'icon' => 'i-route', 'cat' => 'manifests'],
    ];
}

function sl_documentCategory(string $page): string
{
    if (strpos($page, 'label') !== false) return 'labels';
    if (strpos($page, 'invoice_package') !== false || strpos($page, 'charge') !== false) return 'receipts';
    if (strpos($page, 'inv_ship') !== false) return 'invoices';
    if (strpos($page, 'consolidate') !== false || strpos($page, 'parking') !== false) return 'manifests';
    return 'exports';
}

function sl_documentsData($user, $core): array
{
    require_once CDP_ROOT . '/helpers/finance_switch.php';
    $finance = function_exists('cdp_financeEnabled') ? cdp_financeEnabled() : true;
    $cat = [];
    foreach (sl_documentCatalogue() as $e) {
        $file = strtok($e['file'], '?');
        if (!is_file(CDP_ROOT . '/' . $file)) continue;
        if (!empty($e['finance']) && !$finance) continue;
        if (!$user->cdp_hasPermission($e['perm'])) continue;
        $cat[] = $e;
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $filter = (string) ($_GET['filter'] ?? 'all');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $rows = [];
    $count = 0;
    if (sl_tableExists('cdb_activity_log')) {
        $where = "entity_type = 'document'";
        $binds = [];
        if (!$user->cdp_hasPermission('view_activity_logs')) {
            $where .= ' AND user_id = ' . (int) $_SESSION['userid'];
        }
        if ($q !== '') {
            $where .= ' AND (summary LIKE :q1 OR entity_id LIKE :q2 OR actor_name LIKE :q3 OR endpoint LIKE :q4)';
            $binds = [':q1' => "%$q%", ':q2' => "%$q%", ':q3' => "%$q%", ':q4' => "%$q%"];
        }
        $map = ['labels' => "endpoint LIKE '%label%'", 'receipts' => "(endpoint LIKE 'print_invoice_package%' OR endpoint LIKE 'print_charge%')", 'invoices' => "endpoint LIKE 'print_inv_ship%'", 'manifests' => "(endpoint LIKE '%consolidate%' OR endpoint LIKE '%parking%')", 'exports' => "(verb = 'export' OR endpoint LIKE '%excel%' OR endpoint LIKE '%csv%')"];
        if (isset($map[$filter])) $where .= ' AND ' . $map[$filter];
        $count = (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE $where", $binds);
        foreach (sl_rows("SELECT id, created_at, actor_name, verb, summary, entity_id, endpoint, meta FROM cdb_activity_log WHERE $where ORDER BY id DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds) as $r) {
            $meta = json_decode((string) $r->meta, true) ?: [];
            $params = is_array($meta['params'] ?? null) ? $meta['params'] : [];
            unset($params['legacy']);
            $params = array_filter($params, fn($v) => is_scalar($v) && (string) $v !== '');
            $ep = basename((string) ($r->endpoint ?: (($meta['page'] ?? '') . '.php')));
            $catName = sl_documentCategory($ep);
            $label = $r->entity_id !== '' && $r->entity_id !== null ? sl_documentNumber($ep, (int) $r->entity_id, $params) : '';
            $rows[] = [
                'title' => preg_replace('/^(Printed|Exported) /', '', (string) $r->summary), 'sub' => ucfirst($catName) . ($params ? ' · ' . implode(', ', array_map(fn($k, $v) => $k . ' ' . (is_scalar($v) ? $v : ''), array_keys($params), $params)) : ''),
                'icon' => ['labels' => 'i-tag', 'receipts' => 'i-receipt', 'invoices' => 'i-receipt', 'manifests' => 'i-layers', 'exports' => 'i-download'][$catName],
                'num' => $label ?: ($r->entity_id ?: '—'), 'by' => $r->actor_name ?: 'System', 'date' => sl_date($r->created_at, 'M j, H:i'),
                'href' => preg_match('/^[a-z0-9_]+\.php$/', $ep) && is_file(CDP_ROOT . '/' . $ep) ? $ep . ($params ? '?' . http_build_query(array_filter($params, 'is_scalar')) : '') : '',
            ];
        }
    }
    return [
        'catalogue' => $cat, 'groups' => array_values(array_unique(array_map(fn($e) => $e['g'], $cat))), 'prints' => sl_documentPrints(),
        'rows' => $rows, 'count' => $count, 'page' => $page, 'per' => $per, 'q' => $q, 'filter' => $filter,
        'filters' => [['f' => 'all', 'label' => 'All'], ['f' => 'invoices', 'label' => 'Invoices'], ['f' => 'receipts', 'label' => 'Receipts'], ['f' => 'labels', 'label' => 'Labels'], ['f' => 'manifests', 'label' => 'Manifests'], ['f' => 'exports', 'label' => 'Exports']],
        'from' => date('Y-m-d', strtotime('-30 days')), 'to' => date('Y-m-d'),
        'canLabels' => $user->cdp_hasPermission(['print_label', 'view_tools']),
        'schedules' => sl_tableExists('cdb_report_schedules') ? sl_rows('SELECT * FROM cdb_report_schedules ORDER BY id DESC LIMIT 5') : [],
    ];
}

/** Human number of the record a print was made for (shipment/package tracking). */
function sl_documentNumber(string $ep, int $id, array $params): string
{
    static $cache = [];
    $k = $ep . ':' . $id . ':' . ($params['type'] ?? '');
    if (isset($cache[$k])) return $cache[$k];
    $tbl = null;
    if (strpos($ep, 'consolidate') !== false) {
        $r = sl_row(strpos($ep, 'package') !== false ? 'SELECT CONCAT(c_prefix, c_no) AS t FROM cdb_consolidate_packages WHERE consolidate_id = :id' : 'SELECT CONCAT(c_prefix, c_no) AS t FROM cdb_consolidate WHERE consolidate_id = :id', [':id' => $id]);
    } elseif (strpos($ep, 'package') !== false && ($params['type'] ?? 'sea') !== 'air') {
        $r = sl_row('SELECT CONCAT(order_prefix, order_no) AS t FROM cdb_customers_packages WHERE order_id = :id', [':id' => $id]);
    } else {
        $r = sl_row('SELECT CONCAT(order_prefix, order_no) AS t FROM cdb_add_order WHERE order_id = :id', [':id' => $id]);
    }
    return $cache[$k] = $r ? (string) $r->t : '#' . $id;
}
