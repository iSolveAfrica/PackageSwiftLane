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
    require_once 'helpers/sl_auth.php';
    $user = new User();
    $core = new Core();
    if ($user->cdp_loginCheck()) {
        header('location: index.php');
        exit;
    }
    if (isset($core->reg_allowed) && (string) $core->reg_allowed === '0') {
        $_SESSION['sl_flash'] = ['type' => 'warn', 'text' => 'Registration is closed at the moment. Please contact us.'];
        header('location: login.php');
        exit;
    }
    $verifylocker = $core->cdp_verifylockers();
    $lockerauto = $core->cdp_virtual_locker();
    $db = new Conexion;
    $countries = sl_rows('SELECT id, name, iso2, phone_code FROM cdb_countries WHERE is_active = 1 ORDER BY name');
    $pics = sl_loginPicturesHtml('register', 'fs');
    sl_render('SignUp', ['title' => 'Create your account', 'board' => 'SignUp', 'plain' => true, 'scripts' => ['dataJs/sl/auth.js']], [
        'countries' => $countries,
        'locker' => (string) $lockerauto,
        'prefix' => (string) ($core->prefix_locker ?? ''),
        'docs' => [['DNI', 'National ID'], ['PSP', 'Passport'], ['ECW', 'ECOWAS card']],
        'otp' => cdp_otpEnabled(),
        'site' => $core->site_name ?? 'Swift Lane',
        'heading' => $pics['texts'][0] ?? 'Create your account',
        'button' => $pics['texts'][1] ?? 'Create my account',
        'city' => $core->c_city ?? '',
        'country' => $core->c_country ?? '',
    ]);
