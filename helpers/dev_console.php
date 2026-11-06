<?php
/**
 * Developer console helpers: environment facts, service health checks, log
 * readers, metrics and alert-rule evaluation. Used by views/sl/data/dev.php,
 * the Security Centre and cron/dev_alerts.php. Everything here is read-only and
 * every figure comes from PHP, the database or the file system; secrets are
 * masked before they leave this file.
 */
require_once __DIR__ . '/sl_view.php';

/** Mask a secret: first 2 and last 2 characters only. */
function sl_devMask($v): string
{
    $v = (string) $v;
    if ($v === '') {
        return '(empty)';
    }
    if (strlen($v) <= 6) {
        return str_repeat('•', strlen($v));
    }
    return substr($v, 0, 2) . str_repeat('•', min(12, strlen($v) - 4)) . substr($v, -2);
}

function sl_devBytes($n): string
{
    $n = (float) $n;
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($n >= 1024 && $i < 4) {
        $n /= 1024;
        $i++;
    }
    return number_format($n, $i >= 2 ? 1 : 0) . ' ' . $u[$i];
}

function sl_devMs($ms): string
{
    $ms = (float) $ms;
    return $ms >= 1000 ? number_format($ms / 1000, 2) . ' s' : number_format($ms, 0) . ' ms';
}

/** Path of the PHP error log (config CDP_ERROR_LOG, else ini error_log). */
function sl_devErrorLogPath(): string
{
    if (defined('CDP_ERROR_LOG') && CDP_ERROR_LOG) {
        return (string) CDP_ERROR_LOG;
    }
    $ini = (string) ini_get('error_log');
    return $ini !== '' && $ini !== 'syslog' ? $ini : '';
}

/** Disk usage of the path that holds the application. */
function sl_devDisk(?string $path = null): array
{
    $path = $path ?: CDP_ROOT;
    $total = @disk_total_space($path);
    $free = @disk_free_space($path);
    if (!$total) {
        return ['total' => 0, 'free' => 0, 'used' => 0, 'pct' => 0];
    }
    $used = $total - $free;
    return ['total' => $total, 'free' => $free, 'used' => $used, 'pct' => (int) round($used / $total * 100)];
}

/** Last $n lines of a text file, newest first. Cheap on large files (reads from the end). */
function sl_devTail(string $file, int $n = 200, int $maxBytes = 1048576): array
{
    if ($file === '' || !is_readable($file)) {
        return [];
    }
    $size = filesize($file);
    $fh = fopen($file, 'r');
    if (!$fh) {
        return [];
    }
    $start = max(0, $size - $maxBytes);
    fseek($fh, $start);
    $data = stream_get_contents($fh);
    fclose($fh);
    $lines = preg_split('/\r?\n/', trim((string) $data)) ?: [];
    if ($start > 0 && $lines) {
        array_shift($lines); // partial first line
    }
    $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));
    return array_reverse(array_slice($lines, -$n));
}

/** Parse one PHP error-log line into [ts, level, text]. */
function sl_devParseLogLine(string $line): array
{
    $ts = null;
    $level = 'INFO';
    $text = $line;
    if (preg_match('/^\[([^\]]+)\]\s*(.*)$/', $line, $m)) {
        $ts = strtotime($m[1]) ?: null;
        $text = $m[2];
    }
    if (preg_match('/PHP (Fatal error|Parse error|Error)/i', $text) || stripos($text, 'exception') !== false) {
        $level = 'ERROR';
    } elseif (preg_match('/PHP (Warning|Notice|Deprecated)/i', $text)) {
        $level = 'WARN';
    }
    return ['ts' => $ts, 'level' => $level, 'text' => $text];
}

/** Number of error-log lines newer than $minutes (warnings and errors). */
function sl_devPhpErrorCount(int $minutes = 60): int
{
    $since = time() - $minutes * 60;
    $n = 0;
    foreach (sl_devTail(sl_devErrorLogPath(), 5000, 4194304) as $line) {
        $p = sl_devParseLogLine($line);
        if ($p['ts'] === null) {
            continue;
        }
        if ($p['ts'] < $since) {
            break; // newest first
        }
        if ($p['level'] !== 'INFO') {
            $n++;
        }
    }
    return $n;
}

