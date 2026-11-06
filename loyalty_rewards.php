<?php
/** Loyalty, referrals and rewards (LoyaltyRewards.dc.html). Staff with manage_loyalty. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('manage_loyalty');
require_once 'views/sl/data/loyalty.php';
sl_render('LoyaltyRewards', ['id' => 'loyalty', 'title' => 'Loyalty, referrals and rewards', 'board' => 'LoyaltyRewards', 'wide' => true], sl_loyaltyData($user, $core));
