/* Phone-app screens (assets/swiftlane/pwa/mx.css): switches, segmented panels, chips, expandable maps, back. */
(function () {
  'use strict';
  var SL = window.SL || {};
  function toast(m, bad) { if (SL.toast) SL.toast(m, bad); }

  /* .mx-sw switches: flip at once, save when data-sl-save is set, roll back when the server says no */
  document.addEventListener('click', function (e) {
    var sw = e.target.closest('.mx-sw[role="switch"]');
    if (!sw || sw.disabled || sw.getAttribute('aria-busy') === 'true') return;
    var on = !sw.classList.contains('on');
    function set(v) { sw.classList.toggle('on', v); sw.setAttribute('aria-checked', v ? 'true' : 'false'); var i = sw.getAttribute('data-input') && document.querySelector(sw.getAttribute('data-input')); if (i) i.value = v ? '1' : '0'; }
    set(on);
    sw.dispatchEvent(new CustomEvent('mx:switch', { bubbles: true, detail: { on: on } }));
    var url = sw.getAttribute('data-sl-save');
    if (!url || !SL.post) return;
    var data = { key: sw.getAttribute('data-key') || '', value: on ? 1 : 0 };
    Array.prototype.forEach.call(sw.attributes, function (at) { if (at.name.indexOf('data-p-') === 0) data[at.name.slice(7)] = at.value; });
    sw.setAttribute('aria-busy', 'true');
    SL.post(url, data).then(function (j) {
      var ok = j.success === true || j.status === 'success';
      if (!ok) { set(!on); toast(j.message || 'Not saved', true); } else { if (j.message) toast(j.message); if (j.reload || sw.hasAttribute('data-sl-reload')) setTimeout(function () { location.reload(); }, 300); }
    }).catch(function (err) { set(!on); toast(err.message || 'Network error. Please try again.', true); }).finally(function () { sw.removeAttribute('aria-busy'); });
  });

  /* segmented controls: <div class="mx-seg" data-mx-seg> buttons with data-mx-panel="name" show [data-mx-panel-of="name"] */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-mx-seg] [data-mx-panel]');
    if (!b) return;
    var seg = b.closest('[data-mx-seg]'), scope = seg.closest('[data-mx-scope]') || document;
    seg.querySelectorAll('[data-mx-panel]').forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', String(x === b)); });
    scope.querySelectorAll('[data-mx-panel-of]').forEach(function (p) { p.hidden = p.getAttribute('data-mx-panel-of') !== b.getAttribute('data-mx-panel'); });
  });

  /* single-choice chip / option groups writing to a hidden input: <div data-mx-pick="#input"> <button data-value> */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-mx-pick] [data-value]');
    if (!b) return;
    var g = b.closest('[data-mx-pick]'), multi = g.hasAttribute('data-mx-multi');
    if (multi) b.classList.toggle('on'); else g.querySelectorAll('[data-value]').forEach(function (x) { x.classList.toggle('on', x === b); });
    g.querySelectorAll('[data-value]').forEach(function (x) { x.setAttribute('aria-pressed', String(x.classList.contains('on'))); });
    var input = document.querySelector(g.getAttribute('data-mx-pick'));
    if (input) {
      input.value = multi ? Array.prototype.filter.call(g.querySelectorAll('[data-value].on'), Boolean).map(function (x) { return x.getAttribute('data-value'); }).join(',') : b.getAttribute('data-value');
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });

  /* maps: the expand button turns .mx-map into the full-screen map with directions */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-mx-map-toggle]');
    if (!b) return;
    var map = b.closest('.mx-map'); if (!map) return;
    var full = map.classList.toggle('full');
    document.documentElement.style.overflow = full ? 'hidden' : '';
    map.querySelectorAll('[data-mx-map-toggle][aria-label]').forEach(function (x) { x.setAttribute('aria-label', full ? 'Close map' : 'Expand map'); });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { var m = document.querySelector('.mx-map.full'); if (m) { m.classList.remove('full'); document.documentElement.style.overflow = ''; } } });

  /* back: go back in history when the previous page is ours, else follow the link to the parent screen */
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-mx-back]');
    if (!a) return;
    try { if (document.referrer && new URL(document.referrer).origin === location.origin && history.length > 1 && !/login|auth-otp/.test(document.referrer)) { e.preventDefault(); history.back(); } } catch (err) {}
  });

  /* share sheet on phones, copy on computers */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-mx-share]');
    if (!b) return;
    e.preventDefault();
    var payload = { title: b.getAttribute('data-title') || document.title, text: b.getAttribute('data-text') || '', url: b.getAttribute('data-mx-share') || location.href };
    if (navigator.share) { navigator.share(payload).catch(function () {}); return; }
    var text = (payload.text ? payload.text + ' ' : '') + payload.url;
    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { toast('Copied'); }).catch(function () { toast('Could not copy. Select and copy the text.', true); });
  });

  /* countdowns: <b data-mx-countdown="2026-11-27T20:00:00+00:00"> shows 2 h 14 min, then the data-done text */
  function tick() {
    document.querySelectorAll('[data-mx-countdown]').forEach(function (el) {
      var left = Math.floor((new Date(el.getAttribute('data-mx-countdown')).getTime() - Date.now()) / 1000);
      if (isNaN(left)) return;
      if (left <= 0) { el.textContent = el.getAttribute('data-done') || 'now'; return; }
      var h = Math.floor(left / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
      el.textContent = h ? h + ' h ' + m + ' min' : (m ? m + ' min' + (m < 5 ? ' ' + s + ' s' : '') : s + ' s');
    });
  }
  if (document.querySelector('[data-mx-countdown]')) { tick(); setInterval(tick, 1000); }
})();
