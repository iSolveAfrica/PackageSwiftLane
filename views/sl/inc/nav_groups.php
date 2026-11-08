<?php /** Render the permission-filtered navigation groups from sl_navResolve(). */ ?>
<?php foreach ($slNav as $g): ?>
  <details class="sl-nav-group"<?= $g['open'] ? ' open' : '' ?>>
    <summary class="sl-gl"><span><?= h($g['label']) ?></span><i class="ic ic-sm i-down" aria-hidden="true"></i></summary>
    <nav class="sl-nav" aria-label="<?= h($g['label']) ?>">
      <?php foreach ($g['items'] as $it): ?>
        <a href="<?= h($it['href']) ?>" class="<?= h($it['cls']) ?>"<?= $it['cls'] ? ' aria-current="page"' : '' ?>><i class="ic <?= h($it['icon']) ?>" aria-hidden="true"></i><span><?= h($it['label']) ?></span><?php if ($it['c1'] !== ''): ?><span class="cnt <?= h($it['ok']) ?>"><?= h($it['c1']) ?> <em><?= h($it['c2']) ?></em></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
  </details>
<?php endforeach; ?>
