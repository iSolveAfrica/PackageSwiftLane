<?php
/**
 * Route planner (dispatch): allocation proposal, moves, locks, confirm, route edits, vehicles,
 * parcel details, settings, statistics, timeline and the live fleet state.
 * Manager only: signed in, not a customer or rider, dispatch_riders / assign_drivers.
 * POST for everything; GET allowed only for op=state (live polling). JSON {success, ...}.
 */
require_once __DIR__ . '/../../loader.php';
require_once CDP_ROOT . '/helpers/ajax_guard.php';
require_once CDP_ROOT . '/helpers/route_planner.php';
$user = new User();
$core = new Core();
require_login();
require_permission(['dispatch_riders', 'assign_drivers']);
$user->cdp_getUserPermissions();
if (!dl_manager($user)) sl_json(['success' => false, 'message' => 'Dispatch access required'], 403);

$op = (string) ($_POST['op'] ?? $_GET['op'] ?? '');
$read = ['state', 'hold', 'route', 'stats', 'timeline'];
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'POST' && !($op === 'state' && $method === 'GET')) sl_json(['success' => false, 'message' => 'POST required'], 405);
if (!in_array($op, $read, true)) {
    require_safe_write();
    if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Changes are disabled in demo mode'], 403);
}
$in = $method === 'POST' ? $_POST : $_GET;
$actor = (int) $_SESSION['userid'];
$agency = dl_agency();
$int = fn(string $k) => (int) ($in[$k] ?? 0);
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['date'] ?? '')) ? (string) $in['date'] : date('Y-m-d');
$log = function (string $action, string $summary, int $id = 0) {
    if (function_exists('cdp_activityLog')) cdp_activityLog(['module' => 'last_mile', 'action' => 'route_planner.' . $action, 'action_label' => 'Route planner ' . str_replace('_', ' ', $action), 'verb' => 'update', 'outcome' => 'ok', 'entity_type' => 'route_plan', 'entity_id' => $id, 'summary' => $summary]);
};

