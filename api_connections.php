<?php
/** API connections: payments, messages, AI, maps and couriers in one place (secrets masked). ?new=1 shows the catalog (NewIntegration). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('manage_api_settings');
require_once 'views/sl/data/api_connections.php';
$d = sl_apiConnectionsData($user, $core);
if (isset($_GET['new'])) {
    sl_render('NewIntegration', ['id' => 'apis', 'title' => 'Add an integration', 'board' => 'NewIntegration'], $d);
} else {
    sl_render('ApiConnections', ['id' => 'apis', 'title' => 'API connections', 'board' => 'ApiConnections'], $d);
}
