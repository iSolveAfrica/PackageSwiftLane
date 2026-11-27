/* Developer console, package "infra": Infrastructure overrides dialog, Alerts detail drawer and the
   alert-rule dialog. Writes go through swiftlane.js (data-sl-form / data-sl-action, CSRF added there)
   to ajax/sl/dev_infra_ajax.php. Every value from the page is inserted with textContent / value. */
(function () {
  'use strict';
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function parse(el, attr) { try { return JSON.parse(el.getAttribute(attr) || '{}'); } catch (e) { return {}; } }
  function el(tag, cls, text) { var x = document.createElement(tag); if (cls) x.className = cls; if (text != null) x.textContent = text; return x; }

  /* ---------------- Infrastructure: override dialog ---------------- */
  var dlg = document.getElementById('dinf-ovr-dlg');
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-dinf-ovr]');
    if (!b || !dlg) return;
    e.preventDefault();
    var o = parse(b, 'data-dinf-ovr');
    var f = $('form', dlg);
    f.elements.key.value = o.key || '';
    f.elements.note.value = o.note || '';
    $('[data-dinf-t]', dlg).textContent = 'Override: ' + (o.label || '');
    $('[data-dinf-hint]', dlg).textContent = o.hint || '';
    $('[data-dinf-meas]', dlg).textContent = o.measured || 'not measured';
    $('[data-dinf-unit]', dlg).textContent = o.unit ? '(' + o.unit + ')' : '';
    var slot = $('[data-dinf-in]', dlg);
    slot.textContent = '';
    var inp;
    if (o.type === 'enum') {
      inp = el('select', 'sl-in');
      if (!o.value) { var ph = el('option', null, 'Choose…'); ph.value = ''; ph.disabled = true; ph.selected = true; inp.appendChild(ph); }
      (o.opts || []).forEach(function (v) { var op = el('option', null, v); op.value = v; inp.appendChild(op); });
    } else {
      inp = el('input', 'sl-in' + (o.type === 'num' ? ' mono' : ''));
      inp.type = o.type === 'date' ? 'date' : 'text';
      if (o.type === 'num') { inp.inputMode = 'decimal'; inp.placeholder = 'e.g. 50'; }
      if (o.type === 'text') { inp.maxLength = 120; }
    }
    inp.name = 'value'; inp.required = true; inp.value = o.value || '';
    inp.setAttribute('aria-label', 'Override value for ' + (o.label || ''));
    slot.appendChild(inp);
    dlg.hidden = false;
    setTimeout(function () { inp.focus(); }, 30);
  });

  /* ---------------- Alerts: detail drawer ---------------- */
  var scrim = null, drawer = null, lastFocus = null;
  function closeDrawer() {
    if (!drawer) return;
    drawer.remove(); scrim.remove(); drawer = scrim = null;
    document.body.style.overflow = '';
    var u = new URL(location.href); u.searchParams.delete('alert'); history.replaceState(null, '', u);
    $$('.dal-row.on').forEach(function (r) { r.classList.remove('on'); });
    if (lastFocus && lastFocus.isConnected) lastFocus.focus();
  }
  function btn(label, op, id, cls, confirmMsg) {
    var b = el('button', 'dvx-btn ' + (cls || ''), label);
    b.type = 'button';
    b.setAttribute('data-sl-action', page.getAttribute('data-dinf-ajax'));
    b.setAttribute('data-p-op', op); b.setAttribute('data-p-id', String(id));
    if (confirmMsg) b.setAttribute('data-sl-confirm', confirmMsg);
    return b;
  }
  var page = $('[data-dinf-page="alerts"]');
  function openDrawer(row) {
    var a = parse(row, 'data-dinf-alert');
    closeDrawer();
    lastFocus = document.activeElement;
    row.classList.add('on');
    scrim = el('div', 'dvx-scrim'); scrim.addEventListener('click', closeDrawer);
    drawer = el('aside', 'dvx-drawer dal-drawer');
    drawer.setAttribute('role', 'dialog'); drawer.setAttribute('aria-modal', 'true'); drawer.setAttribute('aria-label', 'Alert ' + a.t);
    var head = el('div', 'dvx-drawer-h');
    var sev = el('span', 'dvx-sev ' + a.sevCls, a.sev);
    var tt = el('div', 'col dvx-min grow'); tt.appendChild(el('b', 'dal-dt', a.t)); tt.appendChild(el('small', 'dvx-mut', a.svc + ' · #' + a.id));
    var x = el('button', 'dvx-ib'); x.type = 'button'; x.setAttribute('aria-label', 'Close'); x.appendChild(el('i', 'ic ic-sm i-x')); x.addEventListener('click', closeDrawer);
    head.appendChild(sev); head.appendChild(tt); head.appendChild(x);
    var body = el('div', 'dvx-drawer-b');
    var st = el('div', 'row g8 wrap');
    st.appendChild(el('span', 'dvx-pill ' + a.stCls, a.stLabel));
    st.appendChild(el('span', 'dvx-pill', 'lasted ' + a.dur));
    body.appendChild(st);
    if (a.detail) body.appendChild(el('p', 'dvx-p', a.detail));
    var dl = el('dl', 'dvx-dl');
    [['Source', a.source], ['Value', a.value], ['Limit', a.limit], ['Window', a.window], ['Triggered', a.first + ' (' + a.firstAgo + ')'], ['Last checked', a.last],
      ['Resolved', a.resolved], ['Owner', a.ack], ['Notification', a.mail || 'No e-mail sent for this alert']].forEach(function (p) {
      if (!p[1]) return; dl.appendChild(el('dt', null, p[0])); dl.appendChild(el('dd', 'mono', p[1]));
    });
    body.appendChild(dl);
    var tl = el('ol', 'dal-tl');
    function step(t, s, cls) { var li = el('li', cls || ''); li.appendChild(el('b', null, t)); li.appendChild(el('small', 'mono', s)); tl.appendChild(li); }
    step('Triggered', a.first, 'bad');
    if (a.mail) step('Developers e-mailed', a.mail.split(' · ').slice(1).join(' · '), 'info');
    if (a.ack) step('Acknowledged', a.ack.replace('Acknowledged by ', ''), 'info');
    if (a.resolved) step('Resolved', a.resolved, 'ok'); else step('Still open', 'last checked ' + a.last, 'warn');
    body.appendChild(el('h3', 'dvx-h3', 'Timeline')); body.appendChild(tl);
    var acts = el('div', 'row g8 wrap');
    if (a.st === 'active') acts.appendChild(btn('Acknowledge', 'alert_ack', a.id, 'primary'));
    if (a.st !== 'resolved') acts.appendChild(btn('Resolve', 'alert_resolve', a.id, '', 'Mark this alert resolved? If the condition is still true the next check opens a new one.'));
    if (a.st === 'resolved') acts.appendChild(btn('Reopen', 'alert_reopen', a.id, ''));
    var logs = el('a', 'dvx-btn ghost', 'Related logs'); logs.href = 'dev_logs.php?q=' + encodeURIComponent(a.svc); acts.appendChild(logs);
    body.appendChild(acts);
    drawer.appendChild(head); drawer.appendChild(body);
    var host = document.querySelector('.sl-app.dvx') || document.body;
    host.appendChild(scrim); host.appendChild(drawer);
    document.body.style.overflow = 'hidden';
    var u = new URL(location.href); u.searchParams.set('alert', a.id); history.replaceState(null, '', u);
    x.focus();
  }
  if (page) {
    document.addEventListener('click', function (e) {
      var r = e.target.closest('[data-dinf-alert]');
      if (!r || e.target.closest('button, a')) return;
      openDrawer(r);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer) { closeDrawer(); return; }
      var r = e.target.closest && e.target.closest('[data-dinf-alert]');
      if (r && (e.key === 'Enter' || e.key === ' ') && e.target === r) { e.preventDefault(); openDrawer(r); }
    });
    var sel = $('[data-dinf-alert].on');
    if (sel) openDrawer(sel);
  }

  /* severity filter: a select whose options are URLs */
  document.addEventListener('change', function (e) {
    if (e.target.matches('select[data-dinf-nav]') && e.target.value) location.href = e.target.value;
  });

  /* ---------------- Alerts: rule dialog (new / edit) ---------------- */
  var rd = document.getElementById('dal-rule-dlg');
  function syncMetric() {
    if (!rd) return;
    var f = $('form', rd), o = f.elements.metric.selectedOptions[0];
    if (!o) return;
    var win = o.getAttribute('data-win') === '1';
    $('[data-dal-win]', rd).hidden = !win;
    f.elements.window_min.disabled = !win;
    $('[data-dal-unit]', rd).textContent = o.getAttribute('data-unit') ? '(' + o.getAttribute('data-unit') + ')' : '';
    $('[data-dal-desc]', rd).textContent = o.getAttribute('data-desc') || '';
  }
  if (rd) {
    $('select[name="metric"]', rd).addEventListener('change', syncMetric);
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-dal-rule]');
      if (!b) return;
      e.preventDefault();
      var r = parse(b, 'data-dal-rule'), f = $('form', rd);
      f.reset();
      f.elements.id.value = r.id || '';
      if (r.id) {
        f.elements.label.value = r.label; f.elements.metric.value = r.metric; f.elements.comparator.value = r.comparator;
        f.elements.threshold.value = r.threshold; f.elements.window_min.value = r.window_min || 60;
        f.elements.severity.value = r.severity; f.elements.notify.value = r.notify === 'none' ? 'none' : 'email';
      }
      $('[data-dal-t]', rd).textContent = r.id ? 'Edit alert rule' : 'New alert rule';
      syncMetric();
      rd.hidden = false;
    });
  }
})();
