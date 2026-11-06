<?php
// Dispatch board (Swift Lane UI): unassigned pickups/deliveries and riders.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['dispatch_riders', 'assign_drivers', 'assign_driver_pickup']);
require_once 'views/sl/data/riders.php';
sl_render('RiderDispatch', ['id' => 'drivers', 'title' => 'Dispatch board', 'board' => 'RiderDispatch'], sl_riderDispatchData($user, $core));
