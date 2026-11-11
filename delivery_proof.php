<?php
require_once 'loader.php';$user=new User();$core=new Core();require_once 'helpers/sl_view.php';sl_gate();require_once 'helpers/delivery_service.php';
$role=sl_userIs('client')?'customer':(sl_userIs('driver')?'rider':(dl_manager($user)?'manager':''));
try{$r=(new DeliveryService())->get((int)($_GET['id']??0),(int)$_SESSION['userid'],$role,dl_agency());}catch(Throwable $e){http_response_code(404);exit;}
if(!$r->proof_photo||!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/',$r->proof_photo)){http_response_code(404);exit;}
$path=(getenv('CDP_DELIVERY_PROOF_DIR')?:dirname(CDP_ROOT).'/delivery-proof').'/'.$r->proof_photo;
if(!is_file($path)){http_response_code(404);exit;}
header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($path));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($path);
