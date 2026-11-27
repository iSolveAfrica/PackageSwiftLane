<?php
// *************************************************************************
// * Outbox and Customer history (comms_outbox.php, comms_customers.php).  *
// *                                                                       *
// * GET  action=detail&ref=<kind>-<id>   one row with everything about it *
// * GET  action=export&<filters>          CSV of the filtered list         *
// * GET  action=customers&q=              customer search                  *
// * POST action=retry|cancel|resend|explain|save_prefs (CSRF checked by   *
// *      require_login).                                                  *
// * Reads: staff (rank 2+) or view_message_logs. Writes: staff (rank 2+). *
// * Every query is bound (helpers/comms_outbox_view.php).                 *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/comms.php');
require_once(__DIR__ . '/../../helpers/comms_triggers.php');
require_once(__DIR__ . '/../../helpers/comms_outbox_view.php');
require_once(__DIR__ . '/../../helpers/message_cost.php');
require_once(__DIR__ . '/../../helpers/sl_view.php');
require_once(__DIR__ . '/../../helpers/after_response.php');
require_once(__DIR__ . '/../../views/sl/data/message_logs.php');

$action = (string) ($_REQUEST['action'] ?? '');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$out = function (array $b, $code = 200) {
    cdp_opsOut($b + ['success' => !empty($b['ok'])], $code);
};

$user->cdp_getUserPermissions();
$rank = cdp_opsRank();
if ($rank < 2 && !$user->cdp_hasPermission('view_message_logs')) {
    $out(['ok' => false, 'message' => 'Staff only.'], 403);
}
$writes = ['retry', 'cancel', 'resend', 'explain', 'save_prefs'];
if (in_array($action, $writes, true)) {
    if (!$isPost) {
        $out(['ok' => false, 'message' => 'Use POST.'], 405);
    }
    if ($rank < 2) {
        $out(['ok' => false, 'message' => 'Only staff can do that.'], 403);
    }
}
if (in_array($action, ['customers', 'save_prefs'], true) && $rank < 2) {
    $out(['ok' => false, 'message' => 'Staff only.'], 403);
}
if (!cdp_commEnsureTables()) {
    $out(['ok' => false, 'message' => 'The communication tables could not be created. The database user needs the CREATE privilege once.'], 500);
}

/** "log-12" -> ['log', 12] */
$ref = function () {
    $r = (string) ($_REQUEST['ref'] ?? '');
    if (!preg_match('/^(log|queue|cs|dlv|in)-(\d{1,18})$/', $r, $m)) {
        return [null, 0];
    }
    return [$m[1], (int) $m[2]];
};
$log = function ($verb, $summary, $kind = '', $id = 0) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'communications', 'verb' => $verb, 'action' => 'comms_outbox.' . ($_REQUEST['action'] ?? ''), 'outcome' => 'ok',
            'entity_type' => $kind !== '' ? 'message_' . $kind : 'message', 'entity_id' => $id ?: null, 'entity_label' => $kind !== '' ? $kind . '-' . $id : '',
            'summary' => mb_substr($summary, 0, 250)]);
    }
};

