<?php
require_once 'loader.php';$user=new User();$core=new Core();require_once 'helpers/sl_view.php';sl_gate();require_once 'helpers/delivery_service.php';
if(!dl_manager($user)){header('Location: error403.php');exit;}
require_once 'views/sl/data/delivery.php';
if(isset($_GET['snapshot'])){
 if(!sl_tableExists('cdb_delivery_requests'))sl_json(['message'=>'Delivery data unavailable'],503);
 header('Cache-Control: private, no-store');
 sl_json(dl_fleetSnapshot());
}
$d=dl_dispatchData();
$d['fleet']=$d['ready']?dl_fleetSnapshot():null;
sl_render('DeliveryDispatch',['id'=>'deliverydispatch','title'=>'Delivery requests','board'=>'Delivery','styles'=>['assets/swiftlane/pages/DeliveryFleet.css'],'scripts'=>['dataJs/sl/delivery_fleet.js','dataJs/sl/dispatch_ai.js']],$d);
