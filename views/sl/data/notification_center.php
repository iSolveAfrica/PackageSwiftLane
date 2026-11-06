<?php
/** Data for the Notification Center (NotificationCenter.dc.html). */
require_once __DIR__ . '/msg_common.php';

function sl_notificationCenterData($user, $core): array
{
    $events = sl_msgEvents();
    $rules = sl_msgRules();
    $ready = sl_tableExists('cdb_notification_rules');
    $q = trim((string) ($_GET['q'] ?? ''));
    $curKey = (string) ($_GET['event'] ?? '');
    if (!isset($events[$curKey])) {
        $curKey = array_key_first($events);
    }
    $channel = (string) ($_GET['channel'] ?? 'email');
    if (!in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
        $channel = 'email';
    }
    $on = ['email' => 0, 'sms' => 0, 'whatsapp' => 0];
    $sentMonth = [];
    if (sl_tableExists('cdb_message_log')) {
        foreach (sl_rows("SELECT source, COUNT(*) AS n FROM cdb_message_log WHERE status = 'sent' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') GROUP BY source") as $r) {
            $sentMonth[(string) $r->source] = (int) $r->n;
        }
    }
    $groups = [];
    foreach ($events as $key => $e) {
        if ($q !== '' && stripos($e['name'] . ' ' . $e['desc'] . ' ' . $e['group'], $q) === false) {
            continue;
        }
        $row = ['key' => $key, 'name' => $e['name'], 'when' => $e['when'], 'cls' => $key === $curKey ? 'on' : '', 'href' => sl_url(['event' => $key])];
        foreach (['email' => 'em', 'sms' => 'sm', 'whatsapp' => 'wa'] as $ch => $short) {
            $en = $rules[$key][$ch]['enabled'] ?? true;
            if ($en) {
                $on[$ch]++;
            }
            $row[$short] = $en ? 'on' : 'off';
            $row[$short . 'P'] = $en ? 'true' : 'false';
        }
        $groups[$e['group']]['label'] = $e['group'];
        $groups[$e['group']]['items'][] = $row;
    }
    foreach ($groups as &$g) {
        $g['count'] = count($g['items']);
    }
    unset($g);
    $total = count($events);
    $d = ['ready' => $ready, 'q' => $q, 'total' => $total, 'groups' => array_values($groups), 'health' => sl_msgChannelHealth()];
    foreach (['email' => 'Email', 'sms' => 'Sms', 'whatsapp' => 'Wa'] as $ch => $suffix) {
        $d['on' . $suffix] = $on[$ch];
        $d['w' . $suffix] = 'width:' . sl_pct($on[$ch], $total) . '%';
    }
    $cur = $events[$curKey];
    $sent = 0;
    foreach (sl_msgEventSources($curKey) as $src) {
        $sent += $sentMonth[$src] ?? 0;
    }
    $d['cur'] = ['key' => $curKey, 'group' => $cur['group'], 'when' => $cur['when'], 'name' => $cur['name'], 'desc' => $cur['desc'], 'audience' => $cur['audience'], 'sent' => sl_num($sent)];
    $d['tabs'] = [];
    foreach (['email' => ['E-mail', 'i-mail'], 'sms' => ['SMS', 'i-phone'], 'whatsapp' => ['WhatsApp', 'i-message']] as $ch => $meta) {
        $d['tabs'][] = ['key' => $ch, 'label' => $meta[0], 'icon' => $meta[1], 'cls' => $ch === $channel ? 'on' : '', 'sel' => $ch === $channel ? 'true' : 'false',
            'on' => ($rules[$curKey][$ch]['enabled'] ?? true) ? 'on' : '', 'href' => sl_url(['event' => $curKey, 'channel' => $ch])];
    }
    $d['channel'] = $channel;
    $d['tabLabel'] = ['email' => 'E-mail', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'][$channel];
    $d['isEmail'] = $channel === 'email';
    $d['isChat'] = !$d['isEmail'];
    $d['bubbleCls'] = $channel === 'sms' ? 'sms' : '';
    $d['enabled'] = $rules[$curKey][$channel]['enabled'] ?? true;
    $templates = sl_msgTemplates($channel);
    $tplId = $rules[$curKey][$channel]['template_id'] ?? null;
    if (isset($_GET['tpl']) && isset($templates[(int) $_GET['tpl']])) {
        $tplId = (int) $_GET['tpl'];
    }
    $tpl = $tplId !== null && isset($templates[$tplId]) ? $templates[$tplId] : null;
    $d['templates'] = array_values($templates);
    $d['templateId'] = $tpl['id'] ?? 0;
    $d['templateName'] = $tpl['name'] ?? '';
    $d['subject'] = $tpl['subject'] ?? '';
    $body = $tpl['body'] ?? '';
    $d['body'] = $channel === 'email' ? $body : sl_msgPlain($body);
    $d['bodyIsHtml'] = $channel === 'email' && $body !== strip_tags($body);
    $sample = sl_msgSample();
    $d['sample'] = $sample;
    $d['subjectPrev'] = sl_msgPreviewFill($d['subject'], $sample);
    $d['bodyPrev'] = sl_msgPreviewFill($channel === 'email' ? sl_msgPlain($body) : $d['body'], $sample);
    $len = mb_strlen($d['body']);
    $d['counter'] = $channel === 'sms' ? $len . ' characters · ' . max(1, (int) ceil($len / 153)) . ' SMS part' . ($len > 153 ? 's' : '') : $len . ' characters';
    $d['channelNote'] = $tpl ? 'Template #' . $tpl['id'] . ' · ' . $tpl['name'] : 'No template linked to this channel yet';
    $d['vars'] = array_map(fn($v) => ['label' => $v], ['[USERNAME]', '[SITE_NAME]', '[TRACKING_NUMBER]', '[STATUS]', '[URL]', '[URL_SHIP]', '[LOCKER]', '[MESSAGE]']);
    $d['saveLabel'] = 'Save changes';
    $d['canEdit'] = $user->cdp_hasPermission(['manage_notification_rules', 'view_tools']);
    return $d;
}
