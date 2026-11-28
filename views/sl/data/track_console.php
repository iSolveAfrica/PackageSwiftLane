<?php
/**
 * Live tracking console (the dark map + tracking list + overview panel).
 * Rendered by views/sl/pages/partials/track_console.php on the Overview, Client dashboard,
 * Shipment tracking and Consolidations pages.
 *
 * sl_trackConsoleData($user, $core, $source) with $source:
 *   'shipments'       open air shipments (cdb_add_order) + sea packages (cdb_customers_packages)
 *   'consolidations'  open air (cdb_consolidate) + sea (cdb_consolidate_packages) consolidations
 *   'all'             everything a staff member may track: shipments, packages and both kinds of consolidation
 *   'mine'            the signed-in customer's own shipments, packages and consolidations (no staff permission needed)
 * Rows are always limited by sl_opsScope() (customer / rider / agency scope) and by the viewer's list
 * permissions, so every role only sees what it may see elsewhere in the app.
 *
 * Map: assets/swiftlane/maps/world-dots.svg is an equirectangular map of lon -135..75, lat -45..75 on a
 * 1000 x 571.43 canvas. sl_tcXY() is that same projection; keep the two in step.
 */
require_once __DIR__ . '/ops_common.php';

const SL_TC_W = 1000.0;
const SL_TC_H = 571.43;

/** Longitude/latitude to the map canvas. */
function sl_tcXY(float $lat, float $lon): array
{
    return [round(($lon + 135) / 210 * SL_TC_W, 1), round((75 - $lat) / 120 * SL_TC_H, 1)];
}

/**
 * The company's lanes. Air follows the published journey (helpers/air_journey.php: JFK -> London -> Accra);
 * sea sails New York -> Tema. Edit here to add a lane; the map, chips and progress follow.
 */
function sl_tcLanes(): array
{
    $air = function_exists('cdp_airRoute') ? cdp_airRoute() : null;
    return [
        'air' => [
            'from' => ['code' => 'JFK', 'city' => $air['origin']['city'] ?? 'New York', 'country' => 'USA', 'lat' => 40.64, 'lon' => -73.78],
            'via' => null, // direct flight
            'to' => ['code' => 'ACC', 'city' => $air['destination']['city'] ?? 'Accra', 'country' => 'Ghana', 'lat' => 5.60, 'lon' => -0.17],
        ],
        'sea' => [
            'from' => ['code' => 'NYC', 'city' => 'New York', 'country' => 'USA', 'lat' => 40.68, 'lon' => -74.04],
            'via' => null,
            'to' => ['code' => 'TEM', 'city' => 'Tema', 'country' => 'Ghana', 'lat' => 5.63, 'lon' => 0.01],
        ],
    ];
}

/** Ambient network glows on the map (decorative, unlabelled): the cities the lanes touch plus regional hubs. */
function sl_tcGlows(): array
{
    $pts = [[25.79, -80.29], [33.94, -118.4], [6.52, 3.37], [25.25, 55.36], [-26.14, 28.24], [-23.43, -46.47], [9.07, -79.38], [48.35, 11.78], [41.0, 28.9], [19.43, -99.13], [64.13, -21.9]];
    return array_map(fn($p) => sl_tcXY($p[0], $p[1]), $pts);
}

/** Quadratic arc between two canvas points, bowed "north" like a great-circle route. */
function sl_tcLeg(array $a, array $b): array
{
    $mx = ($a[0] + $b[0]) / 2;
    $my = ($a[1] + $b[1]) / 2;
    $dx = $b[0] - $a[0];
    $dy = $b[1] - $a[1];
    $len = sqrt($dx * $dx + $dy * $dy) ?: 1;
    // perpendicular, flipped so the bow points up the map
    $nx = -$dy / $len;
    $ny = $dx / $len;
    if ($ny > 0) { $nx = -$nx; $ny = -$ny; }
    $bow = $len * 0.22;
    return ['a' => $a, 'c' => [round($mx + $nx * $bow, 1), round($my + $ny * $bow, 1)], 'b' => $b, 'len' => $len];
}

function sl_tcBez(array $l, float $t): array
{
    $u = 1 - $t;
    $x = $u * $u * $l['a'][0] + 2 * $u * $t * $l['c'][0] + $t * $t * $l['b'][0];
    $y = $u * $u * $l['a'][1] + 2 * $u * $t * $l['c'][1] + $t * $t * $l['b'][1];
    $tx = 2 * $u * ($l['c'][0] - $l['a'][0]) + 2 * $t * ($l['b'][0] - $l['c'][0]);
    $ty = 2 * $u * ($l['c'][1] - $l['a'][1]) + 2 * $t * ($l['b'][1] - $l['c'][1]);
    return [round($x, 1), round($y, 1), round(rad2deg(atan2($ty, $tx)), 1)];
}

