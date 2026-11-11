<?php
// *************************************************************************
// * Communication Center worker - run every 5 minutes:                    *
// *   */5 * * * * php /path/to/app/cron/comms_worker.php >/dev/null 2>&1   *
// *                                                                       *
// * One pass: detect new events (tracking history, warehouse packages),   *
// * once a day after 09:00 the claim and payment reminders, launch        *
// * scheduled campaigns, then deliver what is due from the outbox. Also   *
// * runs the Master View scheduled reports and the staff module's         *
// * housekeeping (missed shifts, overdue tasks) so one cron line is       *
// * enough. A lock file stops two passes overlapping.                     *
// *************************************************************************

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
chdir(dirname(__DIR__));
require_once 'loader.php';
require_once 'helpers/querys.php';
require_once 'helpers/comms.php';
require_once 'helpers/mgmt.php';
require_once 'helpers/staffops.php';

$lock = fopen(sys_get_temp_dir() . '/swiftlane_comms_worker.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Another pass is running.\n";
    exit(0);
}
@set_time_limit(280);

$r = cdp_commRunWorker();
echo date('c') . ' comms: detected ' . $r['detected'] . ', delivered ' . json_encode($r['delivered']) . "\n";

try {
    $m = cdp_mgmtRunSchedules();
    echo date('c') . ' reports: ' . json_encode($m) . "\n";
} catch (Throwable $e) {
    echo 'reports error: ' . $e->getMessage() . "\n";
}
try {
    $s = cdp_staffHousekeeping();
    echo date('c') . ' staff: ' . json_encode($s) . "\n";
} catch (Throwable $e) {
    echo 'staff error: ' . $e->getMessage() . "\n";
}

flock($lock, LOCK_UN);
fclose($lock);
