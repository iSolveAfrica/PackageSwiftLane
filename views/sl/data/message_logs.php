<?php
/** Data for Message Logs (MessageLogs.dc.html) via helpers/message_log_query.php. */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/message_log_query.php';

function sl_messageLogsData($user, $core): array
{
    $d = ['ready' => cdp_msgTableReady()];
    $f = cdp_mlFilters();
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q !== '') {
        $f['search'] = $q;
    }
    $filter = (string) ($_GET['filter'] ?? 'all');
    if (in_array($filter, ['sms', 'whatsapp', 'email'], true)) {
        $f['channel'] = $filter;
    } elseif ($filter === 'failed') {
        $f['status'] = 'failed';
    }
    $d['q'] = $q;
    $d['filter'] = $filter;
    $d['filters'] = [['f' => 'all', 'label' => 'All'], ['f' => 'sms', 'label' => 'SMS'], ['f' => 'whatsapp', 'label' => 'WhatsApp'], ['f' => 'email', 'label' => 'Email'], ['f' => 'failed', 'label' => 'Failed']];
    $d['rows'] = [];
    $d['count'] = 0;
    $d['page'] = max(1, (int) ($_GET['page'] ?? 1));
    $d['per'] = 25;
    $d['month'] = 0; $d['sent'] = 0; $d['failed'] = 0; $d['rate'] = 0;
    if (!$d['ready']) {
        return $d;
    }
    $m = sl_row("SELECT COUNT(*) AS n, SUM(status='sent') AS s, SUM(status='failed') AS f FROM cdb_message_log WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $d['month'] = (int) ($m->n ?? 0);
    $d['sent'] = (int) ($m->s ?? 0);
    $d['failed'] = (int) ($m->f ?? 0);
    $d['rate'] = $d['month'] ? round($d['sent'] / $d['month'] * 100, 1) : 0;
    $d['failPct'] = $d['month'] ? round($d['failed'] / $d['month'] * 100, 1) : 0;
    list($where, $binds) = cdp_mlWhere($f);
    $d['count'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_message_log l WHERE $where", $binds);
    $rows = sl_rows("SELECT l.id, l.created_at, l.channel, l.source, l.source_label, l.template_id, l.subject, l.recipient_name, l.recipient_to, l.entity_type, l.entity_id, l.entity_label, l.status, l.status_detail, l.batch_id, l.sent_by_name
        FROM cdb_message_log l WHERE $where ORDER BY l.id DESC LIMIT " . (($d['page'] - 1) * $d['per']) . ", " . $d['per'], $binds);
    $cls = ['sent' => 'delivered', 'failed' => 'overdue', 'skipped' => 'skipped', 'queued' => 'sorting'];
    foreach ($rows as $r) {
        $link = '';
        // Communication Center rows name the tracking number but carry no id (entity_id ''): no link to id 0.
        if ((int) $r->entity_id <= 0) {
            $link = '';
        } elseif ($r->entity_type === 'shipment' || $r->entity_type === 'order') {
            $link = sl_route('ShipmentDetail', ['id' => (int) $r->entity_id]);
        } elseif ($r->entity_type === 'package') {
            $link = sl_route('PackageDetail', ['id' => (int) $r->entity_id]);
        } elseif ($r->entity_type === 'consolidation') {
            $link = sl_route('ConsolidationDetail', ['id' => (int) $r->entity_id]);
        }
        $d['rows'][] = ['t' => sl_date($r->created_at, 'M j, H:i'), 'ini' => sl_initials((string) ($r->recipient_name ?: $r->recipient_to)), 'name' => $r->recipient_name ?: '—', 'to' => $r->recipient_to ?: '', 'ch' => cdp_mlChannelLabel($r->channel),
            'tpl' => ($r->source_label ?: cdp_msgSourceLabel((string) $r->source)) . ($r->template_id ? ' · #' . (int) $r->template_id : ''), 'trk' => $r->entity_label ?: '—', 'link' => $link,
            'st' => cdp_mlStatusLabel($r->status), 'cls' => $cls[$r->status] ?? 'plain', 'detail' => (string) $r->status_detail, 'short' => sl_msgShortDetail((string) $r->status_detail), 'by' => (string) ($r->sent_by_name ?: 'System'), 'batch' => (string) $r->batch_id];
    }
    return $d;
}

/**
 * One short line for the status cell: provider message ids and long codes move to the tooltip.
 * "Accepted by UltraMsg (id 80461)" -> "UltraMsg"; "Accepted by SMTP server" -> "SMTP";
 * "SMS is switched off (Tools > SMS)." -> "SMS is switched off".
 */
function sl_msgShortDetail(string $d): string
{
    $d = trim($d);
    if ($d === '') return '';
    if (preg_match('/^Accepted by (.+?)(\s*\(.*\))?\.?$/i', $d, $m)) return 'via ' . preg_replace('/\s+server$/i', '', trim($m[1]));
    $d = preg_replace('/\s*\([^)]*\)\s*/', ' ', $d);
    $d = trim(rtrim(trim(preg_replace('/\s+/', ' ', $d)), '.'));
    return mb_strlen($d) > 42 ? mb_substr($d, 0, 40) . '…' : $d;
}
