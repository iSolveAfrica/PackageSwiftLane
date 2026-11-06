/* CustomerNotifications: per-event channel checkboxes and quiet hours save straight away. */
(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.sl-chk[data-sl-chk-save]');
    if (!b) return;
    e.preventDefault();
    if (b.hasAttribute('disabled')) return;
    var on = !b.classList.contains('on');
    b.classList.toggle('on', on); b.setAttribute('aria-checked', on ? 'true' : 'false');
    SL.post(b.getAttribute('data-sl-chk-save'), { op: 'event', channel: b.getAttribute('data-channel'), event: b.getAttribute('data-event'), value: on ? 1 : 0 })
      .then(function (j) { if (!j.success) { b.classList.toggle('on', !on); b.setAttribute('aria-checked', on ? 'false' : 'true'); } SL.toast(j.message || (j.success ? 'Saved' : 'Not saved'), !j.success); });
  });
  var qf = document.getElementById('cn-quiet-from'), qt = document.getElementById('cn-quiet-to');
  function saveQuiet() {
    SL.post('ajax/sl/notification_prefs_ajax.php', { op: 'quiet', from: qf.value, to: qt.value }).then(function (j) { SL.toast(j.message || 'Saved', !j.success); });
  }
  if (qf && qt) { qf.addEventListener('change', saveQuiet); qt.addEventListener('change', saveQuiet); }
})();
