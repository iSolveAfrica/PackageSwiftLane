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
// Login required (security fix 2026-11-06).
$__u = new User();
if (!$__u->cdp_loginCheck()) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => 'Session required']); exit; }

require_once(__DIR__ . '/../helpers/querys.php');

$user = new User();
$db = new Conexion;

$search = cdp_sanitize($_REQUEST['q']);

$list = array();
$data = [];

$ctx = cdp_getAgencyContext();
$extraWhere = "";
if ($ctx['is_restricted']) {
	if ($ctx['agency_id'] === null) {
		echo json_encode([]);
		exit;
	}
	$extraWhere = " AND agency_id = " . (int)$ctx['agency_id'];
}

$sql = "SELECT * FROM cdb_users
 WHERE
  (CONCAT(fname, ' ', lname) LIKE '%" . $search . "%' or fname LIKE '%" . $search . "%'
  or lname LIKE '%" . $search . "%'  
  or email LIKE '%" . $search . "%'
  or phone LIKE '%" . $search . "%'
  or locker LIKE '%" . $search . "%')
   and userlevel='1'" . $extraWhere;

$db->cdp_query($sql);
$db->cdp_execute();

$datas = $db->cdp_registros();

foreach ($datas as $key) {

	$data[] = array('id' => $key->id, 'text' => $key->fname . " " . $key->lname . " · " . implode(" · ", array_filter([$key->locker, $key->email, $key->phone])));
}

echo json_encode($data);
