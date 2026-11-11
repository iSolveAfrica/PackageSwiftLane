<?php
/** Integration check. Refuses production DBs; restores original fixture rows. No provider calls. */
require_once __DIR__.'/../loader.php';require_once CDP_ROOT.'/helpers/delivery_service.php';require_once CDP_ROOT.'/helpers/pickup_code.php';
if(!preg_match('/test|audit/i',CDP_DB_NAME)){fwrite(STDERR,"Use a disposable test/audit database\n");exit(1);}
function verify($ok,$why){if(!$ok)throw new RuntimeException($why);}
function rejects(callable $f,string $why){try{$f();}catch(DomainException $e){return;}throw new RuntimeException($why);}
class TestDeliveryGoogle extends DeliveryGoogle{
 public function __construct(){}
 public function place(string $id,string $country):array{if($id==='invalid')throw new DomainException('Invalid location');return ['place_id'=>$id,'address'=>'Verified test address '.$id,'lat'=>5.67,'lng'=>-0.01,'localities'=>['Delivery Test Zone']];}
 public function route(string $origin,string $destination):array{return ['distance_m'=>12501,'duration'=>'1200s'];}
}
$s=new DeliveryService();$db=$s->db;$configRow=$s->sql('SELECT config_json,revision FROM cdb_delivery_config WHERE id=1')->fetch(PDO::FETCH_ASSOC);
$customer=$s->sql("SELECT id FROM cdb_users WHERE username='ama.boateng0'")->fetchColumn();$driver=$s->sql("SELECT id FROM cdb_users WHERE username='uiauditdriver'")->fetchColumn();
verify($customer&&$driver,'Seed the local UI test customer and rider first');
$trackBefore=(int)$s->sql('SELECT COALESCE(MAX(id),0) FROM cdb_courier_track')->fetchColumn();
$original=[];$requests=[];$zone=null;$codeId=null;
try{
 cdp_pickupCodeEnsureTables();
 $c=dl_config();$c=array_replace($c,['enabled'=>true,'modes'=>['zone','kilometre','package'],'default_mode'=>'kilometre','per_km_cents'=>275,'per_package_cents'=>900,'base_cents'=>500,'minimum_cents'=>1500,'origin_place_id'=>'origin-test','max_km'=>100,'rider_pct'=>70,'country'=>'GH']);
 $s->sql('UPDATE cdb_delivery_config SET config_json=? WHERE id=1',[json_encode($c)]);
 foreach(['air'=>'cdb_add_order','sea'=>'cdb_customers_packages'] as $module=>$table){$row=$s->sql("SELECT * FROM $table WHERE sender_id=? ORDER BY order_id LIMIT 1",[$customer])->fetch(PDO::FETCH_ASSOC);verify($row,'Seed a parcel in '.$module);$original[$module]=[$table,$row];$s->sql("UPDATE $table SET driver_id=NULL,status_courier=6,agency=0 WHERE order_id=?",[$row['order_id']]);if($module==='air')$s->sql('UPDATE cdb_add_order SET fs_cleared_for_delivery=1,is_pickup=0 WHERE order_id=?',[$row['order_id']]);}
 $s->sql("INSERT INTO cdb_delivery_zones(name,city_match,fee,rider_id,active,created_at) VALUES ('Delivery test zone','Delivery Test Zone',25,?,1,NOW())",[$driver]);$zone=(int)$db->lastInsertId();
 $s=new DeliveryService($db,$c,new TestDeliveryGoogle());$air=(int)$original['air'][1]['order_id'];$sea=(int)$original['sea'][1]['order_id'];
 rejects(fn()=>$s->quote($customer+100000,'air',[$air],'dest-test','kilometre','DL Test','+233555123456',''),'Foreign parcel accepted');
 rejects(fn()=>$s->quote($customer,'air',[$air],'invalid','kilometre','DL Test','+233555123456',''),'Unverified destination accepted');
 $id=$s->quote($customer,'air',[$air],'dest-test','kilometre','DL Test','+233555123456','Blue gate');$requests[]=$id;
 $competing=$s->quote($customer,'air',[$air],'dest-test','package','DL Test','+233555123456','');$requests[]=$competing;
 $q=$s->get($id,$customer,'customer');verify((int)$q->amount_cents===3938,'Stored route quote');
 rejects(fn()=>$s->get($id,$customer+1,'customer'),'Foreign customer read');
 rejects(fn()=>$s->get($id,$driver,'rider'),'Unassigned rider read');
 // Changing current rates does not change a live customer quote.
 $s->sql('UPDATE cdb_delivery_config SET config_json=?,revision=revision+1 WHERE id=1',[json_encode(array_replace($c,['per_km_cents'=>9999]))]);
 $r=$s->approve($id,$customer);verify($r->state==='assigned'&&(int)$r->driver_id===(int)$driver,'Zone auto assignment');verify((int)$r->amount_cents===3938,'Approved snapshot changed');
 rejects(fn()=>$s->approve($competing,$customer),'Concurrent quote claimed a reserved parcel');
 $s->approve($id,$customer);verify((int)$s->sql('SELECT COUNT(*) FROM cdb_delivery_items WHERE request_id=?',[$id])->fetchColumn()===1,'Duplicate approval items');
 rejects(fn()=>$s->quote($customer,'air',[$air],'dest-test','package','DL Test','+233555123456',''),'Active parcel duplicate quote');
 rejects(fn()=>$s->transition($id,$driver+1,'rider',0,'start'),'Foreign rider started');
 $s->transition($id,$driver,'rider',0,'start');verify($s->get($id,$driver,'rider')->state==='onroute','Start delivery');
 rejects(fn()=>$s->transition($id,$customer,'customer',0,'cancel'),'Customer cancelled on-route delivery');
 $s->transition($id,$driver,'rider',0,'fail',['note'=>'Customer not available']);verify($s->get($id,$driver,'rider')->state==='failed','Failed attempt');
 $s->transition($id,999,'manager',0,'assign',['driver_id'=>$driver]);$s->transition($id,$driver,'rider',0,'start');
 // A live code for exactly this parcel; no messages are issued.
 $s->sql("INSERT INTO cdb_pickup_codes(code,module,owner_id,context,status,created_at,expires_at) VALUES ('746291','air',?,'delivery-workflow-test','active',NOW(),DATE_ADD(NOW(),INTERVAL 1 DAY))",[$customer]);$codeId=(int)$db->lastInsertId();
 $s->sql("INSERT INTO cdb_pickup_code_items(code_id,module,order_id) VALUES (?,'air',?)",[$codeId,$air]);
 rejects(fn()=>$s->transition($id,$driver,'rider',0,'deliver',['code'=>'123456','receiver'=>'Test receiver','proof'=>str_repeat('a',32).'.jpg']),'Wrong code accepted');
 $s->transition($id,$driver,'rider',0,'deliver',['code'=>'746291','receiver'=>'Test receiver','proof'=>str_repeat('a',32).'.jpg','fee_collected'=>1]);
 $r=$s->get($id,$customer,'customer');verify($r->state==='delivered'&&(int)$r->fee_collected===1,'Completion/collection');verify((int)$s->sql('SELECT status_courier FROM cdb_add_order WHERE order_id=?',[$air])->fetchColumn()===8,'Package tracking not updated');verify(!$s->sql('SELECT request_id FROM cdb_delivery_claims WHERE request_id=?',[$id])->fetchColumn(),'Claim not released');
 // Sea request without a preferred rider stays in dispatch, and expiry is enforced.
 $s->sql('UPDATE cdb_delivery_zones SET rider_id=NULL WHERE id=?',[$zone]);
 $id2=$s->quote($customer,'sea',[$sea],'dest-test','package','DL Test','+233555123456','');$requests[]=$id2;
 $s->sql('UPDATE cdb_delivery_requests SET expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?',[$id2]);rejects(fn()=>$s->approve($id2,$customer),'Expired quote accepted');
 $s->sql('UPDATE cdb_delivery_requests SET expires_at=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE id=?',[$id2]);$r=$s->approve($id2,$customer);verify($r->state==='pending','Missing rider did not stay pending');
 $s->transition($id2,$customer,'customer',0,'cancel');verify($s->get($id2,$customer,'customer')->state==='cancelled','Cancel pending');
 verify((int)$s->sql("SELECT COUNT(*) FROM cdb_delivery_outbox WHERE event_key LIKE ?",[$id.':%'])->fetchColumn()>0,'No durable customer notifications');
 echo "Delivery workflow passed: owned air/sea, Google verification, quote snapshot, expiry, idempotent approval, zone assignment/fallback, rider scope, start/fail/retry, code gate, proof, collection and tracking.\n";
}finally{
 foreach($requests as $id){$s->sql('DELETE u FROM cdb_notifications_users u JOIN cdb_notifications n ON n.notification_id=u.notification_id WHERE n.shipping_type=99 AND n.order_id=?',[$id]);$s->sql('DELETE FROM cdb_notifications WHERE shipping_type=99 AND order_id=?',[$id]);$s->sql('DELETE FROM cdb_delivery_outbox WHERE event_key LIKE ?',[$id.':%']);foreach(['cdb_delivery_events','cdb_delivery_items','cdb_delivery_claims'] as $t)$s->sql("DELETE FROM $t WHERE request_id=?",[$id]);$s->sql('DELETE FROM cdb_delivery_requests WHERE id=?',[$id]);}
 if($codeId){$s->sql('DELETE FROM cdb_pickup_code_items WHERE code_id=?',[$codeId]);$s->sql('DELETE FROM cdb_pickup_codes WHERE id=?',[$codeId]);}
 foreach($original as [$table,$row]){$s->sql('DELETE FROM cdb_courier_track WHERE id>? AND order_id=? AND user_id=?',[$trackBefore,$row['order_id'],$driver]);$columns=['driver_id','status_courier','agency','person_receives'];if($table==='cdb_add_order')$columns=array_merge($columns,['fs_cleared_for_delivery','is_pickup','pod_receiver','pod_at']);$s->sql("UPDATE $table SET ".implode(',',array_map(fn($k)=>"$k=?",$columns)).' WHERE order_id=?',array_merge(array_map(fn($k)=>$row[$k],$columns),[$row['order_id']]));}
 if($zone)$s->sql('DELETE FROM cdb_delivery_zones WHERE id=?',[$zone]);
 $s->sql('UPDATE cdb_delivery_config SET config_json=?,revision=? WHERE id=1',[$configRow['config_json'],$configRow['revision']]);
}
