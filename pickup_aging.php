<?php
// Pickup aging (PickupAging artboard). Legacy report kept at ?legacy=1: views/reports/pickup_aging/pickup_aging.php.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_pickup_aging');
if (!empty($_GET['legacy'])) { $permissions = $user->cdp_getUserPermissions(); include 'views/reports/pickup_aging/pickup_aging.php'; exit; }
require_once 'helpers/pickup_aging.php';
require_once 'views/sl/data/warehouse.php';
$d = sl_pickupAgingData($user, $core);
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pickup_aging_' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w');
    fputcsv($o, ['Tracking', 'Mode', 'Customer', 'Locker', 'Status', 'Ready since', 'Days waiting', 'Pickup code']);
    foreach ($d['rows'] as $r) fputcsv($o, [$r['id'], $r['module'], $r['cust'], $r['locker'], $r['st'], $r['ready'], $r['dn'], $r['code']]);
    exit;
}
sl_render('PickupAging', ['id' => 'pickup', 'title' => 'Pickup Aging', 'board' => 'PickupAging', 'styles' => ['assets/swiftlane/pages/ops.css']], $d);
