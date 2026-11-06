<?php
/**
 * Cron run ledger (cdb_cron_runs) for the developer console.
 *
 * Usage at the top of a cron script, after loader.php:
 *     require_once __DIR__ . '/../helpers/cron_run.php';
 *     $cronRun = cdp_cronStart('pickup_aging');
 *     ... work ...
 *     cdp_cronFinish($cronRun, 'ok', 'discovered=3');
 *
 * If the script dies before cdp_cronFinish() the shutdown handler marks the run
 * as failed with the last PHP error. Every function is a no-op when the table
 * does not exist yet (older schema), so cron keeps working during upgrades.
 */
if (!function_exists('cdp_cronTableReady')) {
    function cdp_cronTableReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            try {
                $db = new Conexion();
                $db->cdp_query("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_cron_runs'");
                $db->cdp_execute();
                $r = $db->cdp_registro();
                $ready = $r && (int) $r->n > 0;
            } catch (Throwable $e) {
                $ready = false;
            }
        }
        return $ready;
    }

    /** Record the start of a job; returns the run id (0 when the ledger is unavailable). */
    function cdp_cronStart(string $job): int
    {
        if (!cdp_cronTableReady()) {
            return 0;
        }
        try {
            $db = new Conexion();
            $db->cdp_query('INSERT INTO cdb_cron_runs (job, started_at, status, host) VALUES (:j, NOW(), :s, :h)');
            $db->bind(':j', mb_substr($job, 0, 80));
            $db->bind(':s', 'running');
            $db->bind(':h', mb_substr((string) gethostname(), 0, 100));
            $db->cdp_execute();
            $id = (int) $db->dbh->lastInsertId();
        } catch (Throwable $e) {
            return 0;
        }
        $GLOBALS['__cdp_cron_open'][$id] = ['job' => $job, 't' => microtime(true)];
        static $armed = false;
        if (!$armed) {
            $armed = true;
            register_shutdown_function(function () {
                $err = error_get_last();
                foreach ((array) ($GLOBALS['__cdp_cron_open'] ?? []) as $id => $info) {
                    $msg = $err ? ('Stopped: ' . $err['message'] . ' in ' . basename($err['file']) . ':' . $err['line']) : 'Stopped before finishing';
                    cdp_cronFinish($id, 'failed', $msg);
                }
            });
        }
        return $id;
    }

    /** Close a run. $status is ok | failed. */
    function cdp_cronFinish(int $runId, string $status = 'ok', string $message = ''): void
    {
        if ($runId <= 0 || !cdp_cronTableReady()) {
            return;
        }
        $info = $GLOBALS['__cdp_cron_open'][$runId] ?? null;
        unset($GLOBALS['__cdp_cron_open'][$runId]);
        $ms = $info ? (int) round((microtime(true) - $info['t']) * 1000) : null;
        try {
            $db = new Conexion();
            $db->cdp_query('UPDATE cdb_cron_runs SET finished_at = NOW(), status = :s, duration_ms = :d, message = :m WHERE id = :id AND status = :running');
            $db->bind(':s', $status === 'ok' ? 'ok' : 'failed');
            $db->bind(':d', $ms);
            $db->bind(':m', mb_substr($message, 0, 500));
            $db->bind(':id', $runId);
            $db->bind(':running', 'running');
            $db->cdp_execute();
        } catch (Throwable $e) {
        }
    }

    /** Run a callable as a recorded job; exceptions are recorded as failures and re-thrown. */
    function cdp_cronWrap(string $job, callable $fn)
    {
        $id = cdp_cronStart($job);
        try {
            $out = $fn();
            cdp_cronFinish($id, 'ok', is_scalar($out) ? (string) $out : '');
            return $out;
        } catch (Throwable $e) {
            cdp_cronFinish($id, 'failed', get_class($e) . ': ' . $e->getMessage());
            throw $e;
        }
    }
}
