<?php
/** Data for Message Templates (SMS, WhatsApp, e-mail, default per event). */
require_once __DIR__ . '/msg_common.php';

function sl_messageTemplatesData($user, $core, string $tab): array
{
    $tabs = ['sms' => ['SMS', 'i-phone', 'templates_sms.php'], 'whatsapp' => ['WhatsApp', 'i-message', 'templates_whatsapp.php'], 'email' => ['E-mail', 'i-mail', 'templates_email.php'], 'default' => ['Default templates', 'i-sliders', 'templates_default.php']];
    $lists = ['sms' => sl_msgTemplates('sms'), 'whatsapp' => sl_msgTemplates('whatsapp'), 'email' => sl_msgTemplates('email')];
    $d = ['tab' => $tab, 'tabs' => [], 'q' => trim((string) ($_GET['q'] ?? ''))];
    foreach ($tabs as $k => $t) {
        $d['tabs'][] = ['k' => $k, 'label' => $t[0], 'icon' => $t[1], 'href' => $t[2], 'cls' => $k === $tab ? 'on' : '', 'n' => $k === 'default' ? (int) sl_scalar('SELECT COUNT(*) FROM default_notification_templates') : count($lists[$k])];
    }
    $d['isDef'] = $tab === 'default';
    $d['isEdit'] = !$d['isDef'];
    $d['isWa'] = $tab === 'whatsapp';
    if ($d['isDef']) {
        $wa = $lists['whatsapp'];
        $d['defs'] = array_map(fn($r) => ['id' => (int) $r->id, 'n' => sl_msgPlain((string) $r->notification_description), 'tpl' => (int) $r->id_template, 'on' => (int) $r->active === 1], sl_rows('SELECT * FROM default_notification_templates ORDER BY id'));
        $d['waList'] = array_values($wa);
        return $d;
    }
    $list = $lists[$tab];
    $cur = (int) ($_GET['id'] ?? 0);
    if (!isset($list[$cur])) $cur = (int) (array_key_first($list) ?? 0);
    $d['list'] = [];
    foreach ($list as $t) {
        if ($d['q'] !== '' && stripos($t['name'] . ' ' . $t['subject'], $d['q']) === false) continue;
        $who = stripos($t['name'], 'RECEIVER') !== false ? 'Receiver' : (stripos($t['name'], 'admin') !== false ? 'Admin' : 'Sender');
        $d['list'][] = ['id' => $t['id'], 'name' => $t['name'], 'sub' => $t['subject'], 'who' => $who, 'cls' => $t['id'] === $cur ? 'on' : '', 'href' => sl_url(['id' => $t['id']])];
    }
    $t = $list[$cur] ?? null;
    $d['new'] = isset($_GET['new']) && $d['isWa'];
    if ($d['new']) $t = ['id' => 0, 'name' => '', 'subject' => '', 'body' => '', 'help' => ''];
    $d['cur'] = $t;
    $d['isHtml'] = $t && $tab === 'email';
    $d['body'] = $t ? ($tab === 'email' ? $t['body'] : sl_msgPlain($t['body'])) : '';
    $d['preview'] = $t ? sl_msgPreviewFill(sl_msgPlain($t['body']), sl_msgSample()) : '';
    $len = mb_strlen($d['body']);
    $parts = max(1, (int) ceil($len / 153));
    $rates = function_exists('cdp_pushCostRates') ? cdp_pushCostRates() : null;
    $d['countLabel'] = $tab === 'sms' ? "$len characters · $parts SMS part" . ($parts > 1 ? 's' : '') : "$len characters";
    $d['costLabel'] = $rates && $rates['available'] ? $rates['currency'] . ' ' . number_format($rates[$tab] * ($tab === 'sms' ? $parts : 1), 3) . ' per message' : '';
    $d['meterW'] = 'width:' . min(100, sl_pct($len, $tab === 'sms' ? 459 : 1024)) . '%';
    $d['vars'] = array_map(fn($v) => ['k' => $v], $tab === 'sms' ? ['[NAME]', '[TRACK]', '[STATUS]', '[LINK]', '[SITE_NAME]'] : ['[USERNAME]', '[SITE_NAME]', '[TRACKING_NUMBER]', '[STATUS]', '[URL]', '[URL_SHIP]', '[MESSAGE]', '[SUBJECT]']);
    $d['help'] = $t['help'] ?? '';
    $d['sample'] = sl_msgSample();
    return $d;
}
