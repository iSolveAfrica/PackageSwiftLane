<?php
// New sea consolidation: the NewConsolidation page in sea mode. Legacy form at ?legacy=1.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('add_consolidate_package');
if (!empty($_GET['legacy']) || $_SERVER['REQUEST_METHOD'] === 'POST') { $permissions = $user->cdp_getUserPermissions(); include 'views/consolidate_packages/consolidate_add.php'; exit; }
header('Location: consolidate_add.php?type=sea');
exit;
