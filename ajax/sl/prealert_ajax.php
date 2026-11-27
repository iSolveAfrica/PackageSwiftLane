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
require_once __DIR__ . '/../../helpers/prealert.php';
require_once __DIR__ . '/../../views/sl/data/ops_common.php';
$isClient = sl_userIs('client');
$op = (string) ($_POST['op'] ?? 'create');

/** Pre-alert the current user may see (own for customers, agency scope for restricted staff). */
function slpa_row(int $id)
{
    $r = cdp_preAlertGet($id);
    if (!$r) sl_json(['success' => false, 'message' => 'Pre-alert not found'], 404);
    if (sl_userIs('client') && (int) $r->customer_id !== (int) $_SESSION['userid']) sl_json(['success' => false, 'message' => 'Pre-alert not found'], 404);
    if (!sl_userIs('client')) {
        $ctx = cdp_getAgencyContext();
        if (!empty($ctx['is_restricted']) && (int) sl_scalar('SELECT agency_id FROM cdb_users WHERE id = :id', [':id' => (int) $r->customer_id]) !== (int) $ctx['agency_id']) {
            sl_json(['success' => false, 'message' => 'Pre-alert not found'], 404);
        }
    }
    return $r;
}
$paLog = function (string $action, string $label, $r, string $summary) {
    cdp_activityLog(['module' => 'prealerts', 'action' => $action, 'action_label' => $label, 'verb' => 'update', 'outcome' => 'ok', 'entity_type' => 'prealert', 'entity_id' => (int) $r->pre_alert_id, 'entity_label' => (string) $r->tracking, 'summary' => $summary]);
};

if ($op === 'update') {
    $r = slpa_row((int) ($_POST['id'] ?? 0));
    if (!cdp_preAlertCanEdit($user, $r)) sl_json(['success' => false, 'message' => 'You cannot edit this pre-alert'], 403);
    $errors = [];
    foreach (['tracking' => 'Tracking number is required', 'courier' => 'Pick a carrier', 'description' => 'Say what is in it', 'date' => 'Expected date is required'] as $k => $m) {
        if (trim((string) ($_POST[$k] ?? '')) === '') $errors[$k] = $m;
    }
    if ((float) ($_POST['price'] ?? 0) < 0) $errors['price'] = 'Price cannot be negative';
    $cust = (int) $r->customer_id;
    if (!$isClient && (int) ($_POST['customer_id'] ?? 0) > 0) {
        $cust = (int) $_POST['customer_id'];
        $c = sl_row('SELECT id, agency_id FROM cdb_users WHERE id = :id AND userlevel = 1', [':id' => $cust]);
        if (!$c) $errors['customer_id'] = 'Pick a customer';
        $ctx = cdp_getAgencyContext();
        if ($c && !empty($ctx['is_restricted']) && (int) $c->agency_id !== (int) $ctx['agency_id']) $errors['customer_id'] = 'This customer belongs to another agency';
    }
    $trk = cdp_sanitize(trim((string) ($_POST['tracking'] ?? '')));
    if (!$errors && sl_row('SELECT pre_alert_id FROM cdb_pre_alert WHERE tracking = :t AND customer_id = :c AND pre_alert_id <> :id', [':t' => $trk, ':c' => $cust, ':id' => (int) $r->pre_alert_id])) $errors['tracking'] = 'Another pre-alert already has this tracking number';
    if ($errors) sl_json(['success' => false, 'message' => reset($errors), 'errors' => $errors], 422);
    $url = (string) $r->url_invoice;
    if (!empty($_FILES['file_invoice']['name'])) {
        require_once __DIR__ . '/../../helpers/uploads.php';
        $up = cdp_uploadStore($_FILES['file_invoice'], CDP_ROOT . '/pre_alert_files', 'prealert', 'docs', 5242880);
        if (!$up['ok']) sl_json(['success' => false, 'message' => 'The invoice must be a PDF or image under 5 MB', 'errors' => ['file_invoice' => 'Invalid file']], 422);
        $url = 'pre_alert_files/' . $up['name'];
    }
    $db = new Conexion;
    $db->cdp_query('UPDATE cdb_pre_alert SET tracking = :t, provider_shop = :s, courier_com = :c, customer_id = :cu, purchase_price = :p, package_description = :d, estimated_date = :e, url_invoice = :u'
        . (cdp_preAlertReady() ? ', updated_at = NOW(), updated_by = :by' : '') . ' WHERE pre_alert_id = :id');
    $db->bind(':t', $trk); $db->bind(':s', cdp_sanitize(trim((string) ($_POST['provider'] ?? '')))); $db->bind(':c', (int) $_POST['courier']); $db->bind(':cu', $cust);
    $db->bind(':p', (float) ($_POST['price'] ?? 0)); $db->bind(':d', cdp_sanitize((string) $_POST['description'])); $db->bind(':e', date('Y-m-d', strtotime((string) $_POST['date']) ?: time()));
    $db->bind(':u', $url); $db->bind(':id', (int) $r->pre_alert_id);
    if (cdp_preAlertReady()) $db->bind(':by', (int) $_SESSION['userid']);
    $ok = (bool) $db->cdp_execute();
    if ($ok) $paLog('edit', 'Edited pre-alert', $r, 'Edited pre-alert ' . $trk);
    sl_json(['success' => $ok, 'message' => $ok ? 'Pre-alert updated' : 'Could not save the pre-alert']);
}

