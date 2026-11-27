<?php
/**
 * Data for the WhatsApp screen (whatsapp_web.php, views/sl/pages/WhatsAppWeb.php).
 * The page itself loads fast: connection state, chats and messages are fetched
 * by dataJs/sl/whatsapp_web.js from ajax/comms/wa_web_ajax.php, so a slow
 * provider never holds the page.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_routes.php';
require_once CDP_ROOT . '/helpers/ultramsg.php';

/** Staff (rank 2+) open the screen; administrators (rank 3+) link the phone and export. */
function sl_waWebAllowed(): bool
{
    return cdp_opsRank() >= 2;
}

/** King availability for the reply suggestions (never throws). */
function sl_waWebAi($user): array
{
    $king = function_exists('cdp_kingName') ? cdp_kingName() : 'King';
    $out = ['ok' => false, 'code' => 'not_configured', 'message' => $king . ' is not set up yet.', 'setup_url' => '', 'assistant' => $king];
    if (!is_file(CDP_ROOT . '/helpers/comms_ai.php')) {
        return $out;
    }
    try {
        require_once CDP_ROOT . '/helpers/comms_ai.php';
        return cdp_commAiAvailable($user) + $out;
    } catch (Throwable $e) {
        return $out;
    }
}

function sl_waWebData($user): array
{
    $ready = cdp_umEnsureTables();
    $cfg = cdp_umConfig();
    $admin = cdp_opsRank() >= 3;
    require_once CDP_ROOT . '/helpers/comms.php';
    return [
        'ready' => $ready,
        'isAdmin' => $admin,
        'configured' => $cfg['configured'],
        'active' => $cfg['active'],
        'instance' => $cfg['instance'],
        'king' => function_exists('cdp_kingName') ? cdp_kingName() : 'King',
        'ai' => sl_waWebAi($user),
        'botEnabled' => cdp_commSetting('bot_enabled') === '1',
        'me' => cdp_opsUid(),
        'urls' => [
            'ajax' => 'ajax/comms/wa_web_ajax.php',
            'inbox' => sl_route('CommsInbox'),
            'send' => sl_route('CommsSend') . '?mode=campaign',
            'settings' => sl_route('CommsSettings'),
            'messaging' => 'config_whatsapp.php',
            'home' => sl_waWebNavAllowed(['push_notifications', 'manage_newsletter', 'view_message_logs']) ? sl_route('CommsHub') : sl_route('CommsInbox'),
        ],
    ];
}

function sl_waWebNavAllowed(array $perms): bool
{
    require_once CDP_ROOT . '/helpers/sl_nav.php';
    return function_exists('sl_navAllowed') ? (bool) sl_navAllowed($perms, []) : false;
}
