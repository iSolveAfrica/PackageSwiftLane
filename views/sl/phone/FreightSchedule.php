<?php
/**
 * M-FrSched: departures and arrivals on a phone. Same $d as the web page (sl_freightScheduleData); the segments
 * are the web page's ?mode=air|sea, a row opens ?id= (the departure's details, as on the web page).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$sel = (int) ($_GET['id'] ?? 0);
$cur = $sel ? ($d['d'] ?? null) : null;
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-desk.css')) ?>">
<?= sl_phoneTop($cur ? $cur['n'] : 'Departures & arrivals', $cur ? $cur['route'] : '', ['back' => $cur ? sl_url(['id' => null]) : sl_route('FreightHub')]) ?>
<main class="mx-body">
<?php if (empty($d['ready'])): ?>
<?= sl_mxEmpty('calendar', 'Schedule not installed', 'Ask an administrator to run the freight migration.') ?>
<?php elseif ($cur): ?>
<section class="mx-hero"><div class="mx-hk"><?= h($cur['route']) ?><span class="mx-tag"><?= h($cur['st']) ?></span></div><b class="mx-hv"><?= h($cur['eta']) ?></b><small>Predicted arrival · carrier said <?= h($cur['eta0']) ?></small></section>
<section class="mx-card mx-kv"><div><span>Departs</span><b><?= h($cur['dep']) ?></b></div><div><span>Cut-off</span><b><?= h($cur['cut']) ?></b></div><div><span>Space booked</span><b><?= h($cur['fill']) ?></b></div><div><span>Consolidations</span><b><?= h($cur['cons']) ?></b></div></section>
<div class="mx-ban info"><i class="ic ic-sm i-ai" aria-hidden="true"></i><span><?= h($cur['hist']) ?></span></div>
<?php if ($cur['notes'] !== ''): ?><section class="mx-card mx-form"><p class="pd-note" style="margin:0;white-space:pre-line"><?= h($cur['notes']) ?></p></section><?php endif; ?>
<div class="mx-btns"><a class="mx-btn" href="<?= h($cur['consHref']) ?>">Open consolidations</a><a class="mx-btn sec" href="<?= h(sl_route('Waybills', ['schedule' => $cur['id']])) ?>">Waybills for this departure</a></div>
<?php else: ?>
<nav class="mx-seg" aria-label="Mode"><?php foreach ($d['modes'] as $m): ?><a class="<?= h($m['cls']) ?>" href="<?= h($m['href']) ?>"<?= $m['cls'] === 'on' ? ' aria-current="page"' : '' ?>><?= h($m['label']) ?><?= $m['cls'] === 'on' ? ' ' . (int) $d['count'] : '' ?></a><?php endforeach; ?></nav>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list"><?php foreach ($d['rows'] as $r): ?><a class="mx-row" href="<?= h($r['href']) ?>"><span class="mx-rm"><b><?= h($r['n'] . ' · ' . $r['route']) ?></b><small>Departs <?= h($r['dep']) ?> · ETA <?= h($r['eta']) ?></small><small><?= h($r['fill']) ?> · <?= h($r['conf']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($r['pc']) ?>"><?= h($r['st']) ?></span></span></a><?php endforeach; ?></section>
<?php else: ?><?= sl_mxEmpty('calendar', 'No departures', 'Flights and sailings you schedule show up here.') ?><?php endif; ?>
<div class="mx-ban info"><i class="ic ic-sm i-ai" aria-hidden="true"></i><span>Arrival times are predicted from the carrier's ETA and how late this route has been on its last trips.</span></div>
<?php endif; ?>
</main>
