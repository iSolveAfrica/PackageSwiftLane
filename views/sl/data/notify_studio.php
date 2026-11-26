<?php
/**
 * Data for the Notification Studio (views/sl/pages/NotifyStudio.php).
 * Content and send-time logic: helpers/notify_studio.php; the drafted message
 * of every status: helpers/notify_drafts.php.
 */
require_once CDP_ROOT . '/helpers/notify_studio.php';
require_once CDP_ROOT . '/helpers/comms.php';

/** What each channel of a trigger sends while nothing is saved in the studio. */
function sl_notifyStudioBuiltin(string $kind, string $channel, bool $draftsOn = true): string
{
    if ($kind === 'action') {
        return 'The built-in "shipment registered" WhatsApp template.';
    }
    if ($kind === 'status' && $draftsOn) {
        return 'The message drafted for this status, shown below.';
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

/**
 * Who else may message the customer when a status is set: the Communication
 * Center automation the status is matched to (helpers/comms.php, read only)
 * and the admin-built Automations (helpers/automations.php) for status changes.
 *
 * @return array{event:string,eventLabel:string,how:string,fits:bool,commsChannels:array,autos:array,double:array}
 */
function sl_notifyStudioConflicts(int $statusId, string $name, array $ctx): array
{
    $events = $ctx['events'];
    $event = $ctx['ready'] ? (string) cdp_commClassifyStatus($statusId, $name, $ctx['map']) : '';
    $how = isset($ctx['map'][$statusId]) ? 'set by an administrator in the Communication Center' : 'matched automatically from the status name';
    $kind = cdp_notifyDraftKind($name);
    $fits = in_array($event, cdp_notifyDraftCommsFit($kind), true);
    $commsChannels = [];
    if ($ctx['commsOn'] && $event !== 'none') {
        $own = $event !== '' ? array_keys(array_filter($ctx['rules'][$event] ?? [])) : [];
        $alert = array_keys(array_filter($ctx['rules']['shipment_alert'] ?? []));
        foreach ($own as $ch) {
            $commsChannels[$ch] = $events[$event]['label'] ?? $event;
        }
        foreach (array_diff($alert, $own) as $ch) {
            $commsChannels[$ch] = $events['shipment_alert']['label'] ?? 'Shipment status alert';
        }
    }
    $autos = [];
    foreach ($ctx['autos'] as $a) {
        $match = true;
        foreach ($a['conditions'] as $c) {
            if (($c['key'] ?? '') === 'status_is') {
                $want = array_map('intval', is_array($c['value'] ?? '') ? $c['value'] : explode(',', (string) ($c['value'] ?? '')));
                $match = in_array($statusId, $want, true);
            }
        }
        if (!$match) {
            continue;
        }
        $chs = [];
        foreach ($a['actions'] as $act) {
            $t = (string) ($act['type'] ?? '');
            $map = ['email' => 'email', 'sms' => 'sms', 'whatsapp' => 'whatsapp', 'in_app' => 'inapp'];
            if (isset($map[$t])) {
                $chs[] = $map[$t];
            }
        }
        if ($chs) {
            $autos[] = ['name' => (string) $a['name'], 'channels' => array_values(array_unique($chs))];
        }
    }
    $double = array_keys($commsChannels);
    foreach ($autos as $a) {
        $double = array_merge($double, $a['channels']);
    }
    return ['event' => $event, 'eventLabel' => $event === '' ? 'Shipment status alert (generic)' : ($event === 'none' ? 'Nothing (never announced)' : ($events[$event]['label'] ?? $event)),
        'how' => $how, 'fits' => $fits, 'commsChannels' => $commsChannels, 'autos' => $autos, 'double' => array_values(array_unique($double))];
}

function sl_notifyStudioData($user, $core): array
{
    $canEdit = $user->cdp_hasPermission(['manage_notification_rules', 'view_tools']);
    $canComms = function_exists('cdp_opsRank') && cdp_opsRank() >= 3;
    $canGlobal = $canEdit && $canComms;   // the switch for every status and customer: administrators only
    $commsReady = cdp_commEnsureTables();
    $commsOn = $commsReady && !empty(cdp_commSetting('enabled'));
    $draftsOn = cdp_notifyDraftsOn();
    $saved = cdp_notifyAll();
    $rules = $commsReady ? cdp_commRules() : [];
    $channels = cdp_notifyChannels();
    $q = trim((string) ($_GET['q'] ?? ''));
    $match = function (string $label) use ($q) {
        return $q === '' || mb_stripos($label, $q) !== false;
    };

    // AI assistant (King): may this user ask it for a rewrite here?
    require_once CDP_ROOT . '/helpers/ai_assistant.php';
    $king = function_exists('cdp_kingName') ? cdp_kingName() : 'King';
    $aiConfigured = cdp_aiConfigured();
    $aiCanUse = cdp_aiCanUse($user);
    $ai = ['name' => $king, 'ready' => $aiConfigured && $aiCanUse, 'configured' => $aiConfigured, 'canUse' => $aiCanUse,
        'canManage' => cdp_aiCanManage($user), 'setupUrl' => sl_route('AiCenter'),
        'why' => !$aiConfigured ? $king . ' is not set up yet. A super admin switches it on and adds an AI provider key in the AI centre.'
            : (!$aiCanUse ? 'Your role cannot use ' . $king . '. The AI centre sets who may use it.' : ''),
        'tones' => array_map(function ($t) { return $t[0]; }, cdp_notifyAiTones())];

    // Who else messages the customer on a status change (read only).
    $autos = [];
    if (is_file(CDP_ROOT . '/helpers/automations.php')) {
        try {
            require_once CDP_ROOT . '/helpers/automations.php';
            if (function_exists('cdp_automationsAll')) {
                $autos = array_merge(cdp_automationsAll(true, 'shipment.status'), cdp_automationsAll(true, 'package.status'));
            }
        } catch (Throwable $e) {
            error_log('notify studio automations: ' . $e->getMessage());
        }
    }
    $conflictCtx = ['ready' => $commsReady, 'commsOn' => $commsOn, 'rules' => $rules, 'events' => $commsReady ? cdp_commEvents() : [],
        'map' => $commsReady ? cdp_opsDecode(cdp_commSetting('status_map'), []) : [], 'autos' => $autos];

    $state = function (string $key, string $ch) use ($saved) {
        return cdp_notifyChannelState($key, $saved[$key][$ch] ?? null);
    };

    // ---- the three groups -------------------------------------------------
    $catalogue = [];
    $statusItems = [];
    $kindLabels = cdp_notifyDraftKindLabels();
    foreach (cdp_notifyStatuses() as $s) {
        $key = 'status:' . (int) $s->id;
        $label = cdp_notifyDraftLabel((string) $s->mod_style);
        $kind = cdp_notifyDraftKind((string) $s->mod_style);
        $conf = sl_notifyStudioConflicts((int) $s->id, (string) $s->mod_style, $conflictCtx);
        $catalogue[$key] = ['kind' => 'status', 'label' => $label, 'color' => (string) $s->color, 'channels' => array_keys($channels),
            'statusName' => (string) $s->mod_style, 'draftKind' => $kind, 'draftKindLabel' => $kindLabels[$kind] ?? '',
            'meaning' => cdp_notifyDraftSpec((string) $s->mod_style)['meaning'], 'conflicts' => $conf,
            'desc' => 'Sent to the customer when an air shipment or a sea package is moved to "' . $label . '".'];
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
        $warn = '';
        if (!empty($c['conflicts'])) {
            $cf = $c['conflicts'];
            if ($cf['double']) {
                $warn = 'May send twice: the Communication Center or an automation also messages the customer for this status.';
            } elseif (!$cf['fits']) {
                $warn = 'The Communication Center matches this status to "' . $cf['eventLabel'] . '", which looks wrong for it.';
            }
        }
        return ['key' => $key, 'label' => $c['label'], 'color' => $c['color'], 'states' => $states, 'warn' => $warn,
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
    $statusKeys = [];
    foreach ($catalogue as $k => $c) {
        if ($c['kind'] === 'status') {
            $statusKeys[] = ['key' => $k, 'label' => $c['label'], 'custom' => !empty($saved[$k])];
        }
    }
    foreach ($saved as $k => $byCh) {
        if (strpos($k, 'comms:') === 0) {
            continue;
        }
        foreach ($byCh as $r) {
            (int) $r->enabled === 1 ? $customCount++ : $offCount++;
        }
    }
    $draftedCount = 0;
    foreach ($statusKeys as $sk) {
        foreach (array_keys($channels) as $ch) {
            if (!isset($saved[$sk['key']][$ch])) {
                $draftedCount++;
            }
        }
    }
    $warnCount = 0;
    foreach ($catalogue as $c) {
        if (!empty($c['conflicts']) && ($c['conflicts']['double'] || !$c['conflicts']['fits'])) {
            $warnCount++;
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
    $sample = cdp_notifySampleVars($cur && $cur['kind'] === 'status' ? $cur['statusName'] : null);
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
                $def = cdp_commDefaultTemplates()[$cur['event']] ?? null;
                $defBody = $def ? ($ch === 'sms' ? $def['sms'] : ($ch === 'email' ? str_replace('*', '', $def['text']) : $def['text'])) : '';
                $editor[$ch] = ['supported' => true, 'mode' => $on ? 'on' : 'off', 'enabled' => $on, 'subject' => $subject, 'body' => strpos($body, CDP_NOTIFY_HTML_MARK) === 0 ? '' : $body,
                    'blocks' => $blocks, 'builtin' => '', 'updated' => $tpl && !empty($tpl->updated_at) ? sl_date($tpl->updated_at, 'M j, Y') : '',
                    'draft' => ['subject' => $def['subject'] ?? '', 'body' => $defBody, 'blocks' => $def ? sl_notifyStudioTextToBlocks($def['subject'], $defBody) : []]];
                continue;
            }
            $r = $rows[$ch] ?? null;
            $def = cdp_notifyDefault($curKey, $ch);
            $blocks = $r && $r->blocks ? json_decode((string) $r->blocks, true) : null;
            $mode = cdp_notifyChannelState($curKey, $r);
            $isOn = !$r || (int) $r->enabled === 1;
            $editor[$ch] = [
                'supported' => true,
                'mode' => $mode,
                'enabled' => $isOn,
                'subject' => $r && $r->subject !== null && $r->subject !== '' ? (string) $r->subject : $def['subject'],
                'body' => $r && $ch !== 'email' && trim((string) $r->body) !== '' ? (string) $r->body : $def['body'],
                'blocks' => is_array($blocks) && $blocks ? $blocks : $def['blocks'],
                'builtin' => sl_notifyStudioBuiltin($cur['kind'], $ch, $draftsOn),
                'updated' => $r ? sl_date($r->updated_at, 'M j, Y') : '',
                'draft' => $def,
            ];
        }
        if ($cur['kind'] === 'comms') {
            foreach (cdp_commPlaceholders() as $p) {
                $vars[] = ['key' => $p, 'desc' => ucfirst(str_replace('_', ' ', $p))];
            }
            $sample = cdp_notifyCommsSampleVars($cur['event'], $sample);
        } else {
            // A status only offers the fields it fills: the pickup code where a code
            // is issued, the payment link and amount where payment is asked (the
            // same rules King follows). The others are shown greyed out, with why.
            $curKind = $cur['kind'] === 'status' ? $cur['draftKind'] : '';
            foreach (cdp_notifyPlaceholders() as $k => [$desc, $scope]) {
                if ($scope === 'all' || ($scope === 'status' && $cur['kind'] === 'status') || ($scope === 'action' && in_array($k, $cur['vars'] ?? [], true))) {
                    $off = '';
                    if ($curKind !== '' && $k === 'pickup_code' && !in_array($curKind, cdp_notifyDraftCodeKinds(), true)) {
                        $off = 'Only ready-for-pickup and delivery statuses carry a pickup code; here it would always be empty and the line left out.';
                    } elseif ($curKind !== '' && in_array($k, ['pay_url', 'amount_due'], true) && !in_array($curKind, cdp_notifyDraftPayKinds(), true)) {
                        $off = 'Only payment statuses (pending payment, invoiced) ask for payment.' . ($k === 'amount_due' ? ' Here it would always be empty and the line left out.' : '');
                    }
                    $vars[] = ['key' => $k, 'desc' => $desc, 'off' => $off];
                }
            }
        }
    }
    $ch = (string) ($_GET['ch'] ?? '');
    if (!$cur || !in_array($ch, $cur['channels'], true)) {
        $ch = $cur ? $cur['channels'][0] : 'email';
    }
    // Statuses (by name) the Communication Center matches to this automation, for the comms view.
    $mappedStatuses = [];
    if ($cur && $cur['kind'] === 'comms') {
        foreach ($catalogue as $k => $c) {
            if ($c['kind'] === 'status' && in_array($c['conflicts']['event'], [$cur['event']], true)) {
                $mappedStatuses[] = ['label' => $c['label'], 'href' => sl_url(['t' => $k, 'ch' => null]), 'fits' => $c['conflicts']['fits']];
            }
        }
    }
    return [
        'canEdit' => $canEdit, 'canComms' => $canComms, 'canGlobal' => $canGlobal, 'commsOn' => $commsOn, 'draftsOn' => $draftsOn, 'ai' => $ai,
        'commsUrl' => sl_route('CommsCenter'),
        'groups' => $groups, 'q' => $q, 'total' => count($catalogue), 'customCount' => $customCount, 'offCount' => $offCount,
        'draftedCount' => $draftedCount, 'warnCount' => $warnCount, 'statusKeys' => $statusKeys, 'mappedStatuses' => $mappedStatuses,
        'cur' => $cur, 'picked' => $cur !== null && (string) ($_GET['t'] ?? '') === $cur['key'], 'editor' => $editor, 'channel' => $ch, 'channels' => $channels, 'vars' => $vars,
        'blockTypes' => cdp_notifyBlockTypes(), 'sample' => $sample,
        'limits' => cdp_notifyLimits(),
    ];
}
