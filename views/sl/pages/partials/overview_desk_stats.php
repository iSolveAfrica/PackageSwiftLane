<?php if (!empty($d['deskStats'])): ?>
<div class="dk-snapshot" aria-label="Operations snapshot">
  <div class="row between"><span class="up">Operations snapshot</span><span class="mut sm">Updated <?= h(date('H:i')) ?></span></div>
  <div class="dk-snapshot-grid">
    <?php foreach ($d['deskStats'] as $stat): ?>
    <a class="dk-stat" href="<?= h($stat['href']) ?>"><span class="row g8 sm"><i class="ic ic-sm <?= h($stat['icon']) ?>" aria-hidden="true"></i><?= h($stat['label']) ?></span><b><?= sl_num($stat['n']) ?></b><span class="mut sm"><?= h($stat['sub']) ?></span></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
