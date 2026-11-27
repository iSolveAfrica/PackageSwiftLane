<?php
/**
 * Rider app work-flow screens (design: M-RHome, M-RTasks, M-ROffer, M-RQueue, M-RMap, M-RNavPickup, M-RPickup, M-RDone).
 *
 * One "stop" model over the two real sources of rider work:
 *  - o<ID>  a shipment or pickup assigned by dispatch (cdb_add_order.driver_id), paid by the fee rules (sl_rdFee);
 *  - d<ID>  a customer door delivery (cdb_delivery_requests.driver_id), paid by the share stored on the request.
 * Riders cannot claim work themselves: dispatch (or a zone's default rider) assigns it. So the task list is
 * "assigned to me, not started" and Accept is the existing start action. Nothing here changes a job's state.
 */
require_once CDP_ROOT . '/views/sl/data/riders.php';
require_once CDP_ROOT . '/helpers/delivery_service.php';
if (is_file(CDP_ROOT . '/helpers/rider_money.php')) {
    require_once CDP_ROOT . '/helpers/rider_money.php';
}

/** The signed-in rider's row, or null for a staff preview. */
function ro_me()
{
    return sl_rdRiders()[(int) ($_SESSION['userid'] ?? 0)] ?? null;
}

/** On/off duty. The flag is cdb_users.rider_status; dispatch cannot assign door deliveries to an offline rider. */
function ro_duty($me): array
{
    $status = strtolower(trim((string) ($me->rider_status ?? '')));
    $since = '';
    if (sl_tableExists('cdb_rider_status_log')) {
        $last = sl_row('SELECT online, changed_at FROM cdb_rider_status_log WHERE user_id = :u ORDER BY id DESC', [':u' => (int) $me->id]);
        if ($last && (int) $last->online === ($status === 'offline' ? 0 : 1)) {
            $since = (string) $last->changed_at;
        }
    }
    return ['ready' => sl_columnExists('cdb_users', 'rider_status'), 'suspended' => $status === 'suspended' || !(int) $me->active, 'online' => !in_array($status, ['offline', 'suspended'], true) && (int) $me->active === 1, 'since' => $since];
}

/** An amount stored in cents: in the site's own format when it is the site currency. */
function ro_cents(int $cents, string $currency): string
{
    global $core;
    $site = strtoupper(trim((string) ($core->currency ?? '')));
    return ($site === '' || strtoupper($currency) === $site) ? sl_money($cents / 100) : dl_money($cents, $currency);
}

function ro_navUrl(int $riderId, string $address): string
{
    return function_exists('sl_riderNavUrl') ? sl_riderNavUrl($riderId, $address) : sl_riderNavigation($address);
}

function ro_tel(string $phone): string
{
    $p = preg_replace('/[^0-9+]/', '', $phone);
    return preg_match('/\d{3,}/', $p) ? 'tel:' . $p : '';
}

/**
 * Every stop of the rider that is open, plus those finished today.
 * state: new (assigned, not started) | active (on the way, or a pickup to collect) | done | failed.
 */
