/* Rider app (earnings, wallet and account screens): navigation-app picker, daily checklist, payout method
   fields and "send my location now". Writes go to ajax/sl/rider_app_ajax.php through SL.post (adds CSRF). */
(function () {
  'use strict';
  var SL = window.SL || {};
  function toast(m, bad) { if (SL.toast) SL.toast(m, bad); }

  /* single-choice preference: <div class="mx-fseg" data-rm-pref="nav_app" data-rm-url> <button data-value> */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-rm-pref] [data-value]');
    if (!b || b.classList.contains('on') || !SL.post) return;
    var g = b.closest('[data-rm-pref]'), prev = g.querySelector('.on');
    function set(x) { g.querySelectorAll('[data-value]').forEach(function (y) { y.classList.toggle('on', y === x); y.setAttribute('aria-pressed', String(y === x)); }); }
    set(b);
    SL.post(g.getAttribute('data-rm-url'), { key: g.getAttribute('data-rm-pref'), value: b.getAttribute('data-value') }).then(function (j) {
      if (j.success !== true) { set(prev); toast(j.message || 'Not saved', true); } else if (j.message) toast(j.message);
    }).catch(function (err) { set(prev); toast(err.message || 'Network error. Please try again.', true); });
  });

  /* "before you ride" checklist */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-rm-chk]');
    if (!b || b.disabled || b.getAttribute('aria-busy') === 'true' || !SL.post) return;
    var li = b.closest('li'), on = b.getAttribute('aria-checked') !== 'true';
    function set(v) { b.setAttribute('aria-checked', String(v)); li.classList.toggle('on', v); }
    set(on); b.setAttribute('aria-busy', 'true');
    SL.post(b.getAttribute('data-rm-url'), { op: 'checklist', item: b.getAttribute('data-rm-chk'), value: on ? 1 : 0 }).then(function (j) {
      if (j.success !== true) { set(!on); toast(j.message || 'Not saved', true); return; }
      var card = b.closest('.rd-chk'), c = card.querySelector('[data-rm-chk-count]'), p = card.querySelector('[data-rm-chk-pill]'), all = j.done === j.total;
      if (c) c.textContent = j.done + ' of ' + j.total + ' done today';
      if (p) { p.textContent = all ? 'Ready' : 'To do'; p.classList.toggle('delivered', all); p.classList.toggle('plain', !all); }
    }).catch(function (err) { set(!on); toast(err.message || 'Network error. Please try again.', true); }).finally(function () { b.removeAttribute('aria-busy'); });
  });

  /* payout account: cash at the office needs no account fields */
  document.addEventListener('change', function (e) {
    if (!e.target.matches('[data-rm-method]')) return;
    var f = e.target.form && e.target.form.querySelector('[data-rm-method-fields]');
    if (f) f.hidden = e.target.value === 'cash';
  });

  /* send the phone's current position: share sheet, a text to the emergency contact, or copy */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-rm-locate]');
    if (!b || b.getAttribute('aria-busy') === 'true') return;
    var status = (b.closest('.rd-share') || document).querySelector('[data-rm-locate-status]');
    function say(m) { if (status) status.textContent = m; }
    if (!navigator.geolocation) { say('This phone or browser cannot give a location. Describe where you are in a call instead.'); toast('Location is not available here', true); return; }
    b.setAttribute('aria-busy', 'true'); say('Finding where you are…');
    navigator.geolocation.getCurrentPosition(function (pos) {
      b.removeAttribute('aria-busy');
      var url = 'https://maps.google.com/?q=' + pos.coords.latitude.toFixed(6) + ',' + pos.coords.longitude.toFixed(6);
      var text = b.getAttribute('data-rm-text') || 'I am here:', sms = b.getAttribute('data-rm-sms');
      say('Location found, accurate to about ' + Math.round(pos.coords.accuracy) + ' m.');
      if (sms) { location.href = sms + '?&body=' + encodeURIComponent(text + ' ' + url); return; }
      if (navigator.share) { navigator.share({ text: text, url: url }).catch(function () {}); return; }
      (navigator.clipboard ? navigator.clipboard.writeText(text + ' ' + url) : Promise.reject()).then(function () { toast('Map link copied. Paste it in a message.'); }).catch(function () { say(text + ' ' + url); });
    }, function (err) {
      b.removeAttribute('aria-busy');
      say(err && err.code === 1 ? 'Location is blocked for this app. Allow it in your phone settings, or describe where you are in a call.' : 'Could not find your location. Move to open sky and try again, or call instead.');
      toast('Location not found', true);
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
  });
})();
