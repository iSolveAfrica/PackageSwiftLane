<?php
require_once 'loader.php';$user=new User();$core=new Core();require_once 'helpers/sl_view.php';sl_gate();require_once 'helpers/delivery_service.php';
if(!dl_manager($user)||dl_agency()||!$user->cdp_hasPermission(['manage_freight','manage_rider_fees'])){header('Location: error403.php');exit;}
require_once 'views/sl/data/delivery.php';
sl_render('DeliverySettings',['id'=>'deliverysettings','title'=>'Delivery pricing and zones','board'=>'Delivery','scripts'=>['dataJs/sl/delivery.js']],dl_settingsData());
