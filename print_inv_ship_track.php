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



    require_once("loader.php");

    $user = new User();
    $core = new Core();

    // Security: this page renders a full invoice (names, addresses, phones,
    // charges) by sequential id, so it must not be open to anonymous callers.
    // Customers, drivers and agency users may only print their own documents.
    if ($user->cdp_loginCheck() != true) {
        header("location: login.php");
        exit;
    }
    require_once(__DIR__ . "/helpers/rbac.php");
    if (cdp_roleRankById((int) $user->userlevel) < 2) {
        $ownDb = new Conexion;
        $ownDb->cdp_query('SELECT sender_id, driver_id, agency FROM cdb_add_order WHERE order_id = :id LIMIT 1');
        $ownDb->bind(':id', (int) ($_GET['id'] ?? 0));
        $ownDb->cdp_execute();
        $ownRow = $ownDb->cdp_registro();
        $uid = (int) $user->uid;
        $agencyCtx = cdp_getAgencyContext();
        $isOwner = $ownRow && (
            (int) $ownRow->sender_id === $uid
            || (int) $ownRow->driver_id === $uid
            || (!empty($agencyCtx['is_restricted']) && $agencyCtx['agency_id'] !== null && (int) $ownRow->agency === (int) $agencyCtx['agency_id'])
        );
        if (!$isOwner) {
            http_response_code(403);
            exit('You do not have access to this document.');
        }
    }

      // Audit: record who took this document out of the system.
      require_once(__DIR__ . "/helpers/activity_log.php");
      cdp_activityLogDocument();

      include('views/print/print_inv_ship_track.php');     
           

   
?>