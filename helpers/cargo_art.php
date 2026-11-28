<?php
/**
 * Which of the owner's illustrations (assets/swiftlane/cargo, stored unaltered) goes with each shipment status.
 * Every status in cdb_styles has a picture for air and one for sea: the owner's choice saved on Settings ›
 * Status pictures (cdb_sl_settings.cargo_art_map, JSON {"air":{status_id:key},"sea":{…}}), else a default by
 * status id, else by words in the status name, so a status the team adds later ("Accepted by airline") still
 * gets a sensible picture.
 */
require_once __DIR__ . '/sl_view.php';

/** Which pictures belong to air and which to sea: air shipments only ever show air pictures, sea only sea. */
function sl_cargoArtModes(): array
{
    return ['air' => ['plane'], 'sea' => ['packing', 'loading', 'ship', 'yard', 'opened']];
}

/** The pictures of one mode, key => [file, description, short name]. */
function sl_cargoArtOf(bool $sea): array
{
    return array_intersect_key(sl_cargoArt(), array_flip(sl_cargoArtModes()[$sea ? 'sea' : 'air']));
}

/** The pictures: key => [file, description, short name]. */
function sl_cargoArt(): array
{
    return [
        'plane'   => ['air-cargo-plane.webp', 'Cargo aircraft with its main-deck cargo', 'Plane'],
        'packing' => ['sea-1-packing-container.webp', 'Container being packed', 'Packing'],
        'loading' => ['sea-2-loading-at-port.webp', 'Container lifted onto the ship at port', 'Loading at port'],
        'ship'    => ['sea-3-ship-at-sea.webp', 'Container ship at sea', 'Ship at sea'],
        'yard'    => ['sea-4-container-yard.webp', 'Containers in the port yard', 'Port yard'],
        'opened'  => ['sea-5-container-opened.webp', 'Container opened, cargo ready to hand out', 'Opened container'],
    ];
}

/** Default picture for a status (by id for the built-in statuses, by its words for any other). */
function sl_cargoArtDefault(int $statusId, string $name, bool $sea): string
{
    if (!$sea) {
        return 'plane'; // the only air picture; more air pictures go in sl_cargoArtModes()['air']
    }
    $byId = [
        // booked, approved, waiting, being received or packed
        1 => 'packing', 2 => 'packing', 10 => 'packing', 11 => 'packing', 12 => 'packing', 14 => 'packing', 17 => 'packing', 18 => 'packing', 19 => 'packing', 21 => 'packing', 23 => 'packing',
        // consolidated / handed to the carrier
        13 => $sea ? 'loading' : 'plane',
        // moving
        3 => $sea ? 'ship' : 'plane',
        // arrived, in the warehouse, held, sorting
        4 => 'yard', 5 => 'yard', 25 => 'yard', 28 => 'yard', 29 => 'yard',
        // ready, out for delivery, delivered, collected
        6 => 'opened', 7 => 'opened', 8 => 'opened', 15 => 'opened', 16 => 'opened', 32 => 'opened',
    ];
    if (isset($byId[$statusId])) return $byId[$statusId];
    $n = strtolower($name);
    if (preg_match('/airline|accepted|loaded|load|depart|board|flight|vessel|sail|handed to (the )?carrier|manifest/', $n)) return $sea ? 'loading' : 'plane';
    if (preg_match('/transit|in the air|at sea|en route|on the way|shipped/', $n)) return $sea ? 'ship' : 'plane';
    if (preg_match('/arriv|custom|clear|warehouse|port|yard|landed|held|hold|sort/', $n)) return 'yard';
    if (preg_match('/ready|deliver|collect|picked|hand ?out|handed over|available/', $n)) return 'opened';
    return 'packing';
}

/** Saved choices, as stored. */
function sl_cargoArtMap(): array
{
    static $map = null;
    if ($map === null) {
        $raw = function_exists('sl_slSetting') ? sl_slSetting('cargo_art_map', '') : (sl_tableExists('cdb_sl_settings') ? sl_scalar('SELECT setting_value FROM cdb_sl_settings WHERE setting_key = :k', [':k' => 'cargo_art_map'], '') : '');
        $map = json_decode((string) $raw, true) ?: [];
        $map += ['air' => [], 'sea' => []];
    }
    return $map;
}

/** The picture for a status: ['key', 'file' (asset path), 'alt', 'custom' => bool]. */
function sl_cargoArtFor(int $statusId, string $name, bool $sea): array
{
    $art = sl_cargoArtOf($sea);
    $saved = sl_cargoArtMap()[$sea ? 'sea' : 'air'][(string) $statusId] ?? '';
    $key = isset($art[$saved]) ? $saved : sl_cargoArtDefault($statusId, $name, $sea);
    return ['key' => $key, 'file' => 'assets/swiftlane/cargo/' . $art[$key][0], 'alt' => $art[$key][1], 'custom' => isset($art[$saved])];
}

/** Every status with its air and sea picture, for the settings page. */
function sl_cargoArtRows(): array
{
    $out = [];
    foreach (sl_rows('SELECT id, mod_style, detail FROM cdb_styles ORDER BY id') as $s) {
        $name = trim(str_replace('_', ' ', (string) $s->mod_style));
        $out[] = ['id' => (int) $s->id, 'name' => $name, 'detail' => trim((string) $s->detail),
            'air' => sl_cargoArtFor((int) $s->id, $name . ' ' . $s->detail, false), 'sea' => sl_cargoArtFor((int) $s->id, $name . ' ' . $s->detail, true)];
    }
    return $out;
}
