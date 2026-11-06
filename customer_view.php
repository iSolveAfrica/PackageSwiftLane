<?php
// Customer profile (Swift Lane UI): customer 360 for staff. Accepts ?id= (new) and ?user= (legacy links).
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_client_list', 'edit_client']);
require_once 'views/sl/data/customer_profile.php';
$d = sl_customerProfileData($user, $core, (int) ($_GET['id'] ?? $_GET['user'] ?? 0));
if (!$d) {
    header('Location: ' . sl_route('Customers'));
    exit;
}
sl_render('CustomerProfile', ['id' => 'cust', 'title' => $d['name'], 'board' => 'CustomerProfile'], $d);
