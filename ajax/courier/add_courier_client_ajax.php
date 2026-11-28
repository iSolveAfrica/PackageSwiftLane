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
require_once(__DIR__ . '/../../helpers/sl_auth.php');
require_once(__DIR__ . '/../../helpers/unique_ids.php');

require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
notify_after_response(); // email + WhatsApp go out after the response (helpers/after_response.php)
require_permission('add_shipment');

// A customer books in their own name only. The form shows the sender as
// read-only, but the posted sender_id was trusted: a customer could book a
// shipment under another customer, who then got its messages. For customer
// roles the sender is the session's user and the sender address must be
// one of theirs. Staff keep choosing the sender.
// Rank 0 follows the parent_role_id chain, so a department role created under Customer (own
// is_client flag 0) is pinned too; cdp_roleIsClient() reads only the role's own flag.
require_once(__DIR__ . '/../../helpers/rbac.php');
require_once("../../helpers/querys.php");
// Message source for the message log and the transport gate (helpers/comms_gate.php),
// named here rather than read from this endpoint's file name.
require_once(__DIR__ . '/../../helpers/comms_gate.php');
if (function_exists('cdp_msgSetContext')) {
    cdp_msgSetContext(['source' => 'shipment_registered', 'source_label' => 'Shipment Registered', 'entity_type' => 'shipment']);
}
$scopeLevel = (int) ($_SESSION['userlevel'] ?? 0);
$scopeRank = cdp_roleRankById($scopeLevel);
$scopeIsClient = $scopeRank === 0 || cdp_roleIsClient($scopeLevel);
if ($scopeIsClient) {
    $_POST['sender_id'] = (int) ($_SESSION['userid'] ?? 0);
    $cdpAddrDb = new Conexion;
    $cdpAddrDb->cdp_query("SELECT user_id FROM cdb_senders_addresses WHERE id_addresses = :a LIMIT 1");
    $cdpAddrDb->bind(':a', (int) ($_POST['sender_address_id'] ?? 0));
    $cdpAddr = $cdpAddrDb->cdp_registro();
    if ($cdpAddr && (int) $cdpAddr->user_id !== (int) $_POST['sender_id']) {
        $_POST['sender_address_id'] = '';   // fails the required-address check below
    }
}

// Canonical recipient type. cdp_sanitize() is not idempotent (stripslashes) and is applied again
// further down, so a value like 'us\\er' could pass a check as a cdb_recipients id and then be
// loaded as 'user'. Further down only 'user' vs anything else matters, so this keeps behaviour.
$scopeType = (cdp_sanitize($_POST['recipient_type'] ?? 'recipient') === 'user') ? 'user' : 'recipient';
$_POST['recipient_type'] = $scopeType;

