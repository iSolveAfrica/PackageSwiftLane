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
    if ($filter === 'overdue') $where .= ' AND p.is_package = 0 AND p.estimated_date < CURDATE()';
    if ($filter === 'week') $where .= ' AND p.is_package = 0 AND p.estimated_date >= CURDATE() AND p.estimated_date < CURDATE() + INTERVAL 7 DAY';
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
            'lateBy' => $late ? (int) floor((strtotime('today') - strtotime($r->estimated_date)) / 86400) : 0,
            'st' => $r->is_package ? 'Matched' : ($late ? 'Overdue' : 'Expected'), 'cls' => $r->is_package ? 'delivered' : ($late ? 'overdue' : 'pending'), 'open' => !$r->is_package,
        ];
    }
    $base = "FROM cdb_pre_alert p WHERE 1 = 1 $scope";
    $stats = sl_preAlertStats($user, $base, $isClient);
    return [
        'stats' => $stats,
        'rows' => $list, 'count' => $count, 'page' => $page, 'per' => $per, 'q' => $q, 'filter' => $filter,
        'filters' => [['f' => 'open', 'label' => 'Open'], ['f' => 'week', 'label' => 'Due this week'], ['f' => 'overdue', 'label' => 'Overdue'], ['f' => 'matched', 'label' => 'Arrived'], ['f' => 'all', 'label' => 'All']],
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

/**
 * Statistics cards above the list. $base is "FROM cdb_pre_alert p WHERE 1 = 1 <scope>" so every figure
 * follows the same customer / agency scope as the list.
 */
function sl_preAlertStats($user, string $base, bool $isClient): array
{
    require_once __DIR__ . '/overview_hero.php';
    $open = (int) sl_scalar("SELECT COUNT(*) $base AND p.is_package = 0");
    $week = (int) sl_scalar("SELECT COUNT(*) $base AND p.is_package = 0 AND p.estimated_date >= CURDATE() AND p.estimated_date < CURDATE() + INTERVAL 7 DAY");
    $today = (int) sl_scalar("SELECT COUNT(*) $base AND p.is_package = 0 AND p.estimated_date = CURDATE()");
    $overdue = (int) sl_scalar("SELECT COUNT(*) $base AND p.is_package = 0 AND p.estimated_date < CURDATE()");
    $oldest = sl_scalar("SELECT MIN(p.estimated_date) $base AND p.is_package = 0 AND p.estimated_date < CURDATE()", [], null);
    $late7 = (int) sl_scalar("SELECT COUNT(*) $base AND p.is_package = 0 AND p.estimated_date < CURDATE() - INTERVAL 7 DAY");
    $sent30 = (int) sl_scalar("SELECT COUNT(*) $base AND p.prealert_date >= CURDATE() - INTERVAL 29 DAY");
    $matched30 = (int) sl_scalar("SELECT COUNT(*) $base AND p.is_package = 1 AND p.prealert_date >= CURDATE() - INTERVAL 29 DAY");
    $matchedAll = (int) sl_scalar("SELECT COUNT(*) $base AND p.is_package = 1");
    $value = (float) sl_scalar("SELECT COALESCE(SUM(p.purchase_price),0) $base AND p.is_package = 0");
    // expected arrivals, next 14 days (forecast for the warehouse)
    $fwd = [];
    for ($i = 0; $i < 14; $i++) $fwd[date('Y-m-d', strtotime("+$i days"))] = 0;
    foreach (sl_rows("SELECT p.estimated_date AS day, COUNT(*) AS n $base AND p.is_package = 0 AND p.estimated_date >= CURDATE() AND p.estimated_date < CURDATE() + INTERVAL 14 DAY GROUP BY p.estimated_date") as $r) {
        if (isset($fwd[$r->day])) $fwd[$r->day] = (int) $r->n;
    }
    // sent per day, last 14 days
    $sent = sl_ovZeroDays();
    foreach (sl_rows("SELECT DATE(p.prealert_date) AS day, COUNT(*) AS n $base AND p.prealert_date >= :d GROUP BY DATE(p.prealert_date)", [':d' => array_key_first($sent)]) as $r) {
        if (isset($sent[$r->day])) $sent[$r->day] = (int) $r->n;
    }
    // overdue by how late
    $late = ['1–2' => 0, '3–7' => 0, '8–14' => 0, '15–30' => 0, '30+' => 0];
    foreach (sl_rows("SELECT DATEDIFF(CURDATE(), p.estimated_date) AS d, COUNT(*) AS n $base AND p.is_package = 0 AND p.estimated_date < CURDATE() GROUP BY d") as $r) {
        $dd = (int) $r->d;
        $late[$dd <= 2 ? '1–2' : ($dd <= 7 ? '3–7' : ($dd <= 14 ? '8–14' : ($dd <= 30 ? '15–30' : '30+')))] += (int) $r->n;
    }
    $shops = sl_rows("SELECT COALESCE(NULLIF(TRIM(p.provider_shop),''),'Not given') AS shop, COUNT(*) AS n $base AND p.prealert_date >= CURDATE() - INTERVAL 89 DAY GROUP BY shop ORDER BY n DESC LIMIT 4");
    $shopMax = max(1, ...array_map(fn($r) => (int) $r->n, $shops ?: [(object) ['n' => 1]]));
    $bars = fn(array $m, callable $lab) => sl_sparkBars(array_values($m), 4, array_map($lab, array_keys($m)));
    return [
        'open' => $open, 'week' => $week, 'today' => $today, 'overdue' => $overdue, 'late7' => $late7,
        'oldest' => $oldest ? (int) floor((strtotime('today') - strtotime($oldest)) / 86400) : 0,
        'sent30' => $sent30, 'matched30' => $matched30, 'matchedAll' => $matchedAll, 'rate' => $sent30 ? sl_pct($matched30, $sent30) : 0,
        'value' => sl_money($value), 'showValue' => $isClient || (function_exists('cdp_canViewMoney') && cdp_canViewMoney($user)),
        'fwd' => $bars($fwd, fn($x) => date('D j M', strtotime($x))), 'fwdN' => array_sum($fwd),
        'sent' => $bars($sent, fn($x) => date('D j M', strtotime($x))), 'sentN' => array_sum($sent),
        'late' => sl_sparkBars(array_values($late), 4, array_map(fn($k) => $k . ' days late', array_keys($late))), 'lateL' => array_keys($late),
        'shops' => array_map(fn($r) => ['name' => $r->shop, 'n' => (int) $r->n, 'w' => sl_pct($r->n, $shopMax)], $shops),
    ];
}
