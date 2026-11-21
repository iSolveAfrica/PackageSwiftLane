<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Shipment detail (Swift Lane UI: views/sl/pages/ShipmentDetail.php).
// Customers may open their own shipments only (scope enforced in sl_shipmentLoad).
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(sl_userIs('client') ? null : 'view_shipment_details');
require_once 'views/sl/data/shipments.php';
$d = sl_shipmentDetailData($user, $core, (int) ($_GET['id'] ?? 0));
if (empty($d['missing']) && function_exists('cdp_activityLog')) {
    cdp_activityLog(['module' => 'shipments', 'verb' => 'view', 'action' => 'shipment.view', 'entity_type' => 'shipment',
        'entity_id' => (int) $d['id'], 'entity_label' => (string) $d['track'], 'summary' => 'Viewed shipment ' . $d['track']]);
}
if (!empty($d['missing'])) {
    http_response_code(404);
}
sl_render('ShipmentDetail', ['id' => 'air', 'title' => !empty($d['track']) ? 'Shipment ' . $d['track'] : 'Shipment', 'board' => 'ShipmentDetail'], $d);
