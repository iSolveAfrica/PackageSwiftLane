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
require_login();
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

if ($op === 'lookup') {
    $t = strtoupper(preg_replace('/\s+/', '', (string) ($_GET['tracking'] ?? '')));
    if ($t === '' || strlen($t) > 60 || !preg_match('/^[A-Z0-9\-]+$/', $t)) {
        sl_json(['success' => false, 'message' => 'Enter a carrier tracking number', 'errors' => ['tracking' => 'Letters and digits only']], 422);
    }
    $dup = sl_row('SELECT order_id, order_prefix, order_no FROM cdb_customers_packages WHERE tracking_purchase = :t ORDER BY order_id DESC', [':t' => $t]);
    $pa = sl_row('SELECT p.*, c.name_com FROM cdb_pre_alert p LEFT JOIN cdb_courier_com c ON c.id = p.courier_com WHERE p.tracking = :t ORDER BY p.is_package ASC, p.pre_alert_id DESC', [':t' => $t]);
    if (!$pa) {
        // carriers print the same number with or without spaces / leading zeros
        $pa = sl_row('SELECT p.*, c.name_com FROM cdb_pre_alert p LEFT JOIN cdb_courier_com c ON c.id = p.courier_com WHERE REPLACE(UPPER(p.tracking), " ", "") = :t ORDER BY p.is_package ASC, p.pre_alert_id DESC', [':t' => $t]);
    }
    $out = ['success' => true, 'tracking' => $t, 'carrierGuess' => sl_intakeCarrierGuess($t), 'duplicate' => $dup ? ['id' => (int) $dup->order_id, 'track' => $dup->order_prefix . $dup->order_no] : null, 'prealert' => null, 'customer' => null, 'items' => [], 'source' => 'none', 'conf' => ''];
    if ($pa) {
        $cust = $customer((int) $pa->customer_id);
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
