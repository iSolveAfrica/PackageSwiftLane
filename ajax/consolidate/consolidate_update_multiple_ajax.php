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
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/pickup_aging.php');
require_once(__DIR__ . '/../../helpers/ajax_guard.php');

require_login();
require_permission('view_consolidate_list');
// view_consolidate_list is soft-passed for customers, and the consolidation numbers below are
// not scoped to the caller: staff only, with the permission the list shows its status menu for.
require_staff();
require_permission(['select_change_status_courier', 'select_change_status', 'track_consolidate']);
require_once(__DIR__ . '/../../helpers/video_files.php');
cdp_purgeDeliveredVideosLater();   // videos of delivered packages are deleted after the response

session_start();

// CSRF: the list screen (dataJs/consolidate_ajax.js) still sends this as a jQuery GET;
// helpers/ajax_guard.php require_safe_write() accepts that only as a same-origin XHR or
// with a valid token, so a cross-site link cannot change consolidations.
require_safe_write();

$status = intval($_POST['status'] ?? $_GET['status'] ?? 0);
$data = json_decode((string) ($_POST['checked_data'] ?? $_GET['checked_data'] ?? ''));
if (!is_array($data)) {
    $data = [];
}

$core = new Core();

foreach ($data as $key) {
    // Obtener información del envío
    $courier = cdp_getConsolidateMultiple($key);
    // Agency-restricted staff move only their own agency's consolidations.
    if (!$courier || !cdp_ajaxRowInAgencyScope($courier)) {
        $message[$key] = $key . ': not found.';
        continue;
    }
    $prefix = $courier->c_prefix;
    $office = $courier->origin_off;
    $tracking = $prefix . $key;

    // Verificar si ya existe un registro para este seguimiento y estado
    $exists = cdp_checkDuplicateCourierTrack($tracking, $status);

    if (!$exists) {
        // Si no existe un registro duplicado, actualizar el estado del envío
        // By consolidate_id: the row checked above, not every consolidation sharing the number.
        $cdpDb = new Conexion;
        $cdpDb->cdp_query("UPDATE cdb_consolidate SET status_courier = :s WHERE consolidate_id = :id");
        $cdpDb->bind(':s', $status);
        $cdpDb->bind(':id', (int) $courier->consolidate_id);
        $cdpDb->cdp_execute();

        // Sorting at Accra (33) / Ready for PickUp (32) become the member packages'
        // own status; Ready for PickUp also starts the pickup-aging clock.
        if ($status === CDP_PA_SORTING || $status === CDP_PA_READY) {
            cdp_propagateConsolidationStatusToPackages(
                (int) $courier->consolidate_id, $status, (int) ($_SESSION['userid'] ?? 0), $office
            );
        }

        // Agregar comentario
        $comment = $comments = $lang['multiple_updated3'] . ' ' . cdp_consolidationRef($courier, 'consolidate');

        // Insertar en cdb_courier_track
        $user = $_SESSION['userid'];
        cdp_updateConsolidateTrackingMultiple($tracking, $status, $comment, $office, $user);

        $status_detail = $core->cdp_getStatusById($status);
        // Actual-change guard (on top of the duplicate-track check above).
        $status_changed = !isset($courier->status_courier) || (int) $courier->status_courier !== (int) $status;

        try {
            require_once("../notify_whatsapp/api_whatsapp_service_v2.php");

            if ($status_changed) {
                // Nothing to the consolidation's own sender: customers are told
                // about their own packages only, never the consolidation number.
                // Every package owner inside the consolidation.
                cdp_notifyConsolidationPackageSenders('consolidate', (int) $courier->consolidate_id, $tracking, $status_detail->mod_style);
            }
        } catch (Exception $e) {
            error_log('WhatsApp notification error for consolidation ' . $tracking . ': ' . $e->getMessage());
        }

        // Agregar mensaje de éxito
        $message[$key] = $key . ' ' . $lang['modal-text30'];
    } else {
        // Si ya existe un registro duplicado, simplemente agregar un mensaje de advertencia
        $message[$key] = $key . ' ' . $lang['modal-text31'];
    }
}


if (!empty($message)) {
?>
    <div class="alert alert-success" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <span>Success! </span> Successfully updated shipments
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
