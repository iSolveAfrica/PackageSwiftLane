<?php
/**
 * Developer console, observe package: live data for Overview, Services, Logs and Metrics
 * (dataJs/sl/dev_observe.js). GET only and read-only for the application: it never changes data, so
 * no CSRF token is needed. It defines SL_DVX_AJAX so the live snapshot is taken with
 * cdp_devCollect(false): polling stores no collector sample and opens no alert. The one write left is
 * on the single-threaded PHP built-in server only (development), where the probe cache
 * (cdb_dev_state probe_web / probe_api) is filled from recorded requests so no HTTP self-probe blocks.
 *
 * Gate: signed in, view_developer, then the access package's cdp_devRequireSection($sec, true)
 * (assigned super admin, unlocked console, section grant; JSON 401 / 403 / 423) when installed, and
 * sl_dvxAllowed() (cdp_devCanUse + cdp_devSectionAllowed) in any case.
 *
 *   op=tail    sec=overview|logs  since=Y-m-d H:i:s  source= level= q= view=   new log rows (merged sources)
 *   op=pulse   sec=overview                                              KPI values and component health
 *   op=charts  sec=overview  range=24h|7d|30d                            chart markup for a range
 *   op=svc     sec=services  key=<component>                             recent events of one component
 *
 * These polls are left out of the request metrics (cdp_reqmSkip) so the console does not measure itself.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_login();
require_permission('view_developer');
$core = new Core(); // applies the application time zone, so date() matches the stored log times
define('SL_DVX_AJAX', true);
require_once CDP_ROOT . '/views/sl/data/dev_observe.php';

/** JSON answer that survives invalid UTF-8 in a log line (sl_json would send an empty body). */
function sl_dvxJson(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (function_exists('cdp_reqmSkip')) {
    cdp_reqmSkip();
}
// Read-only polling: tell the activity auto-logger (helpers/activity_log.php, armed by require_login)
// there is nothing to record, or every 5-second poll would become an audit row.
$GLOBALS['cdp_activity_logged'] = true;
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    sl_dvxJson(['success' => false, 'message' => 'GET only'], 405);
}
$op = (string) ($_GET['op'] ?? '');
$sec = (string) ($_GET['sec'] ?? '');
$secFor = ['tail' => ['overview', 'logs'], 'pulse' => ['overview'], 'charts' => ['overview'], 'svc' => ['services']];
if (!isset($secFor[$op]) || !in_array($sec, $secFor[$op], true)) {
    sl_dvxJson(['success' => false, 'message' => 'Unknown request'], 400);
}
if (function_exists('cdp_devRequireSection')) {
    cdp_devRequireSection($sec, true);
}
if (!sl_dvxAllowed($user, $sec)) {
    sl_dvxJson(['success' => false, 'message' => 'This developer section is not available to you'], 403);
}
// Nothing below writes to the session: let other tabs of this user proceed while we read.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

switch ($op) {
    case 'tail':
        $since = (string) ($_GET['since'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
            $since = date('Y-m-d H:i:s', time() - 60);
        }
        // never further back than 24 h, whatever the browser sends
        if (strtotime($since) < time() - 86400) {
            $since = date('Y-m-d H:i:s', time() - 86400);
        }
        // a row stamped in the future (clock skew in a source) must not freeze the tail
        if (strtotime($since) > time()) {
            $since = date('Y-m-d H:i:s');
        }
        // one second of overlap: rows with the same timestamp are de-duplicated by id in the browser
        $since = date('Y-m-d H:i:s', strtotime($since) - 1);
        $f = ['since' => $since, 'limit' => $sec === 'logs' ? 200 : 30];
        if ($sec === 'logs') {
            $f['source'] = (string) ($_GET['source'] ?? '');
            $f['level'] = (string) ($_GET['level'] ?? '');
            $f['q'] = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120);
            $f['view'] = (string) ($_GET['view'] ?? '');
        }
        // some sources match on a later field (an alert's last_seen) than the time they show: keep only rows newer by their own time
        $rows = array_values(array_filter(sl_dvxLogsPublic(sl_dvxLogs($f)), fn($r) => (string) $r['ts'] >= $since));
        $logsOk = $sec === 'logs' || sl_dvxAllowed($user, 'logs');
        foreach ($rows as &$r) {
            $r['href'] = $logsOk ? sl_route('DevLogs', ['source' => $r['source'], 'window' => 1440, 'row' => $r['id']]) : '#';
            if ($sec === 'overview') {
                unset($r['detail'], $r['raw']);
            }
        }
        unset($r);
        sl_dvxJson(['success' => true, 'rows' => $rows, 'newest' => isset($rows[0]['ts']) ? min((string) $rows[0]['ts'], date('Y-m-d H:i:s')) : null, 'at' => date('H:i:s')]);
        break;

    case 'pulse':
        sl_dvxJson(['success' => true] + sl_dvxPulse());
        break;

    case 'charts':
        $range = in_array($_GET['range'] ?? '', ['24h', '7d', '30d'], true) ? (string) $_GET['range'] : '24h';
        $out = [];
        foreach (sl_dvxOverviewCharts($range)['charts'] as $ch) {
            $out[] = ['id' => $ch['id'], 'html' => sl_dvxChartBody($ch)];
        }
        sl_dvxJson(['success' => true, 'range' => $range, 'charts' => $out]);
        break;

    case 'svc':
        $key = (string) ($_GET['key'] ?? '');
        $map = sl_dvxSvcLogMap();
        if (!isset($map[$key])) {
            sl_dvxJson(['success' => true, 'rows' => [], 'none' => 'No event stream for this component.']);
        }
        $logsOk = sl_dvxAllowed($user, 'logs');
        $rows = sl_dvxLogsPublic(sl_dvxLogs($map[$key] + ['window' => 1440, 'limit' => 6]));
        foreach ($rows as &$r) {
            $r['href'] = $logsOk ? sl_route('DevLogs', ['source' => $r['source'], 'window' => 1440, 'row' => $r['id']]) : '#';
            unset($r['raw'], $r['detail']);
        }
        unset($r);
        sl_dvxJson(['success' => true, 'rows' => $rows]);
        break;
}
