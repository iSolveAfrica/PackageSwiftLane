<?php
// Sea Consolidations (Consolidations artboard, sea mode: cdb_consolidate_packages). Air lives in consolidate_list.php.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_consolidate_package_list');
require_once 'views/sl/data/consolidations.php';
sl_render('Consolidations', ['id' => 'consol_sea', 'title' => 'Sea Consolidations', 'board' => 'ConsolidationsSea', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_consolidationsData($user, $core, 'sea'));
