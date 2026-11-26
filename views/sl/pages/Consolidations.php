<?php
/**
 * Consolidations board: Air and Sea statistics (kept apart), then the consolidation console.
 * Clicking a consolidation in the console opens its page (ConsolidationDetail); hovering previews it.
 * Data: views/sl/data/consolidations.php (sl_consolidationsBoard). Styles: assets/swiftlane/pages/Consolidations.css.
 * The sidebar starts collapsed (navCollapsed in consolidate_list.php) for a wider view.
 */
if (!isset($d)) { $d = []; }
$modes = $d['modes'];
$stageL = ['loading' => 'Loading at US warehouse', 'transit' => 'In transit', 'ready' => 'Arrived', 'done' => 'Handed over this month', 'late' => 'Past ETA'];
$filtered = $d['mode'] !== '' || $d['stage'] !== 'all' || $d['q'] !== '';
?>
<div class="sl-head">
  <div><h1 class="sl-title">Consolidations</h1><p class="sl-lede">Parcels grouped into air loads and sea containers at the US warehouse, then flown or shipped to Ghana. Click a consolidation to open it.</p></div>
  <div class="row g8 wrap">
    <?php foreach ($modes as $m): if (!$m['new']) continue; ?><a class="sl-btn<?= $m['mode'] === 'sea' ? ' ghost' : '' ?>" href="<?= h($m['new']) ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New <?= $m['mode'] === 'sea' ? 'sea' : 'air' ?> consolidation</a><?php endforeach; ?>
  </div>
</div>

<div class="cb-modes<?= count($modes) === 1 ? ' one' : '' ?>">
  <?php foreach ($modes as $m): $mOn = $d['mode'] === $m['mode']; ?>
  <section class="cb-mode m-<?= h($m['mode']) ?><?= $mOn ? ' on' : '' ?>" aria-label="<?= h($m['label']) ?>">
    <header class="cb-mh">
      <span class="cb-ic"><i class="ic <?= h($m['icon']) ?>" aria-hidden="true"></i></span>
      <div class="col grow"><h2><?= h($m['label']) ?></h2><span class="mut sm"><?= h($m['prefix']) ?></span></div>
      <a class="cb-all" href="<?= h(sl_url(['mode' => $mOn && $d['stage'] === 'all' ? null : $m['mode'], 'stage' => null, 'q' => null])) ?>">All open <?= h($m['unit']) ?> <i class="ic ic-sm i-right" aria-hidden="true"></i></a>
    </header>
    <div class="cb-stages">
      <?php foreach ($m['stages'] as $i => $s): $sOn = $mOn && $d['stage'] === $s['k']; ?>
      <a class="cb-st<?= $sOn ? ' on' : '' ?><?= !empty($s['bad']) ? ' bad' : '' ?>" href="<?= h(sl_url(['mode' => $sOn ? null : $m['mode'], 'stage' => $sOn ? null : $s['k'], 'q' => null])) ?>"<?= $sOn ? ' aria-current="true"' : '' ?>>
        <span class="cb-step"><?= $i + 1 ?></span>
        <b><?= sl_num($s['n']) ?></b>
        <span class="fw5"><?= h($s['l']) ?></span>
        <small><?= h($s['s']) ?></small>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="cb-foot">
      <dl class="cb-facts"><?php foreach ($m['facts'] as [$k, $v]): ?><div><dt><?= h($k) ?></dt><dd><?= h($v) ?></dd></div><?php endforeach; ?></dl>
      <div class="cb-spark"><div class="ovh-bars" role="img" aria-label="Consolidations created per day, last 14 days"><?php $nb = count($m['bars']); foreach ($m['bars'] as $bi => $b): ?><i class="<?= $bi === $nb - 1 ? 'now' : '' ?>" style="<?= h($b['s']) ?>" title="<?= h($b['t']) ?>"></i><?php endforeach; ?></div><small class="mut"><?= sl_num($m['barsN']) ?> created in the last 14 days</small></div>
    </div>
  </section>
  <?php endforeach; ?>
  <?php if (!$modes): ?><div class="sl-card"><div class="sl-empty">You do not have access to air or sea consolidations.</div></div><?php endif; ?>
</div>

<?php if ($filtered): ?>
<div class="cb-showing" role="status">
  <span>Showing <b><?= h($d['mode'] !== '' ? ($d['mode'] === 'sea' ? 'Sea' : 'Air') : 'Air and sea') ?></b><?php if ($d['stage'] !== 'all'): ?> · <b><?= h($stageL[$d['stage']] ?? $d['stage']) ?></b><?php endif; ?><?php if ($d['q'] !== ''): ?> · matching “<?= h($d['q']) ?>”<?php endif; ?></span>
  <a class="sl-btn ghost sm" href="<?= h(sl_url(['mode' => null, 'stage' => null, 'q' => null])) ?>"><i class="ic ic-sm i-x" aria-hidden="true"></i>Show all open</a>
</div>
<?php endif; ?>

<?php
$tc = $d['tracking']; $tcTitle = 'Consolidations'; $tcNoun = 'consolidations'; $tcExpand = '';
$tcFilters = !$filtered; $tcLight = true; $tcNavigate = true;
include __DIR__ . '/partials/track_console.php';
?>
<?php if (empty($tc['items'])): ?><div class="sl-card"><div class="sl-empty"><span class="sl-tile"><i class="ic i-layers" aria-hidden="true"></i></span><?= $filtered ? 'No consolidations in this view.' : 'No open consolidations. Start one with New consolidation.' ?></div></div><?php endif; ?>
