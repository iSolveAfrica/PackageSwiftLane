<?php
/**
 * Passkeys (fingerprint / face / screen lock sign-in). Helpers: helpers/webauthn.php.
 *
 * Signed-out ops (login page):  login_options -> login_verify
 * Signed-in ops (own passkeys): reg_options -> reg_verify, list, delete
 *
 * Every op is POST with the CSRF token. A passkey sign-in opens the same session as a password
 * sign-in (User::cdp_finalizeLoginById) and is refused for inactive accounts. It does not ask for the
 * email/WhatsApp code: the phone's own fingerprint/face/PIN check is the second factor.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../views/sl/data/ops_common.php';
require_once __DIR__ . '/../../helpers/webauthn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
_ajax_guard_require_csrf();
if (!cdp_passkeysEnabled()) sl_json(['success' => false, 'message' => 'Fingerprint sign-in is turned off.'], 403);
cdp_waEnsureTable();

$user = $user ?? new User();
$op = (string) ($_POST['op'] ?? '');
$in = json_decode((string) ($_POST['data'] ?? ''), true);
$in = is_array($in) ? $in : [];
$rpId = cdp_waRpId();
$site = (string) ((new Core())->site_name ?? 'Swift Lane');

// ── signed out: sign in with a passkey ───────────────────────────────────────
if ($op === 'login_options') {
    // No username first: phones keep passkeys "discoverable", so the browser offers the right account.
    // (Looking up a username here would tell anyone which accounts have fingerprint sign-in.)
    sl_json(['success' => true, 'publicKey' => [
        'challenge' => cdp_waNewChallenge('login'), 'rpId' => $rpId, 'timeout' => 60000,
        'userVerification' => 'required', 'allowCredentials' => [],
    ]]);
}

if ($op === 'login_verify') {
    $challenge = cdp_waTakeChallenge('login');
    $credId = (string) ($in['id'] ?? '');
    $row = $credId !== '' ? sl_row('SELECT * FROM cdb_passkeys WHERE credential_id = :c', [':c' => $credId]) : null;
    if (!$row) {
        cdp_activityLogLogin(false, '(passkey)', 0, 'unknown passkey');
        sl_json(['success' => false, 'message' => 'This fingerprint is not set up for any account here yet. Sign in with your password, then turn on fingerprint sign-in.'], 404);
    }
    try {
        $count = cdp_waVerifyAssertion($in, $challenge, $row);
    } catch (Throwable $e) {
        cdp_activityLogLogin(false, '(passkey)', (int) $row->user_id, 'passkey: ' . $e->getMessage());
        sl_json(['success' => false, 'message' => $e->getMessage()], 401);
    }
    $acct = sl_row('SELECT id, username, active FROM cdb_users WHERE id = :id', [':id' => (int) $row->user_id]);
    if (!$acct || (int) $acct->active !== 1) {
        cdp_activityLogLogin(false, (string) ($acct->username ?? '(passkey)'), (int) $row->user_id, 'account not active');
        sl_json(['success' => false, 'message' => 'Your account is not activated.'], 403);
    }
    $db = new Conexion;
    $db->cdp_query('UPDATE cdb_passkeys SET sign_count = :n, last_used_at = NOW() WHERE id = :id');
    $db->bind(':n', $count); $db->bind(':id', (int) $row->id); $db->cdp_execute();
    if (!$user->cdp_finalizeLoginById((int) $acct->id)) sl_json(['success' => false, 'message' => 'Could not start your session'], 500);
    cdp_activityLogLogin(true, (string) $acct->username, (int) $acct->id, 'passkey');
    $next = (string) ($_POST['next'] ?? '');
    $dest = ($next !== '' && preg_match('#^[a-z0-9_./?=&%-]+$#i', $next) && strpos($next, '//') === false) ? $next : 'index.php';
    sl_json(['success' => true, 'message' => 'Signed in', 'redirect' => $dest]);
}

// ── signed in: manage own passkeys ───────────────────────────────────────────
if (!$user->cdp_loginCheck()) sl_json(['success' => false, 'message' => 'Sign in first'], 401);
$uid = (int) $_SESSION['userid'];

switch ($op) {
    case 'reg_options':
        $me = sl_row('SELECT id, username, fname, lname, email FROM cdb_users WHERE id = :id', [':id' => $uid]);
        $exclude = array_map(fn($r) => ['type' => 'public-key', 'id' => $r->credential_id], sl_rows('SELECT credential_id FROM cdb_passkeys WHERE user_id = :id', [':id' => $uid]));
        sl_json(['success' => true, 'publicKey' => [
            'challenge' => cdp_waNewChallenge('reg'),
            'rp' => ['id' => $rpId, 'name' => $site],
            'user' => ['id' => cdp_waB64uEncode('u' . $uid), 'name' => (string) ($me->email ?: $me->username), 'displayName' => trim($me->fname . ' ' . $me->lname) ?: (string) $me->username],
            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
            'authenticatorSelection' => ['authenticatorAttachment' => 'platform', 'userVerification' => 'required', 'residentKey' => 'preferred', 'requireResidentKey' => false],
            'excludeCredentials' => $exclude, 'attestation' => 'none', 'timeout' => 60000,
        ]]);
    case 'reg_verify':
        try {
            $r = cdp_waVerifyRegistration($in, cdp_waTakeChallenge('reg'));
        } catch (Throwable $e) {
            sl_json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        if (sl_row('SELECT id FROM cdb_passkeys WHERE credential_id = :c', [':c' => $r['credId']])) sl_json(['success' => false, 'message' => 'This device is already set up.'], 409);
        $label = mb_substr(trim(strip_tags((string) ($_POST['label'] ?? ''))), 0, 120) ?: cdp_waDeviceLabel();
        $db = new Conexion;
        $db->cdp_query('INSERT INTO cdb_passkeys (user_id, credential_id, public_key, alg, sign_count, label, created_at) VALUES (:u, :c, :k, :a, :n, :l, NOW())');
        $db->bind(':u', $uid); $db->bind(':c', $r['credId']); $db->bind(':k', $r['pem']); $db->bind(':a', $r['alg']); $db->bind(':n', $r['signCount']); $db->bind(':l', $label);
        $db->cdp_execute();
        cdp_activityLog(['module' => 'auth', 'action' => 'passkey.add', 'action_label' => 'Fingerprint sign-in added', 'verb' => 'create', 'outcome' => 'ok', 'summary' => 'Set up fingerprint sign-in on ' . $label, 'entity_type' => 'user', 'entity_id' => $uid]);
        sl_json(['success' => true, 'message' => 'Fingerprint sign-in is on for ' . $label . '.']);
    case 'list':
        sl_json(['success' => true, 'keys' => array_map(fn($r) => ['id' => (int) $r->id, 'label' => $r->label, 'added' => sl_date($r->created_at), 'used' => $r->last_used_at ? sl_ago($r->last_used_at) : 'never'], sl_rows('SELECT id, label, created_at, last_used_at FROM cdb_passkeys WHERE user_id = :id ORDER BY id DESC', [':id' => $uid]))]);
    case 'delete':
        $id = (int) ($_POST['id'] ?? 0);
        $row = sl_row('SELECT id, label FROM cdb_passkeys WHERE id = :id AND user_id = :u', [':id' => $id, ':u' => $uid]);
        if (!$row) sl_json(['success' => false, 'message' => 'Not found'], 404);
        $db = new Conexion;
        $db->cdp_query('DELETE FROM cdb_passkeys WHERE id = :id AND user_id = :u'); $db->bind(':id', $id); $db->bind(':u', $uid); $db->cdp_execute();
        cdp_activityLog(['module' => 'auth', 'action' => 'passkey.remove', 'action_label' => 'Fingerprint sign-in removed', 'verb' => 'delete', 'outcome' => 'ok', 'summary' => 'Removed fingerprint sign-in for ' . $row->label, 'entity_type' => 'user', 'entity_id' => $uid]);
        sl_json(['success' => true, 'message' => 'Removed ' . $row->label . '.']);
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
