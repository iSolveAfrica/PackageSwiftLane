(function () {
  'use strict';
  var map = document.getElementById('da-route-map');
  if (!map) return;
  function reveal() {
    if (!navigator.onLine) { if (window.SL) SL.toast('Reconnect to load the route map.', true); return false; }
    map.hidden = false;
    var placeholder = document.querySelector('[data-rider-map-placeholder]'); if (placeholder) placeholder.hidden = true;
    return true;
  }
  var load = document.querySelector('[data-load-rider-map]');
  if (load) load.addEventListener('click', function () { if (reveal()) map.src = map.dataset.src; });
  document.querySelectorAll('[data-map-address]').forEach(function (button) {
    button.setAttribute('aria-pressed', 'false');
    button.addEventListener('click', function () {
      if (!reveal()) return;
      document.querySelectorAll('[data-map-address]').forEach(function (b) { b.setAttribute('aria-pressed', String(b === button)); });
      map.src = 'https://maps.google.com/maps?' + new URLSearchParams({ q: button.dataset.mapAddress, z: '16', output: 'embed' });
      map.title = button.getAttribute('aria-label');
      map.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    });
  });
})();
