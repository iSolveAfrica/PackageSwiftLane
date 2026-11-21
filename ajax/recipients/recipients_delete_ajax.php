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
require_permission('view_recipients');
require_once(__DIR__ . '/../../helpers/sl_view.php');
require_once CDP_ROOT . '/views/sl/data/recipients.php';
// Ownership: customers may only touch their own recipients, agency staff only their agency's
// (same scope as ajax/sl/recipient_ajax.php). Drivers have no recipients of their own to manage
// on behalf of others, so they are limited to sender_id = themselves as well.
$rcScope = sl_rcScope();
if (sl_userIs('driver')) $rcScope .= ' AND r.sender_id = ' . (int) $_SESSION['userid'];

$db = new Conexion;

$id = (int) ($_REQUEST['id'] ?? 0);

if (!sl_row('SELECT r.id FROM cdb_recipients r LEFT JOIN cdb_users u ON u.id = r.sender_id WHERE r.id = :id' . $rcScope, [':id' => $id])) {
    header('Content-type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => $lang['message_ajax_error1']]);
    exit;
}

$errors = array();

if ($id == 1) {

    $errors['admin'] =  $lang['validate_field_ajax116'];
}
if (CDP_APP_MODE_DEMO === true) {
?>

    <div class="alert alert-warning" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <span>Error! </span> There was an error processing the request
        <ul class="error">

            <li>
                <i class="icon-double-angle-right"></i>
                This is a demo version, this action is not allowed. Contact iSolveAfrica Ltd. to enable the full version of Swiftlane.

            </li>


        </ul>
        </p>
    </div>
    <?php
} else {



    if (empty($errors)) {

        header('Content-type: application/json; charset=UTF-8');
    
        $response = array();

            $verifyExistsShipment = cdp_verifyReferentialIntegrity('cdb_add_order', 'receiver_id', $id);
            $verifyExistsCustomerPackages = cdp_verifyReferentialIntegrity('cdb_customers_packages', 'receiver_id', $id);
            $verifyExistsConsolidate = cdp_verifyReferentialIntegrity('cdb_consolidate', 'receiver_id', $id);

        if ($verifyExistsShipment || $verifyExistsCustomerPackages || $verifyExistsConsolidate) {

            $response['status'] = 'error1'; // Cambio aquí para manejar 'error1'
            $response['message'] = $lang['validate_field_ajax132'];
        } else {
            
            $delete = cdp_deleteRecipient($id);


            if ($delete) {
                $response['status'] = 'success';
                $response['message'] = $lang['message_ajax_success_delete'];
                // The recipient's addresses live in cdb_recipients_addresses. This used to call
                // cdp_deleteAddressesUsers($id), which wiped cdb_users_multiple_addresses of the
                // USER whose id happened to equal the recipient id.
                $db->cdp_query('DELETE FROM cdb_recipients_addresses WHERE recipient_id = :r');
                $db->bind(':r', $id);
                $db->cdp_execute();
            } else {
                $response['status'] = 'error';
                $response['message'] = $lang['message_ajax_error1'];
            }
        }

        echo json_encode($response);

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
}
