<?php
/**
 * Route planner engine: pure, deterministic functions (no DB, no globals, no I/O).
 * Distances in metres, times in seconds, weights in kg, volumes in litres.
 * Used by helpers/route_planner.php (DB layer) and tested by scripts/check_route_engine.php.
 *
 * Coordinates are [lat, lng]. A "unit" is one accepted delivery request: its parcels share an
 * address and move together (rp_unitFromRequest). Matrices are indexed 0 = start, 1..n = points.
 */

const RP_VEHICLE_TYPES = ['bicycle', 'motorbike', 'car', 'van', 'truck'];
const RP_HANDLING = ['fragile', 'cold', 'high_value', 'oversize', 'liquid'];

/** Built-in settings; stored settings (cdb_rp_settings) override these. */
function rp_defaultSettings(): array
{
    return [
        'start_places' => [['id' => 'accra-office', 'label' => 'Swift Lane Accra office', 'lat' => 5.6037, 'lng' => -0.1870]],
        'default_start' => 'accra-office',
        'service_min_base' => 4, 'service_min_per_parcel' => 1,
        'ping_min_s' => 20, 'ping_min_m' => 40,
        'stuck_min' => 12, 'late_min' => 15, 'offline_min' => 10, 'idle_loading_min' => 30,
        'road_factor' => 1.35,
        // Accra traffic: average door-to-door speeds, km/h.
        'speeds' => ['bicycle' => 12, 'motorbike' => 24, 'car' => 20, 'van' => 18, 'truck' => 15],
        'vehicle_defaults' => [
            'bicycle' => ['max_kg' => 15, 'max_l' => 40, 'max_parcels' => 4, 'cargo_l_cm' => 40, 'cargo_w_cm' => 35, 'cargo_h_cm' => 30, 'can_fragile' => 0, 'can_cold' => 0, 'can_high_value' => 0],
            'motorbike' => ['max_kg' => 30, 'max_l' => 90, 'max_parcels' => 8, 'cargo_l_cm' => 50, 'cargo_w_cm' => 45, 'cargo_h_cm' => 40, 'can_fragile' => 0, 'can_cold' => 0, 'can_high_value' => 1],
            'car' => ['max_kg' => 150, 'max_l' => 400, 'max_parcels' => 20, 'cargo_l_cm' => 100, 'cargo_w_cm' => 90, 'cargo_h_cm' => 50, 'can_fragile' => 1, 'can_cold' => 0, 'can_high_value' => 1],
            'van' => ['max_kg' => 600, 'max_l' => 2500, 'max_parcels' => 50, 'cargo_l_cm' => 240, 'cargo_w_cm' => 150, 'cargo_h_cm' => 130, 'can_fragile' => 1, 'can_cold' => 0, 'can_high_value' => 0],
            'truck' => ['max_kg' => 2000, 'max_l' => 9000, 'max_parcels' => 120, 'cargo_l_cm' => 420, 'cargo_w_cm' => 200, 'cargo_h_cm' => 200, 'can_fragile' => 1, 'can_cold' => 0, 'can_high_value' => 0],
        ],
        'fill_mode' => 'fill',
        'max_stops' => 25,
        'max_route_min' => 480, // driving + stop time a rider can do in one run
        'depart_time' => '09:00',
    ];
}

/** Normalise a free-text vehicle label (cdb_users.vehicle_type / vehiclecode) to a known type. */
function rp_vehicleType(?string $label): string
{
    $s = strtolower(trim((string) $label));
    if ($s === '') return 'motorbike';
    foreach (['bicycle' => ['bicycle', 'bike', 'cycle'], 'motorbike' => ['motor', 'okada', 'scooter', 'moto'], 'van' => ['van', 'bus', 'sprinter'], 'truck' => ['truck', 'lorry'], 'car' => ['car', 'sedan', 'saloon', 'taxi', 'pickup']] as $type => $words) {
        foreach ($words as $w) if (str_contains($s, $w)) return $type === 'bicycle' && str_contains($s, 'motor') ? 'motorbike' : $type;
    }
    return in_array($s, RP_VEHICLE_TYPES, true) ? $s : 'motorbike';
}

/** Great-circle distance in whole metres. */
function rp_haversineM(float $lat1, float $lng1, float $lat2, float $lng2): int
{
    $x = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;
    return (int) round(6371008.8 * 2 * asin(min(1, sqrt(max(0, $x)))));
}

function rp_speedMps(string $vehicleType, array $settings): float
{
    $speeds = ($settings['speeds'] ?? []) + rp_defaultSettings()['speeds'];
    $kmh = (float) ($speeds[$vehicleType] ?? $speeds['car']);
    return max(1.0, $kmh) * 1000 / 3600;
}

/**
 * Offline estimate: straight-line distance x road factor, at the vehicle's Accra speed.
 * Returns ['m' => int[][], 's' => int[][], 'provider' => 'estimate'].
 */
function rp_estimateMatrix(array $points, string $vehicleType, array $settings): array
{
    $factor = (float) ($settings['road_factor'] ?? 1.35);
    $mps = rp_speedMps($vehicleType, $settings);
    $n = count($points);
    $m = $s = [];
    $points = array_values($points);
    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $n; $j++) {
            if ($i === $j) { $m[$i][$j] = 0; $s[$i][$j] = 0; continue; }
            if ($j < $i) { $m[$i][$j] = $m[$j][$i]; $s[$i][$j] = $s[$j][$i]; continue; }
            $d = (int) round(rp_haversineM((float) $points[$i][0], (float) $points[$i][1], (float) $points[$j][0], (float) $points[$j][1]) * $factor);
            $m[$i][$j] = $d;
            $s[$i][$j] = (int) round($d / $mps);
        }
    }
    return ['m' => $m, 's' => $s, 'provider' => 'estimate'];
}

/** Seconds for a vehicle from a metres matrix (estimate provider) or as given (Google). */
function rp_matrixForVehicle(array $matrix, string $vehicleType, array $settings): array
{
    if (($matrix['provider'] ?? 'estimate') !== 'estimate') {
        $k = ['bicycle' => 1.6, 'motorbike' => 0.85, 'car' => 1.0, 'van' => 1.1, 'truck' => 1.25][$vehicleType] ?? 1.0;
        if ($k === 1.0) return $matrix;
        foreach ($matrix['s'] as $i => $row) foreach ($row as $j => $v) $matrix['s'][$i][$j] = (int) round($v * $k);
        return $matrix;
    }
    $mps = rp_speedMps($vehicleType, $settings);
    foreach ($matrix['m'] as $i => $row) foreach ($row as $j => $v) $matrix['s'][$i][$j] = (int) round($v / $mps);
    return $matrix;
}

