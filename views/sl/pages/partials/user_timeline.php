<?php
/**
 * Everything-in-one timeline (CustomerProfile for staff, UserHome for the customer).
 * Expects $tl (sl_u360Timeline()), $tlAud ('staff' | 'customer'), $tlAnchor (fragment id).
 * Filter chips and "Show more" keep the other query parameters (sl_url).
 */
$tlAnchor = $tlAnchor ?? 'everything';
$tlGroups = sl_u360Groups($tlAud);
?>
<div class="u3-tl" id="<?= h($tlAnchor) ?>">
  <div class="u3-chips" role="tablist" aria-label="Filter the timeline">
    <a role="tab" aria-selected="<?= $tl['k'] === '' ? 'true' : 'false' ?>" class="<?= $tl['k'] === '' ? 'on' : '' ?>" href="<?= h(sl_url(['k' => null, 'n' => null])) ?>#<?= h($tlAnchor) ?>"><i class="ic ic-sm i-activity" aria-hidden="true"></i>Everything<b><?= sl_num($tl['all']) ?></b></a>
    <?php foreach ($tl['groups'] as $gk => [$gl, $gi, $gn]): ?>
      <a role="tab" aria-selected="<?= $tl['k'] === $gk ? 'true' : 'false' ?>" class="<?= $tl['k'] === $gk ? 'on' : '' ?>" href="<?= h(sl_url(['k' => $gk, 'n' => null])) ?>#<?= h($tlAnchor) ?>"><i class="ic ic-sm <?= h($gi) ?>" aria-hidden="true"></i><?= h($gl) ?><b><?= sl_num($gn) ?></b></a>
    <?php endforeach; ?>
  </div>
  <?php if (!$tl['days']): ?>
    <div class="sl-empty"><span class="sl-tile"><i class="ic i-activity" aria-hidden="true"></i></span><?= $tlAud === 'staff' ? 'Nothing recorded for this customer yet.' : 'Nothing here yet. Your parcels, payments and messages will show up here.' ?></div>
  <?php endif; ?>
  <?php foreach ($tl['days'] as [$dayLabel, $items]): ?>
    <div class="u3-day"><span class="u3-dl"><?= h($dayLabel) ?></span>
      <ol class="u3-list">
        <?php foreach ($items as $e): ?>
          <li class="u3-ev <?= h($e['tone']) ?>">
            <span class="u3-ic" title="<?= h($tlGroups[$e['k']][0] ?? '') ?>"><i class="ic ic-sm <?= h($e['ic']) ?>" aria-hidden="true"></i></span>
            <div class="u3-body">
              <?php if ($e['href']): ?><a class="u3-t" href="<?= h($e['href']) ?>"><?= h($e['t']) ?></a><?php else: ?><span class="u3-t"><?= h($e['t']) ?></span><?php endif; ?>
              <?php if ($e['s'] !== ''): ?><span class="u3-s"><?= h($e['s']) ?></span><?php endif; ?>
              <span class="u3-m"><?= h(trim($e['time'] . ($e['by'] !== '' ? ' · by ' . $e['by'] : ''), ' ·')) ?><?php if ($tl['k'] === ''): ?><span class="u3-g"><?= h($tlGroups[$e['k']][0] ?? '') ?></span><?php endif; ?></span>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  <?php endforeach; ?>
  <?php if ($tl['more']): ?>
    <div class="row between wrap g8 u3-foot"><span class="mut sm">Showing <?= sl_num($tl['shown']) ?> of <?= sl_num($tl['total']) ?></span><a class="sl-btn ghost sm" href="<?= h(sl_url(['n' => $tl['shown'] + 60])) ?>#<?= h($tlAnchor) ?>"><i class="ic ic-sm i-down" aria-hidden="true"></i>Show more</a></div>
  <?php elseif ($tl['total']): ?>
    <div class="mut sm u3-foot">That is everything · <?= sl_num($tl['total']) ?> entries</div>
  <?php endif; ?>
</div>
