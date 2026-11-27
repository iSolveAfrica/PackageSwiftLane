<?php
/**
 * M-CustProfile: one customer on a phone (customer_view.php; customers_view.php redirects here).
 * Same $d as the web page (views/sl/data/customer_profile.php). Writes: ajax/sl/customer_status_ajax.php
 * (approve / block / unblock) and ajax/sl/customer_profile_ajax.php (newsletter), as on the web page.
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
$slPhone['nav'] = 'none';
$parcels = (int) $d['nShip'] + (int) $d['nPkg'];
$digits = preg_replace('/\D+/', '', (string) $d['phone']);
$addr = $d['addresses'][0]['line'] ?? '';
$owes = $d['finance'] && (float) $d['dueN'] > 0;
$actions = $d['canEdit'] ? sl_phoneIcon('edit', 'Edit customer', 'customers_edit.php?user=' . (int) $d['id']) : '';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop($d['name'], '', ['back' => sl_route('Customers')], $actions) ?>
<main class="mx-body">
<section class="mx-hero lt">
  <div class="mx-hk">Customer since <?= h($d['since']) ?> · <?= h($d['st'][0]) ?><span class="mx-tag"><?= h($d['locker']) ?></span></div>
  <b class="mx-hv"><?= sl_num($parcels) ?> parcel<?= $parcels === 1 ? '' : 's' ?></b>
  <small><?= (int) $d['nShip'] ?> air · <?= (int) $d['nPkg'] ?> sea<?php if ($d['finance']): ?> · <?= h($d['spend']) ?> shipped · <?= $owes ? h($d['due']) . ' owed' : 'nothing owed' ?><?php endif; ?></small>
</section>
<?php if ($d['block']): ?>
<div class="mx-ban bad"><i class="ic ic-sm i-ban" aria-hidden="true"></i><span>Blocked · <?= h($d['block']['reason']) ?> · by <?= h($d['block']['by']) ?>, <?= h($d['block']['at']) ?>.</span></div>
<?php elseif ($d['blocked']): ?>
<div class="mx-ban bad"><i class="ic ic-sm i-ban" aria-hidden="true"></i><span>The account is inactive.</span></div>
<?php elseif (!$d['approve']): ?>
<div class="mx-ban warn"><i class="ic ic-sm i-shield" aria-hidden="true"></i><span>KYC to review<?= $d['docUrl'] ? ': ' . h(trim(($d['docType'] ?: 'ID') . ' ' . ($d['docNo'] ?: ''))) . ' uploaded.' : ': no ID document uploaded yet.' ?></span></div>
<?php if ($d['canApprove']): ?><div class="mx-btns"><button type="button" class="mx-btn" data-sl-action="ajax/sl/customer_status_ajax.php" data-p-op="approve" data-p-id="<?= (int) $d['id'] ?>" data-sl-confirm="Approve <?= h($d['name']) ?>?" data-sl-reload>Approve customer</button></div><?php endif; ?>
<?php endif; ?>
<section class="mx-act c4">
  <?php if ($digits !== ''): ?><a href="tel:+<?= h($digits) ?>"><span><i class="ic i-phone" aria-hidden="true"></i></span><small>Call</small></a><a href="https://wa.me/<?= h($digits) ?>" target="_blank" rel="noopener"><span><i class="ic i-message" aria-hidden="true"></i></span><small>WhatsApp</small></a><?php else: ?><a href="<?= h(sl_route('ComposeMessage', ['to' => $d['id']])) ?>"><span><i class="ic i-send" aria-hidden="true"></i></span><small>Message</small></a><a href="mailto:<?= h($d['mail']) ?>"><span><i class="ic i-mail" aria-hidden="true"></i></span><small>E-mail</small></a><?php endif; ?>
  <?php if ($d['canShip']): ?><a href="<?= h(sl_route('NewShipment', ['sender' => $d['id']])) ?>"><span><i class="ic i-plus" aria-hidden="true"></i></span><small>Shipment</small></a><?php else: ?><a href="<?= h(sl_route('ComposeMessage', ['to' => $d['id']])) ?>"><span><i class="ic i-send" aria-hidden="true"></i></span><small>Message</small></a><?php endif; ?>
  <a href="<?= h(sl_route('HandOut')) ?>"><span><i class="ic i-key" aria-hidden="true"></i></span><small>Codes</small></a>
</section>
<section class="mx-card mx-kv">
  <div><span>Phone</span><b><?= h($d['phone']) ?></b></div>
  <div><span>Email</span><b><?= h($d['mail'] ?: '—') ?></b></div>
  <div><span>Locker ID</span><b><?= h($d['locker']) ?></b></div>
  <div><span>Address</span><b><?= h($addr ?: 'No address on file') ?></b></div>
  <div><span>Last parcel</span><b><?= h($d['lastShip']) ?></b></div>
  <div><span>Last sign-in</span><b><?= h($d['lastLogin']) ?></b></div>
  <?php if ($d['finance']): ?><div><span>Balance due</span><b><?= h($d['due']) ?></b></div><?php endif; ?>
</section>
<div class="mx-sec"><h2>Parcels</h2><a href="<?= h(sl_route('AirShipments', ['q' => $d['locker']])) ?>">All <?= sl_num($parcels) ?></a></div>
<?php if ($d['moving']): ?>
<section class="mx-card mx-list">
<?php foreach (array_slice($d['moving'], 0, 5) as $m): ?>
  <a class="mx-row" href="<?= h($m['href']) ?>"><span class="mx-li"><i class="ic <?= $m['kind'] === 'Air' ? 'i-plane' : 'i-ship' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($m['trk']) ?></b><small><?= h($m['kind']) ?> · <?= h($m['since']) ?><?= $m['eta'] ? ' · ETA ' . h($m['eta']) : '' ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($m['cls']) ?>"><?= h($m['st']) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('box', 'Nothing on the move', $parcels ? 'Every parcel of this customer is delivered or closed.' : 'No parcels yet.') ?>
<?php endif; ?>
<div class="mx-sec"><h2>Notifications and access</h2></div>
<section class="mx-card mx-tgs">
  <?= sl_mxToggle('Marketing messages', 'Offers and news by e-mail', (bool) $d['newsletter'], $d['canEdit'] ? 'ajax/sl/customer_profile_ajax.php' : '', 'newsletter', ['op' => 'pref', 'id' => (int) $d['id']], !$d['canEdit']) ?>
<?php if ($d['canEdit']): ?>
<?php if ($d['blocked']): ?>
  <div class="mx-tg"><span class="mx-rm"><b>Blocked</b><small>Stops new shipments and sign-in</small></span><button type="button" class="mx-sw on" role="switch" aria-checked="true" aria-label="Blocked" data-sl-save="ajax/sl/customer_status_ajax.php" data-key="blocked" data-p-op="unblock" data-p-id="<?= (int) $d['id'] ?>" data-sl-reload></button></div>
<?php else: ?>
  <div class="mx-tg"><span class="mx-rm"><b>Blocked</b><small>Stops new shipments and sign-in</small></span><button type="button" class="mx-sw" role="switch" aria-checked="false" aria-label="Blocked" aria-controls="cp-block" id="cp-block-sw"></button></div>
<?php endif; ?>
<?php endif; ?>
</section>
<?php if ($d['canEdit'] && !$d['blocked']): ?>
<form class="mx-card mx-form pp-hide" id="cp-block" data-sl-form data-sl-reload action="ajax/sl/customer_status_ajax.php" method="post" hidden>
  <input type="hidden" name="op" value="block"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
  <label class="mx-f"><small>Why block <?= h($d['name']) ?>?</small><select class="mx-in" name="reason" required><?php foreach (['Fake payment receipt', 'Abusive to staff', 'Unpaid over 90 days', 'Prohibited items', 'Duplicate account'] as $r): ?><option><?= h($r) ?></option><?php endforeach; ?></select></label>
  <div class="mx-tg"><span class="mx-rm"><b>Hold their parcels</b><small>At the warehouse until unblocked</small></span><button type="button" class="mx-sw on" role="switch" aria-checked="true" aria-label="Hold their parcels" data-input="#cp-hold"></button><input type="hidden" name="hold" id="cp-hold" value="1"></div>
  <div class="mx-tg"><span class="mx-rm"><b>Tell them by e-mail why</b></span><button type="button" class="mx-sw" role="switch" aria-checked="false" aria-label="Tell them by e-mail why" data-input="#cp-notify"></button><input type="hidden" name="notify" id="cp-notify" value="0"></div>
  <div class="mx-btns"><button type="submit" class="mx-btn">Block customer</button></div>
</form>
<script>
(function(){var sw=document.getElementById('cp-block-sw'),f=document.getElementById('cp-block');if(!sw||!f)return;
sw.addEventListener('mx:switch',function(e){f.hidden=!e.detail.on;if(e.detail.on)f.scrollIntoView({behavior:'smooth',block:'nearest'});});})();
</script>
<?php endif; ?>
</main>
