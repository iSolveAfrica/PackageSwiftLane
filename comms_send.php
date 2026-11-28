<?php
/**
 * Send (Communications): one person, several people or a campaign, by
 * WhatsApp, SMS, e-mail and the in-app bell, with the phone previews, the
 * drag-and-drop e-mail builder and Write with King. Replaces Compose message,
 * Push notifications (except the invoice push), the Email builder and the
 * Campaigns / Segments tabs of the old Communication Center.
 * View views/sl/pages/CommsSend.php, script dataJs/sl/comms_send.js,
 * endpoint ajax/comms/send_ajax.php.
 */
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
require_once 'views/sl/data/comms_send.php';
sl_render('CommsSend', [
    'id' => 'commshub',
    'title' => 'Send a message',
    'board' => 'CommsSend',
    'wide' => true,
    'styles' => ['assets/swiftlane/comms_editor.css'],
    'scripts' => ['dataJs/newsletter_templates.js', 'dataJs/sl/email_builder.js', 'dataJs/sl/comms_editor.js', 'dataJs/sl/comms_send.js'],
], sl_commsSendData($user, $core));
