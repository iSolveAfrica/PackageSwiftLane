<?php
/** Developer console: Services (Swift Lane design: DevServices, dark DevOps theme). Data: views/sl/data/dev_observe.php. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'views/sl/data/dev_observe.php';
sl_dvxGate($user, 'services');
sl_render('DevServices', ['id' => 'devsvc', 'title' => 'Services', 'board' => 'DevServices', 'theme' => 'dk dvx', 'styles' => ['assets/swiftlane/dev_theme.css'], 'scripts' => ['dataJs/sl/dev_observe.js']], sl_dvxServicesData($user, $core));
