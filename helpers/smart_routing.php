<?php
/**
 * Smart Routing (smart_routing.php): the dispatcher's day view on top of the route planner engine.
 *
 * Everything that plans, assigns or re-orders goes through helpers/route_planner.php (rp_*), so
 * vehicle capacity, the delivery workflow (DeliveryService) and the rider app stay in step. This
 * file adds what the engine does not have: one route for one chosen rider from chosen deliveries,
 * a hand-made stop order, sending the route to the rider, staff notes on a stop, proof of
 * delivery for the stop dialog, and starred riders.
 * Tables: migrations/2026_11_28_smart_routing.sql (also installed by sr_ensureSchema()).
 */
require_once __DIR__ . '/route_planner.php';

const SR_TABLES = ['cdb_sr_route_sends', 'cdb_sr_stop_notes', 'cdb_sr_favourites'];
const SR_COLORS = ['#2563eb', '#d97706', '#7c3aed', '#059669', '#db2777', '#0891b2', '#ca8a04', '#4f46e5'];

function sr_ensureSchema(): void
{
    static $done = false;
    if ($done) return;
    rp_ensureSchema();
    $have = (int) rp_val('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . rp_in(SR_TABLES) . ')', SR_TABLES);
    if ($have < count(SR_TABLES)) {
        $file = dirname(__DIR__) . '/migrations/2026_11_28_smart_routing.sql';
        $sql = is_file($file) ? (string) file_get_contents($file) : '';
        foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
            $stmt = trim(preg_replace('/^--.*$/m', '', $stmt));
            if (stripos($stmt, 'CREATE TABLE IF NOT EXISTS') === 0) rp_db()->exec($stmt);
        }
    }
    $done = true;
}

/** A valid Y-m-d, else today. */
function sr_date($v): string
{
    $v = (string) $v;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : date('Y-m-d');
}

/** Status shown to staff: [label, pill class, filter group]. */
function sr_statusInfo(string $status, ?string $activity = null): array
{
    switch ($status) {
        case 'onway': return ['On trip', 'onway', 'trip'];
        case 'waiting': return $activity === 'break' ? ['On break', 'onway', 'trip'] : ['At a stop', 'onway', 'trip'];
        case 'loading': return ['Loading', 'sorting', 'trip'];
        case 'assigned': return ['Route planned', 'transit', 'planned'];
        case 'off': return ['Off shift', 'pending', 'off'];
        case 'suspended': return ['Unavailable', 'pending', 'off'];
        default: return ['Available', 'delivered', 'available'];
    }
}

/** Riders of the day with status, vehicle and load (agency-scoped by the engine). */
function sr_riders(string $date, int $agency): array
{
    sr_ensureSchema();
    $out = [];
    $i = 0;
    foreach (rp_ridersForPlanning($date, $agency) as $id => $p) {
        $act = in_array($p['status'], ['waiting', 'onway', 'loading'], true) ? rp__openActivity((int) $id) : null;
        [$label, $cls, $group] = sr_statusInfo((string) $p['status'], $act['state'] ?? null);
        $p['activity'] = $act['state'] ?? null;
        $p['st_label'] = $label; $p['st_cls'] = $cls; $p['st_group'] = $group;
        $p['color'] = SR_COLORS[$i++ % count(SR_COLORS)];
        $out[(int) $id] = $p;
    }
    return $out;
}

/** Open and finished routes of the day for these riders (newest first per rider), as rp_route views. */
function sr_dayRoutes(string $date, array $riders, int $actor): array
{
    if (!$riders) return [];
    $ids = array_keys($riders);
    $rows = rp_all("SELECT id, driver_id, status FROM cdb_rp_routes WHERE route_date = ? AND driver_id IN (" . rp_in($ids) . ") ORDER BY FIELD(status, 'onroute', 'loading', 'planned', 'done'), id DESC", array_merge([$date], $ids));
    $out = [];
    foreach ($rows as $r) {
        try {
            $view = rp_route((int) $r['id'], $actor, 'manager');
        } catch (Throwable $e) {
            error_log('smart routing: route ' . (int) $r['id'] . ': ' . $e->getMessage());
            continue;
        }
        $live = array_values(array_filter($view['stops'], fn($s) => $s['status'] !== 'removed'));
        if (!$live) continue;
        $view['live_stops'] = $live;
        $out[(int) $r['id']] = $view;
    }
    return $out;
}

