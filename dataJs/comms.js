"use strict";

// ============================================================================
// Communication Center (views/comms/center.php). Each tab loads its own data
// from ajax/comms/comms_ajax.php the first time it is shown.
// ============================================================================

(function ($) {
    var OPS = window.OPS, esc = OPS.esc, CFG = window.CDP_COMMS || {};
    var URL = 'ajax/comms/comms_ajax.php';
    var meta = { events: {}, channels: { whatsapp: 'WhatsApp', sms: 'SMS', email: 'E-mail' } };
    var loaded = {};
    var body = function (k) { return $('[data-pane-body="' + k + '"]'); };
    var evLabel = function (k) { return (meta.events[k] && meta.events[k].label) || (k === 'campaign' ? 'Campaign' : k); };

    function api(action, data, post) {
        var d = $.extend({ action: action }, data || {});
        return post ? OPS.post(URL + '?action=' + action, d) : OPS.get(URL, d);
    }

    // ---------------------------------------------------------------- overview
    function overview() {
        api('overview', { days: 30 }).then(function (r) {
            var d = r.data, sent = 0, failed = 0;
            $.each(d.by_status, function (ch, st) { sent += st.sent || 0; failed += st.failed || 0; });
            var h = '';
            if (!d.enabled) { h += '<div class="swl-ops-banner">Automations are switched off. ' + (CFG.isAdmin ? 'Turn them on in Settings, then choose events and channels under Automations.' : 'An administrator can switch them on.') + '</div>'; }
            h += OPS.kpis([
                { label: 'Sent (30 days)', value: OPS.num(sent) },
                { label: 'Failed (30 days)', value: OPS.num(d.failed), sub: 'Retry from the Outbox' },
                { label: 'Waiting to send', value: OPS.num(d.queued) },
                { label: 'Chats waiting for a person', value: OPS.num(d.open_threads), sub: OPS.num(d.bot_threads) + ' handled by the bot' },
                { label: 'Satisfaction', value: d.survey.csat === null ? '—' : d.survey.csat + '%', sub: d.survey.answered + ' of ' + d.survey.sent + ' answered' + (d.survey.avg ? ', avg ' + d.survey.avg + '/5' : '') },
                { label: 'Deliveries booked ahead', value: OPS.num(d.slots_pending) },
                { label: 'Marketing consent', value: OPS.num(d.consent.whatsapp), sub: 'WhatsApp · ' + OPS.num(d.consent.sms) + ' SMS · ' + OPS.num(d.consent.email) + ' e-mail' },
                { label: 'Opted out', value: OPS.num(d.optouts), sub: 'WhatsApp off or all messages off' }
            ]);
            var rows = d.by_event.map(function (e) { return { ev: evLabel(e.event_key), c: e.c, s: e.sent }; });
            h += '<div class="row"><div class="col-lg-7"><h5 class="card-title">Messages by type (30 days)</h5>' + OPS.table([
                { key: 'ev', label: 'Type' }, { key: 'c', label: 'Queued' }, { key: 's', label: 'Sent' }], rows, 'No messages yet.') + '</div>';
            var chRows = [];
            $.each(d.by_status, function (ch, st) { chRows.push({ ch: meta.channels[ch] || ch, sent: st.sent || 0, failed: st.failed || 0, other: (st.cancelled || 0) + (st.queued || 0) }); });
            h += '<div class="col-lg-5"><h5 class="card-title">By channel</h5>' + OPS.table([{ key: 'ch', label: 'Channel' }, { key: 'sent', label: 'Sent' }, { key: 'failed', label: 'Failed' }, { key: 'other', label: 'Queued / cancelled' }], chRows, 'No messages yet.')
                + '<p class="swl-ops-muted mt-3">Worker last ran: ' + esc(d.worker_last_run || 'never - schedule cron/comms_worker.php every 5 minutes') + '</p></div></div>';
            body('overview').html(h);
        });
    }

    // ------------------------------------------------------------- automations
    function automations() {
        api('settings').then(function (r) {
            meta.events = r.events;
            var dis = r.is_admin ? '' : ' disabled';
            var h = '<h5 class="card-title">Which messages go out, on which channel</h5><p class="swl-ops-muted">Ticked messages are sent automatically by the worker. Customers can still switch any of them off for themselves.</p>';
            var rows = Object.keys(r.events).map(function (k) {
                var ev = r.events[k];
                return '<tr><td><strong>' + esc(ev.label) + '</strong><div class="swl-ops-muted swl-ops-small">' + esc(ev.help) + '</div></td>' + Object.keys(r.channels).map(function (ch) {
                    var on = r.rules[k] && r.rules[k][ch];
                    return '<td class="text-center"><input type="checkbox" class="cm-rule" aria-label="' + esc(ev.label + ' by ' + r.channels[ch]) + '" data-ev="' + esc(k) + '" data-ch="' + ch + '"' + (on ? ' checked' : '') + dis + '></td>';
                }).join('') + '</tr>';
            }).join('');
            h += '<div class="table-responsive"><table class="table table-sm swl-ops-matrix"><thead><tr><th scope="col">Message</th>' + Object.keys(r.channels).map(function (ch) { return '<th scope="col" class="text-center">' + esc(r.channels[ch]) + '</th>'; }).join('') + '</tr></thead><tbody>' + rows + '</tbody></table></div>';
            if (r.is_admin) { h += '<button type="button" class="btn btn-primary" id="cm_save_rules">Save automations</button>'; }
            var evOpts = [['', 'Detect automatically'], ['none', 'Never announce']].concat(Object.keys(r.events).map(function (k) { return [k, r.events[k].label]; }));
            h += '<hr><h5 class="card-title">How statuses are read</h5><p class="swl-ops-muted">Each shipment status is matched to a message by its name. Change the match where the name is misleading.</p>';
            h += OPS.table([
                { label: 'Status', render: function (s) { return '<span class="label" style="background:' + esc(s.color || '') + '">' + esc(s.name) + '</span>'; } },
                { label: 'Detected as', render: function (s) { return esc(s.auto === '' ? 'Shipment status alert' : (s.auto === 'none' ? 'Never announced' : evLabel(s.auto))); } },
                { label: 'Use instead', render: function (s) { return '<label class="sr-only" for="cm_map_' + s.id + '">Use instead for ' + esc(s.name) + '</label>' + OPS.select('cm_map_' + s.id, evOpts, s.override, 'data-sid="' + s.id + '" class="custom-select cm-map"' + dis); } }
            ], r.statuses, 'No statuses found.');
            if (r.is_admin) { h += '<button type="button" class="btn btn-secondary mt-3" id="cm_save_map">Save status matching</button>'; }
            body('automations').html(h);
        });
    }
    $(document).on('click', '#cm_save_rules', function () {
        var rules = {};
        $('.cm-rule').each(function () { var e = $(this).data('ev'); rules[e] = rules[e] || {}; rules[e][$(this).data('ch')] = this.checked ? 1 : 0; });
        api('save_rules', { rules: JSON.stringify(rules) }, true).then(function (r) { OPS.result(r, 'Automations saved'); });
    });
    $(document).on('click', '#cm_save_map', function () {
        var map = {};
        $('.cm-map').each(function () { if (this.value) { map[$(this).data('sid')] = this.value; } });
        api('save_status_map', { map: JSON.stringify(map) }, true).then(function (r) { OPS.result(r, 'Status matching saved'); });
    });

    // --------------------------------------------------------------- templates
    var tpl = { rows: [], placeholders: [], canEdit: false };
    function templates() {
        api('templates').then(function (r) {
            meta.events = r.events; tpl.rows = r.templates; tpl.placeholders = r.placeholders; tpl.canEdit = r.can_edit;
            var evOpts = Object.keys(r.events).map(function (k) { return [k, r.events[k].label]; });
            var h = '<div class="row">' + OPS.field('tp_event', 'Message', OPS.select('tp_event', evOpts, evOpts[0][0]), 'col-md-6')
                + OPS.field('tp_channel', 'Channel', OPS.select('tp_channel', [['whatsapp', 'WhatsApp'], ['sms', 'SMS'], ['email', 'E-mail']], 'whatsapp'), 'col-md-3')
                + OPS.field('tp_language', 'Language', OPS.select('tp_language', [['en', 'English'], ['fr', 'French'], ['es', 'Spanish'], ['ar', 'Arabic']], 'en'), 'col-md-3') + '</div>'
                + '<div id="tp_editor"></div>';
            body('templates').html(h);
            showTemplate();
        });
    }
    function showTemplate() {
        var ev = $('#tp_event').val(), ch = $('#tp_channel').val(), lang = $('#tp_language').val();
        var t = tpl.rows.filter(function (x) { return x.event_key === ev && x.channel === ch && x.language === lang; })[0] || { subject: '', body: '', active: 1 };
        var dis = tpl.canEdit ? '' : ' disabled';
        var h = '<div class="row">';
        if (ch === 'email') { h += OPS.field('tp_subject', 'Subject', OPS.input('tp_subject', t.subject, dis.trim() + ' maxlength="190"'), 'col-12'); }
        h += OPS.field('tp_body', 'Message', '<textarea id="tp_body" class="form-control" rows="7"' + dis + '>' + esc(t.body) + '</textarea><small class="swl-ops-muted" id="tp_chars"></small>', 'col-12');
        if (ch === 'whatsapp') {
            h += '<div class="col-12"><p class="swl-ops-muted">WhatsApp Cloud API only: outside the 24-hour window Meta delivers only an approved template. Give its name and which placeholders fill {{1}}, {{2}}… in order.</p></div>'
                + OPS.field('tp_wa_name', 'Approved template name', OPS.input('tp_wa_name', t.wa_template_name, dis.trim()), 'col-md-4')
                + OPS.field('tp_wa_lang', 'Template language code', OPS.input('tp_wa_lang', t.wa_template_lang || 'en', dis.trim()), 'col-md-3')
                + OPS.field('tp_wa_params', 'Placeholders in order', OPS.input('tp_wa_params', t.wa_template_params, dis.trim() + ' placeholder="first_name,tracking,status"'), 'col-md-5');
        }
        h += '<div class="col-12"><div class="custom-control custom-checkbox mb-3"><input type="checkbox" class="custom-control-input" id="tp_active"' + (Number(t.active) ? ' checked' : '') + dis + '><label class="custom-control-label" for="tp_active">Active</label></div></div></div>';
        h += '<p class="swl-ops-muted">Placeholders: ' + tpl.placeholders.map(function (p) { return '<code>{' + esc(p) + '}</code>'; }).join(' ') + '</p>';
        h += '<button type="button" class="btn btn-secondary" id="tp_preview">Preview</button> ' + (tpl.canEdit ? '<button type="button" class="btn btn-primary" id="tp_save">Save template</button>' : '');
        h += '<div id="tp_preview_out" class="mt-3"></div>';
        $('#tp_editor').html(h);
        countChars();
    }
    function countChars() {
        var n = ($('#tp_body').val() || '').length, ch = $('#tp_channel').val();
        $('#tp_chars').text(n + ' characters' + (ch === 'sms' ? ' · about ' + Math.max(1, Math.ceil(n / 153)) + ' SMS part(s) before placeholders are filled' : ''));
    }
    $(document).on('change', '#tp_event, #tp_channel, #tp_language', showTemplate);
    $(document).on('input', '#tp_body', countChars);
    $(document).on('click', '#tp_preview', function () {
        api('preview_template', { subject: $('#tp_subject').val() || '', body: $('#tp_body').val() }, true).then(function (r) {
            $('#tp_preview_out').html('<div class="swl-ops-insight">' + (r.subject ? '<b>' + esc(r.subject) + '</b><br>' : '') + esc(r.body).replace(/\n/g, '<br>') + '<div class="swl-ops-muted swl-ops-small mt-2">' + r.chars + ' characters with sample data</div></div>');
        });
    });
    $(document).on('click', '#tp_save', function () {
        api('save_template', { event_key: $('#tp_event').val(), channel: $('#tp_channel').val(), language: $('#tp_language').val(), subject: $('#tp_subject').val() || '',
            body: $('#tp_body').val(), wa_template_name: $('#tp_wa_name').val() || '', wa_template_lang: $('#tp_wa_lang').val() || '', wa_template_params: $('#tp_wa_params').val() || '',
            active: $('#tp_active').is(':checked') ? 1 : 0 }, true).then(function (r) { if (OPS.result(r, 'Template saved')) { loaded.templates = false; templates(); } });
    });

    // ------------------------------------------------------- customer picker
    function picker(prefix, onPick) {
        return '<div class="form-group"><label for="' + prefix + '_q">Customer</label><input id="' + prefix + '_q" class="form-control cm-picker" data-prefix="' + prefix + '" placeholder="Name, locker, phone or e-mail" autocomplete="off">'
            + '<div id="' + prefix + '_results" class="swl-ops-list"></div><input type="hidden" id="' + prefix + '_id"></div>';
    }
    var pickHandlers = {};
    $(document).on('input', '.cm-picker', OPS.debounce(function () {
        var p = $(this).data('prefix'), q = this.value;
        api('customers', { q: q }).then(function (r) {
            $('#' + p + '_results').html(r.rows.map(function (c) {
                return '<li><button type="button" class="btn btn-link p-0 cm-pick" data-prefix="' + p + '" data-id="' + c.id + '" data-label="' + esc(c.name + ' (' + (c.locker || c.phone || '') + ')') + '">'
                    + esc(c.name) + '</button> <span class="swl-ops-muted">' + esc([c.locker, c.phone, c.email].filter(Boolean).join(' · ')) + '</span></li>';
            }).join('') || (q.length > 1 ? '<li class="swl-ops-muted">No customer found.</li>' : ''));
        });
    }, 300));
    $(document).on('click', '.cm-pick', function () {
        var p = $(this).data('prefix');
        $('#' + p + '_id').val($(this).data('id'));
        $('#' + p + '_q').val($(this).data('label'));
        $('#' + p + '_results').empty();
        if (pickHandlers[p]) { pickHandlers[p]($(this).data('id')); }
    });

    // -------------------------------------------------------------------- send
    function send() {
        api('settings').then(function (r) {
            meta.events = r.events;
            var evOpts = [['custom', 'My own message']].concat(Object.keys(r.events).map(function (k) { return [k, 'Template: ' + r.events[k].label]; }));
            var h = '<p class="swl-ops-muted">Send one message now. It is logged in the customer\'s history and respects their preferences.</p><div class="row"><div class="col-lg-6">' + picker('sd') + '</div><div class="col-lg-6">'
                + '<fieldset class="form-group"><legend class="col-form-label">Channels</legend>' + ['whatsapp', 'sms', 'email'].map(function (c) {
                    return '<div class="custom-control custom-checkbox custom-control-inline"><input type="checkbox" class="custom-control-input sd-ch" id="sd_ch_' + c + '" value="' + c + '"' + (c === 'whatsapp' ? ' checked' : '') + '><label class="custom-control-label" for="sd_ch_' + c + '">' + meta.channels[c] + '</label></div>';
                }).join('') + '</fieldset>'
                + '<div class="form-group"><label for="sd_event">Message</label>' + OPS.select('sd_event', evOpts, 'custom') + '</div></div></div>'
                + '<div class="row">' + OPS.field('sd_tracking', 'Tracking number (optional)', OPS.input('sd_tracking', ''), 'col-md-6') + OPS.field('sd_status', 'Status text (optional)', OPS.input('sd_status', ''), 'col-md-6')
                + '<div class="col-12" id="sd_custom">' + OPS.field('sd_subject', 'Subject (e-mail)', OPS.input('sd_subject', ''), 'col-12 px-0') + OPS.field('sd_body', 'Message', '<textarea id="sd_body" class="form-control" rows="5" maxlength="4000"></textarea>', 'col-12 px-0') + '</div></div>'
                + '<button type="button" class="btn btn-primary" id="sd_send">Send now</button>';
            body('send').html(h);
        });
    }
    $(document).on('change', '#sd_event', function () { $('#sd_custom').toggle(this.value === 'custom'); });
    $(document).on('click', '#sd_send', function () {
        var uid = $('#sd_id').val();
        if (!uid) { OPS.toast('Choose a customer first', 'error'); return; }
        var ch = $('.sd-ch:checked').map(function () { return this.value; }).get();
        api('manual_send', { user_id: uid, channels: ch, event: $('#sd_event').val(), tracking: $('#sd_tracking').val(), status: $('#sd_status').val(), subject: $('#sd_subject').val(), body: $('#sd_body').val() }, true)
            .then(function (r) {
                if (r.ok) {
                    var d = r.delivered || {};
                    OPS.toast((d.sent || 0) + ' sent' + (d.failed || d.retry ? ', ' + ((d.failed || 0) + (d.retry || 0)) + ' not delivered yet (see Outbox)' : ''));
                    $('#sd_body').val('');
                } else { OPS.toast(r.error, 'error'); }
            });
    });

    // ---------------------------------------------------------------- segments
    var segFields = {};
    function segments() {
        api('segments').then(function (r) {
            segFields = r.fields;
            var h = '<div class="row"><div class="col-lg-5"><h5 class="card-title">Saved segments</h5>' + OPS.table([
                { label: 'Name', render: function (s) { return '<strong>' + esc(s.name) + '</strong><div class="swl-ops-muted swl-ops-small">' + esc(s.description || '') + '</div>'; } },
                { label: 'Customers', render: function (s) { return OPS.num(s.last_count); } },
                { label: '', render: function (s) { return '<button type="button" class="btn btn-sm btn-secondary sg-edit" data-row="' + esc(JSON.stringify(s)) + '">Edit</button>' + (CFG.isAdmin ? ' <button type="button" class="btn btn-sm btn-outline-secondary sg-delete" data-id="' + s.id + '" aria-label="Delete segment ' + esc(s.name) + '"><iconify-icon icon="solar:trash-bin-trash-linear"></iconify-icon></button>' : ''); } }
            ], r.rows, 'No segments yet.') + '</div><div class="col-lg-7"><h5 class="card-title">Build a segment</h5><div id="sg_builder"></div></div></div>';
            body('segments').html(h);
            builder({ id: 0, name: '', description: '', definition: { match: 'all', rules: [] } });
        });
    }
    function ruleRow(rule, i) {
        rule = rule || { field: 'country', op: 'eq', value: '' };
        var f = segFields[rule.field] || { type: 'text' };
        var ops = f.type === 'number' ? [['gte', 'at least'], ['lte', 'at most'], ['eq', 'exactly'], ['gt', 'more than'], ['lt', 'less than']]
            : (f.type === 'text' ? [['eq', 'is'], ['ne', 'is not'], ['contains', 'contains']] : [['eq', 'is']]);
        var val = f.type === 'bool' ? OPS.select('sg_v' + i, [['1', 'Yes'], ['0', 'No']], rule.value, 'class="custom-select sg-v"')
            : (f.type === 'channel' ? OPS.select('sg_v' + i, [['whatsapp', 'WhatsApp'], ['sms', 'SMS'], ['email', 'E-mail']], rule.value, 'class="custom-select sg-v"')
            : OPS.input('sg_v' + i, rule.value, 'class="form-control sg-v" type="' + (f.type === 'number' ? 'number' : 'text') + '"'));
        return '<div class="row sg-rule align-items-end">' + OPS.field('sg_f' + i, 'Field', OPS.select('sg_f' + i, Object.keys(segFields).map(function (k) { return [k, segFields[k].label]; }), rule.field, 'class="custom-select sg-f"'), 'col-md-4')
            + OPS.field('sg_o' + i, 'Condition', OPS.select('sg_o' + i, ops, rule.op, 'class="custom-select sg-o"'), 'col-md-3') + OPS.field('sg_v' + i, 'Value', val, 'col-md-4')
            + '<div class="col-md-1"><div class="form-group"><button type="button" class="btn btn-outline-secondary sg-remove" aria-label="Remove condition"><iconify-icon icon="solar:trash-bin-trash-linear"></iconify-icon></button></div></div></div>';
    }
    function builder(seg) {
        var h = '<input type="hidden" id="sg_id" value="' + (seg.id || 0) + '"><div class="row">' + OPS.field('sg_name', 'Name', OPS.input('sg_name', seg.name, 'maxlength="120"'), 'col-md-6')
            + OPS.field('sg_desc', 'Description', OPS.input('sg_desc', seg.description, 'maxlength="255"'), 'col-md-6') + '</div>'
            + '<div class="form-group"><label for="sg_match">Customers who match</label>' + OPS.select('sg_match', [['all', 'all of these conditions'], ['any', 'any of these conditions']], seg.definition.match) + '</div>'
            + '<div id="sg_rules">' + (seg.definition.rules || []).map(ruleRow).join('') + '</div>'
            + '<button type="button" class="btn btn-secondary btn-sm" id="sg_add">Add condition</button> <button type="button" class="btn btn-secondary btn-sm" id="sg_preview">Count customers</button> '
            + '<button type="button" class="btn btn-primary btn-sm" id="sg_save">Save segment</button><div id="sg_out" class="mt-3"></div>'
            + '<p class="swl-ops-muted mt-2">Only active customer accounts are counted. Campaigns additionally skip anyone without marketing consent for the channel.</p>';
        $('#sg_builder').html(h);
    }
    function segDef() {
        return { match: $('#sg_match').val(), rules: $('.sg-rule').map(function () {
            return { field: $(this).find('.sg-f').val(), op: $(this).find('.sg-o').val(), value: $(this).find('.sg-v').val() };
        }).get() };
    }
    $(document).on('click', '#sg_add', function () { $('#sg_rules').append(ruleRow(null, Date.now())); });
    $(document).on('click', '.sg-remove', function () { $(this).closest('.sg-rule').remove(); });
    $(document).on('change', '.sg-f', function () {
        var $r = $(this).closest('.sg-rule'), i = Date.now();
        $r.replaceWith(ruleRow({ field: this.value, op: 'eq', value: '' }, i));
    });
    $(document).on('click', '.sg-edit', function () { var s = $(this).data('row'); s.definition = typeof s.definition === 'string' ? JSON.parse(s.definition) : s.definition; builder(s); });
    $(document).on('click', '#sg_preview', function () {
        api('preview_segment', { definition: JSON.stringify(segDef()) }, true).then(function (r) {
            $('#sg_out').html('<p><strong>' + OPS.num(r.count) + '</strong> customers match.</p>' + OPS.table([{ key: 'name', label: 'Name' }, { key: 'locker', label: 'Locker' }, { key: 'city', label: 'City' }, { key: 'country', label: 'Country' }], r.sample, ''));
        });
    });
    $(document).on('click', '#sg_save', function () {
        api('save_segment', { id: $('#sg_id').val(), name: $('#sg_name').val(), description: $('#sg_desc').val(), definition: JSON.stringify(segDef()) }, true)
            .then(function (r) { if (OPS.result(r, 'Segment saved: ' + OPS.num(r.count) + ' customers')) { segments(); } });
    });
    $(document).on('click', '.sg-delete', function () {
        var id = $(this).data('id');
        OPS.confirm('Delete this segment?').then(function () { api('delete_segment', { id: id }, true).then(function (r) { if (OPS.result(r, 'Deleted')) { segments(); } }); });
    });

    // --------------------------------------------------------------- campaigns
    function campaigns() {
        api('campaigns').then(function (r) {
            var segOpts = [['', 'Choose…']].concat(r.segments.map(function (s) { return [s.id, s.name + ' (' + OPS.num(s.last_count) + ')']; }));
            var h = '<div class="swl-ops-banner is-info">Promotional messages reach only customers who agreed to receive them on that channel. Each message carries an opt-out (reply STOP, or the preferences link in e-mails).'
                + (r.needs_approval ? ' A second administrator approves every campaign before it is sent.' : '') + '</div>';
            h += OPS.table([
                { label: 'Campaign', render: function (c) { return '<strong>' + esc(c.name) + '</strong><div class="swl-ops-muted swl-ops-small">' + esc(c.segment_name || '') + ' · ' + esc(c.channels) + '</div>'; } },
                { label: 'Status', render: function (c) { return OPS.chip(c.status, OPS.statusTone(c.status)) + (c.scheduled_at ? '<div class="swl-ops-small swl-ops-muted">' + esc(c.scheduled_at) + '</div>' : ''); } },
                { label: 'Queued', render: function (c) { return OPS.num(c.queued_count) + (Number(c.excluded_count) ? '<div class="swl-ops-small swl-ops-muted">' + OPS.num(c.excluded_count) + ' skipped (no consent / contact)</div>' : ''); } },
                { label: 'Sent', render: function (c) { return OPS.num(c.sent_count); } },
                { label: 'Failed', render: function (c) { return OPS.num(c.failed_count); } },
                { label: '', render: function (c) {
                    var b = '';
                    if (c.status === 'draft') {
                        b += '<button type="button" class="btn btn-sm btn-secondary cp-edit" data-row="' + esc(JSON.stringify(c)) + '">Edit</button> ';
                        if (r.is_admin && (!r.needs_approval || Number(c.created_by) !== r.me)) { b += '<button type="button" class="btn btn-sm btn-primary cp-approve" data-id="' + c.id + '">Approve &amp; send</button> '; }
                    }
                    if (['draft', 'scheduled', 'sent'].indexOf(c.status) !== -1) { b += '<button type="button" class="btn btn-sm btn-outline-secondary cp-cancel" data-id="' + c.id + '">Cancel unsent</button>'; }
                    return b;
                } }
            ], r.rows, 'No campaigns yet.');
            h += '<hr><h5 class="card-title" id="cp_form_title">New campaign</h5><input type="hidden" id="cp_id" value="0"><div class="row">'
                + OPS.field('cp_name', 'Name', OPS.input('cp_name', '', 'maxlength="160"'), 'col-md-6') + OPS.field('cp_segment', 'Send to', OPS.select('cp_segment', segOpts, ''), 'col-md-6')
                + '<div class="col-md-6"><fieldset class="form-group"><legend class="col-form-label">Channels</legend>' + ['whatsapp', 'sms', 'email'].map(function (c) {
                    return '<div class="custom-control custom-checkbox custom-control-inline"><input type="checkbox" class="custom-control-input cp-ch" id="cp_ch_' + c + '" value="' + c + '"><label class="custom-control-label" for="cp_ch_' + c + '">' + meta.channels[c] + '</label></div>';
                }).join('') + '</fieldset></div>'
                + OPS.field('cp_at', 'Send at (leave empty to send on approval)', '<input type="datetime-local" id="cp_at" class="form-control">', 'col-md-6')
                + OPS.field('cp_subject', 'Subject (e-mail)', OPS.input('cp_subject', '', 'maxlength="190"'), 'col-12')
                + OPS.field('cp_body', 'Message (placeholders such as {first_name} work)', '<textarea id="cp_body" class="form-control" rows="5" maxlength="4000"></textarea>', 'col-12')
                + '</div><button type="button" class="btn btn-primary" id="cp_save">Save draft</button>';
            body('campaigns').html(h);
        });
    }
    $(document).on('click', '#cp_save', function () {
        api('save_campaign', { id: $('#cp_id').val(), name: $('#cp_name').val(), segment_id: $('#cp_segment').val(), channels: $('.cp-ch:checked').map(function () { return this.value; }).get(),
            subject: $('#cp_subject').val(), body: $('#cp_body').val(), scheduled_at: $('#cp_at').val() }, true).then(function (r) { if (OPS.result(r, 'Draft saved')) { campaigns(); } });
    });
    $(document).on('click', '.cp-edit', function () {
        var c = $(this).data('row');
        $('#cp_form_title').text('Edit campaign'); $('#cp_id').val(c.id); $('#cp_name').val(c.name); $('#cp_segment').val(c.segment_id);
        $('.cp-ch').each(function () { this.checked = String(c.channels).split(',').indexOf(this.value) !== -1; });
        $('#cp_subject').val(c.subject); $('#cp_body').val(c.body); $('#cp_at').val(c.scheduled_at ? c.scheduled_at.replace(' ', 'T').substring(0, 16) : '');
        document.getElementById('cp_name').focus();
    });
    $(document).on('click', '.cp-approve', function () {
        var id = $(this).data('id');
        OPS.confirm('Approve this campaign? It is sent (or scheduled) straight away.').then(function () {
            api('approve_campaign', { id: id }, true).then(function (r) { if (OPS.result(r, r.queued !== undefined ? r.queued + ' messages queued' : 'Scheduled')) { campaigns(); } });
        });
    });
    $(document).on('click', '.cp-cancel', function () {
        var id = $(this).data('id');
        OPS.confirm('Cancel every message of this campaign that has not gone out yet?').then(function () {
            api('cancel_campaign', { id: id }, true).then(function (r) { if (OPS.result(r, 'Cancelled')) { campaigns(); } });
        });
    });

    // ------------------------------------------------------------------ outbox
    var obPage = 1;
    function outbox() {
        if (!$('#ob_rows').length) {
            var evOpts = [['', 'All types']].concat(Object.keys(meta.events).map(function (k) { return [k, meta.events[k].label]; })).concat([['campaign', 'Campaign']]);
            body('outbox').html('<div class="swl-ops-toolbar"><label class="sr-only" for="ob_status">Status</label>' + OPS.select('ob_status', [['', 'Any status'], ['queued', 'Queued'], ['sent', 'Sent'], ['failed', 'Failed'], ['cancelled', 'Cancelled']], '')
                + '<label class="sr-only" for="ob_channel">Channel</label>' + OPS.select('ob_channel', [['', 'All channels'], ['whatsapp', 'WhatsApp'], ['sms', 'SMS'], ['email', 'E-mail']], '')
                + '<label class="sr-only" for="ob_event">Type</label>' + OPS.select('ob_event', evOpts, '')
                + '<label class="sr-only" for="ob_q">Search</label><input id="ob_q" type="search" class="form-control" placeholder="Customer, number, tracking"></div><div id="ob_rows"></div>');
        }
        api('outbox', { status: $('#ob_status').val(), channel: $('#ob_channel').val(), event_key: $('#ob_event').val(), q: $('#ob_q').val(), page: obPage }).then(function (r) {
            var h = OPS.table([
                { label: 'Created', render: function (o) { return esc(o.created_at) + (o.sent_at ? '<div class="swl-ops-small swl-ops-muted">sent ' + esc(o.sent_at) + '</div>' : ''); } },
                { label: 'Customer', render: function (o) { return esc(o.customer || '') + '<div class="swl-ops-small swl-ops-muted">' + esc([o.locker, o.recipient_to].filter(Boolean).join(' · ')) + '</div>'; } },
                { label: 'Type', render: function (o) { return esc(evLabel(o.event_key)) + '<div class="swl-ops-small swl-ops-muted">' + esc(meta.channels[o.channel] || o.channel) + (o.kind === 'marketing' ? ' · marketing' : '') + '</div>'; } },
                { label: 'Message', render: function (o) { return '<span title="' + esc(o.body) + '">' + esc(String(o.body).substring(0, 90)) + (String(o.body).length > 90 ? '…' : '') + '</span>'; } },
                { label: 'Status', render: function (o) { return OPS.chip(o.status, OPS.statusTone(o.status)) + (o.last_error ? '<div class="swl-ops-small swl-ops-muted">' + esc(o.last_error) + '</div>' : ''); } },
                { label: '', render: function (o) {
                    return o.status === 'failed' || o.status === 'cancelled' ? '<button type="button" class="btn btn-sm btn-secondary ob-retry" data-id="' + o.id + '">Retry</button>'
                        : (o.status === 'queued' ? '<button type="button" class="btn btn-sm btn-outline-secondary ob-cancel" data-id="' + o.id + '">Cancel</button>' : '');
                } }
            ], r.rows, 'No messages match.');
            h += '<div class="d-flex justify-content-between align-items-center mt-2"><span class="swl-ops-muted">' + OPS.num(r.total) + ' messages · page ' + r.page + ' of ' + r.pages + '</span><span>'
                + '<button type="button" class="btn btn-sm btn-secondary ob-page" data-p="' + (r.page - 1) + '"' + (r.page <= 1 ? ' disabled' : '') + '>Previous</button> '
                + '<button type="button" class="btn btn-sm btn-secondary ob-page" data-p="' + (r.page + 1) + '"' + (r.page >= r.pages ? ' disabled' : '') + '>Next</button></span></div>';
            $('#ob_rows').html(h);
        });
    }
    $(document).on('change', '#ob_status, #ob_channel, #ob_event', function () { obPage = 1; outbox(); });
    $(document).on('input', '#ob_q', OPS.debounce(function () { obPage = 1; outbox(); }, 400));
    $(document).on('click', '.ob-page', function () { obPage = $(this).data('p'); outbox(); });
    $(document).on('click', '.ob-retry', function () { api('retry', { id: $(this).data('id') }, true).then(function (r) { if (OPS.result(r, 'Queued again')) { outbox(); } }); });
    $(document).on('click', '.ob-cancel', function () { api('cancel', { id: $(this).data('id') }, true).then(function (r) { if (OPS.result(r, 'Cancelled')) { outbox(); } }); });

    // ---------------------------------------------------------- customer history
    function customers() {
        body('customers').html('<div class="row"><div class="col-lg-6">' + picker('hs') + '</div></div><div id="hs_out"></div>');
        pickHandlers.hs = loadHistory;
    }
    function loadHistory(uid) {
        api('history', { user_id: uid }).then(function (r) {
            var p = r.prefs, c = r.customer;
            var box = function (k, l) { return '<div class="custom-control custom-checkbox custom-control-inline"><input type="checkbox" class="custom-control-input hs-pref" id="hs_' + k + '" data-k="' + k + '"' + (Number(p[k]) ? ' checked' : '') + '><label class="custom-control-label" for="hs_' + k + '">' + l + '</label></div>'; };
            var h = '<div class="row"><div class="col-lg-4"><h5 class="card-title">' + esc(c.name) + '</h5><p class="swl-ops-muted">' + esc([c.locker, c.phone, c.email].filter(Boolean).join(' · ')) + '</p>'
                + '<fieldset class="form-group"><legend class="col-form-label">Shipment updates</legend>' + box('whatsapp', 'WhatsApp') + box('sms', 'SMS') + box('email', 'E-mail') + '</fieldset>'
                + '<fieldset class="form-group"><legend class="col-form-label">Marketing consent</legend>' + box('mkt_whatsapp', 'WhatsApp') + box('mkt_sms', 'SMS') + box('mkt_email', 'E-mail') + '</fieldset>'
                + '<fieldset class="form-group"><legend class="col-form-label">Everything</legend>' + box('opted_out_all', 'Opted out of all messages') + '</fieldset>'
                + '<div class="form-group"><label for="hs_source">How did the customer tell you?</label>' + OPS.select('hs_source', [['phone', 'On the phone'], ['in_person', 'In person'], ['written', 'In writing'], ['chat', 'In a chat']], 'phone') + '</div>'
                + '<button type="button" class="btn btn-primary" id="hs_save" data-uid="' + c.id + '">Save preferences</button>'
                + '<p class="swl-ops-muted swl-ops-small mt-2">' + (p.consent_at ? 'Marketing consent recorded ' + esc(p.consent_at) + ' (' + esc(p.consent_source || '') + ').' : 'No marketing consent recorded.') + '</p></div>'
                + '<div class="col-lg-8"><h5 class="card-title">Communication history</h5>' + OPS.table([
                    { label: 'When', render: function (i) { return esc(i.at); } },
                    { label: '', render: function (i) { return OPS.chip(i.dir === 'in' ? 'Received' : (i.dir === 'note' ? 'Note' : 'Sent'), i.dir === 'in' ? 'info' : (i.dir === 'note' ? '' : 'ok')); } },
                    { label: 'Channel', render: function (i) { return esc(meta.channels[i.channel] || i.channel); } },
                    { label: 'What', render: function (i) { return '<strong>' + esc(i.what) + '</strong>' + (i.subject ? '<div class="swl-ops-small">' + esc(i.subject) + '</div>' : '') + '<div class="swl-ops-small swl-ops-muted">' + esc(i.body) + '</div>'; } },
                    { label: 'Result', render: function (i) { return i.status ? OPS.chip(i.status, OPS.statusTone(i.status)) + (i.detail ? '<div class="swl-ops-small swl-ops-muted">' + esc(i.detail) + '</div>' : '') : ''; } }
                ], r.items, 'Nothing sent to or received from this customer yet.') + '</div></div>';
            $('#hs_out').html(h);
        });
    }
    $(document).on('click', '#hs_save', function () {
        var d = { user_id: $(this).data('uid'), source: $('#hs_source').val() };
        $('.hs-pref').each(function () { d[$(this).data('k')] = this.checked ? 1 : 0; });
        api('save_prefs', d, true).then(function (r) { if (OPS.result(r, 'Preferences saved')) { loadHistory(d.user_id); } });
    });

    // ----------------------------------------------------------------- surveys
    function surveys() {
        api('surveys').then(function (r) {
            var s = r.stats;
            body('surveys').html(OPS.kpis([
                { label: 'Surveys sent (30 days)', value: OPS.num(s.sent) }, { label: 'Answered', value: OPS.num(s.answered), sub: s.sent ? Math.round(100 * s.answered / s.sent) + '% response' : '' },
                { label: 'Average score', value: s.avg === null ? '—' : s.avg + ' / 5' }, { label: 'Satisfied (4-5)', value: s.csat === null ? '—' : s.csat + '%' },
                { label: 'Unhappy (1-2)', value: OPS.num(s.unhappy), sub: 'Handed to a person on WhatsApp' }
            ]) + OPS.table([
                { label: 'Sent', key: 'sent_at' }, { label: 'Customer', render: function (x) { return esc(x.customer) + ' <span class="swl-ops-muted">' + esc(x.locker || '') + '</span>'; } },
                { label: 'Reference', key: 'entity_ref' }, { label: 'Score', render: function (x) { return x.score ? OPS.chip(x.score + '/5', x.score >= 4 ? 'ok' : (x.score <= 2 ? 'bad' : 'warn')) : '<span class="swl-ops-muted">No answer</span>'; } },
                { label: 'Comment', key: 'comment' }
            ], r.rows, 'No surveys sent yet. Switch on "Satisfaction survey" under Automations.'));
        });
    }

    // -------------------------------------------------------------- deliveries
    function deliveries() {
        var st = $('#dl_status').val() || 'booked';
        api('slots', { status: st }).then(function (r) {
            var h = '<div class="swl-ops-toolbar"><label for="dl_status" class="sr-only">Status</label>' + OPS.select('dl_status', [['booked', 'Booked'], ['pending', 'Link sent, not booked yet'], ['done', 'Done'], ['cancelled', 'Cancelled'], ['all', 'All']], st) + '</div>';
            h += OPS.table([
                { label: 'Day', render: function (s) { return s.slot_date ? '<strong>' + esc(s.slot_date) + '</strong><div class="swl-ops-small">' + esc(r.windows[s.slot_window] || '') + '</div>' : '<span class="swl-ops-muted">Not chosen</span>'; } },
                { label: 'Customer', render: function (s) { return esc(s.customer) + '<div class="swl-ops-small swl-ops-muted">' + esc([s.locker, s.phone || s.customer_phone].filter(Boolean).join(' · ')) + '</div>'; } },
                { label: 'Reference', render: function (s) { return esc(s.entity_ref) + (s.reason === 'failed' ? ' ' + OPS.chip('re-delivery', 'warn') : ''); } },
                { label: 'Notes', key: 'address_note' }, { label: 'Driver', render: function (s) { return esc(s.driver_name || '—'); } },
                { label: '', render: function (s) { return ['pending', 'booked'].indexOf(s.status) !== -1 ? '<button type="button" class="btn btn-sm btn-secondary dl-op" data-op="done" data-id="' + s.id + '">Mark done</button> <button type="button" class="btn btn-sm btn-outline-secondary dl-op" data-op="cancelled" data-id="' + s.id + '">Cancel</button>' : OPS.chip(s.status, OPS.statusTone(s.status)); } }
            ], r.rows, 'No delivery bookings.');
            body('deliveries').html(h);
        });
    }
    $(document).on('change', '#dl_status', deliveries);
    $(document).on('click', '.dl-op', function () { api('slot_update', { id: $(this).data('id'), op: $(this).data('op') }, true).then(function (r) { if (OPS.result(r)) { deliveries(); } }); });

    // ---------------------------------------------------------------- settings
    function settings() {
        api('settings').then(function (r) {
            var s = r.settings, yes = [['1', 'On'], ['0', 'Off']];
            var f = function (k, l, html, col) { return OPS.field('st_' + k, l, html, col || 'col-md-4'); };
            var inp = function (k, attrs) { return '<input id="st_' + k + '" data-k="' + k + '" class="form-control st" value="' + esc(s[k]) + '" ' + (attrs || '') + '>'; };
            var sel = function (k, opts) { return OPS.select('st_' + k, opts, s[k], 'data-k="' + k + '" class="custom-select st"'); };
            var h = '<h5 class="card-title">General</h5><div class="row">' + f('enabled', 'Automations', sel('enabled', yes)) + f('quiet_start', 'Quiet hours start', inp('quiet_start', 'type="time"'))
                + f('quiet_end', 'Quiet hours end', inp('quiet_end', 'type="time"')) + f('batch_size', 'Messages per worker run', inp('batch_size', 'type="number" min="1" max="500"'))
                + f('currency', 'Currency label', inp('currency', 'maxlength="8"')) + f('campaign_needs_approval', 'Campaigns need a second approver', sel('campaign_needs_approval', yes)) + '</div>'
                + '<h5 class="card-title">Reminders</h5><div class="row">' + f('claim_after_days', 'Claim reminder after (days ready)', inp('claim_after_days', 'type="number" min="1"'))
                + f('claim_every_days', 'Repeat every (days)', inp('claim_every_days', 'type="number" min="1"')) + f('claim_max', 'At most (reminders)', inp('claim_max', 'type="number" min="0"'))
                + f('pay_after_days', 'Payment reminder after (days unpaid)', inp('pay_after_days', 'type="number" min="1"')) + f('pay_every_days', 'Repeat every (days)', inp('pay_every_days', 'type="number" min="1"'))
                + f('pay_max', 'At most (reminders)', inp('pay_max', 'type="number" min="0"')) + f('pay_min_amount', 'Only balances of at least', inp('pay_min_amount', 'type="number" min="0" step="0.01"')) + '</div>'
                + '<h5 class="card-title">Delivery booking</h5><div class="row">' + f('schedule_days', 'Days offered', inp('schedule_days', 'type="number" min="1" max="30"')) + f('schedule_skip_sunday', 'Skip Sundays', sel('schedule_skip_sunday', yes)) + '</div>'
                + '<h5 class="card-title">WhatsApp</h5><div class="row">' + f('wa_provider', 'Provider', sel('wa_provider', [['ultramsg', 'UltraMsg (Settings › WhatsApp)'], ['cloud', 'WhatsApp Business Cloud API (Meta)']]))
                + f('wa_cloud_phone_id', 'Cloud API phone number ID', inp('wa_cloud_phone_id')) + f('wa_cloud_version', 'Graph API version', inp('wa_cloud_version'))
                + f('wa_cloud_token', 'Cloud API access token' + (s.wa_cloud_token_set ? ' (saved - leave empty to keep)' : ''), inp('wa_cloud_token', 'type="password" autocomplete="off"'))
                + f('wa_cloud_app_secret', 'App secret (webhook signature)' + (s.wa_cloud_app_secret_set ? ' (saved)' : ''), inp('wa_cloud_app_secret', 'type="password" autocomplete="off"'))
                + f('wa_cloud_verify', 'Webhook verify token', inp('wa_cloud_verify')) + '</div>'
                + '<div class="form-group"><label for="st_hook">Webhook address (paste it in Meta or UltraMsg)</label><input id="st_hook" class="form-control" readonly value="' + esc(r.webhook_url) + '"></div>'
                + '<h5 class="card-title">AI WhatsApp assistant</h5><p class="swl-ops-muted">Uses the provider and key from Tools › AI Assistant. It answers from the customer\'s own shipments and the published help articles only, and hands the chat to a person when unsure.</p><div class="row">'
                + f('bot_enabled', 'Assistant answers new chats', sel('bot_enabled', yes)) + f('bot_daily_cap', 'Most answers per day', inp('bot_daily_cap', 'type="number" min="0"'))
                + f('bot_instructions', 'Extra instructions (opening hours, addresses, prices it may quote)', '<textarea id="st_bot_instructions" data-k="bot_instructions" class="form-control st" rows="4">' + esc(s.bot_instructions) + '</textarea>', 'col-12') + '</div>'
                + '<button type="button" class="btn btn-primary" id="st_save">Save settings</button> <button type="button" class="btn btn-secondary" id="st_run">Run the worker now</button><div id="st_out" class="mt-3"></div>';
            body('settings').html(h);
        });
    }
    $(document).on('click', '#st_save', function () {
        var d = {};
        $('.st').each(function () { d[$(this).data('k')] = $(this).val(); });
        api('save_settings', d, true).then(function (r) { if (OPS.result(r, 'Settings saved')) { settings(); } });
    });
    $(document).on('click', '#st_run', function () {
        api('run_worker', {}, true).then(function (r) {
            var x = r.result, d = x.delivered || {};
            $('#st_out').html('<div class="swl-ops-insight">Found <b>' + OPS.num(x.detected) + '</b> new messages to send. Delivered: ' + OPS.num(d.sent || 0) + ' sent, ' + OPS.num(d.failed || 0) + ' failed, ' + OPS.num(d.retry || 0) + ' to retry.</div>');
        });
    });

    var loaders = { overview: overview, automations: automations, templates: templates, send: send, campaigns: campaigns, segments: segments, outbox: outbox,
        customers: customers, surveys: surveys, deliveries: deliveries, settings: settings };
    $(function () {
        api('settings').then(function (r) { meta.events = r.events; meta.channels = r.channels; }).always(function () {
            OPS.tabs('cm', function (k) {
                if (!loaded[k] && loaders[k]) { loaded[k] = true; loaders[k](); }
            });
        });
    });
})(window.jQuery);
