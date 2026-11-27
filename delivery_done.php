<?php
/** Delivery status (M-CDelivDone): one of the customer's own delivery requests, or their list without ?id. */
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (!sl_userIs('client')) { header('Location: error403.php'); exit; }
header('Cache-Control: private, no-store');
require_once 'helpers/sl_mx.php';
require_once 'views/sl/data/customer_app.php';
$id = (int) ($_GET['id'] ?? 0);
$d = sl_caDeliveryDoneData((int) $_SESSION['userid'], $id);
// A quote still waiting for approval is handled on the request page, where the approve button lives.
if ($d['request'] && $d['request']->state === 'quoted') { header('Location: ' . sl_route('DeliveryRequest', ['id' => $id])); exit; }
sl_render('DeliveryDone', sl_mxPage(['id' => 'deliveries', 'title' => $d['request'] ? 'Delivery #' . (int) $d['request']->id : 'My deliveries', 'board' => 'CustomerApp', 'scripts' => ['dataJs/sl/customer_app.js']]), $d);
