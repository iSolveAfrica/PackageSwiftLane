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
require_once(__DIR__ . '/../../helpers/unique_ids.php');

require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
notify_after_response(); // email + WhatsApp go out after the response (helpers/after_response.php)
require_permission('view_shipment_list');

require_once("../../helpers/querys.php");
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");
require_once("../notify_sms/api_sms_service.php");


$user = new User;
$core = new Core;
$errors = array();

// view_shipment_list (the only permission checked above) is also soft-passed for customers,
// drivers and agency users. This is the staff bulk-booking form (views/sl/data/ops_forms.php):
// without this a customer could post here directly and create shipments under any sender, agency
// or price. Same rule as add_courier_ajax.php: below staff rank the caller needs 'add_shipment'
// and must not be a customer role (rank 0 follows the parent_role_id chain).
require_once(__DIR__ . '/../../helpers/rbac.php');
$scopeLevel = (int) ($_SESSION['userlevel'] ?? 0);
$scopeRank = cdp_roleRankById($scopeLevel);
if ($scopeRank < 2 && ($scopeRank === 0 || cdp_roleIsClient($scopeLevel) || !$user->cdp_hasPermission('add_shipment'))) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
    exit;
}
// Staff were only checked for view_shipment_list, so a read-only staff role could still create
// shipments here. Require the permission of the page that posts here for them too.
if ($scopeRank >= 2) {
    require_permission(['add_multiple_shipments', 'add_shipment']);
}

// Agency-restricted callers (agency users, and staff tied to an agency) may only book for their own
// agency and for customers of that agency, as add_courier_ajax.php enforces. Without this the bulk
// form would trust the posted agency and sender_id and create shipments under any agency/customer.
// cdp_getAgencyContext() only marks seed roles 6 and 2/4-with-agency as restricted, so agency users
// on a custom role (rank 1, other ids) skipped the check and are scoped too. A restricted context
// with an agency keeps that agency (what the sender picker offers); otherwise the agency is resolved
// as cdp_ajaxListScopeSql() does (name_off branch, then users.agency_id).
// Drivers hold 'add_shipment' in the seed Driver role on purpose: they are not agency-scoped, keep
// the parties check below (rank < 2) and the sender must be a customer (rank 0).
$scopeCtx = cdp_getAgencyContext();
$scopeAgencyScoped = !empty($scopeCtx['is_restricted']);
$scopeAgencyId = (int) ($scopeCtx['agency_id'] ?? 0);
$scopeIsDriver = $scopeRank === 1 && ($scopeLevel === 3 || cdp_roleHasFlag($scopeLevel, 'is_driver'));
if ($scopeRank === 1 && !$scopeIsDriver && !($scopeAgencyScoped && $scopeAgencyId > 0)) {
    $scopeMeDb = new Conexion;
    $scopeMeDb->cdp_query('SELECT name_off, agency_id FROM cdb_users WHERE id = :id LIMIT 1');
    $scopeMeDb->bind(':id', (int) ($_SESSION['userid'] ?? 0));
    $scopeMeDb->cdp_execute();
    $scopeMe = $scopeMeDb->cdp_registro();
    $scopeAgencyId = $scopeMe ? (int) cdp_getAgencyBranchIdForUser($scopeMe->name_off ?? '') : 0;
    if ($scopeAgencyId <= 0 && $scopeMe) {
        $scopeAgencyId = (int) ($scopeMe->agency_id ?? 0);
    }
    $scopeAgencyScoped = true;
}
// Write the int back: "00"/"0x" are not empty() but cast to 0, which skipped the checks keyed on
// the sender while still passing the empty() validation below.
if ($scopeRank < 2 || $scopeAgencyScoped) {
    $_POST['sender_id'] = (int) ($_POST['sender_id'] ?? 0);
}
if ($scopeAgencyScoped) {
    // No agency: nothing may be booked.
    $scopeOk = $scopeAgencyId > 0;
    // Skipped without a sender so the 'sender_id' validation below reports the missing field.
    if ($scopeOk && !empty($_POST['sender_id'])) {
        // The sender must be a customer (rank 0, any Customer-derived role) of this agency, not a
        // staff or driver account that carries the agency_id (edit/accept check the same).
        $scopeDb = new Conexion;
        $scopeDb->cdp_query('SELECT agency_id, userlevel FROM cdb_users WHERE id = :id LIMIT 1');
        $scopeDb->bind(':id', (int) $_POST['sender_id']);
        $scopeDb->cdp_execute();
        $scopeSenderRow = $scopeDb->cdp_registro();
        $scopeOk = $scopeSenderRow && (int) $scopeSenderRow->agency_id === $scopeAgencyId
            && cdp_roleRankById((int) $scopeSenderRow->userlevel) === 0;
    }
    if (!$scopeOk) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
        exit;
    }
    // Force the caller's own agency into the insert below.
    $_POST['agency'] = $scopeAgencyId;
}

