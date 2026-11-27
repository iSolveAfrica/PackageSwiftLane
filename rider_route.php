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
// Today's planned route from the Route Planner (optimised order, ETAs, loading checklist) when dispatch
// has confirmed one; otherwise the open stops below.
if (sl_userIs('driver') && is_file(CDP_ROOT . '/helpers/route_planner.php')) {
    require_once 'views/sl/data/riders.php';
    $pd = sl_driverAppData($user, $core);
    if (!empty($pd['rv'])) {
        sl_render('RiderPlannedRoute', sl_mxPage(['id' => 'drvapp', 'title' => 'Route', 'board' => 'RiderOps', 'plain' => true,
            'styles' => ['assets/swiftlane/route-map.css', 'assets/swiftlane/pages/DriverApp.css'],
            'scripts' => ['assets/swiftlane/route-map.js', 'assets/swiftlane/rider-device.js', 'dataJs/sl/rider_route.js']]), $pd);
        exit;
    }
}
require_once 'views/sl/data/rider_ops.php';
$d = ro_data($user, $core);
$d['route'] = $d['isRider'] ? sl_riderRouteMap(array_map(fn($s) => ['addr' => $s['addr'], 'id' => $s['id'], 'cust' => $s['name']], $d['open'])) : null;
sl_render('RiderRoute', sl_mxPage(['id' => 'drvapp', 'title' => 'Route', 'board' => 'RiderOps', 'plain' => true, 'scripts' => ['dataJs/sl/rider_map.js']]), $d);
