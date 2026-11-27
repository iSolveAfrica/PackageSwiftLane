/* Swift Lane route map: one component for the route planner (admin) and the rider app.
 *
 *   var map = SLRouteMap.mount(el, {
 *     depot: {lat, lng, label},
 *     stops: [{id, seq, lat, lng, label, status, eta, color}],
 *     routes: [{color, name, stops: [...], path: [[lat,lng]]}],   // several riders (plan view)
 *     riders: [{id, name, lat, lng, color, status}],
 *     path: [[lat, lng]] | null, provider: 'auto' | 'leaflet' | 'schematic',
 *     interactive: true, onStopClick: function (id) {}
 *   });
 *   map.update(data); map.focus(stopId); map.destroy();
 *   SLRouteMap.navUrl(stop, 'google' | 'apple' | 'auto');
 *
 * Leaflet (assets/vendor/libs/leaflet) with OpenStreetMap tiles is loaded lazily. When the device is
 * offline, or no tile loads within 4 s, the map swaps to a schematic SVG: projected stops, numbered
 * markers, the route line and straight-line distances, labelled "Schematic — map tiles offline".
 * Nothing here is cached or queued (PWA policy: private data never goes to the service worker).
 */
(function () {
  'use strict';
  if (window.SLRouteMap) return;

  var SCRIPT = document.currentScript && document.currentScript.src;
  var BASE = SCRIPT ? SCRIPT.replace(/assets\/swiftlane\/route-map\.js(\?.*)?$/, '') : '';
  var LEAFLET_JS = BASE + 'assets/vendor/libs/leaflet/leaflet.js';
  var LEAFLET_CSS = BASE + 'assets/vendor/libs/leaflet/leaflet.css';
  var TILE_TIMEOUT = 4000;
  var STATUS = {
    pending: '#2563eb', arrived: '#d97706', delivered: '#059669', failed: '#dc2626',
    returned: '#7c3aed', removed: '#94a3b8', planned: '#2563eb'
  };
  var leafletPromise = null;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function num(v) { var n = parseFloat(v); return isFinite(n) ? n : null; }
  function hasPos(p) { return p && num(p.lat) !== null && num(p.lng) !== null && !(Math.abs(p.lat) < 1e-9 && Math.abs(p.lng) < 1e-9); }
  function haversine(a, b) {
    var R = 6371008.8, r = Math.PI / 180;
    var x = Math.pow(Math.sin((b.lat - a.lat) * r / 2), 2) + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.pow(Math.sin((b.lng - a.lng) * r / 2), 2);
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(x)));
  }
  function km(m) { return m >= 1000 ? (m / 1000).toFixed(m >= 10000 ? 0 : 1) + ' km' : Math.round(m) + ' m'; }
  /* HH:MM of an ISO time. tzOffsetMin (minutes east of UTC, the office time zone the server uses)
   * keeps map tooltips on the same clock as the lists; without it, the browser's zone is used. */
  function hm(iso, tzOffsetMin) {
    if (!iso) return '';
    var d = new Date(iso);
    if (isNaN(d)) return String(iso).slice(11, 16);
    if (typeof tzOffsetMin === 'number' && isFinite(tzOffsetMin)) {
      var t = new Date(d.getTime() + tzOffsetMin * 60000);
      return ('0' + t.getUTCHours()).slice(-2) + ':' + ('0' + t.getUTCMinutes()).slice(-2);
    }
    return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }
  /* A rider position older than this is drawn faded with its age ("seen 17 h ago"). */
  var STALE_MS = 15 * 60000;
  function riderAge(r) {
    if (r.stale) return r.stale === true ? '' : String(r.stale);
    if (!r.at) return null;
    var t = new Date(String(r.at).replace(' ', 'T'));
    if (isNaN(t)) return null;
    var ms = Date.now() - t.getTime();
    if (ms < STALE_MS) return null;
    var min = Math.round(ms / 60000);
    return min >= 120 ? Math.round(min / 60) + ' h ago' : min + ' min ago';
  }

  /* Normalise input into routes: [{color, name, stops, path}] */
  function normalise(o) {
    var routes = [];
    if (o.routes && o.routes.length) {
      o.routes.forEach(function (r) {
        routes.push({ color: r.color || '#2563eb', name: r.name || '', stops: (r.stops || []).filter(hasPos), path: r.path || null });
      });
    }
    if (o.stops && o.stops.length) routes.push({ color: o.color || null, name: '', stops: o.stops.filter(hasPos), path: o.path || null });
    return {
      depot: hasPos(o.depot) ? o.depot : null,
      routes: routes,
      riders: (o.riders || []).filter(hasPos)
    };
  }
  function allPoints(n) {
    var pts = [];
    if (n.depot) pts.push(n.depot);
    n.routes.forEach(function (r) { r.stops.forEach(function (s) { pts.push(s); }); });
    n.riders.forEach(function (r) { pts.push(r); });
    return pts;
  }
  function linePoints(n, r) {
    if (r.path && r.path.length > 1) return r.path.map(function (p) { return { lat: +p[0], lng: +p[1] }; });
    var pts = [];
    if (n.depot) pts.push(n.depot);
    r.stops.slice().sort(function (a, b) { return (a.seq || 0) - (b.seq || 0); }).forEach(function (s) { if (s.status !== 'removed') pts.push(s); });
    return pts;
  }
  function stopColor(s, r) {
    if (s.status && s.status !== 'pending' && s.status !== 'planned') return STATUS[s.status] || '#64748b';
    return s.color || r.color || STATUS.pending;
  }

  function loadLeaflet() {
    if (window.L && window.L.map) return Promise.resolve(window.L);
    if (leafletPromise) return leafletPromise;
    leafletPromise = new Promise(function (resolve, reject) {
      if (!document.querySelector('link[data-slrm-leaflet]')) {
        var css = document.createElement('link');
        css.rel = 'stylesheet'; css.href = LEAFLET_CSS; css.setAttribute('data-slrm-leaflet', '1');
        document.head.appendChild(css);
      }
      var s = document.createElement('script');
      s.src = LEAFLET_JS; s.async = true;
      s.onload = function () { window.L ? resolve(window.L) : reject(new Error('Leaflet missing')); };
      s.onerror = function () { leafletPromise = null; reject(new Error('Leaflet failed to load')); };
      document.head.appendChild(s);
    });
    return leafletPromise;
  }

  /* ---------------- schematic SVG ---------------- */
  function drawSchematic(host, n, opts, reason) {
    var pts = allPoints(n);
    // Extra room at the bottom so markers never sit under the "Schematic" caption.
    var W = Math.max(280, host.clientWidth || 600), H = Math.max(200, host.clientHeight || 320), pad = 34, padB = 48;
    if (!pts.length) {
      host.innerHTML = '<div class="slrm-empty">No stops to show yet</div>';
      return;
    }
    var lat0 = pts.reduce(function (a, p) { return a + p.lat; }, 0) / pts.length;
    var kx = Math.cos(lat0 * Math.PI / 180);
    var xs = pts.map(function (p) { return p.lng * kx; }), ys = pts.map(function (p) { return p.lat; });
    var minX = Math.min.apply(null, xs), maxX = Math.max.apply(null, xs), minY = Math.min.apply(null, ys), maxY = Math.max.apply(null, ys);
    var spanX = Math.max(maxX - minX, 0.004), spanY = Math.max(maxY - minY, 0.004);
    var scale = Math.min((W - 2 * pad) / spanX, (H - pad - padB) / spanY);
    var ox = (W - spanX * scale) / 2, oy = padB + (H - pad - padB - spanY * scale) / 2;
    function P(p) { return [ox + (p.lng * kx - minX) * scale, H - (oy + (p.lat - minY) * scale)]; }
    var svg = ['<svg class="slrm-svg" viewBox="0 0 ' + W + ' ' + H + '" width="100%" height="100%" role="img" aria-label="Schematic route map with ' + pts.length + ' points">'];
    svg.push('<defs><pattern id="slrm-grid" width="24" height="24" patternUnits="userSpaceOnUse"><path d="M24 0H0V24" fill="none" class="slrm-gridline"/></pattern></defs>');
    svg.push('<rect width="100%" height="100%" fill="url(#slrm-grid)"/>');
    var totalM = 0;
    n.routes.forEach(function (r) {
      var lp = linePoints(n, r);
      if (lp.length > 1) {
        svg.push('<polyline class="slrm-line" points="' + lp.map(function (p) { var q = P(p); return q[0].toFixed(1) + ',' + q[1].toFixed(1); }).join(' ') + '" stroke="' + esc(r.color || '#2563eb') + '"/>');
        for (var i = 1; i < lp.length; i++) {
          var m = haversine(lp[i - 1], lp[i]); totalM += m;
          // Leg labels: only on single-route maps, only where the leg is long enough on screen,
          // pushed off the line along its normal so the stop markers do not cover them.
          if (lp.length <= 14 && n.routes.length === 1) {
            var a = P(lp[i - 1]), b = P(lp[i]), dx = b[0] - a[0], dy = b[1] - a[1], len = Math.sqrt(dx * dx + dy * dy);
            if (len >= 64) {
              var nx = -dy / len, ny = dx / len; if (ny > 0) { nx = -nx; ny = -ny; }
              svg.push('<text class="slrm-leg" x="' + ((a[0] + b[0]) / 2 + nx * 11).toFixed(1) + '" y="' + ((a[1] + b[1]) / 2 + ny * 11 + 3).toFixed(1) + '">' + esc(km(m)) + '</text>');
            }
          }
        }
      }
    });
    if (n.depot) {
      var d = P(n.depot);
      svg.push('<g class="slrm-depot" transform="translate(' + d[0].toFixed(1) + ',' + d[1].toFixed(1) + ')"><rect x="-9" y="-9" width="18" height="18" rx="4"/><path d="M-4 1 0 -4 4 1V5H-4Z" class="slrm-depot-ico"/><title>' + esc(n.depot.label || 'Start') + '</title></g>');
    }
    n.routes.forEach(function (r) {
      r.stops.forEach(function (s) {
        var q = P(s), c = stopColor(s, r);
        svg.push('<g class="slrm-stop slrm-st-' + esc(s.status || 'pending') + '" data-stop="' + esc(s.id) + '" tabindex="0" role="button" aria-label="Stop ' + esc(s.seq || '') + ' ' + esc(s.label || '') + '" transform="translate(' + q[0].toFixed(1) + ',' + q[1].toFixed(1) + ')">' +
          '<circle r="11" fill="' + esc(c) + '"/><text y="4">' + esc(s.seq || '•') + '</text><title>' + esc((s.seq ? '#' + s.seq + ' ' : '') + (s.label || '') + (s.eta ? ' · ETA ' + hm(s.eta, opts.tzOffsetMin) : '') + (s.status ? ' · ' + s.status : '')) + '</title></g>');
      });
    });
    n.riders.forEach(function (r) {
      var q = P(r), age = riderAge(r), label = (r.name || 'Rider') + (age ? ' · seen ' + age : '');
      svg.push('<g class="slrm-rider' + (age !== null ? ' is-stale' : '') + '" transform="translate(' + q[0].toFixed(1) + ',' + q[1].toFixed(1) + ')"><circle r="8" fill="' + esc(r.color || '#0f172a') + '"/>' + (age === null ? '<circle r="13" class="slrm-pulse" stroke="' + esc(r.color || '#0f172a') + '"/>' : '') +
        '<text class="slrm-rider-lbl" x="12" y="-10">' + esc(label) + '</text><title>' + esc(label) + '</title></g>');
    });
    svg.push('</svg>');
    host.innerHTML = svg.join('') +
      '<div class="slrm-note" role="note"><b>Schematic</b> — ' + esc(reason || 'map tiles offline') + (totalM ? ' · ' + esc(km(totalM)) + ' straight-line' : '') + '</div>';
    host.querySelectorAll('.slrm-stop').forEach(function (g) {
      var go = function () { if (opts.onStopClick) opts.onStopClick(g.getAttribute('data-stop')); };
      g.addEventListener('click', go);
      g.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
    });
  }

  /* ---------------- Leaflet ---------------- */
  function drawLeaflet(L, host, n, opts, state) {
    if (!state.map) {
      host.innerHTML = '';
      state.map = L.map(host, { zoomControl: opts.interactive !== false, scrollWheelZoom: false, dragging: opts.interactive !== false, attributionControl: true, tap: false });
      state.tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(state.map);
      state.layer = L.layerGroup().addTo(state.map);
    }
    var layer = state.layer; layer.clearLayers();
    state.markers = {};
    var bounds = [];
    n.routes.forEach(function (r) {
      var lp = linePoints(n, r);
      if (lp.length > 1) L.polyline(lp.map(function (p) { return [p.lat, p.lng]; }), { color: r.color || '#2563eb', weight: 4, opacity: 0.75, interactive: false }).addTo(layer);
      r.stops.forEach(function (s) {
        var c = stopColor(s, r);
        var icon = L.divIcon({ className: 'slrm-pin-wrap', html: '<span class="slrm-pin slrm-st-' + esc(s.status || 'pending') + '" style="background:' + esc(c) + '">' + esc(s.seq || '•') + '</span>', iconSize: [26, 26], iconAnchor: [13, 13] });
        var mk = L.marker([s.lat, s.lng], { icon: icon, keyboard: true, title: (s.seq ? '#' + s.seq + ' ' : '') + (s.label || '') })
          .bindTooltip(esc((s.seq ? '#' + s.seq + ' ' : '') + (s.label || '')) + (s.eta ? ' · ' + esc(hm(s.eta, opts.tzOffsetMin)) : ''), { direction: 'top', offset: [0, -10] })
          .addTo(layer);
        if (opts.onStopClick) mk.on('click', function () { opts.onStopClick(String(s.id)); });
        state.markers[String(s.id)] = mk;
        bounds.push([s.lat, s.lng]);
      });
    });
    if (n.depot) {
      L.marker([n.depot.lat, n.depot.lng], { icon: L.divIcon({ className: 'slrm-pin-wrap', html: '<span class="slrm-depot-pin" title="' + esc(n.depot.label || 'Start') + '"></span>', iconSize: [22, 22], iconAnchor: [11, 11] }), keyboard: false })
        .bindTooltip(esc(n.depot.label || 'Start'), { direction: 'top', offset: [0, -10] }).addTo(layer);
      bounds.push([n.depot.lat, n.depot.lng]);
    }
    n.riders.forEach(function (r) {
      var age = riderAge(r);
      L.marker([r.lat, r.lng], { icon: L.divIcon({ className: 'slrm-pin-wrap', html: '<span class="slrm-rider-pin' + (age !== null ? ' is-stale' : '') + '" style="background:' + esc(r.color || '#0f172a') + '"></span>', iconSize: [18, 18], iconAnchor: [9, 9] }), keyboard: false, zIndexOffset: 500 })
        .bindTooltip(esc((r.name || 'Rider') + (age ? ' · seen ' + age : '')), { direction: 'top', offset: [0, -8] }).addTo(layer);
      bounds.push([r.lat, r.lng]);
    });
    if (bounds.length === 1) state.map.setView(bounds[0], 14);
    else if (bounds.length) state.map.fitBounds(bounds, { padding: [28, 28], maxZoom: 15 });
    else state.map.setView([5.6037, -0.187], 12);
    setTimeout(function () { if (state.map) state.map.invalidateSize(); }, 50);
  }

  function mount(el, options) {
    if (!el) return null;
    var opts = Object.assign({ provider: 'auto', interactive: true }, options || {});
    var state = { map: null, tiles: null, layer: null, markers: {}, provider: null, data: opts, dead: false, timer: null, tileOk: false };
    el.classList.add('slrm');
    var host = document.createElement('div');
    host.className = 'slrm-host';
    el.innerHTML = '';
    el.appendChild(host);

    function setProvider(p, why) {
      state.provider = p;
      el.setAttribute('data-map-provider', p);
      if (why) el.setAttribute('data-map-reason', why);
    }
    function schematic(why) {
      if (state.map) { try { state.map.remove(); } catch (e) { /* already gone */ } state.map = null; }
      clearTimeout(state.timer);
      setProvider('schematic', why);
      drawSchematic(host, normalise(state.data), opts, why);
    }
    function leaflet() {
      if (navigator.onLine === false) return schematic('map tiles offline');
      loadLeaflet().then(function (L) {
        if (state.dead || state.provider === 'schematic') return;
        setProvider('leaflet');
        drawLeaflet(L, host, normalise(state.data), opts, state);
        if (!state.tileOk) {
          var tileErrors = 0;
          state.tiles.on('tileload', function () { state.tileOk = true; clearTimeout(state.timer); });
          // Tiles blocked or offline: stop asking after a few errors instead of waiting the full 4 s.
          state.tiles.on('tileerror', function () { if (!state.tileOk && ++tileErrors >= 3 && !state.dead) schematic('map tiles offline'); });
          state.timer = setTimeout(function () { if (!state.tileOk && !state.dead) schematic('map tiles offline'); }, TILE_TIMEOUT);
        }
      }).catch(function () { if (!state.dead) schematic('map library unavailable'); });
    }
    function render() {
      if (state.provider === 'schematic' || opts.provider === 'schematic') return schematic(opts.provider === 'schematic' ? 'simple view' : el.getAttribute('data-map-reason') || 'map tiles offline');
      if (state.provider === 'leaflet' && state.map && window.L) return drawLeaflet(window.L, host, normalise(state.data), opts, state);
      leaflet();
    }
    var onOffline = function () { if (state.provider !== 'schematic') schematic('map tiles offline'); };
    var onOnline = function () { if (state.provider === 'schematic' && opts.provider !== 'schematic') { state.provider = null; state.tileOk = false; leaflet(); } };
    window.addEventListener('offline', onOffline);
    window.addEventListener('online', onOnline);
    var ro = null;
    if (window.ResizeObserver) {
      var lastW = 0;
      ro = new ResizeObserver(function () {
        var w = host.clientWidth;
        if (Math.abs(w - lastW) < 8) return;
        lastW = w;
        if (state.provider === 'schematic') drawSchematic(host, normalise(state.data), opts, el.getAttribute('data-map-reason'));
        else if (state.map) state.map.invalidateSize();
      });
      ro.observe(host);
    }
    render();

    return {
      get provider() { return state.provider; },
      update: function (data) { state.data = Object.assign({}, state.data, data || {}); render(); },
      focus: function (stopId) {
        var id = String(stopId);
        if (state.map && state.markers[id]) {
          state.map.setView(state.markers[id].getLatLng(), Math.max(state.map.getZoom(), 15));
          state.markers[id].openTooltip();
          return;
        }
        host.querySelectorAll('.slrm-stop').forEach(function (g) { g.classList.toggle('is-focus', g.getAttribute('data-stop') === id); });
      },
      destroy: function () {
        state.dead = true;
        clearTimeout(state.timer);
        window.removeEventListener('offline', onOffline);
        window.removeEventListener('online', onOnline);
        if (ro) ro.disconnect();
        if (state.map) { try { state.map.remove(); } catch (e) { /* ignore */ } }
        el.innerHTML = '';
        el.removeAttribute('data-map-provider');
      }
    };
  }

  function isApple() {
    var ua = navigator.userAgent || '';
    return /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
  }
  function navUrl(stop, platform) {
    if (!stop) return '';
    var p = platform === 'auto' || !platform ? (isApple() ? 'apple' : 'google') : platform;
    var dest = hasPos(stop) ? (+stop.lat).toFixed(6) + ',' + (+stop.lng).toFixed(6) : (stop.address || '');
    if (!dest) return '';
    return p === 'apple'
      ? 'https://maps.apple.com/?daddr=' + encodeURIComponent(dest) + '&dirflg=d'
      : 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(dest) + '&travelmode=driving';
  }

  window.SLRouteMap = {
    mount: mount,
    navUrl: navUrl,
    haversine: function (a, b) { return Math.round(haversine(a, b)); },
    formatDistance: km,
    statusColors: STATUS
  };
})();
