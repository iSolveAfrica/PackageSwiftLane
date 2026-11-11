<?php
/**
 * Data for the rider pages: Drivers (split sheet), RiderDispatch, RiderDetail,
 * RiderSettings and DriverApp. Fees come from cdb_rider_fee_rules
 * (migrations/2026_11_06_people_staff.sql); jobs are cdb_add_order rows with driver_id.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_people.php';
require_once CDP_ROOT . '/helpers/sl_nav.php';
require_once CDP_ROOT . '/helpers/sl_rider_route.php';

const SL_RD_OPEN_PICKUP = [1, 14];          // pickup waiting to be collected
const SL_RD_OPEN_DELIVERY = [5, 6, 7, 32];  // distribution / available / on route / ready
const SL_RD_DONE = [8, 15];                 // delivered / picked up
const SL_RD_FAILED = [12, 16];              // rejected / not picked up

/** Active fee rules grouped by type. */
function sl_rdRules(bool $all = false): array
{
    if (!sl_tableExists('cdb_rider_fee_rules')) return [];
    $out = [];
    foreach (sl_rows('SELECT * FROM cdb_rider_fee_rules ' . ($all ? '' : 'WHERE active = 1 ') . 'ORDER BY sort_order, id') as $r) $out[$r->rule_type][] = $r;
    return $out;
}

/** Fee and rider share for one job. $rider: cdb_users row (vehicle_type, zone). */
function sl_rdFee($job, $rider, ?array $rules = null): array
{
    $rules = $rules ?? sl_rdRules();
    $base = ((int) ($job->is_pickup ?? 0) ? ($rules['per_pickup'][0] ?? null) : ($rules['per_delivery'][0] ?? null));
    $fee = $base ? (float) $base->amount : 0.0;
    $pct = $base ? (float) $base->rider_pct : 70.0;
    $zone = strtolower(trim((string) ($rider->zone ?? '')));
    foreach ($rules['zone'] ?? [] as $z) if ($zone !== '' && strtolower($z->label) === $zone) { $fee = (float) $z->amount; break; }
    $veh = strtolower(trim((string) ($rider->vehicle_type ?? $rider->vehiclecode ?? '')));
    foreach ($rules['vehicle'] ?? [] as $v) if ($veh !== '' && strtolower($v->label) === $veh) { $pct = (float) $v->rider_pct; break; }
    $rid = round($fee * $pct / 100, 2);
    return ['fee' => $fee, 'pct' => $pct, 'rider' => $rid, 'swift' => round($fee - $rid, 2)];
}

function sl_rdScope(): string
{
    return sl_navAgencyWhere('o');
}

/** Riders (driver-type roles), keyed by id. */
function sl_rdRiders(bool $activeOnly = false): array
{
    $ids = implode(',', array_map('intval', sl_riderRoleIds()));
    $cols = sl_columnExists('cdb_users', 'vehicle_type') ? 'u.vehicle_type, u.zone, u.rider_status' : "u.vehiclecode AS vehicle_type, '' AS zone, '' AS rider_status";
    $out = [];
    foreach (sl_rows("SELECT u.id, u.username, u.fname, u.lname, u.email, u.phone, u.active, u.created, u.lastlogin, u.enrollment, u.vehiclecode, u.name_off, u.userlevel, $cols FROM cdb_users u WHERE u.userlevel IN ($ids)" . ($activeOnly ? ' AND u.active = 1' : '') . ' ORDER BY u.fname, u.lname') as $r) {
        $r->name = trim($r->fname . ' ' . $r->lname) ?: $r->username;
        $out[(int) $r->id] = $r;
    }
    return $out;
}

/** SQL expression for the time a job was finished. */
function sl_rdDoneAt(string $a = 'o'): string
{
    $pod = sl_columnExists('cdb_add_order', 'pod_at') ? "$a.pod_at, " : '';
    return "COALESCE($pod(SELECT MAX(t.t_date) FROM cdb_courier_track t WHERE t.order_id = $a.order_id AND t.status_courier = $a.status_courier), $a.order_date)";
}

