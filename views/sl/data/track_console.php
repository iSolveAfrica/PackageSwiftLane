<?php
/**
 * Live tracking console (the dark map + tracking list + overview panel).
 * Rendered by views/sl/pages/partials/track_console.php on the Overview, Client dashboard,
 * Shipment tracking and Consolidations pages.
 *
 * sl_trackConsoleData($user, $core, $source) with $source:
 *   'shipments'       open air shipments (cdb_add_order) + sea packages (cdb_customers_packages)
 *   'consolidations'  open air (cdb_consolidate) + sea (cdb_consolidate_packages) consolidations
 *   'mine'            the signed-in customer's own shipments and packages (no staff permission needed)
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
            'via' => ['code' => 'LHR', 'city' => $air['transit']['city'] ?? 'London', 'country' => 'UK', 'lat' => 51.47, 'lon' => -0.45],
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

/**
 * Rows for the console. Each item carries everything its panel needs, so switching in the browser
 * needs no further request.
 */
function sl_trackConsoleData($user, $core, string $source = 'shipments', int $limit = 10): array
{
    $client = $source === 'mine';
    $uid = (int) ($_SESSION['userid'] ?? 0);
    $money = $client || (function_exists('cdp_canViewMoney') && cdp_canViewMoney($user));
    $kinds = [];
    if ($source === 'consolidations') {
        if ($user->cdp_hasPermission('view_consolidate_list')) $kinds[] = ['air', 'cdb_consolidate', 'c_prefix', 'c_no', 'c_date', 'cdb_consolidate_detail'];
        if ($user->cdp_hasPermission('view_consolidate_package_list')) $kinds[] = ['sea', 'cdb_consolidate_packages', 'c_prefix', 'c_no', 'c_date', 'cdb_consolidate_packages_detail'];
    } else {
        if ($client || $user->cdp_hasPermission(['view_shipment_list', 'view_general_shipments'])) $kinds[] = ['air', 'cdb_add_order', 'order_prefix', 'order_no', 'order_date', null];
        if ($client || $user->cdp_hasPermission('view_package_list')) $kinds[] = ['sea', 'cdb_customers_packages', 'order_prefix', 'order_no', 'order_date', null];
    }
    $out = ['source' => $source, 'items' => [], 'total' => 0, 'counts' => ['all' => 0, 'air' => 0, 'sea' => 0, 'late' => 0]];
    if (!$kinds) return $out;

    $couriers = [];
    foreach (sl_rows('SELECT id, name_com FROM cdb_courier_com') as $c) $couriers[(int) $c->id] = $c->name_com;
    $lanes = sl_tcLanes();
    $rows = [];
    foreach ($kinds as [$mode, $tbl, $px, $nx, $dcol, $detail]) {
        $idc = $detail ? 'consolidate_id' : 'order_id';
        $scope = $client ? " AND a.sender_id = $uid" : sl_opsScope('a');
        $del = $tbl === 'cdb_add_order' ? ' AND a.is_deleted = 0' : '';
        $eta = sl_columnExists($tbl, 'estimated_eta') ? 'a.estimated_eta' : 'NULL';
        $cnt = $detail ? "(SELECT COUNT(*) FROM $detail d WHERE d.consolidate_id = a.consolidate_id)" : '0';
        $open = "a.status_courier NOT IN (8,15,21,12)$del$scope";
        $out['total'] += (int) sl_scalar("SELECT COUNT(*) FROM $tbl a WHERE $open");
        foreach (sl_rows("SELECT a.$idc AS id, CONCAT(a.$px, a.$nx) AS code, a.$dcol AS dt, a.status_courier AS st, a.total_weight AS w, a.total_order AS amt,
                a.sub_total AS sub, a.total_tax AS tax, a.status_invoice AS inv, a.order_courier AS courier, a.sender_id, $eta AS eta, $cnt AS n
            FROM $tbl a WHERE $open ORDER BY a.$dcol DESC, a.$idc DESC LIMIT " . (int) $limit) as $r) {
            $r->mode = $mode;
            $r->tbl = $tbl;
            $rows[] = $r;
        }
    }
    usort($rows, fn($a, $b) => strcmp((string) $b->dt, (string) $a->dt));
    $rows = array_slice($rows, 0, $limit);
    $senders = sl_usersById(array_map(fn($r) => $r->sender_id, $rows));
    $hasFlight = sl_columnExists('cdb_courier_track', 'flight_no');

    foreach ($rows as $r) {
        $lane = $lanes[$r->mode];
        $st = (int) $r->st;
        $hist = sl_trackHistory((string) $r->code);
        $departed = null;
        foreach (array_reverse($hist) as $h) { if (sl_stageOf($h->status_courier) >= 3) { $departed = $h->t_date; break; } }
        $start = $departed ?: $r->dt;
        $p = sl_tcProgress($st, $start, $r->eta);
        $g = sl_tcGeometry($lane, $p);
        $etaTs = $r->eta ? strtotime($r->eta . ' 23:59:59') : 0;
        $arrived = sl_stageOf($st) >= 4;
        $late = !$arrived && $etaTs && $etaTs < time();
        $flight = '';
        $awb = '';
        if ($hasFlight) {
            $f = sl_row('SELECT flight_no, awb_no FROM cdb_courier_track WHERE order_track = :t AND (flight_no <> \'\' OR awb_no <> \'\') ORDER BY t_date DESC', [':t' => $r->code]);
            $flight = (string) ($f->flight_no ?? '');
            $awb = (string) ($f->awb_no ?? '');
        }
        $id = (int) $r->id;
        $isCon = $source === 'consolidations';
        if ($client) {
            $href = sl_route('PublicTracking', ['order_track' => $r->code]);
        } elseif ($isCon) {
            $href = sl_route('ConsolidationDetail', ['id' => $id, 'type' => $r->mode]);
        } else {
            $href = $r->mode === 'air' ? sl_route('ShipmentDetail', ['id' => $id]) : sl_route('PackageDetail', ['id' => $id]);
        }
        // documents the viewer may open
        $docs = [];
        if ($isCon) {
            require_once __DIR__ . '/consolidations.php';
            $M = sl_conMode($r->mode);
            if ($user->cdp_hasPermission($M['p']['print'])) $docs[] = ['Manifest', $M['url']['print'] . '?id=' . $id, 'i-file'];
            if ($user->cdp_hasPermission($M['p']['label'])) $docs[] = ['Label', $M['url']['label'] . '?id=' . $id, 'i-tag'];
            if ($r->mode === 'air' && $user->cdp_hasPermission($M['p']['print'])) $docs[] = ['Packing list', 'consolidate_parking_list.php?id=' . $id, 'i-layers'];
        } elseif ($r->mode === 'air') {
            if ($user->cdp_hasPermission('print_shipment')) $docs[] = ['Invoice', 'print_inv_ship.php?id=' . $id, 'i-receipt'];
            if ($user->cdp_hasPermission('print_label')) $docs[] = ['Label 4×6', sl_route('Label4x6', ['id' => $id]), 'i-tag'];
        } else {
            if ($user->cdp_hasPermission('print_package')) $docs[] = ['Receipt', 'print_invoice_package.php?id=' . $id, 'i-receipt'];
            if ($user->cdp_hasPermission('print_label')) $docs[] = ['Label', 'print_label_package.php?id=' . $id, 'i-tag'];
        }
        [$payL, $payC] = sl_payPill($r->inv);
        $u = $senders[(int) $r->sender_id] ?? null;
        $daysOpen = $r->dt ? (int) floor((time() - strtotime($r->dt)) / 86400) : 0;
        $etaDelta = $etaTs ? (int) round(($etaTs - time()) / 86400) : null;
        // priority score: late, unpaid, long dwell
        $score = ($late ? 3 : 0) + (in_array((int) $r->inv, [1, 19], true) ? 0 : 1) + ($daysOpen > 14 ? 1 : 0) + ($st === 28 || $st === 29 ? 2 : 0);
        $where = $arrived ? $lane['to']['city'] : ($p < 0.1 ? $lane['from']['city'] : ($g['viaP'] !== null && abs($p - $g['viaP']) < 0.12 ? $lane['via']['city'] : ($r->mode === 'sea' ? 'Atlantic Ocean' : ($g['viaP'] !== null && $p < $g['viaP'] ? 'Over the Atlantic' : 'En route to ' . $lane['to']['city']))));
        $out['counts']['all']++;
        $out['counts'][$r->mode]++;
        if ($late) $out['counts']['late']++;
        $out['items'][] = [
            'key' => $r->mode . '-' . $id, 'id' => $id, 'code' => (string) $r->code, 'mode' => $r->mode, 'modeL' => $r->mode === 'sea' ? 'Sea' : 'Air', 'href' => $href,
            'st' => sl_statusLabel($st), 'cls' => sl_statusClass($st), 'late' => $late, 'arrived' => $arrived,
            'from' => $lane['from'], 'via' => $lane['via'], 'to' => $lane['to'],
            'carrier' => $couriers[(int) $r->courier] ?? ($r->mode === 'sea' ? 'Ocean freight' : 'Air cargo'),
            'vehicle' => $r->mode === 'sea' ? 'Container vessel' : 'Cargo freighter',
            'flight' => $flight, 'awb' => $awb,
            'customer' => $isCon ? ((int) $r->n . ' parcel' . ((int) $r->n === 1 ? '' : 's')) : ($client ? sl_weight($r->w) : sl_userName($u)),
            'weight' => sl_weight($r->w), 'created' => sl_date($r->dt), 'eta' => $r->eta ? sl_date($r->eta) : 'Not set', 'etaShort' => $r->eta ? strtoupper(sl_date($r->eta, 'M j, Y')) : '—',
            'onway' => $departed || sl_stageOf($st) >= 3 ? sl_tcSince($start) : 'Not departed',
            'transit' => $etaTs && $r->dt ? max(1, (int) round(($etaTs - strtotime($r->dt)) / 86400)) . 'D' : '—',
            'estimate' => $arrived ? 'Arrived' : ($late ? 'Late' : ($r->eta ? 'On time' : 'No ETA')), 'estCls' => $arrived ? 'ok' : ($late ? 'bad' : ($r->eta ? 'ok' : 'mut')),
            'money' => $money, 'total' => sl_money($r->amt), 'sub' => sl_money($r->sub), 'tax' => sl_money($r->tax), 'pay' => $payL, 'payCls' => $payC === 'paid' ? 'ok' : ($payC === 'overdue' ? 'bad' : 'warn'),
            'pct' => (int) round($p * 100), 'where' => $where,
            'geo' => $g, 'docs' => $docs,
            'hist' => array_map(fn($h) => ['t' => str_replace('_', ' ', (string) ($h->mod_style ?: 'Update')), 'c' => (string) $h->comments, 'at' => sl_date($h->t_date, 'M j, H:i'), 'loc' => trim((string) $h->t_city)], array_slice($hist, 0, 8)),
            'prio' => ['score' => $score, 'label' => $score >= 3 ? 'High' : ($score >= 1 ? 'Normal' : 'Low'), 'cls' => $score >= 3 ? 'bad' : ($score >= 1 ? 'warn' : 'ok'), 'days' => $daysOpen, 'etaDelta' => $etaDelta],
        ];
    }
    $out['glows'] = sl_tcGlows();
    return $out;
}
