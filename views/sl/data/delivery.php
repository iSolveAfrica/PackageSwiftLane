<?php
require_once CDP_ROOT.'/helpers/delivery_service.php';
function dl_baseData(): array{return ['ready'=>sl_tableExists('cdb_delivery_requests'),'config'=>dl_config()];}
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
