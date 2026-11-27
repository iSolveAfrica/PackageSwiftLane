<?php
require_once CDP_ROOT.'/helpers/delivery_service.php';
require_once CDP_ROOT.'/helpers/delivery_storage.php';
function dl_baseData(): array{dl_ensureStorage();return ['ready'=>sl_tableExists('cdb_delivery_requests'),'config'=>dl_config()];}
function dl_customerData(int $owner): array
{
    $d=dl_baseData();$d+=['request'=>null,'events'=>[],'parcels'=>[],'requests'=>[]];if(!$d['ready'])return $d;
    $s=new DeliveryService();$id=(int)($_GET['id']??0);
    if($id){try{$d['request']=$s->get($id,$owner,'customer');$d['events']=$s->sql('SELECT * FROM cdb_delivery_events WHERE request_id=? ORDER BY id DESC',[$id])->fetchAll(PDO::FETCH_OBJ);}catch(DomainException $e){$d['notFound']=true;}}
    foreach(['air'=>'cdb_add_order','sea'=>'cdb_customers_packages'] as $module=>$table){
        $rows=$s->sql("SELECT o.*,st.mod_style AS status_name FROM $table o LEFT JOIN cdb_styles st ON st.id=o.status_courier WHERE o.sender_id=?".($module==='air'?' AND o.is_deleted=0':'')." AND COALESCE(o.driver_id,0)=0 AND o.status_courier NOT IN (7,8,12,15,16,21) AND NOT EXISTS (SELECT 1 FROM cdb_delivery_claims c WHERE c.module=? AND c.order_id=o.order_id) ORDER BY o.order_id DESC LIMIT 500",[$owner,$module])->fetchAll(PDO::FETCH_OBJ);
        foreach($rows as $p)if(dl_ready($p,$d['config']))$d['parcels'][$module][]=$p;
    }
    $d['requests']=$s->sql("SELECT * FROM cdb_delivery_requests WHERE customer_id=? AND state<>'quoted' ORDER BY id DESC LIMIT 100",[$owner])->fetchAll(PDO::FETCH_OBJ);return $d;
}
function dl_dispatchData(): array
{
    $d=dl_baseData()+['requests'=>[],'request'=>null,'events'=>[],'riders'=>[]];if(!$d['ready'])return $d;
    $s=new DeliveryService();$agency=dl_agency();$filter=(string)($_GET['state']??'open');$where=$agency?' AND r.agency_id='.(int)$agency:'';
    if($filter==='open')$where.=" AND r.state IN ('pending','assigned','onroute','failed')";
    elseif(in_array($filter,['pending','assigned','onroute','failed','delivered','cancelled'],true))$where.=' AND r.state='.$s->db->quote($filter);
    else $filter='all';$d['filter']=$filter;
    $d['requests']=$s->sql("SELECT r.*,CONCAT(u.fname,' ',u.lname) AS rider_name FROM cdb_delivery_requests r LEFT JOIN cdb_users u ON u.id=r.driver_id WHERE r.state<>'quoted' $where ORDER BY r.id DESC LIMIT 200")->fetchAll(PDO::FETCH_OBJ);
    $id=(int)($_GET['id']??0);if($id){try{$d['request']=$s->get($id,(int)$_SESSION['userid'],'manager',$agency);$d['events']=$s->sql('SELECT * FROM cdb_delivery_events WHERE request_id=? ORDER BY id DESC',[$id])->fetchAll(PDO::FETCH_OBJ);}catch(DomainException $e){$d['notFound']=true;}}
    require_once CDP_ROOT.'/views/sl/data/riders.php';foreach(sl_rdRiders(true) as $r)if(!$agency||(int)$r->agency_id===$agency)$d['riders'][]=$r;
    $d['suggestions']=[];
    require_once CDP_ROOT.'/views/sl/data/riders.php';
    $riderWork=[];
    $activeJobs=$s->sql("SELECT r.driver_id,r.lat,r.lng FROM cdb_delivery_requests r WHERE r.driver_id>0 AND r.state IN ('assigned','onroute')".($agency?' AND r.agency_id='.(int)$agency:''))->fetchAll(PDO::FETCH_OBJ);
    foreach($activeJobs as $job) $riderWork[(int)$job->driver_id][]=[$job->lat,$job->lng];
    foreach($d['requests'] as $job) {
        if($job->state!=='pending' || $job->driver_id) continue;
        $choices=[];
        foreach($d['riders'] as $rider) {
            if(!$rider->active || in_array($rider->rider_status??'', ['offline','suspended','on_break','unavailable'],true)) continue;
            if($job->agency_id && (int)$rider->agency_id!==(int)$job->agency_id) continue;
            $stops=$riderWork[(int)$rider->id]??[];
            $nearest=null;
            foreach($stops as [$lat,$lng]) {
                $km=12742*asin(sqrt(min(1,sin(deg2rad($job->lat-$lat)/2)**2+cos(deg2rad($lat))*cos(deg2rad($job->lat))*sin(deg2rad($job->lng-$lng)/2)**2)));
                $nearest=$nearest===null?$km:min($nearest,$km);
            }
            $choices[]=['id'=>(int)$rider->id,'name'=>$rider->name,'stops'=>count($stops),'nearby'=>$nearest===null?null:round($nearest,1),'score'=>count($stops)*3+($nearest??($job->distance_m/1000))];
        }
        usort($choices,fn($a,$b)=>[$a['score'],$a['id']]<=>[$b['score'],$b['id']]);
        $d['suggestions'][(int)$job->id]=$choices;
    }
    return $d;
}
function dl_settingsData(): array
{
    $d=dl_baseData()+['zones'=>[],'riders'=>[]];if(!$d['ready'])return $d;
    require_once CDP_ROOT.'/views/sl/data/riders.php';$d['riders']=sl_rdRiders(true);
    $s=new DeliveryService();$d['zones']=$s->sql('SELECT z.*,COALESCE(r.priority,100) AS priority,r.polygon_json,r.centre_lat,r.centre_lng,r.radius_km,COALESCE(r.agency_id,0) AS agency_id FROM cdb_delivery_zones z LEFT JOIN cdb_delivery_zone_rules r ON r.zone_id=z.id ORDER BY z.active DESC,z.name')->fetchAll(PDO::FETCH_OBJ);return $d;
}
function dl_jobData(int $rider): array
{
    $d=dl_baseData()+['request'=>null,'events'=>[]];if(!$d['ready'])return $d;
    $s=new DeliveryService();try{$d['request']=$s->get((int)($_GET['id']??0),$rider,'rider');$d['events']=$s->sql('SELECT * FROM cdb_delivery_events WHERE request_id=? ORDER BY id DESC',[$d['request']->id])->fetchAll(PDO::FETCH_OBJ);}catch(DomainException $e){$d['notFound']=true;}
    return $d;
}

