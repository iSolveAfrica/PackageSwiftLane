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
require_permission(['view_consolidate_package', 'view_consolidate_package_list']);
// The view permission above is soft-passed for customers, drivers and agency users, and
// cdp_deleteFileCourier() deletes ANY cdb_order_files row by id: staff only, with an edit
// permission (the air and sea edit screens both post here).
require_staff();
require_permission(['edit_consolidate', 'edit_consolidate_package']);

require_once("../../helpers/querys.php");

$errors = array();



$cdpFileId = (int) ($_POST['id'] ?? 0);

if ($cdpFileId <= 0) {

    $errors['id'] = 'Please select file';
} else {
    // Only consolidation files, and only for a consolidation inside the caller's agency.
    // order_id holds a consolidate_id of either table (air or sea), so every consolidation
    // with that id must be in scope.
    $cdpDb = new Conexion;
    $cdpDb->cdp_query("SELECT id, order_id FROM cdb_order_files WHERE id = :id AND is_consolidate = 1 LIMIT 1");
    $cdpDb->bind(':id', $cdpFileId);
    $cdpDb->cdp_execute();
    $cdpFile = $cdpDb->cdp_registro();
    $cdpScopeRows = [];
    if ($cdpFile) {
        foreach (['cdb_consolidate', 'cdb_consolidate_packages'] as $cdpTable) {
            $cdpDb->cdp_query("SELECT consolidate_id, agency FROM $cdpTable WHERE consolidate_id = :cid LIMIT 1");
            $cdpDb->bind(':cid', (int) $cdpFile->order_id);
            $cdpDb->cdp_execute();
            $cdpCons = $cdpDb->cdp_registro();
            if ($cdpCons) {
                $cdpScopeRows[] = $cdpCons;
            }
        }
    }
    if (!$cdpScopeRows) {
        $errors['id'] = 'Please select file';
    }
    foreach ($cdpScopeRows as $cdpScopeRow) {
        if (!cdp_ajaxRowInAgencyScope($cdpScopeRow)) {
            $errors['id'] = 'Please select file';
            break;
        }
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