/** Sub-matrix of a full matrix: $idx are indices into it (first one becomes 0). */
function rp_subMatrix(array $matrix, array $idx): array
{
    $out = ['m' => [], 's' => [], 'provider' => $matrix['provider'] ?? 'estimate'];
    foreach (array_values($idx) as $a => $i) foreach (array_values($idx) as $b => $j) {
        $out['m'][$a][$b] = (int) $matrix['m'][$i][$j];
        $out['s'][$a][$b] = (int) $matrix['s'][$i][$j];
    }
    return $out;
}

/** Litres from centimetre dimensions: ['l','w','h'] | ['length_cm',...] | [L, W, H]. */
function rp_parcelVolumeL(array $dims): float
{
    $l = (float) ($dims['l'] ?? $dims['length_cm'] ?? $dims[0] ?? 0);
    $w = (float) ($dims['w'] ?? $dims['width_cm'] ?? $dims[1] ?? 0);
    $h = (float) ($dims['h'] ?? $dims['height_cm'] ?? $dims[2] ?? 0);
    if ($l <= 0 || $w <= 0 || $h <= 0) return 0.0;
    return round($l * $w * $h / 1000, 2);
}

function rp_timeToSec(?string $t): ?int
{
    if ($t === null || !preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($t), $m)) return null;
    $s = (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) ($m[3] ?? 0);
    return $s <= 86400 ? $s : null;
}

/**
 * One planning unit from a request row and its parcel details (rp_parcelDetails shape).
 * Window = intersection of parcel windows (request window wins when set); priority = max.
 */
function rp_unitFromRequest(array $req, array $parcels): array
{
    $kg = $l = 0.0; $handling = []; $value = 0; $prio = (int) ($req['priority'] ?? 0); $ws = $req['window_start'] ?? null; $we = $req['window_end'] ?? null;
    $maxDims = [0, 0, 0];
    foreach ($parcels as $p) {
        $kg += (float) ($p['kg'] ?? 0);
        $l += (float) ($p['volume_l'] ?? rp_parcelVolumeL($p));
        foreach ((array) ($p['handling'] ?? []) as $hnd) if (in_array($hnd, RP_HANDLING, true)) $handling[$hnd] = true;
        $value += (int) ($p['value_cents'] ?? round(((float) ($p['value'] ?? 0)) * 100));
        $prio = max($prio, (int) ($p['priority'] ?? 0));
        $pw = $p['window'] ?? [];
        $pws = $pw['start'] ?? $p['window_start'] ?? null; $pwe = $pw['end'] ?? $p['window_end'] ?? null;
        if ($pws && (!$ws || rp_timeToSec($pws) > rp_timeToSec($ws))) $ws = $pws;
        if ($pwe && (!$we || rp_timeToSec($pwe) < rp_timeToSec($we))) $we = $pwe;
        $d = [(float) ($p['l'] ?? 0), (float) ($p['w'] ?? 0), (float) ($p['h'] ?? 0)];
        rsort($d);
        for ($i = 0; $i < 3; $i++) $maxDims[$i] = max($maxDims[$i], $d[$i]);
    }
    if ($ws && $we && rp_timeToSec($ws) >= rp_timeToSec($we)) $ws = $we = null; // contradictory windows: ignore
    $h = array_keys($handling); sort($h);
    return [
        'id' => (int) $req['id'],
        'lat' => isset($req['lat']) && $req['lat'] !== null ? (float) $req['lat'] : null,
        'lng' => isset($req['lng']) && $req['lng'] !== null ? (float) $req['lng'] : null,
        'zone_id' => !empty($req['zone_id']) ? (int) $req['zone_id'] : null,
        'agency_id' => (int) ($req['agency_id'] ?? 0),
        'kg' => round($kg, 2), 'l' => round($l, 2), 'n' => max(1, count($parcels)),
        'handling' => $h, 'value_cents' => $value,
        'window_start' => $ws ?: null, 'window_end' => $we ?: null,
        'priority' => max(0, min(2, $prio)),
        'max_dims' => $maxDims,
        'parcels' => array_values($parcels),
    ];
}

/** Null when $vehicle can carry $unit on its own; else the reason it cannot. */
function rp_vehicleEligible(array $vehicle, array $unit): ?string
{
    $h = (array) ($unit['handling'] ?? []);
    if (!empty($vehicle['agency_id']) && !empty($unit['agency_id']) && (int) $vehicle['agency_id'] !== (int) $unit['agency_id']) return 'rider is in another agency';
    if (in_array('cold', $h, true) && empty($vehicle['can_cold'])) return 'needs cold box';
    if (in_array('fragile', $h, true) && empty($vehicle['can_fragile'])) return 'fragile: needs a car or van';
    if (in_array('high_value', $h, true) && empty($vehicle['can_high_value'])) return 'high value: needs a secure vehicle';
    $cargo = [(float) ($vehicle['cargo_l_cm'] ?? 0), (float) ($vehicle['cargo_w_cm'] ?? 0), (float) ($vehicle['cargo_h_cm'] ?? 0)];
    rsort($cargo);
    $dims = array_map('floatval', (array) ($unit['max_dims'] ?? [0, 0, 0]));
    rsort($dims);
    if ($cargo[0] > 0) for ($i = 0; $i < 3; $i++) if (($dims[$i] ?? 0) > $cargo[$i]) return 'over max size (' . round($dims[$i]) . ' cm > ' . round($cargo[$i]) . ' cm cargo ' . ['length', 'width', 'height'][$i] . ')';
    if (in_array('oversize', $h, true) && !in_array(($vehicle['vehicle_type'] ?? ''), ['van', 'truck'], true)) return 'over max size';
    if ((float) $unit['kg'] > (float) ($vehicle['max_kg'] ?? 0)) return 'too heavy for vehicle';
    if ((float) $unit['l'] > (float) ($vehicle['max_l'] ?? 0)) return 'too bulky for vehicle';
    if ((int) $unit['n'] > (int) ($vehicle['max_parcels'] ?? 0)) return 'too many parcels for vehicle';
    return null;
}

