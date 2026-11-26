"use strict";

// ============================================================================
// WhatsApp Inbox (views/sl/pages/CommsInbox.php): conversation list with
// filters and search, one conversation with actions and the reply box.
// Data: ajax/comms/comms_ajax.php (threads, thread, reply, thread_update).
// Writes are POST; dataJs/ops_common.js adds the CSRF token to every POST.
//
// Polling (every 10 s, paused while the tab is hidden) never touches the reply
// box: the header and the actions are redrawn in place, the messages only when
// they changed (server "sig"), and the list only when its rows changed. A list
// request never runs twice at the same time.
// ============================================================================

(function ($) {
    var OPS = window.OPS, esc = OPS.esc, URL = 'ajax/comms/comms_ajax.php';
    var wrap = document.getElementById('ib_wrap');
    if (!wrap) { return; }
    var KING = wrap.getAttribute('data-king') || 'King';
    var FILTERS = ['all', 'open', 'mine', 'bot', 'closed'];
    var EMPTY = { all: 'No conversations yet.', open: 'Nobody is waiting for a person.', mine: 'No open conversations are assigned to you.',
        bot: 'No conversations are with ' + KING + ' right now.', closed: 'No closed conversations.' };

    var st = { filter: 'all', q: '', current: 0, sig: '', listKey: '', rows: [], listBusy: false, listAgain: false, threadBusy: false, sending: false, thread: null, me: 0, pushed: false };
    // Phones have no Shift+Enter: there Enter is a new line and the button sends.
    var TOUCH = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
    var PHONE = function () { return window.matchMedia('(max-width: 900px)').matches; };

    // ------------------------------------------------------------ helpers
    function store(k, v) { try { if (v === undefined) { return window.localStorage.getItem(k); } window.localStorage.setItem(k, v); } catch (e) { return null; } return null; }
    function nameOf(t) { return (t.contact_name || t.customer_name || '').trim() || ('+' + t.phone); }
    function initials(t) {
        var n = (t.contact_name || t.customer_name || '').trim();
        if (!n) { return '#'; }
        var p = n.split(/\s+/);
        return (p[0].charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase();
    }
    function parseTs(s) { var m = String(s || '').match(/^(\d{4})-(\d\d)-(\d\d)[ T](\d\d):(\d\d)/); return m ? new Date(+m[1], m[2] - 1, +m[3], +m[4], +m[5]) : null; }
    function sameDay(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }
    function hm(d) { return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
    function shortTime(s) {
        var d = parseTs(s); if (!d) { return ''; }
        var now = new Date(), y = new Date(now.getTime() - 86400000);
        if (sameDay(d, now)) { return hm(d); }
        if (sameDay(d, y)) { return 'Yesterday'; }
        return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
    }
    function dayLabel(d) {
        var now = new Date(), y = new Date(now.getTime() - 86400000);
        if (sameDay(d, now)) { return 'Today'; }
        if (sameDay(d, y)) { return 'Yesterday'; }
        return d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: d.getFullYear() === now.getFullYear() ? undefined : 'numeric' });
    }
    function pill(t) {
        if (t.status === 'closed') { return '<span class="sl-pill plain">Closed</span>'; }
        if (t.status === 'bot') { return '<span class="sl-pill transit">With ' + esc(KING) + '</span>'; }
        return '<span class="sl-pill onway">Waiting for a person</span>';
    }
    /** push=true adds a history entry (a conversation opened on a phone), so the device Back button returns to the list. */
    function setHash(push) {
        var h = '#f=' + st.filter + (st.current ? '&c=' + st.current : '');
        try { window.history[push ? 'pushState' : 'replaceState'](null, '', h); } catch (e) { /* ignore */ }
    }
    /** Size the two panes to the space really left below the page head, so the reply box is on screen. */
    function fit() {
        var top = wrap.getBoundingClientRect().top + (window.scrollY || window.pageYOffset || 0);
        // a fixed bottom bar (the phone tab bar) covers the end of the page
        var nav = document.querySelector('.sl-mobile-nav'), cover = 0;
        if (nav && nav.offsetHeight && getComputedStyle(nav).position === 'fixed') { cover = Math.max(0, window.innerHeight - nav.getBoundingClientRect().top); }
        var h = Math.round(window.innerHeight - top - cover - 12);
        if (h > 0) { wrap.style.setProperty('--ib-h', h + 'px'); }
    }
    function readHash() {
        var o = {};
        String(window.location.hash || '').replace(/^#/, '').split('&').forEach(function (p) { var kv = p.split('='); if (kv[0]) { o[kv[0]] = decodeURIComponent(kv[1] || ''); } });
        return o;
    }
    function waitingBadges(n) {
        // the Communications tab and the sidebar item show the same figure
        document.querySelectorAll('.cm-tabs a[href*="comms_inbox.php"]').forEach(function (a) {
            var em = a.querySelector('em');
            if (n > 0) { if (!em) { em = document.createElement('em'); em.setAttribute('data-cm-waiting', ''); em.title = 'Waiting for a person'; a.appendChild(em); } em.textContent = n; } else if (em) { em.remove(); }
        });
        document.querySelectorAll('a[data-shortcut-id="commsinbox"]').forEach(function (a) {
            var c = a.querySelector('.cnt');
            if (n > 0) {
                if (!c) { c = document.createElement('span'); c.className = 'cnt'; a.appendChild(c); }
                c.textContent = String(n);
            } else if (c) { c.remove(); }
        });
    }

    /** The filter chips scroll sideways in one row: fade the edge that has more chips behind it. */
    function filterEdges() {
        var bar = document.querySelector('.ib-filters'); if (!bar) { return; }
        bar.classList.toggle('more-l', bar.scrollLeft > 2);
        bar.classList.toggle('more-r', bar.scrollLeft + bar.clientWidth < bar.scrollWidth - 2);
    }

    // ------------------------------------------------------------ list
    function setFilter(f, user) {
        st.filter = FILTERS.indexOf(f) >= 0 ? f : 'all';
        $('[data-ib-filter]').each(function () { this.setAttribute('aria-pressed', this.getAttribute('data-ib-filter') === st.filter ? 'true' : 'false'); });
        var on = document.querySelector('[data-ib-filter="' + st.filter + '"]');
        if (on && on.parentNode.scrollWidth > on.parentNode.clientWidth) {
            var bar = on.parentNode, l = on.offsetLeft - bar.offsetLeft;
            if (l < bar.scrollLeft || l + on.offsetWidth > bar.scrollLeft + bar.clientWidth) { bar.scrollLeft = Math.max(0, l - 24); }
        }
        filterEdges();
        if (user) { store('sl.ib.filter', st.filter); }
        setHash();
    }

    function list() {
        if (st.listBusy) { st.listAgain = true; return; }
        st.listBusy = true;
        var filter = st.filter, q = st.q;
        OPS.get(URL, { action: 'threads', status: filter === 'all' ? '' : filter, q: q }).then(function (r) {
            if (filter !== st.filter || q !== st.q) { return; } // a newer choice is on its way
            st.me = Number(r.me || 0);
            FILTERS.forEach(function (k) { $('[data-ib-count="' + k + '"]').text(r.counts[k]); });
            if (!q) { waitingBadges(r.counts.open); }
            st.rows = r.rows;
            st.total = Number(r.counts[filter] || 0);
            drawList();
        }).always(function () {
            st.listBusy = false;
            if (st.listAgain) { st.listAgain = false; list(); }
        });
    }

    function drawList() {
        var key = JSON.stringify([st.current, st.me, st.total, st.rows]);
        if (key === st.listKey) { return; }
        st.listKey = key;
        var me = Number(st.me || 0);
        var more = st.total > st.rows.length && st.rows.length ? '<p class="ib-more">Showing the latest ' + st.rows.length + ' of ' + st.total + '. Search to narrow the list.</p>' : '';
        $('#ib_threads').html(st.rows.map(function (t) {
            var who = t.assignee_id ? (t.assignee_id === me ? 'You' : (t.assignee_name || 'Staff')) : '';
            return '<button type="button" class="ib-row' + (t.id === st.current ? ' on' : '') + (t.unread ? ' unread' : '') + '" data-id="' + t.id + '"'
                + (t.id === st.current ? ' aria-current="true"' : '') + '>'
                + '<span class="ib-av" aria-hidden="true">' + esc(initials(t)) + '</span><span class="ib-rb">'
                + '<span class="ib-r1"><b>' + esc(nameOf(t)) + '</b>' + (t.locker ? '<span class="lk">' + esc(t.locker) + '</span>' : '') + '<time>' + esc(shortTime(t.updated_at)) + '</time></span>'
                + '<span class="ib-last">' + esc(t.last_message || '') + '</span>'
                + '<span class="ib-r3">' + pill(t) + (who ? '<span class="who">· ' + esc(who) + '</span>' : '')
                + (t.unread ? '<span class="ib-un" aria-label="' + t.unread + ' unread">' + t.unread + '</span>' : '') + '</span>'
                + '</span></button>';
        }).join('') + more || '<p class="ib-none">' + esc(st.q ? 'No conversations match "' + st.q + '".' : EMPTY[st.filter]) + (st.filter !== 'all' ? ' <button type="button" class="sl-btn ghost sm" data-ib-filter-go="all">Show all</button>' : '') + '</p>');
    }

    // ------------------------------------------------------------ conversation
    function headHtml(r) {
        var t = r.thread, me = Number(r.me), mine = Number(t.assignee_id) === me, closed = t.status === 'closed', bot = Number(t.bot_active) === 1;
        var meta = ['+' + esc(t.phone)];
        if (Number(t.user_id)) {
            var cust = esc((t.customer_name || '').trim() || 'Customer') + (t.locker ? ' · ' + esc(t.locker) : '');
            meta.push(r.customer_url ? '<a href="' + esc(r.customer_url) + '">' + cust + '</a>' : cust);
        } else {
            meta.push('Not linked to a customer');
        }
        meta.push(t.assignee_id ? 'Assigned to ' + (mine ? 'you' : esc(r.assignee || 'staff')) : 'Not assigned');
        var acts = '';
        // A closed conversation only offers Reopen (and Unassign): nothing else reopens it implicitly.
        if (!closed && !mine) { acts += '<button type="button" class="sl-btn ghost sm" data-ib-op="assign_me">Assign to me</button>'; }
        if (Number(t.assignee_id)) { acts += '<button type="button" class="sl-btn ghost sm" data-ib-op="unassign">Unassign</button>'; }
        if (!closed) {
            acts += bot ? '<button type="button" class="sl-btn ghost sm" data-ib-op="bot_off">Stop ' + esc(KING) + '</button>'
                : '<button type="button" class="sl-btn ghost sm" data-ib-op="bot_on">Hand back to ' + esc(KING) + '</button>';
        }
        acts += closed ? '<button type="button" class="sl-btn sm" data-ib-op="reopen">Reopen</button>' : '<button type="button" class="sl-btn ghost sm" data-ib-op="close">Close</button>';
        if (r.customer_url) { acts += '<a class="sl-btn ghost sm" href="' + esc(r.customer_url) + '">Customer page</a>'; }
        else if (r.history_url) { acts += '<a class="sl-btn ghost sm" href="' + esc(r.history_url) + '" title="Search the customer in Communication Center › Customer history">History</a>'; }
        return '<div class="ib-ch1"><button type="button" class="sl-ib ib-back" data-ib-back aria-label="Back to conversations"><i class="ic ic-sm i-left" aria-hidden="true"></i></button>'
            + '<span class="ib-av" aria-hidden="true">' + esc(initials(t)) + '</span>'
            + '<div class="col grow" style="min-width:0;gap:4px"><div class="row g8 wrap"><h2>' + esc(nameOf(t)) + '</h2>' + pill(t) + '</div>'
            + '<div class="ib-meta">' + meta.map(function (m) { return '<span>' + m + '</span>'; }).join('') + '</div></div></div>'
            + (t.handoff_reason && t.status === 'open' ? '<div class="ib-hand">Handed to a person: ' + esc(t.handoff_reason) + '</div>' : '')
            + '<div class="ib-acts">' + acts + '</div>';
    }

    function msgsHtml(r) {
        var last = null, out = '';
        r.messages.forEach(function (m) {
            var d = parseTs(m.created_at);
            if (d && (!last || !sameDay(d, last))) { out += '<div class="ib-day">' + esc(dayLabel(d)) + '</div>'; }
            last = d || last;
            var inbound = m.direction === 'in';
            var cls = inbound ? 'in' : (m.author_type === 'bot' ? 'out bot' : (m.author_type === 'agent' ? 'out' : 'out sys'));
            var who = inbound ? nameOf(r.thread) : (m.author_type === 'bot' ? KING : (m.author_type === 'agent' ? (m.author_name || 'Staff') : 'Automatic message'));
            var failed = m.status === 'failed';
            out += '<div class="ib-m ' + cls + (failed ? ' failed' : '') + '"><div class="bb">' + esc(m.body) + '</div>'
                + '<div class="mt"><span>' + esc(who) + '</span><span>' + (d ? esc(hm(d)) : '') + '</span>' + (failed ? '<span class="ib-nd">Not delivered</span>' : '') + '</div></div>';
        });
        return out || '<p class="ib-none">No messages in this conversation.</p>';
    }

    function footHtml(r) {
        var t = r.thread;
        if (t.status === 'closed') {
            return '<div class="ib-closed"><span>This conversation is closed. Reopen it to reply; a new message from the customer reopens it too.</span><button type="button" class="sl-btn sm" data-ib-op="reopen">Reopen</button></div>';
        }
        var winClosed = r.provider === 'cloud' && !r.window_open;
        return (winClosed ? '<div class="sl-banner">The customer has not written in the last 24 hours, so WhatsApp only accepts approved templates. Send one from Communication Center › Send message.</div>' : '')
            + '<label class="sr-only" for="ib_body">Reply</label>'
            + '<textarea id="ib_body" class="sl-in" rows="2" maxlength="4000" placeholder="Write a reply…"' + (winClosed ? ' disabled' : '') + '></textarea>'
            + '<div class="ib-err" id="ib_err" hidden></div>'
            + '<div class="ib-fr"><button type="button" class="sl-btn" id="ib_send"' + (winClosed ? ' disabled' : '') + '><i class="ic ic-sm i-send" aria-hidden="true"></i>Send</button>'
            + '<span class="mut" id="ib_hint">' + hintText(t) + '</span></div>';
    }
    function hintText(t) {
        return esc((TOUCH ? '' : 'Enter sends, Shift+Enter starts a new line.') + (Number(t.bot_active) ? ' Replying takes the chat over from ' + KING + '.' : '')).trim();
    }

    function nearBottom(el) { return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 80; }

    /** Draw a conversation. full=true (first open) builds the whole pane; otherwise only what changed. */
    function draw(r, full) {
        var $d = $('#ib_detail');
        var prev = st.thread;
        st.thread = r.thread;
        if (full || !$d.find('.ib-ch').length) {
            st.headHtml = headHtml(r);
            $d.html('<header class="ib-ch">' + st.headHtml + '</header><div class="ib-msgs" id="ib_chat" aria-live="polite">' + msgsHtml(r) + '</div><footer class="ib-foot" id="ib_foot">' + footHtml(r) + '</footer>');
            st.sig = r.sig;
            var c = document.getElementById('ib_chat'); if (c) { c.scrollTop = c.scrollHeight; }
            return;
        }
        var hh = headHtml(r);
        if (hh !== st.headHtml) { $d.find('.ib-ch').html(hh); st.headHtml = hh; } // untouched when nothing changed, so a click is never lost
        if (r.sig !== st.sig) {
            var chat = document.getElementById('ib_chat'), stick = nearBottom(chat);
            $(chat).html(msgsHtml(r));
            st.sig = r.sig;
            if (stick && chat) { chat.scrollTop = chat.scrollHeight; }
        }
        // The reply box is rebuilt only when what it offers changes (closed / 24-hour window), never on a plain poll.
        var footKey = function (t, x) { return t ? (t.status === 'closed') + '|' + (x.provider === 'cloud' && !x.window_open) : ''; };
        var key = footKey(r.thread, r);
        if (key !== st.footKey) {
            var keep = $('#ib_body').val();
            $('#ib_foot').html(footHtml(r));
            if (keep) { $('#ib_body').val(keep); }
        }
        st.footKey = key;
        // The hint follows King's state without touching the reply box.
        var hint = document.getElementById('ib_hint');
        if (hint && hint.innerHTML !== hintText(r.thread)) { hint.innerHTML = hintText(r.thread); }
        return prev;
    }

    function open(id, opts) {
        opts = opts || {};
        id = Number(id);
        var switching = id !== st.current;
        if (st.threadBusy && !switching && !opts.force) { return $.Deferred().resolve().promise(); }
        var fromList = !st.current;
        st.current = id;
        st.threadBusy = true;
        if (switching) { st.sig = ''; st.footKey = undefined; }
        wrap.classList.add('open');
        var page = wrap.closest('.ib-page'); if (page) { page.classList.add('conv-open'); }
        if (switching && PHONE()) { window.scrollTo(0, 0); }
        if (switching) { fit(); }
        // On a phone the conversation replaces the list: give the device Back button an entry to return to.
        var push = switching && fromList && PHONE() && !opts.fromHistory;
        if (push) { st.pushed = true; }
        setHash(push);
        if (switching) {
            st.listKey = ''; drawList();
            $('#ib_detail').html('<div class="ib-pick"><span class="mut">Loading conversation…</span></div>');
        }
        return OPS.get(URL, { action: 'thread', id: id }).then(function (r) {
            if (id !== st.current) { return; }
            st.me = r.me;
            draw(r, switching || opts.full);
            if (switching || opts.full) {
                st.footKey = (r.thread.status === 'closed') + '|' + (r.provider === 'cloud' && !r.window_open);
                // preventScroll: focusing the reply box must not scroll the page head away
                if (!opts.noFocus && !PHONE()) { var b = document.getElementById('ib_body'); if (b) { try { b.focus({ preventScroll: true }); } catch (e) { b.focus(); } } }
            }
            // Reading marks the conversation read: update its row now, then the list and counts, once.
            var row = st.rows.filter(function (x) { return x.id === id; })[0];
            var changed = row && (row.unread || row.status !== r.thread.status || Number(row.bot_active) !== Number(r.thread.bot_active)
                || Number(row.assignee_id) !== Number(r.thread.assignee_id || 0) || row.last_message !== String(r.thread.last_message || ''));
            if (row && row.unread) { row.unread = 0; st.listKey = ''; drawList(); }
            if (changed || opts.refreshList) { list(); }
        }, function () {
            if (id === st.current && switching) { close(); }
        }).always(function () { st.threadBusy = false; });
    }

    /** The in-page Back arrow: undo the history entry a phone open added, otherwise just close. */
    function back() {
        if (st.pushed) { st.pushed = false; try { window.history.back(); return; } catch (e) { /* fall through */ } }
        close();
    }

    function close() {
        st.pushed = false;
        st.current = 0; st.thread = null; st.sig = '';
        wrap.classList.remove('open');
        var page = wrap.closest('.ib-page'); if (page) { page.classList.remove('conv-open'); }
        $('#ib_detail').html('<div class="ib-pick"><span class="ib-empty-ic" aria-hidden="true"><i class="ic i-message"></i></span><b>Choose a conversation</b><span class="mut sm">Pick one on the left to read it and reply.</span></div>');
        st.listKey = ''; drawList(); setHash(); fit();
    }

    function send() {
        if (st.sending || !st.current) { return; }
        var $b = $('#ib_body'), text = String($b.val() || ''), id = st.current;
        if (!text.trim()) { $b.trigger('focus'); return; }
        st.sending = true;
        var $btn = $('#ib_send');
        $b.prop('disabled', true); $btn.prop('disabled', true).html('Sending…');
        $('#ib_err').prop('hidden', true).text('');
        OPS.post(URL + '?action=reply', { action: 'reply', id: id, body: text }).then(function (r) {
            if (r.ok) {
                if (st.current === id) { $('#ib_body').val(''); }
                OPS.toast('Sent');
            } else {
                // Keep the text so it can be sent again; the failed attempt shows as "Not delivered".
                $('#ib_err').prop('hidden', false).text((r.error || 'Not sent.') + ' Your text is still in the box: try again once WhatsApp is connected.');
            }
            return open(id, { force: true, noFocus: true, refreshList: true }).then(function () {
                var c = document.getElementById('ib_chat'); if (c) { c.scrollTop = c.scrollHeight; } // the error box shrank the messages: keep the newest in view
            });
        }, function (msg) {
            $('#ib_err').prop('hidden', false).text('Not sent: ' + (msg || 'the request failed') + '. Your text is still in the box.');
        }).always(function () {
            st.sending = false;
            $('#ib_body').prop('disabled', false);
            $('#ib_send').prop('disabled', false).html('<i class="ic ic-sm i-send" aria-hidden="true"></i>Send');
        });
    }

    var OP_DONE = { assign_me: 'Assigned to you', unassign: 'Unassigned', bot_on: 'Handed back to ' + KING, bot_off: KING + ' stopped: a person answers', close: 'Conversation closed', reopen: 'Conversation reopened' };
    function op(name, btn) {
        if (!st.current) { return; }
        var id = st.current;
        $(btn).prop('disabled', true);
        OPS.post(URL + '?action=thread_update', { action: 'thread_update', id: id, op: name }).then(function (r) {
            if (OPS.result(r, OP_DONE[name] || 'Updated')) { open(id, { force: true, noFocus: true, refreshList: true }); }
        }).always(function () { $(btn).prop('disabled', false); });
    }

    // ------------------------------------------------------------ events
    $(document).on('click', '[data-ib-filter]', function () { setFilter(this.getAttribute('data-ib-filter'), true); st.listKey = ''; $('#ib_threads').html('<p class="ib-none">Loading…</p>'); list(); });
    $(document).on('click', '[data-ib-filter-go]', function () { setFilter(this.getAttribute('data-ib-filter-go'), true); st.listKey = ''; list(); });
    $(document).on('input', '#ib_q', OPS.debounce(function () { st.q = String($('#ib_q').val() || '').trim(); st.listKey = ''; list(); }, 300));
    $(document).on('keydown', '#ib_q', function (ev) { if (ev.key === 'Escape' && this.value) { this.value = ''; st.q = ''; st.listKey = ''; list(); } });
    $(document).on('click', '.ib-row', function () { open($(this).data('id')); });
    $(document).on('click', '[data-ib-back]', function () { back(); var r = document.querySelector('.ib-row'); if (r) { r.focus(); } });
    $(document).on('click', '[data-ib-op]', function () { op(this.getAttribute('data-ib-op'), this); });
    $(document).on('click', '#ib_send', send);
    $(document).on('keydown', '#ib_body', function (ev) {
        if (!TOUCH && ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing && ev.keyCode !== 229) { ev.preventDefault(); send(); }
    });
    $(document).on('click', '[data-ib-copy]', function () {
        var el = document.getElementById(this.getAttribute('data-ib-copy')); if (!el) { return; }
        var done = function () { OPS.toast('Copied'); };
        if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(el.value).then(done, function () { el.select(); }); }
        else { el.select(); try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ } }
    });

    // ------------------------------------------------------------ start
    $(function () {
        var h = readHash(), saved = store('sl.ib.filter');
        var waiting = Number(wrap.getAttribute('data-waiting') || 0);
        // "Waiting for a person" only while somebody waits: a saved or linked 'open' falls back to All otherwise.
        var pick = function (f) { return FILTERS.indexOf(f) >= 0 && !(f === 'open' && waiting === 0) ? f : ''; };
        var f = pick(h.f) || pick(saved) || (waiting > 0 ? 'open' : 'all');
        setFilter(f, false);
        fit();
        window.addEventListener('resize', OPS.debounce(function () { fit(); filterEdges(); }, 150));
        var fb = document.querySelector('.ib-filters'); if (fb) { fb.addEventListener('scroll', filterEdges, { passive: true }); }
        if (Number(h.c) > 0) { open(Number(h.c), { noFocus: true, fromHistory: true, refreshList: true }); } else { list(); }
        function tick() {
            if (document.hidden) { return; }
            // One list request per tick: open() refreshes the list itself.
            if (st.current && !st.sending && !st.threadBusy) { open(st.current, { noFocus: true, refreshList: true }); } else { list(); }
        }
        setInterval(tick, 10000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { tick(); } });
        // A link or the Back button changing #f=…&c=… shows that filter / conversation.
        function fromHistory() {
            var x = readHash();
            if (FILTERS.indexOf(x.f) >= 0 && x.f !== st.filter) { setFilter(x.f, true); st.listKey = ''; list(); }
            var c = Number(x.c) || 0;
            if (c && c !== st.current) { open(c, { noFocus: true, fromHistory: true }); } else if (!c && st.current) { close(); }
        }
        window.addEventListener('hashchange', fromHistory);
        window.addEventListener('popstate', fromHistory);
    });
})(window.jQuery);
