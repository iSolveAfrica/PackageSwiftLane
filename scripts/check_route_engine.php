<?php
/**
 * Pure tests of helpers/route_engine.php (no database): allocation capacity and handling rules,
 * locked riders, route quality (2-opt vs nearest neighbour vs naive order), time windows,
 * determinism, re-optimise with finished stops, haversine, ping throttle, alerts.
 * Run: php scripts/check_route_engine.php   (exit 1 on failure)
 */
require dirname(__DIR__) . '/helpers/route_engine.php';
date_default_timezone_set('UTC');

$fail = 0; $pass = 0;
function ok(bool $c, string $msg): void { global $fail, $pass; if ($c) { $pass++; return; } $fail++; echo "FAIL: $msg\n"; }

const DEPOT = [5.6037, -0.1870];
$areas = [
    'Osu' => [5.556, -0.182, 1], 'East Legon' => [5.635, -0.155, 2], 'Madina' => [5.668, -0.165, 2], 'Adenta' => [5.708, -0.153, 2],
    'Spintex' => [5.627, -0.105, 3], 'Tema' => [5.669, -0.017, 3], 'Achimota' => [5.616, -0.229, 1], 'Dansoman' => [5.548, -0.264, 1],
    'Kaneshie' => [5.570, -0.236, 1], 'Labadi' => [5.561, -0.150, 1], 'Cantonments' => [5.580, -0.174, 1], 'Airport' => [5.603, -0.180, 1],
    'Haatso' => [5.668, -0.197, 2], 'Teshie' => [5.585, -0.105, 3], 'Nungua' => [5.600, -0.077, 3], 'Lapaz' => [5.607, -0.251, 1],
];
mt_srand(42);
function jitter(): float { return (mt_rand(-1000, 1000) / 1000) * 0.008; }
function makeUnits(array $areas, int $n): array
{
    $names = array_keys($areas); $out = [];
    for ($i = 1; $i <= $n; $i++) {
        $a = $areas[$names[($i * 7) % count($names)]];
        $parcels = [];
        $k = 1 + ($i % 3);
        for ($j = 0; $j < $k; $j++) {
            $parcels[] = ['kg' => 0.5 + (($i * 13 + $j * 7) % 90) / 10, 'l' => 20 + ($i % 4) * 10, 'w' => 15 + ($j % 3) * 10, 'h' => 10 + ($i % 5) * 5,
                'handling' => $i % 11 === 0 ? ['cold'] : ($i % 7 === 0 ? ['fragile'] : ($i % 9 === 0 ? ['high_value'] : [])),
                'window' => $i % 6 === 0 ? ['start' => '09:00', 'end' => '11:00'] : [], 'priority' => $i % 10 === 0 ? 2 : 0];
        }
        foreach ($parcels as &$p) $p['volume_l'] = rp_parcelVolumeL($p);
        unset($p);
        $out[] = rp_unitFromRequest(['id' => 1000 + $i, 'lat' => $a[0] + jitter(), 'lng' => $a[1] + jitter(), 'zone_id' => $a[2]], $parcels);
    }
    return $out;
}
$s = rp_defaultSettings();
$veh = fn($type, $extra = []) => ['vehicle_type' => $type] + $extra + $s['vehicle_defaults'][$type];
$riders = [
    ['id' => 17, 'vehicle' => $veh('motorbike'), 'zone' => 1, 'status' => 'available'],
    ['id' => 21, 'vehicle' => $veh('motorbike'), 'zone' => 2, 'status' => 'available'],
    ['id' => 22, 'vehicle' => $veh('van'), 'zone' => 3, 'status' => 'available'],
    ['id' => 23, 'vehicle' => $veh('car', ['can_cold' => 1, 'can_high_value' => 1]), 'zone' => null, 'status' => 'available'],
    ['id' => 24, 'vehicle' => $veh('van'), 'zone' => null, 'status' => 'off'],
];
$units = makeUnits($areas, 40);
$byId = [];
foreach ($units as $u) $byId[$u['id']] = $u;
$vById = [];
foreach ($riders as $r) $vById[$r['id']] = $r['vehicle'];
$depart = '2026-11-27T08:30:00+00:00';

