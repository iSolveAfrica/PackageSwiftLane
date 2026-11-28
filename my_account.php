<?php
/** My account (M-CAccount): the customer's account hub with a row for every customer screen. */
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (!sl_userIs('client')) { header('Location: error403.php'); exit; }
require_once 'helpers/sl_mx.php';
require_once 'views/sl/data/customer_app.php';
sl_render('MyAccount', sl_mxPage(['id' => 'account', 'title' => 'My account', 'board' => 'CustomerApp', 'scripts' => ['dataJs/sl/customer_app.js']]), sl_caAccountData($user, $core, $userData));