/** Route geometry for a lane at progress $p (0..1): path, travelled path, vehicle point and heading. */
function sl_tcGeometry(array $lane, float $p): array
{
    $pts = [sl_tcXY($lane['from']['lat'], $lane['from']['lon'])];
    if ($lane['via']) $pts[] = sl_tcXY($lane['via']['lat'], $lane['via']['lon']);
    $pts[] = sl_tcXY($lane['to']['lat'], $lane['to']['lon']);
    $legs = [];
    for ($i = 0; $i < count($pts) - 1; $i++) $legs[] = sl_tcLeg($pts[$i], $pts[$i + 1]);
    $total = array_sum(array_column($legs, 'len')) ?: 1;
    $path = '';
    foreach ($legs as $i => $l) {
        $path .= ($i ? '' : 'M' . $l['a'][0] . ' ' . $l['a'][1]) . 'Q' . $l['c'][0] . ' ' . $l['c'][1] . ' ' . $l['b'][0] . ' ' . $l['b'][1];
    }
    // locate the vehicle
    $want = max(0.0, min(1.0, $p)) * $total;
    $acc = 0.0;
    $veh = [$pts[0][0], $pts[0][1], 0];
    $done = 'M' . $pts[0][0] . ' ' . $pts[0][1];
    foreach ($legs as $l) {
        if ($want <= $acc + $l['len'] + 0.001) {
            $t = ($want - $acc) / $l['len'];
            $veh = sl_tcBez($l, $t);
            // split the quadratic at t (de Casteljau) for the travelled part
            $c1 = [round($l['a'][0] + ($l['c'][0] - $l['a'][0]) * $t, 1), round($l['a'][1] + ($l['c'][1] - $l['a'][1]) * $t, 1)];
            $done .= 'Q' . $c1[0] . ' ' . $c1[1] . ' ' . $veh[0] . ' ' . $veh[1];
            break;
        }
        $done .= 'Q' . $l['c'][0] . ' ' . $l['c'][1] . ' ' . $l['b'][0] . ' ' . $l['b'][1];
        $acc += $l['len'];
    }
    $viaP = count($legs) > 1 ? $legs[0]['len'] / $total : null;
    return ['path' => $path, 'done' => $done, 'veh' => $veh, 'pts' => $pts, 'viaP' => $viaP];
}

/** Progress (0..1) along the lane from the status and the departure / ETA dates. */
function sl_tcProgress(int $status, ?string $start, ?string $eta): float
{
    $stage = sl_stageOf($status);
    if ($stage >= 4) return 1.0;
    if ($stage === 1) return 0.03;
    if ($stage === 2) return 0.08;
    $s = $start ? strtotime($start) : 0;
    $e = $eta ? strtotime($eta . (strlen($eta) <= 10 ? ' 18:00' : '')) : 0;
    if ($s && $e && $e > $s) return max(0.12, min(0.94, (time() - $s) / ($e - $s)));
    return 0.5;
}

/** "2D 12H 44M" since a timestamp. */
function sl_tcSince(?string $from): string
{
    $s = $from ? strtotime($from) : 0;
    if (!$s || $s > time()) return '—';
    $m = (int) floor((time() - $s) / 60);
    return floor($m / 1440) . 'D ' . floor(($m % 1440) / 60) . 'H ' . ($m % 60) . 'M';
}

/** Filters the full Live Tracking page offers (key => label). */
function sl_tcFilters(): array
{
    return ['all' => 'Everything moving', 'air' => 'Air', 'sea' => 'Sea', 'con' => 'Consolidations', 'late' => 'Delayed', 'ready' => 'Arrived · ready', 'done' => 'Delivered'];
}

/**
 * The record kinds a viewer may track: [mode, table, prefix col, number col, date col, detail table, kind].
 * $source: shipments | consolidations | all (staff) | mine (customer).
 */
