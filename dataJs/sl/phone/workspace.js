'use strict';
/* M-Workspace (views/sl/phone/Workspace.php): clock in/out, task status and announcements post to the same
   endpoint and actions as the web workspace (dataJs/workspace.js); that endpoint answers {ok: true}. */
(function () {
  var URL = 'ajax/staffops/staffops_ajax.php';
  var msgs = { clock: 'Done', task_status: 'Updated', read_announcement: 'Marked as read' };
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ws-op]');
    if (!b || b.getAttribute('aria-busy') === 'true') return;
    e.preventDefault();
    var action = b.getAttribute('data-ws-op'), data = { action: action };
    Array.prototype.forEach.call(b.attributes, function (a) { if (a.name.indexOf('data-p-') === 0) data[a.name.slice(7)] = a.value; });
    b.setAttribute('aria-busy', 'true');
    SL.post(URL + '?action=' + encodeURIComponent(action), data).then(function (r) {
      var ok = r && (r.ok === true || r.success === true);
      var text = ok ? (action === 'clock' && data.op === 'in' ? (r.late_minutes ? 'Checked in, ' + r.late_minutes + ' minutes late' : 'Checked in') : action === 'clock' ? 'Checked out' : msgs[action]) : (r && (r.error || r.message)) || 'Could not save. Try again.';
      SL.toast(text, !ok);
      if (ok) setTimeout(function () { location.reload(); }, 400);
    }).catch(function (err) { SL.toast(err.message || 'Network error', true); }).finally(function () { b.removeAttribute('aria-busy'); });
  });
  var f = document.getElementById('ws-arrive');
  if (f) f.addEventListener('submit', function (e) {
    e.preventDefault();
    SL.post('ajax/comms/comms_ajax.php?action=driver_arriving', { action: 'driver_arriving', tracking: f.elements.tracking.value, eta: f.elements.eta.value }).then(function (r) {
      var ok = r && (r.ok === true || r.success === true);
      SL.toast(ok ? 'The customer has been told you are on the way' : (r.error || r.message || 'Could not send'), !ok);
      if (ok) f.elements.tracking.value = '';
    }).catch(function (err) { SL.toast(err.message || 'Network error', true); });
  });
})();
