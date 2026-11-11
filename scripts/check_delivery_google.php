<?php
require_once __DIR__.'/../helpers/delivery_google.php';
class StubDeliveryGoogle extends DeliveryGoogle{public array $response=[];public array $calls=[];protected function call(string $url,?array $body=null,array $headers=[]):array{$this->calls[]=[$url,$body,$headers];return $this->response;}}
function assertGoogle($ok,$message){if(!$ok)throw new RuntimeException($message);}
$g=new StubDeliveryGoogle('private-key-not-for-browser');
$g->response=['status'=>'OK','results'=>[['place_id'=>'ChIJ_Test_place','formatted_address'=>'Verified building, Tema, Ghana','types'=>['premise'],'geometry'=>['location'=>['lat'=>5.67,'lng'=>0]],'address_components'=>[['types'=>['country'],'short_name'=>'GH'],['types'=>['locality'],'long_name'=>'Tema']]]]];
$p=$g->place('ChIJ_Test_place','GH');assertGoogle($p['lat']===5.67&&$p['localities']===['Tema'],'Verified Google location');
$g->response['results'][0]['address_components'][0]['short_name']='NG';try{$g->place('ChIJ_Test_place','GH');throw new RuntimeException('Foreign country accepted');}catch(DomainException $e){}
$g->response['results'][0]['address_components'][0]['short_name']='GH';$g->response['results'][0]['types']=['locality'];try{$g->place('ChIJ_Test_place','GH');throw new RuntimeException('City accepted as exact destination');}catch(DomainException $e){}
$g->response=['routes'=>[['distanceMeters'=>12501,'duration'=>'1200s']]];$route=$g->route('origin-id','destination-id');assertGoogle($route['distance_m']===12501,'Road distance comes from Routes');$call=end($g->calls);assertGoogle($call[1]['origin']['placeId']==='origin-id'&&$call[1]['destination']['placeId']==='destination-id','Route verified place IDs');assertGoogle(in_array('X-Goog-FieldMask: routes.distanceMeters,routes.duration',$call[2]),'Routes field mask');
$g->response=['routes'=>[]];try{$g->route('origin','dest');throw new RuntimeException('No route accepted');}catch(DomainException $e){}
echo "Google delivery contract checks passed: verified place, country, exact destination, road distance and no-route failure.\n";
