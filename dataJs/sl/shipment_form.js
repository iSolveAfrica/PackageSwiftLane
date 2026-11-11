/* Shipment / package forms (views/sl/pages/ShipmentForm.php).
 * Same client behaviour as dataJs/sl/new_shipment.js (sender search, address and
 * recipient pickers, item rows, live quote) plus prefill for edit/accept forms.
 * The legacy endpoint in the form's action does the real pricing and saving;
 * the reply is handled by SL.opsSend (dataJs/sl/ops_forms.js). */
(function () {
  var f = document.getElementById('sf-form');
  if (!f) return;
  var R = JSON.parse(f.getAttribute('data-rates') || '{}');
  var PRE = JSON.parse(f.getAttribute('data-pre') || '{}');
  var ITEMS = JSON.parse(f.getAttribute('data-items') || '[]');
  var hasRecip = f.getAttribute('data-recipient') === '1';
  var $ = function (s) { return f.querySelector(s); };
  var num = function (v) { v = parseFloat(String(v == null ? '' : v).replace(/[^0-9.\-]/g, '')); return isFinite(v) ? v : 0; };
  var money = function (v) { return (R.currency === 'USD' ? '$' : R.currency + ' ') + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); };

  function fill(sel, rows, empty, want) {
    sel.innerHTML = '';
    if (empty !== null) { var e = document.createElement('option'); e.value = ''; e.textContent = empty; sel.appendChild(e); }
    rows.forEach(function (r) { var o = document.createElement('option'); o.value = r.id; o.textContent = r.text || r.label || ('#' + r.id); if (r.type) o.setAttribute('data-type', r.type); sel.appendChild(o); });
    if (want && sel.querySelector('option[value="' + want + '"]')) sel.value = String(want);
    else if (rows.length && empty === null) sel.value = rows[0].id;
  }
  var get = function (u) { return fetch(u, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); }).catch(function () { return []; }); };
  var addrText = function (a) { return [a.text, a.city, a.country].filter(Boolean).join(', '); };

  var sSel = $('[data-sf-sender]'), saSel = $('[data-sf-saddr]'), rSel = $('[data-sf-recip]'), raSel = $('[data-sf-raddr]');
  var first = true;
  function findSender() {
    var q = ($('[data-sf-find]').value || '').trim();
    if (q.length < 2) { SL.toast('Type at least 2 characters', true); return; }
    get('ajax/select2_sender.php?q=' + encodeURIComponent(q)).then(function (rows) {
      rows = Array.isArray(rows) ? rows : [];
      fill(sSel, rows, rows.length ? null : 'No customer matches');
      if (!rows.length) SL.toast('No customer matches "' + q + '"', true);
      sSel.dispatchEvent(new Event('change'));
    });
  }
  $('[data-sf-findbtn]').addEventListener('click', findSender);
  $('[data-sf-find]').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); findSender(); } });
  function loadSender() {
    var id = sSel.value; if (!id) return;
    var keep = first;
    get('ajax/select2_sender_addresses.php?id=' + encodeURIComponent(id)).then(function (rows) { fill(saSel, (rows || []).map(function (a) { return { id: a.id, text: addrText(a) }; }), rows && rows.length ? null : 'This customer has no address', keep ? PRE.saddr : 0); quote(); });
    if (!hasRecip) { first = false; return; }
    get('ajax/select2_recipient.php?id=' + encodeURIComponent(id) + '&q=').then(function (rows) {
      rows = (rows || []).map(function (r) { return { id: r.id, text: r.text + (r.type === 'user' ? ' (sender)' : ''), type: r.type || 'recipient' }; });
      var want = 0;
      if (keep && PRE.recip) { rows.forEach(function (r) { if (String(r.id) === String(PRE.recip) && (r.type || 'recipient') === (PRE.rtype || 'recipient')) want = r.id; }); }
      fill(rSel, rows, rows.length ? null : 'No recipient', want);
      if (want) { var o = rSel.querySelector('option[value="' + want + '"][data-type="' + (PRE.rtype || 'recipient') + '"]'); if (o) o.selected = true; }
      onRecip(keep);
      first = false;
    });
  }
  function onRecip(keep) {
    if (!rSel) return;
    var o = rSel.options[rSel.selectedIndex], type = o ? (o.getAttribute('data-type') || 'recipient') : 'recipient';
    $('[data-sf-rtype]').value = type;
    if (!rSel.value) { fill(raSel, [], 'Pick the recipient first'); return; }
    get('ajax/select2_recipient_addresses.php?id=' + encodeURIComponent(rSel.value) + '&type=' + type).then(function (rows) {
      fill(raSel, (rows || []).map(function (a) { return { id: a.id, text: addrText(a) }; }), rows && rows.length ? null : 'No address on file', keep ? PRE.raddr : 0);
      quote();
    });
  }
  sSel.addEventListener('change', function () { first = false; loadSender(); });
  if (rSel) rSel.addEventListener('change', function () { onRecip(false); });
  if (sSel.value) loadSender();

  // ---- items ----
  var tb = $('[data-sf-items]');
  var tpl = tb.querySelector('[data-sf-item]').cloneNode(true);
  function row(it) {
    var r = tpl.cloneNode(true);
    r.querySelectorAll('input').forEach(function (i) { var k = i.getAttribute('data-k'); var v = it && it[k] !== undefined && it[k] !== null && it[k] !== 0 ? it[k] : (k === 'qty' ? 1 : ''); i.value = v; });
    return r;
  }
  if (ITEMS.length) { tb.innerHTML = ''; ITEMS.forEach(function (it) { tb.appendChild(row(it)); }); }
  $('[data-sf-add]').addEventListener('click', function () { var r = row(null); tb.appendChild(r); r.querySelector('[data-k="description"]').focus(); quote(); });
  tb.addEventListener('click', function (e) { var b = e.target.closest('[data-sf-rm]'); if (!b) return; if (tb.children.length > 1) b.closest('tr').remove(); else b.closest('tr').querySelectorAll('input').forEach(function (i) { i.value = i.getAttribute('data-k') === 'qty' ? '1' : ''; }); quote(); });
  function items() {
    return Array.prototype.map.call(tb.querySelectorAll('[data-sf-item]'), function (tr) {
      var v = function (k) { var i = tr.querySelector('[data-k="' + k + '"]'); return i ? i.value : ''; };
      var cp = num(v('custom_price'));
      return { qty: Math.max(1, num(v('qty')) || 1), description: v('description').trim(), weight: cp > 0 ? 0 : num(v('weight')), length: num(v('length')), width: num(v('width')), height: num(v('height')), custom_price: cp, use_custom_price: cp > 0 ? 1 : 0, declared_value: num(v('declared_value')), fixed_value: 0 };
    }).filter(function (p) { return p.description !== '' || p.weight > 0 || p.custom_price > 0; });
  }

  // ---- toggles ----
  var dg = $('[data-sf-dg]');
  if (dg) dg.addEventListener('click', function () { var on = !dg.classList.contains('on'); dg.classList.toggle('on', on); dg.setAttribute('aria-checked', on ? 'true' : 'false'); $('[name="is_dangerous_good"]').value = on ? 1 : 0; });
  var dtype = $('[data-sf-dtype]');
  dtype.addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; dtype.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); $('[name="discount_type"]').value = b.getAttribute('data-v'); quote(); });

  // ---- quote (mirrors the server arithmetic; the server result is final) ----
  var qt;
  function quote() {
    var p = items(), w = 0, base = 0, decl = 0, pcs = 0, priceLb = num(($('[name="price_lb"]') || {}).value) || R.price_lb;
    p.forEach(function (i) { var vol = R.meter > 0 ? i.length * i.width * i.height / R.meter : 0; var cw = Math.max(i.weight, vol); pcs += i.qty; w += cw * i.qty; decl += i.declared_value * i.qty; base += i.use_custom_price ? i.custom_price * i.qty : cw * i.qty * priceLb; });
    $('[data-sf-packages]').value = JSON.stringify(p);
    $('[data-sf-wsum]').textContent = w.toFixed(2) + ' ' + R.weight + ' · ' + pcs + ' piece' + (pcs === 1 ? '' : 's');
    paint(w, decl, priceLb, base);
  }
  function paint(w, decl, priceLb, base) {
    var freight = base;
    var dv = num($('[name="discount_value"]').value), dt = $('[name="discount_type"]').value || 'percent';
    var disc = dt === 'amount' ? dv : freight * dv / 100; if (disc < 0 || disc > freight) disc = 0;
    var insured = num($('[name="insured_value"]').value);
    var ins = insured > 0 ? (R.insurance_mode === 'fixed' ? num(R.insurance_fixed) : insured * R.insurance / 100) : 0;
    var re = num($('[name="reexpedicion_value"]').value);
    var total = Math.round(((freight - disc) + ins + re) * 100) / 100;
    var set = function (k, v) { var el = f.querySelector('[data-q="' + k + '"]'); if (el) el.textContent = v; };
    set('w', w.toFixed(2) + ' ' + R.weight); set('freight', money(freight)); set('decl', money(decl)); set('ins', money(ins)); set('total', money(total));
  }
  f.addEventListener('input', function (e) { if (e.target.closest('[data-sf-items]') || e.target.matches('[data-sf-calc]')) quote(); });
  f.addEventListener('change', function (e) { if (e.target.matches('[name="order_service_options"],[data-sf-saddr],[data-sf-raddr]')) quote(); });
  quote();

  // ---- submit ----
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    quote();
    f.querySelectorAll('.is-invalid').forEach(function (x) { x.classList.remove('is-invalid'); });
    var miss = [];
    ['sender_id', 'sender_address_id', 'recipient_id', 'recipient_address_id'].forEach(function (k) { var el = $('[name="' + k + '"]'); if (el && !el.value) { miss.push(k); (el.closest('.sl-sel') || el).classList.add('is-invalid'); } });
    if (!items().length) miss.push('items');
    if (miss.length) { SL.toast(miss.indexOf('items') >= 0 && miss.length === 1 ? 'Add at least one item' : 'Fill in the customer' + (hasRecip ? ', recipient' : '') + ' and addresses', true); return; }
    SL.opsSend(f);
  });
})();
