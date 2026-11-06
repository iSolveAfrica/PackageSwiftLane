<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Air shipments list (Swift Lane UI: views/sl/pages/AirShipments.php).
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
// Customers see their own shipments only (scoped in the data provider).
sl_gate(sl_userIs('client') ? null : 'view_shipment_list');
require_once 'views/sl/data/shipments.php';
if (($_GET['export'] ?? '') === 'csv') {
    require 'views/sl/export/shipments_csv.php';
    exit;
}
sl_render('AirShipments', ['id' => 'air', 'title' => sl_userIs('client') ? 'My Shipments' : 'Air Shipments', 'board' => 'AirShipments', 'wide' => true], sl_airShipmentsData($user, $core));
