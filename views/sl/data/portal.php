<?php
/**
 * Data for the public pages and the customer portal:
 * PublicTracking, QuoteCalculator, ClientDashboard, UserHome, MyBills, PayCheckout,
 * CustomerNotifications, AiStudio.
 * Customers only ever see rows with sender_id = $_SESSION['userid'].
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_auth.php';
require_once CDP_ROOT . '/helpers/sl_charts.php';
require_once CDP_ROOT . '/helpers/querys.php';
require_once CDP_ROOT . '/helpers/finance_switch.php';
require_once CDP_ROOT . '/helpers/air_journey.php';

/* ------------------------------------------------------------------ tracking */

/** Five customer-facing steps used by the PublicTracking and ClientDashboard boards. */
function sl_trackSteps(int $statusId, bool $air): array
{
    $steps = [
        ['l' => 'Registered', 'i' => 'i-newdoc'],
        ['l' => 'At warehouse', 'i' => 'i-warehouse'],
        ['l' => 'In transit', 'i' => $air ? 'i-plane' : 'i-ship'],
        ['l' => 'Arrival hub', 'i' => 'i-pin'],
        ['l' => 'Delivered', 'i' => 'i-truck'],
    ];
    $at = [1 => 0, 11 => 0, 17 => 0, 18 => 0, 2 => 1, 4 => 1, 10 => 1, 13 => 1, 25 => 1, 28 => 1, 29 => 1, 3 => 2, 5 => 3, 6 => 3, 7 => 3, 19 => 3, 23 => 3, 32 => 3, 14 => 3, 16 => 3, 8 => 4, 15 => 4];
    $idx = $at[$statusId] ?? 0;
    foreach ($steps as $i => &$s) {
        $s['cls'] = $i === $idx ? 'now' : '';
        $s['w'] = $i > $idx ? 'w' : '';
        $s['done'] = $i <= $idx;
    }
    return ['steps' => $steps, 'index' => $idx, 'pct' => (int) round($idx / 4 * 100)];
}

/** Public lookup of one tracking number (courier shipment first, then customer package). PII is masked. */
function sl_trackData(string $code, $core): array
{
    $code = preg_replace('/[^A-Za-z0-9-]/', '', $code);
    $d = ['code' => $code, 'found' => false, 'searched' => $code !== ''];
    if ($code === '') {
        return $d;
    }
    $isPkg = false;
    $res = cdp_getCourierTrack($code);
    $t = $res['data'] ?? null;
    if (!$t) {
        $res = cdp_getCustomersPackagesTrack($code);
        $t = $res['data'] ?? null;
        $isPkg = (bool) $t;
    }
    if (!$t) {
        return $d;
    }
    $table = $isPkg ? 'cdb_customers_packages' : 'cdb_add_order';
    $meta = sl_row("SELECT order_item_category, is_consolidate, order_date, total_weight, estimated_eta, tracking_purchase, provider_purchase, status_invoice, receiver_id, recipient_type, sender_id, order_deli_time FROM $table WHERE order_id = :id", [':id' => (int) $t->order_id]);
    $air = $meta && in_array((int) $meta->order_item_category, cdp_shipCategoryIds('air'), true);
    $eff = cdp_getEffectiveTrackStatus($t->order_no, (int) $t->status_courier, (int) ($meta->is_consolidate ?? 0), $isPkg);
    $statusId = (int) ($eff->status_id ?: $t->status_courier);
    $st = sl_customerStatus($statusId, (string) ($eff->mod_style ?: $t->mod_style));
    $addr = sl_row('SELECT * FROM cdb_address_shipments WHERE order_track = :t', [':t' => (string) $t->order_prefix . (string) $t->order_no]);
    $codes = [$code, (string) $t->order_prefix . (string) $t->order_no];
    if (!empty($eff->consolidate_code)) {
        $codes[] = (string) $eff->consolidate_code;
    }
    $events = array_reverse(cdp_getTrackEvents($codes));
    $ev = [];
    foreach ($events as $i => $e) {
        $s = sl_customerStatus((int) $e->status_courier, (string) $e->mod_style);
        $where = trim(implode(', ', array_filter([(string) ($e->t_city ?? ''), (string) ($e->t_dest ?? '')])));
        $ev[] = [
            'cls' => $i === 0 ? 'sl-tl-i now' : 'sl-tl-i done',
            'sty' => 'animation-delay:' . number_format(0.1 * $i, 1) . 's',
            'w' => $i === 0 ? '' : 'w',
            'ic' => $i === 0 ? 'i-route' : 'i-check',
            't' => $s['label'],
            's' => trim(sl_date($e->t_date, 'M j, Y · H:i') . ($where !== '' ? ' · ' . $where : '') . (!empty($e->flight_no) ? ' · Flight ' . $e->flight_no : '') . (!empty($e->comments) ? ' · ' . $e->comments : '')),
        ];
    }
    $itemsTable = $isPkg ? 'cdb_customers_packages_detail' : 'cdb_add_order_item';
    $items = sl_tableExists($itemsTable) ? sl_rows("SELECT order_item_description AS n, order_item_quantity AS q, order_item_weight AS w, order_item_length AS l, order_item_width AS wd, order_item_height AS ht FROM $itemsTable WHERE order_id = :id", [':id' => (int) $t->order_id]) : [];
    $dims = '';
    $wt = 0.0;
    foreach ($items as $it) {
        $wt += (float) $it->w * max(1, (int) $it->q);
        if ($dims === '' && (float) $it->l > 0) {
            $dims = rtrim(rtrim(number_format((float) $it->l, 1), '0'), '.') . ' × ' . rtrim(rtrim(number_format((float) $it->wd, 1), '0'), '.') . ' × ' . rtrim(rtrim(number_format((float) $it->ht, 1), '0'), '.') . ' ' . ($core->units ?? 'cm');
        }
    }
    if ($wt <= 0 && $meta) {
        $wt = (float) $meta->total_weight;
    }
    $recipient = null;
    if ($meta && $meta->receiver_id) {
        $recipient = ($meta->recipient_type ?? '') === 'user'
            ? sl_row('SELECT fname, lname FROM cdb_users WHERE id = :id', [':id' => (int) $meta->receiver_id])
            : sl_row('SELECT fname, lname FROM cdb_recipients WHERE id = :id', [':id' => (int) $meta->receiver_id]);
    }
    $mask = fn($s) => $s !== '' ? mb_substr($s, 0, 1) . '••••' : '';
    $deli = $meta && $meta->order_deli_time ? sl_row('SELECT delitime FROM cdb_delivery_time WHERE id = :id', [':id' => (int) $meta->order_deli_time]) : null;
    $steps = sl_trackSteps($statusId, $air);
    $pay = $meta ? (in_array((int) $meta->status_invoice, [1, 19], true) ? ['Paid', 'delivered'] : ((int) $meta->status_invoice === 23 || (int) $meta->status_invoice === 2 ? ['Payment due', 'pending'] : ['Not billed yet', 'plain'])) : ['—', 'plain'];
    $from = $addr ? trim(implode(', ', array_filter([(string) $addr->sender_city, (string) $addr->sender_country]))) : '';
    $to = $addr ? trim(implode(', ', array_filter([(string) $addr->recipient_city, (string) $addr->recipient_country]))) : '';
    $d['found'] = true;
    $d += [
        'number' => (string) $t->order_prefix . (string) $t->order_no,
        'st' => $st['label'], 'cls' => $st['cls'],
        'air' => $air, 'mode' => $air ? 'Air freight' : 'Sea freight',
        'from' => $from ?: '—', 'to' => $to ?: '—',
        'eta' => $meta && $meta->estimated_eta ? sl_date($meta->estimated_eta) : ($deli->delitime ?? '—'),
        'steps' => $steps['steps'], 'pct' => $steps['pct'], 'stepIndex' => $steps['index'],
        'events' => $ev,
        'registered' => sl_date($t->order_date ?? ($meta->order_date ?? ''), 'M j, Y'),
        'contents' => $items ? (string) $items[0]->n . (count($items) > 1 ? ' and ' . (count($items) - 1) . ' more' : '') : '—',
        'store' => (string) ($meta->provider_purchase ?? '') ?: '—',
        'carrierNo' => (string) ($meta->tracking_purchase ?? ''),
        'weight' => $wt > 0 ? number_format($wt, 1) . ' ' . ($core->weight_p ?? 'kg') : '—',
        'dims' => $dims ?: '—',
        'pieces' => count($items) ? array_sum(array_map(fn($i) => max(1, (int) $i->q), $items)) . ' piece(s)' : '—',
        'consolidation' => (string) ($eff->consolidate_code ?? '') ?: '—',
        'recipient' => $recipient ? trim($mask((string) $recipient->fname) . ' ' . $mask((string) $recipient->lname)) : '—',
        'pay' => $pay[0], 'payCls' => $pay[1],
        'items' => array_map(fn($i) => ['n' => (string) $i->n, 'q' => max(1, (int) $i->q), 'w' => (float) $i->w > 0 ? number_format((float) $i->w, 1) . ' ' . ($core->weight_p ?? 'kg') : ''], array_slice($items, 0, 8)),
        'flight' => !empty($events[0]->flight_no) ? (string) $events[0]->flight_no : '',
    ];
    return $d;
}

