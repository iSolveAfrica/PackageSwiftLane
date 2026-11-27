<?php
/**
 * Data for the Send page (comms_send.php, views/sl/pages/CommsSend.php):
 * one person, several people or a campaign, by WhatsApp, SMS, e-mail and
 * the in-app bell. Sending: ajax/comms/send_ajax.php (helpers/comms_campaigns.php).
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/comms.php';
require_once CDP_ROOT . '/helpers/comms_campaigns.php';
require_once CDP_ROOT . '/helpers/comms_ai.php';
require_once CDP_ROOT . '/helpers/push_compose.php';
require_once __DIR__ . '/msg_common.php';

function sl_commsSendData($user, $core): array
{
    $ready = cdp_commEnsureTables();
    $rank = cdp_opsRank();
    $canCampaign = $user->cdp_hasPermission(['push_notifications', 'manage_newsletter']);
    $settings = cdp_getSettingsCourier();
    $mode = in_array($_GET['mode'] ?? '', ['one', 'several', 'campaign'], true) ? (string) $_GET['mode'] : '';
    $channel = in_array($_GET['channel'] ?? '', ['whatsapp', 'sms', 'email', 'inapp'], true) ? (string) $_GET['channel'] : '';

    // Old links: compose_message.php?ids=&id=&segment=&channel=, newsletter.php?email=, push_notifications.php?user_id=
    $pre = [];
    $ids = array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? $_GET['id'] ?? $_GET['user_id'] ?? ''))));
    if ($ids) {
        $roles = implode(',', array_map('intval', cdp_commClientRoles()));
        foreach (cdp_opsQ("SELECT id, fname, lname, locker, phone, email, city FROM cdb_users WHERE active = 1 AND userlevel IN ($roles) AND id IN (" . implode(',', array_slice($ids, 0, 300)) . ")") as $u) {
            $pre[] = ['id' => (int) $u->id, 'name' => trim($u->fname . ' ' . $u->lname) ?: 'Customer', 'locker' => (string) $u->locker, 'phone' => (string) $u->phone,
                'email' => (string) $u->email, 'city' => (string) $u->city];
        }
    }
    $list = '';
    if (!empty($_GET['email'])) {
        require_once CDP_ROOT . '/helpers/newsletter.php';
        $list = implode(', ', cdp_nlParseEmails((string) $_GET['email'])['valid']);
    }
    $presets = cdp_cmpPresets();
    $segment = (string) ($_GET['segment'] ?? '');
    $base = isset($presets[$segment]) ? $segment : 'all';
    $cons = preg_match('/^(air|sea):\d+$/', (string) ($_GET['consolidation'] ?? '')) ? (string) $_GET['consolidation'] : '';
    if ($mode === '') {
        $mode = $pre ? (count($pre) > 1 ? 'several' : 'one') : ($list !== '' ? 'several' : (($segment !== '' || $cons !== '') && $canCampaign ? 'campaign' : 'one'));
    }
    if ($mode === 'campaign' && !$canCampaign) {
        $mode = 'several';
    }
    $sampleU = (object) ['id' => 0, 'fname' => 'Ama', 'lname' => 'Mensah', 'locker' => 'SW1024'];
    $sample = $ready ? cdp_commBaseVars($sampleU) : [];
    $sample['prefs_url'] = cdp_opsSiteUrl() . '/comms_preferences.php?t=…';
    $ai = cdp_commAiAvailable($user);
    $health = sl_msgChannelHealth();
    $health['inapp'] = ['label' => 'In-app bell', 'ok' => true, 'pill' => 'ok', 'state' => 'Always on'];
    $presetList = [];
    foreach ($presets as $k => $p) {
        $presetList[] = ['k' => $k, 'label' => $p['label']];
    }
    $nl = $user->cdp_hasPermission('manage_newsletter');
    return [
        'ready' => $ready,
        'rank' => $rank,
        'canCampaign' => $canCampaign,
        'isAdmin' => $rank >= 3,
        'needsApproval' => cdp_cmpNeedsApproval(),
        'selfApprove' => $rank >= 4 || ($rank >= 3 && !cdp_cmpNeedsApproval()),
        'mode' => $mode,
        'channel' => $channel,
        'pre' => $pre,
        'list' => $list,
        'audience' => ['base' => $cons !== '' ? 'consolidation' : $base, 'consolidation' => $cons],
        'edit' => (int) ($_GET['campaign'] ?? 0),
        'presets' => $presetList,
        'consolidations' => $canCampaign ? cdp_cmpConsolidations() : [],
        'fields' => cdp_cmpFilterFields(),
        'contacts' => ['ready' => (bool) cdp_cmpContactsInfo(), 'sources' => $canCampaign ? cdp_cmpContactSources() : []],
        'tags' => cdp_commBasePlaceholders(),
        'sample' => $sample,
        'health' => $health,
        'rates' => cdp_pushCostRates(),
        'waCloud' => $ready && cdp_commSetting('wa_provider') === 'cloud',
        'quiet' => $ready ? [cdp_commSetting('quiet_start'), cdp_commSetting('quiet_end')] : ['', ''],
        'ai' => ['ok' => $ai['ok'], 'message' => $ai['message'], 'setup' => $ai['setup_url'], 'name' => $ai['assistant']],
        'campaigns' => $ready && $canCampaign ? cdp_cmpList() : [],
        'statuses' => cdp_cmpStatuses(),
        'reasons' => cdp_cmpReasons(),
        'directMax' => CDP_CMP_DIRECT_MAX,
        'media' => $user->cdp_hasPermission('push_notifications') ? 'ajax/tools/push_media_upload_ajax.php' : '',
        'canvas' => [
            'ajax' => $nl ? 'ajax/tools/newsletter_ajax.php' : null,
            'siteUrl' => rtrim((string) ($settings->site_url ?? ''), '/'),
            'siteName' => (string) ($core->site_name ?? ''),
            'logo' => !empty($core->logo) ? 'assets/' . $core->logo : '',
        ],
        'senderSms' => function_exists('cdp_smsSenderId') ? (string) cdp_smsSenderId() : (string) ($core->site_name ?? 'SWIFTLANE'),
        'reportUrl' => 'campaigns.php',
        'outboxUrl' => sl_route('CommsOutbox'),
        'consentUrl' => sl_route('CommsCustomers'),
        'settingsUrl' => sl_route('CommsSettings'),
        'waUrl' => sl_route('CommsInbox'),
    ];
}
