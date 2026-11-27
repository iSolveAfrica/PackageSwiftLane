<?php
/**
 * Delivery pricing (design: M-CDelivPrice). Data: sl_caDeliveryPricingData(). Every amount comes from the
 * delivery configuration (cdb_delivery_config, cdb_delivery_zones); the estimate is worked out with dl_price().
 */
if (!isset($d)) { $d = []; }
$caPhone = !empty($caPhone);
?>
<?= sl_caOpen($caPhone, 'Delivery pricing', '', sl_route('DeliveryRequest')) ?>
  <?php if (empty($d['available'])): ?>
    <?= sl_mxEmpty('truck', 'Door delivery is not available yet', 'We have not opened delivery to your door. You can still collect your parcels with a pickup code.', 'See my pickup codes', sl_route('PickupCodes')) ?>
<?= sl_caClose($caPhone) ?>
  <?php return; endif;
$c = $d['config']; $cur = $d['currency']; $mode = $d['mode'];
$m = fn(int $cents) => dl_money($cents, $cur);
$base = (int) $c['base_cents']; $min = (int) $c['minimum_cents']; $perKm = (int) $c['per_km_cents']; $perPkg = (int) $c['per_package_cents']; $maxKm = max(1, (int) $c['max_km']);
$zoneTotal = fn(int $cents) => max($min, $base + $cents);
$lastZone = $d['last']['zone_id'] ?? 0;
$usable = array_values(array_filter($d['modes'], fn($x) => ($x === 'zone' && $d['zones']) || ($x === 'kilometre' && $perKm > 0) || ($x === 'package' && $perPkg > 0)));
$startKm = $d['last'] ? max(1, min($maxKm, (int) round($d['last']['km']))) : min($maxKm, 5);
$explain = ['zone' => 'One price for each delivery zone.', 'kilometre' => 'Priced on the road distance from our hub to your pin.', 'package' => 'Priced on the number of parcels in the delivery.'];
?>
  <div class="mxp-grid">
    <div class="mxp-col">
      <section class="mx-hero lt">
        <?php if ($d['estimate']): ?>
        <div class="mx-hk">Your last delivery address<span class="mx-tag"><?= h(sl_caModeLabel($mode)) ?></span></div>
        <b class="mx-hv"><?= h($m($d['estimate']['cents'])) ?></b>
        <small><?= h($d['last']['address']) ?> · <?= h(number_format($d['last']['km'], 1)) ?> km by road<?= $d['last']['zone'] !== '' ? ' · ' . h($d['last']['zone']) . ' zone' : '' ?>. For one parcel at today's prices.</small>
        <?php else: ?>
        <div class="mx-hk">Door delivery<span class="mx-tag"><?= h(sl_caModeLabel($mode)) ?></span></div>
        <b class="mx-hv"><?= $mode === 'zone' && $d['zones'] ? 'From ' . h($m($zoneTotal($d['zones'][0]['cents']))) : ($min > 0 ? 'From ' . h($m($min)) : h(sl_caModeLabel($mode))) ?></b>
        <small><?= h($explain[$mode] ?? '') ?> You see the exact price for your address before you confirm.</small>
        <?php endif; ?>
      </section>
      <?= sl_mxSec('How the price is worked out') ?>
      <section class="mx-card mx-kv">
        <div><span>Pricing</span><b><?= h(sl_caModeLabel($mode)) ?></b></div>
        <?php if ($base > 0): ?><div><span>Base fee</span><b><?= h($m($base)) ?></b></div><?php endif; ?>
        <?php if ($mode === 'kilometre'): ?><div><span>Each road kilometre</span><b><?= h($m($perKm)) ?></b></div><?php endif; ?>
        <?php if ($mode === 'package'): ?><div><span>Each parcel</span><b><?= h($m($perPkg)) ?></b></div><?php endif; ?>
        <?php if ($mode === 'zone'): ?><div><span>Zone fee</span><b>See the zones below</b></div><?php endif; ?>
        <?php if ($min > 0): ?><div><span>Minimum charge</span><b><?= h($m($min)) ?></b></div><?php endif; ?>
        <div><span>Furthest we deliver</span><b><?= sl_num($maxKm) ?> km by road</b></div>
      </section>
      <p class="mx-note"><?= h($explain[$mode] ?? '') ?> The delivery fee is separate from your shipping bill and is paid when the parcel reaches you.</p>
      <?php if ($d['zones']): ?>
      <?= sl_mxSec($mode === 'zone' ? 'Zone prices' : 'Where we deliver') ?>
      <section class="mx-card cd-tb cd-w">
        <div class="cd-th"><span>Zone</span><span>Includes</span><span><?= $mode === 'zone' ? 'Price' : '' ?></span></div>
        <?php foreach ($d['zones'] as $z): ?>
        <div class="cd-tr<?= $z['id'] === $lastZone ? ' on' : '' ?>"><span><b><?= h($z['name']) ?></b><?php if ($z['radius'] > 0): ?><small>within <?= h(rtrim(rtrim(number_format($z['radius'], 1), '0'), '.')) ?> km</small><?php endif; ?></span><span><?= h($z['places'] !== '' ? $z['places'] : '—') ?></span><span><?php if ($mode === 'zone'): ?><b><?= h($m($zoneTotal($z['cents']))) ?></b><?php endif; ?></span></div>
        <?php endforeach; ?>
      </section>
      <?php if ($lastZone && $d['last']['zone'] !== ''): ?><div class="cd-you"><i class="ic ic-sm i-pin" aria-hidden="true"></i><span>Your last delivery went to <b><?= h($d['last']['zone']) ?></b>. An address outside every zone cannot be delivered to yet.</span></div>
      <?php else: ?><p class="mx-note">An address outside every zone cannot be delivered to yet.</p><?php endif; ?>
      <?php endif; ?>
    </div>
    <div class="mxp-col">
      <?php if ($usable): ?>
      <?= sl_mxSec('Try it') ?>
      <section class="mx-card cd-calc" data-ca-calc data-mx-scope data-mode="<?= h(in_array($mode, $usable, true) ? $mode : $usable[0]) ?>" data-base="<?= $base ?>" data-min="<?= $min ?>" data-km="<?= $perKm ?>" data-pkg="<?= $perPkg ?>" data-cur="<?= h($cur) ?>">
        <?php if (count($usable) > 1): $calcMode = in_array($mode, $usable, true) ? $mode : $usable[0]; ?>
        <div class="mx-seg" data-mx-seg role="group" aria-label="Pricing model"><?php foreach ($usable as $u): ?><button type="button" data-mx-panel="<?= h($u) ?>" class="<?= $u === $calcMode ? 'on' : '' ?>" aria-pressed="<?= $u === $calcMode ? 'true' : 'false' ?>"><?= h(sl_caModeLabel($u)) ?></button><?php endforeach; ?></div>
        <?php else: $calcMode = $usable[0]; endif; ?>
        <?php if (in_array('zone', $usable, true)): ?>
        <div class="cd-cr" data-mx-panel-of="zone"<?= $calcMode === 'zone' ? '' : ' hidden' ?>>
          <label class="mx-f"><span>Delivery zone</span>
            <select class="mx-in" data-ca-in="zone"><?php foreach ($d['zones'] as $z): ?><option value="<?= (int) $z['cents'] ?>"<?= $z['id'] === $lastZone ? ' selected' : '' ?>><?= h($z['name']) ?></option><?php endforeach; ?></select>
          </label>
        </div>
        <?php endif; ?>
        <?php if (in_array('kilometre', $usable, true)): ?>
        <div class="cd-cr" data-mx-panel-of="kilometre"<?= $calcMode === 'kilometre' ? '' : ' hidden' ?>>
          <div class="cd-crh"><small id="ca-km-l">Road distance from the hub</small><b><span data-ca-out="km"><?= $startKm ?></span> km</b></div>
          <input type="range" class="ca-range" min="1" max="<?= $maxKm ?>" step="1" value="<?= $startKm ?>" data-ca-in="km" aria-labelledby="ca-km-l">
          <div class="cd-rgl"><small>1</small><small><?= (int) round($maxKm / 2) ?></small><small><?= $maxKm ?> km</small></div>
        </div>
        <?php endif; ?>
        <?php if (in_array('package', $usable, true)): ?>
        <div class="cd-cr" data-mx-panel-of="package"<?= $calcMode === 'package' ? '' : ' hidden' ?>>
          <div class="cd-crh"><small id="ca-pk-l">Parcels in the delivery</small><b data-ca-out="parcels">1</b></div>
          <input type="range" class="ca-range" min="1" max="10" step="1" value="1" data-ca-in="parcels" aria-labelledby="ca-pk-l">
          <div class="cd-rgl"><small>1</small><small>5</small><small>10</small></div>
        </div>
        <?php endif; ?>
        <div class="cd-res" aria-live="polite"><span><small>Estimate</small><b data-ca-out="total">—</b></span><span class="cd-resb"><small data-ca-out="how"></small></span></div>
      </section>
      <p class="mx-note">This is an estimate. The price you approve is calculated from the road distance to the pin you drop.</p>
      <?php endif; ?>
      <?= sl_mxSec('Good to know') ?>
      <section class="mx-card mx-list">
        <?= sl_mxRow('check', 'You approve the price first', 'No rider is sent until you accept the quote') ?>
        <?= sl_mxRow('dollar', 'Pay on delivery', 'The delivery fee is not added to your shipping bill') ?>
        <?= sl_mxRow('key', 'Hand over with your pickup code', 'The rider asks for the 6-digit code', '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', sl_route('PickupCodes')) ?>
        <?= sl_mxRow('message', 'Questions about a price?', 'Message our support team', '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', 'support.php') ?>
      </section>
    </div>
  </div>
<?= sl_caClose($caPhone, '<a class="mx-btn" href="' . h(sl_route('DeliveryRequest')) . '"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Request door delivery</a>', true) ?>
