<?php
/**
 * Smart Routing view model: views/sl/pages/SmartRouting.php.
 * Three views on one page: the day overview (riders + map), one rider's route (timeline + map) and
 * "Riders & vehicles" (vehicle card, routes, statistics). Reads go through helpers/smart_routing.php
 * (sr_*) and the route planner engine (rp_*); writes go through ajax/sl/smart_routing_ajax.php.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/smart_routing.php';
require_once CDP_ROOT . '/helpers/sl_chart_interactions.php';

function srv_initials(string $name): string
{
    $i = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2) as $p) $i .= mb_strtoupper(mb_substr($p, 0, 1));
    return $i ?: '?';
}

function srv_num(float $v, int $dec = 1): string
{
    return rtrim(rtrim(number_format($v, $dec, '.', ','), '0'), '.') ?: '0';
}

function srv_km(float $m): string
{
    return $m >= 1000 ? srv_num($m / 1000, $m >= 100000 ? 0 : 1) . ' km' : (int) round($m) . ' m';
}

function srv_dur(int $s): string
{
    if ($s <= 0) return '0 min';
    $h = intdiv($s, 3600); $m = (int) round(($s % 3600) / 60);
    if ($m === 60) { $h++; $m = 0; }
    return $h ? $h . ' h' . ($m ? ' ' . $m . ' min' : '') : max(1, $m) . ' min';
}

function srv_hm($v): string
{
    $t = $v ? strtotime((string) $v) : 0;
    return $t ? date('H:i', $t) : '';
}

/** Vehicle line and capacity for lists: "Motorbike · GR 1234-24". */
function srv_vehicle(array $v): array
{
    $type = (string) ($v['vehicle_type'] ?? 'motorbike');
    $l = (int) ($v['cargo_l_cm'] ?? 0); $w = (int) ($v['cargo_w_cm'] ?? 0); $h = (int) ($v['cargo_h_cm'] ?? 0);
    return ['type' => $type, 'typeLabel' => ucfirst($type), 'name' => trim((string) ($v['make_model'] ?? '')) ?: ucfirst($type), 'make' => trim((string) ($v['make_model'] ?? '')),
        'plate' => (string) ($v['plate'] ?? ''), 'max_kg' => (float) ($v['max_kg'] ?? 0), 'max_l' => (float) ($v['max_l'] ?? 0), 'max_n' => (int) ($v['max_parcels'] ?? 0),
        'l' => $l, 'w' => $w, 'h' => $h, 'active' => !empty($v['active']), 'saved' => ($v['source'] ?? '') === 'saved',
        'fragile' => !empty($v['can_fragile']), 'cold' => !empty($v['can_cold']), 'hv' => !empty($v['can_high_value']),
        'docs' => array_values(array_merge((array) ($v['docs'] ?? []), array_map(fn($f) => ['name' => (string) ($f['name'] ?: $f['file_type'] ?: 'Document'), 'expires' => null], (array) ($v['files'] ?? []))))];
}

/** The fullest of weight / volume / pieces decides the load bar. */
function srv_load(array $used, array $veh): array
{
    $dims = [['Weight', (float) ($used['kg'] ?? 0), $veh['max_kg'], ' kg'], ['Volume', (float) ($used['l'] ?? 0), $veh['max_l'], ' L'], ['Pieces', (float) ($used['n'] ?? 0), (float) $veh['max_n'], '']];
    $top = ['label' => 'Weight', 'pct' => 0, 'text' => '0 kg'];
    $all = [];
    foreach ($dims as [$label, $u, $max, $unit]) {
        $pct = $max > 0 ? (int) round($u * 100 / $max) : 0;
        $row = ['label' => $label, 'pct' => $pct, 'w' => min(100, $pct), 'text' => srv_num($u) . ' of ' . srv_num((float) $max) . $unit, 'cls' => $pct > 100 ? 'over' : ($pct >= 85 ? 'full' : 'ok')];
        $all[] = $row;
        if ($pct >= $top['pct']) $top = $row;
    }
    return $top + ['w' => 0, 'cls' => 'ok', 'all' => $all];
}

function srv_money(int $cents, string $cur): string
{
    return ($cur !== '' ? $cur . ' ' : '') . number_format($cents / 100, 2);
}

