<?php
/** Maps URLs built from assigned addresses; never invent coordinates or ETAs. */
function sl_riderNavigation(string $address): string
{
    if (trim($address) === '' || $address === '—') return '';
    return 'https://www.google.com/maps/dir/?' . http_build_query(['api' => 1, 'destination' => $address, 'travelmode' => 'driving'], '', '&', PHP_QUERY_RFC3986);
}
function sl_riderRouteMap(array $jobs): array
{
    $stops = array_values(array_filter($jobs, fn($j) => trim($j['addr']) !== '' && $j['addr'] !== '—'));
    // Embedded Maps supports address-based routes. Limit each preview to a leg
    // so long routes never truncate silently; every stop remains in the list.
    $first = $stops[0]['addr'] ?? '';
    $second = $stops[1]['addr'] ?? '';
    $query = $second ? ['saddr' => $first, 'daddr' => $second, 'output' => 'embed'] : ['q' => $first, 'z' => 15, 'output' => 'embed'];
    return ['stops' => $stops, 'embed' => $first ? 'https://maps.google.com/maps?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '', 'leg' => $second !== ''];
}
