"use strict";

// ============================================================================
// Gerald Master View (views/sl/pages/MasterView.php). Loads every widget's
// figures from ajax/mgmt/mgmt_ajax.php and draws them in the order the user
// saved. Money widgets are absent when the server left money out.
// ============================================================================

(function ($) {
    var OPS = window.OPS, esc = OPS.esc, CFG = window.CDP_MV || {};
    var URL = 'ajax/mgmt/mgmt_ajax.php';
    var state = { data: null, widgets: {}, layout: { order: [], hidden: [] }, branches: [] };
    var cur = function (v) { return v === null || v === undefined ? '—' : (CFG.currency ? CFG.currency + ' ' : '') + OPS.num(v, 2); };
    var pct = function (v) { return v === null || v === undefined ? '—' : v + '%'; };
    var status = function (s) { return esc(String(s || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); })); };

    function range() {
        var r = $('#mv_range').val(), t = new Date(), f = new Date(), iso = function (d) { return d.toISOString().slice(0, 10); };
        if (r === 'custom') { return { from: $('#mv_from').val(), to: $('#mv_to').val() }; }
        if (r === 'month') { f = new Date(t.getFullYear(), t.getMonth(), 1, 12); }
        else if (r === 'lastmonth') { f = new Date(t.getFullYear(), t.getMonth() - 1, 1, 12); t = new Date(t.getFullYear(), t.getMonth(), 0, 12); }
        else if (r === 'year') { f = new Date(t.getFullYear(), 0, 1, 12); }
        else { f.setDate(t.getDate() - (Number(r) - 1)); }
        return { from: iso(f), to: iso(t) };
    }
    function params() { var p = range(); p.branch_id = $('#mv_branch').val() || 0; return p; }

    // ------------------------------------------------------------- widgets
    var W = {};
    W.executive = function (d) {
        var s = d.shipments, k = [
            { label: 'Shipments', value: OPS.num(s.current.n), sub: 'vs ' + OPS.num(s.previous.n), delta: s.change_pct },
            { label: 'Warehouse packages', value: OPS.num(s.packages), sub: OPS.num(s.consolidations) + ' consolidations' },
            { label: 'Customers shipping', value: OPS.num(s.current.customers) }
        ];
        if (d.revenue) { k.push({ label: 'Shipment charges', value: cur(d.revenue.charges), delta: d.revenue.change_pct }); }
        if (d.delivery) { k.push({ label: 'On-time handovers', value: pct(d.delivery.on_time_pct), sub: 'previous ' + pct(d.delivery.previous_on_time_pct) }); }
        if (d.delayed) { k.push({ label: 'Delayed shipments', value: OPS.num(d.delayed.count), sub: 'open past target' }); }
        if (d.customers) { k.push({ label: 'Active customers', value: OPS.num(d.customers.active), sub: OPS.num(d.customers.new) + ' new this period' }); }
        if (d.balances && d.balances.available) { k.push({ label: 'Outstanding (GHS)', value: OPS.num(d.balances.total, 2), sub: OPS.num(d.balances.customers) + ' customers' }); }
        if (d.alerts) { var open = d.alerts.filter(function (a) { return !a.ack; }).length; k.push({ label: 'Open alerts', value: OPS.num(open), sub: 'fraud and anomaly checks' }); }
        return { size: 'full', html: OPS.kpis(k) };
    };
    W.shipments = function (d) {
        var c = d.shipments.current, p = d.shipments.previous;
        return { size: 'third', html: '<h2 class="card-title">Total shipments</h2>' + OPS.kpis([{ label: 'This period', value: OPS.num(c.n), delta: d.shipments.change_pct }]) + OPS.hbars([
            ['Air', OPS.num(c.air) + ' (prev ' + OPS.num(p.air) + ')', c.n ? 100 * c.air / c.n : 0], ['Sea', OPS.num(c.sea) + ' (prev ' + OPS.num(p.sea) + ')', c.n ? 100 * c.sea / c.n : 0],
            ['Handed over', OPS.num(c.handed), c.n ? 100 * c.handed / c.n : 0]]) + '<p class="swl-ops-muted mt-2">' + OPS.num(c.weight, 1) + ' total weight · cancelled shipments left out</p>' };
    };
    W.warehouse = function (d) {
        var w = d.warehouse;
        if (!w) { return null; }
        return { size: 'third', html: '<h2 class="card-title">Warehouse activity</h2>' + OPS.table([{ key: 'k', label: 'Measure' }, { key: 'v', label: 'Count' }], [
            { k: 'Packages received', v: OPS.num(w.received) }, { k: 'Packages waiting (all time)', v: OPS.num(w.waiting) }, { k: 'In warehouse now', v: OPS.num(w.in_warehouse) },
            { k: 'Ready for pickup', v: OPS.num(w.ready_pickup) }, { k: 'Branch transfers', v: OPS.num(w.transfers) }, { k: 'Tasks done / open', v: OPS.num(w.tasks_done) + ' / ' + OPS.num(w.tasks_open) }])
            + OPS.vbars(w.by_day.values, w.by_day.labels, 'Packages received per day') };
    };
    W.delivery = function (d) {
        var v = d.delivery;
        if (!v) { return null; }
        var rows = Object.keys(v.by_mode || {}).map(function (m) { return { m: m, n: OPS.num(v.by_mode[m].n), a: v.by_mode[m].avg_days === null ? '—' : v.by_mode[m].avg_days, o: pct(v.by_mode[m].on_time_pct) }; });
        return { size: 'third', html: '<h2 class="card-title">Delivery performance</h2>' + OPS.kpis([
            { label: 'Handed over', value: OPS.num(v.handed), sub: OPS.num(v.delivered) + ' delivered · ' + OPS.num(v.picked_up) + ' picked up' },
            { label: 'On time', value: pct(v.on_time_pct), sub: 'previous ' + pct(v.previous_on_time_pct) }, { label: 'Average days', value: v.avg_days === null ? '—' : v.avg_days }])
            + OPS.table([{ key: 'm', label: 'Mode' }, { key: 'n', label: 'Handed' }, { key: 'a', label: 'Avg days' }, { key: 'o', label: 'On time' }], rows, 'No handovers in this period.') };
    };
    W.revenue = function (d) {
        var r = d.revenue;
        if (!r) { return null; }
        var k = [{ label: 'Shipment charges', value: cur(r.charges), delta: r.change_pct, sub: 'previous ' + cur(r.previous) }, { label: 'Per shipment', value: cur(r.per_shipment) }];
        if (r.ledger && r.ledger.available) {
            k.push({ label: 'Billed (USD)', value: OPS.num(r.ledger.billed, 2) }, { label: 'Received (USD)', value: OPS.num(r.ledger.received, 2) }, { label: 'Outstanding (USD)', value: OPS.num(r.ledger.outstanding, 2) });
        }
        return { size: 'full', html: '<h2 class="card-title">Revenue performance</h2>' + OPS.kpis(k) + OPS.vbars(r.by_day.values, r.by_day.labels, 'Shipment charges per day') };
    };
    W.growth = function (d) {
        var g = d.growth;
        if (!g) { return null; }
        var cols = [{ key: 'month', label: 'Month' }, { label: 'Shipments', render: function (r) { return OPS.num(r.shipments); } }, { label: 'Growth', render: function (r) { return delta(r.growth_pct); } }];
        if (d.money) { cols.push({ label: 'Charges', render: function (r) { return cur(r.revenue); } }, { label: 'Charges growth', render: function (r) { return delta(r.revenue_growth_pct); } }); }
        cols.push({ label: 'New customers', render: function (r) { return OPS.num(r.new_customers); } });
        return { size: 'half', html: '<h2 class="card-title">Monthly growth (12 months)</h2>' + OPS.vbars(g.map(function (r) { return r.shipments; }), g.map(function (r) { return r.month; }), 'Shipments per month')
            + '<div class="mt-3">' + OPS.table(cols, g.slice().reverse()) + '</div>' };
    };
    function delta(v) { return v === null || v === undefined ? '—' : OPS.chip((v >= 0 ? '▲ ' : '▼ ') + Math.abs(v) + '%', v >= 0 ? 'ok' : 'bad'); }
    W.acquisition = function (d) {
        var c = d.customers;
        if (!c) { return null; }
        var max = Math.max.apply(null, (c.new_by_country || []).map(function (r) { return Number(r.n); }).concat([1]));
        return { size: 'third', html: '<h2 class="card-title">Customer acquisition</h2>' + OPS.kpis([{ label: 'New customers', value: OPS.num(c.new) },
            { label: 'Shipped already', value: pct(c.conversion_pct), sub: OPS.num(c.new_converted) + ' of them' }])
            + '<p class="swl-ops-muted">New customers by country</p>' + OPS.hbars((c.new_by_country || []).map(function (r) { return [r.lbl, OPS.num(r.n), 100 * r.n / max]; })) };
    };
    W.active_customers = function (d) {
        var c = d.customers;
        if (!c) { return null; }
        var t = Math.max(1, c.total);
        return { size: 'third', html: '<h2 class="card-title">Active vs inactive customers</h2>' + OPS.hbars([
            ['Active (shipped in 90 days)', OPS.num(c.active), 100 * c.active / t], ['Inactive (shipped before, not lately)', OPS.num(c.inactive), 100 * c.inactive / t],
            ['Never shipped', OPS.num(c.never), 100 * c.never / t]]) + '<p class="swl-ops-muted mt-2">' + OPS.num(c.total) + ' active accounts · ' + pct(c.active_pct) + ' shipping</p>' };
    };
    W.volume = function (d) {
        if (!d.volume) { return null; }
        var tot = d.volume.values.reduce(function (a, b) { return a + b; }, 0);
        return { size: 'half', html: '<h2 class="card-title">Shipping volume trend</h2><p class="swl-ops-muted">' + OPS.num(tot) + ' shipments, ' + OPS.num(tot / Math.max(1, d.volume.values.length), 1) + ' a day on average</p>'
            + OPS.vbars(d.volume.values, d.volume.labels, 'Shipments per day') };
    };
    W.destinations = function (d) {
        if (!d.destinations) { return null; }
        var cols = [{ key: 'country', label: 'Destination' }, { label: 'Shipments', render: function (r) { return OPS.num(r.n); } }];
        if (d.money) { cols.push({ label: 'Charges', render: function (r) { return cur(r.revenue); } }, { label: 'Margin', render: function (r) { return cur(r.margin); } }); }
        return { size: 'half', html: '<h2 class="card-title">' + (d.money ? 'Revenue by destination' : 'Shipments by destination') + '</h2>' + OPS.table(cols, d.destinations, 'No shipments in this period.') };
    };
    W.receiving = function (d) {
        var r = d.receiving;
        if (!r) { return null; }
        var max = Math.max.apply(null, r.by_staff.map(function (x) { return Number(x.n); }).concat([1]));
        return { size: 'half', html: '<h2 class="card-title">Warehouse receiving productivity</h2>' + OPS.hbars(r.by_staff.map(function (x) {
            return [x.name, OPS.num(x.n) + ' packages · ' + (x.per_day === null ? '—' : x.per_day) + ' a day', 100 * x.n / max];
        })) + '<p class="swl-ops-muted mt-3">Packages received by hour of day</p>' + OPS.vbars(r.by_hour, r.by_hour.map(function (v, i) { return i + ':00'; }), 'Packages received by hour') };
    };
    W.pending = function (d) {
        if (!d.pending) { return null; }
        return { size: 'half', html: '<h2 class="card-title">Pending shipments (no movement)</h2><p class="swl-ops-muted">Open shipments whose tracking has not moved for ' + esc(state.settings.stale_days) + ' days or more.</p>'
            + OPS.table([{ label: 'Tracking', render: function (r) { return '<strong>' + esc(r.trk) + '</strong>'; } }, { key: 'customer', label: 'Customer' }, { label: 'Status', render: function (r) { return status(r.status); } },
                { label: 'Idle days', render: function (r) { return OPS.chip(r.idle_days, r.idle_days > 14 ? 'bad' : 'warn'); } }], d.pending, 'Nothing is stuck.') };
    };
    W.delayed = function (d) {
        if (!d.delayed) { return null; }
        return { size: 'half', html: '<h2 class="card-title">Delayed shipments</h2><p class="swl-ops-muted">' + OPS.num(d.delayed.count) + ' open shipments past their delivery target (air ' + esc(state.settings.sla_air_days) + ' days, sea ' + esc(state.settings.sla_sea_days) + ' days).</p>'
            + OPS.table([{ label: 'Tracking', render: function (r) { return '<strong>' + esc(r.trk) + '</strong>'; } }, { key: 'order_date', label: 'Created' }, { key: 'customer', label: 'Customer' },
                { label: 'Status', render: function (r) { return status(r.status); } }, { label: 'Days late', render: function (r) { return OPS.chip(r.days_late, 'bad'); } }], d.delayed.rows, 'No delayed shipments.') };
    };
    W.balances = function (d) {
        var b = d.balances;
        if (!b) { return null; }
        if (!b.available) { return { size: 'half', html: '<h2 class="card-title">Outstanding customer balances</h2><p class="swl-ops-muted">The billing ledger is not in use on this installation.</p>' }; }
        var tot = Math.max(0.01, b.total);
        return { size: 'half', html: '<h2 class="card-title">Outstanding customer balances (GHS)</h2>' + OPS.hbars(Object.keys(b.aging).map(function (k) { return [k + ' days', OPS.num(b.aging[k], 2), 100 * b.aging[k] / tot]; }))
            + '<div class="mt-3">' + OPS.table([{ label: 'Customer', render: function (r) { return esc(r.customer) + ' <span class="swl-ops-muted">' + esc(r.locker || '') + '</span>'; } },
                { label: 'Balance', render: function (r) { return OPS.num(r.balance, 2); } }, { label: 'Oldest bill', render: function (r) { return esc(String(r.oldest || '').slice(0, 10)); } }], b.rows, 'Nobody owes anything.') + '</div>' };
    };
    W.complaints = function (d) {
        var c = d.complaints;
        if (!c) { return null; }
        var max = Math.max.apply(null, (c.by_category || []).map(function (r) { return Number(r.n); }).concat([1]));
        return { size: 'third', html: '<h2 class="card-title">Customer complaint metrics</h2>' + (c.available ? '' : '<p class="swl-ops-muted">Customer Service is not set up yet; only survey scores are shown.</p>') + OPS.kpis([
            { label: 'Complaints', value: OPS.num(c.complaints), sub: OPS.num(c.tickets) + ' tickets' }, { label: 'Open tickets', value: OPS.num(c.open) },
            { label: 'Resolution', value: c.avg_resolution_h === null ? '—' : c.avg_resolution_h + ' h' }, { label: 'Satisfaction', value: pct(c.csat), sub: 'survey avg ' + (c.survey_avg === null ? '—' : c.survey_avg + '/5') }])
            + OPS.hbars((c.by_category || []).map(function (r) { return [status(r.lbl).replace(/&amp;/g, '&'), OPS.num(r.n), 100 * r.n / max]; })) };
    };
    W.branches = function (d) {
        if (!d.branches) { return null; }
        var cols = [{ label: 'Branch', render: function (r) { return '<strong>' + esc(r.name) + '</strong><div class="swl-ops-small swl-ops-muted">' + esc(r.code + (r.country ? ' · ' + r.country : '')) + (r.linked ? '' : ' · not linked to an office') + '</div>'; } },
            { label: 'Shipments', render: function (r) { return OPS.num(r.shipments); } }, { label: 'Handed over', render: function (r) { return OPS.num(r.handed); } },
            { label: 'Delayed', render: function (r) { return r.delayed ? OPS.chip(r.delayed, 'bad') : '0'; } }];
        if (d.money) { cols.push({ label: 'Charges', render: function (r) { return cur(r.revenue); } }, { label: 'Margin', render: function (r) { return cur(r.margin); } }); }
        cols.push({ label: 'Staff', render: function (r) { return OPS.num(r.staff); } }, { label: 'Tasks done', render: function (r) { return OPS.num(r.tasks_done); } });
        return { size: 'full', html: '<h2 class="card-title">Branch-level performance</h2>' + OPS.table(cols, d.branches, 'No branches yet. Add them under Staff & Branches.') };
    };
    W.employees = function (d) {
        if (!d.employees) { return null; }
        return { size: 'full', html: '<h2 class="card-title">Employee productivity</h2>' + OPS.table([
            { label: 'Name', render: function (r) { return '<strong>' + esc(r.name) + '</strong><div class="swl-ops-small swl-ops-muted">' + esc(String(r.job_role || '').replace(/_/g, ' ') + (r.branch ? ' · ' + r.branch : '')) + '</div>'; } },
            { label: 'Shipments', render: function (r) { return OPS.num(r.shipments); } }, { label: 'Packages', render: function (r) { return OPS.num(r.packages); } },
            { label: 'Tasks done', render: function (r) { return OPS.num(r.tasks_done); } }, { label: 'On time', render: function (r) { return pct(r.on_time_pct); } },
            { label: 'Attendance', render: function (r) { return pct(r.attendance_pct); } }, { label: 'Hours', render: function (r) { return OPS.num(r.hours, 1); } },
            { label: 'Score', render: function (r) { return r.score === null ? '—' : OPS.chip(r.score, r.score >= 75 ? 'ok' : (r.score >= 50 ? 'warn' : 'bad')); } }], d.employees, 'No staff activity in this period.') };
    };
    W.costs = function (d) {
        var c = d.costs;
        if (!c) { return null; }
        return { size: 'half', html: '<h2 class="card-title">Shipping cost analysis</h2>' + OPS.kpis([{ label: 'Total cost', value: cur(c.total_cost) }, { label: 'Cost per kg', value: cur(c.cost_per_kg) },
            { label: 'Shipments with a cost', value: pct(c.costed_pct), sub: 'purchase price recorded' }]) + OPS.table([{ key: 'mode', label: 'Mode' }, { label: 'Shipments', render: function (r) { return OPS.num(r.shipments); } },
                { label: 'Cost / shipment', render: function (r) { return cur(r.cost_per_shipment); } }, { label: 'Cost / kg', render: function (r) { return cur(r.cost_per_kg); } }, { label: 'Cost of charges', render: function (r) { return pct(r.cost_ratio_pct); } }], c.by_mode)
            + '<p class="swl-ops-muted mt-3">By carrier</p>' + OPS.table([{ key: 'carrier', label: 'Carrier' }, { label: 'Shipments', render: function (r) { return OPS.num(r.n); } }, { label: 'Cost', render: function (r) { return cur(r.cost); } }], c.by_carrier)
            + (Number(c.overhead_per_shipment) ? '<p class="swl-ops-muted mt-2">Includes an overhead of ' + cur(c.overhead_per_shipment) + ' per shipment.</p>' : '') };
    };
    W.profit = function (d) {
        var p = d.profit;
        if (!p) { return null; }
        var list = function (rows) { return OPS.table([{ key: 'trk', label: 'Tracking' }, { key: 'customer', label: 'Customer' }, { label: 'Charges', render: function (r) { return cur(r.revenue); } }, { label: 'Cost', render: function (r) { return cur(r.cost); } },
            { label: 'Margin', render: function (r) { return OPS.chip(OPS.num(r.margin, 2), Number(r.margin) < 0 ? 'bad' : 'ok'); } }], rows, 'No costed shipments.'); };
        return { size: 'half', html: '<h2 class="card-title">Profitability per shipment</h2>' + OPS.kpis([{ label: 'Average margin', value: cur(p.avg_margin), sub: pct(p.avg_margin_pct) + ' of charges' },
            { label: 'Total margin', value: cur(p.total_margin) }, { label: 'Loss-making', value: OPS.num(p.loss_making), sub: OPS.num(p.uncosted) + ' without a recorded cost' }])
            + '<p class="swl-ops-muted">Least profitable</p>' + list(p.worst) + '<p class="swl-ops-muted mt-3">Most profitable</p>' + list(p.best) };
    };
    W.alerts = function (d) {
        if (!d.alerts) { return null; }
        var h = '<h2 class="card-title">Fraud and anomaly alerts</h2>';
        if (!d.alerts.length) { return { size: 'full', html: h + '<p class="swl-ops-muted">No anomalies found.</p>' }; }
        h += d.alerts.map(function (a) {
            return '<div class="swl-ops-insight' + (a.ack ? ' swl-ops-hidden-widget' : '') + '"><div class="d-flex justify-content-between flex-wrap align-items-center"><span>' + OPS.chip(a.severity, a.severity === 'high' ? 'bad' : (a.severity === 'medium' ? 'warn' : 'info'))
                + ' <b>' + esc(a.title) + '</b></span>' + (a.ack ? '<span class="swl-ops-small swl-ops-muted">Checked by ' + esc(a.ack.by) + ' ' + esc(a.ack.at) + (a.ack.note ? ': ' + esc(a.ack.note) : '') + '</span>'
                : '<button type="button" class="btn btn-sm btn-secondary mv-ack" data-key="' + esc(a.key) + '">Mark as checked</button>') + '</div><div class="mt-2">' + esc(a.detail) + '</div>'
                + (a.items && a.items.length ? '<div class="swl-ops-small swl-ops-muted mt-2">' + a.items.map(esc).join(' · ') + '</div>' : '') + '</div>';
        }).join('');
        return { size: 'full', html: h };
    };
    W.insights = function () {
        return { size: 'full', html: '<div class="d-flex justify-content-between align-items-center flex-wrap"><h2 class="card-title">AI business insights</h2><button type="button" class="btn btn-sm btn-secondary" id="mv_ai_refresh">Analyse again</button></div><div id="mv_ai">Loading…</div>' };
    };

    function md(text) {
        var lines = esc(text).split('\n'), out = [], inList = false;
        lines.forEach(function (l) {
            var b = l.replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>');
            if (/^\s*[-*]\s+/.test(l)) { if (!inList) { out.push('<ul>'); inList = true; } out.push('<li>' + b.replace(/^\s*[-*]\s+/, '') + '</li>'); return; }
            if (inList) { out.push('</ul>'); inList = false; }
            if (/^#{1,4}\s+/.test(l)) { out.push('<h4>' + b.replace(/^#{1,4}\s+/, '') + '</h4>'); } else if (l.trim()) { out.push('<p>' + b + '</p>'); }
        });
        if (inList) { out.push('</ul>'); }
        return out.join('');
    }
    function insights(refresh) {
        var p = params(), req = refresh ? OPS.post(URL + '?action=insights', $.extend({ action: 'insights', refresh: 1 }, p)) : OPS.get(URL, $.extend({ action: 'insights' }, p));
        $('#mv_ai').html('<p class="swl-ops-muted">' + (refresh ? 'Asking the AI to analyse the figures…' : 'Loading…') + '</p>');
        req.then(function (r) {
            var h = '';
            if (r.text) { h += '<div class="swl-ops-insight">' + md(r.text) + '<div class="swl-ops-small swl-ops-muted">AI analysis of the figures on this page' + (r.at ? ', ' + esc(r.at) : '') + '. Check before acting.</div></div>'; }
            h += '<h4 class="card-title mt-3">Observations</h4><ul class="swl-ops-list">' + (r.rules || []).map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ul>';
            if (r.note) { h += '<p class="swl-ops-muted mt-2">' + esc(r.note) + '</p>'; }
            $('#mv_ai').html(h);
        });
    }

    function draw() {
        var d = state.data, html = '';
        state.layout.order.forEach(function (k) {
            if (state.layout.hidden.indexOf(k) !== -1 || !W[k]) { return; }
            var w = W[k](d);
            if (!w) { return; }
            html += '<section class="sl-card w-' + w.size + '" aria-label="' + esc(state.widgets[k] || k) + '">' + w.html + '</section>';
        });
        $('#mv_widgets').html(html || '<p class="swl-ops-muted w-full">Every panel is hidden. Use Customise to show some.</p>');
        if (state.layout.order.indexOf('insights') !== -1 && state.layout.hidden.indexOf('insights') === -1) { insights(false); }
    }

    function load() {
        var p = params();
        $('#mv_widgets').attr('aria-busy', 'true');
        OPS.get(URL, $.extend({ action: 'data' }, p)).then(function (r) {
            state.data = r.data; state.widgets = r.widgets; state.layout = r.layout; state.settings = r.settings;
            if (r.currency) { CFG.currency = r.currency; }
            if (!state.branches.length && r.branches.length) {
                state.branches = r.branches;
                r.branches.forEach(function (b) { $('#mv_branch').append('<option value="' + b.id + '">' + esc(b.name) + (b.linked ? '' : ' (not linked)') + '</option>'); });
            }
            var f = r.data.filters;
            $('#mv_period').text(f.from + ' to ' + f.to + ' · compared with ' + f.prev_from + ' to ' + f.prev_to + (r.data.money ? '' : ' · money figures hidden for your account'));
            draw();
            if (!state.schedDone) { state.schedDone = true; schedules(); }
        }).always(function () { $('#mv_widgets').removeAttr('aria-busy'); });
    }

    $('#mv_range').on('change', function () {
        var custom = this.value === 'custom';
        $('#mv_from, #mv_to').prop('hidden', !custom);
        if (!custom) { load(); }
    });
    $('#mv_from, #mv_to, #mv_branch').on('change', function () { if ($('#mv_range').val() !== 'custom' || ($('#mv_from').val() && $('#mv_to').val())) { load(); } });
    $(document).on('click', '#mv_ai_refresh', function () { insights(true); });
    $(document).on('click', '.mv-export', function (ev) {
        ev.preventDefault();
        var p = params(), q = $.param($.extend({ action: 'export', format: $(this).data('format') }, p));
        var visible = state.layout.order.filter(function (k) { return state.layout.hidden.indexOf(k) === -1; });
        OPS.download(URL + '?' + q + '&' + visible.map(function (k) { return 'sections[]=' + encodeURIComponent(k); }).join('&'));
    });
    $(document).on('click', '.mv-ack', function () {
        var key = $(this).data('key'), note = window.prompt('What did you find? (optional)') ;
        if (note === null) { return; }
        OPS.post(URL + '?action=ack_alert', { action: 'ack_alert', key: key, note: note }).then(function (r) { if (OPS.result(r, 'Marked as checked')) { load(); } });
    });

    // ----------------------------------------------------------- customise
    function customList() {
        $('#mv_custom_list').html(state.layout.order.map(function (k, i) {
            var on = state.layout.hidden.indexOf(k) === -1;
            return '<li class="d-flex justify-content-between align-items-center flex-wrap" data-k="' + esc(k) + '"><span class="custom-control"><input type="checkbox" class="custom-control-input mv-show" id="mv_show_' + esc(k) + '"' + (on ? ' checked' : '') + '>'
                + '<label class="custom-control-label" for="mv_show_' + esc(k) + '">' + esc(state.widgets[k] || k) + '</label></span><span class="btn-group">'
                + '<button type="button" class="btn btn-sm btn-secondary mv-move" data-dir="-1"' + (i === 0 ? ' disabled' : '') + ' aria-label="Move ' + esc(state.widgets[k]) + ' up">Up</button>'
                + '<button type="button" class="btn btn-sm btn-secondary mv-move" data-dir="1"' + (i === state.layout.order.length - 1 ? ' disabled' : '') + ' aria-label="Move ' + esc(state.widgets[k]) + ' down">Down</button></span></li>';
        }).join(''));
    }
    $('#mv_customise').on('click', function () { var $p = $('#mv_custom_panel'); $p.prop('hidden', !$p.prop('hidden')); if (!$p.prop('hidden')) { customList(); } });
    $(document).on('change', '.mv-show', function () {
        var k = $(this).closest('li').data('k');
        state.layout.hidden = state.layout.hidden.filter(function (x) { return x !== k; });
        if (!this.checked) { state.layout.hidden.push(k); }
    });
    $(document).on('click', '.mv-move', function () {
        var k = $(this).closest('li').data('k'), o = state.layout.order, i = o.indexOf(k), j = i + Number($(this).data('dir'));
        if (j < 0 || j >= o.length) { return; }
        o[i] = o[j]; o[j] = k;
        customList();
        $('#mv_custom_list [data-k="' + k + '"] .mv-move[data-dir="' + $(this).data('dir') + '"]').trigger('focus');
    });
    $('#mv_custom_save').on('click', function () {
        OPS.post(URL + '?action=save_layout', { action: 'save_layout', order: state.layout.order, hidden: state.layout.hidden }).then(function (r) { if (OPS.result(r, 'Layout saved')) { draw(); } });
    });
    $('#mv_custom_reset').on('click', function () {
        state.layout = { order: Object.keys(state.widgets), hidden: [] };
        customList();
    });

    // ------------------------------------------------- schedules & settings
    function schedules() {
        if (!CFG.isAdmin) { return; }
        OPS.get(URL, { action: 'schedules' }).then(function (r) {
            var days = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            var h = '<h2 class="card-title">Scheduled reports</h2><p class="swl-ops-muted">Sent by e-mail with the report attached. Daily reports cover yesterday, weekly the last 7 days, monthly the last calendar month.</p>'
                + OPS.table([{ label: 'Report', render: function (s) { return '<strong>' + esc(s.name) + '</strong><div class="swl-ops-small swl-ops-muted">' + esc(s.recipients) + '</div>'; } },
                    { label: 'When', render: function (s) { return esc(s.frequency === 'weekly' ? 'Weekly, ' + days[s.weekday] : (s.frequency === 'monthly' ? 'Monthly, day ' + s.monthday : 'Daily')) + ' ' + String(s.hour).padStart(2, '0') + ':00 · ' + esc(String(s.format).toUpperCase()); } },
                    { label: 'Last sent', render: function (s) { return esc(s.last_run_at || 'never') + (s.last_status ? '<div class="swl-ops-small swl-ops-muted">' + esc(s.last_status) + '</div>' : '') + '<div class="swl-ops-small swl-ops-muted">next ' + esc(s.next_run_at || '') + '</div>'; } },
                    { label: '', render: function (s) { return '<span class="btn-group"><button type="button" class="btn btn-sm btn-secondary sc-run" data-id="' + s.id + '">Send now</button><button type="button" class="btn btn-sm btn-secondary sc-edit" data-row="' + esc(JSON.stringify(s)) + '">Edit</button><button type="button" class="btn btn-sm btn-outline-secondary sc-del" data-id="' + s.id + '">Delete</button></span>'; } }
                ], r.rows, 'No scheduled reports yet.');
            var wOpts = Object.keys(r.widgets).filter(function (k) { return k !== 'insights'; });
            h += '<hr><h3 class="card-title" id="sc_title">New scheduled report</h3><input type="hidden" id="sc_id" value="0"><div class="row">'
                + OPS.field('sc_name', 'Name', OPS.input('sc_name', 'Weekly management report', 'maxlength="120"'), 'col-md-6')
                + OPS.field('sc_recipients', 'Send to (e-mail addresses)', OPS.input('sc_recipients', '', 'placeholder="md@example.com, ops@example.com"'), 'col-md-6')
                + OPS.field('sc_frequency', 'How often', OPS.select('sc_frequency', [['daily', 'Daily'], ['weekly', 'Weekly'], ['monthly', 'Monthly']], 'weekly'), 'col-md-3')
                + OPS.field('sc_weekday', 'Day of week', OPS.select('sc_weekday', days.slice(1).map(function (d, i) { return [i + 1, d]; }), 1), 'col-md-3')
                + OPS.field('sc_monthday', 'Day of month', OPS.input('sc_monthday', 1, 'type="number" min="1" max="31"'), 'col-md-3')
                + OPS.field('sc_hour', 'Hour', OPS.input('sc_hour', 7, 'type="number" min="0" max="23"'), 'col-md-3')
                + OPS.field('sc_format', 'Format', OPS.select('sc_format', [['pdf', 'PDF'], ['xlsx', 'Excel'], ['csv', 'CSV']], 'pdf'), 'col-md-3')
                + OPS.field('sc_branch', 'Branch', OPS.select('sc_branch', [[0, 'All branches']].concat(state.branches.map(function (b) { return [b.id, b.name]; })), 0), 'col-md-3')
                + '</div><fieldset><legend>Sections</legend>' + wOpts.map(function (k) {
                    return '<span class="custom-control"><input type="checkbox" class="custom-control-input sc-sec" id="sc_s_' + k + '" value="' + k + '"' + (['executive', 'delivery', 'delayed', 'branches', 'alerts'].indexOf(k) !== -1 ? ' checked' : '') + '><label class="custom-control-label" for="sc_s_' + k + '">' + esc(r.widgets[k]) + '</label></span>';
                }).join('') + '</fieldset><button type="button" class="btn" id="sc_save">Save schedule</button>';
            $('#mv_schedules').html(h);
        });
    }
    $(document).on('click', '#sc_save', function () {
        OPS.post(URL + '?action=save_schedule', { action: 'save_schedule', id: $('#sc_id').val(), name: $('#sc_name').val(), recipients: $('#sc_recipients').val(), frequency: $('#sc_frequency').val(),
            weekday: $('#sc_weekday').val(), monthday: $('#sc_monthday').val(), hour: $('#sc_hour').val(), format: $('#sc_format').val(), branch_id: $('#sc_branch').val(),
            sections: $('.sc-sec:checked').map(function () { return this.value; }).get() }).then(function (r) { if (OPS.result(r, 'Saved, next run ' + (r.next_run_at || ''))) { schedules(); } });
    });
    $(document).on('click', '.sc-edit', function () {
        var s = $(this).data('row');
        $('#sc_title').text('Edit scheduled report'); $('#sc_id').val(s.id); $('#sc_name').val(s.name); $('#sc_recipients').val(s.recipients); $('#sc_frequency').val(s.frequency);
        $('#sc_weekday').val(s.weekday); $('#sc_monthday').val(s.monthday); $('#sc_hour').val(s.hour); $('#sc_format').val(s.format); $('#sc_branch').val(s.branch_id || 0);
        $('.sc-sec').each(function () { this.checked = String(s.sections).split(',').indexOf(this.value) !== -1; });
        document.getElementById('sc_name').focus();
    });
    $(document).on('click', '.sc-del', function () {
        var id = $(this).data('id');
        OPS.confirm('Delete this scheduled report?').then(function () { OPS.post(URL + '?action=delete_schedule', { action: 'delete_schedule', id: id }).then(function (r) { if (OPS.result(r, 'Deleted')) { schedules(); } }); });
    });
    $(document).on('click', '.sc-run', function () {
        var $b = $(this).prop('disabled', true);
        OPS.post(URL + '?action=run_schedule', { action: 'run_schedule', id: $b.data('id') }).then(function (r) { OPS.result(r, r.status || 'Sent'); schedules(); }).always(function () { $b.prop('disabled', false); });
    });

    function settings() {
        if (!CFG.isAdmin) { return; }
        OPS.get(URL, { action: 'settings' }).then(function (r) {
            var s = r.settings, f = function (k, l, attrs) { return OPS.field('ms_' + k, l, '<input id="ms_' + k + '" data-k="' + k + '" class="form-control ms" value="' + esc(s[k]) + '" ' + (attrs || '') + '>', 'col-md-6'); };
            $('#mv_settings').html('<h2 class="card-title">Targets and thresholds</h2><div class="row">' + f('sla_air_days', 'Air delivery target (days)', 'type="number" min="1"') + f('sla_sea_days', 'Sea delivery target (days)', 'type="number" min="1"')
                + f('sla_other_days', 'Other modes target (days)', 'type="number" min="1"') + f('stale_days', 'Pending after no movement for (days)', 'type="number" min="1"')
                + f('overhead_per_shipment', 'Overhead per shipment (cost analysis)', 'type="number" min="0" step="0.01"') + f('anomaly_z', 'Volume alert sensitivity (z-score)', 'type="number" min="1" step="0.1"')
                + f('off_hours_start', 'Out of hours from', 'type="time"') + f('off_hours_end', 'Out of hours until', 'type="time"') + f('login_fail_threshold', 'Failed sign-ins before an alert', 'type="number" min="1"')
                + '</div><button type="button" class="btn" id="ms_save">Save</button>');
        });
    }
    $(document).on('click', '#ms_save', function () {
        var d = { action: 'save_settings' };
        $('.ms').each(function () { d[$(this).data('k')] = $(this).val(); });
        OPS.post(URL + '?action=save_settings', d).then(function (r) { if (OPS.result(r, 'Saved')) { load(); } });
    });

    $(function () { load(); settings(); });
})(window.jQuery);
