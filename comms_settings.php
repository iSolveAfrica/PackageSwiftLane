<?php
// Notifications & Messages > Settings: everything about the channels in one
// page - WhatsApp (UltraMsg / Meta Cloud API, link the phone by QR), SMS
// (Hubtel / mNotify), e-mail (SMTP / PHP mail), sending rules (master switch,
// who sends status messages, quiet hours, reminders, campaign approval),
// consent and STOP keywords, King (the WhatsApp bot), costs and a test send
// per channel (views/sl/pages/CommsSettings.php). Administrators (rank 3+);
// provider keys need a super admin or the API-settings permission.
// Saves: ajax/comms/settings_ajax.php.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'views/sl/data/comms_settings.php';
if (!sl_commsSettingsAccess($user)['view']) {
    header('Location: error403.php');
    exit;
}
sl_render('CommsSettings', ['id' => 'commshub', 'title' => 'Message settings', 'board' => 'CommsSettings', 'wide' => true,
    'scripts' => ['dataJs/sl/comms_settings.js']],
    sl_commsSettingsData($user, $core));
