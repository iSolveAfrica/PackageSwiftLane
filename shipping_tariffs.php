<?php
// Rates & tariffs (views/sl/pages/Tariffs.php): one read-only sheet of the prices the shipment forms,
// the delivery quotes and the public calculator use. Editing stays on the existing settings pages.
require_once 'loader.php';$user=new User();$core=new Core();require_once 'helpers/sl_view.php';require_once 'helpers/sl_mx.php';sl_gate();
require_once 'views/sl/data/tariffs.php';
$can=sl_tariffsCan($user);
if(!$can['view']){header('Location: error403.php');exit;}
// RateEditor (?edit=1): the freight prices and charges are edited on the existing settings form.
if(!empty($_GET['edit'])&&$can['taxes']){header('Location: '.sl_tariffsEditUrl('taxes'));exit;}
sl_render('Tariffs',['id'=>'general','title'=>'Rates & tariffs','board'=>'Tariffs'],sl_tariffsData($user,$core,$can));
