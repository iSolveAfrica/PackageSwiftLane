<?php
/**
 * Data for the air shipment pages: AirShipments, ShipmentDetail, ShipmentTracking,
 * DeliveryTracking and NewShipment (views/sl/pages/*). Read only; writes live in
 * ajax/sl/shipment_action_ajax.php and the legacy ajax/courier/* endpoints.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/views/sl/data/ops_common.php';
require_once CDP_ROOT . '/helpers/air_journey.php';
require_once CDP_ROOT . '/helpers/track_progress.php';

/** Paid? Legacy rows use status_invoice 1, imported/seeded rows the cdb_styles id 19 (Invoiced, paid). */
function sl_shipPaid($statusInvoice): bool
{
    return in_array((int) $statusInvoice, [1, 19], true);
}

/** Payment pill for an air shipment (19 counts as paid, see sl_shipPaid). */
function sl_shipPayPill($statusInvoice): array
{
    return sl_shipPaid($statusInvoice) ? ['Paid', 'paid'] : sl_payPill($statusInvoice);
}

/** Carrier tracking numbers for a set of order ids (cdb_package_tracking_number, legacy column fallback). */
function sl_carrierTrackingFor(array $orderIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
    if (!$ids) {
        return [];
    }
    $out = [];
    if (sl_tableExists('cdb_package_tracking_number')) {
        foreach (sl_rows('SELECT order_id, tracking_number, estimated_eta FROM cdb_package_tracking_number WHERE order_id IN (' . implode(',', $ids) . ') ORDER BY id') as $r) {
            $out[(int) $r->order_id] = $r;
        }
    }
    if (sl_columnExists('cdb_add_order', 'tracking_num')) {
        foreach (sl_rows('SELECT order_id, tracking_num FROM cdb_add_order WHERE order_id IN (' . implode(',', $ids) . ') AND tracking_num IS NOT NULL AND tracking_num <> ""') as $r) {
            if (empty($out[(int) $r->order_id]->tracking_number)) {
                $out[(int) $r->order_id] = (object) ['order_id' => $r->order_id, 'tracking_number' => $r->tracking_num, 'estimated_eta' => $out[(int) $r->order_id]->estimated_eta ?? null];
            }
        }
    }
    return $out;
}

/** Recipient display rows (cdb_recipients or cdb_users when recipient_type = user) keyed by order id. */
function sl_recipientsFor(array $orders): array
{
    $users = $recips = [];
    foreach ($orders as $o) {
        $t = $o->recipient_type ?? 'recipient';
        if ($t === 'user') $users[] = (int) $o->receiver_id; else $recips[] = (int) $o->receiver_id;
    }
    $umap = sl_usersById($users);
    $rmap = [];
    $recips = array_values(array_unique(array_filter($recips)));
    if ($recips) {
        foreach (sl_rows('SELECT id, fname, lname, email, phone FROM cdb_recipients WHERE id IN (' . implode(',', $recips) . ')') as $r) {
            $rmap[(int) $r->id] = $r;
        }
    }
    $out = [];
    foreach ($orders as $o) {
        $t = $o->recipient_type ?? 'recipient';
        $out[(int) $o->order_id] = $t === 'user' ? ($umap[(int) $o->receiver_id] ?? null) : ($rmap[(int) $o->receiver_id] ?? null);
    }
    return $out;
}

/** The row actions the legacy list offered, filtered by permission and state. */
function sl_shipmentActions($user, object $o, bool $client): array
{
    $id = (int) $o->order_id;
    $st = (int) $o->status_courier;
    $handed = cdp_isHandedOver($st);
    $acts = [];
    if ($client || $user->cdp_hasPermission('view_shipment_details')) {
        $acts[] = ['href' => sl_route('ShipmentDetail', ['id' => $id]), 'icon' => 'i-eye', 'label' => 'View details', 'cls' => ''];
    }
    if ($client && (int) $o->status_invoice === 2) {
        $acts[] = ['href' => 'add_payment_gateways_courier.php?id_order=' . $id, 'icon' => 'i-dollar', 'label' => 'Pay now', 'cls' => 'pri'];
    }
    if (!$client && (int) $o->status_invoice === 3 && $user->cdp_hasPermission('verify_payments')) {
        $acts[] = ['href' => sl_route('ShipmentDetail', ['id' => $id]) . '#payment', 'icon' => 'i-dollar', 'label' => 'Verify payment', 'cls' => ''];
    }
    if (!$client && (int) $o->order_incomplete === 0 && !(int) $o->is_pickup && $user->cdp_hasPermission('complete_client_shipment')) {
        $acts[] = ['href' => 'courier_accept.php?id=' . $id, 'icon' => 'i-check', 'label' => 'Accept shipment', 'cls' => ''];
    }
    if (!$client && (int) $o->order_incomplete === 1 && !$handed && $user->cdp_hasPermission('edit_shipment')) {
        $acts[] = ['href' => 'courier_edit.php?id=' . $id, 'icon' => 'i-edit', 'label' => 'Edit', 'cls' => ''];
    }
    if (!$client && $st !== 21 && $st !== 12 && $user->cdp_hasPermission('track_shipment')) {
        $acts[] = ['href' => sl_route('ShipmentTracking', ['id' => $id]), 'icon' => 'i-route', 'label' => 'Update status / track', 'cls' => ''];
    }
    if ($user->cdp_hasPermission('print_label')) {
        $acts[] = ['href' => sl_route('Label4x6', ['id' => $id]), 'icon' => 'i-print', 'label' => 'Print label', 'cls' => '', 'blank' => true];
    }
    if ($user->cdp_hasPermission('print_shipment')) {
        $acts[] = ['href' => 'print_inv_ship.php?id=' . $id, 'icon' => 'i-receipt', 'label' => 'Invoice', 'cls' => '', 'blank' => true];
        $acts[] = ['href' => sl_route('ReceiptPaid', ['id' => $id, 'type' => 'air']), 'icon' => 'i-receipt', 'label' => 'Receipt', 'cls' => '', 'blank' => true];
    }
    if (!$client && $st !== 21 && $st !== 12 && !$handed && $user->cdp_hasPermission('assign_drivers')) {
        $acts[] = ['href' => '#', 'icon' => 'i-wheel', 'label' => 'Assign driver', 'cls' => '', 'dialog' => 'driver'];
    }
    if (!$client && $user->cdp_hasPermission('send_email_attachment')) {
        $acts[] = ['href' => sl_route('ComposeMessage', ['order' => $o->order_prefix . $o->order_no, 'user' => (int) $o->sender_id]), 'icon' => 'i-mail', 'label' => 'Send e-mail', 'cls' => ''];
    }
    if (!$client && $user->cdp_hasPermission('deliver_shipment')) {
        $acts[] = ['href' => 'courier_deliver_shipment.php?id=' . $id, 'icon' => 'i-box', 'label' => 'Deliver package', 'cls' => ''];
    }
    if (!$client && $st !== 21 && $st !== 12 && $user->cdp_hasPermission('cancel_shipment')) {
        $acts[] = ['href' => '#', 'icon' => 'i-x', 'label' => 'Cancel shipment', 'cls' => 'danger', 'action' => 'cancel'];
    }
    if (!$client && !(int) $o->is_consolidate && !$handed && $user->cdp_hasPermission('delete_shipment')) {
        $acts[] = ['href' => '#', 'icon' => 'i-trash', 'label' => 'Delete', 'cls' => 'danger', 'action' => 'delete'];
    }
    return $acts;
}

