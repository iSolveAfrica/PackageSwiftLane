<?php
/**
 * ShipmentDetail - generated from docs/handover/artifact-source/ShipmentDetail.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  events                       as $e
 *   value events
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><a href="<?= sl_route('AirShipments') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>Air Shipments</a>
            <div class="col g6" style="margin-top:8px"><span class="up" style="font-size:11.5px">Swift Lane tracking number</span><div class="row g12 wrap" style="align-items:center"><h1 class="mono" style="margin:0;font-size:38px;font-weight:600;letter-spacing:.04em;line-height:1.05;color:var(--ink)">SL-100482</h1><span class="sl-pill transit" style="height:30px;font-size:13.5px;padding:0 14px">In transit</span><span class="sl-badge air" style="height:30px;font-size:13.5px;padding:0 12px">Air</span></div><span class="mut" style="font-size:14px">UPS <span class="mono">1Z999AA10123456784</span> · Ama Boateng · Newark → Accra</span></div></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('ShipmentTracking') ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Full journey</a><a class="sl-btn ghost" href="<?= sl_route('Label4x6') ?>"><i class="ic ic-sm i-print" aria-hidden="true"></i>Label</a><a class="sl-btn ghost" href="<?= sl_route('ReceiptNotPaid') ?>"><i class="ic ic-sm i-receipt" aria-hidden="true"></i>Invoice</a><a class="sl-btn ghost" href="<?= sl_route('NewShipment') ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit</a><button class="sl-btn"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Update status</button></div>
        </div>

        <section class="sl-card col g16" aria-label="Progress">
          <div class="row between wrap g12"><div><div class="up">Estimated delivery</div><div class="big" style="margin-top: 4px">Nov 9, 2026</div></div><div class="col g4 tr"><span class="mut">Newark, NJ <i class="ic ic-sm i-arrow" style="vertical-align: middle" aria-hidden="true"></i> Accra, Ghana</span><span class="mono sm mut">UPS · 1Z999AA10123456784</span></div></div>
          <div class="sl-steps" aria-label="Step 3 of 5"><i class="on"></i><i class="on"></i><i class="on"></i><i></i><i></i></div>
          <div class="row between wrap g8 mut sm"><span>Registered</span><span>At US warehouse</span><span class="fw5" style="color: var(--ink)">In transit</span><span>Arrived in Accra</span><span>Delivered</span></div>
        </section>

        <div class="split">
          <div class="a col g16">
            <section class="sl-card flush">
              <div class="row between" style="padding: 16px 20px"><h2 class="sl-ct">Items</h2><span class="mut sm">2 lines · 3.2 kg</span></div>
              <div class="sl-tw"><table class="sl-table"><thead><tr><th>Qty</th><th>Description</th><th>Category</th><th class="num">Weight</th><th class="num">Value</th></tr></thead><tbody>
                <tr><td>1</td><td>Samsung Galaxy A55</td><td>Electronics</td><td class="num">0.9 kg</td><td class="num">$320.00</td></tr>
                <tr><td>2</td><td>Cotton bedsheet set</td><td>Home</td><td class="num">2.3 kg</td><td class="num">$64.00</td></tr>
              </tbody></table></div>
            </section>
            <div class="grid cols2">
              <section class="sl-card col g12"><div class="row between"><h2 class="sl-ct">Sender</h2><i class="ic i-user mut" aria-hidden="true"></i></div>
                <div class="row g12"><span class="sl-av">KM</span><div><div class="fw5">Kwame Mensah</div><div class="mut sm">kwame@example.com</div></div></div>
                <div class="sl-kv"><div><span>Address</span><span>1200 Commerce Dr, Newark, NJ</span></div><div><span>Phone</span><span>+1 201 555 0142</span></div></div></section>
              <section class="sl-card col g12"><div class="row between"><h2 class="sl-ct">Recipient</h2><i class="ic i-pin mut" aria-hidden="true"></i></div>
                <div class="row g12"><span class="sl-av">AB</span><div><div class="fw5">Ama Boateng</div><div class="mut sm">ama.boateng@example.com</div></div></div>
                <div class="sl-kv"><div><span>Address</span><span>14 Palm Close, East Legon</span></div><div><span>Phone</span><span>+233 24 000 0000</span></div></div></section>
            </div>
            <section class="sl-card col g12">
              <div class="sl-ch" style="margin-bottom: 0"><h2 class="sl-ct">Photos and documents</h2><a class="sl-btn ghost sm" href="<?= sl_route('Documents') ?>"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Upload</a></div>
              <div class="grid cols4"><div class="sl-drop" style="padding: 22px"><i class="ic i-image" aria-hidden="true"></i><span class="sm">parcel-front.jpg</span></div><div class="sl-drop" style="padding: 22px"><i class="ic i-image" aria-hidden="true"></i><span class="sm">parcel-label.jpg</span></div><div class="sl-drop" style="padding: 22px"><i class="ic i-file" aria-hidden="true"></i><span class="sm">invoice.pdf</span></div></div>
            </section>
          </div>
          <aside class="b col g16">
            <section class="sl-card col g12"><h2 class="sl-ct">Payment</h2>
              <div class="row between"><span class="big">$51.63</span><span class="sl-pill paid">Paid</span></div>
              <div class="sl-kv"><div><span>Method</span><span>Paystack</span></div><div><span>Reference</span><span class="mono sm">PSK-8841209</span></div><div><span>Paid on</span><span>Nov 4, 2026</span></div></div>
            </section>
            <section class="sl-card">
              <div class="sl-ch"><h2 class="sl-ct">Tracking</h2><a href="<?= sl_route('PublicTracking') ?>" class="sm fw5 row g6">Public page <i class="ic ic-sm i-ext" aria-hidden="true"></i></a></div>
              <div class="sl-tl">
                <?php foreach ((array) sl_get($d, 'events', []) as $__i => $e): ?><div class="<?= sl_v($e, 'cls') ?>"><span class="dot"></span><div><div class="fw5 row g8"><i class="ic ic-sm <?= sl_v($e, 'ic') ?>" aria-hidden="true"></i><?= sl_v($e, 't') ?></div><div class="mut sm"><?= sl_v($e, 's') ?></div></div></div><?php endforeach; ?>
              </div>
            </section>
            <section class="sl-card col g12"><h2 class="sl-ct">Assigned driver</h2><div class="row g12"><span class="sl-av dark">YA</span><div class="grow"><div class="fw5">Yaw Antwi</div><div class="mut sm">Van GR-2210-21</div></div><a class="sl-ib sm" aria-label="Call driver" href="<?= sl_route('ComposeMessage') ?>"><i class="ic i-phone" aria-hidden="true"></i></a></div></section>
          </aside>
        </div>
      