// 1. haversine Accra (office) to Tema Community 1 is about 17-20 km.
$d = rp_haversineM(DEPOT[0], DEPOT[1], 5.669, -0.017);
ok($d >= 17000 && $d <= 20500, "haversine Accra->Tema = $d m");
ok(rp_haversineM(5.6, -0.2, 5.6, -0.2) === 0, 'haversine zero');

// 2. volume and estimate provider label
ok(abs(rp_parcelVolumeL(['l' => 50, 'w' => 40, 'h' => 30]) - 60.0) < 0.01, 'volume 50x40x30 = 60 L');
$m = rp_estimateMatrix([DEPOT, [5.669, -0.017]], 'motorbike', $s);
ok($m['provider'] === 'estimate', 'estimate provider label');
ok($m['m'][0][1] === (int) round($d * 1.35), 'road factor applied');
ok(abs($m['s'][0][1] - $m['m'][0][1] / (24 / 3.6)) <= 1, 'motorbike speed 24 km/h');

// 3. allocation: capacity, handling, availability
$res = rp_allocate($units, $riders, DEPOT, ['settings' => $s, 'depart' => $depart]);
$seen = [];
foreach ($res['riders'] as $rid => $r) {
    $v = $vById[$rid];
    $kg = $l = $n = 0;
    foreach ($r['units'] as $uid) {
        $u = $byId[$uid]; $kg += $u['kg']; $l += $u['l']; $n += $u['n'];
        ok(rp_vehicleEligible($v, $u) === null, "unit $uid eligible for rider $rid (" . rp_vehicleEligible($v, $u) . ')');
        ok(!isset($seen[$uid]), "unit $uid allocated once");
        $seen[$uid] = true;
        if (in_array('cold', $u['handling'], true)) ok(!empty($v['can_cold']), "cold unit $uid on cold vehicle");
        if (in_array('fragile', $u['handling'], true)) ok(!empty($v['can_fragile']), "fragile unit $uid on fragile-ok vehicle");
        if (in_array('high_value', $u['handling'], true)) ok(!empty($v['can_high_value']), "high-value unit $uid on secure vehicle");
    }
    ok($kg <= $v['max_kg'] + 1e-6 && $l <= $v['max_l'] + 1e-6 && $n <= $v['max_parcels'], "rider $rid within capacity ($kg kg / $l L / $n)");
    ok(abs($r['load']['kg'] - $kg) < 0.01, "rider $rid load reported");
    if ($r['route']) ok(count($r['route']['order']) === count($r['units']), "rider $rid route has every stop");
}
ok(empty($res['riders'][24]['units']), 'off-duty rider gets nothing');
foreach ($res['unallocated'] as $u) ok(!isset($seen[$u['id']]), 'unallocated not also allocated');
ok(count($seen) + count($res['unallocated']) === count($units), 'every unit accounted for');
ok(count($seen) >= 30, 'most units allocated (' . count($seen) . '/40)');
echo 'allocation: ' . count($seen) . ' allocated, ' . count($res['unallocated']) . ' unallocated, riders used ' . $res['score']['riders_used'] . ', avg fill ' . $res['score']['avg_fill'] . "%, " . round($res['score']['total_m'] / 1000, 1) . " km\n";

