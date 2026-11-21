<?php
/**
 * Smart Search (locker_search.php): one box that searches the whole system for staff.
 * Customers (name, locker, phone, e-mail), air shipments, sea packages, pickups, consolidations
 * (and the parcels inside them), pre-alerts, recipients, support tickets, staff and riders, and the
 * pages of the app. Each group is shown only to roles that may open it and is limited to the
 * viewer's agency (sl_opsScope). When the best match is a customer, their locker card, open parcels
 * and virtual mailbox open underneath. Questions go to the AI assistant (StaffAI ?q=).
 */
require_once __DIR__ . '/ops_common.php';
require_once CDP_ROOT . '/helpers/sl_nav.php';

/** Readable virtual mailbox address: the setting is stored as HTML with placeholders. */
function sl_ssMailbox(string $raw, string $name, string $locker): array
{
    $t = html_entity_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $t);
    $t = preg_replace('#</\s*(p|div|li)\s*>#i', "\n", $t);
    $t = strip_tags($t);
    $t = str_ireplace(['[fullname]', '(locker id)', '[locker id]', 'locker id', '[locker]'], [$name, $locker, $locker, $locker, $locker], $t);
    $lines = array_map('trim', explode("\n", str_replace("\r", '', $t)));
    // blocks separated by blank lines (US warehouse, China office, UK ...)
    $blocks = []; $cur = [];
    foreach ($lines as $l) {
        if ($l === '' || preg_match('/^[-_—=]{3,}$/u', $l)) { if ($cur) { $blocks[] = $cur; $cur = []; } continue; }
        $cur[] = $l;
    }
    if ($cur) $blocks[] = $cur;
    return $blocks;
}

