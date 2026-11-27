<?php
/**
 * Data for Consolidations, ConsolidationDetail / ConsolidationAir and NewConsolidation.
 * Air = cdb_consolidate + cdb_consolidate_detail (cdb_add_order);
 * Sea = cdb_consolidate_packages + cdb_consolidate_packages_detail (cdb_customers_packages).
 *
 * Consolidation pages are operational only: weights, volumes, customers, packing list, status, ETA
 * and documents. No money is read or passed here (no charges, totals, paid / unpaid, payment links):
 * consolidation billing lives in Finance (financial sheet, accounts receivable, the customer's My bills).
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
            'deliver' => 'deliver_consolidate_package', 'delete' => 'delete_consolidate_package', 'cancel' => 'cancel_consolidate_package', 'email' => 'send_consolidate_package_email',
        ] : [
            'list' => 'view_consolidate_list', 'view' => 'view_consolidate', 'add' => 'add_consolidate_shipment', 'edit' => 'edit_consolidate',
            'driver' => 'assign_driver_consolidate', 'print' => 'print_consolidate', 'label' => 'print_consolidate_label', 'track' => 'track_consolidate',
            'deliver' => 'deliver_consolidate', 'delete' => 'delete_consolidate', 'cancel' => 'cancel_consolidate', 'email' => 'send_consolidate_email',
        ],
        // No payment link here: consolidation billing is in Finance.
        'url' => $sea ? [
            'edit' => 'consolidate_package_edit.php', 'print' => 'print_consolidate_package.php', 'label' => 'print_label_consolidate_package.php', 'track' => 'consolidate_package_shipment_tracking.php',
            'deliver' => 'consolidate_package_deliver_shipment.php',
        ] : [
            'edit' => 'consolidate_edit.php', 'print' => 'print_consolidate.php', 'label' => 'print_label_consolidate.php', 'track' => 'consolidate_shipment_tracking.php',
            'deliver' => 'consolidate_deliver_shipment.php',
        ],
        'cap' => (float) sl_slSetting($sea ? 'consol_capacity_sea' : 'consol_capacity_air', $sea ? 12000 : 1500),
    ];
}

function sl_conCapClass(int $pct): string
{
    return $pct >= 95 ? 'r' : ($pct >= 70 ? 'y' : '');
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
    $dgCol = !$M['sea'] && sl_columnExists('cdb_add_order', 'is_dangerous_good') ? 'a.is_dangerous_good' : '0';
    $rows = sl_rows("SELECT a.order_id, a.order_prefix, a.order_no, a.sender_id, a.total_weight, a.status_courier, $dgCol AS dg, $postal AS postal,
            (SELECT GROUP_CONCAT(i.order_item_description SEPARATOR ', ') FROM {$M['items']} i WHERE i.order_id = a.order_id) AS what,
            (SELECT COALESCE(SUM(i.order_item_quantity),0) FROM {$M['items']} i WHERE i.order_id = a.order_id) AS pcs
        FROM $o a WHERE $w ORDER BY a.order_id DESC LIMIT $limit", $b);
    $users = sl_usersById(array_map(fn($r) => $r->sender_id, $rows));
    $out = [];
    foreach ($rows as $r) {
        $u = $users[(int) $r->sender_id] ?? null;
        $out[] = ['oid' => (int) $r->order_id, 'id' => $r->order_prefix . $r->order_no, 'name' => sl_userName($u), 'lock' => $u->locker ?? '', 'sender' => (int) $r->sender_id, 'what' => $r->what ?: '—', 'post' => $r->postal ?: '—', 'w' => sl_weight($r->total_weight), 'wn' => (float) $r->total_weight, 'pcs' => (int) $r->pcs,
            'st' => sl_statusLabel($r->status_courier), 'stC' => sl_statusClass($r->status_courier), 'approved' => (int) $r->status_courier === 10, 'dg' => (int) $r->dg === 1];
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
    $rows = sl_rows("SELECT d.detail_id, d.order_id, d.order_prefix, d.order_no, d.weight, d.weight_vol, a.sender_id, a.status_courier, $postal AS postal,
            (SELECT GROUP_CONCAT(i.order_item_description SEPARATOR ', ') FROM {$M['items']} i WHERE i.order_id = a.order_id) AS what,
            (SELECT COALESCE(SUM(i.order_item_quantity),0) FROM {$M['items']} i WHERE i.order_id = a.order_id) AS pcs
        FROM {$M['detail']} d LEFT JOIN $o a ON a.order_id = d.order_id WHERE d.consolidate_id = :id ORDER BY d.detail_id", [':id' => $id]);
    $users = sl_usersById(array_map(fn($r) => $r->sender_id, $rows));
    $list = []; $w = 0; $v = 0; $pcs = 0; $cust = []; $n = 0;
    $canEdit = $user->cdp_hasPermission($M['p']['edit']) && !cdp_isHandedOver($c->status_courier) && (int) $c->status_courier !== 21 && !sl_userIs('client');
    foreach ($rows as $r) {
        $u = $users[(int) $r->sender_id] ?? null;
        $n++;
        $w += (float) $r->weight; $v += (float) $r->weight_vol; $pcs += (int) $r->pcs; $cust[(int) $r->sender_id] = 1;
        $row = ['n' => $n, 'did' => (int) $r->detail_id, 'oid' => (int) $r->order_id, 'ini' => sl_initials(sl_userName($u)), 'name' => sl_userName($u), 'lock' => $u->locker ?? '', 'id' => $r->order_prefix . $r->order_no,
            'href' => $M['sea'] ? sl_route('PackageDetail', ['id' => (int) $r->order_id]) : sl_route('ShipmentDetail', ['id' => (int) $r->order_id]),
            'post' => $r->postal ?: '—', 'what' => $r->what ?: '—', 'pcs' => (int) $r->pcs, 'w' => sl_weight($r->weight), 'v' => number_format((float) $r->weight_vol, 1),
            'cls' => cdp_isHandedOver($r->status_courier) ? 'mut' : '', 'canDel' => $canEdit && !cdp_isHandedOver($r->status_courier),
            'stL' => sl_statusLabel($r->status_courier), 'stC' => sl_statusClass($r->status_courier), 'phone' => (string) ($u->phone ?? ''),
            'vn' => (float) $r->weight_vol, 'weightKg' => (float) $r->weight];
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
        'capacityKg' => (float) $M['cap'], 'weightKg' => (float) $w, 'pkN' => $n, 'custN' => count($cust), 'pcsN' => $pcs, 'wt' => sl_weight($w), 'wt2' => number_format($v, 1) . ' vol. weight', 'vTot' => number_format($v, 1),
        'rows' => $list, 'lq' => $lq, 'info' => $info, 'steps' => $steps,
        'flash' => isset($_GET['created']) ? 'Consolidation ' . $code . ' created.' : '',
        'adding' => !empty($_GET['add']) && $canEdit, 'canEdit' => $canEdit, 'q' => trim((string) ($_GET['q'] ?? '')),
        'can' => [
            'edit' => $canEdit ? $M['url']['edit'] . '?id=' . $id : '', 'print' => $user->cdp_hasPermission($M['p']['print']) ? $M['url']['print'] . '?id=' . $id : '',
            'label' => $user->cdp_hasPermission($M['p']['label']) ? $M['url']['label'] . '?id=' . $id : '', 'track' => $user->cdp_hasPermission($M['p']['track']) && !cdp_isHandedOver($s) && $s !== 21 ? $M['url']['track'] . '?id=' . $id : '',
            'deliver' => $user->cdp_hasPermission($M['p']['deliver']) && !cdp_isHandedOver($s) && $s !== 21 ? $M['url']['deliver'] . '?id=' . $id : '',
            // packing list (packing_list.php): customers -> packages -> items with weights, dangerous goods apart
            'packing' => $user->cdp_hasPermission($M['p']['print']) && !sl_userIs('client') ? 'packing_list.php?id=' . $id . '&type=' . $M['mode'] : '',
            'packingX' => $user->cdp_hasPermission($M['p']['print']) && !sl_userIs('client') ? 'packing_list.php?id=' . $id . '&type=' . $M['mode'] . '&format=csv' : '',
            'notify' => $user->cdp_hasPermission('push_notifications'),
            'delete' => $user->cdp_hasPermission($M['p']['delete']) && !cdp_isHandedOver($s) && $s !== 21 && !sl_userIs('client'),
        ],
        // is_consolidate 1 = air, 2 = sea (air and sea ids collide). Legacy sea files were stored
        // as 1: sea still shows those, but only when no air consolidation has the same id, so
        // air #N's attachments never appear on sea #N (and vice versa).
        'files' => array_map(fn($f) => ['url' => $f->url, 'name' => $f->name], sl_rows('SELECT url, name FROM cdb_order_files WHERE order_id = :id AND ' . ($M['sea'] ? '(is_consolidate = 2 OR (is_consolidate = 1 AND NOT EXISTS (SELECT 1 FROM cdb_consolidate x WHERE x.consolidate_id = cdb_order_files.order_id)))' : 'is_consolidate = 1') . ' ORDER BY id DESC', [':id' => $id])),
        'other' => [],
    ];
    $d['avail'] = $d['adding'] ? sl_conAvailable($M, $d['q'], 1000, $M['sea'] ? -1 : (int) ($c->is_dangerous_good ?? 0)) : [];
    $d['noAvail'] = $d['adding'] && !$d['avail'];
    // the "other mode" chip: the latest consolidation of the other mode the user may see
    $om = sl_conMode($M['sea'] ? 'air' : 'sea');
    if ($user->cdp_hasPermission([$om['p']['view'], $om['p']['list']])) {
        $x = sl_row("SELECT consolidate_id, c_prefix, c_no FROM {$om['head']} c WHERE c.status_courier <> '21' " . sl_opsScope('c') . ' ORDER BY consolidate_id DESC');
        if ($x) $d['other'] = ['id' => (int) $x->consolidate_id, 'code' => $x->c_prefix . $x->c_no, 'type' => $om['mode']];
    }
    $d['canNew'] = $user->cdp_hasPermission($M['p']['add']);

    // ── map and package pop-ups (views/sl/pages/ConsolidationDetail.php) ──
    require_once __DIR__ . '/track_console.php';
    $lane = sl_tcLanes()[$M['mode']];
    $departed = null;
    foreach (array_reverse($track) as $ev) { if (sl_stageOf($ev->status_courier) >= 3) { $departed = $ev->t_date; break; } }
    $p = sl_tcProgress($s, $departed ?: $c->c_date, $c->estimated_eta ?? null);
    $g = sl_tcGeometry($lane, $p);
    $d['map'] = ['geo' => $g, 'lane' => $lane, 'pct' => (int) round($p * 100), 'glows' => sl_tcGlows(), 'arrived' => $stage >= 4,
        'where' => $stage >= 4 ? $lane['to']['city'] : ($p < 0.1 ? $lane['from']['city'] . ' (US warehouse)' : ($M['sea'] ? 'Atlantic Ocean' : 'En route to ' . $lane['to']['city'])),
        'eta' => !empty($c->estimated_eta) ? sl_date($c->estimated_eta) : 'Not set', 'onway' => $departed ? sl_tcSince($departed) : 'Not departed',
        'late' => $stage < 4 && !empty($c->estimated_eta) && strtotime($c->estimated_eta . ' 23:59:59') < time()];
    // "Estimated arrival" figure of the KPI row
    $days = !empty($c->estimated_eta) ? (int) round((strtotime($c->estimated_eta) - strtotime('today')) / 86400) : null;
    $d['arr'] = $stage >= 4 ? ['b' => $stage >= 5 ? 'Handed over' : 'Arrived', 's' => 'in ' . $lane['to']['city'], 'cls' => '']
        : ($days === null ? ['b' => 'Not set', 's' => $canEdit ? 'add the ETA with Edit' : 'no ETA yet', 'cls' => '']
        : ['b' => sl_date($c->estimated_eta), 's' => $days < 0 ? abs($days) . ' day' . ($days === -1 ? '' : 's') . ' past ETA' : ($days === 0 ? 'due today' : 'in ' . $days . ' day' . ($days === 1 ? '' : 's')), 'cls' => $days < 0 ? 'bad' : '']);
    // last tracking events of every package in one query
    $hist = [];
    $codes = array_map(fn($r) => $r['id'], $list);
    if ($codes) {
        $b = []; $in = [];
        foreach (array_values($codes) as $i => $code) { $b[':t' . $i] = $code; $in[] = ':t' . $i; }
        foreach (sl_rows('SELECT t.order_track, t.t_date, t.comments, t.t_city, s.mod_style FROM cdb_courier_track t LEFT JOIN cdb_styles s ON s.id = t.status_courier WHERE t.order_track IN (' . implode(',', $in) . ') ORDER BY t.t_date DESC, t.id DESC', $b) as $ev) {
            if (count($hist[$ev->order_track] ?? []) >= 6) continue;
            $hist[$ev->order_track][] = ['t' => str_replace('_', ' ', (string) ($ev->mod_style ?: 'Update')), 'c' => trim((string) $ev->comments . ($ev->t_city ? ' · ' . $ev->t_city : ''), ' ·'), 'at' => sl_date($ev->t_date, 'M j, H:i')];
        }
    }
    foreach ($d['rows'] as &$row) $row['hist'] = $hist[$row['id']] ?? [];
    unset($row);
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
    // no payment fields and no quote / invoice / payment statuses on a consolidation
    unset($L['payments'], $L['paymethods']);
    $L['statuses'] = sl_conStatuses($L['statuses']);
    $def = cdp_getInfoShipDefault();
    $ag = cdp_getDefaultAgencyOrigin();
    try { $num = (string) cdp_uidNext('consolidate_no'); } catch (Throwable $e) { $num = ''; }
    // With random numbering (company setting) the number is only known once it is claimed on save.
    $random = function_exists('cdp_uidRandomMode') && cdp_uidRandomMode('consolidate_no');
    if ($random) $num = '';
    $modes = [];
    foreach (['air' => ['i-plane', 'Air', 'Air loads, by weight'], 'sea' => ['i-ship', 'Sea', 'Containers, by volume']] as $m => [$ic, $lab, $desc]) {
        $mm = sl_conMode($m);
        if (!$user->cdp_hasPermission($mm['p']['add'])) continue;
        $modes[] = ['m' => $m, 'cls' => $m === $M['mode'] ? 'on' : '', 'icon' => $ic, 'label' => $lab, 'd' => $desc, 'href' => sl_route('NewConsolidation', ['type' => $m])];
    }
    $country = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($_GET['cc'] ?? 'US'))) ?: 'US';
    $avail = sl_conAvailable($M, $q, 1000, $M['sea'] ? -1 : $dg);
    return [
        'type' => $M['mode'], 'sea' => $M['sea'], 'modes' => $modes, 'q' => $q, 'dg' => $dg,
        // The code is generated on save and cannot be edited: air = consolidation prefix + number,
        // sea = "C" + origin country + number (sl_conPrefix, used by the save handler too).
        'prefix' => sl_conPrefix($M['sea'], $country, $core), 'num' => $num, 'random' => $random, 'country' => $country, 'countries' => sl_conCountries(),
        'avail' => $avail, 'approvedN' => count(array_filter($avail, fn($a) => $a['approved'])), 'L' => $L,
        'def' => ['office' => $ag['origin_off'], 'courier' => (int) ($def->courier_default3 ?? 0), 'delitime' => (int) ($def->time_default5 ?? 0), 'status' => (int) ($def->status_default8 ?? 13) ?: 13],
        'unit' => (string) ($core->weight_p ?? 'lbs'),
        'today' => date('Y-m-d\TH:i'), 'eta' => date('Y-m-d', strtotime($M['sea'] ? '+40 days' : '+5 days')), 'dest' => $M['sea'] ? 'Tema' : 'Accra',
        'awbReady' => cdp_awbColumnReady(),
    ];
}

/**
 * Statuses a consolidation may be set to: everything except the quote, invoice and payment statuses
 * (17 Quotation, 18 Pending quote, 19 Invoiced, 23 Pending payment), which belong to billing in Finance.
 * $keep stays in the list (the current status on the edit page), so saving never changes it silently.
 */
