<?php
// *************************************************************************
// * Developer dashboard collector. Run every minute from cron so the      *
// * charts, uptime and alerts keep a history while nobody has the page   *
// * open:                                                                 *
// *   * * * * * php /path/to/cron/devops_collector.php                    *
// *************************************************************************

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
chdir(dirname(__DIR__));
require_once 'loader.php';
require_once 'helpers/rbac.php';
require_once 'helpers/devops.php';

$lock = fopen(sys_get_temp_dir() . '/swiftlane_devops_collector.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
if (!cdp_devEnsureTables()) {
    fwrite(STDERR, "devops tables unavailable\n");
    exit(1);
}
$snap = cdp_devCollect(true);
cdp_devStateSet('collector_last', time());
$down = array_filter($snap['services'], function ($s) { return $s['status'] === 'down'; });
echo date('c') . ' sampled; ' . count($snap['services']) . ' services, ' . count($down) . " down\n";