// Canonical recipient type for everyone. cdp_sanitize() is not idempotent (stripslashes), so a
// value like 'us\\er' could pass the check below as a cdb_recipients id and then, sanitised again
// further down, be loaded as 'user'. Further down only 'user' vs anything else matters.
$scopeType = (cdp_sanitize($_POST['recipient_type'] ?? 'recipient') === 'user') ? 'user' : 'recipient';
$_POST['recipient_type'] = $scopeType;

// Callers below staff rank and agency-restricted staff: the sender (and agency) is checked, but
// the recipient, both addresses and the driver came from POST unchecked, so another customer's or
// agency's contact/address could be attached to, printed on and notified for this shipment (the
// same rule edit_courier_ajax.php and accept_courier_ajax.php enforce).
// Skipped without a sender: the 'sender_id' validation below then reports the missing field
// (same empty() test, so nothing is inserted without one) instead of a misleading 403.
if (($scopeRank < 2 || $scopeAgencyScoped) && !empty($_POST['sender_id'])) {
    $scopeDb = new Conexion;
    $scopeSender = (int) ($_POST['sender_id'] ?? 0);
    // Not agency-scoped below staff rank (drivers): the agency check above did not run, so the
    // sender must at least be a customer account (rank 0), not a staff/driver user.
    if (!$scopeAgencyScoped) {
        $scopeDb->cdp_query("SELECT userlevel FROM cdb_users WHERE id = :id LIMIT 1");
        $scopeDb->bind(':id', $scopeSender);
        $scopeDb->cdp_execute();
        $scopeSenderRow = $scopeDb->cdp_registro();
        if (!$scopeSenderRow || cdp_roleRankById((int) $scopeSenderRow->userlevel) !== 0) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
            exit;
        }
    }
    $scopeAgencies = array_filter([$scopeAgencyScoped ? $scopeAgencyId : 0]);
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
    // The driver gets a notification for this shipment: only an active driver (what the add form
    // offers, User::cdp_userAllDriver) may be named, not any user id.
    $scopeDriver = (int) ($_POST['driver_id'] ?? 0);
    if ($scopeDriver > 0) {
        // A driver on a custom Driver role (rank 1 with is_driver) is a driver too, as in
        // cdp_ajaxListScopeSql(); checking only the seed id 3 refused them.
        $scopeDb->cdp_query("SELECT userlevel FROM cdb_users WHERE id = :id AND active = 1 LIMIT 1");
        $scopeDb->bind(':id', $scopeDriver);
        $scopeDb->cdp_execute();
        $scopeDrvRow = $scopeDb->cdp_registro();
        $scopeDrvLvl = $scopeDrvRow ? (int) $scopeDrvRow->userlevel : -1;
        if (!$scopeDrvRow || !($scopeDrvLvl === 3 || (cdp_roleRankById($scopeDrvLvl) === 1 && cdp_roleHasFlag($scopeDrvLvl, 'is_driver')))) {
            $scopeAddrOk = false;
        }
    }
    if (($scopeRecipient > 0 && !$scopeRecipientOk) || !$scopeAddrOk) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
        exit;
    }
}

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

    // NOTIFY SMS CLICKSEND API
    $templatessender = 2;
    $templatesreceiver = 1;

    $min_cost_tax = $core->min_cost_tax;
    $min_cost_declared_tax = $core->min_cost_declared_tax;

    $date = date('Y-m-d', strtotime(cdp_sanitize($_POST["order_date"])));
    $time = date("H:i:s");
    $date = $date . ' ' . $time;
    $sale_date   = date("Y-m-d H:i:s");

    $payment_methods = cdp_getPaymentMethodCourier($_POST["order_payment_method"]);
    $days = $payment_methods->days;
    $days = intval($days);
    $due_date = cdp_sumardias($sale_date, $days);

    // Never marked paid on creation: payments are recorded by hand afterwards.
    $status_invoice = 2;

    if (isset($_POST["prefix_check"]) && intval($_POST["prefix_check"]) == 1) {
        $code_prefix = cdp_sanitize($_POST["code_prefix2"]);
    } else {
        $code_prefix = cdp_sanitize($_POST["code_prefix"]);
    }

    $tariff_mode = 1; // Tariff table pricing removed: always manual pricing.


    if (isset($_POST["packages"])) {

        $packages = json_decode($_POST['packages']);

        foreach ($packages as $package) {

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

            // Uniqueness: reserve each number server-side (see helpers/unique_ids.php).
            $next_order = cdp_uidClaim('order_no');

            $is_pickup = false;
            $order_incomplete = 0;

            $dataShipment = array(
                'user_id' =>  $_SESSION['userid'],
                'order_incomplete' =>  $order_incomplete,
                'is_pickup' =>  $is_pickup,
                'order_prefix' =>  $code_prefix,
                'order_no' => $next_order,
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
                'order_payment_method' =>  cdp_sanitize(intval($_POST["order_payment_method"])),
                'status_courier' =>  cdp_sanitize(intval($_POST["status_courier"])),
                'driver_id' =>  cdp_sanitize(intval($_POST["driver_id"])),
                'due_date' =>  $due_date,
                'status_invoice' =>  $status_invoice,
                'volumetric_percentage' =>  $meter,
                'manual_tariff' =>  $tariff_mode,
                'tracking_number' => cdp_sanitize(intval(value: $_POST['tracking_number'])),
                'estimated_eta' => cdp_sanitize($_POST['estimated_eta'])

            );

            $shipment_id = cdp_insertCourierShipment($dataShipment);
            if ($shipment_id !== null) { require_once __DIR__ . '/../../helpers/pickup_code.php'; cdp_pickupCodeAssignOnCreate('air', (int) $shipment_id); }

            if ($shipment_id !== null) {

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

                if ($tariff_mode) {
                    $sumador_total = $calculate_weight * $price_lb; // Calculate total based on weight and price per lb
                } else {
                    $sumador_total = $price_lb;
                }

                $sumador_valor_declarado += $package->declared_value;
                $max_fixed_charge += $package->fixed_value;

                if ($sumador_total > $min_cost_tax) {
                    $total_impuesto = $sumador_total * $tax_value / 100;
                }

                if ($sumador_valor_declarado > $min_cost_declared_tax) {
                    $total_valor_declarado = $sumador_valor_declarado * $declared_value_tax / 100;
                }


                $total_descuento = $sumador_total * $discount_value / 100;
                $total_peso = $sumador_libras + $sumador_volumetric;
                $total_seguro = sl_insuranceCharge((float) $insured_value, (float) $insurance_value);
                $total_impuesto_aduanero = ($total_peso * $tariffs_value) / 100;
                $total_envio = ($sumador_total - $total_descuento) + $total_seguro + $total_impuesto + $total_impuesto_aduanero + $total_valor_declarado + $max_fixed_charge + $reexpedicion_value;

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

                $order_track = $code_prefix . $next_order;

                $dataTrack = array(
                    'user_id' =>  $_SESSION['userid'],
                    'order_id' =>  $shipment_id,
                    'order_track' =>  $order_track,
                    't_date' =>  date("Y-m-d H:i:s"),
                    'status_courier' =>  cdp_sanitize(intval($_POST["status_courier"])),
                    'comments' =>  'Shipping created',
                    'office' => cdp_sanitize(intval($_POST["origin_off"]))
                );

                cdp_insertCourierShipmentTrack($dataTrack);

                $sender_data = cdp_getSenderCourier(intval($_POST["sender_id"]));
                // recipient_type='user': the sender doubles as recipient (cdb_users), not cdb_recipients.
                $receiver_data = ((cdp_sanitize($_POST['recipient_type'] ?? 'recipient')) === 'user')
                    ? cdp_getSenderCourier(intval($_POST["recipient_id"]))
                    : cdp_getRecipientCourier(intval($_POST["recipient_id"]));

                // Obtener el ID del estado del mensajero desde el POST
                $name_status = cdp_getCourierstatusApi(intval($_POST["status_courier"]));
                $add_status = $name_status->mod_style;
                $date_ship   = date("Y-m-d H:i:s a");

                $app_url = rtrim((string) $settings->site_url, '/') . '/track.php?order_track=' . $order_track;
                $subject = $lang['notification_shipment2'] . $lang['notification_shipment6'] .  $order_track;

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
                        $order_track,
                        $date_ship,
                        $msite_url,
                        $mlogo,
                        $msnames,
                        $app_url
                    ),
                    $email_template->body
                );

                $newbody = cdp_cleanOut($body);

                //SENDMAIL PHP

                if ($check_mail == 'PHP') {

                    $message = $newbody;
                    $to = $sender_data->email;
                    $from = $site_email;
                    $header = "MIME-Version: 1.0\r\n";
                    $header .= "Content-type: text/html; charset=UTF-8 \r\n";
                    $header .= "From: " . $from . " \r\n";
                    try {
                        mail($to, $subject, $message, $header);
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
                    'action' => $lang['notification_shipment8'],
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

                //NOTIFICATION TO DRIVER
                cdp_insertNotificationsUsers($notification_id, intval($_POST["driver_id"]));
                //NOTIFICATION TO ADMIN AND EMPLOYEES
                $users_employees = cdp_getUsersAdminEmployees();

                foreach ($users_employees as $key) {
                    cdp_insertNotificationsUsers($notification_id, $key->id);
                }
                //NOTIFICATION TO CUSTOMER
                cdp_insertNotificationsUsers($notification_id, intval($_POST['sender_id']));



                $sender_address_data = cdp_getSenderAddress(intval($_POST["sender_address_id"]));
                $sender_country = $sender_address_data->country;
                $sender_state = $sender_address_data->state;
                $sender_city = $sender_address_data->city;
                $sender_zip_code = $sender_address_data->zip_code;
                $sender_address = $sender_address_data->address;

                $_sender_country = cdp_getCountry($sender_country);
                $final_sender_country = $_sender_country['data'];

                $_sender_state = cdp_getState($sender_state);
                $final_sender_state = $_sender_state['data'];

                $sender_city = cdp_getCity($sender_city);
                $final_sender_city = $sender_city['data'];

                // Load the address from the table the ownership check above validated it against
                // (type 'user': a sender address), as add_courier_ajax.php does.
                $recipient_address_data = ($_POST['recipient_type'] === 'user')
                    ? cdp_getSenderAddress(intval($_POST["recipient_address_id"]))
                    : cdp_getRecipientAddress(intval($_POST["recipient_address_id"]));

                $recipient_address = $recipient_address_data->address;
                $recipient_country = $recipient_address_data->country;
                $recipient_city = $recipient_address_data->city;
                $recipient_state = $recipient_address_data->state;
                $recipient_zip_code = $recipient_address_data->zip_code;

                $_recipient_country = cdp_getCountry($recipient_country);
                $final_recipient_country = $_recipient_country['data'];

                $_recipient_state = cdp_getState($recipient_state);
                $final_recipient_state = $_recipient_state['data'];

                $recipient_city = cdp_getCity($recipient_city);
                $final_recipient_city = $recipient_city['data'];

                // SAVE ADDRESS FOR Shipments
                $dataAddresses = array(
                    'order_id' =>   $shipment_id,
                    'order_track' =>   $order_track,
                    'sender_country' =>   $final_sender_country->name,
                    'sender_state' =>   $final_sender_state->name,
                    'sender_city' =>   $final_sender_city->name,
                    'sender_zip_code' =>   $sender_zip_code,
                    'sender_address' =>   $sender_address,
                    'recipient_country' =>   $final_recipient_country->name,
                    'recipient_state' =>   $final_recipient_state->name,
                    'recipient_city' =>   $final_recipient_city->name,
                    'recipient_zip_code' =>   $recipient_zip_code,
                    'recipient_address' =>   $recipient_address,
                );

                cdp_insertCourierShipmentAddresses($dataAddresses);

                //NOTIFY WHATSAPP API

                if (isset($_POST['notify_whatsapp_sender']) && $_POST['notify_whatsapp_sender'] == 1) {
                    // Shared builder — the hand-rolled version here carried only the
                    // carrier tracking # and ETA, so the package weight never showed.
                    $wa_extra_lines = cdp_wa_buildShipmentExtraLines($shipment_id, 'air', true);
                    cdp_sendShipmentRegisteredWhatsApp($sender_data, $fullshipment, array(
                        'courier'  => intval($_POST['order_courier'] ?? 0),
                        'service'  => intval($_POST['order_service_options'] ?? 0),
                        'delitime' => intval($_POST['order_deli_time'] ?? 0),
                        'office'   => intval($_POST['origin_off'] ?? 0),
                    ), $wa_extra_lines);
                }

                // WhatsApp notifications are sender-only by design; receivers are not messaged.

                // Obtener el estado de las casillas de verificación
                // The customer is always texted when a package is created (the SMS switch
                // on Tools > SMS and the customer's own opt-out still apply). The
                // form sends no WhatsApp, so this is the customer's only message.
                $notify_sms_sender = true;
                $notify_sms_receiver = isset($_POST['notify_sms_receiver']) && $_POST['notify_sms_receiver'] == 1;

                // Generar cuerpo del SMS para el remitente
                try {
                    $newbodyS_sender = generateSMSBody($sender_data, $fullshipment, $add_status, $app_url, $templatessender);
                    // Llamar a la función para enviar la notificación SMS al remitente
                    sendNotificationSMS($sender_data, $newbodyS_sender, $notify_sms_sender);
                } catch (Exception $e) {
                    error_log('Error generating or sending SMS for sender: ' . $e->getMessage());
                    // Manejo del error, por ejemplo, establecer una variable para mostrar un mensaje de error al usuario
                }

                // Generar cuerpo del SMS para el receptor
                try {
                    $newbodyS_receiver = generateSMSBody($receiver_data, $fullshipment, $add_status, $app_url, $templatesreceiver);
                    // Llamar a la función para enviar la notificación SMS al receptor
                    sendNotificationSMS($receiver_data, $newbodyS_receiver, $notify_sms_receiver);
                } catch (Exception $e) {
                    error_log('Error generating or sending SMS for receiver: ' . $e->getMessage());
                    // Manejo del error, por ejemplo, establecer una variable para mostrar un mensaje de error al usuario
                }

                cdp_insertPackageTracking($shipment_id, $_SESSION['userid'], $_POST['tracking_number'], $_POST['estimated_eta']);
                
                $messages[] = $lang['message_ajax_success_add_shipment'];
            } else {
                $errors['critical_error'] = $lang['message_ajax_error2'];
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
        'messages' => $messages,
    ]);
}
