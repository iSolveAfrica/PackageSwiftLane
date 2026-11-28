<?php
// Rider app · Wallet (views/sl/pages/RiderWallet.php, data: views/sl/data/rider_money.php).
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (!sl_userIs('driver') && !$user->cdp_hasPermission('view_user_list')) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/rider_money.php';
sl_render('RiderWallet', sl_mxPage(['id' => 'rwallet', 'title' => 'Wallet', 'board' => 'RiderMoney', 'plain' => true, 'scripts' => ['dataJs/sl/rider_app.js']]), sl_riderWalletData($user, $core));
