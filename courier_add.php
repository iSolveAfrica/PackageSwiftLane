<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// New shipment (Swift Lane UI: views/sl/pages/NewShipment.php). The form posts
// to the legacy add endpoint, which keeps pricing, numbering and notifications.
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('add_shipment');
if (sl_userIs('client')) {
    // Customers hold add_shipment too; they use the customer booking form.
    header('Location: courier_add_client.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}
require_once 'views/sl/data/shipments.php';
$d = sl_newShipmentData($user, $core, false);
if (!empty($_GET['saved'])) {
    $_SESSION['sl_flash'] = ['type' => 'ok', 'text' => 'Shipment saved. Ready for the next one.'];
}
sl_render('NewShipment', ['id' => 'new', 'title' => 'New Shipment', 'board' => 'NewShipment', 'scripts' => ['dataJs/sl/new_shipment.js'], 'primary' => ['href' => sl_route('AirShipments'), 'label' => 'All shipments']], $d);