function ro_stops($me): array
{
    $id = (int) $me->id;
    $today = date('Y-m-d');
    $stops = [];
    $viaDoor = [];
    $scanned = [];
    if (sl_tableExists('cdb_rider_scans')) {
        foreach (sl_rows('SELECT module, order_id FROM cdb_rider_scans WHERE user_id = :u', [':u' => $id]) as $s) {
            $scanned[$s->module . ':' . (int) $s->order_id] = true;
        }
    }
    if (sl_tableExists('cdb_delivery_requests')) {
        $items = [];
        if (sl_tableExists('cdb_delivery_items')) {
            foreach (sl_rows('SELECT i.request_id, i.module, i.order_id, i.tracking FROM cdb_delivery_items i JOIN cdb_delivery_requests r ON r.id = i.request_id WHERE r.driver_id = :u', [':u' => $id]) as $it) {
                $items[(int) $it->request_id][] = $it;
                if ($it->module === 'air') $viaDoor[(int) $it->order_id] = true;
            }
        }
        $rows = sl_rows("SELECT * FROM cdb_delivery_requests WHERE driver_id = :u AND (state IN ('assigned','onroute','failed') OR (state = 'delivered' AND completed_at >= CURDATE())) ORDER BY FIELD(state,'delivered','onroute','assigned','failed'), COALESCE(completed_at, assigned_at, approved_at), id", [':u' => $id]);
        foreach ($rows as $r) {
            $rid = (int) $r->id;
            $parcels = [];
            foreach ($items[$rid] ?? [] as $it) {
                $parcels[] = ['module' => $it->module, 'id' => (int) $it->order_id, 'trk' => (string) $it->tracking, 'scanned' => isset($scanned[$it->module . ':' . (int) $it->order_id])];
            }
            $n = count($parcels) ?: count(json_decode((string) $r->parcel_json, true) ?: []);
            $origin = json_decode((string) $r->origin_json, true) ?: [];
            $state = ['assigned' => 'new', 'onroute' => 'active', 'failed' => 'failed', 'delivered' => 'done'][$r->state] ?? 'new';
            $label = ['assigned' => ['Not started', 'sorting'], 'onroute' => ['On the way', 'onway'], 'failed' => ['With dispatch', 'overdue'], 'delivered' => ['Delivered', 'delivered']][$r->state] ?? [ucfirst((string) $r->state), 'plain'];
            $stops[] = ['key' => 'd' . $rid, 'src' => 'request', 'id' => $rid, 'kind' => 'door', 'kindTxt' => 'Door delivery', 'pickup' => false,
                'name' => trim((string) $r->contact_name) ?: 'Customer', 'addr' => (string) $r->address, 'phone' => (string) $r->contact_phone, 'note' => (string) $r->instructions,
                'ref' => 'Delivery #' . $rid, 'parcels' => $parcels, 'n' => $n, 'kg' => null, 'state' => $state, 'st' => $label[0], 'cls' => $label[1],
                'fee' => (int) $r->amount_cents / 100, 'feeTxt' => ro_cents((int) $r->amount_cents, (string) $r->currency), 'pay' => (int) $r->rider_cents / 100, 'payTxt' => ro_cents((int) $r->rider_cents, (string) $r->currency),
                'pct' => (int) $r->amount_cents > 0 ? (int) round((int) $r->rider_cents * 100 / (int) $r->amount_cents) : 0, 'collect' => true, 'collected' => (int) $r->fee_collected === 1,
                'km' => (int) $r->distance_m > 0 ? round((int) $r->distance_m / 1000, 1) : null, 'from' => (string) ($origin['address'] ?? ''),
                'at' => $r->state === 'delivered' ? (string) $r->completed_at : '', 'since' => (string) ($r->assigned_at ?: $r->approved_at), 'receiver' => (string) ($r->receiver_name ?? ''),
                'proof' => 'PIN and photo', 'open' => sl_route('DeliveryJob', ['id' => $rid]),
                'navDest' => 'https://www.google.com/maps/dir/?' . http_build_query(['api' => 1, 'destination' => $r->lat . ',' . $r->lng, 'destination_place_id' => $r->place_id, 'travelmode' => 'driving'])];
        }
    }
    $s = sl_rdStats($today, $today, $id)[$id] ?? ['jobs' => []];
    $codes = function_exists('cdp_pickupCodeEnabled') ? cdp_pickupCodeEnabled() : true;
    foreach ($s['jobs'] as $x) {
        $j = $x['j'];
        if (isset($viaDoor[(int) $j->order_id])) continue; // counted once, as the door delivery
        $row = sl_rdJobRow($x);
        $pickup = (bool) $row['pickup'];
        $state = $row['state'] === 'done' ? 'done' : ($row['state'] === 'failed' ? 'failed' : ($pickup || $row['st'] === 'On route' ? 'active' : 'new'));
        $stops[] = ['key' => 'o' . $row['id'], 'src' => 'order', 'id' => $row['id'], 'kind' => $pickup ? 'pickup' : 'delivery', 'kindTxt' => $pickup ? 'Pickup' : 'Delivery', 'pickup' => $pickup,
            'name' => $row['cust'] === 'Contact not supplied' ? ($pickup ? 'Pickup contact not supplied' : 'Receiver not supplied') : $row['cust'], 'addr' => $row['addr'] === '—' ? '' : (string) $row['addr'], 'phone' => $row['phone'], 'note' => '',
            'ref' => $row['trk'], 'parcels' => [['module' => 'air', 'id' => $row['id'], 'trk' => $row['trk'], 'scanned' => isset($scanned['air:' . $row['id']])]], 'n' => 1, 'kg' => $row['kg'] > 0 ? $row['kg'] : null,
            'state' => $state, 'st' => $state === 'new' ? 'Not started' : $row['st'], 'cls' => $row['cls'],
            'fee' => (float) $x['f']['fee'], 'feeTxt' => $row['fee'], 'pay' => (float) $x['f']['rider'], 'payTxt' => $row['rid'], 'pct' => $row['pct'], 'collect' => false, 'collected' => false,
            'km' => null, 'from' => '', 'at' => $state === 'done' ? (string) $j->done_at : '', 'since' => '', 'receiver' => (string) ($row['receiver'] ?? ''),
            'proof' => $pickup ? 'Name of who handed it over' : ($codes ? 'Name and photo, PIN if set' : 'Name and photo'), 'open' => sl_route('RiderHome', ['job' => $row['id']]), 'navDest' => ''];
    }
    foreach ($stops as &$st) {
        $st['nav'] = $st['navDest'] !== '' && (!function_exists('sl_riderPref') || sl_riderPref($id, 'nav_app') !== 'waze') ? $st['navDest'] : ro_navUrl($id, $st['addr']);
        $st['tel'] = ro_tel($st['phone']);
        $st['wa'] = sl_riderMessageLink($st['phone']);
        $st['ini'] = sl_initials($st['name']);
        $st['atTxt'] = $st['at'] !== '' ? sl_date($st['at'], 'H:i') : '';
        $st['nTxt'] = $st['n'] . ' parcel' . ($st['n'] === 1 ? '' : 's');
        $st['scannedN'] = count(array_filter($st['parcels'], fn($p) => $p['scanned']));
        // Where the rider goes for this stop: a started door delivery or delivery opens its handover, anything else its detail.
        $st['offer'] = sl_route('RiderOffer', ['id' => $st['key']]);
        $st['navPage'] = sl_route('RiderNav', ['id' => $st['key']]);
        $st['donePage'] = sl_route('RiderDone', ['id' => $st['key']]);
        $st['href'] = $st['state'] === 'done' ? $st['donePage'] : ($st['state'] === 'new' ? $st['offer'] : ($st['state'] === 'failed' ? $st['open'] : $st['navPage']));
    }
    unset($st);
    // Run order: finished (oldest first), on the way, pickups to collect, then what has not started, then what is with dispatch.
    $rank = fn($st) => $st['state'] === 'done' ? 0 : ($st['state'] === 'active' ? ($st['pickup'] ? 2 : 1) : ($st['state'] === 'new' ? 3 : 4));
    usort($stops, fn($a, $b) => ($rank($a) <=> $rank($b)) ?: (strcmp($a['at'], $b['at'])) ?: ($a['id'] <=> $b['id']));
    return $stops;
}

