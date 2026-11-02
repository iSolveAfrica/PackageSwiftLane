"use strict";

// ============================================================================
// Customer Service — the customer chat (Support Hub and the chat button).
//
// CS.Chat($root, opts) builds the chat inside $root:
//   opts.url        ajax/support/customer_ajax.php
//   opts.king       { on, name, greeting }
//   opts.entity     { type, id } the chat is about (checked on the server)
//   opts.onChange   called after anything that changes the chat list
//
// Every message carries a client id, so a retry after a dropped connection
// never stores it twice. New messages arrive by polling with back-off; the
// thread is an aria-live log so screen readers hear new replies.
// ============================================================================

(function ($) {
    var CS = window.CS;

    var QUICK = [
        ['solar:map-point-wave-linear', 'Track my shipment', 'Where is my shipment right now?'],
        ['solar:box-linear', 'Warehouse package', 'My package was delivered to your US warehouse but I cannot see it on my account.'],
        ['solar:document-text-linear', 'Invoice or payment', 'I have a question about an invoice or a payment.'],
        ['solar:delivery-linear', 'Delivery issue', 'I have a problem with a delivery or pickup.'],
        ['solar:shield-warning-linear', 'Lost or damaged', 'My package is lost or arrived damaged.']
    ];

    CS.Chat = function ($root, opts) {
        var self = this;
        opts = opts || {};
        var king = opts.king || { on: false, name: 'King' };
        var st = { conv: 0, last: 0, owner: king.on ? 'ai_active' : 'handoff_queued', ownerName: '', ticketRef: '', busy: false, files: [], timer: null, delay: 4000, seen: {} };

        $root.html(
            '<div class="swl-cs-chat" role="region" aria-label="Support chat">'
            + '<div class="swl-cs-chat-head">'
            + '<div class="swl-cs-avatar" aria-hidden="true">' + CS.esc((king.name || 'K').charAt(0)) + '</div>'
            + '<div class="swl-cs-chat-who"><div class="swl-cs-chat-name"></div><div class="swl-cs-chat-state" aria-live="polite"></div></div>'
            + (opts.newButton ? '<button type="button" class="btn btn-secondary btn-sm cs-new"><iconify-icon icon="solar:pen-new-square-linear"></iconify-icon> New chat</button>' : '')
            + (opts.closeButton ? '<button type="button" class="swl-cs-icon cs-close" aria-label="Close chat"><iconify-icon icon="solar:close-circle-linear"></iconify-icon></button>' : '')
            + '</div>'
            + '<div class="swl-cs-chat-scroll"><div class="cs-thread" role="log" aria-live="polite" aria-relevant="additions"></div><div class="cs-typing" hidden><div class="swl-cs-typing" aria-label="Typing"><span></span><span></span><span></span></div> <span class="swl-cs-small swl-cs-muted cs-typing-l"></span></div></div>'
            + '<div class="swl-cs-chat-foot">'
            + '<div class="swl-cs-quick cs-quick"></div>'
            + '<div class="swl-cs-pending-files cs-files"></div>'
            + '<form class="swl-cs-input cs-form" autocomplete="off">'
            + '<label class="swl-cs-icon" title="Attach a photo or PDF" aria-label="Attach a photo or PDF"><iconify-icon icon="solar:paperclip-linear"></iconify-icon><input type="file" class="cs-file" accept="image/*,application/pdf,text/plain" hidden></label>'
            + '<textarea rows="1" maxlength="4000" class="cs-text" aria-label="Your message" placeholder="Write a message…"></textarea>'
            + '<button type="submit" class="swl-cs-icon is-send cs-send" aria-label="Send"><iconify-icon icon="solar:arrow-up-linear"></iconify-icon></button>'
            + '</form>'
            + '<div class="swl-cs-chat-actions">'
            + '<button type="button" class="btn btn-secondary btn-sm cs-human"><iconify-icon icon="solar:user-speak-linear"></iconify-icon> Talk to a person</button>'
            + '<button type="button" class="swl-cs-link-btn cs-solved" hidden>This solved it</button>'
            + '</div>'
            + '<div class="swl-cs-disclose cs-disclose"></div>'
            + '</div></div>');

        var $thread = $root.find('.cs-thread'), $scroll = $root.find('.swl-cs-chat-scroll'), $text = $root.find('.cs-text');

        $root.find('.cs-quick').html(QUICK.map(function (q) {
            return '<button type="button" data-q="' + CS.esc(q[2]) + '"><iconify-icon icon="' + q[0] + '"></iconify-icon>' + CS.esc(q[1]) + '</button>';
        }).join(''));
        $root.on('click', '.cs-quick button', function () { send($(this).data('q')); });
        $root.find('.cs-form').on('submit', function (e) { e.preventDefault(); send($text.val()); });
        $text.on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send($text.val()); }
        }).on('input', function () { CS.grow(this); });
        $root.find('.cs-human').on('click', human);
        $root.find('.cs-solved').on('click', solved);
        $root.find('.cs-new').on('click', function () { self.startNew(); });
        $root.find('.cs-close').on('click', function () { if (opts.onClose) { opts.onClose(); } });
        $root.find('.cs-file').on('change', function () { var f = this.files[0]; this.value = ''; if (f) { upload(f); } });
        $root.on('click', '.cs-files button', function () { st.files.splice(+$(this).data('i'), 1); renderFiles(); });
        $root.on('click', '.cs-retry', function () {
            var $m = $(this).closest('.swl-cs-msg');
            $m.remove();
            sendRaw($m.data('text'), $m.data('cid'), $m.data('files') || []);
        });
        document.addEventListener('visibilitychange', function () { if (!document.hidden && st.conv) { schedule(300); } });

        header();

        // ── public ───────────────────────────────────────────────────────────
        this.open = function (convId) {
            stop();
            st.conv = convId;
            st.last = 0;
            st.seen = {};
            $thread.empty();
            fetch(true);
        };
        this.startNew = function () {
            stop();
            st.conv = 0;
            st.last = 0;
            st.seen = {};
            st.owner = king.on ? 'ai_active' : 'handoff_queued';
            st.ownerName = '';
            st.ticketRef = '';
            $thread.empty();
            welcome();
            header();
            $root.find('.cs-quick').show();
            $text.trigger('focus');
        };
        this.focus = function () { $text.trigger('focus'); };
        this.conversationId = function () { return st.conv; };

        // ── internals ────────────────────────────────────────────────────────
        function welcome() {
            if (king.on) {
                add({ id: 'w', author_type: 'king', author: king.name, body: king.greeting, created_at: '' });
            } else {
                add({ id: 'w', author_type: 'system', body: 'Tell us what you need and a member of our team will reply here.' + (opts.hours ? '\n\nOur support hours: ' + opts.hours : ''), created_at: '' });
            }
        }

        function header() {
            var s = st.owner, name = king.name || 'King', line = '';
            if (s === 'ai_active') { line = 'AI assistant · ask for a person any time'; }
            else if (s === 'handoff_queued') { name = 'Support team'; line = 'Waiting for a person · we reply here'; }
            else if (s === 'human_active') { name = st.ownerName ? st.ownerName + ' · Support team' : 'Support team'; line = 'You are chatting with a person'; }
            else { name = 'Support'; line = 'Resolved · write again to reopen'; }
            $root.find('.swl-cs-chat-name').text(name);
            $root.find('.swl-cs-chat-state').attr('class', 'swl-cs-chat-state is-' + s).text(line + (st.ticketRef ? ' · Ticket ' + st.ticketRef : ''));
            $root.find('.swl-cs-avatar').first().toggleClass('is-staff', s !== 'ai_active').html(s === 'ai_active' ? CS.esc(name.charAt(0)) : '<iconify-icon icon="solar:user-linear"></iconify-icon>');
            $root.find('.cs-human').prop('hidden', s === 'handoff_queued' || s === 'human_active');
            $root.find('.cs-disclose').text(s === 'ai_active'
                ? name + ' is an AI assistant. It reads your own account only and can make mistakes; a person checks anything it cannot answer.'
                : 'Never share passwords, one-time codes or card numbers in chat.');
        }

        function state(r) {
            if (!r) { return; }
            if (r.owner) { st.owner = r.owner; }
            st.ownerName = r.owner_name || '';
            st.ticketRef = r.ticket_ref || '';
            header();
        }

        function bubbleFor(m) {
            var type = m.author_type, mine = type === 'customer';
            var cls = 'swl-cs-msg' + (mine ? ' is-mine' : '') + (type === 'system' ? ' is-system' : '');
            var av = '';
            if (type === 'king') { av = '<div class="swl-cs-avatar" aria-hidden="true">' + CS.esc((m.author || 'K').charAt(0)) + '</div>'; }
            else if (type === 'staff') { av = '<div class="swl-cs-avatar is-staff" aria-hidden="true">' + CS.esc((m.author || 'S').charAt(0)) + '</div>'; }
            var body = m.body ? '<div class="swl-cs-md">' + CS.md(m.body) + '</div>' : '';
            var cards = '';
            if (m.meta && m.meta.cards && m.meta.cards.length) {
                cards = '<div class="swl-cs-cards">' + m.meta.cards.map(function (c) {
                    var link = CS.safeUrl(c.link);
                    return '<div class="swl-cs-card"><b>' + CS.esc(c.tracking) + '</b> · ' + CS.esc(c.status || 'Status not recorded')
                        + (c.payment ? ' · ' + CS.esc(c.payment) : '')
                        + (c.updated ? '<div class="swl-cs-muted">Last update ' + CS.esc(CS.full(c.updated)) + '</div>' : '')
                        + (link ? '<a href="' + CS.esc(link) + '">Open</a>' : '') + '</div>';
                }).join('') + '</div>';
            }
            var who = type === 'king' ? (m.author || 'King') + ' · AI' : (type === 'staff' ? m.author : '');
            var meta = (who ? CS.esc(who) + ' · ' : '') + CS.esc(m.created_at ? CS.when(m.created_at) : '');
            return '<div class="' + cls + '" data-id="' + CS.esc(m.id) + '">' + av + '<div><div class="swl-cs-bubble">' + body + cards + CS.attachments(m.attachments) + '</div>'
                + (type !== 'system' && meta ? '<div class="swl-cs-meta">' + meta + '</div>' : '') + '</div></div>';
        }

        function add(m) {
            if (m.id && st.seen[m.id]) { return; }
            if (m.id) { st.seen[m.id] = true; }
            $thread.append(bubbleFor(m));
            if (typeof m.id === 'number' && m.id > st.last) { st.last = m.id; }
        }

        function addList(list) {
            var had = $thread.children().length;
            (list || []).forEach(add);
            var last = list && list.length ? list[list.length - 1] : null;
            $root.find('.cs-solved').prop('hidden', !(st.owner === 'ai_active' && last && last.author_type === 'king' && $thread.find('.is-mine').length));
            if (list && list.length) {
                $root.find('.cs-quick').toggle(!$thread.find('.is-mine').length);
                toBottom(!had);
            }
        }

        function toBottom(instant) {
            var el = $scroll[0];
            if (instant) { el.scrollTop = el.scrollHeight; } else { $scroll.stop().animate({ scrollTop: el.scrollHeight }, 180); }
        }

        function typing(on, label) {
            $root.find('.cs-typing').prop('hidden', !on);
            $root.find('.cs-typing-l').text(label || '');
            if (on) { toBottom(); }
        }

        function fetch(first) {
            if (!st.conv) { return; }
            CS.get(opts.url, { action: 'poll', conversation_id: st.conv, after: st.last }).done(function (r) {
                if (!r || !r.ok) { return; }
                state(r);
                if (first && !r.messages.length) { welcome(); }
                addList(r.messages);
                if (!st.busy) { typing(!!r.typing, r.typing ? 'typing…' : ''); }
                st.delay = document.hidden ? 20000 : (st.owner === 'resolved' ? 15000 : 4000);
                if (r.messages.length && opts.onChange) { opts.onChange(); }
            }).fail(function () {
                st.delay = Math.min(60000, st.delay * 2); // back off while offline
            }).always(function () { schedule(st.delay); });
        }

        function schedule(ms) {
            stop();
            if (st.conv) { st.timer = setTimeout(function () { fetch(false); }, ms); }
        }
        function stop() { if (st.timer) { clearTimeout(st.timer); st.timer = null; } }

        function ensureConv() {
            if (st.conv) { return $.Deferred().resolve(st.conv).promise(); }
            var data = { action: 'start' };
            if (opts.entity) { data.entity_type = opts.entity.type; data.entity_id = opts.entity.id; }
            return CS.post(opts.url, data).then(function (r) {
                if (!r || !r.ok) { return $.Deferred().reject({ responseJSON: r }).promise(); }
                st.conv = r.conversation_id;
                $thread.empty();
                st.seen = {};
                state(r);
                addList(r.messages);
                if (opts.onChange) { opts.onChange(); }
                return st.conv;
            });
        }

        function send(text) {
            text = String(text || '').trim();
            if ((!text && !st.files.length) || st.busy) { return; }
            var files = st.files.slice();
            st.files = [];
            renderFiles();
            $text.val('');
            CS.grow($text[0]);
            sendRaw(text, CS.uid(), files);
        }

        function sendRaw(text, cid, files) {
            st.busy = true;
            $root.find('.cs-send').prop('disabled', true);
            var tmpId = 'tmp-' + cid;
            add({ id: tmpId, author_type: 'customer', body: text, attachments: files, created_at: '' });
            var $tmp = $thread.find('[data-id="' + tmpId + '"]');
            $tmp.find('.swl-cs-meta').text('Sending…');
            toBottom();
            if (st.owner === 'ai_active') { typing(true, (king.name || 'King') + ' is checking…'); }
            ensureConv().then(function () {
                return CS.post(opts.url, { action: 'send', conversation_id: st.conv, message: text, client_msg_id: cid, after: st.last, files: files.map(function (f) { return f.token; }) });
            }).done(function (r) {
                if (!r || !r.ok) { failed($tmp, text, cid, files, r && r.error); return; }
                $tmp.remove();
                delete st.seen[tmpId];
                state(r);
                addList(r.messages);
                if (opts.onChange) { opts.onChange(); }
            }).fail(function (xhr) {
                failed($tmp, text, cid, files, xhr && xhr.responseJSON && xhr.responseJSON.error);
            }).always(function () {
                st.busy = false;
                typing(false);
                $root.find('.cs-send').prop('disabled', false);
                schedule(st.delay);
            });
        }

        function failed($m, text, cid, files, err) {
            $m.addClass('is-failed').data({ text: text, cid: cid, files: files });
            $m.find('.swl-cs-meta').html(CS.esc(err || 'Not sent') + ' · <button type="button" class="swl-cs-link-btn cs-retry">Try again</button>');
        }

        function human() {
            if (st.busy) { return; }
            st.busy = true;
            ensureConv().then(function () {
                return CS.post(opts.url, { action: 'human', conversation_id: st.conv, after: st.last });
            }).done(function (r) {
                if (r && r.ok) { state(r); addList(r.messages); if (opts.onChange) { opts.onChange(); } }
                else { CS.toast(r && r.error ? r.error : 'Could not reach the team.'); }
            }).fail(CS.fail).always(function () { st.busy = false; schedule(st.delay); });
        }

        function solved() {
            CS.post(opts.url, { action: 'solved', conversation_id: st.conv, after: st.last }).done(function (r) {
                if (r && r.ok) { state(r); addList(r.messages); $root.find('.cs-solved').prop('hidden', true); if (opts.onChange) { opts.onChange(); } }
            }).fail(CS.fail);
        }

        function upload(file) {
            if (file.size > 8 * 1024 * 1024) { CS.toast('Files can be up to 8 MB.'); return; }
            var $f = $root.find('.cs-files');
            $f.append('<span class="cs-up">Uploading ' + CS.esc(file.name) + '…</span>');
            CS.upload(opts.url, file).done(function (r) {
                if (r && r.ok) { st.files.push(r.file); } else { CS.toast(r && r.error ? r.error : 'Upload failed.'); }
            }).fail(CS.fail).always(renderFiles);
        }

        function renderFiles() {
            $root.find('.cs-files').html(st.files.map(function (f, i) {
                return '<span><iconify-icon icon="solar:paperclip-linear"></iconify-icon>' + CS.esc(f.name)
                    + ' <button type="button" class="swl-cs-link-btn" data-i="' + i + '" aria-label="Remove ' + CS.esc(f.name) + '">×</button></span>';
            }).join(''));
        }
    };
})(window.jQuery);
