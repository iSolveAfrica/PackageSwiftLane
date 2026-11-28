<?php
/**
 * M-DelivReq: customer delivery requests. Same $d as the web page (views/sl/data/delivery.php dl_dispatchData):
 * ?state= filter and ?id= one request. Writes go to the existing ajax/sl/delivery_ajax.php (op assign | cancel,
 * id, driver_id, note), exactly as the web form.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$states = ['open' => 'Open', 'pending' => 'New', 'assigned' => 'Assigned', 'onroute' => 'On route', 'failed' => 'Failed', 'delivered' => 'Done', 'cancelled' => 'Cancelled', 'all' => 'All'];
$pill = ['pending' => 'pending', 'assigned' => 'sorting', 'onroute' => 'onway', 'failed' => 'overdue', 'delivered' => 'delivered', 'cancelled' => 'overdue'];
$nParcels = fn($r) => count(json_decode((string) $r->parcel_json, true) ?: []);
$r = $d['request'] ?? null;
$acts = (!dl_agency() && $user->cdp_hasPermission(['manage_freight', 'manage_rider_fees'])) ? sl_phoneIcon('sliders', 'Pricing, Maps and zones', 'delivery_settings.php') : '';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-centres.css')) ?>">
<?php if ($r): ?>
<?php
$slPhone['nav'] = 'none';
$breakdown = json_decode((string) $r->breakdown_json, true) ?: [];
$items = (new DeliveryService())->sql('SELECT tracking FROM cdb_delivery_items WHERE request_id=?', [$r->id])->fetchAll(PDO::FETCH_COLUMN);
$riderName = (string) ($r->rider_name ?? '');
foreach ($d['riders'] as $rider) if ((int) $rider->id === (int) $r->driver_id) $riderName = (string) $rider->name;
if ($riderName === '' && (int) $r->driver_id) foreach ($d['requests'] as $q) if ((int) $q->driver_id === (int) $r->driver_id && $q->rider_name) $riderName = (string) $q->rider_name;
$dest = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($r->lat . ',' . $r->lng) . '&destination_place_id=' . rawurlencode((string) $r->place_id) . '&travelmode=driving';
?>
<?= sl_phoneTop('Delivery #' . (int) $r->id, ucfirst((string) $r->state), ['back' => 'delivery_dispatch.php?state=' . rawurlencode((string) ($d['filter'] ?? 'open'))]) ?>
<main class="mx-body">
<section class="mx-hero">
  <div class="mx-hk">Approved delivery fee<span class="mx-tag"><?= h(ucfirst((string) $r->state)) ?></span></div>
  <b class="mx-hv"><?= h(dl_money((int) $r->amount_cents, $r->currency)) ?></b>
  <small><?= h(number_format($r->distance_m / 1000, 2)) ?> road km · <?= (int) $nParcels($r) ?> parcel<?= $nParcels($r) === 1 ? '' : 's' ?> · <?= $r->fee_collected ? 'fee collected' : 'fee due on delivery' ?></small>
</section>
<section class="mx-card mx-kv">
  <div><span>Contact</span><b><?= h($r->contact_name) ?></b></div>
  <div><span>Phone</span><b><?php if ($r->contact_phone): ?><a href="tel:<?= h(preg_replace('/[^0-9+]/', '', (string) $r->contact_phone)) ?>"><?= h($r->contact_phone) ?></a><?php else: ?>—<?php endif; ?></b></div>
  <div><span>Destination</span><b><?= h($r->address) ?></b></div>
<?php if (!empty($breakdown['zone_name'])): ?>  <div><span>Zone</span><b><?= h($breakdown['zone_name']) ?></b></div><?php endif; ?>
  <div><span>Rider</span><b><?= h($riderName !== '' ? $riderName : ((int) $r->driver_id ? 'Rider #' . (int) $r->driver_id : 'Needs dispatch')) ?></b></div>
<?php if ($r->instructions): ?>  <div><span>Instructions</span><b><?= h($r->instructions) ?></b></div><?php endif; ?>
<?php if ($items): ?>  <div><span>Packages</span><b class="mono"><?= h(implode(', ', $items)) ?></b></div><?php endif; ?>
<?php if ($r->proof_photo): ?>  <div><span>Received by</span><b><?= h($r->receiver_name) ?> · <?= h(sl_date($r->completed_at, 'M j, H:i')) ?></b></div><?php endif; ?>
</section>
<div class="mx-btns"><a class="mx-btn sec" href="<?= h($dest) ?>" target="_blank" rel="noopener">Navigate with Google Maps</a><?php if ($r->proof_photo): ?><a class="mx-btn sec" href="delivery_proof.php?id=<?= (int) $r->id ?>" target="_blank" rel="noopener">View delivery photo</a><?php endif; ?></div>
<?php if (in_array($r->state, ['pending', 'assigned', 'failed'], true)): ?>
<form class="mx-card mx-form" data-sl-form data-sl-redirect action="ajax/sl/delivery_ajax.php" method="post">
  <input type="hidden" name="op" value="assign"><input type="hidden" name="id" value="<?= (int) $r->id ?>">
  <label class="mx-f"><small>Assign or reassign a rider</small><select class="mx-in" name="driver_id" required><option value="">Choose a rider</option><?php foreach ($d['riders'] as $rider): if ($r->agency_id && (int) ($rider->agency_id ?? 0) !== (int) $r->agency_id) continue; ?><option value="<?= (int) $rider->id ?>"<?= (int) $rider->id === (int) $r->driver_id ? ' selected' : '' ?>><?= h($rider->name) ?></option><?php endforeach; ?></select></label>
  <div class="pc-acts"><button class="mx-btn pc-btn" type="submit">Assign delivery</button><button class="mx-btn sec pc-btn" type="button" data-sl-action="ajax/sl/delivery_ajax.php" data-p-op="cancel" data-p-id="<?= (int) $r->id ?>" data-sl-confirm="Cancel this delivery request?" data-sl-reload>Cancel request</button></div>
</form>
<?php elseif ($r->state === 'onroute'): ?>
<form class="mx-card mx-form" data-sl-form data-sl-redirect action="ajax/sl/delivery_ajax.php" method="post">
  <input type="hidden" name="op" value="cancel"><input type="hidden" name="id" value="<?= (int) $r->id ?>">
  <label class="mx-f"><small>Recall / cancel reason (confirm parcel custody with the rider first)</small><input class="mx-in" name="note" minlength="3" maxlength="500" required autocomplete="off"></label>
  <button class="mx-btn sec pc-btn" type="submit">Recall and cancel delivery</button>
</form>
<?php endif; ?>
<?php if ($d['events']): ?>
<?= sl_mxSec('History') ?>
<section class="mx-card"><ol class="mx-tl">
<?php foreach ($d['events'] as $i => $e): ?>
  <li class="<?= $i === 0 ? 'cur' : 'done' ?>"><span class="mx-td"><i class="ic ic-sm i-<?= $i === 0 ? 'truck' : 'check' ?>" aria-hidden="true"></i></span><div><b><?= h(ucfirst((string) $e->state)) ?></b><small><?= h(trim(($e->note ? $e->note . ' · ' : '') . sl_date($e->created_at, 'M j, H:i'))) ?></small></div></li>
<?php endforeach; ?>
</ol></section>
<?php endif; ?>
</main>
<?php else: ?>
<?= sl_phoneTop('Delivery requests', '', ['back' => 'menu.php'], $acts) ?>
<main class="mx-body">
<?php if (!$d['ready']): ?>
<div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Install the courier delivery database migration.</span></div>
<?php else: ?>
<?php if (!empty($d['notFound'])): ?><div class="mx-ban bad"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Request not found in your agency.</span></div><?php endif; ?>
<div class="mx-seg" role="tablist" aria-label="Delivery status">
<?php foreach ($states as $k => $l): ?>
  <a role="tab" aria-selected="<?= $d['filter'] === $k ? 'true' : 'false' ?>" class="<?= $d['filter'] === $k ? 'on' : '' ?>" href="delivery_dispatch.php?state=<?= h($k) ?>"><?= h($l) ?><?= $d['filter'] === $k ? ' ' . count($d['requests']) : '' ?></a>
<?php endforeach; ?>
</div>
<?php if ($d['requests']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['requests'] as $q): $n = $nParcels($q); $area = trim((string) strtok((string) $q->address, ',')); ?>
<a class="mx-row" href="delivery_dispatch.php?id=<?= (int) $q->id ?>&amp;state=<?= h($d['filter']) ?>"><span class="mx-ava"><?= h(sl_initials((string) $q->contact_name ?: '?')) ?></span><span class="mx-rm"><b><?= h($q->contact_name) ?> → <?= h($area) ?></b><small><?= $n ?> parcel<?= $n === 1 ? '' : 's' ?> · <?= h($q->rider_name ?: 'Needs dispatch') ?> · <?= h(sl_ago($q->created_at)) ?></small></span><span class="mx-rr"><b><?= h(dl_money((int) $q->amount_cents, $q->currency)) ?></b><?php if ($q->state === 'pending'): ?><small>Assign</small><?php else: ?><span class="sl-pill <?= h($pill[$q->state] ?? '') ?>"><?= h($states[$q->state] ?? ucfirst((string) $q->state)) ?></span><?php endif; ?></span></a>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('truck', 'No delivery requests in this view', 'Requests customers send from the app appear here.') ?>
<?php endif; ?>
<div class="mx-ban info"><i class="ic ic-sm i-truck" aria-hidden="true"></i><span>Requests come from customers in the app. Fees use the delivery pricing rules.</span></div>
<?php endif; ?>
</main>
<?php endif; ?>
