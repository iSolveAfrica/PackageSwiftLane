<?php
/**
 * Read-only detail for one statistic of the Customers page (dataJs/sl/customers_stats.js): a chart
 * drawn for it, key figures, breakdowns and the matching customers. Computed on demand only.
 * GET stat=total|active|new|phone|idv|repeat|parcels|due|quiet|csat|blocked|month|air|both|sea
 *     ym=YYYY-MM (stat=month)
 * Same gate and agency scope as customers_list.php; filters shared with the list
 * (views/sl/data/customers.php) so "Show all N in the table" lists exactly the same N customers.
 */
require_once __DIR__ . '/../../loader.php';
require_once CDP_ROOT . '/helpers/ajax_guard.php';
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/views/sl/data/customers.php';

$user = new User();
require_login();
require_permission('view_client_list');
// The list page refuses without this permission in every RBAC mode, and refuses customer and
// driver accounts (sl_customersCanView); so does its detail.
if (!sl_customersCanView($user)) {
    sl_json(['success' => false, 'message' => 'No permission to view customers'], 403);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    sl_json(['success' => false, 'message' => 'GET required'], 405);
}
header('Cache-Control: private, no-store');
$core = $core ?? new Core();

$stat = (string) ($_GET['stat'] ?? '');
$keys = ['total', 'active', 'new', 'phone', 'idv', 'repeat', 'parcels', 'due', 'quiet', 'csat', 'blocked', 'month', 'air', 'both', 'sea'];
if (!in_array($stat, $keys, true)) {
    sl_json(['success' => false, 'message' => 'Unknown statistic'], 400);
}
$ym = null;
if ($stat === 'month') {
    $ym = sl_customersMonth($_GET['ym'] ?? '');
    if (!$ym) sl_json(['success' => false, 'message' => 'Choose a month'], 400);
}
if ($stat === 'due' && !cdp_financeEnabled()) {
    sl_json(['success' => false, 'message' => 'Billing is switched off'], 404);
}

/* ---------- query helpers (alias u = the customer, always scoped) ---------- */
function cxs_scope(): string { return sl_customersScope()[0]; }
/** :cx_y0 (the first day of this year) is available to every statement. */
function cxs_rows(string $sql, array $binds = []): array { return sl_rows($sql, sl_cxBinds($sql, $binds + sl_customersScope()[1] + [':cx_y0' => date('Y-01-01')])); }
function cxs_val(string $sql, array $binds = [])
{
    $r = cxs_rows($sql, $binds);
    if (!$r) return 0;
    $v = get_object_vars($r[0]);
    return reset($v) ?? 0;
}
/** Count of customers matching one or more list filters (all must hold). */
function cxs_count(string ...$filters): int
{
    $b = [];
    $w = '';
    foreach ($filters as $f) $w .= sl_customersFilterSql($f, $b, $GLOBALS['ym']);
    return (int) cxs_val('SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel = 1' . cxs_scope() . $w, $b);
}
function cxs_months(): array
{
    $out = [];
    for ($i = 11; $i >= 0; $i--) {
        $ts = strtotime("first day of -$i months");
        $out[] = ['ym' => date('Y-m', $ts), 'l' => date('M', $ts), 'full' => date('F Y', $ts)];
    }
    return $out;
}
/** ym => value map from rows with ym and one value column. */
function cxs_map(array $rows, string $col = 'n'): array
{
    $m = [];
    foreach ($rows as $r) $m[$r->ym] = (float) $r->$col;
    return $m;
}
function cxs_fmt($v, string $fmt): string
{
    if ($v === null) return '—';
    switch ($fmt) {
        case 'pct': return round((float) $v) . '%';
        case 'dec': return number_format((float) $v, 1);
        case 'money': return sl_money($v);
        case 'stars': return number_format((float) $v, 1) . ' ★';
    }
    return sl_num($v);
}
function cxs_chart(string $title, string $name, array $labels, array $values, string $fmt = 'num', array $opt = []): array
{
    return $opt + [
        'title' => $title, 'name' => $name, 'labels' => $labels, 'full' => $opt['full'] ?? $labels,
        'values' => array_map(fn($v) => $v === null ? null : round((float) $v, 2), $values),
        'display' => array_map(fn($v) => cxs_fmt($v, $fmt), $values), 'fmt' => $fmt,
        'highlight' => $opt['highlight'] ?? null, 'note' => $opt['note'] ?? '',
    ];
}
function cxs_fig(string $label, string $value, string $sub = ''): array { return ['label' => $label, 'value' => $value, 'sub' => $sub]; }
/** Breakdown rows from [label => number]; bar widths relative to the largest. */
function cxs_bk(string $title, array $pairs, string $fmt = 'num', array $subs = []): ?array
{
    if (!$pairs) return null;
    $max = max(1, max(array_map('floatval', $pairs)));
    $rows = [];
    foreach ($pairs as $label => $n) {
        $rows[] = ['label' => (string) $label, 'value' => cxs_fmt($n, $fmt) . (isset($subs[$label]) ? ' ' . $subs[$label] : ''), 'w' => sl_pct($n, $max)];
    }
    return ['title' => $title, 'rows' => $rows];
}
/** Top values of a customer column (city, sign-up source) among the customers matching $filter. */
function cxs_by(string $col, string $filter, string $title, string ...$more): ?array
{
    $cols = ['city' => 'city', 'source' => 'signup_source', 'doc' => 'document_type'];
    if (!isset($cols[$col]) || !sl_columnExists('cdb_users', $cols[$col])) return null;
    $b = [];
    $w = sl_customersFilterSql($filter, $b, $GLOBALS['ym']);
    foreach ($more as $f) $w .= sl_customersFilterSql($f, $b, $GLOBALS['ym']);
    $c = 'u.' . $cols[$col];
    $rows = cxs_rows("SELECT COALESCE(NULLIF($c, ''), 'Not recorded') AS label, COUNT(*) AS n FROM cdb_users u WHERE u.userlevel = 1" . cxs_scope() . "$w GROUP BY label ORDER BY n DESC, label LIMIT 5", $b);
    $pairs = [];
    foreach ($rows as $r) $pairs[$r->label] = (int) $r->n;
    return cxs_bk($title, $pairs);
}
/**
 * The customers matching a list filter: total and the first $limit rows in $order (a fixed
 * expression from this file, never request input). $note(row) adds the statistic's own detail.
 */
