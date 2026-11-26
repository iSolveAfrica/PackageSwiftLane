<?php
/**
 * Pre-alert details pop-up (#pa-edit), opened by clicking any row of the Pre-Alerts list ([data-pa-open]).
 * Shows the full text the list shortens to 20 characters, lets the owner / staff with prealert_edit edit it,
 * and lets staff convert it: link it to a registered package with the same tracking number (or a package
 * number typed in), create a sea package or air shipment from it, close it or reopen it.
 * Saves through ajax/sl/prealert_ajax.php (op update | link | close | reopen).
 * Expects $d from sl_preAlertsData().
 */
$paMap = [];
foreach ($d['rows'] as $r) $paMap[$r['id']] = $r['edit'];
?>
<div class="sl-dialog" id="pa-edit" hidden>
  <div class="sl-modal pa-ed" role="dialog" aria-modal="true" aria-labelledby="pa-ed-title">
    <div class="row between g8"><div class="col" style="min-width:0"><span class="up mut">Pre-alert</span><h2 class="sl-ct mono" id="pa-ed-title">—</h2></div><span class="row g8"><span class="sl-pill" data-pe="pill"></span><button type="button" class="sl-ib sm" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></span></div>
    <div class="pa-ed-meta mut sm" data-pe="meta"></div>
    <div class="sl-banner ok" data-pe="linked" hidden><i class="ic i-box" aria-hidden="true"></i><span>Converted to registered package <a class="fw5 mono" data-pe="ref" href="#"></a>.</span></div>
    <div class="sl-banner warn" data-pe="closed" hidden><i class="ic i-alert" aria-hidden="true"></i><span data-pe="closedText"></span></div>

    <form class="col g12" id="pa-ed-form" data-sl-form action="ajax/sl/prealert_ajax.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="op" value="update"><input type="hidden" name="id" value="">
      <?php if ($d['canStaffEdit']): ?><label class="sl-field"><span class="sl-lab">Customer</span><span class="sl-sel native"><select name="customer_id"><?php foreach ($d['customers'] as $c): ?><option value="<?= (int) $c->id ?>"><?= h(trim($c->fname . ' ' . $c->lname)) ?><?= $c->locker ? ' · ' . h($c->locker) : '' ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label><?php endif; ?>
      <div class="pa-two">
        <label class="sl-field"><span class="sl-lab">Carrier tracking number</span><input name="tracking" class="sl-in mono" required></label>
        <label class="sl-field"><span class="sl-lab">Carrier</span><span class="sl-sel native"><select name="courier" required><?php foreach ($d['couriers'] as $c): ?><option value="<?= (int) $c->id ?>"><?= h($c->name_com) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
      </div>
      <label class="sl-field"><span class="sl-lab">Shop</span><input name="provider" class="sl-in" placeholder="Amazon, Shein, eBay…"></label>
      <label class="sl-field"><span class="sl-lab">What is in it</span><textarea name="description" class="sl-in" rows="3" required></textarea></label>
      <div class="pa-two"><label class="sl-field"><span class="sl-lab">Purchase price</span><input name="price" type="number" step="0.01" min="0" class="sl-in mono"></label><label class="sl-field"><span class="sl-lab">Expected at the warehouse</span><input name="date" type="date" class="sl-in" required></label></div>
      <div class="row g8 wrap"><label class="sl-btn ghost sm" style="cursor:pointer"><i class="ic ic-sm i-upload" aria-hidden="true"></i><span data-pe="fileNote">Replace invoice</span><input type="file" name="file_invoice" accept=".pdf,image/*" hidden></label><a class="sl-btn ghost sm" data-pe="invoice" target="_blank" hidden><i class="ic ic-sm i-file" aria-hidden="true"></i>Open invoice</a></div>
      <div class="row g8" data-pe="saveRow"><button type="submit" class="sl-btn"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save changes</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div>
    </form>

    <?php if ($d['canStaffEdit']): ?>
    <section class="pa-conv col g10" data-pe="convert" hidden>
      <h3 class="sl-ct" style="font-size:15px">Convert to a registered package</h3>
      <div class="col g6" data-pe="matches"></div>
      <label class="row g8 sm"><input type="checkbox" data-pe="fill" checked> Update this pre-alert with the package's customer, shop and value</label>
      <div class="row g8 wrap"><input class="sl-in mono grow" data-pe="refIn" placeholder="Or type the package number, e.g. PK-1043" style="min-width:200px"><button type="button" class="sl-btn ghost" data-pe-link-ref>Link</button></div>
      <div class="row g8 wrap">
        <?php if ($d['canPackage']): ?><a class="sl-btn ghost sm" data-pe="newPkg" href="#"><i class="ic ic-sm i-box" aria-hidden="true"></i>Register as sea package</a><?php endif; ?>
        <?php if ($d['canShipment']): ?><a class="sl-btn ghost sm" data-pe="newShip" href="#"><i class="ic ic-sm i-plane" aria-hidden="true"></i>Register as air shipment</a><?php endif; ?>
      </div>
    </section>
    <section class="pa-conv col g8" data-pe="closeBox" hidden>
      <div class="row between wrap g8"><span class="sm mut">Not coming? Close it. Open pre-alerts are closed automatically <?= (int) $d['closeDays'] ?> days after the expected date.</span><button type="button" class="sl-btn ghost sm" data-pe-close-toggle style="color:var(--red-ink)"><i class="ic ic-sm i-x" aria-hidden="true"></i>Close pre-alert</button></div>
      <div class="row g8 wrap" data-pe="closeRow" hidden><input class="sl-in grow" data-pe="closeWhy" placeholder="Reason (e.g. the shop cancelled the order)" maxlength="200"><button type="button" class="sl-btn" data-pe-close>Close it</button></div>
    </section>
    <div class="row" data-pe="reopenRow" hidden><button type="button" class="sl-btn ghost sm" data-pe-reopen><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Reopen pre-alert</button></div>
    <?php endif; ?>
  </div>
