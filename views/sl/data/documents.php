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

/** Friendly name for the page that produced a document. */
function sl_documentName(string $ep, array $params): string
{
    $base = preg_replace('/\.php$/', '', $ep);
    $map = [
        'print_inv_ship_multiple' => 'Shipment invoices (bulk)', 'print_inv_ship_track' => 'Tracking sheet', 'print_inv_ship' => 'Shipment invoice',
        'print_invoice_package' => 'Package receipt', 'print_charge' => 'Charge receipt', 'print_label_ship_multiple' => 'Shipping labels (bulk)',
        'print_label_ship' => !empty($params['size']) ? 'Shelf label' : 'Shipping label 4 × 6', 'print_label_package_multiple' => 'Package labels (bulk)', 'print_label_package' => 'Package label',
        'print_label_consolidate_multiple_package' => 'Sea consolidation labels (bulk)', 'print_label_consolidate_multiple' => 'Consolidation labels (bulk)',
        'print_label_consolidate_package' => 'Sea consolidation label', 'print_label_consolidate' => 'Consolidation label',
        'print_consolidate_package' => 'Sea consolidation manifest', 'print_consolidate' => 'Consolidation manifest', 'consolidate_parking_list_excel' => 'Packing list',
        'print_customer_package_track' => 'Package tracking sheet', 'waybill_print' => 'Waybill', 'warehouse_print' => 'Warehouse sheet',
    ];
    if (isset($map[$base])) return $map[$base];
    foreach (sl_documentCatalogue() as $e) if (strtok($e['file'], '?') === $ep) return $e['label'] . ' · ' . $e['fmt'];
    return ucfirst(str_replace(['print_', '_excel', '_'], ['', ' export', ' '], $base));
}

/** Record ids a document was made for: ?id=, or the JSON list a bulk print sends in ?data=. */
function sl_documentIds(array $params): array
{
    if (!empty($params['id'])) return [(int) $params['id']];
    if (!empty($params['data'])) {
        $j = json_decode((string) $params['data'], true);
        if (is_array($j)) return array_values(array_filter(array_map('intval', $j)));
    }
    return [];
}

