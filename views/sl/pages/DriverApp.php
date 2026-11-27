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
if ($tab === 'today') $tab = 'home';
if (!in_array($tab, ['home', 'route', 'load', 'pay'], true)) $tab = 'home';
$rv = $d['rv'] ?? null;
$layout = (string) ($d['layout'] ?? 'auto');
$titles = ['home' => 'Hi ' . explode(' ', ($d['name'] ?? '') ?: 'rider')[0], 'route' => "Today's route", 'load' => 'Load parcels', 'pay' => 'My earnings'];
?>
<div class="da<?= $layout !== 'auto' ? ' is-' . h($layout) : '' ?>" data-layout="<?= h($layout) ?>" data-tab="<?= h($j ? 'job' : $tab) ?>">
<script>/* Device check before first paint (assets/swiftlane/rider-device.js keeps it in sync): phones and tablets get the phone layout, desktops the web layout, unless the rider chose one. */
(function (el) { var m = el.getAttribute('data-layout') || 'auto'; try { if (m === 'auto') m = localStorage.getItem('sl.rider.layout') || 'auto'; } catch (e) {}
  var phone = (window.matchMedia && matchMedia('(pointer:coarse)').matches && Math.min(screen.width, screen.height) <= 1024) || !!(navigator.userAgentData && navigator.userAgentData.mobile);
  var r = m === 'phone' || m === 'web' ? m : (phone ? 'phone' : 'web'); document.documentElement.setAttribute('data-rider-layout', r); el.classList.remove('is-phone', 'is-web'); el.classList.add('is-' + r); })(document.currentScript.parentNode);</script>
