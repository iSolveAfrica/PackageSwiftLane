<?php
/**
 * Smart Routing (dispatch): create a rider's route, auto-allocate, edit a route (add / remove /
 * re-order / re-optimise / change rider), save, send to the rider, stop details and notes,
 * starred riders and vehicles.
 * Manager only: signed in, not a customer or rider, dispatch_riders / assign_drivers.
 * POST only (CSRF through helpers/ajax_guard.php). JSON {success, message, ...}.
 */
require_once __DIR__ . '/../../loader.php';
require_once CDP_ROOT . '/helpers/ajax_guard.php';
require_once CDP_ROOT . '/helpers/smart_routing.php';
$user = new User();
$core = new Core();
require_login();
require_permission(['dispatch_riders', 'assign_drivers']);
$user->cdp_getUserPermissions();
if (!dl_manager($user)) sl_json(['success' => false, 'message' => 'Dispatch access required'], 403);

$op = (string) ($_POST['op'] ?? '');
$read = ['stop', 'route'];
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (!in_array($op, $read, true)) {
    require_safe_write();
    if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Changes are disabled in demo mode'], 403);
}
$in = $_POST;
$actor = (int) $_SESSION['userid'];
$agency = dl_agency();
$int = fn(string $k) => (int) ($in[$k] ?? 0);
$yes = fn(string $k) => !empty($in[$k]) && $in[$k] !== '0';
$date = sr_date($in['date'] ?? '');
$log = function (string $action, string $summary, int $id = 0) {
    if (function_exists('cdp_activityLog')) cdp_activityLog(['module' => 'last_mile', 'action' => 'smart_routing.' . $action, 'action_label' => 'Smart Routing ' . str_replace('_', ' ', $action), 'verb' => 'update', 'outcome' => 'ok', 'entity_type' => 'route', 'entity_id' => $id, 'summary' => $summary]);
};
$blocked = function (array $res) {
    // A refused step is an answer, not an error: the page offers "assign anyway" when allowed.
    if (!empty($res['blocked'])) sl_json(['success' => false, 'blocked' => true, 'can_force' => !empty($res['can_force']), 'message' => (string) ($res['message'] ?? $res['warning'] ?? 'This cannot be done')]);
};

