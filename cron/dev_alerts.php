#!/usr/bin/env php
<?php
/**
 * cron/dev_alerts.php
 *
 * Evaluates the developer console alert rules (cdb_dev_alert_rules: disk, failed
 * cron runs, failed sign-in bursts, outbox backlog, PHP errors, stale cron) and
 * opens / refreshes / resolves rows in cdb_dev_alerts. Each run is recorded in
 * cdb_cron_runs through helpers/cron_run.php.
 *
 * Suggested crontab (every 5 minutes):
 *   *\/5 * * * * php /var/www/html/PackageSwiftLane/cron/dev_alerts.php >> /var/log/dev_alerts.log 2>&1
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
chdir(dirname(__DIR__));
require_once 'loader.php';
require_once 'helpers/sl_view.php';
require_once 'helpers/cron_run.php';
require_once 'helpers/dev_console.php';

$lock = fopen(sys_get_temp_dir() . '/swiftlane_dev_alerts.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
$summary = cdp_cronWrap('dev_alerts', function () {
    $o = sl_devEvaluateAlerts();
    return "checked={$o['checked']} firing={$o['firing']} opened={$o['opened']} resolved={$o['resolved']}";
});
echo '[' . date('Y-m-d H:i:s') . '] dev-alerts: ' . $summary . "\n";
