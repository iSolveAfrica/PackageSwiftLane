<?php
/**
 * Developer console writes: alerts (acknowledge / resolve through helpers/devops.php),
 * alert rules (cdb_dev_alert_rules), "evaluate now", issue tracker (cdb_dev_issues +
 * cdb_dev_issue_events) and the change log (cdb_dev_changelog). Gate: view_developer.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/rbac.php';
require_once __DIR__ . '/../../helpers/devops.php';
require_once __DIR__ . '/../../helpers/dev_console.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
require_permission('view_developer');
$db = new Conexion();
$actor = (int) $_SESSION['userid'];
$actorName = trim((string) sl_scalar("SELECT CONCAT(fname,' ',lname) FROM cdb_users WHERE id = :u", [':u' => $actor], '')) ?: 'Developer';
$op = (string) ($_POST['op'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$log = function (string $action, string $label, string $summary, string $type, $eid, string $elabel) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'developer', 'action' => $action, 'action_label' => $label, 'verb' => strpos($action, 'create') !== false ? 'create' : 'update', 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => $type, 'entity_id' => $eid, 'entity_label' => $elabel]);
    }
};
$need = function (string $t) {
    if (!sl_tableExists($t)) sl_json(['success' => false, 'message' => "$t is missing: run migrations/2026_11_06_freight_devops.sql"], 409);
};
$issueEvent = function (int $iid, string $kind, string $body) use ($db, $actor, $actorName) {
    $db->cdp_query('INSERT INTO cdb_dev_issue_events (issue_id, user_id, user_name, kind, body, created_at) VALUES (:i, :u, :n, :k, :b, NOW())');
    $db->bind(':i', $iid); $db->bind(':u', $actor); $db->bind(':n', $actorName); $db->bind(':k', $kind); $db->bind(':b', $body);
    $db->cdp_execute();
};
switch ($op) {
    case 'alert_ack':
        $ok = cdp_devAlertAck($id, $actor);
        $log('alert_ack', 'Acknowledged alert', "alert #$id acknowledged", 'dev_alert', $id, '#' . $id);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Acknowledged' : 'Already acknowledged or closed', 'reload' => true]);
        break;
    case 'alert_resolve':
        $ok = cdp_devAlertResolve($id);
        $log('alert_resolve', 'Resolved alert', "alert #$id resolved by hand", 'dev_alert', $id, '#' . $id);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Resolved' : 'Already resolved', 'reload' => true]);
        break;
    case 'evaluate':
        $need('cdb_dev_alert_rules');
        require_once __DIR__ . '/../../helpers/cron_run.php';
        $r = cdp_cronWrap('dev_alerts', function () {
            $o = sl_devEvaluateAlerts();
            return "checked={$o['checked']} firing={$o['firing']} opened={$o['opened']} resolved={$o['resolved']}";
        });
        sl_json(['success' => true, 'message' => 'Rules evaluated: ' . $r, 'reload' => true]);
        break;
    case 'rule_save':
        $need('cdb_dev_alert_rules');
        $metrics = ['disk_pct', 'failed_cron', 'cron_stale_min', 'failed_logins', 'queue_backlog', 'php_errors', 'otp_failures'];
        $errors = [];
        $label = mb_substr(trim((string) ($_POST['label'] ?? '')), 0, 150);
        $metric = in_array($_POST['metric'] ?? '', $metrics, true) ? (string) $_POST['metric'] : '';
        $cmp = in_array($_POST['comparator'] ?? '>', ['>', '>=', '<', '<=', '='], true) ? (string) ($_POST['comparator'] ?? '>') : '>';
        if ($label === '') $errors['label'] = 'Describe the rule';
        if ($metric === '') $errors['metric'] = 'Pick what to watch';
        if (!is_numeric($_POST['threshold'] ?? null)) $errors['threshold'] = 'A number';
        if ($errors) sl_json(['success' => false, 'message' => 'Check the form', 'errors' => $errors], 422);
        $sev = ($_POST['severity'] ?? '') === 'critical' ? 'critical' : 'warning';
        $win = max(0, min(10080, (int) ($_POST['window_min'] ?? 60)));
        if ($id) {
            $db->cdp_query('UPDATE cdb_dev_alert_rules SET label = :l, metric = :m, comparator = :c, threshold = :t, window_min = :w, severity = :s, updated_at = NOW() WHERE id = :id');
            $db->bind(':id', $id);
        } else {
            $db->cdp_query('INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at) VALUES (:k, :l, :m, :c, :t, :w, :s, :n, 1, NOW())');
            $db->bind(':k', mb_substr($metric . '_' . substr(bin2hex(random_bytes(3)), 0, 6), 0, 40)); $db->bind(':n', 'none');
        }
        $db->bind(':l', $label); $db->bind(':m', $metric); $db->bind(':c', $cmp); $db->bind(':t', (float) $_POST['threshold']); $db->bind(':w', $win); $db->bind(':s', $sev);
        $ok = $db->cdp_execute();
        if ($ok) $log($id ? 'rule_update' : 'rule_create', 'Saved alert rule', "rule \"$label\": $metric $cmp " . (float) $_POST['threshold'], 'dev_alert_rule', $id ?: (int) $db->dbh->lastInsertId(), $label);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Rule saved' : 'Could not save', 'reload' => true]);
        break;
    case 'rule_toggle':
        $need('cdb_dev_alert_rules');
        $r = sl_row('SELECT label FROM cdb_dev_alert_rules WHERE id = :id', [':id' => $id]);
        if (!$r) sl_json(['success' => false, 'message' => 'Rule not found'], 404);
        $on = (int) !empty($_POST['value']);
        $db->cdp_query('UPDATE cdb_dev_alert_rules SET enabled = :e, updated_at = NOW() WHERE id = :id');
        $db->bind(':e', $on); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) $log('rule_toggle', 'Alert rule on/off', "rule \"{$r->label}\" " . ($on ? 'on' : 'off'), 'dev_alert_rule', $id, $r->label);
        sl_json(['success' => (bool) $ok, 'message' => $r->label . ($on ? ' on' : ' off')]);
        break;
    case 'issue_create':
        $need('cdb_dev_issues');
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 190);
        if ($title === '') sl_json(['success' => false, 'message' => 'Give the ticket a title', 'errors' => ['title' => 'Required']], 422);
        $type = in_array($_POST['type'] ?? 'bug', ['bug', 'error', 'request', 'task'], true) ? (string) ($_POST['type'] ?? 'bug') : 'bug';
        $pri = in_array($_POST['priority'] ?? 'p2', ['p1', 'p2', 'p3'], true) ? (string) ($_POST['priority'] ?? 'p2') : 'p2';
        $assignee = (int) ($_POST['assignee_id'] ?? 0) ?: null;
        $next = (int) sl_scalar('SELECT COALESCE(MAX(id),0) + 1 FROM cdb_dev_issues');
        $ref = 'DEV-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
        $db->cdp_query('INSERT INTO cdb_dev_issues (ref, title, type, priority, area, page, status, assignee_id, reporter_id, steps, log_excerpt, fix_version, created_at) VALUES (:r, :t, :ty, :p, :a, :pg, :s, :as, :rep, :st, :lg, :fv, NOW())');
        $db->bind(':r', $ref); $db->bind(':t', $title); $db->bind(':ty', $type); $db->bind(':p', $pri);
        $db->bind(':a', mb_substr(trim((string) ($_POST['area'] ?? '')), 0, 60) ?: null); $db->bind(':pg', mb_substr(trim((string) ($_POST['page'] ?? '')), 0, 120) ?: null);
        $db->bind(':s', 'open'); $db->bind(':as', $assignee); $db->bind(':rep', $actor);
        $db->bind(':st', mb_substr(trim((string) ($_POST['steps'] ?? '')), 0, 4000) ?: null); $db->bind(':lg', mb_substr(trim((string) ($_POST['log_excerpt'] ?? '')), 0, 4000) ?: null);
        $db->bind(':fv', mb_substr(trim((string) ($_POST['fix_version'] ?? '')), 0, 40) ?: null);
        if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not create the ticket']);
        $iid = (int) $db->dbh->lastInsertId();
        $issueEvent($iid, 'create', 'Opened ' . strtoupper($pri) . ' ' . $type);
        $log('issue_create', 'Opened dev ticket', "$ref $title", 'dev_issue', $iid, $ref);
        sl_json(['success' => true, 'message' => "$ref created", 'redirect' => sl_route('DevIssues', ['id' => $iid])]);
        break;
    case 'issue_status':
        $need('cdb_dev_issues');
        $i = sl_row('SELECT * FROM cdb_dev_issues WHERE id = :id', [':id' => $id]);
        if (!$i) sl_json(['success' => false, 'message' => 'Ticket not found'], 404);
        $st = in_array($_POST['status'] ?? '', ['open', 'in_progress', 'review', 'done'], true) ? (string) $_POST['status'] : '';
        if ($st === '') sl_json(['success' => false, 'message' => 'Unknown status'], 422);
        $db->cdp_query("UPDATE cdb_dev_issues SET status = :s, closed_at = CASE WHEN :s2 = 'done' THEN NOW() ELSE NULL END, updated_at = NOW() WHERE id = :id");
        $db->bind(':s', $st); $db->bind(':s2', $st); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) {
            $issueEvent($id, 'status', $i->status . ' → ' . $st);
            $log('issue_status', 'Dev ticket status', "{$i->ref}: {$i->status} → $st", 'dev_issue', $id, $i->ref);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? $i->ref . ' → ' . str_replace('_', ' ', $st) : 'Could not update', 'reload' => true]);
        break;
    case 'issue_assign':
        $need('cdb_dev_issues');
        $i = sl_row('SELECT * FROM cdb_dev_issues WHERE id = :id', [':id' => $id]);
        if (!$i) sl_json(['success' => false, 'message' => 'Ticket not found'], 404);
        $to = (int) ($_POST['assignee_id'] ?? 0);
        $name = $to ? trim((string) sl_scalar("SELECT CONCAT(fname,' ',lname) FROM cdb_users WHERE id = :u AND userlevel <> 1", [':u' => $to], '')) : '';
        if ($to && $name === '') sl_json(['success' => false, 'message' => 'Not a staff member'], 422);
        $db->cdp_query('UPDATE cdb_dev_issues SET assignee_id = :a, updated_at = NOW() WHERE id = :id');
        $db->bind(':a', $to ?: null); $db->bind(':id', $id);
        $db->cdp_execute();
        $issueEvent($id, 'assign', $to ? 'Assigned to ' . $name : 'Unassigned');
        $log('issue_assign', 'Dev ticket assigned', "{$i->ref} → " . ($name ?: 'nobody'), 'dev_issue', $id, $i->ref);
        sl_json(['success' => true, 'message' => $to ? 'Assigned to ' . $name : 'Unassigned', 'reload' => true]);
        break;
    case 'issue_comment':
        $need('cdb_dev_issues');
        $i = sl_row('SELECT ref FROM cdb_dev_issues WHERE id = :id', [':id' => $id]);
        if (!$i) sl_json(['success' => false, 'message' => 'Ticket not found'], 404);
        $body = mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 4000);
        if ($body === '') sl_json(['success' => false, 'message' => 'Write a comment', 'errors' => ['body' => 'Required']], 422);
        $issueEvent($id, 'comment', $body);
        $db->cdp_query('UPDATE cdb_dev_issues SET updated_at = NOW() WHERE id = :id');
        $db->bind(':id', $id);
        $db->cdp_execute();
        $log('issue_comment', 'Dev ticket comment', "comment on {$i->ref}", 'dev_issue', $id, $i->ref);
        sl_json(['success' => true, 'message' => 'Comment posted', 'reload' => true]);
        break;
    case 'changelog_add':
        $need('cdb_dev_changelog');
        $ver = mb_substr(trim((string) ($_POST['version'] ?? '')), 0, 40);
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 190);
        $errors = [];
        if ($ver === '') $errors['version'] = 'Version or release name';
        if ($title === '') $errors['title'] = 'What changed';
        if ($errors) sl_json(['success' => false, 'message' => 'Check the form', 'errors' => $errors], 422);
        $kind = in_array($_POST['kind'] ?? 'change', ['feature', 'fix', 'change', 'security', 'migration'], true) ? (string) ($_POST['kind'] ?? 'change') : 'change';
        $env = in_array($_POST['environment'] ?? 'production', ['production', 'staging', 'development'], true) ? (string) ($_POST['environment'] ?? 'production') : 'production';
        $at = strtotime((string) ($_POST['released_at'] ?? '')) ?: time();
        $db->cdp_query('INSERT INTO cdb_dev_changelog (version, kind, title, body, ref, author, environment, released_at, created_by, created_at) VALUES (:v, :k, :t, :b, :r, :a, :e, :at, :by, NOW())');
        $db->bind(':v', $ver); $db->bind(':k', $kind); $db->bind(':t', $title); $db->bind(':b', mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 4000) ?: null);
        $db->bind(':r', mb_substr(trim((string) ($_POST['ref'] ?? '')), 0, 100) ?: null); $db->bind(':a', $actorName); $db->bind(':e', $env); $db->bind(':at', date('Y-m-d H:i:s', $at)); $db->bind(':by', $actor);
        $ok = $db->cdp_execute();
        if ($ok) $log('changelog_create', 'Change log entry', "$ver: $title", 'dev_changelog', (int) $db->dbh->lastInsertId(), $ver);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Added to the change log' : 'Could not save', 'reload' => true]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