// Agency-restricted callers book for their own agency and its customers only, as
// add_courier_ajax.php enforces; the posted agency and sender were trusted here.
// cdp_getAgencyContext() only marks seed roles 6 and 2/4-with-agency as restricted, so agency users
// on a custom role (rank 1, other ids) skipped this check and could book under any customer and
// agency; they are scoped here too. A restricted context with an agency keeps that agency (what the
// sender picker offers); otherwise the agency is resolved as cdp_ajaxListScopeSql() does (name_off
// branch, then users.agency_id). Drivers hold 'add_shipment' in the seed Driver role on purpose: they
// are not agency-scoped, keep the parties check below (rank < 2) and the sender must be a customer.
$scopeCtx = cdp_getAgencyContext();
$scopeAgencies = [];
$scopeAgencyScoped = !$scopeIsClient && !empty($scopeCtx['is_restricted']);
$scopeAgencyId = (int) ($scopeCtx['agency_id'] ?? 0);
$scopeIsDriver = !$scopeIsClient && $scopeRank === 1 && ($scopeLevel === 3 || cdp_roleHasFlag($scopeLevel, 'is_driver'));
if (!$scopeIsClient && $scopeRank === 1 && !$scopeIsDriver && !($scopeAgencyScoped && $scopeAgencyId > 0)) {
    $scopeMeDb = new Conexion;
    $scopeMeDb->cdp_query("SELECT name_off, agency_id FROM cdb_users WHERE id = :id LIMIT 1");
    $scopeMeDb->bind(':id', (int) ($_SESSION['userid'] ?? 0));
    $scopeMeDb->cdp_execute();
    $scopeMe = $scopeMeDb->cdp_registro();
    $scopeAgencyId = $scopeMe ? (int) cdp_getAgencyBranchIdForUser($scopeMe->name_off ?? '') : 0;
    if ($scopeAgencyId <= 0 && $scopeMe) {
        $scopeAgencyId = (int) ($scopeMe->agency_id ?? 0);
    }
    if ($scopeAgencyId <= 0) {
        _ajax_guard_send(403, ['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
    }
    $scopeAgencyScoped = true;
}
// Write the int back: "00"/"0x" are not empty() but cast to 0, which skipped the checks keyed on
// the sender while still passing the empty() validation below.
if ($scopeRank < 2 || $scopeIsClient || $scopeAgencyScoped) {
    $_POST['sender_id'] = (int) ($_POST['sender_id'] ?? 0);
}
// Drivers are not agency-scoped, so the sender must at least be a customer account (rank 0).
if ($scopeIsDriver && !empty($_POST['sender_id'])) {
    $scopeDb = new Conexion;
    $scopeDb->cdp_query("SELECT userlevel FROM cdb_users WHERE id = :id LIMIT 1");
    $scopeDb->bind(':id', (int) $_POST['sender_id']);
    $scopeDb->cdp_execute();
    $scopeSenderRow = $scopeDb->cdp_registro();
    if (!$scopeSenderRow || cdp_roleRankById((int) $scopeSenderRow->userlevel) !== 0) {
        _ajax_guard_send(403, ['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
    }
}
if ($scopeAgencyScoped) {
    $scopeSenderOk = false;
    if ($scopeAgencyId > 0) {
        // The sender must be a customer (rank 0, any Customer-derived role) of this agency, not a
        // staff or driver account that carries the agency_id (edit/accept check the same).
        $scopeDb = new Conexion;
        $scopeDb->cdp_query("SELECT agency_id, userlevel FROM cdb_users WHERE id = :id LIMIT 1");
        $scopeDb->bind(':id', (int) ($_POST['sender_id'] ?? 0));
        $scopeDb->cdp_execute();
        $scopeSenderRow = $scopeDb->cdp_registro();
        $scopeSenderOk = $scopeSenderRow && (int) $scopeSenderRow->agency_id === $scopeAgencyId
            && cdp_roleRankById((int) $scopeSenderRow->userlevel) === 0;
    }
    if (!$scopeSenderOk && !empty($_POST['sender_id'])) {
        _ajax_guard_send(403, ['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
    }
    $_POST['agency'] = $scopeAgencyId;
    $scopeAgencies = [$scopeAgencyId];
}

// The recipient, the sender address and the recipient address came from POST unchecked, so a
// customer could put another customer's contact or address (or, with type 'user', any registered
// user) on the label and in the notifications. They must belong to the sender, as offered by
// ajax/select2_recipient.php and select2_recipient_addresses.php (same rule as add_courier_ajax.php).
// Skipped without a sender: the 'sender_id' validation below then refuses the request.
if (($scopeRank < 2 || $scopeIsClient || $scopeAgencyScoped) && !empty($_POST['sender_id'])) {
    $scopeDb = new Conexion;
    $scopeSender = (int) $_POST['sender_id'];
    $scopeRecipient = (int) ($_POST['recipient_id'] ?? 0);
    if ($scopeType === 'user') {
        $scopeRecipientOk = $scopeRecipient === $scopeSender;
    } else {
        $scopeDb->cdp_query("SELECT sender_id, agency_id FROM cdb_recipients WHERE id = :id LIMIT 1");
        $scopeDb->bind(':id', $scopeRecipient);
        $scopeDb->cdp_execute();
        $scopeRec = $scopeDb->cdp_registro();
        $scopeRecipientOk = $scopeRec && (
            (int) $scopeRec->sender_id === $scopeSender
            || in_array((int) ($scopeRec->agency_id ?? 0), $scopeAgencies, true)
        );
    }
    $scopeAddrOk = true;
    $scopeAddrId = (int) ($_POST['sender_address_id'] ?? 0);
    if ($scopeAddrId > 0) {
        $scopeDb->cdp_query("SELECT user_id FROM cdb_senders_addresses WHERE id_addresses = :id LIMIT 1");
        $scopeDb->bind(':id', $scopeAddrId);
        $scopeDb->cdp_execute();
        $scopeAddr = $scopeDb->cdp_registro();
        $scopeAddrOk = $scopeAddr && (int) $scopeAddr->user_id === $scopeSender;
    }
    // For type 'user' the sender doubles as recipient, so the address is one of the sender's.
    $scopeRAddrId = (int) ($_POST['recipient_address_id'] ?? 0);
    if ($scopeRAddrId > 0) {
        if ($scopeType === 'user') {
            $scopeDb->cdp_query("SELECT user_id AS owner_id FROM cdb_senders_addresses WHERE id_addresses = :id LIMIT 1");
            $scopeRAddrOwner = $scopeSender;
        } else {
            $scopeDb->cdp_query("SELECT recipient_id AS owner_id FROM cdb_recipients_addresses WHERE id_addresses = :id LIMIT 1");
            $scopeRAddrOwner = $scopeRecipient;
        }
        $scopeDb->bind(':id', $scopeRAddrId);
        $scopeDb->cdp_execute();
        $scopeRAddr = $scopeDb->cdp_registro();
        if (!$scopeRAddr || (int) $scopeRAddr->owner_id !== $scopeRAddrOwner) {
            $scopeAddrOk = false;
        }
    }
    if (($scopeRecipient > 0 && !$scopeRecipientOk) || !$scopeAddrOk) {
        _ajax_guard_send(403, ['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
    }
}

require_once("../../helpers/querys.php");
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");


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

// order_service_options falls back to the admin default when not posted (the form's select is disabled).

// if (empty($_POST['order_deli_time']))
//     $errors['order_deli_time'] = $lang['validate_field_ajax155'];

if (empty($_POST['order_pay_mode']))
    $errors['order_pay_mode'] = $lang['validate_field_ajax156'];

// if (empty($_POST['status_courier']))
//     $errors['status_courier'] = $lang['validate_field_ajax157'];

if (empty($_POST['order_payment_method']))
    $errors['order_payment_method'] = $lang['validate_field_ajax158'];

// Attachments are saved in order_files/, which the web server serves from the
// app's own origin. The client's file name and extension were kept as sent, so
// an .html/.svg (stored XSS for staff opening it) or .php file could be stored.
// Only invoices/photos are accepted (the form asks for image/* and PDF; phone
// cameras give HEIC/HEIF/AVIF too), checked by content before the shipment is
// created; cdp_uploadStore() then stores them under our own name and extension.
require_once(__DIR__ . '/../../helpers/uploads.php');
if (isset($_FILES['filesMultiple']['name']) && is_array($_FILES['filesMultiple']['name'])) {
    $skipFileIds = !empty($_POST['deleted_file_ids']) ? explode(",", (string) $_POST['deleted_file_ids']) : array();
    $allowedUploadKinds = cdp_uploadKinds('docs');
    foreach ($_FILES['filesMultiple']['name'] as $key => $fname) {
        if ($fname === '' || in_array($key, $skipFileIds) || (int) $_FILES['filesMultiple']['size'][$key] <= 0) {
            continue;
        }
        $tmp = (string) $_FILES['filesMultiple']['tmp_name'][$key];
        $mime = is_uploaded_file($tmp) ? cdp_uploadDetect($tmp) : '';
        if (!isset($allowedUploadKinds[$mime])) {
            $errors['filesMultiple'] = '"' . htmlspecialchars(basename((string) $fname), ENT_QUOTES, 'UTF-8') . '" cannot be attached: attachments must be a photo or a PDF.';
            break;
        }
        if ((int) $_FILES['filesMultiple']['size'][$key] > 10485760) {
            $errors['filesMultiple'] = '"' . htmlspecialchars(basename((string) $fname), ENT_QUOTES, 'UTF-8') . '" is too large (10 MB at most).';
            break;
        }
    }
}

if (empty($errors)) {

    $settings = cdp_getSettingsCourier();

    $order_prefix = $settings->prefix;
    $site_email = $settings->email_address;
    $check_mail = $settings->mailer;
    $names_info = $settings->smtp_names;
    $mlogo = $settings->logo;
    $msite_url = $settings->site_url;
    $msnames = $settings->site_name;
    //SMTP
    $smtphoste = $settings->smtp_host;
    $smtpuser = $settings->smtp_user;
    $smtppass = $settings->smtp_password;
    $smtpport = $settings->smtp_port;
    $smtpsecure = $settings->smtp_secure;
    $value_weight = $settings->value_weight;
    $meter = $settings->meter;


    // Uniqueness: the posted number is only a suggestion. Claim it (or the next
    // free one) so two people saving at once can never share a number.
    $_POST['order_no'] = cdp_uidClaim('order_no', $_POST['order_no'] ?? '');
    $next_order = $_POST['order_no'];
    $min_cost_tax = $core->min_cost_tax;
    $min_cost_declared_tax = $core->min_cost_declared_tax;

    $quickLoad = !empty($_POST['quick_load_v2']);
    $date = $quickLoad ? date('Y-m-d') : date('Y-m-d', strtotime(cdp_sanitize($_POST["order_date"])));
    $time = date("H:i:s");
    $date = $date . ' ' . $time;
    $sale_date   = date("Y-m-d H:i:s");

    $payment_methods = cdp_getPaymentMethodCourier($_POST["order_payment_method"]);
    $days = $payment_methods->days;
    $days = intval($days);
    $due_date = cdp_sumardias($sale_date, $days);

    // A new shipment is never marked paid here. Payments are recorded
    // afterwards, by hand, so it always starts as unpaid (2).
    $status_invoice = 2;

    if (isset($_POST["prefix_check"]) && intval($_POST["prefix_check"]) == 1) {
        $code_prefix = cdp_sanitize($_POST["code_prefix2"]);
    } else {
        $code_prefix = cdp_sanitize($_POST["code_prefix"]);
    }

    $is_pickup = false;
    $order_incomplete = 0;

    $dataShipment = array(
        'user_id' =>  $_SESSION['userid'],
        'order_prefix' =>  $order_prefix,
        'order_incomplete' =>  $order_incomplete,
        'is_pickup' =>  $is_pickup,
        'order_no' => cdp_sanitize($_POST["order_no"]),
        'order_datetime' =>  cdp_sanitize($date),
        'sender_id' =>  cdp_sanitize(intval($_POST["sender_id"])),
        'recipient_id' =>  cdp_sanitize(intval($_POST["recipient_id"])),
        'sender_address_id' =>  cdp_sanitize(intval($_POST["sender_address_id"])),
        'recipient_address_id' =>  cdp_sanitize(intval($_POST["recipient_address_id"])),
        'order_date' =>  date("Y-m-d H:i:s"),
        'agency' =>  cdp_sanitize(intval($_POST["agency"])),
        'origin_off' =>  cdp_sanitize(intval($_POST["origin_off"])),
        'order_package' =>  cdp_sanitize(intval($_POST["order_package"])),
        'order_item_category' => (intval($_POST["order_item_category"] ?? 0) > 0) ? intval($_POST["order_item_category"]) : (int) (cdp_getInfoShipDefault()->logistics_default1 ?? 0),
        'order_courier' =>  cdp_sanitize(intval($_POST["order_courier"])),
        'order_service_options' => (intval($_POST["order_service_options"] ?? 0) > 0) ? intval($_POST["order_service_options"]) : (int) (cdp_getInfoShipDefault()->service_default4 ?? 0),
        'order_deli_time' =>  cdp_sanitize(intval($_POST["order_deli_time"])),
        'order_pay_mode' =>  cdp_sanitize(intval($_POST["order_pay_mode"])),
        'order_payment_method' =>  cdp_sanitize(intval($_POST["order_payment_method"])),
        'status_courier' =>  cdp_sanitize(intval($_POST["status_courier"])),
        'driver_id' =>  null,
        'due_date' =>  $due_date,
        'status_invoice' =>  $status_invoice,
        'volumetric_percentage' =>  $meter,
        'manual_tariff' => 1,

    );

    $shipment_id = cdp_insertCourierShipment($dataShipment);
    if ($shipment_id !== null) { require_once __DIR__ . '/../../helpers/pickup_code.php'; cdp_pickupCodeAssignOnCreate('air', (int) $shipment_id); }

    if ($shipment_id !== null) {

        if (isset($_POST["packages"])) {

            $packages = json_decode($_POST['packages']);

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
            $insurance_value = (float) ($settings->insurance ?? 0);
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

                $precio_total = $calculate_weight * $price_lb;
                $sumador_total += $precio_total;
                $sumador_valor_declarado += $quickLoad ? (float) $package->declared_value * max(1, (int) $package->qty) : $package->declared_value;
                $max_fixed_charge += $package->fixed_value;

                if ($sumador_total > $min_cost_tax) {
                    $total_impuesto = $sumador_total * $tax_value / 100;
                }

                if ($sumador_valor_declarado > $min_cost_declared_tax) {
                    $total_valor_declarado = $sumador_valor_declarado * $declared_value_tax / 100;
                }
            }

            if ($quickLoad) {
                $sumador_total = 0;
                $sumador_libras = 0;
                foreach ($packages as $package) {
                    $qty = max(1, (int) ($package->qty ?? 1));
                    $weight = max(0, (float) ($package->weight ?? 0));
                    $sumador_libras += $weight * $qty;
                    $sumador_total += $weight * $qty * (float) $price_lb;
                }
                $total_impuesto = 0;
                $total_valor_declarado = 0;
            }

            $total_descuento = $sumador_total * $discount_value / 100;
            $total_peso = $sumador_libras + $sumador_volumetric;
            $total_seguro = sl_insuranceCharge((float) $insured_value, (float) $insurance_value);
            $total_impuesto_aduanero = $total_peso * $tariffs_value;
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
        $order_track = $order_prefix . $_POST["order_no"];


        if (isset($_FILES['filesMultiple']) && count($_FILES['filesMultiple']['name']) > 0 && $_FILES['filesMultiple']['tmp_name'][0] != '') {

            $target_dir = "../../order_files/";
            $deleted_file_ids = array();

            if (isset($_POST['deleted_file_ids']) && !empty($_POST['deleted_file_ids'])) {
                $deleted_file_ids = explode(",", $_POST['deleted_file_ids']);
            }

            foreach ($_FILES["filesMultiple"]['tmp_name'] as $key => $tmp_name) {

                if (!in_array($key, $deleted_file_ids)) {
                    // Our own random name and the extension of the detected type (never the client's).
                    $stored = cdp_uploadStore(cdp_uploadPick($_FILES["filesMultiple"], (int) $key), __DIR__ . '/../../order_files', $order_track, 'docs');
                    if ($stored['ok']) {
                        cdp_insertOrdersFiles($shipment_id, 'order_files/' . $stored['name'], $stored['name'], date("Y-m-d H:i:s"), '0', pathinfo($stored['name'], PATHINFO_EXTENSION));
                    }
                }
            }
        }

        if ($quickLoad) {
            $trackingNumbers = preg_split('/[\r\n,;]+/', (string) ($_POST['tracking_number'] ?? ''));
            foreach (array_slice(array_unique(array_filter(array_map('trim', $trackingNumbers))), 0, 10) as $trackingNumber) {
                cdp_insertPackageTracking($shipment_id, $_SESSION['userid'], cdp_sanitize(substr($trackingNumber, 0, 120)), null);
            }
        }

        $dataTrack = array(
            'user_id' =>  $_SESSION['userid'],
            'order_id' =>  $shipment_id,
            'order_track' =>  $order_track,
            't_date' =>  date("Y-m-d H:i:s"),
            'status_courier' =>  cdp_sanitize(intval($_POST["status_courier"])),
            'comments' =>  $lang['notification_shipment8'],
            'office' => cdp_sanitize(intval($_POST["origin_off"]))
        );

        cdp_insertCourierShipmentTrack($dataTrack);

        $sender_data = cdp_getSenderCourier(intval($_POST["sender_id"]));
        // recipient_type='user': the sender doubles as recipient (cdb_users), not cdb_recipients.
        $receiver_data = ((cdp_sanitize($_POST['recipient_type'] ?? 'recipient')) === 'user')
            ? cdp_getSenderCourier(intval($_POST["recipient_id"]))
            : cdp_getRecipientCourier(intval($_POST["recipient_id"]));

        $fullshipment = $order_prefix . $_POST["order_no"];
        $add_status =   intval($_POST["status_courier"]);
        $date_ship   = date("Y-m-d H:i:s a");

        $app_url = rtrim((string) $settings->site_url, '/') . '/track.php?order_track=' . $fullshipment;
        $subject = "a new shipment has been created, tracking number $fullshipment";

        $email_template = cdp_getEmailTemplatesdg1i4(16);

        $body = str_replace(
            array(
                '[NAME]',
                '[TRACKING]',
                '[DELIVERY_TIME]',
                '[URL]',
                '[URL_LINK]',
                '[SITE_NAME]',
                '[URL_SHIP]'
            ),
            array(
                cdp_nameWithLocker($sender_data),
                $fullshipment,
                $date_ship,
                $msite_url,
                $mlogo,
                $msnames,
                $app_url
            ),
            $email_template->body
        );

        $newbody = cdp_cleanOutx($body);

        //SENDMAIL PHP

        if ($check_mail == 'PHP') {

            $message = $newbody;
            $to = $sender_data->email;
            $from = $site_email;

            $header = "MIME-Version: 1.0\r\n";
            $header .= "Content-type: text/html; charset=UTF-8 \r\n";
            $header .= "From: " . $from . " \r\n";
            try {
                require_once __DIR__ . '/../../helpers/whatsapp.php'; if (cdp_commLegacyAllowed('email', ['id' => (int) ($sender_data->id ?? 0), 'name' => trim((string) ($sender_data->fname ?? '') . ' ' . (string) ($sender_data->lname ?? '')), 'to' => (string) $to, 'subject' => (string) $subject, 'body' => cdp_statusNotifyPlain($message)])) { $cdpOk = @mail($to, $subject, $message, $header); if (function_exists('cdp_msgLogPhpMail')) { cdp_msgLogPhpMail((bool) $cdpOk, $to, $subject, $message); } }
            } catch (Exception $e) {
            }
        } elseif ($check_mail == 'SMTP') {

            //PHPMAILER PHP
            $destinatario = $sender_data->email;

            $mail = new PHPMailer(true);                              // Passing `true` enables exceptions

            //Server settings

            $mail->isSMTP();                                      // Set mailer to use SMTP
            $mail->Host = $smtphoste;                       // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;                               // Enable SMTP authentication
            $mail->Username = $smtpuser;                   // SMTP username
            $mail->Password = $smtppass;               // SMTP password
            $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
            $mail->Port = 587;                                    // TCP port to connect to

            //Recipients
            $mail->setFrom($site_email, $names_info);
            $mail->addAddress($destinatario);     // Add a recipient
            $mail->addCC($site_email,  $lang['notification_shipment5']);

            //Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = "
                <html> 
                <body> 
                <p>{$newbody}</p>
                </body> 
                </html>
                <br />"; // Texto del email en formato HTML

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            try {
                $estadoEnvio = $mail->Send();
                //echo "El correo fue enviado correctamente.";
            } catch (Exception $e) {
                //echo "Ocurrió un error inesperado.";
            }
        }

        $dataHistory = array(
            'user_id' =>  $_SESSION['userid'],
            'order_id' =>  $shipment_id,
            'order_track' =>  $order_track,
            'action' =>  $lang['notification_shipment8'],
            'date_history' =>  cdp_sanitize(date("Y-m-d H:i:s")),
        );

        //INSERT HISTORY USER
        cdp_insertCourierShipmentUserHistory(
            $dataHistory
        );

        $dataNotification = array(
            'user_id' =>  $_SESSION['userid'],
            'order_id' =>  $shipment_id,
            'notification_description' => $lang['notification_shipment'],
            'shipping_type' => '1',
            'notification_date' =>  cdp_sanitize(date("Y-m-d H:i:s")),
        );
        // SAVE NOTIFICATION
        cdp_insertNotification(
            $dataNotification
        );

        $notification_id = $db->dbh->lastInsertId();

        //NOTIFICATION TO ADMIN AND EMPLOYEES
        $users_employees = cdp_getUsersAdminEmployees();

        foreach ($users_employees as $key) {
            cdp_insertNotificationsUsers($notification_id, $key->id);
        }
        //NOTIFICATION TO CUSTOMER
        cdp_insertNotificationsUsers($notification_id, intval($_POST['sender_id']));

        $sender_address_data = cdp_getSenderAddress(intval($_POST["sender_address_id"]));
        $cli_recipient_type = cdp_sanitize($_POST['recipient_type'] ?? 'recipient');
        $cli_recip_addr = ($cli_recipient_type === 'user')
            ? cdp_getSenderAddress(intval($_POST["recipient_address_id"]))
            : cdp_getRecipientAddress(intval($_POST["recipient_address_id"]));

        // SAVE ADDRESS FOR Shipments
        $dataAddresses = array(
            'order_id'           => $shipment_id,
            'order_track'        => $order_track,
            'sender_country'     => $sender_address_data ? cdp_resolveAddressName($sender_address_data->country, 'cdp_getCountry', $sender_address_data->legacy_country ?? '') : '',
            'sender_state'       => $sender_address_data ? cdp_resolveAddressName($sender_address_data->state,   'cdp_getState',   $sender_address_data->legacy_state   ?? '') : '',
            'sender_city'        => $sender_address_data ? cdp_resolveAddressName($sender_address_data->city,    'cdp_getCity',    $sender_address_data->legacy_city    ?? '') : '',
            'sender_zip_code'    => $sender_address_data ? ($sender_address_data->zip_code ?? '') : '',
            'sender_address'     => $sender_address_data ? ($sender_address_data->address  ?? '') : '',
            'recipient_country'  => $cli_recip_addr ? cdp_resolveAddressName($cli_recip_addr->country ?? '', 'cdp_getCountry', $cli_recip_addr->legacy_country ?? '') : '',
            'recipient_state'    => $cli_recip_addr ? cdp_resolveAddressName($cli_recip_addr->state   ?? '', 'cdp_getState',   $cli_recip_addr->legacy_state   ?? '') : '',
            'recipient_city'     => $cli_recip_addr ? cdp_resolveAddressName($cli_recip_addr->city    ?? '', 'cdp_getCity',    $cli_recip_addr->legacy_city    ?? '') : '',
            'recipient_zip_code' => $cli_recip_addr ? ($cli_recip_addr->zip_code ?? '') : '',
            'recipient_address'  => $cli_recip_addr ? ($cli_recip_addr->address  ?? '') : '',
        );

        cdp_insertCourierShipmentAddresses($dataAddresses);

        cdp_activityLog([
            'module'       => 'shipments',
            'verb'         => 'create',
            'entity_type'  => 'shipment',
            'entity_id'    => (int) $shipment_id,
            'entity_label' => $order_track,
            'summary'      => 'Created shipment ' . $order_track . ' (customer self-service)',
            'meta'         => ['sender_id' => (int) ($_POST['sender_id'] ?? 0)],
        ]);

        $messages[] = $lang['message_ajax_success_add_shipment'];
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