function cxs_customers(string $title, string $filter, string $order, ?callable $note = null, string $extra = '', int $limit = 10): array
{
    $ym = $GLOBALS['ym'];
    $b = [];
    $w = sl_customersFilterSql($filter, $b, $ym);
    $scope = cxs_scope();
    $total = (int) cxs_val("SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel = 1$scope$w", $b);
    $city = sl_columnExists('cdb_users', 'city') ? 'u.city' : "''";
    $src = sl_columnExists('cdb_users', 'signup_source') ? 'u.signup_source' : "''";
    // Parcel counts and last activity from one grouped pass per table, joined to the customers in
    // scope (MariaDB pushes u.id into the grouped tables when that is cheaper), not 4 subqueries a row.
    $rows = cxs_rows("SELECT u.id, u.fname, u.lname, u.locker, u.created, $city AS city, $src AS src,
            COALESCE(a.air_n, 0) AS air, COALESCE(s.sea_n, 0) AS sea,
            CASE WHEN a.air_at IS NULL THEN s.sea_at WHEN s.sea_at IS NULL THEN a.air_at ELSE GREATEST(a.air_at, s.sea_at) END AS last_at
            $extra
        FROM cdb_users u
        LEFT JOIN (SELECT sender_id, COUNT(*) AS air_n, MAX(order_date) AS air_at FROM cdb_add_order WHERE is_deleted = 0 AND sender_id IS NOT NULL GROUP BY sender_id) a ON a.sender_id = u.id
        LEFT JOIN (SELECT sender_id, COUNT(*) AS sea_n, MAX(order_date) AS sea_at FROM cdb_customers_packages WHERE sender_id IS NOT NULL GROUP BY sender_id) s ON s.sender_id = u.id
        WHERE u.userlevel = 1$scope$w ORDER BY $order, u.id DESC LIMIT " . (int) $limit, $b);
    $out = [];
    foreach ($rows as $r) {
        $name = trim($r->fname . ' ' . $r->lname) ?: 'Customer #' . (int) $r->id;
        $out[] = [
            'id' => (int) $r->id, 'name' => $name, 'ini' => sl_initials($name), 'locker' => $r->locker ?: '—', 'city' => $r->city ?: '—',
            'air' => (int) $r->air, 'sea' => (int) $r->sea, 'parcels' => (int) $r->air + (int) $r->sea,
            'last' => $r->last_at ? 'Last parcel ' . sl_ago($r->last_at) : 'No parcels yet',
            'note' => $note ? (string) $note($r) : '',
            'url' => sl_route('CustomerProfile', ['id' => (int) $r->id]),
        ];
    }
    return ['title' => $title, 'total' => $total, 'filter' => $filter, 'ym' => $filter === 'month' ? $ym : null, 'label' => sl_customersFilterLabels($ym)[$filter] ?? '', 'rows' => $out];
}
function cxs_method(string $m): string
{
    return ['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'no_otp' => 'Without a code (OTP off)', 'email' => 'E-mail'][strtolower($m)] ?? ucfirst(str_replace('_', ' ', $m));
}
function cxs_joined($r): string { return 'Joined ' . sl_date($r->created, 'M j, Y') . ($r->src ? ' · ' . $r->src : ''); }

/* ---------- shared figures ---------- */
$scope = cxs_scope();
$months = cxs_months();
$labels = array_column($months, 'l');
$full = array_column($months, 'full');
$FROM = $months[0]['ym'] . '-01';
$total = cxs_count('all');
$PX = "(SELECT sender_id, order_date, 'air' AS mode FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= :cx_from
        UNION ALL SELECT sender_id, order_date, 'sea' AS mode FROM cdb_customers_packages WHERE order_date >= :cx_from) x
        JOIN cdb_users u ON u.id = x.sender_id AND u.userlevel = 1$scope";
$AIR = "FROM cdb_add_order o JOIN cdb_users u ON u.id = o.sender_id AND u.userlevel = 1$scope WHERE o.is_deleted = 0";
$series = function (array $map) use ($months): array { return array_map(fn($m) => $map[$m['ym']] ?? 0, $months); };
$newMap = cxs_map(cxs_rows("SELECT DATE_FORMAT(u.created, '%Y-%m') AS ym, COUNT(*) AS n FROM cdb_users u WHERE u.userlevel = 1$scope AND u.created >= :cx_from GROUP BY ym", [':cx_from' => $FROM]));
$Y0 = date('Y-01-01');
// every parcel (air and sea) of the customers in scope, as x.sender_id / x.order_date / x.mode
$ALL = "(SELECT sender_id, order_date, 'air' AS mode FROM cdb_add_order WHERE is_deleted = 0 AND sender_id IS NOT NULL
        UNION ALL SELECT sender_id, order_date, 'sea' AS mode FROM cdb_customers_packages WHERE sender_id IS NOT NULL) x
        JOIN cdb_users u ON u.id = x.sender_id AND u.userlevel = 1$scope";
$yrCol = ', (SELECT COUNT(*) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.is_deleted = 0 AND o.order_date >= :cx_y0)
          + (SELECT COUNT(*) FROM cdb_customers_packages p WHERE p.sender_id = u.id AND p.order_date >= :cx_y0) AS yr';
