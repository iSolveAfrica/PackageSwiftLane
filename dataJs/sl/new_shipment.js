/* New Shipment (views/sl/pages/NewShipment.php).
 * Fills the legacy add form fields and posts to ajax/courier/add_courier_ajax.php
 * (or add_courier_client_ajax.php) so the server keeps pricing, numbering,
 * notifications and audit. The quote here mirrors the server arithmetic. */
(function () {
  var f = document.getElementById('ns-form');
  if (!f) return;
  var client = f.getAttribute('data-client') === '1';
  var R = JSON.parse(f.getAttribute('data-rates') || '{}');
  var $ = function (s) { return f.querySelector(s); };
  var num = function (v) { v = parseFloat(String(v || '').replace(/[^0-9.\-]/g, '')); return isFinite(v) ? v : 0; };
  var money = function (v) { return (R.currency === 'USD' ? '$' : R.currency + ' ') + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); };

  function fill(sel, rows, empty, keep) {
    var cur = keep ? sel.value : '';
    sel.innerHTML = '';
    if (empty !== null) { var o = document.createElement('option'); o.value = ''; o.textContent = empty; sel.appendChild(o); }
    rows.forEach(function (r) { var o = document.createElement('option'); o.value = r.id; o.textContent = r.text || r.label || ('#' + r.id); if (r.type) o.setAttribute('data-type', r.type); sel.appendChild(o); });
    if (cur && sel.querySelector('option[value="' + cur + '"]')) sel.value = cur;
    else if (rows.length === 1 || (rows.length && empty === null)) sel.value = rows[0].id;
  }
  var get = function (u) { return fetch(u, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); }).catch(function () { return []; }); };

  // ---- sender search (locker, name, phone, e-mail) ----
  var sSel = $('[data-ns-sender]'), saSel = $('[data-ns-saddr]'), rSel = $('[data-ns-recip]'), raSel = $('[data-ns-raddr]');
  var senderSummary = $('[data-ns-sender-summary]');
  function showSender() {
    if (!senderSummary || !sSel || !sSel.value) return;
    senderSummary.textContent = (sSel.selectedOptions[0]?.textContent || 'Sender') + ' · ' + (saSel.selectedOptions[0]?.textContent || 'No address on file');
    $('[data-ns-change-sender]').hidden = false;
  }
  function findSender() {
    var q = ($('[data-ns-find]') || {}).value || '';
    if (q.trim().length < 2) { SL.toast('Type at least 2 characters', true); return; }
    get('ajax/select2_sender.php?q=' + encodeURIComponent(q.trim())).then(function (rows) {
      rows = Array.isArray(rows) ? rows : [];
      fill(sSel, rows, rows.length ? null : 'No customer matches', false);
      if (!rows.length) SL.toast('No customer matches "' + q + '"', true);
      sSel.dispatchEvent(new Event('change'));
    });
  }
  if (!client) {
    $('[data-ns-findbtn]').addEventListener('click', findSender);
    $('[data-ns-find]').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); findSender(); } });
  }
  function senderId() { return client ? $('[name="sender_id"]').value : sSel.value; }
  function loadSender() {
    var id = senderId();
    if (!id) return;
    if (!client) {
      var t = sSel.options[sSel.selectedIndex];
      get('ajax/select2_sender_addresses.php?id=' + encodeURIComponent(id)).then(function (rows) { fill(saSel, (rows || []).map(function (a) { return { id: a.id, text: [a.text, a.city, a.country].filter(Boolean).join(', ') }; }), rows && rows.length ? null : 'This customer has no address', false); showSender(); quote(); });
    }
    get('ajax/select2_recipient.php?id=' + encodeURIComponent(id) + '&q=').then(function (rows) {
      rows = (rows || []).map(function (r) { return { id: r.id, text: r.text + (r.type === 'user' ? ' (sender)' : ''), type: r.type || 'recipient' }; });
      fill(rSel, rows, rows.length ? null : 'No recipient', false);
      rSel.dispatchEvent(new Event('change'));
    });
  }
  if (sSel) sSel.addEventListener('change', loadSender);
  if (saSel) saSel.addEventListener('change', showSender);
  if ($('[data-ns-change-sender]')) $('[data-ns-change-sender]').addEventListener('click', function () { $('[data-ns-sender-fields]').hidden = false; sSel.focus(); });
  rSel.addEventListener('change', function () {
    var o = rSel.options[rSel.selectedIndex]; var type = o ? (o.getAttribute('data-type') || 'recipient') : 'recipient';
    $('[data-ns-rtype]').value = type;
    if (!rSel.value) { fill(raSel, [], 'Pick the recipient first'); return; }
    get('ajax/select2_recipient_addresses.php?id=' + encodeURIComponent(rSel.value) + '&type=' + type).then(function (rows) {
      fill(raSel, (rows || []).map(function (a) { return { id: a.id, text: [a.text, a.city, a.country].filter(Boolean).join(', ') }; }), rows && rows.length ? null : 'No address on file', false);
      quote();
    });
  });
  if (client || (sSel && sSel.value)) loadSender();
  else if ($('[data-ns-find]') && $('[data-ns-find]').value) findSender();
  if (!client) { var findTimer; $('[data-ns-find]').addEventListener('input', function () { clearTimeout(findTimer); if (this.value.trim().length >= 2) findTimer = setTimeout(findSender, 350); }); }

  var country = $('[data-ns-country]'), prefix = $('[name="code_prefix"]');
  if (country) {
    $('[data-ns-number-change]').addEventListener('click', function () { var panel = $('.ns-number-edit'); panel.hidden = !panel.hidden; if (!panel.hidden) country.focus(); });
    country.addEventListener('change', function () { prefix.value = this.value ? f.dataset.prefix + this.value.toUpperCase() + '-' : f.dataset.prefix; $('[data-ns-prefix]').textContent = prefix.value; });
  }
  if ($('[data-ns-edit-rate]')) $('[data-ns-edit-rate]').addEventListener('click', function () { var rate = $('[name="price_lb"]'); rate.readOnly = false; rate.focus(); rate.select(); });
  var prealert = $('[data-ns-prealert]');
  if (prealert) prealert.addEventListener('change', function () {
    var raw = this.selectedOptions[0]?.dataset.pa; if (!raw) return;
    try {
      var p = JSON.parse(raw);
      sSel.replaceChildren(new Option(p.customer || p.locker || 'Sender', String(p.customer_id), true, true));
      if ($('[data-ns-find]')) $('[data-ns-find]').value = p.locker || p.customer || '';
      sSel.dispatchEvent(new Event('change'));
      if (p.courier_id) $('[name="order_courier"]').value = String(p.courier_id);
      if (p.tracking) $('[name="tracking_number"]').value = p.tracking;
      var first = $('[data-ns-item]');
      if (first) { first.querySelector('[data-k="description"]').value = p.desc || ''; first.querySelector('[data-k="declared_value"]').value = p.price || ''; }
      quote();
    } catch (e) { SL.toast('Could not load pre-alert details', true); }
  });

  // ---- items ----
  var tb = $('[data-ns-items]');
  var tpl = tb.querySelector('[data-ns-item]').cloneNode(true);
  $('[data-ns-add]').addEventListener('click', function () { var r = tpl.cloneNode(true); r.querySelectorAll('input').forEach(function (i) { i.value = i.getAttribute('data-k') === 'qty' ? '1' : ''; }); tb.appendChild(r); r.querySelector('[data-k="description"]').focus(); quote(); });
  tb.addEventListener('click', function (e) { var b = e.target.closest('[data-ns-rm]'); if (!b) return; if (tb.children.length > 1) b.closest('tr').remove(); else b.closest('tr').querySelectorAll('input').forEach(function (i) { i.value = i.getAttribute('data-k') === 'qty' ? '1' : ''; }); quote(); });
  function items() {
    return Array.prototype.map.call(tb.querySelectorAll('[data-ns-item]'), function (tr) {
      var v = function (k) { var i = tr.querySelector('[data-k="' + k + '"]'); return i ? i.value : ''; };
      var cp = num(v('custom_price'));
      return { qty: Math.max(1, num(v('qty')) || 1), description: v('description').trim(), weight: cp > 0 ? 0 : num(v('weight')), custom_price: cp, use_custom_price: cp > 0 ? 1 : 0, declared_value: num(v('declared_value')), fixed_value: 0, length: 0, width: 0, height: 0 };
    }).filter(function (p) { return p.description !== '' || p.weight > 0 || p.custom_price > 0; });
  }

  // ---- toggles and chips ----
  f.addEventListener('change', function (e) { if (e.target.matches('[data-ns-chip]')) e.target.closest('.nt-ch').classList.toggle('on', e.target.checked); });
  var dtype = $('[data-ns-dtype]');
  if (dtype) dtype.addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; dtype.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); $('[name="discount_type"]').value = b.getAttribute('data-v'); quote(); });
  f.querySelectorAll('[data-ns-files]').forEach(function (inp) { inp.addEventListener('change', function () { var n = 0; f.querySelectorAll('[data-ns-files]').forEach(function (x) { n += x.files.length; }); $('[data-ns-fname]').textContent = n ? n + ' file(s) attached' : 'Invoices, receipts, up to 10 MB each'; }); });

  // ---- quote (mirrors ajax/courier/add_courier_ajax.php) ----
  var qt;
  function quote() {
    var p = items(), w = 0, base = 0, decl = 0, pcs = 0, priceLb = num(($('[name="price_lb"]') || {}).value) || R.price_lb;
    p.forEach(function (i) { pcs += i.qty; w += i.weight * i.qty; decl += i.declared_value * i.qty; base += i.use_custom_price ? i.custom_price * i.qty : i.weight * i.qty * priceLb; });
    $('[data-ns-packages]').value = JSON.stringify(p);
    $('[data-ns-wsum]').textContent = w.toFixed(2) + ' ' + R.weight + ' · ' + pcs + ' piece' + (pcs === 1 ? '' : 's');
    paint(p, w, decl, priceLb, base);
  }
  function paint(p, w, decl, priceLb, base) {
    var freight = base;
    var rate = priceLb;
    var dv = num(($('[name="discount_value"]') || {}).value), dt = ($('[name="discount_type"]') || {}).value || 'percent';
    var disc = dt === 'amount' ? dv : freight * dv / 100; if (disc < 0 || disc > freight) disc = 0;
    var ins = num(($('[name="insured_value"]') || {}).value) > 0 ? (R.insurance_mode === 'fixed' ? num(R.insurance_fixed) : num($('[name="insured_value"]').value) * R.insurance / 100) : 0;
    var re = num(($('[name="reexpedicion_value"]') || {}).value);
    var total = Math.round(((freight - disc) + ins + re) * 100) / 100;
    var set = function (k, v) { var el = f.querySelector('[data-q="' + k + '"]'); if (el) el.textContent = v; };
    set('w', w.toFixed(2) + ' ' + R.weight); set('rate', money(rate)); set('freight', money(freight)); set('decl', money(decl)); set('ins', money(ins)); set('total', money(total));
    if (R.rate > 0) set('ghs', '₵' + (total * R.rate).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
    set('note', 'Final price is confirmed on save' + (client ? ' and when the parcel is weighed' : '') + '.');
  }
  f.addEventListener('input', function (e) { if (e.target.closest('[data-ns-items]') || e.target.matches('[data-ns-calc]')) quote(); });
  f.addEventListener('change', function (e) { if (e.target.matches('[name="order_service_options"],[data-ns-saddr],[data-ns-raddr]')) quote(); });
  quote();

  // ---- defaults ----
  var sd = $('[data-ns-savedef]'), ed = f.querySelector('[data-ns-edit]');
  function saveDefaults() {
    var keys = ['order_courier', 'status_courier'], prefs = {};
    keys.forEach(function (k) { var el = $('[name="' + k + '"]'); if (el && el.value) prefs[k] = el.value; });
    prefs.notify_whatsapp_sender = $('[name="notify_whatsapp_sender"]').checked ? 1 : 0; prefs.notify_sms_receiver = $('[name="notify_sms_receiver"]').checked ? 1 : 0;
    SL.post('ajax/sl/shipment_action_ajax.php', { op: 'prefs', prefs: JSON.stringify(prefs) }).then(function (j) { SL.toast(j.message || 'Saved', !j.success); var s = f.querySelector('[data-ns-saved]'); if (s && j.success) s.style.display = ''; });
  }
  if (sd) sd.addEventListener('click', saveDefaults);
  if (ed) ed.addEventListener('click', function () { var t = $('[name="order_courier"]'); if (t) { t.scrollIntoView({ behavior: 'smooth', block: 'center' }); t.focus(); } SL.toast('Change the carrier or status, then save your defaults below.'); });

  // ---- submit to the legacy endpoint ----
  var next = false;
  f.addEventListener('click', function (e) { if (e.target.closest('[type="submit"]')) next = !!e.target.closest('[data-ns-next]'); });
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    quote();
    f.querySelectorAll('.sl-err').forEach(function (x) { x.remove(); });
    f.querySelectorAll('.is-invalid').forEach(function (x) { x.classList.remove('is-invalid'); });
    var miss = [];
    ['sender_id', 'sender_address_id', 'recipient_id', 'recipient_address_id'].forEach(function (k) { var el = $('[name="' + k + '"]'); if (el && !el.value) { miss.push(k); (el.closest('.sl-sel') || el).classList.add('is-invalid'); } });
    if (!items().length) miss.push('items');
    if (miss.length) { SL.toast(miss.indexOf('items') >= 0 && miss.length === 1 ? 'Add at least one item' : 'Fill in the sender, recipient and their addresses', true); return; }
    var btns = f.querySelectorAll('[type="submit"]'); btns.forEach(function (b) { b.disabled = true; b.classList.add('loading'); });
    SL.post(f.getAttribute('action'), new FormData(f)).then(function (j) {
      // A PHP notice printed before the JSON would hide the payload: recover it.
      if (j.shipment_id === undefined && typeof j.message === 'string' && j.message.indexOf('{') >= 0) { try { j = JSON.parse(j.message.slice(j.message.indexOf('{"'))); } catch (e) {} }
      if (j.success) {
        SL.toast((j.messages || ['Shipment created']).join(' '));
        var id = j.shipment_id;
        setTimeout(function () { location.href = next ? f.getAttribute('data-new') + '?saved=' + encodeURIComponent(id || '') : f.getAttribute('data-detail') + '?id=' + encodeURIComponent(id); }, 400);
        return;
      }
      var errs = j.errors || {}, first = '';
      Object.keys(errs).forEach(function (k) {
        var msg = Array.isArray(errs[k]) ? errs[k].join(' ') : errs[k]; first = first || msg;
        var el = $('[name="' + k + '"]'); if (!el) return;
        (el.closest('.sl-sel') || el).classList.add('is-invalid');
        var s = document.createElement('span'); s.className = 'sl-err'; s.textContent = msg; (el.closest('.sl-field') || el.parentNode).appendChild(s);
      });
      SL.toast(first || j.message || j.error || 'The shipment could not be saved', true);
    }).catch(function () { SL.toast('Network error. Please try again.', true); })
      .finally(function () { btns.forEach(function (b) { b.disabled = false; b.classList.remove('loading'); }); });
  });
})();
