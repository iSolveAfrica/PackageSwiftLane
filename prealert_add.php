<?php
// The add form is part of the PreAlerts page now; this URL opens it.
require_once 'loader.php';
$user = new User();
require_once 'helpers/sl_view.php';
sl_gate('prealert_add');
// Phones: customers get the app screen M-CPreAlert (views/sl/phone/PreAlertAdd.customer.php); everyone else keeps the redirect.
require_once 'helpers/sl_phone.php';
if (sl_phoneView('PreAlertAdd') !== '') {
    $core = new Core();
    $userData = $user->cdp_getUserData();
    require_once 'helpers/sl_auth.php';
    $uid = (int) $_SESSION['userid'];
    $d = [
        'couriers' => sl_rows('SELECT id, name_com FROM cdb_courier_com ORDER BY name_com'),
        'addresses' => sl_customerAddresses($userData, $core),
        'locker' => (string) ($userData->locker ?? ''),
        'openN' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_pre_alert WHERE customer_id = :u AND is_package = 0', [':u' => $uid]),
        'overdueN' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_pre_alert WHERE customer_id = :u AND is_package = 0 AND estimated_date < CURDATE()', [':u' => $uid]),
        'eta' => date('Y-m-d', strtotime('+5 days')),
    ];
    sl_render('PreAlertAdd', ['id' => 'prealert', 'title' => 'New pre-alert'], $d);
    exit;
}
header('Location: prealert_list.php?new=1');
exit;
