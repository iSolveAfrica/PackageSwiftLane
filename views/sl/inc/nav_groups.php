<?php /** Render the permission-filtered navigation groups from sl_navResolve(). */
$groupIcons = ['Operations'=>'gauge','RIDERS'=>'truck','Command Centers'=>'grid','Developer'=>'terminal','Support'=>'ticket','Freight'=>'plane','People'=>'users','Finance'=>'card','Insights'=>'chart','Settings'=>'cog','My shipping'=>'box','Account'=>'user'];
// colour key per group (assets/swiftlane/color.css reads data-group)
$groupKeys = ['Operations'=>'ops','RIDERS'=>'riders','Command Centers'=>'centers','Developer'=>'dev','Support'=>'support','Freight'=>'freight','People'=>'people','Finance'=>'finance','Insights'=>'insights','Settings'=>'settings','My shipping'=>'shipping','Account'=>'account'];
?>
<?php foreach ($slNav as $g): ?>
  <details class="sl-nav-group" data-group="<?= h($groupKeys[$g['label']] ?? 'ops') ?>"<?= $g['open'] ? ' open' : '' ?>>
    <summary class="sl-gl"><span class="sl-group-icon"><i class="ic i-<?= h($groupIcons[$g['label']] ?? 'grid') ?>" aria-hidden="true"></i></span><span class="sl-group-label"><?= h($g['label']) ?></span><i class="ic ic-sm i-down sl-group-chevron" aria-hidden="true"></i></summary>
    <nav class="sl-nav" aria-label="<?= h($g['label']) ?>">
      <?php foreach ($g['items'] as $it): ?>
        <a href="<?= h($it['href']) ?>" class="<?= h($it['cls']) ?>" data-shortcut-id="<?= h($it['id']) ?>"<?= $it['cls'] ? ' aria-current="page"' : '' ?>><i class="ic <?= h($it['icon']) ?>" aria-hidden="true"></i><span><?= h($it['label']) ?></span><?php if ($it['c1'] !== ''): ?><span class="cnt <?= h($it['ok']) ?>"><?= h($it['c1']) ?> <em><?= h($it['c2']) ?></em></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
  </details>
<?php endforeach; ?>
