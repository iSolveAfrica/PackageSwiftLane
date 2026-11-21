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
// cdp_deleteFileCourier() deletes ANY cdb_order_files row by id: staff only, with the
// sea edit permission (only the sea consolidation edit screen posts to this copy).
require_staff();
require_permission('edit_consolidate_package');

require_once("../../helpers/querys.php");

$errors = array();



$cdpFileId = (int) ($_POST['id'] ?? 0);

if ($cdpFileId <= 0) {

    $errors['id'] = 'Please select file';
} else {
    // Only consolidation files, and only for a sea consolidation inside the caller's agency.
    // order_id holds a consolidate_id of either table (air or sea) and cdb_order_files does
    // not record which one. The air and sea screens each post to their own copy of this
    // endpoint, so this copy checks ONLY cdb_consolidate_packages: checking both tables and accepting
    // any match let staff of agency A delete agency B's file when the other table's row with
    // the same number belonged to agency A (the other copy is ajax/consolidate/).
    $cdpDb = new Conexion;
    $cdpDb->cdp_query("SELECT id, order_id FROM cdb_order_files WHERE id = :id AND is_consolidate = 1 LIMIT 1");
    $cdpDb->bind(':id', $cdpFileId);
    $cdpDb->cdp_execute();
    $cdpFile = $cdpDb->cdp_registro();
    $cdpCons = null;
    if ($cdpFile) {
        $cdpDb->cdp_query("SELECT consolidate_id, agency FROM cdb_consolidate_packages WHERE consolidate_id = :cid LIMIT 1");
        $cdpDb->bind(':cid', (int) $cdpFile->order_id);
        $cdpDb->cdp_execute();
        $cdpCons = $cdpDb->cdp_registro();
    }
    if (!$cdpCons || !cdp_ajaxRowInAgencyScope($cdpCons)) {
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