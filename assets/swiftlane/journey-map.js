/* Journey map: draws the direct route (great circle) on a real map and places the aircraft
 * along it at the shipment's stage. Needs Leaflet (assets/vendor/libs/leaflet). */
(function () {
  var rad = function (d) { return d * Math.PI / 180; };
  var deg = function (r) { return r * 180 / Math.PI; };

  // Points along the great circle from a to b (both {lat, lon}).
  function greatCircle(a, b, n) {
    var p1 = rad(a.lat), l1 = rad(a.lon), p2 = rad(b.lat), l2 = rad(b.lon);
    var d = 2 * Math.asin(Math.sqrt(Math.pow(Math.sin((p2 - p1) / 2), 2) + Math.cos(p1) * Math.cos(p2) * Math.pow(Math.sin((l2 - l1) / 2), 2)));
    var out = [];
    for (var i = 0; i <= n; i++) {
      var f = i / n;
      if (d === 0) { out.push([a.lat, a.lon]); continue; }
      var A = Math.sin((1 - f) * d) / Math.sin(d), B = Math.sin(f * d) / Math.sin(d);
      var x = A * Math.cos(p1) * Math.cos(l1) + B * Math.cos(p2) * Math.cos(l2);
      var y = A * Math.cos(p1) * Math.sin(l1) + B * Math.cos(p2) * Math.sin(l2);
      var z = A * Math.sin(p1) + B * Math.sin(p2);
      out.push([deg(Math.atan2(z, Math.sqrt(x * x + y * y))), deg(Math.atan2(y, x))]);
    }
    return out;
  }

  // Compass bearing (degrees from north) from p to q.
  function bearing(p, q) {
    var p1 = rad(p[0]), p2 = rad(q[0]), dl = rad(q[1] - p[1]);
    var y = Math.sin(dl) * Math.cos(p2), x = Math.cos(p1) * Math.sin(p2) - Math.sin(p1) * Math.cos(p2) * Math.cos(dl);
    return (deg(Math.atan2(y, x)) + 360) % 360;
  }

  var PLANE = '<svg viewBox="0 0 64 64" aria-hidden="true"><defs><linearGradient id="jmg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FF8A4C"/><stop offset="1" stop-color="#C94A17"/></linearGradient></defs>' +
    '<path d="M32 3 C34.2 3 35.2 5.2 35.2 8.6 L35.2 23.4 L56.5 35.6 L56.5 41.2 L35.2 35.4 L35.2 49.6 L42.4 55.4 L42.4 58.6 L32 55.8 L21.6 58.6 L21.6 55.4 L28.8 49.6 L28.8 35.4 L7.5 41.2 L7.5 35.6 L28.8 23.4 L28.8 8.6 C28.8 5.2 29.8 3 32 3 Z" fill="url(#jmg)" stroke="#fff" stroke-width="2" stroke-linejoin="round"/>' +
    '<ellipse cx="32" cy="15" rx="2.4" ry="5" fill="#fff" opacity=".55"/></svg>';

  function draw(el) {
    if (el.getAttribute('data-jm-ready')) return;
    var from = JSON.parse(el.getAttribute('data-from')), to = JSON.parse(el.getAttribute('data-to'));
    var frac = Math.max(0, Math.min(1, parseFloat(el.getAttribute('data-frac')) || 0));
    el.setAttribute('data-jm-ready', '1');
    var map = L.map(el, { zoomControl: true, scrollWheelZoom: false, attributionControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 6, attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var pts = greatCircle(from, to, 120);
    // Point index of the aircraft: 0 at JFK (still at the warehouse), the last point at Accra.
    var k = Math.round(frac * (pts.length - 1));
    L.polyline(pts, { color: '#1E1D1A', weight: 2, dashArray: '4 8', opacity: 0.45, interactive: false }).addTo(map);
    if (k > 0) L.polyline(pts.slice(0, k + 1), { color: '#E2632A', weight: 4, lineCap: 'round', className: 'jm-flow', interactive: false }).addTo(map);

    var fromDone = frac > 0;
    L.marker([from.lat, from.lon], { icon: L.divIcon({ className: '', html: '<div class="jm-dot ' + (fromDone ? 'done' : 'todo') + '"></div>', iconSize: [14, 14], iconAnchor: [7, 7] }), keyboard: false })
      .bindTooltip(from.label, { permanent: true, direction: 'top', offset: [0, -8], className: 'jm-tip' }).addTo(map);
    L.marker([to.lat, to.lon], { icon: L.divIcon({ className: '', html: '<div class="jm-dot ' + (frac >= 1 ? 'done' : 'todo') + '"></div>', iconSize: [14, 14], iconAnchor: [7, 7] }), keyboard: false })
      .bindTooltip(to.label, { permanent: true, direction: 'top', offset: [0, -8], className: 'jm-tip' }).addTo(map);

    var here = pts[k];
    var ahead = pts[Math.min(pts.length - 1, k + 1)], behind = pts[Math.max(0, k - 1)];
    var brg = k < pts.length - 1 ? bearing(here, ahead) : bearing(behind, here);
    var planeHtml = '<div class="jm-plane bob"><span class="jm-svg" style="position:absolute;inset:0;transform:rotate(' + brg.toFixed(1) + 'deg)">' + PLANE + '</span></div>';
    L.marker(here, { icon: L.divIcon({ className: '', html: planeHtml, iconSize: [64, 64], iconAnchor: [32, 32] }), keyboard: false, interactive: false }).addTo(map);

    map.fitBounds(L.latLngBounds(pts), { padding: [36, 36], maxZoom: 4 });
    el.setAttribute('data-jm-bearing', brg.toFixed(1));
  }

  function init() {
    if (!window.L) return;
    document.querySelectorAll('[data-journey-map]').forEach(draw);
  }
  // Leaflet and this script both load with defer: wait for the window load.
  if (document.readyState === 'complete') init(); else window.addEventListener('load', init);
})();
