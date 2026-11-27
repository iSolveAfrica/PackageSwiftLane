<?php
/**
 * Data for the customer app screens that have their own page: my_account.php, notifications.php,
 * my_rewards.php, delivery_pricing.php, delivery_done.php. Every query is scoped to the owner id the
 * controller passes from the session.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/finance_switch.php';
require_once CDP_ROOT . '/helpers/loyalty.php';

/** Account hub (M-CAccount): identity, warehouse addresses, and the rows a customer is allowed to open. */
function sl_caAccountData($user, $core, $userData): array
{
    require_once CDP_ROOT . '/helpers/sl_auth.php';
    $uid = (int) $_SESSION['userid'];
    $name = trim(($userData->fname ?? '') . ' ' . ($userData->lname ?? '')) ?: (string) ($userData->username ?? '');
    $loyaltyOn = sl_loyaltyEnabled() && sl_loyaltySettings()['loyalty_enabled'] === '1';
    $acc = $loyaltyOn ? sl_loyaltyAccount($uid, false) : null;
    $active = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier NOT IN (8,15,21)', [':u' => $uid])
        + (int) sl_scalar('SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u AND status_courier NOT IN (8,15,21)', [':u' => $uid]);
    $unread = (int) sl_scalar('SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0', [':u' => $uid]);
    $can = fn($perm) => $user->cdp_hasPermission($perm);

    // Same conditions as the customer menu (helpers/sl_nav.php sl_navCustomer) and the tab bar (helpers/sl_mobile.php).
    $shipping = [['grid', 'Dashboard', sl_route('Main'), 0]];
    if ($can('courier_add_client')) {
        $shipping[] = ['plus', 'Ship a parcel', 'courier_add_client.php', 0];
    }
    $shipping[] = ['box', 'All my parcels', sl_route('MyParcels'), $active];
    if ($can('view_shipment_list')) {
        $shipping[] = ['plane', 'My shipments', sl_route('AirShipments'), (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier NOT IN (8,15,21)', [':u' => $uid])];
    }
    if ($can('view_package_list')) {
        $shipping[] = ['box', 'My packages', sl_route('SeaPackages'), (int) sl_scalar('SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u AND status_courier NOT IN (8,15,21)', [':u' => $uid])];
    }
    if ($can('prealert_list')) {
        $shipping[] = ['inbox', 'Pre-alerts', sl_route('PreAlerts'), (int) sl_scalar('SELECT COUNT(*) FROM cdb_pre_alert WHERE customer_id = :u AND is_package = 0', [':u' => $uid])];
    }
    $shipping[] = ['truck', 'Deliver to my door', sl_route('DeliveryRequest'), 0];
    $shipping[] = ['truck', 'My deliveries', sl_route('DeliveryDone'), sl_tableExists('cdb_delivery_requests') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_delivery_requests WHERE customer_id = :u AND state IN ('pending','assigned','onroute','failed')", [':u' => $uid]) : 0];
    $shipping[] = ['tag', 'Delivery prices', sl_route('DeliveryPricing'), 0];
    $shipping[] = ['key', 'Pickup codes', sl_route('PickupCodes'), 0];
    $shipping[] = ['route', 'Track my parcels', sl_route('LiveTracking'), 0];
    $shipping[] = ['search', 'Track a parcel', sl_route('PublicTracking'), 0];
    if ($can(['view_pickup_list', 'pickup_add'])) {
        $shipping[] = ['truck', 'My pickups', sl_route('Pickups'), 0];
    }
    $shipping[] = ['pin', 'My shipping addresses', sl_route('UserHome') . '#addresses', 0];

    $account = [];
    if ($can('view_recipients')) {
        $account[] = ['user', 'Recipients', sl_route('RecipientsMine'), (int) sl_scalar('SELECT COUNT(*) FROM cdb_recipients WHERE sender_id = :u', [':u' => $uid])];
    }
    if ($can('view_payment_shipment_list')) {
        $account[] = ['card', 'Payments', sl_route('Payments'), 0];
    }
    if (cdp_financeEnabled() && $can('view_my_bills')) {
        $due = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_invoice IN (2,23) AND status_courier <> 21', [':u' => $uid]);
        $account[] = ['receipt', 'My bills', sl_route('MyBills'), $due];
    }
    $account[] = ['sliders', 'Profile', sl_route('UserHome'), 0];
    $account[] = ['lock', 'Security and ID', sl_route('AccountSecurity'), 0];
    $account[] = ['bell', 'Notifications', sl_route('MyNotifications'), $unread];
    $account[] = ['sliders', 'Notification settings', sl_route('CustomerNotifications'), 0];
    $account[] = ['tag', 'Get a quote', sl_route('QuoteCalculator'), 0];
    if ($loyaltyOn) {
        $account[] = ['star', 'Rewards and referrals', sl_route('MyRewards'), 0];
    }
    $account[] = ['help', 'Help and support', 'support.php', 0];

    $addresses = [];
    foreach (sl_customerAddresses($userData, $core) as $a) {
        $lines = array_values(array_filter([$a['recipient'], $a['address1'], $a['address2'], trim($a['city'] . ' ' . $a['zip']), $a['phone']], fn($v) => trim((string) $v) !== ''));
        $addresses[] = ['label' => $a['label'], 'line' => implode(' · ', array_filter([trim($a['address1'], " ·\u{00B7}"), trim($a['address2'])], fn($v) => $v !== '')), 'copy' => implode("\n", $lines)];
    }
    $avatar = !empty($userData->avatar) && strpos((string) $userData->avatar, 'blank') === false && function_exists('cdp_avatarUrl') ? cdp_avatarUrl($userData->avatar) : '';
    return [
        'name' => $name, 'ini' => sl_initials($name ?: 'U'), 'avatar' => $avatar,
        'locker' => (string) ($userData->locker ?? ''),
        'email' => (string) ($userData->email ?? ''), 'phone' => (string) ($userData->phone ?? ''),
        'since' => !empty($userData->created) ? sl_date($userData->created, 'F Y') : '',
        'tier' => $acc ? sl_loyaltyTierLabel($acc->tier) : '', 'points' => $acc ? (int) $acc->points_balance : null,
        'active' => $active, 'unread' => $unread, 'canPickup' => $can('pickup_add'),
        'idVerified' => (int) ($userData->approve ?? 0) === 1,
        'addresses' => $addresses, 'shipping' => $shipping, 'account' => $account,
    ];
}

/** The customer's own notification feed (M-CNotifs): cdb_notifications_users joined to cdb_notifications. */
function sl_caNotificationsData(int $uid): array
{
    $filter = (string) ($_GET['filter'] ?? 'all');
    if (!in_array($filter, ['all', 'unread', 'shipments'], true)) {
        $filter = 'all';
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 20;
    $where = 'a.user_id = :u' . ($filter === 'unread' ? ' AND a.notification_read = 0' : ($filter === 'shipments' ? ' AND b.order_id > 0 AND b.shipping_type <> 99' : ''));
    $base = 'FROM cdb_notifications_users a INNER JOIN cdb_notifications b ON a.notification_id = b.notification_id WHERE ' . $where;
    $count = (int) sl_scalar("SELECT COUNT(*) $base", [':u' => $uid]);
    $unread = (int) sl_scalar('SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0', [':u' => $uid]);
    $rows = sl_rows("SELECT a.notification_read, b.notification_id, b.notification_description, b.notification_date, b.order_id, b.shipping_type $base ORDER BY b.notification_id DESC LIMIT " . (($page - 1) * $per) . ", $per", [':u' => $uid]);
    $items = [];
    foreach ($rows as $r) {
        $type = (int) $r->shipping_type;
        $oid = (int) $r->order_id;
        $href = '';
        $sub = 'Account';
        $icon = 'bell';
        if ($oid > 0) {
            // The detail pages check ownership themselves; the feed only builds the link.
            if ($type === 99) {
                $href = sl_route('DeliveryDone', ['id' => $oid]); $sub = 'Door delivery #' . $oid; $icon = 'truck';
            } elseif ($type === 4) {
                $href = sl_route('PackageDetail', ['id' => $oid]); $sub = 'Sea package'; $icon = 'ship';
            } else {
                $href = sl_route('ShipmentDetail', ['id' => $oid]); $sub = 'Air shipment'; $icon = 'plane';
            }
        }
        $items[] = ['id' => (int) $r->notification_id, 'icon' => $icon, 'title' => (string) $r->notification_description, 'sub' => $sub,
            'when' => sl_ago($r->notification_date), 'read' => (int) $r->notification_read === 1, 'href' => $href];
    }
    return ['items' => $items, 'count' => $count, 'unread' => $unread, 'filter' => $filter, 'page' => $page, 'per' => $per];
}

/** Loyalty card (M-CLoyalty): points, tier, progress, referral code, rewards catalogue and history. */
function sl_caRewardsData(int $uid): array
{
    global $core;
    $s = sl_loyaltySettings();
    $d = ['on' => false, 'settings' => $s];
    if (!sl_loyaltyEnabled() || $s['loyalty_enabled'] !== '1') {
        return $d;
    }
    $l = sl_loyaltyForCustomer($uid);
    if (empty($l['enabled'])) {
        return $d;
    }
    $d = $l + $d;
    $d['on'] = true;
    $d['toNext'] = $l['next_tier'] ? max(0, $l['next_tier'][1] - $l['lifetime']) : 0;
    $d['pct'] = $l['next_tier'] ? min(100, sl_pct($l['lifetime'], $l['next_tier'][1])) : 100;
    $d['step'] = max(1, (int) $s['loyalty_redeem_points']);
    $d['stepValue'] = sl_money((float) $s['loyalty_redeem_value']);
    $d['unit'] = sl_money(1);
    $d['rateAir'] = (float) $s['loyalty_points_air'];
    $d['rateSea'] = (float) $s['loyalty_points_sea'];
    $d['referralOn'] = $s['referral_enabled'] === '1' && sl_tableExists('cdb_referrals');
    $d['referrerPoints'] = (int) $s['referral_referrer_points'];
    $d['friendReward'] = trim((string) $s['referral_friend_reward']);
    $d['rewarded'] = $d['referralOn'] ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_referrals WHERE referrer_id = :u AND status = 'rewarded'", [':u' => $uid]) : 0;
    $d['referralPoints'] = (int) sl_scalar("SELECT COALESCE(SUM(points),0) FROM cdb_loyalty_events WHERE user_id = :u AND kind = 'referral'", [':u' => $uid]);
    $d['site'] = (string) ($core->site_name ?? 'Swift Lane');
    $d['rewards'] = sl_tableExists('cdb_rewards')
        ? sl_rows('SELECT title, kind, points_cost FROM cdb_rewards WHERE active = 1 AND points_cost > 0 AND (starts_at IS NULL OR starts_at <= CURDATE()) AND (ends_at IS NULL OR ends_at >= CURDATE()) AND (max_uses = 0 OR used_count < max_uses) ORDER BY points_cost LIMIT 12')
        : [];
    return $d;
}

/** Door delivery switched on and installed? The honest "not available yet" state hangs on this. */
function sl_caDeliveryBase(): array
{
    require_once CDP_ROOT . '/views/sl/data/delivery.php';
    $d = dl_baseData();
    $d['available'] = $d['ready'] && !empty($d['config']['enabled']);
    return $d;
}

/** What a pricing model charges, in the words of the configuration. */
function sl_caModeLabel(string $mode): string
{
    return ['zone' => 'By zone', 'kilometre' => 'By distance', 'package' => 'By parcel'][$mode] ?? ucfirst($mode);
}

/** Delivery pricing (M-CDelivPrice): the configured models, zones and an estimate from the customer's last delivery address. */
function sl_caDeliveryPricingData(int $uid): array
{
    $d = sl_caDeliveryBase() + ['zones' => [], 'modes' => [], 'estimate' => null, 'last' => null, 'ready_parcels' => 0];
    if (!$d['available']) {
        return $d;
    }
    $c = $d['config'];
    $s = new DeliveryService();
    $modes = array_values(array_intersect(['zone', 'kilometre', 'package'], (array) $c['modes']));
    $d['mode'] = in_array($c['default_mode'], $modes, true) ? $c['default_mode'] : ($modes[0] ?? 'zone');
    $d['modes'] = $modes;
    $d['currency'] = (string) $c['currency'];
    // Zone rules are internal (polygons, riders); the customer sees the name, the places and the fee.
    foreach ($s->zones() as $z) {
        $d['zones'][] = ['id' => (int) $z->id, 'name' => (string) $z->name, 'places' => trim((string) $z->city_match), 'radius' => (float) $z->radius_km, 'cents' => (int) round((float) $z->fee * 100)];
    }
    usort($d['zones'], fn($a, $b) => $a['cents'] <=> $b['cents']);
    // The only address with a road distance on file is the one of an earlier request: re-price it at today's rates.
    $last = sl_row('SELECT address, distance_m, zone_id FROM cdb_delivery_requests WHERE customer_id = :u ORDER BY id DESC', [':u' => $uid]);
    if ($last) {
        $zoneCents = null;
        $zoneName = '';
        foreach ($d['zones'] as $z) {
            if ($z['id'] === (int) $last->zone_id) { $zoneCents = $z['cents']; $zoneName = $z['name']; }
        }
        $d['last'] = ['address' => (string) $last->address, 'km' => (int) $last->distance_m / 1000, 'zone' => $zoneName, 'zone_id' => (int) $last->zone_id];
        try {
            $p = dl_price($d['mode'], $c, (int) $last->distance_m, 1, $zoneCents);
            $d['estimate'] = ['cents' => $p['total_cents'], 'mode' => $d['mode']];
        } catch (DomainException $e) {
            $d['estimate'] = null;
        }
    }
    return $d;
}

/** One delivery request of this customer (M-CDelivDone), or the list of their requests when no id is given. */
function sl_caDeliveryDoneData(int $uid, int $id): array
{
    $d = sl_caDeliveryBase() + ['request' => null, 'events' => [], 'items' => [], 'rider' => null, 'requests' => [], 'notFound' => false];
    if (!$d['ready']) {
        return $d;
    }
    $s = new DeliveryService();
    if ($id > 0) {
        try {
            $d['request'] = $s->get($id, $uid, 'customer');
        } catch (DomainException $e) {
            $d['notFound'] = true;
        }
    }
    $r = $d['request'];
    if (!$r) {
        $d['requests'] = $s->sql("SELECT id, address, state, currency, amount_cents, created_at FROM cdb_delivery_requests WHERE customer_id = ? AND state <> 'quoted' ORDER BY id DESC LIMIT 50", [$uid])->fetchAll(PDO::FETCH_OBJ);
        return $d;
    }
    $d['events'] = $s->sql('SELECT state, note, created_at FROM cdb_delivery_events WHERE request_id = ? ORDER BY id', [$r->id])->fetchAll(PDO::FETCH_OBJ);
    $ids = array_map('intval', json_decode((string) $r->parcel_json, true) ?: []);
    $table = $r->module === 'air' ? 'cdb_add_order' : 'cdb_customers_packages';
    if ($ids) {
        $rows = $s->sql("SELECT o.order_id, o.order_prefix, o.order_no, st.mod_style AS status_name FROM $table o LEFT JOIN cdb_styles st ON st.id = o.status_courier WHERE o.sender_id = ? AND o.order_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY o.order_id', array_merge([$uid], $ids))->fetchAll(PDO::FETCH_OBJ);
        foreach ($rows as $p) {
            $d['items'][] = ['tracking' => $p->order_prefix . $p->order_no, 'status' => str_replace('_', ' ', (string) $p->status_name),
                'href' => sl_route($r->module === 'air' ? 'ShipmentDetail' : 'PackageDetail', ['id' => (int) $p->order_id])];
        }
    }
    if ((int) $r->driver_id > 0) {
        $u = sl_row('SELECT fname, lname, phone' . (sl_columnExists('cdb_users', 'vehicle_type') ? ', vehicle_type' : '') . (sl_columnExists('cdb_users', 'vehiclecode') ? ', vehiclecode' : '') . ' FROM cdb_users WHERE id = :i', [':i' => (int) $r->driver_id]);
        if ($u) {
            $name = trim($u->fname . ' ' . $u->lname);
            $d['rider'] = ['name' => $name, 'ini' => sl_initials($name ?: 'R'), 'phone' => trim((string) $u->phone), 'vehicle' => trim((string) ($u->vehicle_type ?? '')), 'plate' => trim((string) ($u->vehiclecode ?? ''))];
        }
    }
    // Progress line: each step carries the time it really happened, from the request and its event log.
    $at = [];
    foreach ($d['events'] as $e) {
        $at[$e->state] = $e->created_at;
    }
    $state = (string) $r->state;
    $rank = ['pending' => 0, 'assigned' => 1, 'onroute' => 2, 'failed' => 2, 'delivered' => 3][$state] ?? 0;
    $steps = [
        ['Requested', $r->approved_at ?: $r->created_at],
        ['Rider assigned', $r->assigned_at],
        ['On the way', $at['onroute'] ?? null],
        ['Delivered', $r->completed_at],
    ];
    $d['steps'] = [];
    foreach ($steps as $i => $st) {
        // The current step is the stage the delivery is in now; a cancelled request keeps only "Requested".
        $cls = $state === 'delivered' ? 'done' : ($state === 'cancelled' ? ($i === 0 ? 'done' : '') : ($i < $rank ? 'done' : ($i === $rank ? 'cur' : '')));
        $d['steps'][] = ['label' => $st[0], 'cls' => $cls, 'time' => $st[1] && $cls !== '' ? sl_date($st[1], 'M j, H:i') : ''];
    }
    $d['breakdown'] = json_decode((string) $r->breakdown_json, true) ?: [];
    $d['origin'] = json_decode((string) $r->origin_json, true) ?: [];
    $d['failNote'] = '';
    foreach ($d['events'] as $e) {
        if ($e->state === 'failed') { $d['failNote'] = (string) $e->note; }
    }
    return $d;
}

/**
 * The delivery screens are the same components on a phone and on a computer; only the frame differs.
 * Phone (views/sl/phone/*.php sets $caPhone): the design's app bar and .mx-body. Computer: the .mxp page.
 */
function sl_caOpen(bool $phone, string $title, string $sub, string $back, string $actions = ''): string
{
    if ($phone) {
        return '<link rel="stylesheet" href="' . h(cdp_asset('assets/swiftlane/pages/CustomerApp.css')) . '">' . sl_phoneTop($title, $sub, ['back' => $back], $actions) . '<main class="mx-body ca">';
    }
    return '<div class="mxp wide ca">' . sl_mxHead($title, $sub, $back, $actions);
}

/**
 * Closes sl_caOpen(). $foot: trusted HTML of the action row (buttons), or ''. On a phone the sticky .mx-foot
 * sits where the floating tab bar is, so a screen that keeps the tab bar passes $inline and gets the action
 * as the last block of the body instead.
 */
function sl_caClose(bool $phone, string $foot = '', bool $inline = false): string
{
    if ($phone) {
        return $inline ? $foot . '</main>' : '</main>' . ($foot !== '' ? '<div class="mx-foot"><div class="cd-ft">' . $foot . '</div></div>' : '');
    }
    return ($foot !== '' ? '<div class="mx-foot">' . $foot . '</div>' : '') . '</div>';
}
