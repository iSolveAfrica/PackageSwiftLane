<?php
/**
 * AirShipments - generated from docs/handover/artifact-source/AirShipments.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  rows                         as $r
 *   loop  acts                         as $a
 *   if    r.open
 *   value acts, false, rows
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Air Shipments</h1><p class="sl-lede row g8" style="align-items:center"><span class="chan v"><i class="ic ic-sm i-spark" aria-hidden="true"></i></span><span><b class="fw5">AI insight</b> · 128 registered this month · 12 need attention: 5 held at customs, 4 unpaid, 3 missing a carrier number</span></p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('SeaPackages') ?>"><i class="ic ic-sm i-layers" aria-hidden="true"></i>Package management</a><a class="sl-btn ghost" href="<?= sl_route('CsAI') ?>"><i class="ic ic-sm i-spark" aria-hidden="true"></i>Ask AI</a><a class="sl-btn ghost" href="<?= sl_route('ImportData') ?>"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Import Excel</a><a class="sl-btn ghost" href="<?= sl_route('Documents') ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export</a><a class="sl-btn" href="<?= sl_route('NewShipment') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Shipment</a></div>
        </div>

        <div class="grid cols4">
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Open / in progress</span></div><div class="sl-mv">54 <i>/ 128</i></div><div class="sl-ticks y"><i style="width: 42%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Delivered</span></div><div class="sl-mv">61 <i>/ 128</i></div><div class="sl-ticks"><i style="width: 48%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Awaiting payment</span></div><div class="sl-mv">9 <i>/ 128</i></div><div class="sl-ticks r"><i style="width: 7%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span>In consolidations</span></div><div class="sl-mv">4 <i>/ 128</i></div><div class="sl-ticks v"><i style="width: 3%"></i></div></div>
        </div>

        <div class="sl-card flush">
          <div class="row wrap g12" style="padding: 14px 16px">
            <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search shipments" placeholder="Search tracking, recipient or sender"></div>
            <div class="sl-sel" style="width: 160px"><span>All statuses</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div>
            <div class="sl-sel" style="width: 150px"><span>All drivers</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div>
            <div class="sl-seg boxed"><button class="on">30d</button><button>7d</button><button>Today</button></div>
            <span class="grow"></span>
            <span class="mut sm">Rows</span><div class="sl-sel" style="width: 76px"><span>25</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div>
          </div>
          <div class="row g12 wrap" style="padding: 10px 16px; background: var(--active)">
            <strong>2 selected</strong><span class="grow"></span>
            <a class="sl-btn ghost sm" href="<?= sl_route('ShipmentDetail') ?>"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Update status</a>
            <a class="sl-btn ghost sm" href="<?= sl_route('Drivers') ?>"><i class="ic ic-sm i-wheel" aria-hidden="true"></i>Assign driver</a>
            <a class="sl-btn ghost sm" href="<?= sl_route('LabelStudio') ?>"><i class="ic ic-sm i-print" aria-hidden="true"></i>Print labels</a>
            <button class="sl-btn ghost sm" style="border-color: transparent; background: transparent">Clear</button>
          </div>
          <div class="sl-tw">
            <table class="sl-table">
              <thead><tr><th style="width: 40px"><span class="sl-chk"></span></th><th>Swift tracking</th><th>Carrier tracking</th><th>Date</th><th>Sender</th><th>Recipient</th><th>Destination</th><th class="num">Weight</th><th>Payment</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?>
                  <tr>
                    <td><span class="sl-chk <?= sl_v($r, 'chk') ?>"></span></td>
                    <td><a class="id" href="<?= sl_route('ShipmentDetail') ?>"><?= sl_v($r, 'id') ?></a></td>
                    <td class="mono mut sm"><?= sl_v($r, 'carrier') ?></td>
                    <td><?= sl_v($r, 'date') ?></td>
                    <td><div><?= sl_v($r, 'sender') ?><span class="sub"><?= sl_v($r, 'from') ?></span></div></td>
                    <td><div class="sl-cell"><span class="sl-av sm"><?= sl_v($r, 'ini') ?></span><?= sl_v($r, 'name') ?></div></td>
                    <td><?= sl_v($r, 'dest') ?></td>
                    <td class="num"><?= sl_v($r, 'kg') ?></td>
                    <td><span class="sl-pill <?= sl_v($r, 'payCls') ?>"><?= sl_v($r, 'pay') ?></span></td>
                    <td><span class="sl-pill <?= sl_v($r, 'stCls') ?>"><?= sl_v($r, 'st') ?></span></td>
                    <td class="as-dots"><button class="as-more <?= sl_v($r, 'mCls') ?>" aria-label="More actions for <?= sl_v($r, 'id') ?>" aria-expanded="<?= sl_v($r, 'mAria') ?>" data-sl-on="click:toggle"><i class="ic i-more" aria-hidden="true"></i></button></td>
                  </tr>
                  <?php if (sl_get($r, 'open')): ?><tr class="as-mrow"><td colspan="11"><div class="as-menu" role="menu" aria-label="Actions for <?= sl_v($r, 'id') ?>"><div class="as-mh"><b class="fw5"><?= sl_v($r, 'id') ?></b><span class="mut sm"><?= sl_v($r, 'name') ?> · <?= sl_v($r, 'dest') ?></span><span class="grow"></span><button class="sl-ib" style="width:34px;height:34px" aria-label="Close" data-sl-on="click:toggle"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div><div class="as-acts"><?php foreach ((array) sl_get($d, 'acts', []) as $__i => $a): ?><a class="as-a <?= sl_v($a, 'cls') ?>" role="menuitem" href="<?= sl_v($a, 'href') ?>"><i class="ic ic-sm <?= sl_v($a, 'icon') ?>" aria-hidden="true"></i><?= sl_v($a, 'label') ?></a><?php endforeach; ?></div></div></td></tr><?php endif; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="sl-pager"><span>Showing 1–9 of 128 shipments</span><div class="sl-pg"><a href="<?= sl_route('AirShipments') ?>" aria-label="Previous"><i class="ic ic-sm i-left" aria-hidden="true"></i></a><a class="on" href="<?= sl_route('AirShipments') ?>">1</a><a href="<?= sl_route('AirShipments') ?>">2</a><a href="<?= sl_route('AirShipments') ?>">3</a><a href="<?= sl_route('AirShipments') ?>">…</a><a href="<?= sl_route('AirShipments') ?>">6</a><a href="<?= sl_route('AirShipments') ?>" aria-label="Next"><i class="ic ic-sm i-right" aria-hidden="true"></i></a></div></div>
        </div>
      
