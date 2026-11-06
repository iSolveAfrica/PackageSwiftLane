<?php
// Locker search (LockerSearch artboard). Legacy view (retired): views/locker/locker_search.php.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_shipment_list');
if (sl_userIs('client')) { header('Location: error403.php'); exit; }
require_once 'views/sl/data/warehouse.php';
sl_render('LockerSearch', ['id' => 'locker', 'title' => 'Locker Search', 'board' => 'LockerSearch', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_lockerSearchData($user, $core));
