<?php
/** Rewards (M-CLoyalty): the customer's points, tier, referral code and points history. */
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (!sl_userIs('client')) { header('Location: error403.php'); exit; }
require_once 'helpers/sl_mx.php';
require_once 'views/sl/data/customer_app.php';
sl_render('MyRewards', sl_mxPage(['id' => 'rewards', 'title' => 'Rewards', 'board' => 'CustomerApp', 'scripts' => ['dataJs/sl/customer_app.js']]), sl_caRewardsData((int) $_SESSION['userid']));
