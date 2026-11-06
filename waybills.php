<?php
/** Waybills and manifests (Swift Lane design: Waybills). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_freight');
require_once 'views/sl/data/freight.php';
sl_render('Waybills', ['id' => 'frwb', 'title' => 'Waybills and manifests', 'board' => 'Waybills'], sl_waybillsData($user, $core));
