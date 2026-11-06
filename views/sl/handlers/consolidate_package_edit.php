<?php
// POST handler moved verbatim from the retired legacy view consolidate_packages/consolidate_edit.php (everything before its HTML).
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
    $data = cdp_getConsolidatePackage($_GET['id']);
}

if (!isset($_GET['id']) or $data['rowCount'] != 1) {
    cdp_redirect_to("consolidate_package_list.php");
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


$db->cdp_query("SELECT * FROM cdb_consolidate_packages_detail WHERE consolidate_id='" . $_GET['id'] . "'");
$order_items = $db->cdp_registros();

$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row_order->sender_id . "'");
$sender_data = $db->cdp_registro();

$package_tracking_data = (object) array('estimated_eta' => cdp_getConsolidationEtaRaw($row_order->consolidate_id, true));

if ($row_order->recipient_type == 'user') {
    $db->cdp_query("SELECT * FROM cdb_users where id= '" . $row_order->receiver_id . "'");
} else {
    $db->cdp_query("SELECT * FROM cdb_recipients where id= '" . $row_order->receiver_id . "'");
}

$receiver_data = $db->cdp_registro();


$db->cdp_query("SELECT * FROM cdb_address_shipments where order_track='" . $row_order->c_prefix . $row_order->c_no . "'");
$address_order = $db->cdp_registro();

$_before_pkg_edit = null;
 
if (isset($_POST["total_item"])) {
    $db_pkg_snap = new Conexion;
    $db_pkg_snap->cdp_query(
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
         FROM cdb_consolidate_packages cp
         WHERE cp.consolidate_id = :cid
         LIMIT 1"
    );
    $db_pkg_snap->bind(':cid', (int) $_GET['id']);
    $db_pkg_snap->cdp_execute();
    $_before_pkg_edit = $db_pkg_snap->cdp_registro();
}