function sl_conStatuses(array $statuses, int $keep = 0): array
{
    return array_values(array_filter($statuses, fn($s) => !in_array((int) $s->id, [17, 18, 19, 23], true) || (int) $s->id === $keep));
}

/** Prefix of a new consolidation's code. Air: the consolidation prefix setting. Sea: "C" + origin country (e.g. CUS, CCN). */
function sl_conPrefix(bool $sea, string $country, $core): string
{
    if ($sea) {
        $cc = strtoupper(preg_replace('/[^A-Za-z]/', '', $country));
        return 'C' . (strlen($cc) === 2 ? $cc : 'US');
    }
    return (string) (($core->prefix_consolidate ?? '') ?: 'AIR-');
}

/** Origin countries offered for sea containers (code => name). */
function sl_conCountries(): array
{
    return ['US' => 'United States', 'CN' => 'China', 'GB' => 'United Kingdom', 'AE' => 'United Arab Emirates', 'TR' => 'Turkey', 'DE' => 'Germany', 'NL' => 'Netherlands', 'IN' => 'India', 'CA' => 'Canada', 'BE' => 'Belgium', 'IT' => 'Italy', 'FR' => 'France', 'ES' => 'Spain', 'ZA' => 'South Africa', 'NG' => 'Nigeria'];
}

