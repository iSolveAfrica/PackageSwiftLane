<?php
/** Run every minute. No sends without --send; max 25 messages per run. */
if(PHP_SAPI!=='cli')exit;
require_once __DIR__.'/../loader.php';require_once CDP_ROOT.'/helpers/delivery_service.php';
if(!in_array('--send',$argv,true)){echo "Dry run: ".sl_scalar("SELECT COUNT(*) FROM cdb_delivery_outbox WHERE state='pending'")." queued delivery notifications. Use --send to deliver.\n";exit;}
if(CDP_APP_MODE_DEMO===true){fwrite(STDERR,"Delivery messages are disabled in demo mode\n");exit(1);}
$s=new DeliveryService();if(!(int)$s->sql("SELECT GET_LOCK('swiftlane_delivery_messages',0)")->fetchColumn()){echo "Another delivery worker is running\n";exit;}
try{
    require_once CDP_ROOT.'/helpers/push_notify.php';$settings=cdp_getSettingsCourier();
    $s->sql("UPDATE cdb_delivery_outbox SET state='pending',locked_at=NULL WHERE state='sending' AND locked_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
    $rows=$s->sql("SELECT * FROM cdb_delivery_outbox WHERE state='pending' AND available_at<=NOW() AND attempts<5 ORDER BY id LIMIT 25")->fetchAll(PDO::FETCH_OBJ);
    foreach($rows as $m){
        $s->sql("UPDATE cdb_delivery_outbox SET state='sending',locked_at=NOW(),attempts=attempts+1 WHERE id=?",[$m->id]);
        try{
            $recipient=$s->sql('SELECT * FROM cdb_users WHERE id=? AND active=1',[$m->recipient_id])->fetch(PDO::FETCH_OBJ);
            if(!$recipient)throw new RuntimeException('Recipient inactive or missing');
            $previous=json_decode($m->result_json?:'{}',true)?:[];
            $channels=array_filter(['whatsapp','email','sms'],fn($ch)=>(($previous[$ch]['status']??'')!=='sent'));
            $result=cdp_pushNotifyUser($recipient,$m->subject,$m->message,$settings,['source'=>'delivery_request','batch_id'=>'delivery-outbox-'.$m->id],implode(',',$channels));
            // Retry only failed channels, not channels that already succeeded.
            // Initial sends use all configured channels; retried sends are restricted below on later runs.
            foreach($result as $channel=>$r)if(in_array($channel,$channels,true))$previous[$channel]=$r;
            $sent=array_filter($previous,fn($r)=>($r['status']??'')==='sent');
            $failed=array_filter($previous,fn($r)=>($r['status']??'')==='failed');
            $state=$failed?((int)$m->attempts+1>=5?'failed':'pending'):($sent?'sent':'failed');
            $s->sql('UPDATE cdb_delivery_outbox SET state=?,result_json=?,locked_at=NULL,available_at=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE id=?',[$state,json_encode($previous),$m->id]);
        }catch(Throwable $e){$s->sql("UPDATE cdb_delivery_outbox SET state=?,result_json=?,locked_at=NULL,available_at=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE id=?",[(int)$m->attempts+1>=5?'failed':'pending',json_encode(['error'=>'Notification attempt failed; inspect Message Logs']),$m->id]);error_log('delivery notification #'.$m->id.': '.$e->getMessage());}
    }
    echo count($rows)." delivery notifications processed\n";
}finally{$s->sql("SELECT RELEASE_LOCK('swiftlane_delivery_messages')");}
