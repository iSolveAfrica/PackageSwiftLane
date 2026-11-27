<?php
/**
 * Delivery status (design: M-CDelivDone). Data: sl_caDeliveryDoneData(). Cancelling goes through the existing
 * ajax/sl/delivery_ajax.php (op cancel), which checks ownership and state again.
 */
if (!isset($d)) { $d = []; }
$stateLabel = ['pending' => 'Waiting for a rider', 'assigned' => 'Rider assigned', 'onroute' => 'On the way', 'failed' => 'Attempt failed', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
$statePill = ['pending' => 'onway', 'assigned' => 'sorting', 'onroute' => 'transit', 'failed' => 'pending', 'delivered' => 'delivered', 'cancelled' => 'plain'];
$r = $d['request'] ?? null;
$caPhone = !empty($caPhone);
?>
<?php if (empty($d['ready'])): ?>
<?= sl_caOpen($caPhone, 'My deliveries', '', sl_route('MyParcels')) ?>
  <?= sl_mxEmpty('truck', 'Door delivery is not available yet', 'We have not opened delivery to your door. You can still collect your parcels with a pickup code.', 'See my pickup codes', sl_route('PickupCodes')) ?>
<?= sl_caClose($caPhone) ?>
<?php return; endif; ?>
<?php if (!$r): ?>
<?= sl_caOpen($caPhone, 'My deliveries', $d['requests'] ? count($d['requests']) . ' request' . (count($d['requests']) === 1 ? '' : 's') : '', sl_route('DeliveryRequest'), sl_mxIcon('tag', 'Delivery pricing', sl_route('DeliveryPricing'))) ?>
  <?php if ($d['notFound']): ?><div class="mx-ban bad" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>We could not find that delivery on your account. Pick one from the list below.</span></div><?php endif; ?>
  <?php if (!$d['requests']): ?>
    <?= !empty($d['available'])
        ? sl_mxEmpty('truck', 'No deliveries yet', 'When a parcel is ready you can have it brought to your door.', 'Request door delivery', sl_route('DeliveryRequest'))
        : sl_mxEmpty('truck', 'Door delivery is not available yet', 'We have not opened delivery to your door. You can still collect your parcels with a pickup code.', 'See my pickup codes', sl_route('PickupCodes')) ?>
  <?php else: ?>
  <section class="mx-card mx-list">
    <?php foreach ($d['requests'] as $q): ?>
    <a class="mx-row" href="<?= h(sl_route('DeliveryDone', ['id' => (int) $q->id])) ?>"><span class="mx-li"><i class="ic i-truck" aria-hidden="true"></i></span><span class="mx-rm"><b>Delivery #<?= (int) $q->id ?> · <?= h(dl_money((int) $q->amount_cents, $q->currency)) ?></b><small><?= h($q->address) ?> · <?= h(sl_date($q->created_at)) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($statePill[$q->state] ?? 'plain') ?>"><?= h($stateLabel[$q->state] ?? ucfirst($q->state)) ?></span></span></a>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
<?= sl_caClose($caPhone, !empty($d['available']) ? '<a class="mx-btn" href="' . h(sl_route('DeliveryRequest')) . '"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Request door delivery</a>' : '') ?>
<?php return; endif;

$state = (string) $r->state;
$money = fn(int $cents) => dl_money($cents, $r->currency);
$b = $d['breakdown'];
$open = in_array($state, ['pending', 'assigned', 'onroute', 'failed'], true);
$canCancel = in_array($state, ['pending', 'assigned', 'failed'], true);
$titles = ['pending' => 'Delivery requested', 'assigned' => 'A rider has your delivery', 'onroute' => 'Your delivery is on the way', 'failed' => 'The delivery attempt failed', 'delivered' => 'Delivered', 'cancelled' => 'Delivery cancelled'];
$icons = ['pending' => 'clock', 'assigned' => 'check', 'onroute' => 'truck', 'failed' => 'alert', 'delivered' => 'check', 'cancelled' => 'x'];
$mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($r->lat . ',' . $r->lng) . ($r->place_id !== '' ? '&query_place_id=' . rawurlencode($r->place_id) : '');
$nParcels = count($d['items']);
$varLabel = ['zone' => 'Zone fee', 'kilometre' => 'Distance', 'package' => 'Parcels'][$r->pricing_mode] ?? 'Delivery';
$varSub = $r->pricing_mode === 'zone' ? (string) ($b['zone_name'] ?? '') : ($r->pricing_mode === 'kilometre' ? number_format($r->distance_m / 1000, 1) . ' km at ' . $money((int) ($b['unit_cents'] ?? 0)) . ' a km' : (int) ($b['parcels'] ?? $nParcels) . ' at ' . $money((int) ($b['unit_cents'] ?? 0)) . ' each');
$sum = (int) ($b['base_cents'] ?? 0) + (int) ($b['variable_cents'] ?? 0);
$telHref = $d['rider'] && $d['rider']['phone'] !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $d['rider']['phone']) : '';
?>
<?= sl_caOpen($caPhone, $stateLabel[$state] ?? 'Delivery', 'Delivery #' . (int) $r->id, sl_route('DeliveryDone'), sl_mxIcon('tag', 'Delivery pricing', sl_route('DeliveryPricing'))) ?>
  <div class="mxp-grid">
    <div class="mxp-col">
      <section class="cd-ok ca-ok <?= h($state) ?>">
        <div class="cd-okh"><span class="cd-okc"><i class="ic i-<?= h($icons[$state] ?? 'check') ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($titles[$state] ?? 'Delivery') ?></b><small class="mono">#<?= (int) $r->id ?> · <?= h($money((int) $r->amount_cents)) ?> <?= $r->fee_collected ? 'paid to the rider' : ($state === 'cancelled' ? 'not charged' : 'due on delivery') ?></small></span></div>
        <div class="cd-eta"><span><small>Delivery fee</small><b><?= h($money((int) $r->amount_cents)) ?></b></span><span><small>Distance</small><b><?= h(number_format($r->distance_m / 1000, 1)) ?> km</b></span><span><small>Parcels</small><b><?= (int) $nParcels ?></b></span></div>
        <ol class="cd-st ca-st" aria-label="Progress">
          <?php foreach ($d['steps'] as $st): ?><li class="<?= h($st['cls']) ?>"<?= $st['cls'] === 'cur' ? ' aria-current="step"' : '' ?>><i aria-hidden="true"></i><b><?= h($st['label']) ?></b><small><?= h($st['time']) ?></small></li><?php endforeach; ?>
        </ol>
      </section>
      <?php if ($state === 'pending'): ?><div class="mx-ban info"><i class="ic ic-sm i-clock" aria-hidden="true"></i><span>Dispatch is choosing a rider. We notify you as soon as one is assigned.</span></div>
      <?php elseif ($state === 'failed'): ?><div class="mx-ban warn" role="status"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The rider could not hand over your parcels<?= $d['failNote'] !== '' ? ': ' . h($d['failNote']) : '' ?>. Dispatch will arrange another attempt, or you can cancel below.</span></div>
      <?php elseif ($state === 'cancelled'): ?><div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>This request was cancelled. Your parcels stay at the hub; you can request a new delivery or collect them.</span></div>
      <?php endif; ?>
      <?php if ($d['rider']): $rd = $d['rider']; ?>
      <section class="mx-card cd-rd">
        <div class="cd-rdh"><span class="cd-rav" aria-hidden="true"><?= h($rd['ini']) ?></span><span class="mx-rm"><b><?= h($rd['name']) ?></b><small><?= $state === 'delivered' ? 'Delivered your parcels' : 'Your rider' ?><?= $rd['phone'] !== '' ? ' · ' . h($rd['phone']) : '' ?></small></span>
          <?php if ($rd['plate'] !== '' || $rd['vehicle'] !== ''): ?><span class="cd-plate"><small><?= h($rd['vehicle'] !== '' ? ucfirst($rd['vehicle']) : 'Vehicle') ?></small><?php if ($rd['plate'] !== ''): ?><b class="mono"><?= h($rd['plate']) ?></b><?php endif; ?></span><?php endif; ?></div>
        <?php if ($open): ?>
        <div class="cd-rda<?= $telHref === '' ? ' ca-two' : '' ?>">
          <?php if ($telHref !== ''): ?><a href="<?= h($telHref) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i><span>Call</span></a><?php endif; ?>
          <a href="<?= h(sl_route('LiveTracking')) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i><span>Track</span></a>
          <a href="support.php"><i class="ic ic-sm i-message" aria-hidden="true"></i><span>Support</span></a>
        </div>
        <?php endif; ?>
      </section>
      <?php endif; ?>
      <?php if ($state === 'delivered'): ?>
      <section class="mx-card mx-kv">
        <?php if ($r->receiver_name): ?><div><span>Received by</span><b><?= h($r->receiver_name) ?></b></div><?php endif; ?>
        <?php if ($r->completed_at): ?><div><span>Delivered</span><b><?= h(sl_date($r->completed_at, 'M j, Y H:i')) ?></b></div><?php endif; ?>
        <?php if ($r->proof_photo): ?><div><span>Proof of delivery</span><b><a class="ca-a" href="delivery_proof.php?id=<?= (int) $r->id ?>" target="_blank" rel="noopener">View the photo</a></b></div><?php endif; ?>
      </section>
      <?php elseif ($open): ?>
      <a class="mx-card mx-row" href="<?= h(sl_route('PickupCodes')) ?>"><span class="mx-li"><i class="ic i-key" aria-hidden="true"></i></span><span class="mx-rm"><b>Have your pickup code ready</b><small>The rider asks for the 6-digit code at the door. Share it only when you have your parcels.</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
      <?php endif; ?>
    </div>
    <div class="mxp-col">
      <?= sl_mxSec('Drop-off') ?>
      <section class="mx-card mx-kv ca-kv">
        <div><span>Address</span><b><?= h($r->address) ?></b></div>
        <div><span>Contact</span><b><?= h($r->contact_name) ?> · <?= h($r->contact_phone) ?></b></div>
        <?php if ($r->instructions !== ''): ?><div><span>Note for the rider</span><b><?= h($r->instructions) ?></b></div><?php endif; ?>
        <?php if (!empty($d['origin']['address'])): ?><div><span>Leaves from</span><b><?= h($d['origin']['address']) ?></b></div><?php endif; ?>
        <div><span>Map</span><b><a class="ca-a" href="<?= h($mapUrl) ?>" target="_blank" rel="noopener">Open the pin in Google Maps</a></b></div>
      </section>
      <?= sl_mxSec('In this delivery') ?>
      <?php if (!$d['items']): ?>
        <?= sl_mxEmpty('box', 'Parcel details are not available', 'The parcels of this request are no longer on your account.') ?>
      <?php else: ?>
      <section class="mx-card mx-list">
        <?php foreach ($d['items'] as $it): ?>
        <a class="mx-row" href="<?= h($it['href']) ?>"><span class="mx-li"><i class="ic i-box" aria-hidden="true"></i></span><span class="mx-rm"><b class="mono"><?= h($it['tracking']) ?></b><small><?= $r->module === 'air' ? 'Air shipment' : 'Sea package' ?><?= $it['status'] !== '' ? ' · ' . h($it['status']) : '' ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>
      <section class="mx-card cd-bd">
        <div class="cd-bdh"><b>Delivery fee · #<?= (int) $r->id ?></b></div>
        <?php if ((int) ($b['base_cents'] ?? 0) > 0): ?><div class="cd-bl"><span><b>Base fee</b></span><em><?= h($money((int) $b['base_cents'])) ?></em></div><?php endif; ?>
        <?php if (isset($b['variable_cents'])): ?><div class="cd-bl"><span><b><?= h($varLabel) ?></b><?php if ($varSub !== ''): ?><small><?= h($varSub) ?></small><?php endif; ?></span><em><?= h($money((int) $b['variable_cents'])) ?></em></div><?php endif; ?>
        <?php if ($b && (int) $r->amount_cents > $sum): ?><div class="cd-bl"><span><b>Minimum charge applies</b><small>The minimum is <?= h($money((int) ($b['minimum_cents'] ?? 0))) ?></small></span><em><?= h($money((int) $r->amount_cents - $sum)) ?></em></div><?php endif; ?>
        <div class="cd-bt"><span>Total</span><b><?= h($money((int) $r->amount_cents)) ?></b></div>
        <p class="cd-bf"><?= $r->fee_collected ? 'The rider recorded this fee as collected.' : ($state === 'cancelled' ? 'Nothing was charged for this request.' : 'You pay this fee when the parcel reaches you. It is separate from your shipping bill.') ?> <a href="<?= h(sl_route('DeliveryPricing')) ?>">How pricing works</a></p>
      </section>
      <?php if ($canCancel): ?><p class="mx-note">You can cancel free of charge until the rider sets off.</p><?php endif; ?>
    </div>
  </div>
<?php
$foot = '';
if ($open) {
    $foot = '<div class="' . ($canCancel ? 'mx-two' : 'mx-btns') . '"><a class="mx-btn" href="' . h(sl_route('LiveTracking')) . '"><i class="ic ic-sm i-route" aria-hidden="true"></i>Track live</a>'
        . ($canCancel ? '<button type="button" class="mx-btn sec" data-ca-stay data-sl-action="ajax/sl/delivery_ajax.php" data-p-op="cancel" data-p-id="' . (int) $r->id . '" data-sl-confirm="Cancel delivery #' . (int) $r->id . '? Your parcels stay at the hub.">Cancel delivery</button>' : '') . '</div>';
} elseif (!empty($d['available'])) {
    $foot = '<a class="mx-btn" href="' . h(sl_route('DeliveryRequest')) . '"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Request another delivery</a>';
}
?>
<?= sl_caClose($caPhone, $foot) ?>
