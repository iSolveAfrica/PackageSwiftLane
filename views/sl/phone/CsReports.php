<?php
/**
 * M-CsReports: service reports on a phone. Same $d as the web page (sl_csReportsData); the period segments are
 * the web page's ?days=7|30|90. Tickets per day: last 7 days (views/sl/data/phone_desk.php).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/views/sl/data/phone_desk.php';
$r = $d['report'] ?? null;
$k = $d['kpi'] ?? [];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-desk.css')) ?>">
<?= sl_phoneTop('Service reports', '', ['back' => sl_route('CsInbox')], $r ? sl_phoneIcon('download', 'Export CSV', $d['exportHref']) : '') ?>
<main class="mx-body">
<nav class="mx-seg" aria-label="Period"><?php foreach ([7 => 'Week', 30 => 'Month', 90 => 'Quarter'] as $n => $l): ?><a class="<?= $d['days'] === $n ? 'on' : '' ?>" href="<?= h(sl_url(['days' => $n])) ?>"<?= $d['days'] === $n ? ' aria-current="page"' : '' ?>><?= h($l) ?></a><?php endforeach; ?></nav>
<?php if (!$r): ?>
<?= sl_mxEmpty('chart', 'No reports yet', 'The helpdesk tables are missing; ask an administrator to run the support migration.') ?>
<?php else: ?>
<section class="mx-k c2">
<?= sl_mxKpi('ticket', 'Tickets', $k['tickets'], 'last ' . $d['days'] . ' days') ?>
<?= sl_mxKpi('clock', 'First reply', $k['first'], $k['firstSub']) ?>
<?= sl_mxKpi('check', 'Solved', $k['solvedPct'] . '%', $k['solved'] . ' tickets') ?>
<?= sl_mxKpi('star', 'Happy', $k['csatN'] ? $k['csatPct'] . '%' : '—', $k['csatN'] ? $k['csatN'] . ' ratings · ' . $k['csat'] . ' / 5' : 'No ratings yet') ?>
</section>
<?php $bars = sl_pdTicketsPerDay(7); $max = max(1, max(array_column($bars, 'n'))); ?>
<section class="mx-card mx-bars"><div class="mx-bh"><b>Tickets per day</b><small>Last 7 days</small></div><div class="mx-bc" role="img" aria-label="Tickets per day: <?= h(implode(', ', array_map(fn($b) => $b['l'] . ' ' . $b['n'], $bars))) ?>"><?php foreach ($bars as $b): ?><span class="<?= $b['now'] ? 'now' : '' ?>"><i style="height:<?= max(3, (int) round($b['n'] / $max * 100)) ?>%"></i><small><?= h($b['l']) ?></small></span><?php endforeach; ?></div></section>
<?= sl_mxSec('Top reasons') ?>
<?php $tot = max(1, array_sum(array_column($d['topics'], 'n'))); ?>
<?php if ($d['topics']): ?>
<section class="mx-card mx-mts"><?php foreach (array_slice($d['topics'], 0, 5) as $tp): $p = (int) round($tp['n'] / $tot * 100); ?><div class="mx-mt"><div><span><?= h($tp['label']) ?></span><b><?= $p ?>%</b></div><span class="mx-trk"><i class="<?= stripos($tp['label'], 'claim') !== false || stripos($tp['label'], 'complaint') !== false ? 'warn' : '' ?>" style="width:<?= $p ?>%"></i></span></div><?php endforeach; ?></section>
<?php else: ?>
<?= sl_mxEmpty('chart', 'No tickets in this period') ?>
<?php endif; ?>
<?php endif; ?>
</main>
