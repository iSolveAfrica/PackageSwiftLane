"use strict";

// ============================================================================
// Staff & Branches (views/sl/pages/StaffHub.php). Tabs load from
// ajax/staffops/staffops_ajax.php the first time they are shown; what a
// person can change follows the module's capability matrix (meta.can).
// ============================================================================

(function ($) {
    var OPS = window.OPS, esc = OPS.esc, URL = 'ajax/staffops/staffops_ajax.php';
    var M = {}, loaded = {};
    var body = function (k) { return $('[data-pane-body="' + k + '"]'); };
    var api = function (action, data, post) { var d = $.extend({ action: action }, data || {}); return post ? OPS.post(URL + '?action=' + action, d) : OPS.get(URL, d); };
    var roleLabel = function (r) { return (M.roles && M.roles[r]) || String(r || '').replace(/_/g, ' '); };
    var branchOpts = function (first) { return (first ? [[0, first]] : []).concat((M.branches || []).map(function (b) { return [b.id, b.name + ' (' + b.code + ')']; })); };
    var monday = function (d) { d = d ? new Date(d + 'T12:00:00') : new Date(); var x = (d.getDay() + 6) % 7; d.setDate(d.getDate() - x); return d.toISOString().slice(0, 10); };
    var addDays = function (iso, n) { var d = new Date(iso + 'T12:00:00'); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10); };
    var nice = function (s) { return esc(String(s || '').replace(/_/g, ' ')); };

    // ----------------------------------------------------------- branches
    function branches() {
        api('branches').then(function (r) {
            var ed = r.can_edit, inv = {};
            r.inventory.forEach(function (i) { inv[i.id] = i.items; });
            var h = '<div class="row"><div class="col-lg-7"><h2 class="card-title">Branches</h2>' + OPS.table([
                { label: 'Branch', render: function (b) { return '<strong>' + esc(b.name) + '</strong> <span class="swl-ops-muted">' + esc(b.code) + '</span><div class="swl-ops-small swl-ops-muted">' + nice(b.type) + (b.city ? ' · ' + esc(b.city) : '') + (b.active == 1 ? '' : ' · inactive') + '</div>'; } },
                { label: 'Country', render: function (b) { return esc(b.country_name || '—'); } }, { label: 'Manager', render: function (b) { return esc(b.manager_name || '—'); } },
                { label: 'Staff', render: function (b) { return OPS.num(b.staff_count); } }, { label: 'Held items', render: function (b) { return OPS.num(inv[b.id] || 0); } },
                { label: '', render: function (b) { return ed ? '<button type="button" class="btn btn-sm btn-secondary br-edit" data-row="' + esc(JSON.stringify(b)) + '">Edit</button>' : ''; } }
            ], r.branches, 'No branches yet.') + '</div><div class="col-lg-5"><h2 class="card-title">Countries of operation</h2>' + OPS.table([
                { label: 'Country', render: function (c) { return '<strong>' + esc(c.name) + '</strong> ' + esc(c.iso2 || ''); } }, { key: 'currency', label: 'Currency' }, { key: 'timezone', label: 'Time zone' },
                { label: '', render: function (c) { return ed ? '<button type="button" class="btn btn-sm btn-secondary co-edit" data-row="' + esc(JSON.stringify(c)) + '">Edit</button>' : ''; } }
            ], r.countries, 'No countries yet.') + '</div></div>';
            if (ed) {
                var cOpts = [[0, '—']].concat(r.countries.map(function (c) { return [c.id, c.name]; }));
                h += '<hr><div class="row"><div class="col-lg-7"><h3 class="card-title" id="br_title">New branch</h3><input type="hidden" id="br_id" value="0"><div class="row">'
                    + OPS.field('br_code', 'Code', OPS.input('br_code', '', 'maxlength="20" placeholder="ACC-WH1"'), 'col-md-4') + OPS.field('br_name', 'Name', OPS.input('br_name', '', 'maxlength="120"'), 'col-md-4')
                    + OPS.field('br_type', 'Type', OPS.select('br_type', [['warehouse', 'Warehouse'], ['office', 'Office'], ['hub', 'Hub'], ['agent', 'Agent point']], 'warehouse'), 'col-md-4')
                    + OPS.field('br_country', 'Country', OPS.select('br_country', cOpts, 0), 'col-md-4') + OPS.field('br_city', 'City', OPS.input('br_city', ''), 'col-md-4') + OPS.field('br_phone', 'Phone', OPS.input('br_phone', ''), 'col-md-4')
                    + OPS.field('br_address', 'Address', OPS.input('br_address', ''), 'col-md-6') + OPS.field('br_manager', 'Manager (user id)', '<span id="br_manager_box"></span>', 'col-md-6')
                    + OPS.field('br_office', 'Shipments of office', OPS.select('br_office', [[0, 'Not linked']].concat(r.offices.map(function (o) { return [o.id, o.name]; })), 0), 'col-md-4')
                    + OPS.field('br_bo', 'Shipments of agency', OPS.select('br_bo', [[0, 'Not linked']].concat(r.branch_offices.map(function (o) { return [o.id, o.name]; })), 0), 'col-md-4')
                    + OPS.field('br_active', 'Active', OPS.select('br_active', [[1, 'Yes'], [0, 'No']], 1), 'col-md-4')
                    + '</div><p class="swl-ops-muted">Linking a branch to an existing office or agency is what counts its shipments in branch reports and the Master View.</p><button type="button" class="btn" id="br_save">Save branch</button></div>'
                    + '<div class="col-lg-5"><h3 class="card-title" id="co_title">New country</h3><input type="hidden" id="co_id" value="0"><div class="row">'
                    + OPS.field('co_name', 'Country', OPS.input('co_name', '', 'maxlength="100"'), 'col-md-6') + OPS.field('co_iso', 'ISO code', OPS.input('co_iso', '', 'maxlength="2"'), 'col-md-6')
                    + OPS.field('co_cur', 'Currency', OPS.input('co_cur', '', 'maxlength="8" placeholder="GHS"'), 'col-md-6') + OPS.field('co_tz', 'Time zone', OPS.input('co_tz', '', 'placeholder="Africa/Accra"'), 'col-md-6')
                    + '</div><button type="button" class="btn" id="co_save">Save country</button></div></div>';
            }
            body('branches').html(h);
            staffSelect('#br_manager_box', 'br_manager', 0);
        });
    }
    var staffCache = null;
    function staffSelect(box, id, value) {
        var draw = function () { $(box).html(OPS.select(id, [[0, '—']].concat((staffCache || []).map(function (u) { return [u.id, u.name]; })), value)); };
        if (staffCache) { draw(); return; }
        api('staff').then(function (r) { staffCache = r.rows; draw(); }, function () { staffCache = []; draw(); });
    }
    $(document).on('click', '.br-edit', function () {
        var b = $(this).data('row');
        $('#br_title').text('Edit branch'); $('#br_id').val(b.id); $('#br_code').val(b.code); $('#br_name').val(b.name); $('#br_type').val(b.type); $('#br_country').val(b.country_id || 0);
        $('#br_city').val(b.city); $('#br_phone').val(b.phone); $('#br_address').val(b.address); $('#br_manager').val(b.manager_id || 0); $('#br_office').val(b.office_id || 0); $('#br_bo').val(b.branch_office_id || 0); $('#br_active').val(b.active);
        document.getElementById('br_code').focus();
    });
    $(document).on('click', '#br_save', function () {
        api('save_branch', { id: $('#br_id').val(), code: $('#br_code').val(), name: $('#br_name').val(), type: $('#br_type').val(), country_id: $('#br_country').val(), city: $('#br_city').val(),
            phone: $('#br_phone').val(), address: $('#br_address').val(), manager_id: $('#br_manager').val(), office_id: $('#br_office').val(), branch_office_id: $('#br_bo').val(), active: $('#br_active').val() }, true)
            .then(function (r) { if (OPS.result(r, 'Branch saved')) { refreshMeta(); branches(); } });
    });
    $(document).on('click', '.co-edit', function () {
        var c = $(this).data('row');
        $('#co_title').text('Edit country'); $('#co_id').val(c.id); $('#co_name').val(c.name); $('#co_iso').val(c.iso2); $('#co_cur').val(c.currency); $('#co_tz').val(c.timezone);
    });
    $(document).on('click', '#co_save', function () {
        api('save_country', { id: $('#co_id').val(), name: $('#co_name').val(), iso2: $('#co_iso').val(), currency: $('#co_cur').val(), timezone: $('#co_tz').val(), active: 1 }, true)
            .then(function (r) { if (OPS.result(r, 'Country saved')) { branches(); } });
    });

    // --------------------------------------------------------------- staff
    function staff() {
        if (!M.can['staff.view'] && !M.can['staff.manage']) { body('staff').html('<p class="swl-ops-muted">You do not have access to the staff directory.</p>'); return; }
        if (!$('#sf_rows').length) {
            body('staff').html('<div class="swl-ops-toolbar"><label for="sf_branch" class="sr-only">Branch</label>' + OPS.select('sf_branch', branchOpts('All branches'), 0)
                + '<label for="sf_q" class="sr-only">Search</label><input id="sf_q" type="search" class="form-control" placeholder="Name, e-mail, employee number"></div><div id="sf_rows"></div><div id="sf_edit"></div>');
        }
        api('staff', { branch_id: $('#sf_branch').val(), q: $('#sf_q').val() }).then(function (r) {
            staffCache = r.rows;
            $('#sf_rows').html(OPS.table([
                { label: 'Name', render: function (u) { return '<strong>' + esc(u.name) + '</strong><div class="swl-ops-small swl-ops-muted">' + esc([u.employee_no, u.email].filter(Boolean).join(' · ')) + '</div>'; } },
                { label: 'Account role', render: function (u) { return esc(u.role_name || ''); } },
                { label: 'Job role', render: function (u) { return u.job_role ? esc(roleLabel(u.job_role)) : '<span class="swl-ops-muted">' + esc(roleLabel(u.job_role_guess)) + ' (default)</span>'; } },
                { label: 'Branch', render: function (u) { return esc(u.branch_name || '—') + (u.branch_ids.length ? '<div class="swl-ops-small swl-ops-muted">+' + u.branch_ids.length + ' more</div>' : ''); } },
                { label: 'Status', render: function (u) { return OPS.chip(u.status || (u.active == 1 ? 'active' : 'inactive'), (u.status || 'active') === 'active' && u.active == 1 ? 'ok' : 'warn'); } },
                { label: 'Last sign-in', render: function (u) { return esc(u.lastlogin || '—'); } },
                { label: '', render: function (u) { return M.can['staff.manage'] ? '<button type="button" class="btn btn-sm btn-secondary sf-edit" data-row="' + esc(JSON.stringify(u)) + '">Profile</button>' : ''; } }
            ], r.rows, 'No staff accounts found.'));
        });
    }
    $(document).on('change', '#sf_branch', staff);
    $(document).on('input', '#sf_q', OPS.debounce(staff, 400));
    $(document).on('click', '.sf-edit', function () {
        var u = $(this).data('row');
        var h = '<hr><h3 class="card-title">Profile of ' + esc(u.name) + '</h3><input type="hidden" id="pf_uid" value="' + u.id + '"><div class="row">'
            + OPS.field('pf_role', 'Job role', OPS.select('pf_role', Object.keys(M.roles).map(function (k) { return [k, M.roles[k]]; }), u.job_role || u.job_role_guess), 'col-md-4')
            + OPS.field('pf_no', 'Employee number', OPS.input('pf_no', u.employee_no || '', 'maxlength="30"'), 'col-md-4')
            + OPS.field('pf_home', 'Home branch', OPS.select('pf_home', branchOpts('None'), u.home_branch_id || 0), 'col-md-4')
            + OPS.field('pf_hire', 'Hire date', OPS.input('pf_hire', u.hire_date || '', 'type="date"'), 'col-md-4')
            + OPS.field('pf_status', 'Status', OPS.select('pf_status', [['active', 'Active'], ['suspended', 'Suspended'], ['left', 'Left']], u.status || 'active'), 'col-md-4')
            + OPS.field('pf_limit', 'Approval limit (amount)', OPS.input('pf_limit', u.approval_limit || 0, 'type="number" min="0" step="0.01"'), 'col-md-4')
            + '</div><fieldset><legend>Also works in</legend>' + (M.branches || []).map(function (b) {
                return '<span class="custom-control"><input type="checkbox" class="custom-control-input pf-br" id="pf_br_' + b.id + '" value="' + b.id + '"' + (u.branch_ids.indexOf(b.id) !== -1 ? ' checked' : '') + '><label class="custom-control-label" for="pf_br_' + b.id + '">' + esc(b.name) + '</label></span>';
            }).join('') + '</fieldset><button type="button" class="btn" id="pf_save">Save profile</button>';
        $('#sf_edit').html(h);
        document.getElementById('pf_role').focus();
    });
    $(document).on('click', '#pf_save', function () {
        api('save_profile', { user_id: $('#pf_uid').val(), job_role: $('#pf_role').val(), employee_no: $('#pf_no').val(), home_branch_id: $('#pf_home').val(), hire_date: $('#pf_hire').val(),
            status: $('#pf_status').val(), approval_limit: $('#pf_limit').val(), branch_ids: $('.pf-br:checked').map(function () { return this.value; }).get() }, true)
            .then(function (r) { if (OPS.result(r, 'Profile saved')) { $('#sf_edit').empty(); staff(); } });
    });

    // ------------------------------------------------------- role access
    function access() {
        var roles = Object.keys(M.roles), caps = Object.keys(M.capabilities), dis = M.is_admin ? '' : ' disabled';
        var h = '<p class="swl-ops-muted">What each job role may do in this module. Branch-wide abilities apply only to the branches the person works in. Which pages open is still decided by the account\'s permissions (User accounts &amp; permissions).</p>'
            + '<div class="table-responsive"><table class="table"><thead><tr><th scope="col">Ability</th>' + roles.map(function (r) { return '<th scope="col" class="text-center">' + esc(M.roles[r]) + '</th>'; }).join('') + '</tr></thead><tbody>'
            + caps.map(function (c) {
                return '<tr><td>' + esc(M.capabilities[c].label) + (M.capabilities[c].scoped ? ' <span class="swl-ops-small swl-ops-muted">(own branches)</span>' : '') + '</td>' + roles.map(function (r) {
                    var on = (M.matrix[r] || []).indexOf(c) !== -1;
                    return '<td class="text-center"><input type="checkbox" class="ac-cap" aria-label="' + esc(M.roles[r] + ': ' + M.capabilities[c].label) + '" data-r="' + r + '" data-c="' + c + '"' + (on ? ' checked' : '') + (r === 'administrator' ? ' disabled' : dis) + '></td>';
                }).join('') + '</tr>';
            }).join('') + '</tbody></table></div>';
        h += '<div class="form-group mt-3"><label for="ac_tr">Branch transfers need approval before they leave</label>' + OPS.select('ac_tr', [['1', 'Yes'], ['0', 'No']], M.transfer_needs_approval ? '1' : '0', dis.trim()) + '</div>';
        if (M.is_admin) { h += '<button type="button" class="btn" id="ac_save">Save role access</button>'; }
        body('access').html(h);
    }
    $(document).on('click', '#ac_save', function () {
        var m = {};
        $('.ac-cap').each(function () { var r = $(this).data('r'); m[r] = m[r] || []; if (this.checked) { m[r].push($(this).data('c')); } });
        api('save_matrix', { matrix: JSON.stringify(m), transfer_needs_approval: $('#ac_tr').val() }, true).then(function (r) { if (OPS.result(r, 'Role access saved')) { refreshMeta(); } });
    });

    // -------------------------------------------------------------- shifts
    var week = monday();
    function shifts() {
        var canPlan = M.can['shifts.manage'];
        if (!$('#shf_grid').length) {
            body('shifts').html('<div class="swl-ops-toolbar"><button type="button" class="btn btn-sm btn-secondary shf-nav" data-d="-7">Previous week</button><strong id="shf_label"></strong>'
                + '<button type="button" class="btn btn-sm btn-secondary shf-nav" data-d="7">Next week</button><label for="shf_branch" class="sr-only">Branch</label>' + OPS.select('shf_branch', branchOpts('All my branches'), 0)
                + (canPlan ? '<button type="button" class="btn btn-sm btn-secondary" id="shf_copy">Copy this week to next week</button>' : '') + '</div><div id="shf_grid" class="swl-ops-week"></div><div id="shf_form"></div>');
            if (canPlan) {
                staffSelect('#shf_user_box', 'shf_user', 0);
                $('#shf_form').html('<hr><h3 class="card-title">Plan a shift</h3><div class="row align-items-end">' + OPS.field('shf_user', 'Staff member', '<span id="shf_user_box"></span>', 'col-md-4')
                    + OPS.field('shf_br', 'Branch', OPS.select('shf_br', branchOpts('—'), 0), 'col-md-4') + OPS.field('shf_date', 'Date', OPS.input('shf_date', week, 'type="date"'), 'col-md-4')
                    + OPS.field('shf_start', 'Start', OPS.input('shf_start', '08:00', 'type="time"'), 'col-md-3') + OPS.field('shf_end', 'End', OPS.input('shf_end', '17:00', 'type="time"'), 'col-md-3')
                    + OPS.field('shf_note', 'Note', OPS.input('shf_note', '', 'maxlength="190"'), 'col-md-4') + '<div class="col-auto"><div class="form-group"><button type="button" class="btn" id="shf_save">Add shift</button></div></div></div>');
                staffSelect('#shf_user_box', 'shf_user', 0);
            }
        }
        $('#shf_label').text(week + ' – ' + addDays(week, 6));
        api('shifts', { from: week, to: addDays(week, 6), branch_id: $('#shf_branch').val() || 0 }).then(function (r) {
            var days = [];
            for (var i = 0; i < 7; i++) { days.push(addDays(week, i)); }
            $('#shf_grid').html(days.map(function (d) {
                var items = r.rows.filter(function (s) { return s.shift_date === d; });
                return '<div class="swl-ops-day"><h6>' + esc(new Date(d + 'T12:00:00').toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' })) + '</h6>' + items.map(function (s) {
                    return '<div class="swl-ops-shift"><strong>' + esc(s.staff_name) + '</strong><br>' + esc(String(s.start_time).slice(0, 5) + '–' + String(s.end_time).slice(0, 5)) + (s.branch_name ? ' · ' + esc(s.branch_name) : '')
                        + '<br>' + OPS.chip(nice(s.status), OPS.statusTone(s.status === 'checked_in' || s.status === 'completed' ? 'ok' : (s.status === 'missed' ? 'failed' : 'pending')))
                        + (canPlan && s.status === 'scheduled' ? ' <button type="button" class="btn btn-sm btn-link shf-cancel" data-id="' + s.id + '">Cancel</button>' : '') + '</div>';
                }).join('') + '</div>';
            }).join(''));
        });
    }
    $(document).on('click', '.shf-nav', function () { week = addDays(week, Number($(this).data('d'))); shifts(); });
    $(document).on('change', '#shf_branch', shifts);
    $(document).on('click', '#shf_save', function () {
        api('save_shift', { user_id: $('#shf_user').val(), branch_id: $('#shf_br').val(), shift_date: $('#shf_date').val(), start_time: $('#shf_start').val(), end_time: $('#shf_end').val(), note: $('#shf_note').val() }, true)
            .then(function (r) { if (OPS.result(r, 'Shift added')) { shifts(); } });
    });
    $(document).on('click', '.shf-cancel', function () { api('cancel_shift', { id: $(this).data('id') }, true).then(function (r) { if (OPS.result(r, 'Shift cancelled')) { shifts(); } }); });
    $(document).on('click', '#shf_copy', function () {
        OPS.confirm('Copy every shift of this week to the next week? Overlapping shifts are skipped.').then(function () {
            api('copy_week', { week_start: week, branch_id: $('#shf_branch').val() || 0 }, true).then(function (r) { OPS.toast(r.copied + ' shifts copied'); week = addDays(week, 7); shifts(); });
        });
    });

    // --------------------------------------------------------------- tasks
    function tasks() {
        if (!$('#tk_rows').length) {
            body('tasks').html('<div class="swl-ops-toolbar"><label for="tk_status" class="sr-only">Show</label>' + OPS.select('tk_status', [['active', 'Open and in progress'], ['done', 'Done'], ['cancelled', 'Cancelled'], ['', 'All']], 'active')
                + '<label for="tk_branch" class="sr-only">Branch</label>' + OPS.select('tk_branch', branchOpts('All branches'), 0) + '<label class="custom-control"><input type="checkbox" class="custom-control-input" id="tk_mine"><span class="custom-control-label">Only mine</span></label></div><div id="tk_rows"></div><div id="tk_form"></div>');
        }
        api('tasks', { status: $('#tk_status').val(), branch_id: $('#tk_branch').val(), mine: $('#tk_mine').is(':checked') ? 1 : 0 }).then(function (r) {
            $('#tk_rows').html(OPS.table([
                { label: 'Task', render: function (t) { return '<strong>' + esc(t.title) + '</strong><div class="swl-ops-small swl-ops-muted">' + esc((M.task_types[t.task_type] || t.task_type) + (t.entity_ref ? ' · ' + t.entity_ref : '') + (Number(t.quantity) > 1 ? ' · ×' + t.quantity : '') + (t.branch_name ? ' · ' + t.branch_name : '')) + '</div>'; } },
                { label: 'Priority', render: function (t) { return OPS.chip(t.priority, t.priority === 'high' ? 'bad' : (t.priority === 'low' ? '' : 'info')); } },
                { label: 'Assigned to', render: function (t) { return esc(t.assignee_name || 'Anyone'); } },
                { label: 'Due', render: function (t) { return esc(t.due_at || '—') + (Number(t.overdue) ? ' ' + OPS.chip('overdue', 'bad') : ''); } },
                { label: 'Status', render: function (t) { return OPS.chip(nice(t.status), t.status === 'done' ? 'ok' : (t.status === 'in_progress' ? 'info' : (t.status === 'cancelled' ? '' : 'warn'))); } },
                { label: '', render: function (t) {
                    if (t.status === 'done' || t.status === 'cancelled') { return ''; }
                    return '<span class="btn-group">' + (t.status === 'open' ? '<button type="button" class="btn btn-sm btn-secondary tk-st" data-id="' + t.id + '" data-s="in_progress">Start</button>' : '')
                        + '<button type="button" class="btn btn-sm btn-secondary tk-st" data-id="' + t.id + '" data-s="done">Done</button>'
                        + (M.can['tasks.manage'] ? '<button type="button" class="btn btn-sm btn-outline-secondary tk-st" data-id="' + t.id + '" data-s="cancelled">Cancel</button>' : '') + '</span>';
                } }
            ], r.rows, 'No tasks.'));
            if (M.can['tasks.manage'] && !$('#tk_title').length) {
                $('#tk_form').html('<hr><h3 class="card-title">New task</h3><div class="row">' + OPS.field('tk_title', 'Task', OPS.input('tk_title', '', 'maxlength="190" placeholder="Receive and label the Monday air delivery"'), 'col-md-6')
                    + OPS.field('tk_type', 'Type', OPS.select('tk_type', Object.keys(M.task_types).map(function (k) { return [k, M.task_types[k]]; }), 'receive'), 'col-md-3')
                    + OPS.field('tk_priority', 'Priority', OPS.select('tk_priority', [['normal', 'Normal'], ['high', 'High'], ['low', 'Low']], 'normal'), 'col-md-3')
                    + OPS.field('tk_assignee', 'Assign to', OPS.select('tk_assignee', [[0, 'Anyone in the branch']].concat(r.assignees.map(function (a) { return [a.id, a.name]; })), 0), 'col-md-4')
                    + OPS.field('tk_br', 'Branch', OPS.select('tk_br', branchOpts('—'), 0), 'col-md-4') + OPS.field('tk_due', 'Due', '<input type="datetime-local" id="tk_due" class="form-control">', 'col-md-4')
                    + OPS.field('tk_ref', 'Reference (tracking, consolidation…)', OPS.input('tk_ref', '', 'maxlength="80"'), 'col-md-4') + OPS.field('tk_qty', 'Quantity', OPS.input('tk_qty', 1, 'type="number" min="1"'), 'col-md-2')
                    + OPS.field('tk_desc', 'Details', '<textarea id="tk_desc" class="form-control" rows="2"></textarea>', 'col-md-6') + '</div><button type="button" class="btn" id="tk_save">Create task</button>');
            }
        });
    }
    $(document).on('change', '#tk_status, #tk_branch, #tk_mine', tasks);
    $(document).on('click', '.tk-st', function () { api('task_status', { id: $(this).data('id'), status: $(this).data('s') }, true).then(function (r) { if (OPS.result(r, 'Updated')) { tasks(); } }); });
    $(document).on('click', '#tk_save', function () {
        api('save_task', { title: $('#tk_title').val(), task_type: $('#tk_type').val(), priority: $('#tk_priority').val(), assignee_id: $('#tk_assignee').val(), branch_id: $('#tk_br').val(),
            due_at: $('#tk_due').val(), entity_ref: $('#tk_ref').val(), quantity: $('#tk_qty').val(), description: $('#tk_desc').val() }, true)
            .then(function (r) { if (OPS.result(r, 'Task created')) { $('#tk_title, #tk_ref, #tk_desc').val(''); tasks(); } });
    });

    // ----------------------------------------------------------- transfers
    function transfers() {
        if (!$('#tr_rows').length) {
            body('transfers').html('<div class="swl-ops-toolbar"><label for="tr_status" class="sr-only">Show</label>' + OPS.select('tr_status', [['open', 'Open'], ['received', 'Received'], ['cancelled', 'Cancelled'], ['', 'All']], 'open') + '</div><div id="tr_rows"></div><div id="tr_detail"></div>'
                + (M.can['transfers.create'] || M.is_admin ? '<hr><h3 class="card-title">New transfer</h3><div class="row">' + OPS.field('tr_from', 'From branch', OPS.select('tr_from', branchOpts('Choose…'), 0), 'col-md-4')
                    + OPS.field('tr_to', 'To branch', OPS.select('tr_to', branchOpts('Choose…'), 0), 'col-md-4') + OPS.field('tr_notes', 'Notes', OPS.input('tr_notes', '', 'maxlength="255"'), 'col-md-4')
                    + OPS.field('tr_items', 'Tracking numbers (scan or paste, one per line)', '<textarea id="tr_items" class="form-control" rows="4"></textarea>', 'col-12') + '</div>'
                    + '<p class="swl-ops-muted">' + (M.transfer_needs_approval ? 'The transfer waits for approval before it can be dispatched.' : 'The transfer can be dispatched straight away.') + '</p><button type="button" class="btn" id="tr_save">Request transfer</button>' : ''));
        }
        api('transfers', { status: $('#tr_status').val() }).then(function (r) {
            $('#tr_rows').html(OPS.table([
                { label: 'Transfer', render: function (t) { return '<button type="button" class="btn btn-link tr-open" data-id="' + t.id + '">' + esc(t.ref) + '</button><div class="swl-ops-small swl-ops-muted">' + esc(t.created_at) + ' · ' + esc(t.requested_name) + '</div>'; } },
                { label: 'Route', render: function (t) { return esc(t.from_name) + ' → ' + esc(t.to_name); } },
                { label: 'Items', render: function (t) { return OPS.num(t.items) + (t.status === 'received' ? ' (' + OPS.num(t.received_items) + ' arrived)' : ''); } },
                { label: 'Status', render: function (t) { return OPS.chip(nice(t.status), t.status === 'received' ? (Number(t.received_items) < Number(t.items) ? 'warn' : 'ok') : (t.status === 'cancelled' ? '' : 'info')); } }
            ], r.rows, 'No transfers.'));
        });
    }
    $(document).on('change', '#tr_status', transfers);
    $(document).on('click', '#tr_save', function () {
        api('create_transfer', { from_branch_id: $('#tr_from').val(), to_branch_id: $('#tr_to').val(), notes: $('#tr_notes').val(), items: $('#tr_items').val() }, true)
            .then(function (r) { if (OPS.result(r, 'Transfer ' + (r.ref || '') + ' requested')) { $('#tr_items').val(''); transfers(); } });
    });
    $(document).on('click', '.tr-open', function () {
        api('transfer', { id: $(this).data('id') }).then(function (r) {
            var t = r.transfer, h = '<hr><h3 class="card-title">Transfer ' + esc(t.ref) + ' · ' + nice(t.status) + '</h3>';
            h += OPS.table([{ label: 'Item', render: function (i) { return r.can_receive ? '<label class="custom-control"><input type="checkbox" class="custom-control-input tr-rcv" value="' + i.id + '" checked><span class="custom-control-label">' + esc(i.entity_ref) + '</span></label>' : esc(i.entity_ref); } },
                { label: 'Type', render: function (i) { return nice(i.entity_type); } }, { label: 'Arrived', render: function (i) { return t.status === 'received' ? (Number(i.received) ? OPS.chip('yes', 'ok') : OPS.chip(i.received_note || 'missing', 'bad')) : '—'; } }], r.items);
            if (r.can_dispatch) { h += '<button type="button" class="btn mt-2 tr-act" data-op="dispatch" data-id="' + t.id + '">Mark as dispatched</button> '; }
            if (r.can_receive) { h += '<p class="swl-ops-muted mt-2">Untick anything that did not arrive.</p><button type="button" class="btn tr-act" data-op="receive" data-id="' + t.id + '">Confirm receipt</button> '; }
            if (t.status === 'requested' || t.status === 'approved') { h += '<button type="button" class="btn btn-outline-secondary mt-2 tr-act" data-op="cancel" data-id="' + t.id + '">Cancel transfer</button>'; }
            $('#tr_detail').html(h);
        });
    });
    $(document).on('click', '.tr-act', function () {
        api('transfer_action', { id: $(this).data('id'), op: $(this).data('op'), received: $('.tr-rcv:checked').map(function () { return this.value; }).get() }, true)
            .then(function (r) { if (OPS.result(r, 'Updated')) { $('#tr_detail').empty(); transfers(); } });
    });

    // ----------------------------------------------------------- approvals
    function approvals() {
        if (!$('#ap_rows').length) {
            body('approvals').html('<div class="swl-ops-toolbar"><label for="ap_status" class="sr-only">Show</label>' + OPS.select('ap_status', [['pending', 'Waiting for a decision'], ['approved', 'Approved'], ['rejected', 'Rejected'], ['', 'All']], 'pending')
                + '<label class="custom-control"><input type="checkbox" class="custom-control-input" id="ap_mine"><span class="custom-control-label">Only my requests</span></label></div><div id="ap_rows"></div>'
                + '<hr><h3 class="card-title">Ask for approval</h3><div class="row">' + OPS.field('ap_kind', 'Type', OPS.select('ap_kind', Object.keys(M.approval_kinds).filter(function (k) { return k !== 'transfer'; }).map(function (k) { return [k, M.approval_kinds[k]]; }), 'expense'), 'col-md-3')
                + OPS.field('ap_title', 'What', OPS.input('ap_title', '', 'maxlength="190"'), 'col-md-5') + OPS.field('ap_amount', 'Amount (if any)', OPS.input('ap_amount', '', 'type="number" min="0" step="0.01"'), 'col-md-2')
                + OPS.field('ap_branch', 'Branch', OPS.select('ap_branch', branchOpts('—'), 0), 'col-md-2') + OPS.field('ap_details', 'Details', '<textarea id="ap_details" class="form-control" rows="2"></textarea>', 'col-12')
                + '</div><button type="button" class="btn" id="ap_save">Send request</button>');
        }
        api('approvals', { status: $('#ap_status').val(), mine: $('#ap_mine').is(':checked') ? 1 : 0 }).then(function (r) {
            $('#ap_rows').html(OPS.table([
                { label: 'Request', render: function (a) { return '<strong>' + esc(a.title) + '</strong><div class="swl-ops-small swl-ops-muted">' + esc((M.approval_kinds[a.kind] || a.kind) + ' · ' + a.requested_name + ' · ' + a.created_at + (a.branch_name ? ' · ' + a.branch_name : '')) + '</div>' + (a.details ? '<div class="swl-ops-small">' + esc(a.details) + '</div>' : ''); } },
                { label: 'Amount', render: function (a) { return a.amount === null ? '—' : OPS.num(a.amount, 2); } },
                { label: 'Status', render: function (a) { return OPS.chip(a.status, OPS.statusTone(a.status)) + (a.decided_name ? '<div class="swl-ops-small swl-ops-muted">' + esc(a.decided_name + ' ' + (a.decided_at || '')) + (a.decision_note ? ': ' + esc(a.decision_note) : '') + '</div>' : ''); } },
                { label: '', render: function (a) { return a.can_decide ? '<span class="btn-group"><button type="button" class="btn btn-sm ap-dec" data-id="' + a.id + '" data-d="approved">Approve</button><button type="button" class="btn btn-sm btn-outline-secondary ap-dec" data-id="' + a.id + '" data-d="rejected">Reject</button></span>' : ''; } }
            ], r.rows, 'Nothing here.'));
        });
    }
    $(document).on('change', '#ap_status, #ap_mine', approvals);
    $(document).on('click', '.ap-dec', function () {
        var id = $(this).data('id'), dec = $(this).data('d'), note = window.prompt(dec === 'approved' ? 'Note (optional)' : 'Why is it rejected?');
        if (note === null) { return; }
        api('decide', { id: id, decision: dec, note: note }, true).then(function (r) { if (OPS.result(r, dec === 'approved' ? 'Approved' : 'Rejected')) { approvals(); } });
    });
    $(document).on('click', '#ap_save', function () {
        api('request_approval', { kind: $('#ap_kind').val(), title: $('#ap_title').val(), amount: $('#ap_amount').val(), branch_id: $('#ap_branch').val(), details: $('#ap_details').val() }, true)
            .then(function (r) { if (OPS.result(r, 'Request sent')) { $('#ap_title, #ap_amount, #ap_details').val(''); approvals(); } });
    });

    // ------------------------------------------------------- announcements
    function announcements() {
        var canPost = M.can['announcements.post'];
        api('announcements', { all: canPost ? 1 : 0 }).then(function (r) {
            var h = r.rows.map(function (a) {
                return '<div class="swl-ops-insight"><div class="d-flex justify-content-between flex-wrap"><b>' + (Number(a.pinned) ? '📌 ' : '') + esc(a.title) + '</b><span class="swl-ops-small swl-ops-muted">' + esc(a.author + ' · ' + a.created_at)
                    + ' · ' + esc(a.audience_type === 'all' ? 'everyone' : (a.audience_type === 'role' ? roleLabel(a.audience_value) : 'branch')) + '</span></div><div class="mt-2">' + esc(a.body).replace(/\n/g, '<br>') + '</div>'
                    + '<div class="swl-ops-small swl-ops-muted mt-2">' + (Number(a.requires_ack) ? OPS.num(a.acknowledged) + ' acknowledged' : OPS.num(a.acknowledged) + ' read') + '</div></div>';
            }).join('') || '<p class="swl-ops-muted">No announcements.</p>';
            if (canPost) {
                h += '<hr><h3 class="card-title">Post an announcement</h3><div class="row">' + OPS.field('an_title', 'Title', OPS.input('an_title', '', 'maxlength="190"'), 'col-md-6')
                    + OPS.field('an_type', 'Who sees it', OPS.select('an_type', M.is_admin ? [['all', 'Everyone'], ['branch', 'One branch'], ['role', 'One job role']] : [['branch', 'One branch']], M.is_admin ? 'all' : 'branch'), 'col-md-3')
                    + OPS.field('an_value', 'Branch / role', '<span id="an_value_box"></span>', 'col-md-3') + OPS.field('an_body', 'Message', '<textarea id="an_body" class="form-control" rows="4" maxlength="8000"></textarea>', 'col-12')
                    + OPS.field('an_exp', 'Show until (optional)', OPS.input('an_exp', '', 'type="date"'), 'col-md-4') + '<div class="col-md-8"><label class="custom-control"><input type="checkbox" class="custom-control-input" id="an_pin"><span class="custom-control-label">Pin to the top</span></label>'
                    + '<label class="custom-control"><input type="checkbox" class="custom-control-input" id="an_ack"><span class="custom-control-label">Everyone must acknowledge</span></label></div></div><button type="button" class="btn" id="an_save">Post</button>';
            }
            body('announcements').html(h);
            anValue();
        });
    }
    function anValue() {
        var t = $('#an_type').val();
        $('#an_value_box').html(t === 'role' ? OPS.select('an_value', Object.keys(M.roles).map(function (k) { return [k, M.roles[k]]; }), 'warehouse_operator')
            : (t === 'branch' ? OPS.select('an_value', branchOpts(), (M.branches[0] || {}).id) : '<span class="swl-ops-muted">—</span>'));
    }
    $(document).on('change', '#an_type', anValue);
    $(document).on('click', '#an_save', function () {
        api('post_announcement', { title: $('#an_title').val(), body: $('#an_body').val(), audience_type: $('#an_type').val(), audience_value: $('#an_value').val() || '', expires_at: $('#an_exp').val(),
            pinned: $('#an_pin').is(':checked') ? 1 : 0, requires_ack: $('#an_ack').is(':checked') ? 1 : 0 }, true).then(function (r) { if (OPS.result(r, 'Posted')) { announcements(); } });
    });

    // --------------------------------------------------------- commissions
    function commissions() {
        api('commissions').then(function (r) {
            var h = '';
            if (r.can_manage) {
                h += '<div class="d-flex justify-content-between flex-wrap align-items-center mb-3"><p class="swl-ops-muted mb-0">Commission is earned on shipments of the agent\'s agency once they are delivered or picked up, and accrued every night (or now).</p><button type="button" class="btn btn-secondary btn-sm" id="cm_accrue">Calculate now</button></div>';
            }
            h += '<h3 class="card-title">Agents</h3>' + OPS.table([{ key: 'name', label: 'Agent' },
                { label: 'Agencies linked', render: function (a) { return a.agency_ids.length ? OPS.num(a.agency_ids.length) : OPS.chip('not linked', 'warn'); } },
                { label: 'Rule', render: function (a) { return esc(a.calc === 'flat' ? OPS.num(a.rate, 2) + ' per shipment' : a.rate + '%') + (a.own ? '' : ' <span class="swl-ops-muted">(default)</span>'); } },
                { label: '', render: function (a) { return '<a class="btn btn-sm btn-secondary" href="agent_portal.php?agent_id=' + a.id + '">Portal</a>'; } }], r.agents, 'No agent accounts. Give an account the "Partner agent" job role under Staff.');
            h += '<h3 class="card-title mt-3">Statements</h3>' + OPS.table([{ key: 'agent_name', label: 'Agent' }, { key: 'period', label: 'Month' }, { label: 'Shipments', render: function (s) { return OPS.num(s.items); } },
                { label: 'Pending', render: function (s) { return OPS.num(s.pending, 2); } }, { label: 'Approved', render: function (s) { return OPS.num(s.approved, 2); } }, { label: 'Paid', render: function (s) { return OPS.num(s.paid, 2); } },
                { label: '', render: function (s) {
                    if (!r.can_manage) { return ''; }
                    return '<span class="btn-group">' + (Number(s.pending) > 0 ? '<button type="button" class="btn btn-sm btn-secondary cm-ap" data-a="' + s.agent_id + '" data-p="' + esc(s.period) + '">Approve</button>' : '')
                        + (Number(s.approved) > 0 ? '<button type="button" class="btn btn-sm cm-pay" data-a="' + s.agent_id + '" data-p="' + esc(s.period) + '">Mark paid</button>' : '') + '</span>';
                } }], r.summary, 'No commission yet.');
            if (r.can_manage) {
                h += '<hr><h3 class="card-title">Commission rule</h3><div class="row align-items-end">' + OPS.field('cr_agent', 'For', OPS.select('cr_agent', [[0, 'All agents (default)']].concat(r.agents.map(function (a) { return [a.id, a.name]; })), 0), 'col-md-4')
                    + OPS.field('cr_calc', 'Calculation', OPS.select('cr_calc', [['percent', 'Percentage of charges'], ['flat', 'Fixed amount per shipment']], r.default_rule.calc), 'col-md-3')
                    + OPS.field('cr_value', 'Value', OPS.input('cr_value', r.default_rule.value, 'type="number" min="0" step="0.01"'), 'col-md-2')
                    + OPS.field('cr_min', 'Only shipments of at least', OPS.input('cr_min', r.default_rule.min_amount, 'type="number" min="0" step="0.01"'), 'col-md-3')
                    + '</div><button type="button" class="btn" id="cr_save">Save rule</button>';
            }
            body('commissions').html(h);
        });
    }
    $(document).on('click', '#cm_accrue', function () { api('accrue_commissions', {}, true).then(function (r) { OPS.toast(r.added + ' shipments added'); commissions(); }); });
    $(document).on('click', '.cm-ap', function () { api('approve_commissions', { agent_id: $(this).data('a'), period: $(this).data('p') }, true).then(function (r) { if (OPS.result(r, r.approved + ' items approved')) { commissions(); } }); });
    $(document).on('click', '.cm-pay', function () {
        var a = $(this).data('a'), p = $(this).data('p'), ref = window.prompt('Payment reference (transfer number, receipt…)');
        if (ref === null) { return; }
        api('pay_commissions', { agent_id: a, period: p, ref: ref }, true).then(function (r) { if (OPS.result(r, r.paid + ' items paid')) { commissions(); } });
    });
    $(document).on('click', '#cr_save', function () {
        api('save_commission_rule', { agent_id: $('#cr_agent').val(), calc: $('#cr_calc').val(), value: $('#cr_value').val(), min_amount: $('#cr_min').val() }, true).then(function (r) { if (OPS.result(r, 'Rule saved')) { commissions(); } });
    });

    // --------------------------------------------------------- performance
    function performance() {
        if (!$('#pf_rows').length) {
            var t = new Date(), f = new Date(); f.setDate(t.getDate() - 29);
            body('performance').html('<div class="swl-ops-toolbar"><label for="pr_from" class="sr-only">From</label><input type="date" id="pr_from" class="form-control" value="' + f.toISOString().slice(0, 10) + '">'
                + '<label for="pr_to" class="sr-only">To</label><input type="date" id="pr_to" class="form-control" value="' + t.toISOString().slice(0, 10) + '">'
                + '<label for="pr_branch" class="sr-only">Branch</label>' + OPS.select('pr_branch', branchOpts(M.is_admin ? 'All branches' : 'My branches'), 0)
                + '<button type="button" class="btn btn-sm btn-secondary pr-exp" data-f="xlsx">Excel</button><button type="button" class="btn btn-sm btn-secondary pr-exp" data-f="pdf">PDF</button><button type="button" class="btn btn-sm btn-secondary pr-exp" data-f="csv">CSV</button></div>'
                + '<div id="pf_rows"></div><p class="swl-ops-muted mt-3">Score (0-100): tasks done on time 35%, shift attendance 25%, punctuality 15%, output compared with the busiest person in the same role 25%. Parts without data are left out.</p>');
        }
        api('performance', { from: $('#pr_from').val(), to: $('#pr_to').val(), branch_id: $('#pr_branch').val() }).then(function (r) {
            $('#pf_rows').html(OPS.table([
                { label: 'Name', render: function (p) { return '<strong>' + esc(p.name) + '</strong><div class="swl-ops-small swl-ops-muted">' + esc(roleLabel(p.job_role) + (p.branch ? ' · ' + p.branch : '')) + '</div>'; } },
                { label: 'Shipments', render: function (p) { return OPS.num(p.shipments); } }, { label: 'Packages', render: function (p) { return OPS.num(p.packages); } },
                { label: 'Deliveries', render: function (p) { return OPS.num(p.deliveries); } },
                { label: 'Tasks', render: function (p) { return OPS.num(p.tasks_done) + (p.tasks_overdue ? ' ' + OPS.chip(p.tasks_overdue + ' overdue', 'bad') : ''); } },
                { label: 'On time', render: function (p) { return p.on_time_pct === null ? '—' : p.on_time_pct + '%'; } },
                { label: 'Shifts', render: function (p) { return OPS.num(p.attended) + ' / ' + OPS.num(p.shifts); } },
                { label: 'Punctual', render: function (p) { return p.punctual_pct === null ? '—' : p.punctual_pct + '%'; } },
                { label: 'Hours', render: function (p) { return OPS.num(p.hours, 1); } }, { label: 'Per hour', render: function (p) { return p.per_hour === null ? '—' : p.per_hour; } },
                { label: 'Score', render: function (p) { return p.score === null ? '—' : OPS.chip(p.score, p.score >= 75 ? 'ok' : (p.score >= 50 ? 'warn' : 'bad')); } }
            ], r.rows, 'No staff in this selection.'));
        });
    }
    $(document).on('change', '#pr_from, #pr_to, #pr_branch', performance);
    $(document).on('click', '.pr-exp', function () {
        OPS.download(URL + '?' + $.param({ action: 'export_performance', format: $(this).data('f'), from: $('#pr_from').val(), to: $('#pr_to').val(), branch_id: $('#pr_branch').val() }));
    });

    // ------------------------------------------------------------ activity
    function activity() {
        if (!$('#ac_rows').length) {
            body('activity').html('<div class="swl-ops-toolbar"><label for="av_branch" class="sr-only">Branch</label>' + OPS.select('av_branch', branchOpts(M.is_admin ? 'All staff' : 'My branches'), 0)
                + '<span id="av_user_box"></span><label for="av_q" class="sr-only">Search</label><input id="av_q" type="search" class="form-control" placeholder="Search what happened"></div><div id="ac_rows"></div>');
            staffCache ? $('#av_user_box').html('<label for="av_user" class="sr-only">Person</label>' + OPS.select('av_user', [[0, 'Everyone']].concat(staffCache.map(function (u) { return [u.id, u.name]; })), 0))
                : api('staff').then(function (r) { staffCache = r.rows; $('#av_user_box').html('<label for="av_user" class="sr-only">Person</label>' + OPS.select('av_user', [[0, 'Everyone']].concat(r.rows.map(function (u) { return [u.id, u.name]; })), 0)); }, function () {});
        }
        api('activity', { branch_id: $('#av_branch').val(), user_id: $('#av_user').val() || 0, q: $('#av_q').val() }).then(function (r) {
            $('#ac_rows').html(OPS.table([{ key: 'created_at', label: 'When' }, { label: 'Who', render: function (a) { return esc(a.actor_name) + '<div class="swl-ops-small swl-ops-muted">' + esc(a.role_name || '') + '</div>'; } },
                { label: 'What', render: function (a) { return '<strong>' + esc(a.action_label) + '</strong><div class="swl-ops-small">' + esc(a.summary || '') + '</div>'; } },
                { label: 'Record', render: function (a) { return esc(a.entity_label || ''); } },
                { label: 'Result', render: function (a) { return a.outcome && a.outcome !== 'ok' && a.outcome !== 'success' ? OPS.chip(a.outcome, 'bad') : ''; } }, { key: 'ip', label: 'IP' }], r.rows, 'No activity found.'));
        });
    }
    $(document).on('change', '#av_branch, #av_user', activity);
    $(document).on('input', '#av_q', OPS.debounce(activity, 400));

    var loaders = { branches: branches, staff: staff, access: access, shifts: shifts, tasks: tasks, transfers: transfers, approvals: approvals, announcements: announcements,
        commissions: commissions, performance: performance, activity: activity };
    function refreshMeta() { return api('meta').then(function (r) { M = r; }); }
    $(function () {
        refreshMeta().then(function () {
            if (!M.can['commissions.view']) { $('[data-tab="commissions"]').remove(); }
            if (!M.can['reports.branch']) { $('[data-tab="performance"]').remove(); }
            if (!M.can['activity.view']) { $('[data-tab="activity"]').remove(); }
            if (!M.can['staff.view'] && !M.can['staff.manage']) { $('[data-tab="staff"]').remove(); }
            OPS.tabs('sh', function (k) { if (!loaded[k] && loaders[k]) { loaded[k] = true; loaders[k](); } });
        });
    });
})(window.jQuery);
