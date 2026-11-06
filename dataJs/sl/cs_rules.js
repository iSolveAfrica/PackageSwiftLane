/* Ticket rules: on/off, and the builder dialog (trigger, ALL/ANY conditions, actions, dry run). */
(function () {
  'use strict';
  var V = window.CS_RULE_VOCAB, dlg = document.getElementById('rule-dialog'), form = document.getElementById('rule-form');
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-rule-toggle]');
    if (!t) return;
    var on = t.classList.contains('on'); // swiftlane.js has already flipped it
    CS.post(CS.admin, 'rule_status', { id: t.getAttribute('data-rule-toggle'), status: on ? 'published' : 'paused' }).then(function (j) {
      SL.toast(j.success ? (on ? 'Rule published' : 'Rule paused') : j.message, !j.success);
      if (!j.success) t.classList.toggle('on');
    });
  });
  if (!V || !form) return;
  function opts(map, val) { return Object.keys(map).map(function (k) { var l = typeof map[k] === 'object' ? map[k].label : map[k]; return '<option value="' + k + '"' + (String(k) === String(val) ? ' selected' : '') + '>' + String(l).replace(/</g, '&lt;') + '</option>'; }).join(''); }
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
  function valueInput(kind, def, val) {
    if (kind === 'enum') return '<select class="sl-in" data-k="value">' + opts(def.options || {}, val) + '</select>';
    if (kind === 'team') return '<select class="sl-in" data-k="value">' + opts(V.teams, val) + '</select>';
    if (kind === 'agent') return '<select class="sl-in" data-k="value">' + opts(V.agents, val) + '</select>';
    if (kind === 'canned') { var m = {}; V.canned.forEach(function (c) { m[c.id] = c.title; }); return '<select class="sl-in" data-k="value">' + opts(m, val) + '</select>'; }
    if (kind === 'none') return '<input type="hidden" data-k="value" value="">';
    if (kind === 'textarea') return '<textarea class="sl-in" data-k="value" rows="2">' + esc(val) + '</textarea>';
    return '<input class="sl-in" data-k="value" value="' + esc(val) + '">';
  }
  function condRow(c) {
    c = c || { field: 'category', op: 'is', value: '' };
    var row = document.createElement('div'); row.className = 'row g6 rule-cond';
    function draw() {
      var f = V.fields[c.field] || {};
      row.innerHTML = '<select class="sl-in" data-k="field" style="width:200px">' + opts(V.fields, c.field) + '</select><select class="sl-in" data-k="op" style="width:170px">' + opts(V.ops, c.op) + '</select><span class="grow">' + valueInput(f.type === 'enum' || f.type === 'team' ? f.type : 'text', f, c.value) + '</span><button type="button" class="sl-ib sm" aria-label="Remove" data-rm><i class="ic ic-sm i-x" aria-hidden="true"></i></button>';
      row.querySelector('[data-k="field"]').addEventListener('change', function () { c.field = this.value; c.value = ''; draw(); });
    }
    draw(); return row;
  }
  function actRow(a) {
    a = a || { type: 'add_tag', value: '' };
    var row = document.createElement('div'); row.className = 'row g6 rule-act';
    function draw() {
      var m = V.actions[a.type] || {};
      row.innerHTML = '<select class="sl-in" data-k="type" style="width:260px">' + opts(V.actions, a.type) + '</select><span class="grow">' + valueInput(m.value, m, a.value) + '</span><button type="button" class="sl-ib sm" aria-label="Remove" data-rm><i class="ic ic-sm i-x" aria-hidden="true"></i></button>';
      row.querySelector('[data-k="type"]').addEventListener('change', function () { a.type = this.value; a.value = ''; draw(); });
    }
    draw(); return row;
  }
  function setMatch(m) { form.match_mode.value = m; form.querySelectorAll('[data-match]').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-match') === m); }); }
  function open(r) {
    r = r || { id: 0, version: 0, name: '', trigger_event: 'ticket.created', match_mode: 'all', conditions: [], actions: [{ type: 'add_tag', value: '' }], kind: 'automation', max_per_ticket: 1, sort_order: 100, stop_processing: false };
    form.id.value = r.id; form.version.value = r.version; form.name.value = r.name; form.trigger_event.value = r.trigger_event; form.kind.value = r.kind;
    form.max_per_ticket.value = r.max_per_ticket; form.sort_order.value = r.sort_order; setMatch(r.match_mode);
    var sw = form.querySelector('.sl-toggle'); sw.classList.toggle('on', !!r.stop_processing); sw.querySelector('input').value = r.stop_processing ? 1 : 0;
    document.getElementById('rule-title').textContent = r.id ? 'Edit rule · version ' + r.version : 'New rule';
    var cs = document.getElementById('rule-conds'), as = document.getElementById('rule-acts'); cs.innerHTML = ''; as.innerHTML = '';
    (r.conditions || []).forEach(function (c) { cs.appendChild(condRow(c)); });
    (r.actions || []).forEach(function (a) { as.appendChild(actRow(a)); });
    document.getElementById('rule-test-out').textContent = '';
    dlg.hidden = false; form.name.focus();
  }
  function collect(status) {
    var pick = function (row, k) { var el = row.querySelector('[data-k="' + k + '"]'); return el ? el.value : ''; };
    return { id: +form.id.value, version: +form.version.value, name: form.name.value, trigger_event: form.trigger_event.value, match_mode: form.match_mode.value, kind: form.kind.value,
      max_per_ticket: +form.max_per_ticket.value, sort_order: +form.sort_order.value, stop_processing: form.stop_processing.value === '1', status: status,
      conditions: Array.prototype.map.call(document.querySelectorAll('.rule-cond'), function (r) { return { field: pick(r, 'field'), op: pick(r, 'op'), value: pick(r, 'value') }; }),
      actions: Array.prototype.map.call(document.querySelectorAll('.rule-act'), function (r) { return { type: pick(r, 'type'), value: pick(r, 'value') }; }) };
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-rule-new]')) { e.preventDefault(); open(); }
    var ed = e.target.closest('[data-rule-edit]');
    if (ed) { e.preventDefault(); open(JSON.parse(ed.closest('[data-rule]').getAttribute('data-rule'))); }
    if (e.target.closest('[data-add-cond]')) document.getElementById('rule-conds').appendChild(condRow());
    if (e.target.closest('[data-add-act]')) document.getElementById('rule-acts').appendChild(actRow());
    var rm = e.target.closest('[data-rm]'); if (rm) rm.parentNode.remove();
    var m = e.target.closest('[data-match]'); if (m) setMatch(m.getAttribute('data-match'));
  });
  var status = 'published';
  form.querySelectorAll('[type="submit"]').forEach(function (b) { b.addEventListener('click', function () { status = b.getAttribute('data-status'); }); });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    CS.post(CS.admin, 'rule_save', { rule: JSON.stringify(collect(status)) }).then(function (j) {
      SL.toast(j.success ? 'Rule saved' : j.message, !j.success);
      if (j.success) setTimeout(function () { location.reload(); }, 300);
    });
  });
  document.getElementById('rule-test').addEventListener('click', function () {
    var out = document.getElementById('rule-test-out');
    CS.post(CS.admin, 'rule_simulate', { rule: JSON.stringify(collect('draft')), ref: document.getElementById('rule-test-ref').value }).then(function (j) {
      if (!j.success) { out.textContent = j.message; return; }
      var r = j.result || {};
      out.textContent = j.ticket.ref + ' · ' + j.ticket.subject + '\n' + (r.matched ? 'Matches. Would run:\n' + (r.steps || []).map(function (s) { return '• ' + (s.label || s.type || JSON.stringify(s)); }).join('\n') : 'Does not match' + (r.reason ? ': ' + r.reason : '.'));
    });
  });
})();
