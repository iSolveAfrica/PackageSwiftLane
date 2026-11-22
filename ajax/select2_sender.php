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
require_once(__DIR__ . '/../helpers/rbac.php');

$user = new User();
$db = new Conexion;

$search = cdp_sanitize($_REQUEST['q']);

$list = array();
$data = [];

$ctx = cdp_getAgencyContext();
$extraWhere = "";
// Offer the senders the booking endpoints accept (add_courier_ajax.php and siblings): customers on
// any Customer-derived role (rank 0), not only the seed userlevel 1. The role ids come from the
// small cdb_user_roles map, so the user search stays one SQL query.
$scopeCustomerRoles = [1];
foreach (array_keys(cdp_roleFlagsMap()) as $scopeRoleId) {
	if (cdp_roleRankById((int) $scopeRoleId) === 0) {
		$scopeCustomerRoles[] = (int) $scopeRoleId;
	}
}
$scopeCustomerRoles = array_values(array_unique($scopeCustomerRoles));
// Agency resolution as in the booking endpoints: a restricted context with an agency keeps it;
// other agency users (rank 1, not drivers, e.g. custom agency roles the context does not restrict)
// use their name_off branch, then users.agency_id. Without this they were offered every customer
// and then refused for anyone outside their agency. Drivers are not agency-scoped.
$scopeLevel = (int) $__u->userlevel;
$scopeIsAgencyUser = cdp_roleRankById($scopeLevel) === 1 && $scopeLevel !== 3 && !cdp_roleHasFlag($scopeLevel, 'is_driver');
if (!empty($ctx['is_restricted']) && (int) ($ctx['agency_id'] ?? 0) > 0) {
	$extraWhere = " AND agency_id = " . (int)$ctx['agency_id'];
} elseif ($scopeIsAgencyUser) {
	$scopeMe = $__u->cdp_getUserData();
	$scopeAgencyId = $scopeMe ? (int) cdp_getAgencyBranchIdForUser($scopeMe->name_off ?? '') : 0;
	if ($scopeAgencyId <= 0 && $scopeMe) {
		$scopeAgencyId = (int) ($scopeMe->agency_id ?? 0);
	}
	if ($scopeAgencyId <= 0) {
		echo json_encode([]);
		exit;
	}
	$extraWhere = " AND agency_id = " . $scopeAgencyId;
} elseif (!empty($ctx['is_restricted'])) {
	// Agency-restricted staff without an agency: nothing may be booked.
	echo json_encode([]);
	exit;
}
// A customer may only pick themselves as sender (security fix 2026-11-21): without this a
// customer could search every other customer's email, phone and locker code.
if (cdp_roleRankById((int) $__u->userlevel) < 1) {
	$extraWhere .= " AND id = " . (int) $__u->uid;
}

$sql = "SELECT * FROM cdb_users
 WHERE
  (CONCAT(fname, ' ', lname) LIKE '%" . $search . "%' or fname LIKE '%" . $search . "%'
  or lname LIKE '%" . $search . "%'  
  or email LIKE '%" . $search . "%'
  or phone LIKE '%" . $search . "%'
  or locker LIKE '%" . $search . "%')
   and userlevel IN (" . implode(',', array_map('intval', $scopeCustomerRoles)) . ")" . $extraWhere;

$db->cdp_query($sql);
$db->cdp_execute();

$datas = $db->cdp_registros();

foreach ($datas as $key) {

	$data[] = array('id' => $key->id, 'text' => $key->fname . " " . $key->lname . " · " . implode(" · ", array_filter([$key->locker, $key->email, $key->phone])), 'name' => trim($key->fname . ' ' . $key->lname), 'phone' => (string) ($key->phone ?? ''), 'locker' => (string) ($key->locker ?? ''), 'email' => (string) ($key->email ?? ''));
}

echo json_encode($data);
