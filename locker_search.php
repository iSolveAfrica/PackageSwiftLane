<?php
// Smart Search: search everything on the system (Swift Lane UI: views/sl/pages/LockerSearch.php).
// Kept at this address so existing Locker Search links and bookmarks still work.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (sl_userIs('client')) { header('Location: ' . sl_route('LiveTracking')); exit; }
require_once 'views/sl/data/smart_search.php';
sl_render('LockerSearch', ['id' => 'locker', 'title' => 'Smart Search', 'board' => 'LockerSearch', 'wide' => true, 'styles' => ['assets/swiftlane/pages/ops.css']], sl_smartSearchData($user, $core));
