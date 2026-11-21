/* Live tracking console: list selection, filters, search and tabs (views/sl/pages/partials/track_console.php). */
(function () {
  'use strict';
  if (window.slTrackConsole) return;
  window.slTrackConsole = true;

  function tabOf(tc) {
    var on = tc.querySelector('[data-tc-tab].on');
    return on ? on.getAttribute('data-tc-tab') : 'overview';
  }

  function select(tc, key) {
    tc.querySelectorAll('[data-tc-key]').forEach(function (c) {
      var on = c.getAttribute('data-tc-key') === key;
      c.classList.toggle('on', on);
      c.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    var body = null;
    tc.querySelectorAll('[data-tc-body]').forEach(function (b) {
      var on = b.getAttribute('data-tc-body') === key;
      b.classList.toggle('on', on);
      if (on) body = b;
    });
    tc.querySelectorAll('[data-tc-geo]').forEach(function (g) {
      g.classList.toggle('on', g.getAttribute('data-tc-geo') === key);
    });
    var open = tc.querySelector('[data-tc-open]');
    if (open && body) open.setAttribute('href', body.getAttribute('data-href'));
    showTab(tc, tabOf(tc));
  }

  function showTab(tc, tab) {
    tc.querySelectorAll('[data-tc-tab]').forEach(function (t) {
      var on = t.getAttribute('data-tc-tab') === tab;
      t.classList.toggle('on', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    tc.querySelectorAll('[data-tc-body].on [data-tc-sec]').forEach(function (s) {
      s.classList.toggle('on', s.getAttribute('data-tc-sec') === tab);
    });
  }

  function filter(tc) {
    var fb = tc.querySelector('[data-tc-f].on');
    var f = fb ? fb.getAttribute('data-tc-f') : 'all';
    var q = ((tc.querySelector('[data-tc-q]') || {}).value || '').trim().toLowerCase();
    var firstVisible = null, selVisible = false, shown = 0;
    tc.querySelectorAll('[data-tc-key]').forEach(function (c) {
      var ok = (f === 'all' || (f === 'late' ? c.getAttribute('data-late') === '1' : c.getAttribute('data-mode') === f)) &&
        (!q || (c.getAttribute('data-text') || '').indexOf(q) !== -1);
      c.hidden = !ok;
      if (ok) { shown++; if (!firstVisible) firstVisible = c; if (c.classList.contains('on')) selVisible = true; }
    });
    var empty = tc.querySelector('[data-tc-empty]');
    if (empty) empty.hidden = shown > 0;
    if (firstVisible && !selVisible) select(tc, firstVisible.getAttribute('data-tc-key'));
  }

  document.addEventListener('click', function (e) {
    var tc = e.target.closest('[data-tc]');
    if (!tc) return;
    var card = e.target.closest('[data-tc-key]');
    if (card) { select(tc, card.getAttribute('data-tc-key')); return; }
    var tab = e.target.closest('[data-tc-tab]');
    if (tab) { showTab(tc, tab.getAttribute('data-tc-tab')); return; }
    var f = e.target.closest('[data-tc-f]');
    if (f) {
      tc.querySelectorAll('[data-tc-f]').forEach(function (b) { b.classList.toggle('on', b === f); });
      filter(tc);
    }
  });
  document.addEventListener('input', function (e) {
    if (e.target.matches('[data-tc-q]')) filter(e.target.closest('[data-tc]'));
  });
  document.addEventListener('keydown', function (e) {
    var card = e.target.closest && e.target.closest('[data-tc-key]');
    if (!card || (e.key !== 'ArrowDown' && e.key !== 'ArrowUp')) return;
    e.preventDefault();
    var list = Array.prototype.filter.call(card.parentNode.querySelectorAll('[data-tc-key]'), function (c) { return !c.hidden; });
    var next = list[list.indexOf(card) + (e.key === 'ArrowDown' ? 1 : -1)];
    if (next) { next.focus(); select(next.closest('[data-tc]'), next.getAttribute('data-tc-key')); }
  });
})();
