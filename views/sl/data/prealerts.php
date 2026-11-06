<?php
/** Data for PreAlerts.dc.html (cdb_pre_alert). */
require_once __DIR__ . '/ops_common.php';

function sl_preAlertsData($user, $core): array
{
    $isClient = sl_userIs('client');
    $uid = (int) $_SESSION['userid'];
    $scope = '';
    if ($isClient) {
        $scope = " AND p.customer_id = $uid";
    } else {
        $ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : ['is_restricted' => false];
        if (!empty($ctx['is_restricted']) && !empty($ctx['agency_id'])) {
            $aid = (int) $ctx['agency_id'];
            // same rule as ajax/pre_alerts/prealert_list_ajax.php
            $scope = " AND (p.customer_id IN (SELECT sender_id FROM cdb_add_order WHERE agency = $aid) OR p.customer_id IN (SELECT sender_id FROM cdb_customers_packages WHERE agency = $aid) OR p.customer_id IN (SELECT id FROM cdb_users WHERE agency_id = $aid))";
        }
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $filter = (string) ($_GET['filter'] ?? 'open');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $where = "1 = 1 $scope";
    $binds = [];
    if ($q !== '') {
        $where .= " AND (p.tracking LIKE :q OR p.provider_shop LIKE :q OR p.package_description LIKE :q OR p.customer_id IN (SELECT id FROM cdb_users WHERE CONCAT(fname,' ',lname) LIKE :q OR locker LIKE :q))";
        $binds[':q'] = '%' . $q . '%';
    }
    if ($filter === 'open') $where .= ' AND p.is_package = 0';
    if ($filter === 'matched') $where .= ' AND p.is_package = 1';
    $count = (int) sl_scalar("SELECT COUNT(*) FROM cdb_pre_alert p WHERE $where", $binds);
    $rows = sl_rows("SELECT p.*, c.name_com FROM cdb_pre_alert p LEFT JOIN cdb_courier_com c ON c.id = p.courier_com WHERE $where ORDER BY p.prealert_date DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds);
    $users = sl_usersById(array_map(fn($r) => $r->customer_id, $rows));
    $list = [];
    foreach ($rows as $r) {
        $u = $users[(int) $r->customer_id] ?? null;
        $late = !$r->is_package && $r->estimated_date && strtotime($r->estimated_date) < strtotime('today');
        $list[] = [
            'id' => (int) $r->pre_alert_id, 'trk' => $r->tracking, 'name' => sl_userName($u), 'ini' => sl_initials(sl_userName($u)), 'locker' => $u->locker ?? '',
            'sup' => $r->provider_shop, 'carrier' => $r->name_com ?: '—', 'what' => $r->package_description, 'price' => sl_money($r->purchase_price),
            'eta' => sl_date($r->estimated_date), 'sent' => sl_ago($r->prealert_date), 'invoice' => $r->url_invoice,
            'st' => $r->is_package ? 'Matched' : ($late ? 'Overdue' : 'Expected'), 'cls' => $r->is_package ? 'delivered' : ($late ? 'overdue' : 'pending'), 'open' => !$r->is_package,
        ];
    }
    $base = "FROM cdb_pre_alert p WHERE 1 = 1 $scope";
    return [
        'rows' => $list, 'count' => $count, 'page' => $page, 'per' => $per, 'q' => $q, 'filter' => $filter,
        'filters' => [['f' => 'open', 'label' => 'Open'], ['f' => 'matched', 'label' => 'Matched'], ['f' => 'all', 'label' => 'All']],
        'openN' => (int) sl_scalar("SELECT COUNT(*) $base AND p.is_package = 0"), 'weekN' => (int) sl_scalar("SELECT COUNT(*) $base AND p.prealert_date >= NOW() - INTERVAL 7 DAY"),
        'isClient' => $isClient,
        'canAdd' => $user->cdp_hasPermission('prealert_add'),
        'canPackage' => !$isClient && $user->cdp_hasPermission('add_package'),
        'canShipment' => !$isClient && $user->cdp_hasPermission('invoice_prealert'),
        'canInvoice' => $user->cdp_hasPermission('see_attached_invoice_pre-alerts') || $isClient,
        'customers' => $isClient ? [] : sl_opsCustomers(),
        'couriers' => sl_rows('SELECT id, name_com FROM cdb_courier_com ORDER BY name_com'),
        'openForm' => !empty($_GET['new']),
    ];
}
