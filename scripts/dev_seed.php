<?php
/**
 * Development seed: fills a LOCAL database with sample customers, shipments,
 * packages, pickups and consolidations so the dashboards and lists can be
 * reviewed. Never run against production.
 *
 *   php scripts/dev_seed.php --i-know-this-is-a-dev-database
 */
if (PHP_SAPI !== 'cli' || !in_array('--i-know-this-is-a-dev-database', $argv, true)) {
    fwrite(STDERR, "Refusing: run from the CLI with --i-know-this-is-a-dev-database\n");
    exit(1);
}
require_once dirname(__DIR__) . '/loader.php';
mt_srand(42);
$pdo = (new Conexion())->dbh;
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$has = fn($t, $c) => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c'")->fetchColumn() > 0;
$tbl = fn($t) => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t'")->fetchColumn() > 0;

$first = ['Ama', 'Kofi', 'Efua', 'Yaw', 'Abena', 'Nana', 'Esi', 'Kojo', 'Akosua', 'Kwame', 'Adwoa', 'Kwesi', 'Afia', 'Fiifi', 'Akua', 'Selorm', 'Mawuli', 'Naa', 'Adjoa', 'Kwabena'];
$last = ['Boateng', 'Asante', 'Owusu', 'Darko', 'Frimpong', 'Adjei', 'Quaye', 'Appiah', 'Tetteh', 'Mensah', 'Sarpong', 'Bonsu', 'Manu', 'Ofori', 'Serwaa', 'Agbeko', 'Dzah', 'Dedei', 'Nyarko', 'Lamptey'];
$cities = ['Accra', 'Accra', 'Accra', 'Kumasi', 'Takoradi', 'Tema', 'Cape Coast', 'Ho', 'Tamale', 'Sunyani'];
$shops = ['Amazon', 'eBay', 'Walmart', 'Best Buy', 'Nike', 'Apple', 'Shein', 'Target'];
$pw = password_hash('Customer123!', PASSWORD_BCRYPT);
$couriers = $pdo->query('SELECT id FROM cdb_courier_com')->fetchAll(PDO::FETCH_COLUMN) ?: [1];
$offices = $pdo->query('SELECT id FROM cdb_offices')->fetchAll(PDO::FETCH_COLUMN) ?: [1];
$agencies = $pdo->query('SELECT id FROM cdb_branchoffices')->fetchAll(PDO::FETCH_COLUMN) ?: [1];
$packs = $pdo->query('SELECT id FROM cdb_packaging')->fetchAll(PDO::FETCH_COLUMN) ?: [1];
$cats = $pdo->query('SELECT id FROM cdb_category')->fetchAll(PDO::FETCH_COLUMN) ?: [1];
$drivers = $pdo->query('SELECT id FROM cdb_users WHERE userlevel = 3')->fetchAll(PDO::FETCH_COLUMN);

// customers
$customers = [];
$extra = $has('cdb_users', 'approve') ? ', approve' : '';
$extraV = $has('cdb_users', 'approve') ? ', :approve' : '';
$ins = $pdo->prepare("INSERT INTO cdb_users (username, password, locker, userlevel, email, fname, lname, phone, created, active, agency_id $extra) VALUES (:u, :p, :l, 1, :e, :f, :ln, :ph, :c, 1, 0 $extraV)");
for ($i = 0; $i < 60; $i++) {
    $f = $first[$i % 20]; $l = $last[($i * 7) % 20];
    $u = strtolower($f . '.' . $l . $i);
    $params = [':u' => $u, ':p' => $pw, ':l' => 'SL-' . (3200 + $i), ':e' => $u . '@example.com', ':f' => $f, ':ln' => $l, ':ph' => '+233 2' . mt_rand(0, 9) . ' ' . mt_rand(100, 999) . ' ' . mt_rand(1000, 9999), ':c' => date('Y-m-d H:i:s', strtotime('-' . mt_rand(0, 400) . ' days'))];
    if ($extra) $params[':approve'] = mt_rand(0, 9) > 1 ? 1 : 0;
    $ins->execute($params);
    $cid = (int) $pdo->lastInsertId();
    $customers[] = $cid;
    $pdo->prepare('INSERT INTO cdb_senders_addresses (country, state, city, zip_code, address, user_id) VALUES (83, 0, 0, :z, :a, :u)')->execute([':z' => 'GA-' . mt_rand(100, 999), ':a' => mt_rand(1, 99) . ' ' . $cities[$i % 10] . ' Road', ':u' => $cid]);
    $r = $pdo->prepare('INSERT INTO cdb_recipients (email, fname, lname, phone, sender_id, agency_id) VALUES (:e, :f, :l, :p, :s, 0)');
    $r->execute([':e' => 'r' . $i . '@example.com', ':f' => $first[($i + 3) % 20], ':l' => $last[($i + 5) % 20], ':p' => '+233 50 ' . mt_rand(100, 999) . ' ' . mt_rand(1000, 9999), ':s' => $cid]);
    $rid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO cdb_recipients_addresses (country, state, city, zip_code, address, recipient_id) VALUES (83, 0, 0, :z, :a, :r)')->execute([':z' => '', ':a' => mt_rand(1, 99) . ' ' . $cities[($i + 2) % 10] . ' Street', ':r' => $rid]);
}
echo "customers: " . count($customers) . "\n";

