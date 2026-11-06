/* Address editor (views/sl/pages/partials/address_rows.php): add / remove rows, cascade country > state > city,
   renumber the legacy address[i] / country[i] ... names before the form is submitted. */
(function () {
  'use strict';
  if (window.__slAddrRows) return;
  window.__slAddrRows = true;

  function fill(sel, url, ph) {
    sel.innerHTML = '<option value="">' + ph + '</option>';
    if (!url) return Promise.resolve();
    sel.disabled = true;
    return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (list) {
      (list || []).forEach(function (x) { var o = document.createElement('option'); o.value = x.id; o.textContent = x.text; sel.appendChild(o); });
    }).catch(function () {}).finally(function () { sel.disabled = false; });
  }
  function count(list) {
    var rows = list.querySelectorAll('[data-addr-row]');
    var t = list.querySelector('input[name="total_address"]'); if (t) t.value = rows.length;
    var e = list.querySelector('[data-addr-empty]'); if (e) e.hidden = rows.length > 0;
    return rows;
  }
  /* Rename "x[]" / "x[n]" to contiguous indexes so the endpoints read address[0..total-1]. */
  function renumber(form) {
    form.querySelectorAll('[data-addr-list]').forEach(function (list) {
      count(list).forEach(function (row, i) {
        row.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/\[\d*\]$/, '[' + i + ']'); });
      });
    });
  }
  document.addEventListener('submit', function (e) { if (e.target.querySelector && e.target.querySelector('[data-addr-list]')) renumber(e.target); }, true);
  document.addEventListener('change', function (e) {
    var row = e.target.closest('[data-addr-row]'); if (!row) return;
    if (e.target.matches('[data-addr-country]')) {
      var v = e.target.value, st = row.querySelector('[data-addr-state]'), ci = row.querySelector('[data-addr-city]');
      fill(ci, '', 'Choose a city');
      fill(st, v ? 'ajax/select2_states.php?id=' + encodeURIComponent(v) : '', 'Choose a region');
    } else if (e.target.matches('[data-addr-state]')) {
      var s = e.target.value;
      fill(row.querySelector('[data-addr-city]'), s ? 'ajax/select2_cities.php?id=' + encodeURIComponent(s) : '', 'Choose a city');
    }
  });
  document.addEventListener('click', function (e) {
    var add = e.target.closest('[data-addr-add]');
    if (add) {
      var list = add.closest('[data-addr-list]'), tpl = list.querySelector('template[data-addr-tpl]');
      var box = document.createElement('div'); box.innerHTML = tpl.innerHTML.trim();
      var row = box.firstElementChild; list.insertBefore(row, tpl); count(list);
      var f = row.querySelector('select'); if (f) f.focus();
      return;
    }
    var rm = e.target.closest('[data-addr-remove]');
    if (!rm) return;
    var r = rm.closest('[data-addr-row]'), l = rm.closest('[data-addr-list]');
    var url = rm.getAttribute('data-addr-delete');
    if (!url) { r.remove(); count(l); return; }
    if (!confirm('Delete this saved address? This cannot be undone.')) return;
    SL.post(url, { id: rm.getAttribute('data-id') }).then(function (j) {
      var ok = j.success === true || j.status === 'success';
      var msg = j.message || (j.messages && j.messages.join ? j.messages.join(' ') : '') || (j.errors ? Object.keys(j.errors).map(function (k) { return j.errors[k]; }).join(' ') : '');
      SL.toast(msg || (ok ? 'Address deleted' : 'Could not delete the address'), !ok);
      if (ok) { r.remove(); count(l); }
    }).catch(function () { SL.toast('Network error', true); });
  });
})();
