"use strict";

// ============================================================================
// WhatsApp Inbox (views/comms/inbox.php): conversation list, conversation,
// reply box. Polls every 10 seconds while the tab is visible.
// ============================================================================

(function ($) {
    var OPS = window.OPS, esc = OPS.esc, URL = 'ajax/comms/comms_ajax.php';
    var current = 0, lastCount = -1;

    function list() {
        OPS.get(URL, { action: 'threads', status: $('#ib_status').val(), q: $('#ib_q').val() }).then(function (r) {
            $('#ib_counts').text(r.counts.open + ' waiting for a person · ' + r.counts.mine + ' assigned to you · ' + r.counts.bot + ' with the bot' + (r.bot_enabled ? '' : ' (bot is off)'));
            $('#ib_threads').html(r.rows.map(function (t) {
                var name = t.contact_name || ('+' + t.phone);
                return '<button type="button" class="swl-ops-thread' + (Number(t.id) === current ? ' is-active' : '') + '" data-id="' + t.id + '">'
                    + '<span class="swl-ops-thread__name"><span>' + esc(name) + (t.locker ? ' <span class="swl-ops-muted">' + esc(t.locker) + '</span>' : '') + '</span>'
                    + (Number(t.unread) ? OPS.chip(t.unread, 'warn') : OPS.chip(t.status, t.status === 'open' ? 'warn' : (t.status === 'bot' ? 'info' : ''))) + '</span>'
                    + '<span class="swl-ops-thread__last">' + esc(t.last_message || '') + '</span></button>';
            }).join('') || '<p class="swl-ops-muted">No conversations.</p>');
        });
    }

    function open(id, keepScroll) {
        current = Number(id);
        OPS.get(URL, { action: 'thread', id: id }).then(function (r) {
            var t = r.thread;
            if (keepScroll && r.messages.length === lastCount) { return; }
            lastCount = r.messages.length;
            var head = '<div class="d-flex flex-wrap justify-content-between align-items-start mb-2"><div><h5 class="card-title mb-0">' + esc(t.contact_name || ('+' + t.phone)) + '</h5>'
                + '<div class="swl-ops-muted swl-ops-small">+' + esc(t.phone) + (t.locker ? ' · locker ' + esc(t.locker) : ' · not linked to a customer') + (r.assignee ? ' · assigned to ' + esc(r.assignee) : '')
                + (t.handoff_reason ? ' · ' + esc(t.handoff_reason) : '') + '</div></div><div class="btn-group flex-wrap">'
                + '<button type="button" class="btn btn-sm btn-secondary ib-op" data-op="assign_me">Assign to me</button>'
                + (Number(t.bot_active) ? '<button type="button" class="btn btn-sm btn-secondary ib-op" data-op="bot_off">Stop the bot</button>' : '<button type="button" class="btn btn-sm btn-secondary ib-op" data-op="bot_on">Hand back to the bot</button>')
                + (t.status === 'closed' ? '<button type="button" class="btn btn-sm btn-secondary ib-op" data-op="reopen">Reopen</button>' : '<button type="button" class="btn btn-sm btn-dark ib-op" data-op="close">Close</button>')
                + (t.user_id ? '<a class="btn btn-sm btn-outline-secondary" href="comms_center.php#customers">History</a>' : '') + '</div></div>';
            var msgs = '<div class="swl-ops-chat" id="ib_chat" aria-live="polite">' + r.messages.map(function (m) {
                var cls = m.direction === 'in' ? 'is-in' : (m.author_type === 'bot' ? 'is-bot' : 'is-out');
                var who = m.direction === 'in' ? 'Customer' : (m.author_type === 'bot' ? 'AI assistant' : (m.author_type === 'agent' ? (m.author_name || 'Staff') : 'Automatic'));
                return '<div class="swl-ops-msg ' + cls + '">' + esc(m.body) + '<div class="swl-ops-msg__meta">' + esc(who) + ' · ' + esc(m.created_at) + (m.status === 'failed' ? ' · not delivered' : '') + '</div></div>';
            }).join('') + '</div>';
            var warn = (r.provider === 'cloud' && !r.window_open) ? '<div class="swl-ops-banner mt-2">The customer has not written in the last 24 hours, so WhatsApp only accepts approved templates. Send one from Communication Center › Send Message.</div>' : '';
            var form = '<form id="ib_form" class="mt-3"><label for="ib_body">Reply</label><textarea id="ib_body" class="form-control" rows="3" maxlength="4000" required></textarea>'
                + '<button type="submit" class="btn btn-primary mt-2">Send</button> <span class="swl-ops-muted swl-ops-small">Replying takes the chat over from the bot.</span></form>';
            $('#ib_detail').html(head + msgs + warn + form);
            var chat = document.getElementById('ib_chat');
            if (chat) { chat.scrollTop = chat.scrollHeight; }
            list();
        });
    }

    $(document).on('click', '.swl-ops-thread', function () { lastCount = -1; open($(this).data('id')); });
    $(document).on('change', '#ib_status', list);
    $(document).on('input', '#ib_q', OPS.debounce(list, 400));
    $(document).on('submit', '#ib_form', function (ev) {
        ev.preventDefault();
        var $b = $('#ib_body'), text = $b.val();
        if (!text.trim()) { return; }
        $b.prop('disabled', true);
        OPS.post(URL + '?action=reply', { action: 'reply', id: current, body: text }).then(function (r) {
            if (r.ok) { $b.val(''); lastCount = -1; open(current); } else { OPS.toast(r.error, 'error'); }
        }).always(function () { $b.prop('disabled', false); });
    });
    $(document).on('click', '.ib-op', function () {
        OPS.post(URL + '?action=thread_update', { action: 'thread_update', id: current, op: $(this).data('op') }).then(function (r) { if (OPS.result(r, 'Updated')) { lastCount = -1; open(current); } });
    });

    $(function () {
        list();
        setInterval(function () {
            if (document.hidden) { return; }
            if (current) { open(current, true); }
            list();
        }, 10000);
    });
})(window.jQuery);
