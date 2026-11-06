<?php
// ============================================================================
// My Bills — customer self-service payments (userlevel 1).
// ============================================================================

require_once("loader.php");
require_once("helpers/finance_switch.php");
cdp_financeGuardPage(); // financial module switched off: back to the dashboard

$user = new User();
$core = new Core();

if ($user->cdp_loginCheck() == true) {

    $permissions = $user->cdp_getUserPermissions();

    if (!$user->cdp_hasPermission('view_my_bills')) {
        header("location: error403.php");
        exit;
    }

    // MyBills board (views/sl/pages/MyBills.php); paying a chosen set of packages is pay_checkout.php.
    require_once 'helpers/sl_view.php';
    require_once 'helpers/sl_auth.php';
    sl_passwordRotationGuard();
    require_once 'views/sl/data/portal.php';
    $userData = $user->cdp_getUserData();
    sl_render('MyBills', ['id' => 'mybills', 'title' => 'My bills', 'board' => 'MyBills'], sl_billsData($user, $core, $userData));

} else {

    header("location: login.php");
    exit;
}
