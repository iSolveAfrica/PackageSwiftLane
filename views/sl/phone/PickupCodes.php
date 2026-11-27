<?php
/**
 * M-CCodes (pickup_codes.php, view PickupCodes): the customer's active pickup codes (sl_pickupCodesData: own,
 * unused, unexpired only). Parcels that are not ready have no code yet, so no "not ready" cards are invented.
 */
$slPhone['nav'] = 'customer';
$slPhone['tab'] = 'Parcels';
$codes = (array) ($d['codes'] ?? []);
$n = count($codes);
?>
<?= sl_phoneTop('Pickup codes', '', ['back' => sl_route('Main')]) ?>
<main class="mx-body">
<h2 class="b2-hl">Pickup codes<span><?= $n ? $n . ' ready to show.' : 'None ready yet.' ?></span></h2>
<div class="mx-ban warn"><i class="ic ic-sm i-shield" aria-hidden="true"></i><span>Keep your codes private. Show the code and bring a photo ID. Share it only with the person handing over your parcel.</span></div>
<?php foreach ($codes as $c): $code = (string) $c->code; $digits = preg_split('//u', $code, -1, PREG_SPLIT_NO_EMPTY); $half = (int) ceil(count($digits) / 2); $parcels = array_filter(array_map('trim', explode(',', (string) $c->parcels))); ?>
<section class="b2-cc" aria-label="Pickup code for <?= h($c->module === 'sea' ? 'sea packages' : 'air shipments') ?>">
<div class="b2-cch"><span class="b2-st <?= $c->module === 'sea' ? 'sea' : 'air' ?>"><?= $c->module === 'sea' ? 'Sea packages' : 'Air shipments' ?></span><span class="b2-st ok">Ready to show</span><button type="button" class="b2-cpy" data-sl-copy="<?= h($code) ?>" aria-label="Copy code"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div>
<div class="b2-lch"><span class="mx-rm"><b><?= count($parcels) ?> parcel<?= count($parcels) === 1 ? '' : 's' ?></b><small class="mono"><?= h(implode(', ', $parcels)) ?></small></span></div>
<div class="b2-cch"><div class="b2-code" role="img" aria-label="Code <?= h(implode(' ', $digits)) ?>"><?php foreach ($digits as $i => $ch): ?><?= $i === $half ? '<i></i>' : '' ?><span><?= h($ch) ?></span><?php endforeach; ?></div></div>
<div class="b2-ccf"><span>Covers <b><?= count($parcels) ?> parcel<?= count($parcels) === 1 ? '' : 's' ?></b></span><span>Valid until <b><?= h(sl_date($c->expires_at, 'j M')) ?></b></span></div>
</section>
<?php endforeach; ?>
<?php if (!$codes): ?>
<section class="b2-empty"><span class="b2-oi"><i class="ic i-key" aria-hidden="true"></i></span><b>No codes yet</b><small>A code appears here when a parcel is ready at the hub. Collected or expired codes are removed automatically.</small><a class="b2-gh" href="<?= h(sl_route('LiveTracking')) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Track my parcels</a></section>
<?php endif; ?>
<div class="b2-lk"><a href="delivery_request.php"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Request delivery</a><a href="<?= h(sl_route('MyParcels') . '?filter=ready') ?>"><i class="ic ic-sm i-layers" aria-hidden="true"></i>Parcels ready</a><a href="support.php?q=I%20need%20help%20with%20my%20pickup%20code"><i class="ic ic-sm i-help" aria-hidden="true"></i>Need help with a code?</a></div>
<p class="mx-note">A code stops working once the parcels are handed over.</p>
</main>
