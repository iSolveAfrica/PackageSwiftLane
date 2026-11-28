<?php
/**
 * Seed realistic route-planner data for local development (idempotent: previous RPSEED rows are
 * removed and recreated; real rows are never touched).
 *   php scripts/seed_route_planner.php            riders, vehicles, zones, ~40 pending deliveries
 *   php scripts/seed_route_planner.php --history  also 7 days of rider activity and pings (stats)
 *   php scripts/seed_route_planner.php --clean    only remove the seeded rows
 * Refuses unless the database name is "swiftlane" or contains "test".
 * Seeded rows are tagged: users.notes = 'RPSEED', order notes 'RPSEED', request place_id 'RPSEED-*'.
 */
require_once __DIR__ . '/../loader.php';
require_once CDP_ROOT . '/helpers/route_planner.php';
// Same clock as the web pages (Core sets the site time zone), so seeded times line up with them.
if (class_exists('Core')) { try { new Core(); } catch (Throwable $e) { /* keep the CLI default */ } }
if (!(CDP_DB_NAME === 'swiftlane' || preg_match('/test/i', CDP_DB_NAME))) { fwrite(STDERR, "Refusing to seed database " . CDP_DB_NAME . " (swiftlane or *test* only)\n"); exit(1); }
$args = array_slice($argv, 1);
$history = in_array('--history', $args, true);
$cleanOnly = in_array('--clean', $args, true);
$db = rp_db();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
rp_ensureSchema();
$q = function (string $sql, array $p = []) use ($db) { $s = $db->prepare($sql); $s->execute($p); return $s; };
$now = date('Y-m-d H:i:s');
mt_srand(20261127);
$j = fn(float $amp = 0.006) => (mt_rand(-1000, 1000) / 1000) * $amp;

