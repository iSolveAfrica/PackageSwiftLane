<?php
// Handed-out history (WarehousePickups artboard). New page.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_warehouse_pickups', 'view_warehouse_delivery']);
require_once 'views/sl/data/warehouse.php';
if (($_GET['export'] ?? '') === 'csv') {
    $h = sl_whHistory(trim((string) ($_GET['q'] ?? '')), (string) ($_GET['kind'] ?? ''), in_array((int) ($_GET['days'] ?? 1), [1, 7, 30], true) ? (int) $_GET['days'] : 1, 1, 5000);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="warehouse_pickups_' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w');
    fputcsv($o, ['Package', 'Customer', 'Locker', 'Weight', 'Handed over', 'By', 'When', 'Code']);
    foreach ($h['rows'] as $r) fputcsv($o, [$r['id'], $r['cust'], $r['locker'], $r['w'], $r['kind'], $r['by'], $r['when'], $r['code']]);
    exit;
}
sl_render('WarehousePickups', ['id' => 'warehouse', 'title' => 'Warehouse Pickups', 'board' => 'WarehousePickups', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_warehousePickupsData($user, $core));
