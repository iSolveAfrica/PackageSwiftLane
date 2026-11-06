<?php
/**
 * CostSettings - generated from docs/handover/artifact-source/CostSettings.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  infra                        as $c
 *   loop  msgs                         as $c
 *   loop  ocs                          as $o
 *   loop  o.cats                       as $c
 *   loop  o.freqs                      as $q
 *   loop  o.freqs                      as $q
 *   loop  ovs                          as $o
 *   loop  totals                       as $t
 *   if    saved
 *   value addOc, false, from, grand, infra, msgs, ocNote, ocs, ovCount, ovs, save, saveLabel, saved, servers, setServers, totals
 * sl-wrap extras in the design: style="max-width:1180px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="row g8 mut sm"><a href="<?= sl_route('ControlPanels') ?>">Settings</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Operating costs</span></div>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Operating costs</h1><p class="sl-lede">What Swift Lane pays per unit, and developer overrides for the Infrastructure page. These values feed Infrastructure, Usage &amp; Billing and Message Costs.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('DevInfrastructure') ?>"><i class="ic ic-sm i-cloud" aria-hidden="true"></i>See on Infrastructure</a><button class="sl-btn" data-sl-on="click:save"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'saveLabel') ?></button></div></div>
<div class="cs-lock"><i class="ic ic-sm i-lock" aria-hidden="true"></i><span class="grow"><b class="fw5">Super admin only.</b> Other roles see these values but cannot change them.</span><span class="sl-av sm dark">HC</span><span class="sm">Henry Cobblah · Super admin</span></div>
<?php if (sl_get($d, 'saved')): ?><div class="cs-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i>Saved. New costs apply from <?= sl_v($d, 'from') ?> and now show on Infrastructure and Message Costs.</div><?php endif; ?>
<div class="split">
<div class="a col g16">
<section class="col g12"><div class="up">Servers and infrastructure</div>
<?php foreach ((array) sl_get($d, 'infra', []) as $__i => $c): ?><div class="cs-in"><span class="tl"><i class="ic <?= sl_v($c, 'icon') ?>" aria-hidden="true"></i></span><div class="nm"><b><?= sl_v($c, 'label') ?></b><small><?= sl_v($c, 'hint') ?></small></div><label class="cs-money"><span><?= sl_v($c, 'cur') ?></span><input aria-label="<?= sl_v($c, 'label') ?>" value="<?= sl_v($c, 'val') ?>" data-sl-on="change:set"></label><span class="mut sm" style="width:84px"><?= sl_v($c, 'unit') ?></span></div><?php endforeach; ?></section>
<section class="col g12"><div class="up">Messages</div>
<?php foreach ((array) sl_get($d, 'msgs', []) as $__i => $c): ?><div class="cs-in"><span class="tl"><i class="ic <?= sl_v($c, 'icon') ?>" aria-hidden="true"></i></span><div class="nm"><b><?= sl_v($c, 'label') ?></b><small><?= sl_v($c, 'hint') ?></small></div><label class="cs-money"><span><?= sl_v($c, 'cur') ?></span><input aria-label="<?= sl_v($c, 'label') ?>" value="<?= sl_v($c, 'val') ?>" data-sl-on="change:set"></label><span class="mut sm" style="width:84px"><?= sl_v($c, 'unit') ?></span></div><?php endforeach; ?></section>
<section class="sl-card col g12"><div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Other costs</h2><div class="mut sm">Rent, staff, fuel, software, customs fees and anything else. Ticked ones show on Usage &amp; Billing.</div></div><button class="sl-btn ghost sm" data-sl-on="click:addOc"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add cost</button></div>
<?php foreach ((array) sl_get($d, 'ocs', []) as $__i => $o): ?><div class="oc-r"><div class="oc-l1"><input class="sl-in" value="<?= sl_v($o, 'name') ?>" aria-label="Cost name" data-sl-on="change:setName"><label class="cs-money"><span>₵</span><input aria-label="Amount" value="<?= sl_v($o, 'amt') ?>" data-sl-on="change:setAmt"></label><button class="sl-ib" aria-label="Remove cost" data-sl-on="click:del"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></div><div class="oc-l2"><div class="oc-cat"><?php foreach ((array) sl_get($o, 'cats', []) as $__i => $c): ?><button class="<?= sl_v($c, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($c, 'label') ?></button><?php endforeach; ?></div><div class="sl-seg boxed" style="height:34px"><?php foreach ((array) sl_get($o, 'freqs', []) as $__i => $q): ?><button class="<?= sl_v($q, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($q, 'label') ?></button><?php endforeach; ?></div><span class="row g8 sm" style="margin-left:auto"><button class="sl-toggle <?= sl_v($o, 'bCls') ?>" role="switch" aria-checked="<?= sl_v($o, 'bAria') ?>" aria-label="Show on billing" data-sl-on="click:flipB"></button>On billing</span></div></div><?php endforeach; ?></div><div class="sl-seg boxed" style="height:36px"><?php foreach ((array) sl_get($d, 'o.freqs', []) as $__i => $q): ?><button class="<?= sl_v($q, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($q, 'label') ?></button><?php endforeach; ?></div><label class="cs-money"><span>₵</span><input aria-label="Amount" value="<?= sl_v($d, 'o.amt') ?>" data-sl-on="change:o.setAmt"></label><button class="sl-toggle <?= sl_v($d, 'o.bCls') ?>" role="switch" aria-checked="<?= sl_v($d, 'o.bAria') ?>" aria-label="Show on billing" data-sl-on="click:o.flipB"></button><button class="sl-ib" aria-label="Remove cost" data-sl-on="click:o.del"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></div><!-- stray </sc-for> in the design source: review this block -->
<div class="row between wrap g8 sm" style="padding-top:10px;border-top:1px solid var(--line)"><span class="mut"><?= sl_v($d, 'ocNote') ?></span><a class="fw5" href="<?= sl_route('DevBilling') ?>">See on Usage &amp; Billing →</a></div></section>
<section class="sl-card col g12"><div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Infrastructure figures</h2><div class="mut sm">Override what the Infrastructure page shows, for example while a provider bill is wrong</div></div><span class="sl-pill sorting"><i class="ic ic-sm i-terminal" aria-hidden="true"></i>Developers only</span></div>
<?php foreach ((array) sl_get($d, 'ovs', []) as $__i => $o): ?><div class="cs-in" style="padding:10px 14px"><div class="nm"><b><?= sl_v($o, 'label') ?></b><small>Live value: <?= sl_v($o, 'live') ?></small></div><button class="sl-toggle <?= sl_v($o, 'on') ?>" role="switch" aria-checked="<?= sl_v($o, 'aria') ?>" aria-label="Override <?= sl_v($o, 'label') ?>" data-sl-on="click:flip"></button><label class="cs-money" style="opacity:<?= sl_v($o, 'op') ?>"><span><?= sl_v($o, 'cur') ?></span><input aria-label="<?= sl_v($o, 'label') ?> override" value="<?= sl_v($o, 'val') ?>" data-sl-on="change:set"></label></div><?php endforeach; ?>
<div class="mut sm"><?= sl_v($d, 'ovCount') ?> overridden. Overridden figures show an “Override” tag on Infrastructure. Every change is written to System Logs.</div></section>
<section class="sl-card col g12"><div class="grid cols2"><div class="sl-field"><span class="sl-lab">Number of servers running</span><label class="cs-money" style="width:100%"><span>×</span><input aria-label="Number of servers" value="<?= sl_v($d, 'servers') ?>" data-sl-on="change:setServers"></label></div><div class="sl-field"><span class="sl-lab">New costs apply from</span><div class="sl-sel"><span><?= sl_v($d, 'from') ?></span><i class="ic ic-sm i-calendar" aria-hidden="true"></i></div></div></div></section>
</div>
<aside class="b col g16">
<section class="sl-card col"><div class="sl-ch"><h2 class="sl-ct">This month at these costs</h2><span class="mut sm">Based on usage so far</span></div>
<?php foreach ((array) sl_get($d, 'totals', []) as $__i => $t): ?><div class="cs-tot"><span class="col"><span><?= sl_v($t, 'label') ?></span><span class="mut" style="font-size:12px"><?= sl_v($t, 'calc') ?></span></span><b class="mono fw5"><?= sl_v($t, 'total') ?></b></div><?php endforeach; ?>
<div class="cs-tot" style="border-top:1.5px solid var(--ink);font-size:15px"><b>Total</b><b class="mono"><?= sl_v($d, 'grand') ?></b></div></section>
<section class="sl-card col g8"><h2 class="sl-ct">Change history</h2><div class="cs-tot"><span>SMS ₵0.025 → ₵0.030</span><span class="mut">Aug 1 · HC</span></div><div class="cs-tot"><span>Server $0.38 → $0.42 / hr</span><span class="mut">Jul 1 · HC</span></div><div class="cs-tot"><span>WhatsApp ₵0.10 → ₵0.12</span><span class="mut">Jun 1 · HC</span></div></section>
</aside></div>