/** Shared data of every rider work-flow screen. */
function ro_data($user, $core): array
{
    $me = ro_me();
    $d = ['isRider' => (bool) $me, 'name' => $me->name ?? '', 'first' => explode(' ', trim((string) ($me->name ?? '')) ?: 'rider')[0], 'ini' => sl_initials($me->name ?? '?'), 'unread' => 0];
    if (!$me) return $d;
    $id = (int) $me->id;
    $d['id'] = $id;
    $d['sub'] = implode(' · ', array_filter([ucfirst((string) ($me->vehicle_type ?? '')), (string) ($me->enrollment ?? ''), (string) ($me->zone ?? '')]));
    $d['duty'] = ro_duty($me);
    $d['unread'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0', [':u' => $id]);
    $stops = ro_stops($me);
    $d['stops'] = $stops;
    $pick = fn(string $state) => array_values(array_filter($stops, fn($s) => $s['state'] === $state));
    $d['done'] = $pick('done'); $d['active'] = $pick('active'); $d['new'] = $pick('new'); $d['failed'] = $pick('failed');
    $d['open'] = array_merge($d['active'], $d['new']);
    $d['next'] = $d['open'][0] ?? null;
    $d['parcelsOpen'] = array_sum(array_map(fn($s) => $s['n'], $d['open']));
    // Same ledger as the Earnings screen when it is installed, so the two never disagree.
    $today = date('Y-m-d');
    if (function_exists('sl_rmTrips')) {
        $trips = sl_rmTrips($id, $today, $today)['trips'];
        $d['earned'] = array_sum(array_map(fn($t) => $t['share'], $trips));
        $d['trips'] = count($trips);
    } else {
        $d['earned'] = array_sum(array_map(fn($s) => $s['pay'], $d['done']));
        $d['trips'] = count($d['done']);
    }
    $d['earnedTxt'] = sl_money($d['earned']);
    $d['openPay'] = sl_money(array_sum(array_map(fn($s) => $s['pay'], $d['open'])));
    $d['pct'] = (int) sl_rdFee((object) ['is_pickup' => 0], $me)['pct'];
    return $d;
}

/** One stop of the signed-in rider by its key (o12 / d5), or null. The key never reaches SQL. */
function ro_stop(array $d, string $key): ?array
{
    if (!preg_match('/^[od][0-9]{1,12}$/', $key)) return null;
    foreach ($d['stops'] ?? [] as $s) if ($s['key'] === $key) return $s;
    return null;
}

/** A finished stop from an earlier day (the trip summary can be opened from the earnings history). */
function ro_pastStop($me, string $key): ?array
{
    if (!preg_match('/^([od])([0-9]{1,12})$/', $key, $m)) return null;
    $id = (int) $me->id; $n = (int) $m[2];
    if ($m[1] === 'd') {
        if (!sl_tableExists('cdb_delivery_requests')) return null;
        $r = sl_row("SELECT * FROM cdb_delivery_requests WHERE id = :i AND driver_id = :u AND state = 'delivered'", [':i' => $n, ':u' => $id]);
        if (!$r) return null;
        $trk = sl_tableExists('cdb_delivery_items') ? array_map(fn($x) => ['trk' => $x->tracking], sl_rows('SELECT tracking FROM cdb_delivery_items WHERE request_id = :i', [':i' => $n])) : [];
        $cnt = count($trk) ?: count(json_decode((string) $r->parcel_json, true) ?: []);
        return ['key' => $key, 'src' => 'request', 'id' => $n, 'kind' => 'door', 'kindTxt' => 'Door delivery', 'pickup' => false, 'name' => trim((string) $r->contact_name) ?: 'Customer', 'addr' => (string) $r->address, 'ref' => 'Delivery #' . $n,
            'parcels' => $trk, 'n' => $cnt, 'nTxt' => $cnt . ' parcel' . ($cnt === 1 ? '' : 's'), 'kg' => null, 'state' => 'done', 'fee' => (int) $r->amount_cents / 100, 'feeTxt' => ro_cents((int) $r->amount_cents, (string) $r->currency), 'pay' => (int) $r->rider_cents / 100,
            'payTxt' => ro_cents((int) $r->rider_cents, (string) $r->currency), 'pct' => (int) $r->amount_cents > 0 ? (int) round((int) $r->rider_cents * 100 / (int) $r->amount_cents) : 0, 'collect' => true, 'collected' => (int) $r->fee_collected === 1,
            'km' => (int) $r->distance_m > 0 ? round((int) $r->distance_m / 1000, 1) : null, 'at' => (string) $r->completed_at, 'atTxt' => sl_date($r->completed_at, 'H:i'), 'receiver' => (string) ($r->receiver_name ?? ''), 'proof' => 'PIN and photo', 'ini' => sl_initials((string) $r->contact_name)];
    }
    $from = date('Y-m-d', strtotime('-120 day'));
    foreach (sl_rdStats($from, date('Y-m-d'), $id)[$id]['jobs'] ?? [] as $x) {
        if ((int) $x['j']->order_id !== $n || $x['st'][2] !== 'done') continue;
        $row = sl_rdJobRow($x);
        return ['key' => $key, 'src' => 'order', 'id' => $n, 'kind' => $row['pickup'] ? 'pickup' : 'delivery', 'kindTxt' => $row['pickup'] ? 'Pickup' : 'Delivery', 'pickup' => (bool) $row['pickup'], 'name' => $row['cust'], 'addr' => $row['addr'], 'ref' => $row['trk'],
            'parcels' => [['trk' => $row['trk']]], 'n' => 1, 'nTxt' => '1 parcel', 'kg' => $row['kg'] > 0 ? $row['kg'] : null, 'state' => 'done', 'fee' => (float) $x['f']['fee'], 'feeTxt' => $row['fee'], 'pay' => (float) $x['f']['rider'], 'payTxt' => $row['rid'], 'pct' => $row['pct'],
            'collect' => false, 'collected' => false, 'km' => null, 'at' => (string) $x['j']->done_at, 'atTxt' => sl_date($x['j']->done_at, 'H:i'), 'receiver' => (string) ($row['receiver'] ?? ''), 'proof' => '', 'ini' => sl_initials($row['cust'])];
    }
    return null;
}

/** Minutes between starting a stop and finishing it, from the recorded events; null when either time is missing. */
function ro_tripMinutes(array $stop): ?int
{
    $start = null;
    if ($stop['src'] === 'request' && sl_tableExists('cdb_delivery_events')) {
        $start = sl_scalar("SELECT MIN(created_at) FROM cdb_delivery_events WHERE request_id = :i AND state = 'onroute'", [':i' => $stop['id']], null);
    } elseif ($stop['src'] === 'order' && !$stop['pickup']) {
        $start = sl_scalar('SELECT MAX(t_date) FROM cdb_courier_track WHERE order_id = :i AND status_courier = 7', [':i' => $stop['id']], null);
    }
    if (!$start || $stop['at'] === '') return null;
    $min = (int) round((strtotime($stop['at']) - strtotime((string) $start)) / 60);
    return $min > 0 && $min < 24 * 60 ? $min : null;
}

/** The whole remaining run as one Google Maps link (Maps accepts up to 9 waypoints). */
function ro_runLink(array $open): string
{
    $addr = array_values(array_filter(array_map(fn($s) => trim($s['addr']), $open)));
    if (count($addr) < 2) return '';
    $addr = array_slice($addr, 0, 10);
    $dest = array_pop($addr);
    return 'https://www.google.com/maps/dir/?' . http_build_query(['api' => 1, 'destination' => $dest, 'waypoints' => implode('|', $addr), 'travelmode' => 'driving'], '', '&', PHP_QUERY_RFC3986);
}

function ro_embed(string $address): string
{
    return trim($address) === '' ? '' : 'https://maps.google.com/maps?' . http_build_query(['q' => $address, 'z' => 15, 'output' => 'embed'], '', '&', PHP_QUERY_RFC3986);
}

/* ---------- view pieces shared by the rider screens ---------- */

/** App bar actions on tab pages: messages and notifications. */
function ro_topActions(array $d): string
{
    return sl_mxIcon('message', 'Messages', sl_route('RiderChat')) . sl_mxIcon('bell', 'Notifications', sl_route('Notifications'), (int) ($d['unread'] ?? 0));
}

/**
 * Screen top. Phone ($ph): the design's app bar and .mx-body inside the phone frame (views/sl/phone/_start.php).
 * Computer: the shell-less mx-kit screen (helpers/sl_mx.php).
 */
function ro_open(bool $ph, string $title, string $sub = '', ?array $lead = null, string $actions = ''): string
{
    if ($ph) return sl_phoneTop($title, $sub, $lead, $actions) . '<main class="mx-body" id="sl-main" tabindex="-1">';
    return sl_mxScreenOpen($title, $sub, $lead, $actions, true);
}

function ro_close(bool $ph): string
{
    return $ph ? '</main>' : sl_mxScreenClose();
}

/** The honest state for a staff member who opens a rider screen. */
function ro_notRider(bool $ph, string $title): void
{
    echo ro_open($ph, $title, 'Rider app', ['back' => sl_route('Main')]);
    echo sl_mxEmpty('scooter', 'This is the rider app', 'Sign in as a rider to see assigned pickups and deliveries. Staff assign and follow rider work on the dispatch board.', 'Open the dispatch board', sl_route('RiderDispatch'));
    echo ro_close($ph);
}

/** Map on top with the floating header, then the bottom sheet (design: M-ROffer, M-RNavPickup, M-RJob). Phone only. */
function ro_mapTop(string $embed, string $navUrl, string $title, string $sub, string $back, string $right, string $tag = '', bool $tall = false): string
{
    $out = '<main class="mx-body" id="sl-main" tabindex="-1"><section class="rs-mtop' . ($tall ? ' tall' : '') . '">';
    $out .= '<section class="mx-map ro-map" style="--mh:' . ($tall ? 380 : 300) . 'px" aria-label="Map">';
    if ($embed !== '') {
        $out .= '<div class="ro-map-ph" data-rider-map-placeholder><i class="ic i-route" aria-hidden="true"></i><button type="button" class="mx-btn sec rb-sm" data-load-rider-map>Show on the map</button></div>'
            . '<iframe hidden id="da-route-map" title="Map of the stop address" src="about:blank" data-src="' . h($embed) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>';
    } else {
        $out .= '<div class="ro-map-ph"><i class="ic i-pin" aria-hidden="true"></i><span>No address on this job yet. Ask dispatch.</span></div>';
    }
    if ($navUrl !== '') $out .= '<div class="mx-mctl"><a class="mx-ib" href="' . h($navUrl) . '" target="_blank" rel="noopener" aria-label="Directions in your maps app"><i class="ic ic-sm i-route" aria-hidden="true"></i></a></div>';
    $out .= '</section><div class="rs-fh"><a class="rs-rb" href="' . h($back) . '" data-mx-back aria-label="Back"><i class="ic i-left" aria-hidden="true"></i></a><div class="rs-ft"><b>' . h($title) . '</b>' . ($sub !== '' ? '<small>' . h($sub) . '</small>' : '') . '</div>' . $right . '</div>';
    if ($tag !== '') $out .= '<span class="rs-mtag"><i class="ic ic-sm i-pin" aria-hidden="true"></i>' . h($tag) . '</span>';
    return $out . '</section><section class="rs-sheet"><i class="rs-grab" aria-hidden="true"></i>';
}

/** This week's share per day (Mon to Sun) from the earnings ledger, for the home chart. */
function ro_week(int $riderId): array
{
    if (!function_exists('sl_rmTrips')) return [];
    $mon = date('Y-m-d', strtotime('monday this week'));
    $today = date('Y-m-d');
    $by = [];
    foreach (sl_rmTrips($riderId, $mon, $today)['trips'] as $t) {
        $k = substr((string) $t['at'], 0, 10);
        $by[$k] = ['sum' => ($by[$k]['sum'] ?? 0) + $t['share'], 'n' => ($by[$k]['n'] ?? 0) + 1];
    }
    $max = 0.0;
    foreach ($by as $v) $max = max($max, $v['sum']);
    // Axis top: the best day rounded up to a round number.
    $step = $max > 0 ? pow(10, floor(log10($max))) : 1;
    $top = $max > 0 ? ceil($max / $step) * $step : 0;
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $k = date('Y-m-d', strtotime($mon . " +$i day"));
        $v = $by[$k]['sum'] ?? 0;
        $days[] = ['day' => date('D', strtotime($k)), 'date' => sl_date($k, 'D j M'), 'sum' => $v, 'txt' => sl_money($v), 'n' => $by[$k]['n'] ?? 0, 'h' => $top > 0 && $v > 0 ? max(6, (int) round($v * 100 / $top)) : 4, 'cls' => $k === $today ? 'now' : ($k > $today ? 'fut' : '')];
    }
    $yk = date('Y-m-d', strtotime('-1 day'));
    $y = $yk >= $mon ? ($by[$yk] ?? ['sum' => 0, 'n' => 0]) : null;
    if ($y === null) { $yt = sl_rmTrips($riderId, $yk, $yk)['trips']; $y = ['sum' => array_sum(array_map(fn($t) => $t['share'], $yt)), 'n' => count($yt)]; }
    return ['days' => $days, 'top' => $top, 'total' => sl_money(array_sum(array_map(fn($x) => $x['sum'], $days))), 'yesterday' => ['n' => $y['n'], 'txt' => sl_money($y['sum'])]];
}

