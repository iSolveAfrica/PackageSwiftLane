<?php
/**
 * M-LastMile: last-mile KPIs, open parcels by zone, riders today. Same $d as the web page
 * (views/sl/data/lastmile.php sl_lastMileData). Read-only here; zone edits stay on the web page.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$k = $d['kpis'];
$sp = function (array $bars): string {
    if (!$bars) return '';
    $out = '<div class="mx-sp" aria-hidden="true">';
    foreach ($bars as $i => $b) $out .= '<i class="' . ($i === count($bars) - 1 ? 'now' : '') . '" style="' . h($b['s']) . '"></i>';
    return $out . '</div>';
};
$tiles = [[$k[1], ''], [$k[2], 'warn'], [$k[0], ''], [$k[3], '']];
$zoneMax = max(1, ...array_map(fn($z) => (int) $z['open'], $d['zones'] ?: [['open' => 0]]));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-centres.css')) ?>">
<?= sl_phoneTop('Last-mile delivery', '', ['back' => 'menu.php'], sl_phoneIcon('route', 'Delivery tracking', sl_route('DeliveryTracking'))) ?>
<main class="mx-body">
<section class="mx-k c2">
<?php foreach ($tiles as [$t, $cls]): ?>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm <?= h($t['icon']) ?>" aria-hidden="true"></i></span><small><?= h($t['l']) ?></small></div><b><?= h($t['v']) ?></b><em class="<?= h($cls) ?>"><?= h($t['s']) ?></em><?= $sp($t['sp']) ?></div>
<?php endforeach; ?>
</section>
<?= sl_mxSec('Open parcels by zone') ?>
<?php if ($d['zones']): ?>
<section class="mx-card mx-mts">
<?php foreach ($d['zones'] as $z): ?>
  <div class="mx-mt"><div><span><?= h($z['name']) ?><?= $z['riderName'] !== '' ? ' · ' . h($z['riderName']) : '' ?></span><b><?= (int) $z['open'] ?></b></div><span class="mx-trk"><i class="<?= $z['rider'] ? 'ok' : 'warn' ?>" style="width:<?= (int) round($z['open'] / $zoneMax * 100) ?>%"></i></span></div>
<?php endforeach; ?>
<?php if ($d['unzoned']): ?>
  <div class="mx-mt"><div><span>Without a zone</span><b><?= (int) $d['unzoned'] ?></b></div><span class="mx-trk"><i class="bad" style="width:<?= (int) min(100, round($d['unzoned'] / $zoneMax * 100)) ?>%"></i></span></div>
<?php endif; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('pin', 'No delivery zones yet', 'Set zones up on the website to route parcels by city.') ?>
<?php endif; ?>
<?php if ($d['riderList']): ?>
<?= sl_mxSec('Riders today', 'Dispatch', sl_route('RiderDispatch')) ?>
<section class="mx-card mx-list">
<?php foreach (array_slice($d['riderList'], 0, 8) as $r): ?>
<a class="mx-row" href="<?= h($r['detail']) ?>"><span class="mx-ava"><?= h(sl_initials($r['name'])) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= (int) $r['open'] ?> open · <?= (int) $r['done'] ?> delivered<?= $r['fail'] ? ' · ' . (int) $r['fail'] . ' failed' : '' ?></small></span><?php if ($r['codN']): ?><span class="mx-rr"><b><?= h(sl_money($r['cod'])) ?></b><small>COD</small></span><?php endif; ?></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($d['fails']): ?>
<?= sl_mxSec('Failed attempts · 7 days') ?>
<section class="mx-card mx-list pc-wrap">
<?php foreach (array_slice($d['fails'], 0, 5) as $f): ?>
<a class="mx-row" href="<?= h($f['href']) ?>"><span class="mx-li"><i class="ic i-alert" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($f['t']) ?></b><small><?= h($f['s']) ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
<section class="mx-card mx-list">
  <a class="mx-row" href="<?= h(sl_route('RiderSettings')) ?>"><span class="mx-li"><i class="ic i-sliders" aria-hidden="true"></i></span><span class="mx-rm"><b>Rider fees</b><small>Fee per delivery and the rider's share</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php if ($d['canManage']): ?>
  <a class="mx-row" href="delivery_settings.php"><span class="mx-li"><i class="ic i-pin" aria-hidden="true"></i></span><span class="mx-rm"><b>Delivery settings</b><small>Pricing, zones and Maps</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php endif; ?>
</section>
</main>
