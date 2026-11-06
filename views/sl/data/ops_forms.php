<?php
/**
 * Data providers for the Swift Lane operations forms that replaced the old
 * Bootstrap views: shipment / package edit, accept, multiple and pre-alert forms
 * (ShipmentForm), hand-over (Handover), tracking events (TrackingUpdate),
 * consolidation edit (ConsolidationEdit) and the warehouse delivery
 * consolidation tree (WarehouseConsolidation).
 *
 * Records load through the same query helpers the old views used
 * (helpers/querys.php) with the same guards: not found -> back to the list,
 * agency users (userlevel 6) only inside their agency.
 */
require_once CDP_ROOT . '/helpers/querys.php';
require_once CDP_ROOT . '/views/sl/data/ops_common.php';

/** Module table: how each kind of record is loaded, named and linked. */
function sl_opsKinds(): array
{
    return [
        'shipment'   => ['load' => 'cdp_getCourierPrint', 'table' => 'cdb_add_order', 'pk' => 'order_id', 'items' => 'cdb_add_order_item', 'pre' => 'order_prefix', 'no' => 'order_no', 'list' => 'courier_list.php', 'view' => 'courier_view.php', 'module' => 'air', 'files' => 'cdb_order_files', 'fileDel' => 'ajax/courier/courier_files_uploads_delete_ajax.php', 'label' => 'Shipment', 'nav' => 'air', 'statusType' => 1],
        'package'    => ['load' => 'cdp_getCustomerPackagePrint', 'table' => 'cdb_customers_packages', 'pk' => 'order_id', 'items' => 'cdb_customers_packages_detail', 'pre' => 'order_prefix', 'no' => 'order_no', 'list' => 'customer_packages_list.php', 'view' => 'customer_packages_view.php', 'module' => 'sea', 'files' => 'cdb_customer_package_files', 'fileDel' => 'ajax/customers_packages/customer_packages_files_uploads_delete_ajax.php', 'label' => 'Package', 'nav' => 'sea', 'statusType' => 1],
        'consol'     => ['load' => 'cdp_getConsolidatePrint', 'table' => 'cdb_consolidate', 'pk' => 'consolidate_id', 'detail' => 'cdb_consolidate_detail', 'child' => 'cdb_add_order', 'pre' => 'c_prefix', 'no' => 'c_no', 'list' => 'consolidate_list.php', 'view' => 'consolidate_view.php', 'module' => 'air', 'files' => 'cdb_order_files', 'fileDel' => 'ajax/consolidate/consolidate_files_uploads_delete_ajax.php', 'label' => 'Consolidation', 'nav' => 'consol', 'statusType' => 2],
        'consol_pkg' => ['load' => 'cdp_getConsolidatePackage', 'table' => 'cdb_consolidate_packages', 'pk' => 'consolidate_id', 'detail' => 'cdb_consolidate_packages_detail', 'child' => 'cdb_customers_packages', 'pre' => 'c_prefix', 'no' => 'c_no', 'list' => 'consolidate_package_list.php', 'view' => 'consolidate_package_view.php', 'module' => 'sea', 'files' => 'cdb_order_files', 'fileDel' => 'ajax/consolidate/consolidate_files_uploads_delete_ajax.php', 'label' => 'Sea consolidation', 'nav' => 'consol', 'statusType' => 2],
    ];
}

/** Load a record with the old views' guards. Redirects (and exits) like they did. */
function sl_opsRecord(string $kind, int $id, bool $denyClient = false): array
{
    global $user;
    $K = sl_opsKinds()[$kind];
    $userData = $user->cdp_getUserData();
    if ($denyClient && (int) $userData->userlevel === 1) {
        header('Location: login.php');
        exit;
    }
    $data = $id > 0 ? call_user_func($K['load'], $id) : ['rowCount' => 0];
    if ((int) ($data['rowCount'] ?? 0) !== 1) {
        header('Location: ' . $K['list']);
        exit;
    }
    $row = $data['data'];
    if ((int) $userData->userlevel === 6) {
        $aid = (int) cdp_getAgencyBranchIdForUser($userData->name_off ?? '');
        if ((int) ($row->agency ?? 0) !== $aid) {
            header('Location: error403.php');
            exit;
        }
    }
    $sender = sl_row('SELECT id, fname, lname, email, phone, locker FROM cdb_users WHERE id = :id', [':id' => (int) $row->sender_id]);
    $rtype = (string) ($row->recipient_type ?? 'recipient');
    $recip = (int) ($row->receiver_id ?? 0) > 0
        ? sl_row($rtype === 'user' ? 'SELECT id, fname, lname, email, phone FROM cdb_users WHERE id = :id' : 'SELECT id, fname, lname, email, phone FROM cdb_recipients WHERE id = :id', [':id' => (int) $row->receiver_id])
        : null;
    $track = $row->{$K['pre']} . $row->{$K['no']};
    $addr = sl_row('SELECT * FROM cdb_address_shipments WHERE order_track = :t', [':t' => $track]);
    return ['K' => $K, 'kind' => $kind, 'id' => $id, 'row' => $row, 'track' => $track, 'sender' => $sender, 'recip' => $recip, 'rtype' => $rtype, 'addr' => $addr, 'userData' => $userData];
}