// shipments (air) with tracking history
$statusFlow = [1, 2, 3, 4, 6, 7, 8];
$insO = $pdo->prepare('INSERT INTO cdb_add_order (user_id, order_prefix, order_no, order_date, sender_id, sender_address_id, receiver_id, receiver_address_id, total_weight, sub_total, total_tax, total_order, order_datetime, agency, origin_off, order_item_category, order_package, order_courier, order_service_options, order_deli_time, order_pay_mode, order_payment_method, status_courier, driver_id, is_pickup, is_consolidate, due_date, status_invoice, order_incomplete, payment_date, is_deleted) VALUES (1, :pre, :no, :d, :s, 0, :r, 0, :w, :st, :tx, :tot, :dt, :ag, :off, :cat, :pk, :cou, 7, 1, 1, 1, :status, :drv, :pick, 0, :due, :inv, 0, :paid, 0)');
$insT = $pdo->prepare('INSERT INTO cdb_courier_track (order_id, order_track, t_dest, t_city, comments, t_date, status_courier, office_id, user_id) VALUES (:o, :tr, :dest, :city, :c, :t, :s, :off, 1)');
$insA = $pdo->prepare('INSERT INTO cdb_address_shipments (order_id, order_track, sender_country, sender_state, sender_city, sender_zip_code, sender_address, recipient_country, recipient_state, recipient_city, recipient_zip_code, recipient_address) VALUES (:o, :tr, :sc, :ss, :sci, :sz, :sa, :rc, :rs, :rci, :rz, :ra)');
$n = 0;
for ($day = 180; $day >= 0; $day--) {
    $count = (int) round(2 + 4 * abs(sin($day / 9)) + ($day % 7 < 2 ? 0 : 3) + mt_rand(0, 3));
    if ($day === 0) $count = 6;
    for ($k = 0; $k < $count; $k++) {
        $n++;
        $cid = $customers[array_rand($customers)];
        $date = date('Y-m-d', strtotime("-$day days"));
        $age = $day;
        $stage = $age > 20 ? 6 : min(6, (int) floor($age / 3));
        if (mt_rand(0, 40) === 0) $status = 21; elseif (mt_rand(0, 60) === 0) $status = 12; else $status = $statusFlow[$stage];
        $w = round(mt_rand(5, 400) / 10, 1);
        $sub = round($w * 6.5, 2);
        $pick = mt_rand(0, 4) === 0 ? 1 : 0;
        $paid = $status === 8 || mt_rand(0, 2) > 0;
        $insO->execute([':pre' => 'SL-', ':no' => (string) (100000 + $n), ':d' => $date, ':s' => $cid, ':r' => 0, ':w' => $w, ':st' => $sub, ':tx' => round($sub * .05, 2), ':tot' => round($sub * 1.05, 2), ':dt' => $date . ' ' . sprintf('%02d:%02d:00', mt_rand(7, 18), mt_rand(0, 59)), ':ag' => $agencies[array_rand($agencies)], ':off' => $offices[array_rand($offices)], ':cat' => $cats[array_rand($cats)], ':pk' => $packs[array_rand($packs)], ':cou' => $couriers[array_rand($couriers)], ':status' => $status, ':drv' => ($status >= 7 && $drivers) ? $drivers[array_rand($drivers)] : 0, ':pick' => $pick, ':due' => date('Y-m-d', strtotime($date . ' +14 days')), ':inv' => $paid ? 19 : 23, ':paid' => $paid ? date('Y-m-d H:i:s', strtotime($date . ' +' . mt_rand(0, 20) . ' days')) : null]);
        $oid = (int) $pdo->lastInsertId();
        $track = 'SL-' . (100000 + $n);
        $insA->execute([':o' => $oid, ':tr' => $track, ':sc' => 'United States', ':ss' => 'NJ', ':sci' => 'Newark', ':sz' => '07114', ':sa' => '1 Port St', ':rc' => 'Ghana', ':rs' => 'Greater Accra', ':rci' => $cities[array_rand($cities)], ':rz' => '', ':ra' => mt_rand(1, 99) . ' Liberation Rd']);
        $labels = [1 => ['Pending collection', 'Newark'], 2 => ['Received at office', 'Newark'], 3 => ['In transit', 'JFK'], 4 => ['In warehouse', 'Accra'], 6 => ['Available for pickup', 'Accra'], 7 => ['On route', 'Accra'], 8 => ['Delivered', 'Accra'], 21 => ['Cancelled', 'Accra'], 12 => ['Rejected', 'Accra']];
        $steps = array_slice($statusFlow, 0, $stage + 1);
        if ($status === 21 || $status === 12) $steps[] = $status;
        foreach ($steps as $si => $st) {
            $insT->execute([':o' => $oid, ':tr' => $track, ':dest' => $labels[$st][0], ':city' => $labels[$st][1], ':c' => $labels[$st][0], ':t' => date('Y-m-d H:i:s', strtotime($date . ' +' . ($si * 3) . ' days ' . mt_rand(7, 18) . ':' . mt_rand(10, 59))), ':s' => $st, ':off' => $offices[0]]);
        }
    }
}
echo "shipments: $n\n";

