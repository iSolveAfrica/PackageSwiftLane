<?php
/** CSV export of the filtered customer list (same filters as the page). */
$d = sl_customersData($user, $core);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="customers-' . date('Ymd') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Name', 'Email', 'Locker', 'Phone', 'City', 'Parcels', 'Balance', 'Status']);
foreach ($d['rows'] as $r) {
    fputcsv($out, [$r['name'], $r['mail'], $r['locker'], $r['phone'], $r['city'], $r['n'], $r['bal'], $r['st']]);
}
fclose($out);
