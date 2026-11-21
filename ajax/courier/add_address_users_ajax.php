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

require_once("../../helpers/querys.php");
$user = new User;
$core = new Core;
$errors = array();

$_GET['sender'] = (int) ($_GET['sender'] ?? 0);
// view_shipment_list (checked above) is soft-passed for customers, drivers and agency users and the
// sender id comes from the query string: without this anyone signed in could add addresses or
// recipients to another customer's account. A customer may only write to their own account;
// drivers and agency users, and agency-restricted staff, only to customers of their agency.
require_once(__DIR__ . '/../../helpers/rbac.php');
$scopeRank = cdp_roleRankById((int) ($_SESSION['userlevel'] ?? 0));
if ($scopeRank === 0) {
    $_GET['sender'] = (int) ($_SESSION['userid'] ?? 0);
} else {
    $scopeDb = new Conexion;
    $scopeDb->cdp_query('SELECT id, agency_id AS agency FROM cdb_users WHERE id = :id LIMIT 1');
    $scopeDb->bind(':id', (int) $_GET['sender']);
    $scopeDb->cdp_execute();
    $scopeSender = $scopeDb->cdp_registro();
    $scopeOk = $scopeSender && cdp_ajaxRowInAgencyScope($scopeSender);
    if ($scopeOk && $scopeRank === 1) {
        $scopeCtx = cdp_getAgencyContext();
        $scopeOk = !empty($scopeCtx['agency_id']) && (int) $scopeSender->agency === (int) $scopeCtx['agency_id'];
    }
    if (!$scopeOk) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
        exit;
    }
}


if (empty($_POST['address_modal_user_address']))

    $errors['address'] =  $lang['validate_field_ajax100'];


if (empty($_POST['country_modal_user_address']))

    $errors['country'] =  $lang['validate_field_ajax102'];

if (empty($_POST['state_modal_user_address']))

    $errors['state'] = $lang['validate_field_ajax1022212'];

if (empty($_POST['city_modal_user_address']))

    $errors['city'] =  $lang['validate_field_ajax103'];

if (empty($_POST['postal_modal_user_address']))

    $errors['postal'] =  $lang['validate_field_ajax104'];


$response = array();


if (!isset($response['status'])) {

    $db->cdp_query("
                  INSERT INTO cdb_senders_addresses 
                  (
                    country,
                    state,
                    city,
                    address,
                    zip_code,
                    user_id                                
                  )
                  VALUES 
                  (
                      :country,
                      :state,
                      :city, 
                      :address,
                      :zip_code,
                      :user_id                            
                  )
                ");

    $db->bind(':country',  cdp_sanitize($_POST["country_modal_user_address"]));
    $db->bind(':state',  cdp_sanitize($_POST["state_modal_user_address"]));
    $db->bind(':city',  cdp_sanitize($_POST["city_modal_user_address"]));
    $db->bind(':address',  cdp_sanitize($_POST["address_modal_user_address"]));
    $db->bind(':zip_code',  cdp_sanitize($_POST["postal_modal_user_address"]));
    $db->bind(':user_id',  (int) $_GET["sender"]);

    $insert = $db->cdp_execute();

    $last_address_id = $db->dbh->lastInsertId();

    $db->cdp_query("SELECT * FROM cdb_senders_addresses where id_addresses= '" . $last_address_id . "'");
    $customer_address = $db->cdp_registro();


    if ($insert) {
        $response['status'] = 'success';
        $response['message'] = $lang['message_ajax_success_add'];
        $response['customer_address'] = $customer_address;
    } else {
        $response['status'] = 'error';
        $response['message'] = $lang['message_ajax_error1'];
    }
}

// Devuelve la respuesta como JSON
header('Content-type: application/json; charset=UTF-8');
echo json_encode($response);

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
    <div class="alert alert-info" id="success-alert">
        <p><span class="icon-info-sign"></span><i class="close icon-remove-circle"></i>
            <?php
            foreach ($messages as $message) {
                echo $message;
            }
            ?>
        </p>

        <script>
            $("#add_address_users_from_modal_shipments")[0].reset();
        </script>
    </div>

<?php
}
?>