<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Help \& Support: customers chat with King or a person, follow their requests, read help articles.
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

        if (cdp_csIsAgent($user)) {
            // Staff work cases from the desk; a link to a ticket goes straight to it.
            header("location: " . (!empty($_GET["t"]) ? "support_ticket.php?id=" . (int) $_GET["t"] : "support_inbox.php"));
            exit;
        }
        if (!cdp_csIsCustomer($user)) {
            header("location: error403.php");
            exit;
        }

        include('views/support/hub.php');

    } else {
        header("location: login.php");
        exit;
    }
?>
