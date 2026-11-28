<?php
/**
 * M-HandOut: hand parcels over with the customer's pickup code (warehouse_delivery.php?customer=).
 * Same $d as the web page (views/sl/data/warehouse.php sl_warehouseDeliveryData) and the same client flow
 * (views/sl/inc/handover_js.php → ajax/sl/warehouse_ajax.php verify_customer, send_customer, skip_customer, handover).
 * Pickup codes are never shown to staff. The design's signature pad is left out: the handover records no signature.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$c = $d['cust'];
$slPhone['nav'] = $c ? 'none' : 'staff';
$canSkip = $c && $d['canCode'] && function_exists('cdp_pickupCodeCanSkip') && cdp_pickupCodeCanSkip($user);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-wh.css')) ?>">
<?php if (!$c): ?>
<?= sl_phoneTop('Hand out', sl_num($d['todayTot']) . ' handed over today · ' . sl_num($d['readyN']) . ' ready', ['back' => sl_route('Warehouse')], sl_phoneIcon('layers', 'Hand out by consolidation', 'warehouse_delivery.php?view=consolidations')) ?>
<main class="mx-body">
<form method="get" class="mx-srch" role="search"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Find the customer" placeholder="Name, locker, phone or tracking" enterkeyhint="search"></label><button class="mx-ib" type="submit" aria-label="Find"><i class="ic i-right" aria-hidden="true"></i></button></form>
<div class="mx-ban info"><i class="ic ic-sm i-key" aria-hidden="true"></i><span>Find the customer, then ask for the pickup code that came with the ready message.</span></div>
<?php $seen = []; foreach ($d['pkgs'] as $k) { if (empty($k['sender']) || isset($seen[$k['sender']])) continue; $seen[$k['sender']] = $k + ['n' => 0]; } foreach ($d['pkgs'] as $k) if (!empty($k['sender'])) $seen[$k['sender']]['n']++; ?>
<?php if ($seen): ?>
<?= sl_mxSec($d['filter'] === 'handed' ? 'Handed over' : 'Waiting at the counter') ?>
<section class="mx-card mx-list">
<?php foreach ($seen as $sid => $k): ?>
<a class="mx-row" href="<?= h(sl_url(['customer' => $sid, 'q' => null, 'page' => null])) ?>"><span class="mx-ava"><?= h($k['ini']) ?></span><span class="mx-rm"><b><?= h($k['cust']) ?></b><small><?= (int) $k['n'] ?> parcel<?= $k['n'] === 1 ? '' : 's' ?><?= $k['locker'] !== '' ? ' · ' . h($k['locker']) : '' ?><?= $k['days'] !== '' ? ' · ' . h($k['days']) : '' ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($k['pc']) ?>"><?= h($k['st']) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php if ($d['count'] > $d['per']): $pages = (int) ceil($d['count'] / $d['per']); ?><nav class="wh-pg" aria-label="Pages"><?php if ($d['page'] > 1): ?><a href="<?= h(sl_url(['page' => $d['page'] - 1])) ?>">Previous</a><?php else: ?><span></span><?php endif; ?><span>Page <?= (int) $d['page'] ?> of <?= $pages ?></span><?php if ($d['page'] < $pages): ?><a href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>">Next</a><?php else: ?><span></span><?php endif; ?></nav><?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('box', $d['q'] !== '' ? 'No customer matches' : 'Nothing is waiting', $d['q'] !== '' ? 'Try a locker number, phone or tracking number.' : '') ?>
<?php endif; ?>
</main>
<?php else: ?>
<?= sl_phoneTop('Hand out', $c['name'] . ($c['lock'] !== '' ? ' · ' . $c['lock'] : ''), ['back' => sl_route('WarehouseDelivery')]) ?>
<main class="mx-body" id="ho-flow" data-customer="<?= (int) $c['id'] ?>">
<section class="mx-card mx-codew ho-hide-done">
<small><?= $c['code']['live'] ? 'Ask ' . h($c['name']) . ' for their pickup code' : 'No code sent yet: send one first' ?></small>
<div class="mx-code" role="group" aria-label="Pickup code"><?php for ($i = 1; $i <= 6; $i++): ?><input class="ho-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Digit <?= $i ?>" autocomplete="<?= $i === 1 ? 'one-time-code' : 'off' ?>"><?php endfor; ?></div>
<div class="mx-ban bad" id="ho-bad" hidden style="width:100%"><i class="ic ic-sm i-x" aria-hidden="true"></i><span></span></div>
<?php if ($d['canCode']): ?><button class="mx-btn wh-unver" type="button" id="ho-verify" style="width:100%">Verify code</button><?php endif; ?>
<?php if ($c['code']['live']): ?><small>Last sent <?= h($c['code']['live']['sent_at']) ?> · 5 tries before the code is voided</small><?php endif; ?>
</section>
<section class="mx-hero lt ho-hide-done">
<div class="mx-hk"><span class="wh-unver">Waiting for the code</span><span class="wh-ver">Code matches</span><span class="mx-tag wh-ver">Verified</span></div>
<b class="mx-hv"><?= h($c['name']) ?></b>
<small><?= count($c['pkgs']) ?> parcel<?= count($c['pkgs']) === 1 ? '' : 's' ?><?= $c['lock'] !== '' ? ' · ' . h($c['lock']) : '' ?> · <?= h($c['payL']) ?></small>
</section>
<?php if ($c['pkgs']): ?>
<section class="mx-scan ho-hide-done" aria-label="Scanner"><div class="mx-vf" aria-hidden="true"><i></i><i></i><i></i><i></i><em></em></div><b>Scan each parcel to confirm</b><small>Scan with the counter scanner or type the tracking number</small><input class="wh-scan-in" id="ho-scan-in" aria-label="Scan or type a tracking number" placeholder="Tracking number" autocomplete="off" autocapitalize="characters" enterkeyhint="done"></section>
<div class="mx-ban bad ho-hide-done" id="ho-scanbad" hidden><i class="ic ic-sm i-x" aria-hidden="true"></i><span></span></div>
<section class="mx-card mx-list wh-items ho-hide-done">
<?php foreach ($c['pkgs'] as $k): ?>
<label class="mx-row" data-item="<?= h($k['module'] . ':' . $k['oid']) ?>" data-trk="<?= h($k['id']) ?>" data-cleared="<?= $k['cleared'] ? 1 : 0 ?>"><input type="checkbox" disabled aria-label="Hand over <?= h($k['id']) ?>"><span class="mx-rm"><b class="mono"><?= h($k['id']) ?></b><small><?= h($k['what']) ?> · <?= h($k['w']) ?> · bay <?= h($k['bay']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= $k['cleared'] ? 'pending' : 'overdue' ?>"><?= $k['cleared'] ? 'Ready' : 'Awaiting clearance' ?></span></span></label>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('box', 'No parcels ready', $c['name'] . ' has nothing waiting at the counter.') ?>
<?php endif; ?>
<div id="ho-step2" class="mx-f ho-hide-done" hidden><small>How was it handed over?</small><div class="mx-fseg" role="group" aria-label="How was it handed over"><button type="button" class="on" data-kind="picked_up">Picked up</button><button type="button" data-kind="delivered">Delivered by us</button></div><p class="mx-note" id="ho-left" aria-live="polite"></p></div>
<?php if ($canSkip): ?><div class="wh-skip ho-hide-done" id="ho-skip-row" hidden><label class="mx-f"><small>Why is the code skipped?</small><input class="mx-in" id="ho-skip-reason" maxlength="200" placeholder="e.g. customer phone lost, ID checked"></label><button class="mx-btn" type="button" id="ho-skip-go">Skip and continue</button></div><?php endif; ?>
<div class="mx-btns ho-hide-done">
<?php if ($d['canPkg']): ?><button class="mx-btn" type="button" id="ho-go" disabled><span>Confirm hand-out</span></button><?php endif; ?>
<?php if ($d['canCode']): ?><button class="mx-btn sec wh-unver" type="button" id="ho-send">Customer has no code · send it again</button><?php endif; ?>
<?php if ($canSkip): ?><button class="mx-btn sec wh-unver" type="button" id="ho-skip">Skip the code</button><?php endif; ?>
</div>
<section class="mx-card mx-codew" id="ho-done" hidden><span class="mx-li" style="background:var(--green-bg);color:var(--green-ink)"><i class="ic i-check" aria-hidden="true"></i></span><h2 style="margin:0;font-size:16px;text-align:center"></h2><small>The customer is told by WhatsApp and e-mail.</small><a class="mx-btn" style="width:100%" href="<?= h(sl_route('WarehouseDelivery')) ?>">Next customer</a></section>
</main>
<?php $slPreVerified = $c['verifiedItems'] ?? []; include CDP_ROOT . '/views/sl/inc/handover_js.php'; ?>
<?php endif; ?>
