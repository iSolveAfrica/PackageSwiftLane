<?php
/**
 * Rider app route: today's route, position pings, loading checklist, start, arrive, sync after
 * delivery_ajax deliver/fail, re-optimise, break/resume and nearest-first.
 * Rider only (sl_userIs('driver')); every read and write is scoped to the signed-in rider.
 * Deliver and fail stay on ajax/sl/delivery_ajax.php (proof photo, receiver, pickup codes).
 * Pings are never queued offline: the app sends only its latest position.
 */
require_once __DIR__ . '/../../loader.php';
require_once CDP_ROOT . '/helpers/ajax_guard.php';
require_once CDP_ROOT . '/helpers/route_planner.php';
$user = new User();
$core = new Core();
require_login();
if (!sl_userIs('driver')) sl_json(['success' => false, 'message' => 'Rider access required'], 403);

$op = (string) ($_POST['op'] ?? $_GET['op'] ?? '');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$reads = ['today', 'nearest'];
if ($method !== 'POST' && !(in_array($op, $reads, true) && $method === 'GET')) sl_json(['success' => false, 'message' => 'POST required'], 405);
if (!in_array($op, $reads, true)) {
    require_safe_write();
    if (CDP_APP_MODE_DEMO === true && $op !== 'ping') sl_json(['success' => false, 'message' => 'Changes are disabled in demo mode'], 403);
}
$in = $method === 'POST' ? $_POST : $_GET;
$me = (int) $_SESSION['userid'];
$int = fn(string $k) => (int) ($in[$k] ?? 0);
$num = fn(string $k) => isset($in[$k]) && $in[$k] !== '' && is_numeric($in[$k]) ? (float) $in[$k] : null;

try {
    rp_ensureSchema();
    switch ($op) {
        case 'today':
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['date'] ?? '')) ? (string) $in['date'] : null;
            sl_json(['success' => true, 'route' => rp_riderTodayRoute($me, $date)]);
        case 'ping':
            // Per-session flood guard on top of the distance/time throttle.
            $hits = array_filter((array) ($_SESSION['rp_ping_hits'] ?? []), fn($t) => $t > time() - 60);
            if (count($hits) >= 12) sl_json(['success' => true, 'stored' => false, 'next_in_s' => 20, 'reason' => 'rate limited']);
            $hits[] = time(); $_SESSION['rp_ping_hits'] = $hits;
            $res = rp_recordPing($me, ['lat' => $num('lat'), 'lng' => $num('lng'), 'accuracy' => $num('accuracy'), 'speed' => $num('speed'), 'heading' => $num('heading'), 'battery' => $num('battery'), 'ts' => $num('ts')]);
            sl_json(['success' => true] + $res);
        case 'load_scan':
            $res = rp_loadScan($int('route_id'), $me, (string) ($in['code'] ?? ''));
            sl_json(['success' => (bool) $res['ok'], 'message' => $res['ok'] ? (($res['already'] ?? false) ? 'Already loaded: ' : 'Loaded: ') . $res['parcel']['tracking'] : $res['message']] + $res);
        case 'load_toggle':
            $res = rp_loadToggle($int('route_id'), $me, (string) ($in['module'] ?? 'air'), $int('order_id'), !empty($in['loaded']) && $in['loaded'] !== '0', !empty($in['missing']) && $in['missing'] !== '0');
            sl_json(['success' => true] + $res);
        case 'start':
            $res = rp_startRoute($int('route_id'), $me);
            sl_json(['success' => (bool) $res['started'], 'message' => $res['started'] ? 'Route started. Ride safe!' . ($res['errors'] ? ' ' . count($res['errors']) . ' delivery could not start.' : '') : ($res['errors'][0]['message'] ?? 'Route could not start'), 'route' => $res, 'errors' => $res['errors']]);
        case 'arrive':
            sl_json(['success' => true, 'message' => 'Arrived. The customer can see you are here.', 'route' => rp_arriveStop($int('route_id'), $int('stop_id'), $me)]);
        case 'sync':
            $req = rp_one('SELECT id, driver_id FROM cdb_delivery_requests WHERE id = ?', [$int('request_id')]);
            if (!$req || (int) $req['driver_id'] !== $me) throw new DomainException('Delivery not found');
            rp_syncStopFromRequest((int) $req['id']);
            sl_json(['success' => true, 'route' => rp_riderTodayRoute($me)]);
        case 'reoptimise':
            $pos = $num('lat') !== null && $num('lng') !== null ? [$num('lat'), $num('lng')] : null;
            $res = rp_reoptimise($int('route_id'), $me, 'rider', $pos);
            sl_json(['success' => true, 'message' => 'Stops re-ordered from your position', 'route' => $res]);
        case 'activity':
            $state = (string) ($in['state'] ?? '');
            if (!in_array($state, ['break', 'onway'], true)) throw new DomainException('Choose break or back on the way');
            $route = rp_one("SELECT id FROM cdb_rp_routes WHERE driver_id = ? AND route_date = ? AND status = 'onroute' ORDER BY id DESC", [$me, date('Y-m-d')]);
            if (!$route) throw new DomainException('Start your route first');
            rp_setActivity($me, (int) $route['id'], $state);
            sl_json(['success' => true, 'state' => $state, 'message' => $state === 'break' ? 'Break started' : 'Back on the way']);
        case 'nearest':
            $route = rp_riderTodayRoute($me);
            if (!$route) sl_json(['success' => true, 'stops' => []]);
            $lat = $num('lat'); $lng = $num('lng');
            if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) throw new DomainException('Share your location to sort by distance');
            $open = array_values(array_filter($route['stops'], fn($s) => in_array($s['status'], ['pending', 'arrived'], true)));
            sl_json(['success' => true, 'stops' => array_map(fn($s) => ['id' => $s['id'], 'seq' => $s['seq'], 'label' => $s['label'], 'area' => $s['area'], 'dist_m' => $s['dist_m']], rp_sortByDistanceFrom([$lat, $lng], $open))]);
        default:
            sl_json(['success' => false, 'message' => 'Unknown operation'], 400);
    }
} catch (DomainException $e) {
    sl_json(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('rider route ' . $op . ' failed: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
    sl_json(['success' => false, 'message' => 'Could not reach the route service. Please retry.'], 500);
}
