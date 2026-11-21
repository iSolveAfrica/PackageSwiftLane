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
require_permission('view_shipment_list');
// view_shipment_list is soft-passed for customers, drivers and agency users and the file id below
// is any attachment: without these checks any signed-in user could delete anyone's files. The
// callers are the shipment edit / accept / pickup-accept forms (courier_edit.php: edit_shipment,
// courier_accept.php: complete_client_shipment, pickup_accept.php: pickup_accept), so require
// one of those page permissions.
require_permission(['edit_shipment', 'complete_client_shipment', 'pickup_accept']);

require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/rbac.php');

$errors = array();

$cdpFileId = (int) ($_POST['id'] ?? 0);

if ($cdpFileId <= 0)

    $errors['id'] = 'Please select file';

if (empty($errors)) {
    // Load the attachment and its shipment. Consolidation files (is_consolidate = 1) have their
    // own endpoint; here only shipment files are deleted.
    $cdpDb = new Conexion;
    $cdpDb->cdp_query("SELECT f.id, o.sender_id, o.driver_id, o.agency FROM cdb_order_files f
        JOIN cdb_add_order o ON o.order_id = f.order_id
        WHERE f.id = :id AND COALESCE(f.is_consolidate, 0) = 0 LIMIT 1");
    $cdpDb->bind(':id', $cdpFileId);
    $cdpDb->cdp_execute();
    $cdpFileRow = $cdpDb->cdp_registro();

    $cdpScopeUser = $user->cdp_getUserData();
    $cdpRank = cdp_roleRankById((int) ($cdpScopeUser->userlevel ?? 0));
    if ($cdpRank >= 2) {
        // Agency-restricted staff only touch their own agency's shipments.
        $cdpOk = $cdpFileRow && cdp_ajaxRowInAgencyScope($cdpFileRow);
    } elseif ($cdpRank === 1) {
        // Drivers and agency users: same scope as accept_courier_ajax.php (their shipment as
        // sender or driver, or their agency's).
        $cdpUid = (int) ($_SESSION['userid'] ?? 0);
        $cdpCtx = cdp_getAgencyContext();
        $cdpAgencies = array_filter([
            (int) ($cdpCtx['agency_id'] ?? 0),
            ((int) $cdpScopeUser->userlevel === 6) ? (int) cdp_getAgencyBranchIdForUser($cdpScopeUser->name_off) : 0,
        ]);
        $cdpOk = $cdpFileRow && (
            (int) $cdpFileRow->sender_id === $cdpUid
            || (int) $cdpFileRow->driver_id === $cdpUid
            || in_array((int) $cdpFileRow->agency, $cdpAgencies, true)
        );
    } else {
        // Customers never reach the edit / accept forms.
        $cdpOk = false;
    }
    if (!$cdpOk) {
        $errors['id'] = 'Please select file';
    }
}

if (empty($errors)) {

    $data = array(

        'id' => $cdpFileId
    );

    $insert = cdp_deleteFileCourier($data);

    if ($insert) {

        $messages[] =  $lang['message_ajax_success_delete'];
    } else {

        $errors['critical_error'] = $lang['message_ajax_error1'];
    }
}


if (!empty($errors)) {
?>
    <div class="alert alert-danger" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <?php echo $lang['message_ajax_error2']; ?>
        <ul class="error">
            <?php
            foreach ($errors as $error) { ?>
                <li>
                    <i class="icon-double-angle-right"></i>
                    <?php
                    echo $error;

                    ?>

                </li>
            <?php

            }
            ?>


        </ul>
        </p>
    </div>



<?php
}

if (isset($messages)) {

?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <p><span class="icon-info-sign"></span>
            <?php
            foreach ($messages as $message) {
                echo $message;
            }
            ?>
        </p>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

<?php
}
?>