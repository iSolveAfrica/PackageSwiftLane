<?php
/**
 * Ghana destinations map: groups recipient cities by region for the interactive map card
 * (views/sl/pages/partials/overview_ghana_map.php).
 *
 * Data files (generated, see assets/swiftlane/maps/README.md):
 *   assets/swiftlane/maps/ghana-map.json     outline of Ghana (Natural Earth via @geo-maps, MIT),
 *                                            the 16 regions with their capitals, and the projection
 *   assets/swiftlane/maps/ghana-places.json  place name => [region, lat, lon] (Wikidata via @geomelon/ghana, CC0)
 * No database access here, so it is safe to call from any chart builder.
 */

function sl_ghMap(): array
{
    static $m = null;
    if ($m === null) {
        $f = dirname(__DIR__, 3) . '/assets/swiftlane/maps/ghana-map.json';
        $m = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    }
    return $m;
}

function sl_ghPlaces(): array
{
    static $p = null;
    if ($p === null) {
        $f = dirname(__DIR__, 3) . '/assets/swiftlane/maps/ghana-places.json';
        $p = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    }
    return $p;
}

/** Map canvas point for a latitude / longitude. */
function sl_ghXY(float $lat, float $lon): array
{
    [$lon0, $lat1, $k, $s] = sl_ghMap()['proj'];
    return [round(($lon - $lon0) * $k * $s, 1), round(($lat1 - $lat) * $s, 1)];
}

/** Region key and coordinates for a recipient city / state; null when it cannot be placed. */
function sl_ghLocate(string $city, string $state = ''): ?array
{
    $map = sl_ghMap();
    if (!$map) return null;
    $norm = fn($s) => trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 \-]/', ' ', strtolower($s))));
    $regionFrom = function (string $s) use ($map, $norm): ?string {
        $s = preg_replace('/\b(region|reg)\b/', '', $norm($s));
        $s = trim(str_replace('-', ' ', $s));
        if ($s === '') return null;
        foreach ($map['regions'] as $k => $r) if (str_replace('-', ' ', $k) === $s || strtolower($r['name']) === $s) return $k;
        if (in_array($s, ['ga', 'gar', 'accra region', 'gt accra', 'gr accra'], true)) return 'greater-accra';
        if ($s === 'brong ahafo' || $s === 'ba') return 'bono';
        return null;
    };
    $places = sl_ghPlaces();
    $c = $norm($city);
    $hit = $places[$c] ?? null;
    if (!$hit && $c !== '') {
        // "Medina Estates", "Spintex Road", "East Legon Hills": try shorter word runs, longest first
        $w = explode(' ', $c);
        for ($len = count($w) - 1; $len >= 1 && !$hit; $len--) {
            for ($i = 0; $i + $len <= count($w) && !$hit; $i++) {
                $hit = $places[implode(' ', array_slice($w, $i, $len))] ?? null;
            }
        }
        if (!$hit && str_contains($c, 'accra')) $hit = ['greater-accra', null, null];
        if (!$hit && str_contains($c, 'kumasi')) $hit = ['ashanti', null, null];
    }
    $stateRegion = $regionFrom($state) ?? $regionFrom($city);
    $region = $stateRegion ?? ($hit[0] ?? null);
    if (!$region) return null;
    $lat = $hit && $hit[0] === $region ? $hit[1] : null;
    $lon = $hit && $hit[0] === $region ? $hit[2] : null;
    return ['region' => $region, 'lat' => $lat, 'lon' => $lon];
}

/**
 * Region totals and per-location rows for the map.
 * $rows: objects with city, n and optionally state.
 */
function sl_ghDestinations(array $rows): array
{
    $map = sl_ghMap();
    if (!$map) return [];
    $regions = [];
    foreach ($map['regions'] as $k => $r) $regions[$k] = ['key' => $k, 'name' => $r['name'], 'capital' => $r['capital'], 'xy' => $r['xy'], 'n' => 0, 'places' => []];
    $unplaced = 0; $unplacedList = [];
    foreach ($rows as $row) {
        $city = trim((string) ($row->city ?? ''));
        $n = (int) ($row->n ?? 0);
        $loc = $city === '' || $city === 'Not recorded' ? null : sl_ghLocate($city, (string) ($row->state ?? ''));
        if (!$loc) { $unplaced += $n; if ($city !== '') $unplacedList[] = ['name' => $city, 'n' => $n]; continue; }
        $rk = $loc['region'];
        $regions[$rk]['n'] += $n;
        $key = strtolower($city);
        if (isset($regions[$rk]['places'][$key])) { $regions[$rk]['places'][$key]['n'] += $n; continue; }
        $xy = $loc['lat'] !== null ? sl_ghXY((float) $loc['lat'], (float) $loc['lon']) : null;
        $regions[$rk]['places'][$key] = ['name' => ucwords(strtolower($city)), 'n' => $n, 'xy' => $xy];
    }
    $total = array_sum(array_column($regions, 'n'));
    $peak = max(1, max(array_column($regions, 'n') ?: [0]));
    foreach ($regions as &$r) {
        $r['places'] = array_values($r['places']);
        usort($r['places'], fn($a, $b) => $b['n'] <=> $a['n']);
        $r['pct'] = $total ? (int) round($r['n'] / $total * 100) : 0;
        $r['level'] = $r['n'] ? 1 + (int) floor(3 * $r['n'] / ($peak + 1)) : 0; // 0..3
        // places without coordinates sit in a small ring around the capital
        $ring = 0;
        foreach ($r['places'] as &$p) {
            if ($p['xy'] === null) {
                $a = $ring * 2.4;
                $rad = 9 + 3 * floor($ring / 6);
                $p['xy'] = [round($r['xy'][0] + cos($a) * $rad, 1), round($r['xy'][1] + sin($a) * $rad, 1)];
                $p['approx'] = true;
                $ring++;
            }
        }
        unset($p);
    }
    unset($r);
    uasort($regions, fn($a, $b) => $b['n'] <=> $a['n']);
    usort($unplacedList, fn($a, $b) => $b['n'] <=> $a['n']);
    return ['w' => $map['w'], 'h' => $map['h'], 'outline' => $map['outline'], 'regions' => $regions, 'total' => $total,
        'unplaced' => $unplaced, 'unplacedList' => array_slice($unplacedList, 0, 12), 'top' => array_key_first($regions)];
}
