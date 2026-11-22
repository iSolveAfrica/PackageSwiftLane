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
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/uploads.php');
require_login();
require_permission('view_shipment_list');

require_once("../../helpers/querys.php");
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");

$user = new User;
$core = new Core;
$errors = array();

// view_shipment_list (the only permission checked above) is also granted to
// customers, drivers and agency users, and the shipment id comes from the
// request: without this check any of them could accept and re-price any shipment.
// Below staff rank the caller needs the same permission as the page
// ('complete_client_shipment') and the shipment must be theirs (sender or driver) or
// their agency's.
require_once(__DIR__ . '/../../helpers/rbac.php');

// Canonical recipient type for everyone. cdp_sanitize() is not idempotent (stripslashes), so
// writing the sanitised string back let 'us\\er' be checked below as a cdb_recipients id and
// then, sanitised again further down, loaded as 'user' (another user's name and address on this
// shipment). Further down only 'user' vs anything else matters, so this keeps behaviour.
// 'customer' (SL customer pickups, ajax/sl/pickup_ajax.php) stores a cdb_users id and one of the
// sender's cdb_senders_addresses ids, exactly like 'user', so it takes the 'user' path: checking it
// as a cdb_recipients id refused legitimate edits and loaded an unrelated recipient. The type is
// persisted with the recipient by the UPDATE below ($scopePersistType). When the field is not posted, or posted empty (as
// accept_pickup_ajax.php treats it), use the stored type (as the fallbacks further down used to)
// instead of assuming 'recipient'.
$scopeTypeDb = new Conexion;
$scopeTypeDb->cdp_query("SELECT recipient_type FROM cdb_add_order WHERE order_id = :id LIMIT 1");
$scopeTypeDb->bind(':id', (int) ($_POST['order_id'] ?? 0));
$scopeTypeDb->cdp_execute();
$scopeTypeRow = $scopeTypeDb->cdp_registro();
$scopeStoredRawType = (string) ($scopeTypeRow->recipient_type ?? 'recipient');
$scopeStoredType = in_array($scopeStoredRawType, ['user', 'customer'], true) ? 'user' : 'recipient';
if (isset($_POST['recipient_type']) && $_POST['recipient_type'] !== '') {
    $scopeRawType = cdp_sanitize($_POST['recipient_type']);
} else {
    $scopeRawType = $scopeStoredRawType;
}
$scopeType = in_array($scopeRawType, ['user', 'customer'], true) ? 'user' : 'recipient';
$_POST['recipient_type'] = $scopeType;

