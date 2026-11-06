<?php
/** Developer console: Infrastructure (Swift Lane design: DevInfrastructure, dark). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_developer');
require_once 'views/sl/data/dev.php';
sl_render('DevInfrastructure', ['id' => 'devinfra', 'title' => 'Infrastructure', 'board' => 'DevInfrastructure', 'theme' => 'dk'], sl_devInfraData($user, $core));
