<?php
// New consolidation (NewConsolidation artboard). ?type=sea for sea; the old Bootstrap form is retired.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
$type = ($_GET['type'] ?? 'air') === 'sea' ? 'sea' : 'air';
sl_gate($type === 'sea' ? 'add_consolidate_package' : 'add_consolidate_shipment');
if (sl_userIs('client')) { header('Location: error403.php'); exit; }
require_once 'views/sl/data/consolidations.php';
sl_render('NewConsolidation', ['id' => 'consol', 'title' => 'New consolidation', 'board' => 'NewConsolidation', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_newConsolidationData($user, $core, $type));
