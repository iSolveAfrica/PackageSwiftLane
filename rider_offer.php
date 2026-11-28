<?php
// Rider app (design: M-ROffer): one assigned job in detail, with Start.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_mx.php';
sl_gate();
if (!sl_userIs('driver') && !$user->cdp_hasPermission('view_user_list')) {
    header('Location: error403.php');
    exit;
}
header('Cache-Control: private, no-store');
require_once 'views/sl/data/rider_ops.php';
$d = ro_data($user, $core);
$d['stop'] = $d['isRider'] ? ro_stop($d, (string) ($_GET['id'] ?? '')) : null;
// Accept = the existing start action; once started the job lives on the navigation and handover screens.
if ($d['stop'] && $d['stop']['state'] !== 'new') {
    header('Location: ' . ($d['stop']['state'] === 'done' ? $d['stop']['donePage'] : ($d['stop']['state'] === 'failed' ? $d['stop']['open'] : $d['stop']['navPage'])));
    exit;
}
sl_render('RiderOffer', sl_mxPage(['id' => 'drvapp', 'title' => 'Job details', 'board' => 'RiderOps', 'plain' => true, 'scripts' => ['dataJs/sl/rider_map.js']]), $d);
