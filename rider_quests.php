<?php
// Rider app · Quests (views/sl/pages/RiderQuests.php, data: views/sl/data/rider_money.php).
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
sl_render('RiderQuests', sl_mxPage(['id' => 'rquests', 'title' => 'Quests', 'board' => 'RiderMoney', 'plain' => true, 'scripts' => ['dataJs/sl/rider_app.js']]), sl_riderQuestsData($user, $core));
