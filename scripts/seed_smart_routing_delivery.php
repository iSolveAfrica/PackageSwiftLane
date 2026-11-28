<?php
/**
 * Development helper for Smart Routing: act as the rider on one planned route so the page has a
 * finished stop to show. Loads the vehicle, starts the route, delivers the first open stop (with a
 * pickup code and a small proof photo, exactly as the rider app does) and, with --fail, marks the
 * second open stop as failed.
 *   php scripts/seed_smart_routing_delivery.php <route_id> [--fail]
 * Refuses unless the database name is "swiftlane" or contains "test". Prints JSON.
 */
require_once __DIR__ . '/../loader.php';
if (class_exists('Core')) { try { new Core(); } catch (Throwable $e) { /* keep the CLI default */ } }
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/smart_routing.php';
require_once CDP_ROOT . '/helpers/pickup_code.php';
if (!(CDP_DB_NAME === 'swiftlane' || preg_match('/test/i', CDP_DB_NAME))) { fwrite(STDERR, "Refusing: use the local swiftlane or a *test* database\n"); exit(1); }
$routeId = (int) ($argv[1] ?? 0);
$alsoFail = in_array('--fail', $argv, true);
$out = ['route_id' => $routeId, 'delivered' => null, 'failed' => null];
try {
    sr_ensureSchema();
    cdp_pickupCodeEnsureTables();
    $route = rp_one('SELECT * FROM cdb_rp_routes WHERE id = ?', [$routeId]);
    if (!$route) throw new DomainException('Route not found');
    $driver = (int) $route['driver_id'];
    if (in_array($route['status'], ['planned', 'loading'], true)) {
        foreach (rp_all('SELECT module, order_id FROM cdb_rp_load WHERE route_id = ?', [$routeId]) as $l) rp_loadToggle($routeId, $driver, (string) $l['module'], (int) $l['order_id'], true);
        rp_startRoute($routeId, $driver);
    }
    $open = rp_all("SELECT s.id, s.request_id, s.contact_name FROM cdb_rp_stops s WHERE s.route_id = ? AND s.status IN ('pending','arrived') ORDER BY s.seq", [$routeId]);
    if (!$open) throw new DomainException('No open stop on this route');
    $svc = rp_service();
    // Deliver the first open stop.
    $stop = $open[0];
    $req = rp_one('SELECT * FROM cdb_delivery_requests WHERE id = ?', [(int) $stop['request_id']]);
    $orderIds = array_map('intval', json_decode((string) $req['parcel_json'], true) ?: []);
    rp_arriveStop($routeId, (int) $stop['id'], $driver);
    $code = (string) random_int(100000, 999999);
    rp_q("INSERT INTO cdb_pickup_codes (code, module, owner_id, context, status, created_at, expires_at) VALUES (?,?,?,'smart-routing-dev','active',NOW(),DATE_ADD(NOW(),INTERVAL 1 DAY))", [$code, $req['module'], (int) $req['customer_id']]);
    $codeId = (int) rp_db()->lastInsertId();
    foreach ($orderIds as $oid) rp_q('INSERT INTO cdb_pickup_code_items (code_id, module, order_id) VALUES (?,?,?)', [$codeId, $req['module'], $oid]);
    // A small real PNG as the delivery photo (a parcel on a doorstep, drawn with GD when available).
    $dir = getenv('CDP_DELIVERY_PROOF_DIR') ?: dirname(CDP_ROOT) . '/delivery-proof';
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    $name = bin2hex(random_bytes(16)) . '.png';
    if (function_exists('imagecreatetruecolor')) {
        $im = imagecreatetruecolor(640, 480);
        imagefilledrectangle($im, 0, 0, 640, 480, imagecolorallocate($im, 226, 221, 210));
        imagefilledrectangle($im, 0, 330, 640, 480, imagecolorallocate($im, 150, 142, 128));
        imagefilledrectangle($im, 230, 210, 420, 350, imagecolorallocate($im, 196, 148, 92));
        imagefilledrectangle($im, 316, 210, 334, 350, imagecolorallocate($im, 226, 196, 150));
        imagestring($im, 5, 240, 370, 'Swift Lane dev photo', imagecolorallocate($im, 40, 40, 40));
        imagepng($im, $dir . '/' . $name);
    } else {
        file_put_contents($dir . '/' . $name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }
    $svc->transition((int) $req['id'], $driver, 'rider', 0, 'deliver', ['code' => $code, 'receiver' => (string) $stop['contact_name'] ?: 'Receiver', 'proof' => $name, 'fee_collected' => 1]);
    rp_syncStopFromRequest((int) $req['id']);
    $out['delivered'] = ['stop_id' => (int) $stop['id'], 'request_id' => (int) $req['id'], 'photo' => $name];
    if ($alsoFail && isset($open[1])) {
        $svc->transition((int) $open[1]['request_id'], $driver, 'rider', 0, 'fail', ['note' => 'Customer not at home, phone switched off']);
        rp_syncStopFromRequest((int) $open[1]['request_id']);
        $out['failed'] = ['stop_id' => (int) $open[1]['id'], 'request_id' => (int) $open[1]['request_id']];
    }
    $out['success'] = true;
} catch (Throwable $e) {
    $out['success'] = false;
    $out['message'] = $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine();
}
while (ob_get_level()) ob_end_clean();
echo json_encode($out), "\n";
exit(empty($out['success']) ? 1 : 0);
