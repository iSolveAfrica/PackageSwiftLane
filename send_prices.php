<?php
/** Send prices (SendPrices.dc.html): send the current tariff sheet to chosen customers by WhatsApp, e-mail or SMS. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['send_prices', 'push_notifications']);
require_once 'views/sl/data/send_prices.php';
sl_render('SendPrices', ['id' => 'pay', 'title' => 'Send prices', 'board' => 'SendPrices'], sl_sendPricesData($user, $core));