/** Fill percentages of a load against a vehicle. */
function rp_fill(array $load, array $vehicle): array
{
    $pct = fn($a, $b) => $b > 0 ? round(100 * $a / $b, 1) : 0.0;
    $f = ['kg' => $pct($load['kg'], (float) ($vehicle['max_kg'] ?? 0)), 'l' => $pct($load['l'], (float) ($vehicle['max_l'] ?? 0)), 'n' => $pct($load['n'], (float) ($vehicle['max_parcels'] ?? 0))];
    $f['max'] = max($f['kg'], $f['l'], $f['n']);
    return $f;
}

function rp__fits(array $load, array $unit, array $vehicle): bool
{
    return $load['kg'] + $unit['kg'] <= (float) $vehicle['max_kg'] + 1e-6
        && $load['l'] + $unit['l'] <= (float) $vehicle['max_l'] + 1e-6
        && $load['n'] + $unit['n'] <= (int) $vehicle['max_parcels'];
}

/** Service time at a stop, seconds. */
function rp_serviceS(array $unit, array $settings): int
{
    return (int) (60 * ((float) ($settings['service_min_base'] ?? 4) + (float) ($settings['service_min_per_parcel'] ?? 1) * max(1, (int) ($unit['n'] ?? 1))));
}

/**
 * Simulate an ordered route. $order holds matrix indices (1..n), $stops[$idx-1] the stop data.
 * Returns ['legs','total_m','total_s','late','cost'].
 */
function rp__simulate(array $order, array $stops, array $matrix, int $depart, int $dayStart, ?int $fromIdx = 0): array
{
    $t = $depart; $prev = $fromIdx ?? 0; $legs = []; $tm = 0; $late = 0; $cost = 0.0;
    foreach ($order as $idx) {
        $st = $stops[$idx - 1];
        $m = (int) $matrix['m'][$prev][$idx]; $s = (int) $matrix['s'][$prev][$idx];
        $arrive = $t + $s; $wait = 0; $lateS = 0;
        $ws = rp_timeToSec($st['window_start'] ?? null); $we = rp_timeToSec($st['window_end'] ?? null);
        if ($ws !== null && $arrive < $dayStart + $ws) $wait = $dayStart + $ws - $arrive;
        if ($we !== null && $arrive > $dayStart + $we) $lateS = $arrive - $dayStart - $we;
        $begin = $arrive + $wait;
        $prio = (int) ($st['priority'] ?? 0);
        $cost += 4 * $lateS + ($prio === 2 ? 0.5 : ($prio === 1 ? 0.15 : 0)) * ($arrive - $depart);
        $legs[] = ['id' => $st['id'], 'm' => $m, 's' => $s, 'arrive_ts' => $arrive, 'wait_s' => $wait, 'late_s' => $lateS];
        $t = $begin + (int) ($st['service_s'] ?? 0);
        $tm += $m; $late += $lateS > 0 ? 1 : 0; $prev = $idx;
    }
    $total = $t - $depart;
    return ['legs' => $legs, 'total_m' => $tm, 'total_s' => $total, 'late' => $late, 'cost' => $cost + $total];
}

function rp__departTs(string $departIso): array
{
    try { $d = new DateTimeImmutable($departIso ?: 'now'); } catch (Throwable $e) { $d = new DateTimeImmutable('now'); }
    return [$d->getTimestamp(), $d->setTime(0, 0)->getTimestamp(), $d->getTimezone()];
}

/**
 * Stop order from $start through $stops (each: id, lat, lng, window_start/end 'HH:MM', priority 0-2,
 * service_s). Nearest-neighbour seeded with urgent and tight-window stops, then 2-opt and Or-opt
 * (segments of 1-3). Cost = seconds + 4 x lateness + priority penalty. Max 300 improving moves;
 * 2-opt/Or-opt run for up to 60 stops (beyond that the nearest-neighbour order is kept).
 * $opts: vehicle_type, settings, fixed (ids that stay first in the given order). The tour starts
 * at $start (matrix index 0): pass the rider's position there to re-plan from where they are.
 */
