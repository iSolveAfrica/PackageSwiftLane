<?php
/**
 * Package Intake lookups (the save goes through ajax/sl/package_ajax.php op=create,
 * the shared customer-package insert that reuses helpers/querys.php).
 * GET op=lookup&tracking=   pre-alert match + carrier guess (+ AI item parsing when configured)
 * GET op=customer&q=        customers in scope by locker / name / phone
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../views/sl/data/intake.php';
require_once __DIR__ . '/../../helpers/prealert_receive.php';
require_login();
$user = $user ?? new User();
require_permission(['package_intake', 'add_package']);
$op = (string) ($_GET['op'] ?? '');
$ctx = cdp_getAgencyContext();
$agencyScope = !empty($ctx['is_restricted']) && !empty($ctx['agency_id']) ? ' AND u.agency_id = ' . (int) $ctx['agency_id'] : '';

$customer = function (int $id) use ($agencyScope) {
    $u = sl_row("SELECT u.id, u.fname, u.lname, u.locker, u.phone, u.email FROM cdb_users u WHERE u.id = :id AND u.userlevel = 1 $agencyScope", [':id' => $id]);
    return $u ? ['id' => (int) $u->id, 'name' => trim($u->fname . ' ' . $u->lname), 'locker' => (string) $u->locker, 'phone' => (string) $u->phone, 'addresses' => sl_opsAddresses((int) $u->id)] : null;
};

if ($op === 'customer') {
    $q = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) {
        sl_json(['success' => true, 'customers' => []]);
    }
    $rows = sl_rows("SELECT u.id FROM cdb_users u WHERE u.userlevel = 1 AND u.active = 1 $agencyScope AND (u.locker LIKE :a OR CONCAT(u.fname, ' ', u.lname) LIKE :b OR u.phone LIKE :c OR u.email LIKE :e) ORDER BY (u.locker = :x) DESC, u.fname LIMIT 8", [':a' => "%$q%", ':b' => "%$q%", ':c' => "%$q%", ':e' => "%$q%", ':x' => $q]);
    sl_json(['success' => true, 'customers' => array_values(array_filter(array_map(fn($r) => $customer((int) $r->id), $rows)))]);
}

// as-you-type: open pre-alerts whose tracking number contains what was typed (min 4 characters)
if ($op === 'suggest') {
    $t = strtoupper(preg_replace('/\s+/', '', (string) ($_GET['tracking'] ?? '')));
    if (strlen($t) < 4 || strlen($t) > 60 || !preg_match('/^[A-Z0-9\-]+$/', $t)) sl_json(['success' => true, 'items' => []]);
    $rows = sl_rows("SELECT p.pre_alert_id, p.tracking, p.provider_shop, p.package_description, p.purchase_price, p.estimated_date, p.customer_id, u.fname, u.lname, u.locker
        FROM cdb_pre_alert p JOIN cdb_users u ON u.id = p.customer_id
        WHERE p.is_package = 0 AND REPLACE(UPPER(p.tracking), ' ', '') LIKE :t $agencyScope
        ORDER BY (REPLACE(UPPER(p.tracking), ' ', '') = :x) DESC, p.pre_alert_id DESC LIMIT 6", [':t' => '%' . $t . '%', ':x' => $t]);
    sl_json(['success' => true, 'items' => array_map(fn($r) => ['id' => (int) $r->pre_alert_id, 'tracking' => (string) $r->tracking, 'shop' => (string) $r->provider_shop,
        'what' => mb_strimwidth((string) $r->package_description, 0, 40, '…'), 'price' => sl_money($r->purchase_price), 'eta' => sl_date($r->estimated_date),
        'customer' => trim($r->fname . ' ' . $r->lname), 'locker' => (string) $r->locker, 'customer_id' => (int) $r->customer_id], $rows)]);
}

if ($op === 'lookup') {
    $t = strtoupper(preg_replace('/\s+/', '', (string) ($_GET['tracking'] ?? '')));
    if ($t === '' || strlen($t) > 60 || !preg_match('/^[A-Z0-9\-]+$/', $t)) {
        sl_json(['success' => false, 'message' => 'Enter a carrier tracking number', 'errors' => ['tracking' => 'Letters and digits only']], 422);
    }
    $dup = sl_row("SELECT 'sea' AS m, order_id, order_prefix, order_no FROM cdb_customers_packages WHERE tracking_purchase = :t UNION ALL SELECT 'air', order_id, order_prefix, order_no FROM cdb_add_order WHERE is_deleted = 0 AND tracking_purchase = :t2 LIMIT 1", [':t' => $t, ':t2' => $t]);
    cdp_preAlertEnsureColumns();
    $pa = sl_row('SELECT p.*, c.name_com FROM cdb_pre_alert p LEFT JOIN cdb_courier_com c ON c.id = p.courier_com WHERE p.tracking = :t ORDER BY p.is_package ASC, p.pre_alert_id DESC', [':t' => $t]);
    if (!$pa) {
        // carriers print the same number with or without spaces / leading zeros
        $pa = sl_row('SELECT p.*, c.name_com FROM cdb_pre_alert p LEFT JOIN cdb_courier_com c ON c.id = p.courier_com WHERE REPLACE(UPPER(p.tracking), " ", "") = :t ORDER BY p.is_package ASC, p.pre_alert_id DESC', [':t' => $t]);
    }
    $out = ['success' => true, 'tracking' => $t, 'carrierGuess' => sl_intakeCarrierGuess($t), 'duplicate' => $dup ? ['id' => (int) $dup->order_id, 'track' => $dup->order_prefix . $dup->order_no, 'module' => $dup->m, 'href' => $dup->m === 'sea' ? 'customer_packages_view.php?id=' . (int) $dup->order_id : sl_route('ShipmentDetail', ['id' => (int) $dup->order_id])] : null, 'prealert' => null, 'customer' => null, 'items' => [], 'source' => 'none', 'conf' => ''];
    if ($pa) {
        $cust = $customer((int) $pa->customer_id);
        $out['canLink'] = $dup && (int) $pa->is_package === 0 && $user->cdp_hasPermission('prealert_edit');
        $out['prealert'] = ['id' => (int) $pa->pre_alert_id, 'shop' => (string) $pa->provider_shop, 'courier_id' => (int) $pa->courier_com, 'courier' => (string) $pa->name_com, 'desc' => (string) $pa->package_description, 'price' => (float) $pa->purchase_price, 'eta' => (string) $pa->estimated_date, 'used' => (int) $pa->is_package === 1];
        $out['customer'] = $cust;
        $out['items'] = [['name' => (string) ($pa->package_description ?: 'Package from ' . $pa->provider_shop), 'qty' => 1, 'value' => (float) $pa->purchase_price]];
        $out['source'] = 'prealert';
        $out['conf'] = 'Pre-alert match';
        if (!$cust) {
            $out['note'] = 'The pre-alert belongs to a customer outside your agency.';
        }
    }
    // AI lookup: only when an AI provider is configured; it only splits the
    // pre-alert description into item lines (no customer data leaves the app).
    require_once CDP_ROOT . '/helpers/ai_assistant.php';
    if ($pa && trim((string) $pa->package_description) !== '' && function_exists('cdp_aiConfigured') && cdp_aiConfigured()) {
        require_once CDP_ROOT . '/helpers/ai_assistant_llm.php';
        try {
            $r = cdp_aiComplete('You turn a parcel description into JSON. Reply with only a JSON array of {"name": string, "qty": integer, "value": number}. Split the declared total value across items when several are listed; use 0 when unknown.', 'Description: ' . mb_substr((string) $pa->package_description, 0, 600) . "\nDeclared total: " . (float) $pa->purchase_price, null, null, null, 600);
            if (!empty($r['ok']) && preg_match('/\[.*\]/s', (string) $r['text'], $m)) {
                $items = json_decode($m[0], true);
                if (is_array($items) && $items) {
                    $clean = [];
                    foreach (array_slice($items, 0, 12) as $i) {
                        if (!is_array($i) || trim((string) ($i['name'] ?? '')) === '') continue;
                        $clean[] = ['name' => mb_substr(trim((string) $i['name']), 0, 120), 'qty' => max(1, (int) ($i['qty'] ?? 1)), 'value' => max(0, (float) ($i['value'] ?? 0))];
                    }
                    if ($clean) {
                        $out['items'] = $clean;
                        $out['source'] = 'ai';
                        $out['conf'] = 'Pre-alert match · items read by AI';
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('intake AI: ' . $e->getMessage());
        }
    }
    sl_json($out);
}
sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
