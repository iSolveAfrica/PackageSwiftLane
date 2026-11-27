<?php
/**
 * Rider app view model of today's route (input: rp_riderTodayRoute() / rp_route() output).
 * Formats stops, ETAs, the nearest-first order, inventory, load meter and navigation links for
 * views/sl/pages/DriverApp.php and its rider_* partials. No queries here.
 */

/** Seconds as "1 h 05 min" / "12 min". */
function sl_rrDuration(int $s): string
{
    $s = max(0, $s);
    $m = (int) round($s / 60);
    if ($m < 60) return $m . ' min';
    return intdiv($m, 60) . ' h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT) . ' min';
}

/** Metres as "850 m" / "12.4 km". */
function sl_rrDistance(int $m): string
{
    return $m < 1000 ? max(0, $m) . ' m' : number_format($m / 1000, $m < 10000 ? 1 : 0) . ' km';
}

function sl_rrTime(?string $iso): string
{
    return $iso ? date('H:i', strtotime($iso)) : '';
}

/** Ghana numbers stored as 0XXXXXXXXX or 233XXXXXXXXX become dialable international numbers. */
function sl_rrPhone(string $phone): array
{
    $digits = preg_replace('/\D/', '', $phone);
    if ($digits === '') return ['tel' => '', 'wa' => ''];
    if (str_starts_with(trim($phone), '+')) $intl = $digits;
    elseif (strlen($digits) === 10 && $digits[0] === '0') $intl = '233' . substr($digits, 1);
    elseif (strlen($digits) === 12 && str_starts_with($digits, '233')) $intl = $digits;
    else $intl = '';
    return ['tel' => $intl ? '+' . $intl : $digits, 'wa' => $intl && preg_match('/^[1-9][0-9]{7,14}$/', $intl) ? 'https://wa.me/' . $intl : ''];
}

const SL_RR_STATUS = [
    'pending' => ['To deliver', 'plain'], 'arrived' => ['Arrived', 'pending'], 'delivered' => ['Delivered', 'delivered'],
    'failed' => ['Failed', 'overdue'], 'returned' => ['Returned', 'overdue'], 'removed' => ['Removed', 'skipped'],
];
const SL_RR_ROUTE_STATUS = ['planned' => 'Ready to load', 'loading' => 'Loading', 'onroute' => 'On the way', 'done' => 'Finished'];
const SL_RR_FAIL_REASONS = ['Customer not available', 'Wrong or incomplete address', 'Customer refused the parcel', 'Customer asked for another day', 'Could not reach the customer by phone', 'Unsafe to deliver'];

