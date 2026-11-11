<?php
/**
 * Sea package writes for the SeaPackages / NewPackage / PackageDetail pages.
 * Reuses helpers/querys.php (cdp_insertCustomerPackages & co.), helpers/pickup_code.php
 * and the notification helpers the legacy endpoints use.
 *
 * GET  op=addresses&id=   sender addresses of a customer
 * GET  op=prealert&tracking=   open pre-alert for a supplier tracking number
 * POST op=create | delete | driver | status | bulk_status | bulk_driver | confirm_payment | email
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/sl_auth.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/unique_ids.php';
require_once __DIR__ . '/../../helpers/pickup_code.php';
require_once CDP_ROOT . '/views/sl/data/ops_common.php';
$user = new User; $core = new Core;
require_login();
$op = (string) ($_REQUEST['op'] ?? '');
$uid = (int) $_SESSION['userid'];

if ($op === 'addresses') {
    require_permission(['add_package', 'edit_package']);
    sl_json(['success' => true, 'addresses' => sl_opsAddresses((int) ($_GET['id'] ?? 0))]);
}
if ($op === 'prealert') {
    require_permission(['add_package', 'edit_package']);
    $t = trim((string) ($_GET['tracking'] ?? ''));
    $p = $t !== '' ? sl_row('SELECT p.*, u.fname, u.lname FROM cdb_pre_alert p LEFT JOIN cdb_users u ON u.id = p.customer_id WHERE p.tracking = :t AND p.is_package = 0', [':t' => $t]) : null;
    sl_json(['success' => true, 'prealert' => $p ? ['id' => (int) $p->pre_alert_id, 'shop' => $p->provider_shop, 'what' => $p->package_description, 'customer' => trim($p->fname . ' ' . $p->lname), 'customer_id' => (int) $p->customer_id] : null]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
if (sl_userIs('client')) {
    sl_json(['success' => false, 'message' => 'Not allowed'], 403);
}

/** Package row in scope or a 404. */
function slp_pkg(int $id): object
{
    $p = cdp_getCustomerPackage($id);
    if (!$p || !sl_opsInScope($p)) {
        sl_json(['success' => false, 'message' => 'Package not found'], 404);
    }
    return $p;
}

