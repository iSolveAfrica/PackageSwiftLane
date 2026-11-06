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


$db = new Conexion;

$sender = (int) ($_REQUEST['id'] ?? 0);

$list = array();

$data = [];

$sql = "SELECT *  FROM cdb_senders_addresses WHERE user_id='" . $sender . "'";

$db->cdp_query($sql);
$db->cdp_execute();

$datas = $db->cdp_registros();

if (!$datas) {
	// Legacy fallback (raw text values)
	$db->cdp_query("SELECT * FROM cdb_users_multiple_addresses WHERE user_id='" . $sender . "'");
	$db->cdp_execute();
	$legacy = $db->cdp_registros();

	foreach ($legacy as $key) {
		$data[] = array(
			'id' => $key->id ?? $key->id_addresses,
			'text' => $key->address ?? '',
			'country' => $key->country ?? '',
			'state' => $key->state ?? '',
			'city' => $key->city ?? '',
			'zip_code' => $key->zip_code ?? ($key->postal ?? ''),
		);
	}
} else {
	foreach ($datas as $key) {

		$db->cdp_query("SELECT * FROM cdb_countries where id= '" . (int) $key->country . "'");
		$country = $db->cdp_registro();

		$db->cdp_query("SELECT * FROM cdb_states where id= '" . (int) $key->state . "'");
		$state = $db->cdp_registro();

		$db->cdp_query("SELECT * FROM cdb_cities where id= '" . (int) $key->city . "'");
		$city = $db->cdp_registro();

		$data[] = array(
			'id' => $key->id_addresses,
			'text' => $key->address,
			'country' => $country ? $country->name : '',
			'state' => $state ? $state->name : '',
			'city' => $city ? $city->name : '',
			'zip_code' => $key->zip_code,
		);
	}
}

echo json_encode($data);