/** MySQL server facts (version, uptime, threads, connections). */
function sl_devMysql(): array
{
    $out = ['version' => '', 'uptime' => 0, 'threads' => 0, 'max_conn' => 0, 'questions' => 0, 'slow' => 0, 'ok' => false, 'ms' => 0];
    try {
        $t = microtime(true);
        $out['version'] = (string) sl_scalar('SELECT VERSION()', [], '');
        $out['ms'] = round((microtime(true) - $t) * 1000, 1);
        $out['ok'] = $out['version'] !== '';
        $rows = sl_rows("SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime','Threads_connected','Questions','Slow_queries','Threads_running')");
        foreach ($rows as $r) {
            $k = $r->Variable_name;
            if ($k === 'Uptime') $out['uptime'] = (int) $r->Value;
            if ($k === 'Threads_connected') $out['threads'] = (int) $r->Value;
            if ($k === 'Questions') $out['questions'] = (int) $r->Value;
            if ($k === 'Slow_queries') $out['slow'] = (int) $r->Value;
        }
        $out['max_conn'] = (int) sl_scalar("SELECT @@max_connections", [], 0);
        $out['slow_log'] = (string) sl_scalar("SELECT @@slow_query_log", [], '0');
        $out['long_query'] = (float) sl_scalar("SELECT @@long_query_time", [], 10);
    } catch (Throwable $e) {
    }
    return $out;
}

/** Table sizes from information_schema, biggest first. */
function sl_devTableSizes(int $limit = 12): array
{
    return sl_rows("SELECT TABLE_NAME AS t, TABLE_ROWS AS n, DATA_LENGTH AS d, INDEX_LENGTH AS i, UPDATE_TIME AS u FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC LIMIT " . (int) $limit);
}

function sl_devDbSize(): array
{
    $r = sl_row('SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH),0) AS b, COUNT(*) AS n, COALESCE(SUM(TABLE_ROWS),0) AS r FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
    return ['bytes' => (float) ($r->b ?? 0), 'tables' => (int) ($r->n ?? 0), 'rows' => (int) ($r->r ?? 0)];
}

/** Tables with many rows and no index besides the primary key: slow-query candidates. */
function sl_devSlowCandidates(): array
{
    return sl_rows("SELECT t.TABLE_NAME AS t, t.TABLE_ROWS AS n, COUNT(DISTINCT s.INDEX_NAME) AS idx FROM information_schema.TABLES t LEFT JOIN information_schema.STATISTICS s ON s.TABLE_SCHEMA = t.TABLE_SCHEMA AND s.TABLE_NAME = t.TABLE_NAME WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_ROWS > 500 GROUP BY t.TABLE_NAME, t.TABLE_ROWS HAVING idx <= 1 ORDER BY n DESC LIMIT 8");
}

/** Cron scripts in cron/ with their last recorded run. */
function sl_devCronJobs(): array
{
    $jobs = [];
    $hasRuns = sl_tableExists('cdb_cron_runs');
    foreach (glob(CDP_ROOT . '/cron/*.php') ?: [] as $f) {
        $name = basename($f, '.php');
        $head = (string) @file_get_contents($f, false, null, 0, 2500);
        $what = '';
        if (preg_match('/^\s*\*\s+(?!cron\/)([A-Z][^\n]{10,120})$/m', $head, $m)) {
            $what = trim($m[1]);
        }
        if (preg_match('/^\s*(?:\*|\/\/)\s*([A-Za-z][^\n*]{10,110})\*?\s*$/m', $head, $m) && $what === '') {
            $what = trim($m[1], " *");
        }
        $sched = '';
        if (preg_match('/^\s*(?:\*|\/\/|#)\s*((?:\*|[0-9\/,-]+)\s+(?:\*|[0-9\/,-]+)\s+(?:\*|[0-9\/,-]+)\s+(?:\*|[0-9\/,-]+)\s+(?:\*|[0-9\/,-]+))\s+php/m', $head, $m)) {
            $sched = $m[1];
        }
        $last = null;
        $fails = 0;
        $runs = 0;
        if ($hasRuns) {
            $last = sl_row('SELECT started_at, finished_at, status, duration_ms, message FROM cdb_cron_runs WHERE job = :j ORDER BY id DESC', [':j' => $name]);
            $fails = (int) sl_scalar("SELECT COUNT(*) FROM cdb_cron_runs WHERE job = :j AND status = 'failed' AND started_at >= NOW() - INTERVAL 1 DAY", [':j' => $name]);
            $runs = (int) sl_scalar("SELECT COUNT(*) FROM cdb_cron_runs WHERE job = :j AND started_at >= NOW() - INTERVAL 1 DAY", [':j' => $name]);
        }
        $jobs[] = ['name' => $name, 'file' => 'cron/' . $name . '.php', 'what' => $what, 'schedule' => $sched, 'last' => $last, 'fails24' => $fails, 'runs24' => $runs];
    }
    return $jobs;
}

/** Notification outbox (cdb_cs_outbox) counts. */
function sl_devQueue(): array
{
    if (!sl_tableExists('cdb_cs_outbox')) {
        return ['exists' => false, 'queued' => 0, 'retry' => 0, 'dead' => 0, 'sent24' => 0, 'oldest_min' => 0];
    }
    $r = sl_row("SELECT SUM(status IN ('queued','retry')) AS q, SUM(status = 'retry') AS r, SUM(status = 'dead') AS d, SUM(status = 'sent' AND created_at >= NOW() - INTERVAL 1 DAY) AS s, TIMESTAMPDIFF(MINUTE, MIN(CASE WHEN status IN ('queued','retry') THEN created_at END), NOW()) AS o FROM cdb_cs_outbox");
    return ['exists' => true, 'queued' => (int) ($r->q ?? 0), 'retry' => (int) ($r->r ?? 0), 'dead' => (int) ($r->d ?? 0), 'sent24' => (int) ($r->s ?? 0), 'oldest_min' => (int) ($r->o ?? 0)];
}

/** Latest backup file written by helpers/backups_function.php (backups/). */
function sl_devBackup(): array
{
    $dir = CDP_ROOT . '/backups';
    $files = is_dir($dir) ? (glob($dir . '/*.sql') ?: []) : [];
    $latest = null;
    foreach ($files as $f) {
        if (!$latest || filemtime($f) > filemtime($latest)) {
            $latest = $f;
        }
    }
    global $core;
    return [
        'dir' => $dir,
        'count' => count($files),
        'latest' => $latest ? basename($latest) : '',
        'latest_at' => $latest ? filemtime($latest) : 0,
        'latest_size' => $latest ? filesize($latest) : 0,
        'setting' => (string) ($core->backup ?? ''),
    ];
}

/** Quick TCP reachability test (used for SMTP). Never longer than $timeout seconds. */
function sl_devPort(string $host, int $port, float $timeout = 1.5): ?float
{
    if ($host === '' || $port <= 0) {
        return null;
    }
    $t = microtime(true);
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$fp) {
        return null;
    }
    fclose($fp);
    return round((microtime(true) - $t) * 1000, 1);
}