if (isset($_POST["total_item"])) {

    $db = new Conexion;

    // Air waybill (sql/consolidation_awb.sql); the column is optional until migrated.
    $awb_col = cdp_awbColumnReady();

    $db->cdp_query("
        UPDATE cdb_consolidate_packages SET
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

    $db->bind(':consolidate_id',  $_GET['id']);

    $db->bind(':order_pay_mode',  cdp_sanitize($_POST["order_pay_mode"]));
    $db->bind(':status_courier',  cdp_sanitize($_POST["status_courier"]));
    $db->bind(':driver_id',  cdp_sanitize($_POST["driver_id"]));
    $db->bind(':seals_package',  cdp_sanitize($_POST["seals"]));

    $db->cdp_execute();

    // No "Consolidation Updated" e-mail to the consolidation's own sender:
    // the owners of the packages inside are told by the WhatsApp/e-mail
    // fan-out below, about their own packages only and without the
    // consolidation number.

    $order_id = $row_order->consolidate_id;

    // The consolidation's ETA lives on the consolidation itself; every package
    // inside it inherits this value.
    cdp_setConsolidationEta($order_id, cdp_sanitize($_POST["estimated_eta"]), true);

    // Snapshot BEFORE rewriting details: previous membership + which packages'
    // PRIOR own status still keeps them eligible for consolidation alerts.
    $db->cdp_query("SELECT order_id FROM cdb_consolidate_packages_detail WHERE consolidate_id = :cid_wa");
    $db->bind(':cid_wa', (int) $order_id);
    $previous_detail_ids_wa = array_map(function ($r) { return (int) $r->order_id; }, $db->cdp_registros());

    $posted_ids_wa = array_map('intval', (array) ($_POST['order_id'] ?? array()));
    $prior_eligible_wa = array();
    if (!empty($posted_ids_wa)) {
        $in_wa = implode(',', $posted_ids_wa);
        $db->cdp_query("SELECT order_id, status_courier FROM cdb_customers_packages WHERE order_id IN ({$in_wa})");
        foreach ($db->cdp_registros() as $r_wa) {
            if (!in_array((int) $r_wa->status_courier, array(8, 15, 16, 21, 27, 32), true)) {
                $prior_eligible_wa[] = (int) $r_wa->order_id;
            }
        }
    }

    $db->cdp_query("DELETE FROM  cdb_consolidate_packages_detail WHERE consolidate_id='" . $order_id . "'");
    $db->cdp_execute();

    for ($count = 0; $count < $_POST["total_item"]; $count++) {

        $db->cdp_query("
                  INSERT INTO cdb_consolidate_packages_detail 
                  (
                  consolidate_id,
                  order_id,
                  order_prefix,
                  order_no,
                  weight,
                  weight_vol,  
                  length,
                  width,
                  height              
                                  
                  )
                  VALUES 
                  (
                  :consolidate_id,
                  :order_id,
                  :order_prefix,
                  :order_no, 
                  :weight,
                  :weight_vol,
                  :length,
                  :width,
                  :height                               
                  )
                ");


        $db->bind(':consolidate_id',  $order_id);
        $db->bind(':order_prefix',  cdp_sanitize($_POST["prefix"][$count]));
        $db->bind(':order_no',  cdp_sanitize($_POST["order_no_item"][$count]));
        $db->bind(':weight',  cdp_sanitize($_POST["weight"][$count]));
        $db->bind(':length',  cdp_sanitize($_POST["length"][$count]));
        $db->bind(':width',  cdp_sanitize($_POST["width"][$count]));
        $db->bind(':height',  cdp_sanitize($_POST["height"][$count]));
        $db->bind(':weight_vol',  cdp_sanitize($_POST["weight_vol"][$count]));
        $db->bind(':order_id',  cdp_sanitize($_POST["order_id"][$count]));

        $db->cdp_execute();

        $is_consolidate = 1;

        $db->cdp_query("
                UPDATE  cdb_customers_packages SET                
                    
                    is_consolidate=:is_consolidate

                    WHERE order_id=:order_id                 
                
                ");

        $db->bind(':order_id',  cdp_sanitize($_POST["order_id"][$count]));
        $db->bind(':is_consolidate',  $is_consolidate);

        $db->cdp_execute();
    }

    // ═════════════════════════════════════════════════════════════════════
    // WhatsApp fan-out — only when something actually changed.
    // ═════════════════════════════════════════════════════════════════════
    $new_status_id_wa = intval(cdp_sanitize($_POST["status_courier"] ?? 0));
    $old_status_id_wa = isset($_before_pkg_edit->status_courier) ? (int) $_before_pkg_edit->status_courier : null;
    $added_ids_wa = array_values(array_diff($posted_ids_wa, $previous_detail_ids_wa));
    $consolidation_tracking_wa = $row_order->c_prefix . $row_order->c_no;
    $new_status_label_wa = cdp_wa_lookupName('cdb_styles', 'mod_style', $new_status_id_wa);

    if ($new_status_id_wa > 0 && $old_status_id_wa !== null && $new_status_id_wa !== $old_status_id_wa) {
        cdp_notifyConsolidationPackageSenders('consolidate_packages', (int) $order_id, $consolidation_tracking_wa, $new_status_label_wa, $prior_eligible_wa, false);
    } elseif (!empty($added_ids_wa)) {
        cdp_notifyConsolidationPackageSenders('consolidate_packages', (int) $order_id, $consolidation_tracking_wa, $new_status_label_wa, array_values(array_intersect($added_ids_wa, $prior_eligible_wa)), false);
    }

    //INSERT HISTORY USER
    $date = date("Y-m-d H:i:s");
    $db->cdp_query("
                INSERT INTO cdb_order_user_history 
                (
                    user_id,
                    order_id,
                    action,
                    date_history,
                    is_consolidate                  
                    )
                VALUES
                    (
                    :user_id,
                    :order_id,
                    :action,
                    :date_history,
                    :is_consolidate
                    )
            ");



    $db->bind(':order_id',  $order_id);
    $db->bind(':is_consolidate', '1');
    $db->bind(':user_id',  $_SESSION['userid']);
    $db->bind(':action', $lang['notification_shipment30']);
    $db->bind(':date_history',  trim($date));
    $db->cdp_execute();

    $db->cdp_query("SELECT * FROM cdb_senders_addresses where id_addresses= '" . $_POST["sender_address_id"] . "'");

    $sender_address_data = $db->cdp_registro();

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


    $db->cdp_query("SELECT * FROM cdb_recipients_addresses where id_addresses= '" . $_POST["recipient_address_id"] . "'");

    $recipient_address_data = $db->cdp_registro();

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



    cdp_deleteCourierAddress($order_id);

    // SAVE ADDRESS FOR Shipments

    $db->cdp_query("
    INSERT INTO cdb_address_shipments
    (
        order_id,
        order_track,
        sender_country,
        sender_state,
        sender_city,
        sender_zip_code,
        sender_address,
        recipient_country,
        recipient_state,
        recipient_city,
        recipient_zip_code,
        recipient_address
    )
    VALUES
        (
        :order_id,    
        :order_track,
        :sender_country,
        :sender_state,
        :sender_city,
        :sender_zip_code,
        :sender_address,
        :recipient_country,
        :recipient_state,
        :recipient_city,
        :recipient_zip_code,                
        :recipient_address
        )
");

    $db->bind(':order_id', $order_id);
    $db->bind(':order_track',   $row_order->c_prefix . $row_order->c_no);
    $db->bind(':sender_country',   $final_sender_country->name);
    $db->bind(':sender_state',   $final_sender_state->name);
    $db->bind(':sender_city',   $final_sender_city->name);
    $db->bind(':sender_zip_code',   $sender_zip_code);
    $db->bind(':sender_address',   $sender_address);
    $db->bind(':recipient_country',   $final_recipient_country->name);
    $db->bind(':recipient_state',   $final_recipient_state->name);
    $db->bind(':recipient_city',   $final_recipient_city->name);
    $db->bind(':recipient_zip_code',   $recipient_zip_code);
    $db->bind(':recipient_address',   $recipient_address);

    // $db->cdp_execute();


    if (isset($_FILES['filesMultiple']['name']) && count($_FILES['filesMultiple']['name']) > 0) {
        $target_dir = "order_files/";
        $deleted_file_ids = !empty($_POST['deleted_file_ids']) ? explode(",", $_POST['deleted_file_ids']) : [];

        foreach ($_FILES["filesMultiple"]["tmp_name"] as $key => $tmp_name) {
            if (!in_array($key, $deleted_file_ids)) {
                $image_name = time() . "_" . basename($_FILES["filesMultiple"]["name"][$key]);
                $target_file = $target_dir . $image_name;
                $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
                $imageFileZise = $_FILES["filesMultiple"]["size"][$key];

                if ($imageFileZise > 0 && move_uploaded_file($_FILES["filesMultiple"]["tmp_name"][$key], $target_file)) {
                    $imagen = basename($_FILES["filesMultiple"]["name"][$key]);
                    $file = "image_path='img/usuarios/$image_name' ";
                    cdp_insertOrdersFiles($order_id, $target_file, $image_name, date("Y-m-d H:i:s"), '1', $imageFileType);
                }
            }
        }
    }



    if ($db->cdp_execute()) {
        // Éxito al actualizar en la base de datos
        cdp_awbForget();
        $consolidate_number = cdp_consolidationRef((object) array(
            'consolidate_id' => (int) $row_order->consolidate_id,
            'c_prefix'       => $row_order->c_prefix,
            'c_no'           => $row_order->c_no,
            'awb_no'         => isset($_POST['awb_no']) ? cdp_awbNormalize($_POST['awb_no']) : cdp_consolidationAwb($row_order, 'consolidate_packages'),
        ), 'consolidate_packages');
        $message = "Consolidation " . addslashes($consolidate_number) . " has been updated successfully";
        $success_script = 'swal("Success", "' . $message . '", "success").then(function() { window.location.href = "consolidate_package_view.php?id=' . $row_order->consolidate_id . '"; });';
    } else {
        // Error al actualizar en la base de datos
        $message = "There was an error processing the data";
        $error_script = 'swal("Error", "' . $message . '", "error");';
    }


}
?>

