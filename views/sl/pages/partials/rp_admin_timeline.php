<?php
/**
 * Dark roster/timeline: one row per rider on a 06:00-21:00 axis with activity blocks (loading, on the way,
 * waiting, break) and stop markers (done time or ETA). Input: $d['timeline'] (rpv_timeline).
 */
$tl = $d['timeline'] ?? [];
?>
<section class="dk rp-tl col g12" aria-label="Rider roster and routes">
  <div class="row between g8 wrap"><div class="col"><h2 class="sl-ct">Roster · <?= h($d['dateLabel'] ?? '') ?></h2><span class="sm rp-tl-sub">Shifts and route blocks from the rider app; dots are stops (filled = done, ring = ETA).</span></div>
    <form method="get" class="row g8"><input type="hidden" name="tab" value="timeline"><input class="sl-in mono" type="date" name="date" value="<?= h($d['date'] ?? '') ?>" aria-label="Day"><button class="sl-btn ghost sm" type="submit">Show</button></form></div>
  <div class="row g12 wrap sm rp-tl-legend"><span class="row g6"><i class="rp-sw s-loading"></i>Loading</span><span class="row g6"><i class="rp-sw s-onway"></i>On the way</span><span class="row g6"><i class="rp-sw s-waiting"></i>Waiting at a stop</span><span class="row g6"><i class="rp-sw s-break"></i>Break</span><span class="row g6"><i class="rp-sw s-planned"></i>Planned route</span></div>
  <div class="rp-tl-scroll"><div class="rp-tl-grid">
    <div class="rp-tl-axis"><span class="rp-tl-name"></span><div class="rp-tl-track"><?php foreach ((array) ($tl['hours'] ?? []) as $hr): ?><span class="rp-tl-hr" style="left:<?= h($hr['x']) ?>%"><?= h($hr['h']) ?>:00</span><?php endforeach; ?></div></div>
    <?php foreach ((array) ($tl['rows'] ?? []) as $row): ?>
    <div class="rp-tl-row">
      <span class="rp-tl-name"><span class="rp-av sm" style="--rc:<?= h($row['color']) ?>" aria-hidden="true"><?= h($row['initials']) ?></span><span class="col"><b class="fw5"><?= h($row['name']) ?></b><span class="sm rp-tl-sub"><?= h($row['vehicle']) ?> · <?= h($row['work']) ?></span></span></span>
      <div class="rp-tl-track"><?php foreach ((array) ($tl['hours'] ?? []) as $hr): ?><i class="rp-tl-gl" style="left:<?= h($hr['x']) ?>%"></i><?php endforeach; ?>
        <?php if ($tl['now'] !== null): ?><i class="rp-tl-now" style="left:<?= h($tl['now']) ?>%"></i><?php endif; ?>
        <?php foreach ($row['blocks'] as $b): ?><span class="rp-tl-b s-<?= h($b['state']) ?>" style="left:<?= h($b['x']) ?>%;width:<?= h($b['w']) ?>%" title="<?= h($b['label'] . ' ' . $b['time']) ?>" aria-label="<?= h($b['label'] . ' ' . $b['time']) ?>"><?php if (empty($b['short'])): ?><span><?= h($b['label']) ?></span><?php endif; ?></span><?php endforeach; ?>
        <?php foreach ($row['stops'] as $sp): ?><span class="rp-tl-s st-<?= h($sp['status']) ?><?= $sp['n'] > 1 ? ' multi' : '' ?>" style="left:<?= h($sp['x']) ?>%" title="<?= h($sp['title']) ?>"><?= $sp['n'] > 1 ? '<b>' . (int) $sp['n'] . '</b>' : '' ?></span><?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div></div>
  <?php if (empty($tl['rows'])): ?><div class="sm rp-tl-sub">No shifts or routes recorded for this day.</div><?php endif; ?>
</section>
