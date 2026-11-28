/* Smart Routing: views/sl/pages/SmartRouting.php.
 * Day overview (pick a rider, map of every stop), "Create route" dialog (one rider, or share between the
 * free riders), route details (re-order by drag or arrows, add / remove a stop, optimise, hand over, save,
 * send to the rider), the stop dialog (proof of delivery, staff note) and the riders-and-vehicles view.
 * All writes go to ajax/sl/smart_routing_ajax.php through SL.post (CSRF). After a write the page body is
 * fetched again and swapped in place ([data-sr-root]) so the scroll position stays put. */
(function () {
  'use strict';
  if (!window.SL) return;
  var cfg = {}, map = null, mini = null, mapObs = null, pollTimer = null, busy = false, selRider = 0, focusLock = 0;
  var GREY = '#94a3b8', WAIT = '#dc2626';

  function $(sel, el) { return (el || document).querySelector(sel); }
  function $$(sel, el) { return Array.prototype.slice.call((el || document).querySelectorAll(sel)); }
  function root() { return $('[data-sr-root]'); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function setBusy(v) { busy = v; if (v) document.documentElement.setAttribute('data-sr-busy', ''); else document.documentElement.removeAttribute('data-sr-busy'); }
  function readCfg() {
    var el = document.getElementById('sr-data');
    try { cfg = el ? JSON.parse(el.textContent || '{}') : {}; } catch (e) { cfg = {}; }
    cfg.ajax = cfg.ajax || 'ajax/sl/smart_routing_ajax.php';
    cfg.riders = cfg.riders || [];
    cfg.map = cfg.map || { unassigned: [], routes: [] };
  }
  function call(op, data) {
    var fd = new FormData();
    fd.append('op', op);
    if (cfg.date) fd.append('date', cfg.date);
    Object.keys(data || {}).forEach(function (k) {
      var v = data[k];
      if (Array.isArray(v)) v.forEach(function (x) { fd.append(k + '[]', x); });
      else if (v !== undefined && v !== null) fd.append(k, v);
    });
    setBusy(true);
    return SL.post(cfg.ajax, fd).then(function (j) { setBusy(false); return j; }, function (e) { setBusy(false); return { success: false, message: (e && e.message) || 'The request did not go through. Check your connection and try again.' }; });
  }
  function fail(j, fallback) { SL.toast((j && (j.message || j.error)) || fallback || 'Something went wrong', true); }
  function rider(id) { return cfg.riders.filter(function (r) { return r.id === +id; })[0] || null; }
  function pageUrl(params) {
    var u = new URL(cfg.self || 'smart_routing.php', location.href);
    if (cfg.date && !params.noDate) u.searchParams.set('date', cfg.date);
    Object.keys(params).forEach(function (k) { if (k !== 'noDate' && params[k]) u.searchParams.set(k, params[k]); });
    return u.pathname.split('/').pop() + u.search;
  }

  /* ---------- in-place refresh ---------- */
  function refresh(url) {
    url = url || location.href;
    return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.text(); }).then(function (html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var fresh = doc.querySelector('[data-sr-root]'), cur = root();
      if (!fresh || !cur) { location.href = url; return; }
      var tl = $('[data-sr-scroll]'), top = tl ? tl.scrollTop : 0;
      destroyMaps();
      cur.replaceWith(document.importNode(fresh, true));
      document.body.style.overflow = '';
      if (new URL(url, location.href).href !== location.href) history.replaceState(null, '', url);
      init();
      var tl2 = $('[data-sr-scroll]'); if (tl2 && top) tl2.scrollTop = top;
    }).catch(function () { location.href = url; });
  }

  /* ---------- confirm dialog ---------- */
  function ask(title, msg, okLabel) {
    var dlg = document.getElementById('sr-ask');
    if (!dlg) return Promise.resolve(window.confirm(title + '\n\n' + msg));
    return new Promise(function (resolve) {
      $('[data-sr-ask-title]', dlg).textContent = title;
      $('[data-sr-ask-msg]', dlg).textContent = msg || '';
      var okBtn = $('[data-sr-ask-ok]', dlg); okBtn.textContent = okLabel || 'Continue';
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
  /* A step the server refused but allows on confirmation (over capacity, rider off shift). */
  function withForce(op, data, title) {
    return call(op, data).then(function (j) {
      if (j && j.blocked && j.can_force) {
        return ask(title || 'Assign anyway?', j.message + (/capacity/.test(j.message) ? ' A vehicle may be loaded up to 110% when you accept it.' : ' You can still go ahead.'), 'Assign anyway').then(function (yes) {
          if (!yes) return { success: false, cancelled: true };
          var d2 = Object.assign({}, data, { force: 1 });
          return call(op, d2);
        });
      }
      return j;
    });
  }

  /* ---------- map ---------- */
  function destroyMaps() {
    if (mapObs) { mapObs.disconnect(); mapObs = null; }
    [map, mini].forEach(function (m) { try { m && m.destroy && m.destroy(); } catch (e) { /* ignore */ } });
    map = null; mini = null;
  }
  function pad(n) { n = String(n); return n.length < 2 ? '0' + n : n; }
  function mapData() {
    var m = cfg.map, routes = [], detail = cfg.view === 'route', flags = {};
    var focusRoute = detail ? cfg.routeId : (selRider ? (rider(selRider) || {}).routeId : 0);
    var depot = m.depot, riders = [];
    (m.routes || []).forEach(function (r) {
      var on = focusRoute ? r.id === focusRoute : true;
      if (detail && on && r.depot) depot = r.depot;
      var color = on ? r.color : GREY;
      routes.push({ color: color, name: r.name, path: r.path, stops: (r.stops || []).map(function (s) {
        flags[s.id] = { dim: !on, late: s.late && on, route: r.id, rider: r.driver_id };
        return { id: s.id, seq: on || !focusRoute ? pad(s.seq) : '', lat: s.lat, lng: s.lng, label: s.label + (on ? '' : ' (' + r.name + ')'), status: on ? s.status : 'planned', eta: s.eta, color: color };
      }) });
      if (r.pos && (on || !focusRoute)) riders.push({ id: r.driver_id, name: r.name, lat: r.pos.lat, lng: r.pos.lng, color: color, at: r.pos.at });
    });
    // The focused route is drawn last so its line and pins sit on top.
    routes.sort(function (a, b) { return (a.color === GREY ? 0 : 1) - (b.color === GREY ? 0 : 1); });
    if ((m.unassigned || []).length && depot) {
      // A two-point path at the start place keeps the map from joining the waiting stops with a line.
      routes.unshift({ color: WAIT, name: 'Waiting', path: [[depot.lat, depot.lng], [depot.lat, depot.lng]], stops: m.unassigned.map(function (u) { flags[u.id] = { wait: true }; return { id: u.id, lat: u.lat, lng: u.lng, label: u.label + ' (waiting)', status: 'planned', color: WAIT }; }) });
    }
    return { depot: depot, routes: routes, riders: riders, stops: [], path: null, flags: flags };
  }
  /* Schematic map only: numbered bubbles, a double route line, grey "other rider" pins. */
  function decorate(el, flags) {
    var svg = $('svg.slrm-svg', el);
    if (!svg || svg.getAttribute('data-sr-deco')) return;
    svg.setAttribute('data-sr-deco', '1');
    $$('.slrm-stop', svg).forEach(function (g) {
      var f = flags[g.getAttribute('data-stop')] || flags[+g.getAttribute('data-stop')] || {}, c = $('circle', g), t = $('text', g);
      if (f.wait) { g.classList.add('sr-wait'); if (cfg.view === 'route') g.classList.add('sr-quiet'); if (t) { t.textContent = '+'; t.setAttribute('y', '5'); } return; }
      var done = /slrm-st-(delivered|failed|returned)/.test(g.getAttribute('class'));
      if (!done) { g.classList.add('sr-bub'); g.style.setProperty('--bc', c ? c.getAttribute('fill') : '#2563eb'); }
      if (f.dim) g.classList.add('sr-dim');
      if (f.late) g.classList.add('sr-late');
    });
    var dep = $('.slrm-depot', svg);
    if (dep) {
      var rc = $('rect', dep), ic = $('.slrm-depot-ico', dep);
      if (rc) { rc.setAttribute('x', '-15'); rc.setAttribute('y', '-15'); rc.setAttribute('width', '30'); rc.setAttribute('height', '30'); rc.setAttribute('rx', '10'); }
      if (ic) ic.setAttribute('transform', 'scale(1.6)');
      svg.appendChild(dep);
    }
    $$('polyline.slrm-line', svg).forEach(function (pl) {
      if (pl.getAttribute('stroke') === GREY) { pl.style.strokeWidth = '3'; pl.style.opacity = '.7'; return; }
      if (pl.getAttribute('stroke') === WAIT) { pl.style.display = 'none'; return; }
      var inner = pl.cloneNode(false);
      inner.setAttribute('class', 'sr-line-in'); inner.removeAttribute('stroke');
      pl.parentNode.insertBefore(inner, pl.nextSibling);
    });
  }
  function mountMap() {
    var el = $('[data-sr-map]');
    if (!el || !window.SLRouteMap) { if (el) el.innerHTML = '<div class="sr-map-empty">The map could not be loaded. The lists still work.</div>'; return; }
    var data = mapData();
    var has = data.depot || data.routes.length;
    if (!has) { el.innerHTML = '<div class="sr-map-empty">Nothing to show on the map yet.</div>'; return; }
    el.innerHTML = '';
    el._flags = data.flags;
    mapObs = new MutationObserver(function () { decorate(el, el._flags || {}); });
    mapObs.observe(el, { childList: true, subtree: true });
    try {
      map = window.SLRouteMap.mount(el, { depot: data.depot, routes: data.routes, riders: data.riders, stops: [], provider: 'auto', interactive: true, tzOffsetMin: cfg.tz, onStopClick: onPin });
    } catch (e) { el.innerHTML = '<div class="sr-map-empty">The map could not be drawn. The lists still work.</div>'; }
    decorate(el, data.flags);
  }
  function updateMap() {
    var el = $('[data-sr-map]');
    if (!map || !el) return;
    var data = mapData();
    el._flags = data.flags;
    map.update({ depot: data.depot, routes: data.routes, riders: data.riders });
    decorate(el, data.flags);
  }
  function onPin(id) {
    id = String(id);
    if (id.charAt(0) === 'u') return onWaitingPin(+id.slice(1));
    var f = ($('[data-sr-map]')._flags || {})[id] || {};
    if (cfg.view === 'route') {
      if (f.route === cfg.routeId) return focusStop(id, true);
      var other = (cfg.map.routes || []).filter(function (r) { return r.id === f.route; })[0];
      if (other) SL.toast('That stop is on ' + other.name + "'s route.");
      return;
    }
    if (f.rider) pick(f.rider, true);
  }
  function onWaitingPin(reqId) {
    if (cfg.isPast) return;
    var u = (cfg.map.unassigned || []).filter(function (x) { return x.request_id === reqId; })[0];
    if (cfg.view === 'route') {
      var routeEl = $('[data-sr-route]');
      if (!routeEl || routeEl.getAttribute('data-sr-status') === 'done') return;
      ask('Add this stop?', (u ? u.label.replace(' (waiting)', '') : 'Delivery #' + reqId) + ' will join this route and the order is worked out again.', 'Add stop').then(function (yes) { if (yes) addStop(reqId); });
      return;
    }
    openCreate([reqId]);
  }
  function focusStop(id, scroll) {
    var li = $('[data-sr-stop="' + id + '"]');
    $$('.sr-stop.flash').forEach(function (x) { x.classList.remove('flash'); });
    if (li) {
      li.classList.add('flash');
      if (scroll) { li.scrollIntoView({ behavior: 'smooth', block: 'center' }); var b = $('[data-sr-stop-open]', li); if (b) b.focus({ preventScroll: true }); }
      clearTimeout(focusLock); focusLock = setTimeout(function () { li.classList.remove('flash'); }, 2200);
    }
    if (map) { try { map.focus(id); } catch (e) { /* ignore */ } }
  }

  /* ---------- overview: riders ---------- */
  function pick(id, scroll) {
    id = +id;
    selRider = selRider === id && !scroll ? 0 : id;
    $$('[data-sr-rider]').forEach(function (li) {
      var on = +li.getAttribute('data-sr-rider') === selRider;
      li.classList.toggle('sel', on);
      var b = $('[data-sr-pick]', li); if (b) b.setAttribute('aria-pressed', on ? 'true' : 'false');
      if (on && scroll) li.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
    var r = rider(selRider), note = $('[data-sr-foot-note]'), open = $('[data-sr-open-route]'), label = $('[data-sr-create-label]');
    if (note) note.textContent = r ? r.name + ' · ' + r.vehicle + ' · ' + r.stLabel + (r.routeId ? ' · has a route' : '') : 'Pick a rider, or let Smart Routing share the work.';
    if (open) { open.hidden = !(r && r.routeId); if (r && r.routeUrl) open.href = r.routeUrl; }
    if (label) label.textContent = r && r.routeId ? 'Add to rider route' : 'Create rider route';
    try { var u = new URL(location.href); if (selRider) u.searchParams.set('rider', selRider); else u.searchParams.delete('rider'); history.replaceState(null, '', u.pathname.split('/').pop() + u.search); } catch (e) { /* ignore */ }
    updateMap();
  }
  function filterRiders() {
    var q = (($('[data-sr-rider-search]') || {}).value || '').trim().toLowerCase();
    var chip = $('[data-sr-filter].on'), g = chip ? chip.getAttribute('data-sr-filter') : 'all', n = 0;
    $$('[data-sr-rider]').forEach(function (li) {
      var show = (!q || li.getAttribute('data-search').indexOf(q) !== -1) && (g === 'all' || li.getAttribute('data-group') === g);
      li.hidden = !show; if (show) n++;
    });
    var none = $('[data-sr-riders-none]'); if (none) none.hidden = n > 0 || !$$('[data-sr-rider]').length;
  }

  /* ---------- create route ---------- */
  function createForm() { return $('[data-sr-create-form]'); }
  function openCreate(preIds) {
    var dlg = document.getElementById('sr-create'), f = createForm();
    if (!dlg || !f) return;
    var sel = $('[data-sr-f-rider]', f);
    if (sel) sel.value = selRider && $('option[value="' + selRider + '"]', sel) ? String(selRider) : '0';
    if (cfg.view === 'route') { var cur = (cfg.map.routes || []).filter(function (r) { return r.id === cfg.routeId; })[0]; if (cur && sel && $('option[value="' + cur.driver_id + '"]', sel)) sel.value = String(cur.driver_id); }
    if (preIds) $$('input[name="request_ids[]"]', f).forEach(function (c) { c.checked = preIds.indexOf(+c.value) !== -1; });
    var res = $('[data-sr-auto-result]', f); if (res) { res.hidden = true; res.innerHTML = ''; }
    f._plan = 0;
    syncCreate();
    dlg.hidden = false;
    setTimeout(function () { (sel || f).focus(); }, 30);
  }
  function syncCreate() {
    var f = createForm(); if (!f) return;
    var rid = +($('[data-sr-f-rider]', f) || {}).value || 0, r = rider(rid), auto = !rid;
    $$('[data-sr-manual]', f).forEach(function (el) { el.hidden = auto; });
    var note = $('[data-sr-auto-note]', f); if (note) note.hidden = !auto;
    var res = $('[data-sr-auto-result]', f); if (res && !auto) { res.hidden = true; res.innerHTML = ''; f._plan = 0; }
    var pcs = $('[data-sr-f-pieces]', f);
    if (pcs && r) pcs.placeholder = 'Vehicle takes ' + Math.max(0, r.cap.n - (r.used.n || 0)) + ' more';
    var all = (($('[data-sr-scope]:checked', f) || {}).value) === 'all';
    $$('.sr-del.is-with', f).forEach(function (el) {
      var show = all && +el.getAttribute('data-with') !== rid;
      el.hidden = !show; if (!show) { var c = $('input', el); if (c) c.checked = false; }
    });
    filterDels();
    tally();
    var go = $('[data-sr-create-go-label]', f);
    if (go) go.textContent = auto ? (f._plan ? 'Assign these routes' : 'Share between riders') : (r && r.routeId ? 'Add to route' : 'Create route');
  }
  function filterDels() {
    var f = createForm(); if (!f) return;
    var q = (($('[data-sr-del-search]', f) || {}).value || '').trim().toLowerCase();
    var all = (($('[data-sr-scope]:checked', f) || {}).value) === 'all', rid = +($('[data-sr-f-rider]', f) || {}).value || 0;
    $$('.sr-del', f).forEach(function (el) {
      var base = !el.classList.contains('is-with') || (all && +el.getAttribute('data-with') !== rid);
      el.hidden = !(base && (!q || el.getAttribute('data-search').indexOf(q) !== -1));
    });
  }
  function tally() {
    var f = createForm(); if (!f) return true;
    var box = $('[data-sr-tally]', f), text = $('[data-sr-tally-text]', f), bar = $('[data-sr-tally-bar]', f);
    if (!box) return true;
    var n = 0, pcs = 0, kg = 0, l = 0, cents = 0;
    $$('input[name="request_ids[]"]:checked', f).forEach(function (c) { n++; pcs += +c.getAttribute('data-n'); kg += +c.getAttribute('data-kg'); l += +c.getAttribute('data-l'); cents += +c.getAttribute('data-cents'); });
    var r = rider(+($('[data-sr-f-rider]', f) || {}).value || 0);
    var maxStops = +(f.elements.max_stops || {}).value || 0, maxPcs = +(f.elements.max_pieces || {}).value || 0, maxAmt = f.elements.max_amount ? +f.elements.max_amount.value || 0 : 0;
    var parts = [n + ' stop' + (n === 1 ? '' : 's'), pcs + ' piece' + (pcs === 1 ? '' : 's'), (Math.round(kg * 10) / 10) + ' kg'];
    if (cfg.finance && cents) parts.push(cfg.currency + ' ' + (cents / 100).toFixed(2) + ' to collect');
    var problems = [];
    if (maxStops && n > maxStops) problems.push('over the ' + maxStops + ' stop limit');
    if (maxPcs && pcs > maxPcs) problems.push('over the ' + maxPcs + ' piece limit');
    if (cfg.finance && maxAmt && cents > maxAmt * 100) problems.push('over the cash limit');
    var pct = 0;
    if (r && n) {
      pct = Math.max(r.cap.kg ? ((r.used.kg || 0) + kg) * 100 / r.cap.kg : 0, r.cap.l ? ((r.used.l || 0) + l) * 100 / r.cap.l : 0, r.cap.n ? ((r.used.n || 0) + pcs) * 100 / r.cap.n : 0);
      parts.push(Math.round(pct) + '% of the ' + r.vehicle.toLowerCase());
      if (pct > 110) problems.push('too much for the vehicle');
    }
    text.textContent = n ? parts.join(' · ') + (problems.length ? ' · ' + problems.join(', ') : '') : 'Nothing ticked yet';
    box.classList.toggle('bad', problems.length > 0);
    if (bar) { bar.hidden = !(r && n); bar.className = 'sr-lb ' + (pct > 100 ? 'over' : pct >= 85 ? 'full' : 'ok'); $('i', bar).style.width = Math.min(100, Math.round(pct)) + '%'; }
    var all = $('[data-sr-all]', f);
    if (all) { var vis = $$('.sr-del:not([hidden]) input', f); all.checked = vis.length > 0 && vis.every(function (c) { return c.checked; }); }
    return problems.length === 0 && n > 0;
  }
  function submitCreate(e) {
    e.preventDefault();
    var f = createForm(); if (busy || !f) return;
    var rid = +($('[data-sr-f-rider]', f) || {}).value || 0;
    if (!rid) return f._plan ? autoConfirm(f) : autoPropose(f);
    var ids = $$('input[name="request_ids[]"]:checked', f).map(function (c) { return c.value; });
    if (!ids.length) { SL.toast('Tick at least one delivery.', true); return; }
    if (!tally()) { SL.toast('The ticked deliveries are over a limit. Untick some or raise the limit.', true); return; }
    withForce('create_route', { rider_id: rid, start_id: f.elements.start_id.value, request_ids: ids, max_stops: f.elements.max_stops.value, max_pieces: f.elements.max_pieces.value, max_amount: f.elements.max_amount ? f.elements.max_amount.value : '' }, 'Assign anyway?').then(function (j) {
      if (j && j.cancelled) return;
      if (!j || !j.success) return fail(j, 'The route could not be created.');
      SL.toast(j.message);
      refresh(pageUrl({ route: j.route_id }));
    });
  }
  function autoPropose(f) {
    call('auto_propose', { start_id: f.elements.start_id.value }).then(function (j) {
      var res = $('[data-sr-auto-result]', f);
      if (!j || !j.success) return fail(j, 'The work could not be shared out.');
      var html = '';
      (j.riders || []).forEach(function (r) { html += '<div class="sr-autorow"><span><b>' + esc(r.name) + '</b> <span class="mut">' + esc(r.vehicle) + '</span></span><span>' + r.stops + ' stop' + (r.stops === 1 ? '' : 's') + ' · ' + r.km + ' km · ' + r.fill + '% full</span></div>'; });
      if (!(j.riders || []).length) html += '<div class="sr-emptybox">' + esc(j.message) + '</div>';
      if ((j.left || []).length) {
        var why = {}; j.left.forEach(function (u) { var k = u.reason || 'no rider has room'; why[k] = (why[k] || 0) + 1; });
        html += '<div class="sl-banner sr-note"><span>' + j.left.length + ' deliver' + (j.left.length === 1 ? 'y stays' : 'ies stay') + ' waiting: ' + esc(Object.keys(why).map(function (k) { return why[k] + ' ' + k; }).join('; ')) + '.</span></div>';
      }
      res.innerHTML = html; res.hidden = false;
      f._plan = (j.riders || []).length ? j.plan_id : 0;
      syncCreateLabel(f);
    });
  }
  /* A proposal the dispatcher walks away from is discarded, so no half-made plan is left behind. */
  function discardPlan() {
    var f = createForm(); if (!f || !f._plan) return;
    var fd = new FormData(); fd.append('op', 'auto_discard'); fd.append('plan_id', f._plan); f._plan = 0;
    SL.post(cfg.ajax, fd).catch(function () { /* the draft simply stays until the next proposal */ });
    var res = $('[data-sr-auto-result]', f); if (res) { res.hidden = true; res.innerHTML = ''; }
  }
  function syncCreateLabel(f) { var go = $('[data-sr-create-go-label]', f); if (go) go.textContent = f._plan ? 'Assign these routes' : 'Share between riders'; }
  function autoConfirm(f) {
    call('auto_confirm', { plan_id: f._plan }).then(function (j) {
      if (!j || !j.success) { f._plan = 0; syncCreateLabel(f); return fail(j, 'The routes could not be assigned.'); }
      SL.toast(j.message);
      refresh(pageUrl({}));
    });
  }

  /* ---------- route details ---------- */
  function routeId() { var el = $('[data-sr-route]'); return el ? +el.getAttribute('data-sr-route') : 0; }
  function editKey() { return 'sl.sr.edit.' + routeId(); }
  function setEdit(on) {
    var btn = $('[data-sr-edit]'), bar = $('[data-sr-editbar]');
    if (!btn || !bar) return;
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    $('span', btn).textContent = on ? 'Done' : 'Edit';
    bar.hidden = !on;
    $$('[data-sr-tools]').forEach(function (t) { t.hidden = !on; });
    $$('[data-sr-stop][data-movable]').forEach(function (li) { li.draggable = on; });
    var mov = $$('[data-sr-stop][data-movable]');
    mov.forEach(function (li, i) { var u = $('[data-sr-move="up"]', li), d = $('[data-sr-move="down"]', li); if (u) u.disabled = i === 0; if (d) d.disabled = i === mov.length - 1; });
    try { if (on) sessionStorage.setItem(editKey(), '1'); else sessionStorage.removeItem(editKey()); } catch (e) { /* private mode */ }
  }
  function isEdit() { var b = $('[data-sr-edit]'); return !!b && b.getAttribute('aria-pressed') === 'true'; }
  function sendOrder() {
    var ids = $$('[data-sr-stop][data-movable]').map(function (li) { return li.getAttribute('data-sr-stop'); });
    call('reorder', { route_id: routeId(), stop_ids: ids }).then(function (j) {
      if (j && j.success) SL.toast(j.message); else fail(j, 'The order could not be saved.');
      refresh();
    });
  }
  function moveStop(li, dir) {
    var mov = $$('[data-sr-stop][data-movable]'), i = mov.indexOf(li), j = i + (dir === 'up' ? -1 : 1);
    if (i < 0 || j < 0 || j >= mov.length) return;
    if (dir === 'up') mov[j].parentNode.insertBefore(li, mov[j]); else mov[j].parentNode.insertBefore(li, mov[j].nextSibling);
    sendOrder();
  }
  function addStop(reqId) {
    withForce('add_stop', { route_id: routeId(), request_id: reqId }, 'Add anyway?').then(function (j) {
      if (j && j.cancelled) return;
      if (!j || !j.success) return fail(j, 'The stop could not be added.');
      SL.toast(j.message); refresh();
    });
  }
  function filterStops() {
    var q = (($('[data-sr-stop-search]') || {}).value || '').trim().toLowerCase(), sel = $('[data-sr-stop-filter]'), mode = sel ? sel.value : 'all', n = 0;
    if (sel) { if (mode !== 'all') sel.parentNode.setAttribute('data-on', ''); else sel.parentNode.removeAttribute('data-on'); }
    $$('[data-sr-stop]').forEach(function (li) {
      var st = li.getAttribute('data-status'), done = ['delivered', 'failed', 'returned'].indexOf(st) !== -1;
      var show = (!q || li.getAttribute('data-search').indexOf(q) !== -1) && (mode === 'all' || (mode === 'open' && !done) || (mode === 'done' && done) || (mode === 'late' && li.getAttribute('data-late') === '1'));
      li.hidden = !show; if (show) n++;
    });
    var none = $('[data-sr-stops-none]'); if (none) none.hidden = n > 0 || !$$('[data-sr-stop]').length;
  }

  /* ---------- stop dialog ---------- */
  var STOP_TITLES = { delivered: 'Delivery success', failed: 'Delivery not completed', returned: 'Returned to the office', arrived: 'Rider is at this stop', pending: 'Planned stop', removed: 'Removed stop' };
  function dlRow(k, v) { return v === '' || v == null ? '' : '<dt>' + esc(k) + '</dt><dd>' + v + '</dd>'; }
  function openStop(id) {
    var dlg = document.getElementById('sr-stop'), body = $('[data-sr-stop-body]', dlg);
    if (!dlg) return;
    $('[data-sr-stop-title]', dlg).textContent = 'Stop';
    $('[data-sr-stop-sub]', dlg).textContent = '';
    body.innerHTML = '<div class="sr-loading">Loading the stop…</div>';
    dlg.hidden = false;
    if (map) { try { map.focus(id); } catch (e) { /* ignore */ } }
    var fd = new FormData(); fd.append('op', 'stop'); fd.append('stop_id', id);
    SL.post(cfg.ajax, fd).then(function (j) {
      if (!j || !j.success) { body.innerHTML = '<div class="sr-emptybox">' + esc((j && j.message) || 'This stop could not be loaded.') + '</div>'; return; }
      renderStop(dlg, body, j);
    }).catch(function () { body.innerHTML = '<div class="sr-emptybox">This stop could not be loaded. Check your connection.</div>'; });
  }
  function renderStop(dlg, body, j) {
    var s = j.stop, rec = j.recipient, o = j.order, done = s.status === 'delivered', failed = s.status === 'failed' || s.status === 'returned';
    $('[data-sr-stop-title]', dlg).textContent = STOP_TITLES[s.status] || 'Stop';
    $('[data-sr-stop-sub]', dlg).textContent = pad(s.seq) + ', ' + s.address;
    var tick = done ? '<em class="sr-tick st-delivered"><i class="ic ic-sm i-check" aria-hidden="true"></i></em>' : failed ? '<em class="sr-tick st-failed"><i class="ic ic-sm i-x" aria-hidden="true"></i></em>' : '';
    var h = '<div class="sr-pod-who"><span class="sr-av" style="--rc:#2563eb" aria-hidden="true">' + esc((j.rider.name || '?').split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0).toUpperCase(); }).join('')) + '</span><b>' + esc(j.rider.name) + '</b>' +
      '<span class="sr-time' + (tick ? ' has-ico' : '') + '" title="' + (done || failed ? 'Finished' : 'Expected') + '"><i class="ic ic-sm i-clock" aria-hidden="true"></i>' + esc(s.time || '--:--') + tick + '</span></div>';
    h += '<div class="sr-pod-card">';
    var locUrl = s.handover_url || s.map_url, locTitle = s.handover_url ? "Where the rider's phone was at the hand-over" : 'The stop on the map';
    var loc = '<a class="sr-loc" href="' + esc(locUrl) + '" target="_blank" rel="noopener" title="' + esc(locTitle) + '"><i class="ic ic-sm i-pin" aria-hidden="true"></i>Location</a>';
    if (done) {
      h += '<div class="sr-pod-to"><span class="sr-rider-t"><b>Delivered to ' + esc(rec.received_by || rec.name || 'the recipient') + '</b><span class="mut">' + esc([s.done_on, rec.phone].filter(Boolean).join(' · ')) + '</span></span>' + loc + '</div>';
      h += '<div class="sr-pod-tiles"><div class="sr-tile" data-sr-proof="signature"><b>No signature</b><span>' + esc(j.proof.signature_note) + '</span></div>';
      h += j.proof.photo_url ? '<a class="sr-tile has-img" data-sr-proof="photo" href="' + esc(j.proof.photo_url) + '" target="_blank" rel="noopener" title="Open the delivery photo"><img src="' + esc(j.proof.photo_url) + '" alt="Delivery photo taken by the rider" loading="lazy"></a>' : '<div class="sr-tile" data-sr-proof="photo"><b>No photo</b><span>No delivery photo was saved for this stop.</span></div>';
      h += '</div>';
    } else if (failed) {
      h += '<div class="sr-pod-to"><span class="sr-rider-t"><b>Not delivered to ' + esc(rec.name || 'the recipient') + '</b><span class="mut">' + esc([s.done_on, rec.phone].filter(Boolean).join(' · ')) + '</span></span>' + loc + '</div>';
      h += '<div class="sr-fail" data-sr-fail><b>Reason given by the rider</b>' + esc(s.fail_reason || 'No reason was recorded.') + '</div>';
    } else {
      h += '<div class="sr-pod-to"><span class="sr-rider-t"><b>' + esc(rec.name || 'Recipient') + '</b><span class="mut">' + esc([s.status === 'arrived' ? 'Rider arrived' : 'Expected ' + (s.eta || 'time not set'), s.window ? 'customer asked ' + s.window : '', rec.phone].filter(Boolean).join(' · ')) + '</span></span>' + loc + '</div>';
      if (s.late) h += '<div class="sr-fail"><b>Running late</b>The planned arrival is after the time the customer asked for.</div>';
      if (s.instructions) h += '<div class="mut">Customer instructions: ' + esc(s.instructions) + '</div>';
    }
    h += '</div>';
    h += '<form class="sr-notebox" data-sr-note-form data-stop="' + s.id + '"><label class="fw5" for="sr-note-t">Note</label><textarea id="sr-note-t" name="note" maxlength="1000" placeholder="Add a note for the team about this stop">' + esc(j.note.text) + '</textarea>' +
      '<div class="row between g8 wrap"><span class="mut sm" data-sr-note-meta>' + (j.note.at ? 'Saved ' + esc(j.note.at) + (j.note.by ? ' by ' + esc(j.note.by) : '') : 'Only staff see this note.') + '</span><button class="sl-btn ghost sr-pillbtn sm" type="submit">Save note</button></div></form>';
    h += '<details class="sr-acc"><summary>Recipient information<span class="sr-round"><i class="ic ic-sm i-down" aria-hidden="true"></i></span></summary><dl>' +
      dlRow('Deliver to', esc(rec.name)) + dlRow('Phone', rec.phone ? '<a href="tel:' + esc(rec.phone.replace(/[^\d+]/g, '')) + '">' + esc(rec.phone) + '</a>' : '') + dlRow('Received by', esc(rec.received_by)) +
      dlRow('Customer account', esc(rec.customer)) + dlRow('E-mail', esc(rec.email)) + dlRow('Address', esc(s.address)) + dlRow('Instructions', esc(s.instructions)) + '</dl></details>';
    var parcels = (o.parcels || []).map(function (p) { return '<li>' + esc(p.tracking || 'Parcel') + (p.contents ? ' · ' + esc(p.contents) : '') + ' · ' + esc(p.kg) + ' kg' + (p.handling.length ? ' · ' + esc(p.handling.join(', ').replace(/_/g, ' ')) : '') + '</li>'; }).join('');
    var events = (o.events || []).map(function (ev) { return '<li>' + esc(ev.at) + ' · ' + esc(ev.state) + (ev.note ? ': ' + esc(ev.note) : '') + (ev.by ? ' (' + esc(ev.by) + ')' : '') + '</li>'; }).join('');
    h += '<details class="sr-acc"><summary>Order information<span class="sr-round"><i class="ic ic-sm i-down" aria-hidden="true"></i></span></summary><dl>' +
      dlRow('Delivery request', '#' + esc(o.request_id) + ' · ' + esc(o.state)) + dlRow('Service', esc(o.module === 'sea' ? 'Sea freight' : 'Air freight')) + dlRow('Pieces', esc(o.pieces) + ' · ' + esc(o.kg) + ' kg') +
      dlRow('Delivery fee', o.fee ? esc(o.fee) + (o.fee_collected ? ' · collected by the rider' : '') : '') + '</dl>' + (parcels ? '<ul aria-label="Parcels">' + parcels + '</ul>' : '') + (events ? '<ul aria-label="History">' + events + '</ul>' : '') + '</details>';
    body.innerHTML = h;
  }

  /* ---------- riders and vehicles ---------- */
  function fillVehicle(form) {
    var opt = form.elements.driver_id.selectedOptions[0], v = {};
    try { v = JSON.parse(opt.getAttribute('data-v') || '{}'); } catch (e) { v = {}; }
    Object.keys(v).forEach(function (k) {
      var els = $$('[name="' + k + '"]', form);
      els.forEach(function (el) { if (el.type === 'checkbox') el.checked = !!v[k]; else if (el.type !== 'hidden') el.value = v[k] === 0 && /cargo/.test(k) ? '' : v[k]; });
    });
  }
  function mountMini() {
    var el = $('[data-sr-mini]');
    if (!el || !cfg.mini || !window.SLRouteMap) return;
    try {
      mini = window.SLRouteMap.mount(el, { depot: cfg.mini.depot, stops: cfg.mini.stops, path: cfg.mini.path, riders: cfg.mini.pos ? [{ id: 1, name: cfg.mini.name, lat: cfg.mini.pos.lat, lng: cfg.mini.pos.lng, color: '#0f172a', at: cfg.mini.pos.at }] : [], color: '#44433F', provider: 'schematic', interactive: false });
    } catch (e) { /* the figures below the map still tell the story */ }
  }

  /* ---------- events (bound once; the root is swapped, the document is not) ---------- */
  function bindOnce() {
    document.addEventListener('click', function (e) {
      var t = e.target, el;
      if (!t.closest || !t.closest('[data-sr-root]')) return;
      if (t.closest('#sr-create [data-sl-close]') || t.id === 'sr-create') discardPlan();
      if ((el = t.closest('[data-sr-pick]'))) { pick(el.getAttribute('data-sr-pick')); return; }
      if ((el = t.closest('[data-sr-create]'))) { if (!el.disabled) openCreate(); return; }
      if ((el = t.closest('[data-sr-filter-toggle]'))) { var box = document.getElementById('sr-filters'); box.hidden = !box.hidden; el.setAttribute('aria-expanded', box.hidden ? 'false' : 'true'); return; }
      if ((el = t.closest('[data-sr-filter]'))) { $$('[data-sr-filter]').forEach(function (c) { c.classList.toggle('on', c === el); c.setAttribute('aria-pressed', c === el ? 'true' : 'false'); }); filterRiders(); return; }
      if ((el = t.closest('[data-sr-edit]'))) { setEdit(!isEdit()); return; }
      if ((el = t.closest('[data-sr-move]'))) { if (!busy) moveStop(el.closest('[data-sr-stop]'), el.getAttribute('data-sr-move')); return; }
      if ((el = t.closest('[data-sr-remove]'))) {
        var sid = el.getAttribute('data-sr-remove');
        ask('Remove this stop?', (el.getAttribute('data-name') || 'This delivery') + ' goes back to the deliveries waiting for a rider. The customer is not told.', 'Remove stop').then(function (yes) {
          if (!yes) return;
          call('remove_stop', { route_id: routeId(), stop_id: sid }).then(function (j) { if (j && j.success) SL.toast(j.message); else fail(j, 'The stop could not be removed.'); refresh(); });
        });
        return;
      }
      if ((el = t.closest('[data-sr-reopt]'))) { call('reoptimise', { route_id: routeId() }).then(function (j) { if (j && j.success) SL.toast(j.message); else fail(j, 'The order could not be optimised.'); refresh(); }); return; }
      if ((el = t.closest('[data-sr-add]'))) { document.getElementById('sr-add').hidden = true; addStop(el.getAttribute('data-sr-add')); return; }
      if ((el = t.closest('[data-sr-stop-open]'))) { openStop(el.getAttribute('data-sr-stop-open')); return; }
      if ((el = t.closest('[data-sr-save]'))) {
        if (el.disabled || !routeId()) return;
        call('save_route', { route_id: routeId() }).then(function (j) { if (j && j.success) { SL.toast(j.message); refresh(); } else fail(j, 'The route could not be saved.'); });
        return;
      }
      if ((el = t.closest('[data-sr-send]'))) {
        var name = ($('.sr-rhead .sr-rider-t b') || {}).textContent || 'the rider';
        ask('Send the route to ' + name + '?', name + ' gets a notification in the rider app and a WhatsApp and text message with a link to the route.', 'Send route').then(function (yes) {
          if (!yes) return;
          call('send_route', { route_id: routeId() }).then(function (j) { if (j && j.success) SL.toast(j.message); else fail(j, 'The route could not be sent.'); refresh(); });
        });
        return;
      }
      if ((el = t.closest('[data-sr-fav]'))) {
        var on = el.getAttribute('aria-pressed') !== 'true';
        call('favourite', { driver_id: el.getAttribute('data-sr-fav'), on: on ? 1 : 0 }).then(function (j) { if (j && j.success) location.reload(); else fail(j, 'Could not update favourites.'); });
        return;
      }
      if ((el = t.closest('[data-sr-veh-open]'))) {
        var dlg = document.getElementById('sr-vehicle'), form = $('[data-sr-vehicle-form]');
        if (!dlg || !form) return;
        var want = el.getAttribute('data-sr-veh-open');
        if (want && $('option[value="' + want + '"]', form.elements.driver_id)) form.elements.driver_id.value = want;
        fillVehicle(form); dlg.hidden = false; setTimeout(function () { form.elements.driver_id.focus(); }, 30);
        return;
      }
    });
    document.addEventListener('input', function (e) {
      var t = e.target;
      if (!t.matches) return;
      if (t.matches('[data-sr-rider-search]')) filterRiders();
      else if (t.matches('[data-sr-del-search]')) { filterDels(); tally(); }
      else if (t.matches('[data-sr-stop-search]')) filterStops();
      else if (t.matches('[data-sr-add-search]')) { var q = t.value.trim().toLowerCase(); $$('[data-sr-add]').forEach(function (b) { b.hidden = !!q && b.getAttribute('data-search').indexOf(q) === -1; }); }
      else if (t.matches('[data-sr-rv-search]')) {
        var q2 = t.value.trim().toLowerCase(), n = 0;
        $$('.sr-rvrow').forEach(function (r) { var show = !q2 || r.getAttribute('data-search').indexOf(q2) !== -1; r.hidden = !show; if (show) n++; });
        $$('.sr-rvg').forEach(function (g) { if (q2) g.open = true; });
        var none = $('[data-sr-rv-none]'); if (none) none.hidden = n > 0;
      }
      else if (t.closest('[data-sr-create-form]') && (t.name === 'max_stops' || t.name === 'max_pieces' || t.name === 'max_amount')) tally();
    });
    document.addEventListener('change', function (e) {
      var t = e.target;
      if (!t.matches) return;
      if (t.matches('[data-sr-date]')) { if (t.value) location.href = (cfg.self || 'smart_routing.php') + '?date=' + encodeURIComponent(t.value); }
      else if (t.matches('[data-sr-f-rider]') || t.matches('[data-sr-scope]')) syncCreate();
      else if (t.matches('[data-sr-all]')) { $$('.sr-del:not([hidden]) input', createForm()).forEach(function (c) { c.checked = t.checked; }); tally(); }
      else if (t.matches('input[name="request_ids[]"]')) tally();
      else if (t.matches('[data-sr-stop-filter]')) filterStops();
      else if (t.matches('[data-sr-veh-rider]')) fillVehicle(t.form);
    });
    document.addEventListener('submit', function (e) {
      var f = e.target;
      if (f.matches('[data-sr-create-form]')) return submitCreate(e);
      if (f.matches('[data-sr-change-rider]')) {
        e.preventDefault();
        var to = f.elements.rider_id.value, r = rider(to);
        if (!to) return;
        ask('Hand the route over?', 'Every open stop moves to ' + (r ? r.name : 'the chosen rider') + ' and the order is worked out again for their vehicle.', 'Hand over').then(function (yes) {
          if (!yes) return;
          withForce('change_rider', { route_id: routeId(), rider_id: to }, 'Hand over anyway?').then(function (j) {
            if (j && j.cancelled) return;
            if (!j || !j.success) { fail(j, 'The route could not be handed over.'); return refresh(); }
            SL.toast(j.message); refresh(pageUrl({ route: j.route_id }));
          });
        });
        return;
      }
      if (f.matches('[data-sr-note-form]')) {
        e.preventDefault();
        var fd = { stop_id: f.getAttribute('data-stop'), note: f.elements.note.value };
        call('note_save', fd).then(function (j) {
          if (!j || !j.success) return fail(j, 'The note could not be saved.');
          SL.toast(j.message);
          var meta = $('[data-sr-note-meta]', f); if (meta) meta.textContent = 'Saved ' + j.note.at;
          var li = $('[data-sr-stop="' + fd.stop_id + '"]'); if (li) li.setAttribute('data-noted', j.note.text ? '1' : '0');
        });
        return;
      }
      if (f.matches('[data-sr-vehicle-form]')) {
        e.preventDefault();
        var data = {}; new FormData(f).forEach(function (v, k) { data[k] = v; });
        call('vehicle_save', data).then(function (j) { if (j && j.success) { SL.toast(j.message); location.href = pageUrl({ noDate: 1, view: 'riders', rider: data.driver_id }); } else fail(j, 'The vehicle could not be saved.'); });
      }
    });
    /* pin and row stay in step: pointing at or tabbing to a stop highlights its pin */
    document.addEventListener('mouseover', function (e) { var li = e.target.closest && e.target.closest('[data-sr-stop]'); if (li && map && !dragEl) { try { map.focus(li.getAttribute('data-sr-stop')); } catch (x) { /* ignore */ } } });
    document.addEventListener('focusin', function (e) { var li = e.target.closest && e.target.closest('[data-sr-stop]'); if (li && map) { try { map.focus(li.getAttribute('data-sr-stop')); } catch (x) { /* ignore */ } } });
    /* drag to re-order (edit mode) */
    var dragEl = null;
    function clearDrop() { $$('.drop-before,.drop-after').forEach(function (x) { x.classList.remove('drop-before', 'drop-after'); }); }
    document.addEventListener('dragstart', function (e) {
      var li = e.target.closest && e.target.closest('[data-sr-stop][data-movable]');
      if (!li || !isEdit() || busy) return;
      dragEl = li; li.classList.add('dragging');
      try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', li.getAttribute('data-sr-stop')); } catch (x) { /* ignore */ }
    });
    document.addEventListener('dragover', function (e) {
      if (!dragEl) return;
      var li = e.target.closest && e.target.closest('[data-sr-stop][data-movable]');
      if (!li || li === dragEl) return;
      e.preventDefault();
      clearDrop();
      var r = li.getBoundingClientRect();
      li.classList.add(e.clientY < r.top + r.height / 2 ? 'drop-before' : 'drop-after');
    });
    document.addEventListener('drop', function (e) {
      if (!dragEl) return;
      var li = e.target.closest && e.target.closest('[data-sr-stop][data-movable]');
      if (!li || li === dragEl) return;
      e.preventDefault();
      var before = li.classList.contains('drop-before');
      li.parentNode.insertBefore(dragEl, before ? li : li.nextSibling);
      clearDrop(); dragEl.classList.remove('dragging'); dragEl = null;
      sendOrder();
    });
    document.addEventListener('dragend', function () { clearDrop(); if (dragEl) dragEl.classList.remove('dragging'); dragEl = null; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { var open = $$('.sl-dialog:not([hidden])'); if (open.length) { if (open[open.length - 1].id === 'sr-create') discardPlan(); open[open.length - 1].hidden = true; } } });
  }

  /* ---------- live refresh of a route under way ---------- */
  function poll() {
    clearInterval(pollTimer);
    var el = $('[data-sr-route]');
    if (!el || el.getAttribute('data-sr-status') === 'done' || cfg.isPast) return;
    pollTimer = setInterval(function () {
      if (document.hidden || busy || isEdit() || $('.sl-dialog:not([hidden])')) return;
      var a = document.activeElement; if (a && /INPUT|TEXTAREA|SELECT/.test(a.tagName)) return;
      refresh();
    }, 30000);
  }

  function init() {
    readCfg();
    selRider = cfg.view === 'overview' ? (cfg.selRider || 0) : 0;
    if (cfg.view === 'riders') { mountMini(); return; }
    mountMap();
    if (cfg.view === 'overview' && selRider) { selRider = 0; pick(cfg.selRider, false); }
    if (cfg.view === 'route') { var on = false; try { on = sessionStorage.getItem(editKey()) === '1'; } catch (e) { on = false; } setEdit(on && !!$('[data-sr-edit]')); }
    poll();
  }

  bindOnce();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