function rp_optimiseRoute(array $start, array $stops, ?array $matrix, string $departIso, array $opts = []): array
{
    $stops = array_values($stops);
    $n = count($stops);
    $settings = $opts['settings'] ?? rp_defaultSettings();
    if ($matrix === null) {
        $pts = [[(float) $start[0], (float) $start[1]]];
        foreach ($stops as $s) $pts[] = [(float) $s['lat'], (float) $s['lng']];
        $matrix = rp_estimateMatrix($pts, $opts['vehicle_type'] ?? 'car', $settings);
    }
    [$depart, $dayStart, $tz] = rp__departTs($departIso);
    $provider = $matrix['provider'] ?? 'estimate';
    if ($n === 0) return ['order' => [], 'legs' => [], 'total_m' => 0, 'total_s' => 0, 'late' => 0, 'improved_pct' => 0.0, 'nn_total_m' => 0, 'naive_total_m' => 0, 'provider' => $provider];

    $fixedIds = array_map('intval', (array) ($opts['fixed'] ?? []));
    $byId = [];
    foreach ($stops as $i => $s) $byId[(int) $s['id']] = $i + 1;
    $prefix = [];
    foreach ($fixedIds as $fid) if (isset($byId[$fid])) $prefix[] = $byId[$fid];
    $f = count($prefix);

    // Nearest neighbour from the last fixed stop, preferring urgent and tight-window stops.
    $order = $prefix; $left = [];
    for ($i = 1; $i <= $n; $i++) if (!in_array($i, $prefix, true)) $left[$i] = true;
    $sim = rp__simulate($order, $stops, $matrix, $depart, $dayStart);
    $t = $depart + $sim['total_s']; $prev = $order ? end($order) : 0;
    while ($left) {
        $best = null; $bestKey = INF;
        foreach (array_keys($left) as $i) {
            $st = $stops[$i - 1];
            $arrive = $t + (int) $matrix['s'][$prev][$i];
            $ws = rp_timeToSec($st['window_start'] ?? null); $we = rp_timeToSec($st['window_end'] ?? null);
            $wait = $ws !== null ? max(0, $dayStart + $ws - $arrive) : 0;
            $late = $we !== null ? max(0, $arrive - $dayStart - $we) : 0;
            $urgency = $we !== null ? max(0, 5400 - ($dayStart + $we - $arrive)) * 0.5 : 0;
            $prio = (int) ($st['priority'] ?? 0);
            $key = (int) $matrix['s'][$prev][$i] + 4 * $late + 0.5 * $wait - ($prio === 2 ? 3600 : ($prio === 1 ? 900 : 0)) - $urgency;
            if ($key < $bestKey - 1e-9 || ($key <= $bestKey + 1e-9 && $best !== null && $i < $best)) { $bestKey = $key; $best = $i; }
        }
        $st = $stops[$best - 1];
        $arrive = $t + (int) $matrix['s'][$prev][$best];
        $ws = rp_timeToSec($st['window_start'] ?? null);
        $t = max($arrive, $ws !== null ? $dayStart + $ws : $arrive) + (int) ($st['service_s'] ?? 0);
        $order[] = $best; $prev = $best; unset($left[$best]);
    }
    $nnSim = rp__simulate($order, $stops, $matrix, $depart, $dayStart);
    $naive = rp__simulate(range(1, $n), $stops, $matrix, $depart, $dayStart);
    $cost = $nnSim['cost'];

    if ($n - $f >= 3 && $n <= 60) {
        $moves = 0; $improved = true; $passes = 0;
        while ($improved && $moves < 300 && $passes < 60) {
            $improved = false; $passes++;
            // 2-opt: reverse order[i..j]
            for ($i = $f; $i < $n - 1 && $moves < 300; $i++) {
                for ($j = $i + 1; $j < $n && $moves < 300; $j++) {
                    $cand = $order;
                    array_splice($cand, $i, $j - $i + 1, array_reverse(array_slice($order, $i, $j - $i + 1)));
                    $c = rp__simulate($cand, $stops, $matrix, $depart, $dayStart)['cost'];
                    if ($c < $cost - 0.5) { $order = $cand; $cost = $c; $improved = true; $moves++; }
                }
            }
            // Or-opt: move a segment of 1-3 stops elsewhere
            for ($len = 1; $len <= 3; $len++) {
                for ($i = $f; $i + $len <= $n && $moves < 300; $i++) {
                    $seg = array_slice($order, $i, $len);
                    $rest = $order; array_splice($rest, $i, $len);
                    for ($p = $f; $p <= count($rest) && $moves < 300; $p++) {
                        if ($p === $i) continue;
                        $cand = $rest; array_splice($cand, $p, 0, $seg);
                        $c = rp__simulate($cand, $stops, $matrix, $depart, $dayStart)['cost'];
                        if ($c < $cost - 0.5) { $order = $cand; $cost = $c; $improved = true; $moves++; $seg = array_slice($order, $i, $len); $rest = $order; array_splice($rest, $i, $len); }
                    }
                }
            }
        }
    }
    $final = rp__simulate($order, $stops, $matrix, $depart, $dayStart);
    $legs = [];
    foreach ($final['legs'] as $leg) {
        $legs[] = ['id' => $leg['id'], 'm' => $leg['m'], 's' => $leg['s'], 'eta' => (new DateTimeImmutable('@' . $leg['arrive_ts']))->setTimezone($tz)->format('c'), 'late_s' => $leg['late_s'], 'wait_s' => $leg['wait_s']];
    }
    return [
        'order' => array_map(fn($i) => $stops[$i - 1]['id'], $order),
        'legs' => $legs, 'total_m' => $final['total_m'], 'total_s' => $final['total_s'], 'late' => $final['late'],
        // improved_pct compares the planning cost (time + lateness + priority); shorter_pct is distance only.
        'improved_pct' => $nnSim['cost'] > 0 ? round(100 * ($nnSim['cost'] - $final['cost']) / $nnSim['cost'], 1) : 0.0,
        'shorter_pct' => $nnSim['total_m'] > 0 ? round(100 * ($nnSim['total_m'] - $final['total_m']) / $nnSim['total_m'], 1) : 0.0,
        'late_fixed' => max(0, (int) $nnSim['late'] - (int) $final['late']),
        'nn_total_m' => $nnSim['total_m'], 'naive_total_m' => $naive['total_m'], 'naive_late' => $naive['late'], 'nn_late' => $nnSim['late'],
        'cost' => round($final['cost'], 1), 'provider' => $provider,
    ];
}

/** ETAs for a fixed order: matrix index 0 = start, i = $orderedStops[i-1]. */
function rp_etas(array $start, array $orderedStops, ?array $matrix, string $departIso, array $settings): array
{
    $orderedStops = array_values($orderedStops);
    if ($matrix === null) {
        $pts = [[(float) $start[0], (float) $start[1]]];
        foreach ($orderedStops as $s) $pts[] = [(float) $s['lat'], (float) $s['lng']];
        $matrix = rp_estimateMatrix($pts, $settings['vehicle_type'] ?? 'car', $settings);
    }
    [$depart, $dayStart, $tz] = rp__departTs($departIso);
    $sim = rp__simulate($orderedStops ? range(1, count($orderedStops)) : [], $orderedStops, $matrix, $depart, $dayStart);
    $legs = [];
    foreach ($sim['legs'] as $leg) $legs[] = ['id' => $leg['id'], 'm' => $leg['m'], 's' => $leg['s'], 'eta' => (new DateTimeImmutable('@' . $leg['arrive_ts']))->setTimezone($tz)->format('c'), 'late_s' => $leg['late_s'], 'wait_s' => $leg['wait_s']];
    return ['legs' => $legs, 'total_m' => $sim['total_m'], 'total_s' => $sim['total_s'], 'late' => $sim['late'], 'provider' => $matrix['provider'] ?? 'estimate'];
}

/** Stops sorted nearest to furthest from $pos [lat, lng], each with 'dist_m' (straight line). */
function rp_sortByDistanceFrom(array $pos, array $stops): array
{
    $out = [];
    foreach (array_values($stops) as $s) {
        if (!isset($s['lat'], $s['lng'])) continue;
        $s['dist_m'] = rp_haversineM((float) $pos[0], (float) $pos[1], (float) $s['lat'], (float) $s['lng']);
        $out[] = $s;
    }
    usort($out, fn($a, $b) => [$a['dist_m'], $a['id'] ?? 0] <=> [$b['dist_m'], $b['id'] ?? 0]);
    return $out;
}

