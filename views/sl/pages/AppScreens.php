<?php
/** App screens: the design's phone screens grouped as on the canvas, each with its live page. */
$groups = [];
foreach ($d['rows'] as [$sec, $name, $label, $php, $role, $live]) { $groups[$sec][] = compact('name', 'label', 'php', 'role', 'live'); }
$total = count($d['rows']); $live = count(array_filter($d['rows'], fn($r) => $r[5]));
$roleLabel = ['staff' => 'Staff app', 'customer' => 'Customer app', 'rider' => 'Rider app', 'public' => 'Everyone'];
$dev = $d['device'];
?>
<div class="mxp wide">
<?= sl_mxHead('App screens', $live . ' of ' . $total . ' design screens have a live page', sl_route('SettingsHub')) ?>
<section class="mx-k c2">
<?= sl_mxKpi('phone', 'Phone screens', (string) $total, 'from the design') ?>
<?= sl_mxKpi('check', 'Live pages', (string) $live, $live === $total ? 'all built' : ($total - $live) . ' to build', $live === $total ? 'up' : 'down') ?>
</section>
<section class="mx-card mx-list"><?= sl_mxRow($dev['form'] === 'desktop' ? 'monitor' : 'phone', 'You are on ' . $dev['label'], $dev['standalone'] ? 'Installed app' : 'Browser · the app installs from ' . $dev['browserLabel']) ?></section>
<p class="mx-note">Each page below opens as a phone screen on a phone, full screen in the installed app, and as a wider page on a computer. Staff, customer and rider screens open in that person's own app; open them signed in as that kind of account.</p>
<div class="mxp-grid">
<?php foreach ($groups as $sec => $items): ?>
<div class="mxp-col">
<?= sl_mxSec($sec ?: 'Screens', count($items) . ' screens') ?>
<section class="mx-card mx-list">
<?php foreach ($items as $it):
  $right = $it['live'] ? '<span class="sl-pill delivered">Live</span>' : '<span class="sl-pill pending">Not built</span>'; ?>
<?= sl_mxRow($it['role'] === 'rider' ? 'scooter' : ($it['role'] === 'customer' ? 'user' : ($it['role'] === 'public' ? 'globe' : 'grid')), $it['label'], $roleLabel[$it['role']] . ' · ' . $it['php'], $right, $it['live'] ? $it['php'] : '') ?>
<?php endforeach; ?>
</section>
</div>
<?php endforeach; ?>
</div>
</div>
