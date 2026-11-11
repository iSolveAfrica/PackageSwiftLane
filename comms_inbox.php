<?php
// WhatsApp Inbox (staff, rank 2+): two-way WhatsApp support. Conversations
// arrive through webhooks/whatsapp.php; the AI assistant answers first when
// it is switched on and hands over to a person.
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
cdp_opsRender('CommsInbox', 'commsinbox', 'WhatsApp Inbox', ['dataJs/comms_inbox.js'], ['ready' => cdp_commEnsureTables()]);