/** Cheap open-tour length (metres) for allocation: nearest neighbour + 2-opt on matrix indices. */
function rp__tourM(array $m, array $idx): int
{
    $n = count($idx);
    if ($n === 0) return 0;
    $left = array_flip($idx); $prev = 0; $order = [];
    while ($left) {
        $best = null; $bd = PHP_INT_MAX;
        foreach ($left as $i => $_) if ($m[$prev][$i] < $bd || ($m[$prev][$i] === $bd && $i < $best)) { $bd = $m[$prev][$i]; $best = $i; }
        $order[] = $best; $prev = $best; unset($left[$best]);
    }
    $len = function ($o) use ($m) { $t = 0; $p = 0; foreach ($o as $i) { $t += $m[$p][$i]; $p = $i; } return $t; };
    $cur = $len($order);
    for ($pass = 0, $imp = true; $imp && $pass < 8; $pass++) {
        $imp = false;
        for ($i = 0; $i < $n - 1; $i++) for ($j = $i + 1; $j < $n; $j++) {
            $a = $i ? $order[$i - 1] : 0; $b = $order[$i]; $c = $order[$j]; $d = $order[$j + 1] ?? null;
            $delta = $m[$a][$c] - $m[$a][$b] + ($d !== null ? $m[$b][$d] - $m[$c][$d] : 0);
            if ($delta < 0) {
                $cand = $order;
                array_splice($cand, $i, $j - $i + 1, array_reverse(array_slice($order, $i, $j - $i + 1)));
                // The delta assumes a symmetric matrix; Google's is not, so check the real length.
                $c = $len($cand);
                if ($c < $cur) { $order = $cand; $cur = $c; $imp = true; }
            }
        }
    }
    return (int) $cur;
}

/**
 * Allocate units to riders.
 * $units: rp_unitFromRequest results (lat/lng required). $riders: [{id, vehicle, used:{kg,l,n}, zone,
 * locked, status, units[] (fixed unit ids, kept as they are)}]. $start: [lat, lng].
 * $opts: mode 'fill'|'balance', maxStops, matrix (indexed 0 = start, i = $units[i-1]), settings, depart.
 * Capacity is never exceeded on any dimension. Locked riders keep exactly their fixed units.
 */
