<?php
/**
 * M-ShipTrack: update tracking (courier_shipment_tracking.php?id=). Same $d as the web page
 * (views/sl/data/shipments.php sl_shipmentTrackingData). The form posts to the web page's endpoint,
 * ajax/courier/add_courier_tracking.php, with its field names: shipment_id, status_courier, t_date, office, country,
 * address, flight_no, awb_no, comments, notify_whatsapp_sender, notify_sms_sender, notify_sms_receiver.
 * One shipment per update (that endpoint takes one); the design's "override locked status" is left out (no such field).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Shipments';
$id = (int) $d['id'];
$sel = function (string $name, string $options, string $label) { return '<label class="mx-f"><small>' . h($label) . '</small><span class="wh-selw"><select class="mx-in" name="' . h($name) . '" required>' . $options . '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>'; };
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-wh.css')) ?>">
<?= sl_phoneTop('Update tracking', $id ? $d['track'] . ' · ' . $d['status'] : '', ['back' => $id ? sl_route('ShipmentDetail', ['id' => $id]) : 'menu.php'], $id ? sl_phoneIcon('eye', 'Customer view', 'track.php?order_track=' . rawurlencode($d['track'])) : '') ?>
<main class="mx-body">
<form method="get" class="mx-srch" role="search"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Scan or type a tracking number" placeholder="Scan or type a tracking number" autocapitalize="characters" enterkeyhint="search"></label><a class="mx-ib" href="<?= h(sl_route('PackageIntake')) ?>" aria-label="Scan"><i class="ic i-scan" aria-hidden="true"></i></a></form>
<?php if ($d['flash'] !== ''): ?><div class="mx-ban warn" role="status"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= h($d['flash']) ?></span></div><?php endif; ?>
<?php if (!$id): ?>
<?php $items = array_values(array_filter($d['tracking']['items'] ?? [], fn($it) => $it['mode'] === 'air')); ?>
<?= sl_mxSec('Pick a shipment to update') ?>
<?php if ($items || $d['recent']): ?>
<section class="mx-card mx-list">
<?php if ($items): foreach ($items as $it): ?>
<a class="mx-row" href="<?= h(sl_url(['id' => $it['id'], 'q' => null])) ?>"><span class="mx-li"><i class="ic i-box" aria-hidden="true"></i></span><span class="mx-rm"><b class="mono"><?= h($it['code']) ?></b><small><?= h($it['customer']) ?> · <?= h($it['created']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($it['cls']) ?>"><?= h($it['st']) ?></span></span></a>
<?php endforeach; else: foreach ($d['recent'] as $r): ?>
<a class="mx-row" href="<?= h(sl_url(['id' => $r['id'], 'q' => null])) ?>"><span class="mx-li"><i class="ic i-box" aria-hidden="true"></i></span><span class="mx-rm"><b class="mono"><?= h($r['track']) ?></b><small><?= h($r['who']) ?> · <?= h($r['date']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($r['pc']) ?>"><?= h($r['st']) ?></span></span></a>
<?php endforeach; endif; ?>
</section>
<?php else: ?><?= sl_mxEmpty('box', 'No open shipments') ?><?php endif; ?>
<?php else: ?>
<?php if ($d['pkgs']): ?><div class="mx-chips"><?php foreach ($d['pkgs'] as $p): ?><a href="<?= h(sl_url(['id' => $p['id'], 'q' => null])) ?>" style="padding:6px 11px;border-radius:99px;font-size:12px;font-weight:500;min-height:32px;display:inline-flex;align-items:center;background:<?= $p['cls'] === 'on' ? 'var(--ink);color:var(--inverse)' : 'var(--active);color:var(--ink-2)' ?>"<?= $p['cls'] === 'on' ? ' aria-current="true"' : '' ?>><?= h($p['track']) ?></a><?php endforeach; ?></div><?php endif; ?>
<?php if (!empty($d['canTrack'])): ?>
<?= sl_mxSec('Update ' . $d['track']) ?>
<form data-sl-form action="ajax/courier/add_courier_tracking.php" method="post" id="tk-form" class="sp-panel" style="display:flex;flex-direction:column;gap:12px">
<input type="hidden" name="shipment_id" value="<?= $id ?>">
<section class="mx-card mx-form">
<?= $sel('status_courier', implode('', array_map(fn($s) => '<option value="' . (int) $s->id . '"' . ((int) $s->id === $d['statusId'] ? ' selected' : '') . '>' . h(str_replace('_', ' ', $s->mod_style)) . '</option>', $d['statuses'])), 'New status') ?>
<?= $sel('office', implode('', array_map(fn($o) => '<option value="' . (int) $o->id . '"' . ((int) $o->id === $d['office'] ? ' selected' : '') . '>' . h($o->name_off) . '</option>', $d['offices'])), 'Office') ?>
<label class="mx-f"><small>Location</small><input class="mx-in" name="address" placeholder="Where the parcel is now" required autocomplete="off"></label>
<?= $sel('country', implode('', array_map(fn($c) => '<option value="' . h($c->name) . '">' . h($c->name) . '</option>', $d['countries'])), 'Country') ?>
<label class="mx-f"><small>Date</small><input class="mx-in" type="date" name="t_date" value="<?= h(date('Y-m-d')) ?>" required></label>
<div class="mx-two"><label class="mx-f"><small>Flight number</small><input class="mx-in mono" name="flight_no" autocomplete="off" autocapitalize="characters"></label><label class="mx-f"><small>AWB number</small><input class="mx-in mono" name="awb_no" autocomplete="off"></label></div>
<label class="mx-f"><small>Note for customer</small><textarea class="mx-in" name="comments" maxlength="250"></textarea></label>
</section>
<section class="mx-card mx-tgs">
<div class="mx-tg"><span class="mx-rm"><b>Notify customer</b><small>WhatsApp to the sender</small></span><button type="button" class="mx-sw on" role="switch" aria-checked="true" aria-label="Notify customer on WhatsApp" data-input="#tk-wa"></button><input type="hidden" name="notify_whatsapp_sender" id="tk-wa" value="1"></div>
<div class="mx-tg"><span class="mx-rm"><b>SMS to the sender</b></span><button type="button" class="mx-sw" role="switch" aria-checked="false" aria-label="SMS to the sender" data-input="#tk-ss"></button><input type="hidden" name="notify_sms_sender" id="tk-ss" value="0"></div>
<div class="mx-tg"><span class="mx-rm"><b>SMS to the recipient</b></span><button type="button" class="mx-sw" role="switch" aria-checked="false" aria-label="SMS to the recipient" data-input="#tk-sr"></button><input type="hidden" name="notify_sms_receiver" id="tk-sr" value="0"></div>
</section>
<div class="mx-btns"><button type="submit" class="mx-btn">Update <?= h($d['track']) ?></button></div>
</form>
<?php else: ?>
<div class="mx-ban info"><i class="ic ic-sm i-lock" aria-hidden="true"></i><span>This shipment cannot be updated (cancelled, or you lack the tracking permission).</span></div>
<?php endif; ?>
<?= sl_mxSec('Latest events') ?>
<?php if ($d['ms']): ?>
<section class="mx-card"><ol class="mx-tl"><?php foreach (array_slice($d['ms'], 0, 6) as $i => $m): ?><li class="<?= $i === 0 ? 'cur' : 'done' ?>"><span class="mx-td"><i class="ic ic-sm <?= h($m['icon']) ?>" aria-hidden="true"></i></span><div><b><?= h($m['t']) ?></b><small><?= h($m['date'] . ' ' . $m['time']) ?><?= $m['where'] !== '' ? ' · ' . h($m['where']) : '' ?></small></div></li><?php endforeach; ?></ol></section>
<?php else: ?><?= sl_mxEmpty('route', 'No events yet') ?><?php endif; ?>
<?php endif; ?>
</main>
<script>
document.addEventListener('sl:done', function (e) {
  var f = e.target; if (!f.matches || !f.matches('#tk-form')) return;
  var j = e.detail || {};
  if (j.success) { SL.toast((j.messages || ['Event saved']).join(' ')); setTimeout(function () { location.reload(); }, 400); }
  else if (j.errors) { var m = Object.keys(j.errors).map(function (k) { return j.errors[k]; }).join(' '); if (m) SL.toast(m, true); }
}, true);
</script>
