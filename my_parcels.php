<?php
require_once 'loader.php';$user=new User();$core=new Core();require_once 'helpers/sl_view.php';sl_gate();
if(!sl_userIs('client')){header('Location: error403.php');exit;}
require_once 'views/sl/data/portal.php';
$filter=(string)($_GET['filter']??'active');if(!in_array($filter,['active','ready','delivered','all'],true))$filter='active';
$q=mb_substr(trim((string)($_GET['q']??'')),0,100);$page=max(1,(int)($_GET['page']??1));$per=12;$owner=(int)$_SESSION['userid'];
$d=['filter'=>$filter,'q'=>$q,'page'=>$page,'per'=>$per,'rows'=>sl_portalShipments($owner,$per,($page-1)*$per,$q,$filter),'count'=>sl_portalCount($owner,$q,$filter)];
sl_render('MyParcels',['id'=>'parcels','title'=>'My parcels','board'=>'MyParcels'],$d);
