<?php
/**
 * BillingHub - generated from docs/handover/artifact-source/BillingHub.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  kpis                         as $k
 *   loop  k.sp                         as $h
 *   loop  meths                        as $m
 *   loop  custs                        as $c
 *   loop  accts                        as $a
 *   loop  fees                         as $f
 *   loop  rec                          as $r
 *   loop  cf                           as $f
 *   loop  cov                          as $c
 *   if    flash
 *   if    m
 *   value accts, bulk, bulkL, calc, cf, cov, custs, dims, false, fees, flash, flipM, h, kpis, m, mAria, mCls, mNote, mOff, meths, rec, recon, reconL, refund, setDims, setW, true, w
 * sl-wrap extras in the design: style="max-width:1440px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">Billing and finance</h1><p class="sl-lede">Pricing, invoices, accounts, payments and reports. Switch the whole finance side off if you only want to move parcels.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('FinancialOverview') ?>">Financial overview</a></div></div>
<?php if (sl_get($d, 'flash')): ?><div class="fr-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?>
<div class="hb-master <?= sl_v($d, 'mOff') ?>"><span class="sl-tile"><i class="ic i-dollar" aria-hidden="true"></i></span><span class="col grow"><b class="fw5">Finance in the workflow</b><span class="mut sm"><?= sl_v($d, 'mNote') ?></span></span><button class="sl-toggle <?= sl_v($d, 'mCls') ?>" role="switch" aria-checked="<?= sl_v($d, 'mAria') ?>" aria-label="Finance in the workflow" data-sl-on="click:flipM"></button></div>
<div class="hb-ks"><?php foreach ((array) sl_get($d, 'kpis', []) as $__i => $k): ?><div class="hb-k"><div class="hb-kh"><span class="hb-ki"><i class="ic ic-sm <?= sl_v($k, 'icon') ?>" aria-hidden="true"></i></span><span class="hb-kl"><?= sl_v($k, 'l') ?></span></div><b class="hb-kv <?= sl_v($k, 'anim') ?>"><?= sl_v($k, 'v') ?></b><div class="hb-kf"><span class="hb-ks2"><?= sl_v($k, 's') ?></span><span class="hb-sp" aria-hidden="true"><?php foreach ((array) sl_get($k, 'sp', []) as $__i => $h): ?><i style="<?= sl_v($h, '') ?>"></i><?php endforeach; ?></span></div></div><?php endforeach; ?></div>
<?php if (sl_get($d, 'm')): ?><div class="hb-g">
<section class="hb-p"><h2 class="sl-ct">Rate calculator</h2><div class="grid cols2" style="gap:10px"><label class="sl-field"><span class="sl-lab">Actual weight (lb)</span><input class="sl-in mono" value="<?= sl_v($d, 'w') ?>" data-sl-on="input:setW"></label><label class="sl-field"><span class="sl-lab">Box L × W × H (in)</span><input class="sl-in mono" value="<?= sl_v($d, 'dims') ?>" data-sl-on="input:setDims"></label></div><div class="row g8 wrap"><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'meths', []) as $__i => $m): ?><button class="<?= sl_v($m, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($m, 'label') ?></button><?php endforeach; ?></div><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'custs', []) as $__i => $c): ?><button class="<?= sl_v($c, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($c, 'label') ?></button><?php endforeach; ?></div></div>
<div class="hb-out"><?= sl_v($d, 'calc') ?></div></section>
<section class="hb-p"><h2 class="sl-ct">Customer accounts</h2><?php foreach ((array) sl_get($d, 'accts', []) as $__i => $a): ?><div class="hb-l"><span class="col grow"><b class="fw5"><?= sl_v($a, 'n') ?></b><span class="mut" style="font-size:12px"><?= sl_v($a, 'type') ?> · limit <?= sl_v($a, 'lim') ?></span></span><span class="col" style="align-items:flex-end"><b class="mono"><?= sl_v($a, 'bal') ?></b><span style="font-size:11.5px;color:<?= sl_v($a, 'c') ?>"><?= sl_v($a, 'note') ?></span></span><button class="sl-btn ghost sm" data-sl-on="click:top"><?= sl_v($a, 'btn') ?></button></div><?php endforeach; ?><a class="sm fw5" href="<?= sl_route('CustomerProfile') ?>">Statements and audit trail on each customer →</a></section>
<section class="hb-p"><h2 class="sl-ct">Contract rates and fees</h2><?php foreach ((array) sl_get($d, 'fees', []) as $__i => $f): ?><div class="hb-l"><span class="grow"><?= sl_v($f, 't') ?></span><span class="mono"><?= sl_v($f, 'v') ?></span></div><?php endforeach; ?></section>
<section class="hb-p"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Reconciliation · today</h2><button class="sl-btn ghost sm" data-sl-on="click:recon"><?= sl_v($d, 'reconL') ?></button></div><?php foreach ((array) sl_get($d, 'rec', []) as $__i => $r): ?><div class="hb-l"><span class="hb-dot <?= sl_v($r, 'd') ?>"></span><span class="col grow"><b class="fw5"><?= sl_v($r, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($r, 's') ?></span></span><span class="mono"><?= sl_v($r, 'v') ?></span></div><?php endforeach; ?></section>
<section class="hb-p"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Bulk invoices and adjustments</h2></div><div class="row g8 wrap"><button class="sl-btn" data-sl-on="click:bulk"><?= sl_v($d, 'bulkL') ?></button><button class="sl-btn ghost" data-sl-on="click:refund">Refund or adjust</button></div><span class="mut sm">Invoices for every package that arrived without one, sent by WhatsApp with a pay link. Adjustments need a reason and show in the audit trail.</span></section>
</div><?php endif; ?>
<section class="col g10"><div class="row between wrap g8"><h2 class="sl-ct">All 30 features and where they live</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'cf', []) as $__i => $f): ?><button class="<?= sl_v($f, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($f, 'label') ?></button><?php endforeach; ?></div></div><div class="hb-cov"><?php foreach ((array) sl_get($d, 'cov', []) as $__i => $c): ?><a class="hb-i <?= sl_v($c, 'off') ?>" href="<?= sl_v($c, 'href') ?>"><span class="n"><?= sl_v($c, 'n') ?></span><span class="col grow"><b class="fw5 sm"><?= sl_v($c, 't') ?></b><span class="mut" style="font-size:11.5px"><?= sl_v($c, 'where') ?></span></span><span class="sl-pill <?= sl_v($c, 'pc') ?>"><?= sl_v($c, 'st') ?></span></a><?php endforeach; ?></div></section>
