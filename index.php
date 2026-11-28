<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************



// Verifica si el archivo de configuración existe
// config/config.php is created from config/config.example.php (see README.md);
// loader.php explains what is missing when it is absent.

// Incluye el archivo loader.php
require_once("loader.php");

// Crea instancias de las clases User y Core
$user = new User();
$core = new Core();

$permissions = $user->cdp_getUserPermissions();

// Verifica si estamos autenticados
if ($user->cdp_loginCheck() == true) {
    // Route to a dashboard by the role's dashboard_type flag (not a hardcoded
    // userlevel switch) so new department roles land somewhere sensible:
    //   'admin'  -> full admin dashboard   'client' -> customer dashboard
    //   'driver' -> driver dashboard       'roles'  -> generic permission-aware
    // A brand-new staff role defaults to 'roles' until configured otherwise.
    require_once("helpers/rbac.php");
    $dashType = cdp_dashboardType($_SESSION['userlevel'] ?? 0);
    // Phones open the phone app's home screen for the role (design canvas Page 2): riders the rider app
    // (M-RHome), staff without the admin overview their own desk (M-Workspace), so no overview figures are
    // shown to a role that may not see them. Computers keep the web dashboards below.
    require_once 'helpers/sl_device.php';
    if (sl_isPhone() && ($dashType === 'driver' || $dashType === 'roles' || !in_array($dashType, ['admin', 'client'], true))) {
        header('Location: ' . ($dashType === 'driver' ? 'driver_app.php' : 'workspace.php'));
        exit;
    }
    switch ($dashType) {
        case 'admin':
            require_once 'helpers/sl_view.php';
            require_once 'views/sl/data/overview.php';
            $userData = $user->cdp_getUserData();
            sl_render('Main', ['id' => 'dash', 'title' => 'Overview', 'board' => 'Main'], sl_overviewData($user, $core, $userData));
            break;
        case 'client':
            // ClientDashboard board (views/sl/pages/ClientDashboard.php)
            require_once 'helpers/sl_view.php';
            require_once 'helpers/sl_auth.php';
            sl_passwordRotationGuard();
            require_once 'views/sl/data/portal.php';
            $userData = $user->cdp_getUserData();
            sl_render('ClientDashboard', ['id' => 'dash', 'title' => 'Dashboard', 'board' => 'ClientDashboard'], sl_clientDashboardData($user, $core, $userData));
            break;
        case 'driver':
            // Rider overview (views/sl/pages/RiderHome.php); the working tool is the Rider app.
            require_once 'helpers/sl_view.php';
            require_once 'views/sl/data/rider_home.php';
            $userData = $user->cdp_getUserData();
            sl_render('RiderHome', ['id' => 'dash', 'title' => 'Overview', 'board' => 'RiderHome'], sl_riderHomeData($user, $core, $userData));
            break;
        case 'roles':
        default:
            include('dashboard_roles.php');
            break;
    }
} else {
    // Si no estamos autenticados, redirige al inicio de sesión
    header("location: login.php");
    exit;
}
