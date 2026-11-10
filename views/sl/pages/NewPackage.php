<?php
/**
 * NewPackage - from docs/handover/artifact-source/NewPackage.dc.html.
 * Data: views/sl/data/packages.php (sl_newPackageData). Posts to ajax/sl/package_ajax.php (op=create).
 */
if (!isset($d)) { $d = []; }
$def = $d['def']; $pa = $d['prealert'];
$opt = function (array $rows, $selected, string $labelKey = 'label') { $o = ''; foreach ($rows as $r) { $o .= '<option value="' . (int) $r->id . '"' . ((int) $r->id === (int) $selected ? ' selected' : '') . '>' . h($r->$labelKey) . '</option>'; } return $o; };
?>
<form data-sl-form action="ajax/sl/package_ajax.php" method="post" id="np-form" class="col g16">
  <input type="hidden" name="op" value="create"><input type="hidden" name="prealert_id" value="<?= (int) ($pa['id'] ?? 0) ?>"><input type="hidden" name="packages" id="np-boxes-json" value="[]">
        <div class="sl-head">
          <div><a href="<?= sl_route('SeaPackages') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>Sea Packages</a><h1 class="sl-title" style="margin-top: 4px">New Package</h1></div>
          <div class="row g8"><a class="sl-btn ghost" href="<?= sl_route('SeaPackages') ?>">Cancel</a><button type="submit" class="sl-btn">Create package</button></div>
        </div>
        <div class="split">
          <div class="a col g16">
            <section class="sl-card col g16">
              <div class="row between wrap g12"><h2 class="sl-ct">Customer</h2><button type="button" class="sl-btn ghost sm" data-sl-open="np-prealert"><i class="ic ic-sm i-inbox" aria-hidden="true"></i>Import from pre-alert</button></div>
              <div class="grid cols2">
                <label class="sl-field"><span class="sl-lab">Customer</span><span class="sl-sel native"><select name="sender_id" id="np-customer" required><option value="">Select customer</option><?php foreach ($d['customers'] as $c): ?><option value="<?= (int) $c->id ?>" <?= (int) $c->id === (int) ($pa['customer'] ?? 0) ? 'selected' : '' ?>><?= h(trim($c->fname . ' ' . $c->lname)) ?><?= $c->locker ? ' · ' . h($c->locker) : '' ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
                <label class="sl-field"><span class="sl-lab">Delivery address</span><span class="sl-sel native"><select name="sender_address_id" id="np-address" required><option value="">Pick a customer first</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
              </div>
              <?php if ($pa): ?><div class="sl-banner ok"><i class="ic i-check" aria-hidden="true"></i><span>Pre-alert matched: <strong><?= h($pa['what'] ?: 'parcel') ?> from <?= h($pa['shop']) ?></strong> expected <?= h($pa['date']) ?>. The pre-alert is closed when this package is created.</span></div>
              <?php else: ?><div class="sl-banner info" id="np-pa-hint"><i class="ic i-info" aria-hidden="true"></i><span>Type the supplier tracking number: an open pre-alert with the same number is linked automatically.</span></div><?php endif; ?>
            </section>
            <section class="sl-card col g16">
              <h2 class="sl-ct">Package details</h2>
              <div class="grid cols3">
                <label class="sl-field"><span class="sl-lab">Container</span><span class="sl-sel native"><select name="consolidate_id"><option value="0">Not in a container yet</option><?php foreach ($d['containers'] as $c): ?><option value="<?= (int) $c->consolidate_id ?>"><?= h($c->c_prefix . $c->c_no) ?> · <?= h(sl_date($c->c_date)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
                <label class="sl-field"><span class="sl-lab">Package type</span><span class="sl-sel native"><select name="order_item_category"><?= $opt($d['categories'], $def['category']) ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
                <label class="sl-field"><span class="sl-lab">Warehouse</span><span class="sl-sel native"><select name="origin_off" required><?= $opt($d['offices'], $def['office']) ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
                <label class="sl-field"><span class="sl-lab">Supplier tracking</span><input name="tracking_purchase" id="np-postal" class="sl-in mono" value="<?= h($pa['tracking'] ?? '') ?>" placeholder="1Z999AA1…" required></label>
                <label class="sl-field"><span class="sl-lab">Packaging</span><span class="sl-sel native"><select name="order_package" required><?= $opt($d['packaging'], $def['packaging']) ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
                <label class="sl-field"><span class="sl-lab">Carrier</span><span class="sl-sel native"><select name="order_courier" required><?= $opt($d['couriers'], $pa['courier'] ?? $def['courier']) ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
                <label class="sl-field"><span class="sl-lab">Supplier</span><input name="provider_purchase" class="sl-in" value="<?= h($pa['shop'] ?? '') ?>" placeholder="Amazon" required></label>
                <label class="sl-field"><span class="sl-lab">Purchase price</span><input name="price_purchase" class="sl-in mono" type="number" step="0.01" min="0" value="<?= h($pa ? number_format($pa['price'], 2, '.', '') : '') ?>" required></label>
                <label class="sl-field"><span class="sl-lab">Agency</span><span class="sl-sel native"><select name="agency" required><?= $opt($d['agencies'], $def['agency']) ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
                <label class="sl-field"><span class="sl-lab">Delivery time</span><span class="sl-sel native"><select name="order_deli_time" required><?= $opt($d['delitimes'], $def['delitime']) ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
                <label class="sl-field"><span class="sl-lab">Received on</span><input name="order_date" class="sl-in" type="date" value="<?= h($d['today']) ?>" required></label>
                <label class="sl-field"><span class="sl-lab">Estimated arrival</span><input name="estimated_eta" class="sl-in" type="date" value=""></label>
                <label class="sl-field"><span class="sl-lab">Tracking number</span><span class="row g6"><input name="code_prefix" class="sl-in mono" style="width:90px" value="<?= h($d['prefix']) ?>" aria-label="Prefix"><input name="order_no" class="sl-in mono" value="<?= h($d['nextNo']) ?>" placeholder="Next free number" aria-label="Number"></span><span class="sl-hint mut sm">Leave the number as is to use the next free one.</span></label>
                <label class="sl-field"><span class="sl-lab">Driver</span><span class="sl-sel native"><select name="driver_id"><option value="0">No driver</option><?php foreach ($d['drivers'] as $dr): ?><option value="<?= (int) $dr->id ?>"><?= h($dr->fname . ' ' . $dr->lname) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
                <label class="sl-field"><span class="sl-lab">Notes</span><input name="notes" class="sl-in" placeholder="Fragile, keep upright…"></label>
              </div>
            </section>
            <section class="sl-card flush">
              <div class="row between" style="padding: 16px 20px"><h2 class="sl-ct">Boxes</h2><button type="button" class="sl-btn ghost sm" id="np-add"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add box</button></div>
              <div class="sl-tw"><table class="sl-table" id="np-boxes"><thead><tr><th>#</th><th>Contents</th><th class="num">Qty</th><th class="num">L × W × H (<?= h($d['dim']) ?>)</th><th class="num">Weight (<?= h($d['unit']) ?>)</th><th class="num">Declared value</th><th class="num">Volume</th><th></th></tr></thead><tbody></tbody></table></div>
              <template id="np-row"><tr><td class="n">1</td><td><input class="sl-in sm" data-k="description" placeholder="Kitchen appliances" required></td><td><input class="sl-in sm mono num" data-k="qty" type="number" min="1" step="1" value="1" style="width:64px"></td><td><span class="row g4"><input class="sl-in sm mono" data-k="length" type="number" min="0" step="0.1" value="0" style="width:62px" aria-label="Length">×<input class="sl-in sm mono" data-k="width" type="number" min="0" step="0.1" value="0" style="width:62px" aria-label="Width">×<input class="sl-in sm mono" data-k="height" type="number" min="0" step="0.1" value="0" style="width:62px" aria-label="Height"></span></td><td><input class="sl-in sm mono" data-k="weight" type="number" min="0" step="0.1" value="0" style="width:80px" aria-label="Weight"></td><td><input class="sl-in sm mono" data-k="declared_value" type="number" min="0" step="0.01" value="0" style="width:90px" aria-label="Declared value"></td><td class="num mono vol">0.0</td><td><button type="button" class="sl-ib sm np-del" aria-label="Remove box"><i class="ic i-trash" aria-hidden="true"></i></button></td></tr></template>
            </section>
            <section class="sl-card"><div class="sl-ch"><h2 class="sl-ct">Photos</h2></div><label class="sl-drop" style="cursor:pointer"><i class="ic ic-lg i-upload" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Drop photos or browse</span><span class="sm" id="np-files-note">Add a photo of each box before sealing</span><input type="file" name="files[]" multiple accept="image/*,.pdf" hidden id="np-files"></label></section>
          </div>
          <aside class="b col g16">
            <section class="sl-card col g12"><h2 class="sl-ct">Price</h2>
              <div class="sl-kv"><div><span>Weight</span><span id="np-w">0 <?= h($d['unit']) ?></span></div><div><span>Volumetric weight</span><span id="np-vw">0 <?= h($d['unit']) ?></span></div><div><span>Rate</span><span><?= h(sl_money($d['rate'])) ?> / <?= h($d['unit']) ?></span></div><div><span>Freight</span><span id="np-freight"><?= h(sl_money(0)) ?></span></div><div><span>Insurance (<?= h($d['insurance']) ?>%)</span><span id="np-ins"><?= h(sl_money(0)) ?></span></div><div><span>Tax (<?= h($d['tax']) ?>%)</span><span id="np-tax"><?= h(sl_money(0)) ?></span></div></div>
              <label class="sl-field"><span class="sl-lab">Discount %</span><input name="discount_value" id="np-disc" class="sl-in mono" type="number" min="0" max="100" step="0.5" value="0"></label>
              <div class="row between"><span class="fw5">Total due</span><span class="big" id="np-total"><?= h(sl_money(0)) ?></span></div>
              <button type="submit" class="sl-btn block">Create package</button>
            </section>
            <section class="sl-card col g10"><div class="row between"><span class="sl-ct">Customer is told</span></div><div class="row g10"><span class="sl-toggle on" role="switch" aria-checked="true" aria-label="WhatsApp"><input type="hidden" name="notify_whatsapp" value="1"></span><span>WhatsApp with the tracking number</span></div><div class="row g10"><span class="sl-toggle on" role="switch" aria-checked="true" aria-label="E-mail"><input type="hidden" name="notify_email" value="1"></span><span>E-mail with the package details</span></div><p class="mut sm">SMS follows the "package registered" automation in Settings.</p></section>
          </aside>
        </div>
</form>
<div class="sl-dialog" id="np-prealert" hidden><div class="sl-modal" role="dialog" aria-label="Import from pre-alert">
  <b class="fw5">Open pre-alerts</b>
  <div class="col g6" style="max-height:60vh;overflow:auto"><?php foreach ($d['prealerts'] as $p): ?><a class="ops-row" href="<?= sl_route('NewPackage', ['prealert_id' => (int) $p->pre_alert_id]) ?>"><span class="col grow"><b class="fw5 sm"><?= h(trim($p->fname . ' ' . $p->lname)) ?></b><span class="mut" style="font-size:12px"><?= h($p->provider_shop) ?></span></span><span class="mono sm"><?= h($p->tracking) ?></span></a><?php endforeach; ?><?php if (!$d['prealerts']): ?><span class="mut sm">No open pre-alerts.</span><?php endif; ?></div>
  <button type="button" class="sl-btn ghost" data-sl-close>Close</button>
</div></div>
<script>
(function () {
  var CFG = { rate: <?= json_encode($d['rate']) ?>, tax: <?= json_encode($d['tax']) ?>, ins: <?= json_encode($d['insurance']) ?>, meter: <?= json_encode($d['meter']) ?>, minTax: <?= json_encode($d['minTax']) ?>, sym: <?= json_encode($core->for_symbol ?? ($core->currency ?? '$')) ?>, unit: <?= json_encode($d['unit']) ?> };
  var tb = document.querySelector('#np-boxes tbody'), tpl = document.getElementById('np-row');
  function money(n) { return CFG.sym + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
  function rows() { return Array.prototype.slice.call(tb.querySelectorAll('tr')); }
  function add() { tb.appendChild(tpl.content.cloneNode(true)); renumber(); calc(); }
  function renumber() { var single = rows().length === 1; rows().forEach(function (r, i) { r.querySelector('.n').textContent = i + 1; var remove = r.querySelector('.np-del'); remove.disabled = single; remove.title = single ? 'Keep at least one box' : 'Remove box'; }); }
  function val(r, k) { var i = r.querySelector('[data-k="' + k + '"]'); return i ? i.value : ''; }
  function calc() {
    var w = 0, vw = 0, declared = 0, list = [];
    rows().forEach(function (r) {
      var l = +val(r, 'length') || 0, wd = +val(r, 'width') || 0, h = +val(r, 'height') || 0, wt = +val(r, 'weight') || 0, q = +val(r, 'qty') || 1;
      var v = l * wd * h / (CFG.meter || 500);
      r.querySelector('.vol').textContent = v.toFixed(1);
      w += wt; vw += v; declared += (+val(r, 'declared_value') || 0);
      list.push({ qty: q, description: val(r, 'description'), length: l, width: wd, height: h, weight: wt, declared_value: +val(r, 'declared_value') || 0, fixed_value: 0 });
    });
    var bill = Math.max(w, vw), freight = bill * CFG.rate, disc = freight * ((+document.getElementById('np-disc').value || 0) / 100);
    var tax = freight > CFG.minTax ? (freight - disc) * CFG.tax / 100 : 0, ins = declared * CFG.ins / 100;
    document.getElementById('np-w').textContent = w.toFixed(1) + ' ' + CFG.unit;
    document.getElementById('np-vw').textContent = vw.toFixed(1) + ' ' + CFG.unit;
    document.getElementById('np-freight').textContent = money(freight);
    document.getElementById('np-ins').textContent = money(ins);
    document.getElementById('np-tax').textContent = money(tax);
    document.getElementById('np-total').textContent = money(freight - disc + tax + ins);
    document.getElementById('np-boxes-json').value = JSON.stringify(list);
  }
  document.getElementById('np-add').addEventListener('click', add);
  tb.addEventListener('click', function (e) { if (e.target.closest('.np-del') && rows().length > 1) { e.target.closest('tr').remove(); renumber(); calc(); } });
  document.getElementById('np-form').addEventListener('input', calc);
  add();
  var cust = document.getElementById('np-customer'), addr = document.getElementById('np-address');
  function loadAddr() {
    addr.innerHTML = '<option value="">Loading…</option>';
    if (!cust.value) { addr.innerHTML = '<option value="">Pick a customer first</option>'; return; }
    SL.get('ajax/sl/package_ajax.php', { op: 'addresses', id: cust.value }).then(function (j) {
      addr.innerHTML = '';
      (j.addresses || []).forEach(function (a) { var o = document.createElement('option'); o.value = a.id; o.textContent = a.label; addr.appendChild(o); });
      if (!addr.options.length) addr.innerHTML = '<option value="">This customer has no address on file</option>';
    });
  }
  cust.addEventListener('change', loadAddr); if (cust.value) loadAddr();
  document.getElementById('np-files').addEventListener('change', function () { document.getElementById('np-files-note').textContent = this.files.length ? this.files.length + ' file(s) attached' : 'Add a photo of each box before sealing'; });
  var postal = document.getElementById('np-postal'), hint = document.getElementById('np-pa-hint');
  if (postal && hint) postal.addEventListener('change', function () {
    SL.get('ajax/sl/package_ajax.php', { op: 'prealert', tracking: postal.value }).then(function (j) {
      if (j.prealert) { hint.className = 'sl-banner ok'; hint.querySelector('span').textContent = 'Pre-alert matched: ' + (j.prealert.what || 'parcel') + ' from ' + j.prealert.shop + ' for ' + j.prealert.customer + '. It is linked when the package is created.'; document.querySelector('[name=prealert_id]').value = j.prealert.id; if (j.prealert.customer_id) { cust.value = j.prealert.customer_id; loadAddr(); } }
    });
  });
})();
</script>
