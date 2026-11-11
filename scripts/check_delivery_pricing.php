<?php
require_once __DIR__.'/../helpers/delivery_pricing.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$r=['base_cents'=>500,'minimum_cents'=>1500,'per_km_cents'=>275,'per_package_cents'=>900];
check(dl_price('kilometre',$r,12501,2,null)['total_cents']===3938,'Road metres round up to minor units');
check(dl_price('package',$r,5000,3,null)['total_cents']===3200,'Every selected parcel is priced');
check(dl_price('zone',$r,5000,3,2500)['total_cents']===3000,'Zone rate applies once per trip');
check(dl_price('zone',$r,0,1,0)['total_cents']===1500,'Minimum fare');
foreach([['zone',$r,1,1,null],['bad',$r,1,1,100],['package',$r,1,0,100]] as $args){try{dl_price(...$args);throw new RuntimeException('Invalid price accepted');}catch(DomainException $e){}}
$z=(object)['id'=>1,'agency_id'=>0,'polygon_json'=>'','radius_km'=>null,'city_match'=>'Tema','priority'=>100];
$p=['lat'=>5.6,'lng'=>0,'localities'=>['Tema New Town']];check(dl_zone([$z],$p,0)===null,'Partial city names must not match');
$p['localities']=['Tema'];check(dl_zone([$z],$p,0)->id===1,'Exact locality');
$second=clone $z;$second->id=2;try{dl_zone([$z,$second],$p,0);throw new RuntimeException('Ambiguous zone accepted');}catch(DomainException $e){}
$second->priority=10;check(dl_zone([$z,$second],$p,0)->id===2,'Zone priority');
check(dl_inside(5.5,-0.5,[[5,-1],[6,-1],[6,0],[5,0]]),'Point in polygon');check(!dl_inside(7,1,[[5,-1],[6,-1],[6,0],[5,0]]),'Outside polygon');
check(dl_km(5,0,5,0)===0.0,'Zero distance');
echo "Delivery pricing checks passed: modes, minor-unit rounding, minimum fare, package count, exact zones, overlaps, polygons.\n";
