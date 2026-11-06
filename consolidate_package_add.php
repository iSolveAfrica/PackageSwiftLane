<?php
// New sea consolidation: the NewConsolidation page in sea mode. Legacy form (retired).
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('add_consolidate_package');
header('Location: consolidate_add.php?type=sea');
exit;
