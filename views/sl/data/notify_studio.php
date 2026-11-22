<?php
/**
 * Data for the Notification Studio (views/sl/pages/NotifyStudio.php).
 * Content and send-time logic: helpers/notify_studio.php.
 */
require_once CDP_ROOT . '/helpers/notify_studio.php';
require_once CDP_ROOT . '/helpers/comms.php';

/** What each channel of a status sends while nothing is saved in the studio. */
function sl_notifyStudioBuiltin(string $kind, string $channel): string
{
    if ($kind === 'action') {
        return 'The built-in "shipment registered" WhatsApp template.';
    }
    return [
        'email' => 'The shared status e-mail (E-mail templates: shipment tracking update), the same for every status.',
        'sms' => 'The status text from the SMS templates.',
        'whatsapp' => 'The standard WhatsApp status update.',
        'inapp' => 'The standard "shipment updated" bell notification.',
    ][$channel] ?? '';
}

/** Builder blocks for a Communication Center template that was written as plain text. */
function sl_notifyStudioTextToBlocks(string $subject, string $text): array
{
    return [
        ['type' => 'header', 'text' => '{company}'],
        ['type' => 'heading', 'text' => $subject !== '' ? $subject : 'An update from {company}'],
        ['type' => 'text', 'text' => str_replace('*', '', $text)],
        ['type' => 'footer', 'text' => 'You receive this message because you have an account with {company}.'],
    ];
}