/** The summary card every ops form shows on the right. */
function sl_opsSummary(array $r): array
{
    $o = $r['row'];
    $st = sl_row('SELECT mod_style, color FROM cdb_styles WHERE id = :id', [':id' => (int) $o->status_courier]);
    $kv = [
        ['Customer', sl_userName($r['sender']) . (!empty($r['sender']->locker) ? ' · ' . $r['sender']->locker : '')],
        ['Phone', $r['sender']->phone ?? ''],
        ['Recipient', $r['recip'] ? trim($r['recip']->fname . ' ' . $r['recip']->lname) : '—'],
    ];
    if ($r['addr']) {
        $kv[] = ['From', trim(($r['addr']->sender_city ?? '') . ', ' . ($r['addr']->sender_country ?? ''), ', ')];
        $kv[] = ['To', trim(($r['addr']->recipient_address ?? '') . ' ' . ($r['addr']->recipient_city ?? '') . ', ' . ($r['addr']->recipient_country ?? ''), ', ')];
    }
    if (isset($o->total_weight)) {
        $kv[] = ['Weight', sl_weight($o->total_weight)];
    }
    $date = $o->order_datetime ?? ($o->c_date ?? ($o->order_date ?? ''));
    $kv[] = ['Created', sl_date($date)];
    return ['track' => $r['track'], 'status' => str_replace('_', ' ', (string) ($st->mod_style ?? sl_statusLabel($o->status_courier))), 'pc' => sl_statusClass($o->status_courier), 'kv' => array_values(array_filter($kv, fn($x) => (string) $x[1] !== '')), 'view' => $r['K']['view'] . '?id=' . $r['id'], 'label' => $r['K']['label']];
}

function sl_opsDrivers(): array
{
    global $user;
    $rows = $user->cdp_userAllDriver() ?: [];
    return array_map(fn($d) => ['id' => (int) $d->id, 'label' => trim(($d->fname ?? '') . ' ' . ($d->lname ?? '')) ?: ($d->username ?? ('#' . $d->id))], $rows);
}

// ---------------------------------------------------------------------------
// Hand-over (deliver) pages
// ---------------------------------------------------------------------------
function sl_handoverData($user, $core, string $kind, int $id): array
{
    $r = sl_opsRecord($kind, $id, $kind === 'package' ? false : false);
    $o = $r['row'];
    $cfg = [
        'shipment'   => ['action' => 'ajax/courier/add_courier_delivered_ajax.php', 'idField' => 'shipment_id', 'dateField' => 'deliver_date', 'notify' => [['notify_whatsapp_sender', 'WhatsApp to the sender', 1], ['notify_sms_sender', 'SMS to the sender', 0], ['notify_sms_receiver', 'SMS to the recipient', 0]], 'gate' => true],
        'package'    => ['action' => 'ajax/customers_packages/add_customers_packages_delivered_ajax.php', 'idField' => 'package_id', 'dateField' => 't_date', 'notify' => [['notify_whatsapp_sender', 'WhatsApp to the customer', 1], ['notify_sms_sender', 'SMS to the customer', 0]], 'gate' => true],
        'consol'     => ['action' => 'consolidate_deliver_shipment.php?id=' . $id, 'idField' => '', 'dateField' => 'deliver_date', 'notify' => [['notify_sms_sender', 'SMS to the sender', 0], ['notify_sms_receiver', 'SMS to the recipient', 0]], 'gate' => false],
        'consol_pkg' => ['action' => 'consolidate_package_deliver_shipment.php?id=' . $id, 'idField' => '', 'dateField' => 'deliver_date', 'notify' => [['notify_sms_sender', 'SMS to the sender', 0], ['notify_sms_receiver', 'SMS to the recipient', 0]], 'gate' => false],
    ][$kind];
    $handed = function_exists('cdp_isHandedOver') ? cdp_isHandedOver($o->status_courier) : in_array((int) $o->status_courier, [8, 15], true);
    return $cfg + [
        'kind' => $kind, 'id' => $id, 'sum' => sl_opsSummary($r), 'drivers' => sl_opsDrivers(), 'driver' => (int) ($o->driver_id ?? 0),
        'module' => $r['K']['module'], 'owner' => sl_userName($r['sender']), 'handed' => $handed, 'today' => date('Y-m-d'),
        'items' => sl_opsItems($r), 'back' => $r['K']['view'] . '?id=' . $id,
    ];
}