</div>
<script>
(function () {
  var M = <?= json_encode($paMap, JSON_HEX_TAG | JSON_HEX_AMP) ?>, dlg = document.getElementById('pa-edit'), f = document.getElementById('pa-ed-form');
  if (!dlg) return;
  var cur = null, $ = function (k) { return dlg.querySelector('[data-pe="' + k + '"]'); };
  function esc(s) { var x = document.createElement('div'); x.textContent = s == null ? '' : String(s); return x.innerHTML; }
  function show(el, on) { if (el) el.hidden = !on; }
  function open(id) {
    var p = M[id]; if (!p) return; cur = p;
    document.getElementById('pa-ed-title').textContent = p.tracking;
    var pill = $('pill'); pill.className = 'sl-pill ' + p.cls; pill.textContent = p.st;
    $('meta').textContent = 'For ' + p.customer + ' · sent ' + p.sent + (p.lateBy ? ' · ' + p.lateBy + ' d late' : '') + (p.updated ? ' · edited ' + p.updated : '');
    show($('linked'), !!p.ref); if (p.ref) { $('ref').textContent = p.ref; $('ref').href = p.refHref || '#'; }
    show($('closed'), p.state === 2); $('closedText').textContent = 'Closed ' + (p.closedAt || '') + (p.closedWhy ? ': ' + p.closedWhy : '') + '. The customer was told it is not coming.';
    f.elements.id.value = p.id; f.elements.tracking.value = p.tracking; f.elements.provider.value = p.provider; f.elements.description.value = p.description;
    f.elements.price.value = p.price ? p.price.toFixed(2) : ''; f.elements.date.value = p.date; f.elements.courier.value = p.courier;
    if (f.elements.customer_id) f.elements.customer_id.value = p.customer_id;
    Array.prototype.forEach.call(f.elements, function (el) { if (el.name && el.type !== 'hidden') el.disabled = !p.canEdit; });
    show($('saveRow'), p.canEdit); f.querySelector('label.sl-btn').hidden = !p.canEdit; $('fileNote').textContent = 'Replace invoice';
    var inv = $('invoice'); show(inv, !!p.invoice); if (p.invoice) inv.href = p.invoice;
    var conv = $('convert');
    if (conv) {
      show(conv, p.state === 0);
      $('matches').innerHTML = p.matches.length ? p.matches.map(function (m) { return '<div class="pa-match"><i class="ic ic-sm ' + (m.module === 'air' ? 'i-plane' : 'i-box') + '" aria-hidden="true"></i><span class="grow"><b class="mono">' + esc(m.ref) + '</b> <span class="mut sm">is already in the system with this tracking number</span></span><a class="sl-btn ghost sm" href="' + esc(m.href) + '" target="_blank">View</a><button type="button" class="sl-btn sm" data-pe-link="' + esc(m.module) + ':' + m.id + '">Convert to ' + esc(m.ref) + '</button></div>'; }).join('')
        : '<span class="mut sm">No registered package has this tracking number yet. When the parcel is registered with it, it is linked automatically.</span>';
      if ($('newPkg')) $('newPkg').href = 'customer_packages_add.php?prealert_id=' + p.id;
      if ($('newShip')) $('newShip').href = 'courier_add.php?prealert_id=' + p.id;
      show($('closeBox'), p.state === 0); show($('closeRow'), false); show($('reopenRow'), p.state !== 0);
    }
    dlg.hidden = false;
  }
  window.slPreAlertOpen = open;
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-pa-open]');
    if (o && !e.target.closest('summary, a[href], .pop a')) { e.preventDefault(); var dm = o.closest('details'); if (dm) dm.open = false; open(+o.getAttribute('data-pa-open')); return; }
    if (!cur || !dlg.contains(e.target)) return;
    var b;
    function act(data, btn) {
      if (btn) btn.disabled = true;
      data.id = cur.id;
      SL.post('ajax/sl/prealert_ajax.php', data).then(function (j) {
        SL.toast(j.message || '', !j.success);
        if (j.success) setTimeout(function () { location.href = j.href && data.op === 'link' ? j.href : location.href; }, 600);
      }).finally(function () { if (btn) btn.disabled = false; });
    }
    if ((b = e.target.closest('[data-pe-link]'))) { var v = b.getAttribute('data-pe-link').split(':'); act({ op: 'link', module: v[0], order_id: v[1], fill: $('fill').checked ? 1 : '' }, b); }
    else if ((b = e.target.closest('[data-pe-link-ref]'))) { var r = $('refIn').value.trim(); if (!r) { $('refIn').focus(); return; } act({ op: 'link', ref: r, fill: $('fill').checked ? 1 : '' }, b); }
    else if ((b = e.target.closest('[data-pe-close-toggle]'))) { show($('closeRow'), $('closeRow').hidden); $('closeWhy').focus(); }
    else if ((b = e.target.closest('[data-pe-close]'))) act({ op: 'close', reason: $('closeWhy').value.trim() }, b);
    else if ((b = e.target.closest('[data-pe-reopen]'))) act({ op: 'reopen' }, b);
  });
  document.addEventListener('keydown', function (e) { var r = e.target.closest && e.target.closest('tr[data-pa-open]'); if (r && e.key === 'Enter') open(+r.getAttribute('data-pa-open')); });
  f.addEventListener('change', function (e) { if (e.target.name === 'file_invoice') $('fileNote').textContent = e.target.files.length ? e.target.files[0].name : 'Replace invoice'; });
  f.addEventListener('sl:done', function (e) { if (e.detail && e.detail.success) setTimeout(function () { location.reload(); }, 500); });
  var want = <?= (int) $d['openId'] ?>; if (want && M[want]) open(want);
})();
</script>
