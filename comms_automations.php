<?php
// Communications > Automations: everything that sends a customer a message,
// with a WhatsApp / SMS / E-mail switch per trigger and the text behind each
// (views/sl/pages/CommsAutomations.php). Staff (rank 2+) read it; administrators
// or the notification-rules permission change it (ajax/comms/automation_ajax.php).
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'views/sl/data/comms_automations.php';
if (cdp_opsRank() < 2) {
    header('Location: error403.php');
    exit;
}
sl_render('CommsAutomations', ['id' => 'commshub', 'title' => 'Automations', 'board' => 'CommsAutomations', 'wide' => true,
    'styles' => ['assets/swiftlane/comms_editor.css'],
    'scripts' => ['dataJs/newsletter_templates.js', 'dataJs/sl/email_builder.js', 'dataJs/sl/comms_editor.js', 'dataJs/sl/comms_automations.js']],
    sl_commsAutomationsData($user));
