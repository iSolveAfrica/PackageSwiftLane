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
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_consolidate_list');
// The view permission above is soft-passed for customers, drivers and agency users, and this
// deletes any posted consolidation (and releases its items): staff only, with the delete permission.
require_staff();
require_permission(['delete_consolidate']);

$errors = array();



$cdpConsId = (int) ($_POST['id'] ?? 0);

if ($cdpConsId <= 0) {

    $errors['id'] = 'Please select shipment';
} else {
    // Agency-restricted staff may only delete their own agency's consolidations.
    $cdpDb = new Conexion;
    $cdpDb->cdp_query("SELECT consolidate_id, agency FROM cdb_consolidate WHERE consolidate_id = :id LIMIT 1");
    $cdpDb->bind(':id', $cdpConsId);
    $cdpDb->cdp_execute();
    $cdpCons = $cdpDb->cdp_registro();
    if (!$cdpCons || !cdp_ajaxRowInAgencyScope($cdpCons)) {
        $errors['id'] = 'Please select shipment';
    }
}

if (empty($errors)) {

    $data = array(

        'id' => $cdpConsId
    );

    $insert = cdp_deleteConsolidate($data);
    $get = cdp_getItemdeleteConsolidate($data);

    foreach ($get as $key) {

        $update = cdp_updateConsolidateDelete($key->order_id);
    }


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