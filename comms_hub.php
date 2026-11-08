<?php
/** Messages and automation hub: channels, volumes, costs, automatic messages and links to every messaging page. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['push_notifications', 'manage_newsletter', 'view_message_logs']);
require_once 'views/sl/data/msg_common.php';
require_once 'views/sl/data/compose.php';
require_once 'helpers/automations.php';
require_once 'helpers/message_log_query.php';
require_once 'helpers/message_cost.php';
$hasLog = sl_tableExists('cdb_message_log');
$daily = function (string $where) use ($hasLog) {
    $m = [];
    if ($hasLog) foreach (sl_rows("SELECT DATE(created_at) d, COUNT(*) n FROM cdb_message_log WHERE created_at >= CURDATE() - INTERVAL 13 DAY AND $where GROUP BY d") as $r) $m[$r->d] = (int) $r->n;
    $out = [];
    $mx = max(1, max($m ?: [1]));
    for ($i = 13; $i >= 0; $i--) $out[] = $m[date('Y-m-d', strtotime("-$i days"))] ?? 0;
    return sl_sparkBars($out, 6);
};
$cnt = fn($w) => $hasLog ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_message_log WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND $w") : 0;
$cost = cdp_mcReport(['from' => date('Y-m-01'), 'to' => date('Y-m-d'), 'channel' => '', 'source' => '', 'group' => 'day']);
$cur = cdp_mcSettings()['currency'];
$autos = cdp_automationsAll();
$runsM = cdp_automationsReady() ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_automation_runs WHERE outcome = 'ok' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')") : 0;
$kpis = [
    ['icon' => 'i-message', 'l' => 'WhatsApp this month', 'v' => sl_num($cnt("channel = 'whatsapp' AND status = 'sent'")), 's' => 'delivered', 'sp' => $daily("channel = 'whatsapp'"), 'anim' => ''],
    ['icon' => 'i-phone', 'l' => 'SMS this month', 'v' => sl_num($cnt("channel = 'sms' AND status = 'sent'")), 's' => 'delivered', 'sp' => $daily("channel = 'sms'"), 'anim' => ''],
    ['icon' => 'i-mail', 'l' => 'E-mails this month', 'v' => sl_num($cnt("channel = 'email' AND status = 'sent'")), 's' => 'delivered', 'sp' => $daily("channel = 'email'"), 'anim' => ''],
    ['icon' => 'i-alert', 'l' => 'Failed this month', 'v' => sl_num($cnt("status = 'failed'")), 's' => 'see Message Logs', 'sp' => $daily("status = 'failed'"), 'anim' => ''],
    ['icon' => 'i-dollar', 'l' => 'Spent this month', 'v' => $cur . ' ' . cdp_mcMoney($cost['grand']['cost'] ?? 0), 's' => 'from unit costs', 'sp' => [], 'anim' => ''],
    ['icon' => 'i-bolt', 'l' => 'Automations', 'v' => count(array_filter($autos, fn($a) => (int) $a['enabled'])) . ' on', 's' => sl_num($runsM) . ' runs this month', 'sp' => [], 'anim' => ''],
];
$rules = sl_msgRules();
$ev = [];
foreach (sl_msgEvents() as $k => $e) {
    $n = 0;
    foreach (sl_msgEventSources($k) as $src) $n += $hasLog ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_message_log WHERE source = :s AND status = 'sent' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')", [':s' => $src]) : 0;
    $ev[] = ['k' => $k, 't' => $e['name'], 'd' => $e['group'] . ' · ' . $e['desc'], 'wa' => ($rules[$k]['whatsapp']['enabled'] ?? true) ? 'on' : '', 'sm' => ($rules[$k]['sms']['enabled'] ?? true) ? 'on' : '', 'em' => ($rules[$k]['email']['enabled'] ?? true) ? 'on' : '', 'n' => sl_num($n)];
}
$segs = [];
foreach (sl_composeSegments() as $k => $s) $segs[] = ['k' => $k, 't' => $s['label'], 'd' => 'Customers in this group right now', 'n' => sl_num(count(sl_rows($s['sql'])))];
$health = sl_msgChannelHealth();
$cov = [];
$i = 1;
foreach ([
    ['Notification Center', 'NotificationCenter', 'Settings › Notifications', 'view_tools'], ['Send a message', 'ComposeMessage', 'Messages › Compose', 'push_notifications'], ['Push notifications', 'Broadcast', 'Settings › Push Notifications', 'push_notifications'],
    ['E-mail builder', 'EmailBuilder', 'Settings › Email Builder', 'manage_newsletter'], ['SMS, WhatsApp and e-mail templates', 'MessageTemplates', 'Settings › SMS & WhatsApp', 'list_sms_templates'], ['Message logs', 'MessageLogs', 'Insights › Message Logs', 'view_message_logs'],
    ['Message costs', 'MessageCosts', 'Insights › Message Logs › Costs', 'view_message_logs'], ['Automations', 'Automations', 'Settings › Automations', 'manage_automations'], ['SMS provider settings', 'SettingsHub', 'Settings › General › Messaging', 'view_tools'],
    ['API connections', 'ApiConnections', 'Settings › API Connections', 'manage_api_settings'], ['Customer notification preferences', 'CustomerNotifications', 'Customer portal', null], ['In-app notifications', 'Notifications', 'Bell icon', null],
] as $c) {
    $ok = $c[3] === null || $user->cdp_hasPermission($c[3]);
    $cov[] = ['n' => $i++, 't' => $c[0], 'where' => $c[2], 'href' => sl_route($c[1], $c[1] === 'SettingsHub' ? ['tab' => 'messaging'] : []), 'off' => $ok ? '' : 'off', 'st' => $ok ? 'Live' : 'No access', 'pc' => $ok ? 'delivered' : 'plain'];
}
sl_render('CommsHub', ['id' => 'commshub', 'title' => 'Messages and automation', 'board' => 'CommsHub'], ['kpis' => $kpis, 'ev' => $ev, 'segs' => $segs, 'health' => $health, 'cov' => $cov, 'canRules' => $user->cdp_hasPermission(['manage_notification_rules', 'view_tools'])]);
