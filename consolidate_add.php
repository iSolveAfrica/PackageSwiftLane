<?php
// New consolidation (NewConsolidation artboard). ?type=sea for sea; legacy form at ?legacy=1 (views/consolidate/consolidate_add.php).
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
$type = ($_GET['type'] ?? 'air') === 'sea' ? 'sea' : 'air';
sl_gate($type === 'sea' ? 'add_consolidate_package' : 'add_consolidate_shipment');
if (!empty($_GET['legacy']) || $_SERVER['REQUEST_METHOD'] === 'POST') { $permissions = $user->cdp_getUserPermissions(); include $type === 'sea' ? 'views/consolidate_packages/consolidate_add.php' : 'views/consolidate/consolidate_add.php'; exit; }
if (sl_userIs('client')) { header('Location: error403.php'); exit; }
require_once 'views/sl/data/consolidations.php';
sl_render('NewConsolidation', ['id' => 'consol', 'title' => 'New consolidation', 'board' => 'NewConsolidation', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_newConsolidationData($user, $core, $type));