<header class="da-top"><?php if ($j || $tab !== 'home'): ?><a class="da-ib" aria-label="Back" href="<?= sl_route('DriverApp') ?>"><i class="ic i-left" aria-hidden="true"></i></a><?php else: ?><span class="sl-av" style="width:44px;height:44px"><?= h($d['ini']) ?></span><?php endif; ?><h1><?= h($j ? $j['trk'] : $titles[$tab]) ?></h1><a class="da-ib" href="<?= sl_route('Notifications') ?>" aria-label="Alerts"><i class="ic i-bell" aria-hidden="true"></i><?php if (!empty($d['unread'])): ?><b><?= (int) $d['unread'] ?></b><?php endif; ?></a></header>
<div class="rr-offline" data-rr-offline role="status" hidden><i class="ic ic-sm i-alert" aria-hidden="true"></i><span data-rr-offline-msg>You are offline. Showing your route as last loaded; actions need a connection.</span><button type="button" class="sl-btn sm" data-rr-retry>Retry</button></div>
<main class="da-body" id="sl-main">
<?php if (!empty($d['rpError'])): ?><div class="sl-banner warn">Today's route could not be loaded. Pull to refresh, or contact dispatch.</div><?php endif; ?>
<?php if (!empty($d['isRider']) && !$j && $tab==='home') include __DIR__ . '/partials/rider_app_hero.php'; ?>
<?php if (!empty($d['isRider']) && !$j && $rv && in_array($tab, ['home', 'route'], true)) { $rrMode = $tab; include __DIR__ . '/partials/rider_route_today.php'; } ?>
<?php if (!empty($d['isRider']) && !$j && $tab !== 'load') include __DIR__ . '/partials/rider_delivery_requests.php'; ?>
<?php if (!$d['isRider']): ?>
<section class="da-sheet"><b class="fw5">This screen is for riders</b><span class="mut sm">Sign in with a rider account to see assigned pickups and deliveries. Staff can assign jobs on the dispatch board.</span><a class="da-pri" href="<?= sl_route('RiderDispatch') ?>">Open dispatch board</a><a class="da-sec" href="<?= sl_route('Main') ?>" style="flex:none">Back to overview</a></section>
<?php elseif (!empty($d['missingJob'])): ?><section class="da-sheet"><h2 class="sl-ct">Job unavailable</h2><p class="mut">This job is no longer in your assigned list.</p><a class="da-pri" href="<?= sl_route('DriverApp') ?>">Back to my jobs</a></section>
<?php elseif ($j): ?>
<section class="da-sheet"><div class="da-row"><span class="sl-av" style="width:52px;height:52px"><?= h(sl_initials($j['cust'])) ?></span><span class="col grow"><b class="fw5" style="font-size:17px"><?= h($j['cust']) ?></b><span class="mut sm"><?= $j['pickup'] ? 'Pickup' : 'Delivery' ?><?= $j['phone'] ? ' · ' . h($j['phone']) : '' ?></span></span><span class="sl-pill <?= h($j['cls']) ?>"><?= h($j['st']) ?></span></div>
<div class="da-row"><i class="ic i-pin" aria-hidden="true"></i><span class="grow"><?= h($j['addr']) ?></span></div>
<div class="da-row" style="gap:8px"><?php if ($j['phone']): ?><a class="da-sec" href="tel:<?= h(preg_replace('/[^0-9+]/', '', $j['phone'])) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Call</a><a class="da-sec" href="https://wa.me/<?= h(preg_replace('/[^0-9]/', '', $j['phone'])) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp</a><?php endif; ?><?php if ($navUrl = sl_riderNavigation($j['addr'])): ?><a class="da-sec" href="<?= h($navUrl) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-route" aria-hidden="true"></i>Navigate</a><?php endif; ?></div>
<div class="da-kv"><div><small>Tracking</small><b class="mono"><?= h($j['trk']) ?></b></div><div><small>Weight</small><b><?= h($j['kg']) ?> kg</b></div><div><small>Value</small><b><?= h($j['value']) ?></b></div><div><small>Delivery fee</small><b><?= h($j['fee']) ?></b></div><div><small>You get (<?= (int) $j['pct'] ?>%)</small><b><?= h($j['rid']) ?></b></div><div><small>Status</small><b><?= h($j['st']) ?></b></div></div></section>
<?php if ($j['state'] === 'open' && !$j['pickup']): ?>
<?php if ($j['st'] !== 'On route'): ?><button class="da-sec" style="flex:none" data-sl-action="ajax/sl/rider_ajax.php" data-p-op="onroute" data-p-id="<?= (int) $j['id'] ?>" data-sl-reload><i class="ic ic-sm i-truck" aria-hidden="true"></i>Start: I have the package</button><?php endif; ?>
<form class="da-sheet" id="da-deliver" enctype="multipart/form-data"><b class="fw5">Hand over</b>
<?php if ($d['codesOn']): ?><label class="sl-field"><span class="sl-lab">Customer's pickup code (if they have one)</span><input class="sl-in mono" name="code" inputmode="numeric" maxlength="6" placeholder="6 digits" autocomplete="off" style="font-size:22px;letter-spacing:.3em;text-align:center"></label><?php endif; ?>
<label class="sl-field"><span class="sl-lab">Who received it</span><input class="sl-in" name="person_receives" required placeholder="Full name"></label>
<label class="da-photo"><span><i class="ic i-image" aria-hidden="true"></i> <span id="da-photo-l">Take a photo of the hand-over</span></span><input type="file" name="miarchivo" accept="image/*" capture="environment" class="da-file-input" aria-label="Add handover photo"></label>
<input type="hidden" name="shipment_id" value="<?= (int) $j['id'] ?>"><input type="hidden" name="driver_id" value="<?= (int) $_SESSION['userid'] ?>"><input type="hidden" name="handover_type" value="delivered"><input type="hidden" name="deliver_date" value="<?= date('Y-m-d') ?>"><input type="hidden" name="country" value=""><input type="hidden" name="address" value="<?= h($j['addr']) ?>"><input type="hidden" name="comments" value="Delivered by rider app">
<div role="alert" tabindex="-1" class="da-bad" id="da-bad" style="display:none"><i class="ic ic-sm i-x" aria-hidden="true"></i><span></span></div>
<button class="da-pri sl-mobile-sticky" type="submit"><i class="ic ic-sm i-check" aria-hidden="true"></i>Confirm delivery</button>
<button class="da-sec" type="button" style="flex:none" data-sl-action="ajax/sl/rider_ajax.php" data-p-op="failed" data-p-id="<?= (int) $j['id'] ?>" data-p-note="Customer not home" data-sl-confirm="Record a failed attempt?">Customer not home · try later</button></form>
<?php elseif ($j['state'] === 'open' && $j['pickup']): ?>
<form class="da-sheet" data-sl-form action="ajax/sl/rider_ajax.php" method="post" enctype="multipart/form-data" data-sl-redirect><b class="fw5">Collect the parcel</b><input type="hidden" name="op" value="collected"><input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
<label class="sl-field"><span class="sl-lab">Who handed it over</span><input class="sl-in" name="person" required placeholder="Full name"></label>
<label class="da-photo"><span><i class="ic i-image" aria-hidden="true"></i> <span>Photo of the parcel</span></span><input type="file" name="photo" accept="image/*" capture="environment" class="da-file-input" aria-label="Add handover photo"></label>
<input class="sl-in" name="note" aria-label="Note (optional)" placeholder="Note (optional)"><button class="da-pri sl-mobile-sticky" type="submit"><i class="ic ic-sm i-check" aria-hidden="true"></i>Mark collected</button>
<button class="da-sec" type="button" style="flex:none" data-sl-action="ajax/sl/rider_ajax.php" data-p-op="failed" data-p-id="<?= (int) $j['id'] ?>" data-p-note="Customer not available" data-sl-confirm="Record a failed attempt?">Customer not there · try later</button></form>
<?php else: ?><div class="da-ok"><i class="ic i-check" aria-hidden="true"></i><span><?= h($j['st']) ?><?= $j['receiver'] ? ' to ' . h($j['receiver']) : '' ?>. <?= $j['state'] === 'done' ? h($j['rid']) . ' added to today’s earnings.' : 'Contact dispatch to arrange another attempt.' ?></span></div><a class="da-pri" href="<?= sl_route('DriverApp') ?>">Next package</a><?php endif; ?>
<?php elseif ($tab === 'load'): ?>
<?php if ($rv): include __DIR__ . '/partials/rider_load_checklist.php'; else: ?><section class="da-sheet"><h2 class="sl-ct">Nothing to load yet</h2><p class="mut">When dispatch confirms today's route, your parcels appear here as a checklist to scan or tick before you leave the office.</p><a class="da-pri" href="<?= h(sl_route('DriverApp')) ?>">Back to today</a></section><?php endif; ?>
<?php elseif ($tab === 'pay'): ?>
<section class="da-sheet"><span class="up">Other assigned jobs · earned today</span><b style="font:600 38px var(--font);letter-spacing:-.02em"><?= h($d['earned']) ?></b><span class="mut sm"><?= (int) $d['done'] ?> jobs · <?= (int) $d['pct'] ?>% of <?= h($d['feesToday']) ?> in fees</span><div class="da-bars"><?php foreach ($d['week'] as $w): ?><i class="<?= h($w['cls']) ?>" style="<?= h($w['h']) ?>" title="<?= h($w['t']) ?>"></i><?php endforeach; ?></div><div class="row between mut" style="font-size:13.5px"><?php foreach ($d['week'] as $w): ?><span><?= h($w['l']) ?></span><?php endforeach; ?></div></section>
<div class="da-k"><div><b><?= h($d['weekEarned']) ?></b><small>Last 7 days</small></div><div><b><?= (int) $d['done'] ?></b><small>Done today</small></div><div><b><?= (int) $d['left'] ?></b><small>Still to do</small></div></div>
<?php elseif ($tab === 'route' && $rv): ?>
<?php if ($d['jobs']): ?><details class="da-sheet rr-other"><summary><b class="fw5">Other jobs</b> <span class="mut sm"><?= (int) $d['left'] ?> to do · not in the planned route</span></summary>
<?php foreach ($d['jobs'] as $i => $p): ?><a class="da-pk" href="<?= h(sl_route('DriverApp', ['job' => $p['id']])) ?>" style="color:inherit"><span class="da-n <?= $p['state'] === 'open' ? '' : 'done' ?>"><?= $i + 1 ?></span><span class="m"><b class="fw5"><?= h($p['cust']) ?></b><small><?= h($p['addr']) ?></small></span><span class="sl-pill <?= h($p['cls']) ?>"><?= h($p['st']) ?></span></a><?php endforeach; ?></details><?php endif; ?>
<?php elseif ($rv && !$d['total']): ?>
<?php else: ?>
<?php if ($rv): ?><div class="da-row rr-other-h"><b class="fw5 grow">Other jobs</b><span class="mut sm">pickups and deliveries outside the planned route</span></div><?php endif; ?>
<div class="da-k da-job-stats"><div><b><?= (int) $d['left'] ?></b><small>To do</small></div><div><b><?= (int) $d['done'] ?></b><small>Done</small></div><div><b><?= h($d['earned']) ?></b><small>Earned today</small></div></div>
<?php if ($d['next'] && $tab === 'home'): $n = $d['next']; ?><section class="da-next" aria-label="Next stop"><div class="in"><div class="da-row"><span class="up grow">Next stop</span><span class="sl-pill <?= h($n['cls']) ?>"><?= h($n['st']) ?></span></div><div class="da-row"><span class="col grow"><b class="fw5" style="font-size:17px"><?= h($n['cust']) ?></b><span class="mut sm"><?= h($n['addr']) ?></span></span><span class="da-fee"><?= h($n['fee']) ?><small>you get <?= h($n['rid']) ?></small></span></div>
<div class="da-row" style="gap:8px"><?php if ($n['phone']): ?><a class="da-sec" href="tel:<?= h(preg_replace('/[^0-9+]/', '', $n['phone'])) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Call</a><?php endif; ?><?php if ($navUrl = sl_riderNavigation($n['addr'])): ?><a class="da-sec" href="<?= h($navUrl) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-route" aria-hidden="true"></i>Navigate</a><?php endif; ?></div>
<a class="da-pri" href="<?= h(sl_url(['job' => $n['id']])) ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i><?= $n['pickup'] ? 'Collect' : 'Deliver' ?></a></div></section><?php endif; ?>
<?php if (!$rv) include __DIR__ . '/partials/rider_map.php'; ?>
<div class="da-row"><b class="fw5 grow"><?= $rv ? 'Other packages' : 'My packages' ?></b><span class="mut sm"><?= (int) $d['total'] ?> today</span></div>
<div class="da-seg"><?php foreach ($d['segs'] as $g): ?><a class="<?= $g['k'] === $d['seg'] ? 'on' : '' ?>" href="<?= h(sl_url(['seg' => $g['k']])) ?>"><?= h($g['label']) ?></a><?php endforeach; ?></div>
<?php foreach ($d['jobs'] as $i => $p): ?><a class="da-pk" href="<?= h(sl_url(['job' => $p['id']])) ?>" style="color:inherit"><span class="da-n <?= $p['state'] === 'open' ? '' : 'done' ?>"><?= $i + 1 ?></span><span class="m"><b class="fw5"><?= h($p['cust']) ?></b><small><?= h($p['addr']) ?></small><small class="mono"><?= h($p['trk']) ?> · <?= $p['pickup'] ? 'pickup' : 'delivery' ?></small></span><span class="col" style="align-items:flex-end;gap:6px"><span class="sl-pill <?= h($p['cls']) ?>"><?= h($p['st']) ?></span><span class="da-fee"><?= h($p['fee']) ?></span></span></a><?php endforeach; ?>
<?php if (!$d['jobs']): ?><div class="da-sheet mut sm">Nothing here. New jobs appear when the office assigns them.</div><?php endif; ?>
<?php endif; ?>
</main>
<?php if ($d['isRider']): ?>
<div class="rr-layout" role="group" aria-label="Screen layout"><span class="mut sm">Layout</span><?php foreach (['auto' => 'Auto', 'phone' => 'Phone view', 'web' => 'Web view'] as $k => $lbl): ?><button type="button" class="<?= $layout === $k ? 'on' : '' ?>" aria-pressed="<?= $layout === $k ? 'true' : 'false' ?>" data-rider-layout-set="<?= h($k) ?>"><?= h($lbl) ?></button><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($rv && !$j): ?>
<dialog class="rr-dialog" data-rr-fail-dialog aria-labelledby="rr-fail-h"><form method="dialog" class="col g12" data-rr-fail-form novalidate>
<h2 class="sl-ct" id="rr-fail-h">Delivery failed: <span data-rr-fail-name></span></h2>
<fieldset class="rr-reasons"><legend class="sl-lab">What happened?</legend><?php foreach ($rv['fail_reasons'] as $i => $reason): ?><label class="rr-radio"><input type="radio" name="reason" value="<?= h($reason) ?>"><span><?= h($reason) ?></span></label><?php endforeach; ?><label class="rr-radio"><input type="radio" name="reason" value="Other"><span>Other</span></label></fieldset>
<label class="sl-field"><span class="sl-lab">Note for dispatch (optional)</span><textarea class="sl-in" name="note" maxlength="400" rows="2" placeholder="E.g. called twice, gate locked"></textarea></label>
<div class="rr-dialog-actions"><p class="da-bad" data-rr-fail-err role="alert" hidden></p>
<div class="row g8"><button type="button" class="rr-btn" value="cancel" data-rr-fail-cancel>Back</button><button type="submit" class="rr-btn bad" value="ok">Report failed</button></div></div>
</form></dialog>
<script type="application/json" id="rr-data"><?= json_encode($rv['json'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
<?php if ($d['isRider']): ?><nav class="da-tab da-tab-bottom" aria-label="Rider app"><?php foreach ($d['tabs'] ?? [] as $t): $on = $tab === $t['k'] && !$j; ?><a class="<?= $on ? 'on' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?> href="<?= h($t['k'] === 'home' ? sl_route('DriverApp') : sl_route('DriverApp', ['tab' => $t['k']])) ?>"><i class="ic i-<?= h($t['icon']) ?>" aria-hidden="true"></i><?= h($t['label']) ?><?php if ($t['k'] === 'load' && $rv && $rv['inventory']['pending_load'] && in_array($rv['status'], ['planned', 'loading'], true)): ?><b class="rr-tab-n"><?= (int) $rv['inventory']['pending_load'] ?></b><?php endif; ?></a><?php endforeach; ?></nav><?php endif; ?>
</div>
<script>
(function () {
  document.querySelectorAll('.da-photo input[type=file]').forEach(function (inp) { inp.addEventListener('change', function () { var l = inp.closest('.da-photo').querySelector('span span, span'); if (inp.files[0]) { inp.closest('.da-photo').classList.add('on'); l.lastChild.textContent = ' Photo added: ' + inp.files[0].name; } }); });
  var f = document.getElementById('da-deliver'); if (!f) return;
  var bad = document.getElementById('da-bad');
  function fail(m) { bad.style.display = ''; bad.querySelector('span').textContent = m; bad.focus(); }
  f.addEventListener('submit', function (e) {
    e.preventDefault(); if (f.getAttribute('aria-busy') === 'true') return; f.setAttribute('aria-busy','true'); bad.style.display = 'none';
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
    }).catch(function (err) { fail(err.message); }).finally(function () { btn.disabled = false; f.removeAttribute('aria-busy'); });
  });
})();
</script>
<style>/* links in place of the design's buttons (server-side navigation) */
.da-seg a{flex:none;height:38px;padding:0 14px;border-radius:999px;border:1px solid var(--line-2);background:var(--card);font:500 15.5px var(--font);color:var(--ink);display:inline-flex;align-items:center}
.da-seg a.on{background:var(--ink);color:var(--inverse);border-color:var(--ink)}
.da-tab-bottom a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;height:54px;font:500 13.5px var(--font);color:var(--muted);border-radius:14px}
.da-tab a.on{color:var(--ink)}.da-tab a.on .ic{color:#E2632A}
.da-photo{cursor:pointer}.da-body{padding-bottom:96px}</style>
