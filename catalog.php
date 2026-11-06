<?php
/** Lookup lists manager (Swift Lane design): one page for every reference table in helpers/sl_catalogs.php. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/sl_catalogs.php';
$key = (string) ($_GET['c'] ?? '');
$cat = sl_catalog($key);
if (!$cat) {
    header('Location: tools.php');
    exit;
}
sl_gate($cat['perm']['view']);
require_once 'views/sl/data/catalog.php';
sl_render('Catalog', ['id' => 'general', 'title' => $cat['title'], 'board' => 'Catalog'], sl_catalogData($user, $key, $cat));
