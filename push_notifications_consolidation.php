<?php
/** Push to the package owners of one consolidation: the Broadcast page with the consolidation preselected. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('push_notifications');
$_GET['type'] = 'consolidation';
require_once 'views/sl/data/broadcast.php';
sl_render('Broadcast', ['id' => 'bcast', 'title' => 'Push to a consolidation', 'board' => 'Broadcast'], sl_broadcastData($user, $core));
