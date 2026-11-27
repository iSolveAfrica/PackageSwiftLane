<?php
/**
 * WhatsApp screen (staff, rank 2+): the company's WhatsApp number inside the
 * app, on UltraMsg (the instance the app already sends with). Link the phone
 * with a QR code, read every chat and group with its history, reply with text,
 * pictures and documents, see delivery and read ticks, and work each chat with
 * the customer and their shipments beside it (assign, close, hand to King,
 * King's reply suggestions, quick replies, tags, open a ticket). Administrators
 * also export contacts and group members (CSV / Excel) and save them as
 * contacts campaigns can reach.
 * View views/sl/pages/WhatsAppWeb.php, script dataJs/sl/whatsapp_web.js,
 * endpoint ajax/comms/wa_web_ajax.php, provider client helpers/ultramsg.php.
 */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'helpers/querys.php';
require_once 'views/sl/data/whatsapp_web.php';
if (!sl_waWebAllowed()) {
    header('Location: error403.php');
    exit;
}
sl_render('WhatsAppWeb', [
    'id' => 'commsinbox',
    'title' => 'WhatsApp',
    'board' => 'WhatsAppWeb',
    'wide' => true,
    'scripts' => ['dataJs/sl/whatsapp_web.js'],
], sl_waWebData($user));
