<?php
/**
 * M-NewConsol: a new air or sea consolidation. Same $d as the web page (views/sl/data/consolidations.php
 * sl_newConsolidationData); posts to the same endpoint with the same fields (ajax/sl/consolidation_ajax.php, op=create):
 * type, ids, dg, order_pay_mode (air), country_code (sea), origin_off, status_courier, order_courier, awb_no, seals,
 * order_deli_time (sea), order_date, estimated_eta.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'none';
$L = $d['L']; $def = $d['def']; $sea = $d['sea'];
$opt = function (array $rows, $sel) { $o = ''; foreach ($rows as $r) { $o .= '<option value="' . (int) $r->id . '"' . ((int) $r->id === (int) $sel ? ' selected' : '') . '>' . h($r->label) . '</option>'; } return $o; };
$approved = array_values(array_filter($d['avail'], fn($a) => $a['approved']));
$apW = array_sum(array_map(fn($a) => $a['wn'], $approved));
$others = count($d['avail']) - count($approved);
$code = $d['prefix'] . ($d['num'] !== '' ? $d['num'] : '••••••');
$sel = function (string $name, string $options, bool $req = false) { return '<span class="wh-selw"><select class="mx-in" name="' . h($name) . '"' . ($req ? ' required' : '') . '>' . $options . '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span>'; };
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-wh.css')) ?>">
<?= sl_phoneTop('New consolidation', $code, ['back' => sl_route('Consolidations')]) ?>
<form data-sl-form action="ajax/sl/consolidation_ajax.php" method="post" enctype="multipart/form-data" id="nc-form" style="display:contents">
<main class="mx-body">
<input type="hidden" name="op" value="create"><input type="hidden" name="type" value="<?= h($d['type']) ?>"><input type="hidden" name="ids" id="nc-ids" value=""><input type="hidden" name="dg" value="<?= (int) $d['dg'] ?>"><?php if (!$sea): ?><input type="hidden" name="order_pay_mode" value="1"><?php endif; ?>
<?php if (count($d['modes']) > 1): ?>
<div class="mx-f"><small id="nc-tl">Type</small><nav class="mx-fseg" aria-labelledby="nc-tl"><?php foreach ($d['modes'] as $m): ?><a class="<?= h($m['cls']) ?>" href="<?= h($m['href']) ?>"<?= $m['cls'] === 'on' ? ' aria-current="page"' : '' ?>><?= h($m['label']) ?></a><?php endforeach; ?></nav></div>
<?php endif; ?>
<section class="mx-card mx-form">
<?php if ($sea): ?><label class="mx-f"><small>Origin country</small><?= $sel('country_code', implode('', array_map(fn($cc, $cn) => '<option value="' . h($cc) . '"' . ($cc === $d['country'] ? ' selected' : '') . '>' . h($cn) . '</option>', array_keys($d['countries']), $d['countries']))) ?></label><?php endif; ?>
<div class="mx-two">
<label class="mx-f"><small>From</small><?= $sel('origin_off', $opt($L['offices'], $def['office']), true) ?></label>
<div class="mx-f"><small>To</small><div class="mx-in"><span><?= h($d['dest']) ?></span></div></div>
</div>
<label class="mx-f"><small><?= $sea ? 'Shipping line' : 'Airline / carrier' ?></small><?= $sel('order_courier', $opt($L['couriers'], $def['courier'])) ?></label>
<label class="mx-f"><small><?= $sea ? 'Bill of lading' : 'AWB no.' ?></small><input class="mx-in mono" name="awb_no" maxlength="40" autocomplete="off" autocapitalize="characters"<?= $d['awbReady'] ? '' : ' disabled' ?>></label>
<?php if ($sea): ?>
<label class="mx-f"><small>Seals</small><input class="mx-in mono" name="seals" maxlength="300" autocomplete="off"></label>
<label class="mx-f"><small>Delivery time</small><?= $sel('order_deli_time', $opt($L['delitimes'], $def['delitime'])) ?></label>
<?php endif; ?>
<label class="mx-f"><small><?= $sea ? 'Sailing date and time' : 'Flight date and time' ?></small><input class="mx-in" type="datetime-local" name="order_date" value="<?= h($d['today']) ?>" required></label>
<label class="mx-f"><small>Estimated arrival</small><input class="mx-in" type="date" name="estimated_eta" value="<?= h($d['eta']) ?>"></label>
<label class="mx-f"><small>Status</small><?= $sel('status_courier', implode('', array_map(fn($st) => cdp_isHandedOver($st->id) || (int) $st->id === 21 ? '' : '<option value="' . (int) $st->id . '"' . ((int) $st->id === $def['status'] ? ' selected' : '') . '>' . h(str_replace('_', ' ', $st->label)) . '</option>', $L['statuses']))) ?></label>
</section>
<?= sl_mxSec('Parcels to include', !$sea ? ($d['dg'] ? 'Normal goods' : 'Dangerous goods') : '', !$sea ? sl_url(['dg' => $d['dg'] ? 0 : 1]) : '') ?>
<?php if ($d['avail']): ?>
<section class="mx-card mx-list">
<?php if ($approved): ?><button type="button" class="mx-row" id="nc-all" style="width:100%;border:0;background:none;text-align:left;font:inherit;cursor:pointer"><span class="mx-li"><i class="ic i-box" aria-hidden="true"></i></span><span class="mx-rm"><b><?= count($approved) ?> parcel<?= count($approved) === 1 ? '' : 's' ?> ready</b><small><?= h(number_format($apW, 1) . ' ' . $d['unit']) ?> · approved</small></span><span class="mx-rr"><b id="nc-allL">Add all</b></span></button><?php endif; ?>
<?php if ($others): ?><div class="mx-row"><span class="mx-li"><i class="ic i-receipt" aria-hidden="true"></i></span><span class="mx-rm"><b><?= $others ?> not approved yet</b><small>Waiting at the warehouse</small></span><span class="mx-rr"><span class="sl-pill pending">Hold</span></span></div><?php endif; ?>
</section>
<section class="mx-card mx-list wh-items" aria-label="Parcels">
<?php foreach ($d['avail'] as $a): ?>
<label class="mx-row"><input type="checkbox" value="<?= (int) $a['oid'] ?>" data-w="<?= h($a['wn']) ?>" data-ok="<?= $a['approved'] ? 1 : 0 ?>" aria-label="Include <?= h($a['id']) ?>"><span class="mx-rm"><b><?= h($a['name']) ?></b><small><?= h($a['id']) ?> · <?= h($a['what']) ?><?= $a['dg'] ? ' · DG' : '' ?></small></span><span class="mx-rr"><b><?= h($a['w']) ?></b><span class="sl-pill <?= h($a['stC']) ?>"><?= h($a['st']) ?></span></span></label>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('box', 'No parcels waiting', 'No packages are waiting for a consolidation' . (!$sea && $d['dg'] ? ' among dangerous goods' : '') . '.') ?>
<?php endif; ?>
<p class="mx-note" id="nc-sum" aria-live="polite">No parcels picked yet.</p>
<div class="mx-btns"><button type="submit" class="mx-btn" id="nc-go"<?= $d['avail'] ? '' : ' disabled' ?>>Create consolidation</button></div>
</main>
</form>
<script>
(function () {
  var f = document.getElementById('nc-form'); if (!f) return;
  var unit = <?= json_encode($d['unit']) ?>, boxes = Array.prototype.slice.call(f.querySelectorAll('.wh-items input[type=checkbox]'));
  function calc() {
    var on = boxes.filter(function (b) { return b.checked; }), w = 0;
    on.forEach(function (b) { w += +b.getAttribute('data-w'); });
    document.getElementById('nc-ids').value = on.map(function (b) { return b.value; }).join(',');
    document.getElementById('nc-sum').textContent = on.length ? on.length + ' parcel' + (on.length === 1 ? '' : 's') + ' · ' + w.toFixed(1) + ' ' + unit : 'No parcels picked yet.';
    document.getElementById('nc-go').textContent = on.length ? 'Create with ' + on.length + ' parcel' + (on.length === 1 ? '' : 's') : 'Create consolidation';
    var ap = boxes.filter(function (b) { return b.getAttribute('data-ok') === '1'; }), l = document.getElementById('nc-allL');
    if (l) l.textContent = ap.length && ap.every(function (b) { return b.checked; }) ? 'Clear' : 'Add all';
  }
  f.addEventListener('change', calc);
  var all = document.getElementById('nc-all');
  if (all) all.addEventListener('click', function () { var ap = boxes.filter(function (b) { return b.getAttribute('data-ok') === '1'; }), on = !ap.every(function (b) { return b.checked; }); ap.forEach(function (b) { b.checked = on; }); calc(); });
  f.addEventListener('submit', function (e) { if (!document.getElementById('nc-ids').value) { e.preventDefault(); e.stopImmediatePropagation(); if (window.SL && SL.toast) SL.toast('Pick at least one package', true); } }, true);
  calc();
})();
</script>
