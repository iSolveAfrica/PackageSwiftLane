<?php
/** Developer console: Services (Swift Lane design: DevServices, dark). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_developer');
require_once 'views/sl/data/dev.php';
sl_render('DevServices', ['id' => 'devsvc', 'title' => 'Services', 'board' => 'DevServices', 'theme' => 'dk'], sl_devServicesData($user, $core));
