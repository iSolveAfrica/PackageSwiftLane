<?php
/**
 * ShipmentDetail - from docs/handover/artifact-source/ShipmentDetail.dc.html.
 * Data: views/sl/data/shipments.php (sl_shipmentDetailData).
 */
if (!isset($d)) { $d = []; }
if (!empty($d['missing'])): ?>
        <div class="sl-head"><div><a href="<?= sl_route('AirShipments') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>Air Shipments</a><h1 class="sl-title" style="margin-top:4px">Shipment not found</h1></div></div>
        <div class="sl-card"><div class="sl-empty"><span class="sl-tile"><i class="ic i-plane" aria-hidden="true"></i></span>This shipment does not exist or is outside your scope.</div></div>
<?php return; endif; ?>
        <div class="sl-head">
          <div><a href="<?= sl_route('AirShipments') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>Air Shipments</a>
            <div class="col g6" style="margin-top:8px"><span class="up" style="font-size:13.5px">Swift Lane tracking number</span><div class="row g12 wrap" style="align-items:center"><h1 class="mono" style="margin:0;font-size:38px;font-weight:600;letter-spacing:.04em;line-height:1.05;color:var(--ink)"><?= h($d['track']) ?></h1><span class="sl-pill <?= h($d['stCls']) ?>" style="height:30px;font-size:15.5px;padding:0 14px"><?= h($d['status']) ?></span><span class="sl-badge <?= $d['mode'] === 'Air' ? 'air' : 'sea' ?>" style="height:30px;font-size:15.5px;padding:0 12px"><?= h($d['mode']) ?></span><?php if ($d['dangerous']): ?><span class="sl-pill overdue">Dangerous goods</span><?php endif; ?><?php if ($d['consol']): ?><a class="sl-badge" href="<?= sl_route('ConsolidationDetail', ['id' => $d['consol']['id']]) ?>">Consolidated · <?= h($d['consol']['code']) ?></a><?php endif; ?></div><span class="mut" style="font-size:16px"><?= h($d['carrier'] ?: 'No carrier') ?> <span class="mono"><?= h($d['carrierNo'] ?: '—') ?></span> · <?= h($d['recipient']) ?> · <?= h($d['from']) ?> → <?= h($d['to']) ?></span></div></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('ShipmentTracking', ['id' => $d['id']]) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Full journey</a><?php if ($d['canLabel']): ?><a class="sl-btn ghost" href="<?= sl_route('Label4x6', ['id' => $d['id']]) ?>" target="_blank"><i class="ic ic-sm i-print" aria-hidden="true"></i>Label</a><?php endif; ?><?php if ($d['canPrint']): ?><a class="sl-btn ghost" href="<?= sl_route('ReceiptNotPaid', ['id' => $d['id'], 'type' => 'air']) ?>" target="_blank"><i class="ic ic-sm i-receipt" aria-hidden="true"></i>Invoice</a><?php endif; ?><?php if ($d['canEdit']): ?><a class="sl-btn ghost" href="courier_edit.php?id=<?= (int) $d['id'] ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit</a><?php endif; ?><?php if ($d['canPay']): ?><a class="sl-btn" href="add_payment_gateways_courier.php?id_order=<?= (int) $d['id'] ?>"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Pay now</a><?php endif; ?><?php if ($d['canTrack']): ?><button type="button" class="sl-btn" data-sl-open="sd-status"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Update status</button><?php endif; ?></div>
        </div>

        <section class="sl-card col g16" aria-label="Progress">
          <div class="row between wrap g12"><div><div class="up">Estimated delivery</div><div class="big" style="margin-top: 4px"><?= h($d['eta']) ?></div></div><div class="col g4 tr"><span class="mut"><?= h($d['from']) ?> <i class="ic ic-sm i-arrow" style="vertical-align: middle" aria-hidden="true"></i> <?= h($d['to']) ?></span><span class="mono sm mut"><?= h($d['carrier'] ?: 'Carrier') ?> · <?= h($d['carrierNo'] ?: 'no carrier number') ?></span></div></div>
          <div class="sl-steps" aria-label="Step <?= (int) $d['step'] ?> of 5"><?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= $d['step'] ? 'on' : '' ?>"></i><?php endfor; ?></div>
          <div class="row between wrap g8 mut sm"><?php foreach ($d['steps'] as $i => $s): ?><span class="<?= $i + 1 === $d['step'] ? 'fw5' : '' ?>" <?= $i + 1 === $d['step'] ? 'style="color: var(--ink)"' : '' ?>><?= h($s) ?></span><?php endforeach; ?></div>
        </section>

        <div class="split">
          <div class="a col g16">
            <section class="sl-card flush">
              <div class="row between" style="padding: 16px 20px"><h2 class="sl-ct">Items</h2><span class="mut sm"><?= count($d['items']) ?> lines · <?= h($d['kg']) ?></span></div>
              <div class="sl-tw"><table class="sl-table"><thead><tr><th>Qty</th><th>Description</th><th class="num">L × W × H</th><th class="num">Weight</th><th class="num">Value</th></tr></thead><tbody>
                <?php foreach ($d['items'] as $it): ?><tr><td><?= h($it['qty']) ?></td><td><?= h($it['desc']) ?></td><td class="num mono"><?= h($it['dims']) ?></td><td class="num"><?= h($it['kg']) ?></td><td class="num"><?= h($it['val']) ?></td></tr><?php endforeach; ?>
                <?php if (!$d['items']): ?><tr><td colspan="5"><div class="sl-empty">No items recorded on this shipment.</div></td></tr><?php endif; ?>
              </tbody></table></div>
              <?php if ($d['notes'] !== ''): ?><div style="padding: 12px 20px; border-top: 1px solid var(--line)" class="sm"><span class="mut">Notes</span> · <?= h($d['notes']) ?></div><?php endif; ?>
            </section>
            <div class="grid cols2">
              <section class="sl-card col g12"><div class="row between"><h2 class="sl-ct">Sender</h2><i class="ic i-user mut" aria-hidden="true"></i></div>
                <div class="row g12"><span class="sl-av"><?= h($d['sender']['ini']) ?></span><div><div class="fw5"><?php if (!$d['client']): ?><a href="<?= sl_route('CustomerProfile', ['id' => $d['sender']['id']]) ?>" style="color:inherit"><?= h($d['sender']['name']) ?></a><?php else: ?><?= h($d['sender']['name']) ?><?php endif; ?></div><div class="mut sm"><?= h($d['sender']['mail']) ?></div></div></div>
                <div class="sl-kv"><div><span>Locker</span><span class="mono"><?= h($d['sender']['locker'] ?: '—') ?></span></div><div><span>Address</span><span><?= h($d['sender']['addr']) ?></span></div><div><span>Phone</span><span><?= h($d['sender']['phone']) ?></span></div></div></section>
              <section class="sl-card col g12"><div class="row between"><h2 class="sl-ct">Recipient</h2><i class="ic i-pin mut" aria-hidden="true"></i></div>
                <div class="row g12"><span class="sl-av"><?= h($d['recip']['ini']) ?></span><div><div class="fw5"><?= h($d['recip']['name']) ?></div><div class="mut sm"><?= h($d['recip']['mail']) ?></div></div></div>
                <div class="sl-kv"><div><span>Address</span><span><?= h($d['recip']['addr']) ?></span></div><div><span>Phone</span><span><?= h($d['recip']['phone']) ?></span></div></div></section>
            </div>
            <section class="sl-card col g12">
              <div class="sl-ch" style="margin-bottom: 0"><h2 class="sl-ct">Photos and documents</h2><?php if ($d['canEdit']): ?><a class="sl-btn ghost sm" href="courier_edit.php?id=<?= (int) $d['id'] ?>#files"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Upload</a><?php endif; ?></div>
              <div class="grid cols4"><?php foreach ($d['files'] as $f): ?><a class="sl-drop" style="padding: 22px" href="<?= h($f['url']) ?>" target="_blank"><i class="ic <?= $f['img'] ? 'i-image' : 'i-file' ?>" aria-hidden="true"></i><span class="sm" style="word-break:break-all"><?= h($f['name']) ?></span></a><?php endforeach; ?><?php if (!$d['files']): ?><div class="mut sm" style="grid-column:1/-1">No photos or documents attached.</div><?php endif; ?></div>
            </section>
          </div>
          <aside class="b col g16">
            <section class="sl-card col g12" id="payment"><h2 class="sl-ct">Payment</h2>
              <div class="row between"><span class="big"><?= h($d['total']) ?></span><span class="sl-pill <?= h($d['payCls']) ?>"><?= h($d['pay']) ?></span></div>
              <div class="sl-kv"><div><span>Method</span><span><?= h($d['payMethod']) ?></span></div><div><span>Terms</span><span><?= h($d['terms'] ?: '—') ?></span></div><div><span>Reference</span><span class="mono sm"><?= h($d['payRef']) ?></span></div><div><span><?= $d['pay'] === 'Paid' ? 'Paid on' : 'Due' ?></span><span><?= h($d['pay'] === 'Paid' ? $d['paidOn'] : $d['due']) ?></span></div></div>
              <?php if ($d['canVerify']): ?><a class="sl-btn ghost sm" href="courier_list.php?verify=<?= (int) $d['id'] ?>"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Verify payment</a><?php endif; ?>
              <?php if ($d['canPay']): ?><a class="sl-btn block" href="add_payment_gateways_courier.php?id_order=<?= (int) $d['id'] ?>">Pay now</a><?php endif; ?>
            </section>
            <section class="sl-card">
              <div class="sl-ch"><h2 class="sl-ct">Tracking</h2><a href="<?= h($d['publicUrl']) ?>" target="_blank" class="sm fw5 row g6">Public page <i class="ic ic-sm i-ext" aria-hidden="true"></i></a></div>
              <div class="sl-tl">
                <?php foreach ($d['events'] as $e): ?><div class="<?= h($e['cls']) ?>"><span class="dot"></span><div><div class="fw5 row g8"><i class="ic ic-sm <?= h($e['ic']) ?>" aria-hidden="true"></i><?= h($e['t']) ?></div><div class="mut sm"><?= h($e['s']) ?></div></div></div><?php endforeach; ?>
                <?php if (!$d['events']): ?><div class="mut sm">No tracking events yet.</div><?php endif; ?>
              </div>
              <?php if (!empty($d['pickupCode']['live']) || !empty($d['pickupCode']['verified'])): ?><div class="row between sm" style="margin-top:10px;padding-top:10px;border-top:1px solid var(--line)"><span class="mut">Pickup code</span><b><?= !empty($d['pickupCode']['verified']) ? 'Verified at hand-over' : 'Sent to the customer' ?></b></div><?php endif; ?>
            </section>
            <section class="sl-card col g12"><div class="row between"><h2 class="sl-ct">Assigned driver</h2><?php if ($d['canAssign']): ?><button type="button" class="sl-btn ghost sm" data-sl-open="sd-driver"><?= $d['driver'] ? 'Change' : 'Assign' ?></button><?php endif; ?></div>
              <?php if ($d['driver']): ?><div class="row g12"><span class="sl-av dark"><?= h($d['driver']['ini']) ?></span><div class="grow"><div class="fw5"><?= h($d['driver']['name']) ?></div><div class="mut sm"><?= h($d['driver']['veh']) ?></div></div><?php if ($d['driver']['phone']): ?><a class="sl-ib sm" aria-label="Call driver" href="tel:<?= h($d['driver']['phone']) ?>"><i class="ic i-phone" aria-hidden="true"></i></a><?php endif; ?></div><?php else: ?><div class="mut sm">No rider assigned yet.</div><?php endif; ?>
            </section>
          </aside>
        </div>
