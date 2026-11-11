<?php
require_once __DIR__.'/sl_view.php';
require_once __DIR__.'/delivery_pricing.php';
require_once __DIR__.'/delivery_google.php';
require_once __DIR__.'/handover.php';
function dl_config(): array
{
    $defaults=['enabled'=>false,'modes'=>['zone'],'default_mode'=>'zone','currency'=>'GHS','country'=>'GH','base_cents'=>0,'minimum_cents'=>0,'per_km_cents'=>0,'per_package_cents'=>0,'rider_pct'=>70,'max_km'=>100,'origin_place_id'=>'','browser_key'=>'','server_key'=>'','origins'=>[],'ready_statuses'=>[6,32]];
    if(!sl_tableExists('cdb_delivery_config'))return $defaults+['revision'=>0];
    $r=sl_row('SELECT * FROM cdb_delivery_config WHERE id=1');
    return array_replace($defaults,json_decode($r->config_json??'{}',true)?:[],['revision'=>(int)($r->revision??0)]);
}
function dl_ready($parcel,array $config): bool
{
    $status=(int)$parcel->status_courier;
    if(in_array($status,[7,8,12,15,16,21],true)||!empty($parcel->is_pickup))return false;
    return in_array($status,array_map('intval',$config['ready_statuses']),true)||preg_match('/\bready\s+(?:for|at)\s+(?:pick\s*-?\s*up|collection|customs|delivery)\b/i',str_replace('_',' ',$parcel->status_name??''));
}
function dl_url(string $path='delivery_request.php'): string
{
    global $core;
    $base=defined('CDP_APP_URL')?CDP_APP_URL:($core->site_url??'');return rtrim($base,'/').'/'.$path;
}
function dl_invite(int $status,string $label='',bool $html=false): string
{
    $c=dl_config();$p=(object)['status_courier'=>$status,'status_name'=>$label];
    if(!$c['enabled']||!dl_ready($p,$c))return '';
    $url=dl_url();$text='Prefer delivery? Choose your location, get a delivery price and approve it before we dispatch: ';
    return $html?'<p>'.h($text).'<a href="'.h($url).'">Request delivery</a></p>':$text.$url;
}
function dl_manager($user): bool
{
    return !sl_userIs('client')&&!sl_userIs('driver')&&$user->cdp_hasPermission(['manage_freight','dispatch_riders','assign_drivers','manage_rider_fees']);
}
function dl_agency(): int
{
    $c=function_exists('cdp_getAgencyContext')?cdp_getAgencyContext():[];
    return !empty($c['is_restricted'])?(int)($c['agency_id']??-1):0;
}
function dl_money(int $cents,string $currency): string{return $currency.' '.number_format($cents/100,2);}
class DeliveryService
{
    public PDO $db; private array $config; private DeliveryGoogle $google;
    public function __construct(?PDO $db=null,?array $config=null,?DeliveryGoogle $google=null)
    {
        $this->db=$db??(new Conexion())->dbh;$this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $this->config=$config??dl_config();$this->google=$google??new DeliveryGoogle(getenv('CDP_GOOGLE_MAPS_SERVER_KEY')?:$this->config['server_key']);
    }
    public function sql(string $sql,array $params=[]): PDOStatement{$q=$this->db->prepare($sql);$q->execute($params);return $q;}
    public function parcels(string $module,array $ids,int $owner,bool $lock=false): array
    {
        if(!in_array($module,['air','sea'],true))throw new DomainException('Invalid parcel type');
        $ids=array_values(array_unique(array_map('intval',$ids)));sort($ids);
        if(!$ids||count($ids)>50||min($ids)<1)throw new DomainException('Select 1 to 50 parcels');
        $table=$module==='air'?'cdb_add_order':'cdb_customers_packages';
        $rows=$this->sql("SELECT o.*,s.mod_style AS status_name FROM $table o LEFT JOIN cdb_styles s ON s.id=o.status_courier WHERE o.sender_id=? AND o.order_id IN (".implode(',',array_fill(0,count($ids),'?')).')'.($module==='air'?' AND o.is_deleted=0':'').' ORDER BY o.order_id'.($lock?' FOR UPDATE':''),array_merge([$owner],$ids))->fetchAll(PDO::FETCH_OBJ);
        if(count($rows)!==count($ids))throw new DomainException('One or more parcels are unavailable');
        if(count(array_unique(array_map(fn($r)=>(int)($r->agency??0),$rows)))>1)throw new DomainException('Request deliveries from each agency separately');
        return $rows;
    }
    public function eligible(array $rows): void{foreach($rows as $p)if(!dl_ready($p,$this->config))throw new DomainException('A selected parcel is no longer ready for delivery');}
    public function zones(): array{return $this->sql('SELECT z.*,COALESCE(r.priority,100) AS priority,r.polygon_json,r.centre_lat,r.centre_lng,r.radius_km,COALESCE(r.agency_id,0) AS agency_id FROM cdb_delivery_zones z LEFT JOIN cdb_delivery_zone_rules r ON r.zone_id=z.id WHERE z.active=1 ORDER BY z.id')->fetchAll(PDO::FETCH_OBJ);}
    public function quote(int $owner,string $module,array $ids,string $place,string $mode,string $name,string $phone,string $instructions): int
    {
        if(!$this->config['enabled'])throw new DomainException('Delivery requests are not available yet');
        if(!in_array($mode,$this->config['modes'],true))throw new DomainException('This pricing model is not available');
        $name=trim($name);$phone=trim($phone);
        if(mb_strlen($name)<2||mb_strlen($name)>150||!preg_match('/^\+?[0-9 ()-]{7,30}$/',$phone)||mb_strlen($instructions)>500)throw new DomainException('Enter a contact name, valid phone number and instructions under 500 characters');
        $rows=$this->parcels($module,$ids,$owner);$this->eligible($rows);$agency=(int)($rows[0]->agency??0);
        foreach($rows as $p)if($this->sql('SELECT request_id FROM cdb_delivery_claims WHERE module=? AND order_id=?',[$module,$p->order_id])->fetchColumn())throw new DomainException('A selected parcel already has an active delivery request');
        $originId=$this->config['origins'][(string)$agency]??$this->config['origin_place_id'];
        if(!$originId)throw new DomainException('The delivery origin is not configured. Contact the office.');
        $origin=$this->google->place($originId,$this->config['country']);$destination=$this->google->place($place,$this->config['country']);
        $route=$this->google->route($origin['place_id'],$destination['place_id']);$metres=$route['distance_m'];
        if($metres>$this->config['max_km']*1000)throw new DomainException('This location is outside our delivery distance');
        $zone=dl_zone($this->zones(),$destination,$agency);
        // All modes stay within a configured service zone, not merely within a country.
        if(!$zone)throw new DomainException('This location is outside our delivery zones');
        $breakdown=dl_price($mode,$this->config,$metres,count($rows),(int)round((float)$zone->fee*100));
        $breakdown['zone_name']=$zone->name;$breakdown['duration']=$route['duration'];
        $total=$breakdown['total_cents'];$rider=(int)round($total*(float)$this->config['rider_pct']/100);
        $this->sql('INSERT INTO cdb_delivery_requests (customer_id,agency_id,module,parcel_json,address,place_id,lat,lng,origin_json,distance_m,zone_id,pricing_mode,currency,amount_cents,rider_cents,breakdown_json,config_revision,contact_name,contact_phone,instructions,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 15 MINUTE))',[$owner,$agency,$module,json_encode(array_map(fn($p)=>(int)$p->order_id,$rows)),$destination['address'],$destination['place_id'],$destination['lat'],$destination['lng'],json_encode($origin),$metres,$zone->id,$mode,$this->config['currency'],$total,$rider,json_encode($breakdown),$this->config['revision'],$name,$phone,trim($instructions)]);
        return (int)$this->db->lastInsertId();
    }
    public function event(int $id,int $actor,string $state,string $note=''): void
    {
        $this->sql('INSERT INTO cdb_delivery_events(request_id,actor_id,state,note) VALUES (?,?,?,?)',[$id,$actor,$state,mb_substr($note,0,700)]);
    }
    public function message(int $id,string $key,int $recipient,string $text,string $page='delivery_request.php'): void
    {
        $queued=$this->sql('INSERT IGNORE INTO cdb_delivery_outbox(event_key,recipient_id,subject,message) VALUES (?,?,?,?)',[$id.':'.$key,$recipient,'Delivery #'.$id,$text.' '.dl_url($page.'?id='.$id)]);
        if($queued->rowCount()){
            $this->sql('INSERT INTO cdb_notifications(user_id,order_id,notification_description,shipping_type,notification_date) VALUES (?,?,?,99,NOW())',[(int)($_SESSION['userid']??0),$id,mb_substr($text,0,350)]);
            $notification=(int)$this->db->lastInsertId();$this->sql('INSERT INTO cdb_notifications_users(user_id,notification_id) VALUES (?,?)',[$recipient,$notification]);
        }
    }
    public function rider(int $id,int $agency): bool
    {
        require_once CDP_ROOT.'/helpers/sl_people.php';$roles=sl_riderRoleIds();
        $r=$this->sql('SELECT * FROM cdb_users WHERE id=? AND active=1',[$id])->fetch(PDO::FETCH_OBJ);
        return $r&&in_array((int)$r->userlevel,$roles,true)&&(!$agency||(int)($r->agency_id??0)===$agency)&&(!isset($r->rider_status)||!in_array($r->rider_status,['suspended','offline'],true));
    }
    public function approve(int $id,int $owner): object
    {
        $this->db->beginTransaction();
        try{
            $r=$this->sql('SELECT * FROM cdb_delivery_requests WHERE id=? AND customer_id=? FOR UPDATE',[$id,$owner])->fetch(PDO::FETCH_OBJ);
            if(!$r)throw new DomainException('Delivery quote not found');
            if($r->approved_at){$this->db->commit();return $r;}
            if($r->state!=='quoted'||strtotime($r->expires_at)<=time())throw new DomainException('This quote has expired. Get a new price.');
            if(!dl_config()['enabled'])throw new DomainException('Delivery requests are currently paused');
            $rows=$this->parcels($r->module,json_decode($r->parcel_json,true),$owner,true);$this->eligible($rows);
            foreach($rows as $p){
                if((int)($p->agency??0)!==(int)$r->agency_id||!empty($p->driver_id))throw new DomainException('A parcel has changed or already has a rider. Contact dispatch.');
                $this->sql('INSERT INTO cdb_delivery_claims(module,order_id,request_id) VALUES (?,?,?)',[$r->module,$p->order_id,$id]);
                $this->sql('INSERT INTO cdb_delivery_items(request_id,module,order_id,tracking) VALUES (?,?,?,?)',[$id,$r->module,$p->order_id,$p->order_prefix.$p->order_no]);
            }
            $zone=$this->sql('SELECT rider_id,active FROM cdb_delivery_zones WHERE id=? FOR UPDATE',[$r->zone_id])->fetch(PDO::FETCH_OBJ);
            $driver=$zone&&$zone->active&&$zone->rider_id&&$this->rider((int)$zone->rider_id,(int)$r->agency_id)?(int)$zone->rider_id:null;
            $state=$driver?'assigned':'pending';
            $this->sql('UPDATE cdb_delivery_requests SET state=?,driver_id=?,approved_at=NOW(),assigned_at=?,updated_at=NOW() WHERE id=?',[$state,$driver,$driver?date('Y-m-d H:i:s'):null,$id]);
            $this->event($id,$owner,$state,'Customer approved '.dl_money((int)$r->amount_cents,$r->currency));
            $this->message($id,'approved',$owner,'Delivery request approved: '.dl_money((int)$r->amount_cents,$r->currency).'. '.($driver?'A rider has been assigned.':'Waiting for dispatch to assign a rider.'));
            if($driver)$this->message($id,'assigned:'.$driver,$driver,'New delivery assigned to you. Open Rider app → Customer deliveries.','delivery_job.php');
            $this->db->commit();return $this->sql('SELECT * FROM cdb_delivery_requests WHERE id=?',[$id])->fetch(PDO::FETCH_OBJ);
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();if($e instanceof PDOException&&$e->getCode()==='23000')throw new DomainException('A parcel already has an active delivery. Refresh your requests.');throw $e;}
    }
    public function items(object $r,bool $lock=false): array{return $this->parcels($r->module,json_decode($r->parcel_json,true),(int)$r->customer_id,$lock);}
    public function cleared(object $r,array $items): void
    {
        require_once CDP_ROOT.'/helpers/finance_switch.php';
        if($r->module==='air')foreach($items as $p)if(!cdp_fsIsCleared($p->fs_cleared_for_delivery??0))throw new DomainException('Accounts must clear all shipping charges before this delivery can leave');
    }

