<?php
// POST handler moved verbatim from the retired legacy view consolidate/consolidate_edit.php (everything before its HTML).
// Included from the root controller in global scope; the controller turns its redirect / $errors / $*_script result into JSON (helpers/sl_legacy_post.php).
?>
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

require_once('helpers/querys.php');

require_once('helpers/phpmailer/class.phpmailer.php');
require_once('helpers/phpmailer/class.smtp.php');
require_once('ajax/notify_whatsapp/api_whatsapp_service_v2.php');

$userData = $user->cdp_getUserData();
if ($userData->userlevel == 1)
    cdp_redirect_to("login.php");


if (isset($_GET['id'])) {
    $data = cdp_getConsolidatePrint($_GET['id']);
}

if (!isset($_GET['id']) or $data['rowCount'] != 1) {
    cdp_redirect_to("consolidate_list.php");
}

if (isset($userData->userlevel) && (int)$userData->userlevel === 6) {
    require_once(__DIR__ . '/../../../helpers/querys.php');
    $aid = (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '');
    if ((int)($data['data']->agency ?? 0) !== $aid) {
        header('Location: ' . (isset($_SERVER['SCRIPT_NAME']) ? dirname(dirname($_SERVER['SCRIPT_NAME'])) : '') . '/error403.php');
        exit;
    }
}

$row_order = $data['data'];

$db->cdp_query("SELECT * FROM cdb_consolidate_detail WHERE consolidate_id='" . $_GET['id'] . "'");
$order_items = $db->cdp_registros();

$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row_order->sender_id . "'");
$sender_data = $db->cdp_registro();

$package_tracking_data = (object) array('estimated_eta' => cdp_getConsolidationEtaRaw($row_order->consolidate_id));

if ($row_order->recipient_type == 'user') {
    $db->cdp_query("SELECT * FROM cdb_users where id= '" . $row_order->receiver_id . "'");
} else {
    $db->cdp_query("SELECT * FROM cdb_recipients where id= '" . $row_order->receiver_id . "'");
}
$receiver_data = $db->cdp_registro();

$db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $row_order->c_prefix . $row_order->c_no . "'");
$address_order = $db->cdp_registro();

$_before_edit = null;

