<?php
/**
 * Record a manual payment (cash, transfer, mobile money) against one or more
 * shipments / packages / consolidations. Reuses the legacy insert logic:
 * cdp_insertPaymentGateway() (cdb_payments_gateway) + cdp_confirmPayment*()
 * (status_invoice) and a cdb_charges_order row per shipment, exactly like the
 * legacy courier_confirm_payment / add_payment_*_ajax pages did.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/finance_switch.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
cdp_financeGuardAjax();
require_permission(['fs_record_payment', 'manage_account_payments', 'verify_payments', 'verify_consolidate_payment', 'verify_consolidate_package_payment']);

$core = new Core();
$type = (string) ($_POST['type'] ?? 'shipment');
$tables = ['shipment' => ['cdb_add_order', 'order_id', 'order_prefix', 'order_no', 'Shipping', 'cdp_confirmPaymentCourier'], 'package' => ['cdb_customers_packages', 'order_id', 'order_prefix', 'order_no', 'Package', 'cdp_confirmPaymentPackages'], 'consolidation' => ['cdb_consolidate', 'consolidate_id', 'c_prefix', 'c_no', 'Consolidation', 'cdp_confirmPaymentConsolidate'], 'consolidation_packages' => ['cdb_consolidate_packages', 'consolidate_id', 'c_prefix', 'c_no', 'Consolidation packages', 'cdp_confirmPaymentConsolidatePackages']];
if (!isset($tables[$type])) sl_json(['success' => false, 'message' => 'Unknown type'], 422);
[$tbl, $pk, $pre, $no, $label, $confirmFn] = $tables[$type];
$customerId = (int) ($_POST['customer_id'] ?? 0);
$orders = array_values(array_unique(array_map('intval', (array) ($_POST['orders'] ?? []))));
$amount = (float) str_replace(',', '', (string) ($_POST['amount'] ?? 0));
$currency = strtoupper(trim((string) ($_POST['currency'] ?? ($core->currency ?? 'USD'))));
$method = (int) ($_POST['method'] ?? 0);
$reference = mb_substr(trim((string) ($_POST['reference'] ?? '')), 0, 190);
$paidAt = (string) ($_POST['paid_at'] ?? date('Y-m-d'));
$note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 190);
$errors = [];
if (!$orders) $errors['orders'] = 'Tick at least one invoice';
if ($amount <= 0) $errors['amount'] = 'Enter the amount paid';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidAt) || !strtotime($paidAt)) $errors['paid_at'] = 'Pick a date';
if ($errors) sl_json(['success' => false, 'message' => 'Check the form', 'errors' => $errors], 422);

$rows = sl_rows("SELECT $pk AS id, CONCAT($pre, $no) AS track, total_order, sender_id, status_invoice FROM $tbl WHERE $pk IN (" . implode(',', $orders) . ")");
if (!$rows) sl_json(['success' => false, 'message' => 'Invoices not found'], 404);
foreach ($rows as $r) {
    if ($customerId && (int) $r->sender_id !== $customerId) sl_json(['success' => false, 'message' => 'Invoice ' . $r->track . ' belongs to another customer'], 422);
    if ((int) $r->status_invoice !== 23) sl_json(['success' => false, 'message' => 'Invoice ' . $r->track . ' is not awaiting payment'], 409);
}
$customerId = $customerId ?: (int) $rows[0]->sender_id;
$methodLabel = (string) sl_scalar('SELECT label FROM cdb_payment_methods WHERE id = :id', [':id' => $method], 'Cash');
$amountUsd = $amount;
if ($currency === 'GHS' && ($core->currency ?? 'USD') !== 'GHS' && function_exists('cdp_ghsToUsd')) {
    $amountUsd = cdp_ghsToUsd($amount, (float) $core->exchange_rate);
}
$total = array_sum(array_map(fn($r) => (float) $r->total_order, $rows));
$ref = $reference !== '' ? $reference : 'MAN-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
$when = $paidAt . ' ' . date('H:i:s');
$db = new Conexion();
$db->dbh->beginTransaction();
try {
    // One ledger per payment, so totals never double count: air shipments use
    // cdb_charges_order (the legacy manual-charge ledger, per order); packages and
    // consolidations have no charge table and use cdb_payments_gateway.
    if ($tbl !== 'cdb_add_order') {
        $ok = cdp_insertPaymentGateway([
            'amount' => round($amount, 2), 'currency_code' => $currency, 'status' => 'success', 'gateway' => 'Manual: ' . $methodLabel, 'payment_id' => $ref,
            'type_transaccition_courier' => $label, 'date' => $when, 'order_track' => implode(', ', array_map(fn($r) => $r->track, $rows)), 'order_track_customer_id' => $customerId,
        ]);
        if (!$ok) throw new RuntimeException('Could not save the payment');
    }
    $full = $amountUsd + 0.005 >= $total; // paid in full: mark invoices paid (19), keep 23 otherwise
    foreach ($rows as $r) {
        if ($full) {
            $confirmFn(['order_id' => (int) $r->id, 'status_invoice' => 19]);
            $db->cdp_query("UPDATE $tbl SET payment_date = :d WHERE $pk = :id");
            $db->bind(':d', $when); $db->bind(':id', (int) $r->id); $db->cdp_execute();
        }
        if ($tbl === 'cdb_add_order') {
            $share = $total > 0 ? round($amountUsd * (float) $r->total_order / $total, 2) : $amountUsd;
            $db->cdp_query('INSERT INTO cdb_charges_order (order_id, charge_date, total, number_reference, payment_type, note, user_id) VALUES (:o, :d, :t, :r, :p, :n, :u)');
            $db->bind(':o', (int) $r->id); $db->bind(':d', $paidAt); $db->bind(':t', $share); $db->bind(':r', $ref); $db->bind(':p', $method); $db->bind(':n', $note); $db->bind(':u', (int) $_SESSION['userid']);
            $db->cdp_execute();
            if (sl_columnExists('cdb_add_order', 'fs_cleared_for_delivery') && $full) {
                $db->cdp_query('UPDATE cdb_add_order SET fs_cleared_for_delivery = 1, fs_cleared_at = NOW(), fs_cleared_by = :u WHERE order_id = :id');
                $db->bind(':u', (int) $_SESSION['userid']); $db->bind(':id', (int) $r->id); $db->cdp_execute();
            }
        }
    }
    if (!empty($_POST['release']) && (int) sl_scalar('SELECT COUNT(*) FROM cdb_styles WHERE id = 29') > 0) {
        $db->cdp_query('UPDATE cdb_add_order SET status_courier = 6 WHERE sender_id = :u AND is_deleted = 0 AND status_courier = 29');
        $db->bind(':u', $customerId); $db->cdp_execute();
    }
    $db->dbh->commit();
} catch (Throwable $e) {
    $db->dbh->rollBack();
    sl_json(['success' => false, 'message' => $e->getMessage()], 500);
}
$tracks = implode(', ', array_map(fn($r) => $r->track, $rows));
if (!empty($_POST['notify'])) {
    try {
        require_once __DIR__ . '/../../helpers/push_notify.php';
        $cust = sl_row('SELECT * FROM cdb_users WHERE id = :id', [':id' => $customerId]);
        if ($cust) cdp_pushNotifyUser($cust, 'Payment received', 'We received your payment of ' . sl_money($amountUsd) . ' (' . $methodLabel . ', ref ' . $ref . ') for ' . $tracks . '. Thank you.', cdp_getSettingsCourier(), ['source' => 'record_payment', 'entity_type' => 'payment', 'entity_label' => $ref], 'both');
    } catch (Throwable $e) {
        // receipt is best effort; the payment is already saved
    }
}
if (function_exists('cdp_activityLog')) {
    cdp_activityLog(['module' => 'finance', 'action' => 'record_payment', 'action_label' => 'Recorded payment', 'verb' => 'create', 'outcome' => 'ok', 'summary' => 'recorded ' . sl_money($amountUsd) . ' (' . $methodLabel . ') for ' . $tracks, 'entity_type' => 'payment', 'entity_id' => 0, 'entity_label' => $ref]);
}
sl_json(['success' => true, 'message' => 'Payment ' . $ref . ' saved' . ($full ? ' · invoices marked paid' : ' · partial, invoices stay open'), 'redirect' => sl_route('Payments', ['q' => $ref])]);
