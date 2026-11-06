/* Swift Lane auth pages: legacy JSON form bridge, OTP boxes, resend timer, password meter, sign-up helpers. */
(function () {
  'use strict';
  var SL = window.SL || {};

  /* ---- forms that post to the legacy endpoints (messages/errors strings, status/redirect) ---- */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches('form[data-sl-legacy-form]')) return;
    e.preventDefault();
    var btn = f.querySelector('[type="submit"]');
    if (btn) { btn.classList.add('loading'); btn.disabled = true; }
    var box = f.querySelector('[data-sl-msg]');
    function say(msg, bad) {
      if (box) { box.hidden = false; box.className = 'sl-banner ' + (bad ? 'bad' : 'ok'); box.textContent = msg; }
      SL.toast(msg, bad);
    }
    SL.post(f.getAttribute('action'), new FormData(f)).then(function (j) {
      var ok = j.success === true || j.status === 'success';
      var msg = j.message || j.messages || (typeof j.errors === 'string' ? j.errors : '') || (ok ? 'Done' : 'Something went wrong');
      say(msg, !ok);
      if (ok && j.redirect) setTimeout(function () { location.href = j.redirect; }, 400);
      else if (ok && f.hasAttribute('data-sl-reload')) setTimeout(function () { location.reload(); }, 400);
    }).catch(function () { say('Network error. Please try again.', true); })
      .finally(function () { if (btn) { btn.classList.remove('loading'); btn.disabled = false; } });
  });

  /* ---- show / hide password ---- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-sl-peek]');
    if (!b) return;
    e.preventDefault();
    var inp = document.querySelector(b.getAttribute('data-sl-peek'));
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
    b.setAttribute('aria-label', inp.type === 'password' ? 'Show password' : 'Hide password');
  });

  /* ---- OTP boxes: six visual cells backed by one hidden/real input ---- */
  document.querySelectorAll('[data-sl-otp]').forEach(function (wrap) {
    var input = document.querySelector(wrap.getAttribute('data-sl-otp'));
    var cells = wrap.querySelectorAll('span');
    if (!input) return;
    function paint() {
      var v = (input.value || '').replace(/\D/g, '').slice(0, 6);
      input.value = v;
      cells.forEach(function (c, i) {
        c.textContent = v[i] || '';
        var cur = i === Math.min(v.length, 5);
        c.style.borderColor = cur ? 'var(--ink)' : '';
        c.style.boxShadow = cur ? '0 0 0 1px var(--ink)' : '';
      });
    }
    input.addEventListener('input', paint);
    wrap.addEventListener('click', function () { input.focus(); });
    input.addEventListener('paste', function (e) {
      var t = (e.clipboardData || window.clipboardData).getData('text');
      if (t) { e.preventDefault(); input.value = t.replace(/\D/g, '').slice(0, 6); paint(); }
    });
    paint();
    setTimeout(function () { input.focus(); }, 100);
  });

  /* ---- resend countdown (data-sl-countdown="<unix end>") ---- */
  document.querySelectorAll('[data-sl-countdown]').forEach(function (el) {
    var end = parseInt(el.getAttribute('data-sl-countdown'), 10) || 0;
    var btn = document.querySelector(el.getAttribute('data-sl-countdown-btn') || '#resendBtn');
    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function tick() {
      var r = end - Math.floor(Date.now() / 1000);
      if (r <= 0) { el.hidden = true; if (btn) btn.disabled = false; return; }
      el.hidden = false; el.textContent = 'in ' + Math.floor(r / 60) + ':' + pad(r % 60);
      if (btn) btn.disabled = true;
      setTimeout(tick, 500);
    }
    tick();
  });

  /* ---- password strength meter + rules + match ---- */
  function strength(p) {
    var s = 0;
    if (p.length >= 8) s++;
    if (/[0-9]/.test(p)) s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    if (/[A-Z]/.test(p) && /[a-z]/.test(p)) s++;
    if (p.length >= 12) s++;
    return Math.min(4, s);
  }
  document.querySelectorAll('[data-sl-pw]').forEach(function (inp) {
    var meter = document.querySelector(inp.getAttribute('data-sl-pw')), msg = document.querySelector(inp.getAttribute('data-sl-pw-msg'));
    var confirm = document.querySelector(inp.getAttribute('data-sl-pw-confirm')), match = document.querySelector(inp.getAttribute('data-sl-pw-match'));
    var rules = document.querySelectorAll('[data-sl-rule]'), submit = document.querySelector(inp.getAttribute('data-sl-pw-submit'));
    var labels = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'], cls = ['', 'weak', 'fair', 'good', 'strong'];
    function run() {
      var p = inp.value, s = p.length ? strength(p) : 0;
      if (meter) { meter.className = meter.className.replace(/\b(weak|fair|good|strong)\b/g, '').trim() + (s ? ' ' + cls[s] : ''); }
      if (msg) { msg.textContent = p.length ? labels[s] : ''; msg.className = 'sm ' + (s >= 3 ? 'ok' : s >= 1 ? 'mut' : 'bad'); }
      var okAll = true;
      rules.forEach(function (r) {
        var ok = false, k = r.getAttribute('data-sl-rule');
        if (k === 'len') ok = p.length >= 8;
        if (k === 'num') ok = /[0-9]/.test(p);
        if (k === 'sym') ok = /[^A-Za-z0-9]/.test(p);
        if (k === 'case') ok = /[A-Z]/.test(p) && /[a-z]/.test(p);
        r.classList.toggle('ok', ok);
        var ic = r.querySelector('i'); if (ic) ic.className = 'ic ic-sm ' + (ok ? 'i-check' : 'i-x');
        if (!ok) okAll = false;
      });
      var same = confirm ? (confirm.value !== '' && confirm.value === p) : true;
      if (match && confirm) { match.textContent = confirm.value === '' ? '' : (same ? 'Passwords match' : 'Passwords do not match'); match.className = 'sm ' + (same ? 'ok' : 'bad'); }
      if (submit) submit.style.opacity = okAll && same && p.length >= 8 ? '1' : '.6';
    }
    inp.addEventListener('input', run);
    if (confirm) confirm.addEventListener('input', run);
    run();
  });

  /* ---- sign-up: cascading country → state → city, phone composition, ID type segment ---- */
  var country = document.getElementById('su-country'), state = document.getElementById('su-state'), city = document.getElementById('su-city');
  function fill(sel, url, placeholder) {
    sel.innerHTML = '<option value="">' + placeholder + '</option>';
    sel.disabled = true;
    if (!url) return;
    fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (list) {
      (list || []).forEach(function (o) { var op = document.createElement('option'); op.value = o.id; op.textContent = o.text; sel.appendChild(op); });
      sel.disabled = false;
    }).catch(function () { sel.disabled = false; });
  }
  if (country && state && city) {
    country.addEventListener('change', function () {
      fill(state, country.value ? 'ajax/select2_states.php?id=' + encodeURIComponent(country.value) : '', 'Region / state');
      fill(city, '', 'City');
    });
    state.addEventListener('change', function () { fill(city, state.value ? 'ajax/select2_cities.php?id=' + encodeURIComponent(state.value) : '', 'City'); });
  }
  var form = document.getElementById('su-form');
  if (form) {
    form.addEventListener('submit', function () {
      var cc = form.querySelector('[name="phone_cc"]'), num = form.querySelector('[name="phone_number"]'), ph = form.querySelector('[name="phone"]');
      if (cc && num && ph) ph.value = (cc.value + ' ' + num.value.replace(/[^0-9]/g, '')).trim();
    });
    form.querySelectorAll('[data-sl-doc]').forEach(function (b) {
      b.addEventListener('click', function (e) {
        e.preventDefault();
        form.querySelectorAll('[data-sl-doc]').forEach(function (x) { x.classList.toggle('on', x === b); });
        form.querySelector('[name="document_type"]').value = b.getAttribute('data-sl-doc');
        var lab = document.getElementById('su-doclabel'); if (lab) lab.textContent = b.textContent + ' number *';
      });
    });
    form.querySelectorAll('.su-up input[type="file"]').forEach(function (fi) {
      fi.addEventListener('change', function () { var s = fi.closest('.su-up').querySelector('.grow'); if (s && fi.files[0]) s.textContent = fi.files[0].name; });
    });
    var terms = form.querySelector('[data-sl-terms]');
    if (terms) terms.addEventListener('click', function (e) {
      e.preventDefault();
      var on = !terms.classList.contains('on');
      terms.classList.toggle('on', on); terms.setAttribute('aria-checked', on ? 'true' : 'false');
      form.querySelector('[name="terms"]').value = on ? 'yes' : '';
    });
  }

  /* ---- slideshow dots sync with the CSS animation (purely cosmetic) ---- */
  document.querySelectorAll('[data-sl-slides]').forEach(function (el) {
    var n = parseInt(el.getAttribute('data-sl-slides'), 10) || 1, every = (parseInt(el.getAttribute('data-sl-every'), 10) || 6) * 1000;
    var dots = el.querySelectorAll('.lg-dots i, .fs-dots i'), i = 0;
    if (n < 2 || !dots.length) return;
    setInterval(function () { i = (i + 1) % n; dots.forEach(function (d, k) { d.classList.toggle('on', k === i); }); }, every);
  });
})();
