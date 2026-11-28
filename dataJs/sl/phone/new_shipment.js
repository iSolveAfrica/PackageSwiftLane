/* Phone M-CShip1..3: customer's new shipment in three steps (views/sl/phone/NewShipment.customer.php).
 * Same lookups (ajax/select2_recipient*.php, ajax/sl/shipment_prealert_ajax.php), same quote arithmetic and the
 * same POST (FormData of the web form's field names) to ajax/courier/add_courier_client_ajax.php as
 * dataJs/sl/new_shipment.js; the server keeps pricing, numbering and notifications. */
(function () {
  'use strict';
  var f = document.getElementById('pns-form');
  if (!f || !window.SL) return;
  var R = {}; try { R = JSON.parse(f.getAttribute('data-rates') || '{}'); } catch (e) {}
  var $ = function (s) { return f.querySelector(s); };
  var $$ = function (s) { return Array.prototype.slice.call(f.querySelectorAll(s)); };
  var num = function (v) { v = parseFloat(String(v || '').replace(/[^0-9.\-]/g, '')); return isFinite(v) ? v : 0; };
  var money = function (v) { return (R.currency === 'USD' ? '$' : R.currency + ' ') + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); };
  var trim = function (v) { return String(v || '').trim(); };
  var esc = function (s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; };
  var get = function (u) { return fetch(u, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); }).catch(function () { return []; }); };
  var label = function (sel) { return sel && sel.selectedOptions && sel.selectedOptions[0] && sel.value ? sel.selectedOptions[0].textContent : ''; };
  function fill(sel, rows, empty) {
    sel.innerHTML = '';
    if (empty !== null) sel.appendChild(new Option(empty, ''));
    rows.forEach(function (r) { var o = new Option(r.text || ('#' + r.id), r.id); if (r.type) o.setAttribute('data-type', r.type); sel.appendChild(o); });
    if (rows.length && (empty === null || rows.length === 1)) sel.value = rows[0].id;
  }

  // ---- steps ----
  var step = 1, steps = $$('[data-pns-step]'), bar = $('[data-pns-steps]'), go = $('[data-pns-go]'), book = $('[data-pns-book]'), back = document.querySelector('[data-pns-back]');
  function show(n) {
    step = n;
    steps.forEach(function (s) { s.hidden = s.getAttribute('data-pns-step') !== String(n); });
    bar.setAttribute('aria-label', 'Step ' + n + ' of 3');
    Array.prototype.forEach.call(bar.children, function (li, i) { li.className = i + 1 < n ? 'done' : (i + 1 === n ? 'cur' : ''); });
    go.hidden = n === 3; book.hidden = n !== 3;
    go.innerHTML = (n === 1 ? 'Next: items' : 'Next: price') + '<i class="ic ic-sm i-right" aria-hidden="true"></i>';
    var body = f.querySelector('.mx-body'); if (body) body.scrollTop = 0;
    summary();
  }
  function check(n) {
    if (n === 1) {
      var miss = ['sender_address_id', 'recipient_id', 'recipient_address_id'].filter(function (k) { return !$('[name="' + k + '"]').value; });
      if (miss.length) { SL.toast(miss[0] === 'sender_address_id' ? 'Add an address in My account first' : 'Pick the recipient and their delivery address', true); return false; }
    }
    if (n === 2) { flushTrack(); if (!items.length) { SL.toast('Add at least one item', true); return false; } }
    return true;
  }
  go.addEventListener('click', function () { if (check(step)) show(step + 1); });
  if (back) back.addEventListener('click', function (e) { if (step > 1) { e.preventDefault(); e.stopImmediatePropagation(); show(step - 1); } }, true);

  // ---- radio cards (service, courier) write a hidden field ----
  $$('[data-pns-radio]').forEach(function (g) {
    var input = $('[name="' + g.getAttribute('data-pns-radio') + '"]');
    g.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-v]'); if (!b) return;
      g.querySelectorAll('button[data-v]').forEach(function (x) { var on = x === b; x.classList.toggle('on', on); x.setAttribute('aria-checked', on ? 'true' : 'false'); });
      input.value = b.getAttribute('data-v'); summary();
    });
  });

  // ---- recipient and addresses (same endpoints as the web form) ----
  var saSel = $('[data-pns-saddr]'), rSel = $('[data-pns-recip]'), raSel = $('[data-pns-raddr]');
  var uid = f.getAttribute('data-uid');
  get('ajax/select2_recipient.php?id=' + encodeURIComponent(uid) + '&q=').then(function (rows) {
    rows = Array.isArray(rows) ? rows : [];
    fill(rSel, rows.map(function (r) { return { id: r.id, text: r.text || r.name, type: r.type || 'recipient' }; }), rows.length ? 'Choose a recipient' : 'No recipients yet · tap New');
    if (rSel.value) rSel.dispatchEvent(new Event('change'));
  });
  rSel.addEventListener('change', function () {
    var o = rSel.options[rSel.selectedIndex], type = o ? (o.getAttribute('data-type') || 'recipient') : 'recipient';
    $('[data-pns-rtype]').value = type;
    if (!rSel.value) { fill(raSel, [], 'Pick the recipient first'); summary(); return; }
    get('ajax/select2_recipient_addresses.php?id=' + encodeURIComponent(rSel.value) + '&type=' + type).then(function (rows) {
      rows = Array.isArray(rows) ? rows : [];
      fill(raSel, rows.map(function (a) { return { id: a.id, text: [a.text, a.city, a.country].filter(Boolean).join(', ') }; }), rows.length ? null : 'No address on file');
      summary();
    });
  });
  saSel.addEventListener('change', summary); raSel.addEventListener('change', summary);

  // ---- tracking numbers ----
  var tInput = $('[data-pns-track-input]'), tValue = $('[data-pns-track-value]'), chips = $('[data-pns-chips]');
  var tracks = trim(tValue.value).split(/\r?\n/).map(trim).filter(Boolean), detected = null;
  function renderTracks() {
    chips.querySelectorAll('.pc-chip').forEach(function (c) { c.remove(); });
    tracks.forEach(function (t, i) {
      var s = document.createElement('span'); s.className = 'pc-chip'; s.textContent = t + ' ';
      var x = document.createElement('button'); x.type = 'button'; x.setAttribute('aria-label', 'Remove ' + t); x.innerHTML = '<i class="ic ic-sm i-x" aria-hidden="true"></i>';
      x.addEventListener('click', function () { tracks.splice(i, 1); renderTracks(); });
      s.appendChild(x); chips.insertBefore(s, tInput);
    });
    tValue.value = tracks.join('\n');
  }
  function flushTrack() {
    var v = trim(tInput.value); if (!v) return;
    v.split(/[\r\n,;]+/).map(trim).filter(Boolean).forEach(function (t) { if (tracks.indexOf(t) < 0) tracks.push(t); });
    tInput.value = ''; renderTracks(); detectPrealert();
  }
  function detectPrealert() {
    if (tValue.value === detected || !tracks.length) return Promise.resolve();
    detected = tValue.value;
    return Promise.all(tracks.map(function (t) { return get('ajax/sl/shipment_prealert_ajax.php?tracking=' + encodeURIComponent(t)); })).then(function (m) {
      var alerts = m.map(function (j) { return j && j.prealert; }).filter(Boolean);
      var ids = alerts.map(function (p) { return String(p.id); }).filter(function (v, i, a) { return a.indexOf(v) === i; });
      if (ids.length !== 1) { $('[name="prealert_id"]').value = ''; if (ids.length > 1) SL.toast('Several pre-alerts match: create one shipment per pre-alert.', true); return; }
      var p = alerts[0]; $('[name="prealert_id"]').value = p.id;
      if (p.courier_id) { var b = f.querySelector('[data-pns-radio="order_courier"] button[data-v="' + p.courier_id + '"]'); if (b) b.click(); else $('[name="order_courier"]').value = p.courier_id; }
      SL.toast('Pre-alert linked: ' + p.tracking);
    });
  }
  tInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); flushTrack(); } else if (e.key === 'Backspace' && !tInput.value && tracks.length) { tracks.pop(); renderTracks(); } });
  tInput.addEventListener('change', flushTrack);
  $('[data-pns-track-add]').addEventListener('click', function () { flushTrack(); tInput.focus(); });
  renderTracks(); if (tracks.length) detectPrealert();

  // ---- dangerous goods ----
  var dBox = $('[data-pns-danger-box]'), dCb = $('[data-pns-danger]');
  dCb.addEventListener('change', function () { dBox.classList.toggle('on', dCb.checked); });

  // ---- items ----
  var items = [], rowsBox = $('[data-pns-rows]');
  var iv = function (k) { return $('[data-pns-i="' + k + '"]'); };
  function renderItems() {
    var q = 0, w = 0, d = 0;
    rowsBox.innerHTML = items.map(function (it, i) {
      q += it.qty; w += it.weight * it.qty; d += it.declared_value * it.qty;
      return '<div class="b1-tr" style="grid-template-columns:34px 1fr 54px 76px 36px"><span>' + it.qty + '</span><span>' + esc(it.description || 'Item') + '</span><span class="r">' + it.weight + ' ' + esc(R.weight) + '</span><span class="r">' + (it.declared_value ? money(it.declared_value) : '—') + '</span><button type="button" class="b1-x pc-x" data-pns-rm="' + i + '" aria-label="Remove ' + esc(it.description || 'item') + '"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>';
    }).join('');
    $('[data-pns-tq]').textContent = q; $('[data-pns-tw]').textContent = (Math.round(w * 100) / 100) + ' ' + R.weight; $('[data-pns-td]').textContent = d ? money(d) : '—';
    quote();
  }
  rowsBox.addEventListener('click', function (e) { var b = e.target.closest('[data-pns-rm]'); if (!b) return; items.splice(parseInt(b.getAttribute('data-pns-rm'), 10), 1); renderItems(); });
  $('[data-pns-additem]').addEventListener('click', function () {
    var it = { qty: Math.max(1, Math.round(num(iv('qty').value)) || 1), description: trim(iv('description').value), weight: num(iv('weight').value), custom_price: 0, use_custom_price: 0, declared_value: num(iv('declared_value').value), fixed_value: 0, length: 0, width: 0, height: 0 };
    if (!it.description && !it.weight) { SL.toast('Say what is inside, or its weight', true); iv('description').focus(); return; }
    items.push(it); iv('description').value = ''; iv('weight').value = ''; iv('declared_value').value = ''; iv('qty').value = '1';
    renderItems(); iv('description').focus();
  });
  var fa = $('[data-pns-focus-add]'); if (fa) fa.addEventListener('click', function (e) { e.preventDefault(); iv('description').focus(); });

  // ---- files ----
  $$('[data-pns-files]').forEach(function (inp) { inp.addEventListener('change', function () { var n = 0; $$('[data-pns-files]').forEach(function (x) { n += x.files.length; }); $('[data-pns-fname]').textContent = n ? n + ' file(s) attached' : 'Invoices, receipts or photos, up to 10 MB each.'; }); });

  // ---- quote (mirrors dataJs/sl/new_shipment.js for customers) ----
  function quote() {
    var w = 0, base = 0, decl = 0, priceLb = num(($('[name="price_lb"]') || {}).value) || R.price_lb || 0;
    items.forEach(function (i) { w += i.weight * i.qty; decl += i.declared_value * i.qty; base += i.weight * i.qty * priceLb; });
    $('[data-pns-packages]').value = JSON.stringify(items);
    var total = Math.round(base * 100) / 100;
    var set = function (k, v) { f.querySelectorAll('[data-q="' + k + '"]').forEach(function (x) { x.textContent = v; }); };
    set('w', (Math.round(w * 100) / 100) + ' ' + R.weight); set('rate', money(priceLb)); set('freight', money(base)); set('decl', money(decl)); set('total', money(total));
    if (R.rate > 0) set('ghs', '₵' + (total * R.rate).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
  }
  function summary() {
    var from = label(saSel), to = label(raSel), who = label(rSel);
    $('[data-pns-from]').textContent = from || 'No address saved yet';
    $('[data-pns-to]').textContent = to || 'Pick the recipient below';
    $('[data-pns-to-who]').textContent = who ? 'Recipient · ' + who : 'Recipient';
    var city = function (s) { var p = String(s).split(',').map(trim).filter(Boolean); return p.length > 1 ? p[p.length - 2] + ', ' + p[p.length - 1] : (s || '—'); };
    $('[data-pns-route]').innerHTML = esc(city(from)) + ' <i class="ic ic-sm i-arrow" aria-hidden="true"></i> ' + esc(city(to));
    var svc = f.querySelector('[data-pns-radio="order_service_options"] .on b'); $('[data-pns-svc]').textContent = svc ? svc.textContent : 'Air';
    var cr = f.querySelector('[data-pns-radio="order_courier"] .on b'); $('[data-pns-sum-courier]').textContent = cr ? cr.textContent : '—';
    $('[data-pns-sum-recip]').textContent = who ? who.split(/[·|(]/)[0].trim() : '—';
    var q = items.reduce(function (a, i) { return a + i.qty; }, 0); $('[data-pns-sum-items]').textContent = q + (tracks.length ? ' · ' + tracks.length + ' box' + (tracks.length === 1 ? '' : 'es') : '');
  }

  // ---- submit to the legacy endpoint ----
  var next = false, busy = false;
  f.addEventListener('click', function (e) { var s = e.target.closest('[type="submit"]'); if (s) next = s.hasAttribute('data-pns-next'); });
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    if (busy) return;
    flushTrack(); quote();
    if (!check(1)) { show(1); return; }
    if (!check(2)) { show(2); return; }
    busy = true;
    var btns = $$('[type="submit"]'); btns.forEach(function (b) { b.disabled = true; b.setAttribute('aria-busy', 'true'); });
    var fd = new FormData(f);
    ['filesMultiple[]', 'filesVideo[]'].forEach(function (k) {
      var keep = fd.getAll(k).filter(function (x) { return x && typeof x === 'object' && x.size > 0 && x.name; });
      fd.delete(k); keep.forEach(function (x) { fd.append(k, x, x.name); });
    });
    var saved = false;
    SL.post(f.getAttribute('action'), fd).then(function (j) {
      if (j.shipment_id === undefined && typeof j.message === 'string' && j.message.indexOf('{') >= 0) { try { j = JSON.parse(j.message.slice(j.message.indexOf('{"'))); } catch (e2) {} }
      if (j.success) {
        saved = true;
        SL.toast((j.messages || ['Shipment created']).join(' '));
        var id = j.shipment_id || '';
        setTimeout(function () { location.href = next ? f.getAttribute('data-new') + '?saved=' + encodeURIComponent(id) : f.getAttribute('data-detail') + '?id=' + encodeURIComponent(id); }, 400);
        return;
      }
      var errs = j.errors || {}, first = '';
      Object.keys(errs).forEach(function (k) { var m = Array.isArray(errs[k]) ? errs[k].join(' ') : errs[k]; first = first || m; });
      SL.toast(first || j.message || j.error || 'The shipment could not be saved', true);
    }).catch(function () { SL.toast('Network error. Please try again.', true); })
      .finally(function () { busy = false; btns.forEach(function (b) { b.disabled = saved; b.removeAttribute('aria-busy'); }); });
  });

  renderItems(); show(1);
})();
