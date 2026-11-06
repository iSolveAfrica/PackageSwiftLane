<?php
// Customer: request a pickup (SchedulePickup artboard). Legacy view: views/pickup/pickup_add.php (retired).
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('pickup_add');
if (!sl_userIs('client')) { header('Location: pickup_add_full.php'); exit; }
require_once 'views/sl/data/pickups.php';
sl_render('SchedulePickup', ['id' => 'pickup', 'title' => 'Request a pickup', 'board' => 'SchedulePickup', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_schedulePickupData($user, $core));
