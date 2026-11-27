<?php
/** "Everything" (design M-Menu): every page the person may open, in the sidebar's groups. Phones only; a computer has the sidebar. */
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'helpers/sl_nav.php';
require_once 'helpers/sl_phone.php';
if (sl_userIs('client')) { header('Location: ' . sl_route('MyAccount')); exit; }
if (!sl_isPhone()) { header('Location: index.php'); exit; }
$unread = (int) sl_scalar('SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0', [':u' => (int) $_SESSION['userid']]);
sl_render('Menu', ['id' => 'menu', 'title' => 'Everything'], ['groups' => sl_navResolve(''), 'unread' => $unread]);
