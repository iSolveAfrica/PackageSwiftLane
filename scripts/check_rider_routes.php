<?php
/** Disposable DB integration test; contact/address changes roll back. */
require_once __DIR__ . '/../loader.php';
require_once CDP_ROOT . '/views/sl/data/riders.php';
if (!preg_match('/test|audit/i', CDP_DB_NAME)) { fwrite(STDERR, "Use a disposable test/audit database.\n"); exit(1); }
$pdo = (new Conexion())->dbh; $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->beginTransaction();
try {
    $order = $pdo->query("SELECT o.order_id,o.sender_id,o.order_prefix,o.order_no,a.id FROM cdb_add_order o JOIN cdb_address_shipments a ON a.order_track=CONCAT(o.order_prefix,o.order_no) WHERE o.sender_id>0 AND o.is_deleted=0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $receiver = $pdo->query("SELECT id,fname,lname,phone FROM cdb_users WHERE active=1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$order || !$receiver) throw new RuntimeException('Seed test users and shipments first');
    $oid=(int)$order['order_id']; $rid=(int)$receiver['id']; $uid=(int)$order['sender_id'];
    $pdo->exec("UPDATE cdb_add_order SET driver_id=$rid,receiver_id=$rid,recipient_type='user',status_courier=7,is_pickup=0 WHERE order_id=$oid");
    $pdo->exec("UPDATE cdb_address_shipments SET sender_address='Pickup Street',sender_city='Accra',sender_country='Ghana',recipient_address='Delivery Street',recipient_city='Tema',recipient_country='Ghana' WHERE id=".(int)$order['id']);
    $find=function()use($rid,$oid){foreach(sl_rdJobs(date('Y-m-d'),date('Y-m-d'),$rid)as $j)if((int)$j->order_id===$oid)return $j;throw new RuntimeException('Assigned job missing');};
    $j=$find();
    if ($j->addr!=='Delivery Street, Tema, Ghana' || trim($j->cust)!==trim($receiver['fname'].' '.$receiver['lname']) || (string)$j->cust_phone!==(string)$receiver['phone']) throw new RuntimeException('Delivery must use recipient contact and shipment address');
    $pdo->exec("UPDATE cdb_add_order SET is_pickup=1,status_courier=14 WHERE order_id=$oid");
    $sender=$pdo->query("SELECT CONCAT(fname,' ',lname) AS name,phone FROM cdb_users WHERE id=$uid")->fetch(PDO::FETCH_ASSOC);
    $j=$find();if($j->addr!=='Pickup Street, Accra, Ghana'||trim($j->cust)!==trim($sender['name'])||(string)$j->cust_phone!==(string)$sender['phone'])throw new RuntimeException('Pickup must use sender contact and shipment address');
    foreach(sl_rdJobs(date('Y-m-d'),date('Y-m-d'),$rid+100000)as $j)if((int)$j->order_id===$oid)throw new RuntimeException('Driver scope failed');
    if(sl_riderNavigation('—')!==''||sl_riderRouteMap([])['embed']!=='')throw new RuntimeException('Missing addresses must not invent map locations');
    $map=sl_riderRouteMap([['addr'=>'Accra & Tema'],['addr'=>'Cape Coast']]);parse_str(parse_url($map['embed'],PHP_URL_QUERY),$query);
    if($query['saddr']!=='Accra & Tema'||$query['daddr']!=='Cape Coast')throw new RuntimeException('Route address encoding failed');
    echo "Rider route checks passed: pickup/delivery contact and address, driver scope, missing addresses, encoded route endpoints.\n";
} finally { $pdo->rollBack(); }
