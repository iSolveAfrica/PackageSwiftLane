<?php
/**
 * Data for the freight boards: FreightHub, FreightSchedule, Waybills,
 * CargoExceptions, InsuranceClaims and FreightPartners.
 * Tables: cdb_freight_schedules, cdb_freight_partners, cdb_waybills,
 * cdb_waybill_items, cdb_cargo_exceptions, cdb_insurance_claims (migration
 * 2026_11_06_freight_devops) plus cdb_add_order / cdb_consolidate for links.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';

/* ------------------------------------------------------------------ shared */

function sl_frReady(): bool
{
    return sl_tableExists('cdb_freight_schedules') && sl_tableExists('cdb_cargo_exceptions') && sl_tableExists('cdb_waybills');
}

function sl_frTypes(): array
{
    return ['airline' => 'Airline', 'shipping_line' => 'Shipping line', 'trucking' => 'Trucking', 'customs_broker' => 'Customs broker', 'ground_handler' => 'Ground handler', 'us_carrier' => 'US carrier', 'insurer' => 'Insurer'];
}

function sl_frTypeIcon(string $t): string
{
    return ['airline' => 'i-plane', 'shipping_line' => 'i-ship', 'trucking' => 'i-truck', 'customs_broker' => 'i-shield', 'ground_handler' => 'i-warehouse', 'us_carrier' => 'i-box', 'insurer' => 'i-shield'][$t] ?? 'i-users';
}

function sl_frExTypes(): array
{
    return ['damaged' => 'Damaged', 'missing' => 'Missing', 'customs_hold' => 'Customs hold', 'partial' => 'Partial', 'misrouted' => 'Misrouted', 'delayed' => 'Delayed', 'handover' => 'Hand-over'];
}

function sl_frSchedStatus(string $s): array
{
    return ['scheduled' => ['Scheduled', 'pending'], 'boarding' => ['Loading', 'sorting'], 'in_transit' => ['In transit', 'transit'], 'arrived' => ['Arrived', 'delivered'], 'delayed' => ['Delayed', 'overdue'], 'cancelled' => ['Cancelled', 'overdue']][$s] ?? [ucfirst($s), 'plain'];
}

function sl_frClaimSteps(): array
{
    return ['reported' => 'Reported', 'reviewing' => 'Reviewing', 'approved' => 'Approved', 'paid' => 'Paid'];
}

function sl_frUnit(string $u): string
{
    return ['pkgs' => 'pkgs', 'kg' => 'kg', 'ft3' => 'ft³'][$u] ?? $u;
}

function sl_frWhen($dt): string
{
    return $dt ? sl_date($dt, 'D M j H:i') : '—';
}

/** Rows for the schedule select boxes. */
function sl_frScheduleOptions(): array
{
    if (!sl_tableExists('cdb_freight_schedules')) {
        return [];
    }
    return array_map(fn($r) => ['id' => (int) $r->id, 'label' => $r->voyage_no . ' · ' . $r->origin . ' → ' . $r->destination . ' · ' . sl_date($r->departs_at, 'M j')], sl_rows("SELECT id, voyage_no, origin, destination, departs_at FROM cdb_freight_schedules WHERE status NOT IN ('arrived','cancelled') ORDER BY departs_at LIMIT 100"));
}

function sl_frPartnerOptions(?string $type = null): array
{
    if (!sl_tableExists('cdb_freight_partners')) {
        return [];
    }
    $w = $type ? ' AND type = :t' : '';
    return array_map(fn($r) => ['id' => (int) $r->id, 'label' => $r->name, 'type' => $r->type], sl_rows("SELECT id, name, type FROM cdb_freight_partners WHERE active = 1 $w ORDER BY name", $type ? [':t' => $type] : []));
}

function sl_frStaffOptions(): array
{
    return array_map(fn($r) => ['id' => (int) $r->id, 'label' => trim($r->fname . ' ' . $r->lname) ?: $r->username], sl_rows('SELECT id, fname, lname, username FROM cdb_users WHERE userlevel <> 1 AND active = 1 ORDER BY fname LIMIT 200'));
}

/* ---------------------------------------------------------------- Freight hub */

