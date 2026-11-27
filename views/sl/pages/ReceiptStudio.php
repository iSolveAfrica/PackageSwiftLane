<?php
/**
 * ReceiptStudio: choose what the package receipt shows. Drag sections into order, switch them
 * on or off, fine-tune fields, preview live. Data: views/sl/data/labels.php (sl_receiptStudioData).
 * Saved by ajax/sl/receipt_options_ajax.php into cdb_sl_settings receipt_options; printed by
 * print_invoice_package.php through partials/receipt_body.php.
 */
if (!isset($d)) { $d = []; }
$o = $d['o'];
$cat = $d['cat'];
$l = $d['l'];
$canEdit = $d['canEdit'];
$dis = $canEdit ? '' : ' disabled';
$byBlock = [];
foreach ($cat['fields'] as $k => [$label, $blk]) $byBlock[$blk][$k] = $label;
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Receipt Studio</h1><p class="sl-lede">Choose what the package receipt shows. Drag sections into order, switch off what you don't need, preview live.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('LabelStudio') ?>"><i class="ic ic-sm i-tag" aria-hidden="true"></i>Label Studio</a><?php if ($l): ?><a class="sl-btn" href="<?= h('print_invoice_package.php?id=' . (int) $l['id'] . '&type=air') ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-print" aria-hidden="true"></i>Open receipt</a><?php endif; ?></div></div>
<form method="get" class="row g8 wrap sm"><span class="mut">Preview with shipment:</span><span class="sl-search" style="max-width:260px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" placeholder="Tracking number" aria-label="Shipment for the preview"></span><input type="hidden" name="as" value="<?= h($d['as']) ?>"><button class="sl-btn sm ghost" type="submit">Load</button><?php foreach ($d['recent'] as $r): ?><a class="sl-btn sm <?= $l && $r['id'] === $l['id'] ? '' : 'ghost' ?>" href="<?= h(sl_url(['id' => $r['id'], 'q' => null])) ?>"><?= h($r['track']) ?></a><?php endforeach; ?></form>
<?php if (!$canEdit): ?><div class="sl-banner warn">You can preview receipts. Changing the receipt layout needs the Settings (view_tools) permission.</div><?php endif; ?>
<?php if ($d['q'] !== '' && !$l): ?><div class="sl-banner warn">No shipment matches “<?= h($d['q']) ?>”. Showing a sample receipt.</div><?php endif; ?>
<div class="rsx">
<section class="rs-opt" aria-label="Receipt layout">
  <div class="rs-oh"><b>Receipt sections</b><span class="sm" style="opacity:.85"><span data-rs-count><?= count($o['order']) - count($o['hide']) ?></span> of <?= count($o['order']) ?> showing</span></div>
  <div class="rs-ob">
    <p class="mut sm" style="margin:0">Drag <b>⠿</b> to reorder (or use the arrows). The switch shows or hides a section. Changes save automatically.</p>
    <ol class="lo-list" data-lo-list<?= $canEdit ? '' : ' data-lo-ro' ?> id="rs-list">
    <?php foreach ($o['order'] as $k): [$t, $sub] = $cat['blocks'][$k]; $on = empty($o['hide'][$k]); ?>
      <li class="lo-row<?= $on ? '' : ' off' ?>" data-k="<?= h($k) ?>">
        <button type="button" class="lo-grip" data-lo-grip aria-label="Drag <?= h($t) ?>"<?= $dis ?>>⠿</button>
        <span class="lo-t"><b><?= h($t) ?></b><small><?= h($sub) ?></small><?php if (!empty($byBlock[$k])): ?><span class="rs-fl"><?php foreach ($byBlock[$k] as $fk => $fl): ?><label><input type="checkbox" data-rs-field="<?= h($fk) ?>"<?= !empty($o['fields'][$fk]) ? ' checked' : '' ?><?= $dis ?>> <?= h(preg_replace('/^.*? · /', '', $fl)) ?></label><?php endforeach; ?></span><?php endif; ?></span>
        <span class="lo-mv"><button type="button" data-lo-up aria-label="Move <?= h($t) ?> up">▲</button><button type="button" data-lo-down aria-label="Move <?= h($t) ?> down">▼</button></span>
        <button type="button" class="lo-sw" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>" aria-label="Show <?= h($t) ?>" data-rs-show<?= $dis ?>></button>
      </li>
    <?php endforeach; ?>
    </ol>
    <div class="hr"></div>
    <div class="ls-sec"><span>Logo colour</span><div class="rs-pills"><?php foreach (['color' => 'Full colour', 'black' => 'Black (thermal printers)'] as $v => $t): ?><button type="button" data-rs-logo="<?= $v ?>" class="<?= $o['logo'] === $v ? 'on' : '' ?>"<?= $dis ?>><?= h($t) ?></button><?php endforeach; ?></div></div>
    <label class="sl-field"><span class="sl-lab">Receipt title</span><input class="sl-in" id="rs-title" value="<?= h($o['title']) ?>" maxlength="40" placeholder="PACKAGE RECEIPT"<?= $dis ?>></label>
    <label class="sl-field"><span class="sl-lab">Closing note (footer)</span><textarea class="sl-in" id="rs-note" rows="2" maxlength="300" placeholder="Keep this receipt as proof of payment."<?= $dis ?>><?= h($o['note']) ?></textarea></label>
    <?php if ($canEdit): ?><div class="row between wrap g8"><button type="button" class="sl-btn sm ghost" id="rs-reset">Reset to default</button><span class="mut sm" id="rs-status" role="status"></span></div><?php endif; ?>
  </div>
