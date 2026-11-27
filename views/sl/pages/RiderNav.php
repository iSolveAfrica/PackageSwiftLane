<?php
/**
 * Navigate to one stop (design: M-RNavPickup). Data: ro_data() + $d['stop'].
 * Turn-by-turn, speed, ETA and voice guidance belong to the rider's maps app, opened with one tap; the screen
 * shows the address, the contact and what to do on arrival.
 */
if (!isset($d)) { $d = []; }
$ph = !empty($roPhone);
if (empty($d['isRider'])) { ro_notRider($ph, 'Navigate'); return; }
$s = $d['stop'] ?? null;
$slPhone['tab'] = 'Jobs';
if (!$s) {
    echo ro_open($ph, 'Navigate', '', ['back' => sl_route('RiderQueue')]);
    echo sl_mxEmpty('pin', 'This stop is not on your list', 'It was finished or dispatch moved it to someone else.', 'Back to my jobs', sl_route('RiderQueue'));
    echo ro_close($ph);
    return;
}
$chat = '<a class="' . ($ph ? 'rs-rb' : 'mx-ib') . '" href="' . h(sl_route('RiderChat')) . '" aria-label="Chat with dispatch"><i class="ic i-message" aria-hidden="true"></i></a>';
$embed = ro_embed($s['addr']);
$sub = $s['kindTxt'] . ($s['km'] !== null ? ' · ' . $s['km'] . ' km by road' : '');
$turn = '<section class="rb-turn"><div class="rb-tmain"><span class="rb-tic"><i class="ic i-pin" aria-hidden="true"></i></span><div class="rb-ttx"><span>' . h($s['addr'] ?: 'Address not supplied') . '</span></div></div>'
    . ($s['note'] !== '' ? '<div class="rb-tnext"><small>Note</small><span>' . h($s['note']) . '</span></div>' : '') . '</section>';
if ($ph) {
    $slPhone['cls'] = 'rs-r rs-nohd';
    $slPhone['nav'] = 'none';
    $slPhone['scripts'][] = 'dataJs/sl/rider_map.js';
    echo ro_mapTop($embed, $s['nav'], 'To ' . $s['name'], $sub, sl_route('RiderQueue'), $chat, '', true);
    echo $turn;
} else {
    echo ro_open(false, 'To ' . $s['name'], $sub . ' · ' . $s['ref'], ['back' => sl_route('RiderQueue')], $chat);
    echo '<div class="mxp-grid"><div class="mxp-col">' . $turn . ro_map($embed, $s['kindTxt'], $s['nav'], 'Map of the stop address') . '</div><div class="mxp-col">';
}
?>
<section class="rb-tools rs-tools">
<?php if ($s['nav'] !== ''): ?><a class="rb-tool" href="<?= h($s['nav']) ?>" target="_blank" rel="noopener"><i class="ic i-ext" aria-hidden="true"></i><span>Directions</span></a><?php endif; ?>
<?php if ($s['tel'] !== ''): ?><a class="rb-tool" href="<?= h($s['tel']) ?>"><i class="ic i-phone" aria-hidden="true"></i><span>Call</span></a><?php endif; ?>
<a class="rb-tool" href="<?= h(sl_route('RiderMap')) ?>"><i class="ic i-layers" aria-hidden="true"></i><span>Overview</span></a>
<a class="rb-tool bad" href="<?= h(sl_route('RiderSafety')) ?>"><i class="ic i-shield" aria-hidden="true"></i><span>Safety</span></a>
</section>
<div class="mx-sec"><h2><?= $s['pickup'] ? 'Pickup' : 'Drop-off' ?></h2></div>
<section class="mx-card mx-list">
<div class="mx-row"><span class="mx-ava"><?= h($s['ini']) ?></span><span class="mx-rm"><b><?= h($s['name']) ?></b><small><?= h($s['phone'] !== '' ? $s['phone'] : 'No phone number on the job') ?></small></span><span class="mx-rr"><b><?= h($s['nTxt']) ?></b><small><?= h($s['st']) ?></small></span></div>
<div class="mx-row"><span class="mx-li"><i class="ic i-box" aria-hidden="true"></i></span><span class="mx-rm"><b class="mono"><?= h(implode(', ', array_map(fn($p) => $p['trk'], $s['parcels'])) ?: $s['ref']) ?></b><small><?= (int) $s['scannedN'] ?> of <?= count($s['parcels']) ?> ticked on your checklist</small></span></div>
<div class="mx-row"><span class="mx-li"><i class="ic i-dollar" aria-hidden="true"></i></span><span class="mx-rm"><b>You get <?= h($s['payTxt']) ?></b><small><?= $s['collect'] ? 'Collect the ' . h($s['feeTxt']) . ' delivery fee at the door' : (int) $s['pct'] . '% of the ' . h($s['feeTxt']) . ' fee' ?></small></span></div>
</section>
<div class="mx-btns">
<?php if ($s['state'] === 'new'): ?><a class="mx-btn" href="<?= h($s['offer']) ?>"><i class="ic ic-sm i-truck" aria-hidden="true"></i>See the job and start</a>
<?php elseif ($s['pickup']): ?><a class="mx-btn" href="<?= h(sl_route('RiderScan', ['id' => $s['key']])) ?>"><i class="ic ic-sm i-check" aria-hidden="true"></i>Arrived · scan the parcel</a><a class="mx-btn sec" href="<?= h($s['open']) ?>">Record the collection</a>
<?php else: ?><a class="mx-btn" href="<?= h($s['open']) ?>"><i class="ic ic-sm i-check" aria-hidden="true"></i>Arrived · hand over</a><?php endif; ?>
</div>
<div class="rs-pair"><?php if ($s['wa'] !== ''): ?><a class="mx-btn rs-amb" href="<?= h($s['wa']) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp</a><?php elseif ($s['tel'] !== ''): ?><a class="mx-btn rs-amb" href="<?= h($s['tel']) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Call <?= h(explode(' ', $s['name'])[0]) ?></a><?php else: ?><a class="mx-btn rs-amb" href="<?= h(sl_route('RiderMap')) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Route</a><?php endif; ?><a class="mx-btn sec rs-out" href="<?= h(sl_route('RiderChat')) ?>"><i class="ic ic-sm i-flag" aria-hidden="true"></i>Report a problem</a></div>
<p class="mx-note">Calls use the number on the job; numbers are not masked.</p>
<?php if ($ph): ?></section></main><?php else: ?></div></div><?= ro_close(false) ?><?php endif; ?>
