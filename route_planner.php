<?php
/** Route planner (Swift Lane UI): parcel allocation, route optimisation, inventory on board, live fleet. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['dispatch_riders', 'assign_drivers']);
if (is_file(__DIR__ . '/helpers/route_planner.php')) {
    require_once 'helpers/route_planner.php';
}
// Same staff check as ajax/sl/route_planner_ajax.php: the legacy assign_drivers permission is also
// held by some customer and rider roles, and this page shows riders, positions and customers.
require_once 'helpers/delivery_service.php';
if (!dl_manager($user)) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/route_planner.php';
sl_render('RoutePlanner', [
    'id' => 'routeplanner',
    'title' => 'Route planner',
    'board' => 'RoutePlanner',
    'wide' => true,
    // The shared map component ships with the route engine; skip it (the page shows a list) until it exists.
    'scripts' => array_values(array_filter(['assets/swiftlane/route-map.js', 'dataJs/sl/route_planner.js'], fn($f) => is_file(__DIR__ . '/' . $f))),
    'styles' => array_values(array_filter(['assets/swiftlane/route-map.css'], fn($f) => is_file(__DIR__ . '/' . $f))),
], sl_routePlannerData($user, $core));
