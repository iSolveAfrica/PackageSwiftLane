<?php
// *************************************************************************
// * Gerald Master View — endpoint (helpers/mgmt.php).                     *
// *                                                                       *
// * GET  data        from to branch_id        every widget's figures      *
// * GET  export      + format=csv|xlsx|pdf sections[]   file download     *
// * GET  insights    rule-based + cached AI analysis                       *
// * POST insights    refresh=1 asks the AI again                           *
// * POST save_layout order[] hidden[]        this user's dashboard         *
// * POST ack_alert   key note                acknowledge an anomaly        *
// * GET  schedules / POST save_schedule delete_schedule run_schedule      *
// * GET  settings / POST save_settings       targets, thresholds (admins)  *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/mgmt.php');
require_login();

if (!cdp_mgmtCanView($user)) {
    cdp_opsOut(['ok' => false, 'error' => 'The Master View is for administrators and branch managers.'], 403);
}
if (!cdp_mgmtEnsureTables()) {
    cdp_opsOut(['ok' => false, 'error' => 'The Master View tables could not be created.'], 500);
}
@set_time_limit(120);

$action = (string) ($_REQUEST['action'] ?? 'data');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$isAdmin = cdp_opsRank() >= 3;
if (in_array($action, ['save_layout', 'ack_alert', 'save_schedule', 'delete_schedule', 'run_schedule', 'save_settings'], true) && !$isPost) {
    cdp_opsOut(['ok' => false, 'error' => 'Use POST.'], 405);
}
if (in_array($action, ['save_schedule', 'delete_schedule', 'run_schedule', 'save_settings'], true) && !$isAdmin) {
    cdp_opsOut(['ok' => false, 'error' => 'Administrators only.'], 403);
}
$f = cdp_mgmtFilters($_REQUEST);

switch ($action) {
    case 'data':
        $only = array_values(array_intersect((array) ($_GET['widgets'] ?? []), array_keys(cdp_mgmtWidgets())));
        $branches = array_values(array_filter(cdp_staffBranches(true), function ($b) use ($isAdmin) {
            return $isAdmin || in_array((int) $b->id, cdp_staffBranchIds(), true);
        }));
        cdp_opsOut(['ok' => true, 'data' => cdp_mgmtData($f, $only), 'widgets' => cdp_mgmtWidgets(), 'layout' => cdp_mgmtLayout(cdp_opsUid()),
            'branches' => array_map(function ($b) { return ['id' => (int) $b->id, 'name' => $b->name, 'linked' => (bool) ($b->office_id || $b->branch_office_id)]; }, $branches),
            'is_admin' => $isAdmin, 'currency' => (isset($core) && is_object($core)) ? (string) ($core->currency ?? '') : '', 'settings' => cdp_mgmtSettings()]);

    case 'export':
        $sections = array_values(array_intersect((array) ($_GET['sections'] ?? []), array_keys(cdp_mgmtWidgets())));
        $format = in_array($_GET['format'] ?? 'csv', ['csv', 'xlsx', 'pdf'], true) ? $_GET['format'] : 'csv';
        $d = cdp_mgmtData($f, $sections);
        $secs = cdp_mgmtReportSections($d, $sections);
        require_once(__DIR__ . '/../../helpers/activity_log.php');
        cdp_activityLog(['module' => 'reports', 'verb' => 'export', 'summary' => 'Master View exported (' . $format . ') ' . $f['from'] . ' to ' . $f['to'], 'meta' => ['sections' => $sections]]);
        cdp_opsSendExport($format, 'master-view-' . $f['from'] . '-to-' . $f['to'], 'Gerald Master View ' . $f['from'] . ' to ' . $f['to'], $secs,
            'Compared with ' . $f['prev_from'] . ' to ' . $f['prev_to']);

    case 'insights':
        cdp_opsOut(cdp_mgmtInsights($f, $isPost && !empty($_POST['refresh'])));

    case 'save_layout':
        cdp_opsOut(['ok' => cdp_mgmtSaveLayout(cdp_opsUid(), (array) ($_POST['order'] ?? []), (array) ($_POST['hidden'] ?? []))]);

    case 'ack_alert':
        $key = substr((string) ($_POST['key'] ?? ''), 0, 120);
        if ($key === '') {
            cdp_opsOut(['ok' => false, 'error' => 'Missing alert.']);
        }
        $r = cdp_opsX("INSERT INTO cdb_mgmt_alert_acks (alert_key, acked_by, note, acked_at) VALUES (:k, :u, :n, :t)
                       ON DUPLICATE KEY UPDATE acked_by = VALUES(acked_by), note = VALUES(note), acked_at = VALUES(acked_at)",
            [':k' => $key, ':u' => cdp_opsUid(), ':n' => mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255), ':t' => cdp_opsNow()]);
        cdp_opsOut(['ok' => $r['ok']]);

    case 'schedules':
        $rows = cdp_opsQ("SELECT * FROM cdb_mgmt_schedules ORDER BY name");
        cdp_opsOut(['ok' => true, 'rows' => $rows, 'widgets' => cdp_mgmtWidgets(), 'is_admin' => $isAdmin]);

    case 'save_schedule':
        cdp_opsOut(cdp_mgmtSaveSchedule($_POST));

    case 'delete_schedule':
        cdp_opsOut(['ok' => cdp_opsX("DELETE FROM cdb_mgmt_schedules WHERE id = :id", [':id' => (int) ($_POST['id'] ?? 0)])['ok']]);

    case 'run_schedule':
        $s = cdp_opsOne("SELECT * FROM cdb_mgmt_schedules WHERE id = :id", [':id' => (int) ($_POST['id'] ?? 0)]);
        if (!$s) {
            cdp_opsOut(['ok' => false, 'error' => 'Schedule not found.'], 404);
        }
        $ok = cdp_mgmtRunSchedule($s);
        $s = cdp_opsOne("SELECT last_status FROM cdb_mgmt_schedules WHERE id = :id", [':id' => (int) $s->id]);
        cdp_opsOut(['ok' => $ok, 'error' => $ok ? '' : (string) $s->last_status, 'status' => (string) $s->last_status]);

    case 'settings':
        cdp_opsOut(['ok' => true, 'settings' => cdp_mgmtSettings(), 'is_admin' => $isAdmin]);

    case 'save_settings':
        foreach (cdp_mgmtSettingDefaults() as $k => $def) {
            if (!isset($_POST[$k])) {
                continue;
            }
            $v = trim((string) $_POST[$k]);
            if (in_array($k, ['off_hours_start', 'off_hours_end'], true)) {
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
                    continue;
                }
            } elseif ($k === 'overhead_per_shipment' || $k === 'anomaly_z') {
                $v = (string) max(0, (float) $v);
            } else {
                $v = (string) max(1, min(3650, (int) $v));
            }
            cdp_opsSettingSet('mgmt', $k, $v);
        }
        cdp_opsOut(['ok' => true]);

    default:
        cdp_opsOut(['ok' => false, 'error' => 'Unknown action.'], 400);
}
