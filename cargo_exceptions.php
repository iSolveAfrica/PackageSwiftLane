<?php
/** Cargo exceptions (Swift Lane design: CargoExceptions). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_freight');
require_once 'views/sl/data/freight.php';
sl_render('CargoExceptions', ['id' => 'frex', 'title' => 'Cargo exceptions', 'board' => 'CargoExceptions'], sl_cargoExceptionsData($user, $core));
