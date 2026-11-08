<?php
/** CSV export of the filtered air shipment list (same filters as the page). */
require_once CDP_ROOT . '/helpers/activity_log.php';
$_GET['per'] = 100;
$d = sl_airShipmentsData($user, $core);
if (function_exists('cdp_activityLogDocument')) {
    cdp_activityLogDocument();
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="shipments-' . date('Ymd') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Tracking', 'Carrier tracking', 'Date & time', 'Sender', 'Locker', 'Recipient', 'ETA', 'Weight', 'Total', 'Payment', 'Status']);
foreach ($d['rows'] as $r) {
    fputcsv($out, [$r['track'], $r['carrier'], $r['date'] . ($r['time'] ? ' ' . $r['time'] : ''), $r['sender'], $r['from'], $r['name'], $r['eta'], $r['kg'], $r['total'], $r['pay'], $r['st']]);
}
fclose($out);
