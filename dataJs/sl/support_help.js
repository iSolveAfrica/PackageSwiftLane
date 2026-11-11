/* Help and support for customers (views/sl/pages/SupportHelp.php). Talks to ajax/support/customer_ajax.php
   with the same actions and fields as the previous dataJs/support_hub.js + support_chat.js, without jQuery. */
(function () {
  'use strict';
  var CFG = window.SL_HELP || {}, URL_ = CFG.url || 'ajax/support/customer_ajax.php';
  var king = CFG.king || { on: false, name: 'King', greeting: '' };
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var st = { conv: 0, last: 0, seen: {}, owner: king.on ? 'ai_active' : 'handoff_queued', ownerName: '', ticketRef: '', files: [], busy: false, timer: null, delay: 4000 };
  var boot = {}, openId = 0, newFiles = [], tkFiles = [], lastLinks = [];

  function safeUrl(u) { u = String(u || '').trim(); return /^[a-z0-9_\-]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/i.test(u) || /^https?:\/\/[^\s"'<>]+$/i.test(u) ? u : null; }
  function inline(s) {
    s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, l, u) { var x = safeUrl(u.replace(/&amp;/g, '&')); return x ? '<a href="' + esc(x) + '"' + (/^https?:/.test(x) ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' + l + '</a>' : l; });
    return s.replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>').replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>').replace(/`([^`]+)`/g, '<code>$1</code>');
  }
  function md(t) {
    var lines = esc(String(t == null ? '' : t).replace(/\r\n?/g, '\n')).split('\n'), out = [], para = [], list = [];
    function flush() { if (para.length) { out.push('<p>' + inline(para.join('<br>')) + '</p>'); para = []; } if (list.length) { out.push('<ul style="margin:4px 0 4px 18px">' + list.join('') + '</ul>'); list = []; } }
    lines.forEach(function (l) {
      if (/^\s*([-*+]|\d+[.)])\s+/.test(l)) { if (para.length) { out.push('<p>' + inline(para.join('<br>')) + '</p>'); para = []; } list.push('<li>' + inline(l.replace(/^\s*([-*+]|\d+[.)])\s+/, '')) + '</li>'); return; }
      if (/^\s*$/.test(l)) { flush(); return; }
      if (list.length) flush();
      para.push(l.replace(/^#{1,4}\s+/, '').trim());
    });
    flush();
    return out.join('');
  }
  function when(dt) {
    if (!dt) return '';
    var d = new Date(String(dt).replace(' ', 'T')); if (isNaN(d)) return dt;
    var now = new Date(), diff = (now - d) / 1000;
    if (diff >= 0 && diff < 60) return 'just now';
    if (diff >= 0 && diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (d.toDateString() === now.toDateString()) return d.toTimeString().slice(0, 5);
    return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' }) + (d.getFullYear() !== now.getFullYear() ? ' ' + d.getFullYear() : '');
  }
  function full(dt) { var d = new Date(String(dt || '').replace(' ', 'T')); return isNaN(d) ? (dt || '') : d.toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
  function uid() { return 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10); }
  function atts(list) {
    if (!list || !list.length) return '';
    return '<div class="row g6 wrap" style="margin-top:6px">' + list.map(function (a) {
      var u = 'ajax/support/file.php?t=' + encodeURIComponent(a.token);
      return /^image\//.test(a.mime) ? '<a href="' + u + '" target="_blank" rel="noopener"><img src="' + u + '" alt="' + esc(a.name) + '" style="max-height:120px;border-radius:8px"></a>'
        : '<a class="sl-pill plain" href="' + u + '&dl=1"><i class="ic ic-sm i-paperclip" aria-hidden="true"></i>' + esc(a.name) + '</a>';
    }).join('') + '</div>';
  }
  var PILL = { 'new': 'pending', open: 'transit', pending_customer: 'pending', pending_internal: 'sorting', on_hold: 'plain', resolved: 'delivered', closed: 'plain' };
  function pill(key, label) { return '<span class="sl-pill ' + (PILL[key] || 'plain') + '">' + esc(label) + '</span>'; }

  /* ---- requests (POST helpers resolve to the raw {ok, ...} reply) ---- */
  function post(data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { var v = data[k]; if (Array.isArray(v)) v.forEach(function (x) { fd.append(k + '[]', x); }); else fd.append(k, v == null ? '' : v); });
    return SL.post(URL_, fd);
  }
  function get(params) { return SL.get(URL_, params); }
  function fail(j) { SL.toast((j && (j.error || j.message)) || 'The server did not answer. Check your connection and try again.', true); }
  function upload(file, list, box, done) {
    if (file.size > 8 * 1024 * 1024) { SL.toast('Files can be up to 8 MB.', true); return; }
    box.insertAdjacentHTML('beforeend', '<span class="mut sm" data-up>Uploading ' + esc(file.name) + '…</span>');
    var fd = new FormData(); fd.append('action', 'upload'); fd.append('file', file);
    SL.post(URL_, fd).then(function (r) { if (r && r.ok) list.push(r.file); else fail(r); }).catch(fail).finally(done);
  }
  function drawFiles(list, box) {
    box.innerHTML = list.map(function (f, i) { return '<span class="sl-pill plain"><i class="ic ic-sm i-paperclip" aria-hidden="true"></i>' + esc(f.name) + ' <button type="button" class="sl-ib sm" data-rm="' + i + '" aria-label="Remove ' + esc(f.name) + '" style="width:18px;height:18px">×</button></span>'; }).join('');
  }

  /* ---- chat ---- */
  var thread = $('hp-thread'), scroll = $('hp-scroll'), text = $('hp-text');
  function header() {
    var s = st.owner, name = king.name || 'King', line;
    if (s === 'ai_active') line = 'AI assistant · ask for a person any time';
    else if (s === 'handoff_queued') { name = 'Support team'; line = 'Waiting for a person · we reply here'; }
    else if (s === 'human_active') { name = st.ownerName ? st.ownerName + ' · Support team' : 'Support team'; line = 'You are chatting with a person'; }
    else { name = 'Support'; line = 'Resolved · write again to reopen'; }
    $('hp-name').textContent = name; $('hp-state').textContent = line + (st.ticketRef ? ' · Ticket ' + st.ticketRef : '');
    $('hp-av').textContent = s === 'ai_active' ? name.charAt(0) : '';
    if (s !== 'ai_active') $('hp-av').innerHTML = '<i class="ic ic-sm i-user" aria-hidden="true"></i>';
    $('hp-human').hidden = s === 'handoff_queued' || s === 'human_active';
    $('hp-disclose').textContent = s === 'ai_active' ? name + ' is an AI assistant. It reads your own account only and can make mistakes; a person checks anything it cannot answer.' : 'Never share passwords, one-time codes or card numbers in chat.';
  }
  function state(r) { if (!r) return; if (r.owner) st.owner = r.owner; st.ownerName = r.owner_name || ''; st.ticketRef = r.ticket_ref || ''; header(); }
  function bubble(m) {
    var t = m.author_type, mine = t === 'customer';
    if (t === 'system') return '<div class="mut sm" style="text-align:center;padding:4px 12px" data-id="' + esc(m.id) + '">' + md(m.body) + '</div>';
    var cards = '';
    if (m.meta && m.meta.cards && m.meta.cards.length) {
      cards = m.meta.cards.map(function (c) { var l = safeUrl(c.link); return '<div class="sl-card col g4" style="padding:10px;margin-top:6px"><b class="mono sm">' + esc(c.tracking) + '</b><span class="sm">' + esc(c.status || 'Status not recorded') + (c.payment ? ' · ' + esc(c.payment) : '') + '</span>' + (c.updated ? '<span class="mut sm">Last update ' + esc(full(c.updated)) + '</span>' : '') + (l ? '<a class="sm fw5" href="' + esc(l) + '">Open</a>' : '') + '</div>'; }).join('');
    }
    var who = t === 'king' ? (m.author || 'King') + ' · AI' : (t === 'staff' ? m.author : '');
    var meta = (who ? esc(who) + ' · ' : '') + esc(m.created_at ? when(m.created_at) : '');
    return '<div class="bub' + (mine ? ' me' : '') + '" data-id="' + esc(m.id) + '">' + (meta ? '<small data-meta>' + meta + '</small>' : '<small data-meta></small>') + md(m.body) + cards + atts(m.attachments) + '</div>';
  }
  function add(m) {
    if (m.id && st.seen[m.id]) return;
    if (m.id) st.seen[m.id] = true;
    thread.insertAdjacentHTML('beforeend', bubble(m));
    if (typeof m.id === 'number' && m.id > st.last) st.last = m.id;
  }
  function addList(list) {
    (list || []).forEach(add);
    var last = list && list.length ? list[list.length - 1] : null, mine = !!thread.querySelector('.bub.me');
    $('hp-solved').hidden = !(st.owner === 'ai_active' && last && last.author_type === 'king' && mine);
    if (list && list.length) { $('hp-quick').hidden = mine; scroll.scrollTop = scroll.scrollHeight; }
  }
  function typing(on, label) { $('hp-typing').hidden = !on; $('hp-typing').textContent = label || ''; if (on) scroll.scrollTop = scroll.scrollHeight; }
  function welcome() {
    if (king.on) add({ id: 'w', author_type: 'king', author: king.name, body: king.greeting || ('Hi, I am ' + king.name + '. How can I help?'), created_at: '' });
    else add({ id: 'w', author_type: 'system', body: 'Tell us what you need and a member of our team will reply here.' + (CFG.hours ? '\n\nOur support hours: ' + CFG.hours : ''), created_at: '' });
  }
  function stop() { if (st.timer) { clearTimeout(st.timer); st.timer = null; } }
  function schedule(ms) { stop(); if (st.conv) st.timer = setTimeout(function () { poll(false); }, ms); }
  function poll(first) {
    if (!st.conv) return;
    get({ action: 'poll', conversation_id: st.conv, after: st.last }).then(function (r) {
      if (!r || !r.ok) return;
      state(r);
      if (first && !r.messages.length) welcome();
      addList(r.messages);
      if (!st.busy) typing(!!r.typing, r.typing ? 'typing…' : '');
      st.delay = document.hidden ? 20000 : (st.owner === 'resolved' ? 15000 : 4000);
      if (r.messages.length) refresh();
    }).catch(function () { st.delay = Math.min(60000, st.delay * 2); }).finally(function () { schedule(st.delay); });
  }
  function openConv(id) { stop(); st.conv = id; st.last = 0; st.seen = {}; thread.innerHTML = ''; poll(true); }
  function startNew() {
    stop(); st.conv = 0; st.last = 0; st.seen = {}; st.owner = king.on ? 'ai_active' : 'handoff_queued'; st.ownerName = ''; st.ticketRef = '';
    thread.innerHTML = ''; welcome(); header(); $('hp-quick').hidden = false; text.focus();
  }
  function ensureConv() {
    if (st.conv) return Promise.resolve(st.conv);
    var data = { action: 'start' };
    if (CFG.entity) { data.entity_type = CFG.entity.type; data.entity_id = CFG.entity.id; }
    return post(data).then(function (r) {
      if (!r || !r.ok) throw r;
      st.conv = r.conversation_id; thread.innerHTML = ''; st.seen = {}; state(r); addList(r.messages); refresh();
      return st.conv;
    });
  }
  function send(body) {
    body = String(body || '').trim();
    if ((!body && !st.files.length) || st.busy) return;
    var files = st.files.slice(); st.files = []; drawFiles(st.files, $('hp-files')); text.value = '';
    sendRaw(body, uid(), files);
  }
  function sendRaw(body, cid, files) {
    st.busy = true; $('hp-send').disabled = true;
    var tmp = 'tmp-' + cid;
    add({ id: tmp, author_type: 'customer', body: body, attachments: files, created_at: '' });
    var el = thread.querySelector('[data-id="' + tmp + '"]'); el.querySelector('[data-meta]').textContent = 'Sending…';
    scroll.scrollTop = scroll.scrollHeight;
    if (st.owner === 'ai_active') typing(true, (king.name || 'King') + ' is checking…');
    ensureConv().then(function () {
      return post({ action: 'send', conversation_id: st.conv, message: body, client_msg_id: cid, after: st.last, files: files.map(function (f) { return f.token; }) });
    }).then(function (r) {
      if (!r || !r.ok) throw r;
      el.remove(); delete st.seen[tmp]; state(r); addList(r.messages); refresh();
    }).catch(function (r) {
      el.style.outline = '1px solid var(--red-ink)';
      var m = el.querySelector('[data-meta]'); m.textContent = ((r && (r.error || r.message)) || 'Not sent') + ' · ';
      var b = document.createElement('button'); b.type = 'button'; b.className = 'sl-btn ghost sm'; b.textContent = 'Try again';
      b.addEventListener('click', function () { el.remove(); delete st.seen[tmp]; sendRaw(body, cid, files); }); m.appendChild(b);
    }).finally(function () { st.busy = false; typing(false); $('hp-send').disabled = false; schedule(st.delay); });
  }
  $('hp-form').addEventListener('submit', function (e) { e.preventDefault(); send(text.value); });
  text.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(text.value); } });
  text.addEventListener('input', function () { text.style.height = 'auto'; text.style.height = Math.min(160, text.scrollHeight) + 'px'; });
  $('hp-quick').addEventListener('click', function (e) { var b = e.target.closest('[data-hp-q]'); if (b) send(b.getAttribute('data-hp-q')); });
  $('hp-newchat').addEventListener('click', startNew);
  $('hp-human').addEventListener('click', function () {
    if (st.busy) return; st.busy = true;
    ensureConv().then(function () { return post({ action: 'human', conversation_id: st.conv, after: st.last }); })
      .then(function (r) { if (r && r.ok) { state(r); addList(r.messages); refresh(); } else fail(r); }).catch(fail)
      .finally(function () { st.busy = false; schedule(st.delay); });
  });
  $('hp-solved').addEventListener('click', function () {
    post({ action: 'solved', conversation_id: st.conv, after: st.last }).then(function (r) { if (r && r.ok) { state(r); addList(r.messages); $('hp-solved').hidden = true; refresh(); } else fail(r); }).catch(fail);
  });
  $('hp-file').addEventListener('change', function () { var f = this.files[0]; this.value = ''; if (f) upload(f, st.files, $('hp-files'), function () { drawFiles(st.files, $('hp-files')); }); });
  $('hp-files').addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (b) { st.files.splice(+b.getAttribute('data-rm'), 1); drawFiles(st.files, $('hp-files')); } });
  document.addEventListener('visibilitychange', function () { if (!document.hidden && st.conv) schedule(300); });

  /* ---- side panel ---- */
  function renderReqs(list) {
    list = list || [];
    var open = list.filter(function (t) { return ['resolved', 'closed'].indexOf(t.status_key) === -1; }).length;
    $('hp-req-n').textContent = open ? '(' + open + ' open)' : '';
    if (!list.length) { $('hp-reqs').innerHTML = '<div class="sl-empty mut sm"><span class="sl-tile"><i class="ic i-inbox" aria-hidden="true"></i></span>No requests yet. Chat with us, or use New request.</div>'; return; }
    $('hp-reqs').innerHTML = list.map(function (t) {
      return '<button type="button" class="sl-card col g4" data-ticket="' + t.id + '" style="text-align:left;padding:12px;cursor:pointer;color:inherit;font:inherit">'
        + '<span class="row between g8"><b class="mono sm">' + esc(t.ref) + '</b>' + pill(t.status_key, t.status) + '</span>'
        + '<span class="sm fw5">' + esc(t.subject) + '</span>'
        + '<span class="mut" style="font-size:14px">' + (t.waiting_on_you ? '<b>Waiting for your reply</b> · ' : '') + 'Updated ' + esc(when(t.updated_at)) + (t.can_rate ? ' · Please rate our help' : '') + '</span></button>';
    }).join('');
  }
  function renderChats(list) {
    list = list || [];
    if (!list.length) { $('hp-chats').innerHTML = '<div class="sl-empty mut sm">No chats yet.</div>'; return; }
    $('hp-chats').innerHTML = list.map(function (c) {
      return '<div class="row between g8" style="padding:8px 0;border-top:1px solid var(--line)"><span class="col"><button type="button" class="fw5 sm" data-conv="' + c.id + '" style="background:none;border:0;padding:0;text-align:left;cursor:pointer;color:inherit;font:inherit">' + esc(c.subject) + '</button>'
        + '<span class="mut" style="font-size:14px">' + esc(when(c.updated_at)) + (c.ticket_ref ? ' · ' + esc(c.ticket_ref) : '') + '</span></span>' + (c.unread ? '<span class="sl-pill transit">' + c.unread + ' new</span>' : '') + '</div>';
    }).join('');
  }
  function refresh(after) {
    return get({ action: 'bootstrap' }).then(function (r) {
      if (!r || !r.ok) { $('hp-reqs').innerHTML = '<div class="sl-empty mut sm">' + esc(r && r.error ? r.error : 'Support is not available right now.') + '</div>'; return; }
      boot = r; renderReqs(r.tickets); renderChats(r.conversations);
      var cat = $('hp-cat');
      if (!cat.options.length) {
        cat.innerHTML = Object.keys(r.categories || {}).map(function (k) { return '<option value="' + esc(k) + '">' + esc(r.categories[k]) + '</option>'; }).join('');
        $('hp-ent').insertAdjacentHTML('beforeend', (r.records || []).map(function (e) { return '<option value="' + esc(e.type + ':' + e.id) + '">' + esc(e.label + ' ' + e.ref + (e.status ? ' · ' + e.status : '')) + '</option>'; }).join(''));
      }
      if (typeof after === 'function') after();
    }).catch(function () {});
  }
  $('hp-reqs').addEventListener('click', function (e) { var b = e.target.closest('[data-ticket]'); if (b) openTicket(+b.getAttribute('data-ticket')); });
  $('hp-chats').addEventListener('click', function (e) { var b = e.target.closest('[data-conv]'); if (b) openConv(+b.getAttribute('data-conv')); });

  var helpTimer;
  function help(q) {
    get({ action: 'help', q: q }).then(function (r) {
      if (!r || !r.ok) return;
      if (!r.articles.length) { $('hp-help').innerHTML = '<div class="sl-empty mut sm">' + (q ? 'Nothing matches “' + esc(q) + '”. Ask in the chat instead.' : 'No help articles have been published yet.') + '</div>'; return; }
      $('hp-help').innerHTML = r.articles.map(function (a) {
        return '<details class="sl-card" style="padding:12px"><summary class="fw5 sm" style="cursor:pointer">' + esc(a.title) + '</summary><div class="sm" style="margin-top:8px;line-height:1.55">' + md(a.body) + '</div>'
          + '<div class="mut row g6 wrap" style="font-size:14px;margin-top:6px" data-vote-box>Updated ' + esc(when(a.updated_at)) + ' · Was this helpful? <button type="button" class="sl-btn ghost sm" data-vote="1" data-id="' + a.id + '">Yes</button><button type="button" class="sl-btn ghost sm" data-vote="0" data-id="' + a.id + '">No</button></div></details>';
      }).join('');
    }).catch(function () {});
  }
  $('hp-help-tab').addEventListener('click', function () { if (!$('hp-help').children.length) help(''); });
  $('hp-help-q').addEventListener('input', function () { var q = this.value; clearTimeout(helpTimer); helpTimer = setTimeout(function () { help(q); }, 300); });
  $('hp-help').addEventListener('click', function (e) {
    var b = e.target.closest('[data-vote]'); if (!b) return;
    post({ action: 'kb_vote', id: b.getAttribute('data-id'), helpful: b.getAttribute('data-vote') }).then(function () { b.closest('[data-vote-box]').textContent = 'Thanks for the feedback.'; });
  });

  /* ---- new request ---- */
  $('hp-new-file').addEventListener('change', function () { var f = this.files[0]; this.value = ''; if (f) upload(f, newFiles, $('hp-new-files'), function () { drawFiles(newFiles, $('hp-new-files')); }); });
  $('hp-new-files').addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (b) { newFiles.splice(+b.getAttribute('data-rm'), 1); drawFiles(newFiles, $('hp-new-files')); } });
  $('hp-new-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var f = e.target, btn = $('hp-new-send'); btn.disabled = true;
    post({ action: 'ticket_create', subject: f.subject.value, description: f.description.value, category: f.category.value, entity: f.entity.value, files: newFiles.map(function (x) { return x.token; }) }).then(function (r) {
      if (!r || !r.ok) { fail(r); return; }
      $('hp-new').hidden = true; f.reset(); newFiles = []; $('hp-new-files').innerHTML = '';
      renderReqs(r.tickets); SL.toast('Request ' + r.ticket.ref + ' received. We will reply here and notify you.');
    }).catch(fail).finally(function () { btn.disabled = false; });
  });

  /* ---- one request ---- */
  function openTicket(id) {
    get({ action: 'ticket', id: id }).then(function (r) {
      if (!r || !r.ok) { fail(r); return; }
      openId = id; drawTicket(r); $('hp-tk').hidden = false;
    }).catch(fail);
  }
  function drawTicket(r) {
    var t = r.ticket; if (r.links) lastLinks = r.links;
    $('hp-tk-title').textContent = t.subject;
    var kv = [['Reference', '<span class="mono">' + esc(t.ref) + '</span>'], ['Status', pill(t.status_key, t.status)], ['Topic', esc(t.category)], ['Handled by', esc(t.team)], ['Opened', esc(full(t.created_at))], ['Last update', esc(full(t.updated_at))]];
    if (lastLinks.length) kv.push(['About', lastLinks.map(function (l) { return esc(l.label + ' ' + l.ref + ' (' + l.status + ')'); }).join('<br>')]);
    $('hp-tk-kv').innerHTML = kv.map(function (x) { return '<div><span>' + x[0] + '</span><span style="text-align:right">' + x[1] + '</span></div>'; }).join('');
    if (t.can_rate) {
      $('hp-tk-rate').innerHTML = '<div class="sl-banner info col g8" style="align-items:stretch"><b>How did we do?</b><div class="sl-seg boxed" role="group" aria-label="Rating">'
        + [1, 2, 3, 4, 5].map(function (n) { return '<button type="button" data-star="' + n + '" aria-pressed="false">' + n + ' ★</button>'; }).join('')
        + '</div><textarea class="sl-in" id="hp-rate-c" rows="2" placeholder="Anything we could do better? (optional)"></textarea><div><button type="button" class="sl-btn sm" id="hp-rate-send">Send rating</button></div></div>';
    } else $('hp-tk-rate').innerHTML = t.rating ? '<span class="mut sm">You rated this ' + t.rating + ' of 5. Thank you.</span>' : '';
    $('hp-tk-thread').innerHTML = (r.messages || []).map(function (m) {
      if (m.author_type === 'system') return '<div class="mut sm" style="text-align:center">' + md(m.body) + '</div>';
      var mine = m.author_type === 'customer';
      return '<div class="bub' + (mine ? ' me' : '') + '"><small>' + esc((mine ? 'You' : m.author + (m.author_type === 'king' ? ' · AI' : '')) + ' · ' + when(m.created_at)) + '</small>' + md(m.body) + atts(m.attachments) + '</div>';
    }).join('');
    $('hp-tk-reply').hidden = t.status_key === 'closed';
    setTimeout(function () { var s = $('hp-tk-scroll'); s.scrollTop = s.scrollHeight; }, 30);
  }
  $('hp-tk-rate').addEventListener('click', function (e) {
    var s = e.target.closest('[data-star]');
    if (s) { var n = +s.getAttribute('data-star'); this.setAttribute('data-score', n); this.querySelectorAll('[data-star]').forEach(function (b) { var on = +b.getAttribute('data-star') <= n; b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); }); return; }
    if (e.target.closest('#hp-rate-send')) {
      var score = +this.getAttribute('data-score'); if (!score) { SL.toast('Choose 1 to 5 stars.', true); return; }
      var box = this;
      post({ action: 'rate', id: openId, score: score, comment: ($('hp-rate-c') || {}).value || '' }).then(function (r) {
        if (!r || !r.ok) { fail(r); return; }
        box.innerHTML = '<span class="sm">Thank you for rating us.</span>'; renderReqs(r.tickets);
      }).catch(fail);
    }
  });
  $('hp-tk-file').addEventListener('change', function () { var f = this.files[0]; this.value = ''; if (f) upload(f, tkFiles, $('hp-tk-files'), function () { drawFiles(tkFiles, $('hp-tk-files')); }); });
  $('hp-tk-files').addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (b) { tkFiles.splice(+b.getAttribute('data-rm'), 1); drawFiles(tkFiles, $('hp-tk-files')); } });
  $('hp-tk-reply').addEventListener('submit', function (e) {
    e.preventDefault();
    var body = $('hp-tk-text').value.trim(); if (!body && !tkFiles.length) return;
    post({ action: 'ticket_reply', id: openId, message: body, client_msg_id: uid(), files: tkFiles.map(function (f) { return f.token; }) }).then(function (r) {
      if (!r || !r.ok) { fail(r); return; }
      $('hp-tk-text').value = ''; tkFiles = []; $('hp-tk-files').innerHTML = ''; drawTicket(r); refresh();
    }).catch(fail);
  });

  /* ---- start ---- */
  header();
  refresh(function () {
    if (CFG.openTicket) { openTicket(CFG.openTicket); }
    var live = (boot.conversations || []).filter(function (c) { return c.owner !== 'resolved'; })[0];
    if (live && !CFG.entity) openConv(live.id); else startNew();
  });
})();