/** Items (shipment / package) or the packages inside a consolidation. */
function sl_opsItems(array $r): array
{
    $K = $r['K'];
    if (!empty($K['items'])) {
        return array_map(fn($i) => ['t' => (string) $i->order_item_description, 'q' => (int) $i->order_item_quantity, 'w' => (float) $i->order_item_weight, 'dims' => (float) $i->order_item_length > 0 ? (float) $i->order_item_length . '×' . (float) $i->order_item_width . '×' . (float) $i->order_item_height : ''], sl_rows("SELECT * FROM {$K['items']} WHERE order_id = :id", [':id' => $r['id']]));
    }
    return array_map(fn($i) => ['t' => $i->order_prefix . $i->order_no, 'q' => 1, 'w' => (float) $i->weight, 'dims' => (float) $i->length > 0 ? (float) $i->length . '×' . (float) $i->width . '×' . (float) $i->height : ''], sl_rows("SELECT * FROM {$K['detail']} WHERE consolidate_id = :id", [':id' => $r['id']]));
}

// ---------------------------------------------------------------------------
// Tracking event pages (packages and consolidations)
// ---------------------------------------------------------------------------
function sl_trackUpdateData($user, $core, string $kind, int $id): array
{
    $r = sl_opsRecord($kind, $id);
    $o = $r['row'];
    $cfg = [
        'package'    => ['action' => 'ajax/customers_packages/add_customers_packages_tracking.php', 'idField' => 'package_id', 'notify' => [['notify_whatsapp', 'WhatsApp to the customer', 1], ['notify_sms_sender', 'SMS to the customer', 0]], 'flight' => false],
        'consol'     => ['action' => 'consolidate_shipment_tracking.php?id=' . $id, 'idField' => '', 'notify' => [], 'flight' => true],
        'consol_pkg' => ['action' => 'consolidate_package_shipment_tracking.php?id=' . $id, 'idField' => '', 'notify' => [], 'flight' => true],
    ][$kind];
    $hist = sl_trackHistory($r['track']);
    $events = array_map(function ($e) {
        return ['t' => str_replace('_', ' ', (string) ($e->mod_style ?? sl_statusLabel($e->status_courier ?? 0))), 'pc' => sl_statusClass($e->status_courier ?? 0), 'where' => trim(($e->t_city ?? '') . (isset($e->t_dest) && $e->t_dest !== '' ? ', ' . $e->t_dest : '')), 'note' => (string) ($e->comments ?? ''), 'at' => sl_date($e->t_date ?? '', 'M j, Y · H:i')];
    }, array_reverse($hist));
    $types = $kind === 'package' ? 1 : 2;
    return $cfg + [
        'kind' => $kind, 'id' => $id, 'sum' => sl_opsSummary($r), 'events' => $events,
        'statuses' => $core->cdp_getStatusByType($types) ?: sl_rows('SELECT id, mod_style FROM cdb_styles ORDER BY id'),
        'statusId' => (int) $o->status_courier, 'offices' => $core->cdp_getOffices() ?: [], 'office' => (int) ($o->origin_off ?? 0),
        'countries' => sl_rows('SELECT id, name FROM cdb_countries ORDER BY name'), 'today' => date('Y-m-d'),
        'module' => $r['K']['module'], 'owner' => sl_userName($r['sender']), 'back' => $r['K']['view'] . '?id=' . $id,
    ];
}

