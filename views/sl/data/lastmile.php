<?php
/**
 * Data for LastMile.dc.html (Ghana last-mile delivery).
 * Sources: cdb_add_order (+ cdb_address_shipments.recipient_city), cdb_courier_track,
 * cdb_users (riders, userlevel 3), cdb_met_payment (cash), cdb_delivery_zones (migration).
 * "Out for delivery" = status 5 Distribution or 7 On Route (a rider's stops also include 6 Available once assigned); delivered = status 8;
 * failed attempts = tracking scans with status 12 Rejected or 16 No Picked up.
 * COD to collect = cash-payment orders out for delivery whose invoice is still pending (23).
 */
require_once CDP_ROOT . '/helpers/sl_view.php';

/** Zone matching a city (first zone whose city list contains it). */
function sl_lmZoneFor(string $city, array $zones): ?array
{
    $c = mb_strtolower(trim($city));
    if ($c === '') {
        return null;
    }
    $matches = [];
    foreach ($zones as $z) {
        foreach (array_filter(array_map('trim', explode(',', mb_strtolower((string) $z['match'])))) as $m) {
            if ($m !== '' && ($c === $m)) {
                $matches[$z['id']] = $z;
            }
        }
    }
    return count($matches) === 1 ? reset($matches) : null;
}

function sl_lmZones(): array
{
    if (!sl_tableExists('cdb_delivery_zones')) {
        return [];
    }
    return array_map(fn($z) => ['id' => (int) $z->id, 'name' => $z->name, 'match' => $z->city_match, 'fee' => (float) $z->fee, 'rider' => (int) $z->rider_id, 'riderName' => trim((string) $z->rider_name), 'active' => (int) $z->active], sl_rows("SELECT z.*, CONCAT(u.fname,' ',u.lname) AS rider_name FROM cdb_delivery_zones z LEFT JOIN cdb_users u ON u.id = z.rider_id WHERE z.active = 1 ORDER BY z.name"));
}

