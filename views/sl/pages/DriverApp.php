<?php
/**
 * Rider home (design: M-RHome) and, with ?job=ID, the handover / collection form of one assigned shipment.
 * Data: views/sl/data/rider_ops.php (ro_data) + sl_driverAppData() for the job. The phone template
 * (views/sl/phone/DriverApp.php) includes this file with $roPhone set; a computer gets the same blocks in two columns.
 *
 * Left out because the system records nothing for them: demand heat map, boosts, acceptance rate, rating,
 * the offer countdown. Deliveries post to the legacy ajax/courier/add_courier_delivered_ajax.php (photo, receiver,
 * pickup-code gate, notifications); pickups and failed attempts to ajax/sl/rider_ajax.php.
 */
if (!isset($d)) { $d = []; }
$ph = !empty($roPhone);
if (empty($d['isRider'])) { ro_notRider($ph, 'Rider app'); return; }
$j = $d['job'] ?? null;

/* ---------- one assigned shipment or pickup: details and handover ---------- */
if ($j || !empty($d['missingJob'])):
    $slPhone['tab'] = 'Jobs';
    echo ro_open($ph, $j ? ($j['pickup'] ? 'Pickup' : 'Drop-off') : 'Job', $j ? $j['trk'] : '', ['back' => sl_route('RiderQueue')], sl_mxIcon('shield', 'Safety', sl_route('RiderSafety')));
    if (!$j):
        echo sl_mxEmpty('box', 'This job is no longer yours', 'It was finished or dispatch moved it to someone else.', 'Back to my jobs', sl_route('RiderQueue'));
        echo ro_close($ph);
        return;
    endif;
    $nav = ro_navUrl((int) $d['id'], $j['addr'] === '—' ? '' : (string) $j['addr']);
    $tel = ro_tel((string) $j['phone']);
    $wa = sl_riderMessageLink((string) $j['phone']);