/**
 * Consolidations board (consolidate_list.php): Air and Sea statistics kept apart, then the console.
 * Stages (same definitions as the list filters above):
 *   loading  = still at the US warehouse, accepting parcels (not departed, not arrived)
 *   transit  = in the air / at sea (3, 5, 7)
 *   arrived  = arrived, not handed over yet (2, 4, 6, 32)
 *   handed   = handed over this month (8, 15)
 * ?mode=air|sea and ?stage=loading|transit|late|ready|done narrow the console below the statistics.
 */
function sl_consolidationsBoard($user, $core): array
{
    require_once __DIR__ . '/track_console.php';
    require_once __DIR__ . '/overview_hero.php';
    $can = ['air' => $user->cdp_hasPermission('view_consolidate_list'), 'sea' => $user->cdp_hasPermission('view_consolidate_package_list')];
    $scope = sl_opsScope('c');
    $modes = [];
    foreach (['air', 'sea'] as $m) {
        if (!$can[$m]) continue;
        $M = sl_conMode($m);
        $t = $M['head']; $dt = $M['detail'];
        $eta = sl_columnExists($t, 'estimated_eta') ? 'c.estimated_eta' : 'NULL';
        $n = fn(string $w) => (int) sl_scalar("SELECT COUNT(*) FROM $t c WHERE $w $scope");
        $parcels = fn(string $w) => (int) sl_scalar("SELECT COUNT(*) FROM $dt d JOIN $t c ON c.consolidate_id = d.consolidate_id WHERE $w $scope");
        $wLoad = "c.status_courier NOT IN ('3','5','7','2','4','6','32','8','15','21','12')";
        $wTran = "c.status_courier IN ('3','5','7')";
        $wArr = "c.status_courier IN ('2','4','6','32')";
        $wDone = "c.status_courier IN ('8','15') AND c.c_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        $load = sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(c.total_weight),0) AS w, MIN(c.c_date) AS oldest FROM $t c WHERE $wLoad $scope");
        $late = $eta === 'NULL' ? 0 : $n("$wTran AND $eta IS NOT NULL AND $eta < CURDATE()");
        $next = $eta === 'NULL' ? null : sl_row("SELECT CONCAT(c.c_prefix, c.c_no) AS code, $eta AS eta FROM $t c WHERE $wTran AND $eta >= CURDATE() $scope ORDER BY $eta ASC");
        $loadN = (int) $load->n;
        $onWay = $parcels($wTran);
        $fill = $loadN ? sl_pct((float) $load->w / $loadN, $M['cap']) : 0;
        $daily = sl_ovZeroDays();
        foreach (sl_rows("SELECT DATE(c.c_date) AS day, COUNT(*) AS n FROM $t c WHERE c.c_date >= :d AND c.status_courier <> '21' $scope GROUP BY DATE(c.c_date)", [':d' => array_key_first($daily)]) as $r) if (isset($daily[$r->day])) $daily[$r->day] = (int) $r->n;
        $sea = $m === 'sea';
        $modes[$m] = [
            'mode' => $m, 'label' => $sea ? 'Sea consolidations' : 'Air consolidations', 'unit' => $sea ? 'containers' : 'loads',
            'icon' => $sea ? 'i-ship' : 'i-plane', 'prefix' => $sea ? 'Containers to Tema' : 'Air loads to Accra',
            'stages' => [
                ['k' => 'loading', 'l' => 'Loading at US warehouse', 's' => 'still taking parcels · ' . sl_num($parcels($wLoad)) . ' parcels in', 'n' => $loadN],
                ['k' => 'transit', 'l' => $sea ? 'At sea' : 'In the air', 's' => $late ? $late . ' past ETA' : ($next && $next->eta ? 'next arrives ' . sl_date($next->eta, 'M j') : 'none due'), 'n' => $n($wTran), 'bad' => $late > 0],
                ['k' => 'ready', 'l' => 'Arrived', 's' => 'awaiting handover · ' . sl_num($parcels($wArr)) . ' parcels', 'n' => $n($wArr)],
                ['k' => 'done', 'l' => 'Handed over', 's' => 'this month', 'n' => $n($wDone)],
            ],
            'facts' => [
                ['Average fill while loading', $loadN ? $fill . '% of ' . sl_weight($M['cap'], 0) : '—'],
                ['Loading the longest', $load->oldest ? (int) floor((time() - strtotime($load->oldest)) / 86400) . ' days' : '—'],
                ['Next arrival', $next ? $next->code . ' · ' . sl_date($next->eta, 'M j') : '—'],
                [$sea ? 'Parcels at sea' : 'Parcels in the air', sl_num($onWay) . ' parcel' . ($onWay === 1 ? '' : 's')],
            ],
            'fill' => $fill,
            'bars' => sl_sparkBars(array_values($daily), 4, array_map(fn($x) => date('D j M', strtotime($x)), array_keys($daily))), 'barsN' => array_sum($daily),
            'new' => $user->cdp_hasPermission($M['p']['add']) ? sl_route('NewConsolidation', ['type' => $m]) : '',
        ];
    }
    $mode = in_array($_GET['mode'] ?? '', ['air', 'sea'], true) && isset($modes[$_GET['mode']]) ? (string) $_GET['mode'] : '';
    $stage = in_array($_GET['stage'] ?? '', ['loading', 'transit', 'late', 'ready', 'done'], true) ? (string) $_GET['stage'] : 'all';
    $q = trim((string) ($_GET['q'] ?? ''));
    $tc = sl_trackConsoleData($user, $core, 'consolidations', 60, ['filter' => $stage, 'mode' => $mode, 'q' => $q]);
    // The shared console carries totals and payment state; none of it belongs on a consolidation page.
    // Its priority score also adds 1 for an unpaid invoice (payCls is 'ok' only when paid): take that back
    // out so priority here reflects only lateness, dwell and holds.
    foreach ($tc['items'] as &$it) {
        $s = max(0, (int) $it['prio']['score'] - (($it['payCls'] ?? '') === 'ok' ? 0 : 1));
        $it['prio'] = array_merge($it['prio'], ['score' => $s, 'label' => $s >= 3 ? 'High' : ($s >= 1 ? 'Normal' : 'Low'), 'cls' => $s >= 3 ? 'bad' : ($s >= 1 ? 'warn' : 'ok')]);
        $it = array_merge($it, ['money' => false, 'total' => '', 'sub' => '', 'tax' => '', 'pay' => '', 'payCls' => '']);
    }
    unset($it);
    return ['modes' => $modes, 'mode' => $mode, 'stage' => $stage, 'q' => $q, 'tracking' => $tc];
}