    public function get(int $id,int $actor,string $role,int $agency=0,bool $lock=false): object
    {
        $r=$this->sql('SELECT * FROM cdb_delivery_requests WHERE id=?'.($lock?' FOR UPDATE':''),[$id])->fetch(PDO::FETCH_OBJ);
        if(!$r||($role==='customer'&&(int)$r->customer_id!==$actor)||($role==='rider'&&(int)$r->driver_id!==$actor)||($role==='manager'&&$agency&&(int)$r->agency_id!==$agency)||!in_array($role,['customer','rider','manager'],true))throw new DomainException('Delivery request not found');
        return $r;
    }
    public function transition(int $id,int $actor,string $role,int $agency,string $op,array $input=[]): void
    {
        $r=$this->get($id,$actor,$role,$agency);
        if($op==='deliver'){
            if($role!=='rider'||$r->state!=='onroute')throw new DomainException('Only the assigned rider can complete an on-route delivery');
            require_once CDP_ROOT.'/helpers/pickup_code.php';
            $codes=array_values(array_unique(array_filter(array_map('trim',explode(',',(string)($input['code']??''))))));
            if(!$codes||count($codes)>50)throw new DomainException('Enter the customer pickup codes, separated by commas');
            foreach($codes as $code){
                if(!preg_match('/^[0-9]{6}$/',$code))throw new DomainException('Each pickup code must have six digits');
                $verified=cdp_pickupCodeVerify($r->module,json_decode($r->parcel_json,true),$code,$actor);
                if(empty($verified['success']))throw new DomainException($verified['message']);
            }
        }
        $this->db->beginTransaction();
        try{
            $r=$this->get($id,$actor,$role,$agency,true);$from=$r->state;$state=$from;$note=trim((string)($input['note']??''));
            if($op==='cancel'){
                if(!in_array($role,['customer','manager'],true)||(!in_array($from,['quoted','pending','assigned','failed'],true)&&!($role==='manager'&&$from==='onroute'&&mb_strlen($note)>=3)))throw new DomainException('This delivery can no longer be cancelled here');
                $state='cancelled';$items=$this->items($r,true);
                $table=$r->module==='air'?'cdb_add_order':'cdb_customers_packages';
                foreach($items as $p)if(!in_array((int)$p->status_courier,[8,15,21],true))$this->sql("UPDATE $table SET driver_id=NULL,status_courier=CASE WHEN status_courier=7 THEN 6 ELSE status_courier END WHERE order_id=? AND driver_id=?",[$p->order_id,$r->driver_id]);
                $this->sql('DELETE FROM cdb_delivery_claims WHERE request_id=?',[$id]);
            }elseif($op==='assign'){
                if($role!=='manager'||!in_array($from,['pending','assigned','failed'],true))throw new DomainException('Only dispatch can assign an open request');
                $driver=(int)($input['driver_id']??0);
                if(!$this->rider($driver,(int)$r->agency_id))throw new DomainException('Choose an active rider in this agency');
                $this->eligible($this->items($r,true));
                $state='assigned';$this->sql('UPDATE cdb_delivery_requests SET driver_id=?,assigned_at=NOW() WHERE id=?',[$driver,$id]);
                $this->message($id,'assignment:'.bin2hex(random_bytes(6)),$driver,'Delivery assigned. Open Rider app → Customer deliveries.','delivery_job.php');
            }elseif($op==='start'){
                if($role!=='rider'||$from!=='assigned')throw new DomainException('Only the assigned rider can start this delivery');
                $items=$this->items($r,true);$this->eligible($items);$this->cleared($r,$items);$state='onroute';$this->parcelStatus($r,$items,7,$actor,'Out for delivery');
            }elseif($op==='fail'){
                if($role!=='rider'||$from!=='onroute'||mb_strlen($note)<3)throw new DomainException('Give a reason for this failed delivery attempt');
                $state='failed';$items=$this->items($r,true);$this->parcelStatus($r,$items,6,$actor,'Delivery attempt failed: '.$note);
            }elseif($op==='deliver'){
                if($role!=='rider'||$from!=='onroute')throw new DomainException('This delivery is not on route');
                $receiver=trim((string)($input['receiver']??''));$proof=(string)($input['proof']??'');
                if(mb_strlen($receiver)<2||mb_strlen($receiver)>150||!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/',$proof))throw new DomainException('Add the receiver name and a delivery photo');
                $items=$this->items($r,true);$this->cleared($r,$items);
                foreach($items as $p)if((int)$p->status_courier!==7)throw new DomainException('A parcel status changed. Ask dispatch to review this delivery.');
                $gate=cdp_pickupCodeGate($r->module,json_decode($r->parcel_json,true),8);if(!$gate['ok'])throw new DomainException($gate['message']);
                $this->parcelStatus($r,$items,8,$actor,'Delivered to '.$receiver);
                $table=$r->module==='air'?'cdb_add_order':'cdb_customers_packages';
                foreach($items as $parcel){
                    $this->sql("UPDATE $table SET person_receives=? WHERE order_id=?",[$receiver,$parcel->order_id]);
                    if($r->module==='air'&&sl_columnExists($table,'pod_receiver')&&sl_columnExists($table,'pod_at'))$this->sql("UPDATE $table SET pod_receiver=?,pod_at=NOW() WHERE order_id=?",[$receiver,$parcel->order_id]);
                }
                cdp_pickupCodeConsume($r->module,json_decode($r->parcel_json,true),$actor);
                $this->sql('UPDATE cdb_delivery_requests SET receiver_name=?,proof_photo=?,fee_collected=?,completed_at=NOW() WHERE id=?',[$receiver,$proof,!empty($input['fee_collected'])?1:0,$id]);
                $this->sql('DELETE FROM cdb_delivery_claims WHERE request_id=?',[$id]);$state='delivered';
            }else throw new DomainException('Unknown delivery operation');
            $this->sql('UPDATE cdb_delivery_requests SET state=?,updated_at=NOW() WHERE id=?',[$state,$id]);
            $this->event($id,$actor,$state,$note);
            if($state!=='cancelled'||$r->approved_at)$this->message($id,'event:'.bin2hex(random_bytes(6)),(int)$r->customer_id,'Delivery #'.$id.': '.$state.($note?' — '.$note:''));
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    private function parcelStatus(object $r,array $items,int $status,int $actor,string $note): void
    {
        $table=$r->module==='air'?'cdb_add_order':'cdb_customers_packages';
        foreach($items as $p){
            if(in_array((int)$p->status_courier,[8,15,21,12,16],true))throw new DomainException('A parcel is no longer available for this delivery');
            $this->sql("UPDATE $table SET status_courier=?,driver_id=? WHERE order_id=?",[$status,$r->driver_id,$p->order_id]);
            $this->sql('INSERT INTO cdb_courier_track(order_id,order_track,comments,t_date,status_courier,user_id) VALUES (?,?,?,NOW(),?,?)',[$p->order_id,$p->order_prefix.$p->order_no,mb_substr($note,0,255),$status,$actor]);
            if($status===8){
                $detail=$r->module==='air'?'cdb_consolidate_detail':'cdb_consolidate_packages_detail';$head=$r->module==='air'?'cdb_consolidate':'cdb_consolidate_packages';
                if(sl_tableExists($detail)&&sl_tableExists($head))$this->sql("UPDATE $head SET status_courier=8 WHERE consolidate_id IN (SELECT consolidate_id FROM $detail WHERE order_id=?) AND NOT EXISTS (SELECT 1 FROM $detail d JOIN $table o ON o.order_id=d.order_id WHERE d.consolidate_id=$head.consolidate_id AND o.status_courier NOT IN (8,15))",[$p->order_id]);
            }
        }
    }
}

/** Queue the invitation where a status-change endpoint has no existing message. */
function dl_queueReady(string $module,int $orderId,int $status,string $label): void
{
    $message=dl_invite($status,$label);if($message===''||!sl_tableExists('cdb_delivery_outbox'))return;
    $table=$module==='sea'?'cdb_customers_packages':'cdb_add_order';
    $row=sl_row("SELECT sender_id,order_prefix,order_no FROM $table WHERE order_id=:id",[':id'=>$orderId]);
    if(!$row||!(int)$row->sender_id)return;
    $s=new DeliveryService();$s->sql('INSERT INTO cdb_delivery_outbox(event_key,recipient_id,subject,message) VALUES (?,?,?,?)',['ready:'.$module.':'.$orderId.':'.$status.':'.bin2hex(random_bytes(6)),(int)$row->sender_id,'Your parcel is ready',$row->order_prefix.$row->order_no.' · '.$label.'. '.$message]);
}

/** A scoped link from a parcel record to its delivery history/proof. */
function dl_parcelLink(string $module,int $orderId): ?array
{
    global $user;
    if(!sl_tableExists('cdb_delivery_items'))return null;
    $role=sl_userIs('client')?'customer':(sl_userIs('driver')?'rider':(dl_manager($user)?'manager':''));
    if(!$role)return null;
    $where=$role==='customer'?' AND r.customer_id='.(int)$_SESSION['userid']:($role==='rider'?' AND r.driver_id='.(int)$_SESSION['userid']:(dl_agency()?' AND r.agency_id='.dl_agency():''));
    $r=sl_row('SELECT r.id,r.state FROM cdb_delivery_items i JOIN cdb_delivery_requests r ON r.id=i.request_id WHERE i.module=:m AND i.order_id=:o'.$where.' ORDER BY r.id DESC LIMIT 1',[':m'=>$module,':o'=>$orderId]);
    if(!$r)return null;
    return ['state'=>$r->state,'href'=>($role==='rider'?'delivery_job.php':($role==='customer'?'delivery_request.php':'delivery_dispatch.php')).'?id='.(int)$r->id];
}
