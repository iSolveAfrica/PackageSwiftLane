/* Phone screens, insights and settings: confirm before a switch saves, switches that submit their form,
   forms that go somewhere when saved, show/hide panels, and per-person permission switches. */
(function () {
  'use strict';
  /* .mx-sw[data-ph-confirm]: ask first; a "no" stops the switch (runs before mx.js, capture phase) */
  document.addEventListener('click', function (e) {
    var sw = e.target.closest('.mx-sw[data-ph-confirm]');
    if (sw && !confirm(sw.getAttribute('data-ph-confirm'))) { e.stopImmediatePropagation(); e.preventDefault(); }
  }, true);
  /* .mx-sw[data-ph-submit]: the switch writes its hidden input (data-input) and submits its form */
  document.addEventListener('mx:switch', function (e) {
    var sw = e.target;
    if (sw.hasAttribute('data-ph-submit')) {
      var f = sw.closest('form');
      if (f) { if (f.requestSubmit) f.requestSubmit(); else f.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true })); }
    }
    if (sw.hasAttribute('data-ph-perm')) perm(sw, e.detail.on);
  });
  /* form[data-ph-done="url"]: go there after a successful save */
  document.addEventListener('sl:done', function (e) {
    var f = e.target.closest && e.target.closest('[data-ph-done]');
    var j = e.detail || {};
    if (!f || !(j.success === true || j.status === 'success')) return;
    var to = f.getAttribute('data-ph-done');
    setTimeout(function () { if (to) location.href = to; else location.reload(); }, 400);
  });
  /* [data-ph-show="#id"]: show / hide a panel */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ph-show]');
    if (!b) return;
    var p = document.querySelector(b.getAttribute('data-ph-show'));
    if (!p) return;
    p.hidden = !p.hidden;
    b.setAttribute('aria-expanded', String(!p.hidden));
    if (!p.hidden) { var i = p.querySelector('input,select,textarea'); if (i) i.focus(); }
  });
  /* per-person permission: on = allowed. Matching the role saves "inherit", otherwise allow / deny. */
  function perm(sw, on) {
    var roleOn = sw.getAttribute('data-role-on') === '1';
    var state = on === roleOn ? 'inherit' : (on ? 'allow' : 'deny');
    var fd = new FormData();
    fd.append('user_id', sw.getAttribute('data-user'));
    fd.append('changes[' + sw.getAttribute('data-ph-perm') + ']', state);
    function back(m) { sw.classList.toggle('on', !on); sw.setAttribute('aria-checked', String(!on)); if (window.SL) SL.toast(m || 'Not saved', true); }
    sw.setAttribute('aria-busy', 'true');
    SL.post('ajax/users/users_overrides_save_ajax.php', fd).then(function (j) {
      if (j.status === 'success' || j.success === true) {
        SL.toast(state === 'inherit' ? 'Saved · follows the role' : 'Saved · ' + (on ? 'allowed' : 'not allowed') + ' for this person');
        var s = sw.parentNode.querySelector('[data-ph-state]');
        if (s) s.textContent = state === 'inherit' ? 'From role' : 'Set for this person';
      } else back(j.message);
    }).catch(function () { back('Network error'); }).finally(function () { sw.removeAttribute('aria-busy'); });
  }
})();
