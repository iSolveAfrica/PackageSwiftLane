<?php
// Staff activity (Swift Lane UI): one person's day from helpers/staff_activity.php.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_staff_activity', 'view_staff_productivity']);
require_once 'views/sl/data/staff_activity.php';
if (($_GET['export'] ?? '') === 'csv') {
    if (!$user->cdp_hasPermission('export_staff_productivity')) { header('Location: error403.php'); exit; }
    $d = sl_staffActivityData($user, $core);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="staff-activity-' . (int) $d['id'] . '-' . $d['day'] . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Time', 'Type', 'Action', 'Reference']);
    foreach (($d['dayRow']['acts'] ?? []) as $a) fputcsv($out, [$a['at'], $a['verbL'], $a['label'], $a['ref']]);
    fclose($out);
    exit;
}
sl_render('StaffActivity', ['id' => 'staff', 'title' => 'Staff activity', 'board' => 'StaffActivity'], sl_staffActivityData($user, $core));