/** Git facts of the deployed checkout (read-only; only if the binary and .git exist). */
function sl_devGit(int $n = 20): array
{
    $out = ['available' => false, 'branch' => '', 'head' => '', 'commits' => [], 'dirty' => null, 'source' => ''];
    $root = CDP_ROOT;
    if (!is_dir($root . '/.git')) {
        $out['source'] = 'no .git directory';
        return $out;
    }
    $bin = '';
    foreach (['/usr/bin/git', '/usr/local/bin/git', '/bin/git'] as $b) {
        if (is_executable($b)) {
            $bin = $b;
            break;
        }
    }
    if ($bin === '' || !function_exists('shell_exec')) {
        $out['source'] = 'git binary not available';
        return $out;
    }
    $run = function (string $args) use ($bin, $root): string {
        return (string) @shell_exec('cd ' . escapeshellarg($root) . ' && ' . $bin . ' ' . $args . ' 2>/dev/null');
    };
    $out['branch'] = trim($run('rev-parse --abbrev-ref HEAD'));
    $out['head'] = trim($run('rev-parse --short HEAD'));
    $out['available'] = $out['head'] !== '';
    $out['source'] = 'git log';
    $log = $run('log -n ' . (int) $n . ' --date=iso-strict --pretty=format:%h%x1f%an%x1f%ad%x1f%s');
    foreach (preg_split('/\r?\n/', trim($log)) ?: [] as $line) {
        $p = explode("\x1f", $line);
        if (count($p) === 4) {
            $out['commits'][] = ['sha' => $p[0], 'by' => $p[1], 'at' => strtotime($p[2]) ?: 0, 'msg' => $p[3]];
        }
    }
    $status = trim($run('status --porcelain'));
    $out['dirty'] = $status === '' ? 0 : count(preg_split('/\r?\n/', $status));
    return $out;
}