?>
<div class="rs-oid"><div><b><?= h($j['trk']) ?></b><span><?= h($j['addr']) ?></span></div><span class="rs-st<?= $j['state'] === 'open' ? '' : ' ok' ?>"><?= h($j['st']) ?></span></div>
<div class="mxp-grid">
<div class="mxp-col">
<section class="mx-card rs-cust"><span class="mx-ava"><?= h(sl_initials($j['cust'])) ?></span><span class="mx-rm"><b><?= h($j['cust']) ?></b><small><?= $j['pickup'] ? 'Hands the parcel to you' : 'Receives the parcel' ?><?= $j['phone'] ? ' · ' . h($j['phone']) : '' ?></small></span><?php if ($nav !== ''): ?><a class="rs-rb" href="<?= h($nav) ?>" target="_blank" rel="noopener" aria-label="Directions in your maps app"><i class="ic i-route" aria-hidden="true"></i></a><?php endif; ?></section>
<?php if ($tel !== '' || $wa !== ''): ?><div class="rs-pair"><?php if ($tel !== ''): ?><a class="mx-btn rs-amb" href="<?= h($tel) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Call</a><?php endif; ?><?php if ($wa !== ''): ?><a class="mx-btn sec rs-out" href="<?= h($wa) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp</a><?php else: ?><a class="mx-btn sec rs-out" href="<?= h(sl_route('RiderNav', ['id' => 'o' . (int) $j['id']])) ?>"><i class="ic ic-sm i-pin" aria-hidden="true"></i>Address</a><?php endif; ?></div><?php endif; ?>
<section class="ra-fx"><div><span><i class="ic ic-sm i-box" aria-hidden="true"></i></span><small>Weight</small><b><?= h($j['kg']) ?> kg</b></div><div><span><i class="ic ic-sm i-receipt" aria-hidden="true"></i></span><small>Delivery fee</small><b><?= h($j['fee']) ?></b></div><div><span><i class="ic ic-sm i-dollar" aria-hidden="true"></i></span><small>You get (<?= (int) $j['pct'] ?>%)</small><b><?= h($j['rid']) ?></b></div></section>
</div>
<div class="mxp-col">
<?php if ($j['state'] === 'open' && !$j['pickup']): ?>
<?php if ($j['st'] !== 'On route'): ?><button type="button" class="mx-btn sec ro-btn" data-sl-action="ajax/sl/rider_ajax.php" data-p-op="onroute" data-p-id="<?= (int) $j['id'] ?>" data-sl-reload><i class="ic ic-sm i-truck" aria-hidden="true"></i>I have the parcel · start delivery</button><?php endif; ?>
<form class="ro-form" id="da-deliver" enctype="multipart/form-data" data-done="<?= h(sl_route('RiderDone', ['id' => 'o' . (int) $j['id']])) ?>">
<div class="mx-sec"><h2>Hand over</h2></div>
<?php if (!empty($d['codesOn'])): ?><label class="mx-f"><small>Customer’s pickup code (if they have one)</small><input class="ro-in ro-pin" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="······" autocomplete="one-time-code"></label><?php endif; ?>
<label class="mx-f"><small>Who received it</small><input class="ro-in" name="person_receives" required maxlength="150" autocomplete="name" placeholder="Full name"></label>
<label class="ro-photo"><i class="ic i-image" aria-hidden="true"></i><span data-ro-photo-label>Take a photo of the handover</span><input type="file" name="miarchivo" accept="image/*" capture="environment" aria-label="Handover photo"></label>
<input type="hidden" name="shipment_id" value="<?= (int) $j['id'] ?>"><input type="hidden" name="driver_id" value="<?= (int) $d['id'] ?>"><input type="hidden" name="handover_type" value="delivered"><input type="hidden" name="deliver_date" value="<?= h(date('Y-m-d')) ?>"><input type="hidden" name="country" value=""><input type="hidden" name="address" value="<?= h($j['addr']) ?>"><input type="hidden" name="comments" value="Delivered by rider app">
<div class="ro-bad" id="da-bad" role="alert" tabindex="-1" hidden></div>
<button class="mx-btn" type="submit"><i class="ic ic-sm i-check" aria-hidden="true"></i>Confirm delivery</button>
<button class="mx-btn sec" type="button" data-sl-action="ajax/sl/rider_ajax.php" data-p-op="failed" data-p-id="<?= (int) $j['id'] ?>" data-p-note="Customer not home" data-sl-confirm="Record a failed attempt? Dispatch will see it.">Customer not home · try later</button>
</form>
<?php elseif ($j['state'] === 'open'): ?>
<form class="ro-form" data-sl-form data-sl-reload action="ajax/sl/rider_ajax.php" method="post" enctype="multipart/form-data">
<div class="mx-sec"><h2>Collect the parcel</h2></div>
<input type="hidden" name="op" value="collected"><input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
<label class="mx-f"><small>Who handed it over</small><input class="ro-in" name="person" required maxlength="150" autocomplete="name" placeholder="Full name"></label>
<label class="ro-photo"><i class="ic i-image" aria-hidden="true"></i><span data-ro-photo-label>Photo of the parcel (optional)</span><input type="file" name="photo" accept="image/*" capture="environment" aria-label="Photo of the parcel"></label>
<label class="mx-f"><small>Note (optional)</small><input class="ro-in" name="note" maxlength="250"></label>
<button class="mx-btn" type="submit"><i class="ic ic-sm i-check" aria-hidden="true"></i>Mark collected</button>
<a class="mx-btn sec" href="<?= h(sl_route('RiderScan', ['id' => 'o' . (int) $j['id']])) ?>"><i class="ic ic-sm i-scan" aria-hidden="true"></i>Scan the label first</a>
<button class="mx-btn sec" type="button" data-sl-action="ajax/sl/rider_ajax.php" data-p-op="failed" data-p-id="<?= (int) $j['id'] ?>" data-p-note="Customer not available" data-sl-confirm="Record a failed attempt? Dispatch will see it.">Customer not there · try later</button>
</form>
<?php else: ?>
<div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= h($j['st']) ?>. Contact dispatch to arrange another attempt.</span></div>
<div class="mx-btns"><a class="mx-btn" href="<?= h(sl_route('RiderQueue')) ?>">Back to my jobs</a></div>
<?php endif; ?>
</div>
</div>
<script>
(function () {
  document.querySelectorAll('.ro-photo input[type=file]').forEach(function (inp) {
    inp.addEventListener('change', function () { var box = inp.closest('.ro-photo'), l = box.querySelector('[data-ro-photo-label]'); if (inp.files[0]) { box.classList.add('on'); l.textContent = 'Photo added: ' + inp.files[0].name; } });
  });
  var f = document.getElementById('da-deliver'); if (!f) return;
  var bad = document.getElementById('da-bad');
  function fail(m) { bad.hidden = false; bad.textContent = m; bad.focus(); }
  f.addEventListener('submit', function (e) {
    e.preventDefault(); if (f.getAttribute('aria-busy') === 'true') return; f.setAttribute('aria-busy', 'true'); bad.hidden = true;
    var btn = f.querySelector('[type=submit]'); btn.disabled = true;
    var code = f.querySelector('[name=code]'), id = f.querySelector('[name=shipment_id]').value;
    var step = code && code.value.trim() ? SL.post('ajax/pickup_code/pickup_code_ajax.php', { action: 'verify', module: 'air', 'order_ids[]': id, code: code.value.trim() }) : Promise.resolve({ success: true });
    step.then(function (j) {
      if (!j.success) throw new Error(j.message || 'That code does not match. Do not hand over the parcel.');
      var fd = new FormData(f); fd.delete('code');
      return SL.post('ajax/courier/add_courier_delivered_ajax.php', fd);
    }).then(function (j) {
      if (!j.success) { var er = j.errors || {}; throw new Error(Object.keys(er).map(function (k) { return er[k]; }).join(' ') || j.message || 'The delivery was not saved. Try again.'); }
      SL.toast('Delivered'); setTimeout(function () { location.href = f.getAttribute('data-done'); }, 500);
    }).catch(function (err) { fail(err.message); }).finally(function () { btn.disabled = false; f.removeAttribute('aria-busy'); });
  });
})();
</script>
<?php
    echo ro_close($ph);
    return;
