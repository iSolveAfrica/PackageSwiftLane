<section class="da-map-card" aria-label="Route map">
<div class="da-map-heading"><span><b><?= $d['route']['leg'] ? 'Next route leg' : 'Your next stop' ?></b><small><?= count($d['route']['stops']) ?> mapped stop<?= count($d['route']['stops']) === 1 ? '' : 's' ?> · assigned order</small></span><a class="sl-btn sm ghost" href="<?= h(sl_route('DriverApp', ['tab' => 'route'])) ?>">All stops</a></div>
<?php if ($d['route']['embed']): ?>
<iframe class="da-route-map" id="da-route-map" title="<?= $d['route']['leg'] ? 'Map of the next two assigned stops and connecting route' : 'Map of the next assigned stop' ?>" src="<?= h($d['route']['embed']) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
<p class="da-map-note">Maps estimates locations from the saved address. Confirm the entrance with the customer. <a href="<?= h(sl_riderNavigation($d['route']['stops'][0]['addr'])) ?>" target="_blank" rel="noopener">Open in Maps</a></p>
<div class="da-stop-list" aria-label="Assigned stops">
<?php foreach ($d['route']['stops'] as $i => $stop): ?>
<div class="da-stop"><button type="button" class="da-stop-pin" data-map-address="<?= h($stop['addr']) ?>" aria-label="Show stop <?= $i + 1 ?> on map"><?= $i + 1 ?></button><a href="<?= h(sl_route('DriverApp', ['job' => $stop['id']])) ?>"><b><?= h($stop['cust']) ?></b><small><?= h($stop['addr']) ?></small></a><a class="da-ib" aria-label="Navigate to stop <?= $i + 1 ?>" href="<?= h(sl_riderNavigation($stop['addr'])) ?>" target="_blank" rel="noopener"><i class="ic i-route" aria-hidden="true"></i></a></div>
<?php endforeach; ?>
</div>
<?php else: ?><div class="sl-empty"><i class="ic i-route" aria-hidden="true"></i><b><?= $d['left'] ? 'Address needed to show the route' : 'Your route is clear' ?></b><p><?= $d['left'] ? 'Ask dispatch to add the stop address. You can still open the job below.' : 'New stops will appear when dispatch assigns your next job.' ?></p></div><?php endif; ?>
</section>
