<?php
// Rider app (design: M-RPickup): tick off the parcels of the open jobs by scanning or typing the tracking number.
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
$d['focus'] = $d['isRider'] ? ro_stop($d, (string) ($_GET['id'] ?? '')) : null;
sl_render('RiderScan', sl_mxPage(['id' => 'drvapp', 'title' => 'Pickup scan', 'board' => 'RiderOps', 'plain' => true, 'scripts' => ['dataJs/sl/rider_scan.js']]), $d);
