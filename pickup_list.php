<?php
// Pickups (Pickups artboard). Legacy view: views/pickup/pickup_list.php.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_pickup_list');
require_once 'views/sl/data/pickups.php';
sl_render('Pickups', ['id' => 'pickup', 'title' => 'Pickups', 'board' => 'Pickups', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_pickupsData($user, $core));
