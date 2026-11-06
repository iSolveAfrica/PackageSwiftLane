<?php
/**
 * Data for the warehouse boards: Warehouse, WarehouseDelivery, WarehousePickups, HandOut, LockerSearch, PickupAging.
 * Packages are air shipments (cdb_add_order, module 'air') and sea/locker packages (cdb_customers_packages, module 'sea').
 * "Ready" follows helpers/finance_switch.php cdp_wdQueueSql() for air (cleared by Accounts when finance is on,
 * physically here when it is off) plus Ready for PickUp / Available; sea packages are ready at 6 / 32.
 * Pickup codes are never shown to staff: only their state (helpers/pickup_code.php).
 */
require_once __DIR__ . '/ops_common.php';
require_once CDP_ROOT . '/helpers/pickup_code.php';

const SL_WH_TERMINAL = '8,15,16,21,27,35,12';

/** SQL for ready air rows (alias a). */
function sl_whReadyAirSql(): string
{
    return "a.is_deleted = 0 AND a.status_courier NOT IN (" . SL_WH_TERMINAL . ") AND (" . cdp_wdQueueSql('a') . " OR a.status_courier IN (6,32))";
}
function sl_whReadySeaSql(): string
{
    return "a.status_courier IN (6,32)";
}

/**
 * Ready packages (air + sea) as rows: module, order_id, trk, sender_id, status, weight, bay, ready_at, what, cleared.
 * $where adds a condition on alias a (applied to both tables), $binds its parameters.
 */
function sl_whReady(string $where = '', array $binds = [], int $limit = 200, int $offset = 0, bool $count = false)
{
    $scope = sl_opsScope('a');
    $bayA = sl_columnExists('cdb_add_order', 'warehouse_bay') ? 'a.warehouse_bay' : 'NULL';
    $bayS = sl_columnExists('cdb_customers_packages', 'warehouse_bay') ? 'a.warehouse_bay' : 'NULL';
    $air = "SELECT 'air' AS module, a.order_id, CONCAT(a.order_prefix, a.order_no) AS trk, a.sender_id, a.status_courier, a.total_weight, $bayA AS bay, a.fs_cleared_for_delivery AS cleared,
              (SELECT MAX(t.t_date) FROM cdb_courier_track t WHERE t.order_track = CONCAT(a.order_prefix, a.order_no) AND t.status_courier IN (4,6,32)) AS ready_at, a.order_date,
              (SELECT GROUP_CONCAT(i.order_item_description SEPARATOR ', ') FROM cdb_add_order_item i WHERE i.order_id = a.order_id) AS what
            FROM cdb_add_order a WHERE " . sl_whReadyAirSql() . " $scope $where";
    $sea = "SELECT 'sea' AS module, a.order_id, CONCAT(a.order_prefix, a.order_no) AS trk, a.sender_id, a.status_courier, a.total_weight, $bayS AS bay, 1 AS cleared,
              (SELECT MAX(t.t_date) FROM cdb_courier_track t WHERE t.order_track = CONCAT(a.order_prefix, a.order_no) AND t.status_courier IN (4,6,32)) AS ready_at, a.order_date,
              (SELECT GROUP_CONCAT(i.order_item_description SEPARATOR ', ') FROM cdb_customers_packages_detail i WHERE i.order_id = a.order_id) AS what
            FROM cdb_customers_packages a WHERE " . sl_whReadySeaSql() . " $scope $where";
    $b2 = [];
    foreach ($binds as $k => $v) { $b2[$k . 'a'] = $v; $b2[$k . 's'] = $v; }
    $airQ = $air; $seaQ = $sea;
    foreach (array_keys($binds) as $k) { $airQ = str_replace($k, $k . 'a', $airQ); $seaQ = str_replace($k, $k . 's', $seaQ); }
    $u = "($airQ) UNION ALL ($seaQ)";
    if ($count) return (int) sl_scalar("SELECT COUNT(*) FROM ($u) x", $b2);
    return sl_rows("SELECT * FROM ($u) x ORDER BY COALESCE(ready_at, order_date) ASC LIMIT $offset, $limit", $b2);
}

/** Customer search condition for alias a (name, locker, phone, tracking). */
function sl_whSearchSql(): string
{
    return " AND (CONCAT(a.order_prefix, a.order_no) LIKE :q OR a.sender_id IN (SELECT id FROM cdb_users WHERE CONCAT(fname,' ',lname) LIKE :q OR locker LIKE :q OR phone LIKE :q))";
}

