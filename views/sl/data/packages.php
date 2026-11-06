<?php
/** Data for SeaPackages, NewPackage and PackageDetail (cdb_customers_packages). */
require_once __DIR__ . '/ops_common.php';

/** SeaPackages.dc.html: KPIs + server-side list. */
function sl_seaPackagesData($user, $core): array
{
    $d = [];
    $scope = sl_opsScope('a');
    $isClient = sl_userIs('client');
    $base = "FROM cdb_customers_packages a WHERE 1 = 1 $scope";
    $total = (int) sl_scalar("SELECT COUNT(*) $base");
    $open = (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier NOT IN (8,15,21)");
    $k = [
        ['icon' => 'i-warehouse', 'label' => 'At warehouse', 'n' => (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier IN (1,2,4)"), 'cls' => 'y', 'f' => 'warehouse'],
        ['icon' => 'i-ship', 'label' => 'In transit', 'n' => (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier IN (3,5,7,13)"), 'cls' => 'v', 'f' => 'transit'],
        ['icon' => 'i-key', 'label' => 'Ready for pickup', 'n' => (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier IN (6,32)"), 'cls' => 'o', 'f' => 'ready'],
        ['icon' => 'i-check', 'label' => 'Handed over', 'n' => (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier IN (8,15)"), 'cls' => '', 'f' => 'done'],
    ];
    foreach ($k as &$x) {
        $x['pct'] = sl_pct($x['n'], $total);
        $x['href'] = sl_url(['filter' => $x['f'], 'page' => null]);
    }
    unset($x);
    $d['kpi'] = $k;
    $d['total'] = $total;
    $d['open'] = $open;
    $next = sl_row("SELECT c_prefix, c_no, c_date FROM cdb_consolidate_packages WHERE status_courier NOT IN (8,15,21) ORDER BY consolidate_id DESC");
    $d['lede'] = sl_num($open) . ' open package' . ($open === 1 ? '' : 's') . ($next ? ' · next container ' . $next->c_prefix . $next->c_no . ' (' . sl_date($next->c_date) . ')' : '');

    $q = trim((string) ($_GET['q'] ?? ''));
    $filter = (string) ($_GET['filter'] ?? 'all');
    $status = (int) ($_GET['status'] ?? 0);
    $container = (int) ($_GET['container'] ?? 0);
    $view = ($_GET['view'] ?? 'list') === 'container' ? 'container' : 'list';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $where = "1 = 1 $scope";
    $binds = [];
    if ($q !== '') {
        $where .= " AND (CONCAT(a.order_prefix, a.order_no) LIKE :q OR a.tracking_purchase LIKE :q OR a.provider_purchase LIKE :q OR a.sender_id IN (SELECT id FROM cdb_users WHERE CONCAT(fname,' ',lname) LIKE :q OR locker LIKE :q))";
        $binds[':q'] = '%' . $q . '%';
    }
    switch ($filter) {
        case 'warehouse': $where .= ' AND a.status_courier IN (1,2,4)'; break;
        case 'transit': $where .= ' AND a.status_courier IN (3,5,7,13)'; break;
        case 'ready': $where .= ' AND a.status_courier IN (6,32)'; break;
        case 'done': $where .= ' AND a.status_courier IN (8,15)'; break;
        case 'unpaid': $where .= ' AND a.status_invoice <> 1'; break;
        case 'open': $where .= ' AND a.status_courier NOT IN (8,15,21)'; break;
    }
    if ($status > 0) {
        $where .= ' AND ' . cdp_effectiveStatusSql('a', true) . ' = ' . $status;
    }
    if ($container > 0) {
        $where .= " AND a.order_id IN (SELECT order_id FROM cdb_consolidate_packages_detail WHERE consolidate_id = $container)";
    }
    $count = (int) sl_scalar("SELECT COUNT(*) FROM cdb_customers_packages a WHERE $where", $binds);
    $order = $view === 'container' ? '(SELECT MAX(consolidate_id) FROM cdb_consolidate_packages_detail d WHERE d.order_id = a.order_id) DESC, a.order_id DESC' : 'a.order_id DESC';
    $rows = sl_rows("SELECT a.order_id, a.order_prefix, a.order_no, a.order_date, a.sender_id, a.driver_id, a.status_courier, a.status_invoice, a.total_order, a.total_weight, a.tracking_purchase, a.provider_purchase, a.is_consolidate, a.is_prealert,
            (SELECT COUNT(*) FROM cdb_customers_packages_detail i WHERE i.order_id = a.order_id) AS items,
            (SELECT GROUP_CONCAT(i.order_item_description SEPARATOR ', ') FROM cdb_customers_packages_detail i WHERE i.order_id = a.order_id) AS contents
        FROM cdb_customers_packages a WHERE $where ORDER BY $order LIMIT " . (($page - 1) * $per) . ", $per", $binds);
    $users = sl_usersById(array_map(fn($r) => $r->sender_id, $rows));
    $cons = sl_consolidationsFor(array_map(fn($r) => $r->order_id, $rows), true);
    $drivers = sl_usersById(array_map(fn($r) => $r->driver_id, $rows));
    $can = [
        'view' => $user->cdp_hasPermission('view_package_details'),
        'edit' => $user->cdp_hasPermission('edit_package'),
        'delete' => $user->cdp_hasPermission('delete_package'),
        'driver' => $user->cdp_hasPermission('assign_driver_package') && !$isClient,
        'print' => $user->cdp_hasPermission('print_package'),
        'label' => $user->cdp_hasPermission('print_label'),
        'track' => $user->cdp_hasPermission('track_package') && !$isClient,
        'deliver' => $user->cdp_hasPermission('deliver_package') && !$isClient,
        'email' => $user->cdp_hasPermission('send_email_attachment') && !$isClient,
        'verify' => $user->cdp_hasPermission('verify_payments') && !$isClient,
        'bulk' => $user->cdp_hasPermission('select_multiple_package') && !$isClient,
        'bulkStatus' => $user->cdp_hasPermission('select_change_status') && !$isClient,
    ];
    require_once CDP_ROOT . '/helpers/pickup_code.php';
    $can['code'] = cdp_pickupCodeCanUse($user);
    $list = [];
    foreach ($rows as $r) {
        $u = $users[(int) $r->sender_id] ?? null;
        $con = $cons[(int) $r->order_id] ?? null;
        $eff = $con && !cdp_isHandedOver($r->status_courier) && !in_array((int) $r->status_courier, [21, 12], true) ? $con['status'] : (int) $r->status_courier;
        [$payL, $payC] = sl_payPill($r->status_invoice);
        $done = cdp_isHandedOver($r->status_courier);
        $list[] = [
            'id' => (int) $r->order_id, 'trk' => $r->order_prefix . $r->order_no, 'date' => sl_date($r->order_date),
            'name' => sl_userName($u), 'ini' => sl_initials(sl_userName($u)), 'locker' => $u->locker ?? '', 'email' => $u->email ?? '',
            'box' => $con ? $con['code'] : '—', 'boxId' => $con['id'] ?? 0,
            'items' => (int) $r->items ?: 1, 'what' => $r->contents ?: ($r->provider_purchase ?: '—'), 'supplier' => $r->provider_purchase ?: '', 'postal' => $r->tracking_purchase ?: '',
            'w' => (float) $r->total_weight > 0 ? sl_weight($r->total_weight) : '—', 'amt' => sl_money($r->total_order),
            'pay' => $payL, 'payCls' => $payC, 'st' => sl_statusLabel($eff), 'stCls' => sl_statusClass($eff), 'inCon' => (bool) $con,
            'prealert' => (int) $r->is_prealert === 1, 'driver' => sl_userName($drivers[(int) $r->driver_id] ?? null),
            'done' => $done, 'cancelled' => (int) $r->status_courier === 21, 'paid' => (int) $r->status_invoice === 1, 'verify' => (int) $r->status_invoice === 3, 'unpaid' => (int) $r->status_invoice === 2,
            'canEdit' => $can['edit'] && !$con && !$done && (int) $r->status_invoice !== 1,
            'canDelete' => $can['delete'] && !$con && !$done && (int) $r->status_invoice !== 1,
            'canDriver' => $can['driver'] && !$con && !$done,
            'canTrack' => $can['track'] && !$done && !in_array((int) $r->status_courier, [21, 12], true),
            'canCode' => $can['code'] && !$done,
        ];
    }
    $d['rows'] = $list;
    $d['count'] = $count; $d['page'] = $page; $d['per'] = $per; $d['q'] = $q; $d['filter'] = $filter; $d['status'] = $status; $d['container'] = $container; $d['view'] = $view;
    $d['filters'] = [['f' => 'all', 'label' => 'All'], ['f' => 'open', 'label' => 'Open'], ['f' => 'ready', 'label' => 'Ready'], ['f' => 'unpaid', 'label' => 'Unpaid'], ['f' => 'done', 'label' => 'Handed over']];
    $d['statuses'] = sl_rows('SELECT id, mod_style FROM cdb_styles ORDER BY id');
    $d['containers'] = sl_rows('SELECT consolidate_id, c_prefix, c_no FROM cdb_consolidate_packages ORDER BY consolidate_id DESC LIMIT 60');
    $d['drivers'] = sl_rows('SELECT id, fname, lname FROM cdb_users WHERE userlevel = 3 AND active = 1 ORDER BY fname');
    $d['can'] = $can;
    $d['canAdd'] = $user->cdp_hasPermission('add_package') && !$isClient;
    $d['isClient'] = $isClient;
    return $d;
}

/** NewPackage.dc.html: lookups and defaults for the registration form. */
function sl_newPackageData($user, $core): array
{
    require_once CDP_ROOT . '/helpers/unique_ids.php';
    $d = sl_opsLookups();
    $d['customers'] = sl_opsCustomers();
    $d['containers'] = sl_openConsolidations(true);
    $defaults = cdp_getInfoShipDefault();
    $agency = cdp_getDefaultAgencyOrigin();
    $d['def'] = [
        'category' => (int) ($defaults->logistics_default1 ?? 0), 'packaging' => (int) ($defaults->packaging_default2 ?? 0),
        'courier' => (int) ($defaults->courier_default3 ?? 0), 'service' => (int) ($defaults->service_default4 ?? 0),
        'delitime' => (int) ($defaults->time_default5 ?? 0), 'agency' => $agency['agency'], 'office' => $agency['origin_off'],
    ];
    $d['prefix'] = (string) ($core->prefix_online_shopping ?: 'PK-');
    try {
        $d['nextNo'] = (string) cdp_uidNext('package_no');
    } catch (Throwable $e) {
        $d['nextNo'] = '';
    }
    $d['rate'] = (float) ($core->value_weight ?? 0);
    $d['tax'] = (float) ($core->tax ?? 0);
    $d['insurance'] = (float) ($core->insurance ?? 0);
    $d['meter'] = (float) ($core->meter ?: 500);
    $d['minTax'] = (float) ($core->min_cost_tax ?? 0);
    $d['unit'] = (string) ($core->weight_p ?? 'lb');
    $d['dim'] = (string) ($core->units ?? 'in');
    $d['today'] = date('Y-m-d');
    // pre-alert to import (?prealert_id=)
    $d['prealert'] = null;
    $pid = (int) ($_GET['prealert_id'] ?? 0);
    if ($pid > 0) {
        $p = sl_row('SELECT * FROM cdb_pre_alert WHERE pre_alert_id = :id AND is_package = 0', [':id' => $pid]);
        if ($p) {
            $d['prealert'] = ['id' => (int) $p->pre_alert_id, 'tracking' => $p->tracking, 'shop' => $p->provider_shop, 'courier' => (int) $p->courier_com, 'customer' => (int) $p->customer_id, 'price' => (float) $p->purchase_price, 'what' => $p->package_description, 'date' => sl_date($p->estimated_date)];
        }
    }
    $d['prealerts'] = sl_rows('SELECT p.pre_alert_id, p.tracking, p.provider_shop, p.customer_id, u.fname, u.lname FROM cdb_pre_alert p LEFT JOIN cdb_users u ON u.id = p.customer_id WHERE p.is_package = 0 ORDER BY p.pre_alert_id DESC LIMIT 50');
    return $d;
}

/** PackageDetail.dc.html for one package (?id=). */
function sl_packageDetailData($user, $core, int $id): ?array
{
    $p = sl_row('SELECT a.*, s.mod_style, s.color FROM cdb_customers_packages a LEFT JOIN cdb_styles s ON s.id = a.status_courier WHERE a.order_id = :id', [':id' => $id]);
    if (!$p || !sl_opsInScope($p)) {
        return null;
    }
    $d = [];
    $trk = $p->order_prefix . $p->order_no;
    $con = sl_consolidationsFor([$id], true)[$id] ?? null;
    $eff = $con && !cdp_isHandedOver($p->status_courier) ? $con['status'] : (int) $p->status_courier;
    $owner = sl_usersById([$p->sender_id])[(int) $p->sender_id] ?? null;
    $items = sl_rows('SELECT * FROM cdb_customers_packages_detail WHERE order_id = :id ORDER BY order_item_id', [':id' => $id]);
    $meter = (float) ($core->meter ?: 500);
    $vol = 0.0; $boxes = []; $n = 0;
    foreach ($items as $it) {
        $n++;
        $v = (float) $it->order_item_length * (float) $it->order_item_width * (float) $it->order_item_height / max(1, $meter);
        $vol += $v;
        $boxes[] = ['n' => $n, 'what' => $it->order_item_description, 'qty' => (float) $it->order_item_quantity, 'dims' => (float) $it->order_item_length . ' × ' . (float) $it->order_item_width . ' × ' . (float) $it->order_item_height . ' ' . ($core->units ?? 'in'), 'w' => sl_weight($it->order_item_weight), 'vol' => number_format($v, 1), 'declared' => sl_money($it->order_item_declared_value)];
    }
    $d['id'] = $id; $d['trk'] = $trk; $d['st'] = sl_statusLabel($eff); $d['stCls'] = sl_statusClass($eff); $d['raw'] = (int) $p->status_courier;
    $d['done'] = cdp_isHandedOver($p->status_courier); $d['handLabel'] = $d['done'] ? cdp_handoverLabel($p->status_courier) : '';
    $d['stage'] = sl_stageOf($eff, (bool) $con);
    $d['steps'] = ['At warehouse', 'Loaded', 'In transit', 'Ready for pickup', 'Handed over'];
    $eta = cdp_getEffectiveEta($id, $p->order_no, $p->order_deli_time, $p->is_consolidate, true);
    $d['eta'] = $eta && $eta !== 'N/A' ? (strtotime($eta) ? sl_date($eta, 'M j, Y') : $eta) : 'Not set';
    $office = sl_row('SELECT name_off, city FROM cdb_offices WHERE id = :id', [':id' => (int) $p->origin_off]);
    $d['office'] = $office ? $office->name_off . ($office->city ? ', ' . $office->city : '') : '—';
    $d['registered'] = sl_date($p->order_datetime ?: $p->order_date, 'M j, Y H:i');
    $d['boxes'] = $boxes; $d['boxN'] = $n; $d['vol'] = number_format($vol, 1); $d['weight'] = sl_weight($p->total_weight);
    $d['owner'] = ['id' => (int) $p->sender_id, 'name' => sl_userName($owner), 'ini' => sl_initials(sl_userName($owner)), 'locker' => $owner->locker ?? '', 'phone' => $owner->phone ?? '', 'email' => $owner->email ?? ''];
    $addr = sl_row('SELECT * FROM cdb_address_shipments WHERE order_track = :t', [':t' => $trk]);
    $d['deliverTo'] = $addr ? trim(implode(', ', array_filter([$addr->sender_address, $addr->sender_city, $addr->sender_country]))) : '—';
    $d['supplier'] = $p->provider_purchase ?: '—'; $d['postal'] = $p->tracking_purchase ?: '—'; $d['price'] = sl_money($p->price_purchase);
    $courier = sl_row('SELECT name_com FROM cdb_courier_com WHERE id = :id', [':id' => (int) $p->order_courier]);
    $d['courier'] = $courier->name_com ?? '—';
    [$d['pay'], $d['payCls']] = sl_payPill($p->status_invoice);
    $d['total'] = sl_money($p->total_order);
    $pm = sl_row('SELECT name_pay FROM cdb_met_payment WHERE id = :id', [':id' => (int) $p->order_pay_mode]);
    $d['method'] = $pm ? str_replace('_', ' ', $pm->name_pay) : '—';
    $d['paidOn'] = $p->payment_date ? sl_date($p->payment_date) : '—';
    $d['charges'] = [['Freight', sl_money($p->sub_total)], ['Insurance', sl_money($p->total_tax_insurance)], ['Customs', sl_money($p->total_tax_custom_tariffis)], ['Tax', sl_money($p->total_tax)], ['Discount', '−' . sl_money($p->total_tax_discount)]];
    $events = [];
    foreach (sl_trackHistory($trk) as $i => $t) {
        $events[] = ['cls' => $i === 0 ? 'now' : 'done', 't' => str_replace('_', ' ', (string) ($t->mod_style ?: $t->t_dest)), 's' => trim(sl_date($t->t_date, 'M j, Y H:i') . ($t->t_city ? ' · ' . $t->t_city : '') . ($t->comments && $t->comments !== $t->t_dest ? ' · ' . $t->comments : ''))];
    }
    $d['events'] = $events;
    $d['con'] = null;
    if ($con) {
        $c = sl_row('SELECT consolidate_id, c_prefix, c_no, c_date, status_courier, total_weight, (SELECT COUNT(*) FROM cdb_consolidate_packages_detail x WHERE x.consolidate_id = cdb_consolidate_packages.consolidate_id) AS n FROM cdb_consolidate_packages WHERE consolidate_id = :id', [':id' => $con['id']]);
        if ($c) {
            $d['con'] = ['id' => (int) $c->consolidate_id, 'code' => $c->c_prefix . $c->c_no, 'st' => sl_statusLabel($c->status_courier), 'cls' => sl_statusClass($c->status_courier), 'n' => (int) $c->n, 'w' => sl_weight($c->total_weight), 'date' => sl_date($c->c_date)];
        }
    }
    $d['files'] = sl_tableExists('cdb_customer_package_files') ? array_map(fn($f) => ['url' => $f->url, 'name' => $f->name, 'date' => sl_date($f->date_file)], sl_rows('SELECT url, name, date_file FROM cdb_customer_package_files WHERE order_id = :id ORDER BY id DESC', [':id' => $id])) : [];
    $isClient = sl_userIs('client');
    require_once CDP_ROOT . '/helpers/pickup_code.php';
    $d['can'] = [
        'edit' => $user->cdp_hasPermission('edit_package') && !$con && !$d['done'] && (int) $p->status_invoice !== 1 && !$isClient,
        'track' => $user->cdp_hasPermission('track_package') && !$d['done'] && !$isClient,
        'deliver' => $user->cdp_hasPermission('deliver_package') && !$d['done'] && !$isClient,
        'print' => $user->cdp_hasPermission('print_package'), 'label' => $user->cdp_hasPermission('print_label'),
        'code' => cdp_pickupCodeCanUse($user) && !$d['done'],
        'pay' => $isClient && (int) $p->status_invoice === 2,
        'delete' => $user->cdp_hasPermission('delete_package') && !$con && !$d['done'] && (int) $p->status_invoice !== 1 && !$isClient,
    ];
    $d['code'] = $d['can']['code'] ? sl_pickupCodeInfo('sea', [$id]) : ['verified' => false, 'live' => null];
    $d['statuses'] = sl_rows('SELECT id, mod_style FROM cdb_styles ORDER BY id');
    return $d;
}
