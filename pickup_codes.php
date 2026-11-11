<?php
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (!sl_userIs('client')) { header('Location: error403.php'); exit; }
// Codes are credentials for handover: never cache them or accept an owner from the URL.
header('Cache-Control: private, no-store');
header('Referrer-Policy: same-origin');
require_once 'views/sl/data/pickup_codes.php';
sl_render('PickupCodes', ['id' => 'codes', 'title' => 'My pickup codes', 'board' => 'PickupCodes'], sl_pickupCodesData((int) $_SESSION['userid']));