if (in_array($op, ['close', 'reopen', 'link'], true)) {
    $r = slpa_row((int) ($_POST['id'] ?? 0));
    if ($isClient || !$user->cdp_hasPermission('prealert_edit')) sl_json(['success' => false, 'message' => 'Not allowed'], 403);
    if ($op === 'close') {
        $reason = trim((string) ($_POST['reason'] ?? '')) ?: 'Closed by staff';
        $ok = cdp_preAlertClose((int) $r->pre_alert_id, $reason);
        if ($ok) $paLog('close', 'Closed pre-alert', $r, 'Closed pre-alert ' . $r->tracking . ': ' . $reason);
        sl_json(['success' => $ok, 'message' => $ok ? 'Pre-alert closed' : 'Only an open pre-alert can be closed']);
    }
    if ($op === 'reopen') {
        $ok = cdp_preAlertReopen((int) $r->pre_alert_id);
        if ($ok) $paLog('reopen', 'Reopened pre-alert', $r, 'Reopened pre-alert ' . $r->tracking);
        sl_json(['success' => $ok, 'message' => $ok ? 'Pre-alert reopened' : 'Could not reopen']);
    }
    // link to a registered package: by its id from the matches list, or by the package number typed in
    $module = (string) ($_POST['module'] ?? '');
    $oid = (int) ($_POST['order_id'] ?? 0);
    $ref = strtoupper(preg_replace('/\s+/', '', (string) ($_POST['ref'] ?? '')));
    if ($oid <= 0 && $ref !== '') {
        foreach ([['package', 'cdb_customers_packages'], ['air', 'cdb_add_order']] as [$m, $t]) {
            $hit = sl_row("SELECT order_id FROM $t WHERE UPPER(CONCAT(order_prefix, order_no)) = :r OR order_no = :n ORDER BY order_id DESC", [':r' => $ref, ':n' => preg_replace('/\D/', '', $ref) ?: '-']);
            if ($hit) { $module = $m; $oid = (int) $hit->order_id; break; }
        }
        if ($oid <= 0) sl_json(['success' => false, 'message' => 'No registered package with the number ' . $ref, 'errors' => ['ref' => 'Not found']], 404);
    }
    $tbl = $module === 'air' ? 'cdb_add_order' : 'cdb_customers_packages';
    $pkg = $oid > 0 ? sl_row("SELECT * FROM $tbl WHERE order_id = :id", [':id' => $oid]) : null;
    if (!$pkg || !sl_opsInScope($pkg)) sl_json(['success' => false, 'message' => 'Package not found'], 404);
    $ok = cdp_preAlertLink((int) $r->pre_alert_id, $module, $oid, !empty($_POST['fill']));
    $num = $pkg->order_prefix . $pkg->order_no;
    if ($ok) $paLog('link', 'Linked pre-alert', $r, 'Linked pre-alert ' . $r->tracking . ' to package ' . $num);
    sl_json(['success' => $ok, 'message' => $ok ? 'Pre-alert converted: it is now package ' . $num : 'Could not link', 'ref' => $num, 'href' => $module === 'air' ? 'courier_edit.php?id=' . $oid : 'customer_package_edit.php?id=' . $oid]);
}

if ($op !== 'create') sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
require_permission('prealert_add');
$errors = [];
foreach (['tracking' => 'Tracking number is required', 'courier' => 'Pick a carrier', 'price' => 'Price is required', 'description' => 'Say what is in it', 'date' => 'Expected date is required'] as $k => $m) {
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
    // The type is checked from the file contents, not the client's extension (helpers/uploads.php):
    // pre_alert_files/ is web-served, so an HTML/script file renamed .jpg must not be stored.
    require_once __DIR__ . '/../../helpers/uploads.php';
    $up = cdp_uploadStore($_FILES['file_invoice'], CDP_ROOT . '/pre_alert_files', 'prealert', 'docs', 5242880);
    if (!$up['ok']) {
        sl_json(['success' => false, 'message' => 'The invoice must be a PDF or image under 5 MB', 'errors' => ['file_invoice' => 'Invalid file']], 422);
    }
    $url = 'pre_alert_files/' . $up['name'];
}
$data = [
    'tracking_prealert' => cdp_sanitize(trim($_POST['tracking'])), 'provider_prealert' => cdp_sanitize((string) ($_POST['provider'] ?? '')), 'courier_prealert' => (int) $_POST['courier'],
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
cdp_activityLog(['module' => 'prealerts', 'action' => 'create', 'action_label' => 'Raised pre-alert', 'verb' => 'create', 'outcome' => 'ok', 'entity_type' => 'prealert', 'entity_id' => (int) $id, 'entity_label' => $data['tracking_prealert'], 'summary' => 'Raised pre-alert ' . $data['tracking_prealert']]);
@include_once __DIR__ . '/../../helpers/automations.php';
if (function_exists('cdp_automationsFire')) cdp_automationsFire('prealert.created', ['entity_type' => 'prealert', 'entity_id' => (int) $id, 'entity_label' => $data['tracking_prealert'], 'tracking' => $data['tracking_prealert'], 'user_id' => $customer, 'amount' => (float) $data['price_prealert']]);
sl_json(['success' => true, 'message' => 'Pre-alert saved']);
