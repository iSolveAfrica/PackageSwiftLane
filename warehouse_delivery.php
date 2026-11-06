<?php
// Warehouse Delivery (WarehouseDelivery artboard). By consolidation: ?view=consolidations (?legacy=1 still lands there).
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_warehouse_delivery');
if (($_GET['view'] ?? '') === 'consolidations' || !empty($_GET['legacy'])) {
    // Delivery by consolidation (replaces the old consolidation tree).
    require_once 'views/sl/data/ops_forms.php';
    sl_render('WarehouseConsolidation', ['id' => 'warehouse', 'title' => 'Warehouse Delivery · by consolidation', 'board' => 'WarehouseDelivery', 'styles' => ['assets/swiftlane/pages/ops.css'], 'scripts' => ['dataJs/sl/ops_forms.js']], sl_wdConsolData($user, $core, 0));
    exit;
}
require_once 'views/sl/data/warehouse.php';
sl_render('WarehouseDelivery', ['id' => 'warehouse', 'title' => 'Warehouse Delivery', 'board' => 'WarehouseDelivery', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_warehouseDeliveryData($user, $core));
