<?php
/**
 * Data for SecurityCenter.dc.html. Every check is computed from this installation:
 * cdb_activity_log (sign-ins), cdb_settings.active_otp + cdb_auth_otp_challenges,
 * cdb_auth_trusted_devices, cdb_passkeys, cdb_login_attempts, cdb_api_tokens / cdb_api_user_tokens,
 * RBAC tables, backups/ (helpers/backups_function.php), PHP / MySQL versions, HTTPS and the
 * response headers loader.php sends, cron and queue state (helpers/dev_console.php).
 *
 * Each check carries what the pop-up on the page explains: what it looks at ('what'), why it
 * matters ('why'), what to do ('fix'), facts, an optional table and the pages where it is changed.
 * Times are compared with PHP's clock (the app writes its rows with PHP's date()), not MySQL NOW().
 * The per-person detail (devices, passkeys, sign-ins) is sl_securityUserDetail(), served by
 * ajax/sl/security_ajax.php?op=user_detail.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/dev_console.php';

/** 'Y-m-d H:i:s' of $seconds ago on PHP's clock. */
function sl_secCut(int $seconds): string
{
    return date('Y-m-d H:i:s', time() - $seconds);
}

/** "Chrome on Windows" from a user agent; '' when there is none. */
function sl_secDevice(string $ua): string
{
    $ua = trim($ua);
    if ($ua === '') return '';
    if (preg_match('/^(curl|wget|python|okhttp|go-http|java|postman|insomnia)/i', $ua, $m) || preg_match('/bot|spider|crawler/i', $ua)) {
        return 'Script or bot' . (isset($m[1]) ? ' (' . strtolower($m[1]) . ')' : '');
    }
    $os = preg_match('/iPhone/i', $ua) ? 'iPhone' : (preg_match('/iPad/i', $ua) ? 'iPad' : (preg_match('/Android/i', $ua) ? 'Android' : (preg_match('/Windows/i', $ua) ? 'Windows' : (preg_match('/Mac OS X|Macintosh/i', $ua) ? 'Mac' : (preg_match('/CrOS/i', $ua) ? 'ChromeOS' : (preg_match('/Linux/i', $ua) ? 'Linux' : ''))))));
    $br = preg_match('/Edg(e|A|iOS)?\//', $ua) ? 'Edge' : (preg_match('/OPR\/|Opera/', $ua) ? 'Opera' : (preg_match('/SamsungBrowser/', $ua) ? 'Samsung Internet' : (preg_match('/HeadlessChrome/', $ua) ? 'Headless Chrome' : (preg_match('/CriOS|Chrome\//', $ua) ? 'Chrome' : (preg_match('/FxiOS|Firefox\//', $ua) ? 'Firefox' : (preg_match('/Safari\//', $ua) ? 'Safari' : ''))))));
    $s = trim($br . ($br && $os ? ' on ' : '') . $os);
    return $s !== '' ? $s : 'Other browser';
}

function sl_secDeviceIcon(string $ua): string
{
    return preg_match('/iPhone|Android.*Mobile|Mobile Safari|iPad|Android/i', $ua) ? 'i-phone' : 'i-monitor';
}

/** The name typed on a sign-in row (failures have no user id; the typed name is the label). */
function sl_secTriedSql(string $alias = ''): string
{
    $a = $alias !== '' ? $alias . '.' : '';
    return "COALESCE(NULLIF({$a}entity_label, ''), NULLIF({$a}actor_username, ''), NULLIF({$a}actor_name, ''), 'unknown')";
}

/** "Wrong password" from a sign-in summary ("Failed sign-in attempt — wrong password"). */
function sl_secReason(string $summary, string $fallback = 'Failed'): string
{
    $r = trim((string) preg_replace('/^Failed sign-in attempt\s*(—|-)?\s*/u', '', $summary));
    return $r !== '' ? ucfirst($r) : $fallback;
}

/** "Apr 20, 2026 · 220 days ago" */
function sl_secAge($value): string
{
    $ts = strtotime((string) $value);
    if (!$ts) return '';
    $days = (int) floor((time() - $ts) / 86400);
    return sl_date($ts) . ' · ' . ($days <= 0 ? 'today' : ($days === 1 ? 'yesterday' : $days . ' days ago'));
}

/**
 * Whether the signed-in viewer may open a page this page links to, with the same tests the target
 * page's own gate uses. '' = allowed (or not a page: anchors, pop-ups); otherwise a short reason
 * ("needs developer access") shown instead of a link that would only answer "access denied".
 * $targetLevel: for users_edit.php, the userlevel of the account it opens.
 */
function sl_secNeeds(string $href, ?int $targetLevel = null): string
{
    global $user;
    if (!is_object($user) || $href === '' || $href[0] === '#') return '';
    $page = basename((string) parse_url($href, PHP_URL_PATH));
    $has = fn(...$p) => (bool) $user->cdp_hasPermission($p);
    switch ($page) {
        case 'dev_logs.php':
        case 'dev_metrics.php':
        case 'dev_deployments.php':
            // These also need a super admin with developer-console access (cdp_devCanUse). Tested
            // here without its side effect (it grants access to the first super admin who opens it).
            if (!$has('view_developer')) return 'needs developer access';
            require_once CDP_ROOT . '/helpers/rbac.php';
            if (!cdp_roleHasFlag((int) $user->userlevel, 'is_superadmin')) return 'super admins with developer access only';
            if (sl_tableExists('cdb_dev_access') && (int) sl_scalar('SELECT COUNT(*) FROM cdb_dev_access') > 0
                && !(int) sl_scalar('SELECT COUNT(*) FROM cdb_dev_access WHERE user_id = :u', [':u' => (int) $user->uid])) return 'needs developer-console access';
            return '';
        case 'dev_infrastructure.php':
        case 'dev_alerts.php':
            return $has('view_developer') ? '' : 'needs developer access';
        case 'activity_logs.php':   return $has('view_activity_logs') ? '' : 'needs the View activity logs permission';
        case 'message_logs.php':    return $has('view_message_logs') ? '' : 'needs the View message logs permission';
        case 'system_logs.php':     return $has('view_system_logs') ? '' : 'needs the View system logs permission';
        case 'login_settings.php':
        case 'settings_form.php':   return $has('view_tools') ? '' : 'needs the Settings permission';
        case 'api_connections.php': return $has('manage_api_settings') ? '' : 'needs the Manage API settings permission';
        case 'api_access.php':      return $has('use_api_tokens', 'manage_api_clients', 'manage_api_settings') ? '' : 'needs an API permission';
        case 'api_traffic.php':     return $has('view_api_logs', 'manage_api_settings') ? '' : 'needs the View API logs permission';
        case 'users_list.php':      return $has('view_user_list', 'view_role_assignment') ? '' : 'needs the Staff list permission';
        case 'manage_permissions.php': return $has('view_role_assignment') ? '' : 'needs the Role assignment permission';
        case 'customer_view.php':   return $has('view_client_list', 'edit_client') ? '' : 'needs the Customers permission';
        case 'backup.php':          return $user->cdp_is_Admin() ? '' : 'administrators only';
        case 'support_channels.php':
            require_once CDP_ROOT . '/helpers/rbac.php';
            return cdp_roleRankById((int) $user->userlevel) >= 2 ? '' : 'customer service staff only';
        case 'users_edit.php':
            parse_str((string) parse_url($href, PHP_URL_QUERY), $q);
            if ((int) ($q['user'] ?? 0) === (int) $user->uid) return '';
            if (!$has('edit_user') || !$user->cdp_is_Admin()) return 'needs the Edit staff permission';
            require_once CDP_ROOT . '/helpers/rbac.php';
            return $targetLevel === null || cdp_canManageUser($user, $targetLevel) ? '' : 'only a higher role can open this account';
    }
    return '';
}

function sl_secDate($value, string $fmt = 'M j, Y H:i'): string
{
    return $value ? sl_date($value, $fmt) : '';
}

