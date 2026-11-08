<?php
/** Data for Pickups.dc.html and SchedulePickup.dc.html (cdb_add_order with is_pickup = 1). */
require_once __DIR__ . '/ops_common.php';

const SL_PICKUP_WINDOWS = ['08:00–10:00', '10:00–12:00', '12:00–14:00', '14:00–16:00', '16:00–18:00'];

function sl_pickupsData($user, $core): array
{
    $d = [];
    $scope = sl_opsScope('a');
    $base = "FROM cdb_add_order a WHERE a.is_pickup = 1 AND a.is_deleted = 0 $scope";
    $req = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier = 14");
    $reqHour = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier = 14 AND a.order_datetime >= NOW() - INTERVAL 1 HOUR");
    $acc = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier = 1");
    $accSoon = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier = 1 AND DATE(a.order_datetime) = CURDATE() AND HOUR(a.order_datetime) < 11");
    $picked = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier NOT IN (1,12,14,21) AND DATE(a.order_date) = CURDATE()");
    $pickedLw = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier NOT IN (1,12,14,21) AND DATE(a.order_date) = CURDATE() - INTERVAL 7 DAY");
    $refused = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier IN (12,21) AND DATE(a.order_date) = CURDATE()");
    $series = function (string $cond) use ($base) {
        $map = [];
        foreach (sl_rows("SELECT DATE(a.order_date) AS d, COUNT(*) AS n $base AND $cond AND a.order_date >= CURDATE() - INTERVAL 29 DAY GROUP BY d") as $r) $map[$r->d] = (int) $r->n;
        $out = [];
        for ($i = 29; $i >= 0; $i--) $out[] = $map[date('Y-m-d', strtotime("-$i days"))] ?? 0;
        return $out;
    };
    $spark = function (array $v) { $p = sl_svgLinePath($v, 240, 34, 2); return ['series' => $v, 'line' => $p, 'area' => $p . ' L240,46 L0,46 Z']; };
    $d['kpi'] = [
        ['t' => 'Requested', 'v' => $req, 'small' => 'awaiting driver', 'd' => $reqHour ? '▲ ' . $reqHour . ' in the last hour' : 'none in the last hour', 'dc' => $reqHour ? 'dv-bad' : 'dv-info', 'f' => 'requested'] + $spark($series('a.status_courier = 14')),
        ['t' => 'Accepted', 'v' => $acc, 'small' => 'assigned', 'd' => $accSoon . ' start before 11:00', 'dc' => 'dv-warn', 'f' => 'accepted'] + $spark($series('a.status_courier = 1')),
        ['t' => 'Picked up', 'v' => $picked, 'small' => 'today', 'd' => $pickedLw ? (($picked >= $pickedLw ? '▲ ' : '▼ ') . sl_pct(abs($picked - $pickedLw), $pickedLw) . '% vs last ' . date('l')) : 'first ' . date('l') . ' with pickups', 'dc' => 'dv-ok', 'f' => 'picked'] + $spark($series('a.status_courier NOT IN (1,12,14,21)')),
        ['t' => 'Refused / cancelled', 'v' => $refused, 'small' => 'today', 'd' => 'rejected or cancelled', 'dc' => 'dv-info', 'f' => 'refused'] + $spark($series('a.status_courier IN (12,21)')),
    ];
    // slots by window (today, from the requested time)
    $cap = (int) sl_slSetting('pickup_slot_capacity', 5);
    $slots = [];
    $byHour = [];
    foreach (sl_rows("SELECT HOUR(a.order_datetime) AS h, COUNT(*) AS n $base AND DATE(a.order_datetime) = CURDATE() AND a.status_courier NOT IN (12,21) GROUP BY h") as $r) $byHour[(int) $r->h] = (int) $r->n;
    foreach (SL_PICKUP_WINDOWS as $i => $w) {
        $h0 = 8 + $i * 2; $n = ($byHour[$h0] ?? 0) + ($byHour[$h0 + 1] ?? 0);
        $slots[] = ['w' => $w, 'n' => $n, 'cap' => $cap, 'txt' => $n . ' / ' . $cap . ($n >= $cap ? ' full' : ''), 'pct' => sl_pct($n, $cap)];
    }
    $d['slots'] = $slots;
    $booked = array_sum(array_column($slots, 'n'));
    $d['lede'] = date('l, M j') . ' · ' . $booked . ' of ' . ($cap * count($slots)) . ' slots booked';
    $drv = sl_rows("SELECT a.driver_id, COUNT(*) AS n, SUM(a.status_courier NOT IN (1,14)) AS done FROM cdb_add_order a WHERE a.is_pickup = 1 AND a.is_deleted = 0 AND a.driver_id > 0 AND DATE(a.order_datetime) = CURDATE() $scope GROUP BY a.driver_id ORDER BY n DESC LIMIT 6");
    $du = sl_usersById(array_map(fn($r) => $r->driver_id, $drv));
    $mx = max(1, ...array_map(fn($r) => (int) $r->n, $drv ?: [(object) ['n' => 1]]));
    $d['drivers'] = array_map(fn($r) => ['name' => sl_userName($du[(int) $r->driver_id] ?? null), 'txt' => (int) $r->done . ' of ' . (int) $r->n . ' done', 'pct' => sl_pct($r->n, $mx)], $drv);
    $areas = sl_rows("SELECT COALESCE(NULLIF(s.sender_city,''),'Other') AS city, COUNT(*) AS n, AVG(TIMESTAMPDIFF(MINUTE, a.order_datetime, NOW())) AS wait FROM cdb_add_order a LEFT JOIN cdb_address_shipments s ON s.order_track = CONCAT(a.order_prefix, a.order_no) WHERE a.is_pickup = 1 AND a.is_deleted = 0 AND a.order_date >= CURDATE() - INTERVAL 6 DAY $scope GROUP BY city ORDER BY n DESC LIMIT 6");
    $d['areas'] = array_map(fn($r) => ['city' => $r->city, 'n' => (int) $r->n, 'wait' => $r->wait > 1440 ? round($r->wait / 1440) . ' d' : ($r->wait > 60 ? round($r->wait / 60) . ' h' : max(0, (int) $r->wait) . ' min')], $areas);
    $week = [];
    $wm = [];
    foreach (sl_rows("SELECT DATE(a.order_date) AS d, COUNT(*) AS n $base AND a.order_date >= CURDATE() - INTERVAL 6 DAY GROUP BY d") as $r) $wm[$r->d] = (int) $r->n;
    $wmax = max(1, ...array_values($wm ?: [1]));
    for ($i = 6; $i >= 0; $i--) { $dt = date('Y-m-d', strtotime("-$i days")); $week[] = ['l' => date('D', strtotime($dt)), 'n' => $wm[$dt] ?? 0, 'h' => max(3, sl_pct($wm[$dt] ?? 0, $wmax)), 'now' => $i === 0]; }
    $d['week'] = $week;
    $ag = [0, 0, 0, 0, 0, 0];
    foreach (sl_rows("SELECT TIMESTAMPDIFF(HOUR, a.order_datetime, NOW()) AS h $base AND a.status_courier = 14") as $r) {
        $h = (int) $r->h; $ag[$h < 1 ? 0 : ($h < 2 ? 1 : ($h < 4 ? 2 : ($h < 8 ? 3 : ($h < 24 ? 4 : 5))))]++;
    }
    $amax = max(1, ...$ag);
    $d['aging'] = array_map(fn($n, $l) => ['l' => $l, 'n' => $n, 'h' => max(3, sl_pct($n, $amax))], $ag, ['<1', '1–2', '2–4', '4–8', '8–24', '24+']);

    // list
    $q = trim((string) ($_GET['q'] ?? ''));
    $when = (string) ($_GET['when'] ?? 'all');
    $filter = (string) ($_GET['filter'] ?? '');
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
    $fmap = ['requested' => 'a.status_courier = 14', 'accepted' => 'a.status_courier = 1', 'picked' => 'a.status_courier NOT IN (1,12,14,21)', 'refused' => 'a.status_courier IN (12,21)'];
    if (isset($fmap[$filter])) $where .= ' AND ' . $fmap[$filter];
    if ($driver > 0) $where .= " AND a.driver_id = $driver";
    $count = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE $where", $binds);
    $rows = sl_rows("SELECT a.order_id, a.order_prefix, a.order_no, a.order_datetime, a.order_date, a.sender_id, a.driver_id, a.status_courier, a.total_order, a.total_weight, a.notes, a.agency, s.sender_address, s.sender_city,
            (SELECT COALESCE(SUM(i.order_item_quantity),0) FROM cdb_add_order_item i WHERE i.order_id = a.order_id) AS items
        FROM cdb_add_order a LEFT JOIN cdb_address_shipments s ON s.order_track = CONCAT(a.order_prefix, a.order_no) WHERE $where ORDER BY a.order_id DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds);
    $users = sl_usersById(array_merge(array_map(fn($r) => $r->sender_id, $rows), array_map(fn($r) => $r->driver_id, $rows)));
    $isClient = sl_userIs('client');
    require_once CDP_ROOT . '/helpers/pickup_code.php';
    $can = [
        'accept' => $user->cdp_hasPermission('pickup_accept') && !$isClient, 'cancel' => $user->cdp_hasPermission('cancel_pickup'),
        'delete' => $user->cdp_hasPermission('delete_pickup') && !$isClient, 'driver' => $user->cdp_hasPermission('assign_driver_pickup') && !$isClient,
        'label' => $user->cdp_hasPermission(['print_pickup_label', 'print_label']), 'print' => $user->cdp_hasPermission('print_shipment'),
        'code' => cdp_pickupCodeCanUse($user), 'refuse' => $user->cdp_hasPermission('pickup_accept') && !$isClient,
    ];
    $list = [];
    foreach ($rows as $r) {
        $s = (int) $r->status_courier; $u = $users[(int) $r->sender_id] ?? null;
        $win = preg_match('/Window (\S+)/u', (string) $r->notes, $m) ? $m[1] : sl_date($r->order_datetime, 'H:i');
        $act = $s === 14 ? 'Accept' : '';
        $list[] = [
            'id' => (int) $r->order_id, 'trk' => $r->order_prefix . $r->order_no, 'name' => sl_userName($u), 'ini' => sl_initials(sl_userName($u)), 'phone' => $u->phone ?? '',
            'addr' => $r->sender_address ?: '—', 'area' => $r->sender_city ?: '', 'win' => $win, 'day' => sl_date($r->order_datetime, 'D M j'),
            'items' => (int) $r->items, 'kg' => (float) $r->total_weight > 0 ? number_format((float) $r->total_weight, 1) : '—', 'fee' => sl_money($r->total_order),
            'drv' => $r->driver_id ? sl_userName($users[(int) $r->driver_id] ?? null) : 'Unassigned', 'st' => $s === 14 ? 'Requested' : sl_statusLabel($s), 'cls' => $s === 14 ? 'pending' : sl_statusClass($s),
            'open' => !in_array($s, [12, 21], true) && !cdp_isHandedOver($s), 'requested' => $s === 14, 'act' => $act,
        ];
    }
    $d += ['rows' => $list, 'count' => $count, 'page' => $page, 'per' => $per, 'q' => $q, 'when' => $when, 'filter' => $filter, 'driver' => $driver, 'can' => $can, 'isClient' => $isClient];
    $d['whens'] = [['f' => 'all', 'label' => 'All'], ['f' => 'today', 'label' => 'Today'], ['f' => 'tomorrow', 'label' => 'Tomorrow'], ['f' => 'week', 'label' => 'This week']];
    $d['driverList'] = sl_rows('SELECT id, fname, lname FROM cdb_users WHERE userlevel = 3 AND active = 1 ORDER BY fname');
    $d['canAdd'] = $user->cdp_hasPermission($isClient ? 'pickup_add' : 'add_full_pickup');
    $d['canAging'] = $user->cdp_hasPermission('view_pickup_aging');
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
