<?php
/**
 * Rider task list (design: M-RTasks). Data: views/sl/data/rider_ops.php (ro_data).
 * Riders cannot claim open work in this system: dispatch assigns it. The list is what is assigned to the rider and
 * not started; the amount is the rider's share. No "near you", boost, filter chips or live offers: nothing records them.
 */
if (!isset($d)) { $d = []; }
$ph = !empty($roPhone);
if (empty($d['isRider'])) { ro_notRider($ph, 'Task list'); return; }
$slPhone['tab'] = 'Jobs';
$door = array_values(array_filter($d['new'], fn($s) => $s['src'] === 'request'));
$ship = array_values(array_filter($d['new'], fn($s) => $s['src'] === 'order'));
$card = function (array $s): string {
    $tags = '<span>' . h($s['nTxt']) . '</span>';
    if ($s['km'] !== null) $tags .= '<span>' . h($s['km']) . ' km</span>';
    if ($s['kg'] !== null) $tags .= '<span>' . h($s['kg']) . ' kg</span>';
    if ($s['collect']) $tags = '<span class="ra-ttag">Collect ' . h($s['feeTxt']) . '</span>' . $tags;
    $lines = ($s['from'] !== '' ? ro_stLine('p', 'Collect the parcels', $s['from']) : '') . ro_stLine('d', $s['addr'] ?: 'Address not supplied', $s['name'] . ' · ' . $s['nTxt']);
    return '<a class="ra-tc" href="' . h($s['offer']) . '"><div class="ra-tch">' . ($s['src'] === 'request' ? '<span class="ra-th cust">' . h($s['ini']) . '</span>' : '<span class="ra-th hub"><i class="ic i-box" aria-hidden="true"></i></span>')
        . '<span class="mx-rm"><b>' . h($s['src'] === 'request' ? $s['name'] : $s['ref']) . '</b><small>' . h($s['src'] === 'request' ? $s['ref'] . ($s['since'] !== '' ? ' · assigned ' . sl_ago($s['since']) : '') : $s['kindTxt'] . ' · ' . $s['name']) . '</small></span><b class="ra-tp">' . h($s['payTxt']) . '</b></div>'
        . '<ol class="ra-st" aria-label="Stops">' . $lines . '</ol><div class="ra-tm">' . $tags . '</div></a>';
};
echo ro_open($ph, 'Jobs', $d['sub'], ['back' => sl_route('RiderHome')], sl_mxIcon('route', 'Route map', sl_route('RiderMap')));
?>
<div class="ra-tt"><h2 class="mx-big">Task list</h2></div>
<?php if (!$d['duty']['online']): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>You are offline. Dispatch will not send you new door deliveries until you go online on Home.</span></div><?php endif; ?>
<?php if (!$d['new']): ?>
<?= sl_mxEmpty('inbox', 'No jobs waiting to start', 'Dispatch assigns jobs to you. They stay here until you start them.', count($d['active']) ? 'Open my run' : '', sl_route('RiderQueue')) ?>
<?php else: ?>
<nav class="ra-sw2" aria-label="Task type"><a class="on" href="#ra-cust">Door deliveries <small><?= count($door) ?></small></a><a href="#ra-hub">Shipments <small><?= count($ship) ?></small></a></nav>
<div class="mx-sec" id="ra-cust"><h2>Door deliveries · <?= count($door) ?></h2></div>
<?php if ($door): ?><div class="mxp-grid"><?php foreach ($door as $s) echo $card($s); ?></div><?php else: ?><p class="mx-note">No customer door deliveries are waiting.</p><?php endif; ?>
<div class="mx-sec" id="ra-hub"><h2>Shipments to deliver · <?= count($ship) ?></h2><a href="<?= h(sl_route('RiderMap')) ?>">Map view</a></div>
<?php if ($ship): ?><div class="mxp-grid"><?php foreach ($ship as $s) echo $card($s); ?></div><?php else: ?><p class="mx-note">No assigned shipments are waiting.</p><?php endif; ?>
<?php endif; ?>
<?php if ($d['active']): ?><a class="mx-card ra-en" href="<?= h(sl_route('RiderQueue')) ?>"><span class="mx-li"><i class="ic i-box" aria-hidden="true"></i></span><span class="mx-rm"><b>My run</b><small><?= count($d['active']) ?> started or to collect</small></span><span class="mx-cnt"><?= count($d['active']) ?></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a><?php endif; ?>
<p class="mx-note">Amounts are your share of the delivery fee. Dispatch assigns jobs; to hand one back, message dispatch. Tap a job to see it and start.</p>
<?= ro_close($ph) ?>
