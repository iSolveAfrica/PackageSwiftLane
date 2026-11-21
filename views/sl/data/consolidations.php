<?php
/**
 * Data for Consolidations, ConsolidationDetail / ConsolidationAir and NewConsolidation.
 * Air = cdb_consolidate + cdb_consolidate_detail (cdb_add_order);
 * Sea = cdb_consolidate_packages + cdb_consolidate_packages_detail (cdb_customers_packages).
 */
require_once __DIR__ . '/ops_common.php';
require_once CDP_ROOT . '/helpers/consolidation_ref.php';

/** Table names and permission keys of a mode. */
function sl_conMode(string $mode): array
{
    $sea = $mode === 'sea';
    return [
        'mode' => $sea ? 'sea' : 'air', 'sea' => $sea,
        'head' => $sea ? 'cdb_consolidate_packages' : 'cdb_consolidate',
        'detail' => $sea ? 'cdb_consolidate_packages_detail' : 'cdb_consolidate_detail',
        'orders' => $sea ? 'cdb_customers_packages' : 'cdb_add_order',
        'items' => $sea ? 'cdb_customers_packages_detail' : 'cdb_add_order_item',
        'module' => $sea ? 'consolidate_packages' : 'consolidate',
        'p' => $sea ? [
            'list' => 'view_consolidate_package_list', 'view' => 'view_consolidate_package', 'add' => 'add_consolidate_package', 'edit' => 'edit_consolidate_package',
            'driver' => 'assign_driver_consolidate_package', 'print' => 'print_consolidate_package', 'label' => 'print_consolidate_package_label', 'track' => 'track_consolidate_package',
            'deliver' => 'deliver_consolidate_package', 'delete' => 'delete_consolidate_package', 'cancel' => 'cancel_consolidate_package', 'verify' => 'verify_consolidate_package_payment', 'email' => 'send_consolidate_package_email',
        ] : [
            'list' => 'view_consolidate_list', 'view' => 'view_consolidate', 'add' => 'add_consolidate_shipment', 'edit' => 'edit_consolidate',
            'driver' => 'assign_driver_consolidate', 'print' => 'print_consolidate', 'label' => 'print_consolidate_label', 'track' => 'track_consolidate',
            'deliver' => 'deliver_consolidate', 'delete' => 'delete_consolidate', 'cancel' => 'cancel_consolidate', 'verify' => 'verify_consolidate_payment', 'email' => 'send_consolidate_email',
        ],
        'url' => $sea ? [
            'edit' => 'consolidate_package_edit.php', 'print' => 'print_consolidate_package.php', 'label' => 'print_label_consolidate_package.php', 'track' => 'consolidate_package_shipment_tracking.php',
            'deliver' => 'consolidate_package_deliver_shipment.php', 'pay' => 'add_payment_gateways_consolidate_package.php',
        ] : [
            'edit' => 'consolidate_edit.php', 'print' => 'print_consolidate.php', 'label' => 'print_label_consolidate.php', 'track' => 'consolidate_shipment_tracking.php',
            'deliver' => 'consolidate_deliver_shipment.php', 'pay' => 'add_payment_gateways_consolidate.php',
        ],
        'cap' => (float) sl_slSetting($sea ? 'consol_capacity_sea' : 'consol_capacity_air', $sea ? 12000 : 1500),
    ];
}

function sl_conCapClass(int $pct): string
{
    return $pct >= 95 ? 'r' : ($pct >= 70 ? 'y' : '');
}

/**
 * Status buckets of a consolidation, shared by the filters and the statistics.
 * open     = still packing at origin (accepting parcels)
 * transit  = flown / sailed and on the way (3 In transit, 5 Distribution, 7 On route)
 * arrived  = landed / docked, waiting to be handed over (6 Available, 32 Ready for pick-up)
 * done     = handed over (8 Delivered, 15 Picked up)
 */
function sl_conBuckets(): array
{
    return [
        'open' => "c.status_courier NOT IN ('3','5','7','6','32','8','15','21')",
        'transit' => "c.status_courier IN ('3','5','7')",
        'arrived' => "c.status_courier IN ('6','32')",
        'done' => "c.status_courier IN ('8','15')",
        'active' => "c.status_courier NOT IN ('8','15','21')",
    ];
}

