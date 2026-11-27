<?php
/** Developer console: DevOps overview (Swift Lane design: DevOverview, dark DevOps theme). Data: views/sl/data/dev_observe.php. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'views/sl/data/dev_observe.php';
sl_dvxGate($user, 'overview');
sl_render('DevOverview', ['id' => 'devov', 'title' => 'DevOps overview', 'board' => 'DevOverview', 'theme' => 'dk dvx', 'styles' => ['assets/swiftlane/dev_theme.css'], 'scripts' => ['dataJs/sl/dev_observe.js']], sl_dvxOverviewData($user, $core));