/** Pickup-code state label for a package: Verified / Code sent / No code. */
function sl_whCodeStates(array $rows): array
{
    $out = [];
    if (!sl_tableExists('cdb_pickup_codes')) return $out;
    cdp_pickupCodeExpireStale();
    foreach (['air', 'sea'] as $m) {
        $ids = array_map(fn($r) => (int) $r->order_id, array_filter($rows, fn($r) => $r->module === $m));
        if (!$ids) continue;
        foreach (sl_rows("SELECT i.order_id, c.status, c.verified_at, c.last_sent_at, c.created_at, c.context FROM cdb_pickup_code_items i JOIN cdb_pickup_codes c ON c.id = i.code_id WHERE i.module = :m AND i.used_at IS NULL AND c.status IN ('active','verified') AND i.order_id IN (" . implode(',', $ids) . ') ORDER BY c.id DESC', [':m' => $m]) as $r) {
            $k = $m . ':' . (int) $r->order_id;
            if (isset($out[$k])) continue;
            $verified = $r->status === 'verified' && strtotime((string) $r->verified_at) + CDP_PICKUP_VERIFIED_TTL > time();
            $out[$k] = ['l' => $verified ? 'Verified' : (strpos((string) $r->context, 'ready') === 0 ? 'Sent with ready message' : 'Sent by staff'), 'sent' => sl_ago($r->last_sent_at ?: $r->created_at), 'verified' => $verified];
        }
    }
    return $out;
}

function sl_whRowsOut(array $rows): array
{
    $users = sl_usersById(array_map(fn($r) => $r->sender_id, $rows));
    $codes = sl_whCodeStates($rows);
    $out = [];
    foreach ($rows as $r) {
        $u = $users[(int) $r->sender_id] ?? null;
        $since = $r->ready_at ?: $r->order_date;
        $days = $since ? max(0, (int) floor((time() - strtotime($since)) / 86400)) : 0;
        $c = $codes[$r->module . ':' . (int) $r->order_id] ?? ['l' => 'No code yet', 'sent' => '', 'verified' => false];
        $out[] = [
            'module' => $r->module, 'oid' => (int) $r->order_id, 'id' => $r->trk, 'href' => $r->module === 'sea' ? sl_route('PackageDetail', ['id' => (int) $r->order_id]) : sl_route('ShipmentDetail', ['id' => (int) $r->order_id]),
            'cust' => sl_userName($u), 'ini' => sl_initials(sl_userName($u)), 'locker' => $u->locker ?? '', 'phone' => $u->phone ?? '', 'sender' => (int) $r->sender_id,
            'bay' => $r->bay ?: '—', 'what' => $r->what ?: ($r->module === 'sea' ? 'Sea package' : 'Shipment'), 'w' => sl_weight($r->total_weight), 'ready' => sl_date($since),
            'days' => $days . ' day' . ($days === 1 ? '' : 's'), 'dn' => $days, 'wc' => $days > 14 ? 'bad' : ($days >= 3 ? 'warn' : ''),
            'code' => $c['l'], 'cs' => $c['sent'], 'verified' => $c['verified'], 'cleared' => cdp_fsIsCleared($r->cleared), 'st' => sl_statusLabel($r->status_courier), 'pc' => sl_statusClass($r->status_courier),
        ];
    }
    return $out;
}

