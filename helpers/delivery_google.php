<?php
/** Server-only Google Geocoding + Routes API. No client-supplied distances/prices. */
class DeliveryGoogle
{
    private string $key;
    public function __construct(string $key){$this->key=$key;}
    protected function call(string $url,?array $body=null,array $headers=[]): array
    {
        if($this->key==='')throw new DomainException('Google Maps server key is not configured. Please contact the office.');
        $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>$headers]);
        if($body!==null){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));}
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $data=json_decode((string)$raw,true);
        if($status!==200||!is_array($data)||isset($data['error']))throw new DomainException('Google Maps could not verify this trip. Please retry or contact the office.');
        return $data;
    }
    public function place(string $id,string $country): array
    {
        if(!preg_match('/^[A-Za-z0-9_-]{5,255}$/',$id))throw new DomainException('Select a location from Google Maps');
        $data=$this->call('https://maps.googleapis.com/maps/api/geocode/json?'.http_build_query(['place_id'=>$id,'key'=>$this->key]));
        if(($data['status']??'')!=='OK'||empty($data['results'][0])||!empty($data['results'][0]['partial_match']))throw new DomainException('Google could not verify that location. Choose a more precise place.');
        $r=$data['results'][0];$localities=[];$actualCountry='';
        foreach($r['address_components']??[] as $part){if(in_array('country',$part['types']))$actualCountry=$part['short_name'];if(array_intersect($part['types'],['locality','sublocality','sublocality_level_1','administrative_area_level_2']))$localities[]=$part['long_name'];}
        if(strtoupper($actualCountry)!==strtoupper($country))throw new DomainException('That location is outside our delivery country');
        if(!array_intersect($r['types']??[],['street_address','premise','subpremise','establishment','point_of_interest','plus_code','intersection']))throw new DomainException('Select a building, street address or nearby landmark, rather than a city or region');
        $p=$r['geometry']['location']??[];
        if(!isset($p['lat'],$p['lng']))throw new DomainException('Google returned no coordinates');
        return ['place_id'=>$r['place_id'],'address'=>$r['formatted_address'],'lat'=>(float)$p['lat'],'lng'=>(float)$p['lng'],'localities'=>$localities];
    }
    public function route(string $origin,string $destination): array
    {
        $d=$this->call('https://routes.googleapis.com/directions/v2:computeRoutes',[
            'origin'=>['placeId'=>$origin],'destination'=>['placeId'=>$destination], 'travelMode'=>'DRIVE','routingPreference'=>'TRAFFIC_UNAWARE','computeAlternativeRoutes'=>false],
            ['Content-Type: application/json','X-Goog-Api-Key: '.$this->key,'X-Goog-FieldMask: routes.distanceMeters,routes.duration']);
        $route=$d['routes'][0]??[];
        if(!isset($route['distanceMeters'])||(int)$route['distanceMeters']<0)throw new DomainException('Google Maps found no drivable route to this location');
        return ['distance_m'=>(int)$route['distanceMeters'],'duration'=>(string)($route['duration']??'')];
    }
}
