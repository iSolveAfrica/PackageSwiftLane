<?php
/**
 * Developer console, "deploy" package: GitHub (read only), deployments, change log from commits,
 * and the guarded database tools (backup now, upload a dump, apply it).
 *
 * GitHub
 *   Settings live in cdb_dev_state['github'] (owner, repo, branch, API base, deploy workflow pattern).
 *   The token is a fine-grained, read-only personal access token. It is encrypted with libsodium
 *   (key: CDP_APP_KEY when defined, otherwise a random key file in the private folder, never in the
 *   database) and is never sent back to the browser: pages only see "github_pat_…abcd".
 *   Only GET requests are ever made (sl_ghGet); nothing in this file can write to GitHub.
 *   The API base is limited to https://api.github.com plus GHE hosts the server owner lists in
 *   CDP_GH_ALLOWED_HOSTS (http to loopback only with CDP_GH_ALLOW_LOCAL_MOCK, for a test mock). Each
 *   request resolves the host, refuses private/reserved addresses and pins the IP. A saved token is never
 *   sent to a changed repository or host: the token must be entered again, and new settings are tested
 *   before they are saved. Links from the API are kept only when they point at the repository's web host.
 *   Sync: sl_ghSync() pulls commits, Actions runs (+ jobs/steps of the newest runs), releases (or
 *   tags), pull requests and deployments into the cdb_dev_gh_* cache tables. Cron-friendly:
 *       php helpers/dev_github.php sync      (e.g. every 15 minutes)
 *   Times are stored in the app's time zone (cdb_settings.timezone, applied by sl_ghApplyTz()).
 *
 * Database tools
 *   Backups and staged uploads live in the private folder outside the web root (CDP_PRIVATE_DIR or
 *   ../swiftlane_private, 0700/0600); without one, uploads and backups are refused. Import plans with
 *   helpers/database_import.php, then every INSERT passes sl_dbStrictInsert() (values only) and runs on
 *   a connection without multi-statements: DDL or other SQL in a dump is never executed.
 *
 * Access: every caller checks sl_ghCan($user, $section): super admin assigned in cdb_dev_access
 * (cdp_devCanUse) AND the per-section hook cdp_devSectionAllowed($user, $section) when the access
 * package defines it. Sections used here: deployments, database, integrations, github, issues, changelog.
 */

if (!defined('CDP_ROOT')) {
    // CLI entry (cron): php helpers/dev_github.php sync
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
        exit;
    }
    chdir(dirname(__DIR__));
    require_once dirname(__DIR__) . '/loader.php';
}
require_once CDP_ROOT . '/helpers/devops.php';
if (is_file(CDP_ROOT . '/helpers/dev_access.php')) {
    require_once CDP_ROOT . '/helpers/dev_access.php';
}