try {
    sr_ensureSchema();
    switch ($op) {
        case 'create_route':
            $res = sr_createRoute($actor, $agency, ['rider_id' => $int('rider_id'), 'date' => $date, 'start_id' => (string) ($in['start_id'] ?? ''), 'request_ids' => (array) ($in['request_ids'] ?? []),
                'max_stops' => $int('max_stops'), 'max_pieces' => $int('max_pieces'), 'max_amount' => (float) ($in['max_amount'] ?? 0)], $yes('force'));
            $blocked($res);
            if (!$res['route_id']) sl_json(['success' => false, 'message' => $res['errors'][0]['message'] ?? 'No delivery could be assigned', 'errors' => $res['errors']]);
            $log('create_route', 'Route #' . $res['route_id'] . ' with ' . $res['assigned'] . ' deliveries for rider #' . $int('rider_id'), $res['route_id']);
            sl_json(['success' => true, 'message' => 'Route ready: ' . $res['assigned'] . ' stop' . ($res['assigned'] === 1 ? '' : 's') . ' in the best order' . ($res['errors'] ? '. ' . count($res['errors']) . ' could not be assigned' : '')] + $res);
        case 'auto_propose':
            if ($date < date('Y-m-d')) throw new DomainException('Routes can only be created for today or a later day.');
            $res = rp_proposal($actor, ['date' => $date, 'start_id' => (string) ($in['start_id'] ?? ''), 'agency' => $agency]);
            $rows = [];
            foreach ($res['riders'] as $r) {
                $n = count($r['units']) - count($r['fixed'] ?? []);
                if ($n <= 0) continue;
                $rows[] = ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'stops' => $n, 'km' => round((int) ($r['route']['total_m'] ?? 0) / 1000, 1), 'fill' => (int) round((float) ($r['fill']['max'] ?? 0)), 'vehicle' => (string) ($r['vehicle']['vehicle_type'] ?? '')];
            }
            sl_json(['success' => true, 'plan_id' => $res['plan_id'], 'riders' => $rows, 'left' => array_map(fn($u) => ['id' => (int) $u['id'], 'reason' => (string) ($u['reason'] ?? '')], $res['unallocated']), 'kpis' => $res['kpis'],
                'message' => $rows ? 'Proposal ready' : 'Nothing could be shared out: no rider is free or no delivery fits.']);
        case 'auto_confirm':
            $res = rp_confirmPlan($int('plan_id'), $actor, $agency, $yes('ack_over'));
            $log('auto_allocate', 'Auto-allocated ' . $res['assigned'] . ' deliveries on ' . count($res['routes']) . ' routes', $int('plan_id'));
            sl_json(['success' => true, 'message' => $res['assigned'] . ' deliver' . ($res['assigned'] === 1 ? 'y' : 'ies') . ' assigned on ' . count($res['routes']) . ' route' . (count($res['routes']) === 1 ? '' : 's') . ($res['errors'] ? '; ' . count($res['errors']) . ' could not be assigned' : '')] + $res);
        case 'auto_discard':
            rp_discardPlan($int('plan_id'), $actor);
            sl_json(['success' => true, 'message' => 'Proposal discarded']);
        case 'route':
            sl_json(['success' => true] + sr_route($int('route_id'), $actor));
        case 'add_stop':
            $res = rp_addStop($int('route_id'), $int('request_id'), $actor, $yes('force'));
            $blocked($res);
            $log('add_stop', 'Added delivery #' . $int('request_id') . ' to route #' . $int('route_id'), $int('route_id'));
            sl_json(['success' => true, 'message' => 'Stop added and the order worked out again']);
        case 'remove_stop':
            rp_removeStop($int('route_id'), $int('stop_id'), $actor);
            $log('remove_stop', 'Removed stop #' . $int('stop_id') . ' from route #' . $int('route_id'), $int('route_id'));
            sl_json(['success' => true, 'message' => 'Stop removed. The delivery is waiting for a rider again.']);
        case 'reorder':
            sr_reorder($int('route_id'), (array) ($in['stop_ids'] ?? []), $actor);
            sl_json(['success' => true, 'message' => 'Order saved and arrival times updated']);
        case 'reoptimise':
            $res = rp_reoptimise($int('route_id'), $actor, 'manager');
            sl_json(['success' => true, 'message' => !empty($res['improved_pct']) ? 'Order optimised: ' . round((float) $res['improved_pct']) . '% shorter' : 'Order optimised']);
        case 'change_rider':
            $res = sr_changeRider($int('route_id'), $int('rider_id'), $actor, $agency, $yes('force'));
            $blocked($res);
            $log('change_rider', 'Route #' . $int('route_id') . ' handed to rider #' . $int('rider_id'), (int) $res['route_id']);
            sl_json(['success' => true, 'message' => 'Route handed over'] + $res);
        case 'save_route':
            sl_json(['success' => true, 'message' => 'Route saved. The rider has not been sent the route yet.', 'marks' => sr_saveRoute($int('route_id'), $actor)]);
        case 'send_route':
            $res = sr_sendRoute($int('route_id'), $actor);
            $log('send_route', 'Route #' . $int('route_id') . ' sent to the rider', $int('route_id'));
            $queued = count(array_filter($res['channels'], fn($c) => $c['status'] === 'queued'));
            sl_json(['success' => $queued > 0, 'message' => $queued ? 'Route sent to the rider (' . implode(', ', array_map(fn($c) => $c['label'], array_filter($res['channels'], fn($c) => $c['status'] === 'queued'))) . ')' : 'The route could not be sent'] + $res);
        case 'stop':
            sl_json(['success' => true] + sr_stopDetail($int('stop_id'), $actor));
        case 'note_save':
            sl_json(['success' => true, 'message' => 'Note saved', 'note' => sr_saveNote($int('stop_id'), (string) ($in['note'] ?? ''), $actor)]);
        case 'favourite':
            sl_json(['success' => true, 'on' => sr_setFavourite($actor, $int('driver_id'), $yes('on'), $agency)]);
        case 'vehicle_save':
            $driver = $int('driver_id');
            if (!isset(rp__riders($agency)[$driver])) throw new DomainException('Choose a rider');
            rp_saveVehicle($driver, $in, $actor);
            $log('vehicle_save', 'Vehicle of rider #' . $driver . ' updated', $driver);
            sl_json(['success' => true, 'message' => 'Vehicle saved']);
        default:
            sl_json(['success' => false, 'message' => 'Unknown operation'], 400);
    }
} catch (DomainException $e) {
    sl_json(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('smart routing ' . $op . ' failed: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
    sl_json(['success' => false, 'message' => 'Smart Routing could not complete this. Please try again.'], 500);
}