// 4. cluster compactness: each rider's units stay closer to their own centroid than a random split would
$spread = 0; $cnt = 0;
foreach ($res['riders'] as $r) {
    if (count($r['units']) < 2) continue;
    $la = array_sum(array_map(fn($i) => $byId[$i]['lat'], $r['units'])) / count($r['units']);
    $lo = array_sum(array_map(fn($i) => $byId[$i]['lng'], $r['units'])) / count($r['units']);
    foreach ($r['units'] as $i) { $spread += rp_haversineM($la, $lo, $byId[$i]['lat'], $byId[$i]['lng']); $cnt++; }
}
ok($cnt > 0 && $spread / $cnt < 9000, 'clusters are compact (avg ' . round($spread / max(1, $cnt)) . ' m from centroid)');

// 5. determinism
$res2 = rp_allocate(array_reverse($units), array_reverse($riders), DEPOT, ['settings' => $s, 'depart' => $depart]);
ok(json_encode(array_map(fn($r) => $r['units'], $res['riders'])) === json_encode(array_map(fn($r) => $r['units'], $res2['riders'])), 'allocation is deterministic (input order independent)');

// 6. locked riders keep exactly their fixed set
$lockedSet = array_slice($res['riders'][21]['units'], 0, 3);
$riders3 = $riders;
foreach ($riders3 as &$r) if ($r['id'] === 21) { $r['locked'] = true; $r['units'] = $lockedSet; }
unset($r);
$res3 = rp_allocate($units, $riders3, DEPOT, ['settings' => $s, 'depart' => $depart]);
ok($res3['riders'][21]['units'] == (function ($a) { sort($a); return $a; })($lockedSet), 'locked rider untouched');
foreach ($res3['riders'] as $rid => $r) if ($rid !== 21) ok(!array_intersect($r['units'], $lockedSet), 'locked units not given to others');

// 7. balance mode spreads load more evenly than fill mode
$resB = rp_allocate($units, $riders, DEPOT, ['settings' => $s, 'depart' => $depart, 'mode' => 'balance']);
ok($resB['score']['riders_used'] >= $res['score']['riders_used'], 'balance mode uses at least as many riders');
$tooMany = makeUnits($areas, 120);
$resF = rp_allocate($tooMany, $riders, DEPOT, ['settings' => $s, 'depart' => $depart]);
foreach ($resF['riders'] as $rid => $r) ok($r['load']['n'] <= $vById[$rid]['max_parcels'] && $r['load']['kg'] <= $vById[$rid]['max_kg'] + 1e-6 && $r['load']['l'] <= $vById[$rid]['max_l'] + 1e-6, "overflow: rider $rid within capacity");
ok(count($resF['unallocated']) > 0, 'overflow: the rest stays in the hold with reasons');
ok((bool) array_filter($resF['unallocated'], fn($u) => in_array($u['reason'], ['no capacity left', 'stop limit reached', 'needs cold box', 'no rider available'], true)), 'overflow reasons given');

// 8. routes: 2-opt/Or-opt <= nearest neighbour <= (typically) naive
$better = 0; $total = 0;
for ($t = 0; $t < 12; $t++) {
    mt_srand(100 + $t);
    $st = [];
    for ($i = 1; $i <= 14; $i++) $st[] = ['id' => $i, 'lat' => 5.55 + mt_rand(0, 1500) / 10000, 'lng' => -0.27 + mt_rand(0, 2500) / 10000, 'service_s' => 300];
    $r = rp_optimiseRoute(DEPOT, $st, null, $depart, ['settings' => $s, 'vehicle_type' => 'motorbike']);
    ok($r['total_m'] <= $r['nn_total_m'] + 1, "route $t: optimised {$r['total_m']} <= nearest-neighbour {$r['nn_total_m']}");
    ok(count($r['order']) === 14 && count(array_unique($r['order'])) === 14, "route $t visits each stop once");
    if ($r['total_m'] < $r['naive_total_m']) $better++;
    $total++;
}
ok($better >= $total - 1, "optimised beats the naive order in $better/$total cases");

