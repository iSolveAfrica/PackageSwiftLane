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



    require_once("loader.php");

    $user = new User();
    $core = new Core();
    // ... ask if we are logged in here:
    if ($user->cdp_loginCheck() == true) 
    {
      // Security: logging in is not enough; customers, drivers and agency users may
      // only print their own documents (see helpers/print_access.php).
      require_once(__DIR__ . "/helpers/print_access.php");
      if (isset($_GET['id'])) {
          cdp_printOwnerOr403('consolidate', [$_GET['id']]);
      }


       
      // Audit: record who took this document out of the system.
      require_once(__DIR__ . "/helpers/activity_log.php");
      cdp_activityLogDocument();

      include('views/print/print_consolidate.php');     
           

    } else{
        
        require_once(__DIR__ . "/helpers/print_access.php");
        cdp_printLoginRedirect(); // back to this document after signing in
        exit;       
    }
?>