<?php
/** Compose message: send e-mail, SMS or WhatsApp to a customer, a segment or a list. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('push_notifications');
require_once 'views/sl/data/compose.php';
require_once 'views/sl/data/msg_common.php';
require_once 'helpers/push_compose.php';
$pre = [];
foreach (array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? $_GET['id'] ?? '')))) as $id) {
    $u = sl_row('SELECT id, fname, lname FROM cdb_users WHERE id = :id AND userlevel = 1', [':id' => $id]);
    if ($u) $pre[] = ['id' => (int) $u->id, 'name' => trim($u->fname . ' ' . $u->lname)];
}
$segs = [];
foreach (sl_composeSegments() as $k => $s) {
    $segs[] = ['k' => $k, 'label' => $s['label'], 'n' => count(sl_rows($s['sql']))];
}
$tpls = [];
foreach (['whatsapp', 'sms', 'email'] as $ch) {
    foreach (sl_msgTemplates($ch) as $t) {
        $tpls[$ch][] = ['id' => $t['id'], 'name' => $t['name'], 'subject' => $t['subject'], 'body' => $ch === 'email' ? sl_msgPlain($t['body']) : $t['body']];
    }
}
$health = sl_msgChannelHealth();
sl_render('ComposeMessage', ['id' => 'commshub', 'title' => 'Send a message', 'board' => 'ComposeMessage'], [
    'pre' => $pre, 'segments' => $segs, 'segment' => (string) ($_GET['segment'] ?? ''), 'channel' => in_array($_GET['channel'] ?? '', ['whatsapp', 'sms', 'email'], true) ? $_GET['channel'] : 'whatsapp',
    'templates' => $tpls, 'rates' => cdp_pushCostRates(), 'health' => $health, 'sample' => sl_msgSample(),
]);
