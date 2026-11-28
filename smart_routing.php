<?php
/** Smart Routing (Swift Lane UI): riders of the day on a map, one-click rider routes, route details, riders and vehicles. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['dispatch_riders', 'assign_drivers']);
// Same staff check as ajax/sl/smart_routing_ajax.php: the legacy assign_drivers permission is also
// held by some customer and rider roles, and this page shows riders, positions and customers.
require_once 'helpers/delivery_service.php';
if (!dl_manager($user)) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/smart_routing.php';
sl_render('SmartRouting', [
    'id' => 'smartrouting',
    'title' => 'Smart Routing',
    'board' => 'SmartRouting',
    'navCollapsed' => true,
    'wide' => true,
    'styles' => ['assets/swiftlane/route-map.css'],
    'scripts' => ['assets/swiftlane/route-map.js', 'dataJs/sl/smart_routing.js'],
], sl_smartRoutingData($user, $core));