/* ------------------------------------------------------------------ quote */

function sl_quoteData($core): array
{
    $countries = sl_rows('SELECT DISTINCT c.id, c.name FROM cdb_countries c WHERE c.is_active = 1 ORDER BY c.name');
    $cats = sl_rows('SELECT id, name_item FROM cdb_category ORDER BY id');
    $d = ['countries' => $countries, 'cats' => $cats, 'result' => null, 'error' => '', 'rate' => (float) ($core->exchange_rate ?? 0), 'unit' => $core->weight_p ?? 'kg', 'dimUnit' => $core->units ?? 'cm'];
    $in = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $d['origin'] = (int) ($in['origin'] ?? 0);
    $d['destiny'] = (int) ($in['destiny'] ?? 0);
    $d['weight'] = (float) ($in['weight'] ?? 0);
    $d['cat'] = (int) ($in['cat'] ?? 0);
    $d['l'] = (float) ($in['l'] ?? 0); $d['w'] = (float) ($in['w'] ?? 0); $d['h'] = (float) ($in['h'] ?? 0);
    $d['value'] = (float) ($in['value'] ?? 0);
    $d['asked'] = isset($in['weight']);
    if (!$d['asked']) {
        return $d;
    }
    if ($d['origin'] <= 0 || $d['destiny'] <= 0 || $d['weight'] <= 0) {
        $d['error'] = 'Please choose the origin, the destination and the weight.';
        return $d;
    }
    $fee = function (float $weight, int $cat) use ($d) {
        $sql = 'SELECT id, initial_range, final_range, price, volumetric_percentage, order_item_category FROM cdb_shipping_fees
                WHERE origin = :o AND destiny = :d AND (client_id = 0 OR client_id IS NULL) AND :w BETWEEN initial_range AND final_range'
            . ($cat > 0 ? ' AND (order_item_category = :c OR order_item_category = 0 OR order_item_category IS NULL)' : '')
            . ' ORDER BY ' . ($cat > 0 ? '(order_item_category = :c2) DESC, ' : '') . 'id DESC';
        $b = [':o' => $d['origin'], ':d' => $d['destiny'], ':w' => $weight];
        if ($cat > 0) { $b[':c'] = $cat; $b[':c2'] = $cat; }
        return sl_row($sql, $b);
    };
    $first = $fee($d['weight'], $d['cat']);
    $volPct = $first ? max(1, (int) $first->volumetric_percentage) : 5000;
    $vol = ($d['l'] > 0 && $d['w'] > 0 && $d['h'] > 0) ? round($d['l'] * $d['w'] * $d['h'] / $volPct, 2) : 0;
    $charge = max($d['weight'], $vol);
    $row = $charge !== $d['weight'] ? ($fee($charge, $d['cat']) ?: $first) : $first;
    if (!$row) {
        $d['error'] = 'No tariff is configured for this route and weight yet. Create an account and we will quote you.';
        return $d;
    }
    // cdb_shipping_fees.price is the price per weight unit for the band (same rule as the staff rate editor).
    $freight = round((float) $row->price * $charge, 2);
    $d['result'] = [
        'tariff' => (int) $row->id,
        'perUnit' => sl_money($row->price),
        'freight' => sl_money($freight),
        'total' => sl_money($freight),
        'local' => $d['rate'] > 0 ? 'GHS ' . number_format($freight * $d['rate'], 2) . ' at USD 1 = GHS ' . number_format($d['rate'], 2) : '',
        'charge' => rtrim(rtrim(number_format($charge, 2), '0'), '.'),
        'vol' => rtrim(rtrim(number_format($vol, 2), '0'), '.'),
        'band' => rtrim(rtrim(number_format((float) $row->initial_range, 2), '0'), '.') . '–' . rtrim(rtrim(number_format((float) $row->final_range, 2), '0'), '.'),
        'fill' => sl_pct($charge, max(1, (float) $row->final_range)),
    ];
    // price by weight chart, 1..max(12, weight)
    $max = (int) max(4, min(50, ceil($d['weight'])));
    $vals = [];
    for ($w = 1; $w <= $max; $w++) {
        $r = $fee((float) $w, $d['cat']);
        $vals[] = $r ? round((float) $r->price * $w, 2) : 0;
    }
    $top = max(1, max($vals));
    $d['chart'] = ['values' => $vals, 'weights' => range(1, count($vals)), 'path' => sl_svgLinePath($vals, 1030, 170, 12, $top, 44), 'max' => $max, 'top' => $top, 'labels' => [1, (int) round($max / 3), (int) round($max * 2 / 3), $max]];
    return $d;
}

