#!/usr/bin/env php
<?php
/**
 * cron/automations.php
 *
 * Runs the scheduled automations (helpers/automations_scheduled.php): published rules on checks such as
 * "pre-alert still not arrived" (closes pre-alerts 30 days past their expected date and tells the customer),
 * "parcel ready but not collected", "consolidation past its ETA". Each record is handled once per rule.
 * The suggested rules are added to the Automations page on the first run.
 *
 * Suggested crontab (every 15 minutes):
 *   0,15,30,45 * * * * php /var/www/html/PackageSwiftLane/cron/automations.php >> /var/log/automations.log 2>&1
 * Without cron the same runs at most every 15 minutes after a staff page is opened.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../loader.php';
require_once __DIR__ . '/../helpers/querys.php';
require_once __DIR__ . '/../helpers/automations.php';

cdp_automationSuggestionsSync();
$s = cdp_automationsScheduled();
echo date('c') . " automations: {$s['rules']} rule(s) checked, {$s['handled']} record(s) handled\n";