// ---------------------------------------------------------------------------
// Consolidation edit
// ---------------------------------------------------------------------------
function sl_consolEditData($user, $core, string $kind, int $id): array
{
    $r = sl_opsRecord($kind, $id, true);
    $o = $r['row'];
    $K = $r['K'];
    $rows = sl_rows("SELECT d.*, c.status_courier AS st, c.sender_id AS sid FROM {$K['detail']} d LEFT JOIN {$K['child']} c ON c.order_id = CAST(d.order_id AS UNSIGNED) WHERE d.consolidate_id = :id ORDER BY d.detail_id", [':id' => $id]);
    $owners = sl_usersById(array_map(fn($x) => (int) $x->sid, $rows));
    $items = array_map(fn($x) => ['order_id' => (int) $x->order_id, 'prefix' => (string) $x->order_prefix, 'no' => (string) $x->order_no, 'weight' => (float) $x->weight, 'length' => (float) $x->length, 'width' => (float) $x->width, 'height' => (float) $x->height, 'weight_vol' => (float) $x->weight_vol, 'owner' => sl_userName($owners[(int) $x->sid] ?? null), 'st' => sl_statusLabel($x->st), 'pc' => sl_statusClass($x->st)], $rows);
    $files = sl_rows("SELECT id, url, name, date_file FROM cdb_order_files WHERE order_id = :id AND is_consolidate = '1' ORDER BY date_file", [':id' => $id]);
    $look = sl_opsLookups();
    return [
        'kind' => $kind, 'id' => $id, 'sum' => sl_opsSummary($r), 'items' => $items, 'files' => $files, 'fileDel' => $K['fileDel'],
        'action' => ($kind === 'consol' ? 'consolidate_edit.php' : 'consolidate_package_edit.php') . '?id=' . $id,
        'statuses' => $core->cdp_getStatusByType(2) ?: [], 'payModes' => $look['payments'], 'drivers' => sl_opsDrivers(),
        'vals' => [
            'status_courier' => (int) $o->status_courier, 'order_pay_mode' => (int) ($o->order_pay_mode ?? 0), 'driver_id' => (int) ($o->driver_id ?? 0),
            'seals' => (string) ($o->seals_package ?? ''), 'awb_no' => (string) ($o->awb_no ?? ''),
            'estimated_eta' => (string) cdp_getConsolidationEtaRaw($id, $kind === 'consol_pkg'),
            'sender_address_id' => (int) ($o->sender_address_id ?? 0), 'recipient_address_id' => (int) ($o->receiver_address_id ?? 0),
        ],
        'hasAwb' => function_exists('cdp_awbColumnReady') ? (bool) cdp_awbColumnReady() : false,
        'dg' => (int) ($o->is_dangerous_good ?? 0), 'meter' => (float) (cdp_getSettingsCourier()->meter ?? 0),
        'back' => $K['view'] . '?id=' . $id, 'detail' => sl_route('ConsolidationDetail', ['id' => $id, 'type' => $kind === 'consol' ? 'air' : 'sea']),
    ];
}

