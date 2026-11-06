<?php
/** Data for the staff customer 360 (CustomerProfile.dc.html). */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/finance_switch.php';

/** Is the viewer allowed to see this customer (agency scope)? */
function sl_cpInScope(int $agencyId): bool
{
    if (!function_exists('cdp_getAgencyContext')) return true;
    $ctx = cdp_getAgencyContext();
    return empty($ctx['is_restricted']) || (int) ($ctx['agency_id'] ?? 0) === $agencyId;
}

function sl_cpAddresses(int $uid): array
{
    return array_map(fn($a) => ['id' => (int) $a->id_addresses, 'line' => trim(implode(', ', array_filter([$a->address, $a->city_name, $a->state_name, $a->country_name, $a->zip_code]))), 'used' => (int) $a->used],
        sl_rows('SELECT a.*, ci.name AS city_name, st.name AS state_name, co.name AS country_name,
            (SELECT COUNT(*) FROM cdb_add_order o WHERE o.sender_address_id = a.id_addresses) + (SELECT COUNT(*) FROM cdb_customers_packages p WHERE p.sender_address_id = a.id_addresses) AS used
            FROM cdb_senders_addresses a LEFT JOIN cdb_cities ci ON ci.id = a.city LEFT JOIN cdb_states st ON st.id = a.state LEFT JOIN cdb_countries co ON co.id = a.country WHERE a.user_id = :u ORDER BY a.id_addresses', [':u' => $uid]));
}

function sl_customerProfileData($user, $core, int $id): ?array
{
    $u = sl_row('SELECT * FROM cdb_users WHERE id = :id AND userlevel = 1', [':id' => $id]);
    if (!$u || !sl_cpInScope((int) $u->agency_id)) return null;
    $finance = cdp_financeEnabled();
    $name = trim($u->fname . ' ' . $u->lname) ?: $u->username;
    $block = sl_tableExists('cdb_customer_blocks') ? sl_row('SELECT b.*, CONCAT(x.fname, " ", x.lname) AS by_name FROM cdb_customer_blocks b LEFT JOIN cdb_users x ON x.id = b.blocked_by WHERE b.user_id = :u AND b.unblocked_at IS NULL ORDER BY b.id DESC', [':u' => $id]) : null;
    $blocked = $block || !(int) $u->active;
    $approve = isset($u->approve) ? (int) $u->approve : 1;
    $phoneV = sl_tableExists('cdb_profile_phone_verified') ? sl_row('SELECT * FROM cdb_profile_phone_verified WHERE user_id = :u', [':u' => $id]) : null;
    $nShip = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0', [':u' => $id]);
    $nPkg = (int) sl_scalar('SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u', [':u' => $id]);
    $spend = (float) sl_scalar('SELECT COALESCE(SUM(total_order),0) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier <> 21', [':u' => $id]) + (float) sl_scalar('SELECT COALESCE(SUM(total_order),0) FROM cdb_customers_packages WHERE sender_id = :u AND status_courier <> 21', [':u' => $id]);
    $due = (float) sl_scalar('SELECT COALESCE(SUM(total_order),0) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_invoice = 23 AND status_courier <> 21', [':u' => $id]) + (float) sl_scalar('SELECT COALESCE(SUM(total_order),0) FROM cdb_customers_packages WHERE sender_id = :u AND status_invoice = 23 AND status_courier <> 21', [':u' => $id]);
    $waiting = (int) sl_scalar('SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u AND status_courier IN (4,6,32)', [':u' => $id]);
    $tab = (string) ($_GET['tab'] ?? 'ship');
    if (!in_array($tab, ['ship', 'pkg', 'pay', 'msg', 'recip'], true)) $tab = 'ship';
    $page = max(1, (int) ($_GET['page'] ?? 1)); $per = 15; $off = ($page - 1) * $per;
    $rows = []; $count = 0;
    if ($tab === 'ship') {
        $count = $nShip;
        foreach (sl_rows("SELECT o.order_id, o.order_prefix, o.order_no, o.order_date, o.total_weight, o.total_order, o.status_courier, s.mod_style, (SELECT ci.name FROM cdb_recipients_addresses ra LEFT JOIN cdb_cities ci ON ci.id = ra.city WHERE ra.recipient_id = o.receiver_id LIMIT 1) AS dest FROM cdb_add_order o LEFT JOIN cdb_styles s ON s.id = o.status_courier WHERE o.sender_id = :u AND o.is_deleted = 0 ORDER BY o.order_id DESC LIMIT $off, $per", [':u' => $id]) as $r)
            $rows[] = ['href' => sl_route('ShipmentDetail', ['id' => $r->order_id]), 'trk' => $r->order_prefix . $r->order_no, 'date' => sl_date($r->order_date, 'M j'), 'dest' => $r->dest ?: '—', 'kg' => (float) $r->total_weight . ' kg', 'amt' => $finance ? sl_money($r->total_order) : '', 'st' => $r->mod_style, 'cls' => sl_statusClass($r->status_courier)];
    } elseif ($tab === 'pkg') {
        $count = $nPkg;
        foreach (sl_rows("SELECT p.order_id, p.order_prefix, p.order_no, p.order_date, p.total_weight, p.total_order, p.status_courier, p.warehouse_bay, s.mod_style FROM cdb_customers_packages p LEFT JOIN cdb_styles s ON s.id = p.status_courier WHERE p.sender_id = :u ORDER BY p.order_id DESC LIMIT $off, $per", [':u' => $id]) as $r)
            $rows[] = ['href' => sl_route('PackageDetail', ['id' => $r->order_id]), 'trk' => $r->order_prefix . $r->order_no, 'date' => sl_date($r->order_date, 'M j'), 'dest' => $r->warehouse_bay ? 'Bay ' . $r->warehouse_bay : '—', 'kg' => (float) $r->total_weight . ' kg', 'amt' => $finance ? sl_money($r->total_order) : '', 'st' => $r->mod_style, 'cls' => sl_statusClass($r->status_courier)];
    } elseif ($tab === 'pay') {
        $count = (int) sl_scalar('SELECT (SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier <> 21) + (SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u2 AND status_courier <> 21)', [':u' => $id, ':u2' => $id]);
        foreach (sl_rows("SELECT * FROM (SELECT 'ship' AS k, order_id, order_prefix, order_no, order_date, payment_date, total_order, status_invoice FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier <> 21 UNION ALL SELECT 'pkg', order_id, order_prefix, order_no, order_date, payment_date, total_order, status_invoice FROM cdb_customers_packages WHERE sender_id = :u2 AND status_courier <> 21) x ORDER BY order_date DESC LIMIT $off, $per", [':u' => $id, ':u2' => $id]) as $r) {
            $paid = in_array((int) $r->status_invoice, [1, 19], true) || ($r->payment_date && (int) $r->status_invoice !== 23);
            $rows[] = ['href' => $r->k === 'ship' ? sl_route('ShipmentDetail', ['id' => $r->order_id]) : sl_route('PackageDetail', ['id' => $r->order_id]), 'trk' => $r->order_prefix . $r->order_no, 'date' => sl_date($r->order_date, 'M j'), 'dest' => $r->payment_date ? 'Paid ' . sl_date($r->payment_date, 'M j') : '—', 'kg' => '', 'amt' => $finance ? sl_money($r->total_order) : '', 'st' => (int) $r->status_invoice === 23 ? 'Pending payment' : ($paid ? 'Paid' : 'Invoiced'), 'cls' => (int) $r->status_invoice === 23 ? 'pending' : 'paid'];
        }
    } elseif ($tab === 'msg') {
        if (sl_tableExists('cdb_message_log')) {
            $count = (int) sl_scalar('SELECT COUNT(*) FROM cdb_message_log WHERE recipient_user_id = :u', [':u' => $id]);
            foreach (sl_rows("SELECT created_at, channel, subject, body, status, source_label FROM cdb_message_log WHERE recipient_user_id = :u ORDER BY id DESC LIMIT $off, $per", [':u' => $id]) as $r)
                $rows[] = ['href' => sl_route('MessageLogs', ['q' => $u->email]), 'trk' => strtoupper((string) $r->channel), 'date' => sl_date($r->created_at, 'M j H:i'), 'dest' => mb_strimwidth(strip_tags((string) ($r->subject ?: $r->body)), 0, 70, '…'), 'kg' => '', 'amt' => $r->source_label ?: '', 'st' => ucfirst((string) $r->status), 'cls' => $r->status === 'sent' || $r->status === 'delivered' ? 'delivered' : ($r->status === 'failed' ? 'overdue' : 'plain')];
        }
    } else {
        $count = (int) sl_scalar('SELECT COUNT(*) FROM cdb_recipients WHERE sender_id = :u', [':u' => $id]);
        foreach (sl_rows("SELECT r.*, (SELECT COUNT(*) FROM cdb_add_order o WHERE o.receiver_id = r.id AND o.is_deleted = 0) AS n FROM cdb_recipients r WHERE r.sender_id = :u ORDER BY r.id DESC LIMIT $off, $per", [':u' => $id]) as $r)
            $rows[] = ['href' => 'recipients_edit.php?recipient=' . (int) $r->id, 'trk' => trim($r->fname . ' ' . $r->lname), 'date' => '', 'dest' => $r->phone ?: '—', 'kg' => $r->email, 'amt' => (int) $r->n . ' shipments', 'st' => 'Recipient', 'cls' => 'plain'];
    }
    // activity per month (6)
    $act = [];
    $map = [];
    foreach (sl_rows("SELECT DATE_FORMAT(d, '%Y-%m') AS ym, COUNT(*) AS n FROM (SELECT order_date AS d FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 UNION ALL SELECT order_date FROM cdb_customers_packages WHERE sender_id = :u2) x WHERE d >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01') GROUP BY ym", [':u' => $id, ':u2' => $id]) as $r) $map[$r->ym] = (int) $r->n;
    $mx = max(1, max($map ?: [0]));
    for ($i = 5; $i >= 0; $i--) { $ym = date('Y-m', strtotime("first day of -$i months")); $act[] = ['l' => date('M', strtotime($ym . '-01')), 'h' => 'height:' . max(3, sl_pct($map[$ym] ?? 0, $mx)) . '%', 'n' => $map[$ym] ?? 0, 'hi' => $i === 0 ? 'hi' : '']; }
    $docUrl = !empty($u->document_photo) ? (preg_match('#^(https?:|/)#', $u->document_photo) ? $u->document_photo : (is_file(CDP_ROOT . '/' . ltrim($u->document_photo, '/')) ? ltrim($u->document_photo, '/') : 'assets/' . ltrim($u->document_photo, '/'))) : '';
    $hist = sl_tableExists('cdb_profile_update_history') ? sl_rows('SELECT h.*, CONCAT(x.fname, " ", x.lname) AS by_name FROM cdb_profile_update_history h LEFT JOIN cdb_users x ON x.id = h.update_by WHERE h.user_id = :u ORDER BY h.id DESC LIMIT 3', [':u' => $id]) : [];
    return ['id' => $id, 'name' => $name, 'ini' => sl_initials($name), 'locker' => $u->locker ?: '—', 'since' => sl_date($u->created, 'M Y'), 'city' => $u->city ?? '', 'mail' => $u->email, 'phone' => $u->phone ?: '—', 'username' => $u->username,
        'company' => $u->company ?? '', 'docType' => $u->document_type, 'docNo' => $u->document_number, 'docUrl' => $docUrl, 'approve' => $approve, 'phoneV' => (bool) $phoneV, 'newsletter' => (int) $u->newsletter, 'notes' => $u->notes,
        'blocked' => $blocked, 'block' => $block ? ['reason' => $block->reason, 'by' => trim((string) $block->by_name) ?: 'staff', 'at' => sl_ago($block->blocked_at)] : null,
        'st' => $blocked ? ['Blocked', 'overdue'] : (!$approve ? ['Awaiting approval', 'pending'] : ['Active', 'delivered']),
        'nShip' => $nShip, 'nPkg' => $nPkg, 'spend' => $finance ? sl_money($spend) : '—', 'due' => $finance ? sl_money($due) : '—', 'dueN' => $due, 'waiting' => $waiting, 'finance' => $finance,
        'tab' => $tab, 'rows' => $rows, 'count' => $count, 'page' => $page, 'per' => $per, 'act' => $act, 'addresses' => sl_cpAddresses($id), 'hist' => array_map(fn($h) => ['by' => trim((string) $h->by_name), 'at' => sl_date($h->datetime), 'why' => $h->remarks], $hist),
        'countries' => sl_rows('SELECT id, name FROM cdb_countries WHERE is_active = 1 ORDER BY name LIMIT 300'),
        'canEdit' => $user->cdp_hasPermission('edit_client'), 'canApprove' => isset($u->approve) && $user->cdp_hasPermission(['approve_client', 'edit_client']), 'canDelete' => $user->cdp_hasPermission('delete_client'), 'canShip' => $user->cdp_hasPermission('add_shipment'), 'canRecip' => $user->cdp_hasPermission('recipients_add'), 'canViewAs' => function_exists('cdp_canViewAs') && cdp_canViewAs((int) $user->userlevel, 1)];
}
