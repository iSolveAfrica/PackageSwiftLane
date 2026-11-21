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
require_permission('view_pickup_list');

require_once("../../helpers/querys.php");
$db = new Conexion;
$user = new User;
$userData = $user->cdp_getUserData();

// Refusing a pickup changes its status. This endpoint only asked for the customer-facing
// 'view_pickup_list', so a customer could refuse any pickup by posting its id. Require the
// pickup page's own permission (as accept_pickup_ajax.php does), never for customers, and
// keep drivers/agency users to pickups of their agency or assigned to them; agency-restricted
// staff to their agency.
require_once(__DIR__ . '/../../helpers/rbac.php');
$cdpRefuseRank = cdp_roleRankById((int) $user->userlevel);
if ($cdpRefuseRank < 1 || !$user->cdp_hasPermission('pickup_accept')) {
    _ajax_guard_send(403, ['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
}
if (!empty($_POST['id'])) {
    $cdpRefuseDb = new Conexion;
    $cdpRefuseDb->cdp_query("SELECT agency, driver_id, is_pickup FROM cdb_add_order WHERE order_id = :id LIMIT 1");
    $cdpRefuseDb->bind(':id', (int) $_POST['id']);
    $cdpRefuseRow = $cdpRefuseDb->cdp_registro();
    if (!$cdpRefuseRow || (int) $cdpRefuseRow->is_pickup !== 1) {
        _ajax_guard_send(404, ['success' => false, 'error' => 'Not found', 'message' => 'Pickup not found']);
    }
    if ($cdpRefuseRank < 2) {
        // Same agency set as the pickup list (pickup_list_ajax.php): cdb_users.agency_id plus the
        // name_off branch for agency users (userlevel 6).
        $cdpRefuseCtx = cdp_getAgencyContext();
        $cdpRefuseAgencies = array_filter([
            (int) ($cdpRefuseCtx['agency_id'] ?? 0),
            ((int) ($userData->userlevel ?? 0) === 6) ? (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '') : 0,
        ]);
        $cdpOwnAgency = in_array((int) $cdpRefuseRow->agency, $cdpRefuseAgencies, true);
        $cdpOwnDriver = (int) $cdpRefuseRow->driver_id > 0 && (int) $cdpRefuseRow->driver_id === (int) ($_SESSION['userid'] ?? 0);
        $cdpRefuseOk = $cdpOwnAgency || $cdpOwnDriver;
    } else {
        $cdpRefuseOk = cdp_ajaxRowInAgencyScope($cdpRefuseRow);
    }
    if (!$cdpRefuseOk) {
        _ajax_guard_send(403, ['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
    }
}

$errors = array();



if (empty($_POST['id']))

    $errors['id'] = 'Please select shipment';

if (empty($errors)) {

    $data = array(

        'id' => (int) $_POST['id']
    );

    $insert = cdp_refuesePickup($data);

    //INSERT HISTORY USER
    $date = date("Y-m-d H:i:s");
    $db->cdp_query("
                INSERT INTO cdb_order_user_history 
                (
                    user_id,
                    order_id,
                    action,
                    date_history                   
                    )
                VALUES
                    (
                    :user_id,
                    :order_id,
                    :action,
                    :date_history
                    )
            ");



    $db->bind(':order_id',  $data['id']);
    $db->bind(':user_id',  $_SESSION['userid']);
    $db->bind(':action', $lang['notification_shipment19']);
    $db->bind(':date_history',  trim($date));
    $db->cdp_execute();

    if ($insert) {
        $messages[] = $lang['message_ajax_success_refuse'];
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