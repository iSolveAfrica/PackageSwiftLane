<?php
/**
 * M-CDelivery + M-CDropPin: request delivery to the door. Same flow, endpoint and field names as the web page
 * (views/sl/pages/DeliveryRequest.php → ajax/sl/delivery_ajax.php op quote / approve) and the same script
 * (dataJs/sl/delivery.js: Google Maps search, pin, "use my location"), so the element ids are the web page's.
 * Data: dl_customerData(). The design's speed, time-slot, in-app payment and tip blocks have no backend and are not shown.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['tab'] = 'Parcels';
$slPhone['scripts'][] = 'dataJs/sl/delivery.js';
$stateLabel = ['pending' => 'Waiting for a rider', 'assigned' => 'Rider assigned', 'onroute' => 'On the way', 'failed' => 'Attempt failed', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
$statePill = ['pending' => 'onway', 'assigned' => 'sorting', 'onroute' => 'transit', 'failed' => 'pending', 'delivered' => 'delivered', 'cancelled' => 'plain'];
$r = $d['request'] ?? null;
$c = $d['config'];
$nReady = 0;
foreach ($d['parcels'] as $list) { $nReady += count($list); }
$modeText = ['zone' => 'one price for each delivery zone', 'kilometre' => 'priced on the road distance', 'package' => 'priced per parcel'][$c['default_mode']] ?? '';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pages/CustomerApp.css')) ?>">
<?= sl_phoneTop('Deliver to my door', '', ['back' => $r ? sl_route('DeliveryRequest') : sl_route('Main')], sl_phoneIcon('tag', 'Delivery prices', sl_route('DeliveryPricing'))) ?>
<main class="mx-body ca">
<?php if (empty($d['ready'])): ?>
<?= sl_mxEmpty('truck', 'Door delivery is not available yet', 'We have not opened delivery to your door. You can still collect your parcels with a pickup code.', 'See my pickup codes', sl_route('PickupCodes')) ?>
</main>
<?php return; endif; ?>
<?php if (!empty($d['notFound'])): ?><div class="mx-ban bad" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>We could not find that delivery on your account.</span></div><?php endif; ?>

<?php if ($r && $r->state === 'quoted'): $b = json_decode((string) $r->breakdown_json, true) ?: []; $expired = strtotime((string) $r->expires_at) <= time(); ?>
<section class="mx-card mx-intro"><b>Confirm your delivery price</b><p>Check the drop-off point and the price. No rider is sent until you approve.</p></section>
<section class="mx-card dp-addr"><span class="mx-li"><i class="ic i-pin" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r->address) ?></b><small><?= h(number_format($r->distance_m / 1000, 1)) ?> km by road<?= !empty($b['zone_name']) ? ' · ' . h($b['zone_name']) : '' ?></small></span></section>
<section class="mx-card mx-kv">
<div><span>Contact</span><b><?= h($r->contact_name) ?> · <?= h($r->contact_phone) ?></b></div>
<?php if ($r->instructions !== ''): ?><div><span>Note for the rider</span><b><?= h($r->instructions) ?></b></div><?php endif; ?>
<div><span>Packages</span><b><?= count(json_decode((string) $r->parcel_json, true) ?: []) ?></b></div>
<?php if ((int) ($b['base_cents'] ?? 0) > 0): ?><div><span>Base fee</span><b><?= h(dl_money((int) $b['base_cents'], $r->currency)) ?></b></div><?php endif; ?>
<?php if (isset($b['variable_cents'])): ?><div><span>Delivery fee (<?= h(number_format($r->distance_m / 1000, 1)) ?> km)</span><b><?= h(dl_money((int) $b['variable_cents'], $r->currency)) ?></b></div><?php endif; ?>
<div><span><b>Total</b></span><b><?= h(dl_money((int) $r->amount_cents, $r->currency)) ?></b></div>
</section>
<?php if ($expired): ?>
<div class="mx-ban warn" role="status"><i class="ic ic-sm i-clock" aria-hidden="true"></i><span>This price expired at <?= h(sl_date($r->expires_at, 'M j, H:i')) ?>. Get a new price to continue.</span></div>
<a class="mx-btn" href="<?= h(sl_route('DeliveryRequest')) ?>">Get a new price</a>
<?php else: ?>
<form class="ca-quote" data-sl-form data-sl-redirect data-dl-done="<?= h(sl_route('DeliveryDone', ['id' => (int) $r->id])) ?>" method="post" action="ajax/sl/delivery_ajax.php"><input type="hidden" name="op" value="approve"><input type="hidden" name="id" value="<?= (int) $r->id ?>">
<button class="mx-btn" type="submit">Request delivery · <?= h(dl_money((int) $r->amount_cents, $r->currency)) ?></button></form>
<a class="mx-btn sec" href="<?= h(sl_route('DeliveryRequest')) ?>">Change location or parcels</a>
<p class="mx-note">This price is held until <?= h(sl_date($r->expires_at, 'H:i')) ?>. You pay the delivery fee when the parcel reaches you; it is separate from your shipping bill. See <a class="mx-lnk" href="<?= h(sl_route('DeliveryPricing')) ?>">how delivery pricing works</a>.</p>
<?php endif; ?>

<?php elseif ($r): ?>
<a class="mx-card mx-row" href="<?= h(sl_route('DeliveryDone', ['id' => (int) $r->id])) ?>"><span class="mx-li"><i class="ic i-truck" aria-hidden="true"></i></span><span class="mx-rm"><b>Delivery #<?= (int) $r->id ?> · <?= h($stateLabel[$r->state] ?? ucfirst($r->state)) ?></b><small><?= h($r->address) ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<a class="mx-btn" href="<?= h(sl_route('DeliveryDone', ['id' => (int) $r->id])) ?>">Open delivery status</a>

<?php elseif (empty($c['enabled'])): ?>
<?= sl_mxEmpty('truck', 'Door delivery is paused', 'We are not taking delivery requests right now. You can still collect your parcels with a pickup code.', 'Message support', 'support.php') ?>

<?php elseif (!$d['parcels']): ?>
<?= sl_mxEmpty('box', 'No parcels ready for delivery', 'A parcel appears here when it is ready at the hub and has no delivery booked.', 'See my parcels', sl_route('MyParcels')) ?>
<a class="cd-top" href="<?= h(sl_route('DeliveryPricing')) ?>"><span class="cd-ti"><i class="ic i-tag" aria-hidden="true"></i></span><span class="mx-rm"><b>How delivery is priced</b><small><?= h(ucfirst($modeText)) ?></small></span><span class="cd-lnk">Prices<i class="ic ic-sm i-right" aria-hidden="true"></i></span></a>

<?php else: $modules = array_keys($d['parcels']); ?>
<section class="mx-card mx-intro"><b>Packages ready for delivery</b><p>Tick what you want brought to you, then drop the pin where the rider should stop.</p></section>
<a class="cd-top" href="<?= h(sl_route('DeliveryPricing')) ?>"><span class="cd-ti"><i class="ic i-truck" aria-hidden="true"></i></span><span class="mx-rm"><b><?= (int) $nReady ?> parcel<?= $nReady === 1 ? '' : 's' ?> ready at the hub</b><small><?= h(ucfirst($modeText)) ?></small></span><span class="cd-lnk">Prices<i class="ic ic-sm i-right" aria-hidden="true"></i></span></a>
<form id="dl-quote" class="ca-quote" data-sl-form data-sl-redirect method="post" action="ajax/sl/delivery_ajax.php">
<input type="hidden" name="op" value="quote"><input type="hidden" name="place_id" id="dl-place"><input type="hidden" name="mode" value="<?= h($c['default_mode']) ?>">
<div class="mx-sec"><h2>1 · Which parcels</h2></div>
<?php if (count($modules) > 1): ?>
<label class="mx-f"><small>Shipment type</small><select name="module" id="dl-module" class="mx-in"><?php foreach ($modules as $m): ?><option value="<?= h($m) ?>"><?= $m === 'air' ? 'Air shipments' : 'Sea packages' ?></option><?php endforeach; ?></select></label>
<?php else: ?><input type="hidden" name="module" id="dl-module" value="<?= h($modules[0]) ?>"><?php endif; ?>
<?php foreach ($d['parcels'] as $module => $parcels): ?>
<section class="mx-card cd-pks" data-dl-module="<?= h($module) ?>">
<?php foreach ($parcels as $p): $trk = $p->order_prefix . $p->order_no; ?>
<label class="cd-pk"><input type="checkbox" name="parcels[]" value="<?= (int) $p->order_id ?>" aria-label="<?= h($trk) ?>"><span class="cd-cb" aria-hidden="true"><i class="ic ic-sm i-check" aria-hidden="true"></i></span><span class="mx-li"><i class="ic i-<?= $module === 'air' ? 'plane' : 'box' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= $module === 'air' ? 'Air shipment' : 'Sea package' ?></b><small class="mono"><?= h($trk) ?></small><small><?= h(str_replace('_', ' ', (string) $p->status_name)) ?></small></span></label>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
<p class="mx-note">One delivery carries parcels of one shipment type from one branch.</p>
<div class="mx-sec"><h2>2 · Drop-off point</h2></div>
<div id="dl-place-search"></div>
<section class="dp-map"><div id="dl-map" class="dl-map" role="region" aria-label="Delivery location map"></div><div class="dp-hint"><i class="ic ic-sm i-pin" aria-hidden="true"></i><span>Search above or tap the map</span></div><button type="button" class="mx-ib dp-loc" id="dl-locate" aria-label="Use my current location"><i class="ic ic-sm i-pin" aria-hidden="true"></i></button></section>
<div id="dl-map-error" class="mx-ban warn" role="alert" hidden>Google Maps could not load. Please retry or contact support.</div>
<section class="mx-card dp-addr"><span class="mx-li"><i class="ic i-pin" aria-hidden="true"></i></span><span class="mx-rm"><b id="dl-location" role="status">Choose a place to get a price</b><small>Riders see this exact spot</small></span></section>
<section class="mx-card mx-form">
<label class="mx-f"><small>Contact name</small><input class="mx-in" name="contact_name" maxlength="150" required autocomplete="name" value="<?= h(trim(($userData->fname ?? '') . ' ' . ($userData->lname ?? ''))) ?>"></label>
<label class="mx-f"><small>Phone for the rider</small><input class="mx-in" type="tel" inputmode="tel" name="contact_phone" required maxlength="30" autocomplete="tel" value="<?= h($userData->phone ?? '') ?>"></label>
<label class="mx-f"><small>Landmark or gate note for the rider</small><textarea class="mx-in" name="instructions" maxlength="500" rows="3" placeholder="E.g. blue gate opposite the pharmacy"></textarea></label>
</section>
<div class="mx-sec"><h2>3 · Price</h2><a href="<?= h(sl_route('DeliveryPricing')) ?>">How pricing works</a></div>
<button type="submit" class="mx-btn" id="dl-quote-button" disabled>Get delivery price</button>
<p class="mx-note">We work out the price from the road distance to your pin (<?= h($modeText) ?>, in <?= h($c['currency']) ?>). You see it and approve it before a rider is sent. See <a class="mx-lnk" href="<?= h(sl_route('DeliveryPricing')) ?>">how delivery pricing works</a>.</p>
</form>
<div id="dl-map-config" data-browser-key="<?= h(getenv('CDP_GOOGLE_MAPS_BROWSER_KEY') ?: $c['browser_key']) ?>" data-country="<?= h($c['country']) ?>" data-origin="<?= h($c['origin_place_id']) ?>"></div>
<?php endif; ?>

<?php if ($d['requests'] && !($r && $r->state === 'quoted')): ?>
<div class="mx-sec"><h2>My deliveries</h2><a href="<?= h(sl_route('DeliveryDone')) ?>">All</a></div>
<section class="mx-card mx-list">
<?php foreach (array_slice($d['requests'], 0, 5) as $q): ?>
<a class="mx-row" href="<?= h(sl_route('DeliveryDone', ['id' => (int) $q->id])) ?>"><span class="mx-li"><i class="ic i-truck" aria-hidden="true"></i></span><span class="mx-rm"><b>Delivery #<?= (int) $q->id ?></b><small><?= h(dl_money((int) $q->amount_cents, $q->currency)) ?> · <?= h($q->address) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($statePill[$q->state] ?? 'plain') ?>"><?= h($stateLabel[$q->state] ?? ucfirst($q->state)) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
</main>
