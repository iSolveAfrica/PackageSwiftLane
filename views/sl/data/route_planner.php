<?php
/**
 * Route planner (admin) view model: views/sl/pages/RoutePlanner.php.
 * Reads the route engine (helpers/route_planner.php, rp_*) only through its read functions; writes go
 * through ajax/sl/route_planner_ajax.php. Every engine result is normalised here (rpv_*), so the
 * partials only see one shape and the page keeps an empty state while the engine is not installed.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';

/** First present key of an array/object (dotted paths allowed), else $default. */
function rpv_pick($src, array $keys, $default = null)
{
    foreach ($keys as $k) {
        $cur = $src;
        $ok = true;
        foreach (explode('.', $k) as $seg) {
            if (is_array($cur) && array_key_exists($seg, $cur)) $cur = $cur[$seg];
            elseif (is_object($cur) && property_exists($cur, $seg)) $cur = $cur->$seg;
            else { $ok = false; break; }
        }
        if ($ok && $cur !== null && $cur !== '') return $cur;
    }
    return $default;
}

function rpv_arr($v): array
{
    if (is_object($v)) return get_object_vars($v);
    if (is_string($v) && $v !== '' && ($v[0] === '{' || $v[0] === '[')) return json_decode($v, true) ?: [];
    return is_array($v) ? $v : [];
}

/** Call an engine function if it exists; any failure becomes $default plus a logged note. */
function rpv_call(string $fn, array $args, $default, array &$notes)
{
    if (!function_exists($fn)) return $default;
    try {
        return $fn(...$args);
    } catch (Throwable $e) {
        error_log('[route_planner] ' . $fn . ': ' . $e->getMessage());
        $notes[] = $fn . ': ' . $e->getMessage();
        return $default;
    }
}

function rpv_km($m): string
{
    $m = (float) $m;
    return $m >= 1000 ? number_format($m / 1000, $m >= 100000 ? 0 : 1) . ' km' : (int) round($m) . ' m';
}

function rpv_dur($s): string
{
    $s = (int) $s;
    if ($s <= 0) return '0 min';
    $h = intdiv($s, 3600);
    $m = (int) round(($s % 3600) / 60);
    return $h ? $h . ' h' . ($m ? ' ' . $m . ' min' : '') : max(1, $m) . ' min';
}

/** "14:05" from an ISO/SQL datetime or a TIME value. */
function rpv_hm($v): string
{
    if ($v === null || $v === '') return '';
    if (is_numeric($v)) return date('H:i', (int) $v);
    if (preg_match('/^\d{1,2}:\d{2}/', (string) $v)) return substr((string) $v, 0, 5);
    $t = strtotime((string) $v);
    return $t ? date('H:i', $t) : '';
}

function rpv_pct(float $used, float $max): int
{
    return $max > 0 ? (int) max(0, min(999, round($used * 100 / $max))) : 0;
}

/** Fill level class for load bars. */
function rpv_fillCls(int $pct): string
{
    return $pct > 100 ? 'over' : ($pct >= 85 ? 'full' : ($pct >= 50 ? 'mid' : 'low'));
}

const RPV_STATUS = [
    'onway' => ['On the way', 'onway'],
    'loading' => ['Loading', 'sorting'],
    'waiting' => ['Waiting', 'transit'],
    'assigned' => ['Assigned', 'skipped'],
    'available' => ['Available', 'delivered'],
    'off' => ['Off duty', 'plain'],
    'suspended' => ['Suspended', 'pending'],
];
const RPV_HANDLING = ['fragile' => 'Fragile', 'cold' => 'Cold', 'high_value' => 'High value', 'oversize' => 'Oversize', 'liquid' => 'Liquid'];
const RPV_COLORS = ['#2563eb', '#d97706', '#059669', '#db2777', '#7c3aed', '#0891b2', '#dc2626', '#65a30d', '#9333ea', '#ea580c'];

function rpv_status(string $s): string
{
    $s = strtolower(trim($s));
    $map = ['onroute' => 'onway', 'on_the_way' => 'onway', 'busy' => 'onway', 'active' => 'available', 'idle' => 'available', 'off_duty' => 'off', 'offline' => 'off', 'inactive' => 'off'];
    $s = $map[$s] ?? $s;
    return isset(RPV_STATUS[$s]) ? $s : 'available';
}

function rpv_handling($v): array
{
    if (is_string($v)) $v = $v === '' ? [] : preg_split('/\s*,\s*/', $v);
    $out = [];
    foreach ((array) $v as $h) {
        $h = strtolower(str_replace([' ', '-'], '_', (string) $h));
        if (isset(RPV_HANDLING[$h])) $out[] = $h;
    }
    return array_values(array_unique($out));
}

/** One parcel (rp_parcelDetails shape, tolerant). */
function rpv_parcel($p): array
{
    $p = rpv_arr($p);
    $l = (float) rpv_pick($p, ['l', 'length_cm', 'length'], 0);
    $w = (float) rpv_pick($p, ['w', 'width_cm', 'width'], 0);
    $hh = (float) rpv_pick($p, ['h', 'height_cm', 'height'], 0);
    $win = rpv_pick($p, ['window'], null);
    $ws = is_array($win) ? rpv_pick($win, ['start', 0], '') : rpv_pick($p, ['window_start'], '');
    $we = is_array($win) ? rpv_pick($win, ['end', 1], '') : rpv_pick($p, ['window_end'], '');
    return $p + [
        'module' => (string) rpv_pick($p, ['module'], 'air'),
        'order_id' => (int) rpv_pick($p, ['order_id', 'id'], 0),
        'tracking' => (string) rpv_pick($p, ['tracking', 'order_track'], ''),
        'contents' => (string) rpv_pick($p, ['contents', 'description'], ''),
        'l' => $l, 'w' => $w, 'h' => $hh,
        'kg' => (float) rpv_pick($p, ['kg', 'weight_kg', 'weight'], 0),
        'volume_l' => (float) rpv_pick($p, ['volume_l', 'l_vol'], $l && $w && $hh ? round($l * $w * $hh / 1000, 1) : 0),
        'handling' => rpv_handling(rpv_pick($p, ['handling'], [])),
        'value' => rpv_pick($p, ['value', 'value_cents'], 0),
        'currency' => (string) rpv_pick($p, ['currency'], ''),
        'note' => (string) rpv_pick($p, ['note', 'customer_note'], ''),
        'window' => trim(rpv_hm($ws) . ($we ? '–' . rpv_hm($we) : ''), '–'),
        'priority' => (int) rpv_pick($p, ['priority'], 0),
        'status' => (string) rpv_pick($p, ['status', 'status_name'], ''),
    ];
}

/** One stop/unit (a pending delivery request). */
function rpv_unit($u, array $zones = []): array
{
    $u = rpv_arr($u);
    $parcels = array_map('rpv_parcel', rpv_arr(rpv_pick($u, ['parcels', 'items'], [])));
    $handling = rpv_handling(rpv_pick($u, ['handling'], []));
    foreach ($parcels as $p) $handling = array_values(array_unique(array_merge($handling, $p['handling'])));
    $zid = (int) rpv_pick($u, ['zone_id'], 0);
    $ws = rpv_pick($u, ['window_start', 'window.start'], '');
    $we = rpv_pick($u, ['window_end', 'window.end'], '');
    $lat = rpv_pick($u, ['lat'], null);
    $lng = rpv_pick($u, ['lng', 'lon'], null);
    $prio = (int) rpv_pick($u, ['priority'], 0);
    return [
        'id' => (int) rpv_pick($u, ['id', 'request_id'], 0),
        'lat' => $lat === null ? null : (float) $lat,
        'lng' => $lng === null ? null : (float) $lng,
        'address' => (string) rpv_pick($u, ['address', 'label'], ''),
        'area' => (string) rpv_pick($u, ['area', 'zone_name', 'zone', 'city'], $zones[$zid] ?? ''),
        'zone_id' => $zid,
        'contact' => (string) rpv_pick($u, ['contact_name', 'contact', 'customer'], ''),
        'phone' => (string) rpv_pick($u, ['contact_phone', 'phone'], ''),
        'kg' => round((float) rpv_pick($u, ['kg', 'load.kg'], array_sum(array_column($parcels, 'kg'))), 1),
        'l' => round((float) rpv_pick($u, ['l', 'load.l'], array_sum(array_column($parcels, 'volume_l'))), 1),
        'n' => (int) rpv_pick($u, ['n', 'load.n'], count($parcels) ?: 1),
        'handling' => $handling,
        'value_cents' => (int) rpv_pick($u, ['value_cents'], 0),
        'window' => trim(rpv_hm($ws) . ($we ? '–' . rpv_hm($we) : ''), '–'),
        'priority' => $prio,
        'prioLabel' => $prio >= 2 ? 'Urgent' : ($prio === 1 ? 'High' : ''),
        'reason' => (string) rpv_pick($u, ['reason', 'unplaceable'], ''),
        'note' => (string) rpv_pick($u, ['instructions', 'note'], ''),
        'parcels' => $parcels,
        'eta' => rpv_hm(rpv_pick($u, ['eta'], '')),
    ];
}

