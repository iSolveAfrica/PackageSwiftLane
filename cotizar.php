<?php
// Public rate calculator (QuoteCalculator board, views/sl/pages/QuoteCalculator.php).
// Reads public tariffs only (cdb_shipping_fees with client_id 0/NULL); no writes.
require_once("loader.php");
require_once 'helpers/sl_view.php';
require_once 'views/sl/data/portal.php';

$user = new User();
$core = new Core();
$d = sl_quoteData($core);
$d['loggedIn'] = $user->cdp_loginCheck();
$d['site'] = $core->site_name ?? 'Swift Lane';
sl_render('QuoteCalculator', ['title' => 'Rate calculator', 'board' => 'QuoteCalculator', 'plain' => true], $d);
