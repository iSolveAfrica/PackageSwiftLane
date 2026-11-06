<?php
/** Ghana last-mile delivery (Swift Lane design: LastMile). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_pickup_list');
require_once 'views/sl/data/lastmile.php';
sl_render('LastMile', ['id' => 'lastmile', 'title' => 'Last-mile delivery', 'board' => 'LastMile'], sl_lastMileData($user, $core));
