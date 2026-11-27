<?php
/** Developer console: Infrastructure (dark DevOps theme). Data: views/sl/data/dev_infra.php. Writes: ajax/sl/dev_infra_ajax.php. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'views/sl/data/dev_infra.php';
sl_dinfGate($user, 'infrastructure');
sl_render('DevInfrastructure', ['id' => 'devinfra', 'title' => 'Infrastructure', 'board' => 'DevInfrastructure', 'theme' => 'dk dvx', 'styles' => ['assets/swiftlane/dev_theme.css'], 'scripts' => ['dataJs/sl/dev_infra.js']], sl_dinfInfraData($user, $core));