/** Application version from VERSION file or cdb_settings.version. */
function sl_devVersion(): string
{
    global $core;
    $f = CDP_ROOT . '/VERSION';
    if (is_readable($f)) {
        $v = trim((string) file_get_contents($f));
        if ($v !== '') {
            return $v;
        }
    }
    return (string) ($core->version ?? '');
}

/** Config flags with secrets masked. */
function sl_devConfig(): array
{
    $flags = [];
    $consts = ['CDP_APP_URL', 'CDP_APP_MODE_DEMO', 'CDP_RBAC_MODE', 'CDP_FINANCE_ENABLED', 'CDP_API_ALLOWED_ORIGINS', 'CDP_DISPLAY_ERRORS', 'CDP_ERROR_LOG', 'CDP_DB_HOST', 'CDP_DB_NAME', 'CDP_DB_USER', 'CDP_DB_PASS'];
    foreach ($consts as $c) {
        if (!defined($c)) {
            $flags[] = ['k' => $c, 'v' => 'not set', 'secret' => false];
            continue;
        }
        $v = constant($c);
        $secret = in_array($c, ['CDP_DB_PASS', 'CDP_DB_USER'], true);
        $flags[] = ['k' => $c, 'v' => $secret ? sl_devMask($v) : (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v), 'secret' => $secret];
    }
    return $flags;
}

/** Is the site served over HTTPS (this request or the configured URL)? */
function sl_devHttps(): array
{
    $req = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $url = defined('CDP_APP_URL') ? (string) CDP_APP_URL : '';
    return ['request' => $req, 'configured' => stripos($url, 'https://') === 0, 'url' => $url];
}

/** Response headers this app sends (from headers_list() of the current request). */
function sl_devHeaders(): array
{
    $want = ['Strict-Transport-Security', 'X-Frame-Options', 'X-Content-Type-Options', 'Content-Security-Policy', 'Referrer-Policy', 'Permissions-Policy'];
    $have = [];
    foreach (headers_list() as $h) {
        $have[strtolower(trim(strstr($h, ':', true)))] = trim(substr(strstr($h, ':'), 1));
    }
    $out = [];
    foreach ($want as $w) {
        $out[] = ['name' => $w, 'set' => isset($have[strtolower($w)]), 'value' => $have[strtolower($w)] ?? ''];
    }
    $sess = session_get_cookie_params();
    $out[] = ['name' => 'Session cookie HttpOnly', 'set' => !empty($sess['httponly']), 'value' => ''];
    $out[] = ['name' => 'Session cookie Secure', 'set' => !empty($sess['secure']), 'value' => ''];
    $out[] = ['name' => 'Session cookie SameSite', 'set' => !empty($sess['samesite']), 'value' => (string) ($sess['samesite'] ?? '')];
    return $out;
}

