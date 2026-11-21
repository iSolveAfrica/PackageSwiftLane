<?php
/**
 * Everything about one customer on one page.
 *
 * Shared by the staff customer profile (CustomerProfile, audience 'staff') and the
 * customer's own account page (UserHome, audience 'customer'). Every source is
 * optional: a table that does not exist on this installation is skipped.
 *
 * Event shape: ts (Y-m-d H:i:s), k (group key), ic (icon), t (title), s (detail),
 * href, by (who did it), tone ('' | ok | warn | bad | info).
 */
require_once CDP_ROOT . '/helpers/sl_view.php';

/** Timeline groups, in display order: key => [label, icon]. */
function sl_u360Groups(string $aud): array
{
    $g = [
        'parcel'  => ['Parcels', 'i-box'],
        'track'   => ['Tracking', 'i-route'],
        'money'   => ['Payments', 'i-dollar'],
        'msg'     => ['Messages', 'i-send'],
        'support' => ['Support', 'i-message'],
        'pickup'  => ['Pickups & delivery', 'i-truck'],
        'pre'     => ['Pre-alerts', 'i-bell'],
        'reward'  => ['Rewards', 'i-star'],
        'claim'   => ['Claims & issues', 'i-alert'],
        'account' => ['Sign-ins & security', 'i-shield'],
        'change'  => ['Changes by staff', 'i-edit'],
    ];
    if ($aud !== 'staff') {
        unset($g['change']);
    }
    return $g;
}

function sl_u360Status(?string $style): string
{
    return trim(str_replace('_', ' ', (string) $style)) ?: 'Updated';
}

function sl_u360Tone(int $status): string
{
    if ($status === 8 || $status === 15) return 'ok';
    if (in_array($status, [21, 12, 16, 28, 29], true)) return 'bad';
    if (in_array($status, [23, 11, 18, 1], true)) return 'warn';
    return 'info';
}

/** Run one source query, never letting a broken optional table break the page. */
function sl_u360Q(string $table, string $sql, array $binds): array
{
    if ($table !== '' && !sl_tableExists($table)) return [];
    try {
        return sl_rows($sql, $binds);
    } catch (\Throwable $e) {
        error_log('user_360 ' . $table . ': ' . $e->getMessage());
        return [];
    }
}

/**
 * All events for a customer, newest first.
 * $aud 'staff' sees internal entries (staff changes, IPs, blocks, internal notes);
 * 'customer' sees only what concerns them.
 */
