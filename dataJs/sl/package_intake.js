/* Package Intake (views/sl/pages/PackageIntake.php).
 * Lookup: ajax/sl/package_intake_ajax.php. Save: ajax/sl/package_ajax.php op=create. */
(function () {
  var f = document.getElementById('ix-form');
  if (!f) return;
  var R = JSON.parse(f.getAttribute('data-rates') || '{}');
  var $ = function (s) { return f.querySelector(s); };
  var num = function (v) { v = parseFloat(String(v || '').replace(/[^0-9.\-]/g, '')); return isFinite(v) ? v : 0; };
  var money = function (v) { return (R.currency === 'USD' ? '$' : R.currency + ' ') + v.toFixed(2); };
  var get = function (u) { return fetch(u, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); }); };
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
  var custs = {};

  // carriers
  f.querySelector('.ix-carriers').addEventListener('click', function (e) {
    var b = e.target.closest('[data-ix-carrier]'); if (!b) return; pickCarrier(b);
  });
  function pickCarrier(b) { f.querySelectorAll('[data-ix-carrier]').forEach(function (x) { var on = x === b; x.classList.toggle('on', on); x.setAttribute('aria-checked', on); }); $('[name="order_courier"]').value = b.getAttribute('data-ix-carrier'); }
  function carrierByName(n) { if (!n) return; n = n.toLowerCase(); f.querySelectorAll('[data-ix-carrier]').forEach(function (x) { if (x.getAttribute('data-label').toLowerCase().indexOf(n) >= 0) pickCarrier(x); }); }

  // items
  var box = $('[data-ix-items]');
  function addItem(it) {
    it = it || { name: '', qty: 1, value: '' };
    box.insertAdjacentHTML('beforeend', '<div class="ix-it"><input class="sl-in" aria-label="Item name" data-k="name" value="' + esc(it.name) + '"><input class="sl-in mono" aria-label="Quantity" data-k="qty" value="' + esc(it.qty || 1) + '"><input class="sl-in mono" aria-label="Value" data-k="value" value="' + esc(it.value || '') + '"><button type="button" class="sl-ib" aria-label="Remove item" data-ix-rm><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>');
  }
  $('[data-ix-add]').addEventListener('click', function () { addItem(); calc(); });
  box.addEventListener('click', function (e) { var b = e.target.closest('[data-ix-rm]'); if (b) { b.closest('.ix-it').remove(); calc(); } });

  // customers
  var cSel = $('[data-ix-cust]'), aSel = $('[data-ix-addr]');
  function setCustomers(list, pick) {
    cSel.innerHTML = list.length ? '' : '<option value="">No customer found</option>';
    list.forEach(function (c) { custs[c.id] = c; cSel.insertAdjacentHTML('beforeend', '<option value="' + c.id + '">' + esc((c.locker ? c.locker + ' · ' : '') + c.name) + '</option>'); });
    if (pick) cSel.value = pick;
    fillAddr();
  }
  function fillAddr() {
    var c = custs[cSel.value]; aSel.innerHTML = '';
    if (!c || !c.addresses.length) { aSel.innerHTML = '<option value="">' + (c ? 'This customer has no address on file' : 'Pick the customer first') + '</option>'; return; }
    c.addresses.forEach(function (a) { aSel.insertAdjacentHTML('beforeend', '<option value="' + a.id + '">' + esc(a.label) + '</option>'); });
  }
  cSel.addEventListener('change', fillAddr);
  function findCustomer() {
    var q = $('[data-ix-cq]').value.trim(); if (q.length < 2) return;
    get('ajax/sl/package_intake_ajax.php?op=customer&q=' + encodeURIComponent(q)).then(function (j) { setCustomers(j.customers || []); if (!(j.customers || []).length) SL.toast('No customer matches "' + q + '"', true); });
  }
  $('[data-ix-cfind]').addEventListener('click', findCustomer);
  $('[data-ix-cq]').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); findCustomer(); } });

  // lookup
  var msg = $('[data-ix-msg]');
  function say(t, bad) { msg.hidden = !t; msg.innerHTML = t; msg.style.color = bad ? 'var(--red-ink)' : ''; }
  function lookup() {
    var t = $('[data-ix-track]').value.trim(); if (!t) { $('[data-ix-track]').focus(); return; }
    $('[data-ix-loading]').hidden = false; say('');
    get('ajax/sl/package_intake_ajax.php?op=lookup&tracking=' + encodeURIComponent(t)).then(function (j) {
      $('[data-ix-loading]').hidden = true;
      if (!j.success) { say(esc(j.message || 'Lookup failed'), true); return; }
      $('[data-ix-track]').value = j.tracking;
      $('[data-ix-details]').hidden = false;
      box.innerHTML = '';
      if (j.duplicate) say('Already registered as <a href="customer_packages_view.php?id=' + j.duplicate.id + '">' + esc(j.duplicate.track) + '</a>. Check before adding it again.', true);
      if (j.prealert) {
        var p = j.prealert;
        $('[name="prealert_id"]').value = p.used ? '' : p.id;
        $('[data-ix-shop]').value = p.shop || ''; $('[data-ix-price]').value = p.price ? p.price.toFixed(2) : '';
        if (p.eta && p.eta !== '0000-00-00') $('[data-ix-eta]').value = p.eta;
        if (p.courier_id && f.querySelector('[data-ix-carrier="' + p.courier_id + '"]')) pickCarrier(f.querySelector('[data-ix-carrier="' + p.courier_id + '"]'));
        if (j.customer) { setCustomers([j.customer], j.customer.id); $('[data-ix-cq]').value = j.customer.locker || j.customer.name; }
        $('[data-ix-conf]').textContent = j.conf + ' · check and edit';
        if (!j.duplicate) say('Matched pre-alert from <b>' + esc(p.shop) + '</b>' + (j.customer ? ' for <b>' + esc(j.customer.name) + '</b>' : '') + (p.used ? ' (already converted once)' : '') + '.' + (j.note ? ' ' + esc(j.note) : ''));
      } else {
        $('[name="prealert_id"]').value = '';
        carrierByName(j.carrierGuess);
        $('[data-ix-conf]').textContent = 'No pre-alert · fill in by hand';
        if (!j.duplicate) say('No pre-alert for this number' + (j.carrierGuess ? ' (looks like ' + esc(j.carrierGuess) + ')' : '') + '. Find the customer by locker below.');
        setCustomers([]); cSel.innerHTML = '<option value="">Find the customer above</option>';
        $('[data-ix-cq]').focus();
      }
      (j.items && j.items.length ? j.items : [null]).forEach(addItem);
      calc();
    }).catch(function () { $('[data-ix-loading]').hidden = true; say('Lookup failed. Check the connection and try again.', true); });
  }
  f.querySelector('[data-ix-lookup]').addEventListener('click', lookup);
  $('[data-ix-track]').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); lookup(); } });
  document.querySelectorAll('[data-ix-focus]').forEach(function (b) { b.addEventListener('click', function () { var i = $('[data-ix-track]'); i.focus(); i.select(); SL.toast('Scan the barcode with the scanner: it types into the tracking box.'); }); });

  // price (mirrors ajax/sl/package_ajax.php op=create)
  function dims() { var p = String($('[data-ix-dims]').value || '').split(/[x×*,\s]+/).map(num).filter(function (v) { return v > 0; }); return p.length === 3 ? p : [0, 0, 0]; }
  function boxes() {
    var items = Array.prototype.map.call(box.querySelectorAll('.ix-it'), function (r) { return { name: r.querySelector('[data-k=name]').value.trim(), qty: Math.max(1, num(r.querySelector('[data-k=qty]').value) || 1), value: num(r.querySelector('[data-k=value]').value) }; }).filter(function (i) { return i.name; });
    var dm = dims(), w = num($('[data-ix-w]').value);
    // the first line carries the weight and size of the box; the others only their contents
    return items.map(function (i, k) { return { qty: i.qty, description: i.name, weight: k === 0 ? w : 0, length: k === 0 ? dm[0] : 0, width: k === 0 ? dm[1] : 0, height: k === 0 ? dm[2] : 0, declared_value: i.value * i.qty, fixed_value: 0 }; });
  }
  function calc() {
    var b = boxes(), w = num($('[data-ix-w]').value), dm = dims(), vol = dm[0] * dm[1] * dm[2] / (R.meter || 500), ch = Math.max(w, vol);
    var decl = b.reduce(function (s, x) { return s + x.declared_value; }, 0);
    var fr = ch * R.price, ins = decl * R.ins / 100, tot = fr + ins;
    var set = function (k, v) { var el = f.querySelector('[data-p="' + k + '"]'); if (el) el.textContent = v; };
    set('total', money(tot)); set('ghs', '₵' + (tot * (R.rate || 0)).toFixed(2)); set('actual', w.toFixed(2) + ' ' + R.weight); set('vol', vol.toFixed(2) + ' ' + R.weight); set('charge', ch.toFixed(2) + ' ' + R.weight); set('freight', money(fr)); set('ins', money(ins));
    var bar = f.querySelector('[data-p="bar"]'); if (bar) bar.style.width = Math.min(100, ch * 5) + '%';
    $('[data-ix-declared]').textContent = money(decl);
    $('[name="packages"]').value = JSON.stringify(b);
    return tot;
  }
  f.addEventListener('input', calc);

  // save
  var done = document.getElementById('ix-done'), saved = null;
  function previewUrl(kind) { return kind === 'receipt' ? 'print_invoice_package.php?id=' + saved : 'print_label_ship.php?type=sea&id=' + saved + (kind === 'small' ? '&size=12' : '&size=46'); }
  function setPrint(kind) {
    done.querySelectorAll('[data-ix-print]').forEach(function (x) { var on = x.getAttribute('data-ix-print') === kind; x.classList.toggle('on', on); x.setAttribute('aria-checked', on); x.querySelector('.sl-chk').classList.toggle('on', on); });
    done.querySelector('[data-ix-printgo]').href = previewUrl(kind); done.querySelector('[data-ix-preview]').src = previewUrl(kind);
  }
  done.addEventListener('click', function (e) { var b = e.target.closest('[data-ix-print]'); if (b) setPrint(b.getAttribute('data-ix-print')); if (e.target.closest('[data-ix-reload]')) location.reload(); });
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    var tot = calc();
    var btn = f.querySelector('[type=submit]'); btn.disabled = true; btn.classList.add('loading');
    f.querySelectorAll('.sl-err').forEach(function (x) { x.remove(); });
    SL.post(f.getAttribute('action'), new FormData(f)).then(function (j) {
      if (!j.success) {
        Object.keys(j.errors || {}).forEach(function (k) { var el = f.querySelector('[name="' + k + '"]'); if (!el) return; var s = document.createElement('span'); s.className = 'sl-err'; s.textContent = j.errors[k]; (el.closest('.sl-field') || el.parentNode).appendChild(s); });
        SL.toast(j.message || 'Not saved', true); return;
      }
      saved = j.id;
      var c = custs[cSel.value] || {};
      done.querySelector('[data-ix-donesub]').innerHTML = '<b class="mono" style="color:var(--ink)">' + esc((j.message || '').replace(/^Package | registered$/g, '')) + '</b> · ' + esc((boxes()[0] || {}).description || '') + ' · for ' + esc(c.name || '') + (c.locker ? ' (' + esc(c.locker) + ')' : '');
      done.querySelector('[data-ix-doneprice]').textContent = money(tot) + ' estimated';
      done.querySelector('[data-ix-open]').href = 'customer_packages_view.php?id=' + saved;
      var first = done.querySelector('[data-ix-print]'); if (first) setPrint(first.getAttribute('data-ix-print'));
      done.hidden = false;
      var t = document.querySelector('[data-ix-today]'); if (t) t.textContent = (+t.textContent || 0) + 1;
    }).catch(function () { SL.toast('Network error. Please try again.', true); })
      .finally(function () { btn.disabled = false; btn.classList.remove('loading'); });
  });
  document.querySelectorAll('[data-ix-new]').forEach(function (b) { b.addEventListener('click', function () { location.href = location.pathname; }); });
  calc();
})();
