<?php
/**
 * Campaigns: every bulk send on the platform (e-mail newsletters, SMS, WhatsApp, price lists, invoice
 * notices) grouped by batch_id in cdb_message_log, with delivery, opens and clicks (cdb_message_events,
 * helpers/message_tracking.php) and cost from the rates set in Message Costs (helpers/message_cost.php).
 */
require_once CDP_ROOT . '/helpers/message_cost.php';
require_once CDP_ROOT . '/helpers/message_tracking.php';

/** Cost of sent rows: units (SMS parts when enabled) × today's rate per channel. */
function sl_cmpRates(): array
{
    $rates = function_exists('cdp_mcCurrentRates') ? cdp_mcCurrentRates() : [];
    $set = function_exists('cdp_mcSettings') ? cdp_mcSettings() : ['currency' => 'USD', 'sms_parts' => 0];
    return ['rates' => $rates, 'cur' => (string) ($set['currency'] ?? 'USD'), 'parts' => !empty($set['sms_parts'])];
}

function sl_cmpMoney(float $v, string $cur): string
{
    return $cur . ' ' . (function_exists('cdp_mcMoney') ? cdp_mcMoney($v) : number_format($v, 2));
}

/** Names of Send-page campaigns for their batches (campaign-<id>, cdb_comm_campaigns). */
function sl_cmpCampaignNames(array $batches): array
{
    $ids = [];
    foreach ($batches as $b) if (preg_match('/^campaign-(\d+)$/', (string) $b, $m)) $ids[(int) $m[1]] = (string) $b;
    if (!$ids || !sl_tableExists('cdb_comm_campaigns')) return [];
    $out = [];
    foreach (sl_rows('SELECT id, name FROM cdb_comm_campaigns WHERE id IN (' . implode(',', array_keys($ids)) . ')') as $c) $out[$ids[(int) $c->id]] = (string) $c->name;
    return $out;
}

