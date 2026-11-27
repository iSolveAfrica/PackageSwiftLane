<?php
/**
 * Developer console: database tools (Deployments > Database deploy).
 *   stage          upload a .sql / .sql.gz dump (size limit, extension + content check, gzip cap and free
 *                  disk check, one staged dump at a time), store it in the private folder outside the
 *                  web root (0600) and return the import plan plus a strict scan of every INSERT
 *   discard        drop the staged upload
 *   apply          import the staged dump: CDP_DB_IMPORT_ENABLED, not demo, password re-entered, typed
 *                  confirmation "IMPORT <dbname>", every check first, then an automatic backup, then the
 *                  import in one transaction; one import at a time (GET_LOCK); step log in cdb_dev_db_jobs
 *   backup_now     streaming backup into the private folder (2-minute cooldown, retention)
 *   move_legacy    move console backups left in the web-root backups/ into the private folder
 *   download       password re-entered, returns a single-use link (60 s, this session)
 *   fetch (GET)    streams the file of a single-use link
 *   delete_backup  remove a backup file (password re-entered)
 * Gate: CSRF on POST (require_login), view_developer, assigned super admin (cdp_devCanUse) and the
 * "deployments" AND "database" developer sections (sl_ghCan). Every action is written to cdb_activity_log (file names
 * stay out of the summaries: the activity log is readable by staff).
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/dev_github.php';
require_once __DIR__ . '/../../helpers/database_import.php';
require_login();
$op = (string) ($_POST['op'] ?? ($_GET['op'] ?? ''));
$isGet = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !($isGet && $op === 'fetch')) {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
require_permission('view_developer');
// the tools live on the Deployments page: both grants are needed, so "database" alone gives no hidden powers
if (!sl_ghCan($user, 'deployments') || !sl_ghCan($user, 'database')) {
    sl_json(['success' => false, 'message' => 'You do not have access to the database tools'], 403);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: database tools are disabled'], 403);
}
sl_ghEnsureTables();
$actor = (int) $_SESSION['userid'];
$audit = function (string $action, string $label, string $summary, string $outcome = 'success', array $meta = []) {
    cdp_activityLog(['module' => 'developer', 'action' => 'developer.db_' . $action, 'action_label' => $label, 'verb' => $action === 'download' ? 'export' : 'update', 'outcome' => $outcome,
        'summary' => $summary, 'entity_type' => 'database', 'entity_label' => CDP_DB_NAME, 'meta' => $meta]);
};
/** "backup #<job> (19.1 MB)" — how a backup is named in the activity log (never its file name). */
$bkLabel = function (string $name, ?int $bytes = null): string {
    $j = $name !== '' ? cdp_devOne('SELECT id FROM cdb_dev_db_jobs WHERE backup_file = :b ORDER BY id DESC LIMIT 1', [':b' => $name]) : null;
    return 'backup ' . ($j ? '#' . (int) $j->id : 'of ' . substr($name, 7, 11)) . ($bytes !== null ? ' (' . sl_dbBytes($bytes) . ')' : '');
};

/** Re-authentication: the signed-in user's password. 5 wrong answers lock the tools for 15 minutes (per user, stored in the database). */
$reauth = function () use ($actor, $audit) {
    $wait = sl_dbReauthWait($actor);
    if ($wait > 0) {
        sl_json(['success' => false, 'message' => 'Too many wrong passwords. Try again in ' . ceil($wait / 60) . ' minutes.'], 429);
    }
    if (!sl_dbReauthCheck($actor, (string) ($_POST['password'] ?? ''))) {
        $audit('reauth', 'Database tools: wrong password', 'Password re-entry failed for a database action', 'denied');
        if (sl_dbReauthWait($actor) > 0) {
            sl_json(['success' => false, 'message' => 'Too many wrong passwords. The database tools are locked for 15 minutes.'], 429);
        }
        sl_json(['success' => false, 'message' => 'Password is not correct', 'errors' => ['password' => 'Password is not correct']], 422);
    }
};

$importEnabled = defined('CDP_DB_IMPORT_ENABLED') && CDP_DB_IMPORT_ENABLED === true;

