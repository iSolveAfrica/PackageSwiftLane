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
// Sends an arbitrary WhatsApp body from the company account to the users / order owners named in the
// request. view_dashboard is customer-facing, so it let any customer use this; gate it like the
// push_notifications pages and refuse every role below staff.
require_permission('push_notifications');
require_staff();

require_once("../../helpers/querys.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");


$db = new Conexion;
session_start();
$errors = array();

if (empty(trim($_POST['template_whatsapp_description'])))
    $errors['username'] = 'La descripción es requerida';


if (empty($errors)) {
    $template_whatsapp = intval($_POST['template_whatsapp']);
    $template_whatsapp_body = trim($_POST['template_whatsapp_description']);

    $data = json_decode($_POST['checked_data']);


    foreach ($data as $key) {


        $package = getCustomerPackageForOrderNo($key);
        if ($package) {
            $sender = getSenderCourier($package->sender_id);
            $personal_body = cdp_personalizeWhatsAppBody($template_whatsapp_body, $sender, $package->order_prefix . $package->order_no);
            $notification_result = sendNotificationWhatsApp_v2($sender, $personal_body);

            if ($notification_result['success']) {
                $messages = $notification_result['message'];
            } else {
                $errors['notification_error'] = $notification_result['message'];
            }
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
