<?php
/**
 * ImportData - from docs/handover/artifact-source/ImportData.dc.html.
 * Uses the existing importer ajax/courier/import_excel_add_courier_ajax.php
 * (action=preview parses and prices each row, action=create saves them with the
 * same logic as a single shipment). The column template is fixed by that importer.
 */
if (!isset($d)) { $d = []; }
$ep = 'ajax/courier/import_excel_add_courier_ajax.php';
?>
<div class="row g8 mut sm"><a href="<?= sl_route('AirShipments') ?>">Air Shipments</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Import</span></div>
<div class="sl-head"><div><h1 class="sl-title">Import from Excel</h1><p class="sl-lede">Add many shipments, packages or customers at once</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h($ep) ?>?action=reference"><i class="ic ic-sm i-file" aria-hidden="true"></i>ID reference</a><a class="sl-btn ghost" href="<?= h($ep) ?>?action=template"><i class="ic ic-sm i-download" aria-hidden="true"></i>Download template</a></div></div>
<div class="split"><div class="a col g16"><section class="sl-card col g16" id="im"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Import</h2></div>
<div class="row g8 wrap" id="im-steps"><?php foreach (['Upload', 'Match columns', 'Check rows', 'Import'] as $i => $s): ?><span class="row g6 sm <?= $i === 0 ? 'fw5' : 'mut' ?>" data-im-step="<?= $i + 1 ?>"><span class="sl-tile" style="width:26px;height:26px;<?= $i === 0 ? 'background:var(--ink);color:var(--inverse)' : '' ?>"><?= $i + 1 ?></span><?= h($s) ?></span><?php if ($i < 3): ?><i class="ic ic-sm i-right mut" aria-hidden="true"></i><?php endif; ?><?php endforeach; ?></div>
<div class="sl-field"><span class="sl-lab">Import as</span><div class="sl-seg boxed"><a class="on" href="<?= sl_route('ImportData') ?>">Shipments</a><?php if ($user->cdp_hasPermission('add_multiple_packages')): ?><a href="customer_packages_multiple.php">Packages</a><?php endif; ?><?php if ($user->cdp_hasPermission('add_client')): ?><a href="<?= sl_route('AddCustomer') ?>">Customers</a><?php endif; ?><?php if ($user->cdp_hasPermission('view_recipients')): ?><a href="<?= sl_route('Recipients') ?>">Recipients</a><?php endif; ?></div></div>
<label class="np-drop" style="cursor:pointer"><span class="sl-tile"><i class="ic i-upload" aria-hidden="true"></i></span><b class="fw5" id="im-name">Choose an Excel or CSV file</b><span class="mut sm" id="im-sub">.xlsx, .xls or .csv · up to 5 MB · one shipment per row</span><input type="file" id="im-file" accept=".xlsx,.xls,.csv" hidden></label>
<div class="sl-tw" id="im-map"><table class="sl-table"><thead><tr><th>Column in your file</th><th>Example</th><th>Swift Lane field</th></tr></thead><tbody>
<?php foreach ([['sender_id', '12', 'Customer (sender) ID'], ['sender_address_id', '1', 'Sender address ID'], ['recipient_id', '15', 'Recipient ID'], ['recipient_address_id', '3', 'Recipient address ID'], ['order_service_options', '26', 'Category (Air Freight)'], ['quantity', '1', 'Quantity'], ['weight', '5.6', 'Weight'], ['length · width · height', '10 · 8 · 4', 'Dimensions'], ['description', 'iPad Air', 'Item description'], ['declared_value', '599', 'Declared value'], ['manual_tariff · price_lb', '1 · 6.00', 'Manual price per weight unit (else tariff table)']] as $c): ?><tr><td class="fw5 mono sm"><?= h($c[0]) ?></td><td class="mono sm mut"><?= h($c[1]) ?></td><td><span class="sl-sel" style="max-width:280px"><span><?= h($c[2]) ?></span></span></td></tr><?php endforeach; ?>
</tbody></table></div>
<div class="sl-tw" id="im-rows" hidden><table class="sl-table"><thead><tr><th>#</th><th>Sender</th><th>Recipient</th><th>Item</th><th class="num">Weight</th><th class="num">Price</th><th>Check</th></tr></thead><tbody></tbody></table></div>
</section></div>
<aside class="b col g16"><section class="sl-card col g16"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Check</h2></div><div class="sl-kv"><div><span>Rows found</span><span data-im="n">—</span></div><div><span>Ready to import</span><span data-im="ok">—</span></div><div><span>With problems</span><span data-im="bad">—</span></div></div><button type="button" class="sl-btn" id="im-go" disabled><i class="ic ic-sm i-check" aria-hidden="true"></i><span>Import rows</span></button><span class="mut sm" id="im-note">Rows with problems are skipped. Fix them in the file and import again; the template and ID reference above list the accepted values.</span></section></aside></div>
<script>
(function () {
  var EP = <?= json_encode($ep) ?>, rows = [], file = document.getElementById('im-file');
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
  function step(n) { document.querySelectorAll('[data-im-step]').forEach(function (s) { var i = +s.getAttribute('data-im-step'), on = i <= n; s.classList.toggle('fw5', on); s.classList.toggle('mut', !on); s.querySelector('.sl-tile').style.cssText = 'width:26px;height:26px;' + (on ? 'background:var(--ink);color:var(--inverse)' : ''); }); }
  var bad = function (r) { return !!(r.row_error || (!(+r.manual_tariff) && r.tariff_error)); };
  file.addEventListener('change', function () {
    if (!file.files.length) return;
    var f = file.files[0]; document.getElementById('im-name').textContent = f.name; document.getElementById('im-sub').textContent = 'Reading…'; step(2);
    var fd = new FormData(); fd.append('action', 'preview'); fd.append('file', f);
    SL.post(EP, fd).then(function (j) {
      if (!j.success) { SL.toast(j.error || j.message || 'The file could not be read', true); document.getElementById('im-sub').textContent = j.error || 'Could not read the file'; return; }
      rows = j.rows || []; step(3);
      var ok = rows.filter(function (r) { return !bad(r); }).length;
      document.getElementById('im-sub').textContent = rows.length + ' rows · uploaded just now';
      document.querySelector('[data-im="n"]').textContent = rows.length; document.querySelector('[data-im="ok"]').textContent = ok; document.querySelector('[data-im="bad"]').textContent = rows.length - ok;
      var tb = document.querySelector('#im-rows tbody'); tb.innerHTML = '';
      rows.forEach(function (r, i) { var e = [r.row_error, r.tariff_error].filter(Boolean).join(' '); tb.insertAdjacentHTML('beforeend', '<tr><td class="mono sm">' + (i + 1) + '</td><td>' + esc(r.sender_name || r.sender_id) + '</td><td>' + esc(r.recipient_name || r.recipient_id) + '</td><td>' + esc(r.description) + ' ×' + esc(r.quantity) + '</td><td class="num">' + esc(r.weight) + '</td><td class="num mono">' + esc(r.total_tarifa != null ? r.total_tarifa : (r.price_lb || '—')) + '</td><td>' + (bad(r) ? '<span class="sl-pill overdue">' + esc(e) + '</span>' : '<span class="sl-pill delivered">Ready</span>') + '</td></tr>'); });
      document.getElementById('im-rows').hidden = false; document.getElementById('im-map').hidden = true;
      var go = document.getElementById('im-go'); go.disabled = ok === 0; go.querySelector('span').textContent = 'Import ' + ok + ' row' + (ok === 1 ? '' : 's');
    }).catch(function () { SL.toast('Upload failed', true); });
  });
  document.getElementById('im-go').addEventListener('click', function () {
    var good = rows.filter(function (r) { return !bad(r); }); if (!good.length) return;
    var b = this; b.disabled = true; b.classList.add('loading'); step(4);
    var fd = new FormData(); fd.append('action', 'create'); fd.append('rows', JSON.stringify(good));
    SL.post(EP, fd).then(function (j) {
      if (j.success) { SL.toast(j.created + ' shipment(s) created' + (j.failed ? ', ' + j.failed + ' failed' : '')); document.getElementById('im-note').textContent = (j.errors && j.errors.length ? 'Not imported: ' + j.errors.map(function (e) { return typeof e === 'string' ? e : JSON.stringify(e); }).join('; ') : 'Done.'); setTimeout(function () { location.href = <?= json_encode(sl_route('AirShipments')) ?>; }, 1500); }
      else SL.toast(j.error || 'Import failed', true);
    }).finally(function () { b.classList.remove('loading'); });
  });
})();
</script>
