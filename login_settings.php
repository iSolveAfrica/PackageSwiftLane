<?php
// LoginSettings board: pictures, captions, look and texts of the sign-in pages.
// Writes: ajax/sl/login_settings_ajax.php (cdb_sl_settings, uploads in assets/uploads/login/).
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_tools');
require_once 'helpers/sl_auth.php';
$look = sl_loginLook();
$slides = sl_loginSlidesAll();
$pv = in_array($_GET['pv'] ?? '', ['login', 'register', 'forgot', 'otp'], true) ? $_GET['pv'] : 'login';
$on = array_values(array_filter($slides, fn($s) => $s['on']));
$cur = max(0, min(count($on) - 1, (int) ($_GET['n'] ?? 0)));
sl_render('LoginSettings', ['id' => 'loginpg', 'title' => 'Login page', 'board' => 'LoginSettings'], [
    'slides' => $slides,
    'look' => $look,
    'pv' => $pv,
    'cur' => $on[$cur] ?? null,
    'curN' => $cur,
    'onCount' => count($on),
    'logo' => !empty($core->logo_web) ? 'assets/' . $core->logo_web : '',
    'favicon' => !empty($core->favicon) ? 'assets/' . $core->favicon : '',
    'site' => $core->site_name ?? 'Swift Lane',
    'canLogo' => $user->cdp_hasPermission('view_tools'),
]);
