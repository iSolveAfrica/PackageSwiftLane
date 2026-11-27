<?php
/** M-ShipDetail (staff): one air shipment on a phone. $d = views/sl/data/shipments.php sl_shipmentDetailData(). */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'none';
if (!empty($d['missing'])):
?>
<?= sl_phoneTop('Shipment', '', ['back' => sl_route('AirShipments')]) ?>
<main class="mx-body"><?= sl_mxEmpty('plane', 'Shipment not found', 'It was removed, or it is outside the branches you can see.', 'All shipments', sl_route('AirShipments')) ?></main>
<?php return; endif;
$pk = $d['pickup'] ?? ['assigned' => false];
$pkState = empty($pk['assigned']) ? ['Not assigned', 'plain'] : (!empty($pk['verified']) ? ['Verified', 'delivered'] : (!empty($pk['sent']) ? ['Sent', 'paid'] : ['Assigned', 'pending']));
$pct = (int) round(($d['step'] - 1) / 4 * 100);
$items = implode(', ', array_map(fn($i) => ($i['qty'] > 1 ? $i['qty'] . '× ' : '') . $i['desc'], $d['items']));
$declared = array_sum(array_map(fn($i) => (float) preg_replace('/[^\d.]/', '', (string) $i['val']), $d['items']));
$canHand = $user->cdp_hasPermission(['view_warehouse_delivery', 'warehouse_deliver_package']);
require_once CDP_ROOT . '/helpers/delivery_service.php';
$deliveryLink = function_exists('dl_parcelLink') ? dl_parcelLink('air', (int) $d['id']) : null;
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<?= sl_phoneTop($d['track'], $d['carrierNo'] !== '' ? $d['carrier'] . ' ' . $d['carrierNo'] : '', ['back' => sl_route('AirShipments')], '<a class="mx-ib" href="#more" aria-label="More actions"><i class="ic i-more" aria-hidden="true"></i></a>') ?>
<main class="mx-body">
<section class="mx-hero"><div class="mx-hk"><?= h($d['mode'] . ' · ' . $d['from'] . ' → ' . $d['to']) ?><span class="mx-tag"><?= h($d['pay']) ?></span></div><b class="mx-hv"><?= h(ucfirst(strtolower($d['status']))) ?></b><small><?= h($d['steps'][$d['step'] - 1] . ' · ETA ' . $d['eta'] . ($d['consol'] ? ' · in ' . $d['consol']['code'] : '')) ?></small><div class="mx-prog" role="img" aria-label="Step <?= (int) $d['step'] ?> of 5"><i style="width:<?= max(4, $pct) ?>%"></i></div></section>
<?php if ($d['dangerous']): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Dangerous goods: handle and declare accordingly.</span></div><?php endif; ?>
<section class="mx-act c4">
<a href="#pickup-code"><span><i class="ic i-key" aria-hidden="true"></i></span><small>Pickup code</small></a>
<?php if ($d['canLabel']): ?><a href="<?= h(sl_route('Label4x6', ['id' => $d['id']])) ?>" target="_blank" rel="noopener"><span><i class="ic i-print" aria-hidden="true"></i></span><small>Label</small></a><?php endif; ?>
<?php if ($d['canAssign']): ?><a href="#assign"><span><i class="ic i-wheel" aria-hidden="true"></i></span><small><?= $d['driver'] ? 'Change rider' : 'Assign rider' ?></small></a><?php endif; ?>
<?php if ($d['canTrack']): ?><a href="#status"><span><i class="ic i-refresh" aria-hidden="true"></i></span><small>Update status</small></a><?php else: ?><a href="<?= h(sl_route('ShipmentTracking', ['id' => $d['id']])) ?>"><span><i class="ic i-route" aria-hidden="true"></i></span><small>Journey</small></a><?php endif; ?>
</section>
<div class="mx-sec"><h2>Details</h2></div>
<section class="mx-card mx-kv">
<div><span>Customer</span><b><a href="<?= h(sl_route('CustomerProfile', ['id' => $d['sender']['id']])) ?>"><?= h($d['sender']['name'] . ($d['sender']['locker'] !== '' ? ' · ' . $d['sender']['locker'] : '')) ?></a></b></div>
<div><span>Recipient</span><b><?= h($d['recip']['name'] . ($d['recip']['addr'] !== '—' && $d['recip']['addr'] !== '' ? ', ' . $d['recip']['addr'] : '')) ?></b></div>
<?php if ($d['recip']['phone'] !== '—' && $d['recip']['phone'] !== ''): ?><div><span>Phone</span><b><a href="tel:<?= h($d['recip']['phone']) ?>"><?= h($d['recip']['phone']) ?></a></b></div><?php endif; ?>
<div><span>Weight</span><b><?= h($d['kg']) ?></b></div>
<?php if ($items !== ''): ?><div><span>Contents</span><b><?= h($items) ?></b></div><?php endif; ?>
<?php if ($declared > 0): ?><div><span>Declared value</span><b><?= h(sl_money($declared)) ?></b></div><?php endif; ?>
<div><span>Charges</span><b><?= h($d['total'] . ' · ' . strtolower($d['pay'])) ?></b></div>
<?php if ($d['payMethod'] !== '—'): ?><div><span>Payment</span><b><?= h($d['payMethod'] . ($d['paidOn'] !== '—' ? ' · ' . $d['paidOn'] : '')) ?></b></div><?php endif; ?>
<?php if ($d['notes'] !== ''): ?><div><span>Notes</span><b><?= h($d['notes']) ?></b></div><?php endif; ?>
</section>
<div class="mx-sec"><h2>Tracking</h2><a href="<?= h(sl_route('ShipmentTracking', ['id' => $d['id']])) ?>">Full journey</a></div>
<section class="mx-card">
<?php if ($d['events']): ?>
<ol class="mx-tl">
<?php if ($d['step'] < 5): ?><li class="todo"><span class="mx-td"><i class="ic ic-sm i-<?= $d['step'] < 4 ? 'warehouse' : 'check' ?>" aria-hidden="true"></i></span><div><b><?= h($d['steps'][$d['step']]) ?></b><small>Next · ETA <?= h($d['eta']) ?></small></div></li><?php endif; ?>
<?php foreach (array_slice($d['events'], 0, 8) as $i => $e): ?>
<li class="<?= $i === 0 ? 'cur' : 'done' ?>"><span class="mx-td"><i class="ic ic-sm <?= $i === 0 ? 'i-pin' : 'i-check' ?>" aria-hidden="true"></i></span><div><b><?= h(ucfirst(strtolower($e['t']))) ?></b><small><?= h($e['s']) ?></small></div></li>
<?php endforeach; ?>
</ol>
<?php else: ?><?= sl_mxEmpty('route', 'No tracking events yet') ?><?php endif; ?>
</section>
<div class="mx-sec" id="pickup-code"><h2>Pickup code</h2><span class="sl-pill <?= h($pkState[1]) ?>"><?= h($pkState[0]) ?></span></div>
<section class="mx-card mx-kv">
<?php if (!empty($pk['assigned'])): ?>
<div><span>Code</span><b class="mono"><?= h($pk['code']) ?></b></div>
<div><span>Sent</span><b><?= $pk['sent'] !== '' ? h($pk['sent'] . ($pk['channels'] !== '' ? ' · ' . $pk['channels'] : '')) : 'Not yet: sent when ready for pickup' ?></b></div>
<?php if (!empty($pk['verified'])): ?><div><span>Verified</span><b><?= h($pk['verified']) ?></b></div><?php endif; ?>
<?php else: ?><div><span>Code</span><b>Assigned when the package is created</b></div><?php endif; ?>
</section>
<div class="mx-sec"><h2>Rider</h2></div>
<section class="mx-card mx-list">
<?php if ($d['driver']): ?><div class="mx-row"><span class="mx-ava"><?= h($d['driver']['ini']) ?></span><span class="mx-rm"><b><?= h($d['driver']['name']) ?></b><small><?= h($d['driver']['veh']) ?></small></span><?php if ($d['driver']['phone']): ?><span class="mx-rr"><a class="mx-ib" href="tel:<?= h($d['driver']['phone']) ?>" aria-label="Call rider"><i class="ic i-phone" aria-hidden="true"></i></a></span><?php endif; ?></div>
<?php else: ?><div class="mx-row"><span class="mx-li"><i class="ic i-wheel" aria-hidden="true"></i></span><span class="mx-rm"><b>No rider assigned yet</b></span></div><?php endif; ?>
<?php if ($deliveryLink): ?><a class="mx-row" href="<?= h($deliveryLink['href']) ?>"><span class="mx-li"><i class="ic i-truck" aria-hidden="true"></i></span><span class="mx-rm"><b>Delivery history and proof</b><small><?= h($deliveryLink['state']) ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a><?php endif; ?>
</section>
<?php if ($d['canAssign']): ?>
<details class="mx-grp" id="assign"><summary><span><?= $d['driver'] ? 'Change rider' : 'Assign a rider' ?></span><i class="ic ic-sm i-down" aria-hidden="true"></i></summary>
<form class="mx-card mx-form" data-sl-form data-sl-reload action="ajax/sl/shipment_action_ajax.php" method="post">
<input type="hidden" name="op" value="driver"><input type="hidden" name="ids" value="<?= (int) $d['id'] ?>">
<label class="mx-fld"><span>Rider</span><select name="driver_id" required><option value="">Choose a rider</option><?php foreach ($d['drivers'] as $dr): ?><option value="<?= (int) $dr->id ?>"<?= $d['driver'] && (int) $dr->id === $d['driver']['id'] ? ' selected' : '' ?>><?= h(trim($dr->fname . ' ' . $dr->lname)) ?></option><?php endforeach; ?></select></label>
<button type="submit" class="mx-btn or">Assign</button>
</form></details>
<?php endif; ?>
<?php if ($d['canTrack']): ?>
<details class="mx-grp" id="status"><summary><span>Update status</span><i class="ic ic-sm i-down" aria-hidden="true"></i></summary>
<form class="mx-card mx-form" data-sl-form action="ajax/courier/add_courier_tracking.php" method="post" id="sd-status-form">
<input type="hidden" name="shipment_id" value="<?= (int) $d['id'] ?>">
<label class="mx-fld"><span>New status</span><select name="status_courier" required><?php foreach ($d['statuses'] as $s): ?><option value="<?= (int) $s->id ?>"<?= (int) $s->id === $d['statusId'] ? ' selected' : '' ?>><?= h(str_replace('_', ' ', $s->mod_style)) ?></option><?php endforeach; ?></select></label>
<label class="mx-fld"><span>Date</span><input type="date" name="t_date" value="<?= h(date('Y-m-d')) ?>" required></label>
<label class="mx-fld"><span>Office</span><select name="office" required><?php foreach ($d['offices'] as $o): ?><option value="<?= (int) $o->id ?>"<?= (int) $o->id === $d['office'] ? ' selected' : '' ?>><?= h($o->name_off) ?></option><?php endforeach; ?></select></label>
<label class="mx-fld"><span>Country</span><select name="country" required><?php foreach ($d['countries'] as $c): ?><option value="<?= h($c->name) ?>"><?= h($c->name) ?></option><?php endforeach; ?></select></label>
<label class="mx-fld"><span>Location / address</span><input name="address" placeholder="Where the parcel is now" required autocomplete="off"></label>
<label class="mx-fld"><span>Flight number</span><input class="mono" name="flight_no" placeholder="Flight" autocomplete="off" autocapitalize="characters"></label>
<label class="mx-fld"><span>AWB number</span><input class="mono" name="awb_no" placeholder="AWB" autocomplete="off" inputmode="numeric"></label>
<label class="mx-fld"><span>Comment</span><input name="comments" maxlength="250" placeholder="Shown to the customer"></label>
<label class="mx-chk"><input type="checkbox" name="notify_whatsapp_sender" value="1" checked> WhatsApp sender</label>
<label class="mx-chk"><input type="checkbox" name="notify_sms_sender" value="1"> SMS sender</label>
<label class="mx-chk"><input type="checkbox" name="notify_sms_receiver" value="1"> SMS recipient</label>
<button type="submit" class="mx-btn or">Save status</button>
</form></details>
<?php endif; ?>
<?php if ($d['files']): ?>
<div class="mx-sec"><h2>Files</h2></div>
<section class="mx-card mx-list"><?php foreach ($d['files'] as $f): ?><a class="mx-row" href="<?= h($f['url']) ?>" target="_blank" rel="noopener"><span class="mx-li"><i class="ic i-<?= $f['img'] ? 'image' : 'file' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($f['name']) ?></b></span></a><?php endforeach; ?></section>
<?php endif; ?>
<div class="mx-sec" id="more"><h2>More actions</h2></div>
<section class="mx-card mx-list">
<?php if ($canHand): ?><a class="mx-row" href="<?= h(sl_route('HandOut', ['customer' => $d['sender']['id']])) ?>"><span class="mx-li"><i class="ic i-check" aria-hidden="true"></i></span><span class="mx-rm"><b>Hand out / deliver</b></span></a><?php endif; ?>
<?php if ($d['canEdit']): ?><a class="mx-row" href="courier_edit.php?id=<?= (int) $d['id'] ?>"><span class="mx-li"><i class="ic i-edit" aria-hidden="true"></i></span><span class="mx-rm"><b>Edit shipment</b></span></a><?php endif; ?>
<a class="mx-row" href="<?= h(sl_route('ShipmentTracking', ['id' => $d['id']])) ?>"><span class="mx-li"><i class="ic i-route" aria-hidden="true"></i></span><span class="mx-rm"><b>Shipment tracking</b></span></a>
<?php if ($d['canPrint']): ?><a class="mx-row" href="<?= h(sl_route('ReceiptNotPaid', ['id' => $d['id'], 'type' => 'air'])) ?>" target="_blank" rel="noopener"><span class="mx-li"><i class="ic i-receipt" aria-hidden="true"></i></span><span class="mx-rm"><b>Print invoice</b></span></a><?php endif; ?>
<?php if ($d['canVerify']): ?><a class="mx-row" href="courier_list.php?verify=<?= (int) $d['id'] ?>&amp;view=web"><span class="mx-li"><i class="ic i-shield" aria-hidden="true"></i></span><span class="mx-rm"><b>Verify payment</b></span></a><?php endif; ?>
<?php if ($d['consol']): ?><a class="mx-row" href="<?= h(sl_route('ConsolidationDetail', ['id' => $d['consol']['id']])) ?>"><span class="mx-li"><i class="ic i-layers" aria-hidden="true"></i></span><span class="mx-rm"><b>Consolidation <?= h($d['consol']['code']) ?></b></span></a><?php endif; ?>
<a class="mx-row" href="<?= h($d['publicUrl']) ?>" target="_blank" rel="noopener"><span class="mx-li"><i class="ic i-eye" aria-hidden="true"></i></span><span class="mx-rm"><b>Public tracking page</b></span></a>
</section>
</main>
<script>
// The legacy tracking endpoint answers {success, messages[] | errors{}}; reload on success (same as the web page).
document.addEventListener('sl:done', function (e) {
  var f = e.target; if (!f.matches || !f.matches('#sd-status-form')) return;
  var j = e.detail || {};
  if (j.success) { SL.toast((j.messages || ['Status updated']).join(' ')); setTimeout(function () { location.reload(); }, 400); }
  else if (j.errors) { var m = Object.keys(j.errors).map(function (k) { return j.errors[k]; }).join(' '); if (m) SL.toast(m, true); }
}, true);
// Quick actions that point at a folded section open it.
document.addEventListener('click', function (e) {
  var a = e.target.closest('a[href^="#"]'); if (!a) return;
  var t = document.getElementById(a.getAttribute('href').slice(1));
  if (t && t.tagName === 'DETAILS') { t.open = true; }
});
</script>
