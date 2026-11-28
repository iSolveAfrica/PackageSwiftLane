/* Customers page: every statistic draws its detail in place.
 * Tiles, growth-chart months, Ship by groups and customer-status rows open one insight panel
 * (#cx-insight) right where they were clicked: under the clicked tile's row, or under the charts.
 * The detail comes from ajax/sl/customers_stats_ajax.php (read-only, computed on demand, never
 * cached here). "Show all N in the table" filters the list below without a page reload.
 * The panel is kept in the URL hash (#stat=active, #stat=month-2026-03) so a reload reopens it. */
(function () {
  'use strict';
  var panel = document.getElementById('cx-insight');
  if (!panel) return;
  var inner = panel.querySelector('.cx-ins-pad');
  /* The statistic opens as a centred pop-up over a blurred page (not a drop-down inside the page). */
  var modal = document.createElement('div');
  modal.className = 'cx-modal';
  modal.hidden = true;
  document.body.appendChild(modal);
  modal.appendChild(panel);
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-modal', 'true');
  modal.addEventListener('click', function (e) { if (e.target === modal) close(true); });
  var endpoint = panel.getAttribute('data-endpoint');
  var kpis = document.getElementById('cx-kpis');
  var charts = document.getElementById('cx-charts');
  var reduced = matchMedia('(prefers-reduced-motion: reduce)');
  var KEYS = ['total', 'active', 'new', 'phone', 'idv', 'repeat', 'parcels', 'due', 'quiet', 'csat', 'blocked', 'month', 'air', 'both', 'sea'];
  var KICKER = { month: 'Customer growth', air: 'Ship by', both: 'Ship by', sea: 'Ship by', blocked: 'Customer status' };
  var cur = null; // { key, ym, trigger, anchor }
  var ctrl = null, seq = 0, closeTimer = null;

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function icon(name) { var i = el('i', 'ic ic-sm ' + name); i.setAttribute('aria-hidden', 'true'); return i; }
  function idOf(key, ym) { return key === 'month' ? 'month-' + ym : key; }
  function sameStat(node, key, ym) {
    return node.getAttribute('data-cx-stat') === key && (key !== 'month' || node.getAttribute('data-cx-ym') === ym);
  }
  function triggers() { return Array.prototype.slice.call(document.querySelectorAll('[data-cx-stat]')); }
  function smooth() { return reduced.matches ? 'auto' : 'smooth'; }
  function topOffset() {
    var t = document.querySelector('.sl-top'), pos = t && getComputedStyle(t).position;
    return t && (pos === 'sticky' || pos === 'fixed') ? Math.max(0, t.getBoundingClientRect().bottom) + 12 : 12;
  }
  function fromChart(t) { return !!t && !t.closest('.cx-k') && charts.contains(t); }
  function months() { return triggers().filter(function (n) { return n.getAttribute('data-cx-stat') === 'month'; }).map(function (n) { return n.getAttribute('data-cx-ym'); }); }
  function bottomOffset() {
    var nav = document.querySelector('.sl-mobile-nav');
    if (nav && getComputedStyle(nav).position === 'fixed' && nav.offsetHeight) return nav.offsetHeight + 12;
    return 12;
  }

  /* ---------- triggers ---------- */
  triggers().forEach(function (t) {
    if (t.tagName === 'A') t.setAttribute('role', 'button');
    // The growth chart's own keyboard handler (chart_interactions.js) pins its tooltip on
    // Enter/Space; on a month bar those keys open the month instead.
    t.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.stopPropagation();
        if (e.key === ' ' && t.tagName === 'A') { e.preventDefault(); t.click(); }
      }
    });
  });
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-cx-stat]');
    if (!t || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    var key = t.getAttribute('data-cx-stat'), ym = t.getAttribute('data-cx-ym');
    if (cur && sameStat(t, cur.key, cur.ym) && cur.trigger === t && !panel.hidden) { close(true); return; }
    open(key, ym, t, { keyboard: e.detail === 0, scroll: true });
  });
  panel.addEventListener('click', function (e) {
    if (e.target.closest('[data-cx-close]')) { e.preventDefault(); close(true); return; }
    var all = e.target.closest('[data-cx-all]');
    if (all && e.button === 0 && !e.metaKey && !e.ctrlKey && !e.shiftKey) { e.preventDefault(); loadList(all.getAttribute('data-cx-all'), { scroll: true, focus: true }); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' || panel.hidden) return;
    var tip = document.getElementById('sl-chart-tooltip');
    if ((tip && !tip.hidden) || document.querySelector('.sl-dialog:not([hidden])')) return;
    close(true);
  });

  /* ---------- placement: right where the click happened ---------- */
  function rowEnd(tile) {
    var tiles = Array.prototype.slice.call(kpis.querySelectorAll('.cx-k')), top = tile.getBoundingClientRect().top, last = tile;
    tiles.forEach(function (k) { if (Math.abs(k.getBoundingClientRect().top - top) < 4) last = k; });
    return last;
  }
  function place(trigger, key) {
    document.querySelectorAll('.cx-k.is-anchor').forEach(function (k) { k.classList.remove('is-anchor'); });
    var tile = trigger && trigger.closest('.cx-k');
    if (!trigger || (!tile && !charts.contains(trigger))) tile = kpis && kpis.querySelector('.cx-k[data-cx-stat="' + key + '"]');
    if (tile) { tile.classList.add('is-anchor'); return tile; }
    return null;
  }
  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () { if (cur && !panel.hidden) { cur.anchor = place(cur.trigger || cur.anchor, cur.key); fit(); } }, 120);
  });

  // Only the control that opened the panel shows as open (a status row and a tile can share a statistic).
  function mark() {
    triggers().forEach(function (t) {
      var on = !!cur && t === cur.trigger;
      t.classList.toggle('is-on', on);
      t.setAttribute('aria-expanded', String(on));
    });
  }

  /* ---------- open / close ---------- */
  function open(key, ym, trigger, opt) {
    opt = opt || {};
    if (KEYS.indexOf(key) < 0 || (key === 'month' && months().indexOf(ym) < 0)) return;
    clearTimeout(closeTimer); modal.classList.remove('is-closing');
    var wasOpen = !panel.hidden && panel.classList.contains('is-open');
    var before = panel.previousElementSibling;
    trigger = trigger || triggers().filter(function (n) { return sameStat(n, key, ym); })[0] || null;
    cur = { key: key, ym: key === 'month' ? ym : null, trigger: trigger };
    cur.anchor = place(trigger, key);
    var moved = panel.previousElementSibling !== before;
    mark();
    setHash('#stat=' + idOf(key, cur.ym));
    if (!wasOpen || moved) {
      panel.classList.remove('is-open');
      modal.hidden = false;
      document.documentElement.classList.add('cx-modal-on');
      panel.hidden = false;
      skeleton(key);
      void panel.offsetHeight; // start the open transition from closed
      panel.classList.add('is-open');
    } else {
      panel.classList.add('is-loading');
    }
    panel.setAttribute('aria-busy', 'true');
    load(key, cur.ym, opt);
  }
  function close(restoreFocus) {
    if (panel.hidden) return;
    if (ctrl) ctrl.abort();
    var back = cur && cur.trigger;
    cur = null;
    mark();
    document.querySelectorAll('.cx-k.is-anchor').forEach(function (k) { k.classList.remove('is-anchor'); });
    setHash('');
    panel.classList.remove('is-open', 'is-loading');
    modal.classList.add('is-closing');
    closeTimer = setTimeout(function () { panel.hidden = true; modal.hidden = true; modal.classList.remove('is-closing'); document.documentElement.classList.remove('cx-modal-on'); }, reduced.matches ? 0 : 220);
    if (restoreFocus && back && back.isConnected) back.focus({ preventScroll: true });
  }
  /* The open statistic lives in the hash; a ?stat= link that opened it is dropped so closing is final. */
  function setHash(hash) {
    var q = new URLSearchParams(location.search);
    if (q.has('stat')) {
      q.delete('stat');
      if (q.get('filter') !== 'month') q.delete('ym');
    }
    var qs = q.toString();
    try { history.replaceState(history.state, '', location.pathname + (qs ? '?' + qs : '') + hash); } catch (e) { /* file:// or sandboxed */ }
  }

  /* ---------- data ---------- */
  function load(key, ym, opt) {
    if (ctrl) ctrl.abort();
    ctrl = 'AbortController' in window ? new AbortController() : null;
    var mine = ++seq;
    var url = endpoint + '?stat=' + encodeURIComponent(key) + (ym ? '&ym=' + encodeURIComponent(ym) : '');
    fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: ctrl ? ctrl.signal : undefined })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (j) {
          if (!r.ok || !j.success) throw new Error(j.message || (r.status === 401 ? 'Your session ended. Sign in again.' : 'Could not load this statistic.'));
          return j;
        });
      })
      .then(function (j) { if (mine === seq) { render(j); done(opt); } })
      .catch(function (err) { if (mine === seq && err.name !== 'AbortError') { failed(err.message, key, ym); done(opt); } });
  }
  function done(opt) {
    panel.classList.remove('is-loading');
    panel.removeAttribute('aria-busy');
    var t = panel.querySelector('.cx-ins-t');
    if (opt.keyboard && t) t.focus({ preventScroll: true });
    if (opt.scroll) reveal();
  }
  /* Bring the panel into view. From a tile: keep the whole tile on screen (it says what was
     clicked) and show as much of the panel under it as fits. From a chart: show the whole panel,
     or its top under the bar when it is taller than the window; its header names what was clicked. */
  function reveal() {
    modal.scrollTop = 0;
    return; // the pop-up is centred on screen; nothing to scroll into view
    var r = panel.getBoundingClientRect(), top = topOffset(), h = inner.scrollHeight + 2;
    var room = innerHeight - bottomOffset(), dy = 0;
    if (r.top < top) dy = r.top - top;
    else if (r.top + h > room) {
      dy = Math.min(r.top + h - room, r.top - top);
      var from = cur && cur.anchor;
      if (from && from.isConnected) {
        var fr = from.getBoundingClientRect();
        dy = Math.min(dy, Math.max(0, fr.top - top));
      }
    }
    if (Math.abs(dy) > 4) window.scrollBy({ top: dy, behavior: smooth() });
  }

  /* Value labels that would run into their neighbours are hidden (the tooltip still has them); month
     names shrink to one letter when even they do not fit. */
  function fit() {
    panel.querySelectorAll('.cx-ich').forEach(function (box) {
      box.classList.remove('tight', 'tight-l');
      var cols = box.querySelectorAll('.cx-col');
      if (!cols.length) return;
      var room = cols[0].getBoundingClientRect().width + parseFloat(getComputedStyle(box).columnGap || 0) - 4;
      var wide = function (sel) { return Array.prototype.some.call(box.querySelectorAll(sel), function (n) { return n.getBoundingClientRect().width > room; }); };
      if (wide('.cx-col>span')) box.classList.add('tight');
      if (wide('.cx-l')) box.classList.add('tight-l');
    });
  }

  /* ---------- rendering ---------- */
  function header(kicker, title, lede, links) {
    var h = el('div', 'cx-ins-h');
    var t = el('div', 'cx-ins-ht');
    t.appendChild(el('span', 'cx-ins-k', kicker));
    var h2 = el('h2', 'cx-ins-t', title); h2.id = 'cx-ins-title'; h2.tabIndex = -1; t.appendChild(h2);
    if (lede) t.appendChild(el('p', 'cx-ins-lede', lede));
    h.appendChild(t);
    var x = el('div', 'cx-ins-hx');
    (links || []).forEach(function (l) {
      var a = el('a', 'sl-btn ghost sm', l.label); a.href = l.href; a.appendChild(icon('i-ext')); x.appendChild(a);
    });
    var close = el('button', 'sl-ib sm cx-ins-x'); close.type = 'button'; close.setAttribute('data-cx-close', '');
    close.setAttribute('aria-label', 'Close the ' + title + ' details'); close.appendChild(icon('i-x'));
    x.appendChild(close);
    h.appendChild(x);
    return h;
  }
  function guessTitle(t) {
    if (!t) return 'Loading…';
    var s = t.querySelector('small, .grow');
    if (s) return s.textContent.trim();
    var l = t.getAttribute('aria-label');
    if (l) return l.split(':')[0];
    var f = t.querySelector('span');
    return f ? f.textContent.trim() : 'Loading…';
  }
  function kicker(key) { return KICKER[key] || (cur && cur.trigger && cur.trigger.closest('.cx-st2') ? 'Customer status' : 'Statistic'); }
  function skeleton(key) {
    inner.replaceChildren(header(kicker(key), guessTitle(cur && cur.trigger), '', []));
    var body = el('div', 'cx-ins-skel');
    ['f', 'f', 'f', 'f', 'c', 'l'].forEach(function (k) { body.appendChild(el('div', 'sl-skeleton cx-sk-' + k)); });
    inner.appendChild(body);
  }
  function failed(msg, key, ym) {
    inner.replaceChildren(header(kicker(key), 'Not available', '', []));
    var box = el('div', 'cx-ins-err');
    box.appendChild(el('p', '', msg));
    var retry = el('button', 'sl-btn ghost sm', 'Try again'); retry.type = 'button';
    retry.addEventListener('click', function () { panel.setAttribute('aria-busy', 'true'); panel.classList.add('is-loading'); load(key, ym, {}); });
    box.appendChild(retry);
    inner.appendChild(box);
    announce(msg);
  }
  function announce(text) {
    var live = document.getElementById('cx-ins-live');
    if (!live) { live = el('span', 'sl-chart-sr'); live.id = 'cx-ins-live'; live.setAttribute('role', 'status'); panel.after(live); }
    live.textContent = text;
  }
  function chart(c) {
    var wrap = el('div', 'cx-ins-chart');
    var head = el('div', 'cx-ins-ct');
    head.appendChild(el('b', 'fw5', c.title));
    if (c.note) head.appendChild(el('span', 'mut', c.note));
    wrap.appendChild(head);
    var nums = c.values.map(function (v) { return v == null ? 0 : Number(v); });
    var peak = Math.max.apply(null, nums.concat([0])) || 1;
    var box = el('div', 'cx-ch cx-ich' + (c.labels.length > 7 ? ' many' : ''));
    // Tooltip values: whole numbers as numbers, everything else exactly as the server formatted it.
    box.setAttribute('data-sl-chart', JSON.stringify({
      title: c.title, labels: c.full || c.labels,
      series: [{ name: c.name, values: c.fmt === 'num' ? c.values : c.values.map(function (v, i) { return v == null ? null : c.display[i]; }) }]
    }));
    box.setAttribute('aria-label', c.title);
    c.labels.forEach(function (label, i) {
      var col = el('div', 'cx-col' + (i === c.highlight ? ' now' : '') + (c.values[i] == null ? ' none' : ''));
      col.appendChild(el('span', 'mono', c.display[i]));
      var bar = el('i');
      bar.style.height = (c.values[i] == null ? 0 : Math.max(2, Math.round(nums[i] / peak * 100))) + '%';
      bar.style.animationDelay = (reduced.matches ? 0 : i * 28) + 'ms';
      col.appendChild(bar);
      var b = el('b');
      b.appendChild(el('span', 'cx-l', label));
      b.appendChild(el('span', 'cx-s', /^[A-Z][a-z]{2}$/.test(label) ? label.charAt(0) : label)); // "Mar" -> "M" on phones
      col.appendChild(b);
      box.appendChild(col);
    });
    wrap.appendChild(box);
    return wrap;
  }
  function figures(list) {
    var g = el('div', 'cx-ins-figs');
    list.forEach(function (f) {
      var d = el('div', 'cx-fig');
      d.appendChild(el('small', '', f.label));
      d.appendChild(el('b', '', f.value));
      if (f.sub) d.appendChild(el('small', 'mut', f.sub));
      g.appendChild(d);
    });
    return g;
  }
  function breakdowns(list) {
    var g = el('div', 'cx-ins-bks');
    list.forEach(function (bk) {
      var box = el('div', 'cx-bk');
      box.appendChild(el('h3', '', bk.title));
      bk.rows.forEach(function (r) {
        var row = el('div', 'cx-bk-r');
        row.appendChild(el('span', 'cx-bk-l', r.label));
        var track = el('i', 'cx-bk-t'), fill = el('span'); fill.style.width = Math.max(3, r.w) + '%'; track.appendChild(fill); row.appendChild(track);
        row.appendChild(el('b', 'mono fw5', r.value));
        box.appendChild(row);
      });
      g.appendChild(box);
    });
    return g;
  }
  function listHref(c) {
    return 'customers_list.php' + (c.filter && c.filter !== 'all' ? '?filter=' + encodeURIComponent(c.filter) + (c.ym ? '&ym=' + encodeURIComponent(c.ym) : '') : '');
  }
  function customers(c) {
    var side = el('div', 'cx-ins-side');
    var h = el('div', 'cx-ins-lh');
    h.appendChild(el('h3', '', c.title));
    h.appendChild(el('span', 'mut', c.total > c.rows.length ? c.rows.length + ' of ' + c.total.toLocaleString() : c.total.toLocaleString() + ' ' + (c.total === 1 ? 'customer' : 'customers')));
    side.appendChild(h);
    if (!c.rows.length) {
      side.appendChild(el('p', 'cx-ins-none', 'No customers match this yet.'));
      return side;
    }
    var ol = el('ol', 'cx-ins-list');
    c.rows.forEach(function (r) {
      var li = el('li'), a = el('a', 'cx-ins-c'); a.href = r.url;
      a.appendChild(el('span', 'sl-av sm', r.ini));
      var who = el('span', 'cx-ins-who');
      who.appendChild(el('b', 'fw5', r.name));
      who.appendChild(el('span', 'mut', r.locker + ' · ' + r.city));
      a.appendChild(who);
      var meta = el('span', 'cx-ins-meta');
      meta.appendChild(el('span', 'cx-ins-p', r.parcels + (r.parcels === 1 ? ' parcel' : ' parcels')));
      meta.appendChild(el('span', 'mut', r.note || r.last));
      a.appendChild(meta);
      li.appendChild(a);
      ol.appendChild(li);
    });
    side.appendChild(ol);
    var all = el('a', 'sl-btn ghost sm cx-ins-all');
    all.href = listHref(c) + '#cx-list';
    all.setAttribute('data-cx-all', listHref(c));
    all.appendChild(document.createTextNode(c.total > c.rows.length ? 'Show all ' + c.total.toLocaleString() + ' in the table' : 'Show them in the table'));
    all.appendChild(icon('i-down'));
    side.appendChild(all);
    return side;
  }
  function render(j) {
    var key = j.key;
    var title = j.title;
    var frag = document.createDocumentFragment();
    frag.appendChild(header(kicker(key), title, j.lede, j.links));
    if (j.figures && j.figures.length) frag.appendChild(figures(j.figures));
    var body = el('div', 'cx-ins-body');
    var main = el('div', 'cx-ins-main');
    main.appendChild(chart(j.chart));
    if (j.breakdowns && j.breakdowns.length) main.appendChild(breakdowns(j.breakdowns));
    body.appendChild(main);
    body.appendChild(customers(j.customers));
    frag.appendChild(body);
    inner.replaceChildren(frag);
    fit();
    announce(title + ' details loaded');
    if (window.SL && SL.initCharts) SL.initCharts();
    else document.addEventListener('DOMContentLoaded', function () { if (window.SL && SL.initCharts) SL.initCharts(); }, { once: true });
  }

  /* ---------- the list: filter and page in place ---------- */
  function loadList(url, opt) {
    opt = opt || {};
    var card = document.getElementById('cx-list');
    if (!card) { location.href = url + '#cx-list'; return; }
    var focusName = document.activeElement && card.contains(document.activeElement) ? document.activeElement.getAttribute('name') : null;
    card.classList.add('is-loading'); card.setAttribute('aria-busy', 'true');
    var u = new URL(url, location.href);
    u.searchParams.set('partial', 'list');
    fetch(u.toString(), { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'text/html' } })
      .then(function (r) { if (!r.ok) throw new Error(String(r.status)); return r.text(); })
      .then(function (html) {
        var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('cx-list');
        if (!fresh) throw new Error('list');
        fresh = document.importNode(fresh, true);
        card.replaceWith(fresh);
        u.searchParams.delete('partial');
        try { history.replaceState(history.state, '', u.pathname + u.search + location.hash); } catch (e) { /* ignore */ }
        window.dispatchEvent(new Event('resize'));
        var target = focusName && fresh.querySelector('[name="' + focusName + '"]');
        if (opt.scroll || fresh.getBoundingClientRect().top < topOffset()) {
          window.scrollTo({ top: fresh.getBoundingClientRect().top + scrollY - topOffset(), behavior: smooth() });
        }
        (target || fresh).focus({ preventScroll: true });
        if (opt.focus) { fresh.classList.remove('cx-flash'); void fresh.offsetWidth; fresh.classList.add('cx-flash'); }
      })
      .catch(function () { location.href = u.pathname + u.search.replace(/([?&])partial=list&?/, '$1').replace(/[?&]$/, '') + '#cx-list'; });
  }
  function isListLink(a) {
    if (!a || a.hasAttribute('data-sl-action') || a.target) return false;
    var u = new URL(a.href, location.href);
    return u.origin === location.origin && /customers_list\.php$/.test(u.pathname) && !u.searchParams.has('export');
  }
  document.addEventListener('click', function (e) {
    var c = e.target.closest('a[data-cx-list]');
    if (!c || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    loadList(c.href.replace(/#.*$/, ''), { scroll: true, focus: true });
  });
  document.addEventListener('click', function (e) {
    var a = e.target.closest('#cx-list a[href]');
    if (!a || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || !isListLink(a)) return;
    e.preventDefault();
    loadList(a.href, {});
  });
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.id !== 'cx-filter') return;
    e.preventDefault();
    var p = new URLSearchParams();
    new FormData(f).forEach(function (v, k) { if (v !== '' && !(k === 'filter' && v === 'all')) p.append(k, v); });
    var qs = p.toString();
    loadList('customers_list.php' + (qs ? '?' + qs : ''), {});
  });

  /* ---------- the growth chart: one keyboard path, through its month buttons ----------
     chart_interactions.js makes the whole chart a tab stop and renames it; the month buttons already
     carry the interaction (hover still shows the tooltip), so the chart keeps its own name and is
     not an extra stop. Arrow keys move between months. */
  function growthChart() {
    var root = document.querySelector('#cx-growth .cx-ch');
    if (!root) return;
    var label = 'New and active customers, last 12 months. Choose a month for its detail.';
    if (root.dataset.chartReady) root.tabIndex = -1;
    root.setAttribute('aria-label', label);
    if (root.dataset.cxKeys) return;
    root.dataset.cxKeys = '1';
    // Keep the descriptive name when the tooltip code rewrites it.
    if ('MutationObserver' in window) new MutationObserver(function () { if (root.getAttribute('aria-label') !== label) root.setAttribute('aria-label', label); if (root.dataset.chartReady && root.tabIndex !== -1) root.tabIndex = -1; }).observe(root, { attributes: true, attributeFilter: ['aria-label', 'tabindex', 'data-chart-ready'] });
    root.addEventListener('keydown', function (e) {
      var b = e.target.closest('.cx-col');
      if (!b || ['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(e.key) < 0) return;
      var all = Array.prototype.slice.call(root.querySelectorAll('.cx-col')), i = all.indexOf(b);
      i = e.key === 'Home' ? 0 : e.key === 'End' ? all.length - 1 : i + (e.key === 'ArrowLeft' ? -1 : 1);
      if (i < 0 || i >= all.length) return;
      e.preventDefault(); e.stopPropagation();
      all[i].focus();
    }, true);
  }
  growthChart();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', growthChart);
  window.addEventListener('load', growthChart);

  /* A link or the address bar changing the hash opens (or closes) that statistic. */
  window.addEventListener('hashchange', function () {
    var m = /^#stat=([a-z]+)(?:-(\d{4}-\d{2}))?$/.exec(location.hash);
    if (!m) { if (!panel.hidden && !location.hash) close(false); return; }
    var t = triggers().filter(function (n) { return sameStat(n, m[1], m[2]); })[0];
    if (t && KEYS.indexOf(m[1]) >= 0) open(m[1], m[2], t, { scroll: true });
    else setHash(panel.hidden || !cur ? '' : '#stat=' + idOf(cur.key, cur.ym));
  });

  /* ---------- start: reopen what the URL names; a filtered list link lands on the table ---------- */
  (function () {
    var m = /^#stat=([a-z]+)(?:-(\d{4}-\d{2}))?$/.exec(location.hash);
    var q = new URLSearchParams(location.search);
    var key = m ? m[1] : q.get('stat'), ym = m ? m[2] : q.get('ym');
    var nav = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
    var fresh = !nav || nav.type === 'navigate';
    if (key) {
      var t = triggers().filter(function (n) { return sameStat(n, key, ym); })[0] || null;
      // Only what this page can show: a known statistic with its control (a month among the 12 bars).
      if (KEYS.indexOf(key) >= 0 && t) { open(key, ym, t, { scroll: fresh }); return; }
      setHash('');
    }
    if (fresh && !location.hash && ['filter', 'q', 'city', 'page'].some(function (k) { var v = q.get(k); return v && v !== 'all' && v !== '1'; })) {
      var list = document.getElementById('cx-list');
      if (list) window.scrollTo({ top: list.getBoundingClientRect().top + scrollY - topOffset(), behavior: 'auto' });
    }
  })();
})();
