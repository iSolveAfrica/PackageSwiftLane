<?php
// Sea consolidations now live in the Consolidations page (Sea tab).
require_once 'loader.php';
$user = new User();
require_once 'helpers/sl_view.php';
sl_gate('view_consolidate_package_list');
header('Location: ' . sl_route('Consolidations', ['mode' => 'sea'] + array_intersect_key($_GET, ['q' => 1])));
exit;
