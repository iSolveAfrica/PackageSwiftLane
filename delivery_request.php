<?php
require_once 'loader.php';$user=new User();$core=new Core();require_once 'helpers/sl_view.php';sl_gate();
require_once 'helpers/delivery_service.php';
if(!sl_userIs('client')){header('Location: error403.php');exit;}
header('Cache-Control: private, no-store');
require_once 'views/sl/data/delivery.php';
sl_render('DeliveryRequest',['id'=>'deliveries','title'=>'Request delivery','board'=>'Delivery','scripts'=>['dataJs/sl/delivery.js']],dl_customerData((int)$_SESSION['userid']));
