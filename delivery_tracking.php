<?php
// Delivery tracking board (Swift Lane UI: views/sl/pages/DeliveryTracking.php).
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_shipment_list');
require_once 'views/sl/data/shipments.php';
$filter = (string) ($_GET['filter'] ?? 'route');
if (!in_array($filter, ['route', 'ready', 'unassigned', 'today', 'failed'], true)) {
    $filter = 'route';
}
$d = sl_deliveryTrackingData($user, $core, (int) ($_GET['sel'] ?? 0), $filter, trim((string) ($_GET['q'] ?? '')));
sl_render('DeliveryTracking', ['id' => 'dtrack', 'title' => 'Delivery tracking', 'board' => 'DeliveryTracking', 'wide' => true], $d);
