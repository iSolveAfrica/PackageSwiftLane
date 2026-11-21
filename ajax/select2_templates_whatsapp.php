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




require_once("../loader.php");
require_once("../helpers/ajax_guard.php");
require_login();
require_permission(['manage_whatsapp_templates', 'push_notifications', 'view_tools']);

$db = new Conexion;
$where = '';
$search = '';
if (isset($_REQUEST['q'])) {
    $search = trim((string) $_REQUEST['q']);
    $where = "WHERE title LIKE :q";
}

$list = array();
$data = [];


$sql = "SELECT * FROM whatsapp_templates $where";

$db->cdp_query($sql);
if ($where !== '') $db->bind(':q', '%' . $search . '%');
$db->cdp_execute();

$datas = $db->cdp_registros();

foreach ($datas as $key) {

    $data[] = array('id' => $key->id, 'text' => $key->title, 'body' => $key->body);
}

echo json_encode($data);
