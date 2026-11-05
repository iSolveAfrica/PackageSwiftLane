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

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
}
