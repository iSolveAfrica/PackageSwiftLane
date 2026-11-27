<?php
/**
 * Small extra reads for the phone screens of messages and automation (views/sl/phone/: SupportHub, CommsInbox,
 * CommsCenter, Campaigns, EmailBuilder). Read only; every write goes through the endpoints the web pages use.
 */
require_once CDP_ROOT . '/helpers/comms.php';

/** WhatsApp conversations waiting for a person (same figure as the inbox badge). */
function sl_pmWaWaiting(): int
{
    return function_exists('cdp_commWaitingCount') ? (int) cdp_commWaitingCount() : 0;
}

/** Inbox list for the phone: the same search and filters as ajax/comms/comms_ajax.php?action=threads. */
function sl_pmThreads(string $filter, string $q): array
{
    $filter = in_array($filter, ['all', 'open', 'mine', 'bot', 'closed'], true) ? $filter : 'open';
    $q = mb_substr(trim($q), 0, 80);
    return [
        'filter' => $filter,
        'q' => $q,
        'rows' => cdp_commThreads($filter === 'all' ? '' : $filter, $q, 60),
        'counts' => cdp_commThreadCounts($q),
    ];
}

/** One conversation and its latest messages (read only: the page marks it read through the thread endpoint). */
function sl_pmThread(int $id): ?array
{
    $t = cdp_opsOne("SELECT t.*, u.locker, u.created AS customer_since, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer_name
                     FROM cdb_comm_threads t LEFT JOIN cdb_users u ON u.id = t.user_id WHERE t.id = :id", [':id' => $id]);
    if (!$t) {
        return null;
    }
    $msgs = array_reverse(cdp_opsQ('SELECT id, direction, author_type, author_id, body, status, created_at FROM cdb_comm_messages WHERE thread_id = :t ORDER BY id DESC LIMIT 120', [':t' => $id]));
    $parcels = (int) $t->user_id > 0 ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0', [':u' => (int) $t->user_id], 0) : 0;
    return ['t' => $t, 'msgs' => $msgs, 'parcels' => $parcels, 'window' => cdp_commWaWindowOpen($t->phone), 'provider' => cdp_commSetting('wa_provider')];
}

/** Communication Center lists for the phone: campaigns, outbox and automations. */
function sl_pmCenter(): array
{
    $camps = function_exists('cdp_commCampaigns') ? array_slice(cdp_commCampaigns(), 0, 20) : [];
    $out = cdp_opsQ('SELECT id, channel, recipient, subject, status, last_error, created_at FROM cdb_comm_outbox ORDER BY id DESC LIMIT 20');
    $rules = cdp_opsDecode(cdp_commSetting('rules'), []);
    $autos = [];
    foreach (cdp_commEvents() as $k => $e) {
        $on = array_keys(array_filter((array) ($rules[$k] ?? [])));
        $autos[] = ['k' => $k, 'label' => (string) ($e['label'] ?? $k), 'help' => (string) ($e['help'] ?? ''), 'on' => $on];
    }
    return ['campaigns' => $camps, 'outbox' => $out, 'autos' => $autos, 'enabled' => cdp_commSetting('enabled') === '1'];
}

/** Messages sent per day for the last 7 days (Campaigns chart). */
function sl_pmWeekBars(): array
{
    $m = [];
    if (sl_tableExists('cdb_message_log')) {
        foreach (sl_rows("SELECT DATE(created_at) d, COUNT(*) n FROM cdb_message_log WHERE status = 'sent' AND created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY d") as $r) {
            $m[$r->d] = (int) $r->n;
        }
    }
    $vals = [];
    $labels = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $vals[] = $m[$day] ?? 0;
        $labels[] = date('D', strtotime($day));
    }
    $max = max(1, max($vals));
    $out = [];
    foreach ($vals as $k => $v) {
        $out[] = ['h' => 'height:' . max(6, (int) round($v / $max * 100)) . '%', 'l' => $labels[$k], 'v' => $v, 'c' => $k === 6 ? 'now' : ''];
    }
    return $out;
}

/** Scheduled and draft Communication Center campaigns (not yet in the message log). */
function sl_pmPlanned(): array
{
    $rows = function_exists('cdp_commCampaigns') ? cdp_commCampaigns() : [];
    $out = ['scheduled' => [], 'draft' => []];
    foreach ($rows as $r) {
        if (isset($out[$r->status])) {
            $out[$r->status][] = $r;
        }
    }
    return $out;
}

/** Saved e-mail builder designs (cdb_newsletter_designs). */
function sl_pmDesigns(): array
{
    if (!function_exists('cdp_nlTablesReady') || !cdp_nlTablesReady()) {
        return [];
    }
    return sl_rows('SELECT id, name, subject, preheader, updated_at, last_sent_at FROM cdb_newsletter_designs ORDER BY updated_at DESC LIMIT 100');
}

/** One saved design with its blocks, for the phone builder screen. */
function sl_pmDesign(int $id): ?array
{
    if ($id <= 0 || !function_exists('cdp_nlTablesReady') || !cdp_nlTablesReady()) {
        return null;
    }
    $r = sl_row('SELECT * FROM cdb_newsletter_designs WHERE id = :id', [':id' => $id]);
    if (!$r) {
        return null;
    }
    $json = json_decode((string) $r->design_json, true);
    return ['row' => $r, 'blocks' => is_array($json['blocks'] ?? null) ? $json['blocks'] : []];
}
