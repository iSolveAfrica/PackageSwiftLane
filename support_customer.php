<?php
/** Customer 360 for agents (CsCustomer.dc.html). Staff rank 2+. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/support_king.php';
sl_gate();
if (!cdp_csIsAgent($user)) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/support.php';
$d = sl_csCustomerData($user, $core, (int) ($_GET['id'] ?? 0));
if ($d['customer']) {
    $d['customerPrefill'] = $d['customer']['id'];
    $d['customerPrefillName'] = $d['customer']['name'];
}
sl_render('CsCustomer', ['id' => 'tickets', 'title' => $d['customer'] ? $d['customer']['name'] : 'Customer', 'board' => 'CsCustomer', 'scripts' => ['dataJs/sl/cs_common.js']], $d);
