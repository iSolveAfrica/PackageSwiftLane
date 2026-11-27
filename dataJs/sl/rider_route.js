/* Rider app: today's route (views/sl/pages/partials/rider_route_today.php, rider_load_checklist.php,
 * rider_stop_card.php). Talks only to ajax/sl/rider_route_ajax.php (today, ping, load_scan,
 * load_toggle, start, arrive, sync, reoptimise, activity) and ajax/sl/delivery_ajax.php op=fail.
 *
 * - Map: SLRouteMap (Leaflet + OSM online, schematic SVG offline).
 * - Position: watchPosition only while the route is loading/on the way and the page is visible;
 *   a ping is sent at most every ping_min_s seconds and only after ping_min_m metres (or a
 *   heartbeat every 5 x ping_min_s). Pings are never queued: only the latest position is sent.
 * - Nearest-first and live ETAs are computed locally (haversine x road factor) between refreshes.
 * - Offline: the last op=today JSON is kept for the day in localStorage (per rider, dropped after
 *   18 h); while offline the open page stays usable (links to other screens are held back instead
 *   of loading the browser's offline page) and actions show Retry next to the button. Nothing is queued.
 */
(function () {
  'use strict';
  var API = 'ajax/sl/rider_route_ajax.php';
  var SNAP = 'sl.rider.route';
  var dataEl = document.getElementById('rr-data');
  var D = null;
  try { D = dataEl ? JSON.parse(dataEl.textContent) : null; } catch (e) { D = null; }

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var S = (D && D.settings) || {};
  var PING_S = Math.max(5, +S.ping_min_s || 20), PING_M = Math.max(5, +S.ping_min_m || 40);
  var LATE_S = Math.max(60, (+S.late_min || 15) * 60);
  var SPEED = Math.max(5, +S.speed_kmh || 20) / 3.6, ROAD = +S.road_factor || 1.35;

  var pos = null;            // latest device position {lat, lng, accuracy, speed, heading, ts}
  var lastSent = null;       // last position the server stored {lat, lng, ts}
  var watchId = null, beatTimer = null, refreshTimer = null, battery = null;
  var maps = [], retryFn = null, sortMode = 'route';

  function hav(a, b) {
    var R = 6371000, toR = Math.PI / 180;
    var dLat = (b[0] - a[0]) * toR, dLng = (b[1] - a[1]) * toR;
    var x = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(a[0] * toR) * Math.cos(b[0] * toR) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(x)));
  }
  function km(m) { return m < 1000 ? Math.round(m) + ' m' : (m / 1000).toFixed(m < 10000 ? 1 : 0) + ' km'; }
  // Clock times in the office time zone (the server's), like the server-rendered ETAs.
  var TZ = D && typeof D.tz_offset_min === 'number' ? D.tz_offset_min : -new Date().getTimezoneOffset();
  function hhmm(t) { var d = new Date(new Date(t).getTime() + TZ * 60000); return ('0' + d.getUTCHours()).slice(-2) + ':' + ('0' + d.getUTCMinutes()).slice(-2); }
  function toast(m, bad) { if (window.SL && SL.toast) SL.toast(m, bad); }
  function post(data) { return window.SL && SL.post ? SL.post(API, data) : Promise.reject(new Error('App not ready')); }

  /* ---------------- offline banner + retry (no mutation queue) ---------------- */
  var banner = $('[data-rr-offline]');
  function offline(msg, retry) {
    retryFn = retry || refresh;
    if (!banner) return;
    var t = $('[data-rr-offline-msg]', banner);
    if (t) t.textContent = msg;
    banner.hidden = false;
  }
  function online() { if (banner) banner.hidden = true; retryFn = null; }
  var retryBtn = $('[data-rr-retry]');
  if (retryBtn) retryBtn.addEventListener('click', function () { var f = retryFn || refresh; online(); f(); });
  var SNAP_MAX = 18 * 3600 * 1000;
  function readSnap() {
    try {
      var s = JSON.parse(localStorage.getItem(SNAP) || 'null');
      if (!s || !s.at || Date.now() - s.at > SNAP_MAX || (D && D.route_id && s.route_id && s.route_id !== D.route_id && Date.now() - s.at > 3600000)) { localStorage.removeItem(SNAP); return null; }
      return s;
    } catch (e) { return null; }
  }
  function writeSnap(route) {
    try {
      if (!route) { localStorage.removeItem(SNAP); return; }
      localStorage.setItem(SNAP, JSON.stringify({ at: Date.now(), route_id: route.route && route.route.id, route: route }));
    } catch (e) { /* quota or storage blocked */ }
  }
  try { sessionStorage.removeItem(SNAP); } catch (e) { /* older builds kept it here */ }
  function snapshotAt() { var s = readSnap(); return s ? hhmm(s.at) : ''; }
  function offlineMsg() { var at = snapshotAt(); return 'You are offline. Showing your route' + (at ? ' from ' + at : ' as last loaded') + '. Actions need a connection.'; }
  window.addEventListener('offline', function () { offline(offlineMsg()); });
  window.addEventListener('online', function () { online(); sendPing(true); refresh(); });
  if (navigator.onLine === false && D) offline(offlineMsg());

  /* Inline "not saved" note with its own Retry, right under the button the rider pressed
   * (the banner at the top may be scrolled out of view). */
  function errHolder(btn) { return btn && btn.closest ? (btn.closest('.rr-do, .rr-actions, .rr-start, .rr-stops, .rr-stats') || btn.parentNode) : null; }
  function inlineErr(btn, msg, retry) {
    var holder = errHolder(btn);
    if (!holder) return;
    var box = holder.querySelector(':scope > .rr-inline-err');
    if (!box) {
      box = document.createElement('p'); box.className = 'rr-inline-err'; box.setAttribute('role', 'alert');
      box.innerHTML = '<span></span><button type="button" class="sl-btn sm">Retry</button>';
      holder.appendChild(box);
    }
    box.firstChild.textContent = msg;
    box.lastChild.onclick = function () { box.parentNode.removeChild(box); online(); retry(); };
  }
  function clearInlineErr(btn) {
    var holder = errHolder(btn), box = holder && holder.querySelector(':scope > .rr-inline-err');
    if (box) box.parentNode.removeChild(box);
  }
  /* Hold back page navigation while offline: the open page still has every stop, Navigate,
   * Call and WhatsApp; a new page load would only show the browser's offline screen. */
  document.addEventListener('click', function (e) {
    if (navigator.onLine !== false || !D) return;
    var a = e.target.closest('a[href]');
    if (!a) return;
    var href = a.getAttribute('href') || '';
    if (a.target === '_blank' || href.charAt(0) === '#' || /^(tel|sms|mailto|maps|comgooglemaps):/i.test(href)) return;
    if (a.origin && a.origin !== location.origin) return;
    e.preventDefault();
    offline('You are offline. Stay on this screen: your stops, Navigate and Call still work. Other screens open when you are back online.');
  }, true);

  /* Run a write; on network failure show Retry for exactly this action. */
  function act(btn, data, okMsg, after) {
    if (btn) { if (btn.getAttribute('aria-busy') === 'true') return Promise.resolve(); btn.setAttribute('aria-busy', 'true'); btn.disabled = true; }
    var run = function () { return act(btn, data, okMsg, after); };
    return post(data).then(function (j) {
      if (!j.success) throw Object.assign(new Error(j.message || 'Could not save. Please retry.'), { server: true, j: j });
      online(); clearInlineErr(btn);
      toast(j.message || okMsg || 'Saved');
      if (after) after(j); else setTimeout(function () { location.reload(); }, 350);
      return j;
    }).catch(function (err) {
      if (!err.server && (navigator.onLine === false || err instanceof TypeError)) { offline('No connection. Nothing was saved.', run); inlineErr(btn, 'No connection. Not saved.', run); }
      else toast(err.message, true);
      throw err;
    }).finally(function () { if (btn) { btn.removeAttribute('aria-busy'); btn.disabled = false; } });
  }

  /* ---------------- maps ---------------- */
  function riderMarker() {
    var p = pos || (D && D.map && D.map.rider_pos);
    return p ? [{ id: 'me', name: 'You', lat: +p.lat, lng: +p.lng, color: '#2563eb', status: D.status }] : [];
  }
  function mountMaps() {
    if (!window.SLRouteMap) return;
    if (D && D.map) {
      $$('[data-rr-map]').forEach(function (el) {
        var m = SLRouteMap.mount(el, { depot: D.map.depot, stops: D.map.stops || [], path: D.map.path || null, riders: riderMarker(), provider: 'auto', interactive: true, onStopClick: function (id) { pick(id, true); } });
        if (m) { maps.push(m); if (D.next_id) setTimeout(function () { try { m.focus(D.next_id); } catch (e) { /* map not ready */ } }, 600); }
      });
    }
    $$('[data-rr-extra-map]').forEach(function (el) {
      var stops = [];
      try { stops = JSON.parse(el.getAttribute('data-stops') || '[]'); } catch (e) { stops = []; }
      SLRouteMap.mount(el, { stops: stops, provider: 'auto', interactive: true });
    });
  }
  var lastMapUpdate = 0;
  function updateRiderOnMap(force) {
    if (!maps.length || (!force && Date.now() - lastMapUpdate < 15000)) return;
    lastMapUpdate = Date.now();
    maps.forEach(function (m) { m.update({ riders: riderMarker() }); });
  }

  /* ---------------- stop selection, navigation, sorting ---------------- */
  function pick(id, user) {
    id = String(id);
    var card = document.getElementById('rr-stop-' + id);
    if (!card) return;
    $$('[data-rr-card]').forEach(function (c) { c.hidden = c !== card; });
    $$('[data-rr-li]').forEach(function (li) { li.classList.toggle('is-sel', li.getAttribute('data-rr-li') === id); });
    maps.forEach(function (m) { try { m.focus(id); } catch (e) { /* ignore */ } });
    if (user) {
      var layout = document.documentElement.getAttribute('data-rider-layout');
      if (layout !== 'web' || window.innerWidth < 900) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }
  document.addEventListener('click', function (e) {
    var p = e.target.closest('[data-rr-pick]');
    if (!p) return;
    e.preventDefault();
    pick(p.getAttribute('data-rr-pick'), true);
  });
  var apple = /iPhone|iPad|iPod/.test(navigator.userAgent) || (/Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1);
  /* iPhone/iPad: Navigate opens Apple Maps unless the rider once picked Google Maps (remembered);
   * the "Google Maps" link beside it is shown on iOS only. */
  var NAVPREF = 'sl.rider.nav';
  function navPref() { try { return localStorage.getItem(NAVPREF) || ''; } catch (e) { return ''; } }
  if (apple) {
    document.documentElement.classList.add('rr-ios');
    if (navPref() !== 'google') $$('[data-rr-nav]').forEach(function (a) { var u = a.getAttribute('data-apple'); if (u) a.href = u; });
    document.addEventListener('click', function (e) {
      if (!e.target.closest('[data-rr-nav-google]')) return;
      try { localStorage.setItem(NAVPREF, 'google'); } catch (x) { /* ignore */ }
      $$('[data-rr-nav]').forEach(function (a) { var u = a.getAttribute('data-google'); if (u) a.href = u; });
    });
  }

  function origin() {
    if (pos) return [pos.lat, pos.lng];
    if (D && D.map && D.map.rider_pos) return [+D.map.rider_pos.lat, +D.map.rider_pos.lng];
    if (D && D.map && D.map.depot) return [+D.map.depot.lat, +D.map.depot.lng];
    return null;
  }
  function applySort() {
    var list = $('[data-rr-list]');
    if (!list) return;
    var o = origin();
    var items = $$('[data-rr-li]', list);
    items.forEach(function (li) {
      if (o && li.getAttribute('data-open') === '1') li._d = hav(o, [+li.getAttribute('data-lat'), +li.getAttribute('data-lng')]);
      else li._d = Infinity;
    });
    items.sort(function (a, b) {
      var ao = a.getAttribute('data-open') === '1' ? 0 : 1, bo = b.getAttribute('data-open') === '1' ? 0 : 1;
      if (sortMode === 'near') return ao - bo || a._d - b._d || (+a.getAttribute('data-seq') - +b.getAttribute('data-seq'));
      return +a.getAttribute('data-seq') - +b.getAttribute('data-seq');
    });
    items.forEach(function (li) { list.appendChild(li); });
  }
  /* One fresh fix on demand (Nearest first / Re-optimise), in any route state: the server only
   * has positions sent while loading or on the way, and ignores old ones. */
  function locateOnce(timeoutMs) {
    return new Promise(function (resolve) {
      if (!('geolocation' in navigator)) return resolve(pos);
      if (pos && Date.now() - pos.ts < 60000) return resolve(pos);
      var done = false, t = setTimeout(function () { if (!done) { done = true; resolve(pos); } }, timeoutMs || 6000);
      try {
        navigator.geolocation.getCurrentPosition(function (p) {
          if (done) return; done = true; clearTimeout(t);
          pos = { lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy, speed: p.coords.speed, heading: p.coords.heading, ts: p.timestamp || Date.now() };
          liveEtas(); updateRiderOnMap(true); resolve(pos);
        }, function () { if (!done) { done = true; clearTimeout(t); resolve(pos); } }, { enableHighAccuracy: true, maximumAge: 30000, timeout: timeoutMs || 6000 });
      } catch (e) { done = true; clearTimeout(t); resolve(pos); }
    });
  }
  $$('[data-rr-sort]').forEach(function (b) {
    b.addEventListener('click', function () {
      sortMode = b.getAttribute('data-rr-sort');
      if (sortMode === 'near') locateOnce(5000).then(function () { if (sortMode === 'near') applySort(); });
      $$('[data-rr-sort]').forEach(function (x) { var on = x === b; x.classList.toggle('on', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      applySort();
    });
  });

  /* ---------------- live distance + ETA from the rider's position ---------------- */
  function liveEtas() {
    if (!D || !pos) return;
    var o = [pos.lat, pos.lng];
    (D.stops || []).forEach(function (s) {
      var el = $('[data-rr-away="' + s.id + '"]');
      if (el && (s.status === 'pending' || s.status === 'arrived')) el.textContent = km(hav(o, [s.lat, s.lng])) + ' away';
    });
    if (D.status !== 'onroute') return;
    var open = (D.stops || []).filter(function (s) { return s.status === 'pending' || s.status === 'arrived'; });
    var next = open.filter(function (s) { return s.id === D.next_id; })[0] || open[0];
    if (!next || !next.eta) return;
    var secs = hav(o, [next.lat, next.lng]) * ROAD / SPEED;
    var live = Date.now() + secs * 1000;
    var delta = live - new Date(next.eta).getTime();
    var shift = Math.abs(delta) > 120000 ? delta : 0;
    open.forEach(function (s) {
      if (!s.eta) return;
      var t = new Date(s.eta).getTime() + (s === next ? delta : shift);
      $$('[data-rr-eta="' + s.id + '"]').forEach(function (e) { e.textContent = hhmm(t); e.title = 'Live estimate from your position'; });
    });
    var behind = $('[data-rr-behind]');
    if (behind) behind.hidden = !(delta > LATE_S * 1000 && open.length > 1);
    if (sortMode === 'near') applySort();
  }

  /* ---------------- position pings (throttled, never queued) ---------------- */
  /* Tracking runs while the route is loading or on the way. The first ticked or scanned parcel
   * turns a planned route into 'loading' (markLoading), so tracking starts without a reload. */
  function tracking() { return D && (D.status === 'onroute' || D.status === 'loading') && 'geolocation' in navigator; }
  function markLoading() {
    if (!D || D.status !== 'planned') return;
    D.status = 'loading';
    startWatch();
  }
  function shouldSend(now) {
    if (!pos) return false;
    if (!lastSent) return true;
    var age = (now - lastSent.ts) / 1000;
    var moved = hav([lastSent.lat, lastSent.lng], [pos.lat, pos.lng]);
    return (age >= PING_S && moved >= PING_M) || age >= PING_S * 5;
  }
  var pinging = false;
  function sendPing(force) {
    if (!pos || pinging || navigator.onLine === false) return;
    var now = Date.now();
    if (!force && !shouldSend(now)) return;
    if (force && lastSent && (now - lastSent.ts) / 1000 < PING_S) return;
    if (pos.accuracy && pos.accuracy > 500) return;
    pinging = true;
    var p = { op: 'ping', lat: pos.lat.toFixed(7), lng: pos.lng.toFixed(7), accuracy: pos.accuracy != null ? Math.round(pos.accuracy) : '', speed: pos.speed != null ? pos.speed : '', heading: pos.heading != null ? Math.round(pos.heading) : '', battery: battery != null ? battery : '', ts: pos.ts };
    post(p).then(function (j) {
      if (j && j.success) { lastSent = { lat: pos.lat, lng: pos.lng, ts: now }; window.__rrPings = (window.__rrPings || 0) + (j.stored ? 1 : 0); window.__rrPingCalls = (window.__rrPingCalls || 0) + 1; }
    }).catch(function () { /* dropped on purpose: the next position replaces it */ }).finally(function () { pinging = false; });
  }
  function onPos(p) {
    pos = { lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy, speed: p.coords.speed, heading: p.coords.heading, ts: p.timestamp || Date.now() };
    sendPing(false);
    liveEtas();
    updateRiderOnMap(false);
  }
  function startWatch() {
    if (!tracking() || watchId !== null || document.visibilityState === 'hidden') return;
    try {
      watchId = navigator.geolocation.watchPosition(onPos, function (err) {
        if (err && err.code === 1) { stopWatch(); var n = $('.rr-est'); if (n && !n.dataset.geo) { n.dataset.geo = '1'; n.textContent += ' Location is off: dispatch cannot see where you are.'; } }
      }, { enableHighAccuracy: true, maximumAge: 10000, timeout: 30000 });
    } catch (e) { watchId = null; }
    clearInterval(beatTimer);
    beatTimer = setInterval(function () { sendPing(false); }, PING_S * 1000);
  }
  function stopWatch() {
    if (watchId !== null) { try { navigator.geolocation.clearWatch(watchId); } catch (e) { /* ignore */ } }
    watchId = null;
    clearInterval(beatTimer);
  }
  if (navigator.getBattery) navigator.getBattery().then(function (b) { var f = function () { battery = Math.round(b.level * 100); }; f(); b.addEventListener('levelchange', f); }).catch(function () { /* unsupported */ });

  /* ---------------- refresh + snapshot ---------------- */
  function signature(r) { return r ? [r.route.version, r.route.status].concat(r.stops.map(function (s) { return s.id + s.status; })).join('|') : 'none'; }
  var currentSig = null;
  function refresh() {
    if (!window.SL || !SL.get) return Promise.resolve();
    return SL.get(API, { op: 'today' }).then(function (j) {
      if (!j || !j.success) throw new Error((j && j.message) || 'Route unavailable');
      writeSnap(j.route);
      online();
      if (D && j.route && j.route.route && j.route.route.id === D.route_id && j.route.route.status !== D.status) {
        D.status = j.route.route.status;
        if (tracking()) startWatch(); else stopWatch();
      }
      var sig = signature(j.route);
      if (currentSig === null) { currentSig = sig; return; }
      if (sig !== currentSig) {
        var busy = document.querySelector('dialog[open]') || (document.activeElement && /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName));
        if (!busy) location.reload(); else currentSig = sig;
      }
    }).catch(function () { offline(offlineMsg()); });
  }
  function schedule() {
    clearInterval(refreshTimer);
    if (!D) return;
    refreshTimer = setInterval(function () { if (document.visibilityState === 'visible' && navigator.onLine !== false) refresh(); }, 60000);
  }
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') stopWatch();
    else { startWatch(); if (D) refresh(); }
  });

  /* ---------------- route actions ---------------- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-rr-op]');
    if (!b || !D) return;
    var op = b.getAttribute('data-rr-op');
    if (op === 'arrive') act(b, { op: 'arrive', route_id: D.route_id, stop_id: b.getAttribute('data-stop') }, 'Arrived').catch(function () {});
    else if (op === 'start') act(b, { op: 'start', route_id: D.route_id }, 'Route started', function () { setTimeout(function () { location.href = location.pathname; }, 400); }).catch(function () {});
    else if (op === 'reoptimise') {
      if (b.getAttribute('aria-busy') === 'true') return;
      b.setAttribute('aria-busy', 'true');
      locateOnce(6000).then(function () {
        b.removeAttribute('aria-busy');
        var o = pos ? { lat: pos.lat.toFixed(7), lng: pos.lng.toFixed(7) } : {};
        act(b, Object.assign({ op: 'reoptimise', route_id: D.route_id }, o), 'Stops re-ordered').catch(function () {});
      });
    }
  });
  $$('[data-rr-break]').forEach(function (b) {
    b.addEventListener('click', function () { act(b, { op: 'activity', state: b.getAttribute('data-rr-break') }).catch(function () {}); });
  });

  /* Failed with reason: delivery_ajax op=fail (existing workflow), then sync the route. */
  var dlg = $('[data-rr-fail-dialog]'), failReq = null;
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-rr-fail]');
    if (!b || !dlg) return;
    failReq = b.getAttribute('data-rr-fail');
    $('[data-rr-fail-name]', dlg).textContent = b.getAttribute('data-name') || '';
    $('[data-rr-fail-err]', dlg).hidden = true;
    $('[data-rr-fail-form]', dlg).reset();
    if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', '');
  });
  if (dlg) {
    $('[data-rr-fail-cancel]', dlg).addEventListener('click', function () { dlg.close ? dlg.close() : dlg.removeAttribute('open'); });
    $('[data-rr-fail-form]', dlg).addEventListener('submit', function (e) {
      e.preventDefault();
      var f = e.target, btn = f.querySelector('[type=submit]'), err = $('[data-rr-fail-err]', dlg);
      var reason = (f.querySelector('input[name=reason]:checked') || {}).value || '';
      var note = f.note.value.trim();
      if (!reason) { err.textContent = 'Choose what happened.'; err.hidden = false; return; }
      if (reason === 'Other' && note.length < 3) { err.textContent = 'Tell dispatch what happened.'; err.hidden = false; return; }
      var text = (reason === 'Other' ? note : reason + (note ? ': ' + note : '')).slice(0, 500);
      btn.disabled = true; btn.setAttribute('aria-busy', 'true');
      SL.post('ajax/sl/delivery_ajax.php', { op: 'fail', id: failReq, note: text }).then(function (j) {
        if (!j.success) throw Object.assign(new Error(j.message || 'Could not report the failed delivery'), { server: true });
        return post({ op: 'sync', request_id: failReq }).catch(function () { return null; });
      }).then(function () {
        dlg.close ? dlg.close() : dlg.removeAttribute('open');
        toast('Reported to dispatch. Keep the parcels on board.');
        setTimeout(function () { location.reload(); }, 400);
      }).catch(function (x) {
        err.textContent = x.server ? x.message : 'No connection. Nothing was saved. Try again when you are online.';
        err.hidden = false;
      }).finally(function () { btn.disabled = false; btn.removeAttribute('aria-busy'); });
    });
  }

  /* ---------------- loading checklist ---------------- */
  var loadRoot = $('[data-rr-load]');
  function setInventory(inv) {
    if (!inv || !loadRoot) return;
    var l = $('[data-rr-loaded]', loadRoot); if (l) l.textContent = inv.loaded;
    var m = $('[data-rr-missing]', loadRoot); if (m) m.textContent = inv.missing;
    var mw = $('[data-rr-missing-wrap]', loadRoot); if (mw) mw.hidden = !(+inv.missing > 0);
    var bar = $('[data-rr-loadbar]', loadRoot);
    var done = (+inv.loaded) + (+inv.missing);
    if (bar) { bar.style.width = (inv.total ? Math.round(done / inv.total * 100) : 0) + '%'; bar.parentNode.setAttribute('aria-valuenow', done); }
    var start = $('[data-rr-op="start"]');
    var ready = inv.total > 0 && +inv.pending_load === 0;
    if (start) start.disabled = !ready;
    var hint = $('[data-rr-start-hint]'); if (hint) hint.textContent = ready ? 'Everything is checked. Missing parcels go back to dispatch.' : (inv.pending_load + ' parcel' + (inv.pending_load == 1 ? '' : 's') + ' still to check.');
  }
  function setRow(row, state) {
    row.classList.remove('ls-pending', 'ls-loaded', 'ls-missing');
    row.classList.add('ls-' + state);
    var cb = $('[data-rr-tick]', row); if (cb) { cb.checked = state === 'loaded'; cb.disabled = state === 'missing'; }
    var mb = $('[data-rr-missing-btn]', row); if (mb) { mb.textContent = state === 'missing' ? 'Found it' : 'Missing'; mb.setAttribute('aria-pressed', state === 'missing' ? 'true' : 'false'); }
  }
  if (loadRoot && D) {
    var msg = $('[data-rr-scan-msg]', loadRoot);
    var say = function (t, bad) { if (msg) { msg.textContent = t; msg.classList.toggle('is-bad', !!bad); } };
    var scanForm = $('[data-rr-scan]', loadRoot);
    if (scanForm) scanForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var input = scanForm.code, code = input.value.trim();
      if (!code) return;
      var btn = scanForm.querySelector('[type=submit]');
      btn.disabled = true;
      post({ op: 'load_scan', route_id: D.route_id, code: code }).then(function (j) {
        if (!j.success) { say(j.message || 'Not on your route', true); if (navigator.vibrate) navigator.vibrate([80, 60, 80]); return; }
        var p = j.parcel || {};
        var row = $('[data-rr-parcel="' + (p.module || 'air') + ':' + p.order_id + '"]', loadRoot);
        if (row) { setRow(row, 'loaded'); row.classList.add('is-flash'); setTimeout(function () { row.classList.remove('is-flash'); }, 1200); }
        setInventory(j.inventory);
        markLoading();
        say(j.message || 'Loaded', false);
        if (navigator.vibrate) navigator.vibrate(40);
        input.value = '';
        online();
      }).catch(function () { offline('No connection. The parcel was not recorded.', function () { scanForm.requestSubmit ? scanForm.requestSubmit() : scanForm.submit(); }); })
        .finally(function () { btn.disabled = false; input.focus(); });
    });
    var toggle = function (row, data, cb) {
      post(Object.assign({ op: 'load_toggle', route_id: D.route_id, module: row.getAttribute('data-module'), order_id: row.getAttribute('data-order') }, data)).then(function (j) {
        if (!j.success) throw Object.assign(new Error(j.message || 'Could not save'), { server: true });
        setRow(row, j.load_state || (data.missing ? 'missing' : (data.loaded ? 'loaded' : 'pending')));
        setInventory(j.inventory);
        markLoading();
        online();
      }).catch(function (err) {
        if (cb) cb.checked = !cb.checked;
        if (err.server) toast(err.message, true); else offline('No connection. Nothing was saved.', function () { toggle(row, data); });
      });
    };
    $$('[data-rr-tick]', loadRoot).forEach(function (cb) {
      cb.addEventListener('change', function () { toggle(cb.closest('[data-rr-parcel]'), { loaded: cb.checked ? 1 : 0, missing: 0 }, cb); });
    });
    $$('[data-rr-missing-btn]', loadRoot).forEach(function (b) {
      b.addEventListener('click', function () {
        var row = b.closest('[data-rr-parcel]'), missing = !row.classList.contains('ls-missing');
        toggle(row, { loaded: 0, missing: missing ? 1 : 0 });
      });
    });

    /* Camera scanning where the browser has BarcodeDetector; typing always works. */
    var camBtn = $('[data-rr-camera]', loadRoot), cam = $('[data-rr-cam]', loadRoot), stream = null, scanTimer = null;
    var stopCam = function () {
      clearInterval(scanTimer);
      if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
      stream = null; if (cam) cam.hidden = true;
    };
    if (camBtn && 'BarcodeDetector' in window && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      camBtn.hidden = false;
      camBtn.addEventListener('click', function () {
        var det;
        try { det = new window.BarcodeDetector(); } catch (e) { say('Camera scanning is not available. Type the tracking number.', true); return; }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (s) {
          stream = s; cam.hidden = false;
          var v = $('video', cam); v.srcObject = s; v.play();
          scanTimer = setInterval(function () {
            det.detect(v).then(function (codes) {
              if (!codes || !codes.length) return;
              scanForm.code.value = codes[0].rawValue;
              stopCam();
              scanForm.requestSubmit ? scanForm.requestSubmit() : scanForm.dispatchEvent(new Event('submit', { cancelable: true }));
            }).catch(function () { /* frame not ready */ });
          }, 350);
        }).catch(function () { say('Camera blocked. Type the tracking number instead.', true); });
      });
      var stopBtn = $('[data-rr-cam-stop]', loadRoot); if (stopBtn) stopBtn.addEventListener('click', stopCam);
      document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') stopCam(); });
    }
  }

  /* ---------------- boot ---------------- */
  function boot() {
    mountMaps();
    if (!D) return;
    currentSig = null;
    if (navigator.onLine !== false) refresh();
    schedule();
    startWatch();
    if (location.hash && /^#rr-stop-\d+$/.test(location.hash)) pick(location.hash.slice(9), false);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
  window.SLRiderRoute = { data: D, pick: pick, refresh: refresh, position: function () { return pos; } };
})();
