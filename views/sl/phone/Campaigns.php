<?php
/**
 * M-Campaigns: bulk sends (campaigns.php). Running / Done come from the message log (sl_campaignsData);
 * Scheduled / Drafts are the Communication Center campaigns not sent yet. ?batch=id opens one campaign.
 */
require_once CDP_ROOT . '/views/sl/data/phone_msg.php';
require_once CDP_ROOT . '/helpers/sl_mx.php';
$chL = ['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'];
$camps = $d['campaigns'] ?? [];
$running = array_values(array_filter($camps, fn($c) => $c['running']));
$done = array_values(array_filter($camps, fn($c) => !$c['running']));
$planned = sl_pmPlanned();
$bars = sl_pmWeekBars();
$dt = $d['detail'] ?? null;
$newHref = $user->cdp_hasPermission('push_notifications') ? sl_route('ComposeMessage') : sl_route('CommsCenter');
$row = function (array $c): string {
    $chs = implode(' · ', array_map(fn($x) => ['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'][$x] ?? $x, $c['chs']));
    $right = $c['openP'] !== null ? '<b>' . (int) $c['openP'] . '%</b><small>opened</small>' : '<b>' . (int) $c['deliv'] . '%</b><small>delivered</small>';
    return '<a class="mx-row" href="' . h(sl_url(['batch' => $c['id']])) . '"><span class="mx-li"><i class="ic i-send" aria-hidden="true"></i></span><span class="mx-rm"><b>' . h($c['title']) . '</b><small>' . h($chs) . ' · ' . sl_num($c['n']) . ' recipients · ' . h($c['ago']) . '</small></span><span class="mx-rr">' . $right . '</span></a>';
};
$tabs = [['run', 'Running', count($running)], ['sch', 'Scheduled', count($planned['scheduled'])], ['dr', 'Drafts', count($planned['draft'])], ['done', 'Done', count($done)]];
$first = $running ? 'run' : ($planned['scheduled'] ? 'sch' : 'done');
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-msg.css')) ?>">
<?php if ($dt): $slPhone['nav'] = 'none'; ?>
<?= sl_phoneTop($dt['title'], trim($dt['src'] . ($dt['aud'] !== '' ? ' · ' . $dt['aud'] : ''), ' ·'), ['back' => sl_url(['batch' => null])]) ?>
<main class="mx-body">
<section class="mx-k c2">
<?php foreach ([['send', 'Sent', $dt['agg']['sent'], $dt['n']], ['eye', 'Opened', $dt['agg']['opened'], $dt['agg']['sent']], ['link', 'Clicked', $dt['agg']['clicked'], $dt['agg']['sent']], ['alert', 'Failed', $dt['agg']['failed'], $dt['n']]] as [$ic, $l, $v, $of]): ?>
<?= sl_mxKpi($ic, $l, sl_num($v), ($of ? (int) round($v / $of * 100) : 0) . '%') ?>
<?php endforeach; ?>
</section>
<section class="mx-card mx-kv"><div><span>When</span><b><?= h($dt['when']) ?></b></div><div><span>By</span><b><?= h($dt['who'] ?: 'System') ?></b></div><div><span>Recipients</span><b><?= sl_num($dt['n']) ?></b></div></section>
<div class="mx-sec"><h2>Recipients</h2></div>
<section class="mx-card mx-list">
<?php foreach (array_slice($dt['rows'], 0, 40) as $r): $pc = $r['st'] === 'sent' ? 'delivered' : ($r['st'] === 'failed' ? 'overdue' : 'plain'); ?>
<?= sl_mxRow(sl_initials($r['name'] !== '—' ? $r['name'] : '?'), $r['name'], $r['to'] . ' · ' . ($chL[$r['ch']] ?? $r['ch']) . ($r['opened'] !== '' ? ' · opened ' . $r['opened'] : ''), sl_pill(ucfirst($r['st']), $pc), $r['uid'] ? sl_route('CustomerProfile', ['id' => $r['uid']]) : '', true) ?>
<?php endforeach; ?>
</section>
<?php if ($dt['n'] > 40): ?><p class="mx-note">First 40 of <?= sl_num($dt['n']) ?> shown. <a href="<?= h(sl_route('MessageLogs', ['batch_id' => $dt['id']])) ?>">Every message is in Message logs</a>.</p><?php endif; ?>
</main>
<?php else: ?>
<?= sl_phoneTop('Campaigns', $d['days'] . ' days · ' . ($d['totalCost'] ?? ''), ['back' => 'menu.php'], sl_phoneIcon('more', 'Open the full page', sl_url(['view' => 'web']))) ?>
<main class="mx-body">
<?php if (empty($d['ready'])): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Message logging is not set up yet.</span></div><?php endif; ?>
<div class="mx-seg" role="tablist" data-mx-seg><?php foreach ($tabs as [$k, $l, $n]): ?><button type="button" role="tab" data-mx-panel="<?= $k ?>" class="<?= $k === $first ? 'on' : '' ?>" aria-pressed="<?= $k === $first ? 'true' : 'false' ?>"><?= $l ?><?= $n ? ' ' . $n : '' ?></button><?php endforeach; ?></div>
<?php foreach (['run' => $running, 'done' => $done] as $k => $list): ?>
<div data-mx-panel-of="<?= $k ?>"<?= $k === $first ? '' : ' hidden' ?>>
<?php if ($list): ?><section class="mx-card mx-list"><?php foreach (array_slice($list, 0, 30) as $c) echo $row($c); ?></section>
<?php else: ?><?= sl_mxEmpty('send', $k === 'run' ? 'Nothing is sending right now' : 'No campaigns in the last ' . (int) $d['days'] . ' days', '', 'New campaign', $newHref) ?><?php endif; ?>
</div>
<?php endforeach; ?>
<?php foreach (['sch' => $planned['scheduled'], 'dr' => $planned['draft']] as $k => $list): ?>
<div data-mx-panel-of="<?= $k ?>"<?= $k === $first ? '' : ' hidden' ?>>
<?php if ($list): ?><section class="mx-card mx-list"><?php foreach ($list as $c): ?>
<a class="mx-row" href="<?= h(sl_route('CommsCenter') . '#campaigns') ?>"><span class="mx-li"><i class="ic i-<?= $k === 'sch' ? 'calendar' : 'file' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($c->name) ?></b><small><?= h(ucwords(str_replace(',', ' · ', (string) $c->channels))) ?><?= $c->segment_name ? ' · ' . h($c->segment_name) : '' ?><?= $k === 'sch' && $c->scheduled_at ? ' · ' . h(sl_date($c->scheduled_at, 'D j M, H:i')) : '' ?></small></span><span class="mx-rr"><?= sl_pill($k === 'sch' ? 'Scheduled' : 'Draft', 'pending') ?></span></a>
<?php endforeach; ?></section>
<?php else: ?><?= sl_mxEmpty($k === 'sch' ? 'calendar' : 'file', $k === 'sch' ? 'Nothing scheduled' : 'No drafts') ?><?php endif; ?>
</div>
<?php endforeach; ?>
<section class="mx-card mx-bars"><div class="mx-bh"><b>Messages sent this week</b><small><?= sl_num(array_sum(array_column($bars, 'v'))) ?></small></div><div class="mx-bc" role="img" aria-label="Messages sent per day, last 7 days"><?php foreach ($bars as $b): ?><span class="<?= h($b['c']) ?>" title="<?= (int) $b['v'] ?>"><i style="<?= h($b['h']) ?>"></i><small><?= h($b['l']) ?></small></span><?php endforeach; ?></div></section>
</main>
<a class="mx-fab" href="<?= h($newHref) ?>" aria-label="New campaign"><i class="ic i-plus" aria-hidden="true"></i></a>
<?php endif; ?>
