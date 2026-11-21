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
require_once(__DIR__ . '/../../helpers/rbac.php');

// Deleting is a write: only by POST with the CSRF token (every caller already POSTs), so a
// cross-site GET can no longer make a signed-in user delete an address.
require_safe_write();

$id = (int) ($_POST['id'] ?? 0);

$errors = array();
$messages = array();

// Load the address with its owner. Customers, drivers and agency users (rank < 2) may only
// delete their OWN saved addresses (the "My details" page); staff need view_client_list (plus
// edit_client for someone else's address), must
// outrank the owner and stay inside their agency.
$cdpAddrDb = new Conexion;
$cdpAddrDb->cdp_query('SELECT sa.user_id, u.userlevel, u.agency_id AS agency
    FROM cdb_senders_addresses sa
    LEFT JOIN cdb_users u ON u.id = sa.user_id
    WHERE sa.id_addresses = :id LIMIT 1');
$cdpAddrDb->bind(':id', $id);
$cdpAddrRow = $id > 0 ? $cdpAddrDb->cdp_registro() : null;
$cdpAddrSelf = (int) ($_SESSION['userid'] ?? 0);
$cdpAddrOwn = $cdpAddrRow && $cdpAddrSelf > 0 && (int) $cdpAddrRow->user_id === $cdpAddrSelf;
if (cdp_roleRankById((int) $user->userlevel) < 2) {
    $cdpAddrOk = $cdpAddrOwn;
} else {
    require_permission('view_client_list');
    // Removing ANOTHER account's address is a client edit: a view-only permission is not
    // enough (ajax/sl/customer_profile_ajax.php address_delete also requires edit_client).
    if (!$cdpAddrOwn) {
        require_permission('edit_client');
    }
    $cdpAddrOk = $cdpAddrOwn || ($cdpAddrRow && $cdpAddrRow->userlevel !== null
        && cdp_canManageUser($user, (int) $cdpAddrRow->userlevel)
        && cdp_ajaxRowInAgencyScope($cdpAddrRow));
}
if (!$cdpAddrOk) {
    $errors['not_found'] = $lang['message_ajax_error1'];
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

        $verifyExistsShipment = cdp_verifyReferentialIntegrity('cdb_add_order', 'sender_address_id', $id);
        $verifyExistsCustomerPackages = cdp_verifyReferentialIntegrity('cdb_customers_packages', 'sender_address_id', $id);
        $verifyExistsConsolidate = cdp_verifyReferentialIntegrity('cdb_consolidate', 'sender_address_id', $id);

        if ($verifyExistsShipment || $verifyExistsCustomerPackages || $verifyExistsConsolidate) {
            $errors['constrains'] = $lang['validate_field_ajax133'];
        } else {

            $delete = cdp_deleteCustomerAddress($id);
            if ($delete) {
                $messages[] = $lang['message_ajax_success_delete'];
            } else {
                $errors['critical_error'] = $lang['message_ajax_error1'];
            }
        }
    }

    if (!empty($errors)) {

        echo json_encode([
            'success' => false,
            'errors' => $errors
        ]);
    } else {

        echo json_encode([
            'success' => true,
            'messages' => $messages
        ]);
    }
}
