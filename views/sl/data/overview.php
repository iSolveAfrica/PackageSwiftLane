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

function sl_overviewData($user, $core, $userData): array
{
    $d = [];
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
    $doneToday = sl_tableExists('cdb_activity_log') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_activity_log WHERE user_id = :u AND DATE(created_at) = CURDATE() AND outcome = :o', [':u' => $uid, ':o' => 'ok']) : 0;

    $queues = [];
    $kyc = $hasApprove ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel = 1 AND approve = 0 AND active = 1') : 0;
    $kycOld = $hasApprove ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel = 1 AND approve = 0 AND active = 1 AND created < NOW() - INTERVAL 1 DAY') : 0;
    if ($user->cdp_hasPermission('view_client_list')) {
        $queues[] = ['href' => sl_route('Customers', ['filter' => 'unapproved']), 'icon' => 'i-shield', 'label' => 'KYC to review', 'sub' => $kycOld ? "$kycOld waiting over 24h" : 'All reviewed within a day', 'tone' => $kycOld ? 'var(--amber-ink)' : 'var(--muted)', 'n' => $kyc];
    }
    $prealerts = (int) sl_scalar('SELECT COUNT(*) FROM cdb_pre_alert WHERE is_package = 0');
    if ($user->cdp_hasPermission('prealert_list')) {
        $queues[] = ['href' => sl_route('PreAlerts'), 'icon' => 'i-upload', 'label' => 'Pre-alerts to match', 'sub' => 'Waiting for a package', 'tone' => 'var(--muted)', 'n' => $prealerts];
    }
    $toPrint = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (1,2) AND DATE(a.order_date) = CURDATE() $agency");
    if ($user->cdp_hasPermission('view_shipment_list')) {
        $queues[] = ['href' => sl_route('AirShipments', ['status' => 'new']), 'icon' => 'i-print', 'label' => 'Labels to print', 'sub' => 'Registered today', 'tone' => 'var(--muted)', 'n' => $toPrint];
    }
    $held = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (12,25,28,29) $agency");
    if ($user->cdp_hasPermission(['warehouse_view', 'view_shipment_list'])) {
        $queues[] = ['href' => sl_route('Warehouse', ['filter' => 'exceptions']), 'icon' => 'i-alert', 'label' => 'Warehouse exceptions', 'sub' => $held ? 'Held, rejected or not shipped' : 'No exceptions', 'tone' => $held ? 'var(--red-ink)' : 'var(--muted)', 'n' => $held];
    }
    $driversOut = (int) sl_scalar("SELECT COUNT(DISTINCT a.driver_id) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 7 AND a.driver_id > 0 $agency");
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
    $pickupsToday = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND DATE(a.order_date) = CURDATE() $agency");
    $pickupsDone = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND DATE(a.order_date) = CURDATE() AND a.status_courier NOT IN (1,14) $agency");
    $pickupsUnassigned = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND a.status_courier IN (1,14) AND (a.driver_id IS NULL OR a.driver_id = 0) $agency");
    $d['pickups'] = ['done' => $pickupsDone, 'total' => $pickupsToday, 'pct' => sl_pct($pickupsDone, $pickupsToday), 'drivers' => $driversOut, 'unassigned' => $pickupsUnassigned];
    $inBay = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (4,6,32) $agency") + (int) sl_scalar("SELECT COUNT(*) FROM cdb_customers_packages a WHERE a.status_courier IN (4,6,32) $agency");
    $capacity = sl_tableExists('cdb_sl_settings') ? (int) sl_scalar("SELECT setting_value FROM cdb_sl_settings WHERE setting_key = 'warehouse_bays'", [], 0) : 0;
    $arriving = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 3 $agency");
    $awaiting = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (6,32) $agency");
    $d['warehouse'] = ['used' => $inBay, 'capacity' => $capacity, 'pct' => $capacity > 0 ? sl_pct($inBay, $capacity) : 0, 'arriving' => $arriving, 'awaiting' => $awaiting];

    // ── Smart briefing: rule based cards from live figures ─────────────────
    $brief = [];
    if ($held) $brief[] = ['lvl' => 'red', 'icon' => 'i-alert', 'tag' => 'Warehouse', 'sev' => 'Needs action', 'big' => $held, 'bigS' => 'held', 't' => 'Parcels on hold', 'd' => 'Rejected, held or not shipped. Clear them before the next departure.', 'cta' => 'Open warehouse', 'href' => sl_route('Warehouse', ['filter' => 'exceptions']), 'cat' => 'ops'];
    if ($pickupsUnassigned) $brief[] = ['lvl' => 'amber', 'icon' => 'i-truck', 'tag' => 'Pickups', 'sev' => 'Today', 'big' => $pickupsUnassigned, 'bigS' => 'unassigned', 't' => 'Pickups without a rider', 'd' => 'Assign riders so every window is covered.', 'cta' => 'Dispatch board', 'href' => sl_route('RiderDispatch'), 'cat' => 'ops'];
    $overdue = cdp_financeEnabled() ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_invoice = 23 AND a.due_date < CURDATE() $agency") : 0;
    if ($overdue) $brief[] = ['lvl' => 'amber', 'icon' => 'i-receipt', 'tag' => 'Finance', 'sev' => 'Overdue', 'big' => $overdue, 'bigS' => 'invoices', 't' => 'Invoices past due', 'd' => 'Send reminders or record the payments that came in.', 'cta' => 'Accounts receivable', 'href' => sl_route('AccountsReceivable'), 'cat' => 'money'];
    if ($kyc) $brief[] = ['lvl' => 'blue', 'icon' => 'i-shield', 'tag' => 'Customers', 'sev' => $kycOld ? 'Waiting' : 'New', 'big' => $kyc, 'bigS' => 'to verify', 't' => 'New sign-ups to approve', 'd' => 'Check the ID and approve so they can ship.', 'cta' => 'Review customers', 'href' => sl_route('Customers', ['filter' => 'unapproved']), 'cat' => 'people'];
    if ($awaiting) $brief[] = ['lvl' => 'green', 'icon' => 'i-check', 'tag' => 'Warehouse', 'sev' => 'Ready', 'big' => $awaiting, 'bigS' => 'ready', 't' => 'Parcels ready for pickup', 'd' => 'Customers can collect; remind the ones that have not come.', 'cta' => 'Hand out', 'href' => sl_route('HandOut'), 'cat' => 'ops'];
    if (!$brief) $brief[] = ['lvl' => 'green', 'icon' => 'i-check', 'tag' => 'All clear', 'sev' => 'Now', 'big' => '0', 'bigS' => 'issues', 't' => 'Nothing needs your attention', 'd' => 'Queues are empty and no exceptions are open.', 'cta' => 'See activity', 'href' => sl_route('ActivityLogs'), 'cat' => 'ops'];
    $d['brief'] = $brief;
    $d['briefLead'] = count($brief) === 1 && $brief[0]['tag'] === 'All clear' ? 'Everything is on track.' : count($brief) . ' thing' . (count($brief) > 1 ? 's' : '') . ' to look at this morning.';
    $d['bfil'] = [['label' => 'All', 'cls' => 'on', 'f' => 'all'], ['label' => 'Operations', 'cls' => '', 'f' => 'ops'], ['label' => 'Money', 'cls' => '', 'f' => 'money'], ['label' => 'People', 'cls' => '', 'f' => 'people']];

    // ── Live pulse ─────────────────────────────────────────────────────────
    $d['pulse'] = [
        ['l' => 'In transit', 'v' => sl_num(sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 3 $agency")), 's' => 'air + road', 'href' => sl_route('AirShipments', ['status' => 3]), 'a' => ''],
        ['l' => 'In warehouse', 'v' => sl_num($inBay), 's' => 'bays used', 'href' => sl_route('Warehouse'), 'a' => ''],
        ['l' => 'Out for delivery', 'v' => sl_num(sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 7 $agency")), 's' => "$driversOut riders", 'href' => sl_route('DeliveryTracking'), 'a' => ''],
        ['l' => 'Delivered today', 'v' => sl_num(sl_scalar("SELECT COUNT(*) FROM cdb_courier_track t WHERE t.status_courier IN (8,15) AND DATE(t.t_date) = CURDATE()")), 's' => 'since midnight', 'href' => sl_route('AirShipments', ['status' => 8]), 'a' => 'g'],
        ['l' => 'Registered today', 'v' => sl_num(sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND DATE(a.order_date) = CURDATE() $agency")), 's' => 'new shipments', 'href' => sl_route('AirShipments'), 'a' => ''],
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
        ['icon' => 'i-bell', 'n' => $prealerts, 'label' => 'Pre-alerted', 'sub' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_pre_alert WHERE is_package = 1 AND DATE(prealert_date) = CURDATE()') . ' matched today'],
        ['icon' => 'i-warehouse', 'n' => $pk('a.status_courier = 2'), 'label' => 'Collected US', 'sub' => $pk('a.status_courier = 2 AND DATE(a.order_date) = CURDATE()') . ' today'],
        ['icon' => 'i-upload', 'n' => $pk('DATE(a.order_date) >= CURDATE() - INTERVAL 14 DAY'), 'label' => 'Input', 'sub' => 'last 14 days'],
        ['icon' => 'i-layers', 'n' => $pk('a.is_consolidate = 1 AND a.status_courier NOT IN (8,15)'), 'label' => 'Consolidated', 'sub' => (int) sl_scalar("SELECT COUNT(*) FROM cdb_consolidate_packages WHERE status_courier NOT IN ('3','8','15','21')") . ' boxes open'],
        ['icon' => 'i-plane', 'n' => $pk('a.status_courier = 3'), 'label' => 'On the way', 'sub' => 'in transit'],
        ['icon' => 'i-pin', 'n' => $pk('a.status_courier = 4'), 'label' => 'Arrived', 'sub' => 'in warehouse'],
        ['icon' => 'i-check', 'n' => $pk('a.status_courier IN (6,32)'), 'label' => 'Ready for pickup', 'sub' => 'codes sent'],
        ['icon' => 'i-user', 'n' => (int) sl_scalar("SELECT COUNT(*) FROM cdb_courier_track t WHERE t.status_courier = 15 AND DATE(t.t_date) = CURDATE()"), 'label' => 'Picked up', 'sub' => 'today'],
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

    // ── Activity heatmaps ──────────────────────────────────────────────────
    $daily182 = sl_dailyCounts('cdb_add_order', 'order_date', 182, ' AND is_deleted = 0');
    $peak = max(1, max($daily182));
    $peakDay = array_search(max($daily182), $daily182, true);
    $d['heat'] = array_map(fn($day, $n) => ['c' => sl_heatLevel($n, $peak), 't' => $day . ': ' . $n . ' parcels'], array_keys($daily182), array_values($daily182));
    $d['heatRange'] = date('M', strtotime('-181 days')) . ' – ' . date('M Y');
    $d['heatMonths'] = [];
    for ($i = 5; $i >= 0; $i--) $d['heatMonths'][] = date('M', strtotime("-$i months"));
    $d['peak'] = ['n' => max($daily182), 'day' => $peakDay ? date('M j', strtotime($peakDay)) : ''];
    $daily364 = sl_dailyCounts('cdb_add_order', 'order_date', 364, ' AND is_deleted = 0');
    $peakY = max(1, max($daily364));
    $peakYDay = array_search(max($daily364), $daily364, true);
    $d['year'] = array_map(fn($day, $n) => ['c' => sl_heatLevel($n, $peakY), 't' => $day . ': ' . $n . ' parcels'], array_keys($daily364), array_values($daily364));
    $d['yearMonths'] = [];
    for ($i = 11; $i >= 0; $i--) $d['yearMonths'][] = date('M', strtotime("-$i months"));
    $d['yearPeak'] = ['n' => max($daily364), 'day' => $peakYDay ? date('M j', strtotime($peakYDay)) : ''];

    // ── Parcels received (7 days) and destinations ─────────────────────────
    $week = array_values(sl_dailyCounts('cdb_add_order', 'order_date', 7, ' AND is_deleted = 0'));
    $weekKg = (float) sl_scalar("SELECT COALESCE(SUM(a.total_weight),0) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date >= CURDATE() - INTERVAL 6 DAY $agency");
    $maxW = max(1, max($week));
    $d['week'] = ['values' => $week, 'avg' => round(array_sum($week) / 7, 1), 'kg' => $weekKg, 'path' => sl_svgLinePath($week, 400, 188, 10, $maxW), 'axis' => [sl_num($maxW), sl_num($maxW * .75), sl_num($maxW * .5), sl_num($maxW * .25), '0'], 'days' => []];
    for ($i = 6; $i >= 0; $i--) $d['week']['days'][] = date('D', strtotime("-$i days"));
    $lastPt = explode(' ', sl_svgPoints($week, 400, 188, 10, $maxW));
    $d['week']['dot'] = end($lastPt) ?: '400,188';
    $dest = sl_rows("SELECT COALESCE(NULLIF(s.recipient_city,''), 'Other') AS city, COUNT(*) AS n FROM cdb_add_order a LEFT JOIN cdb_address_shipments s ON s.order_id = a.order_id WHERE a.is_deleted = 0 AND a.order_date >= CURDATE() - INTERVAL 30 DAY GROUP BY city ORDER BY n DESC LIMIT 8");
    $dmax = $dest ? max(array_map(fn($r) => (int) $r->n, $dest)) : 1;
    $dtot = array_sum(array_map(fn($r) => (int) $r->n, $dest)) ?: 1;
    $d['dest'] = array_map(fn($r) => ['code' => strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', $r->city) ?: 'OT', 0, 2)), 'city' => $r->city, 'n' => (int) $r->n, 'h' => sl_pct($r->n, $dmax)], $dest);
    $d['destTop'] = $dest ? ['city' => $dest[0]->city, 'n' => (int) $dest[0]->n, 'pct' => sl_pct($dest[0]->n, $dtot)] : null;

    // ── Monthly volume ─────────────────────────────────────────────────────
    $year = (int) ($_GET['year'] ?? date('Y'));
    $ship = sl_monthlyCounts('cdb_add_order', 'order_date', $year, ' AND is_deleted = 0 AND is_pickup = 0');
    $pick = sl_monthlyCounts('cdb_add_order', 'order_date', $year, ' AND is_deleted = 0 AND is_pickup = 1');
    $cons = sl_monthlyCounts('cdb_consolidate', 'c_date', $year);
    $mmax = max(1, max($ship), max($pick), max($cons));
    $d['monthly'] = ['year' => $year, 'ship' => $ship, 'pick' => $pick, 'cons' => $cons,
        'areaShip' => sl_svgAreaPath($ship, 750, 220, 20, $mmax, 50), 'lineShip' => sl_svgLinePath($ship, 750, 220, 20, $mmax, 50),
        'linePick' => sl_svgLinePath($pick, 750, 220, 20, $mmax, 50), 'lineCons' => sl_svgLinePath($cons, 750, 220, 20, $mmax, 50),
        'axis' => [sl_num($mmax), sl_num($mmax * .75), sl_num($mmax * .5), sl_num($mmax * .25)]];

    // ── Status mix and on-time ─────────────────────────────────────────────
    $mix = [
        ['In transit', '#2F7FC8', (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (3,5) $agency")],
        ['At warehouse', '#6C4BDD', (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (2,4,6,32) $agency")],
        ['Out for delivery', '#F2C400', (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (7,14) $agency")],
        ['Delivered', '#0EAD78', (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (8,15) AND a.order_date >= CURDATE() - INTERVAL 30 DAY $agency")],
        ['Exceptions', '#E5484D', $held],
    ];
    $mtot = array_sum(array_column($mix, 2)) ?: 1;
    $d['mix'] = ['total' => array_sum(array_column($mix, 2)), 'conic' => sl_conic(array_map(fn($m) => [$m[1], $m[2]], $mix)), 'rows' => array_map(fn($m) => ['label' => $m[0], 'color' => $m[1], 'n' => $m[2], 'pct' => sl_pct($m[2], $mtot)], $mix)];
    $ontime = [];
    for ($w = 11; $w >= 0; $w--) {
        $from = date('Y-m-d', strtotime("-" . ($w * 7 + 6) . " days"));
        $to = date('Y-m-d', strtotime("-" . ($w * 7) . " days"));
        $tot = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (8,15) AND a.order_date BETWEEN :f AND :t", [':f' => $from, ':t' => $to]);
        $late = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (8,15) AND a.order_date BETWEEN :f AND :t AND a.due_date IS NOT NULL AND a.payment_date > a.due_date", [':f' => $from, ':t' => $to]);
        $ontime[] = $tot ? round(($tot - $late) / $tot * 100, 1) : 100;
    }
    $d['ontime'] = ['series' => $ontime, 'now' => end($ontime), 'path' => sl_svgLinePath($ontime, 400, 140, 20, 100), 'from' => date('M j', strtotime('-83 days')), 'to' => date('M j')];
    $d['ontime']['dot'] = (function () use ($ontime) { $p = explode(' ', sl_svgPoints($ontime, 400, 140, 20, 100)); return end($p); })();

    // ── Payment gateways ───────────────────────────────────────────────────
    $gw = [];
    foreach (sl_rows('SELECT id, name_pay, detail_pay, is_active, public_key FROM cdb_met_payment ORDER BY is_active DESC, id LIMIT 2') as $g) {
        $gw[] = ['name' => $g->name_pay, 'desc' => strip_tags((string) $g->detail_pay), 'active' => (int) $g->is_active === 1, 'configured' => !empty($g->public_key), 'href' => 'payment_mode_list.php'];
    }
    $d['gateways'] = $gw;
    return $d;
}
