<?php
/**
 * AiCenter - generated from docs/handover/artifact-source/AiCenter.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  kpis                         as $k
 *   loop  k.sp                         as $h
 *   loop  focus                        as $f
 *   loop  sugg                         as $q
 *   loop  sug                          as $x
 *   loop  tries                        as $t
 *   loop  bars                         as $b
 *   loop  fc                           as $f
 *   loop  wa                           as $m
 *   loop  cf                           as $f
 *   loop  cov                          as $c
 *   if    flash
 *   value answer, ask, askQ, bars, cf, cov, fLabel, false, fc, flash, focus, h, kpis, setAsk, sug, sugN, sugg, tries, tryImg, tryOut, wa
 * sl-wrap extras in the design: style="max-width:1440px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">AI centre</h1><p class="sl-lede">Every AI helper in Swift Lane, focused on air shipments first, with sea as an option.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('StaffAI') ?>">Staff assistant</a><a class="sl-btn" href="<?= sl_route('ApiConnections') ?>">AI settings</a></div></div>
<?php if (sl_get($d, 'flash')): ?><div class="fr-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?>

<div class="hb-ks"><?php foreach ((array) sl_get($d, 'kpis', []) as $__i => $k): ?><div class="hb-k"><div class="hb-kh"><span class="hb-ki"><i class="ic ic-sm <?= sl_v($k, 'icon') ?>" aria-hidden="true"></i></span><span class="hb-kl"><?= sl_v($k, 'l') ?></span></div><b class="hb-kv <?= sl_v($k, 'anim') ?>"><?= sl_v($k, 'v') ?></b><div class="hb-kf"><span class="hb-ks2"><?= sl_v($k, 's') ?></span><span class="hb-sp" aria-hidden="true"><?php foreach ((array) sl_get($k, 'sp', []) as $__i => $h): ?><i style="<?= sl_v($h, '') ?>"></i><?php endforeach; ?></span></div></div><?php endforeach; ?></div>
<div class="hb-p" style="gap:12px"><div class="row between wrap g8"><h2 class="sl-ct">Ask anything about the business</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'focus', []) as $__i => $f): ?><button class="<?= sl_v($f, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($f, 'label') ?></button><?php endforeach; ?></div></div><div class="row g8"><input class="sl-in grow" value="<?= sl_v($d, 'askQ') ?>" data-sl-on="input:setAsk" aria-label="Question"><button class="sl-btn" data-sl-on="click:ask"><i class="ic ic-sm i-spark" aria-hidden="true"></i>Ask</button></div><div class="row wrap g6"><?php foreach ((array) sl_get($d, 'sugg', []) as $__i => $q): ?><button class="chipbtn" data-sl-on="click:go"><?= sl_v($q, 't') ?></button><?php endforeach; ?></div><div class="hb-out"><?= sl_v($d, 'answer') ?></div></div>
<section class="hb-p"><div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">AI suggestions waiting for you</h2><span class="mut sm"><?= sl_v($d, 'sugN') ?> to review · each one is checked by a person before anything changes</span></div></div><div class="ai-sg"><?php foreach ((array) sl_get($d, 'sug', []) as $__i => $x): ?><div class="ai-sc <?= sl_v($x, 'd') ?> <?= sl_v($x, 'done') ?>"><div class="row g10"><span class="ai-si"><i class="ic ic-sm <?= sl_v($x, 'icon') ?>" aria-hidden="true"></i></span><span class="ai-st"><?= sl_v($x, 'tag') ?></span><span class="ai-conf"><?= sl_v($x, 'conf') ?></span></div><b class="fw5"><?= sl_v($x, 't') ?></b><span class="mut" style="font-size:12.5px;line-height:1.45"><?= sl_v($x, 's') ?></span><div class="row g6" style="margin-top:auto"><button class="sl-btn sm grow" data-sl-on="click:go"><?= sl_v($x, 'l') ?></button><button class="sl-btn ghost sm" data-sl-on="click:skip"><?= sl_v($x, 'sl') ?></button></div></div><?php endforeach; ?></div></section>
<div class="hb-g">
<section class="hb-p"><h2 class="sl-ct">Try it: label reader and photo check</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'tries', []) as $__i => $t): ?><button class="<?= sl_v($t, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($t, 'label') ?></button><?php endforeach; ?></div><div class="tk-ph" style="height:110px;border-radius:12px;background:linear-gradient(135deg,#5B5348,#9A8670);display:grid;place-items:center;color:#fff;font-size:12.5px"><?= sl_v($d, 'tryImg') ?></div><div class="hb-out"><?= sl_v($d, 'tryOut') ?></div></section>
<section class="hb-p"><h2 class="sl-ct">Forecasts · next 7 days (<?= sl_v($d, 'fLabel') ?>)</h2><div class="hb-mini" role="img" aria-label="Expected packages per day"><?php foreach ((array) sl_get($d, 'bars', []) as $__i => $b): ?><i class="<?= sl_v($b, 'c') ?>" style="<?= sl_v($b, 'h') ?>" title="<?= sl_v($b, 't') ?>"></i><?php endforeach; ?></div><?php foreach ((array) sl_get($d, 'fc', []) as $__i => $f): ?><div class="hb-l"><span class="grow"><?= sl_v($f, 't') ?></span><b class="mono"><?= sl_v($f, 'v') ?></b></div><?php endforeach; ?></section>
<section class="hb-p"><h2 class="sl-ct">AI WhatsApp assistant</h2><?php foreach ((array) sl_get($d, 'wa', []) as $__i => $m): ?><div class="hb-l"><i class="ic ic-sm <?= sl_v($m, 'icon') ?>" aria-hidden="true"></i><span class="col grow"><b class="fw5"><?= sl_v($m, 'who') ?></b><span class="mut" style="font-size:12px"><?= sl_v($m, 't') ?></span></span><span class="sl-badge"><?= sl_v($m, 'tag') ?></span></div><?php endforeach; ?><span class="mut sm">Answers tracking, quotes and pickup questions on WhatsApp 24/7; hands over to staff when unsure.</span></section>
</div>
<section class="col g10"><div class="row between wrap g8"><h2 class="sl-ct">All 19 features and where they live</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'cf', []) as $__i => $f): ?><button class="<?= sl_v($f, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($f, 'label') ?></button><?php endforeach; ?></div></div><div class="hb-cov"><?php foreach ((array) sl_get($d, 'cov', []) as $__i => $c): ?><a class="hb-i <?= sl_v($c, 'off') ?>" href="<?= sl_v($c, 'href') ?>"><span class="n"><?= sl_v($c, 'n') ?></span><span class="col grow"><b class="fw5 sm"><?= sl_v($c, 't') ?></b><span class="mut" style="font-size:11.5px"><?= sl_v($c, 'where') ?></span></span><span class="sl-pill <?= sl_v($c, 'pc') ?>"><?= sl_v($c, 'st') ?></span></a><?php endforeach; ?></div></section>