// sea packages + pre-alerts
$insP = $pdo->prepare('INSERT INTO cdb_customers_packages (user_id, order_prefix, order_no, order_date, sender_id, sender_address_id, total_weight, sub_total, total_order, order_datetime, agency, origin_off, order_package, order_item_category, order_courier, order_service_options, order_deli_time, order_pay_mode, order_payment_method, status_courier, driver_id, status_invoice, tracking_purchase, provider_purchase, price_purchase, is_prealert, is_consolidate) VALUES (1, :pre, :no, :d, :s, 0, :w, :st, :tot, :dt, :ag, :off, :pk, :cat, :cou, 7, 1, 1, 1, :status, 0, :inv, :tp, :pp, :pr, 0, :cons)');
$m = 0;
for ($day = 120; $day >= 0; $day--) {
    $count = mt_rand(1, 6);
    for ($k = 0; $k < $count; $k++) {
        $m++;
        $cid = $customers[array_rand($customers)];
        $date = date('Y-m-d', strtotime("-$day days"));
        $stage = min(6, (int) floor($day / 5));
        $status = $statusFlow[$stage];
        $w = round(mt_rand(5, 300) / 10, 1);
        $insP->execute([':pre' => 'PK-', ':no' => (string) (50000 + $m), ':d' => $date, ':s' => $cid, ':w' => $w, ':st' => round($w * 4, 2), ':tot' => round($w * 4.2, 2), ':dt' => $date . ' 10:00:00', ':ag' => $agencies[0], ':off' => $offices[0], ':pk' => $packs[0], ':cat' => $cats[0], ':cou' => $couriers[array_rand($couriers)], ':status' => $status, ':inv' => mt_rand(0, 1) ? 19 : 23, ':tp' => '1Z' . strtoupper(substr(md5((string) $m), 0, 12)), ':pp' => $shops[array_rand($shops)], ':pr' => mt_rand(10, 90), ':cons' => $stage >= 3 ? 1 : 0]);
    }
}
echo "packages: $m\n";
$insPA = $pdo->prepare('INSERT INTO cdb_pre_alert (tracking, provider_shop, courier_com, customer_id, purchase_price, package_description, estimated_date, prealert_date, url_invoice, is_package) VALUES (:t, :s, :c, :u, :p, :d, :e, :pd, :url, :ip)');
for ($i = 0; $i < 25; $i++) {
    $insPA->execute([':t' => '1Z' . strtoupper(substr(md5('pa' . $i), 0, 12)), ':s' => $shops[$i % 8], ':c' => $couriers[0], ':u' => $customers[array_rand($customers)], ':p' => mt_rand(15, 120), ':d' => 'Online order', ':e' => date('Y-m-d', strtotime('+' . mt_rand(1, 12) . ' days')), ':pd' => date('Y-m-d H:i:s', strtotime('-' . mt_rand(0, 6) . ' days')), ':url' => '', ':ip' => $i < 16 ? 0 : 1]);
}
echo "pre-alerts: 25\n";

