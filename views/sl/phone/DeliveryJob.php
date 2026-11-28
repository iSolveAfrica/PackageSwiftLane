<?php
/**
 * Phone screen of a customer door delivery for its rider (design: M-RJob, then M-RProof for the handover).
 * Same $d as views/sl/pages/DeliveryJob.php (dl_jobData) and the same form: fields op, id, code, receiver, photo,
 * fee_collected to ajax/sl/delivery_ajax.php, stepped by assets/swiftlane/pwa/handover.js (data-pwa-* hooks).
 * Left out: live ETA, masked calls, signature pad, cash change calculator, customer rating; none exist in the system.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/views/sl/data/rider_ops.php';
$slPhone['cls'] = 'rs-r';
$slPhone['tab'] = 'Jobs';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-rider-flow.css')) ?>">
<?php
$r = $d['request'] ?? null;
if (empty($d['ready']) || !$r) {
    echo sl_phoneTop('Delivery', '', ['back' => sl_route('RiderQueue')]);
    echo '<main class="mx-body" id="sl-main" tabindex="-1">' . sl_mxEmpty('box', 'This delivery is not yours', 'It is not assigned to you, or it is no longer available.', 'Back to my jobs', sl_route('RiderQueue')) . '</main>';
    return;
}
$slPhone['cls'] = 'rs-r rs-nohd';
$slPhone['nav'] = 'none';
$slPhone['scripts'][] = 'dataJs/sl/rider_map.js';
$slPhone['scripts'][] = 'assets/swiftlane/pwa/handover.js';
$id = (int) $r->id;
$parcelCount = count(json_decode((string) $r->parcel_json, true) ?: []);
$origin = json_decode((string) $r->origin_json, true) ?: [];
$items = sl_tableExists('cdb_delivery_items') ? sl_rows('SELECT tracking FROM cdb_delivery_items WHERE request_id = :i', [':i' => $id]) : [];
$fee = ro_cents((int) $r->amount_cents, (string) $r->currency);
$share = ro_cents((int) $r->rider_cents, (string) $r->currency);
$navUrl = 'https://www.google.com/maps/dir/?' . http_build_query(['api' => 1, 'destination' => $r->lat . ',' . $r->lng, 'destination_place_id' => $r->place_id, 'travelmode' => 'driving']);
$tel = ro_tel((string) $r->contact_phone);
$wa = sl_riderMessageLink((string) $r->contact_phone);
$stateTxt = ['assigned' => 'Not started', 'onroute' => 'On the way', 'failed' => 'With dispatch', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'][$r->state] ?? ucfirst((string) $r->state);
$eventAt = [];
foreach (array_reverse($d['events'] ?? []) as $e) $eventAt[$e->state] = $eventAt[$e->state] ?? (string) $e->created_at;
$tl = [['assigned', 'Assigned to you', $r->assigned_at ?: ($eventAt['assigned'] ?? ''), (string) ($origin['address'] ?? '')], ['onroute', 'On the way', $eventAt['onroute'] ?? '', $parcelCount . ' parcel' . ($parcelCount === 1 ? '' : 's')], ['delivered', 'Delivered', $r->completed_at ?: '', (string) ($r->receiver_name ?: $r->contact_name)]];
$reached = ['assigned' => 0, 'onroute' => 1, 'failed' => 1, 'delivered' => 2][$r->state] ?? 0;
$safety = '<a class="rs-rb" href="' . h(sl_route('RiderSafety')) . '" aria-label="Safety"><i class="ic i-shield" aria-hidden="true"></i></a>';
echo ro_mapTop(ro_embed((string) $r->address), $navUrl, 'Drop-off', $r->contact_name, sl_route('RiderQueue'), $safety, (int) $r->distance_m > 0 ? round((int) $r->distance_m / 1000, 1) . ' km by road' : '');
?>
<div class="rs-oid"><div><b>#<?= $id ?></b><span><?= h((string) ($origin['address'] ?? 'Hub')) ?> <i class="ic ic-sm i-arrow" aria-hidden="true"></i> <?= h($r->address) ?></span></div><span class="rs-st<?= $r->state === 'delivered' ? ' ok' : '' ?>"><?= h($stateTxt) ?></span></div>
<section class="mx-k c3"><?= sl_mxKpi('route', 'Km', (int) $r->distance_m > 0 ? (string) round((int) $r->distance_m / 1000, 1) : '—', 'by road') ?><?= sl_mxKpi('box', 'Parcels', (string) $parcelCount, 'you get ' . $share) ?><?= sl_mxKpi('dollar', 'Fee', number_format((int) $r->amount_cents / 100, 2), $r->currency . ($r->fee_collected ? ' collected' : ' on delivery'), $r->fee_collected ? 'up' : 'warn') ?></section>
<ol class="rs-tl"><?php foreach ($tl as $i => $t): $cls = $i < $reached || $r->state === 'delivered' ? 'done' : ($i === $reached ? 'cur' : 'fut'); ?><li class="<?= $cls ?>"><span class="rs-td" aria-hidden="true"></span><div><b><?= h($t[1]) ?></b><small><?= $t[2] ? h(sl_date($t[2], 'M j, H:i')) : ($i === 2 ? 'PIN, receiver name, photo' : 'Not yet') ?></small></div><em><?= h(mb_strimwidth($t[3], 0, 26, '…')) ?></em></li><?php endforeach; ?></ol>
<section class="mx-card rs-cust"><span class="mx-ava"><?= h(sl_initials((string) $r->contact_name)) ?></span><span class="mx-rm"><b><?= h($r->contact_name) ?></b><small><?= h($r->address) ?></small></span><a class="rs-rb" href="<?= h($navUrl) ?>" target="_blank" rel="noopener" aria-label="Open in Google Maps"><i class="ic i-route" aria-hidden="true"></i></a></section>
<div class="rs-pair"><?php if ($tel !== ''): ?><a class="mx-btn rs-amb" href="<?= h($tel) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Call <?= h(explode(' ', trim((string) $r->contact_name))[0]) ?></a><?php endif; ?><?php if ($wa !== ''): ?><a class="mx-btn sec rs-out" href="<?= h($wa) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp</a><?php else: ?><a class="mx-btn sec rs-out" href="<?= h(sl_route('RiderChat')) ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i>Dispatch</a><?php endif; ?></div>
<small class="rs-mask"><i class="ic ic-sm i-info" aria-hidden="true"></i>Calls use the customer’s own number</small>
<?php if ($r->instructions): ?><section class="mx-card rb-notes"><div class="rb-nh"><i class="ic ic-sm i-note" aria-hidden="true"></i><b>Delivery notes</b></div><p><?= h($r->instructions) ?></p></section><?php endif; ?>
<div class="mx-sec"><h2>Items</h2></div>
<section class="mx-card mx-list">
<?php foreach ($items as $it): ?><div class="mx-row"><span class="mx-li"><i class="ic i-box" aria-hidden="true"></i></span><span class="mx-rm"><b class="mono"><?= h($it->tracking) ?></b><small><?= h(strtoupper((string) $r->module)) ?> parcel</small></span></div><?php endforeach; ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-dollar" aria-hidden="true"></i></span><span class="mx-rm"><b>Delivery fee</b><small><?= $r->fee_collected ? 'Recorded as collected' : 'Paid on delivery · shipping invoices are separate' ?></small></span><span class="mx-rr"><b><?= h($fee) ?></b><small>you get <?= h($share) ?></small></span></div>
</section>
<?php if ($r->state === 'assigned'): ?>
<button type="button" class="mx-btn ro-btn" data-sl-action="ajax/sl/delivery_ajax.php" data-p-op="start" data-p-id="<?= $id ?>" data-sl-confirm="Confirm all <?= $parcelCount ?> parcels are with you and start delivery?" data-sl-reload><i class="ic ic-sm i-truck" aria-hidden="true"></i>I have the parcels · Start delivery</button>
<a class="mx-btn sec rs-out" href="<?= h(sl_route('RiderScan', ['id' => 'd' . $id])) ?>"><i class="ic ic-sm i-scan" aria-hidden="true"></i>Scan the parcels first</a>
<?php elseif ($r->state === 'onroute'): ?>
<div class="mx-sec" id="proof"><h2>Proof of delivery</h2></div>
<form class="ro-form" data-pwa-handover data-parcel-count="<?= $parcelCount ?>" data-sl-form data-sl-redirect method="post" action="ajax/sl/delivery_ajax.php" enctype="multipart/form-data"><input type="hidden" name="op" value="deliver"><input type="hidden" name="id" value="<?= $id ?>">
<ol class="rb-steps" style="grid-template-columns:repeat(3,1fr)" aria-label="Handover steps" data-pwa-steps hidden><li aria-current="step"><i>1</i><span>Code</span></li><li><i>2</i><span>Photo</span></li><li><i>3</i><span>Confirm</span></li></ol>
<fieldset data-pwa-step="0"><legend>Customer pickup code</legend><p class="mx-note">Ask for the code on the customer’s phone.<?= $parcelCount > 1 ? ' For parcels with different codes, separate them with commas.' : '' ?> If it does not match, do not hand over the parcel.</p><label class="mx-f"><small>Pickup PIN<?= $parcelCount > 1 ? 's' : '' ?></small><input class="ro-in <?= $parcelCount === 1 ? 'ro-pin' : '' ?>" name="code" required <?= $parcelCount === 1 ? 'inputmode="numeric" maxlength="6" pattern="[0-9]{6}"' : 'maxlength="350" pattern="[0-9]{6}( *, *[0-9]{6})*"' ?> autocomplete="off" placeholder="<?= $parcelCount === 1 ? '······' : '123456, 654321' ?>"></label><button type="button" class="mx-btn" data-pwa-next hidden>Continue to the photo</button></fieldset>
<fieldset data-pwa-step="1"><legend>Photo proof</legend><label class="mx-f"><small>Receiver’s full name</small><input class="ro-in" name="receiver" required minlength="2" maxlength="150" autocomplete="name" placeholder="Who did you hand the parcels to?"></label><label class="ro-photo"><i class="ic i-image" aria-hidden="true"></i><span data-pwa-photo-label>Take a photo of the handover</span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment" required aria-label="Delivery proof photo"></label><img class="ro-prev" alt="Selected delivery proof" data-pwa-photo-preview hidden><div class="mx-two"><button type="button" class="mx-btn sec" data-pwa-prev hidden>Back</button><button type="button" class="mx-btn" data-pwa-next hidden>Review</button></div></fieldset>
<fieldset data-pwa-step="2"><legend>Check and confirm</legend><section class="mx-card rb-cash"><div class="rb-cah"><span class="mx-li"><i class="ic i-dollar" aria-hidden="true"></i></span><span class="mx-rm"><b>Delivery fee to collect</b><small><?= $parcelCount ?> parcel<?= $parcelCount === 1 ? '' : 's' ?> · receiver: <span data-pwa-review-receiver>enter the receiver above</span></small></span><b class="rb-camt"><?= h($fee) ?></b></div><label class="ro-check"><input type="checkbox" name="fee_collected" value="1"><span>I collected the <?= h($fee) ?> delivery fee</span></label></section><p class="mx-note">This records the delivery fee only; it does not settle the shipping invoice. The PIN and photo are checked when you confirm, and the delivery is complete only when the server confirms it.</p><div class="mx-two"><button type="button" class="mx-btn sec" data-pwa-prev hidden>Back</button><button class="mx-btn" type="submit"><i class="ic ic-sm i-check" aria-hidden="true"></i>Complete delivery</button></div></fieldset>
</form>
<details class="ro-more"><summary class="mx-btn sec rs-out"><i class="ic ic-sm i-flag" aria-hidden="true"></i>Report a problem</summary>
<form class="ro-form" data-sl-form data-sl-redirect action="ajax/sl/delivery_ajax.php" method="post"><input type="hidden" name="op" value="fail"><input type="hidden" name="id" value="<?= $id ?>"><label class="mx-f"><small>What happened?</small><textarea class="ro-in" name="note" required minlength="3" maxlength="500" placeholder="E.g. customer not home; called twice"></textarea></label><button class="mx-btn sec" type="submit">Send to dispatch for review</button><a class="mx-btn sec" href="<?= h(sl_route('RiderSafety')) ?>"><i class="ic ic-sm i-shield" aria-hidden="true"></i>Safety toolkit</a></form></details>
<?php elseif ($r->state === 'failed'): ?>
<div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Dispatch must review and reassign this delivery before another attempt.</span></div>
<?php elseif ($r->state === 'delivered'): ?>
<div class="mx-ban ok"><i class="ic ic-sm i-check" aria-hidden="true"></i><span>Received by <?= h((string) $r->receiver_name) ?> · <?= h(sl_date($r->completed_at, 'M j, H:i')) ?></span></div>
<a class="mx-btn" href="<?= h(sl_route('RiderDone', ['id' => 'd' . $id])) ?>"><i class="ic ic-sm i-check" aria-hidden="true"></i>See the trip summary</a>
<?php if ($r->proof_photo): ?><a class="mx-btn sec rs-out" href="delivery_proof.php?id=<?= $id ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-image" aria-hidden="true"></i>View the delivery photo</a><?php endif; ?>
<?php endif; ?>
<a class="mx-btn sec rs-out" href="<?= h(sl_route('RiderQueue')) ?>">Back to my jobs</a>
</section></main>