try {
    rp_ensureSchema();
    switch ($op) {
        case 'state':
            sl_json(['success' => true] + rp_fleetState($date, $agency));
        case 'hold':
            sl_json(['success' => true] + rp_cargoHold($agency));
        case 'propose':
            $lock = array_map('intval', (array) ($in['lock'] ?? []));
            $res = rp_proposal($actor, ['date' => $date, 'start_id' => (string) ($in['start_id'] ?? ''), 'mode' => (string) ($in['mode'] ?? ''), 'lock' => $lock, 'agency' => $agency]);
            $log('propose', 'Auto-allocated ' . $res['kpis']['allocated'] . ' deliveries to ' . $res['kpis']['riders_used'] . ' riders', $res['plan_id']);
            sl_json(['success' => true, 'message' => 'Proposal ready: ' . $res['kpis']['allocated'] . ' deliveries on ' . $res['kpis']['riders_used'] . ' riders'] + $res);
        case 'move':
            $to = ($in['to'] ?? '') === '' || ($in['to'] ?? '') === 'hold' ? null : (int) $in['to'];
            $res = rp_moveUnit($int('plan_id'), $int('request_id'), $to, $actor, !empty($in['force']));
            // A refused move is an expected answer (not an HTTP error): the page offers what to do next.
            if ($res['blocked'] ?? false) sl_json(['success' => false, 'blocked' => true, 'message' => $res['warning']] + $res);
            sl_json(['success' => true, 'message' => $res['warning'] ?: 'Stop moved'] + $res);
        case 'lock':
            sl_json(['success' => true] + rp_lockRider($int('plan_id'), $int('driver_id'), !empty($in['locked']) && $in['locked'] !== '0', $actor));
        case 'confirm':
            $res = rp_confirmPlan($int('plan_id'), $actor, $agency, !empty($in['ack_over']) && $in['ack_over'] !== '0');
            $log('confirm', 'Assigned ' . $res['assigned'] . ' deliveries' . ($res['errors'] ? ', ' . count($res['errors']) . ' failed' : ''), $int('plan_id'));
            sl_json(['success' => true, 'message' => $res['assigned'] . ' deliveries assigned' . ($res['errors'] ? '; ' . count($res['errors']) . ' could not be assigned' : '')] + $res);
        case 'discard':
            rp_discardPlan($int('plan_id'), $actor);
            sl_json(['success' => true, 'message' => 'Proposal discarded']);
        case 'route':
            rp__routeFor($int('route_id'), $actor, 'manager');
            sl_json(['success' => true] + rp_route($int('route_id'), $actor, 'manager'));
        case 'reoptimise':
            $pos = isset($in['lat'], $in['lng']) && $in['lat'] !== '' ? [(float) $in['lat'], (float) $in['lng']] : null;
            $res = rp_reoptimise($int('route_id'), $actor, 'manager', $pos);
            sl_json(['success' => true, 'message' => 'Route re-optimised'] + $res);
        case 'add_stop':
            $res = rp_addStop($int('route_id'), $int('request_id'), $actor, !empty($in['force']) && $in['force'] !== '0');
            if ($res['blocked'] ?? false) sl_json(['success' => false, 'message' => $res['warning']] + $res);
            $log('add_stop', 'Added delivery #' . $int('request_id') . ' to route #' . $int('route_id'), $int('route_id'));
            sl_json(['success' => true, 'message' => 'Stop added'] + $res);
        case 'remove_stop':
            $res = rp_removeStop($int('route_id'), $int('stop_id'), $actor, !empty($in['cancel']), trim((string) ($in['note'] ?? '')));
            $log('remove_stop', 'Removed stop #' . $int('stop_id') . ' from route #' . $int('route_id'), $int('route_id'));
            sl_json(['success' => true, 'message' => !empty($in['cancel']) ? 'Delivery cancelled' : 'Stop removed; the delivery is back in the hold'] + $res);
        case 'vehicle_save':
            $driver = $int('driver_id');
            if ($agency && (int) rp_val('SELECT agency_id FROM cdb_users WHERE id = ?', [$driver]) !== $agency) throw new DomainException('Rider not found');
            $res = rp_saveVehicle($driver, $in, $actor);
            $log('vehicle_save', 'Vehicle of rider #' . $driver . ' updated', $driver);
            sl_json(['success' => true, 'message' => 'Vehicle saved', 'vehicle' => $res]);
        case 'parcel_meta_save':
            $res = rp_saveParcelMeta((string) ($in['module'] ?? ''), $int('order_id'), $in, $actor);
            sl_json(['success' => true, 'message' => 'Parcel details saved', 'parcel' => $res]);
        case 'settings_save':
            // Start places, speeds and vehicle defaults apply to every agency: head office only.
            if ($agency !== 0 || !$user->cdp_hasPermission(['manage_settings', 'settings', 'manage_freight'])) sl_json(['success' => false, 'message' => 'Only head-office managers can change the route planner settings'], 403);
            $in2 = $in;
            foreach (['start_places', 'speeds', 'vehicle_defaults'] as $k) if (isset($in2[$k]) && is_string($in2[$k])) $in2[$k] = json_decode($in2[$k], true) ?: [];
            $res = rp_saveSettings($in2, $actor);
            $log('settings_save', 'Route planner settings saved (revision ' . $res['revision'] . ')');
            sl_json(['success' => true, 'message' => 'Settings saved', 'settings' => $res]);
        case 'stats':
            $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['from'] ?? '')) ? (string) $in['from'] : date('Y-m-d', strtotime('-6 days'));
            $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['to'] ?? '')) ? (string) $in['to'] : date('Y-m-d');
            if (strtotime($to) - strtotime($from) > 92 * 86400 || $to < $from) throw new DomainException('Choose a period of up to three months');
            $driver = $int('driver_id');
            if ($agency && (int) rp_val('SELECT agency_id FROM cdb_users WHERE id = ?', [$driver]) !== $agency) throw new DomainException('Rider not found');
            sl_json(['success' => true] + rp_riderStats($driver, $from, $to));
        case 'timeline':
            sl_json(['success' => true, 'rows' => rp_timeline($date, $agency), 'date' => $date]);
        default:
            sl_json(['success' => false, 'message' => 'Unknown operation'], 400);
    }
} catch (DomainException $e) {
    // Business refusals (validation, plan changed, no room) are answers, not HTTP errors.
    sl_json(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('route planner ' . $op . ' failed: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
    sl_json(['success' => false, 'message' => 'The route planner could not complete this. Please retry.'], 500);
}
