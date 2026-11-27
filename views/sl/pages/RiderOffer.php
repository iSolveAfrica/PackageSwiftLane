<?php
/**
 * One assigned job before it starts (design: M-ROffer). Data: ro_data() + $d['stop'].
 * Accept is the existing start action (ajax/sl/delivery_ajax.php op=start, ajax/sl/rider_ajax.php op=onroute).
 * No countdown, boost, time estimate or customer rating: the system has none. A rider cannot decline; dispatch reassigns.
 */
if (!isset($d)) { $d = []; }
$ph = !empty($roPhone);
if (empty($d['isRider'])) { ro_notRider($ph, 'Job details'); return; }
$s = $d['stop'] ?? null;
$slPhone['tab'] = 'Jobs';
if (!$s) {
    echo ro_open($ph, 'Job details', '', ['back' => sl_route('RiderTasks')]);
    echo sl_mxEmpty('box', 'This job is not on your list', 'It was finished or dispatch moved it to someone else.', 'Back to the task list', sl_route('RiderTasks'));
    echo ro_close($ph);
    return;
}
$safety = '<a class="' . ($ph ? 'rs-rb' : 'mx-ib') . '" href="' . h(sl_route('RiderSafety')) . '" aria-label="Safety"><i class="ic i-shield" aria-hidden="true"></i></a>';
$embed = ro_embed($s['addr']);
if ($ph) {
    $slPhone['cls'] = 'rs-r rs-nohd';
    $slPhone['nav'] = 'none';
    $slPhone['scripts'][] = 'dataJs/sl/rider_map.js';
    echo ro_mapTop($embed, $s['nav'], 'Assigned job', $s['kindTxt'], sl_route('RiderTasks'), $safety, $s['km'] !== null ? 'Drop-off ' . $s['km'] . ' km by road' : '');
} else {
    echo ro_open(false, 'Assigned job', $s['kindTxt'] . ' · ' . $s['ref'], ['back' => sl_route('RiderTasks')], $safety);
    echo '<div class="mxp-grid"><div class="mxp-col">';
}
?>
<section class="mx-card ra-oft"><div class="ra-ofp"><small>You earn</small><b><?= h($s['payTxt']) ?></b><span class="ro-mut"><?= (int) $s['pct'] ?>% of the <?= h($s['feeTxt']) ?> delivery fee</span><div class="ra-ofk"><?php if ($s['km'] !== null): ?><span><i class="ic ic-sm i-route" aria-hidden="true"></i><b><?= h($s['km']) ?> km</b></span><?php endif; ?><span><i class="ic ic-sm i-pin" aria-hidden="true"></i><b><?= $s['from'] !== '' ? '2 stops' : '1 stop' ?></b></span><span><i class="ic ic-sm i-box" aria-hidden="true"></i><b><?= h($s['nTxt']) ?></b></span></div></div></section>
<div class="rs-oid"><div><b><?= h($s['ref']) ?></b><span><?= h(($s['from'] !== '' ? $s['from'] : 'Hub')) ?> <i class="ic ic-sm i-arrow" aria-hidden="true"></i> <?= h($s['addr'] ?: 'address not supplied') ?></span></div><span class="rs-st"><?= h($s['st']) ?></span></div>
<?php if (!$ph): ?><?= ro_map($embed, 'Drop-off', $s['nav'], 'Map of the drop-off address') ?></div><div class="mxp-col"><?php endif; ?>
<div class="mx-sec"><h2>Route</h2></div>
<section class="mx-card ra-stc"><ol class="ra-st" aria-label="Stops"><?php if ($s['from'] !== ''): ?><?= ro_stLine('p', 'Collect the parcels', $s['from']) ?><?php endif; ?><?= ro_stLine('d', $s['name'], ($s['addr'] ?: 'Address not supplied') . ' · ' . $s['nTxt'], $s['km'] !== null ? $s['km'] . ' km' : '') ?></ol></section>
<section class="ra-fx">
<div><span><i class="ic ic-sm i-box" aria-hidden="true"></i></span><small>Parcels</small><b><?= (int) $s['n'] ?><?= $s['kg'] !== null ? ' · ' . h($s['kg']) . ' kg' : '' ?></b></div>
<?php if ($s['collect']): ?><div class="warn"><span><i class="ic ic-sm i-dollar" aria-hidden="true"></i></span><small>Fee to collect</small><b><?= h($s['feeTxt']) ?></b></div><?php else: ?><div><span><i class="ic ic-sm i-receipt" aria-hidden="true"></i></span><small>Delivery fee</small><b><?= h($s['feeTxt']) ?></b></div><?php endif; ?>
<div><span><i class="ic ic-sm i-key" aria-hidden="true"></i></span><small>Proof</small><b title="<?= h($s['proof']) ?>"><?= h($s['proof']) ?></b></div>
</section>
<section class="mx-card mx-list"><?php foreach ($s['parcels'] as $p): ?><div class="mx-row"><span class="mx-li"><i class="ic i-box" aria-hidden="true"></i></span><span class="mx-rm"><b class="mono"><?= h($p['trk']) ?></b><small><?= $p['scanned'] ? 'Ticked on your pickup checklist' : 'Not scanned yet' ?></small></span><?php if ($p['scanned']): ?><span class="mx-rr"><span class="sl-pill delivered">Scanned</span></span><?php endif; ?></div><?php endforeach; ?></section>
<?php if ($s['note'] !== ''): ?><div class="mx-ban info"><i class="ic ic-sm i-note" aria-hidden="true"></i><span><?= h($s['note']) ?></span></div><?php endif; ?>
<?php if ($s['collect']): ?><div class="mx-ban warn"><i class="ic ic-sm i-dollar" aria-hidden="true"></i><span><?= h($s['name']) ?> pays the <?= h($s['feeTxt']) ?> delivery fee on delivery. Record it when you confirm the handover.</span></div><?php endif; ?>
<p class="mx-note">Start only when the parcels are with you. You cannot decline a job here; message dispatch to hand it back.</p>
<?php if ($ph): ?></section></main><?php else: ?></div></div><?php endif; ?>
<div class="mx-foot"><div class="ra-ofoot rs-bar2"><a class="mx-btn sec rs-out" href="<?= h(sl_route('RiderScan', ['id' => $s['key']])) ?>"><i class="ic ic-sm i-scan" aria-hidden="true"></i>Scan</a>
<?php if ($s['src'] === 'request'): ?><button type="button" class="mx-btn ra-acc" data-sl-action="ajax/sl/delivery_ajax.php" data-p-op="start" data-p-id="<?= (int) $s['id'] ?>" data-sl-confirm="Confirm all <?= (int) $s['n'] ?> parcels are with you and start the delivery?"><span>I have the parcels · Start</span></button>
<?php else: ?><button type="button" class="mx-btn ra-acc" data-sl-action="ajax/sl/rider_ajax.php" data-p-op="onroute" data-p-id="<?= (int) $s['id'] ?>" data-sl-confirm="Confirm the parcel is with you and start the delivery?" data-sl-reload><span>I have the parcel · Start</span></button><?php endif; ?>
</div></div>
<?php if (!$ph) echo ro_close(false); ?>
