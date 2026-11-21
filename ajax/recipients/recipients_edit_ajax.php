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
$user = new User;
$core = new Core;
$errors = array();

$recipientId = (int) ($_POST['recipient_id'] ?? 0);
if (!sl_row('SELECT r.id FROM cdb_recipients r LEFT JOIN cdb_users u ON u.id = r.sender_id WHERE r.id = :id' . $rcScope, [':id' => $recipientId])) {
    header('Content-type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => $lang['message_ajax_error1']]);
    exit;
}

if (empty($_POST['fname']))
    $errors['fname'] = $lang['validate_field_ajax122'];

if (empty($_POST['lname']))
    $errors['lname'] = $lang['validate_field_ajax123'];

if (empty($_POST['email']))
    $errors['email'] = $lang['validate_field_ajax125'];


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

        $data = array(
            'lname' => cdp_sanitize($_POST['lname']),
            'fname' => cdp_sanitize($_POST['fname']),
            'phone' => cdp_sanitize($_POST['phone']),
            'email' => cdp_sanitize($_POST['email']),
            'id_recipient' => $recipientId,
        );

        $update = cdp_updateRecipient($data);

        if ($update  && isset($_POST["total_address"])) {

            for ($count = 0; $count < $_POST["total_address"]; $count++) {

                if (isset($_POST["address_id"][$count]) && !empty($_POST["address_id"][$count])) {

                    // Only addresses of this (already ownership-checked) recipient may be rewritten.
                    if (!sl_scalar('SELECT COUNT(*) FROM cdb_recipients_addresses WHERE id_addresses = :a AND recipient_id = :r', [':a' => (int) $_POST["address_id"][$count], ':r' => $recipientId])) {
                        continue;
                    }

                    $dataAddresses = array(
                        'address_id' =>  cdp_sanitize($_POST["address_id"][$count]),
                        'address' =>  cdp_sanitize($_POST["address"][$count]),
                        'country' =>  cdp_sanitize($_POST["country"][$count]),
                        'city' =>  cdp_sanitize($_POST["city"][$count]),
                        'state' =>  cdp_sanitize($_POST["state"][$count]),
                        'postal' =>  cdp_sanitize($_POST["postal"][$count])
                    );

                    cdp_updateRecipientAddress($dataAddresses);
                } else {

                    $dataAddresses = array(
                        'recipient_id' =>   $recipientId,
                        'address' =>  cdp_sanitize($_POST["address"][$count]),
                        'country' =>  cdp_sanitize($_POST["country"][$count]),
                        'city' =>  cdp_sanitize($_POST["city"][$count]),
                        'state' =>  cdp_sanitize($_POST["state"][$count]),
                        'postal' =>  cdp_sanitize($_POST["postal"][$count])
                    );

                    cdp_insertAddressRecipient($dataAddresses);
                }
            }
        }


        if ($update) {
            $response['status'] = 'success';
            $response['message'] = $lang['message_ajax_success_updated'];
        } else {
            $response['status'] = 'error';
            $response['message'] = $lang['message_ajax_error1'];
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
?>