/** The route (any day) with a manager scope check; throws DomainException when not allowed. */
function sr_route(int $routeId, int $actor): array
{
    sr_ensureSchema();
    rp__routeFor($routeId, $actor, 'manager');
    $view = rp_route($routeId, $actor, 'manager');
    $view['live_stops'] = array_values(array_filter($view['stops'], fn($s) => $s['status'] !== 'removed'));
    return $view;
}

/** Pending, customer-approved requests by id as planning units (agency-scoped). */
function sr__units(array $requestIds, int $agency, array $states = ['pending']): array
{
    $requestIds = array_values(array_unique(array_filter(array_map('intval', $requestIds))));
    if (!$requestIds) return [];
    $reqs = rp_all('SELECT r.*, z.name AS zone_name FROM cdb_delivery_requests r LEFT JOIN cdb_delivery_zones z ON z.id = r.zone_id WHERE r.id IN (' . rp_in($requestIds) . ') AND r.approved_at IS NOT NULL AND r.state IN (' . rp_in($states) . ')' . ($agency ? ' AND r.agency_id = ?' : ''),
        array_merge($requestIds, $states, $agency ? [$agency] : []));
    $res = rp__unitsFor($reqs);
    $out = [];
    foreach (array_merge($res['units'], $res['unplaceable']) as $u) $out[(int) $u['id']] = $u;
    return $out;
}

/**
 * Can these units go with this rider? null = yes; ['hard' => message] = no;
 * ['soft' => message] = only when the dispatcher confirms (over capacity up to RP_FORCE_MAX_PCT,
 * rider off shift or already on the road).
 */
function sr_assignCheck(array $rider, array $units, string $date): ?array
{
    $v = $rider['vehicle'];
    if ($rider['status'] === 'suspended') return ['hard' => $rider['name'] . ' is suspended and cannot take deliveries.'];
    if (empty($v['active'])) return ['hard' => $rider['name'] . "'s vehicle is out of service."];
    $sum = ['kg' => 0.0, 'l' => 0.0, 'n' => 0];
    foreach ($units as $u) {
        if (($u['lat'] ?? null) === null) return ['hard' => 'Delivery #' . $u['id'] . ' has no map location. Fix the address first.'];
        $why = rp_vehicleEligible($v + ['agency_id' => $rider['agency_id']], $u);
        if ($why && !preg_match('/^too (heavy|bulky|many)/', $why)) return ['hard' => 'Delivery #' . $u['id'] . ' cannot go with ' . $rider['name'] . ': ' . $why . '.'];
        $sum['kg'] += (float) $u['kg']; $sum['l'] += (float) $u['l']; $sum['n'] += (int) $u['n'];
    }
    $notes = [];
    $over = rp__overCapacity($rider['used'], $sum, $v);
    if ($over) {
        $text = rp__overText($over);
        if (max(array_column($over, 'pct')) > RP_FORCE_MAX_PCT) return ['hard' => 'This is too much for ' . $rider['name'] . "'s " . $v['vehicle_type'] . ' (' . $text . '). Untick some deliveries.', 'over' => $over];
        $notes[] = 'this puts the ' . $v['vehicle_type'] . ' over capacity (' . $text . ')';
    }
    if ($rider['status'] === 'off') $notes[] = $rider['name'] . ' is off shift';
    if (in_array($rider['status'], ['onway', 'waiting'], true) && $date === date('Y-m-d')) $notes[] = $rider['name'] . ' is already on the road, so the new stops are loaded on the next pass at the office';
    return $notes ? ['soft' => ucfirst(implode('; ', $notes)) . '.', 'over' => $over] : null;
}

