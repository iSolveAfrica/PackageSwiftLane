<?php
/** Rolling-year shipping heatmap. Expects $heat (sl_u360Heatmap()) and $heatTitle. */
?>
<section class="sl-card col g10">
  <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct"><?= h($heatTitle ?? 'Shipping rhythm') ?></h2><div class="mut sm">Parcels per day · last 12 months</div></div><span class="mut sm"><?= sl_num($heat['total']) ?> parcels · <?= sl_num($heat['activeDays']) ?> active days · peak <?= (int) $heat['peak'] ?> a day</span></div>
  <div class="u3-heat-wrap"><div class="u3-heat" role="img" aria-label="<?= sl_num($heat['total']) ?> parcels over the last 12 months">
    <div class="u3-hm"><?php foreach ($heat['months'] as [$w, $m]): ?><span style="grid-column:<?= (int) $w + 1 ?>"><?= h($m) ?></span><?php endforeach; ?></div>
    <div class="u3-hg"><?php foreach ($heat['weeks'] as $col): ?><?php foreach ($col as $c): ?><i class="l<?= (int) $c['l'] ?>" title="<?= h(sl_date($c['d'], 'D j M Y')) ?>: <?= (int) $c['n'] ?>"></i><?php endforeach; ?><?php endforeach; ?></div>
  </div></div>
  <div class="row g6 mut sm" style="justify-content:flex-end">Quiet <i class="u3-sw l0"></i><i class="u3-sw l1"></i><i class="u3-sw l2"></i><i class="u3-sw l3"></i><i class="u3-sw l4"></i> Busy</div>
</section>
