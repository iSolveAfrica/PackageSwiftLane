<?php
/** Channels & Teams (CsChannels.dc.html). Staff rank 2+; administrators (rank 3+) change settings. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/support_king.php';
sl_gate();
if (!cdp_csIsAgent($user)) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/support.php';
sl_render('CsChannels', ['id' => 'cschan', 'title' => 'Channels & Teams', 'board' => 'CsChannels', 'scripts' => ['dataJs/sl/cs_common.js']], sl_csChannelsData($user, $core));
