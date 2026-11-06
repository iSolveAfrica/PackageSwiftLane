/* Support inbox: bulk changes and the quick filters. */
(function () {
  'use strict';
  function selected() { return Array.prototype.map.call(document.querySelectorAll('.cs-row-chk.on'), function (c) { return c.getAttribute('data-id'); }); }
  function refresh() {
    var n = selected().length, bar = document.getElementById('cs-bulk');
    if (bar) { bar.style.display = n === 0 ? 'none' : 'flex'; var cnt = bar.querySelector('[data-cs-n]'); if (cnt) cnt.textContent = n + ' selected'; }
  }
  document.addEventListener('click', function (e) {
    var c = e.target.closest('.cs-row-chk');
    if (c) { c.classList.toggle('on'); refresh(); return; }
    var all = e.target.closest('#cs-all');
    if (all) { var on = !all.classList.contains('on'); all.classList.toggle('on', on); document.querySelectorAll('.cs-row-chk').forEach(function (x) { x.classList.toggle('on', on); }); refresh(); return; }
    var b = e.target.closest('[data-cs-bulk]');
    if (b) {
      e.preventDefault();
      var ids = selected(); if (!ids.length) return;
      var data = {}; ids.forEach(function (id, i) { data['ids[' + i + ']'] = id; });
      var k = b.getAttribute('data-cs-bulk'), sel = document.getElementById('cs-bulk-' + k);
      var v = sel ? sel.value : b.getAttribute('data-value');
      if (!v) { SL.toast('Pick a value first', true); return; }
      data[k] = v;
      CS.post(CS.desk, 'bulk', data).then(function (j) { SL.toast(j.success ? ids.length + ' tickets updated' : j.message, !j.success); if (j.success) setTimeout(function () { location.reload(); }, 300); });
    }
  });
  var f = document.getElementById('cs-filters');
  var tg = document.getElementById('cs-filter-btn');
  if (tg && f) tg.addEventListener('click', function () { var show = f.style.display === 'none'; f.style.display = show ? 'flex' : 'none'; tg.classList.toggle('on', show); });
})();
