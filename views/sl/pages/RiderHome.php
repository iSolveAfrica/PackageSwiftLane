<?php
/**
 * Rider overview. Data: views/sl/data/rider_home.php (sl_riderHomeData). Styles: assets/swiftlane/overview-hero.css.
 */
if (!isset($d)) { $d = []; }
$ringMax = max(1, (int) $d['assignedMonth'], (int) $d['month']);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/overview-hero.css')) ?>">
<section class="ovh-me ovh-client" aria-label="Your day">
  <div class="ovh-me-top">
    <?= $slPic(64, false) ?>
    <div class="col grow" style="min-width:0">
      <span class="ovh-eyebrow"><?= h($d['greet']) ?></span>
      <h1 class="ovh-name">Ready to ride, <?= h($d['first']) ?></h1>
      <span class="mut sm"><?= h($slSub ?? 'Rider') ?> · <?= sl_num($d['week']) ?> delivered in the last 7 days</span>
    </div>
    <a class="sl-btn sm" href="<?= sl_route('DriverApp') ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Open Rider app</a>
  </div>
  <div class="ovh-me-mid">
    <div class="ovh-ring" style="--p:<?= sl_pct($d['month'], $ringMax) ?>" role="img" aria-label="<?= (int) $d['month'] ?> delivered this month of <?= (int) $d['assignedMonth'] ?> assigned"><b><?= (int) $d['month'] ?></b><span>delivered<br>this month</span></div>
    <dl class="ovh-stats">
      <div><dt>To collect</dt><dd><b><?= sl_num($d['collect']) ?></b> parcels</dd></div>
      <div><dt>On route</dt><dd><b><?= sl_num($d['onRoute']) ?></b> out for delivery</dd></div>
      <div><dt>Delivered today</dt><dd><b class="<?= $d['today'] ? 'ok' : '' ?>"><?= sl_num($d['today']) ?></b> handed over</dd></div>
      <div><dt>Pickups</dt><dd><b><?= sl_num($d['pickups']) ?></b> waiting</dd></div>
    </dl>
  </div>
  <div class="ovh-me-bars">
    <div class="row between sm"><span class="mut">My deliveries, 14 days</span></div>
    <div class="ovh-bars violet" role="img" aria-label="Deliveries per day, last 14 days"><?php $nb = count($d['bars']); foreach ($d['bars'] as $i => $b): ?><i class="<?= $i === $nb - 1 ? 'now' : '' ?>" style="<?= h($b['s']) ?>" title="<?= h($b['t']) ?>"></i><?php endforeach; ?></div>
  </div>
  <div class="ovh-chips">
    <a href="<?= h(sl_route('DriverApp')) ?>"><i class="ic ic-sm i-box" aria-hidden="true"></i>My jobs <b><?= sl_num($d['collect'] + $d['onRoute']) ?></b></a>
    <a href="<?= h(sl_route('DriverApp', ['tab' => 'route'])) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>My route</a>
    <a href="<?= h(sl_route('DriverApp', ['tab' => 'pay'])) ?>"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Earnings</a>
    <a href="<?= h(sl_route('Notifications')) ?>"><i class="ic ic-sm i-bell" aria-hidden="true"></i>Alerts</a>
  </div>
</section>
<?php if (!empty($d['tracking']['items'])): $tc = $d['tracking']; $tcTitle = 'My parcels'; $tcNoun = 'shipments'; $tcExpand = sl_route('DriverApp'); ?>
<?php include __DIR__ . '/partials/track_console.php'; ?>
<?php else: ?>
<section class="sl-card"><div class="sl-empty"><span class="sl-tile"><i class="ic i-truck" aria-hidden="true"></i></span>Nothing assigned to you right now. New jobs appear here and in the Rider app.</div></section>
<?php endif; ?>
