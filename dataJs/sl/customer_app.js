/* Customer app screens: notification tap-through, delivery price calculator, cancel on the status page. */
(function () {
  'use strict';
  var SL = window.SL || {};

  /* An unread notification is marked read (existing endpoint) before its link opens. */
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-ca-read]');
    if (!a || !SL.post || e.metaKey || e.ctrlKey || e.shiftKey || e.button) return;
    e.preventDefault();
    var go = function () { location.href = a.href; };
    SL.post('ajax/sl/notifications_ajax.php', { op: 'read', id: a.getAttribute('data-ca-read') }).then(go, go);
  });

  /* After a cancel the endpoint sends the customer to the request page; stay on the status page instead. */
  document.addEventListener('sl:done', function (e) {
    var b = e.target.closest && e.target.closest('[data-ca-stay]');
    var j = e.detail || {};
    if (b && (j.success === true || j.status === 'success')) location.replace(location.href);
  });

  /* Price calculator: the same rule as dl_price() (base + variable, never under the minimum), in minor units. */
  var calc = document.querySelector('[data-ca-calc]');
  if (!calc) return;
  var n = function (k) { return parseInt(calc.getAttribute('data-' + k), 10) || 0; };
  var cur = calc.getAttribute('data-cur') || '';
  var money = function (c) { return cur + ' ' + (c / 100).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var inp = function (k) { return calc.querySelector('[data-ca-in="' + k + '"]'); };
  var out = function (k, v) { var el = calc.querySelector('[data-ca-out="' + k + '"]'); if (el) el.textContent = v; };
  function mode() { var on = calc.querySelector('[data-mx-seg] .on'); return on ? on.getAttribute('data-mx-panel') : calc.getAttribute('data-mode'); }
  function run() {
    var m = mode(), variable = 0, how = '';
    if (m === 'kilometre') { var km = parseInt(inp('km').value, 10) || 0; out('km', km); variable = Math.ceil(km * n('km')); how = km + ' km × ' + money(n('km')); }
    else if (m === 'package') { var p = parseInt(inp('parcels').value, 10) || 1; out('parcels', p); variable = p * n('pkg'); how = p + ' × ' + money(n('pkg')); }
    else { variable = parseInt(inp('zone').value, 10) || 0; how = 'Zone fee ' + money(variable); }
    var sum = n('base') + variable, total = Math.max(n('min'), sum);
    out('total', money(total));
    out('how', (n('base') ? 'Base ' + money(n('base')) + ' + ' : '') + how + (total > sum ? ' · minimum applies' : ''));
  }
  calc.addEventListener('input', run);
  calc.addEventListener('change', run);
  calc.addEventListener('click', function (e) { if (e.target.closest('[data-mx-seg] [data-mx-panel]')) setTimeout(run, 0); });
  run();
})();