/** Assign requests to a rider through the delivery workflow, then write (or extend) the rider's route. */
function sr__assign(array $rider, string $date, array $start, array $requestIds, int $actor, int $agency): array
{
    $svc = rp_service();
    $ok = []; $errors = [];
    foreach ($requestIds as $reqId) {
        try {
            $cur = rp_one('SELECT state, driver_id FROM cdb_delivery_requests WHERE id = ?', [$reqId]);
            if ($cur && $cur['state'] === 'assigned' && (int) $cur['driver_id'] === (int) $rider['id']) { $ok[] = $reqId; continue; }
            $svc->transition($reqId, $actor, 'manager', $agency, 'assign', ['driver_id' => (int) $rider['id'], 'note' => 'Assigned in Smart Routing']);
            $ok[] = $reqId;
        } catch (Throwable $e) {
            $errors[] = ['request_id' => $reqId, 'message' => $e instanceof DomainException ? $e->getMessage() : 'Could not assign'];
            if (!$e instanceof DomainException) error_log('smart routing assign failed: ' . $e->getMessage());
        }
    }
    $routeId = 0;
    if ($ok) {
        // A request taken from another rider leaves that rider's route.
        rp_reconcileStops();
        // The engine keeps one route per rider, day and plan: a small confirmed plan row records
        // who built this route by hand.
        rp_q('INSERT INTO cdb_rp_plans (plan_date, agency_id, status, start_json, params_json, proposal_json, created_by, confirmed_by, created_at, confirmed_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$date, $agency, 'confirmed', json_encode($start), json_encode(['date' => $date, 'source' => 'smart_routing', 'start_id' => $start['id'] ?? '']), json_encode(['riders' => [(int) $rider['id'] => ['units' => $ok]], 'unallocated' => []]), $actor, $actor, rp_now(), rp_now()]);
        $planId = (int) rp_db()->lastInsertId();
        $routeId = rp__writeRoute($planId, (int) $rider['id'], $date, $start, $ok, false, $actor);
    }
    return ['route_id' => $routeId, 'assigned' => count($ok), 'errors' => $errors];
}

/**
 * One optimised route for one rider from the chosen deliveries.
 * $in: rider_id, date, start_id, request_ids[], max_stops, max_pieces, max_amount (0 = no cap).
 * Returns ['blocked' => true, 'can_force', 'message'] when the vehicle check needs a decision.
 */
function sr_createRoute(int $actor, int $agency, array $in, bool $force = false): array
{
    sr_ensureSchema();
    $date = sr_date($in['date'] ?? '');
    if ($date < date('Y-m-d')) throw new DomainException('Routes can only be created for today or a later day.');
    $riders = sr_riders($date, $agency);
    $rider = $riders[(int) ($in['rider_id'] ?? 0)] ?? null;
    if (!$rider) throw new DomainException('Choose a rider.');
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($in['request_ids'] ?? [])))));
    if (!$ids) throw new DomainException('Tick at least one delivery.');
    $units = sr__units($ids, $agency, ['pending', 'assigned', 'failed']);
    $missing = array_diff($ids, array_keys($units));
    foreach ($units as $k => $u) if ($u['state'] === 'assigned' && (int) $u['driver_id'] === (int) $rider['id']) unset($units[$k]);
    if ($missing) throw new DomainException('Delivery #' . reset($missing) . ' is no longer waiting for a rider. Refresh the page.');
    if (!$units) throw new DomainException('These deliveries are already on this rider\'s route.');
    $maxStops = (int) ($in['max_stops'] ?? 0); $maxPieces = (int) ($in['max_pieces'] ?? 0); $maxAmount = (float) ($in['max_amount'] ?? 0);
    $pieces = array_sum(array_map(fn($u) => (int) $u['n'], $units));
    $cents = array_sum(array_map(fn($u) => (int) $u['amount_cents'], $units));
    if ($maxStops > 0 && count($units) > $maxStops) throw new DomainException(count($units) . ' deliveries are ticked but the route is limited to ' . $maxStops . ' stops. Untick some or raise the limit.');
    if ($maxPieces > 0 && $pieces > $maxPieces) throw new DomainException($pieces . ' pieces are ticked but the route is limited to ' . $maxPieces . '. Untick some or raise the limit.');
    if ($maxAmount > 0 && function_exists('cdp_financeEnabled') && cdp_financeEnabled() && $cents > (int) round($maxAmount * 100)) throw new DomainException('The ticked deliveries add up to ' . number_format($cents / 100, 2) . ' to collect, above the limit of ' . number_format($maxAmount, 2) . '.');
    $check = sr_assignCheck($rider, $units, $date);
    if ($check && isset($check['hard'])) return ['blocked' => true, 'can_force' => false, 'message' => $check['hard']];
    if ($check && !$force) return ['blocked' => true, 'can_force' => true, 'message' => $check['soft']];
    $start = rp_startPlace((string) ($in['start_id'] ?? '') ?: null);
    return sr__assign($rider, $date, $start, array_keys($units), $actor, $agency);
}