/**
 * Packing list of one consolidation (packing_list.php): customers -> packages -> items, with weights,
 * dangerous goods in their own section. No prices. Staff only (the list shows every customer).
 */
function sl_packingListData($user, $core, int $id, string $type): ?array
{
    $M = sl_conMode($type);
    if (!$user->cdp_hasPermission([$M['p']['print'], $M['p']['view'], $M['p']['list']]) || sl_userIs('client')) return null;
    $c = sl_row("SELECT * FROM {$M['head']} c WHERE c.consolidate_id = :id", [':id' => $id]);
    if (!$c || !sl_opsInScope($c)) return null;
    $o = $M['orders'];
    $dgCol = sl_columnExists($o, 'is_dangerous_good') ? 'a.is_dangerous_good' : '0';
    $postal = $M['sea'] ? 'a.tracking_purchase' : "(SELECT t.tracking_number FROM cdb_package_tracking_number t WHERE t.order_id = a.order_id ORDER BY t.id DESC LIMIT 1)";
    $pk = sl_rows("SELECT d.order_id, d.order_prefix, d.order_no, d.weight, a.sender_id, a.total_weight, $dgCol AS dg, $postal AS postal
        FROM {$M['detail']} d LEFT JOIN $o a ON a.order_id = d.order_id WHERE d.consolidate_id = :id ORDER BY d.detail_id", [':id' => $id]);
    $items = [];
    $ids = array_values(array_filter(array_map(fn($r) => (int) $r->order_id, $pk)));
    if ($ids) {
        foreach (sl_rows("SELECT order_id, order_item_description AS what, order_item_quantity AS qty, order_item_weight AS w FROM {$M['items']} WHERE order_id IN (" . implode(',', $ids) . ') ORDER BY order_item_id') as $it) {
            $items[(int) $it->order_id][] = ['what' => trim((string) $it->what) ?: '—', 'qty' => (int) $it->qty, 'w' => (float) $it->w];
        }
    }
    $users = sl_usersById(array_map(fn($r) => $r->sender_id, $pk));
    $sections = ['normal' => [], 'dg' => []];
    $sn = 0;
    // customers in name order, packages in loading order
    usort($pk, fn($a, $b) => strcasecmp(sl_userName($users[(int) $a->sender_id] ?? null), sl_userName($users[(int) $b->sender_id] ?? null)));
    foreach ($pk as $r) {
        $sec = (int) $r->dg === 1 ? 'dg' : 'normal';
        $uid = (int) $r->sender_id;
        $u = $users[$uid] ?? null;
        $w = (float) ($r->weight ?: $r->total_weight);
        if (!isset($sections[$sec][$uid])) $sections[$sec][$uid] = ['name' => sl_userName($u), 'locker' => (string) ($u->locker ?? ''), 'phone' => (string) ($u->phone ?? ''), 'pk' => [], 'w' => 0.0, 'pcs' => 0];
        $its = $items[(int) $r->order_id] ?? [];
        $pcs = array_sum(array_column($its, 'qty'));
        $sections[$sec][$uid]['pk'][] = ['sn' => ++$sn, 'code' => $r->order_prefix . $r->order_no, 'post' => (string) ($r->postal ?: '—'), 'w' => $w, 'items' => $its, 'pcs' => $pcs];
        $sections[$sec][$uid]['w'] += $w;
        $sections[$sec][$uid]['pcs'] += $pcs;
    }
    $tot = function (array $cust) { return ['cust' => count($cust), 'pk' => array_sum(array_map(fn($c) => count($c['pk']), $cust)), 'pcs' => array_sum(array_column($cust, 'pcs')), 'w' => array_sum(array_column($cust, 'w'))]; };
    $courier = sl_scalar('SELECT name_com FROM cdb_courier_com WHERE id = :id', [':id' => (int) $c->order_courier], '—');
    $office = sl_row('SELECT name_off, city FROM cdb_offices WHERE id = :id', [':id' => (int) $c->origin_off]);
    return [
        'cid' => $id, 'type' => $M['mode'], 'sea' => $M['sea'], 'code' => $c->c_prefix . $c->c_no, 'site' => (string) ($core->site_name ?? 'Swift Lane'),
        'unit' => (string) ($core->weight_p ?? 'lbs'), 'status' => sl_statusLabel($c->status_courier),
        'facts' => array_values(array_filter([
            [$M['sea'] ? 'Shipping line' : 'Airline', $courier],
            [$M['sea'] ? 'Bill of lading' : 'Air waybill', ($c->awb_no ?? '') ?: '—'],
            $M['sea'] && !empty($c->seals_package) ? ['Seals', $c->seals_package] : null,
            ['Origin', $office ? ($office->name_off . ($office->city ? ' · ' . $office->city : '')) : '—'],
            ['Shipped', sl_date($c->order_datetime ?: $c->c_date, 'M j, Y H:i')],
            ['ETA', !empty($c->estimated_eta) ? sl_date($c->estimated_eta) : '—'],
        ])),
        'sections' => array_filter(['normal' => $sections['normal'], 'dg' => $sections['dg']]),
        'totals' => ['normal' => $tot($sections['normal']), 'dg' => $tot($sections['dg']), 'all' => ['cust' => count(array_unique(array_merge(array_keys($sections['normal']), array_keys($sections['dg']))))] + $tot(array_merge(array_values($sections['normal']), array_values($sections['dg'])))],
        'back' => sl_route('ConsolidationDetail', ['id' => $id, 'type' => $M['mode']]),
    ];
}
