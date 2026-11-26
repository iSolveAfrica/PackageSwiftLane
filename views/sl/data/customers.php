<?php
/** Data for the Customers list (Customers.dc.html). The statistics panel loads its detail on demand
 *  from ajax/sl/customers_stats_ajax.php, which shares the scope and filter definitions below so a
 *  tile, its panel and "Show all N in the table" always count the same customers. */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_charts.php';
require_once CDP_ROOT . '/helpers/finance_switch.php';

/**
 * Agency scope for customer queries on alias u: [sql, binds]. Agency-restricted staff only see their
 * agency's customers; a restricted account without an agency sees none (fails closed).
 */
function sl_customersScope(): array
{
    static $scope = null;
    if ($scope === null) {
        $ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : ['is_restricted' => false];
        $scope = empty($ctx['is_restricted']) ? ['', []] : [' AND u.agency_id = :cx_ag', [':cx_ag' => (int) ($ctx['agency_id'] ?? 0)]];
    }
    return $scope;
}

/**
 * Who may see the customer list and its statistics: view_client_list, held by staff or an agency.
 * Customer and driver accounts never see other customers, even where a role grants the permission
 * (names, phones and e-mails of every customer).
 */
function sl_customersCanView($user): bool
{
    return $user->cdp_hasPermission('view_client_list') && !sl_userIs('client') && !sl_userIs('driver');
}

/** Only the named parameters the statement uses (PDO refuses unused ones). */
function sl_cxBinds(string $sql, array $binds): array
{
    return array_filter($binds, fn($k) => (bool) preg_match('/' . preg_quote($k, '/') . '\b/', $sql), ARRAY_FILTER_USE_KEY);
}

/** A validated YYYY-MM month, or null. */
function sl_customersMonth($ym): ?string
{
    $ym = (string) $ym;
    if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m) || !checkdate((int) $m[2], 1, (int) $m[1]) || (int) $m[1] < 2000 || (int) $m[1] > (int) date('Y') + 1) {
        return null;
    }
    return $ym;
}

/** Labels of every list filter (the segmented control shows the first five). */
function sl_customersFilterLabels(?string $ym = null): array
{
    return [
        'all' => 'All customers', 'active' => 'Active (90 days)', 'quiet' => 'Inactive 90 days', 'new' => 'New this month',
        'due' => 'Balance due', 'blocked' => 'Blocked', 'unapproved' => 'To approve', 'approved' => 'Approved',
        'phone' => 'Verified phone', 'idv' => 'ID on file', 'repeat' => 'Repeat customers this year', 'shipped' => 'Shipped this year',
        'rated' => 'Rated support', 'month' => 'Joined in ' . ($ym ? date('F Y', strtotime($ym . '-01')) : 'a month'),
        'air' => 'Ship by air only', 'both' => 'Ship by air and sea', 'sea' => 'Ship by sea only',
    ];
}

