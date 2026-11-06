<?php
/** Freight partners (Swift Lane design: FreightPartners). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_freight');
require_once 'views/sl/data/freight.php';
sl_render('FreightPartners', ['id' => 'frpart', 'title' => 'Freight partners', 'board' => 'FreightPartners'], sl_freightPartnersData($user, $core));
