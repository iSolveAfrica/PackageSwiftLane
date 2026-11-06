<?php
/** Create a pre-alert (PreAlerts page). Same rules and helper as ajax/pre_alerts/prealert_add_ajax.php (cdp_insertPreAlert). */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/querys.php';
$user = new User;
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
require_permission('prealert_add');
$isClient = sl_userIs('client');
$errors = [];
foreach (['tracking' => 'Tracking number is required', 'provider' => 'Supplier is required', 'courier' => 'Pick a carrier', 'price' => 'Price is required', 'description' => 'Say what is in it', 'date' => 'Expected date is required'] as $k => $m) {
    if (trim((string) ($_POST[$k] ?? '')) === '') $errors[$k] = $m;
}
$customer = $isClient ? (int) $_SESSION['userid'] : (int) ($_POST['customer_id'] ?? 0);
if (!$isClient) {
    $c = sl_row('SELECT id, agency_id FROM cdb_users WHERE id = :id AND userlevel = 1', [':id' => $customer]);
    if (!$c) $errors['customer_id'] = 'Pick a customer';
    $ctx = cdp_getAgencyContext();
    if ($c && !empty($ctx['is_restricted']) && (int) $c->agency_id !== (int) $ctx['agency_id']) $errors['customer_id'] = 'This customer belongs to another agency';
}
$hasFile = !empty($_FILES['file_invoice']['name']);
if ($isClient && !$hasFile) $errors['file_invoice'] = 'Attach the invoice'; // legacy rule for customers
if ($errors) sl_json(['success' => false, 'message' => reset($errors), 'errors' => $errors], 422);
if (sl_row('SELECT pre_alert_id FROM cdb_pre_alert WHERE tracking = :t AND customer_id = :c', [':t' => trim($_POST['tracking']), ':c' => $customer])) {
    sl_json(['success' => false, 'message' => 'A pre-alert with this tracking number already exists', 'errors' => ['tracking' => 'Already alerted']], 422);
}
$url = '';
if ($hasFile) {
    $f = $_FILES['file_invoice'];
    $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if ($f['error'] !== UPLOAD_ERR_OK || !in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'], true) || $f['size'] > 5242880) {
        sl_json(['success' => false, 'message' => 'The invoice must be a PDF or image under 5 MB', 'errors' => ['file_invoice' => 'Invalid file']], 422);
    }
    $name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!is_dir(CDP_ROOT . '/pre_alert_files')) @mkdir(CDP_ROOT . '/pre_alert_files', 0755, true);
    if (!move_uploaded_file($f['tmp_name'], CDP_ROOT . '/pre_alert_files/' . $name)) sl_json(['success' => false, 'message' => 'The file could not be saved'], 500);
    $url = 'pre_alert_files/' . $name;
}
$data = [
    'tracking_prealert' => cdp_sanitize(trim($_POST['tracking'])), 'provider_prealert' => cdp_sanitize($_POST['provider']), 'courier_prealert' => (int) $_POST['courier'],
    'customer_id' => $customer, 'price_prealert' => (float) $_POST['price'], 'description_prealert' => cdp_sanitize($_POST['description']),
    'estimated_date' => date('Y-m-d', strtotime((string) $_POST['date']) ?: time()), 'prealert_date' => date('Y-m-d H:i:s'), 'file_invoice' => $url,
];
$id = cdp_insertPreAlert($data);
if (!$id) sl_json(['success' => false, 'message' => 'Could not save the pre-alert'], 500);
try {
    $owner = cdp_getSenderCourier($customer);
    $nid = (int) cdp_insertNotification(['user_id' => $customer, 'order_id' => 0, 'notification_description' => 'New pre-alert from ' . trim(($owner->fname ?? '') . ' ' . ($owner->lname ?? '')), 'shipping_type' => '3', 'notification_date' => date('Y-m-d H:i:s')]);
    if ($nid) {
        foreach (cdp_getUsersAdminEmployees() as $e) cdp_insertNotificationsUsers($nid, $e->id);
        cdp_insertNotificationsUsers($nid, $customer);
    }
} catch (Throwable $e) { /* notification is best-effort */ }
cdp_activityLog(['module' => 'prealerts', 'action' => 'create', 'action_label' => 'Raised pre-alert', 'verb' => 'create', 'outcome' => 'ok', 'entity_type' => 'prealert', 'entity_id' => (int) $id, 'entity_label' => $data['tracking_prealert'], 'summary' => 'Raised pre-alert ' . $data['tracking_prealert'] . ' from ' . $data['provider_prealert']]);
sl_json(['success' => true, 'message' => 'Pre-alert saved']);
