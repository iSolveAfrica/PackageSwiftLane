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
notify_after_response(); // email + WhatsApp go out after the response (helpers/after_response.php)
require_permission('view_client_list');
// Order numbers below are resolved across every customer, so this bulk change is for staff
// only, with the permission the package list shows its status menu for.
require_staff();
require_permission(['select_change_status', 'select_change_status_courier', 'track_package']);
require_once(__DIR__ . '/../../helpers/video_files.php');
cdp_purgeDeliveredVideosLater();   // videos of delivered packages are deleted after the response

require_once("../../helpers/querys.php");
require_once("../../helpers/phpmailer/class.phpmailer.php");
require_once("../../helpers/phpmailer/class.smtp.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");

session_start();

$settings   = cdp_getSettingsCourier();
$site_email = $settings->email_address;
$check_mail = $settings->mailer;
$names_info = $settings->smtp_names;
$mlogo      = $settings->logo;
$msite_url  = $settings->site_url;
$msnames    = $settings->site_name;
$smtphoste  = $settings->smtp_host;
$smtpuser   = $settings->smtp_user;
$smtppass   = $settings->smtp_password;
$smtpport   = $settings->smtp_port;
$smtpsecure = $settings->smtp_secure;

// CSRF: the list screen (dataJs/customers_packages_ajax.js) still sends this as a jQuery
// GET; helpers/ajax_guard.php require_safe_write() accepts that only as a same-origin XHR
// or with a valid token.
require_safe_write();

$status = intval($_POST['status'] ?? $_GET['status'] ?? 0);
$data   = json_decode((string) ($_POST['checked_data'] ?? $_GET['checked_data'] ?? ''));
if (!is_array($data)) {
    $data = [];
}

// Delivered / Picked up only with the owner's verified pickup code
// (helpers/pickup_code.php). Order numbers repeat across customers, so
// each number is resolved to the one package not yet handed over; a
// number shared by several such packages is refused, never guessed.
require_once(__DIR__ . '/../../helpers/pickup_code.php');
$cdpPickupIds = [];
if (cdp_pickupCodeGatedStatus($status)) {
    $cdpResolved = cdp_pickupCodeResolveNumbers('sea', (array) $data);
    $cdpPickupMsg = '';
    if ($cdpResolved['ambiguous']) {
        $cdpPickupMsg = 'These order numbers belong to more than one package: ' . implode(', ', $cdpResolved['ambiguous'])
            . '. Hand each one over from its own page.';
    } else {
        $cdpPickupGate = cdp_pickupCodeGate('sea', $cdpResolved['ids'], $status);
        $cdpPickupMsg  = $cdpPickupGate['ok'] ? '' : $cdpPickupGate['message'];
    }
    if ($cdpPickupMsg !== '') {
        echo '<div class="alert alert-danger" id="success-alert"><p>' . htmlspecialchars($cdpPickupMsg, ENT_QUOTES, 'UTF-8') . '</p></div>';
        exit;
    }
    foreach ($cdpResolved['ids'] as $cdpId) {
        $cdpRow = cdp_getCustomerPackage($cdpId);
        if ($cdpRow) {
            $cdpPickupIds[(string) $cdpRow->order_no] = (int) $cdpId;
        }
    }
}

// Message source for the message log and the transport gate (helpers/comms_gate.php):
// a bulk status change, sent by the Automation Center when it owns statuses.
if (function_exists('cdp_msgSetContext')) {
    cdp_msgSetContext(['source' => 'bulk_status_update', 'source_label' => 'Bulk Status Update', 'entity_type' => 'package']);
}
require_once(__DIR__ . '/../../helpers/whatsapp.php');
require_once(__DIR__ . '/../../helpers/comms_gate.php');

// Resolve new status label once (same for all shipments in this batch)
$new_status_obj   = cdp_getCourierstatusApi($status);
$new_status_label = $new_status_obj ? $new_status_obj->mod_style : 'Updated';

