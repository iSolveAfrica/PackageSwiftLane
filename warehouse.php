<?php
// Warehouse floor (Warehouse artboard). Legacy view kept at ?legacy=1: views/courier/warehouse_view.php (bulk delivery list).
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('warehouse_view');
if (!empty($_GET['legacy'])) { $permissions = $user->cdp_getUserPermissions(); include 'views/courier/warehouse_view.php'; exit; }
require_once 'views/sl/data/warehouse.php';
sl_render('Warehouse', ['id' => 'warehouse', 'title' => 'Warehouse', 'board' => 'Warehouse', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_warehouseData($user, $core));
