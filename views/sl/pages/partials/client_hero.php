<?php
/**
 * Customer dashboard header card (desktop and tablet; phones use partials/mobile_dashboard.php).
 * Data: sl_clientDashboardData() in views/sl/data/portal.php. Styles: assets/swiftlane/overview-hero.css.
 */
$h0 = (int) date('G');
$act = (array) ($d['parcelActivity'] ?? []);
$actBars = $act ? sl_sparkBars(array_values($act), 4, array_map(fn($x) => date('D j M', strtotime($x)), array_keys($act))) : [];
$yearN = max(1, (int) $d['year']);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/overview-hero.css')) ?>">
<section class="ovh-me ovh-client" aria-label="Your account">
  <div class="ovh-me-top">
    <?= $slPic(64, false) ?>
    <div class="col grow" style="min-width:0">
      <span class="ovh-eyebrow"><?= h(($h0 < 12 ? 'Good morning' : ($h0 < 17 ? 'Good afternoon' : 'Good evening')) . ' · ' . date('l j M')) ?></span>
      <h1 class="ovh-name">Welcome back, <?= h($d['first']) ?></h1>
      <span class="mut sm"><?= $d['locker'] !== '' ? 'Locker <b class="mono">' . h($d['locker']) . '</b>' : 'Customer' ?><?= $d['since'] ? ' · shipping with us since ' . h($d['since']) : '' ?></span>
    </div>
    <div class="row g8 wrap">
      <?php if ($d['canPrealert']): ?><a class="sl-btn sm ghost" href="<?= sl_route('PreAlerts') ?>"><i class="ic ic-sm i-inbox" aria-hidden="true"></i>Add Pre-Alert</a><?php endif; ?>
      <?php if ($d['canShip']): ?><a class="sl-btn sm" href="courier_add_client.php"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Shipment</a><?php endif; ?>
    </div>
  </div>
  <?php $delivery = $d['mobileDelivery'] ?? null; ?>
  <div class="customer-next">
    <?php if ($delivery): ?>
      <div><small>Your delivery</small><h2><?= $delivery->state === 'onroute' ? 'Your rider is on the way' : 'Delivery requested' ?></h2><p><?= h($delivery->address) ?></p></div>
      <a class="sl-btn" href="delivery_request.php?id=<?= (int) $delivery->id ?>">View delivery</a>
    <?php elseif ($d['ready']): ?>
      <div><small>Collection</small><h2><?= (int) $d['ready'] ?> <?= (int) $d['ready'] === 1 ? 'parcel is' : 'parcels are' ?> ready for pickup</h2></div>
      <a class="sl-btn" href="<?= h(sl_route('PickupCodes')) ?>">Show pickup codes</a>
    <?php elseif ($d['latest']): ?>
      <div><small>Your latest parcel</small><h2><?= h($d['latest']['id']) ?></h2><p><?= h($d['latest']['st']) ?></p></div>
      <a class="sl-btn" href="<?= h($d['latest']['href']) ?>">View parcel</a>
    <?php else: ?>
      <div><h2>Shipping your first parcel?</h2><p>Use your forwarding address when you order.</p></div>
      <a class="sl-btn" href="#addresses">Find my address</a>
    <?php endif; ?>
  </div>

  <div class="ovh-me-mid">
    <div class="ovh-ring" style="--p:<?= sl_pct($d['deliveredYear'], $yearN) ?>" role="img" aria-label="<?= (int) $d['deliveredYear'] ?> of <?= (int) $d['year'] ?> parcels this year delivered"><b><?= (int) $d['deliveredYear'] ?></b><span>of <?= (int) $d['year'] ?> delivered<br>this year</span></div>
    <dl class="ovh-stats">
      <div><dt>On the way</dt><dd><b><?= sl_num($d['onWay']) ?></b> parcel<?= (int) $d['onWay'] === 1 ? '' : 's' ?></dd></div>
      <div><dt>At the warehouse</dt><dd><b><?= sl_num($d['atWh']) ?></b> received</dd></div>
      <div><dt>Ready for pickup</dt><dd><b class="<?= (int) $d['ready'] ? 'ok' : '' ?>"><?= sl_num($d['ready']) ?></b> waiting for you</dd></div>
      <?php if ($d['finance']): ?><div><dt>Balance due</dt><dd><b class="<?= $d['dueN'] ? 'bad' : '' ?>"><?= h($d['dueMoney']) ?></b><?= $d['dueN'] ? ' · ' . (int) $d['dueN'] . ' invoice' . ((int) $d['dueN'] === 1 ? '' : 's') . ($d['dueDate'] ? ', due ' . h($d['dueDate']) : '') : '' ?></dd></div><?php endif; ?>
    </dl>
  </div>
  <?php if ($actBars): ?>
  <div class="ovh-me-bars">
    <div class="row between sm"><span class="mut">My parcels registered, 14 days · <?= sl_num(array_sum($act)) ?> in total</span></div>
    <div class="ovh-bars violet" role="img" aria-label="Parcels registered per day, last 14 days"><?php $n = count($actBars); foreach ($actBars as $i => $b): ?><i class="<?= $i === $n - 1 ? 'now' : '' ?>" style="<?= h($b['s']) ?>" title="<?= h($b['t']) ?>"></i><?php endforeach; ?></div>
  </div>
  <?php endif; ?>
  <div class="ovh-chips">
    <a href="<?= h(sl_route('DeliveryRequest')) ?>"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Request delivery</a>
    <a href="<?= h(sl_route('PickupCodes')) ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i>Pickup codes</a>
    <?php if ($d['canBills']): ?><a href="<?= h(sl_route('MyBills')) ?>"><i class="ic ic-sm i-card" aria-hidden="true"></i>Pay bills<?= $d['dueN'] ? ' <b>' . (int) $d['dueN'] . '</b>' : '' ?></a><?php endif; ?>
    <a href="<?= h(sl_route('PublicTracking')) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Track a parcel</a>
    <a href="support.php"><i class="ic ic-sm i-message" aria-hidden="true"></i>Get help</a>
  </div>
</section>
