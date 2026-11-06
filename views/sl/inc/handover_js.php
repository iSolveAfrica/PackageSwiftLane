<?php /** Shared client logic of the code → choose → hand over flow (WarehouseDelivery, HandOut). Expects #ho-* elements; customer id in data-customer on #ho-flow. */ ?>
<script>
(function () {
  var flow = document.getElementById('ho-flow'); if (!flow) return;
  var cust = flow.getAttribute('data-customer');
  function code() { var v = ''; flow.querySelectorAll('.ho-digit').forEach(function (i) { v += i.value; }); var one = flow.querySelector('#ho-code-one'); return one && one.value ? one.value : v; }
  flow.addEventListener('input', function (e) { if (e.target.classList.contains('ho-digit')) { e.target.value = e.target.value.replace(/\D/g, '').slice(0, 1); var n = e.target.nextElementSibling; if (e.target.value && n && n.classList.contains('ho-digit')) n.focus(); } });
  flow.addEventListener('keydown', function (e) { if (e.target.classList.contains('ho-digit') && e.key === 'Backspace' && !e.target.value) { var p = e.target.previousElementSibling; if (p) p.focus(); } });
  flow.addEventListener('paste', function (e) { if (!e.target.classList.contains('ho-digit')) return; var t = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6); if (!t) return; e.preventDefault(); flow.querySelectorAll('.ho-digit').forEach(function (i, k) { i.value = t[k] || ''; }); });
  function verifiedUI(list) { flow.classList.add('is-verified'); flow.querySelectorAll('[data-item]').forEach(function (r) { var ok = list.indexOf(r.getAttribute('data-item')) >= 0 && r.getAttribute('data-cleared') !== '0'; r.classList.toggle('ok', ok); var c = r.querySelector('input[type=checkbox]'); if (c) { c.disabled = !ok; c.checked = ok; } }); var s2 = document.getElementById('ho-step2'); if (s2) s2.hidden = false; count(); }
  var vb = document.getElementById('ho-verify');
  if (vb) vb.addEventListener('click', function () {
    var c = code(); if (c.length !== 6) return SL.toast('Type the 6-digit code', true);
    vb.disabled = true;
    SL.post('ajax/sl/warehouse_ajax.php', { op: 'verify_customer', customer: cust, code: c }).then(function (j) {
      SL.toast(j.message || '', !j.success);
      var bad = document.getElementById('ho-bad'); if (bad) { bad.hidden = !!j.success; bad.querySelector('span').textContent = j.message || ''; }
      if (j.success) verifiedUI(j.verified || []);
    }).finally(function () { vb.disabled = false; });
  });
  var sb = document.getElementById('ho-send');
  if (sb) sb.addEventListener('click', function () { sb.disabled = true; SL.post('ajax/sl/warehouse_ajax.php', { op: 'send_customer', customer: cust }).then(function (j) { SL.toast(j.message || '', !j.success); }).finally(function () { setTimeout(function () { sb.disabled = false; }, 2000); }); });
  var kind = 'picked_up';
  flow.addEventListener('click', function (e) { var k = e.target.closest('[data-kind]'); if (!k) return; e.preventDefault(); kind = k.getAttribute('data-kind'); flow.querySelectorAll('[data-kind]').forEach(function (x) { x.classList.toggle('on', x === k); }); });
  function sel() { return Array.prototype.slice.call(flow.querySelectorAll('[data-item] input[type=checkbox]:checked')).map(function (c) { return c.closest('[data-item]').getAttribute('data-item'); }); }
  function count() { var n = sel().length, b = document.getElementById('ho-go'); if (b) { b.disabled = !n; b.querySelector('span').textContent = 'Hand over ' + n + ' parcel' + (n === 1 ? '' : 's'); } var left = flow.querySelectorAll('[data-item]').length - n, l = document.getElementById('ho-left'); if (l) l.textContent = left > 0 ? left + ' stay · a partial collection can be verified again later' : ''; }
  flow.addEventListener('change', function (e) { if (e.target.type === 'checkbox') count(); });
  var scan = document.getElementById('ho-scan-in');
  if (scan) scan.addEventListener('keydown', function (e) { if (e.key !== 'Enter') return; e.preventDefault(); var v = scan.value.trim().toUpperCase(); var row = null; flow.querySelectorAll('[data-item]').forEach(function (r) { if ((r.getAttribute('data-trk') || '').toUpperCase() === v || (r.getAttribute('data-post') || '').toUpperCase() === v) row = r; }); var bad = document.getElementById('ho-scanbad'); if (!row) { if (bad) { bad.hidden = false; bad.querySelector('span').textContent = v + ' is not one of this customer\'s ready parcels.'; } } else { if (bad) bad.hidden = true; var c = row.querySelector('input[type=checkbox]'); if (c && !c.disabled) { c.checked = true; row.classList.add('scanned'); count(); } } scan.value = ''; });
  var go = document.getElementById('ho-go');
  if (go) go.addEventListener('click', function () {
    var items = sel(); if (!items.length) return;
    var chk = flow.querySelectorAll('.ho-need:not(.on)'); if (chk.length) return SL.toast('Tick the checks first', true);
    go.disabled = true;
    SL.post('ajax/sl/warehouse_ajax.php', { op: 'handover', items: items.join(','), handover_type: kind }).then(function (j) {
      SL.toast(j.message || '', !j.success);
      if (j.success) { var done = document.getElementById('ho-done'); if (done) { done.hidden = false; done.querySelector('h2').textContent = j.message; flow.querySelectorAll('.ho-hide-done').forEach(function (x) { x.hidden = true; }); } else setTimeout(function () { location.reload(); }, 500); }
    }).finally(function () { go.disabled = false; });
  });
  flow.addEventListener('click', function (e) { var c = e.target.closest('.ho-need'); if (!c) return; c.classList.toggle('on'); var s = c.querySelector('.sl-chk'); if (s) s.classList.toggle('on'); });
  <?php if (!empty($slPreVerified)): ?>verifiedUI(<?= json_encode($slPreVerified) ?>);<?php endif; ?>
  count();
})();
</script>
