<?php
// *************************************************************************
// * Notification preferences and opt-out.                                 *
// *                                                                       *
// * Two ways in:                                                          *
// *   - a signed link (?t=…, cdp_opsSign 'prefs') that every e-mail and   *
// *     campaign carries, so a customer can change what they get without *
// *     logging in;                                                       *
// *   - signed in as a customer (sidebar › Notification Preferences);     *
// *     the POST then needs the session's CSRF token.                     *
// * Every change is written to the consent log with its source.           *
// *************************************************************************

require_once("loader.php");
require_once("helpers/querys.php");
require_once("helpers/csrf.php");
require_once("helpers/comms.php");
require_once("views/comms/public_shell.php");

$core = new Core();
$user = new User();
$token = (string) ($_REQUEST['t'] ?? '');
$uid = 0;
$source = '';
if (cdp_commEnsureTables()) {
    if ($token !== '') {
        $uid = cdp_opsVerify('prefs', $token);
        $source = 'link';
    } elseif ($user->cdp_loginCheck() == true) {
        $uid = (int) $user->uid;
        $source = 'customer';
    }
}
$saved = false;
$error = '';
if ($uid && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($source === 'customer' && !cdp_csrf_validate_request()) {
        $error = 'Your session expired. Please reload the page and try again.';
    } else {
        $in = [];
        foreach (['whatsapp', 'sms', 'email', 'mkt_whatsapp', 'mkt_sms', 'mkt_email', 'opted_out_all'] as $f) {
            $in[$f] = isset($_POST[$f]) ? 1 : 0;
        }
        $in['muted_events'] = array_values(array_diff(array_keys(cdp_commEvents()), (array) ($_POST['events'] ?? [])));
        $saved = cdp_commSavePrefs($uid, $in, $source, $source === 'customer' ? $uid : 0);
    }
}
$e = 'cdp_opsEsc';
cdp_commPublicHead('Notification preferences', $core->site_name);
if (!$uid) {
    echo '<h1>Link expired</h1><p>This link is not valid any more. Sign in to your account to change your notification preferences.</p><p><a href="login.php">Sign in</a></p>';
    cdp_commPublicFoot();
    exit;
}
$p = cdp_commPrefs($uid);
$muted = array_filter(explode(',', (string) $p['muted_events']));
$chk = function ($on) { return $on ? ' checked' : ''; };
?>
<h1>Notification preferences</h1>
<?php if ($saved) { echo '<p role="status"><strong>Saved.</strong> Your choices apply to the next message we send.</p>'; } ?>
<?php if ($error) { echo '<p class="err" role="alert">' . $e($error) . '</p>'; } ?>
<form method="post" action="comms_preferences.php">
    <?php if ($source === 'link') { ?><input type="hidden" name="t" value="<?php echo $e($token); ?>"><?php } else { ?>
        <input type="hidden" name="<?php echo $e(cdp_csrf_param()); ?>" value="<?php echo $e(cdp_csrf_token()); ?>"><?php } ?>
    <fieldset>
        <legend>Shipment updates</legend>
        <p class="muted">Messages about your own shipments, packages, deliveries and payments.</p>
        <label class="chk"><input type="checkbox" name="whatsapp" value="1"<?php echo $chk($p['whatsapp']); ?>> WhatsApp</label>
        <label class="chk"><input type="checkbox" name="sms" value="1"<?php echo $chk($p['sms']); ?>> SMS</label>
        <label class="chk"><input type="checkbox" name="email" value="1"<?php echo $chk($p['email']); ?>> E-mail</label>
    </fieldset>
    <fieldset>
        <legend>Which updates</legend>
        <?php foreach (cdp_commEvents() as $k => $ev) { ?>
            <label class="chk"><input type="checkbox" name="events[]" value="<?php echo $e($k); ?>"<?php echo $chk(!in_array($k, $muted, true)); ?>> <?php echo $e($ev['label']); ?></label>
        <?php } ?>
    </fieldset>
    <fieldset>
        <legend>Offers and news</legend>
        <p class="muted">Promotions and announcements. We only send these if you say yes.</p>
        <label class="chk"><input type="checkbox" name="mkt_whatsapp" value="1"<?php echo $chk($p['mkt_whatsapp']); ?>> WhatsApp</label>
        <label class="chk"><input type="checkbox" name="mkt_sms" value="1"<?php echo $chk($p['mkt_sms']); ?>> SMS</label>
        <label class="chk"><input type="checkbox" name="mkt_email" value="1"<?php echo $chk($p['mkt_email']); ?>> E-mail</label>
    </fieldset>
    <fieldset>
        <legend>Stop everything</legend>
        <label class="chk"><input type="checkbox" name="opted_out_all" value="1"<?php echo $chk($p['opted_out_all']); ?>> Do not send me any messages</label>
        <p class="muted">On WhatsApp you can also reply STOP at any time, and START to receive updates again.</p>
    </fieldset>
    <button type="submit">Save preferences</button>
</form>
<?php
cdp_commPublicFoot();
