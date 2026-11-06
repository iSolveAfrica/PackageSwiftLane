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
        header('location: ' . sl_route('PasswordReset'));
        exit;
    }
    $pics = sl_loginPicturesHtml('forgot', 'fs');
    $notice = ($_GET['notice'] ?? '') === 'otp_expired' ? 'Your verification session expired. Request a new code.' : '';
    sl_render('ForgotPassword', ['title' => 'Forgot password', 'board' => 'ForgotPassword', 'plain' => true, 'scripts' => ['dataJs/sl/auth.js']], [
        'step' => 1,
        'notice' => $notice,
        'pics' => $pics,
        'site' => $core->site_name ?? 'Swift Lane',
        'heading' => $pics['texts'][0] ?? 'Forgot your password?',
        'button' => $pics['texts'][1] ?? 'Send me a code',
    ]);
