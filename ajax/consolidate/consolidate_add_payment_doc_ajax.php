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
require_permission('view_consolidate_list');

$user = new User;
$core = new Core;
$errors = array();

// view_consolidate_list is also granted to customers, drivers and agencies, and
// order_id comes from the URL: without this check any of them could mark any
// consolidation as paid. Below staff rank the consolidation must be one the
// user can see in the list (theirs as sender/driver, or their agency's).
require_once(__DIR__ . '/../../helpers/rbac.php');
$userData = $user->cdp_getUserData();
$order_id = (int) ($_GET['order_id'] ?? 0);
$consolidation = null;
if ($order_id > 0) {
    $db->cdp_query("SELECT consolidate_id, sender_id, driver_id, agency FROM cdb_consolidate WHERE consolidate_id = :id LIMIT 1");
    $db->bind(':id', $order_id);
    $db->cdp_execute();
    $consolidation = $db->cdp_registro();
}
$canPay = (bool) $consolidation;
if ($canPay && cdp_roleRankById((int) $userData->userlevel) < 2) {
    $uid = (int) $_SESSION['userid'];
    $agencyBranch = ((int) $userData->userlevel === 6) ? (int) cdp_getAgencyBranchIdForUser($userData->name_off) : 0;
    $canPay = (int) $consolidation->sender_id === $uid
        || (int) $consolidation->driver_id === $uid
        || ($agencyBranch > 0 && (int) $consolidation->agency === $agencyBranch);
}
if (!$canPay) {
    $errors['order_id'] = $lang['message_ajax_error2'];
}



if (empty($_POST['mode_pay']))

    $errors['mode_pay'] = 'Selected pay mode';

if (empty($_FILES['file_invoice']['name']))

    $errors['file_invoice'] = 'attach proof of payment';


if (!empty($_FILES['file_invoice']['name'])) {

    // assets/payment_packages/ is web-served: the client's file name and
    // extension were kept, so a .php/.html/.svg "proof" could be stored there.
    // Accept only PDF/images (checked by extension and content) and store
    // them under a random name with the allow-listed extension.
    $allowedProofTypes = [
        'pdf' => ['application/pdf', 'application/x-pdf'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'],
    ];
    $imageFileType = strtolower(pathinfo((string) $_FILES["file_invoice"]["name"], PATHINFO_EXTENSION));
    $proofTmp = (string) $_FILES["file_invoice"]["tmp_name"];
    $proofMime = '';
    if (function_exists('finfo_open') && is_uploaded_file($proofTmp)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $proofMime = (string) finfo_file($finfo, $proofTmp);
        finfo_close($finfo);
    }
    if (
        (int) $_FILES["file_invoice"]["error"] !== UPLOAD_ERR_OK
        || !isset($allowedProofTypes[$imageFileType])
        || !is_uploaded_file($proofTmp)
        || ($proofMime !== '' && !in_array($proofMime, $allowedProofTypes[$imageFileType], true))
    ) {
        $errors['file_invoice'] = 'The proof of payment must be a PDF or image file (JPG, PNG, GIF, WEBP).';
    }

    $target_dir = "../../assets/payment_packages/";
    $image_name = time() . "_" . bin2hex(random_bytes(8)) . "." . $imageFileType;
    $target_file = $target_dir . $image_name;
    $imageFileZise = $_FILES["file_invoice"]["size"];
}

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



    if (!empty($_FILES['file_invoice']['name'])) {

        if (move_uploaded_file($_FILES["file_invoice"]["tmp_name"], $target_file)) {
            $data['file_invoice'] = 'payment_packages/' . $image_name;
        }
    }

    $insert = cdp_updatePaymentConsolidate($data);



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
        $db->bind(':shipping_type', '2');
        $db->bind(':notification_date',  date("Y-m-d H:i:s"));

        $db->cdp_execute();


        $notification_id = $db->dbh->lastInsertId();


        $users_employees = cdp_getUsersAdminEmployees();

        foreach ($users_employees as $key) {

            cdp_insertNotificationsUsers($notification_id, $key->id);
        }

        cdp_insertNotificationsUsers($notification_id, $_SESSION['userid']);
    } else {

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