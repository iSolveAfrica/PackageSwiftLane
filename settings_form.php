<?php
/** Single-record settings forms in the Swift Lane design (helpers/sl_settings_forms.php). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/sl_settings_forms.php';
require_once 'helpers/sl_catalogs.php';
$forms = sl_settingsForms();
$key = (string) ($_GET['f'] ?? '');
if (!isset($forms[$key])) {
    header('Location: tools.php');
    exit;
}
$form = $forms[$key];
sl_gate($form['perm']);
$values = [];
if ($form['source'] === 'info') {
    $row = sl_row('SELECT * FROM cdb_info_ship_default ORDER BY id');
    foreach ($form['fields'] as $k => $f) {
        $values[$k] = $row->$k ?? '';
    }
} else {
    foreach ($form['fields'] as $k => $f) {
        $values[$k] = $core->$k ?? '';
    }
}
$siblings = array_filter($forms, fn($f) => $user->cdp_hasPermission($f['perm']));
sl_render('SettingsForm', ['id' => 'general', 'title' => $form['title'], 'board' => 'SettingsForm'], ['key' => $key, 'form' => $form, 'values' => $values, 'siblings' => $siblings]);
