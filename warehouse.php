<?php
// Warehouse floor (Warehouse artboard). Legacy view (retired): views/courier/warehouse_view.php (bulk delivery list).
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('warehouse_view');
require_once 'views/sl/data/warehouse.php';
sl_render('Warehouse', ['id' => 'warehouse', 'title' => 'Warehouse', 'board' => 'Warehouse', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_warehouseData($user, $core));
