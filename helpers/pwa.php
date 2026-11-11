<?php
/** Separate installed app identities/scopes, sharing the website's authorization. */
require_once __DIR__.'/sl_mobile.php';
function sl_pwa_root(): string
{
    if(defined('SL_PWA_ROOT_URL'))return SL_PWA_ROOT_URL;
    $dir=str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/index.php')));
    return rtrim($dir,'/').'/';
}
function sl_pwa_role(): string
{
    return defined('SL_PWA_ROLE')?SL_PWA_ROLE:sl_mobileRole();
}
function sl_pwa_scope(?string $role=null): string{return sl_pwa_root().'apps/'.($role??sl_pwa_role()).'/';}
function sl_pwa_head(): void
{
    $role=sl_pwa_role();if(!in_array($role,['customer','rider'],true))return;
    $root=sl_pwa_root();$scope=sl_pwa_scope($role);$inside=defined('SL_PWA_ROLE');
    $name=$role==='rider'?'Swift Lane Rider':'Swift Lane';$theme=$role==='rider'?'#14252b':'#123d35';
    if($inside)echo '<base href="'.h($scope.'app.php/').'">' . "\n";
    echo '<meta name="swiftlane-pwa" content="'.h($role).'" data-root="'.h($root).'" data-scope="'.h($scope).'" data-context="'.($inside?'app':'web').'">';
    echo '<link rel="manifest" href="'.h($scope.'manifest.php').'">';
    echo '<meta name="theme-color" content="'.$theme.'"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="default"><meta name="apple-mobile-web-app-title" content="'.h($name).'">';
    echo '<link rel="apple-touch-icon" href="'.h($root.'assets/swiftlane/pwa/'.$role.'-192.png').'">';
    echo '<link rel="stylesheet" href="'.h(cdp_asset($root.'assets/swiftlane/pwa/mobile-app.css')).'">';
    echo '<script defer src="'.h(cdp_asset($root.'assets/swiftlane/pwa/app.js')).'"></script>';
}
