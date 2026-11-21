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
require_permission('view_shipment_list');
// view_shipment_list is soft-passed for customers, drivers and agency users and the id below is
// any shipment: without these a signed-in customer could cancel anyone's shipment. Every caller
// is a staff list screen (courier, locker, customer view, packages, hand-out).
require_staff();
require_permission('delete_shipment');

$errors = array();

$cdpDeleteId = (int) ($_POST['id'] ?? 0);

if ($cdpDeleteId <= 0)

    $errors['id'] = 'Please select shipment';

$cdp_gone = null;
if (empty($errors)) {
    // Audit: read the shipment before it goes, so the trail keeps its number. Agency-restricted
    // staff may only cancel their own agency's shipments; any other id reads as not found.
    $cdp_gone = cdp_getCourier($cdpDeleteId);
    if (!$cdp_gone || !cdp_ajaxRowInAgencyScope($cdp_gone)) {
        $errors['id'] = 'Please select shipment';
    }
}

if (empty($errors)) {

    $data = array(

        'id' => $cdpDeleteId
    );

    $cdp_gone_track = $cdp_gone->order_prefix . $cdp_gone->order_no;

    $insert = cdp_deleteCourier($data);

    if ($insert) {
        cdp_activityLog([
            'module'       => 'shipments',
            'verb'         => 'delete',
            'entity_type'  => 'shipment',
            'entity_id'    => $cdpDeleteId,
            'entity_label' => $cdp_gone_track,
            'summary'      => 'Deleted shipment ' . $cdp_gone_track,
        ]);

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