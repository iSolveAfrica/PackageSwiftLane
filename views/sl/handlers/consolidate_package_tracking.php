<?php
// POST handler moved verbatim from the retired legacy view consolidate_packages/consolidate_shipment_tracking.php (everything before its HTML).
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
require_once("helpers/phpmailer/class.phpmailer.php");
require_once("helpers/phpmailer/class.smtp.php");
require_once("ajax/notify_whatsapp/api_whatsapp_service_v2.php");

$userData = $user->cdp_getUserData();

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

$row = $data['data'];

// recipient_type='user': the sender doubles as recipient (cdb_users), not cdb_recipients.
if (($row->recipient_type ?? 'recipient') === 'user') {
    $db->cdp_query("SELECT * FROM cdb_users where id= '" . intval($row->receiver_id) . "'");
} else {
    $db->cdp_query("SELECT * FROM cdb_recipients where id= '" . intval($row->receiver_id) . "'");
}
$receiver_data = $db->cdp_registro();

$office = $core->cdp_getOffices();
$statusrow = $core->cdp_getStatusByType(2);


$db->cdp_query("SELECT * FROM cdb_users where id= '" . $row->sender_id . "'");
$sender_data = $db->cdp_registro();


if (isset($_POST['address'])) {
    $db = new Conexion;


    $id = $_GET['id'];

    $errors = array();

    if (empty($_POST['t_date']))

        $errors['t_date'] = $lang['validate_field_ajax159'];

    if (empty($_POST['address']))

        $errors['address'] = $lang['validate_field_ajax88'];

    if (intval($_POST['status_courier']) <= 0)

        $errors['status_courier'] = $lang['validate_field_ajax160'];

    if (intval($_POST['office']) <= 0)

        $errors['office'] = $lang['validate_field_ajax84'];

    if (empty($_POST['country']))

        $errors['country'] = $lang['validate_field_ajax102'];



    if (empty($errors)) {

        // Previous status — fan-out fires only on an actual status change.
        $db->cdp_query('SELECT status_courier FROM cdb_consolidate_packages WHERE consolidate_id = :id');
        $db->bind(':id', $id);
        $row_status_wa = $db->cdp_registro();
        $old_status_courier_wa = $row_status_wa ? (int) $row_status_wa->status_courier : null;

        $db->cdp_query('UPDATE cdb_consolidate_packages SET

                status_courier =:status_courier
                where  consolidate_id=:id
            ');


        $db->bind(':status_courier', $_POST['status_courier']);
        $db->bind(':id', $id);


        $db->cdp_execute();


        $order_track = $row->c_prefix . $row->c_no;
        $date = date('Y-m-d', strtotime(trim($_POST["t_date"])));
        $time = date("H:i:s");
        $date = $date . ' ' . $time;


        // flight_no / awb_no are optional columns (sql/air_journey_tracking.sql).
        $air_cols = cdp_courierTrackHasFlightColumns();
        $db->cdp_query("INSERT INTO cdb_courier_track
                (order_track, t_dest, t_city, comments, t_date, status_courier, office_id, user_id" . ($air_cols ? ", flight_no, awb_no" : "") . ")
                VALUES
                (:order_track, :country, :address, :comments, :t_date, :status_courier, :office, :user_id" . ($air_cols ? ", :flight_no, :awb_no" : "") . ")");



        $db->bind(':order_track',  $order_track);
        $db->bind(':country', cdp_sanitize($_POST['country']));
        $db->bind(':address', cdp_sanitize($_POST['address']));
        $db->bind(':comments', cdp_sanitize($_POST['comments']));
        $db->bind(':t_date',  trim($date));
        $db->bind(':status_courier', cdp_sanitize($_POST['status_courier']));
        $db->bind(':office', cdp_sanitize($_POST['office']));
        $db->bind(':user_id',  $_SESSION['userid']);
        if ($air_cols) {
            $fl = trim(cdp_sanitize($_POST['flight_no'] ?? ''));
            $aw = trim(cdp_sanitize($_POST['awb_no'] ?? ''));
            $db->bind(':flight_no', $fl !== '' ? $fl : null);
            $db->bind(':awb_no', $aw !== '' ? $aw : null);
        }

        $db->cdp_execute();

        // A consolidation with no waybill on record adopts the one typed on this tracking event.
        if (cdp_awbColumnReady() && cdp_consolidationAwb($row, 'consolidate_packages') === '') {
            $aw_c = cdp_awbNormalize($_POST['awb_no'] ?? '');
            if ($aw_c !== '') {
                $db->cdp_query('UPDATE cdb_consolidate_packages SET awb_no = :awb_no WHERE consolidate_id = :id AND (awb_no IS NULL OR awb_no = \'\')');
                $db->bind(':awb_no', $aw_c);
                $db->bind(':id', $id);
                $db->cdp_execute();
                $row->awb_no = $aw_c;
                cdp_awbForget();
            }
        }

        //INSERT HISTORY USER
        $date = date("Y-m-d H:i:s");
        $db->cdp_query("
                INSERT INTO cdb_order_user_history 
                (
                    user_id,
                    order_id,
                    order_track,
                    action,
                    date_history,
                    is_consolidate                  
                    )
                VALUES
                    (
                    :user_id,
                    :order_id,
                    :order_track,
                    :action,
                    :date_history,
                    :is_consolidate
                    )
            ");



        $db->bind(':order_id',  $id);
        $db->bind(':order_track',  $order_track);
        $db->bind(':is_consolidate', '1');
        $db->bind(':user_id',  $_SESSION['userid']);
        $db->bind(':action',  $lang['notification_shipment11']);
        $db->bind(':date_history',  trim($date));
        $db->cdp_execute();


        // SAVE NOTIFICATION
        $db->cdp_query("
                INSERT INTO cdb_notifications 
                (
                    user_id,
                    order_id,
                    notification_description,
                    shipping_type,
                    notification_date

                )
                VALUES
                    (
                    :user_id,                    
                    :order_id,
                    :notification_description,
                    :shipping_type,
                    :notification_date                    
                    )
            ");



        $db->bind(':user_id',  $_SESSION['userid']);
        $db->bind(':order_id',  $_GET['id']);
        $db->bind(':notification_description', $lang['notification_shipment10']);
        $db->bind(':shipping_type', '5');
        $db->bind(':notification_date',  date("Y-m-d H:i:s"));

        $db->cdp_execute();


        $notification_id = $db->dbh->lastInsertId();

        //NOTIFICATION TO DRIVER

        cdp_insertNotificationsUsers($notification_id, $row->driver_id);


        //NOTIFICATION TO ADMIN AND EMPLOYEES

        $users_employees = cdp_getUsersAdminEmployees();

        foreach ($users_employees as $key) {

            cdp_insertNotificationsUsers($notification_id, $key->id);
        }

        // Only the owners of the packages inside are told (fan-out below) —
        // each about their own packages, without the consolidation number.
        // Nothing goes to the consolidation's own sender.
        $fullshipment = $row->c_prefix . $row->c_no;

        // Fan-out to package owners — only when the status actually changed.
        $new_status_id_wa = intval(cdp_sanitize($_POST['status_courier']));
        if ($old_status_courier_wa !== $new_status_id_wa) {
            cdp_notifyConsolidationPackageSenders(
                'consolidate_packages',
                (int) $id,
                $fullshipment,
                cdp_wa_lookupName('cdb_styles', 'mod_style', $new_status_id_wa)
            );
        }

        header("location:consolidate_package_view.php?id=$id");
    }
}


?>
