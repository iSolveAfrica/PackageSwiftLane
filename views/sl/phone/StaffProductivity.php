<?php
/**
 * M-StaffProd: staff productivity on a phone (staff_productivity.php). Same $d as the web page
 * (views/sl/data/staff_activity.php sl_staffProductivityData): period, sort, ranking, team chart by day.
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
$slPhone['nav'] = 'staff';
$sorts = ['active_seconds' => 'Active time', 'packages_added' => 'Packages created', 'packages_edited' => 'Packages edited', 'utilisation' => 'Utilisation', 'days_worked' => 'Days worked', 'name' => 'Name'];
$hours = array_values($d['chartSeries'][0]['values'] ?? []);
$labels = array_values($d['chartLabels'] ?? []);
if (count($hours) > 14) { $hours = array_slice($hours, -14); $labels = array_slice($labels, -14); }
$mx = max(0.01, max($hours ?: [0]));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop('Staff productivity', $d['label'] . ' · ' . sl_date($d['from'], 'j M') . ' – ' . sl_date($d['to'], 'j M'), ['back' => 'menu.php'], $d['canExport'] ? sl_phoneIcon('download', 'Export (CSV)', 'ajax/reports/staff_productivity_export_ajax.php?from=' . rawurlencode($d['from']) . '&to=' . rawurlencode($d['to'])) : '') ?>
<main class="mx-body">
<nav class="mx-seg" aria-label="Period">
<?php foreach ($d['periods'] as $p): ?>
  <a class="<?= $p['k'] === $d['period'] ? 'on' : '' ?>" href="<?= h(sl_url(['period' => $p['k']])) ?>"<?= $p['k'] === $d['period'] ? ' aria-current="true"' : '' ?>><?= h($p['label']) ?></a>
<?php endforeach; ?>
</nav>
<form method="get" class="mx-card mx-form">
  <input type="hidden" name="period" value="<?= h($d['period']) ?>">
  <label class="mx-f"><small>Rank by</small><select class="mx-in" name="sort" onchange="this.form.submit()"><?php foreach ($sorts as $k => $l): ?><option value="<?= h($k) ?>"<?= $k === $d['sort'] ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
  <noscript><button type="submit" class="mx-btn">Sort</button></noscript>
</form>
<section class="mx-k c2">
<?php foreach (array_slice($d['kpis'], 0, 4) as $k): ?>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-chart" aria-hidden="true"></i></span><small><?= h($k['l']) ?></small></div><b><?= h((string) $k['v']) ?></b><em><?= h($k['s']) ?></em></div>
<?php endforeach; ?>
</section>
<div class="mx-sec"><h2>Ranking</h2></div>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): ?>
  <a class="mx-row" href="<?= h(sl_route('StaffActivity', ['id' => $r['id']])) ?>"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small>Days <?= (int) $r['days'] ?> · <?= (int) $r['created'] ?> created · <?= (int) $r['edited'] ?> edited · <?= h($r['used']) ?> used</small></span><span class="mx-rr"><b><?= h($r['active']) ?></b><small>active</small></span></a>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('chart', 'No staff activity in this period') ?>
<?php endif; ?>
<?php if ($hours): ?>
<section class="mx-card mx-bars" role="img" aria-label="Active hours per day for the team, <?= h($d['chartSub']) ?>">
  <div class="mx-bh"><b>Active hours per day (team)</b></div>
  <div class="mx-bc"><?php foreach ($hours as $i => $v): ?><span class="<?= $i === count($hours) - 1 ? 'now' : '' ?>" title="<?= h(sl_date($labels[$i] ?? '', 'D j M') . ': ' . $v . ' h') ?>"><i style="height:<?= max(6, (int) round($v / $mx * 100)) ?>%"></i><small><?= h(sl_date($labels[$i] ?? '', count($hours) > 7 ? 'j' : 'D')) ?></small></span><?php endforeach; ?></div>
</section>
<?php endif; ?>
</main>
