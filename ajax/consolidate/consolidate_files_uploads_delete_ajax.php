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
require_permission('view_consolidate_list');
// The view permission above is soft-passed for customers, drivers and agency users, and
// cdp_deleteFileCourier() deletes ANY cdb_order_files row by id: staff only, with the
// air edit permission (only the air consolidation edit screen posts to this copy).
require_staff();
require_permission('edit_consolidate');

require_once("../../helpers/querys.php");

$errors = array();



$cdpFileId = (int) ($_POST['id'] ?? 0);

if ($cdpFileId <= 0) {

    $errors['id'] = 'Please select file';
} else {
    // Only consolidation files, and only for a air consolidation inside the caller's agency.
    // is_consolidate records the kind: 1 = air (cdb_consolidate), 2 = sea (cdb_consolidate_packages),
    // because the two tables' ids collide (migrations/2026_11_23_consolidation_file_kind.sql).
    // This air copy accepts only kind 1; sea files (kind 2) go through ajax/consolidate_packages/.
    // A kind-1 row is ambiguous when both tables hold a consolidation with that id (legacy sea
    // uploads were also stored as 1), so then BOTH rows must be in the caller's agency scope:
    // otherwise staff of agency A could delete agency B's file through a colliding id
    // (fail closed; unrestricted staff are unaffected). The other copy is ajax/consolidate_packages/.
    $cdpDb = new Conexion;
    $cdpDb->cdp_query("SELECT id, order_id, is_consolidate FROM cdb_order_files WHERE id = :id AND is_consolidate IN (1) LIMIT 1");
    $cdpDb->bind(':id', $cdpFileId);
    $cdpDb->cdp_execute();
    $cdpFile = $cdpDb->cdp_registro();
    $cdpOk = false;
    if ($cdpFile) {
        $cdpDb->cdp_query("SELECT consolidate_id, agency FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
        $cdpDb->bind(':cid', (int) $cdpFile->order_id);
        $cdpDb->cdp_execute();
        $cdpCons = $cdpDb->cdp_registro();
        $cdpOk = $cdpCons && cdp_ajaxRowInAgencyScope($cdpCons);
        if ($cdpOk && (int) $cdpFile->is_consolidate === 1) {
            $cdpDb->cdp_query("SELECT consolidate_id, agency FROM cdb_consolidate_packages WHERE consolidate_id = :cid LIMIT 1");
            $cdpDb->bind(':cid', (int) $cdpFile->order_id);
            $cdpDb->cdp_execute();
            $cdpOther = $cdpDb->cdp_registro();
            if ($cdpOther && !cdp_ajaxRowInAgencyScope($cdpOther)) {
                $cdpOk = false;
            }
        }
    }
    if (!$cdpOk) {
        $errors['id'] = 'Please select file';
    }
}

if (empty($errors)) {

    $data = array(

        'id' => $cdpFileId
    );

    $insert = cdp_deleteFileCourier($data);

    if ($insert) {

        $messages[] = $lang['message_ajax_success_delete'];
    } else {

        $errors['critical_error'] = $lang['message_ajax_error1'];
    }
}


if (!empty($errors)) {
?>
    <div class="alert alert-danger" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <?php echo $lang['message_ajax_error2']; ?>
        <ul class="error">
            <?php
            foreach ($errors as $error) { ?>
                <li>
                    <i class="icon-double-angle-right"></i>
                    <?php
                    echo $error;

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

if (isset($messages)) {

?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <p><span class="icon-info-sign"></span>
            <?php
            foreach ($messages as $message) {
                echo $message;
            }
            ?>
        </p>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

<?php
}
?>