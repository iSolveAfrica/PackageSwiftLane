<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Package receipt, paid or not paid (Swift Lane UI: views/sl/pages/ReceiptPaid.php /
// ReceiptNotPaid.php). ?id=N for a sea package (default), &type=air for an air shipment.
// ?legacy=1 (the previous invoice layout) is retired and renders the same receipt.
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
$type = ($_GET['type'] ?? 'sea') === 'air' ? 'air' : 'sea';
// Customers may print their own receipts; scope is enforced by sl_labelFor().
sl_gate(sl_userIs('client') ? null : ($type === 'air' ? 'print_shipment' : 'print_package'));
require_once 'helpers/activity_log.php';
require_once 'views/sl/data/labels.php';
// Receipt Studio previews (embed=1, sample=paid|due, as=paid|due) are not documents and need view_tools.
$canStudio = $user->cdp_hasPermission('view_tools');
$isPreview = !empty($_GET['embed']) && $canStudio;
if (!$isPreview) cdp_activityLogDocument();
$sample = $isPreview ? (string) ($_GET['sample'] ?? '') : '';
$r = $sample !== '' ? sl_receiptSample($sample === 'paid') : sl_receiptData((int) ($_GET['id'] ?? 0), $type);
if ($r && $isPreview && in_array($_GET['as'] ?? '', ['paid', 'due'], true)) {
    $r['paidState'] = $_GET['as'] === 'paid';
    if ($r['paidState'] && !$r['pay']) $r['pay'] = ['method' => '—', 'ref' => '—', 'on' => '', 'by' => 'Online'];
}
if (!$r) {
    http_response_code(404);
    sl_render('ReceiptNotPaid', ['title' => 'Receipt', 'board' => 'ReceiptNotPaid', 'plain' => true, 'id' => ''], ['r' => null]);
    exit;
}
$board = $r['paidState'] ? 'ReceiptPaid' : 'ReceiptNotPaid';
sl_render($board, ['id' => $type === 'air' ? 'air' : 'sea', 'title' => 'Receipt ' . $r['track'], 'board' => $board, 'plain' => true], ['r' => $r, 'canPay' => sl_userIs('client'), 'canStudio' => $canStudio && !sl_userIs('client')]);
