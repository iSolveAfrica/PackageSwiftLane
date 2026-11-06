<?php
/** Developer console: Developer overview (Swift Lane design: DevOverview, dark). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_developer');
require_once 'views/sl/data/dev.php';
sl_render('DevOverview', ['id' => 'devov', 'title' => 'Developer overview', 'board' => 'DevOverview', 'theme' => 'dk'], sl_devOverviewData($user, $core));
