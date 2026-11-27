<?php
/**
 * Notifications & Messages > Overview: what went out (from the Outbox feed:
 * automatic, campaigns, individual sends, inbox replies), failures, costs,
 * the state of every channel, things that need attention, and a way into
 * every page of the area. View: views/sl/pages/CommsHub.php.
 * Staff (rank 2+) or anyone with a messaging permission.
 */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'helpers/querys.php';
require_once 'helpers/comms.php';
require_once 'helpers/comms_outbox_view.php';
require_once 'helpers/message_cost.php';
require_once 'helpers/sl_nav.php';
require_once 'views/sl/data/comms_settings.php';
if (cdp_opsRank($user) < 2 && !$user->cdp_hasPermission(['push_notifications', 'manage_newsletter', 'view_message_logs'])) {
    header('Location: error403.php');
    exit;
}

$ready = cdp_commEnsureTables();
$days = in_array((int) ($_GET['days'] ?? 30), [1, 7, 30, 90], true) ? (int) ($_GET['days'] ?? 30) : 30;
$from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
$to = date('Y-m-d');
$base = ['from' => $from, 'to' => $to];
$f = cdp_commOutboxFilters($base);
$stats = $ready ? cdp_commOutboxStats($f) : ['total' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'waiting' => 0, 'received' => 0, 'cost' => 0, 'currency' => ''];
$outbox = sl_route('CommsOutbox');
$olink = fn(array $q) => $outbox . '?' . http_build_query(array_merge($base, $q));

// Per channel: sent / failed and a 14-day spark from the message log.
$hasLog = sl_tableExists('cdb_message_log');
$spark = function (string $ch) use ($hasLog) {
    $m = [];
    if ($hasLog) {
        foreach (sl_rows("SELECT DATE(created_at) d, COUNT(*) n FROM cdb_message_log WHERE created_at >= CURDATE() - INTERVAL 13 DAY AND channel = :c AND status = 'sent' GROUP BY d", [':c' => $ch]) as $r) {
            $m[$r->d] = (int) $r->n;
        }
    }
    $v = [];
    for ($i = 13; $i >= 0; $i--) {
        $v[] = $m[date('Y-m-d', strtotime("-$i days"))] ?? 0;
    }
    return sl_sparkBars($v, 6);
};
$channels = [];
foreach (['whatsapp' => ['WhatsApp', 'i-wa'], 'sms' => ['SMS', 'i-message'], 'email' => ['E-mail', 'i-mail']] as $ch => [$label, $icon]) {
    $s = $ready ? cdp_commOutboxStats(cdp_commOutboxFilters($base + ['channel' => $ch])) : ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'waiting' => 0, 'received' => 0, 'cost' => 0];
    $done = $s['sent'] + $s['failed'];
    $channels[] = ['ch' => $ch, 'label' => $label, 'icon' => $icon, 'sent' => $s['sent'], 'failed' => $s['failed'], 'skipped' => $s['skipped'], 'waiting' => $s['waiting'],
        'received' => $s['received'], 'cost' => $s['cost'], 'rate' => $done > 0 ? round(100 * $s['sent'] / $done) : null, 'spark' => $spark($ch), 'href' => $olink(['channel' => $ch])];
}

// Where messages came from (origin) in the period.
$origins = [];
if ($ready) {
    foreach (cdp_commOutboxOrigins() as $k => $l) {
        $s = cdp_commOutboxStats(cdp_commOutboxFilters($base + ['origin' => $k]));
        if ($s['total'] > 0) {
            $origins[] = ['k' => $k, 'label' => $l, 'n' => $s['total'], 'failed' => $s['failed'], 'href' => $olink(['origin' => $k])];
        }
    }
    usort($origins, fn($a, $b) => $b['n'] <=> $a['n']);
}

// Recent failures.
$fails = $ready ? cdp_commOutboxFeed(cdp_commOutboxFilters($base + ['status' => 'failed']), 1, 6)['rows'] : [];

