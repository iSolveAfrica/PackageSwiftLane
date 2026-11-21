<?php
/**
 * Top of the staff Overview (views/sl/pages/Main.php): view bar, "you" card, today cards and the
 * air & sea freight section. Every figure is counted from the database; every card is gated by the
 * viewer's permissions and agency scope, so each staff role sees its own version of the page.
 */
require_once __DIR__ . '/ops_common.php';

/**
 * sl_scalar() memoised for one request. The Overview body (overview.php) and these cards
 * show many of the same status counts; identical SQL + binds now hit the database once.
 */
function sl_ovScalar(string $sql, array $binds = [], $default = 0)
{
    static $memo = [];
    $k = $sql . "\0" . serialize($binds) . "\0" . serialize($default);
    if (!array_key_exists($k, $memo)) $memo[$k] = sl_scalar($sql, $binds, $default);
    return $memo[$k];
}

/**
 * The view bar: mode (all | air | sea) and period (today | week | month | year | custom from/to).
 * Returns the current range [from, to) and the previous range of the same length.
 */
function sl_ovView($user): array
{
    $canAir = $user->cdp_hasPermission(['view_shipment_list', 'view_general_shipments']);
    $canSea = $user->cdp_hasPermission(['view_package_list', 'view_general_package_records']);
    $mode = (string) ($_GET['v'] ?? 'all');
    if (!in_array($mode, ['all', 'air', 'sea'], true)) $mode = 'all';
    if (!$canAir && !$canSea) $mode = 'none';
    elseif ($mode === 'air' && !$canAir) $mode = 'sea';
    elseif ($mode === 'sea' && !$canSea) $mode = 'air';
    elseif ($mode === 'all' && !($canAir && $canSea)) $mode = $canAir ? 'air' : 'sea';

    $period = (string) ($_GET['p'] ?? 'month');
    $today = new DateTimeImmutable('today');
    $from = $to = null;
    if ($period === 'custom') {
        $f = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET['from'] ?? ''));
        $t = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET['to'] ?? ''));
        if ($f && $t && $f <= $t && $f->diff($t)->days <= 731) { $from = $f; $to = $t->modify('+1 day'); }
        else $period = 'month';
    }
    switch ($period) {
        case 'today': $from = $today; $to = $today->modify('+1 day'); $label = 'Today'; break;
        case 'week': $from = $today->modify('monday this week'); $to = $today->modify('+1 day'); $label = 'This week'; break;
        case 'year': $from = $today->modify('first day of january this year'); $to = $today->modify('+1 day'); $label = 'This year'; break;
        case 'custom': $label = $from->format('M j') . ' – ' . $to->modify('-1 day')->format('M j, Y'); break;
        default: $period = 'month'; $from = $today->modify('first day of this month'); $to = $today->modify('+1 day'); $label = 'This month';
    }
    $days = max(1, (int) $from->diff($to)->days);
    $prevFrom = $from->modify('-' . $days . ' days');
    return [
        'mode' => $mode, 'period' => $period, 'label' => $label, 'days' => $days, 'canAir' => $canAir, 'canSea' => $canSea,
        'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'toShow' => $to->modify('-1 day')->format('Y-m-d'),
        'prevFrom' => $prevFrom->format('Y-m-d'), 'prevTo' => $from->format('Y-m-d'),
        'unit' => $period === 'today' ? 'hour' : ($days > 62 ? 'month' : 'day'),
    ];
}

/** The last $n dates (oldest first) with zero counts. */
function sl_ovZeroDays(int $n = 14): array
{
    $out = [];
    for ($i = $n - 1; $i >= 0; $i--) $out[date('Y-m-d', strtotime("-$i days"))] = 0;
    return $out;
}

/** Count rows per day of a date column over the last $n days (oldest first). */
function sl_ovDaily(string $sql, array $binds, int $n = 14): array
{
    $map = [];
    foreach (sl_rows($sql, $binds + [':d0' => date('Y-m-d', strtotime('-' . ($n - 1) . ' days'))]) as $r) $map[$r->day] = (int) $r->n;
    $out = sl_ovZeroDays($n);
    foreach ($out as $day => $v) $out[$day] = $map[$day] ?? 0;
    return $out;
}

/** Permission check that understands the nav's special keys (cs:agent, ai:use, ...). */
function sl_ovAllowed($user, $perm): bool
{
    if (function_exists('sl_navAllowed')) return sl_navAllowed($perm, []);
    return $user->cdp_hasPermission($perm);
}