<?php if ($d['canTrack']): ?>
<div class="sl-dialog" id="sd-status" hidden><form class="sl-modal" data-sl-form action="ajax/courier/add_courier_tracking.php" method="post" role="dialog" aria-label="Update status">
  <input type="hidden" name="shipment_id" value="<?= (int) $d['id'] ?>">
  <div class="row g10"><span class="sl-tile"><i class="ic i-refresh" aria-hidden="true"></i></span><div class="col grow"><b class="fw5">Update status of <?= h($d['track']) ?></b><span class="mut sm">A tracking event is added and the customer is notified by the channels enabled in Settings.</span></div></div>
  <div class="grid cols2" style="gap:10px">
    <label class="sl-field"><span class="sl-lab">New status</span><span class="sl-sel native"><select name="status_courier" required><?php foreach ($d['statuses'] as $s): ?><option value="<?= (int) $s->id ?>" <?= (int) $s->id === $d['statusId'] ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $s->mod_style)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
    <label class="sl-field"><span class="sl-lab">Date</span><input class="sl-in" type="date" name="t_date" value="<?= date('Y-m-d') ?>" required></label>
    <label class="sl-field"><span class="sl-lab">Office</span><span class="sl-sel native"><select name="office" required><?php foreach ($d['offices'] as $o): ?><option value="<?= (int) $o->id ?>" <?= (int) $o->id === $d['office'] ? 'selected' : '' ?>><?= h($o->name_off) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
    <label class="sl-field"><span class="sl-lab">Country</span><span class="sl-sel native"><select name="country" required><?php foreach ($d['countries'] as $c): ?><option value="<?= h($c->name) ?>"><?= h($c->name) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
    <label class="sl-field" style="grid-column:1/-1"><span class="sl-lab">Location / address</span><input class="sl-in" name="address" placeholder="Where the parcel is now" required></label>
    <label class="sl-field"><span class="sl-lab">Flight number</span><input class="sl-in mono" name="flight_no" placeholder="KQ 101"></label>
    <label class="sl-field"><span class="sl-lab">AWB number</span><input class="sl-in mono" name="awb_no" placeholder="176-4421 8890"></label>
    <label class="sl-field" style="grid-column:1/-1"><span class="sl-lab">Comment</span><input class="sl-in" name="comments" maxlength="250" placeholder="Shown to the customer"></label>
  </div>
  <div class="row g12 wrap sm"><label class="row g6"><input type="checkbox" name="notify_whatsapp_sender" value="1" checked>WhatsApp sender</label><label class="row g6"><input type="checkbox" name="notify_sms_sender" value="1">SMS sender</label><label class="row g6"><input type="checkbox" name="notify_sms_receiver" value="1">SMS recipient</label></div>
  <div class="row g8"><button type="submit" class="sl-btn">Save status</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
