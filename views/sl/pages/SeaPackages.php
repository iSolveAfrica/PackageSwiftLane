<?php
/**
 * SeaPackages - generated from docs/handover/artifact-source/SeaPackages.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  rows                         as $r
 *   value rows
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Sea Packages</h1><p class="sl-lede">64 packages · next vessel closes Nov 12</p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('Documents') ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export</a><a class="sl-btn" href="<?= sl_route('NewPackage') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Package</a></div>
        </div>
        <div class="grid cols4">
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-warehouse" aria-hidden="true"></i>At US warehouse</span></div><div class="sl-mv">22 <i>/ 64</i></div><div class="sl-ticks y"><i style="width: 34%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-ship" aria-hidden="true"></i>At sea</span></div><div class="sl-mv">28 <i>/ 64</i></div><div class="sl-ticks v"><i style="width: 44%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-shield" aria-hidden="true"></i>Customs</span></div><div class="sl-mv">6 <i>/ 64</i></div><div class="sl-ticks o"><i style="width: 9%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-check" aria-hidden="true"></i>Delivered</span></div><div class="sl-mv">8 <i>/ 64</i></div><div class="sl-ticks"><i style="width: 13%"></i></div></div>
        </div>
        <div class="sl-card flush">
          <div class="row wrap g12" style="padding: 14px 16px">
            <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search packages" placeholder="Search package, customer or container"></div>
            <div class="sl-sel" style="width: 160px"><span>All statuses</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div>
            <div class="sl-sel" style="width: 170px"><span>All containers</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div>
            <span class="grow"></span>
            <div class="sl-seg boxed"><button class="on">List</button><button>By container</button></div>
          </div>
          <div class="sl-tw"><table class="sl-table">
            <thead><tr><th style="width: 40px"><span class="sl-chk"></span></th><th>Package</th><th>Customer</th><th>Container</th><th>Items</th><th class="num">Volume</th><th>Payment</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?>
                <tr><td><span class="sl-chk"></span></td><td><a class="id" href="<?= sl_route('PackageDetail') ?>"><?= sl_v($r, 'id') ?></a></td><td><div class="sl-cell"><span class="sl-av sm"><?= sl_v($r, 'ini') ?></span><?= sl_v($r, 'name') ?></div></td><td class="mono sm"><?= sl_v($r, 'box') ?></td><td><?= sl_v($r, 'items') ?></td><td class="num"><?= sl_v($r, 'vol') ?></td><td><span class="sl-pill <?= sl_v($r, 'payCls') ?>"><?= sl_v($r, 'pay') ?></span></td><td><span class="sl-pill <?= sl_v($r, 'stCls') ?>"><?= sl_v($r, 'st') ?></span></td><td><a class="sl-ib sm" href="<?= sl_route('PackageDetail') ?>" aria-label="Open package"><i class="ic i-more" aria-hidden="true"></i></a></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
          <div class="sl-pager"><span>Showing 1–8 of 64 packages</span><div class="sl-pg"><a href="<?= sl_route('SeaPackages') ?>" aria-label="Previous"><i class="ic ic-sm i-left" aria-hidden="true"></i></a><a class="on" href="<?= sl_route('SeaPackages') ?>">1</a><a href="<?= sl_route('SeaPackages') ?>">2</a><a href="<?= sl_route('SeaPackages') ?>">3</a><a href="<?= sl_route('SeaPackages') ?>" aria-label="Next"><i class="ic ic-sm i-right" aria-hidden="true"></i></a></div></div>
        </div>
      
