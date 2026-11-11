<?php
require_once 'loader.php';$user=new User();$core=new Core();require_once 'helpers/sl_view.php';sl_gate();require_once 'helpers/delivery_service.php';
if(!dl_manager($user)){header('Location: error403.php');exit;}
require_once 'views/sl/data/delivery.php';
sl_render('DeliveryDispatch',['id'=>'deliverydispatch','title'=>'Delivery requests','board'=>'Delivery'],dl_dispatchData());
