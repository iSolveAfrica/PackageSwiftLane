<?php
/** PATH_INFO adapter keeps existing PHP screens and AJAX inside disjoint PWA scopes.
 * No controller authorization is replaced. Do not allow arbitrary includes/paths. */
if(!defined('SL_PWA_ROLE')||!in_array(SL_PWA_ROLE,['customer','rider'],true)){http_response_code(404);exit;}
$root=dirname(__DIR__);
$script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??''));
$marker='/apps/'.SL_PWA_ROLE.'/app.php';$pos=strpos($script,$marker);
if($pos===false){http_response_code(404);exit;}
define('SL_PWA_ROOT_URL',substr($script,0,$pos).'/');
$scope=SL_PWA_ROOT_URL.'apps/'.SL_PWA_ROLE.'/';
$path=ltrim((string)($_SERVER['PATH_INFO']??''),'/');
if($path===''){$path=SL_PWA_ROLE==='rider'?'driver_app.php':'index.php';}
if(!preg_match('#^[a-zA-Z0-9_.\-/]+$#',$path)||str_contains($path,'..')||str_contains($path,'//')){http_response_code(404);exit;}
// Capability probe lets phone browsers retain the working website if a host disables PATH_INFO.
if($path==='pwa-check.php'){
    header('Content-Type: application/json');header('Cache-Control: no-store');
    echo json_encode(['routing'=>true,'role'=>SL_PWA_ROLE]);exit;
}
// Relative public assets emitted by older templates resolve through the app base.
// Redirect resources to their original URLs so relative CSS/fonts retain their base.
if(preg_match('#^(?:assets|dataJs|driver_files|uploads|support_files)/#',$path)&&preg_match('/\.(?:css|js|svg|png|jpg|jpeg|webp|gif|ico|woff2?|ttf|mp3|mp4|pdf)$/i',$path)){
    $file=realpath($root.'/'.$path);
    if(!$file||!str_starts_with($file,$root.'/')||!is_file($file)){http_response_code(404);exit;}
    header('Location: '.SL_PWA_ROOT_URL.$path.(isset($_SERVER['QUERY_STRING'])&&$_SERVER['QUERY_STRING']!==''?'?'.$_SERVER['QUERY_STRING']:''),302);exit;
}
$controller=realpath($root.'/'.$path);
$publicPage=!str_contains($path,'/')&&str_ends_with($path,'.php')&&!preg_match('/^(?:loader|cron|install|config|dashboard_admin|dashboard_roles)/i',$path);
$ajax=str_starts_with($path,'ajax/')&&str_ends_with($path,'.php');
if((!$publicPage&&!$ajax)||!$controller||!is_file($controller)||!str_starts_with($controller,$root.'/')){http_response_code(404);exit;}
chdir($root);require_once $root.'/loader.php';require_once $root.'/helpers/pwa.php';
$user=new User();$core=new Core();
$authPages=['login.php','logout.php','auth-otp.php','forgot-password.php','forgot-reset.php','sign-up.php','terms.php'];
$actual=sl_mobileRole();
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
if($user->cdp_loginCheck()&&$actual!==SL_PWA_ROLE&&$path!=='logout.php'){
    $actual=$actual?:'staff';
    http_response_code(403);
    if($ajax){header('Content-Type: application/json');echo json_encode(['success'=>false,'message'=>'Open the app for your signed-in account, or switch accounts']);exit;}
    $other=$actual==='staff'?SL_PWA_ROOT_URL.'index.php':sl_pwa_scope($actual).'app.php/'.($actual==='rider'?'driver_app.php':'index.php');
    ?><!doctype html><html lang="en"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Switch Swift Lane app</title><link rel="stylesheet" href="<?= h(SL_PWA_ROOT_URL.'assets/swiftlane/pwa/entry.css') ?>"><main class="entry"><span class="eyebrow"><?= SL_PWA_ROLE==='rider'?'Rider app':'Customer app' ?></span><h1>Open the right app for this account</h1><p>This browser is signed in with a <?= h($actual) ?> account. Open that app, or sign out to switch accounts.</p><a class="primary" href="<?= h($other) ?>">Open <?= h($actual) ?> app</a><a class="secondary" href="<?= h($scope.'app.php/logout.php') ?>">Sign out and switch account</a></main></html><?php exit;
}
if(!$user->cdp_loginCheck()&&!in_array($path,$authPages,true)){
    if($ajax){header('Content-Type: application/json');http_response_code(401);echo json_encode(['success'=>false,'message'=>'Your session ended. Sign in to continue.']);exit;}
    $return=$path.(!empty($_SERVER['QUERY_STRING'])?'?'.$_SERVER['QUERY_STRING']:'');
    if($_SERVER['REQUEST_METHOD']==='GET'&&preg_match('#^[a-z0-9_./?=&%+,\[\]-]+$#i',$return))$_SESSION['pwa_return'][SL_PWA_ROLE]=['target'=>$return,'at'=>time()];
    header('Location: '.$scope.'app.php/login.php?next='.rawurlencode($return));exit;
}
if($user->cdp_loginCheck()&&!in_array($path,$authPages,true)&&isset($_SESSION['pwa_return'][SL_PWA_ROLE])){
    $pending=$_SESSION['pwa_return'][SL_PWA_ROLE];unset($_SESSION['pwa_return'][SL_PWA_ROLE]);
    if($path==='index.php'&&time()-(int)($pending['at']??0)<1800&&preg_match('#^[a-z0-9_-]+\.php(?:[?][a-z0-9_./?=&%+,\[\]-]*)?$#i',(string)($pending['target']??''))&&!str_starts_with($pending['target'],'index.php')){header('Location: '.$scope.'app.php/'.$pending['target']);exit;}
}
if(SL_PWA_ROLE==='rider'&&$path==='index.php'){$path='driver_app.php';$controller=$root.'/'.$path;}
// Preserve the effective controller name used by navigation, guards and legacy code.
$_SERVER['SCRIPT_NAME']=SL_PWA_ROOT_URL.$path;$_SERVER['PHP_SELF']=$_SERVER['SCRIPT_NAME'];
register_shutdown_function(function()use($scope){
    if(headers_sent())return;
    foreach(headers_list() as $header){
        if(stripos($header,'Location:')!==0)continue;
        $location=trim(substr($header,9));$u=parse_url($location);
        if(!$u||isset($u['host']))continue; // External payment/Maps redirects stay external.
        $p=$u['path']??'';
        if(str_starts_with($p,$scope))continue;
        if(str_starts_with($p,SL_PWA_ROOT_URL))$p=substr($p,strlen(SL_PWA_ROOT_URL));
        if(preg_match('#^[a-zA-Z0-9_-]+\.php$#',$p))header('Location: '.$scope.'app.php/'.$p.(isset($u['query'])?'?'.$u['query']:'').(isset($u['fragment'])?'#'.$u['fragment']:''));
    }
});
ob_start(function($body)use($scope){
    $json=false;foreach(headers_list() as $header)if(stripos($header,'Content-Type:')===0&&!str_contains(strtolower($header),'text/html'))$json=true;
    if(!$json&&stripos($body,'<head>')!==false){ $body=preg_replace('/<base\b[^>]*>/i','',$body,1); $body=preg_replace('/<head>/i','<head><base href="'.htmlspecialchars($scope.'app.php/',ENT_QUOTES).'">',$body,1); }
    return $body;
});
chdir(dirname($controller));require $controller;