// Things that need attention.
$status = sl_commsChannelStatus();
$s = cdp_commSettings(true);
$isAdmin = cdp_opsRank($user) >= 3;
$settingsUrl = sl_route('CommsSettings');
$alerts = [];
$notSet = [];
foreach (['whatsapp', 'sms', 'email'] as $ch) {
    $st = $status[$ch];
    if ($st['state'] === 'Not set up') {
        $notSet[$ch] = $st['label'];
    } elseif (!$st['ok']) {
        $alerts[] = ['lvl' => 'warn', 'icon' => $st['icon'], 't' => $st['label'] . ': ' . strtolower($st['state']), 'd' => 'Messages on this channel are not sent.',
            'href' => $isAdmin ? $settingsUrl . '#cs-' . $ch : '', 'cta' => 'Open settings'];
    }
}
if ($notSet) {
    $names = array_values($notSet);
    $list = count($names) > 1 ? implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names) : $names[0];
    $alerts[] = ['lvl' => 'bad', 'icon' => 'i-alert', 't' => $list . (count($names) > 1 ? ' are' : ' is') . ' not set up', 'd' => 'Nothing goes out on ' . (count($names) > 1 ? 'these channels' : 'this channel') . ' until the provider is set up.',
        'href' => $isAdmin ? $settingsUrl . '#cs-' . array_key_first($notSet) : '', 'cta' => 'Set up'];
}
if ($s['enabled'] !== '1') {
    $alerts[] = ['lvl' => 'warn', 'icon' => 'i-bolt', 't' => 'Automatic messages are switched off', 'd' => 'Status messages from the Automation Center, reminders and surveys wait until they are switched on.',
        'href' => $isAdmin ? $settingsUrl . '#cs-rules' : '', 'cta' => 'Sending rules'];
} elseif (!$status['worker']['ok']) {
    $alerts[] = ['lvl' => 'bad', 'icon' => 'i-clock', 't' => 'The message worker is late', 'd' => $status['worker']['provider'] . '. It must run every 5 minutes (cron/comms_worker.php).',
        'href' => $isAdmin ? $settingsUrl . '#cs-rules' : '', 'cta' => 'Sending rules'];
}
if ($stats['failed'] > 0) {
    $alerts[] = ['lvl' => 'bad', 'icon' => 'i-alert', 't' => sl_num($stats['failed']) . ' failed ' . ($stats['failed'] === 1 ? 'message' : 'messages'), 'd' => 'In the ' . ($days === 1 ? 'last day' : 'last ' . $days . ' days') . '. Open each one to see why and retry.',
        'href' => $olink(['status' => 'failed']), 'cta' => 'See failures'];
}
$stuck = $ready ? (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_outbox WHERE status = 'queued' AND send_after <= :n AND created_at < :h", [':n' => cdp_opsNow(), ':h' => cdp_opsNow(-3600)], 0) : 0;
if ($stuck > 0) {
    $alerts[] = ['lvl' => 'warn', 'icon' => 'i-clock', 't' => sl_num($stuck) . ' waiting for more than an hour', 'd' => 'Due messages that have not gone out. Check the worker and the channel settings.',
        'href' => $olink(['status' => 'waiting', 'from' => '']), 'cta' => 'See waiting'];
}
$pending = $ready ? (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_campaigns WHERE status = 'pending'", [], 0) : 0;
if ($pending > 0) {
    $alerts[] = ['lvl' => 'info', 'icon' => 'i-send', 't' => sl_num($pending) . ' ' . ($pending === 1 ? 'campaign waits' : 'campaigns wait') . ' for approval', 'd' => 'A second administrator approves campaigns before they go out.',
        'href' => sl_route('CommsSend') . '#campaigns', 'cta' => 'Campaigns'];
}
$waiting = sl_navCommsWaiting();
if ($waiting > 0) {
    $alerts[] = ['lvl' => 'info', 'icon' => 'i-wa', 't' => sl_num($waiting) . ' WhatsApp ' . ($waiting === 1 ? 'chat waits' : 'chats wait') . ' for a person', 'd' => 'Customers who wrote in and were handed over by ' . cdp_kingName() . '.',
        'href' => sl_route('CommsInbox'), 'cta' => 'Open WhatsApp'];
}
if ($status['king']['warn']) {
    $alerts[] = ['lvl' => 'warn', 'icon' => 'i-ai', 't' => cdp_kingName() . ' is on but the AI is not set up', 'd' => 'WhatsApp chats go straight to a person until the AI centre has a provider.',
        'href' => sl_route('AiCenter'), 'cta' => 'AI centre'];
}
$owner = function_exists('cdp_commStatusOwner') ? cdp_commStatusOwner() : 'legacy';

// Quick links: every page of the area this person may open, with what it is for.
$what = [
    'commsautomations' => 'Everything that sends a message, with WhatsApp, SMS and e-mail per trigger.',
    'commsstatuses' => 'One message per shipment status and channel, predefined and editable.',
    'commssend' => 'One person, several people or a campaign, with the phone and e-mail editors.',
    'commsoutbox' => 'Every message: automatic, campaigns, individual and inbox replies.',
    'commsinbox' => 'WhatsApp conversations with customers, ' . cdp_kingName() . ' and your team.',
    'commscustomers' => 'Everything said to and heard from one customer.',
    'commssurveys' => 'Questions, when they are sent, and the answers.',
    'commssettings' => 'Providers, sending rules, consent, ' . cdp_kingName() . ', costs and test sends.',
];
$links = [];
foreach (sl_navResolveChildren(sl_navCommsChildren(), 'commsoverview') as $c) {
    if (isset($what[$c['id']])) {
        $links[] = $c + ['what' => $what[$c['id']]];
    }
}

sl_render('CommsHub', ['id' => 'commshub', 'title' => 'Notifications & Messages', 'board' => 'CommsHub', 'wide' => true], [
    'ready' => $ready, 'days' => $days, 'stats' => $stats, 'channels' => $channels, 'origins' => $origins, 'fails' => $fails, 'alerts' => $alerts,
    'status' => $status, 'owner' => $owner, 'links' => $links, 'isAdmin' => $isAdmin, 'olink' => $olink, 'settingsUrl' => $settingsUrl,
    'canSend' => cdp_opsRank($user) >= 2, 'currency' => (string) ($stats['currency'] ?: (cdp_mcSettings()['currency'] ?? '')),
]);