// ---------------------------------------------------------------------------
// Shipment / package forms (edit, accept, pickup accept, multiple, from pre-alert)
// ---------------------------------------------------------------------------
function sl_shipFormData($user, $core, string $mode, int $id = 0): array
{
    require_once CDP_ROOT . '/helpers/unique_ids.php';
    $settings = cdp_getSettingsCourier();
    $info = cdp_getInfoShipDefault();
    $ctx = cdp_getAgencyContext();
    $look = sl_opsLookups();
    $pkgKind = in_array($mode, ['package_edit', 'prealert', 'package_multiple'], true);
    $d = [
        'mode' => $mode, 'kind' => $pkgKind ? 'package' : 'shipment', 'id' => $id,
        'agencies' => $look['agencies'], 'offices' => $look['offices'], 'couriers' => $look['couriers'], 'packaging' => $look['packaging'], 'delitimes' => $look['delitimes'], 'services' => $look['services'],
        'statuses' => $core->cdp_getStatusByType(1) ?: [], 'payTerms' => sl_rows('SELECT id, label, days FROM cdb_payment_methods ORDER BY id'), 'drivers' => sl_opsDrivers(),
        'agencyLocked' => !empty($ctx['is_restricted']) && !empty($ctx['agency_id']),
        'rates' => ['price_lb' => (float) ($settings->value_weight ?? 0), 'tax' => (float) ($settings->tax ?? 0), 'insurance' => (float) ($settings->insurance ?? 0), 'tariffs' => (float) ($settings->c_tariffs ?? 0), 'declared' => (float) ($settings->declared_tax ?? 0), 'min_tax' => (float) ($core->min_cost_tax ?? 0), 'min_declared' => (float) ($core->min_cost_declared_tax ?? 0), 'meter' => (float) ($settings->meter ?? 0), 'rate' => (float) ($core->exchange_rate ?? 0), 'currency' => (string) ($core->currency ?? 'USD'), 'weight' => (string) ($core->weight_p ?? 'lb')],
        'hasTariffs' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_shipping_fees') > 0,
        'hidden' => [], 'items' => [], 'files' => [], 'fileDel' => '', 'pre' => ['sender' => null, 'saddr' => 0, 'recip' => null, 'rtype' => 'recipient', 'raddr' => 0],
        'hasRecipient' => true, 'sum' => null,
        'notify' => ['whatsapp' => (int) ($settings->active_whatsapp ?? 0) === 1],
    ];
    $v = [
        'agency' => $d['agencyLocked'] ? (int) $ctx['agency_id'] : (int) ($look['agencies'][0]->id ?? 0),
        'origin_off' => (int) ($look['offices'][0]->id ?? 0), 'order_package' => (int) ($info->packaging_default2 ?? 0), 'order_courier' => (int) ($info->courier_default3 ?? 0),
        'order_service_options' => (int) ($info->service_default4 ?? 0), 'order_deli_time' => (int) ($info->time_default5 ?? 0), 'order_payment_method' => (int) ($info->payment_default7 ?? 0),
        'status_courier' => (int) ($info->status_default8 ?? 0), 'order_item_category' => $pkgKind ? 27 : 26, 'driver_id' => 0, 'estimated_eta' => '', 'order_date' => date('Y-m-d'),
        'tracking_number' => '', 'price_lb' => (float) ($settings->value_weight ?? 0), 'insured_value' => 0, 'reexpedicion_value' => 0, 'discount_value' => 0, 'discount_type' => 'amount',
        'courier_notes' => '', 'is_dangerous_good' => 0, 'tracking_purchase' => '', 'provider_purchase' => '', 'price_purchase' => '', 'package_total_weight' => '', 'manual' => 1,
    ];
    $cfgs = [
        'edit'             => ['title' => 'Edit shipment', 'submit' => 'Save changes', 'endpoint' => 'ajax/courier/edit_courier_ajax.php', 'ok' => 'courier_view.php?id=:id'],
        'accept'           => ['title' => 'Complete customer shipment', 'submit' => 'Accept and price shipment', 'endpoint' => 'ajax/courier/accept_courier_ajax.php', 'ok' => 'courier_view.php?id=:id'],
        'pickup'           => ['title' => 'Accept pickup', 'submit' => 'Accept pickup', 'endpoint' => 'ajax/pickup/accept_pickup_ajax.php', 'ok' => 'courier_view.php?id=:id'],
        'multiple'         => ['title' => 'New shipments in bulk', 'submit' => 'Create shipments', 'endpoint' => 'ajax/courier/add_courier_multiple_ajax.php', 'ok' => 'courier_list.php'],
        'package_edit'     => ['title' => 'Edit package', 'submit' => 'Save changes', 'endpoint' => 'ajax/customers_packages/edit_customers_packages_ajax.php', 'ok' => 'customer_packages_view.php?id=:id'],
        'prealert'         => ['title' => 'Register package from pre-alert', 'submit' => 'Register package', 'endpoint' => 'ajax/customers_packages/add_customers_packages_from_prealert_ajax.php', 'ok' => 'customer_packages_view.php?id=:id'],
        'package_multiple' => ['title' => 'New packages in bulk', 'submit' => 'Register packages', 'endpoint' => 'ajax/customers_packages/add_customers_packages_multiple_ajax.php', 'ok' => 'customer_packages_list.php'],
    ];
    $d += $cfgs[$mode];
    $d['back'] = $pkgKind ? ['href' => 'customer_packages_list.php', 'label' => 'Sea packages'] : ['href' => sl_route('AirShipments'), 'label' => 'Air shipments'];
    if ($mode === 'pickup') {
        $d['back'] = ['href' => sl_route('Pickups'), 'label' => 'Pickups'];
    }

    if (in_array($mode, ['edit', 'accept', 'pickup', 'package_edit'], true)) {
        $r = sl_opsRecord($pkgKind ? 'package' : 'shipment', $id, true);
        $o = $r['row'];
        $d['sum'] = sl_opsSummary($r);
        $d['back'] = ['href' => $r['K']['view'] . '?id=' . $id, 'label' => $r['track']];
        $d['title'] .= ' ' . $r['track'];
        $d['hidden'] = ['order_id' => $id, 'order_no' => (string) $o->order_no, 'meter' => (string) ($settings->meter ?? '')];
        if ($mode !== 'edit' && $mode !== 'package_edit') {
            $d['hidden']['prefix_check'] = '';
        }
        $d['pre'] = ['sender' => ['id' => (int) $o->sender_id, 'label' => sl_userName($r['sender']) . (!empty($r['sender']->locker) ? ' (' . $r['sender']->locker . ')' : '')], 'saddr' => (int) $o->sender_address_id, 'recip' => (int) ($o->receiver_id ?? 0), 'rtype' => $r['rtype'] ?: 'recipient', 'raddr' => (int) ($o->receiver_address_id ?? 0)];
        foreach (['agency', 'origin_off', 'order_package', 'order_courier', 'order_service_options', 'order_deli_time', 'order_payment_method', 'status_courier', 'driver_id', 'order_item_category', 'is_dangerous_good'] as $k) {
            if (isset($o->$k) && (int) $o->$k > 0 || $k === 'is_dangerous_good' && isset($o->$k)) {
                $v[$k] = (int) $o->$k;
            }
        }
        $v['order_date'] = substr((string) ($o->order_datetime ?? date('Y-m-d')), 0, 10);
        $v['price_lb'] = (float) ($o->value_weight ?? 0) > 0 ? (float) $o->value_weight : $v['price_lb'];
        $v['insured_value'] = (float) ($o->total_insured_value ?? 0);
        $v['reexpedicion_value'] = (float) ($o->total_reexp ?? 0);
        $v['discount_value'] = (float) ($o->tax_discount ?? 0);
        $v['discount_type'] = (string) ($o->discount_type ?? 'percent') ?: 'percent';
        $v['courier_notes'] = (string) ($o->courier_notes ?? '');
        $v['manual'] = (int) ($o->manual_tariff ?? 1);
        foreach (['tracking_purchase', 'provider_purchase', 'price_purchase'] as $k) {
            $v[$k] = (string) ($o->$k ?? '');
        }
        if ($pkgKind) {
            $v['estimated_eta'] = (string) cdp_getPackageEtaRaw($id);
        } else {
            $tr = cdp_getPackageTrackingLegacyAware($id);
            $v['estimated_eta'] = (string) ($tr->estimated_eta ?? ($o->estimated_eta ?? ''));
            $v['tracking_number'] = (string) ($tr->tracking_number ?? '');
        }
        $itemTable = $r['K']['items'];
        $d['items'] = array_map(fn($i) => ['qty' => (float) $i->order_item_quantity, 'description' => (string) $i->order_item_description, 'weight' => (float) $i->order_item_weight, 'length' => (float) $i->order_item_length, 'width' => (float) $i->order_item_width, 'height' => (float) $i->order_item_height, 'declared_value' => (float) $i->order_item_declared_value, 'fixed_value' => (float) $i->order_item_fixed_value, 'custom_price' => isset($i->custom_price) && $i->custom_price !== null ? (float) $i->custom_price : ''], sl_rows("SELECT * FROM $itemTable WHERE order_id = :id", [':id' => $id]));
        $d['files'] = sl_rows("SELECT id, url, name, date_file FROM {$r['K']['files']} WHERE order_id = :id ORDER BY date_file", [':id' => $id]);
        $d['fileDel'] = $r['K']['fileDel'];
        if ($mode === 'accept' || $mode === 'pickup') {
            // Accepting assigns the final shipment number (the legacy forms showed the next free one).
            $d['hidden']['order_no'] = (string) $o->order_no;
        }
    } elseif ($mode === 'prealert') {
        $pa = sl_row('SELECT p.*, u.fname, u.lname, u.locker FROM cdb_pre_alert p LEFT JOIN cdb_users u ON u.id = p.customer_id WHERE p.pre_alert_id = :id', [':id' => $id]);
        if (!$pa) {
            header('Location: prealert_list.php');
            exit;
        }
        $d['hasRecipient'] = false;
        $d['back'] = ['href' => 'prealert_list.php', 'label' => 'Pre-alerts'];
        $d['hidden'] = ['prealert_id' => $id, 'code_prefix' => (string) ($settings->prefix_online_shopping ?? ($settings->prefix ?? '')), 'order_no' => (string) ((int) ($core->code_number ?? 1) === 0 ? cdp_generarCodigo((string) $core->digit_random) : $core->cdp_online_shopping_track())];
        $d['numberEditable'] = true;
        $d['pre']['sender'] = ['id' => (int) $pa->customer_id, 'label' => trim($pa->fname . ' ' . $pa->lname) . ($pa->locker ? ' (' . $pa->locker . ')' : '')];
        $v['tracking_purchase'] = (string) $pa->tracking;
        $v['provider_purchase'] = (string) $pa->provider_shop;
        $v['price_purchase'] = (string) $pa->purchase_price;
        $v['order_courier'] = (int) $pa->courier_com ?: $v['order_courier'];
        $d['items'] = [['qty' => 1, 'description' => (string) $pa->package_description, 'weight' => '', 'length' => '', 'width' => '', 'height' => '', 'declared_value' => (float) $pa->purchase_price, 'fixed_value' => 0, 'custom_price' => '']];
        $d['prealertInfo'] = ['tracking' => $pa->tracking, 'shop' => $pa->provider_shop, 'date' => sl_date($pa->prealert_date ?? ''), 'customer' => trim($pa->fname . ' ' . $pa->lname), 'invoice' => (string) ($pa->url_invoice ?? '')];
    } else { // multiple
        $d['hasRecipient'] = $mode === 'multiple';
        $d['hidden'] = ['code_prefix' => (string) ($pkgKind ? ($settings->prefix_online_shopping ?? ($settings->prefix ?? '')) : ($settings->prefix ?? ''))];
    }
    $d['vals'] = $v;
    return $d;
}