/** External integrations and whether each is configured, with masked keys. */
function sl_devIntegrations(): array
{
    global $core;
    $list = [];
    $smtpHost = (string) ($core->smtp_host ?? '');
    $list[] = ['key' => 'mail', 'name' => 'E-mail (' . (($core->mailer ?? '') ?: 'PHP mail') . ')', 'icon' => 'i-mail', 'group' => 'Messaging',
        'configured' => ($core->mailer ?? '') === 'SMTP' ? $smtpHost !== '' && !empty($core->smtp_user) : true,
        'detail' => ($core->mailer ?? '') === 'SMTP' ? $smtpHost . ':' . ($core->smtp_port ?? '') . ' · ' . sl_devMask($core->smtp_user ?? '') : 'Local mail()',
        'href' => sl_route('SettingsHub', ['tab' => 'mail']), 'usage' => "channel = 'email'"];
    $hubtel = $mnotify = false;
    $hubtelSet = $mnotifySet = '';
    if (is_file(CDP_ROOT . '/helpers/hubtel_sms.php')) {
        require_once CDP_ROOT . '/helpers/hubtel_sms.php';
        if (function_exists('cdp_hubtelSmsConfig')) {
            $c = cdp_hubtelSmsConfig();
            $hubtel = cdp_hubtelSmsReady();
            $hubtelSet = 'client ' . sl_devMask($c['client_id']) . ' · sender ' . ($c['sender'] ?: '—') . ' · ' . (cdp_smsProviderEnabled('hubtel') ? 'switched on' : 'switched off');
        }
        if (function_exists('cdp_mnotifySmsConfig')) {
            $c = cdp_mnotifySmsConfig();
            $mnotify = cdp_mnotifySmsConfigured();
            $mnotifySet = 'key ' . sl_devMask($c['api_key']) . ' · sender ' . ($c['sender'] ?: '—') . ' · ' . (cdp_smsProviderEnabled('mnotify') ? 'switched on' : 'switched off');
        }
    }
    $list[] = ['key' => 'hubtel_sms', 'name' => 'Hubtel SMS', 'icon' => 'i-phone', 'group' => 'Messaging', 'configured' => $hubtel, 'detail' => $hubtelSet ?: 'helpers/hubtel_sms.php not installed', 'href' => sl_route('MessageTemplates'), 'usage' => "channel = 'sms'"];
    $list[] = ['key' => 'mnotify_sms', 'name' => 'mNotify SMS', 'icon' => 'i-phone', 'group' => 'Messaging', 'configured' => $mnotify, 'detail' => $mnotifySet ?: 'helpers/mnotify_sms.php not installed', 'href' => sl_route('MessageTemplates'), 'usage' => "channel = 'sms'"];
    $waUrl = (string) ($core->api_ws_url ?? '');
    $list[] = ['key' => 'whatsapp', 'name' => 'WhatsApp API', 'icon' => 'i-message', 'group' => 'Messaging', 'configured' => $waUrl !== '' && !empty($core->api_ws_token), 'detail' => ($waUrl ?: 'no URL') . ' · token ' . sl_devMask($core->api_ws_token ?? '') . ' · ' . (!empty($core->active_whatsapp) ? 'switched on' : 'switched off'), 'href' => sl_route('MessageTemplates'), 'usage' => "channel = 'whatsapp'"];
    $list[] = ['key' => 'otp', 'name' => 'Login OTP', 'icon' => 'i-key', 'group' => 'Security', 'configured' => !empty($core->active_otp), 'detail' => !empty($core->active_otp) ? 'Codes by e-mail / WhatsApp on staff sign-in' : 'Switched off in Settings', 'href' => sl_route('SettingsHub'), 'usage' => ''];
    if (is_file(CDP_ROOT . '/helpers/fs_gateways.php')) {
        require_once CDP_ROOT . '/helpers/fs_gateways.php';
        foreach ([['paystack', 'Paystack', 4], ['hubtel', 'Hubtel Pay', 6]] as $g) {
            $row = function_exists('cdp_fsGatewayRow') ? cdp_fsGatewayRow($g[2]) : null;
            $list[] = ['key' => 'pay_' . $g[0], 'name' => $g[1], 'icon' => 'i-card', 'group' => 'Payments', 'configured' => function_exists('cdp_fsGatewayConfigured') && cdp_fsGatewayConfigured($g[0]),
                'detail' => $row ? ('secret ' . sl_devMask($row->secret_key ?? '') . ' · ' . ((int) ($row->is_active ?? 0) === 1 ? 'active' : 'inactive')) : 'No gateway row (cdb_met_payment id ' . $g[2] . ')', 'href' => sl_route('Payments'), 'usage' => 'gateway'];
        }
    }
    if (is_file(CDP_ROOT . '/helpers/ai_assistant.php')) {
        require_once CDP_ROOT . '/helpers/ai_assistant.php';
        if (function_exists('cdp_aiSetting')) {
            $prov = (string) cdp_aiSetting('provider', 'claude');
            $key = (string) cdp_aiSetting('key_' . $prov, '');
            $list[] = ['key' => 'ai', 'name' => 'AI assistant (' . $prov . ')', 'icon' => 'i-ai', 'group' => 'AI', 'configured' => $key !== '' && (string) cdp_aiSetting('enabled', '0') === '1', 'detail' => 'model ' . cdp_aiSetting('model', '') . ' · key ' . sl_devMask($key) . ' · ' . ((string) cdp_aiSetting('enabled', '0') === '1' ? 'enabled' : 'disabled'), 'href' => sl_route('AiCenter'), 'usage' => ''];
        }
    }
    $apiTokens = sl_tableExists('cdb_api_tokens') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_api_tokens WHERE revoked_at IS NULL AND expires_at > NOW()') : 0;
    $list[] = ['key' => 'api', 'name' => 'Public API v1', 'icon' => 'i-link', 'group' => 'API', 'configured' => is_dir(CDP_ROOT . '/api/v1'), 'detail' => $apiTokens . ' active tokens · CORS ' . (defined('CDP_API_ALLOWED_ORIGINS') ? CDP_API_ALLOWED_ORIGINS : 'not set'), 'href' => sl_route('ApiAccess'), 'usage' => ''];
    $hooks = sl_tableExists('cdb_package_status_webhooks') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_package_status_webhooks WHERE created_at >= NOW() - INTERVAL 7 DAY') : 0;
    $list[] = ['key' => 'webhooks', 'name' => 'Inbound status webhooks', 'icon' => 'i-bolt', 'group' => 'API', 'configured' => sl_tableExists('cdb_package_status_webhooks'), 'detail' => $hooks . ' received in 7 days', 'href' => sl_route('ApiTraffic'), 'usage' => ''];
    // message log counts per channel (7 days) for the usage column
    if (sl_tableExists('cdb_message_log')) {
        $stats = [];
        foreach (sl_rows("SELECT channel, SUM(status = 'sent') AS ok, SUM(status <> 'sent') AS bad FROM cdb_message_log WHERE created_at >= NOW() - INTERVAL 7 DAY GROUP BY channel") as $r) {
            $stats[$r->channel] = ['ok' => (int) $r->ok, 'bad' => (int) $r->bad];
        }
        foreach ($list as &$it) {
            if (preg_match("/channel = '(\w+)'/", $it['usage'], $m)) {
                $s = $stats[$m[1]] ?? ['ok' => 0, 'bad' => 0];
                $it['usage'] = sl_num($s['ok']) . ' sent · ' . sl_num($s['bad']) . ' failed (7 d)';
                $it['failed7'] = $s['bad'];
            } else {
                $it['usage'] = $it['usage'] === 'gateway' && sl_tableExists('cdb_payments_gateway') ? sl_num((int) sl_scalar('SELECT COUNT(*) FROM cdb_payments_gateway WHERE date_payment >= NOW() - INTERVAL 7 DAY AND LOWER(gateway) LIKE :g', [':g' => '%' . strtolower(str_replace('pay_', '', $it['key'])) . '%'])) . ' payments (7 d)' : ($it['usage'] === 'gateway' ? '—' : $it['usage']);
            }
        }
        unset($it);
    }
    return $list;
}