function sl_airShipmentsData($user, $core): array
{
    $client = sl_userIs('client');
    $scope = sl_opsScope('a');
    $base = "FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier <> 14 $scope";
    $month = (int) sl_scalar("SELECT COUNT(*) $base AND a.order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $all = (int) sl_scalar("SELECT COUNT(*) $base");
    $open = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier NOT IN (8,15,16,21,12)");
    $delivered = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier IN (8,15)");
    $unpaid = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_invoice IN (2,23) AND a.status_courier <> 21");
    $consol = (int) sl_scalar("SELECT COUNT(*) $base AND a.is_consolidate = 1");
    $customs = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier IN (28,29,30)");
    $noCarrier = sl_tableExists('cdb_package_tracking_number') ? (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier NOT IN (8,15,21,12) AND NOT EXISTS (SELECT 1 FROM cdb_package_tracking_number p WHERE p.order_id = a.order_id AND p.tracking_number <> '')") : 0;
    $d = [
        'month' => $month, 'all' => $all,
        'metrics' => [
            ['label' => 'Open / in progress', 'v' => $open, 'cls' => 'y'],
            ['label' => 'Delivered', 'v' => $delivered, 'cls' => ''],
            ['label' => 'Awaiting payment', 'v' => $unpaid, 'cls' => 'r'],
            ['label' => 'In consolidations', 'v' => $consol, 'cls' => 'v'],
        ],
        'attention' => $customs + $unpaid + $noCarrier, 'customs' => $customs, 'unpaid' => $unpaid, 'noCarrier' => $noCarrier,
        'client' => $client,
    ];

    // list
    $q = trim((string) ($_GET['q'] ?? ''));
    $status = (int) ($_GET['status'] ?? 0);
    $driver = (int) ($_GET['driver'] ?? 0);
    $period = (string) ($_GET['period'] ?? 'all');
    $filter = (string) ($_GET['filter'] ?? 'all');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = in_array((int) ($_GET['per'] ?? 25), [25, 50, 100], true) ? (int) ($_GET['per'] ?? 25) : 25;
    $where = "a.is_deleted = 0 AND a.status_courier <> 14 $scope";
    $binds = [];
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where .= " AND (CONCAT(a.order_prefix, a.order_no) LIKE :q1 OR a.order_no LIKE :q2"
            . " OR a.sender_id IN (SELECT id FROM cdb_users WHERE CONCAT(fname,' ',lname) LIKE :q3 OR locker LIKE :q4 OR email LIKE :q5)"
            . " OR a.receiver_id IN (SELECT id FROM cdb_recipients WHERE CONCAT(fname,' ',lname) LIKE :q6)"
            . (sl_tableExists('cdb_package_tracking_number') ? " OR EXISTS (SELECT 1 FROM cdb_package_tracking_number p WHERE p.order_id = a.order_id AND p.tracking_number LIKE :q7)" : '') . ')';
        foreach ([1, 2, 3, 4, 5, 6] as $i) $binds[":q$i"] = $like;
        if (sl_tableExists('cdb_package_tracking_number')) $binds[':q7'] = $like;
    }
    if ($status > 0) {
        $where .= ' AND ' . cdp_effectiveStatusSql('a') . ' = ' . $status;
    }
    if ($driver > 0) {
        $where .= ' AND a.driver_id = ' . $driver;
    }
    switch ($period) {
        case 'today': $where .= ' AND a.order_date = CURDATE()'; break;
        case '7d': $where .= ' AND a.order_date >= CURDATE() - INTERVAL 7 DAY'; break;
        case '30d': $where .= ' AND a.order_date >= CURDATE() - INTERVAL 30 DAY'; break;
    }
    switch ($filter) {
        case 'open': $where .= ' AND a.status_courier NOT IN (8,15,16,21,12)'; break;
        case 'delivered': $where .= ' AND a.status_courier IN (8,15)'; break;
        case 'unpaid': $where .= ' AND a.status_invoice IN (2,23) AND a.status_courier <> 21'; break;
        case 'consol': $where .= ' AND a.is_consolidate = 1'; break;
        case 'pickup': $where .= ' AND a.is_pickup = 1'; break;
        case 'customs': $where .= ' AND a.status_courier IN (28,29,30)'; break;
        case 'nocarrier': if (sl_tableExists('cdb_package_tracking_number')) $where .= " AND a.status_courier NOT IN (8,15,21,12) AND NOT EXISTS (SELECT 1 FROM cdb_package_tracking_number p WHERE p.order_id = a.order_id AND p.tracking_number <> '')"; break;
    }
    $count = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE $where", $binds);
    $rows = sl_rows("SELECT a.order_id, a.order_prefix, a.order_no, a.order_date, a.order_datetime, a.order_deli_time, a.sender_id, a.receiver_id, a.recipient_type, a.status_courier, a.status_invoice, a.total_order, a.total_weight, a.driver_id, a.is_pickup, a.is_consolidate, a.order_incomplete, a.is_dangerous_good, a.agency, a.order_pay_mode, a.order_payment_method
        FROM cdb_add_order a WHERE $where ORDER BY a.order_id DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds);
    $senders = sl_usersById(array_map(fn($r) => $r->sender_id, $rows));
    $recips = sl_recipientsFor($rows);
    $carriers = sl_carrierTrackingFor(array_map(fn($r) => $r->order_id, $rows));
    $tracks = [];
    foreach ($rows as $r) $tracks[] = $r->order_prefix . $r->order_no;
    $addr = [];
    if ($tracks) {
        $in = implode(',', array_map(fn($t) => "'" . str_replace("'", "''", $t) . "'", $tracks));
        foreach (sl_rows("SELECT order_track, sender_country, sender_city, recipient_country, recipient_city FROM cdb_address_shipments WHERE order_track IN ($in)") as $a) {
            $addr[$a->order_track] = $addr[$a->order_track] ?? $a;
        }
    }
    $pay = [];
    foreach (sl_rows('SELECT id, label FROM cdb_payment_methods') as $p) $pay[(int) $p->id] = $p->label;
    $statusMap = sl_statusMap();
    cdp_prefetchConsolidations(array_map(fn($r) => $r->order_no, $rows));
    $list = [];
    foreach ($rows as $r) {
        $track = $r->order_prefix . $r->order_no;
        $eff = cdp_getEffectiveStatus($r->order_no, $r->status_courier, $r->is_consolidate);
        $s = $senders[(int) $r->sender_id] ?? null;
        $rc = $recips[(int) $r->order_id] ?? null;
        $a = $addr[$track] ?? null;
        $dest = $a ? trim(($r->recipient_type === 'user' ? $a->sender_city : $a->recipient_city) . ', ' . ($r->recipient_type === 'user' ? $a->sender_country : $a->recipient_country), ', ') : '—';
        [$payLabel, $payCls] = sl_shipPayPill($r->status_invoice);
        $badges = [];
        if ((int) $r->is_pickup) $badges[] = 'Pick-up';
        if ($eff->in_consolidation) $badges[] = 'Consolidated ' . $eff->consolidate_code;
        if ((int) $r->is_dangerous_good) $badges[] = 'Dangerous goods';
        if ((int) $r->order_incomplete === 0 && !(int) $r->is_pickup) $badges[] = $client ? 'Awaiting acceptance' : 'Customer booking';
        $list[] = [
            'id' => (int) $r->order_id, 'track' => $track, 'carrier' => $carriers[(int) $r->order_id]->tracking_number ?? '', 'date' => sl_date($r->order_datetime ?? null) ?: sl_date($r->order_date), 'time' => sl_date($r->order_datetime ?? null, 'H:i'),
            'eta' => cdp_getEffectiveEta((int) $r->order_id, $r->order_no, $r->order_deli_time ?? null, $r->is_consolidate),
            'sender' => sl_userName($s), 'from' => $s->locker ?? '', 'sender_id' => (int) $r->sender_id,
            'name' => $rc ? trim(($rc->fname ?? '') . ' ' . ($rc->lname ?? '')) : sl_userName($s) . ' (self)', 'ini' => sl_initials($rc ? ($rc->fname . ' ' . $rc->lname) : sl_userName($s)),
            'dest' => $dest, 'kg' => (float) $r->total_weight > 0 ? sl_weight($r->total_weight) : '—', 'total' => sl_money($r->total_order),
            'pay' => $payLabel, 'payCls' => $payCls, 'payMethod' => $pay[(int) $r->order_payment_method] ?? '',
            'st' => str_replace('_', ' ', $eff->mod_style ?: ($statusMap[(int) $r->status_courier]->mod_style ?? '—')), 'stCls' => sl_statusClass($eff->status_id), 'stColor' => $eff->color,
            'badges' => $badges, 'selectable' => !cdp_isHandedOver($r->status_courier) && (int) $r->status_courier !== 21,
            'driver_id' => (int) $r->driver_id,
            'acts' => sl_shipmentActions($user, $r, $client),
        ];
    }
    $d['rows'] = $list;
    $d['count'] = $count; $d['page'] = $page; $d['per'] = $per; $d['q'] = $q; $d['status'] = $status; $d['driver'] = $driver; $d['period'] = $period; $d['filter'] = $filter;
    $d['statuses'] = sl_rows('SELECT id, mod_style FROM cdb_styles ORDER BY mod_style');
    $d['drivers'] = $client ? [] : sl_rows('SELECT id, fname, lname FROM cdb_users WHERE userlevel = 3 AND active = 1 ORDER BY fname');
    $d['filters'] = [['f' => 'all', 'label' => 'All'], ['f' => 'open', 'label' => 'Open'], ['f' => 'delivered', 'label' => 'Delivered'], ['f' => 'unpaid', 'label' => 'Unpaid'], ['f' => 'consol', 'label' => 'Consolidated'], ['f' => 'pickup', 'label' => 'Pick-ups']];
    $d['canSelect'] = !$client && $user->cdp_hasPermission('select_multiple_courier');
    $d['canAssign'] = !$client && $user->cdp_hasPermission('assign_drivers');
    $d['canStatus'] = !$client && $user->cdp_hasPermission('track_shipment');
    $d['canLabel'] = $user->cdp_hasPermission('print_label');
    $d['canAdd'] = $client ? $user->cdp_hasPermission('courier_add_client') : $user->cdp_hasPermission('add_shipment');
    $d['canImport'] = !$client && $user->cdp_hasPermission('add_multiple_shipments');
    return $d;
}

/** One shipment with everything the detail, tracking and label pages need; null when missing or out of scope. */
function sl_shipmentLoad(int $id): ?array
{
    $o = sl_row('SELECT * FROM cdb_add_order WHERE order_id = :id AND is_deleted = 0', [':id' => $id]);
    if (!$o || !sl_opsInScope($o)) {
        return null;
    }
    $track = $o->order_prefix . $o->order_no;
    $sender = sl_row('SELECT id, fname, lname, email, phone, locker, avatar FROM cdb_users WHERE id = :id', [':id' => (int) $o->sender_id]);
    $rtype = $o->recipient_type ?? 'recipient';
    $recip = $rtype === 'user'
        ? sl_row('SELECT id, fname, lname, email, phone, locker FROM cdb_users WHERE id = :id', [':id' => (int) $o->receiver_id])
        : sl_row('SELECT id, fname, lname, email, phone FROM cdb_recipients WHERE id = :id', [':id' => (int) $o->receiver_id]);
    if (!$recip) {
        // Imported / legacy rows without a recipient ship to the sender's own address.
        $recip = $sender;
        $rtype = 'user';
    }
    $addr = sl_row('SELECT * FROM cdb_address_shipments WHERE order_track = :t', [':t' => $track]);
    $items = sl_rows('SELECT * FROM cdb_add_order_item WHERE order_id = :id', [':id' => $id]);
    $events = sl_rows('SELECT t.*, s.mod_style, s.color, o.name_off FROM cdb_courier_track t LEFT JOIN cdb_styles s ON s.id = t.status_courier LEFT JOIN cdb_offices o ON o.id = t.office_id WHERE t.order_track = :t ORDER BY t.t_date ASC, t.id ASC', [':t' => $track]);
    $carrier = sl_carrierTrackingFor([$id])[$id] ?? null;
    $eff = cdp_getEffectiveStatus($o->order_no, $o->status_courier, $o->is_consolidate);
    $driver = (int) $o->driver_id > 0 ? sl_row('SELECT id, fname, lname, phone, vehiclecode, enrollment, avatar FROM cdb_users WHERE id = :id', [':id' => (int) $o->driver_id]) : null;
    $files = sl_rows('SELECT id, url, name, date_file, file_type FROM cdb_order_files WHERE order_id = :id AND is_consolidate = 0 ORDER BY id DESC', [':id' => $id]);
    $courier = sl_row('SELECT name_com FROM cdb_courier_com WHERE id = :id', [':id' => (int) $o->order_courier]);
    $deli = sl_row('SELECT delitime FROM cdb_delivery_time WHERE id = :id', [':id' => (int) $o->order_deli_time]);
    $payLabel = sl_row('SELECT label FROM cdb_payment_methods WHERE id = :id', [':id' => (int) $o->order_payment_method]);
    $payMode = sl_row('SELECT name_pay FROM cdb_met_payment WHERE id = :id', [':id' => (int) $o->order_pay_mode]);
    $office = sl_row('SELECT name_off, city FROM cdb_offices WHERE id = :id', [':id' => (int) $o->origin_off]);
    $charge = sl_row('SELECT * FROM cdb_charges_order WHERE order_id = :id ORDER BY id_charge DESC', [':id' => $id]);
    $gateway = sl_row('SELECT * FROM cdb_payments_gateway WHERE order_track = :t ORDER BY id DESC', [':t' => $track]);
    $eta = cdp_getEffectiveEta($id, $o->order_no, $o->order_deli_time, $o->is_consolidate);
    return compact('o', 'track', 'sender', 'recip', 'rtype', 'addr', 'items', 'events', 'carrier', 'eff', 'driver', 'files', 'courier', 'deli', 'payLabel', 'payMode', 'office', 'charge', 'gateway', 'eta');
}

/** 5-step customer progress (Registered, At warehouse, In transit, Arrived, Delivered) from the air stage. */
function sl_shipmentStep(int $statusId, string $statusName): int
{
    if (cdp_isHandedOver($statusId)) return 5;
    $air = cdp_airStageIndex($statusId, $statusName);
    if ($air !== null) {
        if ($air >= 12) return 4;
        if ($air >= 5) return 3;
        if ($air >= 1) return 2;
        return 1;
    }
    $p = cdp_trackProgress($statusId, $statusName);
    return [1, 2, 3, 4, 4, 4, 5][$p['index']] ?? 1;
}

function sl_shipmentDetailData($user, $core, int $id): array
{
    $s = sl_shipmentLoad($id);
    if (!$s) {
        return ['missing' => true];
    }
    $o = $s['o'];
    $client = sl_userIs('client');
    $statusName = str_replace('_', ' ', $s['eff']->mod_style);
    $step = sl_shipmentStep((int) $s['eff']->status_id, $statusName);
    $from = $s['addr'] ? trim($s['addr']->sender_city . ', ' . $s['addr']->sender_country, ', ') : ($s['office']->city ?? '');
    $to = $s['addr'] ? trim(($s['rtype'] === 'user' ? $s['addr']->sender_city : $s['addr']->recipient_city) . ', ' . ($s['rtype'] === 'user' ? $s['addr']->sender_country : $s['addr']->recipient_country), ', ') : '';
    $items = [];
    $kg = 0;
    foreach ($s['items'] as $it) {
        $kg += (float) $it->order_item_weight * max(1, (float) $it->order_item_quantity);
        $items[] = ['qty' => (float) $it->order_item_quantity == (int) $it->order_item_quantity ? (int) $it->order_item_quantity : (float) $it->order_item_quantity, 'desc' => $it->order_item_description, 'kg' => (float) $it->order_item_weight > 0 ? sl_weight($it->order_item_weight, 2) : '—', 'val' => sl_money($it->order_item_declared_value), 'dims' => (float) $it->order_item_length > 0 ? (float) $it->order_item_length . ' × ' . (float) $it->order_item_width . ' × ' . (float) $it->order_item_height : '—'];
    }
    $events = [];
    $rev = array_reverse($s['events']);
    foreach ($rev as $i => $e) {
        $events[] = ['cls' => $i === 0 ? 'now' : 'done', 'ic' => $i === 0 ? 'i-pin' : 'i-check', 't' => str_replace('_', ' ', $e->mod_style ?: 'Update'), 's' => sl_date($e->t_date, 'M j, Y H:i') . ($e->name_off ? ' · ' . $e->name_off : '') . ($e->comments ? ' · ' . $e->comments : '')];
    }
    [$payLabel, $payCls] = sl_shipPayPill($o->status_invoice);
    $d = [
        'id' => $id, 'track' => $s['track'], 'status' => $statusName, 'stCls' => sl_statusClass($s['eff']->status_id), 'stColor' => $s['eff']->color,
        'mode' => (int) $o->order_item_category === 27 ? 'Sea' : 'Air', 'carrier' => $s['courier']->name_com ?? '', 'carrierNo' => $s['carrier']->tracking_number ?? '',
        'recipient' => $s['recip'] ? trim($s['recip']->fname . ' ' . $s['recip']->lname) : '—', 'from' => $from ?: '—', 'to' => $to ?: '—',
        'eta' => $s['eta'] !== 'N/A' && $s['eta'] !== '' ? (strtotime($s['eta']) ? sl_date($s['eta']) : $s['eta']) : 'Not set',
        'step' => $step, 'steps' => ['Registered', 'At warehouse', 'In transit', 'Arrived', 'Delivered'],
        'items' => $items, 'kg' => (float) $o->total_weight > 0 ? sl_weight($o->total_weight) : sl_weight($kg),
        'sender' => ['name' => sl_userName($s['sender']), 'ini' => sl_initials(sl_userName($s['sender'])), 'mail' => $s['sender']->email ?? '', 'phone' => $s['sender']->phone ?? '—', 'locker' => $s['sender']->locker ?? '', 'id' => (int) $o->sender_id, 'addr' => $s['addr'] ? trim(implode(', ', array_filter([$s['addr']->sender_address, $s['addr']->sender_city, $s['addr']->sender_state])), ', ') : '—'],
        'recip' => ['name' => $s['recip'] ? trim($s['recip']->fname . ' ' . $s['recip']->lname) : '—', 'ini' => sl_initials($s['recip'] ? $s['recip']->fname . ' ' . $s['recip']->lname : '?'), 'mail' => $s['recip']->email ?? '', 'phone' => $s['recip']->phone ?? '—', 'addr' => $s['addr'] ? trim(implode(', ', array_filter($s['rtype'] === 'user' ? [$s['addr']->sender_address, $s['addr']->sender_city, $s['addr']->sender_state] : [$s['addr']->recipient_address, $s['addr']->recipient_city, $s['addr']->recipient_state])), ', ') : '—'],
        'files' => array_map(fn($f) => ['name' => $f->name, 'url' => $f->url, 'img' => in_array(strtolower((string) $f->file_type), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)], $s['files']),
        'total' => sl_money($o->total_order), 'pay' => $payLabel, 'payCls' => $payCls, 'payMethod' => $s['payMode']->name_pay ?? ($s['payLabel']->label ?? '—'), 'terms' => $s['payLabel']->label ?? '',
        'payRef' => $s['gateway']->payment_transaction ?? ($s['charge']->number_reference ?? '—'), 'paidOn' => $o->payment_date ? sl_date($o->payment_date) : ($s['charge'] ? sl_date($s['charge']->charge_date) : '—'), 'due' => $o->due_date ? sl_date($o->due_date) : '—',
        'events' => $events, 'eventCount' => count($events),
        'driver' => $s['driver'] ? ['name' => sl_userName($s['driver']), 'ini' => sl_initials(sl_userName($s['driver'])), 'veh' => trim(($s['driver']->vehiclecode ?? '') . ' ' . ($s['driver']->enrollment ?? '')) ?: 'No vehicle on file', 'phone' => $s['driver']->phone ?? '', 'id' => (int) $s['driver']->id] : null,
        'notes' => (string) ($o->notes ?: ($o->courier_notes ?? '')), 'dangerous' => (int) ($o->is_dangerous_good ?? 0) === 1,
        'consol' => $s['eff']->in_consolidation ? ['code' => $s['eff']->consolidate_code, 'id' => $s['eff']->consolidate_id] : null,
        'pickupCode' => sl_pickupCodeInfo('air', [$id]),
        'client' => $client,
        'canTrack' => !$client && $user->cdp_hasPermission('track_shipment') && !in_array((int) $o->status_courier, [21, 12], true),
        'canEdit' => !$client && (int) $o->order_incomplete === 1 && !cdp_isHandedOver($o->status_courier) && $user->cdp_hasPermission('edit_shipment'),
        'canLabel' => $user->cdp_hasPermission('print_label'), 'canPrint' => $user->cdp_hasPermission('print_shipment'),
        'canVerify' => !$client && (int) $o->status_invoice === 3 && $user->cdp_hasPermission('verify_payments'),
        'canPay' => $client && (int) $o->status_invoice === 2,
        'canAssign' => !$client && $user->cdp_hasPermission('assign_drivers'),
        'statuses' => $client ? [] : sl_rows('SELECT id, mod_style FROM cdb_styles ORDER BY mod_style'),
        'offices' => $client ? [] : sl_rows('SELECT id, name_off FROM cdb_offices ORDER BY name_off'),
        'countries' => $client ? [] : sl_rows('SELECT id, name FROM cdb_countries ORDER BY name'),
        'drivers' => $client ? [] : sl_rows('SELECT id, fname, lname FROM cdb_users WHERE userlevel = 3 AND active = 1 ORDER BY fname'),
        'office' => (int) $o->origin_off, 'statusId' => (int) $o->status_courier,
        'publicUrl' => 'track.php?order_track=' . rawurlencode($s['track']),
    ];
    return $d;
}

/** Shipment tracking (advanced) page. $id = 0 shows the finder with recent shipments. */
function sl_shipmentTrackingData($user, $core, int $id, string $q = ''): array
{
    $d = ['q' => $q, 'id' => 0, 'flash' => ''];
    if ($id <= 0 && $q !== '') {
        $scope = sl_opsScope('a');
        $like = '%' . $q . '%';
        $hit = sl_row("SELECT a.order_id FROM cdb_add_order a WHERE a.is_deleted = 0 $scope AND (CONCAT(a.order_prefix, a.order_no) LIKE :q1 OR a.order_no LIKE :q2"
            . (sl_tableExists('cdb_package_tracking_number') ? ' OR EXISTS (SELECT 1 FROM cdb_package_tracking_number p WHERE p.order_id = a.order_id AND p.tracking_number LIKE :q3)' : '')
            . ' OR EXISTS (SELECT 1 FROM cdb_courier_track t WHERE t.order_track = CONCAT(a.order_prefix, a.order_no) AND (t.awb_no LIKE :q4 OR t.flight_no LIKE :q5))) ORDER BY a.order_id DESC',
            sl_tableExists('cdb_package_tracking_number') ? [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like] : [':q1' => $like, ':q2' => $like, ':q4' => $like, ':q5' => $like]);
        if ($hit) {
            $id = (int) $hit->order_id;
        } else {
            $d['flash'] = 'No shipment matches "' . $q . '".';
        }
    }
    $scope = sl_opsScope('a');
    $recent = sl_rows("SELECT a.order_id, a.order_prefix, a.order_no, a.status_courier, a.order_date, a.sender_id FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier NOT IN (8,15,21) $scope ORDER BY a.order_id DESC LIMIT 8");
    $senders = sl_usersById(array_map(fn($r) => $r->sender_id, $recent));
    $d['recent'] = array_map(fn($r) => ['id' => (int) $r->order_id, 'track' => $r->order_prefix . $r->order_no, 'st' => sl_statusLabel($r->status_courier), 'pc' => sl_statusClass($r->status_courier), 'who' => sl_userName($senders[(int) $r->sender_id] ?? null), 'date' => sl_date($r->order_date)], $recent);
    if ($id <= 0) {
        return $d;
    }
    $s = sl_shipmentLoad($id);
    if (!$s) {
        $d['flash'] = 'Shipment not found.';
        return $d;
    }
    $o = $s['o'];
    $d['id'] = $id;
    $d['track'] = $s['track'];
    $statusName = str_replace('_', ' ', $s['eff']->mod_style);
    $journey = cdp_airJourney((int) $s['eff']->status_id, $statusName, $s['events'], $core->site_name ?? '');
    $idx = (int) $journey['index'];
    $isSea = (int) $o->order_item_category === 27;
    $d['status'] = $statusName;
    $d['stCls'] = sl_statusClass($s['eff']->status_id);
    $last = $s['events'] ? end($s['events']) : null;
    $d['ago'] = $last ? sl_ago($last->t_date) : 'no events yet';
    $d['cust'] = sl_userName($s['sender']);
    $d['custId'] = (int) $o->sender_id;
    $d['recipient'] = $s['recip'] ? trim($s['recip']->fname . ' ' . $s['recip']->lname) : '—';
    // Sibling packages: same sender, still open.
    $sib = sl_rows('SELECT order_id, order_prefix, order_no, status_courier, total_weight FROM cdb_add_order WHERE sender_id = :s AND is_deleted = 0 AND status_courier NOT IN (8,15,21) ORDER BY order_id DESC LIMIT 6', [':s' => (int) $o->sender_id]);
    $d['pkgs'] = array_map(fn($p) => ['id' => (int) $p->order_id, 'track' => $p->order_prefix . $p->order_no, 'cls' => (int) $p->order_id === $id ? 'on' : '', 'what' => (float) $p->total_weight > 0 ? sl_weight($p->total_weight) : 'weight pending', 'st' => sl_statusLabel($p->status_courier), 'pc' => sl_statusClass($p->status_courier)], $sib);
    $d['pkN'] = count($sib);
    $d['consol'] = $s['eff']->in_consolidation ? $s['eff']->consolidate_code : '';
    // ETA and risk
    $etaRaw = $s['eta'];
    $etaTs = ($etaRaw !== 'N/A' && $etaRaw !== '') ? strtotime($etaRaw) : false;
    $d['eta'] = $etaTs ? date('D, M j', $etaTs) : ($etaRaw !== 'N/A' && $etaRaw !== '' ? $etaRaw : 'Not set');
    $delivered = cdp_isHandedOver($o->status_courier);
    if ($delivered) { $d['risk'] = 'None'; $d['riskSty'] = 'color:var(--green-ink)'; $d['riskWhy'] = 'Handed over to the customer'; $d['conf'] = 100; }
    elseif ($etaTs && $etaTs < strtotime('today')) { $d['risk'] = 'High'; $d['riskSty'] = 'color:var(--red-ink)'; $d['riskWhy'] = 'ETA passed ' . floor((strtotime('today') - $etaTs) / 86400) . ' days ago'; $d['conf'] = 35; }
    elseif ($last && strtotime($last->t_date) < strtotime('-5 days') && !$delivered) { $d['risk'] = 'Medium'; $d['riskSty'] = 'color:var(--yellow-ink, #8a6d00)'; $d['riskWhy'] = 'No scan for ' . sl_ago($last->t_date); $d['conf'] = 60; }
    else { $d['risk'] = 'Low'; $d['riskSty'] = 'color:var(--green-ink)'; $d['riskWhy'] = 'Moving as planned'; $d['conf'] = 85; }
    $d['confW'] = 'width:' . (int) $d['conf'] . '%';
    // Lane statistics for "from N past shipments on this lane"
    $d['laneN'] = (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE is_deleted = 0 AND status_courier IN (8,15) AND order_item_category = :c', [':c' => (int) $o->order_item_category]);
    $sources = sl_tableExists('cdb_courier_track') && sl_columnExists('cdb_courier_track', 'source') ? sl_rows('SELECT DISTINCT COALESCE(NULLIF(source, ""), "staff") AS src FROM cdb_courier_track WHERE order_track = :t', [':t' => $s['track']]) : [];
    $d['syncN'] = max(1, count($sources));
    $d['syncList'] = $sources ? implode(', ', array_map(fn($r) => ucfirst($r->src), $sources)) : 'Staff updates';
    $d['syncAgo'] = $d['ago'];
    // Legs
    $legs = [];
    if ($isSea) {
        $p = cdp_trackProgress((int) $s['eff']->status_id, $statusName);
        foreach ($p['steps'] as $i => $st) {
            $legs[] = ['t' => $st['label'], 'icon' => $i < 2 ? 'i-warehouse' : ($i < 3 ? 'i-ship' : ($i < 5 ? 'i-layers' : 'i-box')), 'cls' => $i < $p['index'] ? 'ok' : ($i === $p['index'] ? 'warn' : '')];
        }
        $d['legNote'] = 'Sea freight · ' . $p['steps'][$p['index']]['label'];
    } else {
        $groups = [['t' => 'US warehouse', 'icon' => 'i-warehouse', 'from' => 0, 'to' => 4], ['t' => 'Flight to London', 'icon' => 'i-plane', 'from' => 5, 'to' => 8], ['t' => 'London hub', 'icon' => 'i-layers', 'from' => 9, 'to' => 10], ['t' => 'Flight to Accra', 'icon' => 'i-plane', 'from' => 11, 'to' => 12], ['t' => 'Customs', 'icon' => 'i-shield', 'from' => 13, 'to' => 14], ['t' => 'Ready / delivery', 'icon' => 'i-box', 'from' => 15, 'to' => 15]];
        foreach ($groups as $g) {
            $cls = $delivered || $idx > $g['to'] ? 'ok' : (($idx >= $g['from'] && $idx <= $g['to']) ? 'warn' : '');
            $legs[] = ['t' => $g['t'], 'icon' => $g['icon'], 'cls' => $cls];
        }
        $d['legNote'] = 'Air · ' . ($journey['steps'][$idx]['label'] ?? $statusName);
    }
    $d['legs'] = $legs;
    // Map (schematic, three fixed points of the lane)
    $pins = [['l' => $journey['route']['origin']['label'] ?? 'Origin', 'x' => 150, 'y' => 120], ['l' => $journey['route']['transit']['label'] ?? 'Hub', 'x' => 540, 'y' => 70], ['l' => $journey['route']['destination']['label'] ?? 'Destination', 'x' => 880, 'y' => 240]];
    $frac = $delivered ? 1 : min(1, max(0.02, $idx / 15));
    $d['arc'] = 'M150 120 Q 345 20 540 70 Q 760 120 880 240';
    $pt = function (float $t) {
        if ($t <= .5) { $u = $t * 2; $x = (1 - $u) * (1 - $u) * 150 + 2 * (1 - $u) * $u * 345 + $u * $u * 540; $y = (1 - $u) * (1 - $u) * 120 + 2 * (1 - $u) * $u * 20 + $u * $u * 70; }
        else { $u = ($t - .5) * 2; $x = (1 - $u) * (1 - $u) * 540 + 2 * (1 - $u) * $u * 760 + $u * $u * 880; $y = (1 - $u) * (1 - $u) * 70 + 2 * (1 - $u) * $u * 120 + $u * $u * 240; }
        return [round($x, 1), round($y, 1)];
    };
    $pathDone = '';
    for ($t = 0; $t <= $frac + 0.0001; $t += 0.02) { [$x, $y] = $pt(min($t, $frac)); $pathDone .= ($pathDone === '' ? 'M' : ' L') . $x . ' ' . $y; }
    $d['arcDone'] = $pathDone;
    [$d['vx'], $d['vy']] = $pt($frac);
    $d['pins'] = array_map(fn($p, $i) => $p + ['r' => 7, 'f' => ($i / 2) <= $frac ? '#E2632A' : '#1E1D1A'], $pins, array_keys($pins));
    $d['vIcon'] = $isSea ? 'i-ship' : ($idx >= 13 || $delivered ? 'i-truck' : ($idx >= 5 ? 'i-plane' : 'i-warehouse'));
    $d['vName'] = $journey['flights'] ? 'Flight ' . implode(' / ', array_slice(array_unique(array_filter($journey['flights'])), 0, 2)) : ($journey['awb'] ? 'AWB ' . $journey['awb'] : ($s['courier']->name_com ?? 'Carrier'));
    $d['vSub'] = $s['carrier']->tracking_number ?? 'No carrier number yet';
    $d['vNow'] = $journey['steps'][$idx]['label'] ?? $statusName;
    // Milestones
    $ms = [];
    foreach (array_reverse($s['events']) as $i => $e) {
        $ms[] = ['cls' => $i === 0 ? 'now' : 'done', 'icon' => $i === 0 ? 'i-pin' : 'i-check', 'date' => sl_date($e->t_date), 'time' => date('H:i', strtotime($e->t_date)), 't' => cdp_airEventLabel($e, $core->site_name ?? ''), 'where' => trim(implode(' · ', array_filter([$e->name_off, $e->t_city, $e->t_dest, $e->comments, $e->flight_no ? 'Flight ' . $e->flight_no : '', $e->awb_no ? 'AWB ' . $e->awb_no : '']))) ?: '—', 'src' => ucfirst((string) ($e->source ?? '') ?: 'staff')];
    }
    $d['ms'] = $ms;
    $d['msN'] = count($ms);
    // Customs
    $cusIdx = $isSea ? null : $idx;
    $cusSteps = [['t' => 'Arrived in Accra', 'at' => 12], ['t' => 'Documents lodged', 'at' => 13], ['t' => 'Inspection / duties', 'at' => 13], ['t' => 'Cleared / released', 'at' => 14]];
    $d['cus'] = array_map(fn($c) => ['t' => $c['t'], 'cls' => $delivered || ($cusIdx !== null && $cusIdx > $c['at']) ? 'done' : (($cusIdx !== null && $cusIdx === $c['at']) ? 'now' : '')], $cusSteps);
    $d['cusNote'] = $delivered || $idx >= 15 ? 'Cleared. Duties were settled with the shipment invoice.' : ($idx >= 13 ? 'At customs now. We update this stage as soon as the broker reports.' : 'Customs starts when the flight lands in Accra.');
    $priv = json_decode((string) sl_slSetting('tracking_public_privacy', '{}'), true) ?: [];
    $d['cusVis'] = !empty($priv['customs']);
    // Proof of delivery
    $pod = $delivered;
    $d['podSt'] = $pod ? 'Delivered' : 'Pending';
    $d['podPc'] = $pod ? 'delivered' : 'pending';
    $d['podWho'] = $pod ? ('Received by ' . ($o->person_receives ?: 'the customer') . ($o->pod_at ?? null ? ' · ' . sl_date($o->pod_at, 'M j, H:i') : '')) : 'Signature captured at hand-over';
    $d['podPhoto'] = $pod && !empty($o->photo_delivered) ? $o->photo_delivered : (!empty($o->pod_photo ?? '') ? $o->pod_photo : '');
    $pc = sl_pickupCodeInfo('air', [$id]);
    $d['qrNote'] = !empty($pc['verified']) ? 'Pickup code verified at hand-over' : (!empty($pc['live']) ? 'Pickup code sent, not yet used' : 'Pickup code is sent when the parcel is ready');
    // Alerts
    $alerts = [];
    if ($d['risk'] === 'High') $alerts[] = ['lvl' => 'bad', 'icon' => 'i-alert', 't' => 'Past the estimated arrival', 'd' => $d['riskWhy']];
    if (in_array((int) $o->status_invoice, [2, 23], true) && !$delivered) $alerts[] = ['lvl' => 'warn', 'icon' => 'i-dollar', 't' => 'Payment outstanding', 'd' => sl_money($o->total_order) . ' due' . ($o->due_date ? ' by ' . sl_date($o->due_date) : '')];
    if (empty($s['carrier']->tracking_number)) $alerts[] = ['lvl' => 'warn', 'icon' => 'i-tag', 't' => 'No carrier tracking number', 'd' => 'Add it from the detail page so carrier scans can be matched'];
    if (in_array((int) $o->status_courier, [28, 29], true)) $alerts[] = ['lvl' => 'bad', 'icon' => 'i-ban', 't' => 'On hold', 'd' => (int) $o->status_courier === 29 ? 'Customer account on hold' : 'Held at the warehouse'];
    if ((int) ($o->is_dangerous_good ?? 0) === 1) $alerts[] = ['lvl' => 'warn', 'icon' => 'i-alert', 't' => 'Dangerous goods', 'd' => 'Needs an approved declaration before the flight'];
    $disputes = sl_tableExists('cdb_tracking_disputes') ? sl_rows('SELECT reason, raised_at FROM cdb_tracking_disputes WHERE order_id = :id AND resolved_at IS NULL ORDER BY id DESC', [':id' => $id]) : [];
    foreach ($disputes as $x) $alerts[] = ['lvl' => 'warn', 'icon' => 'i-message', 't' => 'Disputed: ' . $x->reason, 'd' => 'Raised ' . sl_ago($x->raised_at)];
    if (!$alerts) $alerts[] = ['lvl' => 'ok', 'icon' => 'i-check', 't' => 'No exceptions', 'd' => 'Nothing needs attention on this shipment'];
    $d['alerts'] = $alerts;
    // Delivery attempts: events with On Route / No Picked up / Delivered
    $tries = [];
    foreach (array_reverse($s['events']) as $e) {
        if (in_array((int) $e->status_courier, [7, 16, 8, 15], true)) {
            $ok = in_array((int) $e->status_courier, [8, 15], true);
            $tries[] = ['st' => $ok ? 'Delivered' : ((int) $e->status_courier === 16 ? 'Failed' : 'Out for delivery'), 'pc' => $ok ? 'delivered' : ((int) $e->status_courier === 16 ? 'overdue' : 'onway'), 't' => sl_date($e->t_date, 'M j, H:i'), 'd' => $e->comments ?: ($e->name_off ?: '')];
        }
    }
    $d['tries'] = $tries;
    // Notifications sent
    $notifs = [];
    if (sl_tableExists('cdb_message_log')) {
        foreach (sl_rows('SELECT channel, subject, created_at, status FROM cdb_message_log WHERE entity_type IN ("shipment","order") AND entity_id = :id ORDER BY id DESC LIMIT 8', [':id' => (string) $id]) as $m) {
            $notifs[] = ['cls' => $m->status === 'sent' ? 'ok' : 'warn', 'icon' => $m->channel === 'email' ? 'i-mail' : ($m->channel === 'whatsapp' ? 'i-message' : 'i-phone'), 't' => ucfirst($m->channel) . ($m->subject ? ' · ' . $m->subject : ''), 'at' => sl_ago($m->created_at)];
        }
    }
    foreach (sl_rows('SELECT n.notification_description, n.notification_date FROM cdb_notifications n WHERE n.order_id = :id AND n.shipping_type = 1 ORDER BY n.notification_id DESC LIMIT 6', [':id' => $id]) as $n) {
        $notifs[] = ['cls' => 'ok', 'icon' => 'i-bell', 't' => $n->notification_description, 'at' => sl_ago($n->notification_date)];
    }
    $d['notifs'] = $notifs;
    // Public link and privacy
    $d['pubUrl'] = rtrim((string) ($core->site_url ?? ''), '/') . '/track.php?order_track=' . rawurlencode($s['track']);
    $d['priv'] = [
        ['k' => 'name', 't' => 'Recipient name', 'on' => !empty($priv['name'])],
        ['k' => 'address', 't' => 'Delivery address', 'on' => !empty($priv['address'])],
        ['k' => 'items', 't' => 'Items and value', 'on' => !empty($priv['items'])],
        ['k' => 'customs', 't' => 'Customs stage', 'on' => !empty($priv['customs'])],
    ];
    $d['exps'] = array_map(fn($x) => ['v' => $x, 'label' => $x === 'never' ? 'Never' : $x . ' days', 'cls' => (string) ($priv['expires'] ?? '30') === $x ? 'on' : ''], ['7', '30', '90', 'never']);
    $d['dis'] = ['Not delivered', 'Wrong status', 'Damaged', 'Wrong recipient', 'Other'];
    $d['client'] = sl_userIs('client');
    $d['canTrack'] = !$d['client'] && $user->cdp_hasPermission('track_shipment') && !in_array((int) $o->status_courier, [21, 12], true);
    $d['canManage'] = !$d['client'] && $user->cdp_hasPermission('view_tools');
    $d['statuses'] = $d['canTrack'] ? sl_rows('SELECT id, mod_style FROM cdb_styles ORDER BY mod_style') : [];
    $d['offices'] = $d['canTrack'] ? sl_rows('SELECT id, name_off FROM cdb_offices ORDER BY name_off') : [];
    $d['countries'] = $d['canTrack'] ? sl_rows('SELECT id, name FROM cdb_countries ORDER BY name') : [];
    $d['office'] = (int) $o->origin_off;
    $d['statusId'] = (int) $o->status_courier;
    $d['cov'] = ['Live status from every scan', 'Predicted delivery date', 'Delay risk', 'Flight and AWB numbers', 'Customs stage', 'Proof of delivery', 'Delivery attempts', 'Notifications log', 'Public tracking link', 'Privacy controls', 'Status disputes', 'Sibling packages', 'Pickup code state', 'Exceptions and holds', 'Carrier number check'];
    return $d;
}

/** Delivery tracking board: shipments out for delivery (status 7) with driver and last scan. */
function sl_deliveryTrackingData($user, $core, int $sel, string $filter, string $q): array
{
    $scope = sl_opsScope('a');
    $where = "a.is_deleted = 0 $scope";
    $binds = [];
    switch ($filter) {
        case 'today': $where .= ' AND a.status_courier IN (8,15) AND EXISTS (SELECT 1 FROM cdb_courier_track t WHERE t.order_track = CONCAT(a.order_prefix, a.order_no) AND t.status_courier IN (8,15) AND DATE(t.t_date) = CURDATE())'; break;
        case 'unassigned': $where .= ' AND a.status_courier IN (6,32,7) AND a.driver_id = 0'; break;
        case 'ready': $where .= ' AND a.status_courier IN (6,32)'; break;
        case 'failed': $where .= ' AND a.status_courier = 16'; break;
        default: $where .= ' AND a.status_courier = 7';
    }
    if ($q !== '') {
        $where .= " AND (CONCAT(a.order_prefix, a.order_no) LIKE :q1 OR a.sender_id IN (SELECT id FROM cdb_users WHERE CONCAT(fname,' ',lname) LIKE :q2 OR locker LIKE :q3) OR a.driver_id IN (SELECT id FROM cdb_users WHERE CONCAT(fname,' ',lname) LIKE :q4))";
        $binds = [':q1' => "%$q%", ':q2' => "%$q%", ':q3' => "%$q%", ':q4' => "%$q%"];
    }
    $rows = sl_rows("SELECT a.order_id, a.order_prefix, a.order_no, a.sender_id, a.receiver_id, a.recipient_type, a.status_courier, a.driver_id, a.total_order, a.total_weight, a.order_courier, a.order_item_category, a.assigned_at FROM cdb_add_order a WHERE $where ORDER BY a.assigned_at DESC, a.order_id DESC LIMIT 40", $binds);
    $outN = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 7 $scope");
    $d = ['q' => $q, 'filter' => $filter, 'outN' => $outN, 'client' => sl_userIs('client')];
    $d['filters'] = [['f' => 'route', 'label' => 'On route', 'cls' => $filter === 'route' ? 'on' : ''], ['f' => 'ready', 'label' => 'Ready to go', 'cls' => $filter === 'ready' ? 'on' : ''], ['f' => 'unassigned', 'label' => 'No rider', 'cls' => $filter === 'unassigned' ? 'on' : ''], ['f' => 'today', 'label' => 'Delivered today', 'cls' => $filter === 'today' ? 'on' : ''], ['f' => 'failed', 'label' => 'Failed', 'cls' => $filter === 'failed' ? 'on' : '']];
    $senders = sl_usersById(array_map(fn($r) => $r->sender_id, $rows));
    $drivers = sl_usersById(array_map(fn($r) => $r->driver_id, $rows));
    $recips = sl_recipientsFor($rows);
    $tracks = array_map(fn($r) => $r->order_prefix . $r->order_no, $rows);
    $lastScan = $addr = [];
    if ($tracks) {
        $in = implode(',', array_map(fn($t) => "'" . str_replace("'", "''", $t) . "'", $tracks));
        foreach (sl_rows("SELECT t.order_track, t.t_date, t.comments, t.t_city, s.mod_style FROM cdb_courier_track t LEFT JOIN cdb_styles s ON s.id = t.status_courier WHERE t.order_track IN ($in) ORDER BY t.t_date DESC, t.id DESC") as $t) {
            $lastScan[$t->order_track] = $lastScan[$t->order_track] ?? $t;
        }
        foreach (sl_rows("SELECT * FROM cdb_address_shipments WHERE order_track IN ($in)") as $a) $addr[$a->order_track] = $addr[$a->order_track] ?? $a;
    }
    if ($sel <= 0 && $rows) $sel = (int) $rows[0]->order_id;
    $cards = [];
    foreach ($rows as $r) {
        $track = $r->order_prefix . $r->order_no;
        $rc = $recips[(int) $r->order_id] ?? null;
        $s = $senders[(int) $r->sender_id] ?? null;
        $a = $addr[$track] ?? null;
        $ls = $lastScan[$track] ?? null;
        $to = $a ? trim(($r->recipient_type === 'user' ? $a->sender_address : $a->recipient_address) . ', ' . ($r->recipient_type === 'user' ? $a->sender_city : $a->recipient_city), ', ') : '—';
        $st = (int) $r->status_courier;
        $open = (int) $r->order_id === $sel;
        $stage = $st === 7 ? 2 : (in_array($st, [8, 15], true) ? 3 : ($st === 16 ? 2 : 1));
        $w = [1 => 15, 2 => 60, 3 => 100][$stage];
        $cards[] = [
            'id' => (int) $r->order_id, 'track' => $track, 'cls' => $open ? 'on' : '', 'open' => $open, 'closed' => !$open,
            'st' => sl_statusLabel($st), 'pc' => sl_statusClass($st),
            'eta' => $ls ? date('H:i', strtotime($ls->t_date)) : '—', 'etaLabel' => $ls ? 'Last scan' : 'No scan yet', 'left' => $ls ? sl_ago($ls->t_date) : '',
            'pcls' => $st === 7 ? 'moving' : (in_array($st, [8, 15], true) ? 'done' : ($st === 16 ? 'idle' : 'todo')), 'w' => 'width:' . $w . '%', 'l' => 'left:' . $w . '%', 'tkIcon' => $st === 7 ? 'i-truck' : 'i-box', 'pAria' => 'Stage ' . $stage . ' of 3',
            'from' => $core->site_name ?? 'Hub', 'fromSub' => $a ? trim($a->sender_city . ', ' . $a->sender_country, ', ') : 'Warehouse', 'to' => $rc ? trim($rc->fname . ' ' . $rc->lname) : '—', 'toSub' => $to, 'toShort' => $a ? ($r->recipient_type === 'user' ? $a->sender_city : $a->recipient_city) : '—',
            'cust' => sl_userName($s), 'custId' => (int) $r->sender_id, 'locker' => $s->locker ?? '—', 'phone' => $rc->phone ?? ($s->phone ?? ''),
            'driver' => sl_userName($drivers[(int) $r->driver_id] ?? null),
        ];
    }
    $d['cards'] = $cards;
    $d['ago'] = 'just now';
    if ($sel > 0) {
        $s = sl_shipmentLoad($sel);
        if ($s) {
            $o = $s['o'];
            $steps = [];
            $n = count($s['events']);
            foreach (array_reverse($s['events']) as $i => $e) {
                $steps[] = ['cls' => $i === 0 ? 'now' : 'done', 'icon' => $i === 0 ? 'i-pin' : 'i-check', 'date' => sl_date($e->t_date), 'time' => date('H:i', strtotime($e->t_date)), 'what' => str_replace('_', ' ', $e->mod_style ?: 'Update'), 'where' => trim(implode(' · ', array_filter([$e->name_off, $e->t_city, $e->comments]))) ?: '—'];
            }
            if (!cdp_isHandedOver($o->status_courier)) {
                $steps = array_merge([['cls' => 'todo', 'icon' => 'i-box', 'date' => 'Next', 'time' => '', 'what' => 'Delivered to the customer', 'where' => 'Pickup code or signature at the door']], $steps);
            }
            $pc = sl_pickupCodeInfo('air', [$sel]);
            $dr = $s['driver'];
            $todayN = $dr ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE driver_id = :d AND status_courier IN (8,15) AND is_deleted = 0 AND EXISTS (SELECT 1 FROM cdb_courier_track t WHERE t.order_track = CONCAT(order_prefix, order_no) AND t.status_courier IN (8,15) AND DATE(t.t_date) = CURDATE())', [':d' => (int) $dr->id]) : 0;
            $openN = $dr ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE driver_id = :d AND status_courier = 7 AND is_deleted = 0', [':d' => (int) $dr->id]) : 0;
            $lastDr = $s['events'] ? end($s['events']) : null;
            $d['sel'] = [
                'id' => $s['track'], 'oid' => $sel, 'courier' => $s['courier']->name_com ?? '—', 'type' => (int) $o->order_item_category === 27 ? 'Sea freight' : 'Air freight', 'pcs' => max(1, array_sum(array_map(fn($i) => (int) $i->order_item_quantity, $s['items']))), 'kg' => (float) $o->total_weight > 0 ? sl_weight($o->total_weight) : '—', 'fee' => sl_money($o->total_order),
                'code' => !empty($pc['verified']) ? 'Verified' : (!empty($pc['live']) ? 'Sent to customer' : 'Not issued'),
                'rider' => $dr ? sl_userName($dr) : 'Unassigned', 'rini' => $dr ? sl_initials(sl_userName($dr)) : '?', 'rid' => $dr ? (int) $dr->id : 0, 'rstat' => $openN ? $openN . ' parcels on route' : 'No parcels on route', 'veh' => $dr->vehiclecode ?? '—', 'plate' => $dr->enrollment ?? '—', 'today' => $todayN . ' delivered', 'phone' => $dr->phone ?? '',
                'mode' => 'Last scan ' . ($lastDr ? sl_ago($lastDr->t_date) : 'never') . ' · ' . count($s['events']) . ' events', 'status' => str_replace('_', ' ', $s['eff']->mod_style),
            ];
            $d['steps'] = $steps;
            $d['stops'] = max(0, $openN - 1);
            $d['km'] = '—';
            $d['kmh'] = '—';
            $d['ago'] = $lastDr ? sl_ago($lastDr->t_date) : 'no scans';
            $frac = cdp_isHandedOver($o->status_courier) ? 1 : ((int) $o->status_courier === 7 ? .6 : .15);
            $pts = [[80, 360], [300, 160], [520, 120], [820, 60]];
            $all = implode(' ', array_map(fn($p) => $p[0] . ',' . $p[1], $pts));
            $done = [];
            $segs = count($pts) - 1;
            $reach = $frac * $segs;
            for ($i = 0; $i <= $segs; $i++) {
                if ($i <= $reach) { $done[] = $pts[$i][0] . ',' . $pts[$i][1]; }
                else { $k = $i - 1; $t = $reach - $k; $x = $pts[$k][0] + ($pts[$i][0] - $pts[$k][0]) * $t; $y = $pts[$k][1] + ($pts[$i][1] - $pts[$k][1]) * $t; $done[] = round($x) . ',' . round($y); break; }
            }
            $d['routeAll'] = $all;
            $d['routeDone'] = implode(' ', $done);
            [$d['rx'], $d['ry']] = explode(',', end($done));
            [$d['sx'], $d['sy']] = $pts[0];
            [$d['ex'], $d['ey']] = $pts[3];
            $d['drivers'] = !sl_userIs('client') && $user->cdp_hasPermission('assign_drivers') ? sl_rows('SELECT id, fname, lname FROM cdb_users WHERE userlevel = 3 AND active = 1 ORDER BY fname') : [];
            $d['canDeliver'] = !sl_userIs('client') && $user->cdp_hasPermission('deliver_shipment');
        }
    }
    return $d;
}

/** Catalogues, defaults and next number for the New Shipment form (staff and customer modes). */
function sl_newShipmentData($user, $core, bool $client): array
{
    global $lang;
    require_once CDP_ROOT . '/helpers/unique_ids.php';
    $settings = cdp_getSettingsCourier();
    $info = cdp_getInfoShipDefault();
    $ctx = cdp_getAgencyContext();
    $uid = (int) $_SESSION['userid'];
    $look = sl_opsLookups();
    $mode = strtolower((string) ($_GET['mode'] ?? 'air')) === 'sea' ? 'sea' : 'air';
    $d = [
        'client' => $client, 'mode' => $mode,
        'prefix' => (string) ($settings->prefix ?? 'SL-'), 'nextNo' => (string) cdp_uidNext('order_no'),
        'agencies' => $look['agencies'], 'offices' => $look['offices'], 'couriers' => $look['couriers'], 'packaging' => $look['packaging'], 'delitimes' => $look['delitimes'], 'services' => $look['services'],
        'statuses' => $core->cdp_getStatusByType(1) ?: $look['statuses'], 'payTerms' => sl_rows('SELECT id, label, days FROM cdb_payment_methods ORDER BY id'), 'payModes' => $look['payments'],
        'drivers' => $client ? [] : $look['drivers'], 'categories' => $core->cdp_getCategoriesById(27) ?: $look['categories'],
        'defaults' => [
            'agency' => !empty($ctx['is_restricted']) && !empty($ctx['agency_id']) ? (int) $ctx['agency_id'] : (int) ($look['agencies'][0]->id ?? 0),
            'origin_off' => (int) ($look['offices'][0]->id ?? 0), 'order_package' => (int) ($info->packaging_default2 ?? 0), 'order_courier' => (int) ($info->courier_default3 ?? 0),
            'order_service_options' => (int) ($info->service_default4 ?? 0), 'order_deli_time' => (int) ($info->time_default5 ?? 0), 'order_pay_mode' => (int) ($info->pay_default6 ?? 0),
            'order_payment_method' => (int) ($info->payment_default7 ?? 0), 'status_courier' => (int) ($info->status_default8 ?? 0), 'order_item_category' => $mode === 'sea' ? 27 : 26,
        ],
        'agencyLocked' => !empty($ctx['is_restricted']) && !empty($ctx['agency_id']),
        'rates' => ['price_lb' => (float) ($settings->value_weight ?? 0), 'tax' => (float) ($settings->tax ?? 0), 'insurance' => (float) ($settings->insurance ?? 0), 'tariffs' => (float) ($settings->c_tariffs ?? 0), 'declared' => (float) ($settings->declared_tax ?? 0), 'min_tax' => (float) ($core->min_cost_tax ?? 0), 'min_declared' => (float) ($core->min_cost_declared_tax ?? 0), 'meter' => (float) ($settings->meter ?? 0), 'rate' => (float) ($core->exchange_rate ?? 0), 'currency' => (string) ($core->currency ?? 'USD'), 'weight' => (string) ($core->weight_p ?? 'lb')],
        'today' => date('Y-m-d'),
        'hasTariffs' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_shipping_fees') > 0,
        'endpoint' => $client ? 'ajax/courier/add_courier_client_ajax.php' : 'ajax/courier/add_courier_ajax.php',
        'me' => null, 'prealert' => null,
        'notify' => ['whatsapp' => (int) ($settings->active_whatsapp ?? 0) === 1, 'sms' => (int) ($settings->active_sms ?? 0) === 1, 'email' => true],
    ];
    if ($client) {
        $me = sl_row('SELECT id, fname, lname, email, phone, locker FROM cdb_users WHERE id = :id', [':id' => $uid]);
        $d['me'] = ['id' => $uid, 'name' => sl_userName($me), 'ini' => sl_initials(sl_userName($me)), 'locker' => $me->locker ?? '', 'phone' => $me->phone ?? '', 'email' => $me->email ?? ''];
        $d['myAddresses'] = sl_opsAddresses($uid);
    }
    $pa = (int) ($_GET['prealert_id'] ?? 0);
    if ($pa > 0) {
        $row = sl_row('SELECT p.*, c.name_com, u.fname, u.lname, u.locker FROM cdb_pre_alert p LEFT JOIN cdb_courier_com c ON c.id = p.courier_com LEFT JOIN cdb_users u ON u.id = p.customer_id WHERE p.pre_alert_id = :id', [':id' => $pa]);
        if ($row && (!$client || (int) $row->customer_id === $uid)) {
            $d['prealert'] = ['id' => $pa, 'tracking' => $row->tracking, 'shop' => $row->provider_shop, 'courier' => $row->name_com ?: '', 'courier_id' => (int) $row->courier_com, 'customer_id' => (int) $row->customer_id, 'customer' => trim($row->fname . ' ' . $row->lname), 'locker' => $row->locker, 'desc' => $row->package_description, 'price' => (float) $row->purchase_price];
        }
    }
    $pending = $client ? [] : sl_rows('SELECT p.pre_alert_id, p.tracking, p.provider_shop, u.fname, u.lname FROM cdb_pre_alert p LEFT JOIN cdb_users u ON u.id = p.customer_id WHERE p.is_package = 0 ORDER BY p.pre_alert_id DESC LIMIT 30');
    $d['prealerts'] = array_map(fn($p) => ['id' => (int) $p->pre_alert_id, 'label' => $p->tracking . ' · ' . $p->provider_shop . ' · ' . trim($p->fname . ' ' . $p->lname)], $pending);
    $d['prefs'] = json_decode((string) sl_slSetting('new_shipment_defaults_' . $uid, ''), true) ?: [];
    return $d;
}
