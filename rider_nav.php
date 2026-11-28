<?php
// Rider app (design: M-RNavPickup): address, contact and maps link of one stop.
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
$d['stop'] = $d['isRider'] ? ro_stop($d, (string) ($_GET['id'] ?? '')) : null;
if ($d['stop'] && $d['stop']['state'] === 'done') {
    header('Location: ' . $d['stop']['donePage']);
    exit;
}
sl_render('RiderNav', sl_mxPage(['id' => 'drvapp', 'title' => 'Navigate', 'board' => 'RiderOps', 'plain' => true, 'scripts' => ['dataJs/sl/rider_map.js']]), $d);
