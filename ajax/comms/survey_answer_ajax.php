<?php
/**
 * The in-app survey pop-up (views/sl/inc/survey_prompt.php): a signed-in
 * customer answers, or asks to be asked later, about THEIR OWN survey.
 *
 * POST only (CSRF checked by require_login). action =
 *   answer   id, a[<question key>] (multi: a[<key>][])   save the answers
 *   shown    id                                           the pop-up was shown (counts towards the limit)
 *   later    id                                           ask again after the gap
 *   never    id                                           do not show the pop-up for this survey again
 * Answers {success, message, thanks} or {success: false, message, errors{key: text}}.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/comms_surveys.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
$uid = (int) ($_SESSION['userid'] ?? 0);
$id = (int) ($_POST['id'] ?? 0);
if ($uid <= 0 || $id <= 0 || !cdp_surveyEnsure()) {
    sl_json(['success' => false, 'message' => 'This survey is no longer open.'], 404);
}
// Only the customer the survey was sent to.
$s = cdp_opsOne("SELECT id, user_id, responded_at, sent_at FROM cdb_comm_surveys WHERE id = :id AND user_id = :u", [':id' => $id, ':u' => $uid]);
if (!$s) {
    sl_json(['success' => false, 'message' => 'This survey is no longer open.'], 404);
}
$window = cdp_surveySettings()['window_days'];
if (strtotime((string) $s->sent_at) < time() - 86400 * $window) {
    sl_json(['success' => false, 'message' => 'This survey has closed. Thank you all the same!'], 410);
}

switch ((string) ($_POST['action'] ?? '')) {
    case 'answer':
        $a = isset($_POST['a']) && is_array($_POST['a']) ? $_POST['a'] : [];
        $r = cdp_surveySubmit($id, $a, ['source' => 'popup']);
        if (!$r['ok']) {
            sl_json(['success' => false, 'message' => $r['error'], 'errors' => (object) $r['errors']], 422);
        }
        sl_json(['success' => true, 'message' => 'Thank you for your feedback', 'thanks' => $r['thanks']]);

    case 'shown':
        if ($s->responded_at === null) {
            cdp_surveyMarkPrompted($id);
        }
        sl_json(['success' => true, 'message' => '']);

    case 'later':
    case 'never':
        if ($s->responded_at !== null) {
            sl_json(['success' => true, 'message' => '']);
        }
        cdp_surveyDismiss($id, $_POST['action'] === 'never');
        sl_json(['success' => true, 'message' => $_POST['action'] === 'never' ? 'We will not ask about this delivery again.' : '']);

    default:
        sl_json(['success' => false, 'message' => 'Unknown action.'], 400);
}
