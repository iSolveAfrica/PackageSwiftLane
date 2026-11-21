<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************




require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_client_list');


$db = new Conexion;
$user = new User;

// The id is a primary key: cast it instead of pasting the sanitized string into SQL.
$order_id = (int) ($_GET['id'] ?? 0);
$list = array();

// The items belong to a package: only list them when the caller may see that package
// (agency-restricted staff only reach their agency's, as with get_data_shipment_edit_ajax.php).
// Out of scope or missing is answered with an empty list.
require_once(__DIR__ . '/../../helpers/print_access.php');
$db->cdp_query("SELECT sender_id, driver_id, agency FROM cdb_customers_packages WHERE order_id = :id LIMIT 1");
$db->bind(':id', $order_id);
$db->cdp_execute();
$owner = $db->cdp_registro();
if ($order_id <= 0 || !$owner || !cdp_printRowInScope($owner, (int) $user->uid, (int) $user->userlevel)) {
    echo json_encode($list);
    exit;
}

$db->cdp_query("SELECT * FROM cdb_customers_packages_detail  WHERE  order_id = :id");
$db->bind(':id', $order_id);
$db->cdp_execute();

$data = $db->cdp_registros();

foreach ($data as $key) {
    $list[] = array(
        'id' => $key->order_item_id,
        'qty' => $key->order_item_quantity,
        'description' => $key->order_item_description,
        'length' => $key->order_item_length,
        'width' => $key->order_item_width,
        'height' => $key->order_item_height,
        'weight' => $key->order_item_weight,
        'declared_value' => $key->order_item_declared_value,
        'fixed_value' => $key->order_item_fixed_value,
    );
}

echo json_encode($list);
