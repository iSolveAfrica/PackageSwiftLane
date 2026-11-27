<?php
/**
 * Route planner integration test against the seeded database, inside one transaction that is
 * rolled back at the end (nested DeliveryService transactions become savepoints).
 * Covers: propose, capacity, move (incl. a blocked move), lock, confirm (requests become
 * 'assigned' to the rider), routes/stops/load rows, inventory, loading, start, arrive, fail +
 * sync, remove stop, re-optimise, pings, fleet state, stats, timeline.
 * Needs: php scripts/seed_route_planner.php. Refuses unless the database is swiftlane or *test*.
 * Run: php scripts/check_route_planner_db.php
 */
require_once __DIR__ . '/../loader.php';
if (class_exists('Core')) { try { new Core(); } catch (Throwable $e) {} } // site time zone, as on the web
require_once CDP_ROOT . '/helpers/route_planner.php';
if (!(CDP_DB_NAME === 'swiftlane' || preg_match('/test/i', CDP_DB_NAME))) { fwrite(STDERR, "Use the local swiftlane or a *test* database\n"); exit(1); }

/** One connection for everything; inner begin/commit/rollback map to savepoints. */
class RpSavepointPDO extends PDO
{
    public int $depth = 0;
    public function beginTransaction(): bool { if ($this->depth === 0) parent::beginTransaction(); else $this->exec('SAVEPOINT rp_sp' . $this->depth); $this->depth++; return true; }
    public function commit(): bool { $this->depth--; if ($this->depth === 0) return parent::commit(); $this->exec('RELEASE SAVEPOINT rp_sp' . $this->depth); return true; }
    public function rollBack(): bool { $this->depth--; if ($this->depth === 0) return parent::rollBack(); $this->exec('ROLLBACK TO SAVEPOINT rp_sp' . $this->depth); return true; }
    public function inTransaction(): bool { return $this->depth > 1; } // the outer test transaction is invisible
    public function rollbackAll(): void { if ($this->depth > 0) { $this->depth = 0; parent::rollBack(); } }
}
rp_ensureSchema(); // DDL before the transaction (DDL would commit it)
$host = CDP_DB_HOST; $port = defined('CDP_DB_PORT') ? (int) CDP_DB_PORT : 0;
if (strpos($host, ':') !== false && strpos($host, '/') === false) [$host, $port] = explode(':', $host, 2);
$pdo = new RpSavepointPDO('mysql:host=' . $host . ($port ? ';port=' . (int) $port : '') . ';dbname=' . CDP_DB_NAME . ';charset=utf8mb4', CDP_DB_USER, CDP_DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$prop = (new ReflectionClass('Conexion'))->getProperty('sharedDbh');
$prop->setAccessible(true);
$prop->setValue(null, $pdo);
$GLOBALS['rp_delivery_service'] = new DeliveryService($pdo);
$GLOBALS['rp_offline'] = true;
$_SESSION['userid'] = $admin = (int) $pdo->query("SELECT id FROM cdb_users WHERE username = 'admin'")->fetchColumn();

$fail = 0; $pass = 0;
function ok(bool $c, string $m): void { global $fail, $pass; if ($c) { $pass++; return; } $fail++; echo "FAIL: $m\n"; }
function throwsDomain(callable $f, string $m): void { try { $f(); ok(false, $m . ' (no error)'); } catch (DomainException $e) { ok(true, $m); } }
$val = fn(string $sql, array $p = []) => rp_val($sql, $p);

$pdo->beginTransaction();
try {
    $pending = (int) $val("SELECT COUNT(*) FROM cdb_delivery_requests WHERE state = 'pending' AND place_id LIKE 'RPSEED-%'");
    if ($pending < 20) throw new RuntimeException('Run php scripts/seed_route_planner.php first');
    $date = date('Y-m-d');
    // A clean slate for today inside the transaction.
    $pdo->exec("UPDATE cdb_rp_plans SET status = 'discarded' WHERE status = 'draft'");
    $pdo->prepare("UPDATE cdb_rp_routes SET status = 'done' WHERE route_date = ?")->execute([$date]);

    /* propose */
    $p = rp_proposal($admin, ['date' => $date, 'agency' => 0]);
    ok($p['plan_id'] > 0, 'proposal saved as a draft');
    ok($p['kpis']['allocated'] >= 25, 'most deliveries allocated (' . $p['kpis']['allocated'] . ')');
    ok((bool) array_filter($p['unallocated'], fn($u) => $u['reason'] === 'No map location'), 'request without a pin reported');
    $riders = rp_ridersForPlanning($date);
    $units = [];
    foreach (rp_cargoHold()['units'] as $u) $units[$u['id']] = $u;
    foreach ($p['riders'] as $rid => $r) {
        $v = $riders[$rid]['vehicle'];
        $kg = $riders[$rid]['used']['kg']; $l = $riders[$rid]['used']['l']; $n = $riders[$rid]['used']['n'];
        foreach ($r['units'] as $uid) { $kg += $units[$uid]['kg']; $l += $units[$uid]['l']; $n += $units[$uid]['n']; ok(rp_vehicleEligible($v, $units[$uid]) === null, "unit $uid eligible for $rid"); }
        ok($kg <= $v['max_kg'] + 0.01 && $l <= $v['max_l'] + 0.01 && $n <= $v['max_parcels'], "rider $rid within capacity");
        if ($riders[$rid]['status'] === 'off') ok(!$r['units'], 'off-duty rider has no stops');
    }
    $withStops = array_keys(array_filter($p['riders'], fn($r) => count($r['units']) >= 2));
    ok(count($withStops) >= 2, 'at least two riders have stops');
    [$a, $b] = $withStops;

    /* move */
    $uid = $p['riders'][$a]['units'][0];
    $m = rp_moveUnit($p['plan_id'], $uid, null, $admin);
    ok(!$m['blocked'] && !in_array($uid, $m['from']['units'], true), 'moved to the hold');
    $cold = null;
    foreach ($units as $u) if (in_array('cold', $u['handling'], true)) { $cold = $u['id']; break; }
    $moto = null;
    foreach ($riders as $rid => $r) if ($r['vehicle']['vehicle_type'] === 'motorbike' && $r['status'] === 'available') { $moto = $rid; break; }
    if ($cold && $moto) {
        $bm = rp_moveUnit($p['plan_id'], $cold, $moto, $admin);
        ok($bm['blocked'] === true && str_contains((string) $bm['warning'], 'cold'), 'cold parcel cannot go on a motorbike: ' . $bm['warning']);
    }
    $van = null;
    foreach ($riders as $rid => $r) if ($r['vehicle']['vehicle_type'] === 'van' && $r['status'] === 'available') $van = $rid;
    $onVan = rp__loadDraft($p['plan_id'])['proposal']['riders'][$van]['units'] ?? [];
    $plain = null;
    foreach ($units as $u) if (!$u['handling'] && $u['n'] === 1 && $u['kg'] < 5 && $u['l'] < 30 && !in_array($u['id'], $onVan, true)) { $plain = $u['id']; break; }
    $mv = rp_moveUnit($p['plan_id'], $plain, $van, $admin);
    ok(!$mv['blocked'] && in_array($plain, $mv['to']['units'] ?? [], true) && $mv['to']['route'] !== null, 'moved a small parcel to the van and re-routed');

    /* lock: re-proposing keeps the locked rider's set */
    $l = rp_lockRider($p['plan_id'], $b, true, $admin);
    ok($l['locked'] === true, 'rider locked');
    $before = rp__loadDraft($p['plan_id'])['proposal']['riders'][$b]['units'];
    $p2 = rp_proposal($admin, ['date' => $date, 'agency' => 0, 'lock' => [$b]]);
    $after = $p2['riders'][$b]['units'];
    sort($before); sort($after);
    ok($before === $after, 'locked rider untouched by Auto-allocate');
    ok($val('SELECT status FROM cdb_rp_plans WHERE id = ?', [$p['plan_id']]) === 'discarded', 'previous draft discarded');

    /* confirm */
    $c = rp_confirmPlan($p2['plan_id'], $admin, 0);
    ok($c['assigned'] >= 20, 'confirm assigned ' . $c['assigned']);
    ok(!$c['errors'], 'no assignment errors: ' . json_encode($c['errors']));
    $rid = $withStops[0];
    $expect = $p2['riders'][$rid]['units'];
    foreach ($expect as $req) ok($val('SELECT state FROM cdb_delivery_requests WHERE id = ?', [$req]) === 'assigned' && (int) $val('SELECT driver_id FROM cdb_delivery_requests WHERE id = ?', [$req]) === $rid, "request $req assigned to rider $rid");
    ok((int) $val("SELECT COUNT(*) FROM cdb_delivery_events WHERE request_id = ? AND state = 'assigned'", [$expect[0]]) >= 1, 'assignment event written (customer tracking)');
    $route = rp_riderTodayRoute($rid);
    ok($route !== null && $route['route']['status'] === 'planned', 'rider sees a planned route');
    ok(count($route['stops']) === count($expect), 'one stop per request');
    $seqs = array_column($route['stops'], 'seq');
    ok($seqs === range(1, count($seqs)), 'stops numbered 1..n');
    ok($route['route']['total_m'] > 0 && $route['stops'][0]['eta'] !== null, 'distances and ETAs written');
    ok($route['provider'] === 'estimate', 'offline provider labelled estimate');
    $parcels = (int) $val('SELECT COUNT(*) FROM cdb_delivery_items WHERE request_id IN (' . implode(',', array_map('intval', $expect)) . ')');
    $inv = rp_inventory($route['route']['id']);
    ok($inv['total'] === $parcels && $inv['pending_load'] === $parcels && $inv['onboard'] === 0 && $inv['open'] === $parcels, "inventory counts $parcels parcels to load, none on board yet");
    ok($inv['by_type']['air'] + $inv['by_type']['sea'] === $parcels, 'inventory by type');
    throwsDomain(fn() => rp_route($route['route']['id'], $rid + 999, 'rider'), 'another rider cannot read the route');
    throwsDomain(fn() => rp_confirmPlan($p2['plan_id'], $admin, 0), 'a confirmed plan cannot be confirmed twice');

    /* loading */
    $routeId = $route['route']['id'];
    throwsDomain(fn() => rp_startRoute($routeId, $rid), 'cannot start before loading');
    $first = $route['stops'][0]['parcels'][0];
    $scan = rp_loadScan($routeId, $rid, strtolower($first['tracking']));
    ok($scan['ok'] && $scan['inventory']['loaded'] === 1, 'scan by tracking loads the parcel');
    ok(!rp_loadScan($routeId, $rid, 'NOPE-123')['ok'], 'unknown code refused');
    ok($val('SELECT status FROM cdb_rp_routes WHERE id = ?', [$routeId]) === 'loading', 'route switches to loading');
    $lastStop = end($route['stops']);
    foreach ($route['stops'] as $s) foreach ($s['parcels'] as $pc) {
        $missing = $s['id'] === $lastStop['id'];
        rp_loadToggle($routeId, $rid, $pc['module'], $pc['order_id'], !$missing, $missing);
    }
    $inv = rp_inventory($routeId);
    ok($inv['pending_load'] === 0 && $inv['missing'] === count($lastStop['parcels']), 'every parcel loaded or marked missing');

    /* start */
    $started = rp_startRoute($routeId, $rid);
    ok($started['started'] && $started['route']['status'] === 'onroute', 'route started: ' . json_encode($started['errors']));
    $firstReq = $route['stops'][0]['request_id'];
    ok($val('SELECT state FROM cdb_delivery_requests WHERE id = ?', [$firstReq]) === 'onroute', 'requests on route through DeliveryService');
    ok($val('SELECT state FROM cdb_delivery_requests WHERE id = ?', [$lastStop['request_id']]) === 'pending', 'all-missing delivery back in the hold');
    ok($val('SELECT state FROM cdb_rp_activity WHERE driver_id = ? AND ended_at IS NULL', [$rid]) === 'onway', 'activity on the way');

    /* arrive, fail, sync */
    $live = rp_route($routeId, $rid, 'rider');
    $next = $live['next_stop'];
    $arr = rp_arriveStop($routeId, $next['id'], $rid);
    ok(array_values(array_filter($arr['stops'], fn($s) => $s['id'] === $next['id']))[0]['status'] === 'arrived', 'stop arrived');
    ok((int) $val("SELECT COUNT(*) FROM cdb_delivery_events WHERE request_id = ? AND note = 'Rider arrived'", [$next['request_id']]) === 1, 'customer sees the arrival');
    ok($val('SELECT state FROM cdb_rp_activity WHERE driver_id = ? AND ended_at IS NULL', [$rid]) === 'waiting', 'activity waiting');
    rp_service()->transition($next['request_id'], $rid, 'rider', 0, 'fail', ['note' => 'Customer not at home']);
    rp_syncStopFromRequest($next['request_id']);
    $after = rp_route($routeId, $rid, 'rider');
    $st = array_values(array_filter($after['stops'], fn($s) => $s['id'] === $next['id']))[0];
    ok($st['status'] === 'failed' && $st['fail_reason'] === 'Customer not at home', 'failed stop synced with reason');
    ok($after['inventory']['failed'] === count($st['parcels']), 'inventory counts the failed parcels');
    ok($st['seq'] === 1, 'finished stop moved to the front');
    ok($val('SELECT state FROM cdb_rp_activity WHERE driver_id = ? AND ended_at IS NULL', [$rid]) === 'onway', 'back on the way after the stop');

    /* re-optimise from a position keeps the finished stop first */
    $re = rp_reoptimise($routeId, $rid, 'rider', [5.62, -0.17]);
    ok($re['stops'][0]['id'] === $next['id'] && $re['route']['version'] > $after['route']['version'], 're-optimise keeps finished stops and bumps the version');

    /* remove a stop on another (planned) route: back to the hold */
    $otherRoute = (int) $val("SELECT id FROM cdb_rp_routes WHERE route_date = ? AND status = 'planned' AND driver_id <> ? ORDER BY id LIMIT 1", [$date, $rid]);
    if ($otherRoute) {
        $os = rp_route($otherRoute, $admin, 'manager')['stops'][0];
        $rm = rp_removeStop($otherRoute, $os['id'], $admin);
        ok($val('SELECT state FROM cdb_delivery_requests WHERE id = ?', [$os['request_id']]) === 'pending' && !array_filter($rm['stops'], fn($s) => $s['id'] === $os['id'] && $s['status'] !== 'removed'), 'removed stop returns to the hold');
        $add = rp_addStop($otherRoute, $os['request_id'], $admin);
        ok((bool) array_filter($add['stops'], fn($s) => $s['request_id'] === $os['request_id'] && $s['status'] === 'pending'), 'stop added back');
    }

    /* pings */
    $pdo->prepare('DELETE FROM cdb_rp_pings WHERE driver_id = ?')->execute([$rid]);
    $t = time();
    ok(rp_recordPing($rid, ['lat' => 5.6, 'lng' => -0.18, 'accuracy' => 15, 'ts' => $t * 1000])['stored'], 'first ping stored');
    ok(!rp_recordPing($rid, ['lat' => 5.6005, 'lng' => -0.18, 'accuracy' => 15, 'ts' => $t * 1000 + 3000])['stored'], 'second ping 3 s later throttled');
    ok(!rp_recordPing($rid, ['lat' => 5.6, 'lng' => -0.18, 'accuracy' => 900])['stored'], 'inaccurate ping refused');
    ok((int) $val('SELECT COUNT(*) FROM cdb_rp_pings WHERE driver_id = ?', [$rid]) === 1, 'one ping stored');


    /* review fixes: server-time pings, no positions off shift */
    $pdo->prepare('DELETE FROM cdb_rp_pings WHERE driver_id = ?')->execute([$rid]);
    $fut = rp_recordPing($rid, ['lat' => 5.61, 'lng' => -0.18, 'accuracy' => 15, 'ts' => (time() + 59) * 1000]);
    ok($fut['stored'] && strtotime((string) $val('SELECT MAX(recorded_at) FROM cdb_rp_pings WHERE driver_id = ?', [$rid])) <= time(), 'a ping with a future device time is stored at server time');
    $offRider = (int) $val("SELECT id FROM cdb_users WHERE username = 'kojo'");
    if ($offRider) ok(rp_recordPing($offRider, ['lat' => 5.6, 'lng' => -0.18, 'accuracy' => 10])['reason'] === 'not on shift', 'no position stored for a rider without an active route');

    /* review fixes: a plan confirmed while the rider is on the way joins the route under way */
    $holdNow = rp_cargoHold()['units'];
    $vehR = rp_vehicle($rid);
    $cand = null;
    foreach ($holdNow as $u) if (!rp_vehicleEligible($vehR, $u)) { $cand = $u; break; }
    if ($cand) {
        $p3 = rp_proposal($admin, ['date' => $date, 'agency' => 0]);
        ok(!in_array($rid, array_keys(array_filter($p3['riders'], fn($r) => $r['units'])), true), 'auto-allocate leaves a rider on the way alone');
        foreach ($p3['riders'] as $r3id => $r3) if (in_array($cand['id'], $r3['units'], true)) rp_moveUnit($p3['plan_id'], $cand['id'], null, $admin);
        $mv = rp_moveUnit($p3['plan_id'], $cand['id'], $rid, $admin);
        ok($mv['blocked'] && $mv['can_force'], 'moving onto a rider on the way asks first');
        $mv = rp_moveUnit($p3['plan_id'], $cand['id'], $rid, $admin, true);
        if ($mv['blocked']) ok(true, 'move refused (' . $mv['warning'] . ')');
        else {
            try { rp_confirmPlan($p3['plan_id'], $admin, 0, true); } catch (DomainException $e) { ok(false, 'confirm: ' . $e->getMessage()); }
            ok((int) $val("SELECT COUNT(*) FROM cdb_rp_routes WHERE driver_id = ? AND route_date = ? AND status <> 'done'", [$rid, $date]) === 1, 'still one open route for the rider after a second plan');
            $today = rp_riderTodayRoute($rid);
            ok($today['route']['id'] === $routeId && $today['route']['status'] === 'onroute' && (bool) array_filter($today['stops'], fn($s) => $s['request_id'] === $cand['id']), 'the rider app keeps the route under way, with the new stop on it');
        }
    } else ok(true, 'no eligible hold unit left for the on-the-way test');

    /* review fixes: hard blocks */
    $p4 = rp_proposal($admin, ['date' => $date, 'agency' => 0]);
    $ridersP = rp_ridersForPlanning($date);
    $moto = null; foreach ($ridersP as $x) if ($x['vehicle']['vehicle_type'] === 'motorbike' && empty($x['vehicle']['can_fragile']) && !in_array($x['status'], ['onway', 'waiting'], true)) { $moto = $x['id']; break; }
    $fragile = null; foreach (rp_cargoHold()['units'] as $u) if (in_array('fragile', $u['handling'], true)) { $fragile = $u['id']; break; }
    if ($moto && $fragile) {
        $b = rp_moveUnit($p4['plan_id'], $fragile, $moto, $admin, true);
        ok($b['blocked'] && !$b['can_force'], 'a fragile parcel cannot be forced onto a motorbike');
        $lk = array_key_first(array_filter($p4['riders'], fn($r) => $r['units']));
        if ($lk) {
            rp_lockRider($p4['plan_id'], (int) $lk, true, $admin);
            $uid = $p4['riders'][$lk]['units'][0];
            $b2 = rp_moveUnit($p4['plan_id'], $uid, null, $admin, true);
            ok($b2['blocked'] && ($b2['code'] ?? '') === 'locked', 'stops of a locked rider cannot be moved');
        }
    }
    $vanRoute = rp_one("SELECT r.id, r.driver_id FROM cdb_rp_routes r JOIN cdb_rp_vehicles v ON v.driver_id = r.driver_id WHERE r.route_date = ? AND r.status IN ('planned','loading') AND v.can_high_value = 0", [$date]);
    $hv = null; foreach (rp_cargoHold()['units'] as $u) if (in_array('high_value', $u['handling'], true)) { $hv = $u['id']; break; }
    if ($vanRoute && $hv) {
        $ad = rp_addStop((int) $vanRoute['id'], $hv, $admin, true);
        ok(!empty($ad['blocked']) && empty($ad['can_force']) && $val('SELECT state FROM cdb_delivery_requests WHERE id = ?', [$hv]) === 'pending', 'a high-value parcel cannot be added to a vehicle without high-value clearance');
    }

    /* review fixes: a request reset outside the planner leaves the route */
    $anyStop = rp_one("SELECT s.id, s.route_id, s.request_id FROM cdb_rp_stops s JOIN cdb_rp_routes r ON r.id = s.route_id WHERE r.route_date = ? AND r.status = 'planned' AND s.status = 'pending' LIMIT 1", [$date]);
    if ($anyStop) {
        $pdo->prepare("UPDATE cdb_delivery_requests SET state = 'pending', driver_id = NULL WHERE id = ?")->execute([(int) $anyStop['request_id']]);
        $rv = rp_route((int) $anyStop['route_id'], $admin, 'manager');
        ok((bool) array_filter($rv['stops'], fn($s) => $s['id'] === (int) $anyStop['id'] && $s['status'] === 'removed'), 'a stop whose request went back to pending is removed from the route');
    }

    /* review fixes: vehicle flags can be switched off */
    $vr = rp_saveVehicle($rid, ['can_high_value' => '0', 'can_cold' => '0', 'active' => '1'], $admin);
    ok((int) $vr['can_high_value'] === 0 && (int) $vr['can_cold'] === 0, 'vehicle capabilities can be switched off');

    /* fleet, stats, timeline */
    $f = rp_fleetState($date);
    ok($f['kpis']['onway'] >= 1 && $f['kpis']['allocated'] >= 1, 'fleet KPIs: ' . json_encode($f['kpis']));
    $fr = array_values(array_filter($f['riders'], fn($r) => $r['id'] === $rid))[0];
    ok($fr['status'] === 'onway' && $fr['pos'] !== null && $fr['progress']['done'] === 1, 'fleet rider on the way with position and progress');
    $stats = rp_riderStats($rid, date('Y-m-d', strtotime('-6 days')), $date);
    ok(count($stats['per_day']) === 7 && isset($stats['by_category']['onway_s']) && $stats['failed'] >= 1 && $stats['delivered'] + $stats['failed'] >= 1, 'rider statistics: ' . $stats['delivered'] . ' delivered, ' . $stats['failed'] . ' failed');
    $tl = rp_timeline($date);
    $row = array_values(array_filter($tl, fn($r) => $r['driver_id'] === $rid))[0] ?? null;
    ok($row && $row['blocks'] && $row['stops'], 'timeline has activity blocks and stops');

    /* settings round trip */
    $s = rp_saveSettings(['service_min_base' => 5, 'start_places' => [['id' => 'accra-office', 'label' => 'Swift Lane Accra office', 'lat' => 5.6037, 'lng' => -0.187], ['id' => 'tema', 'label' => 'Tema depot', 'lat' => 5.67, 'lng' => -0.02]]], $admin);
    ok($s['service_min_base'] == 5 && count($s['start_places']) === 2, 'settings saved');
    throwsDomain(fn() => rp_saveSettings(['ping_min_s' => 1], $admin), 'invalid settings refused');
    $v = rp_saveVehicle($rid, ['plate' => 'GR 1-26', 'max_kg' => 35], $admin);
    ok($v['plate'] === 'GR 1-26' && (float) $v['max_kg'] === 35.0, 'vehicle saved');
} catch (Throwable $e) {
    $fail++;
    echo 'ERROR: ' . get_class($e) . ': ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    $pdo->rollbackAll();
}
echo ($fail ? "FAILED: $fail failure(s), $pass passed" : "Route planner DB check passed: $pass assertions (rolled back)") . "\n";
exit($fail ? 1 : 0);