$scopeUser = $user->cdp_getUserData();
$scopeCheckParties = false;
if (cdp_roleRankById((int) ($scopeUser->userlevel ?? 0)) < 2) {
    $scopeId = (int) ($_POST['order_id'] ?? 0);
    $scopeDb = new Conexion;
    $scopeDb->cdp_query("SELECT sender_id, driver_id, agency FROM cdb_add_order WHERE order_id = :id LIMIT 1");
    $scopeDb->bind(':id', $scopeId);
    $scopeDb->cdp_execute();
    $scopeRow = $scopeDb->cdp_registro();
    $scopeUid = (int) ($_SESSION['userid'] ?? 0);
    $scopeCtx = cdp_getAgencyContext();
    // Agency users (rank 1, not drivers), seed or custom role: the agency the add endpoints book
    // under first (restricted context agency, else name_off branch, else users.agency_id), plus the
    // other two so rows the lists show (cdp_ajaxListScopeSql: name_off first) stay acceptable.
    // Before, only seed level 6 got the name_off branch, so a custom agency role could create and
    // list a shipment but got a 403 accepting it. Customers and drivers match as sender/driver only.
    $scopeLevelMe = (int) ($scopeUser->userlevel ?? 0);
    $scopeAgencies = [];
    if (cdp_roleRankById($scopeLevelMe) === 1 && $scopeLevelMe !== 3 && !cdp_roleHasFlag($scopeLevelMe, 'is_driver')) {
        $scopeBranch = (int) cdp_getAgencyBranchIdForUser($scopeUser->name_off ?? '');
        $scopeCtxAgency = !empty($scopeCtx['is_restricted']) ? (int) ($scopeCtx['agency_id'] ?? 0) : 0;
        $scopeAgencies = array_values(array_unique(array_filter([
            $scopeCtxAgency > 0 ? $scopeCtxAgency : ($scopeBranch > 0 ? $scopeBranch : (int) ($scopeUser->agency_id ?? 0)),
            $scopeBranch,
            (int) ($scopeUser->agency_id ?? 0),
        ])));
    } else {
        $scopeAgencies = array_filter([(int) ($scopeCtx['agency_id'] ?? 0)]);
    }
    $scopeOk = $scopeRow && (
        (int) $scopeRow->sender_id === $scopeUid
        || (int) $scopeRow->driver_id === $scopeUid
        || in_array((int) $scopeRow->agency, $scopeAgencies, true)
    );
    if (!$scopeOk || !$user->cdp_hasPermission('complete_client_shipment')) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Forbidden', 'message' => 'No permission for this action']);
        exit;
    }

    // The scope check above only covers the row as stored. The UPDATE below writes sender, agency
    // and driver from the request, so a caller below staff rank could otherwise move the shipment
    // to another customer or agency (out of their scope, into someone else's notifications) or
    // pick any user as its driver. Keep the stored values for them.
    $_POST['sender_id'] = (int) $scopeRow->sender_id;
    if ((int) $scopeRow->agency > 0) {
        $_POST['agency'] = (int) $scopeRow->agency;
    } elseif ($scopeAgencies) {
        $_POST['agency'] = (int) reset($scopeAgencies);
    } else {
        // Neither the row (legacy/customer pickup with agency 0) nor the caller names an agency:
        // the caller matched as sender or driver, so choosing an agency moves nothing into their
        // own scope. Accept a posted agency only if it is a real branch office; otherwise keep 0
        // and let the 'agency required' validation below ask for one.
        $scopePostedAgency = (int) ($_POST['agency'] ?? 0);
        $scopeAgencyExists = false;
        if ($scopePostedAgency > 0) {
            $scopeDb->cdp_query("SELECT id FROM cdb_branchoffices WHERE id = :id LIMIT 1");
            $scopeDb->bind(':id', $scopePostedAgency);
            $scopeDb->cdp_execute();
            $scopeAgencyExists = (bool) $scopeDb->cdp_registro();
        }
        $_POST['agency'] = $scopeAgencyExists ? $scopePostedAgency : (int) $scopeRow->agency;
    }
    if (!$user->cdp_hasPermission('assign_drivers')) {
        $_POST['driver_id'] = (int) $scopeRow->driver_id;
    }
    $scopeSender = (int) $scopeRow->sender_id;
    $scopeStoredDriver = (int) $scopeRow->driver_id;
    $scopeCheckParties = true;
} else {
    // Staff were only checked for view_shipment_list, so a read-only staff role could still
    // write here. Require the same permission as the page that posts here.
    require_permission('complete_client_shipment');
    // Agency-restricted staff (userlevel 2/4 tied to an agency) passed every check above, so they
    // could edit any agency's shipment by order_id and move it out of (or into) their agency. Same
    // rule as the other courier write endpoints: the stored row must be in their agency, the agency
    // written stays theirs, and a changed sender must be one of their agency's customers.
    $scopeCtx = cdp_getAgencyContext();
    if (!empty($scopeCtx['is_restricted'])) {
        $scopeDb = new Conexion;
        $scopeDb->cdp_query("SELECT sender_id, driver_id, agency FROM cdb_add_order WHERE order_id = :id LIMIT 1");
        $scopeDb->bind(':id', (int) ($_POST['order_id'] ?? 0));
        $scopeDb->cdp_execute();
        $scopeRow = $scopeDb->cdp_registro();
        $scopeAgencyId = (int) ($scopeCtx['agency_id'] ?? 0);
        $scopeSender = (int) ($_POST['sender_id'] ?? 0);
        // Write the int back so the empty() validation below sees the same number: "00"/"0x" are
        // not empty() but cast to 0, which skipped the sender and parties checks keyed on > 0.
        $_POST['sender_id'] = $scopeSender;
        $scopeOk = $scopeRow && $scopeAgencyId > 0 && cdp_ajaxRowInAgencyScope($scopeRow);
        // An empty sender is not a "changed" one: it reaches the 'sender required' validation
        // below (nothing is written) instead of failing the lookup with a misleading 403.
        if ($scopeOk && $scopeSender > 0 && $scopeSender !== (int) $scopeRow->sender_id) {
            // A changed sender must be a customer (rank 0) of this agency, not a staff or driver
            // account that happens to carry the agency_id (add_courier_ajax.php checks the same).
            $scopeDb->cdp_query("SELECT agency_id, userlevel FROM cdb_users WHERE id = :id LIMIT 1");
            $scopeDb->bind(':id', $scopeSender);
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
        $_POST['agency'] = $scopeAgencyId;
        $scopeAgencies = [$scopeAgencyId];
        $scopeStoredDriver = (int) $scopeRow->driver_id;
        $scopeCheckParties = true;
    }
}

// The recipient type may change (the form lists the sender and the contacts in one picker): the
// checks below validate the posted recipient/address against the NEW type, and the UPDATE now
// persists recipient_type with receiver_id (see $scopePersistType), so the row cannot end up with
// an id read against the wrong table. A 'customer' row accepted as 'user' with the sender still
// as recipient keeps 'customer'.
$scopePersistType = ($scopeType === 'user' && $scopeStoredRawType === 'customer'
    && (int) ($_POST['recipient_id'] ?? 0) === (int) ($_POST['sender_id'] ?? 0)) ? 'customer' : $scopeType;

// Skipped without a sender: the 'sender_id' validation below then refuses the request.
if ($scopeCheckParties && $scopeSender > 0) {
    // The recipient, the sender address and the recipient address must belong to that sender
    // (or to its recipient), so another customer's contact or address cannot be attached to,
    // and printed on, this shipment. $scopeType is the canonical value used further down.
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
    // The driver is notified and then passes the driver_id scope of edit/accept/print, so a newly
    // named driver must be an active driver account (what the form offers, as add_courier_ajax.php
    // requires), not any user id. An unchanged stored driver is left alone.
    $scopeDriver = (int) ($_POST['driver_id'] ?? 0);
    if ($scopeDriver > 0 && $scopeDriver !== $scopeStoredDriver) {
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

if (empty($errors)) {

    $min_cost_tax = $core->min_cost_tax;
    $min_cost_declared_tax = $core->min_cost_declared_tax;

    $sale_date   = date("Y-m-d H:i:s");
    $payment_methods = cdp_getPaymentMethodCourier($_POST["order_payment_method"]);
    $days = $payment_methods->days;
    $days = intval($days);
    $due_date = cdp_sumardias($sale_date, $days);

    // Never marked paid on creation: payments are recorded by hand afterwards.
    $status_invoice = 2;

    $tariff_mode = 1; // Tariff table pricing removed: always manual pricing.

    $dataShipment = array(
        'order_id' =>  cdp_sanitize(intval($_POST["order_id"])),
        'driver_id' =>  cdp_sanitize(intval($_POST["driver_id"])),
        'sender_id' =>  cdp_sanitize(intval($_POST["sender_id"])),
        'recipient_id' =>  cdp_sanitize(intval($_POST["recipient_id"])),
        'sender_address_id' =>  cdp_sanitize(intval($_POST["sender_address_id"])),
        'recipient_address_id' =>  cdp_sanitize(intval($_POST["recipient_address_id"])),
        'agency' =>  cdp_sanitize(intval($_POST["agency"])),
        'origin_off' =>  cdp_sanitize(intval($_POST["origin_off"])),
        'order_package' =>  cdp_sanitize(intval($_POST["order_package"])),
        'order_item_category' => (intval($_POST["order_item_category"] ?? 0) > 0) ? intval($_POST["order_item_category"]) : (int) (cdp_getInfoShipDefault()->logistics_default1 ?? 0),
        'order_courier' =>  cdp_sanitize(intval($_POST["order_courier"])),
        'order_service_options' => (intval($_POST["order_service_options"] ?? 0) > 0) ? intval($_POST["order_service_options"]) : (int) (cdp_getInfoShipDefault()->service_default4 ?? 0),
        'order_deli_time' =>  cdp_sanitize(intval($_POST["order_deli_time"])),
        'order_payment_method' =>  cdp_sanitize(intval($_POST["order_payment_method"])),
        'status_courier' =>  cdp_sanitize(intval($_POST["status_courier"])),
        'due_date' =>  $due_date,
        'status_invoice' =>  $status_invoice,
        'order_incomplete' => '1',
        'manual_tariff' =>  $tariff_mode,
        // Persisted with receiver_id so the recipient is read from the right table (validated above).
        'recipient_type' => $scopePersistType,
    );


    $updateShip = cdp_updateCourierShipmentFromCustomer($dataShipment);
    if (cdp_pickupCodeGatedStatus(intval($_POST['status_courier'] ?? 0))) {
        cdp_pickupCodeConsume('air', [intval($_POST['order_id'] ?? 0)], (int) ($_SESSION['userid'] ?? 0));
    }
    $sender_data = cdp_getSenderCourier(intval($_POST["sender_id"]));
    // recipient_type='user': the sender doubles as recipient (cdb_users), not cdb_recipients.
    $receiver_data = ((cdp_sanitize($_POST['recipient_type'] ?? 'recipient')) === 'user')
        ? cdp_getSenderCourier(intval($_POST["recipient_id"]))
        : cdp_getRecipientCourier(intval($_POST["recipient_id"]));

    $shipment_id =  cdp_sanitize(intval($_POST["order_id"]));

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

            $target_dir = "../../order_files/";
            $deleted_file_ids = array();

            if (isset($_POST['deleted_file_ids']) && !empty($_POST['deleted_file_ids'])) {
                $deleted_file_ids = explode(",", $_POST['deleted_file_ids']);
            }

            foreach ($_FILES["filesMultiple"]['tmp_name'] as $key => $tmp_name) {

                if (!in_array($key, $deleted_file_ids)) {
                    // order_files/ is web-served: the client's file name and extension were kept, so a
                    // .php/.html/.svg file could be stored there. cdp_uploadStore() checks the type from the
                    // contents and stores it under our own random name and extension; rejected files are skipped.
                    $stored = cdp_uploadStore(cdp_uploadPick($_FILES["filesMultiple"], (int) $key), __DIR__ . '/../../order_files', $order_track, 'office');
                    if ($stored['ok']) {
                        cdp_insertOrdersFiles($shipment_id, 'order_files/' . $stored['name'], $stored['name'], date("Y-m-d H:i:s"), '0', pathinfo($stored['name'], PATHINFO_EXTENSION));
                    }
                }
            }
        }



        $dataHistory = array(
            'user_id' =>  $_SESSION['userid'],
            'order_id' =>  $shipment_id,
            'order_track' =>  $order_track,
            'action' => $lang['notification_shipment7'],
            'date_history' =>  cdp_sanitize(date("Y-m-d H:i:s")),
        );

        //INSERT HISTORY USER
        cdp_insertCourierShipmentUserHistory(
            $dataHistory
        );
        cdp_deleteCourierAddress($shipment_id);

        $sender_address_data = cdp_getSenderAddress(intval($_POST["sender_address_id"]));
        $shipment = cdp_getCourier($shipment_id);
        $acc_recipient_type = cdp_sanitize($_POST['recipient_type'] ?? ($shipment->recipient_type ?? 'recipient'));
        $acc_recip_addr = ($acc_recipient_type === 'user')
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
            'recipient_country'  => $acc_recip_addr ? cdp_resolveAddressName($acc_recip_addr->country ?? '', 'cdp_getCountry', $acc_recip_addr->legacy_country ?? '') : '',
            'recipient_state'    => $acc_recip_addr ? cdp_resolveAddressName($acc_recip_addr->state   ?? '', 'cdp_getState',   $acc_recip_addr->legacy_state   ?? '') : '',
            'recipient_city'     => $acc_recip_addr ? cdp_resolveAddressName($acc_recip_addr->city    ?? '', 'cdp_getCity',    $acc_recip_addr->legacy_city    ?? '') : '',
            'recipient_zip_code' => $acc_recip_addr ? ($acc_recip_addr->zip_code ?? '') : '',
            'recipient_address'  => $acc_recip_addr ? ($acc_recip_addr->address  ?? '') : '',
        );

        cdp_insertCourierShipmentAddresses($dataAddresses);


        //NOTIFY WHATSAPP API

        if (isset($_POST['notify_whatsapp_sender']) && $_POST['notify_whatsapp_sender'] == 1) {
            // Shared builder — the hand-rolled version here carried only the
            // carrier tracking # and ETA, so the package weight never showed.
            $wa_extra_lines = cdp_wa_buildShipmentExtraLines($shipment_id);
            cdp_sendShipmentRegisteredWhatsApp($sender_data, $order_track, array(
                'courier'  => intval($_POST['order_courier'] ?? 0),
                'service'  => intval($_POST['order_service_options'] ?? 0),
                'delitime' => intval($_POST['order_deli_time'] ?? 0),
                'office'   => intval($_POST['origin_off'] ?? 0),
            ), $wa_extra_lines, true);   // no SMS of its own here: the registration automation texts a copy
        }

        // WhatsApp notifications are sender-only by design; receivers are not messaged.
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
