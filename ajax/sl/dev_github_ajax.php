<?php
/**
 * Developer console: GitHub connection (settings, test, sync, jobs of a run) and deployment
 * records. POST + CSRF (require_login), view_developer, cdp_devCanUse (assigned super admin) and
 * the per-section check (sl_ghCan). Every write is audited in cdb_activity_log. GitHub is only
 * ever read (helpers/dev_github.php sl_ghGet).
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/dev_github.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
require_permission('view_developer');
$op = (string) ($_POST['op'] ?? '');
$section = in_array($op, ['deploy_record', 'deploy_record_delete'], true) ? 'deployments' : ($op === 'gh_jobs' ? 'deployments' : 'github');
if (!sl_ghCan($user, $section)) {
    sl_json(['success' => false, 'message' => 'You do not have access to this developer section'], 403);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
sl_ghEnsureTables();
$actor = (int) $_SESSION['userid'];
$audit = function (string $action, string $label, string $summary, string $outcome = 'success', array $meta = []) {
    cdp_activityLog(['module' => 'developer', 'action' => 'developer.' . $action, 'action_label' => $label, 'verb' => 'update', 'outcome' => $outcome,
        'summary' => $summary, 'entity_type' => 'dev_github', 'entity_label' => 'GitHub / deployments', 'meta' => $meta]);
};

switch ($op) {
    case 'gh_save':
        $token = isset($_POST['token']) ? trim((string) $_POST['token']) : null;
        $clear = !empty($_POST['clear_token']);
        $before = sl_ghConfig();
        $r = sl_ghSaveConfig($_POST, $token, $clear, $actor);
        if (!$r['ok']) {
            if (!empty($r['message'])) {
                $audit('github_settings', 'GitHub connection not changed', 'New GitHub settings failed the connection test and were not saved', 'failure', ['owner' => (string) ($_POST['owner'] ?? ''), 'api_base' => (string) ($_POST['api_base'] ?? '')]);
            }
            sl_json(['success' => false, 'message' => $r['message'] ?? 'Check the form', 'errors' => $r['errors']], 422);
        }
        $c = sl_ghConfig();
        $summary = 'GitHub repository ' . $c['owner'] . '/' . $c['repo'] . ' · API ' . $c['api_base']
            . ($r['baseChanged'] ? ' (API host changed from ' . $before['api_base'] . ')' : '')
            . ($clear ? ' · token removed' : ($token ? ' · token replaced (' . $c['token_mask'] . ')' : ''));
        $audit('github_settings', $r['baseChanged'] ? 'Changed the GitHub API host' : 'Changed GitHub connection', $summary, 'success', ['owner' => $c['owner'], 'repo' => $c['repo'], 'api_base' => $c['api_base'], 'api_base_before' => $before['api_base'], 'cache_cleared' => $r['changedRepo']]);
        $msg = 'GitHub settings saved';
        if (!empty($r['tested'])) {
            $msg .= ' · ' . $r['tested']['message'] . ' · cached data of the previous repository cleared';
        } elseif ($c['ready']) {
            $t = sl_ghTest();
            $msg .= ' · ' . $t['message'];
        }
        sl_json(['success' => true, 'message' => $msg, 'reload' => true]);
        break;

    case 'gh_test':
        $t = sl_ghTest();
        $audit('github_test', 'Tested GitHub connection', $t['message'], $t['ok'] ? 'success' : 'failure');
        sl_json(['success' => $t['ok'], 'message' => $t['message'], 'reload' => $t['ok']]);
        break;

    case 'gh_sync':
        @set_time_limit(180);
        $r = sl_ghSync();
        $audit('github_sync', 'Synced GitHub', $r['message'], $r['ok'] ? 'success' : 'failure', ['counts' => $r['counts']]);
        sl_json(['success' => $r['ok'], 'message' => $r['message'], 'counts' => $r['counts'], 'reload' => $r['ok']]);
        break;

    case 'gh_jobs':
        $id = (int) ($_POST['run_id'] ?? 0);
        if (!cdp_devOne('SELECT id FROM cdb_dev_gh_runs WHERE id = :i', [':i' => $id])) {
            sl_json(['success' => false, 'message' => 'Unknown run'], 404);
        }
        $ok = sl_ghFetchJobs($id);
        sl_json(['success' => $ok, 'message' => $ok ? 'Jobs and steps loaded' : 'Could not load the jobs from GitHub', 'reload' => $ok]);
        break;

    case 'deploy_record':
        $errors = [];
        $env = mb_substr(trim((string) ($_POST['environment'] ?? '')), 0, 40);
        if (!preg_match('/^[A-Za-z0-9 _.-]{2,40}$/', $env)) $errors['environment'] = 'Name the environment';
        $status = in_array($_POST['status'] ?? '', ['success', 'failure', 'rolled_back'], true) ? (string) $_POST['status'] : '';
        if ($status === '') $errors['status'] = 'Pick a result';
        $sha = strtolower(trim((string) ($_POST['commit_sha'] ?? '')));
        if ($sha !== '' && !preg_match('/^[0-9a-f]{7,40}$/', $sha)) $errors['commit_sha'] = '7 to 40 hex characters';
        $branch = trim((string) ($_POST['branch'] ?? ''));
        if ($branch !== '' && !preg_match('#^[A-Za-z0-9_./-]{1,120}$#', $branch)) $errors['branch'] = 'Not a valid branch name';
        $at = trim((string) ($_POST['deployed_at'] ?? ''));
        $ts = $at !== '' ? strtotime($at) : time();
        if (!$ts || $ts > time() + 300) $errors['deployed_at'] = 'A date and time, not in the future';
        $dur = trim((string) ($_POST['duration_min'] ?? ''));
        if ($dur !== '' && (!is_numeric($dur) || (float) $dur < 0 || (float) $dur > 1440)) $errors['duration_min'] = 'Minutes, 0 to 1440';
        if ($errors) {
            sl_json(['success' => false, 'message' => 'Check the form', 'errors' => $errors], 422);
        }
        cdp_devX('INSERT INTO cdb_dev_deploy_records (environment, version, commit_sha, branch, status, duration_s, notes, recorded_by, deployed_at, created_at) VALUES (:e, :v, :s, :b, :st, :d, :n, :u, :at, :c)', [
            ':e' => $env, ':v' => mb_substr(trim((string) ($_POST['version'] ?? '')), 0, 60) ?: null, ':s' => $sha ?: null, ':b' => $branch ?: null, ':st' => $status,
            ':d' => $dur !== '' ? (int) round((float) $dur * 60) : null, ':n' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 500) ?: null, ':u' => $actor,
            ':at' => date('Y-m-d H:i:s', $ts), ':c' => sl_ghNow()]);
        $audit('deploy_record', 'Recorded a deployment', "Deployment to $env recorded ($status" . ($sha ? ', ' . substr($sha, 0, 7) : '') . ')');
        sl_json(['success' => true, 'message' => 'Deployment recorded', 'reload' => true]);
        break;

    case 'deploy_record_delete':
        $id = (int) ($_POST['id'] ?? 0);
        $row = cdp_devOne('SELECT * FROM cdb_dev_deploy_records WHERE id = :i', [':i' => $id]);
        if (!$row) {
            sl_json(['success' => false, 'message' => 'Not found'], 404);
        }
        cdp_devX('DELETE FROM cdb_dev_deploy_records WHERE id = :i', [':i' => $id]);
        $audit('deploy_record_delete', 'Deleted a deployment record', "Deployment record #$id ({$row->environment}, {$row->deployed_at}) deleted");
        sl_json(['success' => true, 'message' => 'Record deleted', 'reload' => true]);
        break;

    default:
        sl_json(['success' => false, 'message' => 'Unknown action'], 400);
}
