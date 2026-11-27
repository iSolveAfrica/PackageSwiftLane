<?php
/**
 * M-FrHub: the freight hub on a phone. Same $d as the web page (views/sl/data/freight.php sl_freightHubData).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$k = [];
foreach ((array) ($d['kpis'] ?? []) as $x) $k[$x['l']] = $x;
$tile = function (string $icon, string $label, string $key) use ($k) {
    return isset($k[$key]) ? sl_mxKpi($icon, $label, $k[$key]['v'], $k[$key]['s']) : '';
};
$acts = [
    ['calendar', 'Schedule', sl_route('FreightSchedule')], ['file', 'Waybills', sl_route('Waybills')], ['alert', 'Exceptions', sl_route('CargoExceptions')], ['shield', 'Claims', sl_route('InsuranceClaims')],
    ['users', 'Partners', sl_route('FreightPartners')], ['layers', 'Consolidate', sl_route('Consolidations')], ['route', 'Live map', sl_route('LiveTracking')], ['ship', 'Sea (optional)', sl_route('SeaPackages')],
];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-desk.css')) ?>">
<?= sl_phoneTop('Freight hub', '', ['back' => 'menu.php'], sl_phoneIcon('calendar', 'Departures and arrivals', sl_route('FreightSchedule'))) ?>
<main class="mx-body">
<?php if (empty($d['ready'])): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The freight tables are missing. Ask an administrator to run the freight migration.</span></div>
<?php else: ?>
<section class="mx-k c2">
<?= $tile('plane', 'Flights this week', 'Departing this week') ?>
<?= $tile('box', 'In the air', 'In the air') ?>
<?= $tile('alert', 'Open exceptions', 'Open exceptions') ?>
<?= $tile('shield', 'Claims', 'Open claims') ?>
</section>
<?php endif; ?>
<section class="mx-act c4"><?php foreach ($acts as $a): ?><a href="<?= h($a[2]) ?>"><span><i class="ic i-<?= h($a[0]) ?>" aria-hidden="true"></i></span><small><?= h($a[1]) ?></small></a><?php endforeach; ?></section>
<?php if (!empty($d['ready'])): ?>
<?= sl_mxSec('Next departures', 'All', sl_route('FreightSchedule')) ?>
<?php if ($d['deps']): ?>
<section class="mx-card mx-list"><?php foreach ($d['deps'] as $dp): ?><a class="mx-row" href="<?= h(sl_route('FreightSchedule', ['id' => $dp['id']])) ?>"><span class="mx-li"><i class="ic <?= h($dp['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($dp['t']) ?></b><small><?= h($dp['s']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= $dp['hi'] ? 'pending' : 'delivered' ?>"><?= h($dp['fill']) ?></span></span></a><?php endforeach; ?></section>
<?php else: ?><?= sl_mxEmpty('plane', 'Nothing departing soon', 'Departures you schedule show up here.') ?><?php endif; ?>
<?php if ($d['acts']): ?>
<?= sl_mxSec('Needs attention') ?>
<section class="mx-card mx-list"><?php foreach ($d['acts'] as $a): ?><a class="mx-row" href="<?= h($a['href']) ?>"><span class="mx-li"><i class="ic i-alert" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($a['t']) ?></b><small><?= h($a['s']) ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a><?php endforeach; ?></section>
<?php endif; ?>
<?php endif; ?>
</main>