function rp_allocate(array $units, array $riders, array $start, array $opts = []): array
{
    $settings = $opts['settings'] ?? rp_defaultSettings();
    $mode = ($opts['mode'] ?? $settings['fill_mode'] ?? 'fill') === 'balance' ? 'balance' : 'fill';
    $maxStops = max(1, (int) ($opts['maxStops'] ?? $settings['max_stops'] ?? 25));
    $depart = (string) ($opts['depart'] ?? date('c'));
    $units = array_values($units);
    usort($units, fn($a, $b) => $a['id'] <=> $b['id']);
    $unallocated = [];
    $U = []; // matrix index => unit
    $points = [[(float) $start[0], (float) $start[1]]];
    $idxById = [];
    foreach ($units as $u) {
        if ($u['lat'] === null || $u['lng'] === null || (abs((float) $u['lat']) < 1e-9 && abs((float) $u['lng']) < 1e-9)) { $unallocated[] = ['id' => $u['id'], 'reason' => 'no coordinates']; continue; }
        $points[] = [(float) $u['lat'], (float) $u['lng']];
        $U[count($points) - 1] = $u; $idxById[$u['id']] = count($points) - 1;
    }
    $matrix = $opts['matrix'] ?? null;
    if (!$matrix || count($matrix['m'] ?? []) !== count($points)) $matrix = rp_estimateMatrix($points, 'car', $settings);
    $M = $matrix['m'];

    // Riders
    $R = [];
    usort($riders, fn($a, $b) => (int) $a['id'] <=> (int) $b['id']);
    foreach ($riders as $r) {
        $v = $r['vehicle'] + ['max_kg' => 0, 'max_l' => 0, 'max_parcels' => 0];
        $used = ($r['used'] ?? []) + ['kg' => 0, 'l' => 0, 'n' => 0];
        $avail = !in_array($r['status'] ?? 'available', ['off', 'suspended', 'onway'], true) && !empty($v['max_kg']) && ($v['active'] ?? 1);
        $R[(int) $r['id']] = ['id' => (int) $r['id'], 'vehicle' => $v, 'base' => ['kg' => (float) $used['kg'], 'l' => (float) $used['l'], 'n' => (int) $used['n']], 'zone' => $r['zone'] ?? null, 'locked' => !empty($r['locked']), 'avail' => $avail, 'fixed' => [], 'set' => []];
    }
    // Fixed units (locked riders keep theirs; any rider's pinned units stay)
    $taken = [];
    foreach ($riders as $r) foreach ((array) ($r['units'] ?? []) as $uid) {
        $uid = (int) $uid;
        if (!isset($idxById[$uid]) || isset($taken[$uid])) continue;
        $R[(int) $r['id']]['fixed'][] = $idxById[$uid]; $taken[$uid] = true;
    }
    $loadOf = function (array $rr, array $set) use ($U) {
        $l = $rr['base'];
        foreach (array_merge($rr['fixed'], $set) as $i) { $l['kg'] += $U[$i]['kg']; $l['l'] += $U[$i]['l']; $l['n'] += $U[$i]['n']; }
        return $l;
    };
    $empty = ['kg' => 0, 'l' => 0, 'n' => 0];
    $maxRouteS = 60 * (int) ($opts['maxRouteMin'] ?? $settings['max_route_min'] ?? 480);
    $tourCache = [];
    $tour = function (array $idx) use (&$tourCache, $M) {
        sort($idx); $k = implode(',', $idx);
        return $tourCache[$k] ??= rp__tourM($M, $idx);
    };
    $routeS = function (array $rr, array $members) use ($tour, $U, $settings) {
        $s = $tour($members) / rp_speedMps(rp_vehicleType($rr['vehicle']['vehicle_type'] ?? 'car'), $settings);
        foreach ($members as $m) $s += rp_serviceS($U[$m], $settings);
        return $s;
    };
    $canTake = function (array $rr, array $set, int $i) use ($U, $loadOf, $maxStops, $maxRouteS, $routeS) {
        if ($rr['locked'] || !$rr['avail']) return 'rider unavailable';
        $why = rp_vehicleEligible($rr['vehicle'], $U[$i]);
        if ($why) return $why;
        if (count($rr['fixed']) + count($set) + 1 > $maxStops) return 'stop limit reached';
        if (!rp__fits($loadOf($rr, $set), $U[$i], $rr['vehicle'])) return 'no capacity left';
        return $routeS($rr, array_merge($rr['fixed'], $set, [$i])) > $maxRouteS ? 'route too long for one run' : null;
    };
    $free = array_values(array_filter(array_keys($U), fn($i) => !isset($taken[$U[$i]['id']])));

    // a/b. Seeds: zone centroid when the rider has a zone with units, else polar-angle sweep sectors.
    $centroid = function (array $idx) use ($points) {
        if (!$idx) return null;
        $la = $lo = 0; foreach ($idx as $i) { $la += $points[$i][0]; $lo += $points[$i][1]; }
        return [$la / count($idx), $lo / count($idx)];
    };
    $seed = []; $seededZones = [];
    foreach ($R as $id => $rr) {
        if (!$rr['avail'] || $rr['locked']) continue;
        $z = $rr['zone'];
        if ($z !== null && $z !== '') {
            $in = array_values(array_filter($free, fn($i) => (string) $U[$i]['zone_id'] === (string) $z));
            if ($in) { $seed[$id] = $centroid($in); $seededZones[(string) $z] = true; }
        }
    }
    $sweepRiders = array_values(array_filter(array_keys($R), fn($id) => $R[$id]['avail'] && !$R[$id]['locked'] && !isset($seed[$id])));
    if ($sweepRiders) {
        $rest = array_values(array_filter($free, fn($i) => !isset($seededZones[(string) $U[$i]['zone_id']])));
        $ang = fn($i) => atan2($points[$i][0] - $start[0], $points[$i][1] - $start[1]);
        usort($rest, fn($a, $b) => [$ang($a), $a] <=> [$ang($b), $b]);
        usort($sweepRiders, fn($a, $b) => [-(float) $R[$a]['vehicle']['max_kg'], $a] <=> [-(float) $R[$b]['vehicle']['max_kg'], $b]);
        $k = count($sweepRiders); $chunks = $rest ? array_chunk($rest, (int) ceil(count($rest) / $k)) : [];
        usort($chunks, fn($a, $b) => array_sum(array_map(fn($i) => $U[$i]['kg'], $b)) <=> array_sum(array_map(fn($i) => $U[$i]['kg'], $a)));
        foreach ($sweepRiders as $n => $id) $seed[$id] = isset($chunks[$n]) ? $centroid($chunks[$n]) : [(float) $start[0], (float) $start[1]];
    }

    // c. First-fit decreasing by dominant share; urgent first; nearest-centroid feasible rider.
    $cap = ['kg' => 1e-9, 'l' => 1e-9, 'n' => 1];
    foreach ($R as $rr) if ($rr['avail']) { $cap['kg'] = max($cap['kg'], (float) $rr['vehicle']['max_kg']); $cap['l'] = max($cap['l'], (float) $rr['vehicle']['max_l']); $cap['n'] = max($cap['n'], (int) $rr['vehicle']['max_parcels']); }
    $dom = fn($i) => max($U[$i]['kg'] / $cap['kg'], $U[$i]['l'] / $cap['l'], $U[$i]['n'] / $cap['n']);
    usort($free, fn($a, $b) => [-(int) $U[$a]['priority'], -count($U[$a]['handling']), -$dom($a), $U[$a]['id']] <=> [-(int) $U[$b]['priority'], -count($U[$b]['handling']), -$dom($b), $U[$b]['id']]);
    $scarce = function (array $v) use ($R) { // capabilities few available riders have
        $s = 0; $tot = max(1, count(array_filter($R, fn($r) => $r['avail'])));
        foreach (['can_cold', 'can_fragile', 'can_high_value'] as $c) if (!empty($v[$c]) && count(array_filter($R, fn($r) => $r['avail'] && !empty($r['vehicle'][$c]))) / $tot <= 0.5) $s++;
        return $s;
    };
    $dist = fn(array $a, array $b) => rp_haversineM($a[0], $a[1], $b[0], $b[1]);
    $failed = []; // matrix index => reason
    $reasonFor = function (array $reasons, array $byType) {
        // The most useful reason: a capable rider is full before a vehicle type is wrong.
        unset($reasons['rider unavailable']);
        foreach (['no capacity left', 'route too long for one run', 'stop limit reached'] as $k) if (isset($reasons[$k])) return $k;
        if (!$reasons) return 'no rider available';
        if (count($byType) === 1) return array_key_first($byType) . ': ' . $byType[array_key_first($byType)];
        // Per vehicle type, so the planner sees which fix works (e.g. the car only lacks cargo length).
        return 'no eligible vehicle: ' . implode('; ', array_map(fn($t, $w) => $t . ': ' . $w, array_keys($byType), $byType));
    };
    foreach ($free as $i) {
        $best = null; $bestScore = INF; $reasons = []; $byType = [];
        foreach ($R as $id => $rr) {
            $why = $canTake($rr, $rr['set'], $i);
            if ($why) {
                $reasons[$why] = true;
                if ($why !== 'rider unavailable') $byType[rp_vehicleType($rr['vehicle']['vehicle_type'] ?? 'car')] ??= $why;
                continue;
            }
            $members = array_merge($rr['fixed'], $rr['set']);
            $anchor = $members ? $centroid($members) : ($seed[$id] ?? [(float) $start[0], (float) $start[1]]);
            $score = $dist($points[$i], $anchor);
            if ($rr['zone'] !== null && $rr['zone'] !== '' && $U[$i]['zone_id'] !== null) $score += (string) $rr['zone'] === (string) $U[$i]['zone_id'] ? -1000 : 1500;
            if (!$members) $score += $mode === 'fill' ? 3000 : 0;
            if ($mode === 'balance') $score += 4000 * rp_fill($loadOf($rr, $rr['set']), $rr['vehicle'])['max'] / 100;
            if (!$U[$i]['handling']) $score += 400 * $scarce($rr['vehicle']);
            if ($score < $bestScore - 1e-9) { $bestScore = $score; $best = $id; }
        }
        if ($best === null) { $failed[$i] = $reasonFor($reasons, $byType); continue; }
        $R[$best]['set'][] = $i;
    }

    // d. Local search: relocate and swap between non-locked riders.
    $riderCost = function (array $rr, array $set) use ($tour, $loadOf, $U, $mode) {
        $members = array_merge($rr['fixed'], $set);
        if (!$members) return 0.0;
        $c = (float) $tour($members);
        if ($rr['zone'] !== null && $rr['zone'] !== '') foreach ($set as $i) if ($U[$i]['zone_id'] !== null && (string) $U[$i]['zone_id'] !== (string) $rr['zone']) $c += 1500;
        if ($mode === 'fill') $c += 4000 + 6000 * (1 - min(100, rp_fill($loadOf($rr, $set), $rr['vehicle'])['max']) / 100);
        return $c;
    };
    $balanceTerm = function () use (&$R, $loadOf) {
        $f = []; foreach ($R as $rr) if ($rr['avail'] && !$rr['locked']) $f[] = rp_fill($loadOf($rr, $rr['set']), $rr['vehicle'])['max'] / 100;
        if (count($f) < 2) return 0.0;
        $mu = array_sum($f) / count($f); $v = 0; foreach ($f as $x) $v += ($x - $mu) ** 2;
        return 20000 * $v / count($f);
    };
    $objective = function () use (&$R, $riderCost, $balanceTerm, $mode) {
        $t = 0.0; foreach ($R as $rr) $t += $riderCost($rr, $rr['set']);
        return $t + ($mode === 'balance' ? $balanceTerm() : 0);
    };
    $obj = $objective(); $moves = 0;
    $movable = array_keys(array_filter($R, fn($r) => $r['avail'] && !$r['locked']));
    for ($pass = 0, $imp = true; $imp && $pass < 12 && $moves < 400; $pass++) {
        $imp = false;
        foreach ($movable as $a) {
            foreach ($R[$a]['set'] as $pos => $i) {
                foreach ($movable as $b) {
                    if ($a === $b || $canTake($R[$b], $R[$b]['set'], $i)) continue;
                    $saveA = $R[$a]['set']; $saveB = $R[$b]['set'];
                    $R[$a]['set'] = array_values(array_diff($saveA, [$i])); $R[$b]['set'] = array_merge($saveB, [$i]);
                    $o = $objective();
                    if ($o < $obj - 1) { $obj = $o; $imp = true; $moves++; continue 3; }
                    $R[$a]['set'] = $saveA; $R[$b]['set'] = $saveB;
                }
            }
        }
        foreach ($movable as $ai => $a) foreach (array_slice($movable, $ai + 1) as $b) {
            foreach ($R[$a]['set'] as $i) foreach ($R[$b]['set'] as $j) {
                $saveA = $R[$a]['set']; $saveB = $R[$b]['set'];
                $setA = array_values(array_diff($saveA, [$i])); $setB = array_values(array_diff($saveB, [$j]));
                if ($canTake($R[$a], $setA, $j) || $canTake($R[$b], $setB, $i)) continue;
                $R[$a]['set'] = array_merge($setA, [$j]); $R[$b]['set'] = array_merge($setB, [$i]);
                $o = $objective();
                if ($o < $obj - 1) { $obj = $o; $imp = true; $moves++; continue 4; }
                $R[$a]['set'] = $saveA; $R[$b]['set'] = $saveB;
            }
        }
    }

    // e. Cheapest insertion: local search may have freed room, so retry every parcel left over
    // (same priority order) on the feasible rider whose tour grows least.
    foreach (array_values(array_filter($free, fn($i) => isset($failed[$i]))) as $i) {
        $best = null; $bestInc = INF; $reasons = []; $byType = [];
        foreach ($R as $id => $rr) {
            $why = $canTake($rr, $rr['set'], $i);
            if ($why) { $reasons[$why] = true; if ($why !== 'rider unavailable') $byType[rp_vehicleType($rr['vehicle']['vehicle_type'] ?? 'car')] ??= $why; continue; }
            $members = array_merge($rr['fixed'], $rr['set']);
            $inc = $tour(array_merge($members, [$i])) - $tour($members) + (!$members && $mode === 'fill' ? 3000 : 0);
            if ($inc < $bestInc - 1e-9) { $bestInc = $inc; $best = $id; }
        }
        if ($best === null) { $failed[$i] = $reasonFor($reasons, $byType); continue; }
        $R[$best]['set'][] = $i; unset($failed[$i]);
    }

    // Routes per rider. The final route (with time windows and waiting) is checked against the
    // max route length again; the stop farthest from the start leaves until it fits.
    $out = []; $totalM = 0; $used = 0; $fills = [];
    foreach ($R as $id => $rr) {
        $vtype = rp_vehicleType($rr['vehicle']['vehicle_type'] ?? 'car');
        $route = null;
        while (true) {
            $members = array_merge($rr['fixed'], $rr['set']);
            sort($members);
            if (!$members) { $route = null; break; }
            $sub = rp_matrixForVehicle(rp_subMatrix($matrix, array_merge([0], $members)), $vtype, $settings);
            $stops = array_map(fn($i) => ['id' => $U[$i]['id'], 'lat' => $U[$i]['lat'], 'lng' => $U[$i]['lng'], 'window_start' => $U[$i]['window_start'], 'window_end' => $U[$i]['window_end'], 'priority' => $U[$i]['priority'], 'service_s' => rp_serviceS($U[$i], $settings)], $members);
            $route = rp_optimiseRoute($start, $stops, $sub, $depart, ['settings' => $settings, 'vehicle_type' => $vtype]);
            if ($route['total_s'] <= $maxRouteS || !$rr['set']) break;
            $far = null; $farM = -1;
            foreach ($rr['set'] as $i) if ($M[0][$i] > $farM || ($M[0][$i] === $farM && $i > $far)) { $farM = $M[0][$i]; $far = $i; }
            $rr['set'] = array_values(array_diff($rr['set'], [$far]));
            $failed[$far] = 'route too long for one run';
        }
        $R[$id] = $rr;
        $load = $loadOf($rr, $rr['set']);
        if ($route) {
            $totalM += $route['total_m']; $used++;
            $fills[] = rp_fill($load, $rr['vehicle'])['max'];
        }
        $out[$id] = ['units' => array_map(fn($i) => $U[$i]['id'], $members), 'fixed' => array_map(fn($i) => $U[$i]['id'], $rr['fixed']), 'locked' => $rr['locked'], 'load' => ['kg' => round($load['kg'], 2), 'l' => round($load['l'], 2), 'n' => $load['n']], 'fill' => rp_fill($load, $rr['vehicle']), 'route' => $route];
    }
    foreach ($failed as $i => $reason) $unallocated[] = ['id' => $U[$i]['id'], 'reason' => $reason];
    usort($unallocated, fn($a, $b) => $a['id'] <=> $b['id']);
    return ['riders' => $out, 'unallocated' => $unallocated, 'score' => ['mode' => $mode, 'total_m' => $totalM, 'riders_used' => $used, 'avg_fill' => $fills ? round(array_sum($fills) / count($fills), 1) : 0.0, 'objective' => round($obj, 1), 'moves' => $moves, 'provider' => $matrix['provider'] ?? 'estimate']];
}

