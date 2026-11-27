<?php
/**
 * Developer access (dev_access.php): per-section grants, developer assignment, locked shutdown and unlock.
 * POST + CSRF (require_login). Every action is written to cdb_activity_log (module "devaccess"),
 * refused attempts included. Codes and passwords are never echoed or logged.
 *
 *   op=unlock     unlock_secret               assigned super admin (works while locked), rate limited
 *   op=grant      user_id, key(section), value   "access" section (access/shutdown: owner only)
 *   op=preset     user_id, preset=all|none|observe
 *   op=assign     user_id, value                 add / remove a super admin from the console
 *   op=lock       confirm=1, unlock_secret, unlock_secret2, password, note
 *                 "shutdown" section + password re-authentication (rate limited: 5 wrong per 15 minutes)
 * The code fields are named *_secret so the activity log's redaction (cdp_activityIsSecret) masks them,
 * and the automatic request snapshot is switched off for this endpoint: every outcome, refusals
 * included, is written explicitly below without any submitted value.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/devops.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
// Never let the shutdown auto-logger snapshot this request body (it would hold unlock codes).
$GLOBALS['cdp_activity_logged'] = true;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
header('Cache-Control: no-store');
$op = (string) ($_POST['op'] ?? '');
$sections = cdp_devSections();

$audit = function (string $action, bool $ok, string $summary, array $extra = []) {
    cdp_activityLog(['module' => 'devaccess', 'action' => 'devaccess.' . $action, 'verb' => $extra['verb'] ?? 'update', 'outcome' => $ok ? 'ok' : 'fail',
        'summary' => $summary, 'entity_type' => $extra['entity_type'] ?? 'dev_access', 'entity_id' => $extra['entity_id'] ?? null,
        'entity_label' => $extra['entity_label'] ?? null, 'changes' => $extra['changes'] ?? null]);
};
$userName = function (int $id): string {
    $r = cdp_devOne('SELECT fname, lname, username FROM cdb_users WHERE id = :i', [':i' => $id]);
    return $r ? (trim($r->fname . ' ' . $r->lname) ?: (string) $r->username) : ('user #' . $id);
};

/* ------------------------------------------------------------ unlock (allowed while locked) */
if ($op === 'unlock') {
    if (!cdp_devAssigned($user)) {
        $audit('unlock', false, 'tried to unlock the developer console without developer access', ['verb' => 'security']);
        sl_json(['success' => false, 'message' => 'You do not have access to the developer console.'], 403);
    }
    if (!cdp_devLocked()) {
        sl_json(['success' => true, 'message' => 'The developer console is already on', 'redirect' => 'dev_access.php']);
    }
    $r = cdp_devUnlock($user, (string) ($_POST['unlock_secret'] ?? ''));
    $audit('unlock', $r['ok'], $r['ok'] ? 'unlocked the developer console with the unlock code' : 'entered a wrong unlock code' . ($r['wait'] ? ' (rate limited)' : ''), ['verb' => 'security', 'entity_label' => 'developer console']);
    if (!$r['ok']) {
        sl_json(['success' => false, 'message' => $r['error'], 'errors' => ['unlock_secret' => $r['error']]], $r['wait'] ? 429 : 422);
    }
    sl_json(['success' => true, 'message' => 'Developer console unlocked', 'redirect' => 'dev_overview.php']);
}

