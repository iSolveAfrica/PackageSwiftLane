<?php
/** "My shipping addresses" block shared by ClientDashboard and UserHome: locker ID and every warehouse address
 * (partials/locker_addresses.php). Expects $d['addresses'] (sl_customerAddresses()) and $d['locker']. */
$laAddr = (array) sl_get($d, 'addresses', []);
$laLocker = (string) sl_get($d, 'locker', '');
$laName = (string) sl_get($d, 'name', '');
$laSteps = false;
$laMore = $GLOBALS['user']->cdp_hasPermission('client_virtual_mail_box_addresses') ? 'client_virtual_mail_box_addresses.php' : '';
include __DIR__ . '/locker_addresses.php';
