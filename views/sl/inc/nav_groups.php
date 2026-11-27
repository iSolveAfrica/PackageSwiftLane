<?php /** Render the permission-filtered navigation groups from sl_navResolve(). */
$groupIcons = ['Operations'=>'gauge','Delivery & Riders'=>'truck','Air Shipping'=>'plane','Sea Shipping'=>'ship','Warehouse'=>'warehouse','Command Centers'=>'grid','Developer'=>'terminal','Support'=>'ticket','Freight'=>'plane','People'=>'users','Finance'=>'card','Insights'=>'chart','Settings'=>'cog','My shipping'=>'box','Account'=>'user'];
// colour key per group (assets/swiftlane/color.css reads data-group)
$groupKeys = ['Operations'=>'ops','Delivery & Riders'=>'riders','Air Shipping'=>'ops','Sea Shipping'=>'freight','Warehouse'=>'ops','Command Centers'=>'centers','Developer'=>'dev','Support'=>'support','Freight'=>'freight','People'=>'people','Finance'=>'finance','Insights'=>'insights','Settings'=>'settings','My shipping'=>'shipping','Account'=>'account'];
?>
<?php foreach ($slNav as $g): ?>
  <details class="sl-nav-group" data-group="<?= h($groupKeys[$g['label']] ?? 'ops') ?>"<?= $g['open'] ? ' open' : '' ?>>
    <summary class="sl-gl"><span class="sl-group-icon"><i class="ic i-<?= h($groupIcons[$g['label']] ?? 'grid') ?>" aria-hidden="true"></i></span><span class="sl-group-label"><?= h($g['label']) ?></span><i class="ic ic-sm i-down sl-group-chevron" aria-hidden="true"></i></summary>
    <nav class="sl-nav" aria-label="<?= h($g['label']) ?>">
      <?php foreach ($g['items'] as $it): ?>
        <?php if (!empty($it['children'])): // sub-menu: a disclosure (summary = keyboard toggle) holding the child links ?>
        <details class="sl-nav-tree<?= !empty($it['act']) ? ' has-active' : '' ?>" data-nav-tree="<?= h($it['id']) ?>"<?= !empty($it['open']) ? ' open' : '' ?>>
          <summary class="sl-nav-parent"><i class="ic <?= h($it['icon']) ?>" aria-hidden="true"></i><span><?= h($it['label']) ?></span><?php if ($it['c1'] !== ''): ?><span class="cnt" title="WhatsApp conversations waiting for a person"><?= h($it['c1']) ?></span><?php endif; ?><i class="ic ic-sm i-down sl-nav-chev" aria-hidden="true"></i></summary>
          <div class="sl-nav-sub" role="group" aria-label="<?= h($it['label']) ?>">
            <?php foreach ($it['children'] as $ch): ?>
            <a href="<?= h($ch['href']) ?>" class="<?= h($ch['cls']) ?>" data-shortcut-id="<?= h($ch['id']) ?>"<?= $ch['cls'] ? ' aria-current="page"' : '' ?>><i class="ic <?= h($ch['icon']) ?>" aria-hidden="true"></i><span><?= h($ch['label']) ?></span><?php if ($ch['c1'] !== ''): ?><span class="cnt"><?= h($ch['c1']) ?></span><?php endif; ?></a>
            <?php endforeach; ?>
          </div>
        </details>
        <?php continue; endif; ?>
        <a href="<?= h($it['href']) ?>" class="<?= h($it['cls']) ?>" data-shortcut-id="<?= h($it['id']) ?>"<?= $it['cls'] ? ' aria-current="page"' : '' ?>><i class="ic <?= h($it['icon']) ?>" aria-hidden="true"></i><span><?= h($it['label']) ?></span><?php if ($it['c1'] !== ''): ?><span class="cnt <?= h($it['ok']) ?>"><?= h($it['c1']) ?> <em><?= h($it['c2']) ?></em></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
  </details>
<?php endforeach; ?>
