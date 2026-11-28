/* King, the customer care chat (views/sl/inc/king_chat.php). Talks to ajax/support/customer_ajax.php:
   brief (the personal briefing), start via=king, send, quick (quick replies), human, upload, poll (peek while closed), rate.
   Every POST says via=king: a chat the team closed is never reopened from here (King owns the next message).
   Dock mode: a bubble on every customer page that opens a chat sheet (full screen on phones).
   Inline mode: the customer dashboard panel (desktop) / card that opens the full-screen chat (phones).
   Everything the server or the customer wrote is escaped before it reaches the page. */
(function () {
  'use strict';
  var CFG = window.SL_KC, root = document.getElementById('kc');
  if (!CFG || !root || !window.SL) return;
  var URL_ = CFG.url, KING = CFG.name || 'King', INLINE = CFG.mode === 'inline';
  var phoneQ = window.matchMedia('(max-width: 767px)');
  var q = function (sel) { return root.querySelector(sel); };
  var panel = q('#kc-panel'), thread = q('[data-kc-thread]'), scroller = q('[data-kc-scroll]'), text = q('[data-kc-text]');
  var chipsBox = q('[data-kc-chips]'), statusBox = q('[data-kc-status]'), typingBox = q('[data-kc-typing]');
  var launch = q('[data-kc-launch]'), teaser = q('[data-kc-teaser]'), foot = q('[data-kc-foot]'), filesBox = q('[data-kc-files]'), fileIn = q('[data-kc-file]');
  var SITE = CFG.site || 'Swift Lane';
  var store = { get: function (k) { try { return sessionStorage.getItem('kc.' + k); } catch (e) { return null; } }, set: function (k, v) { try { sessionStorage.setItem('kc.' + k, v); } catch (e) {} } };
  var live = CFG.live || null;
  var st = {
    conv: live ? live.id : 0, owner: live ? live.owner : 'ai_active', ownerName: '', ticketId: 0, canRate: false, rated: null, queue: null, staffSeen: '',
    last: 0, seen: {}, busy: false, open: false, loaded: false, loading: null, brief: null, codes: {}, timer: null, unread: live ? (live.unread || 0) : 0,
    rateShown: {}, closedNote: {}, hour: typeof CFG.hour === 'number' ? CFG.hour : null, hourAt: Date.now(), files: [], outbox: []
  };
  var serverSkew = 0;
  function clockSync(now) { var s = parse(now); if (s) serverSkew = s - Date.now(); }
  clockSync(CFG.now);

  /* ---------- text helpers ---------- */
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function safeUrl(u) { u = String(u || '').trim(); return /^[a-z0-9_\-]+\.php(\?[A-Za-z0-9_=&%.\-+#]*)?$/i.test(u) || /^https:\/\/[^\s"'<>]+$/i.test(u) ? u : null; }
  function inline(s) {
    s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, l, u) { var x = safeUrl(u.replace(/&amp;/g, '&')); return x ? '<a href="' + esc(x) + '"' + (/^https:/.test(x) ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' + l + '</a>' : l; });
    /* bare https:// links typed by the customer or the team (already escaped text; links made above are kept) */
    s = s.replace(/(<a\b[^>]*>[\s\S]*?<\/a>)|(https:\/\/[^\s<]+)/g, function (m, a, u) {
      if (a) return a;
      var tail = ''; var t;
      while ((t = /(&quot;|&#39;|[.,!?;:)\]])$/.exec(u))) { tail = t[1] + tail; u = u.slice(0, -t[1].length); }
      var x = safeUrl(u.replace(/&amp;/g, '&'));
      return x ? '<a href="' + esc(x) + '" target="_blank" rel="noopener noreferrer">' + u + '</a>' + tail : m;
    });
    return s.replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>').replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>').replace(/`([^`]+)`/g, '<code>$1</code>');
  }
  /* Markdown subset (paragraphs, lists, bold, italics, code, safe links) on already-escaped text. */
  function md(t) {
    var lines = esc(String(t == null ? '' : t).replace(/\r\n?/g, '\n')).split('\n'), out = [], para = [], list = [];
    function flushP() { if (para.length) { out.push('<p>' + inline(para.join('<br>')) + '</p>'); para = []; } }
    function flushL() { if (list.length) { out.push('<ul>' + list.join('') + '</ul>'); list = []; } }
    lines.forEach(function (l) {
      if (/^\s*([-*+]|\d+[.)])\s+/.test(l)) { flushP(); list.push('<li>' + inline(l.replace(/^\s*([-*+]|\d+[.)])\s+/, '')) + '</li>'); return; }
      if (/^\s*$/.test(l)) { flushP(); flushL(); return; }
      flushL(); para.push(l.replace(/^#{1,4}\s+/, '').trim());
    });
    flushP(); flushL();
    return out.join('');
  }
  /* Server times are wall-clock times of the installation; compare them with the server clock, not the device's. */
  function parse(dt) { var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/.exec(String(dt || '')); return m ? Date.UTC(+m[1], m[2] - 1, +m[3], +m[4], +m[5], +(m[6] || 0)) : 0; }
  function clock(ms) { var d = new Date(ms), h = d.getUTCHours(), mi = d.getUTCMinutes(); return ((h % 12) || 12) + ':' + (mi < 10 ? '0' : '') + mi + ' ' + (h < 12 ? 'AM' : 'PM'); }
  var SHIFT = (+CFG.shift || 0) * 1000; // support clock -> the site's clock, for display
  function stamp(dt) {
    var t = parse(dt); if (!t) return '';
    var now = Date.now() + serverSkew, diff = (now - t) / 1000;
    if (diff >= -30 && diff < 60) return 'just now';
    t += SHIFT; now += SHIFT;
    var a = new Date(t), b = new Date(now);
    if (a.toISOString().slice(0, 10) === b.toISOString().slice(0, 10)) return clock(t);
    return a.toLocaleDateString(undefined, { month: 'short', day: 'numeric', timeZone: 'UTC' }) + ', ' + clock(t);
  }
  function uid() { return 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 9); }
  /* Time of day on the site's clock (sent by the server), so the chat and the dashboard greet alike. */
  function hello() {
    var h = st.hour == null ? new Date().getHours() : (st.hour + Math.floor((Date.now() - st.hourAt) / 3600000)) % 24;
    return (h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening') + (CFG.first ? ', ' + CFG.first : '');
  }
  function initial(name) { return esc(String(name || '?').trim().charAt(0).toUpperCase() || '?'); }
  // King's avatar markup from the server (the AI icon, SL_KC.face); the letter when it is missing
  var FACE = typeof CFG.face === 'string' && /^<(i|svg) /.test(CFG.face) ? CFG.face : '';
  function kingAv() { return FACE || initial(KING); }
  function icon(cls) { return /^i-[a-z0-9-]+$/.test(cls || '') ? '<i class="ic ic-sm ' + cls + '" aria-hidden="true"></i>' : ''; }
  var PILLS = { delivered: 1, transit: 1, onway: 1, sorting: 1, pending: 1, overdue: 1, paid: 1, plain: 1 };

  /* ---------- requests ---------- */
  function post(data) {
    var fd = new FormData(); data.via = 'king';
    Object.keys(data).forEach(function (k) { var v = data[k]; if (Array.isArray(v)) v.forEach(function (x) { fd.append(k + '[]', x); }); else fd.append(k, v == null ? '' : v); });
    return SL.post(URL_, fd);
  }
  function get(params) { return SL.get(URL_, params); }
  function fail(r) { SL.toast((r && (r.error || r.message)) || 'That did not go through. Check your connection and try again.', true); }

  /* ---------- rendering ---------- */
  function cardHtml(c) {
    var acts = (c.actions || []).map(function (a) {
      var cls = 'kc-act' + (a.primary ? ' pri' : '');
      if (a.act === 'code') return '<button type="button" class="' + cls + '" data-kc-code="' + esc(a.ref) + '">' + icon(a.icon) + esc(a.label) + '</button>';
      if (a.act === 'intent') return '<button type="button" class="' + cls + '" data-kc-intent="' + esc(a.intent) + '" data-kc-label="' + esc(a.label) + '">' + icon(a.icon) + esc(a.label) + '</button>';
      if (a.act === 'human') return '<button type="button" class="' + cls + '" data-kc-act="human">' + icon(a.icon) + esc(a.label) + '</button>';
      if (a.act === 'rate') return '<button type="button" class="' + cls + '" data-kc-rate="' + (+a.id || 0) + '">' + icon(a.icon) + esc(a.label) + '</button>';
      var u = safeUrl(a.href);
      return u ? '<a class="' + cls + '" href="' + esc(u) + '">' + icon(a.icon) + esc(a.label) + '</a>' : '';
    }).join('');
    var pill = c.status ? '<span class="sl-pill ' + (PILLS[c.cls] ? c.cls : 'plain') + '">' + esc(c.status) + '</span>' : '';
    return '<article class="kc-card k-' + esc(c.k || 'x') + '">'
      + '<div class="kc-card-top"><span class="kc-card-ic">' + icon(c.icon || 'i-box') + '</span><span class="kc-card-id">'
      + (c.ref ? '<b class="mono">' + esc(c.ref) + '</b>' : '') + (c.title ? '<small>' + esc(c.title) + '</small>' : '') + '</span>' + pill + '</div>'
      + (c.amount ? '<div class="kc-card-amt">' + esc(c.amount) + '</div>' : '')
      + (c.text ? '<p class="kc-card-text">' + esc(c.text) + '</p>' : '')
      + ((c.eta || c.when) ? '<div class="kc-card-meta">' + (c.eta ? '<span class="kc-eta">' + icon('i-clock') + esc(c.eta) + '</span>' : '') + (c.when ? '<span>' + esc(c.when) + '</span>' : '') + '</div>' : '')
      + (acts ? '<div class="kc-card-acts">' + acts + '</div>' : '')
      + '</article>';
  }
  function cardsHtml(list) { return list && list.length ? '<div class="kc-cards' + (list.length === 1 ? ' one' : '') + '">' + list.map(cardHtml).join('') + '</div>' : ''; }
  function kingRow(inner, id, when) {
    return '<div class="kc-row k" data-id="' + esc(id) + '"><span class="kc-av sm' + (FACE ? ' face' : '') + '" aria-hidden="true">' + kingAv() + '</span><div class="kc-msg"><div class="kc-bub">' + inner + '</div>'
      + '<small class="kc-meta">' + esc(KING) + (when ? ' · ' + esc(when) : '') + '</small></div></div>';
  }
  function bubble(m) {
    var t = m.author_type, meta = m.meta || {};
    if (t === 'system') {
      if (meta.kind === 'joined') return '<div class="kc-note join" data-id="' + esc(m.id) + '"><span class="kc-av sm p" aria-hidden="true">' + initial(meta.agent || m.body) + '</span>' + esc(m.body) + '</div>';
      if (meta.kind === 'greeting' || meta.kind === 'ack') return '<div class="kc-note" data-id="' + esc(m.id) + '">' + md(m.body) + '</div>';
      return '<div class="kc-note box" data-id="' + esc(m.id) + '">' + md(m.body) + '</div>';
    }
    if (t === 'king') {
      var inner = meta.lead ? md(meta.lead) + cardsHtml(meta.cards) + (meta.tail ? md(meta.tail) : '') : md(m.body) + cardsHtml(meta.cards && meta.cards[0] && meta.cards[0].k ? meta.cards : []);
      return kingRow(inner, m.id, stamp(m.created_at));
    }
    if (t === 'staff') {
      return '<div class="kc-row p" data-id="' + esc(m.id) + '"><span class="kc-av sm p" aria-hidden="true">' + initial(m.author) + '</span><div class="kc-msg"><div class="kc-bub">' + md(m.body) + atts(m.attachments) + '</div>'
        + '<small class="kc-meta">' + esc(m.author || 'Customer care') + ' · Customer care · ' + esc(stamp(m.created_at)) + '</small></div></div>';
    }
    return '<div class="kc-row me" data-id="' + esc(m.id) + '" data-at="' + esc(m.created_at || '') + '"><div class="kc-msg"><div class="kc-bub">' + md(m.body) + atts(m.attachments) + '</div>'
      + '<small class="kc-meta" data-kc-meta>' + esc(m.created_at ? stamp(m.created_at) : 'Sending…') + '</small></div></div>';
  }
  function atts(list) {
    if (!list || !list.length) return '';
    return '<div class="kc-atts">' + list.map(function (a) {
      var u = 'ajax/support/file.php?t=' + encodeURIComponent(a.token || '');
      return /^image\//.test(a.mime || '') ? '<a href="' + esc(u) + '" target="_blank" rel="noopener"><img src="' + esc(u) + '" alt="' + esc(a.name) + '"></a>' : '<a class="sl-pill plain" href="' + esc(u + '&dl=1') + '">' + icon('i-paperclip') + esc(a.name) + '</a>';
    }).join('') + '</div>';
  }
  function add(m) {
    if (m.id && st.seen[m.id]) return false;
    if (m.id) st.seen[m.id] = true;
    thread.insertAdjacentHTML('beforeend', bubble(m));
    if (typeof m.id === 'number' && m.id > st.last) st.last = m.id;
    return true;
  }
  function addList(list) {
    var added = 0, lastKing = null;
    (list || []).forEach(function (m) { if (add(m)) { added++; if (m.author_type === 'king') lastKing = m; } });
    if (added) {
      var last = list[list.length - 1];
      if (lastKing && lastKing === last && lastKing.meta && lastKing.meta.chips) chips(lastKing.meta.chips);
      else if (last && last.author_type !== 'customer' && st.owner === 'ai_active') chips(null, true);
      readState(); toBottom();
    }
    return added;
  }
  function toBottom() { requestAnimationFrame(function () { scroller.scrollTop = scroller.scrollHeight; }); }
  /* "Seen" on the customer's latest message once King or the team answered, or the team opened the chat after it. */
  function readState() {
    var mine = thread.querySelectorAll('.kc-row.me'), lastMine = mine[mine.length - 1];
    thread.querySelectorAll('.kc-seen').forEach(function (x) { x.remove(); });
    if (!lastMine || !/^\d+$/.test(lastMine.getAttribute('data-id') || '')) return;
    var answered = false, n = lastMine.nextElementSibling;
    while (n) { if (n.classList.contains('kc-row') && !n.classList.contains('me')) { answered = true; break; } n = n.nextElementSibling; }
    var seen = answered || (st.staffSeen && parse(st.staffSeen) >= parse(lastMine.getAttribute('data-at')));
    var meta = lastMine.querySelector('[data-kc-meta]');
    if (meta) meta.insertAdjacentHTML('beforeend', '<span class="kc-seen"> · ' + (seen ? 'Seen' : 'Sent') + '</span>');
  }
  var lastChips = null;
  function chips(list, keep) {
    if (keep) list = lastChips;
    if (st.owner !== 'ai_active') list = null;
    lastChips = list || lastChips;
    chipsBox.innerHTML = (list || []).map(function (c) {
      return '<button type="button" class="kc-chip' + (c.intent === 'human' ? ' person' : '') + '" data-kc-intent="' + esc(c.intent) + '">' + (c.intent === 'human' ? icon('i-user') : '') + esc(c.label) + '</button>';
    }).join('');
    chipsBox.hidden = !list || !list.length;
    chipsBox.scrollLeft = 0;
  }
  function typing(on, who) {
    typingBox.hidden = !on;
    if (!on) return;
    var person = who && who !== KING;
    var tav = q('[data-kc-typing-av]');
    if (person || !FACE) tav.textContent = String(who || KING).charAt(0).toUpperCase(); else tav.innerHTML = FACE;
    tav.classList.toggle('p', !!person); tav.classList.toggle('face', !person && !!FACE);
    q('[data-kc-typing-t]').textContent = person ? who + ' is typing' : KING + ' is checking…';
    toBottom();
  }

  /* ---------- who has the chat ---------- */
  function state(r) {
    if (!r) return;
    if (r.now) clockSync(r.now);
    var before = st.owner, beforeName = st.ownerName;
    if (r.owner) st.owner = r.owner;
    st.ownerName = r.owner_name || (st.owner === 'handoff_queued' ? '' : st.ownerName) || ''; // back in the queue: nobody has it
    st.ticketId = r.ticket_id || st.ticketId; st.canRate = !!r.can_rate; st.rated = r.rated == null ? null : r.rated;
    st.queue = r.queue || null; st.staffSeen = r.staff_seen || '';
    root.setAttribute('data-owner', st.owner);
    // A person stepped away (returned the chat to the queue): say so, not only in the banner.
    if (before === 'human_active' && st.owner === 'handoff_queued' && st.loaded) {
      thread.insertAdjacentHTML('beforeend', '<div class="kc-note">' + esc((beforeName || 'Our team member') + " had to step away. You're back in the queue, and the next person will see everything here.") + '</div>');
      toBottom();
    }
    header();
    // Closed by the team, from any state (taken or still waiting), or already closed when the page loaded.
    if (st.owner === 'resolved' && st.conv) { closedByAgent(before); return; }
    if (st.owner !== 'ai_active') chips(null);
    readState();
  }
  function header() {
    var roomy = INLINE && !phone(), name = KING, sub = roomy ? SITE + ' customer care · online now' : 'Online now', av = kingAv(), person = false;
    if (st.owner === 'human_active') { name = st.ownerName || 'Customer care'; sub = roomy ? SITE + ' customer care team' : 'Customer care team'; av = initial(name); person = true; }
    var x = q('[data-kc-close] .ic'); if (x && INLINE) { x.classList.toggle('i-x', phone()); x.classList.toggle('i-minus', !phone()); }
    q('[data-kc-name]').textContent = name; q('[data-kc-sub]').textContent = sub;
    var a = q('[data-kc-av]'); a.innerHTML = av + '<i class="kc-on"></i>'; a.classList.toggle('p', person); a.classList.toggle('face', !person && !!FACE);
    q('[data-kc-human]').hidden = st.owner === 'handoff_queued' || st.owner === 'human_active';
    text.placeholder = st.owner === 'human_active' ? 'Write to ' + name + '…' : st.owner === 'handoff_queued' ? 'Add anything our team should know…' : 'Ask ' + KING + ' anything…';
    if (foot) {
      foot.textContent = st.owner === 'human_active' ? "You're chatting with " + name + ' from our customer care team. Never share passwords or card numbers here.'
        : st.owner === 'handoff_queued' ? 'A person from our customer care team will reply right here. Never share passwords or card numbers here.'
        : (foot.getAttribute('data-king') || '');
    }
    var s = '';
    if (st.owner === 'handoff_queued') {
      var qi = st.queue || {}, bits = [], hours = qi.hours ? ' (hours: ' + esc(qi.hours) + ')' : '';
      if (qi.online) {
        if (qi.assignee) bits.push(esc(qi.assignee) + ' will be with you shortly.');
        else if (qi.position) bits.push(qi.position === 1 ? "You're next in line." : "You're number " + (+qi.position) + ' in line.');
        bits.push(qi.typical_min ? 'Someone usually joins within about ' + (+qi.typical_min) + ' min.' : 'Our team is online now.');
      } else if (qi.assignee) {
        bits.push(esc(qi.assignee) + ' will pick this up when our team is back' + hours + ". We'll reply right here.");
      } else {
        bits.push("Our team isn't online right now. We'll reply right here and let you know" + hours + '.');
      }
      s = '<span class="kc-pulse" aria-hidden="true"></span><span><b>Connecting you to our customer care team…</b><br>' + bits.join(' ') + '</span>';
    } else if (st.owner === 'human_active') {
      s = '<span class="kc-live" aria-hidden="true"></span><span><b>' + esc(st.ownerName || 'Customer care') + '</b> from our customer care team is with you.</span>';
    }
    statusBox.innerHTML = s; statusBox.hidden = !s;
    statusBox.className = 'kc-status' + (st.owner === 'human_active' ? ' live' : '');
  }
  /* The team closed the chat: say so, ask for a rating when the ticket can be rated, and King owns the next message
     (a new chat on the server). */
  function closedByAgent(before) {
    var conv = st.conv;
    stop();
    if (!st.closedNote[conv]) {
      st.closedNote[conv] = true;
      var who = st.ownerName || 'Our team';
      if (st.loaded) {
        thread.insertAdjacentHTML('beforeend', '<div class="kc-note">' + esc(who + ' has closed this chat. Thanks for talking with us' + (CFG.first ? ', ' + CFG.first : '') + '.') + '</div>');
        if (st.canRate && st.ticketId && st.rated == null) rateCard(st.ticketId, who === 'Our team' ? 'we' : who);
        if (before && before !== 'resolved') thread.insertAdjacentHTML('beforeend', kingRow('<p>' + esc("I'm still here if you need anything else. Just ask.") + '</p>', 'back-' + conv, 'just now'));
      }
    }
    st.conv = 0; st.owner = 'ai_active'; st.ownerName = ''; st.queue = null; root.setAttribute('data-owner', 'ai_active'); header();
    chips(st.brief ? st.brief.chips : null); toBottom();
  }
  function rateCard(id, who) {
    if (st.rateShown[id]) return; st.rateShown[id] = true;
    thread.insertAdjacentHTML('beforeend', '<div class="kc-rate" data-kc-rate-box="' + (+id) + '"><b>How did ' + esc(who || 'we') + ' do?</b>'
      + '<div class="kc-stars" role="group" aria-label="Rating from 1 to 5">' + [1, 2, 3, 4, 5].map(function (n) { return '<button type="button" data-kc-star="' + n + '" aria-label="' + n + ' star' + (n > 1 ? 's' : '') + '" aria-pressed="false">★</button>'; }).join('') + '</div>'
      + '<textarea rows="2" maxlength="2000" placeholder="Anything we could do better? (optional)" aria-label="Comment (optional)"></textarea>'
      + '<button type="button" class="sl-btn sm" data-kc-rate-send>Send rating</button></div>');
    toBottom();
  }

  /* ---------- the briefing ---------- */
  function renderBrief(r) {
    var b = r.brief || {}; st.brief = b; st.codes = b.codes || {};
    if (r.now) clockSync(r.now);
    if (typeof r.hour === 'number') { st.hour = r.hour; st.hourAt = Date.now(); }
    if (r.first) CFG.first = r.first;
    var lead = b.lead ? b.lead : (b.empty ? "Everything's quiet on your account right now: nothing waiting for you and nothing due. What can I help you with today?" : '');
    var extra = b.extra && b.extra.length ? b.extra : [];
    var html = '<p><b>' + esc(hello()) + ". I'm " + esc(KING) + '.</b></p>' + (lead ? md(lead) : '') + cardsHtml(b.cards)
      + (extra.length ? '<div class="kc-extra" data-kc-extra hidden>' + cardsHtml(extra) + '</div><p class="kc-more" data-kc-more>' + esc(b.more_text || ('There are ' + extra.length + ' more.')) + ' <button type="button" class="kc-link" data-kc-show-all aria-expanded="false">Show everything</button></p>' : '')
      + (!b.empty ? '<p>' + esc('What would you like to do first?') + '</p>' : '');
    var old = thread.querySelector('[data-id="brief"]'); if (old) old.remove();
    thread.insertAdjacentHTML('afterbegin', kingRow(html, 'brief', 'just now'));
    chips(b.chips || []);
    teaserFill(r);
  }
  function teaserFill(r) {
    var b = r.brief || {}, c = b.counts || {}, tags = [];
    var plain = String(b.lead || (b.empty ? "Everything's quiet on your account. Ask me about your parcels, bills or pickup." : 'Ask me about your parcels, bills or pickup.')).replace(/\*\*([^*]+)\*\*/g, '$1').replace(/\*([^*]+)\*/g, '$1');
    var m = q('[data-kc-min-t]'); if (m) m.textContent = hello() + '. ' + plain;
    if (!teaser) return;
    teaser.querySelector('[data-kc-hello]').textContent = hello() + ". I'm " + KING + ', how can I help?';
    teaser.querySelector('[data-kc-teaser-lead]').textContent = plain;
    if (c.ready) tags.push(['delivered', c.ready + ' ready for pickup']);
    if (c.changed) tags.push(['transit', c.changed + ' update' + (c.changed > 1 ? 's' : '')]);
    if (c.moving) tags.push(['onway', c.moving + ' on the way']);
    if (c.overdue) tags.push(['overdue', c.overdue + ' overdue']);
    else if (c.owing) tags.push(['pending', c.owing + ' unpaid']);
    if (c.tickets) tags.push(['sorting', c.tickets + ' open request' + (c.tickets > 1 ? 's' : '')]);
    teaser.querySelector('[data-kc-teaser-tags]').innerHTML = tags.map(function (t) { return '<span class="sl-pill ' + t[0] + '">' + esc(t[1]) + '</span>'; }).join('');
  }
  function load() {
    if (st.loading) return st.loading;
    thread.insertAdjacentHTML('afterbegin', kingRow('<p class="kc-wait"><span class="kc-dots" aria-hidden="true"><i></i><i></i><i></i></span> Checking your account…</p>', 'loading', ''));
    st.loading = get({ action: 'brief' }).then(function (r) {
      var w = thread.querySelector('[data-id="loading"]'); if (w) w.remove();
      if (!r || !r.ok) {
        thread.insertAdjacentHTML('afterbegin', kingRow('<p>' + esc(hello() + ". I couldn't load your account just now, but I can still help. Ask me anything, or talk to our team.") + '</p>', 'brief', ''));
        chips([{ intent: 'human', label: 'Talk to a person' }]);
        st.loaded = true;
        return;
      }
      st.loaded = true; renderBrief(r);
      if (r.live && r.live.id) {
        // The conversation so far: the whole history (not only what came after the bubble's last peek).
        st.conv = r.live.id; st.last = 0; state(r.live);
        if (!st.conv) return;
        thread.insertAdjacentHTML('beforeend', '<div class="kc-day">Your conversation</div>');
        return pollNow(!st.open, 0);
      }
      if (st.conv) { // the bubble was following a chat that has ended since: show it, with how it ended
        thread.insertAdjacentHTML('beforeend', '<div class="kc-day">Your conversation</div>');
        return pollNow(!st.open, 0);
      }
    }).catch(function () {
      var w = thread.querySelector('[data-id="loading"]'); if (w) w.remove();
      st.loading = null;
      thread.insertAdjacentHTML('afterbegin', kingRow('<p>' + esc("I can't reach our system right now. Check your connection and try again in a moment.") + '</p>', 'brief', ''));
    });
    return st.loading;
  }

  /* ---------- polling: live while a person is involved; paused when the tab is hidden ---------- */
  function stop() { if (st.timer) { clearTimeout(st.timer); st.timer = null; } }
  function schedule() {
    stop();
    if (!st.conv || document.hidden || st.owner === 'resolved') return;
    var person = st.owner === 'handoff_queued' || st.owner === 'human_active';
    if (!st.open && !person) return;
    // King's answers come back with the send itself: while only King has the chat, a slow poll only catches a take-over.
    st.timer = setTimeout(function () { pollNow(!st.open); }, st.open ? (person ? 3500 : 30000) : 15000);
  }
  function pollNow(peek, from) {
    if (!st.conv) return Promise.resolve();
    var conv = st.conv;
    var params = { action: 'poll', conversation_id: conv, after: from == null ? st.last : from };
    if (peek) params.peek = 1; else if (st.last) params.read_to = st.last; // the chat is open: what it shows is read
    return get(params).then(function (r) {
      if (!r || !r.ok || conv !== st.conv) return;
      if (!st.loaded) {
        // The bubble only peeks (the chat was never opened on this page): count what is new, draw nothing yet.
        var fresh = (r.messages || []).filter(function (m) { return m.author_type !== 'customer' && m.id > st.last; }).length;
        (r.messages || []).forEach(function (m) { if (m.id > st.last) st.last = m.id; });
        if (fresh) { st.unread += fresh; badge(); }
        if (r.owner) { st.owner = r.owner; root.setAttribute('data-owner', st.owner); }
        return;
      }
      var news = (r.messages || []).filter(function (m) { return m.author_type !== 'customer' && !st.seen[m.id]; }).length;
      addList(r.messages);
      state(r);
      if (!st.busy) typing(!!r.typing && st.owner === 'human_active', st.ownerName || 'Customer care');
      if (peek && news && from == null) { st.unread += news; badge(); }
    }).catch(function () {}).then(schedule);
  }
  document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); else if (st.conv) pollNow(!st.open); });

  /* ---------- sending ---------- */
  function ensureConv() {
    if (st.conv) return Promise.resolve(st.conv);
    return post({ action: 'start' }).then(function (r) {
      if (!r || !r.ok) throw r;
      st.conv = r.conversation_id;
      (r.messages || []).forEach(function (m) { st.seen[m.id] = true; if (m.id > st.last) st.last = m.id; });
      state(r);
      return st.conv;
    });
  }
  function optimistic(body, files) {
    var tmp = 'tmp-' + uid();
    add({ id: tmp, author_type: 'customer', body: body, created_at: '', attachments: files || [] });
    chips(null); toBottom();
    return thread.querySelector('[data-id="' + tmp + '"]');
  }
  function failText(r) {
    if (r && r.error) return r.error;
    if (r && r.message && !/fetch|network|load failed/i.test(r.message)) return r.message;
    return 'Not sent: check your connection';
  }
  function failed(el, r, retry) {
    if (!el) return fail(r);
    el.classList.add('bad');
    var m = el.querySelector('[data-kc-meta]'); m.textContent = failText(r) + ' · ';
    var b = document.createElement('button'); b.type = 'button'; b.className = 'kc-retry'; b.textContent = 'Try again';
    b.addEventListener('click', function () { el.remove(); retry(); }); m.appendChild(b);
  }
  function after(r, el) {
    if (!r || !r.ok) throw r;
    if (el) el.remove();
    // The server started a new chat (the old one was closed by the team): follow it.
    if (r.conversation_id && r.conversation_id !== st.conv) st.conv = r.conversation_id;
    state(r); addList(r.messages);
  }
  function busy(on) { st.busy = on; q('[data-kc-send]').disabled = on && !text.value.trim(); root.classList.toggle('kc-busy', on); }
  function next() { // a message typed while King was answering goes out now
    var n = st.outbox.shift(); if (n) sendNow(n.body, n.cid, n.el, n.files);
  }
  function send(body) {
    body = String(body || '').trim();
    var files = st.files.slice();
    if (!body && !files.length) return;
    text.value = ''; grow(); st.files = []; drawFiles();
    var cid = uid(), el = optimistic(body, files);
    if (st.busy) { // King is still answering: keep it, send it right after
      var meta = el && el.querySelector('[data-kc-meta]'); if (meta) meta.textContent = 'Waiting to send…';
      st.outbox.push({ body: body, cid: cid, el: el, files: files }); return;
    }
    sendNow(body, cid, el, files);
  }
  function sendNow(body, cid, el, files) {
    busy(true);
    var meta = el && el.querySelector('[data-kc-meta]'); if (meta) meta.textContent = 'Sending…';
    if (st.owner === 'ai_active') setTimeout(function () { if (st.busy) typing(true, KING); }, 350);
    ensureConv().then(function () { return post({ action: 'send', conversation_id: st.conv, message: body, client_msg_id: cid, after: st.last, files: (files || []).map(function (f) { return f.token; }) }); })
      .then(function (r) { after(r, el); })
      .catch(function (r) { failed(el, r, function () { var e2 = optimistic(body, files); if (st.busy) st.outbox.push({ body: body, cid: cid, el: e2, files: files }); else sendNow(body, cid, e2, files); }); })
      .then(function () { busy(false); typing(false); schedule(); next(); });
  }
  function quick(intent, label, cid) {
    if (st.busy) return;
    if (intent === 'human') return human(label);
    cid = cid || uid();
    var el = optimistic(label); busy(true);
    setTimeout(function () { if (st.busy) typing(true, KING); }, 250);
    ensureConv().then(function () { return post({ action: 'quick', conversation_id: st.conv, intent: intent, client_msg_id: cid, after: st.last }); })
      .then(function (r) { after(r, el); })
      .catch(function (r) { failed(el, r, function () { quick(intent, label, cid); }); })
      .then(function () { busy(false); typing(false); schedule(); next(); });
  }
  function human(label) {
    if (st.busy || st.owner === 'handoff_queued' || st.owner === 'human_active') return;
    var cid = uid(), el = optimistic(label || 'Talk to a person'); busy(true);
    ensureConv().then(function () { return post({ action: 'quick', conversation_id: st.conv, intent: 'human', client_msg_id: cid, after: st.last }); })
      .then(function (r) { after(r, el); })
      .catch(function (r) { failed(el, r, function () { human(label); }); })
      .then(function () { busy(false); schedule(); next(); });
  }
  function grow() { text.style.height = 'auto'; text.style.height = Math.min(132, text.scrollHeight) + 'px'; }

  /* ---------- attachments: a photo of a damaged box, a receipt ---------- */
  function drawFiles() {
    if (!filesBox) return;
    filesBox.innerHTML = st.files.map(function (f, i) {
      return '<span class="kc-file">' + icon(/^image\//.test(f.mime || '') ? 'i-image' : 'i-paperclip') + '<span>' + esc(f.name) + '</span><button type="button" data-kc-rm="' + i + '" aria-label="Remove ' + esc(f.name) + '">×</button></span>';
    }).join('');
    filesBox.hidden = !st.files.length;
    q('[data-kc-send]').disabled = st.busy && !text.value.trim();
  }
  function upload(file) {
    if (file.size > 8 * 1024 * 1024) { SL.toast('Files can be up to 8 MB.', true); return; }
    filesBox.hidden = false;
    filesBox.insertAdjacentHTML('beforeend', '<span class="kc-file up" data-kc-up>' + icon('i-paperclip') + '<span>Uploading ' + esc(file.name) + '…</span></span>');
    var fd = new FormData(); fd.append('action', 'upload'); fd.append('via', 'king'); fd.append('file', file);
    SL.post(URL_, fd).then(function (r) { if (r && r.ok && r.file) st.files.push(r.file); else fail(r); }).catch(function (r) { SL.toast(failText(r) + '.', true); }).then(drawFiles);
  }
  if (fileIn) {
    q('[data-kc-attach]').addEventListener('click', function () { fileIn.click(); });
    fileIn.addEventListener('change', function () { Array.prototype.slice.call(fileIn.files || [], 0, 4).forEach(upload); fileIn.value = ''; });
    filesBox.addEventListener('click', function (e) { var b = e.target.closest('[data-kc-rm]'); if (b) { st.files.splice(+b.getAttribute('data-kc-rm'), 1); drawFiles(); } });
  }

  /* ---------- open / close ---------- */
  function phone() { return phoneQ.matches; }
  function sheet() { return !INLINE || phone(); } // a floating or full-screen sheet (not the dashboard panel on desktop)
  function badge() {
    root.querySelectorAll('[data-kc-badge]').forEach(function (b) { b.hidden = !st.unread; b.textContent = st.unread > 9 ? '9+' : String(st.unread || ''); b.setAttribute('aria-label', st.unread + ' unread'); });
  }
  /* While the chat is a sheet (the dock, or full screen on phones) it is a modal dialog: Tab stays inside it. */
  function modal(on) {
    if (on) { panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-modal', 'true'); }
    else { panel.removeAttribute('aria-modal'); panel.setAttribute('role', INLINE ? 'region' : 'dialog'); }
  }
  function focusables() {
    return Array.prototype.filter.call(panel.querySelectorAll('a[href],button:not([disabled]),textarea,input:not([type="file"]),select,[tabindex]:not([tabindex="-1"])'), function (el) {
      return !el.closest('[hidden]') && el.offsetParent !== null;
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab' || !st.open || !sheet()) return;
    var f = focusables(); if (!f.length) return;
    var a = f[0], z = f[f.length - 1], cur = document.activeElement;
    if (!panel.contains(cur)) { e.preventDefault(); (e.shiftKey ? z : a).focus(); return; }
    if (e.shiftKey && cur === a) { e.preventDefault(); z.focus(); }
    else if (!e.shiftKey && cur === z) { e.preventDefault(); a.focus(); }
  });
  var opener = null;
  function open(how) {
    if (st.open && !(INLINE && !sheet() && root.getAttribute('data-min') === '1')) { if (how !== 'auto') text.focus(); return; }
    st.open = true; opener = document.activeElement;
    root.setAttribute('data-open', '1'); root.removeAttribute('data-min'); panel.hidden = false;
    [launch, teaser].forEach(function (b) { if (b) b.setAttribute('aria-expanded', 'true'); });
    if (sheet() && phone()) document.documentElement.classList.add('kc-lock');
    modal(sheet());
    q('[data-kc-min]').hidden = true;
    if (!INLINE || phone()) store.set('open', '1');
    if (INLINE && !phone()) store.set('min', '0');
    st.unread = 0; badge();
    if (!INLINE) root.classList.add('kc-dim');
    var ready = st.loaded ? Promise.resolve() : load();
    ready.then(function () { if (st.conv) pollNow(false); });
    if (sheet()) setTimeout(function () { (how !== 'auto' && !phone() ? text : q('[data-kc-close]')).focus({ preventScroll: true }); }, 60);
    else if (how !== 'auto') setTimeout(function () { text.focus(); }, 60);
    toBottom();
    return ready;
  }
  function close() {
    stop(); store.set('auto', '1'); // closed by the customer: no automatic greeting again this session
    modal(false);
    if (INLINE && !phone()) { // the dashboard panel folds to one line
      st.open = false; root.setAttribute('data-min', '1'); q('[data-kc-min]').hidden = false; store.set('min', '1');
      schedule(); return;
    }
    st.open = false; root.setAttribute('data-open', '0'); document.documentElement.classList.remove('kc-lock'); root.classList.remove('kc-dim');
    if (!INLINE) panel.hidden = true;
    [launch, teaser].forEach(function (b) { if (b) b.setAttribute('aria-expanded', 'false'); });
    store.set('open', '0');
    typing(false); schedule();
    if (opener && opener.focus && document.contains(opener) && opener !== document.body) opener.focus(); else if (launch) launch.focus(); else if (teaser) teaser.focus();
  }
  /* The icon first pops up the questions customers ask most over a blurred page; a question opens the chat and asks it. */
  var pop = q('[data-kc-pop]'), scrim = q('[data-kc-scrim]');
  function popOpen() { return !!pop && !pop.hidden; }
  function popShow(on) {
    if (!pop) return;
    pop.hidden = !on; root.classList.toggle('kc-dim', on || st.open);
    if (launch) launch.setAttribute('aria-expanded', on || st.open ? 'true' : 'false');
    if (on) setTimeout(function () { var b = pop.querySelector('button'); if (b) b.focus(); }, 40);
  }
  if (launch) launch.addEventListener('click', function () {
    if (st.open) return close();
    if (popOpen()) return popShow(false);
    // a person on the chat, or messages waiting: straight to the chat
    if (!pop || st.unread || st.owner === 'handoff_queued' || st.owner === 'human_active') return open();
    popShow(true);
  });
  if (pop) pop.addEventListener('click', function (e) {
    var a = e.target.closest('[data-kc-ask]'), m = e.target.closest('[data-kc-other]');
    if (!a && !m) return;
    popShow(false);
    var ready = open();
    if (a) {
      var intent = a.getAttribute('data-kc-ask'), label = a.textContent.trim();
      Promise.resolve(ready).then(function () { setTimeout(function () { if (st.busy) return; if (intent === 'human') human(label); else quick(intent, label); }, 150); });
    }
  });
  if (scrim) scrim.addEventListener('click', function () { if (popOpen()) popShow(false); else if (st.open) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && popOpen()) { e.stopPropagation(); popShow(false); if (launch) launch.focus(); } }, true);
  if (teaser) teaser.addEventListener('click', function () { open(); });
  q('[data-kc-close]').addEventListener('click', close);
  q('[data-kc-expand]').addEventListener('click', function () { open(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && st.open && sheet()) { e.stopPropagation(); close(); } }, true);
  phoneQ.addEventListener && phoneQ.addEventListener('change', function () { if (st.open && sheet() && phone()) document.documentElement.classList.add('kc-lock'); else document.documentElement.classList.remove('kc-lock'); modal(st.open && sheet()); place(); header(); });

  /* ---------- events ---------- */
  q('[data-kc-form]').addEventListener('submit', function (e) { e.preventDefault(); send(text.value); });
  text.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(text.value); } });
  text.addEventListener('input', function () { grow(); q('[data-kc-send]').disabled = st.busy && !text.value.trim(); });
  q('[data-kc-human]').addEventListener('click', function () { human('Talk to a person'); });
  chipsBox.addEventListener('click', function (e) { var b = e.target.closest('[data-kc-intent]'); if (b) quick(b.getAttribute('data-kc-intent'), b.textContent.trim()); });
  thread.addEventListener('click', function (e) {
    var all = e.target.closest('[data-kc-show-all]');
    if (all) {
      var box = all.closest('.kc-bub').querySelector('[data-kc-extra]'); if (box) box.hidden = false;
      var more = all.closest('[data-kc-more]'); if (more) more.remove();
      return;
    }
    var b = e.target.closest('[data-kc-code]');
    if (b) {
      var ref = b.getAttribute('data-kc-code'), code = st.codes[ref];
      if (!code) { location.href = safeUrl(CFG.codes) || 'pickup_codes.php'; return; }
      var box = document.createElement('div'); box.className = 'kc-code';
      box.innerHTML = '<span class="kc-code-n mono" aria-label="Pickup code ' + esc(code.split('').join(' ')) + '">' + esc(code.replace(/^(\d{3})(\d+)$/, '$1 $2')) + '</span><small>Show this code at our office to collect ' + esc(ref) + '. Keep it to yourself: whoever has it can collect the parcel.</small>';
      b.replaceWith(box); return;
    }
    if (e.target.closest('[data-kc-act="human"]')) { human('Talk to a person'); return; }
    var ib = e.target.closest('[data-kc-intent]');
    if (ib) { var lab = { pickup: 'Pickup hours and address', where: 'Where is my package?', eta: 'When will it arrive?', owe: 'How much do I owe?', myaddress: 'My US address' }; quick(ib.getAttribute('data-kc-intent'), lab[ib.getAttribute('data-kc-intent')] || ib.textContent.trim()); return; }
    var rb = e.target.closest('[data-kc-rate]');
    if (rb) { rateCard(+rb.getAttribute('data-kc-rate'), ''); rb.disabled = true; return; }
    var star = e.target.closest('[data-kc-star]');
    if (star) {
      var box2 = star.closest('[data-kc-rate-box]'), n = +star.getAttribute('data-kc-star');
      box2.setAttribute('data-score', n);
      box2.querySelectorAll('[data-kc-star]').forEach(function (s) { var on = +s.getAttribute('data-kc-star') <= n; s.classList.toggle('on', on); s.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      return;
    }
    var rs = e.target.closest('[data-kc-rate-send]');
    if (rs) {
      var box3 = rs.closest('[data-kc-rate-box]'), score = +box3.getAttribute('data-score');
      if (!score) { SL.toast('Choose 1 to 5 stars.', true); return; }
      rs.disabled = true;
      post({ action: 'rate', id: box3.getAttribute('data-kc-rate-box'), score: score, comment: box3.querySelector('textarea').value }).then(function (r) {
        // Rated already, or the ticket was reopened meanwhile (another tab): the card simply goes away.
        if (!r || !r.ok) { box3.remove(); return; }
        box3.innerHTML = '<b>Thank you' + (CFG.first ? ', ' + esc(CFG.first) : '') + '!</b><span>Your rating helps our team get better.</span>';
      }).catch(function (r) { rs.disabled = false; SL.toast(failText(r) + '.', true); });
    }
  });

  /* ---------- phones: the bubble never sits on a page's own bottom bar, primary button or app-update bar ---------- */
  var spacer = null;
  function place() {
    if (!launch) return;
    if (!phone()) { root.style.removeProperty('--kc-bottom'); root.classList.remove('kc-wide'); return; }
    var nav = document.querySelector('.sl-mobile-nav'), navH = nav && getComputedStyle(nav).display !== 'none' ? nav.getBoundingClientRect().height : 0, lift = navH;
    document.querySelectorAll('.sl-mobile-sticky, .pwa-checkout-bar, [data-sl-sticky-bottom], .pwa-install, .pwa-update, .pwa-banner, [data-pwa-install], [data-pwa-update], #pwa-update, #pwa-install').forEach(function (el) {
      var cs = getComputedStyle(el); if (cs.display === 'none' || (cs.position !== 'fixed' && cs.position !== 'sticky')) return;
      var r = el.getBoundingClientRect(); if (r.height && r.top > innerHeight * 0.5 && r.top < innerHeight) lift = Math.max(lift, innerHeight - r.top);
    });
    root.style.setProperty('--kc-bottom', Math.round(lift + 12) + 'px');
    // A page wider than the screen makes "right" the far edge of that page: keep the bubble on the visible screen.
    // Fixed elements then follow a layout viewport larger than the screen, so the bubble is placed on the visual viewport.
    var vv = window.visualViewport, vw = (vv && vv.width) || document.documentElement.clientWidth;
    var wide = !!vv && document.documentElement.scrollWidth > vw + 2;
    root.classList.toggle('kc-wide', wide);
    if (wide) {
      root.style.setProperty('--kc-left', Math.max(8, Math.round(vv.offsetLeft + vw - 72)) + 'px');
      root.style.setProperty('--kc-top', Math.max(8, Math.round(vv.offsetTop + vv.height - 70 - lift)) + 'px');
    }
    if (!spacer) { var main = document.querySelector('.sl-main'); if (main) { spacer = document.createElement('div'); spacer.className = 'kc-spacer'; spacer.setAttribute('aria-hidden', 'true'); main.appendChild(spacer); } }
  }
  /* While the page scrolls the bubble shrinks back, so it does not sit on links in the cards underneath. */
  var placeT; window.addEventListener('scroll', function () {
    if (phone() && launch) root.classList.add('kc-scrolling');
    clearTimeout(placeT); placeT = setTimeout(function () { root.classList.remove('kc-scrolling'); place(); }, 180);
  }, { passive: true });
  window.addEventListener('resize', place);
  if (window.visualViewport) { window.visualViewport.addEventListener('resize', place); window.visualViewport.addEventListener('scroll', function () { if (root.classList.contains('kc-wide')) place(); }); }
  window.addEventListener('load', place);

  /* ---------- start ---------- */
  root.setAttribute('data-owner', st.owner); header(); badge(); place(); grow(); modal(false);
  if (INLINE) {
    // The dashboard greets once per session: open by itself the first time (the full-screen chat on phones,
    // the panel on desktop), then remember what the customer chose.
    // Remembered only once the greeting has been on screen a moment: a page that hands over straight away
    // (the phone app's redirect after sign-in) does not use up the one automatic greeting.
    var first = store.get('auto') !== '1';
    setTimeout(function () { store.set('auto', '1'); }, 1500);
    // Phones: never open full screen by itself (it covered the whole home page); the compact chat bar on the
    // dashboard shows King's briefing line and opens the chat when tapped.
    if (phone()) { load(); }
    else if (store.get('min') === '1') { load(); st.open = true; close(); }
    else open('auto');
  } else if (store.get('open') === '1' && !phone()) {
    open('auto'); // the chat stays open from page to page on desktop
  } else if (live && (live.owner === 'handoff_queued' || live.owner === 'human_active')) {
    // A person may answer while the chat is closed: the bubble shows it. Only what comes after the newest
    // message the server already counted is new.
    st.conv = live.id; st.last = live.last_id || 0; schedule();
  }
})();
