<?php
// POST handler moved verbatim from the retired legacy view consolidate/consolidate_deliver_shipment.php (everything before its HTML).
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
require_once('helpers/video_files.php');
cdp_purgeDeliveredVideosLater();   // videos of delivered packages are deleted after the response
require_once("helpers/phpmailer/class.phpmailer.php");
require_once("helpers/phpmailer/class.smtp.php");
require_once("ajax/notify_sms/api_sms_consolidate_service.php");
require_once("ajax/notify_whatsapp/api_whatsapp_service_v2.php");


$userData = $user->cdp_getUserData();

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


$driverrow = $user->cdp_userAllDriver();


if (isset($_POST['person_receives'])) {
    $db = new Conexion;
    $core = new Core;


    $id = $_GET['id'];

    $errors = array();

    if (empty($_POST['deliver_date']))

        $errors['deliver_date'] = $lang['validate_field_ajax159'];

    if (empty($_POST['person_receives']))

        $errors['person_receives'] = $lang['validate_field_ajax162'];

    if (intval($_POST['driver_id']) <= 0)

        $errors['driver_id'] =  $lang['validate_field_ajax163'];

    // Picked up (collected from us, the default) or Delivered (taken to the customer).
    require_once('helpers/handover.php');
    $status_courier = cdp_handoverStatusFromInput($_POST['handover_type'] ?? '');
    //signature update  
    if (isset($_POST['sig-dataUrl'])) {

        $img = $_POST['sig-dataUrl']; // Your data 'data:image/png;base64,AAAFBfj42Pj4';
        $img = str_replace('data:image/png;base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        if (!is_dir('doc_signs/consolidate')) { @mkdir('doc_signs/consolidate', 0755, true); }
        file_put_contents('doc_signs/consolidate/' . $id . '.png', base64_decode($img));
    }

    if (!empty($_FILES['miarchivo']['name'])) {

        $target_dir = "files/";
        $image_name = time() . "_" . basename($_FILES["miarchivo"]["name"]);
        $target_file = $target_dir . $image_name;
        $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
        $imageFileZise = $_FILES["miarchivo"]["size"];

        if (($imageFileType != "jpg" && $imageFileType != "png")) {
            $errors['miarchivo'] = $lang['validate_field_ajax62'];
        } else if (empty(getimagesize($_FILES['miarchivo']['tmp_name']))) { //1048576 byte=1MB
            $errors['miarchivo'] = $lang['validate_field_ajax62'];
        } else {

            move_uploaded_file($_FILES["miarchivo"]["tmp_name"], $target_file);
            $photo_delivered = 'files/' . $image_name;
        }
    }


    if (empty($errors)) {
        // Previous status — delivery alerts go out only on an actual change.
        $db->cdp_query('SELECT status_courier FROM cdb_consolidate WHERE consolidate_id = :id');
        $db->bind(':id', $id);
        $row_status_wa = $db->cdp_registro();
        $old_status_courier_wa = $row_status_wa ? (int) $row_status_wa->status_courier : null;

        $db->cdp_query('UPDATE cdb_consolidate SET
                status_courier =:status_courier,
                person_receives=:person_receives,
                photo_delivered=:photo_delivered
                where  consolidate_id=:id      
            ');


        $db->bind(':status_courier', $status_courier);
        $db->bind(':person_receives', $_POST['person_receives']);
        $db->bind(':id', $id);
        $db->bind(':photo_delivered', $photo_delivered);
        $db->cdp_execute();


        $order_track = $row->c_prefix . $row->c_no;
        $date = date('Y-m-d', strtotime(trim($_POST["deliver_date"])));
        $time = date("H:i:s");
        $date = $date . ' ' . $time;


        $db->cdp_query("
                INSERT INTO cdb_courier_track 
                (
                    order_track,
                    t_date,
                    status_courier,
                    user_id
                    )
                VALUES
                    (
                    :order_track,
                    :t_date,
                    :status_courier,                   
                    :user_id
                    )
            ");



        $db->bind(':order_track',  $order_track);
        $db->bind(':user_id',  $_SESSION['userid']);
        $db->bind(':t_date',  trim($date));
        $db->bind(':status_courier', $status_courier);

        $db->cdp_execute();


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
        $db->bind(':action',  ($status_courier === CDP_STATUS_PICKED_UP) ? 'You marked the shipment picked up' : $lang['notification_shipment12']);
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
        $db->bind(':notification_description',  cdp_handoverTrackComment($status_courier) . ', please check it');
        $db->bind(':shipping_type', '2');
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

        // Only the owners of the packages inside are told — each about their
        // own packages, without the consolidation number. Nothing goes to the
        // consolidation's own sender or recipient.
        if ($old_status_courier_wa !== $status_courier) {
            $fullshipment = $row->c_prefix . $row->c_no;
            cdp_notifyConsolidationPackageSenders('consolidate', (int) $id, $fullshipment, cdp_wa_lookupName('cdb_styles', 'mod_style', $status_courier));
        }

        header("location:consolidate_view.php?id=$id");
    }
}


?>
