/* Swift Lane operations forms (deliver, tracking update, consolidation edit, warehouse delivery).
 * - form[data-ops-form]: posts FormData to its action (legacy endpoints or the page itself),
 *   understands the legacy replies ({success, messages[], errors{}} or {ok, message}) and
 *   redirects to data-ok (":id" is replaced by the id in the reply) or reloads.
 * - Pickup codes: when the server refuses a Picked up / Delivered hand-over because no code
 *   was verified (errors.pickup_code or reply.pickup_code), a dialog sends and verifies the
 *   code through ajax/pickup_code/pickup_code_ajax.php, then the request is sent again.
 * - canvas[data-sig]: signature pad that writes a PNG data URL into the hidden input named
 *   by data-sig (the legacy sig-dataUrl field).
 */
(function () {
  'use strict';
  if (!window.SL) return;
  var PC = 'ajax/pickup_code/pickup_code_ajax.php';

  function okOf(j) { return j && (j.success === true || j.ok === true || j.status === 'success'); }
  function msgOf(j, ok) {
    if (!j) return ok ? 'Saved' : 'Something went wrong';
    if (Array.isArray(j.messages) && j.messages.length) return j.messages.join(' ');
    if (j.message) return j.message;
    if (j.errors && typeof j.errors === 'object') { var v = Object.keys(j.errors).map(function (k) { return Array.isArray(j.errors[k]) ? j.errors[k].join(' ') : j.errors[k]; }); if (v.length) return v.join(' · '); }
    return ok ? 'Saved' : 'Something went wrong';
  }
  // A PHP notice printed before the JSON hides the payload: recover it.
  function recover(j) {
    if (j && j.success !== undefined && typeof j.message === 'string' && j.message.indexOf('{"') >= 0 && !j.errors) {
      try { return JSON.parse(j.message.slice(j.message.indexOf('{"'))); } catch (e) {}
    }
    return j;
  }
  // Like SL.post, but keeps the whole reply so a JSON body after PHP warnings is still read.
  function postRaw(url, fd) {
    var m = document.querySelector('meta[name="csrf-token"]'), pm = document.querySelector('meta[name="csrf-param"]');
    var tok = m ? m.content : '', param = pm ? pm.content : 'csrf_token';
    if (!fd.has(param)) fd.append(param, tok);
    return fetch(url, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': tok, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.text().then(function (t) {
        try { return JSON.parse(t); } catch (e) {}
        var i = t.indexOf('{"'); if (i >= 0) { try { return JSON.parse(t.slice(i)); } catch (e) {} }
        var j = SL.fromHtml(t, r.ok); if (!r.ok) j.success = false; return j;
      }); });
  }
  SL.opsOk = okOf; SL.opsMsg = msgOf;

  /* ---------- pickup code dialog ---------- */
  var dlg;
  function pickupDialog(module, ids, owner, onVerified) {
    if (!dlg) {
      dlg = document.createElement('div');
      dlg.className = 'sl-dialog'; dlg.hidden = true;
      dlg.innerHTML = '<div class="sl-modal" role="dialog" aria-label="Pickup code">' +
        '<div class="row g10"><span class="sl-tile"><i class="ic i-key" aria-hidden="true"></i></span><div class="col grow"><b class="fw5">Pickup code required</b><span class="mut sm" data-pc-sub></span></div></div>' +
        '<div class="sl-banner info" data-pc-state><i class="ic ic-sm i-info" aria-hidden="true"></i><span>Checking…</span></div>' +
        '<label class="sl-field"><span class="sl-lab">Code the customer shows</span><input class="sl-in mono" inputmode="numeric" maxlength="8" autocomplete="off" data-pc-code></label>' +
        '<div class="row g8 wrap"><button type="button" class="sl-btn" data-pc-verify>Verify code</button><button type="button" class="sl-btn ghost" data-pc-send><i class="ic ic-sm i-send" aria-hidden="true"></i>Send a code to the customer</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div></div>';
      document.body.appendChild(dlg);
    }
    var st = dlg.querySelector('[data-pc-state] span'), box = dlg.querySelector('[data-pc-state]');
    var say = function (t, cls) { st.textContent = t; box.className = 'sl-banner ' + (cls || 'info'); };
    var data = function (extra) { var o = { module: module }; ids.forEach(function (id, i) { o['order_ids[' + i + ']'] = id; }); Object.keys(extra).forEach(function (k) { o[k] = extra[k]; }); return o; };
    dlg.querySelector('[data-pc-sub]').textContent = (owner ? owner + ' · ' : '') + 'The parcel is released only after the customer\'s code is verified.';
    dlg.querySelector('[data-pc-code]').value = '';
    dlg.hidden = false;
    SL.post(PC, data({ action: 'state' })).then(function (r) {
      if (!r.success) { say(r.message || 'Could not read the code state', 'bad'); return; }
      if (r.verified) { dlg.hidden = true; onVerified(); return; }
      say(r.live ? 'A code was sent' + (r.live.sent_at ? ' at ' + r.live.sent_at : '') + '. Ask the customer for it.' : 'No code has been sent yet. Send one, then enter it.');
    });
    dlg.querySelector('[data-pc-send]').onclick = function (e) {
      var b = e.currentTarget; b.disabled = true;
      SL.post(PC, data({ action: 'generate', context: 'handover' })).then(function (r) { say(r.message || (r.success ? 'Code sent' : 'Not sent'), r.success ? 'ok' : 'bad'); }).finally(function () { b.disabled = false; });
    };
    dlg.querySelector('[data-pc-verify]').onclick = function (e) {
      var code = dlg.querySelector('[data-pc-code]').value.trim();
      if (!code) { say('Enter the code first', 'bad'); return; }
      var b = e.currentTarget; b.disabled = true;
      SL.post(PC, data({ action: 'verify', code: code })).then(function (r) {
        if (!r.success) { say(r.message || 'Wrong code', 'bad'); return; }
        dlg.hidden = true; SL.toast('Code verified'); onVerified();
      }).finally(function () { b.disabled = false; });
    };
  }
  SL.pickupCode = pickupDialog;

  /* ---------- signature pads ---------- */
  document.querySelectorAll('canvas[data-sig]').forEach(function (cv) {
    var form = cv.closest('form'), out = form && form.querySelector('[name="' + cv.getAttribute('data-sig') + '"]');
    var ctx = cv.getContext('2d'), drawing = false, dirty = false;
    function fit() { var r = cv.getBoundingClientRect(); if (!r.width) return; cv.width = r.width * (window.devicePixelRatio || 1); cv.height = r.height * (window.devicePixelRatio || 1); ctx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1); ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.strokeStyle = '#1E1D1A'; }
    fit();
    function pt(e) { var r = cv.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; }
    cv.addEventListener('pointerdown', function (e) { drawing = true; cv.setPointerCapture(e.pointerId); var p = pt(e); ctx.beginPath(); ctx.moveTo(p[0], p[1]); });
    cv.addEventListener('pointermove', function (e) { if (!drawing) return; var p = pt(e); ctx.lineTo(p[0], p[1]); ctx.stroke(); dirty = true; });
    cv.addEventListener('pointerup', function () { drawing = false; if (out && dirty) out.value = cv.toDataURL('image/png'); });
    var clr = form && form.querySelector('[data-sig-clear]');
    if (clr) clr.addEventListener('click', function () { ctx.clearRect(0, 0, cv.width, cv.height); dirty = false; if (out) out.value = ''; });
  });

  /* ---------- file name hints ---------- */
  document.addEventListener('change', function (e) {
    var i = e.target; if (!i.matches || !i.matches('input[type="file"][data-ops-file]')) return;
    var t = document.querySelector(i.getAttribute('data-ops-file')); if (!t) return;
    t.textContent = i.files.length ? Array.prototype.map.call(i.files, function (f) { return f.name; }).join(', ') : t.getAttribute('data-empty') || '';
  });

  /* ---------- forms ---------- */
  function send(f) {
    var btns = f.querySelectorAll('[type="submit"]');
    btns.forEach(function (b) { b.disabled = true; b.classList.add('loading'); });
    f.querySelectorAll('.sl-err').forEach(function (x) { x.remove(); });
    f.querySelectorAll('.is-invalid').forEach(function (x) { x.classList.remove('is-invalid'); });
    if (typeof f.slBefore === 'function') f.slBefore(f);
    postRaw(f.getAttribute('action') || location.href, new FormData(f)).then(function (j) {
      var ok = okOf(j);
      if (!ok && f.getAttribute('data-pickup-module') && ((j.errors && j.errors.pickup_code) || j.pickup_code)) {
        var ids = (f.getAttribute('data-pickup-ids') || '').split(',').filter(Boolean);
        SL.toast(msgOf(j, false), true);
        pickupDialog(f.getAttribute('data-pickup-module'), ids, f.getAttribute('data-pickup-owner') || '', function () { send(f); });
        return;
      }
      if (j.errors && typeof j.errors === 'object') {
        Object.keys(j.errors).forEach(function (k) {
          var inp = f.querySelector('[name="' + k + '"]'); if (!inp) return;
          (inp.closest('.sl-sel') || inp).classList.add('is-invalid');
          var s = document.createElement('span'); s.className = 'sl-err'; s.textContent = Array.isArray(j.errors[k]) ? j.errors[k].join(' ') : j.errors[k];
          (inp.closest('.sl-field') || inp.parentNode).appendChild(s);
        });
      }
      SL.toast(msgOf(j, ok), !ok);
      if (!ok) return;
      var to = j.redirect || f.getAttribute('data-ok');
      var id = j.shipment_id || j.id || j.order_id || j.package_id || '';
      setTimeout(function () { if (to) location.href = to.replace(':id', encodeURIComponent(id)); else location.reload(); }, 500);
    }).catch(function () { SL.toast('Network error. Please try again.', true); })
      .finally(function () { btns.forEach(function (b) { b.disabled = false; b.classList.remove('loading'); }); });
  }
  SL.opsSend = send;
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches('form[data-ops-form]')) return;
    e.preventDefault();
    var need = f.querySelector('[data-sig-required]');
    if (need && !f.querySelector('[name="' + need.getAttribute('data-sig') + '"]').value) { SL.toast('Ask the person to sign first', true); return; }
    send(f);
  });
})();
