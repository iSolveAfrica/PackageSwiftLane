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

    <?php $gh = $ch['ghana'] ?? []; if ($gh && $gh['total'] > 0):
        $ghId = 'gh' . substr(md5((string) mt_rand()), 0, 6);
        $ghPeak = max(1, max(array_column($gh['regions'], 'n')));
        $ghSel = $gh['top'];
    ?>
    <section class="sl-viz-card sl-viz-card--mint sl-gh" id="<?= $ghId ?>" data-gh aria-label="Where parcels go in Ghana">
      <div class="sl-viz-card__head"><div><span class="sl-viz-card__eyebrow">Top destinations</span><h3>Where parcels go</h3></div><span class="sl-viz-card__period"><?= (int) $ch['days'] ?> days</span></div>
      <p class="sl-viz-card__sub"><?= sl_num($gh['total']) ?> air shipments across <?= count(array_filter($gh['regions'], fn($r) => $r['n'] > 0)) ?> of 16 regions · hover a region for its towns, click to open it</p>
      <div class="sl-gh__body">
        <div class="sl-gh__map">
          <svg viewBox="0 0 <?= (float) $gh['w'] ?> <?= (float) $gh['h'] ?>" role="group" aria-label="Map of Ghana with parcels per region">
            <path class="sl-gh__land" d="<?= h($gh['outline']) ?>"/>
            <?php foreach ($gh['regions'] as $r): ?>
            <g class="sl-gh__places<?= $r['key'] === $ghSel ? ' on' : '' ?>" data-gh-places="<?= h($r['key']) ?>" aria-hidden="true">
              <?php foreach (array_slice($r['places'], 0, 40) as $pl): $pr = 2.2 + 5 * sqrt($pl['n'] / max(1, $r['places'][0]['n'])); ?><circle cx="<?= $pl['xy'][0] ?>" cy="<?= $pl['xy'][1] ?>" r="<?= round($pr, 1) ?>"><title><?= h($pl['name']) ?>: <?= sl_num($pl['n']) ?><?= !empty($pl['approx']) ? ' (approximate position)' : '' ?></title></circle><?php endforeach; ?>
            </g>
            <?php endforeach; ?>
            <?php foreach ($gh['regions'] as $r): $rad = $r['n'] ? 7 + 13 * sqrt($r['n'] / $ghPeak) : 4; ?>
            <g class="sl-gh__reg l<?= (int) $r['level'] ?><?= $r['key'] === $ghSel ? ' on' : '' ?>" data-gh-r="<?= h($r['key']) ?>" tabindex="0" role="button" aria-pressed="<?= $r['key'] === $ghSel ? 'true' : 'false' ?>" aria-label="<?= h($r['name']) ?>: <?= sl_num($r['n']) ?> shipments" transform="translate(<?= $r['xy'][0] ?> <?= $r['xy'][1] ?>)">
              <circle class="halo" r="<?= round($rad + 6, 1) ?>"/><circle class="dot" r="<?= round($rad, 1) ?>"/>
              <?php if ($r['n']): ?><text class="n" y="4"><?= $r['n'] >= 1000 ? round($r['n'] / 1000, 1) . 'k' : (int) $r['n'] ?></text><?php endif; ?>
              <text class="lbl" y="<?= round($rad + 14, 1) ?>"><?= h($r['name']) ?></text>
            </g>
            <?php endforeach; ?>
          </svg>
          <div class="sl-gh__tip" data-gh-tip hidden></div>
        </div>
        <div class="sl-gh__side">
          <ol class="sl-gh__list">
            <?php $i = 0; foreach ($gh['regions'] as $r): if (!$r['n']) continue; $i++; ?>
            <li><button type="button" class="<?= $r['key'] === $ghSel ? 'on' : '' ?>" data-gh-r="<?= h($r['key']) ?>" aria-pressed="<?= $r['key'] === $ghSel ? 'true' : 'false' ?>"><span class="sl-insights__rank"><?= $i ?></span><span class="col grow"><span class="row between"><span><?= h($r['name']) ?></span><strong><?= sl_num($r['n']) ?> <small><?= (int) $r['pct'] ?>%</small></strong></span><span class="sl-insights__track"><i style="width:<?= max(2, (int) round($r['n'] / $ghPeak * 100)) ?>%"></i></span></span></button></li>
            <?php endforeach; ?>
          </ol>
          <?php foreach ($gh['regions'] as $r): if (!$r['n']) continue; ?>
          <div class="sl-gh__detail<?= $r['key'] === $ghSel ? ' on' : '' ?>" data-gh-detail="<?= h($r['key']) ?>" data-gh-name="<?= h($r['name']) ?>" data-gh-n="<?= sl_num($r['n']) ?>" data-gh-top="<?= h(implode(', ', array_map(fn($p) => $p['name'] . ' ' . sl_num($p['n']), array_slice($r['places'], 0, 3)))) ?>">
            <div class="row between"><b><?= h($r['name']) ?> · <?= count($r['places']) ?> location<?= count($r['places']) === 1 ? '' : 's' ?></b><span class="mut sm">Capital <?= h($r['capital']) ?></span></div>
            <ul><?php foreach (array_slice($r['places'], 0, 12) as $pl): ?><li><span><?= h($pl['name']) ?></span><i style="width:<?= max(3, (int) round($pl['n'] / max(1, $r['places'][0]['n']) * 100)) ?>%"></i><b><?= sl_num($pl['n']) ?></b></li><?php endforeach; ?></ul>
            <?php if (count($r['places']) > 12): ?><span class="mut sm">+ <?= count($r['places']) - 12 ?> more locations</span><?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php if ($gh['unplaced']): ?><details class="sl-gh__unplaced"><summary><?= sl_num($gh['unplaced']) ?> with a city we could not place on the map</summary><p><?= h(implode(' · ', array_map(fn($u) => $u['name'] . ' ' . $u['n'], $gh['unplacedList']))) ?></p></details><?php endif; ?>
        </div>
      </div>
    </section>
    <script>
    (function () {
      var root = document.getElementById('<?= $ghId ?>'); if (!root) return;
      var tip = root.querySelector('[data-gh-tip]');
      function pick(k) {
        root.querySelectorAll('[data-gh-r]').forEach(function (e) { var on = e.getAttribute('data-gh-r') === k; e.classList.toggle('on', on); e.setAttribute('aria-pressed', on ? 'true' : 'false'); });
        root.querySelectorAll('[data-gh-places]').forEach(function (e) { e.classList.toggle('on', e.getAttribute('data-gh-places') === k); });
        root.querySelectorAll('[data-gh-detail]').forEach(function (e) { e.classList.toggle('on', e.getAttribute('data-gh-detail') === k); });
      }
      root.addEventListener('click', function (e) { var t = e.target.closest('[data-gh-r]'); if (t) pick(t.getAttribute('data-gh-r')); });
      root.addEventListener('keydown', function (e) { var t = e.target.closest('g[data-gh-r]'); if (t && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); pick(t.getAttribute('data-gh-r')); } });
      root.querySelectorAll('g[data-gh-r]').forEach(function (g) {
        g.addEventListener('mouseenter', function () {
          var d = root.querySelector('[data-gh-detail="' + g.getAttribute('data-gh-r') + '"]'), box = root.querySelector('.sl-gh__map').getBoundingClientRect(), r = g.getBoundingClientRect();
          tip.textContent = '';
          var b = document.createElement('b'); b.textContent = d ? d.getAttribute('data-gh-name') + ' · ' + d.getAttribute('data-gh-n') : g.getAttribute('aria-label');
          tip.appendChild(b);
          if (d && d.getAttribute('data-gh-top')) { var s = document.createElement('span'); s.textContent = d.getAttribute('data-gh-top'); tip.appendChild(s); }
          tip.style.left = (r.left - box.left + r.width / 2) + 'px'; tip.style.top = (r.top - box.top) + 'px'; tip.hidden = false;
        });
        g.addEventListener('mouseleave', function () { tip.hidden = true; });
      });
    })();
    </script>
    <?php else: ?>
    <section class="sl-viz-card sl-viz-card--mint" aria-label="Top delivery cities">
      <div class="sl-viz-card__head"><div><span class="sl-viz-card__eyebrow">Top destinations</span><h3>Where parcels go</h3></div><span class="sl-viz-card__period"><?= (int) $ch['days'] ?> days</span></div>
      <p class="sl-viz-card__sub">Air shipment recipient cities by registrations</p>
      <p class="sl-empty">No air shipments in this period.</p>
    </section>
    <?php endif; ?>

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
