/* Report runner: loads the legacy report endpoint's HTML and restyles it with the design system. */
(function () {
  var form = document.getElementById('rv-filters'); if (!form) return;
  var out = document.getElementById('rv-out'), endpoint = form.getAttribute('data-ajax');
  function fmt(v) { return v ? v.split('-').join('/') : ''; }
  function params(page) {
    var q = new URLSearchParams();
    var f = document.getElementById('rv-from'), t = document.getElementById('rv-to');
    if (f && t && f.value && t.value) q.set('range', fmt(f.value) + ' - ' + fmt(t.value));
    form.querySelectorAll('select[name]').forEach(function (s) { q.set(s.name, s.value); });
    q.set('page', page || 1); q.set('per_page', 50);
    return q;
  }
  function restyle() {
    out.querySelectorAll('table').forEach(function (t) {
      t.className = 'sl-table';
      if (!t.parentElement.classList.contains('sl-tw')) { var w = document.createElement('div'); w.className = 'sl-tw'; t.parentNode.insertBefore(w, t); w.appendChild(t); }
    });
    out.querySelectorAll('.label').forEach(function (l) { var bg = l.style.background || l.style.backgroundColor; l.className = 'sl-pill plain'; if (bg) { l.style.background = ''; l.style.boxShadow = 'inset 0 0 0 1px ' + bg; } });
    out.querySelectorAll('.pagination').forEach(function (p) { var pg = document.createElement('div'); pg.className = 'sl-pager'; var inner = document.createElement('div'); inner.className = 'sl-pg'; p.querySelectorAll('a').forEach(function (a) { var n = a.cloneNode(true); n.className = a.parentElement.classList.contains('active') ? 'on' : ''; n.removeAttribute('style'); inner.appendChild(n); }); pg.appendChild(document.createElement('span')); pg.appendChild(inner); p.closest('.pull-right, nav, div').replaceWith(pg); });
    out.querySelectorAll('img[src*="ohh_shipment"]').forEach(function (i) { var e = document.createElement('div'); e.className = 'sl-empty'; e.textContent = 'No records for these filters.'; (i.closest('div') || i).replaceWith(e); });
    out.querySelectorAll('.alert').forEach(function (a) { a.className = 'sl-banner'; });
    out.querySelectorAll('.btn').forEach(function (b) { b.classList.remove('btn', 'btn-primary', 'btn-info', 'btn-success', 'btn-danger', 'btn-outline-info', 'btn-sm'); b.classList.add('sl-btn', 'ghost', 'sm'); });
  }
  window.cdp_load = function (page) {
    out.innerHTML = '<div class="sl-empty">Loading…</div>';
    fetch(endpoint + '?' + params(page).toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.text(); })
      .then(function (html) { out.innerHTML = html || '<div class="sl-empty">No records for these filters.</div>'; restyle(); })
      .catch(function () { out.innerHTML = '<div class="sl-banner bad">The report could not be loaded. Try again.</div>'; });
  };
  form.addEventListener('submit', function (e) { e.preventDefault(); window.cdp_load(1); });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-rv-export]'); if (!b) return;
    window.open(b.getAttribute('data-rv-export') + '?' + params(1).toString(), '_blank', 'noopener');
  });
  window.cdp_load(1);
})();
