<?php
/**
 * Customer history (Communications): everything said to and heard from one
 * customer - every Outbox row (sent, failed, blocked, waiting, cancelled,
 * received), survey answers and preference changes - plus their message
 * preferences and marketing consent, which staff can change for them.
 * Replaces the Customer history tab of the Communication Center (comms_center.php#customers).
 * View views/sl/pages/CommsCustomers.php, scripts dataJs/sl/comms_outbox.js (detail drawer) and
 * dataJs/sl/comms_customers.js, endpoint ajax/comms/outbox_ajax.php.
 */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'views/sl/data/comms_outbox.php';
if (cdp_opsRank() < 2) {
    header('Location: error403.php');
    exit;
}
sl_render('CommsCustomers', [
    'id' => 'commshub',
    'title' => 'Customer history',
    'board' => 'CommsCustomers',
    'wide' => true,
    'styles' => ['assets/swiftlane/pages/CommsOutbox.css'],
    'scripts' => ['dataJs/sl/comms_outbox.js', 'dataJs/sl/comms_customers.js'],
], sl_commsCustomersData($user, $core));
