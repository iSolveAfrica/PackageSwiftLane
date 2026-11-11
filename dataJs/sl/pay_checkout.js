/* PayCheckout: package checklist of one consolidated bill + Paystack hand-off via ajax/customer/my_bills_ajax.php.
   The amount shown here is a preview; the server recomputes it at checkout and the gateway confirms payment. */
(function () {
  'use strict';
  var cfg = document.getElementById('pc-config');
  if (!cfg) return;
  var URL_ = 'ajax/customer/my_bills_ajax.php';
  var sel = document.getElementById('pc-bill'), box = document.getElementById('pc-packages');
  var sum = document.getElementById('pc-summary'), tot = document.getElementById('pc-total'), pay = document.getElementById('pc-pay');
  var pkgs = [], loadVersion = 0, paying = false;
  function money(v) { return 'GHS ' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function esc(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
  function refresh() {
    var chosen = pkgs.filter(function (p) { return !p.cleared && box.querySelector('input[value="' + p.oid + '"]').checked; });
    var t = chosen.reduce(function (a, p) { return a + Number(p.share || 0); }, 0);
    sum.innerHTML = chosen.map(function (p) { return '<div><span>' + esc(p.tracking) + '</span><span>' + money(p.share) + '</span></div>'; }).join('') || '<div><span class="mut">Nothing selected</span><span></span></div>';
    tot.textContent = money(t);
    pay.disabled = !chosen.length;
    pay.querySelector('span').textContent = chosen.length ? 'Pay ' + money(t) : 'Pay';
  }
  function load(cid) {
    var version = ++loadVersion; pkgs = [];
    box.setAttribute('aria-busy', 'true'); sum.textContent = ''; tot.textContent = '—';
    box.innerHTML = '<span class="mut sm">Loading the packages on this bill…</span>';
    pay.disabled = true;
    SL.get(URL_, { action: 'bill', cid: cid }).then(function (j) {
      if (version !== loadVersion) return;
      if (!j.ok) { box.innerHTML = '<div class="sl-banner bad">' + esc(j.message || 'This bill could not be loaded.') + '</div>'; return; }
      pkgs = j.packages || [];
      box.innerHTML = pkgs.map(function (p) {
        return '<label class="row g10 sl-card" style="padding:10px 12px;cursor:pointer"><input type="checkbox" value="' + p.oid + '"' + (p.cleared ? ' disabled' : ' checked') + '>' +
          '<span class="grow mono">' + esc(p.tracking) + '</span><span class="mono">' + (p.cleared ? '<span class="sl-pill delivered">Paid</span>' : money(p.share)) + '</span></label>';
      }).join('') || '<span class="mut sm">No packages on this bill.</span>';
      box.querySelectorAll('input').forEach(function (i) { i.addEventListener('change', refresh); });
      refresh();
    }).catch(function () { if (version === loadVersion) box.innerHTML = '<div class="sl-banner bad" role="alert">Network error. Reload the page.</div>'; })
      .finally(function () { if (version === loadVersion) box.removeAttribute('aria-busy'); });
  }
  if (sel) sel.addEventListener('change', function () { load(sel.value); });
  pay.addEventListener('click', function () {
    if (paying || pay.disabled || box.getAttribute('aria-busy') === 'true') return;
    var orders = Array.prototype.map.call(box.querySelectorAll('input:checked:not(:disabled)'), function (i) { return parseInt(i.value, 10); });
    if (!orders.length) return;
    paying = true; pay.setAttribute('aria-busy','true');
    pay.disabled = true; pay.classList.add('loading');
    if (sel) sel.disabled = true;
    box.querySelectorAll('input:not(:disabled)').forEach(function (input) { input.dataset.checkoutLocked = 'true'; input.disabled = true; });
    SL.post(URL_ + '?action=checkout', { action: 'checkout', cid: sel ? sel.value : cfg.getAttribute('data-cid'), orders: JSON.stringify(orders) }).then(function (j) {
      if (j.ok && j.url) { location.href = j.url; return; }
      SL.toast(j.message || 'The payment could not be started.', true);
      pay.disabled = false;
    }).catch(function () { SL.toast('Network error', true); pay.disabled = false; })
      .finally(function () {
        paying = false; pay.classList.remove('loading'); pay.removeAttribute('aria-busy');
        if (sel) sel.disabled = false;
        box.querySelectorAll('[data-checkout-locked]').forEach(function (input) { input.disabled = false; delete input.dataset.checkoutLocked; });
      });
  });
  load(sel ? sel.value : cfg.getAttribute('data-cid'));
})();
