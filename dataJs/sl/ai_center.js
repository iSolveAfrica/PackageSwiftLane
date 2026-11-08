/* AI centre: settings, test, models, knowledge and page ideas via ajax/ai_assistant/settings_ajax.php ({ok, error}). */
(function () {
  'use strict';
  var url = 'ajax/ai_assistant/settings_ajax.php';
  function post(data) { return SL.post(url, data).then(function (j) { j.success = j.ok === true; if (!j.success) j.message = j.error || j.message || 'Failed'; return j; }); }
  function done(j, okMsg) { SL.toast(j.success ? (j.message || okMsg) : j.message, !j.success); if (j.success && okMsg) setTimeout(function () { location.reload(); }, 400); }
  var f = document.getElementById('aic-form'), out = document.getElementById('aic-out');
  if (f) {
    var model = f.elements.namedItem('model'), hint = document.getElementById('aic-model-hint'), remembered = {};
    function fillModels(models, current) {
      var options = [], seen = {};
      (models || []).forEach(function (m) { var id = typeof m === 'string' ? m : m.id; if (!id || seen[id]) return; seen[id] = true; options.push(new Option(m.name || m.label || id, id)); });
      if (current && !seen[current]) options.unshift(new Option(current + ' (saved)', current));
      model.replaceChildren.apply(model, options);
      if (current) model.value = current;
    }
    f.addEventListener('submit', function (e) { e.preventDefault(); var fd = new FormData(f); fd.set('action', 'save'); post(fd).then(function (j) { done(j, 'Saved'); }); });
    document.querySelectorAll('[data-prov]').forEach(function (b) {
      b.addEventListener('click', function () {
        var p = b.getAttribute('data-prov'); remembered[f.provider.value] = model.value; f.provider.value = p;
        var presets = JSON.parse(b.getAttribute('data-models') || '{}');
        fillModels(Object.keys(presets).map(function (id) { return { id: id, name: presets[id] }; }), remembered[p]);
        hint.textContent = 'Choose a model, or load the latest list from your provider.'; out.textContent = '';
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
      var button = this, provider = f.provider.value, current = model.value;
      button.disabled = true; button.textContent = 'Loading…'; hint.textContent = 'Loading available models…';
      var fd = new FormData(f); fd.set('action', 'models');
      post(fd).then(function (j) {
        if (f.provider.value !== provider) return;
        if (!j.success) { out.textContent = j.message; out.style.color = 'var(--red-ink)'; return; }
        if (!Array.isArray(j.models) || !j.models.length) { hint.textContent = 'No models returned. Your current selection is unchanged.'; return; }
        fillModels(j.models, current);
        hint.textContent = j.models.length + ' models available. Open the Model dropdown to choose.';
        model.focus(); out.textContent = 'Models loaded'; out.style.color = 'var(--green-ink)';
      }).catch(function () { if (f.provider.value === provider) { out.textContent = 'Could not load models. Try again.'; out.style.color = 'var(--red-ink)'; }
      }).finally(function () { button.disabled = false; button.textContent = 'Load models'; if (hint.textContent === 'Loading available models…') hint.textContent = 'Your current model is unchanged. Try loading again.'; });
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