function sl_tcKinds($user, string $source): array
{
    $client = $source === 'mine';
    // Riders see what is assigned to them (sl_opsScope limits rows to driver_id), no list permission needed.
    $rider = function_exists('sl_userIs') && sl_userIs('driver');
    $k = [];
    if ($source !== 'consolidations') {
        if ($client || $rider || $user->cdp_hasPermission(['view_shipment_list', 'view_general_shipments'])) $k[] = ['air', 'cdb_add_order', 'order_prefix', 'order_no', 'order_date', null, 'shipment'];
        if ($client || $rider || $user->cdp_hasPermission(['view_package_list', 'view_general_package_records'])) $k[] = ['sea', 'cdb_customers_packages', 'order_prefix', 'order_no', 'order_date', null, 'package'];
    }
    if ($source !== 'shipments') {
        if ($client || $rider || $user->cdp_hasPermission('view_consolidate_list')) $k[] = ['air', 'cdb_consolidate', 'c_prefix', 'c_no', 'c_date', 'cdb_consolidate_detail', 'consolidation'];
        if ($client || $rider || $user->cdp_hasPermission('view_consolidate_package_list')) $k[] = ['sea', 'cdb_consolidate_packages', 'c_prefix', 'c_no', 'c_date', 'cdb_consolidate_packages_detail', 'consolidation'];
    }
    return $k;
}

