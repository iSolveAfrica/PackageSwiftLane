<?php
/**
 * M-Exec: management center. Same $d as the web page (views/sl/data/exec.php sl_execData; GET mode, per).
 * Money values arrive already hidden ('••••') for roles that may not see them.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$sp = function (array $bars): string {
    if (!$bars) return '';
    $out = '<div class="mx-sp" aria-hidden="true">';
    foreach ($bars as $i => $b) $out .= '<i class="' . ($i === count($bars) - 1 ? 'now' : '') . '" style="' . h($b['s']) . '"></i>';
    return $out . '</div>';
};
$num = fn(string $s) => (float) preg_replace('/[^0-9.]/', '', $s);
$brMax = max(1, ...array_map(fn($b) => $num($b['s']), $d['br'] ?: [['s' => '0']]));
$nb = count($d['bars']);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-centres.css')) ?>">
<?= sl_phoneTop('Management center', $d['modeL'], ['back' => 'menu.php'], sl_phoneIcon('file', 'All reports', sl_route('Reports'))) ?>
<main class="mx-body">
<div class="mx-seg" role="tablist" aria-label="Period">
<?php foreach ($d['pers'] as $p): ?>  <a role="tab" aria-selected="<?= $p['cls'] === 'on' ? 'true' : 'false' ?>" class="<?= h($p['cls']) ?>" href="<?= h(sl_url(['per' => $p['f']])) ?>"><?= h($p['label']) ?></a>
<?php endforeach; ?>
</div>
<div class="mx-chips"><?php foreach ($d['modes'] as $m): ?><a class="<?= h($m['cls']) ?>" href="<?= h(sl_url(['mode' => $m['f']])) ?>"<?= $m['cls'] === 'on' ? ' aria-current="true"' : '' ?>><?= h($m['label']) ?></a><?php endforeach; ?></div>
<section class="mx-k c2">
<?php foreach (array_slice($d['kpis'], 0, 4) as $k): $tr = str_starts_with((string) $k['s'], '+') || stripos((string) $k['s'], 'up') === 0 ? 'up' : (str_starts_with((string) $k['s'], '-') || stripos((string) $k['s'], 'down') === 0 ? 'down' : ''); ?>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm <?= h($k['icon']) ?>" aria-hidden="true"></i></span><small><?= h($k['l']) ?></small></div><b><?= h($k['v']) ?></b><em class="<?= $tr ?>"><?= h($k['s']) ?></em><?= $sp($k['sp']) ?></div>
<?php endforeach; ?>
</section>
<section class="mx-card mx-bars">
  <div class="mx-bh"><b><?= h($d['chartT']) ?></b><small>Peak <?= h($d['peak']) ?></small></div>
  <div class="mx-bc"><?php foreach ($d['bars'] as $i => $b): ?><span class="<?= $i === $nb - 1 ? 'now' : '' ?>" title="<?= h($b['t']) ?>"><i style="<?= h($b['h']) ?>"></i><small><?= $i === $nb - 1 ? 'Now' : ($i % 3 === 0 ? '-' . ($nb - 1 - $i) . 'w' : '') ?></small></span><?php endforeach; ?></div>
</section>
<?= sl_mxSec('By branch') ?>
<?php if ($d['br']): ?>
<section class="mx-card mx-mts">
<?php foreach ($d['br'] as $b): ?>
  <div class="mx-mt"><div><span><?= h($b['n']) ?> · <?= h($b['s']) ?> shipments</span><b><?= h($b['r']) ?></b></div><span class="mx-trk"><i class="ok" style="width:<?= (int) round($num($b['s']) / $brMax * 100) ?>%"></i></span></div>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('warehouse', 'No branch activity in this period') ?>
<?php endif; ?>
<?= sl_mxSec('What changed') ?>
<section class="mx-card mx-list pc-wrap">
<?php foreach ($d['watch'] as $w): ?>
<a class="mx-row" href="<?= h($w['href']) ?>"><span class="mx-li"><i class="ic i-<?= $w['d'] === '' ? 'check' : 'alert' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($w['t']) ?></b><small><?= h($w['s']) ?></small></span><?php if ($w['d'] !== ''): ?><span class="mx-rr"><span class="sl-pill <?= $w['d'] === 'e' ? 'overdue' : 'onway' ?>">Watch</span></span><?php else: ?><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i><?php endif; ?></a>
<?php endforeach; ?>
<?php foreach ($d['ins'] as $i): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-spark" aria-hidden="true"></i></span><span class="mx-rm"><small><?= h($i) ?></small></span></div>
<?php endforeach; ?>
</section>
<?php if ($d['reps']): ?>
<?= sl_mxSec('Reports for this period', 'More', sl_route('Reports')) ?>
<section class="mx-card mx-list">
<?php foreach ($d['reps'] as $r): ?>
<a class="mx-row" href="<?= h($r['href']) ?>"><span class="mx-li"><i class="ic i-file" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r['t']) ?></b><small><?= h($r['s']) ?></small></span><span class="mx-rr"><small><?= h($r['f']) ?></small></span></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
</main>
