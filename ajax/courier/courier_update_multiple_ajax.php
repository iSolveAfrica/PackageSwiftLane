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
require_permission('view_shipment_list');
// view_shipment_list is soft-passed for customers, and the order numbers below are resolved
// across every customer: without these a signed-in customer could set any shipment to
// Delivered / Cancelled. Staff only, with the permission the list uses for its status menu.
require_staff();
require_permission(['select_change_status_courier', 'select_change_status', 'track_shipment']);
require_once(__DIR__ . '/../../helpers/video_files.php');
cdp_purgeDeliveredVideosLater();   // videos of delivered packages are deleted after the response

require_once("../../helpers/querys.php");
require_once("../notify_whatsapp/api_whatsapp_service_v2.php");
// Message source for the message log and the transport gate (helpers/comms_gate.php):
// a bulk status change, sent by the Automation Center when it owns statuses.
if (function_exists('cdp_msgSetContext')) {
    cdp_msgSetContext(['source' => 'bulk_status_update', 'source_label' => 'Bulk Status Update']);
}

session_start();

// CSRF: the list screens (dataJs/courier.js, locker.js, warehouse.js, pickup_client_ajax.js)
// still send this as a jQuery GET; helpers/ajax_guard.php require_safe_write() accepts
// that only as a same-origin XHR or with a valid token.
require_safe_write();

$status = intval($_POST['status'] ?? $_GET['status'] ?? 0);
$data = json_decode((string) ($_POST['checked_data'] ?? $_GET['checked_data'] ?? ''));
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
    $cdpResolved = cdp_pickupCodeResolveNumbers('air', (array) $data);
    $cdpPickupMsg = '';
    if ($cdpResolved['ambiguous']) {
        $cdpPickupMsg = 'These order numbers belong to more than one package: ' . implode(', ', $cdpResolved['ambiguous'])
            . '. Hand each one over from its own page.';
    } else {
        $cdpPickupGate = cdp_pickupCodeGate('air', $cdpResolved['ids'], $status);
        $cdpPickupMsg  = $cdpPickupGate['ok'] ? '' : $cdpPickupGate['message'];
    }
    if ($cdpPickupMsg !== '') {
        echo '<div class="alert alert-danger" id="success-alert"><p>' . htmlspecialchars($cdpPickupMsg, ENT_QUOTES, 'UTF-8') . '</p></div>';
        exit;
    }
    foreach ($cdpResolved['ids'] as $cdpId) {
        $cdpRow = cdp_getCourier($cdpId);
        if ($cdpRow) {
            $cdpPickupIds[(string) $cdpRow->order_no] = (int) $cdpId;
        }
    }
}

