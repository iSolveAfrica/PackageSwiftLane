<?php
// *************************************************************************
// * Customer satisfaction survey (public, signed link from a message).    *
// * The link carries the survey id and an HMAC (helpers/ops_db.php        *
// * cdp_opsSign 'survey'); opening it only shows the form, the answer is  *
// * saved on POST. One answer per survey; it can be changed for 30 days.  *
// *************************************************************************

require_once("loader.php");
require_once("helpers/querys.php");
require_once("helpers/comms.php");
require_once("views/comms/public_shell.php");

$core = new Core();
$token = (string) ($_REQUEST['t'] ?? '');
$id = cdp_commEnsureTables() ? cdp_opsVerify('survey', $token) : 0;
$survey = $id ? cdp_opsOne("SELECT * FROM cdb_comm_surveys WHERE id = :id", [':id' => $id]) : null;
$done = false;
$error = '';
if ($survey && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (cdp_commSurveyAnswer($id, (int) ($_POST['score'] ?? 0), (string) ($_POST['comment'] ?? ''))) {
        $done = true;
    } else {
        $error = 'Please choose a rating from 1 to 5.';
    }
}
$e = 'cdp_opsEsc';
cdp_commPublicHead('Your feedback', $core->site_name);
if (!$survey) {
    echo '<h1>Link expired</h1><p>This survey link is not valid any more. Thank you all the same!</p>';
} elseif ($done) {
    echo '<h1>Thank you!</h1><p>Your feedback helps us serve you better.</p>';
} else {
    ?>
    <h1>How did we do?</h1>
    <p>Please rate your experience<?php echo $survey->entity_ref ? ' with <strong>' . $e($survey->entity_ref) . '</strong>' : ''; ?>.</p>
    <?php if ($error) { echo '<p class="err" role="alert">' . $e($error) . '</p>'; } ?>
    <form method="post" action="survey.php">
        <input type="hidden" name="t" value="<?php echo $e($token); ?>">
        <fieldset>
            <legend>Rating (1 = poor, 5 = excellent)</legend>
            <div class="stars">
                <?php for ($i = 1; $i <= 5; $i++) { ?>
                    <label><input type="radio" name="score" value="<?php echo $i; ?>" <?php echo (int) $survey->score === $i ? 'checked' : ''; ?> required><span><?php echo $i; ?></span></label>
                <?php } ?>
            </div>
        </fieldset>
        <label for="comment">Anything you would like to tell us? (optional)</label>
        <textarea id="comment" name="comment" rows="4" maxlength="2000"><?php echo $e($survey->comment); ?></textarea>
        <button type="submit">Send feedback</button>
    </form>
    <?php
}
cdp_commPublicFoot();
