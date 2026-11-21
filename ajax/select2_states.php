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

// Public on purpose: the sign-up page (dataJs/sl/auth.js) fills its region list from here before
// the visitor has an account. It returns only reference data (id + name of a state), and every
// input is bound, so an anonymous caller cannot read anything else.
$db = new Conexion;

$country = (int) ($_REQUEST['id'] ?? 0);
$search = isset($_REQUEST['q']) ? trim((string) $_REQUEST['q']) : '';
$search = addcslashes(mb_substr($search, 0, 100), '%_\\');

$data = [];

$db->cdp_query("SELECT id, name FROM cdb_states WHERE name LIKE :search AND country_id = :country");
$db->bind(':search', '%' . $search . '%');
$db->bind(':country', $country);
$db->cdp_execute();

$datas = $db->cdp_registros();

foreach ($datas as $key) {

    $data[] = array('id' => $key->id, 'text' => $key->name);
}

echo json_encode($data);