function sl_notifyStudioData($user, $core): array
{
    $canEdit = $user->cdp_hasPermission(['manage_notification_rules', 'view_tools']);
    $canComms = function_exists('cdp_opsRank') && cdp_opsRank() >= 3;
    $commsReady = cdp_commEnsureTables();
    $commsOn = $commsReady && !empty(cdp_commSetting('enabled'));
    $saved = cdp_notifyAll();
    $rules = $commsReady ? cdp_commRules() : [];
    $channels = cdp_notifyChannels();
    $q = trim((string) ($_GET['q'] ?? ''));
    $match = function (string $label) use ($q) {
        return $q === '' || mb_stripos($label, $q) !== false;
    };
    $state = function (string $key, string $ch) use ($saved) {
        $r = $saved[$key][$ch] ?? null;
        return !$r ? 'builtin' : ((int) $r->enabled === 1 ? 'custom' : 'off');
    };

    // ---- the three groups -------------------------------------------------
    $catalogue = [];
    $statusItems = [];
    foreach (cdp_notifyStatuses() as $s) {
        $key = 'status:' . (int) $s->id;
        $label = ucfirst(trim(str_replace('_', ' ', (string) $s->mod_style)));
        $catalogue[$key] = ['kind' => 'status', 'label' => $label, 'color' => (string) $s->color, 'channels' => array_keys($channels),
            'desc' => 'Sent to the customer when an air shipment or a sea package is moved to "' . $label . '", one at a time or in a bulk update.'];
        if ($match($label)) {
            $statusItems[] = $key;
        }
    }
    $actionItems = [];
    foreach (cdp_notifyActions() as $key => $a) {
        $catalogue[$key] = ['kind' => 'action', 'label' => $a['label'], 'color' => '', 'channels' => $a['channels'], 'desc' => $a['desc'], 'vars' => $a['vars']];
        if ($match($a['label'])) {
            $actionItems[] = $key;
        }
    }
    $commsItems = [];
    if ($commsReady) {
        foreach (cdp_commEvents() as $ev => $e) {
            $key = 'comms:' . $ev;
            $catalogue[$key] = ['kind' => 'comms', 'label' => $e['label'], 'color' => '', 'channels' => ['email', 'sms', 'whatsapp'],
                'desc' => $e['help'] . ' Sent by the Communication Center automations' . ($commsOn ? '.' : ', which are switched off at the moment.'), 'event' => $ev];
            if ($match($e['label'])) {
                $commsItems[] = $key;
            }
        }
    }
    $item = function (string $key) use ($catalogue, $channels, $state, $rules) {
        $c = $catalogue[$key];
        $states = [];
        foreach (array_keys($channels) as $ch) {
            if (!in_array($ch, $c['channels'], true)) {
                $states[$ch] = 'na';
            } elseif ($c['kind'] === 'comms') {
                $states[$ch] = !empty($rules[$c['event']][$ch]) ? 'on' : 'off';
            } else {
                $states[$ch] = $state($key, $ch);
            }
        }
        return ['key' => $key, 'label' => $c['label'], 'color' => $c['color'], 'states' => $states,
            'href' => sl_url(['t' => $key, 'ch' => null])];
    };
    $groups = [];
    foreach ([
        ['Statuses', 'Shipments and packages', $statusItems],
        ['Actions', 'Things people do on the platform', $actionItems],
        ['Automations', 'Communication Center', $commsItems],
    ] as [$label, $sub, $keys]) {
        if ($keys) {
            $groups[] = ['label' => $label, 'sub' => $sub, 'items' => array_map($item, $keys)];
        }
    }
    $customCount = 0;
    $offCount = 0;
    foreach ($saved as $k => $byCh) {
        if (strpos($k, 'comms:') === 0) {
            continue;
        }
        foreach ($byCh as $r) {
            (int) $r->enabled === 1 ? $customCount++ : $offCount++;
        }
    }

    // ---- the selected trigger ----------------------------------------------
    $curKey = (string) ($_GET['t'] ?? '');
    if (!isset($catalogue[$curKey])) {
        $curKey = $statusItems[0] ?? (array_key_first($catalogue) ?? '');
    }
    $cur = $curKey !== '' ? $catalogue[$curKey] + ['key' => $curKey] : null;
    $editor = [];
    $vars = [];
    if ($cur) {
        $rows = cdp_notifyGet($curKey);
        foreach (array_keys($channels) as $ch) {
            if (!in_array($ch, $cur['channels'], true)) {
                $editor[$ch] = ['supported' => false];
                continue;
            }
            if ($cur['kind'] === 'comms') {
                $tpl = cdp_commTemplate($cur['event'], $ch);
                $subject = (string) ($tpl->subject ?? '');
                $body = (string) ($tpl->body ?? '');
                $blocks = [];
                if ($ch === 'email') {
                    $savedBlocks = isset($rows['email']) ? json_decode((string) $rows['email']->blocks, true) : null;
                    $blocks = is_array($savedBlocks) && $savedBlocks ? $savedBlocks : sl_notifyStudioTextToBlocks($subject, strpos($body, CDP_NOTIFY_HTML_MARK) === 0 ? '' : $body);
                }
                $on = !empty($rules[$cur['event']][$ch]);
                $editor[$ch] = ['supported' => true, 'mode' => $on ? 'on' : 'off', 'enabled' => $on, 'subject' => $subject, 'body' => $body,
                    'blocks' => $blocks, 'builtin' => '', 'updated' => $tpl && !empty($tpl->updated_at) ? sl_date($tpl->updated_at, 'M j, Y') : ''];
                continue;
            }
            $r = $rows[$ch] ?? null;
            $def = cdp_notifyDefault($curKey, $ch);
            $blocks = $r && $r->blocks ? json_decode((string) $r->blocks, true) : null;
            $editor[$ch] = [
                'supported' => true,
                'mode' => !$r ? 'builtin' : ((int) $r->enabled === 1 ? 'custom' : 'off'),
                'enabled' => $r ? (int) $r->enabled === 1 : true,
                'subject' => $r && $r->subject !== null ? (string) $r->subject : $def['subject'],
                'body' => $r && $ch !== 'email' ? (string) $r->body : $def['body'],
                'blocks' => is_array($blocks) && $blocks ? $blocks : $def['blocks'],
                'builtin' => sl_notifyStudioBuiltin($cur['kind'], $ch),
                'updated' => $r ? sl_date($r->updated_at, 'M j, Y') : '',
            ];
        }
        if ($cur['kind'] === 'comms') {
            foreach (cdp_commPlaceholders() as $p) {
                $vars[] = ['key' => $p, 'desc' => ucfirst(str_replace('_', ' ', $p))];
            }
        } else {
            foreach (cdp_notifyPlaceholders() as $k => [$desc, $scope]) {
                if ($scope === 'all' || ($scope === 'status' && $cur['kind'] === 'status') || ($scope === 'action' && in_array($k, $cur['vars'] ?? [], true))) {
                    $vars[] = ['key' => $k, 'desc' => $desc];
                }
            }
        }
    }
    $ch = (string) ($_GET['ch'] ?? '');
    if (!$cur || !in_array($ch, $cur['channels'], true)) {
        $ch = $cur ? $cur['channels'][0] : 'email';
    }
    return [
        'canEdit' => $canEdit, 'canComms' => $canComms, 'commsOn' => $commsOn,
        'groups' => $groups, 'q' => $q, 'total' => count($catalogue), 'customCount' => $customCount, 'offCount' => $offCount,
        'cur' => $cur, 'picked' => $cur !== null && (string) ($_GET['t'] ?? '') === $cur['key'], 'editor' => $editor, 'channel' => $ch, 'channels' => $channels, 'vars' => $vars,
        'blockTypes' => cdp_notifyBlockTypes(), 'sample' => cdp_notifySampleVars(),
        'limits' => cdp_notifyLimits(),
    ];
}
