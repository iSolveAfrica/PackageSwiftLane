<?php
// Warehouse Delivery - one consolidation (Swift Lane UI: views/sl/pages/WarehouseConsolidation.php).
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_warehouse_delivery');
require_once 'views/sl/data/ops_forms.php';
$d = sl_wdConsolData($user, $core, (int) ($_GET['id'] ?? 0));
if ((int) $d['cid'] <= 0) { header('Location: warehouse_delivery.php?view=consolidations'); exit; }
sl_render('WarehouseConsolidation', ['id' => 'warehouse', 'title' => $d['ref'] . ' · Warehouse Delivery', 'board' => 'WarehouseDelivery', 'styles' => ['assets/swiftlane/pages/ops.css'], 'scripts' => ['dataJs/sl/ops_forms.js']], $d);
