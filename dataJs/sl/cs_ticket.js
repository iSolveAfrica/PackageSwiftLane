/* Support ticket: field changes, reply / note composer, saved replies, AI previews, polling. */
(function () {
  'use strict';
  var root = document.getElementById('tk');
  if (!root) return;
  var id = root.getAttribute('data-id'), version = parseInt(root.getAttribute('data-version'), 10), lastId = parseInt(root.getAttribute('data-last'), 10) || 0;
  var body = document.getElementById('tk-body'), files = [];
  function reload() { setTimeout(function () { location.reload(); }, 300); }
  function update(fields, quiet) {
    var data = Object.assign({ id: id, version: version }, fields);
    return CS.post(CS.desk, 'update', data).then(function (j) {
      if (j.need_resolution) { document.getElementById('tk-resolve').hidden = false; return j; }
      if (!quiet || !j.success) SL.toast(j.success ? 'Saved' : j.message, !j.success);
      if (j.success) { version = j.ticket ? j.ticket.version : version + 1; reload(); }
      else if (j.conflict) reload();
      return j;
    });
  }
  /* field selects and quick buttons */
  document.addEventListener('change', function (e) {
    var s = e.target.closest('select[data-tk-field]');
    if (!s) return;
    var f = {}; f[s.getAttribute('data-tk-field')] = s.value;
    s.closest('.tk-sel') && s.closest('.tk-sel').classList.add('is-saving');
    update(f);
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-tk-field]');
    if (!b) return;
    var c = b.getAttribute('data-sl-confirm'); if (c && !confirm(c)) return;
    var f = {}; f[b.getAttribute('data-tk-field')] = b.getAttribute('data-value');
    if (b.getAttribute('data-value') === 'open') f.quick = 1;
    update(f);
  });
  /* resolve dialog */
  var rf = document.getElementById('tk-resolve-form');
  if (rf) rf.addEventListener('submit', function (e) {
    e.preventDefault();
    update({ status: 'resolved', resolution_code: rf.resolution_code.value, resolution_summary: rf.resolution_summary.value });
  });
  /* composer */
  function send(mode, force) {
    var text = body.value.trim();
    if (!text) { SL.toast('Write something first', true); body.focus(); return; }
    var data = { id: id, mode: mode, body: text, last_seen_id: lastId, client_msg_id: 'w' + Date.now(), set_status: mode === 'reply' ? document.getElementById('tk-after').value : '', ai_assisted: body.getAttribute('data-ai') === '1' ? 1 : 0 };
    if (force) data.force = 1;
    files.forEach(function (t, i) { data['files[' + i + ']'] = t; });
    CS.post(CS.desk, 'send', data).then(function (j) {
      if (!j.success && j.collision) { if (confirm(j.message + '\n\nSend anyway?')) send(mode, true); return; }
      SL.toast(j.success ? (mode === 'note' ? 'Note added' : 'Reply sent') : j.message, !j.success);
      if (j.success) reload();
    });
  }
  document.getElementById('tk-send').addEventListener('click', function () { send('reply'); });
  document.getElementById('tk-note').addEventListener('click', function () { send('note'); });
  body.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); send('reply'); } });
  var typingT = 0;
  body.addEventListener('input', function () {
    var now = Date.now(); if (now - typingT > 5000) { typingT = now; CS.post(CS.desk, 'typing', { id: id }).catch(function () {}); }
    var m = body.value.match(/(^|\s)\/([a-z0-9_-]+)$/i);
    if (m && window.CS_CANNED) { var c = window.CS_CANNED.filter(function (x) { return x.shortcut && x.shortcut.toLowerCase() === m[2].toLowerCase(); })[0]; if (c) { body.value = body.value.replace(/\/[a-z0-9_-]+$/i, c.body); } }
  });
  function insertCanned(cid) {
    var c = (window.CS_CANNED || []).filter(function (x) { return String(x.id) === String(cid); })[0];
    if (!c) return;
    body.value = (body.value ? body.value + '\n\n' : '') + c.body; body.focus();
  }
  document.getElementById('tk-canned').addEventListener('change', function () { insertCanned(this.value); this.value = ''; });
  document.addEventListener('click', function (e) {
    var p = e.target.closest('[data-tk-canned]'); if (p) insertCanned(p.getAttribute('data-tk-canned'));
    var ins = e.target.closest('[data-tk-insert]'); if (ins) { body.value += ins.getAttribute('data-tk-insert'); body.focus(); }
  });
  /* attachments */
  var fi = document.getElementById('tk-file');
  if (fi) fi.addEventListener('change', function () {
    if (!fi.files[0]) return;
    var fd = new FormData(); fd.append('action', 'upload'); fd.append('file', fi.files[0]);
    SL.post(CS.desk, fd).then(function (j) {
      if (!j.ok) { SL.toast(j.error || 'Upload failed', true); return; }
      files.push(j.token);
      var chip = document.createElement('span'); chip.className = 'tk-file'; chip.textContent = j.name || fi.files[0].name; document.getElementById('tk-pending').appendChild(chip);
      fi.value = '';
    });
  });
  /* AI: draft, refine, summary (previews only; the agent sends) */
  var prev = document.getElementById('tk-preview'), prevText = document.getElementById('tk-preview-text');
  function showPreview(text) { prevText.textContent = text; prev.style.display = 'flex'; }
  document.getElementById('tk-preview-use').addEventListener('click', function () { body.value = prevText.textContent; body.setAttribute('data-ai', '1'); prev.style.display = 'none'; body.focus(); });
  document.getElementById('tk-preview-x').addEventListener('click', function () { prev.style.display = 'none'; });
  var draft = document.getElementById('tk-ai-draft');
  if (draft) draft.addEventListener('click', function () {
    draft.classList.add('loading');
    CS.post(CS.desk, 'ai_draft', { id: id }).then(function (j) { if (j.success) showPreview(j.draft || j.text || ''); else SL.toast(j.message, true); }).finally(function () { draft.classList.remove('loading'); });
  });
  var pop = document.getElementById('tk-aipop'), tg = document.getElementById('tk-ai-toggle');
  if (tg && pop) tg.addEventListener('click', function () { if (pop.getAttribute('data-ai') !== '1') { SL.toast('AI tools need a provider key: see the AI centre', true); return; } pop.style.display = pop.style.display === 'none' ? 'flex' : 'none'; });
  document.addEventListener('click', function (e) {
    var r = e.target.closest('[data-tk-refine]'); if (!r) return;
    if (!body.value.trim()) { SL.toast('Write a reply first, then improve it', true); return; }
    r.classList.add('busy');
    var mode = r.getAttribute('data-tk-refine'), lang = (document.querySelector('select[data-tk-field="language"]') || {}).value || 'en';
    CS.post(CS.desk, 'ai_refine', { id: id, text: body.value, mode: mode, lang: lang }).then(function (j) { if (j.success) showPreview(j.text || j.draft || ''); else SL.toast(j.message, true); }).finally(function () { r.classList.remove('busy'); });
  });
  var sum = document.getElementById('tk-ai-summary');
  if (sum) sum.addEventListener('click', function () {
    sum.classList.add('loading');
    CS.post(CS.desk, 'ai_summary', { id: id, save: 1 }).then(function (j) { if (j.success) document.getElementById('tk-summary').textContent = j.summary || j.text || ''; else SL.toast(j.message, true); }).finally(function () { sum.classList.remove('loading'); });
  });
  /* keyboard shortcuts */
  document.addEventListener('keydown', function (e) {
    if (e.target.matches('input, textarea, select')) return;
    var prevA = document.querySelector('a[aria-label="Previous ticket"]'), nextA = document.querySelector('a[aria-label="Next ticket"]');
    if (e.key === 'j' && nextA && nextA.getAttribute('href') !== '#') location.href = nextA.getAttribute('href');
    if (e.key === 'k' && prevA && prevA.getAttribute('href') !== '#') location.href = prevA.getAttribute('href');
    if (e.key === 'r') { e.preventDefault(); body.focus(); }
    if (e.key === 'n') { e.preventDefault(); body.focus(); body.placeholder = 'Private note… click "Add private note" to save'; }
  });
  /* poll for new messages and viewers */
  var thread = document.getElementById('tk-thread');
  setInterval(function () {
    CS.get(CS.desk, { action: 'poll', id: id, after: lastId }).then(function (j) {
      if (!j.success) return;
      (j.messages || []).forEach(function (m) {
        if (m.id <= lastId) return;
        lastId = m.id;
        var el = document.createElement('div');
        if (m.author_type === 'system') { el.className = 'sysnote'; el.textContent = (m.body || '').replace(/\*\*/g, ''); }
        else { el.className = 'bub ' + (m.visibility === 'internal' ? 'note' : (m.author_type === 'customer' ? '' : 'me')); var s = document.createElement('small'); s.textContent = (m.author || m.author_type) + ' · just now'; el.appendChild(s); el.appendChild(document.createTextNode(m.body || '')); }
        thread.appendChild(el);
      });
      var v = document.getElementById('tk-viewers');
      if (v) { if (j.viewers && j.viewers.length) { v.style.display = 'flex'; v.lastElementChild.textContent = j.viewers.map(function (x) { return x.name; }).join(', ') + (j.viewers.length === 1 ? ' is' : ' are') + ' also viewing this ticket' + (j.viewers.some(function (x) { return x.typing; }) ? ' · typing' : ''); } else v.style.display = 'none'; }
      if (j.version && j.version !== version) version = j.version;
    }).catch(function () {});
  }, 10000);
})();
