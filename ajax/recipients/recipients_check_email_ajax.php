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
require_once(__DIR__ . '/../../helpers/rbac.php');
require_login();
require_permission(['recipients_add', 'view_recipients']);

$db = new Conexion;

// Duplicate check for the recipient form. A customer, driver or agency user (rank < 2) is only
// told about their own address book: checking every account's recipients let any caller probe
// whether an e-mail address is a recipient of another customer. Staff keep the global check.
$recipientEmailTaken = function ($email) use ($user) {
    if (cdp_roleRankById((int) $user->userlevel) >= 2) {
        return cdp_recipientEmailExiste($email);
    }
    $db = new Conexion;
    $db->cdp_query("SELECT email FROM cdb_recipients WHERE email = :email AND sender_id = :uid LIMIT 1");
    $db->bind(':email', $email);
    $db->bind(':uid', (int) $user->uid);
    $db->cdp_execute();
    return $db->cdp_registro();
};


$response = []; // Inicializar la respuesta

// Verificar si se recibió un correo electrónico mediante POST
if (!empty($_POST['email'])) {
    // Sanitizar el correo electrónico recibido
    $email = cdp_sanitize($_POST['email']);

    // Verificar si el correo electrónico ya existe en la base de datos
    if ($recipientEmailTaken($email)) {
        $response['status'] = 'error';
        $response['message'] = $lang['validate_field_ajax126']; // Mensaje de correo electrónico duplicado
    } else {
        $response['status'] = 'success'; // Correo electrónico válido
    }
} else {
    $response['status'] = 'error';
    $response['message'] = $lang['messagesform46']; // Mensaje de error si no se recibió un correo electrónico
}

// Devolver la respuesta como JSON
header('Content-Type: application/json');
echo json_encode($response);

