<?php
/**
 * CustomerProfile - generated from docs/handover/artifact-source/CustomerProfile.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  reasons                      as $r
 *   if    asking
 *   if    blocked
 *   value asking, bIcon, bLabel, bStyle, blocked, cancel, confirm, false, reason, reasons, st, stCls, toggleBlk
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <a href="<?= sl_route('Customers') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>Customers</a>
        <section class="sl-card row g16 wrap">
          <span class="sl-av lg dark">EO</span>
          <div class="grow"><div class="row g8 wrap"><h1 class="sl-title" style="font-size: 20px">Efua Owusu</h1><span class="sl-pill <?= sl_v($d, 'stCls') ?>"><?= sl_v($d, 'st') ?></span><span class="sl-badge ok">Phone verified</span></div><div class="mut">SL-3318 · Customer since Mar 2024 · Takoradi</div></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('ComposeMessage') ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Message</a><a class="sl-btn ghost" href="<?= sl_route('AddCustomer') ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit</a><button class="sl-btn ghost" style="<?= sl_v($d, 'bStyle') ?>" data-sl-on="click:toggleBlk"><i class="ic ic-sm <?= sl_v($d, 'bIcon') ?>" aria-hidden="true"></i><?= sl_v($d, 'bLabel') ?></button><a class="sl-btn" href="<?= sl_route('NewShipment') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Shipment</a></div>
        </section>
        <?php if (sl_get($d, 'asking')): ?><section class="sl-card col g12"><div class="row g10"><span class="sl-tile" style="background:var(--red-bg);color:var(--red-ink)"><i class="ic i-ban" aria-hidden="true"></i></span><div class="col"><b class="fw5">Block Efua Owusu?</b><span class="mut sm">She can't log in, book pickups or get offers. Her 3 parcels stay on hold at the warehouse.</span></div></div><div class="row wrap g6"><?php foreach ((array) sl_get($d, 'reasons', []) as $__i => $r): ?><button class="sl-btn ghost sm" style="<?= sl_v($r, 'st') ?>" data-sl-on="click:pick"><?= sl_v($r, 'label') ?></button><?php endforeach; ?></div><label class="sl-field"><span class="sl-lab">Note for staff</span><input class="sl-in" value="Sent an edited MoMo receipt on Nov 4" aria-label="Note"></label><div class="row g8"><button class="sl-btn" style="background:var(--red);border-color:var(--red);color:#fff" data-sl-on="click:confirm"><i class="ic ic-sm i-ban" aria-hidden="true"></i>Block customer</button><button class="sl-btn ghost" data-sl-on="click:cancel">Cancel</button></div></section><?php endif; ?>
        <?php if (sl_get($d, 'blocked')): ?><div class="sl-banner" style="background:var(--red-bg);color:var(--red-ink);border-color:transparent"><i class="ic i-ban" aria-hidden="true"></i><span class="grow"><b class="fw5">Blocked</b> · <?= sl_v($d, 'reason') ?> · by Henry Cobblah, today. Log-in, pickups and offers are off; parcels are on hold.</span><button class="sl-btn ghost sm" data-sl-on="click:toggleBlk">Unblock</button></div><?php endif; ?>
        <div class="grid cols4">
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Lifetime parcels</span></div><div class="sl-mv">42</div><div class="sl-ticks"><i style="width: 70%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Total spend</span></div><div class="sl-mv">$3,486 <i>/ $5,000 plan</i></div><div class="sl-ticks y"><i style="width: 70%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Balance due</span></div><div class="sl-mv">$0.00</div><div class="sl-ticks"><i style="width: 0%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Locker bays</span></div><div class="sl-mv">2 / 5</div><div class="sl-ticks v"><i style="width: 40%"></i></div></div>
        </div>
        <div class="split">
          <div class="a col g16">
            <section class="sl-card flush">
              <div class="row between wrap g12" style="padding: 14px 16px"><div class="sl-seg boxed"><button class="on">Shipments</button><button>Packages</button><button>Payments</button></div><a href="<?= sl_route('AirShipments') ?>" class="sm fw5 row g6">View all <i class="ic ic-sm i-right" aria-hidden="true"></i></a></div>
              <div class="sl-tw"><table class="sl-table"><thead><tr><th>Tracking</th><th>Date</th><th>Destination</th><th class="num">Weight</th><th>Status</th></tr></thead><tbody>
                <tr><td><a class="id" href="<?= sl_route('ShipmentDetail') ?>">SL-100480</a></td><td>Nov 3</td><td>Takoradi</td><td class="num">5.6 kg</td><td><span class="sl-pill sorting">At warehouse</span></td></tr>
                <tr><td><a class="id" href="<?= sl_route('ShipmentDetail') ?>">SL-100455</a></td><td>Oct 28</td><td>Takoradi</td><td class="num">1.2 kg</td><td><span class="sl-pill sorting">At warehouse</span></td></tr>
                <tr><td><a class="id" href="<?= sl_route('ShipmentDetail') ?>">SL-100391</a></td><td>Oct 12</td><td>Takoradi</td><td class="num">8.4 kg</td><td><span class="sl-pill delivered">Delivered</span></td></tr>
                <tr><td><a class="id" href="<?= sl_route('ShipmentDetail') ?>">SL-100302</a></td><td>Sep 21</td><td>Accra</td><td class="num">3.0 kg</td><td><span class="sl-pill delivered">Delivered</span></td></tr>
              </tbody></table></div>
            </section>
            <section class="sl-card"><div class="sl-ch"><h2 class="sl-ct">Shipping activity</h2><span class="mut sm">Last 6 months</span></div>
              <div class="sl-bars" style="height: 120px"><div class="sl-bar"><span style="height: 40%"></span></div><div class="sl-bar"><span style="height: 65%"></span></div><div class="sl-bar"><span style="height: 30%"></span></div><div class="sl-bar"><span style="height: 80%"></span></div><div class="sl-bar hi"><span style="height: 100%"></span></div><div class="sl-bar"><span style="height: 55%"></span></div></div>
              <div class="row between mut sm" style="margin-top: 8px"><span>Jun</span><span>Jul</span><span>Aug</span><span>Sep</span><span>Oct</span><span>Nov</span></div>
            </section>
          </div>
          <aside class="b col g16">
            <section class="sl-card col g12"><h2 class="sl-ct">Contact</h2>
              <div class="sl-kv"><div><span>Email</span><span>efua@example.com</span></div><div><span>Phone</span><span>+233 20 111 2233</span></div><div><span>WhatsApp alerts</span><span class="row g8 end"><span class="sl-toggle on"></span></span></div><div><span>SMS alerts</span><span class="row g8 end"><span class="sl-toggle"></span></span></div></div>
            </section>
            <section class="sl-card col g12"><div class="row between"><h2 class="sl-ct">Addresses</h2><a class="sl-ib sm" aria-label="Add address" href="<?= sl_route('AddCustomer') ?>"><i class="ic i-plus" aria-hidden="true"></i></a></div>
              <div class="row g12 top"><span class="sl-tile"><i class="ic i-pin" aria-hidden="true"></i></span><div><div class="fw5">Home <span class="sl-badge" style="margin-left: 6px">Default</span></div><div class="mut sm">6 Harbour Rd, Takoradi</div></div></div>
              <div class="row g12 top"><span class="sl-tile"><i class="ic i-pin" aria-hidden="true"></i></span><div><div class="fw5">Shop</div><div class="mut sm">Market Circle, Takoradi</div></div></div>
            </section>
            <section class="sl-card col g12"><h2 class="sl-ct">Documents</h2><div class="row g12"><span class="sl-tile"><i class="ic i-file" aria-hidden="true"></i></span><div class="grow"><div class="fw5">Ghana Card</div><div class="mut sm">Verified Mar 12, 2024</div></div><span class="sl-badge ok">OK</span></div></section>
          </aside>
        </div>
      