foreach ($data as $key) {
    // Obtener información del envío
    if (cdp_pickupCodeGatedStatus($status)) {
        if (!isset($cdpPickupIds[(string) $key])) {
            continue; // already handed over
        }
        $courier = cdp_getCourier($cdpPickupIds[(string) $key]);
    } else {
        // One package per number (helpers/querys.php cdp_resolveOrderNumber):
        // a number shared by several open packages is skipped, never guessed.
        $cdpRes = cdp_resolveOrderNumber('cdb_add_order', $key);
        if (!$cdpRes['row']) {
            $message[$key] = $key . ($cdpRes['ambiguous']
                ? ': this order number belongs to more than one package. Update it from its own page.'
                : ': not found.');
            continue;
        }
        $courier = $cdpRes['row'];
    }
    // Agency-restricted staff move only their own agency's shipments.
    if (!$courier || !cdp_ajaxRowInAgencyScope($courier)) {
        $message[$key] = $key . ': not found.';
        continue;
    }
    $prefix = $courier->order_prefix;
    $office = $courier->origin_off;
    $tracking = $prefix . $key;

    // Verificar si ya existe un registro para este seguimiento y estado
    $exists = cdp_checkDuplicateCourierTrack($tracking, $status);

    if (!$exists) {
        // Si no existe un registro duplicado, actualizar el estado del envío
        if (cdp_pickupCodeGatedStatus($status)) {
            // By order_id: a number update would also move another customer's package.
            $cdpDb = new Conexion;
            $cdpDb->cdp_query("UPDATE cdb_add_order SET status_courier = :s WHERE order_id = :id");
            $cdpDb->bind(':s', $status);
            $cdpDb->bind(':id', (int) $courier->order_id);
            $cdpDb->cdp_execute();
            cdp_pickupCodeConsume('air', [(int) $courier->order_id], (int) ($_SESSION['userid'] ?? 0));
        } else {
            // By order_id: a number update would also move another customer's package.
            $cdpDb = new Conexion;
            $cdpDb->cdp_query("UPDATE cdb_add_order SET status_courier = :s WHERE order_id = :id");
            $cdpDb->bind(':s', $status);
            $cdpDb->bind(':id', (int) $courier->order_id);
            $cdpDb->cdp_execute();
        }

        // Agregar comentario
        $comment = $comments = $lang['multiple_updated1'] . ' ' . $tracking;

        // Insertar en cdb_courier_track
        $user = $_SESSION['userid'];
        cdp_updateShipTrackingMultiple($tracking, $status, $comment, $office, $user);

        // Automations (helpers/automations.php): "when shipment status changes".
        @include_once __DIR__ . '/../../helpers/automations.php';
        if (function_exists('cdp_automationsFire')) {
            cdp_automationsFire('shipment.status', ['entity_type' => 'shipment', 'entity_id' => (int) $courier->order_id, 'entity_label' => $tracking,
                'tracking' => $tracking, 'user_id' => (int) ($courier->sender_id ?? 0), 'status_id' => (int) $status, 'mode' => 'air',
                'amount' => (float) ($courier->total_order ?? 0), 'is_pickup' => (int) ($courier->is_pickup ?? 0) === 1,
                'unpaid' => (int) ($courier->status_invoice ?? 0) === 23]);
        }

        // Audit: one row per shipment moved.
        cdp_activityLogStatus(
            'shipments',
            'shipment',
            (int) $courier->order_id,
            $tracking,
            $status,
            cdp_activityStatusName($status),
            cdp_activityStatusName((int) $courier->status_courier)
        );

        // =======================
        // WhatsApp v2 Notification (Template 11)
        // =======================
        try {
            $sender_data = cdp_getSenderCourier(intval($courier->sender_id));

            if (function_exists('cdp_msgSetContext')) {
                cdp_msgSetContext(['entity_id' => (string) $courier->order_id, 'recipient_user_id' => (int) $courier->sender_id]);
            }
            if (!empty($sender_data->phone)) {
                // Ready for pickup / delivery carries the pickup code; ready and
                // picked up / delivered also text an SMS copy (the automations).
                cdp_sendStatusUpdateWhatsApp(
                    $sender_data,
                    $tracking,
                    cdp_wa_lookupName('cdb_styles', 'mod_style', (int) $status),
                    null,
                    ['status_id' => (int) $status, 'module' => 'air', 'order_id' => (int) $courier->order_id]
                );
            }
        } catch (Throwable $e) {
            error_log('Error sending WhatsApp v2 notification for bulk update: ' . $e->getMessage());
        }

        // E-mail from the status message library, as sea bulk status and the
        // single status screens send it (nothing when the library has no e-mail
        // for this status, or it is switched off there).
        try {
            if (!empty($sender_data) && !empty($sender_data->email)) {
                require_once __DIR__ . '/../../helpers/pickup_code.php';
                $cdpLabel = cdp_wa_lookupName('cdb_styles', 'mod_style', (int) $status);
                $cdpTrackUrl = rtrim((string) (cdp_getSettingsCourier()->site_url ?? ''), '/') . '/track.php?order_track=' . rawurlencode($tracking);
                $cdpCodeHtml = cdp_pickupCodeReadyLine('air', [(int) $courier->order_id], (int) $status, $cdpLabel, true);
                $ns = cdp_statusNotifyResolve((int) $status, $sender_data, $tracking, $cdpTrackUrl, 'air', (int) $courier->order_id, [
                    'status' => (string) $cdpLabel, 'comment' => '',
                    'pickup_code' => (string) cdp_pickupCodeReadyLine('air', [(int) $courier->order_id], (int) $status, $cdpLabel),
                    'pickup_code_html' => (string) $cdpCodeHtml,
                ]);
                if (!empty($ns['email']['on']) && !empty($ns['email']['custom'])) {
                    cdp_statusNotifyEmail($sender_data, $ns['email'], 'Shipment Status Update - ' . $tracking);
                }
            }
        } catch (Throwable $e) {
            error_log('Error sending status e-mail for bulk update: ' . $e->getMessage());
        }

        // Agregar mensaje de éxito
        $message[$key] = $key . ' ' . $lang['modal-text30'];
    } else {
        // Si ya existe un registro duplicado, simplemente agregar un mensaje de advertencia
        $message[$key] = $key . ' ' . $lang['modal-text31'];
    }
}

// Mostrar mensajes de éxito o advertencia
if (!empty($message)) {
?>
    <div class="alert alert-success" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <?php echo  $lang['message_ajax_success_updated']; ?>
        <ul class="error">
            <?php
            foreach ($message as $msj) { ?>
                <li>
                    <i class="icon-double-angle-right"></i>
                    <?php
                    echo $msj;
                    ?>
                </li>
            <?php
            }
            ?>
        </ul>
        </p>
    </div>
<?php
}
?>
