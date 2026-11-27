<?php
/**
 * M-CPickup: a customer requests a pickup (pickup_add.php, sl_schedulePickupData()). Same fields and endpoint as the
 * web page (views/sl/pages/SchedulePickup.php): ajax/sl/pickup_ajax.php op=create through data-sl-form, which follows
 * the server's redirect to the pickups list.
 */
$slPhone['nav'] = 'none';
$wins = (array) ($d['windows'] ?? []);
$addr = (array) ($d['addresses'] ?? []);
$me = $d['me'] ?? null;
$tomorrow = (string) ($d['today'] ?? date('Y-m-d', strtotime('+1 day')));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-cust.css')) ?>">
<?= sl_phoneTop('Request a pickup', '', ['back' => sl_route('Pickups')]) ?>
<form data-sl-form action="ajax/sl/pickup_ajax.php" method="post" id="sp-form">
<input type="hidden" name="op" value="create"><input type="hidden" name="window" id="sp-window" value="<?= h($wins[1] ?? ($wins[0] ?? '')) ?>"><input type="hidden" name="kind" id="sp-kind" value="collect"><input type="hidden" name="packages" id="sp-pk" value="[]"><input type="hidden" name="repeat" id="sp-repeat" value=""><input type="hidden" name="notify_whatsapp" value="1">
<main class="mx-body">
<div class="mx-f"><small id="sp-kind-l">Type</small><div class="mx-fseg" id="sp-kinds" role="radiogroup" aria-labelledby="sp-kind-l"><button type="button" class="on" role="radio" aria-checked="true" data-k="collect">Collect from me</button><button type="button" role="radio" aria-checked="false" data-k="deliver">Deliver to me</button></div></div>
<div class="mx-sec"><h2>Where and when</h2></div>
<section class="mx-card mx-form">
<label class="mx-f"><small>Address</small><select class="mx-in mx-inp" name="sender_address_id" required><?php foreach ($addr as $a): ?><option value="<?= (int) $a['id'] ?>"><?= h($a['label']) ?></option><?php endforeach; ?><?php if (!$addr): ?><option value="">Add an address to your account first</option><?php endif; ?></select><small class="mx-fh">Add an address to your account first if it is not listed · <a class="mx-lnk" style="min-height:0;display:inline" href="<?= h(sl_route('UserHome')) ?>">My addresses</a></small></label>
<div class="mx-two"><label class="mx-f"><small>Contact phone</small><input class="mx-in mx-inp mono" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="<?= h($me->phone ?? '') ?>" placeholder="+233 …"></label><label class="mx-f"><small>Date</small><input class="mx-in mx-inp" type="date" name="order_date" id="sp-date" value="<?= h($tomorrow) ?>" min="<?= h(date('Y-m-d')) ?>" required></label></div>
</section>
<div class="mx-f"><small id="sp-rep-l">Repeat</small><div class="mx-fseg" id="sp-reps" role="radiogroup" aria-labelledby="sp-rep-l"><button type="button" class="on" role="radio" aria-checked="true" data-r="">Does not repeat</button><button type="button" role="radio" aria-checked="false" data-r="weekly">Every week ×4</button></div></div>
<div class="mx-sec"><h2>Time window</h2></div>
<div class="b2-chs" role="radiogroup" aria-label="Time window" id="sp-wins"><?php foreach ($wins as $i => $w): ?><button type="button" role="radio" aria-checked="<?= $i === 1 ? 'true' : 'false' ?>" class="<?= $i === 1 ? 'on' : '' ?>" data-w="<?= h($w) ?>"><?= h($w) ?></button><?php endforeach; ?></div>
<div class="mx-sec"><h2>Parcels</h2></div>
<section class="mx-card mx-form"><div id="sp-parcels" class="pc-list"></div><button type="button" class="b2-add" id="sp-add"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add parcel</button></section>
<template id="sp-parcel"><div class="b2-pl"><label class="mx-f"><small>What is it?</small><input class="mx-in mx-inp" data-k="description" placeholder="e.g. Suitcase" autocomplete="off"></label><label class="mx-f"><small>Qty</small><input class="mx-in mx-inp mono" data-k="qty" type="number" min="1" value="1" inputmode="numeric"></label><label class="mx-f"><small>Weight</small><input class="mx-in mx-inp mono" data-k="weight" type="number" min="0" step="0.1" value="1" inputmode="decimal"></label><button type="button" class="b2-x sp-del" aria-label="Remove parcel"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></div></template>
<section class="mx-card mx-form"><label class="mx-f"><small>Notes for rider</small><textarea class="mx-in mx-inp pc-ta" name="notes" rows="3" placeholder="Blue gate, call on arrival"></textarea></label></section>
<div class="mx-ban ok"><i class="ic ic-sm i-message" aria-hidden="true"></i><span>You get a WhatsApp with the time window; the pickup code comes with the ready message.</span></div>
<section class="mx-card mx-list"><a class="mx-row" href="<?= h(sl_route('Pickups')) ?>"><span class="mx-li"><i class="ic i-truck" aria-hidden="true"></i></span><span class="mx-rm"><b>My pickups</b><small>Upcoming and past pickups</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a></section>
</main>
<div class="mx-foot"><div class="b2-ft"><span><small id="sp-sum"></small><b id="sp-count">1 parcel</b></span><button type="submit" class="b2-cta"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Request pickup</button></div></div>
</form>
<script>
(function () {
  var f = document.getElementById('sp-form'), box = document.getElementById('sp-parcels'), tpl = document.getElementById('sp-parcel');
  var date = document.getElementById('sp-date'), win = document.getElementById('sp-window');
  function radio(group, b, input, attr) { group.querySelectorAll('button').forEach(function (x) { var on = x === b; x.classList.toggle('on', on); x.setAttribute('aria-checked', on ? 'true' : 'false'); }); document.getElementById(input).value = b.getAttribute(attr); sync(); }
  function sync() {
    box.querySelectorAll('.sp-del').forEach(function (b) { b.disabled = box.children.length === 1; });
    var l = [], n = 0;
    box.querySelectorAll('.b2-pl').forEach(function (r) { var g = function (k) { return r.querySelector('[data-k=' + k + ']').value; }; if (g('description').trim()) { l.push({ description: g('description'), qty: +g('qty') || 1, weight: +g('weight') || 0, length: 0, width: 0, height: 0, declared_value: 0, fixed_value: 0 }); n += +g('qty') || 1; } });
    document.getElementById('sp-pk').value = JSON.stringify(l);
    document.getElementById('sp-count').textContent = n + ' parcel' + (n === 1 ? '' : 's');
    var d = date.value ? new Date(date.value + 'T12:00:00') : null;
    document.getElementById('sp-sum').textContent = (d && !isNaN(d) ? d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' }) + ' · ' : '') + win.value;
  }
  function add() { box.appendChild(tpl.content.cloneNode(true)); sync(); }
  document.getElementById('sp-add').addEventListener('click', function () { add(); var i = box.lastElementChild.querySelector('[data-k=description]'); if (i) i.focus(); });
  add();
  box.addEventListener('click', function (e) { if (e.target.closest('.sp-del') && box.children.length > 1) { e.target.closest('.b2-pl').remove(); sync(); } });
  f.addEventListener('input', sync); f.addEventListener('change', sync);
  document.getElementById('sp-wins').addEventListener('click', function (e) { var b = e.target.closest('[data-w]'); if (b) radio(this, b, 'sp-window', 'data-w'); });
  document.getElementById('sp-kinds').addEventListener('click', function (e) { var b = e.target.closest('[data-k]'); if (b) radio(this, b, 'sp-kind', 'data-k'); });
  document.getElementById('sp-reps').addEventListener('click', function (e) { var b = e.target.closest('[data-r]'); if (b) radio(this, b, 'sp-repeat', 'data-r'); });
  f.addEventListener('submit', sync, true);
})();
</script>