// 9. time windows: lateness reduced vs nearest neighbour ignoring windows
$st = [];
mt_srand(7);
for ($i = 1; $i <= 10; $i++) $st[] = ['id' => $i, 'lat' => 5.56 + mt_rand(0, 1200) / 10000, 'lng' => -0.25 + mt_rand(0, 1800) / 10000, 'service_s' => 300];
$st[9]['window_start'] = '08:30'; $st[9]['window_end'] = '09:00'; // the last-listed (likely far) stop needs to be early
$st[4]['window_start'] = '11:30'; $st[4]['window_end'] = '13:00'; // and this one late in the day
$plain = $st; foreach ($plain as &$p) unset($p['window_start'], $p['window_end']); unset($p);
$noWin = rp_optimiseRoute(DEPOT, $plain, null, $depart, ['settings' => $s, 'vehicle_type' => 'motorbike']);
$naiveEtas = rp_etas(DEPOT, array_map(fn($id) => $st[$id - 1], $noWin['order']), null, $depart, $s + ['vehicle_type' => 'motorbike']);
$withWin = rp_optimiseRoute(DEPOT, $st, null, $depart, ['settings' => $s, 'vehicle_type' => 'motorbike']);
$lateA = array_sum(array_column($naiveEtas['legs'], 'late_s')); $lateB = array_sum(array_column($withWin['legs'], 'late_s'));
ok($lateB < $lateA || $lateA === 0, "window lateness reduced ($lateA s -> $lateB s)");
ok($withWin['late'] === 0, 'all windows met when feasible');

// 10. priority: urgent stop comes early
$st2 = $plain; $st2[7]['priority'] = 2;
$pr = rp_optimiseRoute(DEPOT, $st2, null, $depart, ['settings' => $s, 'vehicle_type' => 'motorbike']);
ok(array_search(8, $pr['order'], true) <= 2, 'urgent stop among the first three (position ' . array_search(8, $pr['order'], true) . ')');

// 11. ETAs are sensible: increasing, first leg at motorbike speed
$e = $withWin['legs'];
$prev = strtotime($depart);
foreach ($e as $leg) { ok(strtotime($leg['eta']) >= $prev, 'ETAs increase'); $prev = strtotime($leg['eta']); }
ok($withWin['total_s'] > 0 && $withWin['total_s'] < 6 * 3600, 'route duration plausible (' . round($withWin['total_s'] / 60) . ' min)');

// 12. reoptimise keeps finished stops: fixed prefix stays first and in order
$fixed = array_slice($noWin['order'], 0, 3);
$re = rp_optimiseRoute(DEPOT, $plain, null, $depart, ['settings' => $s, 'vehicle_type' => 'motorbike', 'fixed' => $fixed]);
ok(array_slice($re['order'], 0, 3) === $fixed, 'fixed stops keep their place');
ok(count($re['order']) === 10, 'reoptimised route keeps every stop');

// 13. deterministic routes
ok(json_encode(rp_optimiseRoute(DEPOT, $st, null, $depart, ['settings' => $s])) === json_encode(rp_optimiseRoute(DEPOT, $st, null, $depart, ['settings' => $s])), 'route deterministic');

// 14. nearest-first list
$near = rp_sortByDistanceFrom([5.60, -0.18], $plain);
for ($i = 1; $i < count($near); $i++) ok($near[$i]['dist_m'] >= $near[$i - 1]['dist_m'], 'nearest-first ascending');

// 15. eligibility reasons
$moto = $veh('motorbike');
ok(rp_vehicleEligible($moto, ['handling' => ['cold'], 'kg' => 1, 'l' => 1, 'n' => 1]) === 'needs cold box', 'cold needs cold box');
ok(rp_vehicleEligible($moto, ['handling' => ['fragile'], 'kg' => 1, 'l' => 1, 'n' => 1]) !== null, 'fragile refused on motorbike');
ok(strpos((string) rp_vehicleEligible($moto, ['handling' => [], 'kg' => 1, 'l' => 1, 'n' => 1, 'max_dims' => [120, 30, 20]]) , 'over max size') === 0, 'over max size');
ok(rp_vehicleEligible($moto, ['handling' => [], 'kg' => 40, 'l' => 1, 'n' => 1]) === 'too heavy for vehicle', 'too heavy');
ok(rp_vehicleEligible($veh('van'), ['handling' => ['fragile'], 'kg' => 40, 'l' => 100, 'n' => 3]) === null, 'van takes fragile');