/* ------------------------------------------------------------------ customer portal */

function sl_portalBase($userData, $core): array
{
    $uid = (int) $_SESSION['userid'];
    $name = trim(($userData->fname ?? '') . ' ' . ($userData->lname ?? ''));
    $finance = cdp_financeEnabled();
    $due = $finance ? sl_row('SELECT COUNT(*) AS n, COALESCE(SUM(total_order),0) AS amt, MIN(due_date) AS due FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_invoice IN (2,23) AND status_courier <> 21', [':u' => $uid]) : null;
    $readyA = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier IN (6,32)', [':u' => $uid]);
    $readyP = (int) sl_scalar('SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u AND status_courier IN (6,32)', [':u' => $uid]);
    return [
        'uid' => $uid,
        'name' => $name ?: (string) ($userData->username ?? ''),
        'first' => (string) ($userData->fname ?: $name),
        'ini' => sl_initials($name ?: 'U'),
        'locker' => (string) ($userData->locker ?? ''),
        'finance' => $finance,
        'dueN' => $due ? (int) $due->n : 0,
        'dueAmt' => $due ? (float) $due->amt : 0.0,
        'dueMoney' => sl_money($due->amt ?? 0),
        'dueDate' => $due && $due->due ? sl_date($due->due, 'M j') : '',
        'ready' => $readyA + $readyP,
        'addresses' => sl_customerAddresses($userData, $core),
        'canShip' => $GLOBALS['user']->cdp_hasPermission('courier_add_client'),
        'canPrealert' => $GLOBALS['user']->cdp_hasPermission('prealert_add') || $GLOBALS['user']->cdp_hasPermission('prealert_list'),
    ];
}

/** Recent shipments + packages of the customer (merged, newest first). */
function sl_portalShipments(int $uid, int $limit = 6, int $offset = 0, string $q = '', string $filter = 'all'): array
{
    $bind = [':u' => $uid, ':u2' => $uid];
    $w1 = $w2 = '';
    if ($q !== '') {
        $w1 = " AND (CONCAT(a.order_prefix,a.order_no) LIKE :q OR a.provider_purchase LIKE :q OR a.tracking_purchase LIKE :q)";
        $w2 = " AND (CONCAT(p.order_prefix,p.order_no) LIKE :q2 OR p.provider_purchase LIKE :q2 OR p.tracking_purchase LIKE :q2)";
        $bind[':q'] = '%' . $q . '%';
        $bind[':q2'] = '%' . $q . '%';
    }
    $w1 .= sl_portalFilterSql($filter, 'a.'); $w2 .= sl_portalFilterSql($filter, 'p.');
    $sql = "SELECT * FROM (
        SELECT 'air' AS src, a.order_id AS id, CONCAT(a.order_prefix,a.order_no) AS trk, a.order_date, a.total_weight, a.total_order, a.status_courier, a.provider_purchase, a.order_item_category, a.status_invoice FROM cdb_add_order a WHERE a.sender_id = :u AND a.is_deleted = 0 $w1
        UNION ALL
        SELECT 'pkg', p.order_id, CONCAT(p.order_prefix,p.order_no), p.order_date, p.total_weight, p.total_order, p.status_courier, p.provider_purchase, p.order_item_category, p.status_invoice FROM cdb_customers_packages p WHERE p.sender_id = :u2 $w2
    ) x ORDER BY order_date DESC, id DESC LIMIT " . (int) $offset . ', ' . (int) $limit;
    $air = cdp_shipCategoryIds('air');
    $out = [];
    foreach (sl_rows($sql, $bind) as $r) {
        $st = sl_customerStatus((int) $r->status_courier);
        $isAir = in_array((int) $r->order_item_category, $air, true);
        $out[] = [
            'id' => $r->trk, 'oid' => (int) $r->id, 'src' => $r->src,
            'href' => $r->src === 'air' ? sl_route('ShipmentDetail', ['id' => (int) $r->id]) : sl_route('PackageDetail', ['id' => (int) $r->id]),
            'from' => (string) $r->provider_purchase ?: '—',
            'mode' => $isAir ? 'Air' : 'Sea',
            'w' => (float) $r->total_weight > 0 ? number_format((float) $r->total_weight, 1) . ' ' . ($GLOBALS['core']->weight_p ?? 'kg') : '—',
            'c' => (float) $r->total_order > 0 ? sl_money($r->total_order) : '—',
            'st' => $st['label'], 'cls' => $st['cls'],
            'pg' => 'width:' . sl_customerProgress((int) $r->status_courier) . '%',
            'date' => sl_date($r->order_date),
        ];
    }
    return $out;
}

