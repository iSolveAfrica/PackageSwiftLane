<?php
/** Delivery pricing (M-CDelivPrice): how door delivery is priced, from the delivery configuration. */
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (!sl_userIs('client')) { header('Location: error403.php'); exit; }
require_once 'helpers/sl_mx.php';
require_once 'views/sl/data/customer_app.php';
sl_render('DeliveryPricing', sl_mxPage(['id' => 'deliveries', 'title' => 'Delivery pricing', 'board' => 'CustomerApp', 'scripts' => ['dataJs/sl/customer_app.js']]), sl_caDeliveryPricingData((int) $_SESSION['userid']));
