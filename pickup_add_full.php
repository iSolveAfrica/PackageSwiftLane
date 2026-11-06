<?php
// Staff: schedule a pickup (SchedulePickup artboard). Legacy view: views/pickup/pickup_add_full.php (?legacy=1).
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('add_full_pickup');
if (!empty($_GET['legacy'])) { $permissions = $user->cdp_getUserPermissions(); include 'views/pickup/pickup_add_full.php'; exit; }
if (sl_userIs('client')) { header('Location: pickup_add.php'); exit; }
require_once 'views/sl/data/pickups.php';
sl_render('SchedulePickup', ['id' => 'pickup', 'title' => 'Schedule a pickup', 'board' => 'SchedulePickup', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_schedulePickupData($user, $core));
