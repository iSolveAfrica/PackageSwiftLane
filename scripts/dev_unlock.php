<?php
/**
 * Server-side recovery for the developer console (helpers/dev_access.php).
 * Command line only, run on the server from the application folder:
 *
 *     php scripts/dev_unlock.php                 shows the lock, asks you to type UNLOCK, removes it
 *     php scripts/dev_unlock.php --status        shows the lock and the owner, changes nothing
 *     php scripts/dev_unlock.php --yes           no prompt (for a scripted recovery)
 *     php scripts/dev_unlock.php --owner 15      makes user 15 (an active super admin) the console owner:
 *                                                assigns them, stores them as owner (every section,
 *                                                including Developer access and Shutdown). Use it when the
 *                                                owner was demoted, deactivated or deleted. Asks you to
 *                                                type OWNER unless --yes is given. Does not touch the lock.
 *
 * Unlocking also clears the failed-attempt counters, so a burst of wrong codes cannot keep the
 * console shut for the next hour. Every recovery is written to cdb_activity_log (module devaccess).
 * Shell access to the server is the proof of authority: there is no browser path without the code.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
chdir(dirname(__DIR__));
require_once dirname(__DIR__) . '/loader.php';
require_once CDP_ROOT . '/helpers/devops.php';
require_once CDP_ROOT . '/helpers/activity_log.php';

$args = array_slice($argv, 1);
$yes = in_array('--yes', $args, true);
$who = function_exists('posix_getpwuid') && function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'cli') : (getenv('USER') ?: 'cli');
$ask = function (string $word) use ($yes): bool {
    if ($yes) {
        return true;
    }
    echo "Type {$word} to continue: ";
    return trim((string) fgets(STDIN)) === $word;
};

/* ------------------------------------------------------------ --owner <user id> */
$oi = array_search('--owner', $args, true);
if ($oi !== false) {
    $uid = (int) ($args[$oi + 1] ?? 0);
    if (!cdp_devEnsureTables() || !cdp_devAccessEnsure()) {
        echo "The developer console tables could not be created (database error).\n";
        exit(1);
    }
    $u = $uid > 0 ? cdp_devOne('SELECT id, username, fname, lname FROM cdb_users WHERE id = :i', [':i' => $uid]) : null;
    if (!$u || !cdp_devActiveSuper($uid)) {
        echo "User #{$uid} is not an active super admin. Give the account the super admin role (and activate it) first.\n";
        exit(1);
    }
    $old = cdp_devOwnerId();
    echo 'Current owner: ' . ($old ? '#' . $old : 'none') . '. New owner: #' . $uid . ' ' . $u->username . "\n";
    if (!$ask('OWNER')) {
        echo "Nothing changed.\n";
        exit(1);
    }
    $now = cdp_devAppNow();
    $ok = cdp_devX('INSERT IGNORE INTO cdb_dev_access (user_id, granted_by, granted_at) VALUES (:u, 0, :n)', [':u' => $uid, ':n' => $now])
        && cdp_devStateSet('owner_uid', ['uid' => $uid, 'at' => $now, 'via' => 'cli:' . $who, 'previous' => $old]);
    cdp_activityLog(['module' => 'devaccess', 'action' => 'devaccess.recover_owner', 'verb' => 'security', 'outcome' => $ok ? 'ok' : 'fail',
        'user_id' => 0, 'actor_name' => 'Server (' . $who . ')',
        'summary' => 'made ' . $u->username . ' the owner of the developer console from the server command line (scripts/dev_unlock.php --owner)' . ($old && $old !== $uid ? ', replacing user #' . $old : ''),
        'entity_type' => 'user', 'entity_id' => (string) $uid, 'entity_label' => (string) $u->username]);
    echo $ok ? "Done. {$u->username} is the owner and holds every developer section.\n" : "Could not save (database error).\n";
    exit($ok ? 0 : 1);
}

/* ------------------------------------------------------------ unlock */
$info = cdp_devTableExists('cdb_dev_state') ? cdp_devLockInfo(true) : null;
if (in_array('--status', $args, true)) {
    $owner = cdp_devTableExists('cdb_dev_access') ? cdp_devOwnerId() : 0;
    echo 'Owner: ' . ($owner ? '#' . $owner : 'none (the first super admin to open the console becomes owner)') . "\n";
}
if (!$info) {
    echo "The developer console is not locked.\n";
    exit(0);
}
$by = !empty($info['by']) ? cdp_devOne('SELECT username FROM cdb_users WHERE id = :i', [':i' => (int) $info['by']]) : null;
echo 'Developer console is LOCKED since ' . ($info['at'] ?? '?') . ' by ' . ($by->username ?? ($info['by_name'] ?? 'unknown')) . "\n";
if (!empty($info['note'])) {
    echo 'Reason: ' . $info['note'] . "\n";
}
if (in_array('--status', $args, true)) {
    exit(0);
}
if (!$ask('UNLOCK')) {
    echo "Nothing changed.\n";
    exit(1);
}
$ok = cdp_devForceUnlock('cli:' . $who);
if ($ok && cdp_devTableExists('cdb_dev_unlock_attempts')) {
    cdp_devX("DELETE FROM cdb_dev_unlock_attempts WHERE ok = 0");
}
cdp_activityLog(['module' => 'devaccess', 'action' => 'devaccess.recover', 'verb' => 'security', 'outcome' => $ok ? 'ok' : 'fail',
    'user_id' => 0, 'actor_name' => 'Server (' . $who . ')', 'summary' => 'removed the developer console lock from the server command line (scripts/dev_unlock.php) and cleared the failed-attempt counters',
    'entity_type' => 'dev_access', 'entity_label' => 'developer console']);
echo $ok ? "Unlocked. Set a new code the next time you shut the console down.\n" : "Could not remove the lock (database error).\n";
exit($ok ? 0 : 1);
