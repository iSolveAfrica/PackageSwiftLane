<?php
// Communication Center (staff, rank 2+): automations, templates, campaigns,
// segments, outbox, customer history and preferences, surveys, delivery
// bookings, settings. See helpers/comms.php and docs/COMMUNICATIONS.md.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'helpers/querys.php';
require_once 'helpers/comms.php';
if (!cdp_commIsStaff($user)) {
    header('Location: error403.php');
    exit;
}
cdp_opsRender('CommsCenter', 'commscenter', 'Communication Center', ['dataJs/comms.js'], ['ready' => cdp_commEnsureTables(), 'isAdmin' => cdp_commIsAdmin($user)]);
