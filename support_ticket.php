<?php
/** One support ticket (CsTicket.dc.html). Staff rank 2+. */
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
$id = (int) ($_GET['id'] ?? 0);
if (!$id && !empty($_GET['ref'])) {
    $t = cdp_csTicketByRef((string) $_GET['ref']);
    $id = $t ? (int) $t->id : 0;
}
if (!$id) {
    header('Location: ' . sl_route('CsInbox', ['view' => 'mine']));
    exit;
}
$d = sl_csTicketData($user, $core, $id);
sl_render('CsTicket', ['id' => 'tickets', 'title' => $d['ticket'] ? $d['ticket']['ref'] . ' · ' . $d['ticket']['subject'] : 'Ticket', 'board' => 'CsTicket', 'wide' => true, 'scripts' => ['dataJs/sl/cs_common.js', 'dataJs/sl/cs_ticket.js']], $d);