/** WHERE fragment (alias u) for a list filter. Adds its binds to $binds. */
function sl_customersFilterSql(string $filter, array &$binds, ?string $ym = null): string
{
    $act = "SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND sender_id IS NOT NULL AND order_date >= CURDATE() - INTERVAL 90 DAY UNION SELECT sender_id FROM cdb_customers_packages WHERE sender_id IS NOT NULL AND order_date >= CURDATE() - INTERVAL 90 DAY";
    $air = 'SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND sender_id IS NOT NULL';
    $sea = 'SELECT sender_id FROM cdb_customers_packages WHERE sender_id IS NOT NULL';
    // parcels this year by air or sea, one row per parcel
    $thisYear = "SELECT sender_id FROM (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND sender_id IS NOT NULL AND order_date >= DATE_FORMAT(CURDATE(), '%Y-01-01') UNION ALL SELECT sender_id FROM cdb_customers_packages WHERE sender_id IS NOT NULL AND order_date >= DATE_FORMAT(CURDATE(), '%Y-01-01')) py GROUP BY sender_id";
    switch ($filter) {
        case 'active': return " AND u.id IN ($act)";
        case 'quiet': return " AND u.id NOT IN ($act)";
        case 'new': return " AND u.created >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        case 'due': return ' AND u.id IN (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND status_invoice = 23 AND status_courier <> 21 AND sender_id IS NOT NULL)';
        case 'blocked': return sl_tableExists('cdb_customer_blocks') ? ' AND u.id IN (SELECT user_id FROM cdb_customer_blocks WHERE unblocked_at IS NULL)' : ' AND u.active = 0';
        case 'unapproved': return sl_columnExists('cdb_users', 'approve') ? ' AND u.approve = 0' : '';
        case 'approved': return sl_columnExists('cdb_users', 'approve') ? ' AND u.approve = 1' : '';
        case 'phone': return sl_tableExists('cdb_profile_phone_verified') ? ' AND u.id IN (SELECT user_id FROM cdb_profile_phone_verified)' : ' AND 1 = 0';
        case 'idv':
            if (sl_columnExists('cdb_users', 'document_photo')) return " AND u.document_photo IS NOT NULL AND u.document_photo <> ''";
            return sl_columnExists('cdb_users', 'approve') ? ' AND u.approve = 1' : ' AND 1 = 0';
        case 'repeat': return " AND u.id IN ($thisYear HAVING COUNT(*) >= 2)";
        case 'shipped': return " AND u.id IN ($thisYear)";
        case 'rated': return sl_tableExists('cdb_cs_tickets') ? ' AND u.id IN (SELECT customer_id FROM cdb_cs_tickets WHERE csat_score IS NOT NULL AND customer_id IS NOT NULL)' : ' AND 1 = 0';
        case 'month':
            if (!$ym) return ' AND 1 = 0';
            $binds[':cx_m0'] = $ym . '-01 00:00:00';
            $binds[':cx_m1'] = date('Y-m-01 00:00:00', strtotime($ym . '-01 +1 month'));
            return ' AND u.created >= :cx_m0 AND u.created < :cx_m1';
        case 'air': return " AND u.id IN ($air) AND u.id NOT IN ($sea)";
        case 'both': return " AND u.id IN ($air) AND u.id IN ($sea)";
        case 'sea': return " AND u.id IN ($sea) AND u.id NOT IN ($air)";
    }
    return '';
}

/** Number of customers (in scope) matching a filter. */
function sl_customersCount(string $filter, ?string $ym = null): int
{
    [$scope, $binds] = sl_customersScope();
    $sql = "SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel = 1 $scope" . sl_customersFilterSql($filter, $binds, $ym);
    return (int) sl_scalar($sql, sl_cxBinds($sql, $binds));
}

