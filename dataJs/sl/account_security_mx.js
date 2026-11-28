/* My account security (views/sl/pages/AccountSecurity.php): the "biometric on this device" switch.
 * Setting up goes through dataJs/sl/passkey.js (the hidden [data-pk-add] button); removing and reading
 * the state use the same endpoint (ajax/sl/passkey_ajax.php: list, delete). */
(function () {
  'use strict';
  var sw = document.querySelector('[data-as-pk]'), add = document.querySelector('[data-as-pk-add]');
  var SL = window.SL || {};
  if (!sw || !add || !SL.post) return;
  var EP = 'ajax/sl/passkey_ajax.php', label = sw.getAttribute('data-label') || '';

  function set(on, id) {
    sw.classList.toggle('on', on); sw.setAttribute('aria-checked', on ? 'true' : 'false');
    sw.setAttribute('data-id', on ? String(id || '') : '');
  }
  /* The server is the truth: after any change, read the keys back and show what is really stored. */
  function sync() {
    return SL.post(EP, { op: 'list' }).then(function (j) {
      if (!j.success) return;
      var mine = (j.keys || []).filter(function (k) { return k.label === label; })[0];
      set(!!mine, mine ? mine.id : '');
      var n = document.querySelector('[data-as-pk-count]');
      if (n) n.textContent = (j.keys || []).length ? 'Set up on ' + j.keys.length + (j.keys.length === 1 ? ' device' : ' devices') : 'Sign in without typing your password';
    }).catch(function () {});
  }
  /* mx.js has already flipped the switch when this runs. */
  sw.addEventListener('mx:switch', function (e) {
    if (e.detail.on) { add.click(); return; }
    var id = sw.getAttribute('data-id');
    if (!id || !confirm('Turn this off? You sign in with your password on this device next time.')) { set(true, id); return; }
    sw.setAttribute('aria-busy', 'true');
    SL.post(EP, { op: 'delete', id: id }).then(function (j) { if (SL.toast) SL.toast(j.message || (j.success ? 'Removed' : 'Not removed'), !j.success); })
      .catch(function (err) { if (SL.toast) SL.toast(err.message || 'Network error. Please try again.', true); })
      .finally(function () { sw.removeAttribute('aria-busy'); sync(); });
  });
  /* passkey.js marks its button .loading while the phone asks for the fingerprint; when that ends, re-read. */
  var busy = false;
  new MutationObserver(function () {
    var now = add.classList.contains('loading');
    if (busy && !now) sync();
    busy = now;
    if (now) sw.setAttribute('aria-busy', 'true'); else sw.removeAttribute('aria-busy');
  }).observe(add, { attributes: true, attributeFilter: ['class'] });
})();
