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

ini_set('display_errors', 0);

require_once("../../loader.php");
require_once __DIR__ . '/../../helpers/sl_auth.php';
require_once("../../helpers/querys.php");
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");
require_once("../notify_sms/api_sms_service.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_pickup_list');

// Accepting a pickup prices it, sets its agency, driver and status and attaches files.
// The page that posts here (pickup_accept.php) needs 'pickup_accept', but this endpoint
// only asked for the customer-facing 'view_pickup_list', so a customer could rewrite any
// shipment by posting its order_id. Require the page's permission, never for customers,
// and keep drivers/agency users to pickups of their own agency or assigned to them.
require_once(__DIR__ . '/../../helpers/rbac.php');
$cdpAcceptRank = cdp_roleRankById((int) $user->userlevel);
if ($cdpAcceptRank < 1 || !$user->cdp_hasPermission('pickup_accept')) {
    _ajax_guard_send(403, ['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
}
$cdpAcceptDb = new Conexion;
$cdpAcceptDb->cdp_query("SELECT sender_id, agency, driver_id, recipient_type FROM cdb_add_order WHERE order_id = :id LIMIT 1");
$cdpAcceptDb->bind(':id', (int) ($_POST['order_id'] ?? 0));
$cdpAcceptDb->cdp_execute();
$cdpAcceptRow = $cdpAcceptDb->cdp_registro();
$cdpAcceptCtx = cdp_getAgencyContext();

// Canonical recipient type, written back once. cdp_sanitize() is not idempotent (stripslashes) and
// was applied again further down, so 'us\\er' could be checked as one type and loaded as another.
// 'customer' (SL customer pickups, ajax/sl/pickup_ajax.php) stores a cdb_users id and one of the
// sender's cdb_senders_addresses ids, exactly like 'user', so it takes the 'user' path (reading it
// from cdb_recipients picked an unrelated person). Not posted: the stored type, as before.
$cdpRawType = isset($_POST['recipient_type']) && $_POST['recipient_type'] !== ''
    ? cdp_sanitize($_POST['recipient_type'])
    : (string) ($cdpAcceptRow->recipient_type ?? 'recipient');
$cdpScopeType = in_array($cdpRawType, ['user', 'customer'], true) ? 'user' : 'recipient';
$_POST['recipient_type'] = $cdpScopeType;

$cdpCheckParties = false;
$cdpScopeAgencies = [];
if ($cdpAcceptRank < 2) {
    $cdpOwnAgency = !empty($cdpAcceptCtx['agency_id']) && $cdpAcceptRow && (int) $cdpAcceptRow->agency === (int) $cdpAcceptCtx['agency_id'];
    $cdpOwnDriver = $cdpAcceptRow && (int) $cdpAcceptRow->driver_id > 0 && (int) $cdpAcceptRow->driver_id === (int) ($_SESSION['userid'] ?? 0);
    if (!$cdpOwnAgency && !$cdpOwnDriver) {
        _ajax_guard_send(403, ['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
    }
    if ($cdpOwnAgency) {
        $_POST['agency'] = (int) $cdpAcceptCtx['agency_id']; // cannot move it to another agency
        $cdpScopeAgencies = [(int) $cdpAcceptCtx['agency_id']];
    } elseif ((int) $cdpAcceptRow->agency > 0) {
        // Matched only as its driver: the pickup stays with its agency.
        $_POST['agency'] = (int) $cdpAcceptRow->agency;
    } else {
        // No agency on the row yet: accept a posted agency only if it is a real branch office.
        $cdpPostedAgency = (int) ($_POST['agency'] ?? 0);
        $cdpAgencyExists = false;
        if ($cdpPostedAgency > 0) {
            $cdpAcceptDb->cdp_query("SELECT id FROM cdb_branchoffices WHERE id = :id LIMIT 1");
            $cdpAcceptDb->bind(':id', $cdpPostedAgency);
            $cdpAcceptDb->cdp_execute();
            $cdpAgencyExists = (bool) $cdpAcceptDb->cdp_registro();
        }
        $_POST['agency'] = $cdpAgencyExists ? $cdpPostedAgency : 0;
    }
    // The scope check covers the row as stored, but the update writes sender and driver from the
    // request: keep the stored sender (else the pickup moves to another customer and into their
    // notifications) and the stored driver unless the caller may assign drivers.
    $_POST['sender_id'] = (int) $cdpAcceptRow->sender_id;
    if (!$user->cdp_hasPermission('assign_drivers')) {
        $_POST['driver_id'] = (int) $cdpAcceptRow->driver_id;
    }
    $cdpCheckParties = true;
} elseif (!empty($cdpAcceptCtx['is_restricted'])) {
    // Agency-restricted staff (userlevel 2/4 tied to an agency) could accept and re-price any
    // agency's pickup by order_id. The stored row must be in their agency, the agency written stays
    // theirs, and a changed sender must be a customer of that agency (as accept_courier_ajax.php).
    $cdpAgencyId = (int) ($cdpAcceptCtx['agency_id'] ?? 0);
    $cdpScopeOk = $cdpAcceptRow && $cdpAgencyId > 0 && cdp_ajaxRowInAgencyScope($cdpAcceptRow);
    $cdpPostedSender = (int) ($_POST['sender_id'] ?? 0);
    if ($cdpScopeOk && $cdpPostedSender !== (int) $cdpAcceptRow->sender_id) {
        $cdpAcceptDb->cdp_query("SELECT agency_id, userlevel FROM cdb_users WHERE id = :id LIMIT 1");
        $cdpAcceptDb->bind(':id', $cdpPostedSender);
        $cdpAcceptDb->cdp_execute();
        $cdpSenderRow = $cdpAcceptDb->cdp_registro();
        $cdpScopeOk = $cdpSenderRow && (int) $cdpSenderRow->agency_id === $cdpAgencyId
            && cdp_roleRankById((int) $cdpSenderRow->userlevel) === 0;
    }
    if (!$cdpScopeOk) {
        _ajax_guard_send(403, ['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
    }
    $_POST['agency'] = $cdpAgencyId;
    $cdpScopeAgencies = [$cdpAgencyId];
    $cdpCheckParties = true;
}

if ($cdpCheckParties) {
    // The recipient, the sender address and the recipient address must belong to that sender (or
    // to its recipient), so another customer's contact or address cannot be attached to, and
    // printed on, this pickup. A newly named driver must be an active driver account.
    $cdpSender = (int) ($_POST['sender_id'] ?? 0);
    $cdpRecipient = (int) ($_POST['recipient_id'] ?? 0);
    if ($cdpScopeType === 'user') {
        $cdpRecipientOk = $cdpRecipient === $cdpSender;
    } else {
        $cdpAcceptDb->cdp_query("SELECT sender_id, agency_id FROM cdb_recipients WHERE id = :id LIMIT 1");
        $cdpAcceptDb->bind(':id', $cdpRecipient);
        $cdpAcceptDb->cdp_execute();
        $cdpRec = $cdpAcceptDb->cdp_registro();
        $cdpRecipientOk = $cdpRec && (
            (int) $cdpRec->sender_id === $cdpSender
            || in_array((int) ($cdpRec->agency_id ?? 0), $cdpScopeAgencies, true)
        );
    }
    $cdpPartiesOk = true;
    $cdpAddrId = (int) ($_POST['sender_address_id'] ?? 0);
    if ($cdpAddrId > 0) {
        $cdpAcceptDb->cdp_query("SELECT user_id FROM cdb_senders_addresses WHERE id_addresses = :id LIMIT 1");
        $cdpAcceptDb->bind(':id', $cdpAddrId);
        $cdpAcceptDb->cdp_execute();
        $cdpAddr = $cdpAcceptDb->cdp_registro();
        $cdpPartiesOk = $cdpAddr && (int) $cdpAddr->user_id === $cdpSender;
    }
    // For type 'user' the sender doubles as recipient, so the address is one of the sender's.
    $cdpRAddrId = (int) ($_POST['recipient_address_id'] ?? 0);
    if ($cdpRAddrId > 0) {
        if ($cdpScopeType === 'user') {
            $cdpAcceptDb->cdp_query("SELECT user_id AS owner_id FROM cdb_senders_addresses WHERE id_addresses = :id LIMIT 1");
            $cdpRAddrOwner = $cdpSender;
        } else {
            $cdpAcceptDb->cdp_query("SELECT recipient_id AS owner_id FROM cdb_recipients_addresses WHERE id_addresses = :id LIMIT 1");
            $cdpRAddrOwner = $cdpRecipient;
        }
        $cdpAcceptDb->bind(':id', $cdpRAddrId);
        $cdpAcceptDb->cdp_execute();
        $cdpRAddr = $cdpAcceptDb->cdp_registro();
        if (!$cdpRAddr || (int) $cdpRAddr->owner_id !== $cdpRAddrOwner) {
            $cdpPartiesOk = false;
        }
    }
    $cdpDriver = (int) ($_POST['driver_id'] ?? 0);
    if ($cdpDriver > 0 && $cdpDriver !== (int) $cdpAcceptRow->driver_id) {
        $cdpAcceptDb->cdp_query("SELECT id FROM cdb_users WHERE id = :id AND userlevel = 3 AND active = 1 LIMIT 1");
        $cdpAcceptDb->bind(':id', $cdpDriver);
        $cdpAcceptDb->cdp_execute();
        if (!$cdpAcceptDb->cdp_registro()) {
            $cdpPartiesOk = false;
        }
    }
    if (($cdpRecipient > 0 && !$cdpRecipientOk) || !$cdpPartiesOk) {
        _ajax_guard_send(403, ['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
    }
}

$user = new User;
$core = new Core;
$errors = array();


if (empty($_POST['sender_id']))

    $errors['sender_id'] = $lang['validate_field_ajax150'];

if (empty($_POST['sender_address_id']))

    $errors['sender_address_id'] = $lang['validate_field_ajax145'];

if (empty($_POST['recipient_id']))

    $errors['recipient_id'] = $lang['validate_field_ajax146'];

if (empty($_POST['recipient_address_id']))

    $errors['recipient_address_id'] = $lang['validate_field_ajax147'];

if (empty($_POST['agency']))

    $errors['agency'] = $lang['validate_field_ajax148'];

if (empty($_POST['origin_off']))

    $errors['origin_off'] = $lang['validate_field_ajax149'];

if (empty($_POST['order_no']))

    $errors['order_no'] = $lang['validate_field_ajax150'];

// order_item_category falls back to the admin default when not posted.

if (empty($_POST['order_package']))

    $errors['order_package'] = $lang['validate_field_ajax152'];

if (empty($_POST['order_courier']))

    $errors['order_courier'] = $lang['validate_field_ajax153'];

// order_service_options falls back to the admin default when not posted.

if (empty($_POST['order_deli_time']))
    $errors['order_deli_time'] = $lang['validate_field_ajax155'];


if (empty($_POST['status_courier']))
    $errors['status_courier'] = $lang['validate_field_ajax157'];

if (empty($_POST['order_payment_method']))
    $errors['order_payment_method'] = $lang['validate_field_ajax158'];

// Delivered / Picked up only with the owner's verified pickup code
// (helpers/pickup_code.php).
require_once(__DIR__ . '/../../helpers/pickup_code.php');
$cdpPickupGate = cdp_pickupCodeGate('air', [intval($_POST['order_id'] ?? 0)], intval($_POST['status_courier'] ?? 0));
if (!$cdpPickupGate['ok']) {
    $errors['pickup_code'] = $cdpPickupGate['message'];
}

// Attachments are stored in order_files/, which the web server serves. The stored
// name kept the client's file name and extension, so a .php or .html file could be
// stored there. Check each file's real type (from its contents) before anything is
// saved, so a bad file is refused instead of leaving a half-saved pickup.
require_once(__DIR__ . '/../../helpers/uploads.php');
if (isset($_FILES['filesMultiple']['tmp_name']) && is_array($_FILES['filesMultiple']['tmp_name'])) {
    $cdpUploadKinds = cdp_uploadKinds('docs');
    $cdpSkipFiles = !empty($_POST['deleted_file_ids']) ? explode(",", (string) $_POST['deleted_file_ids']) : array();
    foreach ($_FILES['filesMultiple']['tmp_name'] as $key => $tmp) {
        if ((string) $tmp === '' || in_array($key, $cdpSkipFiles) || (int) ($_FILES['filesMultiple']['size'][$key] ?? 0) <= 0) {
            continue;
        }
        if (!isset($cdpUploadKinds[cdp_uploadDetect((string) $tmp)])) {
            $errors['filesMultiple'] = 'Attachments must be a photo or a PDF.';
            break;
        }
    }
}

if (empty($errors)) {

    $settings = cdp_getSettingsCourier();

    $min_cost_tax = $core->min_cost_tax;
    $min_cost_declared_tax = $core->min_cost_declared_tax;

    $sale_date   = date("Y-m-d H:i:s");
    $payment_methods = cdp_getPaymentMethodCourier($_POST["order_payment_method"]);
    $days = $payment_methods->days;
    $days = intval($days);
    $due_date = cdp_sumardias($sale_date, $days);

    // Never marked paid on creation: payments are recorded by hand afterwards.

    $status_invoice = 2;

    $sender_data = cdp_getSenderCourier(intval($_POST["sender_id"]));
    // recipient_type: posted by the form, else the shipment's stored one.
    $cdpRecipType = cdp_sanitize($_POST['recipient_type'] ?? '');
    if ($cdpRecipType === '') {
        $cdpStored = cdp_getCourier(intval($_POST['order_id'] ?? 0));
        $cdpRecipType = (string) ($cdpStored->recipient_type ?? 'recipient');
    }
    // recipient_type 'user': recipient_id is a cdb_users id (a registered customer),
    // not a cdb_recipients id - reading cdb_recipients texted an unrelated person.
    $receiver_data = ($cdpRecipType === 'user')
        ? cdp_getSenderCourier(intval($_POST["recipient_id"]))
        : cdp_getRecipientCourier(intval($_POST["recipient_id"]));
    $tariff_mode = 1; // Tariff table pricing removed: always manual pricing.

    $dataShipment = array(
        'order_id' =>  cdp_sanitize(intval($_POST["order_id"])),
        'sender_id' =>  cdp_sanitize(intval($_POST["sender_id"])),
        'recipient_id' =>  cdp_sanitize(intval($_POST["recipient_id"])),
        'sender_address_id' =>  cdp_sanitize(intval($_POST["sender_address_id"])),
        'recipient_address_id' =>  cdp_sanitize(intval($_POST["recipient_address_id"])),
        'agency' =>  cdp_sanitize(intval($_POST["agency"])),
        'origin_off' =>  cdp_sanitize(intval($_POST["origin_off"])),
        'order_package' =>  cdp_sanitize(intval($_POST["order_package"])),
        'driver_id' =>  cdp_sanitize(intval($_POST["driver_id"])),
        'order_item_category' =>  cdp_sanitize(intval($_POST["order_item_category"])),
        'order_courier' =>  cdp_sanitize(intval($_POST["order_courier"])),
        'order_service_options' => (intval($_POST["order_service_options"] ?? 0) > 0) ? intval($_POST["order_service_options"]) : (int) (cdp_getInfoShipDefault()->service_default4 ?? 0),
        'order_deli_time' =>  cdp_sanitize(intval($_POST["order_deli_time"])),
        'order_payment_method' =>  cdp_sanitize(intval($_POST["order_payment_method"])),
        'status_courier' =>  cdp_sanitize(intval($_POST["status_courier"])),
        'due_date' =>  $due_date,
        'status_invoice' =>  $status_invoice,
        'order_incomplete' => '1',
        'manual_tariff' =>  $tariff_mode,

    );

    $updateShip = cdp_updateCourierShipmentFromCustomer($dataShipment);
    if (cdp_pickupCodeGatedStatus(intval($_POST['status_courier'] ?? 0))) {
        cdp_pickupCodeConsume('air', [intval($_POST['order_id'] ?? 0)], (int) ($_SESSION['userid'] ?? 0));
    }

    $shipment_id =  cdp_sanitize(intval($_POST["order_id"]));

    cdp_insertPackageTracking($shipment_id, $_SESSION['userid'], cdp_sanitize($_POST['tracking_number']), cdp_sanitize($_POST['estimated_eta']));

    if ($updateShip) {

        if (isset($_POST["packages"])) {

            cdp_deleteCourierPackages($shipment_id);
            $packages = json_decode($_POST['packages']);
            $meter = cdp_sanitize($_POST["meter"]);

            $sumador_total = 0;
            $sumador_valor_declarado = 0;
            $max_fixed_charge = 0;
            $sumador_libras = 0;
            $sumador_volumetric = 0;

            $precio_total = 0;
            $total_impuesto = 0;
            $total_descuento = 0;
            $total_seguro = 0;
            $total_peso = 0;
            $total_impuesto_aduanero = 0;
            $total_valor_declarado = 0;

            $tariffs_value = 0; // Retired charge
            $declared_value_tax = 0; // Retired charge
            $insurance_value = $_POST["insurance_value"];
            $tax_value = 0; // Retired charge
            $discount_value = $_POST["discount_value"];
            $reexpedicion_value = $_POST["reexpedicion_value"];
            $price_lb = $_POST["price_lb"];
            $insured_value = $_POST["insured_value"];

            foreach ($packages as $package) {

                $dataAddresses = array(
                    'order_id' =>  $shipment_id,
                    'qty' =>  $package->qty,
                    'description' =>  $package->description,
                    'length' =>  $package->length,
                    'width' =>  $package->width,
                    'height' =>  $package->height,
                    'weight' =>  $package->weight,
                    'declared_value' =>  $package->declared_value,
                    'fixed_value' =>  $package->fixed_value,
                );

                cdp_insertCourierShipmentPackages($dataAddresses);

                // calculate weight columetric box size
                $total_metric = $package->length * $package->width * $package->height / $meter;
                $weight = $package->weight;


                $sumador_volumetric += $total_metric;
                $sumador_libras += $weight;
                // calculate weight x price
                if ($sumador_libras > $sumador_volumetric) {
                    $calculate_weight = $sumador_libras;
                } else {
                    $calculate_weight = $sumador_volumetric;
                }
                $sumador_libras = floatval($sumador_libras);
                $sumador_volumetric = floatval($sumador_volumetric);
                $calculate_weight = floatval($calculate_weight);

                $sumador_libras = round($sumador_libras, 2);
                $sumador_volumetric = round($sumador_volumetric, 2);
                $calculate_weight = round($calculate_weight, 2);

                $sumador_valor_declarado += $package->declared_value;
                $max_fixed_charge += $package->fixed_value;


                if ($tariff_mode) {
                    $sumador_total = $calculate_weight * $price_lb; // Calculate total based on weight and price per lb
                } else {
                    $sumador_total = $price_lb;
                }

                if ($sumador_total > $min_cost_tax) {
                    $total_impuesto = $sumador_total * $tax_value / 100;
                }

                if ($sumador_valor_declarado > $min_cost_declared_tax) {
                    $total_valor_declarado = $sumador_valor_declarado * $declared_value_tax / 100;
                }
            }


            $total_descuento = $sumador_total * $discount_value / 100;
            $total_peso = $sumador_libras + $sumador_volumetric;
            $total_seguro = sl_insuranceCharge((float) $insured_value, (float) $insurance_value);
            $total_impuesto_aduanero = ($total_peso * $tariffs_value) / 100;
            $total_envio = ($sumador_total - $total_descuento) + $total_seguro + $total_impuesto + $total_impuesto_aduanero + $total_valor_declarado + $max_fixed_charge + $reexpedicion_value;
        }

        $dataShipmentUpdateTotals = array(
            'order_id' =>  $shipment_id,
            'value_weight' =>  floatval($price_lb),
            'sub_total' =>  floatval($sumador_total),
            'tax_discount' =>  floatval($discount_value),
            'total_insured_value' => floatval($insured_value),
            'tax_insurance_value' => floatval($insurance_value),
            'tax_custom_tariffis_value' => floatval($tariffs_value),
            'tax_value' => floatval($tax_value),
            'declared_value' =>  floatval($declared_value_tax),
            'total_reexp' =>  floatval($reexpedicion_value),
            'total_declared_value' =>  floatval($sumador_valor_declarado),
            'total_fixed_value' =>  floatval($max_fixed_charge),
            'total_tax_discount' =>  floatval($total_descuento),
            'total_tax_insurance' =>  floatval($total_seguro),
            'total_tax_custom_tariffis' =>  floatval($total_impuesto_aduanero),
            'total_tax' =>  floatval($total_impuesto),
            'total_weight' =>  floatval($total_peso),
            'total_order' =>  floatval($total_envio),
        );

        $update = cdp_updateCourierShipmentTotals($dataShipmentUpdateTotals);
        $shipment = cdp_getCourier($shipment_id);
        $order_track =  $shipment->order_prefix . $shipment->order_no;

        if (isset($_FILES['filesMultiple']) && count($_FILES['filesMultiple']['name']) > 0 && $_FILES['filesMultiple']['tmp_name'][0] != '') {

            $target_dir = __DIR__ . "/../../order_files";
            $deleted_file_ids = array();

            if (isset($_POST['deleted_file_ids']) && !empty($_POST['deleted_file_ids'])) {
                $deleted_file_ids = explode(",", $_POST['deleted_file_ids']);
            }

            foreach ($_FILES["filesMultiple"]['tmp_name'] as $key => $tmp_name) {

                if (!in_array($key, $deleted_file_ids)) {
                    // Our own name with the extension of the detected type (helpers/uploads.php),
                    // never the client's file name: order_files/ is served by the web server.
                    $cdpUp = cdp_uploadStore(cdp_uploadPick($_FILES['filesMultiple'], (int) $key), $target_dir, $order_track);
                    if ($cdpUp['ok']) {
                        $image_name = $cdpUp['name'];
                        cdp_insertOrdersFiles($shipment_id, "order_files/" . $image_name, $image_name, date("Y-m-d H:i:s"), '0', pathinfo($image_name, PATHINFO_EXTENSION));
                    }
                }
            }
        }


        $fullshipment = $shipment->order_prefix . $shipment->order_no;
        // Obtener el ID del estado del envio desde el POST SMS
        $name_status = cdp_getCourierstatusApi(intval($_POST["status_courier"]));
        $add_status = $name_status->mod_style;
        $app_url = rtrim((string) $settings->site_url, '/') . '/track.php?order_track=' . $fullshipment;


        if (!empty($_POST['notify_whatsapp_sender']) && intval($_POST['notify_whatsapp_sender']) === 1) {
            try {
                require_once("../notify_whatsapp/api_whatsapp_service_v2.php");

                $db_sender = new Conexion;
                $db_sender->cdp_query("SELECT * FROM cdb_users WHERE id = :id");
                $db_sender->bind(':id', intval($_POST['sender_id']));
                $db_sender->cdp_execute();
                $sender_data = $db_sender->cdp_registro();

                if ($sender_data && !empty($sender_data->phone)) {
                    // Shared template-4 path: defaults the service, adds
                    // weight/contents/carrier-tracking/ETA, valid track URL.
                    // The accept form has no "Original Total Weight" input, so
                    // the order id is what lets the weight line resolve.
                    $wa_result = cdp_sendShipmentRegisteredWhatsApp($sender_data, $fullshipment, array(
                        'courier'  => intval($dataShipment['order_courier'] ?? 0),
                        'service'  => intval($dataShipment['order_service_options'] ?? 0),
                        'delitime' => intval($dataShipment['order_deli_time'] ?? 0),
                        'office'   => intval($dataShipment['origin_off'] ?? 0),
                    ), cdp_wa_buildShipmentExtraLines($shipment_id));

                    if (empty($wa_result['success'])) {
                        error_log("WhatsApp notification failed for shipment {$fullshipment}: " . ($wa_result['message'] ?? ''));
                    }
                }
            } catch (Exception $e) {
                error_log('WhatsApp notification error for shipment ' . ($fullshipment ?? 'unknown') . ': ' . $e->getMessage());
            }
        }

        // NOTIFY SMS CLICKSEND API
        $templatessender = 11;
        $templatesreceiver = 12;

        // Obtener el estado de las casillas de verificación
        $notify_sms_sender = isset($_POST['notify_sms_sender']) && $_POST['notify_sms_sender'] == 1;
        $notify_sms_receiver = isset($_POST['notify_sms_receiver']) && $_POST['notify_sms_receiver'] == 1;

        // Generar cuerpo del SMS para el remitente
        $newbodyS_sender = generateSMSBody($sender_data, $fullshipment, $add_status, $app_url, $templatessender);

        // Llamar a la función para enviar la notificación SMS al remitente
        sendNotificationSMS($sender_data, $newbodyS_sender, $notify_sms_sender);

        // Generar cuerpo del SMS para el receptor
        $newbodyS_receiver = generateSMSBody($receiver_data, $fullshipment, $add_status, $app_url, $templatesreceiver);

        // Llamar a la función para enviar la notificación SMS al receptor
        sendNotificationSMS($receiver_data, $newbodyS_receiver, $notify_sms_receiver);


        $accept_data = cdp_getacceptCourier(intval($_POST["driver_id"]));

        $dataHistory = array(
            'user_id' =>  $_SESSION['userid'],
            'order_id' =>  $shipment_id,
            'action' =>  $lang['notification_shipment088'] . ' ' . $accept_data->fname . ' ' . $accept_data->lname,
            'date_history' =>  cdp_sanitize(date("Y-m-d H:i:s")),
            'order_track' => $order_track
        );

        //INSERT HISTORY USER
        cdp_insertCourierShipmentUserHistory(
            $dataHistory
        );
        cdp_deleteCourierAddress($shipment_id);

        $sender_address_data = cdp_getSenderAddress(intval($_POST["sender_address_id"]));
        $shipment = cdp_getCourier($shipment_id);
        $apck_recipient_type = cdp_sanitize($_POST['recipient_type'] ?? ($shipment->recipient_type ?? 'recipient'));
        $apck_recip_addr = ($apck_recipient_type === 'user')
            ? cdp_getSenderAddress(intval($_POST["recipient_address_id"]))
            : cdp_getRecipientAddress(intval($_POST["recipient_address_id"]));

        // SAVE ADDRESS FOR Shipments
        $dataAddresses = array(
            'order_id'           => $shipment_id,
            'order_track'        => $shipment->order_prefix . $shipment->order_no,
            'sender_country'     => $sender_address_data ? cdp_resolveAddressName($sender_address_data->country, 'cdp_getCountry', $sender_address_data->legacy_country ?? '') : '',
            'sender_state'       => $sender_address_data ? cdp_resolveAddressName($sender_address_data->state,   'cdp_getState',   $sender_address_data->legacy_state   ?? '') : '',
            'sender_city'        => $sender_address_data ? cdp_resolveAddressName($sender_address_data->city,    'cdp_getCity',    $sender_address_data->legacy_city    ?? '') : '',
            'sender_zip_code'    => $sender_address_data ? ($sender_address_data->zip_code ?? '') : '',
            'sender_address'     => $sender_address_data ? ($sender_address_data->address  ?? '') : '',
            'recipient_country'  => $apck_recip_addr ? cdp_resolveAddressName($apck_recip_addr->country ?? '', 'cdp_getCountry', $apck_recip_addr->legacy_country ?? '') : '',
            'recipient_state'    => $apck_recip_addr ? cdp_resolveAddressName($apck_recip_addr->state   ?? '', 'cdp_getState',   $apck_recip_addr->legacy_state   ?? '') : '',
            'recipient_city'     => $apck_recip_addr ? cdp_resolveAddressName($apck_recip_addr->city    ?? '', 'cdp_getCity',    $apck_recip_addr->legacy_city    ?? '') : '',
            'recipient_zip_code' => $apck_recip_addr ? ($apck_recip_addr->zip_code ?? '') : '',
            'recipient_address'  => $apck_recip_addr ? ($apck_recip_addr->address  ?? '') : '',
        );

        cdp_insertCourierShipmentAddresses($dataAddresses);



        $messages[] = $lang['message_ajax_success_add_pickup'];
    } else {
        $errors['critical_error'] = $lang['message_ajax_error2'];
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
        'messages' => $messages,
        'shipment_id' => $shipment_id,
    ]);
}
