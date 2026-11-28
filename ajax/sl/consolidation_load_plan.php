<?php
require_once __DIR__.'/../../loader.php';
require_once CDP_ROOT.'/helpers/ajax_guard.php';
require_once CDP_ROOT.'/views/sl/data/consolidations.php';
require_once CDP_ROOT.'/helpers/consolidation_load_plan.php';
require_once CDP_ROOT.'/helpers/activity_log.php';
$user=new User();$core=new Core();
require_login();
if($_SERVER['REQUEST_METHOD']!=='POST' || !cdp_csrf_validate_request())sl_json(['success'=>false,'message'=>'Invalid request'],403);
if(CDP_APP_MODE_DEMO || sl_userIs('client') || sl_userIs('driver'))sl_json(['success'=>false,'message'=>'Staff access required'],403);
$type=(string)($_POST['type']??'');
if(!in_array($type,['sea','air'],true))sl_json(['success'=>false,'message'=>'Invalid mode'],422);
$M=sl_conMode($type);
$user->cdp_getUserPermissions();
if(!$user->cdp_hasPermission($M['p']['edit']))sl_json(['success'=>false,'message'=>'Permission required'],403);
if(!sl_tableExists('cdb_consolidation_load_plans'))sl_json(['success'=>false,'message'=>'Load plan storage is unavailable'],503);
$id=(int)($_POST['id']??0);
$version=filter_var($_POST['revision']??null,FILTER_VALIDATE_INT);
$positions=json_decode((string)($_POST['positions']??''),true);
if($version===false || $version<0 || !is_array($positions) || strlen((string)$_POST['positions'])>250000)sl_json(['success'=>false,'message'=>'Invalid load plan'],422);
$db=(new Conexion())->dbh;
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
try {
    $db->beginTransaction();
    $q=$db->prepare("SELECT * FROM {$M['head']} WHERE consolidate_id=? FOR UPDATE");
    $q->execute([$id]);$head=$q->fetch(PDO::FETCH_OBJ);
    if(!$head || !sl_opsInScope($head))throw new DomainException('Consolidation unavailable.');
    if(cdp_isHandedOver($head->status_courier) || (int)$head->status_courier===21)throw new DomainException('This consolidation is closed.');
    $q=$db->prepare("SELECT order_id FROM {$M['detail']} WHERE consolidate_id=?");
    $q->execute([$id]);$members=$q->fetchAll(PDO::FETCH_COLUMN);
    $positions=sl_loadPositions($positions,$members);
    $q=$db->prepare('SELECT revision FROM cdb_consolidation_load_plans WHERE mode=? AND consolidation_id=? FOR UPDATE');
    $q->execute([$type,$id]);$current=$q->fetchColumn();
    if((int)($current===false?0:$current)!==$version) {
        $db->rollBack();
        sl_json(['success'=>false,'message'=>'Another dispatcher changed this plan. Refresh before saving.'],409);
    }
    $q=$db->prepare('INSERT INTO cdb_consolidation_load_plans(mode,consolidation_id,revision,positions_json,updated_by,updated_at) VALUES(?,?,1,?,?,NOW()) ON DUPLICATE KEY UPDATE revision=revision+1,positions_json=VALUES(positions_json),updated_by=VALUES(updated_by),updated_at=NOW()');
    $q->execute([$type,$id,json_encode($positions),(int)$_SESSION['userid']]);
    $db->commit();
    cdp_activityLog(['module'=>$M['module'],'action'=>'load_plan.save','action_label'=>'Saved load plan','verb'=>'update','outcome'=>'ok','entity_type'=>'consolidation','entity_id'=>$id,'summary'=>'Updated '.$type.' consolidation load positions']);
    sl_json(['success'=>true,'message'=>'Load plan saved','revision'=>$version+1]);
} catch(DomainException $e) {
    if($db->inTransaction())$db->rollBack();
    sl_json(['success'=>false,'message'=>$e->getMessage()],422);
} catch(Throwable $e) {
    if($db->inTransaction())$db->rollBack();
    error_log('[load-plan] '.$e->getMessage());
    sl_json(['success'=>false,'message'=>'Could not save the load plan. Try again.'],500);
}
