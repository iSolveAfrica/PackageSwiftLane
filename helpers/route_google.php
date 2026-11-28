<?php
/**
 * Google Routes computeRouteMatrix and address geocoding for the route planner.
 * Extends DeliveryGoogle (helpers/delivery_google.php, unchanged) so the key handling and the
 * error policy stay the same. Callers (rp_providerMatrix, rp_geocodeText) fall back to the
 * offline estimate on any exception.
 */
require_once __DIR__ . '/delivery_google.php';

class RouteGoogle extends DeliveryGoogle
{
    private string $routeKey;

    public function __construct(string $key)
    {
        parent::__construct($key);
        $this->routeKey = $key;
    }

    /**
     * Distance/duration matrix. $origins/$destinations: [[lat, lng], ...] (at most 25 x 25).
     * $mode: DRIVE | TWO_WHEELER. Returns ['m' => int[][], 's' => int[][], 'provider' => 'google'].
     */
    public function matrix(array $origins, array $destinations, string $mode = 'DRIVE'): array
    {
        if (count($origins) * count($destinations) > 625 || !$origins || !$destinations) throw new DomainException('Matrix too large');
        $wp = fn($p) => ['waypoint' => ['location' => ['latLng' => ['latitude' => (float) $p[0], 'longitude' => (float) $p[1]]]]];
        $mode = in_array($mode, ['DRIVE', 'TWO_WHEELER'], true) ? $mode : 'DRIVE';
        $body = ['origins' => array_map($wp, array_values($origins)), 'destinations' => array_map($wp, array_values($destinations)), 'travelMode' => $mode, 'routingPreference' => 'TRAFFIC_AWARE'];
        $rows = $this->callMatrix($body);
        $n = count($origins); $k = count($destinations);
        $m = $s = [];
        for ($i = 0; $i < $n; $i++) for ($j = 0; $j < $k; $j++) { $m[$i][$j] = $i === $j && $n === $k ? 0 : null; $s[$i][$j] = $m[$i][$j]; }
        foreach ($rows as $el) {
            $i = (int) ($el['originIndex'] ?? -1); $j = (int) ($el['destinationIndex'] ?? -1);
            if (!isset($m[$i]) || !array_key_exists($j, $m[$i])) continue;
            if (($el['condition'] ?? '') !== 'ROUTE_EXISTS') continue;
            $m[$i][$j] = (int) ($el['distanceMeters'] ?? 0);
            $s[$i][$j] = (int) rtrim((string) ($el['duration'] ?? '0s'), 's');
        }
        foreach ($m as $i => $row) foreach ($row as $j => $v) if ($v === null) throw new DomainException('Google returned no route between two stops');
        return ['m' => $m, 's' => $s, 'provider' => 'google'];
    }

    /** Free-text address to coordinates (Geocoding API). Null when Google finds nothing precise. */
    public function geocodeText(string $address, string $country = 'GH'): ?array
    {
        $data = $this->call('https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query(['address' => $address, 'components' => 'country:' . $country, 'key' => $this->routeKey]));
        $r = $data['results'][0] ?? null;
        if (($data['status'] ?? '') !== 'OK' || !$r || !empty($r['partial_match'])) return null;
        $p = $r['geometry']['location'] ?? [];
        return isset($p['lat'], $p['lng']) ? ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng'], 'address' => (string) ($r['formatted_address'] ?? $address)] : null;
    }

    /** computeRouteMatrix streams a JSON array; 5 s timeout so planning never hangs on the network. */
    private function callMatrix(array $body): array
    {
        if ($this->routeKey === '') throw new DomainException('Google Maps server key is not configured');
        $ch = curl_init('https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body), CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Goog-Api-Key: ' . $this->routeKey, 'X-Goog-FieldMask: originIndex,destinationIndex,distanceMeters,duration,condition']]);
        $raw = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $data = json_decode((string) $raw, true);
        if ($status !== 200 || !is_array($data) || isset($data['error'])) throw new DomainException('Google route matrix unavailable');
        return $data;
    }
}
