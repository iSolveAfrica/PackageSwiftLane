<?php
/**
 * SupportHub - generated from docs/handover/artifact-source/SupportHub.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  kpis                         as $k
 *   loop  k.sp                         as $h
 *   loop  sla                          as $x
 *   loop  esc                          as $e
 *   loop  cats                         as $c
 *   loop  kb                           as $k
 *   loop  dis                          as $d
 *   loop  cf                           as $f
 *   loop  cov                          as $c
 *   if    flash
 *   value cats, cf, cov, dis, esc, false, flash, h, kb, kpis, sla
 * sl-wrap extras in the design: style="max-width:1440px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">Support and CRM</h1><p class="sl-lede">Helpdesk, tickets, SLAs, escalations, help centre and disputes, linked to every customer and shipment.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('CsReports') ?>">Reports</a><a class="sl-btn" href="<?= sl_route('CsInbox') ?>">Support inbox</a></div></div>
<?php if (sl_get($d, 'flash')): ?><div class="fr-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?>

<div class="hb-ks"><?php foreach ((array) sl_get($d, 'kpis', []) as $__i => $k): ?><div class="hb-k"><div class="hb-kh"><span class="hb-ki"><i class="ic ic-sm <?= sl_v($k, 'icon') ?>" aria-hidden="true"></i></span><span class="hb-kl"><?= sl_v($k, 'l') ?></span></div><b class="hb-kv <?= sl_v($k, 'anim') ?>"><?= sl_v($k, 'v') ?></b><div class="hb-kf"><span class="hb-ks2"><?= sl_v($k, 's') ?></span><span class="hb-sp" aria-hidden="true"><?php foreach ((array) sl_get($k, 'sp', []) as $__i => $h): ?><i style="<?= sl_v($h, '') ?>"></i><?php endforeach; ?></span></div></div><?php endforeach; ?></div>
<div class="hb-g"><section class="hb-p"><h2 class="sl-ct">SLA timers · live</h2><?php foreach ((array) sl_get($d, 'sla', []) as $__i => $x): ?><div class="hb-l"><span class="hb-dot <?= sl_v($x, 'd') ?>"></span><span class="col grow"><b class="fw5"><?= sl_v($x, 'id') ?> · <?= sl_v($x, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($x, 'who') ?> · <?= sl_v($x, 'cat') ?></span></span><b class="mono" style="color:<?= sl_v($x, 'c') ?>"><?= sl_v($x, 'left') ?></b></div><?php endforeach; ?></section>
<section class="hb-p"><h2 class="sl-ct">Escalation rules</h2><?php foreach ((array) sl_get($d, 'esc', []) as $__i => $e): ?><div class="hb-l"><span class="grow"><?= sl_v($e, 't') ?></span><button class="sl-toggle <?= sl_v($e, 'cls') ?>" role="switch" aria-checked="<?= sl_v($e, 'aria') ?>" aria-label="<?= sl_v($e, 't') ?>" data-sl-on="click:flip"></button></div><?php endforeach; ?></section>
<section class="hb-p"><h2 class="sl-ct">Complaint categories · this month</h2><?php foreach ((array) sl_get($d, 'cats', []) as $__i => $c): ?><div class="ai-bar2" style="display:grid;grid-template-columns:140px 1fr 40px;gap:8px;align-items:center;font-size:12.5px"><span><?= sl_v($c, 't') ?></span><i style="display:block;height:8px;border-radius:9px;background:#A9A59C;<?= sl_v($c, 'w') ?>"></i><b class="mono" style="text-align:right"><?= sl_v($c, 'n') ?></b></div><?php endforeach; ?></section>
<section class="hb-p"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Help centre</h2><span class="mut sm">Answers customers find themselves</span></div><?php foreach ((array) sl_get($d, 'kb', []) as $__i => $k): ?><div class="hb-l"><i class="ic ic-sm i-book" aria-hidden="true"></i><span class="grow"><?= sl_v($k, 't') ?></span><span class="mut mono" style="font-size:12px"><?= sl_v($k, 'v') ?> views</span></div><?php endforeach; ?></section>
<section class="hb-p"><h2 class="sl-ct">Disputes</h2><?php foreach ((array) sl_get($d, 'dis', []) as $__i => $it_d): ?><div class="hb-l"><span class="col grow"><b class="fw5"><?= sl_v($it_d, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($it_d, 's') ?></span></span><span class="sl-pill <?= sl_v($it_d, 'pc') ?>"><?= sl_v($it_d, 'st') ?></span></div><?php endforeach; ?><span class="mut sm">Steps: customer opens → evidence pulled from tracking → manager decides within 48 h → refund, redelivery or closed.</span></section></div>
<section class="col g10"><div class="row between wrap g8"><h2 class="sl-ct">All 20 features and where they live</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'cf', []) as $__i => $f): ?><button class="<?= sl_v($f, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($f, 'label') ?></button><?php endforeach; ?></div></div><div class="hb-cov"><?php foreach ((array) sl_get($d, 'cov', []) as $__i => $c): ?><a class="hb-i <?= sl_v($c, 'off') ?>" href="<?= sl_v($c, 'href') ?>"><span class="n"><?= sl_v($c, 'n') ?></span><span class="col grow"><b class="fw5 sm"><?= sl_v($c, 't') ?></b><span class="mut" style="font-size:11.5px"><?= sl_v($c, 'where') ?></span></span><span class="sl-pill <?= sl_v($c, 'pc') ?>"><?= sl_v($c, 'st') ?></span></a><?php endforeach; ?></div></section>
