<?php
// Communications > Status messages: every status x channel (WhatsApp, SMS,
// e-mail, in-app), predefined for each status (helpers/notify_drafts.php),
// edited in the shared editor (views/sl/pages/CommsStatuses.php). Staff
// (rank 2+) read it; administrators or the notification-rules permission change it.
// ?status=<id> opens that status's editor.
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
sl_render('CommsStatuses', ['id' => 'commshub', 'title' => 'Status messages', 'board' => 'CommsStatuses', 'wide' => true,
    'styles' => ['assets/swiftlane/comms_editor.css'],
    'scripts' => ['dataJs/newsletter_templates.js', 'dataJs/sl/email_builder.js', 'dataJs/sl/comms_editor.js', 'dataJs/sl/comms_automations.js']],
    sl_commsStatusesData($user));
