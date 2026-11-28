<?php
// Hand-out Log (Warehouse menu): every package handed over at the warehouse, by whom, with staff totals per period.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_handover_log', 'view_warehouse_delivery', 'warehouse_deliver_package', 'view_staff_activity']);
if (sl_userIs('client')) { header('Location: error403.php'); exit; }
require_once 'views/sl/data/handover_log.php';
$d = sl_handoverLogData($user, $core);
if ($d['export'] ?? false) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="hand-out-log-' . $d['from'] . '-to-' . $d['to'] . '.csv"');
    $out = fopen('php://output', 'w');
    $cell = fn($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v;
    fputcsv($out, ['Date and time', 'Tracking', 'Mode', 'Customer', 'Locker', 'Handed over by', 'Type', 'Pickup code', 'Skip reason', 'Paid', 'Weight']);
    foreach ($d['rows'] as $r) fputcsv($out, array_map($cell, [$r['dt'], $r['trk'], $r['module'], $r['cust'], $r['lock'], $r['by'], $r['kind'], $r['methodL'], $r['reason'], $r['paid'] ? 'yes' : 'no', $r['w']]));
    fputcsv($out, []);
    fputcsv($out, ['Staff member', 'Packages', 'Customers', 'Code skipped', 'Per day', 'Today', 'This week', 'This month']);
    foreach ($d['staffRows'] as $s) fputcsv($out, array_map($cell, [$s['name'], $s['n'], $s['cust'], $s['sk'], $s['perDay'], $s['today'], $s['week'], $s['month']]));
    exit;
}
sl_render('HandoverLog', ['id' => 'whlog', 'title' => 'Hand-out Log', 'board' => 'HandoverLog', 'styles' => ['assets/swiftlane/pages/ops.css']], $d);
