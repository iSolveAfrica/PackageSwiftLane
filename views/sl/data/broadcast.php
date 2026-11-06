<?php
/** Data for Broadcast (push notifications with previews). Sending: ajax/tools/push_notifications_ajax.php (existing, chunked). */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/push_compose.php';
require_once __DIR__ . '/msg_common.php';

function sl_broadcastData($user, $core): array
{
    $type = (string) ($_GET['type'] ?? 'broadcast');
    if (!in_array($type, ['broadcast', 'single_user', 'consolidation', 'invoice'], true)) $type = 'broadcast';
    $cid = (int) ($_GET['consolidation_id'] ?? $_GET['id'] ?? 0);
    $cons = sl_rows("SELECT consolidate_id, c_prefix, c_no, created FROM cdb_consolidate ORDER BY consolidate_id DESC LIMIT 40");
    $all = (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE active = 1 AND userlevel = 1');
    $phone = (int) sl_scalar("SELECT COUNT(*) FROM cdb_users WHERE active = 1 AND userlevel = 1 AND TRIM(COALESCE(phone,'')) <> ''");
    $mail = (int) sl_scalar("SELECT COUNT(*) FROM cdb_users WHERE active = 1 AND userlevel = 1 AND email LIKE '%@%'");
    $uid = (int) ($_GET['user_id'] ?? 0);
    $one = $uid ? sl_row('SELECT id, fname, lname FROM cdb_users WHERE id = :id AND active = 1', [':id' => $uid]) : null;
    $last = sl_tableExists('cdb_message_log') ? sl_rows("SELECT batch_id, MIN(created_at) at, MAX(subject) subject, COUNT(*) n, SUM(status='sent') ok FROM cdb_message_log WHERE source LIKE 'push_notification%' GROUP BY batch_id ORDER BY at DESC LIMIT 5") : [];
    return [
        'type' => $type, 'cid' => $cid, 'consolidations' => array_map(fn($c) => ['id' => (int) $c->consolidate_id, 'label' => $c->c_prefix . $c->c_no . ' · ' . sl_date($c->created)], $cons),
        'types' => [['k' => 'broadcast', 'label' => 'All customers', 'hint' => 'Every active customer account (' . sl_num($all) . ').'], ['k' => 'single_user', 'label' => 'One customer', 'hint' => 'One customer by name, phone or locker.'], ['k' => 'consolidation', 'label' => 'Consolidation', 'hint' => 'The owners of the packages inside one consolidation, all of them or the ones you tick.'], ['k' => 'invoice', 'label' => 'Invoice push', 'hint' => 'Send each customer their invoice amount for chosen packages, with the pickup date (WhatsApp template 14 and e-mail).']],
        'counts' => ['all' => $all, 'phone' => $phone, 'mail' => $mail], 'one' => $one ? ['id' => (int) $one->id, 'name' => trim($one->fname . ' ' . $one->lname)] : null,
        'rates' => cdp_pushCostRates(), 'health' => sl_msgChannelHealth(), 'sample' => sl_msgSample(), 'history' => array_map(fn($r) => ['b' => $r->batch_id, 's' => $r->subject ?: '(no subject)', 'at' => sl_ago($r->at), 'n' => (int) $r->n, 'ok' => (int) $r->ok], $last),
    ];
}
