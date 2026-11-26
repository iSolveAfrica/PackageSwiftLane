<?php
/** Data for Pickups.dc.html and SchedulePickup.dc.html (cdb_add_order with is_pickup = 1). */
require_once __DIR__ . '/ops_common.php';

const SL_PICKUP_WINDOWS = ['08:00–10:00', '10:00–12:00', '12:00–14:00', '14:00–16:00', '16:00–18:00'];

/**
 * Home pickup stages. A pickup is a cdb_add_order row with is_pickup = 1: we collect from the customer's
 * home in the US and bring it to the US warehouse, where it joins the normal shipment flow.
 */
function sl_pkStages(): array
{
    return [
        'requested' => ['label' => 'Requested', 'sub' => 'waiting to be accepted', 'sql' => 'a.status_courier = 14', 'icon' => 'i-inbox', 'cls' => 'pending'],
        'scheduled' => ['label' => 'Scheduled', 'sub' => 'accepted, rider to collect', 'sql' => 'a.status_courier = 1', 'icon' => 'i-calendar', 'cls' => 'sorting'],
        'collected' => ['label' => 'Collected', 'sub' => 'on the way to the warehouse', 'sql' => 'a.status_courier = 15', 'icon' => 'i-truck', 'cls' => 'onway'],
        'warehouse' => ['label' => 'At US warehouse', 'sub' => 'received, ready to ship', 'sql' => 'a.status_courier IN (2,4,10,13)', 'icon' => 'i-warehouse', 'cls' => 'transit'],
        'shipped' => ['label' => 'Shipped onward', 'sub' => 'on its way to Ghana or delivered', 'sql' => 'a.status_courier NOT IN (1,2,4,10,13,14,15,12,16,21)', 'icon' => 'i-plane', 'cls' => 'delivered'],
        'issues' => ['label' => 'Not collected', 'sub' => 'refused, failed or cancelled', 'sql' => 'a.status_courier IN (12,16,21)', 'icon' => 'i-alert', 'cls' => 'overdue'],
    ];
}

function sl_pkStageOf(int $s): string
{
    if ($s === 14) return 'requested';
    if ($s === 1) return 'scheduled';
    if ($s === 15) return 'collected';
    if (in_array($s, [2, 4, 10, 13], true)) return 'warehouse';
    if (in_array($s, [12, 16, 21], true)) return 'issues';
    return 'shipped';
}

