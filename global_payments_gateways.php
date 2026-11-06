<?php
/**
 * Payments: every payment received across air, sea and consolidations
 * (cdb_payments_gateway, cdb_charges_order, cdb_fs_payments). Design: Payments.dc.html.
 * The four legacy payments_gateways_*_list.php pages redirect here with a type filter.
 */
require_once 'loader.php';
require_once 'helpers/finance_switch.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_global_payments', 'view_payment_shipment_list', 'view_payment_list', 'payments_gateways_consolidate_shipment', 'payments_gateways_package_consolidate']);
cdp_financeGuardPage();
require_once 'views/sl/data/finance.php';
$d = sl_paymentsData($user, $core);
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Reference', 'Customer', 'Packages', 'Gateway', 'Method', 'Amount', 'Fee', 'Net', 'Paid at', 'Status']);
    foreach ($d['rows'] as $r) fputcsv($out, [$r['ref'], $r['name'], $r['pkg'], $r['gateway'], $r['method'], $r['amt'], $r['fee'], $r['net'], $r['at'], $r['st']]);
    fclose($out);
    exit;
}
sl_render('Payments', ['id' => 'pay', 'title' => 'Payments', 'board' => 'Payments'], $d);