/**
 * Services of this installation with a live health check each.
 * status: ok | warn | bad | off
 */
function sl_devServices(): array
{
    global $core;
    $s = [];
    $s[] = ['key' => 'php', 'name' => 'PHP ' . PHP_VERSION, 'icon' => 'i-code', 'status' => version_compare(PHP_VERSION, '8.1', '>=') ? 'ok' : 'warn', 'version' => PHP_VERSION, 'detail' => PHP_SAPI . ' · memory_limit ' . ini_get('memory_limit') . ' · OPcache ' . (function_exists('opcache_get_status') && @opcache_get_status(false) ? 'on' : 'off'), 'ms' => null, 'href' => sl_route('DevInfrastructure')];
    $my = sl_devMysql();
    $s[] = ['key' => 'mysql', 'name' => 'MySQL', 'icon' => 'i-database', 'status' => $my['ok'] ? ($my['max_conn'] && $my['threads'] / max(1, $my['max_conn']) > 0.8 ? 'warn' : 'ok') : 'bad', 'version' => $my['version'], 'detail' => $my['ok'] ? $my['threads'] . ' / ' . $my['max_conn'] . ' connections · up ' . floor($my['uptime'] / 86400) . ' d ' . floor($my['uptime'] % 86400 / 3600) . ' h' : 'No answer', 'ms' => $my['ms'], 'href' => sl_route('DevMetrics')];
    $disk = sl_devDisk();
    $s[] = ['key' => 'disk', 'name' => 'Disk', 'icon' => 'i-folder', 'status' => $disk['pct'] >= 90 ? 'bad' : ($disk['pct'] >= 80 ? 'warn' : 'ok'), 'version' => '', 'detail' => sl_devBytes($disk['used']) . ' of ' . sl_devBytes($disk['total']) . ' used (' . $disk['pct'] . '%)', 'ms' => null, 'href' => sl_route('DevInfrastructure')];
    $jobs = sl_devCronJobs();
    $fails = array_sum(array_column($jobs, 'fails24'));
    $ran = array_sum(array_column($jobs, 'runs24'));
    $lastAny = sl_tableExists('cdb_cron_runs') ? (string) sl_scalar('SELECT MAX(started_at) FROM cdb_cron_runs', [], '') : '';
    $s[] = ['key' => 'cron', 'name' => 'Cron jobs', 'icon' => 'i-clock', 'status' => $fails ? 'bad' : ($ran ? 'ok' : 'warn'), 'version' => count($jobs) . ' scripts', 'detail' => $ran ? $ran . ' runs in 24 h · ' . $fails . ' failed · last ' . sl_ago($lastAny) : (sl_tableExists('cdb_cron_runs') ? 'No run recorded in 24 h' : 'Ledger table missing (run the upgrade)'), 'ms' => null, 'href' => sl_route('DevLogs', ['tab' => 'cron'])];
    $q = sl_devQueue();
    $s[] = ['key' => 'queue', 'name' => 'Notification outbox', 'icon' => 'i-send', 'status' => !$q['exists'] ? 'off' : ($q['dead'] > 0 || $q['oldest_min'] > 30 ? 'warn' : 'ok'), 'version' => 'cdb_cs_outbox', 'detail' => $q['exists'] ? $q['queued'] . ' waiting · ' . $q['retry'] . ' retrying · ' . $q['dead'] . ' dead · ' . $q['sent24'] . ' sent in 24 h' : 'Created when the Customer Service module is first used', 'ms' => null, 'href' => sl_route('CsChannels')];
    $smtpMs = null;
    $mailOk = true;
    if (($core->mailer ?? '') === 'SMTP') {
        $smtpMs = sl_devPort((string) ($core->smtp_host ?? ''), (int) ($core->smtp_port ?? 587), 1.5);
        $mailOk = $smtpMs !== null;
    }
    $s[] = ['key' => 'mail', 'name' => 'E-mail', 'icon' => 'i-mail', 'status' => ($core->mailer ?? '') === 'SMTP' ? ($mailOk ? 'ok' : 'warn') : 'ok', 'version' => (string) ($core->mailer ?? 'mail()'), 'detail' => ($core->mailer ?? '') === 'SMTP' ? ($core->smtp_host . ':' . $core->smtp_port . ($mailOk ? ' reachable' : ' not reachable from this server')) : 'PHP mail()', 'ms' => $smtpMs, 'href' => sl_route('SettingsHub')];
    foreach (sl_devIntegrations() as $i) {
        if (in_array($i['key'], ['hubtel_sms', 'mnotify_sms', 'whatsapp'], true)) {
            $bad = (int) ($i['failed7'] ?? 0);
            $s[] = ['key' => $i['key'], 'name' => $i['name'], 'icon' => $i['icon'], 'status' => !$i['configured'] ? 'off' : ($bad > 0 ? 'warn' : 'ok'), 'version' => '', 'detail' => $i['configured'] ? $i['usage'] : 'Not configured', 'ms' => null, 'href' => $i['href']];
        }
    }
    $b = sl_devBackup();
    $age = $b['latest_at'] ? (time() - $b['latest_at']) / 86400 : null;
    $s[] = ['key' => 'backup', 'name' => 'Database backups', 'icon' => 'i-archive', 'status' => $age === null ? 'warn' : ($age > 7 ? 'warn' : 'ok'), 'version' => $b['count'] . ' files', 'detail' => $b['latest'] ? 'Last ' . sl_date($b['latest_at'], 'M j, Y H:i') . ' · ' . sl_devBytes($b['latest_size']) : 'No backup file in backups/', 'ms' => null, 'href' => 'backup.php'];
    return $s;
}

