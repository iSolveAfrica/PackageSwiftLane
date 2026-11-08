<?php
/**
 * Data for SecurityCenter.dc.html. Every check is computed from this installation:
 * cdb_activity_log (sign-ins), cdb_settings.active_otp + cdb_auth_otp_challenges,
 * cdb_auth_trusted_devices, cdb_api_tokens / cdb_api_user_tokens, RBAC tables,
 * backups/ (helpers/backups_function.php), PHP / MySQL versions, HTTPS and the
 * response headers loader.php sends, cron and queue state (helpers/dev_console.php).
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/dev_console.php';

function sl_securityData($user, $core): array
{
    $d = ['flash' => (string) ($_GET['flash'] ?? ''), 'canAct' => $user->cdp_hasPermission('edit_user'), 'ran' => date('H:i')];
    $hasLog = sl_tableExists('cdb_activity_log');
    $fail24 = $hasLog ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE verb = 'login' AND outcome IN ('failure','denied') AND created_at >= NOW() - INTERVAL 1 DAY") : 0;
    $ok24 = $hasLog ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE verb = 'login' AND outcome = 'success' AND created_at >= NOW() - INTERVAL 1 DAY") : 0;
    $denied24 = $hasLog ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE outcome = 'denied' AND created_at >= NOW() - INTERVAL 1 DAY") : 0;
    $burst = $hasLog ? sl_rows("SELECT ip, COUNT(*) AS n, MAX(created_at) AS last FROM cdb_activity_log WHERE verb = 'login' AND outcome IN ('failure','denied') AND created_at >= NOW() - INTERVAL 7 DAY GROUP BY ip ORDER BY n DESC LIMIT 5") : [];
    $otpOn = !empty($core->active_otp);
    $otp = sl_tableExists('cdb_auth_otp_challenges') ? sl_row("SELECT COUNT(*) AS n, SUM(status = 'verified') AS ok, SUM(status IN ('failed','locked','expired')) AS bad FROM cdb_auth_otp_challenges WHERE created_at >= NOW() - INTERVAL 7 DAY") : null;
    $staff = (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel <> 1 AND active = 1');
    $dev = sl_tableExists('cdb_auth_trusted_devices') ? sl_row('SELECT COUNT(*) AS n, COUNT(DISTINCT user_id) AS u FROM cdb_auth_trusted_devices WHERE revoked_at IS NULL AND expires_at > NOW()') : null;
    $tok = ['n' => 0, 'stale' => 0];
    foreach (['cdb_api_tokens', 'cdb_api_user_tokens'] as $t) {
        if (sl_tableExists($t)) {
            $tok['n'] += (int) sl_scalar("SELECT COUNT(*) FROM $t WHERE revoked_at IS NULL AND expires_at > NOW()");
            $tok['stale'] += (int) sl_scalar("SELECT COUNT(*) FROM $t WHERE revoked_at IS NULL AND expires_at > NOW() AND COALESCE(last_used_at, created_at) < NOW() - INTERVAL 90 DAY");
        }
    }
    $roles = (int) sl_scalar('SELECT COUNT(*) FROM cdb_user_roles');
    $actions = (int) sl_scalar('SELECT COUNT(*) FROM cdb_user_module_actions');
    $grants = (int) sl_scalar('SELECT COUNT(*) FROM cdb_user_role_permissions WHERE permitted = 1');
    $supers = (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel = 9 AND active = 1');
    $rbac = defined('CDP_RBAC_MODE') ? (string) CDP_RBAC_MODE : 'audit';
    $b = sl_devBackup();
    $bAge = $b['latest_at'] ? (time() - $b['latest_at']) / 86400 : null;
    $my = sl_devMysql();
    $https = sl_devHttps();
    $headers = sl_devHeaders();
    $hdrOk = count(array_filter($headers, fn($h) => $h['set']));
    $missingHdr = array_map(fn($h) => $h['name'], array_filter($headers, fn($h) => !$h['set']));
    $jobs = sl_devCronJobs();
    $cronFails = array_sum(array_column($jobs, 'fails24'));
    $cronRuns = array_sum(array_column($jobs, 'runs24'));
    $q = sl_devQueue();
    $disk = sl_devDisk();
    $pwOld = sl_columnExists('cdb_users', 'password_changed_at') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel <> 1 AND active = 1 AND (password_changed_at IS NULL OR password_changed_at < NOW() - INTERVAL 180 DAY)') : null;
    $forced = sl_columnExists('cdb_users', 'force_password_reset') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE force_password_reset = 1') : null;
    $errLog = sl_devErrorLogPath();
    $displayErr = (bool) ini_get('display_errors');
    $demo = defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true;
    $cors = defined('CDP_API_ALLOWED_ORIGINS') ? (string) CDP_API_ALLOWED_ORIGINS : '';
    $chk = [];
    $add = function (string $t, string $s, bool $ok, bool $bad = false) use (&$chk) {
        $chk[] = ['t' => $t, 's' => $s, 'd' => $ok ? '' : ($bad ? 'e' : 'w')];
    };
    $add('One-time code on sign-in', $otpOn ? 'On' . ($otp && (int) $otp->n ? ' · ' . (int) $otp->ok . ' of ' . (int) $otp->n . ' codes verified in 7 days' : ' · no codes sent in 7 days') : 'Off in Settings: staff sign in with a password only', $otpOn);
    $add('HTTPS', $https['request'] ? 'This request came over HTTPS' : ('This request is plain HTTP' . ($https['configured'] ? ' · site URL is https' : ' · site URL ' . ($https['url'] ?: 'not set'))), $https['request'] || $https['configured'], !$https['configured']);
    $add('Security headers', $hdrOk . ' of ' . count($headers) . ' set' . ($missingHdr ? ' · missing: ' . implode(', ', array_slice($missingHdr, 0, 3)) : ''), !$missingHdr);
    $add('Failed sign-ins (24 h)', $fail24 . ' failed · ' . $ok24 . ' succeeded' . ($burst ? ' · most from ' . ($burst[0]->ip ?: 'unknown') . ' (' . (int) $burst[0]->n . ' in 7 d)' : ''), $fail24 <= 20);
    $add('Permission denials (24 h)', $denied24 . ' blocked actions logged', $denied24 <= 50);
    $add('Roles and permissions', $roles . ' roles · ' . $actions . ' actions · ' . $grants . ' grants · enforcement ' . $rbac, $rbac === 'enforce');
    $add('Super admins', $supers . ' active', $supers > 0 && $supers <= 3);
    $add('API tokens', $tok['n'] . ' active · ' . $tok['stale'] . ' unused over 90 days', $tok['stale'] === 0);
    $add('CORS for the API', $cors === '' ? 'Not set' : ($cors === '*' ? 'Any origin (*)' : $cors), $cors !== '*');
    $add('Trusted devices', $dev ? (int) $dev->n . ' remembered devices for ' . (int) $dev->u . ' users' : 'Table missing', (bool) $dev);
    $add('Staff passwords', $pwOld === null ? 'Change dates not recorded' : $pwOld . ' of ' . $staff . ' staff not changed in 180 days' . ($forced ? ' · ' . $forced . ' must reset' : ''), $pwOld === 0);
    $add('Database backups', $b['latest'] ? 'Last ' . sl_date($b['latest_at'], 'M j H:i') . ' · ' . sl_devBytes($b['latest_size']) . ' · ' . $b['count'] . ' files' : 'No backup file in backups/', $bAge !== null && $bAge <= 7, $bAge === null);
    $add('PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.2', '>=') ? 'Supported version' : 'Upgrade to 8.2 or newer', version_compare(PHP_VERSION, '8.2', '>='));
    $add('MySQL ' . ($my['version'] ?: '?'), $my['ok'] ? $my['threads'] . ' / ' . $my['max_conn'] . ' connections' : 'Not answering', $my['ok'], !$my['ok']);
    $add('Errors hidden from visitors', $displayErr ? 'display_errors is ON: errors print in pages' : 'display_errors off', !$displayErr);
    $add('Error log', $errLog !== '' ? ($errLog . (is_writable($errLog) || (!file_exists($errLog) && is_writable(dirname($errLog))) ? ' · writable' : ' · not writable')) : 'CDP_ERROR_LOG not set', $errLog !== '');
    $add('Demo mode', $demo ? 'ON: writes are refused' : 'Off', !$demo);
    $add('Cron jobs', $cronRuns ? $cronRuns . ' runs in 24 h · ' . $cronFails . ' failed' : 'No runs recorded in 24 h (cdb_cron_runs)', $cronRuns > 0 && $cronFails === 0, $cronFails > 0);
    $add('Notification queue', $q['exists'] ? $q['queued'] . ' waiting · ' . $q['dead'] . ' dead' : 'Outbox not created yet', !$q['exists'] || ($q['dead'] === 0 && $q['queued'] < 200));
    $add('Disk', $disk['pct'] . '% used · ' . sl_devBytes($disk['free']) . ' free', $disk['pct'] < 85, $disk['pct'] >= 95);
    $d['chk'] = $chk;
    $warn = count(array_filter($chk, fn($c) => $c['d'] !== ''));
    $d['passN'] = count($chk) - $warn;
    $d['warnN'] = $warn;
    $spark = function (string $sql): array {
        $map = [];
        foreach (sl_rows($sql) as $r) $map[$r->d] = (int) $r->n;
        $v = [];
        for ($i = 11; $i >= 0; $i--) $v[] = $map[date('Y-m-d', strtotime("-$i days"))] ?? 0;
        $m = max(1, max($v));
        return sl_sparkBars($v, 6);
    };
    $d['kpis'] = [
        ['icon' => 'i-shield', 'l' => 'Checks passing', 'v' => $d['passN'] . ' / ' . count($chk), 's' => 'Computed on page load', 'sp' => []],
        ['icon' => 'i-x', 'l' => 'Failed sign-ins 24 h', 'v' => sl_num($fail24), 's' => $denied24 . ' permission denials', 'sp' => $hasLog ? $spark("SELECT DATE(created_at) AS d, COUNT(*) AS n FROM cdb_activity_log WHERE verb = 'login' AND outcome IN ('failure','denied') AND created_at >= CURDATE() - INTERVAL 11 DAY GROUP BY d") : []],
        ['icon' => 'i-key', 'l' => 'One-time codes', 'v' => $otpOn ? 'On' : 'Off', 's' => $otp ? (int) $otp->n . ' sent · ' . (int) $otp->bad . ' failed (7 d)' : '—', 'sp' => []],
        ['icon' => 'i-activity', 'l' => 'Trusted devices', 'v' => $dev ? sl_num($dev->n) : '0', 's' => $dev ? (int) $dev->u . ' users' : 'Table missing', 'sp' => []],
        ['icon' => 'i-alert', 'l' => 'Open security items', 'v' => (string) $warn, 's' => $warn ? 'See the checks below' : 'All clear', 'sp' => []],
    ];
    $d['burst'] = array_map(fn($r) => ['ip' => $r->ip ?: 'unknown', 'n' => (int) $r->n, 'last' => sl_ago($r->last)], $burst);
    // devices and log-ins per user
    $d['users'] = [];
    if (sl_tableExists('cdb_auth_trusted_devices')) {
        foreach (sl_rows("SELECT u.id, CONCAT(u.fname,' ',u.lname) AS name, u.username, u.lastlogin, u.lastip, COUNT(t.id) AS n, MAX(t.last_used_at) AS used" . (sl_columnExists('cdb_users', 'force_password_reset') ? ', u.force_password_reset AS fr' : ', 0 AS fr') . " FROM cdb_users u LEFT JOIN cdb_auth_trusted_devices t ON t.user_id = u.id AND t.revoked_at IS NULL AND t.expires_at > NOW() WHERE u.userlevel <> 1 AND u.active = 1 GROUP BY u.id ORDER BY n DESC, u.lastlogin DESC LIMIT 12") as $u) {
            $d['users'][] = ['id' => (int) $u->id, 't' => trim((string) $u->name) ?: $u->username, 's' => (int) $u->n . ' trusted devices · last sign-in ' . ($u->lastlogin ? sl_ago($u->lastlogin) : 'never') . ($u->lastip ? ' from ' . $u->lastip : '') . ((int) $u->fr ? ' · must reset password' : ''), 'n' => (int) $u->n, 'fr' => (int) $u->fr, 'icon' => (int) $u->n ? 'i-monitor' : 'i-user'];
        }
    }
    $d['recent'] = $hasLog ? array_map(fn($r) => ['t' => ($r->actor_name ?: $r->actor_username ?: 'unknown') . ' · ' . ($r->outcome === 'success' ? 'signed in' : 'failed'), 's' => ($r->ip ?: '') . ' · ' . sl_ago($r->created_at) . ($r->summary ? ' · ' . $r->summary : ''), 'bad' => $r->outcome !== 'success'], sl_rows("SELECT actor_name, actor_username, outcome, ip, created_at, summary FROM cdb_activity_log WHERE verb = 'login' ORDER BY id DESC LIMIT 8")) : [];
    $d['policy'] = [
        ['t' => 'Require a code on staff sign-in', 'd' => 'Settings › General (cdb_settings.active_otp)', 'on' => $otpOn, 'href' => sl_route('SettingsHub')],
        ['t' => 'Enforce role permissions', 'd' => 'config.php CDP_RBAC_MODE = ' . $rbac, 'on' => $rbac === 'enforce', 'href' => sl_route('StaffPermissions')],
        ['t' => 'Remember trusted devices for 60 days', 'd' => 'lib/OtpService.php · revoke per user below', 'on' => true, 'href' => '#sc-devices'],
        ['t' => 'Hide PHP errors from visitors', 'd' => 'config.php CDP_DISPLAY_ERRORS', 'on' => !$displayErr, 'href' => sl_route('DevInfrastructure')],
    ];
    $cov = [['One-time code sign-in', 'Login OTP', 'LoginOtp'], ['Roles and fine permissions', 'Staff permissions', 'StaffPermissions'], ['Audit log', 'Activity logs', 'ActivityLogs'], ['API authentication', 'API access', 'ApiAccess'], ['API traffic', 'Live API traffic', 'ApiTraffic'], ['Payments via providers', 'API connections', 'ApiConnections'], ['Backups', 'Backup tool', ''], ['Monitoring and logs', 'Developer logs', 'DevLogs'], ['Alerts', 'Developer alerts', 'DevAlerts'], ['Device and login history', 'Devices below', '#'], ['Staff accounts', 'Staff and roles', 'StaffRoles'], ['Releases', 'Deployments', 'DevDeployments'], ['Passkeys', 'Not built', '-'], ['Malware scan of uploads', 'Not built', '-'], ['Rate limiting', 'Not built in the app (use the web server / WAF)', '-']];
    $cf = (string) ($_GET['cf'] ?? 'all');
    $d['cf'] = array_map(fn($f) => ['label' => $f[1], 'cls' => $cf === $f[0] ? 'on' : '', 'href' => sl_url(['cf' => $f[0]])], [['all', 'All ' . count($cov)], ['here', 'On this page ' . count(array_filter($cov, fn($c) => $c[2] === '#'))], ['other', 'Other pages ' . count(array_filter($cov, fn($c) => !in_array($c[2], ['#', '-'], true)))]]);
    $d['cov'] = [];
    foreach ($cov as $i => $c) {
        if ($cf === 'here' && $c[2] !== '#') continue;
        if ($cf === 'other' && in_array($c[2], ['#', '-'], true)) continue;
        $d['cov'][] = ['n' => $i + 1, 't' => $c[0], 'where' => $c[1], 'href' => $c[2] === '#' ? '#sc-devices' : ($c[2] === '-' ? '#' : ($c[2] === '' ? 'backup.php' : sl_route($c[2]))), 'st' => $c[2] === '#' ? 'Here' : ($c[2] === '-' ? 'Not built' : 'Open'), 'pc' => $c[2] === '#' ? 'onway' : ($c[2] === '-' ? 'plain' : 'delivered'), 'off' => $c[2] === '-' ? 'off' : ''];
    }
    return $d;
}
