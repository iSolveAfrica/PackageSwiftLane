<?php
/** Transactions (Transactions.dc.html): every payment in and refund out, all sources. */
require_once 'loader.php';
require_once 'helpers/finance_switch.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_transactions');
cdp_financeGuardPage();
require_once 'views/sl/data/finance.php';
$d = sl_transactionsData($user, $core);
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="transactions-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Reference', 'Date', 'Customer', 'Invoice', 'Method', 'Amount', 'Status']);
    foreach ($d['rows'] as $r) fputcsv($out, [$r['ref'], $r['at'], $r['name'], $r['inv'], $r['method'], $r['amt'], $r['st']]);
    fclose($out);
    exit;
}
sl_render('Transactions', ['id' => 'fin', 'title' => 'Transactions', 'board' => 'Transactions'], $d);
