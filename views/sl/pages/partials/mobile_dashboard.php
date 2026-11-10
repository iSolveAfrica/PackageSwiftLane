<section class="sl-phone-home" aria-label="Quick shipping actions">
<div class="sl-phone-shortcuts">
<a href="<?= h(sl_route('PublicTracking')) ?>"><i class="ic i-route" aria-hidden="true"></i>Track</a>
<a href="<?= h(sl_route('PickupCodes')) ?>"><i class="ic i-key" aria-hidden="true"></i>Pickup codes</a>
<?php if (sl_get($d, 'finance') && $user->cdp_hasPermission('view_my_bills')): ?><a href="<?= h(sl_route('MyBills')) ?>"><i class="ic i-card" aria-hidden="true"></i>Pay bills</a><?php else: ?><a href="<?= h(sl_route('UserHome')) ?>"><i class="ic i-user" aria-hidden="true"></i>Account</a><?php endif; ?>
<a href="support.php"><i class="ic i-message" aria-hidden="true"></i>Support</a>
</div>
<?php if ($latest): ?>
<div class="sl-phone-shipment" data-sl-reveal><div class="row between g8 wrap"><small>YOUR LATEST SHIPMENT</small><span class="sl-pill <?= h($latest['cls']) ?>"><?= h($latest['st']) ?></span></div><h2 class="mono"><?= h($latest['id']) ?></h2><p><?= h($latest['from']) ?> · <?= h($latest['mode']) ?></p><a class="sl-btn" href="<?= h(sl_route('PublicTracking', ['order_track' => $latest['id']])) ?>">Track this parcel <i class="ic i-right" aria-hidden="true"></i></a></div>
<?php else: ?><div class="sl-card col g12"><h2>Let’s get your parcel moving</h2><p class="mut sm">Already shipped something? Find its latest update with your tracking number.</p><a class="sl-btn" href="<?= h(sl_route('PublicTracking')) ?>">Track a parcel</a></div><?php endif; ?>
<?php if ((int) sl_get($d, 'ready') > 0): ?><a class="sl-banner ok" href="<?= h(sl_route('PickupCodes')) ?>"><i class="ic i-key" aria-hidden="true"></i><span><b><?= (int) sl_get($d, 'ready') ?> ready for pickup</b><br>Open your pickup codes</span><i class="ic i-right" aria-hidden="true"></i></a><?php endif; ?>
<?php if (sl_get($d, 'rows')): ?><section class="sl-card"><div class="row between g8"><h2>My parcels</h2><a class="sl-btn sm ghost" href="<?= h(sl_route('UserHome')) ?>">View all</a></div><?php foreach (array_slice($d['rows'], 0, 3) as $r): ?><a class="sl-phone-parcel" href="<?= h($r['href']) ?>"><span class="sl-tile"><i class="ic i-box" aria-hidden="true"></i></span><span class="grow col g4"><b class="mono sm"><?= h($r['id']) ?></b><small class="mut"><?= h($r['from']) ?></small></span><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['st']) ?></span></a><?php endforeach; ?></section><?php endif; ?>
</section>
