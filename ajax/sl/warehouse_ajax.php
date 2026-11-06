<?php
/**
 * Warehouse endpoint for Warehouse, WarehouseDelivery, WarehousePickups, HandOut, PickupAging and the pickup code dialog.
 *
 * Pickup codes (helpers/pickup_code.php, rules in docs/HANDOVER_PICKUP_CODES_SMS.md):
 *   op=code_state | code_send | code_verify   module=air|sea  ids=1,2,3
 * Handover (helpers/handover.php: 15 Picked up / 8 Delivered, Picked up is the default):
 *   op=handover  items=air:12,sea:7  handover_type=picked_up|delivered
 *     every package needs a verified pickup code (cdp_pickupCodeGate) and, for air, Accounts clearance (cdp_fsIsCleared);
 *     the code items are spent (cdp_pickupCodeConsume), a track row and history line are written, the consolidation of an
 *     air package closes as Picked up when every package in it was picked up, otherwise Delivered (same rule as
 *     ajax/courier/warehouse_delivery_ajax.php), and the customer gets one WhatsApp (SMS copy via sms_event handed_over) + e-mail.
 *   op=undo  module=  id=   (warehouse_undo_delivery) reopens a handed-over package to In_Warehouse (4)
 * Warehouse floor: op=receive tracking= bay=   op=move id= module= bay=
 * Aging: op=aging_notify (view_pickup_aging) -> cdp_pickupAgingNotifySenders for the 14-day list.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_once __DIR__ . '/../../helpers/pickup_code.php';
require_once CDP_ROOT . '/views/sl/data/warehouse.php';
$user = new User; $core = new Core;
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
$op = (string) ($_POST['op'] ?? '');
$uid = (int) $_SESSION['userid'];
if (sl_userIs('client')) sl_json(['success' => false, 'message' => 'Not allowed'], 403);
if (CDP_APP_MODE_DEMO === true && !in_array($op, ['code_state'], true)) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);

/** Package row (air or sea) in scope, or null. */
function slw_pkg(string $module, int $id): ?object
{
    $r = $module === 'sea' ? cdp_getCustomerPackage($id) : cdp_getCourier($id);
    if (!$r || ($module === 'air' && (int) ($r->is_deleted ?? 0) === 1) || !sl_opsInScope($r)) return null;
    return $r;
}
function slw_ids(): array
{
    return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($_POST['ids'] ?? ''))))));
}
/** Same rule as wd_sync_consolidation_status() in ajax/courier/warehouse_delivery_ajax.php. */
function slw_syncConsolidation(int $orderId, bool $sea): void
{
    $detail = $sea ? 'cdb_consolidate_packages_detail' : 'cdb_consolidate_detail';
    $head = $sea ? 'cdb_consolidate_packages' : 'cdb_consolidate';
    $orders = $sea ? 'cdb_customers_packages' : 'cdb_add_order';
    foreach (sl_rows("SELECT DISTINCT consolidate_id FROM $detail WHERE order_id = :o", [':o' => $orderId]) as $c) {
        $cid = (int) $c->consolidate_id;
        $rows = sl_rows("SELECT a.status_courier FROM $detail d JOIN $orders a ON a.order_id = d.order_id WHERE d.consolidate_id = :c", [':c' => $cid]);
        if (!$rows) continue;
        $all = true; $allPick = true;
        foreach ($rows as $r) {
            if (!cdp_isHandedOver($r->status_courier)) { $all = false; break; }
            if ((int) $r->status_courier !== CDP_STATUS_PICKED_UP) $allPick = false;
        }
        $cur = (int) sl_scalar("SELECT status_courier FROM $head WHERE consolidate_id = :c", [':c' => $cid]);
        $to = null;
        if ($all) { $to = $allPick ? CDP_STATUS_PICKED_UP : CDP_STATUS_DELIVERED; if ($to === $cur) $to = null; }
        elseif (cdp_isHandedOver($cur)) $to = 4;
        if ($to !== null) {
            $db = new Conexion; $db->cdp_query("UPDATE $head SET status_courier = :s WHERE consolidate_id = :c"); $db->bind(':s', (string) $to); $db->bind(':c', $cid); $db->cdp_execute();
        }
    }
}
/** One message per customer after a handover (WhatsApp with the handed_over SMS copy, and e-mail). */
function slw_notify(int $senderId, array $trackings, int $status, $core): void
{
    $sender = cdp_getSenderCourier($senderId);
    if (!$sender || !$trackings) return;
    require_once CDP_ROOT . '/helpers/after_response.php';
    cdp_afterResponse(function () use ($sender, $trackings, $status, $core) {
        $site = trim((string) ($core->site_name ?? ''));
        $plural = count($trackings) > 1;
        $text = $status === CDP_STATUS_PICKED_UP ? cdp_handoverPickedUpText(cdp_nameWithLocker($sender), $trackings, $site)
            : 'Hello ' . cdp_nameWithLocker($sender) . ",\n\nYour package" . ($plural ? 's ' : ' ') . '*' . implode(', ', $trackings) . '* ' . cdp_handoverPhrase($status, $plural) . '. Thank you for shipping with ' . ($site ?: 'us') . '!';
        try {
            if (trim((string) ($sender->phone ?? '')) !== '') {
                require_once CDP_ROOT . '/ajax/notify_whatsapp/api_whatsapp_service_v2.php';
                sendNotificationWhatsApp_v2($sender, $text, 'GH', ['sms_event' => 'handed_over']);
            }
        } catch (Throwable $e) { error_log('sl handover WA: ' . $e->getMessage()); }
        try {
            $email = trim((string) ($sender->email ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                require_once CDP_ROOT . '/helpers/phpmailer/class.phpmailer.php';
                require_once CDP_ROOT . '/helpers/phpmailer/class.smtp.php';
                $subject = cdp_handoverSubject($status, implode(', ', $trackings));
                cdp_sendTemplateEmail(29, $email, ['[USERNAME]' => htmlspecialchars(cdp_nameWithLocker($sender)), '[SUBJECT]' => $subject, '[MESSAGE]' => '<p>' . nl2br(htmlspecialchars(str_replace('*', '', $text))) . '</p>'], $subject . ($site ? ' — ' . $site : ''));
            }
        } catch (Throwable $e) { error_log('sl handover mail: ' . $e->getMessage()); }
    });
}

switch ($op) {
    case 'code_state':
    case 'code_send':
    case 'code_verify':
        if (!cdp_pickupCodeCanUse($user)) sl_json(['success' => false, 'message' => 'You do not have permission to use pickup codes.'], 403);
        $module = cdp_pickupCodeModule((string) ($_POST['module'] ?? 'air'));
        $ids = slw_ids();
        if (!$ids) sl_json(['success' => false, 'message' => 'No package specified.'], 422);
        foreach ($ids as $id) if (!slw_pkg($module, $id)) sl_json(['success' => false, 'message' => 'Package not found'], 404);
        if ($op === 'code_state') {
            $pkgs = cdp_pickupCodePackages($module, $ids);
            $owners = array_values(array_unique(array_map(fn($p) => (int) $p->sender_id, $pkgs)));
            $owner = count($owners) === 1 && $owners[0] > 0 ? cdp_getSenderCourier($owners[0]) : null;
            sl_json(['success' => true, 'owner' => $owner ? cdp_nameWithLocker($owner) : ''] + cdp_pickupCodeState($module, $ids));
        }
        if ($op === 'code_send') {
            $res = cdp_pickupCodeGenerate($module, $ids, (string) ($_POST['context'] ?? 'sl_warehouse'), $uid);
            if (!empty($res['success'])) cdp_activityLog(['module' => 'pickup_codes', 'action' => 'send', 'action_label' => 'Sent pickup code', 'verb' => 'update', 'outcome' => 'ok', 'summary' => 'Pickup code sent for ' . implode(', ', $res['trackings'] ?? []), 'entity_type' => 'pickup_code', 'entity_id' => (int) ($res['code_id'] ?? 0)]);
            unset($res['owner']);
            sl_json(['success' => !empty($res['success']), 'message' => $res['message'] ?? '', 'channels' => $res['channels'] ?? []]);
        }
        $res = cdp_pickupCodeVerify($module, $ids, (string) ($_POST['code'] ?? ''), $uid);
        cdp_activityLog(['module' => 'pickup_codes', 'action' => 'verify', 'action_label' => 'Verified pickup code', 'verb' => 'update', 'outcome' => !empty($res['success']) ? 'ok' : 'fail', 'summary' => ($res['success'] ? 'Code verified for ' : 'Wrong code for ') . $module . ' #' . implode(',', $ids), 'entity_type' => 'package', 'entity_id' => $ids[0]]);
        sl_json(['success' => !empty($res['success']), 'message' => $res['message'], 'errors' => empty($res['success']) ? ['code' => $res['message']] : null], empty($res['success']) ? 422 : 200);
        break;

    case 'verify_customer':
        // HandOut / WarehouseDelivery: the code the customer shows, checked against all their ready packages (per module).
        if (!cdp_pickupCodeCanUse($user)) sl_json(['success' => false, 'message' => 'You do not have permission to use pickup codes.'], 403);
        $c = sl_whCustomer((int) ($_POST['customer'] ?? 0));
        if (!$c || !$c['pkgs']) sl_json(['success' => false, 'message' => 'This customer has no parcel ready.'], 404);
        $code = preg_replace('/\D+/', '', (string) ($_POST['code'] ?? ''));
        if (strlen($code) !== 6) sl_json(['success' => false, 'message' => 'Type the 6-digit code', 'errors' => ['code' => '6 digits']], 422);
        $ok = false; $msg = '';
        foreach ($c['byMod'] as $m => $ids) {
            if (!$ids) continue;
            $live = cdp_pickupCodeLiveFor($m, $ids);
            if (!$live) { $msg = $msg ?: 'The customer has no pickup code yet. Send one first.'; continue; }
            $r = cdp_pickupCodeVerify($m, $ids, $code, $uid);
            if (!empty($r['success'])) { $ok = true; $msg = $r['message']; break; }
            $msg = $r['message'];
        }
        cdp_activityLog(['module' => 'pickup_codes', 'action' => 'verify', 'action_label' => 'Verified pickup code', 'verb' => 'update', 'outcome' => $ok ? 'ok' : 'fail', 'summary' => ($ok ? 'Code verified for ' : 'Wrong code for ') . $c['name'], 'entity_type' => 'customer', 'entity_id' => $c['id']]);
        $verified = [];
        foreach ($c['byMod'] as $m => $ids) foreach (array_keys(cdp_pickupCodeVerifiedFor($m, $ids)) as $oid) $verified[] = $m . ':' . $oid;
        sl_json(['success' => $ok, 'message' => $msg, 'verified' => $verified], $ok ? 200 : 422);
        break;

    case 'send_customer':
        if (!cdp_pickupCodeCanUse($user)) sl_json(['success' => false, 'message' => 'You do not have permission to use pickup codes.'], 403);
        $c = sl_whCustomer((int) ($_POST['customer'] ?? 0));
        if (!$c || !$c['pkgs']) sl_json(['success' => false, 'message' => 'This customer has no parcel ready.'], 404);
        $msgs = []; $ok = false;
        foreach ($c['byMod'] as $m => $ids) {
            if (!$ids) continue;
            $r = cdp_pickupCodeGenerate($m, $ids, 'sl_handout', $uid);
            $ok = $ok || !empty($r['success']); $msgs[] = $r['message'];
        }
        sl_json(['success' => $ok, 'message' => implode(' ', array_unique($msgs))]);
        break;

    case 'handover':
        $items = [];
        foreach (explode(',', (string) ($_POST['items'] ?? '')) as $it) {
            if (preg_match('/^(air|sea):(\d+)$/', trim($it), $m)) $items[$m[1]][] = (int) $m[2];
        }
        if (!$items) sl_json(['success' => false, 'message' => 'Pick the packages to hand over'], 422);
        $total = array_sum(array_map('count', $items));
        require_permission($total > 1 ? ['warehouse_deliver_user', 'warehouse_deliver_package'] : 'warehouse_deliver_package');
        if ($total > 1 && !$user->cdp_hasPermission('warehouse_deliver_user')) sl_json(['success' => false, 'message' => 'You may hand over one package at a time.'], 403);
        $status = cdp_handoverStatusFromInput($_POST['handover_type'] ?? '');
        $label = cdp_handoverLabel($status);
        $rows = [];
        foreach ($items as $m => $ids) {
            foreach ($ids as $id) {
                $r = slw_pkg($m, $id);
                if (!$r) sl_json(['success' => false, 'message' => 'Package not found'], 404);
                if (cdp_isHandedOver($r->status_courier)) continue;
                if (in_array((int) $r->status_courier, [16, 21, 27, 35, 12], true)) sl_json(['success' => false, 'message' => $r->order_prefix . $r->order_no . ' can no longer be handed over.'], 409);
                if ($m === 'air' && !cdp_fsIsCleared($r->fs_cleared_for_delivery ?? 0)) sl_json(['success' => false, 'message' => $r->order_prefix . $r->order_no . ' is not cleared by Accounts yet.'], 409);
                $rows[] = [$m, $r];
            }
            $gate = cdp_pickupCodeGate($m, $ids, $status);
            if (!$gate['ok']) sl_json(['success' => false, 'message' => $gate['message'], 'pickup_code' => ['module' => $m, 'order_ids' => $gate['missing']]], 409);
        }
        if (!$rows) sl_json(['success' => false, 'message' => 'These packages were already handed over.'], 409);
        $bySender = [];
        foreach ($rows as [$m, $r]) {
            $table = $m === 'sea' ? 'cdb_customers_packages' : 'cdb_add_order';
            $db = new Conexion; $db->cdp_query("UPDATE $table SET status_courier = :s WHERE order_id = :id"); $db->bind(':s', $status); $db->bind(':id', (int) $r->order_id); $db->cdp_execute();
            cdp_pickupCodeConsume($m, [(int) $r->order_id], $uid);
            $trk = $r->order_prefix . $r->order_no;
            cdp_insertCourierShipmentTrack(['user_id' => $uid, 'order_id' => (int) $r->order_id, 'order_track' => $trk, 't_date' => date('Y-m-d H:i:s'), 'status_courier' => $status, 'comments' => cdp_handoverTrackComment($status) . ' (Warehouse)', 'office' => (int) ($r->origin_off ?? 0)]);
            cdp_insertCourierShipmentUserHistory(['user_id' => $uid, 'order_id' => (int) $r->order_id, 'order_track' => $trk, 'action' => 'Warehouse — package ' . strtolower($label), 'date_history' => date('Y-m-d H:i:s')]);
            slw_syncConsolidation((int) $r->order_id, $m === 'sea');
            cdp_activityLogStatus($m === 'sea' ? 'packages' : 'shipments', $m === 'sea' ? 'package' : 'shipment', (int) $r->order_id, $trk, $status, $label, sl_statusLabel($r->status_courier));
            $bySender[(int) $r->sender_id][] = $trk;
        }
        foreach ($bySender as $sid => $trks) slw_notify($sid, $trks, $status, $core);
        sl_json(['success' => true, 'message' => count($rows) . ' package' . (count($rows) === 1 ? '' : 's') . ' ' . strtolower($label), 'label' => $label, 'n' => count($rows)]);
        break;

    case 'undo':
        require_permission('warehouse_undo_delivery');
        $m = ($_POST['module'] ?? '') === 'sea' ? 'sea' : 'air';
        $r = slw_pkg($m, (int) ($_POST['id'] ?? 0));
        if (!$r) sl_json(['success' => false, 'message' => 'Package not found'], 404);
        if (!cdp_isHandedOver($r->status_courier)) sl_json(['success' => false, 'message' => 'That package is not marked picked up or delivered.'], 409);
        $table = $m === 'sea' ? 'cdb_customers_packages' : 'cdb_add_order';
        $db = new Conexion; $db->cdp_query("UPDATE $table SET status_courier = 4 WHERE order_id = :id"); $db->bind(':id', (int) $r->order_id); $db->cdp_execute();
        $trk = $r->order_prefix . $r->order_no;
        cdp_insertCourierShipmentTrack(['user_id' => $uid, 'order_id' => (int) $r->order_id, 'order_track' => $trk, 't_date' => date('Y-m-d H:i:s'), 'status_courier' => 4, 'comments' => cdp_handoverLabel($r->status_courier) . ' reversed via Warehouse', 'office' => (int) ($r->origin_off ?? 0)]);
        cdp_insertCourierShipmentUserHistory(['user_id' => $uid, 'order_id' => (int) $r->order_id, 'order_track' => $trk, 'action' => strtolower(cdp_handoverLabel($r->status_courier)) . ' reversed (reopened)', 'date_history' => date('Y-m-d H:i:s')]);
        slw_syncConsolidation((int) $r->order_id, $m === 'sea');
        cdp_activityLog(['module' => 'warehouse', 'action' => 'undo_handover', 'action_label' => 'Undid handover', 'verb' => 'update', 'outcome' => 'ok', 'summary' => 'Reopened ' . $trk, 'entity_type' => $m === 'sea' ? 'package' : 'shipment', 'entity_id' => (int) $r->order_id, 'entity_label' => $trk, 'status_id' => 4]);
        sl_json(['success' => true, 'message' => $trk . ' reopened (In warehouse)']);
        break;

    case 'receive':
    case 'move':
        require_permission(['warehouse_receive', 'add_package', 'add_shipment']);
        $bay = mb_substr(strtoupper(trim(cdp_sanitize($_POST['bay'] ?? ''))), 0, 12);
        if ($op === 'receive') {
            $t = trim((string) ($_POST['tracking'] ?? ''));
            if ($t === '') sl_json(['success' => false, 'message' => 'Scan or type a tracking number', 'errors' => ['tracking' => 'Required']], 422);
            $hit = sl_row("SELECT 'air' AS m, order_id FROM cdb_add_order WHERE is_deleted = 0 AND (CONCAT(order_prefix, order_no) = :t OR order_id IN (SELECT order_id FROM cdb_package_tracking_number WHERE tracking_number = :t2)) UNION ALL SELECT 'sea', order_id FROM cdb_customers_packages WHERE CONCAT(order_prefix, order_no) = :t3 OR tracking_purchase = :t4 LIMIT 1", [':t' => $t, ':t2' => $t, ':t3' => $t, ':t4' => $t]);
            if (!$hit) {
                $pa = sl_row('SELECT pre_alert_id FROM cdb_pre_alert WHERE tracking = :t AND is_package = 0', [':t' => $t]);
                sl_json(['success' => false, 'message' => $pa ? 'Not registered yet, but a pre-alert matches: register it as a package.' : 'No package with this tracking number.', 'redirect' => $pa ? sl_route('NewPackage', ['prealert_id' => (int) $pa->pre_alert_id]) : null], 404);
            }
            $m = $hit->m; $r = slw_pkg($m, (int) $hit->order_id);
        } else {
            $m = ($_POST['module'] ?? '') === 'sea' ? 'sea' : 'air';
            $r = slw_pkg($m, (int) ($_POST['id'] ?? 0));
        }
        if (!$r) sl_json(['success' => false, 'message' => 'Package not found'], 404);
        if (cdp_isHandedOver($r->status_courier) || (int) $r->status_courier === 21) sl_json(['success' => false, 'message' => 'This package is closed'], 409);
        $table = $m === 'sea' ? 'cdb_customers_packages' : 'cdb_add_order';
        $trk = $r->order_prefix . $r->order_no;
        $db = new Conexion;
        if (sl_columnExists($table, 'warehouse_bay') && $bay !== '') { $db->cdp_query("UPDATE $table SET warehouse_bay = :b WHERE order_id = :id"); $db->bind(':b', $bay); $db->bind(':id', (int) $r->order_id); $db->cdp_execute(); }
        $moved = false;
        if ($op === 'receive' && in_array((int) $r->status_courier, [1, 2, 3, 5], true)) {
            $db->cdp_query("UPDATE $table SET status_courier = 4 WHERE order_id = :id"); $db->bind(':id', (int) $r->order_id); $db->cdp_execute();
            cdp_insertCourierShipmentTrack(['user_id' => $uid, 'order_id' => (int) $r->order_id, 'order_track' => $trk, 't_date' => date('Y-m-d H:i:s'), 'status_courier' => 4, 'comments' => 'Received at the warehouse' . ($bay ? ' · bay ' . $bay : ''), 'office' => (int) ($r->origin_off ?? 0)]);
            $moved = true;
        }
        if (sl_tableExists('cdb_warehouse_scans')) {
            $db->cdp_query('INSERT INTO cdb_warehouse_scans (module, order_id, tracking, action, bay, note, user_id, created_at) VALUES (:m, :o, :t, :a, :b, :n, :u, NOW())');
            $db->bind(':m', $m); $db->bind(':o', (int) $r->order_id); $db->bind(':t', $trk); $db->bind(':a', $op); $db->bind(':b', $bay ?: null); $db->bind(':n', $moved ? 'set to In warehouse' : sl_statusLabel($r->status_courier)); $db->bind(':u', $uid); $db->cdp_execute();
        }
        $owner = cdp_getSenderCourier((int) $r->sender_id);
        cdp_activityLog(['module' => 'warehouse', 'action' => $op, 'action_label' => $op === 'receive' ? 'Received parcel' : 'Moved parcel', 'verb' => 'update', 'outcome' => 'ok', 'summary' => $trk . ($op === 'receive' ? ' received' : ' moved') . ($bay ? ' to bay ' . $bay : ''), 'entity_type' => $m === 'sea' ? 'package' : 'shipment', 'entity_id' => (int) $r->order_id, 'entity_label' => $trk]);
        sl_json(['success' => true, 'message' => $trk . ' ' . ($op === 'receive' ? 'received' : 'moved') . ($owner ? ' · ' . cdp_nameWithLocker($owner) : '') . ' · ' . sl_weight($r->total_weight) . ($bay ? ' · bay ' . $bay : '')]);
        break;

    case 'aging_notify':
        require_permission('view_pickup_aging');
        require_once CDP_ROOT . '/helpers/pickup_aging.php';
        cdp_processPickupAging();
        $ids = array_map(fn($r) => (int) $r->order_id, (array) cdp_pickupAgingPending());
        if (!$ids) sl_json(['success' => true, 'message' => 'No customer is due a 14-day reminder.']);
        $res = cdp_pickupAgingNotifySenders($ids, $uid);
        sl_json(['success' => true, 'message' => 'Reminders sent for ' . count($ids) . ' package(s)', 'result' => $res]);
        break;

    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
