<?php
// *************************************************************************
// * Customer Service worker. Run every minute or five from cron:          *
// *   * * * * * php /path/to/cron/support_worker.php                      *
// *                                                                       *
// *  - records SLA warnings and breaches and runs the sla.* rules         *
// *  - runs "no customer reply" rules on tickets waiting on the customer  *
// *  - delivers the notification outbox (e-mail / WhatsApp / SMS) with    *
// *    retries; a row that fails 5 times is marked dead (Settings shows it)*
// *  - closes resolved tickets after N days only when an administrator    *
// *    has set auto_close_days (0 = never)                                *
// * A lock file stops two runs overlapping.                               *
// *************************************************************************

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
chdir(dirname(__DIR__));
require_once 'loader.php';
require_once 'helpers/querys.php';
require_once 'helpers/support.php';

$lock = fopen(sys_get_temp_dir() . '/swiftlane_support_worker.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
if (!cdp_csEnsureTables()) {
    fwrite(STDERR, "support tables unavailable\n");
    exit(1);
}

$sla = cdp_csSlaSweep(500);

// "No reply" rules: tickets waiting on the customer, checked against each rule's own conditions.
$noReply = 0;
if (cdp_csOne("SELECT id FROM cdb_cs_rules WHERE trigger_event = 'schedule.no_reply' AND status = 'published' LIMIT 1")) {
    foreach (cdp_csQ("SELECT id, last_activity_at FROM cdb_cs_tickets WHERE status = 'pending_customer' AND disposition = 'none' ORDER BY last_activity_at LIMIT 300") as $t) {
        $res = cdp_csRulesRun('schedule.no_reply', $t->id, ['dedupe' => 'cycle' . substr(sha1((string) $t->last_activity_at), 0, 8)]);
        foreach ($res as $r) {
            if (($r['outcome'] ?? '') === 'ok') {
                $noReply++;
            }
        }
    }
}

$closed = 0;
$days = (int) cdp_csSetting('auto_close_days', '0');
if ($days > 0) {
    foreach (cdp_csQ("SELECT id FROM cdb_cs_tickets WHERE status = 'resolved' AND resolved_at < :d LIMIT 300", [':d' => cdp_csNow(-$days * 86400)]) as $t) {
        $r = cdp_csTicketUpdate($t->id, ['status' => 'closed'], 'system', null, null, ['reason' => "Closed automatically after $days days resolved"]);
        $closed += !empty($r['ok']) ? 1 : 0;
    }
}

$out = cdp_csOutboxRun(100);
cdp_csSettingSet('worker_last_run', cdp_csNow());
echo date('c') . " sla=$sla no_reply=$noReply closed=$closed sent={$out['sent']} failed={$out['failed']} suppressed={$out['suppressed']}\n";
