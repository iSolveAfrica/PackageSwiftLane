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

// Public on purpose: the sign-up page fills its country list from here before the visitor has an
// account. It returns only active countries (id + name), and the search term is bound.
$db = new Conexion;

$sWhere = '';
$search = '';
if (isset($_REQUEST['q'])) {
    $search = addcslashes(mb_substr(trim((string) $_REQUEST['q']), 0, 100), '%_\\');
    $sWhere = "AND name LIKE :search";
}
$data = [];


$db->cdp_query("SELECT id, name FROM cdb_countries WHERE is_active = 1 $sWhere");
if ($sWhere !== '') {
    $db->bind(':search', '%' . $search . '%');
}
$db->cdp_execute();

$datas = $db->cdp_registros();

foreach ($datas as $key) {

    $data[] = array('id' => $key->id, 'text' => $key->name);
}

echo json_encode($data);
