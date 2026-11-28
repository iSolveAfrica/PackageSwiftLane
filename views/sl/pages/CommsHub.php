<?php
/**
 * CommsHub - Notifications & Messages > Overview (comms_hub.php).
 * KPIs from the Outbox feed (helpers/comms_outbox_view.php), channel state
 * (views/sl/data/comms_settings.php: sl_commsChannelStatus), alerts,
 * recent failures and links to every page of the area. Styles: CommsHub.css.
 */
if (!isset($d)) { $d = []; }
$commsTab = 'commsoverview';
include __DIR__ . '/partials/comms_tabs.php';
$st = $d['stats'];
$ol = $d['olink'];
$cur = $d['currency'];
$money = fn($v) => trim($cur . ' ' . number_format((float) $v, 2));
$period = [1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days'];
$tiles = [
    ['Sent', $st['sent'], 'ok', $ol(['status' => 'sent'])],
    ['Failed', $st['failed'], $st['failed'] > 0 ? 'bad' : '', $ol(['status' => 'failed'])],
    ['Not sent (blocked, opted out)', $st['skipped'], $st['skipped'] > 0 ? 'warn' : '', $ol(['status' => 'skipped'])],
    ['Waiting to go', $st['waiting'], 'info', $ol(['status' => 'waiting'])],
    ['Received from customers', $st['received'], 'in', $ol(['status' => 'received'])],
];
?>
<div class="sl-head"><div><h1 class="sl-title">Notifications &amp; Messages</h1><p class="sl-lede">Every WhatsApp, SMS and e-mail - automatic, campaigns, one-to-one and inbox replies - in one place.</p></div>
<div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h(sl_route('CommsOutbox')) ?>"><i class="ic ic-sm i-archive" aria-hidden="true"></i>Outbox</a><?php if ($d['canSend']): ?><a class="sl-btn" href="<?= h(sl_route('CommsSend')) ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send a message</a><?php endif; ?></div></div>

<?php if (!$d['ready']): ?><div class="sl-banner bad"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The message tables could not be created. The database user needs the CREATE privilege once.</span></div><?php endif; ?>

<div class="row between wrap g10 hb-bar">
<div class="row g8 wrap hb-chans" aria-label="Channels">
<?php foreach ($d['status'] as $k => $s): $cls = $s['ok'] ? 'ok' : ($s['warn'] ? 'warn' : 'off'); ?>
<?php if ($d['isAdmin']): ?><a class="hb-ch <?= $cls ?>" href="<?= h($d['settingsUrl'] . '#cs-' . ($k === 'worker' ? 'rules' : $k)) ?>"><?php else: ?><span class="hb-ch <?= $cls ?>"><?php endif; ?>
<span class="hb-dot<?= $s['ok'] ? '' : ($s['warn'] ? ' w' : ' e') ?>" aria-hidden="true"></span><b class="fw5"><?= h($s['label']) ?></b><span class="mut"><?= h($s['state']) ?></span>
<?= $d['isAdmin'] ? '</a>' : '</span>' ?>
<?php endforeach; ?>
</div>
<nav class="sl-seg boxed" aria-label="Period"><?php foreach ($period as $n => $l): ?><a href="<?= h(sl_url(['days' => $n === 30 ? null : $n])) ?>" class="<?= $d['days'] === $n ? 'on' : '' ?>"<?= $d['days'] === $n ? ' aria-current="true"' : '' ?>><?= h($l) ?></a><?php endforeach; ?></nav>
</div>

<section class="hb-tiles" aria-label="Messages in the period">
<?php foreach ($tiles as [$l, $n, $cls, $href]): ?>
<a class="sl-card hb-t <?= h($cls) ?>" href="<?= h($href) ?>"><span class="hb-tl"><?= h($l) ?></span><b class="hb-tn"><?= sl_num($n) ?></b></a>
<?php endforeach; ?>
<a class="sl-card hb-t cost" href="<?= h(sl_route('MessageCosts')) ?>"><span class="hb-tl">Cost of what was sent</span><b class="hb-tn"><?= h($money($st['cost'])) ?></b></a>
</section>

<div class="hb-g2">
<section class="sl-card hb-p" aria-labelledby="hb-ch-h">
<div class="sl-ch" style="margin:0"><h2 class="sl-ct" id="hb-ch-h">By channel</h2><span class="mut sm">Sent in the last 14 days, per day</span></div>
<div class="hb-cl">
<?php foreach ($d['channels'] as $c): ?>
<a class="hb-cr" href="<?= h($c['href']) ?>" aria-label="<?= h($c['label'] . ': ' . sl_num($c['sent']) . ' sent' . ($c['failed'] ? ', ' . sl_num($c['failed']) . ' failed' : '') . ($c['rate'] === null ? '' : ', ' . (int) $c['rate'] . '% delivered') . '. Open in the Outbox') ?>">
<span class="hb-ci"><i class="ic ic-sm <?= h($c['icon']) ?>" aria-hidden="true"></i></span>
<span class="col grow hb-cm"><b class="fw5"><?= h($c['label']) ?></b><span class="mut sm"><?= sl_num($c['sent']) ?> sent<?= $c['failed'] ? ' · <span class="hb-bad">' . sl_num($c['failed']) . ' failed</span>' : '' ?><?= $c['skipped'] ? ' · ' . sl_num($c['skipped']) . ' not sent' : '' ?><?= $c['received'] ? ' · ' . sl_num($c['received']) . ' received' : '' ?></span></span>
<span class="col hb-cs"><b class="mono"><?= $c['rate'] === null ? '-' : (int) $c['rate'] . '%' ?></b><span class="mut sm">delivered</span></span>
<span class="col hb-cs"><b class="mono"><?= h($money($c['cost'])) ?></b><span class="mut sm">cost</span></span>
<span class="hb-spk" aria-hidden="true"><?php foreach ($c['spark'] as $b): ?><i data-t="<?= h($b['t']) ?>" style="<?= h($b['s']) ?>"></i><?php endforeach; ?></span>
</a>
<?php endforeach; ?>
</div>
<?php if ($d['origins']): ?>
<h3 class="hb-h3">Where they came from</h3>
<div class="hb-or">
<?php $max = max(1, max(array_column($d['origins'], 'n'))); foreach ($d['origins'] as $o): ?>
<a class="hb-o" href="<?= h($o['href']) ?>"><span class="hb-ol"><?= h($o['label']) ?></span><span class="fr-bar grow" aria-hidden="true"><i style="width:<?= max(2, round(100 * $o['n'] / $max)) ?>%"></i></span><b class="mono"><?= sl_num($o['n']) ?></b></a>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<section class="sl-card hb-p" aria-labelledby="hb-al-h">
<div class="sl-ch" style="margin:0"><h2 class="sl-ct" id="hb-al-h">Needs attention</h2><?php if ($d['alerts']): ?><span class="sl-badge"><?= count($d['alerts']) ?></span><?php endif; ?></div>
<?php if (!$d['alerts']): ?>
<div class="fr-ok"><i class="ic ic-sm i-check" aria-hidden="true"></i>All channels are working and nothing is waiting.</div>
<?php else: ?>
<ul class="hb-al">
<?php foreach ($d['alerts'] as $a): ?>
<li class="hb-a <?= h($a['lvl']) ?>"><span class="hb-ai"><i class="ic ic-sm <?= h($a['icon']) ?>" aria-hidden="true"></i></span><span class="col grow"><b class="fw5"><?= h($a['t']) ?></b><span class="mut sm"><?= h($a['d']) ?></span></span><?php if ($a['href'] !== ''): ?><a class="sl-btn ghost sm" href="<?= h($a['href']) ?>"><?= h($a['cta']) ?></a><?php endif; ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<div class="hb-own mut sm"><i class="ic ic-sm i-track" aria-hidden="true"></i><span>Status messages are sent by <b class="fw5"><?= $d['owner'] === 'comms' ? 'the Automation Center' : 'the old per-screen code' ?></b><?= $d['owner'] === 'comms' ? '' : ' until an administrator hands them over in Settings' ?>.</span></div>
</section>
</div>

<section class="sl-card hb-p" aria-labelledby="hb-fl-h">
<div class="sl-ch" style="margin:0"><div><h2 class="sl-ct" id="hb-fl-h">Latest failures</h2><span class="mut sm">Open a message in the Outbox to see the provider's answer, retry it or ask <?= h(cdp_kingName()) ?> why.</span></div><a class="sl-btn ghost sm" href="<?= h($ol(['status' => 'failed'])) ?>">All failures</a></div>
<?php if (!$d['fails']): ?><p class="mut">No failed messages in this period.</p><?php else: ?>
<div class="sl-tw"><table class="sl-table hb-ft"><thead><tr><th>When</th><th>Channel</th><th>To</th><th>What</th><th>Why</th></tr></thead><tbody>
<?php foreach ($d['fails'] as $r): ?>
<tr><td class="mono nowrap"><a href="<?= h(sl_route('CommsOutbox', ['open' => $r['ref'], 'status' => 'failed'])) ?>"><?= h($r['when']) ?></a></td><td><?= h($r['channel_label']) ?></td><td><?= h($r['name'] !== '' ? $r['name'] : $r['to']) ?></td><td class="hb-what"><?= h($r['what']) ?></td><td class="hb-why"><?php $why = trim((string) $r['detail']) !== '' ? (string) $r['detail'] : ($r['short'] !== '' ? $r['short'] : 'Failed'); ?><span class="sl-pill overdue" title="<?= h($why) ?>"><?= h(mb_strlen($why) > 220 ? mb_substr($why, 0, 219) . '…' : $why) ?></span></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>

<section class="col g10" aria-labelledby="hb-ql-h">
<h2 class="sl-ct" id="hb-ql-h">Everything in Notifications &amp; Messages</h2>
<div class="hb-ql">
<?php foreach ($d['links'] as $l): ?>
<a class="sl-card hb-q" href="<?= h($l['href']) ?>"><span class="hb-qi"><i class="ic ic-sm <?= h($l['icon']) ?>" aria-hidden="true"></i></span><span class="col grow"><b class="fw5"><?= h($l['label']) ?><?php if ($l['c1'] !== ''): ?> <em class="hb-qc"><?= h($l['c1']) ?></em><?php endif; ?></b><span class="mut sm"><?= h($l['what']) ?></span></span><i class="ic ic-sm i-right mut" aria-hidden="true"></i></a>
<?php endforeach; ?>
</div>
</section>
