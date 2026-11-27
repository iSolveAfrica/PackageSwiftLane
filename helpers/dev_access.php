<?php
/**
 * Developer console access: per-section grants and the locked shutdown.
 *
 * Loaded by helpers/devops.php, so every Dev page and endpoint that already requires devops.php
 * has these functions. Three layers, all of them required:
 *   1. cdp_devCanUse($user)                      super admin AND listed in cdb_dev_access, console not locked
 *   2. cdp_devSectionAllowed($user, $section)    a grant for that section (cdb_dev_section_grants);
 *                                                the owner (first assigned super admin) has every section
 *   3. cdp_devRequireSection('logs')             the one-line gate for pages and endpoints: does 1 + 2 and
 *                                                answers with a redirect (pages) or a JSON 401/403/423 (AJAX)
 *
 * Sections: overview services logs metrics infrastructure overrides deployments github database
 *           alerts issues modules access shutdown   (aliases: integrations -> github, changelog -> issues)
 *
 * Locked shutdown: a super admin with the "shutdown" section switches the whole console off and sets an
 * unlock code (min 8 characters, stored only as password_hash in cdb_dev_state 'console_lock').
 * While locked cdp_devCanUse() is false for everybody (pages and endpoints refuse); dev pages send
 * assigned super admins to dev_access.php, which shows only the unlock form. Unlock attempts and the
 * shutdown password are rate limited (cdb_dev_unlock_attempts) and written to cdb_activity_log.
 * Lost code: run `php scripts/dev_unlock.php` on the server. Owner demoted / gone: `--owner <id>`.
 * Times are written and compared in the system timezone (cdp_devAppNow), whatever the request.
 */