/** Short "5 min ago" / "3 h ago" / "2 d ago" label. */
function sl_ovAgo(?string $when): string
{
    if (!$when) return '';
    $m = max(0, (int) floor((time() - strtotime($when)) / 60));
    if ($m < 1) return 'just now';
    if ($m < 60) return $m . ' min ago';
    if ($m < 1440) return floor($m / 60) . ' h ago';
    return floor($m / 1440) . ' d ago';
}

/** The "you" card: greeting, role and this person's own productivity numbers. */
function sl_ovProfile($user, $userData, array $view): array
{
    $uid = (int) ($_SESSION['userid'] ?? 0);
    $h = (int) date('G');
    $p = [
        'greet' => ($h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening')) . ' · ' . date('l j M'),
        'name' => trim(($userData->fname ?? '') . ' ' . ($userData->lname ?? '')),
        'last' => !empty($userData->lastlogin) && $userData->lastlogin !== '0000-00-00 00:00:00' ? sl_date($userData->lastlogin, 'j M, H:i') : '',
        'today' => 0, 'week' => 0, 'weekDelta' => null, 'active' => 0, 'mine' => 0, 'bars' => [], 'logOn' => false,
        'rank' => null, 'team' => 0, 'peak' => null, 'kinds' => [], 'lastLabel' => '', 'lastAgo' => '', 'perDay' => 0, 'todayVsAvg' => null,
    ];
    if (sl_tableExists('cdb_activity_log')) {
        $p['logOn'] = true;
        $p['today'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE user_id = :u AND created_at >= CURDATE() AND action <> 'page_view'", [':u' => $uid]);
        $p['week'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE user_id = :u AND created_at >= :w AND action <> 'page_view'", [':u' => $uid, ':w' => date('Y-m-d', strtotime('monday this week'))]);
        // same number of days of last week, so the comparison is fair mid-week
        $lastFrom = date('Y-m-d', strtotime('monday last week'));
        $lastTo = date('Y-m-d H:i:s', strtotime('-7 days'));
        $last = (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE user_id = :u AND created_at >= :a AND created_at < :b AND action <> 'page_view'", [':u' => $uid, ':a' => $lastFrom, ':b' => $lastTo]);
        $p['weekDelta'] = $last > 0 ? (int) round(($p['week'] - $last) / $last * 100) : null;
        $days = array_map(fn($r) => $r->day, sl_rows('SELECT DISTINCT DATE(created_at) AS day FROM cdb_activity_log WHERE user_id = :u AND created_at >= :f', [':u' => $uid, ':f' => date('Y-m-d', strtotime('-29 days'))]));
        $p['active'] = count($days);
        $daily = sl_ovDaily("SELECT DATE(created_at) AS day, COUNT(*) AS n FROM cdb_activity_log WHERE user_id = :u AND created_at >= :d0 AND action <> 'page_view' GROUP BY DATE(created_at)", [':u' => $uid]);
        $p['bars'] = sl_sparkBars(array_values($daily), 4, array_map(fn($d) => date('D j M', strtotime($d)), array_keys($daily)));
        $p['perDay'] = (int) round(array_sum($daily) / max(1, count(array_filter($daily))), 0);
        $p['todayVsAvg'] = $p['perDay'] > 0 ? (int) round(($p['today'] - $p['perDay']) / $p['perDay'] * 100) : null;
        $wk = date('Y-m-d', strtotime('monday this week'));
        // busiest hour over 30 days
        $pk = sl_rows("SELECT HOUR(created_at) AS h, COUNT(*) AS n FROM cdb_activity_log WHERE user_id = :u AND created_at >= :f AND action <> 'page_view' GROUP BY HOUR(created_at) ORDER BY n DESC LIMIT 1", [':u' => $uid, ':f' => date('Y-m-d', strtotime('-29 days'))]);
        if ($pk) $p['peak'] = sprintf('%02d:00–%02d:00', (int) $pk[0]->h, ((int) $pk[0]->h + 1) % 24);
        // what kind of work this week
        $kinds = sl_rows("SELECT COALESCE(NULLIF(verb, ''), 'other') AS v, COUNT(*) AS n FROM cdb_activity_log WHERE user_id = :u AND created_at >= :w AND action <> 'page_view' AND COALESCE(verb, '') NOT IN ('view', 'login') GROUP BY v ORDER BY n DESC LIMIT 5", [':u' => $uid, ':w' => $wk]);
        $kmax = $kinds ? max(1, (int) $kinds[0]->n) : 1;
        foreach ($kinds as $k) $p['kinds'][] = ['l' => function_exists('cdp_activityVerbLabel') ? cdp_activityVerbLabel($k->v) : ucfirst($k->v), 'n' => (int) $k->n, 'w' => (int) round((int) $k->n / $kmax * 100)];
        $last = sl_rows("SELECT action_label, summary, created_at FROM cdb_activity_log WHERE user_id = :u AND action <> 'page_view' AND COALESCE(verb, '') NOT IN ('view', 'login') ORDER BY id DESC LIMIT 1", [':u' => $uid]);
        if ($last) { $p['lastLabel'] = (string) ($last[0]->summary ?: $last[0]->action_label); $p['lastAgo'] = sl_ovAgo($last[0]->created_at); }
    }
    $mine = 0;
    if ($view['mode'] !== 'sea') $mine += (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE user_id = :u AND is_deleted = 0 AND order_date >= :f AND order_date < :t', [':u' => $uid, ':f' => $view['from'], ':t' => $view['to']]);
    if ($view['mode'] !== 'air') $mine += (int) sl_scalar('SELECT COUNT(*) FROM cdb_customers_packages WHERE user_id = :u AND order_date >= :f AND order_date < :t', [':u' => $uid, ':f' => $view['from'], ':t' => $view['to']]);
    $p['mine'] = $mine;
    $p['chips'] = [];
    if (sl_tableExists('cdb_cs_tickets') && sl_ovAllowed($user, 'cs:agent')) {
        $p['chips'][] = ['icon' => 'i-ticket', 'l' => 'My open tickets', 'n' => (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE assignee_id = :u AND status NOT IN ('resolved','closed','merged')", [':u' => $uid]), 'href' => sl_route('CsInbox', ['view' => 'mine'])];
    }
    if ($user->cdp_hasPermission('view_client_list') && sl_columnExists('cdb_users', 'approve')) {
        $p['chips'][] = ['icon' => 'i-shield', 'l' => 'Approvals', 'n' => (int) sl_ovScalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel = 1 AND approve = 0 AND active = 1'), 'href' => sl_route('Customers', ['filter' => 'unapproved'])];
    }
    if ($user->cdp_hasPermission('prealert_list')) {
        $p['chips'][] = ['icon' => 'i-inbox', 'l' => 'Pre-alerts', 'n' => (int) sl_ovScalar('SELECT COUNT(*) FROM cdb_pre_alert WHERE is_package = 0'), 'href' => sl_route('PreAlerts')];
    }
    return $p;
}

/** Today cards. Each is included only when the viewer may open the page it links to. */
function sl_ovCards($user, array $view): array
{
    $agency = sl_navAgencyWhere('a');
    $cards = [];
    $canAir = $view['canAir']; $canSea = $view['canSea'];

    // Parcels in today
    if ($canAir || $canSea) {
        $airT = $canAir ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date >= CURDATE() $agency") : 0;
        $seaT = $canSea ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_customers_packages a WHERE a.order_date >= CURDATE() $agency") : 0;
        $airY = $canAir ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date >= CURDATE() - INTERVAL 1 DAY AND a.order_date < CURDATE() $agency") : 0;
        $seaY = $canSea ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_customers_packages a WHERE a.order_date >= CURDATE() - INTERVAL 1 DAY AND a.order_date < CURDATE() $agency") : 0;
        // scans are hub-wide (no agency on the row): not shown to agency-restricted users
        $scans = $agency === '' && sl_tableExists('cdb_warehouse_scans') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_warehouse_scans WHERE created_at >= CURDATE()') : null;
        $daily = sl_ovZeroDays();
        if ($canAir) foreach (sl_ovDaily("SELECT DATE(a.order_date) AS day, COUNT(*) AS n FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date >= :d0 $agency GROUP BY DATE(a.order_date)", []) as $k => $v) $daily[$k] += $v;
        if ($canSea) foreach (sl_ovDaily("SELECT DATE(a.order_date) AS day, COUNT(*) AS n FROM cdb_customers_packages a WHERE a.order_date >= :d0 $agency GROUP BY DATE(a.order_date)", []) as $k => $v) $daily[$k] += $v;
        $sub = [];
        if ($canAir) $sub[] = ['n' => sl_num($airT), 'l' => 'Air shipments', 'href' => sl_route('AirShipments', ['period' => 'today'])];
        if ($canSea) $sub[] = ['n' => sl_num($seaT), 'l' => 'Sea packages', 'href' => sl_route('SeaPackages')];
        if ($scans !== null && $user->cdp_hasPermission(['package_intake', 'add_package', 'warehouse_view'])) $sub[] = ['n' => sl_num($scans), 'l' => 'Warehouse scans', 'href' => sl_route('PackageIntake')];
        $cards[] = ['key' => 'parcels', 'tone' => 'blue', 'icon' => 'i-box', 'eyebrow' => 'Parcels today', 'big' => sl_num($airT + $seaT), 'lede' => 'came into the system · ' . sl_num($airY + $seaY) . ' yesterday',
            'sub' => $sub, 'bars' => sl_sparkBars(array_values($daily), 4, array_map(fn($d) => date('D j M', strtotime($d)), array_keys($daily))), 'barsL' => '14 days · ' . sl_num(array_sum($daily)) . ' in',
            'barsR' => 'avg ' . number_format(array_sum($daily) / 14, 1) . ' a day', 'href' => $canAir ? sl_route('AirShipments') : sl_route('SeaPackages')];
    }

    // Deliveries & pickups today
    if ($user->cdp_hasPermission(['view_pickup_list', 'dispatch_riders', 'view_user_list'])) {
        $outN = (int) sl_ovScalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 7 $agency");
        $riders = (int) sl_ovScalar("SELECT COUNT(DISTINCT a.driver_id) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 7 AND a.driver_id > 0 $agency");
        // delivered = a delivered/picked-up tracking event; scoped through the shipment for agency users
        $trk = "FROM cdb_courier_track t" . ($agency !== '' ? " JOIN cdb_add_order a ON t.order_track = CONCAT(a.order_prefix, a.order_no)" : '') . " WHERE t.status_courier IN (8,15)" . ($agency !== '' ? " $agency" : '');
        $delivered = (int) sl_scalar("SELECT COUNT(DISTINCT t.order_track) $trk AND t.t_date >= CURDATE()");
        $pickT = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND a.order_date >= CURDATE() $agency");
        $unass = (int) sl_ovScalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND a.status_courier IN (1,14) AND (a.driver_id IS NULL OR a.driver_id = 0) $agency");
        $daily = sl_ovDaily("SELECT DATE(t.t_date) AS day, COUNT(DISTINCT t.order_track) AS n $trk AND t.t_date >= :d0 GROUP BY DATE(t.t_date)", []);
        $cards[] = ['key' => 'delivery', 'tone' => 'amber', 'icon' => 'i-truck', 'eyebrow' => 'Deliveries today', 'big' => sl_num($delivered) . ' handed over',
            'lede' => sl_num($outN) . ' out with ' . sl_num($riders) . ' rider' . ($riders === 1 ? '' : 's') . ($unass ? ' · ' . $unass . ' pickups without a rider' : ''),
            'sub' => [['n' => sl_num($outN), 'l' => 'Out now'], ['n' => sl_num($riders), 'l' => 'Riders'], ['n' => sl_num($pickT), 'l' => 'Pickups'], ['n' => sl_num($unass), 'l' => 'No rider']],
            'bars' => sl_sparkBars(array_values($daily), 4, array_map(fn($d) => date('D j M', strtotime($d)), array_keys($daily))), 'barsL' => 'Handed over per day, 14 days', 'barsR' => 'avg ' . number_format(array_sum($daily) / 14, 1) . ' a day',
            'href' => sl_route('RiderDispatch'), 'alert' => $unass ? $unass . ' pickups need a rider' : ''];
    }

    // Support today
    if (sl_ovAllowed($user, ['cs:agent', 'cs:manage'])) {
      if (!sl_tableExists('cdb_cs_tickets')) {
        $cards[] = ['key' => 'support', 'tone' => 'rose', 'icon' => 'i-message', 'eyebrow' => 'Customer service today', 'big' => '0 waiting',
            'lede' => 'The support desk starts counting after its first conversation', 'sub' => [['n' => '0', 'l' => 'Chats started'], ['n' => '0', 'l' => 'To staff'], ['n' => '0', 'l' => 'Resolved'], ['n' => '—', 'l' => 'First reply']],
            'bars' => sl_sparkBars(array_fill(0, 14, 0)), 'barsL' => 'New chats per day, 14 days', 'barsR' => '0 open tickets', 'href' => sl_route('CsInbox'), 'alert' => ''];
      } else {
        $open = "status NOT IN ('resolved','closed','merged')";
        $waiting = (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE $open AND human_first_response_at IS NULL");
        $oldest = sl_scalar("SELECT MIN(created_at) FROM cdb_cs_tickets WHERE $open AND human_first_response_at IS NULL", [], null);
        $over30 = (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE $open AND human_first_response_at IS NULL AND created_at < NOW() - INTERVAL 30 MINUTE");
        $openN = (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE $open");
        $started = (int) sl_scalar('SELECT COUNT(*) FROM cdb_cs_tickets WHERE created_at >= CURDATE()');
        $toStaff = (int) sl_scalar('SELECT COUNT(*) FROM cdb_cs_tickets WHERE created_at >= CURDATE() AND assignee_id IS NOT NULL AND assignee_id > 0');
        $resolved = (int) sl_scalar('SELECT COUNT(*) FROM cdb_cs_tickets WHERE resolved_at >= CURDATE()');
        $firstMin = sl_scalar('SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, COALESCE(human_first_response_at, first_response_at))) FROM cdb_cs_tickets WHERE created_at >= CURDATE() AND COALESCE(human_first_response_at, first_response_at) IS NOT NULL', [], null);
        $age = $oldest ? (int) floor((time() - strtotime($oldest)) / 60) : 0;
        $daily = sl_ovDaily('SELECT DATE(created_at) AS day, COUNT(*) AS n FROM cdb_cs_tickets WHERE created_at >= :d0 GROUP BY DATE(created_at)', []);
        $cards[] = ['key' => 'support', 'tone' => 'rose', 'icon' => 'i-message', 'eyebrow' => 'Customer service today', 'big' => sl_num($waiting) . ' waiting',
            'lede' => $waiting ? 'for a person · longest ' . ($age >= 60 ? floor($age / 60) . ' h ' . ($age % 60) . ' min' : $age . ' min') . ($over30 ? ' · ' . $over30 . ' unanswered 30 min+' : '') : 'Everyone has had a reply',
            'sub' => [['n' => sl_num($started), 'l' => 'Chats started'], ['n' => sl_num($toStaff), 'l' => 'To staff'], ['n' => sl_num($resolved), 'l' => 'Resolved'], ['n' => $firstMin === null ? '—' : (int) round((float) $firstMin) . ' min', 'l' => 'First reply']],
            'bars' => sl_sparkBars(array_values($daily), 4, array_map(fn($d) => date('D j M', strtotime($d)), array_keys($daily))), 'barsL' => 'New chats per day, 14 days', 'barsR' => sl_num($openN) . ' open tickets',
            'href' => sl_route('CsInbox'), 'alert' => $over30 ? $over30 . ' unanswered over 30 min' : ''];
      }
    }

    // AI across the system: customer chat bot + staff assistant
    if (sl_ovAllowed($user, ['ai:use', 'ai:manage'])) {
        $on = function_exists('cdp_aiSetting') && (string) cdp_aiSetting('enabled', '0') === '1';
        $model = function_exists('cdp_aiSetting') ? (string) cdp_aiSetting('model', '') : '';
        $hasConv = sl_tableExists('cdb_cs_conversations');
        $hasQ = sl_tableExists('cdb_ai_questions');
        $hasRuns = sl_tableExists('cdb_cs_ai_runs');
        $chats = $hasConv ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_cs_conversations WHERE created_at >= CURDATE()') : 0;
        $handed = $hasConv ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_cs_conversations WHERE created_at >= CURDATE() AND handover_at IS NOT NULL') : 0;
        $aiOnly = max(0, $chats - $handed);
        $staffQ = $hasQ ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_ai_questions WHERE created_at >= CURDATE()') : 0;
        $failed = $hasRuns ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_ai_runs WHERE created_at >= CURDATE() AND outcome IN ('error','failed','timeout')") : 0;
        $tokens = $hasRuns ? (int) sl_scalar('SELECT COALESCE(SUM(COALESCE(tokens_in,0) + COALESCE(tokens_out,0)), 0) FROM cdb_cs_ai_runs WHERE created_at >= CURDATE()') : 0;
        if (sl_tableExists('cdb_ai_messages')) $tokens += (int) sl_scalar('SELECT COALESCE(SUM(COALESCE(tokens_in,0) + COALESCE(tokens_out,0)), 0) FROM cdb_ai_messages WHERE created_at >= CURDATE()');
        $latency = $hasRuns ? sl_scalar('SELECT AVG(latency_ms) FROM cdb_cs_ai_runs WHERE created_at >= CURDATE() AND latency_ms IS NOT NULL', [], null) : null;
        $daily = sl_ovZeroDays();
        if ($hasConv) foreach (sl_ovDaily('SELECT DATE(created_at) AS day, COUNT(*) AS n FROM cdb_cs_conversations WHERE created_at >= :d0 GROUP BY DATE(created_at)', []) as $k => $v) $daily[$k] += $v;
        if ($hasQ) foreach (sl_ovDaily('SELECT DATE(created_at) AS day, COUNT(*) AS n FROM cdb_ai_questions WHERE created_at >= :d0 GROUP BY DATE(created_at)', []) as $k => $v) $daily[$k] += $v;
        $tok = $tokens >= 1000000 ? round($tokens / 1000000, 1) . 'M' : ($tokens >= 1000 ? round($tokens / 1000, 1) . 'k' : (string) $tokens);
        $cards[] = ['key' => 'ai', 'tone' => 'violet', 'icon' => 'i-ai', 'eyebrow' => 'AI across the system', 'big' => sl_num($chats + $staffQ) . ' handled',
            'lede' => !$on ? cdp_kingName() . ' is switched off · turn it on in the AI Automation Center'
                : ($chats ? sl_pct($aiOnly, $chats) . '% of customer chats closed without a person' : 'No customer chats yet today') . ($model !== '' ? ' · ' . $model : ''),
            'sub' => [['n' => sl_num($chats), 'l' => 'Customer chats'], ['n' => sl_num($handed), 'l' => 'Handed to staff'], ['n' => sl_num($staffQ), 'l' => 'Staff questions'], ['n' => $latency === null ? $tok : number_format((float) $latency / 1000, 1) . ' s', 'l' => $latency === null ? 'Tokens' : 'Avg reply']],
            'bars' => sl_sparkBars(array_values($daily), 4, array_map(fn($d) => date('D j M', strtotime($d)), array_keys($daily))), 'barsL' => 'AI conversations per day, 14 days', 'barsR' => $tok . ' tokens today',
            'href' => sl_ovAllowed($user, 'ai:manage') ? sl_route('AiCenter') : sl_route('StaffAI'), 'alert' => $failed ? $failed . ' AI errors today' : (!$on ? cdp_kingName() . ' is off' : '')];
    }

    // Warehouse now
    if ($user->cdp_hasPermission(['warehouse_view', 'view_warehouse_delivery'])) {
        $inBay = (int) sl_ovScalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (4,6,32) $agency") + (int) sl_ovScalar("SELECT COUNT(*) FROM cdb_customers_packages a WHERE a.status_courier IN (4,6,32) $agency");
        $ready = (int) sl_ovScalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (6,32) $agency");
        $arriving = (int) sl_ovScalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 3 $agency");
        $held = (int) sl_ovScalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (12,25,28,29) $agency");
        $old = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (6,32) AND a.order_date < CURDATE() - INTERVAL 30 DAY $agency");
        $cap = sl_tableExists('cdb_sl_settings') ? (int) sl_ovScalar("SELECT setting_value FROM cdb_sl_settings WHERE setting_key = 'warehouse_bays'", [], 0) : 0;
        $daily = $agency === '' && sl_tableExists('cdb_warehouse_scans') ? sl_ovDaily('SELECT DATE(created_at) AS day, COUNT(*) AS n FROM cdb_warehouse_scans WHERE created_at >= :d0 GROUP BY DATE(created_at)', []) : [];
        $cards[] = ['key' => 'warehouse', 'tone' => 'mint', 'icon' => 'i-warehouse', 'eyebrow' => 'Warehouse now', 'big' => sl_num($inBay) . ($cap ? ' / ' . sl_num($cap) : '') . ' in bays',
            'lede' => sl_num($arriving) . ' arriving · ' . sl_num($ready) . ' ready for pickup' . ($cap ? ' · ' . sl_pct($inBay, $cap) . '% full' : ''),
            'sub' => [['n' => sl_num($ready), 'l' => 'Ready'], ['n' => sl_num($arriving), 'l' => 'Arriving'], ['n' => sl_num($held), 'l' => 'Held'], ['n' => sl_num($old), 'l' => 'Waiting 30 d+']],
            'bars' => $daily ? sl_sparkBars(array_values($daily), 4, array_map(fn($d) => date('D j M', strtotime($d)), array_keys($daily))) : [], 'barsL' => 'Scans per day, 14 days', 'barsR' => $daily ? sl_num(array_sum($daily)) . ' scans' : '',
            'href' => sl_route('Warehouse'), 'alert' => $held ? $held . ' parcels held' : ''];
    }
    return $cards;
}

/** Short-lived per-session cache for slow, slowly-changing counters (falls back to a live query). */
function sl_ovCachedInt(string $key, int $ttl, callable $fn): int
{
    if (session_status() !== PHP_SESSION_ACTIVE) return (int) $fn();
    $k = md5($key);
    $hit = $_SESSION['sl_ov_cache'][$k] ?? null;
    if (is_array($hit) && ($hit[0] ?? 0) > time() - $ttl) return (int) $hit[1];
    $n = (int) $fn();
    $cache = is_array($_SESSION['sl_ov_cache'] ?? null) ? $_SESSION['sl_ov_cache'] : [];
    $cache = array_filter($cache, fn($v) => is_array($v) && ($v[0] ?? 0) > time() - 3600);
    $cache[$k] = [time(), $n];
    $_SESSION['sl_ov_cache'] = $cache;
    return $n;
}

/** Air & sea freight for the chosen view: total, change, split and a per-day (hour, month) chart. */
function sl_ovFreight($user, array $view): array
{
    if ($view['mode'] === 'none') return [];
    $agency = sl_navAgencyWhere('a');
    $src = [];
    if ($view['mode'] !== 'sea') $src['air'] = ['tbl' => 'cdb_add_order', 'w' => 'AND a.is_deleted = 0 AND a.is_pickup = 0'];
    if ($view['mode'] !== 'air') $src['sea'] = ['tbl' => 'cdb_customers_packages', 'w' => ''];
    $unit = $view['unit'];
    $fmt = $unit === 'hour' ? '%H' : ($unit === 'month' ? '%Y-%m' : '%Y-%m-%d');
    // order_date is a DATE; the time of day lives in order_datetime
    $bucketCol = $unit === 'hour' ? 'COALESCE(a.order_datetime, a.order_date)' : 'a.order_date';
    // buckets
    $keys = []; $prevKeys = []; $labels = [];
    if ($unit === 'hour') {
        for ($i = 0; $i < 24; $i++) { $keys[] = sprintf('%02d', $i); $labels[] = sprintf('%02d:00', $i); }
        $prevKeys = $keys;
    } elseif ($unit === 'month') {
        $c = new DateTimeImmutable(substr($view['from'], 0, 7) . '-01');
        $end = new DateTimeImmutable($view['to']);
        $pc = new DateTimeImmutable(substr($view['prevFrom'], 0, 7) . '-01');
        while ($c < $end) { $keys[] = $c->format('Y-m'); $labels[] = $c->format('M'); $prevKeys[] = $pc->format('Y-m'); $c = $c->modify('+1 month'); $pc = $pc->modify('+1 month'); }
    } else {
        for ($i = 0; $i < $view['days']; $i++) {
            $keys[] = date('Y-m-d', strtotime($view['from'] . " +$i days"));
            $prevKeys[] = date('Y-m-d', strtotime($view['prevFrom'] . " +$i days"));
            $labels[] = date('M j', strtotime($view['from'] . " +$i days"));
        }
    }
    $cur = array_fill(0, count($keys), 0); $prev = array_fill(0, count($keys), 0);
    $tot = ['air' => 0, 'sea' => 0]; $ptot = 0; $book = 0;
    foreach ($src as $k => $s) {
        $map = [];
        foreach (sl_rows("SELECT DATE_FORMAT($bucketCol, '$fmt') AS b, COUNT(*) AS n FROM {$s['tbl']} a WHERE a.order_date >= :f AND a.order_date < :t {$s['w']} $agency GROUP BY b", [':f' => $view['from'], ':t' => $view['to']]) as $r) $map[$r->b] = (int) $r->n;
        foreach ($keys as $i => $key) { $cur[$i] += $map[$key] ?? 0; $tot[$k] += $map[$key] ?? 0; }
        $pmap = [];
        foreach (sl_rows("SELECT DATE_FORMAT($bucketCol, '$fmt') AS b, COUNT(*) AS n FROM {$s['tbl']} a WHERE a.order_date >= :f AND a.order_date < :t {$s['w']} $agency GROUP BY b", [':f' => $view['prevFrom'], ':t' => $view['prevTo']]) as $r) $pmap[$r->b] = (int) $r->n;
        foreach ($prevKeys as $i => $key) $prev[$i] += $pmap[$key] ?? 0;
        // same WHERE as the grouped query above, so its buckets already add up to the period total
        $ptot += array_sum($pmap);
        // all-time count is a whole-table scan; it barely moves, so keep it per session for 10 minutes
        $bookSql = "SELECT COUNT(*) FROM {$s['tbl']} a WHERE 1 = 1 {$s['w']} $agency";
        $book += sl_ovCachedInt($bookSql, 600, fn() => (int) sl_scalar($bookSql));
    }
    $total = $tot['air'] + $tot['sea'];
    // running totals: is this period ahead of or behind the last one at the same point?
    $cumCur = []; $cumPrev = []; $rc = 0; $rp = 0;
    // buckets still in the future (rest of today's hours) are not drawn on the "this period" line
    $nowIdx = $unit === 'hour' ? (int) date('G') : count($keys) - 1;
    foreach ($cur as $i => $v) { $rc += $v; $rp += $prev[$i]; $cumCur[] = $i <= $nowIdx ? $rc : null; $cumPrev[] = $rp; }
    $prevSame = $cumPrev[min($nowIdx, count($cumPrev) - 1)] ?? 0;
    $elapsed = array_slice($cur, 0, $nowIdx + 1);
    $bestI = $elapsed ? array_search(max($elapsed), $elapsed, true) : 0;
    // forecast to the end of the calendar period at today's pace
    $forecast = null; $forecastLabel = '';
    $calEnd = ['week' => 7, 'month' => (int) date('t'), 'year' => 365 + (int) date('L')][$view['period']] ?? 0;
    if ($calEnd && $view['days'] < $calEnd && $view['days'] > 0) {
        $forecast = (int) round($total / $view['days'] * $calEnd);
        $forecastLabel = ['week' => 'by Sunday', 'month' => 'by ' . date('M t'), 'year' => 'by Dec 31'][$view['period']];
    }
    return [
        'cumCur' => $cumCur, 'cumPrev' => $cumPrev, 'prevSame' => $prevSame,
        'pace' => $prevSame > 0 ? round(($total - $prevSame) / $prevSame * 100, 1) : null,
        'avg' => count($elapsed) ? round($total / count($elapsed), 1) : 0,
        'best' => $elapsed ? ['n' => (int) $elapsed[$bestI], 'l' => $labels[$bestI] ?? ''] : ['n' => 0, 'l' => ''],
        'quiet' => count(array_filter($elapsed, fn($v) => $v === 0)), 'buckets' => count($elapsed),
        'forecast' => $forecast, 'forecastLabel' => $forecastLabel,
        'title' => ($view['mode'] === 'all' ? 'Air & sea freight' : ($view['mode'] === 'air' ? 'Air freight' : 'Sea freight')) . ' — ' . strtolower($view['label']),
        'total' => $total, 'prevTotal' => $ptot, 'book' => $book,
        'change' => $ptot > 0 ? round(($total - $ptot) / $ptot * 100, 1) : null,
        'rangeLabel' => $view['period'] === 'custom' ? 'between ' . $view['label'] : strtolower($view['label']),
        'prevLabel' => $view['period'] === 'today' ? 'yesterday' : 'the previous ' . $view['days'] . ' days',
        'air' => $tot['air'], 'sea' => $tot['sea'], 'airPct' => sl_pct($tot['air'], max(1, $total)), 'seaPct' => $total ? 100 - sl_pct($tot['air'], $total) : 0,
        'showSplit' => $view['mode'] === 'all',
        'labels' => $labels, 'cur' => $cur, 'prev' => $prev, 'peak' => max(1, max($cur ?: [0]), max($prev ?: [0])), 'unit' => $unit,
    ];
}
