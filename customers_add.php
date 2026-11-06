<?php
// Add customer or recipient (Swift Lane UI). Customer creation keeps the e-mail OTP flow of ajax/customers/*.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(($_GET['type'] ?? '') === 'recipient' ? ['recipients_add', 'add_client'] : 'add_client');
require_once 'helpers/otp_settings.php';
$ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : ['is_restricted' => false, 'agency_id' => null];
$scope = !empty($ctx['is_restricted']) ? ' AND agency_id = ' . (int) $ctx['agency_id'] : '';
$d = [
    'type' => ($_GET['type'] ?? '') === 'recipient' ? 'recipient' : 'customer',
    'countries' => sl_rows('SELECT id, name FROM cdb_countries WHERE is_active = 1 ORDER BY name LIMIT 300'),
    'customers' => sl_rows("SELECT id, fname, lname, locker FROM cdb_users WHERE userlevel = 1 $scope ORDER BY fname, lname LIMIT 1000"),
    'owner' => (int) ($_GET['owner'] ?? 0),
    'otp' => function_exists('cdp_otpEnabled') ? cdp_otpEnabled() : true,
    'lockerHint' => 'Next free number, on save',
    'agency' => !empty($ctx['is_restricted']) ? (string) sl_scalar('SELECT name_off FROM cdb_offices WHERE id = :a', [':a' => (int) $ctx['agency_id']], 'Your agency') : 'Main',
    'canRecip' => $user->cdp_hasPermission(['recipients_add', 'view_recipients']),
];
sl_render('AddCustomer', ['id' => 'cust', 'title' => 'Add customer', 'board' => 'AddCustomer'], $d);
