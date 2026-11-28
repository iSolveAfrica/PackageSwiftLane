<?php
/**
 * Shipment insights above the Air Shipments list (staff): status tiles, this month by day,
 * the last 12 months, status mix, top shippers, most shipped items and an AI brief.
 * Data: sl_shipmentInsightsData() in views/sl/data/shipments.php. Charts use the shared tooltip
 * layer (dataJs/sl/chart_interactions.js) through sl_chartAttrs().
 * Expects $d (sl_airShipmentsData) with $d['ins'].
 */
$in = $d['ins'];
$tiles = [
    ['f' => 'open', 'icon' => 'i-clock', 'c' => 'var(--yellow)', 'sub' => 'still moving'],
    ['f' => 'delivered', 'icon' => 'i-check', 'c' => 'var(--green)', 'sub' => 'handed over'],
    ['f' => 'unpaid', 'icon' => 'i-alert', 'c' => 'var(--red)', 'sub' => 'payment due'],
    ['f' => 'consol', 'icon' => 'i-layers', 'c' => 'var(--ch-4)', 'sub' => 'grouped to ship'],
];
// columns: this month (days so far) and the last 12 months
$days = array_slice($in['byDay'], 0, $in['today'], true);
$dayPeak = max(1, max($days ?: [0]));
$dayLabels = array_map(fn($k) => $in['monthName'] . ' ' . $k, array_keys($days));
$monthPeak = max(1, max($in['months']));
$monthKeys = array_keys($in['months']);
$monthLabels = array_map(fn($k) => date('M Y', strtotime($k . '-01')), $monthKeys);
$peakMonthIdx = array_search(max($in['months']), array_values($in['months']), true);
$rank = function (array $rows, string $valueKey, callable $labelFn, callable $subFn, callable $fmt, string $title, string $cls, ?callable $href = null) {
    if (!$rows) {
        return '<p class="si-empty">Nothing in this period yet.</p>';
    }
    $peak = max(1, max(array_map(fn($r) => (float) $r[$valueKey], $rows)));
    $out = '<ol class="si-rank ' . $cls . '" ' . sl_chartAttrs([['name' => $title, 'values' => array_map(fn($r) => (float) $r[$valueKey], $rows)]], array_map(fn($r) => $labelFn($r) . ' · ' . $subFn($r), $rows), $title) . '>';
    foreach ($rows as $i => $r) {
        $name = h($labelFn($r));
        if ($href) $name = '<a href="' . h($href($r)) . '">' . $name . '</a>';
        $out .= '<li><span class="si-rk">' . ($i + 1) . '</span><span class="si-rn"><b>' . $name . '</b><small>' . h($subFn($r)) . '</small><span class="si-track"><i style="width:' . max(2, round(100 * (float) $r[$valueKey] / $peak, 1)) . '%"></i></span></span><span class="si-rv">' . h($fmt($r[$valueKey])) . '</span></li>';
    }
    return $out . '</ol>';
};
?>
<section class="si" aria-labelledby="si-title">
  <div class="si-head">
    <div><span class="si-eyebrow"><i class="ic ic-sm i-spark" aria-hidden="true"></i> Shipment insights</span><h2 id="si-title" class="si-title"><?= sl_num($in['all']) ?> shipments · <?= sl_num($in['customers']) ?> customers</h2></div>
    <nav class="sl-seg boxed" aria-label="Period for status, shippers and items"><?php foreach (['30d' => '30 days', '90d' => '90 days', '12m' => '12 months', 'all' => 'All time'] as $k => $t): ?><a class="<?= $in['range'] === $k ? 'on' : '' ?>" href="<?= h(sl_url(['ins' => $k])) ?>#si-title"<?= $in['range'] === $k ? ' aria-current="true"' : '' ?>><?= h($t) ?></a><?php endforeach; ?></nav>
  </div>

  <div class="si-tiles">
    <?php foreach ($d['metrics'] as $i => $m): $t = $tiles[$i]; $pct = sl_pct($m['v'], $d['all']); $on = $d['filter'] === $t['f']; ?>
    <a class="si-tile<?= $on ? ' on' : '' ?>" href="<?= h(sl_url(['filter' => $on ? 'all' : $t['f'], 'page' => null])) ?>#as-list" style="--si-c:<?= $t['c'] ?>" title="<?= h($m['label'] . ': ' . sl_num($m['v']) . ' of ' . sl_num($d['all']) . ' (' . $pct . '%)') ?>">
      <svg class="si-ring" viewBox="0 0 44 44" aria-hidden="true"><circle cx="22" cy="22" r="18" class="bg"/><circle cx="22" cy="22" r="18" class="fg" stroke-dasharray="<?= round(113.1 * $pct / 100, 1) ?> 113.1" transform="rotate(-90 22 22)"/></svg>
      <span class="si-tl"><span class="si-tlab"><i class="ic ic-sm <?= $t['icon'] ?>" aria-hidden="true"></i><?= h($m['label']) ?></span><b><?= sl_num($m['v']) ?></b><small><?= $pct ?>% of all · <?= h($t['sub']) ?></small></span>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="si-grid2">
    <article class="si-card">
      <div class="si-ch"><div><span class="si-k">Registered in <?= h($in['monthName']) ?></span><div class="si-big"><?= sl_num($in['month']) ?> <small>packages</small></div></div>
        <?php if ($in['change'] !== null): ?><span class="si-delta <?= $in['change'] < 0 ? 'down' : '' ?>"><?= $in['change'] > 0 ? '▲ +' : ($in['change'] < 0 ? '▼ ' : '') ?><?= (int) $in['change'] ?>%</span><?php endif; ?></div>
      <p class="si-sub"><?= $in['change'] !== null ? 'Against ' . sl_num($in['lastSame']) . ' in the same ' . (int) $in['today'] . ' days of last month' : 'No packages in the same days of last month' ?> · <?= h(sl_weight($in['monthWeight'])) ?> · <?= h(sl_money($in['monthValue'])) ?> billed</p>
      <div class="si-plot">
        <span class="si-peak" style="bottom:100%">Peak <?= sl_num($dayPeak) ?>/day</span>
        <div class="si-cols blue" <?= sl_chartAttrs([['name' => 'Packages registered', 'values' => array_values($days)]], $dayLabels, 'Packages registered per day') ?> role="img" aria-label="Packages registered per day in <?= h($in['monthName']) ?>"><?php foreach ($days as $k => $v): ?><span><i style="height:<?= $v ? max(3, round(100 * $v / $dayPeak, 1)) : 0 ?>%"<?= $k === $in['today'] ? ' class="now"' : '' ?>></i></span><?php endforeach; ?></div>
      </div>
      <div class="si-axis"><span><?= h($in['monthName']) ?> 1</span><?php if ($in['today'] > 2): ?><span><?= h($in['monthName']) ?> <?= (int) ceil($in['today'] / 2) ?></span><?php endif; ?><span>Today</span></div>
    </article>
    <article class="si-card">
      <div class="si-ch"><div><span class="si-k">Last 12 months</span><div class="si-big"><?= sl_num(array_sum($in['months'])) ?> <small>packages</small></div></div><span class="si-pill">Last month <?= sl_num($in['lastFull']) ?></span></div>
      <p class="si-sub">Busiest: <b><?= h($monthLabels[$peakMonthIdx] ?? '—') ?></b> with <?= sl_num(max($in['months'])) ?> · all time <?= sl_num($in['all']) ?> packages, <?= h(sl_weight($in['allWeight'])) ?></p>
      <div class="si-plot">
        <div class="si-cols blue wide" <?= sl_chartAttrs([['name' => 'Packages registered', 'values' => array_values($in['months'])]], $monthLabels, 'Packages registered per month') ?> role="img" aria-label="Packages registered per month, last 12 months"><?php foreach (array_values($in['months']) as $i => $v): ?><span><i style="height:<?= $v ? max(3, round(100 * $v / $monthPeak, 1)) : 0 ?>%"<?= $i === 11 ? ' class="now"' : '' ?>></i></span><?php endforeach; ?></div>
      </div>
      <div class="si-axis m"><?php foreach ($monthKeys as $k): ?><span><?= h(date('M', strtotime($k . '-01'))) ?></span><?php endforeach; ?></div>
    </article>
  </div>

  <?php
  // month labels: drop one that would sit within 3 weeks of the next (the partial first month)
  $hm = $in['heatMonths'];
  $hk = array_keys($hm);
  foreach ($hk as $i => $c) if (isset($hk[$i + 1]) && $hk[$i + 1] - $c < 3) unset($hm[$c]);
  $wdNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  $wdOrder = [1, 2, 3, 4, 5, 6, 0];
  $wdPeak = max(1, max($in['weekday']));
  $wdBusy = array_search(max($in['weekday']), $in['weekday'], true);
  ?>
  <article class="si-card si-intake" aria-labelledby="si-intake-h">
    <div class="si-ch"><div><span class="si-k" id="si-intake-h">Daily intake · packages received per day</span><div class="si-big"><?= sl_num($in['heatTotal']) ?> <small>in the last 12 months</small></div></div>
      <span class="si-pill">Peak <?= sl_num($in['heatPeak']) ?> a day</span></div>
    <p class="si-sub">Busiest day: <b><?= h($in['heatPeakDay'] ?: '—') ?></b> · intake on <?= sl_num($in['heatActive']) ?> days · <?= h(sl_num($in['heatAvg'], 1)) ?> a day on days with intake · hover or tap a square for the day's count</p>
    <div class="si-intake-body">
      <div class="si-heatwrap">
        <div class="sl-heatbox">
          <div class="sl-heatdays" aria-hidden="true"><span></span><span>Mon</span><span></span><span>Wed</span><span></span><span>Fri</span><span></span></div>
          <div class="sl-heatcol">
            <div class="si-heatmonths" aria-hidden="true" style="grid-template-columns:repeat(<?= (int) $in['heatWeeks'] ?>,minmax(0,1fr))"><?php foreach ($hm as $c => $m): ?><span style="grid-column:<?= (int) $c + 1 ?> / span 3"><?= h($m) ?></span><?php endforeach; ?></div>
            <div class="sl-heat si-heat" style="grid-template-columns:repeat(<?= (int) $in['heatWeeks'] ?>,minmax(0,1fr))" role="img" aria-label="Packages registered per day over the last 12 months">
              <?php foreach ($in['heat'] as $c): ?><b title="<?= h($c['t']) ?>" class="<?= h($c['c']) ?><?= $c['today'] ? ' today' : '' ?>"></b><?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="sl-legend" style="justify-content:space-between;margin-top:8px"><span class="row g6">Quiet <b style="background:var(--heat-0)"></b><b style="background:var(--heat-1)"></b><b style="background:var(--heat-2)"></b><b style="background:var(--heat-3)"></b> Busy</span><span>Outlined square = today</span></div>
      </div>
      <div class="si-wd">
        <span class="si-k">By day of the week</span>
        <ol class="si-wdl" <?= sl_chartAttrs([['name' => 'Packages registered', 'values' => array_map(fn($w) => $in['weekday'][$w], $wdOrder)]], array_map(fn($w) => $wdNames[$w] . 'days', $wdOrder), 'Packages registered by day of the week, last 12 months') ?>>
          <?php foreach ($wdOrder as $w): ?><li class="<?= $w === $wdBusy ? 'top' : '' ?>"><span><?= $wdNames[$w] ?></span><span class="si-track"><i style="width:<?= max(1, round(100 * $in['weekday'][$w] / $wdPeak, 1)) ?>%"></i></span><b><?= sl_num($in['weekday'][$w]) ?></b></li><?php endforeach; ?>
        </ol>
        <p class="si-sub" style="font-size:12px">Busiest: <b><?= $wdNames[$wdBusy] ?>days</b> · plan staff for intake on that day</p>
      </div>
    </div>
  </article>

  <div class="si-grid3">
    <article class="si-card">
      <div class="si-ch"><div><span class="si-k">Status mix</span><div class="si-mid"><?= sl_num($in['rangeTotal']) ?> <small>shipments · <?= h(strtolower($in['rangeLabel'])) ?></small></div></div></div>
      <?= $rank($in['status'], 'n', fn($r) => $r['label'], fn($r) => sl_pct($r['n'], $in['rangeTotal']) . '% of the period', fn($v) => sl_num($v), 'Shipments by status', 'mint', fn($r) => $r['id'] ? sl_url(['status' => $r['id'], 'filter' => 'all', 'page' => null]) . '#as-list' : '#as-list') ?>
    </article>
    <article class="si-card">
      <div class="si-ch"><div><span class="si-k">Top shippers</span><div class="si-mid"><?= h($in['rangeLabel']) ?></div></div></div>
      <?= $rank($in['shippers'], 'n', fn($r) => $r['name'], fn($r) => ($r['locker'] !== '' ? 'Locker ' . $r['locker'] . ' · ' : '') . sl_weight($r['w']) . ' · ' . sl_money($r['v']), fn($v) => sl_num($v), 'Shipments per customer', 'violet', fn($r) => sl_route('CustomerProfile', ['id' => $r['id']])) ?>
    </article>
    <article class="si-card">
      <div class="si-ch"><div><span class="si-k">Most shipped items</span><div class="si-mid"><?= h($in['rangeLabel']) ?></div></div></div>
      <?= $rank($in['items'], 'q', fn($r) => $r['label'], fn($r) => 'in ' . sl_num($r['n']) . ' shipment' . ($r['n'] === 1 ? '' : 's'), fn($v) => sl_num($v) . ' pcs', 'Items shipped (quantity)', 'orange', fn($r) => sl_url(['q' => $r['label'], 'filter' => 'all', 'page' => null]) . '#as-list') ?>
    </article>
  </div>

  <article class="si-card si-ai" id="si-ai">
    <div class="si-ch"><div class="row g8" style="align-items:center"><span class="si-aiic"><i class="ic ic-sm i-spark" aria-hidden="true"></i></span><div><span class="si-k"><?= h(cdp_kingName()) ?> · shipment insight</span><div class="si-mid">Ask about your shipments in plain words</div></div></div>
      <?php if (!empty($d['aiOn'])): ?><button type="button" class="sl-btn sm" data-si-ask=""><i class="ic ic-sm i-spark" aria-hidden="true"></i>Get today's brief</button><?php endif; ?></div>
    <?php if (!empty($d['aiOn'])): ?>
    <div class="si-chips"><?php foreach (['What needs my attention today?', 'Which customers are growing or slowing down?', 'Which items should we plan space for next month?', 'How does this month compare with the last 12 months?', 'Who owes the most and what should we chase?'] as $c): ?><button type="button" data-si-ask="<?= h($c) ?>"><?= h($c) ?></button><?php endforeach; ?></div>
    <form class="si-askf" data-si-form><input class="sl-in" name="q" maxlength="400" placeholder="Ask anything about shipments, e.g. which customer shipped the most laptops?" aria-label="Question about shipments"><button class="sl-btn sm" type="submit">Ask</button></form>
    <div class="si-out" data-si-out aria-live="polite"><p class="mut sm">The brief reads the figures on this page (same branch scope) and suggests what to do next. Answers come from the AI set up on Tools > King.</p></div>
    <?php else: ?>
    <p class="mut sm" style="margin:0"><?= h(cdp_kingName()) ?> is not set up yet. A super admin turns it on in Tools > King; the shipment brief and questions then appear here.</p>
    <?php endif; ?>
  </article>
