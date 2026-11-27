/* What only the browser itself knows about this phone, tablet or computer, and the small manners that follow.
   The server's guess (helpers/sl_device.php) is already on <html> as data-os / data-browser / data-form.
   Nothing here changes layout: width and pointer media queries do that. */
(function () {
  'use strict';
  var root = document.documentElement, ds = root.dataset;
  var phoneQuery = matchMedia('(max-width:767px), (max-height:500px) and (max-width:950px) and (pointer:coarse)');
  var device = window.SLDevice = {
    os: ds.os || 'other', browser: ds.browser || 'other', form: ds.form || 'desktop',
    standalone: ds.standalone === '1', touch: navigator.maxTouchPoints > 0,
    phoneLayout: function () { return phoneQuery.matches; }
  };
  function toast(message, bad) { if (window.SL && SL.toast) SL.toast(message, bad); }
  function reducedMotion() { return matchMedia('(prefers-reduced-motion: reduce)').matches; }

  /* ---- greeting by the clock of the person's own device, not the server's ---- */
  function greet() {
    var hour = new Date().getHours();
    var text = hour < 5 ? 'Good evening' : hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
    document.querySelectorAll('[data-sl-greet]').forEach(function (el) { el.textContent = text; });
  }

  /* ---- phones: typing gets the whole screen, the tab bar steps aside ---- */
  function typing(el) { return el && el.matches && el.matches('input:not([type=checkbox]):not([type=radio]):not([type=button]):not([type=submit]):not([type=file]):not([type=range]), textarea, select, [contenteditable=""], [contenteditable="true"]'); }
  document.addEventListener('focusin', function (e) { if (device.touch && phoneQuery.matches && typing(e.target)) root.classList.add('sl-kb'); });
  document.addEventListener('focusout', function () { setTimeout(function () { if (!typing(document.activeElement)) root.classList.remove('sl-kb'); }, 80); });

  /* ---- connection: say so when the phone drops offline, and when it is back ---- */
  var strip = null;
  function connection() {
    if (navigator.onLine) {
      if (strip) { strip.remove(); strip = null; toast('Back online'); }
      return;
    }
    if (strip) return;
    strip = document.createElement('div'); strip.className = 'sl-net-strip'; strip.setAttribute('role', 'status');
    strip.textContent = 'You are offline. Changes are not saved until you reconnect.';
    document.body.appendChild(strip);
  }
  addEventListener('online', connection); addEventListener('offline', connection);

  /* ---- Android: a short tick on the tab bar and on main buttons (iPhones do not allow it) ---- */
  if (device.os === 'android' && navigator.vibrate && !reducedMotion()) {
    document.addEventListener('click', function (e) {
      if (e.target.closest('.sl-mobile-nav a, .sl-mobile-nav button, .mx-btn, .mx-sw, .ra-big, .sl-btn.primary')) { try { navigator.vibrate(8); } catch (err) {} }
    }, { passive: true });
  }

  /* ---- installed app on an iPhone or iPad: there is no browser bar, so screens below a tab get a Back button ---- */
  function backButton() {
    if (!device.standalone || device.os !== 'ios' || !phoneQuery.matches) return;
    var top = document.querySelector('.sl-top'); if (!top || top.querySelector('.pwa-back') || document.querySelector('[data-mx-back]')) return;
    var here = location.pathname.split('/').pop();
    var isTab = Array.prototype.some.call(document.querySelectorAll('.sl-mobile-nav a[href]'), function (a) { return (a.getAttribute('href') || '').split(/[?#]/)[0].split('/').pop() === here && !location.search; });
    if (isTab || history.length < 2) return;
    var b = document.createElement('button'); b.type = 'button'; b.className = 'sl-ib pwa-back'; b.setAttribute('aria-label', 'Back');
    b.innerHTML = '<i class="ic i-left" aria-hidden="true"></i>';
    b.addEventListener('click', function () { history.back(); });
    top.insertBefore(b, top.firstChild);
  }

  /* ---- computers: a phone number cannot be dialled, so offer to copy it ---- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href^="tel:"]');
    if (!a || device.touch || device.form !== 'desktop' || !navigator.clipboard) return;
    e.preventDefault();
    var number = decodeURIComponent(a.getAttribute('href').slice(4));
    navigator.clipboard.writeText(number).then(function () { toast('Number copied: ' + number); }).catch(function () { location.href = a.href; });
  });

  /* ---- maps links: <a data-maps-dest="address or lat,lng"> opens the maps app this device uses ----
     data-maps-app="google|apple|waze" pins the app (a rider's saved preference). */
  function mapsUrl(dest, app) {
    app = app || ((device.os === 'ios' || device.os === 'macos') ? 'apple' : 'google');
    var pair = /^-?\d+(\.\d+)?,\s*-?\d+(\.\d+)?$/.test(dest), q = encodeURIComponent(dest);
    if (app === 'waze') return 'https://waze.com/ul?' + (pair ? 'll=' + encodeURIComponent(dest.replace(/\s/g, '')) : 'q=' + q) + '&navigate=yes';
    if (app === 'apple') return 'https://maps.apple.com/?daddr=' + q + '&dirflg=d';
    return 'https://www.google.com/maps/dir/?api=1&destination=' + q + '&travelmode=driving';
  }
  device.mapsUrl = mapsUrl;
  function mapsLinks() {
    document.querySelectorAll('a[data-maps-dest]').forEach(function (a) {
      a.href = mapsUrl(a.getAttribute('data-maps-dest'), a.getAttribute('data-maps-app') || '');
      a.target = '_blank'; a.rel = 'noopener';
    });
  }

  /* ---- "this device" lines written by the server get the facts only the browser knows ---- */
  function deviceLines() {
    document.querySelectorAll('[data-device-installed]').forEach(function (el) { el.hidden = !device.standalone; });
    document.querySelectorAll('[data-device-browser-only]').forEach(function (el) { el.hidden = device.standalone; });
  }

  /* ---- the phone's status bar follows the app theme (the theme is the person's choice, not the system's) ---- */
  function statusBar() {
    var meta = document.querySelector('meta[name="theme-color"][data-dark]'); if (!meta) return;
    meta.content = root.classList.contains('sl-dark') ? meta.dataset.dark : meta.dataset.light;
  }
  new MutationObserver(statusBar).observe(root, { attributes: true, attributeFilter: ['class'] });

  function ready() { greet(); connection(); backButton(); mapsLinks(); deviceLines(); statusBar(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready); else ready();
})();
