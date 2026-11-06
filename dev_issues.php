<?php
/** Developer console: Issues and change log (Swift Lane design: DevIssues, dark). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_developer');
require_once 'views/sl/data/dev.php';
sl_render('DevIssues', ['id' => 'devissues', 'title' => 'Issues and change log', 'board' => 'DevIssues', 'theme' => 'dk'], sl_devIssuesData($user, $core));
