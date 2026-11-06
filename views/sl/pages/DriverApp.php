<?php
/**
 * DriverApp - from docs/handover/artifact-source/DriverApp.dc.html (plain, mobile).
 * Data: views/sl/data/riders.php (sl_driverAppData) for the logged-in rider.
 * Deliveries post to the legacy ajax/courier/add_courier_delivered_ajax.php (photo, receiver,
 * pickup-code gate, notifications); pickups and failed attempts to ajax/sl/rider_ajax.php.
 */
if (!isset($d)) { $d = []; }
$j = $d['job'] ?? null;
$tab = (string) ($_GET['tab'] ?? 'home');
?>
<div class="da">
<header class="da-top"><?php if ($j || $tab !== 'home'): ?><a class="da-ib" aria-label="Back" href="<?= sl_route('DriverApp') ?>"><i class="ic i-left" aria-hidden="true"></i></a><?php else: ?><span class="sl-av" style="width:44px;height:44px"><?= h($d['ini']) ?></span><?php endif; ?><h1><?= h($j ? $j['trk'] : ($tab === 'pay' ? 'My earnings' : ($tab === 'route' ? "Today's route" : 'Hi ' . explode(' ', $d['name'] ?: 'rider')[0]))) ?></h1><a class="da-ib" href="<?= sl_route('Notifications') ?>" aria-label="Alerts"><i class="ic i-bell" aria-hidden="true"></i><?php if (!empty($d['unread'])): ?><b><?= (int) $d['unread'] ?></b><?php endif; ?></a></header>
<div class="da-body">
<?php if (!$d['isRider']): ?>
<section class="da-sheet"><b class="fw5">This screen is for riders</b><span class="mut sm">Sign in with a driver account to see assigned pickups and deliveries. Staff can assign jobs on the dispatch board.</span><a class="da-pri" href="<?= sl_route('RiderDispatch') ?>">Open dispatch board</a><a class="da-sec" href="<?= sl_route('Main') ?>" style="flex:none">Back to overview</a></section>
<?php elseif ($j): ?>
<section class="da-sheet"><div class="da-row"><span class="sl-av" style="width:52px;height:52px"><?= h(sl_initials($j['cust'])) ?></span><span class="col grow"><b class="fw5" style="font-size:17px"><?= h($j['cust']) ?></b><span class="mut sm"><?= $j['pickup'] ? 'Pickup' : 'Delivery' ?><?= $j['phone'] ? ' · ' . h($j['phone']) : '' ?></span></span><span class="sl-pill <?= h($j['cls']) ?>"><?= h($j['st']) ?></span></div>
<div class="da-row"><i class="ic i-pin" aria-hidden="true"></i><span class="grow"><?= h($j['addr']) ?></span></div>
<div class="da-row" style="gap:8px"><?php if ($j['phone']): ?><a class="da-sec" href="tel:<?= h(preg_replace('/[^0-9+]/', '', $j['phone'])) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Call</a><a class="da-sec" href="https://wa.me/<?= h(preg_replace('/[^0-9]/', '', $j['phone'])) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp</a><?php endif; ?><a class="da-sec" href="https://maps.google.com/?q=<?= h(urlencode($j['addr'])) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-route" aria-hidden="true"></i>Navigate</a></div>
<div class="da-kv"><div><small>Tracking</small><b class="mono"><?= h($j['trk']) ?></b></div><div><small>Weight</small><b><?= h($j['kg']) ?> kg</b></div><div><small>Value</small><b><?= h($j['value']) ?></b></div><div><small>Delivery fee</small><b><?= h($j['fee']) ?></b></div><div><small>You get (<?= (int) $j['pct'] ?>%)</small><b><?= h($j['rid']) ?></b></div><div><small>Status</small><b><?= h($j['st']) ?></b></div></div></section>
<?php if ($j['state'] === 'open' && !$j['pickup']): ?>
<?php if ($j['st'] !== 'On route'): ?><button class="da-sec" style="flex:none" data-sl-action="ajax/sl/rider_ajax.php" data-p-op="onroute" data-p-id="<?= (int) $j['id'] ?>" data-sl-reload><i class="ic ic-sm i-truck" aria-hidden="true"></i>Start: I have the package</button><?php endif; ?>
<form class="da-sheet" id="da-deliver" enctype="multipart/form-data"><b class="fw5">Hand over</b>
<?php if ($d['codesOn']): ?><label class="sl-field"><span class="sl-lab">Customer's pickup code (if they have one)</span><input class="sl-in mono" name="code" inputmode="numeric" maxlength="6" placeholder="6 digits" autocomplete="off" style="font-size:22px;letter-spacing:.3em;text-align:center"></label><?php endif; ?>
<label class="sl-field"><span class="sl-lab">Who received it</span><input class="sl-in" name="person_receives" required placeholder="Full name"></label>
<label class="da-photo"><span><i class="ic i-image" aria-hidden="true"></i> <span id="da-photo-l">Take a photo of the hand-over</span></span><input type="file" name="miarchivo" accept="image/*" capture="environment" hidden></label>
<input type="hidden" name="shipment_id" value="<?= (int) $j['id'] ?>"><input type="hidden" name="driver_id" value="<?= (int) $_SESSION['userid'] ?>"><input type="hidden" name="handover_type" value="delivered"><input type="hidden" name="deliver_date" value="<?= date('Y-m-d') ?>"><input type="hidden" name="country" value=""><input type="hidden" name="address" value="<?= h($j['addr']) ?>"><input type="hidden" name="comments" value="Delivered by rider app">
<div class="da-bad" id="da-bad" hidden><i class="ic ic-sm i-x" aria-hidden="true"></i><span></span></div>
<button class="da-pri" type="submit"><i class="ic ic-sm i-check" aria-hidden="true"></i>Confirm delivery</button>
<button class="da-sec" type="button" style="flex:none" data-sl-action="ajax/sl/rider_ajax.php" data-p-op="failed" data-p-id="<?= (int) $j['id'] ?>" data-p-note="Customer not home" data-sl-confirm="Record a failed attempt?">Customer not home · try later</button></form>
<?php elseif ($j['state'] === 'open' && $j['pickup']): ?>
<form class="da-sheet" data-sl-form action="ajax/sl/rider_ajax.php" method="post" enctype="multipart/form-data" data-sl-redirect><b class="fw5">Collect the parcel</b><input type="hidden" name="op" value="collected"><input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
<label class="sl-field"><span class="sl-lab">Who handed it over</span><input class="sl-in" name="person" required placeholder="Full name"></label>
<label class="da-photo"><span><i class="ic i-image" aria-hidden="true"></i> <span>Photo of the parcel</span></span><input type="file" name="photo" accept="image/*" capture="environment" hidden></label>
<input class="sl-in" name="note" placeholder="Note (optional)"><button class="da-pri" type="submit"><i class="ic ic-sm i-check" aria-hidden="true"></i>Mark collected</button>
<button class="da-sec" type="button" style="flex:none" data-sl-action="ajax/sl/rider_ajax.php" data-p-op="failed" data-p-id="<?= (int) $j['id'] ?>" data-p-note="Customer not available" data-sl-confirm="Record a failed attempt?">Customer not there · try later</button></form>
<?php else: ?><div class="da-ok"><i class="ic i-check" aria-hidden="true"></i><span><?= h($j['st']) ?><?= $j['receiver'] ? ' to ' . h($j['receiver']) : '' ?>. <?= h($j['rid']) ?> added to today's earnings.</span></div><a class="da-pri" href="<?= sl_route('DriverApp') ?>">Next package</a><?php endif; ?>
<?php elseif ($tab === 'pay'): ?>
<section class="da-sheet"><span class="up">Earned today</span><b style="font:600 38px var(--font);letter-spacing:-.02em"><?= h($d['earned']) ?></b><span class="mut sm"><?= (int) $d['done'] ?> jobs · <?= (int) $d['pct'] ?>% of <?= h($d['feesToday']) ?> in fees</span><div class="da-bars"><?php foreach ($d['week'] as $w): ?><i class="<?= h($w['cls']) ?>" style="<?= h($w['h']) ?>" title="<?= h($w['t']) ?>"></i><?php endforeach; ?></div><div class="row between mut" style="font-size:11.5px"><?php foreach ($d['week'] as $w): ?><span><?= h($w['l']) ?></span><?php endforeach; ?></div></section>
<div class="da-k"><div><b><?= h($d['weekEarned']) ?></b><small>Last 7 days</small></div><div><b><?= (int) $d['done'] ?></b><small>Done today</small></div><div><b><?= (int) $d['left'] ?></b><small>Still to do</small></div></div>
<?php else: ?>
<div class="da-k"><div><b><?= (int) $d['left'] ?></b><small>To do</small></div><div><b><?= (int) $d['done'] ?></b><small>Done</small></div><div><b><?= h($d['earned']) ?></b><small>Earned today</small></div></div>
<?php if ($d['next'] && $tab === 'home'): $n = $d['next']; ?><section class="da-next"><div class="in"><div class="da-row"><span class="up grow">Next stop</span><span class="sl-pill <?= h($n['cls']) ?>"><?= h($n['st']) ?></span></div><div class="da-row"><span class="col grow"><b class="fw5" style="font-size:17px"><?= h($n['cust']) ?></b><span class="mut sm"><?= h($n['addr']) ?></span></span><span class="da-fee"><?= h($n['fee']) ?><small>you get <?= h($n['rid']) ?></small></span></div>
<div class="da-row" style="gap:8px"><?php if ($n['phone']): ?><a class="da-sec" href="tel:<?= h(preg_replace('/[^0-9+]/', '', $n['phone'])) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Call</a><?php endif; ?><a class="da-sec" href="<?= h(sl_url(['job' => $n['id']])) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Details</a></div>
<a class="da-pri" href="<?= h(sl_url(['job' => $n['id']])) ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i><?= $n['pickup'] ? 'Collect' : 'Deliver' ?></a></div></section><?php endif; ?>
<div class="da-row"><b class="fw5 grow">My packages</b><span class="mut sm"><?= (int) $d['total'] ?> today</span></div>
<div class="da-seg"><?php foreach ($d['segs'] as $g): ?><a class="<?= $g['k'] === $d['seg'] ? 'on' : '' ?>" href="<?= h(sl_url(['seg' => $g['k']])) ?>"><?= h($g['label']) ?></a><?php endforeach; ?></div>
<?php foreach ($d['jobs'] as $i => $p): ?><a class="da-pk" href="<?= h(sl_url(['job' => $p['id']])) ?>" style="color:inherit"><span class="da-n <?= $p['state'] === 'open' ? '' : 'done' ?>"><?= $i + 1 ?></span><span class="m"><b class="fw5"><?= h($p['cust']) ?></b><small><?= h($p['addr']) ?></small><small class="mono"><?= h($p['trk']) ?> · <?= $p['pickup'] ? 'pickup' : 'delivery' ?></small></span><span class="col" style="align-items:flex-end;gap:6px"><span class="sl-pill <?= h($p['cls']) ?>"><?= h($p['st']) ?></span><span class="da-fee"><?= h($p['fee']) ?></span></span></a><?php endforeach; ?>
<?php if (!$d['jobs']): ?><div class="da-sheet mut sm">Nothing here. New jobs appear when the office assigns them.</div><?php endif; ?>
<?php endif; ?>
</div>
<?php if ($d['isRider']): ?><nav class="da-tab" aria-label="Rider app"><a class="<?= $tab === 'home' && !$j ? 'on' : '' ?>" href="<?= sl_route('DriverApp') ?>"><i class="ic i-box" aria-hidden="true"></i>Jobs</a><a class="<?= $tab === 'pay' ? 'on' : '' ?>" href="<?= sl_route('DriverApp', ['tab' => 'pay']) ?>"><i class="ic i-dollar" aria-hidden="true"></i>Earnings</a><a href="<?= sl_route('Notifications') ?>"><i class="ic i-bell" aria-hidden="true"></i>Alerts</a><a href="logout.php"><i class="ic i-logout" aria-hidden="true"></i>Sign out</a></nav><?php endif; ?>
</div>
<script>
(function () {
  document.querySelectorAll('.da-photo input[type=file]').forEach(function (inp) { inp.addEventListener('change', function () { var l = inp.closest('.da-photo').querySelector('span span, span'); if (inp.files[0]) { inp.closest('.da-photo').classList.add('on'); l.lastChild.textContent = ' Photo added: ' + inp.files[0].name; } }); });
  var f = document.getElementById('da-deliver'); if (!f) return;
  var bad = document.getElementById('da-bad');
  function fail(m) { bad.hidden = false; bad.querySelector('span').textContent = m; }
  f.addEventListener('submit', function (e) {
    e.preventDefault(); bad.hidden = true;
    var btn = f.querySelector('[type=submit]'); btn.disabled = true;
    var code = f.querySelector('[name=code]'), id = f.querySelector('[name=shipment_id]').value;
    var step = code && code.value.trim() ? SL.post('ajax/pickup_code/pickup_code_ajax.php', { action: 'verify', module: 'air', 'order_ids[]': id, code: code.value.trim() }) : Promise.resolve({ success: true });
    step.then(function (j) {
      if (!j.success) throw new Error(j.message || 'That code does not match');
      var fd = new FormData(f); fd.delete('code');
      return SL.post('ajax/courier/add_courier_delivered_ajax.php', fd);
    }).then(function (j) {
      if (!j.success) { var er = j.errors || {}; throw new Error(Object.keys(er).map(function (k) { return er[k]; }).join(' ') || j.message || 'Could not deliver'); }
      SL.toast('Delivered'); setTimeout(function () { location.href = location.pathname; }, 500);
    }).catch(function (err) { fail(err.message); }).finally(function () { btn.disabled = false; });
  });
})();
</script>
