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

        $permissions = $user->cdp_getUserPermissions();

        $userData = $user->cdp_getUserData();
      require_once 'helpers/sl_view.php';
      require_once 'views/sl/data/customers.php';
        if (!sl_customersCanView($user)) {
            header("location: error403.php");
            exit;
        }

      if (($_GET['export'] ?? '') === 'csv') {
          require 'views/sl/export/customers_csv.php';
          exit;
      }
      // The list card alone, for filtering and paging the table in place (dataJs/sl/customers_stats.js).
      if (($_GET['partial'] ?? '') === 'list') {
          unset($_GET['partial']);
          header('Content-Type: text/html; charset=UTF-8');
          header('Cache-Control: private, no-store');
          $d = sl_customersData($user, $core, true);
          include CDP_ROOT . '/views/sl/pages/Customers.php';
          exit;
      }
      sl_render('Customers', ['id' => 'cust', 'title' => 'Customers', 'board' => 'Customers', 'scripts' => ['dataJs/sl/customers_stats.js']], sl_customersData($user, $core));
           

    } else{
        
        header("location: login.php");
        exit;       
    }
?>