if (!function_exists('cdp_devSections')) {

    /** key => [label, group, description]. Order = display order of the access matrix. */
    function cdp_devSections(): array
    {
        return [
            'overview'       => ['Overview', 'Observe', 'KPIs, system architecture, status, live logs'],
            'services'       => ['Services', 'Observe', 'Service health, response time, uptime, throughput'],
            'logs'           => ['Logs', 'Observe', 'PHP error log, activity, messages, cron, live tail'],
            'metrics'        => ['Metrics', 'Observe', 'CPU, memory, network, disk I/O, request rate'],
            'infrastructure' => ['Infrastructure', 'Operate', 'Disk, RAM, database sizes, nodes, backups'],
            'overrides'      => ['Infrastructure overrides', 'Operate', 'Set server space, RAM and other figures by hand'],
            'alerts'         => ['Alerts', 'Operate', 'Alert rules, acknowledge and resolve'],
            'deployments'    => ['Deployments', 'Ship', 'Pipeline, deploy history, success rate'],
            'github'         => ['GitHub and integrations', 'Ship', 'Commits, actions, releases, pull requests, token'],
            'database'       => ['Database upload', 'Ship', 'Upload and import SQL dumps (re-authentication)'],
            'issues'         => ['Issues and change log', 'Ship', 'Issue tracker and release notes'],
            'modules'        => ['Modules', 'Control', 'Switch business modules on and off'],
            'access'         => ['Developer access', 'Control', 'Grant and revoke these sections'],
            'shutdown'       => ['Shutdown', 'Control', 'Lock the whole developer console with a code'],
        ];
    }

    /**
     * Sections only the owner may grant or revoke. "database" is here because an SQL import can rewrite
     * cdb_dev_access / cdb_dev_section_grants / cdb_dev_state (lift the lock, change the owner), so it
     * is worth every other section.
     */
    function cdp_devOwnerOnlySections(): array
    {
        return ['access', 'shutdown', 'database'];
    }

    /** The read-mostly set legacy developers are seeded with, and the "Observe" preset. */
    function cdp_devObserveSections(): array
    {
        return ['overview', 'services', 'logs', 'metrics', 'infrastructure', 'alerts', 'issues', 'deployments', 'github'];
    }

    /** True when $section (after aliases) is one of cdp_devSections(). */
    function cdp_devSectionValid(string $section): bool
    {
        return isset(cdp_devSections()[cdp_devSectionKey($section)]);
    }

    /* ------------------------------------------------------------------ one clock */

    /** The system timezone (cdb_settings.timezone, the zone Core applies on pages). */
    function cdp_devAppTz(): DateTimeZone
    {
        static $tz = null;
        if ($tz === null) {
            $name = '';
            $r = cdp_devOne('SELECT timezone FROM cdb_settings LIMIT 1');
            if ($r && !empty($r->timezone) && in_array((string) $r->timezone, timezone_identifiers_list(), true)) {
                $name = (string) $r->timezone;
            }
            $tz = new DateTimeZone($name !== '' ? $name : date_default_timezone_get());
        }
        return $tz;
    }

    /**
     * Wall-clock time in the system timezone, whatever the request: pages (Core sets the zone), AJAX
     * endpoints and the CLI (PHP default, usually UTC) all write and compare in the same clock.
     */
    function cdp_devAppNow(int $offsetSeconds = 0): string
    {
        return (new DateTime('@' . (time() + $offsetSeconds)))->setTimezone(cdp_devAppTz())->format('Y-m-d H:i:s');
    }

    /** Unix time of a 'Y-m-d H:i:s' written by cdp_devAppNow(). */
    function cdp_devAppTs(string $dt): int
    {
        try {
            return (new DateTime($dt, cdp_devAppTz()))->getTimestamp();
        } catch (Throwable $e) {
            return 0;
        }
    }

    function cdp_devSectionKey(string $section): string
    {
        $section = strtolower(trim($section));
        $alias = ['integrations' => 'github', 'integration' => 'github', 'changelog' => 'issues', 'change_log' => 'issues',
            'deploy' => 'deployments', 'db' => 'database', 'database_upload' => 'database', 'infra' => 'infrastructure', 'module' => 'modules'];
        return $alias[$section] ?? $section;
    }

    /** User id from a User object, a row or an int. */
    function cdp_devUid($user): int
    {
        if (is_object($user)) {
            return (int) ($user->uid ?? ($user->id ?? 0));
        }
        return (int) $user;
    }

    function cdp_devAccessEnsure(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        if (!cdp_devEnsureTables()) {
            return $ok = false;
        }
        $t = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $ok = cdp_devX("CREATE TABLE IF NOT EXISTS cdb_dev_section_grants (
                user_id INT NOT NULL, section VARCHAR(32) NOT NULL, granted_by INT NULL, granted_at DATETIME NOT NULL,
                PRIMARY KEY (user_id, section))$t")
            && cdp_devX("CREATE TABLE IF NOT EXISTS cdb_dev_unlock_attempts (
                id BIGINT NOT NULL AUTO_INCREMENT, user_id INT NULL, ip VARCHAR(45) NULL, kind VARCHAR(16) NOT NULL DEFAULT 'unlock', ok TINYINT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY idx_user (user_id, created_at), KEY idx_ip (ip, created_at), KEY idx_at (created_at), KEY idx_kind (kind, created_at))$t");
        if ($ok && !cdp_devState('sections_seeded')) {
            // Upgrade path (least privilege): developers assigned before per-section access get the
            // observe set only. Database upload, modules, overrides, access and shutdown are granted
            // afterwards by the owner on Developer access. Recorded as one activity-log row.
            $owner = cdp_devOwnerId();
            $seeded = [];
            foreach (cdp_devQ('SELECT a.user_id, u.username FROM cdb_dev_access a LEFT JOIN cdb_users u ON u.id = a.user_id') as $a) {
                if ((int) $a->user_id === $owner) {
                    continue;
                }
                foreach (cdp_devObserveSections() as $s) {
                    cdp_devX('INSERT IGNORE INTO cdb_dev_section_grants (user_id, section, granted_by, granted_at) VALUES (:u, :s, NULL, :n)',
                        [':u' => (int) $a->user_id, ':s' => $s, ':n' => cdp_devAppNow()]);
                }
                $seeded[] = (string) ($a->username ?: ('user #' . (int) $a->user_id));
            }
            cdp_devStateSet('sections_seeded', ['at' => cdp_devAppNow(), 'users' => $seeded]);
            if ($seeded) {
                if (!function_exists('cdp_activityLog') && is_file(__DIR__ . '/activity_log.php')) {
                    require_once __DIR__ . '/activity_log.php';
                }
                if (function_exists('cdp_activityLog')) {
                    cdp_activityLog(['module' => 'devaccess', 'action' => 'devaccess.seed', 'verb' => 'security', 'outcome' => 'ok',
                        'summary' => 'per-section developer access installed: ' . implode(', ', $seeded) . ' kept the observe sections (' . implode(', ', cdp_devObserveSections()) . '); everything else needs a grant',
                        'entity_type' => 'dev_access', 'entity_label' => 'developer console']);
                }
            }
        }
        return $ok;
    }

    /** The owner: set once to the first assigned super admin; has every section and cannot be removed. */
    function cdp_devOwnerId(): int
    {
        static $owner = null;
        if ($owner !== null) {
            return $owner;
        }
        if (!cdp_devTableExists('cdb_dev_access')) {
            return 0;
        }
        $s = cdp_devTableExists('cdb_dev_state') ? cdp_devState('owner_uid') : null;
        if (is_array($s) && !empty($s['uid']) && cdp_devOne('SELECT user_id FROM cdb_dev_access WHERE user_id = :u', [':u' => (int) $s['uid']])
            && cdp_devActiveSuper((int) $s['uid'])) {
            return $owner = (int) $s['uid'];
        }
        // No stored owner, or the stored one was demoted, deactivated or deleted: the earliest assigned
        // developer who is still an active super admin acts as owner. It is persisted only when no owner
        // was ever stored, so a reinstated owner gets the role back (scripts/dev_unlock.php --owner moves it).
        $first = null;
        foreach (cdp_devQ('SELECT user_id FROM cdb_dev_access ORDER BY granted_at ASC, user_id ASC') as $a) {
            if (cdp_devActiveSuper((int) $a->user_id)) {
                $first = $a;
                break;
            }
        }
        if (!$first) {
            return $owner = 0; // nobody assigned yet: the first visitor becomes owner (cdp_devCanUse)
        }
        $owner = (int) $first->user_id;
        if (cdp_devTableExists('cdb_dev_state') && !(is_array($s) && !empty($s['uid']))) {
            cdp_devStateSet('owner_uid', ['uid' => $owner, 'at' => cdp_devAppNow()]);
        }
        return $owner;
    }

    /** Is this user id an active super admin (cdb_users.active = 1 and the is_superadmin role flag)? */
    function cdp_devActiveSuper(int $uid): bool
    {
        static $cache = [];
        if (isset($cache[$uid])) {
            return $cache[$uid];
        }
        $row = $uid > 0 ? cdp_devOne('SELECT userlevel, active FROM cdb_users WHERE id = :u', [':u' => $uid]) : null;
        if (!function_exists('cdp_roleHasFlag')) {
            require_once __DIR__ . '/rbac.php';
        }
        return $cache[$uid] = $row && (string) $row->active === '1' && cdp_roleHasFlag((int) $row->userlevel, 'is_superadmin');
    }

    /** Super admin and listed in cdb_dev_access, ignoring the lock (used for the unlock form). */
    function cdp_devAssigned($user): bool
    {
        if (is_object($user)) {
            if (!cdp_devIsSuper($user)) {
                return false;
            }
        } else {
            $row = cdp_devOne('SELECT userlevel, active FROM cdb_users WHERE id = :u', [':u' => (int) $user]);
            if (!function_exists('cdp_roleHasFlag')) {
                require_once __DIR__ . '/rbac.php';
            }
            if (!$row || !cdp_roleHasFlag((int) $row->userlevel, 'is_superadmin')) {
                return false;
            }
        }
        if (!cdp_devTableExists('cdb_dev_access')) {
            return false;
        }
        static $seen = [];
        $uid = cdp_devUid($user);
        if (!isset($seen[$uid])) {
            $seen[$uid] = (bool) cdp_devOne('SELECT user_id FROM cdb_dev_access WHERE user_id = :u', [':u' => $uid]);
        }
        return $seen[$uid];
    }

    function cdp_devIsOwner($user): bool
    {
        $uid = cdp_devUid($user);
        return $uid > 0 && $uid === cdp_devOwnerId();
    }

    /** Sections granted to a user id (owner: all). Cached per request. */
    function cdp_devUserSections(int $uid): array
    {
        static $cache = [];
        if (isset($cache[$uid])) {
            return $cache[$uid];
        }
        if ($uid > 0 && $uid === cdp_devOwnerId()) {
            return $cache[$uid] = array_keys(cdp_devSections());
        }
        if (!cdp_devAccessEnsure()) {
            return $cache[$uid] = [];
        }
        $out = [];
        foreach (cdp_devQ('SELECT section FROM cdb_dev_section_grants WHERE user_id = :u', [':u' => $uid]) as $r) {
            if (isset(cdp_devSections()[$r->section])) {
                $out[] = (string) $r->section;
            }
        }
        return $cache[$uid] = $out;
    }

    /**
     * May this user open this developer section? Needs an assigned super admin, an unlocked console
     * and a grant (the owner holds every section). Unknown sections: owner only.
     */
    function cdp_devSectionAllowed($user, $section): bool
    {
        if (!cdp_devAssigned($user) || cdp_devLocked()) {
            return false;
        }
        $key = cdp_devSectionKey((string) $section);
        if (cdp_devIsOwner($user)) {
            return true;
        }
        if (!isset(cdp_devSections()[$key])) {
            return false;
        }
        return in_array($key, cdp_devUserSections(cdp_devUid($user)), true);
    }

    /** Is the request an AJAX / JSON one (path under ajax/ or api/, or fetch headers)? */
    function cdp_devWantsJson(): bool
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        return strpos($script, '/ajax/') !== false || strpos($script, '/api/') !== false
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') === 0;
    }

    function cdp_devDeny(int $status, string $error, string $message, ?string $location, bool $json): void
    {
        if ($json) {
            if (!headers_sent()) {
                http_response_code($status);
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
            }
            echo json_encode(['success' => false, 'ok' => false, 'error' => $error, 'message' => $message]);
            exit;
        }
        if (!headers_sent()) {
            header('Location: ' . ($location ?? 'error403.php'));
        }
        exit;
    }

    /**
     * Gate for every Dev page and Dev endpoint. Call after loader.php:
     *     require_once CDP_ROOT . '/helpers/devops.php';
     *     cdp_devRequireSection('logs');            // page: redirects; ajax/: JSON refusal
     *     cdp_devRequireSection('database', true);  // force JSON
     * Signed out -> login (401); console locked -> dev_access.php lock screen (423);
     * not an assigned super admin or no grant for the section -> error403.php (403).
     * Returns the signed-in User on success.
     */
    function cdp_devRequireSection(string $section, ?bool $json = null)
    {
        global $user;
        $json = $json ?? cdp_devWantsJson();
        if (!isset($user) || !($user instanceof User)) {
            $user = new User();
        }
        if (empty($user->logged_in)) {
            cdp_devDeny(401, 'unauthorized', 'Sign in first.', 'login.php?next=' . urlencode((string) ($_SERVER['REQUEST_URI'] ?? '')), $json);
        }
        if (cdp_devLocked()) {
            $can = cdp_devAssigned($user);
            cdp_devDeny($can ? 423 : 403, 'dev_locked', 'The developer console is locked. Enter the unlock code on the Developer access page.', $can ? 'dev_access.php' : 'error403.php', $json);
        }
        if (!cdp_devCanUse($user) || !cdp_devSectionAllowed($user, $section)) {
            cdp_devDeny(403, 'dev_section_denied', 'You do not have access to this part of the developer console.', 'error403.php', $json);
        }
        cdp_devAccessEnsure();
        return $user;
    }

    /* ------------------------------------------------------------------ grants */

    /** Assigned super admins and every super admin who could be assigned, with their sections. */
    function cdp_devAccessMatrix(): array
    {
        cdp_devAccessEnsure();
        $owner = cdp_devOwnerId();
        $rows = [];
        foreach (cdp_devAccessList() as $u) {
            $u['owner'] = $u['id'] === $owner;
            $u['sections'] = $u['assigned'] ? cdp_devUserSections($u['id']) : [];
            $last = cdp_devOne('SELECT MAX(granted_at) AS t FROM cdb_dev_section_grants WHERE user_id = :u', [':u' => $u['id']]);
            $u['changed_at'] = $last && $last->t ? (string) $last->t : $u['granted_at'];
            $rows[] = $u;
        }
        usort($rows, fn($a, $b) => [$b['owner'], $b['assigned'], $a['name']] <=> [$a['owner'], $a['assigned'], $b['name']]);
        return $rows;
    }

    /**
     * Grant or revoke one section. $by must hold "access"; only the owner changes access/shutdown;
     * nobody changes their own grants or the owner's. Returns ['ok'=>bool,'error'=>string].
     */
    function cdp_devSectionSet($by, int $targetId, string $section, bool $on): array
    {
        $section = cdp_devSectionKey($section);
        if (!isset(cdp_devSections()[$section])) {
            return ['ok' => false, 'error' => 'Unknown section.'];
        }
        if (!cdp_devSectionAllowed($by, 'access')) {
            return ['ok' => false, 'error' => 'You cannot change developer access.'];
        }
        $byId = cdp_devUid($by);
        if ($targetId === $byId) {
            return ['ok' => false, 'error' => 'You cannot change your own access. Ask the owner.'];
        }
        if ($targetId === cdp_devOwnerId()) {
            return ['ok' => false, 'error' => 'The owner always has every section.'];
        }
        if (in_array($section, cdp_devOwnerOnlySections(), true) && !cdp_devIsOwner($by)) {
            return ['ok' => false, 'error' => 'Only the owner can grant or revoke this section.'];
        }
        if (!cdp_devOne('SELECT user_id FROM cdb_dev_access WHERE user_id = :u', [':u' => $targetId])) {
            return ['ok' => false, 'error' => 'Assign this super admin to the developer console first.'];
        }
        $ok = $on
            ? cdp_devX('INSERT IGNORE INTO cdb_dev_section_grants (user_id, section, granted_by, granted_at) VALUES (:u, :s, :b, :n)', [':u' => $targetId, ':s' => $section, ':b' => $byId, ':n' => cdp_devAppNow()])
            : cdp_devX('DELETE FROM cdb_dev_section_grants WHERE user_id = :u AND section = :s', [':u' => $targetId, ':s' => $section]);
        return ['ok' => $ok, 'error' => $ok ? '' : 'Could not save.'];
    }

    /* ------------------------------------------------------------------ locked shutdown */

    /** Lock record or null. ['hash','by','by_name','at','note'] */
    function cdp_devLockInfo(bool $fresh = false): ?array
    {
        static $info = false;
        if ($info !== false && !$fresh) {
            return $info;
        }
        $info = null;
        if (cdp_devTableExists('cdb_dev_state')) {
            $s = cdp_devState('console_lock');
            if (is_array($s) && !empty($s['locked']) && !empty($s['hash'])) {
                $info = $s;
            }
        }
        return $info;
    }

    function cdp_devLocked(): bool
    {
        return cdp_devLockInfo() !== null;
    }

    function cdp_devClientIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
    }

    /** Check an unlock code strength; returns '' when fine, else the reason. */
    function cdp_devCodeProblem(string $code, string $repeat): string
    {
        if (mb_strlen($code) < 8) {
            return 'The unlock code needs at least 8 characters.';
        }
        if (mb_strlen($code) > 128) {
            return 'The unlock code is too long (128 characters at most).';
        }
        if (!hash_equals($code, $repeat)) {
            return 'The two codes do not match.';
        }
        if (preg_match('/^(.)\1+$/u', $code)) {
            return 'Choose a code that is not one repeated character.';
        }
        return '';
    }

    /** Switch the console off. Caller has checked CSRF, the shutdown section and the password. */
    function cdp_devLock($by, string $code, string $note = ''): bool
    {
        $name = '';
        if (is_object($by)) {
            $name = trim((string) (($by->name ?? '') ?: ($by->username ?? '')));
        }
        $ok = cdp_devStateSet('console_lock', [
            'locked' => true, 'hash' => password_hash($code, PASSWORD_DEFAULT), 'by' => cdp_devUid($by),
            'by_name' => $name, 'at' => cdp_devAppNow(), 'note' => mb_substr($note, 0, 200),
        ]);
        cdp_devLockInfo(true);
        return $ok;
    }

    /** Make sure cdb_dev_unlock_attempts has the "kind" column (unlock | password). */
    function cdp_devAttemptsReady(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        if (!cdp_devAccessEnsure()) {
            return $ok = false;
        }
        $col = cdp_devOne("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_dev_unlock_attempts' AND COLUMN_NAME = 'kind'");
        if (!$col || (int) $col->n === 0) {
            cdp_devX("ALTER TABLE cdb_dev_unlock_attempts ADD COLUMN kind VARCHAR(16) NOT NULL DEFAULT 'unlock' AFTER ip, ADD KEY idx_kind (kind, created_at)");
        }
        return $ok = true;
    }

    /**
     * Rate limits: [label, SQL condition, bind value, max failures, window seconds]. Per user always;
     * per address and system-wide for unlock attempts, except for the owner (so one developer cannot
     * keep the owner out by burning the shared limits). Password re-authentication: per user only.
     */
    function cdp_devAttemptLimits(string $kind, int $uid): array
    {
        $out = [['user', 'user_id = :k', $uid, 5, 900]];
        if ($kind === 'unlock' && !cdp_devIsOwner($uid)) {
            $out[] = ['ip', 'ip = :k', cdp_devClientIp(), 10, 900];
            $out[] = ['all', '1 = :k', 1, 25, 3600];
        }
        return $out;
    }

    /**
     * Seconds until another attempt of $kind is allowed (0 = now). With $uptoId, only failures with
     * id <= $uptoId count (the caller's own pending row included): parallel requests each see the rows
     * inserted before them, so a burst cannot get more than the limit through.
     */
    function cdp_devAttemptWait(string $kind, int $uid, int $uptoId = 0): int
    {
        if (!cdp_devAttemptsReady()) {
            return 0;
        }
        $wait = 0;
        foreach (cdp_devAttemptLimits($kind, $uid) as [, $cond, $k, $max, $win]) {
            $sql = "SELECT COUNT(*) AS n, MIN(created_at) AS f FROM cdb_dev_unlock_attempts WHERE ok = 0 AND kind = :kind AND $cond AND created_at > :since"
                . ($uptoId > 0 ? ' AND id <= :upto' : '');
            $b = [':kind' => $kind, ':k' => $k, ':since' => cdp_devAppNow(-$win)];
            if ($uptoId > 0) {
                $b[':upto'] = $uptoId;
            }
            $r = cdp_devOne($sql, $b);
            if ($r && (int) $r->n >= $max + ($uptoId > 0 ? 1 : 0) && $r->f) {
                // Clamp to the window: a row stamped in another clock (older data) never means a longer wait.
                $wait = max($wait, min($win, cdp_devAppTs((string) $r->f) + $win - time()));
            }
        }
        return max(0, $wait);
    }

    /** Record an attempt first (ok = 0) and return its id; 0 when the table is not there. */
    function cdp_devAttemptBegin(string $kind, int $uid): int
    {
        if (!cdp_devAttemptsReady()) {
            return 0;
        }
        try {
            $db = new Conexion;
            $db->cdp_query('INSERT INTO cdb_dev_unlock_attempts (user_id, ip, kind, ok, created_at) VALUES (:u, :i, :k, 0, :n)');
            $db->bind(':u', $uid);
            $db->bind(':i', cdp_devClientIp());
            $db->bind(':k', $kind);
            $db->bind(':n', cdp_devAppNow());
            $db->cdp_execute();
            return (int) $db->dbh->lastInsertId();
        } catch (Throwable $e) {
            error_log('DEV_ATTEMPT ' . $e->getMessage());
            return 0;
        }
    }

    /** Seconds until another unlock attempt is allowed for this user / address (0 = allowed now). */
    function cdp_devUnlockWait(int $uid): int
    {
        return cdp_devAttemptWait('unlock', $uid);
    }

    /** Failed attempts of $kind left in the current 15-minute window for this user. */
    function cdp_devUnlockLeft(int $uid, string $kind = 'unlock'): int
    {
        if (!cdp_devAttemptsReady()) {
            return 5;
        }
        $r = cdp_devOne('SELECT COUNT(*) AS n FROM cdb_dev_unlock_attempts WHERE ok = 0 AND kind = :kind AND user_id = :u AND created_at > :since',
            [':kind' => $kind, ':u' => $uid, ':since' => cdp_devAppNow(-900)]);
        return max(0, 5 - (int) ($r->n ?? 0));
    }

    function cdp_devWaitText(int $wait): string
    {
        return 'Too many wrong attempts. Try again in ' . max(1, (int) ceil($wait / 60)) . ' min.';
    }

    /**
     * Run one rate-limited check. The attempt row is inserted before the check and counted with it;
     * a refused (rate-limited) attempt is deleted again so it does not extend the wait.
     * Returns ['ok'=>bool,'wait'=>int,'left'=>int].
     */
    function cdp_devLimitedCheck(string $kind, int $uid, callable $check): array
    {
        $id = cdp_devAttemptBegin($kind, $uid);
        if ($id > 0) {
            $wait = cdp_devAttemptWait($kind, $uid, $id);
            if ($wait > 0) {
                cdp_devX('DELETE FROM cdb_dev_unlock_attempts WHERE id = :i', [':i' => $id]);
                return ['ok' => false, 'wait' => $wait, 'left' => 0];
            }
        }
        $good = (bool) $check();
        if ($good && $id > 0) {
            cdp_devX('UPDATE cdb_dev_unlock_attempts SET ok = 1 WHERE id = :i', [':i' => $id]);
        }
        return ['ok' => $good, 'wait' => 0, 'left' => $good ? 5 : cdp_devUnlockLeft($uid, $kind)];
    }

    /** Try the code. Returns ['ok'=>bool,'error'=>string,'wait'=>int]. Rate limited and recorded. */
    function cdp_devUnlock($by, string $code): array
    {
        $uid = cdp_devUid($by);
        $info = cdp_devLockInfo(true);
        if (!$info) {
            return ['ok' => true, 'error' => '', 'wait' => 0];
        }
        $r = cdp_devLimitedCheck('unlock', $uid, fn() => $code !== '' && password_verify($code, (string) $info['hash']));
        if ($r['wait'] > 0) {
            return ['ok' => false, 'error' => cdp_devWaitText($r['wait']), 'wait' => $r['wait']];
        }
        if (!$r['ok']) {
            $left = $r['left'];
            return ['ok' => false, 'error' => 'That code is not right.' . ($left > 0 ? ' ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left before a 15-minute wait.' : ' Wait 15 minutes before trying again.'), 'wait' => 0];
        }
        cdp_devStateSet('console_lock', ['locked' => false, 'unlocked_by' => $uid, 'unlocked_at' => cdp_devAppNow(), 'was_locked_at' => $info['at'] ?? null]);
        cdp_devLockInfo(true);
        return ['ok' => true, 'error' => '', 'wait' => 0];
    }

    /** Server-side recovery (scripts/dev_unlock.php): remove the lock without the code. */
    function cdp_devForceUnlock(string $reason = 'cli'): bool
    {
        $info = cdp_devLockInfo(true);
        $ok = cdp_devStateSet('console_lock', ['locked' => false, 'unlocked_by' => 0, 'unlocked_at' => cdp_devAppNow(), 'via' => $reason, 'was_locked_at' => $info['at'] ?? null]);
        cdp_devLockInfo(true);
        return $ok;
    }

    /** Recent unlock attempts for the lock screen / access page. */
    function cdp_devUnlockAttempts(int $limit = 10): array
    {
        if (!cdp_devTableExists('cdb_dev_unlock_attempts')) {
            return [];
        }
        cdp_devAttemptsReady();
        return cdp_devQ("SELECT a.user_id, a.ip, a.ok, a.created_at, u.fname, u.lname, u.username FROM cdb_dev_unlock_attempts a LEFT JOIN cdb_users u ON u.id = a.user_id WHERE a.kind = 'unlock' ORDER BY a.id DESC LIMIT " . max(1, min(50, $limit)));
    }

    /**
     * Verify the signed-in user's own password for shutdown, rate limited (5 wrong per 15 minutes per
     * user, recorded in cdb_dev_unlock_attempts kind "password"). Returns ['ok','wait','left'].
     */
    function cdp_devCheckPasswordLimited($user, string $password): array
    {
        return cdp_devLimitedCheck('password', cdp_devUid($user), fn() => cdp_devCheckPassword($user, $password));
    }

    /** Verify the signed-in user's own password (re-authentication for shutdown). */
    function cdp_devCheckPassword($user, string $password): bool
    {
        if ($password === '') {
            return false;
        }
        $r = cdp_devOne('SELECT password FROM cdb_users WHERE id = :u', [':u' => cdp_devUid($user)]);
        return $r && password_verify($password, (string) $r->password);
    }
}
