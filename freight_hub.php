<?php
/** Freight hub (Swift Lane design: FreightHub). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_freight');
require_once 'views/sl/data/freight.php';
sl_render('FreightHub', ['id' => 'frhub', 'title' => 'Freight hub', 'board' => 'FreightHub'], sl_freightHubData($user, $core));