$yrNote = fn($r) => (int) $r->yr . ' parcel' . ((int) $r->yr === 1 ? '' : 's') . ' this year';
$distribution = function () use ($ALL, $Y0): ?array {
    $rows = cxs_rows("SELECT CASE WHEN c = 1 THEN '1 parcel' WHEN c = 2 THEN '2 parcels' WHEN c <= 5 THEN '3–5 parcels' ELSE '6 or more' END AS b, COUNT(*) AS n
        FROM (SELECT x.sender_id, COUNT(*) AS c FROM $ALL WHERE x.order_date >= :cx_y0 GROUP BY x.sender_id) t GROUP BY b", [':cx_y0' => $Y0]);
    $pairs = array_fill_keys(['1 parcel', '2 parcels', '3–5 parcels', '6 or more'], 0);
    foreach ($rows as $r) $pairs[$r->b] = (int) $r->n;
    return array_sum($pairs) ? cxs_bk('Parcels per customer this year', $pairs) : null;
};
$res = ['success' => true, 'key' => $stat, 'links' => []];

switch ($stat) {
    case 'total':
        $before = (int) cxs_val("SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel = 1$scope AND u.created < :cx_from", [':cx_from' => $FROM]);
        $run = $before;
        $cum = array_map(function ($n) use (&$run) { $run += $n; return $run; }, $series($newMap));
        $joined = (int) array_sum($series($newMap));
        $res += [
            'title' => 'Total customers', 'lede' => 'Every customer account, how the base has grown, and where they come from.',
            'chart' => cxs_chart('Customers at the end of each month', 'Customers', $labels, $cum, 'num', ['full' => $full, 'highlight' => 11]),
            'figures' => [cxs_fig('All customers', sl_num($total)), cxs_fig('Joined this month', sl_num($newMap[date('Y-m')] ?? 0)), cxs_fig('Joined in 12 months', sl_num($joined)), cxs_fig('12-month growth', $before ? '+' . round($joined / $before * 100) . '%' : '—', 'since ' . date('M Y', strtotime($FROM)))],
            'breakdowns' => [cxs_by('city', 'all', 'By city'), cxs_by('source', 'all', 'By sign-up source')],
            'customers' => cxs_customers('Newest customers', 'all', 'u.created DESC', 'cxs_joined'),
        ];
        break;

    case 'active':
        $act = cxs_map(cxs_rows("SELECT DATE_FORMAT(x.order_date, '%Y-%m') AS ym, COUNT(DISTINCT x.sender_id) AS n FROM $PX GROUP BY ym", [':cx_from' => $FROM]));
        $n = cxs_count('active');
        $p90 = (int) cxs_val("SELECT COUNT(*) FROM $PX", [':cx_from' => date('Y-m-d', strtotime('-90 days'))]);
        $ship = ['Air only' => cxs_count('active', 'air'), 'Air and sea' => cxs_count('active', 'both'), 'Sea only' => cxs_count('active', 'sea')];
        $res += [
            'title' => 'Active customers', 'lede' => 'Customers who shipped by air or sea in the last 90 days.',
            'chart' => cxs_chart('Active customers per month', 'Active customers', $labels, $series($act), 'num', ['full' => $full, 'highlight' => 11, 'note' => 'Customers with at least one parcel that month']),
            'figures' => [cxs_fig('Active (90 days)', sl_num($n), sl_pct($n, $total) . '% of all customers'), cxs_fig('Parcels in 90 days', sl_num($p90)), cxs_fig('Parcels each', $n ? number_format($p90 / $n, 1) : '0', 'per active customer'), cxs_fig('Shipped this month', sl_num($act[date('Y-m')] ?? 0))],
            'breakdowns' => [cxs_by('city', 'active', 'Active by city'), cxs_bk('How they ship', array_filter($ship))],
            'customers' => cxs_customers('Most recently active', 'active', 'last_at DESC'),
        ];
        break;

    case 'new':
        $nm = (int) ($newMap[date('Y-m')] ?? 0);
        $pm = (int) ($newMap[date('Y-m', strtotime('first day of last month'))] ?? 0);
        $b0 = [];
        $shipped = (int) cxs_val("SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel = 1$scope" . sl_customersFilterSql('new', $b0) . " AND (u.id IN (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND sender_id IS NOT NULL) OR u.id IN (SELECT sender_id FROM cdb_customers_packages WHERE sender_id IS NOT NULL))");
        $change = $pm ? round(($nm - $pm) / $pm * 100) : null;
        $res += [
            'title' => 'New this month', 'lede' => 'Sign-ups in ' . date('F Y') . ', compared with the months before.',
            'chart' => cxs_chart('New sign-ups per month', 'New customers', $labels, $series($newMap), 'num', ['full' => $full, 'highlight' => 11]),
            'figures' => [cxs_fig('This month', sl_num($nm)), cxs_fig(date('F', strtotime('first day of last month')), sl_num($pm)), cxs_fig('Change', $change === null ? '—' : ($change > 0 ? '▲ ' : ($change < 0 ? '▼ ' : '')) . abs($change) . '%', 'vs last month'), cxs_fig('Already shipped', sl_num($shipped), sl_pct($shipped, $nm) . '% of this month\'s sign-ups')],
            'breakdowns' => [cxs_by('source', 'new', 'Sign-up source this month'), cxs_by('city', 'new', 'City this month')],
            'customers' => cxs_customers('Joined this month', 'new', 'u.created DESC', 'cxs_joined'),
        ];
        break;

    case 'phone':
        $has = sl_tableExists('cdb_profile_phone_verified');
        $n = cxs_count('phone');
        $rate = array_fill(0, 12, null);
        $figs = [cxs_fig('Verified phone', sl_num($n), sl_pct($n, $total) . '% of all customers'), cxs_fig('Not yet verified', sl_num($total - $n))];
        $bks = [];
        if ($has) {
            $vBefore = (int) cxs_val("SELECT COUNT(*) FROM cdb_profile_phone_verified v JOIN cdb_users u ON u.id = v.user_id AND u.userlevel = 1$scope WHERE GREATEST(u.created, v.verified_at) < :cx_from", [':cx_from' => $FROM]);
            $vMap = cxs_map(cxs_rows("SELECT DATE_FORMAT(GREATEST(u.created, v.verified_at), '%Y-%m') AS ym, COUNT(*) AS n FROM cdb_profile_phone_verified v JOIN cdb_users u ON u.id = v.user_id AND u.userlevel = 1$scope WHERE GREATEST(u.created, v.verified_at) >= :cx_from GROUP BY ym", [':cx_from' => $FROM]));
            $cBefore = (int) cxs_val("SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel = 1$scope AND u.created < :cx_from", [':cx_from' => $FROM]);
            $cv = $vBefore; $cc = $cBefore;
            foreach ($months as $i => $m) {
                $cv += $vMap[$m['ym']] ?? 0; $cc += $newMap[$m['ym']] ?? 0;
                $rate[$i] = $cc ? $cv / $cc * 100 : null;
            }
            $thisMonth = (int) cxs_val("SELECT COUNT(*) FROM cdb_profile_phone_verified v JOIN cdb_users u ON u.id = v.user_id AND u.userlevel = 1$scope WHERE v.verified_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
            $fast = (int) cxs_val("SELECT COUNT(*) FROM cdb_profile_phone_verified v JOIN cdb_users u ON u.id = v.user_id AND u.userlevel = 1$scope WHERE v.verified_at <= u.created + INTERVAL 1 DAY");
            $figs[] = cxs_fig('Verified this month', sl_num($thisMonth));
            $figs[] = cxs_fig('Within a day', $n ? sl_pct($fast, $n) . '%' : '—', 'verified within 24 h of sign-up');
            $methods = [];
            foreach (cxs_rows("SELECT COALESCE(NULLIF(v.method, ''), 'Other') AS label, COUNT(*) AS n FROM cdb_profile_phone_verified v JOIN cdb_users u ON u.id = v.user_id AND u.userlevel = 1$scope GROUP BY label ORDER BY n DESC LIMIT 5") as $r) {
                $methods[cxs_method($r->label)] = (int) $r->n;
            }
            $bks[] = cxs_bk('How they verified', $methods);
        }
        $bks[] = cxs_by('city', 'phone', 'Verified by city');
        $res += [
            'title' => 'Verified phone', 'lede' => 'Customers who confirmed their phone number with a one-time code.',
            'chart' => cxs_chart('Share of customers with a verified phone', 'Verified phone', $labels, $rate, 'pct', ['full' => $full, 'highlight' => 11, 'unit' => '%', 'note' => 'At the end of each month']),
            'figures' => $figs, 'breakdowns' => $bks,
            'customers' => cxs_customers('Recently verified', 'phone', $has ? 'vat DESC' : 'u.created DESC', $has ? fn($r) => 'Verified ' . sl_date($r->vat, 'M j') . ($r->vm ? ' · ' . cxs_method($r->vm) : '') : null,
                $has ? ', (SELECT v.verified_at FROM cdb_profile_phone_verified v WHERE v.user_id = u.id) AS vat, (SELECT v.method FROM cdb_profile_phone_verified v WHERE v.user_id = u.id) AS vm' : ''),
        ];
        break;

    case 'idv':
        $doc = sl_columnExists('cdb_users', 'document_photo') ? "(u.document_photo IS NOT NULL AND u.document_photo <> '')" : (sl_columnExists('cdb_users', 'approve') ? 'u.approve = 1' : '0');
        $coh = cxs_rows("SELECT DATE_FORMAT(u.created, '%Y-%m') AS ym, COUNT(*) AS n, SUM(CASE WHEN $doc THEN 1 ELSE 0 END) AS k FROM cdb_users u WHERE u.userlevel = 1$scope AND u.created >= :cx_from GROUP BY ym", [':cx_from' => $FROM]);
        $cm = [];
        foreach ($coh as $r) $cm[$r->ym] = $r->n ? $r->k / $r->n * 100 : null;
        $n = cxs_count('idv');
        $figs = [cxs_fig('ID on file', sl_num($n), sl_pct($n, $total) . '% of all customers'), cxs_fig('No ID yet', sl_num($total - $n))];
        if (sl_columnExists('cdb_users', 'approve')) $figs[] = cxs_fig('Awaiting approval', sl_num(cxs_count('unapproved')), 'accounts to review');
        $figs[] = cxs_fig('This month', isset($cm[date('Y-m')]) ? round($cm[date('Y-m')]) . '%' : '—', 'of new sign-ups sent an ID');
        $res += [
            'title' => 'ID verified', 'lede' => 'Customers with an identity document on file.',
            'chart' => cxs_chart('Sign-ups who sent an ID, by month joined', 'ID on file', $labels, array_map(fn($m) => $cm[$m['ym']] ?? null, $months), 'pct', ['full' => $full, 'highlight' => 11, 'unit' => '%', 'note' => 'Months without sign-ups show no bar']),
            'figures' => $figs,
            'breakdowns' => [cxs_by('doc', 'idv', 'Document type'), cxs_by('city', 'idv', 'By city')],
            'customers' => cxs_customers('Most recent with ID', 'idv', 'u.created DESC', fn($r) => ($r->doc_type ?: 'ID on file') . ((int) $r->appr === 0 ? ' · awaiting approval' : ''),
                ', ' . (sl_columnExists('cdb_users', 'document_type') ? 'u.document_type' : "''") . ' AS doc_type, ' . (sl_columnExists('cdb_users', 'approve') ? 'u.approve' : '1') . ' AS appr'),
        ];
        break;

    case 'repeat':
        $rows = cxs_rows("SELECT DATE_FORMAT(x.order_date, '%Y-%m') AS ym, COUNT(DISTINCT x.sender_id) AS shippers,
                COUNT(DISTINCT CASE WHEN f.first_date < DATE_FORMAT(x.order_date, '%Y-%m-01') THEN x.sender_id END) AS ret
            FROM $ALL JOIN (SELECT sender_id, MIN(order_date) AS first_date FROM (SELECT sender_id, order_date FROM cdb_add_order WHERE is_deleted = 0 AND sender_id IS NOT NULL UNION ALL SELECT sender_id, order_date FROM cdb_customers_packages WHERE sender_id IS NOT NULL) a GROUP BY sender_id) f ON f.sender_id = x.sender_id
            WHERE x.order_date >= :cx_from GROUP BY ym", [':cx_from' => $FROM]);
        $rm = [];
        foreach ($rows as $r) $rm[$r->ym] = $r->shippers ? $r->ret / $r->shippers * 100 : null;
        $rep = cxs_count('repeat');
        $shp = cxs_count('shipped');
        $gap = (float) cxs_val("SELECT AVG(g) FROM (SELECT DATEDIFF(MAX(x.order_date), MIN(x.order_date)) / GREATEST(COUNT(*) - 1, 1) AS g FROM $ALL GROUP BY x.sender_id HAVING COUNT(*) > 1) gx");
        $res += [
            'title' => 'Repeat customers', 'lede' => 'Customers who shipped two or more times this year, by air or sea.',
            'chart' => cxs_chart('Returning customers among each month\'s shippers', 'Returning share', $labels, array_map(fn($m) => $rm[$m['ym']] ?? null, $months), 'pct', ['full' => $full, 'highlight' => 11, 'unit' => '%', 'note' => 'Shipped that month and had shipped before']),
            'figures' => [cxs_fig('Repeat rate', sl_pct($rep, $shp) . '%', 'of this year\'s shippers'), cxs_fig('Repeat customers', sl_num($rep), 'shipped 2+ times'), cxs_fig('Shipped once', sl_num($shp - $rep), 'this year'), cxs_fig('Ships every', $gap > 0 ? round($gap) . ' days' : '—', 'average gap')],
            'breakdowns' => [$distribution(), cxs_by('city', 'repeat', 'By city')],
            'customers' => cxs_customers('Most parcels this year', 'repeat', 'yr DESC, last_at DESC', $yrNote, $yrCol),
        ];
        break;

    case 'parcels':
        $pm = cxs_rows("SELECT DATE_FORMAT(x.order_date, '%Y-%m') AS ym, COUNT(*) AS parcels, COUNT(DISTINCT x.sender_id) AS shippers FROM $ALL WHERE x.order_date >= :cx_from GROUP BY ym", [':cx_from' => $FROM]);
        $avg = [];
        foreach ($pm as $r) $avg[$r->ym] = $r->shippers ? $r->parcels / $r->shippers : 0;
        $byMode = ['air' => 0, 'sea' => 0];
        foreach (cxs_rows("SELECT x.mode, COUNT(*) AS n FROM $ALL WHERE x.order_date >= :cx_y0 GROUP BY x.mode", [':cx_y0' => $Y0]) as $r) $byMode[$r->mode] = (int) $r->n;
        $py = $byMode['air'] + $byMode['sea'];
        $shp = cxs_count('shipped');
        $res += [
            'title' => 'Average parcels each', 'lede' => 'How many parcels, by air or sea, each shipping customer sends.',
            'chart' => cxs_chart('Parcels per shipping customer, each month', 'Parcels each', $labels, $series($avg), 'dec', ['full' => $full, 'highlight' => 11]),
            'figures' => [cxs_fig('Parcels each', $shp ? number_format($py / $shp, 1) : '0', 'this year'), cxs_fig('Parcels this year', sl_num($py), $byMode['air'] . ' air · ' . $byMode['sea'] . ' sea'), cxs_fig('Customers who shipped', sl_num($shp), 'this year'), cxs_fig('Shipped 2+ times', sl_num(cxs_count('repeat')), 'this year')],
            'breakdowns' => [$distribution(), cxs_bk('Air and sea this year', array_filter(['Air parcels' => $byMode['air'], 'Sea packages' => $byMode['sea']]))],
            'customers' => cxs_customers('Top shippers this year', 'shipped', 'yr DESC, last_at DESC', $yrNote, $yrCol),
        ];
        break;

    case 'due':
        $UNPAID = "$AIR AND o.status_invoice = 23 AND o.status_courier <> 21";
        $age = array_fill_keys(['0–30 days', '31–60 days', '61–90 days', 'Over 90 days'], 0.0);
        $ageN = array_fill_keys(array_keys($age), 0);
        foreach (cxs_rows("SELECT CASE WHEN DATEDIFF(CURDATE(), o.order_date) <= 30 THEN '0–30 days' WHEN DATEDIFF(CURDATE(), o.order_date) <= 60 THEN '31–60 days' WHEN DATEDIFF(CURDATE(), o.order_date) <= 90 THEN '61–90 days' ELSE 'Over 90 days' END AS b, COALESCE(SUM(o.total_order), 0) AS amt, COUNT(*) AS n $UNPAID GROUP BY b") as $r) {
            $age[$r->b] = (float) $r->amt; $ageN[$r->b] = (int) $r->n;
        }
        $owing = cxs_count('due');
        $owed = array_sum($age);
        $oldest = (int) cxs_val("SELECT COALESCE(MAX(DATEDIFF(CURDATE(), o.order_date)), 0) $UNPAID");
        $byCity = [];
        $cityCol = sl_columnExists('cdb_users', 'city') ? "COALESCE(NULLIF(u.city, ''), 'Not recorded')" : "'Not recorded'";
        foreach (cxs_rows("SELECT $cityCol AS label, COALESCE(SUM(o.total_order), 0) AS amt $UNPAID GROUP BY label ORDER BY amt DESC LIMIT 5") as $r) $byCity[$r->label] = (float) $r->amt;
        $res += [
            'title' => 'With balance due', 'lede' => 'Customers with unpaid invoices, and how long the money has been owed.',
            'chart' => cxs_chart('Amount owed by age of the invoice', 'Owed', array_keys($age), array_values($age), 'money', ['note' => implode(' · ', array_map(fn($k, $v) => "$k: $v invoice" . ($v === 1 ? '' : 's'), array_keys($ageN), $ageN))]),
            'figures' => [cxs_fig('Customers owing', sl_num($owing)), cxs_fig('Total owed', sl_money($owed)), cxs_fig('Average each', sl_money($owing ? $owed / $owing : 0)), cxs_fig('Oldest unpaid', $oldest ? $oldest . ' days' : '—')],
            'breakdowns' => [cxs_bk('Owed by city', $byCity, 'money')],
            'customers' => cxs_customers('Largest balances', 'due', 'owed DESC', fn($r) => 'Owes ' . sl_money($r->owed) . ' · since ' . sl_date($r->since, 'M j'),
                ', (SELECT COALESCE(SUM(o.total_order), 0) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.is_deleted = 0 AND o.status_invoice = 23 AND o.status_courier <> 21) AS owed, (SELECT MIN(o.order_date) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.is_deleted = 0 AND o.status_invoice = 23 AND o.status_courier <> 21) AS since'),
            'links' => [['label' => 'Open accounts receivable', 'href' => sl_route('AccountsReceivable')]],
        ];
        break;

    case 'quiet':
        $b = [];
        $qw = sl_customersFilterSql('quiet', $b);
        $buckets = array_fill_keys(['Never shipped', '3–6 months', '6–12 months', 'Over a year'], 0);
        foreach (cxs_rows("SELECT CASE WHEN l.last_at IS NULL THEN 'Never shipped' WHEN l.last_at >= CURDATE() - INTERVAL 180 DAY THEN '3–6 months' WHEN l.last_at >= CURDATE() - INTERVAL 365 DAY THEN '6–12 months' ELSE 'Over a year' END AS bk, COUNT(*) AS n
            FROM cdb_users u LEFT JOIN (SELECT sender_id, MAX(order_date) AS last_at FROM (SELECT sender_id, order_date FROM cdb_add_order WHERE is_deleted = 0 UNION ALL SELECT sender_id, order_date FROM cdb_customers_packages) a GROUP BY sender_id) l ON l.sender_id = u.id
            WHERE u.userlevel = 1$scope$qw GROUP BY bk", $b) as $r) $buckets[$r->bk] = (int) $r->n;
        $n = cxs_count('quiet');
        $recent = (int) cxs_val("SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel = 1$scope$qw AND u.id IN (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= CURDATE() - INTERVAL 120 DAY UNION SELECT sender_id FROM cdb_customers_packages WHERE order_date >= CURDATE() - INTERVAL 120 DAY)", $b);
        $res += [
            'title' => 'Inactive 90 days', 'lede' => 'Customers with no parcel in the last 90 days: who to win back first.',
            'chart' => cxs_chart('How long they have been quiet', 'Customers', array_keys($buckets), array_values($buckets), 'num', ['note' => 'Time since their last parcel']),
            'figures' => [cxs_fig('Inactive', sl_num($n), sl_pct($n, $total) . '% of all customers'), cxs_fig('Never shipped', sl_num($buckets['Never shipped'])), cxs_fig('Just went quiet', sl_num($recent), 'last parcel 90–120 days ago'), cxs_fig('Reachable', sl_num(cxs_count('quiet', 'phone')), 'with a verified phone')],
            'breakdowns' => [cxs_by('city', 'quiet', 'By city'), cxs_by('source', 'quiet', 'By sign-up source')],
            'customers' => cxs_customers('Most recently gone quiet', 'quiet', 'last_at IS NULL, last_at DESC, u.created DESC', fn($r) => $r->last_at ? 'Quiet since ' . sl_date($r->last_at, 'M j, Y') : 'Never shipped · joined ' . sl_date($r->created, 'M j')),
            'links' => [['label' => 'Win back quiet customers', 'href' => sl_route('ComposeMessage', ['segment' => 'quiet'])]],
        ];
        break;

    case 'csat':
        if (!sl_tableExists('cdb_cs_tickets')) sl_json(['success' => false, 'message' => 'No ratings yet'], 404);
        $T = $scope === '' ? 'FROM cdb_cs_tickets t WHERE t.csat_score IS NOT NULL' : "FROM cdb_cs_tickets t JOIN cdb_users u ON u.id = t.customer_id AND u.userlevel = 1$scope WHERE t.csat_score IS NOT NULL";
        $dist = array_fill(1, 5, 0);
        foreach (cxs_rows("SELECT t.csat_score AS s, COUNT(*) AS n $T GROUP BY s") as $r) if ($r->s >= 1 && $r->s <= 5) $dist[(int) $r->s] = (int) $r->n;
        $cnt = array_sum($dist);
        $avg = $cnt ? array_sum(array_map(fn($s, $n) => $s * $n, array_keys($dist), $dist)) / $cnt : 0;
        $monthly = [];
        $subs = [];
        foreach (cxs_rows("SELECT DATE_FORMAT(COALESCE(t.csat_at, t.created_at), '%Y-%m') AS ym, AVG(t.csat_score) AS a, COUNT(*) AS n $T AND COALESCE(t.csat_at, t.created_at) >= :cx_six GROUP BY ym ORDER BY ym", [':cx_six' => date('Y-m-01', strtotime('first day of -5 months'))]) as $r) {
            $k = date('M Y', strtotime($r->ym . '-01'));
            $monthly[$k] = (float) $r->a;
            $subs[$k] = '(' . (int) $r->n . ')';
        }
        $res += [
            'title' => 'Satisfaction', 'lede' => 'How customers rated support after their ticket was solved.',
            'chart' => cxs_chart('Ratings from 1 to 5 stars', 'Ratings', ['1 ★', '2 ★', '3 ★', '4 ★', '5 ★'], array_values($dist), 'num'),
            'figures' => [cxs_fig('Average', $cnt ? number_format($avg, 1) . ' ★' : '—', 'out of 5'), cxs_fig('Ratings', sl_num($cnt)), cxs_fig('Happy', sl_pct($dist[4] + $dist[5], $cnt) . '%', 'gave 4 or 5 stars'), cxs_fig('Unhappy', sl_num($dist[1] + $dist[2]), 'gave 1 or 2 stars')],
            'breakdowns' => [$monthly ? array_merge(cxs_bk('', $monthly, 'stars', $subs), ['title' => 'Average by month (ratings)']) : null],
            'customers' => cxs_customers('Latest ratings', 'rated', 'rated_at DESC', fn($r) => 'Rated ' . (int) $r->score . ' ★ · ' . sl_date($r->rated_at, 'M j'),
                ', (SELECT t.csat_score FROM cdb_cs_tickets t WHERE t.customer_id = u.id AND t.csat_score IS NOT NULL ORDER BY COALESCE(t.csat_at, t.created_at) DESC LIMIT 1) AS score, (SELECT MAX(COALESCE(t.csat_at, t.created_at)) FROM cdb_cs_tickets t WHERE t.customer_id = u.id AND t.csat_score IS NOT NULL) AS rated_at'),
            'links' => [['label' => 'Open support reports', 'href' => sl_route('CsReports')]],
        ];
        break;

    case 'blocked':
        $n = cxs_count('blocked');
        if (sl_tableExists('cdb_customer_blocks')) {
            $B = "FROM cdb_customer_blocks b JOIN cdb_users u ON u.id = b.user_id AND u.userlevel = 1$scope";
            $bm = cxs_map(cxs_rows("SELECT DATE_FORMAT(b.blocked_at, '%Y-%m') AS ym, COUNT(*) AS n $B WHERE b.blocked_at >= :cx_from GROUP BY ym", [':cx_from' => $FROM]));
            $reasons = [];
            foreach (cxs_rows("SELECT b.reason AS label, COUNT(*) AS n $B WHERE b.unblocked_at IS NULL GROUP BY label ORDER BY n DESC LIMIT 5") as $r) $reasons[$r->label] = (int) $r->n;
            $res += [
                'chart' => cxs_chart('Accounts blocked per month', 'Blocked', $labels, $series($bm), 'num', ['full' => $full, 'highlight' => 11]),
                'figures' => [cxs_fig('Blocked now', sl_num($n), sl_pct($n, $total) . '% of all customers'), cxs_fig('Blocked this month', sl_num($bm[date('Y-m')] ?? 0)), cxs_fig('Unblocked', sl_num(cxs_val("SELECT COUNT(*) $B WHERE b.unblocked_at >= :cx_from", [':cx_from' => $FROM])), 'in 12 months'), cxs_fig('Parcels on hold', sl_num(cxs_val("SELECT COUNT(*) $B WHERE b.unblocked_at IS NULL AND b.hold_parcels = 1")), 'accounts with parcels held')],
                'breakdowns' => [cxs_bk('Why they were blocked', $reasons), cxs_by('city', 'blocked', 'By city')],
                'customers' => cxs_customers('Recently blocked', 'blocked', 'bat DESC', fn($r) => ($r->reason ?: 'Blocked') . ' · ' . sl_date($r->bat, 'M j'),
                    ', (SELECT b.reason FROM cdb_customer_blocks b WHERE b.user_id = u.id AND b.unblocked_at IS NULL ORDER BY b.id DESC LIMIT 1) AS reason, (SELECT MAX(b.blocked_at) FROM cdb_customer_blocks b WHERE b.user_id = u.id AND b.unblocked_at IS NULL) AS bat'),
            ];
        } else {
            $res += [
                'chart' => cxs_chart('Inactive accounts by month joined', 'Inactive', $labels, $series(cxs_map(cxs_rows("SELECT DATE_FORMAT(u.created, '%Y-%m') AS ym, COUNT(*) AS n FROM cdb_users u WHERE u.userlevel = 1$scope AND u.active = 0 AND u.created >= :cx_from GROUP BY ym", [':cx_from' => $FROM]))), 'num', ['full' => $full]),
                'figures' => [cxs_fig('Blocked now', sl_num($n))], 'breakdowns' => [cxs_by('city', 'blocked', 'By city')],
                'customers' => cxs_customers('Inactive accounts', 'blocked', 'u.created DESC', 'cxs_joined'),
            ];
        }
        $res += ['title' => 'Blocked customers', 'lede' => 'Accounts that cannot log in, book or receive offers until unblocked.'];
        break;

    case 'month':
        $m0 = $ym . '-01';
        $m1 = date('Y-m-01', strtotime($m0 . ' +1 month'));
        $p0 = date('Y-m-01', strtotime($m0 . ' -1 month'));
        $days = (int) date('t', strtotime($m0));
        $wk = array_fill(0, $days > 28 ? 5 : 4, 0);
        foreach (cxs_rows("SELECT LEAST(FLOOR((DAY(u.created) - 1) / 7), 4) AS w, COUNT(*) AS n FROM cdb_users u WHERE u.userlevel = 1$scope AND u.created >= :cx_m0 AND u.created < :cx_m1 GROUP BY w", [':cx_m0' => $m0, ':cx_m1' => $m1]) as $r) $wk[(int) $r->w] = (int) $r->n;
        $wl = ['1–7', '8–14', '15–21', '22–28', '29–' . $days];
        $wl = array_slice($wl, 0, count($wk));
        $n = array_sum($wk);
        $prev = (int) cxs_val("SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel = 1$scope AND u.created >= :cx_p0 AND u.created < :cx_m0", [':cx_p0' => $p0, ':cx_m0' => $m0]);
        $PXM = str_replace('order_date >= :cx_from', 'order_date >= :cx_m0 AND order_date < :cx_m1', $PX);
        $act = (int) cxs_val("SELECT COUNT(DISTINCT x.sender_id) FROM $PXM", [':cx_m0' => $m0, ':cx_m1' => $m1]);
        $parcels = cxs_rows("SELECT x.mode, COUNT(*) AS n FROM $PXM GROUP BY x.mode", [':cx_m0' => $m0, ':cx_m1' => $m1]);
        $pp = ['air' => 0, 'sea' => 0];
        foreach ($parcels as $r) $pp[$r->mode] = (int) $r->n;
        $b = [];
        $shipped = (int) cxs_val("SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel = 1$scope" . sl_customersFilterSql('month', $b, $ym) . " AND (u.id IN (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND sender_id IS NOT NULL) OR u.id IN (SELECT sender_id FROM cdb_customers_packages WHERE sender_id IS NOT NULL))", $b);
        $change = $prev ? round(($n - $prev) / $prev * 100) : null;
        $name = date('F Y', strtotime($m0));
        $hi = array_search($ym, array_column($months, 'ym'), true);
        $res += [
            'title' => $name, 'lede' => 'Who signed up in ' . $name . ', where they came from, and how busy customers were.',
            'chart' => cxs_chart('Sign-ups by week of the month', 'New customers', $wl, $wk, 'num', ['full' => array_map(fn($l) => date('F', strtotime($m0)) . ' ' . $l, $wl)]),
            'figures' => [cxs_fig('New sign-ups', sl_num($n), $change === null ? 'none the month before' : ($change > 0 ? '▲ ' : ($change < 0 ? '▼ ' : '')) . abs($change) . '% vs ' . date('M', strtotime($p0))), cxs_fig('Active that month', sl_num($act), 'customers with a parcel'), cxs_fig('Parcels', sl_num($pp['air'] + $pp['sea']), $pp['air'] . ' air · ' . $pp['sea'] . ' sea'), cxs_fig('New who shipped', sl_num($shipped), sl_pct($shipped, $n) . '% of the sign-ups')],
            'breakdowns' => [cxs_by('source', 'month', 'Sign-up source'), cxs_by('city', 'month', 'City')],
            'customers' => cxs_customers('Signed up in ' . date('F', strtotime($m0)), 'month', 'u.created DESC', 'cxs_joined'),
            'month' => ['ym' => $ym, 'index' => $hi === false ? null : $hi],
        ];
        break;

    case 'air':
    case 'both':
    case 'sea':
        $names = ['air' => 'Air only', 'both' => 'Air and sea', 'sea' => 'Sea only'];
        $b = [];
        $gw = sl_customersFilterSql($stat, $b);
        $gm = cxs_map(cxs_rows("SELECT DATE_FORMAT(x.order_date, '%Y-%m') AS ym, COUNT(DISTINCT x.sender_id) AS n FROM $PX WHERE 1 = 1$gw GROUP BY ym", $b + [':cx_from' => $FROM]));
        $n = cxs_count($stat);
        $all = cxs_count('air') + cxs_count('both') + cxs_count('sea');
        $airP = (int) cxs_val("SELECT COUNT(*) $AIR$gw", $b);
        $seaP = (int) cxs_val("SELECT COUNT(*) FROM cdb_customers_packages p JOIN cdb_users u ON u.id = p.sender_id AND u.userlevel = 1$scope WHERE 1 = 1$gw", $b);
        $act = cxs_count($stat, 'active');
        $lede = ['air' => 'Customers who only ship by air.', 'both' => 'Customers who use both air freight and sea freight.', 'sea' => 'Customers who only ship by sea.'][$stat];
        $res += [
            'title' => 'Ship by: ' . $names[$stat], 'lede' => $lede,
            'chart' => cxs_chart('Customers shipping each month', $names[$stat], $labels, $series($gm), 'num', ['full' => $full, 'highlight' => 11]),
            'figures' => [cxs_fig('Customers', sl_num($n), sl_pct($n, $all) . '% of shipping customers'), cxs_fig('Air parcels', sl_num($airP)), cxs_fig('Sea packages', sl_num($seaP)), cxs_fig('Parcels each', $n ? number_format(($airP + $seaP) / $n, 1) : '0', 'all time')],
            'breakdowns' => [cxs_by('city', $stat, 'By city'), cxs_bk('Still shipping?', array_filter(['Active (90 days)' => $act, 'Gone quiet' => $n - $act]))],
            'customers' => cxs_customers('Most parcels', $stat, '(air + sea) DESC, last_at DESC', fn($r) => ($stat === 'sea' ? '' : (int) $r->air . ' air') . ($stat === 'both' ? ' · ' : '') . ($stat === 'air' ? '' : (int) $r->sea . ' sea')),
        ];
        break;
}
$res['breakdowns'] = array_values(array_filter($res['breakdowns'] ?? []));
$res['figures'] = array_slice($res['figures'] ?? [], 0, 4);
sl_json($res);
