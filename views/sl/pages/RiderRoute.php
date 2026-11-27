<?php
/**
 * Route (design: M-RMap). Data: ro_data() + $d['route'] (sl_riderRouteMap: Google Maps preview of the next leg,
 * loaded only when the rider asks, dataJs/sl/rider_map.js).
 * Left out: total time, optimise / reorder, route options; the system stores no order, durations or traffic.
 */
if (!isset($d)) { $d = []; }
$ph = !empty($roPhone);
if (empty($d['isRider'])) { ro_notRider($ph, 'Route'); return; }
$slPhone['tab'] = 'Jobs';
$slPhone['scripts'][] = 'dataJs/sl/rider_map.js';
$open = $d['open'];
$next = $d['next'];
$km = 0.0; $kmN = 0;
foreach ($open as $s) if ($s['km'] !== null) { $km += $s['km']; $kmN++; }
$run = ro_runLink($open);
$embed = $d['route']['embed'] ?? '';
echo ro_open($ph, 'Route', count($open) . ' stop' . (count($open) === 1 ? '' : 's') . ' to go', ['back' => sl_route('RiderQueue')], sl_mxIcon('message', 'Chat', sl_route('RiderChat')));
?>
<section class="mx-k c3"><?= sl_mxKpi('pin', 'Stops', (string) (count($open) + count($d['done'])), count($d['done']) . ' done') ?><?= sl_mxKpi('box', 'Parcels', (string) $d['parcelsOpen'], 'on open stops') ?><?= $kmN ? sl_mxKpi('route', 'Road km', (string) round($km, 1), $kmN . ' door deliver' . ($kmN === 1 ? 'y' : 'ies')) : sl_mxKpi('route', 'Road km', '—', 'not recorded') ?></section>
<?php if (!$open && !$d['done']): ?>
<?= sl_mxEmpty('route', 'Your route is clear', 'Stops appear here when dispatch assigns your next job.', 'Back to jobs', sl_route('RiderQueue')) ?>
<?php else: ?>
<div class="mxp-grid">
<div class="mxp-col">
<?php if ($embed !== ''): ?><?= ro_map($embed, !empty($d['route']['leg']) ? 'Next leg' : 'Next stop', $next['nav'] ?? '', 'Map of the next assigned stops') ?>
<p class="mx-note">The map places stops from the saved address. Confirm the entrance with the customer.</p>
<?php elseif ($open): ?><?= sl_mxEmpty('pin', 'No address to show', 'Ask dispatch to add the stop address. You can still open the job.') ?><?php endif; ?>
<div class="mx-btns"><?php if ($run !== ''): ?><a class="mx-btn" href="<?= h($run) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-ext" aria-hidden="true"></i>Open the whole run in Google Maps</a><?php elseif ($next && $next['nav'] !== ''): ?><a class="mx-btn" href="<?= h($next['nav']) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-route" aria-hidden="true"></i>Start navigation</a><?php endif; ?><a class="mx-btn sec" href="<?= h(sl_route('RiderQueue')) ?>">Back to jobs</a></div>
</div>
<div class="mxp-col">
<div class="mx-sec"><h2>Stops</h2></div>
<section class="mx-card"><ol class="ra-ns ra-ro"><?php $n = 0; foreach (array_merge($d['done'], $open) as $s): $n++; $cls = $s['state'] === 'done' ? 'done' : ($next && $s['key'] === $next['key'] ? 'next' : 'later'); ?>
<li class="<?= $cls ?>"><span class="ra-n"><?= $n ?></span><a class="ro-st" href="<?= h($s['href']) ?>"><b><?= h($s['name']) ?></b><small><?= h(($s['pickup'] ? 'Pickup · ' : '') . ($s['addr'] ?: 'Address not supplied') . ' · ' . $s['nTxt']) ?></small></a><?php if ($s['state'] === 'done'): ?><em><?= h($s['atTxt']) ?></em><span class="ra-lk"><i class="ic ic-sm i-check" aria-hidden="true"></i></span><?php else: ?><span class="ro-stb"><?php if ($s['addr'] !== '' && $embed !== ''): ?><button type="button" class="mx-ib" data-map-address="<?= h($s['addr']) ?>" aria-label="Show stop <?= $n ?> on the map"><i class="ic ic-sm i-pin" aria-hidden="true"></i></button><?php endif; ?><?php if ($s['nav'] !== ''): ?><a class="mx-ib" href="<?= h($s['nav']) ?>" target="_blank" rel="noopener" aria-label="Directions to stop <?= $n ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i></a><?php endif; ?></span><?php endif; ?></li>
<?php endforeach; ?></ol></section>
<div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>Stops are in the order dispatch assigned them. Reordering and route optimisation are not available yet; Google Maps keeps the order shown here.</span></div>
</div>
</div>
<?php endif; ?>
<?= ro_close($ph) ?>
