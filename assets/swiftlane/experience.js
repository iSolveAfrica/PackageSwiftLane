/* Motion is progressive enhancement: no content is hidden before JavaScript runs. */
(function () {
  'use strict';
  var reduced = matchMedia('(prefers-reduced-motion: reduce)');
  if (!reduced.matches && 'IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        if (!reduced.matches) entry.target.classList.add('sl-revealed');
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.08 });
    document.querySelectorAll('.sl-wrap > section,.sl-wrap > .grid,.sl-wrap > .split,.da-next,.da-sheet,[data-sl-reveal]').forEach(function (el) { observer.observe(el); });
    reduced.addEventListener('change', function () { if (reduced.matches) observer.disconnect(); });
  }
  var banner;
  function connection() {
    if (!banner) {
      banner = document.createElement('div'); banner.className = 'sl-network-status';
      banner.setAttribute('role', 'status'); banner.textContent = 'You’re offline. Reconnect before sending updates or payments.';
      document.body.appendChild(banner);
    }
    banner.hidden = navigator.onLine;
  }
  window.addEventListener('offline', connection); window.addEventListener('online', connection);
  if (!navigator.onLine) connection();
  // Keep focus / a virtual keyboard from being covered by phone navigation.
  if (window.visualViewport) {
    var initialHeight = window.visualViewport.height;
    function viewport() {
      var editing = document.activeElement && document.activeElement.matches('input,textarea,select');
      document.documentElement.classList.toggle('sl-keyboard-open', !!editing && initialHeight - visualViewport.height > 150);
    }
    visualViewport.addEventListener('resize', viewport);
    document.addEventListener('focusout', function () { document.documentElement.classList.remove('sl-keyboard-open'); });
    window.addEventListener('orientationchange', function () { initialHeight = window.innerHeight; });
  }
})();
