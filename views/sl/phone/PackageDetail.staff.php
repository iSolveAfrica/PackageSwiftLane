<?php
/** M-PkgDetail (staff): one sea package on a phone. $d = views/sl/data/packages.php sl_packageDetailData(). */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'none';
$can = $d['can'];
$pct = (int) round(($d['stage'] - 1) / 4 * 100);
$canHand = $user->cdp_hasPermission(['view_warehouse_delivery', 'warehouse_deliver_package']);
$canVerify = $user->cdp_hasPermission('verify_payments') && $d['payCls'] === 'onway';
require_once CDP_ROOT . '/helpers/delivery_service.php';
$deliveryLink = function_exists('dl_parcelLink') ? dl_parcelLink('sea', (int) $d['id']) : null;
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<?= sl_phoneTop($d['trk'], $d['owner']['name'], ['back' => sl_route('SeaPackages')], '<a class="mx-ib" href="#more" aria-label="More actions"><i class="ic i-more" aria-hidden="true"></i></a>') ?>
<main class="mx-body">
<section class="mx-hero"><div class="mx-hk">Sea · <?= h($d['office']) ?><span class="mx-tag"><?= h($d['pay']) ?></span></div><b class="mx-hv"><?= h($d['done'] ? $d['handLabel'] : ucfirst(strtolower($d['st']))) ?></b><small><?= h(($d['steps'][$d['stage'] - 1] ?? '') . ' · ETA ' . $d['eta'] . ($d['con'] ? ' · container ' . $d['con']['code'] : '')) ?></small><div class="mx-prog" role="img" aria-label="Step <?= (int) $d['stage'] ?> of 5"><i style="width:<?= max(4, $pct) ?>%"></i></div></section>
<?php if (!empty($d['scene'])): $sc = $d['scene']; $scDone = !empty($d['done']); ?><div class="b1-scene"><?php include CDP_ROOT . '/views/sl/pages/partials/parcel_scene.php'; ?></div><?php endif; ?>
<div class="mx-sec"><h2>Details</h2></div>
<section class="mx-card mx-kv">
<div><span>Customer</span><b><a href="<?= h(sl_route('CustomerProfile', ['id' => $d['owner']['id']])) ?>"><?= h($d['owner']['name'] . ($d['owner']['locker'] !== '' ? ' · ' . $d['owner']['locker'] : '')) ?></a></b></div>
<?php if ($d['owner']['phone'] !== ''): ?><div><span>Phone</span><b><a href="tel:<?= h($d['owner']['phone']) ?>"><?= h($d['owner']['phone']) ?></a></b></div><?php endif; ?>
<div><span>Boxes</span><b><?= h($d['boxN'] . ' · ' . $d['weight'] . ' · ' . $d['vol'] . ' vol') ?></b></div>
<div><span>Container</span><b><?php if ($d['con']): ?><a href="<?= h(sl_route('ConsolidationDetail', ['id' => $d['con']['id'], 'type' => 'sea'])) ?>"><?= h($d['con']['code'] . ' · ' . $d['con']['st']) ?></a><?php else: ?>Not in a container yet<?php endif; ?></b></div>
<div><span>Store</span><b><?= h($d['supplier'] . ($d['postal'] !== '—' ? ' · ' . $d['postal'] : '')) ?></b></div>
<div><span>Courier</span><b><?= h($d['courier']) ?></b></div>
<div><span>Charges</span><b><?= h($d['total'] . ' · ' . strtolower($d['pay'])) ?></b></div>
<?php if ($d['method'] !== '—'): ?><div><span>Payment</span><b><?= h($d['method'] . ($d['paidOn'] !== '—' ? ' · ' . $d['paidOn'] : '')) ?></b></div><?php endif; ?>
<div><span>Deliver to</span><b><?= h($d['deliverTo']) ?></b></div>
<div><span>Registered</span><b><?= h($d['registered']) ?></b></div>
</section>
<?php if ($d['boxes']): ?>
<div class="mx-sec"><h2>Boxes</h2></div>
<section class="mx-card mx-list">
<?php foreach ($d['boxes'] as $b): ?><div class="mx-row"><span class="mx-li"><i class="ic i-box" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($b['what'] ?: 'Box ' . $b['n']) ?></b><small><?= h(($b['qty'] > 1 ? $b['qty'] . '× · ' : '') . $b['dims'] . ' · ' . $b['w']) ?></small></span><span class="mx-rr"><small><?= h($b['declared']) ?></small></span></div><?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($d['files']): ?>
<section class="mx-ph"><?php foreach (array_slice($d['files'], 0, 6) as $f): ?><a href="<?= h($f['url']) ?>" target="_blank" rel="noopener"><span><i class="ic i-image" aria-hidden="true"></i></span><small><?= h($f['name']) ?></small></a><?php endforeach; ?></section>
<?php endif; ?>
<div class="mx-sec"><h2>Tracking</h2></div>
<section class="mx-card">
<?php if ($d['events']): ?>
<ol class="mx-tl"><?php foreach (array_slice($d['events'], 0, 8) as $i => $e): ?><li class="<?= $i === 0 ? 'cur' : 'done' ?>"><span class="mx-td"><i class="ic ic-sm <?= $i === 0 ? 'i-pin' : 'i-check' ?>" aria-hidden="true"></i></span><div><b><?= h(ucfirst(strtolower($e['t']))) ?></b><small><?= h($e['s']) ?></small></div></li><?php endforeach; ?></ol>
<?php else: ?><?= sl_mxEmpty('route', 'No tracking events yet') ?><?php endif; ?>
</section>
<?php if ($can['code']): ?>
<div class="mx-ban <?= $d['code']['verified'] ? 'ok' : 'info' ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i><span><?= $d['code']['verified'] ? 'Pickup code verified · hand over within 30 minutes.' : ($d['code']['live'] ? 'The customer holds a pickup code sent ' . h($d['code']['live']['sent_at']) . '.' : 'No pickup code yet. It goes out with the ready message.') ?></span></div>
<?php endif; ?>
<div class="mx-btns">
<?php if ($canHand && !$d['done']): ?><a class="mx-btn" href="<?= h(sl_route('HandOut', ['customer' => $d['owner']['id']])) ?>">Hand out with pickup code</a><?php endif; ?>
<?php if ($canVerify): ?><button type="button" class="mx-btn sec" data-sl-action="ajax/sl/package_ajax.php" data-p-op="confirm_payment" data-p-id="<?= (int) $d['id'] ?>" data-sl-confirm="Confirm the payment for <?= h($d['trk']) ?>?" data-sl-reload>Confirm payment</button><?php endif; ?>
</div>
<?php if ($can['track']): ?>
<details class="mx-grp" id="status"><summary><span>Update status</span><i class="ic ic-sm i-down" aria-hidden="true"></i></summary>
<form class="mx-card mx-form" data-sl-form data-sl-reload action="ajax/sl/package_ajax.php" method="post">
<input type="hidden" name="op" value="status"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
<p class="mx-note">The customer is told by WhatsApp and e-mail. Handing over needs a verified code: use Hand out.</p>
<label class="mx-fld"><span>New status</span><select name="status" required><?php foreach ($d['statuses'] as $s): if (cdp_isHandedOver($s->id)) continue; ?><option value="<?= (int) $s->id ?>"<?= (int) $s->id === $d['raw'] ? ' selected' : '' ?>><?= h(str_replace('_', ' ', $s->mod_style)) ?></option><?php endforeach; ?></select></label>
<label class="mx-fld"><span>Location</span><input name="city" placeholder="Where the package is now" autocomplete="off"></label>
<label class="mx-fld"><span>Note</span><input name="comment" placeholder="Optional note for the customer"></label>
<button type="submit" class="mx-btn or">Save status</button>
</form></details>
<?php endif; ?>
<div class="mx-sec" id="more"><h2>More actions</h2></div>
<section class="mx-card mx-list">
<?php if ($can['deliver']): ?><a class="mx-row" href="customer_package_deliver.php?id=<?= (int) $d['id'] ?>&amp;view=web"><span class="mx-li"><i class="ic i-check" aria-hidden="true"></i></span><span class="mx-rm"><b>Deliver package</b></span></a><?php endif; ?>
<?php if ($can['edit']): ?><a class="mx-row" href="customer_package_edit.php?id=<?= (int) $d['id'] ?>"><span class="mx-li"><i class="ic i-edit" aria-hidden="true"></i></span><span class="mx-rm"><b>Edit package</b></span></a><?php endif; ?>
<?php if ($can['label']): ?><a class="mx-row" href="print_label_package.php?id=<?= (int) $d['id'] ?>" target="_blank" rel="noopener"><span class="mx-li"><i class="ic i-print" aria-hidden="true"></i></span><span class="mx-rm"><b>Print label</b></span></a><?php endif; ?>
<?php if ($can['print']): ?><a class="mx-row" href="print_invoice_package.php?id=<?= (int) $d['id'] ?>" target="_blank" rel="noopener"><span class="mx-li"><i class="ic i-file" aria-hidden="true"></i></span><span class="mx-rm"><b>Print invoice</b></span></a><?php endif; ?>
<?php if ($deliveryLink): ?><a class="mx-row" href="<?= h($deliveryLink['href']) ?>"><span class="mx-li"><i class="ic i-truck" aria-hidden="true"></i></span><span class="mx-rm"><b>Delivery history and proof</b><small><?= h($deliveryLink['state']) ?></small></span></a><?php endif; ?>
<?php if ($can['delete']): ?><button type="button" class="mx-row bad" data-sl-action="ajax/sl/package_ajax.php" data-p-op="delete" data-p-id="<?= (int) $d['id'] ?>" data-sl-confirm="Delete package <?= h($d['trk']) ?>? This cannot be undone." data-sl-reload><span class="mx-li"><i class="ic i-trash" aria-hidden="true"></i></span><span class="mx-rm"><b>Delete package</b></span></button><?php endif; ?>
</section>
</main>
<script>
document.addEventListener('click', function (e) {
  var a = e.target.closest('a[href^="#"]'); if (!a) return;
  var t = document.getElementById(a.getAttribute('href').slice(1));
  if (t && t.tagName === 'DETAILS') t.open = true;
});
</script>