/**
 * Air Consolidations / Sea Consolidations list (Consolidations.dc.html), one mode per page:
 * consolidate_list.php = air (cdb_consolidate), consolidate_package_list.php = sea (cdb_consolidate_packages).
 */
function sl_consolidationsData($user, $core, string $mode = 'air'): array
{
    $mode = $mode === 'sea' ? 'sea' : 'air';
    $M = sl_conMode($mode);
    $sea = $M['sea'];
    $canAir = $user->cdp_hasPermission('view_consolidate_list');
    $canSea = $user->cdp_hasPermission('view_consolidate_package_list');
    $q = trim((string) ($_GET['q'] ?? ''));
    $status = (string) ($_GET['filter'] ?? '');
    $filters = ['open', 'transit', 'arrived', 'done', 'active', 'unpaid', 'nodoc', 'late', 'cancelled'];
    if (!in_array($status, $filters, true)) $status = '';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $scope = sl_opsScope('c');
    $B = sl_conBuckets();
    $monthStart = date('Y-m-01');
    $code = "CONCAT(c.c_prefix, c.c_no)";
    $doneMonth = "{$B['done']} AND EXISTS (SELECT 1 FROM cdb_courier_track t WHERE t.order_track = $code AND t.status_courier IN (8,15) AND t.t_date >= '$monthStart')";
    $noDoc = "(c.awb_no IS NULL OR c.awb_no = '') AND {$B['active']}";
    $late = "c.estimated_eta IS NOT NULL AND c.estimated_eta < CURDATE() AND c.status_courier NOT IN ('6','32','8','15','21')";
    $unpaid = "COALESCE(c.status_invoice, 0) NOT IN (1,19) AND c.status_courier <> '21'";

    $binds = [];
    $w = "1 = 1 $scope";
    if ($q !== '') {
        $w .= " AND ($code LIKE :q OR c.awb_no LIKE :q OR c.seals_package LIKE :q OR c.consolidate_id IN (SELECT d.consolidate_id FROM {$M['detail']} d WHERE CONCAT(d.order_prefix, d.order_no) LIKE :q))";
        $binds[':q'] = '%' . $q . '%';
    }
    switch ($status) {
        case 'open': case 'transit': case 'arrived': case 'active': $w .= ' AND ' . $B[$status]; break;
        case 'done': $w .= ' AND ' . $doneMonth; break;
        case 'unpaid': $w .= ' AND ' . $unpaid; break;
        case 'nodoc': $w .= ' AND ' . $noDoc; break;
        case 'late': $w .= ' AND ' . $late; break;
        case 'cancelled': $w .= " AND c.status_courier = '21'"; break;
    }
    $count = (int) sl_scalar("SELECT COUNT(*) FROM {$M['head']} c WHERE $w", $binds);
    $rows = sl_rows("SELECT c.consolidate_id, c.c_prefix, c.c_no, c.c_date, c.status_courier, c.total_weight, c.total_order, c.status_invoice, c.awb_no, c.seals_package, c.order_courier, c.origin_off, c.agency, c.sender_id, c.driver_id, c.estimated_eta,
            (SELECT COUNT(*) FROM {$M['detail']} d WHERE d.consolidate_id = c.consolidate_id) AS n,
            (SELECT COALESCE(SUM(d.weight_vol), 0) FROM {$M['detail']} d WHERE d.consolidate_id = c.consolidate_id) AS vol
        FROM {$M['head']} c WHERE $w ORDER BY c.c_date DESC, c.consolidate_id DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds);

    $d = ['mode' => $mode, 'sea' => $sea, 'q' => $q, 'filter' => $status, 'page' => $page, 'per' => $per, 'canAir' => $canAir, 'canSea' => $canSea,
        'title' => $sea ? 'Sea Consolidations' : 'Air Consolidations',
        'unit' => $sea ? 'container' : 'flight load', 'units' => $sea ? 'containers' : 'flight loads',
        'docL' => $sea ? 'Bill of lading' : 'Master air waybill', 'carrierL' => $sea ? 'Shipping line / seal' : 'Airline / ULD',
        'colId' => $sea ? 'Container' : 'Consolidation', 'otherHref' => $sea ? ($canAir ? sl_route('ConsolidationsAir') : '') : ($canSea ? sl_route('ConsolidationsSea') : ''),
        'parentHref' => $sea ? sl_route('SeaPackages') : sl_route('AirShipments'), 'parentL' => $sea ? 'Sea Packages' : 'Air Shipments'];

    $couriers = [];
    foreach (sl_rows('SELECT id, name_com FROM cdb_courier_com') as $c) $couriers[(int) $c->id] = $c->name_com;
    $offices = [];
    foreach (sl_rows('SELECT id, name_off, city FROM cdb_offices') as $o) $offices[(int) $o->id] = $o->city ?: $o->name_off;
    $dest = $core->c_city ?: 'Accra';
    $can = function ($k) use ($user, $M) { return $user->cdp_hasPermission($M['p'][$k]); };
    $list = [];
    foreach ($rows as $r) {
        $pct = sl_pct($r->total_weight, $M['cap']);
        $s = (int) $r->status_courier;
        $done = cdp_isHandedOver($s);
        $id = (int) $r->consolidate_id;
        $etaTs = $r->estimated_eta ? strtotime($r->estimated_eta . ' 23:59:59') : 0;
        $isLate = $etaTs && $etaTs < time() && !in_array($s, [6, 32, 8, 15, 21], true);
        $list[] = [
            'id' => $r->c_prefix . $r->c_no, 'cid' => $id, 'type' => $mode, 'href' => sl_route('ConsolidationDetail', ['id' => $id, 'type' => $mode]),
            'mode' => $mode, 'modeL' => $sea ? 'Sea' : 'Air', 'route' => ($offices[(int) $r->origin_off] ?? 'Origin') . ' → ' . $dest,
            'awb' => $r->awb_no ?: '—', 'awbType' => $r->awb_no ? $d['docL'] : 'Not set yet', 'noDoc' => !$r->awb_no && !$done && $s !== 21,
            'carrier' => $couriers[(int) $r->order_courier] ?? '—', 'voy' => $r->seals_package ? ($sea ? 'Seal ' : 'ULD ') . $r->seals_package : '',
            'tc' => sl_conCapClass($pct), 'sty' => 'width: ' . $pct . '%', 'fill' => sl_weight($r->total_weight, 0) . ' · ' . $pct . '%',
            'vol' => number_format((float) $r->vol, 1),
            'n' => (int) $r->n, 'dep' => sl_date($r->c_date), 'eta' => $r->estimated_eta ? sl_date($r->estimated_eta) : '—', 'late' => $isLate,
            'st' => sl_statusLabel($s), 'cls' => sl_statusClass($s), 'done' => $done, 'cancelled' => $s === 21,
            'pay' => sl_payPill($r->status_invoice),
            'act' => [
                'edit' => $can('edit') && !$done && !sl_userIs('client') ? $M['url']['edit'] . '?id=' . $id : '',
                'print' => $can('print') && !$done ? $M['url']['print'] . '?id=' . $id : '',
                'label' => $can('label') && !$done ? $M['url']['label'] . '?id=' . $id : '',
                'packing' => $can('print') && !sl_userIs('client') && !$sea ? 'consolidate_parking_list.php?id=' . $id : '',
                'packingX' => $can('print') && !sl_userIs('client') && !$sea ? 'consolidate_parking_list_excel.php?id=' . $id : '',
                'track' => $can('track') && !$done && $s !== 21 ? $M['url']['track'] . '?id=' . $id : '',
                'deliver' => $can('deliver') && !$done && $s !== 21 ? $M['url']['deliver'] . '?id=' . $id : '',
                'pay' => $can('verify') && (int) $r->status_invoice === 2 ? $M['url']['pay'] . '?id_order=' . $id : '',
                'delete' => $can('delete') && !$done && !sl_userIs('client'),
                'driver' => $can('driver') && $s !== 21 && !sl_userIs('client'),
            ],
        ];
    }
    $d['rows'] = $list;
    $d['count'] = $count;

    // statistics for this mode only
    $st = sl_row("SELECT COUNT(*) AS t,
            SUM({$B['open']}) AS o, SUM({$B['transit']}) AS tr, SUM({$B['arrived']}) AS ar, SUM($doneMonth) AS dm, SUM({$B['done']}) AS dn,
            SUM($unpaid) AS up, SUM(CASE WHEN $unpaid THEN c.total_order ELSE 0 END) AS upAmt,
            SUM($noDoc) AS nd, SUM($late) AS lt, SUM(c.status_courier = '21') AS cx,
            SUM(CASE WHEN {$B['active']} THEN c.total_weight ELSE 0 END) AS w,
            SUM(CASE WHEN c.c_date >= '$monthStart' AND c.status_courier <> '21' THEN 1 ELSE 0 END) AS cm
        FROM {$M['head']} c WHERE 1 = 1 $scope");
    $onBoard = sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(d.weight_vol), 0) AS v FROM {$M['detail']} d JOIN {$M['head']} c ON c.consolidate_id = d.consolidate_id WHERE {$B['active']} $scope");
    $all = (int) ($st->t ?? 0);
    $live = $all - (int) ($st->cx ?? 0);
    $activeN = (int) $st->o + (int) $st->tr + (int) $st->ar;
    $d['tot'] = $live;
    $d['all'] = $all;
    $d['cancelledN'] = (int) ($st->cx ?? 0);
    $d['metrics'] = [
        ['icon' => 'i-layers', 'l' => $sea ? 'Open containers' : 'Open flight loads', 'n' => (int) $st->o, 'of' => $live, 'sub' => $sea ? 'packing at origin, accepting parcels' : 'building at origin, accepting parcels', 'cls' => 'y', 'f' => 'open'],
        ['icon' => $sea ? 'i-ship' : 'i-plane', 'l' => $sea ? 'At sea' : 'In the air', 'n' => (int) $st->tr, 'of' => $live, 'sub' => $sea ? 'sailed, on the way to port' : 'flown, on the way to destination', 'cls' => 'v', 'f' => 'transit'],
        ['icon' => 'i-warehouse', 'l' => $sea ? 'Arrived at port' : 'Landed', 'n' => (int) $st->ar, 'of' => $live, 'sub' => 'cleared, waiting for hand-over', 'cls' => '', 'f' => 'arrived'],
        ['icon' => 'i-check', 'l' => 'Handed over this month', 'n' => (int) $st->dm, 'of' => (int) $st->dn, 'sub' => sl_num((int) $st->dn) . ' handed over in total', 'cls' => '', 'f' => 'done'],
    ];
    $d['metrics2'] = [
        ['icon' => 'i-box', 'l' => $sea ? 'Parcels in containers' : 'Parcels on flights', 'v' => sl_num((int) $onBoard->n), 'sub' => sl_weight($st->w, 0) . ($sea ? ' · ' . number_format((float) $onBoard->v, 1) . ' vol.' : '') . ' across ' . sl_num($activeN) . ' active', 'f' => 'active', 'warn' => false],
        ['icon' => 'i-receipt', 'l' => 'Unpaid', 'v' => sl_num((int) $st->up), 'sub' => (function_exists('cdp_canViewMoney') && !cdp_canViewMoney($user)) ? 'awaiting payment' : sl_money($st->upAmt) . ' outstanding', 'f' => 'unpaid', 'warn' => (int) $st->up > 0],
        ['icon' => 'i-file', 'l' => $sea ? 'No bill of lading' : 'No master air waybill', 'v' => sl_num((int) $st->nd), 'sub' => 'active and missing the document', 'f' => 'nodoc', 'warn' => (int) $st->nd > 0],
        ['icon' => 'i-clock', 'l' => 'Past ETA', 'v' => sl_num((int) $st->lt), 'sub' => 'not yet ' . ($sea ? 'at port' : 'landed') . ' · ' . sl_num((int) $st->cm) . ' created this month', 'f' => 'late', 'warn' => (int) $st->lt > 0],
    ];
    $d['filterL'] = ['open' => $d['metrics'][0]['l'], 'transit' => $d['metrics'][1]['l'], 'arrived' => $d['metrics'][2]['l'], 'done' => 'Handed over this month', 'active' => 'Active', 'unpaid' => 'Unpaid', 'nodoc' => $d['metrics2'][2]['l'], 'late' => 'Past ETA', 'cancelled' => 'Cancelled'][$status] ?? '';
    $d['canNew'] = $user->cdp_hasPermission($M['p']['add']);
    $d['drivers'] = sl_rows('SELECT id, fname, lname FROM cdb_users WHERE userlevel = 3 AND active = 1 ORDER BY fname');
    require_once __DIR__ . '/track_console.php';
    $d['tracking'] = $page === 1 && $q === '' ? sl_trackConsoleData($user, $core, 'consolidations_' . $mode, 10) : null;
    return $d;
}

