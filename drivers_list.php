<?php
// Riders (Swift Lane UI): split sheet and rider cards. Legacy drivers_add/edit pages stay for the forms.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_user_list');
require_once 'views/sl/data/riders.php';
$d = sl_driversData($user, $core);
if (($_GET['export'] ?? '') === 'csv' && $d['canMoney']) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rider-split-' . $d['period'] . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Rider', 'Vehicle', 'Assigned', 'Done', 'Fees', 'Rider %', 'Rider gets', 'We get', 'Status']);
    foreach ($d['rows'] as $r) fputcsv($out, [$r['name'], $r['veh'], $r['assigned'], $r['done'], $r['fees'], $r['pct'], $r['rid'], $r['sw'], $r['st']]);
    fclose($out);
    exit;
}
sl_render('Drivers', ['id' => 'drivers', 'title' => 'Riders', 'board' => 'Drivers'], $d);
