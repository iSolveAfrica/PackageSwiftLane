<?php
/** Data for Send prices (SendPrices.dc.html): pick customers/segments and send the current price sheet. */
require_once CDP_ROOT . '/helpers/sl_view.php';
/** The price sheet as plain text, used as the message body. Built from the
 *  price per weight unit and taxes in Settings (the tariff table was removed). */
function sl_tariffSheetText(): string
{
    $s = sl_rows('SELECT * FROM cdb_settings LIMIT 1');
    $s = $s ? $s[0] : null;
    if (!$s) return 'Prices are not set up yet.';
    $cur = (string) ($s->currency ?? '');
    $unit = (string) ($s->weight_p ?? 'lb');
    $lines = ['Price per ' . $unit . ': ' . $cur . ' ' . number_format((float) ($s->value_weight ?? 0), 2)];
    if ((float) ($s->c_tariffs ?? 0) > 0) $lines[] = 'Customs: ' . (float) $s->c_tariffs . '%';
    if ((float) ($s->tax ?? 0) > 0) $lines[] = 'Tax: ' . (float) $s->tax . '%';
    if ((float) ($s->insurance ?? 0) > 0) $lines[] = 'Insurance: ' . (float) $s->insurance . '% of declared value';
    return implode("\n", $lines);
}

function sl_sendPricesSegments(): array
{
    return [
        'active' => ['Shipped in the last 90 days', "u.id IN (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= CURDATE() - INTERVAL 90 DAY UNION SELECT sender_id FROM cdb_customers_packages WHERE order_date >= CURDATE() - INTERVAL 90 DAY)"],
        'warehouse' => ['Parcels waiting in the warehouse', "u.id IN (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND status_courier IN (4,6,32) UNION SELECT sender_id FROM cdb_customers_packages WHERE status_courier IN (4,6,32))"],
        'quiet' => ['Quiet for 90 days', "u.id NOT IN (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= CURDATE() - INTERVAL 90 DAY)"],
        'new' => ['Joined this month', "u.created >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"],
        'all' => ['All active customers', '1=1'],
    ];
}

function sl_sendPricesData($user, $core): array
{
    $d = [];
    $segs = sl_sendPricesSegments();
    $seg = (string) ($_GET['segment'] ?? 'active');
    if (!isset($segs[$seg])) $seg = 'active';
    $q = trim((string) ($_GET['q'] ?? ''));
    $where = "u.userlevel = 1 AND u.active = 1 AND " . $segs[$seg][1];
    $b = [];
    if ($q !== '') { $where .= " AND (CONCAT(u.fname,' ',u.lname) LIKE :q OR u.locker LIKE :q OR u.phone LIKE :q OR u.email LIKE :q)"; $b[':q'] = "%$q%"; }
    $d['quick'] = array_map(fn($k, $v) => ['f' => $k, 'label' => $v[0], 'cls' => $k === $seg ? 'on' : '', 'n' => (int) sl_scalar("SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel = 1 AND u.active = 1 AND {$v[1]}")], array_keys($segs), $segs);
    $rows = sl_rows("SELECT u.id, u.fname, u.lname, u.locker, u.phone, u.email, (SELECT COUNT(*) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.is_deleted = 0) AS n FROM cdb_users u WHERE $where ORDER BY u.fname, u.lname LIMIT 300", $b);
    $d['rows'] = array_map(fn($r) => ['id' => (int) $r->id, 'name' => trim($r->fname . ' ' . $r->lname), 'locker' => $r->locker ?: '', 'phone' => $r->phone ?: '', 'email' => $r->email ?: '', 'n' => (int) $r->n], $rows);
    $d['seg'] = $seg; $d['segLabel'] = $segs[$seg][0]; $d['q'] = $q;
    $d['sheet'] = sl_tariffSheetText();
    $d['subject'] = 'Our current shipping prices';
    $d['template'] = "Hello [NAME],\n\nHere are our current shipping prices:\n[PRICES]\n\nReply to this message to book a pickup.";
    $rates = sl_tableExists('cdb_message_cost_rates') ? sl_rows('SELECT channel, unit_cost FROM cdb_message_cost_rates r WHERE effective_from = (SELECT MAX(effective_from) FROM cdb_message_cost_rates r2 WHERE r2.channel = r.channel AND r2.effective_from <= CURDATE())') : [];
    $cost = []; foreach ($rates as $r) $cost[$r->channel] = (float) $r->unit_cost;
    $d['chans'] = [
        ['k' => 'whatsapp', 'label' => 'WhatsApp', 'icon' => 'i-message', 'cost' => isset($cost['whatsapp']) ? '₵' . number_format($cost['whatsapp'], 3) . ' per message' : 'Uses the push notification template', 'on' => true],
        ['k' => 'email', 'label' => 'E-mail', 'icon' => 'i-mail', 'cost' => isset($cost['email']) ? '₵' . number_format($cost['email'], 3) . ' per message' : 'Free', 'on' => true],
        ['k' => 'sms', 'label' => 'SMS', 'icon' => 'i-phone', 'cost' => isset($cost['sms']) ? '₵' . number_format($cost['sms'], 3) . ' per message' : 'Through the SMS provider', 'on' => false],
    ];
    $d['recent'] = sl_tableExists('cdb_message_log') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_message_log WHERE source = 'send_prices' AND created_at >= CURDATE() - INTERVAL 7 DAY") : 0;
    return $d;
}