<?php if ($d['canAssign']): ?>
<div class="sl-dialog" id="sd-driver" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/shipment_action_ajax.php" method="post" role="dialog" aria-label="Assign rider">
  <input type="hidden" name="op" value="driver"><input type="hidden" name="ids" value="<?= (int) $d['id'] ?>">
  <div class="row g10"><span class="sl-tile"><i class="ic i-wheel" aria-hidden="true"></i></span><div class="col grow"><b class="fw5">Assign a rider</b><span class="mut sm">The driver is notified and the shipment appears on their route.</span></div></div>
  <label class="sl-field"><span class="sl-lab">Rider</span><span class="sl-sel native"><select name="driver_id" required><option value="">Choose a rider</option><?php foreach ($d['drivers'] as $dr): ?><option value="<?= (int) $dr->id ?>" <?= $d['driver'] && (int) $dr->id === $d['driver']['id'] ? 'selected' : '' ?>><?= h(trim($dr->fname . ' ' . $dr->lname)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  <div class="row g8"><button type="submit" class="sl-btn">Assign</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
<script>
// The legacy tracking endpoint answers {success, messages[] | errors{}}; reload on success.
document.addEventListener('sl:done', function (e) {
  var f = e.target; if (!f.matches || !f.matches('form[action$="add_courier_tracking.php"]')) return;
  var j = e.detail || {};
  if (j.success) { SL.toast((j.messages || ['Status updated']).join(' ')); setTimeout(function () { location.reload(); }, 400); }
  else if (j.errors) { var m = Object.keys(j.errors).map(function (k) { return j.errors[k]; }).join(' '); if (m) SL.toast(m, true); }
}, true); // sl:done does not bubble: listen in the capture phase
</script>

<?php if (!empty($d['id'])) { require_once CDP_ROOT . "/helpers/delivery_service.php"; $deliveryLink=dl_parcelLink("air", (int)$d['id']); if ($deliveryLink): ?>
<a class="sl-btn ghost" href="<?= h($deliveryLink['href']) ?>">Delivery history and proof · <?= h($deliveryLink['state']) ?></a>
<?php endif; } ?>
