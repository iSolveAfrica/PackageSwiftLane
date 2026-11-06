<?php
/**
 * Consolidations - generated from docs/handover/artifact-source/Consolidations.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  rows                         as $r
 *   value rows
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Consolidations</h1><p class="sl-lede">Group parcels into containers and flights before they leave the US</p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('Documents') ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Packing list</a><a class="sl-btn" href="<?= sl_route('ConsolidationDetail') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Consolidation</a></div>
        </div>
        <div class="grid cols3">
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-layers" aria-hidden="true"></i>Open containers</span></div><div class="sl-mv">3 / 7 <i>still accepting parcels</i></div><div class="sl-ticks y"><i style="width: 43%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-ship" aria-hidden="true"></i>In transit</span></div><div class="sl-mv">3 / 7 <i>at sea or in the air</i></div><div class="sl-ticks v"><i style="width: 43%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-check" aria-hidden="true"></i>Arrived this month</span></div><div class="sl-mv">1 / 7 <i>cleared and unloaded</i></div><div class="sl-ticks"><i style="width: 14%"></i></div></div>
        </div>
        <div class="sl-card flush">
          <div class="row wrap g12" style="padding: 14px 16px">
            <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search consolidations" placeholder="Search container, shipping number, AWB or B/L"></div>
            <div class="sl-seg boxed"><button class="on">All</button><button>Air</button><button>Sea</button></div>
          </div>
          <div class="sl-tw"><table class="sl-table">
            <thead><tr><th>Container</th><th>Mode</th><th>Route</th><th>Shipping number</th><th>Air waybill / B/L</th><th>Flight / vessel</th><th style="min-width: 170px">Fill</th><th>Parcels</th><th>Departs</th><th>ETA</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?>
                <tr><td><a class="id" href="<?= sl_v($r, 'href') ?>"><?= sl_v($r, 'id') ?></a></td><td><span class="sl-badge <?= sl_v($r, 'mode') ?>"><?= sl_v($r, 'modeL') ?></span></td><td><?= sl_v($r, 'route') ?></td><td><span class="mono fw5"><?= sl_v($r, 'ship') ?></span></td><td><span class="row g6"><span class="mono"><?= sl_v($r, 'awb') ?></span><a class="sl-ib sm" aria-label="Open waybill <?= sl_v($r, 'awb') ?>" href="<?= sl_route('Documents') ?>"><i class="ic ic-sm i-file" aria-hidden="true"></i></a></span><span class="sub"><?= sl_v($r, 'awbType') ?></span></td><td><?= sl_v($r, 'carrier') ?><span class="sub mono"><?= sl_v($r, 'voy') ?></span></td><td><div class="col g6"><div class="sl-ticks <?= sl_v($r, 'tc') ?>"><i style="<?= sl_v($r, 'sty') ?>"></i></div><span class="mut sm"><?= sl_v($r, 'fill') ?></span></div></td><td><span class="fw5"><?= sl_v($r, 'n') ?></span><span class="sub">parcels</span></td><td><?= sl_v($r, 'dep') ?></td><td><?= sl_v($r, 'eta') ?></td><td><span class="sl-pill <?= sl_v($r, 'cls') ?>"><?= sl_v($r, 'st') ?></span></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </div>
      
