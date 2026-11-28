<?php
require_once 'loader.php';$user=new User();$core=new Core();require_once 'helpers/sl_view.php';sl_gate();require_once 'helpers/delivery_service.php';
if(!sl_userIs('driver')){header('Location: error403.php');exit;}
header('Cache-Control: private, no-store');require_once 'views/sl/data/delivery.php';
$d=dl_jobData((int)$_SESSION['userid']);
// Today's planned route (helpers/route_planner.php): keep the stop in step after deliver/fail and show parcel details.
$d['rpParcels']=[];$d['rpStop']=null;
if(!empty($d['request'])&&sl_tableExists('cdb_rp_stops')&&is_file(CDP_ROOT.'/helpers/route_planner.php')){
    try{
        require_once CDP_ROOT.'/helpers/route_planner.php';
        $req=$d['request'];
        if(in_array($req->state,['delivered','failed','cancelled'],true)&&function_exists('rp_syncStopFromRequest'))rp_syncStopFromRequest((int)$req->id);
        if(function_exists('rp_parcelDetails'))foreach(array_map('intval',json_decode($req->parcel_json,true)?:[]) as $oid)$d['rpParcels'][]=rp_parcelDetails((string)$req->module,$oid);
        $d['rpStop']=sl_row("SELECT s.id, s.seq, s.status, s.eta, s.window_start, s.window_end FROM cdb_rp_stops s JOIN cdb_rp_routes r ON r.id = s.route_id WHERE s.request_id = :q AND r.driver_id = :u AND s.status <> 'removed' ORDER BY s.id DESC",[':q'=>(int)$req->id,':u'=>(int)$_SESSION['userid']]);
    }catch(Throwable $e){error_log('delivery job route: '.$e->getMessage());}
}
sl_render('DeliveryJob',['id'=>'drvapp','title'=>'Delivery job','board'=>'Delivery','styles'=>['assets/swiftlane/route-map.css'],'scripts'=>['assets/swiftlane/pwa/handover.js','dataJs/sl/rider_position.js']],$d);
