<?php /** Render the permission-filtered navigation groups from sl_navResolve(). */ ?>
<?php foreach ($slNav as $g): ?>
  <details class="sl-nav-group"<?= $g['open'] ? ' open' : '' ?>>
    <summary class="sl-gl"><span><?= h($g['label']) ?></span><i class="ic ic-sm i-down" aria-hidden="true"></i></summary>
    <nav class="sl-nav" aria-label="<?= h($g['label']) ?>">
      <?php foreach ($g['items'] as $it): ?>
        <a href="<?= h($it['href']) ?>" class="<?= h($it['cls']) ?>"<?= $it['cls'] ? ' aria-current="page"' : '' ?>><i class="ic <?= h($it['icon']) ?>" aria-hidden="true"></i><span><?= h($it['label']) ?></span><?php if ($it['c1'] !== ''): ?><span class="cnt <?= h($it['ok']) ?>"><?= h($it['c1']) ?> <em><?= h($it['c2']) ?></em></span><?php endif; ?></a>
        <?php if (($it['id'] ?? '') === 'warehouse' && function_exists('sl_displayCurrency')): $slGhs = sl_displayCurrency() === 'GHS'; ?>
        <button type="button" class="sl-ccy" role="switch" aria-checked="<?= $slGhs ? 'true' : 'false' ?>" data-sl-action="ajax/sl/display_currency_ajax.php" data-sl-reload title="Show every amount in <?= $slGhs ? 'USD' : 'GHS' ?> at the current exchange rate"><i class="ic i-dollar" aria-hidden="true"></i><span>USD <b aria-hidden="true">⇄</b> GHS</span><span class="sl-toggle<?= $slGhs ? ' on' : '' ?>" aria-hidden="true"></span><em><?= $slGhs ? '₵' : '$' ?></em></button>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </details>
<?php endforeach; ?>
