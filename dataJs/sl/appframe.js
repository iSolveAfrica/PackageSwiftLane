/* Customer app on a computer (helpers/sl_appframe.php, views/sl/inc/app_frame.php).
 * Outside the window: remember whether the screen is wide (cookie sl_vw) and, on a wide screen, ask once for
 * the app window when this page may be shown in it.
 * Inside the window: tell the window where we are, and open payments, prints, downloads and other sites as
 * full pages (outside the window). */
(function () {
  'use strict';
  var html = document.documentElement, body = document.body;
  var framed = false;
  try { framed = window.top !== window.self && window.top.location.origin === location.origin; } catch (e) { framed = false; }
  var wide = Math.max(window.innerWidth, screen.width || 0) >= 900 && !matchMedia('(pointer: coarse)').matches;
  if (!framed) {
    document.cookie = 'sl_vw=' + (wide ? 'wide' : 'narrow') + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    var meta = document.querySelector('meta[name="sl-appframe"]');
    if (wide && meta && meta.content === '1') {
      var key = 'sl.appframe.asked';
      var asked = null; try { asked = sessionStorage.getItem(key); } catch (e) {}
      if (asked !== location.pathname) { // one try per page: a browser that cannot do it keeps the website
        try { sessionStorage.setItem(key, location.pathname); } catch (e) {}
        location.reload();
      }
    }
    return;
  }
  html.classList.add('sl-in-appframe');
  function tell() { try { window.top.postMessage({ type: 'sl-app-nav', url: location.href, title: document.title }, location.origin); } catch (e) {} }
  tell();
  addEventListener('hashchange', tell);
  var full = /\/(pay_checkout|payment_return|track_invoice|download|logout|view_as|print_[a-z_]+|send_email[a-z_]*)\.php/i;
  function outside(url) {
    try { var u = new URL(url, location.href); return u.origin !== location.origin || full.test(u.pathname); } catch (e) { return false; }
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || e.defaultPrevented || a.target === '_blank' || /^(#|javascript:|mailto:|tel:)/i.test(a.getAttribute('href'))) return;
    if (outside(a.href)) a.target = '_top';
  }, true);
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f && f.action && outside(f.action) && !f.hasAttribute('data-sl-form')) f.target = '_top';
  }, true);
})();
