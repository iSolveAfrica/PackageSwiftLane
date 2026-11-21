<?php
/**
 * Data for the staff Overview (Main.dc.html). Every figure is computed from the
 * database; sections whose source table is not installed yet degrade to zero.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_charts.php';
require_once CDP_ROOT . '/helpers/finance_switch.php';
require_once CDP_ROOT . '/helpers/sl_nav.php';
require_once CDP_ROOT . '/helpers/rbac.php';
require_once __DIR__ . '/ops_common.php';
require_once __DIR__ . '/overview_charts.php';
require_once __DIR__ . '/overview_hero.php'; // sl_ovScalar() shared with the hero cards

function sl_overviewData($user, $core, $userData): array
{
    $d = [];
    $d['insights'] = sl_overviewChartsData($user);
    $uid = (int) $_SESSION['userid'];
    $agency = sl_navAgencyWhere('a');
    $hasApprove = sl_columnExists('cdb_users', 'approve');
    $hasHeld = true; // statuses 28/29 may not exist on old installs; counts are simply 0 then
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');

    // ── Your desk ──────────────────────────────────────────────────────────
    $d['me'] = ['name' => trim(($userData->fname ?? '') . ' ' . ($userData->lname ?? '')), 'ini' => sl_initials(($userData->fname ?? '') . ' ' . ($userData->lname ?? ''))];
    $shiftStart = 8; $shiftEnd = 17; // working day used for the shift meter
    $h = (int) date('G') + (int) date('i') / 60;
    $d['shift'] = ['label' => sprintf('Shift %02d:00–%02d:00', $shiftStart, $shiftEnd), 'pct' => (int) max(0, min(100, round(($h - $shiftStart) / ($shiftEnd - $shiftStart) * 100)))];
    $doneToday = sl_tableExists('cdb_activity_log') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_activity_log WHERE user_id = :u AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY AND outcome = :o', [':u' => $uid, ':o' => 'ok']) : 0;

    $queues = [];
    $kyc = $hasApprove ? (int) sl_ovScalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel = 1 AND approve = 0 AND active = 1') : 0;
    $kycOld = $hasApprove ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel = 1 AND approve = 0 AND active = 1 AND created < NOW() - INTERVAL 1 DAY') : 0;
    if ($user->cdp_hasPermission('view_client_list')) {
        $queues[] = ['href' => sl_route('Customers', ['filter' => 'unapproved']), 'icon' => 'i-shield', 'label' => 'KYC to review', 'sub' => $kycOld ? "$kycOld waiting over 24h" : 'All reviewed within a day', 'tone' => $kycOld ? 'var(--amber-ink)' : 'var(--muted)', 'n' => $kyc];
    }
    $prealerts = (int) sl_ovScalar('SELECT COUNT(*) FROM cdb_pre_alert WHERE is_package = 0');
    if ($user->cdp_hasPermission('prealert_list')) {
        $queues[] = ['href' => sl_route('PreAlerts'), 'icon' => 'i-upload', 'label' => 'Pre-alerts to match', 'sub' => 'Waiting for a package', 'tone' => 'var(--muted)', 'n' => $prealerts];
    }
    $toPrint = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (1,2) AND a.order_date >= CURDATE() AND a.order_date < CURDATE() + INTERVAL 1 DAY $agency");
    if ($user->cdp_hasPermission('view_shipment_list')) {
        $queues[] = ['href' => sl_route('AirShipments', ['status' => 'new']), 'icon' => 'i-print', 'label' => 'Labels to print', 'sub' => 'Registered today', 'tone' => 'var(--muted)', 'n' => $toPrint];
    }
    $held = (int) sl_ovScalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (12,25,28,29) $agency");
    if ($user->cdp_hasPermission(['warehouse_view', 'view_shipment_list'])) {
        $queues[] = ['href' => sl_route('Warehouse', ['filter' => 'exceptions']), 'icon' => 'i-alert', 'label' => 'Warehouse exceptions', 'sub' => $held ? 'Held, rejected or not shipped' : 'No exceptions', 'tone' => $held ? 'var(--red-ink)' : 'var(--muted)', 'n' => $held];
    }
    $driversOut = (int) sl_ovScalar("SELECT COUNT(DISTINCT a.driver_id) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 7 AND a.driver_id > 0 $agency");
    if ($user->cdp_hasPermission('view_user_list')) {
        $queues[] = ['href' => sl_route('Drivers'), 'icon' => 'i-wheel', 'label' => 'Riders on route', 'sub' => 'Deliveries in progress', 'tone' => 'var(--muted)', 'n' => $driversOut];
    }
    $aging = sl_tableExists('cdb_package_pickup_aging') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_package_pickup_aging WHERE not_picked_at IS NOT NULL AND auction_at IS NULL') : (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (6,32) AND a.order_date < CURDATE() - INTERVAL 60 DAY $agency");
    if ($user->cdp_hasPermission('view_pickup_aging')) {
        $queues[] = ['href' => sl_route('PickupAging'), 'icon' => 'i-hourglass', 'label' => 'Unclaimed, near auction', 'sub' => 'Over 60 days in bay', 'tone' => $aging ? 'var(--amber-ink)' : 'var(--muted)', 'n' => $aging];
    }
    $d['queues'] = $queues;
    $d['queueTotal'] = array_sum(array_column($queues, 'n'));
    $d['doneToday'] = $doneToday;

    // ── Three metrics ──────────────────────────────────────────────────────
    $revenue = (float) sl_scalar("SELECT COALESCE(SUM(a.total_order),0) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date >= :m $agency", [':m' => $monthStart]);
    $parcelsMonth = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date >= :m $agency", [':m' => $monthStart]);
    $target = sl_tableExists('cdb_sl_settings') ? (float) sl_scalar("SELECT setting_value FROM cdb_sl_settings WHERE setting_key = 'revenue_target_month'", [], 0) : 0;
    $d['revenue'] = ['value' => $revenue, 'parcels' => $parcelsMonth, 'target' => $target, 'pct' => $target > 0 ? sl_pct($revenue, $target) : 0, 'next' => date('M j, Y', strtotime('first day of next month'))];
    $pickupsToday = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND a.order_date >= CURDATE() AND a.order_date < CURDATE() + INTERVAL 1 DAY $agency");
    $pickupsDone = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND a.order_date >= CURDATE() AND a.order_date < CURDATE() + INTERVAL 1 DAY AND a.status_courier NOT IN (1,14) $agency");
    $pickupsUnassigned = (int) sl_ovScalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND a.status_courier IN (1,14) AND (a.driver_id IS NULL OR a.driver_id = 0) $agency");
    $d['pickups'] = ['done' => $pickupsDone, 'total' => $pickupsToday, 'pct' => sl_pct($pickupsDone, $pickupsToday), 'drivers' => $driversOut, 'unassigned' => $pickupsUnassigned];
    $inBay = (int) sl_ovScalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (4,6,32) $agency") + (int) sl_ovScalar("SELECT COUNT(*) FROM cdb_customers_packages a WHERE a.status_courier IN (4,6,32) $agency");
    $capacity = sl_tableExists('cdb_sl_settings') ? (int) sl_ovScalar("SELECT setting_value FROM cdb_sl_settings WHERE setting_key = 'warehouse_bays'", [], 0) : 0;
    $arriving = (int) sl_ovScalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 3 $agency");
    $awaiting = (int) sl_ovScalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (6,32) $agency");
    $d['warehouse'] = ['used' => $inBay, 'capacity' => $capacity, 'pct' => $capacity > 0 ? sl_pct($inBay, $capacity) : 0, 'arriving' => $arriving, 'awaiting' => $awaiting];
    $registeredToday = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date >= CURDATE() AND a.order_date < CURDATE() + INTERVAL 1 DAY $agency");
    $d['deskStats'] = [];
    if ($user->cdp_hasPermission('view_shipment_list')) {
        $handedToday = (int) sl_scalar("SELECT COUNT(DISTINCT a.order_id) FROM cdb_add_order a JOIN cdb_courier_track t ON t.order_track = CONCAT(a.order_prefix, a.order_no) WHERE a.is_deleted = 0 AND t.status_courier IN (8,15) AND t.t_date >= CURDATE() AND t.t_date < CURDATE() + INTERVAL 1 DAY $agency");
        $d['deskStats'][] = ['label' => 'Registered today', 'n' => $registeredToday, 'sub' => 'New air shipments', 'icon' => 'i-plus', 'href' => sl_route('AirShipments', ['period' => 'today'])];
        $d['deskStats'][] = ['label' => 'Handed over today', 'n' => $handedToday, 'sub' => 'Picked up or delivered', 'icon' => 'i-check', 'href' => sl_route('AirShipments', ['filter' => 'delivered'])];
    }
    if ($user->cdp_hasPermission(['view_warehouse_delivery', 'warehouse_deliver_package'])) {
        $d['deskStats'][] = ['label' => 'Ready for collection', 'n' => $awaiting, 'sub' => 'Air shipments awaiting handover', 'icon' => 'i-box', 'href' => sl_route('HandOut')];
    }

    // ── Smart briefing: rule based cards from live figures ─────────────────
    // Each card: level (hi needs action, md soon, ok fine, in info), the figure, why it matters, 2-3 facts
    // that explain it (age, split, money), an optional meter and two ways in. Sorted most urgent first.
    $brief = [];
    $ageD = fn($dt) => $dt ? max(0, (int) floor((time() - strtotime((string) $dt)) / 86400)) : 0;
    $plural = fn($n, $w) => sl_num($n) . ' ' . $w . ((int) $n === 1 ? '' : 's');
    $hasEta = sl_columnExists('cdb_add_order', 'estimated_eta');
    if ($held) {
        $split = sl_rows("SELECT a.status_courier AS st, COUNT(*) AS n, MIN(a.order_date) AS oldest FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (12,25,28,29) $agency GROUP BY a.status_courier ORDER BY n DESC");
        $oldest = min(array_map(fn($r) => (string) $r->oldest, $split) ?: ['']);
        $facts = array_map(fn($r) => [ucfirst(strtolower(sl_statusLabel($r->st))), sl_num($r->n)], array_slice($split, 0, 2));
        $facts[] = ['Oldest', $ageD($oldest) . ' d'];
        $brief[] = ['lvl' => 'hi', 'rank' => 1, 'icon' => 'i-alert', 'tag' => 'Warehouse', 'sev' => 'Needs action', 'big' => sl_num($held), 'bigS' => 'parcels held',
            't' => 'Parcels on hold', 'd' => 'Rejected, held or not shipped. Clear them before the next departure so they do not miss the flight.',
            'facts' => $facts, 'cta' => 'Open exceptions', 'href' => sl_route('Warehouse', ['filter' => 'exceptions']), 'cat' => 'ops'];
    }
    if ($hasEta) {
        $lateR = sl_row("SELECT COUNT(*) AS n, MIN(a.estimated_eta) AS worst FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.estimated_eta IS NOT NULL AND a.estimated_eta < CURDATE() AND a.status_courier NOT IN (6,32,8,15,21,12) $agency");
        if ($lateR && (int) $lateR->n) {
            $arrivals = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.estimated_eta >= CURDATE() AND a.estimated_eta < CURDATE() + INTERVAL 1 DAY AND a.status_courier NOT IN (6,32,8,15,21) $agency");
            $brief[] = ['lvl' => 'hi', 'rank' => 2, 'icon' => 'i-clock', 'tag' => 'Transit', 'sev' => 'Late', 'big' => sl_num($lateR->n), 'bigS' => 'past their ETA',
                't' => 'Shipments running late', 'd' => 'The promised date has passed and they have not reached the warehouse. Update the ETA or tell the customer.',
                'facts' => [['Latest by', $ageD($lateR->worst) . ' d'], ['Due today', sl_num($arrivals)]], 'cta' => 'Live tracking', 'href' => sl_route('LiveTracking', ['f' => 'late']), 'cat' => 'ops'];
        }
    }
    if ($pickupsUnassigned) {
        $pToday = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND a.status_courier IN (1,14) AND (a.driver_id IS NULL OR a.driver_id = 0) AND a.order_date >= CURDATE() $agency");
        $ridersOn = (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel = 3 AND active = 1');
        $pOld = $pickupsUnassigned - $pToday;
        $brief[] = ['lvl' => $pOld > 0 ? 'hi' : 'md', 'rank' => $pOld > 0 ? 3 : 5, 'icon' => 'i-truck', 'tag' => 'Pickups', 'sev' => $pOld > 0 ? 'Overdue' : 'Today', 'big' => sl_num($pickupsUnassigned), 'bigS' => 'without a rider',
            't' => 'Pickups waiting for a rider', 'd' => $pOld > 0 ? $plural($pOld, 'pickup') . ' booked before today still have nobody assigned.' : 'Assign riders so every window today is covered.',
            'facts' => [['Today', sl_num($pToday)], ['Earlier', sl_num($pOld)], ['Riders on', sl_num($ridersOn)]],
            'meter' => ['pct' => $ridersOn ? min(100, (int) round($pickupsUnassigned / max(1, $ridersOn * 8) * 100)) : 100, 'l' => $ridersOn ? '≈ ' . round($pickupsUnassigned / max(1, $ridersOn), 1) . ' per rider' : 'No active riders'],
            'cta' => 'Dispatch board', 'href' => sl_route('RiderDispatch'), 'cat' => 'ops'];
    }
    if (sl_tableExists('cdb_cs_tickets') && function_exists('cdp_csIsAgent') && cdp_csIsAgent($user)) {
        $wait = sl_row("SELECT COUNT(*) AS n, MIN(created_at) AS oldest, SUM(created_at < NOW() - INTERVAL 30 MINUTE) AS slow FROM cdb_cs_tickets WHERE status NOT IN ('resolved','closed','merged') AND human_first_response_at IS NULL");
        if ($wait && (int) $wait->n) {
            $mins = (int) floor((time() - strtotime((string) $wait->oldest)) / 60);
            $brief[] = ['lvl' => (int) $wait->slow ? 'hi' : 'md', 'rank' => (int) $wait->slow ? 2 : 6, 'icon' => 'i-message', 'tag' => 'Customers', 'sev' => (int) $wait->slow ? 'Slow reply' : 'Waiting', 'big' => sl_num($wait->n), 'bigS' => 'waiting for a person',
                't' => 'Customers waiting for a reply', 'd' => 'No one from the team has answered yet.',
                'facts' => [['Longest', $mins >= 60 ? floor($mins / 60) . ' h ' . ($mins % 60) . ' m' : $mins . ' min'], ['Over 30 min', sl_num($wait->slow)]], 'cta' => 'Support desk', 'href' => sl_route('CsInbox'), 'cat' => 'people'];
        }
    }
    $overdue = 0;
    if (cdp_financeEnabled()) {
        $od = sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(a.total_order), 0) AS amt, MIN(a.due_date) AS oldest FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_invoice = 23 AND a.due_date < CURDATE() $agency");
        $overdue = (int) ($od->n ?? 0);
        if ($overdue) $brief[] = ['lvl' => 'md', 'rank' => 4, 'icon' => 'i-receipt', 'tag' => 'Finance', 'sev' => 'Overdue', 'big' => sl_num($overdue), 'bigS' => 'invoices past due',
            't' => 'Invoices past due', 'd' => 'Send reminders or record the payments that came in.',
            'facts' => array_values(array_filter([cdp_canViewMoney($user) ? ['Outstanding', sl_money($od->amt)] : null, ['Oldest', $ageD($od->oldest) . ' d late']])), 'cta' => 'Accounts receivable', 'href' => sl_route('AccountsReceivable'), 'cat' => 'money'];
    }
    if ($kyc) {
        $kOld = sl_scalar('SELECT MIN(created) FROM cdb_users WHERE userlevel = 1 AND approve = 0 AND active = 1', [], null);
        $brief[] = ['lvl' => $kycOld ? 'md' : 'in', 'rank' => $kycOld ? 6 : 8, 'icon' => 'i-shield', 'tag' => 'Customers', 'sev' => $kycOld ? 'Waiting' : 'New', 'big' => sl_num($kyc), 'bigS' => 'to verify',
            't' => 'New sign-ups to approve', 'd' => 'Check the ID and approve so they can ship.',
            'facts' => [['Over a day', sl_num($kycOld)], ['Oldest', $ageD($kOld) . ' d']], 'cta' => 'Review customers', 'href' => sl_route('Customers', ['filter' => 'unapproved']), 'cat' => 'people'];
    }
    if ($awaiting) {
        $rd = sl_row("SELECT SUM(a.order_date < CURDATE() - INTERVAL 7 DAY) AS w7, SUM(a.order_date < CURDATE() - INTERVAL 30 DAY) AS w30, SUM(a.status_invoice NOT IN (1,19)) AS unpaid FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (6,32) $agency");
        $w30 = (int) ($rd->w30 ?? 0);
        $brief[] = ['lvl' => $w30 ? 'md' : 'ok', 'rank' => $w30 ? 7 : 9, 'icon' => 'i-check', 'tag' => 'Warehouse', 'sev' => $w30 ? 'Slow to collect' : 'Ready', 'big' => sl_num($awaiting), 'bigS' => 'ready to collect',
            't' => 'Parcels ready for pickup', 'd' => 'Customers can collect. Remind the ones that have not come; long stays fill the bays.',
            'facts' => [['7 d+', sl_num($rd->w7 ?? 0)], ['30 d+', sl_num($w30)], ['Unpaid', sl_num($rd->unpaid ?? 0)]],
            'meter' => $capacity ? ['pct' => min(100, sl_pct($inBay, $capacity)), 'l' => sl_pct($inBay, $capacity) . '% of bays in use'] : null,
            'cta' => 'Hand out', 'href' => sl_route('HandOut'), 'cat' => 'ops'];
    }
    if (!$brief) $brief[] = ['lvl' => 'ok', 'rank' => 9, 'icon' => 'i-check', 'tag' => 'All clear', 'sev' => 'Now', 'big' => '0', 'bigS' => 'issues', 't' => 'Nothing needs your attention', 'd' => 'Queues are empty and no exceptions are open.', 'facts' => [], 'cta' => 'See activity', 'href' => sl_route('ActivityLogs'), 'cat' => 'ops'];
    usort($brief, fn($x, $y) => $x['rank'] <=> $y['rank']);
    $d['brief'] = $brief;
    $urgent = count(array_filter($brief, fn($b) => $b['lvl'] === 'hi'));
    $d['briefLead'] = count($brief) === 1 && $brief[0]['tag'] === 'All clear' ? 'Everything is on track.'
        : count($brief) . ' thing' . (count($brief) > 1 ? 's' : '') . ' to look at today' . ($urgent ? ', ' . $urgent . ' need' . ($urgent === 1 ? 's' : '') . ' action now.' : '.');
    $catN = array_count_values(array_column($brief, 'cat'));
    $d['bfil'] = [['label' => 'All', 'n' => count($brief), 'cls' => 'on', 'f' => 'all']];
    foreach (['ops' => 'Operations', 'money' => 'Money', 'people' => 'People'] as $ck => $cl) if (!empty($catN[$ck])) $d['bfil'][] = ['label' => $cl, 'n' => $catN[$ck], 'cls' => '', 'f' => $ck];

    // ── Live pulse ─────────────────────────────────────────────────────────
    $d['pulse'] = [
        ['l' => 'In transit', 'v' => sl_num($arriving), 's' => 'air + road', 'href' => sl_route('AirShipments', ['status' => 3]), 'a' => ''],
        ['l' => 'In warehouse', 'v' => sl_num($inBay), 's' => 'bays used', 'href' => sl_route('Warehouse'), 'a' => ''],
        ['l' => 'Out for delivery', 'v' => sl_num(sl_ovScalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 7 $agency")), 's' => "$driversOut riders", 'href' => sl_route('DeliveryTracking'), 'a' => ''],
        ['l' => 'Delivered today', 'v' => sl_num(sl_scalar("SELECT COUNT(*) FROM cdb_courier_track t WHERE t.status_courier IN (8,15) AND t.t_date >= CURDATE() AND t.t_date < CURDATE() + INTERVAL 1 DAY")), 's' => 'since midnight', 'href' => sl_route('AirShipments', ['status' => 8]), 'a' => 'g'],
        ['l' => 'Registered today', 'v' => sl_num($registeredToday), 's' => 'new shipments', 'href' => sl_route('AirShipments'), 'a' => ''],
        ['l' => 'Open tickets', 'v' => sl_num(sl_tableExists('cdb_cs_tickets') ? sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE status NOT IN ('closed','resolved')") : 0), 's' => 'customer service', 'href' => sl_route('CsInbox'), 'a' => ''],
    ];
    $d['clock'] = date('H:i');
    $ticker = [];
    if (sl_tableExists('cdb_activity_log')) {
        foreach (sl_rows('SELECT actor_name, summary, created_at, outcome FROM cdb_activity_log WHERE action <> :pv ORDER BY id DESC LIMIT 6', [':pv' => 'page_view']) as $r) {
            $ticker[] = ['who' => $r->actor_name ?: 'System', 'what' => $r->summary, 'at' => sl_ago($r->created_at), 'cls' => $r->outcome === 'fail' ? 'bad' : '', 'ini' => sl_initials($r->actor_name ?: 'S')];
        }
    }
    $d['ticker'] = $ticker;

    // ── Next 14 days forecast: expected arrivals by ETA ────────────────────
    $fc = [];
    $capRef = max(1, $capacity ?: 50);
    $etaRows = [];
    if (sl_columnExists('cdb_add_order', 'estimated_eta')) {
        $etaRows = sl_rows("SELECT DATE(estimated_eta) AS d, COUNT(*) AS c FROM cdb_add_order WHERE is_deleted = 0 AND estimated_eta BETWEEN CURDATE() AND CURDATE() + INTERVAL 13 DAY AND status_courier NOT IN (8,15,21) GROUP BY DATE(estimated_eta)");
    } elseif (sl_tableExists('cdb_package_tracking_number')) {
        $etaRows = sl_rows("SELECT DATE(estimated_eta) AS d, COUNT(*) AS c FROM cdb_package_tracking_number WHERE estimated_eta BETWEEN CURDATE() AND CURDATE() + INTERVAL 13 DAY GROUP BY DATE(estimated_eta)");
    }
    $etaMap = [];
    foreach ($etaRows as $r) $etaMap[$r->d] = (int) $r->c;
    $peakFc = max(1, max($etaMap ?: [0]), $capRef);
    for ($i = 0; $i < 14; $i++) {
        $day = date('Y-m-d', strtotime("+$i days"));
        $n = $etaMap[$day] ?? 0;
        $fc[] = ['l' => $i % 2 === 0 ? date('D j', strtotime($day)) : '', 'tip' => date('D, M j', strtotime($day)) . ": $n expected", 'h' => 'height:' . max(2, (int) round($n / $peakFc * 100)) . '%', 'c' => $n > $capRef ? 'over' : ($n > $capRef * 0.8 ? 'near' : '')];
    }
    $d['fc'] = $fc;
    $d['capY'] = 'bottom:' . (int) round($capRef / $peakFc * 100) . '%';
    $d['capacityLabel'] = $capacity ? "Bay capacity $capacity" : 'Set bay capacity in Settings';
    $over = count(array_filter($etaMap, fn($n) => $n > $capRef));
    $d['fcPill'] = $over ? 'overdue' : 'delivered';
    $d['fcNote'] = $over ? "$over day" . ($over > 1 ? 's' : '') . ' over capacity' : 'Within capacity';

    // ── US packages pipeline (sea / locker packages) ───────────────────────
    $pk = fn(string $where) => (int) sl_scalar("SELECT COUNT(*) FROM cdb_customers_packages a WHERE $where $agency");
    $d['pipe'] = [
        ['icon' => 'i-bell', 'n' => $prealerts, 'label' => 'Pre-alerted', 'sub' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_pre_alert WHERE is_package = 1 AND prealert_date >= CURDATE() AND prealert_date < CURDATE() + INTERVAL 1 DAY') . ' matched today'],
        ['icon' => 'i-warehouse', 'n' => $pk('a.status_courier = 2'), 'label' => 'Collected US', 'sub' => $pk('a.status_courier = 2 AND a.order_date >= CURDATE() AND a.order_date < CURDATE() + INTERVAL 1 DAY') . ' today'],
        ['icon' => 'i-upload', 'n' => $pk('a.order_date >= CURDATE() - INTERVAL 14 DAY'), 'label' => 'Input', 'sub' => 'last 14 days'],
        ['icon' => 'i-layers', 'n' => $pk('a.is_consolidate = 1 AND a.status_courier NOT IN (8,15)'), 'label' => 'Consolidated', 'sub' => (int) sl_scalar("SELECT COUNT(*) FROM cdb_consolidate_packages WHERE status_courier NOT IN ('3','8','15','21')") . ' boxes open'],
        ['icon' => 'i-plane', 'n' => $pk('a.status_courier = 3'), 'label' => 'On the way', 'sub' => 'in transit'],
        ['icon' => 'i-pin', 'n' => $pk('a.status_courier = 4'), 'label' => 'Arrived', 'sub' => 'in warehouse'],
        ['icon' => 'i-check', 'n' => $pk('a.status_courier IN (6,32)'), 'label' => 'Ready for pickup', 'sub' => 'codes sent'],
        ['icon' => 'i-user', 'n' => (int) sl_scalar("SELECT COUNT(*) FROM cdb_courier_track t WHERE t.status_courier = 15 AND t.t_date >= CURDATE() AND t.t_date < CURDATE() + INTERVAL 1 DAY"), 'label' => 'Picked up', 'sub' => 'today'],
    ];
    $collected = array_values(sl_dailyCounts('cdb_customers_packages', 'order_date', 14));
    $input = array_values(sl_dailyCounts('cdb_add_order', 'order_date', 14, ' AND is_deleted = 0'));
    $maxCI = max(1, max($collected), max($input));
    $bars = [];
    foreach ($collected as $i => $v) {
        $x = 6 + $i * 45.7;
        $hgt = round($v / $maxCI * 150, 1);
        $bars[] = sprintf('<rect x="%.1f" y="%.1f" width="33.7" height="%.1f" rx="4" fill="%s"/>', $x, 170 - $hgt, $hgt, $i === 13 ? '#8E8A82' : '#D6D2CA');
    }
    $d['ciBars'] = implode('', $bars);
    $d['ciSeries'] = [['name' => 'Packages collected', 'values' => $collected], ['name' => 'Shipments input', 'values' => $input]];
    $d['ciDays'] = array_map(fn($i) => date('M j, Y', strtotime('-' . (13 - $i) . ' days')), range(0, 13));
    $d['ciLine'] = sl_svgPoints($input, 640, 170, 20, $maxCI, 22.9);
    $pts = explode(' ', $d['ciLine']);
    $d['ciDots'] = implode('', array_map(fn($p) => '<circle cx="' . explode(',', $p)[0] . '" cy="' . explode(',', $p)[1] . '" r="3" fill="#E08A3C"/>', array_filter($pts)));
    $d['ciLabels'] = [date('M j', strtotime('-13 days')), date('M j', strtotime('-7 days')), date('M j')];
    $d['ciTotals'] = ['collected' => array_sum($collected), 'input' => array_sum($input), 'weight' => (float) sl_scalar("SELECT COALESCE(AVG(a.total_weight),0) FROM cdb_customers_packages a WHERE a.order_date >= CURDATE() - INTERVAL 14 DAY")];
    $carriers = sl_rows("SELECT c.name_com AS name, COUNT(*) AS n FROM cdb_customers_packages a JOIN cdb_courier_com c ON c.id = a.order_courier WHERE a.order_date >= CURDATE() - INTERVAL 30 DAY GROUP BY c.id ORDER BY n DESC LIMIT 5");
    $ctot = array_sum(array_map(fn($r) => (int) $r->n, $carriers)) ?: 1;
    $cmax = $carriers ? (int) $carriers[0]->n : 1;
    $d['carriers'] = array_map(fn($r) => ['name' => $r->name, 'pct' => sl_pct($r->n, $ctot), 'w' => sl_pct($r->n, $cmax)], $carriers);
    $deps = [];
    foreach (['cdb_consolidate' => 'i-plane', 'cdb_consolidate_packages' => 'i-ship'] as $tbl => $icon) {
        $etaCol = sl_columnExists($tbl, 'estimated_eta') ? 'estimated_eta' : 'c_date';
        $r = sl_row("SELECT c_prefix, c_no, $etaCol AS eta, (SELECT COUNT(*) FROM {$tbl}_detail dd WHERE dd.consolidate_id = c.consolidate_id) AS n FROM $tbl c WHERE c.status_courier NOT IN ('8','15','21') ORDER BY $etaCol DESC");
        if ($r) $deps[] = ['icon' => $icon, 'title' => ($tbl === 'cdb_consolidate' ? 'Next air · ' : 'Next sea · ') . $r->c_prefix . $r->c_no, 'sub' => $r->eta ? 'ETA ' . sl_date($r->eta) : 'No ETA set', 'n' => (int) $r->n];
    }
    $d['departures'] = $deps;

    // ── Payment gateways ───────────────────────────────────────────────────
    $gw = [];
    foreach (sl_rows('SELECT id, name_pay, detail_pay, is_active, public_key FROM cdb_met_payment ORDER BY is_active DESC, id LIMIT 2') as $g) {
        $gw[] = ['name' => $g->name_pay, 'desc' => strip_tags((string) $g->detail_pay), 'active' => (int) $g->is_active === 1, 'configured' => !empty($g->public_key), 'href' => 'payment_mode_list.php'];
    }
    $d['gateways'] = $gw;

    // ── Live tracking console (rows already scoped to the viewer's role) ────
    require_once __DIR__ . '/track_console.php';
    $d['tracking'] = sl_trackConsoleData($user, $core, 'shipments', 10);

    // ── Top of the page: view bar, "you" card, today cards, freight ────────
    require_once __DIR__ . '/overview_hero.php';
    $d['view'] = sl_ovView($user);
    $d['profile'] = sl_ovProfile($user, $userData, $d['view']);
    $d['cards'] = sl_ovCards($user, $d['view']);
    if (cdp_financeEnabled() && cdp_canViewMoney($user, 'dashboard')) {
        $rv = $d['revenue'];
        $rvDaily = sl_ovZeroDays();
        foreach (sl_rows("SELECT DATE(a.order_date) AS day, SUM(a.total_order) AS n FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date >= :d $agency GROUP BY DATE(a.order_date)", [':d' => array_key_first($rvDaily)]) as $r) {
            if (isset($rvDaily[$r->day])) $rvDaily[$r->day] = (float) $r->n;
        }
        $d['cards'][] = ['key' => 'billing', 'tone' => 'amber', 'icon' => 'i-dollar', 'eyebrow' => 'Billing this month', 'big' => sl_money($rv['value']),
            'lede' => sl_num($rv['parcels']) . ' parcels billed · next settlement ' . $rv['next'],
            'sub' => $rv['target'] > 0 ? [['n' => (int) $rv['pct'] . '%', 'l' => 'Of target'], ['n' => sl_money($rv['target']), 'l' => 'Target']] : [],
            'bars' => sl_sparkBars(array_values($rvDaily), 4, array_map(fn($x) => date('D j M', strtotime($x)), array_keys($rvDaily))), 'barsL' => 'Billed per day, 14 days', 'barsR' => sl_money(array_sum($rvDaily)),
            'href' => sl_route('FinancialOverview'), 'alert' => ''];
    }
    $d['freight'] = sl_ovFreight($user, $d['view']);
    return $d;
}
