/* Swift Lane runtime: theme, menus, dialogs, filters, AJAX helpers. No framework. */
(function () {
  'use strict';
  var SL = window.SL = window.SL || {};
  var app = document.getElementById('sl-app');

  /* ---------- navigation and focus ---------- */
  var nav = document.getElementById('navtg'), side = document.getElementById('sl-navigation');
  var mobile = matchMedia('(max-width: 900px)');
  function focusable(root) {
    return Array.prototype.filter.call(root.querySelectorAll('a[href],button,input,select,textarea,summary,[tabindex]'), function (el) {
      return !el.disabled && el.tabIndex >= 0 && !el.closest('[hidden], [inert]') && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden';
    });
  }
  function trapFocus(e, root) {
    if (e.key !== 'Tab') return;
    var items = focusable(root), first = items[0], last = items[items.length - 1];
    if (!first) { e.preventDefault(); root.focus(); return; }
    if (e.shiftKey && (document.activeElement === first || !root.contains(document.activeElement))) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && (document.activeElement === last || !root.contains(document.activeElement))) { e.preventDefault(); first.focus(); }
  }
  function syncNav() {
    if (!nav || !side) return;
    var open = mobile.matches ? nav.checked : !nav.checked;
    side.inert = !open;
    side.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.querySelectorAll('[data-sl-nav]').forEach(function (b) {
      b.setAttribute('aria-expanded', String(open));
      b.setAttribute('aria-label', open ? 'Close the menu' : 'Open the menu');
    });
    document.querySelectorAll('.sl-main,.sl-top,.sl-rail').forEach(function (el) { el.inert = mobile.matches && open; });
    document.body.style.overflow = mobile.matches && open ? 'hidden' : '';
  }
  if (nav && side) {
    nav.addEventListener('change', function () { syncNav(); if (mobile.matches && !nav.checked) document.querySelector('.nav-open').focus(); });
    mobile.addEventListener('change', function () { nav.checked = false; syncNav(); });
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-sl-nav]');
      if (!b) return;
      nav.checked = !nav.checked; syncNav();
      if (mobile.matches && nav.checked) { var first = focusable(side)[0]; if (first) first.focus(); }
      else if (mobile.matches) document.querySelector('.nav-open').focus();
    });
    syncNav();
  }

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
  var saved = app && app.classList.contains('dk') ? 'dark' : 'light';
  try { saved = localStorage.getItem('sl.theme') || saved; } catch (e) {}
  applyTheme(saved);
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-sl-theme]');
    if (t) { e.preventDefault(); SL.theme(document.documentElement.classList.contains('sl-dark') ? 'light' : 'dark'); return; }
    var s = e.target.closest('[data-sl-set-theme]');
    if (s) { e.preventDefault(); SL.theme(s.getAttribute('data-sl-set-theme')); }
  });
  /* Row menus use the top layer so scrolling table/card containers cannot clip them. */
  var pinnedMenus = new WeakMap();
  document.addEventListener('toggle', function (e) {
    var details = e.target;
    if (!details.matches || !details.matches('details[data-sl-pop]') || !details.closest('.sl-tw')) return;
    var menu = details.querySelector('.pop'), summary = details.querySelector('summary');
    if (!menu || !summary) return;
    if (!details.open) {
      var undo = pinnedMenus.get(details); if (undo) { undo(); pinnedMenus.delete(details); }
      return;
    }
    if (pinnedMenus.has(details)) return;
    var oldStyle = menu.getAttribute('style'), oldPopover = menu.getAttribute('popover');
    if (menu.showPopover) { menu.setAttribute('popover', 'manual'); menu.showPopover(); }
    menu.style.position = 'fixed'; menu.style.margin = '0'; menu.style.inset = 'auto';
    menu.style.maxWidth = 'calc(100vw - 16px)'; menu.style.maxHeight = 'calc(100vh - 16px)'; menu.style.overflowY = 'auto'; menu.style.zIndex = '80';
    var anchor = summary.getBoundingClientRect(), rect = menu.getBoundingClientRect();
    menu.style.left = Math.max(8, Math.min(innerWidth - rect.width - 8, anchor.right - rect.width)) + 'px';
    menu.style.top = Math.max(8, Math.min(innerHeight - rect.height - 8, anchor.bottom + 4)) + 'px';
    function close(ev) { if (!menu.contains(ev.target)) details.open = false; }
    window.addEventListener('resize', close); window.addEventListener('scroll', close, true);
    pinnedMenus.set(details, function () {
      window.removeEventListener('resize', close); window.removeEventListener('scroll', close, true);
      if (menu.hidePopover && menu.matches(':popover-open')) menu.hidePopover();
      if (oldStyle === null) menu.removeAttribute('style'); else menu.setAttribute('style', oldStyle);
      if (oldPopover === null) menu.removeAttribute('popover'); else menu.setAttribute('popover', oldPopover);
    });
  }, true);

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
      if (nav && mobile.matches && nav.checked) { nav.checked = false; syncNav(); document.querySelector('.nav-open').focus(); }
    }
    var dialogs = document.querySelectorAll('.sl-dialog:not([hidden])');
    if (dialogs.length) trapFocus(e, dialogs[dialogs.length - 1]);
    else if (nav && mobile.matches && nav.checked) trapFocus(e, side);
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
    var bad = !httpOk || !!box.querySelector('.alert-danger, .alert-warning, .error') || !box.querySelector('.alert-success, .success');
    var items = Array.prototype.map.call(box.querySelectorAll('li'), function (li) { return li.textContent.trim(); }).filter(Boolean);
    var text = items.length ? items.join(' · ') : box.textContent.replace(/\s+/g, ' ').trim();
    if (!text) text = bad ? 'The server returned an unexpected response. Please try again.' : 'Saved';
    return { success: !bad, message: text.slice(0, 300) };
  };
  SL.post = function (url, data) {
    var c = csrf(), body;
    if (data instanceof FormData) { body = data; body.append(c.param, c.token); }
    else { body = new URLSearchParams(); Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); }); body.append(c.param, c.token); }
    return fetch(url, { method: 'POST', body: body, headers: { 'X-CSRF-Token': c.token, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) {
        if (r.redirected && /\/(login|auth-otp)\.php(?:[?#]|$)/.test(r.url)) throw new Error('Your session expired. Sign in again before saving.');
        return r.text().then(function (t) {
          var j; try { j = JSON.parse(t); } catch (e) { j = SL.fromHtml(t, r.ok); }
          if (!j || typeof j !== 'object') j = { success: false, message: 'The server returned an unexpected response. Please try again.' };
          if (!r.ok) { j.success = false; j.ok = false; j.status = 'error'; }
          return j;
        });
      });
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
    if (f.getAttribute('aria-busy') === 'true') return;
    f.setAttribute('aria-busy', 'true');
    var btn = e.submitter || f.querySelector('[type="submit"]');
    if (btn) { btn.classList.add('loading'); btn.disabled = true; }
    f.querySelectorAll('.sl-err').forEach(function (x) { x.remove(); });
    f.querySelectorAll('.is-invalid').forEach(function (x) { x.classList.remove('is-invalid'); x.removeAttribute('aria-invalid'); });
    SL.post(f.getAttribute('action') || location.href, new FormData(f)).then(function (j) {
      var ok = j.success === true || j.status === 'success';
      var msg = j.message || (ok ? 'Saved' : 'Something went wrong');
      if (j.errors && typeof j.errors === 'object') {
        Object.keys(j.errors).forEach(function (k) {
          var inp = f.elements.namedItem(k); if (inp && !inp.tagName) inp = inp[0]; if (!inp) return;
          inp.classList.add('is-invalid');
          inp.setAttribute('aria-invalid', 'true');
          var s = document.createElement('span'); s.className = 'sl-err'; s.textContent = j.errors[k];
          (inp.closest('.sl-field') || inp.parentNode).appendChild(s);
        });
        var invalid = f.querySelector('.is-invalid'); if (invalid) invalid.focus();
      }
      SL.toast(msg, !ok);
      if (ok && j.redirect) setTimeout(function () { location.href = j.redirect; }, 300);
      else if (ok && f.hasAttribute('data-sl-reload')) setTimeout(function () { location.reload(); }, 300);
      f.dispatchEvent(new CustomEvent('sl:done', { detail: j, bubbles: true }));
    }).catch(function (err) { SL.toast(err.message || 'Network error. Please try again.', true); })
      .finally(function () { f.removeAttribute('aria-busy'); if (btn) { btn.classList.remove('loading'); btn.disabled = false; } });
  });

  /* ---------- data-sl-action: one-click POST actions (with optional confirm) ---------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-sl-action]');
    if (!a) return;
    e.preventDefault();
    if (a.getAttribute('aria-busy') === 'true' || a.disabled || a.getAttribute('aria-disabled') === 'true') return;
    var msg = a.getAttribute('data-sl-confirm');
    if (msg && !confirm(msg)) return;
    var data = {};
    Array.prototype.forEach.call(a.attributes, function (at) { if (at.name.indexOf('data-p-') === 0) data[at.name.slice(7)] = at.value; });
    a.classList.add('loading');
    a.setAttribute('aria-busy', 'true');
    SL.post(a.getAttribute('data-sl-action'), data).then(function (j) {
      var ok = j.success === true || j.status === 'success';
      SL.toast(j.message || (ok ? 'Done' : 'Failed'), !ok);
      if (ok && (a.hasAttribute('data-sl-reload') || j.reload)) setTimeout(function () { location.reload(); }, 300);
      if (ok && j.redirect) location.href = j.redirect;
      a.dispatchEvent(new CustomEvent('sl:done', { detail: j, bubbles: true }));
    }).catch(function (err) { SL.toast(err.message || 'Network error', true); }).finally(function () { a.classList.remove('loading'); a.removeAttribute('aria-busy'); });
  });

  /* ---------- confirm on plain links/forms ---------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-sl-confirm]:not([data-sl-action])');
    if (a && !confirm(a.getAttribute('data-sl-confirm'))) e.preventDefault();
  });

  /* ---------- dialogs ---------- */
  var dialogFocus = new WeakMap();
  document.querySelectorAll('.sl-dialog').forEach(function (dialog) {
    var modal = dialog.querySelector('.sl-modal') || dialog;
    modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true'); modal.tabIndex = -1;
    if (!modal.hasAttribute('aria-label') && !modal.hasAttribute('aria-labelledby')) {
      var heading = modal.querySelector('h1,h2,h3'); if (heading) modal.setAttribute('aria-label', heading.textContent.trim());
    }
    new MutationObserver(function () {
      if (!dialog.hidden) {
        dialogFocus.set(dialog, document.activeElement);
        var first = focusable(modal)[0]; (first || modal).focus();
      } else {
        var previous = dialogFocus.get(dialog); if (previous && previous.isConnected) previous.focus();
      }
      document.body.style.overflow = document.querySelector('.sl-dialog:not([hidden])') || (nav && mobile.matches && nav.checked) ? 'hidden' : '';
    }).observe(dialog, { attributes: true, attributeFilter: ['hidden'] });
  });
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-sl-open]');
    if (o) { e.preventDefault(); var d = document.getElementById(o.getAttribute('data-sl-open')); if (d) d.hidden = false; return; }
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
    group.querySelectorAll('[data-sl-tab]').forEach(function (x) { x.classList.toggle('on', x === t); x.setAttribute('aria-selected', String(x === t)); });
    document.querySelectorAll('[data-sl-tabpanel]').forEach(function (p) {
      if ((p.closest('[data-sl-tabs]') || document) !== group) return;
      p.hidden = p.getAttribute('data-sl-tabpanel') !== name;
    });
    if (t.hasAttribute('data-sl-tab-url')) history.replaceState(null, '', t.getAttribute('data-sl-tab-url'));
  });
  document.addEventListener('keydown', function (e) {
    if (!e.target.matches('[data-sl-tab]')) return;
    if ((e.key === 'Enter' || e.key === ' ') && !e.target.matches('button,a')) { e.preventDefault(); e.target.click(); }
    if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(e.key) < 0) return;
    var group = e.target.closest('[data-sl-tabs]') || document;
    var tabs = Array.prototype.filter.call(group.querySelectorAll('[data-sl-tab]'), function (t) { return (t.closest('[data-sl-tabs]') || document) === group; });
    var index = tabs.indexOf(e.target);
    if (e.key === 'Home') index = 0;
    else if (e.key === 'End') index = tabs.length - 1;
    else index = (index + (e.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
    if (tabs[index]) { e.preventDefault(); tabs[index].focus(); tabs[index].click(); }
  });
  if (location.hash) { var ht = Array.prototype.find.call(document.querySelectorAll('[data-sl-tab]'), function (el) { return el.getAttribute('data-sl-tab') === location.hash.slice(1); }); if (ht) ht.click(); }

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
    if (!sw || sw.hasAttribute('data-static') || sw.getAttribute('aria-busy') === 'true' || sw.getAttribute('aria-disabled') === 'true') return;
    var on = !sw.classList.contains('on');
    sw.classList.toggle('on', on); sw.setAttribute('aria-checked', on ? 'true' : 'false');
    var inp = sw.querySelector('input[type="hidden"]') || (sw.getAttribute('data-input') && document.querySelector(sw.getAttribute('data-input')));
    if (inp) inp.value = on ? '1' : '0';
    if (sw.hasAttribute('data-sl-save')) {
      var data = { key: sw.getAttribute('data-key') || '', value: on ? 1 : 0 };
      Array.prototype.forEach.call(sw.attributes, function (at) { if (at.name.indexOf('data-p-') === 0) data[at.name.slice(7)] = at.value; });
      sw.setAttribute('aria-busy', 'true');
      function rollback(msg) { sw.classList.toggle('on', !on); sw.setAttribute('aria-checked', String(!on)); if (inp) inp.value = on ? '0' : '1'; SL.toast(msg || 'Not saved', true); }
      SL.post(sw.getAttribute('data-sl-save'), data).then(function (j) { var ok = j.success === true || j.status === 'success'; if (!ok) rollback(j.message); else if (j.message) SL.toast(j.message); })
        .catch(function (err) { rollback(err.message || 'Network error. Please try again.'); }).finally(function () { sw.removeAttribute('aria-busy'); });
    }
  });
  document.querySelectorAll('.sl-toggle[role="switch"]').forEach(function (sw) { if (sw.hasAttribute('data-static')) { sw.setAttribute('aria-disabled', 'true'); sw.tabIndex = -1; } else if (!sw.hasAttribute('tabindex')) sw.tabIndex = 0; });
  document.addEventListener('keydown', function (e) { if ((e.key === ' ' || e.key === 'Enter') && e.target.matches('.sl-toggle[role="switch"]')) { e.preventDefault(); e.target.click(); } });

  /* ---------- copy to clipboard ---------- */
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-sl-copy]');
    if (!c) return; e.preventDefault();
    if (!navigator.clipboard) { SL.toast('Clipboard access is unavailable in this browser.', true); return; }
    navigator.clipboard.writeText(c.getAttribute('data-sl-copy')).then(function () { SL.toast('Copied'); }).catch(function () { SL.toast('Could not copy. Please select and copy the text.', true); });
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

  /* Scrolling tables must also work without a pointer. */
  function nameTables() {
    document.querySelectorAll('.sl-tw').forEach(function (el) {
      if (el.scrollWidth > el.clientWidth + 1) {
        el.tabIndex = 0; el.setAttribute('role', 'region');
        if (!el.hasAttribute('aria-label')) el.setAttribute('aria-label', 'Scrollable table');
      } else { el.removeAttribute('tabindex'); el.removeAttribute('role'); }
    });
  }
  nameTables(); window.addEventListener('resize', nameTables);
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

// Keep native details keyboard behavior while animating sidebar groups both ways.
(function () {
  document.querySelectorAll('.sl-nav-group').forEach(function (group) {
    var summary = group.querySelector('summary'), animation = null, expanded = group.open;
    summary.addEventListener('click', function (event) {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !group.animate) return;
      event.preventDefault();
      var from = group.getBoundingClientRect().height;
      expanded = animation ? !expanded : !group.open;
      if (animation) { animation.cancel(); animation = null; }
      group.open = true;
      var to = expanded ? group.getBoundingClientRect().height : summary.getBoundingClientRect().height;
      group.style.overflow = 'hidden';
      animation = group.animate([{ height: from + 'px' }, { height: to + 'px' }], { duration: 240, easing: 'cubic-bezier(.2,.8,.2,1)' });
      animation.onfinish = function () {
        group.open = expanded;
        group.style.overflow = '';
        animation = null;
      };
    });
  });
})();
