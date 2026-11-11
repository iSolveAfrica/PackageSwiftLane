"use strict";

// ============================================================================
// My Workspace (views/sl/pages/Workspace.php): shift clock, tasks,
// announcements, approval requests and the panels of the person's job role
// (helpers/staff_workspace.php). Panels are generic: kpis, a table, links,
// and for drivers the "I am arriving" message to the customer.
// ============================================================================

(function ($) {
    var OPS = window.OPS, esc = OPS.esc, URL = 'ajax/staffops/staffops_ajax.php';
    var nice = function (s) { return esc(String(s || '').replace(/_/g, ' ')); };

    function panel(p) {
        var h = '<section class="sl-card w-half"><h2 class="card-title">' + esc(p.title) + '</h2>';
        if (p.note) { h += '<p class="swl-ops-muted">' + esc(p.note) + '</p>'; }
        if (p.kpis && p.kpis.length) { h += OPS.kpis(p.kpis.map(function (k) { return { label: k.label, value: typeof k.value === 'number' ? OPS.num(k.value) : k.value, sub: k.sub }; })); }
        if (p.table) {
            h += OPS.table(p.table.cols.map(function (c) { return { key: c[0], label: c[1], render: function (r) { return nice(r[c[0]]); } }; }), p.table.rows, p.empty || 'Nothing here.');
        }
        if (p.action === 'driver_arriving') {
            h += '<form class="row align-items-end mt-3" id="ws_arrive"><div class="col-md-5"><div class="form-group"><label for="ws_trk">Tracking number</label><input id="ws_trk" class="form-control" required></div></div>'
                + '<div class="col-md-3"><div class="form-group"><label for="ws_eta">Minutes away</label><input id="ws_eta" class="form-control" type="number" min="0" max="600" value="15"></div></div>'
                + '<div class="col-auto"><div class="form-group"><button class="btn" type="submit">Tell the customer I am arriving</button></div></div></form>';
        }
        if (p.links && p.links.length) { h += '<div class="btn-group mt-3">' + p.links.map(function (l) { return '<a class="btn btn-sm btn-secondary" href="' + esc(l.href) + '">' + esc(l.label) + '</a>'; }).join('') + '</div>'; }
        return h + '</section>';
    }

    function load() {
        OPS.get(URL, { action: 'workspace' }).then(function (r) {
            var d = r.data, s = d.shift, h = '';
            $('#ws_title').text('My Workspace · ' + d.me.role_label);
            $('#ws_lede').text(d.me.name + (d.me.branch_names.length ? ' · ' + d.me.branch_names.join(', ') : ' · no branch assigned yet'));
            var clock = '';
            if (s && s.status === 'checked_in') { clock = '<span class="swl-ops-chip is-ok">On shift since ' + esc(String(s.check_in_at).slice(11, 16)) + '</span><button type="button" class="sl-btn ghost ws-clock" data-op="out">Check out</button>'; }
            else if (s && s.status === 'scheduled') { clock = '<span class="swl-ops-chip is-info">Shift ' + esc(String(s.start_time).slice(0, 5) + '–' + String(s.end_time).slice(0, 5)) + (s.branch_name ? ' · ' + esc(s.branch_name) : '') + '</span><button type="button" class="sl-btn ws-clock" data-op="in">Check in</button>'; }
            else if (s && s.status === 'completed') { clock = '<span class="swl-ops-chip">Shift done today</span>'; }
            else { clock = '<span class="swl-ops-chip">No shift planned today</span>'; }
            $('#ws_clock').html(clock);

            var unread = d.announcements.filter(function (a) { return !a.read_at || (Number(a.requires_ack) && !a.ack_at); });
            if (unread.length) {
                h += '<section class="sl-card w-full"><h2 class="card-title">Announcements</h2>' + unread.map(function (a) {
                    return '<div class="swl-ops-insight"><b>' + esc(a.title) + '</b> <span class="swl-ops-small swl-ops-muted">' + esc(a.author + ' · ' + a.created_at) + '</span><div class="mt-2">' + esc(a.body).replace(/\n/g, '<br>') + '</div>'
                        + '<button type="button" class="btn btn-sm btn-secondary mt-2 ws-ann" data-id="' + a.id + '" data-ack="' + (Number(a.requires_ack) ? 1 : 0) + '">' + (Number(a.requires_ack) ? 'I have read and understood this' : 'Mark as read') + '</button></div>';
                }).join('') + '</section>';
            }
            d.panels.forEach(function (p) { h += panel(p); });
            h += '<section class="sl-card w-half"><h2 class="card-title">My tasks</h2>' + OPS.table([
                { label: 'Task', render: function (t) { return '<strong>' + esc(t.title) + '</strong><div class="swl-ops-small swl-ops-muted">' + esc((t.entity_ref || '') + (t.branch_name ? ' · ' + t.branch_name : '')) + '</div>'; } },
                { label: 'Due', render: function (t) { return esc(t.due_at || '—') + (Number(t.overdue) ? ' ' + OPS.chip('overdue', 'bad') : ''); } },
                { label: '', render: function (t) { return '<span class="btn-group">' + (t.status === 'open' ? '<button type="button" class="btn btn-sm btn-secondary ws-task" data-id="' + t.id + '" data-s="in_progress">Start</button>' : '') + '<button type="button" class="btn btn-sm ws-task" data-id="' + t.id + '" data-s="done">Done</button></span>'; } }
            ], d.tasks, 'No open tasks.') + '</section>';
            h += '<section class="sl-card w-half"><h2 class="card-title">My week</h2>' + OPS.table([{ key: 'shift_date', label: 'Day' },
                { label: 'Hours', render: function (x) { return esc(String(x.start_time).slice(0, 5) + '–' + String(x.end_time).slice(0, 5)); } }, { key: 'branch_name', label: 'Branch' },
                { label: 'Status', render: function (x) { return nice(x.status); } }], d.week, 'No shifts in the next 7 days.') + '</section>';
            if (d.to_decide.length) {
                h += '<section class="sl-card w-half"><h2 class="card-title">Waiting for my decision</h2>' + OPS.table([{ label: 'Request', render: function (a) { return '<strong>' + esc(a.title) + '</strong><div class="swl-ops-small swl-ops-muted">' + esc(a.requested_name) + '</div>'; } },
                    { label: 'Amount', render: function (a) { return a.amount === null ? '—' : OPS.num(a.amount, 2); } }], d.to_decide) + '<a class="btn btn-sm btn-secondary mt-2" href="staff_hub.php#approvals">Decide</a></section>';
            }
            h += '<section class="sl-card w-half"><h2 class="card-title">My requests</h2>' + OPS.table([{ key: 'title', label: 'Request' }, { label: 'Status', render: function (a) { return OPS.chip(a.status, OPS.statusTone(a.status)); } }],
                d.requests.slice(0, 8), 'No requests.') + '<a class="btn btn-sm btn-secondary mt-2" href="staff_hub.php#approvals">Ask for approval</a></section>';
            $('#ws_panels').html(h);
        });
    }
    $(document).on('click', '.ws-clock', function () {
        var op = $(this).data('op');
        OPS.post(URL + '?action=clock', { action: 'clock', op: op }).then(function (r) {
            if (OPS.result(r, op === 'in' ? (r.late_minutes ? 'Checked in, ' + r.late_minutes + ' minutes late' : 'Checked in') : 'Checked out')) { load(); }
        });
    });
    $(document).on('click', '.ws-task', function () { OPS.post(URL + '?action=task_status', { action: 'task_status', id: $(this).data('id'), status: $(this).data('s') }).then(function (r) { if (OPS.result(r, 'Updated')) { load(); } }); });
    $(document).on('click', '.ws-ann', function () { OPS.post(URL + '?action=read_announcement', { action: 'read_announcement', id: $(this).data('id'), ack: $(this).data('ack') }).then(function () { load(); }); });
    $(document).on('submit', '#ws_arrive', function (ev) {
        ev.preventDefault();
        OPS.post('ajax/comms/comms_ajax.php?action=driver_arriving', { action: 'driver_arriving', tracking: $('#ws_trk').val(), eta: $('#ws_eta').val() })
            .then(function (r) { if (OPS.result(r, 'The customer has been told you are on the way')) { $('#ws_trk').val(''); } });
    });
    $(load);
})(window.jQuery);
