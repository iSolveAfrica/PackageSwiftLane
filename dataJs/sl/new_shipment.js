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
  var el = function (tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined && text !== null) e.textContent = text; return e; };
  var trim = function (v) { return String(v || '').trim(); };

  function fill(sel, rows, empty, keep) {
    var cur = keep ? sel.value : '';
    sel.innerHTML = '';
    if (empty !== null) { var o = document.createElement('option'); o.value = ''; o.textContent = empty; sel.appendChild(o); }
    rows.forEach(function (r) { var o = document.createElement('option'); o.value = r.id; o.textContent = r.text || r.label || ('#' + r.id); if (r.type) o.setAttribute('data-type', r.type); sel.appendChild(o); });
    if (cur && sel.querySelector('option[value="' + cur + '"]')) sel.value = cur;
    else if (rows.length === 1 || (rows.length && empty === null)) sel.value = rows[0].id;
  }
  var get = function (u) { return fetch(u, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); }).catch(function () { return []; }); };

  // ---- person rows: name, phone and locker ID side by side ----
  function personCells(p) {
    return [
      el('span', 'ns-p-name', p.name || p.text || 'Unnamed'),
      el('span', 'ns-p-phone mono', p.phone || '—'),
      el('span', 'ns-p-locker mono', p.locker || '—')
    ];
  }
  function personHead() {
    var h = el('div', 'ns-person ns-person-head');
    ['Name', 'Phone', 'Locker ID'].forEach(function (t) { h.appendChild(el('span', '', t)); });
    return h;
  }
  function personButton(p, onPick) {
    var b = el('button', 'ns-person-btn'); b.type = 'button';
    personCells(p).forEach(function (c) { b.appendChild(c); });
    b.addEventListener('click', function () { onPick(p); });
    return b;
  }
  function personRow(p) { var d = el('div', 'ns-person'); personCells(p).forEach(function (c) { d.appendChild(c); }); return d; }
  function renderResults(box, rows, onPick, emptyText) {
    box.replaceChildren();
    if (!rows.length) { box.hidden = false; box.appendChild(el('p', 'sm mut', emptyText)); return; }
    box.hidden = false;
    box.appendChild(personHead());
    rows.forEach(function (p) { box.appendChild(personButton(p, onPick)); });
  }

  // ---- sender and recipient ----
  var sSel = $('[data-ns-sender]'), saSel = $('[data-ns-saddr]'), rSel = $('[data-ns-recip]'), raSel = $('[data-ns-raddr]');
  var findInput = $('[data-ns-find]'), findBtn = $('[data-ns-findbtn]'), senderResults = $('[data-ns-results]');
  var searchBlock = $('[data-ns-search]'), senderFields = $('[data-ns-sender-fields]'), senderSummary = $('[data-ns-sender-summary]'), changeSender = $('[data-ns-change-sender]');
  var recipPanel = $('[data-ns-recipient-panel]'), recipResults = $('[data-ns-rec-results]'), recipEmpty = $('[data-ns-rec-empty]');
  var recipFields = $('[data-ns-recipient-fields]'), recipSummary = $('[data-ns-recipient-summary]'), changeRecip = $('[data-ns-change-recip]');
  var senderInfo = null, recipInfo = null, recipRows = [];

  function renderSenderSummary() {
    if (!senderSummary || !senderInfo) return;
    senderSummary.replaceChildren(personRow(senderInfo));
    var addr = saSel && saSel.selectedOptions[0] ? saSel.selectedOptions[0].textContent : '';
    senderSummary.appendChild(el('div', 'ns-p-addr sm mut', addr || 'No address on file yet'));
  }
  function showSender() { renderSenderSummary(); quote(); }

  function pickSender(p) {
    senderInfo = p;
    fill(sSel, [{ id: p.id, text: p.text }], null, false);
    sSel.value = String(p.id);
    senderResults.hidden = true;
    searchBlock.hidden = true;
    senderFields.hidden = false;
    recipPanel.hidden = false;
    sSel.dispatchEvent(new Event('change'));
  }
  function findSender() {
    var q = trim(findInput && findInput.value);
    if (q.length < 2) { SL.toast('Type at least 2 characters', true); return; }
    get('ajax/select2_sender.php?q=' + encodeURIComponent(q)).then(function (rows) {
      if (trim(findInput.value) !== q) return;
      renderResults(senderResults, Array.isArray(rows) ? rows : [], pickSender, 'No customer matches');
    });
  }
  function loadSender() {
    var id = client ? $('[name="sender_id"]').value : (sSel && sSel.value);
    if (!id) return;
    recipPanel.hidden = false;
    if (!client) {
      get('ajax/select2_sender_addresses.php?id=' + encodeURIComponent(id)).then(function (rows) {
        fill(saSel, (rows || []).map(function (a) { return { id: a.id, text: [a.text, a.city, a.country].filter(Boolean).join(', ') }; }), rows && rows.length ? null : 'This customer has no address', false);
        showSender();
      });
    }
    get('ajax/select2_recipient.php?id=' + encodeURIComponent(id) + '&q=').then(function (rows) {
      recipRows = (rows || []).map(function (r) { return { id: r.id, text: r.text, name: r.name, phone: r.phone, locker: r.locker, type: r.type || 'recipient' }; });
      recipFields.hidden = true;
      recipSummary.replaceChildren();
      renderResults(recipResults, recipRows, pickRecipient, '');
      recipEmpty.hidden = recipRows.length > 0;
      recipResults.hidden = recipRows.length === 0;
    });
  }
  function pickRecipient(p) {
    recipInfo = p;
    fill(rSel, [{ id: p.id, text: p.text, type: p.type }], null, false);
    rSel.value = String(p.id);
    recipResults.hidden = true;
    recipEmpty.hidden = true;
    recipFields.hidden = false;
    rSel.dispatchEvent(new Event('change'));
  }

  if (!client) {
    findBtn.addEventListener('click', findSender);
    findInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); findSender(); } });
    var findTimer;
    findInput.addEventListener('input', function () { clearTimeout(findTimer); if (trim(this.value).length >= 2) findTimer = setTimeout(findSender, 350); });
    changeSender.addEventListener('click', function () {
      senderFields.hidden = true; recipPanel.hidden = true; recipFields.hidden = true; recipInfo = null;
      searchBlock.hidden = false; senderResults.hidden = true; findInput.focus(); findInput.select();
    });
    if (findInput.value) findSender();
  }
  if (saSel) saSel.addEventListener('change', showSender);
  changeRecip.addEventListener('click', function () {
    recipFields.hidden = true; recipInfo = null;
    renderResults(recipResults, recipRows, pickRecipient, '');
    recipResults.hidden = recipRows.length === 0;
  });
  rSel.addEventListener('change', function () {
    var o = rSel.options[rSel.selectedIndex]; var type = o ? (o.getAttribute('data-type') || 'recipient') : 'recipient';
    $('[data-ns-rtype]').value = type;
    if (!rSel.value) { fill(raSel, [], 'Pick the recipient first'); return; }
    get('ajax/select2_recipient_addresses.php?id=' + encodeURIComponent(rSel.value) + '&type=' + type).then(function (rows) {
      fill(raSel, (rows || []).map(function (a) { return { id: a.id, text: [a.text, a.city, a.country].filter(Boolean).join(', ') }; }), rows && rows.length ? null : 'No address on file', false);
      recipSummary.replaceChildren();
      if (recipInfo) recipSummary.appendChild(personRow(recipInfo));
      var addr = raSel.selectedOptions[0] ? raSel.selectedOptions[0].textContent : '';
      recipSummary.appendChild(el('div', 'ns-p-addr sm mut', addr || 'No address on file yet'));
      quote();
    });
  });
  if (sSel) sSel.addEventListener('change', loadSender);
  if (client) loadSender();
  else if (sSel && sSel.value) { senderInfo = { id: sSel.value, name: sSel.selectedOptions[0] ? sSel.selectedOptions[0].textContent : 'Sender', text: sSel.selectedOptions[0] ? sSel.selectedOptions[0].textContent : '' }; senderFields.hidden = false; searchBlock.hidden = true; renderSenderSummary(); loadSender(); }

  // ---- shipment number and country ----
  var country = $('[data-ns-country]'), prefix = $('[name="code_prefix"]'), tag = $('[data-ns-country-tag]');
  var setTag = function () { if (!tag) return; var v = country ? country.value : ''; tag.hidden = !v; tag.textContent = v ? 'Number country: ' + country.options[country.selectedIndex].textContent : ''; };
  if (country) {
    var numBtn = $('[data-ns-number-change]');
    numBtn.addEventListener('click', function () {
      var panel = $('.ns-number-edit'); panel.hidden = !panel.hidden;
      numBtn.setAttribute('aria-expanded', String(!panel.hidden));
      if (!panel.hidden) country.focus();
    });
    country.addEventListener('change', function () {
      prefix.value = this.value ? f.dataset.prefix + this.value.toUpperCase() + '-' : f.dataset.prefix;
      $('[data-ns-prefix]').textContent = prefix.value;
      setTag();
    });
  }
  if ($('[data-ns-edit-rate]')) $('[data-ns-edit-rate]').addEventListener('click', function () { var rate = $('[name="price_lb"]'); rate.readOnly = false; rate.focus(); rate.select(); });

  // ---- tracking numbers: each one is kept with Enter (or a scan) ----
  var trackInput = $('[data-ns-track-input]'), trackValue = $('[data-ns-track-value]'), trackChips = $('[data-ns-track-chips]');
  var tracks = trim(trackValue.value).split(/\r?\n/).map(trim).filter(Boolean);
  var trackingTimer, detectedTracking = null, trackingVersion = 0;
  function renderTracks() {
    trackChips.replaceChildren();
    tracks.forEach(function (t, i) {
      var chip = el('span', 'ns-tchip mono', t);
      var x = el('button', 'ns-tchip-x', '×'); x.type = 'button'; x.setAttribute('aria-label', 'Remove ' + t);
      x.addEventListener('click', function () { tracks.splice(i, 1); renderTracks(); detectPrealert(); });
      chip.appendChild(x); trackChips.appendChild(chip);
    });
    trackValue.value = tracks.join('\n');
  }
  function addTracks(text) {
    String(text).split(/[\r\n,;]+/).map(trim).filter(Boolean).forEach(function (v) { if (tracks.indexOf(v) < 0) tracks.push(v); });
    renderTracks();
  }
  function flushTrack() { if (trim(trackInput.value)) { addTracks(trackInput.value); trackInput.value = ''; } }
  function detectPrealert() {
    if (trackValue.value === detectedTracking) return Promise.resolve();
    var scannedValue = trackValue.value;
    var values = tracks.slice();
    var version = ++trackingVersion;
    return Promise.all(values.map(function (value) { return get('ajax/sl/shipment_prealert_ajax.php?tracking=' + encodeURIComponent(value)); })).then(function (matches) {
      if (version !== trackingVersion) return;
      detectedTracking = scannedValue;
      var alerts = matches.map(function (j) { return j.prealert; }).filter(Boolean);
      var ids = Array.from(new Set(alerts.map(function (p) { return String(p.id); })));
      var note = $('[data-ns-prealert-note]');
      if (ids.length > 1) { $('[name="prealert_id"]').value = ''; note.hidden = false; note.textContent = 'Multiple pre-alerts detected. Create a separate shipment for each pre-alert.'; return; }
      var p = alerts[0];
      $('[name="prealert_id"]').value = p ? p.id : '';
      note.hidden = !p;
      if (!p) return;
      note.textContent = 'Pre-alert linked: ' + p.tracking;
      if (!client) {
        senderInfo = { id: p.customer_id, name: p.customer || p.locker || 'Sender', locker: p.locker || '', phone: '', text: p.customer || '' };
        sSel.replaceChildren(new Option(senderInfo.name, String(p.customer_id), true, true));
        findInput.value = p.locker || p.customer || '';
        searchBlock.hidden = true; senderResults.hidden = true; senderFields.hidden = false; recipPanel.hidden = false;
        sSel.dispatchEvent(new Event('change'));
      }
      if (p.courier_id) $('[name="order_courier"]').value = String(p.courier_id);
      var first = $('[data-ns-item]');
      if (first) { first.querySelector('[data-k="description"]').value = p.desc || ''; first.querySelector('[data-k="declared_value"]').value = p.price || ''; }
      quote();
    });
  }
  renderTracks();
  trackInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); if (trim(trackInput.value)) { flushTrack(); detectPrealert(); } }
    else if (e.key === 'Backspace' && !trackInput.value && tracks.length) { tracks.pop(); renderTracks(); }
  });
  trackInput.addEventListener('paste', function () { setTimeout(function () { if (/[\r\n,;]/.test(trackInput.value)) { flushTrack(); detectPrealert(); } }, 0); });
  trackInput.addEventListener('change', function () { if (trim(trackInput.value)) { flushTrack(); detectPrealert(); } });
  trackInput.addEventListener('input', function () { clearTimeout(trackingTimer); trackingTimer = setTimeout(function () { if (/[\r\n,;]/.test(trackInput.value)) { flushTrack(); detectPrealert(); } }, 250); });
  $('[data-ns-track-box]').addEventListener('click', function (e) { if (e.target === this) trackInput.focus(); });
  if (tracks.length) detectPrealert();

  // ---- dangerous goods ----
  var dangerBox = $('[data-ns-danger-box]'), dangerCb = $('[data-ns-danger]');
  var paintDanger = function () { dangerBox.classList.toggle('on', dangerCb.checked); };
  dangerCb.addEventListener('change', paintDanger);
  paintDanger();

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

  // ---- notification chips ----
  f.addEventListener('change', function (e) { if (e.target.matches('[data-ns-chip]')) e.target.closest('.nt-ch').classList.toggle('on', e.target.checked); });
  var dtype = $('[data-ns-dtype]');
  if (dtype) dtype.addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; dtype.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); $('[name="discount_type"]').value = b.getAttribute('data-v'); quote(); });
  f.querySelectorAll('[data-ns-files]').forEach(function (inp) { inp.addEventListener('change', function () { var n = 0; f.querySelectorAll('[data-ns-files]').forEach(function (x) { n += x.files.length; }); $('[data-ns-fname]').textContent = n ? n + ' file(s) attached' : 'Invoices, receipts, up to 10 MB each'; }); });

  // ---- quote (mirrors ajax/courier/add_courier_ajax.php) ----
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
    var set = function (k, v) { var el2 = f.querySelector('[data-q="' + k + '"]'); if (el2) el2.textContent = v; };
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
    keys.forEach(function (k) { var el2 = $('[name="' + k + '"]'); if (el2 && el2.value) prefs[k] = el2.value; });
    prefs.notify_whatsapp_sender = $('[name="notify_whatsapp_sender"]').checked ? 1 : 0; prefs.notify_sms_receiver = $('[name="notify_sms_receiver"]').checked ? 1 : 0;
    SL.post('ajax/sl/shipment_action_ajax.php', { op: 'prefs', prefs: JSON.stringify(prefs) }).then(function (j) { SL.toast(j.message || 'Saved', !j.success); var s = f.querySelector('[data-ns-saved]'); if (s && j.success) s.style.display = ''; });
  }
  if (sd) sd.addEventListener('click', saveDefaults);
  if (ed) ed.addEventListener('click', function () { var t = $('[name="status_courier"]'); if (t) { t.scrollIntoView({ behavior: 'smooth', block: 'center' }); t.focus(); } });

  // ---- label pop-up after save (staff only) ----
  var labels = { open: null };
  var lbl = document.getElementById('ns-labels');
  if (lbl && !client) {
    var lframe = lbl.querySelector('[data-ns-lframe]'), lsettings = lbl.querySelector('[data-ns-lsettings]');
    var lstate = { id: 0, next: false, size: '46', mode: 'label' };
    var labelSrc = function () { return 'print_label_ship.php?id=' + lstate.id + '&size=' + lstate.size + '&type=air'; };
    var paintSize = function () { lbl.querySelectorAll('[data-ns-lsize-group] button').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-v') === lstate.size); }); };
    var paintSettings = function () { var on = lstate.mode === 'settings'; lsettings.setAttribute('aria-pressed', String(on)); lsettings.querySelector('span').textContent = on ? 'Back to label' : 'Label settings'; };
    var showFrame = function () { lframe.src = lstate.mode === 'settings' ? f.getAttribute('data-labels') : labelSrc(); };
    labels.open = function (id, nextAfter) {
      lstate.id = id; lstate.next = nextAfter; lstate.mode = 'label';
      lbl.querySelector('[data-ns-lsub]').textContent = 'Shipment saved. Choose a label size, then print.';
      paintSize(); paintSettings(); showFrame();
      lbl.hidden = false;
      lbl.querySelector('[data-ns-lprint]').focus();
    };
    lbl.querySelectorAll('[data-ns-lsize-group] button').forEach(function (b) {
      b.addEventListener('click', function () { lstate.size = b.getAttribute('data-v'); lstate.mode = 'label'; paintSize(); paintSettings(); showFrame(); });
    });
    lsettings.addEventListener('click', function () { lstate.mode = lstate.mode === 'settings' ? 'label' : 'settings'; paintSettings(); showFrame(); });
    lbl.querySelector('[data-ns-lprint]').addEventListener('click', function () {
      try { lframe.contentWindow.focus(); lframe.contentWindow.print(); } catch (e) { SL.toast('Could not open the print dialog. Try again.', true); }
    });
    lbl.querySelector('[data-ns-lnew]').addEventListener('click', function () { location.href = f.getAttribute('data-new'); });
    lbl.querySelector('[data-ns-lview]').addEventListener('click', function () { location.href = f.getAttribute('data-detail') + '?id=' + encodeURIComponent(lstate.id); });
    var closeLabels = function () { lbl.hidden = true; lframe.src = 'about:blank'; if (lstate.next) location.href = f.getAttribute('data-new') + '?saved=' + encodeURIComponent(lstate.id); };
    lbl.querySelector('[data-ns-lclose]').addEventListener('click', closeLabels);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !lbl.hidden) closeLabels(); });
  }

  // ---- submit to the legacy endpoint ----
  var next = false;
  f.addEventListener('click', function (e) { if (e.target.closest('[type="submit"]')) next = !!e.target.closest('[data-ns-next]'); });
  f.addEventListener('submit', async function (e) {
    e.preventDefault();
    clearTimeout(trackingTimer);
    flushTrack();
    await detectPrealert();
    quote();
    f.querySelectorAll('.sl-err').forEach(function (x) { x.remove(); });
    f.querySelectorAll('.is-invalid').forEach(function (x) { x.classList.remove('is-invalid'); });
    var miss = [];
    ['sender_id', 'sender_address_id', 'recipient_id', 'recipient_address_id'].forEach(function (k) { var el2 = $('[name="' + k + '"]'); if (el2 && !el2.value) { miss.push(k); (el2.closest('.sl-sel') || el2).classList.add('is-invalid'); } });
    if (!items().length) miss.push('items');
    if (miss.length) { SL.toast(miss.indexOf('items') >= 0 && miss.length === 1 ? 'Add at least one item' : 'Fill in the sender, recipient and their addresses', true); return; }
    var btns = f.querySelectorAll('[type="submit"]'); btns.forEach(function (b) { b.disabled = true; b.classList.add('loading'); });
    SL.post(f.getAttribute('action'), new FormData(f)).then(function (j) {
      // A PHP notice printed before the JSON would hide the payload: recover it.
      if (j.shipment_id === undefined && typeof j.message === 'string' && j.message.indexOf('{') >= 0) { try { j = JSON.parse(j.message.slice(j.message.indexOf('{"'))); } catch (e2) {} }
      if (j.success) {
        SL.toast((j.messages || ['Shipment created']).join(' '));
        var id = j.shipment_id;
        // Staff get the label pop-up here; customers go straight to the shipment.
        if (!client && id && labels.open) { labels.open(id, next); return; }
        setTimeout(function () { location.href = next ? f.getAttribute('data-new') + '?saved=' + encodeURIComponent(id || '') : f.getAttribute('data-detail') + '?id=' + encodeURIComponent(id); }, 400);
        return;
      }
      var errs = j.errors || {}, first = '';
      Object.keys(errs).forEach(function (k) {
        var msg = Array.isArray(errs[k]) ? errs[k].join(' ') : errs[k]; first = first || msg;
        var el2 = $('[name="' + k + '"]'); if (!el2) return;
        (el2.closest('.sl-sel') || el2).classList.add('is-invalid');
        var s = document.createElement('span'); s.className = 'sl-err'; s.textContent = msg; (el2.closest('.sl-field') || el2.parentNode).appendChild(s);
      });
      SL.toast(first || j.message || j.error || 'The shipment could not be saved', true);
    }).catch(function () { SL.toast('Network error. Please try again.', true); })
      .finally(function () { btns.forEach(function (b) { b.disabled = false; b.classList.remove('loading'); }); });
  });
})();
