<?php
// *************************************************************************
// * Customer satisfaction survey (public, signed link from a message).    *
// * The link carries the survey id and an HMAC (helpers/ops_db.php        *
// * cdp_opsSign 'survey'); opening it only shows the form, the answers    *
// * are saved on POST (helpers/comms_surveys.php cdp_surveySubmit). The   *
// * questions are the survey form's (Communications > Surveys); answering *
// * again while the link is open replaces the earlier answers.            *
// * ?preview=<form id> shows a form to signed-in staff without saving.    *
// *************************************************************************

require_once("loader.php");
require_once("helpers/querys.php");
require_once("helpers/comms_surveys.php");
require_once("views/comms/public_shell.php");

$core = new Core();
$e = 'cdp_opsEsc';
$ready = cdp_surveyEnsure();

// Staff preview of a form: no survey row, nothing saved.
$previewId = (int) ($_GET['preview'] ?? 0);
if ($previewId > 0) {
    $u = new User();
    $form = ($ready && !empty($u->logged_in) && cdp_opsRank($u) >= 2) ? cdp_surveyForm($previewId) : null;
    cdp_commPublicHead('Survey preview', $core->site_name);
    echo '<style>' . cdp_surveyQuestionsCss() . '.pv{background:var(--amber-bg);color:var(--amber-ink);border-radius:var(--r-sm);padding:8px 12px;font-size:14px;margin:0 0 16px}</style>';
    if (!$form) {
        echo '<h1>Preview not available</h1><p>Sign in as staff to preview a survey.</p>';
    } else {
        echo '<p class="pv">Preview: this is what customers see. Answers are not saved.</p><h1>' . $e($form['name']) . '</h1>'
            . ($form['intro'] !== '' ? '<p>' . $e(cdp_surveyFillText($form['intro'], (int) ($_SESSION['userid'] ?? 0))) . '</p>' : '')
            . '<form onsubmit="return false">' . cdp_surveyQuestionsHtml($form['questions'], [], 'pv') . '<button type="button" disabled>Send feedback</button></form>';
    }
    cdp_commPublicFoot();
    exit;
}

$token = (string) ($_REQUEST['t'] ?? '');
$id = $ready ? cdp_opsVerify('survey', $token) : 0;
$survey = $id ? cdp_opsOne("SELECT * FROM cdb_comm_surveys WHERE id = :id", [':id' => $id]) : null;
$form = $survey ? cdp_surveyFormFor($survey) : null;
$done = false;
$error = '';
$errors = [];
$values = [];
if ($survey) {
    foreach (cdp_surveyAnswers((int) $survey->id) as $a) {
        $k = (string) $a->question_key;
        $v = $a->value_num !== null ? (string) (int) $a->value_num : (string) $a->value_text;
        if ($a->question_type === 'multi') {
            $values[$k][] = $v;
        } else {
            $values[$k] = $v;
        }
    }
    if (!$values && $survey->score !== null) {
        // Answered by replying a number: show it on the first rating question.
        foreach ($form['questions'] as $q) {
            if ($q['type'] === 'rating') {
                $values[$q['key']] = (string) (int) $survey->score;
                break;
            }
        }
    }
}
if ($survey && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $answers = isset($_POST['a']) && is_array($_POST['a']) ? $_POST['a'] : [];
    // Links sent before the survey builder posted a single "score" and "comment".
    if (!$answers && isset($_POST['score'])) {
        foreach ($form['questions'] as $q) {
            if ($q['type'] === 'rating' && !isset($answers[$q['key']])) {
                $answers[$q['key']] = (string) $_POST['score'];
            } elseif ($q['type'] === 'text' && !isset($answers[$q['key']])) {
                $answers[$q['key']] = (string) ($_POST['comment'] ?? '');
            }
        }
    }
    $r = cdp_surveySubmit($id, $answers, ['source' => 'link']);
    if ($r['ok']) {
        $done = $r['thanks'];
    } else {
        $error = $r['error'];
        $errors = $r['errors'];
        $values = $answers;
    }
}

cdp_commPublicHead('Your feedback', $core->site_name);
echo '<style>' . cdp_surveyQuestionsCss() . '</style>';
if (!$survey) {
    echo '<h1>Link expired</h1><p>This survey link is not valid any more. Thank you all the same!</p>';
} elseif ($done !== false) {
    echo '<h1>All done</h1><p>' . $e($done) . '</p>';
} else {
    ?>
    <h1><?php echo $e($form['name'] ?: 'How did we do?'); ?></h1>
    <p><?php echo $form['intro'] !== '' ? $e(cdp_surveyFillText($form['intro'], (int) $survey->user_id)) : 'Please rate your experience.'; ?><?php echo $survey->entity_ref ? ' <span class="muted">(' . $e($survey->entity_ref) . ')</span>' : ''; ?></p>
    <?php if ($error) { echo '<p class="err" role="alert">' . $e($error) . '</p>'; } ?>
    <form method="post" action="survey.php" novalidate>
        <input type="hidden" name="t" value="<?php echo $e($token); ?>">
        <?php echo cdp_surveyQuestionsHtml($form['questions'], $values, 'sq', $errors); ?>
        <button type="submit">Send feedback</button>
    </form>
    <?php
}
cdp_commPublicFoot();
