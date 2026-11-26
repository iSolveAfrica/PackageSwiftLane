<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Single sign-on (helpers/sso.php). ?p=google|microsoft starts the sign-in; the provider sends the
// person back here with ?code&state. Only existing, active accounts with a matching verified email
// can sign in; nothing is created. Errors go back to login.php?sso=<reason>.
require_once 'loader.php';
require_once 'helpers/sl_view.php';
require_once 'helpers/activity_log.php';
require_once 'helpers/sso.php';

$login = new User;
$back = function (string $reason) {
    header('Location: login.php?sso=' . rawurlencode($reason));
    exit;
};

if (!empty($_GET['error'])) $back('cancelled');

// ── 1. start ────────────────────────────────────────────────────────────────
if (isset($_GET['p'])) {
    $key = (string) $_GET['p'];
    $p = cdp_ssoActive()[$key] ?? null;
    if (!$p) $back('off');
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $state = bin2hex(random_bytes(16));
    $nonce = bin2hex(random_bytes(16));
    $next = (string) ($_GET['next'] ?? '');
    $_SESSION['sso'] = ['p' => $key, 'state' => $state, 'nonce' => $nonce, 'verifier' => $verifier, 't' => time(),
        'next' => ($next !== '' && preg_match('#^[a-z0-9_./?=&%-]+$#i', $next) && strpos($next, '//') === false) ? $next : ''];
    $q = ['client_id' => $p['client_id'], 'response_type' => 'code', 'redirect_uri' => cdp_ssoRedirectUri(), 'scope' => $p['scope'],
        'state' => $state, 'nonce' => $nonce, 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256', 'prompt' => 'select_account'];
    header('Location: ' . $p['auth'] . '?' . http_build_query($q));
    exit;
}

// ── 2. return from the provider ────────────────────────────────────────────
$s = $_SESSION['sso'] ?? null;
unset($_SESSION['sso']);
if (!is_array($s) || empty($_GET['code']) || !hash_equals((string) $s['state'], (string) ($_GET['state'] ?? '')) || time() - (int) $s['t'] > 600) $back('expired');
$key = (string) $s['p'];
$p = cdp_ssoActive()[$key] ?? null;
if (!$p) $back('off');

try {
    $tok = cdp_ssoPost($p['token'], ['grant_type' => 'authorization_code', 'code' => (string) $_GET['code'], 'redirect_uri' => cdp_ssoRedirectUri(),
        'client_id' => $p['client_id'], 'client_secret' => $p['secret'], 'code_verifier' => (string) $s['verifier']]);
    if (empty($tok['id_token'])) throw new RuntimeException('No ID token');
    [$sub, $email] = cdp_ssoIdentity($key, $p, cdp_ssoClaims((string) $tok['id_token']), (string) $s['nonce']);
} catch (Throwable $e) {
    error_log('sso ' . $key . ': ' . $e->getMessage());
    cdp_activityLogLogin(false, '(' . $p['label'] . ')', 0, 'sso: ' . $e->getMessage());
    $back('failed');
}

cdp_ssoEnsureTable();
$link = sl_row('SELECT user_id FROM cdb_user_sso WHERE provider = :p AND subject = :s', [':p' => $key, ':s' => $sub]);
$uid = $link ? (int) $link->user_id : 0;
if (!$uid) {
    if ($email === '') $back('noemail');
    $matches = sl_rows('SELECT id FROM cdb_users WHERE LOWER(email) = LOWER(:e) LIMIT 2', [':e' => $email]);
    if (count($matches) !== 1) {
        cdp_activityLogLogin(false, $email, 0, 'sso ' . $key . ': ' . (count($matches) ? 'email on several accounts' : 'no account with this email'));
        $back(count($matches) ? 'ambiguous' : 'noaccount');
    }
    $uid = (int) $matches[0]->id;
    $db = new Conexion;
    $db->cdp_query('INSERT INTO cdb_user_sso (user_id, provider, subject, email, created_at) VALUES (:u, :p, :s, :e, NOW())');
    $db->bind(':u', $uid); $db->bind(':p', $key); $db->bind(':s', $sub); $db->bind(':e', $email); $db->cdp_execute();
}

$acct = sl_row('SELECT id, username, active FROM cdb_users WHERE id = :id', [':id' => $uid]);
if (!$acct) $back('noaccount');
if ((int) $acct->active !== 1) {
    cdp_activityLogLogin(false, (string) $acct->username, $uid, 'sso ' . $key . ': account not active');
    $back('inactive');
}
$db = new Conexion;
$db->cdp_query('UPDATE cdb_user_sso SET last_used_at = NOW() WHERE provider = :p AND subject = :s');
$db->bind(':p', $key); $db->bind(':s', $sub); $db->cdp_execute();
if (!$login->cdp_finalizeLoginById($uid)) $back('failed');
cdp_activityLogLogin(true, (string) $acct->username, $uid, 'sso ' . $key);
header('Location: ' . ($s['next'] !== '' ? $s['next'] : 'index.php'));
exit;