function sl_portalCount(int $uid, string $q = '', string $filter = 'all'): int
{
    $b = [':u' => $uid];
    $w1 = $w2 = '';
    if ($q !== '') {
        $w1 = " AND (CONCAT(order_prefix,order_no) LIKE :q OR provider_purchase LIKE :q OR tracking_purchase LIKE :q)";
        $w2 = $w1;
        $b[':q'] = '%' . $q . '%';
    }
    $w1 .= sl_portalFilterSql($filter); $w2 .= sl_portalFilterSql($filter);
    return (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 $w1", $b)
        + (int) sl_scalar("SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u $w2", $b);
}

/** ClientDashboard board. */
function sl_clientDashboardData($user, $core, $userData): array
{
    $d = sl_portalBase($userData, $core);
    $uid = $d['uid'];
    $d['active'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier NOT IN (8,15,21)', [':u' => $uid])
        + (int) sl_scalar('SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u AND status_courier NOT IN (8,15,21)', [':u' => $uid]);
    $d['year'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND YEAR(order_date) = YEAR(CURDATE())', [':u' => $uid])
        + (int) sl_scalar('SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u AND YEAR(order_date) = YEAR(CURDATE())', [':u' => $uid]);
    $d['atWh'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier IN (2,4,10)', [':u' => $uid])
        + (int) sl_scalar('SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u AND status_courier IN (2,4,10)', [':u' => $uid]);
    $billed = $d['finance'] ? (float) sl_scalar('SELECT COALESCE(SUM(total_order),0) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier <> 21', [':u' => $uid]) : 0;
    $d['duePct'] = sl_pct($d['dueAmt'], max(1, $billed));
    $d['rows'] = sl_portalShipments($uid, 6);
    $d['mobileRows'] = sl_portalShipments($uid, 3, 0, '', 'active');
    // latest tracked parcel
    $latest = $d['rows'][0] ?? null;
    $d['latest'] = $latest;
    $d['mobileDelivery'] = sl_tableExists('cdb_delivery_requests') ? sl_row("SELECT r.id,r.state,r.address,u.fname AS rider_name,u.phone AS rider_phone FROM cdb_delivery_requests r LEFT JOIN cdb_users u ON u.id=r.driver_id AND u.active=1 WHERE r.customer_id=:u AND r.state IN ('pending','assigned','onroute','failed') ORDER BY r.id DESC LIMIT 1", [':u'=>$uid]) : null;
    $d['tl'] = [];
    if ($latest) {
        $ev = array_reverse(cdp_getTrackEvents([$latest['id']]));
        foreach (array_slice($ev, 0, 5) as $i => $e) {
            $s = sl_customerStatus((int) $e->status_courier, (string) $e->mod_style);
            $d['tl'][] = ['c' => $i === 0 ? 'now' : 'done', 't' => $s['label'], 'w' => sl_date($e->t_date, 'M j, H:i') . ($e->t_city ? ' · ' . $e->t_city : '')];
        }
        $row = sl_row('SELECT status_courier, order_item_category FROM ' . ($latest['src'] === 'air' ? 'cdb_add_order' : 'cdb_customers_packages') . ' WHERE order_id = :id', [':id' => $latest['oid']]);
        $d['latestSteps'] = sl_trackSteps((int) ($row->status_courier ?? 1), $latest['mode'] === 'Air');
    }
    $d['aiOn'] = function_exists('cdp_aiConfigured') && cdp_aiConfigured();
    return $d;
}

/** UserHome (My account) board. */
function sl_userHomeData($user, $core, $userData): array
{
    $d = sl_portalBase($userData, $core);
    $uid = $d['uid'];
    $q = trim((string) ($_GET['q'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 5;
    $d['q'] = $q;
    $d['page'] = $page;
    $d['per'] = $per;
    $d['count'] = sl_portalCount($uid, $q);
    $d['rows'] = sl_portalShipments($uid, $per, ($page - 1) * $per, $q);
    $u = sl_row('SELECT u.*, (SELECT COUNT(*) FROM cdb_recipients r WHERE r.sender_id = u.id) AS recips FROM cdb_users u WHERE u.id = :u', [':u' => $uid]);
    $d['email'] = (string) ($u->email ?? '');
    $d['phone'] = (string) ($u->phone ?? '');
    $d['created'] = sl_date($u->created ?? '', 'j M Y');
    $d['lastLogin'] = $u && $u->lastlogin ? sl_ago($u->lastlogin) : '—';
    $d['recips'] = (int) ($u->recips ?? 0);
    $d['avatar'] = !empty($u->avatar) && strpos((string) $u->avatar, 'blank') === false ? cdp_avatarUrl($u->avatar) : '';
    $addr = sl_row('SELECT sa.address, sa.zip_code, c.name AS city, s.name AS state FROM cdb_senders_addresses sa LEFT JOIN cdb_cities c ON c.id = sa.city LEFT JOIN cdb_states s ON s.id = sa.state WHERE sa.user_id = :u ORDER BY sa.id_addresses', [':u' => $uid]);
    $d['address'] = $addr ? trim((string) $addr->address) : '';
    $d['addressCity'] = $addr ? trim(implode(', ', array_filter([(string) $addr->city, (string) $addr->state]))) : '';
    $hasDoc = !empty($u->document_number);
    $hasPhone = !empty($u->phone);
    $hasAddr = (bool) $addr;
    $hasPhoto = $d['avatar'] !== '';
    $hasIdPhoto = !empty($u->document_photo);
    $d['complete'] = (int) round((($hasDoc ? 1 : 0) + ($hasPhone ? 1 : 0) + ($hasAddr ? 1 : 0) + ($hasPhoto ? 1 : 0) + ($hasIdPhoto ? 1 : 0) + 1) / 6 * 100);
    $d['idVerified'] = $hasIdPhoto || (int) ($u->approve ?? 0) === 1;
    $d['phoneMasked'] = sl_maskPhone($d['phone']);
    $last = sl_scalar('SELECT MAX(order_date) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0', [':u' => $uid], '');
    $d['lastShip'] = $last ? sl_date($last, 'M j') : '—';
    $notif = sl_tableExists('cdb_user_notification_prefs') ? sl_rows("SELECT channel, enabled FROM cdb_user_notification_prefs WHERE user_id = :u AND event_key = '*'", [':u' => $uid]) : [];
    $off = array_map(fn($r) => $r->channel, array_filter($notif, fn($r) => !(int) $r->enabled));
    $chs = array_diff(['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'E-mail'], []);
    $on = [];
    foreach (['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'E-mail'] as $k => $l) {
        if (!in_array($k, $off, true)) $on[] = $l;
    }
    $d['alerts'] = $on ? implode(', ', $on) : 'All off';
    // KPIs
    $d['total'] = $d['count'];
    $d['month'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')", [':u' => $uid])
        + (int) sl_scalar("SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u AND order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')", [':u' => $uid]);
    $d['prevMonth'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND order_date >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') AND order_date < DATE_FORMAT(CURDATE(), '%Y-%m-01')", [':u' => $uid]);
    $d['monthKg'] = number_format((float) sl_scalar("SELECT COALESCE(SUM(total_weight),0) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')", [':u' => $uid]), 1);
    $d['totalKg'] = number_format((float) sl_scalar('SELECT COALESCE(SUM(total_weight),0) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0', [':u' => $uid])
        + (float) sl_scalar('SELECT COALESCE(SUM(total_weight),0) FROM cdb_customers_packages WHERE sender_id = :u', [':u' => $uid]), 1);
    $d['atWh'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier IN (2,4,10)', [':u' => $uid])
        + (int) sl_scalar('SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u AND status_courier IN (2,4,10)', [':u' => $uid]);
    $d['invoiced'] = $d['dueN'];
    $d['delivered'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier IN (8,15)', [':u' => $uid])
        + (int) sl_scalar('SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u AND status_courier IN (8,15)', [':u' => $uid]);
    $d['lastDelivered'] = sl_date(sl_scalar('SELECT MAX(order_date) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier IN (8,15)', [':u' => $uid], ''), 'M j');
    $d['billed'] = $d['finance'] ? sl_money(sl_scalar('SELECT COALESCE(SUM(total_order),0) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier <> 21', [':u' => $uid])) : '—';
    $d['paid'] = $d['finance'] ? sl_money(sl_scalar('SELECT COALESCE(SUM(total_order),0) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_invoice IN (1,19)', [':u' => $uid])) : '—';
    $d['preShip'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_pre_alert WHERE customer_id = :u AND is_package = 0', [':u' => $uid]);
    $d['prePkg'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_pre_alert WHERE customer_id = :u AND is_package = 1', [':u' => $uid]);
    $d['pickups'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND is_pickup = 1', [':u' => $uid]);
    // heatmap: 52 weeks × 7 days
    $daily = [];
    foreach (sl_rows("SELECT DATE(order_date) AS d, COUNT(*) AS n FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND order_date >= CURDATE() - INTERVAL 364 DAY GROUP BY DATE(order_date)", [':u' => $uid]) as $r) {
        $daily[$r->d] = (int) $r->n;
    }
    $peak = $daily ? max($daily) : 0;
    $peakDay = $peak ? array_search($peak, $daily, true) : '';
    $start = strtotime('-363 days');
    $start -= (int) date('w', $start) * 86400;
    $heat = [];
    for ($i = 0; $i < 364; $i++) {
        // grid fills column by column (week), 7 rows
        $day = date('Y-m-d', $start + $i * 86400);
        $heat[] = ['c' => sl_heatLevel($daily[$day] ?? 0, $peak), 't' => $day . ': ' . ($daily[$day] ?? 0) . ' shipments'];
    }
    // the CSS grid places items row by row: reorder to day-major
    $grid = [];
    for ($r = 0; $r < 7; $r++) {
        for ($w = 0; $w < 52; $w++) {
            $grid[] = $heat[$w * 7 + $r] ?? ['c' => ''];
        }
    }
    $d['heat'] = $grid;
    $d['peak'] = $peak ? $peak . ' a day (' . sl_date($peakDay, 'M j') . ')' : 'no shipments yet';
    $months = [];
    for ($i = 11; $i >= 0; $i--) {
        $months[] = date('M', strtotime("first day of -$i months"));
    }
    $d['months'] = $months;
    // activity
    $act = [];
    foreach (sl_rows('SELECT t.order_track, t.t_date, t.status_courier, s.mod_style, t.t_city FROM cdb_courier_track t JOIN cdb_styles s ON s.id = t.status_courier
            WHERE t.order_track IN (SELECT CONCAT(order_prefix,order_no) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0)
            ORDER BY t.t_date DESC LIMIT 6', [':u' => $uid]) as $e) {
        $s = sl_customerStatus((int) $e->status_courier, (string) $e->mod_style);
        $act[] = ['icon' => 'i-route', 'trk' => $e->order_track, 'st' => $s['label'], 'cls' => $s['cls'], 'at' => sl_date($e->t_date, 'M j, H:i'), 'sub' => (string) $e->t_city];
    }
    foreach (sl_rows('SELECT tracking, provider_shop, purchase_price, estimated_date, prealert_date FROM cdb_pre_alert WHERE customer_id = :u ORDER BY prealert_date DESC LIMIT 2', [':u' => $uid]) as $p) {
        $act[] = ['icon' => 'i-inbox', 'pre' => true, 'trk' => $p->tracking, 'shop' => (string) $p->provider_shop, 'price' => sl_money($p->purchase_price), 'eta' => sl_date($p->estimated_date, 'M j'), 'at' => sl_date($p->prealert_date, 'M j, H:i')];
    }
    $d['activity'] = $act;
    return $d;
}

/* ------------------------------------------------------------------ bills */

/**
 * Bills for MyBills / PayCheckout. Two sources:
 *   - consolidated bills (cdb_consolidate_customer_billing, the legacy my_bills flow; Paystack checkout via ajax/customer/my_bills_ajax.php)
 *   - unpaid single shipments (cdb_add_order.status_invoice 2/23)
 */
function sl_billsData($user, $core, $userData): array
{
    $d = sl_portalBase($userData, $core);
    $uid = $d['uid'];
    $filter = (string) ($_GET['filter'] ?? 'owing');
    $d['filter'] = in_array($filter, ['all', 'owing', 'settled'], true) ? $filter : 'owing';
    $d['canPay'] = $user->cdp_hasPermission('pay_my_bills');
    $d['consolidated'] = sl_tableExists('cdb_consolidate_customer_billing');
    $d['gateway'] = false;
    if (is_file(CDP_ROOT . '/helpers/fs_payments.php')) {
        require_once CDP_ROOT . '/helpers/fs_payments.php';
        $d['gateway'] = function_exists('cdp_fsGatewayConfigured') && cdp_fsGatewayConfigured('paystack');
    }
    $bills = [];
    if ($d['consolidated']) {
        $moneyExpr = function_exists('cdp_fsMoneyExpr') ? cdp_fsMoneyExpr('p') : 'p.amount_ghs';
        $moneyFilter = function_exists('cdp_fsMoneySqlFilter') ? cdp_fsMoneySqlFilter('p') : '1=1';
        $hasPay = sl_tableExists('cdb_fs_payments');
        $hasDisc = sl_tableExists('cdb_fs_discounts');
        foreach (sl_rows("SELECT b.consolidate_id, b.amount_ghs, b.billed_at, c.c_prefix, c.c_no,
                " . ($hasPay ? "COALESCE((SELECT SUM($moneyExpr) FROM cdb_fs_payments p WHERE p.consolidate_id = b.consolidate_id AND p.sender_id = b.sender_id AND $moneyFilter),0)" : '0') . " AS paid,
                " . ($hasDisc ? 'COALESCE((SELECT SUM(x.amount_ghs) FROM cdb_fs_discounts x WHERE x.consolidate_id = b.consolidate_id AND x.sender_id = b.sender_id),0)' : '0') . " AS disc
            FROM cdb_consolidate_customer_billing b LEFT JOIN cdb_consolidate c ON c.consolidate_id = b.consolidate_id WHERE b.sender_id = :u ORDER BY b.billed_at DESC", [':u' => $uid]) as $r) {
            $net = max(0, round((float) $r->amount_ghs - (float) $r->disc, 2));
            $bal = max(0, round($net - (float) $r->paid, 2));
            $bills[] = ['kind' => 'consol', 'cid' => (int) $r->consolidate_id, 'no' => trim((string) $r->c_prefix . (string) $r->c_no) ?: 'Bill ' . (int) $r->consolidate_id,
                'sub' => sl_date($r->billed_at) . ' · consolidated shipment', 'amount' => $net, 'balance' => $bal, 'settled' => $bal <= 0.005, 'date' => (string) $r->billed_at, 'ghs' => true];
        }
    }
    foreach (sl_rows("SELECT order_id, order_prefix, order_no, order_date, due_date, total_order, total_weight, status_invoice, provider_purchase, payment_date FROM cdb_add_order
            WHERE sender_id = :u AND is_deleted = 0 AND status_courier <> 21 AND total_order > 0 AND status_invoice IN (1,2,19,23) ORDER BY order_date DESC LIMIT 200", [':u' => $uid]) as $r) {
        $paid = in_array((int) $r->status_invoice, [1, 19], true);
        $bills[] = ['kind' => 'order', 'oid' => (int) $r->order_id, 'no' => $r->order_prefix . $r->order_no,
            'sub' => sl_date($r->order_date) . ($r->provider_purchase ? ' · ' . $r->provider_purchase : '') . ((float) $r->total_weight > 0 ? ' · ' . number_format((float) $r->total_weight, 1) . ' ' . ($core->weight_p ?? 'kg') : ''),
            'amount' => (float) $r->total_order, 'balance' => $paid ? 0 : (float) $r->total_order, 'settled' => $paid,
            'due' => !$paid && $r->due_date ? sl_date($r->due_date, 'M j') : '', 'date' => (string) $r->order_date, 'ghs' => false];
    }
    $fmt = fn($b, $v) => $b['ghs'] ? 'GHS ' . number_format($v, 2) : sl_money($v);
    $owing = 0.0; $owingN = 0; $paidY = 0.0; $billedY = 0.0; $settledN = 0;
    foreach ($bills as &$b) {
        $b['money'] = $fmt($b, $b['amount']);
        $b['balMoney'] = $fmt($b, $b['balance']);
        if (!$b['settled']) { $owing += $b['ghs'] ? 0 : $b['balance']; $owingN++; }
        if (substr($b['date'], 0, 4) === date('Y') && !$b['ghs']) { $billedY += $b['amount']; $paidY += $b['amount'] - $b['balance']; if ($b['settled']) $settledN++; }
    }
    unset($b);
    $d['owingN'] = $owingN;
    $d['owing'] = sl_money($owing);
    $d['owingGhs'] = array_sum(array_map(fn($b) => $b['ghs'] && !$b['settled'] ? $b['balance'] : 0, $bills));
    $d['paidY'] = sl_money($paidY);
    $d['billedY'] = sl_money($billedY);
    $d['paidPct'] = sl_pct($paidY, max(1, $billedY));
    $d['settledN'] = $settledN;
    $d['bills'] = array_values(array_filter($bills, fn($b) => $d['filter'] === 'all' || ($d['filter'] === 'owing' ? !$b['settled'] : $b['settled'])));
    // monthly spend 12 months
    $map = [];
    foreach (sl_rows("SELECT DATE_FORMAT(order_date,'%Y-%m') ym, SUM(total_order) s FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier <> 21 AND order_date >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01') GROUP BY ym", [':u' => $uid]) as $r) {
        $map[$r->ym] = (float) $r->s;
    }
    $vals = []; $labels = [];
    for ($i = 11; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("first day of -$i months"));
        $vals[] = $map[$ym] ?? 0;
        $labels[] = date('M', strtotime($ym . '-01'));
    }
    $top = max(1, max($vals));
    $bars = [];
    foreach ($vals as $i => $v) {
        $h = round($v / $top * 146, 1);
        $bars[] = ['x' => round(72 + $i * 82.2, 1), 'y' => round(170 - $h, 1), 'h' => $h, 'l' => $labels[$i], 'v' => sl_money($v), 'now' => $i === 11];
    }
    $d['bars'] = $bars;
    $d['top'] = sl_money($top);
    $d['mid'] = sl_money($top / 2);
    $d['avg'] = sl_money(array_sum($vals) / 12);
    return $d;
}

/* ------------------------------------------------------------------ notifications */

function sl_notifPrefsData($user, $core, $userData): array
{
    $uid = (int) $_SESSION['userid'];
    $events = sl_notificationEvents();
    $has = sl_tableExists('cdb_user_notification_prefs');
    $stored = [];
    if ($has) {
        foreach (sl_rows('SELECT channel, event_key, enabled FROM cdb_user_notification_prefs WHERE user_id = :u', [':u' => $uid]) as $r) {
            $stored[$r->channel][$r->event_key] = (int) $r->enabled;
        }
    }
    $prefs = sl_prefs($uid);
    $paused = ($prefs['notif_paused'] ?? '0') === '1';
    $chMeta = [
        'whatsapp' => ['WhatsApp', 'i-message', (string) ($userData->phone ?? ''), 2],
        'sms' => ['SMS', 'i-phone', (string) ($userData->phone ?? ''), 3],
        'email' => ['E-mail', 'i-mail', (string) ($userData->email ?? ''), 4],
    ];
    $chans = [];
    foreach ($chMeta as $k => $m) {
        $on = ($stored[$k]['*'] ?? 1) === 1;
        $chans[] = ['key' => $k, 'label' => $m[0], 'icon' => $m[1], 'addr' => $m[2] !== '' ? $m[2] : 'Not on file', 'ver' => $m[2] === '' ? 'Add it in your profile' : ($on ? '✓ On' : 'Off'), 'cls' => $on ? 'on' : '', 'aria' => $on ? 'true' : 'false', 'off' => $on ? '' : 'off'];
    }
    $evs = [];
    $smsN = 0;
    foreach ($events as $key => $e) {
        $row = ['key' => $key, 'n' => $e[0], 'd' => $e[1]];
        foreach (['whatsapp' => [2, 'wa'], 'sms' => [3, 'sms'], 'email' => [4, 'em']] as $ch => [$idx, $short]) {
            $on = ($stored[$ch][$key] ?? $e[$idx]) === 1;
            if ($ch === 'sms' && $on) $smsN++;
            $row[$short] = $on ? 'on' : '';
            $row[$short . 'A'] = $on ? 'true' : 'false';
        }
        $evs[] = $row;
    }
    $recent = [];
    if (sl_tableExists('cdb_message_log')) {
        foreach (sl_rows('SELECT channel, subject, body, created_at FROM cdb_message_log WHERE recipient_user_id = :u ORDER BY created_at DESC LIMIT 4', [':u' => $uid]) as $r) {
            $icon = ['whatsapp' => 'i-message', 'sms' => 'i-phone', 'email' => 'i-mail'][strtolower((string) $r->channel)] ?? 'i-bell';
            $recent[] = ['icon' => $icon, 't' => mb_strimwidth(trim((string) ($r->subject ?: strip_tags((string) $r->body))), 0, 70, '…'), 'w' => sl_ago($r->created_at)];
        }
    }
    if (!$recent) {
        foreach (sl_rows('SELECT n.notification_description, n.notification_date FROM cdb_notifications_users nu JOIN cdb_notifications n ON n.notification_id = nu.notification_id WHERE nu.user_id = :u ORDER BY n.notification_date DESC LIMIT 4', [':u' => $uid]) as $r) {
            $recent[] = ['icon' => 'i-bell', 't' => mb_strimwidth(strip_tags((string) $r->notification_description), 0, 70, '…'), 'w' => sl_ago($r->notification_date)];
        }
    }
    $mk = function (string $prefKey, array $opts, string $def) use ($prefs) {
        $cur = $prefs[$prefKey] ?? $def;
        return array_map(fn($o) => ['v' => $o[0], 'label' => $o[1], 'cls' => $cur === $o[0] ? 'on' : ''], $opts);
    };
    return [
        'ready' => $has,
        'chans' => $chans,
        'evs' => $evs,
        'costNote' => $smsN . ' SMS updates per parcel · free for you',
        'quiet' => ($prefs['notif_quiet'] ?? '1') === '1',
        'quietFrom' => $prefs['notif_quiet_from'] ?? '21:00',
        'quietTo' => $prefs['notif_quiet_to'] ?? '07:00',
        'langs' => $mk('notif_lang', [['en', 'English'], ['tw', 'Twi'], ['fr', 'French']], 'en'),
        'digs' => $mk('notif_digest', [['week', 'Weekly'], ['month', 'Monthly'], ['off', 'Off']], 'week'),
        'promo' => ($prefs['notif_promo'] ?? '1') === '1',
        'news' => (string) ($userData->newsletter ?? '0') === '1',
        'paused' => $paused,
        'pauseLabel' => $paused ? 'Resume all messages' : 'Pause all messages',
        'recent' => $recent,
        'canTest' => !empty($userData->email),
    ];
}

/* ------------------------------------------------------------------ profile studio */

function sl_profileStudioData($user, $core, $userData): array
{
    $uid = (int) $_SESSION['userid'];
    $u = sl_row('SELECT id, fname, lname, email, phone, avatar, locker, userlevel FROM cdb_users WHERE id = :id', [':id' => $uid]);
    $name = trim(($u->fname ?? '') . ' ' . ($u->lname ?? ''));
    $prefs = sl_prefs($uid);
    $icon = $prefs['avatar_style'] ?? 'init';
    $ini = sl_initials($name ?: 'U');
    $avatar = !empty($u->avatar) && strpos((string) $u->avatar, 'blank') === false ? cdp_avatarUrl($u->avatar) : '';
    $fix = function (string $n): string {
        $n = preg_replace('/\s+/', ' ', trim($n));
        return mb_convert_case(mb_strtolower($n), MB_CASE_TITLE, 'UTF-8');
    };
    $icons = [
        ['init', $ini, 'Initials', 'background:var(--ink);color:var(--inverse)'],
        ['ring', $ini, 'Initials with ring', 'background:var(--active);box-shadow:inset 0 0 0 3px var(--ink)'],
    ];
    if ($avatar) {
        $icons[] = ['photo', '', 'From photo', 'background-image:url(' . $avatar . ');background-size:cover;background-position:center'];
    }
    $iconsOut = [];
    $cur = $icons[0];
    foreach ($icons as $i) {
        if ($i[0] === $icon) $cur = $i;
        $iconsOut[] = ['key' => $i[0], 'txt' => $i[1], 'label' => $i[2], 'sty' => $i[3], 'cls' => $i[0] === $icon ? 'on' : ''];
    }
    require_once CDP_ROOT . '/helpers/ai_assistant.php';
    $aiOn = function_exists('cdp_aiConfigured') && cdp_aiConfigured();
    return [
        'uid' => $uid,
        'fname' => (string) ($u->fname ?? ''),
        'lname' => (string) ($u->lname ?? ''),
        'name' => $name,
        'phone' => (string) ($u->phone ?? ''),
        'email' => (string) ($u->email ?? ''),
        'suggest' => $fix($name),
        'labelName' => mb_strtoupper($fix($name)),
        'avatar' => $avatar,
        'ini' => $ini,
        'icons' => $iconsOut,
        'iconLabel' => $cur[2],
        'curIconSty' => $cur[3],
        'curIconTxt' => $cur[1],
        'sub' => sl_userIs('client') ? 'Locker ' . ($u->locker ?? '') : (string) sl_scalar('SELECT role_name FROM cdb_user_roles WHERE role_id = :r', [':r' => (int) $u->userlevel], ''),
        'aiOn' => $aiOn,
        'isClient' => sl_userIs('client'),
        'canEditPhone' => !sl_userIs('client'),
        'avatarEndpoint' => sl_userIs('client') ? 'ajax/customers/customers_avatar_edit_ajax.php' : 'ajax/users/users_avatar_edit_ajax.php',
        'isAdmin' => sl_userIs('admin') || sl_userIs('superadmin'),
    ];
}

/** Server-selected status filters; caller input never enters SQL. */
function sl_portalFilterSql(string $filter,string $alias=''): string
{
    if($filter==='active')return " AND {$alias}status_courier NOT IN (8,15,21,12,16)";
    if($filter==='delivered')return " AND {$alias}status_courier IN (8,15)";
    if($filter==='ready'){
        require_once CDP_ROOT.'/helpers/delivery_service.php';$config=dl_config();$ids=[];
        foreach(sl_rows('SELECT id,mod_style FROM cdb_styles') as $status)if(dl_ready((object)['status_courier'=>$status->id,'status_name'=>$status->mod_style],$config))$ids[]=(int)$status->id;
        return " AND {$alias}status_courier IN (".implode(',',$ids?:[6,32]).")";
    }
    return '';
}
