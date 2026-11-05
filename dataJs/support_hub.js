"use strict";

// ============================================================================
// Help & Support (customer). The chat component lives in support_chat.js;
// this file runs the side panel: requests, earlier chats, help articles, the
// new-request form and the request dialog (thread, reply, rating).
// ============================================================================

(function ($) {
    var CFG = window.CDP_CS || {};
    var CS = window.CS;
    var chat, boot = {}, openId = 0, lastLinks = [], reqFiles = [], tkFiles = [];

    $(function () {
        chat = new CS.Chat($('#cs_chat'), { url: CFG.url, king: CFG.king, hours: CFG.hours, entity: CFG.entity, newButton: true, onChange: refresh });

        $('.swl-cs-tabs [data-tab]').on('click', function () {
            var t = $(this).data('tab');
            $('.swl-cs-tabs [data-tab]').removeClass('is-on').attr('aria-selected', 'false');
            $(this).addClass('is-on').attr('aria-selected', 'true');
            $('[data-pane]').prop('hidden', true).filter('[data-pane="' + t + '"]').prop('hidden', false);
            if (t === 'help' && !$('#cs_help').children().length) { help(''); }
        });
        $('#cs_chat_new').on('click', function () { chat.startNew(); });
        $('#cs_chats').on('click', '[data-conv]', function () { chat.open(+$(this).data('conv')); });
        $('#cs_reqs').on('click', '[data-ticket]', function () { openTicket(+$(this).data('ticket')); });

        var t;
        $('#cs_help_q').on('input', function () { var q = this.value; clearTimeout(t); t = setTimeout(function () { help(q); }, 300); });
        $('#cs_help').on('click', '[data-vote]', function () {
            var $b = $(this);
            CS.post(CFG.url, { action: 'kb_vote', id: $b.data('id'), helpful: $b.data('vote') }).done(function () { $b.closest('.cs-vote').text('Thanks for the feedback.'); });
        });

        $('#cs_new_req').on('click', function () { $('#cs_req_modal').modal('show'); });
        $('#cs_req_file').on('change', function () { var f = this.files[0]; this.value = ''; if (f) { up(f, reqFiles, '#cs_req_files'); } });
        $('#cs_tk_file').on('change', function () { var f = this.files[0]; this.value = ''; if (f) { up(f, tkFiles, '#cs_tk_files'); } });
        $('#cs_req_form').on('submit', createRequest);
        $('#cs_tk_reply').on('submit', replyTicket);
        $('#cs_tk_rate').on('click', '[data-star]', function () {
            var n = +$(this).data('star');
            $('#cs_tk_rate [data-star]').each(function () { $(this).toggleClass('is-on', +$(this).data('star') <= n).attr('aria-pressed', +$(this).data('star') <= n ? 'true' : 'false'); });
            $('#cs_tk_rate').data('score', n);
        }).on('click', '#cs_rate_send', rate);

        refresh(function () {
            if (CFG.openTicket) { openTicket(CFG.openTicket); return; }
            // Resume the most recent chat that is still going; otherwise start fresh.
            var live = (boot.conversations || []).filter(function (c) { return c.owner !== 'resolved'; })[0];
            if (live && !CFG.entity) { chat.open(live.id); } else { chat.startNew(); }
        });
    });

    function refresh(after) {
        CS.get(CFG.url, { action: 'bootstrap' }).done(function (r) {
            if (!r || !r.ok) { $('#cs_reqs').html('<div class="swl-cs-empty">' + CS.esc(r && r.error ? r.error : 'Support is not available right now.') + '</div>'); return; }
            boot = r;
            renderReqs(r.tickets);
            renderChats(r.conversations);
            if (!$('#cs_req_cat option').length) {
                $('#cs_req_cat').html(Object.keys(r.categories).map(function (k) { return '<option value="' + k + '">' + CS.esc(r.categories[k]) + '</option>'; }).join(''));
                $('#cs_req_ent').append(r.records.map(function (e) {
                    return '<option value="' + e.type + ':' + e.id + '">' + CS.esc(e.label + ' ' + e.ref + (e.status ? ' · ' + e.status : '')) + '</option>';
                }).join(''));
            }
            if (typeof after === 'function') { after(); }
        }).fail(CS.fail);
    }

    // Requests by status, as a donut: the customer sees where each request stands.
    var reqChart = null, lastReqs = null;
    $(document).on('swl:theme', function () { if (lastReqs) { drawReqChart(lastReqs); } });
    function drawReqChart(list) {
        lastReqs = list;
        var box = document.getElementById('cs_req_chart');
        if (!box) { return; }
        if (!list.length || typeof window.ApexCharts === 'undefined') { box.hidden = true; return; }
        var groups = { 'Open': 0, 'Waiting on you': 0, 'Resolved': 0 };
        list.forEach(function (t) {
            if (['resolved', 'closed'].indexOf(t.status_key) > -1) { groups['Resolved']++; }
            else if (t.status_key === 'pending_customer') { groups['Waiting on you']++; }
            else { groups['Open']++; }
        });
        box.hidden = false;
        if (reqChart) { reqChart.destroy(); }
        reqChart = new window.ApexCharts(box, CS.chartTheme({
            chart: { type: 'donut', height: 200, fontFamily: 'inherit', background: 'transparent' },
            series: Object.keys(groups).map(function (k) { return groups[k]; }),
            labels: Object.keys(groups),
            colors: ['#2563eb', '#d97706', '#0f766e'],
            legend: { position: 'right', fontSize: '12px' },
            dataLabels: { enabled: false },
            stroke: { width: 2, colors: ['#fff'] },
            plotOptions: { pie: { donut: { size: '66%', labels: { show: true, total: { show: true, label: 'Requests', formatter: function (w) { return w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0); } } } } } }
        }));
        reqChart.render();
    }

    function renderReqs(list) {
        var open = list.filter(function (t) { return ['resolved', 'closed'].indexOf(t.status_key) === -1; }).length;
        $('#cs_req_n').text(open ? '(' + open + ' open)' : '');
        drawReqChart(list);
        if (!list.length) {
            $('#cs_reqs').html('<div class="swl-cs-empty"><iconify-icon icon="solar:inbox-linear"></iconify-icon>No requests yet. Chat with us, or use New request.</div>');
            return;
        }
        $('#cs_reqs').html(list.map(function (t) {
            return '<button type="button" class="swl-cs-req" data-ticket="' + t.id + '">'
                + '<div class="swl-cs-req-top"><span>' + CS.esc(t.ref) + '</span>' + CS.pill('status', t.status_key, t.status) + '</div>'
                + '<div class="swl-cs-req-sub">' + CS.esc(t.subject) + '</div>'
                + '<div class="swl-cs-small swl-cs-muted">' + (t.waiting_on_you ? '<b>Waiting for your reply</b> · ' : '') + 'Updated ' + CS.esc(CS.when(t.updated_at))
                + (t.can_rate ? ' · Please rate our help' : '') + '</div></button>';
        }).join(''));
    }

    function renderChats(list) {
        if (!list.length) { $('#cs_chats').html('<div class="swl-cs-empty">No chats yet.</div>'); return; }
        $('#cs_chats').html('<ul class="swl-cs-list">' + list.map(function (c) {
            return '<li><div class="grow"><button type="button" class="swl-cs-link-btn" data-conv="' + c.id + '">' + CS.esc(c.subject) + '</button>'
                + '<div class="swl-cs-small swl-cs-muted">' + CS.esc(CS.when(c.updated_at)) + (c.ticket_ref ? ' · ' + CS.esc(c.ticket_ref) : '') + '</div></div>'
                + (c.unread ? '<span class="swl-cs-pill is-open">' + c.unread + ' new</span>' : '') + '</li>';
        }).join('') + '</ul>');
    }

    function help(q) {
        CS.get(CFG.url, { action: 'help', q: q }).done(function (r) {
            if (!r || !r.ok) { return; }
            if (!r.articles.length) {
                $('#cs_help').html('<div class="swl-cs-empty">' + (q ? 'Nothing matches “' + CS.esc(q) + '”. Ask in the chat instead.' : 'No help articles have been published yet.') + '</div>');
                return;
            }
            $('#cs_help').html(r.articles.map(function (a) {
                return '<details class="swl-cs-article"><summary>' + CS.esc(a.title) + '</summary><div class="swl-cs-md">' + CS.md(a.body) + '</div>'
                    + '<div class="swl-cs-small swl-cs-muted cs-vote mt-1">Updated ' + CS.esc(CS.when(a.updated_at)) + ' · Was this helpful? '
                    + '<button type="button" class="swl-cs-link-btn" data-vote="1" data-id="' + a.id + '">Yes</button> · '
                    + '<button type="button" class="swl-cs-link-btn" data-vote="0" data-id="' + a.id + '">No</button></div></details>';
            }).join(''));
        });
    }

    function up(file, list, target) {
        if (file.size > 8 * 1024 * 1024) { CS.toast('Files can be up to 8 MB.'); return; }
        $(target).append('<span class="cs-up">Uploading…</span>');
        CS.upload(CFG.url, file).done(function (r) {
            if (r && r.ok) { list.push(r.file); } else { CS.toast(r && r.error ? r.error : 'Upload failed.'); }
        }).fail(CS.fail).always(function () {
            $(target).html(list.map(function (f) { return '<span>' + CS.esc(f.name) + '</span>'; }).join(''));
        });
    }

    function createRequest(e) {
        e.preventDefault();
        var $b = $('#cs_req_send').prop('disabled', true);
        CS.post(CFG.url, {
            action: 'ticket_create', subject: $('#cs_req_subject').val(), description: $('#cs_req_desc').val(),
            category: $('#cs_req_cat').val(), entity: $('#cs_req_ent').val(), files: reqFiles.map(function (f) { return f.token; })
        }).done(function (r) {
            if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Could not send.'); return; }
            $('#cs_req_modal').modal('hide');
            $('#cs_req_form')[0].reset();
            reqFiles = [];
            $('#cs_req_files').empty();
            renderReqs(r.tickets);
            CS.toast('Request ' + r.ticket.ref + ' received. We will reply here and notify you.', 'success');
        }).fail(CS.fail).always(function () { $b.prop('disabled', false); });
    }

    function openTicket(id) {
        CS.get(CFG.url, { action: 'ticket', id: id }).done(function (r) {
            if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Request not found.'); return; }
            openId = id;
            drawTicket(r);
            $('#cs_tk_modal').modal('show');
        }).fail(CS.fail);
    }

    function drawTicket(r) {
        var t = r.ticket;
        if (r.links) { lastLinks = r.links; }
        $('#cs_tk_title').text(t.subject);
        $('#cs_tk_kv').html('<dt>Reference</dt><dd>' + CS.esc(t.ref) + '</dd><dt>Status</dt><dd>' + CS.pill('status', t.status_key, t.status) + '</dd>'
            + '<dt>Topic</dt><dd>' + CS.esc(t.category) + '</dd><dt>Handled by</dt><dd>' + CS.esc(t.team) + '</dd>'
            + '<dt>Opened</dt><dd>' + CS.esc(CS.full(t.created_at)) + '</dd><dt>Last update</dt><dd>' + CS.esc(CS.full(t.updated_at)) + '</dd>'
            + (lastLinks.length ? '<dt>About</dt><dd>' + lastLinks.map(function (l) { return CS.esc(l.label + ' ' + l.ref + ' (' + l.status + ')'); }).join('<br>') + '</dd>' : ''));
        if (t.can_rate) {
            $('#cs_tk_rate').html('<div class="swl-cs-banner is-info"><b>How did we do?</b><div class="swl-cs-stars" role="group" aria-label="Rating">'
                + [1, 2, 3, 4, 5].map(function (n) { return '<button type="button" data-star="' + n + '" aria-label="' + n + ' star' + (n > 1 ? 's' : '') + '" aria-pressed="false">★</button>'; }).join('')
                + '</div><textarea class="form-control mt-2" id="cs_rate_c" rows="2" placeholder="Anything we could do better? (optional)"></textarea>'
                + '<button type="button" class="btn btn-primary btn-sm mt-2" id="cs_rate_send">Send rating</button></div>');
        } else {
            $('#cs_tk_rate').html(t.rating ? '<p class="swl-cs-small swl-cs-muted">You rated this ' + t.rating + ' of 5. Thank you.</p>' : '');
        }
        $('#cs_tk_thread').html(r.messages.map(function (m) {
            var mine = m.author_type === 'customer';
            var cls = 'swl-cs-msg' + (mine ? ' is-mine' : '') + (m.author_type === 'system' ? ' is-system' : '');
            return '<div class="' + cls + '"><div><div class="swl-cs-bubble">' + CS.md(m.body) + CS.attachments(m.attachments) + '</div>'
                + (m.author_type !== 'system' ? '<div class="swl-cs-meta">' + CS.esc((mine ? 'You' : m.author + (m.author_type === 'king' ? ' · AI' : '')) + ' · ' + CS.when(m.created_at)) + '</div>' : '') + '</div></div>';
        }).join(''));
        $('#cs_tk_reply').toggle(t.status_key !== 'closed');
        setTimeout(function () { var el = $('#cs_tk_thread').parent()[0]; el.scrollTop = el.scrollHeight; }, 50);
    }

    function replyTicket(e) {
        e.preventDefault();
        var text = $('#cs_tk_text').val().trim();
        if (!text && !tkFiles.length) { return; }
        CS.post(CFG.url, { action: 'ticket_reply', id: openId, message: text, client_msg_id: CS.uid(), files: tkFiles.map(function (f) { return f.token; }) }).done(function (r) {
            if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Not sent.'); return; }
            $('#cs_tk_text').val('');
            tkFiles = [];
            $('#cs_tk_files').empty();
            drawTicket(r);
            refresh();
        }).fail(CS.fail);
    }

    function rate() {
        var n = $('#cs_tk_rate').data('score');
        if (!n) { CS.toast('Choose 1 to 5 stars.'); return; }
        CS.post(CFG.url, { action: 'rate', id: openId, score: n, comment: $('#cs_rate_c').val() }).done(function (r) {
            if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Not saved.'); return; }
            $('#cs_tk_rate').html('<p class="swl-cs-small">Thank you for rating us.</p>');
            renderReqs(r.tickets);
        }).fail(CS.fail);
    }
})(window.jQuery);