/**
 * Server-side ping throttle. $last: ['lat','lng','recorded_at' (ts)] or null.
 * Returns ['store' => bool, 'next_in_s' => int, 'reason' => string].
 */
function rp_shouldStorePing(?array $last, array $p, array $settings, int $now): array
{
    $minS = max(5, (int) ($settings['ping_min_s'] ?? 20)); $minM = max(5, (int) ($settings['ping_min_m'] ?? 40));
    if (!isset($p['lat'], $p['lng']) || !is_numeric($p['lat']) || !is_numeric($p['lng']) || $p['lat'] < -90 || $p['lat'] > 90 || $p['lng'] < -180 || $p['lng'] > 180) return ['store' => false, 'next_in_s' => $minS, 'reason' => 'invalid coordinates'];
    if (isset($p['accuracy']) && $p['accuracy'] !== null && (float) $p['accuracy'] > 500) return ['store' => false, 'next_in_s' => $minS, 'reason' => 'inaccurate'];
    if (!$last) return ['store' => true, 'next_in_s' => $minS, 'reason' => 'first'];
    $age = $now - (int) $last['recorded_at'];
    $moved = rp_haversineM((float) $last['lat'], (float) $last['lng'], (float) $p['lat'], (float) $p['lng']);
    if ($age >= $minS && $moved >= $minM) return ['store' => true, 'next_in_s' => $minS, 'reason' => 'moved'];
    // Stationary riders still report every 5 x the interval, so "stuck" and "offline" stay accurate.
    if ($age >= $minS * 5) return ['store' => true, 'next_in_s' => $minS, 'reason' => 'heartbeat'];
    return ['store' => false, 'next_in_s' => max(1, $minS - max(0, $age)), 'reason' => $age < $minS ? 'too soon' : 'not moved'];
}

