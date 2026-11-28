<?php
/**
 * Customer dashboard, lower row: the latest shipments (left) and one card that tracks a parcel, shows the
 * latest parcel's progress and updates, and links to help (right). Both cards share one height, no filler.
 * Data: sl_clientDashboardData() (rows, latest, latestSteps, tl). Styles: assets/swiftlane/pages/ClientDashboard.css (.cdl).
 */
$rows = (array) sl_get($d, 'rows', []);
$latest = sl_get($d, 'latest');
$steps = sl_get($d, 'latestSteps');
$tl = (array) sl_get($d, 'tl', []);
?>
<section class="cdl" aria-label="My shipments and tracking">
  <article class="cdl-card cdl-list sl-desktop-shipments">
    <header class="cdl-h"><h2><i class="ic ic-sm i-box" aria-hidden="true"></i>My shipments</h2><a href="<?= h(sl_route('MyParcels')) ?>" class="cdl-more">View all <i class="ic ic-sm i-right" aria-hidden="true"></i></a></header>
    <?php if ($rows): ?>
    <div class="cdl-rows">
      <?php foreach ($rows as $r): $air = ($r['mode'] ?? 'Air') === 'Air'; $sub = array_filter([$r['date'] ?? '', ($r['from'] ?? '—') !== '—' ? $r['from'] : '', ($r['w'] ?? '—') !== '—' ? $r['w'] : '']); ?>
      <a class="cdl-row" href="<?= h($r['href']) ?>">
        <span class="cdl-mode <?= $air ? 'air' : 'sea' ?>" title="<?= $air ? 'Air' : 'Sea' ?>"><i class="ic ic-sm <?= $air ? 'i-plane' : 'i-ship' ?>" aria-hidden="true"></i></span>
        <span class="cdl-id"><b class="mono"><?= h($r['id']) ?></b><small><?= h(implode(' · ', $sub) ?: ($air ? 'Air' : 'Sea')) ?></small></span>
        <span class="cdl-pg" aria-hidden="true"><i style="<?= h($r['pg'] ?? 'width:0%') ?>"></i></span>
        <span class="cdl-c mono"><?= h($r['c'] ?? '—') ?></span>
        <span class="sl-pill <?= h($r['cls'] ?? '') ?>"><?= h($r['st'] ?? '') ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="cdl-empty"><span class="sl-tile"><i class="ic i-box" aria-hidden="true"></i></span><b>No shipments yet</b><span class="mut sm">Use your locker address above when you shop online.</span></div>
    <?php endif; ?>
  </article>

  <article class="cdl-card cdl-track" aria-label="Track a parcel">
    <header class="cdl-h"><h2><i class="ic ic-sm i-track" aria-hidden="true"></i>Track a parcel</h2><a class="cdl-more" href="<?= h(sl_route('PublicTracking')) ?>">Full tracking <i class="ic ic-sm i-right" aria-hidden="true"></i></a></header>
    <form class="cdl-search" method="get" action="<?= h(sl_route('PublicTracking')) ?>"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="order_track" aria-label="Tracking number" class="mono" placeholder="Tracking number" value="<?= h($latest['id'] ?? '') ?>" required><button class="sl-btn sm" type="submit">Track</button></form>
    <?php if ($latest): ?>
    <div class="cdl-latest">
      <div class="cdl-lh"><a class="mono" href="<?= h($latest['href']) ?>"><?= h($latest['id']) ?></a><span class="sl-pill <?= h($latest['cls']) ?>"><?= h($latest['st']) ?></span></div>
      <?php if ($steps): ?>
      <ol class="cdl-steps" style="--pct:<?= (int) $steps['pct'] ?>%">
        <?php foreach ($steps['steps'] as $s): ?><li class="<?= $s['done'] ? 'done' : '' ?> <?= h($s['cls']) ?>"><i><i class="ic ic-sm <?= $s['done'] && !$s['cls'] ? 'i-check' : h($s['i']) ?>" aria-hidden="true"></i></i><span><?= h($s['l']) ?></span></li><?php endforeach; ?>
      </ol>
      <?php endif; ?>
      <small class="mut"><?= h(trim((($latest['from'] ?? '—') !== '—' ? $latest['from'] . ' · ' : '') . (($latest['w'] ?? '—') !== '—' ? $latest['w'] . ' · ' : '') . $latest['mode'] . ' · registered ' . $latest['date'])) ?></small>
    </div>
    <?php if ($tl): ?>
    <ol class="cdl-tl" aria-label="Latest updates on <?= h($latest['id']) ?>">
      <?php foreach ($tl as $t): ?><li class="<?= h($t['c']) ?>"><b><?= h($t['t']) ?></b><small><?= h($t['w']) ?></small></li><?php endforeach; ?>
    </ol>
    <?php endif; ?>
    <?php endif; ?>
    <footer class="cdl-help"><span><i class="ic ic-sm i-message" aria-hidden="true"></i>Questions? Tap <?= h(function_exists('cdp_kingName') ? cdp_kingName() : 'King') ?> at the bottom right.</span><a href="support.php">Help</a><a href="<?= h(sl_route('PickupCodes')) ?>">Pickup codes</a></footer>
  </article>
</section>
