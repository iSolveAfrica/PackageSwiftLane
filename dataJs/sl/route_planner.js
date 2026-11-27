/* Route planner (admin): views/sl/pages/RoutePlanner.php.
 * Auto-allocate, drag/move stops between riders (HTML5 drag + "Move to…" dialog for touch/keyboard), lock,
 * confirm/discard, re-optimise, add/remove stops, live polling (op=state every 20 s while visible), maps.
 * All writes go to ajax/sl/route_planner_ajax.php through SL.post (CSRF). After a write the page body is
 * re-fetched and swapped in place ([data-rp-root]) so scroll position and the URL stay put. */
(function () {
  'use strict';
  if (!window.SL) return;
  var cfg = {}, maps = [], pollTimer = null, dragUnit = null, moveUnit = null, busy = false;
  var LOCK_KEY = 'sl.rp.lock';
  function setBusy(v) { busy = v; if (v) document.documentElement.setAttribute('data-rp-busy', ''); else document.documentElement.removeAttribute('data-rp-busy'); }

  function root() { return document.querySelector('[data-rp-root]'); }
  function readCfg() {
    var el = document.getElementById('rp-data');
    try { cfg = el ? JSON.parse(el.textContent || '{}') : {}; } catch (e) { cfg = {}; }
    cfg.ajax = cfg.ajax || 'ajax/sl/route_planner_ajax.php';
  }
  function ok(j) { return !!j && (j.success === true || j.status === 'success'); }
  function call(op, data) {
    var fd = new FormData();
    fd.append('op', op);
    Object.keys(data || {}).forEach(function (k) {
      var v = data[k];
      if (Array.isArray(v)) v.forEach(function (x) { fd.append(k + '[]', x); });
      else if (v !== undefined && v !== null) fd.append(k, v);
    });
    return SL.post(cfg.ajax, fd);
  }
  function fail(j, fallback) { SL.toast((j && (j.message || j.error)) || fallback || 'Something went wrong', true); }
  function storeGet(k) { try { return JSON.parse(sessionStorage.getItem(k) || 'null'); } catch (e) { return null; } }
  function storeSet(k, v) { try { sessionStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode */ } }

  /* ---------- in-place refresh ---------- */
  function refresh(url) {
    url = url || location.href;
    return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.text(); }).then(function (html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var fresh = doc.querySelector('[data-rp-root]'), cur = root();
      if (!fresh || !cur) { location.href = url; return; }
      destroyMaps();
      cur.replaceWith(document.importNode(fresh, true));
      if (url !== location.href) history.replaceState(null, '', url);
      init();
    }).catch(function () { location.href = url; });
  }
  function urlWith(params) {
    var u = new URL(location.href);
    Object.keys(params).forEach(function (k) { if (params[k] === null || params[k] === '') u.searchParams.delete(k); else u.searchParams.set(k, params[k]); });
    return u.pathname.split('/').pop() + u.search;
  }

  /* ---------- maps ---------- */
  function destroyMaps() { maps.forEach(function (m) { try { m && m.destroy && m.destroy(); } catch (e) { /* ignore */ } }); maps = []; }
  function mapData(kind) {
    if (kind === 'sel') return cfg.sel && cfg.sel.map;
    if (kind === 'live') {
      var m = cfg.map || {}, stops = [];
      (m.routes || []).forEach(function (r) { (r.stops || []).forEach(function (s) { stops.push(Object.assign({ color: r.color, route: r.driver_id }, s)); }); });
      return { depot: m.depot, stops: stops, riders: m.riders || [], path: null, routes: (m.routes || []).map(function (r) { return { color: r.color, name: r.name, stops: r.stops || [], path: null }; }) };
    }
    return null;
  }
  function mountMaps() {
    document.querySelectorAll('[data-rp-map]').forEach(function (el) {
      var kind = el.getAttribute('data-rp-map'), data = mapData(kind);
      if (!data || (!(data.stops || []).length && !(data.riders || []).length)) return;
      var multi = data.routes && data.routes.length;
      var opts = { depot: data.depot, stops: multi ? [] : (data.stops || []), routes: multi ? data.routes : undefined, riders: data.riders || [], path: data.path || null, provider: 'auto', interactive: true,
        onStopClick: function (id) { focusStop(id); } };
      if (kind === 'sel' && cfg.sel) opts.color = (cfg.riders || []).filter(function (r) { return r.id === cfg.sel.id; }).map(function (r) { return r.color; })[0];
      var empty = el.querySelector('.rp-map-empty'); if (empty) empty.remove();
      var handle = null;
      if (window.SLRouteMap && typeof window.SLRouteMap.mount === 'function') {
        try { handle = window.SLRouteMap.mount(el, opts); } catch (e) { handle = null; }
      }
      if (!handle) handle = schematic(el, Object.assign({}, opts, { stops: data.stops || [] }), data.routes);
      el._rpMap = handle; el._rpOpts = opts;
      maps.push(handle);
    });
  }
  function focusStop(id) {
    var el = document.querySelector('.rp-card [data-rp-unit="' + id + '"]') || document.querySelector('[data-rp-remove-stop="' + id + '"]');
    if (el) { el = el.closest('.rp-u') || el; el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.classList.add('flash'); setTimeout(function () { el.classList.remove('flash'); }, 1200); }
  }
  /* Minimal SVG schematic, only used when assets/swiftlane/route-map.js is not installed. */
  function schematic(el, o, routes) {
    var pts = [];
    if (o.depot) pts.push(o.depot);
    (o.stops || []).forEach(function (s) { if (s.lat != null) pts.push(s); });
    (o.riders || []).forEach(function (r) { if (r.lat != null) pts.push(r); });
    if (!pts.length) return null;
    var lat0 = Math.min.apply(null, pts.map(function (p) { return +p.lat; })), lat1 = Math.max.apply(null, pts.map(function (p) { return +p.lat; }));
    var lng0 = Math.min.apply(null, pts.map(function (p) { return +p.lng; })), lng1 = Math.max.apply(null, pts.map(function (p) { return +p.lng; }));
    var k = Math.cos(((lat0 + lat1) / 2) * Math.PI / 180), W = 1000, H = 600, pad = 50;
    var spanX = Math.max((lng1 - lng0) * k, 0.005), spanY = Math.max(lat1 - lat0, 0.005), s = Math.min((W - 2 * pad) / spanX, (H - 2 * pad) / spanY);
    var ox = (W - spanX * s) / 2, oy = (H - spanY * s) / 2;
    function X(p) { return ox + (p.lng - lng0) * k * s; }
    function Y(p) { return H - (oy + (p.lat - lat0) * s); }
    var NS = 'http://www.w3.org/2000/svg', svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H); svg.setAttribute('class', 'rp-sch'); svg.setAttribute('aria-hidden', 'true');
    function add(tag, attrs, text) { var n = document.createElementNS(NS, tag); Object.keys(attrs).forEach(function (a) { n.setAttribute(a, attrs[a]); }); if (text != null) n.textContent = text; svg.appendChild(n); return n; }
    for (var g = 1; g < 6; g++) { add('line', { x1: 0, x2: W, y1: g * H / 6, y2: g * H / 6, stroke: 'currentColor', 'stroke-opacity': '.07' }); add('line', { y1: 0, y2: H, x1: g * W / 6, x2: g * W / 6, stroke: 'currentColor', 'stroke-opacity': '.07' }); }
    var groups = {};
    (o.stops || []).forEach(function (st) { if (st.lat == null) return; var key = st.route || 'one'; (groups[key] = groups[key] || []).push(st); });
    Object.keys(groups).forEach(function (key) {
      var list = groups[key].slice().sort(function (a, b) { return (a.seq || 0) - (b.seq || 0); }), col = list[0].color || o.color || '#2563eb';
      var d = (o.depot ? 'M' + X(o.depot) + ' ' + Y(o.depot) + ' L' : 'M') + list.map(function (p) { return X(p) + ' ' + Y(p); }).join(' L');
      add('path', { d: d, fill: 'none', stroke: col, 'stroke-width': 3, 'stroke-opacity': '.7', 'stroke-dasharray': '8 6', 'stroke-linejoin': 'round' });
      list.forEach(function (p) {
        var done = /delivered|failed|returned/.test(p.status || '');
        var c = add('circle', { cx: X(p), cy: Y(p), r: 14, fill: done ? '#9ca3af' : col, stroke: '#fff', 'stroke-width': 2, style: 'cursor:pointer' });
        c.addEventListener('click', function () { if (o.onStopClick) o.onStopClick(p.id); });
        add('text', { x: X(p), y: Y(p) + 4.5, 'text-anchor': 'middle', 'font-size': 13, 'font-weight': 600, fill: '#fff', 'pointer-events': 'none' }, String(p.seq || ''));
      });
    });
    if (o.depot) { add('rect', { x: X(o.depot) - 12, y: Y(o.depot) - 12, width: 24, height: 24, rx: 5, fill: '#1e1d1a' }); add('text', { x: X(o.depot) + 18, y: Y(o.depot) + 5, 'font-size': 14, fill: 'currentColor' }, o.depot.label || 'Start'); }
    (o.riders || []).forEach(function (r) { if (r.lat == null) return; add('circle', { cx: X(r), cy: Y(r), r: 9, fill: r.color || '#e08a3c', stroke: '#fff', 'stroke-width': 3 }); add('text', { x: X(r) + 13, y: Y(r) - 10, 'font-size': 13, fill: 'currentColor' }, r.name || ''); });
    el.appendChild(svg);
    var note = document.createElement('span'); note.className = 'rp-sch-note'; note.textContent = 'Schematic — straight lines between stops'; el.appendChild(note);
    return { update: function () {}, focus: function () {}, destroy: function () { svg.remove(); note.remove(); } };
  }

  /* ---------- filters ---------- */
  function filterRiders(group) {
    document.querySelectorAll('[data-rp-group]').forEach(function (c) { var on = c.getAttribute('data-rp-group') === group; c.classList.toggle('on', on); c.setAttribute('aria-pressed', String(on)); });
    document.querySelectorAll('[data-rp-grp]').forEach(function (g) { g.hidden = group !== 'all' && g.getAttribute('data-rp-grp') !== group; });
    storeSet('sl.rp.group', group);
  }
  function filterHold() {
    var q = ((document.querySelector('[data-rp-hold-q]') || {}).value || '').trim().toLowerCase();
    var area = (document.querySelector('[data-rp-hold-area]') || {}).value || '';
    var hand = (document.querySelector('[data-rp-hold-handling]') || {}).value || '';
    var shown = 0, all = 0;
    document.querySelectorAll('[data-rp-hold-list] .rp-u').forEach(function (u) {
      all++;
      var vis = (!q || (u.getAttribute('data-q') || '').indexOf(q) >= 0) && (!area || u.getAttribute('data-area') === area) && (!hand || (',' + (u.getAttribute('data-handling') || '') + ',').indexOf(',' + hand + ',') >= 0);
      u.hidden = !vis; if (vis) shown++;
    });
    var empty = document.querySelector('[data-rp-hold-empty]');
    if (empty && all) { empty.hidden = shown > 0; empty.textContent = 'Nothing matches these filters.'; }
  }

  /* ---------- in-app confirm (the #rp-ask dialog; resolves true on Continue) ---------- */
  function ask(title, msg, okLabel) {
    var dlg = document.getElementById('rp-ask');
    if (!dlg) return Promise.resolve(window.confirm(title + '\n\n' + msg));
    return new Promise(function (resolve) {
      dlg.querySelector('[data-rp-ask-title]').textContent = title;
      dlg.querySelector('[data-rp-ask-msg]').textContent = msg || '';
      var okBtn = dlg.querySelector('[data-rp-ask-ok]'); okBtn.textContent = okLabel || 'Continue';
      var done = false;
      function finish(v) { if (done) return; done = true; obs.disconnect(); okBtn.removeEventListener('click', onOk); dlg.hidden = true; resolve(v); }
      function onOk() { finish(true); }
      var obs = new MutationObserver(function () { if (dlg.hidden) finish(false); });
      obs.observe(dlg, { attributes: true, attributeFilter: ['hidden'] });
      okBtn.addEventListener('click', onOk);
      dlg.hidden = false;
      setTimeout(function () { okBtn.focus(); }, 30);
    });
  }

  /* ---------- plan actions ---------- */
  function localLocks() { return storeGet(LOCK_KEY) || []; }
  function move(unitId, to, force) {
    if (busy) return;
    if (!cfg.plan) { SL.toast('Run auto-allocate first: stops move between riders inside a draft plan.', true); return; }
    setBusy(true);
    return call('move', { plan_id: cfg.plan, request_id: unitId, to: to ? to : '', force: force ? 1 : 0 }).then(function (j) {
      if (!ok(j)) {
        // Only capacity (up to 110%) and rider status can be overridden; handling, size and locks cannot.
        if (j && j.blocked && j.can_force && !force) {
          var name = ((cfg.riders || []).filter(function (r) { return String(r.id) === String(to); })[0] || {}).name || 'This rider';
          setBusy(false);
          return ask(name + ': assign anyway?', (j.message || j.warning || '') + ' Assign the stop anyway?', 'Assign anyway').then(function (yes) { if (yes) return move(unitId, to, true); refresh(); });
        }
        fail(j, 'Could not move the stop'); if (j && j.blocked) refresh(); return;
      }
      var w = j.warning || (j.capacity_warning && (j.capacity_warning.message || j.capacity_warning));
      SL.toast(typeof w === 'string' && w ? w : (to ? 'Stop moved, route re-ordered' : 'Stop back in the hold'), !!(typeof w === 'string' && w));
      return refresh();
    }).catch(function (e) { SL.toast(e.message || 'Network error. Please try again.', true); }).finally(function () { setBusy(false); });
  }
  function lock(btn) {
    var id = parseInt(btn.getAttribute('data-rp-lock'), 10), on = btn.getAttribute('aria-pressed') !== 'true';
    function paint(state) { btn.classList.toggle('on', state); btn.setAttribute('aria-pressed', String(state)); var card = btn.closest('.rp-r'); if (card) card.classList.toggle('locked', state); }
    paint(on);
    if (!cfg.plan) {
      var l = localLocks().filter(function (x) { return x !== id; }); if (on) l.push(id); storeSet(LOCK_KEY, l);
      SL.toast(on ? 'Locked: auto-allocate will not give this rider new stops' : 'Unlocked'); return;
    }
    call('lock', { plan_id: cfg.plan, driver_id: id, locked: on ? 1 : 0 }).then(function (j) {
      if (!ok(j)) { paint(!on); fail(j, 'Could not change the lock'); return; }
      SL.toast(on ? 'Locked: re-running auto-allocate keeps this set' : 'Unlocked');
    }).catch(function (e) { paint(!on); SL.toast(e.message || 'Network error', true); });
  }
  function propose(form, btn) {
    var locks = localLocks();
    document.querySelectorAll('[data-rp-lock][aria-pressed="true"]').forEach(function (b) { var id = parseInt(b.getAttribute('data-rp-lock'), 10); if (locks.indexOf(id) < 0) locks.push(id); });
    var data = { start_id: form.elements.start_id ? form.elements.start_id.value : '', mode: form.elements.mode ? form.elements.mode.value : 'fill', lock: locks, date: cfg.date || '' };
    if (btn) { btn.classList.add('loading'); btn.disabled = true; }
    call('propose', data).then(function (j) {
      if (!ok(j)) { fail(j, 'Auto-allocate failed'); return; }
      var un = (j.unallocated || []).length, n = 0;
      Object.keys(j.riders || {}).forEach(function (k) { n += ((j.riders[k] || {}).units || []).length; });
      SL.toast('Proposal ready: ' + n + ' stops allocated' + (un ? ', ' + un + ' left in the hold' : ''));
      storeSet(LOCK_KEY, []);
      return refresh(urlWith({ tab: 'plan', plan: j.plan_id || (j.plan && j.plan.id) || '' }));
    }).catch(function (e) { SL.toast(e.message || 'Network error', true); }).finally(function () { if (btn) { btn.classList.remove('loading'); btn.disabled = false; } });
  }
  function confirmPlan(btn) {
    var planId = btn.getAttribute('data-plan'), box = document.querySelector('[data-rp-confirm-result]');
    var ack = document.querySelector('[data-rp-ack-over]');
    if (btn.hasAttribute('data-needs-ack') && ack && !ack.checked) { SL.toast('Tick the box to accept the overload first, or move some stops.', true); ack.focus(); return; }
    btn.classList.add('loading'); btn.disabled = true;
    call('confirm', { plan_id: planId, ack_over: ack && ack.checked ? 1 : 0 }).then(function (j) {
      var errors = (j && j.errors) || [];
      if (!ok(j) && !(j && j.assigned)) { fail(j, 'Could not confirm the plan'); setTimeout(function () { refresh(); }, 1800); return; }
      var msg = (j.assigned || 0) + ' stops assigned to riders' + (errors.length ? ', ' + errors.length + ' failed' : '');
      SL.toast(msg, !!errors.length);
      if (box) {
        box.hidden = false; box.textContent = '';
        var head = document.createElement('b'); head.className = 'fw5'; head.textContent = msg; box.appendChild(head);
        errors.forEach(function (e) { var s = document.createElement('span'); s.className = 'sm rp-reason'; s.textContent = '#' + (e.request_id || e[0] || '') + ': ' + (e.message || e[1] || 'failed'); box.appendChild(s); });
      }
      setTimeout(function () { refresh(urlWith({ plan: null, tab: 'plan' })); }, errors.length ? 2600 : 900);
    }).catch(function (e) { SL.toast(e.message || 'Network error', true); }).finally(function () { btn.classList.remove('loading'); btn.disabled = false; });
  }
  function simple(op, data, okMsg, after) {
    if (busy) return Promise.resolve();
    setBusy(true);
    return call(op, data).then(function (j) {
      if (!ok(j)) { fail(j); return; }
      SL.toast(j.message || okMsg);
      return refresh(after || location.href);
    }).catch(function (e) { SL.toast(e.message || 'Network error', true); }).finally(function () { setBusy(false); });
  }

  function addStop(routeId, reqId, force) {
    if (busy) return;
    setBusy(true);
    call('add_stop', { route_id: routeId, request_id: reqId, force: force ? 1 : 0 }).then(function (j) {
      if (ok(j)) { SL.toast(j.message || 'Stop added and route re-ordered'); return refresh(); }
      if (j && j.blocked && j.can_force && !force) {
        setBusy(false);
        return ask('Add it anyway?', (j.message || '') + ' Add the stop anyway?', 'Add anyway').then(function (yes) { if (yes) addStop(routeId, reqId, true); });
      }
      fail(j, 'Could not add the stop');
    }).catch(function (e) { SL.toast(e.message || 'Network error', true); }).finally(function () { setBusy(false); });
  }

  /* ---------- live polling ---------- */
  function applyState(st) {
    if (!st || !ok(st)) return;
    var k = st.kpis || (st.state && st.state.kpis) || {};
    var map = { ready: k.ready, onway: k.onway, delivered: k.delivered_today, failed: k.failed_today };
    Object.keys(map).forEach(function (key) { var el = document.querySelector('[data-rp-kpi="' + key + '"]'); if (el && map[key] != null) el.textContent = map[key]; });
    var riders = st.riders || (st.state && st.state.riders) || [];
    var pos = [];
    riders.forEach(function (r) {
      if (r.pos && r.pos.lat != null) pos.push({ id: r.id, name: r.name, lat: +r.pos.lat, lng: +r.pos.lng, status: r.status, color: ((cfg.riders || []).filter(function (x) { return x.id === r.id; })[0] || {}).color });
      var row = document.querySelector('[data-rp-live-rider="' + r.id + '"]'); if (!row) return;
      var p = r.progress || {}, bar = row.querySelector('[data-rp-live-prog]'), meta = row.querySelector('[data-rp-live-meta]');
      if (bar && p.total) bar.style.width = Math.round((p.done || 0) * 100 / p.total) + '%';
      if (meta) meta.textContent = (p.total ? (p.done || 0) + '/' + p.total + ' stops' : 'No route') + (r.next_eta ? ' · next ETA ' + String(r.next_eta).replace(/^.*[T ](\d\d:\d\d).*$/, '$1') : '');
    });
    var alerts = st.alerts || (st.state && st.state.alerts) || [], list = document.querySelector('[data-rp-alerts]');
    if (list) {
      list.textContent = '';
      alerts.forEach(function (a) {
        var d = document.createElement('div'); d.className = 'rp-alert a-' + String(a.type || 'alert').replace(/[^a-z_]/g, '');
        var b = document.createElement('b'); b.className = 'fw5 sm'; b.textContent = a.name || String(a.type || 'Alert').replace(/_/g, ' ');
        var s = document.createElement('span'); s.className = 'sm'; s.textContent = a.message || '';
        var c = document.createElement('span'); c.className = 'col grow'; c.appendChild(b); c.appendChild(s); d.appendChild(c); list.appendChild(d);
      });
      var n = document.querySelector('[data-rp-alert-n]'); if (n) n.textContent = alerts.length;
      var e = document.querySelector('[data-rp-alerts-empty]'); if (e) e.hidden = alerts.length > 0;
    }
    var stamp = document.querySelector('[data-rp-live-stamp]'); if (stamp) stamp.textContent = 'Updated ' + new Date().toTimeString().slice(0, 5);
    document.querySelectorAll('[data-rp-map="live"]').forEach(function (el) {
      if (!el._rpMap || !el._rpOpts || !pos.length) return;
      el._rpOpts.riders = pos;
      if (el._rpMap.update && window.SLRouteMap) { try { el._rpMap.update(el._rpOpts); } catch (err) { /* keep last */ } }
    });
  }
  function poll() {
    if (document.hidden || !navigator.onLine) return;
    SL.get(cfg.ajax, { op: 'state', date: cfg.date || '' }).then(applyState).catch(function () { /* next tick */ });
  }
  function startPolling() {
    if (pollTimer) clearInterval(pollTimer);
    if (['live', 'plan', 'fleet'].indexOf(cfg.tab) < 0 || !document.querySelector('[data-rp-kpi]')) return;
    pollTimer = setInterval(poll, 20000);
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden && pollTimer) poll(); });

  /* ---------- events (delegated once) ---------- */
  document.addEventListener('click', function (e) {
    var t;
    if ((t = e.target.closest('[data-rp-group]'))) { filterRiders(t.getAttribute('data-rp-group')); return; }
    if ((t = e.target.closest('[data-rp-lock]'))) { e.preventDefault(); lock(t); return; }
    if ((t = e.target.closest('[data-rp-parcels]'))) {
      var tpl = document.querySelector('template[data-rp-parcel-tpl="' + t.getAttribute('data-rp-parcels') + '"]'), dlg = document.getElementById('rp-parcel');
      if (tpl && dlg) { var body = dlg.querySelector('[data-rp-parcel-body]'); body.textContent = ''; body.appendChild(tpl.content.cloneNode(true)); dlg.hidden = false; }
      return;
    }
    if ((t = e.target.closest('[data-rp-move]'))) {
      var card = t.closest('[data-rp-unit]'); moveUnit = { id: t.getAttribute('data-rp-move'), from: card ? card.getAttribute('data-rp-from') : '' };
      var md = document.getElementById('rp-move'); if (!md) return;
      md.querySelector('[data-rp-move-label]').textContent = card ? card.getAttribute('data-rp-label') : '#' + moveUnit.id;
      var fromLocked = !!document.querySelector('.rp-r.locked[data-rp-rider="' + (moveUnit.from || 0) + '"]');
      if (fromLocked) { SL.toast('This rider is locked. Unlock to change their stops.', true); return; }
      md.querySelectorAll('[data-rp-move-to]').forEach(function (b) { b.disabled = b.getAttribute('data-rp-move-to') === String(moveUnit.from || 0) || b.hasAttribute('data-locked') || !!document.querySelector('.rp-r.locked[data-rp-rider="' + b.getAttribute('data-rp-move-to') + '"]'); });
      md.hidden = false; return;
    }
    if ((t = e.target.closest('[data-rp-move-to]'))) {
      var dd = t.closest('.sl-dialog'); if (dd) dd.hidden = true;
      if (moveUnit) move(moveUnit.id, parseInt(t.getAttribute('data-rp-move-to'), 10) || 0);
      return;
    }
    if ((t = e.target.closest('[data-rp-discard]'))) {
      var planD = t.getAttribute('data-plan');
      ask('Discard this draft plan?', 'Nothing has been assigned yet. The stops go back to the cargo hold.', 'Discard').then(function (yes) { if (yes) simple('discard', { plan_id: planD }, 'Draft discarded', urlWith({ plan: null })); });
      return;
    }
    if ((t = e.target.closest('[data-rp-confirm]'))) { confirmPlan(t); return; }
    if ((t = e.target.closest('[data-rp-reopt]'))) { simple('reoptimise', { route_id: t.getAttribute('data-rp-reopt') }, 'Route re-optimised'); return; }
    if ((t = e.target.closest('[data-rp-add-stop]'))) { addStop(t.getAttribute('data-route'), t.getAttribute('data-rp-add-stop'), false); return; }
    if ((t = e.target.closest('[data-rp-remove-stop]'))) {
      var rRoute = t.getAttribute('data-route'), rStop = t.getAttribute('data-rp-remove-stop');
      ask('Take ' + (t.getAttribute('data-rp-label') || 'this stop') + ' off the route?', 'It goes back to the cargo hold for another rider or a later run.', 'Remove stop').then(function (yes) { if (yes) simple('remove_stop', { route_id: rRoute, stop_id: rStop }, 'Stop removed'); });
      return;
    }
    if ((t = e.target.closest('[data-rp-start-add]'))) {
      var wrap = document.querySelector('[data-rp-starts]'), rows = wrap.querySelectorAll('[data-rp-start-row]'), src = rows[rows.length - 1];
      if (!src) return;
      var n = rows.length, row = src.cloneNode(true);
      row.querySelectorAll('input').forEach(function (inp) {
        if (inp.name) inp.name = inp.name.replace(/start_places\[\d+\]/, 'start_places[' + n + ']');
        if (inp.type === 'radio') { inp.checked = false; inp.value = 'start-' + Date.now().toString(36); }
        else if (/\[id\]$/.test(inp.name)) inp.value = '';
        else inp.value = '';
      });
      var idIn = row.querySelector('input[name$="[id]"]'), radio = row.querySelector('input[type=radio]'); if (idIn && radio) idIn.value = radio.value;
      if (!row.querySelector('[data-rp-start-del]')) { var del = document.createElement('button'); del.type = 'button'; del.className = 'sl-btn ghost sm'; del.setAttribute('data-rp-start-del', ''); del.setAttribute('aria-label', 'Remove start place'); del.innerHTML = '<i class="ic ic-sm i-trash" aria-hidden="true"></i>'; row.appendChild(del); }
      wrap.appendChild(row); var first = row.querySelector('input[name$="[label]"]'); if (first) first.focus();
      return;
    }
    if ((t = e.target.closest('[data-rp-start-del]'))) { var r = t.closest('[data-rp-start-row]'); if (r && !r.querySelector('input[type=radio]:checked')) r.remove(); else SL.toast('Pick another default start place first', true); return; }
  });
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('[data-rp-propose]');
    if (!f) return;
    e.preventDefault();
    propose(f, f.querySelector('[data-rp-auto]'));
  });
  document.addEventListener('keydown', function (e) {
    var t = e.target.closest && e.target.closest('span[role="button"][data-rp-move]');
    if (t && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); t.click(); }
  });
  document.addEventListener('input', function (e) { if (e.target.matches('[data-rp-hold-q]')) filterHold(); });
  document.addEventListener('change', function (e) { if (e.target.matches('[data-rp-hold-area],[data-rp-hold-handling]')) filterHold(); });

  /* drag and drop (desktop); touch and keyboard use the "Move to…" button */
  document.addEventListener('dragstart', function (e) {
    var u = e.target.closest && e.target.closest('[data-rp-unit][draggable="true"]');
    if (!u) return;
    dragUnit = { id: u.getAttribute('data-rp-unit'), from: u.getAttribute('data-rp-from') || '0', el: u };
    u.classList.add('dragging');
    document.documentElement.classList.add('rp-dragging');
    if (dragUnit.from !== '0') {
      var bar = document.getElementById('rp-dropbar');
      if (!bar) { bar = document.createElement('div'); bar.id = 'rp-dropbar'; bar.className = 'rp-dropbar'; bar.setAttribute('data-rp-drop', '0'); bar.textContent = 'Drop here to put it back in the cargo hold'; document.body.appendChild(bar); }
      bar.hidden = false;
    }
    try { e.dataTransfer.setData('text/plain', dragUnit.id); e.dataTransfer.effectAllowed = 'move'; } catch (err) { /* old browsers */ }
  });
  document.addEventListener('dragend', function () {
    if (dragUnit && dragUnit.el) dragUnit.el.classList.remove('dragging');
    var bar = document.getElementById('rp-dropbar'); if (bar) bar.hidden = true;
    document.documentElement.classList.remove('rp-dragging');
    document.querySelectorAll('.drop-ok,.drop-no').forEach(function (x) { x.classList.remove('drop-ok', 'drop-no'); });
    dragUnit = null;
  });
  document.addEventListener('dragover', function (e) {
    if (!dragUnit) return;
    var zone = e.target.closest('[data-rp-drop]');
    document.querySelectorAll('.drop-ok,.drop-no').forEach(function (x) { if (x !== zone) x.classList.remove('drop-ok', 'drop-no'); });
    if (!zone) return;
    var to = zone.getAttribute('data-rp-drop'), lockedTarget = zone.classList.contains('locked');
    if (to === dragUnit.from) return;
    e.preventDefault();
    zone.classList.toggle('drop-no', lockedTarget); zone.classList.toggle('drop-ok', !lockedTarget);
    try { e.dataTransfer.dropEffect = lockedTarget ? 'none' : 'move'; } catch (err) { /* ignore */ }
  });
  document.addEventListener('drop', function (e) {
    if (!dragUnit) return;
    var zone = e.target.closest('[data-rp-drop]');
    if (!zone) return;
    e.preventDefault();
    var to = parseInt(zone.getAttribute('data-rp-drop'), 10) || 0, unit = dragUnit.id;
    zone.classList.remove('drop-ok', 'drop-no');
    if (String(to) === dragUnit.from) return;
    var srcLocked = !!document.querySelector('.rp-r.locked[data-rp-rider="' + dragUnit.from + '"]');
    if (zone.classList.contains('locked') || srcLocked) { SL.toast('This rider is locked. Unlock to change their set.', true); return; }
    if (dragUnit.el && zone.id !== 'rp-dropbar') { var dest = zone.querySelector('.rp-r-units, [data-rp-hold-list]') || zone; dest.appendChild(dragUnit.el); }
    move(unit, to);
  });

  function init() {
    readCfg();
    if (!root()) return;
    var g = storeGet('sl.rp.group'); if (g && document.querySelector('[data-rp-group="' + g + '"]')) filterRiders(g);
    if (!cfg.plan) localLocks().forEach(function (id) { var b = document.querySelector('[data-rp-lock="' + id + '"]'); if (b) { b.classList.add('on'); b.setAttribute('aria-pressed', 'true'); var c = b.closest('.rp-r'); if (c) c.classList.add('locked'); } });
    // dialogs inserted by refresh() need the shell's dialog wiring (role/focus) re-applied
    document.querySelectorAll('[data-rp-root] .sl-dialog .sl-modal').forEach(function (m) { m.setAttribute('role', 'dialog'); m.setAttribute('aria-modal', 'true'); m.tabIndex = -1; });
    filterHold();
    // Keep the active tab in view on narrow screens (the tab strip scrolls sideways).
    var on = document.querySelector('.rp-tabs a.on');
    if (on && on.parentNode.scrollWidth > on.parentNode.clientWidth) { var strip = on.parentNode; strip.scrollLeft = Math.max(0, on.offsetLeft - (strip.clientWidth - on.offsetWidth) / 2); }
    mountMaps();
    startPolling();
  }
  window.SLRoutePlanner = { refresh: refresh };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