</section>
<div class="rs-stage">
  <div class="row between wrap g8"><div class="rs-pills" role="group" aria-label="Preview state"><?php foreach (['paid' => 'Paid receipt', 'due' => 'Not paid receipt'] as $v => $t): ?><a class="<?= $d['as'] === $v ? 'on' : '' ?>" href="<?= h(sl_url(['as' => $v])) ?>"><?= h($t) ?></a><?php endforeach; ?></div><span class="mut sm"><?= $l ? 'Previewing ' . h($l['track']) : 'Sample receipt' ?></span></div>
  <div class="rs-canvas"><iframe id="rs-frame" class="rs-frame" src="<?= h($d['src']) ?>" title="Receipt preview"></iframe></div>
</div>
</div>
<?php include __DIR__ . '/partials/layout_sort.php'; ?>
<?php if ($canEdit): ?>
<script>
(function () {
  var list = document.getElementById('rs-list'), frame = document.getElementById('rs-frame'), status = document.getElementById('rs-status');
  var logo = <?= json_encode($o['logo']) ?>, timer = null;
  function collect() {
    var hide = {}, fields = {};
    list.querySelectorAll('[data-k]').forEach(function (li) { if (li.querySelector('[data-rs-show]').getAttribute('aria-checked') !== 'true') hide[li.getAttribute('data-k')] = 1; });
    document.querySelectorAll('[data-rs-field]').forEach(function (c) { fields[c.getAttribute('data-rs-field')] = c.checked ? 1 : 0; });
    return { order: SLLayout.order(list), hide: hide, fields: fields, logo: logo, title: document.getElementById('rs-title').value, note: document.getElementById('rs-note').value };
  }
  function save(reset) {
    clearTimeout(timer);
    status.textContent = 'Saving…';
    SL.post('ajax/sl/receipt_options_ajax.php', reset ? { reset: 1 } : { options: JSON.stringify(collect()) }).then(function (j) {
      if (!j.success) { status.textContent = ''; SL.toast(j.message || 'Not saved', true); return; }
      if (reset) { location.reload(); return; }
      status.textContent = 'Saved · preview updated';
      document.querySelector('[data-rs-count]').textContent = list.querySelectorAll('[data-rs-show][aria-checked="true"]').length;
      frame.src = frame.src.replace(/&_=\d+/, '') + '&_=' + Date.now();
    }).catch(function (e) { status.textContent = ''; SL.toast(e.message || 'Network error', true); });
  }
  function later() { clearTimeout(timer); timer = setTimeout(save, 500); }
  list.addEventListener('lo:change', function () { save(); });
  list.addEventListener('click', function (e) {
    var sw = e.target.closest('[data-rs-show]'); if (!sw) return;
    var on = sw.getAttribute('aria-checked') !== 'true';
    sw.setAttribute('aria-checked', on ? 'true' : 'false');
    sw.closest('.lo-row').classList.toggle('off', !on);
    save();
  });
  document.addEventListener('change', function (e) { if (e.target.matches('[data-rs-field]')) save(); });
  document.querySelectorAll('[data-rs-logo]').forEach(function (b) {
    b.addEventListener('click', function () {
      logo = b.getAttribute('data-rs-logo');
      document.querySelectorAll('[data-rs-logo]').forEach(function (x) { x.classList.toggle('on', x === b); });
      save();
    });
  });
  ['rs-title', 'rs-note'].forEach(function (id) { document.getElementById(id).addEventListener('input', later); });
  document.getElementById('rs-reset').addEventListener('click', function () { if (confirm('Put every receipt section back to the default order and show them all?')) save(true); });
})();
</script>
<?php endif; ?>