/** Link inside the page that keeps the day. */
function srv_url(array $q): string
{
    return sl_route('SmartRouting', array_filter($q, fn($v) => $v !== null && $v !== '' && $v !== 0));
}

function sl_smartRoutingData($user, $core): array
{
    $actor = (int) ($_SESSION['userid'] ?? 0);
    $agency = dl_agency();
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $date = sr_date($_GET['date'] ?? '');
    $view = (string) ($_GET['view'] ?? '') === 'riders' ? 'riders' : 'overview';
    $finance = function_exists('cdp_financeEnabled') ? (bool) cdp_financeEnabled() : true;
    $notes = [];
    $d = ['ajax' => 'ajax/sl/smart_routing_ajax.php', 'view' => $view, 'date' => $date, 'today' => $today, 'tomorrow' => $tomorrow, 'finance' => $finance, 'notes' => &$notes,
        'isToday' => $date === $today, 'isPast' => $date < $today,
        'dayWord' => $date === $today ? 'Today' : ($date === $tomorrow ? 'Tomorrow' : date('l', strtotime($date))),
        'dateLong' => date('l j F Y', strtotime($date)),
        'pickLabel' => $date !== $today && $date !== $tomorrow ? date('D j M', strtotime($date)) : 'Pick a date',
        'minDate' => date('Y-m-d', strtotime('-30 days')), 'maxDate' => date('Y-m-d', strtotime('+30 days')),
        'urls' => ['today' => srv_url(['view' => $view === 'riders' ? 'riders' : null]), 'tomorrow' => srv_url(['date' => $tomorrow]), 'overview' => srv_url(['date' => $date === $today ? null : $date]),
            'riders' => srv_url(['view' => 'riders']), 'planner' => sl_route('RoutePlanner'), 'self' => sl_route('SmartRouting')],
        'ready' => true, 'riders' => [], 'hold' => [], 'detail' => null, 'rv' => null];
    try {
        sr_ensureSchema();
        $riders = sr_riders($date, $agency);
        $routes = sr_dayRoutes($date, $riders, $actor);
        $hold = rp_cargoHold($agency);
    } catch (Throwable $e) {
        error_log('[smart_routing] ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
        $d['ready'] = false;
        $d['json'] = ['ajax' => $d['ajax'], 'view' => $view, 'date' => $date];
        return $d;
    }
    $settings = rp_settings();
    $d['starts'] = array_map(fn($p) => ['id' => (string) $p['id'], 'label' => (string) $p['label'], 'lat' => (float) $p['lat'], 'lng' => (float) $p['lng']], (array) $settings['start_places']);
    $d['startId'] = (string) $settings['default_start'];
    $d['maxStops'] = (int) ($settings['max_stops'] ?? 25);
    $d['departTime'] = (string) ($settings['depart_time'] ?? '09:00');
    $start = rp_startPlace();

    // One route per rider for the day view: the engine's order already puts the one under way first.
    $routeOf = [];
    foreach ($routes as $rid => $rv) if (!isset($routeOf[(int) $rv['rider']['id']])) $routeOf[(int) $rv['rider']['id']] = $rid;

    $selRider = (int) ($_GET['rider'] ?? 0);
    $routeId = (int) ($_GET['route'] ?? 0);
    $rows = []; $jsRiders = [];
    $currency = '';
    foreach ($riders as $id => $p) {
        $veh = srv_vehicle($p['vehicle']);
        $rv = isset($routeOf[$id]) ? $routes[$routeOf[$id]] : null;
        $load = srv_load($p['used'], $veh);
        $open = $rv ? count(array_filter($rv['live_stops'], fn($s) => in_array($s['status'], ['pending', 'arrived'], true))) : 0;
        $rows[$id] = ['id' => $id, 'name' => $p['name'], 'initials' => srv_initials($p['name']), 'color' => $p['color'], 'phone' => (string) $p['phone'],
            'vehicle' => $veh, 'sub' => $veh['typeLabel'] . ($veh['plate'] ? ' · ' . $veh['plate'] : '') . ($p['phone'] ? ' · ' . $p['phone'] : ''),
            'status' => $p['status'], 'stLabel' => $p['st_label'], 'stCls' => $p['st_cls'], 'group' => $p['st_group'], 'load' => $load,
            'routeId' => $rv ? (int) $rv['route']['id'] : 0, 'routeStatus' => $rv['route']['status'] ?? '', 'stops' => $rv ? count($rv['live_stops']) : 0, 'open' => $open,
            'done' => $rv ? (int) $rv['progress']['done'] : 0, 'sel' => $selRider === $id,
            'routeUrl' => $rv ? srv_url(['date' => $date === $today ? null : $date, 'route' => (int) $rv['route']['id']]) : '',
            'viewUrl' => srv_url(['view' => 'riders', 'rider' => $id]), 'zone' => (string) $p['zone_name']];
        $jsRiders[] = ['id' => $id, 'name' => $p['name'], 'color' => $p['color'], 'status' => $p['status'], 'stLabel' => $p['st_label'], 'routeId' => $rows[$id]['routeId'], 'routeUrl' => $rows[$id]['routeUrl'],
            'cap' => ['kg' => $veh['max_kg'], 'l' => $veh['max_l'], 'n' => $veh['max_n']], 'used' => $p['used'], 'vehicle' => $veh['typeLabel']];
    }
    $d['riders'] = $rows;
    $d['counts'] = ['all' => count($rows)];
    foreach ($rows as $r) $d['counts'][$r['group']] = ($d['counts'][$r['group']] ?? 0) + 1;

    // Deliveries waiting for a rider (the modal's checklist and the map's grey pins).
    $holdRows = []; $mapUn = [];
    foreach ($hold['units'] as $u) {
        $currency = $currency ?: (string) $u['currency'];
        $holdRows[] = ['id' => (int) $u['id'], 'name' => (string) $u['contact_name'], 'area' => (string) $u['area'], 'address' => (string) $u['address'], 'n' => (int) $u['n'], 'kg' => round((float) $u['kg'], 1), 'l' => round((float) $u['l'], 1),
            'cents' => (int) $u['amount_cents'], 'money' => $finance ? srv_money((int) $u['amount_cents'], (string) $u['currency']) : '', 'handling' => (array) $u['handling'], 'with' => '', 'withId' => 0,
            'window' => $u['window_start'] || $u['window_end'] ? trim(($u['window_start'] ?: '') . '-' . ($u['window_end'] ?: ''), '-') : '', 'priority' => (int) $u['priority']];
        $mapUn[] = ['id' => 'u' . (int) $u['id'], 'request_id' => (int) $u['id'], 'lat' => (float) $u['lat'], 'lng' => (float) $u['lng'], 'label' => (string) $u['contact_name'] . ' · ' . (string) $u['area'], 'status' => 'planned'];
    }
    // Deliveries already on a rider's not-started route: shown under "View all", ticking one moves it.
    $withRows = [];
    $mapRoutes = [];
    foreach ($routes as $rid => $rv) {
        $driver = (int) $rv['rider']['id'];
        $color = $riders[$driver]['color'] ?? '#64748b';
        $stops = [];
        foreach ($rv['live_stops'] as $s) {
            $stops[] = ['id' => (int) $s['id'], 'seq' => (int) $s['seq'], 'lat' => (float) $s['lat'], 'lng' => (float) $s['lng'], 'label' => (string) $s['label'], 'status' => (string) $s['status'], 'eta' => $s['eta'], 'late' => !empty($s['late'])];
            if ($s['status'] === 'pending' && ($s['request_state'] ?? '') === 'assigned' && $rv['route']['status'] === 'planned') {
                $withRows[] = ['id' => (int) $s['request_id'], 'name' => (string) $s['contact_name'], 'area' => (string) $s['area'], 'address' => (string) $s['address'], 'n' => (int) $s['n'], 'kg' => round((float) $s['kg'], 1), 'l' => round((float) $s['l'], 1),
                    'cents' => 0, 'money' => '', 'handling' => (array) $s['handling'], 'with' => (string) $rv['rider']['name'], 'withId' => $driver, 'window' => '', 'priority' => (int) $s['priority']];
            }
        }
        $mapRoutes[] = ['id' => (int) $rid, 'driver_id' => $driver, 'name' => (string) $rv['rider']['name'], 'color' => $color, 'status' => (string) $rv['route']['status'], 'stops' => $stops, 'path' => $rv['map']['path'] ?? null,
            'pos' => $rv['map']['rider_pos'] ?? null, 'depot' => $rv['map']['depot'] ?? null];
    }
    if ($withRows) {
        // Amounts of the assigned requests, for the modal's running total.
        $ids = array_column($withRows, 'id');
        $amt = [];
        foreach (rp_all('SELECT id, amount_cents, currency FROM cdb_delivery_requests WHERE id IN (' . rp_in($ids) . ')', $ids) as $a) $amt[(int) $a['id']] = $a;
        foreach ($withRows as &$w) { $w['cents'] = (int) ($amt[$w['id']]['amount_cents'] ?? 0); $w['money'] = $finance ? srv_money($w['cents'], (string) ($amt[$w['id']]['currency'] ?? $currency)) : ''; }
        unset($w);
    }
    $d['hold'] = $holdRows;
    $d['holdWith'] = $withRows;
    $d['unplaceable'] = array_map(fn($u) => ['id' => (int) $u['id'], 'name' => (string) $u['contact_name'], 'address' => (string) $u['address'], 'reason' => (string) ($u['reason'] ?? 'No map location')], $hold['unplaceable']);
    $d['currency'] = $currency ?: 'GHS';
    $d['kpis'] = ['waiting' => count($holdRows), 'routes' => count($mapRoutes), 'stops' => array_sum(array_map(fn($r) => count($r['stops']), $mapRoutes)),
        'done' => array_sum(array_map(fn($r) => count(array_filter($r['stops'], fn($s) => in_array($s['status'], RP_DONE_STOP, true))), $mapRoutes))];
    if (!$d['isToday'] && !$d['isPast']) $notes[] = 'Planning ahead for ' . $d['dateLong'] . ': deliveries waiting now are offered for this day, riders are sent the route when you press Send, and arrival times assume a ' . $d['departTime'] . ' start.';
    if ($d['isPast']) $notes[] = 'This day is over. You can look at its routes and proof of delivery, but not create or change routes.';

    /* ---------- one rider's route ---------- */
    if ($view === 'overview' && $routeId) {
        try {
            $rv = $routes[$routeId] ?? sr_route($routeId, $actor);
            $d['detail'] = srv_detail($rv, $rows, $riders, $finance, $d);
            $d['view'] = 'route';
            if ($rv['route']['route_date'] !== $date) {
                $d['date'] = $date = (string) $rv['route']['route_date'];
                $d['dayWord'] = $date === $today ? 'Today' : ($date === $tomorrow ? 'Tomorrow' : date('l', strtotime($date)));
                $d['isToday'] = $date === $today; $d['isPast'] = $date < $today;
            }
            $selRider = (int) $rv['rider']['id'];
        } catch (Throwable $e) {
            $notes[] = 'That route could not be opened. It may have been removed.';
        }
    }

    /* ---------- riders & vehicles ---------- */
    if ($view === 'riders') $d['rv'] = srv_ridersView($rows, $riders, $routes, $routeOf, $actor, $agency, $selRider, $finance);

    $d['selRider'] = $selRider;
    $d['json'] = ['ajax' => $d['ajax'], 'view' => $d['view'], 'date' => $date, 'isPast' => $d['isPast'], 'finance' => $finance, 'currency' => $d['currency'], 'selRider' => $selRider,
        'routeId' => $d['detail']['id'] ?? 0, 'tz' => (int) round((new DateTime('now'))->getOffset() / 60), 'riders' => $jsRiders, 'self' => sl_route('SmartRouting'),
        'map' => ['depot' => ['lat' => (float) $start['lat'], 'lng' => (float) $start['lng'], 'label' => (string) $start['label']], 'starts' => $d['starts'], 'unassigned' => $mapUn, 'routes' => $mapRoutes],
        'mini' => $d['rv']['sel']['now']['map'] ?? null];
    return $d;
}

/** Timeline view model of one route. */
function srv_detail(array $rv, array $rows, array $riders, bool $finance, array $d): array
{
    $route = $rv['route'];
    $driver = (int) $rv['rider']['id'];
    $row = $rows[$driver] ?? ['name' => $rv['rider']['name'], 'initials' => srv_initials((string) $rv['rider']['name']), 'color' => '#2563eb', 'sub' => (string) $rv['rider']['phone'], 'stLabel' => '', 'stCls' => 'plain', 'vehicle' => srv_vehicle($rv['vehicle']), 'viewUrl' => srv_url(['view' => 'riders', 'rider' => $driver])];
    $stops = []; $cents = 0; $cur = ''; $lateN = 0; $lastEta = null;
    $reqIds = array_map(fn($s) => (int) $s['request_id'], $rv['live_stops']);
    $amt = [];
    if ($reqIds) foreach (rp_all('SELECT id, amount_cents, currency, fee_collected FROM cdb_delivery_requests WHERE id IN (' . rp_in($reqIds) . ')', $reqIds) as $a) $amt[(int) $a['id']] = $a;
    $notes = [];
    if ($rv['live_stops']) foreach (rp_all('SELECT stop_id FROM cdb_sr_stop_notes WHERE route_id = ? AND note <> ?', [(int) $route['id'], '']) as $n) $notes[(int) $n['stop_id']] = true;
    $doneN = 0; $n = 0;
    foreach ($rv['live_stops'] as $s) {
        $n++;
        $done = in_array($s['status'], RP_DONE_STOP, true);
        if ($done) $doneN++;
        $open = in_array($s['status'], ['pending', 'arrived'], true);
        $a = $amt[(int) $s['request_id']] ?? null;
        if ($a && $open) { $cents += (int) $a['amount_cents']; $cur = (string) $a['currency']; }
        if ($open && $s['eta']) $lastEta = $s['eta'];
        $late = $open && !empty($s['late']);
        if ($late) $lateN++;
        $stops[] = ['id' => (int) $s['id'], 'request_id' => (int) $s['request_id'], 'seq' => $n, 'num' => str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'name' => (string) ($s['contact_name'] ?: $s['label']), 'address' => (string) $s['address'], 'area' => (string) $s['area'],
            'time' => $done ? srv_hm($s['done_at']) : srv_hm($s['eta']), 'status' => (string) $s['status'], 'done' => $done, 'open' => $open, 'late' => $late,
            'window' => $s['window_end'] ? 'by ' . $s['window_end'] : '', 'n' => (int) $s['n'], 'kg' => round((float) $s['kg'], 1), 'fail' => (string) ($s['fail_reason'] ?? ''),
            'movable' => $s['status'] === 'pending', 'removable' => $s['status'] === 'pending' && ($s['request_state'] ?? '') !== 'onroute', 'hasNote' => isset($notes[(int) $s['id']]),
            'search' => mb_strtolower($s['contact_name'] . ' ' . $s['address'] . ' #' . $s['request_id'])];
    }
    $marks = sr_routeMarks((int) $route['id']);
    $veh = $row['vehicle'];
    $inv = $rv['inventory'];
    $load = srv_load(['kg' => (float) $inv['open_kg'], 'l' => (float) $inv['open_l'], 'n' => (int) $inv['open']], $veh);
    $statusLabels = ['planned' => ['Not started', 'plain'], 'loading' => ['Loading', 'sorting'], 'onroute' => ['On the way', 'onway'], 'done' => ['Finished', 'delivered']];
    $editable = $route['status'] !== 'done' && !$d['isPast'];
    $sent = $marks['sent'];
    $changedSinceSend = $sent && (int) $sent['route_version'] !== (int) $route['version'];
    return ['id' => (int) $route['id'], 'status' => (string) $route['status'], 'stLabel' => $statusLabels[$route['status']][0] ?? ucfirst((string) $route['status']), 'stCls' => $statusLabels[$route['status']][1] ?? 'plain',
        'rider' => $row + ['id' => $driver], 'start' => ['label' => (string) ($rv['start']['label'] ?? 'Start'), 'time' => $route['started_at'] ? srv_hm($route['started_at']) : $d['departTime'], 'left' => $route['status'] === 'onroute' || $route['status'] === 'done' || $doneN > 0],
        'stops' => $stops, 'doneN' => $doneN, 'total' => $n, 'lateN' => $lateN, 'editable' => $editable, 'canChangeRider' => $editable && $route['status'] === 'planned',
        'summary' => ['distance' => srv_km((float) $route['total_m']), 'drive' => srv_dur((int) $route['total_s']), 'finish' => $lastEta ? srv_hm($lastEta) : '', 'cash' => $finance && $cents ? srv_money($cents, $cur) : '', 'load' => $load,
            'estimated' => ($rv['provider'] ?? 'estimate') !== 'google'],
        'alerts' => array_values(array_map(fn($a) => (string) ($a['msg'] ?? $a['message'] ?? ''), (array) $rv['alerts'])),
        'pos' => $rv['map']['rider_pos'] ?? null, 'lastPing' => $rv['last_ping_at'] ? srv_hm($rv['last_ping_at']) : '',
        'saved' => $marks['saved'] ? ['at' => date('j M H:i', strtotime((string) $marks['saved']['created_at'])), 'by' => $marks['saved']['by']] : null,
        'sent' => $sent ? ['at' => date('j M H:i', strtotime((string) $sent['created_at'])), 'by' => $sent['by'], 'stops' => (int) $sent['stops'], 'channels' => $sent['channels'], 'changed' => $changedSinceSend] : null,
        'others' => array_values(array_filter($rows, fn($r) => (int) $r['id'] !== $driver && !in_array($r['status'], ['suspended'], true))),
        'backUrl' => srv_url(['date' => $d['isToday'] ? null : $route['route_date'], 'rider' => $driver])];
}

/** "Riders & vehicles": grouped list, the chosen rider's vehicle, routes and statistics. */
function srv_ridersView(array $rows, array $riders, array $routes, array $routeOf, int $actor, int $agency, int $selRider, bool $finance): array
{
    $fav = sr_favourites($actor);
    $settings = rp_settings();
    $freshS = max(60, (int) ($settings['offline_min'] ?? 10) * 60);
    $states = ['onway' => 'On the way', 'loading' => 'Loading', 'waiting' => 'Waiting', 'assigned' => 'Planned', 'available' => 'Available', 'off' => 'Off', 'suspended' => 'Suspended'];
    $list = [];
    foreach ($rows as $id => $r) {
        $p = $riders[$id];
        $ping = rp_val('SELECT MAX(recorded_at) FROM cdb_rp_pings WHERE driver_id = ?', [$id]);
        $state = $p['status'] === 'waiting' && ($p['activity'] ?? '') === 'break' ? 'On break' : ($states[$p['status']] ?? ucfirst((string) $p['status']));
        $list[$id] = $r + ['fav' => in_array($id, $fav, true), 'online' => $ping && strtotime((string) $ping) >= time() - $freshS, 'state' => $state, 'stateKey' => (string) $p['status'], 'alert' => false,
            'href' => srv_url(['view' => 'riders', 'rider' => $id])];
    }
    // Alerts raised by the engine (late, stuck, no signal) put a dot on the rider.
    foreach ($routes as $rv) if (!empty($rv['alerts']) && isset($list[(int) $rv['rider']['id']])) $list[(int) $rv['rider']['id']]['alert'] = true;
    $plural = ['bicycle' => 'Bicycles', 'motorbike' => 'Motorbikes', 'car' => 'Cars', 'van' => 'Vans', 'truck' => 'Trucks'];
    $groups = [];
    $favRows = array_values(array_filter($list, fn($r) => $r['fav']));
    $groups[] = ['key' => 'fav', 'label' => 'Favourites', 'rows' => $favRows, 'star' => true, 'empty' => 'Star a rider to keep them at the top of your list.'];
    foreach ($plural as $type => $label) {
        $g = array_values(array_filter($list, fn($r) => $r['vehicle']['type'] === $type));
        if ($g) $groups[] = ['key' => $type, 'label' => $label, 'rows' => $g, 'star' => false, 'empty' => ''];
    }
    if (!$selRider || !isset($list[$selRider])) $selRider = (int) (array_key_first($list) ?? 0);
    $sel = null;
    if ($selRider) {
        $r = $list[$selRider];
        $range = in_array($_GET['range'] ?? '', ['w', 'm', '6m', 'y'], true) ? (string) $_GET['range'] : 'w';
        $now = null;
        if (isset($routeOf[$selRider])) {
            $rv = $routes[$routeOf[$selRider]];
            $open = array_values(array_filter($rv['live_stops'], fn($s) => in_array($s['status'], ['pending', 'arrived'], true)));
            $last = $rv['live_stops'] ? end($rv['live_stops']) : null;
            $leftS = array_sum(array_map(fn($s) => (int) $s['leg_s'], $open));
            $inv = $rv['inventory'];
            $now = ['id' => (int) $rv['route']['id'], 'status' => (string) $rv['route']['status'], 'heading' => ['onroute' => 'Now on the way', 'loading' => 'Loading now', 'planned' => 'Planned for today', 'done' => 'Finished today'][$rv['route']['status']] ?? 'Today',
                'packages' => (int) $inv['total'], 'stops' => count($rv['live_stops']), 'done' => (int) $rv['progress']['done'], 'from' => (string) ($rv['start']['label'] ?? 'Start'), 'to' => $last ? ((string) ($last['area'] ?: $last['label'])) : '',
                'distance' => srv_km((float) $rv['route']['total_m']), 'timeLeft' => $rv['route']['status'] === 'done' ? 'Done' : srv_dur($leftS), 'weight' => srv_num((float) $inv['kg']) . ' kg', 'volume' => srv_num((float) $inv['l']) . ' L',
                'url' => srv_url(['route' => (int) $rv['route']['id']]),
                'map' => ['depot' => $rv['map']['depot'], 'stops' => array_map(fn($s) => ['id' => (int) $s['id'], 'seq' => (int) $s['seq'], 'lat' => (float) $s['lat'], 'lng' => (float) $s['lng'], 'label' => (string) $s['label'], 'status' => (string) $s['status']], $rv['live_stops']), 'path' => $rv['map']['path'] ?? null, 'pos' => $rv['map']['rider_pos'] ?? null, 'color' => $r['color'], 'name' => $r['name']]];
        }
        $earlier = [];
        foreach (rp_all("SELECT r.id, r.route_date, r.status, r.total_m, r.total_s, r.start_json,
                (SELECT COUNT(*) FROM cdb_rp_load l WHERE l.route_id = r.id) AS packages,
                (SELECT COUNT(*) FROM cdb_rp_stops s WHERE s.route_id = r.id AND s.status <> 'removed') AS stops,
                (SELECT s2.address FROM cdb_rp_stops s2 WHERE s2.route_id = r.id AND s2.status <> 'removed' ORDER BY s2.seq DESC LIMIT 1) AS last_address
            FROM cdb_rp_routes r WHERE r.driver_id = ? AND r.id <> ? AND r.route_date <= ? ORDER BY r.route_date DESC, r.id DESC LIMIT 5", [$selRider, $now['id'] ?? 0, date('Y-m-d')]) as $e) {
            if (!(int) $e['stops']) continue;
            $st = json_decode((string) $e['start_json'], true) ?: [];
            $earlier[] = ['id' => (int) $e['id'], 'date' => date('D j M Y', strtotime((string) $e['route_date'])), 'packages' => (int) $e['packages'], 'stops' => (int) $e['stops'], 'from' => (string) ($st['label'] ?? 'Start'), 'to' => rp__area((string) $e['last_address']) ?: mb_strimwidth((string) $e['last_address'], 0, 40, '...'),
                'distance' => srv_km((float) $e['total_m']), 'url' => srv_url(['date' => (string) $e['route_date'], 'route' => (int) $e['id']])];
        }
        $phoneDigits = preg_replace('/\D+/', '', (string) $r['phone']);
        $sel = $r + ['now' => $now, 'earlier' => $earlier, 'stats' => srv_stats($selRider, $range), 'range' => $range,
            'ranges' => array_map(fn($k) => ['k' => $k, 'label' => strtoupper($k), 'title' => ['w' => 'Last 7 days', 'm' => 'Last 30 days', '6m' => 'Last 6 months', 'y' => 'Last 12 months'][$k], 'on' => $k === $range, 'href' => srv_url(['view' => 'riders', 'rider' => $selRider, 'range' => $k === 'w' ? null : $k]) . '#sr-stats'], ['w', 'm', '6m', 'y']),
            'wa' => strlen($phoneDigits) >= 9 ? 'https://wa.me/' . $phoneDigits : '', 'tel' => $r['phone'] ? 'tel:' . preg_replace('/[^\d+]/', '', (string) $r['phone']) : '',
            'history' => sl_route('RiderDetail', ['id' => $selRider]), 'docsUrl' => sl_route('RiderDetail', ['id' => $selRider])];
    }
    return ['groups' => $groups, 'sel' => $sel, 'count' => count($list)];
}

/** Time by category and working time per day (or per week / month for the long ranges). */
function srv_stats(int $driver, string $range): array
{
    $days = ['w' => 7, 'm' => 30, '6m' => 182, 'y' => 365][$range] ?? 7;
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    try {
        $st = rp_riderStats($driver, $from, date('Y-m-d'));
    } catch (Throwable $e) {
        error_log('[smart_routing] stats: ' . $e->getMessage());
        return ['empty' => true, 'cats' => [], 'bars' => [], 'period' => ''];
    }
    $per = array_values((array) ($st['per_day'] ?? []));
    $worked = array_values(array_filter($per, fn($x) => (int) $x['work_s'] > 0));
    $nWorked = max(1, count($worked));
    // Only what the rider app records: time on the way, loading, waiting at a stop and breaks.
    $defs = ['onway_s' => ['On the way', 'var(--ch-2)'], 'loading_s' => ['Loading', 'var(--ch-3)'], 'waiting_s' => ['Waiting', 'var(--ch-4)'], 'break_s' => ['Break', 'var(--ch-1)']];
    $tot = [];
    foreach ($defs as $k => $_) $tot[$k] = array_sum(array_map(fn($x) => (int) ($x[$k] ?? 0), $per));
    $sum = array_sum($tot);
    $cats = [];
    foreach ($defs as $k => [$label, $color]) {
        if ($tot[$k] <= 0) continue;
        $cats[] = ['label' => $label, 'color' => $color, 'avg' => srv_dur((int) round($tot[$k] / $nWorked)), 'pct' => round($tot[$k] * 100 / max(1, $sum), 1), 'w' => max(6, round($tot[$k] * 100 / max(1, $sum), 1))];
    }
    // Bars: days for a week or month, weeks for six months, months for a year (average per worked day).
    $buckets = [];
    foreach ($per as $x) {
        $t = strtotime((string) $x['date']);
        $key = $range === 'y' ? date('Y-m', $t) : ($range === '6m' ? date('o-\WW', $t) : (string) $x['date']);
        $label = $range === 'y' ? date('F Y', $t) : ($range === '6m' ? 'Week of ' . date('j M', strtotime('monday this week', $t)) : date('D j M', $t));
        $short = $range === 'y' ? date('M', $t) : ($range === '6m' ? date('j/n', strtotime('monday this week', $t)) : ($range === 'm' ? date('j', $t) : date('D', $t)));
        $b = &$buckets[$key];
        $b = $b ?? ['label' => $label, 'short' => $short, 'work' => 0, 'n' => 0];
        if ((int) $x['work_s'] > 0) { $b['work'] += (int) $x['work_s']; $b['n']++; }
        unset($b);
    }
    $avgS = $worked ? array_sum(array_map(fn($x) => (int) $x['work_s'], $worked)) / count($worked) : 0;
    $vals = array_map(fn($b) => $b['n'] ? round($b['work'] / $b['n'] / 3600, 2) : 0.0, array_values($buckets));
    $peak = max(1.0, max($vals ?: [0]), $avgS / 3600);
    $axis = (int) ceil($peak / 2) * 2;
    $bars = [];
    foreach (array_values($buckets) as $i => $b) $bars[] = ['label' => $b['label'], 'short' => $b['short'], 'hours' => $vals[$i], 'h' => (int) round($vals[$i] * 100 / $axis), 'text' => $vals[$i] > 0 ? srv_dur((int) round($vals[$i] * 3600)) : 'No work recorded'];
    return ['empty' => $sum <= 0, 'cats' => $cats, 'bars' => $bars, 'avgH' => (int) round(($avgS / 3600) * 100 / $axis), 'avgText' => srv_dur((int) round($avgS)), 'axis' => $axis, 'workedDays' => count($worked),
        'period' => date('j M', strtotime($from)) . ' to ' . date('j M Y'), 'delivered' => (int) ($st['delivered'] ?? 0), 'failed' => (int) ($st['failed'] ?? 0), 'distance' => srv_km((float) ($st['distance_m'] ?? 0)),
        'unit' => $range === 'y' ? 'month' : ($range === '6m' ? 'week' : 'day'),
        'chart' => sl_chartAttrs([['name' => 'Working time', 'values' => $vals, 'unit' => ' h', 'color' => 'var(--ink)'], ['name' => 'Average', 'values' => array_fill(0, count($vals), round($avgS / 3600, 2)), 'unit' => ' h', 'color' => 'var(--ch-2)']], array_column(array_values($buckets), 'label'), 'Working time per ' . ($range === 'y' ? 'month' : ($range === '6m' ? 'week' : 'day')))];
}
