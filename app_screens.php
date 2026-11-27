<?php
/** Every phone screen of the design and the page that serves it on the phone, in the installed app and on a computer. */
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/sl_mx.php';
sl_gate('view_tools');
require_once 'views/sl/data/app_screens.php';
require_once 'helpers/sl_device.php';
$rows = sl_appScreens();
foreach ($rows as &$r) { $r[] = is_file(CDP_ROOT . '/' . strtok($r[3], '?#')); }
unset($r);
sl_render('AppScreens', sl_mxPage(['id' => 'screens', 'title' => 'App screens', 'board' => '']), ['rows' => $rows, 'device' => sl_device()]);
