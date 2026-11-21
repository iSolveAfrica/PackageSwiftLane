<?php
/**
 * Parcels still on their way, with a simple 4-step journey.
 * Expects $moving (sl_u360InMotion()) and $movingTitle.
 */
$steps = ['Booked', 'In transit', 'At warehouse', 'Ready'];
?>
<section class="sl-card col g12">
  <div class="sl-ch" style="margin:0"><h2 class="sl-ct"><?= h($movingTitle ?? 'On the move') ?></h2><span class="mut sm"><?= count($moving) ?> open</span></div>
  <?php if (!$moving): ?><span class="mut sm">Nothing on the way right now.</span><?php endif; ?>
  <div class="u3-mv">
    <?php foreach ($moving as $m): ?>
      <a class="u3-mi" href="<?= h($m['href']) ?>">
        <span class="row between g8"><b class="mono fw5"><?= h($m['trk']) ?></b><span class="sl-pill <?= h($m['cls']) ?>"><?= h($m['st']) ?></span></span>
        <span class="u3-steps" role="img" aria-label="Step <?= (int) $m['stage'] ?> of 4: <?= h($steps[$m['stage'] - 1] ?? '') ?>"><?php for ($i = 1; $i <= 4; $i++): ?><i class="<?= $i <= $m['stage'] ? 'on' : '' ?>"></i><?php endfor; ?></span>
        <span class="row between mut" style="font-size:13.5px"><span><?= h($m['kind']) ?> · <?= h($steps[$m['stage'] - 1] ?? '') ?></span><span><?= $m['eta'] ? 'ETA ' . h($m['eta']) : h($m['since']) ?></span></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
