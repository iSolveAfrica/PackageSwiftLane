<?php
// *************************************************************************
// * Developer dashboard — data. Assigned super admins only.               *
// *                                                                       *
// * GET  action=overview            live snapshot, uptime, open alerts    *
// * GET  action=series range=1h|24h|7d|30d   history for the charts       *
// * GET  action=logs source level q window since limit   log stream       *
// * GET  action=deployments         git history and pipeline checks       *
// * GET  action=alerts status=open|resolved|all                           *
// * POST action=alert_ack id | alert_resolve id                           *
// * GET  action=access              super admins and who is assigned      *
// * POST action=access_set user_id on=1|0                                 *
// *                                                                       *
// * Every number comes from helpers/devops.php, which measures it.        *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/rbac.php');
require_once(__DIR__ . '/../../helpers/devops.php');
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!cdp_devCanUse($user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'The developer dashboard is for assigned super admins.']);
    exit;
}

$action = (string) ($_REQUEST['action'] ?? 'overview');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$send = function (array $d) {
    echo json_encode(['ok' => true] + $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
};

switch ($action) {
    case 'overview':
        $snap = cdp_devCollect(true);
        $alerts = cdp_devAlerts('open', 20);
        $series = cdp_devSeries('24h');
        $send(['snapshot' => $snap, 'availability' => cdp_devAvailability(30), 'alerts' => $alerts, 'series' => $series,
            'logs' => cdp_devLogs(['window' => 60, 'limit' => 25]), 'deploys' => array_slice(cdp_devGitLog(6), 0, 6), 'head' => cdp_devGitHead()]);
        break;

    case 'series':
        $range = in_array($_GET['range'] ?? '', ['1h', '24h', '7d', '30d'], true) ? $_GET['range'] : '24h';
        $send(['range' => $range, 'series' => cdp_devSeries($range)]);
        break;

    case 'logs':
        $f = [
            'source' => in_array($_GET['source'] ?? '', ['activity', 'messages', 'ai', 'php', 'alerts'], true) ? $_GET['source'] : '',
            'level' => in_array($_GET['level'] ?? '', ['INFO', 'WARN', 'ERROR'], true) ? $_GET['level'] : '',
            'q' => mb_substr((string) ($_GET['q'] ?? ''), 0, 120),
            'window' => (int) ($_GET['window'] ?? 60),
            'since' => (string) ($_GET['since'] ?? ''),
            'limit' => (int) ($_GET['limit'] ?? 200),
        ];
        $send(['logs' => cdp_devLogs($f), 'at' => cdp_devNow()]);
        break;

    case 'deployments':
        $snap = cdp_devCollect(false);
        $send(['deployments' => cdp_devDeployments($snap['services'])]);
        break;

    case 'alerts':
        $st = in_array($_GET['status'] ?? '', ['open', 'resolved', 'all'], true) ? $_GET['status'] : 'all';
        $send(['alerts' => cdp_devAlerts($st, 200)]);
        break;

    case 'alert_ack':
    case 'alert_resolve':
        if (!$post) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Use POST.']);
            exit;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $ok = $action === 'alert_ack' ? cdp_devAlertAck($id, $user->uid) : cdp_devAlertResolve($id);
        $send(['done' => $ok, 'alerts' => cdp_devAlerts('all', 200)]);
        break;

    case 'access':
        $send(['users' => cdp_devAccessList(), 'me' => (int) $user->uid]);
        break;

    case 'access_set':
        if (!$post) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Use POST.']);
            exit;
        }
        $r = cdp_devAccessSet((int) ($_POST['user_id'] ?? 0), !empty($_POST['on']), $user->uid);
        if (!$r['ok']) {
            http_response_code(422);
            echo json_encode($r);
            exit;
        }
        $send(['users' => cdp_devAccessList(), 'me' => (int) $user->uid]);
        break;

    case 'users':
        $send(['users' => cdp_devUsers([
            'user_id' => (int) ($_GET['user_id'] ?? 0), 'module' => mb_substr((string) ($_GET['module'] ?? ''), 0, 60),
            'outcome' => in_array($_GET['outcome'] ?? '', ['success', 'failure', 'denied', 'problem'], true) ? $_GET['outcome'] : '',
            'q' => mb_substr((string) ($_GET['q'] ?? ''), 0, 120),
        ]), 'at' => cdp_devNow()]);
        break;

    case 'user':
        $send(['detail' => cdp_devUserDetail((int) ($_GET['id'] ?? 0))]);
        break;

    case 'data':
        $send(['data' => cdp_devData(), 'processes' => cdp_devProcesses(10)]);
        break;

    case 'processes':
        $send(['processes' => cdp_devProcesses(12)]);
        break;

    case 'diag_list':
        $send(['reports' => cdp_devDiagList(), 'ai_ready' => cdp_devAiReady()]);
        break;

    case 'diag_get':
        $g = cdp_devDiagGet((int) ($_GET['id'] ?? 0));
        if (!$g) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Report not found.']);
            exit;
        }
        $send(['id' => (int) $g['row']->id, 'title' => (string) $g['row']->title, 'markdown' => $g['markdown']]);
        break;

    case 'diag_download':
        $g = cdp_devDiagGet((int) ($_GET['id'] ?? 0));
        $evidence = ($_GET['what'] ?? '') === 'evidence';
        $path = $g ? ($evidence ? $g['bundle'] : $g['path']) : null;
        if (!$path || !is_file($path)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Report file not found.';
            exit;
        }
        header('Content-Type: ' . ($evidence ? 'application/json' : 'text/markdown') . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;

    case 'diag_run':
        if (!$post) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Use POST.']);
            exit;
        }
        $me = $user->cdp_getUserData();
        $who = (object) ['uid' => (int) $user->uid, 'fname' => $me->fname ?? '', 'lname' => $me->lname ?? '', 'username' => $me->username ?? ''];
        $scope = array_map('strval', (array) ($_POST['scope'] ?? []));
        $paths = array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) ($_POST['paths'] ?? ''))));
        $r = cdp_devDiagRun($who, (string) ($_POST['question'] ?? ''), $scope, (int) ($_POST['window'] ?? 1440), $paths);
        $send($r + ['reports' => cdp_devDiagList()]);
        break;

    case 'diag_delete':
        if (!$post) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Use POST.']);
            exit;
        }
        cdp_devDiagDelete((int) ($_POST['id'] ?? 0));
        $send(['reports' => cdp_devDiagList()]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
}