if (isset($_POST["total_item"])) {

    // ─────────────────────────────────────────────────────────────────────
    // DANGEROUS-GOODS GUARD: this consolidation already has a fixed kind
    // (dangerous vs normal). Every package kept/added must match it — the edit
    // screen offers no kind toggle, so any mismatch is tampering and is refused
    // before a single row is rewritten.
    // ─────────────────────────────────────────────────────────────────────
    $consolidation_dg_edit = (int) ($row_order->is_dangerous_good ?? 0);
    $posted_ids_edit = array_values(array_filter(
        array_map('intval', (array) ($_POST['order_id'] ?? [])),
        function ($v) { return $v > 0; }
    ));
    $dg_mismatch = false;
    if (!empty($posted_ids_edit)) {
        $in_e = implode(',', $posted_ids_edit);
        $db_dg = new Conexion;
        $db_dg->cdp_query("SELECT COUNT(*) AS c FROM cdb_add_order WHERE order_id IN ($in_e) AND is_dangerous_good <> $consolidation_dg_edit");
        $db_dg->cdp_execute();
        $dg_row = $db_dg->cdp_registro();
        $dg_mismatch = ($dg_row && (int) $dg_row->c > 0);
    }

    if ($dg_mismatch) {
        $error_script = 'swal("Cannot consolidate", "This consolidation holds ' . ($consolidation_dg_edit ? 'dangerous goods' : 'normal goods') . ' only. One or more selected packages are the other kind and were not saved.", "error");';
    } else {

    // Capture before-edit state for change tracking
    try {
        $db_snap = new Conexion;
        $db_snap->cdp_query(
            "SELECT
                cp.sender_id,
                cp.receiver_id,
                cp.value_weight,
                cp.sub_total,
                cp.total_tax_insurance,
                cp.total_insured_value,
                cp.total_tax_discount,
                cp.total_tax_custom_tariffis,
                cp.total_tax,
                cp.total_order,
                cp.total_weight,
                cp.order_datetime,
                cp.agency,
                cp.origin_off,
                cp.order_package,
                cp.order_item_category,
                cp.order_courier,
                cp.order_service_options,
                cp.order_deli_time,
                cp.order_pay_mode,
                cp.status_courier,
                cp.driver_id,
                cp.seals_package,
                cp.estimated_eta
             FROM cdb_consolidate cp
             WHERE cp.consolidate_id = :cid
             LIMIT 1"
        );
        $db_snap->bind(':cid', (int) $_GET['id']);
        $db_snap->cdp_execute();
        $_before_edit = $db_snap->cdp_registro();
    } catch (Exception $e) {
        // Continue with empty before state
    }

    // ═════════════════════════════════════════════════════════════════════
    // MAIN UPDATE
    // ═════════════════════════════════════════════════════════════════════

    try {
        $db = new Conexion;

        // Air waybill (sql/consolidation_awb.sql); the column is optional until migrated.
        $awb_col = cdp_awbColumnReady();

        $db->cdp_query("
            UPDATE cdb_consolidate SET
                order_pay_mode = :order_pay_mode,
                status_courier = :status_courier,
                driver_id = :driver_id,
                seals_package = :seals_package" . ($awb_col ? ",
                awb_no = :awb_no" : "") . "
            WHERE consolidate_id = :consolidate_id
        ");

        if ($awb_col) {
            $db->bind(':awb_no', cdp_awbNormalize($_POST['awb_no'] ?? ''));
        }
        $db->bind(':order_pay_mode', cdp_sanitize($_POST["order_pay_mode"] ?? ''));
        $db->bind(':status_courier', cdp_sanitize($_POST["status_courier"] ?? ''));
        $db->bind(':driver_id', cdp_sanitize($_POST["driver_id"] ?? ''));
        $db->bind(':seals_package', cdp_sanitize($_POST["seals"] ?? ''));
        $db->bind(':consolidate_id', (int) $_GET['id']);

        // Capture main UPDATE result immediately
        $main_update_result = $db->cdp_execute();

    } catch (Exception $e) {
        $main_update_result = false;
    }

    // No "Consolidation Updated" e-mail to the consolidation's own sender:
    // the owners of the packages inside are told by the WhatsApp/e-mail
    // fan-out below, about their own packages only and without the
    // consolidation number.

    $order_id = $row_order->consolidate_id;

    // ═════════════════════════════════════════════════════════════════════
    // ETA UPDATE
    // ═════════════════════════════════════════════════════════════════════

    // The consolidation's ETA lives on the consolidation itself; every package
    // inside it inherits this value.
    cdp_setConsolidationEta($order_id, cdp_sanitize($_POST["estimated_eta"]));

    // ═════════════════════════════════════════════════════════════════════
    // DELETE AND INSERT DETAIL ROWS
    // ═════════════════════════════════════════════════════════════════════

    // Snapshot BEFORE rewriting details: which packages were already in the
    // consolidation, and which are still eligible for alerts (their PRIOR own
    // status doesn't put them out of the consolidation). Needed because the
    // status-32 carve-out below mutates package state before the fan-out runs.
    $db->cdp_query("SELECT order_id FROM cdb_consolidate_detail WHERE consolidate_id = :cid_wa");
    $db->bind(':cid_wa', (int) $order_id);
    $previous_detail_ids_wa = array_map(function ($r) { return (int) $r->order_id; }, $db->cdp_registros());

    $posted_ids_wa = array_map('intval', (array) ($_POST['order_id'] ?? array()));
    $prior_eligible_wa = array();
    if (!empty($posted_ids_wa)) {
        $in_wa = implode(',', $posted_ids_wa);
        $db->cdp_query("SELECT order_id, status_courier FROM cdb_add_order WHERE order_id IN ({$in_wa})");
        foreach ($db->cdp_registros() as $r_wa) {
            if (!in_array((int) $r_wa->status_courier, array(8, 15, 16, 21, 27, 32), true)) {
                $prior_eligible_wa[] = (int) $r_wa->order_id;
            }
        }
    }

    $db->cdp_query("DELETE FROM cdb_consolidate_detail WHERE consolidate_id='" . $order_id . "'");
    $db->cdp_execute();

    for ($count = 0; $count < $_POST["total_item"]; $count++) {

        $db->cdp_query("
            INSERT INTO cdb_consolidate_detail 
            (consolidate_id, order_id, order_prefix, order_no, weight, weight_vol, length, width, height)
            VALUES 
            (:consolidate_id, :order_id, :order_prefix, :order_no, :weight, :weight_vol, :length, :width, :height)
        ");

        $db->bind(':consolidate_id', $order_id);
        $db->bind(':order_prefix', cdp_sanitize($_POST["prefix"][$count]));
        $db->bind(':order_no', cdp_sanitize($_POST["order_no_item"][$count]));
        $db->bind(':weight', cdp_sanitize($_POST["weight"][$count]));
        $db->bind(':length', cdp_sanitize($_POST["length"][$count]));
        $db->bind(':width', cdp_sanitize($_POST["width"][$count]));
        $db->bind(':height', cdp_sanitize($_POST["height"][$count]));
        $db->bind(':weight_vol', cdp_sanitize($_POST["weight_vol"][$count]));
        $db->bind(':order_id', cdp_sanitize($_POST["order_id"][$count]));

        $db->cdp_execute();

        // Mark as consolidate
        $db->cdp_query("UPDATE cdb_add_order SET is_consolidate=:is_consolidate WHERE order_id=:order_id");
        $db->bind(':order_id', cdp_sanitize($_POST["order_id"][$count]));
        $db->bind(':is_consolidate', 1);
        $db->cdp_execute();

        if ($_POST['status_courier'] == 32) {
            // By order_id: order numbers repeat across customers, and a
            // number match moved another customer's package too.
            $db->cdp_query("UPDATE cdb_add_order SET status_courier = '32', is_consolidate = '0' WHERE order_id = :order_id");
            $db->bind(':order_id', (int) $_POST["order_id"][$count]);
            $db->cdp_execute();
        }

        if ($_POST['status_courier'] == 33) {
            // By order_id: order numbers repeat across customers, and a
            // number match moved another customer's package too.
            $db->cdp_query("UPDATE cdb_add_order SET status_courier = '33', is_consolidate = '0' WHERE order_id = :order_id");
            $db->bind(':order_id', (int) $_POST["order_id"][$count]);
            $db->cdp_execute();
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // WhatsApp fan-out — only when something actually changed.
    //  - status changed: alert every still-eligible package owner (prior-state
    //    filtered, so a 32 "Ready for PickUp" transition still notifies)
    //  - status unchanged: alert only the owners of newly added packages
    // ═════════════════════════════════════════════════════════════════════
    $new_status_id_wa = intval(cdp_sanitize($_POST["status_courier"] ?? 0));
    $old_status_id_wa = isset($_before_edit->status_courier) ? (int) $_before_edit->status_courier : null;
    $added_ids_wa = array_values(array_diff($posted_ids_wa, $previous_detail_ids_wa));
    $consolidation_tracking_wa = $row_order->c_prefix . $row_order->c_no;
    $new_status_label_wa = cdp_wa_lookupName('cdb_styles', 'mod_style', $new_status_id_wa);

    if ($new_status_id_wa > 0 && $old_status_id_wa !== null && $new_status_id_wa !== $old_status_id_wa) {
        cdp_notifyConsolidationPackageSenders('consolidate', (int) $order_id, $consolidation_tracking_wa, $new_status_label_wa, $prior_eligible_wa, false);
    } elseif (!empty($added_ids_wa)) {
        cdp_notifyConsolidationPackageSenders('consolidate', (int) $order_id, $consolidation_tracking_wa, $new_status_label_wa, array_values(array_intersect($added_ids_wa, $prior_eligible_wa)), false);
    }

    // ═════════════════════════════════════════════════════════════════════
    // HISTORY LOG
    // ═════════════════════════════════════════════════════════════════════

    $date = date("Y-m-d H:i:s");
    $db->cdp_query("
        INSERT INTO cdb_order_user_history 
        (user_id, order_id, action, date_history, is_consolidate)
        VALUES (:user_id, :order_id, :action, :date_history, :is_consolidate)
    ");
    $db->bind(':order_id', $order_id);
    $db->bind(':is_consolidate', '1');
    $db->bind(':user_id', $_SESSION['userid']);
    $db->bind(':action', $lang['notification_shipment30']);
    $db->bind(':date_history', $date);
    $db->cdp_execute();

    // ═════════════════════════════════════════════════════════════════════
    // ADDRESS UPDATES
    // ═════════════════════════════════════════════════════════════════════

    $db->cdp_query("SELECT * FROM cdb_senders_addresses where id_addresses= '" . $_POST["sender_address_id"] . "'");
    $sender_address_data = $db->cdp_registro();

    if ($sender_address_data) {
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
    }

    $db->cdp_query("SELECT * FROM cdb_recipients_addresses where id_addresses= '" . $_POST["recipient_address_id"] . "'");
    $recipient_address_data = $db->cdp_registro();

    if ($recipient_address_data) {
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
    }

    cdp_deleteCourierAddress($order_id);

    if ($sender_address_data && $recipient_address_data) {
        $db->cdp_query("
            INSERT INTO cdb_address_shipments
            (order_id, order_track, sender_country, sender_state, sender_city, sender_zip_code, sender_address,
             recipient_country, recipient_state, recipient_city, recipient_zip_code, recipient_address)
            VALUES
            (:order_id, :order_track, :sender_country, :sender_state, :sender_city, :sender_zip_code, :sender_address,
             :recipient_country, :recipient_state, :recipient_city, :recipient_zip_code, :recipient_address)
        ");

        $db->bind(':order_id', $order_id);
        $db->bind(':order_track', $row_order->c_prefix . $row_order->c_no);
        $db->bind(':sender_country', $final_sender_country->name ?? 'N/A');
        $db->bind(':sender_state', $final_sender_state->name ?? 'N/A');
        $db->bind(':sender_city', $final_sender_city->name ?? 'N/A');
        $db->bind(':sender_zip_code', $sender_zip_code ?? '');
        $db->bind(':sender_address', $sender_address ?? '');
        $db->bind(':recipient_country', $final_recipient_country->name ?? 'N/A');
        $db->bind(':recipient_state', $final_recipient_state->name ?? 'N/A');
        $db->bind(':recipient_city', $final_recipient_city->name ?? 'N/A');
        $db->bind(':recipient_zip_code', $recipient_zip_code ?? '');
        $db->bind(':recipient_address', $recipient_address ?? '');

        $db->cdp_execute();
    }

    // ═════════════════════════════════════════════════════════════════════
    // FILE UPLOADS
    // ═════════════════════════════════════════════════════════════════════

    if (isset($_FILES['filesMultiple']['name']) && count($_FILES['filesMultiple']['name']) > 0) {
        $target_dir = "order_files/";
        $deleted_file_ids = !empty($_POST['deleted_file_ids']) ? explode(",", $_POST['deleted_file_ids']) : [];

        foreach ($_FILES["filesMultiple"]["tmp_name"] as $key => $tmp_name) {
            if (!in_array($key, $deleted_file_ids)) {
                $image_name = time() . "_" . basename($_FILES["filesMultiple"]["name"][$key]);
                $target_file = $target_dir . $image_name;
                $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
                $imageFileSize = $_FILES["filesMultiple"]["size"][$key];

                if ($imageFileSize > 0 && move_uploaded_file($_FILES["filesMultiple"]["tmp_name"][$key], $target_file)) {
                    cdp_insertOrdersFiles($order_id, $target_file, $image_name, date("Y-m-d H:i:s"), '1', $imageFileType);
                }
            }
        }
    }


    if ($main_update_result) {
        cdp_awbForget();
        $consolidate_number = cdp_consolidationRef((object) array(
            'consolidate_id' => (int) $row_order->consolidate_id,
            'c_prefix'       => $row_order->c_prefix,
            'c_no'           => $row_order->c_no,
            'awb_no'         => isset($_POST['awb_no']) ? cdp_awbNormalize($_POST['awb_no']) : cdp_consolidationAwb($row_order, 'consolidate'),
        ), 'consolidate');
        $message = "Consolidation " . addslashes($consolidate_number) . " has been updated successfully";
        $success_script = 'swal("Success", "' . $message . '", "success").then(function() { window.location.href = "consolidate_view.php?id=' . $row_order->consolidate_id . '"; });';
    } else {
        $message = "There was an error processing the data";
        $error_script = 'swal("Error", "' . $message . '", "error");';
    }

    } // end DANGEROUS-GOODS guard (else of $dg_mismatch)
}

?>

