<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Package receipt, paid or not paid (Swift Lane UI: views/sl/pages/ReceiptPaid.php /
// ReceiptNotPaid.php). ?id=N for a sea package (default), &type=air for an air shipment.
// The previous invoice layout stays at print_invoice_package.php?legacy=1.
require_once("loader.php");
$user = new User();
$core = new Core();
if (!empty($_GET['legacy'])) {
    if ($user->cdp_loginCheck() == true) {
        require_once(__DIR__ . "/helpers/activity_log.php");
        cdp_activityLogDocument();
        include('views/customer_packages/print_invoice_package.php');
    } else {
        header("location: login.php");
    }
    exit;
}
require_once 'helpers/sl_view.php';
$type = ($_GET['type'] ?? 'sea') === 'air' ? 'air' : 'sea';
// Customers may print their own receipts; scope is enforced by sl_labelFor().
sl_gate(sl_userIs('client') ? null : ($type === 'air' ? 'print_shipment' : 'print_package'));
require_once 'helpers/activity_log.php';
require_once 'views/sl/data/labels.php';
cdp_activityLogDocument();
$r = sl_receiptData((int) ($_GET['id'] ?? 0), $type);
if (!$r) {
    http_response_code(404);
    sl_render('ReceiptNotPaid', ['title' => 'Receipt', 'board' => 'ReceiptNotPaid', 'plain' => true, 'id' => ''], ['r' => null]);
    exit;
}
$board = $r['paidState'] ? 'ReceiptPaid' : 'ReceiptNotPaid';
sl_render($board, ['id' => $type === 'air' ? 'air' : 'sea', 'title' => 'Receipt ' . $r['track'], 'board' => $board, 'plain' => true], ['r' => $r, 'canPay' => sl_userIs('client')]);
