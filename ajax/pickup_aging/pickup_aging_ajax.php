<?php
// ============================================================================
// Pickup-aging — list data + the "notify senders" action.
//   action=pending : runs the scan (discover + auto-transitions) and returns the
//                    JSON list of packages awaiting the 2-week notification,
//                    with how many customers they belong to.
//   action=scope   : POST order_ids (JSON) or scope=all -> how many packages and
//                    customers a notify would touch (shown in the confirmation).
//   action=notify  : POST order_ids (JSON) or scope=all, plus confirm_customers
//                    -> message senders + set Pending Collection. Refused unless
//                    confirm_customers equals the number of customers the notify
//                    would message right now, so nothing goes out on a stray click.
// The list lives on the Warehouse Panel, the Packages Control Panel and the
// Pickup Aging page; there is no dashboard pop-up any more.
// Admin / super-admin only.
// ============================================================================

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/pickup_aging.php');
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();

header('Content-Type: application/json; charset=UTF-8');

$user = new User;
if (!$user->cdp_hasPermission('view_pickup_aging')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$userData = $user->cdp_getUserData();
$uid    = (int) ($userData->id ?? ($_SESSION['userid'] ?? 0));
$action = isset($_REQUEST['action']) ? cdp_sanitize($_REQUEST['action']) : 'pending';

// The dashboard scan: refresh the state machine on every check.
cdp_processPickupAging();

if ($action === 'scope' || $action === 'notify') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'POST required']);
        exit;
    }
    if (($_POST['scope'] ?? '') === 'all') {
        $scope = cdp_pickupAgingScope(null);
    } else {
        $raw = $_POST['order_ids'] ?? '[]';
        $ids = is_array($raw) ? $raw : json_decode((string) $raw, true);
        $scope = cdp_pickupAgingScope(is_array($ids) ? $ids : []);
    }

    if ($action === 'scope') {
        echo json_encode(['ok' => true, 'packages' => $scope['packages'], 'customers' => $scope['customers']]);
        exit;
    }

    if (!$scope['packages']) {
        echo json_encode(['ok' => false, 'error' => 'No selected package is due a reminder any more.']);
        exit;
    }
    // The staff member must type back the customer count shown to them. If the
    // list changed since (or the field is missing) nothing is sent.
    $typed = isset($_POST['confirm_customers']) ? trim((string) $_POST['confirm_customers']) : '';
    if ($typed === '' || !ctype_digit($typed) || (int) $typed !== $scope['customers']) {
        http_response_code(409);
        echo json_encode([
            'ok'        => false,
            'error'     => 'Confirmation did not match: this would message ' . $scope['customers'] . ' customer(s). Nothing was sent.',
            'packages'  => $scope['packages'],
            'customers' => $scope['customers'],
        ]);
        exit;
    }

    $res = cdp_pickupAgingNotifySenders($scope['ids'], $uid);
    echo json_encode(array_merge(['ok' => true, 'customers' => $scope['customers']], $res));
    exit;
}

// action = pending (default)
$rows = cdp_pickupAgingPending();
$out  = [];
foreach ($rows as $r) {
    $out[] = [
        'order_id' => (int) $r->order_id,
        'tracking' => $r->order_track,
        'days'     => (int) $r->days_ready,
    ];
}
$scope = cdp_pickupAgingScope(null);
echo json_encode(['ok' => true, 'count' => count($out), 'customers' => $scope['customers'], 'packages' => $out]);
