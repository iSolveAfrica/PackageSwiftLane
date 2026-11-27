<?php
/**
 * Request metrics for the developer console (Overview, Services, Logs, Metrics).
 *
 * Recorder: loader.php calls cdp_reqmArm() once per web request. A shutdown function then keeps
 * a sample of the request in cdb_dev_requests: script path (never the query string), method,
 * status code, duration, peak memory, plus the host's 1-minute load and memory use at that moment.
 *   - every 5xx, PHP fatal error and slow request (>= CDP_REQ_METRICS_SLOW_MS, default 1000) is kept with weight 1;
 *   - any other request is kept with probability CDP_REQ_METRICS_RATE (default 0.5) and weight round(1 / rate),
 *     so SUM(w) is an unbiased estimate of the real number of requests.
 * Switch it off with define('CDP_REQ_METRICS', false) in config/config.php. It never throws and
 * never prints: a missing table is created on first use, any other failure is silently dropped.
 *
 * Rollups: finished days (in the application's time zone) are summarised into cdb_dev_request_daily
 * (per route plus a '*' row) and raw rows older than CDP_REQ_METRICS_DAYS (default 8) are deleted.
 * This runs after the response on about 1 request in 300 (flushed first under PHP-FPM) and whenever
 * a reader needs a day that is not rolled up yet. If the table cannot be created (no CREATE privilege
 * before the migration is applied) the recorder pauses for an hour instead of retrying every request.
 *
 * Readers (cdp_reqm*) are used by views/sl/data/dev_observe.php and ajax/sl/dev_observe_ajax.php.
 * Rows are stamped in the application's zone (cdp_reqmTz) and every bound is formatted in that zone
 * (cdp_reqmFmt), whatever PHP's default zone or MySQL's zone is. Readers aggregate in SQL.
 */