/** Vehicle (rp_vehicle shape, tolerant). */
function rpv_vehicle($v, string $fallbackType = ''): array
{
    $v = rpv_arr($v);
    $type = strtolower((string) rpv_pick($v, ['vehicle_type', 'type'], $fallbackType ?: 'motorbike'));
    $l = (float) rpv_pick($v, ['cargo_l_cm'], 0); $w = (float) rpv_pick($v, ['cargo_w_cm'], 0); $hh = (float) rpv_pick($v, ['cargo_h_cm'], 0);
    $docs = [];
    foreach (rpv_arr(rpv_pick($v, ['docs_json', 'docs'], [])) as $doc) {
        $doc = rpv_arr($doc);
        $exp = (string) rpv_pick($doc, ['expires', 'expiry', 'expires_at'], '');
        $t = $exp ? strtotime($exp) : 0;
        $docs[] = ['name' => (string) rpv_pick($doc, ['name', 'label', 'type'], 'Document'), 'expires' => $exp ? sl_date($exp) : 'No expiry', 'file_id' => (int) rpv_pick($doc, ['file_id', 'id'], 0),
            'state' => !$t ? 'plain' : ($t < time() ? 'pending' : ($t < time() + 30 * 86400 ? 'onway' : 'delivered')), 'stLabel' => !$t ? 'On file' : ($t < time() ? 'Expired' : ($t < time() + 30 * 86400 ? 'Expires soon' : 'Valid'))];
    }
    if (!$docs) foreach (rpv_arr(rpv_pick($v, ['files'], [])) as $f) {
        $f = rpv_arr($f);
        $docs[] = ['name' => (string) rpv_pick($f, ['name', 'file_type'], 'Document'), 'expires' => ($fd = rpv_pick($f, ['date_file'], '')) ? 'Uploaded ' . sl_date($fd) : 'On file', 'file_id' => (int) rpv_pick($f, ['id'], 0), 'state' => 'plain', 'stLabel' => 'On file'];
    }
    return [
        'type' => $type,
        'typeLabel' => ucfirst($type),
        'plate' => (string) rpv_pick($v, ['plate'], ''),
        'make' => (string) rpv_pick($v, ['make_model'], ''),
        'max_kg' => (float) rpv_pick($v, ['max_kg'], 0),
        'max_l' => (float) rpv_pick($v, ['max_l'], 0),
        'max_n' => (int) rpv_pick($v, ['max_parcels', 'max_n'], 0),
        'dims' => $l && $w && $hh ? (int) $l . ' × ' . (int) $w . ' × ' . (int) $hh . ' cm' : '',
        'cargo' => ['l' => $l, 'w' => $w, 'h' => $hh],
        'fragile' => (bool) rpv_pick($v, ['can_fragile'], false),
        'cold' => (bool) rpv_pick($v, ['can_cold'], false),
        'hv' => (bool) rpv_pick($v, ['can_high_value'], false),
        'active' => (bool) rpv_pick($v, ['active'], true),
        'docs' => $docs,
        'raw' => $v,
    ];
}

function rpv_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $i = '';
    foreach (array_slice($parts, 0, 2) as $p) $i .= mb_strtoupper(mb_substr($p, 0, 1));
    return $i ?: '?';
}

/** Load bars for kg / litres / parcels. */
function rpv_load(array $veh, float $kg, float $l, int $n): array
{
    $bars = [];
    foreach ([['kg', 'Weight', $kg, $veh['max_kg'], ' kg'], ['l', 'Volume', $l, $veh['max_l'], ' L'], ['n', 'Parcels', $n, $veh['max_n'], '']] as [$k, $label, $used, $max, $unit]) {
        $pct = rpv_pct((float) $used, (float) $max);
        $bars[$k] = ['label' => $label, 'pct' => $pct, 'w' => min(100, $pct), 'cls' => rpv_fillCls($pct),
            'text' => ($k === 'n' ? (int) $used : rtrim(rtrim(number_format((float) $used, 1), '0'), '.')) . ' / ' . ($max > 0 ? ($k === 'n' ? (int) $max : rtrim(rtrim(number_format((float) $max, 1), '0'), '.')) : '–') . $unit];
    }
    return $bars;
}

/** The newest draft plan (or ?plan=) with its decoded proposal. */
function rpv_draftPlan(string $date, int $agency): ?array
{
    if (!sl_tableExists('cdb_rp_plans')) return null;
    $id = (int) ($_GET['plan'] ?? 0);
    $agencyWhere = $agency ? ' AND agency_id = :a' : '';
    $b = $agency ? [':a' => $agency] : [];
    $row = $id
        ? sl_row("SELECT * FROM cdb_rp_plans WHERE id = :id AND status = 'draft'$agencyWhere", [':id' => $id] + $b)
        : sl_row("SELECT * FROM cdb_rp_plans WHERE status = 'draft' AND plan_date = :d$agencyWhere ORDER BY id DESC", [':d' => $date] + $b);
    if (!$row) return null;
    $prop = rpv_arr($row->proposal_json ?? '');
    if (isset($prop['proposal']) && is_array($prop['proposal'])) $prop = $prop['proposal'];
    $params = rpv_arr($row->params_json ?? '');
    $locked = array_map('intval', (array) rpv_pick($params, ['lock', 'locked', 'locks'], []));
    $riders = [];
    foreach (rpv_arr($prop['riders'] ?? []) as $rid => $r) {
        $r = rpv_arr($r);
        $rid = (int) rpv_pick($r, ['id', 'driver_id'], $rid);
        $route = rpv_arr($r['route'] ?? []);
        $riders[$rid] = [
            'units' => array_map('intval', array_values((array) ($r['units'] ?? []))),
            'route' => $route,
            'locked' => !empty($r['locked']) || in_array($rid, $locked, true),
        ];
    }
    $un = [];
    foreach (rpv_arr($prop['unallocated'] ?? []) as $u) {
        $u = is_array($u) ? $u : ['id' => (int) $u];
        $un[(int) rpv_pick($u, ['id', 'request_id'], 0)] = (string) rpv_pick($u, ['reason'], '');
    }
    return ['id' => (int) $row->id, 'riders' => $riders, 'unallocated' => $un, 'created' => (string) ($row->created_at ?? ''),
        'mode' => (string) rpv_pick($params, ['mode'], ''), 'start' => rpv_arr($row->start_json ?? ''), 'score' => rpv_arr($prop['score'] ?? [])];
}

