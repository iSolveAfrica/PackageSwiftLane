/* Notification Studio (views/sl/pages/NotifyStudio.php).
 * E-mail: a list of blocks you add, reorder, edit and remove, with a live
 * preview the server compiles (the same code that sends the e-mail), shown at
 * desktop or phone width.
 * SMS / WhatsApp / in-app: a text box with a phone-style preview.
 * The AI assistant (King) rewrites a channel, every channel of a message, or
 * every status: its results are PROPOSALS kept in this browser tab
 * (sessionStorage) until the administrator accepts, retries or discards them.
 * Saves to ajax/sl/notify_studio_ajax.php. */
(function () {
  'use strict';
  var dataEl = document.getElementById('nsd-data');
  if (!dataEl) return;
  var D = JSON.parse(dataEl.textContent || '{}');
  var URL_AJAX = 'ajax/sl/notify_studio_ajax.php';
  var AI = D.ai || {};
  var KING = AI.name || 'King';
  var f = document.getElementById('nsd-form');
  var el = function (tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };
  var escH = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
  function busy(btn, on) { if (!btn) return; btn.disabled = on; btn.classList.toggle('loading', on); if (on) btn.setAttribute('aria-busy', 'true'); else btn.removeAttribute('aria-busy'); }
  var chLabels = D.channelLabels || { email: 'E-mail', sms: 'SMS', whatsapp: 'WhatsApp', inapp: 'In-app' };
  // SL.post without a way to stop it: the bulk redraft needs to give up on a
  // status that takes too long, and Stop must end the request in flight.
  function postAbortable(data, signal) {
    var m = document.querySelector('meta[name="csrf-token"]'), pm = document.querySelector('meta[name="csrf-param"]');
    var token = m ? m.content : '', body = new URLSearchParams();
    Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
    body.append(pm ? pm.content : 'csrf_token', token);
    return fetch(URL_AJAX, { method: 'POST', body: body, signal: signal, credentials: 'same-origin', headers: { 'X-CSRF-Token': token, 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        if (r.redirected && /\/(login|auth-otp)\.php(?:[?#]|$)/.test(r.url)) throw new Error('Your session expired. Sign in again.');
        return r.text().then(function (t) { var j; try { j = JSON.parse(t); } catch (e) { j = null; } if (!j || typeof j !== 'object') j = { success: false, message: 'The server returned an unexpected response.' }; if (!r.ok) j.success = false; return j; });
      });
  }
  // Keep the open status visible in the list (it can sit far down).
  (function () {
    var on = document.querySelector('.nsd-scroll .nsd-item.on'), sc = on && on.closest('.nsd-scroll');
    if (!on || !sc) return;
    var top = on.offsetTop - sc.offsetTop;
    if (top < sc.scrollTop || top + on.offsetHeight > sc.scrollTop + sc.clientHeight) sc.scrollTop = Math.max(0, top - sc.clientHeight / 3);
  })();

  // ================= proposals (sessionStorage) =================
  var PKEY = 'nsd.p.';
  function propGet(key, ch) { try { var v = sessionStorage.getItem(PKEY + key + '|' + ch); return v ? JSON.parse(v) : null; } catch (e) { return null; } }
  function propSet(key, ch, p) { try { sessionStorage.setItem(PKEY + key + '|' + ch, JSON.stringify(p)); } catch (e) { /* storage full or blocked: the proposal stays on screen only */ } }
  function propDel(key, ch) { try { sessionStorage.removeItem(PKEY + key + '|' + ch); } catch (e) {} }
  function propAll() {
    var out = [];
    try {
      for (var i = 0; i < sessionStorage.length; i++) {
        var k = sessionStorage.key(i);
        if (k && k.indexOf(PKEY) === 0) { var parts = k.slice(PKEY.length).split('|'); out.push({ key: parts[0], ch: parts[1] }); }
      }
    } catch (e) {}
    return out;
  }
  function markProposals() {
    var all = propAll(), byKey = {};
    all.forEach(function (p) { (byKey[p.key] = byKey[p.key] || []).push(p.ch); });
    document.querySelectorAll('.nsd-item[data-key]').forEach(function (a) {
      var dot = a.querySelector('.nsd-prop-dot'); if (dot) dot.hidden = !byKey[a.getAttribute('data-key')];
    });
    document.querySelectorAll('.nsd-tab[data-ch]').forEach(function (t) {
      var dot = t.querySelector('.nsd-prop-dot'); if (dot) dot.hidden = !(byKey[D.key] && byKey[D.key].indexOf(t.getAttribute('data-ch')) >= 0);
    });
  }

  // ================= tone pickers =================
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.nsd-tone');
    if (!b) return;
    var g = b.closest('.nsd-tones');
    g.querySelectorAll('.nsd-tone').forEach(function (x) { var on = x === b; x.classList.toggle('on', on); x.setAttribute('aria-checked', on ? 'true' : 'false'); });
  });
  function toneOf(root) { var on = root && root.querySelector('.nsd-tone.on'); return on ? on.getAttribute('data-tone') : 'keep'; }

  // ================= the global switch =================
  var master = document.querySelector('[data-nsd-drafts]');
  if (master && !master.hasAttribute('data-static')) master.addEventListener('click', function () {
    setTimeout(function () {
      var on = master.classList.contains('on');
      var q = on ? 'Send the message drafted for each status again (for every status without a saved message)?'
        : 'Go back to the old shared messages? Every status without a saved message will send the same shared message again.';
      if (!window.confirm(q)) { master.classList.toggle('on', !on); master.setAttribute('aria-checked', String(!on)); return; }
      master.setAttribute('aria-busy', 'true');
      SL.post(URL_AJAX, { op: 'drafts', on: on ? 1 : 0 }).then(function (j) {
        SL.toast(j.message || (j.success ? 'Saved' : 'Not saved'), !j.success);
        if (j.success) setTimeout(function () { location.reload(); }, 700);
        else { master.classList.toggle('on', !on); master.setAttribute('aria-checked', String(!on)); }
      }).catch(function (err) { SL.toast(err.message || 'Network error. Please try again.', true); master.classList.toggle('on', !on); master.setAttribute('aria-checked', String(!on)); })
        .finally(function () { master.removeAttribute('aria-busy'); });
    }, 0);
  });

  // ================= bulk redraft (every status) =================
  (function bulkRedraft() {
    var dlg = document.getElementById('nsd-bulk'), open = document.querySelector('[data-nsd-bulk]');
    if (!dlg || !open) return;
    var $b = function (s) { return dlg.querySelector(s); };
    var list = $b('[data-nsd-bulk-list]'), start = $b('[data-nsd-bulk-start]'), stopB = $b('[data-nsd-bulk-stop]'), saveB = $b('[data-nsd-bulk-save]'), clearB = $b('[data-nsd-bulk-clear]');
    var prog = $b('[data-nsd-bulk-prog]'), bar = $b('[data-nsd-bulk-bar]'), ptext = $b('[data-nsd-bulk-text]'), skip = $b('[data-nsd-bulk-skip]');
    var running = false, stopAsked = false, ctrl = null, PER_STATUS_MS = 90000, results = {};
    function hrefOf(key) { var a = document.querySelector('.nsd-item[data-key="' + key + '"]'); return a ? a.getAttribute('href') : 'notify_studio.php?t=' + encodeURIComponent(key); }
    function targets() { return (D.statusKeys || []).filter(function (s) { return !(skip.checked && s.custom); }); }
    function hasProp(key) { return propAll().some(function (p) { return p.key === key; }); }
    function draw() {
      list.replaceChildren();
      (D.statusKeys || []).forEach(function (s) {
        var li = el('li', 'nsd-bulk-row'); li.setAttribute('data-key', s.key);
        var a = el('a', 'nsd-link', s.label); a.href = hrefOf(s.key);
        var st = el('span', 'nsd-bulk-st');
        if (skip.checked && s.custom) { st.textContent = 'Skipped: has a custom message'; li.classList.add('skip'); }
        else if (hasProp(s.key)) { st.textContent = 'Proposal waiting for review'; li.classList.add('ready'); }
        else st.textContent = 'Waiting';
        li.appendChild(a); li.appendChild(st); list.appendChild(li);
      });
      var waiting = propAll().filter(function (p) { return p.key.indexOf('status:') === 0; }).length;
      saveB.hidden = running || !waiting; clearB.hidden = running || !waiting;
      if (waiting) saveB.textContent = 'Save all ' + waiting + ' proposals as custom…';
    }
    function row(key, cls, text) {
      var li = list.querySelector('[data-key="' + key + '"]'); if (!li) return;
      li.className = 'nsd-bulk-row ' + cls; li.querySelector('.nsd-bulk-st').textContent = text;
      if (cls !== 'working') results[key] = [cls, text];
      if (cls === 'working') li.scrollIntoView({ block: 'nearest' });
    }
    open.addEventListener('click', function () { dlg.hidden = false; draw(); });
    $b('[data-nsd-bulk-close]').addEventListener('click', function () { if (running && !window.confirm('Stop redrafting? Proposals made so far are kept.')) return; stopAsked = true; if (ctrl) ctrl.abort(); dlg.hidden = true; });
    skip.addEventListener('change', draw);
    stopB.addEventListener('click', function () { stopAsked = true; stopB.disabled = true; ptext.textContent = 'Stopping…'; if (ctrl) ctrl.abort(); });
    start.addEventListener('click', function () {
      if (!AI.ready) { SL.toast(AI.why || (KING + ' is not set up.'), true); return; }
      var items = targets();
      if (!items.length) { SL.toast('Nothing to redraft: every status has a custom message. Untick “Skip” to include them.', true); return; }
      running = true; stopAsked = false; results = {}; start.hidden = true; stopB.hidden = false; stopB.disabled = false; saveB.hidden = true; clearB.hidden = true; prog.hidden = false;
      var ins = $b('[data-nsd-bulk-ins]').value, tone = toneOf(dlg), i = 0, okN = 0, failN = 0;
      function next() {
        if (stopAsked || i >= items.length) return finish();
        var s = items[i];
        bar.style.width = Math.round(i / items.length * 100) + '%';
        ptext.textContent = 'Status ' + (i + 1) + ' of ' + items.length + ': ' + KING + ' is writing “' + s.label + '”…';
        row(s.key, 'working', 'Writing…');
        var timedOut = false;
        ctrl = window.AbortController ? new AbortController() : null;
        var timer = ctrl ? setTimeout(function () { timedOut = true; ctrl.abort(); }, PER_STATUS_MS) : null;
        postAbortable({ op: 'ai', key: s.key, channel: 'all', tone: tone, instruction: ins }, ctrl ? ctrl.signal : undefined).then(function (j) {
          if (j.success) {
            var notes = 0;
            Object.keys(j.proposals || {}).forEach(function (ch) { propSet(s.key, ch, Object.assign({ at: Date.now(), tone: tone, instruction: ins }, j.proposals[ch])); notes += (j.proposals[ch].warnings || []).length; });
            okN++; row(s.key, 'ready', 'Proposal ready' + (notes ? ' · ' + notes + ' note' + (notes === 1 ? '' : 's') : ''));
          } else {
            failN++; row(s.key, 'failed', j.message || 'No proposal');
            if (j.code === 'not_configured' || j.code === 'not_allowed') stopAsked = true;
          }
        }).catch(function (err) {
          failN++;
          if (err && err.name === 'AbortError') row(s.key, 'failed', timedOut ? 'No answer after ' + Math.round(PER_STATUS_MS / 1000) + ' seconds: skipped' : 'Stopped');
          else row(s.key, 'failed', (err && err.message) || 'Network error');
        }).finally(function () { clearTimeout(timer); ctrl = null; i++; markProposals(); next(); });
      }
      function finish() {
        running = false; bar.style.width = '100%'; start.hidden = false; start.textContent = 'Run again'; stopB.hidden = true;
        ptext.textContent = okN + ' status' + (okN === 1 ? '' : 'es') + ' redrafted' + (failN ? ', ' + failN + ' failed' : '') + (stopAsked && i < items.length ? ' (stopped)' : '') + '. Open a status to review its proposals, or save them all below.';
        draw();
        // keep the per-row results (ready, failed, stopped) that draw() just reset
        Object.keys(results).forEach(function (k) { var r = results[k]; row(k, r[0], r[0] === 'ready' && hasProp(k) ? 'Proposal waiting for review' : r[1]); });
      }
      next();
    });
    clearB.addEventListener('click', function () {
      if (!window.confirm('Discard every proposal ' + KING + ' made for the statuses?')) return;
      propAll().forEach(function (p) { if (p.key.indexOf('status:') === 0) propDel(p.key, p.ch); });
      markProposals(); draw(); SL.toast('Proposals discarded');
    });
    saveB.addEventListener('click', function () {
      var all = propAll().filter(function (p) { return p.key.indexOf('status:') === 0; });
      if (!all.length) return;
      if (!window.confirm('Save ' + all.length + ' proposals as custom messages? They replace what those statuses send now, on each channel. You can reset any of them to its draft later.')) return;
      running = true; busy(saveB, true); clearB.hidden = true; prog.hidden = false;
      var i = 0, okN = 0, failN = 0;
      function next() {
        if (i >= all.length) {
          running = false; busy(saveB, false);
          SL.toast(okN + ' message' + (okN === 1 ? '' : 's') + ' saved' + (failN ? ', ' + failN + ' not saved' : ''), failN > 0);
          setTimeout(function () { location.reload(); }, 900);
          return;
        }
        var p = all[i], data = propGet(p.key, p.ch);
        bar.style.width = Math.round(i / all.length * 100) + '%';
        ptext.textContent = 'Saving ' + (i + 1) + ' of ' + all.length + '…';
        if (!data) { i++; return next(); }
        SL.post(URL_AJAX, { op: 'save', key: p.key, channel: p.ch, enabled: 1, via_ai: 1, subject: data.subject || '', body: data.body || '', blocks: JSON.stringify(data.blocks || []) })
          .then(function (j) { if (j.success) { okN++; propDel(p.key, p.ch); } else { failN++; } })
          .catch(function () { failN++; })
          .finally(function () { i++; next(); });
      }
      next();
    });
  })();

  markProposals();
  if (!f || !D.key) return;

  // ================= the editor =================
  var canSave = f.getAttribute('data-can-save') === '1';
  var isEmail = D.channel === 'email';
  var blocks = Array.isArray(D.blocks) ? D.blocks.map(function (b) { return Object.assign({}, b); }) : [];
  var dirty = false, lastField = null;
  var $ = function (s, r) { return (r || f).querySelector(s); };
  var undoBtn = $('[data-nsd-undo]');

  function setDirty(v) {
    dirty = v;
    var d = $('[data-nsd-dirty]'); if (d) d.hidden = !v;
    if (undoBtn) undoBtn.hidden = !v;
  }
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  // ---- placeholders (client mirror of cdp_notifyPruneLines + cdp_notifyRender, for previews) ----
  function val(k) {
    if (!Object.prototype.hasOwnProperty.call(D.sample, k)) return '';
    var v = String(D.sample[k] == null ? '' : D.sample[k]);
    if (D.channel === 'sms' && k === 'pickup_code') {
      // mirror of cdp_notifyBuild(): no bold, short form of the code sentence
      v = v.replace(/\*/g, '')
        .replace(/^Pickup code: (\S+?)\. Show this code when you collect your packages?\. Do not share it with anyone else\./m, 'Pickup code: $1. Show it at the counter; do not share it.')
        .replace(/^Pickup code: (\S+?)\. Give this code to our driver when your packages? arrives?\. Do not share it with anyone else\./m, 'Pickup code: $1. Give it to our rider; do not share it.');
    }
    return v;
  }
  function prune(text) {
    var out = [];
    String(text || '').replace(/\r\n/g, '\n').split('\n').forEach(function (l) {
      var m = l.match(/\{([a-z_]+)\}/g);
      if (m && !m.some(function (t) { return val(t.slice(1, -1)).trim() !== ''; })) return;
      out.push(l);
    });
    return out.join('\n').replace(/\n{3,}/g, '\n\n').replace(/^\n+|\n+$/g, '');
  }
  function render(text) { return prune(text).replace(/\{([a-z_]+)\}/g, function (m, k) { return val(k); }); }

  // ---- switch: swiftlane.js flips it; we read the state after its click ----
  var sw = $('[data-nsd-enabled]');
  function enabled() { return sw ? sw.classList.contains('on') : true; }
  if (sw && !sw.hasAttribute('data-static')) sw.addEventListener('click', function () {
    setTimeout(function () {
      setDirty(true);
      var t = $('[data-nsd-modetext]');
      if (t) t.textContent = enabled()
        ? 'Turned on: save to send the message below' + (D.kind === 'comms' ? ' from this automation.' : '.')
        : 'Turned off: save, and nothing is sent on ' + D.channelLabel + ' for this one.';
    }, 0);
  });

  // ---- insert a field where the cursor was ----
  f.addEventListener('focusin', function (e) { if (e.target.matches('input[type="text"]:not([data-nsd-ai-ins]), input[name="subject"], textarea')) lastField = e.target; });
  f.addEventListener('click', function (e) {
    var b = e.target.closest('[data-nsd-var]');
    if (!b || !canSave) return;
    if (b.getAttribute('aria-disabled') === 'true') { SL.toast(b.title, true); return; }
    var token = '{' + b.getAttribute('data-nsd-var') + '}';
    var t = lastField && f.contains(lastField) ? lastField : (isEmail ? $('[name="subject"]') : $('[name="body"]'));
    if (!t || t.readOnly) return;
    var s = t.selectionStart != null ? t.selectionStart : t.value.length, en = t.selectionEnd != null ? t.selectionEnd : s;
    t.value = t.value.slice(0, s) + token + t.value.slice(en);
    t.focus(); t.selectionStart = t.selectionEnd = s + token.length;
    t.dispatchEvent(new Event('input', { bubbles: true }));
  });

  var varsPop = $('[data-nsd-vars-pop]');
  if (varsPop) {
    document.addEventListener('click', function (e) { if (varsPop.open && !varsPop.contains(e.target)) varsPop.open = false; });
    varsPop.addEventListener('keydown', function (e) { if (e.key === 'Escape' && varsPop.open) { varsPop.open = false; varsPop.querySelector('summary').focus(); } });
    // keep the cursor where it was in the field: do not steal focus when a chip is pressed
    varsPop.addEventListener('mousedown', function (e) { if (e.target.closest('[data-nsd-var]')) e.preventDefault(); });
  }
  // grow a text box to its content (no inner scrollbar hiding half a paragraph)
  function grow(t) { if (!t || t.tagName !== 'TEXTAREA') return; t.style.height = 'auto'; t.style.height = (t.scrollHeight + 2) + 'px'; }

  // ================= e-mail builder =================
  var list = $('[data-nsd-blocks]'), frame = $('[data-nsd-frame]'), fwrap = $('[data-nsd-fwrap]'), fin = $('[data-nsd-fin]'), prevTimer = null, width = 'desk', widthAuto = true;

  function fieldsOf(type) { return (D.types[type] && D.types[type].fields) || {}; }
  function typeLabel(type) { return (D.types[type] || {}).label || type; }
  function blockHint(type) {
    return {
      status_card: 'Shows tracking, status, location, carrier tracking, weight and estimated delivery. Empty lines are left out.',
      items: 'The items in the package, as a table. Left out when the package has no items.',
      pickup_code: 'The pickup code. Only shown when the package is ready for pickup or delivery.',
      divider: 'A thin line.', spacer: 'Some empty space.'
    }[type] || '';
  }

  function drawBlocks() {
    if (!list) return;
    list.replaceChildren();
    blocks.forEach(function (b, i) {
      var li = el('li', 'nsd-block');
      li.setAttribute('data-i', i);
      var head = el('div', 'nsd-bh');
      head.appendChild(el('span', 'nsd-bn', (i + 1) + '. ' + typeLabel(b.type)));
      var tools = el('span', 'nsd-bt');
      [['up', 'i-up', 'Move up', i === 0], ['down', 'i-down', 'Move down', i === blocks.length - 1], ['copy', 'i-copy', 'Duplicate', false], ['del', 'i-trash', 'Remove', false]].forEach(function (t) {
        var btn = el('button', 'sl-ib sm'); btn.type = 'button'; btn.setAttribute('data-act', t[0]); btn.setAttribute('aria-label', t[2] + ' block ' + (i + 1) + ' (' + typeLabel(b.type) + ')'); btn.title = t[2];
        btn.disabled = !canSave || t[3];
        var ic = el('i', 'ic ic-sm ' + t[1]); ic.setAttribute('aria-hidden', 'true'); btn.appendChild(ic);
        tools.appendChild(btn);
      });
      head.appendChild(tools);
      li.appendChild(head);
      var fs = fieldsOf(b.type), keys = Object.keys(fs);
      keys.forEach(function (k) {
        var lab = el('label', 'nsd-bf');
        lab.appendChild(el('span', 'nsd-bfl', fs[k]));
        var inp = k === 'text' && (b.type === 'text' || b.type === 'footer') ? el('textarea', 'sl-in nsd-bta') : el('input', 'sl-in');
        if (inp.tagName === 'INPUT') inp.type = 'text';
        inp.value = b[k] || ''; inp.setAttribute('data-k', k); inp.readOnly = !canSave;
        if (inp.tagName === 'TEXTAREA') inp.rows = 3;
        if (k === 'url') inp.placeholder = b.type === 'button' ? '{track_url}' : 'https://';
        lab.appendChild(inp);
        li.appendChild(lab);
      });
      if (!keys.length) li.appendChild(el('p', 'nsd-bhint', blockHint(b.type)));
      list.appendChild(li);
    });
    if (!blocks.length) list.appendChild(el('li', 'nsd-empty', 'No blocks yet. Choose a block above and add it.'));
    list.querySelectorAll('textarea').forEach(grow);
  }

  function setWidthButtons() {
    f.querySelectorAll('[data-nsd-w]').forEach(function (x) { var on = x.getAttribute('data-nsd-w') === width; x.classList.toggle('on', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
  }
  function fitFrame() {
    if (!frame || !fwrap) return;
    // Until the admin picks a width, a narrow preview column shows the phone
    // view at full size instead of a desktop e-mail shrunk to half.
    if (widthAuto) { var want = (fwrap.clientWidth || 640) < 560 ? 'phone' : 'desk'; if (want !== width) { width = want; setWidthButtons(); } }
    var natural = width === 'phone' ? 375 : 640, avail = fwrap.clientWidth || natural, s = Math.min(1, avail / natural), h = 400;
    try { var doc = frame.contentDocument; if (doc && doc.documentElement) h = Math.max(200, doc.documentElement.scrollHeight); } catch (e) {}
    frame.style.width = natural + 'px';
    frame.style.height = h + 'px';
    frame.style.transform = s < 1 ? 'scale(' + s + ')' : '';
    (fin || fwrap).style.height = Math.ceil(h * s) + 'px';
    fwrap.classList.toggle('phone', width === 'phone');
  }
  function preview() {
    if (!frame) return;
    clearTimeout(prevTimer);
    prevTimer = setTimeout(function () {
      SL.post(URL_AJAX, { op: 'preview', key: D.key, channel: 'email', subject: ($('[name="subject"]') || {}).value || '', blocks: JSON.stringify(blocks) })
        .then(function (j) {
          if (!j.success) { var ps0 = $('[data-nsd-psubj]'); if (ps0) ps0.textContent = j.message || 'Preview not available'; return; }
          frame.onload = fitFrame;
          frame.srcdoc = '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0}a{pointer-events:none}</style><body>' + j.html + '</body>';
          var ps = $('[data-nsd-psubj]'); if (ps) ps.textContent = j.subject || '(no subject)';
        }).catch(function () { /* the next edit tries again */ });
    }, 300);
  }

  if (isEmail) {
    drawBlocks();
    preview();
    if (window.ResizeObserver && fwrap) new ResizeObserver(fitFrame).observe(fwrap); else window.addEventListener('resize', fitFrame);
    list.addEventListener('input', function (e) {
      var li = e.target.closest('.nsd-block'); var k = e.target.getAttribute('data-k');
      if (!li || !k) return;
      blocks[+li.getAttribute('data-i')][k] = e.target.value;
      grow(e.target);
      setDirty(true); preview();
    });
    list.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-act]'); if (!btn || !canSave) return;
      var i = +btn.closest('.nsd-block').getAttribute('data-i'), act = btn.getAttribute('data-act'), focusAt = i;
      if (act === 'up' && i > 0) { blocks.splice(i - 1, 0, blocks.splice(i, 1)[0]); focusAt = i - 1; }
      else if (act === 'down' && i < blocks.length - 1) { blocks.splice(i + 1, 0, blocks.splice(i, 1)[0]); focusAt = i + 1; }
      else if (act === 'copy') { blocks.splice(i + 1, 0, Object.assign({}, blocks[i])); focusAt = i + 1; }
      else if (act === 'del') { blocks.splice(i, 1); focusAt = Math.min(i, blocks.length - 1); }
      drawBlocks(); setDirty(true); preview();
      // keep keyboard users where they were
      var again = list.querySelector('.nsd-block[data-i="' + focusAt + '"] [data-act="' + act + '"]:not(:disabled)') || list.querySelector('.nsd-block[data-i="' + focusAt + '"] [data-act]:not(:disabled)');
      if (again) again.focus();
    });
    var addBtn = $('[data-nsd-add]'), addSel = $('[data-nsd-addtype]');
    if (addBtn) addBtn.addEventListener('click', function () {
      var t = addSel.value, b = { type: t };
      Object.keys(fieldsOf(t)).forEach(function (k) { b[k] = k === 'url' && t === 'button' ? '{track_url}' : (t === 'button' && k === 'text' ? 'Track your shipment' : ''); });
      blocks.push(b); drawBlocks(); setDirty(true); preview();
      var last = list.querySelector('.nsd-block:last-child input, .nsd-block:last-child textarea');
      (last || list.lastElementChild).scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      if (last) last.focus();
    });
    var subj = $('[name="subject"]');
    if (subj) subj.addEventListener('input', function () { setDirty(true); preview(); });
    f.querySelectorAll('[data-nsd-w]').forEach(function (b) {
      b.addEventListener('click', function () {
        widthAuto = false; width = b.getAttribute('data-nsd-w'); setWidthButtons(); fitFrame();
      });
    });
  }

  // ================= text channels =================
  var ta = $('[name="body"]'), bubble = $('[data-nsd-bubble]'), count = $('[data-nsd-count]');
  function smsParts(s) {
    var gsm = /^[\n\r @£$¥èéùìòÇØøÅå_ÆæßÉ!"#%&'()*+,\-./0-9:;<=>?¡A-ZÄÖÑÜ§¿a-zäöñüà^{}\\[\]~|€]*$/.test(s);
    var one = gsm ? 160 : 70, many = gsm ? 153 : 67;
    return !s.length ? 0 : (s.length <= one ? 1 : Math.ceil(s.length / many));
  }
  function waFormat(s) {
    var esc = escH(s);
    return esc.replace(/\*([^*\n]+)\*/g, '<b>$1</b>').replace(/(^|\s)_([^_\n]+)_(?=\s|$)/g, '$1<i>$2</i>').replace(/~([^~\n]+)~/g, '<s>$1</s>')
      .replace(/(https?:\/\/[^\s<]+)/g, '<span class="nsd-wa-link">$1</span>').replace(/\n/g, '<br>');
  }
  function drawText() {
    if (!ta) return;
    var out = render(ta.value);
    if (bubble) {
      if (D.channel === 'whatsapp') bubble.innerHTML = waFormat(out) + '<span class="nsd-wa-time">' + new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) + '</span>';
      else bubble.textContent = out || ' ';
    }
    if (count) {
      var n = ta.value.length, filled = out.length;
      if (D.channel === 'sms') {
        var parts = smsParts(out);
        count.innerHTML = '';
        var a = el('span', filled > 160 ? 'nsd-over' : '', filled + ' / 160 characters with sample values · ' + parts + ' SMS part' + (parts === 1 ? '' : 's'));
        count.appendChild(a);
      } else {
        count.textContent = n + ' / ' + D.limit + ' characters' + (D.channel === 'inapp' ? ' (' + filled + ' with sample values)' : '');
      }
    }
  }
  if (ta) { drawText(); grow(ta); ta.addEventListener('input', function () { setDirty(true); drawText(); grow(ta); }); }

  // ================= save, reset, undo, test =================
  function payload(op) {
    return { op: op, key: D.key, channel: D.channel, enabled: enabled() ? 1 : 0,
      subject: ($('[name="subject"]') || {}).value || '', body: ta ? ta.value : '', blocks: JSON.stringify(blocks) };
  }
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!canSave) return;
    var btn = $('[data-nsd-save]'); busy(btn, true);
    SL.post(URL_AJAX, payload('save')).then(function (j) {
      SL.toast(j.message || (j.success ? 'Saved' : 'Not saved'), !j.success);
      if (j.success) { setDirty(false); propDel(D.key, D.channel); setTimeout(function () { location.reload(); }, 500); }
    }).catch(function (err) { SL.toast(err.message || 'Network error. Please try again.', true); })
      .finally(function () { busy(btn, false); });
  });
  var reset = $('[data-nsd-reset]');
  if (reset) reset.addEventListener('click', function () {
    var q = D.kind === 'comms' ? 'Put back the default text for this automation? Your text for this channel is replaced.'
      : (D.kind === 'status' && D.draftsOn ? 'Go back to the message drafted for this status? Your saved ' + D.channelLabel + ' message is removed.'
        : 'Go back to the built-in message for this channel? Your saved message is removed.');
    if (!window.confirm(q)) return;
    busy(reset, true);
    SL.post(URL_AJAX, payload('reset')).then(function (j) {
      SL.toast(j.message || (j.success ? 'Done' : 'Not done'), !j.success);
      if (j.success) { setDirty(false); setTimeout(function () { location.reload(); }, 400); }
    }).catch(function (err) { SL.toast(err.message || 'Network error. Please try again.', true); })
      .finally(function () { busy(reset, false); });
  });
  function loadContent(c) {
    if (isEmail) {
      blocks = (c.blocks || []).map(function (b) { return Object.assign({}, b); });
      var s = $('[name="subject"]'); if (s) s.value = c.subject || '';
      drawBlocks(); preview();
    } else if (ta) { ta.value = c.body || ''; drawText(); grow(ta); }
  }
  if (undoBtn) undoBtn.addEventListener('click', function () {
    if (!D.draft || !window.confirm('Put back the drafted message? Your unsaved changes are lost.')) return;
    loadContent(D.draft); if (sw && !sw.classList.contains('on')) { sw.classList.add('on'); sw.setAttribute('aria-checked', 'true'); }
    setDirty(false); SL.toast('Back to the drafted message');
  });
  var test = $('[data-nsd-test]');
  if (test) test.addEventListener('click', function () {
    busy(test, true);
    SL.post(URL_AJAX, payload('test')).then(function (j) { SL.toast(j.message || (j.success ? 'Test sent' : 'Not sent'), !j.success); })
      .catch(function (err) { SL.toast(err.message || 'Network error. Please try again.', true); })
      .finally(function () { busy(test, false); });
  });

  // ================= rewrite with King =================
  var aiBox = $('[data-nsd-ai]'), propBox = $('[data-nsd-prop]');
  if (!aiBox) return;
  var aiStatus = $('[data-nsd-ai-status]'), aiSetup = $('[data-nsd-ai-setup]'), aiIns = $('[data-nsd-ai-ins]');
  function notReady(msg) {
    if (!aiSetup) return;
    aiSetup.hidden = false;
    var w = aiSetup.querySelector('[data-nsd-ai-why]'); if (w && msg) w.textContent = msg;
    aiSetup.classList.remove('nsd-flash'); void aiSetup.offsetWidth; aiSetup.classList.add('nsd-flash');
    var a = aiSetup.querySelector('a'); if (a) a.focus();
  }
  function current() { return { subject: ($('[name="subject"]') || {}).value || '', body: ta ? ta.value : '', blocks: blocks }; }

  function aiRun(mode, btn) {
    if (!AI.ready) { notReady(AI.why); aiStatus.textContent = ''; return; }
    var tone = toneOf(aiBox), ins = aiIns ? aiIns.value : '', c = current();
    busy(btn, true);
    aiStatus.textContent = KING + ' is writing' + (mode === 'all' ? ' every channel' : ' the ' + D.channelLabel) + '…';
    SL.post(URL_AJAX, { op: 'ai', key: D.key, channel: mode === 'all' ? 'all' : D.channel, tone: tone, instruction: ins,
      subject: c.subject, body: c.body, blocks: JSON.stringify(c.blocks) }).then(function (j) {
      if (!j.success) {
        if (j.code === 'not_configured' || j.code === 'not_allowed') notReady(j.message);
        aiStatus.textContent = j.message || 'No proposal came back.';
        SL.toast(j.message || 'No proposal came back.', true);
        return;
      }
      var chs = Object.keys(j.proposals || {});
      chs.forEach(function (ch) { propSet(D.key, ch, Object.assign({ at: Date.now(), tone: tone, instruction: ins }, j.proposals[ch])); });
      aiStatus.textContent = mode === 'all'
        ? 'Proposals ready for ' + chs.map(function (c2) { return chLabels[c2] || c2; }).join(', ') + '. The other channels show a dot on their tab.'
        : 'Proposal ready: compare it below.';
      markProposals(); showProposal(true);
    }).catch(function (err) { aiStatus.textContent = err.message || 'Network error. Please try again.'; SL.toast(aiStatus.textContent, true); })
      .finally(function () { busy(btn, false); });
  }
  aiBox.querySelectorAll('[data-nsd-ai-go]').forEach(function (b) { b.addEventListener('click', function () { aiRun(b.getAttribute('data-nsd-ai-go'), b); }); });
  if (aiIns) aiIns.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); aiRun('one', aiBox.querySelector('[data-nsd-ai-go="one"]')); } });

  // word diff (LCS) for the side-by-side comparison
  function diff(a, b) {
    var A = String(a).split(/(\s+)/), B = String(b).split(/(\s+)/);
    if (A.length * B.length > 640000) return { a: escH(a), b: escH(b) };
    var n = A.length, m = B.length, L = [], i, j;
    for (i = 0; i <= n; i++) { L[i] = new Uint16Array(m + 1); }
    for (i = n - 1; i >= 0; i--) for (j = m - 1; j >= 0; j--) L[i][j] = A[i] === B[j] ? L[i + 1][j + 1] + 1 : Math.max(L[i + 1][j], L[i][j + 1]);
    var oa = '', ob = ''; i = 0; j = 0;
    var ws = function (t) { return /^\s+$/.test(t); };
    while (i < n && j < m) {
      if (A[i] === B[j]) { oa += escH(A[i]); ob += escH(B[j]); i++; j++; }
      else if (L[i + 1][j] >= L[i][j + 1]) { oa += ws(A[i]) ? escH(A[i]) : '<del>' + escH(A[i]) + '</del>'; i++; }
      else { ob += ws(B[j]) ? escH(B[j]) : '<ins>' + escH(B[j]) + '</ins>'; j++; }
    }
    for (; i < n; i++) oa += ws(A[i]) ? escH(A[i]) : '<del>' + escH(A[i]) + '</del>';
    for (; j < m; j++) ob += ws(B[j]) ? escH(B[j]) : '<ins>' + escH(B[j]) + '</ins>';
    return { a: oa, b: ob };
  }
  function asText(c) {
    if (!isEmail) return c.body || '';
    var out = ['Subject: ' + (c.subject || '')];
    (c.blocks || []).forEach(function (b) {
      var label = typeLabel(b.type);
      if (b.type === 'button') out.push('[' + label + ': ' + (b.text || '') + ' → ' + (b.url || '') + ']');
      else if (b.text != null && b.type !== 'header') out.push(label + ': ' + b.text);
      else if (b.type !== 'header') out.push('[' + label + ']');
    });
    return out.join('\n\n');
  }
  function showProposal(scroll) {
    var p = propGet(D.key, D.channel);
    if (!p || !propBox) { if (propBox) { propBox.hidden = true; propBox.replaceChildren(); } return; }
    var d = diff(asText(current()), asText(p));
    var warn = (p.warnings || []).map(function (w) { return '<li>' + escH(w) + '</li>'; }).join('');
    var meta = '';
    if (D.channel === 'sms' && p.chars != null) meta = '<p class="sm ' + (p.chars > 160 ? 'nsd-over' : 'mut') + '">' + p.chars + ' characters with sample values · ' + (p.parts || 1) + ' SMS part' + ((p.parts || 1) === 1 ? '' : 's') + '</p>';
    var tone = (AI.tones && AI.tones[p.tone]) || '';
    propBox.innerHTML = '<div class="nsd-prop-head"><span class="nsd-ai-t"><i class="ic ic-sm i-spark" aria-hidden="true"></i>' + escH(KING) + '’s proposal · ' + escH(D.channelLabel) + '</span>'
      + '<span class="mut sm">' + (tone && p.tone !== 'keep' ? escH(tone) + ' · ' : '') + (p.instruction ? '“' + escH(p.instruction) + '” · ' : '') + 'not saved</span></div>'
      + (warn ? '<ul class="nsd-prop-warn sm">' + warn + '</ul>' : '')
      + '<div class="nsd-cmp"><div><div class="nsd-cmp-l">Now</div><div class="nsd-cmp-t">' + d.a + '</div></div>'
      + '<div><div class="nsd-cmp-l">Proposed</div><div class="nsd-cmp-t">' + d.b + '</div></div></div>' + meta
      + (isEmail && p.preview && p.preview.html ? '<details class="nsd-prop-mail"><summary class="sm">See the proposed e-mail</summary><iframe class="nsd-prop-frame" sandbox="" title="Proposed e-mail"></iframe></details>' : '')
      + '<div class="row g8 wrap"><button type="button" class="sl-btn" data-p="accept"><i class="ic ic-sm i-check" aria-hidden="true"></i>Accept into the editor</button>'
      + '<button type="button" class="sl-btn ghost" data-p="retry"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Try again</button>'
      + '<button type="button" class="sl-btn ghost" data-p="discard"><i class="ic ic-sm i-x" aria-hidden="true"></i>Discard</button></div>';
    var fr = propBox.querySelector('.nsd-prop-frame');
    if (fr) fr.srcdoc = '<!doctype html><meta charset="utf-8"><body style="margin:0">' + p.preview.html + '</body>';
    propBox.hidden = false;
    if (aiIns && !aiIns.value && p.instruction) aiIns.value = p.instruction;
    if (scroll) propBox.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }
  if (propBox) propBox.addEventListener('click', function (e) {
    var b = e.target.closest('[data-p]'); if (!b) return;
    var act = b.getAttribute('data-p'), p = propGet(D.key, D.channel);
    if (act === 'accept' && p) {
      loadContent(p);
      if (sw && !sw.classList.contains('on')) { sw.classList.add('on'); sw.setAttribute('aria-checked', 'true'); }
      setDirty(true); propDel(D.key, D.channel); markProposals(); showProposal();
      aiStatus.textContent = 'Proposal placed in the editor. Check it, then press Save to send it.';
      SL.toast('Proposal placed in the editor. Press Save to send it.');
      var sv = $('[data-nsd-save]'); if (sv) sv.focus();
    } else if (act === 'retry') {
      aiRun('one', b);
    } else if (act === 'discard') {
      propDel(D.key, D.channel); markProposals(); showProposal(); aiStatus.textContent = 'Proposal discarded.';
    }
  });
  showProposal(false);
})();
