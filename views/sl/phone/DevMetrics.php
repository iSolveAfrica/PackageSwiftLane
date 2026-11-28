<?php
/** M-DevMetrics: host and request metrics for a range. Data: sl_dvxMetricsData (same as the web DevMetrics, ?range=). */
require_once CDP_ROOT . '/views/sl/data/dev_phone.php';
$slPhone['nav'] = 'dev';
$slPhone['tab'] = 'Console';
$range = (string) ($d['range'] ?? '24h');
$tile = [];
foreach ($d['tiles'] ?? [] as $t) {
    $tile[$t['k']] = $t;
}
$chart = [];
foreach ($d['charts'] ?? [] as $ch) {
    $chart[$ch['id']] = $ch;
}
$vals = fn(string $id, int $i = 0) => $chart[$id]['paths'][$i]['values'] ?? [];
$tiles = [
    ['cpu', 'cpu', 'CPU load', $vals('cpu')],
    ['mem', 'server', 'Memory', $vals('mem')],
    ['rpm', 'activity', 'Requests/min', $vals('traffic')],
    ['lat', 'clock', 'Response p95', $vals('latency', 1)],
];
$pts = sl_phDevSeries($range)['points'] ?? [];
$bars = sl_phDevBars(array_column($pts, 'p95'), array_column($pts, 'label'), 7, 'max');
$slow = array_slice($d['slow'] ?? [], 0, 5);
$slowMax = $slow ? max(1, max(array_map(fn($r) => (int) ($r['p95'] ?? $r['avg']), $slow))) : 1;
?>
<?= sl_phoneTop('Metrics', (string) ($d['host'] ?? ''), ['back' => sl_route('DevOverview')], sl_phDevMore()) ?>
<main class="mx-body">
<div class="mx-seg" role="group" aria-label="Range"><form method="get" style="display:contents">
<?php foreach ($d['ranges'] ?? [] as $r): ?><button type="submit" name="range" value="<?= h($r['label']) ?>" class="<?= h($r['cls']) ?>" aria-pressed="<?= $r['cls'] ? 'true' : 'false' ?>"><?= h(str_replace(['h', 'd'], [' h', ' d'], $r['label'])) ?></button><?php endforeach; ?>
</form></div>
<section class="mx-k c2">
<?php foreach ($tiles as [$k, $icon, $label, $sp]): $t = $tile[$k] ?? ['v' => '—', 'u' => '', 'sub' => '']; ?>
<div class="mx-kt" title="<?= h($t['sub']) ?>"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-<?= h($icon) ?>" aria-hidden="true"></i></span><small><?= h($label) ?></small></div><b><?= h($t['v'] . ($t['v'] !== '—' && in_array($t['u'], ['%'], true) ? $t['u'] : ($k === 'lat' && $t['v'] !== '—' ? ' ms' : ''))) ?></b><?= sl_phDevSparkHtml(sl_phDevSpark($sp)) ?></div>
<?php endforeach; ?>
</section>
<section class="mx-card mx-bars"><div class="mx-bh"><b>Response time p95 (ms)</b></div>
<?php if ($bars): ?><?= sl_phDevBarsHtml($bars, fn($v) => sl_num($v) . ' ms') ?><?php else: ?><p style="margin:8px 0 0;color:var(--muted);font-size:13px">No request recorded in this range yet.</p><?php endif; ?>
</section>
<?= sl_mxSec('Slow pages', '', '') ?>
<section class="mx-card mx-mts">
<?php foreach ($slow as $i => $r): $v = (int) ($r['p95'] ?? $r['avg']); ?>
<div class="mx-mt"><div><span><?= $i === 0 ? 'Slowest: ' : '' ?><?= h($r['route']) ?></span><b><?= h(sl_dvxMs($v)) ?></b></div><span class="mx-trk"><i class="<?= $v >= 1000 ? 'warn' : ($v < 300 ? 'ok' : '') ?>" style="width:<?= max(2, (int) round($v / $slowMax * 100)) ?>%"></i></span></div>
<?php endforeach; ?>
<?php if (!$slow): ?><span style="color:var(--muted);font-size:13px">No request recorded in <?= h($d['routeWindow'] ?? $range) ?>.</span><?php endif; ?>
</section>
<?php if (!empty($tile['db'])): ?>
<section class="mx-card mx-kv">
<div><span>Database</span><b><?= h($tile['db']['v']) ?></b></div>
<div><span>Connections</span><b><?= h($tile['db']['sub']) ?></b></div>
<?php if (!empty($tile['disk'])): ?><div><span>Disk</span><b><?= h($tile['disk']['v'] . $tile['disk']['u']) ?></b></div><?php endif; ?>
<?php if (!empty($tile['err'])): ?><div><span>Error rate</span><b><?= h($tile['err']['v'] . $tile['err']['u']) ?></b></div><?php endif; ?>
</section>
<?php endif; ?>
</main>