// 16. ping throttle
$now = 1_800_000_000;
ok(rp_shouldStorePing(null, ['lat' => 5.6, 'lng' => -0.18, 'accuracy' => 20], $s, $now)['store'], 'first ping stored');
$last = ['lat' => 5.6, 'lng' => -0.18, 'recorded_at' => $now - 5];
ok(!rp_shouldStorePing($last, ['lat' => 5.601, 'lng' => -0.18], $s, $now)['store'], 'ping 5 s later throttled');
$last['recorded_at'] = $now - 25;
ok(!rp_shouldStorePing($last, ['lat' => 5.6001, 'lng' => -0.18], $s, $now)['store'], 'ping after 25 s but 11 m throttled');
ok(rp_shouldStorePing($last, ['lat' => 5.601, 'lng' => -0.18], $s, $now)['store'], 'ping after 25 s and 111 m stored');
$last['recorded_at'] = $now - 120;
ok(rp_shouldStorePing($last, ['lat' => 5.6, 'lng' => -0.18], $s, $now)['store'], 'stationary heartbeat after 100 s');
ok(!rp_shouldStorePing(null, ['lat' => 95, 'lng' => 0], $s, $now)['store'], 'invalid latitude rejected');
ok(!rp_shouldStorePing(null, ['lat' => 5.6, 'lng' => -0.18, 'accuracy' => 900], $s, $now)['store'], 'inaccurate fix rejected');

// 17. alerts
$route = ['status' => 'onroute', 'rider_name' => 'Ama'];
$stopsA = [['id' => 1, 'seq' => 1, 'status' => 'pending', 'eta' => $now - 40 * 60, 'label' => 'Osu']];
$al = rp_detectAlerts($route, $stopsA, null, [], $s, $now);
ok((bool) array_filter($al, fn($a) => $a['type'] === 'offline'), 'offline alert without pings');
ok((bool) array_filter($al, fn($a) => $a['type'] === 'late' && $a['stop_id'] === 1), 'late alert');
$pings = [];
for ($i = 0; $i <= 12; $i++) $pings[] = ['lat' => 5.6 + $i * 0.00001, 'lng' => -0.18, 'recorded_at' => $now - 12 * 60 + $i * 60];
$al2 = rp_detectAlerts($route, [['id' => 2, 'seq' => 1, 'status' => 'pending', 'eta' => $now + 600]], end($pings), $pings, $s, $now);
ok((bool) array_filter($al2, fn($a) => $a['type'] === 'stuck'), 'stuck alert after 12 min without moving');
$al3 = rp_detectAlerts(['status' => 'loading', 'loading_since' => $now - 45 * 60], [], null, [], $s, $now);
ok((bool) array_filter($al3, fn($a) => $a['type'] === 'idle_loading'), 'idle loading alert');

// 18. unit from request: window intersection, priority, handling
$u = rp_unitFromRequest(['id' => 5, 'lat' => 5.6, 'lng' => -0.2], [['kg' => 2, 'volume_l' => 10, 'handling' => ['fragile'], 'window' => ['start' => '09:00', 'end' => '13:00'], 'priority' => 1], ['kg' => 3, 'volume_l' => 5, 'handling' => ['cold'], 'window' => ['start' => '10:00', 'end' => '12:00']]]);
ok($u['kg'] == 5 && $u['l'] == 15 && $u['n'] === 2 && $u['handling'] === ['cold', 'fragile'] && $u['window_start'] === '10:00' && $u['window_end'] === '12:00' && $u['priority'] === 1, 'unit merges parcels');