/** The stop line of a card: <li> for .ra-st lists. */
function ro_stLine(string $cls, string $title, string $sub, string $right = ''): string
{
    return '<li class="' . h($cls) . '"><span class="ra-dot" aria-hidden="true"></span><div><b>' . h($title) . '</b>' . ($sub !== '' ? '<small>' . h($sub) . '</small>' : '') . '</div>' . ($right !== '' ? '<em>' . h($right) . '</em>' : '') . '</li>';
}

/** Lazy Google Maps preview (the existing rider map approach, dataJs/sl/rider_map.js): nothing loads until the rider asks. */
function ro_map(string $embed, string $label, string $navUrl, string $title): string
{
    if ($embed === '') return '';
    $out = '<section class="mx-map ro-map" aria-label="' . h($title) . '"><span class="mx-ml">' . h($label) . '</span>';
    $out .= '<div class="ro-map-ph" data-rider-map-placeholder><i class="ic i-route" aria-hidden="true"></i><button type="button" class="mx-btn sec rb-sm" data-load-rider-map>Show on the map</button></div>';
    $out .= '<iframe hidden id="da-route-map" title="' . h($title) . '" src="about:blank" data-src="' . h($embed) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>';
    if ($navUrl !== '') $out .= '<div class="mx-mctl"><a class="mx-ib" href="' . h($navUrl) . '" target="_blank" rel="noopener" aria-label="Directions in your maps app"><i class="ic ic-sm i-route" aria-hidden="true"></i></a></div>';
    return $out . '</section>';
}
