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
// The consolidation reference helper (waybill first, code after) lives in
// helpers/querys.php, which nothing on this page's include chain loads.
require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('view_consolidate_list');


$db = new Conexion;
$user = new User;
$core = new Core;
$userData = $user->cdp_getUserData();


$order_id = intval($_REQUEST['id']);

$sWhere = "";

if ($order_id > 0) {

    $sWhere .= "";
}



$sql = "SELECT * FROM cdb_consolidate   where consolidate_id = '" . $order_id . "' ";


$db->cdp_query($sql);
$db->cdp_execute();
$numrows = $db->cdp_rowCount();


$db->cdp_query($sql);
$data = $db->cdp_registro();

// The list permission above is customer-facing, so the id alone must not reveal another
// customer's (or another agency's) payment: same ownership rule as the print pages.
// A row outside the caller's scope gets the same empty answer as a missing one.
require_once(__DIR__ . '/../../helpers/print_access.php');
if (!$data || !cdp_printRowInScope($data, (int) $user->uid, (int) $user->userlevel)) {
    exit;
}



$db->cdp_query("SELECT * FROM cdb_met_payment where id= '" . $data->order_pay_mode . "'");
$met_payment = $db->cdp_registro();

// Payment notes and the attachment path are stored values (notes are often already HTML-encoded
// by cdp_sanitize()): decode once and escape on output so stored markup cannot run in this popup.
$payEsc = function ($v) {
    return htmlspecialchars(html_entity_decode((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
};
// The attachment is a relative file under assets/ (e.g. payment_packages/x.pdf). Anything else
// (a scheme, an absolute path, "..") gets no link; each segment is URL-encoded so the value
// cannot leave the href attribute.
$payAttach = str_replace('\\', '/', trim((string) ($data->url_payment_attach ?? '')));
$payAttachHref = '#';
if ($payAttach !== '' && $payAttach[0] !== '/' && strpos($payAttach, '..') === false
    && strpos($payAttach, ':') === false && !preg_match('/[\x00-\x1F\x7F]/', $payAttach)) {
    $payAttachHref = 'assets/' . implode('/', array_map('rawurlencode', explode('/', $payAttach)));
}



?>


<div class="row">


    <div class="col-md-6">
        <h3 class=" pull-left"><b class="text-danger"> <?php echo $lang['billing'] ?></b> <span><?php echo cdp_consolidationRefHtml($data, 'consolidate'); ?></span></h3>

    </div>

    <div class="col-md-6  pull-right">
        <div class="btn-group pull-right">
            <a href="<?php echo h($payAttachHref); ?>" target="blank" class="btn btn-info text-white">
                <?php echo $lang['left1108'] ?>
            </a>

        </div>

    </div>


    <br>
    <br>
    <br>


    <div class="col-md-4">
        <div class="pull-left">
            <h5> &nbsp;<b> <?php echo $lang['leftorder157'] ?></b></h5>
            <p class="text-muted  m-l-5"><?php echo date('Y-m-d h:i A', strtotime($data->payment_date)); ?></p>
        </div>

    </div>

    <div class="col-md-4">
        <div class="pull-left">
            <h5> &nbsp;<b><?php echo $lang['modal-text20'] ?></b></h5>
            <p class="text-muted  m-l-5"><?php echo cdb_money_format($data->total_order); ?></p>
        </div>

    </div>

    <div class="col-md-4">
        <div class="pull-left">
            <h5> &nbsp;<b><?php echo $lang['left603'] ?></b></h5>
            <p class="text-muted  m-l-5"><?php echo $payEsc($met_payment->name_pay ?? ''); ?></p>
        </div>

    </div>


    <div class="col-md-12 pt-2">
        <div class="pull-left">
            <h5> &nbsp;<b><?php echo $lang['modal-text18'] ?></b></h5>
            <b class="text-muted  m-l-5"><?php if ($data->notes != null) {
                                                echo $payEsc($data->notes);
                                            } ?></b>
        </div>
    </div>
</div>