/** Value of an alert metric right now. */
function sl_devMetric(string $metric, int $windowMin): ?float
{
    switch ($metric) {
        case 'disk_pct':
            return (float) sl_devDisk()['pct'];
        case 'failed_cron':
            return sl_tableExists('cdb_cron_runs') ? (float) sl_scalar("SELECT COUNT(*) FROM cdb_cron_runs WHERE status = 'failed' AND started_at >= NOW() - INTERVAL :m MINUTE", [':m' => max(1, $windowMin)]) : 0.0;
        case 'cron_stale_min':
            if (!sl_tableExists('cdb_cron_runs')) {
                return null;
            }
            $last = (string) sl_scalar('SELECT MAX(started_at) FROM cdb_cron_runs', [], '');
            return $last ? (float) floor((time() - strtotime($last)) / 60) : null;
        case 'failed_logins':
            return sl_tableExists('cdb_activity_log') ? (float) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE verb = 'login' AND outcome IN ('failure','denied') AND created_at >= NOW() - INTERVAL :m MINUTE", [':m' => max(1, $windowMin)]) : 0.0;
        case 'queue_backlog':
            return (float) sl_devQueue()['queued'];
        case 'php_errors':
            return sl_devErrorLogPath() !== '' ? (float) sl_devPhpErrorCount(max(1, $windowMin)) : null;
        case 'otp_failures':
            return sl_tableExists('cdb_auth_otp_challenges') ? (float) sl_scalar("SELECT COUNT(*) FROM cdb_auth_otp_challenges WHERE status IN ('failed','expired','locked') AND created_at >= NOW() - INTERVAL :m MINUTE", [':m' => max(1, $windowMin)]) : 0.0;
    }
    return null;
}

