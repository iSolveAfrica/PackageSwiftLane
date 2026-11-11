<?php
// *************************************************************************
// * Inbound WhatsApp webhook (two-way support and the AI bot).            *
// *                                                                       *
// * Meta WhatsApp Business Cloud API                                      *
// *   GET  hub.mode=subscribe&hub.verify_token=…&hub.challenge=…          *
// *        answers the challenge when the verify token matches the one    *
// *        saved in Communication Center › Settings.                      *
// *   POST JSON signed with X-Hub-Signature-256 (the app secret).         *
// *        Unsigned or wrongly signed bodies are refused.                 *
// *                                                                       *
// * UltraMsg (or any provider without signatures)                         *
// *   POST to …/webhooks/whatsapp.php?key=<webhook key> — the key is the   *
// *   shared secret; a request without it is refused.                     *
// *                                                                       *
// * The provider gets its 200 straight away; the bot answers after the    *
// * response (helpers/after_response.php) so a slow AI never causes a     *
// * provider retry. Duplicate deliveries are ignored (provider message id).*
// *************************************************************************

require_once(dirname(__DIR__) . '/loader.php');
require_once(dirname(__DIR__) . '/helpers/querys.php');
require_once(dirname(__DIR__) . '/helpers/comms.php');
require_once(dirname(__DIR__) . '/helpers/after_response.php');

header('Cache-Control: no-store');
$s = cdp_commSettings();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $mode = (string) ($_GET['hub_mode'] ?? ($_GET['hub.mode'] ?? ''));
    $token = (string) ($_GET['hub_verify_token'] ?? ($_GET['hub.verify_token'] ?? ''));
    $challenge = (string) ($_GET['hub_challenge'] ?? ($_GET['hub.challenge'] ?? ''));
    if ($mode === 'subscribe' && $s['wa_cloud_verify'] !== '' && hash_equals((string) $s['wa_cloud_verify'], $token)) {
        header('Content-Type: text/plain');
        echo preg_replace('/[^A-Za-z0-9_\-]/', '', $challenge);
        exit;
    }
    http_response_code(403);
    exit;
}

$raw = (string) file_get_contents('php://input');
if (strlen($raw) > 1048576) {
    http_response_code(413);
    exit;
}
$sig = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
$key = (string) ($_GET['key'] ?? '');
$trusted = false;
if ($sig !== '') {
    $trusted = cdp_commVerifyMetaSignature($raw, $sig, (string) $s['wa_cloud_app_secret']);
} elseif ($key !== '' && $s['webhook_key'] !== '') {
    $trusted = hash_equals((string) $s['webhook_key'], $key);
}
if (!$trusted) {
    error_log('[comms webhook] refused: bad or missing signature/key from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    http_response_code(401);
    exit;
}

$j = json_decode($raw, true);
$messages = is_array($j) ? cdp_commParseInbound($j) : [];

http_response_code(200);
header('Content-Type: application/json');
echo '{"ok":true}';

if ($messages) {
    cdp_afterResponse(function () use ($messages) {
        foreach (array_slice($messages, 0, 20) as $m) {
            try {
                cdp_commHandleInbound($m);
            } catch (Throwable $e) {
                error_log('[comms webhook] ' . $e->getMessage());
            }
        }
    });
}
