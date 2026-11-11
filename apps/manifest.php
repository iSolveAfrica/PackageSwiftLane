<?php
if(!defined('SL_PWA_ROLE')||!in_array(SL_PWA_ROLE,['customer','rider'],true)){http_response_code(404);exit;}
$role=SL_PWA_ROLE;$rider=$role==='rider';
header('Content-Type: application/manifest+json');header('Cache-Control: public, max-age=3600');header('X-Content-Type-Options: nosniff');
echo json_encode([
 'id'=>rtrim(str_replace('\\','/',dirname((string)$_SERVER['SCRIPT_NAME'])),'/').'/','name'=>$rider?'Swift Lane Rider':'Swift Lane — My Parcels','short_name'=>$rider?'SL Rider':'Swift Lane',
 'description'=>$rider?'Your delivery run: assigned stops, navigation, contact and secure handover.':'Your parcels, delivery requests, pickup codes, bills and support in one place.',
 'start_url'=>'app.php/'.($rider?'driver_app.php':'index.php').'?launch=pwa','scope'=>'./',
 'display'=>'standalone','display_override'=>['standalone','minimal-ui'],'background_color'=>$rider?'#f2f4f5':'#f5f7f4','theme_color'=>$rider?'#14252b':'#123d35','lang'=>'en',
 'icons'=>array_merge(array_map(fn($size)=>['src'=>'../../assets/swiftlane/pwa/'.$role.'-'.$size.'.png','sizes'=>$size.'x'.$size,'type'=>'image/png','purpose'=>'any'],[192,512]),[['src'=>'../../assets/swiftlane/pwa/'.$role.'-maskable.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'maskable']]),
 'shortcuts'=>$rider?[
  ['name'=>'My route','url'=>'app.php/driver_app.php?tab=route'],['name'=>'Earnings','url'=>'app.php/driver_app.php?tab=pay']
 ]:[['name'=>'Track a parcel','url'=>'app.php/track.php'],['name'=>'Pickup codes','url'=>'app.php/pickup_codes.php'],['name'=>'Request delivery','url'=>'app.php/delivery_request.php'],['name'=>'Support','url'=>'app.php/support.php']],
 'categories'=>['business','utilities'],'prefer_related_applications'=>false
],JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
