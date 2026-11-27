/* Hand Out Packages (views/sl/pages/HandOut.php).
 * Reads: ajax/sl/handout_ajax.php (search, panel, package). Writes: ajax/sl/warehouse_ajax.php (verify_customer, send_customer, handover). */
(function () {
  var page = document.getElementById('ho-page'); if (!page) return;
  var q = document.getElementById('ho-q'), list = document.getElementById('ho-list'), panel = document.getElementById('ho-panel');
  var spin = page.querySelector('[data-ho-spin]'), countEl = page.querySelector('[data-ho-count]');
  var API = 'ajax/sl/handout_ajax.php', WH = 'ajax/sl/warehouse_ajax.php';
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
  var current = +page.getAttribute('data-open') || 0, timer = null, seq = 0, emptyHtml = panel.querySelector('.ho-empty') ? panel.innerHTML : '';

  function setUrl() { var u = new URL(location.href); if (q.value.trim()) u.searchParams.set('q', q.value.trim()); else u.searchParams.delete('q'); if (current) u.searchParams.set('customer', current); else u.searchParams.delete('customer'); u.searchParams.delete('tracking'); history.replaceState(null, '', u); }
  function mark() { list.querySelectorAll('[data-ho-cust]').forEach(function (b) { b.classList.toggle('on', +b.getAttribute('data-ho-cust') === current); }); }

  // ---- live search: filters while typing, opens the customer on one match / exact tracking ----
  function search(openIt) {
    var my = ++seq; spin.hidden = false;
    SL.get(API, { op: 'search', q: q.value.trim() }).then(function (j) {
      if (my !== seq) return;
      spin.hidden = true;
      countEl.textContent = j.total + ' customer' + (j.total === 1 ? '' : 's') + (q.value.trim() ? ' match' + (j.total === 1 ? 'es' : '') : ' with parcels ready');
      list.innerHTML = (j.rows || []).map(function (r) {
        return '<button type="button" class="ho-q" data-ho-cust="' + r.id + '" role="option"><span class="sl-av">' + esc(r.ini) + '</span><span class="col grow" style="min-width:0;text-align:left"><b class="fw5 sm ho-queue-name">' + esc(r.name) + '</b><span class="mut ho-queue-meta">' + esc(r.lock || '—') + ' · ' + esc(r.nL) + (r.hit ? ' · <b>' + esc(r.hit) + '</b>' : '') + '</span><span class="mut ho-queue-meta">ready ' + esc(r.wait) + '</span></span><span class="sl-pill delivered">' + r.n + '</span></button>';
      }).join('') || '<span class="mut sm">No customer with a ready parcel matches "' + esc(q.value.trim()) + '".</span>';
      mark();
      var target = j.open || (openIt && j.rows && j.rows.length ? j.rows[0].id : 0);
      if (target && target !== current) open(target);
      else if (target && target === current) { /* already open */ }
    }).catch(function () { spin.hidden = true; });
  }
  q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { search(false); }, 180); });
  q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(timer); search(true); } if (e.key === 'ArrowDown') { var f = list.querySelector('[data-ho-cust]'); if (f) { e.preventDefault(); f.focus(); } } });
  list.addEventListener('click', function (e) { var b = e.target.closest('[data-ho-cust]'); if (b) open(+b.getAttribute('data-ho-cust')); });
  list.addEventListener('keydown', function (e) { var b = e.target.closest('[data-ho-cust]'); if (!b) return; var n = e.key === 'ArrowDown' ? b.nextElementSibling : e.key === 'ArrowUp' ? b.previousElementSibling : null; if (n && n.matches('[data-ho-cust]')) { e.preventDefault(); n.focus(); } else if (e.key === 'ArrowUp') { e.preventDefault(); q.focus(); } });

  function open(id) {
    current = id; mark(); setUrl();
    panel.innerHTML = '<div class="ho-empty"><span class="ho-spin"></span><span class="mut">Opening…</span></div>';
    SL.get(API, { op: 'panel', customer: id }).then(function (j) {
      if (current !== id) return;
      if (!j.success) { panel.innerHTML = '<div class="ho-empty"><span class="mut">' + esc(j.message || 'Could not open this customer.') + '</span></div>'; return; }
      panel.innerHTML = j.html; init();
    }).catch(function () { panel.innerHTML = '<div class="ho-empty"><span class="mut">Network error. Try again.</span></div>'; });
  }
  function close() { current = 0; mark(); setUrl(); panel.innerHTML = emptyHtml || '<div class="ho-empty"><span class="mut">Pick a customer on the left.</span></div>'; q.focus(); q.select(); }

  // ---- customer panel ----
  function init() {
    var flow = document.getElementById('ho-flow'); if (!flow) return;
    var cust = flow.getAttribute('data-customer'), canUser = flow.getAttribute('data-can-user') === '1';
    var unlocked = false, skipped = false, skipReason = '', verified = [];
    try { verified = JSON.parse(flow.getAttribute('data-verified') || '[]'); } catch (e) { verified = []; }
    var $ = function (s) { return flow.querySelector(s); };
    var digits = flow.querySelectorAll('.ho-digit'), bad = $('[data-ho-bad]'), state = $('[data-ho-state]'), go = $('[data-ho-go]'), allBtn = $('[data-ho-all]'), paidChk = $('[data-ho-paidchk]');
    function rows() { return Array.prototype.slice.call(flow.querySelectorAll('[data-item]')); }
    function code() { var v = ''; digits.forEach(function (i) { v += i.value; }); return v; }
    function showBad(t) { bad.hidden = !t; bad.querySelector('span').textContent = t || ''; }
    function unlock(list, how) {
      unlocked = true;
      rows().forEach(function (r) {
        var ok = r.getAttribute('data-cleared') !== '0' && (skipped || list.indexOf(r.getAttribute('data-item')) >= 0);
        r.classList.toggle('locked', !ok); var t = r.querySelector('[data-ho-tick]'); t.disabled = !ok;
      });
      state.hidden = false; state.querySelector('span').textContent = how;
      flow.querySelector('[data-ho-codeinputs]').hidden = true; var sb = $('[data-ho-skipbox]'); if (sb) sb.hidden = true; showBad('');
      $('[data-ho-lockmsg]').textContent = 'Tick each package as you hand it across, or Check all. Click a package to see what is inside.';
      allBtn.disabled = false; count();
      var first = flow.querySelector('[data-ho-tick]:not([disabled])'); if (first) first.focus();
    }
    // digits: auto-advance, paste, auto-verify on the 6th digit
    flow.addEventListener('input', function (e) {
      if (!e.target.classList.contains('ho-digit')) return;
      e.target.value = e.target.value.replace(/\D/g, '').slice(0, 1);
      var n = e.target.nextElementSibling; if (e.target.value && n && n.classList.contains('ho-digit')) n.focus();
      if (code().length === 6) verify();
    });
    flow.addEventListener('keydown', function (e) { if (e.target.classList.contains('ho-digit') && e.key === 'Backspace' && !e.target.value) { var p = e.target.previousElementSibling; if (p) p.focus(); } });
    flow.addEventListener('paste', function (e) { if (!e.target.classList.contains('ho-digit')) return; var t = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6); if (!t) return; e.preventDefault(); digits.forEach(function (i, k) { i.value = t[k] || ''; }); if (t.length === 6) verify(); });
    var busy = false;
    function verify() {
      if (busy) return; busy = true; showBad('');
      digits.forEach(function (i) { i.disabled = true; });
      SL.post(WH, { op: 'verify_customer', customer: cust, code: code() }).then(function (j) {
        if (j.success) { verified = j.verified || []; unlock(verified, 'Code verified'); SL.toast(j.message || 'Code verified'); }
        else { showBad(j.message || 'Wrong code'); digits.forEach(function (i) { i.value = ''; }); }
      }).catch(function () { showBad('Network error. Type the code again.'); })
        .finally(function () { busy = false; digits.forEach(function (i) { i.disabled = false; }); if (!unlocked) digits[0].focus(); });
    }
    var send = $('[data-ho-send]');
    if (send) send.addEventListener('click', function () { send.disabled = true; SL.post(WH, { op: 'send_customer', customer: cust }).then(function (j) { SL.toast(j.message || '', !j.success); if (j.success) $('[data-ho-codehelp]').textContent = 'Code sent just now · type it in, it checks itself'; }).finally(function () { setTimeout(function () { send.disabled = false; }, 2000); }); });
    // manager skip
    var skip = $('[data-ho-skip]'), skipBox = $('[data-ho-skipbox]');
    if (skip && skipBox) {
      skip.addEventListener('click', function () { skipBox.hidden = false; flow.querySelector('[data-ho-codeinputs]').hidden = true; });
      skipBox.addEventListener('click', function (e) {
        var r = e.target.closest('[data-ho-reason]'); if (r) { skipBox.querySelectorAll('[data-ho-reason]').forEach(function (x) { x.classList.toggle('on', x === r); }); skipBox.querySelector('[data-ho-reasontext]').value = r.getAttribute('data-ho-reason'); }
        if (e.target.closest('[data-ho-skipcancel]')) { skipBox.hidden = true; flow.querySelector('[data-ho-codeinputs]').hidden = false; digits[0].focus(); }
        if (e.target.closest('[data-ho-skipok]')) { skipped = true; skipReason = skipBox.querySelector('[data-ho-reasontext]').value.trim() || 'ID checked in person'; unlock([], 'Code skipped · ' + skipReason); }
      });
    }
    // ticking
    function sel() { return rows().filter(function (r) { return r.classList.contains('ok'); }); }
    function setTick(r, on) { if (!r || r.classList.contains('locked')) return; r.classList.toggle('ok', on); var t = r.querySelector('[data-ho-tick]'); t.setAttribute('aria-pressed', on ? 'true' : 'false'); t.querySelector('span').textContent = on ? 'Checked' : 'Check'; count(); }
    function count() {
      var s = sel(), n = s.length, unpaid = s.some(function (r) { return r.getAttribute('data-paid') !== '1'; });
      if (paidChk) paidChk.hidden = !unpaid;
      if (go) { go.disabled = !n || (!canUser && n > 1); go.querySelector('span').textContent = !canUser && n > 1 ? 'You may hand over one package at a time' : 'Hand over ' + n + ' parcel' + (n === 1 ? '' : 's'); }
      var left = rows().length - n, l = $('[data-ho-left]'); if (l) l.textContent = unlocked && left > 0 && n ? left + ' stay in the warehouse' : '';
      if (allBtn) allBtn.innerHTML = '<i class="ic ic-sm i-check" aria-hidden="true"></i>' + (n && n === rows().filter(function (r) { return !r.classList.contains('locked'); }).length ? 'Uncheck all' : 'Check all');
    }
    flow.addEventListener('click', function (e) {
      var t = e.target.closest('[data-ho-tick]'); if (t) { var r = t.closest('[data-item]'); setTick(r, !r.classList.contains('ok')); return; }
      var o = e.target.closest('[data-ho-open]'); if (o) { showPkg(o.getAttribute('data-ho-open'), o.getAttribute('data-ho-id'), o.closest('[data-item]')); return; }
      if (e.target.closest('[data-ho-all]')) { var avail = rows().filter(function (r) { return !r.classList.contains('locked'); }); var on = sel().length !== avail.length; avail.forEach(function (r) { setTick(r, on); }); return; }
      var pc = e.target.closest('[data-ho-paidchk]'); if (pc) { pc.classList.toggle('on'); pc.querySelector('.sl-chk').classList.toggle('on'); return; }
      if (e.target.closest('[data-ho-close]')) { close(); return; }
      if (e.target.closest('[data-ho-next]')) { q.value = ''; close(); search(false); }
    });
    // optional scan
    var scan = $('[data-ho-scan]');
    scan.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return; e.preventDefault();
      var v = scan.value.trim().toUpperCase(), sb = $('[data-ho-scanbad]'); scan.value = '';
      var row = rows().filter(function (r) { return (r.getAttribute('data-trk') || '').toUpperCase() === v || (r.getAttribute('data-post') || '').toUpperCase() === v; })[0];
      sb.hidden = !!row; if (!row) { sb.querySelector('span').textContent = v + ' is not one of this customer\'s ready parcels.'; return; }
      if (row.classList.contains('locked')) { sb.hidden = false; sb.querySelector('span').textContent = 'Enter the pickup code first.'; return; }
      setTick(row, true); row.classList.add('fresh'); setTimeout(function () { row.classList.remove('fresh'); }, 900);
    });
    // hand over
    if (go) go.addEventListener('click', function () {
      var items = sel().map(function (r) { return r.getAttribute('data-item'); }); if (!items.length) return;
      if (paidChk && !paidChk.hidden && !paidChk.classList.contains('on')) { SL.toast('Confirm the balance was collected for the unpaid package(s)', true); paidChk.focus(); return; }
      go.disabled = true;
      var body = { op: 'handover', items: items.join(','), handover_type: 'picked_up' };
      if (skipped) { body.skip_code = 1; body.skip_reason = skipReason; }
      SL.post(WH, body).then(function (j) {
        SL.toast(j.message || '', !j.success);
        if (!j.success) return;
        flow.querySelectorAll('.ho-hide-done').forEach(function (x) { x.hidden = true; });
        var done = $('[data-ho-done]'); done.hidden = false; done.querySelector('h2').textContent = j.message;
        ['[data-ho-today]', '[data-ho-mine]'].forEach(function (s) { var el = document.querySelector(s); if (el) el.textContent = (+el.textContent || 0) + (j.n || items.length); });
        done.querySelector('[data-ho-next]').focus();
        search(false);
      }).finally(function () { go.disabled = false; count(); });
    });
    // pre-verified (code checked a few minutes ago)
    if (verified.length) unlock(verified, 'Code already verified');
    else if (digits.length && !digits[0].disabled) digits[0].focus();
    count();
  }

  // ---- package pop-up ----
  var dlg = document.getElementById('ho-pkg'), dlgRow = null;
  function showPkg(module, id, row) {
    dlgRow = row;
    var P = function (k) { return dlg.querySelector('[data-p="' + k + '"]'); };
    dlg.querySelector('#ho-pkg-t').textContent = row ? row.getAttribute('data-trk') : 'Package';
    P('sub').textContent = 'Loading…'; P('items').innerHTML = ''; P('hist').innerHTML = ''; P('kv').innerHTML = ''; P('pills').innerHTML = '';
    dlg.hidden = false;
    SL.get(API, { op: 'package', module: module, id: id }).then(function (j) {
      if (!j.success) { P('sub').textContent = j.message || 'Not found'; return; }
      var p = j.package;
      P('sub').textContent = p.modeL + ' · ' + p.cust + (p.lock ? ' (' + p.lock + ')' : '');
      P('pills').innerHTML = '<span class="sl-pill ' + esc(p.payC) + '">' + esc(p.payL) + '</span><span class="sl-pill ' + esc(p.stc) + '">' + esc(p.st) + '</span>' + (p.cleared ? '' : '<span class="sl-pill overdue">Not cleared by Accounts</span>');
      var kv = [['Bay', p.bay], ['Weight', p.w], ['Pieces', p.pieces || '—'], ['Bill', p.total + (p.paidAmt ? ' · paid ' + p.paidAmt : '')], ['Carrier tracking', p.post || '—'], ['Shop', p.shop || '—']];
      P('kv').innerHTML = kv.map(function (x) { return '<div><span>' + esc(x[0]) + '</span><span class="mono">' + esc(x[1]) + '</span></div>'; }).join('');
      P('items').innerHTML = (p.items || []).map(function (i) { return '<div class="ho-item"><b class="fw5">' + i.q + ' × ' + esc(i.d) + '</b><span class="mut sm mono">' + esc([i.w, i.dims, i.v].filter(Boolean).join(' · ')) + '</span></div>'; }).join('') || '<span class="mut sm">No items recorded.</span>';
      P('hist').innerHTML = (p.hist || []).map(function (h) { return '<div class="row between g8"><span>' + esc(h.t) + '</span><span class="mut mono">' + esc(h.d) + '</span></div>'; }).join('') || '<span class="mut">No tracking yet.</span>';
      P('receipt').href = p.links.receipt; P('view').href = p.links.view;
      var tick = P('tick'), locked = !row || row.classList.contains('locked');
      tick.disabled = locked; tick.querySelector('span').textContent = locked ? 'Enter the pickup code first' : (row.classList.contains('ok') ? 'Uncheck this package' : 'Check this package');
    });
  }
  dlg.querySelector('[data-p="tick"]').addEventListener('click', function () { if (!dlgRow) return; var t = dlgRow.querySelector('[data-ho-tick]'); if (t && !t.disabled) t.click(); dlg.hidden = true; });
  dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.hidden = true; });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !dlg.hidden) dlg.hidden = true; });

  if (document.getElementById('ho-flow')) init();
  if (q.value.trim() && !current) search(false);
})();