endif;

/* ---------- home ---------- */
$duty = $d['duty'];
$next = $d['next'];
$newN = count($d['new']);
$week = $d['week'] ?? [];
$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$shift = $d['shift'] ?? null;
if ($ph):
    $slPhone['cls'] = 'rs-r rs-nohd';
    $slPhone['tab'] = 'Home';
    $slPhone['scripts'][] = 'dataJs/sl/rider_map.js';
?>
<main class="mx-body" id="sl-main" tabindex="-1">
<header class="rs-hd"><a class="mx-av ro-ava" href="<?= h(sl_route('RiderAccount')) ?>" aria-label="My account<?= $duty['online'] ? ', online' : ', offline' ?>"><?= h($d['ini']) ?><?php if ($duty['online']): ?><i aria-hidden="true"></i><?php endif; ?></a><div class="rs-hi"><small><i class="ic ic-sm i-sun" aria-hidden="true"></i><?= h($greet) ?></small><b><?= h($d['first']) ?></b></div><a class="rs-chat" href="<?= h(sl_route('RiderChat')) ?>">Chat</a><a class="rs-rb rs-bell" href="<?= h(sl_route('Notifications')) ?>" aria-label="Notifications<?= $d['unread'] ? ', ' . (int) $d['unread'] . ' new' : '' ?>"><i class="ic i-bell" aria-hidden="true"></i><?php if ($d['unread']): ?><b aria-hidden="true"><?= $d['unread'] > 99 ? '99+' : (int) $d['unread'] ?></b><?php endif; ?></a></header>
<?php else:
    echo ro_open(false, 'Hi, ' . $d['first'], $d['sub'], ['avatar' => $d['ini'], 'href' => sl_route('RiderAccount'), 'online' => $duty['online']], ro_topActions($d));