/** Freight leg of a record from Waybills & Manifests: waybill, container, seal, voyage, vessel, departs / arrives. */
function sl_tcFreight(string $code, ?string $conCode = null): array
{
    static $ok = null;
    if ($ok === null) $ok = sl_tableExists('cdb_waybills') && sl_tableExists('cdb_waybill_items');
    if (!$ok) return [];
    $codes = array_values(array_filter([$code, $conCode]));
    $b = [];
    $inA = []; $inB = [];
    foreach ($codes as $i => $c) { $b[':a' . $i] = $c; $b[':b' . $i] = $c; $inA[] = ':a' . $i; $inB[] = ':b' . $i; }
    $sched = sl_tableExists('cdb_freight_schedules');
    $w = sl_row('SELECT w.waybill_no, w.container_no, w.seal_no, w.carrier' . ($sched ? ', s.voyage_no, s.carrier_name, s.departs_at, s.arrives_at, s.arrived_at, s.status AS sstatus' : '') . '
        FROM cdb_waybills w ' . ($sched ? 'LEFT JOIN cdb_freight_schedules s ON s.id = w.schedule_id ' : '') . '
        WHERE w.consolidation_no IN (' . implode(',', $inA) . ') OR w.id IN (SELECT i.waybill_id FROM cdb_waybill_items i WHERE i.order_track IN (' . implode(',', $inB) . "))
        ORDER BY (w.kind = 'master') DESC, w.id DESC LIMIT 1", $b);
    if (!$w) return [];
    return array_filter([
        'waybill' => (string) $w->waybill_no, 'container' => (string) ($w->container_no ?? ''), 'seal' => (string) ($w->seal_no ?? ''),
        'voyage' => (string) ($w->voyage_no ?? ''), 'vessel' => (string) (($w->carrier_name ?? '') ?: ($w->carrier ?? '')),
        'departs' => !empty($w->departs_at) ? sl_date($w->departs_at, 'M j, H:i') : '', 'arrives' => !empty($w->arrived_at) ? 'Arrived ' . sl_date($w->arrived_at, 'M j') : (!empty($w->arrives_at) ? sl_date($w->arrives_at, 'M j, H:i') : ''),
        'eta' => (string) (($w->arrived_at ?? '') ?: ($w->arrives_at ?? '')),
        'leg' => (string) ($w->sstatus ?? ''),
    ], fn($v) => $v !== '');
}

/**
 * Rows for the console. Each item carries everything its panel needs, so switching in the browser
 * needs no further request.
 *
 * $opt: q (search, server side: code, customer, store tracking, parcels inside a consolidation),
 *       filter (sl_tcFilters key), plus the $limit argument.
 */
function sl_trackConsoleData($user, $core, string $source = 'shipments', int $limit = 10, array $opt = []): array
{
    $client = $source === 'mine';
    $uid = (int) ($_SESSION['userid'] ?? 0);
    $money = $client || (function_exists('cdp_canViewMoney') && cdp_canViewMoney($user));
    $q = trim((string) ($opt['q'] ?? ''));
    $f = (string) ($opt['filter'] ?? 'all');
    // 'loading' (still at the origin warehouse) and 'transit' are used by the Consolidations statistics only
    if (!isset(sl_tcFilters()[$f]) && !in_array($f, ['loading', 'transit'], true)) $f = 'all';
    $onlyMode = in_array($opt['mode'] ?? '', ['air', 'sea'], true) ? (string) $opt['mode'] : '';
    $kinds = sl_tcKinds($user, $source);
    $out = ['source' => $source, 'items' => [], 'total' => 0, 'shown' => 0, 'q' => $q, 'filter' => $f,
        'counts' => ['all' => 0, 'air' => 0, 'sea' => 0, 'con' => 0, 'late' => 0], 'tally' => [], 'glows' => sl_tcGlows()];
    if (!$kinds) return $out;

    $couriers = [];
    foreach (sl_rows('SELECT id, name_com FROM cdb_courier_com') as $c) $couriers[(int) $c->id] = $c->name_com;
    $lanes = sl_tcLanes();
    $rows = [];
    foreach ($kinds as [$mode, $tbl, $px, $nx, $dcol, $detail, $kind]) {
        $idc = $detail ? 'consolidate_id' : 'order_id';
        $scope = $client ? " AND a.sender_id = $uid" : sl_opsScope('a');
        $del = $tbl === 'cdb_add_order' ? ' AND a.is_deleted = 0' : '';
        $openW = 'a.status_courier NOT IN (8,15,21,12)';
        // page-wide tallies (open records of each kind, whatever the filter)
        $tk = $kind === 'consolidation' ? $mode . '_con' : $mode;
        $out['tally'][$tk] = ($out['tally'][$tk] ?? 0) + (int) sl_scalar("SELECT COUNT(*) FROM $tbl a WHERE $openW$del$scope");
        // does this kind take part in the filter?
        if ($onlyMode !== '' && $mode !== $onlyMode) continue;
        if ($f === 'air' && $mode !== 'air') continue;
        if ($f === 'sea' && $mode !== 'sea') continue;
        if ($f === 'con' && $kind !== 'consolidation') continue;
        $eta = sl_columnExists($tbl, 'estimated_eta') ? 'a.estimated_eta' : 'NULL';
        $statusW = $f === 'done' ? 'a.status_courier IN (8,15)' : ($f === 'ready' ? 'a.status_courier IN (6,32)' : ($q !== '' ? 'a.status_courier <> 21' : $openW));
        // 'late' = not yet arrived (stage < 4) and past ETA, with or without a search term;
        // matches the per-item $late rule and the Overview "running late" brief count
        if ($f === 'loading') $statusW = 'a.status_courier NOT IN (3,5,7,2,4,6,32,8,15,21,12)';
        if ($f === 'transit') $statusW = 'a.status_courier IN (3,5,7)';
        if ($f === 'late') $statusW = 'a.status_courier NOT IN (6,32,8,15,21,12)' . ($eta === 'NULL' ? ' AND 1 = 0' : " AND $eta IS NOT NULL AND $eta < CURDATE()");
        $b = [];
        $qW = '';
        if ($q !== '') {
            $b = [':q1' => "%$q%", ':q2' => "%$q%"];
            $qW = " AND (CONCAT(a.$px, a.$nx) LIKE :q1 OR a.sender_id IN (SELECT u.id FROM cdb_users u WHERE CONCAT_WS(' ', u.fname, u.lname, u.email, u.phone, u.locker) LIKE :q2)";
            if ($detail) { $qW .= " OR a.consolidate_id IN (SELECT d.consolidate_id FROM $detail d WHERE CONCAT(d.order_prefix, d.order_no) LIKE :q3)"; $b[':q3'] = "%$q%"; }
            elseif ($tbl === 'cdb_customers_packages') { $qW .= ' OR a.tracking_purchase LIKE :q3'; $b[':q3'] = "%$q%"; }
            $qW .= ')';
        }
        $where = "$statusW$del$scope$qW";
        $out['total'] += (int) sl_scalar("SELECT COUNT(*) FROM $tbl a WHERE $where", $b);
        $cnt = $detail ? "(SELECT COUNT(*) FROM $detail d WHERE d.consolidate_id = a.consolidate_id)" : '0';
        $vol = $detail ? "(SELECT COALESCE(SUM(d.length * d.width * d.height), 0) FROM $detail d WHERE d.consolidate_id = a.consolidate_id)" : '0';
        $extra = $tbl === 'cdb_customers_packages' ? 'a.tracking_purchase AS store_trk, a.provider_purchase AS store, a.total_declared_value AS declared, a.is_consolidate AS in_con' : "'' AS store_trk, '' AS store, 0 AS declared, 0 AS in_con";
        $extra .= $detail ? ', a.receiver_id, a.seals_package AS seals' : ', 0 AS receiver_id, \'\' AS seals';
        foreach (sl_rows("SELECT a.$idc AS id, CONCAT(a.$px, a.$nx) AS code, a.$dcol AS dt, a.status_courier AS st, a.total_weight AS w, a.total_order AS amt,
                a.sub_total AS sub, a.total_tax AS tax, a.status_invoice AS inv, a.order_courier AS courier, a.order_service_options AS svc, a.sender_id, a.driver_id, $eta AS eta, $cnt AS n, $vol AS vol, $extra
            FROM $tbl a WHERE $where ORDER BY a.$dcol DESC, a.$idc DESC LIMIT " . (int) $limit, $b) as $r) {
            $r->mode = $mode;
            $r->tbl = $tbl;
            $r->kind = $kind;
            $r->detail = $detail;
            $rows[] = $r;
        }
    }
    usort($rows, fn($a, $b) => strcmp((string) $b->dt, (string) $a->dt));
    $rows = array_slice($rows, 0, $limit);
    $senders = sl_usersById(array_merge(array_map(fn($r) => $r->sender_id, $rows), array_map(fn($r) => $r->receiver_id, $rows), array_map(fn($r) => $r->driver_id, $rows)));
    $hasFlight = sl_columnExists('cdb_courier_track', 'flight_no');
    // parcels that sit inside a consolidation
    $inCon = ['air' => sl_consolidationsFor(array_map(fn($r) => $r->id, array_filter($rows, fn($r) => $r->kind === 'shipment')), false),
        'sea' => sl_consolidationsFor(array_map(fn($r) => $r->id, array_filter($rows, fn($r) => $r->kind === 'package')), true)];
    // latest flight / AWB per air code in one query instead of one lookup per row
    $flights = [];
    if ($hasFlight) {
        $fb = [];
        foreach (array_values(array_unique(array_map(fn($r) => (string) $r->code, array_filter($rows, fn($r) => $r->mode === 'air')))) as $i => $c) $fb[':fc' . $i] = $c;
        if ($fb) {
            foreach (sl_rows('SELECT order_track, flight_no, awb_no FROM cdb_courier_track WHERE order_track IN (' . implode(', ', array_keys($fb)) . ') AND (flight_no <> \'\' OR awb_no <> \'\') ORDER BY t_date DESC', $fb) as $fl) {
                $fk = mb_strtoupper((string) $fl->order_track);
                if (!isset($flights[$fk])) $flights[$fk] = $fl;
            }
        }
    }
    $services = [];
    if (sl_tableExists('cdb_shipping_mode')) foreach (sl_rows('SELECT id, ship_mode FROM cdb_shipping_mode') as $sm) $services[(int) $sm->id] = $sm->ship_mode;

    foreach ($rows as $r) {
        $lane = $lanes[$r->mode];
        $st = (int) $r->st;
        $isCon = $r->kind === 'consolidation';
        $id = (int) $r->id;
        $hist = sl_trackHistory((string) $r->code);
        $con = $isCon ? null : ($inCon[$r->mode][$id] ?? null);
        $fr = sl_tcFreight((string) $r->code, $con['code'] ?? null);
        $eta = $r->eta ?: (!empty($fr['eta']) ? substr($fr['eta'], 0, 10) : null);
        $departed = null;
        foreach (array_reverse($hist) as $h) { if (sl_stageOf($h->status_courier) >= 3) { $departed = $h->t_date; break; } }
        $start = $departed ?: $r->dt;
        $p = sl_tcProgress($st, $start, $eta);
        $g = sl_tcGeometry($lane, $p);
        $etaTs = $eta ? strtotime($eta . ' 23:59:59') : 0;
        $arrived = sl_stageOf($st) >= 4;
        $late = !$arrived && $etaTs && $etaTs < time();
        $flight = '';
        $awb = '';
        if ($hasFlight && $r->mode === 'air') {
            $fl = $flights[mb_strtoupper((string) $r->code)] ?? null;
            $flight = (string) ($fl->flight_no ?? '');
            $awb = (string) ($fl->awb_no ?? '');
        }
        if ($flight === '' && $r->mode === 'air' && !empty($fr['voyage'])) $flight = $fr['voyage'];
        if ($awb === '' && $r->mode === 'air' && !empty($fr['waybill'])) $awb = $fr['waybill'];
        if ($client) {
            $href = sl_route('PublicTracking', ['order_track' => $r->code]);
        } elseif ($isCon) {
            $href = sl_route('ConsolidationDetail', ['id' => $id, 'type' => $r->mode]);
        } else {
            $href = $r->mode === 'air' ? sl_route('ShipmentDetail', ['id' => $id]) : sl_route('PackageDetail', ['id' => $id]);
        }
        // documents the viewer may open
        $docs = [];
        if ($client) {
            // customers: their own receipt and tracking page only
            $docs[] = ['Tracking page', $href, 'i-route'];
        } elseif ($isCon) {
            require_once __DIR__ . '/consolidations.php';
            $M = sl_conMode($r->mode);
            if ($user->cdp_hasPermission($M['p']['print'])) $docs[] = ['Manifest', $M['url']['print'] . '?id=' . $id, 'i-file'];
            if ($user->cdp_hasPermission($M['p']['label'])) $docs[] = ['Label', $M['url']['label'] . '?id=' . $id, 'i-tag'];
            if ($user->cdp_hasPermission($M['p']['print']) && !$client) $docs[] = ['Packing list', 'packing_list.php?id=' . $id . '&type=' . $r->mode, 'i-layers'];
        } elseif ($r->mode === 'air') {
            if ($user->cdp_hasPermission('print_shipment')) $docs[] = ['Invoice', 'print_inv_ship.php?id=' . $id, 'i-receipt'];
            if ($user->cdp_hasPermission('print_label')) $docs[] = ['Label 4×6', sl_route('Label4x6', ['id' => $id]), 'i-tag'];
        } else {
            if ($user->cdp_hasPermission('print_package')) $docs[] = ['Receipt', 'print_invoice_package.php?id=' . $id, 'i-receipt'];
            if ($user->cdp_hasPermission('print_label')) $docs[] = ['Label', 'print_label_package.php?id=' . $id, 'i-tag'];
        }
        if (!$client && $con) $docs[] = ['Consolidation ' . $con['code'], sl_route('ConsolidationDetail', ['id' => $con['id'], 'type' => $r->mode]), 'i-layers'];
        // what the viewer can do with this record, for the details pop-up
        $acts = [];
        $closed = in_array($st, [8, 15, 21], true);
        if ($client) {
            $acts[] = ['Tracking page', $href, 'i-route', true];
        } elseif ($isCon) {
            require_once __DIR__ . '/consolidations.php';
            $M = sl_conMode($r->mode);
            $acts[] = ['Open consolidation', $href, 'i-eye', false];
            if (!$closed && $user->cdp_hasPermission($M['p']['edit'])) $acts[] = ['Edit', $M['url']['edit'] . '?id=' . $id, 'i-edit', true];
            if ($user->cdp_hasPermission($M['p']['track'])) $acts[] = ['Add tracking event', $M['url']['track'] . '?id=' . $id, 'i-refresh', false];
            if (!$closed && $user->cdp_hasPermission($M['p']['deliver'])) $acts[] = ['Deliver', $M['url']['deliver'] . '?id=' . $id, 'i-check', false];
            if (!$closed && $user->cdp_hasPermission($M['p']['driver'])) $acts[] = ['Assign rider', $href . '#driver', 'i-truck', false];
        } elseif ($r->mode === 'air') {
            $acts[] = ['Open shipment', $href, 'i-eye', false];
            if (!$closed && $user->cdp_hasPermission('edit_shipment')) $acts[] = ['Edit', 'courier_edit.php?id=' . $id, 'i-edit', true];
            if ($user->cdp_hasPermission('track_shipment')) $acts[] = ['Add tracking event', sl_route('ShipmentTracking', ['id' => $id]), 'i-refresh', false];
            if (!$closed && $user->cdp_hasPermission('deliver_shipment')) $acts[] = ['Deliver', 'courier_deliver_shipment.php?id=' . $id, 'i-check', false];
            if (!$closed && $user->cdp_hasPermission('assign_drivers')) $acts[] = ['Assign rider', $href . '#driver', 'i-truck', false];
        } else {
            $acts[] = ['Open package', $href, 'i-eye', false];
            if (!$closed && $user->cdp_hasPermission('edit_package')) $acts[] = ['Edit', 'customer_package_edit.php?id=' . $id, 'i-edit', true];
            if ($user->cdp_hasPermission(['track_package', 'track_shipment'])) $acts[] = ['Add tracking event', 'customer_package_tracking.php?id=' . $id, 'i-refresh', false];
            if (!$closed && $user->cdp_hasPermission('deliver_package')) $acts[] = ['Deliver', 'customer_package_deliver.php?id=' . $id, 'i-check', false];
            if (!$closed && $user->cdp_hasPermission('assign_driver_package')) $acts[] = ['Assign rider', $href . '#driver', 'i-truck', false];
        }
        if (!$client) $acts[] = ['Customer tracking page', sl_route('PublicTracking', ['order_track' => $r->code]), 'i-route', false];
        [$payL, $payC] = sl_payPill($r->inv);
        $u = $senders[(int) $r->sender_id] ?? null;
        $drv = $senders[(int) $r->driver_id] ?? null;
        $rcv = $senders[(int) $r->receiver_id] ?? null;
        $daysOpen = $r->dt ? (int) floor((time() - strtotime($r->dt)) / 86400) : 0;
        $etaDelta = $etaTs ? (int) round(($etaTs - time()) / 86400) : null;
        // priority score: late, unpaid, long dwell, held
        $score = ($late ? 3 : 0) + (in_array((int) $r->inv, [1, 19], true) ? 0 : 1) + ($daysOpen > 14 ? 1 : 0) + ($st === 28 || $st === 29 ? 2 : 0);
        $where = $arrived ? $lane['to']['city'] : ($p < 0.1 ? $lane['from']['city'] : ($g['viaP'] !== null && abs($p - $g['viaP']) < 0.12 ? $lane['via']['city'] : ($r->mode === 'sea' ? 'Atlantic Ocean' : ($g['viaP'] !== null && $p < $g['viaP'] ? 'Over the Atlantic' : 'En route to ' . $lane['to']['city']))));
        // contents of a consolidation (first 25 parcels)
        $contents = [];
        if ($isCon) {
            $ptbl = $r->mode === 'sea' ? 'cdb_customers_packages' : 'cdb_add_order';
            foreach (sl_rows("SELECT d.order_id, CONCAT(d.order_prefix, d.order_no) AS code, d.weight, d.length, d.width, d.height, o.status_courier, o.sender_id
                FROM {$r->detail} d LEFT JOIN $ptbl o ON o.order_id = d.order_id WHERE d.consolidate_id = :c ORDER BY d.detail_id LIMIT 25", [':c' => $id]) as $c) {
                $contents[] = ['code' => (string) $c->code, 'w' => sl_weight($c->weight), 'dim' => ((float) $c->length && (float) $c->width && (float) $c->height) ? (float) $c->length . '×' . (float) $c->width . '×' . (float) $c->height : '—',
                    'st' => $c->status_courier !== null ? sl_statusLabel($c->status_courier) : '—', 'cls' => $c->status_courier !== null ? sl_statusClass((int) $c->status_courier) : '', 'sender' => (int) $c->sender_id];
            }
            if (!$client) {
                $cu = sl_usersById(array_column($contents, 'sender'));
                foreach ($contents as &$c) $c['who'] = sl_userName($cu[$c['sender']] ?? null);
                unset($c);
            }
        }
        // the details grid, by kind
        $det = [];
        $det[] = ['Type', $isCon ? ($r->mode === 'sea' ? 'Sea consolidation' : 'Air consolidation') : ($r->mode === 'sea' ? 'Sea package' : 'Air shipment')];
        if (!$client || $isCon) $det[] = [$isCon ? 'Shipper' : 'Customer', sl_userName($u) . (!empty($u->locker) ? ' · ' . $u->locker : '')];
        if ($isCon && $rcv) $det[] = ['Consignee', sl_userName($rcv)];
        if (isset($services[(int) $r->svc])) $det[] = ['Service', $services[(int) $r->svc]];
        $det[] = ['Weight', sl_weight($r->w)];
        if ($isCon) {
            $det[] = ['Parcels inside', (int) $r->n];
            if ((float) $r->vol > 0) $det[] = ['Volume', number_format((float) $r->vol / 1728, 1) . ' ft³'];
        }
        if ($r->mode === 'air') {
            if ($awb !== '') $det[] = ['Air waybill', $awb];
            if ($flight !== '') $det[] = ['Flight', $flight];
        } else {
            if (!empty($fr['vessel'])) $det[] = ['Vessel / line', $fr['vessel']];
            if (!empty($fr['voyage'])) $det[] = ['Voyage', $fr['voyage']];
            if (!empty($fr['waybill'])) $det[] = ['Bill of lading', $fr['waybill']];
        }
        if (!empty($fr['container'])) $det[] = ['Container', $fr['container']];
        $seal = trim((string) ($fr['seal'] ?? '') ?: (string) $r->seals);
        if ($seal !== '') $det[] = ['Seal', $seal];
        if (!empty($fr['departs'])) $det[] = [$r->mode === 'sea' ? 'Sailed' : 'Departs', $fr['departs']];
        if (!empty($fr['arrives'])) $det[] = [$r->mode === 'sea' ? 'Port arrival' : 'Lands', $fr['arrives']];
        if ($r->kind === 'package') {
            if (trim((string) $r->store) !== '') $det[] = ['Store', (string) $r->store];
            if (trim((string) $r->store_trk) !== '') $det[] = ['Store tracking', (string) $r->store_trk];
            if ($money && (float) $r->declared > 0) $det[] = ['Declared value', sl_money($r->declared)];
        }
        if ($con) $det[] = ['In consolidation', $con['code'] . ' · ' . sl_statusLabel($con['status'])];
        $det[] = ['Registered', sl_date($r->dt)];

        $mk = $isCon ? 'con' : $r->mode;
        $out['counts']['all']++;
        $out['counts'][$r->mode]++;
        if ($isCon) $out['counts']['con']++;
        if ($late) $out['counts']['late']++;
        $out['items'][] = [
            'key' => $r->kind . '-' . $r->mode . '-' . $id, 'id' => $id, 'code' => (string) $r->code, 'mode' => $r->mode, 'kind' => $r->kind,
            'modeL' => $r->mode === 'sea' ? 'Sea' : 'Air', 'kindL' => $isCon ? 'Consolidation' : ($r->mode === 'sea' ? 'Package' : 'Shipment'), 'href' => $href,
            'st' => sl_statusLabel($st), 'cls' => sl_statusClass($st), 'late' => $late, 'arrived' => $arrived,
            'from' => $lane['from'], 'via' => $lane['via'], 'to' => $lane['to'],
            'carrier' => $couriers[(int) $r->courier] ?? (!empty($fr['vessel']) ? $fr['vessel'] : ($r->mode === 'sea' ? 'Ocean freight' : 'Air cargo')),
            'vehicle' => $r->mode === 'sea' ? ($isCon ? 'Container' . (!empty($fr['container']) ? ' ' . $fr['container'] : '') : 'Container vessel') : ($isCon ? 'Air consolidation' : 'Cargo freighter'),
            'flight' => $flight, 'awb' => $awb, 'freight' => $fr,
            'customer' => $isCon ? ((int) $r->n . ' parcel' . ((int) $r->n === 1 ? '' : 's')) : ($client ? sl_weight($r->w) : sl_userName($u)),
            'search' => strtolower(implode(' ', array_filter([$r->code, sl_userName($u), $u->locker ?? '', $r->store_trk, $fr['container'] ?? '', $fr['waybill'] ?? '', $con['code'] ?? '', implode(' ', array_column($contents, 'code'))]))),
            'weight' => sl_weight($r->w), 'created' => sl_date($r->dt), 'eta' => $eta ? sl_date($eta) : 'Not set', 'etaShort' => $eta ? strtoupper(sl_date($eta, 'M j, Y')) : '—',
            'onway' => $departed || sl_stageOf($st) >= 3 ? sl_tcSince($start) : 'Not departed',
            'transit' => $etaTs && $r->dt ? max(1, (int) round(($etaTs - strtotime($r->dt)) / 86400)) . 'D' : '—',
            'estimate' => $arrived ? 'Arrived' : ($late ? 'Late' : ($eta ? 'On time' : 'No ETA')), 'estCls' => $arrived ? 'ok' : ($late ? 'bad' : ($eta ? 'ok' : 'mut')),
            // Consolidations are operational records: no money on them anywhere (billing lives in Finance).
            'money' => $money && !$isCon, 'total' => $isCon ? '' : sl_money($r->amt), 'sub' => $isCon ? '' : sl_money($r->sub), 'tax' => $isCon ? '' : sl_money($r->tax), 'pay' => $isCon ? '' : $payL, 'payCls' => $payC === 'paid' ? 'ok' : ($payC === 'overdue' ? 'bad' : 'warn'),
            'pct' => (int) round($p * 100), 'where' => $where,
            'geo' => $g, 'docs' => $docs, 'actions' => $acts, 'details' => $det, 'contents' => $contents, 'con' => $con, 'fk' => $mk,
            'driver' => $drv ? ['id' => (int) $r->driver_id, 'name' => sl_userName($drv), 'phone' => trim((string) ($drv->phone ?? ''))] : null,
            'sender' => $u ? ['id' => (int) $r->sender_id, 'name' => sl_userName($u), 'phone' => trim((string) ($u->phone ?? ''))] : null,
            'hist' => array_map(fn($h) => ['t' => str_replace('_', ' ', (string) ($h->mod_style ?: 'Update')), 'c' => (string) $h->comments, 'at' => sl_date($h->t_date, 'M j, H:i'), 'loc' => trim((string) $h->t_city)], array_slice($hist, 0, 12)),
            'prio' => ['score' => $score, 'label' => $score >= 3 ? 'High' : ($score >= 1 ? 'Normal' : 'Low'), 'cls' => $score >= 3 ? 'bad' : ($score >= 1 ? 'warn' : 'ok'), 'days' => $daysOpen, 'etaDelta' => $etaDelta],
        ];
    }
    $out['shown'] = count($out['items']);
    return $out;
}
