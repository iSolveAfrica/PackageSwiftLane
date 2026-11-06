<?php
/** Settings hub: every setting in one place (SettingsHub artboard). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_tools');
if (isset($_GET['legacy'])) {
    // The classic tools grid is gone: old links (?legacy=1&list=<view>) land on the design-system form.
    $map = ['config' => 'system', 'configlogo' => 'logo', 'config_seo' => 'seo', 'config_email' => 'smtp', 'config_api_google' => 'google',
            'config_taxes' => 'taxes', 'config_track_invoice' => 'numbering', 'config_info_ship_default' => 'shipping_defaults'];
    $list = (string) ($_GET['list'] ?? '');
    $to = isset($map[$list]) ? 'settings_form.php?f=' . $map[$list] : ($list === 'config_general' ? 'tools.php#language' : ($list === '' ? 'settings_form.php?f=logo' : 'tools.php'));
    header('Location: ' . $to, true, 302);
    exit;
}
require_once 'views/sl/data/settings_hub.php';
sl_render('SettingsHub', ['id' => 'general', 'title' => 'Settings', 'board' => 'SettingsHub'], sl_settingsHubData($user, $core));
