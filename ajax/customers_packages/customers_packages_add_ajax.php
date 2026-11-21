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
require_permission('view_client_list');

require_once("../../helpers/querys.php");

$user = new User;
$core = new Core;
$errors = array();

require_once(__DIR__ . '/../../helpers/uploads.php');
$order_id = (int) ($_POST['order_id'] ?? $_GET['order_id'] ?? 0); // the sea-package screens post it in the form body
if ($order_id <= 0) {
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

    $insert = empty($errors) && cdp_updatePaymentPackagesCustomer($data);



    if ($insert) {

        $messages[] = $lang['notification_shipment27'];

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
        $db->bind(':notification_description', $lang['notification_shipment26']);
        $db->bind(':shipping_type', '4');
        $db->bind(':notification_date',  date("Y-m-d H:i:s"));

        $db->cdp_execute();


        $notification_id = $db->dbh->lastInsertId();


        $users_employees = cdp_getUsersAdminEmployees();

        foreach ($users_employees as $key) {

            cdp_insertNotificationsUsers($notification_id, $key->id);
        }

        cdp_insertNotificationsUsers($notification_id, $_SESSION['userid']);
    } elseif (empty($errors)) {

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

    <script>
        setTimeout('redirect()', 3000);
    </script>

<?php
}
?>