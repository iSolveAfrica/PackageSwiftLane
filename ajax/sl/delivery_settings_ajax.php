<?php
require_once __DIR__.'/../../loader.php';require_once CDP_ROOT.'/helpers/ajax_guard.php';require_once CDP_ROOT.'/helpers/delivery_service.php';
$user=new User();$core=new Core();require_login();$user->cdp_getUserPermissions();
if(!dl_manager($user)||dl_agency()||!$user->cdp_hasPermission(['manage_freight','manage_rider_fees']))sl_json(['success'=>false,'message'=>'Global delivery configuration requires an authorized manager'],403);
if($_SERVER['REQUEST_METHOD']!=='POST')sl_json(['success'=>false,'message'=>'POST required'],405);
if(CDP_APP_MODE_DEMO===true)sl_json(['success'=>false,'message'=>'Changes are disabled in demo mode'],403);
try{
    $s=new DeliveryService();$op=(string)($_POST['op']??'');
    $decimal=function($value):int{if(!preg_match('/^\d{1,6}(?:\.\d{1,2})?$/',(string)$value))throw new DomainException('Rates must be positive amounts with up to two decimals');return (int)round((float)$value*100);};
    if($op==='config'){
        $old=dl_config();$c=$old;unset($c['revision']);
        $c['enabled']=!empty($_POST['enabled']);$mode=(string)($_POST['mode']??'zone');
        if(!in_array($mode,['zone','kilometre','package'],true))throw new DomainException('Choose a pricing mode');
        $c['modes']=[$mode];$c['default_mode']=$mode;
        foreach(['base','minimum','per_km','per_package'] as $k)$c[$k.'_cents']=$decimal($_POST[$k]??'0');
        $c['currency']=strtoupper(trim((string)($_POST['currency']??'')));$c['country']=strtoupper(trim((string)($_POST['country']??'')));
        if(!preg_match('/^[A-Z]{3}$/',$c['currency'])||!preg_match('/^[A-Z]{2}$/',$c['country']))throw new DomainException('Use a three-letter currency and two-letter country code');
        foreach(['max_km'=>[1,5000],'rider_pct'=>[0,100]] as $k=>[$min,$max]){if(!is_numeric($_POST[$k]??null)||(float)$_POST[$k]<$min||(float)$_POST[$k]>$max)throw new DomainException('Check delivery distance and rider percentage');$c[$k]=(float)$_POST[$k];}
        $c['origin_place_id']=trim((string)($_POST['origin_place_id']??''));
        $origins=json_decode((string)($_POST['origins']??'{}'),true);
        if(!is_array($origins))throw new DomainException('Agency origins must be a JSON object');
        foreach($origins as $agency=>$place)if(!ctype_digit((string)$agency)||!is_string($place)||!preg_match('/^[A-Za-z0-9_-]{5,255}$/',$place))throw new DomainException('Agency origins must map agency IDs to Google place IDs');
        $c['origins']=$origins;
        foreach(['browser_key','server_key'] as $key){$input=trim((string)($_POST[$key]??''));if($input!==''){if(!preg_match('/^[A-Za-z0-9_-]{10,255}$/',$input))throw new DomainException('Check the Google Maps keys');$c[$key]=$input;}}
        $ready=array_values(array_unique(array_filter(array_map('intval',explode(',',(string)($_POST['ready_statuses']??'6,32'))))));
        if(!$ready||array_intersect($ready,[7,8,12,15,16,21]))throw new DomainException('Ready statuses cannot include on-route, handed-over, failed or cancelled states');
        foreach($ready as $status)if(!sl_row('SELECT id FROM cdb_styles WHERE id=:i',[':i'=>$status]))throw new DomainException('A ready status ID does not exist');
        $c['ready_statuses']=$ready;
        if($c['enabled']){
            if(!$c['origin_place_id']||!($c['browser_key']||getenv('CDP_GOOGLE_MAPS_BROWSER_KEY'))||!($c['server_key']||getenv('CDP_GOOGLE_MAPS_SERVER_KEY')))throw new DomainException('Set both Maps keys and the origin place before enabling delivery');
            if(!$s->zones())throw new DomainException('Create a delivery zone before enabling requests');
            if($mode==='kilometre'&&!$c['per_km_cents'])throw new DomainException('Set a kilometre rate');
            if($mode==='package'&&!$c['per_package_cents'])throw new DomainException('Set a package rate');
        }
        $s->sql('UPDATE cdb_delivery_config SET config_json=?,revision=revision+1,updated_by=?,updated_at=NOW() WHERE id=1',[json_encode($c),(int)$_SESSION['userid']]);
    }elseif($op==='zone'){
        $id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));$match=trim((string)($_POST['city_match']??''));$fee=$decimal($_POST['fee']??'0');$priority=filter_var($_POST['priority']??100,FILTER_VALIDATE_INT);
        if(!$name||mb_strlen($name)>100||mb_strlen($match)>500||$priority===false||$priority<0||$priority>9999)throw new DomainException('Check zone name, localities and priority');
        $polyText=trim((string)($_POST['polygon']??''));$polygon=$polyText!==''?json_decode($polyText,true):null;
        if($polyText!==''&&(!is_array($polygon)||count($polygon)<3||count($polygon)>100))throw new DomainException('Use a polygon with 3 to 100 [latitude, longitude] points');
        foreach($polygon??[] as $point)if(!is_array($point)||count($point)!==2||!is_numeric($point[0])||!is_numeric($point[1])||abs((float)$point[0])>90||abs((float)$point[1])>180)throw new DomainException('Invalid polygon point');
        $lat=trim((string)($_POST['lat']??''));$lng=trim((string)($_POST['lng']??''));$radius=trim((string)($_POST['radius_km']??''));
        if($radius!==''&&(!is_numeric($radius)||(float)$radius<=0||(float)$radius>5000||!is_numeric($lat)||!is_numeric($lng)||abs((float)$lat)>90||abs((float)$lng)>180))throw new DomainException('Check the zone centre and radius');
        if(!$polygon&&$radius===''&&$match==='')throw new DomainException('Provide a polygon, radius or exact Google localities');
        $agency=max(0,(int)($_POST['agency_id']??0));$driver=(int)($_POST['driver_id']??0);
        if($driver&&!$s->rider($driver,$agency))throw new DomainException('Choose an active rider in the zone agency');
        $s->db->beginTransaction();
        try{
            if($id){if(!$s->sql('SELECT id FROM cdb_delivery_zones WHERE id=? FOR UPDATE',[$id])->fetchColumn())throw new DomainException('Zone not found');$s->sql('UPDATE cdb_delivery_zones SET name=?,city_match=?,fee=?,rider_id=?,active=?,updated_at=NOW() WHERE id=?',[$name,$match,$fee/100,$driver?:null,!empty($_POST['active'])?1:0,$id]);}
            else{$s->sql('INSERT INTO cdb_delivery_zones(name,city_match,fee,rider_id,active,created_by,created_at) VALUES (?,?,?,?,?,?,NOW())',[$name,$match,$fee/100,$driver?:null,!empty($_POST['active'])?1:0,(int)$_SESSION['userid']]);$id=(int)$s->db->lastInsertId();}
            $s->sql('INSERT INTO cdb_delivery_zone_rules(zone_id,priority,polygon_json,centre_lat,centre_lng,radius_km,agency_id) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE priority=VALUES(priority),polygon_json=VALUES(polygon_json),centre_lat=VALUES(centre_lat),centre_lng=VALUES(centre_lng),radius_km=VALUES(radius_km),agency_id=VALUES(agency_id)',[$id,$priority,$polygon?json_encode($polygon):null,$radius!==''?$lat:null,$radius!==''?$lng:null,$radius!==''?$radius:null,$agency]);
            $s->db->commit();
        }catch(Throwable $e){if($s->db->inTransaction())$s->db->rollBack();throw $e;}
    }else throw new DomainException('Unknown operation');
    cdp_activityLog(['module'=>'last_mile','verb'=>'update','action'=>'delivery.settings','summary'=>'Updated delivery '.$op,'outcome'=>'ok']);
    sl_json(['success'=>true,'message'=>'Delivery settings saved','reload'=>true]);
}catch(DomainException $e){sl_json(['success'=>false,'message'=>$e->getMessage()],422);}
catch(Throwable $e){error_log('delivery settings: '.$e->getMessage());sl_json(['success'=>false,'message'=>'Could not save delivery settings'],500);}