/** Give every open stop of a not-yet-started route to another rider. */
function sr_changeRider(int $routeId, int $toRider, int $actor, int $agency, bool $force = false): array
{
    $route = rp__routeFor($routeId, $actor, 'manager');
    if ($route['status'] !== 'planned') throw new DomainException('This route has already started. Remove single stops instead, or ask the rider to hand parcels back.');
    $date = (string) $route['route_date'];
    if ((int) $route['driver_id'] === $toRider) throw new DomainException('This rider already has the route.');
    $riders = sr_riders($date, $agency);
    $rider = $riders[$toRider] ?? null;
    if (!$rider) throw new DomainException('Choose a rider.');
    $stops = rp_all("SELECT id, request_id FROM cdb_rp_stops WHERE route_id = ? AND status = 'pending' ORDER BY seq", [$routeId]);
    if (!$stops) throw new DomainException('This route has no open stops to hand over.');
    $reqIds = array_map(fn($s) => (int) $s['request_id'], $stops);
    $units = sr__units($reqIds, $agency, ['assigned', 'failed', 'pending']);
    $check = sr_assignCheck($rider, $units, $date);
    if ($check && isset($check['hard'])) return ['blocked' => true, 'can_force' => false, 'message' => $check['hard']];
    if ($check && !$force) return ['blocked' => true, 'can_force' => true, 'message' => $check['soft']];
    foreach ($stops as $s) rp_removeStop($routeId, (int) $s['id'], $actor);
    $start = json_decode((string) $route['start_json'], true) ?: rp_startPlace();
    $res = sr__assign($rider, $date, $start, array_keys($units), $actor, $agency);
    // The old route is empty now: close it so its rider shows as available again.
    if (!(int) rp_val("SELECT COUNT(*) FROM cdb_rp_stops WHERE route_id = ? AND status <> 'removed'", [$routeId])) {
        rp_q("UPDATE cdb_rp_routes SET status = 'done', finished_at = ?, updated_at = ? WHERE id = ? AND status = 'planned'", [rp_now(), rp_now(), $routeId]);
    }
    return $res;
}

/** Keep a hand-made order of the open stops: finished stops stay first, ETAs are worked out again. */
function sr_reorder(int $routeId, array $stopIds, int $actor): array
{
    $route = rp__routeFor($routeId, $actor, 'manager');
    if ($route['status'] === 'done') throw new DomainException('This route is finished.');
    $stopIds = array_values(array_unique(array_map('intval', $stopIds)));
    $pending = array_map('intval', array_column(rp_all("SELECT id FROM cdb_rp_stops WHERE route_id = ? AND status = 'pending' ORDER BY seq, id", [$routeId]), 'id'));
    $a = $stopIds; $b = $pending; sort($a); sort($b);
    if ($a !== $b) throw new DomainException('The stops changed while you were moving them. The list was refreshed.');
    $base = (int) rp_val("SELECT COUNT(*) FROM cdb_rp_stops WHERE route_id = ? AND status NOT IN ('pending','removed')", [$routeId]);
    $db = rp_db();
    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        foreach ($stopIds as $i => $sid) rp_q('UPDATE cdb_rp_stops SET seq = ? WHERE id = ? AND route_id = ?', [$base + $i + 1, $sid, $routeId]);
        if ($own) $db->commit();
    } catch (Throwable $e) { if ($own && $db->inTransaction()) $db->rollBack(); throw $e; }
    rp__recompute($routeId, false);
    return sr_route($routeId, $actor);
}

