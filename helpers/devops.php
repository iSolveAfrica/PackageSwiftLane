<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Developer dashboard (DevOps) — live health of this installation.      *
// *                                                                       *
// * Everything here is measured, never invented:                          *
// *   server   /proc (CPU, memory, uptime, network, disk I/O), disk space  *
// *   database timed ping, MySQL status counters, schema size             *
// *   web/API  timed HTTP probes of this site's own URL                    *
// *   services the support worker heartbeat, outbox, AI runs, the message *
// *            log (e-mail, SMS, WhatsApp), payment gateway config         *
// *   logs     activity log, message log, AI runs, PHP error log          *
// *   deploys  the git reflog of this checkout, plus changes we detected  *
// *   alerts   rules evaluated on every sample; they open and close       *
// *            themselves and can be acknowledged                         *
// *                                                                       *
// * Access: super admins only, and only those assigned (cdb_dev_access).  *
// * The first super admin to open the page is assigned automatically so   *
// * the page can never lock everybody out; after that an assigned         *
// * developer assigns the others. See docs/DEVELOPER_DASHBOARD.md.        *
// *                                                                       *
// *************************************************************************

if (!function_exists('cdp_devQ')) {

    // ---------------------------------------------------------------------
    // Database helpers
    // ---------------------------------------------------------------------

    function cdp_devQ($sql, array $bind = [])
    {
        try {
            $db = new Conexion;
            $db->cdp_query($sql);
            foreach ($bind as $k => $v) {
                $db->bind($k, $v);
            }
            $rows = $db->cdp_registros();
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            error_log('DEV_Q ' . $e->getMessage());
            return [];
        }
    }

    function cdp_devOne($sql, array $bind = [])
    {
        $r = cdp_devQ($sql, $bind);
        return $r ? $r[0] : null;
    }

    function cdp_devX($sql, array $bind = [])
    {
        try {
            $db = new Conexion;
            $db->cdp_query($sql);
            foreach ($bind as $k => $v) {
                $db->bind($k, $v);
            }
            return (bool) $db->cdp_execute();
        } catch (Throwable $e) {
            error_log('DEV_X ' . $e->getMessage());
            return false;
        }
    }

    function cdp_devNow()
    {
        return date('Y-m-d H:i:s');
    }

    function cdp_devTableExists($name)
    {
        static $cache = [];
        if (!isset($cache[$name])) {
            $cache[$name] = (bool) cdp_devOne("SELECT 1 AS x FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t", [':t' => $name]);
        }
        return $cache[$name];
    }

    /** Creates the dashboard's own tables on first use. */
    function cdp_devEnsureTables()
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        $t = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $sql = [
            "CREATE TABLE IF NOT EXISTS cdb_dev_access (
                user_id INT NOT NULL, granted_by INT NULL, granted_at DATETIME NOT NULL, PRIMARY KEY (user_id))$t",
            "CREATE TABLE IF NOT EXISTS cdb_dev_metrics (
                id BIGINT NOT NULL AUTO_INCREMENT, ts DATETIME NOT NULL,
                cpu_pct DECIMAL(6,2) NULL, load1 DECIMAL(8,2) NULL, mem_pct DECIMAL(6,2) NULL, disk_pct DECIMAL(6,2) NULL,
                db_ms DECIMAL(10,2) NULL, db_up TINYINT NOT NULL DEFAULT 1, web_ms DECIMAL(10,2) NULL, web_ok TINYINT NULL,
                api_ms DECIMAL(10,2) NULL, actions_pm INT NULL, errors_pm INT NULL, err_rate DECIMAL(6,3) NULL,
                net_rx_bps BIGINT NULL, net_tx_bps BIGINT NULL, disk_r_bps BIGINT NULL, disk_w_bps BIGINT NULL, db_qps DECIMAL(10,2) NULL,
                PRIMARY KEY (id), KEY idx_ts (ts))$t",
            "CREATE TABLE IF NOT EXISTS cdb_dev_alerts (
                id INT NOT NULL AUTO_INCREMENT, alert_key VARCHAR(80) NOT NULL, severity VARCHAR(12) NOT NULL, service VARCHAR(60) NOT NULL,
                title VARCHAR(160) NOT NULL, detail VARCHAR(500) NULL, context TEXT NULL, status VARCHAR(12) NOT NULL DEFAULT 'active',
                first_seen DATETIME NOT NULL, last_seen DATETIME NOT NULL, resolved_at DATETIME NULL, ack_by INT NULL, ack_at DATETIME NULL,
                PRIMARY KEY (id), KEY idx_key (alert_key, status), KEY idx_status (status, last_seen))$t",
            "CREATE TABLE IF NOT EXISTS cdb_dev_deploys (
                id INT NOT NULL AUTO_INCREMENT, commit_hash VARCHAR(64) NOT NULL, branch VARCHAR(120) NULL, message VARCHAR(255) NULL,
                author VARCHAR(120) NULL, committed_at DATETIME NULL, detected_at DATETIME NOT NULL, source VARCHAR(20) NOT NULL DEFAULT 'detected',
                PRIMARY KEY (id), KEY idx_hash (commit_hash))$t",
            "CREATE TABLE IF NOT EXISTS cdb_dev_state (k VARCHAR(60) NOT NULL, v TEXT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (k))$t",
        ];
        $ok = true;
        foreach ($sql as $s) {
            if (!cdp_devX($s)) {
                $ok = false;
            }
        }
        return $ok;
    }

    function cdp_devState($k, $default = null)
    {
        $r = cdp_devOne("SELECT v FROM cdb_dev_state WHERE k = :k", [':k' => $k]);
        if (!$r || $r->v === null) {
            return $default;
        }
        $d = json_decode($r->v, true);
        return $d === null ? $default : $d;
    }

    function cdp_devStateSet($k, $v)
    {
        return cdp_devX("INSERT INTO cdb_dev_state (k, v, updated_at) VALUES (:k, :v, :n) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = VALUES(updated_at)",
            [':k' => $k, ':v' => json_encode($v), ':n' => cdp_devNow()]);
    }

    // ---------------------------------------------------------------------
    // Access: super admins who have been assigned
    // ---------------------------------------------------------------------

    function cdp_devIsSuper($user)
    {
        if (!is_object($user) || empty($user->logged_in)) {
            return false;
        }
        if (!function_exists('cdp_roleHasFlag')) {
            require_once __DIR__ . '/rbac.php';
        }
        return cdp_roleHasFlag((int) $user->userlevel, 'is_superadmin');
    }

    /** Super admin AND assigned. The very first super admin is assigned on first visit. */
    function cdp_devCanUse($user)
    {
        if (!cdp_devIsSuper($user) || !cdp_devEnsureTables()) {
            return false;
        }
        $uid = (int) $user->uid;
        if (cdp_devOne("SELECT user_id FROM cdb_dev_access WHERE user_id = :u", [':u' => $uid])) {
            return true;
        }
        $any = cdp_devOne("SELECT COUNT(*) AS n FROM cdb_dev_access");
        if ($any && (int) $any->n === 0) {
            cdp_devX("INSERT INTO cdb_dev_access (user_id, granted_by, granted_at) VALUES (:u, :u, :n)", [':u' => $uid, ':n' => cdp_devNow()]);
            return true;
        }
        return false;
    }

    /** For the side menu: read-only, never creates tables or assigns anyone. */
    function cdp_devMenuVisible($user)
    {
        if (!cdp_devIsSuper($user)) {
            return false;
        }
        if (!cdp_devTableExists('cdb_dev_access')) {
            return true;
        }
        $mine = cdp_devOne("SELECT user_id FROM cdb_dev_access WHERE user_id = :u", [':u' => (int) $user->uid]);
        if ($mine) {
            return true;
        }
        $any = cdp_devOne("SELECT COUNT(*) AS n FROM cdb_dev_access");
        return $any && (int) $any->n === 0;
    }

    /** Super admins with whether each is assigned. */
    function cdp_devAccessList()
    {
        if (!function_exists('cdp_roleFlagsMap')) {
            require_once __DIR__ . '/rbac.php';
        }
        $out = [];
        $assigned = [];
        foreach (cdp_devQ("SELECT user_id, granted_by, granted_at FROM cdb_dev_access") as $a) {
            $assigned[(int) $a->user_id] = $a;
        }
        foreach (cdp_devQ("SELECT id, username, fname, lname, email, userlevel FROM cdb_users ORDER BY fname, lname") as $u) {
            if (!cdp_roleHasFlag((int) $u->userlevel, 'is_superadmin')) {
                continue;
            }
            $a = $assigned[(int) $u->id] ?? null;
            $out[] = [
                'id' => (int) $u->id, 'name' => trim($u->fname . ' ' . $u->lname) ?: $u->username, 'username' => (string) $u->username,
                'email' => (string) $u->email, 'assigned' => (bool) $a, 'granted_at' => $a ? (string) $a->granted_at : null,
            ];
        }
        return $out;
    }

    function cdp_devAccessSet($targetId, $on, $byId)
    {
        $targetId = (int) $targetId;
        $u = cdp_devOne("SELECT id, userlevel FROM cdb_users WHERE id = :i", [':i' => $targetId]);
        if (!$u || !cdp_roleHasFlag((int) $u->userlevel, 'is_superadmin')) {
            return ['ok' => false, 'error' => 'Only super admins can be assigned the developer dashboard.'];
        }
        if ($on) {
            cdp_devX("INSERT IGNORE INTO cdb_dev_access (user_id, granted_by, granted_at) VALUES (:u, :b, :n)", [':u' => $targetId, ':b' => (int) $byId, ':n' => cdp_devNow()]);
        } else {
            $n = cdp_devOne("SELECT COUNT(*) AS n FROM cdb_dev_access");
            if ($n && (int) $n->n <= 1) {
                return ['ok' => false, 'error' => 'At least one super admin must keep access.'];
            }
            cdp_devX("DELETE FROM cdb_dev_access WHERE user_id = :u", [':u' => $targetId]);
        }
        return ['ok' => true];
    }

    // ---------------------------------------------------------------------
    // Server (Linux /proc; values are null where the host does not expose them)
    // ---------------------------------------------------------------------

    function cdp_devRead($file)
    {
        return @is_readable($file) ? (string) @file_get_contents($file) : '';
    }

    /** CPU jiffies from /proc/stat: [busy, total]. */
    function cdp_devCpuTicks()
    {
        $s = cdp_devRead('/proc/stat');
        if (!preg_match('/^cpu\s+(.+)$/m', $s, $m)) {
            return null;
        }
        $v = array_map('floatval', preg_split('/\s+/', trim($m[1])));
        $idle = ($v[3] ?? 0) + ($v[4] ?? 0);
        $total = array_sum(array_slice($v, 0, 8));
        return [$total - $idle, $total];
    }

    function cdp_devNetBytes()
    {
        $rx = $tx = 0;
        $found = false;
        foreach (explode("\n", cdp_devRead('/proc/net/dev')) as $l) {
            if (!preg_match('/^\s*([^:]+):\s*(.+)$/', $l, $m) || trim($m[1]) === 'lo') {
                continue;
            }
            $c = preg_split('/\s+/', trim($m[2]));
            $rx += (float) ($c[0] ?? 0);
            $tx += (float) ($c[8] ?? 0);
            $found = true;
        }
        return $found ? [$rx, $tx] : null;
    }

    function cdp_devDiskBytes()
    {
        $r = $w = 0;
        $found = false;
        foreach (explode("\n", cdp_devRead('/proc/diskstats')) as $l) {
            $c = preg_split('/\s+/', trim($l));
            if (count($c) < 10) {
                continue;
            }
            // Whole disks only, so partitions are not counted twice.
            if (!preg_match('/^(sd[a-z]+|vd[a-z]+|xvd[a-z]+|nvme\d+n\d+|mmcblk\d+)$/', $c[2])) {
                continue;
            }
            $r += (float) $c[5] * 512;
            $w += (float) $c[9] * 512;
            $found = true;
        }
        return $found ? [$r, $w] : null;
    }

    function cdp_devServer()
    {
        $now = microtime(true);
        $prev = cdp_devState('proc_prev', []);
        $cur = ['t' => $now, 'cpu' => cdp_devCpuTicks(), 'net' => cdp_devNetBytes(), 'disk' => cdp_devDiskBytes()];
        $dt = isset($prev['t']) ? max(0.001, $now - $prev['t']) : 0;
        $cpuPct = null;
        if ($cur['cpu'] && !empty($prev['cpu']) && $cur['cpu'][1] > $prev['cpu'][1]) {
            $cpuPct = round(($cur['cpu'][0] - $prev['cpu'][0]) / ($cur['cpu'][1] - $prev['cpu'][1]) * 100, 1);
        }
        $rate = function ($a, $b, $i) use ($dt) {
            return ($a && $b && $dt > 0 && $a[$i] >= $b[$i]) ? (int) round(($a[$i] - $b[$i]) / $dt) : null;
        };
        $net = ['rx' => $rate($cur['net'], $prev['net'] ?? null, 0), 'tx' => $rate($cur['net'], $prev['net'] ?? null, 1)];
        $dio = ['r' => $rate($cur['disk'], $prev['disk'] ?? null, 0), 'w' => $rate($cur['disk'], $prev['disk'] ?? null, 1)];
        // Keep a baseline at most every 2 s so quick refreshes still measure something.
        if (!$dt || $dt >= 2) {
            cdp_devStateSet('proc_prev', $cur);
        }

        $cores = max(1, preg_match_all('/^processor\s*:/m', cdp_devRead('/proc/cpuinfo')));
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        if ($cpuPct === null && $load) {
            $cpuPct = round(min(100, $load[0] / $cores * 100), 1);
        }
        $mem = [];
        foreach (explode("\n", cdp_devRead('/proc/meminfo')) as $l) {
            if (preg_match('/^(\w+):\s+(\d+)/', $l, $m)) {
                $mem[$m[1]] = (float) $m[2] * 1024;
            }
        }
        $memTotal = $mem['MemTotal'] ?? null;
        $memAvail = $mem['MemAvailable'] ?? (isset($mem['MemFree']) ? $mem['MemFree'] + ($mem['Cached'] ?? 0) : null);
        $root = dirname(__DIR__);
        $dTotal = @disk_total_space($root) ?: null;
        $dFree = @disk_free_space($root) ?: null;
        $up = cdp_devRead('/proc/uptime');
        return [
            'host' => (string) gethostname(), 'os' => php_uname('s') . ' ' . php_uname('r'), 'arch' => php_uname('m'),
            'software' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? PHP_SAPI),
            'uptime_s' => $up ? (int) floatval($up) : null,
            'cores' => $cores, 'load' => $load ? array_map(function ($x) { return round($x, 2); }, $load) : null, 'cpu_pct' => $cpuPct,
            'mem_total' => $memTotal, 'mem_used' => ($memTotal && $memAvail !== null) ? $memTotal - $memAvail : null,
            'mem_pct' => ($memTotal && $memAvail !== null) ? round(($memTotal - $memAvail) / $memTotal * 100, 1) : null,
            'swap_total' => $mem['SwapTotal'] ?? null, 'swap_used' => isset($mem['SwapTotal'], $mem['SwapFree']) ? $mem['SwapTotal'] - $mem['SwapFree'] : null,
            'disk_total' => $dTotal, 'disk_used' => ($dTotal && $dFree !== null) ? $dTotal - $dFree : null,
            'disk_pct' => ($dTotal && $dFree !== null) ? round(($dTotal - $dFree) / $dTotal * 100, 1) : null,
            'net_rx_bps' => $net['rx'], 'net_tx_bps' => $net['tx'], 'disk_r_bps' => $dio['r'], 'disk_w_bps' => $dio['w'],
            'php' => [
                'version' => PHP_VERSION, 'sapi' => PHP_SAPI, 'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => (int) ini_get('max_execution_time'), 'upload_max' => ini_get('upload_max_filesize'),
                'opcache' => function_exists('opcache_get_status') && @opcache_get_status(false) ? true : false,
                'extensions' => array_values(array_filter(['curl', 'pdo_mysql', 'mbstring', 'gd', 'openssl', 'zip', 'intl', 'json'], 'extension_loaded')),
                'missing' => array_values(array_filter(['curl', 'pdo_mysql', 'mbstring', 'gd', 'openssl'], function ($e) { return !extension_loaded($e); })),
                'peak_mem' => memory_get_peak_usage(true),
            ],
        ];
    }

    // ---------------------------------------------------------------------
    // Database
    // ---------------------------------------------------------------------

    function cdp_devDb()
    {
        $out = ['up' => false, 'ping_ms' => null];
        try {
            $db = new Conexion;
            $times = [];
            for ($i = 0; $i < 3; $i++) {
                $t = microtime(true);
                $db->cdp_query('SELECT 1');
                $db->cdp_execute();
                $times[] = (microtime(true) - $t) * 1000;
            }
            sort($times);
            $out['up'] = true;
            $out['ping_ms'] = round($times[1], 2);
        } catch (Throwable $e) {
            $out['error'] = 'The database did not answer.';
            return $out;
        }
        $v = cdp_devOne('SELECT VERSION() AS v');
        $out['version'] = $v ? (string) $v->v : null;
        $st = [];
        foreach (cdp_devQ("SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime','Threads_connected','Threads_running','Questions','Slow_queries','Aborted_connects')") as $r) {
            $st[$r->Variable_name] = (float) $r->Value;
        }
        $mx = cdp_devOne("SHOW VARIABLES LIKE 'max_connections'");
        $size = cdp_devOne("SELECT SUM(data_length + index_length) AS b, COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
        $prev = cdp_devState('db_prev', []);
        $now = microtime(true);
        $qps = null;
        if (isset($st['Questions'], $prev['q'], $prev['t']) && $st['Questions'] >= $prev['q'] && $now > $prev['t']) {
            $qps = round(($st['Questions'] - $prev['q']) / ($now - $prev['t']), 2);
        }
        if (isset($st['Questions']) && (!isset($prev['t']) || $now - $prev['t'] >= 2)) {
            cdp_devStateSet('db_prev', ['q' => $st['Questions'], 't' => $now]);
        }
        $out += [
            'uptime_s' => isset($st['Uptime']) ? (int) $st['Uptime'] : null,
            'connections' => isset($st['Threads_connected']) ? (int) $st['Threads_connected'] : null,
            'running' => isset($st['Threads_running']) ? (int) $st['Threads_running'] : null,
            'max_connections' => $mx ? (int) $mx->Value : null,
            'slow_queries' => isset($st['Slow_queries']) ? (int) $st['Slow_queries'] : null,
            'qps' => $qps,
            'size_bytes' => $size ? (int) $size->b : null, 'tables' => $size ? (int) $size->n : null,
        ];
        return $out;
    }

    // ---------------------------------------------------------------------
    // HTTP probes of this site
    // ---------------------------------------------------------------------

    function cdp_devBaseUrl()
    {
        static $u = null;
        if ($u !== null) {
            return $u;
        }
        $u = '';
        try {
            if (class_exists('Core')) {
                $c = new Core();
                $u = (string) ($c->site_url ?? '');
            }
        } catch (Throwable $e) {
            $u = '';
        }
        if ($u === '' && !empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $dir = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
            $u = $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir;
        }
        return $u = rtrim($u, '/') . '/';
    }

    /** Timed GET. Cached for 15 s so a 5 s refresh does not hammer the site. */
    function cdp_devProbe($key, $url, $okBelow = 500)
    {
        $cache = cdp_devState('probe_' . $key);
        if ($cache && isset($cache['at']) && time() - $cache['at'] < 15) {
            return $cache;
        }
        $r = ['url' => $url, 'ok' => null, 'code' => null, 'ms' => null, 'at' => time(), 'error' => null];
        if (!function_exists('curl_init') || !preg_match('#^https?://#', $url)) {
            $r['error'] = function_exists('curl_init') ? 'No site URL configured' : 'PHP curl extension missing';
            return $r;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'SwiftLane-HealthCheck/1.0', CURLOPT_NOBODY => false, CURLOPT_RANGE => '0-2047']);
        $t = microtime(true);
        curl_exec($ch);
        $r['ms'] = round((microtime(true) - $t) * 1000, 1);
        $r['code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            $r['error'] = curl_error($ch);
            $r['ok'] = false;
        } else {
            $r['ok'] = $r['code'] > 0 && $r['code'] < $okBelow;
        }
        curl_close($ch);
        cdp_devStateSet('probe_' . $key, $r);
        return $r;
    }

    // ---------------------------------------------------------------------
    // Application activity
    // ---------------------------------------------------------------------

    function cdp_devActivity()
    {
        $out = ['actions_1m' => null, 'actions_5m' => null, 'failed_5m' => null, 'actions_24h' => null, 'failed_24h' => null];
        if (cdp_devTableExists('cdb_activity_log')) {
            $r = cdp_devOne("SELECT
                    SUM(created_at >= NOW() - INTERVAL 1 MINUTE) AS a1,
                    SUM(created_at >= NOW() - INTERVAL 5 MINUTE) AS a5,
                    SUM(created_at >= NOW() - INTERVAL 5 MINUTE AND outcome <> 'success') AS f5,
                    COUNT(*) AS a24, SUM(outcome <> 'success') AS f24
                FROM cdb_activity_log WHERE created_at >= NOW() - INTERVAL 1 DAY");
            if ($r) {
                $out = ['actions_1m' => (int) $r->a1, 'actions_5m' => (int) $r->a5, 'failed_5m' => (int) $r->f5, 'actions_24h' => (int) $r->a24, 'failed_24h' => (int) $r->f24];
            }
        }
        return $out;
    }

    /** Message log per channel, last 24 h: sent / failed / skipped and the last failure. */
    function cdp_devChannels()
    {
        $out = [];
        if (!cdp_devTableExists('cdb_message_log')) {
            return $out;
        }
        foreach (cdp_devQ("SELECT channel, SUM(status = 'sent') AS s, SUM(status = 'failed') AS f, SUM(status = 'skipped') AS k, MAX(created_at) AS last_at,
                MAX(CASE WHEN status = 'failed' THEN created_at END) AS last_fail
            FROM cdb_message_log WHERE created_at >= NOW() - INTERVAL 1 DAY GROUP BY channel") as $r) {
            $out[strtolower((string) $r->channel)] = ['sent' => (int) $r->s, 'failed' => (int) $r->f, 'skipped' => (int) $r->k, 'last_at' => $r->last_at, 'last_fail' => $r->last_fail];
        }
        return $out;
    }

    // ---------------------------------------------------------------------
    // Services: one list, each with a status worked out from what it measured
    // ---------------------------------------------------------------------

    function cdp_devServices(array $srv, array $db, array $web, array $api, array $act, array $ch)
    {
        $s = [];
        $add = function ($key, $name, $tech, $layer, $status, array $m = []) use (&$s) {
            $s[] = array_merge(['key' => $key, 'name' => $name, 'tech' => $tech, 'layer' => $layer, 'status' => $status,
                'latency_ms' => null, 'error_rate' => null, 'throughput' => null, 'instances' => '1/1', 'detail' => ''], $m);
        };
        // Web application
        $wst = $web['ok'] === null ? 'unknown' : ($web['ok'] ? ($web['ms'] > 1500 ? 'degraded' : 'operational') : 'down');
        $ar = ($act['actions_5m'] ?? 0) ? round(($act['failed_5m'] ?? 0) / $act['actions_5m'] * 100, 2) : 0;
        $add('web', 'Web application', 'PHP ' . PHP_VERSION, 'app', $wst, ['latency_ms' => $web['ms'], 'error_rate' => $ar,
            'throughput' => ($act['actions_5m'] ?? 0) ? round($act['actions_5m'] / 5, 1) . ' actions/min' : '0 actions/min',
            'detail' => $web['error'] ?: ('HTTP ' . $web['code'] . ' from ' . $web['url'])]);
        // REST API
        $ast = $api['ok'] === null ? 'unknown' : ($api['ok'] ? ($api['ms'] > 1500 ? 'degraded' : 'operational') : 'down');
        $add('api', 'REST API v1', 'api/v1 · token auth', 'app', $ast, ['latency_ms' => $api['ms'], 'detail' => $api['error'] ?: ('HTTP ' . $api['code'] . ' from ' . $api['url'])]);
        // Database
        $dst = !$db['up'] ? 'down' : ($db['ping_ms'] > 250 ? 'degraded' : 'operational');
        $add('db', 'Database', 'MySQL ' . ($db['version'] ?? ''), 'data', $dst, ['latency_ms' => $db['ping_ms'],
            'throughput' => $db['qps'] !== null ? $db['qps'] . ' queries/s' : null,
            'instances' => isset($db['connections']) ? $db['connections'] . ' / ' . $db['max_connections'] . ' conn' : '1/1',
            'detail' => $db['up'] ? ('Up ' . cdp_devDur($db['uptime_s'] ?? 0) . ' · ' . cdp_devBytes($db['size_bytes'] ?? 0) . ' in ' . ($db['tables'] ?? 0) . ' tables') : ($db['error'] ?? 'Down')]);
        // File storage
        $dirs = ['uploads' => 'uploads', 'support_files' => 'support_files', 'driver_files' => 'driver_files'];
        $bad = [];
        foreach ($dirs as $k => $d) {
            $p = dirname(__DIR__) . '/' . $d;
            if (is_dir($p) && !is_writable($p)) {
                $bad[] = $d;
            }
        }
        $fst = $bad ? 'degraded' : (($srv['disk_pct'] ?? 0) > 95 ? 'down' : (($srv['disk_pct'] ?? 0) > 85 ? 'degraded' : 'operational'));
        $add('storage', 'File storage', 'Local disk', 'data', $fst, ['throughput' => $srv['disk_pct'] !== null ? $srv['disk_pct'] . '% used' : null,
            'detail' => $bad ? 'Not writable: ' . implode(', ', $bad) : cdp_devBytes($srv['disk_total'] ? $srv['disk_total'] - $srv['disk_used'] : 0) . ' free']);
        // Customer Service worker + outbox
        $wl = null;
        $outbox = null;
        if (cdp_devTableExists('cdb_cs_settings')) {
            $r = cdp_devOne("SELECT setting_value FROM cdb_cs_settings WHERE setting_key = 'worker_last_run'");
            $wl = $r ? (string) $r->setting_value : null;
            $wl = $wl ? trim($wl, '"') : null;
        }
        if (cdp_devTableExists('cdb_cs_outbox')) {
            $outbox = cdp_devOne("SELECT SUM(status IN ('queued','retry')) AS q, SUM(status = 'dead') AS d, SUM(status = 'sent' AND created_at >= NOW() - INTERVAL 1 DAY) AS s FROM cdb_cs_outbox");
        }
        $age = $wl ? time() - strtotime($wl) : null;
        $wst = $wl === null ? 'not_configured' : ($age > 3600 ? 'down' : ($age > 900 ? 'degraded' : 'operational'));
        $add('worker', 'Support worker (cron)', 'cron/support_worker.php', 'jobs', $wst, ['detail' => $wl ? 'Last run ' . cdp_devAgo($age) : 'Never ran. Add it to cron (see docs/CUSTOMER_SERVICE.md).']);
        if ($outbox) {
            $q = (int) $outbox->q;
            $d = (int) $outbox->d;
            $add('outbox', 'Delivery queue', 'cdb_cs_outbox', 'jobs', $d > 0 ? 'degraded' : ($q > 200 ? 'degraded' : 'operational'), [
                'throughput' => (int) $outbox->s . ' sent today', 'error_rate' => ($q + $d + (int) $outbox->s) ? round($d / ($q + $d + (int) $outbox->s) * 100, 2) : 0,
                'detail' => $q . ' waiting · ' . $d . ' dead']);
        }
        // Collector heartbeat
        $cl = cdp_devState('collector_last');
        $cage = $cl ? time() - (int) $cl : null;
        $add('collector', 'Metrics collector (cron)', 'cron/devops_collector.php', 'jobs', $cl === null ? 'not_configured' : ($cage > 600 ? 'degraded' : 'operational'), [
            'detail' => $cl ? 'Last sample ' . cdp_devAgo($cage) : 'Not scheduled: history only fills while this page is open.']);
        // AI provider
        $aiOn = false;
        $aiProv = '';
        if (cdp_devTableExists('cdb_ai_settings')) {
            $rows = [];
            foreach (cdp_devQ("SELECT setting_key, setting_value FROM cdb_ai_settings WHERE setting_key IN ('provider','api_key','enabled','model')") as $r) {
                $rows[$r->setting_key] = (string) $r->setting_value;
            }
            $aiOn = !empty($rows['api_key']);
            $aiProv = trim(($rows['provider'] ?? '') . ' ' . ($rows['model'] ?? ''));
        }
        $runs = cdp_devTableExists('cdb_cs_ai_runs') ? cdp_devOne("SELECT COUNT(*) AS n, SUM(outcome LIKE 'error%') AS e, AVG(latency_ms) AS l FROM cdb_cs_ai_runs WHERE created_at >= NOW() - INTERVAL 1 DAY") : null;
        $rn = $runs ? (int) $runs->n : 0;
        $re = $runs ? (int) $runs->e : 0;
        $add('ai', 'AI provider (King)', $aiProv ?: 'Not set up', 'external', !$aiOn ? 'not_configured' : ($rn >= 5 && $re / $rn > 0.2 ? 'degraded' : 'operational'), [
            'latency_ms' => $runs && $runs->l !== null ? round((float) $runs->l) : null, 'error_rate' => $rn ? round($re / $rn * 100, 2) : 0,
            'throughput' => $rn . ' calls today', 'detail' => $aiOn ? $re . ' errors in 24 h' : 'Set the key in Tools › AI Assistant']);
        // Messaging channels
        foreach (['email' => ['E-mail (SMTP)', 'PHPMailer'], 'sms' => ['SMS', 'Hubtel / mNotify / Twilio'], 'whatsapp' => ['WhatsApp', 'WhatsApp API']] as $k => $label) {
            $c = $ch[$k] ?? null;
            $n = $c ? $c['sent'] + $c['failed'] : 0;
            $st = !$c ? 'idle' : ($n >= 5 && $c['failed'] / $n > 0.2 ? 'degraded' : ($n && $c['failed'] === $n ? 'down' : 'operational'));
            $add('ch_' . $k, $label[0], $label[1], 'external', $st, ['error_rate' => $n ? round($c['failed'] / $n * 100, 2) : 0,
                'throughput' => ($c['sent'] ?? 0) . ' sent today', 'detail' => $c ? ($c['failed'] . ' failed · last ' . ($c['last_at'] ?: '–')) : 'Nothing sent in 24 h']);
        }
        // Payment gateways (configuration only; their APIs are not called from here)
        if (cdp_devTableExists('cdb_met_payment')) {
            $pg = cdp_devQ("SELECT * FROM cdb_met_payment");
            $names = [];
            foreach ($pg as $p) {
                $p = (array) $p;
                $active = $p['is_active'] ?? ($p['active'] ?? ($p['status'] ?? 1));
                if ((int) $active === 1) {
                    $names[] = (string) ($p['name_pay'] ?? $p['name'] ?? ('#' . ($p['id'] ?? '')));
                }
            }
            $add('payments', 'Payment gateways', $names ? implode(', ', array_slice($names, 0, 3)) : 'None active', 'external', $names ? 'operational' : 'not_configured', [
                'instances' => count($names) . ' active', 'detail' => 'Configured in Tools › Payment modes']);
        }
        return $s;
    }

    // ---------------------------------------------------------------------
    // Collect: one live snapshot, stored as a sample at most once a minute
    // ---------------------------------------------------------------------

    function cdp_devCollect($store = true)
    {
        cdp_devEnsureTables();
        $base = cdp_devBaseUrl();
        $srv = cdp_devServer();
        $db = cdp_devDb();
        $web = cdp_devProbe('web', $base . 'login.php');
        $api = cdp_devProbe('api', $base . 'api/v1/', 500);
        $act = cdp_devActivity();
        $ch = cdp_devChannels();
        $services = cdp_devServices($srv, $db, $web, $api, $act, $ch);
        $errRate = ($act['actions_5m'] ?? 0) ? round(($act['failed_5m'] ?? 0) / $act['actions_5m'] * 100, 3) : 0;

        if ($store) {
            $last = cdp_devOne("SELECT ts FROM cdb_dev_metrics ORDER BY id DESC LIMIT 1");
            if (!$last || time() - strtotime($last->ts) >= 55) {
                cdp_devX("INSERT INTO cdb_dev_metrics (ts, cpu_pct, load1, mem_pct, disk_pct, db_ms, db_up, web_ms, web_ok, api_ms, actions_pm, errors_pm, err_rate,
                        net_rx_bps, net_tx_bps, disk_r_bps, disk_w_bps, db_qps)
                    VALUES (:ts, :cpu, :l1, :mem, :disk, :dbms, :dbup, :wms, :wok, :ams, :apm, :epm, :er, :rx, :tx, :dr, :dw, :qps)", [
                    ':ts' => cdp_devNow(), ':cpu' => $srv['cpu_pct'], ':l1' => $srv['load'][0] ?? null, ':mem' => $srv['mem_pct'], ':disk' => $srv['disk_pct'],
                    ':dbms' => $db['ping_ms'], ':dbup' => $db['up'] ? 1 : 0, ':wms' => $web['ms'], ':wok' => $web['ok'] === null ? null : ($web['ok'] ? 1 : 0),
                    ':ams' => $api['ms'], ':apm' => $act['actions_1m'], ':epm' => $act['failed_5m'] !== null ? (int) round($act['failed_5m'] / 5) : null,
                    ':er' => $errRate, ':rx' => $srv['net_rx_bps'], ':tx' => $srv['net_tx_bps'], ':dr' => $srv['disk_r_bps'], ':dw' => $srv['disk_w_bps'], ':qps' => $db['qps'],
                ]);
                // Keep 35 days.
                if (mt_rand(1, 50) === 1) {
                    cdp_devX("DELETE FROM cdb_dev_metrics WHERE ts < NOW() - INTERVAL 35 DAY");
                }
            }
            cdp_devEvaluate($srv, $db, $services, $act);
            cdp_devDetectDeploy();
        }
        return ['at' => cdp_devNow(), 'server' => $srv, 'db' => $db, 'web' => $web, 'api' => $api, 'activity' => $act, 'channels' => $ch,
            'services' => $services, 'error_rate' => $errRate];
    }

    /** Uptime share: samples where the site answered (or, with no probe, the database was up). */
    function cdp_devAvailability($days = 30)
    {
        $r = cdp_devOne("SELECT COUNT(*) AS n, SUM(COALESCE(web_ok, db_up)) AS ok FROM cdb_dev_metrics WHERE ts >= NOW() - INTERVAL :d DAY", [':d' => (int) $days]);
        return ($r && (int) $r->n) ? round((int) $r->ok / (int) $r->n * 100, 3) : null;
    }

    /** Bucketed history for charts. range: 1h, 24h, 7d, 30d. */
    function cdp_devSeries($range = '24h')
    {
        $map = ['1h' => [1 / 24, 60], '24h' => [1, 300], '7d' => [7, 3600], '30d' => [30, 21600]];
        list($days, $bucket) = $map[$range] ?? $map['24h'];
        $rows = cdp_devQ("SELECT FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(ts) / $bucket) * $bucket) AS t,
                AVG(cpu_pct) AS cpu, AVG(mem_pct) AS mem, AVG(disk_pct) AS disk, AVG(db_ms) AS db, AVG(web_ms) AS web, AVG(api_ms) AS api,
                AVG(err_rate) AS err, AVG(actions_pm) AS apm, AVG(net_rx_bps) AS rx, AVG(net_tx_bps) AS tx, AVG(disk_r_bps) AS dr, AVG(disk_w_bps) AS dw,
                AVG(db_qps) AS qps, MIN(COALESCE(web_ok, db_up)) AS ok, COUNT(*) AS n
            FROM cdb_dev_metrics WHERE ts >= NOW() - INTERVAL " . (int) round($days * 1440) . " MINUTE
            GROUP BY FLOOR(UNIX_TIMESTAMP(ts) / $bucket) ORDER BY t");
        $f = function ($v, $d = 2) { return $v === null ? null : round((float) $v, $d); };
        return array_map(function ($r) use ($f) {
            return ['t' => (string) $r->t, 'cpu' => $f($r->cpu, 1), 'mem' => $f($r->mem, 1), 'disk' => $f($r->disk, 1), 'db' => $f($r->db), 'web' => $f($r->web, 1),
                'api' => $f($r->api, 1), 'err' => $f($r->err, 3), 'apm' => $f($r->apm, 1), 'rx' => $f($r->rx, 0), 'tx' => $f($r->tx, 0), 'dr' => $f($r->dr, 0),
                'dw' => $f($r->dw, 0), 'qps' => $f($r->qps), 'ok' => $r->ok === null ? null : (int) $r->ok];
        }, $rows);
    }

    // ---------------------------------------------------------------------
    // Alerts: evaluated on every sample, open and close themselves
    // ---------------------------------------------------------------------

    function cdp_devEvaluate(array $srv, array $db, array $services, array $act)
    {
        $want = [];
        $raise = function ($key, $sev, $service, $title, $detail, array $ctx = []) use (&$want) {
            $want[$key] = compact('sev', 'service', 'title', 'detail', 'ctx');
        };
        if (($srv['disk_pct'] ?? 0) >= 95) {
            $raise('disk', 'critical', 'File storage', 'Disk almost full', $srv['disk_pct'] . '% used on the app volume', ['free' => cdp_devBytes($srv['disk_total'] - $srv['disk_used'])]);
        } elseif (($srv['disk_pct'] ?? 0) >= 85) {
            $raise('disk', 'warning', 'File storage', 'Disk space running low', $srv['disk_pct'] . '% used on the app volume', ['free' => cdp_devBytes($srv['disk_total'] - $srv['disk_used'])]);
        }
        if (($srv['mem_pct'] ?? 0) >= 92) {
            $raise('mem', 'warning', 'Server', 'High memory usage', $srv['mem_pct'] . '% of ' . cdp_devBytes($srv['mem_total']) . ' in use');
        }
        if ($srv['load'] && $srv['load'][1] / max(1, $srv['cores']) >= 2) {
            $raise('load', 'critical', 'Server', 'Server overloaded', '5-minute load ' . $srv['load'][1] . ' on ' . $srv['cores'] . ' cores');
        } elseif (($srv['cpu_pct'] ?? 0) >= 90) {
            $raise('load', 'warning', 'Server', 'High CPU usage', $srv['cpu_pct'] . '% CPU');
        }
        if (!$db['up']) {
            $raise('db_down', 'critical', 'Database', 'Database unreachable', $db['error'] ?? 'No answer');
        } elseif ($db['ping_ms'] > 250) {
            $raise('db_slow', 'warning', 'Database', 'Slow database', 'Ping ' . $db['ping_ms'] . ' ms');
        }
        foreach ($services as $s) {
            if ($s['key'] === 'db' || $s['key'] === 'storage') {
                continue;
            }
            if ($s['status'] === 'down') {
                $raise('svc_' . $s['key'], 'critical', $s['name'], $s['name'] . ' is down', $s['detail'], ['latency_ms' => $s['latency_ms']]);
            } elseif ($s['status'] === 'degraded') {
                $raise('svc_' . $s['key'], 'warning', $s['name'], $s['name'] . ' degraded', $s['detail'], ['latency_ms' => $s['latency_ms'], 'error_rate' => $s['error_rate']]);
            }
        }
        if (($act['actions_5m'] ?? 0) >= 10 && ($act['failed_5m'] / $act['actions_5m']) > 0.1) {
            $raise('errors', 'warning', 'Web application', 'High error rate', $act['failed_5m'] . ' of ' . $act['actions_5m'] . ' actions failed in 5 minutes');
        }
        $now = cdp_devNow();
        $open = [];
        foreach (cdp_devQ("SELECT id, alert_key, severity FROM cdb_dev_alerts WHERE status IN ('active','acknowledged')") as $a) {
            $open[$a->alert_key] = $a;
        }
        foreach ($want as $key => $w) {
            if (isset($open[$key])) {
                cdp_devX("UPDATE cdb_dev_alerts SET last_seen = :n, severity = :s, title = :t, detail = :d, context = :c WHERE id = :i",
                    [':n' => $now, ':s' => $w['sev'], ':t' => $w['title'], ':d' => mb_substr($w['detail'], 0, 500), ':c' => json_encode($w['ctx']), ':i' => $open[$key]->id]);
            } else {
                cdp_devX("INSERT INTO cdb_dev_alerts (alert_key, severity, service, title, detail, context, status, first_seen, last_seen)
                    VALUES (:k, :s, :sv, :t, :d, :c, 'active', :n, :n)",
                    [':k' => $key, ':s' => $w['sev'], ':sv' => $w['service'], ':t' => $w['title'], ':d' => mb_substr($w['detail'], 0, 500), ':c' => json_encode($w['ctx']), ':n' => $now]);
            }
        }
        foreach ($open as $key => $a) {
            if (!isset($want[$key])) {
                cdp_devX("UPDATE cdb_dev_alerts SET status = 'resolved', resolved_at = :n WHERE id = :i", [':n' => $now, ':i' => $a->id]);
            }
        }
    }

    function cdp_devAlerts($status = 'all', $limit = 100)
    {
        $w = $status === 'open' ? "WHERE status IN ('active','acknowledged')" : ($status === 'resolved' ? "WHERE status = 'resolved'" : '');
        return array_map(function ($a) {
            return ['id' => (int) $a->id, 'key' => $a->alert_key, 'severity' => $a->severity, 'service' => $a->service, 'title' => $a->title, 'detail' => $a->detail,
                'context' => json_decode((string) $a->context, true) ?: [], 'status' => $a->status, 'first_seen' => $a->first_seen, 'last_seen' => $a->last_seen,
                'resolved_at' => $a->resolved_at, 'ack_by' => $a->ack_by ? (int) $a->ack_by : null, 'ack_at' => $a->ack_at,
                'duration_s' => max(0, strtotime($a->resolved_at ?: cdp_devNow()) - strtotime($a->first_seen))];
        }, cdp_devQ("SELECT * FROM cdb_dev_alerts $w ORDER BY FIELD(status,'active','acknowledged','resolved'), FIELD(severity,'critical','warning','info'), last_seen DESC LIMIT " . (int) $limit));
    }

    function cdp_devAlertAck($id, $uid)
    {
        return cdp_devX("UPDATE cdb_dev_alerts SET status = 'acknowledged', ack_by = :u, ack_at = :n WHERE id = :i AND status = 'active'",
            [':u' => (int) $uid, ':n' => cdp_devNow(), ':i' => (int) $id]);
    }

    function cdp_devAlertResolve($id)
    {
        return cdp_devX("UPDATE cdb_dev_alerts SET status = 'resolved', resolved_at = :n WHERE id = :i AND status <> 'resolved'", [':n' => cdp_devNow(), ':i' => (int) $id]);
    }

    // ---------------------------------------------------------------------
    // Deployments: the git reflog of this checkout, plus changes we saw
    // ---------------------------------------------------------------------

    function cdp_devGitDir()
    {
        $d = dirname(__DIR__) . '/.git';
        return is_dir($d) ? $d : null;
    }

    function cdp_devGitHead()
    {
        $g = cdp_devGitDir();
        if (!$g) {
            return null;
        }
        $head = trim(cdp_devRead($g . '/HEAD'));
        $branch = null;
        $hash = $head;
        if (strpos($head, 'ref:') === 0) {
            $ref = trim(substr($head, 4));
            $branch = preg_replace('#^refs/heads/#', '', $ref);
            $hash = trim(cdp_devRead($g . '/' . $ref));
            if ($hash === '' && is_readable($g . '/packed-refs')) {
                foreach (explode("\n", cdp_devRead($g . '/packed-refs')) as $l) {
                    if (substr($l, -strlen($ref)) === $ref) {
                        $hash = substr($l, 0, 40);
                    }
                }
            }
        }
        return ['branch' => $branch, 'hash' => $hash];
    }

    /** Reflog entries, newest first: what changed the running code and when. */
    function cdp_devGitLog($limit = 60)
    {
        $g = cdp_devGitDir();
        if (!$g) {
            return [];
        }
        $lines = array_filter(explode("\n", cdp_devRead($g . '/logs/HEAD')));
        $lines = array_slice($lines, -400);
        $out = [];
        foreach (array_reverse($lines) as $l) {
            if (!preg_match('/^([0-9a-f]{40}) ([0-9a-f]{40}) (.*?) <([^>]*)> (\d+) ([+-]\d{4})\t(.*)$/', $l, $m)) {
                continue;
            }
            $msg = $m[7];
            $kind = strtolower(trim(strtok($msg, ':( ')));
            if (!in_array($kind, ['commit', 'pull', 'merge', 'reset', 'checkout', 'clone', 'rebase', 'cherry-pick', 'commit (merge)', 'commit (amend)'], true)) {
                $kind = 'update';
            }
            if ($kind === 'checkout' && $m[1] === $m[2]) {
                continue;
            }
            $out[] = ['from' => substr($m[1], 0, 7), 'hash' => substr($m[2], 0, 7), 'full' => $m[2], 'author' => $m[3], 'ts' => date('Y-m-d H:i:s', (int) $m[5]),
                'kind' => $kind, 'message' => trim(preg_replace('/^[^:]+:\s*/', '', $msg))];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /** Records a new running commit once, the first time we see it. */
    function cdp_devDetectDeploy()
    {
        $h = cdp_devGitHead();
        if (!$h || !$h['hash']) {
            return;
        }
        $known = cdp_devState('deploy_head');
        if ($known === $h['hash']) {
            return;
        }
        $log = cdp_devGitLog(1);
        cdp_devX("INSERT INTO cdb_dev_deploys (commit_hash, branch, message, author, committed_at, detected_at, source) VALUES (:h, :b, :m, :a, :c, :n, 'detected')", [
            ':h' => $h['hash'], ':b' => $h['branch'], ':m' => mb_substr($log[0]['message'] ?? '', 0, 255), ':a' => mb_substr($log[0]['author'] ?? '', 0, 120),
            ':c' => $log[0]['ts'] ?? null, ':n' => cdp_devNow(),
        ]);
        cdp_devStateSet('deploy_head', $h['hash']);
    }

    /** Deploy history and the pipeline checks for the running version. */
    function cdp_devDeployments(array $services = [])
    {
        $head = cdp_devGitHead();
        $log = cdp_devGitLog(60);
        $root = dirname(__DIR__);
        // Tests: PHPUnit's result cache holds failures ("defects") of the last run.
        $tests = ['state' => 'unknown', 'detail' => 'No test run recorded', 'at' => null];
        $cache = $root . '/.phpunit.result.cache';
        if (is_readable($cache)) {
            $j = json_decode(cdp_devRead($cache), true);
            $defects = is_array($j['defects'] ?? null) ? array_filter($j['defects'], function ($v) { return (int) $v > 1; }) : [];
            $tests = ['state' => $defects ? 'failed' : 'passed', 'detail' => $defects ? count($defects) . ' failing tests' : count($j['times'] ?? []) . ' tests passed',
                'at' => date('Y-m-d H:i:s', filemtime($cache))];
        }
        $deps = ['state' => 'unknown', 'detail' => 'No composer.lock', 'at' => null];
        if (is_readable($root . '/composer.lock')) {
            $vendor = is_readable($root . '/vendor/autoload.php') ? $root . '/vendor/autoload.php' : (is_readable($root . '/helpers/vendor/autoload.php') ? $root . '/helpers/vendor/autoload.php' : null);
            $deps = $vendor ? ['state' => filemtime($vendor) >= filemtime($root . '/composer.lock') - 5 ? 'passed' : 'warning',
                'detail' => filemtime($vendor) >= filemtime($root . '/composer.lock') - 5 ? 'Dependencies installed' : 'composer.lock is newer than vendor/: run composer install',
                'at' => date('Y-m-d H:i:s', filemtime($vendor))] : ['state' => 'warning', 'detail' => 'vendor/ missing', 'at' => null];
        }
        $down = array_filter($services, function ($s) { return $s['status'] === 'down'; });
        $deg = array_filter($services, function ($s) { return $s['status'] === 'degraded'; });
        $health = ['state' => $down ? 'failed' : ($deg ? 'warning' : 'passed'),
            'detail' => $down ? count($down) . ' services down' : ($deg ? count($deg) . ' services degraded' : 'All services healthy'), 'at' => cdp_devNow()];
        $latest = $log[0] ?? null;
        $pipeline = [
            ['name' => 'Code', 'state' => $latest ? 'passed' : 'unknown', 'detail' => $latest ? $latest['hash'] . ' · ' . $latest['kind'] : 'No git history', 'at' => $latest['ts'] ?? null],
            ['name' => 'Tests', 'state' => $tests['state'], 'detail' => $tests['detail'], 'at' => $tests['at']],
            ['name' => 'Dependencies', 'state' => $deps['state'], 'detail' => $deps['detail'], 'at' => $deps['at']],
            ['name' => 'Deploy', 'state' => $head && $head['hash'] ? 'passed' : 'unknown', 'detail' => $head ? (($head['branch'] ?: 'detached') . ' @ ' . substr((string) $head['hash'], 0, 7)) : 'No git metadata', 'at' => $latest['ts'] ?? null],
            ['name' => 'Health check', 'state' => $health['state'], 'detail' => $health['detail'], 'at' => $health['at']],
        ];
        $detected = array_map(function ($d) {
            return ['hash' => substr($d->commit_hash, 0, 7), 'branch' => $d->branch, 'message' => $d->message, 'author' => $d->author, 'committed_at' => $d->committed_at, 'detected_at' => $d->detected_at];
        }, cdp_devQ("SELECT * FROM cdb_dev_deploys ORDER BY id DESC LIMIT 30"));
        $since = strtotime('-30 days');
        $recent = array_filter($log, function ($e) use ($since) { return strtotime($e['ts']) >= $since; });
        $resets = array_filter($recent, function ($e) { return $e['kind'] === 'reset'; });
        return ['head' => $head, 'history' => $log, 'detected' => $detected, 'pipeline' => $pipeline,
            'stats' => ['total_30d' => count($recent), 'rollbacks_30d' => count($resets),
                'success_rate' => count($recent) ? round((count($recent) - count($resets)) / count($recent) * 100, 1) : null,
                'last_at' => $latest['ts'] ?? null]];
    }

    // ---------------------------------------------------------------------
    // Logs: one stream from every source, newest first
    // ---------------------------------------------------------------------

    function cdp_devLogs(array $f)
    {
        $since = !empty($f['since']) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $f['since']) ? $f['since'] : date('Y-m-d H:i:s', time() - 60 * max(1, min(10080, (int) ($f['window'] ?? 60))));
        $limit = max(20, min(500, (int) ($f['limit'] ?? 200)));
        $src = $f['source'] ?? '';
        $q = trim((string) ($f['q'] ?? ''));
        $out = [];
        $like = $q !== '' ? '%' . $q . '%' : null;
        if ((!$src || $src === 'activity') && cdp_devTableExists('cdb_activity_log')) {
            $w = 'created_at > :s';
            $b = [':s' => $since];
            if ($like) {
                $w .= ' AND (summary LIKE :q OR action_label LIKE :q2 OR actor_name LIKE :q3 OR entity_label LIKE :q4)';
                $b += [':q' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
            }
            foreach (cdp_devQ("SELECT id, created_at, module, action, action_label, outcome, summary, actor_name, role_name, entity_type, entity_id, entity_label, method, endpoint, ip
                    FROM cdb_activity_log WHERE $w ORDER BY id DESC LIMIT $limit", $b) as $r) {
                $lvl = $r->outcome === 'success' ? 'INFO' : ($r->outcome === 'denied' ? 'WARN' : 'ERROR');
                $out[] = ['id' => 'a' . $r->id, 'ts' => $r->created_at, 'source' => 'activity', 'service' => ucfirst((string) $r->module), 'level' => $lvl,
                    'message' => trim(($r->summary ?: $r->action_label) . ($r->actor_name ? ' · ' . $r->actor_name : '')),
                    'detail' => ['action' => $r->action, 'outcome' => $r->outcome, 'actor' => $r->actor_name, 'role' => $r->role_name,
                        'entity' => trim($r->entity_type . ' ' . $r->entity_id . ' ' . $r->entity_label), 'request' => trim($r->method . ' ' . $r->endpoint), 'ip' => $r->ip]];
            }
        }
        if ((!$src || $src === 'messages') && cdp_devTableExists('cdb_message_log')) {
            $w = 'created_at > :s';
            $b = [':s' => $since];
            if ($like) {
                $w .= ' AND (subject LIKE :q OR recipient_to LIKE :q2 OR status_detail LIKE :q3)';
                $b += [':q' => $like, ':q2' => $like, ':q3' => $like];
            }
            foreach (cdp_devQ("SELECT id, created_at, channel, source_label, subject, recipient_name, recipient_to, status, status_detail, sent_by_name
                    FROM cdb_message_log WHERE $w ORDER BY id DESC LIMIT $limit", $b) as $r) {
                $lvl = $r->status === 'sent' ? 'INFO' : ($r->status === 'skipped' ? 'WARN' : 'ERROR');
                $out[] = ['id' => 'm' . $r->id, 'ts' => $r->created_at, 'source' => 'messages', 'service' => strtoupper((string) $r->channel) === 'EMAIL' ? 'E-mail' : ucfirst((string) $r->channel), 'level' => $lvl,
                    'message' => ucfirst((string) $r->status) . ': ' . ($r->subject ?: $r->source_label) . ' → ' . ($r->recipient_name ?: $r->recipient_to),
                    'detail' => ['channel' => $r->channel, 'to' => $r->recipient_to, 'status' => $r->status, 'status_detail' => $r->status_detail, 'source' => $r->source_label, 'sent_by' => $r->sent_by_name]];
            }
        }
        if ((!$src || $src === 'ai') && cdp_devTableExists('cdb_cs_ai_runs')) {
            $w = 'created_at > :s';
            $b = [':s' => $since];
            if ($like) {
                $w .= ' AND (detail LIKE :q OR kind LIKE :q2 OR model LIKE :q3)';
                $b += [':q' => $like, ':q2' => $like, ':q3' => $like];
            }
            foreach (cdp_devQ("SELECT id, created_at, kind, provider, model, outcome, detail, latency_ms, tokens_in, tokens_out, ticket_id, conversation_id
                    FROM cdb_cs_ai_runs WHERE $w ORDER BY id DESC LIMIT $limit", $b) as $r) {
                $lvl = strpos((string) $r->outcome, 'error') === 0 ? 'ERROR' : ($r->outcome === 'discarded' ? 'WARN' : 'INFO');
                $out[] = ['id' => 'k' . $r->id, 'ts' => $r->created_at, 'source' => 'ai', 'service' => 'King AI', 'level' => $lvl,
                    'message' => ucfirst((string) $r->kind) . ' ' . $r->outcome . ($r->latency_ms ? ' in ' . $r->latency_ms . ' ms' : ''),
                    'detail' => ['provider' => $r->provider, 'model' => $r->model, 'outcome' => $r->outcome, 'detail' => $r->detail, 'latency_ms' => $r->latency_ms,
                        'tokens' => (int) $r->tokens_in + (int) $r->tokens_out, 'ticket_id' => $r->ticket_id, 'conversation_id' => $r->conversation_id]];
            }
        }
        if (!$src || $src === 'php') {
            foreach (cdp_devPhpErrors($since, $q, $limit) as $e) {
                $out[] = $e;
            }
        }
        if (!$src || $src === 'alerts') {
            foreach (cdp_devQ("SELECT * FROM cdb_dev_alerts WHERE last_seen > :s ORDER BY id DESC LIMIT 50", [':s' => $since]) as $a) {
                if ($q !== '' && stripos($a->title . ' ' . $a->detail, $q) === false) {
                    continue;
                }
                $out[] = ['id' => 'x' . $a->id, 'ts' => $a->status === 'resolved' && $a->resolved_at ? $a->resolved_at : $a->first_seen, 'source' => 'alerts', 'service' => $a->service,
                    'level' => $a->status === 'resolved' ? 'INFO' : ($a->severity === 'critical' ? 'ERROR' : 'WARN'),
                    'message' => ($a->status === 'resolved' ? 'Resolved: ' : 'Alert: ') . $a->title . ' · ' . $a->detail, 'detail' => ['severity' => $a->severity, 'status' => $a->status, 'first_seen' => $a->first_seen]];
            }
        }
        if (!empty($f['level'])) {
            $lv = strtoupper($f['level']);
            $out = array_values(array_filter($out, function ($e) use ($lv) { return $e['level'] === $lv; }));
        }
        usort($out, function ($a, $b) { return strcmp($b['ts'], $a['ts']); });
        return array_slice($out, 0, $limit);
    }

    /** Tail of the PHP error log, when PHP writes to a file we can read. */
    function cdp_devPhpErrors($since, $q = '', $limit = 100)
    {
        $file = ini_get('error_log');
        if (!$file || !@is_readable($file) || !@is_file($file)) {
            return [];
        }
        $size = filesize($file);
        $fh = @fopen($file, 'r');
        if (!$fh) {
            return [];
        }
        fseek($fh, max(0, $size - 131072));
        $buf = stream_get_contents($fh);
        fclose($fh);
        $out = [];
        $sinceT = strtotime($since);
        $i = 0;
        foreach (array_reverse(explode("\n", $buf)) as $l) {
            if (!preg_match('/^\[([^\]]+)\]\s+(?:PHP\s+)?(Fatal error|Parse error|Warning|Notice|Deprecated|[A-Za-z ]+error)?:?\s*(.*)$/i', $l, $m)) {
                continue;
            }
            $t = strtotime($m[1]);
            if (!$t || $t <= $sinceT) {
                continue;
            }
            if ($q !== '' && stripos($l, $q) === false) {
                continue;
            }
            $type = strtolower($m[2] ?? '');
            $lvl = (strpos($type, 'fatal') !== false || strpos($type, 'parse') !== false || strpos($type, 'error') !== false) ? 'ERROR' : (($type === 'warning') ? 'WARN' : 'INFO');
            $out[] = ['id' => 'p' . $t . '_' . (++$i), 'ts' => date('Y-m-d H:i:s', $t), 'source' => 'php', 'service' => 'PHP', 'level' => $lvl,
                'message' => mb_substr(trim($m[3]), 0, 300), 'detail' => ['type' => $m[2] ?: 'log', 'line' => mb_substr($l, 0, 2000), 'file' => basename($file)]];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    // ---------------------------------------------------------------------
    // Formatting
    // ---------------------------------------------------------------------

    function cdp_devBytes($b)
    {
        $b = (float) $b;
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($b >= 1024 && $i < 4) {
            $b /= 1024;
            $i++;
        }
        return round($b, $b >= 100 ? 0 : 1) . ' ' . $u[$i];
    }

    function cdp_devDur($s)
    {
        $s = (int) $s;
        if ($s < 3600) {
            return max(1, (int) floor($s / 60)) . ' min';
        }
        if ($s < 86400) {
            return floor($s / 3600) . ' h ' . floor(($s % 3600) / 60) . ' min';
        }
        return floor($s / 86400) . ' d ' . floor(($s % 86400) / 3600) . ' h';
    }

    function cdp_devAgo($s)
    {
        return $s === null ? 'never' : ($s < 60 ? $s . ' s ago' : cdp_devDur($s) . ' ago');
    }
}