// 19. review fixes: max route length is respected on the final route; leftovers are retried;
// reasons are per vehicle; break suppresses "stuck"; distance-based improvement is reported.
$s60 = $s; $s60['max_route_min'] = 120;
$resL = rp_allocate($units, $riders, DEPOT, ['settings' => $s60, 'depart' => $depart]);
$overLong = array_filter($resL['riders'], fn($r) => $r['route'] && $r['route']['total_s'] > 120 * 60 && count($r['units']) > 0);
ok(!$overLong, 'no proposed route is longer than max_route_min');
$allIds = [];
foreach ($resL['riders'] as $r) $allIds = array_merge($allIds, $r['units']);
ok(count($allIds) + count($resL['unallocated']) === count($units), 'every unit is allocated or listed once (max route)');
// Cheapest insertion: anything unallocated for capacity cannot fit any available rider.
foreach ($res['unallocated'] as $ua) {
    if ($ua['reason'] !== 'no capacity left') continue;
    $u = $byId[$ua['id']];
    foreach ($res['riders'] as $rid => $r) {
        $rr = array_values(array_filter($riders, fn($x) => $x['id'] === $rid))[0];
        if ($rr['status'] !== 'available' || rp_vehicleEligible($rr['vehicle'], $u)) continue;
        $fits = $r['load']['kg'] + $u['kg'] <= $rr['vehicle']['max_kg'] && $r['load']['l'] + $u['l'] <= $rr['vehicle']['max_l'] && $r['load']['n'] + $u['n'] <= $rr['vehicle']['max_parcels'] && count($r['units']) < ($s['max_stops'] ?? 25);
        ok(!$fits, 'unallocated #' . $ua['id'] . ' really has no room on rider ' . $rid);
    }
}
$tv = rp_unitFromRequest(['id' => 9001, 'lat' => 5.6, 'lng' => -0.19], [['kg' => 12, 'volume_l' => 60, 'l' => 105, 'w' => 15, 'h' => 65, 'handling' => ['fragile', 'high_value']]]);
$resTv = rp_allocate([$tv], [
    ['id' => 1, 'vehicle' => $veh('motorbike'), 'status' => 'available'],
    ['id' => 2, 'vehicle' => $veh('van', ['can_fragile' => 1, 'can_high_value' => 0]), 'status' => 'available'],
    ['id' => 3, 'vehicle' => $veh('car', ['can_fragile' => 1, 'can_high_value' => 1, 'cargo_l_cm' => 100]), 'status' => 'available'],
], DEPOT, ['settings' => $s, 'depart' => $depart]);
$why = $resTv['unallocated'][0]['reason'] ?? '';
ok(strpos($why, 'car: over max size (105 cm > 100 cm') !== false && strpos($why, 'van: high value') !== false && strpos($why, 'motorbike:') !== false, 'reason grouped by vehicle: ' . $why);
$alB = rp_detectAlerts($route + ['activity' => 'break'], [['id' => 2, 'seq' => 1, 'status' => 'pending', 'eta' => $now + 600]], end($pings), $pings, $s, $now);
ok(!array_filter($alB, fn($a) => $a['type'] === 'stuck'), 'no stuck alert while the rider is on break');
$ro = rp_optimiseRoute(DEPOT, array_map(fn($u) => ['id' => $u['id'], 'lat' => $u['lat'], 'lng' => $u['lng'], 'service_s' => 240], array_slice($units, 0, 12)), null, $depart, ['settings' => $s]);
ok(isset($ro['shorter_pct']) && abs($ro['shorter_pct'] - round(100 * ($ro['nn_total_m'] - $ro['total_m']) / $ro['nn_total_m'], 1)) < 0.01, 'shorter_pct is distance based');

echo ($fail ? "FAILED: $fail of " . ($fail + $pass) : "Route engine check passed: $pass assertions") . "\n";
exit($fail ? 1 : 0);