function sl_u360Events(int $uid, string $aud = 'staff', int $perSource = 150): array
{
    $staff = $aud === 'staff';
    $you = $staff ? 'Customer' : 'You';
    $L = (int) $perSource;
    $b = [':u' => $uid];
    $ev = [];
    $add = function ($ts, $k, $ic, $t, $s = '', $href = '', $by = '', $tone = '') use (&$ev) {
        if (!$ts || strpos((string) $ts, '0000') === 0) return;
        $ev[] = ['ts' => (string) $ts, 'k' => $k, 'ic' => $ic, 't' => $t, 's' => $s, 'href' => $href, 'by' => $by, 'tone' => $tone];
    };

    // Parcels: air shipments (pickups are flagged), customer packages, consolidations.
    foreach (sl_u360Q('cdb_add_order', "SELECT o.order_id, o.order_prefix, o.order_no, COALESCE(o.order_datetime, o.order_date) AS ts, o.total_weight, o.status_courier, o.is_pickup, s.mod_style
        FROM cdb_add_order o LEFT JOIN cdb_styles s ON s.id = o.status_courier WHERE o.sender_id = :u AND o.is_deleted = 0 ORDER BY o.order_id DESC LIMIT $L", $b) as $r) {
        $trk = $r->order_prefix . $r->order_no;
        $pick = (int) $r->is_pickup === 1;
        $add($r->ts, $pick ? 'pickup' : 'parcel', $pick ? 'i-truck' : 'i-plane', ($pick ? 'Pickup booked ' : 'Air shipment ') . $trk,
            sl_u360Status($r->mod_style) . ((float) $r->total_weight ? ' · ' . (float) $r->total_weight . ' kg' : ''), sl_route('ShipmentDetail', ['id' => (int) $r->order_id]), '', sl_u360Tone((int) $r->status_courier));
    }
    foreach (sl_u360Q('cdb_customers_packages', "SELECT p.order_id, p.order_prefix, p.order_no, COALESCE(p.order_datetime, p.order_date) AS ts, p.total_weight, p.status_courier, p.tracking_purchase, p.provider_purchase, s.mod_style
        FROM cdb_customers_packages p LEFT JOIN cdb_styles s ON s.id = p.status_courier WHERE p.sender_id = :u ORDER BY p.order_id DESC LIMIT $L", $b) as $r) {
        $from = trim((string) $r->provider_purchase) !== '' ? ' from ' . $r->provider_purchase : '';
        $add($r->ts, 'parcel', 'i-box', 'Package ' . $r->order_prefix . $r->order_no . ' received' . $from,
            sl_u360Status($r->mod_style) . ((float) $r->total_weight ? ' · ' . (float) $r->total_weight . ' kg' : '') . ($r->tracking_purchase ? ' · store tracking ' . $r->tracking_purchase : ''),
            sl_route('PackageDetail', ['id' => (int) $r->order_id]), '', sl_u360Tone((int) $r->status_courier));
    }
    foreach (['cdb_consolidate' => 'Air consolidation', 'cdb_consolidate_packages' => 'Sea consolidation'] as $tbl => $label) {
        foreach (sl_u360Q($tbl, "SELECT c.consolidate_id, c.c_prefix, c.c_no, COALESCE(c.order_datetime, c.c_date) AS ts, c.total_weight, c.status_courier, s.mod_style
            FROM $tbl c LEFT JOIN cdb_styles s ON s.id = c.status_courier WHERE c.sender_id = :u ORDER BY c.consolidate_id DESC LIMIT $L", $b) as $r) {
            $add($r->ts, 'parcel', 'i-layers', $label . ' ' . $r->c_prefix . $r->c_no, sl_u360Status($r->mod_style) . ((float) $r->total_weight ? ' · ' . (float) $r->total_weight . ' kg' : ''),
                sl_route('ConsolidationDetail', ['id' => (int) $r->consolidate_id, 'type' => $tbl === 'cdb_consolidate_packages' ? 'sea' : 'air']), '', sl_u360Tone((int) $r->status_courier));
        }
    }

    // Tracking updates on any of their parcels.
    foreach (sl_u360Q('cdb_courier_track', "SELECT t.order_track, t.t_date, t.t_city, t.t_dest, t.comments, t.status_courier, s.mod_style, CONCAT(x.fname, ' ', x.lname) AS by_name
        FROM cdb_courier_track t
        JOIN (SELECT CONCAT(order_prefix, order_no) AS trk FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0
              UNION SELECT CONCAT(order_prefix, order_no) FROM cdb_customers_packages WHERE sender_id = :u2
              UNION SELECT CONCAT(c_prefix, c_no) FROM cdb_consolidate WHERE sender_id = :u3
              UNION SELECT CONCAT(c_prefix, c_no) FROM cdb_consolidate_packages WHERE sender_id = :u4) mine ON mine.trk = t.order_track
        LEFT JOIN cdb_styles s ON s.id = t.status_courier LEFT JOIN cdb_users x ON x.id = t.user_id
        ORDER BY t.id DESC LIMIT $L", [':u' => $uid, ':u2' => $uid, ':u3' => $uid, ':u4' => $uid]) as $r) {
        $where = trim(implode(', ', array_filter([(string) $r->t_city, (string) $r->t_dest])));
        $add($r->t_date, 'track', 'i-route', $r->order_track . ': ' . sl_u360Status($r->mod_style), trim($where . ($r->comments ? ' · ' . strip_tags((string) $r->comments) : ''), ' ·'),
            sl_route('LiveTracking', ['q' => $r->order_track]), $staff ? trim((string) $r->by_name) : '', sl_u360Tone((int) $r->status_courier));
    }
    foreach (sl_u360Q('cdb_warehouse_scans', "SELECT w.tracking, w.action, w.bay, w.created_at, CONCAT(x.fname, ' ', x.lname) AS by_name FROM cdb_warehouse_scans w
        JOIN cdb_customers_packages p ON p.order_id = w.order_id AND p.sender_id = :u LEFT JOIN cdb_users x ON x.id = w.user_id ORDER BY w.id DESC LIMIT $L", $b) as $r) {
        $add($r->created_at, 'track', 'i-scan', 'Warehouse scan ' . $r->tracking, ucfirst(str_replace('_', ' ', (string) $r->action)) . ($staff && $r->bay ? ' · bay ' . $r->bay : ''), '', $staff ? trim((string) $r->by_name) : 'Warehouse', 'info');
    }

    // Money: payments on shipments and on consolidations.
    foreach (sl_u360Q('cdb_charges_order', "SELECT c.charge_date, c.total, c.number_reference, c.payment_type, o.order_id, o.order_prefix, o.order_no FROM cdb_charges_order c
        JOIN cdb_add_order o ON o.order_id = c.order_id AND o.sender_id = :u ORDER BY c.id_charge DESC LIMIT $L", $b) as $r) {
        $add($r->charge_date, 'money', 'i-dollar', 'Payment ' . sl_money($r->total) . ' on ' . $r->order_prefix . $r->order_no, trim(($r->payment_type ? 'By ' . $r->payment_type : '') . ($r->number_reference ? ' · ref ' . $r->number_reference : ''), ' ·'),
            sl_route('ShipmentDetail', ['id' => (int) $r->order_id]), '', 'ok');
    }
    foreach (sl_u360Q('cdb_fs_payments', "SELECT amount_ghs, refunded_ghs, mode, reference, gateway_status, reversed_at, recorded_at FROM cdb_fs_payments WHERE sender_id = :u ORDER BY id DESC LIMIT $L", $b) as $r) {
        $rev = !empty($r->reversed_at);
        $add($r->recorded_at, 'money', 'i-receipt', ($rev ? 'Payment reversed ' : 'Payment received ') . sl_money($r->amount_ghs, 'GHS'),
            trim(($r->mode ? ucfirst((string) $r->mode) : '') . ($r->reference ? ' · ref ' . $r->reference : '') . ((float) $r->refunded_ghs ? ' · refunded ' . sl_money($r->refunded_ghs, 'GHS') : ''), ' ·'), '', '', $rev ? 'bad' : 'ok');
    }

    // Messages sent to them.
    foreach (sl_u360Q('cdb_message_log', "SELECT created_at, channel, subject, body, status, sent_by_name, source_label FROM cdb_message_log WHERE recipient_user_id = :u ORDER BY id DESC LIMIT $L", $b) as $r) {
        $ch = strtolower((string) $r->channel);
        $chL = ['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'push' => 'App notification'][$ch] ?? ucfirst($ch);
        $txt = mb_strimwidth(trim(strip_tags((string) ($r->subject ?: $r->body))), 0, 110, '…');
        $failed = in_array($r->status, ['failed', 'bounced', 'error'], true);
        $add($r->created_at, 'msg', 'i-send', $chL . ($failed ? ' not delivered' : ' sent') . ($txt !== '' ? ': ' . $txt : ''), $staff ? trim(ucfirst((string) $r->status) . ($r->source_label ? ' · ' . $r->source_label : ''), ' ·') : '',
            $staff ? sl_route('MessageLogs', ['q' => (string) $uid]) : '', $staff ? (trim((string) $r->sent_by_name) ?: 'System') : '', $failed ? 'bad' : '');
    }

    // Support: tickets and chats.
    foreach (sl_u360Q('cdb_cs_tickets', "SELECT id, public_ref, subject, status, priority, created_at, resolved_at, csat_score FROM cdb_cs_tickets WHERE customer_id = :u AND (is_internal = 0 OR is_internal IS NULL) ORDER BY id DESC LIMIT $L", $b) as $r) {
        $href = $staff ? sl_route('CsTicket', ['id' => (int) $r->id]) : 'support.php';
        $add($r->created_at, 'support', 'i-ticket', 'Support ticket ' . $r->public_ref . ' opened', trim((string) $r->subject) . ' · ' . ucfirst(str_replace('_', ' ', (string) $r->status)) . ($staff ? ' · ' . $r->priority . ' priority' : ''), $href, $you, 'info');
        if ($r->resolved_at) {
            $add($r->resolved_at, 'support', 'i-check', 'Support ticket ' . $r->public_ref . ' resolved', $r->csat_score ? 'Rated ' . (int) $r->csat_score . '/5' : '', $href, '', 'ok');
        }
    }
    foreach (sl_u360Q('cdb_cs_conversations', "SELECT public_ref, channel, subject, created_at, ai_turns, handover_at, rating FROM cdb_cs_conversations WHERE customer_id = :u ORDER BY id DESC LIMIT $L", $b) as $r) {
        $add($r->created_at, 'support', 'i-message', 'Chat started' . ($r->subject ? ': ' . $r->subject : ''), trim(ucfirst((string) $r->channel) . ((int) $r->ai_turns ? ' · ' . cdp_kingName() . ' answered ' . (int) $r->ai_turns . ' times' : '') . ($r->rating ? ' · rated ' . $r->rating : ''), ' ·'), 'support.php', $you);
        if ($r->handover_at) {
            $add($r->handover_at, 'support', 'i-users', 'Chat handed to a person', (string) $r->public_ref, 'support.php', cdp_kingName(), 'info');
        }
    }

    // Pickups and doorstep deliveries.
    foreach (sl_u360Q('cdb_delivery_requests', "SELECT id, state, address, amount_cents, currency, created_at, completed_at FROM cdb_delivery_requests WHERE customer_id = :u ORDER BY id DESC LIMIT $L", $b) as $r) {
        $add($r->created_at, 'pickup', 'i-truck', 'Delivery requested', trim(mb_strimwidth((string) $r->address, 0, 80, '…') . ' · ' . ucfirst(str_replace('_', ' ', (string) $r->state)), ' ·'), sl_route('DeliveryRequest', ['id' => (int) $r->id]), $you, 'info');
        if ($r->completed_at) $add($r->completed_at, 'pickup', 'i-check', 'Delivered to the door', '', sl_route('DeliveryRequest', ['id' => (int) $r->id]), '', 'ok');
    }
    foreach (sl_u360Q('cdb_pickup_codes', "SELECT code, status, created_at, verified_at, trackings FROM cdb_pickup_codes WHERE owner_id = :u ORDER BY id DESC LIMIT $L", $b) as $r) {
        $add($r->created_at, 'pickup', 'i-key', 'Pickup code issued', ($staff ? 'Code ' . $r->code . ' · ' : '') . ucfirst((string) $r->status), $staff ? '' : sl_route('PickupCodes'), '', 'info');
        if ($r->verified_at) $add($r->verified_at, 'pickup', 'i-check', 'Collected at the warehouse with pickup code', '', '', '', 'ok');
    }

    // Pre-alerts.
    foreach (sl_u360Q('cdb_pre_alert', "SELECT tracking, provider_shop, courier_com, package_description, prealert_date, estimated_date FROM cdb_pre_alert WHERE customer_id = :u ORDER BY pre_alert_id DESC LIMIT $L", $b) as $r) {
        $add($r->prealert_date, 'pre', 'i-bell', 'Pre-alert ' . $r->tracking . ($r->provider_shop ? ' from ' . $r->provider_shop : ''),
            trim(mb_strimwidth((string) $r->package_description, 0, 80, '…') . ($r->estimated_date ? ' · expected ' . sl_date($r->estimated_date, 'j M') : ''), ' ·'), sl_route('PreAlerts'), $you);
    }

    // Rewards.
    foreach (sl_u360Q('cdb_loyalty_events', "SELECT kind, points, note, created_at FROM cdb_loyalty_events WHERE user_id = :u ORDER BY id DESC LIMIT $L", $b) as $r) {
        $p = (int) $r->points;
        $add($r->created_at, 'reward', 'i-star', ($p >= 0 ? '+' : '') . $p . ' points · ' . ucfirst(str_replace('_', ' ', (string) $r->kind)), (string) $r->note, sl_route('LoyaltyRewards'), '', $p >= 0 ? 'ok' : '');
    }

    // Claims and cargo issues.
    foreach (sl_u360Q('cdb_insurance_claims', "SELECT claim_no, order_track, type, status, claim_amount, created_at FROM cdb_insurance_claims WHERE customer_id = :u ORDER BY id DESC LIMIT $L", $b) as $r) {
        $add($r->created_at, 'claim', 'i-shield', 'Claim ' . $r->claim_no . ' on ' . $r->order_track, ucfirst((string) $r->type) . ' · ' . ucfirst(str_replace('_', ' ', (string) $r->status)) . ' · ' . sl_money($r->claim_amount), $staff ? sl_route('InsuranceClaims') : '', '', 'warn');
    }
    $exWhere = $staff ? '' : ' AND notify_customer = 1';
    foreach (sl_u360Q('cdb_cargo_exceptions', "SELECT title, entity_ref, severity, status, created_at, resolved_at FROM cdb_cargo_exceptions WHERE customer_id = :u$exWhere ORDER BY id DESC LIMIT $L", $b) as $r) {
        $add($r->created_at, 'claim', 'i-alert', 'Issue: ' . $r->title, trim($r->entity_ref . ' · ' . ucfirst((string) $r->status) . ($staff ? ' · ' . $r->severity : ''), ' ·'), $staff ? sl_route('CargoExceptions') : '', '', $r->resolved_at ? 'ok' : 'bad');
    }

    // Sign-ins and account security (the customer's own actions).
    $acWhere = $staff ? "verb <> 'view'" : "module = 'auth'";
    foreach (sl_u360Q('cdb_activity_log', "SELECT created_at, module, verb, outcome, summary, action_label, entity_label, ip, user_agent FROM cdb_activity_log WHERE user_id = :u AND $acWhere ORDER BY id DESC LIMIT $L", $b) as $r) {
        $title = trim((string) $r->summary) ?: trim((string) $r->action_label);
        $dev = sl_u360Device((string) $r->user_agent);
        $s = trim(($dev ? $dev : '') . ($staff && $r->ip ? ' · IP ' . $r->ip : ''), ' ·');
        $add($r->created_at, $r->module === 'auth' ? 'account' : 'change', $r->module === 'auth' ? 'i-shield' : 'i-activity', $title, $s, '', $you, $r->outcome === 'success' ? '' : 'bad');
    }
    foreach (sl_u360Q('cdb_auth_trusted_devices', "SELECT created_at, revoked_at FROM cdb_auth_trusted_devices WHERE user_id = :u ORDER BY id DESC LIMIT $L", $b) as $r) {
        $add($r->created_at, 'account', 'i-lock', 'New device trusted for sign-in', '', '', $you, 'info');
        if ($r->revoked_at) $add($r->revoked_at, 'account', 'i-lock', 'Trusted device removed', '', '', '', '');
    }

    if ($staff) {
        // What staff changed on this customer.
        foreach (sl_u360Q('cdb_activity_log', "SELECT created_at, actor_name, summary, action_label, outcome FROM cdb_activity_log
            WHERE entity_type IN ('user', 'customer') AND entity_id = :u AND (user_id IS NULL OR user_id <> :u2) AND verb <> 'view' ORDER BY id DESC LIMIT $L", [':u' => $uid, ':u2' => $uid]) as $r) {
            $add($r->created_at, 'change', 'i-edit', trim((string) $r->summary) ?: trim((string) $r->action_label), '', '', trim((string) $r->actor_name) ?: 'Staff', $r->outcome === 'success' ? '' : 'bad');
        }
        foreach (sl_u360Q('cdb_profile_update_history', "SELECT h.datetime, h.remarks, CONCAT(x.fname, ' ', x.lname) AS by_name FROM cdb_profile_update_history h LEFT JOIN cdb_users x ON x.id = h.update_by WHERE h.user_id = :u ORDER BY h.id DESC LIMIT $L", $b) as $r) {
            $add($r->datetime, 'change', 'i-file', 'Profile document updated', (string) $r->remarks, '', trim((string) $r->by_name) ?: 'Staff');
        }
        foreach (sl_u360Q('cdb_customer_blocks', "SELECT b.reason, b.blocked_at, b.unblocked_at, CONCAT(x.fname, ' ', x.lname) AS by_name FROM cdb_customer_blocks b LEFT JOIN cdb_users x ON x.id = b.blocked_by WHERE b.user_id = :u ORDER BY b.id DESC LIMIT $L", $b) as $r) {
            $add($r->blocked_at, 'change', 'i-ban', 'Account blocked', (string) $r->reason, '', trim((string) $r->by_name) ?: 'Staff', 'bad');
            if ($r->unblocked_at) $add($r->unblocked_at, 'change', 'i-check', 'Account unblocked', '', '', 'Staff', 'ok');
        }
    }

    usort($ev, fn($a, $b) => strcmp($b['ts'], $a['ts']));
    return $ev;
}

/** "Safari on iPhone" from a user agent. */
function sl_u360Device(string $ua): string
{
    if ($ua === '') return '';
    $os = preg_match('/iPhone/i', $ua) ? 'iPhone' : (preg_match('/iPad/i', $ua) ? 'iPad' : (preg_match('/Android/i', $ua) ? 'Android' : (preg_match('/Windows/i', $ua) ? 'Windows' : (preg_match('/Mac OS X|Macintosh/i', $ua) ? 'Mac' : (preg_match('/Linux/i', $ua) ? 'Linux' : '')))));
    $br = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/OPR\//', $ua) ? 'Opera' : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Firefox\//', $ua) ? 'Firefox' : (preg_match('/Safari\//', $ua) ? 'Safari' : ''))));
    if (preg_match('/curl|bot|spider|python|okhttp/i', $ua)) return 'App or script';
    return trim($br . ($br && $os ? ' on ' : '') . $os);
}

/**
 * Timeline block data: filter by group, page through, group by day.
 * Returns ['groups' => [k => [label, icon, count]], 'k' => current, 'days' => [[label, events]], 'total', 'shown', 'more'].
 */
function sl_u360Timeline(array $events, string $aud, string $k = '', int $show = 40): array
{
    $groups = [];
    foreach (sl_u360Groups($aud) as $gk => [$label, $icon]) $groups[$gk] = [$label, $icon, 0];
    foreach ($events as $e) if (isset($groups[$e['k']])) $groups[$e['k']][2]++;
    if ($k !== '' && !isset($groups[$k])) $k = '';
    $list = $k === '' ? $events : array_values(array_filter($events, fn($e) => $e['k'] === $k));
    $total = count($list);
    $list = array_slice($list, 0, max(1, $show));
    $days = [];
    $today = date('Y-m-d');
    $yday = date('Y-m-d', strtotime('-1 day'));
    foreach ($list as $e) {
        $d = substr($e['ts'], 0, 10);
        $label = $d === $today ? 'Today' : ($d === $yday ? 'Yesterday' : sl_date($d, 'D, j M Y'));
        $e['time'] = strlen($e['ts']) > 10 && substr($e['ts'], 11, 5) !== '00:00' ? substr($e['ts'], 11, 5) : '';
        $days[$d][0] = $label;
        $days[$d][1][] = $e;
    }
    return ['groups' => array_filter($groups, fn($g) => $g[2] > 0), 'k' => $k, 'days' => array_values($days), 'total' => $total, 'shown' => count($list), 'more' => $total > count($list), 'all' => count($events)];
}

/** Parcels still moving (not delivered, cancelled or rejected), newest first. */
function sl_u360InMotion(int $uid, int $limit = 8): array
{
    $out = [];
    $done = '8, 21, 12, 15';
    $rows = array_merge(
        sl_u360Q('cdb_add_order', "SELECT 'air' AS src, o.order_id AS id, CONCAT(o.order_prefix, o.order_no) AS trk, o.order_date AS d, o.status_courier, s.mod_style, o.estimated_eta FROM cdb_add_order o LEFT JOIN cdb_styles s ON s.id = o.status_courier WHERE o.sender_id = :u AND o.is_deleted = 0 AND o.status_courier NOT IN ($done) ORDER BY o.order_id DESC LIMIT $limit", [':u' => $uid]),
        sl_u360Q('cdb_customers_packages', "SELECT 'pkg' AS src, p.order_id AS id, CONCAT(p.order_prefix, p.order_no) AS trk, p.order_date AS d, p.status_courier, s.mod_style, p.estimated_eta FROM cdb_customers_packages p LEFT JOIN cdb_styles s ON s.id = p.status_courier WHERE p.sender_id = :u AND p.status_courier NOT IN ($done) ORDER BY p.order_id DESC LIMIT $limit", [':u' => $uid])
    );
    usort($rows, fn($a, $b) => strcmp((string) $b->d, (string) $a->d));
    // Simple journey: received → in transit → at warehouse → ready → delivered.
    $stage = function (int $s): int {
        if (in_array($s, [1, 2, 10, 11, 14, 17, 18, 13, 25], true)) return 1;
        if (in_array($s, [3, 7], true)) return 2;
        if (in_array($s, [4, 5, 28, 29, 19, 23], true)) return 3;
        if (in_array($s, [6, 32], true)) return 4;
        return 1;
    };
    foreach (array_slice($rows, 0, $limit) as $r) {
        $out[] = ['trk' => $r->trk, 'kind' => $r->src === 'air' ? 'Air' : 'Package', 'st' => sl_u360Status($r->mod_style), 'cls' => sl_statusClass($r->status_courier), 'stage' => $stage((int) $r->status_courier),
            'eta' => !empty($r->estimated_eta) && strpos((string) $r->estimated_eta, '0000') !== 0 ? sl_date($r->estimated_eta, 'j M') : '', 'since' => sl_ago($r->d),
            'href' => $r->src === 'air' ? sl_route('ShipmentDetail', ['id' => (int) $r->id]) : sl_route('PackageDetail', ['id' => (int) $r->id]), 'track' => sl_route('LiveTracking', ['q' => $r->trk])];
    }
    return $out;
}

/** Rolling-year heatmap of parcels created per day: 53 columns × 7 rows. */
function sl_u360Heatmap(int $uid): array
{
    $map = [];
    foreach (sl_u360Q('cdb_add_order', "SELECT d, COUNT(*) AS n FROM (SELECT DATE(order_date) AS d FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND order_date >= CURDATE() - INTERVAL 371 DAY
            UNION ALL SELECT DATE(order_date) FROM cdb_customers_packages WHERE sender_id = :u2 AND order_date >= CURDATE() - INTERVAL 371 DAY) x GROUP BY d", [':u' => $uid, ':u2' => $uid]) as $r) {
        $map[(string) $r->d] = (int) $r->n;
    }
    $peak = max(1, max($map ?: [0]));
    $start = strtotime('monday this week', strtotime('-52 weeks'));
    $weeks = [];
    $months = [];
    $active = 0;
    for ($w = 0; $w < 53; $w++) {
        $col = [];
        for ($d = 0; $d < 7; $d++) {
            $t = $start + ($w * 7 + $d) * 86400;
            $key = date('Y-m-d', $t);
            $n = $map[$key] ?? 0;
            if ($n) $active++;
            $lvl = $n === 0 ? 0 : min(4, (int) ceil($n / $peak * 4));
            $col[] = ['d' => $key, 'n' => $n, 'l' => $t > time() ? -1 : $lvl];
        }
        $m = date('M', $start + $w * 7 * 86400);
        if (!$months || end($months)[1] !== $m) $months[] = [$w, $m];
        $weeks[] = $col;
    }
    return ['weeks' => $weeks, 'months' => $months, 'peak' => max($map ?: [0]), 'activeDays' => $active, 'total' => array_sum($map)];
}
