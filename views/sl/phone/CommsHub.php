<?php
/**
 * M-Comms: Notifications & Messages > Overview on a phone (comms_hub.php). Same $d as the web page
 * (views/sl/pages/CommsHub.php): stats from the Outbox feed, per-channel figures, alerts, recent failures
 * and the pages of the area this person may open (sl_navResolveChildren). Every row opens the real page.
 */
$st = $d['stats'] ?? ['sent' => 0, 'failed' => 0, 'waiting' => 0, 'cost' => 0];
$ol = $d['olink'];
$cur = (string) ($d['currency'] ?? '');
$money = fn($v) => trim($cur . ' ' . number_format((float) $v, 2));
$period = [1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days'];
$days = (int) ($d['days'] ?? 30);
$tiles = [['Sent', 'send', $st['sent'], $ol(['status' => 'sent'])], ['Failed', 'alert', $st['failed'], $ol(['status' => 'failed'])],
    ['Waiting', 'clock', $st['waiting'], $ol(['status' => 'waiting'])], ['Cost', 'dollar', $money($st['cost']), sl_route('MessageCosts')]];
$lvl = ['bad' => 'bad', 'warn' => '', 'info' => ''];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-msg.css')) ?>">
<style>.mx-seg a{flex:none;min-height:40px;display:inline-flex;align-items:center;padding:0 14px;border-radius:12px;border:1px solid var(--line);background:var(--card);color:var(--ink-2);font:500 13px var(--font);white-space:nowrap}.mx-seg a.on{background:var(--ink);color:var(--inverse);border-color:var(--ink)}a.mx-kt{color:inherit}.i-wa{--i:url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'><path d='M3.5 20.5l1.3-4.3A8.5 8.5 0 1 1 8 19.4z'/><path d='M9 8.5c0 3 2.5 6.5 6.5 6.5l1-1.5-2-1-1 .8c-1-.4-2.3-1.7-2.8-2.8l.8-1-1-2z'/></svg>")}</style>
<?= sl_phoneTop('Notifications & Messages', $period[$days] ?? '30 days', ['back' => 'menu.php'], sl_phoneIcon('more', 'Open the full page', sl_url(['view' => 'web']))) ?>
<main class="mx-body">
<?php if (empty($d['ready'])): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The message tables could not be created. The database user needs the CREATE privilege once.</span></div><?php endif; ?>
<nav class="mx-seg" aria-label="Period"><?php foreach ($period as $n => $l): ?><a href="<?= h(sl_url(['days' => $n === 30 ? null : $n])) ?>" class="<?= $days === $n ? 'on' : '' ?>"<?= $days === $n ? ' aria-current="true"' : '' ?>><?= h($l) ?></a><?php endforeach; ?></nav>
<section class="mx-k c2" aria-label="Messages in the period">
<?php foreach ($tiles as [$l, $ic, $v, $href]): ?>
<a class="mx-kt" href="<?= h($href) ?>"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-<?= h($ic) ?>" aria-hidden="true"></i></span><small><?= h($l) ?></small></div><b><?= is_int($v) ? sl_num($v) : h($v) ?></b></a>
<?php endforeach; ?>
</section>
<div class="mx-sec"><h2>By channel</h2></div>
<section class="mx-card mx-list">
<?php foreach ($d['channels'] ?? [] as $c): $s = $d['status'][$c['ch']] ?? null; ?>
<a class="mx-row" href="<?= h($c['href']) ?>"><span class="mx-li"><i class="ic <?= h($c['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($c['label']) ?><?= $s && !$s['ok'] ? ' · ' . h($s['state']) : '' ?></b><small><?= sl_num($c['sent']) ?> sent<?= $c['failed'] ? ' · ' . sl_num($c['failed']) . ' failed' : '' ?><?= $c['received'] ? ' · ' . sl_num($c['received']) . ' received' : '' ?></small></span><span class="mx-rr"><b><?= $c['rate'] === null ? '-' : (int) $c['rate'] . '%' ?></b><small><?= h($money($c['cost'])) ?></small></span></a>
<?php endforeach; ?>
</section>
<?php if (!empty($d['alerts'])): ?>
<div class="mx-sec"><h2>Needs attention</h2></div>
<section class="mx-card mx-list">
<?php foreach ($d['alerts'] as $a): $tag = $a['href'] !== '' ? 'a' : 'div'; ?>
<<?= $tag ?> class="mx-row <?= h($lvl[$a['lvl']] ?? '') ?>"<?= $a['href'] !== '' ? ' href="' . h($a['href']) . '"' : '' ?>><span class="mx-li"><i class="ic <?= h($a['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($a['t']) ?></b><small><?= h($a['d']) ?></small></span><?php if ($a['href'] !== ''): ?><i class="ic ic-sm i-right mx-ch" aria-hidden="true"></i><?php endif; ?></<?= $tag ?>>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php if (!empty($d['fails'])): ?>
<div class="mx-sec"><h2>Latest failures</h2><a href="<?= h($ol(['status' => 'failed'])) ?>">All</a></div>
<section class="mx-card mx-list">
<?php foreach ($d['fails'] as $r): ?>
<a class="mx-row" href="<?= h(sl_route('CommsOutbox', ['open' => $r['ref'], 'status' => 'failed'])) ?>"><span class="mx-rm"><b><?= h($r['name'] !== '' ? $r['name'] : $r['to']) ?> · <?= h($r['channel_label']) ?></b><small title="<?= h($r['detail']) ?>"><?= h($r['short'] !== '' ? $r['short'] : 'Failed') ?></small></span><span class="mx-rr"><small><?= h($r['when']) ?></small></span></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
<div class="mx-sec"><h2>Everything in Notifications &amp; Messages</h2></div>
<section class="mx-card mx-list">
<?php foreach ($d['links'] ?? [] as $l): ?>
<a class="mx-row" href="<?= h($l['href']) ?>"><span class="mx-li"><i class="ic <?= h($l['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($l['label']) ?></b><small><?= h($l['what']) ?></small></span><?php if (($l['c1'] ?? '') !== ''): ?><span class="mx-cnt"><?= h($l['c1']) ?></span><?php endif; ?><i class="ic ic-sm i-right mx-ch" aria-hidden="true"></i></a>
<?php endforeach; ?>
</section>
<?php if (!empty($d['canSend'])): ?><div class="mx-btns"><a class="mx-btn" href="<?= h(sl_route('CommsSend')) ?>">Send a message</a><a class="mx-btn sec" href="<?= h(sl_route('CommsOutbox')) ?>">Outbox</a></div><?php endif; ?>
</main>
