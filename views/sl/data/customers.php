<?php
/** Data for the Customers list (Customers.dc.html). */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_charts.php';
require_once CDP_ROOT . '/helpers/finance_switch.php';

function sl_customersData($user, $core): array
{
    $d = [];
    $hasApprove = sl_columnExists('cdb_users', 'approve');
    $hasSource = sl_columnExists('cdb_users', 'signup_source');
    $hasBlocks = sl_tableExists('cdb_customer_blocks');
    $hasPhoneV = sl_tableExists('cdb_profile_phone_verified');
    $hasDoc = sl_columnExists('cdb_users', 'document_photo');
    $ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : ['is_restricted' => false];
    $scope = !empty($ctx['is_restricted']) && !empty($ctx['agency_id']) ? ' AND u.agency_id = ' . (int) $ctx['agency_id'] : '';
    $base = "FROM cdb_users u WHERE u.userlevel = 1 $scope";

    $total = (int) sl_scalar("SELECT COUNT(*) $base");
    $newM = (int) sl_scalar("SELECT COUNT(*) $base AND u.created >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $prevM = (int) sl_scalar("SELECT COUNT(*) $base AND u.created >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') AND u.created < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $active = (int) sl_scalar("SELECT COUNT(DISTINCT u.id) $base AND u.id IN (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= CURDATE() - INTERVAL 90 DAY UNION SELECT sender_id FROM cdb_customers_packages WHERE order_date >= CURDATE() - INTERVAL 90 DAY)");
    $phoneV = $hasPhoneV ? (int) sl_scalar("SELECT COUNT(*) $base AND u.id IN (SELECT user_id FROM cdb_profile_phone_verified)") : 0;
    $idV = $hasDoc ? (int) sl_scalar("SELECT COUNT(*) $base AND u.document_photo IS NOT NULL AND u.document_photo <> ''") : ($hasApprove ? (int) sl_scalar("SELECT COUNT(*) $base AND u.approve = 1") : 0);
    $repeat = (int) sl_scalar("SELECT COUNT(*) FROM (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND YEAR(order_date) = YEAR(CURDATE()) GROUP BY sender_id HAVING COUNT(*) >= 2) x");
    $shippers = (int) sl_scalar("SELECT COUNT(DISTINCT sender_id) FROM cdb_add_order WHERE is_deleted = 0 AND YEAR(order_date) = YEAR(CURDATE())");
    $parcelsYear = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order WHERE is_deleted = 0 AND YEAR(order_date) = YEAR(CURDATE())");
    $finance = cdp_financeEnabled();
    $due = $finance ? sl_row("SELECT COUNT(DISTINCT sender_id) AS n, COALESCE(SUM(total_order),0) AS amt FROM cdb_add_order WHERE is_deleted = 0 AND status_invoice = 23 AND status_courier <> 21") : null;
    $inactive = $total - $active;
    $blocked = $hasBlocks ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_customer_blocks WHERE unblocked_at IS NULL") : (int) sl_scalar("SELECT COUNT(*) $base AND u.active = 0");
    $csat = sl_tableExists('cdb_cs_tickets') ? sl_row('SELECT AVG(csat_score) AS avg, COUNT(csat_score) AS n FROM cdb_cs_tickets WHERE csat_score IS NOT NULL') : null;

    $d['kpi'] = [
        ['href' => sl_route('Customers'), 'label' => 'Total customers', 'value' => sl_num($total), 'sub' => '▲ ' . $newM . ' this month'],
        ['href' => sl_url(['filter' => 'active']), 'label' => 'Active (90 days)', 'value' => sl_num($active), 'sub' => sl_pct($active, $total) . '% of all'],
        ['href' => sl_url(['filter' => 'new']), 'label' => 'New this month', 'value' => sl_num($newM), 'sub' => $prevM ? ($newM >= $prevM ? '▲ ' : '▼ ') . abs(sl_pct($newM - $prevM, $prevM)) . '% vs ' . date('M', strtotime('first day of last month')) : 'first month with sign-ups'],
        ['href' => sl_url(['filter' => 'phone']), 'label' => 'Verified phone', 'value' => sl_num($phoneV), 'sub' => sl_pct($phoneV, $total) . '% · WhatsApp OTP'],
        ['href' => sl_url(['filter' => $hasApprove ? 'approved' : 'all']), 'label' => 'ID verified', 'value' => sl_num($idV), 'sub' => sl_pct($idV, $total) . '% · ID on file'],
        ['href' => sl_route('Reports'), 'label' => 'Repeat customers', 'value' => sl_pct($repeat, $shippers) . '%', 'sub' => 'shipped 2+ times'],
        ['href' => sl_route('Reports'), 'label' => 'Avg parcels each', 'value' => $shippers ? number_format($parcelsYear / $shippers, 1) : '0', 'sub' => 'per customer this year'],
    ];
    if ($finance && $due) {
        $d['kpi'][] = ['href' => sl_route('AccountsReceivable'), 'label' => 'With balance due', 'value' => sl_num($due->n), 'sub' => sl_money($due->amt) . ' owed'];
    }
    $d['kpi'][] = ['href' => sl_url(['filter' => 'quiet']), 'label' => 'Inactive 90 days', 'value' => sl_num($inactive), 'sub' => 'win-back list ready'];
    if ($csat && $csat->n) {
        $d['kpi'][] = ['href' => sl_route('CsReports'), 'label' => 'Satisfaction', 'value' => number_format((float) $csat->avg, 1) . ' ★', 'sub' => 'from ' . sl_num($csat->n) . ' ratings'];
    }

    // growth: new per month (12 months) + active customers per month
    $months = [];
    $activeSeries = [];
    $maxNew = 1;
    $rowsNew = sl_rows("SELECT DATE_FORMAT(u.created, '%Y-%m') AS ym, COUNT(*) AS n $base AND u.created >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01') GROUP BY ym");
    $mapNew = [];
    foreach ($rowsNew as $r) $mapNew[$r->ym] = (int) $r->n;
    $rowsAct = sl_rows("SELECT DATE_FORMAT(order_date, '%Y-%m') AS ym, COUNT(DISTINCT sender_id) AS n FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01') GROUP BY ym");
    $mapAct = [];
    foreach ($rowsAct as $r) $mapAct[$r->ym] = (int) $r->n;
    for ($i = 11; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("first day of -$i months"));
        $maxNew = max($maxNew, $mapNew[$ym] ?? 0);
    }
    for ($i = 11; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("first day of -$i months"));
        $n = $mapNew[$ym] ?? 0;
        $months[] = ['l' => date('M', strtotime($ym . '-01')), 'v' => $n, 'h' => 'height:' . max(2, sl_pct($n, $maxNew)) . '%', 'tip' => date('F Y', strtotime($ym . '-01')) . ": $n new, " . ($mapAct[$ym] ?? 0) . ' active', 'now' => $i === 0 ? 'now' : ''];
        $activeSeries[] = $mapAct[$ym] ?? 0;
    }
    $d['months'] = $months;
    $d['activeSeries'] = $activeSeries;
    $d['line'] = sl_svgPoints($activeSeries, 1200, 160, 16, max(1, max($activeSeries)), 50);
    $pts = explode(' ', $d['line']);
    [$d['dotX'], $d['dotY']] = explode(',', end($pts) ?: '1150,160');
    $d['activeNow'] = sl_num(sl_tableExists('cdb_staff_presence') ? 0 : (int) sl_scalar("SELECT COUNT(*) $base AND u.lastlogin >= NOW() - INTERVAL 15 MINUTE"));
    $d['today'] = (int) sl_scalar("SELECT COUNT(*) $base AND DATE(u.created) = CURDATE()");
    $d['clock'] = date('H:i');
    $feed = [];
    $cityCol = sl_columnExists('cdb_users', 'city') ? 'u.city' : "''";
    foreach (sl_rows("SELECT u.fname, u.lname, u.created, $cityCol AS city " . ($hasSource ? ', u.signup_source' : ", '' AS signup_source") . " $base ORDER BY u.id DESC LIMIT 5") as $r) {
        $feed[] = ['ini' => sl_initials($r->fname . ' ' . $r->lname), 'name' => trim($r->fname . ' ' . $r->lname), 'what' => 'signed up', 'city' => $r->city ?: '—', 'src' => $r->signup_source ?: 'Website', 'at' => sl_ago($r->created), 'anim' => ''];
    }
    $d['feed'] = $feed;

    // ship by
    $air = (int) sl_scalar("SELECT COUNT(DISTINCT sender_id) FROM cdb_add_order WHERE is_deleted = 0 AND sender_id NOT IN (SELECT DISTINCT sender_id FROM cdb_customers_packages)");
    $sea = (int) sl_scalar("SELECT COUNT(DISTINCT sender_id) FROM cdb_customers_packages WHERE sender_id NOT IN (SELECT DISTINCT sender_id FROM cdb_add_order WHERE is_deleted = 0)");
    $both = (int) sl_scalar("SELECT COUNT(DISTINCT sender_id) FROM cdb_add_order WHERE is_deleted = 0 AND sender_id IN (SELECT DISTINCT sender_id FROM cdb_customers_packages)");
    $tot = max(1, $air + $sea + $both);
    $d['shipBy'] = ['air' => sl_pct($air, $tot), 'both' => sl_pct($both, $tot), 'sea' => sl_pct($sea, $tot)];
    $d['status'] = ['active' => $active, 'quiet' => $inactive, 'new' => $newM, 'blocked' => $blocked];
    $tenure = (float) sl_scalar("SELECT AVG(DATEDIFF(CURDATE(), u.created)) $base");
    $d['tenure'] = $tenure >= 365 ? floor($tenure / 365) . ' yr ' . floor(($tenure % 365) / 30) . ' mo' : floor($tenure / 30) . ' mo';
    $gap = (float) sl_scalar("SELECT AVG(g) FROM (SELECT DATEDIFF(MAX(order_date), MIN(order_date)) / GREATEST(COUNT(*) - 1, 1) AS g FROM cdb_add_order WHERE is_deleted = 0 GROUP BY sender_id HAVING COUNT(*) > 1) x");
    $d['shipEvery'] = $gap > 0 ? round($gap) . ' days' : '—';

    // cities and sources
    $cities = sl_rows("SELECT COALESCE(NULLIF($cityCol,''), s.city_name, 'Unknown') AS city, COUNT(*) AS n FROM cdb_users u LEFT JOIN (SELECT sa.user_id, c.name AS city_name FROM cdb_senders_addresses sa LEFT JOIN cdb_cities c ON c.id = sa.city GROUP BY sa.user_id) s ON s.user_id = u.id WHERE u.userlevel = 1 $scope GROUP BY city ORDER BY n DESC LIMIT 6");
    $cmax = $cities ? max(1, (int) $cities[0]->n) : 1;
    $d['cities'] = array_map(fn($r) => ['label' => $r->city, 'n' => (int) $r->n, 'w' => sl_pct($r->n, $cmax)], $cities);
    $sources = $hasSource ? sl_rows("SELECT COALESCE(NULLIF(u.signup_source,''), 'Not recorded') AS src, COUNT(*) AS n $base GROUP BY src ORDER BY n DESC LIMIT 5") : [];
    $smax = $sources ? max(1, (int) $sources[0]->n) : 1;
    $d['sources'] = array_map(fn($r) => ['label' => $r->src, 'n' => (int) $r->n, 'w' => sl_pct($r->n, $smax)], $sources);
    $top = sl_rows("SELECT u.id, u.fname, u.lname, COUNT(o.order_id) AS n, COALESCE(SUM(o.total_order),0) AS amt FROM cdb_add_order o JOIN cdb_users u ON u.id = o.sender_id WHERE o.is_deleted = 0 AND YEAR(o.order_date) = YEAR(CURDATE()) GROUP BY u.id ORDER BY amt DESC LIMIT 5");
    $d['top'] = array_map(fn($r) => ['id' => (int) $r->id, 'name' => trim($r->fname . ' ' . $r->lname), 'ini' => sl_initials($r->fname . ' ' . $r->lname), 'n' => (int) $r->n, 'amt' => sl_money($r->amt)], $top);

    // list
    $q = trim((string) ($_GET['q'] ?? ''));
    $filter = (string) ($_GET['filter'] ?? 'all');
    $city = trim((string) ($_GET['city'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $where = "u.userlevel = 1 $scope";
    $binds = [];
    if ($q !== '') {
        $where .= " AND (u.fname LIKE :q OR u.lname LIKE :q OR CONCAT(u.fname,' ',u.lname) LIKE :q OR u.email LIKE :q OR u.phone LIKE :q OR u.locker LIKE :q OR u.username LIKE :q)";
        $binds[':q'] = '%' . $q . '%';
    }
    switch ($filter) {
        case 'active': $where .= " AND u.id IN (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= CURDATE() - INTERVAL 90 DAY)"; break;
        case 'quiet': $where .= " AND u.id NOT IN (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= CURDATE() - INTERVAL 90 DAY)"; break;
        case 'new': $where .= " AND u.created >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"; break;
        case 'due': $where .= " AND u.id IN (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND status_invoice = 23 AND status_courier <> 21)"; break;
        case 'blocked': $where .= $hasBlocks ? " AND u.id IN (SELECT user_id FROM cdb_customer_blocks WHERE unblocked_at IS NULL)" : " AND u.active = 0"; break;
        case 'unapproved': $where .= $hasApprove ? " AND u.approve = 0" : ''; break;
        case 'approved': $where .= $hasApprove ? " AND u.approve = 1" : ''; break;
        case 'phone': $where .= $hasPhoneV ? " AND u.id IN (SELECT user_id FROM cdb_profile_phone_verified)" : ''; break;
    }
    if ($city !== '') {
        $where .= " AND $cityCol = :city";
        $binds[':city'] = $city;
    }
    $count = (int) sl_scalar("SELECT COUNT(*) FROM cdb_users u WHERE $where", $binds);
    $blockJoin = $hasBlocks ? "LEFT JOIN cdb_customer_blocks b ON b.user_id = u.id AND b.unblocked_at IS NULL" : '';
    $blockCols = $hasBlocks ? 'b.reason AS block_reason, b.id AS block_id' : 'NULL AS block_reason, NULL AS block_id';
    $rows = sl_rows("SELECT u.id, u.fname, u.lname, u.email, u.locker, u.phone, u.active, u.created, $cityCol AS city, $blockCols,
            (SELECT COUNT(*) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.is_deleted = 0) AS n,
            (SELECT COALESCE(SUM(o.total_order),0) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.is_deleted = 0 AND o.status_invoice = 23 AND o.status_courier <> 21) AS bal,
            (SELECT MAX(o.order_date) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.is_deleted = 0) AS last_order
            " . ($hasApprove ? ', u.approve' : ', 1 AS approve') . "
        FROM cdb_users u $blockJoin WHERE $where ORDER BY u.id DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds);
    $list = [];
    foreach ($rows as $r) {
        $isBlocked = $r->block_id || (int) $r->active === 0;
        $bal = (float) $r->bal;
        if ($isBlocked) { $st = ['Blocked', 'overdue']; $why = $r->block_reason ?: 'Account inactive'; }
        elseif (!(int) $r->approve) { $st = ['Awaiting approval', 'pending']; $why = 'KYC to review'; }
        elseif ($finance && $bal > 0) { $st = ['Balance due', 'onway']; $why = 'Unpaid invoices'; }
        elseif ($r->last_order && strtotime($r->last_order) >= strtotime('-90 days')) { $st = ['Active', 'delivered']; $why = 'Last parcel ' . sl_ago($r->last_order); }
        else { $st = ['Quiet', 'plain']; $why = $r->last_order ? 'Last parcel ' . sl_date($r->last_order) : 'No parcels yet'; }
        $list[] = ['id' => (int) $r->id, 'name' => trim($r->fname . ' ' . $r->lname), 'ini' => sl_initials($r->fname . ' ' . $r->lname), 'mail' => $r->email, 'locker' => $r->locker ?: '—', 'phone' => $r->phone ?: '—', 'city' => $r->city ?: '—', 'n' => (int) $r->n, 'bal' => $finance ? sl_money($bal) : '—', 'st' => $st[0], 'cls' => $st[1], 'why' => $why, 'blocked' => $isBlocked, 'approve' => (int) $r->approve];
    }
    $d['rows'] = $list;
    $d['count'] = $count;
    $d['page'] = $page;
    $d['per'] = $per;
    $d['q'] = $q;
    $d['filter'] = $filter;
    $d['city'] = $city;
    $d['filters'] = [['f' => 'all', 'label' => 'All'], ['f' => 'active', 'label' => 'Active'], ['f' => 'due', 'label' => 'Balance due'], ['f' => 'unapproved', 'label' => 'To approve'], ['f' => 'blocked', 'label' => 'Blocked']];
    $d['cityOptions'] = array_map(fn($c) => $c['label'], array_filter($d['cities'], fn($c) => $c['label'] !== 'Unknown'));
    $d['hasApprove'] = $hasApprove;
    $d['canEdit'] = $user->cdp_hasPermission('edit_client');
    $d['canDelete'] = $user->cdp_hasPermission('delete_client');
    $d['canApprove'] = $hasApprove && $user->cdp_hasPermission(['approve_client', 'edit_client']);
    $d['total'] = $total;
    $d['newM'] = $newM;
    return $d;
}