if (!function_exists('cdp_reqmArm')) {

    // -----------------------------------------------------------------------------------------
    // Recorder
    // -----------------------------------------------------------------------------------------

    /** Arm the recorder for this request (web only). Safe to call more than once. */
    function cdp_reqmArm(): void
    {
        static $armed = false;
        if ($armed || PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return;
        }
        if (defined('CDP_REQ_METRICS') && !CDP_REQ_METRICS) {
            return;
        }
        $armed = true;
        $GLOBALS['cdp_reqm'] = ['t0' => (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)), 'skip' => false];
        register_shutdown_function('cdp_reqmRecord');
    }

    /** Leave this request out of the metrics (the console's own live polling). */
    function cdp_reqmSkip(bool $skip = true): void
    {
        if (isset($GLOBALS['cdp_reqm'])) {
            $GLOBALS['cdp_reqm']['skip'] = $skip;
        }
    }

    /** Sampling rate between 0.01 and 1. */
    function cdp_reqmRate(): float
    {
        $r = defined('CDP_REQ_METRICS_RATE') ? (float) CDP_REQ_METRICS_RATE : 0.5;
        return max(0.01, min(1.0, $r));
    }

    function cdp_reqmSlowMs(): int
    {
        return defined('CDP_REQ_METRICS_SLOW_MS') ? max(100, (int) CDP_REQ_METRICS_SLOW_MS) : 1000;
    }

    function cdp_reqmRetentionDays(): int
    {
        return defined('CDP_REQ_METRICS_DAYS') ? max(2, min(60, (int) CDP_REQ_METRICS_DAYS)) : 8;
    }

    /** Script path relative to the application root, e.g. "dev_overview.php" or "ajax/sl/x.php". */
    function cdp_reqmRoute(): string
    {
        $file = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
        $root = defined('CDP_ROOT') ? rtrim(str_replace('\\', '/', (string) realpath(CDP_ROOT)), '/') : '';
        $real = $file !== '' ? str_replace('\\', '/', (string) (realpath($file) ?: $file)) : '';
        if ($root !== '' && $real !== '' && strpos($real, $root . '/') === 0) {
            $route = substr($real, strlen($root) + 1);
        } else {
            $route = ltrim((string) parse_url((string) ($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH), '/');
        }
        $route = preg_replace('/[^A-Za-z0-9_.\/-]/', '', $route) ?: 'unknown';
        return substr($route, 0, 120);
    }

    /** Host memory in use (%), from /proc/meminfo when readable. */
    function cdp_reqmMemPct(): ?float
    {
        $s = @is_readable('/proc/meminfo') ? (string) @file_get_contents('/proc/meminfo') : '';
        if ($s === '' || !preg_match('/^MemTotal:\s+(\d+)/m', $s, $t)) {
            return null;
        }
        if (preg_match('/^MemAvailable:\s+(\d+)/m', $s, $a)) {
            $avail = (float) $a[1];
        } elseif (preg_match('/^MemFree:\s+(\d+)/m', $s, $f)) {
            $avail = (float) $f[1] + (preg_match('/^Cached:\s+(\d+)/m', $s, $c) ? (float) $c[1] : 0);
        } else {
            return null;
        }
        return (float) $t[1] > 0 ? round(((float) $t[1] - $avail) / (float) $t[1] * 100, 1) : null;
    }

    /** Shared PDO handle of the app, or null. Swallows anything Conexion would print. */
    function cdp_reqmPdo(): ?PDO
    {
        if (!class_exists('Conexion')) {
            return null;
        }
        ob_start();
        try {
            $c = new Conexion();
            $pdo = $c->dbh instanceof PDO ? $c->dbh : null;
        } catch (Throwable $e) {
            $pdo = null;
        }
        ob_end_clean();
        return $pdo;
    }

    /**
     * The application's time zone (cdb_settings.timezone, applied by Core). Requests that never build
     * Core (most AJAX endpoints) would otherwise be stamped in PHP's default zone, so the recorder
     * always stamps in this zone and the rows line up with the activity and message logs.
     */
    function cdp_reqmTz(?PDO $pdo = null): DateTimeZone
    {
        static $tz = null;
        if ($tz !== null) {
            return $tz;
        }
        $name = '';
        try {
            $pdo = $pdo ?: cdp_reqmPdo();
            $st = $pdo ? $pdo->query('SELECT timezone FROM cdb_settings LIMIT 1') : false;
            $name = $st ? (string) $st->fetchColumn() : '';
        } catch (Throwable $e) {
            $name = '';
        }
        try {
            $tz = new DateTimeZone($name !== '' ? $name : date_default_timezone_get());
        } catch (Throwable $e) {
            $tz = new DateTimeZone(date_default_timezone_get());
        }
        return $tz;
    }

    /** Shutdown function: keep a sample of this request. */
    function cdp_reqmRecord(): void
    {
        $st = $GLOBALS['cdp_reqm'] ?? null;
        if (!$st || !empty($st['skip'])) {
            return;
        }
        try {
            $ms = (int) max(0, round((microtime(true) - (float) $st['t0']) * 1000));
            $status = (int) (http_response_code() ?: 200);
            $fatal = 0;
            $err = error_get_last();
            if ($err && in_array((int) $err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
                $fatal = 1;
                if ($status < 500) {
                    $status = 500;
                }
            }
            $rate = cdp_reqmRate();
            $always = $status >= 500 || $ms >= cdp_reqmSlowMs();
            if (!$always && $rate < 1.0 && mt_rand() / mt_getrandmax() >= $rate) {
                return;
            }
            $w = $always ? 1 : max(1, (int) round(1 / $rate));
            $route = cdp_reqmRoute();
            $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
            $kind = strpos($route, 'api/') === 0 ? 'api' : (strpos($route, 'ajax/') === 0 || $xhr ? 'ajax' : 'page');
            $method = substr(preg_replace('/[^A-Z]/', '', strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'))), 0, 7) ?: 'GET';
            if (cdp_reqmIsOff()) {
                return; // the table could not be created within the last hour: do not retry on every request
            }
            $load = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;
            $pdo = cdp_reqmPdo();
            if (!$pdo) {
                return;
            }
            $tz = cdp_reqmTz($pdo);
            $sql = 'INSERT INTO cdb_dev_requests (ts, route, method, status, ms, mem_kb, w, kind, fatal, load1, mem_pct) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $args = [(new DateTime('now', $tz))->format('Y-m-d H:i:s'), $route, $method, min(65535, $status), $ms, (int) round(memory_get_peak_usage(true) / 1024), min(65535, $w), $kind, $fatal,
                is_array($load) ? round((float) $load[0], 2) : null, cdp_reqmMemPct()];
            $stmt = $pdo->prepare($sql);
            $ok = $stmt && $stmt->execute($args);
            if (!$ok) {
                if (cdp_reqmEnsure($pdo)) {
                    $stmt = $pdo->prepare($sql);
                    $ok = $stmt && $stmt->execute($args);
                } else {
                    // No CREATE privilege (shared hosting before the migration is applied): pause for an hour.
                    @touch(cdp_reqmOffFile());
                }
            }
            if ($ok && mt_rand(1, 300) === 1) {
                // registered from a shutdown function, so it runs after every other one; under PHP-FPM the
                // response is flushed to the client first (fastcgi_finish_request)
                register_shutdown_function('cdp_reqmMaintainLate');
            }
        } catch (Throwable $e) {
            // Metrics must never break a request.
        }
    }

    /** Create both tables (same DDL as migrations/2026_11_26_dev_request_metrics.sql). */
    function cdp_reqmEnsure(?PDO $pdo = null): bool
    {
        static $done = null;
        if ($done !== null) {
            return $done;
        }
        $pdo = $pdo ?: cdp_reqmPdo();
        if (!$pdo) {
            return $done = false;
        }
        $mode = $pdo->getAttribute(PDO::ATTR_ERRMODE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $t = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $a = $pdo->exec("CREATE TABLE IF NOT EXISTS cdb_dev_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ts DATETIME NOT NULL, route VARCHAR(120) NOT NULL, method VARCHAR(7) NOT NULL DEFAULT 'GET',
            status SMALLINT UNSIGNED NOT NULL DEFAULT 200, ms INT UNSIGNED NOT NULL DEFAULT 0, mem_kb INT UNSIGNED NULL, w SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            kind VARCHAR(8) NOT NULL DEFAULT 'page', fatal TINYINT NOT NULL DEFAULT 0, load1 DECIMAL(6,2) NULL, mem_pct DECIMAL(5,1) NULL,
            PRIMARY KEY (id), KEY idx_ts (ts), KEY idx_route_ts (route, ts))$t");
        $b = $pdo->exec("CREATE TABLE IF NOT EXISTS cdb_dev_request_daily (
            day DATE NOT NULL, route VARCHAR(120) NOT NULL, n INT UNSIGNED NOT NULL DEFAULT 0, samples INT UNSIGNED NOT NULL DEFAULT 0,
            err5 INT UNSIGNED NOT NULL DEFAULT 0, err4 INT UNSIGNED NOT NULL DEFAULT 0, sum_ms BIGINT UNSIGNED NOT NULL DEFAULT 0, max_ms INT UNSIGNED NOT NULL DEFAULT 0,
            p50_ms INT UNSIGNED NULL, p95_ms INT UNSIGNED NULL, mem_kb_max INT UNSIGNED NULL, active_min INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (day, route))$t");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, $mode);
        if ($a !== false && $b !== false) {
            @unlink(cdp_reqmOffFile());
        }
        return $done = ($a !== false && $b !== false);
    }

    /** Weighted percentile of [[ms, w], ...] (sorted or not). */
    function cdp_reqmPercentile(array $pairs, float $p): ?int
    {
        if (!$pairs) {
            return null;
        }
        usort($pairs, function ($a, $b) { return $a[0] <=> $b[0]; });
        $total = 0;
        foreach ($pairs as $x) {
            $total += $x[1];
        }
        $target = $total * $p;
        $acc = 0;
        foreach ($pairs as $x) {
            $acc += $x[1];
            if ($acc >= $target) {
                return (int) $x[0];
            }
        }
        return (int) end($pairs)[0];
    }

    /** SQL expression of the latency bucket used for percentiles (exact below 100 ms, then 1 % to 5 % wide). */
    function cdp_reqmBucketSql(): string
    {
        return 'CASE WHEN ms < 100 THEN ms WHEN ms < 1000 THEN ROUND(ms, -1) WHEN ms < 10000 THEN ROUND(ms, -2) ELSE ROUND(ms, -3) END';
    }

    /** A unix time as a wall-clock string in the application's zone (the zone the rows are stamped in). */
    function cdp_reqmFmt(int $t, string $fmt = 'Y-m-d H:i:s'): string
    {
        return (new DateTime('@' . $t))->setTimezone(cdp_reqmTz())->format($fmt);
    }

    /** Unix time of a wall-clock string written in the application's zone. */
    function cdp_reqmUnix(string $wall): int
    {
        try {
            return (new DateTime($wall, cdp_reqmTz()))->getTimestamp();
        } catch (Throwable $e) {
            return (int) strtotime($wall);
        }
    }

    /** Is the recorder switched off for a while because its table could not be created? */
    function cdp_reqmOffFile(): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . '/cdp_reqm_off_' . substr(md5(defined('CDP_ROOT') ? (string) CDP_ROOT : __DIR__), 0, 12);
    }

    function cdp_reqmIsOff(): bool
    {
        $f = cdp_reqmOffFile();
        return @is_file($f) && @filemtime($f) > time() - 3600;
    }

    /** Runs last, after every other shutdown function: answer the client first where PHP-FPM allows it. */
    function cdp_reqmMaintainLate(): void
    {
        try {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            cdp_reqmMaintain();
        } catch (Throwable $e) {
            // never break a request
        }
    }

    /**
     * Roll finished days up and apply retention. Idempotent. Every boundary is computed in the
     * application's zone (the zone the rows are stamped in), whatever PHP's default zone is, so the
     * current local day is never rolled up. A day already rolled up is rolled up again when its raw
     * rows no longer match the rollup (a day rolled up early by an older version heals itself).
     */
    function cdp_reqmMaintain(?PDO $pdo = null): void
    {
        $pdo = $pdo ?: cdp_reqmPdo();
        if (!$pdo) {
            return;
        }
        $tz = cdp_reqmTz($pdo);
        $today = (new DateTime('now', $tz))->format('Y-m-d');
        $q = $pdo->prepare('SELECT DATE(ts) AS d, SUM(w) AS n, COUNT(*) AS s FROM cdb_dev_requests WHERE ts < ? GROUP BY DATE(ts) ORDER BY d');
        if (!$q || !$q->execute([$today . ' 00:00:00'])) {
            return;
        }
        $raw = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $raw[$r['d']] = [(int) $r['n'], (int) $r['s']];
        }
        $have = $pdo->query("SELECT day, n, samples FROM cdb_dev_request_daily WHERE route = '*'");
        $done = [];
        foreach ($have ? $have->fetchAll(PDO::FETCH_ASSOC) : [] as $r) {
            $done[$r['day']] = [(int) $r['n'], (int) $r['samples']];
        }
        $oldest = $raw ? array_key_first($raw) : null;
        $bucket = cdp_reqmBucketSql();
        foreach ($raw as $d => $cnt) {
            if ($d >= $today) {
                continue;
            }
            // already rolled up and unchanged, or the oldest raw day (retention may have cut into it)
            if (isset($done[$d]) && ($done[$d] === $cnt || $d === $oldest)) {
                continue;
            }
            $next = (new DateTime($d, $tz))->modify('+1 day')->format('Y-m-d');
            $args = [$d . ' 00:00:00', $next . ' 00:00:00'];
            $agg = [];
            $r = $pdo->prepare("SELECT route, SUM(w) AS n, COUNT(*) AS s, SUM(IF(status >= 500, w, 0)) AS e5, SUM(IF(status BETWEEN 400 AND 499, w, 0)) AS e4,
                    SUM(ms * w) AS sm, MAX(ms) AS mx, MAX(mem_kb) AS mem, COUNT(DISTINCT DATE_FORMAT(ts, '%H%i')) AS act
                FROM cdb_dev_requests WHERE ts >= ? AND ts < ? GROUP BY route");
            if (!$r || !$r->execute($args)) {
                continue;
            }
            foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $x) {
                $agg[$x['route']] = $x + ['pairs' => []];
            }
            $tot = $pdo->prepare("SELECT SUM(w) AS n, COUNT(*) AS s, SUM(IF(status >= 500, w, 0)) AS e5, SUM(IF(status BETWEEN 400 AND 499, w, 0)) AS e4,
                    SUM(ms * w) AS sm, MAX(ms) AS mx, MAX(mem_kb) AS mem, COUNT(DISTINCT DATE_FORMAT(ts, '%H%i')) AS act
                FROM cdb_dev_requests WHERE ts >= ? AND ts < ?");
            if ($tot && $tot->execute($args) && ($t = $tot->fetch(PDO::FETCH_ASSOC)) && (int) $t['s'] > 0) {
                $agg['*'] = $t + ['pairs' => []];
            }
            $p = $pdo->prepare("SELECT route, $bucket AS b, SUM(w) AS w FROM cdb_dev_requests WHERE ts >= ? AND ts < ? GROUP BY route, b");
            if ($p && $p->execute($args)) {
                $all = [];
                foreach ($p->fetchAll(PDO::FETCH_ASSOC) as $x) {
                    if (isset($agg[$x['route']])) {
                        $agg[$x['route']]['pairs'][] = [(int) $x['b'], (int) $x['w']];
                    }
                    $all[(int) $x['b']] = ($all[(int) $x['b']] ?? 0) + (int) $x['w'];
                }
                if (isset($agg['*'])) {
                    foreach ($all as $b => $w) {
                        $agg['*']['pairs'][] = [$b, $w];
                    }
                }
            }
            $ins = $pdo->prepare('REPLACE INTO cdb_dev_request_daily (day, route, n, samples, err5, err4, sum_ms, max_ms, p50_ms, p95_ms, mem_kb_max, active_min) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($agg as $route => $a) {
                $ins && $ins->execute([$d, (string) $route, (int) $a['n'], (int) $a['s'], (int) $a['e5'], (int) $a['e4'], (int) $a['sm'], (int) $a['mx'],
                    cdp_reqmPercentile($a['pairs'], 0.5), cdp_reqmPercentile($a['pairs'], 0.95), (int) $a['mem'] ?: null, (int) $a['act']]);
            }
            if (!$agg) {
                $ins && $ins->execute([$d, '*', 0, 0, 0, 0, 0, 0, null, null, null, 0]);
            }
        }
        $cut = (new DateTime('now', $tz))->modify('-' . cdp_reqmRetentionDays() . ' days')->format('Y-m-d') . ' 00:00:00';
        $del = $pdo->prepare('DELETE FROM cdb_dev_requests WHERE ts < ?');
        $del && $del->execute([$cut]);
        $old = $pdo->prepare('DELETE FROM cdb_dev_request_daily WHERE day < ?');
        $old && $old->execute([(new DateTime('now', $tz))->modify('-400 days')->format('Y-m-d')]);
    }

    // -----------------------------------------------------------------------------------------
    // Readers (all aggregation happens in SQL; PHP only receives totals and latency histograms)
    // -----------------------------------------------------------------------------------------

    function cdp_reqmQ(string $sql, array $args = []): array
    {
        $pdo = cdp_reqmPdo();
        if (!$pdo) {
            return [];
        }
        try {
            $st = $pdo->prepare($sql);
            if (!$st || !$st->execute($args)) {
                return [];
            }
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** True when the recorder has its table (creates it when missing). */
    function cdp_reqmReady(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $ok = (bool) cdp_reqmQ("SELECT 1 AS x FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_dev_requests'") || cdp_reqmEnsure();
        }
        return $ok;
    }

    /** First and last recorded request, number of rows. */
    function cdp_reqmCoverage(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        $r = cdp_reqmReady() ? (cdp_reqmQ('SELECT MIN(ts) AS a, MAX(ts) AS b, COUNT(*) AS n FROM cdb_dev_requests')[0] ?? []) : [];
        $d = cdp_reqmReady() ? (cdp_reqmQ("SELECT MIN(day) AS a, COUNT(*) AS n FROM cdb_dev_request_daily WHERE route = '*'")[0] ?? []) : [];
        return $memo = ['first' => $r['a'] ?? null, 'last' => $r['b'] ?? null, 'rows' => (int) ($r['n'] ?? 0), 'first_day' => $d['a'] ?? null, 'days' => (int) ($d['n'] ?? 0),
            'rate' => cdp_reqmRate(), 'enabled' => !(defined('CDP_REQ_METRICS') && !CDP_REQ_METRICS), 'retention' => cdp_reqmRetentionDays()];
    }

    /** Empty window totals. */
    function cdp_reqmEmpty(): array
    {
        return ['n' => 0, 'samples' => 0, 'err5' => 0, 'err4' => 0, 'avg' => null, 'p50' => null, 'p95' => null, 'max' => null, 'rpm' => 0.0, 'fatal' => 0, 'err_pct' => null];
    }

    /** Finish a totals row: averages, percentiles from the latency histogram, rates. */
    function cdp_reqmFinish(array $t, array $pairs, int $from, int $to): array
    {
        $out = cdp_reqmEmpty();
        $out['n'] = (int) ($t['n'] ?? 0);
        $out['samples'] = (int) ($t['s'] ?? 0);
        if (!$out['samples']) {
            return $out;
        }
        $out['err5'] = (int) $t['e5'];
        $out['err4'] = (int) $t['e4'];
        $out['fatal'] = (int) $t['f'];
        $out['max'] = (int) $t['mx'];
        $out['avg'] = $out['n'] ? round((float) $t['sm'] / $out['n'], 1) : null;
        $out['p50'] = cdp_reqmPercentile($pairs, 0.5);
        $out['p95'] = cdp_reqmPercentile($pairs, 0.95);
        $out['rpm'] = round($out['n'] / max(1, ($to - $from) / 60), 2);
        $out['err_pct'] = $out['n'] ? round($out['err5'] / $out['n'] * 100, 3) : null;
        return $out;
    }

    /**
     * Totals of a window for the whole site and per kind (page, ajax, api), two GROUP BY queries,
     * memoised per request. Returns ['*' => totals, 'page' => totals, ...].
     */
    function cdp_reqmWindowKinds(int $from, int $to): array
    {
        static $memo = [];
        $key = $from . '-' . $to;
        if (isset($memo[$key])) {
            return $memo[$key];
        }
        $out = ['*' => cdp_reqmEmpty()];
        if (!cdp_reqmReady()) {
            return $memo[$key] = $out;
        }
        $a = [cdp_reqmFmt($from), cdp_reqmFmt($to)];
        $tot = [];
        foreach (cdp_reqmQ("SELECT kind, SUM(w) AS n, COUNT(*) AS s, SUM(ms * w) AS sm, MAX(ms) AS mx, SUM(IF(status >= 500, w, 0)) AS e5,
                SUM(IF(status BETWEEN 400 AND 499, w, 0)) AS e4, SUM(fatal) AS f FROM cdb_dev_requests WHERE ts >= ? AND ts < ? GROUP BY kind", $a) as $r) {
            $tot[$r['kind']] = $r;
        }
        $pairs = ['*' => []];
        $all = [];
        foreach (cdp_reqmQ('SELECT kind, ' . cdp_reqmBucketSql() . ' AS b, SUM(w) AS w FROM cdb_dev_requests WHERE ts >= ? AND ts < ? GROUP BY kind, b', $a) as $r) {
            $pairs[$r['kind']][] = [(int) $r['b'], (int) $r['w']];
            $all[(int) $r['b']] = ($all[(int) $r['b']] ?? 0) + (int) $r['w'];
        }
        foreach ($all as $b => $w) {
            $pairs['*'][] = [$b, $w];
        }
        $sum = ['n' => 0, 's' => 0, 'sm' => 0, 'mx' => 0, 'e5' => 0, 'e4' => 0, 'f' => 0];
        foreach ($tot as $k => $r) {
            $out[$k] = cdp_reqmFinish($r, $pairs[$k] ?? [], $from, $to);
            foreach (['n', 's', 'sm', 'e5', 'e4', 'f'] as $f) {
                $sum[$f] += (float) $r[$f];
            }
            $sum['mx'] = max($sum['mx'], (int) $r['mx']);
        }
        $out['*'] = cdp_reqmFinish($sum, $pairs['*'], $from, $to);
        return $memo[$key] = $out;
    }

    /**
     * Totals for a time window. Returns n (estimated requests), samples, err5, err4,
     * avg / p50 / p95 / max ms, rpm (requests per minute over the window), fatal, err_pct.
     * Percentiles come from a latency histogram (exact below 100 ms, within 5 % above).
     */
    function cdp_reqmWindow(int $from, int $to, ?string $route = null, ?string $kind = null): array
    {
        if ($route === null) {
            $k = cdp_reqmWindowKinds($from, $to);
            return $k[$kind ?? '*'] ?? cdp_reqmEmpty();
        }
        if (!cdp_reqmReady()) {
            return cdp_reqmEmpty();
        }
        $w = 'ts >= ? AND ts < ? AND route = ?';
        $a = [cdp_reqmFmt($from), cdp_reqmFmt($to), $route];
        if ($kind !== null) {
            $w .= ' AND kind = ?';
            $a[] = $kind;
        }
        $t = cdp_reqmQ("SELECT SUM(w) AS n, COUNT(*) AS s, SUM(ms * w) AS sm, MAX(ms) AS mx, SUM(IF(status >= 500, w, 0)) AS e5,
            SUM(IF(status BETWEEN 400 AND 499, w, 0)) AS e4, SUM(fatal) AS f FROM cdb_dev_requests WHERE $w", $a)[0] ?? [];
        $pairs = array_map(fn($r) => [(int) $r['b'], (int) $r['w']], cdp_reqmQ('SELECT ' . cdp_reqmBucketSql() . " AS b, SUM(w) AS w FROM cdb_dev_requests WHERE $w GROUP BY b", $a));
        return cdp_reqmFinish($t, $pairs, $from, $to);
    }

    /**
     * One row per day for the last $days days (today included, from raw rows; earlier days from the
     * rollup), in the application's zone. Missing days are present with n = 0 and has = false so
     * sparklines keep their dates.
     */
    function cdp_reqmDaily(int $days = 30, string $route = '*'): array
    {
        $days = max(1, min(400, $days));
        $tz = cdp_reqmTz();
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = (new DateTime('now', $tz))->modify("-$i days")->format('Y-m-d');
            $out[$d] = ['day' => $d, 'n' => 0, 'err5' => 0, 'err4' => 0, 'avg' => null, 'p50' => null, 'p95' => null, 'max' => null, 'rpm' => null, 'err_pct' => null, 'has' => false];
        }
        if (!cdp_reqmReady()) {
            return array_values($out);
        }
        $today = (new DateTime('now', $tz))->format('Y-m-d');
        // Roll up any finished day that is still raw before reading.
        $first = array_key_first($out);
        $last = cdp_reqmQ('SELECT DATE(MAX(ts)) AS d FROM cdb_dev_requests WHERE ts < ?', [$today . ' 00:00:00']);
        $lastDay = $last[0]['d'] ?? null;
        if ($lastDay && !cdp_reqmQ("SELECT 1 AS x FROM cdb_dev_request_daily WHERE day = ? AND route = '*'", [$lastDay])) {
            cdp_reqmMaintain();
        }
        foreach (cdp_reqmQ('SELECT * FROM cdb_dev_request_daily WHERE route = ? AND day >= ?', [$route, $first]) as $r) {
            if (!isset($out[$r['day']]) || $r['day'] === $today) {
                continue;
            }
            $n = (int) $r['n'];
            $out[$r['day']] = ['day' => $r['day'], 'n' => $n, 'err5' => (int) $r['err5'], 'err4' => (int) $r['err4'], 'avg' => $n ? round((int) $r['sum_ms'] / $n, 1) : null,
                'p50' => $r['p50_ms'] !== null ? (int) $r['p50_ms'] : null, 'p95' => $r['p95_ms'] !== null ? (int) $r['p95_ms'] : null, 'max' => (int) $r['max_ms'],
                'rpm' => $n ? round($n / 1440, 2) : null, 'err_pct' => $n ? round((int) $r['err5'] / $n * 100, 3) : null, 'has' => $n > 0];
        }
        $t0 = cdp_reqmUnix($today . ' 00:00:00');
        $now = time();
        $w = cdp_reqmWindow($t0, $now + 1, $route === '*' ? null : $route);
        $out[$today] = ['day' => $today, 'n' => $w['n'], 'err5' => $w['err5'], 'err4' => $w['err4'], 'avg' => $w['avg'], 'p50' => $w['p50'], 'p95' => $w['p95'], 'max' => $w['max'],
            'rpm' => $w['n'] ? round($w['n'] / max(1, ($now - $t0) / 60), 2) : null, 'err_pct' => $w['err_pct'], 'has' => $w['n'] > 0];
        return array_values($out);
    }

    /** Range definitions: [seconds, bucket seconds, label format]. */
    function cdp_reqmRanges(): array
    {
        return ['1h' => [3600, 60, 'H:i'], '24h' => [86400, 1800, 'H:i'], '7d' => [7 * 86400, 3 * 3600, 'D H:i'], '30d' => [30 * 86400, 86400, 'M j']];
    }

    /**
     * Bucketed series for charts: t (unix), label, rpm, avg, p95, err (5xx %), load, mem, n.
     * 1h / 24h / 7d come from raw rows (grouped in SQL); 30d from the daily rollup (load and memory not kept there).
     */
    function cdp_reqmSeries(string $range = '24h'): array
    {
        $ranges = cdp_reqmRanges();
        [$span, $bucket, $fmt] = $ranges[$range] ?? $ranges['24h'];
        $out = ['range' => isset($ranges[$range]) ? $range : '24h', 'bucket' => $bucket, 'points' => [], 'samples' => 0];
        if ($bucket === 86400) {
            foreach (cdp_reqmDaily(30) as $d) {
                $t = cdp_reqmUnix($d['day'] . ' 00:00:00');
                $out['points'][] = ['t' => $t, 'label' => cdp_reqmFmt($t, $fmt), 'n' => $d['n'], 'rpm' => $d['rpm'], 'avg' => $d['avg'], 'p95' => $d['p95'],
                    'err' => $d['err_pct'], 'load' => null, 'mem' => null];
                $out['samples'] += $d['n'] ? 1 : 0;
            }
            return $out;
        }
        $now = time();
        $end = (int) (floor($now / $bucket) + 1) * $bucket;
        $start = $end - (int) ceil($span / $bucket) * $bucket;
        $nb = (int) (($end - $start) / $bucket);
        $pts = [];
        for ($i = 0; $i < $nb; $i++) {
            $t = $start + $i * $bucket;
            $pts[$i] = ['t' => $t, 'label' => cdp_reqmFmt($t, $fmt), 'n' => 0, 's' => 0, 'sm' => 0, 'e' => 0, 'l' => null, 'm' => null, 'p' => []];
        }
        if (cdp_reqmReady()) {
            $from = cdp_reqmFmt($start);
            $a = [$from, $from, cdp_reqmFmt($end)];
            // bucket index from the wall-clock difference, so MySQL's own time zone does not matter
            $k = "FLOOR(TIMESTAMPDIFF(SECOND, ?, ts) / $bucket)";
            foreach (cdp_reqmQ("SELECT $k AS k, SUM(w) AS n, COUNT(*) AS s, SUM(ms * w) AS sm, SUM(IF(status >= 500, w, 0)) AS e, AVG(load1) AS l, AVG(mem_pct) AS m
                    FROM cdb_dev_requests WHERE ts >= ? AND ts < ? GROUP BY k", $a) as $r) {
                $i = (int) $r['k'];
                if (!isset($pts[$i])) {
                    continue;
                }
                $pts[$i] = ['n' => (int) $r['n'], 's' => (int) $r['s'], 'sm' => (float) $r['sm'], 'e' => (int) $r['e'], 'l' => $r['l'], 'm' => $r['m']] + $pts[$i];
                $out['samples'] += (int) $r['s'];
            }
            foreach (cdp_reqmQ("SELECT $k AS k, " . cdp_reqmBucketSql() . " AS b, SUM(w) AS w FROM cdb_dev_requests WHERE ts >= ? AND ts < ? GROUP BY k, b", $a) as $r) {
                $i = (int) $r['k'];
                if (isset($pts[$i])) {
                    $pts[$i]['p'][] = [(int) $r['b'], (int) $r['w']];
                }
            }
        }
        foreach ($pts as $p) {
            $n = $p['n'];
            $mins = $bucket / 60;
            if ($p['t'] + $bucket > $now) {
                $mins = max(1, ($now - $p['t']) / 60);
            }
            $out['points'][] = ['t' => $p['t'], 'label' => $p['label'], 'n' => $n, 'rpm' => round($n / $mins, 2), 'avg' => $n ? round($p['sm'] / $n, 1) : null,
                'p95' => cdp_reqmPercentile($p['p'], 0.95), 'err' => $n ? round($p['e'] / $n * 100, 3) : null,
                'load' => $p['l'] !== null ? round((float) $p['l'], 2) : null, 'mem' => $p['m'] !== null ? round((float) $p['m'], 1) : null];
        }
        return $out;
    }

    /** Per-route table for a window: n, rpm, avg, p50, p95, max, err5, err4, err_pct, mem. Order: busy | slow | errors. */
    function cdp_reqmRoutes(int $from, int $to, string $order = 'busy', int $limit = 10): array
    {
        if (!cdp_reqmReady()) {
            return [];
        }
        $a = [cdp_reqmFmt($from), cdp_reqmFmt($to)];
        $agg = [];
        foreach (cdp_reqmQ('SELECT route, MIN(kind) AS kind, SUM(w) AS n, SUM(ms * w) AS sm, MAX(ms) AS mx, SUM(IF(status >= 500, w, 0)) AS e5,
                SUM(IF(status BETWEEN 400 AND 499, w, 0)) AS e4, MAX(mem_kb) AS mem FROM cdb_dev_requests WHERE ts >= ? AND ts < ? GROUP BY route', $a) as $r) {
            $agg[$r['route']] = $r + ['p' => []];
        }
        if (!$agg) {
            return [];
        }
        foreach (cdp_reqmQ('SELECT route, ' . cdp_reqmBucketSql() . ' AS b, SUM(w) AS w FROM cdb_dev_requests WHERE ts >= ? AND ts < ? GROUP BY route, b', $a) as $r) {
            if (isset($agg[$r['route']])) {
                $agg[$r['route']]['p'][] = [(int) $r['b'], (int) $r['w']];
            }
        }
        $mins = max(1, ($to - $from) / 60);
        $rows = [];
        foreach ($agg as $x) {
            $n = (int) $x['n'];
            $rows[] = ['route' => (string) $x['route'], 'kind' => (string) $x['kind'], 'n' => $n, 'rpm' => round($n / $mins, 3), 'avg' => round((float) $x['sm'] / max(1, $n), 1),
                'p50' => cdp_reqmPercentile($x['p'], 0.5), 'p95' => cdp_reqmPercentile($x['p'], 0.95), 'max' => (int) $x['mx'], 'err5' => (int) $x['e5'], 'err4' => (int) $x['e4'],
                'err_pct' => round((int) $x['e5'] / max(1, $n) * 100, 2), 'mem_kb' => (int) $x['mem']];
        }
        usort($rows, function ($x, $y) use ($order) {
            if ($order === 'slow') {
                return [$y['p95'], $y['avg']] <=> [$x['p95'], $x['avg']];
            }
            if ($order === 'errors') {
                return [$y['err5'], $y['err4'], $y['n']] <=> [$x['err5'], $x['err4'], $x['n']];
            }
            return $y['n'] <=> $x['n'];
        });
        return array_slice($rows, 0, max(1, $limit));
    }

    /** SQL condition and arguments of the cdp_reqmRecent filters (shared with the log counters). */
    function cdp_reqmWhere(array $f): array
    {
        $w = ['ts >= ?'];
        $a = [!empty($f['since']) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $f['since']) ? (string) $f['since'] : cdp_reqmFmt(time() - 3600)];
        if (!empty($f['after_id'])) {
            $w[] = 'id > ?';
            $a[] = (int) $f['after_id'];
        }
        if (!empty($f['errors_only'])) {
            $w[] = 'status >= 500';
        } elseif (!empty($f['slow_only'])) {
            $w[] = 'ms >= ' . cdp_reqmSlowMs();
        } elseif (!empty($f['level'])) {
            $w[] = $f['level'] === 'ERROR' ? 'status >= 500' : ($f['level'] === 'WARN' ? '(status < 500 AND (status >= 400 OR ms >= ' . cdp_reqmSlowMs() . '))' : '(status < 400 AND ms < ' . cdp_reqmSlowMs() . ')');
        }
        if (!empty($f['q'])) {
            $w[] = 'route LIKE ?';
            $a[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], (string) $f['q']) . '%';
        }
        return [implode(' AND ', $w), $a];
    }

    /** Raw recorded requests, newest first. Filters: since (Y-m-d H:i:s, app zone), errors_only, slow_only, level, q (route contains). */
    function cdp_reqmRecent(array $f = [], int $limit = 100): array
    {
        if (!cdp_reqmReady()) {
            return [];
        }
        [$w, $a] = cdp_reqmWhere($f);
        return cdp_reqmQ('SELECT id, ts, route, method, status, ms, mem_kb, w, kind, fatal, load1, mem_pct FROM cdb_dev_requests WHERE ' . $w . ' ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit)), $a);
    }
}
