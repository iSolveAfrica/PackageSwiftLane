/* AI centre: settings, test, models, knowledge and page ideas via ajax/ai_assistant/settings_ajax.php ({ok, error}). */
(function () {
  'use strict';
  var url = 'ajax/ai_assistant/settings_ajax.php';
  function post(data) { return SL.post(url, data).then(function (j) { j.success = j.ok === true; if (!j.success) j.message = j.error || j.message || 'Failed'; return j; }); }
  function done(j, okMsg) { SL.toast(j.success ? (j.message || okMsg) : j.message, !j.success); if (j.success && okMsg) setTimeout(function () { location.reload(); }, 400); }
  var f = document.getElementById('aic-form'), out = document.getElementById('aic-out');
  if (f) {
    f.addEventListener('submit', function (e) { e.preventDefault(); var fd = new FormData(f); fd.set('action', 'save'); post(fd).then(function (j) { done(j, 'Saved'); }); });
    document.querySelectorAll('[data-prov]').forEach(function (b) {
      b.addEventListener('click', function () {
        var p = b.getAttribute('data-prov'); f.provider.value = p;
        document.querySelectorAll('[data-prov]').forEach(function (x) { x.classList.toggle('on', x === b); });
        document.querySelectorAll('[data-key-for]').forEach(function (x) { x.style.display = x.getAttribute('data-key-for') !== p ? 'none' : ''; });
      });
    });
    document.getElementById('aic-test').addEventListener('click', function () {
      out.textContent = 'Testing…';
      var fd = new FormData(f); fd.set('action', 'test');
      post(fd).then(function (j) { out.textContent = j.success ? j.message : j.message; out.style.color = j.success ? 'var(--green-ink)' : 'var(--red-ink)'; });
    });
    document.getElementById('aic-models-btn').addEventListener('click', function () {
      var fd = new FormData(f); fd.set('action', 'models');
      post(fd).then(function (j) {
        if (!j.success) { out.textContent = j.message; out.style.color = 'var(--red-ink)'; return; }
        var dl = document.getElementById('aic-models'); dl.innerHTML = '';
        (j.models || []).forEach(function (m) { var o = document.createElement('option'); o.value = m.id || m; o.textContent = m.label || m.id || m; dl.appendChild(o); });
        out.textContent = (j.models || []).length + ' models loaded'; out.style.color = '';
      });
    });
  }
  document.addEventListener('submit', function (e) {
    var k = e.target.closest('[data-aic-form]'); if (!k) return;
    e.preventDefault(); var fd = new FormData(k); fd.set('action', k.getAttribute('data-aic-form')); post(fd).then(function (j) { done(j, 'Saved'); });
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-aic]'); if (!b || b.disabled) return;
    var data = { action: b.getAttribute('data-aic') };
    Array.prototype.forEach.call(b.attributes, function (a) { if (a.name.indexOf('data-p-') === 0) data[a.name.slice(7)] = a.value; });
    b.classList.add('loading'); post(data).then(function (j) { done(j, data.action === 'analyse' ? (j.added || 0) + ' ideas added' : 'Done'); }).finally(function () { b.classList.remove('loading'); });
  });
  document.addEventListener('change', function (e) {
    var s = e.target.closest('[data-aic-idea]'); if (!s) return;
    post({ action: 'idea_status', id: s.getAttribute('data-aic-idea'), status: s.value }).then(function (j) { SL.toast(j.success ? 'Saved' : j.message, !j.success); });
  });
})();
