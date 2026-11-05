"use strict";

// ============================================================================
// One ticket (staff). Everything the server knows arrives in one payload (D);
// each change posts and gets the fresh payload back, so the screen never shows
// a guess. Field changes carry the ticket version: if a colleague changed the
// ticket first, the change is refused and the page reloads their version.
// AI output is only shown as a preview; the agent decides what is sent.
// ============================================================================

(function ($) {
    var V = window.CDP_CS_VOCAB || {};
    var CS = window.CS;
    var URL_ = 'ajax/support/desk_ajax.php';
    var ID = window.CDP_CS_TICKET;
    var D = null, T = {}, mode = 'reply', files = [], aiAssisted = false, lastId = 0, typingAt = 0, pollTimer = null, classify = null;

    $(function () {
        if (!ID) { $('#cs_t_subject').text('No ticket chosen.'); return; }
        bindComposer();
        bindSide();
        CS.get(URL_, { action: 'tools' }).done(function (r) {
            if (!r || !r.ok) { return; }
            T = r;
            $('#cs_canned').append(r.canned.map(function (c, i) { return '<option value="' + i + '">' + CS.esc(c.title + (c.shortcut ? ' (/' + c.shortcut + ')' : '')) + '</option>'; }).join(''));
            $('#cs_refine').append(Object.keys(r.refine).map(function (k) { return '<option value="' + k + '">' + CS.esc(r.refine[k]) + '</option>'; }).join(''));
            if (D) { drawDetails(); }
        });
        if (!V.copilot) { $('.cs-ai').prop('hidden', true); }
        load();
    });

    // ── Loading ────────────────────────────────────────────────────────────
    function load() {
        CS.get(URL_, { action: 'ticket', id: ID }).done(function (r) {
            if (!r || !r.ok) { $('#cs_t_subject').text(r && r.error ? r.error : 'Could not load the ticket.'); return; }
            apply(r, true);
            schedulePoll();
        }).fail(CS.fail);
    }

    function apply(r, scroll) {
        D = r;
        document.title = D.ticket.ref + ' · ' + D.ticket.subject;
        drawHead();
        drawTimeline(scroll);
        drawDetails();
        drawCustomer();
        drawAi();
        drawHistory();
        drawComposerState();
    }

    function schedulePoll() {
        clearTimeout(pollTimer);
        pollTimer = setTimeout(poll, document.hidden ? 30000 : 7000);
    }

    function poll() {
        CS.get(URL_, { action: 'poll', id: ID, after: lastId }).done(function (r) {
            if (!r || !r.ok) { return; }
            drawViewers(r.viewers);
            var changed = r.version !== D.ticket.version || r.owner !== (D.ticket.conversation ? D.ticket.conversation.owner : '');
            if (changed || r.messages.length) {
                // Something moved: take the whole fresh state, keep what the agent is typing.
                CS.get(URL_, { action: 'ticket', id: ID }).done(function (f) { if (f && f.ok) { apply(f, true); } });
            }
        }).always(schedulePoll);
    }

    // ── Header ─────────────────────────────────────────────────────────────
    function drawHead() {
        var t = D.ticket, c = t.conversation;
        $('#cs_t_subject').text(t.subject);
        $('#cs_t_sub').html(CS.esc(t.ref) + ' ' + CS.pill('status', t.status) + ' ' + CS.pill('priority', t.priority)
            + (c && c.owner && c.owner !== 'resolved' ? ' ' + CS.pill('owner', c.owner, c.owner === 'human_active' && c.owner_user ? 'With ' + c.owner_user : '') : '')
            + ' · ' + CS.esc(V.channels[t.channel] || t.channel) + ' · opened ' + CS.esc(CS.full(t.created_at))
            + (t.is_internal ? ' · ' + '<span class="swl-cs-pill is-pending_internal">Staff request</span>' : ''));

        var a = [];
        if (c) {
            if (c.owner === 'handoff_queued') { a.push(btn('accept', 'Accept chat', 'primary', 'solar:hand-shake-linear')); }
            if (c.owner === 'ai_active') { a.push(btn('accept', 'Take over from ' + V.king, 'secondary', 'solar:user-hand-up-linear')); }
            if (c.owner === 'human_active' && c.owner_user_id === V.me) {
                a.push(btn('requeue', 'Return to queue', 'secondary', 'solar:undo-left-linear'));
                if (V.king_on) { a.push(btn('release', 'Hand back to ' + V.king, 'secondary', 'solar:magic-stick-3-linear')); }
            }
            if (c.owner === 'human_active' && c.owner_user_id !== V.me && V.admin) { a.push(btn('takeover', 'Take over', 'secondary', 'solar:user-hand-up-linear')); }
        }
        a.push(btn('follow', D.following ? 'Following' : 'Follow', 'secondary', D.following ? 'solar:bell-bold' : 'solar:bell-linear'));
        a.push('<div class="dropdown d-inline-block"><button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">More</button>'
            + '<div class="dropdown-menu dropdown-menu-right">'
            + '<button type="button" class="dropdown-item" data-act="merge">Merge into another ticket…</button>'
            + (t.disposition === 'none' ? '<button type="button" class="dropdown-item" data-act="archive">Archive</button>' : '<button type="button" class="dropdown-item" data-act="unarchive">Move back to the inbox</button>')
            + ((V.admin || t.assignee_id === V.me) && t.disposition === 'none' ? '<button type="button" class="dropdown-item" data-act="spam">Mark as spam</button><button type="button" class="dropdown-item" data-act="trash">Move to trash</button>' : '')
            + '</div></div>');
        $('#cs_t_actions').html(a.join(''));
        drawViewers(D.viewers);

        var b = [];
        if (t.merged_into) { b.push('<div class="swl-cs-banner is-info">This ticket was merged into <a href="support_ticket.php?id=' + t.merged_into + '">' + CS.esc(t.merged_into_ref) + '</a>.</div>'); }
        if (t.disposition && t.disposition !== 'none') { b.push('<div class="swl-cs-banner">This ticket is in <b>' + CS.esc(t.disposition) + '</b>.</div>'); }
        if (c && c.owner === 'handoff_queued') { b.push('<div class="swl-cs-banner is-info"><b>The customer is waiting for a person.</b> ' + (c.handover_reason ? CS.ico('action', 'handover') + 'Why: ' + CS.esc(c.handover_reason.replace(/[_-]+/g, ' ')) + '. ' : '') + 'Accept the chat to reply live.</div>'); }
        if (c && c.owner === 'ai_active' && ['resolved', 'closed'].indexOf(t.status) === -1) { b.push('<div class="swl-cs-banner is-info">' + CS.ico('action', 'king') + CS.esc(V.king) + ' is answering this chat. Replying takes it over; the customer is told a person joined.</div>'); }
        if (D.similar && D.similar.length) {
            b.push('<div class="swl-cs-banner">This customer has ' + D.similar.length + ' other open ticket' + (D.similar.length > 1 ? 's' : '') + ': '
                + D.similar.map(function (s) { return '<a href="support_ticket.php?id=' + s.id + '">' + CS.esc(s.ref) + '</a> (' + CS.esc(s.subject) + ')'; }).join(', ') + '. Merge if it is the same issue.</div>');
        }
        $('#cs_banners').html(b.join(''));
    }

    function btn(act, label, kind, icon) {
        return '<button type="button" class="btn btn-' + kind + ' btn-sm" data-act="' + act + '"><iconify-icon icon="' + icon + '"></iconify-icon> ' + CS.esc(label) + '</button>';
    }

    function drawViewers(list) {
        if (!list || !list.length) { $('#cs_viewers').empty(); return; }
        $('#cs_viewers').html('<iconify-icon icon="solar:eye-linear"></iconify-icon> ' + list.map(function (v) {
            return CS.esc(v.name.split(' ')[0]) + (v.typing ? ' is typing…' : '');
        }).join(', ') + (list.some(function (v) { return v.typing; }) ? '' : ' viewing'));
    }

    // ── Timeline ───────────────────────────────────────────────────────────
    function drawTimeline(scroll) {
        var el = $('#cs_timeline')[0];
        var atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
        var msgs = D.messages;
        lastId = msgs.length ? msgs[msgs.length - 1].id : 0;
        if (!msgs.length) {
            $('#cs_timeline').html('<div class="swl-cs-empty">No messages yet.' + (D.ticket.description ? '<br>' + CS.esc(D.ticket.description) : '') + '</div>');
            return;
        }
        $('#cs_timeline').html(msgs.map(msgHtml).join(''));
        if (scroll && (atBottom || !el.dataset.seen)) { el.scrollTop = el.scrollHeight; el.dataset.seen = '1'; }
    }

    function msgHtml(m) {
        var meta = m.meta || {}, internal = m.visibility === 'internal';
        var cls = 'swl-cs-msg';
        var who = m.author;
        if (m.author_type === 'customer') { who = m.author || D.ticket.customer; }
        else if (m.author_type === 'system') { cls += internal ? ' is-note' : ' is-system'; who = internal ? 'System note' : ''; }
        else if (m.author_type === 'king') { cls += ' is-king'; who = V.king + ' (AI)'; }
        else if (m.author_type === 'staff') { cls += internal ? ' is-note' : (m.author_id === V.me ? ' is-staff-mine' : ''); }
        if (m.author_type === 'staff' || (m.author_type === 'system' && internal) || m.author_type === 'king') { cls += ' is-mine'; }
        var extra = '';
        if (meta.sources && meta.sources.length) { extra += '<div class="swl-cs-src">Sources: ' + meta.sources.map(function (s) { return CS.esc(s.title || s); }).join(', ') + '</div>'; }
        if (meta.tools && meta.tools.length) { extra += '<div class="swl-cs-src">Looked up: ' + meta.tools.map(function (s) { return CS.esc(s.name || s); }).join(', ') + '</div>'; }
        if (meta.ai_assisted) { extra += '<div class="swl-cs-src">Drafted with AI, reviewed by the agent</div>'; }
        var state = m.delivery_state && ['failed', 'dead'].indexOf(m.delivery_state) > -1 ? ' · <span class="swl-cs-pill is-dead">Not delivered</span>' : '';
        return '<div class="' + cls + '" data-mid="' + m.id + '"><div>'
            + (who ? '<div class="swl-cs-who">' + CS.esc(who) + (internal && m.author_type === 'staff' ? ' · internal note' : '') + '</div>' : '')
            + '<div class="swl-cs-bubble">' + CS.md(m.body) + CS.attachments(m.attachments) + '</div>' + extra
            + '<div class="swl-cs-meta">' + CS.esc(CS.full(m.created_at)) + (m.channel && m.channel !== 'web' && m.channel !== 'staff' ? ' · ' + CS.esc(V.channels[m.channel] || m.channel) : '') + state + '</div>'
            + '</div></div>';
    }

    // ── Composer ───────────────────────────────────────────────────────────
    function drawComposerState() {
        var t = D.ticket, noCustomer = !t.customer_id || t.is_internal;
        if (noCustomer && mode === 'reply') { setMode('note'); }
        $('[data-mode="reply"]').prop('disabled', noCustomer).attr('title', noCustomer ? 'No customer to reply to' : '');
        $('#cs_composer').toggle(!t.merged_into);
    }

    function setMode(m) {
        mode = m;
        $('[data-mode]').removeClass('is-on').attr('aria-selected', 'false').filter('[data-mode="' + m + '"]').addClass('is-on').attr('aria-selected', 'true');
        $('#cs_composer').toggleClass('is-note', m === 'note');
        $('#cs_note_warn').prop('hidden', m !== 'note');
        $('#cs_after').prop('disabled', m === 'note');
        $('#cs_body').attr('placeholder', m === 'note' ? 'Write an internal note (customers never see it)…' : 'Write your reply…');
        $('#cs_send').html((m === 'note' ? 'Add note' : 'Send') + ' <span class="swl-cs-small">(Ctrl+Enter)</span>');
    }

    function bindComposer() {
        $('[data-mode]').on('click', function () { if (!$(this).prop('disabled')) { setMode($(this).data('mode')); } });
        $('#cs_body').on('keydown', function (e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); send(false); }
        }).on('input', function () {
            var v = this.value;
            if (/^\/\w+$/.test(v.trim()) && T.canned) {
                var c = T.canned.filter(function (x) { return x.shortcut && '/' + x.shortcut === v.trim(); })[0];
                if (c) { this.value = fill(c.body); }
            }
            if (Date.now() - typingAt > 5000 && mode === 'reply') { typingAt = Date.now(); CS.post(URL_, { action: 'typing', id: ID }); }
        });
        $('#cs_canned').on('change', function () {
            var c = T.canned && T.canned[this.value];
            this.value = '';
            if (!c) { return; }
            var $b = $('#cs_body'), cur = $b.val();
            $b.val((cur ? cur.replace(/\s+$/, '') + '\n\n' : '') + fill(c.body)).trigger('focus');
        });
        $('#cs_file').on('change', function () {
            var f = this.files[0]; this.value = '';
            if (!f) { return; }
            if (f.size > 8 * 1024 * 1024) { CS.toast('Files can be up to 8 MB.'); return; }
            $('#cs_files').text('Uploading…');
            CS.upload(URL_, f).done(function (r) {
                if (r && r.ok) { files.push(r.file); } else { CS.toast(r && r.error ? r.error : 'Upload failed.'); }
            }).fail(CS.fail).always(drawFiles);
        });
        $('#cs_files').on('click', '[data-rm]', function () { files.splice(+$(this).data('rm'), 1); drawFiles(); });
        $('#cs_send').on('click', function () { send(false); });
        $('#cs_ai_draft').on('click', aiDraft);
        $('#cs_refine').on('change', function () { var m = this.value; this.value = ''; if (m) { aiRefine(m); } });
        $('#cs_ai_preview').on('click', '[data-ai="use"]', function () {
            $('#cs_body').val($(this).closest('.swl-cs-ai-preview').find('pre').text()).trigger('focus');
            aiAssisted = true;
            $('#cs_ai_preview').empty();
        }).on('click', '[data-ai="discard"]', function () { $('#cs_ai_preview').empty(); });
    }

    function drawFiles() {
        $('#cs_files').html(files.map(function (f, i) { return '<span>' + CS.esc(f.name) + ' <button type="button" class="swl-cs-link-btn" data-rm="' + i + '" aria-label="Remove ' + CS.esc(f.name) + '">×</button></span>'; }).join(' '));
    }

    function fill(text) {
        var t = D.ticket, name = (t.customer && t.customer !== 'No account') ? t.customer : '';
        var me = (V.agents.filter(function (a) { return a.id === V.me; })[0] || { name: '' }).name.split(' ')[0];
        return String(text).replace(/\{customer_name\}/g, name || 'there').replace(/\{first_name\}/g, name ? name.split(' ')[0] : 'there')
            .replace(/\{ticket_ref\}/g, t.ref).replace(/\{subject\}/g, t.subject).replace(/\{agent_name\}/g, me || 'The support team');
    }

    function send(force) {
        var body = $('#cs_body').val().trim();
        if (!body && !files.length) { $('#cs_body').trigger('focus'); return; }
        var after = mode === 'reply' ? $('#cs_after').val() : '';
        var go = function (extra) {
            var $b = $('#cs_send').prop('disabled', true);
            var cid = $('#cs_body').data('cid') || CS.uid();
            $('#cs_body').data('cid', cid);
            CS.post(URL_, $.extend({
                action: 'send', id: ID, body: body, mode: mode, last_seen_id: lastId, client_msg_id: cid, force: force ? 1 : 0,
                files: files.map(function (f) { return f.token; }), set_status: after, ai_assisted: aiAssisted ? 1 : 0
            }, extra || {})).done(function (r) {
                if (r && r.conflict) {
                    CS.get(URL_, { action: 'ticket', id: ID }).done(function (f) { if (f && f.ok) { apply(f, true); } });
                    CS.confirm('A colleague replied first', r.error, 'Send anyway', function () { send(true); });
                    return;
                }
                if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Not sent.'); return; }
                $('#cs_body').val('').removeData('cid');
                files = []; drawFiles(); aiAssisted = false; $('#cs_after').val('');
                apply(r, true);
                $('#cs_timeline')[0].scrollTop = $('#cs_timeline')[0].scrollHeight;
            }).fail(CS.fail).always(function () { $b.prop('disabled', false); });
        };
        if (after === 'resolved' && !D.ticket.resolution_summary) {
            askResolution(function (code, summary) {
                // Save the resolution first, then send with the status change.
                update({ resolution_code: code, resolution_summary: summary }, function () { go(); });
            });
            return;
        }
        go();
    }

    function preview(title, text) {
        $('#cs_ai_preview').html('<div class="swl-cs-ai-preview" role="region" aria-label="AI suggestion"><b>' + CS.esc(title) + '</b> <span class="swl-cs-small swl-cs-muted">Check it before you use it. Nothing is sent until you press Send.</span>'
            + '<pre>' + CS.esc(text) + '</pre><button type="button" class="btn btn-primary btn-sm" data-ai="use">Use this</button> <button type="button" class="btn btn-secondary btn-sm" data-ai="discard">Discard</button></div>');
    }

    function busy(on, label) {
        if (on) { $('#cs_ai_preview').html('<div class="swl-cs-ai-preview"><span class="swl-cs-typing" aria-hidden="true"><span></span><span></span><span></span></span> ' + CS.esc(label) + '</div>'); }
        $('.cs-ai').prop('disabled', on);
    }

    function aiDraft() {
        var instr = mode === 'reply' ? $('#cs_body').val().trim() : '';
        busy(true, 'Drafting a reply from the conversation and help articles…');
        CS.post(URL_, { action: 'ai_draft', id: ID, instruction: instr }).done(function (r) {
            if (!r || !r.ok) { $('#cs_ai_preview').empty(); CS.toast(r && r.error ? r.error : 'No draft.'); return; }
            preview('Suggested reply' + (r.sources && r.sources.length ? ' (from: ' + r.sources.map(function (s) { return s.title; }).join(', ') + ')' : ''), r.text);
        }).fail(function (x) { $('#cs_ai_preview').empty(); CS.fail(x); }).always(function () { busy(false); });
    }

    function aiRefine(m) {
        var text = $('#cs_body').val().trim();
        if (!text) { CS.toast('Write something first.'); return; }
        var go = function (lang) {
            busy(true, 'Rewriting…');
            CS.post(URL_, { action: 'ai_refine', id: ID, text: text, mode: m, lang: lang || D.ticket.language || 'en' }).done(function (r) {
                if (!r || !r.ok) { $('#cs_ai_preview').empty(); CS.toast(r && r.error ? r.error : 'Not rewritten.'); return; }
                preview(T.refine[m], r.text);
            }).fail(function (x) { $('#cs_ai_preview').empty(); CS.fail(x); }).always(function () { busy(false); });
        };
        if (m === 'translate' && window.Swal) {
            var inputOptions = {}; Object.keys(V.languages).forEach(function (k) { inputOptions[k] = V.languages[k]; });
            Swal.fire({ title: 'Translate into', input: 'select', inputOptions: inputOptions, inputValue: D.ticket.language || 'en', showCancelButton: true, confirmButtonText: 'Translate' })
                .then(function (r) { if (r.isConfirmed) { go(r.value); } });
        } else { go(); }
    }

    // ── Field changes ──────────────────────────────────────────────────────
    function update(changes, then) {
        CS.post(URL_, $.extend({ action: 'update', id: ID, version: D.ticket.version }, changes)).done(function (r) {
            if (r && r.need_resolution) {
                askResolution(function (code, summary) { update($.extend({}, changes, { resolution_code: code, resolution_summary: summary }), then); });
                return;
            }
            if (r && r.conflict) { CS.toast(r.error, 'warning'); load(); return; }
            if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Not saved.'); drawDetails(); return; }
            apply(r, false);
            if (then) { then(); }
        }).fail(CS.fail);
    }

    function askResolution(fn) {
        var codes = T.resolution_codes || { other: 'Other' };
        if (!window.Swal) { var s = window.prompt('How was it resolved?'); if (s) { fn('other', s); } return; }
        Swal.fire({
            title: 'How was it resolved?',
            html: '<select id="cs_rc" class="custom-select mb-2">' + Object.keys(codes).map(function (k) { return '<option value="' + k + '">' + CS.esc(codes[k]) + '</option>'; }).join('') + '</select>'
                + '<textarea id="cs_rs" class="form-control" rows="3" placeholder="One or two sentences for the record and the reports"></textarea>',
            showCancelButton: true, confirmButtonText: 'Resolve', focusConfirm: false,
            preConfirm: function () {
                var s = $('#cs_rs').val().trim();
                if (!s) { Swal.showValidationMessage('Add a short summary.'); return false; }
                return { code: $('#cs_rc').val(), summary: s };
            }
        }).then(function (r) { if (r.isConfirmed) { fn(r.value.code, r.value.summary); } else { drawDetails(); } });
    }

    function post(data, then) {
        return CS.post(URL_, $.extend({ id: ID }, data)).done(function (r) {
            if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Not done.'); return; }
            if (r.ticket) { apply(r, false); }
            if (then) { then(r); }
        }).fail(CS.fail);
    }

    // ── Side panel ─────────────────────────────────────────────────────────
    function sel(name, obj, val, extra) {
        return '<select class="custom-select custom-select-sm" data-field="' + name + '" id="cs_f_' + name + '">' + (extra || '')
            + Object.keys(obj).map(function (k) { return '<option value="' + CS.esc(k) + '"' + (String(val) === String(k) ? ' selected' : '') + '>' + CS.esc(obj[k]) + '</option>'; }).join('') + '</select>';
    }
    function arr2obj(a) { var o = {}; a.forEach(function (x) { o[x.id] = x.name; }); return o; }
    function group(label, html, id) { return '<div class="form-group"><label' + (id ? ' for="' + id + '"' : '') + '>' + CS.esc(label) + '</label>' + html + '</div>'; }
    function section(title, html) { return '<div class="swl-cs-section">' + CS.esc(title) + '</div><div>' + html + '</div>'; }

    function drawDetails() {
        if (!D) { return; }
        var t = D.ticket, s = D.sla, h = '';
        h += '<div class="row">'
            + '<div class="col-12">' + group('Status', sel('status', V.statuses, t.status), 'cs_f_status') + '</div>'
            + (t.status.indexOf('pending') === 0 || t.status === 'on_hold' ? '<div class="col-12">' + group('Waiting for', '<input type="text" class="form-control form-control-sm" data-field="pending_reason" id="cs_f_pending_reason" value="' + CS.esc(t.pending_reason) + '" placeholder="e.g. receipt from the customer">', 'cs_f_pending_reason') + '</div>' : '')
            + '<div class="col-6">' + group('Priority', sel('priority', V.priorities, t.priority), 'cs_f_priority') + '</div>'
            + '<div class="col-6">' + group('Language', sel('language', V.languages, t.language) + (t.language_source === 'detected' ? '<span class="swl-cs-small swl-cs-muted">Detected</span>' : ''), 'cs_f_language') + '</div>'
            + '<div class="col-12">' + group('Topic', sel('category', V.categories, t.category), 'cs_f_category') + '</div>'
            + '<div class="col-6">' + group('Team', sel('team_id', arr2obj(V.teams), t.team_id, '<option value="0">No team</option>'), 'cs_f_team_id') + '</div>'
            + '<div class="col-6">' + group('Assignee', sel('assignee_id', arr2obj(V.agents), t.assignee_id, '<option value="0">Unassigned</option>')
                + (t.assignee_id !== V.me ? '<button type="button" class="swl-cs-link-btn swl-cs-small" data-act="mine">Assign to me</button>' : ''), 'cs_f_assignee_id') + '</div>'
            + '</div>';

        if (t.status === 'resolved' || t.status === 'closed') {
            h += section('Resolution', '<p class="swl-cs-small mb-1"><b>' + CS.esc((T.resolution_codes || {})[t.resolution_code] || t.resolution_code || '–') + '</b></p><p class="swl-cs-small">' + CS.esc(t.resolution_summary) + '</p>'
                + (t.csat !== null ? '<p class="swl-cs-small">Customer rating: <b>' + t.csat + '/5</b>' + (t.csat_comment ? ' · “' + CS.esc(t.csat_comment) + '”' : '') + '</p>' : ''));
        }

        h += section('Service level' + (s.policy ? ' · ' + s.policy : ''), ['first', 'resolve'].map(function (k) {
            var c = s[k];
            return '<div class="swl-cs-clock"><span>' + CS.esc(c.label) + '<br><span class="swl-cs-small swl-cs-muted">' + (c.met_at ? 'Met ' + CS.esc(CS.full(c.met_at)) : 'Due ' + CS.esc(CS.full(c.due))) + '</span></span>' + CS.pill('sla', c.state) + '</div>';
        }).join('') + (s.resolve.paused_minutes ? '<div class="swl-cs-small swl-cs-muted">Paused ' + CS.dur(s.resolve.paused_minutes) + ' while waiting on the customer.</div>' : ''));

        h += section('Tags', '<div class="mb-1">' + (t.tags.length ? t.tags.map(function (g) { return '<span class="swl-cs-tag">#' + CS.esc(g) + ' <button type="button" class="swl-cs-link-btn" data-untag="' + CS.esc(g) + '" aria-label="Remove tag ' + CS.esc(g) + '">×</button></span>'; }).join(' ') : '<span class="swl-cs-small swl-cs-muted">No tags</span>') + '</div>'
            + '<form class="d-flex" style="gap:6px" data-form="tag"><label class="swl-cs-sr" for="cs_tag_in">Add tag</label><input id="cs_tag_in" class="form-control form-control-sm" placeholder="Add tag" maxlength="40"><button class="btn btn-secondary btn-sm">Add</button></form>');

        h += section('Related records', (D.links.length ? '<ul class="swl-cs-list">' + D.links.map(function (l) {
            return '<li><div class="grow">' + (l.url ? '<a href="' + CS.esc(l.url) + '" target="_blank" rel="noopener">' + CS.esc(l.label + ' ' + l.ref) + '</a>' : CS.esc(l.label + ' ' + l.ref)) + '<div class="swl-cs-small swl-cs-muted">' + CS.esc(l.status) + '</div></div>'
                + '<button type="button" class="swl-cs-link-btn" data-unlink="' + l.id + '" aria-label="Unlink">×</button></li>';
        }).join('') + '</ul>' : '<p class="swl-cs-small swl-cs-muted">None linked.</p>')
            + '<form class="d-flex" style="gap:6px" data-form="link"><label class="swl-cs-sr" for="cs_link_in">Tracking number</label><input id="cs_link_in" class="form-control form-control-sm" placeholder="Link by tracking number"><button class="btn btn-secondary btn-sm">Link</button></form>'
            + (D.customer && D.customer.records && D.customer.records.length ? '<label class="swl-cs-sr" for="cs_link_pick">Link a record</label><select id="cs_link_pick" class="custom-select custom-select-sm mt-1"><option value="">…or pick one of the customer\'s records</option>'
                + D.customer.records.map(function (r) { return '<option value="' + r.type + ':' + r.id + '">' + CS.esc(r.label + ' ' + r.ref + ' · ' + r.status) + '</option>'; }).join('') + '</select>' : ''));

        h += section('Tasks', (D.tasks.length ? '<ul class="swl-cs-list">' + D.tasks.map(function (k) {
            return '<li><input type="checkbox" data-task="' + k.id + '"' + (k.done ? ' checked' : '') + ' aria-label="Done"><div class="grow"' + (k.done ? ' style="text-decoration: line-through;"' : '') + '>' + CS.esc(k.title)
                + '<div class="swl-cs-small ' + (k.overdue ? '' : 'swl-cs-muted') + '">' + CS.esc(k.assignee) + (k.due_at ? ' · due ' + CS.esc(CS.full(k.due_at)) : '') + (k.overdue ? ' · <b>overdue</b>' : '') + '</div></div></li>';
        }).join('') + '</ul>' : '')
            + '<form data-form="task"><label class="swl-cs-sr" for="cs_task_in">New task</label><input id="cs_task_in" class="form-control form-control-sm mb-1" placeholder="Follow-up task, e.g. call the warehouse">'
            + '<div class="d-flex" style="gap:6px"><input type="datetime-local" id="cs_task_due" class="form-control form-control-sm" aria-label="Due"><button class="btn btn-secondary btn-sm">Add</button></div></form>');

        h += section('Approvals', (D.proposals.length ? D.proposals.map(function (p) {
            var d = p.details || {};
            return '<div class="swl-cs-rule"><div class="grow"><div class="swl-cs-rule-name">' + CS.esc(p.kind_label) + ' ' + CS.pill('', p.status === 'pending' ? 'pending_customer' : (p.status === 'approved' ? 'resolved' : 'closed'), p.status) + '</div>'
                + '<div class="swl-cs-small">' + CS.esc(d.summary || '') + (d.amount ? ' · ' + CS.esc(d.amount) : '') + '</div>'
                + '<div class="swl-cs-small swl-cs-muted">Asked by ' + CS.esc(p.requested_by) + ' · ' + CS.esc(CS.when(p.created_at)) + (p.decided_by ? ' · decided by ' + CS.esc(p.decided_by) + (p.decision_note ? ': ' + CS.esc(p.decision_note) : '') : '') + '</div>'
                + (p.status === 'pending' && D.can_decide ? '<div class="mt-1"><button type="button" class="btn btn-primary btn-sm" data-decide="approved" data-p="' + p.id + '" data-hash="' + CS.esc(p.hash) + '">Approve</button> <button type="button" class="btn btn-secondary btn-sm" data-decide="declined" data-p="' + p.id + '" data-hash="' + CS.esc(p.hash) + '">Decline</button></div>' : '')
                + '</div></div>';
        }).join('') : '<p class="swl-cs-small swl-cs-muted">Refunds, waivers, claims and similar changes need an administrator\'s approval. Approval is recorded here; the change itself is made on its own page.</p>')
            + '<button type="button" class="btn btn-secondary btn-sm" data-act="ask_approval">Ask for approval…</button>');

        if (T.manual_rules && T.manual_rules.length) {
            h += section('Run an automation', '<div class="d-flex" style="gap:6px"><label class="swl-cs-sr" for="cs_rule_pick">Automation</label><select id="cs_rule_pick" class="custom-select custom-select-sm">'
                + T.manual_rules.map(function (r) { return '<option value="' + r.id + '">' + CS.esc(r.name) + '</option>'; }).join('') + '</select><button type="button" class="btn btn-secondary btn-sm" data-act="run_rule">Run</button></div>');
        }
        h += section('Followers', D.followers.length ? '<p class="swl-cs-small">' + D.followers.map(function (f) { return CS.esc(f.name); }).join(', ') + '</p>' : '<p class="swl-cs-small swl-cs-muted">Nobody follows this ticket.</p>');
        $('#cs_p_details').html(h);
    }

    function drawCustomer() {
        var c = D.customer, t = D.ticket;
        if (!c) {
            $('#cs_p_customer').html(t.is_internal ? '<div class="swl-cs-empty">Staff request: no customer.</div>'
                : '<dl class="swl-cs-kv"><dt>Name</dt><dd>' + CS.esc(t.customer) + '</dd><dt>E-mail</dt><dd>' + CS.esc(t.customer_email || '–') + '</dd></dl><p class="swl-cs-small swl-cs-muted">No customer account is linked.</p>');
            return;
        }
        var h = '<dl class="swl-cs-kv"><dt>Name</dt><dd><a href="customers_edit.php?user=' + c.id + '" target="_blank" rel="noopener">' + CS.esc(c.name) + '</a></dd>'
            + '<dt>Locker</dt><dd>' + CS.esc(c.locker || '–') + '</dd><dt>E-mail</dt><dd>' + CS.esc(c.email || '–') + '</dd><dt>Phone</dt><dd>' + CS.esc(c.phone || '–') + '</dd>'
            + '<dt>Customer since</dt><dd>' + CS.esc(CS.full(c.since)) + '</dd><dt>Last sign-in</dt><dd>' + CS.esc(CS.full(c.last_login) || '–') + '</dd>'
            + '<dt>Unpaid</dt><dd>' + (c.unpaid_count ? '<b>' + c.unpaid_count + ' record' + (c.unpaid_count > 1 ? 's' : '') + '</b>' : 'None') + '</dd>'
            + '<dt>Satisfaction</dt><dd>' + (c.csat_avg !== null ? c.csat_avg + '/5 from ' + c.csat_n + ' rating' + (c.csat_n > 1 ? 's' : '') : 'No ratings yet') + '</dd></dl>';
        h += section('Recent records', c.records.length ? '<ul class="swl-cs-list">' + c.records.map(function (r) {
            return '<li><div class="grow"><a href="' + CS.esc(r.url) + '" target="_blank" rel="noopener">' + CS.esc(r.label + ' ' + r.ref) + '</a><div class="swl-cs-small swl-cs-muted">' + CS.esc(r.status + (r.paid ? ' · ' + r.paid : '') + ' · ' + CS.when(r.date)) + '</div></div></li>';
        }).join('') + '</ul>' : '<p class="swl-cs-small swl-cs-muted">No records.</p>');
        h += section('Other tickets', c.tickets.length ? '<ul class="swl-cs-list">' + c.tickets.map(function (k) {
            return '<li><div class="grow"><a href="support_ticket.php?id=' + k.id + '">' + CS.esc(k.ref) + '</a> ' + CS.esc(k.subject) + '<div class="swl-cs-small swl-cs-muted">' + CS.esc(CS.when(k.created_at)) + (k.csat ? ' · rated ' + k.csat + '/5' : '') + '</div></div>' + CS.pill('status', k.status) + '</li>';
        }).join('') + '</ul>' : '<p class="swl-cs-small swl-cs-muted">This is their first ticket.</p>');
        $('#cs_p_customer').html(h);
    }

    function drawAi() {
        var t = D.ticket, h = '';
        if (!V.copilot) {
            $('#cs_p_ai').html('<div class="swl-cs-empty">AI help for agents is switched off' + (V.admin ? '. Turn it on in <a href="support_settings.php">Settings</a>.' : '.') + '</div>');
            return;
        }
        h += section('Summary', (t.summary ? '<div class="swl-cs-md swl-cs-small">' + CS.md(t.summary) + '</div>' : '<p class="swl-cs-small swl-cs-muted">No summary yet.</p>')
            + '<div id="cs_sum_out"></div><button type="button" class="btn btn-secondary btn-sm" data-act="summary"><iconify-icon icon="solar:document-text-linear"></iconify-icon> ' + (t.summary ? 'Update summary' : 'Summarise') + '</button>');
        h += section('Classify', '<p class="swl-cs-small swl-cs-muted">Suggests the language, topic, priority and tags. Nothing changes until you apply it.</p><div id="cs_cls_out">' + (classify ? classifyHtml(classify) : '') + '</div>'
            + '<button type="button" class="btn btn-secondary btn-sm" data-act="classify"><iconify-icon icon="solar:tag-linear"></iconify-icon> Suggest</button>');
        if (t.status === 'resolved' || t.status === 'closed') {
            h += section('Help article', '<p class="swl-cs-small swl-cs-muted">Turn this case into a draft help article with personal details removed. An administrator reviews it before customers see it.</p>'
                + '<button type="button" class="btn btn-secondary btn-sm" data-act="kb_draft"><iconify-icon icon="solar:notebook-linear"></iconify-icon> Draft article</button>');
        }
        $('#cs_p_ai').html(h);
    }

    function classifyHtml(c) {
        var t = D.ticket, rows = [];
        var row = function (label, cur, next, field, value) {
            return '<li><div class="grow">' + CS.esc(label) + ': <b>' + CS.esc(next) + '</b>' + (cur === value ? ' <span class="swl-cs-small swl-cs-muted">(already set)</span>' : '') + '</div>'
                + (cur !== value ? '<button type="button" class="btn btn-secondary btn-sm" data-apply="' + field + '" data-v="' + CS.esc(value) + '">Apply</button>' : '') + '</li>';
        };
        rows.push(row('Language', t.language, (V.languages[c.language] || c.language) + ' (' + Math.round(c.language_confidence * 100) + '% sure)', 'language', c.language));
        rows.push(row('Topic', t.category, V.categories[c.category] || c.category, 'category', c.category));
        rows.push(row('Priority', t.priority, V.priorities[c.priority] || c.priority, 'priority', c.priority));
        var newTags = c.tags.filter(function (g) { return t.tags.indexOf(g) === -1; });
        if (newTags.length) { rows.push('<li><div class="grow">Tags: ' + newTags.map(function (g) { return '#' + CS.esc(g); }).join(' ') + '</div><button type="button" class="btn btn-secondary btn-sm" data-apply-tags="' + CS.esc(newTags.join(',')) + '">Add</button></li>'); }
        return '<ul class="swl-cs-list">' + rows.join('') + '</ul><p class="swl-cs-small">Mood: ' + CS.esc(c.sentiment) + (c.reason ? ' · ' + CS.esc(c.reason) : '') + '</p>';
    }

    var EVENTS = {
        'ticket.created': 'Ticket created', 'ticket.updated': 'Changed', 'ticket.reopened': 'Reopened', 'ticket.resolved': 'Resolved', 'ticket.merged': 'Merged',
        'ticket.tags': 'Tags', 'ticket.linked': 'Record linked', 'ticket.unlinked': 'Record unlinked', 'ticket.assigned': 'Assigned',
        'ownership.handoff_queued': 'Waiting for a person', 'ownership.human_active': 'A person took the chat', 'ownership.ai_active': 'Chat back with King',
        'ownership.resolved': 'Chat resolved', 'ownership.transferred': 'Chat taken over', 'conversation.started': 'Chat started', 'king.discarded': 'Late AI answer discarded',
        'ticket.acknowledged': 'Customer acknowledged', 'task.created': 'Task added', 'ai.summary': 'AI summary', 'rule.ran': 'Automation ran',
        'proposal.approved': 'Approved', 'proposal.declined': 'Declined', 'bulk.update': 'Bulk change',
        'sla.warning': 'SLA warning', 'sla.breached': 'SLA missed', 'proposal.created': 'Approval asked', 'proposal.decided': 'Approval decided',
        'ai.classified': 'AI classification', 'task.done': 'Task done', 'task.reopened': 'Task reopened', 'csat.received': 'Rating received'
    };

    function drawHistory() {
        if (!D.history.length) { $('#cs_p_history').html('<div class="swl-cs-empty">No history.</div>'); return; }
        $('#cs_p_history').html('<ul class="swl-cs-list">' + D.history.map(function (e) {
            var d = $.extend({}, e.data || {}), bits = [];
            if (d.changes && typeof d.changes === 'object') {
                Object.keys(d.changes).forEach(function (k) {
                    var c = d.changes[k] || {};
                    if (['summary', 'resolution_summary', 'language_source'].indexOf(k) > -1) { bits.push(k.replace(/_/g, ' ') + ' updated'); return; }
                    bits.push(k.replace(/_id$/, '').replace(/_/g, ' ') + ': ' + label(k, c.from) + ' → ' + label(k, c.to));
                });
                delete d.changes;
            }
            Object.keys(d).slice(0, 6).forEach(function (k) {
                var v = d[k];
                if (v === null || v === '' || (typeof v === 'object' && !Array.isArray(v))) {
                    if (v && typeof v === 'object' && 'from' in v) { bits.push(k.replace(/_/g, ' ') + ': ' + (v.from || '–') + ' → ' + (v.to || '–')); }
                    return;
                }
                bits.push(k.replace(/_/g, ' ') + ': ' + (Array.isArray(v) ? v.join(', ') : String(v)));
            });
            return '<li><div class="grow"><b>' + CS.esc(EVENTS[e.type] || e.type.replace(/[._]/g, ' ')) + '</b> · ' + CS.esc(e.actor)
                + (bits.length ? '<div class="swl-cs-small swl-cs-muted">' + CS.esc(bits.join(' · ').slice(0, 300)) + '</div>' : '')
                + '<div class="swl-cs-small swl-cs-muted">' + CS.esc(CS.full(e.at)) + '</div></div></li>';
        }).join('') + '</ul>');
    }

    function label(field, v) {
        if (v === null || v === undefined || v === '' || v === 0 || v === '0') { return '–'; }
        var maps = { status: V.statuses, priority: V.priorities, category: V.categories, language: V.languages, team_id: arr2obj(V.teams), assignee_id: arr2obj(V.agents) };
        return (maps[field] && maps[field][v]) || String(v);
    }

    function bindSide() {
        $('.swl-cs-side .swl-cs-tabs [data-tab]').on('click', function () {
            var t = $(this).data('tab');
            $('.swl-cs-side .swl-cs-tabs [data-tab]').removeClass('is-on').attr('aria-selected', 'false');
            $(this).addClass('is-on').attr('aria-selected', 'true');
            $('.swl-cs-side [data-pane]').prop('hidden', true).filter('[data-pane="' + t + '"]').prop('hidden', false);
        });

        $(document).on('change', '#cs_p_details [data-field]', function () {
            var f = $(this).data('field'), ch = {};
            ch[f] = $(this).val();
            if (f === 'status' && (ch.status === 'resolved' || ch.status === 'closed') && !D.ticket.resolution_summary) {
                askResolution(function (code, summary) { update({ status: ch.status, resolution_code: code, resolution_summary: summary }); });
                return;
            }
            update(ch);
        });

        $(document).on('click', '[data-act]', function () {
            var a = $(this).data('act');
            var t = D && D.ticket;
            if (!t) { return; }
            switch (a) {
                case 'accept': post({ action: 'accept' }); break;
                case 'takeover': CS.confirm('Take over this chat?', 'The colleague who has it is replaced. They will see the change.', 'Take over', function () { post({ action: 'accept', takeover: 1 }); }); break;
                case 'requeue': post({ action: 'requeue' }); break;
                case 'release': CS.confirm('Hand the chat back to ' + V.king + '?', 'The customer is told the AI assistant is back. They can ask for a person again at any time.', 'Hand back', function () { post({ action: 'release' }); }); break;
                case 'follow': post({ action: 'follow', on: D.following ? 0 : 1 }, function () { load(); }); break;
                case 'mine': update({ assignee_id: V.me }); break;
                case 'archive': update({ disposition: 'archived' }); break;
                case 'unarchive': update({ disposition: 'none' }); break;
                case 'spam': CS.confirm('Mark as spam?', 'It leaves the inbox. Rules and notifications stop for it.', 'Mark as spam', function () { update({ disposition: 'spam' }); }); break;
                case 'trash': CS.confirm('Move to trash?', 'It leaves the inbox. You can move it back from the Trash view.', 'Move to trash', function () { update({ disposition: 'trash' }); }); break;
                case 'merge': merge(); break;
                case 'run_rule': post({ action: 'run_rule', rule_id: $('#cs_rule_pick').val() }, function (r) {
                    var n = (r.result || []).filter(function (e) { return e.outcome === 'ok' || e.outcome === 'partial'; }).length;
                    CS.toast(n ? 'Automation ran.' : 'The automation\'s conditions did not match this ticket, so nothing changed.', n ? 'success' : 'info');
                }); break;
                case 'ask_approval': askApproval(); break;
                case 'summary': aiSummary(); break;
                case 'classify': aiClassify(); break;
                case 'kb_draft':
                    CS.post(URL_, { action: 'ai_kb', id: ID }).done(function (r) {
                        if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'No draft.'); return; }
                        CS.toast('Draft article “' + r.title + '” saved for review in Settings › Knowledge base.', 'success');
                    }).fail(CS.fail);
                    break;
            }
        });

        $(document).on('submit', '#cs_p_details [data-form]', function (e) {
            e.preventDefault();
            var f = $(this).data('form');
            if (f === 'tag') {
                var g = $('#cs_tag_in').val().trim();
                if (g) { post({ action: 'tag', tag: g }, function () { load(); }); }
            } else if (f === 'link') {
                var ref = $('#cs_link_in').val().trim();
                if (ref) { post({ action: 'link_ref', ref: ref }, function () { load(); }); }
            } else if (f === 'task') {
                var title = $('#cs_task_in').val().trim();
                if (title) { post({ action: 'task', title: title, due_at: ($('#cs_task_due').val() || '').replace('T', ' ') }, function () { load(); }); }
            }
        });
        $(document).on('click', '[data-untag]', function () { post({ action: 'tag', tag: $(this).data('untag'), remove: 1 }, function () { load(); }); });
        $(document).on('click', '[data-unlink]', function () { post({ action: 'unlink', link_id: $(this).data('unlink') }, function () { load(); }); });
        $(document).on('change', '#cs_link_pick', function () {
            var v = this.value.split(':');
            if (v.length === 2) { post({ action: 'link', type: v[0], entity_id: v[1] }, function () { load(); }); }
        });
        $(document).on('change', '[data-task]', function () { post({ action: 'task_done', task_id: $(this).data('task'), done: this.checked ? 1 : 0 }, function () { load(); }); });
        $(document).on('click', '[data-decide]', function () {
            var $b = $(this), decision = $b.data('decide');
            var go = function (note) { post({ action: 'proposal', proposal_id: $b.data('p'), decision: decision, hash: $b.data('hash'), note: note || '' }); };
            if (window.Swal) {
                Swal.fire({ title: decision === 'approved' ? 'Approve this request?' : 'Decline this request?', input: 'text', inputPlaceholder: 'Note (optional)', showCancelButton: true, confirmButtonText: decision === 'approved' ? 'Approve' : 'Decline' })
                    .then(function (r) { if (r.isConfirmed) { go(r.value); } });
            } else { go(''); }
        });
        $(document).on('click', '[data-apply]', function () { var ch = {}; ch[$(this).data('apply')] = String($(this).data('v')); update(ch, function () { drawAi(); }); });
        $(document).on('click', '[data-apply-tags]', function () {
            var tags = String($(this).data('apply-tags')).split(',');
            var next = function () { var g = tags.shift(); if (!g) { load(); return; } CS.post(URL_, { action: 'tag', id: ID, tag: g }).always(next); };
            next();
        });
    }

    function merge() {
        if (!window.Swal) { return; }
        Swal.fire({
            title: 'Merge into another ticket', input: 'text', inputPlaceholder: 'Reference of the ticket to keep, e.g. ' + (D.similar[0] ? D.similar[0].ref : 'CS-…'),
            text: 'Messages, tags, links and followers move to that ticket and this one is closed. Only tickets of the same customer can be merged.',
            showCancelButton: true, confirmButtonText: 'Merge'
        }).then(function (r) {
            if (!r.isConfirmed || !r.value) { return; }
            CS.post(URL_, { action: 'merge', id: ID, target_ref: r.value.trim() }).done(function (x) {
                if (!x || !x.ok) { CS.toast(x && x.error ? x.error : 'Not merged.'); return; }
                location.href = 'support_ticket.php?id=' + x.target_id;
            }).fail(CS.fail);
        });
    }

    function askApproval() {
        if (!window.Swal) { return; }
        var kinds = T.proposal_kinds || { other: 'Other' };
        Swal.fire({
            title: 'Ask an administrator to approve',
            html: '<select id="cs_pk" class="custom-select mb-2">' + Object.keys(kinds).map(function (k) { return '<option value="' + k + '">' + CS.esc(kinds[k]) + '</option>'; }).join('') + '</select>'
                + '<textarea id="cs_ps" class="form-control mb-2" rows="3" placeholder="What should be approved, and why"></textarea>'
                + '<input id="cs_pa" class="form-control" placeholder="Amount, if any (e.g. GHS 120)">',
            showCancelButton: true, confirmButtonText: 'Send for approval', focusConfirm: false,
            preConfirm: function () {
                var s = $('#cs_ps').val().trim();
                if (!s) { Swal.showValidationMessage('Say what should be approved.'); return false; }
                return { kind: $('#cs_pk').val(), summary: s, amount: $('#cs_pa').val().trim() };
            }
        }).then(function (r) {
            if (r.isConfirmed) { post($.extend({ action: 'proposal_create' }, r.value), function () { CS.toast('Sent to administrators for approval.', 'success'); }); }
        });
    }

    function aiSummary() {
        $('#cs_sum_out').html('<p class="swl-cs-small"><span class="swl-cs-typing" aria-hidden="true"><span></span><span></span><span></span></span> Summarising…</p>');
        CS.post(URL_, { action: 'ai_summary', id: ID, save: 0 }).done(function (r) {
            if (!r || !r.ok) { $('#cs_sum_out').empty(); CS.toast(r && r.error ? r.error : 'No summary.'); return; }
            $('#cs_sum_out').html('<div class="swl-cs-ai-preview"><div class="swl-cs-md">' + CS.md(r.text) + '</div><button type="button" class="btn btn-primary btn-sm" id="cs_sum_save">Save to ticket</button> <button type="button" class="btn btn-secondary btn-sm" id="cs_sum_x">Discard</button></div>');
            $('#cs_sum_save').on('click', function () { update({ summary: r.text }, function () { CS.toast('Summary saved.', 'success'); }); });
            $('#cs_sum_x').on('click', function () { $('#cs_sum_out').empty(); });
        }).fail(function (x) { $('#cs_sum_out').empty(); CS.fail(x); });
    }

    function aiClassify() {
        $('#cs_cls_out').html('<p class="swl-cs-small"><span class="swl-cs-typing" aria-hidden="true"><span></span><span></span><span></span></span> Reading the case…</p>');
        CS.post(URL_, { action: 'ai_classify', id: ID }).done(function (r) {
            if (!r || !r.ok) { $('#cs_cls_out').empty(); CS.toast(r && r.error ? r.error : 'No suggestion.'); return; }
            classify = r;
            $('#cs_cls_out').html(classifyHtml(r));
        }).fail(function (x) { $('#cs_cls_out').empty(); CS.fail(x); });
    }
})(window.jQuery);
