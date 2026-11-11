<?php if (!empty($d['charts'])): ?>
<section class="col g12" id="analytics" aria-label="Report analytics">
  <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct" style="font-size:20px">Explore the data</h2><p class="mut sm">Selected dates: <?= h(sl_date($d['from'])) ?> – <?= h(sl_date($d['to'])) ?>. Hover to inspect, or click for details and CSV download.</p></div></div>
  <div class="rp-analytics-grid">
  <?php foreach ($d['charts'] as $chart):
      $peak = max(1, ...array_merge(...array_column($chart['series'], 'values')));
      $total = array_sum(array_map(fn($s) => array_sum($s['values']), $chart['series']));
  ?>
    <section class="sl-card rp-analytics-card col g12">
      <div><h3 class="sl-ct"><?= h($chart['title']) ?></h3><p class="mut sm rp-chart-description"><?= h($chart['sub']) ?></p></div>
      <?php if (!$total): ?><div class="rp-chart-empty"><i class="ic i-chart" aria-hidden="true"></i><span>No records for these dates.</span></div>
      <?php elseif ($chart['kind'] === 'line'): ?>
        <svg <?= sl_chartAttrs($chart['series'], $chart['labels'], $chart['title'], 44, 620) ?> viewBox="0 0 640 210" class="rp-trend" role="img" aria-label="<?= h($chart['title']) ?>">
          <?php foreach ([0, .5, 1] as $fraction): $y = 170 - 150 * $fraction; ?><line x1="44" y1="<?= $y ?>" x2="620" y2="<?= $y ?>" stroke="var(--line)" stroke-dasharray="3 4"/><text x="36" y="<?= $y + 4 ?>" text-anchor="end"><?= sl_num($peak * $fraction) ?></text><?php endforeach; ?>
          <?php foreach ($chart['series'] as $series): ?><path d="<?= h(sl_svgLinePath($series['values'], 620, 170, 20, $peak, 44)) ?>" fill="none" stroke="<?= h($series['color']) ?>" stroke-width="2.5" stroke-linejoin="round"/><?php endforeach; ?>
          <?php if (count($chart['labels']) <= 31): foreach ($chart['series'] as $series): foreach ($series['values'] as $i => $value): ?><circle cx="<?= count($series['values']) === 1 ? 310 : 44 + 576 * $i / (count($series['values']) - 1) ?>" cy="<?= 170 - 150 * $value / $peak ?>" r="3" fill="<?= h($series['color']) ?>"/><?php endforeach; endforeach; endif; ?>
          <?php foreach (array_unique([0, (int) floor((count($chart['labels']) - 1) / 2), count($chart['labels']) - 1]) as $i): ?><text x="<?= 44 + 576 * $i / max(1, count($chart['labels']) - 1) ?>" y="200" text-anchor="<?= $i === 0 ? 'start' : ($i === count($chart['labels']) - 1 ? 'end' : 'middle') ?>"><?= h($chart['labels'][$i]) ?></text><?php endforeach; ?>
        </svg>
        <div class="row wrap g16 sm mut"><?php foreach ($chart['series'] as $series): ?><span class="row g6"><i class="rp-series-dot" style="background:<?= h($series['color']) ?>"></i><?= h($series['name']) ?></span><?php endforeach; ?></div>
      <?php else: $series = $chart['series'][0]; ?>
        <div class="rp-chart-bars" <?= sl_chartAttrs($chart['series'], $chart['labels'], $chart['title']) ?> role="img" aria-label="<?= h($chart['title']) ?>">
          <?php foreach ($chart['labels'] as $i => $label): ?><div class="rp-chart-row"><span class="rp-chart-label"><?= h($label) ?></span><span class="rp-chart-track"><i style="width:<?= round(100 * $series['values'][$i] / $peak, 2) ?>%;background:<?= h($series['color']) ?>"></i></span><b><?= sl_num($series['values'][$i]) ?></b></div><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
