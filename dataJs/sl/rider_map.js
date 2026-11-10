(function () {
  'use strict';
  var map = document.getElementById('da-route-map');
  if (!map) return;
  document.querySelectorAll('[data-map-address]').forEach(function (button) {
    button.setAttribute('aria-pressed', 'false');
    button.addEventListener('click', function () {
      document.querySelectorAll('[data-map-address]').forEach(function (b) { b.setAttribute('aria-pressed', String(b === button)); });
      map.src = 'https://maps.google.com/maps?' + new URLSearchParams({ q: button.dataset.mapAddress, z: '16', output: 'embed' });
      map.title = button.getAttribute('aria-label');
      map.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    });
  });
})();