function sl_routePlannerData($user, $core): array
{
    $notes = [];
    $tabs = ['plan' => 'Plan & allocate', 'live' => 'Live map', 'fleet' => 'Fleet', 'timeline' => 'Timeline', 'settings' => 'Settings'];
    $tab = (string) ($_GET['tab'] ?? 'plan');
    if (!isset($tabs[$tab])) $tab = 'plan';
    $date = (string) ($_GET['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    $agency = function_exists('dl_agency') ? dl_agency() : 0;
    $actor = (int) ($user->uid ?? 0);
    $ready = function_exists('rp_cargoHold') && function_exists('rp_ridersForPlanning');
    if ($ready && function_exists('rp_ensureSchema')) rpv_call('rp_ensureSchema', [], null, $notes);
    $ready = $ready && sl_tableExists('cdb_rp_routes');

    $d = [
        'ready' => $ready, 'tab' => $tab, 'date' => $date, 'agency' => $agency, 'notes' => &$notes,
        'tabs' => array_map(fn($k, $label) => ['k' => $k, 'label' => $label, 'on' => $k === $tab, 'href' => sl_url(['tab' => $k, 'rider' => $k === 'settings' ? null : ($_GET['rider'] ?? null)])], array_keys($tabs), $tabs),
        'kpis' => [], 'riders' => [], 'groups' => [], 'hold' => [], 'holdUnplaceable' => [], 'plan' => null, 'sel' => null,
        'alerts' => [], 'timeline' => [], 'settings' => [], 'zones' => [], 'map' => ['depot' => null, 'routes' => [], 'riders' => []],
        'ajax' => 'ajax/sl/route_planner_ajax.php', 'canEdit' => true, 'provider' => 'estimate',
        'now' => date('H:i'), 'dateLabel' => sl_date($date, 'D j M Y'),
    ];
    if (!$ready) return $d;

    // Settings, start place, zones
    $settings = rpv_arr(rpv_call('rp_settings', [], [], $notes));
    $d['settings'] = $settings;
    $starts = [];
    foreach (rpv_arr($settings['start_places'] ?? []) as $sp) {
        $sp = rpv_arr($sp);
        $starts[] = ['id' => (string) rpv_pick($sp, ['id'], count($starts) + 1), 'label' => (string) rpv_pick($sp, ['label', 'name'], 'Start'), 'lat' => (float) rpv_pick($sp, ['lat'], 0), 'lng' => (float) rpv_pick($sp, ['lng'], 0)];
    }
    if (!$starts) $starts[] = ['id' => 'office', 'label' => 'Swift Lane Accra office', 'lat' => 5.6037, 'lng' => -0.1870];
    $defStart = (string) ($settings['default_start'] ?? $starts[0]['id']);
    $d['starts'] = $starts;
    $d['startId'] = $defStart;
    $depot = $starts[0];
    foreach ($starts as $s) if ($s['id'] === $defStart) $depot = $s;
    $d['map']['depot'] = ['lat' => $depot['lat'], 'lng' => $depot['lng'], 'label' => $depot['label']];
    $d['fillMode'] = (string) ($settings['fill_mode'] ?? 'fill');
    if (sl_tableExists('cdb_delivery_zones')) {
        foreach (sl_rows('SELECT id, name FROM cdb_delivery_zones' . (sl_columnExists('cdb_delivery_zones', 'archived_at') ? ' WHERE archived_at IS NULL' : '') . ' ORDER BY name') as $z) $d['zones'][(int) $z->id] = (string) $z->name;
    }

    // Fleet state: KPIs, positions, alerts
    $fleet = rpv_arr(rpv_call('rp_fleetState', [$date, $agency], [], $notes));
    $fk = rpv_arr($fleet['kpis'] ?? []);
    $fleetRiders = [];
    foreach (rpv_arr($fleet['riders'] ?? []) as $fr) {
        $fr = rpv_arr($fr);
        $fleetRiders[(int) rpv_pick($fr, ['id', 'driver_id'], 0)] = $fr;
    }
    foreach (rpv_arr($fleet['alerts'] ?? []) as $a) {
        $a = rpv_arr($a);
        $sev = (string) rpv_pick($a, ['severity'], 'warn');
        $d['alerts'][] = ['type' => (string) rpv_pick($a, ['type'], 'alert'), 'sev' => $sev, 'pc' => in_array($sev, ['high', 'critical', 'error', 'bad'], true) ? 'pending' : 'onway',
            'msg' => (string) rpv_pick($a, ['message', 'msg'], ''), 'driver_id' => (int) rpv_pick($a, ['driver_id', 'rider_id'], 0), 'name' => (string) rpv_pick($a, ['name', 'rider'], ''), 'route_id' => (int) rpv_pick($a, ['route_id'], 0)];
    }

    // Cargo hold
    $holdRaw = rpv_call('rp_cargoHold', [$agency], [], $notes);
    $holdRaw = rpv_arr($holdRaw);
    $unitsRaw = isset($holdRaw['units']) ? rpv_arr($holdRaw['units']) : $holdRaw;
    $units = [];
    foreach ($unitsRaw as $u) {
        $vm = rpv_unit($u, $d['zones']);
        if ($vm['id']) $units[$vm['id']] = $vm;
    }
    foreach (rpv_arr($holdRaw['unplaceable'] ?? []) as $u) {
        $vm = rpv_unit($u, $d['zones']);
        if ($vm['id'] && !isset($units[$vm['id']])) { $vm['reason'] = $vm['reason'] ?: 'No map location'; $units[$vm['id']] = $vm; }
    }
    foreach ($units as &$u) if (!$u['reason'] && ($u['lat'] === null || $u['lng'] === null || (!$u['lat'] && !$u['lng']))) $u['reason'] = 'No map location';
    unset($u);

    // Draft plan, re-checked against today's assignments (made elsewhere since the proposal).
    $plan = rpv_draftPlan($date, $agency);
    $d['planIssues'] = null;
    if ($plan && function_exists('rp_rebaseDraft') && function_exists('rp__loadDraft')) {
        try {
            $row = rp__loadDraft($plan['id']);
            $rb = rp_rebaseDraft($row);
            if (json_encode($rb['proposal']) !== json_encode($row['proposal'])) rp__saveDraft($plan['id'], $rb['proposal'], $row['params']);
            foreach ($rb['proposal']['riders'] ?? [] as $rid => $rr) if (isset($plan['riders'][(int) $rid])) {
                $plan['riders'][(int) $rid]['units'] = array_map('intval', (array) $rr['units']);
                $plan['riders'][(int) $rid]['route'] = rpv_arr($rr['route'] ?? []);
            }
            $iss = $rb['issues'];
            $msgs = [];
            if ($iss['stale']) $msgs[] = count($iss['stale']) . ' stop' . (count($iss['stale']) === 1 ? ' was' : 's were') . ' assigned elsewhere and left the plan';
            if ($iss['over']) $msgs[] = count($iss['over']) . ' rider' . (count($iss['over']) === 1 ? ' is' : 's are') . ' over capacity';
            if ($iss['ineligible']) $msgs[] = count($iss['ineligible']) . ' stop' . (count($iss['ineligible']) === 1 ? ' does' : 's do') . ' not suit the rider\'s vehicle';
            $d['planIssues'] = $msgs ? ['msgs' => $msgs, 'over' => array_map('intval', array_keys($iss['over'])), 'ineligible' => $iss['ineligible'], 'block' => (bool) $iss['ineligible'] || (bool) array_filter($iss['over'], fn($f) => max($f['kg'], $f['l'], $f['n']) > 110.05)] : null;
        } catch (Throwable $e) {
            error_log('[route_planner] rebase draft: ' . $e->getMessage());
        }
    }
    $inPlan = [];
    if ($plan) foreach ($plan['riders'] as $rid => $pr) foreach ($pr['units'] as $uid) $inPlan[$uid] = $rid;

    // Riders
    $ridersRaw = rpv_arr(rpv_call('rp_ridersForPlanning', [$date, $agency], [], $notes));
    $selId = (int) ($_GET['rider'] ?? 0);
    $ci = 0;
    $groupDefs = ['onway' => 'On the way', 'loading' => 'Loading', 'waiting' => 'Waiting', 'assigned' => 'Assigned', 'available' => 'Available', 'off' => 'Off'];
    $groups = array_fill_keys(array_keys($groupDefs), []);
    $sumAlloc = 0;
    foreach ($ridersRaw as $key => $r) {
        $r = rpv_arr($r);
        $id = (int) rpv_pick($r, ['id', 'driver_id'], is_int($key) ? $key : 0);
        if (!$id) continue;
        $fr = $fleetRiders[$id] ?? [];
        $name = (string) rpv_pick($r, ['name'], trim(rpv_pick($r, ['fname'], '') . ' ' . rpv_pick($r, ['lname'], '')) ?: 'Rider #' . $id);
        $veh = rpv_vehicle(rpv_pick($r, ['vehicle'], []), (string) rpv_pick($r, ['vehicle_type'], ''));
        $used = rpv_arr(rpv_pick($r, ['used', 'load'], []));
        $status = rpv_status((string) rpv_pick($fr, ['status'], rpv_pick($r, ['status', 'rider_status'], 'available')));
        $pr = $plan['riders'][$id] ?? null;
        $planUnits = [];
        foreach (($pr['units'] ?? []) as $uid) if (isset($units[$uid])) $planUnits[] = $units[$uid];
        // Route order of the proposal (stop ids = request ids)
        $order = array_map('intval', (array) rpv_pick($pr['route'] ?? [], ['order'], []));
        $legs = rpv_arr(rpv_pick($pr['route'] ?? [], ['legs'], []));
        if ($order) {
            $pos = array_flip($order);
            usort($planUnits, fn($a, $b) => ($pos[$a['id']] ?? 999) <=> ($pos[$b['id']] ?? 999));
            foreach ($planUnits as $i => &$pu) {
                $li = $pos[$pu['id']] ?? null;
                $leg = $li !== null ? rpv_arr($legs[$li] ?? []) : [];
                $pu['seq'] = $i + 1;
                $pu['eta'] = rpv_hm(rpv_pick($leg, ['eta'], ''));
                $pu['late'] = (int) rpv_pick($leg, ['late_s'], 0) > 0;
                $pu['leg'] = $leg ? rpv_km(rpv_pick($leg, ['m'], 0)) : '';
            }
            unset($pu);
        } else {
            foreach ($planUnits as $i => &$pu) { $pu['seq'] = $i + 1; $pu['late'] = false; $pu['leg'] = ''; }
            unset($pu);
        }
        $ukg = (float) rpv_pick($used, ['kg'], 0); $ul = (float) rpv_pick($used, ['l'], 0); $un = (int) rpv_pick($used, ['n'], 0);
        $pkg = array_sum(array_column($planUnits, 'kg')); $pl = array_sum(array_column($planUnits, 'l')); $pn = array_sum(array_column($planUnits, 'n'));
        $prog = rpv_arr(rpv_pick($fr, ['progress'], []));
        $posR = rpv_pick($fr, ['pos'], null);
        $posR = is_array($posR) ? ['lat' => (float) rpv_pick($posR, ['lat', 0], 0), 'lng' => (float) rpv_pick($posR, ['lng', 1], 0)] : null;
        $sumAlloc += count($planUnits);
        $vm = [
            'id' => $id, 'name' => $name, 'initials' => rpv_initials($name), 'color' => RPV_COLORS[$ci++ % count(RPV_COLORS)],
            'phone' => (string) rpv_pick($r, ['phone'], ''), 'zone' => (string) rpv_pick($r, ['zone_name', 'zone'], ''),
            'status' => $status, 'stLabel' => RPV_STATUS[$status][0], 'stCls' => RPV_STATUS[$status][1],
            'vehicle' => $veh, 'locked' => (bool) ($pr['locked'] ?? rpv_pick($r, ['locked'], false)),
            'planUnits' => $planUnits, 'inPlan' => $pr !== null,
            'route' => $pr ? ['km' => rpv_km(rpv_pick($pr['route'], ['total_m'], 0)), 'time' => rpv_dur(rpv_pick($pr['route'], ['total_s'], 0)),
                'late' => (int) rpv_pick($pr['route'], ['late'], 0),
                // Distance saved against the plain nearest-first order (not the cost function).
                'improved' => ($nn = (float) rpv_pick($pr['route'], ['nn_total_m'], 0)) > 0 ? round(100 * ($nn - (float) rpv_pick($pr['route'], ['total_m'], 0)) / $nn, 1) : 0.0,
                'late_fixed' => max(0, (int) rpv_pick($pr['route'], ['nn_late'], 0) - (int) rpv_pick($pr['route'], ['late'], 0)),
                'provider' => (string) rpv_pick($pr['route'], ['provider'], 'estimate')] : null,
            'current' => ['kg' => $ukg, 'l' => $ul, 'n' => $un],
            'bars' => rpv_load($veh, $ukg + $pkg, $ul + $pl, $un + $pn),
            'over' => in_array($id, $d['planIssues']['over'] ?? [], true),
            'routeId' => (int) rpv_pick($fr, ['route_id'], rpv_pick($r, ['route_id'], 0)),
            'progress' => ['done' => (int) rpv_pick($prog, ['done'], 0), 'total' => (int) rpv_pick($prog, ['total'], 0)],
            'nextEta' => rpv_hm(rpv_pick($fr, ['next_eta'], '')),
            'lastPing' => ($lp = rpv_pick($fr, ['last_ping_at'], '')) ? sl_ago($lp) : '',
            'pos' => $posR,
            'href' => sl_url(['rider' => $id, 'tab' => in_array($tab, ['plan', 'live', 'fleet'], true) ? $tab : 'fleet']),
            'sel' => $id === $selId,
            'eligible' => array_values(array_filter([$veh['fragile'] ? 'fragile' : '', $veh['cold'] ? 'cold' : '', $veh['hv'] ? 'high_value' : ''])),
        ];
        $d['riders'][$id] = $vm;
        $g = $status === 'suspended' ? 'off' : $status;
        $groups[$g][] = $id;
        if ($vm['pos']) $d['map']['riders'][] = ['id' => $id, 'name' => $name, 'lat' => $vm['pos']['lat'], 'lng' => $vm['pos']['lng'], 'color' => $vm['color'], 'status' => $status];
    }
    foreach ($groupDefs as $k => $label) $d['groups'][] = ['k' => $k, 'label' => $label, 'n' => count($groups[$k]), 'ids' => $groups[$k]];

    // Hold = pending units not in the draft plan. A unit no working vehicle can carry keeps its
    // "needs attention" reason with or without a draft (checked against the riders' vehicles now).
    $rawById = [];
    foreach ($unitsRaw as $ru) { $ru = rpv_arr($ru); if (!empty($ru['id'])) $rawById[(int) $ru['id']] = $ru; }
    $fleetVeh = [];
    foreach ($ridersRaw as $rr) { $rr = rpv_arr($rr); if (!in_array((string) ($rr['status'] ?? ''), ['off', 'suspended'], true) && !empty($rr['vehicle']) && (rpv_arr($rr['vehicle'])['active'] ?? 1)) $fleetVeh[] = rpv_arr($rr['vehicle']); }
    $noVehicle = function (array $raw) use ($fleetVeh): string {
        if (!function_exists('rp_vehicleEligible') || !$fleetVeh) return '';
        $why = [];
        foreach ($fleetVeh as $v) {
            $w = rp_vehicleEligible($v, $raw);
            if ($w === null || preg_match('/^too (heavy|bulky|many)|another agency/', $w)) return '';
            $why[ucfirst((string) ($v['vehicle_type'] ?? 'vehicle'))] ??= $w;
        }
        return 'No vehicle on duty can take it: ' . implode('; ', array_map(fn($t, $w) => $t . ': ' . $w, array_keys($why), $why));
    };
    foreach ($units as $u) {
        if (isset($inPlan[$u['id']])) continue;
        if ($plan && isset($plan['unallocated'][$u['id']]) && $plan['unallocated'][$u['id']] !== '') $u['reason'] = $u['reason'] ?: $plan['unallocated'][$u['id']];
        if (!$u['reason'] && isset($rawById[$u['id']])) $u['reason'] = $noVehicle($rawById[$u['id']]);
        if ($u['reason']) $d['holdUnplaceable'][] = $u; else $d['hold'][] = $u;
    }
    usort($d['hold'], fn($a, $b) => [$b['priority'], $a['area'], $a['id']] <=> [$a['priority'], $b['area'], $b['id']]);
    $d['holdAll'] = array_merge($d['hold'], $d['holdUnplaceable']);
    $d['holdAreas'] = array_values(array_unique(array_filter(array_column($d['holdAll'], 'area'))));
    sort($d['holdAreas']);
    $d['plan'] = $plan ? ['id' => $plan['id'], 'created' => $plan['created'] ? sl_ago($plan['created']) : '', 'n' => $sumAlloc, 'riders' => count(array_filter($plan['riders'], fn($r) => $r['units'])), 'mode' => $plan['mode']] : null;

    // KPIs
    $ready_n = (int) rpv_pick($fk, ['ready'], count($units));
    $d['kpis'] = [
        ['k' => 'ready', 'l' => 'Ready for delivery', 'v' => $ready_n, 's' => count($d['hold']) . ' in the hold' . ($d['holdUnplaceable'] ? ' · ' . count($d['holdUnplaceable']) . ' need attention' : ''), 'icon' => 'i-box'],
        ['k' => 'allocated', 'l' => 'Allocated', 'v' => (int) rpv_pick($fk, ['allocated'], 0) + $sumAlloc, 's' => $sumAlloc ? $sumAlloc . ' in the draft plan' : 'Assigned, not started', 'icon' => 'i-layers'],
        ['k' => 'onway', 'l' => 'On the way', 'v' => (int) rpv_pick($fk, ['onway'], 0), 's' => ($nOn = count($groups['onway']) + count($groups['waiting'])) . ' rider' . ($nOn === 1 ? '' : 's') . ' on route', 'icon' => 'i-scooter'],
        ['k' => 'delivered', 'l' => 'Delivered today', 'v' => (int) rpv_pick($fk, ['delivered_today', 'delivered'], 0), 's' => 'Proof captured by riders', 'icon' => 'i-check'],
        ['k' => 'failed', 'l' => 'Failed', 'v' => (int) rpv_pick($fk, ['failed_today', 'failed'], 0), 's' => count($d['alerts']) ? count($d['alerts']) . ' live alerts' : 'No live alerts', 'icon' => 'i-alert'],
    ];

    // Plan map: each rider's proposed stops in its colour
    foreach ($d['riders'] as $r) {
        if (!$r['planUnits']) continue;
        $d['map']['routes'][] = ['driver_id' => $r['id'], 'color' => $r['color'], 'name' => $r['name'],
            'stops' => array_values(array_map(fn($u) => ['id' => $u['id'], 'seq' => $u['seq'], 'lat' => $u['lat'], 'lng' => $u['lng'], 'label' => $u['contact'] ?: $u['address'], 'status' => 'pending', 'eta' => $u['eta'], 'color' => $r['color']], array_filter($r['planUnits'], fn($u) => $u['lat'] !== null)))];
    }

    // Live map: confirmed routes of today (stops in route order, provider path when stored)
    if ($tab === 'live') {
        foreach ($d['riders'] as $r) {
            if (!$r['routeId']) continue;
            $rt = rpv_arr(rpv_call('rp_route', [$r['routeId'], $actor, 'manager'], [], $notes));
            $mp = rpv_arr($rt['map'] ?? []);
            if (empty($mp['stops'])) continue;
            $d['map']['routes'][] = ['driver_id' => $r['id'], 'color' => $r['color'], 'name' => $r['name'], 'confirmed' => true,
                'stops' => array_values(array_map(fn($x) => rpv_arr($x) + ['color' => $r['color']], rpv_arr($mp['stops'])))];
        }
    }

    // Selected rider card
    if (!$selId && $d['riders'] && $tab !== 'settings') {
        foreach ($d['riders'] as $r) { if ($r['planUnits'] || $r['routeId']) { $selId = $r['id']; break; } }
        if (!$selId) $selId = (int) array_key_first($d['riders']);
        $d['riders'][$selId]['sel'] = true;
    }
    if ($selId && isset($d['riders'][$selId])) $d['sel'] = rpv_selected($d['riders'][$selId], $d, $actor, $notes);

    if ($tab === 'timeline') $d['timeline'] = rpv_timeline(rpv_arr(rpv_call('rp_timeline', [$date, $agency], [], $notes)), $d['riders']);
    if ($tab === 'settings') $d['settingsForm'] = rpv_settingsForm($settings, $starts, $defStart);
    // Settings apply to every agency: head-office managers only (same rule as op=settings_save).
    $d['canSettings'] = $agency === 0 && method_exists($user, 'cdp_hasPermission') && $user->cdp_hasPermission(['manage_settings', 'settings', 'manage_freight']);
    $d['json'] = ['ajax' => $d['ajax'], 'tab' => $tab, 'date' => $date, 'plan' => $d['plan']['id'] ?? 0, 'startId' => $defStart, 'mode' => $d['fillMode'],
        'map' => $d['map'], 'sel' => $d['sel'] ? ['id' => $d['sel']['id'], 'routeId' => $d['sel']['routeId'], 'map' => $d['sel']['map']] : null,
        'riders' => array_values(array_map(fn($r) => ['id' => $r['id'], 'name' => $r['name'], 'color' => $r['color'], 'locked' => $r['locked']], $d['riders']))];
    return $d;
}

/** Selected rider: vehicle card, today's route (confirmed) or the proposed one, inventory, statistics. */
function rpv_selected(array $r, array $d, int $actor, array &$notes): array
{
    $veh = rpv_vehicle(rpv_call('rp_vehicle', [$r['id']], $r['vehicle']['raw'], $notes), $r['vehicle']['type']);
    $s = $r + ['vehicle' => $veh, 'routeId' => $r['routeId'], 'stops' => [], 'inventory' => null, 'stats' => null, 'source' => 'none', 'map' => null, 'summary' => '', 'alerts' => [], 'provider' => 'estimate'];
    $depot = $d['map']['depot'];
    if ($r['routeId']) {
        $route = rpv_arr(rpv_call('rp_route', [$r['routeId'], $actor, 'manager'], [], $notes));
        if ($route) {
            $s['source'] = 'route';
            $rt = rpv_arr($route['route'] ?? []);
            $s['routeStatus'] = (string) rpv_pick($rt, ['status'], '');
            $s['provider'] = (string) rpv_pick($route, ['provider', 'route.provider'], 'estimate');
            $s['summary'] = rpv_km(rpv_pick($rt, ['total_m'], 0)) . ' · ' . rpv_dur(rpv_pick($rt, ['total_s'], 0));
            foreach (rpv_arr($route['stops'] ?? []) as $st) {
                $st = rpv_arr($st);
                $u = rpv_unit($st, $d['zones']);
                $status = (string) rpv_pick($st, ['status'], 'pending');
                if ($status === 'removed') continue;
                $u['stop_id'] = (int) rpv_pick($st, ['stop_id', 'id'], 0);
                $u['id'] = (int) rpv_pick($st, ['request_id'], $u['id']);
                $u['seq'] = (int) rpv_pick($st, ['seq'], count($s['stops']) + 1);
                $u['status'] = $status;
                $u['eta'] = rpv_hm(rpv_pick($st, ['eta'], ''));
                $u['done'] = rpv_hm(rpv_pick($st, ['done_at'], ''));
                $u['leg'] = ($lm = rpv_pick($st, ['leg_m'], 0)) ? rpv_km($lm) : '';
                $u['late'] = false;
                $u['fail'] = (string) rpv_pick($st, ['fail_reason'], '');
                $s['stops'][] = $u;
            }
            $inv = rpv_arr($route['inventory'] ?? rpv_call('rp_inventory', [$r['routeId']], [], $notes));
            if ($inv) {
                $by = rpv_arr($inv['by_type'] ?? []);
                $s['inventory'] = [
                    'total' => (int) ($inv['total'] ?? 0), 'kg' => round((float) ($inv['kg'] ?? 0), 1), 'l' => round((float) ($inv['l'] ?? 0), 1),
                    'cells' => [['Loaded', (int) ($inv['loaded'] ?? 0), 'delivered'], ['To load', (int) ($inv['pending_load'] ?? 0), 'onway'], ['Missing', (int) ($inv['missing'] ?? 0), 'pending'], ['On board', (int) ($inv['onboard'] ?? 0), 'transit'], ['Delivered', (int) ($inv['delivered'] ?? 0), 'delivered'], ['Failed', (int) ($inv['failed'] ?? 0), 'pending'], ['Returned', (int) ($inv['returned'] ?? 0), 'sorting']],
                    'types' => array_values(array_filter(array_map(fn($k, $label) => ['label' => $label, 'n' => (int) ($by[$k] ?? 0)], ['air', 'sea', 'fragile', 'cold', 'high_value'], ['Air', 'Sea', 'Fragile', 'Cold', 'High value']), fn($t) => $t['n'] > 0)),
                ];
            }
            foreach (rpv_arr($route['alerts'] ?? []) as $a) { $a = rpv_arr($a); $s['alerts'][] = ['msg' => (string) rpv_pick($a, ['message'], ''), 'pc' => 'onway']; }
            $map = rpv_arr($route['map'] ?? []);
            $path = rpv_pick($map, ['path'], null);
            $rpos = rpv_pick($map, ['rider_pos'], $r['pos']);
            $s['map'] = [
                'depot' => rpv_pick($map, ['depot'], $depot),
                'stops' => array_values(array_map(fn($u) => ['id' => $u['stop_id'] ?: $u['id'], 'seq' => $u['seq'], 'lat' => $u['lat'], 'lng' => $u['lng'], 'label' => $u['contact'] ?: $u['address'], 'status' => $u['status'], 'eta' => $u['eta']], array_filter($s['stops'], fn($u) => $u['lat'] !== null))),
                'riders' => $rpos ? [['id' => $r['id'], 'name' => $r['name'], 'lat' => (float) rpv_pick($rpos, ['lat', 0], 0), 'lng' => (float) rpv_pick($rpos, ['lng', 1], 0), 'color' => $r['color'], 'status' => $r['status']]] : [],
                'path' => is_array($path) ? $path : null,
            ];
        }
    }
    if ($s['source'] === 'none' && $r['planUnits']) {
        $s['source'] = 'plan';
        $s['stops'] = array_map(fn($u) => $u + ['status' => 'pending', 'stop_id' => 0, 'done' => '', 'fail' => ''], $r['planUnits']);
        $s['summary'] = $r['route'] ? $r['route']['km'] . ' · ' . $r['route']['time'] . ($r['route']['improved'] >= 0.5 ? ' · ' . $r['route']['improved'] . '% shorter than nearest-first' : '') . (!empty($r['route']['late_fixed']) ? ' · ' . $r['route']['late_fixed'] . ' fewer late stops' : '') : '';
        $s['provider'] = $r['route']['provider'] ?? 'estimate';
    }
    if ($s['source'] === 'plan' || ($s['source'] === 'route' && !$s['map'])) {
        $s['map'] = ['depot' => $depot, 'riders' => $r['pos'] ? [['id' => $r['id'], 'name' => $r['name'], 'lat' => $r['pos']['lat'], 'lng' => $r['pos']['lng'], 'color' => $r['color'], 'status' => $r['status']]] : [], 'path' => null,
            'stops' => array_values(array_map(fn($u) => ['id' => $u['stop_id'] ?: $u['id'], 'seq' => $u['seq'], 'lat' => $u['lat'], 'lng' => $u['lng'], 'label' => $u['contact'] ?: $u['address'], 'status' => $u['status'], 'eta' => $u['eta']], array_filter($s['stops'], fn($u) => $u['lat'] !== null)))];
    }
    // Nearest-first from the rider's position (or the depot): straight-line distance
    $from = $r['pos'] ?: $depot;
    $near = [];
    foreach ($s['stops'] as $u) {
        if ($u['lat'] === null || in_array($u['status'], ['delivered', 'failed', 'returned'], true)) continue;
        $near[] = ['seq' => $u['seq'], 'label' => $u['contact'] ?: $u['address'], 'area' => $u['area'], 'm' => rpv_hav((float) $from['lat'], (float) $from['lng'], $u['lat'], $u['lng'])];
    }
    usort($near, fn($a, $b) => $a['m'] <=> $b['m']);
    $s['nearest'] = array_map(fn($n) => $n + ['dist' => rpv_km($n['m'])], $near);
    $s['nearFrom'] = $r['pos'] ? 'rider\'s last position' : ($depot['label'] ?? 'start');
    // Statistics, last 7 days
    $from = date('Y-m-d', strtotime($d['date'] . ' -6 days'));
    $st = rpv_arr(rpv_call('rp_riderStats', [$r['id'], $from, $d['date']], [], $notes));
    if ($st) {
        $cat = rpv_arr($st['by_category'] ?? []);
        $cats = [['On the way', (int) ($cat['onway_s'] ?? 0), 'onway'], ['Loading', (int) ($cat['loading_s'] ?? 0), 'loading'], ['Waiting', (int) ($cat['waiting_s'] ?? 0), 'waiting'], ['Break', (int) ($cat['break_s'] ?? 0), 'break']];
        $tot = max(1, array_sum(array_column($cats, 1)));
        $days = [];
        $byDate = [];
        foreach (rpv_arr($st['per_day'] ?? []) as $pd) { $pd = rpv_arr($pd); $byDate[(string) ($pd['date'] ?? '')] = $pd; }
        $maxW = 1;
        for ($i = 6; $i >= 0; $i--) {
            $dt = date('Y-m-d', strtotime($d['date'] . " -$i days"));
            $pd = $byDate[$dt] ?? [];
            $w = (int) ($pd['work_s'] ?? 0);
            $maxW = max($maxW, $w);
            $days[] = ['date' => $dt, 'dow' => date('D', strtotime($dt)), 'work' => $w, 'label' => rpv_dur($w), 'stops' => (int) ($pd['stops'] ?? 0),
                'seg' => ['onway' => (int) ($pd['onway_s'] ?? 0), 'loading' => (int) ($pd['loading_s'] ?? 0), 'waiting' => (int) ($pd['waiting_s'] ?? 0)]];
        }
        foreach ($days as &$day) { $day['h'] = (int) round($day['work'] * 100 / $maxW); }
        unset($day);
        $s['stats'] = ['cats' => array_map(fn($c) => ['label' => $c[0], 'dur' => rpv_dur($c[1]), 'pct' => (int) round($c[1] * 100 / $tot), 'cls' => $c[2]], $cats),
            'total' => rpv_dur(array_sum(array_column($cats, 1))), 'days' => $days, 'distance' => rpv_km($st['distance_m'] ?? 0),
            'delivered' => (int) ($st['delivered'] ?? 0), 'failed' => (int) ($st['failed'] ?? 0)];
    }
    return $s;
}

function rpv_hav(float $a1, float $o1, float $a2, float $o2): int
{
    if (function_exists('rp_haversineM')) return rp_haversineM($a1, $o1, $a2, $o2);
    $r = 6371000; $p1 = deg2rad($a1); $p2 = deg2rad($a2); $dp = deg2rad($a2 - $a1); $dl = deg2rad($o2 - $o1);
    $x = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return (int) round(2 * $r * asin(min(1, sqrt($x))));
}

/** Timeline rows positioned on a 06:00–21:00 axis. */
function rpv_timeline(array $rows, array $riders): array
{
    $t0 = 6 * 3600; $t1 = 21 * 3600; $span = $t1 - $t0;
    $sec = function ($v) { if ($v === null || $v === '') return null; $t = is_numeric($v) ? (int) $v : strtotime((string) $v); if (!$t) return null; return (int) date('G', $t) * 3600 + (int) date('i', $t) * 60 + (int) date('s', $t); };
    $pct = fn($s) => max(0, min(100, round(($s - $t0) * 100 / $span, 2)));
    $nowS = (int) date('G') * 3600 + (int) date('i') * 60;
    $out = ['hours' => array_map(fn($h) => ['h' => sprintf('%02d', $h), 'x' => round(($h * 3600 - $t0) * 100 / $span, 2)], range(6, 21, 1)), 'now' => $nowS > $t0 && $nowS < $t1 ? $pct($nowS) : null, 'rows' => []];
    $labels = ['onway' => 'On the way', 'loading' => 'Loading', 'waiting' => 'Waiting', 'break' => 'Break'];
    foreach ($rows as $row) {
        $row = rpv_arr($row);
        $id = (int) rpv_pick($row, ['driver_id', 'id'], 0);
        $blocks = [];
        foreach (rpv_arr($row['blocks'] ?? []) as $b) {
            $b = rpv_arr($b);
            $s = $sec($b['start'] ?? null); $e = $sec($b['end'] ?? null) ?? ($s !== null ? max($s + 300, $nowS) : null);
            if ($s === null) continue;
            $st = (string) ($b['state'] ?? 'onway');
            $blocks[] = ['state' => $st, 'label' => $labels[$st] ?? ucfirst($st), 'x' => $pct($s), 'w' => max(0.6, $pct($e) - $pct($s)), 'short' => ($pct($e) - $pct($s)) < 4.5, 'time' => rpv_hm($b['start']) . '–' . (empty($b['end']) ? 'now' : rpv_hm($b['end']))];
        }
        $stops = []; $openEtas = [];
        $all = rpv_arr($row['stops'] ?? []);
        usort($all, fn($a, $b) => ($sec($a['done_at'] ?? null) ?? $sec($a['eta'] ?? null) ?? 0) <=> ($sec($b['done_at'] ?? null) ?? $sec($b['eta'] ?? null) ?? 0));
        foreach ($all as $sp) {
            $sp = rpv_arr($sp);
            $t = $sec($sp['done_at'] ?? null) ?? $sec($sp['eta'] ?? null);
            $stt = (string) ($sp['status'] ?? 'pending');
            if ($t !== null && in_array($stt, ['pending', 'arrived'], true)) $openEtas[] = $t;
            if ($t === null || $t < $t0 || $t > $t1) continue;
            $title = '#' . (int) ($sp['seq'] ?? 0) . ' · ' . $stt . ' · ' . (rpv_hm($sp['done_at'] ?? '') ?: 'ETA ' . rpv_hm($sp['eta'] ?? ''));
            $last = $stops ? count($stops) - 1 : null;
            // Stops closer than ~8 minutes merge into one marker with a count, so rings do not pile up.
            if ($last !== null && $pct($t) - $stops[$last]['x'] < 0.9 && $stops[$last]['status'] === $stt) { $stops[$last]['n']++; $stops[$last]['title'] .= "\n" . $title; continue; }
            $stops[] = ['x' => $pct($t), 'seq' => (int) ($sp['seq'] ?? 0), 'status' => $stt, 'n' => 1, 'title' => $title];
        }
        // A planned or confirmed route that has not run yet shows as a dashed span (first to last ETA).
        if ($openEtas) {
            $ps = max($t0, min($openEtas) - 15 * 60); $pe = min($t1, max($openEtas) + 5 * 60);
            if ($pe > $ps) array_unshift($blocks, ['state' => 'planned', 'label' => 'Planned route', 'x' => $pct($ps), 'w' => max(0.6, $pct($pe) - $pct($ps)), 'short' => ($pct($pe) - $pct($ps)) < 6, 'time' => date('H:i', strtotime('today') + $ps) . '–' . date('H:i', strtotime('today') + $pe)]);
        }
        $r = $riders[$id] ?? null;
        $out['rows'][] = ['id' => $id, 'name' => (string) rpv_pick($row, ['name'], $r['name'] ?? 'Rider #' . $id), 'initials' => rpv_initials((string) rpv_pick($row, ['name'], $r['name'] ?? '?')),
            'vehicle' => $r['vehicle']['typeLabel'] ?? '', 'color' => $r['color'] ?? '#64748b', 'blocks' => $blocks, 'stops' => $stops,
            'work' => rpv_dur(array_sum(array_map(fn($b) => in_array($b['state'], ['break', 'planned'], true) ? 0 : $b['w'] * $span / 100, $blocks)))];
    }
    return $out;
}

/** Settings form model (field names mirror the settings JSON keys). */
function rpv_settingsForm(array $s, array $starts, string $def): array
{
    $speeds = rpv_arr($s['speeds'] ?? []) + ['bicycle' => 12, 'motorbike' => 24, 'car' => 20, 'van' => 18, 'truck' => 15];
    $vd = rpv_arr($s['vehicle_defaults'] ?? []);
    $defaults = ['bicycle' => [12, 40, 4], 'motorbike' => [30, 90, 8], 'car' => [150, 400, 20], 'van' => [600, 2500, 50], 'truck' => [2000, 9000, 120]];
    $veh = [];
    foreach ($defaults as $type => [$kg, $l, $n]) {
        $v = rpv_arr($vd[$type] ?? []);
        $veh[] = ['type' => $type, 'label' => ucfirst($type), 'speed' => (float) ($speeds[$type] ?? 20), 'max_kg' => (float) ($v['max_kg'] ?? $kg), 'max_l' => (float) ($v['max_l'] ?? $l), 'max_parcels' => (int) ($v['max_parcels'] ?? $n)];
    }
    $num = fn($k, $dflt) => isset($s[$k]) && is_numeric($s[$k]) ? $s[$k] + 0 : $dflt;
    return [
        'starts' => $starts, 'default' => $def, 'vehicles' => $veh, 'fill_mode' => (string) ($s['fill_mode'] ?? 'fill'),
        'revision' => (int) ($s['revision'] ?? 0),
        'fields' => [
            ['service_min_base', 'Minutes at each stop', $num('service_min_base', 4), 'Parking, finding the door, handing over', '0.5'],
            ['service_min_per_parcel', 'Extra minutes per parcel', $num('service_min_per_parcel', 1), 'Added for each parcel at the stop', '0.5'],
            ['road_factor', 'Road factor', $num('road_factor', 1.35), 'Straight-line distance × this = road estimate when Google is off', '0.05'],
            ['late_min', 'Late after (minutes)', $num('late_min', 15), 'Alert when a rider is this far behind the ETA', '1'],
            ['stuck_min', 'Stuck after (minutes)', $num('stuck_min', 12), 'Alert when a rider has not moved 100 m for this long', '1'],
            ['offline_min', 'Offline after (minutes)', $num('offline_min', 10), 'Alert when the rider app sent no position for this long', '1'],
            ['ping_min_s', 'Position every (seconds)', $num('ping_min_s', 20), 'The rider app sends at most one position this often…', '1'],
            ['ping_min_m', '…or every (metres)', $num('ping_min_m', 40), '…unless the rider moved this far', '1'],
        ],
    ];
}

/** Parcel drawer body: the shared rp_parcel_card.php partial when the engine ships it, else a local card. */
function rpv_parcelCardHtml(array $p): string
{
    $file = CDP_ROOT . '/views/sl/pages/partials/rp_parcel_card.php';
    if (is_file($file)) {
        ob_start();
        try { include $file; } catch (Throwable $e) { error_log('[route_planner] rp_parcel_card: ' . $e->getMessage()); }
        $out = (string) ob_get_clean();
        if (trim($out) !== '') return $out;
    }
    $p = rpv_parcel($p);
    $num = fn($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
    $dims = $p['l'] && $p['w'] && $p['h'] ? $num($p['l']) . ' × ' . $num($p['w']) . ' × ' . $num($p['h']) . ' cm' : 'Size not recorded';
    $val = $p['value'] ? (is_numeric($p['value']) && isset($p['value_cents']) ? number_format($p['value'] / 100, 2) : (string) $p['value']) : '';
    $html = '<div class="rp-pc"><div class="row between g8"><b class="mono fw5">' . h($p['tracking'] ?: ('#' . $p['order_id'])) . '</b>' . ($p['status'] ? '<span class="sl-pill plain">' . h($p['status']) . '</span>' : '') . '</div>';
    $html .= '<div class="rp-pc-grid"><span><small>Size</small><b>' . h($dims) . '</b></span><span><small>Weight</small><b>' . h($num($p['kg'])) . ' kg</b></span><span><small>Volume</small><b>' . h($num($p['volume_l'])) . ' L</b></span>' . ($val !== '' ? '<span><small>Value</small><b>' . h(trim($p['currency'] . ' ' . $val)) . '</b></span>' : '') . ($p['window'] ? '<span><small>Window</small><b>' . h($p['window']) . '</b></span>' : '') . '</div>';
    if ($p['contents']) $html .= '<div class="sm"><span class="mut">Contents</span> ' . h($p['contents']) . '</div>';
    if ($p['handling']) $html .= '<div class="row g6 wrap">' . implode('', array_map(fn($x) => '<i class="rp-hb h-' . h($x) . '">' . h(RPV_HANDLING[$x] ?? $x) . '</i>', $p['handling'])) . '</div>';
    if ($p['note']) $html .= '<div class="sm rp-note"><i class="ic ic-sm i-note" aria-hidden="true"></i> ' . h($p['note']) . '</div>';
    return $html . '</div>';
}

/** One stop / request card. $o: drag (bool), from (rider id or 0), routeStop (confirmed route stop), addRoute (route id for "add to route"), removeRoute. */
function rpv_unitCard(array $u, array $o = []): string
{
    $o += ['drag' => false, 'from' => 0, 'seq' => true, 'addRoute' => 0, 'removeRoute' => 0, 'move' => false];
    $label = '#' . $u['id'] . ' ' . ($u['contact'] ?: $u['address']);
    $st = (string) ($u['status'] ?? '');
    $stMap = ['delivered' => ['Delivered', 'delivered'], 'failed' => ['Failed', 'pending'], 'returned' => ['Returned', 'sorting'], 'arrived' => ['Arrived', 'transit'], 'pending' => ['', '']];
    $attrs = ' data-rp-unit="' . (int) $u['id'] . '" data-rp-from="' . (int) $o['from'] . '" data-rp-label="' . h($label) . '" data-area="' . h($u['area']) . '" data-handling="' . h(implode(',', $u['handling'])) . '" data-q="' . h(mb_strtolower($label . ' ' . $u['address'] . ' ' . $u['area'] . ' ' . implode(' ', array_column($u['parcels'], 'tracking')))) . '"';
    $html = '<article class="rp-u' . (!empty($u['late']) ? ' late' : '') . ($st && $st !== 'pending' ? ' st-' . h($st) : '') . ($u['reason'] ? ' noplace' : '') . '"' . $attrs . ($o['drag'] && !$u['reason'] ? ' draggable="true"' : '') . '>';
    if ($o['seq'] && !empty($u['seq'])) $html .= '<span class="rp-seq mono">' . (int) $u['seq'] . '</span>';
    $html .= '<div class="col grow rp-u-b"><div class="row g6 wrap"><b class="fw5">' . h($u['contact'] ?: 'Request #' . $u['id']) . '</b>' . ($u['prioLabel'] ? '<span class="sl-pill ' . ($u['priority'] >= 2 ? 'pending' : 'onway') . '">' . h($u['prioLabel']) . '</span>' : '') . (!empty($stMap[$st][0]) ? '<span class="sl-pill ' . $stMap[$st][1] . '">' . $stMap[$st][0] . '</span>' : '') . '</div>';
    $html .= '<span class="mut sm rp-addr">' . h(trim(($u['area'] ? $u['area'] . ' · ' : '') . $u['address'], ' ·')) . '</span>';
    $meta = [(int) $u['n'] . ' parcel' . ((int) $u['n'] === 1 ? '' : 's'), rtrim(rtrim(number_format($u['kg'], 1), '0'), '.') . ' kg', rtrim(rtrim(number_format($u['l'], 1), '0'), '.') . ' L'];
    if ($u['window']) $meta[] = 'window ' . $u['window'];
    if (!empty($u['leg'])) $meta[] = '+' . $u['leg'];
    $html .= '<span class="rp-meta mono">' . h(implode(' · ', $meta)) . (!empty($u['eta']) ? ' · <b class="' . (!empty($u['late']) ? 'rp-late' : '') . '">ETA ' . h($u['eta']) . '</b>' : '') . (!empty($u['done']) ? ' · done ' . h($u['done']) : '') . '</span>';
    if ($u['handling']) $html .= '<span class="row g6 wrap">' . implode('', array_map(fn($x) => '<i class="rp-hb h-' . h($x) . '">' . h(RPV_HANDLING[$x] ?? $x) . '</i>', $u['handling'])) . '</span>';
    if ($u['reason']) $html .= '<span class="rp-reason sm"><i class="ic ic-sm i-alert" aria-hidden="true"></i> ' . h($u['reason']) . '</span>';
    if (!empty($u['fail'])) $html .= '<span class="rp-reason sm">' . h($u['fail']) . '</span>';
    $html .= '</div><div class="rp-u-act">';
    if ($u['parcels']) $html .= '<button type="button" class="sl-btn ghost sm" data-rp-parcels="' . (int) $u['id'] . '" aria-label="Parcel details for ' . h($label) . '"><i class="ic ic-sm i-box" aria-hidden="true"></i></button>';
    if ($o['move'] && !$u['reason']) $html .= '<button type="button" class="sl-btn ghost sm" data-rp-move="' . (int) $u['id'] . '" aria-label="Move ' . h($label) . ' to another rider" title="Move to…"><i class="ic ic-sm i-arrow" aria-hidden="true"></i></button>';
    if ($o['addRoute'] && !$u['reason']) $html .= '<button type="button" class="sl-btn ghost sm" data-rp-add-stop="' . (int) $u['id'] . '" data-route="' . (int) $o['addRoute'] . '" title="Add to the selected rider\'s route" aria-label="Add ' . h($label) . ' to the selected route"><i class="ic ic-sm i-plus" aria-hidden="true"></i></button>';
    if ($o['removeRoute'] && !empty($u['stop_id']) && in_array($st, ['pending', 'arrived', ''], true)) $html .= '<button type="button" class="sl-btn ghost sm" data-rp-remove-stop="' . (int) $u['stop_id'] . '" data-route="' . (int) $o['removeRoute'] . '" data-rp-label="' . h($label) . '" aria-label="Remove ' . h($label) . ' from the route"><i class="ic ic-sm i-x" aria-hidden="true"></i></button>';
    $html .= '</div>';
    if ($u['parcels']) $html .= '<template data-rp-parcel-tpl="' . (int) $u['id'] . '"><div class="col g4 rp-pd-h"><b class="fw5">' . h($u['contact'] ?: 'Request #' . $u['id']) . '</b><span class="mut sm">' . h($u['address']) . ($u['phone'] ? ' · ' . h($u['phone']) : '') . '</span>' . ($u['note'] ? '<span class="sm rp-note">' . h($u['note']) . '</span>' : '') . '</div>' . implode('', array_map('rpv_parcelCardHtml', $u['parcels'])) . '</template>';
    return $html . '</article>';
}
