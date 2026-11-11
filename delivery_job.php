<?php
require_once 'loader.php';$user=new User();$core=new Core();require_once 'helpers/sl_view.php';sl_gate();require_once 'helpers/delivery_service.php';
if(!sl_userIs('driver')){header('Location: error403.php');exit;}
header('Cache-Control: private, no-store');require_once 'views/sl/data/delivery.php';
sl_render('DeliveryJob',['id'=>'drvapp','title'=>'Delivery job','board'=>'Delivery','scripts'=>['assets/swiftlane/pwa/handover.js']],dl_jobData((int)$_SESSION['userid']));
