/* WhatsApp screen (whatsapp_web.php, views/sl/pages/WhatsAppWeb.php).
 * Connection (QR) screen until the phone is linked, then three panes: chats,
 * conversation, details. Live by polling ajax/comms/wa_web_ajax.php (every 4 s
 * while the page is visible, 15 s in the background); the server only sends
 * the list or the messages when they changed. Every value is escaped. */
(function () {
  'use strict';
  var root = document.getElementById('ww');
  if (!root || !window.SL) return;
  var CFG = {};
  try { CFG = JSON.parse(root.getAttribute('data-cfg') || '{}'); } catch (e) { CFG = {}; }
  var AJAX = CFG.ajax || 'ajax/comms/wa_web_ajax.php';
  var KING = CFG.king || 'King';
  var $ = function (id) { return document.getElementById(id); };
  var conn = $('ww_conn'), app = $('ww_app'), list = $('ww_list'), main = $('ww_main'), panel = $('ww_panel'), note = $('ww_note');
  var S = { state: '', filter: 'all', q: '', chat: null, chatId: '', lsig: '', msig: '', rows: [], counts: {}, messages: [], customer: null,
    file: null, timer: null, qrTimer: null, qrLeft: 0, busy: false, panelOpen: window.matchMedia('(min-width: 1200px)').matches, quick: null, me: null, caps: {} };

  /* ---------- helpers ---------- */
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, sm) { return '<i class="ic' + (sm === false ? '' : ' ic-sm') + ' i-' + n + '" aria-hidden="true"></i>'; }
  function get(action, params) {
    var p = Object.assign({ action: action }, params || {});
    return SL.get(AJAX, p).catch(function () { return { ok: false, error: 'Network error. Check your connection.' }; });
  }
  function post(action, data) {
    var body;
    if (data instanceof FormData) { body = data; body.append('action', action); } else { body = Object.assign({ action: action }, data || {}); }
    return SL.post(AJAX + '?action=' + encodeURIComponent(action), body).catch(function (e) { return { ok: false, error: e.message || 'Network error.' }; });
  }
  function fail(j, fallback) { SL.toast((j && (j.error || j.message)) || fallback || 'Something went wrong.', true); }
  function fmt(text) {
    var h = esc(text);
    h = h.replace(/```([\s\S]+?)```/g, '<code>$1</code>');
    h = h.replace(/(^|[\s(])\*(?!\s)([^*\n]+?)\*(?=$|[\s.,!?:;)])/g, '$1<b>$2</b>');
    h = h.replace(/(^|[\s(])_(?!\s)([^_\n]+?)_(?=$|[\s.,!?:;)])/g, '$1<i>$2</i>');
    h = h.replace(/(^|[\s(])~(?!\s)([^~\n]+?)~(?=$|[\s.,!?:;)])/g, '$1<s>$2</s>');
    h = h.replace(/(^|\s)(https?:\/\/[^\s<]+)/g, function (m, a, u) { return a + '<a href="' + u + '" target="_blank" rel="noopener noreferrer">' + u + '</a>'; });
    return h.replace(/\n/g, '<br>');
  }
  function plus(d) { d = String(d || '').replace(/\D+/g, ''); return d ? '+' + d : ''; }
  function initials(name) {
    var p = String(name || '').replace(/[^\p{L}\p{N} ]/gu, '').trim().split(/\s+/).filter(Boolean);
    return p.length ? (p[0][0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase() : '';
  }
  function hue(s) { var h = 0; s = String(s || ''); for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % 360; return h; }
  function avatar(row, cls) {
    var name = row.customer || row.name || '', st = 'style="--h:' + hue(row.id || name) + '"';
    var inner = row.group ? ic('users', false) : (initials(name) ? esc(initials(name)) : ic('user', false));
    return '<span class="ww-av ' + (cls || '') + (row.group ? ' grp' : '') + '" ' + st + ' aria-hidden="true">' + inner + '</span>';
  }
  function title(row) { return row.customer || row.name || plus(row.phone) || (row.group ? 'Group' : 'Unknown'); }
  var DAY = 86400000;
  function dayStart(d) { var x = new Date(d); x.setHours(0, 0, 0, 0); return x.getTime(); }
  function timeOf(ts) { return new Date(ts * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
  function shortWhen(ts) {
    if (!ts) return '';
    var d = ts * 1000, today = dayStart(Date.now());
    if (d >= today) return timeOf(ts);
    if (d >= today - DAY) return 'Yesterday';
    if (d >= today - 6 * DAY) return new Date(d).toLocaleDateString([], { weekday: 'long' });
    return new Date(d).toLocaleDateString([], { day: '2-digit', month: '2-digit', year: 'numeric' });
  }
  function dayLabel(ts) {
    var d = ts * 1000, today = dayStart(Date.now());
    if (d >= today) return 'Today';
    if (d >= today - DAY) return 'Yesterday';
    if (d >= today - 6 * DAY) return new Date(d).toLocaleDateString([], { weekday: 'long' });
    return new Date(d).toLocaleDateString([], { day: 'numeric', month: 'long', year: 'numeric' });
  }
  var TICK = {
    pending: '<svg viewBox="0 0 16 16" class="ww-tk pend" role="img" aria-label="Waiting to send"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 4.8V8l2.2 1.4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
    sent: '<svg viewBox="0 0 16 16" class="ww-tk" role="img" aria-label="Sent"><path d="m3 8.5 3 3 7-7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    delivered: '<svg viewBox="0 0 20 16" class="ww-tk dbl" role="img" aria-label="Delivered"><path d="m1.5 8.5 3 3 7-7M8 11.5l.5.5 7-7.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    read: '<svg viewBox="0 0 20 16" class="ww-tk dbl rd" role="img" aria-label="Read"><path d="m1.5 8.5 3 3 7-7M8 11.5l.5.5 7-7.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    failed: '<svg viewBox="0 0 16 16" class="ww-tk bad" role="img" aria-label="Not sent"><circle cx="8" cy="8" r="6.5" fill="currentColor"/><path d="M8 4.5v4.2M8 10.9v.1" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/></svg>'
  };
  TICK.played = TICK.read;
  function tick(a) { return TICK[a] || ''; }
  function isNarrow() { return window.matchMedia('(max-width: 899px)').matches; }

  /* ---------- connection screen ---------- */
  function showApp(on) {
    app.hidden = !on; conn.hidden = on;
    if (on) sizeApp();
  }
  function sizeApp() {
    if (app.hidden) return;
    var top = app.getBoundingClientRect().top + window.scrollY;
    if (isNarrow() && app.classList.contains('is-chat')) {
      // Phones: an open chat takes the whole screen under the sticky top bar.
      var bar = document.querySelector('.sl-top'), barH = bar && /sticky|fixed/.test(getComputedStyle(bar).position) ? bar.getBoundingClientRect().height : 0;
      window.scrollTo(0, Math.max(0, top - barH - 6));
      top = barH + 6;
    }
    app.style.setProperty('--ww-top', Math.max(0, Math.round(top)) + 'px');
    // A bar fixed to the bottom of the screen (the phone app's tab bar) must not cover the composer.
    var bottom = 0, el = document.elementFromPoint(window.innerWidth / 2, window.innerHeight - 3);
    for (var n = el; n && n !== document.body; n = n.parentElement) {
      if (root.contains(n)) break;
      if (getComputedStyle(n).position === 'fixed') { bottom = Math.max(0, window.innerHeight - n.getBoundingClientRect().top); break; }
    }
    app.style.setProperty('--ww-bottom', Math.round(bottom) + 'px');
  }
  window.addEventListener('resize', sizeApp);

  function connScreen(st) {
    clearTimeout(S.qrTimer);
    var admin = !!CFG.admin, h = '';
    var settingsLink = admin ? '<a class="sl-btn ghost" href="' + esc(CFG.urls.messaging) + '">Open Settings › Messaging</a>' : '';
    if (st.state === 'not_configured' || st.configured === false) {
      h = block('alert', 'WhatsApp is not set up yet', 'This screen uses the UltraMsg instance the app already sends WhatsApp messages with. Its instance address and token are empty.' +
        (admin ? ' Add them in Settings › General › Messaging, then come back to link the phone.' : ' Ask an administrator to set it up.'), settingsLink);
    } else if (st.state === 'unreachable' || st.state === 'auth' || st.state === 'unknown') {
      h = block('alert', st.state === 'auth' ? 'UltraMsg refused the instance token' : 'WhatsApp provider unreachable',
        (st.error ? esc(st.error) + ' ' : '') + (st.state === 'auth' ? 'Check the token in Settings › General › Messaging.' : 'Saved chats stay readable below once the provider answers again.'),
        '<button type="button" class="sl-btn" data-ww="retry">' + ic('refresh') + 'Try again</button>' + settingsLink +
        (st.cached || S.rows.length ? '<button type="button" class="sl-btn ghost" data-ww="offline">Read saved chats</button>' : ''));
    } else if (st.state === 'standby') {
      h = block('monitor', 'WhatsApp is open on another computer', 'WhatsApp Web runs in one place at a time. ' + (admin ? 'Use it here to bring the session back to this instance.' : 'Ask an administrator to bring it back here.'),
        admin ? '<button type="button" class="sl-btn" data-ww="takeover">Use here</button>' : '');
    } else if (!admin) {
      h = block('phone', 'The WhatsApp phone is not linked', 'An administrator links the company phone by scanning a QR code on this screen. Saved chats stay readable meanwhile.',
        (st.cached || S.rows.length ? '<button type="button" class="sl-btn ghost" data-ww="offline">Read saved chats</button>' : '') + '<button type="button" class="sl-btn ghost" data-ww="retry">' + ic('refresh') + 'Check again</button>');
    } else {
      h = '<div class="ww-link"><div class="col g12 ww-link-l"><h2 class="ww-h2">Link the company WhatsApp phone</h2>' +
        '<ol class="ww-steps"><li>Open <b>WhatsApp</b> on the company phone.</li><li>Tap <b>Menu</b> or <b>Settings</b>, then <b>Linked devices</b>.</li><li>Tap <b>Link a device</b>.</li><li>Point the phone at the QR code on this screen.</li></ol>' +
        '<p class="mut sm">' + (st.state === 'loading' ? 'The instance is starting. The code appears in a few seconds.' : 'The code changes every 20 seconds; this screen keeps it fresh and opens your chats as soon as the phone is linked.') + '</p>' +
        '<div class="row g8 wrap"><button type="button" class="sl-btn ghost sm" data-ww="restart">' + ic('refresh') + 'Restart the instance</button>' +
        '<a class="sl-btn ghost sm" href="' + esc(CFG.urls.messaging) + '">Messaging settings</a></div></div>' +
        '<div class="ww-qr" id="ww_qr" aria-live="polite"><div class="ww-qr-box"><span class="ww-spin" aria-hidden="true"></span></div><span class="mut sm" id="ww_qr_t">Getting the QR code…</span></div></div>';
    }
    conn.innerHTML = h;
    conn.hidden = false;
    app.hidden = true;
    if (admin && (st.state === 'qr' || st.state === 'disconnected' || st.state === 'loading')) loadQr();
  }
  function block(icon, head, text, actions) {
    return '<div class="ww-block"><span class="ww-pick-ic" aria-hidden="true">' + ic(icon, false) + '</span><h2 class="ww-h2">' + esc(head) + '</h2><p class="mut">' + text + '</p>' +
      (actions ? '<div class="row g8 wrap ww-center">' + actions + '</div>' : '') + '</div>';
  }
  function loadQr() {
    clearTimeout(S.qrTimer);
    get('qr').then(function (j) {
      var box = $('ww_qr');
      if (!box) return;
      if (j.connected) { boot(true); return; }
      if (j.image) box.querySelector('.ww-qr-box').innerHTML = '<img src="' + esc(j.image) + '" alt="QR code to link WhatsApp" width="264" height="264">';
      else if (j.text) box.querySelector('.ww-qr-box').innerHTML = '<div class="ww-qr-text"><b>QR text</b><code>' + esc(j.text) + '</code><span class="mut sm">The provider gave the code as text only. Open the UltraMsg dashboard to scan it, or restart the instance.</span></div>';
      else box.querySelector('.ww-qr-box').innerHTML = '<span class="mut sm">' + esc(j.error || 'No QR code yet.') + '</span>';
      S.qrLeft = 20; qrCountdown();
    });
  }
  function qrCountdown() {
    var t = $('ww_qr_t');
    if (!t) return;
    if (S.qrLeft <= 0) { t.textContent = 'Refreshing…'; loadQr(); return; }
    t.textContent = 'Refreshes in ' + S.qrLeft + ' s';
    if (S.qrLeft % 4 === 0) {
      get('state').then(function (st) { if (st.state === 'connected') boot(true); });
    }
    S.qrLeft--;
    S.qrTimer = setTimeout(qrCountdown, 1000);
  }

  /* ---------- boot / state ---------- */
  function boot(fresh) {
    get('state', fresh ? { fresh: '1' } : {}).then(function (st) {
      if (!st || st.ok === false && !st.state) { connScreen({ state: 'unreachable', error: st && st.error }); return; }
      applyState(st);
      if (st.state === 'connected') { showApp(true); loadChats(true); }
      else connScreen(st);
      schedule();
    });
  }
  function applyState(st) {
    S.state = st.state; S.caps = st.caps || {};
    if (st.me) S.me = st.me;
    var dot = $('ww_dot');
    if (dot) dot.className = 'ww-dot ' + (st.state === 'connected' ? 'ok' : 'bad');
    if (S.me) {
      $('ww_me_name').textContent = S.me.name || 'WhatsApp';
      $('ww_me_sub').textContent = plus(S.me.phone) + (st.state === 'connected' ? '' : ' · offline');
      var av = $('ww_me_av');
      if (S.me.picture) av.innerHTML = '<img src="' + esc(S.me.picture) + '" alt="">';
    } else {
      $('ww_me_sub').textContent = st.state === 'connected' ? 'Connected' : 'Offline';
    }
    if (st.state !== 'connected' && !app.hidden) {
      showNote('WhatsApp is not connected (' + esc(st.state) + '). You are reading saved chats; replies cannot be sent until it is back.' +
        (CFG.admin ? ' <button type="button" class="ww-lnk" data-ww="connection">Connection</button>' : ''));
    } else if (st.state === 'connected') {
      showNote(CFG.active ? '' : 'WhatsApp sending is switched off in Settings › General › Messaging: you can read chats, but replies are not sent.');
    }
  }
  function showNote(html) { note.innerHTML = html; note.hidden = !html; sizeApp(); }

  function schedule() {
    clearTimeout(S.timer);
    var every = document.hidden ? 15000 : 4000;
    S.timer = setTimeout(poll, every);
  }
  function poll() {
    if (S.busy) { schedule(); return; }
    if (app.hidden) {
      // Connection screen: the QR flow checks by itself; otherwise look again every 15 s and redraw only on a change.
      if ($('ww_qr')) { schedule(); return; }
      S.timer = setTimeout(function () {
        get('state').then(function (st) { if (st && st.state && st.state !== S.state) boot(false); else poll(); });
      }, 15000);
      return;
    }
    S.busy = true;
    var p = { filter: S.filter, q: S.q, lsig: S.lsig, visible: document.hidden ? '0' : '1' };
    if (S.chatId) { p.chat = S.chatId; p.msig = S.msig; }
    get('poll', p).then(function (j) {
      S.busy = false;
      if (!j || j.ok === false) { schedule(); return; }
      if (j.state && j.state !== S.state) {
        var was = S.state;
        S.state = j.state;
        get('state').then(applyState);
        if (j.state === 'connected' && was !== 'connected' && app.hidden) { boot(true); return; }
      }
      if (j.rows) { S.lsig = j.sig; S.rows = j.rows; S.counts = j.counts; drawList(); }
      if (j.messages && S.chatId && p.chat === S.chatId) { S.msig = j.msig; drawMessages(j.messages, false); }
      schedule();
    });
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden && !app.hidden) { clearTimeout(S.timer); poll(); } });

  /* ---------- chat list ---------- */
  function loadChats(sync, force) {
    var p = { filter: S.filter, q: S.q };
    if (sync) p.sync = '1';
    if (force) p.force = '1';
    return get('chats', p).then(function (j) {
      if (!j.ok) { list.innerHTML = '<p class="ww-none">' + esc(j.error || 'Chats could not be loaded.') + '</p>'; return; }
      S.rows = j.rows; S.counts = j.counts; S.lsig = j.sig;
      if (j.sync && !j.sync.ok && j.sync.code === 'unsupported') showNote('The provider does not list chats, so only chats that came in through live updates are shown.');
      else if (j.sync && !j.sync.ok && j.sync.error) SL.toast(j.sync.error, true);
      drawList();
      var want = new URLSearchParams(location.search).get('chat');
      if (want && !S.chatId) openChat(want);
    });
  }
  function drawList() {
    Object.keys(S.counts || {}).forEach(function (k) { var e = root.querySelector('[data-ww-count="' + k + '"]'); if (e) e.textContent = S.counts[k] ? S.counts[k] : ''; });
    if (!S.rows.length) {
      list.innerHTML = '<p class="ww-none">' + (S.q ? 'No chat matches “' + esc(S.q) + '”.' : S.filter === 'all' ? 'No chats yet. They appear here once the phone has conversations.' : 'Nothing here.') + '</p>';
      return;
    }
    var h = '';
    S.rows.forEach(function (r) {
      var on = r.id === S.chatId, sub = r.preview || (r.group ? 'Group' : '');
      var meta = [];
      if (r.locker) meta.push('<span class="ww-lk">' + esc(r.locker) + '</span>');
      if (r.bot) meta.push('<span class="ww-tag bot">' + esc(KING) + '</span>');
      if (r.status === 'closed') meta.push('<span class="ww-tag">Closed</span>');
      else if (r.assignee && r.assignee === CFG.me) meta.push('<span class="ww-tag me">Mine</span>');
      (r.tags || []).slice(0, 2).forEach(function (t) { meta.push('<span class="ww-tag">' + esc(t) + '</span>'); });
      h += '<button type="button" class="ww-row' + (on ? ' on' : '') + (r.unread ? ' unread' : '') + '" data-chat="' + esc(r.id) + '" aria-current="' + (on ? 'true' : 'false') + '">' +
        avatar(r) + '<span class="ww-rb"><span class="ww-r1"><b>' + esc(title(r)) + '</b><time>' + esc(shortWhen(r.ts)) + '</time></span>' +
        '<span class="ww-r2">' + (r.from_me && r.ack ? tick(r.ack) : '') + '<span class="ww-last">' + esc(sub) + '</span>' +
        (r.muted ? '<span class="ww-mute" title="Muted">' + ic('bell') + '</span>' : '') + (r.pinned ? '<span class="ww-pin" title="Pinned">' + ic('pin') + '</span>' : '') +
        (r.unread ? '<span class="ww-badge" aria-label="' + r.unread + ' unread">' + (r.unread > 99 ? '99+' : r.unread) + '</span>' : '') + '</span>' +
        (meta.length ? '<span class="ww-r3">' + meta.join('') + '</span>' : '') + '</span></button>';
    });
    list.innerHTML = h;
  }
  root.addEventListener('click', function (e) {
    var f = e.target.closest('[data-ww-filter]');
    if (f) {
      S.filter = f.getAttribute('data-ww-filter'); S.lsig = '';
      root.querySelectorAll('[data-ww-filter]').forEach(function (b) { b.setAttribute('aria-pressed', String(b === f)); });
      loadChats(false);
      return;
    }
    var r = e.target.closest('.ww-row[data-chat]');
    if (r) openChat(r.getAttribute('data-chat'));
  });
  var qTimer;
  $('ww_q').addEventListener('input', function () {
    clearTimeout(qTimer);
    var v = this.value.trim();
    qTimer = setTimeout(function () { S.q = v; S.lsig = ''; loadChats(false); }, 250);
  });

  /* ---------- conversation ---------- */
  function openChat(id, force) {
    if (!id) return;
    var changed = id !== S.chatId;
    S.chatId = id; S.msig = '';
    if (changed) {
      S.file = null; S.chat = null; S.customer = null;
      var row = S.rows.find(function (x) { return x.id === id; }) || { id: id, name: '', group: /@g\.us$/.test(id) ? 1 : 0, phone: id.replace(/@.*/, '') };
      drawHeader(row);
      $('ww_msgs').innerHTML = '<div class="ww-load"><span class="ww-spin" aria-hidden="true"></span><span>Loading messages…</span></div>';
      app.classList.add('is-chat');
      sizeApp();
      drawList();
      try { history.replaceState(null, '', location.pathname + '?chat=' + encodeURIComponent(id)); } catch (er) {}
    }
    get('chat', { id: id, force: force ? '1' : '0' }).then(function (j) {
      if (S.chatId !== id) return;
      if (!j.ok) { $('ww_msgs').innerHTML = '<p class="ww-none">' + esc(j.error || 'This chat could not be opened.') + '</p>'; return; }
      S.chat = j.chat; S.customer = j.customer; S.botEnabled = j.bot_enabled;
      var r = S.rows.find(function (x) { return x.id === id; });
      if (r) { r.unread = 0; drawList(); }
      drawHeader(Object.assign({}, j.chat, { customer: j.customer ? j.customer.name : '', locker: j.customer ? j.customer.locker : '' }));
      S.msig = j.sig;
      drawMessages(j.messages, true);
      if (j.sync && !j.sync.ok && j.sync.error && j.sync.code !== 'offline') {
        $('ww_sync').innerHTML = esc(j.sync.code === 'unsupported' ? 'The provider does not return the history of this chat; messages from now on are kept here.' : 'Could not refresh from WhatsApp: ' + j.sync.error);
        $('ww_sync').hidden = false;
      }
      if (changed && S.panelOpen && !isNarrow()) togglePanel(true); else drawPanel();
      if (!isNarrow()) { var t = $('ww_text'); if (t && changed) t.focus(); }
    });
  }
  function drawHeader(row) {
    var sub = row.group ? ((row.participants ? row.participants + ' members' : 'Group') + (row.tags && row.tags.length ? ' · ' + row.tags.join(', ') : '')) :
      [plus(row.phone), row.locker ? 'Locker ' + row.locker : ''].filter(Boolean).join(' · ');
    main.innerHTML = '<header class="ww-ch"><button type="button" class="ww-ib ww-back" data-ww="back" aria-label="Back to chats">' + ic('left') + '</button>' +
      avatar(row) + '<button type="button" class="ww-ct" data-ww="panel" aria-label="Show details of ' + esc(title(row)) + '"><b>' + esc(title(row)) + '</b><span class="mut sm">' + esc(sub) + '</span></button>' +
      '<div class="ww-sa"><button type="button" class="ww-ib" data-ww="reload" aria-label="Refresh messages" title="Refresh messages">' + ic('refresh') + '</button>' +
      '<button type="button" class="ww-ib" data-ww="panel" aria-label="Details" title="Details" aria-expanded="' + (!panel.hidden) + '">' + ic('sidebar') + '</button></div></header>' +
      '<p class="ww-sync" id="ww_sync" hidden></p>' +
      '<div class="ww-msgs" id="ww_msgs" tabindex="0" aria-label="Messages" role="log" aria-live="polite"></div>' +
      '<button type="button" class="ww-down" id="ww_down" hidden aria-label="Scroll to the newest message">' + ic('down') + '</button>' +
      composer();
    bindComposer();
  }
  function composer() {
    var ai = CFG.ai && CFG.ai.ok;
    return '<div class="ww-king" id="ww_king" hidden></div><div class="ww-quick" id="ww_quick" hidden></div>' +
      '<div class="ww-att" id="ww_att" hidden></div>' +
      '<form class="ww-comp" id="ww_comp" autocomplete="off">' +
      '<input type="file" id="ww_file" hidden accept="image/jpeg,image/png,image/webp,image/gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt">' +
      '<button type="button" class="ww-ib" data-ww="attach" aria-label="Attach a picture or document" title="Attach a picture or document">' + ic('paperclip') + '</button>' +
      '<button type="button" class="ww-ib" data-ww="quick" aria-label="Quick replies" title="Quick replies" aria-expanded="false" aria-controls="ww_quick">' + ic('bolt') + '</button>' +
      '<button type="button" class="ww-ib ww-kb" data-ww="king" aria-label="' + esc(KING) + ': suggest a reply" title="' + esc(ai ? KING + ': suggest a reply' : (CFG.ai.message || KING + ' is not set up')) + '" aria-expanded="false" aria-controls="ww_king">' + ic('spark') + '</button>' +
      '<label class="ww-tw"><span class="ww-sr">Message</span><textarea id="ww_text" rows="1" maxlength="4000" placeholder="Message"></textarea></label>' +
      '<button type="submit" class="ww-send" aria-label="Send" title="Send (Enter)">' + ic('send') + '</button></form>';
  }
  function bindComposer() {
    var t = $('ww_text'), f = $('ww_comp'), file = $('ww_file'), box = $('ww_msgs');
    t.addEventListener('input', grow);
    t.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && !isNarrow()) { e.preventDefault(); f.requestSubmit(); }
    });
    f.addEventListener('submit', function (e) { e.preventDefault(); send(); });
    file.addEventListener('change', function () {
      var x = file.files && file.files[0];
      if (!x) return;
      if (x.size > 16 * 1048576) { SL.toast('WhatsApp accepts files up to 16 MB.', true); file.value = ''; return; }
      S.file = x; drawAttachment();
    });
    box.addEventListener('scroll', function () { $('ww_down').hidden = box.scrollHeight - box.scrollTop - box.clientHeight < 200; });
    $('ww_down').addEventListener('click', function () { box.scrollTop = box.scrollHeight; });
  }
  function grow() { var t = $('ww_text'); if (!t) return; t.style.height = 'auto'; t.style.height = Math.min(160, t.scrollHeight) + 'px'; }
  function drawAttachment() {
    var a = $('ww_att');
    if (!S.file) { a.hidden = true; a.innerHTML = ''; return; }
    var img = /^image\//.test(S.file.type);
    a.innerHTML = '<span class="ww-att-ic">' + (img ? '<img alt="" id="ww_att_img">' : ic('file', false)) + '</span><span class="ww-att-n"><b>' + esc(S.file.name) + '</b><span class="mut sm">' +
      (img ? 'Picture' : 'Document') + ' · ' + Math.max(1, Math.round(S.file.size / 1024)) + ' KB · the message becomes its caption</span></span>' +
      '<button type="button" class="ww-ib" data-ww="unattach" aria-label="Remove the attachment">' + ic('x') + '</button>';
    a.hidden = false;
    if (img) { var r = new FileReader(); r.onload = function () { var i = $('ww_att_img'); if (i) i.src = r.result; }; r.readAsDataURL(S.file); }
  }
  function send() {
    var t = $('ww_text'), btn = root.querySelector('.ww-send');
    var text = t.value.trim();
    if (!text && !S.file) { t.focus(); return; }
    if (S.state !== 'connected') { SL.toast('WhatsApp is not connected, so nothing can be sent right now.', true); return; }
    var fd = new FormData();
    fd.append('chat_id', S.chatId); fd.append('body', text);
    if (S.file) fd.append('file', S.file, S.file.name);
    btn.disabled = true; btn.classList.add('loading');
    var id = S.chatId;
    post('send', fd).then(function (j) {
      btn.disabled = false; btn.classList.remove('loading');
      if (j.messages && S.chatId === id) { S.msig = j.msig; drawMessages(j.messages, true); }
      if (!j.ok) { fail(j, 'Not sent.'); if (j.closed) loadChat(); return; }
      t.value = ''; grow(); S.file = null; drawAttachment(); $('ww_file').value = '';
      closeKing(); closeQuick();
      S.lsig = '';
    });
  }
  function loadChat() { if (S.chatId) openChat(S.chatId, false); }

  function media(m) {
    var cap = m.body ? '<div class="ww-tx">' + fmt(m.body) + '</div>' : '';
    if (m.type === 'image' || m.type === 'sticker') {
      var src = m.media || m.thumb;
      if (src) return '<a class="ww-img' + (m.type === 'sticker' ? ' stk' : '') + '" href="' + esc(m.media || m.thumb) + '" target="_blank" rel="noopener noreferrer"><img src="' + esc(src) + '" alt="' + esc(m.body || 'Picture') + '" loading="lazy"></a>' + cap;
      return '<div class="ww-file">' + ic('image', false) + '<span><b>Picture</b><span class="mut sm">Open the phone to see it</span></span></div>' + cap;
    }
    if (m.type === 'document') {
      var name = m.filename || 'Document';
      return (m.media ? '<a class="ww-file" href="' + esc(m.media) + '" target="_blank" rel="noopener noreferrer" download>' : '<div class="ww-file">') + ic('file', false) +
        '<span><b>' + esc(name) + '</b><span class="mut sm">' + esc((name.split('.').pop() || 'file').toUpperCase()) + (m.media ? ' · open' : '') + '</span></span>' + (m.media ? '</a>' : '</div>') +
        (m.body && m.body !== name ? cap : '');
    }
    if (m.type === 'audio' || m.type === 'video') {
      var lbl = m.type === 'audio' ? 'Voice message' : 'Video';
      if (m.media) return (m.type === 'audio' ? '<audio controls preload="none" src="' + esc(m.media) + '"></audio>' : '<video controls preload="none" src="' + esc(m.media) + '"' + (m.thumb ? ' poster="' + esc(m.thumb) + '"' : '') + '></video>') + cap;
      return '<div class="ww-file">' + ic(m.type === 'audio' ? 'play' : 'play', false) + '<span><b>' + lbl + '</b><span class="mut sm">Open the phone to play it</span></span></div>' + cap;
    }
    if (m.type === 'location') {
      var ll = String(m.body || '').match(/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/);
      return '<a class="ww-file" ' + (ll ? 'href="https://www.google.com/maps?q=' + esc(ll[1] + ',' + ll[2]) + '" target="_blank" rel="noopener noreferrer"' : '') + '>' + ic('pin', false) + '<span><b>Location</b><span class="mut sm">' + esc(m.body || '') + '</span></span></a>';
    }
    if (m.type === 'contact') return '<div class="ww-file">' + ic('user', false) + '<span><b>Contact card</b><span class="mut sm">' + esc(String(m.body || '').replace(/[\s\S]*FN:([^\n]*)[\s\S]*/, '$1').slice(0, 80)) + '</span></span></div>';
    if (m.type === 'other') return '<div class="ww-tx mut"><i>Message type not shown here</i></div>' + cap;
    return '<div class="ww-tx">' + fmt(m.body) + '</div>';
  }
  /* The time and ticks float at the end of the last line of text (like WhatsApp), or sit under a picture or file. */
  function withMeta(html, meta) {
    var i = html.lastIndexOf('<div class="ww-tx');
    if (i >= 0 && html.slice(-6) === '</div>' && html.indexOf('</div>', i) === html.length - 6) return html.slice(0, -6) + meta + '</div>';
    return html + '<div class="ww-mrow">' + meta + '</div>';
  }
  function drawMessages(msgs, scroll) {
    var box = $('ww_msgs');
    if (!box) return;
    var nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 120;
    S.messages = msgs;
    if (!msgs.length) {
      box.innerHTML = '<p class="ww-none">' + (S.state === 'connected' ? 'No messages yet. Write the first one below.' : 'No saved messages for this chat.') + '</p>';
      return;
    }
    var h = '', lastDay = null, prev = null, group = S.chat ? S.chat.group : /@g\.us$/.test(S.chatId);
    msgs.forEach(function (m) {
      var d = dayStart(m.ts * 1000);
      if (d !== lastDay) { h += '<div class="ww-day"><span>' + esc(dayLabel(m.ts)) + '</span></div>'; lastDay = d; prev = null; }
      var first = !prev || prev.me !== m.me || prev.author !== m.author || m.ts - prev.ts > 600;
      var who = group && !m.me && first ? '<span class="ww-au" style="--h:' + hue(m.author) + '">' + esc(m.author_name || plus(m.author)) + (m.author_name ? ' <span class="mut">' + esc(plus(m.author)) + '</span>' : '') + '</span>' : '';
      var by = m.me && m.by && first ? '<span class="ww-by">' + esc(m.by) + '</span>' : '';
      h += '<div class="ww-msg ' + (m.me ? 'out' : 'in') + (first ? ' first' : '') + (m.ack === 'failed' ? ' failed' : '') + '" data-id="' + m.id + '">' +
        '<div class="ww-bub">' + who + by + withMeta(media(m), '<span class="ww-meta"><time datetime="' + new Date(m.ts * 1000).toISOString() + '">' + esc(timeOf(m.ts)) + '</time>' + (m.me ? tick(m.ack || 'sent') : '') + '</span>') + '</div>' +
        (m.ack === 'failed' ? '<span class="ww-fail">Not sent</span>' : '') + '</div>';
      prev = m;
    });
    box.innerHTML = h;
    if (scroll || nearBottom) box.scrollTop = box.scrollHeight;
  }

  /* ---------- quick replies ---------- */
  function vars() {
    var c = S.customer || {}, sh = (c.shipments || [])[0];
    return { name: c.name || (S.chat && S.chat.name) || '', first_name: c.first_name || (c.name || '').split(' ')[0] || '', locker: c.locker || '',
      balance: c.balance != null ? 'GHS ' + Number(c.balance).toFixed(2) : '', tracking: sh ? sh.tracking : '' };
  }
  function fill(text) { var v = vars(); return String(text).replace(/\{(\w+)\}/g, function (m, k) { return v[k] ? v[k] : m; }); }
  function loadQuick() {
    if (S.quick) return Promise.resolve(S.quick);
    return get('quick_replies').then(function (j) { S.quick = j.ok ? j : { rows: [], can_edit: false }; return S.quick; });
  }
  function openQuick() {
    var q = $('ww_quick'), b = root.querySelector('[data-ww="quick"]');
    if (!q.hidden) { closeQuick(); return; }
    closeKing();
    loadQuick().then(function (d) {
      var h = '<div class="ww-pop-h"><b>Quick replies</b>' + (d.can_edit ? '<button type="button" class="ww-lnk" data-ww="qr-edit">Edit</button>' : '') + '</div>';
      if (!d.rows.length) h += '<p class="mut sm">No quick replies yet.' + (d.can_edit ? ' Add some with Edit.' : '') + '</p>';
      d.rows.forEach(function (r, i) { h += '<button type="button" class="ww-qi" data-ww-quick="' + i + '"><b>' + esc(r.title) + '</b><span>' + esc(fill(r.body)) + '</span></button>'; });
      q.innerHTML = h; q.hidden = false; b.setAttribute('aria-expanded', 'true');
      var first = q.querySelector('button'); if (first) first.focus();
    });
  }
  function closeQuick() { var q = $('ww_quick'), b = root.querySelector('[data-ww="quick"]'); if (q) q.hidden = true; if (b) b.setAttribute('aria-expanded', 'false'); }
  function insertText(text, replace) {
    var t = $('ww_text');
    if (!t) return;
    if (replace || !t.value.trim()) t.value = text;
    else { var s = t.selectionStart || t.value.length; t.value = t.value.slice(0, s) + (s && !/\s$/.test(t.value.slice(0, s)) ? ' ' : '') + text + t.value.slice(t.selectionEnd || s); }
    grow(); t.focus();
    if (/\{\w+\}/.test(t.value)) SL.toast('Fill in the parts still in braces before sending.', true);
  }
  function editQuick() {
    closeQuick();
    loadQuick().then(function (d) {
      var rows = $('ww_qr_rows');
      rows.innerHTML = '';
      (d.rows.length ? d.rows : [{ title: '', body: '' }]).forEach(addQuickRow);
      $('ww_dlg_qr').hidden = false;
    });
  }
  function addQuickRow(r) {
    var div = document.createElement('div');
    div.className = 'ww-qr-row';
    div.innerHTML = '<input class="sl-in" maxlength="60" placeholder="Title" aria-label="Title" value="' + esc(r.title) + '"><textarea class="sl-in ww-ta" rows="2" maxlength="1500" placeholder="Message" aria-label="Message">' + esc(r.body) + '</textarea>' +
      '<button type="button" class="ww-ib" data-ww="qr-del" aria-label="Delete this reply">' + ic('trash') + '</button>';
    $('ww_qr_rows').appendChild(div);
  }

  /* ---------- King ---------- */
  function openKing(tone) {
    var k = $('ww_king'), b = root.querySelector('[data-ww="king"]');
    closeQuick();
    if (!CFG.ai || !CFG.ai.ok) {
      k.innerHTML = '<div class="ww-pop-h"><b>' + esc(KING) + '</b><button type="button" class="ww-ib" data-ww="king-close" aria-label="Close">' + ic('x') + '</button></div><p class="mut sm">' +
        esc(CFG.ai && CFG.ai.message ? CFG.ai.message : KING + ' is not set up yet.') + '</p>' + (CFG.ai && CFG.ai.setup ? '<a class="sl-btn ghost sm" href="' + esc(CFG.ai.setup) + '">Open the AI centre</a>' : '');
      k.hidden = false; b.setAttribute('aria-expanded', 'true');
      return;
    }
    k.hidden = false; b.setAttribute('aria-expanded', 'true');
    k.innerHTML = '<div class="ww-pop-h"><b>' + ic('spark') + ' ' + esc(KING) + ' suggests</b><button type="button" class="ww-ib" data-ww="king-close" aria-label="Close">' + ic('x') + '</button></div>' +
      '<div class="ww-load"><span class="ww-spin" aria-hidden="true"></span><span>' + esc(KING) + ' is reading the chat…</span></div>';
    post('suggest', { chat_id: S.chatId, tone: tone || '', instruction: (S.kingInstr || '') }).then(function (j) {
      if (k.hidden) return;
      var tones = '<div class="ww-tones" role="group" aria-label="Tone">' + [['friendly', 'Friendlier'], ['short', 'Shorter'], ['formal', 'More formal']].map(function (t) {
        return '<button type="button" class="ww-chip" data-ww-tone="' + t[0] + '">' + t[1] + '</button>'; }).join('') + '</div>';
      if (!j.ok) {
        k.innerHTML = '<div class="ww-pop-h"><b>' + esc(KING) + '</b><button type="button" class="ww-ib" data-ww="king-close" aria-label="Close">' + ic('x') + '</button></div><p class="ww-err sm">' + esc(j.error || 'No suggestion.') + '</p>' +
          (j.setup_url && (j.code === 'not_configured' || j.code === 'not_allowed') ? '<a class="sl-btn ghost sm" href="' + esc(j.setup_url) + '">Open the AI centre</a>' : '<button type="button" class="sl-btn ghost sm" data-ww="king-again">Try again</button>');
        return;
      }
      k.innerHTML = '<div class="ww-pop-h"><b>' + ic('spark') + ' ' + esc(j.assistant || KING) + ' suggests</b><button type="button" class="ww-ib" data-ww="king-close" aria-label="Close">' + ic('x') + '</button></div>' +
        '<div class="ww-sug" id="ww_sug">' + fmt(j.text) + '</div>' + (j.warnings && j.warnings.length ? '<p class="mut sm">' + esc(j.warnings.join(' ')) + '</p>' : '') +
        '<div class="row g8 wrap"><button type="button" class="sl-btn sm" data-ww="king-use">Use this reply</button><button type="button" class="sl-btn ghost sm" data-ww="king-again">' + ic('refresh') + 'Another</button>' + tones + '</div>' +
        '<p class="mut xs">Check it before sending: ' + esc(KING) + ' only knows what is in this chat and the customer\'s account.</p>';
      k.setAttribute('data-text', j.text);
    });
  }
  function closeKing() { var k = $('ww_king'), b = root.querySelector('[data-ww="king"]'); if (k) { k.hidden = true; k.innerHTML = ''; } if (b) b.setAttribute('aria-expanded', 'false'); }

  /* ---------- details panel ---------- */
  function togglePanel(force) {
    var open = force != null ? force : panel.hidden;
    if (open && !S.chat) return;
    panel.hidden = !open;
    if (force == null || !isNarrow()) S.panelOpen = open;
    app.classList.toggle('has-panel', open);
    root.querySelectorAll('[data-ww="panel"][aria-expanded]').forEach(function (b) { b.setAttribute('aria-expanded', String(open)); });
    if (open) drawPanel();
  }
  function drawPanel() {
    if (panel.hidden || !S.chat) return;
    var c = S.chat, cu = S.customer, h = '<header class="ww-ph"><b>' + (c.group ? 'Group details' : 'Contact details') + '</b><button type="button" class="ww-ib" data-ww="panel-close" aria-label="Close details">' + ic('x') + '</button></header><div class="ww-pb">';
    h += '<section class="ww-pc ww-who">' + avatar({ id: c.id, name: c.name, customer: cu ? cu.name : '', group: c.group }, 'lg') +
      '<b>' + esc(cu ? cu.name : (c.name || plus(c.phone) || 'Group')) + '</b><span class="mut">' + esc(c.group ? (c.participants ? c.participants + ' members' : 'WhatsApp group') : plus(c.phone)) + '</span></section>';
    if (!c.group) {
      if (cu) {
        h += '<section class="ww-pc"><h3>Customer</h3><dl class="ww-dl"><dt>Locker</dt><dd><b class="mono">' + esc(cu.locker || '—') + '</b></dd>' +
          (cu.email ? '<dt>E-mail</dt><dd>' + esc(cu.email) + '</dd>' : '') +
          (cu.balance != null ? '<dt>Balance</dt><dd class="' + (cu.balance > 0 ? 'ww-owe' : '') + '">GHS ' + esc(Number(cu.balance).toFixed(2)) + '</dd>' : '') + '</dl>' +
          '<div class="row g8 wrap">' + (cu.url ? '<a class="sl-btn ghost sm" href="' + esc(cu.url) + '">Profile</a>' : '') + '<a class="sl-btn ghost sm" href="' + esc(cu.history_url) + '">Message history</a></div></section>';
        h += '<section class="ww-pc"><h3>Shipments</h3>';
        if (!cu.shipments.length) h += '<p class="mut sm">No shipments yet.</p>';
        cu.shipments.forEach(function (s) {
          h += '<a class="ww-ship" href="' + esc(s.url) + '"><span><b class="mono">' + esc(s.tracking) + '</b><span class="mut xs">' + esc(s.kind === 'package' ? 'Package' : 'Shipment') + ' · ' + esc(s.date) + '</span></span>' +
            '<span class="ww-st" style="' + (s.color ? '--c:' + esc(s.color) : '') + '">' + esc(s.status || '—') + '</span></a>';
        });
        h += '</section>';
      } else {
        h += '<section class="ww-pc"><h3>Customer</h3><p class="mut sm">This number is not linked to a customer account (no account has this phone number).</p></section>';
      }
    }
    // Conversation: status, owner, King
    var closed = c.status === 'closed';
    h += '<section class="ww-pc"><h3>Conversation</h3><dl class="ww-dl"><dt>Status</dt><dd>' + (closed ? '<span class="sl-pill plain">Closed</span>' : c.bot ? '<span class="sl-pill onway">With ' + esc(KING) + '</span>' : '<span class="sl-pill delivered">Open</span>') + '</dd>' +
      '<dt>Assigned</dt><dd>' + (c.assignee ? esc(c.assignee === CFG.me ? 'You' : c.assignee_name) : '<span class="mut">Nobody</span>') + '</dd></dl><div class="ww-acts">';
    if (!closed) h += c.assignee === CFG.me ? act('unassign', 'user', 'Unassign me') : act('assign_me', 'user', 'Assign to me');
    h += closed ? act('reopen', 'reply', 'Reopen') : act('close', 'check', 'Close');
    if (!c.group && !closed) {
      if (c.bot) h += act('bot_off', 'user', 'Take back from ' + KING);
      else h += '<button type="button" class="sl-btn ghost sm" data-ww-op="bot_on"' + (S.botEnabled ? '' : ' disabled title="' + esc(KING) + ' does not answer WhatsApp yet: switch it on in Communications › Settings"') + '>' + ic('spark') + 'Hand to ' + esc(KING) + '</button>';
    }
    h += '<button type="button" class="sl-btn ghost sm" data-ww="ticket">' + ic('ticket') + 'Open ticket</button>';
    if (!c.group && c.thread_id) h += '<a class="sl-btn ghost sm" href="' + esc(CFG.urls.inbox) + '">' + ic('inbox') + 'In the Inbox</a>';
    h += '</div>' + (!c.group && !S.botEnabled ? '<p class="mut xs">' + esc(KING) + ' answers WhatsApp only when it is switched on in Communications › Settings.</p>' : '') + '</section>';
    // Tags
    h += '<section class="ww-pc"><h3>Tags</h3><div class="ww-tags" id="ww_tags">' + (c.tags || []).map(function (t) {
      return '<span class="ww-chip on">' + esc(t) + '<button type="button" data-ww-untag="' + esc(t) + '" aria-label="Remove tag ' + esc(t) + '">' + ic('x') + '</button></span>'; }).join('') +
      '</div><form class="ww-tagf" id="ww_tagf"><input class="sl-in" maxlength="30" placeholder="Add a tag, e.g. VIP" aria-label="New tag"><button type="submit" class="sl-btn ghost sm">Add</button></form></section>';
    if (c.group) {
      h += '<section class="ww-pc"><h3>Members</h3><div id="ww_members"><div class="ww-load"><span class="ww-spin" aria-hidden="true"></span><span>Loading members…</span></div></div></section>';
    } else if (CFG.admin) {
      h += '<section class="ww-pc"><h3>Contact list</h3><p class="mut xs">Saved contacts can receive campaigns; a STOP from the number always wins.</p><button type="button" class="sl-btn ghost sm" data-ww="save-chat">' + ic('plus') + 'Save as contact</button></section>';
    }
    h += '</div>';
    panel.innerHTML = h;
    if (c.group) loadMembers(false);
  }
  function act(op, icon, label) { return '<button type="button" class="sl-btn ghost sm" data-ww-op="' + op + '">' + ic(icon) + esc(label) + '</button>'; }
  function loadMembers(fresh) {
    var box = $('ww_members');
    if (!box) return;
    get('participants', { chat_id: S.chatId, fresh: fresh ? '1' : '0' }).then(function (j) {
      box = $('ww_members');
      if (!box) return;
      if (!j.ok) { box.innerHTML = '<p class="mut sm">' + esc(j.error || 'Members could not be loaded.') + '</p>'; return; }
      var h = (j.note ? '<p class="ww-warn xs">' + esc(j.note) + '</p>' : '') + '<p class="mut sm">' + j.rows.length + ' member' + (j.rows.length === 1 ? '' : 's') + ' · ' +
        j.rows.filter(function (r) { return r.user_id; }).length + ' customers</p>';
      if (j.can_export) {
        h += '<div class="ww-acts"><a class="sl-btn ghost sm" href="' + esc(AJAX + '?action=export&what=group&format=csv&chat_id=' + encodeURIComponent(S.chatId)) + '" data-ww-dl>' + ic('download') + 'CSV</a>' +
          '<a class="sl-btn ghost sm" href="' + esc(AJAX + '?action=export&what=group&format=xlsx&chat_id=' + encodeURIComponent(S.chatId)) + '" data-ww-dl>' + ic('download') + 'Excel</a>' +
          '<button type="button" class="sl-btn ghost sm" data-ww="save-group">' + ic('plus') + 'Save as contacts</button>' +
          '<button type="button" class="ww-ib" data-ww="members-refresh" aria-label="Reload members" title="Reload members">' + ic('refresh') + '</button></div>' +
          '<p class="mut xs">Saved members are tagged “whatsapp_group:' + esc(j.name) + '” and can be reached by campaigns; anyone who replied STOP stays opted out.</p>';
      }
      h += '<ul class="ww-mem">';
      j.rows.slice(0, 300).forEach(function (r) {
        h += '<li><span class="ww-av sm" style="--h:' + hue(r.phone) + '" aria-hidden="true">' + (initials(r.name) ? esc(initials(r.name)) : ic('user')) + '</span><span class="ww-mn"><b>' + esc(r.me ? 'You' : (r.name || plus(r.phone))) + '</b><span class="mut xs">' +
          esc([r.name ? plus(r.phone) : '', r.locker ? 'Locker ' + r.locker : ''].filter(Boolean).join(' · ')) + '</span></span>' +
          (r.admin ? '<span class="ww-tag">Admin</span>' : '') + (r.saved === 'stop' ? '<span class="ww-tag bad">STOP</span>' : r.saved ? '<span class="ww-tag me">Saved</span>' : '') + '</li>';
      });
      if (j.rows.length > 300) h += '<li class="mut sm">and ' + (j.rows.length - 300) + ' more (all are in the export)</li>';
      box.innerHTML = h + '</ul>';
    });
  }

  /* ---------- connection dialog ---------- */
  function openConnection() {
    var body = $('ww_conn_body');
    body.innerHTML = '<div class="ww-load"><span class="ww-spin" aria-hidden="true"></span><span>Checking…</span></div>';
    $('ww_dlg_conn').hidden = false;
    get('state', { fresh: '1', webhook: CFG.admin ? '1' : '0' }).then(function (st) {
      applyState(st);
      var ok = st.state === 'connected', h = '<dl class="ww-dl wide"><dt>Status</dt><dd>' + (ok ? '<span class="sl-pill delivered">Connected</span>' : '<span class="sl-pill pending">' + esc(st.state || 'unknown') + '</span>') + '</dd>' +
        (st.me ? '<dt>Phone</dt><dd><b>' + esc(plus(st.me.phone)) + '</b>' + (st.me.name ? ' · ' + esc(st.me.name) : '') + '</dd>' : '') +
        '<dt>Provider</dt><dd>UltraMsg' + (st.instance ? ' · instance <span class="mono">' + esc(st.instance) + '</span>' : '') + '</dd>' +
        '<dt>Sending</dt><dd>' + (st.active ? 'On' : 'Switched off in Settings › General › Messaging') + '</dd>';
      if (st.webhook) {
        var w = st.webhook;
        h += '<dt>Live updates</dt><dd>' + (w.ok ? (w.points_here && w.received && w.ack ? '<span class="sl-pill delivered">On</span> new messages and ticks arrive at once' : '<span class="sl-pill onway">Off</span> the screen checks for news every few seconds') : '<span class="mut">' + esc(w.code === 'unsupported' ? 'The provider does not report its webhook settings.' : (w.error || 'Unknown')) + '</span>') + '</dd>';
      }
      h += '</dl>';
      if (st.error) h += '<p class="ww-err sm">' + esc(st.error) + '</p>';
      var notes = [];
      if (st.caps && st.caps.groups === false) notes.push('Group member lists are not offered by the provider: members are taken from the people who wrote in each group.');
      if (st.caps && st.caps.contacts === false) notes.push('The provider does not list the phone\'s contacts, so the contacts export uses the chats instead.');
      if (notes.length) h += '<ul class="ww-notes">' + notes.map(function (n) { return '<li>' + esc(n) + '</li>'; }).join('') + '</ul>';
      if (CFG.admin) {
        h += '<div class="ww-acts">';
        if (ok && st.webhook && st.webhook.ok && !(st.webhook.points_here && st.webhook.received && st.webhook.ack)) h += '<button type="button" class="sl-btn sm" data-ww="webhook"' + (st.webhook.can_setup ? '' : ' disabled title="Save Communications › Settings once so the webhook address gets its secret key"') + '>' + ic('bolt') + 'Turn on live updates</button>';
        h += '<button type="button" class="sl-btn ghost sm" data-ww="restart">' + ic('refresh') + 'Restart</button>';
        if (st.state === 'standby') h += '<button type="button" class="sl-btn ghost sm" data-ww="takeover">Use here</button>';
        if (ok) h += '<button type="button" class="sl-btn danger sm" data-ww="logout">' + ic('logout') + 'Unlink this phone</button>';
        else h += '<button type="button" class="sl-btn sm" data-ww="relink">Show the QR code</button>';
        h += '</div><p class="mut xs">Unlinking signs the company phone out of this instance: nothing is sent or received until a phone is linked again with the QR code.</p>';
      } else {
        h += '<p class="mut sm">Administrators link, unlink and restart the WhatsApp connection.</p>';
      }
      body.innerHTML = h;
    });
  }

  /* ---------- contacts dialog (admin) ---------- */
  function openContacts() {
    var body = $('ww_contacts_body');
    if (!body) return;
    $('ww_dlg_contacts').hidden = false;
    body.innerHTML = '<div class="ww-load"><span class="ww-spin" aria-hidden="true"></span><span>Loading…</span></div>';
    get('contacts_info').then(function (j) {
      if (!j.ok) { body.innerHTML = '<p class="ww-err">' + esc(j.error || 'Not available.') + '</p>'; return; }
      var h = '<div class="ww-stats"><span><b>' + j.count + '</b><span class="mut xs">in the phone' + (j.synced_at ? ' · read ' + esc(j.synced_at.slice(0, 16)) : '') + '</span></span>' +
        '<span><b>' + j.saved + '</b><span class="mut xs">saved contacts</span></span><span><b>' + j.stopped + '</b><span class="mut xs">opted out (STOP)</span></span></div>';
      if (j.caps && j.caps.contacts === false) h += '<p class="ww-warn sm">The provider does not list the phone\'s contacts. Group members can still be exported from each group\'s details.</p>';
      h += '<div class="ww-acts"><button type="button" class="sl-btn ghost sm" data-ww="contacts-sync">' + ic('refresh') + 'Read contacts from the phone</button>' +
        '<a class="sl-btn ghost sm" href="' + esc(AJAX + '?action=export&what=contacts&format=csv') + '" data-ww-dl>' + ic('download') + 'Export CSV</a>' +
        '<a class="sl-btn ghost sm" href="' + esc(AJAX + '?action=export&what=contacts&format=xlsx') + '" data-ww-dl>' + ic('download') + 'Export Excel</a>' +
        '<button type="button" class="sl-btn sm" data-ww="save-contacts">' + ic('plus') + 'Save all as contacts</button></div>' +
        '<p class="mut sm">Saved contacts are tagged with where they came from (<code>whatsapp_contacts</code>, <code>whatsapp_group:&lt;name&gt;</code>, <code>whatsapp_chat</code>) and campaigns can reach them, as the owner decided. Anyone who replies STOP is opted out and skipped. Every export and save is recorded.</p>' +
        '<a class="sl-btn ghost sm" href="' + esc(j.send_url) + '">' + ic('send') + 'Use them in a campaign</a>';
      if (j.exports.length) {
        h += '<h3 class="ww-h3">Recent exports and saves</h3><ul class="ww-log">' + j.exports.map(function (e) {
          return '<li><span>' + esc(e.format === 'save' ? 'Saved ' + e.saved + ' of ' + e.rows : e.format.toUpperCase() + ' · ' + e.rows + ' rows') + ' · ' + esc(e.target || e.kind) + '</span><span class="mut xs">' + esc(e.by) + ' · ' + esc(e.at.slice(0, 16)) + '</span></li>'; }).join('') + '</ul>';
      }
      body.innerHTML = h;
    });
  }

  /* ---------- actions ---------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-ww]');
    if (a && document.body.contains(a)) {
      var w = a.getAttribute('data-ww');
      switch (w) {
        case 'refresh': S.lsig = ''; a.classList.add('loading'); loadChats(true, true).then(function () { a.classList.remove('loading'); }); return;
        case 'reload': openChat(S.chatId, true); return;
        case 'back': app.classList.remove('is-chat'); sizeApp(); S.chatId = ''; S.chat = null; drawList(); togglePanel(false); try { history.replaceState(null, '', location.pathname); } catch (er) {} return;
        case 'panel': togglePanel(); return;
        case 'panel-close': togglePanel(false); return;
        case 'attach': $('ww_file').click(); return;
        case 'unattach': S.file = null; $('ww_file').value = ''; drawAttachment(); return;
        case 'quick': openQuick(); return;
        case 'qr-edit': editQuick(); return;
        case 'qr-add': addQuickRow({ title: '', body: '' }); var rs = $('ww_qr_rows').querySelectorAll('input'); rs[rs.length - 1].focus(); return;
        case 'qr-del': a.closest('.ww-qr-row').remove(); return;
        case 'qr-save': saveQuick(a); return;
        case 'king': var k = $('ww_king'); if (k && !k.hidden) closeKing(); else openKing(''); return;
        case 'king-close': closeKing(); return;
        case 'king-again': openKing(''); return;
        case 'king-use': var kk = $('ww_king'); insertText(kk.getAttribute('data-text') || '', true); closeKing(); return;
        case 'ticket': openTicket(); return;
        case 'connection': openConnection(); return;
        case 'contacts': openContacts(); return;
        case 'contacts-sync': a.classList.add('loading'); post('sync_contacts').then(function (j) { a.classList.remove('loading'); if (!j.ok) fail(j, 'The contacts could not be read.'); else { SL.toast(j.count + ' contacts read from the phone.'); openContacts(); } }); return;
        case 'save-contacts': saveContacts(a, { what: 'contacts' }, 'Save every contact of the phone as a contact campaigns can reach? People who replied STOP stay opted out.'); return;
        case 'save-group': saveContacts(a, { what: 'group', chat_id: S.chatId }, 'Save the members of this group as contacts campaigns can reach? They are tagged with the group\'s name; people who replied STOP stay opted out.'); return;
        case 'save-chat': saveContacts(a, { what: 'chat', chat_id: S.chatId }, 'Save this number as a contact campaigns can reach?'); return;
        case 'members-refresh': loadMembers(true); return;
        case 'retry': boot(true); return;
        case 'offline': showApp(true); loadChats(false); showNote('WhatsApp is not connected. You are reading saved chats; replies cannot be sent until it is back.'); return;
        case 'restart': instance(a, 'restart'); return;
        case 'takeover': instance(a, 'takeover'); return;
        case 'logout': if (confirm('Unlink the company WhatsApp phone from this instance? Nothing is sent or received until a phone is linked again.')) instance(a, 'logout'); return;
        case 'relink': $('ww_dlg_conn').hidden = true; connScreen({ state: 'qr', configured: true }); return;
        case 'webhook': a.classList.add('loading'); post('webhook_setup').then(function (j) { a.classList.remove('loading'); if (!j.ok) fail(j); else { SL.toast(j.message); openConnection(); } }); return;
      }
    }
    var q = e.target.closest('[data-ww-quick]');
    if (q) { var r = S.quick.rows[+q.getAttribute('data-ww-quick')]; if (r) insertText(fill(r.body), false); closeQuick(); return; }
    var tn = e.target.closest('[data-ww-tone]');
    if (tn) { openKing(tn.getAttribute('data-ww-tone')); return; }
    var op = e.target.closest('[data-ww-op]');
    if (op && !op.disabled) {
      op.classList.add('loading'); op.disabled = true;
      post('chat_update', { chat_id: S.chatId, op: op.getAttribute('data-ww-op') }).then(function (j) {
        op.classList.remove('loading'); op.disabled = false;
        if (!j.ok) { fail(j); return; }
        SL.toast({ assign_me: 'Assigned to you.', unassign: 'Unassigned.', close: 'Chat closed.', reopen: 'Chat reopened.', bot_on: KING + ' answers this chat now.', bot_off: 'You answer this chat now.' }[op.getAttribute('data-ww-op')] || 'Saved.');
        S.lsig = ''; openChat(S.chatId);
      });
      return;
    }
    var ut = e.target.closest('[data-ww-untag]');
    if (ut) { var tags = (S.chat.tags || []).filter(function (t) { return t !== ut.getAttribute('data-ww-untag'); }); saveTags(tags); return; }
    var dl = e.target.closest('[data-ww-dl]');
    if (dl) SL.toast('Preparing the file… The export is recorded.');
    // outside clicks close the pop-ups
    var quick = $('ww_quick');
    if (quick && !quick.hidden && !e.target.closest('#ww_quick') && !e.target.closest('[data-ww="quick"]')) closeQuick();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var q = $('ww_quick'), k = $('ww_king');
    if (q && !q.hidden) { closeQuick(); var b = root.querySelector('[data-ww="quick"]'); if (b) b.focus(); }
    else if (k && !k.hidden) { closeKing(); }
    else if (isNarrow() && !panel.hidden && !document.querySelector('.sl-dialog:not([hidden])')) togglePanel(false);
  });
  document.addEventListener('submit', function (e) {
    if (e.target.id === 'ww_tagf') {
      e.preventDefault();
      var inp = e.target.querySelector('input'), v = inp.value.trim();
      if (!v) { inp.focus(); return; }
      saveTags((S.chat.tags || []).concat([v]));
    }
    if (e.target.id === 'ww_ticket_form') {
      e.preventDefault();
      var f = e.target, btn = f.querySelector('[type="submit"]');
      if (!f.subject.value.trim()) { f.subject.focus(); f.subject.setAttribute('aria-invalid', 'true'); return; }
      btn.disabled = true; btn.classList.add('loading');
      post('ticket', { chat_id: S.chatId, subject: f.subject.value, priority: f.priority.value, note: f.note.value }).then(function (j) {
        btn.disabled = false; btn.classList.remove('loading');
        if (!j.ok) { fail(j, 'The ticket could not be opened.'); return; }
        $('ww_dlg_ticket').hidden = true; f.reset();
        SL.toast(j.message || 'Ticket opened.');
        showNote('Ticket <a href="' + esc(j.url) + '">' + esc(j.ref) + '</a> opened from this chat.');
      });
    }
  });
  function saveTags(tags) {
    post('tags', { chat_id: S.chatId, tags: tags.join(',') }).then(function (j) {
      if (!j.ok) { fail(j); return; }
      S.chat.tags = j.tags; S.lsig = ''; drawPanel();
      var inp = document.querySelector('#ww_tagf input'); if (inp) inp.focus();
    });
  }
  function openTicket() {
    var f = $('ww_ticket_form'), c = S.chat || {};
    $('ww_ticket_who').textContent = 'From the WhatsApp chat with ' + (S.customer ? S.customer.name + ' (' + plus(c.phone) + ')' : (c.name || plus(c.phone))) + '.';
    if (!f.subject.value) {
      var lastIn = (S.messages || []).filter(function (m) { return !m.me && m.body; }).pop();
      f.subject.value = lastIn ? 'WhatsApp: ' + lastIn.body.slice(0, 80) : 'WhatsApp conversation';
    }
    $('ww_dlg_ticket').hidden = false;
  }
  function instance(btn, op) {
    btn.classList.add('loading'); btn.disabled = true;
    post('instance', { op: op }).then(function (j) {
      btn.classList.remove('loading'); btn.disabled = false;
      if (!j.ok) { fail(j); return; }
      SL.toast(j.message || 'Done.');
      $('ww_dlg_conn').hidden = true;
      if (op === 'logout') { S.me = null; connScreen({ state: 'qr', configured: true }); }
      else setTimeout(function () { boot(true); }, op === 'restart' ? 3000 : 500);
    });
  }
  function saveContacts(btn, data, question) {
    if (!confirm(question)) return;
    btn.classList.add('loading'); btn.disabled = true;
    post('save_contacts', data).then(function (j) {
      btn.classList.remove('loading'); btn.disabled = false;
      if (!j.ok) { fail(j); return; }
      SL.toast(j.message || 'Saved.');
      if (data.what === 'group') loadMembers(false);
      if (data.what === 'contacts') openContacts();
    });
  }
  function saveQuick(btn) {
    var rows = Array.prototype.map.call($('ww_qr_rows').querySelectorAll('.ww-qr-row'), function (r) {
      return { title: r.querySelector('input').value.trim(), body: r.querySelector('textarea').value.trim() };
    }).filter(function (r) { return r.title || r.body; });
    var bad = rows.filter(function (r) { return !r.title || !r.body; });
    if (bad.length) { SL.toast('Every quick reply needs a title and a message.', true); return; }
    btn.classList.add('loading');
    post('quick_replies_save', { rows: JSON.stringify(rows) }).then(function (j) {
      btn.classList.remove('loading');
      if (!j.ok) { fail(j); return; }
      S.quick = { rows: j.rows, can_edit: true };
      $('ww_dlg_qr').hidden = true;
      SL.toast(j.message || 'Saved.');
    });
  }

  /* ---------- start ---------- */
  conn.hidden = false;
  boot(false);
})();