/** Warehouse.dc.html */
function sl_warehouseData($user, $core): array
{
    $bays = max(1, (int) sl_slSetting('warehouse_bays', 50));
    $hasBay = sl_columnExists('cdb_add_order', 'warehouse_bay');
    $occ = [];
    if ($hasBay) {
        foreach (sl_rows("SELECT warehouse_bay AS b, MAX(status_courier IN (6,32)) AS ready FROM cdb_add_order WHERE is_deleted = 0 AND warehouse_bay IS NOT NULL AND warehouse_bay <> '' AND status_courier NOT IN (" . SL_WH_TERMINAL . ") GROUP BY warehouse_bay
                          UNION ALL SELECT warehouse_bay, MAX(status_courier IN (6,32)) FROM cdb_customers_packages WHERE warehouse_bay IS NOT NULL AND warehouse_bay <> '' AND status_courier NOT IN (" . SL_WH_TERMINAL . ") GROUP BY warehouse_bay") as $r) {
            $occ[$r->b] = max((int) ($occ[$r->b] ?? 0), 1 + (int) $r->ready);
        }
    }
    $prefix = (string) sl_slSetting('warehouse_bay_prefix', 'B-');
    $grid = []; $free = 0; $busy = 0; $wait = 0;
    for ($i = 1; $i <= $bays; $i++) {
        $name = $prefix . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $s = $occ[$name] ?? 0;
        $grid[] = ['c' => $s === 2 ? 'h1' : ($s === 1 ? 'h3' : 'h0'), 'n' => $name, 't' => $name . ($s === 2 ? ' · awaiting pickup' : ($s === 1 ? ' · occupied' : ' · free'))];
        if ($s === 2) $wait++; elseif ($s === 1) $busy++; else $free++;
    }
    $feed = [];
    if (sl_tableExists('cdb_warehouse_scans')) {
        $sc = sl_rows('SELECT s.*, u.fname, u.lname FROM cdb_warehouse_scans s LEFT JOIN cdb_users u ON u.id = s.user_id ORDER BY s.id DESC LIMIT 8');
        foreach ($sc as $r) $feed[] = ['icon' => $r->action === 'move' ? 'i-refresh' : 'i-scan', 't' => $r->tracking . ($r->action === 'move' ? ' moved' : ' received') . ($r->bay ? ' · bay ' . $r->bay : ''), 's' => trim(($r->note ?: '') . ' · ' . trim($r->fname . ' ' . $r->lname), ' ·'), 'when' => sl_ago($r->created_at)];
    }
    if (!$feed) {
        foreach (sl_rows("SELECT t.order_track, t.t_date, t.comments, s.mod_style FROM cdb_courier_track t LEFT JOIN cdb_styles s ON s.id = t.status_courier WHERE t.status_courier IN (2,4,6,32) ORDER BY t.id DESC LIMIT 8") as $r) {
            $feed[] = ['icon' => 'i-box', 't' => $r->order_track . ' · ' . str_replace('_', ' ', (string) $r->mod_style), 's' => (string) $r->comments, 'when' => sl_ago($r->t_date)];
        }
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $w = $q !== '' ? sl_whSearchSql() : '';
    $b = $q !== '' ? [':q' => '%' . $q . '%'] : [];
    $office = sl_row('SELECT name_off, city FROM cdb_offices ORDER BY id LIMIT 1');
    return [
        'lede' => ($office ? $office->name_off : ($core->site_name ?? 'Hub')) . ' · receive, shelve and hand over parcels',
        'bays' => $grid, 'bayN' => $bays, 'free' => $free, 'busy' => $busy, 'wait' => $wait, 'full' => $busy + $wait, 'fullPct' => sl_pct($busy + $wait, $bays), 'hasBay' => $hasBay,
        'feed' => $feed, 'q' => $q, 'page' => $page, 'per' => $per,
        'rows' => sl_whRowsOut(sl_whReady($w, $b, $per, ($page - 1) * $per)), 'count' => sl_whReady($w, $b, 0, 0, true),
        'canReceive' => $user->cdp_hasPermission(['warehouse_receive', 'add_package', 'add_shipment']),
        'canHand' => $user->cdp_hasPermission(['view_warehouse_delivery', 'warehouse_deliver_package']),
        'canCode' => cdp_pickupCodeCanUse($user), 'canBulk' => $user->cdp_hasPermission('deliver_shipment'),
        'bayList' => array_column($grid, 'n'),
    ];
}

/** Handover KPIs + per-day bars + hour heat shared by WarehouseDelivery / WarehousePickups. */
function sl_whHandoverStats(int $days): array
{
    $scope = '';
    $rows = sl_rows("SELECT DATE(t.t_date) AS d, HOUR(t.t_date) AS h, DAYOFWEEK(t.t_date) AS wd, t.status_courier AS s, COUNT(DISTINCT t.order_track) AS n FROM cdb_courier_track t WHERE t.status_courier IN (8,15) AND t.t_date >= CURDATE() - INTERVAL " . ($days - 1) . " DAY GROUP BY d, h, wd, s");
    $byDay = []; $hours = array_fill(8, 11, 0); $heat = []; $pick = 0; $del = 0;
    foreach ($rows as $r) {
        $n = (int) $r->n;
        $byDay[$r->d][(int) $r->s] = ($byDay[$r->d][(int) $r->s] ?? 0) + $n;
        if ((int) $r->s === 15) $pick += $n; else $del += $n;
        if ((int) $r->h >= 8 && (int) $r->h <= 18) $hours[(int) $r->h] += $n;
        $heat[(int) $r->wd][(int) $r->h] = ($heat[(int) $r->wd][(int) $r->h] ?? 0) + $n;
    }
    $bars = []; $mx = 1; $busy = ['', 0];
    foreach ($byDay as $dd => $v) $mx = max($mx, array_sum($v));
    for ($i = $days - 1; $i >= 0; $i--) {
        $dt = date('Y-m-d', strtotime("-$i days"));
        $p = $byDay[$dt][15] ?? 0; $dl = $byDay[$dt][8] ?? 0;
        if ($p + $dl > $busy[1]) $busy = [date('D M j', strtotime($dt)), $p + $dl];
        $bars[] = ['p' => 'height:' . sl_pct($p, $mx) . '%', 'd' => 'height:' . sl_pct($dl, $mx) . '%', 'tip' => date('D M j', strtotime($dt)) . ": $p picked up, $dl delivered", 'n' => $p + $dl, 'l' => date('D', strtotime($dt))];
    }
    $peak = 1;
    foreach ($heat as $hh) foreach ($hh as $n) $peak = max($peak, $n);
    $hm = [];
    foreach ([2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat'] as $wd => $l) {
        $c = [];
        for ($h = 8; $h <= 18; $h++) $c[] = sl_heatLevel($heat[$wd][$h] ?? 0, $peak);
        $hm[] = ['d' => $l, 'c' => $c];
    }
    $tot = $pick + $del;
    return ['tot' => $tot, 'pick' => $pick, 'del' => $del, 'pickPct' => sl_pct($pick, $tot), 'delPct' => sl_pct($del, $tot), 'perDay' => number_format($tot / max(1, $days), 1), 'bars' => $bars, 'busy' => $busy[0] ?: '—', 'hours' => $hours, 'heat' => $hm, 'from' => date('M j', strtotime('-' . ($days - 1) . ' days'))];
}

/** WarehouseDelivery.dc.html */
function sl_warehouseDeliveryData($user, $core): array
{
    $period = (int) ($_GET['period'] ?? 14);
    if (!in_array($period, [7, 14, 30], true)) $period = 14;
    $st = sl_whHandoverStats($period);
    $today = sl_whHandoverStats(1);
    $readyN = sl_whReady('', [], 0, 0, true);
    $codes = sl_tableExists('cdb_pickup_codes') ? sl_row("SELECT SUM(status IN ('active','verified') AND expires_at >= NOW()) AS live, SUM(status = 'void') AS voided, SUM(status = 'void' AND DATE(created_at) >= CURDATE() - INTERVAL 30 DAY) AS v30, SUM(DATE(last_sent_at) = CURDATE()) AS resent FROM cdb_pickup_codes") : null;
    $wait = sl_scalar("SELECT AVG(TIMESTAMPDIFF(HOUR, r.t, h.t)) FROM (SELECT order_track, MIN(t_date) AS t FROM cdb_courier_track WHERE status_courier IN (6,32) GROUP BY order_track) r JOIN (SELECT order_track, MAX(t_date) AS t FROM cdb_courier_track WHERE status_courier IN (8,15) AND t_date >= CURDATE() - INTERVAL $period DAY GROUP BY order_track) h ON h.order_track = r.order_track WHERE h.t > r.t", [], 0);
    // package list: ready + handed over in the period
    $q = trim((string) ($_GET['q'] ?? ''));
    $f = (string) ($_GET['filter'] ?? 'ready');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $w = $q !== '' ? sl_whSearchSql() : '';
    $b = $q !== '' ? [':q' => '%' . $q . '%'] : [];
    if ($f === 'handed') {
        $hist = sl_whHistory($q, '', 30, $page, $per);
        $pkgs = $hist['rows']; $count = $hist['count'];
    } else {
        if ($f === 'waiting') $w .= ' AND a.order_date < CURDATE() - INTERVAL 3 DAY';
        $pkgs = sl_whRowsOut(sl_whReady($w, $b, $per, ($page - 1) * $per));
        $count = sl_whReady($w, $b, 0, 0, true);
    }
    return $st + [
        'period' => $period, 'periods' => array_map(fn($p) => ['p' => $p, 'label' => $p . ' days', 'cls' => $p === $period ? 'on' : ''], [7, 14, 30]),
        'todayTot' => $today['tot'], 'todayPick' => $today['pick'], 'todayDel' => $today['del'], 'readyN' => $readyN, 'todayPct' => sl_pct($today['tot'], $today['tot'] + $readyN),
        'live' => (int) ($codes->live ?? 0), 'voided' => (int) ($codes->v30 ?? 0), 'resent' => (int) ($codes->resent ?? 0),
        'wait' => $wait ? ($wait >= 48 ? round($wait / 24, 1) . ' days' : round($wait) . ' h') : '—',
        'filters' => array_map(fn($x) => $x + ['cls' => $x['f'] === $f ? 'on' : ''], [['f' => 'ready', 'label' => 'Ready'], ['f' => 'waiting', 'label' => 'Waiting 3+ days'], ['f' => 'handed', 'label' => 'Handed over']]),
        'filter' => $f, 'q' => $q, 'page' => $page, 'per' => $per, 'pkgs' => $pkgs, 'count' => $count,
        'canCode' => cdp_pickupCodeCanUse($user), 'canPkg' => $user->cdp_hasPermission('warehouse_deliver_package'), 'canUser' => $user->cdp_hasPermission('warehouse_deliver_user'),
        'cust' => sl_whCustomer((int) ($_GET['customer'] ?? 0)),
        'sms' => (int) ($core->active_sms ?? 0) === 1, 'wa' => (int) ($core->active_whatsapp ?? 0) === 1,
    ];
}

/** One customer's ready packages for the handover flow (HandOut / WarehouseDelivery). */
function sl_whCustomer(int $id): ?array
{
    if ($id <= 0) return null;
    $u = sl_row('SELECT id, fname, lname, locker, phone, email, avatar FROM cdb_users WHERE id = :id AND userlevel = 1', [':id' => $id]);
    if (!$u) return null;
    $rows = sl_whRowsOut(sl_whReady(' AND a.sender_id = ' . (int) $id, [], 50));
    $unpaid = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_invoice IN (2,23) AND status_courier NOT IN (8,15,21)', [':u' => $id]);
    $byMod = ['air' => [], 'sea' => []];
    foreach ($rows as $r) $byMod[$r['module']][] = $r['oid'];
    $state = ['verified' => false, 'live' => null];
    foreach ($byMod as $m => $ids) {
        if (!$ids) continue;
        $s = sl_pickupCodeInfo($m, $ids);
        if ($s['live'] && !$state['live']) $state['live'] = $s['live'];
        $state['verified'] = $state['verified'] || $s['verified'];
    }
    return ['id' => (int) $u->id, 'name' => sl_userName($u), 'ini' => sl_initials(sl_userName($u)), 'lock' => $u->locker ?: '', 'phone' => $u->phone ?: '', 'pkgs' => $rows, 'byMod' => $byMod, 'code' => $state,
        'payL' => $unpaid ? $unpaid . ' unpaid' : 'Paid up', 'payCls' => $unpaid ? 'overdue' : 'paid', 'unpaid' => $unpaid];
}

/** Handed-out history (WarehousePickups). */
function sl_whHistory(string $q, string $kind, int $days, int $page, int $per): array
{
    $w = "t.status_courier IN (8,15) AND t.t_date >= CURDATE() - INTERVAL " . max(0, $days - 1) . " DAY";
    if ($kind === 'picked') $w .= ' AND t.status_courier = 15';
    if ($kind === 'delivered') $w .= ' AND t.status_courier = 8';
    $b = [];
    if ($q !== '') {
        $w .= " AND (t.order_track LIKE :q OR COALESCE(a.sender_id, p.sender_id) IN (SELECT id FROM cdb_users WHERE CONCAT(fname,' ',lname) LIKE :q OR locker LIKE :q))";
        $b[':q'] = '%' . $q . '%';
    }
    $ctx = cdp_getAgencyContext();
    if (!empty($ctx['is_restricted']) && !empty($ctx['agency_id'])) $w .= ' AND COALESCE(a.agency, p.agency) = ' . (int) $ctx['agency_id'];
    $from = "FROM cdb_courier_track t LEFT JOIN cdb_add_order a ON CONCAT(a.order_prefix, a.order_no) = t.order_track AND a.is_deleted = 0 LEFT JOIN cdb_customers_packages p ON a.order_id IS NULL AND CONCAT(p.order_prefix, p.order_no) = t.order_track
             LEFT JOIN cdb_users s ON s.id = t.user_id WHERE $w AND (a.order_id IS NOT NULL OR p.order_id IS NOT NULL)";
    $count = (int) sl_scalar("SELECT COUNT(*) $from", $b);
    $rows = sl_rows("SELECT t.id, t.order_track, t.t_date, t.status_courier, t.comments, s.fname AS sf, s.lname AS sl, IF(a.order_id IS NOT NULL, 'air', 'sea') AS module, COALESCE(a.order_id, p.order_id) AS order_id, COALESCE(a.sender_id, p.sender_id) AS sender_id, COALESCE(a.total_weight, p.total_weight) AS w, COALESCE(a.status_courier, p.status_courier) AS cur
        $from ORDER BY t.t_date DESC, t.id DESC LIMIT " . (($page - 1) * $per) . ", $per", $b);
    $users = sl_usersById(array_map(fn($r) => $r->sender_id, $rows));
    $used = [];
    if ($rows && sl_tableExists('cdb_pickup_code_items')) {
        foreach (sl_rows('SELECT i.module, i.order_id, i.used_at FROM cdb_pickup_code_items i WHERE i.used_at IS NOT NULL AND i.order_id IN (' . implode(',', array_map(fn($r) => (int) $r->order_id, $rows)) . ')') as $x) $used[$x->module . ':' . (int) $x->order_id] = $x->used_at;
    }
    $out = [];
    foreach ($rows as $r) {
        $u = $users[(int) $r->sender_id] ?? null;
        $k = $r->module . ':' . (int) $r->order_id;
        $out[] = ['tid' => (int) $r->id, 'module' => $r->module, 'oid' => (int) $r->order_id, 'id' => $r->order_track, 'href' => $r->module === 'sea' ? sl_route('PackageDetail', ['id' => (int) $r->order_id]) : sl_route('ShipmentDetail', ['id' => (int) $r->order_id]),
            'cust' => sl_userName($u), 'ini' => sl_initials(sl_userName($u)), 'locker' => $u->locker ?? '', 'w' => sl_weight($r->w), 'kind' => cdp_handoverLabel($r->status_courier), 'kc' => (int) $r->status_courier === 15 ? 'ok' : '',
            'by' => trim(($r->sf ?? '') . ' ' . mb_substr((string) ($r->sl ?? ''), 0, 1) . '.'), 'time' => sl_date($r->t_date, 'H:i'), 'day' => sl_date($r->t_date, 'M j'), 'code' => isset($used[$k]) ? 'Code checked' : ((int) $r->status_courier === 8 ? 'On delivery' : 'No code record'),
            'still' => cdp_isHandedOver($r->cur), 'receipt' => $r->module === 'sea' ? 'print_invoice_package.php?id=' . (int) $r->order_id : 'print_inv_ship.php?id=' . (int) $r->order_id,
            'when' => sl_date($r->t_date, 'M j H:i'), 'st' => cdp_handoverLabel($r->status_courier), 'pc' => 'delivered', 'bay' => '—', 'what' => '', 'ready' => '', 'days' => '', 'wc' => '', 'cs' => ''];
    }
    return ['rows' => $out, 'count' => $count];
}

/** WarehousePickups.dc.html */
function sl_warehousePickupsData($user, $core): array
{
    $days = (int) ($_GET['days'] ?? 1);
    if (!in_array($days, [1, 7, 30], true)) $days = 1;
    $kind = (string) ($_GET['kind'] ?? '');
    $q = trim((string) ($_GET['q'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $st = sl_whHandoverStats($days === 1 ? 7 : $days);
    $today = sl_whHandoverStats(1);
    $lastWeek = (int) sl_scalar("SELECT COUNT(DISTINCT order_track) FROM cdb_courier_track WHERE status_courier IN (8,15) AND DATE(t_date) = CURDATE() - INTERVAL 7 DAY");
    $readyN = sl_whReady('', [], 0, 0, true);
    $onRoute = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE is_deleted = 0 AND status_courier = 7');
    $voided = sl_tableExists('cdb_pickup_codes') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_pickup_codes WHERE status = 'void' AND created_at >= CURDATE() - INTERVAL $days DAY") : 0;
    $h = sl_whHistory($q, $kind, $days, $page, $per);
    $all = sl_whHistory('', '', $days, 1, 1)['count'];
    $pk = sl_whHistory('', 'picked', $days, 1, 1)['count'];
    $hours = $today['hours'];
    $peakH = array_search(max($hours ?: [0]), $hours);
    $mx = max(1, ...array_values($hours));
    $pts = [];
    $i = 0;
    foreach ($hours as $hh => $n) { $pts[] = round(44 + $i * (506 / max(1, count($hours) - 1)), 1) . ',' . round(190 - $n / $mx * 178, 1); $i++; }
    $dayBars = array_slice($st['bars'], -7);
    $dmx = max(1, ...array_map(fn($b) => $b['n'], $dayBars));
    return [
        'days' => $days, 'kind' => $kind, 'q' => $q, 'page' => $page, 'per' => $per, 'rows' => $h['rows'], 'count' => $h['count'],
        'todayTot' => $today['tot'], 'readyN' => $readyN, 'todayPct' => sl_pct($today['tot'], $today['tot'] + $readyN), 'delta' => ($today['tot'] >= $lastWeek ? '▲ ' : '▼ ') . abs($today['tot'] - $lastWeek) . ' vs last ' . date('l'),
        'pick' => $today['pick'], 'pickPct' => $today['pickPct'], 'del' => $today['del'], 'delPct' => $today['delPct'], 'onRoute' => $onRoute, 'voided' => $voided,
        'all' => $all, 'picked' => $pk, 'delivered' => $all - $pk, 'perDay' => $st['perDay'],
        'dayBars' => array_map(fn($b) => $b + ['h' => round($b['n'] / $dmx * 178, 1)], $dayBars), 'dmx' => $dmx,
        'line' => implode(' ', $pts), 'peak' => $mx > 1 || max($hours) > 0 ? sprintf('%02d:00–%02d:00', $peakH, $peakH + 1) : '—', 'hourMax' => $mx, 'hourKeys' => array_keys($hours),
        'canUndo' => $user->cdp_hasPermission('warehouse_undo_delivery'), 'canHand' => $user->cdp_hasPermission(['view_warehouse_delivery', 'warehouse_deliver_package']),
        'canAging' => $user->cdp_hasPermission('view_pickup_aging'),
    ];
}

/** HandOut.dc.html: counter queue (customers with ready parcels) + the selected customer. */
function sl_handOutData($user, $core): array
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $w = $q !== '' ? sl_whSearchSql() : '';
    $b = $q !== '' ? [':q' => '%' . $q . '%'] : [];
    $rows = sl_whReady($w, $b, 300);
    $by = [];
    foreach ($rows as $r) {
        $k = (int) $r->sender_id;
        $by[$k] = $by[$k] ?? ['n' => 0, 'since' => $r->ready_at ?: $r->order_date];
        $by[$k]['n']++;
    }
    $users = sl_usersById(array_keys($by));
    $sel = (int) ($_GET['customer'] ?? 0);
    $queue = [];
    foreach (array_slice($by, 0, 12, true) as $id => $x) {
        $u = $users[$id] ?? null;
        $queue[] = ['id' => $id, 'cls' => $id === $sel ? 'on' : '', 'name' => sl_userName($u), 'ini' => sl_initials(sl_userName($u)), 'lock' => $u->locker ?? '', 'n' => $x['n'] . ' parcel' . ($x['n'] === 1 ? '' : 's'), 'wait' => sl_ago($x['since']), 'st' => 'Ready', 'pc' => 'delivered', 'href' => sl_url(['customer' => $id])];
    }
    $c = sl_whCustomer($sel);
    $doneToday = sl_whHandoverStats(1)['tot'];
    return ['q' => $q, 'queue' => $queue, 'qN' => count($by), 'c' => $c, 'doneToday' => $doneToday, 'office' => ($core->c_city ?? '') ?: 'the hub',
        'canCode' => cdp_pickupCodeCanUse($user), 'canPkg' => $user->cdp_hasPermission('warehouse_deliver_package'), 'canUser' => $user->cdp_hasPermission('warehouse_deliver_user'),
        'track' => trim((string) ($_GET['tracking'] ?? ''))];
}

/** LockerSearch.dc.html */
function sl_lockerSearchData($user, $core): array
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $d = ['q' => $q, 'u' => null, 'matches' => [], 'parcels' => [], 'mailbox' => []];
    if ($q === '') return $d;
    $ctx = cdp_getAgencyContext();
    $scope = !empty($ctx['is_restricted']) && !empty($ctx['agency_id']) ? ' AND agency_id = ' . (int) $ctx['agency_id'] : '';
    $m = sl_rows("SELECT id, fname, lname, locker, email, phone, active, approve FROM cdb_users WHERE userlevel = 1 $scope AND (locker LIKE :q OR CONCAT(fname,' ',lname) LIKE :q OR phone LIKE :q OR email LIKE :q OR id IN (SELECT sender_id FROM cdb_add_order WHERE CONCAT(order_prefix, order_no) = :t) OR id IN (SELECT sender_id FROM cdb_customers_packages WHERE CONCAT(order_prefix, order_no) = :t OR tracking_purchase = :t)) ORDER BY (locker = :e) DESC, fname LIMIT 10", [':q' => '%' . $q . '%', ':t' => $q, ':e' => $q]);
    $pick = (int) ($_GET['id'] ?? 0);
    $u = null;
    foreach ($m as $x) if ((int) $x->id === $pick) $u = $x;
    $u = $u ?: ($m[0] ?? null);
    $d['matches'] = array_map(fn($x) => ['id' => (int) $x->id, 'name' => sl_userName($x), 'lock' => $x->locker, 'on' => $u && (int) $x->id === (int) $u->id], $m);
    if (!$u) return $d;
    $d['u'] = ['id' => (int) $u->id, 'name' => sl_userName($u), 'ini' => sl_initials(sl_userName($u)), 'lock' => $u->locker ?: '—', 'email' => $u->email, 'phone' => $u->phone, 'ok' => (int) $u->active === 1 && (int) ($u->approve ?? 1) === 1];
    $bayA = sl_columnExists('cdb_add_order', 'warehouse_bay') ? 'a.warehouse_bay' : 'NULL';
    $bayS = sl_columnExists('cdb_customers_packages', 'warehouse_bay') ? 'a.warehouse_bay' : 'NULL';
    $rows = sl_rows("(SELECT 'air' AS module, a.order_id, CONCAT(a.order_prefix,a.order_no) AS trk, (SELECT t.tracking_number FROM cdb_package_tracking_number t WHERE t.order_id = a.order_id ORDER BY t.id DESC LIMIT 1) AS postal, $bayA AS bay, a.total_weight, a.status_courier, a.order_date FROM cdb_add_order a WHERE a.sender_id = :u AND a.is_deleted = 0 AND a.status_courier NOT IN (8,15,21))
        UNION ALL (SELECT 'sea', a.order_id, CONCAT(a.order_prefix,a.order_no), a.tracking_purchase, $bayS, a.total_weight, a.status_courier, a.order_date FROM cdb_customers_packages a WHERE a.sender_id = :u2 AND a.status_courier NOT IN (8,15,21)) ORDER BY order_date DESC LIMIT 40", [':u' => (int) $u->id, ':u2' => (int) $u->id]);
    $inWh = 0; $way = 0; $bays = [];
    foreach ($rows as $r) {
        $here = in_array((int) $r->status_courier, [4, 6, 32], true);
        $here ? $inWh++ : $way++;
        if ($r->bay) $bays[$r->bay] = 1;
        $d['parcels'][] = ['id' => $r->trk, 'href' => $r->module === 'sea' ? sl_route('PackageDetail', ['id' => (int) $r->order_id]) : sl_route('ShipmentDetail', ['id' => (int) $r->order_id]), 'post' => $r->postal ?: '—', 'bay' => $r->bay ?: ($r->module === 'sea' ? 'Sea' : '—'), 'w' => sl_weight($r->total_weight), 'st' => sl_statusLabel($r->status_courier), 'cls' => sl_statusClass($r->status_courier), 'mode' => $r->module];
    }
    $d['inWh'] = $inWh; $d['way'] = $way; $d['bayN'] = count($bays);
    $addr = trim((string) ($core->locker_address ?? ''));
    $al = sl_row('SELECT name, country, city, cpostal FROM cdb_address_locker ORDER BY id LIMIT 1');
    $d['mailbox'] = [['Name', $d['u']['name'] . ' ' . $d['u']['lock']], ['Street', $addr ?: ($al->name ?? '—')], ['City, state, ZIP', $al ? trim($al->city . ' ' . $al->cpostal) : '—'], ['Country', $al->country ?? ($core->c_country ?? '—')]];
    $d['copy'] = implode("\n", array_map(fn($x) => $x[1], $d['mailbox']));
    $d['canProfile'] = $user->cdp_hasPermission('view_client_list');
    return $d;
}

/** PickupAging.dc.html */
function sl_pickupAgingData($user, $core): array
{
    $mode = (string) ($_GET['mode'] ?? 'all');
    $rows = sl_whReady(' AND a.status_courier IN (6,32)', [], 2000);
    if ($mode === 'air' || $mode === 'sea') $rows = array_values(array_filter($rows, fn($r) => $r->module === $mode));
    $out = sl_whRowsOut($rows);
    usort($out, fn($a, $b) => $b['dn'] <=> $a['dn']);
    $n = count($out);
    $u3 = count(array_filter($out, fn($r) => $r['dn'] < 3)); $m14 = count(array_filter($out, fn($r) => $r['dn'] >= 3 && $r['dn'] <= 14)); $o14 = $n - $u3 - $m14;
    $edges = [[0, 1, '0–1'], [1, 2, '1–2'], [2, 3, '2–3'], [3, 5, '3–5'], [5, 7, '5–7'], [7, 14, '7–14'], [14, 30, '14–30'], [30, 99999, '30+']];
    $hist = [];
    foreach ($edges as [$a, $b, $l]) $hist[] = ['l' => $l, 'n' => count(array_filter($out, fn($r) => $r['dn'] >= $a && $r['dn'] < $b))];
    $hmx = max(4, ...array_column($hist, 'n'));
    foreach ($hist as $i => &$hh) { $hh['x'] = round(62.6 + $i * 63.25, 1); $hh['h'] = round($hh['n'] / $hmx * 178, 1); $hh['y'] = round(190 - $hh['h'], 1); }
    unset($hh);
    // average ready → handed over, last 6 months
    $avg = [];
    foreach (sl_rows("SELECT DATE_FORMAT(h.t, '%Y-%m') AS ym, AVG(TIMESTAMPDIFF(HOUR, r.t, h.t)) / 24 AS d FROM (SELECT order_track, MIN(t_date) AS t FROM cdb_courier_track WHERE status_courier IN (6,32) GROUP BY order_track) r JOIN (SELECT order_track, MAX(t_date) AS t FROM cdb_courier_track WHERE status_courier IN (8,15) AND t_date >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01') GROUP BY order_track) h ON h.order_track = r.order_track WHERE h.t > r.t GROUP BY ym") as $r) $avg[$r->ym] = (float) $r->d;
    $series = []; $lab = [];
    for ($i = 5; $i >= 0; $i--) { $ym = date('Y-m', strtotime("first day of -$i months")); $series[] = round($avg[$ym] ?? 0, 1); $lab[] = date('M', strtotime($ym . '-01')); }
    $amx = max(8, ceil(max($series)));
    $pts = []; foreach ($series as $i => $v) $pts[] = round(44 + $i * 101.2, 1) . ',' . round(190 - $v / $amx * 178, 1);
    $avgAll = $n ? round(array_sum(array_column($out, 'dn')) / $n, 1) : 0;
    $pending = function_exists('cdp_pickupAgingPending') && sl_tableExists('cdb_package_pickup_aging') ? count((array) cdp_pickupAgingPending()) : 0;
    return [
        'mode' => $mode, 'rows' => array_slice($out, 0, 100), 'n' => $n, 'u3' => $u3, 'm14' => $m14, 'o14' => $o14,
        'u3p' => sl_pct($u3, $n), 'm14p' => sl_pct($m14, $n), 'o14p' => sl_pct($o14, $n), 'hist' => $hist, 'hmx' => $hmx, 'avg' => $avgAll,
        'line' => implode(' ', $pts), 'lab' => $lab, 'series' => $series, 'amx' => $amx, 'first' => $series[0], 'last' => end($series), 'firstL' => $lab[0],
        'pending' => $pending, 'canCode' => cdp_pickupCodeCanUse($user), 'canNotify' => $user->cdp_hasPermission('view_pickup_aging'),
    ];
}