/* ------------------------------------------------------------------ save / send */

/** Last "saved" and "sent" marks of a route, with the delivery status of the sent messages. */
function sr_routeMarks(int $routeId): array
{
    sr_ensureSchema();
    $out = ['saved' => null, 'sent' => null];
    foreach (['saved', 'sent'] as $kind) {
        $row = rp_one('SELECT s.*, u.fname, u.lname, u.username FROM cdb_sr_route_sends s LEFT JOIN cdb_users u ON u.id = s.actor_id WHERE s.route_id = ? AND s.kind = ? ORDER BY s.id DESC', [$routeId, $kind]);
        if (!$row) continue;
        $row['by'] = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')) ?: (string) ($row['username'] ?? 'Staff');
        $row['channels'] = json_decode((string) $row['channels_json'], true) ?: [];
        if ($kind === 'sent') {
            foreach ($row['channels'] as $i => $c) {
                if (empty($c['id'])) continue;
                // Live status from the message queues, so staff see queued / sent / failed.
                if (($c['queue'] ?? '') === 'comm' && sl_tableExists('cdb_comm_outbox')) {
                    $st = rp_one('SELECT status, last_error, sent_at FROM cdb_comm_outbox WHERE id = ?', [(int) $c['id']]);
                    if ($st) $row['channels'][$i]['status'] = (string) $st['status'];
                } elseif (($c['queue'] ?? '') === 'delivery' && sl_tableExists('cdb_delivery_outbox')) {
                    $st = rp_val('SELECT state FROM cdb_delivery_outbox WHERE id = ?', [(int) $c['id']]);
                    if ($st !== null) $row['channels'][$i]['status'] = $st === 'pending' ? 'queued' : (string) $st;
                }
            }
        }
        $out[$kind] = $row;
    }
    return $out;
}

/** Mark the route as saved (a draft the rider has not been sent yet). */
function sr_saveRoute(int $routeId, int $actor): array
{
    sr_ensureSchema();
    $route = rp__routeFor($routeId, $actor, 'manager');
    $n = (int) rp_val("SELECT COUNT(*) FROM cdb_rp_stops WHERE route_id = ? AND status <> 'removed'", [$routeId]);
    rp_q('INSERT INTO cdb_sr_route_sends (route_id, kind, route_version, stops, channels_json, actor_id, created_at) VALUES (?,?,?,?,?,?,?)', [$routeId, 'saved', (int) $route['version'], $n, null, $actor, rp_now()]);
    return sr_routeMarks($routeId);
}

/** The text a rider gets for the route (plain, short enough for SMS segments to stay few). */
function sr_routeMessage(array $view): string
{
    $open = array_values(array_filter($view['live_stops'] ?? $view['stops'], fn($s) => in_array($s['status'], ['pending', 'arrived'], true)));
    $day = $view['route']['route_date'] === date('Y-m-d') ? 'today' : date('D j M', strtotime($view['route']['route_date']));
    $first = $open[0] ?? null;
    $text = 'Your route for ' . $day . ': ' . count($open) . ' stop' . (count($open) === 1 ? '' : 's') . ', about ' . round($view['route']['total_m'] / 1000, 1) . ' km, starting at ' . ($view['start']['label'] ?? 'the office') . '.';
    if ($first) $text .= ' First stop: ' . trim(($first['contact_name'] ?: 'customer') . ', ' . ($first['area'] ?: mb_strimwidth((string) $first['address'], 0, 40, '...')), ', ') . ($first['eta'] ? ' around ' . date('H:i', strtotime($first['eta'])) : '') . '.';
    return $text . ' Open the rider app to see every stop in order.';
}