/** Jobs of riders: open ones plus those finished in [from, to]. */
function sl_rdJobs(string $from, string $to, ?int $driverId = null): array
{
    $open = implode(',', array_merge(SL_RD_OPEN_PICKUP, SL_RD_OPEN_DELIVERY));
    $closed = implode(',', array_merge(SL_RD_DONE, SL_RD_FAILED));
    $w = 'o.is_deleted = 0 AND o.driver_id > 0' . sl_rdScope() . ($driverId ? ' AND o.driver_id = ' . (int) $driverId : '');
    $done = sl_rdDoneAt('o');
    if (sl_tableExists('cdb_delivery_claims')) $w .= " AND NOT EXISTS (SELECT 1 FROM cdb_delivery_claims dc WHERE dc.module='air' AND dc.order_id=o.order_id)";
    $recipientType = sl_columnExists('cdb_add_order', 'recipient_type') ? "COALESCE(o.recipient_type, 'recipient')" : "'recipient'";
    $sql = "SELECT o.order_id, o.order_prefix, o.order_no, o.driver_id, o.status_courier, o.is_pickup, o.total_order, o.total_weight, o.order_pay_mode, o.status_invoice, o.sender_id, o.receiver_id, o.person_receives,
            CASE WHEN o.is_pickup = 1 THEN CONCAT(s.fname, ' ', s.lname)
                 WHEN $recipientType = 'user' THEN CONCAT(ru.fname, ' ', ru.lname)
                 ELSE CONCAT(r.fname, ' ', r.lname) END AS cust,
            CASE WHEN o.is_pickup = 1 THEN s.phone WHEN $recipientType = 'user' THEN ru.phone ELSE r.phone END AS cust_phone,
            $done AS done_at,
            CASE WHEN o.is_pickup = 1 THEN
                COALESCE(NULLIF(CONCAT_WS(', ', NULLIF(a.sender_address,''), NULLIF(a.sender_city,''), NULLIF(a.sender_country,'')), ''), sa.address)
                ELSE COALESCE(NULLIF(CONCAT_WS(', ', NULLIF(a.recipient_address,''), NULLIF(a.recipient_city,''), NULLIF(a.recipient_country,'')), ''), CASE WHEN $recipientType = 'user' THEN ua.address ELSE ra.address END) END AS addr,
            st.mod_style AS status_name
        FROM cdb_add_order o LEFT JOIN cdb_users s ON s.id = o.sender_id LEFT JOIN cdb_styles st ON st.id = o.status_courier
        LEFT JOIN cdb_recipients r ON r.id = o.receiver_id AND $recipientType <> 'user'
        LEFT JOIN cdb_users ru ON ru.id = o.receiver_id AND $recipientType = 'user'
        LEFT JOIN cdb_address_shipments a ON a.id = (SELECT ax.id FROM cdb_address_shipments ax WHERE ax.order_track = CONCAT(o.order_prefix, o.order_no) ORDER BY ax.id DESC LIMIT 1)
        LEFT JOIN cdb_senders_addresses sa ON sa.id_addresses = o.sender_address_id AND sa.user_id = o.sender_id
        LEFT JOIN cdb_senders_addresses ua ON ua.id_addresses = o.receiver_address_id AND ua.user_id = o.receiver_id
        LEFT JOIN cdb_recipients_addresses ra ON ra.id_addresses = o.receiver_address_id AND ra.recipient_id = o.receiver_id
        WHERE $w AND (o.status_courier IN ($open) OR (o.status_courier IN ($closed) AND $done BETWEEN :f AND :t))
        ORDER BY o.driver_id, o.status_courier, o.order_id LIMIT 3000";
    return sl_rows($sql, [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']);
}

function sl_rdJobState($j): array
{
    $s = (int) $j->status_courier;
    if (in_array($s, SL_RD_DONE, true)) return [$j->is_pickup ? 'Collected' : 'Delivered', 'delivered', 'done'];
    if (in_array($s, SL_RD_FAILED, true)) return ['Failed', 'overdue', 'failed'];
    if ($s === 7) return ['On route', 'onway', 'open'];
    if (in_array($s, SL_RD_OPEN_PICKUP, true)) return ['To collect', 'pending', 'open'];
    return [$j->status_name ?: 'Assigned', 'sorting', 'open'];
}

/** Per-rider totals for a period. */
function sl_rdStats(string $from, string $to, ?int $driverId = null): array
{
    $riders = sl_rdRiders();
    $rules = sl_rdRules();
    $out = [];
    foreach ($riders as $id => $r) $out[$id] = ['rider' => $r, 'assigned' => 0, 'done' => 0, 'failed' => 0, 'open' => 0, 'fees' => 0.0, 'rid' => 0.0, 'sw' => 0.0, 'pct' => sl_rdFee((object) ['is_pickup' => 0], $r, $rules)['pct'], 'cash' => 0.0, 'jobs' => []];
    foreach (sl_rdJobs($from, $to, $driverId) as $j) {
        $id = (int) $j->driver_id;
        if (!isset($out[$id])) continue;
        $f = sl_rdFee($j, $riders[$id], $rules);
        $st = sl_rdJobState($j);
        $o = &$out[$id];
        $o['assigned']++;
        if ($st[2] === 'done') { $o['done']++; $o['fees'] += $f['fee']; $o['rid'] += $f['rider']; $o['sw'] += $f['swift']; if ((int) $j->status_invoice !== 1 && (int) $j->status_invoice !== 19 && !$j->is_pickup) $o['cash'] += 0; }
        elseif ($st[2] === 'failed') $o['failed']++;
        else $o['open']++;
        $o['jobs'][] = ['j' => $j, 'f' => $f, 'st' => $st];
        unset($o);
    }
    return $out;
}

function sl_rdPeriod(): array
{
    $p = (string) ($_GET['period'] ?? 'today');
    $map = ['today' => ['Today', date('Y-m-d'), date('Y-m-d')], 'week' => ['This week', date('Y-m-d', strtotime('monday this week')), date('Y-m-d')], 'month' => ['This month', date('Y-m-01'), date('Y-m-d')]];
    if (!isset($map[$p])) $p = 'today';
    return [$p, $map[$p][0], $map[$p][1], $map[$p][2], array_map(fn($k, $v) => ['k' => $k, 'label' => $v[0]], array_keys($map), $map)];
}

function sl_rdJobRow(array $x): array
{
    $j = $x['j'];
    return ['id' => (int) $j->order_id, 'trk' => $j->order_prefix . $j->order_no, 'cust' => trim((string) $j->cust) ?: 'Contact not supplied', 'phone' => (string) ($j->cust_phone ?? ''), 'addr' => $j->addr ?: '—', 'pickup' => (int) $j->is_pickup, 'st' => $x['st'][0], 'cls' => $x['st'][1], 'state' => $x['st'][2],
        'fee' => sl_money($x['f']['fee']), 'rid' => sl_money($x['f']['rider']), 'sw' => sl_money($x['f']['swift']), 'pct' => (int) $x['f']['pct'], 'at' => $x['st'][2] === 'open' ? '' : sl_date($j->done_at, 'H:i'), 'kg' => (float) $j->total_weight, 'receiver' => $j->person_receives, 'value' => sl_money($j->total_order)];
}

function sl_driversData($user, $core): array
{
    [$p, $label, $from, $to, $periods] = sl_rdPeriod();
    $stats = sl_rdStats($from, $to);
    $q = trim((string) ($_GET['q'] ?? ''));
    $filter = (string) ($_GET['filter'] ?? 'all');
    $tot = ['assigned' => 0, 'done' => 0, 'failed' => 0, 'fees' => 0, 'rid' => 0, 'sw' => 0, 'onroute' => 0, 'avail' => 0, 'off' => 0];
    $rows = [];
    foreach ($stats as $id => $s) {
        $r = $s['rider'];
        $state = !(int) $r->active ? ['Suspended', 'overdue', 'off'] : ($s['open'] > 0 ? ['On route', 'onway', 'route'] : ['Available', 'delivered', 'avail']);
        $tot['assigned'] += $s['assigned']; $tot['done'] += $s['done']; $tot['failed'] += $s['failed']; $tot['fees'] += $s['fees']; $tot['rid'] += $s['rid']; $tot['sw'] += $s['sw'];
        $tot[$state[2] === 'route' ? 'onroute' : ($state[2] === 'avail' ? 'avail' : 'off')]++;
        if ($q !== '' && stripos($r->name . ' ' . $r->email . ' ' . $r->phone . ' ' . $r->enrollment, $q) === false) continue;
        if ($filter !== 'all' && $filter !== $state[2]) continue;
        $rows[] = ['id' => $id, 'name' => $r->name, 'ini' => sl_initials($r->name), 'veh' => trim(($r->vehicle_type ?: 'Vehicle') . ' ' . ($r->enrollment ?: '')), 'phone' => $r->phone ?: '—', 'mail' => $r->email, 'assigned' => $s['assigned'], 'done' => $s['done'], 'open' => $s['open'], 'failed' => $s['failed'],
            'fees' => sl_money($s['fees']), 'pct' => (int) $s['pct'], 'rid' => sl_money($s['rid']), 'sw' => sl_money($s['sw']), 'st' => $state[0], 'cls' => $state[1], 'active' => (int) $r->active, 'zone' => $r->zone, 'prog' => sl_pct($s['done'], max(1, $s['assigned'])), 'managed' => cdp_canManageUser($user, (int) $r->userlevel)];
    }
    $n = count($stats);
    $page = max(1, (int) ($_GET['page'] ?? 1)); $per = 24;
    $count = count($rows);
    return ['period' => $p, 'periods' => $periods, 'label' => $label, 'tot' => $tot, 'n' => $n, 'rows' => array_slice($rows, ($page - 1) * $per, $per), 'count' => $count, 'page' => $page, 'per' => $per, 'q' => $q, 'filter' => $filter,
        'avgFee' => $tot['done'] ? sl_money($tot['fees'] / $tot['done']) : sl_money(0), 'ridPct' => sl_pct($tot['rid'], max(0.01, $tot['fees'])), 'rules' => sl_rdRules(),
        'canMoney' => $user->cdp_hasPermission(['view_financial_overview', 'manage_rider_fees']) || sl_userIs('superadmin'), 'canEdit' => $user->cdp_hasPermission(['drivers_edit', 'edit_user']), 'canDelete' => $user->cdp_hasPermission('delete_user'), 'canAdd' => $user->cdp_hasPermission('add_user'), 'canDispatch' => $user->cdp_hasPermission(['dispatch_riders', 'assign_drivers', 'assign_driver_pickup'])];
}

function sl_riderDispatchData($user, $core): array
{
    $today = date('Y-m-d');
    $stats = sl_rdStats($today, $today);
    $rules = sl_rdRules();
    $q = trim((string) ($_GET['q'] ?? ''));
    $kind = (string) ($_GET['kind'] ?? 'all');
    $statuses = $kind === 'pickup' ? SL_RD_OPEN_PICKUP : ($kind === 'delivery' ? [5, 6, 32] : array_merge(SL_RD_OPEN_PICKUP, [5, 6, 32]));
    $w = 'o.is_deleted = 0 AND (o.driver_id IS NULL OR o.driver_id = 0) AND o.status_courier IN (' . implode(',', $statuses) . ')' . sl_rdScope();
    if (sl_tableExists('cdb_delivery_claims')) $w .= " AND NOT EXISTS (SELECT 1 FROM cdb_delivery_claims dc WHERE dc.module='air' AND dc.order_id=o.order_id)";
    if ($kind === 'pickup') $w .= ' AND o.is_pickup = 1';
    if ($kind === 'delivery') $w .= ' AND o.is_pickup = 0';
    $b = [];
    if ($q !== '') { $w .= " AND (CONCAT(o.order_prefix, o.order_no) LIKE :q OR CONCAT(s.fname,' ',s.lname) LIKE :q)"; $b[':q'] = "%$q%"; }
    $page = max(1, (int) ($_GET['page'] ?? 1)); $per = 20;
    $count = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order o LEFT JOIN cdb_users s ON s.id = o.sender_id WHERE $w", $b);
    $waiting = array_map(function ($j) use ($rules) {
        $f = sl_rdFee($j, (object) [], $rules);
        return ['id' => (int) $j->order_id, 'trk' => $j->order_prefix . $j->order_no, 'cust' => trim((string) $j->cust) ?: '—', 'area' => $j->addr ?: ($j->city ?: '—'), 'w' => (float) $j->total_weight . ' kg', 'pickup' => (int) $j->is_pickup, 'st' => $j->status_name, 'fee' => sl_money($f['fee']), 'age' => sl_ago($j->order_date)];
    }, sl_rows("SELECT o.order_id, o.order_prefix, o.order_no, o.is_pickup, o.total_weight, o.order_date, CONCAT(s.fname,' ',s.lname) AS cust, s.city, st.mod_style AS status_name,
            (SELECT ra.address FROM cdb_recipients_addresses ra WHERE ra.recipient_id = o.receiver_id LIMIT 1) AS addr
        FROM cdb_add_order o LEFT JOIN cdb_users s ON s.id = o.sender_id LEFT JOIN cdb_styles st ON st.id = o.status_courier WHERE $w ORDER BY o.is_pickup DESC, o.order_date LIMIT " . (($page - 1) * $per) . ", $per", $b));
    $riders = [];
    $tot = ['n' => 0, 'fees' => 0, 'rid' => 0, 'sw' => 0];
    foreach ($stats as $id => $s) {
        if (!(int) $s['rider']->active) continue;
        $open = array_values(array_filter($s['jobs'], fn($x) => $x['st'][2] === 'open'));
        $openFees = array_sum(array_map(fn($x) => $x['f']['fee'], $open));
        $tot['n'] += count($open); $tot['fees'] += $openFees; $tot['rid'] += array_sum(array_map(fn($x) => $x['f']['rider'], $open)); $tot['sw'] += array_sum(array_map(fn($x) => $x['f']['swift'], $open));
        $riders[] = ['id' => $id, 'name' => $s['rider']->name, 'ini' => sl_initials($s['rider']->name), 'veh' => $s['rider']->vehicle_type ?: 'Vehicle', 'zone' => $s['rider']->zone, 'n' => count($open), 'done' => $s['done'], 'fees' => sl_money($openFees), 'earned' => sl_money($s['rid']), 'pct' => (int) $s['pct'], 'pw' => 'width:' . sl_pct($s['done'], max(1, $s['done'] + count($open))) . '%', 'pk' => array_map('sl_rdJobRow', array_slice($open, 0, 8)), 'more' => max(0, count($open) - 8)];
    }
    usort($riders, fn($a, $b) => $a['n'] <=> $b['n']);
    $base = $rules['per_delivery'][0] ?? null;
    $vehTxt = implode(' · ', array_map(fn($v) => strtolower($v->label) . ' ' . (int) $v->rider_pct . '%', $rules['vehicle'] ?? []));
    return ['waiting' => $waiting, 'count' => $count, 'page' => $page, 'per' => $per, 'q' => $q, 'kind' => $kind, 'riders' => $riders, 'tot' => $tot, 'pct' => sl_pct($tot['rid'], max(0.01, $tot['fees'])),
        'ruleTxt' => 'Split rule · ' . ($vehTxt ?: 'base ' . ($base ? (int) $base->rider_pct : 70) . '%') . ' to the rider', 'canAssign' => $user->cdp_hasPermission(['dispatch_riders', 'assign_drivers', 'assign_driver_pickup']), 'canFees' => $user->cdp_hasPermission('manage_rider_fees')];
}

function sl_riderDetailData($user, $core, int $id): ?array
{
    $riders = sl_rdRiders();
    if (!isset($riders[$id])) return null;
    $r = $riders[$id];
    $today = date('Y-m-d');
    $t = sl_rdStats($today, $today, $id)[$id];
    $m = sl_rdStats(date('Y-m-01'), $today, $id)[$id];
    $run = array_map('sl_rdJobRow', $t['jobs']);
    $settle = sl_tableExists('cdb_rider_settlements') ? sl_rows('SELECT * FROM cdb_rider_settlements WHERE driver_id = :d ORDER BY period_start DESC LIMIT 12', [':d' => $id]) : [];
    $paid = 0.0; foreach ($settle as $s) if ($s->status === 'paid' && $s->period_start >= date('Y-m-01')) $paid += (float) $s->rider_share;
    $lastSettle = $settle[0]->period_end ?? null;
    $from = $lastSettle ? date('Y-m-d', strtotime($lastSettle . ' +1 day')) : date('Y-m-01');
    $due = $from <= $today ? sl_rdStats($from, $today, $id)[$id] : null;
    $act = cdp_activityTableReady() ? sl_rows("SELECT created_at, summary, action_label FROM cdb_activity_log WHERE (user_id = :d OR (module IN ('drivers','shipments','pickups') AND summary LIKE :n)) AND verb <> 'view' ORDER BY id DESC LIMIT 10", [':d' => $id, ':n' => '%' . $r->name . '%']) : [];
    return ['id' => $id, 'name' => $r->name, 'ini' => sl_initials($r->name), 'veh' => trim(($r->vehicle_type ?: 'Vehicle') . ' ' . ($r->enrollment ?: '')), 'phone' => $r->phone ?: '—', 'mail' => $r->email, 'since' => sl_date($r->created, 'M Y'), 'zone' => $r->zone ?: '—', 'active' => (int) $r->active,
        'st' => !(int) $r->active ? ['Suspended', 'overdue'] : ($t['open'] ? ['On route', 'onway'] : ['Available', 'delivered']),
        'today' => $t, 'month' => $m, 'run' => $run, 'runNo' => 'RS-' . date('md') . '-' . $id, 'pct' => (int) $t['pct'], 'settle' => array_map(fn($s) => ['id' => (int) $s->id, 'span' => sl_date($s->period_start, 'M j') . ' – ' . sl_date($s->period_end, 'M j'), 'n' => (int) $s->deliveries + (int) $s->pickups, 'gross' => sl_money($s->gross), 'rid' => sl_money($s->rider_share), 'status' => $s->status, 'paid' => $s->paid_at ? sl_date($s->paid_at) : ''], $settle),
        'paid' => sl_money($paid), 'due' => $due ? ['from' => $from, 'to' => $today, 'n' => $due['done'], 'fees' => $due['fees'], 'rid' => $due['rid'], 'txt' => sl_money($due['rid'])] : null, 'activity' => array_map(fn($a) => ['at' => sl_date($a->created_at, 'M j H:i'), 'what' => $a->summary ?: $a->action_label], $act),
        'canSettle' => $user->cdp_hasPermission('manage_rider_fees'), 'canEdit' => $user->cdp_hasPermission(['drivers_edit', 'edit_user']) && cdp_canManageUser($user, (int) $r->userlevel), 'canDispatch' => $user->cdp_hasPermission(['dispatch_riders', 'assign_drivers', 'assign_driver_pickup'])];
}

function sl_riderSettingsData($user, $core): array
{
    $rules = sl_rdRules(true);
    $riders = sl_rdRiders(true);
    $vehCount = []; $zoneCount = [];
    foreach ($riders as $r) { $vehCount[strtolower((string) $r->vehicle_type)] = ($vehCount[strtolower((string) $r->vehicle_type)] ?? 0) + 1; $zoneCount[strtolower((string) $r->zone)] = ($zoneCount[strtolower((string) $r->zone)] ?? 0) + 1; }
    $map = fn($r) => ['id' => (int) $r->id, 'key' => $r->rule_key, 'label' => $r->label, 'type' => $r->rule_type, 'amount' => number_format((float) $r->amount, 2, '.', ''), 'pct' => rtrim(rtrim(number_format((float) $r->rider_pct, 2, '.', ''), '0'), '.'), 'active' => (int) $r->active, 'cond' => json_decode((string) $r->condition_json, true) ?: [], 'updated' => $r->updated_at ? sl_ago($r->updated_at) : ''];
    $out = ['ready' => sl_tableExists('cdb_rider_fee_rules'), 'vehicles' => [], 'zones' => [], 'conds' => [], 'base' => []];
    foreach ($rules as $type => $list) foreach ($list as $r) {
        $x = $map($r);
        if ($type === 'vehicle') { $x['n'] = $vehCount[strtolower($r->label)] ?? 0; $out['vehicles'][] = $x; }
        elseif ($type === 'zone') { $x['n'] = $zoneCount[strtolower($r->label)] ?? 0; $out['zones'][] = $x; }
        elseif (in_array($type, ['per_delivery', 'per_pickup'], true)) $out['base'][] = $x;
        else $out['conds'][] = $x;
    }
    $last = cdp_activityTableReady() ? sl_rows("SELECT created_at, actor_name, summary FROM cdb_activity_log WHERE action = 'rider_fees' ORDER BY id DESC LIMIT 6") : [];
    $out['log'] = array_map(fn($l) => ['at' => sl_date($l->created_at, 'M j H:i'), 'who' => $l->actor_name, 'what' => $l->summary], $last);
    $out['condOn'] = count(array_filter($out['conds'], fn($c) => $c['active']));
    $out['riderN'] = count($riders);
    $out['canEdit'] = $user->cdp_hasPermission('manage_rider_fees');
    return $out;
}

function sl_driverAppData($user, $core): array
{
    $id = (int) $_SESSION['userid'];
    $riders = sl_rdRiders();
    $me = $riders[$id] ?? null;
    $d = ['isRider' => (bool) $me, 'name' => $me->name ?? '', 'ini' => sl_initials($me->name ?? '?')];
    if (!$me) return $d;
    $today = date('Y-m-d');
    $s = sl_rdStats($today, $today, $id)[$id];
    $jobs = array_map('sl_rdJobRow', $s['jobs']);
    // An in-progress delivery stays first, then the oldest assigned stop.
    usort($jobs, fn($a, $b) => (($a['st'] === 'On route' ? 0 : 1) <=> ($b['st'] === 'On route' ? 0 : 1)) ?: ($a['id'] <=> $b['id']));
    $seg = (string) ($_GET['seg'] ?? 'open');
    if (!in_array($seg, ['open', 'pickup', 'done', 'all'], true)) $seg = 'open';
    $d['seg'] = $seg;
    $d['segs'] = [['k' => 'open', 'label' => 'To do ' . $s['open']], ['k' => 'pickup', 'label' => 'Pickups'], ['k' => 'done', 'label' => 'Done ' . $s['done']], ['k' => 'all', 'label' => 'All']];
    $d['jobs'] = array_values(array_filter($jobs, fn($j) => $seg === 'all' || ($seg === 'open' && $j['state'] === 'open') || ($seg === 'done' && $j['state'] !== 'open') || ($seg === 'pickup' && $j['pickup'] && $j['state'] === 'open')));
    $openJobs = array_values(array_filter($jobs, fn($j) => $j['state'] === 'open'));
    $d['next'] = $openJobs[0] ?? null;
    $d['route'] = sl_riderRouteMap($openJobs);
    $d['left'] = $s['open']; $d['done'] = $s['done']; $d['earned'] = sl_money($s['rid']); $d['feesToday'] = sl_money($s['fees']); $d['pct'] = (int) $s['pct']; $d['total'] = count($jobs);
    $jobId = (int) ($_GET['job'] ?? 0);
    $d['job'] = null;
    foreach ($jobs as $j) if ($j['id'] === $jobId) $d['job'] = $j;
    $d['missingJob'] = $jobId > 0 && !$d['job'];
    $week = [];
    $wk = sl_rdStats(date('Y-m-d', strtotime('-6 day')), $today, $id)[$id];
    $byDay = [];
    foreach ($wk['jobs'] as $x) if ($x['st'][2] === 'done') { $k = substr((string) $x['j']->done_at, 0, 10); $byDay[$k] = ($byDay[$k] ?? 0) + $x['f']['rider']; }
    $mx = max(1, max($byDay ?: [0]));
    for ($i = 6; $i >= 0; $i--) { $k = date('Y-m-d', strtotime("-$i day")); $v = $byDay[$k] ?? 0; $week[] = ['l' => date('D', strtotime($k)), 'h' => 'height:' . max(4, sl_pct($v, $mx)) . '%', 't' => date('D', strtotime($k)) . ' ' . sl_money($v), 'cls' => $i === 0 ? 'now' : '']; }
    $d['week'] = $week; $d['weekEarned'] = sl_money($wk['rid']);
    $d['unread'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0', [':u' => $id]);
    $d['codesOn'] = function_exists('cdp_pickupCodeEnabled') ? cdp_pickupCodeEnabled() : true;
    $deliveries=[];
    if(sl_tableExists('cdb_delivery_requests'))$deliveries=sl_rows("SELECT * FROM cdb_delivery_requests WHERE driver_id=:u AND (state IN ('assigned','onroute','failed') OR (state='delivered' AND completed_at>=CURDATE())) ORDER BY FIELD(state,'onroute','assigned','failed','delivered'),approved_at,id",[':u'=>$id]);
    $pending=array_values(array_filter($deliveries,fn($r)=>in_array($r->state,['assigned','onroute'],true)));
    $deliveryNext=$pending[0]??null;$legacyNext=$d['next'];$hero=null;
    if($deliveryNext&&(!$legacyNext||$legacyNext['st']!=='On route'||$deliveryNext->state==='onroute')){
        $hero=['href'=>sl_route('DeliveryJob',['id'=>(int)$deliveryNext->id]),'name'=>$deliveryNext->contact_name,'address'=>$deliveryNext->address,'phone'=>$deliveryNext->contact_phone,'status'=>$deliveryNext->state==='onroute'?'On the way':'Ready to start','label'=>$deliveryNext->state==='onroute'?'Continue delivery':'Open next delivery','navigate'=>'https://www.google.com/maps/dir/?'.http_build_query(['api'=>1,'destination'=>$deliveryNext->lat.','.$deliveryNext->lng,'destination_place_id'=>$deliveryNext->place_id,'travelmode'=>'driving']),'parcels'=>count(json_decode($deliveryNext->parcel_json,true)?:[]),'type'=>'Delivery'];
    }elseif($legacyNext){$hero=['href'=>sl_route('DriverApp',['job'=>$legacyNext['id']]),'name'=>$legacyNext['cust']==='Contact not supplied'?'Your next stop':$legacyNext['cust'],'address'=>$legacyNext['addr'],'phone'=>$legacyNext['phone'],'status'=>$legacyNext['st'],'label'=>$legacyNext['pickup']?'Open next pickup':'Continue delivery','navigate'=>sl_riderNavigation($legacyNext['addr']),'parcels'=>1,'type'=>$legacyNext['pickup']?'Pickup':'Delivery'];}
    $d['pwaRun']=['next'=>$hero,'stops'=>count($pending)+count($openJobs),'parcels'=>array_sum(array_map(fn($r)=>count(json_decode($r->parcel_json,true)?:[]),$pending))+count($openJobs),'completed'=>$s['done']+count(array_filter($deliveries,fn($r)=>$r->state==='delivered'))];

    return $d;
}
