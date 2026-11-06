<?php
/**
 * CommsHub - generated from docs/handover/artifact-source/CommsHub.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  kpis                         as $k
 *   loop  k.sp                         as $h
 *   loop  ev                           as $e
 *   loop  segs                         as $g
 *   loop  cf                           as $f
 *   loop  cov                          as $c
 *   if    flash
 *   value cf, cov, ev, false, flash, h, kpis, segs
 * sl-wrap extras in the design: style="max-width:1440px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">Messages and automation</h1><p class="sl-lede">WhatsApp, SMS and e-mail sent at every step of a shipment, plus segments, surveys and opt-outs.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('MessageLogs') ?>">History</a><a class="sl-btn" href="<?= sl_route('Broadcast') ?>">Send to customers</a></div></div>
<?php if (sl_get($d, 'flash')): ?><div class="fr-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?>

<div class="hb-ks"><?php foreach ((array) sl_get($d, 'kpis', []) as $__i => $k): ?><div class="hb-k"><div class="hb-kh"><span class="hb-ki"><i class="ic ic-sm <?= sl_v($k, 'icon') ?>" aria-hidden="true"></i></span><span class="hb-kl"><?= sl_v($k, 'l') ?></span></div><b class="hb-kv <?= sl_v($k, 'anim') ?>"><?= sl_v($k, 'v') ?></b><div class="hb-kf"><span class="hb-ks2"><?= sl_v($k, 's') ?></span><span class="hb-sp" aria-hidden="true"><?php foreach ((array) sl_get($k, 'sp', []) as $__i => $h): ?><i style="<?= sl_v($h, '') ?>"></i><?php endforeach; ?></span></div></div><?php endforeach; ?></div>
<div class="hb-g"><section class="hb-p" style="grid-column:1/-1"><div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">What is sent automatically</h2><span class="mut sm">Tick a channel to switch it on for that event. Customers' own opt-outs always win.</span></div><a class="sl-btn ghost sm" href="<?= sl_route('MessageTemplates') ?>">Edit wording</a></div><div class="sl-tw"><table class="sl-table"><thead><tr><th>Event</th><th style="text-align:center">WhatsApp</th><th style="text-align:center">SMS</th><th style="text-align:center">E-mail</th><th class="num">Sent this month</th></tr></thead><tbody><?php foreach ((array) sl_get($d, 'ev', []) as $__i => $e): ?><tr><td><span class="col"><b class="fw5"><?= sl_v($e, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($e, 'd') ?></span></span></td><td style="text-align:center"><button class="sl-chk <?= sl_v($e, 'wa') ?>" aria-label="WhatsApp" data-sl-on="click:fw"></button></td><td style="text-align:center"><button class="sl-chk <?= sl_v($e, 'sm') ?>" aria-label="SMS" data-sl-on="click:fs"></button></td><td style="text-align:center"><button class="sl-chk <?= sl_v($e, 'em') ?>" aria-label="E-mail" data-sl-on="click:fe"></button></td><td class="num mono"><?= sl_v($e, 'n') ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="hb-p"><h2 class="sl-ct">Customer segments</h2><?php foreach ((array) sl_get($d, 'segs', []) as $__i => $g): ?><div class="hb-l"><span class="col grow"><b class="fw5"><?= sl_v($g, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($g, 'd') ?></span></span><b class="mono"><?= sl_v($g, 'n') ?></b><a class="sl-btn ghost sm" href="<?= sl_route('Broadcast') ?>">Message</a></div><?php endforeach; ?><span class="mut sm">Promotions only go to customers who said yes to offers.</span></section>
<section class="hb-p"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Satisfaction survey</h2><span class="sl-badge">Sent after delivery</span></div><div class="hb-out">How was your delivery today? Reply 1 to 5 ⭐
Anything we can do better?</div><div class="sl-kv"><div><span>Average this month</span><span class="mono">4.6 / 5</span></div><div><span>Replies</span><span class="mono">412 (38%)</span></div><div><span>Unhappy (1–2) → ticket</span><span class="mono">9</span></div></div></section></div>
<section class="col g10"><div class="row between wrap g8"><h2 class="sl-ct">All 21 features and where they live</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'cf', []) as $__i => $f): ?><button class="<?= sl_v($f, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($f, 'label') ?></button><?php endforeach; ?></div></div><div class="hb-cov"><?php foreach ((array) sl_get($d, 'cov', []) as $__i => $c): ?><a class="hb-i <?= sl_v($c, 'off') ?>" href="<?= sl_v($c, 'href') ?>"><span class="n"><?= sl_v($c, 'n') ?></span><span class="col grow"><b class="fw5 sm"><?= sl_v($c, 't') ?></b><span class="mut" style="font-size:11.5px"><?= sl_v($c, 'where') ?></span></span><span class="sl-pill <?= sl_v($c, 'pc') ?>"><?= sl_v($c, 'st') ?></span></a><?php endforeach; ?></div></section>
