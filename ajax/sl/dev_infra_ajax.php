<?php
/**
 * Developer console, package "infra": writes of the Infrastructure and Alerts pages and of the
 * "Infrastructure overrides" panel of Operating costs.
 *
 * Gate: signed in, CSRF token (require_login), view_developer, assigned super admin
 * (cdp_devCanUse) and the section switch (cdp_devSectionAllowed, access package):
 *   overrides:      ovr_save, ovr_clear (the separate "Infrastructure overrides" section)
 *   infrastructure: rescan
 *   alerts:         alert_ack, alert_resolve, alert_reopen, evaluate, rule_save, rule_toggle,
 *                   rule_delete, notify_save, notify_test
 * POST only. Every change is written to cdb_activity_log (module developer). Nothing here runs a
 * shell command or echoes a stored secret.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/rbac.php';
require_once __DIR__ . '/../../helpers/devops.php';
require_once __DIR__ . '/../../helpers/dev_console.php';
require_once __DIR__ . '/../../helpers/dev_infra.php';
require_login();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
require_permission('view_developer');
$core = new Core(); // applies the application time zone, so stored times match the rest of the console
if (defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
if (function_exists('cdp_reqmSkip')) {
    cdp_reqmSkip();
}
// Request values: strings only (an array such as metric[]=x must not reach isset/offset or string casts).
$in = fn(string $k): string => is_string($_POST[$k] ?? null) ? (string) $_POST[$k] : '';
$op = $in('op');
$sections = ['ovr_save' => 'overrides', 'ovr_clear' => 'overrides', 'rescan' => 'infrastructure',
    'alert_ack' => 'alerts', 'alert_resolve' => 'alerts', 'alert_reopen' => 'alerts', 'evaluate' => 'alerts', 'rule_save' => 'alerts', 'rule_toggle' => 'alerts',
    'rule_delete' => 'alerts', 'notify_save' => 'alerts', 'notify_test' => 'alerts'];
if (!isset($sections[$op])) {
    sl_json(['success' => false, 'message' => 'Unknown action'], 400);
}
if (!sl_dinfAllowed($user, $sections[$op])) {
    sl_json(['success' => false, 'message' => 'This developer section is not available to you'], 403);
}
$actor = (int) $_SESSION['userid'];
$actorName = trim((string) sl_scalar("SELECT CONCAT(fname,' ',lname) FROM cdb_users WHERE id = :u", [':u' => $actor], '')) ?: 'Super admin';
$id = is_scalar($_POST['id'] ?? null) ? (int) $_POST['id'] : 0;
$log = function (string $action, string $label, string $summary, string $type, $eid, string $elabel, array $changes = [], string $verb = 'update') {
    $GLOBALS['cdp_activity_logged'] = true;
    cdp_activityLog(['module' => 'developer', 'action' => 'developer.' . $action, 'action_label' => $label, 'label' => $label, 'verb' => $verb, 'outcome' => 'success',
        'summary' => mb_substr($summary, 0, 250), 'entity_type' => $type, 'entity_id' => $eid, 'entity_label' => $elabel, 'changes' => $changes]);
};
$fmt = fn($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');

switch ($op) {
    /* ------------------------------------------------------------ overrides */
    case 'ovr_save':
        $key = $in('key');
        $cat = sl_dinfCatalog()[$key] ?? null;
        if (!$cat) {
            sl_json(['success' => false, 'message' => 'Unknown figure'], 422);
        }
        [$val, $err] = sl_dinfValidate($key, $in('value'));
        if ($err) {
            sl_json(['success' => false, 'message' => $err, 'errors' => ['value' => $err]], 422);
        }
        // used cannot exceed total (against the other override or the measured total)
        $pairs = ['disk_used_gb' => 'disk_total_gb', 'ram_used_gb' => 'ram_total_gb', 'bandwidth_used_gb' => null];
        if (isset($pairs[$key]) && $pairs[$key]) {
            $tot = cdp_devOverride($pairs[$key]) ?? (sl_dinfMeasured()[$pairs[$key]]['v'] ?? null);
            if ($tot !== null && (float) $val > (float) $tot) {
                $e = 'Higher than the total (' . $fmt($tot) . ' ' . $cat['unit'] . ')';
                sl_json(['success' => false, 'message' => $e, 'errors' => ['value' => $e]], 422);
            }
        }
        if (in_array($key, ['disk_total_gb', 'ram_total_gb'], true)) {
            $usedKey = $key === 'disk_total_gb' ? 'disk_used_gb' : 'ram_used_gb';
            $used = cdp_devOverride($usedKey);
            if ($used !== null && (float) $used > (float) $val) {
                $e = 'Lower than the used figure override (' . $fmt($used) . ' GB): change or clear that first';
                sl_json(['success' => false, 'message' => $e, 'errors' => ['value' => $e]], 422);
            }
            // no used override: the total cannot be below what PHP measures as used
            $mUsed = $used === null ? (sl_dinfMeasured()[$usedKey]['v'] ?? null) : null;
            if ($mUsed !== null && (float) $mUsed > (float) $val) {
                $what = $key === 'disk_total_gb' ? 'Disk space used' : 'RAM in use';
                $e = 'PHP measures ' . $fmt($mUsed) . ' GB used, more than ' . $fmt($val) . ' GB: first set "' . $what . '" (from the control panel), then the total';
                sl_json(['success' => false, 'message' => $e, 'errors' => ['value' => $e]], 422);
            }
        }
        $note = trim($in('note'));
        if (strpbrk($note, '<>') !== false) {
            sl_json(['success' => false, 'message' => 'Leave out < and > in the note', 'errors' => ['note' => 'Plain text only']], 422);
        }
        $note = mb_substr($note, 0, 255);
        $old = cdp_devOverrideMeta($key);
        if (!sl_dinfOverrideSet($key, (string) $val, $note, $actor, $actorName)) {
            sl_json(['success' => false, 'message' => 'Could not save the override']);
        }
        $shown = $val . ($cat['unit'] && $cat['type'] === 'num' ? ' ' . $cat['unit'] : '');
        $log('override_set', 'Set infrastructure override', $cat['label'] . ' = ' . $shown . ($old ? ' (was ' . $old['v'] . ')' : ''), 'dev_override', null, $key,
            ['value' => ['from' => $old['v'] ?? null, 'to' => $val]]);
        sl_json(['success' => true, 'message' => $cat['label'] . ' overridden: ' . $shown, 'reload' => true]);
        break;

    case 'ovr_clear':
        $key = $in('key');
        $cat = sl_dinfCatalog()[$key] ?? null;
        $old = $cat ? cdp_devOverrideMeta($key) : null;
        if (!$cat || !$old) {
            sl_json(['success' => false, 'message' => 'No override to clear'], 404);
        }
        sl_dinfOverrideClear($key);
        $log('override_clear', 'Cleared infrastructure override', $cat['label'] . ' back to the measured value (was ' . $old['v'] . ')', 'dev_override', null, $key,
            ['value' => ['from' => $old['v'], 'to' => null]], 'delete');
        sl_json(['success' => true, 'message' => $cat['label'] . ': override cleared', 'reload' => true]);
        break;

    case 'rescan':
        $t = microtime(true);
        $s = sl_dinfStorage(true);
        $log('storage_scan', 'Re-scanned storage', 'Storage scan: ' . number_format($s['files']) . ' files in ' . round((microtime(true) - $t) * 1000) . ' ms' . ($s['partial'] ? ' (partial)' : ''), 'dev_infra', null, 'storage');
        sl_json(['success' => true, 'message' => 'Storage re-scanned' . ($s['partial'] ? ' (stopped early: very many files)' : ''), 'reload' => true]);
        break;

    /* --------------------------------------------------------------- alerts */
    case 'alert_ack':
    case 'alert_resolve':
    case 'alert_reopen':
        $a = cdp_devOne('SELECT id, title, status FROM cdb_dev_alerts WHERE id = :i', [':i' => $id]);
        if (!$a) {
            sl_json(['success' => false, 'message' => 'Alert not found'], 404);
        }
        if ($op === 'alert_ack') {
            $ok = cdp_devAlertAck($id, $actor) && $a->status === 'active';
            $msg = $ok ? 'Acknowledged: you own "' . $a->title . '"' : 'Only an active alert can be acknowledged';
        } elseif ($op === 'alert_resolve') {
            $ok = $a->status !== 'resolved' && cdp_devAlertResolve($id);
            if ($ok) {
                // marks a hand resolve, so sl_dinfRepairRuleAlerts() leaves it closed
                cdp_devX("UPDATE cdb_dev_alerts SET detail = CONCAT(LEFT(COALESCE(detail,''), 470), ' · resolved by hand') WHERE id = :i", [':i' => $id]);
            }
            $msg = $ok ? 'Resolved. If the condition is still true the next check opens a new alert.' : 'Already resolved';
        } elseif (cdp_devOne("SELECT id FROM cdb_dev_alerts WHERE alert_key = (SELECT alert_key FROM cdb_dev_alerts WHERE id = :i) AND status <> 'resolved' AND id <> :j", [':i' => $id, ':j' => $id])) {
            // one open alert per check: the check has already opened a newer one
            $ok = false;
            $msg = 'This check already has a newer open alert';
        } else {
            $ok = $a->status === 'resolved' && cdp_devX("UPDATE cdb_dev_alerts SET status = 'active', resolved_at = NULL, ack_by = NULL, ack_at = NULL, last_seen = :n,
                detail = REPLACE(REPLACE(REPLACE(COALESCE(detail,''), ' · resolved by hand', ''), ' · cleared', ''), ' · rule deleted', '') WHERE id = :i", [':n' => cdp_devNow(), ':i' => $id]);
            $msg = $ok ? 'Reopened' : 'Only a resolved alert can be reopened';
        }
        if ($ok) {
            $verbs = ['alert_ack' => ['acknowledged', 'Acknowledged alert'], 'alert_resolve' => ['resolved by hand', 'Resolved alert'], 'alert_reopen' => ['reopened', 'Reopened alert']];
            $log($op, $verbs[$op][1], 'Alert #' . $id . ' "' . $a->title . '" ' . $verbs[$op][0], 'dev_alert', $id, (string) $a->title, ['status' => ['from' => $a->status, 'to' => ['alert_ack' => 'acknowledged', 'alert_resolve' => 'resolved', 'alert_reopen' => 'active'][$op]]]);
        }
        sl_json(['success' => (bool) $ok, 'message' => $msg, 'reload' => (bool) $ok], $ok ? 200 : 409);
        break;

    case 'evaluate':
        if (!sl_tableExists('cdb_dev_alert_rules')) {
            sl_json(['success' => false, 'message' => 'cdb_dev_alert_rules is missing: run migrations/2026_11_06_freight_devops.sql'], 409);
        }
        // A manual run is recorded in the activity log only, NOT as a cron run of dev_alerts: the cron
        // health ("last evaluated", Scheduled jobs, the stale-cron rule) must keep showing whether the
        // scheduled job really runs. E-mails go out after the response (no SMTP wait on the button).
        $o = sl_devEvaluateAlerts(false);
        require_once __DIR__ . '/../../helpers/after_response.php';
        cdp_afterResponse(function () {
            try {
                sl_dinfNotifySweep();
            } catch (Throwable $e) {
                error_log('dev alerts notify: ' . $e->getMessage());
            }
        });
        $msg = ($o['checked'] ?? 0) . ' rules checked · ' . ($o['firing'] ?? 0) . ' firing · ' . ($o['opened'] ?? 0) . ' opened · ' . ($o['resolved'] ?? 0) . ' resolved'
            . (($o['opened'] ?? 0) ? ' · developers are e-mailed in the background' : '');
        $log('alerts_evaluate', 'Evaluated alert rules', $msg, 'dev_alert_rule', null, 'all rules');
        sl_json(['success' => true, 'message' => $msg, 'reload' => true]);
        break;

    case 'rule_save':
        if (!sl_tableExists('cdb_dev_alert_rules')) {
            sl_json(['success' => false, 'message' => 'cdb_dev_alert_rules is missing: run migrations/2026_11_06_freight_devops.sql'], 409);
        }
        $metrics = sl_dinfMetrics();
        $errors = [];
        $label = mb_substr(trim($in('label')), 0, 150);
        $metric = isset($metrics[$in('metric')]) ? $in('metric') : '';
        $cmp = in_array($in('comparator'), ['>', '>=', '<', '<=', '='], true) ? $in('comparator') : '>';
        $th = str_replace(',', '', trim($in('threshold')));
        if ($label === '') {
            $errors['label'] = 'Describe the rule';
        } elseif (strpbrk($label, '<>') !== false) {
            $errors['label'] = 'Leave out < and >';
        }
        if ($metric === '') {
            $errors['metric'] = 'Pick what to watch';
        }
        if (!is_numeric($th) || abs((float) $th) > 9999999999) {
            $errors['threshold'] = 'A number';
        } elseif ($metric !== '' && $metrics[$metric][1] === '%' && ((float) $th < 0 || (float) $th > 100)) {
            $errors['threshold'] = 'A percentage from 0 to 100';
        } elseif ((float) $th < 0) {
            // counts, ms, minutes and hours are never negative: a negative limit would always fire
            $errors['threshold'] = 'A number from 0 upwards';
        }
        if ($errors) {
            sl_json(['success' => false, 'message' => 'Check the form', 'errors' => $errors], 422);
        }
        $sev = in_array($in('severity'), ['critical', 'warning', 'info'], true) ? $in('severity') : 'warning';
        $notify = $in('notify') === 'none' ? 'none' : 'email';
        $win = $metrics[$metric][2] ? max(1, min(10080, (int) ($in('window_min') !== '' ? $in('window_min') : 60))) : 0;
        $bind = [':l' => $label, ':m' => $metric, ':c' => $cmp, ':t' => (float) $th, ':w' => $win, ':s' => $sev, ':n' => $notify];
        if ($id) {
            $old = sl_row('SELECT * FROM cdb_dev_alert_rules WHERE id = :id', [':id' => $id]);
            if (!$old) {
                sl_json(['success' => false, 'message' => 'Rule not found'], 404);
            }
            $ok = cdp_devX('UPDATE cdb_dev_alert_rules SET label = :l, metric = :m, comparator = :c, threshold = :t, window_min = :w, severity = :s, notify = :n, updated_at = NOW() WHERE id = :id', $bind + [':id' => $id]);
            $ch = [];
            foreach (['label' => $label, 'metric' => $metric, 'comparator' => $cmp, 'threshold' => $fmt($th), 'window_min' => $win, 'severity' => $sev, 'notify' => $notify] as $f => $nv) {
                $ov = $f === 'threshold' ? $fmt($old->$f) : (string) $old->$f;
                if ((string) $ov !== (string) $nv) {
                    $ch[$f] = ['from' => $ov, 'to' => $nv];
                }
            }
            if ($ok) {
                $log('rule_update', 'Changed alert rule', 'Rule "' . $label . '": ' . $metric . ' ' . $cmp . ' ' . $fmt($th), 'dev_alert_rule', $id, $label, $ch);
            }
        } else {
            $rk = mb_substr($metric . '_' . bin2hex(random_bytes(3)), 0, 40);
            $ok = cdp_devX('INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at) VALUES (:k, :l, :m, :c, :t, :w, :s, :n, 1, NOW())',
                $bind + [':k' => $rk]);
            if ($ok) {
                // by its own random key, not MAX(id) (two rules created at once)
                $nid = (int) sl_scalar('SELECT id FROM cdb_dev_alert_rules WHERE rule_key = :k', [':k' => $rk]);
                $log('rule_create', 'Created alert rule', 'Rule "' . $label . '": ' . $metric . ' ' . $cmp . ' ' . $fmt($th) . ($win ? ' over ' . $win . ' min' : '') . ' · ' . $sev . ' · notify ' . $notify, 'dev_alert_rule', $nid, $label, [], 'create');
            }
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Rule saved' : 'Could not save the rule', 'reload' => (bool) $ok]);
        break;

    case 'rule_toggle':
        $r = sl_tableExists('cdb_dev_alert_rules') ? sl_row('SELECT label, enabled FROM cdb_dev_alert_rules WHERE id = :id', [':id' => $id]) : null;
        if (!$r) {
            sl_json(['success' => false, 'message' => 'Rule not found'], 404);
        }
        $on = (int) !in_array($in('value'), ['', '0', 'false', 'off'], true);
        $ok = cdp_devX('UPDATE cdb_dev_alert_rules SET enabled = :e, updated_at = NOW() WHERE id = :id', [':e' => $on, ':id' => $id]);
        if ($ok) {
            $log('rule_toggle', 'Switched alert rule', 'Rule "' . $r->label . '" ' . ($on ? 'on' : 'off'), 'dev_alert_rule', $id, (string) $r->label, ['enabled' => ['from' => (int) $r->enabled, 'to' => $on]]);
        }
        sl_json(['success' => (bool) $ok, 'message' => $r->label . ($on ? ' on' : ' off')]);
        break;

    case 'rule_delete':
        $r = sl_tableExists('cdb_dev_alert_rules') ? sl_row('SELECT * FROM cdb_dev_alert_rules WHERE id = :id', [':id' => $id]) : null;
        if (!$r) {
            sl_json(['success' => false, 'message' => 'Rule not found'], 404);
        }
        $ok = cdp_devX('DELETE FROM cdb_dev_alert_rules WHERE id = :id', [':id' => $id]);
        if ($ok) {
            // its open alert has no rule any more: close it
            cdp_devX("UPDATE cdb_dev_alerts SET status = 'resolved', resolved_at = :n, detail = CONCAT(COALESCE(detail,''), ' · rule deleted') WHERE alert_key = :k AND status <> 'resolved'", [':n' => cdp_devNow(), ':k' => 'rule:' . $r->rule_key]);
            $log('rule_delete', 'Deleted alert rule', 'Rule "' . $r->label . '" (' . $r->metric . ' ' . $r->comparator . ' ' . $fmt($r->threshold) . ') deleted', 'dev_alert_rule', $id, (string) $r->label, [], 'delete');
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Rule deleted' : 'Could not delete', 'reload' => (bool) $ok]);
        break;

    case 'notify_save':
        $extra = [];
        $bad = [];
        foreach (preg_split('/[\s,;]+/', $in('extra'), -1, PREG_SPLIT_NO_EMPTY) as $e) {
            if (filter_var($e, FILTER_VALIDATE_EMAIL) && mb_strlen($e) <= 120) {
                $extra[] = strtolower($e);
            } else {
                $bad[] = $e;
            }
        }
        if ($bad) {
            sl_json(['success' => false, 'message' => 'Not an e-mail address: ' . mb_substr(implode(', ', array_slice($bad, 0, 3)), 0, 120), 'errors' => ['extra' => 'Check the addresses']], 422);
        }
        if (count($extra) > 10) {
            sl_json(['success' => false, 'message' => 'At most 10 extra addresses', 'errors' => ['extra' => 'At most 10']], 422);
        }
        $old = sl_dinfNotifySettings();
        $new = ['on' => (int) ($in('on') !== '' && $in('on') !== '0'), 'min' => $in('min') === 'critical' ? 'critical' : 'warning', 'builtin' => (int) ($in('builtin') !== '' && $in('builtin') !== '0'), 'extra' => array_values(array_unique($extra))];
        cdp_devStateSet('alert_notify', $new);
        $log('alert_notify', 'Changed alert e-mail settings', 'Alert e-mails ' . ($new['on'] ? 'on' : 'off') . ' · minimum ' . $new['min'] . ' · built-in checks ' . ($new['builtin'] ? 'included' : 'left out') . ' · ' . count($new['extra']) . ' extra address(es)', 'dev_alert_rule', null, 'notifications',
            ['on' => ['from' => (int) $old['on'], 'to' => $new['on']], 'min' => ['from' => $old['min'], 'to' => $new['min']], 'extra' => ['from' => count($old['extra']), 'to' => count($new['extra'])]]);
        sl_json(['success' => true, 'message' => 'Notification settings saved', 'reload' => true]);
        break;

    case 'notify_test':
        $to = sl_dinfRecipients();
        if (!$to) {
            sl_json(['success' => false, 'message' => 'No developer has an e-mail address and no extra address is set'], 422);
        }
        $sent = 0;
        $fail = [];
        foreach (array_slice($to, 0, 10) as $r) {
            $x = sl_dinfSendMail($r['email'], 'Test: developer console alerts · ' . (string) gethostname(), "This is a test from the Alerts page of the developer console on " . (string) gethostname() . ".\nSent by " . $actorName . ' at ' . date('Y-m-d H:i:s') . ".\n\nNew alerts will reach this address while alert e-mails are switched on.");
            if (!empty($x['success'])) {
                $sent++;
            } else {
                $fail[] = (string) ($x['message'] ?? 'failed');
            }
        }
        $log('alert_notify_test', 'Sent test alert e-mail', 'Test alert e-mail: ' . $sent . ' sent, ' . count($fail) . ' failed', 'dev_alert_rule', null, 'notifications');
        sl_json(['success' => $sent > 0, 'message' => $sent . ' of ' . min(10, count($to)) . ' test e-mail(s) sent' . ($fail ? ' · ' . mb_substr($fail[0], 0, 140) : '')], $sent > 0 ? 200 : 502);
        break;
}
