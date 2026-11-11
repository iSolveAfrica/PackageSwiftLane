<?php
/** Integration check against a disposable database only; all fixture writes roll back. */
require_once __DIR__ . '/../loader.php';
require_once CDP_ROOT . '/views/sl/data/pickup_codes.php';
if (!preg_match('/test|audit/i', CDP_DB_NAME)) { fwrite(STDERR, "Use a disposable test/audit database.\n"); exit(1); }
$pdo = (new Conexion())->dbh;
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->beginTransaction();
try {
    $order = $pdo->query('SELECT order_id, sender_id FROM cdb_add_order WHERE sender_id > 0 AND is_deleted = 0 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new RuntimeException('Seed a test shipment first');
    $owner = (int) $order['sender_id']; $oid = (int) $order['order_id'];
    $pdo->exec("UPDATE cdb_add_order SET status_courier=6 WHERE order_id=$oid");
    $ids = [];
    foreach (['active', 'verified', 'expired', 'void', 'used', 'wrong-owner', 'consumed', 'expired-clock'] as $case) {
        $status = in_array($case, ['wrong-owner','consumed','expired-clock'], true) ? 'active' : $case;
        $stmt = $pdo->prepare("INSERT INTO cdb_pickup_codes (code,module,owner_id,context,status,created_at,expires_at) VALUES ('987654','air',?,'wallet-test',?,NOW(),?)");
        $stmt->execute([$case === 'wrong-owner' ? $owner + 100000 : $owner, $status, $case === 'expired-clock' ? '2000-01-01 00:00:00' : date('Y-m-d H:i:s', time()+3600)]);
        $id = (int) $pdo->lastInsertId(); $ids[$case] = $id;
        $pdo->exec("INSERT INTO cdb_pickup_code_items (code_id,module,order_id,used_at) VALUES ($id,'air',$oid," . ($case === 'consumed' ? 'NOW()' : 'NULL') . ')');
    }
    $visible = array_map(fn($c) => (int) $c->id, sl_pickupCodesData($owner)['codes']);
    foreach ($ids as $case => $id) {
        if (in_array($id, $visible, true) !== in_array($case, ['active','verified'], true)) throw new RuntimeException("Unexpected visibility: $case");
    }
    $pdo->exec("UPDATE cdb_add_order SET sender_id=" . ($owner+100000) . " WHERE order_id=$oid");
    $visible = array_map(fn($c) => (int) $c->id, sl_pickupCodesData($owner)['codes']);
    foreach (['active','verified'] as $case) if (in_array($ids[$case], $visible, true)) throw new RuntimeException('Parcel owner boundary failed');
    echo "Pickup wallet checks passed: active/verified, expiry, void/used, consumed items and both ownership boundaries.\n";
} finally { $pdo->rollBack(); }
