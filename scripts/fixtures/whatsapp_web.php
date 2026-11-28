<?php
// Isolated rendering of the WhatsApp screen template (views/sl/pages/WhatsAppWeb.php)
// for scripts/check_whatsapp_web.cjs; no loader, database or provider calls.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
define('CDP_ROOT', dirname(__DIR__, 2));
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
$admin = ($argv[1] ?? 'admin') === 'admin';
$d = ['ready' => true, 'isAdmin' => $admin, 'configured' => true, 'active' => true, 'instance' => 'instance1', 'king' => 'King',
    'ai' => ['ok' => false, 'code' => 'not_configured', 'message' => 'King is not set up yet.', 'setup_url' => ''], 'botEnabled' => false, 'me' => 1,
    'urls' => ['ajax' => 'ajax/comms/wa_web_ajax.php', 'inbox' => 'comms_inbox.php', 'send' => 'comms_send.php?mode=campaign', 'settings' => 'comms_settings.php',
        'messaging' => 'config_whatsapp.php', 'home' => 'comms_hub.php']];
?>
<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>WhatsApp screen fixture</title>
<meta name="csrf-token" content="fixture-token"><meta name="csrf-param" content="csrf_token">
<link rel="stylesheet" href="/assets/swiftlane/swiftlane.css"><link rel="stylesheet" href="/assets/swiftlane/pages/ops.css"><link rel="stylesheet" href="/assets/swiftlane/pages/WhatsAppWeb.css">
<div id="sl-app" class="sl-app"><main style="padding:24px">
<?php
// The real template; only the Communications tabs (they need the signed-in user's menu) are left out.
$src = (string) file_get_contents(CDP_ROOT . '/views/sl/pages/WhatsAppWeb.php');
$src = preg_replace('#<\?php \$commsTab = [^?]*\?>#', '', $src, 1, $n);
if ($n !== 1) { fwrite(STDERR, "comms tabs include not found\n"); exit(1); }
eval('?>' . $src);
?>
</main></div>
<div id="sl-toast" class="sl-toast" role="status" aria-live="polite" hidden></div>
<script src="/assets/swiftlane/swiftlane.js"></script>
<script src="/dataJs/sl/whatsapp_web.js"></script>
