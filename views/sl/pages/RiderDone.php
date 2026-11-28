<?php
/**
 * Trip summary of a finished stop (design: M-RDone). Data: ro_data() + $d['stop'] (today's or an earlier finished
 * stop of this rider) + $d['minutes'] (start to finish from the recorded events, when both exist).
 * Left out: tips, boosts, distance pay lines, customer rating and the auto-opening next offer (no data or backend).
 */
if (!isset($d)) { $d = []; }
$ph = !empty($roPhone);
if (empty($d['isRider'])) { ro_notRider($ph, 'Trip complete'); return; }
$s = $d['stop'] ?? null;
$slPhone['tab'] = 'Jobs';
if (!$s) {
    echo ro_open($ph, 'Trip complete', '', ['back' => sl_route('RiderQueue')]);
    echo sl_mxEmpty('check', 'No finished trip here', 'This stop is not one of your finished jobs.', 'Back to my jobs', sl_route('RiderQueue'));
    echo ro_close($ph);
    return;
}
$next = $d['next'];
$kpis = '';
if (($d['minutes'] ?? null) !== null) $kpis .= sl_mxKpi('clock', 'Time', $d['minutes'] . ' min', 'start to handover');
if ($s['km'] !== null) $kpis .= sl_mxKpi('route', 'Km', $s['km'] . ' km', 'by road');
$kpis .= sl_mxKpi('trendup', 'Today', $d['earnedTxt'], (int) $d['trips'] . ' trip' . ($d['trips'] === 1 ? '' : 's'), $d['trips'] ? 'up' : '');
$cols = substr_count($kpis, 'class="mx-kt"');
echo ro_open($ph, 'Trip complete', $s['ref'] . ($s['at'] !== '' ? ' · ' . sl_date($s['at'], date('Y-m-d', strtotime($s['at'])) === date('Y-m-d') ? 'H:i' : 'M j, H:i') : ''), ['back' => sl_route('RiderQueue')]);
?>
<section class="mx-k <?= $cols >= 3 ? 'c3' : ($cols === 2 ? 'c2' : '') ?>"><?= $kpis ?></section>
<div class="mxp-grid">
<div class="mxp-col">
<section class="rb-earn"><small>You earned</small><b class="rb-big"><?= h($s['payTxt']) ?></b><span class="rb-sub">Added to your earnings · payouts are in Wallet</span>
<?php if ($s['fee'] > 0): ?><span class="rb-stack" aria-hidden="true"><i style="width:<?= (int) $s['pct'] ?>%"></i><i style="width:<?= 100 - (int) $s['pct'] ?>%"></i></span><?php endif; ?>
<div class="rb-brk"><div><span><?= $s['pickup'] ? 'Pickup fee' : 'Delivery fee' ?></span><b><?= h($s['feeTxt']) ?></b></div><div><span>Swift Lane’s share</span><b>−<?= h($s['swiftTxt'] ?? '')?></b></div><div class="rb-tot"><span>Your share · <?= (int) $s['pct'] ?>%</span><b><?= h($s['payTxt']) ?></b></div></div></section>
<?php if ($next): ?><a class="mx-card rb-next" href="<?= h($next['href']) ?>"><span class="mx-li"><i class="ic i-route" aria-hidden="true"></i></span><span class="mx-rm"><b>Next: <?= h($next['name']) ?> · <?= h($next['payTxt']) ?></b><small><?= h($next['kindTxt'] . ' · ' . ($next['addr'] ?: $next['ref'])) ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a><?php endif; ?>
</div>
<div class="mxp-col">
<div class="mx-sec"><h2>This stop</h2></div>
<section class="mx-card mx-list">
<div class="mx-row"><span class="mx-li"><i class="ic i-check" aria-hidden="true"></i></span><span class="mx-rm"><b><?= $s['pickup'] ? 'Collected from ' : 'Delivered to ' ?><?= h($s['receiver'] !== '' ? $s['receiver'] : $s['name']) ?></b><small><?= h(trim(($s['atTxt'] ?? '') . ($s['proof'] !== '' ? ' · ' . $s['proof'] : ''), ' ·')) ?></small></span><span class="mx-rr"><span class="sl-pill delivered">Done</span></span></div>
<div class="mx-row"><span class="mx-li"><i class="ic i-pin" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($s['name']) ?></b><small><?= h($s['addr'] ?: 'Address not supplied') ?></small></span><span class="mx-rr"><b><?= h($s['nTxt']) ?></b></span></div>
<?php if ($s['collect']): ?><div class="mx-row"><span class="mx-li"><i class="ic i-dollar" aria-hidden="true"></i></span><span class="mx-rm"><b>Delivery fee <?= h($s['feeTxt']) ?></b><small><?= $s['collected'] ? 'Recorded as collected · hand in at the office' : 'Not recorded as collected' ?></small></span><span class="mx-rr"><span class="sl-pill <?= $s['collected'] ? 'delivered' : 'pending' ?>"><?= $s['collected'] ? 'Collected' : 'Due' ?></span></span></div><?php endif; ?>
<?php if ($s['src'] === 'request'): ?><a class="mx-row" href="<?= h(sl_route('DeliveryJob', ['id' => (int) $s['id']])) ?>"><span class="mx-li"><i class="ic i-receipt" aria-hidden="true"></i></span><span class="mx-rm"><b>Delivery record</b><small>Parcels, proof photo and history</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a><?php endif; ?>
</section>
<div class="mx-btns"><a class="mx-btn" href="<?= h($next ? sl_route('RiderQueue') : sl_route('RiderHome')) ?>"><?= $next ? 'Back to my jobs' : 'Back to home' ?></a><a class="mx-btn sec" href="<?= h(sl_route('RiderEarnings')) ?>">See earnings</a></div>
</div>
</div>
<?= ro_close($ph) ?>