function sl_freightHubData($user, $core): array
{
    $d = ['ready' => sl_frReady(), 'flash' => (string) ($_GET['flash'] ?? '')];
    if (!$d['ready']) {
        $d['kpis'] = [];
        $d['deps'] = [];
        $d['acts'] = [];
    } else {
        $air = sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(capacity_booked),0) AS b FROM cdb_freight_schedules WHERE mode = 'air' AND status = 'in_transit'");
        $sea = sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(capacity_booked),0) AS b FROM cdb_freight_schedules WHERE mode = 'sea' AND status = 'in_transit'");
        $week = sl_rows("SELECT voyage_no, departs_at FROM cdb_freight_schedules WHERE status IN ('scheduled','boarding') AND departs_at BETWEEN NOW() AND NOW() + INTERVAL 7 DAY ORDER BY departs_at");
        $ex = sl_row("SELECT COUNT(*) AS n, SUM(severity = 'urgent') AS u, SUM(type = 'partial') AS p FROM cdb_cargo_exceptions WHERE status IN ('open','in_progress')");
        $cl = sl_tableExists('cdb_insurance_claims') ? sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(claim_amount),0) AS amt FROM cdb_insurance_claims WHERE status NOT IN ('paid','rejected')") : null;
        $d['kpis'] = [
            ['l' => 'In the air', 'v' => (int) $air->n . ' ' . ((int) $air->n === 1 ? 'flight' : 'flights'), 's' => sl_num($air->b) . ' booked units in transit'],
            ['l' => 'At sea', 'v' => (int) $sea->n . ' ' . ((int) $sea->n === 1 ? 'vessel' : 'vessels'), 's' => sl_num($sea->b) . ' booked units in transit'],
            ['l' => 'Departing this week', 'v' => (string) count($week), 's' => $week ? 'Next: ' . $week[0]->voyage_no . ' ' . sl_date($week[0]->departs_at, 'D H:i') : 'Nothing scheduled'],
            ['l' => 'Open exceptions', 'v' => (string) (int) $ex->n, 's' => (int) $ex->u . ' urgent · ' . (int) $ex->p . ' partial'],
            ['l' => 'Open claims', 'v' => $cl ? (string) (int) $cl->n : '0', 's' => $cl ? sl_money($cl->amt) . ' claimed' : 'Claims table missing'],
        ];
        $d['deps'] = [];
        foreach (sl_rows("SELECT * FROM cdb_freight_schedules WHERE status IN ('scheduled','boarding','delayed') AND departs_at >= NOW() - INTERVAL 1 DAY ORDER BY departs_at LIMIT 4") as $s) {
            $cap = (float) $s->capacity_total;
            $pct = $cap > 0 ? sl_pct($s->capacity_booked, $cap) : 0;
            $d['deps'][] = ['id' => (int) $s->id, 'icon' => $s->mode === 'sea' ? 'i-ship' : 'i-plane', 't' => $s->voyage_no . ' · ' . $s->origin . ' → ' . $s->destination, 's' => sl_frWhen($s->departs_at) . ($s->cutoff_at ? ' · cut-off ' . sl_date($s->cutoff_at, 'D H:i') : '') . ($s->consolidation_ids ? ' · ' . $s->consolidation_ids : ''), 'fill' => sl_num($s->capacity_booked) . ' / ' . ($cap > 0 ? sl_num($cap) : '—'), 'w' => 'width:' . $pct . '%', 'hi' => $pct > 75 ? 'hi' : ''];
        }
        $acts = [];
        foreach (sl_rows("SELECT id, title, entity_ref, type, severity, created_at FROM cdb_cargo_exceptions WHERE status IN ('open','in_progress') ORDER BY FIELD(severity,'urgent','high','medium','low'), created_at LIMIT 3") as $e) {
            $acts[] = ['c' => in_array($e->severity, ['urgent', 'high'], true) ? 'var(--red)' : 'var(--yellow)', 't' => $e->entity_ref . ' · ' . $e->title, 's' => (sl_frExTypes()[$e->type] ?? $e->type) . ' · opened ' . sl_ago($e->created_at), 'href' => sl_route('CargoExceptions', ['id' => (int) $e->id])];
        }
        if ($cl) {
            foreach (sl_rows("SELECT id, claim_no, customer_name, type, claim_amount FROM cdb_insurance_claims WHERE status = 'reported' ORDER BY created_at LIMIT 2") as $c) {
                $acts[] = ['c' => 'var(--red)', 't' => 'Claim ' . $c->claim_no . ' waiting for review', 's' => ucfirst($c->type) . ' · ' . $c->customer_name . ' · ' . sl_money($c->claim_amount), 'href' => sl_route('InsuranceClaims', ['id' => (int) $c->id])];
            }
        }
        foreach (sl_rows("SELECT s.id, s.voyage_no, s.departs_at FROM cdb_freight_schedules s WHERE s.mode = 'air' AND s.status IN ('scheduled','boarding') AND s.departs_at BETWEEN NOW() AND NOW() + INTERVAL 3 DAY AND NOT EXISTS (SELECT 1 FROM cdb_waybills w WHERE w.schedule_id = s.id AND w.kind = 'master' AND w.status <> 'draft') ORDER BY s.departs_at LIMIT 2") as $s) {
            $acts[] = ['c' => 'var(--yellow)', 't' => 'No MAWB issued for ' . $s->voyage_no, 's' => 'Departs ' . sl_frWhen($s->departs_at), 'href' => sl_route('Waybills', ['schedule' => (int) $s->id])];
        }
        foreach (sl_rows("SELECT id, voyage_no, destination FROM cdb_freight_schedules WHERE status = 'delayed' ORDER BY departs_at LIMIT 2") as $s) {
            $acts[] = ['c' => 'var(--yellow)', 't' => $s->voyage_no . ' delayed', 's' => 'Arrival at ' . $s->destination . ' pushed back', 'href' => sl_route('FreightSchedule', ['id' => (int) $s->id])];
        }
        $d['acts'] = array_slice($acts, 0, 6);
    }
    $f = (string) ($_GET['filter'] ?? 'all');
    $tools = [
        ['t' => 'Departures and arrivals', 'board' => 'FreightSchedule', 'icon' => 'i-calendar', 'new' => true, 'd' => 'Flights and sailings, cut-offs, booked space, predicted arrival', 'n' => sl_tableExists('cdb_freight_schedules') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_freight_schedules WHERE status NOT IN ('arrived','cancelled')") : 0, 'noun' => 'open'],
        ['t' => 'Waybills and manifests', 'board' => 'Waybills', 'icon' => 'i-file', 'new' => true, 'd' => 'Master and house airway bills, cargo manifest, container loading', 'n' => sl_tableExists('cdb_waybills') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_waybills') : 0, 'noun' => 'bills'],
        ['t' => 'Cargo exceptions', 'board' => 'CargoExceptions', 'icon' => 'i-alert', 'new' => true, 'd' => 'Damaged, missing, customs holds, partial shipments, hand-overs, bulk updates', 'n' => sl_tableExists('cdb_cargo_exceptions') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_cargo_exceptions WHERE status IN ('open','in_progress')") : 0, 'noun' => 'open'],
        ['t' => 'Insurance and claims', 'board' => 'InsuranceClaims', 'icon' => 'i-shield', 'new' => true, 'd' => 'Cover from declared value, claims from report to payout', 'n' => sl_tableExists('cdb_insurance_claims') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_insurance_claims WHERE status NOT IN ('paid','rejected')") : 0, 'noun' => 'open'],
        ['t' => 'Freight partners', 'board' => 'FreightPartners', 'icon' => 'i-users', 'new' => true, 'd' => 'Airlines, lines, brokers, handlers: contacts, lanes, rates, performance', 'n' => sl_tableExists('cdb_freight_partners') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_freight_partners WHERE active = 1') : 0, 'noun' => 'partners'],
        ['t' => 'Consolidations', 'board' => 'Consolidations', 'icon' => 'i-layers', 'new' => false, 'd' => 'Group shipments into one air or sea consolidation', 'n' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_consolidate'), 'noun' => 'air'],
        ['t' => 'Warehouse', 'board' => 'Warehouse', 'icon' => 'i-warehouse', 'new' => false, 'd' => 'Scan parcels in and out, bays and holds', 'n' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE is_deleted = 0 AND status_courier = 4'), 'noun' => 'in warehouse'],
        ['t' => 'Hand out packages', 'board' => 'HandOut', 'icon' => 'i-box', 'new' => false, 'd' => 'Counter hand-outs with pickup codes', 'n' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE is_deleted = 0 AND status_courier = 32'), 'noun' => 'ready'],
        ['t' => 'Shipment tracking', 'board' => 'ShipmentTracking', 'icon' => 'i-track', 'new' => false, 'd' => 'Every scan on a shipment', 'n' => null, 'noun' => ''],
        ['t' => 'Last-mile delivery', 'board' => 'LastMile', 'icon' => 'i-truck', 'new' => false, 'd' => 'Zones, riders, COD and failed attempts in Ghana', 'n' => null, 'noun' => ''],
    ];
    $nNew = count(array_filter($tools, fn($t) => $t['new']));
    $d['fl'] = [['f' => 'all', 'label' => 'All ' . count($tools)], ['f' => 'new', 'label' => 'Freight ' . $nNew], ['f' => 'built', 'label' => 'Related ' . (count($tools) - $nNew)]];
    foreach ($d['fl'] as &$x) {
        $x['cls'] = $x['f'] === $f ? 'on' : '';
        $x['href'] = sl_url(['filter' => $x['f']]);
    }
    unset($x);
    $d['tools'] = [];
    foreach ($tools as $t) {
        if ($f === 'new' && !$t['new']) continue;
        if ($f === 'built' && $t['new']) continue;
        $has = $t['n'] === null || $t['n'] > 0;
        $d['tools'][] = ['t' => $t['t'], 'href' => sl_route($t['board']), 'icon' => $t['icon'], 'd' => $t['d'], 'st' => $t['n'] === null ? 'Open' : ($t['n'] > 0 ? sl_num($t['n']) . ' ' . $t['noun'] : 'Empty'), 'pc' => $t['n'] === null ? 'plain' : ($has ? 'delivered' : 'pending')];
    }
    return $d;
}

/* ----------------------------------------------------------- Freight schedule */

function sl_freightScheduleData($user, $core): array
{
    $d = ['ready' => sl_tableExists('cdb_freight_schedules'), 'flash' => (string) ($_GET['flash'] ?? ''), 'canManage' => $user->cdp_hasPermission('manage_freight')];
    $mode = in_array($_GET['mode'] ?? 'all', ['all', 'air', 'sea'], true) ? $_GET['mode'] : 'all';
    $d['mode'] = $mode;
    $d['modes'] = array_map(fn($m) => ['label' => $m[1], 'cls' => $mode === $m[0] ? 'on' : '', 'href' => sl_url(['mode' => $m[0] === 'all' ? '' : $m[0]])], [['all', 'All'], ['air', 'Air'], ['sea', 'Sea']]);
    $d['partners'] = sl_frPartnerOptions();
    $d['kpis'] = [];
    $d['rows'] = [];
    $d['days'] = [];
    $d['d'] = null;
    if (!$d['ready']) {
        return $d;
    }
    $month = sl_row("SELECT COUNT(*) AS n, SUM(mode = 'air') AS a, SUM(mode = 'sea') AS s FROM cdb_freight_schedules WHERE departs_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND departs_at < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01') AND status <> 'cancelled'");
    $fill = sl_row("SELECT SUM(capacity_booked) AS b, SUM(capacity_total) AS t, SUM(CASE WHEN mode='air' THEN capacity_booked END) AS ab, SUM(CASE WHEN mode='air' THEN capacity_total END) AS at, SUM(CASE WHEN mode='sea' THEN capacity_booked END) AS sb, SUM(CASE WHEN mode='sea' THEN capacity_total END) AS st FROM cdb_freight_schedules WHERE status IN ('scheduled','boarding','in_transit') AND capacity_total > 0");
    $ot = sl_row("SELECT COUNT(*) AS n, SUM(arrived_at <= arrives_at) AS ok, SUM(mode='air') AS an, SUM(mode='air' AND arrived_at <= arrives_at) AS aok, SUM(mode='sea') AS sn, SUM(mode='sea' AND arrived_at <= arrives_at) AS sok FROM cdb_freight_schedules WHERE status = 'arrived' AND arrived_at IS NOT NULL AND arrives_at IS NOT NULL AND arrived_at >= NOW() - INTERVAL 90 DAY");
    $cut = sl_rows("SELECT voyage_no, cutoff_at FROM cdb_freight_schedules WHERE status IN ('scheduled','boarding') AND cutoff_at BETWEEN NOW() AND NOW() + INTERVAL 48 HOUR ORDER BY cutoff_at");
    $late = sl_rows("SELECT voyage_no, mode FROM cdb_freight_schedules WHERE status = 'delayed'");
    $d['kpis'] = [
        ['l' => 'Departures this month', 'v' => (string) (int) $month->n, 's' => (int) $month->a . ' air · ' . (int) $month->s . ' sea'],
        ['l' => 'Space booked', 'v' => $fill && $fill->t > 0 ? sl_pct($fill->b, $fill->t) . '%' : '—', 's' => $fill && $fill->t > 0 ? 'Air ' . ($fill->at > 0 ? sl_pct($fill->ab, $fill->at) . '%' : '—') . ' · sea ' . ($fill->st > 0 ? sl_pct($fill->sb, $fill->st) . '%' : '—') : 'No capacity set on open departures'],
        ['l' => 'On-time arrivals (90 days)', 'v' => (int) $ot->n ? sl_pct($ot->ok, $ot->n) . '%' : '—', 's' => (int) $ot->n ? 'Air ' . ((int) $ot->an ? sl_pct($ot->aok, $ot->an) . '%' : '—') . ' · sea ' . ((int) $ot->sn ? sl_pct($ot->sok, $ot->sn) . '%' : '—') : 'No arrivals recorded yet'],
        ['l' => 'Cut-offs in 48 h', 'v' => (string) count($cut), 's' => $cut ? implode(' · ', array_map(fn($c) => $c->voyage_no . ' ' . sl_date($c->cutoff_at, 'D H:i'), array_slice($cut, 0, 2))) : 'None'],
        ['l' => 'Delayed now', 'v' => (string) count($late), 's' => $late ? implode(', ', array_map(fn($l) => $l->voyage_no, array_slice($late, 0, 3))) : 'Nothing marked delayed'],
    ];
    // week calendar (Mon..Sun of the current week)
    $monday = strtotime('monday this week');
    $sunday = $monday + 6 * 86400;
    $d['weekLabel'] = date('M j', $monday) . ' – ' . date('M j', $sunday);
    $sel = (int) ($_GET['id'] ?? 0);
    $evs = [];
    foreach (sl_rows("SELECT id, voyage_no, mode, departs_at, cutoff_at, arrives_at, arrived_at, status, capacity_booked, capacity_total, capacity_unit FROM cdb_freight_schedules WHERE (departs_at BETWEEN :a AND :b) OR (cutoff_at BETWEEN :a2 AND :b2) OR (COALESCE(arrived_at, arrives_at) BETWEEN :a3 AND :b3)", [':a' => date('Y-m-d 00:00:00', $monday), ':b' => date('Y-m-d 23:59:59', $sunday), ':a2' => date('Y-m-d 00:00:00', $monday), ':b2' => date('Y-m-d 23:59:59', $sunday), ':a3' => date('Y-m-d 00:00:00', $monday), ':b3' => date('Y-m-d 23:59:59', $sunday)]) as $s) {
        $add = function ($at, $t, $sub, $co = false) use (&$evs, $s, $sel, $monday) {
            if (!$at) return;
            $day = (int) floor((strtotime(date('Y-m-d', strtotime($at))) - $monday) / 86400);
            if ($day < 0 || $day > 6) return;
            $evs[$day][] = ['t' => $t, 's' => $sub, 'cls' => (($sel === (int) $s->id) ? 'on ' : '') . ($co ? 'co' : ''), 'href' => sl_url(['id' => (int) $s->id])];
        };
        $isSoon = $s->cutoff_at && strtotime($s->cutoff_at) <= time() + 2 * 86400 && strtotime($s->cutoff_at) >= time() - 86400;
        $add($s->cutoff_at, $s->voyage_no . ' cut-off', sl_date($s->cutoff_at, 'H:i') . ' · ' . sl_num($s->capacity_booked) . ' / ' . ($s->capacity_total > 0 ? sl_num($s->capacity_total) : '—'), $isSoon);
        $add($s->departs_at, $s->voyage_no . ' ' . ($s->status === 'in_transit' || $s->status === 'arrived' ? 'departed' : 'departs'), sl_date($s->departs_at, 'H:i') . ' · ' . sl_num($s->capacity_booked) . ' ' . sl_frUnit($s->capacity_unit), $isSoon);
        $add($s->arrived_at ?: $s->arrives_at, $s->voyage_no . ($s->arrived_at ? ' landed' : ' arrives'), sl_date($s->arrived_at ?: $s->arrives_at, 'H:i'));
    }
    for ($i = 0; $i < 7; $i++) {
        $ts = $monday + $i * 86400;
        $d['days'][] = ['l' => date('D j', $ts), 'cls' => date('Y-m-d', $ts) === date('Y-m-d') ? 'today' : '', 'evs' => $evs[$i] ?? []];
    }
    // list
    $w = $mode === 'all' ? '' : ' AND mode = :m';
    $binds = $mode === 'all' ? [] : [':m' => $mode];
    $rows = sl_rows("SELECT s.*, p.name AS partner FROM cdb_freight_schedules s LEFT JOIN cdb_freight_partners p ON p.id = s.partner_id WHERE 1 $w ORDER BY CASE WHEN s.status IN ('arrived','cancelled') THEN 1 ELSE 0 END, s.departs_at LIMIT 60", $binds);
    $first = null;
    foreach ($rows as $s) {
        $st = sl_frSchedStatus($s->status);
        $cap = (float) $s->capacity_total;
        $pct = $cap > 0 ? sl_pct($s->capacity_booked, $cap) : 0;
        $pred = sl_frPredict($s);
        $r = ['id' => (int) $s->id, 'n' => $s->voyage_no, 'icon' => $s->mode === 'sea' ? 'i-ship' : 'i-plane', 'route' => $s->origin . ' → ' . $s->destination . ($s->partner ? ' · ' . $s->partner : ($s->carrier_name ? ' · ' . $s->carrier_name : '')), 'dep' => sl_frWhen($s->departs_at), 'cut' => sl_frWhen($s->cutoff_at), 'fill' => sl_num($s->capacity_booked) . ' / ' . ($cap > 0 ? sl_num($cap) : '—') . ' ' . sl_frUnit($s->capacity_unit), 'w' => 'width:' . $pct . '%', 'hi' => $pct > 80 ? 'hi' : '', 'eta' => $s->arrived_at ? sl_frWhen($s->arrived_at) : ($pred['eta'] ?: sl_frWhen($s->arrives_at)), 'conf' => $pred['conf'], 'st' => $st[0], 'pc' => $st[1], 'cls' => $sel === (int) $s->id ? 'on' : '', 'href' => sl_url(['id' => (int) $s->id])];
        $d['rows'][] = $r;
        if ($first === null) $first = $s;
    }
    $cur = $sel ? sl_row('SELECT s.*, p.name AS partner FROM cdb_freight_schedules s LEFT JOIN cdb_freight_partners p ON p.id = s.partner_id WHERE s.id = :id', [':id' => $sel]) : $first;
    if ($cur) {
        $st = sl_frSchedStatus($cur->status);
        $cap = (float) $cur->capacity_total;
        $pred = sl_frPredict($cur);
        $hist = sl_rows("SELECT arrives_at, arrived_at FROM cdb_freight_schedules WHERE origin = :o AND destination = :de AND mode = :m AND status = 'arrived' AND arrived_at IS NOT NULL AND id <> :id ORDER BY arrived_at DESC LIMIT 10", [':o' => $cur->origin, ':de' => $cur->destination, ':m' => $cur->mode, ':id' => (int) $cur->id]);
        $bars = [];
        $lateN = 0;
        $delays = [];
        $maxD = 1;
        foreach ($hist as $h) {
            $delta = $h->arrives_at ? (strtotime($h->arrived_at) - strtotime($h->arrives_at)) / 3600 : 0;
            $delays[] = $delta;
            $maxD = max($maxD, abs($delta));
        }
        foreach (array_reverse($delays) as $delta) {
            $late = $delta > 1;
            if ($late) $lateN++;
            $bars[] = ['c' => $late ? 'late' : '', 's' => 'height:' . max(12, (int) round(abs($delta) / $maxD * 100)) . '%', 't' => $late ? 'Late by ' . round($delta, 1) . ' h' : 'On time'];
        }
        $avg = $delays ? array_sum($delays) / count($delays) : 0;
        $consIds = array_filter(array_map('trim', explode(',', (string) $cur->consolidation_ids)));
        $consHref = $consIds ? sl_route('Consolidations', ['q' => reset($consIds)]) : sl_route('Consolidations');
        $d['d'] = ['id' => (int) $cur->id, 'n' => $cur->voyage_no, 'icon' => $cur->mode === 'sea' ? 'i-ship' : 'i-plane', 'route' => $cur->origin . ' → ' . $cur->destination . ($cur->partner ? ' · ' . $cur->partner : ($cur->carrier_name ? ' · ' . $cur->carrier_name : '')), 'st' => $st[0], 'pc' => $st[1], 'status' => $cur->status, 'dep' => sl_frWhen($cur->departs_at), 'cut' => sl_frWhen($cur->cutoff_at), 'fill' => sl_num($cur->capacity_booked) . ' / ' . ($cap > 0 ? sl_num($cap) : '—') . ' ' . sl_frUnit($cur->capacity_unit), 'cons' => $consIds ? implode(', ', $consIds) : '—', 'consHref' => $consHref, 'eta0' => sl_frWhen($cur->arrives_at), 'eta' => $cur->arrived_at ? sl_frWhen($cur->arrived_at) . ' (actual)' : ($pred['eta'] ?: '—'), 'bars' => $bars, 'hist' => $hist ? $lateN . ' of the last ' . count($hist) . ' were late · average ' . ($avg >= 0 ? '+' : '') . round($avg, 1) . ' h vs carrier ETA' : 'No arrivals recorded on this route yet', 'notes' => (string) $cur->notes, 'unit' => sl_frUnit($cur->capacity_unit), 'mode' => $cur->mode];
    }
    $d['count'] = count($rows);
    return $d;
}

/** Our arrival prediction: carrier ETA + average delay of the last 10 trips on the route. */
function sl_frPredict($s): array
{
    if (!$s->arrives_at || $s->status === 'arrived') {
        return ['eta' => '', 'conf' => $s->status === 'arrived' ? 'Arrived' : 'No carrier ETA'];
    }
    $h = sl_row("SELECT COUNT(*) AS n, AVG(TIMESTAMPDIFF(MINUTE, arrives_at, arrived_at)) AS avgm, SUM(TIMESTAMPDIFF(MINUTE, arrives_at, arrived_at) > 60) AS late FROM (SELECT arrives_at, arrived_at FROM cdb_freight_schedules WHERE origin = :o AND destination = :de AND mode = :m AND status = 'arrived' AND arrived_at IS NOT NULL AND arrives_at IS NOT NULL AND id <> :id ORDER BY arrived_at DESC LIMIT 10) x", [':o' => $s->origin, ':de' => $s->destination, ':m' => $s->mode, ':id' => (int) $s->id]);
    if (!$h || (int) $h->n === 0) {
        return ['eta' => sl_frWhen($s->arrives_at), 'conf' => 'Carrier ETA · no history on this route'];
    }
    $eta = strtotime($s->arrives_at) + (int) round((float) $h->avgm * 60);
    $onTime = sl_pct((int) $h->n - (int) $h->late, (int) $h->n);
    return ['eta' => date('D M j H:i', $eta), 'conf' => $onTime . '% on time over last ' . (int) $h->n . ' trips'];
}

/* ------------------------------------------------------------------ Waybills */

function sl_waybillsData($user, $core): array
{
    $d = ['ready' => sl_tableExists('cdb_waybills') && sl_tableExists('cdb_waybill_items'), 'flash' => (string) ($_GET['flash'] ?? ''), 'canManage' => $user->cdp_hasPermission('manage_freight')];
    $tab = in_array($_GET['tab'] ?? 'awb', ['awb', 'man', 'load'], true) ? $_GET['tab'] : 'awb';
    $d['tabs'] = array_map(fn($t) => ['label' => $t[1], 'icon' => $t[2], 'cls' => $tab === $t[0] ? 'on' : '', 'href' => sl_url(['tab' => $t[0]])], [['awb', 'Master and house bills', 'i-layers'], ['man', 'Cargo manifest', 'i-file'], ['load', 'Container loading', 'i-box']]);
    $d['tAwb'] = $tab === 'awb';
    $d['tMan'] = $tab === 'man';
    $d['tLoad'] = $tab === 'load';
    $d['schedules'] = sl_frScheduleOptions();
    $d['kpis'] = [];
    $d['mawbs'] = [];
    $d['cur'] = null;
    if (!$d['ready']) {
        return $d;
    }
    $m = sl_row("SELECT COUNT(*) AS n, SUM(container_no IS NOT NULL AND container_no <> '') AS sea FROM cdb_waybills WHERE kind = 'master' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $h = (int) sl_scalar("SELECT COUNT(*) FROM cdb_waybill_items i JOIN cdb_waybills w ON w.id = i.waybill_id WHERE w.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $waiting = sl_rows("SELECT s.voyage_no, s.departs_at FROM cdb_freight_schedules s WHERE s.status IN ('scheduled','boarding') AND s.departs_at >= NOW() AND NOT EXISTS (SELECT 1 FROM cdb_waybills w WHERE w.schedule_id = s.id AND w.kind = 'master' AND w.status <> 'draft') ORDER BY s.departs_at LIMIT 3");
    $sent = (int) sl_scalar("SELECT COUNT(*) FROM cdb_waybills WHERE kind = 'master' AND sent_at IS NOT NULL AND sent_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $fillRow = sl_row("SELECT w.container_ft3, w.waybill_no, (SELECT COALESCE(SUM(i.weight_kg),0) FROM cdb_waybill_items i WHERE i.waybill_id = w.id) AS kg FROM cdb_waybills w WHERE w.container_no IS NOT NULL AND w.container_no <> '' AND w.status <> 'closed' ORDER BY w.id DESC");
    $d['kpis'] = [
        ['l' => 'MAWBs this month', 'v' => (string) (int) $m->n, 's' => ((int) $m->n - (int) $m->sea) . ' air · ' . (int) $m->sea . ' container'],
        ['l' => 'House bills this month', 'v' => sl_num($h), 's' => 'One per customer shipment'],
        ['l' => 'Waiting for MAWB', 'v' => (string) count($waiting), 's' => $waiting ? $waiting[0]->voyage_no . ' ' . sl_date($waiting[0]->departs_at, 'D') : 'Every departure has one'],
        ['l' => 'Manifests sent', 'v' => (string) $sent, 's' => 'This month'],
        ['l' => 'Latest container', 'v' => $fillRow ? sl_num($fillRow->kg) . ' kg' : '—', 's' => $fillRow ? $fillRow->waybill_no : 'No container bill open'],
    ];
    $open = (int) ($_GET['id'] ?? 0);
    $schedFilter = (int) ($_GET['schedule'] ?? 0);
    $rows = sl_rows('SELECT w.*, s.voyage_no, s.origin AS s_origin, s.destination AS s_dest, s.mode, (SELECT COUNT(*) FROM cdb_waybill_items i WHERE i.waybill_id = w.id) AS n, (SELECT COALESCE(SUM(i.weight_kg),0) FROM cdb_waybill_items i WHERE i.waybill_id = w.id) AS kg, (SELECT COALESCE(SUM(i.charge),0) FROM cdb_waybill_items i WHERE i.waybill_id = w.id) AS billed FROM cdb_waybills w LEFT JOIN cdb_freight_schedules s ON s.id = w.schedule_id WHERE w.kind = :k' . ($schedFilter ? ' AND w.schedule_id = :s' : '') . ' ORDER BY w.id DESC LIMIT 40', $schedFilter ? [':k' => 'master', ':s' => $schedFilter] : [':k' => 'master']);
    if (!$open && $rows) {
        $open = (int) $rows[0]->id;
    }
    $stMap = ['draft' => ['Draft', 'pending'], 'issued' => ['Issued', 'delivered'], 'sent' => ['Sent', 'delivered'], 'closed' => ['Closed', 'plain']];
    $cur = null;
    foreach ($rows as $w) {
        $st = $stMap[$w->status] ?? [ucfirst($w->status), 'plain'];
        $isOpen = (int) $w->id === $open;
        $hawbs = [];
        $more = '';
        if ($isOpen) {
            $items = sl_rows('SELECT * FROM cdb_waybill_items WHERE waybill_id = :w ORDER BY id LIMIT 200', [':w' => (int) $w->id]);
            foreach (array_slice($items, 0, 8) as $i) {
                $hawbs[] = ['no' => 'H-' . str_pad((string) $i->id, 5, '0', STR_PAD_LEFT), 'cust' => $i->customer_name ?: '—', 'what' => $i->description ?: $i->source, 'trk' => $i->order_track, 'kg' => number_format((float) $i->weight_kg, 1) . ' kg', 'chg' => sl_money($i->charge)];
            }
            $more = count($items) > 8 ? '+ ' . (count($items) - 8) . ' more house bills' : (count($items) === 0 ? 'No house bills yet — add shipments below' : '');
            $cur = $w;
        }
        $d['mawbs'][] = ['id' => (int) $w->id, 'no' => $w->waybill_no, 'fl' => $w->voyage_no ?: ($w->carrier ?: '—'), 'route' => ($w->origin ?: $w->s_origin ?: '?') . ' → ' . ($w->destination ?: $w->s_dest ?: '?'), 'cons' => $w->consolidation_no ?: 'no consolidation', 'n' => (int) $w->n, 'kg' => number_format((float) $w->kg, 1) . ' kg', 'st' => $st[0], 'pc' => $st[1], 'cls' => $isOpen ? 'on' : '', 'open' => $isOpen, 'hawbs' => $hawbs, 'more' => $more, 'href' => sl_url(['id' => (int) $w->id]), 'isSea' => $w->container_no !== null && $w->container_no !== ''];
    }
    if ($cur) {
        $kg = (float) $cur->kg;
        $rate = (float) $cur->rate_per_kg;
        $sur = (float) $cur->surcharge_per_kg;
        $cost = $cur->total_cost > 0 ? (float) $cur->total_cost : $kg * ($rate + $sur);
        $bill = (float) $cur->billed;
        $items = sl_rows('SELECT * FROM cdb_waybill_items WHERE waybill_id = :w ORDER BY COALESCE(slot, 9999), id', [':w' => (int) $cur->id]);
        $d['cur'] = ['id' => (int) $cur->id, 'no' => $cur->waybill_no, 'fl' => ($cur->voyage_no ?: ($cur->carrier ?: '')) . ' · ' . ($cur->origin ?: $cur->s_origin ?: '?') . ' → ' . ($cur->destination ?: $cur->s_dest ?: '?'), 'status' => $cur->status, 'kg' => number_format($kg, 1) . ' kg', 'rate' => sl_money($rate) . ' / kg', 'sur' => sl_money($sur) . ' / kg', 'cost' => sl_money($cost), 'bill' => sl_money($bill), 'margin' => sl_money($bill - $cost) . ($bill > 0 ? ' (' . round(($bill - $cost) / $bill * 100) . '%)' : ''), 'n' => count($items), 'container' => $cur->container_no ?: '', 'seal' => $cur->seal_no ?: '', 'ft3' => (float) $cur->container_ft3, 'sentTo' => $cur->sent_to ?: '', 'sentAt' => $cur->sent_at ? sl_ago($cur->sent_at) : '', 'issuedAt' => $cur->issued_at ? sl_date($cur->issued_at, 'M j, Y H:i') : '', 'carrier' => $cur->carrier ?: '', 'consolidation' => $cur->consolidation_no ?: ''];
        $d['issueLabel'] = $cur->status === 'draft' ? 'Issue ' . $cur->waybill_no : $cur->waybill_no . ' ' . $cur->status;
        $d['issueDisabled'] = $cur->status !== 'draft';
        // manifest text
        $fmt = in_array($_GET['fmt'] ?? 'iata', ['iata', 'customs', 'load'], true) ? $_GET['fmt'] : 'iata';
        $d['fmts'] = array_map(fn($f) => ['label' => $f[1], 'cls' => $fmt === $f[0] ? 'on' : '', 'href' => sl_url(['fmt' => $f[0], 'tab' => 'man'])], [['iata', 'IATA'], ['customs', 'Customs'], ['load', 'Loading list']]);
        $d['manifest'] = sl_frManifestText($cur, $items, $fmt, $core);
        $d['fmt'] = $fmt;
        $sentKeys = array_filter(array_map('trim', explode(',', (string) $cur->sent_to)));
        $d['sends'] = [];
        foreach ([['carrier', 'i-plane', 'Carrier cargo desk', 'E-mail the manifest to ' . ($cur->carrier ?: 'the carrier')], ['customs', 'i-shield', 'Customs broker', 'Pre-arrival declaration'], ['hub', 'i-warehouse', 'Destination hub', 'So they can prepare bays'], ['pdf', 'i-print', 'Printable manifest', 'Open the print view']] as $x) {
            $done = in_array($x[0], $sentKeys, true);
            $d['sends'][] = ['key' => $x[0], 'icon' => $x[1], 't' => $x[2], 'd' => $x[3], 'st' => $x[0] === 'pdf' ? 'Open' : ($done ? 'Marked sent' : 'Ready'), 'pc' => $done ? 'delivered' : '', 'href' => $x[0] === 'pdf' ? 'waybill_print.php?id=' . (int) $cur->id . '&fmt=' . $fmt : ''];
        }
        // loading grid: 12 columns x 6 rows = 72 slots; slot 1 = back
        $cells = [];
        $bySlot = [];
        foreach ($items as $i) {
            if ($i->slot) $bySlot[(int) $i->slot] = $i;
        }
        $kgTot = 0;
        foreach ($items as $i) $kgTot += (float) $i->weight_kg;
        for ($s = 1; $s <= 72; $s++) {
            $i = $bySlot[$s] ?? null;
            if (!$i) {
                $cells[] = ['c' => 'e', 'l' => '', 't' => 'Empty'];
                continue;
            }
            $c = (int) $i->fragile ? 'c' : ((float) $i->weight_kg > 30 ? 'b' : ((float) $i->weight_kg > 10 ? 'a' : ''));
            $cells[] = ['c' => $c, 'l' => (string) $s, 't' => $i->order_track . ' · ' . number_format((float) $i->weight_kg, 1) . ' kg' . ((int) $i->fragile ? ' · fragile' : '')];
        }
        $d['cells'] = $cells;
        $planned = count($bySlot);
        $d['ldN'] = count($items);
        $d['ldPlanned'] = $planned;
        $d['ldKg'] = sl_num($kgTot) . ' kg';
        $ft3 = (float) $cur->container_ft3;
        $d['ldVol'] = $ft3 > 0 ? sl_num($planned * ($ft3 / 72), 0) . ' / ' . sl_num($ft3) . ' ft³ (by slots)' : $planned . ' / 72 slots';
        $d['ldW'] = 'width:' . sl_pct($planned, 72) . '%';
        $d['planLabel'] = $planned === count($items) && $planned > 0 ? 'Re-plan load' : 'Plan the load';
        $d['loadTitle'] = 'Load plan · ' . $cur->waybill_no . ($cur->container_no ? ' · ' . $cur->container_no : '');
    } else {
        $d['issueLabel'] = 'New master waybill';
        $d['issueDisabled'] = true;
        $d['fmts'] = [];
        $d['manifest'] = '';
        $d['sends'] = [];
        $d['cells'] = [];
    }
    return $d;
}

/** Plain-text manifest in one of three layouts. */
function sl_frManifestText($w, array $items, string $fmt, $core): string
{
    $site = (string) ($core->site_name ?? 'Swift Lane');
    $head = $fmt === 'iata' ? 'CARGO MANIFEST · IATA FFM' : ($fmt === 'customs' ? 'CUSTOMS MANIFEST · PRE-ARRIVAL DECLARATION' : strtoupper($site) . ' LOADING LIST');
    $l = [$head];
    $l[] = ($w->container_no ? 'CONTAINER ' : 'MAWB ') . $w->waybill_no . '  ' . ($w->voyage_no ? 'FLIGHT/VESSEL ' . $w->voyage_no . '  ' : '') . ($w->origin ?: $w->s_origin ?: '') . ' → ' . ($w->destination ?: $w->s_dest ?: '');
    $l[] = 'SHIPPER  ' . strtoupper($site) . ($core->c_address ? ' · ' . strtoupper((string) $core->c_address) : '');
    if ($w->carrier) $l[] = 'CARRIER  ' . strtoupper($w->carrier);
    if ($w->seal_no) $l[] = 'SEAL  ' . $w->seal_no;
    $l[] = str_repeat('-', 72);
    if ($fmt === 'load') {
        $l[] = str_pad('SLOT', 6) . str_pad('TRACKING', 20) . str_pad('CONSIGNEE', 22) . str_pad('KG', 8) . 'NOTE';
    } elseif ($fmt === 'customs') {
        $l[] = str_pad('HAWB', 10) . str_pad('TRACKING', 20) . str_pad('CONSIGNEE', 22) . str_pad('PCS', 5) . str_pad('KG', 8) . 'DESCRIPTION';
    } else {
        $l[] = str_pad('HAWB', 10) . str_pad('CONSIGNEE', 22) . str_pad('PCS', 5) . str_pad('KG', 8) . 'DESCRIPTION';
    }
    $kg = 0;
    $pcs = 0;
    foreach ($items as $i) {
        $kg += (float) $i->weight_kg;
        $pcs += (int) $i->pieces;
        $no = 'H-' . str_pad((string) $i->id, 5, '0', STR_PAD_LEFT);
        $cons = mb_strtoupper(mb_substr((string) ($i->customer_name ?: '-'), 0, 20));
        $desc = mb_strtoupper(mb_substr((string) ($i->description ?: ''), 0, 24));
        if ($fmt === 'load') {
            $l[] = str_pad((string) ($i->slot ?: '-'), 6) . str_pad(mb_substr($i->order_track, 0, 18), 20) . str_pad($cons, 22) . str_pad(number_format((float) $i->weight_kg, 1), 8) . ((int) $i->fragile ? 'FRAGILE' : '');
        } elseif ($fmt === 'customs') {
            $l[] = str_pad($no, 10) . str_pad(mb_substr($i->order_track, 0, 18), 20) . str_pad($cons, 22) . str_pad((string) (int) $i->pieces, 5) . str_pad(number_format((float) $i->weight_kg, 1), 8) . $desc;
        } else {
            $l[] = str_pad($no, 10) . str_pad($cons, 22) . str_pad((string) (int) $i->pieces, 5) . str_pad(number_format((float) $i->weight_kg, 1), 8) . $desc;
        }
    }
    if (!$items) {
        $l[] = '(no house bills on this waybill yet)';
    }
    $l[] = str_repeat('-', 72);
    $l[] = 'TOTAL  ' . count($items) . ' HAWB   ' . $pcs . ' PCS   ' . number_format($kg, 1) . ' KG';
    $l[] = 'Generated ' . date('Y-m-d H:i') . ' · ' . $site;
    return implode("\n", $l);
}

/* ---------------------------------------------------------- Cargo exceptions */

function sl_cargoExceptionsData($user, $core): array
{
    $d = ['ready' => sl_tableExists('cdb_cargo_exceptions'), 'flash' => (string) ($_GET['flash'] ?? ''), 'canManage' => $user->cdp_hasPermission('manage_freight'), 'canInsure' => $user->cdp_hasPermission('manage_insurance')];
    $tab = in_array($_GET['tab'] ?? 'ex', ['ex', 'bulk', 'part', 'chain'], true) ? $_GET['tab'] : 'ex';
    $d['tabs'] = array_map(fn($t) => ['label' => $t[1], 'icon' => $t[2], 'cls' => $tab === $t[0] ? 'on' : '', 'href' => sl_url(['tab' => $t[0]])], [['ex', 'Exceptions', 'i-alert'], ['bulk', 'Bulk status update', 'i-refresh'], ['part', 'Partial shipments', 'i-layers'], ['chain', 'Hand-over tracking', 'i-users']]);
    $d['tEx'] = $tab === 'ex';
    $d['tBulk'] = $tab === 'bulk';
    $d['tPart'] = $tab === 'part';
    $d['tChain'] = $tab === 'chain';
    $d['types'] = sl_frExTypes();
    $d['staff'] = sl_frStaffOptions();
    $d['kpis'] = [];
    $d['exs'] = [];
    $d['x'] = null;
    $d['parts'] = [];
    // hand-over counters from the tracking table (today)
    $ho = sl_row("SELECT SUM(status_courier = 7) AS rider, SUM(status_courier = 8) AS cust, SUM(status_courier = 15) AS counter, SUM(status_courier IN (2,4)) AS hub FROM cdb_courier_track WHERE DATE(t_date) = CURDATE()");
    $noSig = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order o WHERE o.is_deleted = 0 AND o.status_courier = 8 AND (o.photo_delivered IS NULL OR o.photo_delivered = '') AND (o.person_receives IS NULL OR o.person_receives = '') AND EXISTS (SELECT 1 FROM cdb_courier_track t WHERE t.order_track = o.order_no AND t.status_courier = 8 AND DATE(t.t_date) = CURDATE())");
    $d['handovers'] = ['rider' => (int) ($ho->rider ?? 0), 'cust' => (int) ($ho->cust ?? 0), 'counter' => (int) ($ho->counter ?? 0), 'hub' => (int) ($ho->hub ?? 0), 'nosig' => $noSig];
    $today = array_sum([(int) ($ho->rider ?? 0), (int) ($ho->cust ?? 0), (int) ($ho->counter ?? 0), (int) ($ho->hub ?? 0)]);
    if ($d['ready']) {
        $k = sl_row("SELECT SUM(status IN ('open','in_progress')) AS open_n, SUM(status IN ('open','in_progress') AND severity = 'urgent') AS urgent, SUM(status IN ('open','in_progress') AND type = 'partial') AS partial, SUM(status IN ('open','in_progress') AND type = 'partial') AS pn, COALESCE(SUM(CASE WHEN status IN ('open','in_progress') AND type = 'partial' THEN GREATEST(COALESCE(pieces_expected,0) - COALESCE(pieces_arrived,0),0) END),0) AS pieces, AVG(CASE WHEN status IN ('resolved','closed') AND type <> 'delayed' THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) AS fix_h FROM cdb_cargo_exceptions");
        $bulkToday = sl_tableExists('cdb_activity_log') ? sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.count')) AS UNSIGNED)),0) AS items FROM cdb_activity_log WHERE module = 'freight' AND action = 'bulk_status' AND DATE(created_at) = CURDATE()") : null;
        $d['kpis'] = [
            ['l' => 'Open exceptions', 'v' => (string) (int) $k->open_n, 's' => (int) $k->urgent . ' urgent'],
            ['l' => 'Partial shipments', 'v' => (string) (int) $k->partial, 's' => (int) $k->pieces . ' pieces still to come'],
            ['l' => 'Bulk updates today', 'v' => $bulkToday ? (string) (int) $bulkToday->n : '0', 's' => $bulkToday ? (int) $bulkToday->items . ' shipments updated' : 'Activity log off'],
            ['l' => 'Hand-overs today', 'v' => (string) $today, 's' => $noSig . ' missing a name or photo'],
            ['l' => 'Average time to fix', 'v' => $k->fix_h !== null ? number_format((float) $k->fix_h / 24, 1) . ' d' : '—', 's' => 'Delays excluded'],
        ];
        $filter = in_array($_GET['filter'] ?? 'open', ['open', 'resolved', 'all'], true) ? $_GET['filter'] : 'open';
        $d['filter'] = $filter;
        $d['filters'] = array_map(fn($f) => ['label' => $f[1], 'cls' => $filter === $f[0] ? 'on' : '', 'href' => sl_url(['filter' => $f[0]])], [['open', 'Open'], ['resolved', 'Resolved'], ['all', 'All']]);
        $w = $filter === 'open' ? "status IN ('open','in_progress')" : ($filter === 'resolved' ? "status IN ('resolved','closed')" : '1');
        $sel = (int) ($_GET['id'] ?? 0);
        $rows = sl_rows("SELECT e.*, CONCAT(u.fname,' ',u.lname) AS owner FROM cdb_cargo_exceptions e LEFT JOIN cdb_users u ON u.id = e.owner_id WHERE $w ORDER BY FIELD(e.status,'open','in_progress','resolved','closed'), FIELD(e.severity,'urgent','high','medium','low'), e.created_at DESC LIMIT 60");
        $sevColor = ['urgent' => 'var(--red)', 'high' => 'var(--red)', 'medium' => 'var(--yellow)', 'low' => 'var(--green)'];
        $stMap = ['open' => ['Open', 'overdue'], 'in_progress' => ['In progress', 'onway'], 'resolved' => ['Resolved', 'delivered'], 'closed' => ['Closed', 'plain']];
        $cur = null;
        foreach ($rows as $e) {
            $st = $stMap[$e->status] ?? [ucfirst($e->status), 'plain'];
            $isSel = $sel ? $sel === (int) $e->id : $cur === null;
            if ($isSel) $cur = $e;
            $d['exs'][] = ['id' => (int) $e->id, 't' => $e->title, 'type' => sl_frExTypes()[$e->type] ?? $e->type, 'ref' => $e->entity_ref, 'who' => $e->owner ?: 'Unassigned', 'age' => sl_ago($e->created_at), 'c' => in_array($e->status, ['resolved', 'closed'], true) ? 'var(--green)' : ($sevColor[$e->severity] ?? 'var(--yellow)'), 'st' => $st[0], 'pc' => $st[1], 'cls' => $isSel ? 'on' : '', 'href' => sl_url(['id' => (int) $e->id])];
        }
        if ($cur) {
            $st = $stMap[$cur->status] ?? [ucfirst($cur->status), 'plain'];
            $photos = json_decode((string) $cur->photos, true) ?: [];
            $d['x'] = ['id' => (int) $cur->id, 't' => $cur->title, 'ref' => $cur->entity_ref, 'entity' => $cur->entity_type, 'type' => sl_frExTypes()[$cur->type] ?? $cur->type, 'typeKey' => $cur->type, 'age' => sl_ago($cur->created_at), 'd' => (string) $cur->notes, 'status' => $cur->status, 'st' => $st[0], 'pc' => $st[1], 'severity' => $cur->severity, 'owner' => $cur->owner ?: 'Unassigned', 'ownerId' => (int) $cur->owner_id, 'notify' => (int) $cur->notify_customer, 'photos' => $photos, 'pieces' => $cur->pieces_expected ? (int) $cur->pieces_arrived . ' of ' . (int) $cur->pieces_expected : '', 'claim' => (int) $cur->claim_id, 'resolvedAt' => $cur->resolved_at ? sl_date($cur->resolved_at, 'M j, Y H:i') : '', 'trackHref' => $cur->entity_type === 'shipment' ? sl_route('ShipmentTracking', ['q' => $cur->entity_ref]) : ($cur->entity_type === 'consolidation' ? sl_route('Consolidations', ['q' => $cur->entity_ref]) : sl_route('FreightSchedule'))];
        }
        // partial shipments
        foreach (sl_rows("SELECT e.*, CONCAT(u.fname,' ',u.lname) AS cust FROM cdb_cargo_exceptions e LEFT JOIN cdb_users u ON u.id = e.customer_id WHERE e.type = 'partial' AND e.status IN ('open','in_progress') ORDER BY e.created_at DESC LIMIT 30") as $p) {
            $exp = max(1, (int) $p->pieces_expected);
            $arr = (int) $p->pieces_arrived;
            $d['parts'][] = ['id' => (int) $p->id, 'ref' => $p->entity_ref, 'cust' => $p->cust ?: '—', 'of' => $arr . ' of ' . $exp, 'w' => 'width:' . sl_pct($arr, $exp) . '%', 'miss' => $p->notes ?: ($exp - $arr) . ' still to come', 'split' => $p->status === 'in_progress', 'href' => sl_url(['id' => (int) $p->id, 'tab' => 'ex'])];
        }
    }
    // bulk tab: pick a source (consolidation or recent in-transit shipments)
    $src = (string) ($_GET['src'] ?? '');
    $cons = sl_rows("SELECT c_no FROM cdb_consolidate WHERE status_courier NOT IN (8,21) ORDER BY consolidate_id DESC LIMIT 4");
    $d['bsrc'] = [['label' => 'In transit', 'cls' => $src === '' ? 'on' : '', 'href' => sl_url(['src' => '', 'tab' => 'bulk'])]];
    foreach ($cons as $c) {
        $d['bsrc'][] = ['label' => $c->c_no, 'cls' => $src === $c->c_no ? 'on' : '', 'href' => sl_url(['src' => $c->c_no, 'tab' => 'bulk'])];
    }
    if ($src !== '') {
        $bulk = sl_rows("SELECT o.order_no, o.status_courier, CONCAT(u.fname,' ',u.lname) AS cust, s.mod_style FROM cdb_consolidate c JOIN cdb_consolidate_detail cd ON cd.consolidate_id = c.consolidate_id JOIN cdb_add_order o ON o.order_id = cd.order_id LEFT JOIN cdb_users u ON u.id = o.sender_id LEFT JOIN cdb_styles s ON s.id = o.status_courier WHERE c.c_no = :c AND o.is_deleted = 0 ORDER BY o.order_id DESC LIMIT 200", [':c' => $src]);
    } else {
        $bulk = sl_rows("SELECT o.order_no, o.status_courier, CONCAT(u.fname,' ',u.lname) AS cust, s.mod_style FROM cdb_add_order o LEFT JOIN cdb_users u ON u.id = o.sender_id LEFT JOIN cdb_styles s ON s.id = o.status_courier WHERE o.is_deleted = 0 AND o.status_courier IN (3,2,4,5) ORDER BY o.order_id DESC LIMIT 50");
    }
    $d['bulk'] = array_map(fn($b) => ['id' => $b->order_no, 'cust' => $b->cust ?: '—', 'st' => str_replace('_', ' ', (string) $b->mod_style)], $bulk);
    $d['bulkN'] = count($bulk);
    $d['newSt'] = array_map(fn($s) => ['id' => (int) $s->id, 'label' => str_replace('_', ' ', $s->mod_style)], sl_rows('SELECT id, mod_style FROM cdb_styles WHERE id IN (2,3,4,5,6,7,32,28) ORDER BY FIELD(id,2,3,4,28,5,6,32,7)'));
    // chain of custody
    $track = trim((string) ($_GET['track'] ?? ''));
    if ($track === '' && $tab === 'chain') {
        $track = (string) sl_scalar("SELECT order_track FROM cdb_courier_track ORDER BY id DESC", [], '');
    }
    $d['track'] = $track;
    $d['chain'] = [];
    if ($track !== '') {
        $prev = 'Shipper';
        foreach (sl_rows("SELECT t.*, s.mod_style, CONCAT(u.fname,' ',u.lname) AS who, o.name_off FROM cdb_courier_track t LEFT JOIN cdb_styles s ON s.id = t.status_courier LEFT JOIN cdb_users u ON u.id = t.user_id LEFT JOIN cdb_offices o ON o.id = t.office_id WHERE t.order_track = :t ORDER BY t.t_date, t.id LIMIT 40", [':t' => $track]) as $t) {
            $holder = match ((int) $t->status_courier) { 2 => $t->name_off ?: 'Office', 4 => 'Warehouse', 3 => 'Carrier', 5, 7 => 'Rider', 8, 15 => 'Customer', 32, 6 => 'Counter', default => $t->name_off ?: str_replace('_', ' ', (string) $t->mod_style) };
            $d['chain'][] = ['from' => $prev, 'to' => $holder, 'pic' => '', 'ini' => sl_initials($t->who ?: $holder), 'how' => str_replace('_', ' ', (string) $t->mod_style) . ($t->comments ? ' · ' . $t->comments : '') . ($t->who ? ' · by ' . $t->who : ''), 'at' => sl_date($t->t_date, 'M j H:i'), 'where' => $t->t_city ?: ($t->name_off ?: '—')];
            $prev = $holder;
        }
        if ($d['ready']) {
            foreach (sl_rows("SELECT * FROM cdb_cargo_exceptions WHERE type = 'handover' AND entity_ref = :t ORDER BY created_at", [':t' => $track]) as $h) {
                $d['chain'][] = ['from' => $h->handed_from ?: '—', 'to' => $h->handed_to ?: '—', 'pic' => '', 'ini' => sl_initials($h->handed_to ?: '?'), 'how' => $h->title . ($h->notes ? ' · ' . $h->notes : ''), 'at' => sl_date($h->created_at, 'M j H:i'), 'where' => 'Recorded hand-over'];
            }
        }
    }
    return $d;
}

/* ---------------------------------------------------------- Insurance claims */

function sl_insuranceClaimsData($user, $core): array
{
    $d = ['ready' => sl_tableExists('cdb_insurance_claims'), 'flash' => (string) ($_GET['flash'] ?? ''), 'canManage' => $user->cdp_hasPermission('manage_insurance')];
    $rate = (float) ($core->insurance ?? 0);
    $fx = (float) ($core->exchange_rate ?? 0);
    $ins = sl_row("SELECT COUNT(*) AS n, SUM(total_insured_value > 0 OR total_tax_insurance > 0) AS ins, COALESCE(SUM(total_tax_insurance),0) AS prem FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $year = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE is_deleted = 0 AND YEAR(order_date) = YEAR(CURDATE())');
    $d['kpis'] = [
        ['l' => 'Insured shipments', 'v' => (int) $ins->n ? sl_pct($ins->ins, $ins->n) . '%' : '—', 's' => sl_num($ins->n) . ' shipments this month'],
    ];
    $d['plans'] = [['n' => 'Standard cover', 'd' => 'Applied at booking from the declared value (Settings › Shipping)', 'r' => rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%', 'u' => 'of declared value']];
    $d['rate'] = $rate;
    $val = max(0, (float) ($_GET['val'] ?? 500));
    $prem = $val * $rate / 100;
    $d['val'] = rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.');
    $d['prem'] = sl_money($prem);
    $d['premG'] = $fx > 0 ? 'GHS ' . number_format($prem * $fx, 2) : 'Exchange rate not set';
    $d['cover'] = sl_money($val);
    $d['exc'] = sl_money(0);
    $insurer = sl_tableExists('cdb_freight_partners') ? sl_row("SELECT * FROM cdb_freight_partners WHERE type = 'insurer' AND active = 1 ORDER BY id") : null;
    $d['insurer'] = $insurer ? ['name' => $insurer->name, 'policy' => $insurer->code ?: '—', 'renews' => $insurer->contract_ends ? sl_date($insurer->contract_ends) : '—', 'contact' => trim(($insurer->contact_name ?: '') . ' ' . ($insurer->contact_phone ?: '')) ?: ($insurer->contact_email ?: '—')] : null;
    $d['premMonth'] = sl_money($ins->prem);
    $d['claims'] = [];
    $d['c'] = null;
    if (!$d['ready']) {
        return $d;
    }
    $k = sl_row("SELECT SUM(status NOT IN ('paid','rejected')) AS open_n, COALESCE(SUM(CASE WHEN status NOT IN ('paid','rejected') THEN claim_amount END),0) AS open_amt, AVG(CASE WHEN status = 'paid' THEN TIMESTAMPDIFF(DAY, created_at, paid_at) END) AS pay_d, SUM(YEAR(created_at) = YEAR(CURDATE())) AS year_n, COALESCE(SUM(CASE WHEN status = 'paid' AND YEAR(paid_at) = YEAR(CURDATE()) THEN COALESCE(offer_amount, claim_amount) END),0) AS paid_y FROM cdb_insurance_claims");
    $d['kpis'][] = ['l' => 'Open claims', 'v' => (string) (int) $k->open_n, 's' => sl_money($k->open_amt) . ' claimed'];
    $d['kpis'][] = ['l' => 'Average payout time', 'v' => $k->pay_d !== null ? round((float) $k->pay_d) . ' days' : '—', 's' => $k->pay_d !== null ? 'From report to paid' : 'No payouts yet'];
    $d['kpis'][] = ['l' => 'Claims this year', 'v' => (string) (int) $k->year_n, 's' => $year ? number_format((int) $k->year_n / $year * 100, 1) . '% of shipments' : '—'];
    $d['kpis'][] = ['l' => 'Premium income', 'v' => sl_money($ins->prem), 's' => 'This month · insurance tax on orders'];
    $d['paidYear'] = sl_money($k->paid_y);
    $prem12 = (float) sl_scalar('SELECT COALESCE(SUM(total_tax_insurance),0) FROM cdb_add_order WHERE is_deleted = 0 AND YEAR(order_date) = YEAR(CURDATE())');
    $d['lossRatio'] = $prem12 > 0 ? sl_pct($k->paid_y, $prem12) . '%' : '—';
    $filter = in_array($_GET['filter'] ?? 'open', ['open', 'paid', 'all'], true) ? $_GET['filter'] : 'open';
    $d['filters'] = array_map(fn($f) => ['label' => $f[1], 'cls' => $filter === $f[0] ? 'on' : '', 'href' => sl_url(['filter' => $f[0]])], [['open', 'Open'], ['paid', 'Paid or rejected'], ['all', 'All']]);
    $w = $filter === 'open' ? "status NOT IN ('paid','rejected')" : ($filter === 'paid' ? "status IN ('paid','rejected')" : '1');
    $sel = (int) ($_GET['id'] ?? 0);
    $steps = sl_frClaimSteps();
    $order = array_keys($steps);
    $cur = null;
    foreach (sl_rows("SELECT * FROM cdb_insurance_claims WHERE $w ORDER BY FIELD(status,'reported','reviewing','approved','paid','rejected'), created_at DESC LIMIT 60") as $c) {
        $isSel = $sel ? $sel === (int) $c->id : $cur === null;
        if ($isSel) $cur = $c;
        $d['claims'][] = ['id' => (int) $c->id, 'no' => $c->claim_no, 'cust' => $c->customer_name ?: '—', 'ship' => $c->order_track, 'type' => ucfirst($c->type), 'amt' => sl_money($c->claim_amount), 'st' => $c->status === 'rejected' ? 'Rejected' : ($steps[$c->status] ?? ucfirst($c->status)), 'pc' => $c->status === 'paid' ? 'delivered' : ($c->status === 'rejected' ? 'overdue' : ($c->status === 'approved' ? 'paid' : 'pending')), 'cls' => $isSel ? 'on' : '', 'href' => sl_url(['id' => (int) $c->id])];
    }
    if ($cur) {
        $idx = array_search($cur->status, $order, true);
        $stepList = [];
        foreach ($order as $i => $k2) {
            $stepList[] = ['t' => $steps[$k2], 'c' => $cur->status === 'rejected' ? '' : ($i < $idx ? 'ok' : ($i === $idx ? 'now' : ''))];
        }
        $docs = json_decode((string) $cur->documents, true) ?: [];
        $tl = json_decode((string) $cur->timeline, true) ?: [];
        $next = $idx !== false && $idx < count($order) - 1 ? $order[$idx + 1] : '';
        $d['c'] = ['id' => (int) $cur->id, 'no' => $cur->claim_no, 'cust' => $cur->customer_name ?: '—', 'ship' => $cur->order_track, 'type' => ucfirst($cur->type), 'plan' => 'Standard cover', 'd' => (string) $cur->notes, 'decl' => sl_money($cur->declared_value), 'insured' => sl_money($cur->insured_value), 'amt' => sl_money($cur->claim_amount), 'offer' => $cur->offer_amount !== null ? sl_money($cur->offer_amount) : 'After review', 'st' => $cur->status === 'rejected' ? 'Rejected' : ($steps[$cur->status] ?? ucfirst($cur->status)), 'pc' => $cur->status === 'paid' ? 'delivered' : ($cur->status === 'rejected' ? 'overdue' : ($cur->status === 'approved' ? 'paid' : 'pending')), 'status' => $cur->status, 'steps' => $stepList, 'docs' => $docs, 'timeline' => array_reverse($tl), 'next' => $next, 'nextLabel' => $next === 'paid' ? 'Mark paid out' : ($next ? 'Move to ' . $steps[$next] : ''), 'offerVal' => $cur->offer_amount !== null ? (float) $cur->offer_amount : (float) $cur->claim_amount, 'trackHref' => sl_route('ShipmentTracking', ['q' => $cur->order_track]), 'exception' => (int) $cur->exception_id];
    }
    return $d;
}

/* ---------------------------------------------------------- Freight partners */

function sl_freightPartnersData($user, $core): array
{
    $d = ['ready' => sl_tableExists('cdb_freight_partners'), 'flash' => (string) ($_GET['flash'] ?? ''), 'canManage' => $user->cdp_hasPermission('manage_freight'), 'typeList' => sl_frTypes()];
    $type = (string) ($_GET['type'] ?? 'all');
    $q = trim((string) ($_GET['q'] ?? ''));
    $d['q'] = $q;
    $d['types'] = [['label' => 'All', 'cls' => $type === 'all' ? 'on' : '', 'href' => sl_url(['type' => ''])]];
    foreach (sl_frTypes() as $k => $l) {
        $d['types'][] = ['label' => $l, 'cls' => $type === $k ? 'on' : '', 'href' => sl_url(['type' => $k])];
    }
    $d['kpis'] = [];
    $d['rows'] = [];
    $d['p'] = null;
    if (!$d['ready']) {
        return $d;
    }
    $hasSched = sl_tableExists('cdb_freight_schedules');
    $hasWb = sl_tableExists('cdb_waybills');
    $hasEx = sl_tableExists('cdb_cargo_exceptions');
    $perf = function ($p) use ($hasSched, $hasWb, $hasEx): array {
        $out = ['n' => 0, 'ok' => 0, 'ot' => null, 'delay' => null, 'issues' => 0, 'spend' => 0.0, 'vol' => 0];
        if ($hasSched) {
            $r = sl_row("SELECT COUNT(*) AS n, SUM(arrived_at <= arrives_at) AS ok, AVG(GREATEST(TIMESTAMPDIFF(MINUTE, arrives_at, arrived_at),0)) AS dl FROM cdb_freight_schedules WHERE partner_id = :p AND status = 'arrived' AND arrived_at IS NOT NULL AND arrives_at IS NOT NULL AND arrived_at >= NOW() - INTERVAL 90 DAY", [':p' => (int) $p->id]);
            $out['n'] = (int) $r->n;
            $out['ok'] = (int) $r->ok;
            $out['ot'] = (int) $r->n ? sl_pct($r->ok, $r->n) : null;
            $out['delay'] = $r->dl !== null ? (float) $r->dl : null;
            $out['vol'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_freight_schedules WHERE partner_id = :p AND YEAR(departs_at) = YEAR(CURDATE())', [':p' => (int) $p->id]);
            if ($hasEx) {
                $out['issues'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_cargo_exceptions e WHERE YEAR(e.created_at) = YEAR(CURDATE()) AND e.type IN ('damaged','missing','misrouted','delayed') AND ((e.entity_type = 'schedule' AND e.entity_ref IN (SELECT voyage_no FROM cdb_freight_schedules WHERE partner_id = :p)) OR (e.entity_type = 'waybill' AND e.entity_ref IN (SELECT waybill_no FROM cdb_waybills WHERE carrier = :n)))", [':p' => (int) $p->id, ':n' => $p->name]);
            }
        }
        if ($hasWb) {
            $out['spend'] = (float) sl_scalar('SELECT COALESCE(SUM(w.total_cost),0) FROM cdb_waybills w LEFT JOIN cdb_freight_schedules s ON s.id = w.schedule_id WHERE YEAR(w.created_at) = YEAR(CURDATE()) AND (w.carrier = :n OR s.partner_id = :p)', [':n' => $p->name, ':p' => (int) $p->id]);
        }
        return $out;
    };
    $all = sl_rows('SELECT * FROM cdb_freight_partners WHERE active = 1 ORDER BY name');
    $byType = [];
    foreach ($all as $p) $byType[$p->type] = ($byType[$p->type] ?? 0) + 1;
    $typeSum = implode(' · ', array_map(fn($k, $n) => $n . ' ' . strtolower(sl_frTypes()[$k] ?? $k) . ($n > 1 && substr($k, -1) !== 's' ? 's' : ''), array_keys($byType), $byType));
    $spendTotal = $hasWb ? (float) sl_scalar('SELECT COALESCE(SUM(total_cost),0) FROM cdb_waybills WHERE YEAR(created_at) = YEAR(CURDATE())') : 0;
    $airSpend = $hasWb ? (float) sl_scalar("SELECT COALESCE(SUM(total_cost),0) FROM cdb_waybills WHERE YEAR(created_at) = YEAR(CURDATE()) AND (container_no IS NULL OR container_no = '')") : 0;
    $perfs = [];
    $best = null;
    $worst = null;
    foreach ($all as $p) {
        $pf = $perf($p);
        $perfs[$p->id] = $pf;
        if ($pf['ot'] !== null) {
            if ($best === null || $pf['ot'] > $perfs[$best]['ot']) $best = $p->id;
            if ($worst === null || $pf['ot'] < $perfs[$worst]['ot']) $worst = $p->id;
        }
    }
    $nameOf = fn($id) => $id ? array_values(array_filter($all, fn($p) => $p->id == $id))[0]->name : '';
    $ending = count(array_filter($all, fn($p) => $p->contract_ends && strtotime($p->contract_ends) <= time() + 60 * 86400 && strtotime($p->contract_ends) >= time()));
    $d['kpis'] = [
        ['l' => 'Partners', 'v' => (string) count($all), 's' => $typeSum ?: 'None yet'],
        ['l' => 'Spend this year', 'v' => sl_money($spendTotal), 's' => $spendTotal > 0 ? 'Air ' . sl_pct($airSpend, $spendTotal) . '% · sea ' . sl_pct($spendTotal - $airSpend, $spendTotal) . '%' : 'From waybill costs'],
        ['l' => 'Best on time', 'v' => $best ? $nameOf($best) . ' ' . $perfs[$best]['ot'] . '%' : '—', 's' => $best ? 'Last 90 days' : 'No arrivals recorded'],
        ['l' => 'Needs a talk', 'v' => $worst && $perfs[$worst]['ot'] < 85 ? $nameOf($worst) : '—', 's' => $worst && $perfs[$worst]['ot'] < 85 ? $perfs[$worst]['ot'] . '% on time · ' . $perfs[$worst]['issues'] . ' issues' : 'Everyone above 85%'],
        ['l' => 'Contracts ending in 60 days', 'v' => (string) $ending, 's' => $ending ? 'Renew or renegotiate' : 'None due'],
    ];
    $sel = (int) ($_GET['id'] ?? 0);
    $cur = null;
    foreach ($all as $p) {
        if ($type !== 'all' && $p->type !== $type) continue;
        if ($q !== '' && stripos($p->name . ' ' . $p->lanes . ' ' . $p->contact_name, $q) === false) continue;
        $pf = $perfs[$p->id];
        $isSel = $sel ? $sel === (int) $p->id : $cur === null;
        if ($isSel) $cur = $p;
        $rate = $p->rate_value !== null ? sl_money($p->rate_value, $p->rate_currency ?: null) . ($p->rate_unit ? ' ' . $p->rate_unit : '') : '—';
        $d['rows'][] = ['id' => (int) $p->id, 'n' => $p->name, 'type' => sl_frTypes()[$p->type] ?? $p->type, 'icon' => sl_frTypeIcon($p->type), 'lane' => $p->lanes ?: '—', 'rate' => $rate, 'ot' => $pf['ot'] !== null ? $pf['ot'] . '%' : '—', 'w' => 'width:' . (int) ($pf['ot'] ?? 0) . '%', 'hi' => $pf['ot'] !== null && $pf['ot'] < 80 ? 'hi' : '', 'iss' => (string) $pf['issues'], 'con' => $p->contract_ends ? sl_date($p->contract_ends) : '—', 'dot' => $pf['ot'] === null ? 'var(--line)' : ($pf['ot'] >= 90 ? 'var(--green)' : ($pf['ot'] >= 80 ? 'var(--yellow)' : 'var(--red)')), 'cls' => $isSel ? 'on' : '', 'href' => sl_url(['id' => (int) $p->id])];
    }
    if ($cur) {
        $pf = $perfs[$cur->id];
        $d['p'] = ['id' => (int) $cur->id, 'n' => $cur->name, 'type' => sl_frTypes()[$cur->type] ?? $cur->type, 'typeKey' => $cur->type, 'icon' => sl_frTypeIcon($cur->type), 'lane' => $cur->lanes ?: '—', 'rate' => $cur->rate_value !== null ? sl_money($cur->rate_value, $cur->rate_currency ?: null) . ($cur->rate_unit ? ' ' . $cur->rate_unit : '') : '—', 'rateValue' => $cur->rate_value, 'rateUnit' => $cur->rate_unit ?: '', 'ot' => $pf['ot'] !== null ? $pf['ot'] . '%' : '—', 'iss' => $pf['issues'] . ' this year', 'con' => $cur->contract_ends ? sl_date($cur->contract_ends) : '—', 'who' => $cur->contact_name ?: '—', 'ph' => trim(($cur->contact_phone ?: '') . ' ' . ($cur->contact_email ?: '')) ?: '—', 'spend' => sl_money($pf['spend']), 'vol' => (string) $pf['vol'], 'api' => $cur->api_link ?: 'None', 'delay' => $pf['delay'] !== null ? round($pf['delay'] / 60, 1) . ' h' : '—', 'code' => $cur->code ?: '', 'notes' => (string) $cur->notes, 'email' => $cur->contact_email ?: '', 'phone' => $cur->contact_phone ?: '', 'contractRaw' => $cur->contract_ends ?: ''];
    }
    $d['count'] = count($d['rows']);
    return $d;
}
