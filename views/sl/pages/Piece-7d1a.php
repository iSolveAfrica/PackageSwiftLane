<?php
/**
 * Piece-7d1a - generated from docs/handover/artifact-source/Piece-7d1a.dc.html by scripts/sl_convert.py.
 * Shell page: no (plain page)
 * Data contract ($d):
 *   loop  groups                       as $g
 *   loop  groups                       as $g
 *   loop  g.top                        as $it
 *   value groups
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-app dk" style="position: absolute; left: 0px; top: 0px; right: auto; bottom: auto; margin: 0px; box-sizing: border-box; width: 1440px;">
<div class="sx">
  <div class="sx-top">
    <div class="sx-crumb"><a href="<?= sl_route('ControlPanels') ?>">Control panels</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Settings</span></div>
    <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('ActivityLogs') ?>"><i class="ic ic-sm i-clock" aria-hidden="true"></i>Change history</a><a class="sl-btn" href="<?= sl_route('Main') ?>"><i class="ic ic-sm i-left" aria-hidden="true"></i>Back to app</a></div>
  </div>

  <section class="sx-hero">
    <span class="sl-tile" style="width:52px;height:52px;border-radius:16px"><i class="ic i-cog" aria-hidden="true"></i></span>
    <h1>How can we set things up?</h1>
    <p>Everything Swift Lane is configured by — company, money, operations, messaging and access — in one place.</p>
    <label class="sx-find"><i class="ic i-search" aria-hidden="true"></i><input aria-label="Search settings" placeholder="Search 81 settings, e.g. exchange rate or SMS"><kbd>⌘K</kbd></label>
    <div class="sx-sugg"><span>Popular:</span><a href="<?= sl_route('FinancialOverview') ?>"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Exchange rate</a><a href="<?= sl_route('Notifications') ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp API</a><a href="<?= sl_route('StaffRoles') ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i>Roles</a><a href="<?= sl_route('DevInfrastructure') ?>"><i class="ic ic-sm i-database" aria-hidden="true"></i>Backup</a></div>
  </section>

  <div class="sx-strip">
    <section class="sx-box">
      <div class="row between wrap g8"><div><h2 class="sl-ct" style="font-size:16px">Finish setting up</h2><div class="mut sm">7 of 10 steps done</div></div><span class="mono sm">70%</span></div>
      <div class="sl-ticks" role="img" aria-label="70% complete"><i style="width:70%"></i></div>
      <div>
        <div class="sx-step"><span class="ck on"><i class="ic ic-sm i-check" aria-hidden="true"></i></span><span class="grow mut">Company profile and logo</span></div>
        <div class="sx-step todo"><span class="ck" aria-hidden="true"></span><b class="grow">Add your US warehouse address</b><a class="sl-btn sm" href="<?= sl_route('ShippingAddresses') ?>">Set address</a></div>
        <div class="sx-step todo"><span class="ck" aria-hidden="true"></span><b class="grow">Connect an SMS sender ID</b><a class="sl-btn sm ghost" href="<?= sl_route('NewIntegration') ?>">Connect</a></div>
        <div class="sx-step todo"><span class="ck" aria-hidden="true"></span><b class="grow">Turn on daily backups off-site</b><a class="sl-btn sm ghost" href="<?= sl_route('DevInfrastructure') ?>">Set up</a></div>
      </div>
    </section>
    <section class="sx-box">
      <div class="row between"><h2 class="sl-ct" style="font-size:16px">Recently changed</h2><a class="sm fw5" href="<?= sl_route('ActivityLogs') ?>">View log</a></div>
      <div>
        <div class="sx-log"><span class="sl-av sm">HC</span><div class="grow"><b class="fw5">Henry</b> changed <b class="fw5">Air express rate</b> to $6.00 / lb<div class="mut sm">Today, 09:38</div></div></div>
        <div class="sx-log"><span class="sl-av sm">NL</span><div class="grow"><b class="fw5">Nii</b> set exchange rate to <span class="mono">USD 1 = GHS 12.40</span><div class="mut sm">Yesterday, 17:02</div></div></div>
        <div class="sx-log"><span class="sl-av sm">AS</span><div class="grow"><b class="fw5">Adwoa</b> edited the <b class="fw5">Arrived at warehouse</b> WhatsApp template<div class="mut sm">Nov 3, 11:15</div></div></div>
        <div class="sx-log"><span class="sl-av sm">HC</span><div class="grow"><b class="fw5">Henry</b> added the role <b class="fw5">Night shift lead</b><div class="mut sm">Nov 2, 20:40</div></div></div>
      </div>
    </section>
  </div>

  <nav class="sx-tabs" aria-label="Setting categories"><a class="on" href="#st-all">All</a><?php foreach ((array) sl_get($d, 'groups', []) as $__i => $g): ?><a href="<?= sl_v($g, 'anchor') ?>"><?= sl_v($g, 'title') ?></a><?php endforeach; ?></nav>

  <div class="sx-grid" id="st-all">
    <?php foreach ((array) sl_get($d, 'groups', []) as $__i => $g): ?>
      <section class="sx-tile">
        <div class="sx-th"><span class="tl"><i class="ic <?= sl_v($g, 'icon') ?>" aria-hidden="true"></i></span><div><b><?= sl_v($g, 'title') ?></b><small><?= sl_v($g, 'desc') ?></small></div></div>
        <div class="sx-links"><?php foreach ((array) sl_get($g, 'top', []) as $__i => $it): ?><a href="<?= sl_v($it, 'href') ?>"><i class="ic ic-sm <?= sl_v($it, 'icon') ?>" aria-hidden="true"></i><span class="col" style="min-width:0"><span><?= sl_v($it, 'label') ?></span><small class="mut" style="font-size:12px"><?= sl_v($it, 'sub') ?></small></span><span class="v"><?= sl_v($it, 'val') ?></span></a><?php endforeach; ?></div>
        <div class="sx-ft"><span><?= sl_v($g, 'count') ?> · <?= sl_v($g, 'more') ?></span><a href="<?= sl_v($g, 'anchor') ?>">Open<i class="ic ic-sm i-right" aria-hidden="true"></i></a></div>
      </section>
    <?php endforeach; ?>
  </div>
</div>
</div>