/**
 * Alerts for one route. $route: status, started_at, loading_since (ts or datetime).
 * $stops: [{id, seq, status, eta, window_end, label}]. $lastPing/$recentPings: [lat, lng, recorded_at].
 */
function rp_detectAlerts(array $route, array $stops, ?array $lastPing, array $recentPings, array $settings, int $now): array
{
    $ts = fn($v) => $v === null || $v === '' ? null : (is_numeric($v) ? (int) $v : (strtotime((string) $v) ?: null));
    $out = []; $status = $route['status'] ?? 'planned'; $who = $route['rider_name'] ?? 'Rider';
    $onBreak = ($route['activity'] ?? null) === 'break';
    if ($status === 'onroute') {
        $last = $lastPing ? $ts($lastPing['recorded_at']) : null;
        $offMin = (int) ($settings['offline_min'] ?? 10);
        if ($last === null || $now - $last > $offMin * 60) {
            $out[] = ['type' => 'offline', 'severity' => 'warn', 'message' => $who . ($last ? ' has not sent a position for ' . (int) floor(($now - $last) / 60) . ' min' : ' has not shared a position yet')];
        } else {
            $win = (int) ($settings['stuck_min'] ?? 12) * 60;
            $pts = array_values(array_filter($recentPings, fn($p) => $ts($p['recorded_at']) !== null && $ts($p['recorded_at']) >= $now - $win - 60));
            $arrived = (bool) array_filter($stops, fn($s) => ($s['status'] ?? '') === 'arrived');
            if (count($pts) >= 3 && !$arrived && !$onBreak) {
                $times = array_map(fn($p) => $ts($p['recorded_at']), $pts);
                if (max($times) - min($times) >= $win * 0.8) {
                    $maxD = 0;
                    foreach ($pts as $p) $maxD = max($maxD, rp_haversineM((float) $pts[0]['lat'], (float) $pts[0]['lng'], (float) $p['lat'], (float) $p['lng']));
                    if ($maxD < 150) $out[] = ['type' => 'stuck', 'severity' => 'warn', 'message' => $who . ' has not moved for ' . (int) round((max($times) - min($times)) / 60) . ' min'];
                }
            }
        }
        $lateMin = (int) ($settings['late_min'] ?? 15);
        foreach ($stops as $s) {
            if (!in_array($s['status'] ?? 'pending', ['pending', 'arrived'], true)) continue;
            $eta = $ts($s['eta'] ?? null);
            if ($eta !== null && $now > $eta + $lateMin * 60) {
                $mins = (int) floor(($now - $eta) / 60);
                $out[] = ['type' => 'late', 'severity' => $mins > 2 * $lateMin ? 'high' : 'warn', 'message' => 'Stop ' . ($s['seq'] ?? '') . ($s['label'] ?? '' ? ' (' . $s['label'] . ')' : '') . ' is ' . $mins . ' min behind its ETA' . ($onBreak ? ' (rider on break)' : ''), 'stop_id' => (int) $s['id']];
                break; // the first late stop is enough: the rest follow from it
            }
        }
    }
    if ($status === 'loading') {
        $since = $ts($route['loading_since'] ?? $route['updated_at'] ?? null);
        $idle = (int) ($settings['idle_loading_min'] ?? 30);
        if ($since !== null && $now - $since > $idle * 60) $out[] = ['type' => 'idle_loading', 'severity' => 'info', 'message' => $who . ' has been loading for ' . (int) floor(($now - $since) / 60) . ' min'];
    }
    return $out;
}
