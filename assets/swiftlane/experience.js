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
    var i = 0;
    document.querySelectorAll('.sl-wrap > section,.sl-wrap > .grid,.sl-wrap > .split,.sl-wrap > .sl-card,.sl-wrap > .cs-layout,.da-next,.da-sheet,[data-sl-reveal]').forEach(function (el) {
      /* reading order inside the first screen sets the stagger; later ones arrive as they scroll in */
      el.style.setProperty('--i', String(Math.min(i++, 8)));
      observer.observe(el);
    });
    reduced.addEventListener('change', function () { if (reduced.matches) observer.disconnect(); });
  }
  /* Figures count up once, when they come into view: the first number in the element. */
  if (!reduced.matches && 'IntersectionObserver' in window) {
    var seen = new WeakSet();
    var counter = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting || seen.has(entry.target)) return;
        seen.add(entry.target); counter.unobserve(entry.target);
        countUp(entry.target);
      });
    }, { threshold: 0.4 });
    document.querySelectorAll('[data-sl-count],.sl-metric .sl-mv,.dk-q b,.up7-st b,.desk .big,.dv-kpi .v,.sl-num').forEach(function (el) {
      if (el.querySelector('input,select,textarea') || el.closest('[data-sl-static]')) return;
      counter.observe(el);
    });
  }
  function countUp(el) {
    var node = firstNumberText(el); if (!node) return;
    var m = /^(\s*[^\d-]*?)(-?\d[\d,]*)(\.\d+)?/.exec(node.nodeValue); if (!m) return;
    var whole = m[2].replace(/,/g, ''), target = parseFloat(whole + (m[3] || ''));
    if (!isFinite(target) || Math.abs(target) > 1e9) return;
    var decimals = m[3] ? m[3].length - 1 : 0, grouped = m[2].indexOf(',') >= 0;
    var prefix = m[1], suffix = node.nodeValue.slice(m[0].length), start = null, duration = 650;
    function fmt(v) { var s = v.toFixed(decimals); if (grouped) { var p = s.split('.'); p[0] = p[0].replace(/\B(?=(\d{3})+(?!\d))/g, ','); s = p.join('.'); } return s; }
    function frame(ts) {
      if (start === null) start = ts;
      var p = Math.min(1, (ts - start) / duration), e = 1 - Math.pow(1 - p, 3);
      node.nodeValue = prefix + fmt(target * e) + suffix;
      if (p < 1) requestAnimationFrame(frame); else node.nodeValue = prefix + fmt(target) + suffix;
    }
    requestAnimationFrame(frame);
  }
  function firstNumberText(el) {
    var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT), n;
    while ((n = walker.nextNode())) { if (/\d/.test(n.nodeValue)) return n; }
    return null;
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