function sl_lastMileData($user, $core): array
{
    $d = ['flash' => (string) ($_GET['flash'] ?? ''), 'zonesReady' => sl_tableExists('cdb_delivery_zones'), 'canManage' => $user->cdp_hasPermission('manage_freight')];
    $ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : ['is_restricted' => false];
    $scope = !empty($ctx['is_restricted']) && !empty($ctx['agency_id']) ? ' AND o.agency = ' . (int) $ctx['agency_id'] : '';
    if (sl_tableExists('cdb_delivery_claims')) $scope .= " AND NOT EXISTS (SELECT 1 FROM cdb_delivery_claims dc WHERE dc.module='air' AND dc.order_id=o.order_id)";
    $out = "o.is_deleted = 0 AND o.status_courier IN (5,7) $scope";
    $zones = sl_lmZones();
    $d['zones'] = $zones;
    // KPIs
    $outN = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order o WHERE $out");
    $delToday = (int) sl_scalar("SELECT COUNT(DISTINCT t.order_track) FROM cdb_courier_track t WHERE t.status_courier = 8 AND DATE(t.t_date) = CURDATE()");
    $failToday = (int) sl_scalar("SELECT COUNT(*) FROM cdb_courier_track t WHERE t.status_courier IN (12,16) AND DATE(t.t_date) = CURDATE()");
    $routeToday = (int) sl_scalar("SELECT COUNT(DISTINCT t.order_track) FROM cdb_courier_track t WHERE t.status_courier = 7 AND DATE(t.t_date) = CURDATE()");
    $cod = sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(o.total_order),0) AS amt, COUNT(DISTINCT o.driver_id) AS riders FROM cdb_add_order o WHERE $out AND o.order_payment_method = 1 AND o.status_invoice = 23");
    $fail7 = (int) sl_scalar("SELECT COUNT(*) FROM cdb_courier_track t WHERE t.status_courier IN (12,16) AND t.t_date >= CURDATE() - INTERVAL 6 DAY");
    $del7 = (int) sl_scalar("SELECT COUNT(DISTINCT t.order_track) FROM cdb_courier_track t WHERE t.status_courier = 8 AND t.t_date >= CURDATE() - INTERVAL 6 DAY");
    $unzoned = 0;
    $byZone = [];
    $byCity = [];
    foreach (sl_rows("SELECT COALESCE(NULLIF(a.recipient_city,''),'Unknown') AS city, COUNT(*) AS n FROM cdb_add_order o LEFT JOIN cdb_address_shipments a ON a.order_id = o.order_id WHERE $out GROUP BY city ORDER BY n DESC") as $r) {
        $z = sl_lmZoneFor($r->city, $zones);
        if ($z) $byZone[$z['id']] = ($byZone[$z['id']] ?? 0) + (int) $r->n;
        else $unzoned += (int) $r->n;
        $byCity[] = ['city' => $r->city, 'n' => (int) $r->n, 'zone' => $z ? $z['name'] : ''];
    }
    $spark = function (string $sql): array {
        $map = [];
        foreach (sl_rows($sql) as $r) $map[$r->d] = (int) $r->n;
        $vals = [];
        for ($i = 11; $i >= 0; $i--) $vals[] = $map[date('Y-m-d', strtotime("-$i days"))] ?? 0;
        $max = max(1, max($vals));
        return sl_sparkBars($vals, 6);
    };
    $spDel = $spark("SELECT DATE(t_date) AS d, COUNT(DISTINCT order_track) AS n FROM cdb_courier_track WHERE status_courier = 8 AND t_date >= CURDATE() - INTERVAL 11 DAY GROUP BY d");
    $spFail = $spark("SELECT DATE(t_date) AS d, COUNT(*) AS n FROM cdb_courier_track WHERE status_courier IN (12,16) AND t_date >= CURDATE() - INTERVAL 11 DAY GROUP BY d");
    $spRoute = $spark("SELECT DATE(t_date) AS d, COUNT(DISTINCT order_track) AS n FROM cdb_courier_track WHERE status_courier = 7 AND t_date >= CURDATE() - INTERVAL 11 DAY GROUP BY d");
    $d['kpis'] = [
        ['icon' => 'i-truck', 'l' => 'Out for delivery', 'v' => sl_num($outN), 's' => $routeToday . ' sent out today', 'sp' => $spRoute],
        ['icon' => 'i-check', 'l' => 'Delivered today', 'v' => sl_num($delToday), 's' => sl_num($del7) . ' in 7 days', 'sp' => $spDel],
        ['icon' => 'i-alert', 'l' => 'Failed attempts today', 'v' => sl_num($failToday), 's' => $fail7 . ' in 7 days · ' . (($del7 + $fail7) ? sl_pct($fail7, $del7 + $fail7) : 0) . '% of attempts', 'sp' => $spFail],
        ['icon' => 'i-dollar', 'l' => 'COD to collect', 'v' => sl_money($cod->amt), 's' => (int) $cod->n . ' parcels · ' . (int) $cod->riders . ' riders', 'sp' => []],
        ['icon' => 'i-pin', 'l' => 'Without a zone', 'v' => sl_num($unzoned), 's' => $zones ? count($zones) . ' zones set up' : 'No zones yet', 'sp' => []],
    ];
    // zones with live counts
    foreach ($d['zones'] as &$z) {
        $z['open'] = $byZone[$z['id']] ?? 0;
    }
    unset($z);
    $d['cities'] = array_slice($byCity, 0, 12);
    $d['unzoned'] = $unzoned;
    $d['riders'] = sl_rows("SELECT id, CONCAT(fname,' ',lname) AS name FROM cdb_users WHERE userlevel = 3 AND active = 1 ORDER BY fname");
    // riders and their stops today
    $sel = (int) ($_GET['rider'] ?? 0);
    $riders = [];
    foreach (sl_rows("SELECT u.id, CONCAT(u.fname,' ',u.lname) AS name, u.phone,
            (SELECT COUNT(*) FROM cdb_add_order o WHERE o.driver_id = u.id AND o.is_deleted = 0 AND o.status_courier IN (5,6,7)) AS open_n,
            (SELECT COUNT(DISTINCT t.order_track) FROM cdb_courier_track t JOIN cdb_add_order o2 ON o2.order_no = t.order_track WHERE o2.driver_id = u.id AND t.status_courier = 8 AND DATE(t.t_date) = CURDATE()) AS done_n,
            (SELECT COUNT(*) FROM cdb_courier_track t JOIN cdb_add_order o3 ON o3.order_no = t.order_track WHERE o3.driver_id = u.id AND t.status_courier IN (12,16) AND DATE(t.t_date) = CURDATE()) AS fail_n,
            (SELECT COALESCE(SUM(o4.total_order),0) FROM cdb_add_order o4 WHERE o4.driver_id = u.id AND o4.is_deleted = 0 AND o4.status_courier IN (5,7) AND o4.order_payment_method = 1 AND o4.status_invoice = 23) AS cod_amt,
            (SELECT COUNT(*) FROM cdb_add_order o5 WHERE o5.driver_id = u.id AND o5.is_deleted = 0 AND o5.status_courier IN (5,7) AND o5.order_payment_method = 1 AND o5.status_invoice = 23) AS cod_n
        FROM cdb_users u WHERE u.userlevel = 3 AND u.active = 1 ORDER BY open_n DESC, u.fname") as $r) {
        $riders[] = ['id' => (int) $r->id, 'name' => $r->name, 'open' => (int) $r->open_n, 'done' => (int) $r->done_n, 'fail' => (int) $r->fail_n, 'cod' => (float) $r->cod_amt, 'codN' => (int) $r->cod_n, 'href' => sl_url(['rider' => (int) $r->id]), 'detail' => sl_route('RiderDetail', ['id' => (int) $r->id])];
        if (!$sel && (int) $r->open_n > 0) $sel = (int) $r->id;
    }
    if (!$sel && $riders) $sel = $riders[0]['id'];
    $d['riderList'] = $riders;
    $d['rider'] = null;
    foreach ($riders as $r) if ($r['id'] === $sel) $d['rider'] = $r;
    $d['stops'] = [];
    if ($sel) {
        $i = 0;
        foreach (sl_rows("SELECT o.order_no, o.status_courier, CONCAT(r.fname,' ',r.lname) AS who, a.recipient_city, a.recipient_address, s.mod_style FROM cdb_add_order o LEFT JOIN cdb_recipients r ON r.id = o.receiver_id LEFT JOIN cdb_address_shipments a ON a.order_id = o.order_id LEFT JOIN cdb_styles s ON s.id = o.status_courier WHERE o.driver_id = :r AND o.is_deleted = 0 AND (o.status_courier IN (5,6,7) OR o.order_no IN (SELECT order_track FROM cdb_courier_track WHERE DATE(t_date) = CURDATE() AND status_courier IN (8,12,16))) ORDER BY FIELD(o.status_courier,7,5,6,8,12,16), a.recipient_city LIMIT 30", [':r' => $sel]) as $s) {
            $z = sl_lmZoneFor((string) $s->recipient_city, $zones);
            $st = (int) $s->status_courier;
            $d['stops'][] = ['n' => ++$i, 'who' => trim((string) $s->who) ?: $s->order_no, 'where' => trim(($s->recipient_address ?: '') . ($s->recipient_city ? ', ' . $s->recipient_city : ''), ', ') ?: '—', 'zone' => $z ? $z['name'] : 'no zone', 'trk' => $s->order_no, 'st' => str_replace('_', ' ', (string) $s->mod_style), 'pc' => sl_statusClass($st)];
        }
    }
    // COD per rider
    $d['cod'] = array_values(array_filter(array_map(fn($r) => ['r' => $r['name'], 's' => $r['codN'] . ' parcels · cash on delivery, invoice pending', 'v' => sl_money($r['cod']), 'd' => 'w', 'href' => sl_route('AccountsReceivable')], $riders), fn($c) => $c['v'] !== sl_money(0)));
    // failed attempts (7 days)
    $d['fails'] = array_map(fn($f) => ['t' => $f->order_track . ' · ' . str_replace('_', ' ', (string) $f->mod_style), 's' => ($f->comments ?: 'No reason recorded') . ' · ' . sl_ago($f->t_date) . ($f->who ? ' · ' . $f->who : ''), 'href' => sl_route('ShipmentTracking', ['q' => $f->order_track])], sl_rows("SELECT t.order_track, t.comments, t.t_date, s.mod_style, CONCAT(u.fname,' ',u.lname) AS who FROM cdb_courier_track t LEFT JOIN cdb_styles s ON s.id = t.status_courier LEFT JOIN cdb_users u ON u.id = t.user_id WHERE t.status_courier IN (12,16) AND t.t_date >= CURDATE() - INTERVAL 6 DAY ORDER BY t.t_date DESC LIMIT 8"));
    $d['returns'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_returns WHERE created_at >= CURDATE() - INTERVAL 6 DAY');
    $d['assignable'] = 0;
    if ($zones) {
        foreach (sl_rows("SELECT a.recipient_city AS city FROM cdb_add_order o LEFT JOIN cdb_address_shipments a ON a.order_id = o.order_id WHERE o.is_deleted = 0 AND o.status_courier IN (5,6,7) AND COALESCE(o.driver_id,0) = 0 $scope") as $r) {
            $z = sl_lmZoneFor((string) $r->city, $zones);
            if ($z && $z['rider']) $d['assignable']++;
        }
    }
    // feature map (design section "All 25 features"): link to where each lives; only real pages.
    $cov = [['Delivery zones and fees by city', 'Zones panel on this page', ''], ['Zone assignment by recipient city', 'Assign riders by zone below', ''], ['Today\'s stops per rider', 'Rider panel below', ''], ['Cash on delivery to collect', 'COD panel below', ''], ['Failed delivery attempts', 'Failed attempts below', ''], ['Driver assignment', 'Dispatch board', 'RiderDispatch'], ['Live driver tracking', 'Delivery tracking', 'DeliveryTracking'], ['Driver mobile app', 'Rider app', 'DriverApp'], ['OTP pickup codes', 'Warehouse delivery', 'WarehouseDelivery'], ['Photo and signature proof', 'Shipment tracking', 'ShipmentTracking'], ['Driver performance', 'Rider detail', 'RiderDetail'], ['Rider fees', 'Rider fees settings', 'RiderSettings'], ['Pickup verification', 'Hand out packages', 'HandOut'], ['Hand-over chain of custody', 'Cargo exceptions', 'CargoExceptions'], ['GhanaPost GPS lookup', 'Not built: needs a GhanaPost API key', '-'], ['Route optimisation', 'Not built', '-'], ['Customer time-slot choice', 'Not built', '-']];
    $cf = (string) ($_GET['cf'] ?? 'all');
    $d['cf'] = array_map(fn($f) => ['label' => $f[1], 'cls' => $cf === $f[0] ? 'on' : '', 'href' => sl_url(['cf' => $f[0]])], [['all', 'All ' . count($cov)], ['here', 'On this page ' . count(array_filter($cov, fn($c) => $c[2] === ''))], ['other', 'Other pages ' . count(array_filter($cov, fn($c) => $c[2] !== '' && $c[2] !== '-'))]]);
    $d['cov'] = [];
    foreach ($cov as $i => $c) {
        if ($cf === 'here' && $c[2] !== '') continue;
        if ($cf === 'other' && ($c[2] === '' || $c[2] === '-')) continue;
        $d['cov'][] = ['n' => $i + 1, 't' => $c[0], 'where' => $c[1], 'href' => $c[2] === '' ? '#lm-zones' : ($c[2] === '-' ? '#' : sl_route($c[2])), 'st' => $c[2] === '' ? 'Here' : ($c[2] === '-' ? 'Not built' : 'Open'), 'pc' => $c[2] === '' ? 'onway' : ($c[2] === '-' ? 'plain' : 'delivered'), 'off' => $c[2] === '-' ? 'off' : ''];
    }
    return $d;
}