switch ($op) {
    case 'create':
        require_permission('add_package');
        $errors = [];
        $need = ['sender_id' => 'Pick a customer', 'sender_address_id' => 'Pick an address', 'tracking_purchase' => 'Supplier tracking is required', 'provider_purchase' => 'Supplier is required', 'agency' => 'Agency is required', 'origin_off' => 'Warehouse is required', 'order_package' => 'Packaging is required', 'order_courier' => 'Carrier is required', 'order_deli_time' => 'Delivery time is required'];
        foreach ($need as $k => $msg) {
            if (empty($_POST[$k])) $errors[$k] = $msg;
        }
        $boxes = json_decode((string) ($_POST['packages'] ?? '[]'), true);
        $boxes = is_array($boxes) ? array_values(array_filter($boxes, fn($b) => trim((string) ($b['description'] ?? '')) !== '')) : [];
        if (!$boxes) $errors['packages'] = 'Add at least one box with its contents';
        if ($errors) sl_json(['success' => false, 'message' => reset($errors), 'errors' => $errors], 422);
        $senderId = (int) $_POST['sender_id'];
        if (!sl_row('SELECT id FROM cdb_users WHERE id = :id AND userlevel = 1', [':id' => $senderId])) sl_json(['success' => false, 'message' => 'Customer not found'], 404);
        if (!sl_row('SELECT id_addresses FROM cdb_senders_addresses WHERE id_addresses = :a AND user_id = :u', [':a' => (int) $_POST['sender_address_id'], ':u' => $senderId])) sl_json(['success' => false, 'message' => 'That address does not belong to the customer', 'errors' => ['sender_address_id' => 'Pick one of the customer\'s addresses']], 422);
        $defaults = cdp_getInfoShipDefault();
        $prefix = cdp_sanitize($_POST['code_prefix'] ?? '') ?: (string) ($core->prefix_online_shopping ?: 'PK-');
        $orderNo = cdp_uidClaim('package_no', (string) ($_POST['order_no'] ?? ''));
        $date = date('Y-m-d', strtotime((string) ($_POST['order_date'] ?? 'now')) ?: time()) . ' ' . date('H:i:s');
        $status = 2; // Received Office, as the legacy form
        $data = [
            'user_id' => $uid, 'order_prefix' => $prefix, 'order_no' => $orderNo, 'agency' => (int) $_POST['agency'], 'origin_off' => (int) $_POST['origin_off'],
            'sender_id' => $senderId, 'sender_address_id' => (int) $_POST['sender_address_id'],
            'tracking_purchase' => cdp_sanitize($_POST['tracking_purchase']), 'provider_purchase' => cdp_sanitize($_POST['provider_purchase']), 'price_purchase' => (float) ($_POST['price_purchase'] ?? 0),
            'order_package' => (int) $_POST['order_package'], 'order_item_category' => (int) ($_POST['order_item_category'] ?? 0) ?: (int) ($defaults->logistics_default1 ?? 0),
            'order_courier' => (int) $_POST['order_courier'], 'order_service_options' => (int) ($_POST['order_service_options'] ?? 0) ?: (int) ($defaults->service_default4 ?? 0),
            'order_deli_time' => (int) $_POST['order_deli_time'], 'status_courier' => $status, 'driver_id' => (int) ($_POST['driver_id'] ?? 0),
            'order_date' => date('Y-m-d H:i:s'), 'order_datetime' => $date, 'status_invoice' => 2, 'is_prealert' => (int) ($_POST['prealert_id'] ?? 0) > 0 ? 1 : 0,
            'volumetric_percentage' => (string) ($core->meter ?: 500), 'recipient_id' => 0, 'recipient_address_id' => 0, 'recipient_type' => 'recipient', 'order_payment_method' => 0,
            'notify_whatsapp_sender' => (int) !empty($_POST['notify_whatsapp']),
        ];
        $id = cdp_insertCustomerPackages($data);
        if (!$id) sl_json(['success' => false, 'message' => 'The package could not be saved'], 500);
        $id = (int) $id;
        if (!empty($_POST['estimated_eta'])) cdp_setPackageEta($id, cdp_sanitize($_POST['estimated_eta']));
        // boxes and totals: the same arithmetic as ajax/customers_packages/add_customers_packages_ajax.php
        $meter = (float) ($core->meter ?: 500); $rate = (float) ($core->value_weight ?? 0); $tax = 0; $ins = (float) ($core->insurance ?? 0);
        $disc = max(0, min(100, (float) ($_POST['discount_value'] ?? 0)));
        $w = 0; $vw = 0; $declared = 0; $fixed = 0;
        foreach ($boxes as $b) {
            $row = ['order_id' => $id, 'qty' => max(1, (float) ($b['qty'] ?? 1)), 'description' => mb_substr(trim((string) $b['description']), 0, 250), 'length' => (float) ($b['length'] ?? 0), 'width' => (float) ($b['width'] ?? 0), 'height' => (float) ($b['height'] ?? 0), 'weight' => (float) ($b['weight'] ?? 0), 'declared_value' => (float) ($b['declared_value'] ?? 0), 'fixed_value' => (float) ($b['fixed_value'] ?? 0)];
            cdp_insertCustomerPackagesItems($row);
            $w += $row['weight']; $vw += $row['length'] * $row['width'] * $row['height'] / max(1, $meter); $declared += $row['declared_value']; $fixed += $row['fixed_value'];
        }
        $freight = max($w, $vw) * $rate;
        $discount = $freight * $disc / 100;
        $taxAmt = $freight > (float) ($core->min_cost_tax ?? 0) ? ($freight - $discount) * $tax / 100 : 0;
        $insAmt = sl_insuranceCharge((float) $declared, (float) $ins);
        $total = $freight - $discount + $taxAmt + $insAmt + $fixed;
        cdp_updateCustomerPackagesTotals(['order_id' => $id, 'value_weight' => $rate, 'sub_total' => $freight, 'tax_discount' => $disc, 'total_insured_value' => $declared, 'tax_insurance_value' => $ins, 'tax_custom_tariffis_value' => 0, 'tax_value' => $tax, 'declared_value' => 0, 'total_reexp' => 0, 'total_declared_value' => $declared, 'total_fixed_value' => $fixed, 'total_tax_discount' => $discount, 'total_tax_insurance' => $insAmt, 'total_tax_custom_tariffis' => 0, 'total_tax' => $taxAmt, 'total_weight' => $w + $vw, 'total_order' => $total]);
        $track = $prefix . $orderNo;
        if (!empty($_POST['notes'])) {
            $db = new Conexion; $db->cdp_query('UPDATE cdb_customers_packages SET notes = :n WHERE order_id = :id'); $db->bind(':n', mb_substr(cdp_sanitize($_POST['notes']), 0, 2000)); $db->bind(':id', $id); $db->cdp_execute();
        }
        // files
        if (!empty($_FILES['files']['name'][0])) {
            $dir = CDP_ROOT . '/order_files/';
            foreach ($_FILES['files']['tmp_name'] as $k => $tmp) {
                if (!is_uploaded_file($tmp) || (int) $_FILES['files']['size'][$k] <= 0 || (int) $_FILES['files']['size'][$k] > 5242880) continue;
                $ext = strtolower(pathinfo((string) $_FILES['files']['name'][$k], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'], true)) continue;
                $name = preg_replace('/[^A-Za-z0-9_.-]/', '_', $track . '_' . date('Ymd_His') . '_' . $k . '.' . $ext);
                if (move_uploaded_file($tmp, $dir . $name)) cdp_insertCustomerPackagesFiles($id, 'order_files/' . $name, $name, date('Y-m-d H:i:s'), $ext);
            }
        }
        cdp_insertCourierShipmentTrack(['user_id' => $uid, 'order_id' => $id, 'order_track' => $track, 't_date' => date('Y-m-d H:i:s'), 'status_courier' => $status, 'comments' => 'Package registered', 'office' => (int) $_POST['origin_off']]);
        cdp_insertCourierShipmentUserHistory(['user_id' => $uid, 'order_id' => $id, 'order_track' => $track, 'action' => 'Package registered', 'date_history' => date('Y-m-d H:i:s')]);
        $pid = (int) ($_POST['prealert_id'] ?? 0);
        if ($pid > 0) cdp_updatedPreAlertPackage($pid);
        $cid = (int) ($_POST['consolidate_id'] ?? 0);
        if ($cid > 0 && sl_row('SELECT consolidate_id FROM cdb_consolidate_packages WHERE consolidate_id = :c AND status_courier NOT IN (8,15,21)', [':c' => $cid])) {
            $db = new Conexion;
            $db->cdp_query('INSERT INTO cdb_consolidate_packages_detail (consolidate_id, order_id, order_prefix, order_no, weight, length, width, height, weight_vol) VALUES (:c, :o, :p, :n, :w, 0, 0, 0, :v)');
            $db->bind(':c', $cid); $db->bind(':o', $id); $db->bind(':p', $prefix); $db->bind(':n', $orderNo); $db->bind(':w', $w); $db->bind(':v', $vw); $db->cdp_execute();
            $db->cdp_query('UPDATE cdb_customers_packages SET is_consolidate = 1 WHERE order_id = :id'); $db->bind(':id', $id); $db->cdp_execute();
        }
        // customer notifications: the same helpers as the legacy form (best-effort, after the response)
        $sender = cdp_getSenderCourier($senderId);
        try {
            require_once CDP_ROOT . '/helpers/after_response.php';
            $notifyWa = !empty($_POST['notify_whatsapp']); $notifyMail = !empty($_POST['notify_email']);
            cdp_afterResponse(function () use ($sender, $track, $data, $id, $notifyWa, $notifyMail, $core) {
                try {
                    if ($notifyWa && $sender && !empty($sender->phone)) {
                        require_once CDP_ROOT . '/helpers/whatsapp.php';
                        $extra = cdp_wa_buildShipmentExtraLines($id, 'sea');
                        cdp_sendShipmentRegisteredWhatsApp($sender, $track, ['courier' => $data['order_courier'], 'service' => $data['order_service_options'], 'delitime' => $data['order_deli_time'], 'office' => $data['origin_off']], $extra);
                    }
                } catch (Throwable $e) { error_log('sl package WA: ' . $e->getMessage()); }
                try {
                    require_once CDP_ROOT . '/helpers/hubtel_sms.php';
                    if ($sender && !empty($sender->phone) && function_exists('cdp_smsEventNotify')) {
                        cdp_smsEventNotify($sender, trim((string) $core->site_name) . ': your package ' . $track . ' has been registered at our warehouse.', 'registered');
                    }
                } catch (Throwable $e) { error_log('sl package SMS: ' . $e->getMessage()); }
                try {
                    if ($notifyMail && $sender && !empty($sender->email) && filter_var($sender->email, FILTER_VALIDATE_EMAIL)) {
                        require_once CDP_ROOT . '/helpers/phpmailer/class.phpmailer.php';
                        require_once CDP_ROOT . '/helpers/phpmailer/class.smtp.php';
                        $url = rtrim((string) $core->site_url, '/') . '/track_online_shopping.php?order_track=' . urlencode($track);
                        cdp_sendTemplateEmail(16, $sender->email, ['[NAME]' => htmlspecialchars(cdp_nameWithLocker($sender)), '[TRACKING]' => $track, '[DELIVERY_TIME]' => date('Y-m-d H:i'), '[URL]' => (string) $core->site_url, '[URL_LINK]' => (string) $core->logo, '[SITE_NAME]' => (string) $core->site_name, '[URL_SHIP]' => $url], 'Package registered - ' . $track);
                    }
                } catch (Throwable $e) { error_log('sl package mail: ' . $e->getMessage()); }
            });
        } catch (Throwable $e) {
            error_log('sl package notify: ' . $e->getMessage());
        }
        cdp_activityLog(['module' => 'packages', 'action' => 'create', 'action_label' => 'Registered package', 'verb' => 'create', 'outcome' => 'ok', 'summary' => 'Registered package ' . $track, 'entity_type' => 'package', 'entity_id' => $id, 'entity_label' => $track, 'status_id' => $status, 'meta' => ['sender_id' => $senderId, 'prealert_id' => $pid, 'consolidate_id' => $cid]]);
        sl_json(['success' => true, 'message' => 'Package ' . $track . ' registered', 'id' => $id, 'redirect' => sl_route('PackageDetail', ['id' => $id])]);
        break;

    case 'delete':
        require_permission('delete_package');
        $p = slp_pkg((int) ($_POST['id'] ?? 0));
        if ((int) $p->is_consolidate === 1 || cdp_isHandedOver($p->status_courier) || (int) $p->status_invoice === 1) {
            sl_json(['success' => false, 'message' => 'This package can no longer be deleted (paid, consolidated or handed over).'], 409);
        }
        $track = $p->order_prefix . $p->order_no;
        $ok = cdp_deleteFullCourierpackage(['id' => (int) $p->order_id]);
        if ($ok) cdp_activityLog(['module' => 'packages', 'action' => 'delete', 'action_label' => 'Deleted package', 'verb' => 'delete', 'outcome' => 'ok', 'summary' => 'Deleted package ' . $track, 'entity_type' => 'package', 'entity_id' => (int) $p->order_id, 'entity_label' => $track]);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Package ' . $track . ' deleted' : 'Could not delete', 'redirect' => $ok ? sl_route('SeaPackages') : null]);
        break;

    case 'driver':
    case 'bulk_driver':
        require_permission('assign_driver_package');
        $driver = (int) ($_POST['driver_id'] ?? 0);
        if (!sl_row('SELECT id FROM cdb_users WHERE id = :id AND userlevel = 3', [':id' => $driver])) sl_json(['success' => false, 'message' => 'Pick a driver', 'errors' => ['driver_id' => 'Required']], 422);
        $ids = $op === 'driver' ? [(int) ($_POST['id'] ?? 0)] : array_filter(array_map('intval', explode(',', (string) ($_POST['ids'] ?? ''))));
        $n = 0;
        foreach ($ids as $id) {
            $p = slp_pkg($id);
            if ((int) $p->is_consolidate === 1 || cdp_isHandedOver($p->status_courier)) continue;
            cdp_updateDriverPackage(['id_shipment' => $id, 'driver_id' => $driver]);
            $n++;
            cdp_insertCourierShipmentUserHistory(['user_id' => $uid, 'order_id' => $id, 'order_track' => $p->order_prefix . $p->order_no, 'action' => 'Driver assigned', 'date_history' => date('Y-m-d H:i:s')]);
            cdp_activityLog(['module' => 'packages', 'action' => 'assign_driver', 'action_label' => 'Assigned driver', 'verb' => 'update', 'outcome' => 'ok', 'summary' => 'Driver #' . $driver . ' assigned to ' . $p->order_prefix . $p->order_no, 'entity_type' => 'package', 'entity_id' => $id, 'entity_label' => $p->order_prefix . $p->order_no]);
        }
        try {
            $nid = (int) cdp_insertNotification(['user_id' => $uid, 'order_id' => (int) reset($ids), 'notification_description' => $n . ' package(s) assigned to you', 'shipping_type' => 'package', 'notification_date' => date('Y-m-d H:i:s')]);
            if ($nid) cdp_insertNotificationsUsers($nid, $driver);
        } catch (Throwable $e) { /* best-effort: the assignment itself is saved */ }
        sl_json(['success' => true, 'message' => $n . ' package(s) assigned']);
        break;

    case 'status':
    case 'bulk_status':
        require_permission($op === 'status' ? 'track_package' : 'select_change_status');
        $status = (int) ($_POST['status'] ?? 0);
        if (!isset(sl_statusMap()[$status])) sl_json(['success' => false, 'message' => 'Pick a status', 'errors' => ['status' => 'Required']], 422);
        if (cdp_isHandedOver($status)) sl_json(['success' => false, 'message' => 'Handing over needs a verified pickup code: use the Hand over page.'], 422);
        $ids = $op === 'status' ? [(int) ($_POST['id'] ?? 0)] : array_filter(array_map('intval', explode(',', (string) ($_POST['ids'] ?? ''))));
        $label = sl_statusLabel($status);
        $comment = mb_substr(cdp_sanitize($_POST['comment'] ?? ''), 0, 250) ?: $label;
        $city = mb_substr(cdp_sanitize($_POST['city'] ?? ''), 0, 250);
        $n = 0;
        require_once CDP_ROOT . '/helpers/after_response.php';
        foreach ($ids as $id) {
            $p = slp_pkg($id);
            if (cdp_isHandedOver($p->status_courier) || (int) $p->status_courier === 21) continue;
            $track = $p->order_prefix . $p->order_no;
            $db = new Conexion;
            $db->cdp_query('UPDATE cdb_customers_packages SET status_courier = :s WHERE order_id = :id'); $db->bind(':s', $status); $db->bind(':id', $id); $db->cdp_execute();
            $db->cdp_query('INSERT INTO cdb_courier_track (order_id, order_track, t_dest, t_city, comments, t_date, status_courier, office_id, user_id) VALUES (:o, :t, :d, :c, :m, NOW(), :s, :off, :u)');
            $db->bind(':o', $id); $db->bind(':t', $track); $db->bind(':d', $label); $db->bind(':c', $city); $db->bind(':m', $comment); $db->bind(':s', $status); $db->bind(':off', (int) $p->origin_off); $db->bind(':u', $uid); $db->cdp_execute();
            cdp_activityLogStatus('packages', 'package', $id, $track, $status, $label, sl_statusLabel($p->status_courier));
            $n++;
            // customer message, with the pickup code when the status is "ready" (helpers/pickup_code.php)
            $sender = cdp_getSenderCourier((int) $p->sender_id);
            if ($sender) {
                $codeLine = cdp_pickupCodeReadyLine('sea', [$id], $status, $label);
                $smsEvent = cdp_statusSmsEvent($status, $label);
                $codeHtml = cdp_pickupCodeReadyLine('sea', [$id], $status, $label, true);
                cdp_afterResponse(function () use ($sender, $track, $label, $comment, $codeLine, $codeHtml, $smsEvent, $core) {
                    $text = 'Hello ' . cdp_nameWithLocker($sender) . ",\n\nYour package *" . $track . '* is now *' . $label . '*.' . ($comment !== $label ? ' ' . $comment : '') . ($codeLine ? "\n\n" . $codeLine : '');
                    try { require_once CDP_ROOT . '/ajax/notify_whatsapp/api_whatsapp_service_v2.php'; if (!empty($sender->phone)) sendNotificationWhatsApp_v2($sender, $text, null, ['sms_event' => $smsEvent]); } catch (Throwable $e) { error_log('sl status WA: ' . $e->getMessage()); }
                    try {
                        if (!empty($sender->email) && filter_var($sender->email, FILTER_VALIDATE_EMAIL)) {
                            require_once CDP_ROOT . '/helpers/phpmailer/class.phpmailer.php'; require_once CDP_ROOT . '/helpers/phpmailer/class.smtp.php';
                            cdp_sendTemplateEmail(29, $sender->email, ['[USERNAME]' => htmlspecialchars(cdp_nameWithLocker($sender)), '[SUBJECT]' => 'Package ' . $track . ' · ' . $label, '[MESSAGE]' => '<p>Your package <b>' . htmlspecialchars($track) . '</b> is now <b>' . htmlspecialchars($label) . '</b>.</p>' . $codeHtml], 'Package ' . $track . ' · ' . $label . ' — ' . $core->site_name);
                        }
                    } catch (Throwable $e) { error_log('sl status mail: ' . $e->getMessage()); }
                });
            }
        }
        sl_json(['success' => true, 'message' => $n . ' package(s) set to ' . $label]);
        break;

    case 'confirm_payment':
        require_permission('verify_payments');
        $p = slp_pkg((int) ($_POST['id'] ?? 0));
        if ((int) $p->status_invoice !== 3) sl_json(['success' => false, 'message' => 'There is no payment waiting to be verified.'], 409);
        $ok = cdp_confirmPaymentPackages(['order_id' => (int) $p->order_id, 'status_invoice' => 1]);
        if ($ok) cdp_activityLog(['module' => 'packages', 'action' => 'confirm_payment', 'action_label' => 'Confirmed payment', 'verb' => 'update', 'outcome' => 'ok', 'summary' => 'Payment confirmed for ' . $p->order_prefix . $p->order_no, 'entity_type' => 'package', 'entity_id' => (int) $p->order_id, 'entity_label' => $p->order_prefix . $p->order_no]);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Payment confirmed' : 'Could not confirm']);
        break;

    case 'email':
        require_permission('send_email_attachment');
        $p = slp_pkg((int) ($_POST['id'] ?? 0));
        $sender = cdp_getSenderCourier((int) $p->sender_id);
        if (!$sender || empty($sender->email)) sl_json(['success' => false, 'message' => 'The customer has no e-mail address'], 422);
        try {
            require_once CDP_ROOT . '/helpers/phpmailer/class.phpmailer.php'; require_once CDP_ROOT . '/helpers/phpmailer/class.smtp.php';
            $track = $p->order_prefix . $p->order_no;
            $ok = cdp_sendTemplateEmail(29, $sender->email, ['[USERNAME]' => htmlspecialchars(cdp_nameWithLocker($sender)), '[SUBJECT]' => 'Your package ' . $track, '[MESSAGE]' => '<p>Package <b>' . htmlspecialchars($track) . '</b> · status ' . htmlspecialchars(sl_statusLabel($p->status_courier)) . ' · total ' . htmlspecialchars(sl_money($p->total_order)) . '.</p><p><a href="' . htmlspecialchars(rtrim((string) $core->site_url, '/') . '/print_invoice_package.php?id=' . (int) $p->order_id) . '">Open the invoice</a></p>'], 'Your package ' . $track . ' — ' . $core->site_name);
        } catch (Throwable $e) { $ok = false; }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'E-mail sent to ' . $sender->email : 'The e-mail could not be sent (check SMTP settings)']);
        break;

    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
