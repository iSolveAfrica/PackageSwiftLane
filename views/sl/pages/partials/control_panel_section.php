<?php /** @var array $s One permission-filtered Control panels section. */ ?>
<section class="cpx-card<?= $s['featured'] ? ' cpx-card-featured' : '' ?>" id="<?= h($s['id']) ?>">
  <div class="cpx-ch">
    <span class="tl"><i class="ic <?= h($s['icon']) ?>" aria-hidden="true"></i></span>
    <div><b><?= h($s['t']) ?></b><span><?= h($s['s']) ?></span></div>
    <span class="cpx-card-count" aria-label="<?= count($s['rows']) ?> available destinations"><?= count($s['rows']) ?></span>
  </div>
  <?php foreach ($s['rows'] as $r): ?>
    <a class="cpx-row" href="<?= h($r['href']) ?>">
      <i class="ic ic-sm <?= h($r['icon']) ?>" aria-hidden="true"></i>
      <span class="nm"><span><?= h($r['label']) ?></span><small><?= h($r['sub']) ?></small></span>
      <?php if ($r['n'] !== ''): ?><span class="sl-badge mono"><?= h($r['n']) ?></span><?php endif; ?>
      <i class="ic ic-sm i-right ch" aria-hidden="true"></i>
    </a>
  <?php endforeach; ?>
</section>
