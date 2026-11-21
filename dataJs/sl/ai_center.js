/* AI centre: settings, test, models, knowledge and page ideas via ajax/ai_assistant/settings_ajax.php ({ok, error}). */
(function () {
  'use strict';
  var url = 'ajax/ai_assistant/settings_ajax.php';
  function post(data) { return SL.post(url, data).then(function (j) { j.success = j.ok === true; if (!j.success) j.message = j.error || j.message || 'Failed'; return j; }); }
  function done(j, okMsg) { SL.toast(j.success ? (j.message || okMsg) : j.message, !j.success); if (j.success && okMsg) setTimeout(function () { location.reload(); }, 400); }
  var f = document.getElementById('aic-form'), out = document.getElementById('aic-out');
  if (f) {
    var model = f.elements.namedItem('model'), hint = document.getElementById('aic-model-hint'), remembered = {};
    var picker = document.querySelector('.aic-mp'), qIn = document.getElementById('aic-model-q'), tierBox = document.getElementById('aic-tier'), info = document.getElementById('aic-model-info');
    var presets = {}; try { presets = JSON.parse(picker.getAttribute('data-presets') || '{}'); } catch (e) {}
    var loaded = {}, state = { list: [], q: '', tier: '' };
    var BADGE = { recommended: '★ Recommended', best: 'Best', value: 'Best value', freebest: 'Best free' };
    function badgeText(m) { return (m.badges || []).map(function (b) { return BADGE[b] || b; }).join(' · '); }
    function label(m) {
      var b = badgeText(m), parts = [];
      if (b) parts.push(b + ' ·');
      parts.push(m.name && m.name !== m.id ? m.name : m.id);
      if (m.tier === 'free') parts.push('— Free');
      if (m.tools === false) parts.push('(no lookups)');
      return parts.join(' ');
    }
    function matches(m) {
      if (state.tier && m.tier !== state.tier) return false;
      if (!state.q) return true;
      var hay = (m.id + ' ' + (m.name || '') + ' ' + badgeText(m) + ' ' + (m.tier || '')).toLowerCase();
      return state.q.split(/\s+/).every(function (w) { return hay.indexOf(w) !== -1; });
    }
    function render() {
      var current = model.value, frag = document.createDocumentFragment(), seen = {}, shown = 0;
      var groups = [['Recommended for ' + (window.SL_KING || 'King'), function (m) { return (m.badges || []).length > 0; }]];
      if (state.list.some(function (m) { return m.tier; })) { groups.push(['Free models', function (m) { return m.tier === 'free'; }]); groups.push(['Paid models', function (m) { return m.tier === 'paid'; }]); }
      else groups.push(['All models', function () { return true; }]);
      groups.forEach(function (g) {
        var og = document.createElement('optgroup'); og.label = g[0];
        state.list.forEach(function (m) {
          if (seen[m.id] || !g[1](m) || !matches(m)) return;
          seen[m.id] = true; shown++;
          var o = new Option(label(m), m.id); o.title = m.id + (m.price ? ' · ' + m.price : ''); og.appendChild(o);
        });
        if (og.children.length) frag.appendChild(og);
      });
      if (current && !seen[current]) { var keep = state.list.filter(function (m) { return m.id === current; })[0]; frag.insertBefore(new Option(keep ? label(keep) : current + ' (saved)', current), frag.firstChild); }
      model.replaceChildren(frag);
      if (current) model.value = current;
      var total = state.list.length, free = state.list.filter(function (m) { return m.tier === 'free'; }).length;
      if (qIn) qIn.placeholder = 'Search ' + total + ' models';
      if (tierBox) tierBox.querySelectorAll('button').forEach(function (b) { var t = b.getAttribute('data-tier'); b.textContent = (t === 'free' ? 'Free' : t === 'paid' ? 'Paid' : 'All') + ' ' + (t === 'free' ? free : t === 'paid' ? total - free : total); });
      if (state.q || state.tier) hint.textContent = shown ? shown + ' of ' + total + ' models match. Open the Model list to choose.' : 'No model matches. Clear the search to see all ' + total + '.';
      else if (total) hint.textContent = total + ' models' + (free ? ' · ' + free + ' free · ' + (total - free) + ' paid' : '') + '. Search above or open the list to choose.';
      describe();
    }
    function describe() {
      var m = state.list.filter(function (x) { return x.id === model.value; })[0];
      if (!info) return;
      if (!m) { info.textContent = model.value ? model.value : ''; return; }
      var bits = [m.id];
      if (badgeText(m)) bits.push(badgeText(m));
      if (m.price) bits.push(m.price);
      if (m.ctx) bits.push((m.ctx >= 1e6 ? (Math.round(m.ctx / 1e5) / 10) + 'M' : Math.round(m.ctx / 1000) + 'k') + ' context');
      bits.push(m.tools === false ? 'cannot look up data (no tool support)' : 'can look up data');
      info.textContent = bits.join(' · ');
      info.classList.toggle('warn', m.tools === false);
    }
    function fillModels(models, current) {
      var seen = {};
      state.list = (models || []).map(function (m) { return typeof m === 'string' ? { id: m, name: m } : m; }).filter(function (m) { if (!m.id || seen[m.id]) return false; seen[m.id] = true; return true; });
      if (current) { if (!seen[current]) model.replaceChildren(new Option(current + ' (saved)', current)); model.value = current; }
      render();
    }
    function setTierUi(p) { if (tierBox) { tierBox.hidden = p !== 'openrouter'; state.tier = ''; tierBox.querySelectorAll('button').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-tier') === ''); }); } }
    if (qIn) qIn.addEventListener('input', function () { state.q = qIn.value.trim().toLowerCase(); render(); });
    if (qIn) qIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); var first = model.querySelector('option'); if (first) { model.value = first.value; describe(); model.focus(); } } });
    if (tierBox) tierBox.addEventListener('click', function (e) { var b = e.target.closest('[data-tier]'); if (!b) return; state.tier = b.getAttribute('data-tier'); tierBox.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); render(); });
    model.addEventListener('change', describe);
    function hasKey(p) { var k = f.elements.namedItem('key_' + p); return !!(k && k.value.trim()); }
    function loadModels(auto) {
      var button = document.getElementById('aic-models-btn'), provider = f.provider.value, current = model.value;
      if (loaded[provider]) { fillModels(loaded[provider], current); return; }
      button.disabled = true; button.textContent = 'Loading…'; hint.textContent = 'Loading every model from ' + provider + '…';
      var fd = new FormData(f); fd.set('action', 'models');
      post(fd).then(function (j) {
        if (f.provider.value !== provider) return;
        if (!j.success) { if (!auto) { out.textContent = j.message; out.style.color = 'var(--red-ink)'; } hint.textContent = auto ? 'Showing the suggested models. Press Load models for the full list.' : 'Your current model is unchanged.'; return; }
        if (!Array.isArray(j.models) || !j.models.length) { hint.textContent = 'No models returned. Your current selection is unchanged.'; return; }
        loaded[provider] = j.models;
        fillModels(j.models, model.value || current);
        hint.textContent = j.models.length + ' models' + (j.paid !== null && j.paid !== undefined ? ' · ' + j.free + ' free · ' + j.paid + ' paid' : '') + '. Search above or open the list to choose.';
        if (!auto) { out.textContent = 'Models loaded'; out.style.color = 'var(--green-ink)'; }
      }).catch(function () { if (f.provider.value === provider && !auto) { out.textContent = 'Could not load models. Try again.'; out.style.color = 'var(--red-ink)'; }
      }).finally(function () { button.disabled = false; button.textContent = 'Load models'; if (/^Loading every model/.test(hint.textContent)) hint.textContent = 'Your current model is unchanged. Try loading again.'; });
    }
    f.addEventListener('submit', function (e) { e.preventDefault(); var fd = new FormData(f); fd.set('action', 'save'); post(fd).then(function (j) { done(j, 'Saved'); }); });
    document.querySelectorAll('[data-prov]').forEach(function (b) {
      b.addEventListener('click', function () {
        var p = b.getAttribute('data-prov'); remembered[f.provider.value] = model.value; f.provider.value = p;
        if (qIn) { qIn.value = ''; state.q = ''; }
        setTierUi(p);
        model.replaceChildren();
        fillModels(loaded[p] || presets[p] || [], remembered[p]);
        hint.textContent = 'Choose a model, or load the full list from your provider.'; out.textContent = '';
        document.querySelectorAll('[data-prov]').forEach(function (x) { x.classList.toggle('on', x === b); });
        document.querySelectorAll('[data-key-for]').forEach(function (x) { x.style.display = x.getAttribute('data-key-for') !== p ? 'none' : ''; });
        if (!loaded[p] && hasKey(p)) loadModels(true);
      });
    });
    document.getElementById('aic-test').addEventListener('click', function () {
      out.textContent = 'Testing…';
      var fd = new FormData(f); fd.set('action', 'test');
      post(fd).then(function (j) { out.textContent = j.success ? j.message : j.message; out.style.color = j.success ? 'var(--green-ink)' : 'var(--red-ink)'; });
    });
    document.getElementById('aic-models-btn').addEventListener('click', function () { loaded[f.provider.value] = null; loadModels(false); });
    fillModels(presets[f.provider.value] || [], model.value);
    if (hasKey(f.provider.value)) loadModels(true);
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