switch ($op) {
    case 'stage':
        if (!$importEnabled) {
            sl_json(['success' => false, 'message' => 'Database import is switched off on this server (CDP_DB_IMPORT_ENABLED).'], 403);
        }
        $stageDir = sl_dbStageDir();
        if ($stageDir === '') {
            sl_json(['success' => false, 'message' => 'No private folder outside the web root for uploads. Create ../swiftlane_private (writable by PHP) or set CDP_PRIVATE_DIR.'], 503);
        }
        sl_dbJobsTidy();
        $limit = sl_dbUploadLimit();
        $f = $_FILES['dump'] ?? null;
        if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            $code = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
            $msg = in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'The file is larger than the server allows (' . sl_dbBytes($limit) . ').' : 'Choose a .sql or .sql.gz file.';
            sl_json(['success' => false, 'message' => $msg, 'errors' => ['dump' => $msg]], 422);
        }
        $name = basename((string) $f['name']);
        $size = (int) $f['size'];
        if ($size <= 0 || $size > $limit) {
            sl_json(['success' => false, 'message' => 'The file must be between 1 byte and ' . sl_dbBytes($limit) . '.', 'errors' => ['dump' => 'Too large']], 422);
        }
        $isGz = (bool) preg_match('/\.sql\.gz$/i', $name);
        if (!$isGz && !preg_match('/\.sql$/i', $name)) {
            sl_json(['success' => false, 'message' => 'Only .sql and .sql.gz files are accepted.', 'errors' => ['dump' => '.sql or .sql.gz']], 422);
        }
        $head = (string) file_get_contents((string) $f['tmp_name'], false, null, 0, 65536);
        if ($isGz && substr($head, 0, 2) !== "\x1f\x8b") {
            sl_json(['success' => false, 'message' => 'That file is not gzip data.', 'errors' => ['dump' => 'Not gzip']], 422);
        }
        if (!$isGz && (strpos($head, "\0") !== false || !preg_match('/\b(INSERT|CREATE|SET|DROP|LOCK)\b|^--|^\/\*/mi', $head))) {
            sl_json(['success' => false, 'message' => 'That file is not a text SQL dump.', 'errors' => ['dump' => 'Not SQL text']], 422);
        }
        $cap = $isGz ? sl_dbGzipCap() : $size;
        $free = @disk_free_space($stageDir);
        if ($free !== false && $free < 2 * $cap + 64 * 1024 * 1024) {
            sl_json(['success' => false, 'message' => 'Not enough free disk to unpack and check this upload safely (' . sl_dbBytes($free) . ' free).'], 507);
        }
        // one staged dump at a time: this user's earlier upload is closed; someone else's blocks a new one
        foreach (cdp_devQ("SELECT id, user_id, stored_name FROM cdb_dev_db_jobs WHERE status = 'staged' AND stored_name IS NOT NULL ORDER BY id") as $sj) {
            $sp = preg_match('/^stage_[0-9a-f]{24}\.sql$/', (string) $sj->stored_name) ? $stageDir . '/' . $sj->stored_name : '';
            if ((int) $sj->user_id === $actor) {
                if ($sp !== '') @unlink($sp);
                sl_dbJobLog((int) $sj->id, 'Discarded: replaced by a new upload');
                sl_dbJobEnd((int) $sj->id, 'discarded');
            } elseif ($sp !== '' && is_file($sp)) {
                sl_json(['success' => false, 'message' => 'Another developer has a dump staged (job #' . (int) $sj->id . '). It expires within an hour of its upload; only one upload is kept at a time.'], 409);
            }
        }
        unset($_SESSION['sl_dev_db_stage']);
        $stored = 'stage_' . bin2hex(random_bytes(12)) . '.sql';
        $path = $stageDir . '/' . $stored;
        try {
            if ($isGz) {
                $old = umask(0077);
                try {
                    cdp_import_extract_gzip((string) $f['tmp_name'], $path, $cap);
                } finally {
                    umask($old);
                }
            } elseif (!move_uploaded_file((string) $f['tmp_name'], $path)) {
                throw new RuntimeException('Could not store the upload.');
            }
            @chmod($path, 0600);
            $sha = hash_file('sha256', $path);
            $job = sl_dbJobStart('upload', ['status' => 'staged', 'file_name' => mb_substr($name, 0, 190), 'stored_name' => $stored, 'file_bytes' => $size, 'sha256' => $sha]);
            sl_dbJobLog($job, 'Uploaded ' . $name . ' (' . sl_dbBytes($size) . ($isGz ? ', ' . sl_dbBytes(filesize($path)) . ' unpacked' : '') . '), sha256 ' . substr($sha, 0, 16) . '…');
            $plan = cdp_import_plan($path, (new Conexion)->dbh);
            try {
                $nIns = sl_dbScanDump($path);
                sl_dbJobLog($job, "Strict check: $nIns INSERT statements, values only");
            } catch (Throwable $e) {
                $plan['issues'][] = $e->getMessage();
            }
        } catch (Throwable $e) {
            @unlink($path);
            if (!empty($job)) {
                sl_dbJobLog($job, 'Rejected: ' . $e->getMessage());
                sl_dbJobEnd($job, 'rejected');
            }
            $audit('stage', 'Database upload rejected', 'Upload rejected: ' . mb_substr($e->getMessage(), 0, 160), 'failure', ['job' => $job ?? null]);
            sl_json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        $tables = array_keys($plan['tables']);
        $existing = sl_dbExistingRows($tables);
        $planOut = ['job' => $job, 'name' => $name, 'bytes' => $size, 'sha256' => $sha, 'issues' => array_slice($plan['issues'], 0, 50),
            'tables' => array_map(fn($t) => ['name' => $t, 'existing' => $existing[$t], 'protected' => sl_dbProtected($t), 'ownerOnly' => sl_dbOwnerOnlyTable($t)], $tables)];
        $_SESSION['sl_dev_db_stage'] = ['job' => $job, 'path' => $path, 'hash' => $sha, 'time' => time(), 'name' => $name, 'plan' => $planOut];
        sl_dbJobLog($job, count($tables) . ' tables with data, ' . count($plan['issues']) . ' issues');
        $audit('stage', 'Uploaded a database dump', 'Dump staged as job #' . $job . ' (' . sl_dbBytes($size) . '): ' . count($tables) . ' tables', 'success', ['job' => $job, 'sha256' => $sha]);
        sl_json(['success' => true, 'message' => 'Upload checked: ' . count($tables) . ' tables' . ($plan['issues'] ? ', ' . count($plan['issues']) . ' issues to fix first' : ''),
            'plan' => $planOut, 'reload' => true]);
        break;

    case 'discard':
        $st = $_SESSION['sl_dev_db_stage'] ?? null;
        if ($st && is_file($st['path'])) {
            @unlink($st['path']);
            sl_dbJobLog((int) $st['job'], 'Discarded before import');
            sl_dbJobEnd((int) $st['job'], 'discarded');
        }
        unset($_SESSION['sl_dev_db_stage']);
        $audit('discard', 'Discarded a database upload', 'Staged dump discarded');
        sl_json(['success' => true, 'message' => 'Upload discarded', 'reload' => true]);
        break;

    case 'apply':
        if (!$importEnabled) {
            sl_json(['success' => false, 'message' => 'Database import is switched off on this server (CDP_DB_IMPORT_ENABLED).'], 403);
        }
        $st = $_SESSION['sl_dev_db_stage'] ?? null;
        $job = (int) ($_POST['job'] ?? 0);
        if (!$st || (int) $st['job'] !== $job || time() - (int) $st['time'] > 3600 || !is_file($st['path']) || !hash_equals((string) $st['hash'], (string) hash_file('sha256', $st['path']))) {
            sl_json(['success' => false, 'message' => 'The upload expired or changed. Upload the file again.'], 409);
        }
        $jr = cdp_devOne('SELECT status FROM cdb_dev_db_jobs WHERE id = :i', [':i' => $job]);
        if (!$jr || $jr->status !== 'staged') {
            sl_json(['success' => false, 'message' => 'This upload is no longer staged (' . ($jr->status ?? 'unknown') . '). Upload the file again.'], 409);
        }
        $want = 'IMPORT ' . CDP_DB_NAME;
        if (trim((string) ($_POST['confirm'] ?? '')) !== $want) {
            sl_json(['success' => false, 'message' => 'Type ' . $want . ' to confirm', 'errors' => ['confirm' => 'Type exactly: ' . $want]], 422);
        }
        $mode = ($_POST['mode'] ?? 'empty') === 'replace' ? 'replace' : 'empty';
        $reauth();
        // the stage data is in $st; release the session so the user's other tabs keep working
        session_write_close();
        @ignore_user_abort(true);
        @set_time_limit(3600);
        try {
            $pdo = sl_dbPdo();
            if ((int) $pdo->query("SELECT GET_LOCK('swiftlane_db_import', 0)")->fetchColumn() !== 1) {
                sl_json(['success' => false, 'message' => 'Another import or backup-and-import is running. Wait for it to finish.'], 409);
            }
            // 0. every check before anything is written (no backup is taken for a dump that cannot be applied)
            $pre = sl_dbPrecheck($st['path'], $mode, $pdo);
        } catch (Throwable $e) {
            sl_dbJobLog($job, 'Checks failed, nothing done: ' . $e->getMessage());
            $audit('import', 'Database import refused', 'Job #' . $job . ' refused before backup: ' . mb_substr($e->getMessage(), 0, 160), 'failure', ['job' => $job]);
            sl_json(['success' => false, 'message' => 'Nothing was changed: ' . $e->getMessage(), 'reload' => true], 422);
        }
        if ($pre['ownerOnly'] && !(function_exists('cdp_devIsOwner') && cdp_devIsOwner($user))) {
            sl_json(['success' => false, 'message' => 'This dump contains accounts, roles or permissions (' . implode(', ', $pre['ownerOnly']) . '). Only the console owner may import those.'], 403);
        }
        cdp_devX("UPDATE cdb_dev_db_jobs SET kind = 'import', status = 'running', mode = :m WHERE id = :i", [':m' => $mode, ':i' => $job]);
        sl_dbJobLog($job, 'Import started by user #' . $actor . ' (' . $mode . '), ' . count($pre['tables']) . ' tables checked');
        $audit('import_start', 'Started a database import', 'Job #' . $job . ' into ' . CDP_DB_NAME . " ($mode)", 'success', ['job' => $job]);
        // 1. automatic backup
        sl_dbJobLog($job, 'Step 1/3: automatic backup');
        $b = sl_dbBackupNow('before import #' . $job, $job);
        if (!$b['ok']) {
            sl_dbJobEnd($job, 'failed');
            $audit('import', 'Database import stopped', 'Job #' . $job . ': backup before import failed', 'failure', ['job' => $job]);
            sl_json(['success' => false, 'message' => 'Stopped: the backup before import failed (' . $b['message'] . '). Nothing was imported.'], 500);
        }
        cdp_devX('UPDATE cdb_dev_db_jobs SET backup_file = :b WHERE id = :i', [':b' => $b['file'], ':i' => $job]);
        // 2. import
        sl_dbJobLog($job, 'Step 2/3: import');
        try {
            $r = sl_dbApply($st['path'], $mode, $job, $pre, $pdo);
        } catch (Throwable $e) {
            sl_dbJobLog($job, 'Import failed: ' . $e->getMessage());
            sl_dbJobEnd($job, 'failed');
            $audit('import', 'Database import failed', 'Job #' . $job . ' rolled back: ' . mb_substr($e->getMessage(), 0, 160), 'failure', ['job' => $job]);
            sl_json(['success' => false, 'message' => 'Import failed, nothing was changed: ' . $e->getMessage(), 'reload' => true], 422);
        }
        // 3. clean up
        sl_dbJobLog($job, 'Step 3/3: removing the staged file');
        @unlink($st['path']);
        $pdo->query("SELECT RELEASE_LOCK('swiftlane_db_import')");
        sl_dbJobEnd($job, 'done', ['tables_n' => (int) $r['tables'], 'backup_file' => $b['file']]);
        if (@session_start()) {
            unset($_SESSION['sl_dev_db_stage']);
            session_write_close();
        }
        $audit('import', 'Imported a database dump', 'Job #' . $job . " imported into " . CDP_DB_NAME . ": {$r['tables']} tables ($mode), backup taken first", 'success', ['job' => $job, 'skipped' => $r['skipped']]);
        sl_json(['success' => true, 'message' => "Imported {$r['tables']} tables (" . number_format((int) ($r['rows'] ?? 0)) . ' rows). Backup taken first (job #' . $job . ').', 'reload' => true]);
        break;

    case 'backup_now':
        $last = cdp_devOne("SELECT started_at FROM cdb_dev_db_jobs WHERE kind = 'backup' AND started_at >= :t ORDER BY id DESC LIMIT 1", [':t' => date('Y-m-d H:i:s', time() - 120)]);
        if ($last) {
            sl_json(['success' => false, 'message' => 'A backup was made less than two minutes ago. Wait a moment.'], 429);
        }
        session_write_close();
        $b = sl_dbBackupNow('manual');
        $audit('backup', 'Database backup', $b['ok'] ? 'Backup job #' . (int) $b['job'] . ': ' . $b['tables'] . ' tables, ' . sl_dbBytes($b['bytes']) : 'Backup failed (job #' . (int) ($b['job'] ?? 0) . ')', $b['ok'] ? 'success' : 'failure', ['job' => $b['job'] ?? null]);
        sl_json(['success' => $b['ok'], 'message' => $b['message'], 'reload' => $b['ok']]);
        break;

    case 'move_legacy':
        $reauth();
        [$moved, $failed] = sl_dbMoveLegacy();
        $audit('backup_move', 'Moved backups out of the web root', "$moved backup file(s) moved to the private folder" . ($failed ? ", $failed failed" : ''), $failed ? 'failure' : 'success');
        sl_json(['success' => $failed === 0, 'message' => $moved . ' backup' . ($moved === 1 ? '' : 's') . ' moved to the private folder' . ($failed ? ", $failed could not be moved" : ''), 'reload' => true]);
        break;

    case 'download':
    case 'delete_backup':
        $name = (string) ($_POST['file'] ?? '');
        $path = sl_dbBackupPath($name);
        if (!$path) {
            sl_json(['success' => false, 'message' => 'Backup not found'], 404);
        }
        $reauth();
        $bytes = (int) filesize($path);
        if ($op === 'delete_backup') {
            $label = $bkLabel($name, $bytes);
            @unlink($path);
            $audit('delete', 'Deleted a database backup', ucfirst($label) . ' deleted');
            sl_json(['success' => true, 'message' => 'Backup deleted', 'reload' => true]);
        }
        // a single-use link (this session, 60 s): the browser saves the file straight to disk
        $t = bin2hex(random_bytes(24));
        $_SESSION['sl_dev_db_dl'] = [hash('sha256', $t) => ['name' => $name, 'exp' => time() + 60]];
        sl_json(['success' => true, 'message' => 'Download starting', 'url' => 'ajax/sl/dev_db_upload_ajax.php?op=fetch&t=' . $t]);
        break;

    case 'fetch':
        $t = (string) ($_GET['t'] ?? '');
        $all = $_SESSION['sl_dev_db_dl'] ?? [];
        $key = preg_match('/^[0-9a-f]{48}$/', $t) ? hash('sha256', $t) : '';
        $ent = $key !== '' && isset($all[$key]) ? $all[$key] : null;
        unset($_SESSION['sl_dev_db_dl']); // single use, whatever happens
        session_write_close();
        $path = $ent && $ent['exp'] >= time() ? sl_dbBackupPath((string) $ent['name']) : null;
        if (!$path) {
            http_response_code(410);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'This download link expired. Start the download again from Deployments > Database deploy.';
            exit;
        }
        $name = (string) $ent['name'];
        $bytes = (int) filesize($path);
        $audit('download', 'Downloaded a database backup', ucfirst($bkLabel($name, $bytes)) . ' downloaded', 'success');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        @set_time_limit(0);
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . $bytes);
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;

    default:
        sl_json(['success' => false, 'message' => 'Unknown action'], 400);
}
