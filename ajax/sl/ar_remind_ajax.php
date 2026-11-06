<?php
/** Payment reminders from Accounts Receivable, through the push notify helper (logged to cdb_message_log). */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/finance_switch.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
cdp_financeGuardAjax();
require_permission('view_receivable_accounts');
require_once __DIR__ . '/../../helpers/push_notify.php';
$op = (string) ($_POST['op'] ?? '');
$byUser = [];
if ($op === 'one') {
    $kinds = ['shipment' => ['cdb_add_order', 'order_id', 'order_prefix', 'order_no'], 'package' => ['cdb_customers_packages', 'order_id', 'order_prefix', 'order_no'], 'consolidation' => ['cdb_consolidate', 'consolidate_id', 'c_prefix', 'c_no']];
    $k = $kinds[(string) ($_POST['kind'] ?? '')] ?? null;
    if (!$k) sl_json(['success' => false, 'message' => 'Unknown invoice type'], 422);
    $r = sl_row("SELECT sender_id, CONCAT({$k[2]}, {$k[3]}) AS inv, total_order FROM {$k[0]} WHERE {$k[1]} = :id AND status_invoice = 23", [':id' => (int) ($_POST['id'] ?? 0)]);
    if (!$r) sl_json(['success' => false, 'message' => 'Invoice not found or already paid'], 404);
    $byUser[(int) $r->sender_id][] = $r;
} elseif ($op === 'overdue') {
    foreach (sl_rows("SELECT sender_id, CONCAT(order_prefix, order_no) AS inv, total_order FROM cdb_add_order WHERE is_deleted = 0 AND status_invoice = 23 AND due_date < CURDATE() LIMIT 500") as $r) $byUser[(int) $r->sender_id][] = $r;
} else {
    sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
$settings = cdp_getSettingsCourier();
$sent = 0;
foreach ($byUser as $uid => $invs) {
    $u = sl_row('SELECT * FROM cdb_users WHERE id = :id', [':id' => $uid]);
    if (!$u) continue;
    $total = array_sum(array_map(fn($i) => (float) $i->total_order, $invs));
    $msg = 'You have ' . count($invs) . ' unpaid invoice' . (count($invs) > 1 ? 's' : '') . ' (' . implode(', ', array_map(fn($i) => $i->inv, array_slice($invs, 0, 5))) . ') totalling ' . sl_money($total) . '. Please pay at the counter or online.';
    $res = cdp_pushNotifyUser($u, 'Payment reminder', $msg, $settings, ['source' => 'ar_reminder', 'entity_type' => 'invoice', 'entity_label' => $invs[0]->inv], 'both');
    if (($res['whatsapp']['status'] ?? '') === 'sent' || ($res['email']['status'] ?? '') === 'sent') $sent++;
}
cdp_activityLog(['module' => 'finance', 'action' => 'ar_reminder', 'action_label' => 'Sent payment reminders', 'verb' => 'other', 'outcome' => 'ok', 'summary' => 'payment reminders to ' . count($byUser) . ' customers (' . $sent . ' delivered)', 'entity_type' => 'invoice']);
sl_json(['success' => true, 'message' => 'Reminders sent to ' . count($byUser) . ' customer' . (count($byUser) === 1 ? '' : 's') . ($sent < count($byUser) ? ' · ' . (count($byUser) - $sent) . ' could not be delivered (see Message Logs)' : '')]);