// consolidations (air + sea)
foreach (['cdb_consolidate' => 'AIR-', 'cdb_consolidate_packages' => 'SLC-'] as $t => $prefix) {
    $insC = $pdo->prepare("INSERT INTO $t (user_id, c_prefix, c_no, c_date, sender_id, sender_address_id, receiver_id, receiver_address_id, total_weight, sub_total, total_order, order_datetime, agency, origin_off, order_package, order_item_category, order_courier, order_service_options, order_deli_time, order_pay_mode, status_courier, driver_id, seals_package, status_invoice) VALUES (1, :p, :n, :d, :s, 0, 0, 0, :w, :st, :tot, :dt, :ag, :off, :pk, :cat, :cou, '7', '1', '1', :status, 0, '', 23)");
    for ($i = 0; $i < 10; $i++) {
        $date = date('Y-m-d', strtotime('-' . ($i * 12) . ' days'));
        $insC->execute([':p' => $prefix, ':n' => (string) (1100 + $i), ':d' => $date, ':s' => $customers[$i], ':w' => mt_rand(100, 900), ':st' => mt_rand(500, 4000), ':tot' => mt_rand(500, 4000), ':dt' => $date . ' 09:00:00', ':ag' => (string) $agencies[0], ':off' => (string) $offices[0], ':pk' => (string) $packs[0], ':cat' => (string) $cats[0], ':cou' => (string) $couriers[0], ':status' => (string) ($i < 2 ? 13 : ($i < 4 ? 3 : 8))]);
    }
}
echo "consolidations: 20\n";
if ($tbl('cdb_activity_log')) {
    $insL = $pdo->prepare("INSERT INTO cdb_activity_log (created_at, user_id, actor_name, actor_username, role_id, role_name, module, action, action_label, verb, outcome, summary, entity_type, entity_id, entity_label, ip, method, endpoint) VALUES (:c, 1, 'Swiftlane Admin', 'admin', 9, 'Super admin', :m, :a, :al, :v, 'ok', :s, :et, :ei, :el, '127.0.0.1', 'POST', :ep)");
    $acts = [['shipments', 'create', 'Created shipment', 'create', 'registered shipment SL-100482', 'shipment', 'SL-100482'], ['customers', 'approve', 'Approved customer', 'update', 'approved Ama Boateng', 'customer', '12'], ['payments', 'record', 'Recorded payment', 'create', 'recorded a payment of $51.63', 'payment', 'PSK-8841209'], ['warehouse', 'scan', 'Scanned in', 'update', 'scanned in SL-100471', 'shipment', 'SL-100471']];
    for ($i = 0; $i < 40; $i++) {
        $a = $acts[$i % 4];
        $insL->execute([':c' => date('Y-m-d H:i:s', strtotime('-' . ($i * 37) . ' minutes')), ':m' => $a[0], ':a' => $a[1], ':al' => $a[2], ':v' => $a[3], ':s' => $a[4], ':et' => $a[5], ':ei' => $a[6], ':el' => $a[6], ':ep' => '/' . $a[0]]);
    }
    echo "activity log: 40\n";
}
echo "done\n";
