<?php
/**
 * M-Dispatch: packages waiting for a rider and riders with room. Same $d as the web page
 * (views/sl/data/riders.php sl_riderDispatchData; GET q, kind, page). Writes: the existing
 * ajax/sl/rider_ajax.php (op assign | unassign, id, driver_id), as the web page's rider chips.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/helpers/delivery_service.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$t = $d['tot'];
$best = $d['riders'][0] ?? null; // riders are sorted by open jobs, fewest first
$first = fn(string $n) => strtok($n, ' ') ?: $n;
$acts = (dl_manager($user) ? sl_phoneIcon('truck', 'Customer delivery requests', sl_route('DeliveryDispatch')) : '') . sl_phoneIcon('route', 'Live tracking', sl_route('DeliveryTracking'));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-centres.css')) ?>">
<?= sl_phoneTop('Dispatch board', 'Live · ' . date('H:i'), ['back' => sl_route('Drivers')], $acts) ?>
<main class="mx-body" data-mx-scope>
<div class="mx-seg" role="group" data-mx-seg aria-label="Show">
  <button type="button" aria-pressed="true" class="on" data-mx-panel="wait">Unassigned <?= (int) $d['count'] ?></button>
  <button type="button" aria-pressed="false" data-mx-panel="riders">Assigned <?= (int) $t['n'] ?></button>
  <a href="<?= h(sl_route('DeliveryTracking')) ?>">On route</a>
</div>
<div class="pc-panel" data-mx-panel-of="wait">
<form method="get"><input type="hidden" name="kind" value="<?= h($d['kind']) ?>"><div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Search packages" placeholder="Tracking or customer" autocomplete="off"></label></div></form>
<div class="mx-chips"><?php foreach (['all' => 'All', 'pickup' => 'Pickups', 'delivery' => 'Deliveries'] as $k => $l): ?><a class="<?= $k === $d['kind'] ? 'on' : '' ?>" href="<?= h(sl_url(['kind' => $k, 'page' => null])) ?>"<?= $k === $d['kind'] ? ' aria-current="true"' : '' ?>><?= h($l) ?></a><?php endforeach; ?></div>
<?= sl_mxSec('Waiting for a rider') ?>
<?php if ($d['waiting']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['waiting'] as $p): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-<?= $p['pickup'] ? 'download' : 'box' ?>" aria-hidden="true"></i></span><a class="mx-rm" href="<?= h($p['pickup'] ? 'pickup_list.php?q=' . urlencode($p['trk']) : sl_route('ShipmentDetail', ['id' => $p['id']])) ?>" style="color:inherit"><b><?= h($p['cust']) ?><?= $p['area'] !== '—' ? ' · ' . h(strtok((string) $p['area'], ',')) : '' ?></b><small><span class="mono"><?= h($p['trk']) ?></span> · <?= $p['pickup'] ? 'Pickup' : 'Delivery' ?> · <?= h($p['w']) ?> · <?= h($p['fee']) ?></small></a><span class="mx-rr"><?php if ($d['canAssign'] && $best): ?><button type="button" class="sl-pill sorting pc-btn pc-pillbtn" data-sl-action="ajax/sl/rider_ajax.php" data-p-op="assign" data-p-id="<?= (int) $p['id'] ?>" data-p-driver_id="<?= (int) $best['id'] ?>" data-sl-confirm="Give <?= h($p['trk']) ?> to <?= h($best['name']) ?>?" data-sl-reload>Give to <?= h($first($best['name'])) ?></button><?php else: ?><small><?= h($p['age']) ?></small><?php endif; ?></span></div>
<?php endforeach; ?>
</section>
<?php if ($d['count'] > $d['page'] * $d['per']): ?><div class="pc-more"><a href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>">Show more packages</a></div><?php endif; ?>
<?php if ($d['canAssign'] && $d['riders']): ?>
<form class="mx-card mx-form" data-sl-form data-sl-reload action="ajax/sl/rider_ajax.php" method="post">
  <input type="hidden" name="op" value="assign">
  <label class="mx-f"><small>Assign by hand · package</small><select class="mx-in" name="id" required><?php foreach ($d['waiting'] as $p): ?><option value="<?= (int) $p['id'] ?>"><?= h($p['trk'] . ' · ' . $p['cust']) ?></option><?php endforeach; ?></select></label>
  <label class="mx-f"><small>Rider</small><select class="mx-in" name="driver_id" required><?php foreach ($d['riders'] as $r): ?><option value="<?= (int) $r['id'] ?>"><?= h($r['name'] . ' · ' . $r['n'] . ' open') ?></option><?php endforeach; ?></select></label>
  <button class="mx-btn pc-btn" type="submit">Assign package</button>
</form>
<?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('check', 'All packages are assigned', $d['q'] !== '' ? 'Nothing matches “' . $d['q'] . '”.' : 'New pickups and deliveries appear here.') ?>
<?php endif; ?>
</div>
<div class="pc-panel" data-mx-panel-of="riders" hidden>
<?= sl_mxSec('Riders with room') ?>
<?php if ($d['riders']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['riders'] as $r): ?>
<a class="mx-row" href="<?= h(sl_route('RiderDetail', ['id' => $r['id']])) ?>"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= $r['n'] ? (int) $r['n'] . ' stops left' : 'Free now' ?><?= $r['zone'] ? ' · ' . h($r['zone']) : '' ?> · <?= (int) $r['done'] ?> done today</small></span><span class="mx-rr"><b><?= h($r['fees']) ?></b><small>open fees</small></span></a>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('users', 'No active riders', 'Add one from Staff & Roles.') ?>
<?php endif; ?>
<section class="mx-card mx-kv">
  <div><span>Fees on open packages</span><b><?= h(sl_money($t['fees'])) ?></b></div>
  <div><span>Riders get · <?= (int) $d['pct'] ?>%</span><b><?= h(sl_money($t['rid'])) ?></b></div>
  <div><span>We keep · <?= 100 - (int) $d['pct'] ?>%</span><b><?= h(sl_money($t['sw'])) ?></b></div>
</section>
</div>
</main>
