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
sl_gate('courier_add_client');
require_once 'views/sl/data/shipments.php';
$d = sl_newShipmentData($user, $core, true);
if (!empty($_GET['saved'])) {
    $_SESSION['sl_flash'] = ['type' => 'ok', 'text' => 'Shipment saved. Ready for the next one.'];
}
sl_render('NewShipment', ['id' => 'new', 'title' => 'New Shipment', 'board' => 'NewShipment', 'scripts' => ['dataJs/sl/new_shipment.js'], 'primary' => ['href' => sl_route('AirShipments'), 'label' => 'All shipments']], $d);
