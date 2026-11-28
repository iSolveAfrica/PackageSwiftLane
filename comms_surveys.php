<?php
// Communications > Surveys: the satisfaction surveys sent after a delivery or
// pickup. Results (scores, trends, comments, by rider and branch), the survey
// forms (questions, when they are sent, channels) and the in-app pop-up
// (views/sl/pages/CommsSurveys.php). Staff (rank 2+) read it; administrators
// change it (ajax/comms/surveys_ajax.php). ?export=csv|xlsx downloads the answers.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'views/sl/data/comms_surveys.php';
if (cdp_opsRank() < 2) {
    header('Location: error403.php');
    exit;
}
if (isset($_GET['export'])) {
    sl_commsSurveysExport((string) $_GET['export']);
}
sl_render('CommsSurveys', ['id' => 'commshub', 'title' => 'Surveys', 'board' => 'CommsSurveys', 'wide' => true,
    'styles' => ['assets/swiftlane/comms_editor.css'],
    'scripts' => ['dataJs/newsletter_templates.js', 'dataJs/sl/email_builder.js', 'dataJs/sl/comms_editor.js', 'dataJs/sl/comms_surveys.js']],
    sl_commsSurveysData($user));
