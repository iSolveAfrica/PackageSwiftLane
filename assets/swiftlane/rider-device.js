/* Rider app layout: phone layout on phones and tablets, web layout on desktops, with a manual
 * "Auto / Phone view / Web view" switch. The choice lives in localStorage 'sl.rider.layout' and in
 * the cookie sl_rider_layout (SameSite=Lax, 180 days) so PHP renders the chosen layout first.
 * Sets <html data-rider-layout="phone|web"> and .da.is-phone / .is-web. CSS media queries stay the
 * baseline; there is no user-agent sniffing on the server (helpers/sl_mobile.php policy).
 */
(function () {
  'use strict';
  var KEY = 'sl.rider.layout';
  var MODES = ['auto', 'phone', 'web'];

  function isPhoneDevice() {
    var coarse = !!(window.matchMedia && window.matchMedia('(pointer:coarse)').matches);
    var small = Math.min(screen.width || 9999, screen.height || 9999) <= 1024;
    var uaMobile = !!(navigator.userAgentData && navigator.userAgentData.mobile);
    return (coarse && small) || uaMobile;
  }
  function stored() {
    var root = document.querySelector('.da[data-layout]');
    var server = root ? root.getAttribute('data-layout') : 'auto';
    var local = null;
    try { local = localStorage.getItem(KEY); } catch (e) { /* private mode */ }
    if (server && server !== 'auto') return server;
    return MODES.indexOf(local) >= 0 ? local : 'auto';
  }
  function resolve(mode) { return mode === 'phone' || mode === 'web' ? mode : (isPhoneDevice() ? 'phone' : 'web'); }

  function apply(mode) {
    var r = resolve(mode);
    document.documentElement.setAttribute('data-rider-layout', r);
    document.querySelectorAll('.da').forEach(function (el) {
      el.classList.remove('is-phone', 'is-web');
      el.classList.add('is-' + r);
      el.setAttribute('data-layout', mode);
    });
    document.querySelectorAll('[data-rider-layout-set]').forEach(function (b) {
      var on = b.getAttribute('data-rider-layout-set') === mode;
      b.classList.toggle('on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    window.dispatchEvent(new CustomEvent('sl:rider-layout', { detail: { mode: mode, layout: r } }));
    return r;
  }
  function save(mode) {
    try { localStorage.setItem(KEY, mode); } catch (e) { /* ignore */ }
    var secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = 'sl_rider_layout=' + mode + '; Max-Age=' + (180 * 86400) + '; Path=/; SameSite=Lax' + secure;
  }

  var mode = stored();
  apply(mode);
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-rider-layout-set]');
    if (!b) return;
    var m = b.getAttribute('data-rider-layout-set');
    if (MODES.indexOf(m) < 0) return;
    mode = m;
    save(m);
    var r = apply(m);
    if (window.SL && SL.toast) SL.toast(m === 'auto' ? 'Layout: automatic (' + (r === 'phone' ? 'phone' : 'web') + ' view)' : (m === 'phone' ? 'Phone view' : 'Web view'));
  });
  // Rotation or a docked tablet can change the pointer type; only "auto" follows it.
  if (window.matchMedia) {
    var mq = window.matchMedia('(pointer:coarse)');
    var onChange = function () { if (mode === 'auto') apply(mode); };
    if (mq.addEventListener) mq.addEventListener('change', onChange); else if (mq.addListener) mq.addListener(onChange);
  }
  window.SLRiderLayout = { get: function () { return { mode: mode, layout: resolve(mode) }; }, set: function (m) { if (MODES.indexOf(m) >= 0) { mode = m; save(m); apply(m); } }, isPhoneDevice: isPhoneDevice };
})();