if (!function_exists('sl_ghCan')) {

    /* ------------------------------------------------------------------ access */

    function sl_ghSectionAllowed($user, string $section): bool
    {
        // fail closed: without the access package nobody gets a section
        return function_exists('cdp_devSectionAllowed') && (bool) cdp_devSectionAllowed($user, $section);
    }

    function sl_ghCan($user, string $section): bool
    {
        return cdp_devCanUse($user) && sl_ghSectionAllowed($user, $section);
    }

    /** Page gate: 403 page unless the user may open this developer section. */
    function sl_ghPageGate($user, string $section): void
    {
        if (!sl_ghCan($user, $section)) {
            header('Location: error403.php');
            exit;
        }
        sl_ghEnsureTables();
    }

    /* ------------------------------------------------------------------ tables */

    function sl_ghEnsureTables(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        $ok = true;
        $file = CDP_ROOT . '/migrations/2026_11_26_dev_github.sql';
        if (cdp_devTableExists('cdb_dev_db_jobs') && cdp_devTableExists('cdb_dev_gh_deployments')) {
            return $ok;
        }
        $sql = is_readable($file) ? (string) file_get_contents($file) : '';
        if (!preg_match_all('/CREATE TABLE IF NOT EXISTS .*?;/s', $sql, $m)) {
            return $ok = false;
        }
        foreach ($m[0] as $stmt) {
            if (!cdp_devX($stmt)) {
                $ok = false;
            }
        }
        return $ok;
    }

    /**
     * The app's time zone (cdb_settings.timezone), as Core applies it on every page. AJAX endpoints and
     * the CLI do not build Core, so without this the cache tables, job log and backup names were stamped
     * in the server zone (UTC) and read back as local time. Applied once when this file is loaded.
     */
    function sl_ghApplyTz(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $row = cdp_devOne('SELECT timezone FROM cdb_settings LIMIT 1');
        $tz = $row && !empty($row->timezone) ? (string) $row->timezone : '';
        if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
            date_default_timezone_set($tz);
        }
    }

    function sl_ghNow(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** ISO 8601 (GitHub) to local DATETIME. */
    function sl_ghTime($iso): ?string
    {
        if (!$iso) {
            return null;
        }
        $t = strtotime((string) $iso);
        return $t ? date('Y-m-d H:i:s', $t) : null;
    }

    /* ---------------------------------------------------------- private folder */

    /** True when $path is the web root or inside it. */
    function sl_ghInsideRoot(string $path): bool
    {
        $root = rtrim(realpath(CDP_ROOT) ?: CDP_ROOT, '/\\');
        $p = rtrim(realpath($path) ?: $path, '/\\');
        return $p === $root || strpos($p, $root . DIRECTORY_SEPARATOR) === 0;
    }

    /**
     * The folder for secrets, staged uploads and console backups: CDP_PRIVATE_DIR, else
     * ../swiftlane_private next to the web root. Never inside the web root and never the shared
     * system temp folder: when neither is usable this returns '' and the callers refuse to work
     * (no key file, no uploads, no backups) and the page says how to fix it.
     */
    function sl_ghPrivateDir(): string
    {
        static $dir = null;
        if ($dir !== null) {
            return $dir;
        }
        $cands = [];
        if (defined('CDP_PRIVATE_DIR') && CDP_PRIVATE_DIR) {
            $cands[] = rtrim((string) CDP_PRIVATE_DIR, '/\\');
        }
        $cands[] = dirname(CDP_ROOT) . '/swiftlane_private';
        foreach ($cands as $c) {
            if (sl_ghInsideRoot($c)) {
                continue;
            }
            if (!is_dir($c)) {
                $old = umask(0077);
                @mkdir($c, 0700, true);
                umask($old);
            }
            if (is_dir($c) && is_writable($c) && !sl_ghInsideRoot($c)) {
                return $dir = $c;
            }
        }
        return $dir = '';
    }

    /** True when a private folder outside the web root is available. */
    function sl_ghPrivateOutsideRoot(): bool
    {
        return sl_ghPrivateDir() !== '';
    }

    /** A sub folder of the private folder (created 0700), or '' when there is no private folder. */
    function sl_ghPrivateSub(string $name): string
    {
        $base = sl_ghPrivateDir();
        if ($base === '') {
            return '';
        }
        $d = $base . '/' . $name;
        if (!is_dir($d)) {
            $old = umask(0077);
            @mkdir($d, 0700, true);
            umask($old);
        }
        return is_dir($d) && is_writable($d) ? $d : '';
    }

    function sl_ghDenyFolder(string $dir): void
    {
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "# Swift Lane: never served\nRequire all denied\nDeny from all\n");
        }
        if (!is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '');
        }
    }

    /* ---------------------------------------------------------------- secrets */

    function sl_ghKey(): ?string
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            return null;
        }
        if (defined('CDP_APP_KEY') && (string) CDP_APP_KEY !== '') {
            return hash('sha256', 'swiftlane-dev-github|' . CDP_APP_KEY, true);
        }
        $dir = sl_ghPrivateDir();
        if ($dir === '') {
            return null; // never keep the key inside the web root or in a shared temp folder
        }
        $f = $dir . '/dev_secret.key';
        if (is_readable($f)) {
            $k = base64_decode(trim((string) file_get_contents($f)), true);
            if ($k !== false && strlen($k) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                return $k;
            }
            return null; // a damaged key file is never silently replaced (the stored token would be lost)
        }
        $k = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $old = umask(0077);
        $fh = @fopen($f, 'x'); // created 0600, fails if it appeared meanwhile
        umask($old);
        if (!$fh) {
            return null;
        }
        @chmod($f, 0600);
        fwrite($fh, base64_encode($k));
        fclose($fh);
        return $k;
    }

    function sl_ghEncrypt(string $plain): ?string
    {
        $k = sl_ghKey();
        if ($k === null) {
            return null;
        }
        $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'v1:' . base64_encode($n . sodium_crypto_secretbox($plain, $n, $k));
    }

    function sl_ghDecrypt(?string $box): ?string
    {
        if (!$box || strpos($box, 'v1:') !== 0) {
            return null;
        }
        $k = sl_ghKey();
        $raw = base64_decode(substr($box, 3), true);
        if ($k === null || $raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $p = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $k);
        return $p === false ? null : $p;
    }

    function sl_ghMaskToken(string $t): string
    {
        if ($t === '') {
            return '';
        }
        $pre = preg_match('/^(github_pat_|ghp_|gho_|ghs_|ghu_)/', $t, $m) ? $m[1] : '';
        return $pre . '…' . substr($t, -4);
    }

    /* ----------------------------------------------------------------- config */

    function sl_ghConfig(): array
    {
        $c = cdp_devState('github', []);
        $c = is_array($c) ? $c : [];
        $c += ['owner' => '', 'repo' => '', 'branch' => '', 'api_base' => 'https://api.github.com', 'deploy_pattern' => 'deploy|release|publish|cd',
            'token_box' => '', 'token_mask' => '', 'token_set_at' => null, 'token_set_by' => null,
            'last_sync' => null, 'last_status' => '', 'last_error' => '', 'last_counts' => [], 'rate_remaining' => null, 'rate_reset' => null,
            'repo_url' => '', 'default_branch' => '', 'private' => null, 'scopes_warning' => '', 'tested_at' => null];
        $c['has_token'] = $c['token_box'] !== '';
        $c['configured'] = $c['owner'] !== '' && $c['repo'] !== '';
        $c['ready'] = $c['configured'] && $c['has_token'];
        $c['web'] = sl_ghWebBase($c['api_base']) . '/' . rawurlencode($c['owner']) . '/' . rawurlencode($c['repo']);
        return $c;
    }

    /** Config without the encrypted token, safe to hand to a page. */
    function sl_ghPublicConfig(): array
    {
        $c = sl_ghConfig();
        unset($c['token_box']);
        return $c;
    }

    /** https://api.github.com -> https://github.com; GHE https://host/api/v3 -> https://host. */
    function sl_ghWebBase(string $api): string
    {
        $api = rtrim($api, '/');
        if (preg_match('#^https://api\.github\.com$#i', $api) || preg_match('#^http://(127\.0\.0\.1|localhost)(:\d+)?$#i', $api)) {
            return 'https://github.com'; // a local test mock stands in for api.github.com
        }
        return preg_replace('#/api/v3$#i', '', $api);
    }

    /**
     * GitHub Enterprise API hosts the server owner allows, besides api.github.com: the constant
     * CDP_GH_ALLOWED_HOSTS (array or comma list, e.g. 'github.example.com') in config/config.php.
     * A user of the console can never add a host: the list is server configuration.
     */
    function sl_ghAllowedHosts(): array
    {
        $raw = defined('CDP_GH_ALLOWED_HOSTS') ? CDP_GH_ALLOWED_HOSTS : (string) getenv('CDP_GH_ALLOWED_HOSTS');
        $list = is_array($raw) ? $raw : explode(',', (string) $raw);
        $out = ['api.github.com'];
        foreach ($list as $h) {
            $h = strtolower(trim((string) $h));
            if ($h !== '' && preg_match('/^[a-z0-9.-]{1,190}$/', $h)) {
                $out[] = $h;
            }
        }
        return array_values(array_unique($out));
    }

    /** http://127.0.0.1 / localhost (a local mock for tests) only when CDP_GH_ALLOW_LOCAL_MOCK is true (constant or environment). */
    function sl_ghLocalMockAllowed(): bool
    {
        return (defined('CDP_GH_ALLOW_LOCAL_MOCK') && CDP_GH_ALLOW_LOCAL_MOCK === true) || getenv('CDP_GH_ALLOW_LOCAL_MOCK') === '1';
    }

    /**
     * Validates an API base URL against the server-side allowlist: https://api.github.com, https on a
     * host in CDP_GH_ALLOWED_HOSTS (path empty or /api/v3), or http on loopback when the local mock is
     * allowed. No credentials, query, fragment, IP literals or other schemes.
     */
    function sl_ghValidBase(string $u): bool
    {
        return sl_ghBaseProblem($u) === '';
    }

    /** '' when the base is allowed, otherwise the reason. */
    function sl_ghBaseProblem(string $u): string
    {
        $p = parse_url($u);
        if (!$p || empty($p['host']) || !empty($p['user']) || !empty($p['pass']) || isset($p['query']) || isset($p['fragment'])) {
            return 'Not a valid URL';
        }
        $s = strtolower($p['scheme'] ?? '');
        $host = strtolower((string) $p['host']);
        $path = rtrim((string) ($p['path'] ?? ''), '/');
        if ($s === 'http' && in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return sl_ghLocalMockAllowed() ? '' : 'http:// to this machine is only for a local test mock (CDP_GH_ALLOW_LOCAL_MOCK)';
        }
        if ($s !== 'https') {
            return 'Use https://';
        }
        if (!in_array($host, sl_ghAllowedHosts(), true)) {
            return 'Only api.github.com' . (count(sl_ghAllowedHosts()) > 1 ? ' and the GitHub Enterprise hosts listed in CDP_GH_ALLOWED_HOSTS' : ' (a GitHub Enterprise host must first be listed by the server owner in CDP_GH_ALLOWED_HOSTS)');
        }
        if (isset($p['port']) && (int) $p['port'] !== 443) {
            return 'Only the standard https port';
        }
        if ($host === 'api.github.com' ? $path !== '' : !in_array($path, ['', '/api/v3'], true)) {
            return 'Unexpected path in the API URL';
        }
        return '';
    }

    /**
     * Resolves an allowed https host once and checks every address: api.github.com must be public;
     * hosts listed by the server owner may be private but never loopback, link-local (169.254.x,
     * cloud metadata) or reserved. Returns the IP to pin with CURLOPT_RESOLVE, or '' when refused.
     */
    function sl_ghResolveHost(string $host, string &$why = ''): string
    {
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $why = 'IP addresses are not accepted';
            return '';
        }
        foreach ((array) @gethostbynamel($host) as $ip) {
            if ($ip) $ips[] = $ip;
        }
        if (function_exists('dns_get_record')) {
            foreach ((array) @dns_get_record($host, DNS_AAAA) as $r) {
                if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
            }
        }
        if (!$ips) {
            $why = 'Could not resolve ' . $host;
            return '';
        }
        $public = $host === 'api.github.com';
        foreach ($ips as $ip) {
            $flags = FILTER_FLAG_NO_RES_RANGE | ($public ? FILTER_FLAG_NO_PRIV_RANGE : 0);
            if (!filter_var($ip, FILTER_VALIDATE_IP, $flags) || preg_match('/^(fe80:|fc|fd|::ffff:)/i', $ip) && $public) {
                $why = $host . ' resolves to an address that is not allowed';
                return '';
            }
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $ip;
        }
        return '[' . $ips[0] . ']';
    }

    /** Deploy workflow keywords ("deploy|release|publish|cd"): plain words, matched case-insensitively (no regex). */
    function sl_ghDeployWords(string $pattern): array
    {
        $out = [];
        foreach (explode('|', $pattern) as $w) {
            $w = strtolower(trim($w));
            if ($w !== '') $out[] = $w;
        }
        return $out ?: ['deploy'];
    }

    function sl_ghIsDeployWorkflow(string $name, array $words): bool
    {
        foreach ($words as $w) {
            if (stripos($name, $w) !== false) return true;
        }
        return false;
    }

    /**
     * Keeps a link from the API only when it points at the repository's web host (https://github.com/…
     * or the GHE host), so a hostile or misconfigured API cannot store javascript:/data: links.
     */
    function sl_ghSafeUrl($u, ?array $cfg = null): string
    {
        $u = (string) $u;
        if ($u === '') return '';
        $cfg = $cfg ?? sl_ghConfig();
        $web = rtrim(sl_ghWebBase((string) $cfg['api_base']), '/');
        $okWeb = strncasecmp($u, $web . '/', strlen($web) + 1) === 0;
        $pu = parse_url($u);
        $pw = parse_url($web);
        if (!$okWeb && $pu && $pw && strtolower($pu['scheme'] ?? '') === 'https' && strtolower($pu['host'] ?? '') === strtolower($pw['host'] ?? '-')) {
            $okWeb = true;
        }
        return $okWeb && !preg_match('/[\s"<>\\\\]/', $u) ? mb_substr($u, 0, 255) : '';
    }

    /**
     * Saves the connection. A saved token is never carried over to another repository or API host:
     * changing owner, repo or API base needs the token entered again in the same request (or the
     * token removed). New settings with a token are tested first; nothing is saved and the cache is
     * kept when the test fails.
     */
    function sl_ghSaveConfig(array $in, ?string $token, bool $clearToken, int $actor): array
    {
        $c = cdp_devState('github', []);
        $c = is_array($c) ? $c : [];
        $err = [];
        $owner = trim((string) ($in['owner'] ?? ''));
        $repo = trim((string) ($in['repo'] ?? ''));
        // accept "owner/repo" or a github URL in the owner field
        if ($repo === '' && preg_match('#(?:github\.com[/:])?([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?/?$#', $owner, $m)) {
            [$owner, $repo] = [$m[1], $m[2]];
        }
        if (!preg_match('/^[A-Za-z0-9_.-]{1,100}$/', $owner)) $err['owner'] = 'Owner: letters, digits, - _ .';
        if (!preg_match('/^[A-Za-z0-9_.-]{1,100}$/', $repo)) $err['repo'] = 'Repository name: letters, digits, - _ .';
        $branch = trim((string) ($in['branch'] ?? ''));
        if ($branch !== '' && !preg_match('#^[A-Za-z0-9_./-]{1,120}$#', $branch)) $err['branch'] = 'Not a valid branch name';
        $base = rtrim(trim((string) ($in['api_base'] ?? '')), '/') ?: 'https://api.github.com';
        $bp = sl_ghBaseProblem($base);
        if ($bp !== '') $err['api_base'] = $bp;
        $pat = trim((string) ($in['deploy_pattern'] ?? '')) ?: 'deploy|release|publish|cd';
        $words = explode('|', $pat);
        if (strlen($pat) > 120 || count($words) > 12 || array_filter($words, fn($w) => !preg_match('/^[A-Za-z0-9 _.-]{1,40}$/', trim($w)))) {
            $err['deploy_pattern'] = 'Plain words separated by | (letters, digits, space - _ .)';
        }
        if ($token !== null && $token !== '' && !preg_match('/^[A-Za-z0-9_]{20,255}$/', $token)) $err['token'] = 'That does not look like a GitHub token';
        $newToken = $token !== null && $token !== '';
        $hadToken = (string) ($c['token_box'] ?? '') !== '';
        $changedRepo = ($c['owner'] ?? '') !== $owner || ($c['repo'] ?? '') !== $repo || rtrim((string) ($c['api_base'] ?? 'https://api.github.com'), '/') !== $base;
        if (!$err && $changedRepo && $hadToken && !$newToken && !$clearToken) {
            $err['token'] = 'Enter the token again (or remove it): a saved token is never sent to a different repository or API host.';
        }
        if ($err) {
            return ['ok' => false, 'errors' => $err];
        }
        $n = $c;
        $n['owner'] = $owner;
        $n['repo'] = $repo;
        $n['branch'] = $branch;
        $n['api_base'] = $base;
        $n['deploy_pattern'] = implode('|', array_map('trim', $words));
        if ($clearToken) {
            $n['token_box'] = '';
            $n['token_mask'] = '';
            $n['token_set_at'] = null;
            $n['token_set_by'] = null;
        } elseif ($newToken) {
            $box = sl_ghEncrypt($token);
            if ($box === null) {
                return ['ok' => false, 'errors' => ['token' => sl_ghPrivateDir() === '' && !(defined('CDP_APP_KEY') && (string) CDP_APP_KEY !== '')
                    ? 'No safe place for the encryption key: define CDP_APP_KEY in config/config.php, or make a writable folder outside the web root (CDP_PRIVATE_DIR or ../swiftlane_private).'
                    : 'This server cannot encrypt secrets (libsodium missing or the key file is not readable)']];
            }
            $n['token_box'] = $box;
            $n['token_mask'] = sl_ghMaskToken($token);
            $n['token_set_at'] = sl_ghNow();
            $n['token_set_by'] = $actor;
        }
        $tested = null;
        if ($changedRepo) {
            $n['last_sync'] = null;
            $n['last_status'] = '';
            $n['last_error'] = '';
            $n['repo_url'] = '';
            $n['default_branch'] = '';
            if ($newToken) {
                // test the new target before anything is saved or the cache is cleared
                $cand = $n + [];
                $cand['has_token'] = true;
                $cand['configured'] = true;
                $cand['ready'] = true;
                $tested = sl_ghTest($cand, false);
                if (!$tested['ok']) {
                    return ['ok' => false, 'errors' => ['api_base' => $tested['message']], 'message' => 'Not saved: ' . $tested['message']];
                }
                $n = array_merge($n, $tested['patch']);
            }
            sl_ghClearCache();
        }
        cdp_devStateSet('github', $n);
        return ['ok' => true, 'changedRepo' => $changedRepo, 'baseChanged' => rtrim((string) ($c['api_base'] ?? 'https://api.github.com'), '/') !== $base, 'tested' => $tested];
    }

    function sl_ghClearCache(): void
    {
        foreach (['cdb_dev_gh_commits', 'cdb_dev_gh_runs', 'cdb_dev_gh_releases', 'cdb_dev_gh_pulls', 'cdb_dev_gh_deployments'] as $t) {
            if (cdp_devTableExists($t)) {
                cdp_devX("DELETE FROM $t");
            }
        }
    }

    function sl_ghStatePatch(array $patch): void
    {
        $c = cdp_devState('github', []);
        cdp_devStateSet('github', array_merge(is_array($c) ? $c : [], $patch));
    }

    /* ------------------------------------------------------------- HTTP (GET) */

    /**
     * One read-only GET to the GitHub REST API. $path starts with "/".
     * Returns ['code' => int, 'json' => mixed, 'headers' => [lowercase => value], 'error' => string].
     */
    function sl_ghGet(string $path, array $query = [], ?array $cfg = null): array
    {
        $cfg = $cfg ?? sl_ghConfig();
        $base = rtrim((string) $cfg['api_base'], '/');
        if (!sl_ghValidBase($base)) {
            return ['code' => 0, 'json' => null, 'headers' => [], 'error' => 'API base URL not allowed'];
        }
        $pin = [];
        $pb = parse_url($base);
        if (strtolower($pb['scheme'] ?? '') === 'https') {
            // resolve once, refuse private/reserved addresses, and pin the address (no DNS rebinding)
            $why = '';
            $ip = sl_ghResolveHost(strtolower((string) $pb['host']), $why);
            if ($ip === '') {
                return ['code' => 0, 'json' => null, 'headers' => [], 'error' => $why];
            }
            $pin = [strtolower((string) $pb['host']) . ':443:' . $ip];
        }
        $token = sl_ghDecrypt($cfg['token_box'] ?? '');
        $url = $base . $path . ($query ? '?' . http_build_query($query) : '');
        $hdrs = ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28', 'User-Agent: SwiftLane-DevConsole'];
        if ($token) {
            $hdrs[] = 'Authorization: Bearer ' . $token;
        }
        $respHeaders = [];
        if (!function_exists('curl_init')) {
            return ['code' => 0, 'json' => null, 'headers' => [], 'error' => 'PHP curl extension missing'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPGET => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $hdrs,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP, CURLOPT_MAXFILESIZE => 20 * 1024 * 1024,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$respHeaders) {
                $p = strpos($line, ':');
                if ($p !== false) {
                    $respHeaders[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
                }
                return strlen($line);
            },
        ]);
        if ($pin) {
            curl_setopt($ch, CURLOPT_RESOLVE, $pin);
        }
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = $body === false ? 'connection failed (' . curl_errno($ch) . ')' : '';
        curl_close($ch);
        $json = is_string($body) && $body !== '' ? json_decode($body, true) : null;
        if ($code >= 400 && $err === '') {
            // generic text only: the remote body is never echoed back to the browser
            $err = 'HTTP ' . $code . ($code === 401 ? ' (token refused)' : ($code === 403 ? ' (forbidden or rate limited)' : ($code === 404 ? ' (not found)' : '')));
        }
        if (isset($respHeaders['x-ratelimit-remaining'])) {
            $GLOBALS['sl_gh_rate'] = ['remaining' => (int) $respHeaders['x-ratelimit-remaining'], 'reset' => isset($respHeaders['x-ratelimit-reset']) ? date('Y-m-d H:i:s', (int) $respHeaders['x-ratelimit-reset']) : null];
        }
        return ['code' => $code, 'json' => $json, 'headers' => $respHeaders, 'error' => $err];
    }

    function sl_ghRepoPath(array $cfg): string
    {
        return '/repos/' . rawurlencode($cfg['owner']) . '/' . rawurlencode($cfg['repo']);
    }

    /** Test the connection: reads the repository. Records default branch, privacy and a scope warning. */
    function sl_ghTest(?array $cfg = null, bool $persist = true): array
    {
        $cfg = $cfg ?? sl_ghConfig();
        if (!$cfg['configured']) {
            return ['ok' => false, 'message' => 'Set the repository owner and name first'];
        }
        $r = sl_ghGet(sl_ghRepoPath($cfg), [], $cfg);
        if ($r['code'] !== 200 || !is_array($r['json'])) {
            $msg = $r['code'] === 401 ? 'GitHub refused the token (401). Check that it is valid and not expired.'
                : ($r['code'] === 404 ? 'Repository not found (404): check owner/name, and that the token has access to it.'
                : ($r['code'] === 0 ? 'Could not reach ' . $cfg['api_base'] . ': ' . $r['error'] : 'GitHub answered ' . $r['error']));
            if ($persist) {
                sl_ghStatePatch(['last_status' => 'error', 'last_error' => $msg, 'tested_at' => sl_ghNow()]);
            }
            return ['ok' => false, 'message' => $msg];
        }
        $j = $r['json'];
        $warn = '';
        $scopes = strtolower((string) ($r['headers']['x-oauth-scopes'] ?? ''));
        if ($scopes !== '' && preg_match('/\b(repo|write:|admin:|workflow|delete_repo)\b/', $scopes)) {
            $warn = 'This classic token has write scopes (' . $scopes . '). The console only reads, but use a fine-grained read-only token.';
        }
        $perm = (array) ($j['permissions'] ?? []);
        if ($warn === '' && (!empty($perm['admin']) || !empty($perm['push'])) && $scopes === '' && empty($r['headers']['github-authentication-token-expiration'])) {
            // fine-grained tokens do not expose scopes; permissions show the account's role, not the token's
        }
        $patch = ['repo_url' => sl_ghSafeUrl($j['html_url'] ?? '', $cfg), 'default_branch' => preg_match('#^[A-Za-z0-9_./-]{1,120}$#', (string) ($j['default_branch'] ?? '')) ? (string) $j['default_branch'] : '', 'private' => !empty($j['private']),
            'scopes_warning' => $warn, 'tested_at' => sl_ghNow(), 'last_error' => ''];
        if (!empty($GLOBALS['sl_gh_rate'])) {
            $patch['rate_remaining'] = $GLOBALS['sl_gh_rate']['remaining'];
            $patch['rate_reset'] = $GLOBALS['sl_gh_rate']['reset'];
        }
        if (!empty($r['headers']['github-authentication-token-expiration'])) {
            $patch['token_expires'] = (string) $r['headers']['github-authentication-token-expiration'];
        }
        if ($persist) {
            sl_ghStatePatch($patch);
        }
        return ['ok' => true, 'message' => 'Connected to ' . $cfg['owner'] . '/' . $cfg['repo'] . ' · default branch ' . ($patch['default_branch'] ?: '?') . ($warn ? ' · ' . $warn : ''), 'patch' => $patch];
    }

    /* ------------------------------------------------------- commit messages */

    /** Conventional commit parse: type, scope, breaking, subject. Accepts "Feat(x): ..." and "Security: ...". */
    function sl_ghParseCommit(string $title): array
    {
        $map = ['feat' => 'feat', 'feature' => 'feat', 'features' => 'feat', 'add' => 'feat', 'fix' => 'fix', 'fixes' => 'fix', 'bugfix' => 'fix', 'hotfix' => 'fix',
            'security' => 'security', 'sec' => 'security', 'perf' => 'perf', 'performance' => 'perf', 'refactor' => 'refactor', 'docs' => 'docs', 'doc' => 'docs',
            'test' => 'test', 'tests' => 'test', 'build' => 'build', 'ci' => 'build', 'chore' => 'chore', 'style' => 'style', 'revert' => 'revert', 'deps' => 'build'];
        $out = ['type' => 'other', 'scope' => null, 'breaking' => false, 'subject' => $title, 'merge' => (bool) preg_match('/^Merge (pull request|branch|remote-tracking)/i', $title)];
        if (preg_match('/^([A-Za-z]+)(?:\(([^)]{1,60})\))?(!)?:\s*(.+)$/', $title, $m)) {
            $t = strtolower($m[1]);
            if (isset($map[$t])) {
                $out['type'] = $map[$t];
                $out['scope'] = $m[2] !== '' ? $m[2] : null;
                $out['breaking'] = $m[3] === '!';
                $out['subject'] = $m[4];
            }
        }
        if (preg_match('/BREAKING CHANGE/i', $title)) {
            $out['breaking'] = true;
        }
        return $out;
    }

    function sl_ghTypeLabels(): array
    {
        return ['feat' => 'Features', 'fix' => 'Fixes', 'security' => 'Security', 'perf' => 'Performance', 'refactor' => 'Refactoring', 'docs' => 'Documentation',
            'test' => 'Tests', 'build' => 'Build and CI', 'chore' => 'Chores', 'style' => 'Style', 'revert' => 'Reverts', 'other' => 'Other changes'];
    }

    /* ------------------------------------------------------------------- sync */

    /** Pull everything into the cache tables. $parts: any of commits, runs, releases, pulls, deployments. */
    function sl_ghSync(array $parts = []): array
    {
        sl_ghEnsureTables();
        $cfg = sl_ghConfig();
        if (!$cfg['ready']) {
            return ['ok' => false, 'message' => $cfg['configured'] ? 'Add a read-only token first' : 'Connect a repository first', 'counts' => []];
        }
        $parts = $parts ?: ['commits', 'runs', 'releases', 'pulls', 'deployments'];
        $rp = sl_ghRepoPath($cfg);
        $now = sl_ghNow();
        $counts = [];
        $errors = [];
        $branch = $cfg['branch'] ?: ($cfg['default_branch'] ?: '');
        if ($branch === '') {
            $t = sl_ghTest();
            if (!$t['ok']) {
                return ['ok' => false, 'message' => $t['message'], 'counts' => []];
            }
            $cfg = sl_ghConfig();
            $branch = $cfg['branch'] ?: $cfg['default_branch'];
        }
        $web = $cfg['web'];

        if (in_array('commits', $parts, true)) {
            $n = 0;
            for ($page = 1; $page <= 3; $page++) {
                $r = sl_ghGet($rp . '/commits', ['sha' => $branch, 'per_page' => 100, 'page' => $page], $cfg);
                if ($r['code'] !== 200 || !is_array($r['json'])) { $errors[] = 'commits: ' . $r['error']; break; }
                foreach ($r['json'] as $c) {
                    $sha = (string) ($c['sha'] ?? '');
                    if (!preg_match('/^[0-9a-f]{40}$/', $sha)) continue;
                    $msg = (string) ($c['commit']['message'] ?? '');
                    $lines = preg_split('/\r?\n/', $msg, 2);
                    $title = mb_substr(trim($lines[0]), 0, 255);
                    $p = sl_ghParseCommit($title);
                    cdp_devX('REPLACE INTO cdb_dev_gh_commits (sha, branch, title, body, ctype, cscope, breaking, is_merge, author, author_login, committed_at, url, synced_at)
                        VALUES (:s, :b, :t, :bo, :ty, :sc, :br, :m, :a, :al, :at, :u, :n)', [
                        ':s' => $sha, ':b' => $branch, ':t' => $title, ':bo' => mb_substr(trim($lines[1] ?? ''), 0, 4000), ':ty' => $p['type'], ':sc' => $p['scope'] ? mb_substr($p['scope'], 0, 60) : null,
                        ':br' => $p['breaking'] || stripos($msg, 'BREAKING CHANGE') !== false ? 1 : 0, ':m' => $p['merge'] || count((array) ($c['parents'] ?? [])) > 1 ? 1 : 0,
                        ':a' => mb_substr((string) ($c['commit']['author']['name'] ?? ''), 0, 120), ':al' => mb_substr((string) ($c['author']['login'] ?? ''), 0, 120),
                        ':at' => sl_ghTime($c['commit']['committer']['date'] ?? ($c['commit']['author']['date'] ?? null)), ':u' => sl_ghSafeUrl($c['html_url'] ?? '', $cfg) ?: $web . '/commit/' . $sha, ':n' => $now]);
                    $n++;
                }
                if (count($r['json']) < 100) break;
            }
            $counts['commits'] = $n;
        }

        if (in_array('runs', $parts, true)) {
            $n = 0;
            for ($page = 1; $page <= 2; $page++) {
                $r = sl_ghGet($rp . '/actions/runs', ['per_page' => 50, 'page' => $page], $cfg);
                if ($r['code'] !== 200 || !is_array($r['json'])) { $errors[] = 'actions: ' . $r['error']; break; }
                $runs = (array) ($r['json']['workflow_runs'] ?? []);
                foreach ($runs as $w) {
                    sl_ghStoreRun($w, $now);
                    $n++;
                }
                if (count($runs) < 50) break;
            }
            $counts['runs'] = $n;
            // jobs and steps for the newest runs (cached; refreshed while a run is still going)
            $need = cdp_devQ("SELECT id FROM cdb_dev_gh_runs WHERE jobs_at IS NULL OR status <> 'completed' OR jobs_at < updated_at ORDER BY created_at DESC LIMIT 12");
            $j = 0;
            foreach ($need as $row) {
                if (sl_ghFetchJobs((int) $row->id, $cfg)) $j++;
            }
            $counts['jobs'] = $j;
        }

        if (in_array('releases', $parts, true)) {
            $r = sl_ghGet($rp . '/releases', ['per_page' => 30], $cfg);
            $n = 0;
            if ($r['code'] === 200 && is_array($r['json'])) {
                foreach ($r['json'] as $rel) {
                    cdp_devX('REPLACE INTO cdb_dev_gh_releases (id, tag, name, target, draft, prerelease, author, published_at, url, body, synced_at) VALUES (:i, :t, :na, :ta, :d, :p, :a, :pa, :u, :b, :n)', [
                        ':i' => (int) $rel['id'], ':t' => mb_substr((string) ($rel['tag_name'] ?? ''), 0, 120), ':na' => mb_substr((string) ($rel['name'] ?? ''), 0, 255), ':ta' => mb_substr((string) ($rel['target_commitish'] ?? ''), 0, 160),
                        ':d' => !empty($rel['draft']) ? 1 : 0, ':p' => !empty($rel['prerelease']) ? 1 : 0, ':a' => mb_substr((string) ($rel['author']['login'] ?? ''), 0, 120),
                        ':pa' => sl_ghTime($rel['published_at'] ?? ($rel['created_at'] ?? null)), ':u' => sl_ghSafeUrl($rel['html_url'] ?? '', $cfg), ':b' => mb_substr((string) ($rel['body'] ?? ''), 0, 8000), ':n' => $now]);
                    $n++;
                }
            } else {
                $errors[] = 'releases: ' . $r['error'];
            }
            if ($n === 0) {
                // no releases: fall back to tags (no dates on tags; the tagged commit's date is used when cached)
                $t = sl_ghGet($rp . '/tags', ['per_page' => 30], $cfg);
                if ($t['code'] === 200 && is_array($t['json'])) {
                    foreach ($t['json'] as $tag) {
                        $sha = (string) ($tag['commit']['sha'] ?? '');
                        $at = cdp_devOne('SELECT committed_at FROM cdb_dev_gh_commits WHERE sha = :s', [':s' => $sha]);
                        cdp_devX('REPLACE INTO cdb_dev_gh_releases (id, tag, name, target, draft, prerelease, author, published_at, url, body, synced_at) VALUES (:i, :t, :na, :ta, 0, 0, NULL, :pa, :u, NULL, :n)', [
                            ':i' => -1 * (int) sprintf('%u', crc32((string) $tag['name'])), ':t' => mb_substr((string) $tag['name'], 0, 120), ':na' => mb_substr((string) $tag['name'], 0, 255), ':ta' => $sha,
                            ':pa' => $at->committed_at ?? null, ':u' => $web . '/releases/tag/' . rawurlencode((string) $tag['name']), ':n' => $now]);
                        $n++;
                    }
                }
            }
            $counts['releases'] = $n;
        }

        if (in_array('pulls', $parts, true)) {
            $r = sl_ghGet($rp . '/pulls', ['state' => 'all', 'sort' => 'updated', 'direction' => 'desc', 'per_page' => 30], $cfg);
            $n = 0;
            if ($r['code'] === 200 && is_array($r['json'])) {
                foreach ($r['json'] as $p) {
                    cdp_devX('REPLACE INTO cdb_dev_gh_pulls (number, title, state, draft, author, head_ref, base_ref, created_at, updated_at, merged_at, closed_at, url, synced_at) VALUES (:nu, :t, :s, :d, :a, :h, :b, :c, :u, :m, :cl, :url, :n)', [
                        ':nu' => (int) $p['number'], ':t' => mb_substr((string) ($p['title'] ?? ''), 0, 255), ':s' => !empty($p['merged_at']) ? 'merged' : mb_substr((string) ($p['state'] ?? ''), 0, 12),
                        ':d' => !empty($p['draft']) ? 1 : 0, ':a' => mb_substr((string) ($p['user']['login'] ?? ''), 0, 120), ':h' => mb_substr((string) ($p['head']['ref'] ?? ''), 0, 160), ':b' => mb_substr((string) ($p['base']['ref'] ?? ''), 0, 160),
                        ':c' => sl_ghTime($p['created_at'] ?? null), ':u' => sl_ghTime($p['updated_at'] ?? null), ':m' => sl_ghTime($p['merged_at'] ?? null), ':cl' => sl_ghTime($p['closed_at'] ?? null),
                        ':url' => sl_ghSafeUrl($p['html_url'] ?? '', $cfg), ':n' => $now]);
                    $n++;
                }
            } else {
                $errors[] = 'pulls: ' . $r['error'];
            }
            $counts['pulls'] = $n;
        }

        if (in_array('deployments', $parts, true)) {
            $r = sl_ghGet($rp . '/deployments', ['per_page' => 30], $cfg);
            $n = 0;
            if ($r['code'] === 200 && is_array($r['json'])) {
                foreach ($r['json'] as $i => $dp) {
                    $state = null;
                    $stateAt = null;
                    if ($i < 15) {
                        $s = sl_ghGet($rp . '/deployments/' . (int) $dp['id'] . '/statuses', ['per_page' => 1], $cfg);
                        if ($s['code'] === 200 && !empty($s['json'][0])) {
                            $state = (string) $s['json'][0]['state'];
                            $stateAt = sl_ghTime($s['json'][0]['created_at'] ?? null);
                        }
                    }
                    cdp_devX('REPLACE INTO cdb_dev_gh_deployments (id, environment, ref, sha, task, creator, created_at, state, state_at, url, synced_at) VALUES (:i, :e, :r, :s, :t, :c, :ca, :st, :sa, :u, :n)', [
                        ':i' => (int) $dp['id'], ':e' => mb_substr((string) ($dp['environment'] ?? ''), 0, 80), ':r' => mb_substr((string) ($dp['ref'] ?? ''), 0, 160), ':s' => preg_match('/^[0-9a-f]{40}$/', (string) ($dp['sha'] ?? '')) ? $dp['sha'] : null,
                        ':t' => mb_substr((string) ($dp['task'] ?? ''), 0, 60), ':c' => mb_substr((string) ($dp['creator']['login'] ?? ''), 0, 120), ':ca' => sl_ghTime($dp['created_at'] ?? null),
                        ':st' => $state, ':sa' => $stateAt, ':u' => $web . '/deployments', ':n' => $now]);
                    $n++;
                }
            } else {
                $errors[] = 'deployments: ' . $r['error'];
            }
            $counts['deployments'] = $n;
        }

        $patch = ['last_sync' => $now, 'last_status' => $errors ? (array_sum($counts) ? 'partial' : 'error') : 'ok', 'last_error' => implode(' · ', array_slice($errors, 0, 4)), 'last_counts' => $counts];
        if (!empty($GLOBALS['sl_gh_rate'])) {
            $patch['rate_remaining'] = $GLOBALS['sl_gh_rate']['remaining'];
            $patch['rate_reset'] = $GLOBALS['sl_gh_rate']['reset'];
        }
        sl_ghStatePatch($patch);
        $parts2 = [];
        foreach ($counts as $k => $v) $parts2[] = "$v $k";
        return ['ok' => !$errors || array_sum($counts) > 0, 'message' => ($errors ? 'Synced with errors: ' . implode(' · ', array_slice($errors, 0, 2)) . '. ' : 'Synced: ') . implode(', ', $parts2), 'counts' => $counts, 'errors' => $errors];
    }

    function sl_ghStoreRun(array $w, string $now): void
    {
        $started = sl_ghTime($w['run_started_at'] ?? ($w['created_at'] ?? null));
        $updated = sl_ghTime($w['updated_at'] ?? null);
        $dur = ($w['status'] ?? '') === 'completed' && $started && $updated ? max(0, strtotime($updated) - strtotime($started)) : null;
        cdp_devX('INSERT INTO cdb_dev_gh_runs (id, workflow_id, workflow_name, title, event, branch, head_sha, status, conclusion, run_number, run_attempt, actor, created_at, started_at, updated_at, duration_s, url, synced_at)
            VALUES (:i, :wi, :wn, :t, :e, :b, :h, :s, :c, :rn, :ra, :a, :ca, :sa, :ua, :d, :u, :n)
            ON DUPLICATE KEY UPDATE workflow_name = VALUES(workflow_name), title = VALUES(title), status = VALUES(status), conclusion = VALUES(conclusion), run_attempt = VALUES(run_attempt),
              created_at = VALUES(created_at), started_at = VALUES(started_at), updated_at = VALUES(updated_at), duration_s = VALUES(duration_s), url = VALUES(url), synced_at = VALUES(synced_at)', [
            ':i' => (int) $w['id'], ':wi' => isset($w['workflow_id']) ? (int) $w['workflow_id'] : null, ':wn' => mb_substr((string) ($w['name'] ?? ''), 0, 160),
            ':t' => mb_substr((string) ($w['display_title'] ?? ($w['head_commit']['message'] ?? '')), 0, 255), ':e' => mb_substr((string) ($w['event'] ?? ''), 0, 40),
            ':b' => mb_substr((string) ($w['head_branch'] ?? ''), 0, 160), ':h' => preg_match('/^[0-9a-f]{40}$/', (string) ($w['head_sha'] ?? '')) ? $w['head_sha'] : null,
            ':s' => mb_substr((string) ($w['status'] ?? ''), 0, 20), ':c' => isset($w['conclusion']) ? mb_substr((string) $w['conclusion'], 0, 20) : null,
            ':rn' => isset($w['run_number']) ? (int) $w['run_number'] : null, ':ra' => isset($w['run_attempt']) ? (int) $w['run_attempt'] : null,
            ':a' => mb_substr((string) ($w['actor']['login'] ?? ($w['triggering_actor']['login'] ?? '')), 0, 120), ':ca' => sl_ghTime($w['created_at'] ?? null), ':sa' => $started, ':ua' => $updated,
            ':d' => $dur, ':u' => sl_ghSafeUrl($w['html_url'] ?? ''), ':n' => $now]);
    }

    /** Fetch and cache the jobs (with steps) of one run. */
    function sl_ghFetchJobs(int $runId, ?array $cfg = null): bool
    {
        $cfg = $cfg ?? sl_ghConfig();
        if (!$cfg['ready'] || $runId <= 0) {
            return false;
        }
        $r = sl_ghGet(sl_ghRepoPath($cfg) . '/actions/runs/' . $runId . '/jobs', ['per_page' => 50], $cfg);
        if ($r['code'] !== 200 || !is_array($r['json'])) {
            return false;
        }
        $jobs = [];
        foreach ((array) ($r['json']['jobs'] ?? []) as $j) {
            $steps = [];
            foreach ((array) ($j['steps'] ?? []) as $s) {
                $steps[] = ['name' => mb_substr((string) ($s['name'] ?? ''), 0, 160), 'status' => (string) ($s['status'] ?? ''), 'conclusion' => (string) ($s['conclusion'] ?? ''),
                    'number' => (int) ($s['number'] ?? 0), 'started_at' => sl_ghTime($s['started_at'] ?? null), 'completed_at' => sl_ghTime($s['completed_at'] ?? null)];
            }
            $jobs[] = ['id' => (int) ($j['id'] ?? 0), 'name' => mb_substr((string) ($j['name'] ?? ''), 0, 160), 'status' => (string) ($j['status'] ?? ''), 'conclusion' => (string) ($j['conclusion'] ?? ''),
                'started_at' => sl_ghTime($j['started_at'] ?? null), 'completed_at' => sl_ghTime($j['completed_at'] ?? null), 'url' => sl_ghSafeUrl($j['html_url'] ?? '', $cfg),
                'runner' => mb_substr((string) ($j['runner_name'] ?? ''), 0, 120), 'labels' => array_slice(array_map('strval', (array) ($j['labels'] ?? [])), 0, 6), 'steps' => $steps];
        }
        return cdp_devX('UPDATE cdb_dev_gh_runs SET jobs_json = :j, jobs_at = :n WHERE id = :i', [':j' => json_encode($jobs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':n' => sl_ghNow(), ':i' => $runId]);
    }

    /* --------------------------------------------------------------- pipeline */

    /** Stage of a job or step name: tests, build, deploy, health (or null). */
    function sl_ghStageOf(string $name): ?string
    {
        $n = strtolower($name);
        if (preg_match('/health|smoke|verify|ping|uptime|post-?deploy check|status check/', $n)) return 'health';
        if (preg_match('/deploy|release|publish|upload|ftp|rsync|scp|ssh|rollout|ship/', $n)) return 'deploy';
        if (preg_match('/test|lint|phpunit|pest|jest|spec|phpstan|psalm|check|qa|audit|syntax/', $n)) return 'tests';
        if (preg_match('/build|compile|install|composer|npm|yarn|bundle|package|docker|artifact|setup|cache/', $n)) return 'build';
        return null;
    }

    /**
     * The five stages for one cached run: Code push > Tests > Build > Deploy > Health check.
     * Each: key, label, state (success|failure|running|skipped|absent), detail, duration_s, items (job/step names).
     */
    function sl_ghPipeline($run): array
    {
        $stages = ['code' => 'Code push', 'tests' => 'Tests', 'build' => 'Build', 'deploy' => 'Deploy', 'health' => 'Health check'];
        $out = [];
        foreach ($stages as $k => $l) {
            $out[$k] = ['key' => $k, 'label' => $l, 'state' => 'absent', 'detail' => 'Not in this workflow', 'duration_s' => null, 'items' => []];
        }
        if (!$run) {
            return array_values($out);
        }
        $out['code'] = ['key' => 'code', 'label' => 'Code push', 'state' => 'success', 'detail' => trim(($run->event ?: 'push') . ' · ' . ($run->branch ?: '') . ' @ ' . substr((string) $run->head_sha, 0, 7)), 'duration_s' => null, 'items' => [(string) $run->title]];
        $jobs = $run->jobs_json ? json_decode((string) $run->jobs_json, true) : null;
        if (!is_array($jobs)) {
            foreach (['tests', 'build', 'deploy', 'health'] as $k) {
                $out[$k]['detail'] = 'Jobs not loaded yet';
                $out[$k]['state'] = 'unknown';
            }
            return array_values($out);
        }
        $acc = [];
        foreach ($jobs as $j) {
            $jobStage = sl_ghStageOf((string) $j['name']);
            $units = [];
            foreach ((array) $j['steps'] as $s) {
                $st = sl_ghStageOf((string) $s['name']) ?? $jobStage;
                if ($st === null || preg_match('/^(set up job|complete job|post |run actions\/checkout|checkout)/i', (string) $s['name'])) {
                    continue;
                }
                $units[] = [$st, $s['name'], $s['status'], $s['conclusion'], $s['started_at'], $s['completed_at']];
            }
            if (!$units && $jobStage !== null) {
                $units[] = [$jobStage, $j['name'], $j['status'], $j['conclusion'], $j['started_at'], $j['completed_at']];
            }
            foreach ($units as $u) {
                $acc[$u[0]][] = $u;
            }
        }
        foreach ($acc as $k => $units) {
            $states = [];
            $dur = 0;
            $names = [];
            foreach ($units as $u) {
                $states[] = $u[2] !== 'completed' ? 'running' : ($u[3] ?: 'success');
                if ($u[4] && $u[5]) $dur += max(0, strtotime($u[5]) - strtotime($u[4]));
                $names[] = $u[1];
            }
            $state = in_array('failure', $states, true) || in_array('timed_out', $states, true) ? 'failure'
                : (in_array('running', $states, true) ? 'running' : (in_array('cancelled', $states, true) ? 'cancelled'
                : (count(array_filter($states, fn($s) => $s === 'skipped')) === count($states) ? 'skipped' : 'success')));
            $out[$k]['state'] = $state;
            $out[$k]['duration_s'] = $dur ?: null;
            $out[$k]['items'] = array_slice(array_values(array_unique($names)), 0, 8);
            $out[$k]['detail'] = count($names) . ' step' . (count($names) === 1 ? '' : 's') . ($dur ? ' · ' . sl_ghDur($dur) : '');
        }
        return array_values($out);
    }

    function sl_ghDur($s): string
    {
        if ($s === null || $s === '') return '—';
        $s = (int) $s;
        if ($s < 60) return $s . 's';
        if ($s < 3600) return intdiv($s, 60) . 'm ' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT) . 's';
        return intdiv($s, 3600) . 'h ' . intdiv($s % 3600, 60) . 'm';
    }

    /** Status of a run in one word: success, failure, cancelled, running, queued, skipped. */
    function sl_ghRunState($r): string
    {
        if (($r->status ?? '') !== 'completed') {
            return in_array($r->status ?? '', ['queued', 'waiting', 'requested', 'pending'], true) ? 'queued' : 'running';
        }
        $c = (string) ($r->conclusion ?? '');
        if (in_array($c, ['failure', 'timed_out', 'startup_failure', 'action_required'], true)) return 'failure';
        if ($c === 'cancelled') return 'cancelled';
        if (in_array($c, ['skipped', 'neutral', 'stale'], true)) return 'skipped';
        return 'success';
    }

    /* -------------------------------------------------------------- change log */

    /**
     * Change log from cached GitHub commits (or the local reflog when GitHub is not connected).
     * Groups: by release (commits after the previous release and up to this one; newer = Unreleased)
     * or by day. Returns ['source', 'groups' => [['key','title','date','tag','url','n','sections' => [type => [items]]]]].
     */
    function sl_ghChangelog(array $o = []): array
    {
        $o += ['group' => 'release', 'type' => '', 'q' => '', 'merges' => false, 'limit' => 300];
        $commits = [];
        $source = 'github';
        if (cdp_devTableExists('cdb_dev_gh_commits')) {
            foreach (cdp_devQ('SELECT sha, title, ctype, cscope, breaking, is_merge, author, author_login, committed_at, url FROM cdb_dev_gh_commits ORDER BY committed_at DESC LIMIT ' . (int) $o['limit']) as $c) {
                $commits[] = ['sha' => $c->sha, 'title' => $c->title, 'type' => $c->ctype, 'scope' => $c->cscope, 'breaking' => (bool) $c->breaking, 'merge' => (bool) $c->is_merge,
                    'author' => $c->author_login ?: $c->author, 'at' => $c->committed_at, 'url' => $c->url];
            }
        }
        if (!$commits) {
            $source = 'local';
            foreach (cdp_devGitLog(200) as $e) {
                if (!in_array($e['kind'], ['commit', 'commit (merge)', 'commit (amend)', 'merge', 'pull'], true) && strpos($e['kind'], 'commit') !== 0) continue;
                $p = sl_ghParseCommit($e['message']);
                $commits[] = ['sha' => $e['full'], 'title' => $e['message'], 'type' => $p['type'], 'scope' => $p['scope'], 'breaking' => $p['breaking'], 'merge' => $p['merge'] || $e['kind'] === 'merge',
                    'author' => $e['author'], 'at' => $e['ts'], 'url' => ''];
            }
            if (!$commits) $source = 'none';
        }
        $all = count($commits);
        $commits = array_values(array_filter($commits, function ($c) use ($o) {
            if (!$o['merges'] && $c['merge']) return false;
            if ($o['q'] !== '' && stripos($c['title'] . ' ' . $c['author'] . ' ' . $c['sha'], $o['q']) === false) return false;
            return true;
        }));
        $typeCounts = [];
        foreach ($commits as $c) $typeCounts[$c['type']] = ($typeCounts[$c['type']] ?? 0) + 1;
        if ($o['type'] !== '') {
            $commits = array_values(array_filter($commits, fn($c) => $c['type'] === $o['type']));
        }
        $releases = cdp_devTableExists('cdb_dev_gh_releases') ? cdp_devQ('SELECT tag, name, published_at, url, prerelease FROM cdb_dev_gh_releases WHERE draft = 0 AND published_at IS NOT NULL ORDER BY published_at DESC LIMIT 40') : [];
        $groups = [];
        $labels = sl_ghTypeLabels();
        $add = function (string $key, array $meta, array $c) use (&$groups) {
            if (!isset($groups[$key])) {
                $groups[$key] = $meta + ['n' => 0, 'sections' => [], 'authors' => []];
            }
            $groups[$key]['sections'][$c['type']][] = $c;
            $groups[$key]['n']++;
            $groups[$key]['authors'][$c['author']] = true;
        };
        foreach ($commits as $c) {
            if ($o['group'] === 'release' && $releases) {
                $rel = null;
                foreach (array_reverse($releases) as $r) { // oldest first: first release published at/after the commit
                    if ($c['at'] && strtotime($r->published_at) >= strtotime($c['at'])) { $rel = $r; break; }
                }
                if ($rel) {
                    $add('r:' . $rel->tag, ['key' => $rel->tag, 'title' => $rel->name ?: $rel->tag, 'tag' => $rel->tag, 'date' => $rel->published_at, 'url' => $rel->url, 'pre' => (bool) $rel->prerelease], $c);
                } else {
                    $add('unreleased', ['key' => 'unreleased', 'title' => 'Unreleased', 'tag' => '', 'date' => $c['at'], 'url' => '', 'pre' => false], $c);
                }
            } else {
                $day = $c['at'] ? substr($c['at'], 0, 10) : 'unknown';
                $add('d:' . $day, ['key' => $day, 'title' => $day !== 'unknown' ? date('l, M j, Y', strtotime($day)) : 'Unknown date', 'tag' => '', 'date' => $c['at'], 'url' => '', 'pre' => false], $c);
            }
        }
        // order sections by the label order
        foreach ($groups as &$g) {
            $sorted = [];
            foreach (array_keys($labels) as $t) {
                if (!empty($g['sections'][$t])) $sorted[$t] = $g['sections'][$t];
            }
            $g['sections'] = $sorted;
            $g['authors'] = array_keys($g['authors']);
        }
        unset($g);
        return ['source' => $source, 'groups' => array_values($groups), 'total' => $all, 'shown' => count($commits), 'types' => $typeCounts, 'labels' => $labels, 'hasReleases' => (bool) $releases];
    }

    /* ------------------------------------------------------------ deployments */

    /**
     * One list of deployments from: GitHub Actions runs of deploy workflows (or all runs when no
     * workflow name matches the pattern), GitHub deployments, manual records and this server's
     * checked-out commit. Each row: source, id, title, sha, branch, env, state, duration_s, actor, at, url, run_id.
     */
    function sl_ghDeployments(array $f = []): array
    {
        $cfg = sl_ghConfig();
        $rows = [];
        $words = sl_ghDeployWords((string) ($cfg['deploy_pattern'] ?: 'deploy'));
        $runs = cdp_devTableExists('cdb_dev_gh_runs') ? cdp_devQ('SELECT id, workflow_name, title, event, branch, head_sha, status, conclusion, run_number, actor, created_at, started_at, updated_at, duration_s, url, jobs_json IS NOT NULL AS has_jobs FROM cdb_dev_gh_runs ORDER BY created_at DESC LIMIT 200') : [];
        $deployRuns = array_values(array_filter($runs, fn($r) => sl_ghIsDeployWorkflow((string) $r->workflow_name, $words)));
        $allRuns = !$deployRuns;
        $useRuns = $allRuns ? $runs : $deployRuns;
        $ghDeps = cdp_devTableExists('cdb_dev_gh_deployments') ? cdp_devQ('SELECT * FROM cdb_dev_gh_deployments ORDER BY created_at DESC LIMIT 60') : [];
        // A deploy workflow that creates a GitHub deployment is one deployment, not two: the GitHub
        // deployment with the same sha created inside the run's window (start - 1 min .. end + 5 min)
        // is merged into the run, which takes its environment. Unmatched GitHub deployments stay rows.
        $merged = [];
        foreach ($useRuns as $r) {
            $env = '';
            $t0 = strtotime((string) ($r->started_at ?: $r->created_at)) - 60;
            $t1 = strtotime((string) ($r->updated_at ?: $r->created_at)) + 300;
            foreach ($ghDeps as $d) {
                if (isset($merged[$d->id]) || !$d->sha || $d->sha !== $r->head_sha || !$d->created_at) continue;
                $t = strtotime((string) $d->created_at);
                if ($t >= $t0 && $t <= $t1) {
                    $merged[$d->id] = true;
                    $env = (string) $d->environment;
                    break;
                }
            }
            $rows[] = ['source' => 'actions', 'id' => 'run-' . $r->id, 'run_id' => (int) $r->id, 'title' => $r->title ?: $r->workflow_name, 'workflow' => $r->workflow_name . ($r->run_number ? ' #' . $r->run_number : '') . ($env !== '' ? ' · GitHub deployment' : ''),
                'sha' => (string) $r->head_sha, 'branch' => (string) $r->branch, 'env' => $env, 'state' => sl_ghRunState($r), 'duration_s' => $r->duration_s !== null ? (int) $r->duration_s : null,
                'actor' => (string) $r->actor, 'at' => $r->created_at, 'url' => (string) $r->url, 'event' => (string) $r->event];
        }
        foreach ($ghDeps as $d) {
            if (isset($merged[$d->id])) continue;
            $st = in_array($d->state, ['success'], true) ? 'success' : (in_array($d->state, ['failure', 'error'], true) ? 'failure' : ($d->state === 'inactive' ? 'superseded' : ($d->state ? 'running' : 'unknown')));
            $dur = $d->state_at && $d->created_at ? max(0, strtotime($d->state_at) - strtotime($d->created_at)) : null;
            $rows[] = ['source' => 'github', 'id' => 'dep-' . $d->id, 'run_id' => 0, 'title' => ($d->task ?: 'deploy') . ' to ' . ($d->environment ?: '?'), 'workflow' => 'GitHub deployment',
                'sha' => (string) $d->sha, 'branch' => (string) $d->ref, 'env' => (string) $d->environment, 'state' => $st, 'duration_s' => in_array($st, ['success', 'failure'], true) ? $dur : null,
                'actor' => (string) $d->creator, 'at' => $d->created_at, 'url' => (string) $d->url, 'event' => 'deployment'];
        }
        if (cdp_devTableExists('cdb_dev_deploy_records')) {
            foreach (cdp_devQ("SELECT d.*, CONCAT(u.fname,' ',u.lname) AS who FROM cdb_dev_deploy_records d LEFT JOIN cdb_users u ON u.id = d.recorded_by ORDER BY d.deployed_at DESC LIMIT 100") as $m) {
                $rows[] = ['source' => 'manual', 'id' => 'man-' . $m->id, 'run_id' => 0, 'title' => $m->notes ?: ('Version ' . ($m->version ?: '?')), 'workflow' => 'Recorded by hand' . ($m->version ? ' · ' . $m->version : ''),
                    'sha' => (string) $m->commit_sha, 'branch' => (string) $m->branch, 'env' => (string) $m->environment, 'state' => $m->status, 'duration_s' => $m->duration_s !== null ? (int) $m->duration_s : null,
                    'actor' => trim((string) $m->who), 'at' => $m->deployed_at, 'url' => '', 'event' => 'manual'];
            }
        }
        // this server: the commit checked out here (read from .git without a shell)
        $head = cdp_devGitHead();
        $server = null;
        if ($head && $head['hash']) {
            $log = cdp_devGitLog(1);
            $server = ['sha' => $head['hash'], 'branch' => (string) $head['branch'], 'at' => $log[0]['ts'] ?? null, 'message' => $log[0]['message'] ?? '', 'author' => $log[0]['author'] ?? '', 'kind' => $log[0]['kind'] ?? ''];
            $title = '';
            if (cdp_devTableExists('cdb_dev_gh_commits')) {
                $c = cdp_devOne('SELECT title FROM cdb_dev_gh_commits WHERE sha = :s', [':s' => $head['hash']]);
                $title = $c->title ?? '';
                $server['on_github'] = (bool) $c;
            }
            // state 'live': what runs here now; not a pipeline result, so never counted in success/failure
            $rows[] = ['source' => 'server', 'id' => 'srv', 'run_id' => 0, 'title' => $title ?: ($server['message'] ?: 'Checked out on this server'), 'workflow' => 'This server (' . ($server['kind'] ?: 'git') . ')',
                'sha' => $head['hash'], 'branch' => (string) $head['branch'], 'env' => 'this server', 'state' => 'live', 'duration_s' => null, 'actor' => (string) $server['author'],
                'at' => $server['at'], 'url' => $cfg['ready'] ? $cfg['web'] . '/commit/' . $head['hash'] : '', 'event' => 'checkout'];
        }
        usort($rows, fn($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
        $branches = array_values(array_unique(array_filter(array_column($rows, 'branch'))));
        $envs = array_values(array_unique(array_filter(array_column($rows, 'env'))));
        sort($branches);
        sort($envs);
        $filtered = array_values(array_filter($rows, function ($r) use ($f) {
            if (!empty($f['branch']) && $r['branch'] !== $f['branch']) return false;
            if (!empty($f['env']) && $r['env'] !== $f['env']) return false;
            if (!empty($f['status']) && $r['state'] !== $f['status']) return false;
            if (!empty($f['source']) && $r['source'] !== $f['source']) return false;
            return true;
        }));
        return ['rows' => $filtered, 'all' => $rows, 'branches' => $branches, 'envs' => $envs, 'server' => $server, 'allRuns' => $allRuns && $runs, 'runCount' => count($runs), 'deployRunCount' => count($deployRuns)];
    }

    /* ======================================================== database tools */

    /**
     * Console backups live in <private folder>/backups: outside the web root, folder 0700, files 0600,
     * served only through ajax/sl/dev_db_upload_ajax.php (password + single-use download link).
     * Returns '' when there is no private folder: backups are then refused rather than written into
     * the web root, where nginx or the PHP built-in server would serve them.
     */
    function sl_dbBackupDir(): string
    {
        return sl_ghPrivateSub('backups');
    }

    /** The web-root backups/ folder: only read, to list and move console backups made before. */
    function sl_dbLegacyDir(): string
    {
        return CDP_ROOT . '/backups';
    }

    /** Console backup file names: backup_<d-M-Y_H-i-s>_<12 hex>.sql */
    function sl_dbIsConsoleName(string $n): bool
    {
        return (bool) preg_match('/^backup_\d{2}-[A-Za-z]{3}-\d{4}_\d{2}-\d{2}-\d{2}_[0-9a-f]{12}\.sql$/', $n);
    }

    function sl_dbStageDir(): string
    {
        return sl_ghPrivateSub('db_uploads');
    }

    /** Upload limit in bytes: 128 MB, or less when PHP's upload/post limits are lower. */
    function sl_dbUploadLimit(): int
    {
        $toBytes = function ($v) {
            $v = trim((string) $v);
            if ($v === '' || $v === '-1' || $v === '0') return PHP_INT_MAX;
            $n = (float) $v;
            switch (strtolower(substr($v, -1))) {
                case 'g': $n *= 1024;
                // no break
                case 'm': $n *= 1024;
                // no break
                case 'k': $n *= 1024;
            }
            return (int) $n;
        };
        return (int) min(128 * 1024 * 1024, $toBytes(ini_get('upload_max_filesize')), $toBytes(ini_get('post_max_size')));
    }

    /** Largest unpacked size of a .sql.gz upload: 8 x the upload limit, at most 1 GB (gzip bombs). */
    function sl_dbGzipCap(): int
    {
        return (int) min(1024 * 1024 * 1024, 8 * sl_dbUploadLimit());
    }

    /** How many console backups are kept (older ones are deleted after each new backup). */
    function sl_dbKeepBackups(): int
    {
        return defined('CDP_DEV_BACKUP_KEEP') ? max(2, (int) CDP_DEV_BACKUP_KEEP) : 10;
    }

    /**
     * A dedicated PDO (not the shared persistent one) so a long import does not hold the app's handle.
     * Multi-statement execution is off, and the session sql_mode drops NO_BACKSLASH_ESCAPES and
     * ANSI_QUOTES so MySQL tokenizes strings exactly as sl_dbStrictInsert() does.
     */
    function sl_dbPdo(): PDO
    {
        $host = CDP_DB_HOST;
        $port = defined('CDP_DB_PORT') ? (int) CDP_DB_PORT : 0;
        if (strpos($host, ':') !== false && strpos($host, '/') === false) {
            [$host, $p] = explode(':', $host, 2);
            $port = (int) $p;
        }
        $pdo = new PDO('mysql:host=' . $host . ($port > 0 ? ';port=' . $port : '') . ';dbname=' . CDP_DB_NAME . ';charset=utf8mb4', CDP_DB_USER, CDP_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_PERSISTENT => false, PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]);
        $mode = (string) $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        $keep = array_filter(array_map('trim', explode(',', $mode)), fn($m) => $m !== '' && !in_array(strtoupper($m), ['NO_BACKSLASH_ESCAPES', 'ANSI_QUOTES', 'ANSI'], true));
        $pdo->exec('SET SESSION sql_mode = ' . $pdo->quote(implode(',', $keep)));
        return $pdo;
    }

    /** Tables an upload may never write (the console's own access and the audit trail), in either mode. */
    function sl_dbProtected(string $t): bool
    {
        return (bool) preg_match('/^(cdb_dev_|cdb_activity_log$|cdb_schema_migrations$)/', $t);
    }

    /** Accounts, roles and permissions: only the console owner may import them (either mode). */
    function sl_dbOwnerOnlyTable(string $t): bool
    {
        return in_array($t, ['cdb_users', 'cdb_user_roles', 'cdb_user_role_permissions', 'cdb_user_permission_overrides', 'cdb_user_module_permissions', 'cdb_department_permissions'], true);
    }

    function sl_dbJobStart(string $kind, array $f = []): int
    {
        cdp_devX('INSERT INTO cdb_dev_db_jobs (kind, status, mode, file_name, stored_name, file_bytes, sha256, user_id, started_at, log) VALUES (:k, :s, :m, :fn, :sn, :b, :h, :u, :n, :l)', [
            ':k' => $kind, ':s' => $f['status'] ?? 'running', ':m' => $f['mode'] ?? null, ':fn' => $f['file_name'] ?? null, ':sn' => $f['stored_name'] ?? null,
            ':b' => $f['file_bytes'] ?? null, ':h' => $f['sha256'] ?? null, ':u' => (int) ($f['user_id'] ?? ($_SESSION['userid'] ?? 0)) ?: null, ':n' => sl_ghNow(), ':l' => '']);
        $db = new Conexion;
        return (int) $db->dbh->lastInsertId();
    }

    function sl_dbJobLog(int $id, string $line): void
    {
        cdp_devX("UPDATE cdb_dev_db_jobs SET log = CONCAT(COALESCE(log,''), :l) WHERE id = :i", [':l' => '[' . date('H:i:s') . '] ' . $line . "\n", ':i' => $id]);
    }

    function sl_dbJobEnd(int $id, string $status, array $f = []): void
    {
        $set = 'status = :s, finished_at = :n';
        $b = [':s' => $status, ':n' => sl_ghNow(), ':i' => $id];
        foreach (['tables_n', 'backup_file', 'mode'] as $k) {
            if (array_key_exists($k, $f)) {
                $set .= ", $k = :$k";
                $b[":$k"] = $f[$k];
            }
        }
        cdp_devX("UPDATE cdb_dev_db_jobs SET $set WHERE id = :i", $b);
    }

    /**
     * Housekeeping on every database page load and upload: staged uploads older than an hour are
     * deleted and their jobs closed as "expired"; jobs still "running" after two hours were cut off
     * (proxy timeout, killed worker) and are closed as "interrupted".
     */
    function sl_dbJobsTidy(): void
    {
        if (!cdp_devTableExists('cdb_dev_db_jobs')) return;
        $old = date('Y-m-d H:i:s', time() - 3600);
        $dir = sl_dbStageDir();
        foreach (cdp_devQ("SELECT id, stored_name FROM cdb_dev_db_jobs WHERE status = 'staged' AND started_at < :o", [':o' => $old]) as $j) {
            if ($dir !== '' && $j->stored_name && preg_match('/^stage_[0-9a-f]{24}\.sql$/', (string) $j->stored_name)) {
                @unlink($dir . '/' . $j->stored_name);
            }
            sl_dbJobLog((int) $j->id, 'Expired: not imported within an hour, file removed');
            sl_dbJobEnd((int) $j->id, 'expired');
        }
        foreach (cdp_devQ("SELECT id FROM cdb_dev_db_jobs WHERE status = 'running' AND started_at < :o", [':o' => date('Y-m-d H:i:s', time() - 7200)]) as $j) {
            sl_dbJobLog((int) $j->id, 'Interrupted: the request ended before the job finished (time limit or proxy timeout). An import is rolled back in that case.');
            sl_dbJobEnd((int) $j->id, 'interrupted');
        }
        if ($dir !== '') {
            foreach (glob($dir . '/stage_*.sql') ?: [] as $f) {
                if (filemtime($f) < time() - 3600) @unlink($f);
            }
        }
    }

    /** Kept for callers of the first version. */
    function sl_dbPurgeStaged(): void
    {
        sl_dbJobsTidy();
    }

    /**
     * Streaming backup of every base table into <private>/backups/backup_<date>_<random>.sql: DROP +
     * CREATE + INSERT with explicit columns (re-importable through this console). Refused when there
     * is no private folder or not enough free disk. Keeps the newest sl_dbKeepBackups() console backups.
     * Returns ['ok', 'file', 'bytes', 'tables', 'rows', 'message'].
     */
    function sl_dbBackupNow(string $reason = 'manual', ?int $jobId = null): array
    {
        @set_time_limit(1800);
        $own = $jobId === null;
        if ($own) {
            $jobId = sl_dbJobStart('backup', ['mode' => mb_substr($reason, 0, 20)]);
        }
        $fail = function (string $msg) use ($own, $jobId) {
            sl_dbJobLog($jobId, 'Backup failed: ' . $msg);
            if ($own) sl_dbJobEnd($jobId, 'failed');
            return ['ok' => false, 'message' => $msg, 'file' => '', 'bytes' => 0, 'tables' => 0, 'rows' => 0, 'job' => $jobId];
        };
        $dir = sl_dbBackupDir();
        if ($dir === '') {
            return $fail('No private folder outside the web root for backups. Create ../swiftlane_private (writable by PHP) or set CDP_PRIVATE_DIR.');
        }
        $dbBytes = (int) (cdp_devOne('SELECT SUM(DATA_LENGTH) AS b FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->b ?? 0);
        $free = @disk_free_space($dir);
        if ($free !== false && $free < $dbBytes * 1.5 + 50 * 1024 * 1024) {
            return $fail('Not enough free disk for a backup (' . sl_dbBytes($free) . ' free, about ' . sl_dbBytes($dbBytes) . ' of data).');
        }
        $name = 'backup_' . date('d-M-Y_H-i-s') . '_' . bin2hex(random_bytes(6)) . '.sql';
        $path = $dir . '/' . $name;
        $old = umask(0077);
        $fh = @fopen($path, 'xb');
        umask($old);
        if (!$fh) {
            return $fail('The private backups folder is not writable');
        }
        @chmod($path, 0600);
        $tables = 0;
        $rows = 0;
        try {
            $pdo = sl_dbPdo();
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $list = [];
            $q = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            foreach ($q->fetchAll(PDO::FETCH_NUM) as $t) $list[] = $t[0];
            $q->closeCursor();
            fwrite($fh, "-- Swift Lane database backup\n-- Database: " . CDP_DB_NAME . "\n-- Created: " . date('c') . " ($reason)\n-- Tables: " . count($list) . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            sl_dbJobLog($jobId, 'Backing up ' . count($list) . ' tables to the private backups folder (' . $name . ')');
            foreach ($list as $t) {
                if (!preg_match('/^[A-Za-z0-9_$]+$/', $t)) continue;
                $c = $pdo->query('SHOW CREATE TABLE `' . $t . '`');
                $create = $c->fetch(PDO::FETCH_NUM)[1] ?? '';
                $c->closeCursor();
                fwrite($fh, "DROP TABLE IF EXISTS `$t`;\n$create;\n\n");
                $cols = null;
                $batch = [];
                $size = 0;
                $st = $pdo->query('SELECT * FROM `' . $t . '`');
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    if ($cols === null) $cols = '(`' . implode('`, `', array_keys($r)) . '`)';
                    $vals = [];
                    foreach ($r as $v) $vals[] = $v === null ? 'NULL' : $pdo->quote((string) $v);
                    $line = '(' . implode(',', $vals) . ')';
                    $batch[] = $line;
                    $size += strlen($line);
                    $rows++;
                    if (count($batch) >= 300 || $size > 1048576) {
                        fwrite($fh, "INSERT INTO `$t` $cols VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                        $size = 0;
                    }
                }
                $st->closeCursor();
                if ($batch) fwrite($fh, "INSERT INTO `$t` $cols VALUES\n" . implode(",\n", $batch) . ";\n");
                fwrite($fh, "\n");
                $tables++;
            }
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fh);
        } catch (Throwable $e) {
            if (is_resource($fh)) fclose($fh);
            @unlink($path);
            $r = $fail('Backup failed: ' . $e->getMessage());
            $r['tables'] = $tables;
            return $r;
        }
        $bytes = (int) filesize($path);
        sl_dbJobLog($jobId, "Backup written: $tables tables, $rows rows, " . sl_dbBytes($bytes));
        $removed = sl_dbBackupRetention($name);
        if ($removed) sl_dbJobLog($jobId, 'Retention: removed ' . $removed . ' older backup' . ($removed === 1 ? '' : 's') . ' (keeping ' . sl_dbKeepBackups() . ')');
        if ($own) sl_dbJobEnd($jobId, 'done', ['tables_n' => $tables, 'backup_file' => $name]);
        return ['ok' => true, 'file' => $name, 'bytes' => $bytes, 'tables' => $tables, 'rows' => $rows, 'message' => "Backup made: $tables tables, " . number_format($rows) . ' rows, ' . sl_dbBytes($bytes), 'job' => $jobId];
    }

    /** Deletes console backups beyond the newest sl_dbKeepBackups() in the private folder. Returns how many. */
    function sl_dbBackupRetention(string $keepName = ''): int
    {
        $dir = sl_dbBackupDir();
        if ($dir === '') return 0;
        $files = [];
        foreach (glob($dir . '/backup_*.sql') ?: [] as $f) {
            if (sl_dbIsConsoleName(basename($f))) $files[$f] = (int) filemtime($f);
        }
        arsort($files);
        $n = 0;
        foreach (array_slice(array_keys($files), sl_dbKeepBackups()) as $f) {
            if (basename($f) !== $keepName && @unlink($f)) $n++;
        }
        return $n;
    }

    function sl_dbBytes($b): string
    {
        $b = (float) $b;
        foreach (['B', 'KB', 'MB', 'GB'] as $u) {
            if ($b < 1024 || $u === 'GB') return ($u === 'B' ? (int) $b : number_format($b, 1)) . ' ' . $u;
            $b /= 1024;
        }
        return '';
    }

    /** Backup files, newest first: the private folder, plus console backups still in the web-root backups/ ('legacy'). */
    function sl_dbBackups(): array
    {
        $out = [];
        $dir = sl_dbBackupDir();
        foreach ($dir !== '' ? (glob($dir . '/*.sql') ?: []) : [] as $f) {
            $out[] = ['name' => basename($f), 'bytes' => (int) filesize($f), 'at' => date('Y-m-d H:i:s', (int) filemtime($f)), 'legacy' => false];
        }
        $leg = sl_dbLegacyDir();
        foreach (is_dir($leg) ? (glob($leg . '/backup_*.sql') ?: []) : [] as $f) {
            if (sl_dbIsConsoleName(basename($f))) {
                $out[] = ['name' => basename($f), 'bytes' => (int) filesize($f), 'at' => date('Y-m-d H:i:s', (int) filemtime($f)), 'legacy' => true];
            }
        }
        usort($out, fn($a, $b) => strcmp($b['at'], $a['at']));
        return $out;
    }

    /** Resolve a backup file name to a path (private folder, or a console backup left in backups/); no traversal. */
    function sl_dbBackupPath(string $name): ?string
    {
        if (!preg_match('/^[A-Za-z0-9_.-]{1,190}\.sql$/', $name)) return null;
        $dirs = [];
        if (sl_dbBackupDir() !== '') $dirs[] = sl_dbBackupDir();
        if (sl_dbIsConsoleName($name)) $dirs[] = sl_dbLegacyDir();
        foreach ($dirs as $d) {
            $real = realpath($d . '/' . $name);
            $dir = realpath($d);
            if ($real && $dir && dirname($real) === $dir && is_file($real)) return $real;
        }
        return null;
    }

    /** Moves console backups from the web-root backups/ into the private folder. Returns [moved, failed]. */
    function sl_dbMoveLegacy(): array
    {
        $dir = sl_dbBackupDir();
        $moved = 0;
        $failed = 0;
        if ($dir === '') return [0, 0];
        foreach (glob(sl_dbLegacyDir() . '/backup_*.sql') ?: [] as $f) {
            if (!sl_dbIsConsoleName(basename($f))) continue;
            $to = $dir . '/' . basename($f);
            if (!is_file($to) && @rename($f, $to)) {
                @chmod($to, 0600);
                $moved++;
            } else {
                $failed++;
            }
        }
        return [$moved, $failed];
    }

    /** Row counts for the tables a plan touches (to show what would be replaced). */
    function sl_dbExistingRows(array $tables): array
    {
        $out = [];
        foreach ($tables as $t) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $t) || !cdp_devTableExists($t)) {
                $out[$t] = null;
                continue;
            }
            $r = cdp_devOne('SELECT TABLE_ROWS AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t', [':t' => $t]);
            $exact = cdp_devOne("SELECT COUNT(*) AS n FROM (SELECT 1 FROM `$t` LIMIT 100001) x");
            $out[$t] = $exact ? (int) $exact->n : (int) ($r->n ?? 0);
        }
        return $out;
    }

    /**
     * Strict check of one INSERT from a dump:
     *     INSERT INTO `t` [(`c`, …)] VALUES (literal, …)[, (…)]…
     * where every value is a quoted string ('…' with \ escapes or ''), a number, NULL / TRUE / FALSE,
     * 0x…, X'…', b'…' or a charset introducer before a string (_binary '…'). Anything else — comments,
     * a second statement, ON DUPLICATE KEY, SELECT, function calls, double-quoted text — is refused.
     * The statement splitter of helpers/database_import.php does not know SQL comments, so this check
     * (together with no multi-statements on the connection) is what keeps a dump to its data.
     * Returns [table, columns|null, tuples].
     */
    function sl_dbStrictInsert(string $sql): array
    {
        if (!preg_match('/^INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?\s*(\(\s*`?[A-Za-z0-9_]+`?(?:\s*,\s*`?[A-Za-z0-9_]+`?)*\s*\))?\s*VALUES\s*/i', $sql, $m)) {
            throw new RuntimeException('Refused statement (not a plain INSERT … VALUES): ' . mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 60));
        }
        $cols = !empty($m[2]) ? array_map(fn($c) => trim($c, " `\t\r\n"), explode(',', trim($m[2], "() \t\r\n"))) : null;
        // non-string literals (short; regex). Strings are scanned without regex so a value of any size works.
        $lit = '/[-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][-+]?\d+)?(?![A-Za-z0-9_])|(?:NULL|TRUE|FALSE)(?![A-Za-z0-9_])|0x[0-9A-Fa-f]+(?![A-Za-z0-9_])|[xX]\'[0-9A-Fa-f]*\'|[bB]\'[01]*\'/Ai';
        $n = strlen($sql);
        $i = strlen($m[0]);
        $bad = function () use ($m, &$i) {
            throw new RuntimeException('Refused: ' . $m[1] . ' INSERT has something other than plain values near byte ' . $i . ' (comments, functions, SELECT or a second statement are not allowed)');
        };
        $skip = function () use ($sql, $n, &$i) {
            while ($i < $n && ($sql[$i] === ' ' || $sql[$i] === "\t" || $sql[$i] === "\r" || $sql[$i] === "\n")) $i++;
        };
        /** One value at $i; moves $i past it or refuses. */
        $value = function () use ($sql, $n, &$i, $lit, $bad) {
            if ($sql[$i] === '_' && preg_match('/_[A-Za-z0-9]{1,32}\s*(?=\')/A', $sql, $mi, 0, $i)) {
                $i += strlen($mi[0]); // charset introducer, a string must follow
            }
            if ($i < $n && $sql[$i] === "'") {
                $j = $i + 1;
                while (true) {
                    $j += strcspn($sql, "'\\", $j);
                    if ($j >= $n) $bad(); // unterminated string
                    if ($sql[$j] === '\\') { $j += 2; continue; }
                    if ($j + 1 < $n && $sql[$j + 1] === "'") { $j += 2; continue; }
                    break;
                }
                $i = $j + 1;
                return;
            }
            if (!preg_match($lit, $sql, $mm, 0, $i)) $bad();
            $i += strlen($mm[0]);
        };
        $tuples = 0;
        while (true) {
            $skip();
            if ($i >= $n || $sql[$i] !== '(') $bad();
            $i++;
            while (true) {
                $skip();
                if ($i >= $n) $bad();
                $value();
                $skip();
                if ($i < $n && $sql[$i] === ',') { $i++; continue; }
                if ($i < $n && $sql[$i] === ')') { $i++; break; }
                $bad();
            }
            $tuples++;
            $skip();
            if ($i >= $n) break;
            if ($sql[$i] !== ',') $bad();
            $i++;
        }
        return [$m[1], $cols, $tuples];
    }

    /** Every INSERT of the dump through sl_dbStrictInsert; any other statement must be one the plan only inspects. Returns the INSERT count. */
    function sl_dbScanDump(string $path): int
    {
        require_once CDP_ROOT . '/helpers/database_import.php';
        $n = 0;
        cdp_import_statements($path, function ($sql) use (&$n) {
            if (preg_match('/^INSERT\b/i', $sql)) {
                sl_dbStrictInsert($sql);
                $n++;
            } elseif (!preg_match('/^(?:SET|START TRANSACTION|COMMIT|CREATE TABLE|ALTER TABLE|DROP TABLE|LOCK TABLES|UNLOCK TABLES)\b/i', $sql)) {
                throw new RuntimeException('Unsupported SQL command in upload: ' . mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 60));
            }
        });
        return $n;
    }

    /**
     * Everything that can be checked before the automatic backup: schema plan, strict scan of every
     * INSERT, protected tables (skipped in both modes), owner-only tables, and in "empty" mode that each
     * target table is empty. Throws on a problem. Returns ['plan', 'tables' (to load), 'skip', 'ownerOnly'].
     */
    function sl_dbPrecheck(string $path, string $mode, PDO $pdo): array
    {
        require_once CDP_ROOT . '/helpers/database_import.php';
        $plan = cdp_import_plan($path, $pdo);
        if ($plan['issues']) {
            throw new RuntimeException('Schema mismatch: ' . implode('; ', array_slice($plan['issues'], 0, 8)));
        }
        sl_dbScanDump($path);
        $skip = array_values(array_filter(array_keys($plan['tables']), 'sl_dbProtected'));
        $tables = array_values(array_diff(array_keys($plan['tables']), $skip));
        if (!$tables) {
            throw new RuntimeException('Every table in this dump is protected (console access, audit log, migration ledger): nothing to import.');
        }
        if ($mode === 'empty') {
            foreach ($tables as $t) {
                if ($pdo->query('SELECT 1 FROM `' . $t . '` LIMIT 1')->fetchColumn() !== false) {
                    throw new RuntimeException("$t already has data. Choose \"Replace the rows\" or import into an empty table.");
                }
            }
        }
        return ['plan' => $plan, 'tables' => $tables, 'skip' => $skip, 'ownerOnly' => array_values(array_filter($tables, 'sl_dbOwnerOnlyTable'))];
    }

    /**
     * Apply a staged dump after sl_dbPrecheck(). 'empty': insert into the (empty) tables. 'replace':
     * delete the rows of the tables in the dump, then insert. Protected tables are skipped in both modes.
     * One transaction; only statements that pass sl_dbStrictInsert run; DDL is never executed.
     */
    function sl_dbApply(string $path, string $mode, int $jobId, ?array $pre = null, ?PDO $pdo = null): array
    {
        require_once CDP_ROOT . '/helpers/database_import.php';
        @set_time_limit(3600);
        $pdo = $pdo ?? sl_dbPdo();
        $pre = $pre ?? sl_dbPrecheck($path, $mode, $pdo);
        $plan = $pre['plan'];
        $skip = $pre['skip'];
        $tables = $pre['tables'];
        sl_dbJobLog($jobId, $mode === 'empty' ? 'Mode: into empty tables only' : 'Mode: replace the rows of the tables in the dump');
        if ($skip) sl_dbJobLog($jobId, 'Protected, kept as they are: ' . implode(', ', $skip));
        $inserted = 0;
        $pdo->beginTransaction();
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            if ($mode === 'replace') {
                foreach ($tables as $t) {
                    $del = $pdo->exec('DELETE FROM `' . $t . '`');
                    sl_dbJobLog($jobId, "Cleared $t ($del rows)");
                }
            }
            cdp_import_statements($path, function ($sql) use ($pdo, $plan, $skip, $tables, &$inserted) {
                if (!preg_match('/^INSERT\b/i', $sql)) return; // schema and session statements are never run
                [$t, $cols] = sl_dbStrictInsert($sql);
                if (in_array($t, $skip, true)) return;
                if (!in_array($t, $tables, true)) throw new RuntimeException("Unexpected table $t.");
                if ($cols === null) {
                    $sql = preg_replace('/^(INSERT\s+INTO\s+`?[A-Za-z0-9_]+`?)\s*VALUES/i', '$1 (' . implode(', ', array_map(fn($c) => '`' . str_replace('`', '', $c) . '`', $plan['source'][$t])) . ') VALUES', $sql, 1);
                }
                $inserted += (int) $pdo->exec($sql);
            });
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            sl_dbJobLog($jobId, 'Rolled back: ' . $e->getMessage());
            throw $e;
        }
        sl_dbJobLog($jobId, 'Inserted ' . number_format($inserted) . ' rows into ' . count($tables) . ' tables');
        return ['tables' => count($tables), 'skipped' => $skip, 'rows' => $inserted];
    }

    function sl_dbJobs(int $limit = 15): array
    {
        if (!cdp_devTableExists('cdb_dev_db_jobs')) return [];
        return cdp_devQ("SELECT j.*, CONCAT(u.fname,' ',u.lname) AS who FROM cdb_dev_db_jobs j LEFT JOIN cdb_users u ON u.id = j.user_id ORDER BY j.id DESC LIMIT " . (int) $limit);
    }

    /* ----------------------------------------- re-authentication (per user, in the database) */

    /** Seconds this user is still locked out of the database tools (5 wrong passwords = 15 minutes). */
    function sl_dbReauthWait(int $uid): int
    {
        $s = cdp_devState('db_reauth_' . $uid, []);
        return is_array($s) && !empty($s['until']) && (int) $s['until'] > time() ? (int) $s['until'] - time() : 0;
    }

    /** Records a password check. Returns true when the password is right. */
    function sl_dbReauthCheck(int $uid, string $password): bool
    {
        $row = cdp_devOne('SELECT password FROM cdb_users WHERE id = :u', [':u' => $uid]);
        $ok = $password !== '' && $row && password_verify($password, (string) $row->password);
        $s = cdp_devState('db_reauth_' . $uid, []);
        $s = is_array($s) ? $s : [];
        if ($ok) {
            if (!empty($s['n']) || !empty($s['until'])) cdp_devStateSet('db_reauth_' . $uid, ['n' => 0, 'until' => 0]);
            return true;
        }
        $n = (int) ($s['n'] ?? 0) + 1;
        cdp_devStateSet('db_reauth_' . $uid, $n >= 5 ? ['n' => 0, 'until' => time() + 900] : ['n' => $n, 'until' => 0]);
        return false;
    }

}
sl_ghApplyTz();

// CLI: php helpers/dev_github.php sync | backup
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $cmd = $argv[1] ?? 'sync';
    require_once CDP_ROOT . '/helpers/cron_run.php';
    if ($cmd === 'sync') {
        $out = cdp_cronWrap('dev_github_sync', function () {
            $r = sl_ghSync();
            return $r['message'];
        });
        echo '[' . date('Y-m-d H:i:s') . '] github: ' . $out . "\n";
    } elseif ($cmd === 'backup') {
        $out = cdp_cronWrap('dev_db_backup', function () {
            $r = sl_dbBackupNow('scheduled');
            return $r['message'];
        });
        echo '[' . date('Y-m-d H:i:s') . '] backup: ' . $out . "\n";
    } else {
        fwrite(STDERR, "usage: php helpers/dev_github.php sync|backup\n");
        exit(2);
    }
}
