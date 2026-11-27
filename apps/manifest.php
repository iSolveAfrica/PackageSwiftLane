<?php
if(!defined('SL_PWA_ROLE')||!in_array(SL_PWA_ROLE,['customer','rider','staff'],true)){http_response_code(404);exit;}
$role=SL_PWA_ROLE;$rider=$role==='rider';$staff=$role==='staff';
header('Content-Type: application/manifest+json');header('Cache-Control: public, max-age=3600');header('X-Content-Type-Options: nosniff');
echo json_encode([
 'id'=>rtrim(str_replace('\\','/',dirname((string)$_SERVER['SCRIPT_NAME'])),'/').'/','name'=>$staff?'Swift Lane Staff':($rider?'Swift Lane Rider':'Swift Lane — My Parcels'),'short_name'=>$staff?'SL Staff':($rider?'SL Rider':'Swift Lane'),
 'description'=>$staff?'Shipments, packages, consolidations, the warehouse, customers and reports: the whole Swift Lane system as an app.':($rider?'Your delivery run: assigned stops, navigation, contact and secure handover.':'Your parcels, delivery requests, pickup codes, bills and support in one place.'),
 'start_url'=>'app.php/'.($rider?'driver_app.php':'index.php').'?launch=pwa','scope'=>'./',
 'display'=>'standalone','display_override'=>['standalone','minimal-ui'],'background_color'=>'#F3F2EF','theme_color'=>'#F3F2EF','lang'=>'en',
 'icons'=>array_merge(array_map(fn($size)=>['src'=>'../../assets/swiftlane/pwa/'.$role.'-'.$size.'.png','sizes'=>$size.'x'.$size,'type'=>'image/png','purpose'=>'any'],[192,512]),[['src'=>'../../assets/swiftlane/pwa/'.$role.'-maskable.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'maskable']]),
 'shortcuts'=>$staff?[
  ['name'=>'New shipment','url'=>'app.php/courier_add.php'],['name'=>'Package intake','url'=>'app.php/package_intake.php'],['name'=>'Hand out packages','url'=>'app.php/warehouse_delivery.php'],['name'=>'WhatsApp inbox','url'=>'app.php/comms_inbox.php'],['name'=>'Smart search','url'=>'app.php/locker_search.php']
 ]:($rider?[
  ['name'=>'My route','url'=>'app.php/driver_app.php?tab=route'],['name'=>'Earnings','url'=>'app.php/driver_app.php?tab=pay']
 ]:[['name'=>'My parcels','url'=>'app.php/my_parcels.php'],['name'=>'Track a parcel','url'=>'app.php/track.php'],['name'=>'Pickup codes','url'=>'app.php/pickup_codes.php'],['name'=>'Request delivery','url'=>'app.php/delivery_request.php'],['name'=>'Support','url'=>'app.php/support.php']]),
 'categories'=>['business','utilities'],'prefer_related_applications'=>false
],JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
