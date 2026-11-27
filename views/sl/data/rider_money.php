<?php
/**
 * Data for the rider app's earnings, wallet and account screens (RiderEarnings, RiderWallet, RiderQuests,
 * RiderShifts, RiderChat, RiderSafety, RiderAccount, RiderRatings). Everything is scoped to the signed-in
 * rider ($_SESSION['userid']); a staff preview gets isRider = false and no rider data.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/helpers/rider_money.php';
require_once CDP_ROOT . '/views/sl/data/riders.php';

/** Who is looking: the rider row, or isRider = false for a staff preview. */
function sl_rmCtx(): array
{
    $id = (int) ($_SESSION['userid'] ?? 0);
    $me = sl_rdRiders()[$id] ?? null;
    $d = ['id' => $id, 'isRider' => (bool) $me, 'me' => $me, 'name' => $me->name ?? '', 'ini' => sl_initials($me->name ?? '?'), 'unread' => 0];
    if ($me) {
        $d['unread'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0', [':u' => $id]);
        $d['veh'] = trim((string) ($me->vehicle_type ?: ''));
        $d['sub'] = $me->name . ($d['veh'] !== '' ? ' · ' . strtolower($d['veh']) : '');
    }
    return $d;
}

/**
 * Opens a screen. On a phone (views/sl/phone/Rider*.php sets \$GLOBALS['slRmPhone']) the body goes into the
 * design frame's .mx-body; on a computer it is the shell-less mx-kit page. Same arguments as sl_mxScreenOpen().
 */
function sl_rmOpen(string $title, string $sub = '', ?array $lead = null, string $actions = '', bool $wide = true): string
{
    if (!empty($GLOBALS['slRmPhone'])) {
        require_once CDP_ROOT . '/helpers/sl_phone.php';
        return '<link rel="stylesheet" href="' . h(cdp_asset('assets/swiftlane/pages/RiderMoney.css')) . '">' . sl_phoneTop($title, $sub, $lead, $actions) . '<main class="mx-body rm" id="sl-main" tabindex="-1">';
    }
    return sl_mxScreenOpen($title, $sub, $lead, $actions, $wide);
}

function sl_rmClose(): string
{
    return !empty($GLOBALS['slRmPhone']) ? '</main>' : sl_mxScreenClose();
}

/** The screen a staff member sees instead of a rider's data. */
function sl_rmNotRider(): string
{
    return sl_mxEmpty('scooter', 'This is the rider app', 'Sign in as a rider to see earnings, payouts and the account. Staff manage riders from the Riders pages.', 'Open riders', sl_route('Drivers'));
}

/** Link to the finished-trip screen. */
function sl_rmTripHref(array $t): string
{
    if ($t['k'] === 'd') {
        return sl_route('DeliveryJob', ['id' => $t['id']]);
    }
    return is_file(CDP_ROOT . '/rider_done.php') ? sl_route('RiderDone', ['id' => $t['id'], 'k' => 'o']) : sl_route('RiderQueue');
}

function sl_rmTripRow(array $t): array
{
    return ['href' => sl_rmTripHref($t), 'ini' => sl_initials($t['name']), 'name' => $t['name'], 'line' => $t['kind'] . ($t['km'] !== null ? ' · ' . $t['km'] . ' km' : '') . ($t['addr'] !== '' ? ' · ' . $t['addr'] : ''),
        'ref' => $t['ref'] . ' · ' . sl_date($t['at'], 'D j M H:i'), 'share' => sl_money($t['share']), 'fee' => 'of ' . sl_money($t['fee'])];
}

function sl_riderEarningsData($user, $core): array
{
    $d = sl_rmCtx();
    if (!$d['isRider']) {
        return $d;
    }
    $id = $d['id'];
    $thisMon = date('Y-m-d', strtotime('monday this week'));
    $w = (string) ($_GET['week'] ?? '');
    $ts = preg_match('/^\d{4}-\d{2}-\d{2}$/', $w) ? strtotime($w) : false;
    $mon = $ts ? date('Y-m-d', strtotime('monday this week', $ts)) : $thisMon;
    if ($mon > $thisMon) {
        $mon = $thisMon;
    }
    $sun = date('Y-m-d', strtotime($mon . ' +6 day'));
    $today = date('Y-m-d');
    $cur = sl_rmTrips($id, $mon, $sun);
    $prevMon = date('Y-m-d', strtotime($mon . ' -7 day'));
    $prev = sl_rmTrips($id, $prevMon, date('Y-m-d', strtotime($mon . ' -1 day')));
    $sum = fn(array $trips, string $k = 'share') => array_sum(array_column($trips, $k));
    $total = $sum($cur['trips']);
    $prevTotal = $sum($prev['trips']);
    $n = count($cur['trips']);

    $byDay = [];
    foreach ($cur['trips'] as $t) {
        $k = substr($t['at'], 0, 10);
        $byDay[$k] = ['v' => ($byDay[$k]['v'] ?? 0) + $t['share'], 'n' => ($byDay[$k]['n'] ?? 0) + 1];
    }
    $max = max(1, max(array_column($byDay, 'v') ?: [0]));
    $day = (string) ($_GET['day'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || $day < $mon || $day > $sun) {
        $day = '';
    }
    $cols = [];
    for ($i = 0; $i < 7; $i++) {
        $k = date('Y-m-d', strtotime("$mon +$i day"));
        $v = $byDay[$k]['v'] ?? 0;
        $c = $byDay[$k]['n'] ?? 0;
        $fut = $k > $today;
        $cols[] = ['l' => date('D', strtotime($k)), 'v' => $c ? sl_money($v) : '—', 'h' => 'height:' . max(4, sl_pct($v, $max)) . '%', 'cls' => ($k === $today ? 'now' : ($fut ? 'fut' : '')) . ($k === $day ? ' sel' : ''),
            'aria' => date('l', strtotime($k)) . ': ' . ($c ? sl_money($v) . ', ' . $c . ($c === 1 ? ' trip' : ' trips') : ($fut ? 'not yet' : 'no trips')), 'href' => sl_route('RiderEarnings', ['week' => $mon] + ($k === $day ? [] : ['day' => $k]))];
    }

    // Breakdown by what was actually paid: fee-rule jobs (deliveries, pickups) and stored door-delivery shares.
    $groups = ['Delivery' => ['n' => 0, 'fee' => 0, 'share' => 0, 'pct' => 0], 'Pickup' => ['n' => 0, 'fee' => 0, 'share' => 0, 'pct' => 0], 'Door delivery' => ['n' => 0, 'fee' => 0, 'share' => 0, 'pct' => 0]];
    foreach ($cur['trips'] as $t) {
        $g = &$groups[$t['kind']];
        $g['n']++; $g['fee'] += $t['fee']; $g['share'] += $t['share']; $g['pct'] = $t['pct'] ?? 0;
        unset($g);
    }
    $lines = [];
    $labels = ['Delivery' => 'Delivery fees', 'Pickup' => 'Pickup fees', 'Door delivery' => 'Customer door deliveries'];
    foreach ($groups as $k => $g) {
        if (!$g['n']) {
            continue;
        }
        $lines[] = ['l' => $labels[$k] . ' · ' . $g['n'] . ($k === 'Door delivery' ? ' · your share of ' : ' · your ' . (float) $g['pct'] . '% of ') . sl_money($g['fee']), 'v' => sl_money($g['share'])];
    }

    $list = $day !== '' ? array_values(array_filter($cur['trips'], fn($t) => substr($t['at'], 0, 10) === $day)) : $cur['trips'];
    $bal = sl_rmBalance($id);
    $quest = null;
    foreach (sl_rmQuestRows($id) as $q) {
        if ($q['state'] === 'live') { $quest = $q; break; }
    }
    return $d + [
        'mon' => $mon, 'isThisWeek' => $mon === $thisMon, 'span' => sl_date($mon, 'j M') . ' – ' . sl_date($sun, 'j M'), 'spanSub' => $mon === $thisMon ? 'This week' : ($mon === date('Y-m-d', strtotime($thisMon . ' -7 day')) ? 'Last week' : sl_date($mon, 'Y')),
        'prevHref' => sl_route('RiderEarnings', ['week' => $prevMon]), 'nextHref' => sl_route('RiderEarnings', ['week' => date('Y-m-d', strtotime($mon . ' +7 day'))]),
        'total' => sl_money($total), 'prevTotal' => sl_money($prevTotal), 'hasPrev' => $prevTotal > 0, 'delta' => $prevTotal > 0 ? (int) round(($total - $prevTotal) / $prevTotal * 100) : 0,
        'n' => $n, 'nDelta' => $n - count($prev['trips']), 'avg' => $n ? sl_money($total / $n) : '—', 'gross' => sl_money($sum($cur['trips'], 'fee')), 'failed' => $cur['failed'], 'open' => $cur['open'],
        'activeDays' => count($byDay),
        'cols' => $cols, 'lines' => $lines, 'skipped' => $cur['skipped'], 'day' => $day, 'dayLabel' => $day !== '' ? sl_date($day, 'l j M') : '',
        'trips' => array_map('sl_rmTripRow', array_slice($list, 0, 40)), 'tripCount' => count($list), 'allHref' => sl_route('RiderEarnings', ['week' => $mon]),
        'owed' => sl_money($bal['owed']), 'owedRaw' => $bal['owed'], 'quest' => $quest,
    ];
}

function sl_rmRequestRow($r): array
{
    $map = ['requested' => ['Waiting for the office', 'pending'], 'approved' => ['Approved · being paid', 'onway'], 'paid' => ['Paid', 'paid'], 'declined' => ['Declined', 'overdue'], 'cancelled' => ['Cancelled', 'plain']];
    [$label, $cls] = $map[$r->status] ?? [ucfirst((string) $r->status), 'plain'];
    return ['id' => (int) $r->id, 'amount' => sl_money($r->amount), 'status' => $r->status, 'label' => $label, 'cls' => $cls, 'at' => sl_date($r->created_at, 'D j M H:i'), 'note' => (string) ($r->note ?? ''), 'staffNote' => (string) ($r->staff_note ?? ''),
        'when' => $r->status === 'paid' && $r->paid_at ? 'paid ' . sl_date($r->paid_at, 'j M') : ($r->decided_at ? sl_date($r->decided_at, 'j M') : '')];
}

/** The payout account a rider saved (cdb_rider_prefs payout_*), display-safe. */
function sl_rmPayoutAccount(int $id): array
{
    $m = sl_riderPref($id, 'payout_method', '');
    $labels = ['momo' => 'Mobile money', 'bank' => 'Bank account', 'cash' => 'Cash at the office'];
    $number = sl_riderPref($id, 'payout_number', '');
    return ['set' => isset($labels[$m]), 'method' => $m, 'label' => $labels[$m] ?? '', 'provider' => sl_riderPref($id, 'payout_provider', ''), 'name' => sl_riderPref($id, 'payout_name', ''), 'number' => $number, 'masked' => sl_rmMask($number), 'labels' => $labels];
}

function sl_riderWalletData($user, $core): array
{
    $d = sl_rmCtx();
    if (!$d['isRider']) {
        return $d;
    }
    $id = $d['id'];
    $bal = sl_rmBalance($id);
    $history = [];
    $paidMonth = 0.0;
    $paidMonthN = 0;
    $monthStart = date('Y-m-01 00:00:00');
    if (sl_tableExists('cdb_rider_settlements')) {
        foreach (sl_rows('SELECT * FROM cdb_rider_settlements WHERE driver_id = :d ORDER BY COALESCE(paid_at, created_at) DESC LIMIT 30', [':d' => $id]) as $s) {
            $paid = $s->status === 'paid';
            if ($paid && (string) $s->paid_at >= $monthStart) { $paidMonth += (float) $s->rider_share; $paidMonthN++; }
            $history[] = ['sort' => (string) ($s->paid_at ?: $s->created_at), 'icon' => 'download', 'title' => 'Settlement', 'sub' => sl_date($s->period_start, 'j M') . ' – ' . sl_date($s->period_end, 'j M') . ' · ' . ((int) $s->deliveries + (int) $s->pickups) . ' trips' . ($paid ? ' · paid ' . sl_date($s->paid_at, 'D j M') : ''),
                'ref' => 'ST-' . str_pad((string) (int) $s->id, 5, '0', STR_PAD_LEFT), 'pill' => $paid ? 'Paid' : 'Recorded, not paid yet', 'cls' => $paid ? 'paid' : 'pending', 'amount' => sl_money($s->rider_share), 'note' => (string) ($s->note ?? '')];
        }
    }
    $requests = sl_rmPayoutRequests($id, false, 30);
    foreach ($requests as $r) {
        $x = sl_rmRequestRow($r);
        if ($r->status === 'paid' && (string) $r->paid_at >= $monthStart) { $paidMonth += (float) $r->amount; $paidMonthN++; }
        $history[] = ['sort' => (string) ($r->paid_at ?: ($r->decided_at ?: $r->created_at)), 'icon' => 'send', 'title' => 'Payout request', 'sub' => 'Asked ' . $x['at'] . ($x['when'] !== '' ? ' · ' . $x['when'] : ''), 'ref' => 'PR-' . str_pad((string) $x['id'], 5, '0', STR_PAD_LEFT),
            'pill' => $x['label'], 'cls' => $x['cls'], 'amount' => $x['amount'], 'note' => $x['staffNote']];
    }
    usort($history, fn($a, $b) => strcmp($b['sort'], $a['sort']));
    $avail = $bal['available'];
    $chips = [];
    foreach ([1, 0.5, 0.25] as $f) {
        $v = round($avail * $f, 2);
        if ($v >= 1) $chips[] = ['v' => number_format($v, 2, '.', ''), 'l' => $f === 1 ? 'All' : ($f === 0.5 ? 'Half' : 'A quarter')];
    }
    return $d + ['ready' => sl_tableExists('cdb_rider_payout_requests'), 'owed' => sl_money($bal['owed']), 'owedRaw' => $bal['owed'], 'available' => sl_money($avail), 'availableRaw' => $avail, 'availableInput' => number_format($avail, 2, '.', ''),
        'earned' => sl_money($bal['earned']), 'paid' => sl_money($bal['paid']), 'pending' => sl_money($bal['pending']), 'pendingRaw' => $bal['pending'], 'open' => $bal['open'] ? sl_rmRequestRow($bal['open']) : null,
        'paidMonth' => sl_money($paidMonth), 'paidMonthN' => $paidMonthN, 'chips' => $chips, 'history' => array_slice($history, 0, 30), 'account' => sl_rmPayoutAccount($id), 'symbol' => (string) ($core->for_symbol ?? '')];
}

/** Quests with this rider's progress. state: live | soon | earned | missed. */
function sl_rmQuestRows(int $id): array
{
    $quests = sl_rmQuests();
    if (!$quests) {
        return [];
    }
    $from = min(array_map(fn($q) => substr((string) $q->starts_at, 0, 10), $quests));
    $trips = sl_rmTrips($id, $from, date('Y-m-d'))['trips'];
    $now = date('Y-m-d H:i:s');
    $out = [];
    foreach ($quests as $q) {
        $done = count(array_filter($trips, fn($t) => $t['at'] >= $q->starts_at && $t['at'] <= $q->ends_at));
        $target = (int) $q->target_trips;
        $hit = $done >= $target;
        $state = $q->starts_at > $now ? 'soon' : ($hit ? 'earned' : ($q->ends_at < $now ? 'missed' : 'live'));
        $out[] = ['id' => (int) $q->id, 'title' => $q->title, 'target' => $target, 'done' => min($done, $target), 'left' => max(0, $target - $done), 'reward' => sl_money($q->reward), 'pct' => min(100, sl_pct($done, max(1, $target))), 'state' => $state,
            'span' => sl_date($q->starts_at, 'D j M') . ' – ' . sl_date($q->ends_at, 'D j M H:i'), 'endsIso' => date('c', strtotime((string) $q->ends_at)), 'startsIso' => date('c', strtotime((string) $q->starts_at)),
            'pill' => ['live' => ['Live', 'onway'], 'soon' => ['Starts ' . sl_date($q->starts_at, 'D j M'), 'plain'], 'earned' => ['Target reached', 'paid'], 'missed' => ['Ended', 'plain']][$state]];
    }
    $order = ['live' => 0, 'soon' => 1, 'earned' => 2, 'missed' => 3];
    usort($out, fn($a, $b) => $order[$a['state']] <=> $order[$b['state']]);
    return $out;
}

function sl_riderQuestsData($user, $core): array
{
    $d = sl_rmCtx();
    if (!$d['isRider']) {
        return $d;
    }
    $id = $d['id'];
    $mon = date('Y-m-d', strtotime('monday this week'));
    $today = date('Y-m-d');
    $wk = sl_rmTrips($id, $mon, $today);
    $days = [];
    $byDay = array_count_values(array_map(fn($t) => substr($t['at'], 0, 10), $wk['trips']));
    for ($i = 0; $i < 7; $i++) {
        $k = date('Y-m-d', strtotime("$mon +$i day"));
        $days[] = ['l' => date('D', strtotime($k)), 'n' => $byDay[$k] ?? 0, 'cls' => $k === $today ? 'now' : ($k > $today ? 'fut' : ''), 'on' => !empty($byDay[$k])];
    }
    $rules = sl_rdRules();
    $extra = [];
    foreach (['bonus' => 'Bonus', 'penalty' => 'Deduction', 'per_km' => 'Distance'] as $type => $kind) {
        foreach ($rules[$type] ?? [] as $r) {
            $extra[] = ['kind' => $kind, 'label' => $r->label, 'amount' => sl_money($r->amount), 'icon' => $type === 'penalty' ? 'alert' : ($type === 'per_km' ? 'route' : 'star')];
        }
    }
    return $d + ['ready' => sl_tableExists('cdb_rider_quests'), 'quests' => sl_rmQuestRows($id), 'weekTrips' => count($wk['trips']), 'weekEarned' => sl_money(array_sum(array_column($wk['trips'], 'share'))), 'failed' => $wk['failed'], 'open' => $wk['open'],
        'days' => $days, 'activeDays' => count($byDay), 'extra' => $extra, 'pct' => (float) $wk['pct']];
}

/** Places a rider can pick for a block: their zone, the company's offices, or anywhere. */
function sl_rmHubs($me): array
{
    $hubs = [];
    if (trim((string) ($me->zone ?? '')) !== '') {
        $hubs[] = trim((string) $me->zone);
    }
    if (sl_tableExists('cdb_offices')) {
        foreach (sl_rows('SELECT name_off FROM cdb_offices ORDER BY name_off LIMIT 40') as $o) {
            $n = trim((string) $o->name_off);
            // Office names that lost their characters in an import (all "?") are not offered.
            if ($n !== '' && !preg_match('/^[?\s]+$/', $n) && !in_array($n, $hubs, true)) $hubs[] = mb_substr($n, 0, 120);
        }
    }
    return $hubs;
}

function sl_riderShiftsData($user, $core): array
{
    $d = sl_rmCtx();
    if (!$d['isRider']) {
        return $d;
    }
    $id = $d['id'];
    $today = date('Y-m-d');
    $last = date('Y-m-d', strtotime('+13 day'));
    $sel = (string) ($_GET['day'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sel) || $sel < $today || $sel > $last) {
        $sel = $today;
    }
    $shifts = sl_rmShifts($id, $today, $last);
    $mins = fn($s) => (strtotime($s->day . ' ' . $s->end_time) - strtotime($s->day . ' ' . $s->start_time)) / 60;
    $hours = fn(float $m) => rtrim(rtrim(number_format($m / 60, 1), '0'), '.') . ' h';
    $byDay = [];
    foreach ($shifts as $s) {
        $byDay[$s->day][] = $s;
    }
    $days = [];
    for ($i = 0; $i < 14; $i++) {
        $k = date('Y-m-d', strtotime("+$i day"));
        $days[] = ['k' => $k, 'l' => date('D', strtotime($k)), 'n' => date('j', strtotime($k)), 'cls' => trim(($k === $today ? 'today' : '') . ($k === $sel ? ' on' : '')), 'bk' => !empty($byDay[$k]), 'href' => sl_route('RiderShifts', ['day' => $k]), 'aria' => date('l j F', strtotime($k)) . (!empty($byDay[$k]) ? ', ' . count($byDay[$k]) . ' booked' : '')];
    }
    $now = time();
    $rows = [];
    $weekEnd = date('Y-m-d', strtotime('sunday this week'));
    $weekMin = 0; $weekN = 0; $allMin = 0;
    foreach ($shifts as $s) {
        $start = strtotime($s->day . ' ' . $s->start_time);
        $end = strtotime($s->day . ' ' . $s->end_time);
        if ($end <= $now) {
            continue;
        }
        $m = $mins($s);
        $allMin += $m;
        if ($s->day <= $weekEnd) { $weekMin += $m; $weekN++; }
        $rows[] = ['id' => (int) $s->id, 'dow' => date('D', $start), 'dom' => date('j', $start), 'hub' => $s->hub !== '' ? $s->hub : 'Any area', 'time' => date('H:i', $start) . ' – ' . date('H:i', $end), 'len' => $hours($m), 'running' => $start <= $now,
            'when' => $start <= $now ? 'On now · ends ' . date('H:i', $end) : ($s->day === $today ? 'Today' : ($s->day === date('Y-m-d', strtotime('+1 day')) ? 'Tomorrow' : date('l j M', $start))), 'startIso' => date('c', $start), 'soon' => $start > $now && $start - $now < 6 * 3600,
            'nav' => $s->hub !== '' ? sl_riderNavUrl($id, $s->hub) : '', 'sel' => $s->day === $sel];
    }
    return $d + ['ready' => sl_tableExists('cdb_rider_shifts'), 'days' => $days, 'sel' => $sel, 'selLabel' => sl_date($sel, 'l j M'), 'selIsToday' => $sel === $today, 'rows' => $rows, 'weekHours' => $hours($weekMin), 'weekN' => $weekN, 'allHours' => $hours($allMin), 'allN' => count($rows),
        'hubs' => sl_rmHubs($d['me']), 'selCount' => count(array_filter($rows, fn($r) => $r['sel'])), 'minStart' => $sel === $today ? date('H:i') : '00:00'];
}

/** Company numbers a rider can reach dispatch on (cdb_settings), as call / WhatsApp links. */
function sl_rmDispatchContacts($core): array
{
    $out = [];
    foreach (['c_phone' => 'Office line', 'cell_phone' => 'Dispatch mobile'] as $k => $label) {
        $p = trim((string) ($core->$k ?? ''));
        if ($p === '' || sl_rmTel($p) === '' || in_array($p, array_column($out, 'phone'), true)) {
            continue;
        }
        $out[] = ['label' => $label, 'phone' => $p, 'tel' => sl_rmTel($p), 'wa' => sl_riderMessageLink($p)];
    }
    return $out;
}

/** Customers of the rider's open jobs: the only people a rider needs to reach while working. */
function sl_rmOpenStops(int $id): array
{
    $stops = [];
    if (sl_tableExists('cdb_delivery_requests')) {
        foreach (sl_rows("SELECT id, state, contact_name, contact_phone, address FROM cdb_delivery_requests WHERE driver_id = :u AND state IN ('assigned','onroute') ORDER BY FIELD(state,'onroute','assigned'), assigned_at, id LIMIT 30", [':u' => $id]) as $r) {
            $stops[] = ['name' => trim((string) $r->contact_name) ?: 'Customer', 'phone' => (string) $r->contact_phone, 'addr' => (string) $r->address, 'ref' => 'Delivery #' . (int) $r->id, 'state' => $r->state === 'onroute' ? 'On the way' : 'Assigned', 'cls' => $r->state === 'onroute' ? 'onway' : 'pending', 'href' => sl_route('DeliveryJob', ['id' => (int) $r->id]), 'onroute' => $r->state === 'onroute'];
        }
    }
    $today = date('Y-m-d');
    foreach (sl_rdStats($today, $today, $id)[$id]['jobs'] ?? [] as $x) {
        if ($x['st'][2] !== 'open') {
            continue;
        }
        $j = $x['j'];
        $stops[] = ['name' => trim((string) $j->cust) ?: 'Customer', 'phone' => (string) ($j->cust_phone ?? ''), 'addr' => (string) ($j->addr ?: ''), 'ref' => $j->order_prefix . $j->order_no, 'state' => $x['st'][0], 'cls' => $x['st'][1], 'href' => sl_route('RiderQueue'), 'onroute' => (int) $j->status_courier === 7];
    }
    foreach ($stops as &$s) {
        $s['ini'] = sl_initials($s['name']);
        $s['tel'] = sl_rmTel($s['phone']);
        $s['wa'] = sl_riderMessageLink($s['phone']);
    }
    unset($s);
    usort($stops, fn($a, $b) => (int) $b['onroute'] <=> (int) $a['onroute']);
    return array_slice($stops, 0, 30);
}

function sl_riderChatData($user, $core): array
{
    $d = sl_rmCtx();
    if (!$d['isRider']) {
        return $d;
    }
    return $d + ['dispatch' => sl_rmDispatchContacts($core), 'email' => filter_var(trim((string) ($core->site_email ?? '')), FILTER_VALIDATE_EMAIL) ?: '', 'stops' => sl_rmOpenStops($d['id']), 'site' => (string) ($core->site_name ?? 'the office'),
        'quick' => ["I'm outside", 'Running 5 minutes late', "I'm at the gate", "I can't find the address. Please send a landmark.", 'Is someone available to receive the parcel?']];
}

const SL_RM_CHECKLIST = ['helmet' => 'Helmet strapped and visor clean', 'vest' => 'Reflective vest on', 'bike' => 'Lights, horn and brakes work', 'phone' => 'Phone mounted, not in hand'];

function sl_rmEmergencyContact(int $id): array
{
    $phone = sl_riderPref($id, 'ec_phone', '');
    $name = sl_riderPref($id, 'ec_name', '');
    return ['set' => $name !== '' && sl_rmTel($phone) !== '', 'name' => $name, 'phone' => $phone, 'relation' => sl_riderPref($id, 'ec_relation', ''), 'tel' => sl_rmTel($phone), 'ini' => sl_initials($name ?: '?'), 'sms' => sl_rmTel($phone) !== '' ? 'sms:' . preg_replace('/[^0-9+]/', '', $phone) : ''];
}

function sl_riderSafetyData($user, $core): array
{
    $d = sl_rmCtx();
    if (!$d['isRider']) {
        return $d;
    }
    $id = $d['id'];
    $chk = json_decode((string) sl_riderPref($id, 'checklist', ''), true);
    $doneToday = is_array($chk) && ($chk['d'] ?? '') === date('Y-m-d') ? array_values(array_intersect(array_keys(SL_RM_CHECKLIST), (array) ($chk['items'] ?? []))) : [];
    $emergency = preg_replace('/[^0-9+]/', '', (string) sl_riderPref(0, 'emergency_number', ''));
    $stops = sl_rmOpenStops($id);
    $current = null;
    foreach ($stops as $s) {
        if ($s['onroute']) { $current = $s; break; }
    }
    return $d + ['ready' => sl_tableExists('cdb_rider_prefs'), 'emergency' => $emergency, 'dispatch' => sl_rmDispatchContacts($core), 'contact' => sl_rmEmergencyContact($id), 'checklist' => SL_RM_CHECKLIST, 'checked' => $doneToday, 'current' => $current, 'openStops' => count($stops),
        'plate' => trim((string) ($d['me']->enrollment ?? '')), 'site' => (string) ($core->site_name ?? 'the office')];
}

/** Real performance numbers of a rider over the last $days days. Nothing here is a customer rating. */
function sl_rmPerformance(int $id, int $days = 90): array
{
    $from = date('Y-m-d', strtotime("-$days day"));
    $t = sl_rmTrips($id, $from, date('Y-m-d'));
    $done = count($t['trips']);
    $failed = $t['failed'];
    $handover = null;
    $handoverN = 0;
    $doorFailed = 0;
    if (sl_tableExists('cdb_delivery_requests') && sl_tableExists('cdb_delivery_events')) {
        // Handover time = from "on route" (first event) to the completed delivery, door deliveries only: both times are stored.
        $r = sl_row("SELECT COUNT(*) AS n, AVG(TIMESTAMPDIFF(MINUTE, e.started, r.completed_at)) AS m FROM cdb_delivery_requests r JOIN (SELECT request_id, MIN(created_at) AS started FROM cdb_delivery_events WHERE state = 'onroute' GROUP BY request_id) e ON e.request_id = r.id
            WHERE r.driver_id = :u AND r.state = 'delivered' AND r.completed_at >= :f AND e.started <= r.completed_at", [':u' => $id, ':f' => $from . ' 00:00:00']);
        if ($r && (int) $r->n > 0) { $handover = (float) $r->m; $handoverN = (int) $r->n; }
        $doorFailed = (int) sl_scalar("SELECT COUNT(*) FROM cdb_delivery_events WHERE actor_id = :u AND state = 'failed' AND created_at >= :f", [':u' => $id, ':f' => $from . ' 00:00:00']);
    }
    $failed += $doorFailed;
    return ['days' => $days, 'done' => $done, 'failed' => $failed, 'open' => $t['open'], 'finish' => $done + $failed > 0 ? sl_pct($done, $done + $failed) : null, 'handover' => $handover, 'handoverN' => $handoverN, 'trips' => $t['trips'],
        'door' => count(array_filter($t['trips'], fn($x) => $x['k'] === 'd')), 'pickups' => count(array_filter($t['trips'], fn($x) => $x['kind'] === 'Pickup'))];
}

function sl_rmMinutes(?float $m): string
{
    if ($m === null) {
        return '—';
    }
    $m = (int) round($m);
    return $m >= 60 ? floor($m / 60) . ' h ' . ($m % 60) . ' min' : $m . ' min';
}

function sl_riderRatingsData($user, $core): array
{
    $d = sl_rmCtx();
    if (!$d['isRider']) {
        return $d;
    }
    $p = sl_rmPerformance($d['id'], 90);
    $bars = [];
    $byWeek = [];
    foreach ($p['trips'] as $t) {
        $k = date('Y-m-d', strtotime('monday this week', strtotime($t['at'])));
        $byWeek[$k] = ($byWeek[$k] ?? 0) + 1;
    }
    $max = max(1, max($byWeek ?: [0]));
    for ($i = 11; $i >= 0; $i--) {
        $k = date('Y-m-d', strtotime('monday this week -' . $i . ' week'));
        $n = $byWeek[$k] ?? 0;
        $bars[] = ['h' => 'height:' . max(3, sl_pct($n, $max)) . '%', 't' => 'Week of ' . sl_date($k, 'j M') . ': ' . $n . ($n === 1 ? ' trip' : ' trips'), 'cls' => $i === 0 ? 'on' : ''];
    }
    $lifetime = count(sl_rmTrips($d['id'], '2000-01-01', date('Y-m-d'))['trips']);
    return $d + ['p' => $p, 'handover' => sl_rmMinutes($p['handover']), 'bars' => $bars, 'lifetime' => $lifetime, 'since' => sl_date($d['me']->created, 'M Y'), 'recent' => array_map('sl_rmTripRow', array_slice($p['trips'], 0, 5))];
}

function sl_riderAccountData($user, $core): array
{
    $d = sl_rmCtx();
    if (!$d['isRider']) {
        return $d;
    }
    $id = $d['id'];
    $me = $d['me'];
    $u = sl_row('SELECT document_type, document_number, document_photo, city, address FROM cdb_users WHERE id = :id', [':id' => $id]);
    $p = sl_rmPerformance($id, 90);
    $docs = [];
    if ($u && trim((string) $u->document_number) !== '') {
        $docs[] = ['icon' => 'card', 'title' => trim((string) $u->document_type) !== '' ? (string) $u->document_type : 'Identity document', 'sub' => 'Number ' . sl_rmMask((string) $u->document_number), 'pill' => trim((string) $u->document_photo) !== '' ? 'Photo on file' : 'No photo on file', 'cls' => trim((string) $u->document_photo) !== '' ? 'delivered' : 'plain'];
    }
    $plate = trim((string) ($me->enrollment ?? ''));
    if ($plate !== '' || trim((string) ($me->vehiclecode ?? '')) !== '') {
        $docs[] = ['icon' => 'scooter', 'title' => 'Vehicle' . ($d['veh'] !== '' ? ' · ' . $d['veh'] : ''), 'sub' => trim(($plate !== '' ? 'Plate ' . $plate : '') . (trim((string) $me->vehiclecode) !== '' ? ' · code ' . $me->vehiclecode : ''), ' ·'), 'pill' => '', 'cls' => ''];
    }
    $bal = sl_rmBalance($id);
    $week = sl_rmShifts($id, date('Y-m-d'), date('Y-m-d', strtotime('+13 day')));
    $next = null;
    foreach ($week as $s) {
        if (strtotime($s->day . ' ' . $s->end_time) > time()) { $next = $s; break; }
    }
    $live = count(array_filter(sl_rmQuestRows($id), fn($q) => $q['state'] === 'live'));
    return $d + ['ready' => sl_tableExists('cdb_rider_prefs'), 'plate' => $plate, 'zone' => trim((string) ($me->zone ?? '')), 'phone' => (string) ($me->phone ?? ''), 'email' => (string) ($me->email ?? ''), 'since' => sl_date($me->created, 'M Y'), 'active' => (int) $me->active,
        'p' => $p, 'handover' => sl_rmMinutes($p['handover']), 'docs' => $docs, 'account' => sl_rmPayoutAccount($id), 'owed' => sl_money($bal['owed']), 'nav' => sl_riderPref($id, 'nav_app'), 'sounds' => sl_riderPref($id, 'offer_sounds') === '1',
        'contact' => sl_rmEmergencyContact($id), 'nextShift' => $next ? sl_date($next->day, 'D j M') . ' ' . substr((string) $next->start_time, 0, 5) . '–' . substr((string) $next->end_time, 0, 5) : '', 'liveQuests' => $live,
        'version' => trim((string) ($core->version ?? '')), 'site' => (string) ($core->site_name ?? ''), 'riderRef' => 'R-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT)];
}
