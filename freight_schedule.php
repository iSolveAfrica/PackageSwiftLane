<?php
/** Departures and arrivals (Swift Lane design: FreightSchedule). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_freight');
require_once 'views/sl/data/freight.php';
sl_render('FreightSchedule', ['id' => 'frsched', 'title' => 'Departures and arrivals', 'board' => 'FreightSchedule'], sl_freightScheduleData($user, $core));
