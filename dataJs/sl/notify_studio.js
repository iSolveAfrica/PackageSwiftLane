/* Notification Studio (views/sl/pages/NotifyStudio.php).
 * E-mail: a list of blocks you add, reorder, edit and remove, with a live
 * preview the server compiles (the same code that sends the e-mail).
 * SMS / WhatsApp / in-app: a text box with a phone-style preview.
 * Saves to ajax/sl/notify_studio_ajax.php. */
(function () {
  var f = document.getElementById('nsd-form');
  var dataEl = document.getElementById('nsd-data');
  if (!f || !dataEl) return;
  var D = JSON.parse(dataEl.textContent || '{}');
  var URL_AJAX = 'ajax/sl/notify_studio_ajax.php';
  var canSave = f.getAttribute('data-can-save') === '1';
  var isEmail = D.channel === 'email';
  var blocks = Array.isArray(D.blocks) ? D.blocks.map(function (b) { return Object.assign({}, b); }) : [];
  var dirty = false, lastField = null;
  var $ = function (s, r) { return (r || f).querySelector(s); };
  var el = function (tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };

  function setDirty(v) {
    dirty = v;
    var d = $('[data-nsd-dirty]'); if (d) d.hidden = !v;
  }
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  // ---- placeholders (client mirror of cdp_notifyRender, for text previews) ----
  function render(text) {
    return String(text || '').replace(/\{([a-z_]+)\}/g, function (m, k) { return Object.prototype.hasOwnProperty.call(D.sample, k) ? String(D.sample[k]) : ''; });
  }

  // ---- switch: swiftlane.js flips it; we read the state after its click ----
  var sw = $('[data-nsd-enabled]');
  function enabled() { return sw ? sw.classList.contains('on') : true; }
  if (sw) sw.addEventListener('click', function () {
    setTimeout(function () {
      setDirty(true);
      var t = $('[data-nsd-modetext]');
      if (t) t.textContent = enabled()
        ? 'Turned on: save to send the message below' + (D.kind === 'comms' ? ' from this automation.' : ' instead of the built-in one.')
        : 'Turned off: save, and nothing is sent on ' + D.channelLabel + ' for this one.';
    }, 0);
  });

  // ---- insert a field where the cursor was ----
  f.addEventListener('focusin', function (e) { if (e.target.matches('input[type="text"], input:not([type]), textarea')) lastField = e.target; });
  f.addEventListener('click', function (e) {
    var b = e.target.closest('[data-nsd-var]');
    if (!b || !canSave) return;
    var token = '{' + b.getAttribute('data-nsd-var') + '}';
    var t = lastField && f.contains(lastField) ? lastField : (isEmail ? $('[name="subject"]') : $('[name="body"]'));
    if (!t || t.readOnly) return;
    var s = t.selectionStart != null ? t.selectionStart : t.value.length, en = t.selectionEnd != null ? t.selectionEnd : s;
    t.value = t.value.slice(0, s) + token + t.value.slice(en);
    t.focus(); t.selectionStart = t.selectionEnd = s + token.length;
    t.dispatchEvent(new Event('input', { bubbles: true }));
  });

  // ================= e-mail builder =================
  var list = $('[data-nsd-blocks]'), frame = $('[data-nsd-frame]'), prevTimer = null;

  function fieldsOf(type) { return (D.types[type] && D.types[type].fields) || {}; }
  function blockHint(type) {
    return {
      status_card: 'Shows tracking, carrier tracking, status and estimated delivery.',
      items: 'The items in the package, as a table.',
      pickup_code: 'The pickup code. Only shown when the package is ready for pickup.',
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
      head.appendChild(el('span', 'nsd-bn', (i + 1) + '. ' + ((D.types[b.type] || {}).label || b.type)));
      var tools = el('span', 'nsd-bt');
      [['up', 'i-up', 'Move up', i === 0], ['down', 'i-down', 'Move down', i === blocks.length - 1], ['copy', 'i-copy', 'Duplicate', false], ['del', 'i-trash', 'Remove', false]].forEach(function (t) {
        var btn = el('button', 'sl-ib sm'); btn.type = 'button'; btn.setAttribute('data-act', t[0]); btn.setAttribute('aria-label', t[2] + ' block ' + (i + 1)); btn.title = t[2];
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
        if (k === 'url') inp.placeholder = b.type === 'button' ? '{track_url}' : 'https://';
        lab.appendChild(inp);
        li.appendChild(lab);
      });
      if (!keys.length) li.appendChild(el('p', 'nsd-bhint', blockHint(b.type)));
      list.appendChild(li);
    });
    if (!blocks.length) list.appendChild(el('li', 'nsd-empty', 'No blocks yet. Choose a block above and add it.'));
  }

  function preview() {
    if (!frame) return;
    clearTimeout(prevTimer);
    prevTimer = setTimeout(function () {
      SL.post(URL_AJAX, { op: 'preview', key: D.key, channel: 'email', subject: ($('[name="subject"]') || {}).value || '', blocks: JSON.stringify(blocks) })
        .then(function (j) {
          if (!j.success) return;
          frame.srcdoc = '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="margin:0">' + j.html + '</body>';
          var ps = $('[data-nsd-psubj]'); if (ps) ps.textContent = j.subject || '(no subject)';
        });
    }, 350);
  }

  if (isEmail) {
    drawBlocks();
    preview();
    list.addEventListener('input', function (e) {
      var li = e.target.closest('.nsd-block'); var k = e.target.getAttribute('data-k');
      if (!li || !k) return;
      blocks[+li.getAttribute('data-i')][k] = e.target.value;
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
        f.querySelectorAll('[data-nsd-w]').forEach(function (x) { x.classList.toggle('on', x === b); });
        frame.classList.toggle('phone', b.getAttribute('data-nsd-w') === 'phone');
      });
    });
  }

  // ================= text channels =================
  var ta = $('[name="body"]'), bubble = $('[data-nsd-bubble]'), count = $('[data-nsd-count]');
  function smsParts(s) {
    var gsm = /^[\n\r @£$¥èéùìòÇØøÅå_ÆæßÉ!"#%&'()*+,\-./0-9:;<=>?¡A-ZÄÖÑÜ§¿a-zäöñüà^{}\\[\]~|€]*$/.test(s);
    var one = gsm ? 160 : 70, many = gsm ? 153 : 67;
    return s.length <= one ? 1 : Math.ceil(s.length / many);
  }
  function waFormat(s) {
    var esc = s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return esc.replace(/\*([^*\n]+)\*/g, '<b>$1</b>').replace(/_([^_\n]+)_/g, '<i>$1</i>').replace(/~([^~\n]+)~/g, '<s>$1</s>').replace(/\n/g, '<br>');
  }
  function drawText() {
    if (!ta) return;
    var out = render(ta.value);
    if (bubble) {
      if (D.channel === 'whatsapp') bubble.innerHTML = waFormat(out);
      else bubble.textContent = out;
    }
    if (count) {
      var n = ta.value.length;
      count.textContent = D.channel === 'sms'
        ? n + ' / ' + D.limit + ' characters · about ' + smsParts(out) + ' SMS part' + (smsParts(out) === 1 ? '' : 's') + ' with sample values'
        : n + ' / ' + D.limit + ' characters';
    }
  }
  if (ta) { drawText(); ta.addEventListener('input', function () { setDirty(true); drawText(); }); }

  // ================= save, reset, test =================
  function busy(btn, on) { if (!btn) return; btn.disabled = on; btn.classList.toggle('loading', on); }
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
      if (j.success) { setDirty(false); setTimeout(function () { location.reload(); }, 500); }
    }).catch(function (err) { SL.toast(err.message || 'Network error. Please try again.', true); })
      .finally(function () { busy(btn, false); });
  });
  var reset = $('[data-nsd-reset]');
  if (reset) reset.addEventListener('click', function () {
    var q = D.kind === 'comms' ? 'Put back the default text for this automation? Your text for this channel is replaced.' : 'Go back to the built-in message for this channel? Your saved message is removed.';
    if (!window.confirm(q)) return;
    busy(reset, true);
    SL.post(URL_AJAX, payload('reset')).then(function (j) {
      SL.toast(j.message || (j.success ? 'Done' : 'Not done'), !j.success);
      if (j.success) { setDirty(false); setTimeout(function () { location.reload(); }, 400); }
    }).catch(function (err) { SL.toast(err.message || 'Network error. Please try again.', true); })
      .finally(function () { busy(reset, false); });
  });
  var test = $('[data-nsd-test]');
  if (test) test.addEventListener('click', function () {
    busy(test, true);
    SL.post(URL_AJAX, payload('test')).then(function (j) { SL.toast(j.message || (j.success ? 'Test sent' : 'Not sent'), !j.success); })
      .catch(function (err) { SL.toast(err.message || 'Network error. Please try again.', true); })
      .finally(function () { busy(test, false); });
  });
})();
