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
    require_once 'helpers/sl_view.php';
    require_once 'views/sl/data/portal.php';
    $user = new User();
    $core = new Core();
    // Public page (no login). Light per-session throttle against number guessing.
    $code = (string) ($_GET['order_track'] ?? ($_GET['q'] ?? ''));
    $now = time();
    $_SESSION['sl_track_hits'] = array_values(array_filter((array) ($_SESSION['sl_track_hits'] ?? []), fn($t) => $t > $now - 60));
    $throttled = false;
    if ($code !== '') {
        $_SESSION['sl_track_hits'][] = $now;
        $throttled = count($_SESSION['sl_track_hits']) > 30;
    }
    $d = $throttled ? ['code' => $code, 'found' => false, 'searched' => true, 'throttled' => true] : sl_trackData($code, $core);
    $d['loggedIn'] = $user->cdp_loginCheck();
    $d['site'] = $core->site_name ?? 'Swift Lane';
    $d['rate'] = (float) ($core->exchange_rate ?? 0);
    sl_render('PublicTracking', ['title' => $code !== '' ? 'Tracking ' . $code : 'Track a parcel', 'board' => 'PublicTracking', 'plain' => true], $d);