// ---------------------------------------------------------------------------
// Warehouse delivery by consolidation (list + one consolidation)
// ---------------------------------------------------------------------------
function sl_wdPkgState(object $p): string
{
    if (cdp_isHandedOver($p->status_courier)) return 'delivered';
    if (in_array((int) $p->status_courier, [16, 21, 27, 35], true)) return 'other';
    return cdp_fsIsCleared($p->fs_cleared_for_delivery) ? 'ready' : 'awaiting';
}

function sl_wdPackages(int $cid): array
{
    $own = sl_userIs('client') ? ' AND a.sender_id = ' . (int) $_SESSION['userid'] : '';
    return sl_rows("SELECT a.order_id, a.order_prefix, a.order_no, a.sender_id, a.status_courier, a.fs_cleared_for_delivery, a.total_weight, a.tracking_purchase,
            s.mod_style AS status_name, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), CONCAT('User ', a.sender_id)) AS customer, u.locker
        FROM cdb_consolidate_detail d INNER JOIN cdb_add_order a ON a.order_id = CAST(d.order_id AS UNSIGNED)
        LEFT JOIN cdb_styles s ON s.id = a.status_courier LEFT JOIN cdb_users u ON u.id = a.sender_id
        WHERE d.consolidate_id = :cid $own ORDER BY customer ASC, a.order_id DESC", [':cid' => $cid]);
}

