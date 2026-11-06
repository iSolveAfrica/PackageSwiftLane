<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Developer dashboard: live infrastructure, services, logs, deploys,    *
// * architecture and alerts. Assigned super admins only.                  *
// * See helpers/devops.php and docs/DEVELOPER_DASHBOARD.md.               *
// *                                                                       *
// *************************************************************************

    require_once("loader.php");
    require_once("helpers/rbac.php");
    require_once("helpers/devops.php");

    $user = new User();
    $core = new Core();

    if ($user->cdp_loginCheck() == true) {

        $permissions = $user->cdp_getUserPermissions();

        if (!cdp_devCanUse($user)) {
            header("location: error403.php");
            exit;
        }

        include('views/devops/developer.php');

    } else {
        header("location: login.php");
        exit;
    }
?>