endif; ?>
<div class="mxp-grid">
<div class="mxp-col">
<h2 class="rs-hl">Today’s deliveries<span><?= count($d['open']) ?> to go · <?= (int) $d['trips'] ?> done.</span></h2>
<div class="rs-sr"><form class="rs-sp" method="get" action="<?= h(sl_route('RiderScan')) ?>" role="search"><i class="ic i-search" aria-hidden="true"></i><input name="q" aria-label="Check a tracking number against your jobs" placeholder="Check a tracking number" autocomplete="off" autocapitalize="characters" maxlength="60" enterkeyhint="search"></form><a class="rs-scan" href="<?= h(sl_route('RiderScan')) ?>" aria-label="Scan a parcel label"><i class="ic i-scan" aria-hidden="true"></i></a></div>
<div class="mx-sec rs-sec"><h2>Delivery overview</h2><a href="<?= h(sl_route('RiderQueue')) ?>">See all</a></div>
<section class="rs-ovw">
<div class="rs-ot"><div class="rs-oth"><span class="rs-ob"><i class="ic i-box" aria-hidden="true"></i></span></div><b class="rs-ov"><?= (int) $d['trips'] ?></b><small>Deliveries today</small><div class="rs-vs"><span><?= $week ? (int) $week['yesterday']['n'] . ' yesterday' : count($d['open']) . ' to go' ?></span><a class="rs-go" href="<?= h(sl_route('RiderQueue')) ?>" aria-label="Open my jobs"><i class="ic ic-sm i-arrow" aria-hidden="true"></i></a></div></div>
<div class="rs-ot"><div class="rs-oth"><span class="rs-ob"><i class="ic i-dollar" aria-hidden="true"></i></span></div><b class="rs-ov"><?= h($d['earnedTxt']) ?></b><small>Earned today</small><div class="rs-vs"><span><?= $week ? h($week['yesterday']['txt']) . ' yesterday' : 'Your share' ?></span><a class="rs-go" href="<?= h(sl_route('RiderEarnings')) ?>" aria-label="Open earnings"><i class="ic ic-sm i-arrow" aria-hidden="true"></i></a></div></div>
</section>
<div class="rs-onw"><section class="ra-on<?= $duty['online'] ? '' : ' ro-off' ?>">
<div class="ra-onh"><span class="ra-pulse" aria-hidden="true"></span><span class="mx-rm"><b><?= $duty['suspended'] ? 'Your account is suspended' : ($duty['online'] ? 'You’re online' : 'You’re offline') ?></b><small><?= $duty['suspended'] ? 'Contact the office' : ($duty['online'] ? 'Dispatch can send you jobs' : 'No new door deliveries until you go online') ?></small></span><?php if ($duty['since'] !== ''): ?><span class="ra-onm">since <?= h(sl_date($duty['since'], date('Y-m-d', strtotime($duty['since'])) === date('Y-m-d') ? 'H:i' : 'M j')) ?></span><?php endif; ?></div>
<?php if ($duty['ready'] && !$duty['suspended']): ?><button type="button" class="ra-big" role="switch" aria-checked="<?= $duty['online'] ? 'true' : 'false' ?>" aria-label="<?= $duty['online'] ? 'Online. Tap to go offline' : 'Offline. Tap to go online' ?>" data-sl-action="ajax/sl/rider_ops_ajax.php" data-p-op="online" data-p-value="<?= $duty['online'] ? '0' : '1' ?>"><span class="<?= $duty['online'] ? '' : 'on' ?>">Offline</span><span class="<?= $duty['online'] ? 'on' : '' ?>"><i class="ic ic-sm i-bolt" aria-hidden="true"></i>Online</span></button><?php endif; ?>
<div class="ra-stat"><div><b><?= (int) $d['trips'] ?></b><small>Trips</small></div><div><b><?= count($d['open']) ?></b><small>To do</small></div><div><b><?= (int) $d['parcelsOpen'] ?></b><small>Parcels</small></div><div><b><?= (int) $d['pct'] ?>%</b><small>Your share</small></div></div>
</section></div>
<?php if ($newN): $first = $d['new'][0]; ?>
<a class="ra-ofs" href="<?= h($newN === 1 ? $first['offer'] : sl_route('RiderTasks')) ?>"><span class="mx-li"><i class="ic i-inbox" aria-hidden="true"></i></span><span class="mx-rm"><b><?= $newN === 1 ? 'New job · ' . h($first['payTxt']) : $newN . ' jobs to start' ?></b><small><?= h($first['name'] . ' · ' . ($first['addr'] ?: 'address not supplied')) ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php endif; ?>
</div>
<div class="mxp-col">
<?php if ($week): ?>
<section class="rs-wk"><div class="rs-wkh"><b>Weekly earnings overview</b><a class="rs-dd" href="<?= h(sl_route('RiderEarnings')) ?>"><?= h($week['total']) ?><i class="ic ic-sm i-right" aria-hidden="true"></i></a></div>
<div class="rs-wkc"><div class="rs-y" aria-hidden="true"><?php for ($i = 4; $i >= 0; $i--): ?><small><?= h(sl_num($week['top'] * $i / 4)) ?></small><?php endfor; ?></div>
<div class="rs-cols"><?php foreach ($week['days'] as $w): ?><a class="rs-bar <?= h($w['cls']) ?>" href="<?= h(sl_route('RiderEarnings')) ?>" aria-label="<?= h($w['date'] . ': ' . ($w['cls'] === 'fut' ? 'no trips yet' : $w['txt'] . ', ' . $w['n'] . ' trips')) ?>"><span style="height:<?= (int) $w['h'] ?>%"><?php if ($w['cls'] === 'now'): ?><span class="rs-tip" role="tooltip"><small><?= h($w['date']) ?></small><span><i>Earned</i><b><?= h($w['txt']) ?></b></span><span><i>Trips</i><b><?= (int) $w['n'] ?></b></span></span><?php endif; ?></span><small><?= h($w['day']) ?></small></a><?php endforeach; ?></div></div></section>
<?php endif; ?>
<div class="mx-sec rs-sec"><h2>Next stop</h2><a href="<?= h(sl_route('RiderMap')) ?>">Open map</a></div>
<?php if ($next): ?>
<section class="mx-card rs-cust"><span class="mx-ava"><?= h($next['ini']) ?></span><a class="mx-rm" href="<?= h($next['href']) ?>"><b><?= h($next['name']) ?></b><small><?= h($next['kindTxt'] . ' · ' . ($next['addr'] ?: 'address not supplied')) ?></small></a><?php if ($next['nav'] !== ''): ?><a class="rs-rb" href="<?= h($next['nav']) ?>" target="_blank" rel="noopener" aria-label="Directions in your maps app"><i class="ic i-route" aria-hidden="true"></i></a><?php endif; ?></section>
<a class="ra-swipe" href="<?= h($next['href']) ?>"><span class="ra-knob"><i class="ic i-right" aria-hidden="true"></i></span><span class="ra-swt"><b><?= $next['state'] === 'new' ? 'See the job and start' : ($next['pickup'] ? 'Go to the pickup' : 'Continue the delivery') ?></b><small><?= h($next['ref'] . ' · ' . $next['nTxt'] . ' · you get ' . $next['payTxt']) ?></small></span></a>
<?php else: ?>
<?= sl_mxEmpty('check', 'You’re all caught up', 'Your next job appears here when dispatch assigns it.') ?>
<?php endif; ?>
<a class="mx-card ra-en" href="<?= h(sl_route('RiderTasks')) ?>"><span class="mx-li"><i class="ic i-inbox" aria-hidden="true"></i></span><span class="mx-rm"><b>Task list</b><small><?= $newN ? $newN . ' assigned, not started' : 'Nothing waiting to start' ?></small></span><?php if ($newN): ?><span class="mx-cnt"><?= $newN ?></span><?php endif; ?><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php if ($shift): ?><a class="mx-card ra-en" href="<?= h(sl_route('RiderShifts')) ?>"><span class="mx-li"><i class="ic i-calendar" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($shift['when']) ?></b><small><?= h($shift['hub'] ?: 'Your next shift') ?></small></span><span class="sl-pill delivered">Booked</span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a><?php endif; ?>
<?php if ($d['failed']): ?><a class="mx-card ra-en" href="<?= h(sl_route('RiderQueue')) ?>"><span class="mx-li"><i class="ic i-alert" aria-hidden="true"></i></span><span class="mx-rm"><b><?= count($d['failed']) ?> with dispatch</b><small>Failed attempts waiting for a decision</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a><?php endif; ?>
<p class="mx-note">You keep <?= (int) $d['pct'] ?>% of the delivery fee on assigned shipments; door deliveries show your share on the job. Payouts are in <a href="<?= h(sl_route('RiderWallet')) ?>">Wallet</a>.</p>
</div>
</div>
<?= ro_close($ph) ?>
