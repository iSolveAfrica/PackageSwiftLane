<?php
// Public: e-mail open pixel and click redirect for campaigns (helpers/message_tracking.php).
// ?e=open|click &b=batch &r=recipient (base64url) [&u=url (base64url)] &s=signature
require_once("loader.php");
require_once("helpers/message_tracking.php");
$e = ($_GET['e'] ?? '') === 'click' ? 'click' : 'open';
$batch = preg_match('/^[A-Za-z0-9_-]{1,40}$/', (string) ($_GET['b'] ?? '')) ? (string) $_GET['b'] : '';
$to = cdp_mtUnB64((string) ($_GET['r'] ?? ''));
$url = $e === 'click' ? cdp_mtUnB64((string) ($_GET['u'] ?? '')) : '';
$sig = (string) ($_GET['s'] ?? '');
$ok = $batch !== '' && $to !== '' && $sig !== '' && hash_equals(cdp_mtSig($e, $batch, $to, $url), $sig);
if ($e === 'click') {
    // only signed links redirect, and only to http(s): no open redirect
    if ($ok && preg_match('#^https?://#i', $url)) {
        cdp_mtRecord('click', $batch, $to, $url);
        header('Location: ' . $url, true, 302);
    } else {
        header('Location: index.php', true, 302);
    }
    exit;
}
if ($ok) cdp_mtRecord('open', $batch, $to);
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