switch ($action) {

    case 'detail':
        [$kind, $id] = $ref();
        if (!$kind) {
            $out(['ok' => false, 'message' => 'Unknown message.'], 400);
        }
        $d = cdp_commOutboxDetail($kind, $id);
        if (!$d) {
            $out(['ok' => false, 'message' => 'That message is no longer there.'], 404);
        }
        $d['can_retry'] = $d['can_retry'] && $rank >= 2;
        $d['can_cancel'] = $d['can_cancel'] && $rank >= 2;
        $d['can_resend'] = !empty($d['can_resend']) && $rank >= 2;
        $d['can_explain'] = $d['can_explain'] && $rank >= 2;
        $out(['ok' => true, 'row' => $d]);

    case 'retry':
        [$kind, $id] = $ref();
        $r = $kind ? cdp_commOutboxRetry($kind, $id) : ['ok' => false, 'error' => 'Unknown message.'];
        if ($r['ok']) {
            $log('updated', 'Put an Outbox message back in the queue', $kind, $id);
            if (function_exists('cdp_afterResponse')) {
                cdp_afterResponse(function () { cdp_commDeliverDue(20); });
            }
        }
        $out(['ok' => $r['ok'], 'message' => $r['ok'] ? 'Back in the queue. It goes out within a minute.' : $r['error']]);

    case 'cancel':
        [$kind, $id] = $ref();
        $r = $kind ? cdp_commOutboxCancel($kind, $id) : ['ok' => false, 'error' => 'Unknown message.'];
        if ($r['ok']) {
            $log('cancelled', 'Cancelled an Outbox message before it was sent', $kind, $id);
        }
        $out(['ok' => $r['ok'], 'message' => $r['ok'] ? 'Cancelled. It will not be sent.' : $r['error']]);

    case 'resend':
        [$kind, $id] = $ref();
        if ($kind !== 'log') {
            $out(['ok' => false, 'message' => 'Only a logged message can be sent again. Use Retry for queued messages.'], 400);
        }
        $r = cdp_commOutboxResend($id);
        if ($r['ok']) {
            $log('sent', 'Sent a failed or skipped message again from the Outbox', 'log', $id);
        }
        $out(['ok' => $r['ok'], 'message' => $r['ok'] ? 'Queued again. The new attempt appears at the top of the Outbox.' : $r['error'], 'ref' => $r['ok'] && !empty($r['id']) ? 'queue-' . (int) $r['id'] : '']);

    case 'explain':
        [$kind, $id] = $ref();
        $d = $kind ? cdp_commOutboxDetail($kind, $id) : null;
        if (!$d) {
            $out(['ok' => false, 'message' => 'That message is no longer there.'], 404);
        }
        require_once(__DIR__ . '/../../helpers/comms_ai.php');
        $av = cdp_commAiAvailable($user);
        if (empty($av['ok'])) {
            $out(['ok' => false, 'code' => $av['code'], 'setup_url' => $av['setup_url'], 'assistant' => $av['assistant'],
                'message' => $av['message'] !== '' ? $av['message'] : $av['assistant'] . ' is not set up yet.']);
        }
        $question = mb_substr(trim((string) ($_POST['question'] ?? '')), 0, 300);
        $res = cdp_commAiDraft('explain_failure', cdp_commOutboxExplainContext($d), '', $question);
        if (empty($res['ok'])) {
            $out(['ok' => false, 'code' => $res['code'] ?? 'error', 'setup_url' => $res['setup_url'] ?? '', 'assistant' => $res['assistant'] ?? $av['assistant'],
                'message' => (string) ($res['error'] ?? 'No answer came back. Please try again.')]);
        }
        $out(['ok' => true, 'text' => (string) $res['body'], 'assistant' => $res['assistant'] ?? $av['assistant'], 'model' => $res['model'] ?? '']);

    case 'customers':
        $out(['ok' => true, 'rows' => cdp_commOutboxFindCustomers((string) ($_GET['q'] ?? ''), 20)]);

    case 'save_prefs':
        $uid = (int) ($_POST['user_id'] ?? 0);
        if (!cdp_commUser($uid)) {
            $out(['ok' => false, 'message' => 'Customer not found.'], 404);
        }
        $in = [];
        foreach (['whatsapp', 'sms', 'email', 'mkt_whatsapp', 'mkt_sms', 'mkt_email', 'opted_out_all'] as $k) {
            if (isset($_POST[$k])) {
                $in[$k] = (string) $_POST[$k] === '1' ? 1 : 0;
            }
        }
        $src = preg_replace('/[^a-z_]/', '', (string) ($_POST['source'] ?? 'phone'));
        if (!in_array($src, ['phone', 'in_person', 'written', 'chat'], true)) {
            $src = 'phone';
        }
        $ok = cdp_commSavePrefs($uid, $in, 'staff:' . $src);
        if ($ok) {
            $log('updated', 'Changed the message preferences of customer #' . $uid, 'prefs', $uid);
        }
        $out(['ok' => $ok, 'message' => $ok ? 'Preferences saved' : 'Not saved. Please try again.', 'prefs' => cdp_commPrefs($uid)]);

    case 'export':
        $f = cdp_commOutboxFilters($_GET);
        $feed = cdp_commOutboxFeed($f, 1, 10000);
        $log('exported', 'Exported ' . count($feed['rows']) . ' Outbox rows');
        $cur = cdp_notifyCostCurrency();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="outbox-' . date('Y-m-d-His') . '.csv"');
        header('Cache-Control: no-store');
        $fh = fopen('php://output', 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        $cell = function ($v) {
            $v = (string) $v;
            // Spreadsheet formula injection: a leading = + - @ is shown as text.
            return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
        };
        fputcsv($fh, ['Time', 'Reference', 'Channel', 'Status', 'Origin', 'What', 'Customer', 'Customer id', 'To', 'Subject', 'Message', 'Attempts', 'Result',
            'Provider', 'Campaign', 'Record', 'Sent by', 'Cost' . ($cur !== '' ? ' (' . $cur . ')' : ''), 'Opens', 'Clicks']);
        foreach ($feed['rows'] as $r) {
            fputcsv($fh, array_map($cell, [$r['at'], $r['ref'], $r['channel_label'], $r['status_label'], $r['origin_label'], $r['what'], $r['name'], $r['uid'] ?: '',
                $r['to'], $r['subject'], $r['excerpt'], $r['attempts'], $r['detail'], $r['provider'], $r['campaign'], $r['record'], $r['by'],
                $r['cost'] === null ? '' : $r['cost'], $r['opens'] === null ? '' : $r['opens'], $r['clicks'] === null ? '' : $r['clicks']]));
        }
        fclose($fh);
        exit;

    default:
        $out(['ok' => false, 'message' => 'Unknown action.'], 400);
}
