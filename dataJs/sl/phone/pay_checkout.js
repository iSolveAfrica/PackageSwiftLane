/* Phone M-CPay: same flow as dataJs/sl/pay_checkout.js (ajax/customer/my_bills_ajax.php action=bill, then
   action=checkout → Paystack hosted page), drawn with the design's checklist (.b2-ck) and summary (.b2-ml).
   Amounts here are a preview; the server recomputes them at checkout and the gateway confirms payment. */
(function () {
  'use strict';
  var cfg = document.getElementById('pc-config');
  if (!cfg || !window.SL) return;
  var URL_ = 'ajax/customer/my_bills_ajax.php';
  var sel = document.getElementById('pc-bill'), box = document.getElementById('pc-packages');
  var sum = document.getElementById('pc-summary'), tot = document.getElementById('pc-total'), pay = document.getElementById('pc-pay');
  var ftot = document.getElementById('pc-foot-total'), cnt = document.getElementById('pc-count');
  var pkgs = [], loadVersion = 0, paying = false;
  function money(v) { return 'GHS ' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function esc(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
  function billId() { return sel ? sel.value : cfg.getAttribute('data-cid'); }
  function refresh() {
    var chosen = [];
    box.querySelectorAll('label').forEach(function (l) {
      var i = l.querySelector('input'); if (!i) return;
      var cb = l.querySelector('.b2-cb'); if (cb) cb.classList.toggle('on', i.checked && !i.disabled);
      if (i.checked && !i.disabled) { var p = pkgs.filter(function (x) { return String(x.oid) === i.value; })[0]; if (p) chosen.push(p); }
    });
    var t = chosen.reduce(function (a, p) { return a + Number(p.share || 0); }, 0);
    var rows = chosen.map(function (p) { return '<div><span>' + esc(p.tracking) + '</span><em>' + money(p.share) + '</em></div>'; }).join('');
    sum.innerHTML = rows || '<div class="mu"><span>Nothing selected</span><em></em></div>';
    tot.textContent = money(t); if (ftot) ftot.textContent = money(t);
    if (cnt) cnt.textContent = 'Secured by Paystack · ' + chosen.length + ' package' + (chosen.length === 1 ? '' : 's');
    pay.disabled = !chosen.length;
    pay.querySelector('span').textContent = chosen.length ? 'Pay ' + money(t) : 'Pay';
  }
  function load(cid) {
    var version = ++loadVersion; pkgs = [];
    box.setAttribute('aria-busy', 'true'); sum.innerHTML = ''; tot.textContent = '—'; if (ftot) ftot.textContent = '—';
    box.innerHTML = '<label class="dis"><span class="mx-rm"><b>Loading the packages on this bill…</b></span></label>';
    pay.disabled = true;
    SL.get(URL_, { action: 'bill', cid: cid }).then(function (j) {
      if (version !== loadVersion) return;
      if (!j.ok) { box.innerHTML = '<label class="dis"><span class="mx-rm"><b>' + esc(j.message || 'This bill could not be loaded.') + '</b></span></label>'; return; }
      pkgs = j.packages || [];
      box.innerHTML = pkgs.map(function (p) {
        return '<label class="' + (p.cleared ? 'dis' : '') + '"><input type="checkbox" value="' + esc(p.oid) + '"' + (p.cleared ? ' disabled' : ' checked') + ' aria-label="' + esc(p.tracking) + '">' +
          '<span class="b2-cb' + (p.cleared ? '' : ' on') + '" aria-hidden="true"><i class="ic ic-sm i-check" aria-hidden="true"></i></span>' +
          '<span class="mx-rm"><b class="mono">' + esc(p.tracking) + '</b><small>' + (p.cleared ? 'paid' : 'owing') + '</small></span>' +
          '<b class="b2-amt">' + (p.cleared ? 'Paid' : money(p.share)) + '</b></label>';
      }).join('') || '<label class="dis"><span class="mx-rm"><b>No packages on this bill.</b></span></label>';
      box.querySelectorAll('input').forEach(function (i) { i.addEventListener('change', refresh); });
      refresh();
    }).catch(function () { if (version === loadVersion) box.innerHTML = '<label class="dis" role="alert"><span class="mx-rm"><b>Network error. Reload the page.</b></span></label>'; })
      .finally(function () { if (version === loadVersion) box.removeAttribute('aria-busy'); });
  }
  if (sel && sel.tagName === 'SELECT') sel.addEventListener('change', function () { load(sel.value); });
  pay.addEventListener('click', function () {
    if (paying || pay.disabled || box.getAttribute('aria-busy') === 'true') return;
    var orders = Array.prototype.map.call(box.querySelectorAll('input:checked:not(:disabled)'), function (i) { return parseInt(i.value, 10); });
    if (!orders.length) return;
    paying = true; pay.setAttribute('aria-busy', 'true'); pay.disabled = true;
    if (sel) sel.disabled = true;
    box.querySelectorAll('input:not(:disabled)').forEach(function (input) { input.dataset.checkoutLocked = 'true'; input.disabled = true; });
    SL.post(URL_ + '?action=checkout', { action: 'checkout', cid: billId(), orders: JSON.stringify(orders) }).then(function (j) {
      if (j.ok && j.url) { location.href = j.url; return; }
      SL.toast(j.message || 'The payment could not be started.', true);
      pay.disabled = false;
    }).catch(function () { SL.toast('Network error', true); pay.disabled = false; })
      .finally(function () {
        paying = false; pay.removeAttribute('aria-busy');
        if (sel) sel.disabled = false;
        box.querySelectorAll('[data-checkout-locked]').forEach(function (input) { input.disabled = false; delete input.dataset.checkoutLocked; });
      });
  });
  load(billId());
})();
