<?php
/** Settings hub: every setting in one place (SettingsHub artboard). The previous tools grid stays at tools.php?legacy=1. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_tools');
if (isset($_GET['legacy'])) {
    include 'views/tools/all_tools.php';
    exit;
}
require_once 'views/sl/data/settings_hub.php';
sl_render('SettingsHub', ['id' => 'general', 'title' => 'Settings', 'board' => 'SettingsHub'], sl_settingsHubData($user, $core));
