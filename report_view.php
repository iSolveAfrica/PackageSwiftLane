<?php
/** Report runner (Swift Lane design): filters + results for every legacy report in helpers/sl_reports.php. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/sl_reports.php';
$key = (string) ($_GET['r'] ?? '');
$rep = sl_report($key);
if (!$rep) {
    header('Location: reports.php');
    exit;
}
sl_gate($rep['perm']);
$d = ['key' => $key, 'rep' => $rep];
$d['statuses'] = sl_rows('SELECT id, mod_style FROM cdb_styles ORDER BY mod_style');
$d['agencies'] = sl_rows('SELECT id, name_branch FROM cdb_branchoffices ORDER BY name_branch');
$d['methods'] = sl_rows('SELECT id, name_pay FROM cdb_met_payment ORDER BY name_pay');
$d['customers'] = in_array('customer_id', $rep['params'], true) ? sl_rows("SELECT id, CONCAT(fname, ' ', lname, IF(locker <> '', CONCAT(' · ', locker), '')) AS n FROM cdb_users WHERE userlevel = 1 ORDER BY fname LIMIT 2000") : [];
$d['employees'] = in_array('employee_id', $rep['params'], true)
    ? sl_rows('SELECT id, CONCAT(fname, " ", lname) AS n FROM cdb_users WHERE ' . ($rep['drivers'] ? 'userlevel = 3' : 'userlevel NOT IN (1, 3)') . ' ORDER BY fname')
    : [];
$d['siblings'] = array_filter(sl_reports(), fn($r) => $r['group'] === $rep['group'] && $user->cdp_hasPermission($r['perm']));
sl_render('ReportView', ['id' => 'reports', 'title' => $rep['title'], 'board' => 'ReportView', 'scripts' => ['dataJs/sl/report_view.js']], $d);