function sl_devCompare(?float $value, string $cmp, float $threshold): bool
{
    if ($value === null) {
        return false;
    }
    switch ($cmp) {
        case '>=': return $value >= $threshold;
        case '<': return $value < $threshold;
        case '<=': return $value <= $threshold;
        case '=': return abs($value - $threshold) < 0.0001;
        default: return $value > $threshold;
    }
}

/**
 * Evaluate every enabled rule: open or refresh firing alerts, resolve cleared
 * ones. Returns a summary. Used by cron/dev_alerts.php and the "Evaluate now"
 * button on the Alerts page.
 */
function sl_devEvaluateAlerts(): array
{
    $out = ['checked' => 0, 'firing' => 0, 'opened' => 0, 'resolved' => 0, 'rules' => []];
    if (!sl_tableExists('cdb_dev_alert_rules') || !sl_tableExists('cdb_dev_alerts')) {
        return $out;
    }
    $db = new Conexion();
    foreach (sl_rows('SELECT * FROM cdb_dev_alert_rules ORDER BY id') as $r) {
        $out['checked']++;
        $value = sl_devMetric($r->metric, (int) $r->window_min);
        $fires = (int) $r->enabled === 1 && sl_devCompare($value, $r->comparator, (float) $r->threshold);
        $key = 'rule:' . $r->rule_key;
        $open = sl_row("SELECT id, status FROM cdb_dev_alerts WHERE alert_key = :k AND status IN ('active','acknowledged') ORDER BY id DESC", [':k' => $key]);
        $fmt = fn($v) => $v === null ? 'unavailable' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        $detail = 'Value ' . $fmt($value) . ' ' . $r->comparator . ' limit ' . $fmt($r->threshold) . ($r->window_min ? ' over ' . (int) $r->window_min . ' min' : '');
        $ctx = json_encode(['metric' => $r->metric, 'value' => $value, 'threshold' => (float) $r->threshold, 'window_min' => (int) $r->window_min]);
        if ($fires) {
            $out['firing']++;
            if ($open) {
                $db->cdp_query('UPDATE cdb_dev_alerts SET last_seen = NOW(), detail = :d, context = :c WHERE id = :id');
                $db->bind(':d', $detail); $db->bind(':c', $ctx); $db->bind(':id', (int) $open->id);
                $db->cdp_execute();
            } else {
                $db->cdp_query('INSERT INTO cdb_dev_alerts (alert_key, severity, service, title, detail, context, status, first_seen, last_seen) VALUES (:k, :s, :sv, :t, :d, :c, :st, NOW(), NOW())');
                $db->bind(':k', $key); $db->bind(':s', $r->severity === 'critical' ? 'critical' : 'warning'); $db->bind(':sv', 'Alert rules'); $db->bind(':t', $r->label); $db->bind(':d', $detail); $db->bind(':c', $ctx); $db->bind(':st', 'active');
                $db->cdp_execute();
                $out['opened']++;
            }
        } elseif ($open) {
            $db->cdp_query('UPDATE cdb_dev_alerts SET status = :s, resolved_at = NOW(), detail = :d WHERE id = :id');
            $db->bind(':s', 'resolved'); $db->bind(':d', $detail . ' · cleared'); $db->bind(':id', (int) $open->id);
            $db->cdp_execute();
            $out['resolved']++;
        }
        $out['rules'][] = ['key' => $r->rule_key, 'value' => $value, 'fires' => $fires, 'enabled' => (int) $r->enabled];
    }
    return $out;
}
