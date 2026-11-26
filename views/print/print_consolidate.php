<?php
// *************************************************************************
// * Consolidation manifest — print_inv_ship.php design, no amounts.       *
// *************************************************************************

require_once('helpers/querys.php');
require_once('views/print/partials/inv_data_helpers.php');

if (isset($_GET['id'])) {
    $data = cdp_getConsolidatePrint($_GET['id']);
}
if (!isset($_GET['id']) || $data['rowCount'] != 1) {
    cdp_redirect_to('consolidate_list.php');
}

$row = $data['data'];
$INV = cdp_invModelFromConsolidate($db, $row, 'cdb_consolidate_detail');
// This is the consolidation manifest opened from the consolidation pages: no amounts (show_total and the
// PAID stamp stay off) and no billing wording. Consolidation billing documents are in Finance.
$lang['inv-shipping5'] = 'Shipper';
$lang['inv-shipping19'] = 'Print manifest';
$page_title = 'Manifest - ' . $INV['sys_tracking'];
include 'views/print/partials/inv_ship_page.php';
