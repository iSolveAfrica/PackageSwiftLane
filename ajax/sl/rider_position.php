<?php
require_once __DIR__.'/../../loader.php';
require_once CDP_ROOT.'/helpers/ajax_guard.php';
require_once CDP_ROOT.'/helpers/delivery_service.php';
$user=new User();
require_login();
header('Cache-Control: private, no-store');
if($_SERVER['REQUEST_METHOD']!=='POST'||!cdp_csrf_validate_request()||!sl_userIs('driver')||CDP_APP_MODE_DEMO)sl_json(['message'=>'Location update unavailable'],403);
if(!sl_tableExists('cdb_rider_positions'))sl_json(['message'=>'Location sharing is unavailable'],409);
$id=(int)($_POST['id']??0);
$actor=(int)$_SESSION['userid'];
$s=new DeliveryService();
if(($_POST['op']??'')==='stop'){
 $s->sql('DELETE FROM cdb_rider_positions WHERE driver_id=? AND request_id=?',[$actor,$id]);
 sl_json(['ok'=>true]);
}
$lat=filter_var($_POST['lat']??'',FILTER_VALIDATE_FLOAT);
$lng=filter_var($_POST['lng']??'',FILTER_VALIDATE_FLOAT);
if($lat===false||$lng===false||abs($lat)>90||abs($lng)>180)sl_json(['message'=>'Invalid location'],422);
if(time()-(int)($_SESSION['last_rider_position']??0)<8)sl_json(['message'=>'Wait before sharing again'],429);
try{
 $s->db->beginTransaction();
 $r=$s->get($id,$actor,'rider',0,true);
 if($r->state!=='onroute')throw new DomainException('Delivery is no longer active');
 $s->sql('INSERT INTO cdb_rider_positions(driver_id,request_id,latitude,longitude,recorded_at) VALUES(?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE request_id=VALUES(request_id),latitude=VALUES(latitude),longitude=VALUES(longitude),recorded_at=UTC_TIMESTAMP()',[$actor,$id,$lat,$lng]);
 $s->db->commit();
 $_SESSION['last_rider_position']=time();
 sl_json(['ok'=>true]);
}catch(DomainException $e){
 if($s->db->inTransaction())$s->db->rollBack();
 sl_json(['message'=>'Delivery is no longer active'],409);
}
