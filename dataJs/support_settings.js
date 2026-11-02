"use strict";

// ============================================================================
// Customer Service settings. One load call returns everything; every save
// returns the same fresh payload, so each tab simply redraws from S.
// Rules are edited in a modal and can be tested against a real ticket with
// a dry run that changes nothing.
// ============================================================================

(function ($) {
    var V = window.CDP_CS_VOCAB || {};
    var CS = window.CS;
    var URL_ = 'ajax/support/admin_ajax.php';
    var S = null, rule = null, ADMIN = !!V.admin;

    $(function () {
        $('#cs_set_tabs [data-tab]').on('click', function () {
            var t = $(this).data('tab');
            $('#cs_set_tabs [data-tab]').removeClass('is-on').attr('aria-selected', 'false');
            $(this).addClass('is-on').attr('aria-selected', 'true');
            $('[data-pane]').prop('hidden', true).filter('[data-pane="' + t + '"]').prop('hidden', false);
            try { localStorage.setItem('cs_settings_tab', t); } catch (e) { /* private mode */ }
        });
        try { var last = localStorage.getItem('cs_settings_tab'); if (last) { $('#cs_set_tabs [data-tab="' + last + '"]').trigger('click'); } } catch (e) { /* ignore */ }
        bind();
        CS.get(URL_, { action: 'load' }).done(apply).fail(CS.fail);
    });

    function apply(r) {
        if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Could not load.'); return; }
        S = r;
        drawKing(); drawTeams(); drawRules(); drawSla(); drawCanned(); drawKb(); drawChannels();
        if (r.note) { CS.toast(r.note, 'info'); }
    }

    function save(data, okMsg) {
        return CS.post(URL_, data).done(function (r) {
            if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Not saved.'); return; }
            apply(r);
            if (okMsg) { CS.toast(okMsg, 'success'); }
        }).fail(CS.fail);
    }

    var dis = function () { return ADMIN ? '' : ' disabled'; };
    function opt(obj, val) { return Object.keys(obj).map(function (k) { return '<option value="' + CS.esc(k) + '"' + (String(val) === String(k) ? ' selected' : '') + '>' + CS.esc(obj[k]) + '</option>'; }).join(''); }
    function field(label, html, help, id, cls) {
        return '<div class="' + (cls || 'col-md-6') + ' form-group"><label' + (id ? ' for="' + id + '"' : '') + '>' + CS.esc(label) + '</label>' + html + (help ? '<small class="form-text swl-cs-muted">' + help + '</small>' : '') + '</div>';
    }
    function check(name, label, on) {
        return '<div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="cs_k_' + name + '" name="' + name + '" value="1"' + (on ? ' checked' : '') + dis() + '><label class="custom-control-label" for="cs_k_' + name + '">' + CS.esc(label) + '</label></div>';
    }
    function heading(t) { return '<div class="swl-cs-section">' + CS.esc(t) + '</div>'; }

    // ── King & general ─────────────────────────────────────────────────────
    function drawKing() {
        var s = S.settings, langs = (s.languages || 'en').split(','), ch = (s.notify_channels || '').split(',');
        var runs = {}; (S.king_runs || []).forEach(function (r) { runs[r.outcome] = r; });
        var total = Object.keys(runs).reduce(function (a, k) { return a + (+runs[k].n); }, 0);
        var h = '<form id="cs_king_form" autocomplete="off">';
        h += '<div class="swl-cs-banner ' + (S.ai_ready ? 'is-info' : '') + '">' + (S.ai_ready
            ? 'AI provider: <b>' + CS.esc(S.ai_provider) + '</b>' + (S.ai_model ? ' · model <b>' + CS.esc(S.ai_model) + '</b>' : '') + '. King uses the same provider as the AI Assistant.'
            : 'The AI provider is not set up yet. Set the provider, key and model on <a href="ai_assistant_settings.php">Tools › AI Assistant</a> before switching King on.') + '</div>';
        h += heading('What King does');
        h += '<div class="row">' + [
            ['off', 'Off', 'Customers always reach a person. No AI anywhere.'],
            ['copilot', 'Helps agents only', 'Drafts, summaries and classification for staff. Customers talk to people.'],
            ['customer', 'Answers customers', 'King answers in the chat first, using the customer\'s own records and published help, and hands over to a person on request or when unsure.']
        ].map(function (m) {
            return '<div class="col-md-4 mb-2"><label class="swl-cs-rule mb-0" style="cursor:pointer; height:100%;"><input type="radio" name="king_mode" value="' + m[0] + '"' + (s.king_mode === m[0] ? ' checked' : '') + dis() + '>'
                + '<span class="grow"><span class="swl-cs-rule-name">' + CS.esc(m[1]) + '</span><br><span class="swl-cs-small swl-cs-muted">' + CS.esc(m[2]) + '</span></span></label></div>';
        }).join('') + '</div>';
        h += '<div class="row">'
            + field('Assistant name', '<input class="form-control" id="cs_k_name" name="king_name" maxlength="40" value="' + CS.esc(s.king_name) + '"' + dis() + '>', '', 'cs_k_name', 'col-md-4')
            + field('Answers per conversation before handing over', '<input type="number" min="1" max="50" class="form-control" id="cs_k_turns" name="king_max_turns" value="' + CS.esc(s.king_max_turns) + '"' + dis() + '>', '', 'cs_k_turns', 'col-md-4')
            + field('AI answers per day (0 = no cap)', '<input type="number" min="0" class="form-control" id="cs_k_budget" name="king_daily_budget" value="' + CS.esc(s.king_daily_budget) + '"' + dis() + '>', 'When the cap is reached, new questions go straight to a person.', 'cs_k_budget', 'col-md-4')
            + field('Greeting', '<textarea class="form-control" rows="3" id="cs_k_greet" name="king_greeting"' + dis() + '>' + CS.esc(s.king_greeting) + '</textarea>', 'Shown when a customer opens a new chat. Say it is an AI assistant.', 'cs_k_greet', 'col-md-6')
            + field('Extra instructions for King', '<textarea class="form-control" rows="3" id="cs_k_instr" name="king_instructions" placeholder="e.g. Storage is free for 14 days; mention the Accra warehouse closes at 5 pm."' + dis() + '>' + CS.esc(s.king_instructions) + '</textarea>', 'Added to King\'s rules. It cannot widen what King may see or do.', 'cs_k_instr', 'col-md-6')
            + field('Lookups per answer', '<input type="number" min="1" max="10" class="form-control" id="cs_k_rounds" name="king_max_rounds" value="' + CS.esc(s.king_max_rounds) + '"' + dis() + '>', '', 'cs_k_rounds', 'col-md-4')
            + '<div class="col-md-8 form-group"><label>Languages King answers in</label><div>' + Object.keys(V.languages).map(function (k) {
                return '<label class="mr-3 swl-cs-small"><input type="checkbox" name="languages[]" value="' + k + '"' + (langs.indexOf(k) > -1 ? ' checked' : '') + dis() + '> ' + CS.esc(V.languages[k]) + '</label>';
            }).join('') + '</div></div>'
            + '</div>';
        h += check('copilot_enabled', 'AI help for agents (drafts, summaries, classification, help-article drafts)', s.copilot_enabled === '1');
        if (total) {
            h += '<p class="swl-cs-small swl-cs-muted">Last 7 days: ' + total + ' AI runs · ' + Object.keys(runs).map(function (k) { return CS.esc(k) + ' ' + runs[k].n; }).join(' · ')
                + (runs.ok && runs.ok.ms ? ' · typical time ' + (Math.round(runs.ok.ms / 100) / 10) + ' s' : '') + '</p>';
        }

        h += heading('Hours and replies');
        h += '<div class="row">'
            + field('Support hours (shown to customers)', '<input class="form-control" id="cs_k_hours" name="business_hours" placeholder="e.g. Mon–Fri 8:00–17:00, Sat 9:00–13:00 (GMT)" value="' + CS.esc(s.business_hours) + '"' + dis() + '>', 'Leave empty to show nothing.', 'cs_k_hours')
            + field('Message while waiting for a person', '<textarea class="form-control" rows="2" id="cs_k_off" name="offline_note"' + dis() + '>' + CS.esc(s.offline_note) + '</textarea>', '', 'cs_k_off')
            + field('Ticket reference prefix', '<input class="form-control" id="cs_k_pref" name="ref_prefix" maxlength="4" value="' + CS.esc(s.ref_prefix) + '"' + dis() + '>', 'Letters only, e.g. CS gives CS-7KQ4M2.', 'cs_k_pref', 'col-md-3')
            + field('Close resolved tickets after (days)', '<input type="number" min="0" class="form-control" id="cs_k_close" name="auto_close_days" value="' + CS.esc(s.auto_close_days) + '"' + dis() + '>', '0 = never. A customer reply reopens a resolved ticket; a closed one needs a new request.', 'cs_k_close', 'col-md-3')
            + field('Rating request delay (minutes)', '<input type="number" min="0" class="form-control" id="cs_k_csd" name="csat_delay_min" value="' + CS.esc(s.csat_delay_min) + '"' + dis() + '>', 'After resolving. Skipped if the ticket reopens first.', 'cs_k_csd', 'col-md-3')
            + '<div class="col-md-3 form-group"><label>Customer updates also go by</label><div>' + ['email', 'whatsapp', 'sms'].map(function (k) {
                return '<label class="mr-3 swl-cs-small"><input type="checkbox" name="notify_channels[]" value="' + k + '"' + (ch.indexOf(k) > -1 ? ' checked' : '') + dis() + '> ' + ({ email: 'E-mail', whatsapp: 'WhatsApp', sms: 'SMS' })[k] + '</label>';
            }).join('') + '</div><small class="form-text swl-cs-muted">In-app notifications are always on.</small></div>'
            + '</div>';
        h += check('ack_enabled', 'Acknowledge each new ticket once, with its reference', s.ack_enabled === '1');
        h += check('csat_enabled', 'Ask customers to rate resolved tickets', s.csat_enabled === '1');
        if (ADMIN) { h += '<button type="submit" class="btn btn-primary mt-2">Save</button>'; }
        h += '</form>';
        $('#cs_s_king').html(h);
    }

    function formData($f) {
        var d = { action: 'settings' };
        $f.find('input, textarea, select').each(function () {
            var n = this.name;
            if (!n) { return; }
            if (/\[\]$/.test(n)) { var k = n.slice(0, -2); d[k] = d[k] || ''; if (this.checked) { d[k] += (d[k] ? ',' : '') + this.value; } return; }
            if (this.type === 'checkbox') { d[n] = this.checked ? '1' : '0'; return; }
            if (this.type === 'radio') { if (this.checked) { d[n] = this.value; } return; }
            d[n] = $(this).val();
        });
        return d;
    }

    // ── Teams ──────────────────────────────────────────────────────────────
    var MODES = { manual: 'Manual (agents pick)', round_robin: 'Round robin', least_loaded: 'Least loaded (fewest open tickets)' };

    function drawTeams() {
        var meOn = S.teams.some(function (t) { return t.member_list.some(function (m) { return m.user_id === V.me; }); });
        var h = '';
        if (meOn) {
            var avail = S.teams.some(function (t) { return t.member_list.some(function (m) { return m.user_id === V.me && m.available; }); });
            h += '<div class="swl-cs-banner is-info d-flex" style="gap:10px; align-items:center;"><span class="grow">You are <b>' + (avail ? 'available' : 'away') + '</b> for new assignments.</span><button type="button" class="btn btn-secondary btn-sm" data-avail="' + (avail ? 0 : 1) + '">' + (avail ? 'Set me away' : 'Set me available') + '</button></div>';
        }
        h += '<p class="swl-cs-small swl-cs-muted">New tickets go to the default team unless a routing rule sends them elsewhere. Inside a team they are assigned by the team\'s method, only to available members under their capacity.</p>';
        h += S.teams.map(function (t) {
            var members = t.member_list.length ? '<div class="table-responsive"><table class="table table-sm mb-2"><thead><tr><th>Member</th><th>Lead</th><th>Capacity</th><th>Available</th><th>Open now</th><th></th></tr></thead><tbody>'
                + t.member_list.map(function (m) {
                    return '<tr data-team="' + t.id + '" data-user="' + m.user_id + '"><td>' + CS.esc(m.name) + '</td>'
                        + '<td><input type="checkbox" data-m="is_lead"' + (m.is_lead ? ' checked' : '') + dis() + ' aria-label="Lead"></td>'
                        + '<td><input type="number" min="1" max="500" class="form-control form-control-sm" style="width:80px" data-m="capacity" value="' + m.capacity + '"' + dis() + ' aria-label="Capacity"></td>'
                        + '<td><input type="checkbox" data-m="available"' + (m.available ? ' checked' : '') + dis() + ' aria-label="Available"></td>'
                        + '<td>' + m.open + '</td>'
                        + '<td>' + (ADMIN ? '<button type="button" class="swl-cs-link-btn" data-m-remove>Remove</button>' : '') + '</td></tr>';
                }).join('') + '</tbody></table></div>' : '<p class="swl-cs-small swl-cs-muted">No members yet.</p>';
            var addable = S.agents.filter(function (a) { return !t.member_list.some(function (m) { return m.user_id === a.id; }); });
            return '<div class="swl-cs-rule" style="display:block;"><div class="d-flex" style="gap:8px; align-items:center; flex-wrap:wrap;"><span class="swl-cs-rule-name grow">' + CS.esc(t.name) + '</span>'
                + (t.is_default ? '<span class="swl-cs-pill is-open">Default</span>' : '') + (t.active ? '' : '<span class="swl-cs-pill is-closed">Off</span>')
                + '<span class="swl-cs-small swl-cs-muted">' + CS.esc(MODES[t.assign_mode] || t.assign_mode) + '</span>'
                + (ADMIN ? '<button type="button" class="btn btn-secondary btn-sm" data-team-edit="' + t.id + '">Edit</button>' : '') + '</div>'
                + (t.description ? '<p class="swl-cs-small mb-2">' + CS.esc(t.description) + '</p>' : '') + members
                + (ADMIN && addable.length ? '<div class="d-flex" style="gap:6px"><label class="swl-cs-sr" for="cs_add_' + t.id + '">Add member</label><select class="custom-select custom-select-sm" id="cs_add_' + t.id + '" style="max-width:260px"><option value="">Add a staff member…</option>'
                    + addable.map(function (a) { return '<option value="' + a.id + '">' + CS.esc(a.name) + '</option>'; }).join('') + '</select><button type="button" class="btn btn-secondary btn-sm" data-m-add="' + t.id + '">Add</button></div>' : '')
                + '</div>';
        }).join('');
        if (ADMIN) { h += '<button type="button" class="btn btn-primary btn-sm" data-team-edit="0"><iconify-icon icon="solar:add-circle-linear"></iconify-icon> New team</button>'; }
        $('#cs_s_teams').html(h);
    }

    function editTeam(id) {
        var t = S.teams.filter(function (x) { return x.id === id; })[0] || { id: 0, name: '', description: '', assign_mode: 'least_loaded', timezone: '', active: true, is_default: false };
        Swal.fire({
            title: id ? 'Edit team' : 'New team', focusConfirm: false, showCancelButton: true, confirmButtonText: 'Save',
            showDenyButton: !!id && !t.is_default, denyButtonText: 'Delete',
            html: '<div class="text-left"><label for="cs_tm_n">Name</label><input id="cs_tm_n" class="form-control mb-2" value="' + CS.esc(t.name) + '">'
                + '<label for="cs_tm_d">What it handles</label><input id="cs_tm_d" class="form-control mb-2" value="' + CS.esc(t.description) + '">'
                + '<label for="cs_tm_m">Assignment</label><select id="cs_tm_m" class="custom-select mb-2">' + opt(MODES, t.assign_mode) + '</select>'
                + '<label for="cs_tm_tz">Time zone (optional)</label><input id="cs_tm_tz" class="form-control mb-2" placeholder="e.g. Africa/Accra" value="' + CS.esc(t.timezone) + '">'
                + '<label class="swl-cs-small mr-3"><input type="checkbox" id="cs_tm_a"' + (t.active ? ' checked' : '') + '> Active</label>'
                + '<label class="swl-cs-small"><input type="checkbox" id="cs_tm_def"' + (t.is_default ? ' checked' : '') + '> Default team for new tickets</label></div>',
            preConfirm: function () {
                if (!$('#cs_tm_n').val().trim()) { Swal.showValidationMessage('Name the team.'); return false; }
                return { action: 'team_save', id: t.id, name: $('#cs_tm_n').val(), description: $('#cs_tm_d').val(), assign_mode: $('#cs_tm_m').val(), timezone: $('#cs_tm_tz').val(), active: $('#cs_tm_a').is(':checked') ? 1 : 0, is_default: $('#cs_tm_def').is(':checked') ? 1 : 0 };
            }
        }).then(function (r) {
            if (r.isConfirmed) { save(r.value, 'Team saved.'); }
            else if (r.isDenied) { CS.confirm('Delete ' + t.name + '?', 'Only possible when it has no open tickets.', 'Delete', function () { save({ action: 'team_delete', id: t.id }, 'Team deleted.'); }); }
        });
    }

    // ── Rules ──────────────────────────────────────────────────────────────
    function drawRules() {
        var h = '<p class="swl-cs-small swl-cs-muted">Rules run in order when their trigger happens. Each rule runs at most a set number of times per ticket, and rules that trigger other rules stop after three levels. Test any rule against a real ticket before turning it on: the test changes nothing.</p>';
        var groups = {};
        S.rules.forEach(function (r) { (groups[r.trigger_event] = groups[r.trigger_event] || []).push(r); });
        if (!S.rules.length) { h += '<div class="swl-cs-empty">No rules yet. Start from a template below, or create one.</div>'; }
        Object.keys(groups).forEach(function (ev) {
            h += '<div class="swl-cs-section">' + CS.esc(S.triggers[ev] || ev) + '</div>';
            h += groups[ev].map(function (r) {
                var st = { published: ['resolved', 'On'], draft: ['closed', 'Draft'], paused: ['pending_customer', 'Paused'] }[r.status] || ['closed', r.status];
                return '<div class="swl-cs-rule"><div class="grow"><span class="swl-cs-rule-name">' + CS.esc(r.name) + '</span> <span class="swl-cs-pill is-' + st[0] + '">' + st[1] + '</span>'
                    + (r.kind === 'routing' ? ' <span class="swl-cs-pill is-open">Routing</span>' : '')
                    + '<div class="swl-cs-small swl-cs-muted">' + describe(r) + '</div>'
                    + '<div class="swl-cs-small swl-cs-muted">Ran ' + r.run_count + ' time' + (r.run_count === 1 ? '' : 's') + (r.fail_count ? ', ' + r.fail_count + ' with problems' : '') + (r.last_run_at ? ' · last ' + CS.esc(CS.when(r.last_run_at)) : '') + ' · v' + r.version + ' by ' + CS.esc(r.updated_by || '–') + '</div></div>'
                    + '<button type="button" class="btn btn-secondary btn-sm" data-rule-runs="' + r.id + '">History</button>'
                    + (ADMIN ? '<button type="button" class="btn btn-secondary btn-sm" data-rule-edit="' + r.id + '">Edit</button>'
                        + (r.status === 'published' ? '<button type="button" class="btn btn-secondary btn-sm" data-rule-status="paused" data-id="' + r.id + '">Pause</button>' : '<button type="button" class="btn btn-secondary btn-sm" data-rule-status="published" data-id="' + r.id + '">Turn on</button>')
                        + '<button type="button" class="swl-cs-link-btn" data-rule-del="' + r.id + '">Delete</button>' : '')
                    + '<div class="w-100" id="cs_runs_' + r.id + '"></div></div>';
            }).join('');
        });
        if (ADMIN) {
            h += '<button type="button" class="btn btn-primary btn-sm mb-3" data-rule-edit="0"><iconify-icon icon="solar:add-circle-linear"></iconify-icon> New rule</button>';
            h += '<div class="swl-cs-section">Templates</div><div class="row">' + Object.keys(S.templates).map(function (k) {
                var t = S.templates[k];
                return '<div class="col-md-6 col-xl-4 mb-2"><div class="swl-cs-rule" style="height:100%"><div class="grow"><span class="swl-cs-rule-name">' + CS.esc(t.name) + '</span><div class="swl-cs-small swl-cs-muted">' + describe(t) + '</div></div>'
                    + '<button type="button" class="btn btn-secondary btn-sm" data-tpl="' + k + '">Add as draft</button></div></div>';
            }).join('') + '</div>';
        }
        $('#cs_s_rules').html(h);
    }

    function valLabel(defs, key, v) {
        var d = defs[key];
        if (!d) { return v; }
        if (d.options && d.options[v]) { return d.options[v]; }
        if (d.type === 'team' || d.value === 'team') { var t = V.teams.filter(function (x) { return String(x.id) === String(v); })[0]; return t ? t.name : v; }
        if (d.value === 'agent') { var a = S.agents.filter(function (x) { return String(x.id) === String(v); })[0]; return a ? a.name : v; }
        if (d.value === 'canned') { var c = S.canned.filter(function (x) { return String(x.id) === String(v); })[0]; return c ? '“' + c.title + '”' : v; }
        return v;
    }

    function describe(r) {
        var conds = (r.conditions || []).map(function (c) {
            var f = S.fields[c.field];
            return CS.esc((f ? f.label : c.field) + ' ' + (S.ops[c.op] || c.op) + (['empty', 'not_empty'].indexOf(c.op) > -1 ? '' : ' ' + valLabel(S.fields, c.field, c.value)));
        });
        var acts = (r.actions || []).map(function (a) {
            var d = S.actions[a.type];
            return CS.esc((d ? d.label : a.type) + (a.value && d && d.value !== 'none' ? ': ' + String(valLabel(S.actions, a.type, a.value)).slice(0, 60) : ''));
        });
        return (conds.length ? 'When ' + conds.join(r.match_mode === 'any' ? ' <b>or</b> ' : ' <b>and</b> ') : 'Always') + ' → ' + (acts.join('; ') || 'nothing');
    }

    function valueInput(def, value, attr) {
        value = value == null ? '' : value;
        var kind = def ? (def.type || def.value) : 'text';
        if (kind === 'enum') { return '<select class="custom-select custom-select-sm" ' + attr + '>' + opt(def.options, value) + '</select>'; }
        if (kind === 'team') { return '<select class="custom-select custom-select-sm" ' + attr + '>' + V.teams.map(function (t) { return '<option value="' + t.id + '"' + (String(value) === String(t.id) ? ' selected' : '') + '>' + CS.esc(t.name) + '</option>'; }).join('') + '</select>'; }
        if (kind === 'agent') { return '<select class="custom-select custom-select-sm" ' + attr + '>' + S.agents.map(function (a) { return '<option value="' + a.id + '"' + (String(value) === String(a.id) ? ' selected' : '') + '>' + CS.esc(a.name) + '</option>'; }).join('') + '</select>'; }
        if (kind === 'canned') { return S.canned.length ? '<select class="custom-select custom-select-sm" ' + attr + '>' + S.canned.filter(function (c) { return c.shared; }).map(function (c) { return '<option value="' + c.id + '"' + (String(value) === String(c.id) ? ' selected' : '') + '>' + CS.esc(c.title) + '</option>'; }).join('') + '</select>' : '<span class="swl-cs-small swl-cs-muted">Add a shared saved reply first</span><input type="hidden" ' + attr + ' value="">'; }
        if (kind === 'none') { return '<input type="hidden" ' + attr + ' value=""><span class="swl-cs-small swl-cs-muted">No setting needed</span>'; }
        if (kind === 'textarea') { return '<textarea class="form-control form-control-sm" rows="2" ' + attr + '>' + CS.esc(value) + '</textarea>'; }
        if (kind === 'number') { return '<input type="number" class="form-control form-control-sm" ' + attr + ' value="' + CS.esc(value) + '">'; }
        return '<input type="text" class="form-control form-control-sm" ' + attr + ' value="' + CS.esc(value) + '">';
    }

    function condRow(c) {
        c = c || { field: 'category', op: 'is', value: '' };
        return '<div class="swl-cs-row" data-cond><select class="custom-select custom-select-sm" data-c="field" aria-label="Field">' + Object.keys(S.fields).map(function (k) { return '<option value="' + k + '"' + (c.field === k ? ' selected' : '') + '>' + CS.esc(S.fields[k].label) + '</option>'; }).join('') + '</select>'
            + '<select class="custom-select custom-select-sm" data-c="op" aria-label="Comparison">' + opt(S.ops, c.op) + '</select>'
            + '<div data-c-wrap>' + valueInput(['any_of', 'contains', 'not_contains'].indexOf(c.op) > -1 ? null : S.fields[c.field], c.value, 'data-c="value" aria-label="Value"') + '</div>'
            + '<button type="button" class="swl-cs-icon" data-row-del aria-label="Remove condition">×</button></div>';
    }

    function actRow(a) {
        a = a || { type: 'add_tag', value: '' };
        return '<div class="swl-cs-row is-action" data-act-row><select class="custom-select custom-select-sm" data-a="type" aria-label="Action">' + Object.keys(S.actions).map(function (k) { return '<option value="' + k + '"' + (a.type === k ? ' selected' : '') + '>' + CS.esc(S.actions[k].label) + '</option>'; }).join('') + '</select>'
            + '<div data-a-wrap>' + valueInput(S.actions[a.type], a.value, 'data-a="value" aria-label="Setting"') + '</div>'
            + '<button type="button" class="swl-cs-icon" data-row-del aria-label="Remove action">×</button></div>';
    }

    function editRule(id) {
        rule = S.rules.filter(function (r) { return r.id === id; })[0] || { id: 0, name: '', kind: 'automation', trigger_event: 'ticket.created', match_mode: 'all', conditions: [], actions: [{ type: 'add_tag', value: '' }], sort_order: 100, stop_processing: false, max_per_ticket: 1 };
        var h = '<div class="row">'
            + field('Name', '<input class="form-control" id="cs_r_name" value="' + CS.esc(rule.name) + '" maxlength="160">', '', 'cs_r_name', 'col-md-6')
            + field('Type', '<select class="custom-select" id="cs_r_kind">' + opt({ automation: 'Automation', routing: 'Routing (decides team / assignee)' }, rule.kind) + '</select>', '', 'cs_r_kind', 'col-md-3')
            + field('Order', '<input type="number" class="form-control" id="cs_r_order" value="' + rule.sort_order + '">', 'Lower runs first.', 'cs_r_order', 'col-md-3')
            + field('When', '<select class="custom-select" id="cs_r_trigger">' + opt(S.triggers, rule.trigger_event) + '</select>', '', 'cs_r_trigger', 'col-md-6')
            + field('Runs per ticket (at most)', '<input type="number" min="1" max="50" class="form-control" id="cs_r_max" value="' + rule.max_per_ticket + '">', '', 'cs_r_max', 'col-md-3')
            + '<div class="col-md-3 form-group"><label>&nbsp;</label><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="cs_r_stop"' + (rule.stop_processing ? ' checked' : '') + '><label class="custom-control-label" for="cs_r_stop">Stop later rules if this one matches</label></div></div>'
            + '</div>';
        h += '<div class="swl-cs-section">If <select class="custom-select custom-select-sm" id="cs_r_mode" style="width:auto; text-transform:none;">' + opt({ all: 'all', any: 'any' }, rule.match_mode) + '</select> of these are true (no conditions = always)</div>';
        h += '<div id="cs_r_conds">' + (rule.conditions || []).map(condRow).join('') + '</div><button type="button" class="btn btn-secondary btn-sm mb-2" id="cs_r_addc">Add condition</button>';
        h += '<div class="swl-cs-section">Then</div><div id="cs_r_acts">' + (rule.actions || []).map(actRow).join('') + '</div><button type="button" class="btn btn-secondary btn-sm mb-2" id="cs_r_adda">Add action</button>';
        h += '<div class="swl-cs-section">Test against a ticket</div><div class="d-flex" style="gap:6px; max-width:420px"><label class="swl-cs-sr" for="cs_r_ref">Ticket reference</label><input class="form-control form-control-sm" id="cs_r_ref" placeholder="Ticket reference, e.g. ' + CS.esc((S.settings.ref_prefix || 'CS') + '-7KQ4M2') + '"><button type="button" class="btn btn-secondary btn-sm" id="cs_r_test">Test</button></div><div id="cs_r_sim"></div>';
        $('#cs_rule_body').html(h);
        $('#cs_rule_title').text(id ? 'Edit rule' : 'New rule');
        $('#cs_rule_modal').modal('show');
    }

    function readRule() {
        return {
            id: rule.id, version: rule.version, name: $('#cs_r_name').val(), kind: $('#cs_r_kind').val(), trigger_event: $('#cs_r_trigger').val(), match_mode: $('#cs_r_mode').val(),
            sort_order: +$('#cs_r_order').val() || 100, max_per_ticket: +$('#cs_r_max').val() || 1, stop_processing: $('#cs_r_stop').is(':checked'),
            conditions: $('#cs_r_conds [data-cond]').map(function () { return { field: $(this).find('[data-c="field"]').val(), op: $(this).find('[data-c="op"]').val(), value: $(this).find('[data-c="value"]').val() }; }).get(),
            actions: $('#cs_r_acts [data-act-row]').map(function () { return { type: $(this).find('[data-a="type"]').val(), value: $(this).find('[data-a="value"]').val() }; }).get()
        };
    }

    function simulate() {
        var d = readRule();
        CS.post(URL_, { action: 'rule_simulate', rule: JSON.stringify(d), ref: $('#cs_r_ref').val().trim() }).done(function (r) {
            if (!r || !r.ok) { $('#cs_r_sim').html('<div class="swl-cs-sim">' + CS.esc(r && r.error ? r.error : 'Test failed.') + '</div>'); return; }
            var res = r.result || {}, h = '<div class="swl-cs-sim"><b>' + CS.esc(r.ticket.ref + ' · ' + r.ticket.subject) + '</b><br>';
            h += res.matched ? '<b>Matches.</b> These would happen (nothing was changed):' : '<b>Does not match.</b> Nothing would happen.';
            if (d.conditions.length) {
                h += '<ul class="mb-1">' + d.conditions.map(function (c, i) {
                    var f = S.fields[c.field] || { label: c.field };
                    return '<li>' + ((res.conditions || [])[i] ? '✓' : '✗') + ' ' + CS.esc(f.label + ' ' + (S.ops[c.op] || c.op) + ' ' + (c.value || '')) + ' <span class="swl-cs-muted">(ticket: ' + CS.esc(String(r.facts[c.field] == null ? '–' : r.facts[c.field])) + ')</span></li>';
                }).join('') + '</ul>';
            }
            if (res.matched) { h += '<ul class="mb-0">' + (res.steps || []).map(function (s) { return '<li>' + (s.ok ? '✓' : '✗') + ' ' + CS.esc((s.label || s.action) + (s.detail ? ': ' + s.detail : '')) + '</li>'; }).join('') + '</ul>'; }
            $('#cs_r_sim').html(h + '</div>');
        }).fail(CS.fail);
    }

    function showRuns(id) {
        var $box = $('#cs_runs_' + id);
        if ($box.children().length) { $box.empty(); return; }
        CS.get(URL_, { action: 'rule_runs', rule_id: id }).done(function (r) {
            if (!r || !r.ok) { return; }
            var h = '<div class="swl-cs-sim">';
            h += r.runs.length ? '<div class="table-responsive"><table class="table table-sm mb-1"><thead><tr><th>When</th><th>Ticket</th><th>Event</th><th>Result</th><th>Version</th></tr></thead><tbody>' + r.runs.map(function (x) {
                return '<tr><td>' + CS.esc(CS.full(x.at)) + '</td><td><a href="support_ticket.php?id=' + x.ticket_id + '">' + CS.esc(x.ref) + '</a></td><td>' + CS.esc(S.triggers[x.event] || x.event) + '</td><td title="' + CS.esc((x.steps || []).map(function (s) { return (s.label || s.action) + ': ' + (s.detail || ''); }).join('\n')) + '">' + CS.esc(x.outcome) + '</td><td>v' + x.version + '</td></tr>';
            }).join('') + '</tbody></table></div>' : '<p class="mb-1">Not run yet.</p>';
            if (r.versions.length) { h += '<span class="swl-cs-small swl-cs-muted">Versions: ' + r.versions.map(function (v) { return 'v' + v.version + ' ' + CS.esc(v.by) + ' ' + CS.esc(CS.when(v.at)); }).join(' · ') + '</span>'; }
            $box.html(h + '</div>');
        });
    }

    // ── SLA ────────────────────────────────────────────────────────────────
    function drawSla() {
        var s = S.settings;
        var h = '<form id="cs_sla_form">';
        if (s.sla_approved !== '1') {
            h += '<div class="swl-cs-banner"><b>These targets are provisional.</b> They are starting values for testing, not a promise to customers. Agree them with the business, adjust, then mark them approved. Customers are never shown SLA times.</div>';
        }
        h += '<div class="table-responsive"><table class="table table-sm" style="max-width:640px"><thead><tr><th>Priority</th><th>First reply (minutes)</th><th>Resolution (hours)</th></tr></thead><tbody>'
            + ['urgent', 'high', 'normal', 'low'].map(function (p) {
                return '<tr><td>' + CS.pill('priority', p) + '</td><td><input type="number" min="1" class="form-control form-control-sm" name="sla_first_' + p + '" value="' + CS.esc(s['sla_first_' + p]) + '"' + dis() + ' aria-label="First reply minutes, ' + p + '"></td>'
                    + '<td><input type="number" min="1" class="form-control form-control-sm" name="sla_resolve_' + p + '" value="' + CS.esc(s['sla_resolve_' + p]) + '"' + dis() + ' aria-label="Resolution hours, ' + p + '"></td></tr>';
            }).join('') + '</tbody></table></div>';
        h += '<div class="row">' + field('Warn when this much of the time is used (%)', '<input type="number" min="10" max="99" class="form-control" id="cs_sla_w" name="sla_warn_pct" value="' + CS.esc(s.sla_warn_pct) + '"' + dis() + '>', '', 'cs_sla_w', 'col-md-4') + '</div>';
        h += check('sla_pause_pending', 'Pause the resolution clock while waiting on the customer', s.sla_pause_pending === '1');
        h += check('sla_approved', 'These targets are approved by the business', s.sla_approved === '1');
        h += '<p class="swl-cs-small swl-cs-muted">Clocks run in calendar time on the server\'s time zone. A missed deadline stays recorded even if the ticket is later fixed. Changes apply to new tickets and to priority changes.</p>';
        if (ADMIN) { h += '<button type="submit" class="btn btn-primary">Save</button>'; }
        $('#cs_s_sla').html(h + '</form>');
    }

    // ── Saved replies ──────────────────────────────────────────────────────
    function drawCanned() {
        var h = '<p class="swl-cs-small swl-cs-muted">Insert from the ticket composer, or type /shortcut. Placeholders: {first_name}, {customer_name}, {ticket_ref}, {subject}, {agent_name}. ' + (ADMIN ? 'Shared replies are available to everyone and to automation.' : 'Your replies are private to you; administrators can share replies with the team.') + '</p>';
        h += S.canned.length ? S.canned.map(function (c) {
            return '<div class="swl-cs-rule"><div class="grow"><span class="swl-cs-rule-name">' + CS.esc(c.title) + '</span>' + (c.shortcut ? ' <code>/' + CS.esc(c.shortcut) + '</code>' : '') + (c.shared ? ' <span class="swl-cs-pill is-open">Shared</span>' : '')
                + '<div class="swl-cs-small swl-cs-muted">' + CS.esc(c.body.slice(0, 180)) + (c.body.length > 180 ? '…' : '') + '</div></div>'
                + '<button type="button" class="btn btn-secondary btn-sm" data-canned-edit="' + c.id + '">Edit</button><button type="button" class="swl-cs-link-btn" data-canned-del="' + c.id + '">Delete</button></div>';
        }).join('') : '<div class="swl-cs-empty">No saved replies yet.</div>';
        h += '<button type="button" class="btn btn-primary btn-sm" data-canned-edit="0"><iconify-icon icon="solar:add-circle-linear"></iconify-icon> New saved reply</button>';
        $('#cs_s_canned').html(h);
    }

    function editCanned(id) {
        var c = S.canned.filter(function (x) { return x.id === id; })[0] || { id: 0, title: '', body: '', shortcut: '', shared: ADMIN };
        Swal.fire({
            title: id ? 'Edit saved reply' : 'New saved reply', width: 640, focusConfirm: false, showCancelButton: true, confirmButtonText: 'Save',
            html: '<div class="text-left"><label for="cs_c_t">Title</label><input id="cs_c_t" class="form-control mb-2" value="' + CS.esc(c.title) + '">'
                + '<label for="cs_c_s">Shortcut (optional)</label><input id="cs_c_s" class="form-control mb-2" placeholder="e.g. storage" value="' + CS.esc(c.shortcut) + '">'
                + '<label for="cs_c_b">Text</label><textarea id="cs_c_b" class="form-control mb-2" rows="7">' + CS.esc(c.body) + '</textarea>'
                + (ADMIN ? '<label class="swl-cs-small"><input type="checkbox" id="cs_c_sh"' + (c.shared ? ' checked' : '') + '> Share with all agents</label>' : '') + '</div>',
            preConfirm: function () {
                if (!$('#cs_c_t').val().trim() || !$('#cs_c_b').val().trim()) { Swal.showValidationMessage('Add a title and text.'); return false; }
                return { action: 'canned_save', id: c.id, title: $('#cs_c_t').val(), shortcut: $('#cs_c_s').val().replace(/^\//, ''), body: $('#cs_c_b').val(), shared: $('#cs_c_sh').is(':checked') ? 1 : 0 };
            }
        }).then(function (r) { if (r.isConfirmed) { save(r.value, 'Saved.'); } });
    }

    // ── Knowledge base ─────────────────────────────────────────────────────
    function drawKb() {
        var aud = { public: 'Everyone', customer: 'Signed-in customers', staff: 'Staff only' };
        var h = '<p class="swl-cs-small swl-cs-muted">King answers only from published articles (and the customer\'s own records). Customers see public and customer articles in Help & Support. ' + (ADMIN ? '' : 'Your articles are saved as drafts; an administrator publishes them.') + '</p>';
        var live = S.kb.filter(function (a) { return a.status !== 'retired'; });
        h += live.length ? live.map(function (a) {
            var st = { published: ['resolved', 'Published'], draft: ['closed', 'Draft'] }[a.status] || ['closed', a.status];
            return '<div class="swl-cs-rule"><div class="grow"><span class="swl-cs-rule-name">' + CS.esc(a.title) + '</span> <span class="swl-cs-pill is-' + st[0] + '">' + st[1] + '</span>' + (a.needs_review ? ' <span class="swl-cs-pill is-warning">Review due</span>' : '')
                + '<div class="swl-cs-small swl-cs-muted">' + CS.esc(aud[a.audience] || a.audience) + (a.category ? ' · ' + CS.esc(V.categories[a.category] || a.category) : '') + ' · v' + a.version + ' · ' + CS.esc(a.owner || '–') + ' · updated ' + CS.esc(CS.when(a.updated_at))
                + (a.helpful + a.not_helpful ? ' · ' + a.helpful + ' found it helpful, ' + a.not_helpful + ' not' : '') + '</div></div>'
                + '<button type="button" class="btn btn-secondary btn-sm" data-kb-edit="' + a.id + '">Edit</button>'
                + (ADMIN ? '<button type="button" class="swl-cs-link-btn" data-kb-del="' + a.id + '">Retire</button>' : '') + '</div>';
        }).join('') : '<div class="swl-cs-empty">No articles yet. Write one, or draft one from a resolved ticket (ticket › AI › Draft article).</div>';
        h += '<button type="button" class="btn btn-primary btn-sm" data-kb-edit="0"><iconify-icon icon="solar:add-circle-linear"></iconify-icon> New article</button>';
        $('#cs_s_kb').html(h);
    }

    function editKb(id) {
        var a = S.kb.filter(function (x) { return x.id === id; })[0] || { id: 0, title: '', body: '', category: 'general', audience: 'customer', status: 'draft', review_at: '' };
        Swal.fire({
            title: id ? 'Edit article' : 'New article', width: 760, focusConfirm: false, showCancelButton: true, confirmButtonText: 'Save',
            html: '<div class="text-left"><label for="cs_a_t">Title (a question works well)</label><input id="cs_a_t" class="form-control mb-2" value="' + CS.esc(a.title) + '">'
                + '<div class="row"><div class="col-md-4"><label for="cs_a_c">Topic</label><select id="cs_a_c" class="custom-select mb-2">' + opt(V.categories, a.category) + '</select></div>'
                + '<div class="col-md-4"><label for="cs_a_a">Who can see it</label><select id="cs_a_a" class="custom-select mb-2">' + opt({ public: 'Everyone', customer: 'Signed-in customers', staff: 'Staff only' }, a.audience) + '</select></div>'
                + '<div class="col-md-4"><label for="cs_a_s">Status</label><select id="cs_a_s" class="custom-select mb-2">' + opt(ADMIN ? { draft: 'Draft', published: 'Published' } : { draft: 'Draft' }, a.status) + '</select></div></div>'
                + '<label for="cs_a_b">Article (Markdown: **bold**, lists, links)</label><textarea id="cs_a_b" class="form-control mb-2" rows="12">' + CS.esc(a.body) + '</textarea>'
                + '<label for="cs_a_r">Review by (optional)</label><input type="date" id="cs_a_r" class="form-control" value="' + CS.esc((a.review_at || '').slice(0, 10)) + '"></div>',
            preConfirm: function () {
                if (!$('#cs_a_t').val().trim() || !$('#cs_a_b').val().trim()) { Swal.showValidationMessage('Add a title and the article.'); return false; }
                return { action: 'kb_save', id: a.id, title: $('#cs_a_t').val(), category: $('#cs_a_c').val(), audience: $('#cs_a_a').val(), status: $('#cs_a_s').val(), body: $('#cs_a_b').val(), review_at: $('#cs_a_r').val() };
            }
        }).then(function (r) { if (r.isConfirmed) { save(r.value, 'Article saved.'); } });
    }

    // ── Channels & delivery ────────────────────────────────────────────────
    function drawChannels() {
        var lbl = { live: 'Working', setup: 'Needs setup', configured_elsewhere: 'Set up elsewhere', not_connected: 'Not connected' };
        var h = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Channel</th><th>State</th><th>Notes</th></tr></thead><tbody>'
            + S.channels.map(function (c) { return '<tr><td>' + CS.esc(c.label) + '</td><td><span class="swl-cs-pill is-' + c.state + '">' + CS.esc(lbl[c.state] || c.state) + '</span></td><td class="swl-cs-small">' + CS.esc(c.detail) + '</td></tr>'; }).join('')
            + '</tbody></table></div>';
        var w = S.worker_last_run;
        var stale = !w || (Date.now() - new Date(w.replace(' ', 'T')).getTime()) > 15 * 60000;
        h += '<div class="swl-cs-section">Background worker</div>';
        h += '<div class="swl-cs-banner ' + (stale ? 'is-danger' : 'is-info') + '">' + (w ? 'Last ran ' + CS.esc(CS.full(w)) + '.' : 'Has not run yet.')
            + (stale ? ' Schedule <code>php cron/support_worker.php</code> every 5 minutes (cron). Without it, SLA warnings, e-mail/WhatsApp/SMS copies, rating requests and auto-close do not happen. In-app chat and notifications still work.' : ' It sends updates, checks SLA deadlines and runs scheduled rules.') + '</div>';
        var counts = {}; S.outbox.forEach(function (o) { counts[o.status] = o.n; });
        h += '<div class="swl-cs-section">Outgoing messages, last 7 days</div><p class="swl-cs-small">' + (['sent', 'pending', 'retry', 'dead', 'skipped'].map(function (k) { return counts[k] ? k + ': <b>' + counts[k] + '</b>' : ''; }).filter(Boolean).join(' · ') || 'None yet.') + '</p>';
        if (S.outbox_problems.length) {
            h += '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>When</th><th>Ticket</th><th>Channel</th><th>Attempts</th><th>Error</th><th></th></tr></thead><tbody>'
                + S.outbox_problems.map(function (o) {
                    return '<tr><td>' + CS.esc(CS.full(o.created_at)) + '</td><td><a href="support_ticket.php?id=' + o.ticket_id + '">#' + o.ticket_id + '</a></td><td>' + CS.esc(o.channel) + '</td><td>' + o.attempts + '</td><td class="swl-cs-small">' + CS.esc(o.last_error || '') + '</td>'
                        + '<td>' + (ADMIN ? '<button type="button" class="btn btn-secondary btn-sm" data-retry="' + o.id + '">Retry</button>' : '') + '</td></tr>';
                }).join('') + '</tbody></table></div>';
        }
        $('#cs_s_channels').html(h);
    }

    // ── Events ─────────────────────────────────────────────────────────────
    function bind() {
        $(document).on('submit', '#cs_king_form, #cs_sla_form', function (e) { e.preventDefault(); save(formData($(this)), 'Settings saved.'); });
        $(document).on('click', '[data-avail]', function () {
            CS.post(URL_, { action: 'availability', available: $(this).data('avail') }).done(function () { CS.get(URL_, { action: 'load' }).done(apply); });
        });
        $(document).on('click', '[data-team-edit]', function () { editTeam(+$(this).data('team-edit')); });
        $(document).on('change', '[data-team] [data-m]', function () {
            var $tr = $(this).closest('tr');
            save({ action: 'member_save', team_id: $tr.data('team'), user_id: $tr.data('user'), is_lead: $tr.find('[data-m="is_lead"]').is(':checked') ? 1 : 0, capacity: $tr.find('[data-m="capacity"]').val(), available: $tr.find('[data-m="available"]').is(':checked') ? 1 : 0 });
        });
        $(document).on('click', '[data-m-remove]', function () { var $tr = $(this).closest('tr'); save({ action: 'member_remove', team_id: $tr.data('team'), user_id: $tr.data('user') }); });
        $(document).on('click', '[data-m-add]', function () {
            var t = $(this).data('m-add'), u = $('#cs_add_' + t).val();
            if (u) { save({ action: 'member_save', team_id: t, user_id: u, capacity: 20, available: 1 }); }
        });

        $(document).on('click', '[data-rule-edit]', function () { editRule(+$(this).data('rule-edit')); });
        $(document).on('click', '[data-rule-runs]', function () { showRuns(+$(this).data('rule-runs')); });
        $(document).on('click', '[data-rule-status]', function () { save({ action: 'rule_status', id: $(this).data('id'), status: $(this).data('rule-status') }); });
        $(document).on('click', '[data-rule-del]', function () { var id = $(this).data('rule-del'); CS.confirm('Delete this rule?', 'Its run history is kept.', 'Delete', function () { save({ action: 'rule_delete', id: id }, 'Rule deleted.'); }); });
        $(document).on('click', '[data-tpl]', function () { save({ action: 'rule_template', key: $(this).data('tpl') }, 'Added as a draft. Test it, then turn it on.'); });
        $(document).on('click', '#cs_r_addc', function () { $('#cs_r_conds').append(condRow()); });
        $(document).on('click', '#cs_r_adda', function () { $('#cs_r_acts').append(actRow()); });
        $(document).on('click', '#cs_rule_body [data-row-del]', function () { $(this).closest('[data-cond], [data-act-row]').remove(); });
        $(document).on('change', '#cs_rule_body [data-c="field"], #cs_rule_body [data-c="op"]', function () {
            var $r = $(this).closest('[data-cond]'), f = $r.find('[data-c="field"]').val(), op = $r.find('[data-c="op"]').val();
            var free = ['any_of', 'contains', 'not_contains'].indexOf(op) > -1;
            $r.find('[data-c-wrap]').html(['empty', 'not_empty'].indexOf(op) > -1 ? '<input type="hidden" data-c="value" value="">' : valueInput(free ? null : S.fields[f], '', 'data-c="value" aria-label="Value"'));
        });
        $(document).on('change', '#cs_rule_body [data-a="type"]', function () {
            $(this).closest('[data-act-row]').find('[data-a-wrap]').html(valueInput(S.actions[$(this).val()], '', 'data-a="value" aria-label="Setting"'));
        });
        $(document).on('click', '#cs_r_test', simulate);
        $('[data-rule-save]').on('click', function () {
            var d = readRule();
            d.status = $(this).data('rule-save');
            CS.post(URL_, { action: 'rule_save', rule: JSON.stringify(d) }).done(function (r) {
                if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Not saved.'); return; }
                $('#cs_rule_modal').modal('hide');
                apply(r);
                CS.toast(d.status === 'published' ? 'Rule saved and on.' : 'Saved as a draft.', 'success');
            }).fail(CS.fail);
        });

        $(document).on('click', '[data-canned-edit]', function () { editCanned(+$(this).data('canned-edit')); });
        $(document).on('click', '[data-canned-del]', function () { var id = $(this).data('canned-del'); CS.confirm('Delete this saved reply?', 'Automation rules that send it will report a problem.', 'Delete', function () { save({ action: 'canned_delete', id: id }); }); });
        $(document).on('click', '[data-kb-edit]', function () { editKb(+$(this).data('kb-edit')); });
        $(document).on('click', '[data-kb-del]', function () { var id = $(this).data('kb-del'); CS.confirm('Retire this article?', 'Customers and King stop seeing it. It stays on record.', 'Retire', function () { save({ action: 'kb_delete', id: id }); }); });
        $(document).on('click', '[data-retry]', function () { save({ action: 'outbox_retry', id: $(this).data('retry') }, 'Queued again.'); });
    }
})(window.jQuery);