function sl_wdConsolData($user, $core, int $cid): array
{
    require_once CDP_ROOT . '/helpers/finance_switch.php';
    require_once CDP_ROOT . '/helpers/handover.php';
    $d = ['cid' => $cid, 'q' => trim((string) ($_GET['q'] ?? '')), 'finance' => function_exists('cdp_financeEnabled') && cdp_financeEnabled(),
        'canPkg' => $user->cdp_hasPermission('warehouse_deliver_package'), 'canUser' => $user->cdp_hasPermission('warehouse_deliver_user'), 'canUndo' => $user->cdp_hasPermission('warehouse_undo_delivery')];
    $count = function (array $pkgs) {
        $c = ['total' => count($pkgs), 'delivered' => 0, 'ready' => 0, 'awaiting' => 0, 'weight' => 0.0, 'customers' => []];
        foreach ($pkgs as $p) { $c['weight'] += (float) $p->total_weight; $c['customers'][(int) $p->sender_id] = 1; $s = sl_wdPkgState($p); if (isset($c[$s])) $c[$s]++; }
        $c['customers'] = count($c['customers']);
        $c['pct'] = sl_pct($c['delivered'], max(1, $c['total']));
        return $c;
    };
    if ($cid <= 0) {
        $own = sl_userIs('client') ? ' AND a.sender_id = ' . (int) $_SESSION['userid'] : '';
        $where = ' WHERE EXISTS (SELECT 1 FROM cdb_consolidate_detail d INNER JOIN cdb_add_order a ON a.order_id = CAST(d.order_id AS UNSIGNED) WHERE d.consolidate_id = c.consolidate_id AND ' . cdp_wdQueueSql('a') . " $own) ";
        $bind = [];
        if ($d['q'] !== '') {
            $where .= " AND (CONCAT(COALESCE(c.c_prefix,''),COALESCE(c.c_no,'')) LIKE :q1 OR EXISTS (SELECT 1 FROM cdb_consolidate_detail d2 INNER JOIN cdb_add_order a2 ON a2.order_id = CAST(d2.order_id AS UNSIGNED) LEFT JOIN cdb_users u2 ON u2.id = a2.sender_id WHERE d2.consolidate_id = c.consolidate_id AND (CONCAT(COALESCE(u2.fname,''),' ',COALESCE(u2.lname,'')) LIKE :q2 OR u2.locker LIKE :q3 OR CONCAT(COALESCE(a2.order_prefix,''),COALESCE(a2.order_no,'')) LIKE :q4)))";
            $like = '%' . $d['q'] . '%';
            $bind = [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
        }
        $rows = sl_rows("SELECT c.consolidate_id, c.c_prefix, c.c_no, c.c_date, c.status_courier" . (function_exists('cdp_awbColumnReady') && cdp_awbColumnReady() ? ', c.awb_no' : '') . " FROM cdb_consolidate c $where ORDER BY c.consolidate_id DESC LIMIT 300", $bind);
        $d['list'] = array_map(fn($c) => ['id' => (int) $c->consolidate_id, 'ref' => cdp_consolidationRef($c), 'date' => sl_date($c->c_date)] + $count(sl_wdPackages((int) $c->consolidate_id)), $rows);
        return $d;
    }
    $c = sl_row('SELECT consolidate_id, c_prefix, c_no, c_date, status_courier' . (function_exists('cdp_awbColumnReady') && cdp_awbColumnReady() ? ', awb_no' : '') . ' FROM cdb_consolidate WHERE consolidate_id = :id', [':id' => $cid]);
    if (!$c) {
        header('Location: warehouse_delivery.php?view=consolidations');
        exit;
    }
    $pkgs = sl_wdPackages($cid);
    $d['ref'] = cdp_consolidationRef($c);
    $d['code'] = $c->c_prefix . $c->c_no;
    $d['date'] = sl_date($c->c_date);
    $d['sum'] = $count($pkgs);
    $ids = array_map(fn($p) => (int) $p->order_id, $pkgs);
    $items = [];
    if ($ids) {
        foreach (sl_rows('SELECT order_id, order_item_description, order_item_quantity, order_item_weight FROM cdb_add_order_item WHERE order_id IN (' . implode(',', $ids) . ')') as $it) {
            $items[(int) $it->order_id][] = ['t' => (string) ($it->order_item_description ?: '—'), 'q' => (int) $it->order_item_quantity, 'w' => (float) $it->order_item_weight];
        }
    }
    $cust = [];
    foreach ($pkgs as $p) {
        $sid = (int) $p->sender_id;
        if (!isset($cust[$sid])) {
            $cust[$sid] = ['sid' => $sid, 'name' => (string) $p->customer, 'ini' => sl_initials((string) $p->customer), 'locker' => (string) $p->locker, 'pkgs' => [], 'ready' => 0, 'delivered' => 0];
        }
        $s = sl_wdPkgState($p);
        if ($s === 'ready') $cust[$sid]['ready']++;
        if ($s === 'delivered') $cust[$sid]['delivered']++;
        $cust[$sid]['pkgs'][] = ['oid' => (int) $p->order_id, 'no' => (string) $p->order_no, 'track' => $p->order_prefix . $p->order_no, 'carrier' => (string) ($p->tracking_purchase ?? ''), 'st' => str_replace('_', ' ', (string) $p->status_name), 'pc' => sl_statusClass($p->status_courier), 'state' => $s, 'label' => $s === 'delivered' ? cdp_handoverLabel($p->status_courier) : '', 'w' => sl_weight($p->total_weight, 2), 'items' => $items[(int) $p->order_id] ?? []];
    }
    $d['customers'] = array_values($cust);
    return $d;
}