/**
 * Send the rider their route: an in-app notification (the delivery message path, which also
 * feeds the e-mail outbox) plus WhatsApp and SMS through the Communication Center queue.
 */
function sr_sendRoute(int $routeId, int $actor): array
{
    sr_ensureSchema();
    $view = sr_route($routeId, $actor);
    if ($view['route']['status'] === 'done') throw new DomainException('This route is finished.');
    $open = array_values(array_filter($view['live_stops'], fn($s) => in_array($s['status'], ['pending', 'arrived'], true)));
    if (!$open) throw new DomainException('There are no open stops to send.');
    $driver = (int) $view['rider']['id'];
    $text = sr_routeMessage($view);
    $link = dl_url('rider_route.php');
    $channels = [];
    $svc = rp_service();
    try {
        $key = 'route:' . $routeId . ':' . bin2hex(random_bytes(5));
        $reqId = (int) $open[0]['request_id'];
        $svc->message($reqId, $key, $driver, $text, 'rider_route.php');
        $id = (int) rp_val('SELECT id FROM cdb_delivery_outbox WHERE event_key = ? ORDER BY id DESC', [$reqId . ':' . $key]);
        $channels[] = ['channel' => 'app', 'label' => 'In-app notification', 'queue' => 'delivery', 'id' => $id, 'status' => 'queued'];
    } catch (Throwable $e) {
        error_log('smart routing send (in-app) failed: ' . $e->getMessage());
        $channels[] = ['channel' => 'app', 'label' => 'In-app notification', 'status' => 'failed', 'note' => 'Could not be saved'];
    }
    $phone = trim((string) ($view['rider']['phone'] ?? ''));
    $commFile = __DIR__ . '/comms.php';
    if (is_file($commFile)) require_once $commFile;
    foreach (['whatsapp' => 'WhatsApp', 'sms' => 'SMS'] as $ch => $label) {
        if ($phone === '') { $channels[] = ['channel' => $ch, 'label' => $label, 'status' => 'skipped', 'note' => 'No phone number on the rider']; continue; }
        if (!function_exists('cdp_commQueueRaw')) { $channels[] = ['channel' => $ch, 'label' => $label, 'status' => 'skipped', 'note' => 'Messaging is not set up']; continue; }
        try {
            $res = cdp_commQueueRaw($phone, $ch, [], ['body' => $text . ' ' . $link, 'subject' => 'Your delivery route', 'event' => 'rider_route', 'kind' => 'transactional', 'name' => (string) $view['rider']['name'],
                'entity_type' => 'rp_route', 'entity_ref' => (string) $routeId, 'send_after' => time(), 'dedupe' => 'rider_route:' . $routeId . ':' . (int) $view['route']['version'] . ':' . date('YmdHis')]);
            if (!empty($res['queued'])) $channels[] = ['channel' => $ch, 'label' => $label, 'queue' => 'comm', 'id' => (int) ($res['ids'][0] ?? 0), 'status' => 'queued'];
            else $channels[] = ['channel' => $ch, 'label' => $label, 'status' => 'skipped', 'note' => (string) (reset($res['skipped']) ?: 'Not queued')];
        } catch (Throwable $e) {
            error_log('smart routing send (' . $ch . ') failed: ' . $e->getMessage());
            $channels[] = ['channel' => $ch, 'label' => $label, 'status' => 'failed', 'note' => 'Could not be queued'];
        }
    }
    rp_q('INSERT INTO cdb_sr_route_sends (route_id, kind, route_version, stops, channels_json, actor_id, created_at) VALUES (?,?,?,?,?,?,?)', [$routeId, 'sent', (int) $view['route']['version'], count($open), json_encode($channels), $actor, rp_now()]);
    return ['channels' => $channels, 'marks' => sr_routeMarks($routeId), 'text' => $text];
}

/* ------------------------------------------------------------------ stop dialog */

