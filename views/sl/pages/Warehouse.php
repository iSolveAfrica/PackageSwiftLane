<?php
/**
 * Warehouse - generated from docs/handover/artifact-source/Warehouse.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  bays                         as $b
 *   loop  feed                         as $f
 *   value bays, feed
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Warehouse</h1><p class="sl-lede">Accra hub · receive, shelve and hand over parcels</p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('LabelStudio') ?>"><i class="ic ic-sm i-print" aria-hidden="true"></i>Print bay labels</a><a class="sl-btn" href="<?= sl_route('LockerSearch') ?>"><i class="ic ic-sm i-search" aria-hidden="true"></i>Locker search</a></div>
        </div>
        <section class="sl-card col g12" aria-label="Scan">
          <div class="sl-seg boxed" style="align-self: flex-start"><a class="on" href="<?= sl_route('PackageIntake') ?>"><i class="ic ic-sm i-scan" aria-hidden="true"></i>Receive</a><a href="<?= sl_route('WarehouseDelivery') ?>"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Hand over</a><button>Move bay</button></div>
          <div class="row wrap g8"><div class="sl-search" style="max-width: none; height: 52px; flex: 1 1 320px"><i class="ic i-scan" aria-hidden="true"></i><input class="mono" aria-label="Scan or type a tracking number" value="1Z999AA10123456784"></div><a class="sl-btn" style="height: 52px; padding: 0 28px" href="<?= sl_route('PackageIntake') ?>">Receive parcel</a></div>
          <div class="sl-banner ok"><i class="ic i-check" aria-hidden="true"></i><div><strong>SL-100482 received</strong> · Ama Boateng · 3.2 kg · assigned to bay <strong>B-17</strong>. WhatsApp pickup alert sent.</div></div>
        </section>
        <div class="split">
          <section class="a sl-card">
            <div class="sl-ch"><h2 class="sl-ct">Locker bays</h2><div class="sl-mv" style="margin: 0; font-size: 14px">46 / 50 <i>full (92%)</i></div></div>
            <div class="sl-ticks r" style="margin-bottom: 18px"><i style="width: 92%"></i></div>
            <div class="sl-heat" style="grid-template-rows: repeat(5, 40px); grid-template-columns: repeat(10, minmax(0, 1fr))" role="img" aria-label="Locker bay map, 46 of 50 bays occupied">
              <?php foreach ((array) sl_get($d, 'bays', []) as $__i => $b): ?><b class="<?= sl_v($b, 'c') ?>" style="width: 30px; height: 30px; border-radius: 8px; opacity: 1"></b><?php endforeach; ?>
            </div>
            <div class="row g16 wrap mut sm" style="margin-top: 14px"><span class="row g6"><b style="width: 12px; height: 12px; border-radius: 3px; background: var(--heat-0)"></b>Free (4)</span><span class="row g6"><b style="width: 12px; height: 12px; border-radius: 3px; background: var(--heat-3)"></b>Occupied (36)</span><span class="row g6"><b style="width: 12px; height: 12px; border-radius: 3px; background: var(--heat-1)"></b>Awaiting pickup (10)</span></div>
          </section>
          <aside class="b sl-card">
            <div class="sl-ch"><h2 class="sl-ct">Recent scans</h2></div>
            <div class="sl-feed">
              <?php foreach ((array) sl_get($d, 'feed', []) as $__i => $f): ?><div><span class="sl-tile"><i class="ic <?= sl_v($f, 'icon') ?>" aria-hidden="true"></i></span><div class="grow"><div class="fw5"><?= sl_v($f, 't') ?></div><div class="mut sm"><?= sl_v($f, 's') ?></div></div><span class="mut sm nw"><?= sl_v($f, 'when') ?></span></div><?php endforeach; ?>
            </div>
          </aside>
        </div>
        <section class="sl-card flush">
          <div class="row between wrap g12" style="padding: 16px 20px"><h2 class="sl-ct">Ready for hand over</h2><div class="sl-search" style="min-width: 200px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Filter" placeholder="Name, bay or pickup code"></div></div>
          <div class="sl-tw"><table class="sl-table"><thead><tr><th>Parcel</th><th>Customer</th><th>Bay</th><th>Waiting</th><th>Pickup code</th><th></th></tr></thead><tbody>
            <tr><td><a class="id" href="<?= sl_route('ShipmentDetail') ?>">SL-100479</a></td><td><div class="sl-cell"><span class="sl-av sm">YD</span>Yaw Darko</div></td><td class="mono sm">B-04</td><td>2 days</td><td class="mono fw5">482 913</td><td><a class="sl-btn sm" href="<?= sl_route('WarehouseDelivery') ?>">Hand over</a></td></tr>
            <tr><td><a class="id" href="<?= sl_route('ShipmentDetail') ?>">SL-100471</a></td><td><div class="sl-cell"><span class="sl-av sm">EQ</span>Esi Quaye</div></td><td class="mono sm">B-11</td><td>4 days</td><td class="mono fw5">117 640</td><td><a class="sl-btn sm" href="<?= sl_route('WarehouseDelivery') ?>">Hand over</a></td></tr>
            <tr><td><a class="id" href="<?= sl_route('ShipmentDetail') ?>">SL-100466</a></td><td><div class="sl-cell"><span class="sl-av sm">KA</span>Kofi Asante</div></td><td class="mono sm">B-23</td><td><span class="sl-pill pending">9 days</span></td><td class="mono fw5">905 372</td><td><a class="sl-btn ghost sm" href="<?= sl_route('ComposeMessage') ?>">Send reminder</a></td></tr>
          </tbody></table></div>
        </section>
      