function dl_fleetSnapshot(): array
{
 $agency=dl_agency();
 $scope=$agency?' AND r.agency_id=:agency':'';
 $bind=$agency?[':agency'=>$agency]:[];
 $position=sl_tableExists('cdb_rider_positions');
 $join=$position?"LEFT JOIN cdb_rider_positions p ON p.driver_id=r.driver_id AND p.request_id=r.id AND r.state='onroute' AND p.recorded_at>=UTC_TIMESTAMP()-INTERVAL 2 MINUTE":'';
 $coords=$position?'p.latitude,p.longitude,p.recorded_at':'NULL AS latitude,NULL AS longitude,NULL AS recorded_at';
 $stops=sl_rows("SELECT r.id,r.driver_id,r.address,r.lat,r.lng,r.state,$coords FROM cdb_delivery_requests r $join WHERE r.state IN ('assigned','onroute') $scope ORDER BY r.id DESC LIMIT 200",$bind);
 $totals=sl_row("SELECT COUNT(*) AS total,SUM(r.state IN ('assigned','onroute')) AS active,SUM(r.state='delivered') AS completed,SUM(r.state='failed') AS failed FROM cdb_delivery_requests r WHERE r.state<>'quoted' $scope",$bind);
 return ['stops'=>$stops,'totals'=>$totals,'asOf'=>gmdate('c')];
}
