<?php
/** Accounts Receivable (AccountsReceivable.dc.html): aging of unpaid invoices (status_invoice 23, due_date). */
require_once 'loader.php';
require_once 'helpers/finance_switch.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_receivable_accounts');
cdp_financeGuardPage();
require_once 'views/sl/data/finance.php';
$d = sl_arData($user, $core);
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="receivables-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice', 'Type', 'Customer', 'Issued', 'Due', 'Amount', 'Balance', 'Status']);
    foreach ($d['rows'] as $r) fputcsv($out, [$r['inv'], $r['kind'], $r['name'], $r['issued'], $r['due'], $r['amt'], $r['bal'], $r['st']]);
    fclose($out);
    exit;
}
sl_render('AccountsReceivable', ['id' => 'ar', 'title' => 'Accounts Receivable', 'board' => 'AccountsReceivable'], $d);
