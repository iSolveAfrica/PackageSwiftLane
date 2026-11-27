<?php
/** Developer console: Issues and change log (Swift Lane design: DevIssues, dark). Change log generated from commits. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_developer');
require_once 'helpers/dev_github.php';
// tickets need the "issues" section; the change log alone needs "changelog"
sl_ghPageGate($user, sl_ghCan($user, 'issues') ? 'issues' : 'changelog');
require_once 'views/sl/data/dev_deploy.php';
sl_render('DevIssues', ['id' => 'devissues', 'title' => 'Issues and change log', 'board' => 'DevIssues', 'theme' => 'dk dvx', 'styles' => sl_dplStyles(), 'scripts' => ['dataJs/sl/dev_deploy.js']], sl_dplIssuesData($user, $core));
