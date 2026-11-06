<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Public terms and conditions (Swift Lane design, plain page: views/sl/pages/Terms.php).
require_once 'loader.php';
$core = new Core();
require_once 'helpers/sl_view.php';
sl_render('Terms', ['title' => 'Terms and conditions', 'board' => '', 'plain' => true], [
    'site' => (string) ($core->site_name ?? 'Swift Lane'),
    'email' => (string) ($core->site_email ?? ''),
]);