/** Packages that may join a consolidation of this mode (legacy courier_list_add_ajax rules). */
function sl_conAvailable(array $M, string $q = '', int $limit = 60, int $dg = -1): array
{
    $o = $M['orders'];
    $w = "a.is_consolidate = 0 AND a.status_courier NOT IN (8,12,14,15,21)" . ($M['sea'] ? '' : ' AND a.is_deleted = 0') . sl_opsScope('a');
    if (!$M['sea'] && $dg >= 0 && sl_columnExists('cdb_add_order', 'is_dangerous_good')) $w .= ' AND a.is_dangerous_good = ' . $dg;
    $b = [];
    if ($q !== '') {
        $w .= " AND (CONCAT(a.order_prefix, a.order_no) LIKE :q OR a.sender_id IN (SELECT id FROM cdb_users WHERE CONCAT(fname,' ',lname) LIKE :q OR locker LIKE :q))";
        $b[':q'] = '%' . $q . '%';
    }
    $postal = $M['sea'] ? 'a.tracking_purchase' : "(SELECT t.tracking_number FROM cdb_package_tracking_number t WHERE t.order_id = a.order_id ORDER BY t.id DESC LIMIT 1)";
    $rows = sl_rows("SELECT a.order_id, a.order_prefix, a.order_no, a.sender_id, a.total_weight, a.total_order, a.status_invoice, $postal AS postal,
            (SELECT GROUP_CONCAT(i.order_item_description SEPARATOR ', ') FROM {$M['items']} i WHERE i.order_id = a.order_id) AS what,
            (SELECT COALESCE(SUM(i.order_item_quantity),0) FROM {$M['items']} i WHERE i.order_id = a.order_id) AS pcs
        FROM $o a WHERE $w ORDER BY a.order_id DESC LIMIT $limit", $b);
    $users = sl_usersById(array_map(fn($r) => $r->sender_id, $rows));
    $out = [];
    foreach ($rows as $r) {
        $u = $users[(int) $r->sender_id] ?? null;
        $out[] = ['oid' => (int) $r->order_id, 'id' => $r->order_prefix . $r->order_no, 'name' => sl_userName($u), 'lock' => $u->locker ?? '', 'sender' => (int) $r->sender_id, 'what' => $r->what ?: '—', 'post' => $r->postal ?: '—', 'w' => sl_weight($r->total_weight), 'wn' => (float) $r->total_weight, 'pcs' => (int) $r->pcs, 'amt' => (float) $r->total_order];
    }
    return $out;
}

/** ConsolidationDetail / ConsolidationAir for one consolidation. */
function sl_consolidationDetailData($user, $core, int $id, string $type): ?array
{
    $M = sl_conMode($type);
    if (!$user->cdp_hasPermission([$M['p']['view'], $M['p']['list']])) return null;
    $c = sl_row("SELECT c.*, s.mod_style FROM {$M['head']} c LEFT JOIN cdb_styles s ON s.id = c.status_courier WHERE c.consolidate_id = :id", [':id' => $id]);
    if (!$c || !sl_opsInScope($c)) return null;
    $code = $c->c_prefix . $c->c_no;
    $o = $M['orders'];
    $postal = $M['sea'] ? 'a.tracking_purchase' : "(SELECT t.tracking_number FROM cdb_package_tracking_number t WHERE t.order_id = a.order_id ORDER BY t.id DESC LIMIT 1)";
    $lq = trim((string) ($_GET['lq'] ?? ''));
    $rows = sl_rows("SELECT d.detail_id, d.order_id, d.order_prefix, d.order_no, d.weight, d.weight_vol, a.sender_id, a.status_courier, a.status_invoice, a.total_order, $postal AS postal,
            (SELECT GROUP_CONCAT(i.order_item_description SEPARATOR ', ') FROM {$M['items']} i WHERE i.order_id = a.order_id) AS what,
            (SELECT COALESCE(SUM(i.order_item_quantity),0) FROM {$M['items']} i WHERE i.order_id = a.order_id) AS pcs
        FROM {$M['detail']} d LEFT JOIN $o a ON a.order_id = d.order_id WHERE d.consolidate_id = :id ORDER BY d.detail_id", [':id' => $id]);
    $users = sl_usersById(array_map(fn($r) => $r->sender_id, $rows));
    $list = []; $w = 0; $v = 0; $pcs = 0; $paid = 0; $unpaid = 0; $cust = []; $n = 0;
    $canEdit = $user->cdp_hasPermission($M['p']['edit']) && !cdp_isHandedOver($c->status_courier) && (int) $c->status_courier !== 21 && !sl_userIs('client');
    foreach ($rows as $r) {
        $u = $users[(int) $r->sender_id] ?? null;
        $n++;
        $w += (float) $r->weight; $v += (float) $r->weight_vol; $pcs += (int) $r->pcs; $cust[(int) $r->sender_id] = 1;
        if ((int) $r->status_invoice === 1) $paid += (float) $r->total_order; else $unpaid++;
        [$pl, $pc] = sl_payPill($r->status_invoice);
        $row = ['n' => $n, 'did' => (int) $r->detail_id, 'oid' => (int) $r->order_id, 'ini' => sl_initials(sl_userName($u)), 'name' => sl_userName($u), 'lock' => $u->locker ?? '', 'id' => $r->order_prefix . $r->order_no,
            'href' => $M['sea'] ? sl_route('PackageDetail', ['id' => (int) $r->order_id]) : sl_route('ShipmentDetail', ['id' => (int) $r->order_id]),
            'post' => $r->postal ?: '—', 'what' => $r->what ?: '—', 'pcs' => (int) $r->pcs, 'w' => sl_weight($r->weight), 'v' => number_format((float) $r->weight_vol, 1), 'pay' => $pl, 'pc' => $pc,
            'cls' => cdp_isHandedOver($r->status_courier) ? 'mut' : '', 'canDel' => $canEdit && !cdp_isHandedOver($r->status_courier)];
        if ($lq === '' || stripos(implode(' ', [$row['name'], $row['lock'], $row['id'], $row['post'], $row['what']]), $lq) !== false) $list[] = $row;
    }
    $pct = sl_pct($c->total_weight ?: $w, $M['cap']);
    $s = (int) $c->status_courier;
    $stage = sl_stageOf($s);
    $labels = $M['sea'] ? ['Packing at origin', 'Sealed and loaded', 'At sea', 'Arrived · customs', 'Handed over'] : ['Packing at origin', 'Loaded on flight', 'In the air', 'Arrived · customs', 'Handed over'];
    $track = sl_trackHistory($code);
    $steps = [];
    foreach ($labels as $i => $l) {
        $steps[] = ['cls' => $i + 1 < $stage ? 'done' : ($i + 1 === $stage ? 'now' : ''), 't' => $l, 'd' => $i === 0 ? sl_date($c->c_date) : ($i + 1 === $stage && $track ? sl_date($track[0]->t_date, 'M j, H:i') : ($i === 3 && $c->estimated_eta ? 'ETA ' . sl_date($c->estimated_eta) : ''))];
    }
    $courier = sl_scalar('SELECT name_com FROM cdb_courier_com WHERE id = :id', [':id' => (int) $c->order_courier], '—');
    $office = sl_row('SELECT name_off, city FROM cdb_offices WHERE id = :id', [':id' => (int) $c->origin_off]);
    $agency = sl_scalar('SELECT name_branch FROM cdb_branchoffices WHERE id = :id', [':id' => (int) $c->agency], '—');
    $driver = $c->driver_id ? sl_userName(sl_usersById([$c->driver_id])[(int) $c->driver_id] ?? null) : 'Not assigned';
    $dest = $core->c_city ?: 'Accra';
    $route = ($office->city ?? ($office->name_off ?? 'Origin')) . ' → ' . $dest;
    $info = $M['sea']
        ? [['Shipping line', $courier], ['Container', $code], ['Bill of lading', $c->awb_no ?: 'Not set'], ['Seal', $c->seals_package ?: '—'], ['Route', $route], ['Sailed · ETA', sl_date($c->c_date) . ' · ' . ($c->estimated_eta ? sl_date($c->estimated_eta) : '—')], ['Driver to port', $driver], ['Agency', $agency]]
        : [['Carrier', $courier], ['Master air waybill', $c->awb_no ?: 'Not set'], ['Seals / ULD', $c->seals_package ?: '—'], ['Route', $route], ['Departs', sl_date($c->c_date)], ['ETA ' . $dest, $c->estimated_eta ? sl_date($c->estimated_eta) : '—'], ['Driver', $driver], ['Agency', $agency]];
    $d = [
        'cid' => $id, 'type' => $M['mode'], 'code' => $code, 'st' => sl_statusLabel($s), 'stCls' => sl_statusClass($s), 'sea' => $M['sea'],
        'lede' => $n . ' package' . ($n === 1 ? '' : 's') . ' from ' . count($cust) . ' customer' . (count($cust) === 1 ? '' : 's') . ' · ' . $route . ' · created ' . sl_date($c->c_date),
        'capV' => sl_weight($c->total_weight ?: $w, 0) . ' / ' . sl_weight($M['cap'], 0), 'capW' => 'width:' . $pct . '%', 'capCls' => sl_conCapClass($pct), 'capS' => $pct . '% of capacity',
        'pkN' => $n, 'custN' => count($cust), 'pcsN' => $pcs, 'wt' => sl_weight($w), 'wt2' => number_format($v, 1) . ' vol. weight', 'vTot' => number_format($v, 1),
        'paid' => sl_money($paid), 'unpaidN' => $unpaid, 'rows' => $list, 'lq' => $lq, 'info' => $info, 'steps' => $steps,
        'sub' => sl_money($c->sub_total), 'disc' => sl_money($c->total_tax_discount), 'discP' => (float) $c->tax_discount, 'ins' => sl_money($c->total_tax_insurance), 'duty' => sl_money($c->total_tax_custom_tariffis), 'tax' => sl_money($c->total_tax), 'tot' => sl_money($c->total_order),
        'flash' => isset($_GET['created']) ? 'Consolidation ' . $code . ' created.' : '',
        'adding' => !empty($_GET['add']) && $canEdit, 'canEdit' => $canEdit, 'q' => trim((string) ($_GET['q'] ?? '')),
        'can' => [
            'edit' => $canEdit ? $M['url']['edit'] . '?id=' . $id : '', 'print' => $user->cdp_hasPermission($M['p']['print']) ? $M['url']['print'] . '?id=' . $id : '',
            'label' => $user->cdp_hasPermission($M['p']['label']) ? $M['url']['label'] . '?id=' . $id : '', 'track' => $user->cdp_hasPermission($M['p']['track']) && !cdp_isHandedOver($s) && $s !== 21 ? $M['url']['track'] . '?id=' . $id : '',
            'deliver' => $user->cdp_hasPermission($M['p']['deliver']) && !cdp_isHandedOver($s) && $s !== 21 ? $M['url']['deliver'] . '?id=' . $id : '',
            'packing' => !$M['sea'] && $user->cdp_hasPermission($M['p']['print']) && !sl_userIs('client') ? 'consolidate_parking_list.php?id=' . $id : '',
            'packingX' => !$M['sea'] && $user->cdp_hasPermission($M['p']['print']) && !sl_userIs('client') ? 'consolidate_parking_list_excel.php?id=' . $id : '',
            'notify' => $user->cdp_hasPermission('push_notifications'),
            'delete' => $user->cdp_hasPermission($M['p']['delete']) && !cdp_isHandedOver($s) && $s !== 21 && !sl_userIs('client'),
        ],
        'files' => array_map(fn($f) => ['url' => $f->url, 'name' => $f->name], sl_rows('SELECT url, name FROM cdb_order_files WHERE order_id = :id AND is_consolidate = 1 ORDER BY id DESC', [':id' => $id])),
        'other' => [],
    ];
    $d['avail'] = $d['adding'] ? sl_conAvailable($M, $d['q'], 40, $M['sea'] ? -1 : (int) ($c->is_dangerous_good ?? 0)) : [];
    $d['noAvail'] = $d['adding'] && !$d['avail'];
    // the "other mode" chip: the latest consolidation of the other mode the user may see
    $om = sl_conMode($M['sea'] ? 'air' : 'sea');
    if ($user->cdp_hasPermission([$om['p']['view'], $om['p']['list']])) {
        $x = sl_row("SELECT consolidate_id, c_prefix, c_no FROM {$om['head']} c WHERE c.status_courier <> '21' " . sl_opsScope('c') . ' ORDER BY consolidate_id DESC');
        if ($x) $d['other'] = ['id' => (int) $x->consolidate_id, 'code' => $x->c_prefix . $x->c_no, 'type' => $om['mode']];
    }
    $d['canNew'] = $user->cdp_hasPermission($M['p']['add']);
    return $d;
}

/** NewConsolidation.dc.html */
function sl_newConsolidationData($user, $core, string $type): array
{
    require_once CDP_ROOT . '/helpers/unique_ids.php';
    $M = sl_conMode($type);
    $q = trim((string) ($_GET['q'] ?? ''));
    $dg = (int) ($_GET['dg'] ?? 0) === 1 ? 1 : 0;
    $L = sl_opsLookups();
    $def = cdp_getInfoShipDefault();
    $ag = cdp_getDefaultAgencyOrigin();
    try { $num = (string) cdp_uidNext('consolidate_no'); } catch (Throwable $e) { $num = ''; }
    $modes = [];
    foreach (['air' => ['i-plane', 'Air', 'Flights, priced per ' . ($core->weight_p ?? 'lb')], 'sea' => ['i-ship', 'Sea', 'Containers, priced per volume']] as $m => [$ic, $lab, $desc]) {
        $mm = sl_conMode($m);
        if (!$user->cdp_hasPermission($mm['p']['add'])) continue;
        $modes[] = ['m' => $m, 'cls' => $m === $M['mode'] ? 'on' : '', 'icon' => $ic, 'label' => $lab, 'd' => $desc, 'href' => sl_route('NewConsolidation', ['type' => $m])];
    }
    return [
        'type' => $M['mode'], 'sea' => $M['sea'], 'modes' => $modes, 'q' => $q, 'dg' => $dg,
        'prefix' => (string) ($M['sea'] ? 'SLC-' : ($core->prefix_consolidate ?: 'AIR-')), 'num' => $num,
        'avail' => sl_conAvailable($M, $q, 80, $M['sea'] ? -1 : $dg), 'L' => $L, 'cap' => $M['cap'],
        'def' => ['agency' => $ag['agency'], 'office' => $ag['origin_off'], 'courier' => (int) ($def->courier_default3 ?? 0), 'service' => (int) ($def->service_default4 ?? 0), 'delitime' => (int) ($def->time_default5 ?? 0), 'packaging' => (int) ($def->packaging_default2 ?? 0), 'category' => (int) ($def->logistics_default1 ?? 0), 'status' => (int) ($def->status_default8 ?? 13) ?: 13],
        'rate' => (float) ($core->value_weight ?? 0), 'tax' => (float) ($core->tax ?? 0), 'insurance' => (float) ($core->insurance ?? 0), 'unit' => (string) ($core->weight_p ?? 'lb'),
        'today' => date('Y-m-d'), 'eta' => date('Y-m-d', strtotime($M['sea'] ? '+40 days' : '+5 days')), 'dest' => $core->c_city ?: 'Accra',
        'awbReady' => cdp_awbColumnReady(),
    ];
}
