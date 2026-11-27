<?php
/** M-Comms: messages and automation hub (comms_hub.php). Data: the controller's kpis (this month), channel health and page links. */
$k = $d['kpis'] ?? [];
$n = fn($i) => (int) preg_replace('/\D/', '', (string) ($k[$i]['v'] ?? '0'));
$wa = $n(0); $sms = $n(1); $em = $n(2);
$total = max(1, $wa + $sms + $em);
$sp = function (array $bars): string {
    if (!$bars) return '';
    $out = '<div class="mx-sp" aria-hidden="true">';
    $c = count($bars);
    foreach ($bars as $i => $b) $out .= '<i class="' . ($i === $c - 1 ? 'now' : '') . '" style="' . h($b['s']) . '"></i>';
    return $out . '</div>';
};
// The same links and permissions as the "where it lives" list of the web page.
$can = [];
foreach ($d['cov'] ?? [] as $c) $can[$c['t']] = $c['off'] === '';
$acts = [
    ['Inbox', 'message', sl_route('CommsInbox'), true],
    ['Compose', 'mail', sl_route('ComposeMessage'), $can['Send a message'] ?? false],
    ['Push', 'send', sl_route('Broadcast'), $can['Push notifications'] ?? false],
    ['Templates', 'file', sl_route('MessageTemplates'), $can['SMS, WhatsApp and e-mail templates'] ?? false],
    ['Rules', 'bolt', sl_route('NotificationCenter'), $can['Notification Center'] ?? false],
    ['Logs', 'archive', sl_route('MessageLogs'), $can['Message logs'] ?? false],
    ['Costs', 'dollar', sl_route('MessageCosts'), $can['Message costs'] ?? false],
    ['Email builder', 'mail', sl_route('EmailBuilder'), $can['E-mail builder'] ?? false],
];
$acts = array_values(array_filter($acts, fn($a) => $a[3]));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-msg.css')) ?>">
<?= sl_phoneTop('Communications', 'This month', ['back' => 'menu.php'], sl_phoneIcon('more', 'Open the full page', sl_url(['view' => 'web']))) ?>
<main class="mx-body">
<section class="mx-k c2">
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-send" aria-hidden="true"></i></span><small>Sent</small></div><b><?= sl_num($wa + $sms + $em) ?></b><?= $sp($k[0]['sp'] ?? []) ?></div>
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-alert" aria-hidden="true"></i></span><small>Failed</small></div><b><?= h($k[3]['v'] ?? '0') ?></b><?= $sp($k[3]['sp'] ?? []) ?></div>
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-bolt" aria-hidden="true"></i></span><small>Automations</small></div><b><?= h($k[5]['v'] ?? '0') ?></b><em><?= h($k[5]['s'] ?? '') ?></em></div>
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-dollar" aria-hidden="true"></i></span><small>Cost</small></div><b><?= h($k[4]['v'] ?? '—') ?></b><em><?= h($k[4]['s'] ?? '') ?></em></div>
</section>
<div class="mx-sec"><h2>By channel</h2></div>
<section class="mx-card mx-mts">
<?php foreach ([['WhatsApp', $wa, 'whatsapp', 'ok'], ['SMS', $sms, 'sms', ''], ['Email', $em, 'email', '']] as [$l, $v, $key, $cls]): $hl = $d['health'][$key] ?? null; ?>
<div class="mx-mt"><div><span><?= h($l) ?><?= $hl && empty($hl['ok']) ? ' · ' . h($hl['state']) : '' ?></span><b><?= sl_num($v) ?></b></div><span class="mx-trk"><i class="<?= $cls ?>" style="width:<?= (int) round($v / $total * 100) ?>%"></i></span></div>
<?php endforeach; ?>
</section>
<section class="mx-act c4">
<?php foreach ($acts as [$l, $ic, $href]): ?><a href="<?= h($href) ?>"><span><i class="ic i-<?= h($ic) ?>" aria-hidden="true"></i></span><small><?= h($l) ?></small></a><?php endforeach; ?>
</section>
</main>
