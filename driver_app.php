<?php
// Rider app (Swift Lane UI): mobile screen for the logged-in rider.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (!sl_userIs('driver') && !$user->cdp_hasPermission('view_user_list')) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/riders.php';
sl_render('DriverApp', ['id' => 'drvapp', 'title' => 'Rider app', 'board' => 'DriverApp', 'plain' => true], sl_driverAppData($user, $core));
