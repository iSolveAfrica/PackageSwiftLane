<?php
/** Separate installed app identities/scopes, sharing the website's authorization. */
require_once __DIR__.'/sl_mobile.php';
require_once __DIR__.'/sl_device.php';
function sl_pwa_root(): string
{
    if(defined('SL_PWA_ROOT_URL'))return SL_PWA_ROOT_URL;
    $dir=str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/index.php')));
    return rtrim($dir,'/').'/';
}
function sl_pwa_role(): string
{
    return defined('SL_PWA_ROLE')?SL_PWA_ROLE:sl_bodyRole(); // customer | rider | staff
}
function sl_pwa_scope(?string $role=null): string{return sl_pwa_root().'apps/'.($role??sl_pwa_role()).'/';}
/**
 * Device facts for every page, signed in or not: the server's reading goes on <html> before first paint,
 * the browser corrects what only it can know (an iPad that says it is a Mac, an installed app, a slow link)
 * and tells the server through the sl_dev cookie for the next page.
 */
function sl_device_head(): void
{
    static $done=false;if($done)return;$done=true;
    $d=sl_device();$root=sl_pwa_root();sl_deviceRequestHints();
    $seed=json_encode(['os'=>$d['os'],'browser'=>$d['browser'],'form'=>$d['form']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    echo '<script>(function(s){var r=document.documentElement,d=r.dataset;d.os=s.os;d.browser=s.browser;d.form=s.form;'
        .'if(s.os==="macos"&&navigator.maxTouchPoints>1){d.os="ios";d.form="tablet";}'
        .'var a=matchMedia("(display-mode: standalone)").matches||navigator.standalone===true;if(a)d.standalone="1";'
        .'var c=navigator.connection;if(c&&(c.saveData||/(^|-)2g$/.test(c.effectiveType||"")))d.net="slow";'
        .'var v=d.os+"."+d.form+"."+(a?1:0);if(document.cookie.indexOf("sl_dev="+v)<0)document.cookie="sl_dev="+v+";path=/;max-age=31536000;SameSite=Lax"+(location.protocol==="https:"?";Secure":"");'
        .'})('.$seed.');</script>';
    echo '<link rel="stylesheet" href="'.h(cdp_asset($root.'assets/swiftlane/pwa/device.css')).'">';
    echo '<script defer src="'.h(cdp_asset($root.'assets/swiftlane/pwa/device.js')).'"></script>';
}
function sl_pwa_head(): void
{
    sl_device_head();
    $role=sl_pwa_role();if(!in_array($role,['customer','rider','staff'],true))return;
    $root=sl_pwa_root();$scope=sl_pwa_scope($role);$inside=defined('SL_PWA_ROLE');
    $name=$role==='rider'?'Swift Lane Rider':($role==='staff'?'Swift Lane Staff':'Swift Lane');$theme='#F3F2EF'; // status bar matches the paper app bar on every role
    if($inside)echo '<base href="'.h($scope.'app.php/').'">' . "\n";
    require_once __DIR__.'/sl_modules.php';
    $force=function_exists('sl_moduleEnabled')&&sl_moduleEnabled('pwa_install')?'1':'0'; // Settings › Modules › Install the phone app
    echo '<meta name="swiftlane-pwa" content="'.h($role).'" data-root="'.h($root).'" data-scope="'.h($scope).'" data-context="'.($inside?'app':'web').'" data-force="'.$force.'" data-name="'.h($name).'" data-icon="'.h($root.'assets/swiftlane/pwa/'.$role.'-192.png').'" data-install="'.h(json_encode(sl_device()['install'])).'" data-device="'.h(sl_device()['model']).'" data-browser-name="'.h(sl_device()['browserLabel']).'">';
    echo '<link rel="manifest" href="'.h($scope.'manifest.php').'">';
    echo '<meta name="theme-color" content="'.$theme.'" data-light="'.$theme.'" data-dark="#141413"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="default"><meta name="apple-mobile-web-app-title" content="'.h($name).'">';
    echo '<link rel="apple-touch-icon" href="'.h($root.'assets/swiftlane/pwa/'.$role.'-192.png').'">';
    echo '<link rel="stylesheet" href="'.h(cdp_asset($root.'assets/swiftlane/pwa/mobile-app.css')).'">';
    echo '<script defer src="'.h(cdp_asset($root.'assets/swiftlane/pwa/app.js')).'"></script>';
    // app look on phones for every page + tables as cards (all roles)
    echo '<link rel="stylesheet" href="'.h(cdp_asset($root.'assets/swiftlane/pwa/app-shell.css')).'">';
    echo '<script defer src="'.h(cdp_asset($root.'assets/swiftlane/pwa/app-tables.js')).'"></script>';
    // Warm Ledger finish for the phone app (matches the M-* mobile screens of the design canvas)
    echo '<link rel="stylesheet" href="'.h(cdp_asset($root.'assets/swiftlane/pwa/app-ledger.css')).'">';
}