function sl_smartSearchData($user, $core): array
{
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120);
    $in = (string) ($_GET['in'] ?? 'all');
    $d = ['q' => $q, 'in' => $in, 'groups' => [], 'best' => null, 'cust' => null, 'total' => 0, 'kind' => '', 'recent' => [],
        'canAi' => function_exists('sl_navAllowed') && sl_navAllowed('ai:use', [])];
    $can = fn($p) => $user->cdp_hasPermission($p);
    $scope = sl_opsScope('a');
    $like = '%' . $q . '%';
    // what the text looks like, so exact matches lead
    $d['kind'] = $q === '' ? '' : (preg_match('/^[A-Za-z]{2,5}\d{4,}$/', $q) ? 'tracking' : (preg_match('/^\+?\d[\d\s-]{6,}$/', $q) ? 'phone' : (str_contains($q, '@') ? 'email' : (preg_match('/^[A-Za-z]{0,4}\s?\d{3,7}$/', $q) ? 'locker' : 'text'))));
    if ($q === '') {
        $d['tips'] = [['SAS917384', 'a tracking number'], ['374671', 'a locker number'], ['Henry Mensah', 'a customer'], ['0243715048', 'a phone number'], ['MSKU', 'a container or consolidation'], ['held parcels', 'a page or action']];
        return $d;
    }
    $groups = [];
    $add = function (string $key, string $label, string $icon, array $items, int $total, string $more = '') use (&$groups) {
        if ($items) $groups[$key] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'items' => $items, 'total' => $total, 'more' => $more];
    };
    $lim = $in === 'all' ? 6 : 50;

    // customers
    if ($can(['view_client_list', 'view_shipment_list'])) {
        $ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : [];
        $cs = !empty($ctx['is_restricted']) && !empty($ctx['agency_id']) ? ' AND u.agency_id = ' . (int) $ctx['agency_id'] : '';
        $w = "u.userlevel = 1$cs AND (u.locker LIKE :a OR CONCAT_WS(' ', u.fname, u.lname) LIKE :b OR u.phone LIKE :c OR u.email LIKE :e
            OR u.id IN (SELECT sender_id FROM cdb_add_order WHERE CONCAT(order_prefix, order_no) = :t1) OR u.id IN (SELECT sender_id FROM cdb_customers_packages WHERE CONCAT(order_prefix, order_no) = :t2 OR tracking_purchase = :t3))";
        $b = [':a' => $like, ':b' => $like, ':c' => '%' . preg_replace('/\D/', '', $q) . '%', ':e' => $like, ':t1' => $q, ':t2' => $q, ':t3' => $q];
        if (preg_replace('/\D/', '', $q) === '') $b[':c'] = '__none__';
        $n = (int) sl_scalar("SELECT COUNT(*) FROM cdb_users u WHERE $w", $b);
        $items = [];
        foreach (sl_rows("SELECT u.id, u.fname, u.lname, u.locker, u.email, u.phone, u.active, u.created,
                (SELECT COUNT(*) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.is_deleted = 0 AND o.status_courier NOT IN (8,15,21)) AS openA,
                (SELECT COUNT(*) FROM cdb_customers_packages p WHERE p.sender_id = u.id AND p.status_courier NOT IN (8,15,21)) AS openS
            FROM cdb_users u WHERE $w ORDER BY (u.locker = :x) DESC, (CONCAT_WS(' ', u.fname, u.lname) = :y) DESC, u.fname LIMIT $lim", $b + [':x' => $q, ':y' => $q]) as $u) {
            $items[] = ['id' => (int) $u->id, 't' => sl_userName($u), 's' => implode(' · ', array_filter([$u->locker ? 'Locker ' . $u->locker : '', $u->phone, $u->email])),
                'm' => ((int) $u->openA + (int) $u->openS) . ' open', 'ini' => sl_initials(sl_userName($u)), 'href' => sl_url(['cust' => (int) $u->id]),
                'exact' => (string) $u->locker === $q, 'badge' => (int) $u->active === 1 ? '' : 'Inactive'];
        }
        $add('customers', 'Customers', 'i-users', $items, $n);
    }
    // air shipments and pickups
    if ($can(['view_shipment_list', 'view_general_shipments'])) {
        $w = "a.is_deleted = 0$scope AND (CONCAT(a.order_prefix, a.order_no) LIKE :a OR a.order_id IN (SELECT t.order_id FROM cdb_package_tracking_number t WHERE t.tracking_number LIKE :b)
            OR a.order_id IN (SELECT s.order_id FROM cdb_address_shipments s WHERE s.recipient_address LIKE :c))";
        $b = [':a' => $like, ':b' => $like, ':c' => $like];
        foreach ([0 => ['shipments', 'Air shipments', 'i-plane'], 1 => ['pickups', 'Pickups', 'i-truck']] as $pk => [$key, $label, $icon]) {
            if ($pk === 1 && !$can('view_pickup_list')) continue;
            $ww = "$w AND a.is_pickup = $pk";
            $n = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE $ww", $b);
            $items = [];
            foreach (sl_rows("SELECT a.order_id, CONCAT(a.order_prefix, a.order_no) AS code, a.status_courier, a.order_date, a.total_weight, a.sender_id FROM cdb_add_order a WHERE $ww ORDER BY (CONCAT(a.order_prefix, a.order_no) = :x) DESC, a.order_id DESC LIMIT $lim", $b + [':x' => $q]) as $r) $items[] = $r;
            $us = sl_usersById(array_map(fn($r) => $r->sender_id, $items));
            $items = array_map(fn($r) => ['t' => $r->code, 's' => sl_userName($us[(int) $r->sender_id] ?? null) . ' · ' . sl_weight($r->total_weight) . ' · ' . sl_date($r->order_date, 'M j'), 'st' => sl_statusLabel($r->status_courier), 'cls' => sl_statusClass($r->status_courier),
                'href' => sl_route('ShipmentDetail', ['id' => (int) $r->order_id]), 'track' => sl_route('LiveTracking', ['q' => $r->code]), 'exact' => strcasecmp($r->code, $q) === 0, 'mono' => true], $items);
            $add($key, $label, $icon, $items, $n);
        }
    }
    // sea packages
    if ($can(['view_package_list', 'view_general_package_records'])) {
        $w = "1 = 1$scope AND (CONCAT(a.order_prefix, a.order_no) LIKE :a OR a.tracking_purchase LIKE :b OR a.provider_purchase LIKE :c)";
        $b = [':a' => $like, ':b' => $like, ':c' => $like];
        $n = (int) sl_scalar("SELECT COUNT(*) FROM cdb_customers_packages a WHERE $w", $b);
        $rows = sl_rows("SELECT a.order_id, CONCAT(a.order_prefix, a.order_no) AS code, a.tracking_purchase, a.provider_purchase, a.status_courier, a.order_date, a.total_weight, a.sender_id FROM cdb_customers_packages a WHERE $w ORDER BY (CONCAT(a.order_prefix, a.order_no) = :x OR a.tracking_purchase = :y) DESC, a.order_id DESC LIMIT $lim", $b + [':x' => $q, ':y' => $q]);
        $us = sl_usersById(array_map(fn($r) => $r->sender_id, $rows));
        $add('packages', 'Sea packages', 'i-ship', array_map(fn($r) => ['t' => $r->code, 's' => implode(' · ', array_filter([sl_userName($us[(int) $r->sender_id] ?? null), $r->provider_purchase, $r->tracking_purchase ? 'Store ' . $r->tracking_purchase : '', sl_weight($r->total_weight)])),
            'st' => sl_statusLabel($r->status_courier), 'cls' => sl_statusClass($r->status_courier), 'href' => sl_route('PackageDetail', ['id' => (int) $r->order_id]), 'track' => sl_route('LiveTracking', ['q' => $r->code]),
            'exact' => strcasecmp($r->code, $q) === 0 || strcasecmp((string) $r->tracking_purchase, $q) === 0, 'mono' => true], $rows), $n);
    }
    // consolidations, including the parcels inside them
    $items = []; $n = 0;
    foreach ([['air', 'cdb_consolidate', 'cdb_consolidate_detail', 'view_consolidate_list'], ['sea', 'cdb_consolidate_packages', 'cdb_consolidate_packages_detail', 'view_consolidate_package_list']] as [$mode, $tbl, $det, $perm]) {
        if (!$can($perm)) continue;
        $w = "1 = 1$scope AND (CONCAT(a.c_prefix, a.c_no) LIKE :a OR a.seals_package LIKE :b OR a.consolidate_id IN (SELECT d.consolidate_id FROM $det d WHERE CONCAT(d.order_prefix, d.order_no) LIKE :c))";
        $b = [':a' => $like, ':b' => $like, ':c' => $like];
        $n += (int) sl_scalar("SELECT COUNT(*) FROM $tbl a WHERE $w", $b);
        foreach (sl_rows("SELECT a.consolidate_id, CONCAT(a.c_prefix, a.c_no) AS code, a.status_courier, a.c_date, a.total_weight, (SELECT COUNT(*) FROM $det d WHERE d.consolidate_id = a.consolidate_id) AS pcs FROM $tbl a WHERE $w ORDER BY a.consolidate_id DESC LIMIT $lim", $b) as $r) {
            $items[] = ['t' => $r->code, 's' => ucfirst($mode) . ' · ' . (int) $r->pcs . ' parcels · ' . sl_weight($r->total_weight) . ' · ' . sl_date($r->c_date, 'M j'), 'st' => sl_statusLabel($r->status_courier), 'cls' => sl_statusClass($r->status_courier),
                'href' => sl_route('ConsolidationDetail', ['id' => (int) $r->consolidate_id, 'type' => $mode]), 'track' => sl_route('LiveTracking', ['q' => $r->code, 'f' => 'con']), 'exact' => strcasecmp($r->code, $q) === 0, 'mono' => true];
        }
    }
    // containers and waybills from Waybills & Manifests
    if ($can('view_freight') && sl_tableExists('cdb_waybills')) {
        foreach (sl_rows("SELECT id, waybill_no, container_no, consolidation_no, carrier, status, pieces FROM cdb_waybills WHERE waybill_no LIKE :a OR container_no LIKE :b OR seal_no LIKE :c ORDER BY id DESC LIMIT $lim", [':a' => $like, ':b' => $like, ':c' => $like]) as $r) {
            $n++;
            $items[] = ['t' => $r->waybill_no, 's' => implode(' · ', array_filter(['Waybill', $r->container_no ? 'Container ' . $r->container_no : '', $r->carrier, (int) $r->pieces . ' pieces'])), 'st' => ucfirst((string) $r->status), 'cls' => '',
                'href' => sl_route('Waybills') . '#wb-' . (int) $r->id, 'exact' => strcasecmp($r->waybill_no, $q) === 0 || strcasecmp((string) $r->container_no, $q) === 0, 'mono' => true];
        }
    }
    $add('consolidations', 'Consolidations & containers', 'i-layers', array_slice($items, 0, $lim), $n);
    // pre-alerts
    if ($can('prealert_list')) {
        $w = 'p.tracking LIKE :a OR p.provider_shop LIKE :b OR p.package_description LIKE :c';
        $b = [':a' => $like, ':b' => $like, ':c' => $like];
        $n = (int) sl_scalar("SELECT COUNT(*) FROM cdb_pre_alert p WHERE $w", $b);
        $rows = sl_rows("SELECT p.pre_alert_id, p.tracking, p.provider_shop, p.package_description, p.prealert_date, p.customer_id, p.is_package FROM cdb_pre_alert p WHERE $w ORDER BY (p.tracking = :x) DESC, p.pre_alert_id DESC LIMIT $lim", $b + [':x' => $q]);
        $us = sl_usersById(array_map(fn($r) => $r->customer_id, $rows));
        $add('prealerts', 'Pre-alerts', 'i-inbox', array_map(fn($r) => ['t' => $r->tracking, 's' => implode(' · ', array_filter([sl_userName($us[(int) $r->customer_id] ?? null), $r->provider_shop, mb_strimwidth((string) $r->package_description, 0, 50, '…'), sl_date($r->prealert_date, 'M j')])),
            'st' => (int) $r->is_package ? 'Received' : 'Waiting', 'cls' => (int) $r->is_package ? 'delivered' : 'pending', 'href' => sl_route('PreAlerts', ['q' => $r->tracking]), 'exact' => strcasecmp((string) $r->tracking, $q) === 0, 'mono' => true], $rows), $n);
    }
    // recipients
    if ($can('view_recipients')) {
        $w = "CONCAT_WS(' ', r.fname, r.lname) LIKE :a OR r.phone LIKE :b OR r.email LIKE :c";
        $b = [':a' => $like, ':b' => $like, ':c' => $like];
        $rows = sl_rows("SELECT r.id, r.fname, r.lname, r.phone, r.email, r.sender_id FROM cdb_recipients r WHERE $w ORDER BY r.id DESC LIMIT $lim", $b);
        $us = sl_usersById(array_map(fn($r) => $r->sender_id, $rows));
        $add('recipients', 'Recipients', 'i-pin', array_map(fn($r) => ['t' => trim($r->fname . ' ' . $r->lname), 's' => implode(' · ', array_filter([$r->phone, $r->email, 'for ' . sl_userName($us[(int) $r->sender_id] ?? null)])), 'href' => sl_route('Recipients', ['q' => trim($r->fname . ' ' . $r->lname)])], $rows),
            (int) sl_scalar("SELECT COUNT(*) FROM cdb_recipients r WHERE $w", $b));
    }
    // support tickets
    if (sl_tableExists('cdb_cs_tickets') && function_exists('sl_navAllowed') && sl_navAllowed('cs:agent', [])) {
        $w = 't.public_ref LIKE :a OR t.subject LIKE :b OR t.contact_name LIKE :c OR t.contact_email LIKE :e OR t.contact_phone LIKE :f';
        $b = [':a' => $like, ':b' => $like, ':c' => $like, ':e' => $like, ':f' => $like];
        $add('tickets', 'Support tickets', 'i-ticket', array_map(fn($r) => ['t' => $r->public_ref . ' · ' . $r->subject, 's' => implode(' · ', array_filter([$r->contact_name, ucfirst(str_replace('_', ' ', $r->status)), sl_ago($r->last_activity_at)])), 'st' => ucfirst($r->priority), 'cls' => $r->priority === 'urgent' || $r->priority === 'high' ? 'overdue' : '',
            'href' => sl_route('CsTicket', ['id' => (int) $r->id]), 'exact' => strcasecmp($r->public_ref, $q) === 0],
            sl_rows("SELECT t.id, t.public_ref, t.subject, t.contact_name, t.status, t.priority, t.last_activity_at FROM cdb_cs_tickets t WHERE $w ORDER BY t.last_activity_at DESC LIMIT $lim", $b)),
            (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets t WHERE $w", $b));
    }
    // staff and riders
    if ($can('view_user_list')) {
        $w = "u.userlevel <> 1 AND (CONCAT_WS(' ', u.fname, u.lname) LIKE :a OR u.username LIKE :b OR u.email LIKE :c OR u.phone LIKE :e)";
        $b = [':a' => $like, ':b' => $like, ':c' => $like, ':e' => $like];
        $add('staff', 'Staff & riders', 'i-shield', array_map(fn($u) => ['t' => sl_userName($u), 's' => implode(' · ', array_filter([(int) $u->userlevel === 3 ? 'Rider' : 'Staff', $u->email, $u->phone])), 'ini' => sl_initials(sl_userName($u)),
            'href' => (int) $u->userlevel === 3 ? sl_route('RiderDetail', ['id' => (int) $u->id]) : sl_route('StaffRoles', ['q' => sl_userName($u)]), 'badge' => (int) $u->active === 1 ? '' : 'Inactive'],
            sl_rows("SELECT u.id, u.fname, u.lname, u.username, u.email, u.phone, u.userlevel, u.active FROM cdb_users u WHERE $w ORDER BY u.fname LIMIT $lim", $b)),
            (int) sl_scalar("SELECT COUNT(*) FROM cdb_users u WHERE $w", $b));
    }
    // pages and actions from the menu
    if (function_exists('sl_navStaff')) {
        $pages = [];
        $ql = mb_strtolower($q);
        foreach (sl_navStaff() as $g) foreach ($g['items'] as $it) {
            if (!sl_navAllowed($it['perm'] ?? null, [])) continue;
            if (str_contains(mb_strtolower($it['label']), $ql) || str_contains(mb_strtolower($g['label']), $ql)) $pages[] = ['t' => $it['label'], 's' => ucfirst(strtolower($g['label'])), 'href' => $it['href'] ?? sl_route($it['board']), 'icon' => 'i-' . ($it['icon'] ?? 'grid')];
        }
        $add('pages', 'Pages', 'i-grid', array_slice($pages, 0, $lim), count($pages));
    }

    // best match: an exact hit leads; a single customer opens their card
    foreach (['shipments', 'packages', 'consolidations', 'pickups', 'prealerts', 'tickets', 'customers'] as $k) {
        foreach ($groups[$k]['items'] ?? [] as $it) if (!empty($it['exact'])) { $d['best'] = $it + ['group' => $groups[$k]['label'], 'gicon' => $groups[$k]['icon']]; break 2; }
    }
    $custId = (int) ($_GET['cust'] ?? 0);
    if (!$custId && isset($groups['customers']) && (count($groups['customers']['items']) === 1 || !empty($groups['customers']['items'][0]['exact']))) $custId = $groups['customers']['items'][0]['id'];
    if ($custId && isset($groups['customers'])) $d['cust'] = sl_ssCustomer($user, $core, $custId);
    if ($in !== 'all') $groups = array_intersect_key($groups, [$in => 1]) ?: $groups;
    $d['groups'] = $groups;
    $d['total'] = array_sum(array_column($groups, 'total'));
    $d['counts'] = array_map(fn($g) => ['key' => $g['key'], 'label' => $g['label'], 'n' => $g['total']], $groups);
    return $d;
}

/** The customer card: details, open parcels, virtual mailbox and recent activity. */
function sl_ssCustomer($user, $core, int $id): ?array
{
    // ?cust= is user input: apply the same agency scope as the customers search group, so restricted
    // staff cannot open the card (contact details, parcels, mailbox) of another agency's customer.
    $ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : [];
    $cs = !empty($ctx['is_restricted']) && !empty($ctx['agency_id']) ? ' AND agency_id = ' . (int) $ctx['agency_id'] : '';
    $u = sl_row("SELECT id, fname, lname, locker, email, phone, active, approve, created FROM cdb_users WHERE id = :id AND userlevel = 1$cs", [':id' => $id]);
    if (!$u) return null;
    $name = sl_userName($u);
    $bayA = sl_columnExists('cdb_add_order', 'warehouse_bay') ? 'a.warehouse_bay' : 'NULL';
    $bayS = sl_columnExists('cdb_customers_packages', 'warehouse_bay') ? 'a.warehouse_bay' : 'NULL';
    $rows = sl_rows("(SELECT 'air' AS module, a.order_id, CONCAT(a.order_prefix,a.order_no) AS trk, (SELECT t.tracking_number FROM cdb_package_tracking_number t WHERE t.order_id = a.order_id ORDER BY t.id DESC LIMIT 1) AS postal, $bayA AS bay, a.total_weight, a.status_courier, a.status_invoice, a.order_date FROM cdb_add_order a WHERE a.sender_id = :u AND a.is_deleted = 0 AND a.status_courier NOT IN (8,15,21))
        UNION ALL (SELECT 'sea', a.order_id, CONCAT(a.order_prefix,a.order_no), a.tracking_purchase, $bayS, a.total_weight, a.status_courier, a.status_invoice, a.order_date FROM cdb_customers_packages a WHERE a.sender_id = :u2 AND a.status_courier NOT IN (8,15,21)) ORDER BY order_date DESC LIMIT 40", [':u' => $id, ':u2' => $id]);
    $parcels = []; $inWh = 0; $way = 0; $unpaid = 0; $weight = 0.0;
    foreach ($rows as $r) {
        $here = in_array((int) $r->status_courier, [4, 6, 32], true);
        $here ? $inWh++ : $way++;
        if (!in_array((int) $r->status_invoice, [1, 19], true)) $unpaid++;
        $weight += (float) $r->total_weight;
        [$pl, $pc] = sl_payPill($r->status_invoice);
        $parcels[] = ['id' => $r->trk, 'href' => $r->module === 'sea' ? sl_route('PackageDetail', ['id' => (int) $r->order_id]) : sl_route('ShipmentDetail', ['id' => (int) $r->order_id]), 'post' => $r->postal ?: '—', 'bay' => $r->bay ?: '—', 'w' => sl_weight($r->total_weight),
            'st' => sl_statusLabel($r->status_courier), 'cls' => sl_statusClass($r->status_courier), 'mode' => $r->module, 'pay' => $pl, 'payCls' => $pc, 'age' => sl_ago($r->order_date)];
    }
    $life = sl_row('SELECT COUNT(*) AS n, MAX(order_date) AS last FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0', [':u' => $id]);
    $lifeS = (int) sl_scalar('SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u', [':u' => $id]);
    $al = sl_row('SELECT name, country, city, cpostal FROM cdb_address_locker ORDER BY id LIMIT 1');
    $raw = trim((string) ($core->locker_address ?? '')) ?: (string) ($al->name ?? '');
    $blocks = sl_ssMailbox($raw, $name, (string) $u->locker);
    return [
        'id' => (int) $u->id, 'name' => $name, 'ini' => sl_initials($name), 'lock' => $u->locker ?: '—', 'email' => $u->email, 'phone' => $u->phone,
        'ok' => (int) $u->active === 1 && (int) ($u->approve ?? 1) === 1, 'since' => $u->created ? sl_date($u->created, 'M Y') : '',
        'parcels' => $parcels, 'inWh' => $inWh, 'way' => $way, 'unpaid' => $unpaid, 'weight' => sl_weight($weight),
        'lifetime' => (int) ($life->n ?? 0) + $lifeS, 'last' => !empty($life->last) ? sl_ago($life->last) : '—',
        'mailbox' => $blocks, 'mailboxCity' => $al ? trim($al->city . ' ' . $al->cpostal) : '', 'mailboxCountry' => $al->country ?? '',
        'copy' => implode("\n\n", array_map(fn($b) => implode("\n", $b), $blocks)),
        'canProfile' => $user->cdp_hasPermission('view_client_list'),
    ];
}
