<?php
// Rider app (design: M-RMap): the open stops in order with a map preview.
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
$d['route'] = $d['isRider'] ? sl_riderRouteMap(array_map(fn($s) => ['addr' => $s['addr'], 'id' => $s['id'], 'cust' => $s['name']], $d['open'])) : null;
sl_render('RiderRoute', sl_mxPage(['id' => 'drvapp', 'title' => 'Route', 'board' => 'RiderOps', 'plain' => true, 'scripts' => ['dataJs/sl/rider_map.js']]), $d);
