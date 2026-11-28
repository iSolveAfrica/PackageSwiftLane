<?php
// Rider app (design: M-RQueue): the rider's run today: done, next and later stops.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_mx.php';
sl_gate();
if (!sl_userIs('driver') && !$user->cdp_hasPermission('view_user_list')) {
    header('Location: error403.php');
    exit;
}
header('Cache-Control: private, no-store');
require_once 'views/sl/data/rider_ops.php';
$d = ro_data($user, $core);

sl_render('RiderJobs', sl_mxPage(['id' => 'drvapp', 'title' => 'My jobs', 'board' => 'RiderOps', 'plain' => true]), $d);
