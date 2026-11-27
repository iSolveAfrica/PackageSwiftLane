<?php
/**
 * Data exports (CSV, Excel, XLSX downloads of lists and reports) are for administrators and
 * super administrators only. loader.php calls cdp_exportGuardRequest() on every web request, so
 * a page, AJAX endpoint or legacy report that exports is refused before it runs, whatever its own
 * permission check says. cdp_opsSendExport() (helpers/ops_db.php) checks again as a backstop.
 * Printing a single document (invoice, label, receipt, packing list PDF) is not an export.
 */

if (!function_exists('cdp_canExportData')) {
    /** True for administrators and super administrators (same rule as sl_userIs('admin'|'superadmin')). */
    function cdp_canExportData(): bool
    {
        $lvl = (int) ($_SESSION['userlevel'] ?? 0);
        if ($lvl <= 0) return false;
        if (in_array($lvl, [2, 9], true)) return true;
        require_once CDP_ROOT . '/helpers/rbac.php';
        try {
            return (bool) (cdp_roleHasFlag($lvl, 'is_superadmin') || cdp_roleHasFlag($lvl, 'is_admin'));
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Does this request ask for a data export? */
    function cdp_isExportRequest(): bool
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? ''));
        $base = basename($script);
        if (preg_match('/(_excel|_export_ajax|_csv)\.php$/i', $base)) return true;
        $p = $_GET + $_POST;
        if (isset($p['export']) && (string) $p['export'] !== '' && (string) $p['export'] !== '0') return true;
        foreach (['format', 'fmt'] as $k) {
            if (isset($p[$k]) && in_array(strtolower((string) $p[$k]), ['csv', 'xls', 'xlsx', 'excel'], true)) return true;
        }
        foreach (['op', 'action', 'do'] as $k) {
            if (isset($p[$k]) && is_string($p[$k]) && preg_match('/^export/i', $p[$k])) return true;
        }
        return false;
    }

    /** Refuse the export: JSON for AJAX callers, a short page otherwise. */
    function cdp_exportDeny(): void
    {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(403);
        header_remove('Content-Disposition');
        $msg = 'Exports are for administrators only.';
        $ajax = strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || strpos(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/ajax/') !== false;
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'ok' => false, 'message' => $msg]);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><meta charset="utf-8"><title>Not allowed</title><body style="font-family:Figtree,Arial,sans-serif;padding:40px;color:#222"><h1 style="font-size:20px">' . $msg . '</h1><p>Ask an administrator if you need this data.</p><p><a href="javascript:history.back()">Go back</a></p></body>';
        }
        exit;
    }

    /** loader.php: stop an export request from anyone who is not an administrator. */
    function cdp_exportGuardRequest(): void
    {
        if (PHP_SAPI === 'cli') return;
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        // The public API, webhooks and cron authenticate on their own and are not staff exports.
        if (preg_match('~/(api|webhooks|cron)/~', $script)) return;
        if (!cdp_isExportRequest()) return;
        if (!cdp_canExportData()) cdp_exportDeny();
    }
}
