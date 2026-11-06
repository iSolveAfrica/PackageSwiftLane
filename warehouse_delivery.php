<?php
// Warehouse Delivery (WarehouseDelivery artboard). The consolidation tree (views/courier/warehouse_delivery.php) stays at ?legacy=1.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_warehouse_delivery');
if (!empty($_GET['legacy'])) { $permissions = $user->cdp_getUserPermissions(); include 'views/courier/warehouse_delivery.php'; exit; }
require_once 'views/sl/data/warehouse.php';
sl_render('WarehouseDelivery', ['id' => 'warehouse', 'title' => 'Warehouse Delivery', 'board' => 'WarehouseDelivery', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_warehouseDeliveryData($user, $core));
