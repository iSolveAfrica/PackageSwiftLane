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
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_pickup_list');
$db = new Conexion;
$user = new User;
$userData = $user->cdp_getUserData();

// This endpoint only asked for the customer-facing 'view_pickup_list', so anyone signed in
// could cancel any shipment or pickup by posting its id. Below staff rank: require
// 'cancel_pickup'; customers may cancel only their own pickup while it is still requested
// (status 14, as ajax/sl/pickup_ajax.php does); drivers/agency users only pickups of their
// agency or assigned to them. Staff (who also cancel shipments here from the shipment
// list) need cancel_pickup or cancel_shipment and stay within their agency scope.
require_once(__DIR__ . '/../../helpers/rbac.php');
$cdpCancelRank = cdp_roleRankById((int) $user->userlevel);
if (!empty($_POST['id_cancel'])) {
    $cdpCancelDb = new Conexion;
    $cdpCancelDb->cdp_query("SELECT sender_id, agency, driver_id, is_pickup, status_courier FROM cdb_add_order WHERE order_id = :id LIMIT 1");
    $cdpCancelDb->bind(':id', (int) $_POST['id_cancel']);
    $cdpCancelRow = $cdpCancelDb->cdp_registro();
    if (!$cdpCancelRow) {
        _ajax_guard_send(404, ['success' => false, 'error' => 'Not found', 'message' => 'Shipment not found']);
    }
    $cdpUid = (int) ($_SESSION['userid'] ?? 0);
    if ($cdpCancelRank >= 2) {
        $cdpCancelOk = ($user->cdp_hasPermission('cancel_pickup') || $user->cdp_hasPermission('cancel_shipment'))
            && cdp_ajaxRowInAgencyScope($cdpCancelRow);
    } elseif (!$user->cdp_hasPermission('cancel_pickup') || (int) $cdpCancelRow->is_pickup !== 1) {
        $cdpCancelOk = false;
    } elseif ($cdpCancelRank < 1) {
        $cdpCancelOk = (int) $cdpCancelRow->sender_id === $cdpUid && (int) $cdpCancelRow->status_courier === 14;
    } else {
        $cdpCancelCtx = cdp_getAgencyContext();
        $cdpCancelOk = (!empty($cdpCancelCtx['agency_id']) && (int) $cdpCancelRow->agency === (int) $cdpCancelCtx['agency_id'])
            || ((int) $cdpCancelRow->driver_id > 0 && (int) $cdpCancelRow->driver_id === $cdpUid);
    }
    if (!$cdpCancelOk) {
        _ajax_guard_send(403, ['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
    }
}

$errors = array();



if (empty($_POST['id_cancel']))

    $errors['id'] = 'Please select shipment';

if (empty($errors)) {

    $data = array(

        'id' => (int) $_POST['id_cancel'],
        'message' => cdp_sanitize($_POST['message'] ?? '')
    );

    $insert = cdp_deleteCourier($data);

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
    $db->bind(':action', $lang['notification_shipment18']);
    $db->bind(':date_history',  trim($date));
    $db->cdp_execute();


    if ($insert) {
        $messages[] = $lang['message_ajax_success_cancel'];
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