/* ---------- clean previous seed ---------- */
$reqIds = $q("SELECT id FROM cdb_delivery_requests WHERE place_id LIKE 'RPSEED-%'")->fetchAll(PDO::FETCH_COLUMN);
if ($reqIds) {
    $in = implode(',', array_map('intval', $reqIds));
    $routeIds = $q("SELECT DISTINCT route_id FROM cdb_rp_stops WHERE request_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
    if ($routeIds) {
        $rin = implode(',', array_map('intval', $routeIds));
        $q("DELETE FROM cdb_rp_load WHERE route_id IN ($rin)"); $q("DELETE FROM cdb_rp_stops WHERE route_id IN ($rin)"); $q("DELETE FROM cdb_rp_routes WHERE id IN ($rin)");
    }
    foreach (['cdb_delivery_events', 'cdb_delivery_items', 'cdb_delivery_claims'] as $t) $q("DELETE FROM $t WHERE request_id IN ($in)");
    $q("DELETE FROM cdb_delivery_outbox WHERE event_key REGEXP ?", ['^(' . implode('|', array_map('intval', $reqIds)) . '):']);
    $q("DELETE FROM cdb_delivery_requests WHERE id IN ($in)");
}
$orderIds = $q("SELECT order_id FROM cdb_add_order WHERE notes = 'RPSEED'")->fetchAll(PDO::FETCH_COLUMN);
if ($orderIds) {
    $in = implode(',', array_map('intval', $orderIds));
    $q("DELETE FROM cdb_add_order_item WHERE order_id IN ($in)"); $q("DELETE FROM cdb_courier_track WHERE order_id IN ($in)");
    $q("DELETE FROM cdb_rp_parcel_meta WHERE module = 'air' AND order_id IN ($in)"); $q("DELETE FROM cdb_add_order WHERE order_id IN ($in)");
}
$seaIds = $q("SELECT order_id FROM cdb_customers_packages WHERE notes = 'RPSEED'")->fetchAll(PDO::FETCH_COLUMN);
if ($seaIds) {
    $in = implode(',', array_map('intval', $seaIds));
    $q("DELETE FROM cdb_customers_packages_detail WHERE order_id IN ($in)"); $q("DELETE FROM cdb_rp_parcel_meta WHERE module = 'sea' AND order_id IN ($in)"); $q("DELETE FROM cdb_customers_packages WHERE order_id IN ($in)");
}
$q("DELETE FROM cdb_rp_plans WHERE params_json LIKE '%\"seed\":true%' OR (status IN ('draft','discarded','confirming') AND created_by IN (SELECT id FROM cdb_users WHERE notes = 'RPSEED'))");
if ($cleanOnly) { echo "Removed seeded route-planner rows.\n"; exit(0); }

/* ---------- zones ---------- */
$zones = [
    'Accra Central / Osu' => [5.570, -0.205, 9.0, 'Osu,Accra Central,Labadi,Cantonments,Kaneshie,Dansoman,Achimota,Lapaz,Airport Residential', 25],
    'East Legon / Madina / Adenta' => [5.665, -0.168, 6.5, 'East Legon,Madina,Adenta,Haatso,Legon', 30],
    'Tema / Spintex' => [5.630, -0.065, 9.0, 'Tema,Spintex,Teshie,Nungua,Sakumono', 35],
];
$zoneIds = [];
$prio = 10;
foreach ($zones as $name => [$lat, $lng, $km, $cities, $fee]) {
    $id = $q('SELECT id FROM cdb_delivery_zones WHERE name = ?', [$name])->fetchColumn();
    if (!$id) { $q('INSERT INTO cdb_delivery_zones (name, city_match, fee, active, created_at) VALUES (?,?,?,1,?)', [$name, $cities, $fee, $now]); $id = $db->lastInsertId(); }
    $q('INSERT INTO cdb_delivery_zone_rules (zone_id, priority, centre_lat, centre_lng, radius_km, agency_id) VALUES (?,?,?,?,?,0) ON DUPLICATE KEY UPDATE priority = VALUES(priority), centre_lat = VALUES(centre_lat), centre_lng = VALUES(centre_lng), radius_km = VALUES(radius_km)', [$id, $prio += 10, $lat, $lng, $km]);
    $zoneIds[$name] = (int) $id;
}
$zoneFor = function (float $lat, float $lng) use ($zones, $zoneIds) {
    $best = null; $bd = INF;
    foreach ($zones as $name => [$zl, $zg, $km]) { $d = rp_haversineM($lat, $lng, $zl, $zg) / 1000; if ($d <= $km && $d < $bd) { $bd = $d; $best = $zoneIds[$name]; } }
    return $best;
};

/* ---------- riders & customers ---------- */
$hash = password_hash('DevPassword123!', PASSWORD_DEFAULT);
$user = function (string $username, int $level, string $fname, string $lname, string $phone, array $extra = []) use ($q, $db, $hash, $now) {
    $row = $q('SELECT id, notes FROM cdb_users WHERE username = ?', [$username])->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['notes'] !== 'RPSEED') return (int) $row['id']; // a real account: leave it as it is
    $cols = ['username' => $username, 'password' => $hash, 'userlevel' => $level, 'fname' => $fname, 'lname' => $lname, 'phone' => $phone, 'email' => $username . '@rpseed.test', 'active' => 1, 'agency_id' => 1, 'notes' => 'RPSEED', 'password_changed_at' => $now, 'city' => 'Accra', 'country' => 'Ghana'] + $extra;
    if ($row) { $q('UPDATE cdb_users SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($cols))) . ' WHERE id = ?', array_merge(array_values($cols), [$row['id']])); return (int) $row['id']; }
    $q('INSERT INTO cdb_users (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($cols));
    return (int) $db->lastInsertId();
};
$ama = (int) $q("SELECT id FROM cdb_users WHERE username = 'ama'")->fetchColumn();
if (!$ama) { fwrite(STDERR, "Rider ama is missing\n"); exit(1); }
$q("UPDATE cdb_users SET vehicle_type = 'motorbike', zone = 'Accra Central / Osu', rider_status = 'active' WHERE id = ?", [$ama]);
$riders = [
    'ama' => $ama,
    'kwame' => $user('kwame', 3, 'Kwame', 'Asare', '+233244100201', ['vehicle_type' => 'motorbike', 'zone' => 'East Legon / Madina / Adenta', 'rider_status' => 'active']),
    'yaw' => $user('yaw.mensah', 3, 'Yaw', 'Mensah', '+233244100202', ['vehicle_type' => 'van', 'zone' => 'Tema / Spintex', 'rider_status' => 'active']),
    'efua' => $user('efua', 3, 'Efua', 'Boateng', '+233244100203', ['vehicle_type' => 'car', 'zone' => '', 'rider_status' => 'active']),
    'kojo' => $user('kojo', 3, 'Kojo', 'Appiah', '+233244100204', ['vehicle_type' => 'van', 'zone' => 'Tema / Spintex', 'rider_status' => 'off']),
];
$vehicles = [
    'ama' => ['motorbike', 'GR 2231-22', 'Honda CG 125 with top box', 30, 90, 8, 50, 45, 40, 0, 0, 1],
    'kwame' => ['motorbike', 'GE 5520-23', 'Yamaha YBR 125', 30, 90, 8, 50, 45, 40, 0, 0, 1],
    'yaw' => ['van', 'GT 8812-21', 'Toyota HiAce', 600, 2500, 50, 240, 150, 130, 1, 0, 0],
    'efua' => ['car', 'GN 4410-24', 'Toyota Corolla (cool box)', 150, 400, 20, 100, 90, 50, 1, 1, 1],
    'kojo' => ['van', 'GW 3307-20', 'Nissan Urvan', 600, 2500, 50, 240, 150, 130, 1, 0, 0],
];
$docsFor = fn($i) => json_encode([['name' => "Driver's licence", 'file_id' => null, 'expires' => date('Y-m-d', strtotime('+' . (200 + 40 * $i) . ' days'))], ['name' => 'Insurance', 'file_id' => null, 'expires' => date('Y-m-d', strtotime('+' . (12 + 30 * $i) . ' days'))], ['name' => 'Roadworthy certificate', 'file_id' => null, 'expires' => date('Y-m-d', strtotime('+' . (90 + 15 * $i) . ' days'))]]);
$i = 0;
foreach ($vehicles as $k => $v) {
    $q('REPLACE INTO cdb_rp_vehicles (driver_id, vehicle_type, plate, make_model, max_kg, max_l, max_parcels, cargo_l_cm, cargo_w_cm, cargo_h_cm, can_fragile, can_cold, can_high_value, docs_json, active, updated_by, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NULL,?)',
        array_merge([$riders[$k]], $v, [$docsFor($i++), $now]));
}
$esi = (int) $q("SELECT id FROM cdb_users WHERE username = 'esi'")->fetchColumn();
$customers = [
    $esi ?: $user('rpseed_esi', 1, 'Esi', 'Asante', '+233541234567'),
    $user('rpseed_abena', 1, 'Abena', 'Owusu', '+233201110001'),
    $user('rpseed_kofi', 1, 'Kofi', 'Danquah', '+233201110002'),
    $user('rpseed_akua', 1, 'Akua', 'Mensah', '+233201110003'),
    $user('rpseed_nii', 1, 'Nii', 'Lartey', '+233201110004'),
];

/* ---------- parcels ---------- */
// [contents, kg, L, W, H, declared USD, handling]
$catalog = [
    ['Phone accessories', 0.6, 22, 16, 8, 45, []], ['Laptop and charger', 3.2, 45, 35, 10, 950, ['high_value']], ['Kitchen blender', 4.1, 40, 30, 30, 85, ['fragile']],
    ['Shoes (2 pairs)', 2.4, 36, 26, 14, 120, []], ['Clothing', 5.5, 50, 40, 30, 160, []], ['Books', 7.2, 40, 30, 25, 70, []],
    ['Vitamins and supplements', 1.4, 25, 20, 15, 60, []], ['Insulin pens (cold chain)', 0.9, 25, 20, 18, 300, ['cold']], ['Car parts', 11.5, 60, 40, 30, 240, []],
    ['42" LED TV', 13.8, 105, 65, 14, 420, ['fragile']], ['Gold jewellery', 0.3, 15, 12, 8, 2400, ['high_value']], ['Baby formula', 6.0, 45, 30, 25, 90, []],
    ['Printer', 8.6, 55, 45, 35, 210, ['fragile']], ['Hair products', 3.0, 35, 25, 20, 75, ['liquid']], ['Smartphone', 0.5, 20, 12, 8, 899, ['high_value']],
    ['Kids toys', 2.8, 50, 35, 30, 55, []], ['Perfume set', 1.1, 25, 18, 12, 180, ['fragile', 'liquid']], ['Frozen fish (cooler)', 4.5, 40, 30, 30, 40, ['cold']],
];
$areas = [ // name, lat, lng, street
    ['Osu', 5.556, -0.182, 'Oxford Street'], ['East Legon', 5.635, -0.155, 'Lagos Avenue'], ['Madina', 5.668, -0.165, 'Madina Zongo Junction'], ['Adenta', 5.708, -0.153, 'Adenta Housing Down'],
    ['Spintex', 5.627, -0.105, 'Spintex Road'], ['Tema Community 1', 5.669, -0.017, 'Meridian Road'], ['Achimota', 5.616, -0.229, 'Achimota Forest Road'], ['Dansoman', 5.548, -0.264, 'Dansoman High Street'],
    ['Kaneshie', 5.570, -0.236, 'Kaneshie Market Road'], ['Labadi', 5.561, -0.150, 'La Beach Road'], ['Cantonments', 5.580, -0.174, 'Switchback Road'], ['Airport Residential', 5.603, -0.180, 'Patrice Lumumba Road'],
    ['Haatso', 5.668, -0.197, 'Haatso-Atomic Road'], ['Teshie', 5.585, -0.105, 'Teshie-Nungua Road'], ['Nungua', 5.600, -0.077, 'Nungua Barrier'], ['Lapaz', 5.607, -0.251, 'Nii Boi Town'],
];
$contacts = ['Abena Owusu', 'Kofi Danquah', 'Akua Mensah', 'Nii Lartey', 'Esi Asante', 'Yaa Asantewaa', 'Kwabena Ofori', 'Adjoa Badu', 'Kwesi Arthur', 'Efia Sarpong', 'Mawuli Agbo', 'Selasi Kpodo'];
$windows = [[null, null], [null, null], [null, null], ['09:00', '12:00'], ['13:00', '17:00'], [null, null], ['10:00', '13:00'], [null, null]];
$airNo = 990100; $seaNo = 880100;
$newAir = function (int $customer, array $c, int $n) use ($q, $db, &$airNo, $now) {
    [$desc, $kg, $l, $w, $h, $val] = $c;
    $q("INSERT INTO cdb_add_order (user_id, order_prefix, order_no, order_date, order_datetime, sender_id, total_weight, declared_value, total_declared_value, agency, status_courier, is_pickup, notes, fs_cleared_for_delivery, fs_cleared_at, recipient_type, is_deleted) VALUES (?,?,?,?,?,?,?,?,?,1,34,0,'RPSEED',1,?,'recipient',0)",
        [15, 'SLA-', (string) ++$airNo, date('Y-m-d', strtotime('-9 days')), $now, $customer, $kg, $val, $val, $now]);
    $id = (int) $db->lastInsertId();
    $q('INSERT INTO cdb_add_order_item (order_id, order_item_description, order_item_quantity, order_item_weight, order_item_length, order_item_width, order_item_height, order_item_declared_value) VALUES (?,?,?,?,?,?,?,?)', [$id, $desc, 1, $kg, $l, $w, $h, $val]);
    return $id;
};
$newSea = function (int $customer, array $c) use ($q, $db, &$seaNo, $now) {
    [$desc, $kg, $l, $w, $h, $val] = $c;
    $q("INSERT INTO cdb_customers_packages (order_prefix, order_no, order_date, order_datetime, sender_id, total_weight, declared_value, total_declared_value, agency, status_courier, notes) VALUES (?,?,?,?,?,?,?,?,1,34,'RPSEED')",
        ['SLS-', (string) ++$seaNo, date('Y-m-d', strtotime('-30 days')), $now, $customer, $kg, $val, $val]);
    $id = (int) $db->lastInsertId();
    $q('INSERT INTO cdb_customers_packages_detail (order_id, order_item_description, order_item_quantity, order_item_weight, order_item_length, order_item_width, order_item_height, order_item_declared_value) VALUES (?,?,?,?,?,?,?,?)', [$id, $desc, 1, (string) $kg, (string) $l, (string) $w, (string) $h, $val]);
    return $id;
};
$meta = function (string $module, int $id, array $c, ?array $win, int $prio, string $note) use ($q, $now) {
    $q('REPLACE INTO cdb_rp_parcel_meta (module, order_id, contents, handling, value_cents, currency, customer_note, window_start, window_end, priority, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [$module, $id, $c[0], implode(',', $c[6]), (int) round($c[5] * 100), 'USD', $note ?: null, $win[0] ? $win[0] . ':00' : null, $win[1] ? $win[1] . ':00' : null, $prio, $now]);
};
$notes = ['', '', 'Call when at the gate', 'Leave with the security man if I am out', '', 'Blue gate opposite the pharmacy', 'Office closes at 5pm', ''];

// 40 requests: 37 in the Accra zones (some with 2-3 parcels), 1 in Kasoa (out of zone),
// 1 without a map pin, plus sea requests mixed in.
$plan = [];
for ($r = 0; $r < 40; $r++) $plan[] = ['module' => in_array($r, [5, 13, 21, 29, 33, 37], true) ? 'sea' : 'air', 'parcels' => $r % 5 === 0 ? 2 : ($r % 13 === 0 ? 3 : 1)];
$airCount = 0; $seaCount = 0; $created = 0; $requestIds = [];
foreach ($plan as $r => $spec) {
    $customer = $customers[$r % count($customers)];
    if ($r === 38) { $area = ['Kasoa', 5.534, -0.418, 'Kasoa-Winneba Road']; }
    elseif ($r === 39) { $area = ['Unknown', 0.0, 0.0, 'Behind the old cinema']; }
    else $area = $areas[($r * 5) % count($areas)];
    $lat = $area[1] ? round($area[1] + $j(), 7) : 0.0; $lng = $area[2] ? round($area[2] + $j(), 7) : 0.0;
    $win = $windows[$r % count($windows)];
    $prio = $r % 11 === 4 ? 2 : ($r % 7 === 3 ? 1 : 0);
    $ids = [];
    for ($k = 0; $k < $spec['parcels']; $k++) {
        $c = $catalog[($r * 3 + $k * 5) % count($catalog)];
        if ($spec['module'] === 'air') { $id = $newAir($customer, $c, $k); $airCount++; }
        else { $id = $newSea($customer, $c); $seaCount++; }
        $meta($spec['module'], $id, $c, $win, $prio, $k === 0 ? $notes[$r % count($notes)] : '');
        $ids[] = $id;
    }
    $zone = $lat ? $zoneFor($lat, $lng) : null;
    $metres = $lat ? (int) round(rp_haversineM(5.6037, -0.1870, $lat, $lng) * 1.35) : 0;
    $fee = $zone ? (int) $q('SELECT fee FROM cdb_delivery_zones WHERE id = ?', [$zone])->fetchColumn() * 100 : 4500;
    $contact = $contacts[$r % count($contacts)];
    $q("INSERT INTO cdb_delivery_requests (customer_id, agency_id, module, parcel_json, address, place_id, lat, lng, origin_json, distance_m, zone_id, pricing_mode, currency, amount_cents, rider_cents, breakdown_json, config_revision, state, contact_name, contact_phone, instructions, expires_at, approved_at, created_at, updated_at)
        VALUES (?,1,?,?,?,?,?,?,'{}',?,?,'zone','GHS',?,?,'{}',1,'pending',?,?,?,?,?,?,?)",
        [$customer, $spec['module'], json_encode($ids), ($r + 12) . ' ' . $area[3] . ', ' . $area[0] . ', Accra, Ghana', 'RPSEED-' . $r, $lat, $lng, $metres, $zone, $fee, (int) round($fee * 0.7), $contact, '+2332455' . sprintf('%05d', 10000 + $r * 37), $notes[$r % count($notes)], date('Y-m-d H:i:s', time() + 900), date('Y-m-d H:i:s', time() - 3600 * (1 + $r % 20)), $now, $now]);
    $req = (int) $db->lastInsertId();
    $requestIds[] = $req;
    $head = $spec['module'] === 'air' ? 'cdb_add_order' : 'cdb_customers_packages';
    foreach ($ids as $id) {
        $t = $q("SELECT CONCAT(order_prefix, order_no) FROM $head WHERE order_id = ?", [$id])->fetchColumn();
        $q('INSERT INTO cdb_delivery_items (request_id, module, order_id, tracking) VALUES (?,?,?,?)', [$req, $spec['module'], $id, $t]);
        $q('INSERT INTO cdb_delivery_claims (module, order_id, request_id) VALUES (?,?,?)', [$spec['module'], $id, $req]);
    }
    $q('INSERT INTO cdb_delivery_events (request_id, actor_id, state, note, created_at) VALUES (?,?,?,?,?)', [$req, $customer, 'pending', 'Customer approved GHS ' . number_format($fee / 100, 2), $now]);
    $created++;
}
// "Arrived in Accra" counts as ready for door delivery (dl_ready); the feature switch is left as it is.
$cfg = json_decode((string) $q('SELECT config_json FROM cdb_delivery_config WHERE id = 1')->fetchColumn(), true) ?: [];
$ready = array_values(array_unique(array_map('intval', array_merge($cfg['ready_statuses'] ?? [6, 32], [6, 32, 34]))));
if (($cfg['ready_statuses'] ?? null) !== $ready) {
    $cfg['ready_statuses'] = $ready;
    if ($q('SELECT COUNT(*) FROM cdb_delivery_config WHERE id = 1')->fetchColumn()) $q('UPDATE cdb_delivery_config SET config_json = ? WHERE id = 1', [json_encode($cfg)]);
    else $q('INSERT INTO cdb_delivery_config (id, config_json, revision, updated_at) VALUES (1, ?, 1, ?)', [json_encode($cfg), $now]);
}

/* ---------- history ---------- */
if ($history) {
    $rids = array_values($riders);
    $in = implode(',', array_map('intval', $rids));
    $q("DELETE FROM cdb_rp_activity WHERE driver_id IN ($in) AND started_at < ?", [date('Y-m-d 00:00:00')]);
    $q("DELETE FROM cdb_rp_pings WHERE driver_id IN ($in) AND recorded_at < ?", [date('Y-m-d 00:00:00')]);
    $old = $q("SELECT id FROM cdb_rp_routes WHERE driver_id IN ($in) AND route_date < ? AND start_json LIKE '%RPSEED%'", [date('Y-m-d')])->fetchAll(PDO::FETCH_COLUMN);
    if ($old) { $oin = implode(',', array_map('intval', $old)); $q("DELETE FROM cdb_rp_stops WHERE route_id IN ($oin)"); $q("DELETE FROM cdb_rp_routes WHERE id IN ($oin)"); }
    $areasH = [[5.556, -0.182, 'Osu'], [5.635, -0.155, 'East Legon'], [5.668, -0.165, 'Madina'], [5.627, -0.105, 'Spintex'], [5.570, -0.236, 'Kaneshie'], [5.580, -0.174, 'Cantonments'], [5.616, -0.229, 'Achimota'], [5.561, -0.150, 'Labadi']];
    foreach (['ama', 'kwame', 'yaw', 'efua'] as $ri => $k) {
        $id = $riders[$k];
        for ($d = 7; $d >= 1; $d--) {
            if (($d + $ri) % 6 === 0) continue; // a day off now and then
            $t = strtotime(date('Y-m-d', strtotime("-$d days")) . ' 08:' . sprintf('%02d', 10 + 7 * $ri));
            $lat = 5.6037; $lng = -0.1870;
            $seq = [['loading', 25 + 5 * $ri]];
            $stops = 5 + ($d + $ri) % 4;
            for ($s = 0; $s < $stops; $s++) { $seq[] = ['onway', 18 + ($s * 7 + $d) % 15]; $seq[] = ['waiting', 4 + ($s + $d) % 7]; if ($s === 2) $seq[] = ['break', 30]; }
            $seq[] = ['onway', 25];
            // A finished route for the day with its delivered (and the odd failed) stops, so the
            // statistics and the roster show real outcomes next to the activity blocks.
            $day = date('Y-m-d', $t);
            $q('INSERT INTO cdb_rp_routes (plan_id, driver_id, route_date, status, start_json, provider, total_m, total_s, load_n, started_at, finished_at, created_at, updated_at) VALUES (0,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$id, $day, 'done', json_encode(['id' => 'accra-office', 'label' => 'Swift Lane Accra office (RPSEED history)', 'lat' => 5.6037, 'lng' => -0.1870]), 'estimate', 9000 * $stops, 1500 * $stops, $stops, date('Y-m-d H:i:s', $t), date('Y-m-d H:i:s', $t + 3600 * 6), date('Y-m-d H:i:s', $t), date('Y-m-d H:i:s', $t)]);
            $hRoute = (int) $db->lastInsertId();
            $doneT = $t + (25 + 5 * $ri) * 60;
            for ($s = 0; $s < $stops; $s++) {
                $doneT += (18 + ($s * 7 + $d) % 15 + 4 + ($s + $d) % 7 + ($s === 2 ? 30 : 0)) * 60;
                $a = $areasH[($s + $ri + $d) % count($areasH)];
                $failedStop = ($s + $d + $ri) % 9 === 0;
                $q('INSERT INTO cdb_rp_stops (route_id, request_id, seq, lat, lng, address, contact_name, status, eta, arrived_at, done_at, fail_reason, leg_m, leg_s) VALUES (?,0,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$hRoute, $s + 1, round($a[0] + $j(), 7), round($a[1] + $j(), 7), $a[2] . ', Accra', 'Customer ' . ($s + 1), $failedStop ? 'failed' : 'delivered', date('Y-m-d H:i:s', $doneT - 300), date('Y-m-d H:i:s', $doneT - 240), date('Y-m-d H:i:s', $doneT), $failedStop ? 'Customer not home' : null, 9000, 1500]);
            }
            foreach ($seq as [$state, $min]) {
                $end = $t + $min * 60;
                $q('INSERT INTO cdb_rp_activity (driver_id, route_id, state, started_at, ended_at) VALUES (?,?,?,?,?)', [$id, $hRoute, $state, date('Y-m-d H:i:s', $t), date('Y-m-d H:i:s', $end)]);
                if ($state === 'onway') {
                    $tlat = 5.6037 + $j(0.08); $tlng = -0.1870 + $j(0.1);
                    for ($p = $t; $p < $end; $p += 60) {
                        $f = ($p - $t) / max(1, $end - $t);
                        $q('INSERT INTO cdb_rp_pings (driver_id, route_id, lat, lng, accuracy_m, speed_mps, recorded_at, received_at) VALUES (?,?,?,?,?,?,?,?)', [$id, $hRoute, round($lat + ($tlat - $lat) * $f, 7), round($lng + ($tlng - $lng) * $f, 7), 12, 6.5, date('Y-m-d H:i:s', $p), date('Y-m-d H:i:s', $p)]);
                    }
                    $lat = $tlat; $lng = $tlng;
                }
                $t = $end;
            }
        }
    }
}
echo "Seeded: " . count($riders) . " riders (" . implode(', ', array_map(fn($k, $v) => "$k#$v", array_keys($riders), $riders)) . "), 3 zones, $airCount air + $seaCount sea parcels, $created pending delivery requests" . ($history ? ', 7 days of activity/pings' : '') . ".\n";
echo "Riders sign in with password DevPassword123!\n";
