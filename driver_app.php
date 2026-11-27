<?php
// Rider app home (design: M-RHome): on/off duty, today's numbers, next stop, entry to the task list.
// ?job=ID keeps the handover / collection form of an assigned shipment or pickup.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_mx.php';
sl_gate();
if (!sl_userIs('driver') && !$user->cdp_hasPermission('view_user_list')) {
    header('Location: error403.php');
    exit;
}
// Old tab links: the route and the earnings are screens of their own now.
$tab = (string) ($_GET['tab'] ?? '');
if ($tab === 'route' || $tab === 'pay') {
    header('Location: ' . sl_route($tab === 'route' ? 'RiderMap' : 'RiderEarnings'));
    exit;
}
header('Cache-Control: private, no-store');
require_once 'views/sl/data/rider_ops.php';
$d = ro_data($user, $core);
$jobId = (int) ($_GET['job'] ?? 0);
$d['job'] = null;
$d['missingJob'] = false;
if ($jobId > 0 && $d['isRider']) {
    $legacy = sl_driverAppData($user, $core);
    $d['job'] = $legacy['job'];
    $d['codesOn'] = $legacy['codesOn'];
    $d['missingJob'] = !$d['job'];
    if ($d['job'] && $d['job']['state'] === 'done') {
        header('Location: ' . sl_route('RiderDone', ['id' => 'o' . $jobId]));
        exit;
    }
}
if ($d['isRider'] && !$jobId) {
    $d['week'] = ro_week((int) $d['id']);
    $d['shift'] = null;
    if (function_exists('sl_rmShifts')) {
        foreach (sl_rmShifts((int) $d['id'], date('Y-m-d'), date('Y-m-d', strtotime('+14 day'))) as $sh) {
            if ($sh->day . ' ' . $sh->end_time < date('Y-m-d H:i:s')) continue;
            $d['shift'] = ['when' => sl_date($sh->day, 'D j M') . ' · ' . substr((string) $sh->start_time, 0, 5) . '–' . substr((string) $sh->end_time, 0, 5), 'hub' => (string) $sh->hub];
            break;
        }
    }
}
sl_render('DriverApp', sl_mxPage(['id' => 'drvapp', 'title' => 'Rider app', 'board' => 'RiderOps', 'plain' => true]), $d);