function sl_securityData($user, $core): array
{
    $canAct = $user->cdp_hasPermission('edit_user');
    $isSuper = (int) ($_SESSION['userlevel'] ?? 0) === 9;
    $d = ['flash' => (string) ($_GET['flash'] ?? ''), 'canAct' => $canAct, 'ran' => date('H:i')];
    $now = date('Y-m-d H:i:s');
    $c24 = sl_secCut(86400);
    $c7 = sl_secCut(7 * 86400);
    $c30 = sl_secCut(30 * 86400);
    $tried = sl_secTriedSql();
    $hasLog = sl_tableExists('cdb_activity_log');
    $login = "module = 'auth' AND verb = 'login'";
    $failed = "$login AND outcome IN ('failure','denied')";
    $fail24 = $hasLog ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE $failed AND created_at >= :c", [':c' => $c24]) : 0;
    $ok24 = $hasLog ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE $login AND outcome = 'success' AND created_at >= :c", [':c' => $c24]) : 0;
    $fail7 = $hasLog ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE $failed AND created_at >= :c", [':c' => $c7]) : 0;
    $denied24 = $hasLog ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE outcome = 'denied' AND created_at >= :c", [':c' => $c24]) : 0;
    $burst = $hasLog ? sl_rows("SELECT ip, COUNT(*) AS n, MAX(created_at) AS last, COUNT(DISTINCT $tried) AS names, SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT $tried ORDER BY created_at DESC SEPARATOR ', '), ', ', 3) AS who FROM cdb_activity_log WHERE $failed AND created_at >= :c GROUP BY ip ORDER BY n DESC, last DESC LIMIT 8", [':c' => $c7]) : [];
    $otpOn = !empty($core->active_otp);
    $otp = sl_tableExists('cdb_auth_otp_challenges') ? sl_row("SELECT COUNT(*) AS n, SUM(status = 'verified') AS ok, SUM(status IN ('failed','locked','expired')) AS bad, SUM(status = 'pending') AS pend, SUM(status = 'replaced') AS rep FROM cdb_auth_otp_challenges WHERE created_at >= :c", [':c' => $c7]) : null;
    $hasDev = sl_tableExists('cdb_auth_trusted_devices');
    // Staff devices are what "Devices and log-ins" lists and manages; customers' remembered devices
    // are counted apart (customers forget their own in My account; the count shows on their profile).
    $dev = $hasDev ? sl_row('SELECT SUM(u.userlevel <> 1) AS n, COUNT(DISTINCT CASE WHEN u.userlevel <> 1 THEN t.user_id END) AS u, SUM(u.userlevel = 1) AS cn, COUNT(DISTINCT CASE WHEN u.userlevel = 1 THEN t.user_id END) AS cu FROM cdb_auth_trusted_devices t JOIN cdb_users u ON u.id = t.user_id AND u.active = 1 WHERE t.revoked_at IS NULL AND t.expires_at > :now', [':now' => $now]) : null;
    $devDays = defined('OtpService::TRUSTED_DEVICE_DAYS') ? (int) OtpService::TRUSTED_DEVICE_DAYS : 60;
    $hasPk = sl_tableExists('cdb_passkeys');
    $pkTotal = $hasPk ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_passkeys') : 0;
    $pkUsers = $hasPk ? (int) sl_scalar('SELECT COUNT(DISTINCT user_id) FROM cdb_passkeys') : 0;
    if (!function_exists('cdp_passkeysEnabled') && is_file(CDP_ROOT . '/helpers/webauthn.php')) {
        require_once CDP_ROOT . '/helpers/webauthn.php';
    }
    $pkOn = function_exists('cdp_passkeysEnabled') ? cdp_passkeysEnabled() : false;

    // API tokens (both kinds): user tokens may have no expiry.
    $tok = ['n' => 0, 'stale' => 0, 'rows' => []];
    $c90 = sl_secCut(90 * 86400);
    foreach (['cdb_api_tokens' => 'name', 'cdb_api_user_tokens' => 'device_name'] as $t => $nameCol) {
        if (!sl_tableExists($t)) continue;
        $live = "t.revoked_at IS NULL AND (t.expires_at IS NULL OR t.expires_at > :now)";
        $tok['n'] += (int) sl_scalar("SELECT COUNT(*) FROM $t t WHERE $live", [':now' => $now]);
        $tok['stale'] += (int) sl_scalar("SELECT COUNT(*) FROM $t t WHERE $live AND COALESCE(t.last_used_at, t.created_at) < :c", [':now' => $now, ':c' => $c90]);
        foreach (sl_rows("SELECT t.id, t.$nameCol AS name, t.created_at, t.last_used_at, t.expires_at, CONCAT(u.fname, ' ', u.lname) AS owner, u.username FROM $t t LEFT JOIN cdb_users u ON u.id = t.user_id WHERE $live ORDER BY COALESCE(t.last_used_at, t.created_at) ASC LIMIT 15", [':now' => $now]) as $r) {
            $stale = strtotime((string) ($r->last_used_at ?: $r->created_at)) < time() - 90 * 86400;
            $tok['rows'][] = [$r->name ?: '(no name)', trim((string) $r->owner) ?: ($r->username ?: '—'), $r->last_used_at ? sl_ago($r->last_used_at) : 'never', $r->expires_at ? sl_date($r->expires_at) : 'no expiry', ['t' => $stale ? 'Unused 90 d' : 'In use', 'pill' => $stale ? 'onway' : 'delivered']];
        }
    }
    $roles = (int) sl_scalar('SELECT COUNT(*) FROM cdb_user_roles');
    $actions = (int) sl_scalar('SELECT COUNT(*) FROM cdb_user_module_actions');
    $grants = (int) sl_scalar('SELECT COUNT(*) FROM cdb_user_role_permissions WHERE permitted = 1');
    $supers = sl_rows("SELECT id, fname, lname, username, email, lastlogin, lastip FROM cdb_users WHERE userlevel = 9 AND active = 1 ORDER BY lastlogin DESC");
    $rbac = defined('CDP_RBAC_MODE') ? (string) CDP_RBAC_MODE : 'audit';
    $b = sl_devBackup();
    $bAge = $b['latest_at'] ? (time() - $b['latest_at']) / 86400 : null;
    $my = sl_devMysql();
    $https = sl_devHttps();
    $headers = sl_devHeaders();
    $hdrApplies = array_values(array_filter($headers, fn($h) => $h['applies']));
    $hdrOk = count(array_filter($hdrApplies, fn($h) => $h['set']));
    $hdrHttps = count($headers) - count($hdrApplies);
    $missingHdr = array_map(fn($h) => $h['name'], array_filter($headers, fn($h) => $h['status'] === 'missing'));
    $jobs = sl_devCronJobs();
    $cronFails = array_sum(array_column($jobs, 'fails24'));
    $cronRuns = array_sum(array_column($jobs, 'runs24'));
    $q = sl_devQueue();
    $disk = sl_devDisk();
    $hasPwDate = sl_columnExists('cdb_users', 'password_changed_at');
    $hasFr = sl_columnExists('cdb_users', 'force_password_reset');
    $c180 = sl_secCut(180 * 86400);
    $staffN = (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel <> 1 AND active = 1');
    $pwOld = $hasPwDate ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel <> 1 AND active = 1 AND (password_changed_at IS NULL OR password_changed_at < :c)', [':c' => $c180]) : null;
    $forced = $hasFr ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE force_password_reset = 1 AND active = 1') : null;
    $errLog = sl_devErrorLogPath();
    $errWritable = $errLog !== '' && (is_writable($errLog) || (!file_exists($errLog) && is_writable(dirname($errLog))));
    $displayErr = (bool) ini_get('display_errors');
    $demo = defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true;
    $cors = defined('CDP_API_ALLOWED_ORIGINS') ? (string) CDP_API_ALLOWED_ORIGINS : '';
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $local = in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
    $logs = sl_route('ActivityLogs');
    $failHref = sl_route('ActivityLogs', ['verb' => 'login', 'outcome' => 'failure']);
    $editUser = fn(int $id) => 'users_edit.php?user=' . $id;
    $otpSettings = 'settings_form.php?f=system';

    $chk = [];
    $add = function (string $k, string $t, string $s, bool $ok, bool $bad, array $more) use (&$chk) {
        // A link the viewer's role cannot open is shown as plain text with the reason (index 3).
        foreach ((array) ($more['links'] ?? []) as $i => $l) $more['links'][$i][3] = sl_secNeeds((string) $l[1]);
        $chk[$k] = $more + ['k' => $k, 't' => $t, 's' => $s, 'd' => $ok ? '' : ($bad ? 'e' : 'w'), 'icon' => 'i-shield', 'what' => '', 'why' => '', 'fix' => '', 'facts' => [], 'tables' => [], 'links' => [], 'note' => '', 'jump' => '', 'wide' => false];
    };

    // 1. One-time code
    $add('otp', 'One-time code on sign-in', $otpOn ? 'On' . ($otp && (int) $otp->n ? ' · ' . (int) $otp->ok . ' of ' . (int) $otp->n . ' codes verified in 7 days' : ' · no codes sent in 7 days') : 'Off in Settings: staff sign in with a password only', $otpOn, false, [
        'icon' => 'i-key',
        'what' => 'Whether people must type a 6-digit code, sent by e-mail or WhatsApp, after their password (Settings › Company and sign-up, cdb_settings.active_otp), and how the codes sent in the last 7 days ended.',
        'why' => 'With the code on, a stolen or guessed password alone is not enough to get in. A device the person ticks as trusted skips the code for ' . $devDays . ' days.',
        'fix' => $otpOn ? 'Nothing to do. If many codes fail or expire, check that e-mail and WhatsApp messages are delivered (Message logs).' : 'Switch on "E-mail one-time code at sign-in and sign-up" in Settings › Company and sign-up. Check first that e-mail sending works, or staff will not receive their codes.',
        'facts' => [['Setting', $otpOn ? 'On' : 'Off'], ['Codes sent (7 d)', $otp ? sl_num($otp->n) : '0'], ['Verified', $otp ? sl_num($otp->ok) : '0'], ['Failed, expired or locked', $otp ? sl_num($otp->bad) : '0'], ['Replaced by a new code', $otp ? sl_num($otp->rep) : '0'], ['Limits', '1 code a minute, 5 per 30 min, 5 tries per code']],
        'links' => [['Settings › Company and sign-up', $otpSettings, 'i-cog'], ['Message logs', sl_route('MessageLogs'), 'i-mail']],
    ]);
    // 2. HTTPS
    $add('https', 'HTTPS', $https['request'] ? 'This request came over HTTPS' : ('This request is plain HTTP' . ($https['configured'] ? ' · site URL is https' : ' · site URL ' . ($https['url'] ?: 'not set'))), $https['request'] || $https['configured'], !$https['configured'], [
        'icon' => 'i-lock',
        'what' => 'Whether this page reached the server over https, and whether the site address in config/config.php (CDP_APP_URL) starts with https://.',
        'why' => 'Over plain http, passwords, sign-in cookies and customer data travel unencrypted and can be read or changed on the way (public Wi-Fi, a shared network). Fingerprint sign-in (passkeys) and the Secure cookie flag only work on https.',
        'fix' => $https['request'] ? 'Nothing to do. Keep the certificate renewing automatically.' : 'On the web server, install a certificate (Let\'s Encrypt is free), redirect every http request to https, then set CDP_APP_URL in config/config.php to the https:// address.' . ($local ? ' This copy is opened on a local test address (' . $host . '), which is normally plain http; this only matters on the live site.' : ''),
        'facts' => [['This request', $https['request'] ? 'https' : 'plain http'], ['Site address (CDP_APP_URL)', $https['url'] ?: 'not set'], ['Host name', $host ?: '—'], ['Strict-Transport-Security', $https['request'] ? 'sent' : 'sent once on https']],
        'links' => [['Configuration (Developer › Infrastructure)', sl_route('DevInfrastructure'), 'i-server']],
    ]);
    // 3. Security headers
    $hdrRows = [];
    foreach ($headers as $h) {
        $pill = $h['status'] === 'set' ? ['t' => 'Set', 'pill' => 'delivered'] : ($h['status'] === 'https' ? ['t' => 'Applies on HTTPS', 'pill' => 'transit'] : ['t' => 'Missing', 'pill' => 'pending']);
        $hdrRows[] = [['t' => $h['name'], 'b' => true], $pill, ['t' => $h['value'] !== '' ? $h['value'] : ($h['status'] === 'https' ? 'sent on https requests' : '—'), 'code' => $h['value'] !== ''], ['t' => $h['purpose'], 'sub' => $h['status'] === 'missing' ? $h['fix'] : '']];
    }
    $add('headers', 'Security headers', $hdrOk . ' of ' . count($hdrApplies) . ' set' . ($missingHdr ? ' · missing: ' . implode(', ', array_slice($missingHdr, 0, 3)) : '') . ($hdrHttps ? ' · ' . $hdrHttps . ' apply on HTTPS only' : ''), !$missingHdr, false, [
        'icon' => 'i-shield', 'wide' => true,
        'what' => 'The protective headers and sign-in cookie flags this page was sent with. loader.php adds them to every page; this check reads them back from the response of this very page.',
        'why' => 'They tell the browser to refuse common attacks: showing the site inside another site\'s frame, running an upload as a script, leaking page addresses, or sending the sign-in cookie to other websites or over plain http.',
        'fix' => $missingHdr ? 'Missing: ' . implode(', ', $missingHdr) . '. The line under each one in the table says where it is set.' : 'Nothing to do.' . ($hdrHttps ? ' Strict-Transport-Security and the Secure cookie flag switch on by themselves when the site is opened over https.' : ''),
        'tables' => [['head' => ['Header or cookie flag', 'Status', 'Current value', 'What it does'], 'rows' => $hdrRows]],
        'note' => ($hdrHttps ? 'This page was opened over plain http, so the two HTTPS-only items are shown as "Applies on HTTPS" and are not counted as missing. ' : '') . 'Headers added by the web server (Apache, nginx or a CDN) are not visible to PHP; check those in the browser\'s developer tools (Network tab) on the live site.',
    ]);
    // 4. Failed sign-ins
    $failRows = [];
    if ($hasLog) {
        foreach (sl_rows("SELECT created_at, $tried AS who, ip, user_agent, summary, outcome FROM cdb_activity_log WHERE $failed ORDER BY created_at DESC, id DESC LIMIT 20") as $r) {
            $failRows[] = [['t' => sl_secDate($r->created_at, 'M j H:i'), 'sub' => sl_ago($r->created_at)], ['t' => $r->who, 'b' => true], ['t' => $r->ip ?: '—', 'mono' => true, 'href' => $r->ip ? sl_route('ActivityLogs', ['verb' => 'login', 'search' => $r->ip]) : ''], sl_secDevice((string) $r->user_agent) ?: '—', sl_secReason((string) $r->summary, $r->outcome === 'denied' ? 'Refused' : 'Failed')];
        }
    }
    $burstRows = array_map(fn($r) => [['t' => $r->ip ?: 'unknown', 'mono' => true, 'href' => $r->ip ? sl_route('ActivityLogs', ['verb' => 'login', 'outcome' => 'failure', 'search' => $r->ip]) : ''], ['t' => sl_num($r->n), 'num' => true], (string) $r->who . ((int) $r->names > 3 ? ' +' . ((int) $r->names - 3) : ''), sl_ago($r->last)], $burst);
    $throttled = [];
    if (sl_tableExists('cdb_login_attempts')) {
        $win = sl_secCut(User::LOGIN_WINDOW_MIN * 60);
        foreach (sl_rows('SELECT ip, COUNT(*) AS n FROM cdb_login_attempts WHERE created_at >= :c GROUP BY ip HAVING n >= :m', [':c' => $win, ':m' => User::LOGIN_MAX_PER_IP]) as $r) $throttled[] = 'address ' . $r->ip;
        foreach (sl_rows('SELECT username, ip, COUNT(*) AS n FROM cdb_login_attempts WHERE created_at >= :c GROUP BY username, ip HAVING n >= :m', [':c' => $win, ':m' => User::LOGIN_MAX_PER_USER_IP]) as $r) $throttled[] = $r->username . ' from ' . $r->ip;
    }
    $add('fails', 'Failed sign-ins (24 h)', $fail24 . ' failed · ' . $ok24 . ' succeeded' . ($burst ? ' · most from ' . ($burst[0]->ip ?: 'unknown') . ' (' . (int) $burst[0]->n . ' in 7 d)' : ''), $fail24 <= 20, false, [
        'icon' => 'i-alert', 'wide' => true,
        'what' => 'Sign-in attempts with a wrong password, an unknown username or a refused passkey, from the audit trail (Activity logs).',
        'why' => 'Many failures from one address usually mean someone is guessing passwords. The app blocks an address after ' . User::LOGIN_MAX_PER_USER_IP . ' wrong passwords for the same username, or ' . User::LOGIN_MAX_PER_IP . ' for any username, within ' . User::LOGIN_WINDOW_MIN . ' minutes.',
        'fix' => 'If one address keeps failing, block it on the firewall or web server. Force a password reset for any staff account that was targeted (open the person in Devices and log-ins). A staff member who locked themselves out can wait ' . User::LOGIN_WINDOW_MIN . ' minutes or use Forgot password.',
        'facts' => [['Failed (24 h)', sl_num($fail24)], ['Succeeded (24 h)', sl_num($ok24)], ['Failed (7 d)', sl_num($fail7)], ['Blocked right now', $throttled ? implode(', ', array_slice($throttled, 0, 4)) : 'nobody']],
        'tables' => [['title' => 'Latest failures', 'head' => ['When', 'Username tried', 'Address', 'Browser', 'Reason'], 'rows' => $failRows, 'empty' => 'No failed sign-ins logged.'], ['title' => 'By address · 7 days', 'head' => ['Address', 'Failures', 'Usernames tried', 'Last'], 'rows' => $burstRows, 'empty' => 'No failures in 7 days.']],
        'note' => 'The counts include refused attempts (for example a passkey that was not accepted). Activity logs keep those apart, under "Refused sign-ins".',
        'links' => [['Activity logs: failed sign-ins', $failHref, 'i-activity'], ['Activity logs: refused sign-ins', sl_route('ActivityLogs', ['verb' => 'login', 'outcome' => 'denied']), 'i-ban'], ['All security activity', sl_route('ActivityLogs', ['filter' => 'security']), 'i-shield']],
    ]);
    // 5. Permission denials
    $denRows = $hasLog ? array_map(fn($r) => [['t' => sl_secDate($r->created_at, 'M j H:i'), 'sub' => sl_ago($r->created_at)], ['t' => $r->actor_name ?: ($r->actor_username ?: 'unknown'), 'b' => true], (string) ($r->summary ?: $r->action_label), ['t' => (string) $r->endpoint, 'code' => true]], sl_rows("SELECT created_at, actor_name, actor_username, summary, action_label, endpoint FROM cdb_activity_log WHERE outcome = 'denied' ORDER BY created_at DESC, id DESC LIMIT 10")) : [];
    $add('denials', 'Permission denials (24 h)', $denied24 . ' blocked actions logged', $denied24 <= 50, false, [
        'icon' => 'i-ban', 'wide' => (bool) $denRows,
        'what' => 'Actions the role permissions refused (someone opened a page or pressed a button their role does not allow).',
        'why' => 'A few are normal (a link followed by mistake). Many from one person can mean a missing permission they really need, or someone probing what they can reach.',
        'fix' => $denied24 > 50 ? 'Look at who is refused most in Activity logs. Give the permission in Staff permissions if the work needs it; otherwise talk to the person.' : 'Nothing to do.',
        'facts' => [['Refused (24 h)', sl_num($denied24)], ['Enforcement', $rbac]],
        'tables' => $denRows ? [['title' => 'Latest refusals', 'head' => ['When', 'Who', 'What', 'Page'], 'rows' => $denRows]] : [],
        'links' => [['Activity logs: refused actions', sl_route('ActivityLogs', ['outcome' => 'denied']), 'i-activity'], ['Staff permissions', sl_route('StaffPermissions'), 'i-lock']],
    ]);
    // 6. Roles and permissions
    $roleRows = [];
    foreach (sl_rows("SELECT r.role_id, r.role_name, r.is_superadmin, r.is_admin, r.is_staff, r.is_driver, r.is_client, r.is_agency, (SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel = r.role_id AND u.active = 1) AS people, (SELECT COUNT(*) FROM cdb_user_role_permissions p WHERE p.role_id = r.role_id AND p.permitted = 1) AS g FROM cdb_user_roles r WHERE r.rol_active = 1 ORDER BY r.role_id") as $r) {
        $kind = $r->is_superadmin ? 'Super admin' : ($r->is_admin ? 'Admin' : ($r->is_driver ? 'Driver' : ($r->is_client ? 'Customer' : ($r->is_agency ? 'Agency' : ($r->is_staff ? 'Staff' : '—')))));
        $roleRows[] = [['t' => $r->role_name, 'b' => true], $kind, ['t' => sl_num($r->people), 'num' => true], ['t' => $r->is_superadmin ? 'all' : sl_num($r->g) . ' of ' . sl_num($actions), 'num' => true]];
    }
    $add('roles', 'Roles and permissions', $roles . ' roles · ' . $actions . ' actions · ' . $grants . ' grants · enforcement ' . $rbac, $rbac === 'enforce', false, [
        'icon' => 'i-users',
        'what' => 'The roles, the actions each one may do, and whether refusals are enforced (config/config.php CDP_RBAC_MODE).',
        'why' => 'In "enforce" mode a staff member without a permission is stopped. In "audit" mode the action is only written to the log, and in "off" nothing is checked.',
        'fix' => $rbac === 'enforce' ? 'Nothing to do. Review the grants when someone changes job.' : 'Set CDP_RBAC_MODE to \'enforce\' in config/config.php after checking in Activity logs that no needed action is being refused.',
        'facts' => [['Enforcement (CDP_RBAC_MODE)', $rbac], ['Roles', sl_num($roles)], ['Actions', sl_num($actions)], ['Grants', sl_num($grants)]],
        'tables' => [['head' => ['Role', 'Kind', 'People', 'Allowed actions'], 'rows' => $roleRows]],
        'links' => [['Staff permissions', sl_route('StaffPermissions'), 'i-lock'], ['Staff and roles', sl_route('StaffRoles'), 'i-users']],
    ]);
    // 7. Super admins
    $superRows = array_map(fn($r) => [['t' => trim($r->fname . ' ' . $r->lname) ?: $r->username, 'b' => true, 'href' => sl_secNeeds($editUser((int) $r->id), 9) === '' ? $editUser((int) $r->id) : '', 'sub' => $r->username], $r->lastlogin ? sl_ago($r->lastlogin) . ($r->lastip ? ' · ' . $r->lastip : '') : 'never', ['user' => (int) $r->id, 't' => 'Devices and sign-ins']], $supers);
    $add('supers', 'Super admins', count($supers) . ' active', count($supers) > 0 && count($supers) <= 3, count($supers) === 0, [
        'icon' => 'i-star',
        'what' => 'Active accounts with the Super admin role (full control, including other people\'s rights and every setting).',
        'why' => 'Every super admin account is a key to everything. Fewer keys mean fewer chances that one is stolen or misused. Only a super admin can change another super admin.',
        'fix' => count($supers) === 0 ? 'No active super admin: give the role to one trusted person in Staff and roles.' : (count($supers) > 3 ? 'Keep 1 to 3. Give the others the Administrator or Employee role in Staff and roles.' : 'Nothing to do. Remove the role when someone no longer needs it.'),
        'tables' => [['head' => ['Name', 'Last sign-in', ''], 'rows' => $superRows, 'empty' => 'No active super admin.']],
        'links' => [['Staff and roles', sl_route('StaffRoles'), 'i-users']],
    ]);
    // 8. API tokens
    $add('tokens', 'API tokens', $tok['n'] . ' active · ' . $tok['stale'] . ' unused over 90 days', $tok['stale'] === 0, false, [
        'icon' => 'i-key', 'wide' => (bool) $tok['rows'],
        'what' => 'Live keys that let apps and integrations call the API (cdb_api_tokens and cdb_api_user_tokens), and which of them nobody has used for 90 days.',
        'why' => 'A forgotten token still opens the API. Unused tokens are the easiest to leak without anyone noticing.',
        'fix' => $tok['stale'] ? 'Revoke the tokens marked "Unused 90 d" in API access, unless an integration that runs rarely still needs them.' : 'Nothing to do.',
        'facts' => [['Active tokens', sl_num($tok['n'])], ['Unused over 90 days', sl_num($tok['stale'])]],
        'tables' => $tok['rows'] ? [['title' => 'Least recently used first', 'head' => ['Token', 'Owner', 'Last used', 'Expires', ''], 'rows' => $tok['rows']]] : [],
        'links' => [['API access', sl_route('ApiAccess'), 'i-link'], ['Live API traffic', sl_route('ApiTraffic'), 'i-activity']],
    ]);
    // 9. CORS
    $add('cors', 'CORS for the API', $cors === '' ? 'Not set' : ($cors === '*' ? 'Any origin (*)' : $cors), $cors !== '*', false, [
        'icon' => 'i-globe',
        'what' => 'Which other websites may call the API from a visitor\'s browser (config/config.php CDP_API_ALLOWED_ORIGINS).',
        'why' => 'With "*" any website can make a signed-in visitor\'s browser talk to the API. Server-to-server integrations are not affected by this setting.',
        'fix' => $cors === '*' ? 'Replace * with the exact web addresses that need it, comma separated (for example https://shop.example.com) in config/config.php.' : ($cors === '' ? 'Nothing to do: browsers on other websites cannot call the API, which is the safe default. Set it only when a web app on another address must call the API.' : 'Nothing to do. Keep the list to the addresses that really need it.'),
        'facts' => [['CDP_API_ALLOWED_ORIGINS', $cors === '' ? 'not set' : $cors]],
        'links' => [['API access', sl_route('ApiAccess'), 'i-link']],
    ]);
    // 10. Trusted devices: jumps to the devices section
    $add('devices', 'Trusted devices', $dev ? (int) $dev->n . ' staff devices for ' . (int) $dev->u . ' ' . ((int) $dev->u === 1 ? 'person' : 'people') . ((int) $dev->cn ? ' · ' . (int) $dev->cn . ' customer devices (on each customer\'s own account)' : '') : 'Table missing', (bool) $dev, false, ['icon' => 'i-monitor', 'jump' => '#sc-devices']);
    // 11. Staff passwords
    $pwRows = [];
    if ($hasPwDate || $hasFr) {
        $cond = [];
        if ($hasPwDate) $cond[] = '(u.password_changed_at IS NULL OR u.password_changed_at < :c)';
        if ($hasFr) $cond[] = 'u.force_password_reset = 1';
        foreach (sl_rows('SELECT u.id, u.fname, u.lname, u.username, u.userlevel, r.role_name' . ($hasPwDate ? ', u.password_changed_at AS pc' : ', NULL AS pc') . ($hasFr ? ', u.force_password_reset AS fr' : ', 0 AS fr') . ' FROM cdb_users u LEFT JOIN cdb_user_roles r ON r.role_id = u.userlevel WHERE u.userlevel <> 1 AND u.active = 1 AND (' . implode(' OR ', $cond) . ') ORDER BY ' . ($hasPwDate ? 'u.password_changed_at IS NULL DESC, u.password_changed_at ASC' : 'u.id') . ' LIMIT 50', $hasPwDate ? [':c' => $c180] : []) as $r) {
            $mayAct = $canAct && ((int) $r->userlevel !== 9 || $isSuper);
            $pwRows[] = [['t' => trim($r->fname . ' ' . $r->lname) ?: $r->username, 'b' => true, 'sub' => $r->role_name ?: ''], $r->pc ? sl_secAge($r->pc) : 'never recorded', (int) $r->fr ? ['t' => 'Must reset', 'pill' => 'pending'] : ['t' => 'Over 180 days', 'pill' => 'onway'],
                $mayAct ? ['act' => (int) $r->fr ? ['op' => 'clear_reset', 'id' => (int) $r->id, 'label' => 'Clear reset'] : ['op' => 'force_reset', 'id' => (int) $r->id, 'label' => 'Force reset', 'confirm' => 'Ask ' . (trim($r->fname . ' ' . $r->lname) ?: $r->username) . ' to choose a new password at the next sign-in?']] : ['user' => (int) $r->id, 't' => 'Details']];
        }
    }
    $add('passwords', 'Staff passwords', $pwOld === null ? 'Change dates not recorded' : $pwOld . ' of ' . $staffN . ' staff not changed in 180 days' . ($forced ? ' · ' . $forced . ' must reset' : ''), $pwOld === 0, false, [
        'icon' => 'i-lock', 'wide' => (bool) $pwRows,
        'what' => 'Active staff accounts whose password was last changed more than 180 days ago (or never since the date started being recorded), and accounts already asked to choose a new one.',
        'why' => 'Old passwords have had more time to be reused elsewhere, written down or leaked. "Force reset" makes the person choose a new password at their next sign-in, before anything else.',
        'fix' => $pwOld ? 'Use Force reset for the people below, starting with the oldest. Clear the flag if you set it by mistake.' : 'Nothing to do.',
        'facts' => [['Staff accounts', sl_num($staffN)], ['Not changed in 180 days', $pwOld === null ? 'not recorded' : sl_num($pwOld)], ['Must reset now', $forced === null ? 'not available' : sl_num($forced)]],
        'tables' => [['head' => ['Name', 'Password changed', 'Status', ''], 'rows' => $pwRows, 'empty' => $pwOld === null ? 'Run migrations/2026_11_06_freight_devops.sql to record change dates.' : 'Every staff password is recent.']],
    ]);
    // 12. Backups
    $bkRows = [];
    if (is_dir($b['dir'])) {
        $files = glob($b['dir'] . '/*.sql') ?: [];
        usort($files, fn($x, $y) => filemtime($y) <=> filemtime($x));
        foreach (array_slice($files, 0, 5) as $f) $bkRows[] = [['t' => basename($f), 'code' => true], sl_devBytes(filesize($f)), sl_date(filemtime($f), 'M j, Y H:i') . ' · ' . sl_ago(filemtime($f))];
    }
    $add('backups', 'Database backups', $b['latest'] ? 'Last ' . sl_date($b['latest_at'], 'M j H:i') . ' · ' . sl_devBytes($b['latest_size']) . ' · ' . $b['count'] . ' files' : 'No backup file in backups/', $bAge !== null && $bAge <= 7, $bAge === null, [
        'icon' => 'i-archive',
        'what' => 'The newest database backup file in the backups/ folder, written by the Backup tool.',
        'why' => 'Without a recent backup, a broken server, a bad import or a ransomware attack can lose shipments, payments and customers for good.',
        'fix' => $bAge !== null && $bAge <= 7 ? 'Nothing to do. Also copy backups off this server regularly: a backup on the same disk is lost with it.' : 'Open the Backup tool and make a backup now. Then make one at least weekly (daily is better) and copy the files to another place.',
        'facts' => [['Folder', 'backups/'], ['Files', sl_num($b['count'])], ['Newest', $b['latest'] ? sl_date($b['latest_at'], 'M j, Y H:i') . ' (' . sl_ago($b['latest_at']) . ')' : 'none']],
        'tables' => $bkRows ? [['title' => 'Newest files', 'head' => ['File', 'Size', 'Made'], 'rows' => $bkRows]] : [],
        'links' => [['Backup tool', 'backup.php', 'i-archive']],
    ]);
    // 13. PHP
    $phpOk = version_compare(PHP_VERSION, '8.2', '>=');
    $opc = function_exists('opcache_get_status') && @opcache_get_status(false);
    $add('php', 'PHP ' . PHP_VERSION, $phpOk ? 'Supported version' : 'Upgrade to 8.2 or newer', $phpOk, false, [
        'icon' => 'i-code',
        'what' => 'The PHP version and main limits of the server running the app.',
        'why' => 'Old PHP versions stop getting security fixes. 8.2 and newer are supported.',
        'fix' => $phpOk ? 'Nothing to do. Install PHP updates when your host offers them.' : 'Ask your host (or use the control panel) to switch this site to PHP 8.2 or newer.',
        'facts' => [['Version', PHP_VERSION], ['Server API', PHP_SAPI], ['memory_limit', (string) ini_get('memory_limit')], ['upload_max_filesize', (string) ini_get('upload_max_filesize')], ['post_max_size', (string) ini_get('post_max_size')], ['max_execution_time', ini_get('max_execution_time') . ' s'], ['OPcache', $opc ? 'on' : 'off']],
        'links' => [['Developer › Infrastructure', sl_route('DevInfrastructure'), 'i-server']],
    ]);
    // 14. MySQL
    $add('mysql', 'MySQL ' . ($my['version'] ?: '?'), $my['ok'] ? $my['threads'] . ' / ' . $my['max_conn'] . ' connections' : 'Not answering', $my['ok'], !$my['ok'], [
        'icon' => 'i-database',
        'what' => 'Whether the database answers, its version and how many of its connections are in use.',
        'why' => 'When connections run out, pages fail with database errors. A slow-query log helps find the pages that load the database most.',
        'fix' => $my['ok'] ? 'Nothing to do.' . (($my['slow_log'] ?? '0') === '0' || ($my['slow_log'] ?? '') === 'OFF' ? ' Optional: switch on the slow-query log on the database server.' : '') : 'Check that the database server runs and the settings in config/config.php are right.',
        'facts' => [['Version', $my['version'] ?: '—'], ['Connections', $my['threads'] . ' of ' . $my['max_conn']], ['Up for', floor($my['uptime'] / 86400) . ' d ' . floor($my['uptime'] % 86400 / 3600) . ' h'], ['Answer time', $my['ms'] . ' ms'], ['Slow-query log', in_array((string) ($my['slow_log'] ?? '0'), ['1', 'ON'], true) ? 'on (over ' . ($my['long_query'] ?? 10) . ' s)' : 'off'], ['Slow queries since start', sl_num($my['slow'])]],
        'links' => [['Developer › Metrics', sl_route('DevMetrics'), 'i-chart']],
    ]);
    // 15. Errors hidden
    $add('display', 'Errors hidden from visitors', $displayErr ? 'display_errors is ON: errors print in pages' : 'display_errors off', !$displayErr, false, [
        'icon' => 'i-eye',
        'what' => 'Whether PHP prints error messages into the pages people see (config/config.php CDP_DISPLAY_ERRORS).',
        'why' => 'Printed errors show file paths, database names and query details that help an attacker. Errors should go to the error log only.',
        'fix' => $displayErr ? 'Set CDP_DISPLAY_ERRORS to false in config/config.php. Read errors in Developer logs instead.' : 'Nothing to do.',
        'facts' => [['display_errors', $displayErr ? 'on' : 'off'], ['CDP_DISPLAY_ERRORS', defined('CDP_DISPLAY_ERRORS') ? (CDP_DISPLAY_ERRORS ? 'true' : 'false') : 'not set'], ['log_errors', ini_get('log_errors') ? 'on' : 'off']],
        'links' => [['Developer logs', sl_route('DevLogs', ['tab' => 'php']), 'i-terminal']],
    ]);
    // 16. Error log
    $errSize = $errLog !== '' && is_file($errLog) ? (int) filesize($errLog) : 0;
    $add('errlog', 'Error log', $errLog !== '' ? ($errLog . ($errWritable ? ' · writable' : ' · not writable')) : 'CDP_ERROR_LOG not set', $errLog !== '' && $errWritable, false, [
        'icon' => 'i-file',
        'what' => 'Where PHP writes its errors and warnings (CDP_ERROR_LOG, or error_log in php.ini) and whether the app can write there.',
        'why' => 'With errors hidden from visitors, the log is the only place problems show up. If nobody can read it, failures go unnoticed.',
        'fix' => $errLog === '' ? 'Set CDP_ERROR_LOG in config/config.php to a file outside the web folder, for example /var/log/swiftlane/php-error.log.' : ($errWritable ? 'Nothing to do. Developer logs reads it for you.' : 'Give the web server user write access to the file or its folder.'),
        'facts' => [['File', $errLog ?: 'not set'], ['Writable', $errWritable ? 'yes' : 'no'], ['Size', sl_devBytes($errSize)], ['Warnings and errors (last hour)', $errLog !== '' ? sl_num(sl_devPhpErrorCount(60)) : '—']],
        'links' => [['Developer logs', sl_route('DevLogs', ['tab' => 'php']), 'i-terminal']],
    ]);
    // 17. Demo mode
    $add('demo', 'Demo mode', $demo ? 'ON: writes are refused' : 'Off', !$demo, false, [
        'icon' => 'i-play',
        'what' => 'Whether the installation runs as a read-only demo (config/config.php CDP_APP_MODE_DEMO).',
        'why' => 'In demo mode saving is refused everywhere, so a live site in demo mode cannot take shipments or payments.',
        'fix' => $demo ? 'Set CDP_APP_MODE_DEMO to false in config/config.php on the live site.' : 'Nothing to do.',
        'facts' => [['CDP_APP_MODE_DEMO', $demo ? 'true' : 'false']],
    ]);
    // 18. Cron jobs
    $cronRows = [];
    foreach ($jobs as $j) {
        $last = $j['last'];
        $st = !$last ? ['t' => 'Never ran', 'pill' => 'plain'] : ($last->status === 'failed' ? ['t' => 'Failed', 'pill' => 'pending'] : (in_array($last->status, ['ok', 'success', 'done'], true) ? ['t' => 'OK', 'pill' => 'delivered'] : ['t' => ucfirst((string) $last->status), 'pill' => 'onway']));
        $cronRows[] = [['t' => $j['name'], 'b' => true, 'sub' => $j['what']], ['t' => $j['schedule'] ?: '—', 'code' => $j['schedule'] !== ''], $last ? sl_ago($last->started_at) . ($last->duration_ms !== null ? ' · ' . sl_devMs($last->duration_ms) : '') : '—', $st, $j['runs24'] . ' runs · ' . $j['fails24'] . ' failed'];
    }
    $add('cron', 'Cron jobs', $cronRuns ? $cronRuns . ' runs in 24 h · ' . $cronFails . ' failed' : 'No runs recorded in 24 h (cdb_cron_runs)', $cronRuns > 0 && $cronFails === 0, $cronFails > 0, [
        'icon' => 'i-clock', 'wide' => true,
        'what' => 'The background scripts in cron/ (sending queued messages, alerts, clean-up) and their runs in the last 24 hours (cdb_cron_runs).',
        'why' => 'If cron does not run, queued WhatsApp, SMS and e-mail messages are not sent, alerts do not fire and old uploads pile up.',
        'fix' => $cronRuns ? ($cronFails ? 'Open Developer logs › Cron runs to read why the failed job stopped.' : 'Nothing to do.') : 'Add the scripts to the server\'s crontab (crontab -e), each with the schedule shown below. For example, the message worker every minute:',
        'fixCode' => $cronRuns ? '' : '* * * * * php ' . CDP_ROOT . '/cron/comms_worker.php',
        'tables' => [['head' => ['Job', 'Schedule', 'Last run', 'Status', 'Last 24 h'], 'rows' => $cronRows, 'empty' => 'No scripts in cron/.']],
        'links' => [['Developer logs › Cron runs', sl_route('DevLogs', ['tab' => 'cron']), 'i-terminal']],
    ]);
    // 19. Notification queue
    $add('queue', 'Notification queue', $q['exists'] ? $q['queued'] . ' waiting · ' . $q['dead'] . ' dead' : 'Outbox not created yet', !$q['exists'] || ($q['dead'] === 0 && $q['queued'] < 200), false, [
        'icon' => 'i-send',
        'what' => 'Messages waiting to be sent by the background worker (cdb_cs_outbox): waiting, retrying, given up ("dead") and sent in the last day.',
        'why' => 'A growing queue means customers are not getting their WhatsApp, SMS or e-mail updates. Dead messages failed every retry.',
        'fix' => !$q['exists'] ? 'Nothing to do: the outbox is created the first time Customer service sends something.' : ($q['dead'] || $q['queued'] >= 200 ? 'Check that cron/comms_worker.php runs (Cron jobs check) and that the channels are set up in Customer service › Channels.' : 'Nothing to do.'),
        'facts' => [['Waiting', sl_num($q['queued'])], ['Retrying', sl_num($q['retry'])], ['Dead', sl_num($q['dead'])], ['Sent (24 h)', sl_num($q['sent24'])], ['Oldest waiting', $q['queued'] ? $q['oldest_min'] . ' min' : '—']],
        'links' => [['Customer service › Channels', sl_route('CsChannels'), 'i-send'], ['Message logs', sl_route('MessageLogs'), 'i-mail']],
    ]);
    // 20. Disk
    $add('disk', 'Disk', $disk['pct'] . '% used · ' . sl_devBytes($disk['free']) . ' free', $disk['pct'] < 85, $disk['pct'] >= 95, [
        'icon' => 'i-folder',
        'what' => 'Space on the disk that holds the app, its uploads and backups.',
        'why' => 'A full disk stops uploads, backups, sessions and the database from writing, which breaks sign-in and saving.',
        'fix' => $disk['pct'] < 85 ? 'Nothing to do.' : 'Delete old backup files after copying them elsewhere, clear old logs, or ask your host for more space.',
        'facts' => [['Used', sl_devBytes($disk['used']) . ' (' . $disk['pct'] . '%)'], ['Free', sl_devBytes($disk['free'])], ['Total', sl_devBytes($disk['total'])]],
        'links' => [['Developer › Infrastructure', sl_route('DevInfrastructure'), 'i-server']],
    ]);

    $d['chk'] = array_values($chk);
    $warn = count(array_filter($chk, fn($c) => $c['d'] !== ''));
    $d['passN'] = count($chk) - $warn;
    $d['warnN'] = $warn;
    $spark = function (string $sql, array $binds): array {
        $map = [];
        foreach (sl_rows($sql, $binds) as $r) $map[$r->d] = (int) $r->n;
        $v = [];
        for ($i = 11; $i >= 0; $i--) $v[] = $map[date('Y-m-d', strtotime("-$i days"))] ?? 0;
        return sl_sparkBars($v, 6);
    };
    $d['kpis'] = [
        ['icon' => 'i-shield', 'l' => 'Checks passing', 'v' => $d['passN'] . ' / ' . count($chk), 's' => 'Computed on page load', 'sp' => [], 'href' => '#sc-checks', 'aria' => 'Show all security checks'],
        ['icon' => 'i-x', 'l' => 'Failed sign-ins (24 h)', 'v' => sl_num($fail24), 's' => sl_num($ok24) . ' succeeded · ' . sl_num($fail7) . ' failed in 7 d', 'sp' => $hasLog ? $spark("SELECT DATE(created_at) AS d, COUNT(*) AS n FROM cdb_activity_log WHERE $failed AND created_at >= :c GROUP BY d", [':c' => date('Y-m-d', strtotime('-11 days')) . ' 00:00:00']) : [], 'open' => 'sc-d-fails', 'aria' => 'Open failed sign-ins'],
        ['icon' => 'i-key', 'l' => 'One-time codes', 'v' => $otpOn ? 'On' : 'Off', 's' => $otp ? (int) $otp->n . ' sent · ' . (int) $otp->bad . ' failed (7 d)' : '—', 'sp' => [], 'open' => 'sc-d-otp', 'aria' => 'Open the one-time code check'],
        ['icon' => 'i-monitor', 'l' => 'Trusted devices', 'v' => $dev ? sl_num($dev->n) : '0', 's' => $dev ? 'Staff, ' . (int) $dev->u . ' ' . ((int) $dev->u === 1 ? 'person' : 'people') . ((int) $dev->cn ? ' · ' . (int) $dev->cn . ' more on customer accounts' : '') : 'Table missing', 'sp' => [], 'href' => '#sc-devices', 'aria' => 'Go to devices and log-ins'],
        ['icon' => 'i-alert', 'l' => 'Open security items', 'v' => (string) $warn, 's' => $warn ? 'See the checks below' : 'All clear', 'sp' => [], 'href' => '#sc-checks', 'filter' => 'warn', 'aria' => 'Show the checks to look at'],
    ];

    // ── Devices and log-ins: every active staff account ──────────────────────
    $d += sl_securityPeople($canAct, $isSuper, $hasLog, $hasDev, $hasPk, $hasPwDate, $hasFr, $now, $c30, $c180);
    $d['recent'] = [];
    $mayCust = sl_secNeeds(sl_route('CustomerProfile', ['id' => 1])) === '';
    if ($hasLog) {
        foreach (sl_rows("SELECT l.user_id, l.actor_name, " . sl_secTriedSql('l') . " AS who, l.outcome, l.ip, l.user_agent, l.created_at, l.summary, u.userlevel, u.fname, u.lname FROM cdb_activity_log l LEFT JOIN cdb_users u ON u.id = l.user_id AND l.user_id > 0 WHERE l.module = 'auth' AND l.verb = 'login' ORDER BY l.created_at DESC, l.id DESC LIMIT 12") as $r) {
            $ok = $r->outcome === 'success';
            $name = $r->fname !== null ? (trim($r->fname . ' ' . $r->lname) ?: $r->who) : $r->who;
            $d['recent'][] = ['t' => $name, 'ok' => $ok, 'ip' => (string) $r->ip, 'dev' => sl_secDevice((string) $r->user_agent), 'ago' => sl_ago($r->created_at), 'at' => sl_secDate($r->created_at), 'why' => $ok ? (stripos((string) $r->summary, 'code sent') !== false ? 'Password accepted, code sent' : '') : sl_secReason((string) $r->summary),
                'user' => $r->userlevel !== null && (int) $r->userlevel !== 1 ? (int) $r->user_id : 0, 'cust' => $r->userlevel !== null && (int) $r->userlevel === 1 && $mayCust ? (int) $r->user_id : 0];
        }
    }
    $mayLogs = sl_secNeeds($logs) === '';
    $d['burst'] = array_map(fn($r) => ['ip' => $r->ip ?: 'unknown', 'n' => (int) $r->n, 'last' => sl_ago($r->last), 'who' => (string) $r->who . ((int) $r->names > 3 ? ' +' . ((int) $r->names - 3) : ''), 'href' => $mayLogs ? sl_route('ActivityLogs', ['verb' => 'login', 'outcome' => 'failure', 'search' => (string) $r->ip]) : ''], $burst);
    $d['logsHref'] = $mayLogs ? sl_route('ActivityLogs', ['verb' => 'login']) : '';
    $d['failHref'] = $failHref;
    $d['policy'] = [
        ['t' => 'Require a code on staff sign-in', 'd' => 'Settings › Company and sign-up (cdb_settings.active_otp)', 'on' => $otpOn, 'href' => $otpSettings],
        ['t' => 'Enforce role permissions', 'd' => 'config.php CDP_RBAC_MODE = ' . $rbac, 'on' => $rbac === 'enforce', 'href' => sl_route('StaffPermissions')],
        // Remembering a device only skips the code step, so it has no effect while codes are off.
        ['t' => 'Remember trusted devices for ' . $devDays . ' days', 'd' => $otpOn ? 'Ticked at the code step · forget them per person below' : 'Not used while sign-in codes are off · forget devices per person below', 'on' => $otpOn, 'href' => '#sc-devices'],
        ['t' => 'Block repeated wrong passwords', 'd' => User::LOGIN_MAX_PER_USER_IP . ' per name and address in ' . User::LOGIN_WINDOW_MIN . ' min (lib/User.php)', 'on' => true, 'open' => 'sc-d-ratelimit'],
        ['t' => 'Fingerprint and face sign-in (passkeys)', 'd' => 'Settings › Login page · ' . $pkTotal . ' registered', 'on' => $pkOn, 'href' => sl_route('LoginSettings')],
        ['t' => 'Hide PHP errors from visitors', 'd' => 'config.php CDP_DISPLAY_ERRORS', 'on' => !$displayErr, 'open' => 'sc-d-display'],
    ];
    foreach ($d['policy'] as $i => $p) $d['policy'][$i]['needs'] = isset($p['href']) ? sl_secNeeds($p['href']) : '';
    // Sign-in rate limiting explanation (opened from the policy list and the coverage list)
    $attempts15 = sl_tableExists('cdb_login_attempts') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_login_attempts WHERE created_at >= :c', [':c' => sl_secCut(User::LOGIN_WINDOW_MIN * 60)]) : null;
    $d['ratelimit'] = [
        'k' => 'ratelimit', 't' => 'Sign-in rate limiting', 'icon' => 'i-ban', 'd' => '', 's' => 'Built in · web and API sign-in',
        'what' => 'lib/User.php counts wrong passwords in cdb_login_attempts and refuses further tries for ' . User::LOGIN_WINDOW_MIN . ' minutes. The same limit protects the API sign-in (api/v1). One-time codes have their own limits.',
        'why' => 'It stops password guessing: a script can only try a handful of passwords before it is shut out, and a person who only knows a username cannot lock the real user out from another address.',
        'fix' => 'Nothing to switch on. To block an address for good, use the firewall or the web server. A person who is shut out can wait ' . User::LOGIN_WINDOW_MIN . ' minutes or use Forgot password.',
        'facts' => [['Same username from the same address', User::LOGIN_MAX_PER_USER_IP . ' failures in ' . User::LOGIN_WINDOW_MIN . ' min'], ['Any username from one address', User::LOGIN_MAX_PER_IP . ' failures in ' . User::LOGIN_WINDOW_MIN . ' min'], ['One username from all addresses', User::LOGIN_MAX_PER_USERNAME . ' failures in ' . User::LOGIN_WINDOW_MIN . ' min'], ['One-time codes', '1 a minute, 5 per 30 min, 5 tries each'], ['Failures counted right now', $attempts15 === null ? 'table not created yet' : sl_num($attempts15)], ['Blocked right now', $throttled ? implode(', ', array_slice($throttled, 0, 4)) : 'nobody']],
        'tables' => [], 'links' => [['Activity logs: failed sign-ins', $failHref, 'i-activity', sl_secNeeds($failHref)]], 'note' => '', 'jump' => '', 'wide' => false,
    ];

    // ── Coverage: security features and where they live ─────────────────────
    $cov = [
        ['One-time code sign-in', 'Company and sign-up settings', 'page', $otpSettings, $otpOn ? 'On' : 'Off'],
        ['Fingerprint sign-in (passkeys)', $pkTotal . ' registered by ' . $pkUsers . ' ' . ($pkUsers === 1 ? 'person' : 'people') . ' · Login page settings', 'page', sl_route('LoginSettings'), $pkOn ? 'Available' : 'Switched off'],
        ['Sign-in rate limiting', 'Web and API sign-in · lib/User.php', 'dialog', 'sc-d-ratelimit', 'Built in'],
        ['Roles and fine permissions', 'Staff permissions', 'page', sl_route('StaffPermissions')],
        ['Audit log', 'Activity logs', 'page', $logs],
        ['API authentication', 'API access', 'page', sl_route('ApiAccess')],
        ['API traffic', 'Live API traffic', 'page', sl_route('ApiTraffic')],
        ['Payments via providers', 'API connections', 'page', sl_route('ApiConnections')],
        ['Backups', 'Backup tool', 'page', 'backup.php'],
        ['Monitoring and logs', 'Developer logs', 'page', sl_route('DevLogs')],
        ['Alerts', 'Developer alerts', 'page', sl_route('DevAlerts')],
        ['Device and login history', 'Devices and log-ins below', 'here', '#sc-devices'],
        ['Security headers', 'Security checks above', 'dialog', 'sc-d-headers', 'Here'],
        ['Staff accounts', 'Staff and roles', 'page', sl_route('StaffRoles')],
        ['Releases', 'Deployments', 'page', sl_route('DevDeployments')],
        ['Safe file uploads', 'File type read from the contents', 'dialog', 'sc-d-uploads', 'Built in'],
        ['Malware scan of uploads', 'Uploads are not virus-scanned', 'none', 'sc-d-malware', 'Not built'],
    ];
    $d['cov'] = [];
    foreach ($cov as $i => $c) {
        $kind = $c[2];
        $group = $kind === 'none' ? 'off' : (in_array($kind, ['here', 'dialog'], true) ? 'here' : 'other');
        // The pill is the feature's state, only when it has one (On / Off / Available / Built in / Not built);
        // plain pages get a neutral "Open" arrow. A page the viewer cannot open is shown as text with the reason.
        $st = (string) ($c[4] ?? ($kind === 'here' ? 'Here' : ''));
        $pc = in_array($st, ['On', 'Available', 'Built in'], true) ? 'delivered' : ($st === 'Here' ? 'onway' : 'plain');
        $needs = $kind === 'page' ? sl_secNeeds((string) $c[3]) : '';
        $d['cov'][] = ['n' => $i + 1, 't' => $c[0], 'where' => $c[1], 'kind' => $needs !== '' ? 'locked' : $kind, 'target' => $c[3], 'group' => $group, 'st' => $st, 'pc' => $pc, 'needs' => $needs];
    }
    $cnt = array_count_values(array_column($d['cov'], 'group'));
    $d['cf'] = [['f' => 'all', 'label' => 'All', 'n' => count($d['cov'])], ['f' => 'here', 'label' => 'On this page', 'n' => $cnt['here'] ?? 0], ['f' => 'other', 'label' => 'Other pages', 'n' => $cnt['other'] ?? 0], ['f' => 'off', 'label' => 'Not built', 'n' => $cnt['off'] ?? 0]];
    // Explanations behind the coverage items that have no page of their own.
    $d['info'] = [
        ['k' => 'uploads', 't' => 'Safe file uploads', 'icon' => 'i-upload', 's' => 'Built in · helpers/uploads.php',
            'what' => 'Uploads (ID photos, delivery photos, shipment documents) go through cdp_uploadStore(): the file type is read from the file\'s contents, never from its name, and the file is saved under a random name with the extension of the detected type.',
            'why' => 'A file pretending to be a picture (for example a PHP or HTML file renamed to .jpg) is refused, so an upload can never run as code on the server or as a page in the browser.',
            'fix' => 'Nothing to switch on. Allowed: images and PDF (plus Word, Excel, CSV and text for back-office attachments).'],
        ['k' => 'malware', 't' => 'Malware scan of uploads', 'icon' => 'i-bug', 's' => 'Not built',
            'what' => 'The app does not send uploaded files to a virus scanner (such as ClamAV).',
            'why' => 'An infected PDF or office file can still harm the computer of the staff member who opens it. Only file types that cannot run on the server are accepted (see Safe file uploads), so the server itself is not at risk from them.',
            'fix' => 'Keep antivirus on staff computers. If you need server-side scanning, install ClamAV on the server and ask a developer to call it from helpers/uploads.php before a file is stored.'],
    ];
    return $d;
}

/**
 * The "Devices and log-ins" list: every active staff account (userlevel <> 1) with remembered
 * devices, passkeys, last sign-in (time, address, browser), failed sign-ins in 30 days and a status.
 */
function sl_securityPeople(bool $canAct, bool $isSuper, bool $hasLog, bool $hasDev, bool $hasPk, bool $hasPwDate, bool $hasFr, string $now, string $c30, string $c180): array
{
    $rows = sl_rows('SELECT u.id, u.fname, u.lname, u.username, u.email, u.userlevel, u.lastlogin, u.lastip, r.role_name'
        . ($hasPwDate ? ', u.password_changed_at AS pc' : ', NULL AS pc') . ($hasFr ? ', u.force_password_reset AS fr' : ', 0 AS fr')
        . ($hasDev ? ', (SELECT COUNT(*) FROM cdb_auth_trusted_devices t WHERE t.user_id = u.id AND t.revoked_at IS NULL AND t.expires_at > :now) AS devs' : ', 0 AS devs')
        . ' FROM cdb_users u LEFT JOIN cdb_user_roles r ON r.role_id = u.userlevel WHERE u.userlevel <> 1 AND u.active = 1 ORDER BY u.lastlogin IS NULL, u.lastlogin DESC, u.fname', $hasDev ? [':now' => $now] : []);
    $ids = array_map(fn($r) => (int) $r->id, $rows);
    $pk = [];
    if ($hasPk) {
        foreach (sl_rows('SELECT user_id, COUNT(*) AS n FROM cdb_passkeys GROUP BY user_id') as $r) $pk[(int) $r->user_id] = (int) $r->n;
    }
    $last = [];
    $failRows = [];
    if ($hasLog && $ids) {
        $in = implode(',', $ids); // integers from the database
        foreach (sl_rows("SELECT l.id, l.user_id, l.ip, l.user_agent, l.created_at FROM cdb_activity_log l JOIN (SELECT user_id, MAX(created_at) AS at FROM cdb_activity_log WHERE module = 'auth' AND verb = 'login' AND outcome = 'success' AND user_id IN ($in) GROUP BY user_id) m ON m.user_id = l.user_id AND m.at = l.created_at WHERE l.module = 'auth' AND l.verb = 'login' AND l.outcome = 'success' ORDER BY l.id") as $r) {
            $last[(int) $r->user_id] = $r;
        }
        foreach (sl_rows("SELECT COALESCE(user_id, 0) AS uid, LOWER(" . sl_secTriedSql() . ") AS who, COUNT(*) AS n FROM cdb_activity_log WHERE module = 'auth' AND verb = 'login' AND outcome IN ('failure','denied') AND created_at >= :c GROUP BY uid, who", [':c' => $c30]) as $r) {
            $failRows[] = $r;
        }
    }
    $today = date('Y-m-d');
    $people = [];
    $sum = ['staff' => count($rows), 'devices' => 0, 'today' => 0, 'reset' => 0, 'withDev' => 0, 'idle' => 0];
    foreach ($rows as $u) {
        $id = (int) $u->id;
        $name = trim($u->fname . ' ' . $u->lname) ?: $u->username;
        $f30 = 0;
        foreach ($failRows as $r) {
            if ((int) $r->uid === $id || ($r->who !== '' && ($r->who === strtolower((string) $u->username) || ($u->email && $r->who === strtolower((string) $u->email))))) $f30 += (int) $r->n;
        }
        $l = $last[$id] ?? null;
        // cdb_users.lastlogin can be newer than the logged sign-in (e.g. a session opened another way):
        // then the browser of that sign-in is unknown and only the address of cdb_users is shown.
        if ($l && $u->lastlogin && strtotime((string) $u->lastlogin) > strtotime((string) $l->created_at) + 120) $l = null;
        $at = $l ? $l->created_at : $u->lastlogin;
        $ip = $l ? (string) $l->ip : (string) $u->lastip;
        $idle = !$at || strtotime((string) $at) < strtotime($c30);
        $fr = (int) $u->fr === 1;
        $pwOld = $hasPwDate && (!$u->pc || strtotime((string) $u->pc) < strtotime($c180));
        $st = $fr ? ['Must reset password', 'pending'] : ($f30 >= 5 ? [$f30 . ' failed sign-ins', 'onway'] : ($pwOld ? ['Old password', 'onway'] : ($idle ? ['Not signed in 30 d', 'plain'] : ['OK', 'delivered'])));
        $tags = [];
        if ((int) $u->devs) { $tags[] = 'dev'; $sum['withDev']++; }
        if ($fr) { $tags[] = 'reset'; $sum['reset']++; }
        if ($idle) { $tags[] = 'idle'; $sum['idle']++; }
        if ($at && substr((string) $at, 0, 10) === $today) $sum['today']++;
        $sum['devices'] += (int) $u->devs;
        $people[] = ['id' => $id, 'name' => $name, 'ini' => sl_initials($name), 'user' => (string) $u->username, 'role' => (string) ($u->role_name ?: 'Role ' . (int) $u->userlevel), 'super' => (int) $u->userlevel === 9,
            'devs' => (int) $u->devs, 'pk' => $pk[$id] ?? 0, 'at' => $at ? sl_ago($at) : 'Never', 'atFull' => $at ? sl_secDate($at) : '', 'ip' => $ip, 'dev' => $l ? sl_secDevice((string) $l->user_agent) : '',
            'f30' => $f30, 'st' => $st[0], 'pc' => $st[1], 'tags' => implode(' ', $tags), 'q' => strtolower($name . ' ' . $u->username . ' ' . ($u->email ?? '') . ' ' . ($u->role_name ?? ''))];
    }
    return ['people' => $people, 'peopleSum' => $sum, 'pageSize' => 25, 'pkOn' => $hasPk];
}

/**
 * Everything about one staff account for the detail pop-up: remembered devices (with a readable
 * label; rows without a stored browser fall back to the nearest sign-in), passkeys, the last 15
 * sign-ins and which actions the viewer may take. Null when the id is not a staff account.
 */
function sl_securityUserDetail(int $id, bool $canAct, bool $viewerSuper): ?array
{
    $hasFr = sl_columnExists('cdb_users', 'force_password_reset');
    $hasPwDate = sl_columnExists('cdb_users', 'password_changed_at');
    $u = sl_row('SELECT u.id, u.fname, u.lname, u.username, u.email, u.userlevel, u.active, u.lastlogin, u.lastip, r.role_name'
        . ($hasPwDate ? ', u.password_changed_at AS pc' : ', NULL AS pc') . ($hasFr ? ', u.force_password_reset AS fr' : ', 0 AS fr')
        . ' FROM cdb_users u LEFT JOIN cdb_user_roles r ON r.role_id = u.userlevel WHERE u.id = :id AND u.userlevel <> 1', [':id' => $id]);
    if (!$u) return null;
    $name = trim($u->fname . ' ' . $u->lname) ?: $u->username;
    $mayLogs = sl_secNeeds(sl_route('ActivityLogs')) === '';
    $super = (int) $u->userlevel === 9;
    $mayAct = $canAct && (!$super || $viewerSuper);
    $hasLog = sl_tableExists('cdb_activity_log');
    $now = date('Y-m-d H:i:s');
    $cookieSel = '';
    if (!empty($_COOKIE['trusted_device']) && strpos((string) $_COOKIE['trusted_device'], ':') !== false) {
        $cookieSel = explode(':', (string) $_COOKIE['trusted_device'])[0];
    }
    $devices = [];
    if (sl_tableExists('cdb_auth_trusted_devices')) {
        $hasDetail = sl_columnExists('cdb_auth_trusted_devices', 'ip') && sl_columnExists('cdb_auth_trusted_devices', 'user_agent');
        foreach (sl_rows('SELECT id, selector, created_at, last_used_at, expires_at' . ($hasDetail ? ', ip, user_agent' : ', NULL AS ip, NULL AS user_agent') . ' FROM cdb_auth_trusted_devices WHERE user_id = :u AND revoked_at IS NULL AND expires_at > :now ORDER BY COALESCE(last_used_at, created_at) DESC', [':u' => $id, ':now' => $now]) as $t) {
            $ua = (string) $t->user_agent;
            $ip = (string) $t->ip;
            $guess = false;
            $ipGuess = false;
            if ($ua === '' && $hasLog) {
                // No browser stored with the device (remembered before the columns existed): use the
                // successful sign-in nearest to when it was remembered, else nearest to when it was last used.
                foreach (array_filter([(string) $t->created_at, (string) $t->last_used_at]) as $at) {
                    $ts = strtotime($at);
                    $near = sl_row("SELECT ip, user_agent FROM cdb_activity_log WHERE user_id = :u AND module = 'auth' AND verb = 'login' AND outcome = 'success' AND user_agent IS NOT NULL AND user_agent <> '' AND created_at BETWEEN :a AND :b ORDER BY ABS(TIMESTAMPDIFF(SECOND, created_at, :t)) LIMIT 1", [':u' => $id, ':a' => date('Y-m-d H:i:s', $ts - 86400), ':b' => date('Y-m-d H:i:s', $ts + 86400), ':t' => $at]);
                    if ($near) {
                        $ua = (string) $near->user_agent;
                        if ($ip === '' && (string) $near->ip !== '') { $ip = (string) $near->ip; $ipGuess = true; }
                        $guess = true;
                        break;
                    }
                }
            }
            $devices[] = ['id' => (int) $t->id, 'label' => sl_secDevice($ua) ?: 'Unknown device', 'icon' => $ua !== '' ? sl_secDeviceIcon($ua) : 'i-monitor', 'guess' => $guess, 'ip' => $ip, 'ipGuess' => $ipGuess,
                'first' => sl_secDate($t->created_at, 'M j, Y'), 'used' => $t->last_used_at ? sl_ago($t->last_used_at) : 'not yet', 'usedAt' => sl_secDate($t->last_used_at), 'expires' => sl_secDate($t->expires_at, 'M j, Y'),
                'current' => $cookieSel !== '' && hash_equals((string) $t->selector, $cookieSel) && $id === (int) ($_SESSION['userid'] ?? 0)];
        }
    }
    $passkeys = null;
    if (sl_tableExists('cdb_passkeys')) {
        $passkeys = array_map(fn($p) => ['label' => (string) ($p->label ?: 'Passkey'), 'added' => sl_secDate($p->created_at, 'M j, Y'), 'used' => $p->last_used_at ? sl_ago($p->last_used_at) : 'never'], sl_rows('SELECT label, created_at, last_used_at FROM cdb_passkeys WHERE user_id = :u ORDER BY COALESCE(last_used_at, created_at) DESC', [':u' => $id]));
    }
    $logins = [];
    $lastLog = null;
    $f30 = 0;
    if ($hasLog) {
        $names = array_values(array_filter([strtolower((string) $u->username), strtolower((string) $u->email)]));
        $bind = [':u' => $id, ':n0' => $names[0] ?? '-', ':n1' => $names[1] ?? ($names[0] ?? '-')];
        $tried = 'LOWER(' . sl_secTriedSql() . ')';
        $who = "(user_id = :u OR (COALESCE(user_id, 0) = 0 AND $tried IN (:n0, :n1)))";
        foreach (sl_rows("SELECT outcome, ip, user_agent, created_at, summary FROM cdb_activity_log WHERE module = 'auth' AND verb = 'login' AND $who ORDER BY created_at DESC, id DESC LIMIT 15", $bind) as $r) {
            $ok = $r->outcome === 'success';
            if ($ok && !$lastLog) $lastLog = $r;
            $logins[] = ['ok' => $ok, 'what' => $ok ? (stripos((string) $r->summary, 'code sent') !== false ? 'Password accepted, code sent' : 'Signed in') : sl_secReason((string) $r->summary), 'ip' => (string) $r->ip, 'dev' => sl_secDevice((string) $r->user_agent) ?: 'Unknown device', 'at' => sl_secDate($r->created_at), 'ago' => sl_ago($r->created_at)];
        }
        $f30 = (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE module = 'auth' AND verb = 'login' AND outcome IN ('failure','denied') AND created_at >= :c AND $who", $bind + [':c' => sl_secCut(30 * 86400)]);
    }
    $pwOld = $hasPwDate && (!$u->pc || strtotime((string) $u->pc) < time() - 180 * 86400);
    // Last sign-in: cdb_users.lastlogin, or the newest logged sign-in when that is newer (or lastlogin is empty).
    $lastAt = $u->lastlogin;
    $lastIp = (string) $u->lastip;
    if ($lastLog && (!$lastAt || strtotime((string) $lastLog->created_at) > strtotime((string) $lastAt) + 120)) {
        $lastAt = $lastLog->created_at;
        $lastIp = (string) $lastLog->ip;
    }
    return [
        'user' => ['id' => (int) $u->id, 'name' => $name, 'ini' => sl_initials($name), 'username' => (string) $u->username, 'email' => (string) $u->email, 'role' => (string) ($u->role_name ?: 'Role ' . (int) $u->userlevel), 'super' => $super,
            'last' => $lastAt ? sl_ago($lastAt) : 'never', 'lastAt' => sl_secDate($lastAt), 'lastIp' => $lastIp, 'fr' => (int) $u->fr === 1, 'frAvailable' => $hasFr,
            'pw' => $hasPwDate ? ($u->pc ? sl_date($u->pc) : 'Not recorded') : 'Not recorded', 'pwOld' => $pwOld, 'f30' => $f30,
            'edit' => sl_secNeeds('users_edit.php?user=' . (int) $u->id, (int) $u->userlevel) === '' ? 'users_edit.php?user=' . (int) $u->id : '', 'logs' => $mayLogs ? sl_route('ActivityLogs', ['user_id' => (int) $u->id]) : '', 'signins' => $mayLogs ? sl_route('ActivityLogs', ['user_id' => (int) $u->id, 'verb' => 'login']) : ''],
        'can' => ['act' => $mayAct, 'why' => $canAct && !$mayAct ? 'Only a super admin can change another super admin.' : ($canAct ? '' : 'Changing devices and passwords needs the Edit staff permission.')],
        'devices' => $devices, 'passkeys' => $passkeys, 'logins' => $logins,
    ];
}