function sl_documentsData($user, $core): array
{
    require_once CDP_ROOT . '/helpers/finance_switch.php';
    $all = $user->cdp_hasPermission('view_activity_logs');
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $filter = (string) ($_GET['filter'] ?? 'all');
    $by = (int) ($_GET['by'] ?? 0);
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : '';
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : '';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 30;
    $rows = []; $count = 0; $facets = []; $people = []; $tiles = [];
    $map = ['invoices' => "endpoint LIKE 'print_inv_ship%'", 'receipts' => "(endpoint LIKE 'print_invoice_package%' OR endpoint LIKE 'print_charge%')", 'labels' => "endpoint LIKE '%label%'",
        'manifests' => "(endpoint LIKE '%consolidate%' OR endpoint LIKE '%parking%' OR endpoint LIKE 'waybill%')", 'exports' => "(verb = 'export' OR endpoint LIKE '%excel%' OR endpoint LIKE '%csv%')"];
    if (sl_tableExists('cdb_activity_log')) {
        $base = "entity_type = 'document'" . ($all ? '' : ' AND user_id = ' . (int) $_SESSION['userid']);
        $where = $base;
        $binds = [];
        if ($q !== '') {
            // a tracking number or customer name finds the documents printed for that record
            $ids = [];
            foreach (sl_rows("SELECT o.order_id FROM cdb_add_order o LEFT JOIN cdb_users u ON u.id = o.sender_id WHERE CONCAT(o.order_prefix, o.order_no) LIKE :a OR CONCAT_WS(' ', u.fname, u.lname, u.locker) LIKE :b LIMIT 200", [':a' => "%$q%", ':b' => "%$q%"]) as $o) $ids[] = (int) $o->order_id;
            foreach (sl_rows("SELECT o.order_id FROM cdb_customers_packages o LEFT JOIN cdb_users u ON u.id = o.sender_id WHERE CONCAT(o.order_prefix, o.order_no) LIKE :a OR CONCAT_WS(' ', u.fname, u.lname, u.locker) LIKE :b LIMIT 200", [':a' => "%$q%", ':b' => "%$q%"]) as $o) $ids[] = (int) $o->order_id;
            $idW = $ids ? ' OR entity_id IN (' . implode(',', array_unique($ids)) . ')' : '';
            $where .= " AND (summary LIKE :q1 OR entity_id LIKE :q2 OR actor_name LIKE :q3 OR endpoint LIKE :q4 OR meta LIKE :q5$idW)";
            $binds = [':q1' => "%$q%", ':q2' => "%$q%", ':q3' => "%$q%", ':q4' => "%$q%", ':q5' => "%$q%"];
        }
        if ($by && $all) $where .= ' AND user_id = ' . $by;
        if ($from !== '') { $where .= ' AND created_at >= :f'; $binds[':f'] = $from; }
        if ($to !== '') { $where .= ' AND created_at < :t'; $binds[':t'] = date('Y-m-d', strtotime($to . ' +1 day')); }
        // counts per type for the current search (before the type filter)
        $case = 'CASE ' . implode(' ', array_map(fn($k, $w) => "WHEN $w THEN '$k'", array_keys($map), $map)) . " ELSE 'other' END";
        foreach (sl_rows("SELECT $case AS k, COUNT(*) AS n FROM cdb_activity_log WHERE $where GROUP BY k", $binds) as $f) $facets[$f->k] = (int) $f->n;
        $facets['all'] = array_sum($facets);
        if (isset($map[$filter])) $where .= ' AND ' . $map[$filter];
        $count = (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE $where", $binds);
        if ($all) $people = sl_rows("SELECT user_id AS id, MAX(actor_name) AS name, COUNT(*) AS n FROM cdb_activity_log WHERE $base AND created_at >= NOW() - INTERVAL 90 DAY AND user_id > 0 GROUP BY user_id ORDER BY n DESC LIMIT 40");
        $tiles = [
            ['Today', (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE $base AND created_at >= CURDATE()"), 'i-clock'],
            ['Last 7 days', (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE $base AND created_at >= CURDATE() - INTERVAL 6 DAY"), 'i-calendar'],
            ['Labels · 7 days', (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE $base AND {$map['labels']} AND created_at >= CURDATE() - INTERVAL 6 DAY"), 'i-tag'],
            ['Invoices & receipts · 7 days', (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE $base AND ({$map['invoices']} OR {$map['receipts']}) AND created_at >= CURDATE() - INTERVAL 6 DAY"), 'i-receipt'],
        ];
        foreach (sl_rows("SELECT id, created_at, actor_name, verb, summary, entity_id, endpoint, meta FROM cdb_activity_log WHERE $where ORDER BY id DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds) as $r) {
            $meta = json_decode((string) $r->meta, true) ?: [];
            $params = is_array($meta['params'] ?? null) ? $meta['params'] : [];
            unset($params['legacy']);
            $params = array_filter($params, fn($v) => is_scalar($v) && (string) $v !== '');
            $ep = basename((string) ($r->endpoint ?: (($meta['page'] ?? '') . '.php')));
            $catName = sl_documentCategory($ep);
            $ids = sl_documentIds($params);
            if (!$ids && $r->entity_id !== '' && $r->entity_id !== null && ctype_digit((string) $r->entity_id)) $ids = [(int) $r->entity_id];
            $recs = array_map(fn($id) => sl_documentNumber($ep, $id, $params, true), array_slice($ids, 0, 4));
            $whoList = array_values(array_unique(array_filter(array_column($recs, 'who'))));
            $range = !empty($params['range']) ? str_replace('/', '-', (string) $params['range']) : '';
            $rows[] = [
                'title' => sl_documentName($ep, $params), 'cat' => $catName,
                'icon' => ['labels' => 'i-tag', 'receipts' => 'i-receipt', 'invoices' => 'i-receipt', 'manifests' => 'i-layers', 'exports' => 'i-download'][$catName],
                'recs' => array_column($recs, 't'), 'more' => max(0, count($ids) - 4), 'n' => count($ids),
                'who' => $whoList ? implode(', ', array_slice($whoList, 0, 2)) . (count($whoList) > 2 ? ' +' . (count($whoList) - 2) : '') : '',
                'range' => $range, 'by' => $r->actor_name ?: 'System', 'date' => sl_date($r->created_at, 'M j, H:i'), 'ago' => sl_ago($r->created_at),
                'href' => preg_match('/^[a-z0-9_]+\.php$/', $ep) && is_file(CDP_ROOT . '/' . $ep) ? $ep . ($params ? '?' . http_build_query(array_filter($params, 'is_scalar')) : '') : '',
            ];
        }
    }
    return [
        'prints' => sl_documentPrints(), 'all' => $all,
        'rows' => $rows, 'count' => $count, 'page' => $page, 'per' => $per, 'q' => $q, 'filter' => $filter, 'by' => $by, 'from' => $from, 'to' => $to,
        'facets' => $facets, 'people' => $people, 'tiles' => $tiles,
        'filters' => [['f' => 'all', 'label' => 'All'], ['f' => 'invoices', 'label' => 'Invoices'], ['f' => 'receipts', 'label' => 'Receipts'], ['f' => 'labels', 'label' => 'Labels'], ['f' => 'manifests', 'label' => 'Manifests'], ['f' => 'exports', 'label' => 'Exports']],
        'canLabels' => $user->cdp_hasPermission(['print_label', 'view_tools']),
        'canReports' => $user->cdp_hasPermission('view_general_reports'),
    ];
}

/** Human number of the record a print was made for (shipment/package tracking). */
function sl_documentNumber(string $ep, int $id, array $params, bool $withOwner = false)
{
    static $cache = [];
    $k = $ep . ':' . $id . ':' . ($params['type'] ?? '');
    if (isset($cache[$k])) return $withOwner ? $cache[$k] : $cache[$k]['t'];
    $tbl = null;
    if (strpos($ep, 'consolidate') !== false) {
        $r = sl_row(strpos($ep, 'package') !== false ? 'SELECT CONCAT(c_prefix, c_no) AS t, sender_id FROM cdb_consolidate_packages WHERE consolidate_id = :id' : 'SELECT CONCAT(c_prefix, c_no) AS t, sender_id FROM cdb_consolidate WHERE consolidate_id = :id', [':id' => $id]);
    } elseif (strpos($ep, 'package') !== false && ($params['type'] ?? 'sea') !== 'air') {
        $r = sl_row('SELECT CONCAT(order_prefix, order_no) AS t, sender_id FROM cdb_customers_packages WHERE order_id = :id', [':id' => $id]);
    } else {
        $r = sl_row('SELECT CONCAT(order_prefix, order_no) AS t, sender_id FROM cdb_add_order WHERE order_id = :id', [':id' => $id]);
    }
    $who = '';
    if ($r && (int) $r->sender_id) {
        $u = sl_row('SELECT fname, lname FROM cdb_users WHERE id = :u', [':u' => (int) $r->sender_id]);
        $who = $u ? trim($u->fname . ' ' . $u->lname) : '';
    }
    $cache[$k] = ['t' => $r ? (string) $r->t : '#' . $id, 'who' => $who];
    return $withOwner ? $cache[$k] : $cache[$k]['t'];
}