foreach ($data as $key) {

    // Get shipment info
    if (cdp_pickupCodeGatedStatus($status)) {
        if (!isset($cdpPickupIds[(string) $key])) {
            continue; // already handed over
        }
        $courier = cdp_getCustomerPackage($cdpPickupIds[(string) $key]);
    } else {
        // One package per number (helpers/querys.php cdp_resolveOrderNumber):
        // a number shared by several open packages is skipped, never guessed.
        $cdpRes = cdp_resolveOrderNumber('cdb_customers_packages', $key);
        if (!$cdpRes['row']) {
            $message[$key] = $key . ($cdpRes['ambiguous']
                ? ': this order number belongs to more than one package. Update it from its own page.'
                : ': not found.');
            continue;
        }
        $courier = $cdpRes['row'];
    }
    // Agency-restricted staff move only their own agency's packages.
    if (!$courier || !cdp_ajaxRowInAgencyScope($courier)) {
        $message[$key] = $key . ': not found.';
        continue;
    }
    $prefix   = $courier->order_prefix;
    $office   = $courier->origin_off;
    $tracking = $prefix . $key;

    // Check for duplicate track entry
    $exists = cdp_checkDuplicateCourierTrack($tracking, $status);

    if (!$exists) {

        // Snapshot old status before update
        $old_status_obj   = cdp_getCourierstatusApi((int)$courier->status_courier);
        $old_status_label = $old_status_obj ? $old_status_obj->mod_style : 'Previous Status';

        // Update shipment status
        if (cdp_pickupCodeGatedStatus($status)) {
            // By order_id: a number update would also move another customer's package.
            $cdpDb = new Conexion;
            $cdpDb->cdp_query("UPDATE cdb_customers_packages SET status_courier = :s WHERE order_id = :id");
            $cdpDb->bind(':s', $status);
            $cdpDb->bind(':id', (int) $courier->order_id);
            $cdpDb->cdp_execute();
            cdp_pickupCodeConsume('sea', [(int) $courier->order_id], (int) ($_SESSION['userid'] ?? 0));
        } else {
            // By order_id: a number update would also move another customer's package.
            $cdpDb = new Conexion;
            $cdpDb->cdp_query("UPDATE cdb_customers_packages SET status_courier = :s WHERE order_id = :id");
            $cdpDb->bind(':s', $status);
            $cdpDb->bind(':id', (int) $courier->order_id);
            $cdpDb->cdp_execute();
        }

        // Build comment and insert track entry
        $comment = $lang['multiple_updated2'] . ' ' . $tracking;
        $user    = $_SESSION['userid'];
        cdp_updateShipTrackingMultiple($tracking, $status, $comment, $office, $user);

        // Audit: one row per package moved, so the log can answer "who put this
        // package into Ready For Pickup, and when".
        cdp_activityLogStatus('packages', 'package', (int) $courier->order_id, $tracking, $status, $new_status_label, $old_status_label);

        // Get sender details
        $sender_data = cdp_getSenderCourier((int)$courier->sender_id);
        $app_url     = rtrim((string) $msite_url, '/') . '/track_online_shopping.php?order_track=' . $tracking;

        // Ready for pickup / delivery: this update's messages carry the owner's
        // pickup code; ready and picked up / delivered text an SMS copy too.
        $cdp_sms_event = cdp_statusSmsEvent($status, $new_status_label);
        $cdp_code_line = cdp_pickupCodeReadyLine('sea', [(int) $courier->order_id], $status, $new_status_label);
        $cdp_code_html = $cdp_code_line !== '' ? cdp_pickupCodeReadyLine('sea', [(int) $courier->order_id], $status, $new_status_label, true) : '';
        if (function_exists('cdp_msgSetContext')) {
            cdp_msgSetContext(['entity_id' => (string) $courier->order_id, 'entity_label' => $tracking, 'recipient_user_id' => (int) $courier->sender_id]);
        }

        // Status message library (helpers/notify_studio.php), as the single status
        // screens use it: the text saved or drafted for this status per channel, a
        // channel switched off there is not sent, and the built-in texts below go
        // out only when the library has nothing for the status.
        $ns = cdp_statusNotifyResolve($status, $sender_data, $tracking, $app_url, 'sea', (int) $courier->order_id, [
            'status' => (string) $new_status_label, 'comment' => '',
            'pickup_code' => (string) $cdp_code_line, 'pickup_code_html' => (string) $cdp_code_html,
        ]);

        // =====================
        // EMAIL NOTIFICATION
        // =====================
        $email_template = cdp_getEmailTemplatesdg1i4(35);

        if ($ns['email']['custom'] && $sender_data && !empty($sender_data->email)) {
            cdp_statusNotifyEmail($sender_data, $ns['email'], 'Shipment Status Update - ' . $tracking);
        } elseif ($ns['email']['on'] && $email_template && $sender_data && !empty($sender_data->email)) {

            $body = str_replace(
                [
                    '[NAME]',
                    '[TRACKING]',
                    '[OLD_STATUS]',
                    '[NEW_STATUS]',
                    '[URL]',
                    '[URL_LINK]',
                    '[SITE_NAME]',
                    '[URL_SHIP]'
                ],
                [
                    cdp_nameWithLocker($sender_data),
                    $tracking,
                    $old_status_label,
                    $new_status_label,
                    $msite_url,
                    $mlogo,
                    $msnames,
                    $app_url
                ],
                $email_template->body
            );

            $newbody = cdp_cleanOutx($body);
            if ($cdp_code_line !== '') {
                $newbody .= cdp_pickupCodeReadyLine('sea', [(int) $courier->order_id], $status, $new_status_label, true);
            }
            $subject = 'Shipment Status Update - ' . $tracking;

            if ($check_mail == 'PHP') {

                $header  = "MIME-Version: 1.0\r\n";
                $header .= "Content-type: text/html; charset=UTF-8 \r\n";
                $header .= "From: " . $site_email . " \r\n";
                // mail() bypasses PHPMailer, so the transport gate is asked here.
                require_once __DIR__ . '/../../helpers/whatsapp.php'; if (cdp_commLegacyAllowed('email', ['id' => (int) $courier->sender_id, 'name' => trim((string) ($sender_data->fname ?? '') . ' ' . (string) ($sender_data->lname ?? '')), 'to' => (string) $sender_data->email, 'subject' => $subject, 'body' => cdp_statusNotifyPlain($newbody)])) {
                    try {
                        $cdpOk = mail($sender_data->email, $subject, $newbody, $header);
                        if (function_exists('cdp_msgLogPhpMail')) {
                            cdp_msgLogPhpMail((bool) $cdpOk, $sender_data->email, $subject, $newbody);
                        }
                    } catch (Throwable $e) {}
                }

            } elseif ($check_mail == 'SMTP') {

                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $smtphoste;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtpuser;
                $mail->Password   = $smtppass;
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;

                $mail->setFrom($site_email, $names_info);
                $mail->addAddress($sender_data->email);
                $mail->addCC($site_email, $msnames);

                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = $subject;
                $mail->Body    = "<html><body><p>{$newbody}</p></body></html><br />";

                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    )
                );

                try {
                    $mail->Send();
                } catch (Throwable $e) {}
            }
        }

        // =====================
        // WHATSAPP NOTIFICATION
        // =====================
        if ($sender_data && !empty($sender_data->phone)) {
            try {
                $whatsapp_body =
                    "Hello " . cdp_nameWithLocker($sender_data) . ",\n\n" .
                    "There is an update on your shipment *{$tracking}*.\n\n" .
                    "*Shipment Status:*\n" .
                    "_{$old_status_label}_ -> *{$new_status_label}*\n\n" .
                    ($cdp_code_line !== '' ? $cdp_code_line . "\n\n" : '') .
                    "Track your shipment at any time:\n" .
                    $app_url . "\n\n" .
                    "Thank you, *{$msnames}* Team";

                // WhatsApp with its library text; the SMS copy (ready / handed over) with its own.
                cdp_statusNotifyWaSms($sender_data, $whatsapp_body, $cdp_sms_event, $ns);

            } catch (Throwable $e) {
                error_log('Error sending WhatsApp status notification for ' . $tracking . ': ' . $e->getMessage());
            }
        }

        $message[$key] = $key . ' ' . $lang['modal-text30'];

    } else {
        $message[$key] = $key . ' ' . $lang['modal-text31'];
    }
}


if (!empty($message)) {
?>
    <div class="alert alert-success" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <?php echo $lang['message_ajax_success_updated']; ?>
        <ul class="error">
            <?php foreach ($message as $msj) { ?>
                <li>
                    <i class="icon-double-angle-right"></i>
                    <?php echo $msj; ?>
                </li>
            <?php } ?>
        </ul>
        </p>
    </div>
<?php
}