<?php
/**
 * PreAlerts - generated from docs/handover/artifact-source/PreAlerts.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  rows                         as $r
 *   value rows
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Pre-Alerts</h1><p class="sl-lede">Customers tell us what is coming before it reaches the warehouse</p></div>
          <a class="sl-btn" href="<?= sl_route('PreAlerts') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Pre-Alert</a>
        </div>
        <div class="split">
          <div class="a sl-card flush">
            <div class="row wrap g12" style="padding: 14px 16px">
              <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search pre-alerts" placeholder="Search tracking or customer"></div>
              <div class="sl-seg boxed"><a class="on" href="<?= sl_route('PackageDetail') ?>">Open</a><button>Matched</button><button>All</button></div>
            </div>
            <div class="sl-tw"><table class="sl-table">
              <thead><tr><th>Tracking</th><th>Customer</th><th>Supplier</th><th>Expected</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?>
                  <tr><td class="mono sm fw5"><?= sl_v($r, 'trk') ?></td><td><div class="sl-cell"><span class="sl-av sm"><?= sl_v($r, 'ini') ?></span><?= sl_v($r, 'name') ?></div></td><td><?= sl_v($r, 'sup') ?></td><td><?= sl_v($r, 'eta') ?></td><td><span class="sl-pill <?= sl_v($r, 'cls') ?>"><?= sl_v($r, 'st') ?></span></td><td><a class="sl-btn ghost sm" href="<?= sl_route('NewPackage') ?>">Create package</a></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table></div>
            <div class="sl-pager"><span>Showing 1–7 of 9 open pre-alerts</span><div class="sl-pg"><a class="on" href="<?= sl_route('PreAlerts') ?>">1</a><a href="<?= sl_route('PreAlerts') ?>">2</a></div></div>
          </div>
          <aside class="b col g16">
            <section class="sl-card col g12"><h2 class="sl-ct">New pre-alert</h2>
              <div class="sl-field"><span class="sl-lab">Customer</span><div class="sl-sel"><span>Select customer</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
              <div class="sl-field"><label class="sl-lab" for="t">Carrier tracking number</label><input id="t" class="sl-in mono" placeholder="1Z999AA1…"></div>
              <div class="grid cols2" style="gap: 12px"><div class="sl-field"><label class="sl-lab" for="s">Supplier</label><input id="s" class="sl-in" placeholder="Amazon"></div><div class="sl-field"><span class="sl-lab">Mode</span><div class="sl-sel"><span>Air</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div></div>
              <div class="sl-drop" style="padding: 16px"><i class="ic i-upload" aria-hidden="true"></i><span class="sm">Attach invoice (optional)</span></div>
              <button class="sl-btn block">Submit pre-alert</button>
            </section>
            <div class="sl-banner info"><i class="ic i-info" aria-hidden="true"></i><span>When a parcel scans in at the warehouse, a matching pre-alert is linked automatically.</span></div>
          </aside>
        </div>
      
