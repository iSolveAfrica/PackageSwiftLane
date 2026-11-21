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
require_permission(['view_consolidate_package', 'view_consolidate_package_list']);

require_once("../../helpers/querys.php");

$user = new User;
$core = new Core;
$errors = array();

// view_consolidate_package / view_consolidate_package_list are also granted to customers, drivers and agencies, and
// order_id comes from the URL: without this check any of them could mark any
// record as paid. Below staff rank the record must be one the user can see in
// the list (theirs as sender/driver, or their agency's).
require_once(__DIR__ . '/../../helpers/rbac.php');
require_once(__DIR__ . '/../../helpers/uploads.php');
$userData = $user->cdp_getUserData();
$order_id = (int) ($_GET['order_id'] ?? 0);
$paidRecord = null;
if ($order_id > 0) {
    $db->cdp_query("SELECT consolidate_id, sender_id, driver_id, agency FROM cdb_consolidate_packages WHERE consolidate_id = :id LIMIT 1");
    $db->bind(':id', $order_id);
    $db->cdp_execute();
    $paidRecord = $db->cdp_registro();
}
$canPay = (bool) $paidRecord;
if ($canPay && cdp_roleRankById((int) $userData->userlevel) < 2) {
    $uid = (int) $_SESSION['userid'];
    // Same agency scope as the lists (sl_opsInScope: the user's agency_id), plus the branch by name.
    $agencyCtxId = (int) (cdp_getAgencyContext()['agency_id'] ?? 0);
    $agencyBranch = ((int) $userData->userlevel === 6) ? (int) cdp_getAgencyBranchIdForUser($userData->name_off) : 0;
    $canPay = (int) $paidRecord->sender_id === $uid
        || (int) $paidRecord->driver_id === $uid
        || ($agencyBranch > 0 && (int) $paidRecord->agency === $agencyBranch)
        || ($agencyCtxId > 0 && (int) $paidRecord->agency === $agencyCtxId);
}
if (!$canPay) {
    $errors['order_id'] = $lang['message_ajax_error2'];
}



if (empty($_POST['mode_pay']))

    $errors['mode_pay'] = 'Selected pay mode';

if (empty($_FILES['file_invoice']['name']))

    $errors['file_invoice'] = 'attach proof of payment';



$status_invoice = 3;


if (empty($errors)) {

    $data = array(

        'mode_pay'   =>   cdp_sanitize($_POST["mode_pay"]),
        'order_id'   =>   $order_id,
        'notes'   =>   cdp_sanitize($_POST["notes"]),
        'status_invoice'   =>   $status_invoice,
        'payment_date'   =>  date("Y-m-d H:i:s"),

    );

    $data['file_invoice'] = '';



    // assets/payment_packages/ is web-served: the client's file name and
    // extension were kept, so a .php/.html/.svg "proof" could be stored there.
    // cdp_uploadStore() accepts only images/PDF (detected from the contents)
    // and stores them under our own random name and extension.
    $stored = cdp_uploadStore($_FILES['file_invoice'], __DIR__ . '/../../assets/payment_packages', 'pay', 'docs');
    if ($stored['ok']) {
        $data['file_invoice'] = 'payment_packages/' . $stored['name'];
    } else {
        $errors['file_invoice'] = $stored['error'];
    }

    $insert = empty($errors) && cdp_updatePaymentConsolidatePackages($data);



    if ($insert) {

        $messages[] =  $lang['message_ajax_success_payment'];

        // SAVE NOTIFICATION
        $db->cdp_query("
                    INSERT INTO cdb_notifications 
                    (
                        user_id,
                        order_id,
                        notification_description,
                        shipping_type,
                        notification_date

                    )
                    VALUES
                        (
                        :user_id,      
                        :order_id,

                        :notification_description,
                        :shipping_type,
                        :notification_date                    
                        )
                ");



        $db->bind(':user_id',  $_SESSION['userid']);
        $db->bind(':order_id', $order_id);
        $db->bind(':notification_description', $lang['notification_shipment16']);
        $db->bind(':shipping_type', '5');
        $db->bind(':notification_date',  date("Y-m-d H:i:s"));

        $db->cdp_execute();


        $notification_id = $db->dbh->lastInsertId();


        $users_employees = cdp_getUsersAdminEmployees();

        foreach ($users_employees as $key) {

            cdp_insertNotificationsUsers($notification_id, $key->id);
        }

        cdp_insertNotificationsUsers($notification_id, $_SESSION['userid']);
    } elseif (empty($errors)) {

        $errors['critical_error'] = $lang['message_ajax_error2'];
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

    <script>
        setTimeout('redirect()', 3000);
    </script>

<?php
}
?>