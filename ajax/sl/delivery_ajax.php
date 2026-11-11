<?php
require_once __DIR__.'/../../loader.php';
require_once CDP_ROOT.'/helpers/ajax_guard.php';
require_once CDP_ROOT.'/helpers/delivery_service.php';
$user=new User();$core=new Core();require_login();
if($_SERVER['REQUEST_METHOD']!=='POST')sl_json(['success'=>false,'message'=>'POST required'],405);
if(CDP_APP_MODE_DEMO===true)sl_json(['success'=>false,'message'=>'Changes are disabled in demo mode'],403);
if(!sl_tableExists('cdb_delivery_requests'))sl_json(['success'=>false,'message'=>'Install the courier delivery migration first'],409);
$user->cdp_getUserPermissions();$actor=(int)$_SESSION['userid'];$role=sl_userIs('client')?'customer':(sl_userIs('driver')?'rider':(dl_manager($user)?'manager':''));
if(!$role)sl_json(['success'=>false,'message'=>'Not allowed'],403);
$op=(string)($_POST['op']??'');$proof='';
try{
    $s=new DeliveryService();$id=(int)($_POST['id']??0);
    if($op==='quote'){
        if($role!=='customer')sl_json(['success'=>false,'message'=>'Customer access required'],403);
        // Bound external lookups per session; repeated requests cannot flood Google billing.
        $hits=array_filter((array)($_SESSION['delivery_quote_hits']??[]),fn($v)=>$v>time()-300);
        if(count($hits)>=15)sl_json(['success'=>false,'message'=>'Please wait a few minutes before requesting another price'],429);
        $hits[]=time();$_SESSION['delivery_quote_hits']=$hits;
        $ids=is_array($_POST['parcels']??null)?$_POST['parcels']:[];
        $id=$s->quote($actor,(string)($_POST['module']??''),$ids,(string)($_POST['place_id']??''),(string)($_POST['mode']??''),(string)($_POST['contact_name']??''),(string)($_POST['contact_phone']??''),(string)($_POST['instructions']??''));
        sl_json(['success'=>true,'redirect'=>'delivery_request.php?id='.$id]);
    }
    if($op==='approve'){
        if($role!=='customer')sl_json(['success'=>false,'message'=>'Customer access required'],403);
        $s->approve($id,$actor);
    }else{
        $s->get($id,$actor,$role,dl_agency());
        $input=$_POST;
        if($op==='deliver'){
            if($role!=='rider')sl_json(['success'=>false,'message'=>'Rider access required'],403);
            $f=$_FILES['photo']??[];
            if(empty($f['tmp_name'])||($f['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name'])||$f['size']>8*1048576)throw new DomainException('Upload a delivery photo under 8 MB');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
            if(!$ext||!getimagesize($f['tmp_name']))throw new DomainException('Use a JPEG, PNG or WebP photo');
            $dir=getenv('CDP_DELIVERY_PROOF_DIR')?:dirname(CDP_ROOT).'/delivery-proof';
            if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Proof storage is not writable');
            $proof=bin2hex(random_bytes(16)).'.'.$ext;
            if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$proof))throw new RuntimeException('Could not save the delivery photo');
            chmod($dir.'/'.$proof,0600);$input['proof']=$proof;
        }
        $s->transition($id,$actor,$role,dl_agency(),$op,$input);
    }
    cdp_activityLog(['module'=>'last_mile','action'=>'delivery.'.$op,'action_label'=>'Delivery '.$op,'verb'=>'update','outcome'=>'ok','entity_type'=>'delivery_request','entity_id'=>$id,'summary'=>'Delivery #'.$id.' '.$op]);
    sl_json(['success'=>true,'message'=>'Delivery updated','redirect'=>($role==='rider'?'delivery_job.php':($role==='manager'?'delivery_dispatch.php':'delivery_request.php')).'?id='.$id]);
}catch(DomainException $e){if($proof)@unlink($dir.'/'.$proof);sl_json(['success'=>false,'message'=>$e->getMessage()],422);}
catch(Throwable $e){if($proof)@unlink($dir.'/'.$proof);error_log('delivery operation failed: '.$e->getMessage());sl_json(['success'=>false,'message'=>'The delivery could not be saved. Please retry or contact dispatch.'],500);}