function sl_campaignsData($user): array
{
    $days = in_array((int) ($_GET['days'] ?? 30), [7, 30, 90, 365], true) ? (int) ($_GET['days'] ?? 30) : 30;
    $ch = in_array($_GET['ch'] ?? '', ['email', 'sms', 'whatsapp'], true) ? $_GET['ch'] : '';
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
    $R = sl_cmpRates();
    $units = function_exists('cdp_mcUnitsSql') ? cdp_mcUnitsSql($R['parts']) : '1';
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $d = ['days' => $days, 'ch' => $ch, 'q' => $q, 'cur' => $R['cur'], 'rates' => $R['rates'], 'channels' => [], 'campaigns' => [], 'ready' => sl_tableExists('cdb_message_log'),
        'canCost' => function_exists('cdp_mcCanEdit') && cdp_mcCanEdit($user)];
    if (!$d['ready']) return $d;
    $hasEv = cdp_mtReady();

    // channel totals for the period (every message, bulk or automatic)
    foreach (sl_rows("SELECT l.channel AS ch, SUM(l.status = 'sent') AS sent, SUM(l.status = 'failed') AS failed, SUM(l.status = 'skipped') AS skipped,
            SUM(CASE WHEN l.status = 'sent' THEN $units ELSE 0 END) AS u, COUNT(DISTINCT l.batch_id) AS batches
        FROM cdb_message_log l WHERE l.created_at >= :f AND l.channel IN ('email','sms','whatsapp') GROUP BY l.channel", [':f' => $from]) as $r) {
        $rate = (float) ($R['rates'][$r->ch] ?? 0);
        $tot = (int) $r->sent + (int) $r->failed;
        $d['channels'][$r->ch] = ['sent' => (int) $r->sent, 'failed' => (int) $r->failed, 'skipped' => (int) $r->skipped, 'ok' => $tot ? (int) round($r->sent / $tot * 100) : 0,
            'cost' => (float) $r->u * $rate, 'costL' => sl_cmpMoney((float) $r->u * $rate, $R['cur']), 'rate' => $rate];
    }
    if ($hasEv) {
        $ev = sl_row("SELECT COUNT(DISTINCT CASE WHEN event = 'open' THEN CONCAT(batch_id, recipient_to) END) AS o, COUNT(DISTINCT CASE WHEN event = 'click' THEN CONCAT(batch_id, recipient_to) END) AS c FROM cdb_message_events WHERE created_at >= :f", [':f' => $from]);
        if (isset($d['channels']['email'])) { $d['channels']['email']['opens'] = (int) ($ev->o ?? 0); $d['channels']['email']['clicks'] = (int) ($ev->c ?? 0); }
    }
    $d['totalCost'] = sl_cmpMoney(array_sum(array_column($d['channels'], 'cost')), $R['cur']);

    // campaigns = batches
    $w = "l.batch_id IS NOT NULL AND l.batch_id <> '' AND l.batch_id NOT LIKE 'nltest%' AND l.created_at >= :f" . ($ch ? ' AND l.channel = :ch' : '');
    $b = [':f' => $from] + ($ch ? [':ch' => $ch] : []);
    if ($q !== '') { $w .= ' AND (l.subject LIKE :q OR l.source_label LIKE :q2 OR l.entity_label LIKE :q3 OR l.batch_id = :q4)'; $b += [':q' => "%$q%", ':q2' => "%$q%", ':q3' => "%$q%", ':q4' => $q]; }
    $rows = sl_rows("SELECT l.batch_id, MIN(l.created_at) AS started, MAX(l.created_at) AS ended, GROUP_CONCAT(DISTINCT l.channel) AS chs, MAX(l.source_label) AS src, MAX(l.subject) AS subj,
            MAX(l.entity_label) AS aud, MAX(l.sent_by_name) AS who, COUNT(*) AS n, SUM(l.status = 'sent') AS sent, SUM(l.status = 'failed') AS failed, SUM(l.status = 'skipped') AS skipped,
            SUM(CASE WHEN l.status = 'sent' AND l.channel = 'sms' THEN $units ELSE 0 END) AS us, SUM(CASE WHEN l.status = 'sent' AND l.channel = 'whatsapp' THEN 1 ELSE 0 END) AS uw,
            SUM(CASE WHEN l.status = 'sent' AND l.channel = 'email' THEN 1 ELSE 0 END) AS ue
        FROM cdb_message_log l WHERE $w GROUP BY l.batch_id ORDER BY started DESC LIMIT 60", $b);
    $evs = [];
    if ($hasEv && $rows) {
        $ids = array_map(fn($r) => $r->batch_id, $rows);
        $ph = implode(',', array_map(fn($i) => ':b' . $i, array_keys($ids)));
        foreach (sl_rows("SELECT batch_id, COUNT(DISTINCT CASE WHEN event = 'open' THEN recipient_to END) AS o, COUNT(DISTINCT CASE WHEN event = 'click' THEN recipient_to END) AS c, SUM(event = 'click') AS cl FROM cdb_message_events WHERE batch_id IN ($ph) GROUP BY batch_id", array_combine(array_map(fn($i) => ':b' . $i, array_keys($ids)), $ids)) as $e) $evs[$e->batch_id] = $e;
    }
    $names = sl_cmpCampaignNames(array_map(fn($r) => (string) $r->batch_id, $rows));
    foreach ($rows as $r) {
        if (isset($names[$r->batch_id])) $r->subj = $names[$r->batch_id];
        $cost = (float) $r->us * (float) ($R['rates']['sms'] ?? 0) + (float) $r->uw * (float) ($R['rates']['whatsapp'] ?? 0) + (float) $r->ue * (float) ($R['rates']['email'] ?? 0);
        $e = $evs[$r->batch_id] ?? null;
        $sent = (int) $r->sent;
        $d['campaigns'][] = ['id' => $r->batch_id, 'chs' => explode(',', (string) $r->chs), 'title' => (string) ($r->subj ?: $r->src ?: 'Campaign'), 'src' => (string) $r->src, 'aud' => (string) $r->aud,
            'who' => (string) ($r->who ?: 'System'), 'started' => sl_date($r->started, 'M j, H:i'), 'ago' => sl_ago($r->started), 'n' => (int) $r->n, 'sent' => $sent, 'failed' => (int) $r->failed, 'skipped' => (int) $r->skipped,
            'opens' => $e ? (int) $e->o : null, 'clicks' => $e ? (int) $e->c : null, 'openP' => $e && $sent ? (int) round($e->o / $sent * 100) : null, 'clickP' => $e && $sent ? (int) round($e->c / $sent * 100) : null,
            'deliv' => ($sent + (int) $r->failed) ? (int) round($sent / ($sent + (int) $r->failed) * 100) : 0, 'cost' => sl_cmpMoney($cost, $R['cur']), 'running' => strtotime((string) $r->ended) > time() - 120];
    }
    // one campaign opened
    $bid = preg_match('/^[A-Za-z0-9_-]{1,40}$/', (string) ($_GET['batch'] ?? '')) ? (string) $_GET['batch'] : '';
    if ($bid !== '') $d['detail'] = sl_campaignDetail($bid, $R, $units, $hasEv);
    return $d;
}

function sl_campaignDetail(string $bid, array $R, string $units, bool $hasEv): array
{
    $sum = null;
    $rec = sl_rows("SELECT l.id, l.channel, l.recipient_name, l.recipient_to, l.recipient_user_id, l.status, l.status_detail, l.created_at FROM cdb_message_log l WHERE l.batch_id = :b ORDER BY l.id LIMIT 500", [':b' => $bid]);
    $ev = [];
    $links = [];
    $timeline = [];
    if ($hasEv) {
        foreach (sl_rows("SELECT recipient_to, MIN(CASE WHEN event = 'open' THEN created_at END) AS o, MIN(CASE WHEN event = 'click' THEN created_at END) AS c, SUM(event = 'click') AS n FROM cdb_message_events WHERE batch_id = :b GROUP BY recipient_to", [':b' => $bid]) as $e) $ev[strtolower($e->recipient_to)] = $e;
        $links = sl_rows("SELECT url, COUNT(*) AS n, COUNT(DISTINCT recipient_to) AS u FROM cdb_message_events WHERE batch_id = :b AND event = 'click' GROUP BY url ORDER BY n DESC LIMIT 10", [':b' => $bid]);
        foreach (sl_rows("SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00') AS h, SUM(event = 'open') AS o, SUM(event = 'click') AS c FROM cdb_message_events WHERE batch_id = :b GROUP BY h ORDER BY h LIMIT 72", [':b' => $bid]) as $t) $timeline[] = ['h' => date('M j H:00', strtotime($t->h)), 'o' => (int) $t->o, 'c' => (int) $t->c];
    }
    $out = [];
    $fails = [];
    foreach ($rec as $r) {
        $e = $ev[strtolower((string) $r->recipient_to)] ?? null;
        $out[] = ['name' => (string) ($r->recipient_name ?: '—'), 'to' => (string) $r->recipient_to, 'uid' => (int) $r->recipient_user_id, 'ch' => $r->channel, 'st' => $r->status, 'why' => (string) $r->status_detail,
            'opened' => $e && $e->o ? sl_date($e->o, 'M j H:i') : '', 'clicked' => $e && $e->c ? sl_date($e->c, 'M j H:i') . ((int) $e->n > 1 ? ' ×' . (int) $e->n : '') : ''];
        if ($r->status === 'failed' && $r->status_detail) $fails[$r->status_detail] = ($fails[$r->status_detail] ?? 0) + 1;
    }
    arsort($fails);
    $head = sl_row('SELECT MAX(subject) AS subj, MAX(source_label) AS src, MAX(entity_label) AS aud, MAX(sent_by_name) AS who, MIN(created_at) AS s, MAX(created_at) AS e, COUNT(*) AS n FROM cdb_message_log WHERE batch_id = :b', [':b' => $bid]);
    $agg = sl_row("SELECT SUM(status = 'sent') AS sent, SUM(status = 'failed') AS failed FROM cdb_message_log WHERE batch_id = :b", [':b' => $bid]);
    $evAgg = $hasEv ? sl_row("SELECT COUNT(DISTINCT CASE WHEN event = 'open' THEN recipient_to END) AS o, COUNT(DISTINCT CASE WHEN event = 'click' THEN recipient_to END) AS c FROM cdb_message_events WHERE batch_id = :b", [':b' => $bid]) : null;
    $named = sl_cmpCampaignNames([$bid]);
    if (isset($named[$bid]) && $head) $head->subj = $named[$bid];
    return ['agg' => ['sent' => (int) ($agg->sent ?? 0), 'failed' => (int) ($agg->failed ?? 0), 'opened' => (int) ($evAgg->o ?? 0), 'clicked' => (int) ($evAgg->c ?? 0)],
        'id' => $bid, 'title' => (string) ($head->subj ?: $head->src ?: 'Campaign'), 'src' => (string) $head->src, 'aud' => (string) $head->aud, 'who' => (string) $head->who,
        'when' => $head->s ? sl_date($head->s, 'M j, Y H:i') . ' – ' . sl_date($head->e, 'H:i') : '', 'n' => (int) $head->n, 'rows' => $out, 'links' => $links, 'fails' => array_slice($fails, 0, 6, true), 'timeline' => $timeline,
        'body' => (string) sl_scalar('SELECT body FROM cdb_message_log WHERE batch_id = :b ORDER BY id LIMIT 1', [':b' => $bid], '')];
}
