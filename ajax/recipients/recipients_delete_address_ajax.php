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
require_permission('view_recipients');

require_once("../../helpers/querys.php");

// Deleting is a write: only by POST with the CSRF token (callers already POST).
require_safe_write();

$id = (int) ($_POST['id'] ?? 0);

$errors = array();
$messages = array();

// 'view_recipients' is customer-facing, so anyone signed in could delete any recipient
// address by id. Load the address with its recipient: below staff rank it must be in the
// caller's own address book (agency users: also their agency's); staff stay in their agency.
require_once(__DIR__ . '/../../helpers/rbac.php');
$cdpAddrDb = new Conexion;
$cdpAddrDb->cdp_query("SELECT r.sender_id, COALESCE(r.agency_id, u.agency_id) AS agency
    FROM cdb_recipients_addresses a
    JOIN cdb_recipients r ON r.id = a.recipient_id
    LEFT JOIN cdb_users u ON u.id = r.sender_id
    WHERE a.id_addresses = :id LIMIT 1");
$cdpAddrDb->bind(':id', $id);
$cdpAddrRow = $cdpAddrDb->cdp_registro();
$cdpAddrRank = cdp_roleRankById((int) $user->userlevel);
if (!$cdpAddrRow) {
    $cdpAddrOk = false;
} elseif ($cdpAddrRank >= 2) {
    $cdpAddrOk = cdp_ajaxRowInAgencyScope($cdpAddrRow);
} else {
    $cdpAddrOk = (int) $cdpAddrRow->sender_id === (int) ($_SESSION['userid'] ?? 0);
    if (!$cdpAddrOk && $cdpAddrRank === 1) {
        $cdpAddrCtx = cdp_getAgencyContext();
        $cdpAddrOk = !empty($cdpAddrCtx['is_restricted']) && !empty($cdpAddrCtx['agency_id'])
            && (int) $cdpAddrRow->agency === (int) $cdpAddrCtx['agency_id'];
    }
}
if (!$cdpAddrOk) {
    $errors['not_found'] = $lang['message_ajax_error1'];
}

if (CDP_APP_MODE_DEMO === true) {
    // Si está en modo demo, mostrar un mensaje de error
    $errors['demo_mode'] = "This is a demo version, this action is not allowed.";
} else {
    if (empty($errors)) {
        // Verificar si la dirección está asociada a algún envío, paquete de cliente o consolidado
        $verifyExistsShipment = cdp_verifyReferentialIntegrity('cdb_add_order', 'receiver_address_id', $id);
        $verifyExistsCustomerPackages = cdp_verifyReferentialIntegrity('cdb_customers_packages', 'receiver_address_id', $id);
        $verifyExistsConsolidate = cdp_verifyReferentialIntegrity('cdb_consolidate', 'receiver_address_id', $id);

        if ($verifyExistsShipment || $verifyExistsCustomerPackages || $verifyExistsConsolidate) {
            // Si la dirección está asociada a algún envío, paquete de cliente o consolidado, mostrar un mensaje de error
            $errors['constrains'] = $lang['validate_field_ajax133'];
        } else {
            // Si no hay restricciones, eliminar la dirección
            $delete = cdp_deleteRecipientAddress($id);
            if ($delete) {
                $messages[] = $lang['message_ajax_success_delete'];
            } else {
                // Si hay un error crítico al eliminar la dirección, mostrar un mensaje de error
                $errors['critical_error'] = $lang['message_ajax_error1'];
            }
        }
    }
}

// Comprobar si hay errores
if (!empty($errors)) {
    // Si hay errores, devolver un JSON con los errores y mostrar SweetAlert
    echo json_encode([
        'success' => false,
        'errors' => $errors
    ]);
} else {
    // Si no hay errores, devolver un JSON con los mensajes de éxito y mostrar SweetAlert
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
}
