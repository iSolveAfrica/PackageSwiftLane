<?php
// Route prices (views/sl/pages/RateBands.php): the public weight bands the rate calculator quotes from.
require_once 'loader.php'; $user = new User(); $core = new Core();
require_once 'helpers/sl_view.php'; require_once 'helpers/sl_mx.php';
sl_gate();
require_once 'views/sl/data/rate_bands.php';
$can = sl_tariffsCan($user);
if (!$can['view']) { header('Location: error403.php'); exit; }
sl_render('RateBands', ['id' => 'tariffs', 'title' => 'Route prices', 'board' => 'Tariffs'], sl_bandsData($user, $core));
