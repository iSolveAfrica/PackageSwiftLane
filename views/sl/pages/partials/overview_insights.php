<?php if (!empty($d['insights'])):
    $ch = $d['insights'];
    $count = count($ch['current']);
    $plotLeft = 38; $plotRight = 746; $plotTop = 20; $plotBottom = 190;
    $step = ($plotRight - $plotLeft) / max(1, $count);
    $barWidth = min(28, $step * .62);
    $priorPath = '';
    foreach ($ch['previous'] as $i => $value) {
        $x = $plotLeft + $step * ($i + .5);
        $y = $plotBottom - ($plotBottom - $plotTop) * $value / $ch['peak'];
        $priorPath .= ($i ? ' L' : 'M') . round($x, 2) . ',' . round($y, 2);
    }
?>
<section class="sl-insights" aria-labelledby="insights-title" data-overview-refresh="ajax/sl/overview_insights.php?chart_days=<?= (int) $ch['days'] ?>">
  <div class="sl-insights__head">
    <div><span class="up">Across the system</span><h2 id="insights-title" class="sl-title">Operations in focus</h2><p class="mut sm"><?= h($ch['range']) ?> · updated <time datetime="<?= h(date(DATE_ATOM)) ?>"><?= h(date('H:i')) ?></time></p></div>
    <nav class="sl-insights__periods" aria-label="Chart period">
      <?php foreach ([7 => '7 days', 14 => '14 days', 30 => '30 days'] as $days => $label): ?>
      <a class="<?= $ch['days'] === $days ? 'is-active' : '' ?>" href="?chart_days=<?= (int) $days ?>"<?= $ch['days'] === $days ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
  <div class="sl-insights__grid">
    <section class="sl-viz-card sl-viz-card--blue sl-insights__trend" aria-label="Daily registrations">
      <div class="sl-viz-card__head"><div><span class="sl-viz-card__eyebrow">Daily registrations</span><h3><?= sl_num($ch['total']) ?> <small>registered</small></h3></div><span class="sl-viz-card__period"><?= h($ch['range']) ?></span></div>
      <p class="sl-viz-card__sub">
        <?php if ($ch['change'] !== null): ?><span class="sl-viz-card__delta <?= $ch['change'] < 0 ? 'is-down' : '' ?>"><?= $ch['change'] > 0 ? '+' : '' ?><?= h($ch['change']) ?>%</span> versus the previous <?= (int) $ch['days'] ?> days
        <?php else: ?>No registrations in the previous period<?php endif; ?>
      </p>
      <?php if (!$ch['total']): ?><p class="mut sm">No registrations in this period.</p><?php endif; ?>
      <div class="sl-insights__plot">
        <svg <?= sl_chartAttrs([['name' => 'This period', 'values' => $ch['current'], 'color' => 'var(--ch-2)'], ['name' => 'Previous period', 'values' => $ch['previous'], 'color' => 'var(--ch-3)']], $ch['labels'], 'Daily registrations', $plotLeft + $step / 2, $plotRight - $step / 2) ?> viewBox="0 0 760 220" role="img" aria-label="Daily registrations for <?= (int) $ch['days'] ?> days compared with the previous period">
          <?php foreach (array_unique([0, intdiv((int) $ch['peak'], 2), $ch['peak']]) as $tick): $y = $plotBottom - ($plotBottom - $plotTop) * $tick / $ch['peak']; ?><line class="sl-insights__gridline" x1="<?= $plotLeft ?>" x2="<?= $plotRight ?>" y1="<?= $y ?>" y2="<?= $y ?>"/><text class="sl-insights__axis" x="30" y="<?= $y + 4 ?>" text-anchor="end"><?= sl_num($tick) ?></text><?php endforeach; ?>
          <?php foreach ($ch['current'] as $i => $value): $x = $plotLeft + $step * ($i + .5) - $barWidth / 2; $height = ($plotBottom - $plotTop) * $value / $ch['peak']; ?><rect class="sl-insights__bar" x="<?= round($x, 2) ?>" y="<?= round($plotBottom - $height, 2) ?>" width="<?= round($barWidth, 2) ?>" height="<?= round($height, 2) ?>" rx="3"><title><?= h($ch['labels'][$i]) ?>: <?= (int) $value ?> registrations</title></rect><?php endforeach; ?>
          <path class="sl-insights__prior" d="<?= h($priorPath) ?>"/>
          <?php foreach (array_unique([0, (int) floor(($count - 1) / 2), $count - 1]) as $i): ?><text class="sl-insights__axis" x="<?= round($plotLeft + $step * ($i + .5), 2) ?>" y="214" text-anchor="middle"><?= h($ch['labels'][$i]) ?></text><?php endforeach; ?>
        </svg>
      </div>
      <div class="sl-viz-card__legend"><span><i class="sl-viz-card__key sl-viz-card__key--blue"></i>This period</span><span><i class="sl-viz-card__key sl-viz-card__key--amber"></i>Previous period</span></div>
      <div class="sl-insights__totals"><?php foreach ($ch['series'] as $series): ?><span><?= h($series['name']) ?> <strong><?= sl_num($series['total']) ?></strong></span><?php endforeach; ?></div>
    </section>

    <?php if (!empty($ch['status'])): ?>
    <section class="sl-viz-card sl-viz-card--violet" aria-label="Air shipment status mix">
      <div class="sl-viz-card__head"><div><span class="sl-viz-card__eyebrow">Air shipment status</span><h3><?= sl_num($ch['statusTotal']) ?> <small>shipments</small></h3></div><span class="sl-viz-card__period"><?= (int) $ch['days'] ?> days</span></div>
      <p class="sl-viz-card__sub">How shipments registered in this period are progressing</p>
      <div class="sl-insights__mix"><div class="sl-insights__donut" style="--sl-donut:<?= h($ch['statusTotal'] ? $ch['statusConic'] : 'var(--ch-muted)') ?>" <?= sl_chartAttrs([['name' => 'Shipments', 'values' => array_column($ch['status'], 'count')]], array_column($ch['status'], 'label'), 'Air shipment status mix', null, null, 'donut') ?> role="img" aria-label="Status mix of <?= (int) $ch['statusTotal'] ?> air shipments"><span><?= sl_num($ch['statusTotal']) ?><small>total</small></span></div>
      <ul class="sl-insights__mix-list"><?php foreach ($ch['status'] as $row): ?><li><i style="--sl-key:<?= h($row['color']) ?>"></i><span><?= h($row['label']) ?></span><strong><?= sl_num($row['count']) ?></strong></li><?php endforeach; ?></ul></div>
    </section>

    <section class="sl-viz-card sl-viz-card--mint" aria-label="Top delivery cities">
      <div class="sl-viz-card__head"><div><span class="sl-viz-card__eyebrow">Top destinations</span><h3>Where parcels go</h3></div><span class="sl-viz-card__period"><?= (int) $ch['days'] ?> days</span></div>
      <p class="sl-viz-card__sub">Air shipment recipient cities by registrations</p>
      <?php if ($ch['destinations']): ?><ol class="sl-insights__dest"><?php foreach ($ch['destinations'] as $i => $row): ?><li><span class="sl-insights__rank"><?= $i + 1 ?></span><div><div class="sl-insights__dest-label"><span><?= h($row['city']) ?></span><strong><?= sl_num($row['count']) ?></strong></div><div class="sl-insights__track"><i style="width:<?= (int) $row['pct'] ?>%"></i></div></div></li><?php endforeach; ?></ol>
      <?php else: ?><p class="sl-empty">No air shipments in this period.</p><?php endif; ?>
    </section>

    <section class="sl-viz-card sl-viz-card--amber sl-insights__intake" aria-label="Daily air shipment intake">
      <div class="sl-viz-card__head"><div><span class="sl-viz-card__eyebrow">Daily intake</span><h3>Last 12 weeks</h3></div><span class="sl-viz-card__period">Peak <?= sl_num($ch['heatPeak']) ?> / day</span></div>
      <p class="sl-viz-card__sub">Air shipments registered each day</p>
      <div class="sl-insights__heat" role="img" aria-label="Daily air shipment registrations over the last 84 days">
        <?php foreach ($ch['heat'] as $cell): ?><span class="<?= h($cell['level']) ?>" title="<?= h($cell['date']) ?>: <?= (int) $cell['count'] ?> shipments"></span><?php endforeach; ?>
      </div>
      <div class="sl-insights__heat-key"><span>Quiet</span><i class="h0"></i><i class="h1"></i><i class="h2"></i><i class="h3"></i><span>Busy</span></div>
    </section>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>
