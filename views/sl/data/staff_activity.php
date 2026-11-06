<?php
/** Data for Staff activity (StaffActivity.dc.html) and Staff productivity (StaffProductivity.dc.html). Uses helpers/staff_activity.php (cdp_sp*). */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_charts.php';
require_once CDP_ROOT . '/helpers/sl_people.php';
require_once CDP_ROOT . '/helpers/staff_activity.php';
require_once CDP_ROOT . '/helpers/activity_log.php';

/** Staff the viewer may look at: themselves plus everyone of lower rank. */
function sl_saPeople($user): array
{
    $out = [];
    foreach (cdp_spStaffUsers() as $u) {
        $u = (object) $u;
        $id = (int) ($u->id ?? 0);
        if (!$id) continue;
        if ($id !== (int) $_SESSION['userid'] && !cdp_canManageUser($user, (int) ($u->userlevel ?? 0))) continue;
        $out[$id] = $u;
    }
    return $out;
}

function sl_saTimeline(array $day, int $from, int $to): array
{
    $span = max(1, $to - $from);
    $segs = [];
    foreach ($day['segments'] ?? [] as $i => $s) {
        $l = max(0, ($s['start'] - $from) / $span * 100);
        $w = max(0.4, ($s['end'] - $s['start']) / $span * 100);
        $segs[] = ['i' => $i, 'k' => $s['type'] === 'active' ? 'a' : 'i', 'pos' => sprintf('left:%.2f%%;width:%.2f%%', $l, $w), 'tip' => date('H:i', $s['start']) . '–' . date('H:i', $s['end']) . ' ' . $s['type']];
    }
    return $segs;
}

