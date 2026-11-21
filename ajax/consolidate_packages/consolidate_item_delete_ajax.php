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
require_permission(['view_consolidate_package', 'view_consolidate_package_list']);
// The view permission above is soft-passed for customers, drivers and agency users, and this
// removes any posted item from its consolidation: staff only, with the edit permission.
require_staff();
require_permission(['edit_consolidate_package']);

$errors = array();



$cdpItemId = (int) ($_POST['id'] ?? 0);

if ($cdpItemId <= 0) {

    $errors['id'] = 'Please select shipment';
} else {
    // Agency-restricted staff may only edit their own agency's consolidations: every
    // consolidation holding this item (or, when it is not consolidated yet, the item itself)
    // must be inside their agency.
    $cdpDb = new Conexion;
    $cdpDb->cdp_query("SELECT c.consolidate_id, c.agency FROM cdb_consolidate_packages_detail d
        JOIN cdb_consolidate_packages c ON c.consolidate_id = d.consolidate_id WHERE d.order_id = :id");
    $cdpDb->bind(':id', $cdpItemId);
    $cdpDb->cdp_execute();
    $cdpScopeRows = $cdpDb->cdp_registros() ?: [];
    if (!$cdpScopeRows) {
        $cdpDb->cdp_query("SELECT order_id, agency FROM cdb_customers_packages WHERE order_id = :id LIMIT 1");
        $cdpDb->bind(':id', $cdpItemId);
        $cdpDb->cdp_execute();
        $cdpItem = $cdpDb->cdp_registro();
        $cdpScopeRows = $cdpItem ? [$cdpItem] : [];
    }
    if (!$cdpScopeRows) {
        $errors['id'] = 'Please select shipment';
    }
    foreach ($cdpScopeRows as $cdpScopeRow) {
        if (!cdp_ajaxRowInAgencyScope($cdpScopeRow)) {
            $errors['id'] = 'Please select shipment';
            break;
        }
    }
}

if (empty($errors)) {

    $data = array(

        'id' => $cdpItemId
    );

    // cdp_updateItemConsolidatePackages() updates cdb_consolidate_packages, which has no
    // order_id column, so the package was never released; free it in cdb_customers_packages.
    $insert = cdp_updateConsolidatePackagesDelete($cdpItemId);
    $delete = cdp_deleteItemConsolidatePackages($data);


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