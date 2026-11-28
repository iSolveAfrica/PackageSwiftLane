<?php
// Rider app (design: M-RDone): summary of a finished stop.
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
$d['stop'] = null;
if ($d['isRider']) {
    $key = (string) ($_GET['id'] ?? '');
    $d['stop'] = ro_stop($d, $key);
    if ($d['stop'] && $d['stop']['state'] !== 'done') {
        header('Location: ' . $d['stop']['href']);
        exit;
    }
    $d['stop'] = $d['stop'] ?? ro_pastStop(ro_me(), $key);
    $d['minutes'] = $d['stop'] ? ro_tripMinutes($d['stop']) : null;
}
sl_render('RiderDone', sl_mxPage(['id' => 'drvapp', 'title' => 'Trip complete', 'board' => 'RiderOps', 'plain' => true]), $d);