</section>
<?php if (!empty($d['aiOn'])): ?>
<script>
(function () {
  var box = document.getElementById('si-ai'), out = box.querySelector('[data-si-out]'), range = <?= json_encode($in['range']) ?>, busy = false;
  function esc(s) { return s.replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function md(t) {
    var html = '', list = false;
    esc(t).split(/\n/).forEach(function (line) {
      line = line.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').trim();
      var li = line.match(/^(?:[-*•]|\d+[.)])\s+(.*)$/), h = line.match(/^#{1,4}\s+(.*)$/);
      if (li) { if (!list) { html += '<ul>'; list = true; } html += '<li>' + li[1] + '</li>'; return; }
      if (list) { html += '</ul>'; list = false; }
      if (h) html += '<h4>' + h[1] + '</h4>'; else if (line) html += '<p>' + line + '</p>';
    });
    return html + (list ? '</ul>' : '');
  }
  function ask(q, fresh) {
    if (busy) return; busy = true;
    out.innerHTML = '<p class="si-think"><span></span><span></span><span></span> ' + (q ? 'Thinking about: ' + esc(q) : 'Reading your shipments…') + '</p>';
    SL.post('ajax/sl/shipment_insights_ai.php', { range: range, q: q || '', fresh: fresh ? 1 : 0 }).then(function (j) {
      if (!j.success) { out.innerHTML = '<p class="si-err">' + esc(j.message || 'No answer. Please try again.') + '</p>'; return; }
      out.innerHTML = (q ? '<p class="si-q">' + esc(q) + '</p>' : '') + '<div class="si-md">' + md(j.text) + '</div><p class="mut sm si-meta">' + (j.cached ? 'From ' : 'Generated at ') + esc(j.at) + ' · AI can be wrong; check the figures above · <button type="button" class="si-link" data-si-again>Refresh</button></p>';
      out.querySelector('[data-si-again]').addEventListener('click', function () { ask(q, true); });
    }).catch(function (e) { out.innerHTML = '<p class="si-err">' + esc(e.message || 'Network error') + '</p>'; }).finally(function () { busy = false; });
  }
  box.addEventListener('click', function (e) { var b = e.target.closest('[data-si-ask]'); if (b) ask(b.getAttribute('data-si-ask')); });
  box.querySelector('[data-si-form]').addEventListener('submit', function (e) { e.preventDefault(); var v = this.q.value.trim(); if (v) ask(v); });
})();
</script>
<?php endif; ?>
