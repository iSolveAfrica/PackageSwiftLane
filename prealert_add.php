<?php
// The add form is part of the PreAlerts page now; this URL opens it.
require_once 'loader.php';
$user = new User();
require_once 'helpers/sl_view.php';
sl_gate('prealert_add');
header('Location: prealert_list.php?new=1');
exit;
