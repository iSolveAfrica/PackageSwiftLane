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

$id   = (int) ($_REQUEST['id'] ?? 0);
$type = $_REQUEST['type'] ?? 'recipient';

$data = [];

// Customers may only read their own addresses and those of their own recipients
// (security fix 2026-11-21): ids were otherwise taken from the request unchecked.
require_once(__DIR__ . '/../helpers/rbac.php');
if (cdp_roleRankById((int) $__u->userlevel) < 1) {
    if ($type === "user") {
        $id = (int) $__u->uid;
    } else {
        $db->cdp_query("SELECT sender_id FROM cdb_recipients WHERE id = :rid LIMIT 1");
        $db->bind(':rid', $id);
        $db->cdp_execute();
        $owner = $db->cdp_registro();
        if (!$owner || (int) $owner->sender_id !== (int) $__u->uid) {
            echo json_encode([]);
            exit;
        }
    }
} else {
    // Drivers, agency users and agency-restricted staff: the owning customer (the user itself for
    // type=user, the recipient's sender otherwise) must be one they could pick in select2_sender.php
    // (userlevel 1, in their agency when restricted). Drivers keep access: their role has add_shipment.
    $ctx = cdp_getAgencyContext();
    if (cdp_roleRankById((int) $__u->userlevel) === 1 || !empty($ctx['is_restricted'])) {
        if (!empty($ctx['is_restricted']) && $ctx['agency_id'] === null) {
            echo json_encode([]);
            exit;
        }
        $agencyWhere = !empty($ctx['is_restricted']) ? " AND u.agency_id = " . (int) $ctx['agency_id'] : '';
        if ($type === "user") {
            $db->cdp_query("SELECT u.id FROM cdb_users u WHERE u.id = :id AND u.userlevel = 1" . $agencyWhere . " LIMIT 1");
        } else {
            $db->cdp_query("SELECT r.id FROM cdb_recipients r JOIN cdb_users u ON u.id = r.sender_id"
                . " WHERE r.id = :id AND u.userlevel = 1" . $agencyWhere . " LIMIT 1");
        }
        $db->bind(':id', $id);
        $db->cdp_execute();
        if (!$db->cdp_registro()) {
            echo json_encode([]);
            exit;
        }
    }
}

if ($type === "user") {
    $sql = "SELECT * FROM cdb_senders_addresses WHERE user_id='$id'";

} else {
    $sql = "SELECT * FROM cdb_recipients_addresses WHERE recipient_id='$id'";
}

$db->cdp_query($sql);
$db->cdp_execute();
$datas = $db->cdp_registros();

if ($type === "user" && !$datas) {
    $db->cdp_query("SELECT * FROM cdb_users_multiple_addresses WHERE user_id='$id'");
    $db->cdp_execute();
    $legacy = $db->cdp_registros();

    foreach ($legacy as $key) {
        $data[] = [
            'id' => $key->id ?? $key->id_addresses,
            'text' => $key->address ?? '',
            'country' => $key->country ?? '',
            'state' => $key->state ?? '',
            'city' => $key->city ?? '',
            'zip_code' => $key->zip_code ?? ($key->postal ?? '')
        ];
    }
} elseif ($datas) {
    foreach ($datas as $key) {

        $db->cdp_query("SELECT name FROM cdb_countries WHERE id='" . (int) $key->country . "'");
        $country = $db->cdp_registro();

        $db->cdp_query("SELECT name FROM cdb_states WHERE id='" . (int) $key->state . "'");
        $state = $db->cdp_registro();

        $db->cdp_query("SELECT name FROM cdb_cities WHERE id='" . (int) $key->city . "'");
        $city = $db->cdp_registro();

        $data[] = [
            'id' => $key->id_addresses,
            'text' => $key->address,
            'country' => $country->name ?? '',
            'state' => $state->name ?? '',
            'city' => $city->name ?? '',
            'zip_code' => $key->zip_code
        ];
    }
}

echo json_encode($data);
