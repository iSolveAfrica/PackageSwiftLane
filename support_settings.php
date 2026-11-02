<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Customer Service settings: King, teams, routing and automation rules, SLA, saved replies, knowledge base, channels.
// * See helpers/support.php and docs/CUSTOMER_SERVICE.md.                 *
// *                                                                       *
// *************************************************************************

    require_once("loader.php");
    require_once("helpers/querys.php");
    require_once("helpers/support_king.php");

    $user = new User();
    $core = new Core();

    if ($user->cdp_loginCheck() == true) {

        $permissions = $user->cdp_getUserPermissions();

        if (!cdp_csIsAgent($user)) {
            header("location: error403.php");
            exit;
        }

        include('views/support/settings.php');

    } else {
        header("location: login.php");
        exit;
    }
?>
