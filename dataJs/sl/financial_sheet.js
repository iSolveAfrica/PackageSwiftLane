/* Financial Sheet workspace (views/sl/pages/FinancialSheet.php). Plain JS on SL.post.
 * Same actions and payloads as the previous screen (dataJs/financial_sheet.js);
 * the server keeps every money rule. Storage is canonical USD; bills are GHS. */
(function () {
  'use strict';
  var AJ = 'ajax/reports/financial_sheet_ajax.php', C = window.FS || {}, $ = function (i) { return document.getElementById(i); };
  var st = { by: 'consol', cid: 0, sid: 0, sheet: null, cur: 'ghs', locks: {}, stats: {} };
  var rate = function () { return Number(C.rate) > 0 ? Number(C.rate) : 1; };
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML.replace(/"/g, '&quot;'); }
  function n2(v) { return (Number(v) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function usd(v) { return st.cur === 'usd' ? '$' + n2(v) : '₵' + n2((Number(v) || 0) * rate()); }
  function ghs(v) { return st.cur === 'usd' ? '$' + n2((Number(v) || 0) / rate()) : '₵' + n2(v); }
  function G(v) { return '₵' + n2(v); }
  function ok(j) { return j && (j.ok === true || j.success === true); }
  function post(data) { return SL.post(AJ, data); }
  function fail(j, dflt) { SL.toast((j && j.message) || (j && j.error === 'locked' ? 'Being edited by ' + (j.by || 'another user') : dflt || 'Could not save'), true); }
  function pill(t, cls) { return '<span class="sl-pill ' + (cls || 'plain') + '">' + esc(t) + '</span>'; }

  /* ---------- dialog ---------- */
  var dlgOk = null;
  function dlg(title, body, okLabel, onOk, opts) {
    opts = opts || {};
    $('fs-dlg-t').textContent = title; $('fs-dlg-b').innerHTML = body; $('fs-dlg-e').hidden = true;
    $('fs-dlg-ok').textContent = okLabel || 'OK'; $('fs-dlg-ok').hidden = !onOk; $('fs-dlg-c').textContent = onOk ? 'Cancel' : 'Close';
    dlgOk = onOk; $('fs-dlg').hidden = false; if (opts.open) opts.open($('fs-dlg-b'));
  }
  function dlgErr(m) { $('fs-dlg-e').textContent = m; $('fs-dlg-e').hidden = false; }
  function dlgClose() { $('fs-dlg').hidden = true; dlgOk = null; }
  $('fs-dlg-ok').addEventListener('click', function () {
    if (!dlgOk) { dlgClose(); return; }
    var b = this; $('fs-dlg-e').hidden = true; var r = dlgOk();
    if (r && r.then) { b.disabled = true; r.then(function (v) { b.disabled = false; if (v !== false) dlgClose(); }, function () { b.disabled = false; dlgErr('Server error. Please try again.'); }); }
    else if (r !== false) dlgClose();
  });

  /* ---------- currency ---------- */
  $('fs-cur').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; st.cur = b.getAttribute('data-cur'); this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); renderConsols(); if (st.sheet) renderSheet(); });

  /* ---------- consolidation picker ---------- */
  var consols = [];
  function loadConsols() {
    var q = $('fs-q').value.trim();
    if (st.by !== 'consol' && q) {
      post({ action: 'sl_find', by: st.by, q: q }).then(function (j) { consols = (j.items || []).map(function (x) { x.hitLabel = x.hit; return x; }); renderConsols(); loadStats(); });
      return;
    }
    post({ action: 'sl_list', q: st.by === 'consol' ? q : '' }).then(function (j) { consols = j.items || []; renderConsols(); loadStats(); });
  }
  function loadStats() {
    var miss = consols.map(function (c) { return c.id; }).filter(function (id) { return !st.stats[id]; }).slice(0, 60);
    if (!miss.length) return;
    post({ action: 'list_stats', cids: miss.join(',') }).then(function (j) { if (j && j.stats) { Object.keys(j.stats).forEach(function (k) { st.stats[k] = j.stats[k]; }); renderConsols(); } });
  }
  function renderConsols() {
    var box = $('fs-consols');
    if (!consols.length) { box.innerHTML = '<div class="sl-empty mut sm">No consolidations found.</div>'; return; }
    var seen = {};
    box.innerHTML = consols.filter(function (c) { var k = c.id + ':' + (c.hitLabel || ''); if (seen[k]) return false; seen[k] = 1; return true; }).map(function (c) {
      var s = st.stats[c.id];
      var sub = s ? (s.custs_priced + '/' + s.custs + ' priced · ' + n2(s.weight) + ' ' + esc(C.unit) + ' · due ' + usd(s.due_usd) + (s.paid_usd > 0 ? ' · got ' + usd(s.paid_usd) : '')) : esc(c.date || '');
      return '<button type="button" class="fs-row' + (c.id === st.cid ? ' on' : '') + '" data-cid="' + c.id + '"' + (c.sid ? ' data-sid="' + c.sid + '"' : '') + '><span class="grow"><b class="mono fw5">' + esc(c.no) + '</b>' + (c.dg ? ' ' + pill('Dangerous goods', 'pending') : '') + (c.hitLabel ? ' <span class="mut sm">' + esc(c.hitLabel) + '</span>' : '') + '<small>' + sub + '</small></span><i class="ic ic-sm i-right" aria-hidden="true"></i></button>';
    }).join('');
  }
  var qt; $('fs-q').addEventListener('input', function () { clearTimeout(qt); qt = setTimeout(loadConsols, 250); });
  $('fs-by').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; st.by = b.getAttribute('data-by'); this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); $('fs-q').placeholder = { consol: 'Consolidation #', package: 'Package or tracking #', customer: 'Customer name or locker' }[st.by]; loadConsols(); });
  $('fs-consols').addEventListener('click', function (e) { var b = e.target.closest('[data-cid]'); if (!b) return; openConsol(+b.getAttribute('data-cid'), +(b.getAttribute('data-sid') || 0)); });

  /* ---------- one consolidation ---------- */
  function openConsol(cid, sid) {
    releaseLocks(); st.cid = cid; st.sid = sid || 0; renderConsols();
    history.replaceState(null, '', 'financial_sheet_consolidation.php?id=' + cid + (st.sid ? '&sid=' + st.sid : ''));
    $('fs-main').innerHTML = '<section class="sl-card"><div class="sl-empty mut sm">Loading…</div></section>';
    return reloadSheet();
  }
  function reloadSheet() {
    return post({ action: 'sl_sheet', consolidate_id: st.cid }).then(function (j) {
      if (!ok(j)) { $('fs-main').innerHTML = '<section class="sl-card"><div class="sl-empty mut">' + esc(j.message || 'Could not load the consolidation.') + '</div></section>'; return; }
      st.sheet = j; C.rate = j.rate || C.rate; $('fs-rate').textContent = C.rate;
      $('fs-ratelog').textContent = j.rate_log ? ' (updated by ' + j.rate_log.by + ' on ' + j.rate_log.at + ')' : '';
      if (st.sid && !j.customers.some(function (c) { return c.sid === st.sid; })) st.sid = 0;
      if (!st.sid && j.customers.length) st.sid = j.customers[0].sid;
      delete st.stats[st.cid]; renderSheet();
    });
  }
  function cust() { var s = st.sheet; if (!s) return null; for (var i = 0; i < s.customers.length; i++) if (s.customers[i].sid === st.sid) return s.customers[i]; return null; }
  function custState(c) {
    if (!c.billed) return c.can_bill ? ['Ready to bill', 'onway'] : ['Pricing ' + c.pkgs_priced + '/' + c.pkgs, 'sorting'];
    if (c.owing_ghs <= 0 && c.has_paid) return ['Settled', 'paid'];
    if (c.has_paid) return ['Part paid', 'onway'];
    return ['Billed', 'transit'];
  }
  function renderSheet() {
    var s = st.sheet, sm = s.summary;
    $('fs-custs-card').hidden = false; $('fs-custs-n').textContent = s.customers.length + ' · ' + sm.custs_priced + ' priced';
    $('fs-custs').innerHTML = s.customers.length ? s.customers.map(function (c) { var k = custState(c);
      return '<button type="button" class="fs-row' + (c.sid === st.sid ? ' on' : '') + '" data-sid="' + c.sid + '"><span class="sl-av" aria-hidden="true">' + esc((c.name.match(/\b\w/g) || ['?']).slice(0, 2).join('').toUpperCase()) + '</span><span class="grow">' + esc(c.name) + (c.locker ? ' <span class="mut sm mono">' + esc(c.locker) + '</span>' : '') + '<small>' + c.pkgs + ' package' + (c.pkgs === 1 ? '' : 's') + ' · ' + usd(c.usd) + (c.billed && c.owing_ghs > 0 ? ' · owes ' + ghs(c.owing_ghs) : '') + '</small></span>' + pill(k[0], k[1]) + '</button>'; }).join('') : '<div class="sl-empty mut sm">No packages in this consolidation.</div>';
    var c = cust();
    var h = '<section class="sl-card col g12"><div class="row between wrap g8"><div class="col"><h2 class="sl-ct">Consolidation <span class="mono">' + esc(s.consol.no) + '</span>' + (s.consol.dg ? ' ' + pill('Dangerous goods', 'pending') : '') + '</h2><span class="mut sm">' + esc(s.consol.date) + '</span></div><div class="row g8 wrap"><a class="sl-btn ghost sm" target="_blank" rel="noopener" href="views/print/print_financial_sheet.php?consolidate_id=' + s.consol.id + '"><i class="ic ic-sm i-print" aria-hidden="true"></i>Print</a><a class="sl-btn ghost sm" href="views/print/print_financial_sheet_excel.php?consolidate_id=' + s.consol.id + '"><i class="ic ic-sm i-download" aria-hidden="true"></i>Excel</a></div></div>' +
      '<div class="fs-kpis"><div class="fr-k"><small>Due incl. fees</small><b>' + usd(sm.due_usd) + '</b><small>' + usd(sm.base_usd) + ' + ' + usd(sm.fee_usd) + ' fees</small></div><div class="fr-k"><small>Received</small><b>' + usd(sm.paid_usd) + '</b><small>' + G(sm.paid_ghs) + '</small></div><div class="fr-k"><small>Customers priced</small><b>' + sm.custs_priced + ' / ' + sm.custs + '</b><small>all items priced</small></div></div></section>';
    if (c) h += renderCustomer(c, s.perms);
    $('fs-main').innerHTML = h;
  }
  $('fs-custs').addEventListener('click', function (e) { var b = e.target.closest('[data-sid]'); if (!b) return; releaseLocks(); st.sid = +b.getAttribute('data-sid'); history.replaceState(null, '', 'financial_sheet_consolidation.php?id=' + st.cid + '&sid=' + st.sid); renderSheet(); });

  function renderCustomer(c, p) {
    var k = custState(c), acts = [];
    if (!c.billed) { if (p.bill) acts.push('<button type="button" class="sl-btn" data-fs="bill"' + (c.can_bill ? '' : ' disabled title="All packages must be fully priced before billing"') + '><i class="ic ic-sm i-receipt" aria-hidden="true"></i>Bill customer</button>'); }
    else {
      if (p.pay && !c.all_delivered && !c.all_cleared) acts.push('<button type="button" class="sl-btn" data-fs="pay"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>' + (c.has_paid ? 'Update payment' : 'Record payment') + '</button>');
      if (p.pay && c.owing_ghs > 0) acts.push('<button type="button" class="sl-btn ghost" data-fs="debt"><i class="ic ic-sm i-card" aria-hidden="true"></i>Clear debt (' + G(c.owing_ghs) + ')</button>');
      if (p.discount) acts.push('<button type="button" class="sl-btn ghost" data-fs="disc"><i class="ic ic-sm i-tag" aria-hidden="true"></i>' + (c.disc_ghs > 0 ? 'Edit discount' : 'Apply discount') + '</button>');
      if (p.discount && c.disc_ghs > 0) acts.push('<button type="button" class="sl-btn ghost" data-fs="undisc"><i class="ic ic-sm i-x" aria-hidden="true"></i>Remove discount</button>');
      if (p.bill && !c.all_delivered) acts.push('<button type="button" class="sl-btn ghost" data-fs="bill"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Re-bill</button>');
    }
    acts.push('<button type="button" class="sl-btn ghost" data-fs="hist"><i class="ic ic-sm i-clock" aria-hidden="true"></i>Payment history</button>');
    acts.push('<button type="button" class="sl-btn ghost" data-fs="print"><i class="ic ic-sm i-print" aria-hidden="true"></i>Print shipment' + (c.orders.length > 1 ? 's' : '') + '</button>');
    var h = '<section class="sl-card col g12"><div class="row between wrap g8"><div class="col"><h2 class="sl-ct">' + esc(c.label) + ' ' + pill(k[0], k[1]) + '</h2><span class="mut sm">' + c.pkgs_priced + '/' + c.pkgs + ' packages priced · ' + n2(c.weight) + ' ' + esc(C.unit) + ' · packages ' + usd(c.usd) + (c.billed ? ' · billed by ' + esc(c.billed_by) + ' ' + esc(c.billed_at) : '') + '</span></div></div>';
    if (c.billed) h += '<div class="fs-money"><div><small>Bill</small><b>' + ghs(c.bill_ghs) + '</b></div><div><small>Discount</small><b>' + ghs(c.disc_ghs) + '</b></div><div><small>To pay</small><b>' + ghs(c.net_ghs) + '</b></div><div><small>Paid</small><b>' + ghs(c.paid_ghs) + '</b></div><div class="' + (c.owing_ghs > 0 ? 'bad' : '') + '"><small>Owing</small><b>' + ghs(c.owing_ghs) + '</b></div></div>';
    h += '<div class="row g8 wrap">' + acts.join('') + '</div></section>';
    h += '<section class="col g8"><h2 class="sl-ct">Packages</h2>' + c.packages.map(function (pk) {
      var state = pk.delivered ? pill(pk.handover || 'Delivered', 'delivered') : (pk.cleared ? pill('Paid · cleared', 'paid') : pill('Unpaid', 'pending'));
      return '<details class="fs-pkg" data-oid="' + pk.oid + '"><summary><i class="ic ic-sm i-box" aria-hidden="true"></i><b class="mono fw5">' + esc(pk.no) + '</b>' + (pk.carrier ? '<span class="mut sm mono">' + esc(pk.carrier) + '</span>' : '') + '<span class="mut sm">' + n2(pk.weight) + ' ' + esc(C.unit) + '</span>' + pill(pk.priced + '/' + pk.items + ' priced', pk.items > 0 && pk.priced >= pk.items ? 'paid' : 'plain') + state + '<span class="grow"></span><b class="mono">' + usd(pk.usd) + '</b>' + (p.clear && !pk.delivered && !pk.cleared ? '<button type="button" class="sl-btn ghost sm" data-fs="clearpkg" data-oid="' + pk.oid + '" data-no="' + esc(pk.no) + '"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Clear for delivery</button>' : '') + '</summary><div class="fs-body"><div class="sl-empty mut sm">Loading items…</div></div></details>';
    }).join('') + '</section>';
    if (c.log.length) h += '<section class="sl-card col g8"><h2 class="sl-ct">Billing log</h2><div class="fs-log">' + c.log.map(function (e) {
      return '<div><i class="ic ic-sm ' + esc(e.icon) + '" aria-hidden="true"></i><span class="grow"><b class="fw5">' + esc(e.who) + '</b> ' + esc(e.text) + (e.status ? ' ' + pill(e.status, e.statusOk ? 'paid' : 'pending') : '') + (e.ref ? ' <span class="mut mono sm">ref ' + esc(e.ref) + '</span>' : '') + ' <span class="mut sm">— ' + esc(e.when) + '</span>' + (e.facts ? '<br><span class="mut sm">' + esc(e.facts) + '</span>' : '') + '</span><span class="row g4">' + (e.check ? '<button type="button" class="sl-btn ghost sm" data-fs="checkpay" data-id="' + e.payment_id + '">Check status</button>' : '') + (e.intent ? '<button type="button" class="sl-btn ghost sm" data-fs="checkintent" data-ref="' + esc(e.intent) + '">Check</button>' : '') + (e.payment_id ? '<button type="button" class="sl-btn ghost sm" data-fs="events" data-id="' + e.payment_id + '">History</button>' : '') + '</span></div>';
    }).join('') + '</div></section>';
    return h;
  }

  /* ---------- items + locks ---------- */
  document.addEventListener('toggle', function (e) { var d = e.target; if (!d.matches || !d.matches('details.fs-pkg')) return; var oid = +d.getAttribute('data-oid'); if (d.open) loadItems(oid); else unlock(oid); }, true);
  function loadItems(oid) {
    var box = document.querySelector('details.fs-pkg[data-oid="' + oid + '"] .fs-body'); if (!box) return;
    post({ action: 'sl_items', order_id: oid }).then(function (j) {
      if (!ok(j)) { box.innerHTML = '<div class="fs-err">Failed to load items.</div>'; return; }
      var c = cust(), canPrice = st.sheet.perms.price && !(c && c.billed) || (st.sheet.perms.price && c && c.billed);
      var ed = j.editable && st.sheet.perms.price; if (j.editable) lock(oid);
      var h = j.editable ? '' : '<div class="sl-banner warn"><i class="ic ic-sm i-lock" aria-hidden="true"></i><span>Being edited by <b>' + esc(j.locked_by || 'another user') + '</b>. Read-only until they finish.</span></div>';
      j.groups.forEach(function (g) {
        h += '<div class="fs-grp" data-group="' + esc(g.token) + '"><div class="row between wrap g8"><span><i class="ic ic-sm i-link" aria-hidden="true"></i> <b class="fw5">Priced together</b> ' + pill(g.members.length + ' items', 'transit') + '</span>' + (ed ? '<button type="button" class="sl-btn ghost sm" data-fs="ungroup" data-oid="' + oid + '" data-token="' + esc(g.token) + '">Ungroup</button>' : '') + '</div><span class="mut sm">' + g.members.map(function (m) { return m.qty + ' × ' + esc(m.desc); }).join(' · ') + '</span><div class="row g8 wrap">' + priceCtl(g.mode, g.value, ed) + (ed ? '<button type="button" class="sl-btn sm" data-fs="savegroup" data-oid="' + oid + '" data-token="' + esc(g.token) + '"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save group</button>' : '') + '</div></div>';
      });
      var canGroup = ed && j.singles.length >= 2;
      if (j.singles.length) {
        h += '<div class="sl-tw"><table class="sl-table"><thead><tr>' + (canGroup ? '<th aria-label="Group"></th>' : '') + '<th>Qty</th><th>Description</th><th>Pricing</th><th></th></tr></thead><tbody>' + j.singles.map(function (it) {
          return '<tr data-iid="' + it.iid + '">' + (canGroup ? '<td><input type="checkbox" class="fs-ick" value="' + it.iid + '" aria-label="Select for grouping"></td>' : '') + '<td>' + it.qty + '</td><td class="wrap">' + esc(it.desc) + (it.priced ? ' <i class="ic ic-sm i-check" style="color:var(--green-ink)" title="Priced" aria-label="Priced"></i>' : '') + '</td><td>' + priceCtl(it.mode, it.value, ed) + '</td><td>' + (ed ? '<button type="button" class="sl-btn sm" data-fs="saveitem" data-oid="' + oid + '" data-iid="' + it.iid + '" aria-label="Save price"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save</button>' : '') + '</td></tr>';
        }).join('') + '</tbody></table></div>';
        if (canGroup) h += '<div class="row g8 wrap"><button type="button" class="sl-btn ghost sm" data-fs="group" data-oid="' + oid + '"><i class="ic ic-sm i-link" aria-hidden="true"></i>Price selected together</button><span class="mut sm">One weight or price covers the whole batch.</span></div>';
      } else if (!j.groups.length) h += '<div class="sl-empty mut sm">No items in this package.</div>';
      h += '<div class="col g4"><b class="fw5 sm">Change log</b><div class="fs-log">' + (j.history.length ? j.history.map(function (x) { return '<div><span><b class="fw5">' + esc(x.who) + '</b> ' + esc(x.what) + ' <span class="mut sm">— ' + esc(x.when) + '</span></span></div>'; }).join('') : '<div class="mut sm">No changes recorded yet.</div>') + '</div></div>';
      box.innerHTML = h; void canPrice;
    });
  }
  function priceCtl(mode, value, ed) {
    var cu = mode === 'custom', dis = ed ? '' : ' disabled';
    return '<span class="fs-price" data-mode="' + (cu ? 'custom' : 'weight') + '" data-cur="usd"><span class="sl-seg boxed" data-ctl="mode"><button type="button" data-m="weight" class="' + (cu ? '' : 'on') + '"' + dis + '>Weight</button><button type="button" data-m="custom" class="' + (cu ? 'on' : '') + '"' + dis + '>Custom</button></span><span class="sl-seg boxed" data-ctl="cur"' + (cu ? '' : ' hidden') + '><button type="button" data-c="usd" class="on"' + dis + '>$</button><button type="button" data-c="ghs"' + dis + '>₵</button></span><input class="sl-in" inputmode="decimal" aria-label="' + (cu ? 'Price' : 'Weight') + '" placeholder="' + (cu ? 'USD' : esc(C.unit)) + '" value="' + esc(value) + '"' + dis + '><span class="mut sm" data-ctl="unit"' + (cu ? ' hidden' : '') + '>' + esc(C.unit) + '</span><span class="fs-eq"></span></span>';
  }
  function eq(ctl) {
    var v = parseFloat(String(ctl.querySelector('input').value).replace(/,/g, '')), out = '';
    if (!isNaN(v) && v > 0) {
      if (ctl.getAttribute('data-mode') === 'custom') out = ctl.getAttribute('data-cur') === 'ghs' ? '≈ $' + (v / rate()).toFixed(2) + ' USD' : '≈ ₵' + (v * rate()).toFixed(2) + ' GHS';
      else if (parseFloat(C.weightRate) > 0) out = '≈ ' + C.symbol + (v * parseFloat(C.weightRate)).toFixed(2);
    }
    ctl.querySelector('.fs-eq').textContent = out;
  }
  document.addEventListener('input', function (e) { var c = e.target.closest('.fs-price'); if (c) eq(c); });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.fs-price [data-m], .fs-price [data-c]'); if (!b) return;
    var ctl = b.closest('.fs-price'), seg = b.parentNode; seg.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); });
    if (b.hasAttribute('data-m')) { var cu = b.getAttribute('data-m') === 'custom'; ctl.setAttribute('data-mode', cu ? 'custom' : 'weight'); ctl.querySelector('[data-ctl="cur"]').hidden = !cu; ctl.querySelector('[data-ctl="unit"]').hidden = cu; ctl.querySelector('input').placeholder = cu ? (ctl.getAttribute('data-cur') === 'ghs' ? 'GHS' : 'USD') : C.unit; }
    else { ctl.setAttribute('data-cur', b.getAttribute('data-c')); ctl.querySelector('input').placeholder = b.getAttribute('data-c') === 'ghs' ? 'GHS' : 'USD'; }
    eq(ctl);
  });
  function collect(ctl) {
    var mode = ctl.getAttribute('data-mode'), raw = String(ctl.querySelector('input').value || '').replace(/,/g, '').trim(), v = parseFloat(raw);
    if (raw === '' || isNaN(v) || v < 0) { SL.toast('Enter a ' + (mode === 'custom' ? 'custom price' : 'weight') + ' of 0 or more.', true); return null; }
    return { mode: mode, value: v, currency: mode === 'custom' ? ctl.getAttribute('data-cur') : 'usd' };
  }
  var hb = null;
  function lock(oid) { st.locks[oid] = 1; if (!hb) hb = setInterval(function () { Object.keys(st.locks).forEach(function (id) { post({ action: 'lock', order_id: id }).then(function (r) { if (r && r.ok === false) { delete st.locks[id]; SL.toast('Lock lost: now edited by ' + (r.by || 'another user'), true); loadItems(+id); } }); }); }, 30000); }
  function unlock(oid) { if (!st.locks[oid]) return; delete st.locks[oid]; post({ action: 'unlock', order_id: oid }); }
  function releaseLocks() { Object.keys(st.locks).forEach(unlock); }
  window.addEventListener('pagehide', function () { Object.keys(st.locks).forEach(function (id) { try { fetch(AJ + '?action=unlock&order_id=' + id, { keepalive: true, credentials: 'same-origin' }); } catch (e) {} }); });

  /* ---------- actions ---------- */
  function after(j) { if (j && j.consol && st.sheet) st.sheet.summary = j.consol; return reloadSheet().then(function () { var open = j && j._oid; if (open) { var d = document.querySelector('details.fs-pkg[data-oid="' + open + '"]'); if (d) d.open = true; } }); }
  function billTable(pk) { return '<div class="sl-tw"><table class="sl-table"><thead><tr><th>Package</th><th>Price ($)</th><th>Price (₵)</th></tr></thead><tbody>' + (pk || []).map(function (x) { return '<tr><td class="mono">' + esc(x.no) + '</td><td>$' + n2(x.usd) + '</td><td><b>' + G(x.ghs) + '</b></td></tr>'; }).join('') + '</tbody></table></div>'; }
  var A = {};
  A.saveitem = function (b) { var oid = +b.getAttribute('data-oid'), iid = +b.getAttribute('data-iid'), v = collect(b.closest('tr').querySelector('.fs-price')); if (!v) return; b.disabled = true;
    post({ action: 'save_item', order_id: oid, order_item_id: iid, mode: v.mode, value: v.value, currency: v.currency, consolidate_id: st.cid, sender_id: st.sid }).then(function (r) { b.disabled = false; if (ok(r)) { SL.toast('Price saved'); r._oid = oid; after(r); } else fail(r); }); };
  A.savegroup = function (b) { var oid = +b.getAttribute('data-oid'), v = collect(b.closest('.fs-grp').querySelector('.fs-price')); if (!v) return; b.disabled = true;
    post({ action: 'save_group', order_id: oid, group: b.getAttribute('data-token'), mode: v.mode, value: v.value, currency: v.currency, consolidate_id: st.cid, sender_id: st.sid }).then(function (r) { b.disabled = false; if (ok(r)) { SL.toast('Group saved'); r._oid = oid; after(r); } else fail(r); }); };
  A.ungroup = function (b) { var oid = +b.getAttribute('data-oid'); if (!confirm('Ungroup these items? They return to the list unpriced and each must be priced again.')) return;
    post({ action: 'clear_group', order_id: oid, group: b.getAttribute('data-token'), consolidate_id: st.cid, sender_id: st.sid }).then(function (r) { if (ok(r)) { r._oid = oid; after(r); } else { fail(r); loadItems(oid); } }); };
  A.group = function (b) { var oid = +b.getAttribute('data-oid'), ids = Array.prototype.map.call(b.closest('.fs-body').querySelectorAll('.fs-ick:checked'), function (x) { return x.value; });
    if (ids.length < 2) { SL.toast('Select at least two items to group.', true); return; }
    dlg('Price ' + ids.length + ' items together', '<p class="mut sm">One value covers the whole batch (no per-item quantity multiplier).</p>' + priceCtl('weight', '', true), 'Group and save', function () {
      var v = collect($('fs-dlg-b').querySelector('.fs-price')); if (!v) return false;
      return post({ action: 'save_group', order_id: oid, item_ids: ids.join(','), mode: v.mode, value: v.value, currency: v.currency, consolidate_id: st.cid, sender_id: st.sid }).then(function (r) { if (!ok(r)) { dlgErr(r.message || 'Could not save.'); return false; } r._oid = oid; after(r); });
    }); };
  A.bill = function () { var c = cust();
    post({ action: 'bill_preview', consolidate_id: st.cid, sender_id: st.sid }).then(function (p) {
      if (!ok(p)) { fail(p, 'Could not load the bill preview.'); return; }
      var rb = !!p.rebill;
      dlg((rb ? 'Re-bill ' : 'Bill ') + c.label + '?', billTable(p.packages) + '<div class="sl-kv col g4 sm"><span>Subtotal <b>' + G(p.sub_ghs) + '</b> ($' + n2(p.usd) + ')</span><span>Handling fee (on the customer total) <b>' + G(p.fee_ghs) + '</b></span><span>Total <b>' + G(p.total_ghs) + '</b></span>' + (rb && p.prev_ghs != null ? '<span class="mut">Previous bill ' + G(p.prev_ghs) + '</span>' : '') + '</div><div class="sl-banner info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>This notifies the customer by WhatsApp and e-mail' + (rb ? ' with the updated bill and updates the recorded bill' : ' and releases the packages from this consolidation (they are cleared for delivery only once payment is recorded)') + '. The action is logged under your name.</span></div>', rb ? 'Yes, re-bill' : 'Yes, bill now', function () {
        return post({ action: 'bill_customer', consolidate_id: st.cid, sender_id: st.sid }).then(function (r) { if (!ok(r)) { dlgErr(r.message || 'Billing failed.'); return false; }
          setTimeout(function () { dlg(r.rebill ? 'Customer re-billed' : 'Customer billed', billTable(r.packages) + '<div class="col g4 sm"><span>Subtotal <b>' + G(r.sub_ghs) + '</b> ($' + n2(r.amount_usd) + ')</span><span>Handling fee <b>' + G(r.handling_ghs || 0) + '</b></span><span>Total billed <b>' + G(r.amount_ghs) + '</b> by ' + esc(r.billed_by || 'you') + '</span><span>WhatsApp: ' + (r.sent_whatsapp ? pill('sent', 'paid') : pill('not sent', 'pending')) + ' · E-mail: ' + (r.sent_email ? pill('sent', 'paid') : pill('not sent', 'pending')) + '</span>' + (r.warnings && r.warnings.length ? '<span class="mut">' + r.warnings.map(esc).join('<br>') + '</span>' : '') + '</div>'); }, 50);
          after(r); });
      });
    }); };
  function payDialog(f, debt) {
    var c = cust(), pk = f.packages || [], gross = 0; pk.forEach(function (p) { gross += Number(p.ghs) || 0; });
    var npg = gross > 0 ? Number(f.net_ghs) / gross : 1, owing = Number(f.balance_ghs) || 0, owedAll = Number(f.owed_ghs) || 0;
    var rows = pk.map(function (p) { var act = !p.cleared && !p.delivered;
      return '<label class="fs-chk"><span class="row g8">' + (act ? '<input type="checkbox" class="fsp-pkg" data-oid="' + p.oid + '" data-ghs="' + Number(p.ghs) + '" checked>' : '<span style="width:13px"></span>' + (p.cleared ? '<span class="fsp-done" data-ghs="' + Number(p.ghs) + '" hidden></span>' : '')) + '<span class="mono">' + esc(p.no) + '</span>' + (p.delivered ? pill('delivered', 'delivered') : '') + (p.cleared ? pill('cleared', 'paid') : '') + '</span><b>' + G(p.ghs) + '</b></label>'; }).join('');
    var body = '<div class="fs-money"><div><small>Bill (net)</small><b>' + G(f.net_ghs) + '</b></div>' + (Number(f.paid_ghs) > 0 ? '<div><small>Already paid</small><b>' + G(f.paid_ghs) + '</b></div>' : '') + '<div class="bad"><small>Owing here</small><b>' + G(owing) + '</b></div>' + (owedAll > owing + 0.01 ? '<div class="bad"><small>Owed, all consolidations</small><b>' + G(owedAll) + '</b></div>' : '') + '</div>' +
      '<div class="col g4"><b class="fw5 sm">Clear these packages for delivery</b><span class="mut sm">Untick any the customer is not paying for.</span><div class="fs-scroll">' + (rows || '<div class="mut sm">No packages.</div>') + '</div><span class="row between sm"><span class="mut">Value of ticked packages</span><b id="fsp-val">₵0.00</b></span></div>' +
      '<label class="sl-field"><span class="sl-lab">Payment method</span><span class="sl-sel native"><select id="fsp-mode"><option value="cash">Cash</option><option value="paystack">Paystack</option><option value="hubtel">Hubtel</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>' +
      '<div id="fsp-cash" class="col g4"><label class="sl-field"><span class="sl-lab">Amount received (GH₵)</span><input class="sl-in" id="fsp-paid" inputmode="decimal" placeholder="0.00" value="' + (debt ? owing.toFixed(2) : '') + '"></label><span class="fs-warn" id="fsp-warn" hidden></span></div>' +
      '<div id="fsp-online" class="col g8" hidden><span class="row between sm"><span>Amount to charge</span><b id="fsp-charge">₵0.00</b></span><div class="row g8 wrap"><button type="button" class="sl-btn ghost sm" id="fsp-init">Start checkout</button><span class="mut sm" id="fsp-msg"></span></div><label class="sl-field"><span class="sl-lab">Transaction reference</span><input class="sl-in" id="fsp-ref" placeholder="Filled by checkout, verified on save"></label></div>' +
      '<label class="sl-field"><span class="sl-lab">Note (internal only)</span><textarea class="sl-in" id="fsp-note" rows="2" style="height:auto;padding:8px 12px" placeholder="Optional note"></textarea></label><span class="mut sm">The customer receives a payment receipt by WhatsApp and e-mail.</span>';
    dlg((debt ? 'Clear debt · ' : 'Record payment · ') + c.label, body, debt ? 'Record debt payment' : 'Save payment', function () {
      var b = $('fs-dlg-b'), oids = Array.prototype.map.call(b.querySelectorAll('.fsp-pkg:checked'), function (x) { return parseInt(x.getAttribute('data-oid'), 10); }), mode = $('fsp-mode').value;
      var data = { action: debt ? 'clear_debt' : 'record_payment', consolidate_id: st.cid, sender_id: st.sid, orders: JSON.stringify(oids), mode: mode, note: $('fsp-note').value.trim() };
      if (mode === 'cash') { var raw = $('fsp-paid').value.replace(/,/g, '').trim(), a = parseFloat(raw); if (raw === '' || isNaN(a) || a <= 0) { dlgErr('Enter the amount received (greater than 0).'); return false; } data.paid = a; }
      else { var ref = $('fsp-ref').value.trim(); if (!ref) { dlgErr('Start the ' + mode + ' checkout first (it fills the reference).'); return false; } data.reference = ref; }
      return post(data).then(function (r) { if (!ok(r)) { dlgErr(r.message || 'Could not save the payment.'); return false; }
        setTimeout(function () { dlg('Payment recorded', '<div class="col g4 sm"><span>Received <b>' + G(r.this_payment) + '</b> (' + esc(r.mode || 'cash') + ')</span><span>Total paid <b>' + G(r.paid_ghs) + '</b> of ' + G(r.net_ghs) + ', balance <b>' + G(r.balance_ghs) + '</b></span><span>' + ((r.cleared_orders && r.cleared_orders.length) || 0) + ' package(s) cleared for delivery.</span><span>Receipt: WhatsApp ' + (r.sent_whatsapp ? pill('sent', 'paid') : pill('not sent', 'pending')) + ' · E-mail ' + (r.sent_email ? pill('sent', 'paid') : pill('not sent', 'pending')) + '</span>' + (r.warnings && r.warnings.length ? '<span class="mut">' + r.warnings.map(esc).join('<br>') + '</span>' : '') + '</div>'); }, 50);
        after(r); });
    }, { open: function (b) {
      function sum(sel) { var g = 0; b.querySelectorAll(sel).forEach(function (x) { g += parseFloat(x.getAttribute('data-ghs')) || 0; }); return g; }
      function online() { return $('fsp-mode').value !== 'cash'; }
      function refresh() {
        $('fsp-val').textContent = G(sum('.fsp-pkg:checked')); $('fsp-charge').textContent = G(debt ? owing : sum('.fsp-pkg:checked') * npg);
        var w = $('fsp-warn'); if (online() || debt) { w.hidden = true; return; }
        var clearedNet = (sum('.fsp-pkg:checked') + sum('.fsp-done')) * npg, paidAfter = Number(f.paid_ghs) + (parseFloat($('fsp-paid').value.replace(/,/g, '')) || 0), diff = paidAfter - clearedNet;
        if (diff < -0.01) { w.textContent = 'Short by ' + G(Math.abs(diff)) + ': you are clearing packages worth ' + G(clearedNet) + ' but only ' + G(paidAfter) + ' will have been received in total.'; w.hidden = false; }
        else if (diff > 0.01) { var un = b.querySelectorAll('.fsp-pkg').length - b.querySelectorAll('.fsp-pkg:checked').length; w.textContent = G(paidAfter) + ' received, but the cleared packages are only worth ' + G(clearedNet) + (un > 0 ? ' (' + un + ' package(s) left un-cleared).' : ' (overpayment).'); w.hidden = false; }
        else w.hidden = true;
      }
      b.addEventListener('change', refresh); $('fsp-paid').addEventListener('input', refresh);
      $('fsp-mode').addEventListener('change', function () { var o = online(); $('fsp-cash').hidden = o; $('fsp-online').hidden = !o; $('fsp-msg').textContent = ''; refresh(); });
      $('fsp-init').addEventListener('click', function () { var mode = $('fsp-mode').value, amt = Math.round((debt ? owing : sum('.fsp-pkg:checked') * npg) * 100) / 100;
        if (amt <= 0) { $('fsp-msg').textContent = debt ? 'Nothing outstanding to charge.' : 'Tick at least one package to charge for.'; return; }
        $('fsp-msg').textContent = 'Starting ' + mode + ' checkout…';
        post({ action: 'gateway_init', consolidate_id: st.cid, sender_id: st.sid, mode: mode, amount: amt }).then(function (r) { if (r && r.ok && r.url) { $('fsp-ref').value = r.reference || ''; window.open(r.url, '_blank'); $('fsp-msg').textContent = 'Checkout opened. Complete it, then save to verify.'; } else $('fsp-msg').textContent = (r && r.message) || 'Could not start checkout.'; }); });
      refresh();
    } });
  }
  A.pay = function () { post({ action: 'payment_form', consolidate_id: st.cid, sender_id: st.sid }).then(function (f) { if (!ok(f)) { fail(f, 'Could not load the packages.'); return; } payDialog(f, false); }); };
  A.debt = function () { post({ action: 'payment_form', consolidate_id: st.cid, sender_id: st.sid }).then(function (f) { if (!ok(f)) { fail(f, 'Could not load the packages.'); return; } payDialog(f, true); }); };
  A.disc = function () { var c = cust(), type = 'amount', gr = parseFloat(C.weightRate) || 0;
    dlg('Discount · ' + c.label, '<span class="row between sm"><span>Customer bill</span><b>' + G(c.bill_ghs) + '</b></span><div class="sl-seg boxed" id="fsd-type"><button type="button" class="on" data-t="amount">₵ amount</button><button type="button" data-t="percent">%</button><button type="button" data-t="weight_rate">' + esc(C.symbol + '/' + C.unit) + ' rate</button></div><label class="sl-field"><span class="sl-lab">Discount</span><input class="sl-in" id="fsd-val" inputmode="decimal" placeholder="0.00" value="' + (c.disc_ghs > 0 ? c.disc_ghs.toFixed(2) : '') + '"></label><span class="sm">Discount amount: <b id="fsd-pv">' + G(c.disc_ghs) + '</b></span><span class="mut sm" id="fsd-hint" hidden>General rate is ' + esc(C.symbol) + gr.toFixed(2) + '/' + esc(C.unit) + '. Enter a lower rate for this customer; the difference on their weight-priced items becomes the discount (computed on save).</span><label class="sl-field"><span class="sl-lab">Reason (optional, internal)</span><input class="sl-in" id="fsd-reason" placeholder="e.g. loyalty, damage, goodwill"></label>', 'Save discount', function () {
      var raw = $('fsd-val').value.replace(/,/g, '').trim(), v = parseFloat(raw); if (raw === '' || isNaN(v) || v <= 0) { dlgErr('Enter a value greater than 0.'); return false; }
      return post({ action: 'apply_discount', consolidate_id: st.cid, sender_id: st.sid, disc_type: type, value: v, reason: $('fsd-reason').value.trim() }).then(function (r) { if (!ok(r)) { dlgErr(r.message || 'Could not save the discount.'); return false; } SL.toast('Discount applied: ' + G(r.discount_ghs)); after(r); });
    }, { open: function () {
      function pv() { if (type === 'weight_rate') { $('fsd-pv').textContent = 'computed on save'; return; } var v = parseFloat($('fsd-val').value.replace(/,/g, '')) || 0, a = type === 'percent' ? c.bill_ghs * Math.min(v, 100) / 100 : v; $('fsd-pv').textContent = G(Math.min(a, c.bill_ghs)); }
      $('fsd-val').addEventListener('input', pv);
      $('fsd-type').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; type = b.getAttribute('data-t'); this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); $('fsd-hint').hidden = type !== 'weight_rate'; if (type === 'weight_rate') { $('fsd-val').value = ''; $('fsd-val').placeholder = 'new ' + C.symbol + '/' + C.unit + ' rate'; } else $('fsd-val').placeholder = '0.00'; pv(); });
    } }); };
  A.undisc = function () { if (!confirm("Remove this customer's discount?")) return; post({ action: 'remove_discount', consolidate_id: st.cid, sender_id: st.sid }).then(function (r) { if (ok(r)) { SL.toast('Discount removed'); after(r); } else fail(r); }); };
  A.hist = function () { var c = cust(); post({ action: 'payment_history', sender_id: st.sid }).then(function (r) {
    if (!ok(r)) { fail(r, 'Could not load payment history.'); return; }
    var t = function (rows, cols) { return rows.length ? '<div class="sl-tw"><table class="sl-table"><thead><tr>' + cols.map(function (x) { return '<th>' + x[0] + '</th>'; }).join('') + '</tr></thead><tbody>' + rows.map(function (x) { return '<tr>' + cols.map(function (k) { return '<td>' + (k[1] === 'ghs' ? G(x.ghs) : esc(x[k[1]])) + '</td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table></div>' : '<div class="mut sm">None recorded yet.</div>'; };
    dlg('Payment history · ' + (r.name || c.label), '<div class="row g8 wrap">' + pill('Total paid ' + G(r.total_paid), 'paid') + (r.total_disc > 0 ? pill('Discounts ' + G(r.total_disc), 'transit') : '') + (r.owed_ghs > 0 ? pill('Outstanding ' + G(r.owed_ghs), 'pending') : pill('No outstanding balance', 'plain')) + '</div><b class="fw5 sm">Payments</b>' + t(r.payments || [], [['Date', 'when'], ['Consol #', 'cid'], ['Mode', 'mode'], ['Reference', 'ref'], ['Amount', 'ghs'], ['By', 'by']]) + ((r.discounts || []).length ? '<b class="fw5 sm">Discounts</b>' + t(r.discounts, [['Date', 'when'], ['Consol #', 'cid'], ['Type', 'type'], ['Reason', 'reason'], ['Amount', 'ghs'], ['By', 'by']]) : ''));
  }); };
  A.print = function () { var o = cust().orders || []; if (!o.length) return; if (o.length === 1) { window.open('print_inv_ship.php?id=' + encodeURIComponent(o[0].id), '_blank'); return; } window.open('print_inv_ship_multiple.php?data=' + JSON.stringify(o.map(function (x) { return x.no.replace(/^\D+/, ''); })), '_blank'); };
  A.clearpkg = function (b) { var oid = +b.getAttribute('data-oid'); if (!confirm('Mark ' + b.getAttribute('data-no') + ' as cleared for delivery?')) return; post({ action: 'clear_package', order_id: oid }).then(function (r) { if (ok(r)) { SL.toast('Package cleared for delivery'); after(r); } else fail(r, 'Could not clear the package.'); }); };
  A.checkpay = function (b) { b.disabled = true; post({ action: 'check_payment', payment_id: b.getAttribute('data-id') }).then(function (r) { b.disabled = false; SL.toast((r && r.message) || (ok(r) ? 'Status checked' : 'Could not check'), !ok(r)); if (ok(r)) after(r); }); };
  A.checkintent = function (b) { b.disabled = true; post({ action: 'check_intent', reference: b.getAttribute('data-ref') }).then(function (r) { b.disabled = false; SL.toast((r && r.message) || 'Checked', !ok(r)); if (r && r.changed) after(r); }); };
  A.events = function (b) { post({ action: 'payment_events', payment_id: b.getAttribute('data-id') }).then(function (r) {
    if (!ok(r)) { fail(r, 'Could not load the history.'); return; }
    var ev = r.events || [];
    dlg('Payment history', ev.length ? '<div class="sl-tw"><table class="sl-table"><thead><tr><th>When</th><th>What happened</th><th>Reported by</th></tr></thead><tbody>' + ev.map(function (e) { return '<tr><td>' + esc(e.when) + '</td><td class="wrap"><b class="fw5">' + esc(e.event) + '</b> ' + (e.from && e.to && e.from !== e.to ? esc(e.from) + ' → ' + pill(e.to, 'plain') : (e.to ? pill(e.to, 'plain') : '')) + (e.message ? '<br><span class="mut sm">' + esc(e.message) + '</span>' : '') + '</td><td>' + esc(e.source) + '<br><span class="mut sm">' + esc(e.who) + '</span></td></tr>'; }).join('') + '</tbody></table></div><span class="mut sm">The complete record of what the gateway told us about this payment.</span>' : '<div class="mut sm">Nothing has happened to this payment since it was recorded.</div>');
  }); };
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-fs]'); if (!b || b.disabled) return; e.preventDefault(); e.stopPropagation(); var f = A[b.getAttribute('data-fs')]; if (f) f(b); }, true);

  /* ---------- weight rate (settings) ---------- */
  var rb = $('fs-rate-btn');
  if (rb) rb.addEventListener('click', function () {
    dlg('Set weight rate', '<p class="mut sm">The price charged per ' + esc(C.unit) + ' of chargeable weight. This is the system default for pricing new shipments; existing bills keep their own captured rate.</p><label class="sl-field"><span class="sl-lab">Rate (' + esc(C.symbol) + ' per ' + esc(C.unit) + ')</span><input class="sl-in" id="fs-wr-in" inputmode="decimal" value="' + esc(C.weightRate) + '" placeholder="0.00"></label>', 'Save rate', function () {
      var v = $('fs-wr-in').value.replace(/,/g, '').trim(); if (v === '' || isNaN(parseFloat(v)) || parseFloat(v) <= 0) { dlgErr('Enter a rate greater than 0.'); return false; }
      return post({ action: 'set_weight_rate', value: v }).then(function (r) { if (!ok(r)) { dlgErr(r.message || 'Could not update the rate.'); return false; } C.weightRate = String(r.value); $('fs-wr').textContent = C.symbol + r.value + '/' + C.unit; SL.toast('Weight rate updated'); });
    });
  });

  loadConsols();
  if (C.cid) openConsol(C.cid, C.sid);
})();
