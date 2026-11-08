/* Swift Lane runtime: theme, menus, dialogs, filters, AJAX helpers. No framework. */
(function () {
  'use strict';
  var SL = window.SL = window.SL || {};
  var app = document.getElementById('sl-app');

  /* ---------- theme ---------- */
  function applyTheme(t) {
    var dark = t === 'dark' || (t === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('sl-dark', dark);
    if (app && !app.hasAttribute('data-force-dark')) app.classList.toggle('dk', dark);
    document.querySelectorAll('[data-sl-set-theme]').forEach(function (b) {
      b.classList.toggle('on', b.getAttribute('data-sl-set-theme') === (dark ? 'dark' : 'light'));
    });
  }
  SL.theme = function (t) {
    try { localStorage.setItem('sl.theme', t); } catch (e) {}
    applyTheme(t);
    SL.post('ajax/sl/preferences_ajax.php', { theme: t }).catch(function () {});
  };
  var saved = 'light';
  try { saved = localStorage.getItem('sl.theme') || 'light'; } catch (e) {}
  if (app && app.classList.contains('dk')) app.setAttribute('data-force-dark', '1');
  applyTheme(saved);
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-sl-theme]');
    if (t) { e.preventDefault(); SL.theme(document.documentElement.classList.contains('sl-dark') ? 'light' : 'dark'); return; }
    var s = e.target.closest('[data-sl-set-theme]');
    if (s) { e.preventDefault(); SL.theme(s.getAttribute('data-sl-set-theme')); }
  });

  /* ---------- details popups: one open at a time, close on outside click / Escape ---------- */
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.aitop[open], details.uptop[open], details[data-sl-pop][open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('details[open]').forEach(function (d) { d.removeAttribute('open'); });
      document.querySelectorAll('.sl-dialog:not([hidden])').forEach(function (d) { d.hidden = true; });
      var nav = document.getElementById('navtg'); if (nav) nav.checked = false;
    }
  });

  /* ---------- toast ---------- */
  var toastTimer;
  SL.toast = function (msg, bad) {
    var el = document.getElementById('sl-toast');
    if (!el) return;
    el.textContent = msg; el.classList.toggle('bad', !!bad); el.hidden = false;
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { el.hidden = true; }, 4000);
  };

  /* ---------- CSRF + fetch helpers ---------- */
  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]'), p = document.querySelector('meta[name="csrf-param"]');
    return { token: m ? m.content : '', param: p ? p.content : 'csrf_token' };
  }
  /* Legacy endpoints answer with Bootstrap alert HTML instead of JSON: read it as a result. */
  SL.fromHtml = function (html, httpOk) {
    var box = document.createElement('div'); box.innerHTML = html;
    var bad = !httpOk || !!box.querySelector('.alert-danger, .alert-warning, .error');
    var items = Array.prototype.map.call(box.querySelectorAll('li'), function (li) { return li.textContent.trim(); }).filter(Boolean);
    var text = items.length ? items.join(' · ') : box.textContent.replace(/\s+/g, ' ').trim();
    if (!text) text = bad ? 'The request was not accepted' : 'Saved';
    return { success: !bad, message: text.slice(0, 300) };
  };
  SL.post = function (url, data) {
    var c = csrf(), body;
    if (data instanceof FormData) { body = data; body.append(c.param, c.token); }
    else { body = new URLSearchParams(); Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); }); body.append(c.param, c.token); }
    return fetch(url, { method: 'POST', body: body, headers: { 'X-CSRF-Token': c.token, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.text().then(function (t) { var j; try { j = JSON.parse(t); } catch (e) { j = SL.fromHtml(t, r.ok); } if (!r.ok && j.success === undefined) j.success = false; return j; }); });
  };
  SL.get = function (url, params) {
    var u = new URL(url, location.href); Object.keys(params || {}).forEach(function (k) { u.searchParams.set(k, params[k]); });
    return fetch(u, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  };

  /* ---------- forms: data-sl-form posts via fetch, shows result ---------- */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches('form[data-sl-form]')) return;
    e.preventDefault();
    var btn = f.querySelector('[type="submit"]');
    if (btn) { btn.classList.add('loading'); btn.disabled = true; }
    f.querySelectorAll('.sl-err').forEach(function (x) { x.remove(); });
    SL.post(f.getAttribute('action') || location.href, new FormData(f)).then(function (j) {
      var ok = j.success === true || j.status === 'success';
      var msg = j.message || (ok ? 'Saved' : 'Something went wrong');
      if (j.errors && typeof j.errors === 'object') {
        Object.keys(j.errors).forEach(function (k) {
          var inp = f.querySelector('[name="' + k + '"]'); if (!inp) return;
          inp.classList.add('is-invalid');
          var s = document.createElement('span'); s.className = 'sl-err'; s.textContent = j.errors[k];
          (inp.closest('.sl-field') || inp.parentNode).appendChild(s);
        });
      }
      SL.toast(msg, !ok);
      if (ok && j.redirect) setTimeout(function () { location.href = j.redirect; }, 300);
      else if (ok && f.hasAttribute('data-sl-reload')) setTimeout(function () { location.reload(); }, 300);
      f.dispatchEvent(new CustomEvent('sl:done', { detail: j, bubbles: true }));
    }).catch(function () { SL.toast('Network error. Please try again.', true); })
      .finally(function () { if (btn) { btn.classList.remove('loading'); btn.disabled = false; } });
  });

  /* ---------- data-sl-action: one-click POST actions (with optional confirm) ---------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-sl-action]');
    if (!a) return;
    e.preventDefault();
    var msg = a.getAttribute('data-sl-confirm');
    if (msg && !confirm(msg)) return;
    var data = {};
    Array.prototype.forEach.call(a.attributes, function (at) { if (at.name.indexOf('data-p-') === 0) data[at.name.slice(7)] = at.value; });
    a.classList.add('loading');
    SL.post(a.getAttribute('data-sl-action'), data).then(function (j) {
      var ok = j.success === true || j.status === 'success';
      SL.toast(j.message || (ok ? 'Done' : 'Failed'), !ok);
      if (ok && (a.hasAttribute('data-sl-reload') || j.reload)) setTimeout(function () { location.reload(); }, 300);
      if (ok && j.redirect) location.href = j.redirect;
      a.dispatchEvent(new CustomEvent('sl:done', { detail: j, bubbles: true }));
    }).catch(function () { SL.toast('Network error', true); }).finally(function () { a.classList.remove('loading'); });
  });

  /* ---------- confirm on plain links/forms ---------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-sl-confirm]:not([data-sl-action])');
    if (a && !confirm(a.getAttribute('data-sl-confirm'))) e.preventDefault();
  });

  /* ---------- dialogs ---------- */
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-sl-open]');
    if (o) { e.preventDefault(); var d = document.getElementById(o.getAttribute('data-sl-open')); if (d) { d.hidden = false; var f = d.querySelector('input,select,textarea,button'); if (f) f.focus(); } return; }
    var c = e.target.closest('[data-sl-close]');
    if (c) { e.preventDefault(); var dd = c.closest('.sl-dialog'); if (dd) dd.hidden = true; return; }
    if (e.target.classList && e.target.classList.contains('sl-dialog')) e.target.hidden = true;
  });

  /* ---------- tabs ---------- */
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-sl-tab]');
    if (!t) return;
    e.preventDefault();
    var group = t.closest('[data-sl-tabs]') || document;
    var name = t.getAttribute('data-sl-tab');
    group.querySelectorAll('[data-sl-tab]').forEach(function (x) { x.classList.toggle('on', x === t); });
    document.querySelectorAll('[data-sl-tabpanel]').forEach(function (p) {
      if (p.closest('[data-sl-tabs]') && p.closest('[data-sl-tabs]') !== group) return;
      p.hidden = p.getAttribute('data-sl-tabpanel') !== name;
    });
    if (t.hasAttribute('data-sl-tab-url')) history.replaceState(null, '', t.getAttribute('data-sl-tab-url'));
  });
  if (location.hash) { var ht = document.querySelector('[data-sl-tab="' + location.hash.slice(1) + '"]'); if (ht) ht.click(); }

  /* ---------- segmented filters and search: filter rows client-side ---------- */
  function applyFilter(scope) {
    var seg = scope.querySelector('[data-sl-filter-seg] .on, .sl-seg[data-sl-filter] .on');
    var val = seg ? (seg.getAttribute('data-f') || '').toLowerCase() : '';
    var q = (scope.querySelector('[data-sl-filter-q]') || {}).value || '';
    q = q.toLowerCase();
    var shown = 0;
    scope.querySelectorAll('[data-sl-row]').forEach(function (r) {
      var tags = (r.getAttribute('data-f') || '').toLowerCase();
      var ok = (!val || val === 'all' || tags.split(' ').indexOf(val) >= 0) && (!q || r.textContent.toLowerCase().indexOf(q) >= 0);
      r.hidden = !ok; if (ok) shown++;
    });
    var empty = scope.querySelector('[data-sl-empty]'); if (empty) empty.hidden = shown > 0;
    var cnt = scope.querySelector('[data-sl-count]'); if (cnt) cnt.textContent = shown;
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.sl-seg[data-sl-filter] button, .sl-seg[data-sl-filter] a, [data-sl-filter-seg] button');
    if (!b) return;
    e.preventDefault();
    var seg = b.closest('.sl-seg, [data-sl-filter-seg]');
    seg.querySelectorAll('button, a').forEach(function (x) { x.classList.toggle('on', x === b); });
    applyFilter(seg.closest('[data-sl-scope]') || document);
  });
  document.addEventListener('input', function (e) {
    if (e.target.matches('[data-sl-filter-q]')) applyFilter(e.target.closest('[data-sl-scope]') || document);
  });

  /* ---------- toggles (role=switch) ---------- */
  document.addEventListener('click', function (e) {
    var sw = e.target.closest('.sl-toggle[role="switch"]');
    if (!sw || sw.hasAttribute('data-static')) return;
    var on = !sw.classList.contains('on');
    sw.classList.toggle('on', on); sw.setAttribute('aria-checked', on ? 'true' : 'false');
    var inp = sw.querySelector('input[type="hidden"]') || (sw.getAttribute('data-input') && document.querySelector(sw.getAttribute('data-input')));
    if (inp) inp.value = on ? '1' : '0';
    if (sw.hasAttribute('data-sl-save')) {
      var data = { key: sw.getAttribute('data-key') || '', value: on ? 1 : 0 };
      Array.prototype.forEach.call(sw.attributes, function (at) { if (at.name.indexOf('data-p-') === 0) data[at.name.slice(7)] = at.value; });
      SL.post(sw.getAttribute('data-sl-save'), data).then(function (j) { var ok = j.success === true || j.status === 'success'; if (!ok) { sw.classList.toggle('on', !on); SL.toast(j.message || 'Not saved', true); } else if (j.message) SL.toast(j.message); });
    }
  });
  document.querySelectorAll('.sl-toggle[role="switch"]').forEach(function (sw) { if (!sw.hasAttribute('tabindex')) sw.setAttribute('tabindex', '0'); });
  document.addEventListener('keydown', function (e) { if ((e.key === ' ' || e.key === 'Enter') && e.target.matches('.sl-toggle[role="switch"]')) { e.preventDefault(); e.target.click(); } });

  /* ---------- copy to clipboard ---------- */
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-sl-copy]');
    if (!c) return; e.preventDefault();
    navigator.clipboard.writeText(c.getAttribute('data-sl-copy')).then(function () { SL.toast('Copied'); });
  });

  /* ---------- table sort ---------- */
  document.addEventListener('click', function (e) {
    var th = e.target.closest('th[data-sort]');
    if (!th) return;
    var table = th.closest('table'), idx = Array.prototype.indexOf.call(th.parentNode.children, th);
    var dir = th.getAttribute('aria-sort') === 'ascending' ? -1 : 1;
    th.parentNode.querySelectorAll('th').forEach(function (x) { x.removeAttribute('aria-sort'); });
    th.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
    var tb = table.tBodies[0], rows = Array.prototype.slice.call(tb.rows);
    rows.sort(function (a, b) {
      var x = a.cells[idx] ? a.cells[idx].textContent.trim() : '', y = b.cells[idx] ? b.cells[idx].textContent.trim() : '';
      var nx = parseFloat(x.replace(/[^0-9.\-]/g, '')), ny = parseFloat(y.replace(/[^0-9.\-]/g, ''));
      if (!isNaN(nx) && !isNaN(ny) && x !== '' && y !== '') return (nx - ny) * dir;
      return x.localeCompare(y) * dir;
    });
    rows.forEach(function (r) { tb.appendChild(r); });
  });

  /* ---------- print ---------- */
  document.addEventListener('click', function (e) { if (e.target.closest('[data-sl-print]')) { e.preventDefault(); window.print(); } });

  /* ---------- auto-refresh blocks ---------- */
  document.querySelectorAll('[data-sl-refresh]').forEach(function (el) {
    var every = parseInt(el.getAttribute('data-sl-refresh'), 10) || 30000, url = el.getAttribute('data-sl-src');
    if (!url) return;
    setInterval(function () { fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (t) { el.innerHTML = t; }).catch(function () {}); }, every);
  });

  /* ---------- live clock ---------- */
  document.querySelectorAll('[data-sl-clock]').forEach(function (el) {
    setInterval(function () { el.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }, 30000);
  });
  /* ---------- masonry grids: short cards stack instead of leaving holes ---------- */
  (function () {
    var sel = '.sl-masonry,.hb-g';
    function lay(g) {
      var cs = getComputedStyle(g), cols = cs.gridTemplateColumns.split(' ').length;
      var gap = parseFloat(cs.columnGap) || 16, kids = Array.prototype.slice.call(g.children);
      kids.forEach(function (k) { k.style.minHeight = ''; });
      g.classList.toggle('is-masonry', cols > 1);
      if (cols < 2) { kids.forEach(function (k) { k.style.gridRowEnd = ''; }); return; }
      kids.forEach(function (k) { k.style.gridRowEnd = 'span ' + Math.max(1, Math.ceil(k.getBoundingClientRect().height + gap)); });
      /* close the holes: the last card in each column grows to the grid's bottom edge */
      var last = {}, bottom = 0;
      kids.forEach(function (k) {
        if (k.offsetParent === null) return;
        var r = k.getBoundingClientRect(); bottom = Math.max(bottom, r.bottom);
        var key = Math.round(r.left) + ':' + Math.round(r.right);
        if (!last[key] || r.bottom > last[key].r.bottom) last[key] = { k: k, r: r };
      });
      Object.keys(last).forEach(function (key) {
        var it = last[key], d = bottom - it.r.bottom;
        if (d > 1) it.k.style.minHeight = Math.round(it.r.height + d) + 'px';
      });
    }
    var grids = document.querySelectorAll(sel);
    if (!grids.length) return;
    var ro = window.ResizeObserver ? new ResizeObserver(function (es) {
      es.forEach(function (e) { var g = e.target.closest(sel); if (g) lay(g); });
    }) : null;
    Array.prototype.forEach.call(grids, function (g) {
      lay(g);
      if (ro) { ro.observe(g); Array.prototype.forEach.call(g.children, function (k) { ro.observe(k); }); }
    });
    window.addEventListener('load', function () { Array.prototype.forEach.call(grids, lay); });
  })();
  /* ---------- balance side-by-side columns (.split, .fr-split, .mt, .sl-balance, card grids) ----------
     Cards sharing a row end on the same line. A short companion column next to a very long one
     becomes sticky instead of being stretched into a big empty card. Layout only; no content change. */
  (function () {
    var sel = '.split,.fr-split,.mt,.au,.dt,.eb,.nc-wrap,.ix,.ho,.sl-balance,.cpx-featured-grid,.grid.cols2,.grid.cols3';
    var STRETCH_MAX = 720;
    function cardish(e) {
      var s = getComputedStyle(e);
      return s.borderTopWidth !== '0px' || (s.backgroundColor !== 'rgba(0, 0, 0, 0)' && s.backgroundColor !== 'transparent');
    }
    function lastCard(e) {
      for (var i = 0; i < 6 && e; i++) {
        if (/^(INPUT|SELECT|TEXTAREA|BUTTON|LABEL|A|TABLE|IMG|SVG|CANVAS|FORM)$/i.test(e.tagName) && !cardish(e)) return null;
        if (e.offsetHeight < 80) return null;
        if (cardish(e)) return e;
        /* walk back past small trailing notes/buttons to the last real card */
        var kids = Array.prototype.filter.call(e.children, function (k) { return k.offsetParent !== null; }), next = null;
        for (var j = kids.length - 1; j >= 0; j--) { if (kids[j].offsetHeight >= 80) { next = kids[j]; break; } }
        e = next;
      }
      return null;
    }
    function topOffset() {
      var t = document.querySelector('.sl-top');
      return t && getComputedStyle(t).position === 'sticky' ? t.offsetHeight + 12 : 16;
    }
    function bal(c) {
      var kids = Array.prototype.filter.call(c.children, function (k) { return k.offsetParent !== null && k.offsetHeight > 40; });
      if (kids.length < 2) return;
      var rows = {};
      kids.forEach(function (k) { var r = k.getBoundingClientRect(), key = Math.round(r.top / 4); (rows[key] = rows[key] || []).push({ k: k, r: r }); });
      Object.keys(rows).forEach(function (key) {
        var row = rows[key]; if (row.length < 2) return;
        var bottom = Math.max.apply(null, row.map(function (x) { return x.r.bottom; }));
        row.forEach(function (x) {
          var d = bottom - x.r.bottom; if (d < 2) return;
          if (d > STRETCH_MAX) {
            var t = topOffset();
            x.k.style.position = 'sticky'; x.k.style.top = t + 'px'; x.k.style.alignSelf = 'flex-start';
            /* taller than the screen: cap it and let it scroll inside, so it stays reachable */
            if (x.r.height > window.innerHeight - t - 16) { x.k.style.maxHeight = (window.innerHeight - t - 16) + 'px'; x.k.style.overflowY = 'auto'; }
            touched.push(x.k);
            return;
          }
          var card = lastCard(x.k); if (!card) return;
          var cr = card.getBoundingClientRect();
          card.style.minHeight = Math.round(Math.max(cr.height, cr.height + (bottom - cr.bottom))) + 'px';
          touched.push(card);
        });
      });
    }
    var list = document.querySelectorAll(sel);
    if (!list.length) return;
    var pending = false, touched = [];
    function all() {
      pending = false;
      touched.forEach(function (e) { e.style.minHeight = ''; e.style.position = ''; e.style.top = ''; e.style.alignSelf = ''; e.style.maxHeight = ''; e.style.overflowY = ''; });
      touched = [];
      Array.prototype.forEach.call(list, bal);
    }
    function soon() { if (!pending) { pending = true; requestAnimationFrame(all); } }
    all();
    window.addEventListener('load', soon);
    window.addEventListener('resize', soon);
    if (window.ResizeObserver) {
      var last = new WeakMap();
      var ro = new ResizeObserver(function (es) {
        var changed = es.some(function (e) { var w = Math.round(e.contentRect.width); var p = last.get(e.target); last.set(e.target, w); return p !== undefined && p !== w; });
        if (changed) soon();
      });
      Array.prototype.forEach.call(list, function (c) { ro.observe(c); });
    }
    document.addEventListener('click', function () { setTimeout(soon, 60); });
  })();
})();