/** $listOnly: just the list card (customers_list.php?partial=list), no statistics. */
function sl_customersData($user, $core, bool $listOnly = false): array
{
    // The CSV export (views/sl/export/customers_csv.php) only writes the rows: skip the statistics.
    if (($_GET['export'] ?? '') === 'csv') $listOnly = true;
    $d = ['listOnly' => $listOnly];
    $hasApprove = sl_columnExists('cdb_users', 'approve');
    $hasSource = sl_columnExists('cdb_users', 'signup_source');
    [$scope, $sb] = sl_customersScope();
    $base = "FROM cdb_users u WHERE u.userlevel = 1 $scope";
    // orders joined to the customers in scope
    $airJoin = "FROM cdb_add_order o JOIN cdb_users u ON u.id = o.sender_id AND u.userlevel = 1 $scope WHERE o.is_deleted = 0";
    $q1 = fn(string $sql) => sl_scalar($sql, sl_cxBinds($sql, $sb));
    $finance = cdp_financeEnabled();
    $cityCol = sl_columnExists('cdb_users', 'city') ? 'u.city' : "''";

    if (!$listOnly) {
        $total = (int) $q1("SELECT COUNT(*) $base");
        $newM = (int) $q1("SELECT COUNT(*) $base AND u.created >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
        $prevM = (int) $q1("SELECT COUNT(*) $base AND u.created >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') AND u.created < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
        $active = sl_customersCount('active');
        $phoneV = sl_customersCount('phone');
        $idV = sl_customersCount('idv');
        $repeat = sl_customersCount('repeat');
        $shippers = sl_customersCount('shipped');
        $parcelsYear = (int) $q1("SELECT COUNT(*) FROM (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= DATE_FORMAT(CURDATE(), '%Y-01-01') UNION ALL SELECT sender_id FROM cdb_customers_packages WHERE order_date >= DATE_FORMAT(CURDATE(), '%Y-01-01')) x JOIN cdb_users u ON u.id = x.sender_id AND u.userlevel = 1 $scope");
        $dueSql = "SELECT COUNT(DISTINCT o.sender_id) AS n, COALESCE(SUM(o.total_order),0) AS amt $airJoin AND o.status_invoice = 23 AND o.status_courier <> 21";
        $due = $finance ? sl_row($dueSql, sl_cxBinds($dueSql, $sb)) : null;
        $inactive = $total - $active;
        $blocked = sl_customersCount('blocked');
        $csat = null;
        if (sl_tableExists('cdb_cs_tickets')) {
            $csatSql = $scope === ''
                ? 'SELECT AVG(t.csat_score) AS avg, COUNT(t.csat_score) AS n FROM cdb_cs_tickets t WHERE t.csat_score IS NOT NULL'
                : "SELECT AVG(t.csat_score) AS avg, COUNT(t.csat_score) AS n FROM cdb_cs_tickets t JOIN cdb_users u ON u.id = t.customer_id AND u.userlevel = 1 $scope WHERE t.csat_score IS NOT NULL";
            $csat = sl_row($csatSql, sl_cxBinds($csatSql, $sb));
        }
        $change = $prevM ? (int) round(($newM - $prevM) / $prevM * 100) : null;
        $lastMonth = date('M', strtotime('first day of last month'));

        // Every tile opens its statistic in the insight panel (dataJs/sl/customers_stats.js); the href
        // is the no-script fallback: the filtered table, scrolled into view.
        $tile = fn(string $key, string $filter, string $label, string $value, string $sub) => ['key' => $key, 'filter' => $filter, 'href' => sl_url(['filter' => $filter === 'all' ? null : $filter, 'page' => null, 'ym' => null, 'q' => null, 'city' => null]) . '#cx-list', 'label' => $label, 'value' => $value, 'sub' => $sub];
        $d['kpi'] = [
            $tile('total', 'all', 'Total customers', sl_num($total), '▲ ' . $newM . ' this month'),
            $tile('active', 'active', 'Active (90 days)', sl_num($active), sl_pct($active, $total) . '% of all'),
            $tile('new', 'new', 'New this month', sl_num($newM), $change === null ? 'none in ' . $lastMonth : ($change > 0 ? '▲ ' : ($change < 0 ? '▼ ' : '')) . abs($change) . '% vs ' . $lastMonth),
            $tile('phone', 'phone', 'Verified phone', sl_num($phoneV), sl_pct($phoneV, $total) . '% · WhatsApp OTP'),
            $tile('idv', 'idv', 'ID verified', sl_num($idV), sl_pct($idV, $total) . '% · ID on file'),
            $tile('repeat', 'repeat', 'Repeat customers', sl_pct($repeat, $shippers) . '%', 'shipped 2+ times'),
            $tile('parcels', 'shipped', 'Avg parcels each', $shippers ? number_format($parcelsYear / $shippers, 1) : '0', 'per customer this year'),
        ];
        if ($finance && $due) {
            $d['kpi'][] = $tile('due', 'due', 'With balance due', sl_num($due->n), sl_money($due->amt) . ' owed');
        }
        $d['kpi'][] = $tile('quiet', 'quiet', 'Inactive 90 days', sl_num($inactive), 'win-back list ready');
        if ($csat && $csat->n) {
            $d['kpi'][] = $tile('csat', 'rated', 'Satisfaction', number_format((float) $csat->avg, 1) . ' ★', 'from ' . sl_num($csat->n) . ' ratings');
        }

        // growth: new per month (12 months) + customers active (air or sea parcel) per month
        $months = [];
        $activeSeries = [];
        $maxNew = 1;
        $from = date('Y-m-01', strtotime('first day of -11 months'));
        $sqlNew = "SELECT DATE_FORMAT(u.created, '%Y-%m') AS ym, COUNT(*) AS n $base AND u.created >= :cx_from GROUP BY ym";
        $mapNew = [];
        foreach (sl_rows($sqlNew, sl_cxBinds($sqlNew, $sb + [':cx_from' => $from])) as $r) $mapNew[$r->ym] = (int) $r->n;
        $sqlAct = "SELECT DATE_FORMAT(x.order_date, '%Y-%m') AS ym, COUNT(DISTINCT x.sender_id) AS n FROM (SELECT sender_id, order_date FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= :cx_from UNION ALL SELECT sender_id, order_date FROM cdb_customers_packages WHERE order_date >= :cx_from) x JOIN cdb_users u ON u.id = x.sender_id AND u.userlevel = 1 $scope GROUP BY ym";
        $mapAct = [];
        foreach (sl_rows($sqlAct, sl_cxBinds($sqlAct, $sb + [':cx_from' => $from])) as $r) $mapAct[$r->ym] = (int) $r->n;
        for ($i = 11; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("first day of -$i months"));
            $maxNew = max($maxNew, $mapNew[$ym] ?? 0);
        }
        for ($i = 11; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("first day of -$i months"));
            $n = $mapNew[$ym] ?? 0;
            $a = $mapAct[$ym] ?? 0;
            $full = date('F Y', strtotime($ym . '-01'));
            $months[] = ['ym' => $ym, 'l' => date('M', strtotime($ym . '-01')), 'full' => $full, 'v' => $n, 'a' => $a, 'h' => 'height:' . max(2, sl_pct($n, $maxNew)) . '%', 'tip' => "$full: $n new, $a active", 'now' => $i === 0 ? 'now' : ''];
            $activeSeries[] = $a;
        }
        $d['months'] = $months;
        $d['activeSeries'] = $activeSeries;
        $d['line'] = sl_svgPoints($activeSeries, 1200, 160, 16, max(1, max($activeSeries)), 50);
        $pts = explode(' ', $d['line']);
        [$d['dotX'], $d['dotY']] = explode(',', end($pts) ?: '1150,160');
        $d['activeNow'] = sl_num(sl_tableExists('cdb_staff_presence') ? 0 : (int) $q1("SELECT COUNT(*) $base AND u.lastlogin >= NOW() - INTERVAL 15 MINUTE"));
        $d['today'] = (int) $q1("SELECT COUNT(*) $base AND DATE(u.created) = CURDATE()");
        $d['clock'] = date('H:i');
        $feed = [];
        $feedSql = "SELECT u.fname, u.lname, u.created, $cityCol AS city " . ($hasSource ? ', u.signup_source' : ", '' AS signup_source") . " $base ORDER BY u.id DESC LIMIT 5";
        foreach (sl_rows($feedSql, sl_cxBinds($feedSql, $sb)) as $r) {
            $feed[] = ['ini' => sl_initials($r->fname . ' ' . $r->lname), 'name' => trim($r->fname . ' ' . $r->lname), 'what' => 'signed up', 'city' => $r->city ?: '—', 'src' => $r->signup_source ?: 'Website', 'at' => sl_ago($r->created), 'anim' => ''];
        }
        $d['feed'] = $feed;

        // ship by (customers in scope)
        $air = sl_customersCount('air');
        $sea = sl_customersCount('sea');
        $both = sl_customersCount('both');
        $tot = max(1, $air + $sea + $both);
        $d['shipBy'] = ['air' => sl_pct($air, $tot), 'both' => sl_pct($both, $tot), 'sea' => sl_pct($sea, $tot)];
        $d['shipByN'] = ['air' => $air, 'both' => $both, 'sea' => $sea];
        $d['status'] = ['active' => $active, 'quiet' => $inactive, 'new' => $newM, 'blocked' => $blocked];
        $tenure = (float) $q1("SELECT AVG(DATEDIFF(CURDATE(), u.created)) $base");
        $d['tenure'] = $tenure >= 365 ? floor($tenure / 365) . ' yr ' . floor(($tenure % 365) / 30) . ' mo' : floor($tenure / 30) . ' mo';
        $gap = (float) $q1("SELECT AVG(g) FROM (SELECT DATEDIFF(MAX(x.order_date), MIN(x.order_date)) / GREATEST(COUNT(*) - 1, 1) AS g FROM (SELECT sender_id, order_date FROM cdb_add_order WHERE is_deleted = 0 UNION ALL SELECT sender_id, order_date FROM cdb_customers_packages) x JOIN cdb_users u ON u.id = x.sender_id AND u.userlevel = 1 $scope GROUP BY x.sender_id HAVING COUNT(*) > 1) g");
        $d['shipEvery'] = $gap > 0 ? round($gap) . ' days' : '—';
        $d['total'] = $total;
        $d['newM'] = $newM;
    }

    // cities (for the city filter)
    $citySql = "SELECT COALESCE(NULLIF($cityCol,''), s.city_name, 'Unknown') AS city, COUNT(*) AS n FROM cdb_users u LEFT JOIN (SELECT sa.user_id, c.name AS city_name FROM cdb_senders_addresses sa LEFT JOIN cdb_cities c ON c.id = sa.city GROUP BY sa.user_id) s ON s.user_id = u.id WHERE u.userlevel = 1 $scope GROUP BY city ORDER BY n DESC LIMIT 6";
    $cities = sl_rows($citySql, sl_cxBinds($citySql, $sb));
    $cmax = $cities ? max(1, (int) $cities[0]->n) : 1;
    $d['cities'] = array_map(fn($r) => ['label' => $r->city, 'n' => (int) $r->n, 'w' => sl_pct($r->n, $cmax)], $cities);

    // list
    $q = trim((string) ($_GET['q'] ?? ''));
    $labels = sl_customersFilterLabels();
    $filter = (string) ($_GET['filter'] ?? 'all');
    if (!isset($labels[$filter])) $filter = 'all';
    $ym = $filter === 'month' ? sl_customersMonth($_GET['ym'] ?? '') : null;
    if ($filter === 'month' && !$ym) $filter = 'all';
    $city = trim((string) ($_GET['city'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $where = "u.userlevel = 1 $scope";
    $binds = $sb;
    if ($q !== '') {
        $where .= " AND (u.fname LIKE :q OR u.lname LIKE :q OR CONCAT(u.fname,' ',u.lname) LIKE :q OR u.email LIKE :q OR u.phone LIKE :q OR u.locker LIKE :q OR u.username LIKE :q)";
        $binds[':q'] = '%' . $q . '%';
    }
    $where .= sl_customersFilterSql($filter, $binds, $ym);
    if ($city !== '') {
        $where .= " AND $cityCol = :city";
        $binds[':city'] = $city;
    }
    $countSql = "SELECT COUNT(*) FROM cdb_users u WHERE $where";
    $count = (int) sl_scalar($countSql, sl_cxBinds($countSql, $binds));
    $hasBlocks = sl_tableExists('cdb_customer_blocks');
    $blockJoin = $hasBlocks ? "LEFT JOIN cdb_customer_blocks b ON b.user_id = u.id AND b.unblocked_at IS NULL" : '';
    $blockCols = $hasBlocks ? 'b.reason AS block_reason, b.id AS block_id' : 'NULL AS block_reason, NULL AS block_id';
    $listSql = "SELECT u.id, u.fname, u.lname, u.email, u.locker, u.phone, u.active, u.created, $cityCol AS city, $blockCols,
            (SELECT COUNT(*) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.is_deleted = 0) AS air,
            (SELECT COUNT(*) FROM cdb_customers_packages p WHERE p.sender_id = u.id) AS sea,
            (SELECT COALESCE(SUM(o.total_order),0) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.is_deleted = 0 AND o.status_invoice = 23 AND o.status_courier <> 21) AS bal,
            NULLIF(GREATEST(COALESCE((SELECT MAX(o.order_date) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.is_deleted = 0), '1000-01-01'),
                            COALESCE((SELECT MAX(p.order_date) FROM cdb_customers_packages p WHERE p.sender_id = u.id), '1000-01-01')), '1000-01-01') AS last_order
            " . ($hasApprove ? ', u.approve' : ', 1 AS approve') . "
        FROM cdb_users u $blockJoin WHERE $where ORDER BY u.id DESC LIMIT " . (($page - 1) * $per) . ", $per";
    $rows = sl_rows($listSql, sl_cxBinds($listSql, $binds));
    $list = [];
    foreach ($rows as $r) {
        $isBlocked = $r->block_id || (int) $r->active === 0;
        $bal = (float) $r->bal;
        if ($isBlocked) { $st = ['Blocked', 'overdue']; $why = $r->block_reason ?: 'Account inactive'; }
        elseif (!(int) $r->approve) { $st = ['Awaiting approval', 'pending']; $why = 'KYC to review'; }
        elseif ($finance && $bal > 0) { $st = ['Balance due', 'onway']; $why = 'Unpaid invoices'; }
        elseif ($r->last_order && strtotime($r->last_order) >= strtotime('-90 days')) { $st = ['Active', 'delivered']; $why = 'Last parcel ' . sl_ago($r->last_order); }
        else { $st = ['Quiet', 'plain']; $why = $r->last_order ? 'Last parcel ' . sl_date($r->last_order) : 'No parcels yet'; }
        $list[] = ['id' => (int) $r->id, 'name' => trim($r->fname . ' ' . $r->lname), 'ini' => sl_initials($r->fname . ' ' . $r->lname), 'mail' => $r->email, 'locker' => $r->locker ?: '—', 'phone' => $r->phone ?: '—', 'city' => $r->city ?: '—', 'n' => (int) $r->air + (int) $r->sea, 'nTip' => (int) $r->air . ' by air · ' . (int) $r->sea . ' by sea', 'bal' => $finance ? sl_money($bal) : '—', 'st' => $st[0], 'cls' => $st[1], 'why' => $why, 'blocked' => $isBlocked, 'approve' => (int) $r->approve];
    }
    $d['rows'] = $list;
    $d['count'] = $count;
    $d['page'] = $page;
    $d['per'] = $per;
    $d['q'] = $q;
    $d['filter'] = $filter;
    $d['ym'] = $ym;
    $d['filterLabel'] = sl_customersFilterLabels($ym)[$filter];
    $d['city'] = $city;
    $d['filters'] = [['f' => 'all', 'label' => 'All'], ['f' => 'active', 'label' => 'Active'], ['f' => 'due', 'label' => 'Balance due'], ['f' => 'unapproved', 'label' => 'To approve'], ['f' => 'blocked', 'label' => 'Blocked']];
    $d['cityOptions'] = array_map(fn($c) => $c['label'], array_filter($d['cities'], fn($c) => $c['label'] !== 'Unknown'));
    $d['hasApprove'] = $hasApprove;
    $d['canEdit'] = $user->cdp_hasPermission('edit_client');
    $d['canDelete'] = $user->cdp_hasPermission('delete_client');
    $d['canApprove'] = $hasApprove && $user->cdp_hasPermission(['approve_client', 'edit_client']);
    return $d;
}
