<?php
require_once '../../../loader.php';
require_once __DIR__ . '/../../../helpers/ajax_guard.php';
require_login();
http_response_code(410);
exit('Use Settings > Database import to preview and import a SQL file.');