function sl_riderRouteView(array $route): array
{
    $r = $route['route'] ?? [];
    $v = $route['vehicle'] ?? [];
    $inv = $route['inventory'] ?? [];
    $status = (string) ($r['status'] ?? 'planned');
    $nearestDist = [];
    foreach ($route['nearest'] ?? [] as $i => $n) $nearestDist[(int) $n['id']] = ['rank' => $i + 1, 'm' => (int) ($n['dist_m'] ?? 0)];

    $stops = [];
    $leftS = 0; $leftM = 0; $open = 0;
    foreach ($route['stops'] ?? [] as $s) {
        if (($s['status'] ?? '') === 'removed') continue;
        $isOpen = in_array($s['status'], ['pending', 'arrived'], true);
        if ($isOpen) { $open++; $leftS += (int) $s['leg_s']; $leftM += (int) $s['leg_m']; }
        $ph = sl_rrPhone((string) ($s['contact_phone'] ?? ''));
        [$stLabel, $stCls] = SL_RR_STATUS[$s['status']] ?? [ucfirst((string) $s['status']), 'plain'];
        $win = ($s['window_start'] || $s['window_end']) ? trim(($s['window_start'] ?? '') . '–' . ($s['window_end'] ?? ''), '–') : '';
        $loaded = count(array_filter($s['parcels'] ?? [], fn($p) => ($p['load_state'] ?? '') === 'loaded'));
        $stops[] = [
            'id' => (int) $s['id'], 'request_id' => (int) $s['request_id'], 'seq' => (int) $s['seq'],
            'name' => (string) ($s['contact_name'] ?: 'Customer'), 'area' => (string) ($s['area'] ?? ''), 'address' => (string) ($s['address'] ?? ''),
            'phone' => (string) ($s['contact_phone'] ?? ''), 'tel' => $ph['tel'], 'wa' => $ph['wa'],
            'lat' => (float) $s['lat'], 'lng' => (float) $s['lng'],
            'eta' => sl_rrTime($s['eta'] ?? null), 'eta_iso' => $s['eta'] ?? null, 'leg_m' => (int) $s['leg_m'], 'leg_s' => (int) $s['leg_s'],
            'leg' => sl_rrDistance((int) $s['leg_m']), 'window' => $win, 'late' => !empty($s['late']), 'priority' => (int) ($s['priority'] ?? 0),
            'status' => (string) $s['status'], 'status_label' => $stLabel, 'cls' => $stCls, 'open' => $isOpen,
            'request_state' => (string) ($s['request_state'] ?? ''), 'instructions' => (string) ($s['instructions'] ?? ''),
            'n' => (int) ($s['n'] ?? 0), 'kg' => (float) ($s['kg'] ?? 0), 'l' => (float) ($s['l'] ?? 0), 'loaded' => $loaded,
            'handling' => $s['handling'] ?? [], 'parcels' => $s['parcels'] ?? [],
            'nav_google' => (string) ($s['nav']['google'] ?? ''), 'nav_apple' => (string) ($s['nav']['apple'] ?? ''),
            'job_url' => 'delivery_job.php?' . http_build_query(['id' => (int) $s['request_id'], 'from' => 'route']),
            'fail_reason' => (string) ($s['fail_reason'] ?? ''), 'done_at' => $s['done_at'] ? date('H:i', strtotime($s['done_at'])) : '',
            'near_rank' => $nearestDist[(int) $s['id']]['rank'] ?? 999, 'near_m' => $nearestDist[(int) $s['id']]['m'] ?? null,
        ];
    }
    $openStops = array_values(array_filter($stops, fn($s) => $s['open']));
    $next = null;
    foreach ($openStops as $s) if ($s['status'] === 'arrived') { $next = $s; break; }
    $next = $next ?: ($openStops[0] ?? null);
    $pct = fn($a, $b) => $b > 0 ? (int) min(100, round($a / $b * 100)) : 0;
    $maxKg = (float) ($v['max_kg'] ?? 0); $maxL = (float) ($v['max_l'] ?? 0); $maxN = (int) ($v['max_parcels'] ?? 0);
    $onKg = (float) ($inv['onboard_kg'] ?? $r['load_kg'] ?? 0); $onL = (float) ($inv['onboard_l'] ?? $r['load_l'] ?? 0); $onN = (int) ($inv['onboard'] ?? $r['load_n'] ?? 0);
    if ($status === 'planned' || $status === 'loading') { $onKg = (float) ($inv['kg'] ?? 0); $onL = (float) ($inv['l'] ?? 0); $onN = (int) ($inv['total'] ?? 0); }
    $load = [
        ['k' => 'kg', 'label' => 'Weight', 'val' => rtrim(rtrim(number_format($onKg, 1), '0'), '.') . ' / ' . rtrim(rtrim(number_format($maxKg, 1), '0'), '.') . ' kg', 'pct' => $pct($onKg, $maxKg)],
        ['k' => 'l', 'label' => 'Volume', 'val' => rtrim(rtrim(number_format($onL, 1), '0'), '.') . ' / ' . rtrim(rtrim(number_format($maxL, 1), '0'), '.') . ' L', 'pct' => $pct($onL, $maxL)],
        ['k' => 'n', 'label' => 'Parcels', 'val' => $onN . ' / ' . $maxN, 'pct' => $pct($onN, $maxN)],
    ];
    $loadGroups = [];
    foreach ($stops as $s) if ($s['parcels'] && $s['status'] !== 'removed') $loadGroups[] = ['stop' => $s, 'parcels' => $s['parcels']];
    $prog = $route['progress'] ?? ['done' => 0, 'total' => count($stops), 'delivered' => 0, 'failed' => 0];
    $estimate = ($route['provider'] ?? 'estimate') !== 'google';
    $vehLabel = ucfirst((string) ($v['vehicle_type'] ?? 'vehicle'));

    // Compact payload for dataJs/sl/rider_route.js (map, ETAs, nearest-first, pings).
    $json = [
        'route_id' => (int) ($r['id'] ?? 0), 'status' => $status, 'version' => (int) ($r['version'] ?? 0), 'provider' => $route['provider'] ?? 'estimate',
        'settings' => $route['settings'] ?? [], 'tz_offset_min' => intdiv((int) date('Z'), 60), 'generated_at' => $route['generated_at'] ?? date('c'),
        'map' => $route['map'] ?? null, 'next_id' => $next['id'] ?? null,
        'stops' => array_map(fn($s) => ['id' => $s['id'], 'request_id' => $s['request_id'], 'seq' => $s['seq'], 'lat' => $s['lat'], 'lng' => $s['lng'], 'label' => $s['name'], 'status' => $s['status'], 'eta' => $s['eta_iso'], 'leg_s' => $s['leg_s'], 'n' => $s['n']], $stops),
    ];

    return [
        'id' => (int) ($r['id'] ?? 0), 'status' => $status, 'status_label' => SL_RR_ROUTE_STATUS[$status] ?? ucfirst($status),
        'can_load' => in_array($status, ['planned', 'loading', 'onroute'], true), 'onroute' => $status === 'onroute', 'done' => $status === 'done',
        'version' => (int) ($r['version'] ?? 0), 'estimate' => $estimate,
        'total' => sl_rrDistance((int) ($r['total_m'] ?? 0)), 'total_time' => sl_rrDuration((int) ($r['total_s'] ?? 0)),
        'left_km' => sl_rrDistance($leftM), 'left_time' => sl_rrDuration($leftS), 'open' => $open,
        'finish_eta' => $openStops ? sl_rrTime(end($openStops)['eta_iso']) : '',
        'stops' => $stops, 'open_stops' => $openStops, 'next' => $next, 'progress' => $prog,
        'inventory' => $inv + ['total' => 0, 'loaded' => 0, 'pending_load' => 0, 'missing' => 0, 'onboard' => 0, 'delivered' => 0, 'failed' => 0, 'returned' => 0, 'by_type' => []],
        'load' => $load, 'load_groups' => $loadGroups, 'all_loaded' => (int) ($inv['pending_load'] ?? 0) === 0 && (int) ($inv['total'] ?? 0) > 0,
        'vehicle' => ['type' => $vehLabel, 'plate' => (string) ($v['plate'] ?? ''), 'model' => (string) ($v['make_model'] ?? ''), 'can_cold' => !empty($v['can_cold']), 'can_fragile' => !empty($v['can_fragile'])],
        'start' => $route['start'] ?? [], 'alerts' => $route['alerts'] ?? [],
        'started_at' => !empty($r['started_at']) ? date('H:i', strtotime($r['started_at'])) : '',
        'fail_reasons' => SL_RR_FAIL_REASONS,
        'json' => $json,
    ];
}