function sl_staffActivityData($user, $core): array
{
    $people = sl_saPeople($user);
    $id = (int) ($_GET['id'] ?? 0);
    if (!isset($people[$id])) $id = isset($people[(int) $_SESSION['userid']]) ? (int) $_SESSION['userid'] : (int) array_key_first($people);
    $day = (string) ($_GET['day'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || $day > date('Y-m-d')) $day = date('Y-m-d');
    $seg = isset($_GET['seg']) ? (int) $_GET['seg'] : -1;
    $u = $people[$id] ?? null;
    $d = ['people' => [], 'id' => $id, 'day' => $day, 'cur' => null, 'dayRow' => null];
    foreach ($people as $pid => $p) $d['people'][] = ['id' => $pid, 'name' => trim(($p->fname ?? '') . ' ' . ($p->lname ?? '')) ?: ($p->username ?? ''), 'ini' => sl_initials(($p->fname ?? '') . ' ' . ($p->lname ?? ''))];
    $d['days'] = [['label' => 'Today', 'day' => date('Y-m-d')], ['label' => 'Yesterday', 'day' => date('Y-m-d', strtotime('-1 day'))], ['label' => date('D j', strtotime('-2 day')), 'day' => date('Y-m-d', strtotime('-2 day'))]];
    if (!$u) return $d;
    $roles = cdp_roleFlagsMap();
    $name = trim(($u->fname ?? '') . ' ' . ($u->lname ?? ''));
    $d['cur'] = ['name' => $name, 'ini' => sl_initials($name), 'role' => $roles[(int) ($u->userlevel ?? 0)]->role_name ?? '', 'branch' => $u->name_off ?? ''];
    $last = cdp_activityTableReady() ? sl_row('SELECT created_at, endpoint, user_agent, ip FROM cdb_activity_log WHERE user_id = :u ORDER BY id DESC', [':u' => $id]) : null;
    $d['now'] = ['state' => $last && strtotime($last->created_at) > time() - 900 ? 'Online' : 'Offline', 'page' => $last ? cdp_activityHumanPage(basename((string) $last->endpoint, '.php')) : '—', 'ago' => $last ? sl_ago($last->created_at) : 'never', 'device' => $last && stripos((string) $last->user_agent, 'mobile') !== false ? 'Phone' : 'Computer', 'where' => $last->ip ?? '—',
        'today' => cdp_activityTableReady() ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE user_id = :u AND verb <> 'view' AND created_at >= CURDATE()", [':u' => $id]) : 0];
    $r = cdp_spDay($id, $day);
    $d['dayLabel'] = sl_date($day, 'l, M j');
    if ($r) {
        $from = (int) strtotime($day . ' 06:00'); $to = (int) strtotime($day . ' 20:00');
        $from = min($from, (int) $r['check_in'] - 1800); $to = max($to, (int) $r['check_out'] + 1800);
        $from -= $from % 3600; $to += 3600 - $to % 3600;
        $segs = sl_saTimeline($r, $from, $to);
        foreach ($segs as &$s) $s['sel'] = $s['i'] === $seg ? 'sel' : '';
        unset($s);
        $hours = [];
        for ($t = $from; $t <= $to; $t += 3600 * max(1, (int) ceil(($to - $from) / 3600 / 14))) $hours[] = date('H', $t);
        $rows = [];
        foreach ($r['segments'] as $i => $s) {
            $chips = array_merge(array_slice($s['created'] ?? [], 0, 4), array_slice($s['edited'] ?? [], 0, 3));
            $rows[] = ['i' => $i, 'span' => date('H:i', $s['start']) . '–' . date('H:i', $s['end']), 'kind' => $s['type'] === 'active' ? 'Active' : 'Idle', 'vc' => $s['type'] === 'active' ? 'create' : 'other',
                'what' => $s['type'] === 'active' ? (($s['events'] ?? 0) . ' actions') : 'No input', 'chips' => $chips, 'dur' => cdp_spDuration($s['seconds']), 'sel' => $i === $seg ? 'sel' : ''];
        }
        $acts = [];
        $pick = $seg >= 0 && isset($r['segments'][$seg]) ? [$r['segments'][$seg]] : $r['segments'];
        foreach ($pick as $s) foreach ($s['actions'] ?? [] as $a) $acts[] = ['at' => date('H:i', $a['at']), 'verb' => in_array($a['verb'] ?? '', ['create', 'update', 'delete'], true) ? $a['verb'] : 'other', 'verbL' => ucfirst($a['verb'] ?? 'other'), 'label' => $a['label'], 'ref' => $a['ref'], 'href' => $a['ref'] !== '' ? sl_route('LockerSearch', ['q' => $a['ref']]) : '#'];
        $d['dayRow'] = ['checkIn' => $r['check_in_time'], 'checkWhy' => trim(($r['check_in_kind'] ?? '') . ' ' . ($r['check_in_ref'] ?? '')) ?: 'first action', 'checkOut' => $r['check_out_time'], 'active' => cdp_spDuration($r['active_seconds']), 'activePct' => $r['utilisation'] . '%',
            'idle' => $r['idle_reliable'] ? cdp_spDuration($r['idle_seconds']) : '—', 'idleNote' => $r['idle_reliable'] ? 'pauses over ' . cdp_spSetting('idle_minutes') . ' min' : 'not reliable for this day', 'actions' => (int) $r['events'], 'pkgs' => (int) $r['created_count'] . ' created · ' . (int) $r['edited_count'] . ' edited',
            'before' => (int) $r['pre_checkin_events'] . ' actions', 'cov' => ['presence' => ['Presence data', 'presence'], 'log' => ['Activity log only', 'log'], 'history' => ['Package history only', 'history']][$r['coverage']] ?? ['—', ''],
            'segs' => $segs, 'hours' => $hours, 'inPos' => sprintf('left:%.2f%%', ($r['check_in'] - $from) / ($to - $from) * 100), 'outPos' => sprintf('left:%.2f%%', ($r['check_out'] - $from) / ($to - $from) * 100), 'rows' => $rows, 'acts' => array_slice($acts, 0, 60),
            'selTitle' => $seg >= 0 && isset($rows[$seg]) ? $rows[$seg]['span'] : 'All actions', 'selSub' => $seg >= 0 && isset($rows[$seg]) ? $rows[$seg]['kind'] . ' · ' . $rows[$seg]['dur'] : 'Click a stretch to narrow down'];
        $hm = []; $hmL = []; $max = max(1, max($r['hour_seconds'] ?: [0]));
        for ($h = 7; $h <= 19; $h++) { $v = (int) ($r['hour_seconds'][$h] ?? 0); $hm[] = ['c' => sl_heatLevel($v, $max), 't' => sprintf('%02d:00 · %d min', $h, $v / 60)]; $hmL[] = sprintf('%02d', $h); }
        $d['dayRow']['hm'] = $hm; $d['dayRow']['hmL'] = $hmL;
    }
    // week
    $monday = date('Y-m-d', strtotime('monday this week', strtotime($day)));
    $week = cdp_spBuildDays($monday, min(date('Y-m-d'), date('Y-m-d', strtotime($monday . ' +6 day'))), [$id])[$id] ?? [];
    $wk = []; $wkTotal = 0; $wkMax = 1;
    foreach ($week as $w) $wkMax = max($wkMax, $w['window_seconds']);
    for ($i = 0; $i < 7; $i++) {
        $dd = date('Y-m-d', strtotime($monday . " +$i day"));
        $w = $week[$dd] ?? null;
        $wkTotal += $w['active_seconds'] ?? 0;
        $wk[] = ['day' => $dd, 'd' => date('D', strtotime($dd)), 'h' => $w ? round($w['active_seconds'] / 3600, 1) . 'h' : '', 'ah' => 'height:' . ($w ? max(3, sl_pct($w['active_seconds'], $wkMax)) : 0) . '%', 'ih' => 'height:' . ($w && $w['idle_reliable'] ? sl_pct($w['idle_seconds'], $wkMax) : 0) . '%', 'in' => $w['check_in_time'] ?? '', 'cls' => ($dd === $day ? 'sel ' : '') . ($w ? 'on' : ''), 'tip' => $w ? 'Active ' . cdp_spDuration($w['active_seconds']) : 'No activity'];
    }
    $d['week'] = $wk; $d['wkTotal'] = cdp_spDuration($wkTotal) . ' active';
    // modules of the day
    $mods = cdp_activityTableReady() ? sl_rows("SELECT module, verb, COUNT(*) AS n FROM cdb_activity_log WHERE user_id = :u AND created_at >= :a AND created_at < :b AND verb <> 'view' GROUP BY module, verb", [':u' => $id, ':a' => $day . ' 00:00:00', ':b' => date('Y-m-d', strtotime($day . ' +1 day')) . ' 00:00:00']) : [];
    $byMod = []; $verbs = ['c' => 0, 'u' => 0, 'd' => 0, 'o' => 0];
    foreach ($mods as $m) { $byMod[$m->module] = ($byMod[$m->module] ?? 0) + $m->n; $k = ['create' => 'c', 'update' => 'u', 'delete' => 'd'][$m->verb] ?? 'o'; $verbs[$k] += $m->n; }
    arsort($byMod); $mm = max(1, (int) reset($byMod));
    $d['mods'] = []; foreach (array_slice($byMod, 0, 6, true) as $k => $n) $d['mods'][] = ['n' => cdp_activityModuleLabel($k), 'c' => $n, 'w' => 'width:' . sl_pct($n, $mm) . '%'];
    $d['verbs'] = $verbs;
    $d['canExport'] = $user->cdp_hasPermission('export_staff_productivity');
    return $d;
}

function sl_staffProductivityData($user, $core): array
{
    $periods = ['today' => ['Today', date('Y-m-d'), date('Y-m-d')], 'yesterday' => ['Yesterday', date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))], 'week' => ['This week', date('Y-m-d', strtotime('monday this week')), date('Y-m-d')], 'month' => ['This month', date('Y-m-01'), date('Y-m-d')], '30d' => ['30 days', date('Y-m-d', strtotime('-29 day')), date('Y-m-d')]];
    $p = (string) ($_GET['period'] ?? 'week');
    if (!isset($periods[$p])) $p = 'week';
    [$label, $from, $to] = $periods[$p];
    $people = sl_saPeople($user);
    $ids = array_keys($people);
    $sum = cdp_spSummary($from, $to, $ids);
    $rows = $sum['rows'] ?? [];
    $sort = (string) ($_GET['sort'] ?? 'active_seconds');
    $allowed = ['name', 'first_at', 'last_at', 'active_seconds', 'idle_seconds', 'packages_added', 'packages_edited', 'utilisation', 'per_hour', 'days_worked'];
    if (!in_array($sort, $allowed, true)) $sort = 'active_seconds';
    usort($rows, fn($a, $b) => $sort === 'name' ? strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')) : (($b[$sort] ?? 0) <=> ($a[$sort] ?? 0)));
    $today = cdp_spBuildDays(date('Y-m-d'), date('Y-m-d'), $ids);
    $from0 = strtotime(date('Y-m-d') . ' 06:00'); $to0 = strtotime(date('Y-m-d') . ' 21:00');
    $roles = cdp_roleFlagsMap();
    $live = [];
    foreach ($today as $uid => $days) {
        $dy = $days[date('Y-m-d')] ?? null;
        if (!$dy || !isset($people[$uid])) continue;
        $u = $people[$uid];
        $idleNow = time() - $dy['check_out'] > 60 * (int) cdp_spSetting('idle_minutes');
        $live[] = ['id' => $uid, 'name' => trim(($u->fname ?? '') . ' ' . ($u->lname ?? '')), 'ini' => sl_initials(($u->fname ?? '') . ' ' . ($u->lname ?? '')), 'role' => $roles[(int) ($u->userlevel ?? 0)]->role_name ?? '', 'dot' => $idleNow ? 'i' : 'a', 'stl' => $idleNow ? 'Idle' : 'Active', 'now' => 'In ' . $dy['check_in_time'] . ' · last ' . $dy['check_out_time'], 'segs' => array_map(fn($s) => ['c' => $s['k'], 's' => preg_replace('/;width/', ';width', $s['pos'])], sl_saTimeline($dy, $from0, $to0)), 'act' => cdp_spDuration($dy['active_seconds']), 'cnt' => (int) $dy['events']];
    }
    $t = $sum['totals'] ?? [];
    $byDay = $sum['by_day'] ?? [];
    ksort($byDay);
    $bars = []; $pk = []; $n = max(1, count($byDay)); $mx = 1;
    foreach ($byDay as $b) { $mx = max($mx, $b['span'] ?? $b['seconds']); }
    $i = 0; $w = 1000 / $n;
    foreach ($byDay as $day => $b) {
        $ha = $b['seconds'] / $mx * 200; $hi = max(0, (($b['span'] ?? 0) - ($b['rel_seconds'] ?? 0))) / $mx * 200;
        $x = $i * $w + $w * 0.2;
        $bars[] = ['x' => round($x, 1), 'w' => round($w * 0.6, 1), 'ya' => round(220 - $ha, 1), 'ha' => round($ha, 1), 'yi' => round(220 - $ha - $hi, 1), 'hi' => round($hi, 1)];
        $pk[] = (int) $b['packages']; $i++;
    }
    $line = $pk ? sl_svgPoints($pk, 1000, 200, 20, max(1, max($pk)), $w / 2) : '';
    $staff = [];
    foreach ($rows as $r) {
        $uid = (int) $r['user_id'];
        $dy = $today[$uid][date('Y-m-d')] ?? null;
        $staff[] = ['id' => $uid, 'name' => $r['name'] ?? '', 'ini' => sl_initials($r['name'] ?? ''), 'role' => $r['role'] ?: ($roles[(int) ($people[$uid]->userlevel ?? 0)]->role_name ?? ''), 'first' => $r['first_at'] ? date('M j H:i', strtotime($r['first_at'])) : '—', 'last' => $r['last_at'] ? date('M j H:i', strtotime($r['last_at'])) : '—', 'active' => cdp_spDuration($r['active_seconds']), 'idle' => $r['idle_reliable'] ? cdp_spDuration($r['idle_seconds']) : '—', 'created' => (int) $r['packages_added'], 'edited' => (int) $r['packages_edited'], 'used' => $r['utilisation'] . '%', 'ph' => $r['per_hour'], 'days' => (int) $r['days_worked'], 'segs' => $dy ? array_map(fn($s) => ['c' => $s['k'], 's' => $s['pos']], sl_saTimeline($dy, $from0, $to0)) : []];
    }
    $ins = [];
    if ($rows) { $top = $rows; usort($top, fn($a, $b) => $b['packages_added'] <=> $a['packages_added']); $ins[] = ['icon' => 'i-star', 't' => ($top[0]['name'] ?? '') . ' registered the most packages', 'd' => (int) $top[0]['packages_added'] . ' in ' . strtolower($label)]; }
    if (($t['utilisation'] ?? 0) > 0) $ins[] = ['icon' => 'i-clock', 't' => 'Team utilisation ' . $t['utilisation'] . '%', 'd' => 'Active time inside the working window (presence/log days only)'];
    $ins[] = ['icon' => 'i-info', 't' => cdp_spPresenceTableReady() ? 'Presence tracking is on' : 'Presence tracking is off', 'd' => cdp_spPresenceTableReady() ? 'Idle is measured from keyboard and mouse activity' : 'Idle is estimated from gaps between actions'];
    $goal = (int) (sl_scalar("SELECT setting_value FROM cdb_staff_productivity_settings WHERE setting_key = 'team_goal'", [], 0) ?: 0);
    $pkToday = 0; foreach ($today as $days) $pkToday += (int) ($days[date('Y-m-d')]['created_count'] ?? 0);
    return ['period' => $p, 'periods' => array_map(fn($k, $v) => ['k' => $k, 'label' => $v[0]], array_keys($periods), $periods), 'label' => $label, 'from' => $from, 'to' => $to,
        'kpis' => [['l' => 'Staff with activity', 'v' => (int) ($t['staff'] ?? 0), 's' => sl_num(count($ids)) . ' staff in scope'], ['l' => 'Active hours', 'v' => number_format((float) ($t['active_hours'] ?? 0), 1), 's' => 'of ' . number_format((float) ($t['span_hours'] ?? 0), 1) . ' h in the window'], ['l' => 'Idle hours', 'v' => number_format((float) ($t['idle_hours'] ?? 0), 1), 's' => 'presence and log days only'], ['l' => 'Utilisation', 'v' => ($t['utilisation'] ?? 0) . '%', 's' => 'active ÷ window'], ['l' => 'Packages created', 'v' => sl_num($t['packages_added'] ?? 0), 's' => sl_num($t['packages_edited'] ?? 0) . ' edited'], ['l' => 'Check-ins', 'v' => sl_num($t['checkins'] ?? 0), 's' => sl_num($t['staff_days'] ?? 0) . ' staff-days'], ['l' => 'Actions', 'v' => sl_num($t['events'] ?? 0), 's' => 'every recorded action']],
        'live' => $live, 'liveSum' => count($live) . ' people checked in today', 'bars' => $bars, 'line' => $line, 'x0' => sl_date($from, 'M j'), 'x1' => sl_date($to, 'M j'), 'chartSub' => $label . ' · ' . sl_date($from, 'M j') . ' – ' . sl_date($to, 'M j'),
        'insights' => $ins, 'goalTxt' => $goal ? "$pkToday / $goal" : (string) $pkToday, 'goalW' => 'width:' . ($goal ? sl_pct($pkToday, $goal) : 100) . '%', 'rows' => $staff, 'sort' => $sort,
        'settings' => cdp_spSettings(), 'settingsReady' => cdp_spSettingsTableReady(), 'canExport' => $user->cdp_hasPermission('export_staff_productivity')];
}