/* ------------------------------------------------------------ everything else: console on, assigned */
if (cdp_devLocked()) {
    $audit($op !== '' ? preg_replace('/[^a-z_]/', '', substr($op, 0, 20)) : 'request', false, 'developer access change refused: the console is locked', ['verb' => 'security']);
    sl_json(['success' => false, 'error' => 'dev_locked', 'message' => 'The developer console is locked.'], 423);
}
if (!cdp_devCanUse($user)) {
    $audit($op !== '' ? preg_replace('/[^a-z_]/', '', substr($op, 0, 20)) : 'request', false, 'developer access change refused: not an assigned developer', ['verb' => 'security']);
    sl_json(['success' => false, 'message' => 'You do not have access to the developer console.'], 403);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
$me = (int) $user->uid;

switch ($op) {
    case 'grant':
        $target = (int) ($_POST['user_id'] ?? 0);
        $sec = cdp_devSectionKey((string) ($_POST['key'] ?? ''));
        $on = !empty($_POST['value']) && $_POST['value'] !== '0';
        if (!cdp_devSectionValid($sec)) {
            $audit('grant', false, 'section change refused: unknown section', ['entity_type' => 'user', 'entity_id' => (string) $target, 'entity_label' => $userName($target)]);
            sl_json(['success' => false, 'message' => 'Unknown section.'], 422);
        }
        $r = cdp_devSectionSet($user, $target, $sec, $on);
        $label = $sections[$sec][0] ?? $sec;
        $audit('grant', $r['ok'], ($on ? 'granted ' : 'revoked ') . $label . ($on ? ' to ' : ' from ') . $userName($target) . ($r['ok'] ? '' : ' (refused: ' . $r['error'] . ')'),
            ['entity_type' => 'user', 'entity_id' => (string) $target, 'entity_label' => $userName($target), 'changes' => [$sec => ['from' => $on ? 'off' : 'on', 'to' => $on ? 'on' : 'off']]]);
        sl_json(['success' => $r['ok'], 'message' => $r['ok'] ? $label . ($on ? ' granted' : ' revoked') : $r['error']], $r['ok'] ? 200 : 403);

    case 'preset':
        $target = (int) ($_POST['user_id'] ?? 0);
        $preset = (string) ($_POST['preset'] ?? '');
        $sets = ['observe' => ['overview', 'services', 'logs', 'metrics', 'infrastructure', 'alerts'], 'all' => array_keys($sections), 'none' => []];
        if (!isset($sets[$preset])) {
            $audit('preset', false, 'section preset refused: unknown preset', ['entity_type' => 'user', 'entity_id' => (string) $target, 'entity_label' => $userName($target)]);
            sl_json(['success' => false, 'message' => 'Unknown preset'], 422);
        }
        $want = $sets[$preset];
        $changes = [];
        $errors = [];
        $owner = cdp_devIsOwner($user);
        foreach (array_keys($sections) as $s) {
            if (in_array($s, cdp_devOwnerOnlySections(), true) && !$owner) {
                continue; // a non-owner preset leaves access / shutdown as they are
            }
            $r = cdp_devSectionSet($user, $target, $s, in_array($s, $want, true));
            if ($r['ok']) {
                $changes[$s] = in_array($s, $want, true) ? 'on' : 'off';
            } else {
                $errors[$r['error']] = true;
            }
        }
        $ok = !$errors;
        $audit('preset', $ok, 'set ' . $userName($target) . '\'s developer sections to "' . $preset . '"' . ($ok ? '' : ' (refused: ' . implode(' ', array_keys($errors)) . ')'),
            ['entity_type' => 'user', 'entity_id' => (string) $target, 'entity_label' => $userName($target), 'changes' => $changes]);
        sl_json(['success' => $ok, 'message' => $ok ? 'Sections updated' : implode(' ', array_keys($errors))], $ok ? 200 : 403);

    case 'assign':
        $target = (int) ($_POST['user_id'] ?? 0);
        $on = !empty($_POST['value']) && $_POST['value'] !== '0';
        $r = cdp_devAccessSet($target, $on, $me);
        $audit('assign', $r['ok'], ($on ? 'gave ' : 'removed ') . $userName($target) . ($on ? ' access to' : ' from') . ' the developer console' . ($r['ok'] ? '' : ' (refused: ' . $r['error'] . ')'),
            ['entity_type' => 'user', 'entity_id' => (string) $target, 'entity_label' => $userName($target)]);
        sl_json(['success' => $r['ok'], 'message' => $r['ok'] ? ($on ? 'Developer added with the Overview section' : 'Developer removed') : $r['error']], $r['ok'] ? 200 : 403);

    case 'lock':
        if (!cdp_devSectionAllowed($user, 'shutdown')) {
            $audit('lock', false, 'tried to shut the developer console down without the Shutdown section', ['verb' => 'security']);
            sl_json(['success' => false, 'message' => 'You cannot shut the developer console down.'], 403);
        }
        $errors = [];
        if (empty($_POST['confirm'])) {
            $errors['confirm'] = 'Tick the box to confirm the shutdown.';
        }
        $code = (string) ($_POST['unlock_secret'] ?? '');
        $p = cdp_devCodeProblem($code, (string) ($_POST['unlock_secret2'] ?? ''));
        if ($p !== '') {
            $errors[mb_strlen($code) < 8 || mb_strlen($code) > 128 ? 'unlock_secret' : 'unlock_secret2'] = $p;
        }
        $pw = cdp_devCheckPasswordLimited($user, (string) ($_POST['password'] ?? ''));
        if ($pw['wait'] > 0) {
            $audit('lock', false, 'shutdown of the developer console refused: too many wrong passwords (rate limited)', ['verb' => 'security']);
            sl_json(['success' => false, 'message' => cdp_devWaitText($pw['wait']), 'errors' => ['password' => cdp_devWaitText($pw['wait'])]], 429);
        }
        if (!$pw['ok']) {
            $errors['password'] = 'Your password is not right.' . ($pw['left'] > 0 ? ' ' . $pw['left'] . ' attempt' . ($pw['left'] === 1 ? '' : 's') . ' left before a 15-minute wait.' : ' Wait 15 minutes before trying again.');
        }
        if ($errors) {
            // Reasons only, never the values typed.
            $why = [];
            if (isset($errors['password'])) $why[] = 'wrong password';
            if (isset($errors['unlock_secret'])) $why[] = 'unlock code too short or too long';
            if (isset($errors['unlock_secret2'])) $why[] = $p === 'The two codes do not match.' ? 'the two codes do not match' : 'weak unlock code';
            if (isset($errors['confirm'])) $why[] = 'not confirmed';
            $audit('lock', false, 'shutdown of the developer console refused: ' . implode(', ', $why), ['verb' => 'security']);
            sl_json(['success' => false, 'message' => reset($errors), 'errors' => $errors], 422);
        }
        $note = trim(strip_tags((string) ($_POST['note'] ?? '')));
        $ok = cdp_devLock($user, $code, $note);
        $audit('lock', $ok, 'shut the developer console down (locked with an unlock code)' . ($note !== '' ? ': ' . mb_substr($note, 0, 120) : ''), ['verb' => 'security', 'entity_label' => 'developer console']);
        sl_json(['success' => $ok, 'message' => $ok ? 'Developer console locked' : 'Could not lock', 'redirect' => 'dev_access.php']);

    default:
        sl_json(['success' => false, 'message' => 'Unknown action'], 422);
}
