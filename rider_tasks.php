<?php
// Rider app (design: M-RTasks): jobs dispatch assigned to the rider that are not started yet.
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

sl_render('RiderTasks', sl_mxPage(['id' => 'drvapp', 'title' => 'Task list', 'board' => 'RiderOps', 'plain' => true]), $d);
