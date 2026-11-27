<?php
// Rider app · Performance (views/sl/pages/RiderRatings.php, data: views/sl/data/rider_money.php).
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (!sl_userIs('driver') && !$user->cdp_hasPermission('view_user_list')) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/rider_money.php';
sl_render('RiderRatings', sl_mxPage(['id' => 'rperf', 'title' => 'Performance', 'board' => 'RiderMoney', 'plain' => true, 'scripts' => ['dataJs/sl/rider_app.js']]), sl_riderRatingsData($user, $core));
