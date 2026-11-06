<?php
/** Support inbox (CsInbox.dc.html). Staff rank 2+. */
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
sl_render('CsInbox', ['id' => 'tickets', 'title' => 'Support Inbox', 'board' => 'CsInbox', 'scripts' => ['dataJs/sl/cs_common.js', 'dataJs/sl/cs_inbox.js']], sl_csInboxData($user, $core));