function sl_pickupsData($user, $core): array
{
    require_once __DIR__ . '/overview_hero.php';
    $d = [];
    $scope = sl_opsScope('a');
    $base = "FROM cdb_add_order a WHERE a.is_pickup = 1 AND a.is_deleted = 0 $scope";
    $stages = sl_pkStages();
    $isClient = sl_userIs('client');
    $money = !$isClient && function_exists('cdp_canViewMoney') && cdp_canViewMoney($user);

    // ── pipeline counts (open stages all-time, closing stages this month) ───
    $pipe = [];
    foreach ($stages as $k => $st) {
        $recent = in_array($k, ['shipped', 'issues', 'warehouse'], true) ? " AND a.order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')" : '';
        $pipe[] = ['k' => $k, 'n' => (int) sl_scalar("SELECT COUNT(*) $base AND {$st['sql']}$recent"), 'label' => $st['label'], 'sub' => $recent ? $st['sub'] . ' · this month' : $st['sub'], 'icon' => $st['icon']];
    }
    $d['pipe'] = $pipe;

    // ── time between steps, from tracking events (last 30 days) ────────────
    $trk = "SELECT a.order_id, MIN(CASE WHEN t.status_courier = 14 THEN t.t_date END) AS req, MIN(CASE WHEN t.status_courier = 15 THEN t.t_date END) AS col,
            MIN(CASE WHEN t.status_courier IN (2,4) THEN t.t_date END) AS rec
        FROM cdb_add_order a JOIN cdb_courier_track t ON t.order_track = CONCAT(a.order_prefix, a.order_no)
        WHERE a.is_pickup = 1 AND a.is_deleted = 0 AND a.order_date >= CURDATE() - INTERVAL 29 DAY $scope GROUP BY a.order_id";
    $toCol = []; $toWh = [];
    foreach (sl_rows($trk) as $r) {
        $req = $r->req ?: null;
        if ($req && $r->col && strtotime($r->col) >= strtotime($req)) $toCol[] = (strtotime($r->col) - strtotime($req)) / 3600;
        if ($r->col && $r->rec && strtotime($r->rec) >= strtotime($r->col)) $toWh[] = (strtotime($r->rec) - strtotime($r->col)) / 3600;
    }
    $fmtH = fn($h) => $h === null ? '—' : ($h >= 48 ? round($h / 24, 1) . ' d' : ($h >= 1 ? round($h, 1) . ' h' : round($h * 60) . ' min'));
    $avg = fn(array $a) => $a ? array_sum($a) / count($a) : null;

    $dueToday = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier IN (1,14) AND DATE(a.order_datetime) = CURDATE()");
    $colToday = (int) sl_scalar("SELECT COUNT(DISTINCT a.order_id) FROM cdb_add_order a JOIN cdb_courier_track t ON t.order_track = CONCAT(a.order_prefix, a.order_no) WHERE a.is_pickup = 1 AND a.is_deleted = 0 AND t.status_courier = 15 AND t.t_date >= CURDATE() $scope");
    $riders = (int) sl_scalar("SELECT COUNT(DISTINCT a.driver_id) $base AND a.status_courier IN (1,14) AND a.driver_id > 0 AND DATE(a.order_datetime) = CURDATE()");
    $noRider = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier IN (1,14) AND (a.driver_id IS NULL OR a.driver_id = 0)");
    $waiting24 = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier IN (1,14) AND a.order_datetime < NOW() - INTERVAL 24 HOUR");
    $withRider = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier = 15");
    $withRiderOld = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_pickup = 1 AND a.is_deleted = 0 AND a.status_courier = 15 $scope AND NOT EXISTS (SELECT 1 FROM cdb_courier_track t WHERE t.order_track = CONCAT(a.order_prefix, a.order_no) AND t.status_courier = 15 AND t.t_date >= NOW() - INTERVAL 24 HOUR)");
    $month = (int) sl_scalar("SELECT COUNT(*) $base AND a.order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $monthDone = (int) sl_scalar("SELECT COUNT(*) $base AND a.order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND a.status_courier NOT IN (1,14,15,12,16,21)");
    $monthFail = (int) sl_scalar("SELECT COUNT(*) $base AND a.order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND a.status_courier IN (12,16,21)");
    $fees = $money ? (float) sl_scalar("SELECT COALESCE(SUM(a.total_order),0) $base AND a.order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND a.status_courier NOT IN (12,16,21)") : 0;

    $lab = fn($x) => date('D j M', strtotime($x));
    $daily = function (string $sql) use ($lab) { $m = sl_ovDaily($sql, []); return sl_sparkBars(array_values($m), 4, array_map($lab, array_keys($m))); };
    $d['cards'] = [
        ['tone' => 'blue', 'icon' => 'i-route', 'eyebrow' => 'Today\'s collections', 'big' => sl_num($colToday) . ' / ' . sl_num($colToday + $dueToday), 'lede' => sl_num($dueToday) . ' still to collect today · ' . sl_num($riders) . ' rider' . ($riders === 1 ? '' : 's') . ' out',
            'bars' => $daily("SELECT DATE(t.t_date) AS day, COUNT(DISTINCT a.order_id) AS n FROM cdb_add_order a JOIN cdb_courier_track t ON t.order_track = CONCAT(a.order_prefix, a.order_no) WHERE a.is_pickup = 1 AND a.is_deleted = 0 AND t.status_courier = 15 AND t.t_date >= :d0 $scope GROUP BY DATE(t.t_date)"),
            'foot' => 'Collected per day, 14 days', 'alert' => $noRider && !$isClient ? $noRider . ' without a rider' : '', 'f' => 'scheduled'],
        ['tone' => 'amber', 'icon' => 'i-clock', 'eyebrow' => 'Time to collect', 'big' => $fmtH($avg($toCol)), 'lede' => 'average from request to collection · last 30 days' . ($toCol ? ' · ' . count($toCol) . ' pickups' : ''),
            'bars' => $daily("SELECT DATE(a.order_datetime) AS day, COUNT(*) AS n $base AND a.order_datetime >= :d0 GROUP BY DATE(a.order_datetime)"),
            'foot' => 'Requests per day, 14 days', 'alert' => $waiting24 ? $waiting24 . ' waiting over 24 h' : '', 'f' => 'requested'],
        ['tone' => 'mint', 'icon' => 'i-warehouse', 'eyebrow' => 'Into the warehouse', 'big' => $fmtH($avg($toWh)), 'lede' => 'average from collection to the US warehouse · ' . sl_num($withRider) . ' with riders now',
            'bars' => $daily("SELECT DATE(t.t_date) AS day, COUNT(DISTINCT a.order_id) AS n FROM cdb_add_order a JOIN cdb_courier_track t ON t.order_track = CONCAT(a.order_prefix, a.order_no) WHERE a.is_pickup = 1 AND a.is_deleted = 0 AND t.status_courier IN (2,4) AND t.t_date >= :d0 $scope GROUP BY DATE(t.t_date)"),
            'foot' => 'Received at the warehouse per day, 14 days', 'alert' => $withRiderOld ? $withRiderOld . ' collected over 24 h ago' : '', 'f' => 'collected'],
        ['tone' => 'rose', 'icon' => 'i-chart', 'eyebrow' => 'This month', 'big' => sl_num($month) . ' pickups', 'lede' => sl_pct($monthDone, max(1, $month)) . '% reached the warehouse · ' . sl_num($monthFail) . ' not collected' . ($money ? ' · ' . sl_money($fees) . ' in fees' : ''),
            'meter' => sl_pct($monthDone, max(1, $month)), 'bars' => [], 'foot' => 'Share of this month\'s pickups now at or past the warehouse', 'alert' => '', 'f' => 'warehouse'],
    ];

    // ── today's board: windows and riders ─────────────────────────────────
    $cap = (int) sl_slSetting('pickup_slot_capacity', 5);
    $slots = [];
    $byHour = [];
    foreach (sl_rows("SELECT HOUR(a.order_datetime) AS h, COUNT(*) AS n $base AND DATE(a.order_datetime) = CURDATE() AND a.status_courier NOT IN (12,16,21) GROUP BY h") as $r) $byHour[(int) $r->h] = (int) $r->n;
    foreach (SL_PICKUP_WINDOWS as $i => $w) {
        $h0 = 8 + $i * 2; $n = ($byHour[$h0] ?? 0) + ($byHour[$h0 + 1] ?? 0);
        $slots[] = ['w' => $w, 'n' => $n, 'cap' => $cap, 'txt' => $n . ' / ' . $cap . ($n >= $cap ? ' · full' : ''), 'pct' => sl_pct($n, $cap), 'full' => $n >= $cap];
    }
    $d['slots'] = $slots;
    $booked = array_sum(array_column($slots, 'n'));
    $d['lede'] = $isClient
        ? 'We collect parcels from your home in the US and take them to our US warehouse for shipping'
        : 'Collect parcels from customers\' homes in the US and bring them to the US warehouse · ' . date('l, M j') . ' · ' . $booked . ' of ' . ($cap * count($slots)) . ' slots booked';
    $drv = sl_rows("SELECT a.driver_id, COUNT(*) AS n, SUM(a.status_courier NOT IN (1,14)) AS done FROM cdb_add_order a WHERE a.is_pickup = 1 AND a.is_deleted = 0 AND a.driver_id > 0 AND DATE(a.order_datetime) = CURDATE() $scope GROUP BY a.driver_id ORDER BY n DESC LIMIT 6");
    $du = sl_usersById(array_map(fn($r) => $r->driver_id, $drv));
    $d['drivers'] = array_map(fn($r) => ['name' => sl_userName($du[(int) $r->driver_id] ?? null), 'txt' => (int) $r->done . ' of ' . (int) $r->n . ' collected', 'pct' => sl_pct($r->done, max(1, $r->n))], $drv);

    // ── list ──────────────────────────────────────────────────────────────
    $q = trim((string) ($_GET['q'] ?? ''));
    $when = (string) ($_GET['when'] ?? 'all');
    // a search with no stage chosen looks across every stage (e.g. links from the Dispatch Board)
    $filter = (string) ($_GET['filter'] ?? ($q !== '' ? 'all' : 'active'));
    $driver = (int) ($_GET['driver'] ?? 0);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $where = "a.is_pickup = 1 AND a.is_deleted = 0 $scope";
    $binds = [];
    if ($q !== '') {
        $where .= " AND (CONCAT(a.order_prefix, a.order_no) LIKE :q OR a.sender_id IN (SELECT id FROM cdb_users WHERE CONCAT(fname,' ',lname) LIKE :q OR locker LIKE :q OR phone LIKE :q) OR CONCAT(a.order_prefix, a.order_no) IN (SELECT order_track FROM cdb_address_shipments WHERE sender_address LIKE :q OR sender_city LIKE :q))";
        $binds[':q'] = '%' . $q . '%';
    }
    if ($when === 'today') $where .= ' AND DATE(a.order_datetime) = CURDATE()';
    if ($when === 'tomorrow') $where .= ' AND DATE(a.order_datetime) = CURDATE() + INTERVAL 1 DAY';
    if ($when === 'week') $where .= ' AND YEARWEEK(a.order_datetime, 1) = YEARWEEK(CURDATE(), 1)';
    // old links (?filter=accepted|picked|refused) map onto the new stages
    $filter = ['accepted' => 'scheduled', 'picked' => 'collected', 'refused' => 'issues'][$filter] ?? $filter;
    if ($filter === 'active') $where .= ' AND a.status_courier IN (1,14,15)';
    elseif (isset($stages[$filter])) $where .= ' AND ' . $stages[$filter]['sql'];
    else $filter = 'all';
    if ($driver > 0) $where .= " AND a.driver_id = $driver";
    $count = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE $where", $binds);
    $rows = sl_rows("SELECT a.order_id, a.order_prefix, a.order_no, a.order_datetime, a.order_date, a.sender_id, a.driver_id, a.status_courier, a.total_order, a.total_weight, a.notes, a.agency, s.sender_address, s.sender_city,
            (SELECT COALESCE(SUM(i.order_item_quantity),0) FROM cdb_add_order_item i WHERE i.order_id = a.order_id) AS items
        FROM cdb_add_order a LEFT JOIN cdb_address_shipments s ON s.order_track = CONCAT(a.order_prefix, a.order_no) WHERE $where
        ORDER BY FIELD(a.status_courier, 14, 1, 15) = 0, a.order_datetime ASC, a.order_id DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds);
    $users = sl_usersById(array_merge(array_map(fn($r) => $r->sender_id, $rows), array_map(fn($r) => $r->driver_id, $rows)));
    require_once CDP_ROOT . '/helpers/pickup_code.php';
    $can = [
        'accept' => $user->cdp_hasPermission('pickup_accept') && !$isClient, 'cancel' => $user->cdp_hasPermission('cancel_pickup'),
        'delete' => $user->cdp_hasPermission('delete_pickup') && !$isClient, 'driver' => $user->cdp_hasPermission('assign_driver_pickup') && !$isClient,
        'label' => $user->cdp_hasPermission(['print_pickup_label', 'print_label']), 'print' => $user->cdp_hasPermission('print_shipment'),
        'code' => cdp_pickupCodeCanUse($user), 'refuse' => $user->cdp_hasPermission('pickup_accept') && !$isClient,
        'collect' => $user->cdp_hasPermission(['complete_pickup', 'dispatch_riders']) && !$isClient,
        'receive' => $user->cdp_hasPermission(['warehouse_receive', 'add_shipment']) && !$isClient,
    ];
    $order = array_keys(array_slice($stages, 0, 5));
    $list = [];
    foreach ($rows as $r) {
        $s = (int) $r->status_courier; $u = $users[(int) $r->sender_id] ?? null;
        $stage = sl_pkStageOf($s);
        $win = preg_match('/Window (\S+)/u', (string) $r->notes, $m) ? $m[1] : sl_date($r->order_datetime, 'H:i');
        $due = $r->order_datetime ? strtotime($r->order_datetime) : 0;
        $list[] = [
            'id' => (int) $r->order_id, 'trk' => $r->order_prefix . $r->order_no, 'name' => sl_userName($u), 'ini' => sl_initials(sl_userName($u)), 'phone' => $u->phone ?? '', 'locker' => $u->locker ?? '',
            'addr' => $r->sender_address ?: '—', 'area' => $r->sender_city ?: '', 'win' => $win,
            'day' => $due ? (date('Y-m-d', $due) === date('Y-m-d') ? 'Today' : (date('Y-m-d', $due) === date('Y-m-d', strtotime('+1 day')) ? 'Tomorrow' : date('D M j', $due))) : '',
            'late' => in_array($stage, ['requested', 'scheduled'], true) && $due && $due < strtotime('today'),
            'items' => (int) $r->items, 'kg' => (float) $r->total_weight > 0 ? number_format((float) $r->total_weight, 1) . ' ' . ($core->weight_p ?? 'lbs') : '—', 'fee' => sl_money($r->total_order),
            'drv' => $r->driver_id ? sl_userName($users[(int) $r->driver_id] ?? null) : '', 'stage' => $stage, 'st' => $stages[$stage]['label'], 'cls' => $stages[$stage]['cls'],
            'step' => $stage === 'issues' ? -1 : array_search($stage, $order, true),
            'open' => in_array($stage, ['requested', 'scheduled', 'collected'], true), 'requested' => $stage === 'requested',
        ];
    }
    $d += ['rows' => $list, 'count' => $count, 'page' => $page, 'per' => $per, 'q' => $q, 'when' => $when, 'filter' => $filter, 'driver' => $driver, 'can' => $can, 'isClient' => $isClient, 'money' => $money];
    $d['stages'] = $stages;
    $d['tabs'] = array_merge([['f' => 'active', 'label' => 'In progress']], array_map(fn($k) => ['f' => $k, 'label' => $stages[$k]['label']], array_keys($stages)), [['f' => 'all', 'label' => 'All']]);
    $d['whens'] = [['f' => 'all', 'label' => 'Any day'], ['f' => 'today', 'label' => 'Today'], ['f' => 'tomorrow', 'label' => 'Tomorrow'], ['f' => 'week', 'label' => 'This week']];
    $d['driverList'] = sl_rows('SELECT id, fname, lname FROM cdb_users WHERE userlevel = 3 AND active = 1 ORDER BY fname');
    $d['canAdd'] = $user->cdp_hasPermission($isClient ? 'pickup_add' : 'add_full_pickup');
    return $d;
}

function sl_schedulePickupData($user, $core): array
{
    $isClient = sl_userIs('client');
    $d = [
        'isClient' => $isClient, 'windows' => SL_PICKUP_WINDOWS, 'customers' => $isClient ? [] : sl_opsCustomers(),
        'drivers' => [], 'today' => date('Y-m-d', strtotime('+1 day')), 'me' => null,
    ];
    if ($isClient) {
        $u = sl_row('SELECT id, fname, lname, phone, locker FROM cdb_users WHERE id = :id', [':id' => (int) $_SESSION['userid']]);
        $d['me'] = $u; $d['addresses'] = sl_opsAddresses((int) $_SESSION['userid']);
    } else {
        foreach (sl_rows("SELECT u.id, u.fname, u.lname, (SELECT COUNT(*) FROM cdb_add_order a WHERE a.driver_id = u.id AND a.is_pickup = 1 AND a.is_deleted = 0 AND DATE(a.order_datetime) = CURDATE()) AS stops FROM cdb_users u WHERE u.userlevel = 3 AND u.active = 1 ORDER BY u.fname") as $r) {
            $d['drivers'][] = ['id' => (int) $r->id, 'label' => trim($r->fname . ' ' . $r->lname) . ' · ' . (int) $r->stops . ' stops today'];
        }
        $d['addresses'] = [];
    }
    $d['lookups'] = sl_opsLookups();
    $def = cdp_getInfoShipDefault();
    $d['def'] = ['packaging' => (int) ($def->packaging_default2 ?? 0), 'courier' => (int) ($def->courier_default3 ?? 0), 'delitime' => (int) ($def->time_default5 ?? 0), 'category' => (int) ($def->logistics_default1 ?? 0)] + cdp_getDefaultAgencyOrigin();
    return $d;
}
