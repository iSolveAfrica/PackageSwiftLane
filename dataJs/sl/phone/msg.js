/* Phone screens of messages and automation (views/sl/phone/*). Every write goes to the endpoint the web page uses:
 * notify_studio_ajax (studio), templates_ajax (test), compose_ajax (compose), push_notifications_ajax (push),
 * comms_ajax (inbox thread / reply / thread_update), newsletter_ajax (email designs). Uses SL.post / SL.toast (swiftlane.js). */
(function () {
  'use strict';
  var SL = window.SL || {};
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function toast(m, bad) { if (SL.toast) SL.toast(m, bad); }
  function okOf(j) { return !!j && (j.success === true || j.ok === true || j.status === 'success'); }
  function msgOf(j, d) { return (j && (j.message || j.error || (j.errors && (Array.isArray(j.errors) ? j.errors : Object.values(j.errors)).join(' ')))) || d; }
  function json(id) { var el = document.getElementById(id); try { return el ? JSON.parse(el.textContent) : {}; } catch (e) { return {}; } }
  function formData(f) { var o = {}; new FormData(f).forEach(function (v, k) { o[k] = v; }); return o; }
  function busy(b, on) { if (!b) return; b.disabled = on; b.classList.toggle('loading', on); if (on) b.setAttribute('aria-busy', 'true'); else b.removeAttribute('aria-busy'); }

  /* ---------- variable chips insert into the message box ---------- */
  document.addEventListener('click', function (e) {
    var v = e.target.closest('[data-pm-var]'); if (!v) return;
    var scope = v.closest('form') || document, box = $('[data-pm-body]', scope) || $('[data-pm-body]');
    if (!box || box.readOnly) return;
    var s = box.selectionStart != null ? box.selectionStart : box.value.length, t = v.getAttribute('data-pm-var');
    box.value = box.value.slice(0, s) + t + box.value.slice(box.selectionEnd != null ? box.selectionEnd : s);
    box.focus(); try { box.setSelectionRange(s + t.length, s + t.length); } catch (err) {}
    box.dispatchEvent(new Event('input', { bubbles: true }));
  });

  /* ---------- "Send me a test": the form's fields plus data-p-* (templates, notification center) ---------- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-pm-test]'); if (!b) return;
    var f = b.closest('form'), data = f ? formData(f) : {};
    Array.prototype.forEach.call(b.attributes, function (a) { if (a.name.indexOf('data-p-') === 0) data[a.name.slice(7)] = a.value; });
    busy(b, true);
    SL.post(b.getAttribute('data-pm-test'), data).then(function (j) { toast(msgOf(j, okOf(j) ? 'Test sent' : 'Not sent'), !okOf(j)); })
      .catch(function (err) { toast(err.message || 'Network error', true); }).finally(function () { busy(b, false); });
  });

  /* ---------- live preview of a template ([USERNAME] style fields) ---------- */
  var tplBody = $('[data-pm-fill="tpl"]');
  if (tplBody) {
    var S = json('pm-sample'), pv = $('[data-pm-preview]'), cnt = $('[data-pm-count]');
    var fill = function (t) { return t.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').replace(/\[USERNAME\]|\[NAME\]/g, S.name || '').replace(/\[FIRST_NAME\]/g, S.first || '').replace(/\[SITE_NAME\]/g, S.site || '').replace(/\[TRACKING_NUMBER\]|\[TRACK\]/g, 'SL-100480').replace(/\[STATUS\]/g, 'Ready for pickup').replace(/\[LOCKER\]/g, S.locker || '').replace(/\[URL_SHIP\]|\[URL\]|\[LINK\]/g, location.origin); };
    tplBody.addEventListener('input', function () {
      if (pv) pv.textContent = fill(tplBody.value);
      var n = tplBody.value.length;
      if (cnt) cnt.textContent = S.sms ? n + ' characters · ' + Math.max(1, Math.ceil(n / 153)) + ' SMS part' + (n > 153 ? 's' : '') : n + ' characters';
    });
  }

  /* ---------- notification studio: save / test ---------- */
  var ns = $('form[data-pm-studio]');
  if (ns) {
    var url = ns.getAttribute('data-pm-studio'), SS = json('pm-sample'), nsBody = $('[data-pm-body]', ns), nsPv = $('[data-pm-preview]');
    var sfill = function (t) { return String(t).replace(/\{([a-z0-9_]+)\}/gi, function (m, k) { return SS[k] != null ? SS[k] : m; }); };
    if (nsBody && nsPv) nsBody.addEventListener('input', function () { nsPv.textContent = sfill(nsBody.value); });
    var nsPost = function (op, btn) {
      var d = formData(ns); d.op = op; busy(btn, true);
      return SL.post(url, d).then(function (j) {
        toast(msgOf(j, okOf(j) ? (op === 'test' ? 'Test sent' : 'Saved') : 'Not saved'), !okOf(j));
        if (okOf(j) && op === 'save') setTimeout(function () { location.reload(); }, 500);
      }).catch(function (err) { toast(err.message || 'Network error', true); }).finally(function () { busy(btn, false); });
    };
    ns.addEventListener('submit', function (e) { e.preventDefault(); nsPost('save', e.submitter || $('[type="submit"]', ns)); });
    var nsT = $('[data-pm-ns-test]', ns); if (nsT) nsT.addEventListener('click', function () { nsPost('test', nsT); });
  }

  /* ---------- WhatsApp inbox ---------- */
  // Web links carry the conversation in the hash (#f=open&c=12): open it as ?c=12 on the phone.
  if ($('[data-pm-inbox]')) {
    var hm = String(location.hash).match(/[#&]c=(\d+)/);
    if (hm) location.replace(location.pathname + '?c=' + hm[1]);
  }
  var chat = $('[data-pm-thread]');
  if (chat) {
    var tid = chat.getAttribute('data-pm-thread'), AJ = chat.getAttribute('data-pm-ajax');
    chat.scrollTop = chat.scrollHeight;
    if (SL.get) SL.get(AJ, { action: 'thread', id: tid }).catch(function () {}); // marks the conversation read, as the web inbox does
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-pm-op]'); if (!b) return;
      var q = b.getAttribute('data-pm-confirm'); if (q && !window.confirm(q)) return;
      busy(b, true);
      SL.post(AJ + '?action=thread_update', { action: 'thread_update', id: tid, op: b.getAttribute('data-pm-op') }).then(function (j) {
        if (okOf(j)) location.reload(); else { toast(msgOf(j, 'Not saved'), true); busy(b, false); }
      }).catch(function (err) { toast(err.message || 'Network error', true); busy(b, false); });
    });
    var rf = $('form[data-pm-reply]');
    if (rf) {
      var ta = $('textarea', rf), err = document.getElementById('pm-reply-err'), sendB = $('.mx-send', rf);
      ta.addEventListener('input', function () { ta.style.height = 'auto'; ta.style.height = Math.min(120, ta.scrollHeight) + 'px'; });
      rf.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = ta.value; if (!text.trim()) { ta.focus(); return; }
        busy(sendB, true); ta.readOnly = true; if (err) err.hidden = true;
        SL.post(AJ + '?action=reply', { action: 'reply', id: tid, body: text }).then(function (j) {
          if (okOf(j)) { ta.value = ''; toast('Sent'); location.reload(); return; }
          if (err) { err.hidden = false; err.textContent = (j.error || 'Not sent.') + ' Your text is still in the box.'; }
        }).catch(function (x) { if (err) { err.hidden = false; err.textContent = 'Not sent: ' + (x.message || 'the request failed') + '. Your text is still in the box.'; } })
          .finally(function () { busy(sendB, false); ta.readOnly = false; });
      });
    }
  }

  /* ---------- compose message ---------- */
  var cm = $('form[data-pm-compose]');
  if (cm) {
    var CU = cm.getAttribute('data-pm-compose'), CD = json('pm-compose-data'), chIn = document.getElementById('pm-cm-ch');
    var tplSel = $('[data-pm-tpl]', cm), msg = $('[name="message"]', cm), subj = $('[name="subject"]', cm), cpv = $('[data-pm-preview]', cm);
    var LBL = { whatsapp: 'WhatsApp messages', sms: 'SMS', email: 'e-mails' };
    var cfill = function (t) { var s = CD.sample || {}; return t.replace(/\[FIRST_NAME\]/g, s.first || '').replace(/\[USERNAME\]|\[NAME\]/g, s.name || '').replace(/\[SITE_NAME\]/g, CD.site || '').replace(/\[URL\]/g, location.origin); };
    var cprev = function () { cpv.textContent = (chIn.value !== 'sms' && subj.value ? cfill(subj.value) + '\n' : '') + cfill(msg.value); };
    var ctpls = function () {
      var ch = chIn.value, list = (CD.tpl || {})[ch] || [];
      tplSel.innerHTML = '<option value="">Write my own</option>';
      list.forEach(function (t, i) { var o = document.createElement('option'); o.value = i; o.textContent = t.name; tplSel.appendChild(o); });
      $('[data-pm-subj]', cm).hidden = ch === 'sms';
      var h = (CD.health || {})[ch], note = $('[data-pm-chnote]');
      if (note) { note.textContent = h && !h.ok ? 'Channel status: ' + h.state + '. Messages will be logged as failed or skipped.' : ''; note.hidden = !note.textContent; }
    };
    var cdata = function (op) { var d = formData(cm); d.op = op; return d; };
    var et, cest = function () {
      clearTimeout(et);
      et = setTimeout(function () {
        SL.post(CU, cdata('estimate')).then(function (j) {
          if (!okOf(j)) return;
          $('[data-pm-send]', cm).textContent = 'Send to ' + j.reach + ' ' + (j.reach === 1 ? 'person' : 'people');
          $('[data-pm-costl]', cm).textContent = j.reach + ' ' + LBL[chIn.value] + ' × ' + (j.currency || '') + ' ' + j.unit;
          $('[data-pm-cost]', cm).textContent = (j.currency || '') + ' ' + Number(j.cost || 0).toFixed(2);
        });
      }, 250);
    };
    $$('[data-pm-ch]', cm).forEach(function (b) {
      b.addEventListener('click', function () {
        chIn.value = b.getAttribute('data-pm-ch');
        $$('[data-pm-ch]', cm).forEach(function (x) { var on = x === b; x.classList.toggle('on', on); x.setAttribute('aria-pressed', String(on)); });
        ctpls(); cprev(); cest();
      });
    });
    tplSel.addEventListener('change', function () { var t = ((CD.tpl || {})[chIn.value] || [])[tplSel.value]; if (!t) return; msg.value = t.body; if (t.subject) subj.value = t.subject; cprev(); });
    [msg, subj].forEach(function (x) { x.addEventListener('input', cprev); });
    $('[name="segment"]', cm).addEventListener('change', cest);
    $('[name="list"]', cm).addEventListener('input', cest);
    $('[data-pm-cm-test]', cm).addEventListener('click', function () {
      var b = this; busy(b, true);
      SL.post(CU, cdata('test')).then(function (j) { toast(msgOf(j, okOf(j) ? 'Test sent' : 'Not sent'), !okOf(j)); }).finally(function () { busy(b, false); });
    });
    cm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!window.confirm('Send this message now?')) return;
      var btn = $('[data-pm-send]', cm), prog = $('[data-pm-prog]', cm), tot = { sent: 0, failed: 0, skipped: 0 };
      busy(btn, true); prog.hidden = false; prog.textContent = 'Sending…';
      (function step(after, batch) {
        var d = cdata('send'); d.after_id = after; d.batch_id = batch || '';
        SL.post(CU, d).then(function (j) {
          if (!okOf(j)) { toast(msgOf(j, 'Not sent'), true); busy(btn, false); return; }
          tot.sent += j.sent; tot.failed += j.failed; tot.skipped += j.skipped;
          var n = tot.sent + tot.failed + tot.skipped;
          prog.textContent = n + ' / ' + j.total + ' · ' + tot.sent + ' sent · ' + tot.failed + ' failed · ' + tot.skipped + ' skipped';
          if (j.done) { toast('Finished: ' + tot.sent + ' sent'); busy(btn, false); } else step(j.after_id, j.batch_id);
        }).catch(function () { toast('Network error', true); busy(btn, false); });
      })(0, '');
    });
    ctpls(); cprev(); cest();
  }

  /* ---------- push notifications ---------- */
  var ps = $('form[data-pm-push]');
  if (ps) {
    var PU = ps.getAttribute('data-pm-push'), R = (json('pm-push-data').rates) || {}, typeIn = $('[data-pm-type]', ps), uid = $('[data-pm-uid]', ps);
    var pmsg = $('[name="message"]', ps), psub = $('[name="subject"]', ps);
    var chans = function () { return $$('[data-pm-pch]', ps).filter(function (b) { return b.getAttribute('aria-pressed') === 'true'; }).map(function (b) { return b.getAttribute('data-pm-pch'); }); };
    var pbase = function () { return { notification_type: typeIn.value, user_id: uid.value, consolidation_id: $('[name="consolidation_id"]', ps).value, channels: chans().join(','), subject: psub.value, message: pmsg.value, media_file: '' }; };
    var pview = function () {
      $('[data-pm-pv-s]', ps).textContent = psub.value || 'Title';
      $('[data-pm-preview]', ps).textContent = pmsg.value || 'Your message appears here.';
      $$('[data-pm-show]', ps).forEach(function (x) { x.hidden = x.getAttribute('data-pm-show') !== typeIn.value; });
      if (typeIn.value !== 'single_user') $('[data-pm-sug]', ps).innerHTML = '';
    };
    var pet, pest = function () {
      clearTimeout(pet);
      pet = setTimeout(function () {
        var p = pbase(); p.action = 'estimate';
        SL.post(PU, p).then(function (j) {
          var a = j.audience || { total: 0, with_phone: 0, with_email: 0 }, c = chans(), cost = 0;
          var parts = R.sms_per_part ? Math.max(1, Math.ceil(pmsg.value.length / 153)) : 1;
          if (c.indexOf('whatsapp') >= 0) cost += a.with_phone * R.whatsapp;
          if (c.indexOf('sms') >= 0) cost += a.with_phone * R.sms * parts;
          if (c.indexOf('email') >= 0) cost += a.with_email * R.email;
          $('[data-pm-reach]', ps).textContent = a.total + ' · ' + a.with_phone + ' with a phone · ' + a.with_email + ' with an e-mail';
          $('[data-pm-cost]', ps).textContent = R.available ? 'About ' + R.currency + ' ' + cost.toFixed(2) : 'Set unit prices in Message costs';
          $('[data-pm-send]', ps).textContent = 'Send push to ' + a.total;
        });
      }, 250);
    };
    $$('[data-pm-pch]', ps).forEach(function (b) { b.addEventListener('click', function () { b.setAttribute('aria-pressed', String(b.getAttribute('aria-pressed') !== 'true')); pest(); }); });
    $('[data-pm-aud]', ps).addEventListener('change', function () { typeIn.value = this.value; pview(); pest(); });
    $('[name="consolidation_id"]', ps).addEventListener('change', pest);
    [pmsg, psub].forEach(function (x) { x.addEventListener('input', function () { pview(); if (x === pmsg) pest(); }); });
    var pq = $('[data-pm-q]', ps), sug = $('[data-pm-sug]', ps), qt;
    pq.addEventListener('input', function () {
      var q = pq.value; uid.value = 0; clearTimeout(qt);
      qt = setTimeout(function () {
        SL.post('ajax/sl/compose_ajax.php', { op: 'search', q: q }).then(function (j) {
          sug.innerHTML = '';
          (j.items || []).forEach(function (it) {
            var b = document.createElement('button'); b.type = 'button'; b.textContent = it.name;
            var s = document.createElement('small'); s.textContent = it.sub || ''; b.appendChild(s);
            b.addEventListener('click', function () { uid.value = it.id; pq.value = it.name; sug.innerHTML = ''; pest(); });
            sug.appendChild(b);
          });
        });
      }, 200);
    });
    ps.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!chans().length) { toast('Pick at least one channel', true); return; }
      if (!window.confirm('Send this push now?')) return;
      var btn = $('[data-pm-send]', ps), prog = $('[data-pm-prog]', ps), t = { s: 0, k: 0, f: 0, n: 0 };
      busy(btn, true); prog.hidden = false; prog.textContent = 'Sending…';
      (function step(after, batch) {
        var p = pbase(); p.after_id = after; p.batch_id = batch || '';
        SL.post(PU, p).then(function (j) {
          if (!okOf(j)) { toast(msgOf(j, 'Not sent'), true); busy(btn, false); return; }
          var s = j.summary;
          ['whatsapp', 'email', 'sms'].forEach(function (c) { t.s += s[c].sent; t.k += s[c].skipped; t.f += s[c].failed; });
          t.n += s.recipients;
          prog.textContent = t.n + ' / ' + s.total + ' recipients · ' + t.s + ' sent · ' + t.k + ' skipped · ' + t.f + ' failed';
          if (s.done) { busy(btn, false); toast('Push finished: ' + t.s + ' delivered, ' + t.f + ' failed'); } else step(s.next_after_id, s.batch_id);
        }).catch(function () { toast('Network error', true); busy(btn, false); });
      })(0, '');
    });
    pview(); pest();
  }

  /* ---------- email builder: save the name / subject, send a test of the saved design ---------- */
  var nl = $('form[data-pm-nl]');
  if (nl) {
    var NU = nl.getAttribute('data-pm-nl');
    $$('[data-pm-nl-act]').forEach(function (b) {
      b.addEventListener('click', function () {
        var act = b.getAttribute('data-pm-nl-act'), fd = new FormData(nl);
        fd.set('action', act);
        if (act === 'test' && !String(fd.get('emails') || '').trim()) { toast('Add your e-mail under Settings first', true); return; }
        busy(b, true);
        SL.post(NU, fd).then(function (j) {
          var ok = okOf(j);
          toast(ok ? (act === 'test' ? 'Test sent' + (j.lines && j.lines.length ? ': ' + j.lines.join(', ') : '') : 'Saved') : msgOf(j, 'Not done'), !ok);
          if (ok && act === 'save') setTimeout(function () { location.reload(); }, 500);
        }).catch(function (err) { toast(err.message || 'Network error', true); }).finally(function () { busy(b, false); });
      });
    });
  }
})();
