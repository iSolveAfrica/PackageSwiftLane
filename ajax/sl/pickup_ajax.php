<?php
/**
 * Pickup writes for the Pickups and SchedulePickup pages (cdb_add_order, is_pickup = 1).
 * Reuses helpers/querys.php: cdp_insertCourierShipment / cdp_insertCourierPickupFromCustomer,
 * cdp_insertCourierShipmentPackages, cdp_insertCourierShipmentTrack, cdp_insertCourierShipmentAddresses,
 * cdp_deleteCourier (cancel), cdp_refuesePickup (refuse), cdp_deleteFullCourier (delete), cdp_updateDriverCourier.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/unique_ids.php';
require_once CDP_ROOT . '/views/sl/data/pickups.php';
$user = new User; $core = new Core;
require_login();
$op = (string) ($_REQUEST['op'] ?? '');
$uid = (int) $_SESSION['userid'];
$isClient = sl_userIs('client');
if ($op === 'addresses') {
    require_permission('add_full_pickup');
    sl_json(['success' => true, 'addresses' => sl_opsAddresses((int) ($_GET['id'] ?? 0))]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);

function slk_pickup(int $id): object
{
    $p = cdp_getCourier($id);
    if (!$p || (int) $p->is_pickup !== 1 || (int) ($p->is_deleted ?? 0) === 1 || !sl_opsInScope($p)) sl_json(['success' => false, 'message' => 'Pickup not found'], 404);
    return $p;
}
function slk_log(string $action, object $p, string $summary): void
{
    cdp_activityLog(['module' => 'pickups', 'action' => $action, 'action_label' => ucfirst($action) . ' pickup', 'verb' => $action === 'create' ? 'create' : ($action === 'delete' ? 'delete' : 'update'), 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => 'pickup', 'entity_id' => (int) $p->order_id, 'entity_label' => $p->order_prefix . $p->order_no]);
}

switch ($op) {
    case 'create':
        require_permission($isClient ? 'pickup_add' : 'add_full_pickup');
        $sender = $isClient ? $uid : (int) ($_POST['sender_id'] ?? 0);
        $addr = (int) ($_POST['sender_address_id'] ?? 0);
        $errors = [];
        $cust = sl_row('SELECT id, agency_id FROM cdb_users WHERE id = :id AND userlevel = 1', [':id' => $sender]);
        if (!$cust) $errors['sender_id'] = 'Pick a customer';
        $ctx = cdp_getAgencyContext();
        if ($cust && !$isClient && !empty($ctx['is_restricted']) && (int) $cust->agency_id !== (int) $ctx['agency_id']) $errors['sender_id'] = 'This customer belongs to another agency';
        if (!sl_row('SELECT id_addresses FROM cdb_senders_addresses WHERE id_addresses = :a AND user_id = :u', [':a' => $addr, ':u' => $sender])) $errors['sender_address_id'] = 'Pick one of the customer\'s addresses';
        $date = (string) ($_POST['order_date'] ?? '');
        if (!strtotime($date) || strtotime($date) < strtotime('today')) $errors['order_date'] = 'Pick today or a later date';
        $win = (string) ($_POST['window'] ?? '');
        if (!in_array($win, SL_PICKUP_WINDOWS, true)) $errors['window'] = 'Pick a time window';
        $parcels = json_decode((string) ($_POST['packages'] ?? '[]'), true);
        $parcels = is_array($parcels) ? array_values(array_filter($parcels, fn($p) => trim((string) ($p['description'] ?? '')) !== '')) : [];
        if (!$parcels) $errors['packages'] = 'Describe at least one parcel';
        if ($errors) sl_json(['success' => false, 'message' => reset($errors), 'errors' => $errors], 422);
        $kind = ($_POST['kind'] ?? '') === 'deliver' ? 'Deliver to customer' : 'Collect from customer';
        $phone = mb_substr(cdp_sanitize($_POST['phone'] ?? ''), 0, 40);
        $notes = trim('Window ' . $win . ' · ' . $kind . ($phone !== '' ? ' · Phone ' . $phone : '') . "\n" . mb_substr(cdp_sanitize($_POST['notes'] ?? ''), 0, 1500));
        $startH = (int) substr($win, 0, 2);
        $dates = [$date];
        if (($_POST['repeat'] ?? '') === 'weekly') for ($i = 1; $i < 4; $i++) $dates[] = date('Y-m-d', strtotime("$date +$i week"));
        $def = cdp_getInfoShipDefault(); $ag = cdp_getDefaultAgencyOrigin();
        $settings = cdp_getSettingsCourier();
        $made = [];
        foreach ($dates as $dt) {
            $no = cdp_uidClaim('order_no');
            $prefix = (string) $settings->prefix;
            $when = $dt . ' ' . sprintf('%02d:00:00', $startH);
            $w = array_sum(array_map(fn($p) => (float) ($p['weight'] ?? 0), $parcels));
            if ($isClient) {
                $id = (int) cdp_insertCourierPickupFromCustomer(['user_id' => $uid, 'order_prefix' => $prefix, 'order_no' => $no, 'order_date' => date('Y-m-d H:i:s'), 'sender_id' => $sender, 'sender_address_id' => $addr, 'recipient_id' => $sender, 'recipient_address_id' => $addr, 'volumetric_percentage' => (string) $settings->meter, 'order_datetime' => $when, 'order_item_category' => (int) ($def->logistics_default1 ?? 0), 'order_package' => (int) ($def->packaging_default2 ?? 0), 'status_courier' => 14, 'is_pickup' => 1, 'due_date' => date('Y-m-d H:i:s'), 'status_invoice' => 2, 'order_incomplete' => 0, 'recipient_type' => 'customer']);
            } else {
                $pm = (int) ($_POST['order_payment_method'] ?? 1);
                $pmRow = cdp_getPaymentMethodCourier($pm);
                $id = (int) cdp_insertCourierShipment(['manual_tariff' => 0, 'order_incomplete' => 1, 'recipient_type' => 'customer', 'is_pickup' => 1, 'user_id' => $uid, 'order_prefix' => $prefix, 'order_no' => $no, 'order_datetime' => $when, 'sender_id' => $sender, 'recipient_id' => $sender, 'sender_address_id' => $addr, 'recipient_address_id' => $addr, 'order_date' => date('Y-m-d H:i:s'), 'agency' => (int) ($_POST['agency'] ?? $ag['agency']), 'origin_off' => (int) ($_POST['origin_off'] ?? $ag['origin_off']), 'order_package' => (int) ($def->packaging_default2 ?? 0), 'order_item_category' => (int) ($def->logistics_default1 ?? 0), 'order_courier' => (int) ($def->courier_default3 ?? 0), 'order_service_options' => (int) ($def->service_default4 ?? 0), 'order_deli_time' => (int) ($def->time_default5 ?? 0), 'order_payment_method' => $pm, 'status_courier' => 14, 'driver_id' => (int) ($_POST['driver_id'] ?? 0), 'due_date' => cdp_sumardias(date('Y-m-d H:i:s'), (int) ($pmRow->days ?? 0)), 'status_invoice' => 2, 'volumetric_percentage' => (string) $settings->meter, 'courier_notes' => $notes, 'is_dangerous_good' => 0]);
            }
            if (!$id) sl_json(['success' => false, 'message' => 'The pickup could not be saved'], 500);
            $db = new Conexion;
            $db->cdp_query('UPDATE cdb_add_order SET notes = :n, total_weight = :w WHERE order_id = :id'); $db->bind(':n', $notes); $db->bind(':w', $w); $db->bind(':id', $id); $db->cdp_execute();
            foreach ($parcels as $p) cdp_insertCourierShipmentPackages(['order_id' => $id, 'qty' => max(1, (float) ($p['qty'] ?? 1)), 'description' => mb_substr((string) $p['description'], 0, 250), 'length' => 0, 'width' => 0, 'height' => 0, 'weight' => (float) ($p['weight'] ?? 0), 'declared_value' => 0, 'fixed_value' => 0]);
            $a = sl_row('SELECT sa.address, sa.zip_code, c.name AS city, s.name AS state, k.name AS country FROM cdb_senders_addresses sa LEFT JOIN cdb_cities c ON c.id = sa.city LEFT JOIN cdb_states s ON s.id = sa.state LEFT JOIN cdb_countries k ON k.id = sa.country WHERE sa.id_addresses = :a', [':a' => $addr]);
            cdp_insertCourierShipmentAddresses(['order_id' => $id, 'order_track' => $prefix . $no, 'sender_country' => $a->country ?? '', 'sender_state' => $a->state ?? '', 'sender_city' => $a->city ?? '', 'sender_zip_code' => $a->zip_code ?? '', 'sender_address' => $a->address ?? '', 'recipient_country' => $a->country ?? '', 'recipient_state' => $a->state ?? '', 'recipient_city' => $a->city ?? '', 'recipient_zip_code' => $a->zip_code ?? '', 'recipient_address' => $a->address ?? '']);
            cdp_insertCourierShipmentTrack(['user_id' => $uid, 'order_id' => $id, 'order_track' => $prefix . $no, 't_date' => date('Y-m-d H:i:s'), 'status_courier' => 14, 'comments' => 'Pickup requested · ' . $win, 'office' => (int) ($_POST['origin_off'] ?? $ag['origin_off'])]);
            cdp_insertCourierShipmentUserHistory(['user_id' => $uid, 'order_id' => $id, 'order_track' => $prefix . $no, 'action' => 'Pickup scheduled', 'date_history' => date('Y-m-d H:i:s')]);
            $made[] = (object) ['order_id' => $id, 'order_prefix' => $prefix, 'order_no' => $no];
        }
        try {
            $nid = (int) cdp_insertNotification(['user_id' => $uid, 'order_id' => $made[0]->order_id, 'notification_description' => 'New pickup ' . $made[0]->order_prefix . $made[0]->order_no . ' · ' . $win, 'shipping_type' => '1', 'notification_date' => date('Y-m-d H:i:s')]);
            if ($nid) { foreach (cdp_getUsersAdminEmployees() as $e) cdp_insertNotificationsUsers($nid, $e->id); cdp_insertNotificationsUsers($nid, $sender); if (!empty($_POST['driver_id'])) cdp_insertNotificationsUsers($nid, (int) $_POST['driver_id']); }
        } catch (Throwable $e) { }
        if (!empty($_POST['notify_whatsapp'])) {
            require_once CDP_ROOT . '/helpers/after_response.php';
            $owner = cdp_getSenderCourier($sender);
            $txt = 'Hello ' . ($owner ? cdp_nameWithLocker($owner) : '') . ",\n\nYour pickup *" . $made[0]->order_prefix . $made[0]->order_no . '* is booked for ' . sl_date($date, 'D M j') . ', ' . $win . '.' . (count($made) > 1 ? ' It repeats weekly (' . count($made) . ' pickups).' : '');
            cdp_afterResponse(function () use ($owner, $txt) { try { if ($owner && !empty($owner->phone)) { require_once CDP_ROOT . '/ajax/notify_whatsapp/api_whatsapp_service_v2.php'; sendNotificationWhatsApp_v2($owner, $txt, null, ['allow_sms' => false]); } } catch (Throwable $e) { error_log('sl pickup WA: ' . $e->getMessage()); } });
        }
        foreach ($made as $m) slk_log('create', $m, 'Scheduled pickup ' . $m->order_prefix . $m->order_no . ' for ' . $date . ' ' . $win);
        sl_json(['success' => true, 'message' => count($made) . ' pickup(s) booked', 'redirect' => sl_route('Pickups')]);
        break;
    case 'cancel':
        require_permission('cancel_pickup');
        $p = slk_pickup((int) ($_POST['id'] ?? 0));
        $reason = mb_substr(trim(cdp_sanitize($_POST['reason'] ?? '')), 0, 500);
        if ($reason === '') sl_json(['success' => false, 'message' => 'Give a reason', 'errors' => ['reason' => 'Required']], 422);
        if ($isClient && (int) $p->status_courier !== 14) sl_json(['success' => false, 'message' => 'This pickup was already accepted; call us to cancel it.'], 409);
        cdp_deleteCourier(['id' => (int) $p->order_id, 'message' => $reason]);
        cdp_insertCourierShipmentTrack(['user_id' => $uid, 'order_id' => (int) $p->order_id, 'order_track' => $p->order_prefix . $p->order_no, 't_date' => date('Y-m-d H:i:s'), 'status_courier' => 21, 'comments' => 'Pickup cancelled: ' . $reason, 'office' => (int) $p->origin_off]);
        slk_log('cancel', $p, 'Cancelled pickup ' . $p->order_prefix . $p->order_no . ' (' . $reason . ')');
        sl_json(['success' => true, 'message' => 'Pickup cancelled']);
        break;
    case 'refuse':
        require_permission('pickup_accept');
        if ($isClient) sl_json(['success' => false, 'message' => 'Not allowed'], 403);
        $p = slk_pickup((int) ($_POST['id'] ?? 0));
        if ((int) $p->status_courier !== 14) sl_json(['success' => false, 'message' => 'Only a requested pickup can be refused'], 409);
        cdp_refuesePickup(['id' => (int) $p->order_id]);
        slk_log('refuse', $p, 'Refused pickup ' . $p->order_prefix . $p->order_no);
        sl_json(['success' => true, 'message' => 'Pickup refused']);
        break;
    case 'delete':
        require_permission('delete_pickup');
        if ($isClient) sl_json(['success' => false, 'message' => 'Not allowed'], 403);
        $p = slk_pickup((int) ($_POST['id'] ?? 0));
        cdp_deleteFullCourier(['id' => (int) $p->order_id]);
        slk_log('delete', $p, 'Deleted pickup ' . $p->order_prefix . $p->order_no);
        sl_json(['success' => true, 'message' => 'Pickup deleted']);
        break;
    case 'driver':
        require_permission('assign_driver_pickup');
        if ($isClient) sl_json(['success' => false, 'message' => 'Not allowed'], 403);
        $p = slk_pickup((int) ($_POST['id'] ?? 0));
        $drv = (int) ($_POST['driver_id'] ?? 0);
        if (!sl_row('SELECT id FROM cdb_users WHERE id = :id AND userlevel = 3', [':id' => $drv])) sl_json(['success' => false, 'message' => 'Pick a driver', 'errors' => ['driver_id' => 'Required']], 422);
        cdp_updateDriverCourier(['id_shipment' => (int) $p->order_id, 'driver_id' => $drv]);
        try { $nid = (int) cdp_insertNotification(['user_id' => $uid, 'order_id' => (int) $p->order_id, 'notification_description' => 'Pickup ' . $p->order_prefix . $p->order_no . ' assigned to you', 'shipping_type' => '1', 'notification_date' => date('Y-m-d H:i:s')]); if ($nid) cdp_insertNotificationsUsers($nid, $drv); } catch (Throwable $e) { }
        slk_log('assign_driver', $p, 'Driver #' . $drv . ' assigned to pickup ' . $p->order_prefix . $p->order_no);
        sl_json(['success' => true, 'message' => 'Driver assigned']);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
