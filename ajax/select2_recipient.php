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
// Login required (security fix 2026-11-21): anonymous callers could read any user's
// contact details and saved recipients by walking ids.
$__u = new User();
if (!$__u->cdp_loginCheck()) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['success' => false, 'message' => 'Session required']); exit; }
require_once(__DIR__ . '/../helpers/querys.php');
require_once(__DIR__ . '/../helpers/rbac.php');

$db = new Conexion;

$sender_id = (int)($_REQUEST['id'] ?? 0);
// Customers only ever load their own recipients: force the sender to the signed-in user.
if (cdp_roleRankById((int) $__u->userlevel) < 1) {
    $sender_id = (int) $__u->uid;
}
$search = isset($_REQUEST['q']) ? cdp_sanitize($_REQUEST['q']) : '';

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

/*
------------------------------------
1. DEFAULT RECIPIENT (THE SENDER)
   No agency/userlevel filter — sender is uniquely identified by id.
------------------------------------
*/

$sql = "SELECT id,fname,lname,email,phone FROM cdb_users
        WHERE id = $sender_id
        AND (
            fname LIKE '%$search%' OR
            lname LIKE '%$search%' OR
            email LIKE '%$search%' OR
            phone LIKE '%$search%'
        )";

$db->cdp_query($sql);
$db->cdp_execute();
$users = $db->cdp_registros();

$sender_email = '';
foreach ($users as $row) {
    $sender_email = $row->email ?? '';
    $data[] = [
        'id' => $row->id,
        'text' => $row->fname . " " . $row->lname . " · " . implode(" · ", array_filter([$row->email, $row->phone])), 'name' => trim($row->fname . ' ' . $row->lname), 'phone' => (string) ($row->phone ?? ''), 'locker' => (string) ($row->locker ?? ''), 'email' => (string) ($row->email ?? ''),
        'type' => 'user'
    ];
}


/*
------------------------------------
2. EXTRA RECIPIENTS
   Exclude any recipient whose email matches the sender (prevents duplicates
   when the sender was also added as their own recipient in cdb_recipients).
------------------------------------
*/

// NULL-safe: `email != 'x'` evaluates to NULL for rows without an email and
// silently hid those recipients from the dropdown (261 rows affected).
$excludeEmail = $sender_email !== ''
    ? " AND (email IS NULL OR email = '' OR email != '" . addslashes($sender_email) . "')"
    : '';

$sql = "SELECT * FROM cdb_recipients
        WHERE sender_id = $sender_id
        $excludeEmail
        AND (
            fname LIKE '%$search%' OR
            lname LIKE '%$search%' OR
            email LIKE '%$search%' OR
            phone LIKE '%$search%'
        )";

if ($ctx['is_restricted'] && $ctx['agency_id'] !== null) {
    $sql .= " AND agency_id=" . (int)$ctx['agency_id'];
}

$db->cdp_query($sql);
$db->cdp_execute();
$recipients = $db->cdp_registros();

foreach ($recipients as $row) {
    $data[] = [
        'id' => $row->id,
        'text' => $row->fname . " " . $row->lname . " · " . implode(" · ", array_filter([$row->email, $row->phone])), 'name' => trim($row->fname . ' ' . $row->lname), 'phone' => (string) ($row->phone ?? ''), 'locker' => (string) ($row->locker ?? ''), 'email' => (string) ($row->email ?? ''),
        'type' => 'recipient'
    ];
}

echo json_encode($data);