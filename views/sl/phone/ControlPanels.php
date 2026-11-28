<?php
/** M-Panels: Control panels on a phone. $d = views/sl/data/control_panels.php. */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['tab'] = 'Menu';
$attn = array_values(array_filter($d['attn'] ?? [], fn($a) => !empty($a['mix'])));
$mixIcon = ['At origin' => 'clock', 'In transit' => 'plane', 'Arrived / ready' => 'warehouse', 'Held' => 'alert', 'Out now' => 'truck', 'Ready to hand out' => 'box', 'Pickups booked' => 'calendar', 'No rider' => 'alert',
    'Solved by AI' => 'check', 'Handed to staff' => 'users', 'Staff questions' => 'message', 'Unanswered' => 'alert'];
$trk = ['m0' => '', 'm1' => '', 'm2' => 'ok', 'm3' => 'bad'];
$dayL = [];
for ($i = 6; $i >= 0; $i--) $dayL[] = substr(date('D', strtotime("-$i days")), 0, 1);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<?= sl_phoneTop('Control panels', 'Updated ' . ($d['updated'] ?? date('H:i')), ['back' => 'menu.php'], sl_phoneIcon('search', 'Search', sl_route('LockerSearch'))) ?>
<main class="mx-body" data-mx-scope>
<?php if ($attn): ?>
<div class="mx-seg" data-mx-seg aria-label="Panel">
<?php foreach ($attn as $i => $a): ?><button type="button" data-mx-panel="p<?= $i ?>" class="<?= $i === 0 ? 'on' : '' ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>"><?= h($a['l']) ?></button><?php endforeach; ?>
</div>
<?php foreach ($attn as $i => $a): $tot = max(1, array_sum(array_column($a['mix'], 1))); $peak = max(1, max($a['bars'] ?: [0])); ?>
<div data-mx-panel-of="p<?= $i ?>"<?= $i ? ' hidden' : '' ?>>
<section class="mx-k c2">
<?php foreach ($a['mix'] as $k => [$ml, $mv, $mc]): ?>
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-<?= h($mixIcon[$ml] ?? 'box') ?>" aria-hidden="true"></i></span><small><?= h($ml) ?></small></div><b><?= h(sl_num($mv)) ?></b><?php if ($k === 0): ?><em class="up"><?= h($a['badge']) ?></em><?php endif; ?></div>
<?php endforeach; ?>
</section>
<div class="mx-sec"><h2><?= h($a['l']) ?> by stage</h2><a href="<?= h($a['href']) ?>">Open</a></div>
<section class="mx-card mx-mts">
<div class="mx-mt"><div><span><?= h(ucfirst($a['unit'])) ?></span><b><?= h(sl_num($a['n'])) ?></b></div><span class="mx-trk"><i class="" style="width:100%"></i></span></div>
<?php foreach ($a['mix'] as [$ml, $mv, $mc]): ?>
<div class="mx-mt"><div><span><?= h($ml) ?></span><b><?= h(sl_num($mv)) ?></b></div><span class="mx-trk"><i class="<?= h($trk[$mc] ?? '') ?>" style="width:<?= (int) sl_pct($mv, $tot) ?>%"></i></span></div>
<?php endforeach; ?>
</section>
<section class="mx-card mx-bars"><div class="mx-bh"><b><?= h($a['footL']) ?></b><small><?= h($a['footR']) ?></small></div><div class="mx-bc" role="img" aria-label="<?= h($a['barsName'] . ' per day, last 7 days: ' . implode(', ', $a['bars'])) ?>">
<?php foreach (array_values($a['bars']) as $k => $v): ?><span class="<?= $k === 6 ? 'now' : '' ?>"><i style="height:<?= max(6, (int) round($v / ($peak * 1.15) * 100)) ?>%"></i><small><?= h($dayL[$k] ?? '') ?></small></span><?php endforeach; ?>
</div></section>
<?php if (!empty($a['why'])): ?><a class="mx-ban info" href="<?= h($a['whyHref'] ?: $a['href']) ?>"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><?= h($a['why']) ?></span></a><?php endif; ?>
<?php foreach ($a['insights'] ?? [] as [$tip, $tipHref]): ?><a class="mx-ban info" href="<?= h($tipHref) ?>"><i class="ic ic-sm i-ai" aria-hidden="true"></i><span><?= h($tip) ?></span></a><?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php if (!empty($d['health'])): ?>
<div class="mx-sec"><h2>System health</h2></div>
<section class="mx-card mx-list">
<?php foreach ($d['health'] as $hh): ?>
<a class="mx-row" href="<?= h($hh['href']) ?>"><span class="mx-li"><i class="ic <?= h($hh['i']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($hh['t']) ?></b><small><?= h($hh['x']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= $hh['c'] === 'b' ? 'overdue' : ($hh['c'] === 'w' ? 'pending' : 'delivered') ?>"><?= h($hh['s']) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
<div class="mx-sec"><h2>All panels</h2></div>
<?php foreach ($d['sections'] ?? [] as $si => $s): ?>
<details class="mx-grp"<?= $si === 0 ? ' open' : '' ?>><summary><span><?= h($s['t']) ?></span><small><?= count($s['rows']) ?></small><i class="ic ic-sm i-down" aria-hidden="true"></i></summary><section class="mx-card mx-list">
<?php foreach ($s['rows'] as $r): ?>
<a class="mx-row" href="<?= h($r['href']) ?>"><span class="mx-li"><i class="ic <?= h($r['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r['label']) ?></b><?php if ($r['sub'] !== ''): ?><small><?= h($r['sub']) ?></small><?php endif; ?></span><?php if ($r['n'] !== ''): ?><span class="mx-cnt"><?= h($r['n']) ?></span><?php endif; ?><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php endforeach; ?>
</section></details>
<?php endforeach; ?>
</main>
