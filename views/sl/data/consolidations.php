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

/** Consolidations.dc.html */
function sl_consolidationsData($user, $core): array
{
    $canAir = $user->cdp_hasPermission('view_consolidate_list');
    $canSea = $user->cdp_hasPermission('view_consolidate_package_list');
    $mode = (string) ($_GET['mode'] ?? 'all');
    if (!in_array($mode, ['all', 'air', 'sea'], true)) $mode = 'all';
    if ($mode === 'air' && !$canAir) $mode = 'sea';
    if ($mode === 'sea' && !$canSea) $mode = 'air';
    $q = trim((string) ($_GET['q'] ?? ''));
    $status = (string) ($_GET['filter'] ?? '');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $parts = [];
    $binds = [];
    $scope = sl_opsScope('c');
    foreach (['air' => $canAir, 'sea' => $canSea] as $m => $ok) {
        if (!$ok || ($mode !== 'all' && $mode !== $m)) continue;
        $M = sl_conMode($m);
        $w = "1 = 1 $scope";
        if ($q !== '') {
            $w .= " AND (CONCAT(c.c_prefix, c.c_no) LIKE :q$m OR c.awb_no LIKE :q$m OR c.seals_package LIKE :q$m OR c.consolidate_id IN (SELECT d.consolidate_id FROM {$M['detail']} d WHERE CONCAT(d.order_prefix, d.order_no) LIKE :q$m))";
            $binds[":q$m"] = '%' . $q . '%';
        }
        if ($status === 'open') $w .= " AND c.status_courier NOT IN ('3','5','7','8','15','21')";
        if ($status === 'transit') $w .= " AND c.status_courier IN ('3','5','7')";
        if ($status === 'arrived') $w .= " AND c.status_courier IN ('2','4','6','32','8','15')";
        $parts[] = "SELECT '$m' AS mode, c.consolidate_id, c.c_prefix, c.c_no, c.c_date, c.status_courier, c.total_weight, c.total_order, c.status_invoice, c.awb_no, c.seals_package, c.order_courier, c.origin_off, c.agency, c.sender_id, c.driver_id, c.estimated_eta,
            (SELECT COUNT(*) FROM {$M['detail']} d WHERE d.consolidate_id = c.consolidate_id) AS n FROM {$M['head']} c WHERE $w";
    }
    $d = ['mode' => $mode, 'q' => $q, 'filter' => $status, 'page' => $page, 'per' => $per, 'canAir' => $canAir, 'canSea' => $canSea];
    $rows = [];
    $count = 0;
    if ($parts) {
        $u = implode(' UNION ALL ', $parts);
        $count = (int) sl_scalar("SELECT COUNT(*) FROM ($u) x", $binds);
        $rows = sl_rows("SELECT * FROM ($u) x ORDER BY c_date DESC, consolidate_id DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds);
    }
    $couriers = [];
    foreach (sl_rows('SELECT id, name_com FROM cdb_courier_com') as $c) $couriers[(int) $c->id] = $c->name_com;
    $offices = [];
    foreach (sl_rows('SELECT id, name_off, city FROM cdb_offices') as $o) $offices[(int) $o->id] = $o->city ?: $o->name_off;
    $dest = $core->c_city ?: 'Accra';
    $list = [];
    foreach ($rows as $r) {
        $M = sl_conMode($r->mode);
        $pct = sl_pct($r->total_weight, $M['cap']);
        $s = (int) $r->status_courier;
        $done = cdp_isHandedOver($s);
        $id = (int) $r->consolidate_id;
        $can = function ($k) use ($user, $M) { return $user->cdp_hasPermission($M['p'][$k]); };
        $list[] = [
            'id' => $r->c_prefix . $r->c_no, 'cid' => $id, 'type' => $r->mode, 'href' => sl_route('ConsolidationDetail', ['id' => $id, 'type' => $r->mode]),
            'mode' => $r->mode, 'modeL' => $r->mode === 'sea' ? 'Sea' : 'Air', 'route' => ($offices[(int) $r->origin_off] ?? 'Origin') . ' → ' . $dest,
            'ship' => $r->c_prefix . $r->c_no, 'awb' => $r->awb_no ?: '—', 'awbType' => $r->awb_no ? ($r->mode === 'sea' ? 'Bill of lading' : 'Master air waybill') : 'Not set yet',
            'carrier' => $couriers[(int) $r->order_courier] ?? '—', 'voy' => $r->seals_package ? 'Seal ' . $r->seals_package : '',
            'tc' => sl_conCapClass($pct), 'sty' => 'width: ' . $pct . '%', 'fill' => sl_weight($r->total_weight, 0) . ' · ' . $pct . '%',
            'n' => (int) $r->n, 'dep' => sl_date($r->c_date), 'eta' => $r->estimated_eta ? sl_date($r->estimated_eta) : '—',
            'st' => sl_statusLabel($s), 'cls' => sl_statusClass($s), 'done' => $done, 'cancelled' => $s === 21, 'unpaid' => (int) $r->status_invoice !== 1, 'verify' => (int) $r->status_invoice === 3,
            'act' => [
                'edit' => $can('edit') && !$done && !sl_userIs('client') ? $M['url']['edit'] . '?id=' . $id : '',
                'print' => $can('print') && !$done ? $M['url']['print'] . '?id=' . $id : '',
                'label' => $can('label') && !$done ? $M['url']['label'] . '?id=' . $id : '',
                'packing' => $can('print') && !sl_userIs('client') && $r->mode === 'air' ? 'consolidate_parking_list.php?id=' . $id : '',
                'packingX' => $can('print') && !sl_userIs('client') && $r->mode === 'air' ? 'consolidate_parking_list_excel.php?id=' . $id : '',
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
    // metrics across the visible modes
    $tot = 0; $openN = 0; $transit = 0; $arrived = 0;
    foreach (['air' => $canAir, 'sea' => $canSea] as $m => $ok) {
        if (!$ok || ($mode !== 'all' && $mode !== $m)) continue;
        $M = sl_conMode($m);
        $r = sl_row("SELECT COUNT(*) AS t, SUM(c.status_courier NOT IN ('3','5','7','8','15','21')) AS o, SUM(c.status_courier IN ('3','5','7')) AS tr, SUM(c.status_courier IN ('2','4','6','32','8','15') AND c.c_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS ar FROM {$M['head']} c WHERE c.status_courier <> '21' $scope");
        $tot += (int) $r->t; $openN += (int) $r->o; $transit += (int) $r->tr; $arrived += (int) $r->ar;
    }
    $d['metrics'] = [
        ['icon' => 'i-layers', 'l' => 'Open containers', 'n' => $openN, 'sub' => 'still accepting parcels', 'cls' => 'y', 'f' => 'open'],
        ['icon' => 'i-ship', 'l' => 'In transit', 'n' => $transit, 'sub' => 'at sea or in the air', 'cls' => 'v', 'f' => 'transit'],
        ['icon' => 'i-check', 'l' => 'Arrived this month', 'n' => $arrived, 'sub' => 'cleared and unloaded', 'cls' => '', 'f' => 'arrived'],
    ];
    $d['tot'] = $tot;
    $d['canNew'] = ($canAir && $user->cdp_hasPermission('add_consolidate_shipment')) || ($canSea && $user->cdp_hasPermission('add_consolidate_package'));
    $d['drivers'] = sl_rows('SELECT id, fname, lname FROM cdb_users WHERE userlevel = 3 AND active = 1 ORDER BY fname');
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