function sr_stopDetail(int $stopId, int $actor): array
{
    sr_ensureSchema();
    $row = rp_one('SELECT route_id FROM cdb_rp_stops WHERE id = ?', [$stopId]);
    if (!$row) throw new DomainException('Stop not found');
    $view = sr_route((int) $row['route_id'], $actor);
    $stop = null;
    foreach ($view['stops'] as $s) if ((int) $s['id'] === $stopId) $stop = $s;
    if (!$stop) throw new DomainException('Stop not found');
    $req = rp_one('SELECT * FROM cdb_delivery_requests WHERE id = ?', [(int) $stop['request_id']]) ?? [];
    $cust = $req ? rp_one('SELECT id, fname, lname, username, email, phone FROM cdb_users WHERE id = ?', [(int) $req['customer_id']]) : null;
    $events = [];
    if ($req && sl_tableExists('cdb_delivery_events')) {
        foreach (rp_all('SELECT e.state, e.note, e.created_at, u.fname, u.lname FROM cdb_delivery_events e LEFT JOIN cdb_users u ON u.id = e.actor_id WHERE e.request_id = ? ORDER BY e.id DESC LIMIT 12', [(int) $req['id']]) as $e) {
            $events[] = ['state' => (string) $e['state'], 'note' => (string) $e['note'], 'at' => date('j M H:i', strtotime((string) $e['created_at'])), 'by' => trim(($e['fname'] ?? '') . ' ' . ($e['lname'] ?? ''))];
        }
    }
    $note = rp_one('SELECT n.note, n.updated_at, u.fname, u.lname FROM cdb_sr_stop_notes n LEFT JOIN cdb_users u ON u.id = n.updated_by WHERE n.stop_id = ?', [$stopId]);
    $doneAt = $stop['done_at'] ?: ($req['completed_at'] ?? null);
    // Where the rider's phone was at the hand-over, when a position was recorded within five minutes.
    $at = null;
    if ($doneAt) {
        $ping = rp_one('SELECT lat, lng FROM cdb_rp_pings WHERE driver_id = ? AND recorded_at BETWEEN ? AND ? ORDER BY ABS(TIMESTAMPDIFF(SECOND, recorded_at, ?)) LIMIT 1',
            [(int) $view['rider']['id'], date('Y-m-d H:i:s', strtotime($doneAt) - 300), date('Y-m-d H:i:s', strtotime($doneAt) + 300), $doneAt]);
        if ($ping) $at = ['lat' => (float) $ping['lat'], 'lng' => (float) $ping['lng']];
    }
    $ll = fn(float $a, float $o) => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(sprintf('%.6F,%.6F', $a, $o));
    $finance = function_exists('cdp_financeEnabled') ? cdp_financeEnabled() : true;
    $hasPhoto = $req && !empty($req['proof_photo']) && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', (string) $req['proof_photo']);
    $parcels = [];
    foreach ($stop['parcels'] as $p) {
        $parcels[] = ['tracking' => (string) ($p['tracking'] ?? ''), 'contents' => (string) ($p['contents'] ?? ''), 'kg' => round((float) ($p['kg'] ?? 0), 2), 'handling' => array_values((array) ($p['handling'] ?? [])), 'outcome' => (string) ($p['outcome'] ?? ''), 'load_state' => (string) ($p['load_state'] ?? '')];
    }
    return [
        'stop' => ['id' => $stopId, 'seq' => (int) $stop['seq'], 'status' => (string) $stop['status'], 'address' => (string) $stop['address'], 'area' => (string) $stop['area'],
            'eta' => $stop['eta'] ? date('H:i', strtotime($stop['eta'])) : '', 'time' => $doneAt ? date('H:i', strtotime($doneAt)) : ($stop['eta'] ? date('H:i', strtotime($stop['eta'])) : ''),
            'done_on' => $doneAt ? date('D j M', strtotime($doneAt)) : '', 'late' => !empty($stop['late']),
            'window' => $stop['window_start'] || $stop['window_end'] ? trim(($stop['window_start'] ?: '') . ' to ' . ($stop['window_end'] ?: ''), ' to') : '',
            'fail_reason' => (string) ($stop['fail_reason'] ?? ''), 'instructions' => (string) ($stop['instructions'] ?? ''),
            'map_url' => $ll((float) $stop['lat'], (float) $stop['lng']), 'handover_url' => $at ? $ll($at['lat'], $at['lng']) : ''],
        'route' => ['id' => (int) $view['route']['id'], 'status' => (string) $view['route']['status']],
        'rider' => ['id' => (int) $view['rider']['id'], 'name' => (string) $view['rider']['name'], 'phone' => (string) $view['rider']['phone']],
        'recipient' => ['received_by' => (string) ($req['receiver_name'] ?? ''), 'name' => (string) $stop['contact_name'], 'phone' => (string) $stop['contact_phone'],
            'customer' => $cust ? (trim(($cust['fname'] ?? '') . ' ' . ($cust['lname'] ?? '')) ?: (string) $cust['username']) : '', 'email' => (string) ($cust['email'] ?? ''), 'customer_phone' => (string) ($cust['phone'] ?? '')],
        'order' => ['request_id' => (int) $stop['request_id'], 'state' => (string) ($req['state'] ?? ''), 'module' => (string) $stop['module'], 'pieces' => (int) $stop['n'], 'kg' => (float) $stop['kg'],
            'fee' => $finance && $req ? dl_money((int) $req['amount_cents'], (string) $req['currency']) : '', 'fee_collected' => $finance && $req ? (bool) $req['fee_collected'] : null,
            'parcels' => $parcels, 'url' => 'delivery_dispatch.php', 'events' => $events],
        'proof' => ['photo_url' => $hasPhoto ? 'delivery_proof.php?id=' . (int) $req['id'] : '', 'signature_url' => '', 'signature_note' => 'The rider app confirms a hand-over with the customer\'s pickup code and a photo. It does not collect a signature.'],
        'note' => ['text' => (string) ($note['note'] ?? ''), 'by' => $note ? trim(($note['fname'] ?? '') . ' ' . ($note['lname'] ?? '')) : '', 'at' => $note ? date('j M H:i', strtotime((string) $note['updated_at'])) : ''],
    ];
}

