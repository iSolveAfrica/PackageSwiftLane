<?php
// Consolidations, air and sea (Consolidations artboard). Legacy views: views/consolidate/consolidate_list.php, views/consolidate_packages/consolidate_list.php.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_consolidate_list', 'view_consolidate_package_list']);
require_once 'views/sl/data/consolidations.php';
sl_render('Consolidations', ['id' => 'consol', 'title' => 'Consolidations', 'board' => 'Consolidations', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_consolidationsData($user, $core));
