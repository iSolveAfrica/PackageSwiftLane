<?php
/**
 * Pickup scan (design: M-RPickup). Data: ro_data(); $d['focus'] = one stop when opened with ?id=.
 * The rider scans (camera, where the browser has BarcodeDetector) or types a tracking number; the server answers
 * whether it is on one of the rider's open jobs and ticks it (ajax/sl/rider_ops_ajax.php, dataJs/sl/rider_scan.js).
 * Scanning is a checklist: starting and collecting use the existing actions. No geofence, waiting time, scale
 * weight or hub release code: the system has none.
 */
if (!isset($d)) { $d = []; }
$ph = !empty($roPhone);
if (empty($d['isRider'])) { ro_notRider($ph, 'Pickup scan'); return; }
$slPhone['tab'] = 'Scan';
$slPhone['scripts'][] = 'dataJs/sl/rider_scan.js';
$focus = $d['focus'] ?? null;
$stops = $focus && in_array($focus['state'], ['active', 'new'], true) ? [$focus] : $d['open'];
$total = 0; $ticked = 0; $kg = 0.0;
foreach ($stops as $s) { $total += count($s['parcels']); $ticked += $s['scannedN']; $kg += (float) ($s['kg'] ?? 0); }
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
$ready = sl_tableExists('cdb_rider_scans');
echo ro_open($ph, 'Pickup scan', $focus ? $focus['name'] . ' · ' . $focus['ref'] : count($stops) . ' open job' . (count($stops) === 1 ? '' : 's'), $focus ? ['back' => $focus['navPage']] : ['avatar' => $d['ini'], 'href' => sl_route('RiderAccount'), 'online' => $d['duty']['online']], sl_mxIcon('message', 'Chat with dispatch', sl_route('RiderChat')));
?>
<?php if (!$ready): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The pickup checklist needs a database upgrade. Ask the office; you can still open each job below.</span></div><?php endif; ?>
<div data-ro-scan="ajax/sl/rider_ops_ajax.php" class="mxp-grid">
<div class="mxp-col">
<section class="mx-k c3"><div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-scan" aria-hidden="true"></i></span><small>Done</small></div><b><span data-ro-ticked><?= $ticked ?></span> / <span data-ro-total><?= $total ?></span></b><em class="<?= $total && $ticked >= $total ? 'up' : 'warn' ?>" data-ro-left><?= $total - $ticked > 0 ? ($total - $ticked) . ' to go' : ($total ? 'all ticked' : 'nothing to scan') ?></em></div><?= sl_mxKpi('box', 'Jobs', (string) count($stops), 'open') ?><?= sl_mxKpi('gauge', 'Weight', $kg > 0 ? round($kg, 1) . ' kg' : '—', $kg > 0 ? 'declared' : 'not recorded') ?></section>
<section class="mx-scan" aria-label="Camera scanner"><div class="mx-vf" data-ro-vf><i></i><i></i><i></i><i></i><em></em><video data-ro-video playsinline muted hidden></video></div><b data-ro-status aria-live="polite"><?= $total ? 'Type the tracking number' : 'No parcels to scan' ?></b><small data-ro-hint>The number is printed under the barcode on the label.</small></section>
<div class="rb-scanbar" data-ro-cambar hidden><button type="button" class="mx-btn sec rb-sm" data-ro-cam><i class="ic ic-sm i-scan" aria-hidden="true"></i><span>Use camera</span></button><button type="button" class="mx-btn sec rb-sm" data-ro-torch hidden aria-pressed="false"><i class="ic ic-sm i-sun" aria-hidden="true"></i>Torch</button><button type="button" class="mx-btn sec rb-sm" data-ro-type><i class="ic ic-sm i-type" aria-hidden="true"></i>Type code</button></div>
<form class="ro-code" data-ro-form><input name="code" aria-label="Tracking number" placeholder="Tracking number" value="<?= h($q) ?>" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="60" enterkeyhint="go"<?= $ready && $total ? '' : ' disabled' ?>><button class="mx-btn" type="submit"<?= $ready && $total ? '' : ' disabled' ?>>Check</button></form>
</div>
<div class="mxp-col">
<div class="mx-sec"><h2>Parcels on your open jobs</h2><a href="<?= h(sl_route('RiderQueue')) ?>"><span data-ro-ticked><?= $ticked ?></span>&nbsp;of <?= $total ?></a></div>
<?php if (!$stops): ?>
<?= sl_mxEmpty('scan', 'Nothing to scan', 'Parcels appear here when dispatch assigns you a pickup or a delivery.', 'Open my jobs', sl_route('RiderQueue')) ?>
<?php endif; ?>
<?php foreach ($stops as $s): $all = $s['scannedN'] >= count($s['parcels']) && $s['parcels']; ?>
<section class="mx-card mx-list" data-ro-job="<?= h($s['key']) ?>">
<?php foreach ($s['parcels'] as $p): ?>
<div class="mx-row rb-ck<?= $p['scanned'] ? ' ok' : '' ?>" data-ro-parcel="<?= h($p['module'] . ':' . $p['id']) ?>"><span class="rb-cki"><i class="ic ic-sm i-<?= $p['scanned'] ? 'check' : 'scan' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($s['name']) ?></b><small><?= h($s['kindTxt'] . ' · ' . ($s['addr'] ?: 'address not supplied')) ?></small><small class="mono"><?= h($p['trk']) ?></small></span><span class="rb-ckl"><?= $p['scanned'] ? 'Scanned' : 'To scan' ?></span><button type="button" class="ro-un" data-ro-un aria-label="Untick <?= h($p['trk']) ?>"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
<?php endforeach; ?>
<div class="ro-act" data-ro-act<?= $all ? '' : ' hidden' ?>>
<?php if ($s['pickup']): ?><a class="mx-btn" href="<?= h($s['open']) ?>"><i class="ic ic-sm i-check" aria-hidden="true"></i>All scanned · record the collection</a>
<?php elseif ($s['state'] === 'new' && $s['src'] === 'request'): ?><button type="button" class="mx-btn" data-sl-action="ajax/sl/delivery_ajax.php" data-p-op="start" data-p-id="<?= (int) $s['id'] ?>" data-sl-confirm="Confirm all <?= (int) $s['n'] ?> parcels are with you and start the delivery?"><i class="ic ic-sm i-truck" aria-hidden="true"></i>All scanned · start delivery</button>
<?php elseif ($s['state'] === 'new'): ?><button type="button" class="mx-btn" data-sl-action="ajax/sl/rider_ajax.php" data-p-op="onroute" data-p-id="<?= (int) $s['id'] ?>" data-sl-reload><i class="ic ic-sm i-truck" aria-hidden="true"></i>Scanned · start delivery</button>
<?php else: ?><a class="mx-btn sec" href="<?= h($s['navPage']) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Scanned · go to the stop</a><?php endif; ?>
</div>
</section>
<?php endforeach; ?>
<?php if ($focus && count($d['open']) > 1): ?><a class="mx-btn sec" href="<?= h(sl_route('RiderScan')) ?>">Show all <?= count($d['open']) ?> open jobs</a><?php endif; ?>
<p class="mx-note">Scanning only ticks your checklist; it does not change a parcel’s status. If a label is not on your list, do not take the parcel. <a href="<?= h(sl_route('RiderChat')) ?>">Parcel missing or damaged? Message dispatch.</a></p>
</div>
</div>
<?= ro_close($ph) ?>