function sr_saveNote(int $stopId, string $text, int $actor): array
{
    sr_ensureSchema();
    $stop = rp_one('SELECT id, route_id, request_id FROM cdb_rp_stops WHERE id = ?', [$stopId]);
    if (!$stop) throw new DomainException('Stop not found');
    rp__routeFor((int) $stop['route_id'], $actor, 'manager');
    $text = trim(preg_replace('/[^\P{C}\n]+/u', '', $text));
    if (mb_strlen($text) > 1000) throw new DomainException('Keep the note under 1,000 characters.');
    rp_q('INSERT INTO cdb_sr_stop_notes (stop_id, route_id, request_id, note, updated_by, updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE note = VALUES(note), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
        [$stopId, (int) $stop['route_id'], (int) $stop['request_id'], $text, $actor, rp_now()]);
    return ['text' => $text, 'at' => date('j M H:i')];
}

/* ------------------------------------------------------------------ favourites */

function sr_favourites(int $userId): array
{
    sr_ensureSchema();
    return array_map('intval', array_column(rp_all('SELECT driver_id FROM cdb_sr_favourites WHERE user_id = ?', [$userId]), 'driver_id'));
}

function sr_setFavourite(int $userId, int $driverId, bool $on, int $agency): bool
{
    sr_ensureSchema();
    if (!isset(rp__riders($agency)[$driverId])) throw new DomainException('Rider not found');
    if ($on) rp_q('INSERT IGNORE INTO cdb_sr_favourites (user_id, driver_id, created_at) VALUES (?,?,?)', [$userId, $driverId, rp_now()]);
    else rp_q('DELETE FROM cdb_sr_favourites WHERE user_id = ? AND driver_id = ?', [$userId, $driverId]);
    return $on;
}
