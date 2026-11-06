<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Shipment tracking (Swift Lane UI: views/sl/pages/ShipmentTracking.php).
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('track_shipment');
require_once 'views/sl/data/shipments.php';
$d = sl_shipmentTrackingData($user, $core, (int) ($_GET['id'] ?? 0), trim((string) ($_GET['q'] ?? '')));
sl_render('ShipmentTracking', ['id' => 'shiptrack', 'title' => 'Shipment tracking', 'board' => 'ShipmentTracking', 'wide' => true], $d);
