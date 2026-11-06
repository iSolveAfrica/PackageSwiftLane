<?php
// AiStudio board: the signed-in user's profile photo, name and icon (staff and customers).
// Writes: ajax/sl/profile_studio_ajax.php; photo upload: the existing avatar endpoints.
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'helpers/sl_auth.php';
sl_passwordRotationGuard();
require_once 'views/sl/data/portal.php';
sl_render('AiStudio', ['id' => 'profile', 'title' => 'Profile and photo', 'board' => 'AiStudio', 'scripts' => ['dataJs/sl/profile_studio.js']], sl_profileStudioData($user, $core, $userData));
