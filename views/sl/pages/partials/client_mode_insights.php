<?php
/**
 * Insights at the top of the customer's own list (SeaPackages / AirShipments): the sea | air switch,
 * headline figures, the last 12 months, where the parcels are now and what they ship most.
 * Expects $mi = sl_clientModeInsights() and $miOther (the other mode's sl_clientModeInsights(), or null).
 * Styles: assets/swiftlane/client-modes.css.
 */
$peakWhere = max(1, max(array_column($mi['where'], 'n') ?: [0]));
$peakItem = max(1, max(array_column($mi['items'], 'q') ?: [0]));
$noun = $mi['sea'] ? 'packages' : 'shipments';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/client-modes.css')) ?>">
<nav class="cm-switch" aria-label="Air or sea">
  <?php foreach (array_filter([$mi['sea'] ? $miOther : $mi, $mi['sea'] ? $mi : $miOther]) as $m): // air, then sea ?>
  <a href="<?= h($m['href']) ?>"<?= $m['mode'] === $mi['mode'] ? ' aria-current="page"' : '' ?>><i class="ic ic-sm <?= h($m['icon']) ?>" aria-hidden="true"></i><?= h($m['title']) ?><?php if ($m['active']): ?><span><?= sl_num($m['active']) ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</nav>
<section class="cm-ins t-<?= h($mi['mode']) ?>" aria-label="Insights on my <?= h($noun) ?>">
  <div class="cm-kpis">
    <div><small>On the way now</small><b><?= sl_num($mi['active']) ?></b><span><?= sl_num($mi['where']['ready']['n']) ?> ready for pickup</span></div>
    <div><small>This year</small><b><?= sl_num($mi['yearN']) ?></b><span><?= h(sl_weight($mi['yearW'])) ?></span></div>
    <div><small>Delivered</small><b><?= sl_num($mi['delivered']) ?></b><span>of <?= sl_num($mi['total']) ?> in total</span></div>
    <div><small>Average weight</small><b><?= h(sl_weight($mi['avgW'])) ?></b><span>per <?= $mi['sea'] ? 'package' : 'shipment' ?>, 12 months</span></div>
  </div>
  <div class="cm-grid">
    <article class="cm-panel cm-months">
      <div class="row between"><b>My <?= h($noun) ?> per month</b><span class="mut sm"><?= sl_num($mi['n12']) ?> in 12 months<?= $mi['busy'] !== '' ? ' · busiest ' . h($mi['busy']) : '' ?></span></div>
      <div class="cm-bars lg" role="img" aria-label="My <?= h($noun) ?> per month, last 12 months"><?php $nb = count($mi['bars']); foreach ($mi['bars'] as $i => $b): ?><i class="<?= $i === $nb - 1 ? 'now' : '' ?>" style="<?= h($b['s']) ?>" title="<?= h($b['t']) ?>"></i><?php endforeach; ?></div>
      <div class="cm-axis"><?php foreach ($mi['axis'] as $a): ?><span><?= h($a) ?></span><?php endforeach; ?></div>
    </article>
    <article class="cm-panel">
      <div class="row between"><b>Where they are now</b><span class="mut sm"><?= sl_num($mi['active']) ?> not delivered</span></div>
      <ul class="cm-where">
        <?php foreach ($mi['where'] as $x): ?><li class="<?= $x['n'] ? '' : 'zero' ?>"><span><i class="ic ic-sm <?= h($x['ic']) ?>" aria-hidden="true"></i><?= h($x['l']) ?></span><span class="cm-bar"><i style="width:<?= $x['n'] ? max(4, round(100 * $x['n'] / $peakWhere)) : 0 ?>%;background:<?= $x['c'] ?>"></i></span><b><?= sl_num($x['n']) ?></b></li><?php endforeach; ?>
      </ul>
    </article>
    <article class="cm-panel">
      <div class="row between"><b>What I ship most</b><span class="mut sm">12 months</span></div>
      <?php if ($mi['items']): ?><ol class="cm-items"><?php foreach ($mi['items'] as $k => $x): ?><li><span class="cm-rk"><?= $k + 1 ?></span><span class="cm-rn"><b><?= h($x['l']) ?></b><span class="cm-bar"><i style="width:<?= max(4, round(100 * $x['q'] / $peakItem)) ?>%"></i></span></span><span class="cm-rv"><?= sl_num($x['q']) ?> pcs</span></li><?php endforeach; ?></ol>
      <?php else: ?><p class="mut sm" style="margin:0">Shows here once your <?= h($noun) ?> have declared items.</p><?php endif; ?>
    </article>
  </div>
</section>
