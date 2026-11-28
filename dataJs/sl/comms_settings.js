/*
 * Notifications & Messages > Settings (views/sl/pages/CommsSettings.php).
 * The section forms save through swiftlane.js (data-sl-form); this file adds
 * the segmented choices (provider, mailer, test channel) that show and hide
 * their fields, the one-click actions (check connection, SMTP check, mNotify
 * balance, rotate webhook key), the status-owner switch, the test send, the
 * copy button and the section index highlight. Endpoint:
 * ajax/comms/settings_ajax.php.
 */
(function () {
  'use strict';
  var cfg = window.SL_COMMS_SETTINGS || {};
  var ajax = cfg.ajax || 'ajax/comms/settings_ajax.php';
  var root = document.querySelector('.cs-wrap');
  if (!root || !window.SL) return;

  function scopeOf(el) { return el.closest('form, section') || document; }
  function out(scope, name, text, bad) {
    var o = (scope.querySelector('[data-cs-out="' + name + '"]') || document.querySelector('[data-cs-out="' + name + '"]'));
    if (!o) { SL.toast(text, bad); return; }
    o.textContent = text;
    o.classList.toggle('cs-bad', !!bad);
    o.classList.toggle('cs-good', !bad && !!text);
  }

  /* ---- segmented choices: set the hidden input, show the matching fields ---- */
  function applyWhen(scope, name, value) {
    scope.querySelectorAll('[data-cs-when]').forEach(function (el) {
      var w = el.getAttribute('data-cs-when').split('=');
      if (w[0] !== name) return;
      var show = w[1].split(',').indexOf(value) >= 0;
      el.hidden = !show;
    });
  }
  function pick(btn) {
    var seg = btn.closest('[data-cs-seg]'); if (!seg || btn.disabled) return;
    var name = seg.getAttribute('data-cs-seg'), scope = scopeOf(seg);
    seg.querySelectorAll('[role="radio"]').forEach(function (b) {
      var on = b === btn; b.classList.toggle('on', on); b.setAttribute('aria-checked', on ? 'true' : 'false'); b.tabIndex = on ? 0 : -1;
    });
    var inp = scope.querySelector('input[type="hidden"][name="' + name + '"]');
    if (inp) inp.value = btn.getAttribute('data-v');
    applyWhen(scope, name, btn.getAttribute('data-v'));
  }
  document.querySelectorAll('[data-cs-seg]').forEach(function (seg) {
    seg.querySelectorAll('[role="radio"]').forEach(function (b) { b.tabIndex = b.classList.contains('on') ? 0 : -1; });
  });
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cs-seg] [role="radio"]');
    if (b) { e.preventDefault(); pick(b); }
  });
  root.addEventListener('keydown', function (e) {
    var b = e.target.closest('[role="radio"]'); if (!b) return;
    var seg = b.closest('[data-cs-seg], [data-cs-owner]'); if (!seg) return;
    if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].indexOf(e.key) < 0) return;
    var all = Array.prototype.filter.call(seg.querySelectorAll('[role="radio"]'), function (x) { return !x.disabled; });
    var i = all.indexOf(b) + (e.key === 'ArrowRight' || e.key === 'ArrowDown' ? 1 : -1);
    var n = all[(i + all.length) % all.length];
    if (n) { e.preventDefault(); n.focus(); if (n.closest('[data-cs-seg]')) pick(n); }
  });

  /* ---- channel status strip ---- */
  function paintStatus(st) {
    if (!st) return;
    Object.keys(st).forEach(function (k) {
      var card = document.querySelector('[data-cs-st="' + k + '"]'); if (!card) return;
      var s = st[k], cls = s.ok ? 'ok' : (s.warn ? 'warn' : 'off');
      card.classList.remove('ok', 'warn', 'off'); card.classList.add(cls);
      var pill = card.querySelector('[data-cs-state]');
      if (pill) { pill.textContent = s.state; pill.className = 'sl-pill ' + (s.ok ? 'ok' : (s.warn ? 'warn' : 'plain')); }
      var sub = card.querySelector('.cs-sts'); if (sub) sub.textContent = s.provider;
    });
  }
  /* An error on a switch (its hidden input) goes under the switch row, not inside the 36px toggle. */
  function placeSwitchErrors(scope) {
    scope.querySelectorAll('.sl-toggle > .sl-err').forEach(function (err) {
      var sw = err.parentNode, row = sw.closest('label.cs-sw') || sw;
      err.classList.add('cs-swerr'); err.id = err.id || 'cs-err-' + (sw.getAttribute('data-cs-switch') || Math.random().toString(36).slice(2));
      row.insertAdjacentElement('afterend', err);
      sw.classList.add('is-invalid'); sw.setAttribute('aria-invalid', 'true'); sw.setAttribute('aria-describedby', err.id);
    });
    var first = scope.querySelector('.is-invalid:not(input[type="hidden"])');
    if (first && first.classList.contains('sl-toggle')) first.focus();
  }
  document.addEventListener('submit', function (e) {
    var f = e.target; if (!f.closest || !f.closest('.cs-wrap')) return;
    f.querySelectorAll('.sl-toggle.is-invalid').forEach(function (sw) { sw.classList.remove('is-invalid'); sw.removeAttribute('aria-invalid'); sw.removeAttribute('aria-describedby'); });
  }, true);
  document.addEventListener('sl:done', function (e) {
    var j = e.detail || {};
    if (e.target.closest && e.target.closest('.cs-wrap')) placeSwitchErrors(e.target);
    if (j.status) paintStatus(j.status);
    if (j.owner) setOwner(j.owner);
    // A saved secret is never shown again: clear the field and say it is kept.
    var f = e.target.closest && e.target.closest('form.cs-sec');
    if (f && (j.success || j.ok)) {
      f.querySelectorAll('input[type="password"]').forEach(function (p) {
        if (p.value) { p.value = ''; p.placeholder = '•••••••• saved - leave blank to keep'; }
      });
      // Secrets marked "Remove saved" are gone now.
      var cl = f.querySelector('input[name="clear_secrets"]');
      if (cl && cl.value) {
        cl.value.split(',').forEach(function (n) {
          var inp = f.querySelector('[name="' + n + '"]'), b = f.querySelector('[data-cs-clear="' + n + '"]');
          if (inp && !inp.value) inp.placeholder = 'Not set';
          if (b) b.remove();
        });
        cl.value = '';
      }
    }
  });

  /* ---- "Remove saved" next to a saved secret: cleared on the next save ---- */
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cs-clear]'); if (!b) return;
    e.preventDefault();
    var f = b.closest('form'), name = b.getAttribute('data-cs-clear');
    var cl = f && f.querySelector('input[name="clear_secrets"]'); if (!cl) return;
    var list = cl.value ? cl.value.split(',') : [], on = list.indexOf(name) < 0;
    list = list.filter(function (n) { return n !== name; }); if (on) list.push(name);
    cl.value = list.join(',');
    var inp = f.querySelector('[name="' + name + '"]');
    if (inp) {
      if (!inp.hasAttribute('data-ph')) inp.setAttribute('data-ph', inp.placeholder);
      inp.placeholder = on ? 'Removed when you save' : inp.getAttribute('data-ph');
      if (on) inp.value = '';
    }
    b.setAttribute('aria-pressed', on ? 'true' : 'false');
    b.textContent = on ? 'Keep it' : 'Remove saved';
  });

  /* ---- clicking the text of a switch row flips the switch ---- */
  root.addEventListener('click', function (e) {
    var row = e.target.closest('label.cs-sw'); if (!row) return;
    if (e.target.closest('.sl-toggle, a, button, input, select, textarea')) return;
    var sw = row.querySelector('.sl-toggle[role="switch"]'); if (!sw) return;
    e.preventDefault();
    sw.click();
  });

  /* ---- one-click actions ---- */
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cs-do]'); if (!b) return;
    e.preventDefault();
    if (b.getAttribute('aria-busy') === 'true') return;
    var act = b.getAttribute('data-cs-do'), scope = scopeOf(b);
    var msg = b.getAttribute('data-cs-confirm');
    if (msg && !window.confirm(msg)) return;
    var data = { action: act };
    if (act === 'mnotify_balance') { var k = scope.querySelector('[name="mnotify_api_key"]'); if (k && k.value) data.mnotify_api_key = k.value; }
    // The SMTP check tests what is on screen (a blank password means the saved one, for the saved host only).
    if (act === 'check_smtp') ['mailer', 'smtp_host', 'smtp_user', 'smtp_port', 'smtp_secure', 'smtp_password'].forEach(function (n) {
      var f = scope.querySelector('[name="' + n + '"]'); if (f) data[n] = f.value;
    });
    b.setAttribute('aria-busy', 'true'); b.classList.add('loading');
    out(scope, act, 'Working…', false);
    SL.post(ajax, data).then(function (j) {
      var ok = j.success === true;
      out(scope, act, j.message || (ok ? 'Done' : 'Failed'), !ok);
      if (act === 'rotate_key' && ok && j.webhook) {
        var h = document.querySelector('[data-cs-hook]'); if (h) h.value = j.webhook;
        SL.toast(j.message);
      }
    }).catch(function (err) { out(scope, act, err.message || 'Network error', true); })
      .finally(function () { b.removeAttribute('aria-busy'); b.classList.remove('loading'); });
  });

  /* ---- copy the webhook address ---- */
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cs-copy]'); if (!b) return;
    e.preventDefault();
    var inp = document.querySelector('[data-cs-hook]'); if (!inp) return;
    var done = function () { SL.toast('Webhook address copied'); };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(inp.value).then(done, function () { inp.select(); document.execCommand('copy'); done(); });
    else { inp.select(); try { document.execCommand('copy'); done(); } catch (x) { SL.toast('Select the address and copy it.', true); } }
  });

  /* ---- who sends status messages ---- */
  var ownerBox = document.querySelector('[data-cs-owner]');
  var ownerText = {
    comms: 'The Automation Center sends every status message, with the texts and channels of Status messages. The old screens no longer send them (no double messages).',
    legacy: 'The old screens send status messages as they always did. Status messages edited here are used once you hand them over.'
  };
  function setOwner(o) {
    if (!ownerBox) return;
    ownerBox.setAttribute('data-cs-owner', o);
    ownerBox.classList.toggle('on', o === 'comms');
    ownerBox.querySelectorAll('[data-cs-owner-set]').forEach(function (b) {
      var on = b.getAttribute('data-cs-owner-set') === o; b.classList.toggle('on', on); b.setAttribute('aria-checked', on ? 'true' : 'false'); b.tabIndex = on ? 0 : -1;
    });
    var t = ownerBox.querySelector('[data-cs-owner-text]'); if (t) t.textContent = ownerText[o] || '';
  }
  if (ownerBox) {
    setOwner(ownerBox.getAttribute('data-cs-owner'));
    ownerBox.addEventListener('click', function (e) {
      var b = e.target.closest('[data-cs-owner-set]'); if (!b) return;
      e.preventDefault();
      var want = b.getAttribute('data-cs-owner-set'), cur = ownerBox.getAttribute('data-cs-owner');
      if (want === cur || ownerBox.getAttribute('aria-busy') === 'true') return;
      var q = want === 'comms'
        ? 'Hand status messages to the Automation Center? From now on it sends them with the texts of Status messages, and the old screens stop sending them.'
        : 'Give status messages back to the old screens? They send them again with their own templates.';
      if (!window.confirm(q)) return;
      ownerBox.setAttribute('aria-busy', 'true');
      SL.post(ajax, { action: 'set_owner', owner: want }).then(function (j) {
        var ok = j.success === true;
        SL.toast(j.message || (ok ? 'Saved' : 'Not saved'), !ok);
        setOwner(ok ? want : (j.owner || cur));
        if (j.status) paintStatus(j.status);
      }).catch(function (err) { SL.toast(err.message || 'Network error', true); setOwner(cur); })
        .finally(function () { ownerBox.removeAttribute('aria-busy'); });
    });
  }

  /* ---- test send ---- */
  var test = document.querySelector('form[data-cs-test]');
  if (test) {
    test.addEventListener('submit', function (e) {
      e.preventDefault();
      if (test.getAttribute('aria-busy') === 'true') return;
      var btn = test.querySelector('[type="submit"]');
      test.setAttribute('aria-busy', 'true'); if (btn) { btn.classList.add('loading'); btn.disabled = true; }
      test.querySelectorAll('.sl-err').forEach(function (x) { x.remove(); });
      test.querySelectorAll('.is-invalid').forEach(function (x) { x.classList.remove('is-invalid'); x.removeAttribute('aria-invalid'); });
      var fd = new FormData(test); fd.append('action', 'test');
      out(test, 'test', 'Sending…', false);
      SL.post(ajax, fd).then(function (j) {
        var ok = j.success === true;
        out(test, 'test', j.message || (ok ? 'Sent' : 'Not sent'), !ok);
        if (j.errors) Object.keys(j.errors).forEach(function (k) {
          var inp = test.elements.namedItem(k); if (!inp) return;
          inp.classList.add('is-invalid'); inp.setAttribute('aria-invalid', 'true'); inp.focus();
        });
      }).catch(function (err) { out(test, 'test', err.message || 'Network error', true); })
        .finally(function () { test.removeAttribute('aria-busy'); if (btn) { btn.classList.remove('loading'); btn.disabled = false; } });
    });
  }

  /* ---- section index: mark the section in view ---- */
  var links = Array.prototype.slice.call(document.querySelectorAll('.cs-index a'));
  if (links.length && 'IntersectionObserver' in window) {
    var byId = {};
    links.forEach(function (a) { byId[a.getAttribute('href').slice(1)] = a; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        links.forEach(function (a) { a.classList.remove('on'); a.removeAttribute('aria-current'); });
        var a = byId[en.target.id]; if (a) { a.classList.add('on'); a.setAttribute('aria-current', 'true'); }
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    Object.keys(byId).forEach(function (id) { var s = document.getElementById(id); if (s) io.observe(s); });
  }
})();
