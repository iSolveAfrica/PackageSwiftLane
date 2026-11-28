<?php
/**
 * One mode on the customer's My parcels hub (sea or air): what is moving now, the last 12 months and the
 * latest parcel; the whole card opens that list. Expects $mi = sl_clientModeInsights(). Styles: client-modes.css.
 */
$peakWhere = max(1, max(array_column($mi['where'], 'n') ?: [0]));
?>
<a class="cm-card t-<?= h($mi['mode']) ?>" href="<?= h($mi['href']) ?>">
  <div class="cm-ch">
    <span class="cm-ic"><i class="ic <?= h($mi['icon']) ?>" aria-hidden="true"></i></span>
    <span class="cm-tt"><b><?= h($mi['title']) ?></b><small><?= $mi['sea'] ? 'Shipped by sea' : 'Flown by air' ?></small></span>
    <span class="cm-open">Open <i class="ic ic-sm i-right" aria-hidden="true"></i></span>
  </div>
  <div class="cm-big"><b><?= sl_num($mi['active']) ?></b><span>on the way now<br><small><?= sl_num($mi['total']) ?> in total · <?= sl_num($mi['delivered']) ?> delivered</small></span></div>
  <ul class="cm-where">
    <?php foreach ($mi['where'] as $x): ?><li class="<?= $x['n'] ? '' : 'zero' ?>"><span><i class="ic ic-sm <?= h($x['ic']) ?>" aria-hidden="true"></i><?= h($x['l']) ?></span><span class="cm-bar"><i style="width:<?= $x['n'] ? max(4, round(100 * $x['n'] / $peakWhere)) : 0 ?>%;background:<?= $x['c'] ?>"></i></span><b><?= sl_num($x['n']) ?></b></li><?php endforeach; ?>
  </ul>
  <div class="cm-chart">
    <div class="row between sm"><span class="mut">Last 12 months</span><span class="mut"><?= sl_num($mi['n12']) ?> shipments · <?= h(sl_weight($mi['w12'])) ?></span></div>
    <div class="cm-bars" role="img" aria-label="<?= h($mi['title']) ?> per month, last 12 months"><?php $nb = count($mi['bars']); foreach ($mi['bars'] as $i => $b): ?><i class="<?= $i === $nb - 1 ? 'now' : '' ?>" style="<?= h($b['s']) ?>" title="<?= h($b['t']) ?>"></i><?php endforeach; ?></div>
    <div class="cm-axis"><?php foreach ($mi['axis'] as $i => $a): ?><span><?= $i % 2 === 0 || $i === 11 ? h($a) : '' ?></span><?php endforeach; ?></div>
  </div>
  <div class="cm-foot">
    <?php if ($mi['last']): ?><span class="mut sm">Latest <b class="mono"><?= h($mi['last']['id']) ?></b> · <?= h($mi['last']['date']) ?></span><span class="sl-pill <?= h($mi['last']['cls']) ?>"><?= h($mi['last']['st']) ?></span>
    <?php else: ?><span class="mut sm">No <?= $mi['sea'] ? 'sea shipments' : 'air shipments' ?> yet</span><?php endif; ?>
  </div>
</a>
