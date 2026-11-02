"use strict";

// ============================================================================
// Customer Service inbox. State lives in the address bar (view, filters, sort,
// pg) so a refresh or a shared link shows the same list. Note the page
// parameter is "pg": the footer's table loader reacts to any "page" param.
// ============================================================================

(function ($) {
    var V = window.CDP_CS_VOCAB || {};
    var CS = window.CS;
    var URL_ = 'ajax/support/desk_ajax.php';
    var FILTERS = ['status', 'priority', 'category', 'channel', 'team_id', 'assignee_id', 'language', 'tag', 'from', 'to'];
    var GROUPS = [
        ['Queues', ['all', 'mine', 'unassigned', 'queued', 'ai', 'followed', 'internal']],
        ['By status', ['new', 'open', 'pending_customer', 'pending_internal', 'on_hold', 'resolved', 'closed']],
        ['Deadlines', ['sla_risk', 'overdue']],
        ['Elsewhere', ['archived', 'spam', 'trash']]
    ];
    var st = { view: 'all', q: '', sort: 'activity', pg: 1, f: {} };
    var selected = {}, lastRows = [], timer = null, xhr = null;

    $(function () {
        fillSelects();
        readUrl();
        bind();
        load();
        setInterval(function () { if (!document.hidden && !Object.keys(selected).length) { load(true); } }, 45000);
    });

    function opts(obj) { return Object.keys(obj).map(function (k) { return '<option value="' + CS.esc(k) + '">' + CS.esc(obj[k]) + '</option>'; }).join(''); }
    function list(arr) { return arr.map(function (x) { return '<option value="' + x.id + '">' + CS.esc(x.name) + '</option>'; }).join(''); }

    function fillSelects() {
        $('[data-f="status"], [data-b="status"], #cs_n_status').append(opts(V.statuses));
        $('[data-f="priority"], [data-b="priority"]').append(opts(V.priorities));
        $('[data-f="category"]').append(opts(V.categories));
        $('[data-f="channel"]').append(opts(V.channels));
        $('[data-f="language"]').append(opts(V.languages));
        $('[data-f="team_id"], [data-b="team_id"], #cs_n_team').append(list(V.teams.filter(function (t) { return t.active; })));
        $('[data-f="assignee_id"], [data-b="assignee_id"], #cs_n_asg').append(list(V.agents));
        $('#cs_n_cat').html(opts(V.categories));
        $('#cs_n_pri').html(opts(V.priorities)).val('normal');
        var ch = $.extend({}, V.channels); delete ch.web; delete ch.portal;
        $('#cs_n_ch').html(opts(ch)).val('phone');
        if (!V.admin) { $('[data-b="disposition"] option[value="spam"], [data-b="disposition"] option[value="trash"]').remove(); }
    }

    function readUrl() {
        var p = new URLSearchParams(location.search);
        st.view = p.get('view') || 'all';
        st.q = p.get('q') || '';
        st.sort = p.get('sort') || 'activity';
        st.pg = Math.max(1, parseInt(p.get('pg') || '1', 10));
        FILTERS.forEach(function (k) { if (p.get(k) !== null && p.get(k) !== '') { st.f[k] = p.get(k); } });
        $('#cs_q').val(st.q);
        $('#cs_sort').val(st.sort);
        FILTERS.forEach(function (k) { $('[data-f="' + k + '"]').val(st.f[k] || ''); });
        if (Object.keys(st.f).length) { $('#cs_filters').addClass('is-open'); $('#cs_filter_btn').attr('aria-expanded', 'true'); }
    }

    function writeUrl() {
        var p = new URLSearchParams();
        if (st.view !== 'all') { p.set('view', st.view); }
        if (st.q) { p.set('q', st.q); }
        if (st.sort !== 'activity') { p.set('sort', st.sort); }
        if (st.pg > 1) { p.set('pg', st.pg); }
        Object.keys(st.f).forEach(function (k) { p.set(k, st.f[k]); });
        var s = p.toString();
        history.replaceState(null, '', location.pathname + (s ? '?' + s : ''));
        var n = Object.keys(st.f).length;
        $('#cs_filter_n').text(n ? '(' + n + ')' : '');
    }

    function bind() {
        $('#cs_views').on('click', '[data-view]', function () {
            st.view = $(this).data('view'); st.pg = 1; clearSel(); load();
        }).on('click', '[data-saved]', function () {
            var v = savedViews[+$(this).data('saved')];
            if (!v) { return; }
            var f = v.filters || {};
            st.view = f.view || 'all'; st.q = f.q || ''; st.sort = f.sort || 'activity'; st.pg = 1; st.f = {};
            FILTERS.forEach(function (k) { if (f[k]) { st.f[k] = f[k]; } });
            $('#cs_q').val(st.q); $('#cs_sort').val(st.sort);
            FILTERS.forEach(function (k) { $('[data-f="' + k + '"]').val(st.f[k] || ''); });
            clearSel(); load();
        }).on('click', '[data-del-view]', function (e) {
            e.stopPropagation();
            var id = $(this).data('del-view');
            CS.confirm('Delete this saved view?', 'The tickets are not affected.', 'Delete', function () {
                CS.post(URL_, { action: 'view_delete', view_id: id }).done(function (r) { if (r && r.ok) { savedViews = r.saved_views; drawViews(); } });
            });
        });

        $('#cs_q').on('input', function () { var v = this.value; clearTimeout(timer); timer = setTimeout(function () { st.q = v.trim(); st.pg = 1; load(); }, 350); });
        $('#cs_sort').on('change', function () { st.sort = this.value; st.pg = 1; load(); });
        $('#cs_filter_btn').on('click', function () {
            var open = !$('#cs_filters').hasClass('is-open');
            $('#cs_filters').toggleClass('is-open', open);
            $(this).attr('aria-expanded', open ? 'true' : 'false');
        });
        $('#cs_filters').on('change', '[data-f]', function () {
            var k = $(this).data('f'), v = $(this).val();
            if (v === '' || v == null) { delete st.f[k]; } else { st.f[k] = v; }
            st.pg = 1; load();
        });
        $('#cs_clear').on('click', function () { st.f = {}; $('[data-f]').val(''); st.pg = 1; load(); });
        $('#cs_reload').on('click', function () { load(); });
        $('#cs_save_view').on('click', saveView);

        $('#cs_rows').on('click', 'tr[data-id]', function (e) {
            if ($(e.target).is('input, a, label')) { return; }
            var url = 'support_ticket.php?id=' + $(this).data('id');
            if (e.ctrlKey || e.metaKey) { window.open(url, '_blank'); } else { location.href = url; }
        }).on('keydown', 'tr[data-id]', function (e) {
            if (e.key === 'Enter') { location.href = 'support_ticket.php?id=' + $(this).data('id'); }
        }).on('change', 'input[data-sel]', function () {
            var id = +$(this).data('sel');
            if (this.checked) { selected[id] = true; } else { delete selected[id]; }
            drawBulk();
        });
        $('#cs_all').on('change', function () {
            var on = this.checked;
            $('#cs_rows input[data-sel]').prop('checked', on).each(function () { var id = +$(this).data('sel'); if (on) { selected[id] = true; } else { delete selected[id]; } });
            drawBulk();
        });
        $('#cs_pager').on('click', '[data-pg]', function () { st.pg = +$(this).data('pg'); load(); window.scrollTo(0, 0); });

        $('#cs_bulk_clear').on('click', clearSel);
        $('#cs_bulk_apply').on('click', bulk);

        // New ticket
        $('#cs_new').on('click', function () { $('#cs_new_modal').modal('show'); });
        $('#cs_n_internal').on('change', function () { $('.cs-cust').toggle(!this.checked); });
        var ct;
        $('#cs_n_cust_q').on('input', function () {
            var q = this.value; clearTimeout(ct);
            $('#cs_n_cust').val('');
            ct = setTimeout(function () {
                CS.get(URL_, { action: 'customers', q: q }).done(function (r) {
                    if (!r || !r.ok) { return; }
                    $('#cs_n_cust_list').html(r.customers.length ? '<ul class="swl-cs-list">' + r.customers.map(function (c) {
                        return '<li><button type="button" class="swl-cs-link-btn" data-cust="' + c.id + '" data-name="' + CS.esc(c.name) + '">' + CS.esc(c.name) + '</button>'
                            + '<span class="swl-cs-small swl-cs-muted">' + CS.esc([c.locker, c.email].filter(Boolean).join(' · ')) + '</span></li>';
                    }).join('') + '</ul>' : (q.length > 1 ? '<span class="swl-cs-small swl-cs-muted">No customer matches.</span>' : ''));
                });
            }, 300);
        });
        $('#cs_n_cust_list').on('click', '[data-cust]', function () {
            var id = $(this).data('cust');
            $('#cs_n_cust').val(id);
            $('#cs_n_cust_q').val($(this).data('name'));
            $('#cs_n_cust_list').empty();
            CS.get(URL_, { action: 'records', customer_id: id }).done(function (r) {
                $('#cs_n_ent').html('<option value="">None</option>' + ((r && r.records) || []).map(function (e) {
                    return '<option value="' + e.type + ':' + e.id + '">' + CS.esc(e.label + ' ' + e.ref + (e.status ? ' · ' + e.status : '')) + '</option>';
                }).join(''));
            });
        });
        $('#cs_new_form').on('submit', function (e) {
            e.preventDefault();
            var data = $(this).serializeArray().reduce(function (a, x) { a[x.name] = x.value; return a; }, {});
            data.action = 'create';
            if (!data.is_internal && !data.customer_id && !confirmNoCustomer()) { return; }
            var $b = $(this).find('[type=submit]').prop('disabled', true);
            CS.post(URL_, data).done(function (r) {
                if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Not created.'); return; }
                location.href = 'support_ticket.php?id=' + r.id;
            }).fail(CS.fail).always(function () { $b.prop('disabled', false); });
        });
    }

    function confirmNoCustomer() {
        if ($('#cs_n_cust_q').val().trim() !== '') {
            CS.toast('Pick the customer from the list, or clear the box for a ticket without an account.');
            return false;
        }
        return true;
    }

    var savedViews = [], counts = {}, viewNames = {};

    function load(quiet) {
        writeUrl();
        if (xhr) { xhr.abort(); }
        if (!quiet) { $('#cs_rows').css('opacity', 0.55); }
        var data = $.extend({ action: 'inbox', view: st.view, q: st.q, sort: st.sort, pg: st.pg }, st.f);
        xhr = CS.get(URL_, data).done(function (r) {
            if (!r || !r.ok) { $('#cs_rows').html('<tr><td colspan="8"><div class="swl-cs-empty">' + CS.esc(r && r.error ? r.error : 'Could not load.') + '</div></td></tr>'); return; }
            counts = r.counts; viewNames = r.views; savedViews = r.saved_views;
            drawViews();
            drawRows(r.list);
        }).fail(function (x) { if (x.statusText !== 'abort') { CS.fail(x); } }).always(function () { $('#cs_rows').css('opacity', 1); xhr = null; });
    }

    function drawViews() {
        var h = '';
        GROUPS.forEach(function (g) {
            h += '<div class="swl-cs-view-group">' + CS.esc(g[0]) + '</div>';
            g[1].forEach(function (k) {
                if (!viewNames[k]) { return; }
                var n = counts[k];
                h += '<button type="button" class="swl-cs-view' + (st.view === k ? ' is-on' : '') + '" data-view="' + k + '"' + (st.view === k ? ' aria-current="true"' : '') + '>'
                    + CS.esc(viewNames[k]) + (n ? '<span class="n">' + n + '</span>' : '') + '</button>';
            });
        });
        if (savedViews.length) {
            h += '<div class="swl-cs-view-group">Saved views</div>';
            savedViews.forEach(function (v, i) {
                h += '<button type="button" class="swl-cs-view" data-saved="' + i + '">' + CS.esc(v.name) + (v.shared ? ' <small>(shared)</small>' : '')
                    + ((v.mine || V.admin) ? '<span class="n" data-del-view="' + v.id + '" role="button" aria-label="Delete saved view" tabindex="0">×</span>' : '') + '</button>';
            });
        }
        $('#cs_views').html(h);
    }

    function slaCell(s) {
        if (!s || !s.due) { return '<span class="swl-cs-muted">–</span>'; }
        if (s.state === 'met' || s.state === 'paused') { return CS.pill('sla', s.state); }
        var mins = Math.round((new Date(s.due.replace(' ', 'T')) - new Date()) / 60000);
        var txt = mins < 0 ? CS.dur(-mins) + ' late' : CS.dur(mins) + ' left';
        return '<span class="swl-cs-pill is-' + (s.state === 'breached' || mins < 0 ? 'breached' : s.state) + '" title="' + CS.esc(s.label + ' due ' + CS.full(s.due)) + '">' + CS.esc(txt) + '</span>';
    }

    function drawRows(l) {
        lastRows = l.rows;
        $('#cs_all').prop('checked', false);
        if (!l.rows.length) {
            var msg = st.q || Object.keys(st.f).length ? 'No tickets match the search and filters.' : (st.view === 'mine' ? 'Nothing assigned to you. Nice.' : 'No tickets here.');
            $('#cs_rows').html('<tr><td colspan="8"><div class="swl-cs-empty"><iconify-icon icon="solar:inbox-linear"></iconify-icon>' + CS.esc(msg) + '</div></td></tr>');
        } else {
            $('#cs_rows').html(l.rows.map(function (t) {
                var owner = t.owner_state && t.owner_state !== 'resolved' && ['ai_active', 'handoff_queued'].indexOf(t.owner_state) > -1 ? ' ' + CS.pill('owner', t.owner_state) : '';
                return '<tr data-id="' + t.id + '" tabindex="0" class="' + (t.awaiting_us && ['resolved', 'closed'].indexOf(t.status) === -1 ? 'is-unread' : '') + '">'
                    + '<td><input type="checkbox" data-sel="' + t.id + '" aria-label="Select ' + CS.esc(t.ref) + '"' + (selected[t.id] ? ' checked' : '') + '></td>'
                    + '<td class="swl-cs-subj"><b>' + CS.esc(t.subject) + '</b><small>' + CS.esc(t.ref) + (t.link_ref ? ' · ' + CS.esc(t.link_ref) : '') + ' · ' + CS.esc(V.categories[t.category] || t.category)
                    + (t.tags.length ? ' · ' + t.tags.map(function (g) { return '#' + CS.esc(g); }).join(' ') : '') + '</small>'
                    + (t.preview ? '<small>' + (t.awaiting_us ? '● ' : '') + CS.esc(CS.plain(t.preview)) + '</small>' : '') + '</td>'
                    + '<td>' + CS.esc(t.is_internal ? 'Staff request' : t.customer) + (t.locker ? '<div class="swl-cs-small swl-cs-muted">' + CS.esc(t.locker) + '</div>' : '') + '</td>'
                    + '<td>' + CS.pill('status', t.status) + owner + '</td>'
                    + '<td>' + CS.pill('priority', t.priority) + '</td>'
                    + '<td>' + (t.assignee ? CS.esc(t.assignee) : '<span class="swl-cs-muted">Unassigned</span>') + (t.team ? '<div class="swl-cs-small swl-cs-muted">' + CS.esc(t.team) + '</div>' : '') + '</td>'
                    + '<td>' + (['resolved', 'closed'].indexOf(t.status) > -1 ? CS.pill('sla', t.sla_resolve.state) : slaCell(t.sla)) + '</td>'
                    + '<td><span title="' + CS.esc(CS.full(t.last_activity_at)) + '">' + CS.esc(CS.when(t.last_activity_at)) + '</span></td></tr>';
            }).join(''));
        }
        var pages = Math.max(1, Math.ceil(l.total / l.per));
        if (st.pg > pages && l.total) { st.pg = pages; load(); return; }
        var from = l.total ? (l.page - 1) * l.per + 1 : 0, to = Math.min(l.total, l.page * l.per);
        $('#cs_pager').html('<span>' + from + '–' + to + ' of ' + l.total + '</span><span>'
            + '<button type="button" class="btn btn-secondary btn-sm" data-pg="' + (l.page - 1) + '"' + (l.page <= 1 ? ' disabled' : '') + '>Previous</button> '
            + '<button type="button" class="btn btn-secondary btn-sm" data-pg="' + (l.page + 1) + '"' + (l.page >= pages ? ' disabled' : '') + '>Next</button></span>');
        drawBulk();
    }

    function drawBulk() {
        var n = Object.keys(selected).length;
        $('#cs_bulk').toggleClass('is-on', n > 0);
        $('#cs_bulk_n').text(n + ' selected');
    }

    function clearSel() {
        selected = {};
        $('#cs_rows input[data-sel], #cs_all').prop('checked', false);
        $('[data-b]').val('');
        drawBulk();
    }

    function bulk() {
        var data = { action: 'bulk', ids: Object.keys(selected).map(Number) };
        var any = false;
        $('[data-b]').each(function () { var v = $(this).val(); if (v !== '' && v != null) { data[$(this).data('b')] = v; any = true; } });
        if (!any) { CS.toast('Choose a change first.'); return; }
        var go = function () {
            CS.post(URL_, data).done(function (r) {
                if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Not applied.'); return; }
                var bad = r.results.filter(function (x) { return !x.ok; });
                CS.toast(bad.length ? (r.results.length - bad.length) + ' updated, ' + bad.length + ' not: ' + bad.map(function (x) { return x.error; }).filter(Boolean).slice(0, 2).join('; ') : r.results.length + ' tickets updated.', bad.length ? 'warning' : 'success');
                clearSel(); load();
            }).fail(CS.fail);
        };
        if (data.status === 'resolved' || data.status === 'closed' || ['spam', 'trash'].indexOf(data.disposition) > -1) {
            CS.confirm('Change ' + data.ids.length + ' tickets?', 'Resolving or closing in bulk may send each customer an update.', 'Apply', go);
        } else { go(); }
    }

    function saveView() {
        var save = function (name, shared) {
            if (!name) { return; }
            var filters = $.extend({ view: st.view, q: st.q, sort: st.sort }, st.f);
            CS.post(URL_, { action: 'view_save', name: name, shared: shared ? 1 : 0, filters: filters }).done(function (r) {
                if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Not saved.'); return; }
                savedViews = r.saved_views; drawViews(); CS.toast('View saved.', 'success');
            }).fail(CS.fail);
        };
        if (window.Swal) {
            Swal.fire({
                title: 'Save this view', input: 'text', inputPlaceholder: 'For example: Billing, urgent',
                html: V.admin ? '<label class="swl-cs-small"><input type="checkbox" id="cs_sv_shared"> Share with all agents</label>' : '',
                showCancelButton: true, confirmButtonText: 'Save',
                preConfirm: function (v) { return { name: (v || '').trim(), shared: $('#cs_sv_shared').is(':checked') }; }
            }).then(function (r) { if (r.isConfirmed) { save(r.value.name, r.value.shared); } });
        } else {
            save((window.prompt('Name this view') || '').trim(), false);
        }
    }
})(window.jQuery);
