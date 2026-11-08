"use strict";

// ============================================================================
// Developer dashboard. Polls ajax/devops/devops_ajax.php: the overview every
// 5 s while "Live" is on, the log tail every 3 s. Every value shown is one the
// server measured; when a source has nothing yet the page says so instead of
// drawing a made-up number. Charts are ApexCharts and follow the light/dark
// theme (dataJs/swl_theme.js).
// ============================================================================

(function ($) {
    var URL_ = 'ajax/devops/devops_ajax.php';
    var S = {
        tab: 'overview', live: true, tail: true, ov: null, range: '24h', series: null,
        metric: 'cpu', mRange: '24h', mSeries: null, logs: [], logSel: null, logNewest: null,
        deploy: null, alerts: [], alStatus: 'open', access: null, busy: {}
    };
    var charts = {}, timers = {};

    // ---------------------------------------------------------------- utils
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function ico(name, cls) { return '<iconify-icon icon="' + name + '" aria-hidden="true"' + (cls ? ' class="' + cls + '"' : '') + '></iconify-icon>'; }
    function n(v, d) { return v == null || isNaN(v) ? '–' : Number(v).toLocaleString(undefined, { maximumFractionDigits: d == null ? 1 : d }); }
    function bytes(b) {
        if (b == null || isNaN(b)) { return '–'; }
        var u = ['B', 'KB', 'MB', 'GB', 'TB'], i = 0; b = +b;
        while (b >= 1024 && i < 4) { b /= 1024; i++; }
        return (b >= 100 ? Math.round(b) : Math.round(b * 10) / 10) + ' ' + u[i];
    }
    function rate(b) { return b == null ? '–' : bytes(b) + '/s'; }
    function dur(s) {
        if (s == null) { return '–'; }
        s = Math.max(0, Math.round(s));
        if (s < 60) { return s + ' s'; }
        if (s < 3600) { return Math.floor(s / 60) + ' min'; }
        if (s < 86400) { return Math.floor(s / 3600) + ' h ' + Math.floor((s % 3600) / 60) + ' min'; }
        return Math.floor(s / 86400) + ' d ' + Math.floor((s % 86400) / 3600) + ' h';
    }
    function toDate(t) { return t ? new Date(String(t).replace(' ', 'T')) : null; }
    function ago(t) { var d = toDate(t); return d && !isNaN(d) ? dur((Date.now() - d) / 1000) + ' ago' : '–'; }
    function hms(t) { var d = toDate(t); return d && !isNaN(d) ? d.toTimeString().slice(0, 8) : ''; }
    function stamp(t) { var d = toDate(t); return d && !isNaN(d) ? d.toLocaleString(undefined, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : ''; }
    function label(st) { return { operational: 'Operational', degraded: 'Degraded', down: 'Down', unknown: 'Unknown', not_configured: 'Not set up', idle: 'Idle', passed: 'Passed', warning: 'Warning', failed: 'Failed', active: 'Active', acknowledged: 'Acknowledged', resolved: 'Resolved', critical: 'Critical' }[st] || st; }
    function state(st, text) { return '<span class="swl-dev-state is-' + esc(st) + '">' + esc(text || label(st)) + '</span>'; }
    function empty(msg, icon) { return '<div class="swl-dev-empty">' + ico(icon || 'solar:inbox-linear') + esc(msg) + '</div>'; }
    function get(data) { return $.ajax({ url: URL_, type: 'GET', data: data, dataType: 'json' }); }
    function post(data) { return $.ajax({ url: URL_, type: 'POST', data: data, dataType: 'json' }); }
    function fail(x) {
        var r = x && x.responseJSON;
        if (x && x.statusText === 'abort') { return; }
        toast(r && r.error ? r.error : 'The server did not answer. Check the connection.');
    }
    function toast(msg, icon) {
        if (window.Swal) { Swal.fire({ toast: true, position: 'top-end', icon: icon || 'error', title: msg, showConfirmButton: false, timer: 3600 }); }
        else { window.alert(msg); }
    }

    var SVC_ICON = {
        web: 'solar:monitor-smartphone-linear', api: 'solar:code-2-linear', db: 'solar:database-linear', storage: 'solar:folder-with-files-linear',
        worker: 'solar:clock-circle-linear', outbox: 'solar:inbox-out-linear', collector: 'solar:graph-up-linear', ai: 'solar:magic-stick-3-linear',
        ch_email: 'solar:letter-linear', ch_sms: 'solar:chat-square-linear', ch_whatsapp: 'solar:chat-round-dots-linear', payments: 'solar:card-linear'
    };
    function sIcon(k) { return SVC_ICON[k] || 'solar:server-linear'; }
    function svc(k) { return ((S.ov && S.ov.snapshot.services) || []).filter(function (s) { return s.key === k; })[0]; }

    // ---------------------------------------------------------------- charts
    function css(v) { return getComputedStyle(document.documentElement).getPropertyValue(v).trim() || '#888'; }
    function dark() { return document.documentElement.getAttribute('data-swl-theme') === 'dark'; }
    var PAL = { blue: '#3B82F6', teal: '#14B8A6', violet: '#8B5CF6', amber: '#F59E0B', red: '#F43F5E', green: '#22C55E', cyan: '#06B6D4' };

    function chart(key, el, opts) {
        var box = document.getElementById(el);
        if (!box) { return; }
        if (typeof window.ApexCharts === 'undefined') { box.innerHTML = empty('The chart library did not load.', 'solar:chart-square-linear'); return; }
        if (charts[key]) { charts[key].destroy(); delete charts[key]; }
        box.innerHTML = '';
        opts.chart = $.extend(true, { height: 240, toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', background: 'transparent', foreColor: css('--slate-600'), animations: { enabled: true, speed: 350 } }, opts.chart || {});
        opts.theme = { mode: dark() ? 'dark' : 'light' };
        opts.grid = $.extend(true, { borderColor: css('--line-200'), strokeDashArray: 3 }, opts.grid || {});
        opts.tooltip = $.extend(true, { theme: dark() ? 'dark' : 'light', x: { format: 'dd MMM HH:mm' } }, opts.tooltip || {});
        opts.dataLabels = opts.dataLabels || { enabled: false };
        charts[key] = new window.ApexCharts(box, opts);
        charts[key].render();
    }
    function pts(series, field, scale) {
        return (series || []).filter(function (r) { return r[field] != null; }).map(function (r) { return [toDate(r.t).getTime(), Math.round(r[field] * (scale || 1) * 100) / 100]; });
    }
    function spark(key, el, data, color) {
        if (!data.length) { $('#' + el).html('<div class="swl-dev-note">History builds up as samples are taken.</div>'); return; }
        chart(key, el, {
            chart: { type: 'area', height: 46, sparkline: { enabled: true }, animations: { enabled: false } },
            series: [{ name: 'Value', data: data }], colors: [color], stroke: { width: 2, curve: 'smooth' },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } }, tooltip: { enabled: true, shared: false, intersect: false }, xaxis: { type: 'datetime' }
        });
    }
    function areaOpts(series, names, colors, unit, height) {
        return {
            chart: { type: 'area', height: height || 240 },
            series: series.map(function (d, i) { return { name: names[i], data: d }; }),
            colors: colors, stroke: { width: 2, curve: 'smooth' },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.02 } },
            xaxis: { type: 'datetime', labels: { datetimeUTC: false }, axisBorder: { show: false } },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: function (v) { return unit === 'B/s' ? bytes(v) + '/s' : n(v, v < 10 ? 2 : 0) + (unit ? ' ' + unit : ''); } } },
            legend: { position: 'top', horizontalAlign: 'left' }, markers: { size: 0, hover: { size: 4 } },
            noData: { text: 'No samples yet in this period' }
        };
    }

    // ---------------------------------------------------------------- tabs
    function show(tab) {
        if (!$('#dv_p_' + tab).length) { tab = 'overview'; }
        S.tab = tab;
        $('#dv_tabs [data-tab]').each(function () {
            var on = $(this).data('tab') === tab;
            $(this).toggleClass('is-on', on).attr('aria-selected', on ? 'true' : 'false');
        });
        $('[data-pane]').prop('hidden', true);
        $('#dv_p_' + tab).prop('hidden', false);
        if (location.hash !== '#' + tab) { history.replaceState(null, '', '#' + tab); }
        load(tab);
        schedule();
    }

    function load(tab) {
        if (tab === 'overview' || tab === 'services' || tab === 'infrastructure') { loadOverview(); }
        if (tab === 'logs') { loadLogs(true); }
        if (tab === 'metrics') { loadMetric(); if (!S.ov) { loadOverview(); } }
        if (tab === 'deployments') { loadDeploys(); }
        if (tab === 'alerts') { loadAlerts(); }
        if (tab === 'access') { loadAccess(); }
        if (tab === 'users') { loadUsers(); }
        if (tab === 'data') { loadData(); }
        if (tab === 'ai') { loadDiagList(); }
        if (tab === 'infrastructure') { loadProcs(); }
        if (tab === 'logs' && !S.ov) { loadOverview(); }
    }

    function schedule() {
        Object.keys(timers).forEach(function (k) { clearInterval(timers[k]); });
        timers = {};
        if (!S.live) { return; }
        timers.ov = setInterval(function () { if (!document.hidden) { loadOverview(); } }, 5000);
        if (S.tab === 'logs' && S.tail) { timers.logs = setInterval(function () { if (!document.hidden) { loadLogs(false); } }, 3000); }
        if (S.tab === 'alerts') { timers.al = setInterval(function () { if (!document.hidden) { loadAlerts(); } }, 10000); }
        if (S.tab === 'metrics') { timers.m = setInterval(function () { if (!document.hidden) { loadMetric(); } }, 30000); }
        if (S.tab === 'users') { timers.u = setInterval(function () { if (!document.hidden) { loadUsers(true); } }, 15000); }
        if (S.tab === 'infrastructure') { timers.p = setInterval(function () { if (!document.hidden) { loadProcs(); } }, 20000); }
    }

    // ---------------------------------------------------------------- overview
    function loadOverview() {
        if (S.busy.ov) { return; }
        S.busy.ov = true;
        get({ action: 'overview' }).done(function (r) {
            if (!r || !r.ok) { return; }
            S.ov = r;
            if (S.range === '24h') { S.series = r.series; }
            drawHead(); drawKpis(); drawStatus(); drawArch('dv_arch_small'); drawOvAlerts(); drawOvLogs(); drawOvDeploys();
            if (S.tab === 'overview') { drawOvCharts(); }
            if (S.tab === 'services') { drawServices(); }
            if (S.tab === 'infrastructure') { drawInfra(); }
            if (S.tab === 'metrics') { drawMetricTiles(); }
        }).fail(fail).always(function () { S.busy.ov = false; });
    }

    function counts() {
        var s = (S.ov && S.ov.snapshot.services) || [], c = { total: s.length, operational: 0, degraded: 0, down: 0, other: 0 };
        s.forEach(function (x) { if (c[x.status] != null && x.status !== 'total') { c[x.status]++; } else { c.other++; } });
        return c;
    }

    function drawHead() {
        var c = counts(), a = (S.ov.alerts || []).filter(function (x) { return x.status !== 'resolved'; });
        var st = c.down ? 'down' : (c.degraded ? 'degraded' : 'operational');
        var txt = c.down ? c.down + ' service' + (c.down > 1 ? 's' : '') + ' down' : (c.degraded ? c.degraded + ' degraded' : 'All systems operational');
        $('#dv_head_state').html(state(st, txt));
        $('#dv_alert_n').text(a.length).prop('hidden', !a.length);
        $('#dv_updated').text('Updated ' + hms(S.ov.snapshot.at) + ' · refreshes every 5 s while Live is on');
    }

    function delta(cur, list, lowerBetter, unit) {
        var v = list.filter(function (x) { return x != null; });
        if (cur == null || v.length < 3) { return '<span>vs period average: not enough history</span>'; }
        var avg = v.reduce(function (a, b) { return a + b; }, 0) / v.length;
        var d = cur - avg, good = lowerBetter ? d <= 0 : d >= 0;
        return '<span class="' + (good ? 'up' : 'down') + '">' + ico(d >= 0 ? 'solar:arrow-right-up-linear' : 'solar:arrow-right-down-linear') + ' ' + n(Math.abs(d), 2) + (unit || '') + '</span><span>vs 24 h average</span>';
    }

    function drawKpis() {
        var o = S.ov, sn = o.snapshot, srv = sn.server, ser = S.ov.series || [];
        var thr = sn.activity.actions_5m != null ? sn.activity.actions_5m / 5 : null;
        var k = [
            { id: 'up', cls: 'is-green', icon: 'solar:shield-check-linear', l: 'Server uptime', v: o.availability != null ? n(o.availability, 2) + '%' : '–', small: 'last 30 days',
                s: '<span>Host up ' + dur(srv.uptime_s) + (o.availability == null ? ' · availability fills in as samples are taken' : '') + '</span>', data: pts(ser, 'ok', 100), color: PAL.green },
            { id: 'rt', cls: '', icon: 'solar:stopwatch-linear', l: 'Avg. response time', v: sn.web.ms != null ? n(sn.web.ms, 0) : '–', small: 'ms · web',
                s: delta(sn.web.ms, ser.map(function (r) { return r.web; }), true, ' ms'), data: pts(ser, 'web'), color: PAL.blue },
            { id: 'er', cls: 'is-violet', icon: 'solar:danger-triangle-linear', l: 'Error rate', v: n(sn.error_rate, 2) + '%', small: 'last 5 min',
                s: delta(sn.error_rate, ser.map(function (r) { return r.err; }), true, ' pts'), data: pts(ser, 'err'), color: PAL.violet },
            { id: 'tp', cls: 'is-amber', icon: 'solar:pulse-2-linear', l: 'Throughput', v: thr != null ? n(thr, 1) : '–', small: 'actions/min',
                s: '<span>' + (sn.db.qps != null ? n(sn.db.qps, 1) + ' DB queries/s' : 'DB query rate after the next refresh') + '</span>', data: pts(ser, 'apm'), color: PAL.amber }
        ];
        if (!$('#dv_k_up').length) {
            $('#dv_kpis').html(k.map(function (x) {
                return '<div class="swl-dev-card swl-dev-kpi ' + x.cls + '" id="dv_k_' + x.id + '"><div class="l">' + ico(x.icon) + esc(x.l) + '</div><div class="v"></div><div class="swl-dev-spark" id="dv_sp_' + x.id + '"></div><div class="s"></div></div>';
            }).join(''));
        }
        k.forEach(function (x) {
            var c = $('#dv_k_' + x.id);
            c.find('.v').html(esc(x.v) + '<small>' + esc(x.small) + '</small>');
            c.find('.s').html(x.s);
            spark('sp_' + x.id, 'dv_sp_' + x.id, x.data, x.color);
        });
    }

    function drawStatus() {
        var s = S.ov.snapshot.services;
        $('#dv_status').html(s.map(function (x) {
            var cls = x.status === 'operational' ? 'ok' : (x.status === 'degraded' ? 'warn' : (x.status === 'down' ? 'bad' : 'na'));
            var icon = x.status === 'operational' ? 'solar:check-circle-bold' : (x.status === 'down' ? 'solar:close-circle-bold' : (x.status === 'degraded' ? 'solar:danger-circle-bold' : 'solar:minus-circle-linear'));
            return '<li title="' + esc(x.detail) + '">' + ico(icon, cls) + '<span class="nm">' + esc(x.name) + '</span>' + state(x.status) + '<span class="ms">' + (x.latency_ms != null ? n(x.latency_ms, 0) + ' ms' : (x.throughput ? esc(x.throughput) : '')) + '</span></li>';
        }).join(''));
    }

    function node(k, name, sub, st, icon) {
        return '<div class="swl-dev-node is-' + esc(st) + '" title="' + esc(label(st)) + '">' + ico(icon) + '<div class="t"><b>' + esc(name) + '</b><small>' + esc(sub) + '</small></div><span class="dot" role="img" aria-label="' + esc(label(st)) + '"></span></div>';
    }
    function nodeFor(k) {
        var s = svc(k);
        return s ? node(k, s.name, s.tech || '', s.status, sIcon(k)) : '';
    }
    function drawArch(el) {
        if (!S.ov) { return; }
        var sn = S.ov.snapshot, web = svc('web') || { status: 'unknown' };
        var cols = [
            { nodes: [node('users', 'Customers & staff', 'Browsers · mobile', web.status === 'down' ? 'down' : 'operational', 'solar:users-group-rounded-linear')] },
            { nodes: [node('server', 'Web server', (sn.server.software || 'PHP') + ' · ' + sn.server.host, web.status, 'solar:server-linear')] },
            { cap: 'Application', nodes: ['web', 'api', 'worker', 'collector'].map(nodeFor) },
            { cap: 'Data', nodes: ['db', 'storage', 'outbox'].map(nodeFor) },
            { cap: 'External services', nodes: ['ai', 'ch_email', 'ch_sms', 'ch_whatsapp', 'payments'].map(nodeFor) }
        ];
        $('#' + el).html(cols.map(function (c) {
            return '<div class="swl-dev-arch-col' + (c.cap ? ' is-group' : '') + '">' + (c.cap ? '<span class="cap">' + esc(c.cap) + '</span>' : '') + c.nodes.join('') + '</div>';
        }).join(''));
    }

    function drawOvCharts() {
        var ser = S.series || [];
        chart('rt', 'dv_c_rt', areaOpts([pts(ser, 'web'), pts(ser, 'api'), pts(ser, 'db')], ['Web', 'API', 'Database'], [PAL.blue, PAL.cyan, PAL.teal], 'ms'));
        chart('err', 'dv_c_err', areaOpts([pts(ser, 'err')], ['Error rate'], [PAL.violet], '%'));
    }

    function drawOvAlerts() {
        var a = (S.ov.alerts || []).slice(0, 5);
        $('#dv_ov_alerts').html(a.length ? '<ul class="swl-dev-status-list">' + a.map(function (x) {
            var c = x.severity === 'critical' ? 'bad' : 'warn';
            return '<li>' + ico(x.severity === 'critical' ? 'solar:danger-triangle-bold' : 'solar:danger-circle-bold', c) + '<span class="nm">' + esc(x.title) + '<small class="d-block swl-dev-note">' + esc(x.service) + '</small></span><span class="swl-dev-badge is-' + esc(x.severity) + '">' + esc(label(x.severity)) + '</span><span class="ms">' + esc(ago(x.first_seen)) + '</span></li>';
        }).join('') + '</ul>' : empty('No active alerts. Everything checked is within its limits.', 'solar:check-circle-linear'));
    }

    function drawOvLogs() {
        var l = (S.ov.logs || []).slice(0, 14);
        $('#dv_ov_logs').html(l.length ? l.map(function (x) {
            return '<div class="ln is-' + esc(x.level) + '"><span class="ts">' + esc(hms(x.ts)) + '</span><span class="sv">' + esc(x.service) + '</span><span title="' + esc(x.message) + '">' + esc(x.message) + '</span></div>';
        }).join('') : '<div class="swl-dev-note">No log lines in the last hour.</div>');
    }

    function drawOvDeploys() {
        var d = S.ov.deploys || [];
        $('#dv_ov_deploys').html(d.length ? d.slice(0, 5).map(function (x) {
            return '<tr><td class="swl-dev-mono">' + esc(x.hash) + '</td><td class="wrap">' + esc(x.message) + '</td><td><span class="swl-dev-badge is-' + (x.kind === 'reset' ? 'warning' : 'success') + '">' + esc(x.kind) + '</span></td><td>' + esc(ago(x.ts)) + '</td></tr>';
        }).join('') : '<tr><td colspan="4">' + empty('No git history on this server. Deploys show here when the app runs from a git checkout.', 'solar:rocket-2-linear') + '</td></tr>');
    }

    // ---------------------------------------------------------------- services
    function drawServices() {
        var c = counts();
        var k = [['Total services', c.total, 'solar:server-square-linear', '', 'All checked on every refresh'], ['Healthy', c.operational, 'solar:check-circle-linear', 'is-green', c.total ? Math.round(c.operational / c.total * 100) + '%' : ''],
            ['Degraded', c.degraded, 'solar:danger-circle-linear', 'is-amber', c.total ? Math.round(c.degraded / c.total * 100) + '%' : ''], ['Down', c.down, 'solar:close-circle-linear', 'is-red', c.other ? c.other + ' not set up or idle' : '']];
        $('#dv_svc_kpis').html(k.map(function (x) { return '<div class="swl-dev-card swl-dev-kpi ' + x[3] + '"><div class="l">' + ico(x[2]) + esc(x[0]) + '</div><div class="v">' + x[1] + '</div><div class="s"><span>' + esc(x[4]) + '</span></div></div>'; }).join(''));
        var q = ($('#dv_svc_q').val() || '').toLowerCase(), ly = $('#dv_svc_layer').val(), stf = $('#dv_svc_status').val();
        var rows = S.ov.snapshot.services.filter(function (s) {
            return (!q || (s.name + ' ' + s.tech + ' ' + s.detail).toLowerCase().indexOf(q) > -1) && (!ly || s.layer === ly) && (!stf || s.status === stf);
        });
        $('#dv_svc_rows').html(rows.length ? rows.map(function (s) {
            return '<tr><td><div class="swl-dev-svc">' + ico(sIcon(s.key)) + '<div><b>' + esc(s.name) + '</b><small>' + esc(s.tech) + '</small></div></div></td><td>' + state(s.status) + '</td>'
                + '<td>' + (s.latency_ms != null ? n(s.latency_ms, 0) + ' ms' : '–') + '</td><td>' + (s.error_rate != null ? n(s.error_rate, 2) + '%' : '–') + '</td>'
                + '<td>' + esc(s.throughput || '–') + '</td><td>' + esc(s.instances || '–') + '</td><td class="wrap swl-dev-note">' + esc(s.detail) + '</td></tr>';
        }).join('') : '<tr><td colspan="7">' + empty('No services match.') + '</td></tr>');
        $('#dv_svc_foot').text('Showing ' + rows.length + ' of ' + S.ov.snapshot.services.length + ' services · checked ' + hms(S.ov.snapshot.at));
    }

    // ---------------------------------------------------------------- logs
    function logParams(since) {
        return { action: 'logs', source: $('#dv_log_src').val(), level: $('#dv_log_lvl').val(), q: $('#dv_log_q').val(), window: $('#dv_log_win').val(), since: since || '', limit: 300 };
    }
    function loadLogs(reset) {
        if (S.busy.logs) { return; }
        S.busy.logs = true;
        get(logParams(reset ? '' : S.logNewest)).done(function (r) {
            if (!r || !r.ok) { return; }
            if (reset) { S.logs = r.logs; }
            else if (r.logs.length) {
                var seen = {};
                S.logs.forEach(function (x) { seen[x.id] = 1; });
                S.logs = r.logs.filter(function (x) { return !seen[x.id]; }).concat(S.logs).slice(0, 500);
            }
            if (S.logs.length) { S.logNewest = S.logs[0].ts; }
            drawLogs();
        }).fail(fail).always(function () { S.busy.logs = false; });
    }
    function drawLogs() {
        $('#dv_log_n').text(S.logs.length.toLocaleString() + ' lines');
        drawLogHist();
        $('#dv_log_rows').html(S.logs.length ? S.logs.map(function (x, i) {
            return '<tr class="is-click' + (S.logSel === x.id ? ' is-sel' : '') + '" data-log="' + i + '" tabindex="0"><td class="swl-dev-mono">' + esc(stamp(x.ts)) + '</td><td>' + esc(x.service) + '</td><td><span class="swl-dev-badge is-' + esc(x.level) + '">' + esc(x.level) + '</span></td><td class="wrap">' + esc(x.message) + '</td></tr>';
        }).join('') : '<tr><td colspan="4">' + empty('No log lines match these filters in this time window.', 'solar:document-text-linear') + '</td></tr>');
    }
    function jsonHtml(obj) {
        return esc(JSON.stringify(obj, null, 2)).replace(/(&quot;[^&]*?&quot;)(\s*:)/g, '<span class="k">$1</span>$2')
            .replace(/:\s(&quot;.*?&quot;)/g, ': <span class="s">$1</span>').replace(/:\s(-?\d+(\.\d+)?)/g, ': <span class="n">$1</span>');
    }
    function logDetail(x) {
        S.logSel = x.id;
        var rec = $.extend({ timestamp: x.ts, service: x.service, level: x.level, source: x.source, message: x.message }, x.detail || {});
        $('#dv_log_detail').html('<div class="swl-dev-card-h"><iconify-icon icon="solar:document-text-linear" aria-hidden="true"></iconify-icon><h2>Log details</h2><div class="r"><button type="button" class="btn btn-secondary btn-sm" id="dv_log_copy">' + ico('solar:copy-linear') + ' Copy</button></div></div>'
            + '<dl class="swl-dev-kv"><dt>Service</dt><dd>' + esc(x.service) + '</dd><dt>Level</dt><dd><span class="swl-dev-badge is-' + esc(x.level) + '">' + esc(x.level) + '</span></dd><dt>Timestamp</dt><dd class="swl-dev-mono">' + esc(x.ts) + '</dd><dt>Source</dt><dd>' + esc(x.source) + '</dd><dt>Message</dt><dd>' + esc(x.message) + '</dd></dl>'
            + '<pre class="swl-dev-json" id="dv_log_json">' + jsonHtml(rec) + '</pre>');
        $('#dv_log_copy').on('click', function () {
            var t = JSON.stringify(rec, null, 2);
            if (navigator.clipboard) { navigator.clipboard.writeText(t).then(function () { toast('Copied', 'success'); }); }
        });
        drawLogs();
    }

    // ---------------------------------------------------------------- metrics
    var METRICS = [
        ['cpu', 'CPU usage', 'solar:cpu-linear', [['cpu', 'CPU']], '%', [PAL.green]],
        ['mem', 'Memory usage', 'solar:ssd-round-linear', [['mem', 'Memory']], '%', [PAL.blue]],
        ['disk', 'Disk usage', 'solar:folder-linear', [['disk', 'Disk']], '%', [PAL.amber]],
        ['net', 'Network I/O', 'solar:transfer-vertical-linear', [['rx', 'In'], ['tx', 'Out']], 'B/s', [PAL.cyan, PAL.violet]],
        ['dio', 'Disk I/O', 'solar:diskette-linear', [['dr', 'Read'], ['dw', 'Write']], 'B/s', [PAL.teal, PAL.amber]],
        ['web', 'Response time', 'solar:stopwatch-linear', [['web', 'Web'], ['api', 'API']], 'ms', [PAL.blue, PAL.cyan]],
        ['db', 'Database latency', 'solar:database-linear', [['db', 'Ping']], 'ms', [PAL.teal]],
        ['qps', 'Database queries', 'solar:server-linear', [['qps', 'Queries/s']], '/s', [PAL.violet]],
        ['apm', 'Request rate', 'solar:pulse-2-linear', [['apm', 'Actions/min']], '', [PAL.amber]],
        ['err', 'Error rate', 'solar:danger-triangle-linear', [['err', 'Failed actions']], '%', [PAL.red]]
    ];
    function metricDef(k) { return METRICS.filter(function (m) { return m[0] === k; })[0] || METRICS[0]; }
    function drawMetricNav() {
        $('#dv_metric_nav').html(METRICS.map(function (m) {
            return '<button type="button" data-metric="' + m[0] + '" class="' + (S.metric === m[0] ? 'is-on' : '') + '" aria-pressed="' + (S.metric === m[0]) + '">' + ico(m[2]) + esc(m[1]) + '</button>';
        }).join(''));
    }
    function loadMetric() {
        drawMetricNav();
        get({ action: 'series', range: S.mRange }).done(function (r) {
            if (!r || !r.ok) { return; }
            S.mSeries = r.series;
            drawMetric();
        }).fail(fail);
    }
    function drawMetric() {
        var m = metricDef(S.metric), ser = S.mSeries || [];
        $('#dv_metric_title').text(m[1]);
        var max = m[4] === '%' ? 100 : undefined;
        var o = areaOpts(m[3].map(function (f) { return pts(ser, f[0]); }), m[3].map(function (f) { return f[1]; }), m[5], m[4], 340);
        if (max) { o.yaxis.max = 100; }
        chart('metric', 'dv_c_metric', o);
        drawMetricTiles();
    }
    function drawMetricTiles() {
        if (!S.ov || !$('#dv_metric_tiles').is(':visible')) { return; }
        setTimeout(metricTileCharts, 0);
        var sn = S.ov.snapshot, srv = sn.server;
        $('#dv_metric_tiles').html(
            '<div class="swl-dev-card swl-dev-kpi"><div class="l">' + ico('solar:ssd-round-linear') + 'Memory usage</div><div class="v">' + n(srv.mem_pct, 1) + '%</div><div class="s"><span>' + bytes(srv.mem_used) + ' of ' + bytes(srv.mem_total) + '</span></div></div>'
            + '<div class="swl-dev-card swl-dev-kpi is-violet"><div class="l">' + ico('solar:transfer-vertical-linear') + 'Network I/O</div><div class="v">' + rate((srv.net_rx_bps || 0) + (srv.net_tx_bps || 0)) + '</div><div class="s"><span>In ' + rate(srv.net_rx_bps) + ' · out ' + rate(srv.net_tx_bps) + '</span></div></div>'
            + '<div class="swl-dev-card swl-dev-kpi is-green"><div class="l">' + ico('solar:diskette-linear') + 'Disk I/O</div><div class="v">' + rate((srv.disk_r_bps || 0) + (srv.disk_w_bps || 0)) + '</div><div class="s"><span>Read ' + rate(srv.disk_r_bps) + ' · write ' + rate(srv.disk_w_bps) + '</span></div></div>'
            + '<div class="swl-dev-card swl-dev-kpi is-amber"><div class="l">' + ico('solar:pulse-2-linear') + 'Request rate</div><div class="v">' + n(sn.activity.actions_1m, 0) + '<small>actions/min</small></div><div class="s"><span>' + n(sn.db.qps, 1) + ' DB queries/s</span></div></div>');
    }

    // ---------------------------------------------------------------- deployments
    function loadDeploys() {
        get({ action: 'deployments' }).done(function (r) {
            if (!r || !r.ok) { return; }
            S.deploy = r.deployments;
            drawDeploys();
        }).fail(fail);
    }
    function drawDeploys() {
        var d = S.deploy, st = d.stats;
        $('#dv_dep_kpis').html([
            ['Success rate', st.success_rate != null ? st.success_rate + '%' : '–', 'solar:check-circle-linear', 'is-green', 'Changes not rolled back, 30 days'],
            ['Deployments', st.total_30d, 'solar:rocket-2-linear', '', 'Code changes in 30 days'],
            ['Rollbacks', st.rollbacks_30d, 'solar:undo-left-linear', 'is-red', 'git reset on this server'],
            ['Last deploy', st.last_at ? ago(st.last_at) : '–', 'solar:history-linear', 'is-violet', st.last_at ? stamp(st.last_at) : 'No git history']
        ].map(function (x) { return '<div class="swl-dev-card swl-dev-kpi ' + x[3] + '"><div class="l">' + ico(x[2]) + esc(x[0]) + '</div><div class="v">' + esc(x[1]) + '</div><div class="s"><span>' + esc(x[4]) + '</span></div></div>'; }).join(''));
        $('#dv_dep_head').text(d.head ? 'Running ' + (d.head.branch || 'detached') + ' @ ' + String(d.head.hash || '').slice(0, 7) : 'This server has no git checkout');
        var PI = { passed: 'solar:check-read-linear', warning: 'solar:danger-circle-linear', failed: 'solar:close-circle-linear', unknown: 'solar:question-circle-linear' };
        $('#dv_pipe').html(d.pipeline.map(function (p) {
            return '<div class="st is-' + esc(p.state) + '"><span class="ic">' + ico(PI[p.state] || PI.unknown) + '</span><b>' + esc(p.name) + '</b>' + state(p.state) + '<small>' + esc(p.detail) + (p.at ? '<br>' + esc(stamp(p.at)) : '') + '</small></div>';
        }).join(''));
        deployCharts(d.history);
        $('#dv_dep_rows').html(d.history.length ? d.history.map(function (x) {
            return '<tr><td class="swl-dev-mono">' + esc(x.hash) + '</td><td><span class="swl-dev-badge is-' + (x.kind === 'reset' ? 'warning' : 'success') + '">' + esc(x.kind) + '</span></td><td class="wrap">' + esc(x.message) + '</td><td>' + esc(x.author) + '</td><td class="swl-dev-mono">' + esc(x.from) + '</td><td>' + esc(stamp(x.ts)) + '<div class="swl-dev-note">' + esc(ago(x.ts)) + '</div></td></tr>';
        }).join('') : '<tr><td colspan="6">' + empty('No git history on this server.', 'solar:rocket-2-linear') + '</td></tr>');
    }

    // ---------------------------------------------------------------- infrastructure
    function gauge(label, pct, sub, icon) {
        var cls = pct == null ? '' : (pct >= 90 ? 'is-bad' : (pct >= 75 ? 'is-warn' : ''));
        return '<div class="swl-dev-card swl-dev-kpi"><div class="l">' + ico(icon) + esc(label) + '</div><div class="v">' + (pct == null ? '–' : n(pct, 1) + '%') + '</div>'
            + '<div class="swl-dev-gauge"><div class="bar ' + cls + '" role="progressbar" aria-label="' + esc(label) + '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + (pct || 0) + '"><span style="width:' + Math.min(100, pct || 0) + '%"></span></div></div><div class="s"><span>' + esc(sub) + '</span></div></div>';
    }
    function drawInfra() {
        var sn = S.ov.snapshot, srv = sn.server, db = sn.db, php = srv.php;
        $('#dv_inf_kpis').html(
            gauge('CPU usage', srv.cpu_pct, srv.cores + ' cores · load ' + (srv.load ? srv.load.join(' / ') : '–'), 'solar:cpu-linear')
            + gauge('Memory usage', srv.mem_pct, bytes(srv.mem_used) + ' of ' + bytes(srv.mem_total), 'solar:ssd-round-linear')
            + gauge('Disk usage', srv.disk_pct, bytes(srv.disk_used) + ' of ' + bytes(srv.disk_total), 'solar:folder-linear')
            + gauge('DB connections', db.max_connections ? db.connections / db.max_connections * 100 : null, (db.connections != null ? db.connections : '–') + ' of ' + (db.max_connections || '–') + ' · ' + (db.running != null ? db.running : '–') + ' running', 'solar:database-linear'));
        drawArch('dv_arch');
        infraExtras();
        if (srv.mem_total) {
            chart('mem', 'dv_c_mem', {
                chart: { type: 'donut', height: 240 }, series: [Math.round(srv.mem_used / 1048576), Math.round((srv.mem_total - srv.mem_used) / 1048576)],
                labels: ['Used', 'Available'], colors: [PAL.blue, dark() ? '#23314A' : '#E1E5EA'], legend: { position: 'bottom' }, stroke: { width: 0 },
                plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Memory', formatter: function () { return n(srv.mem_pct, 0) + '%'; } } } } } },
                tooltip: { y: { formatter: function (v) { return bytes(v * 1048576); } } }
            });
        } else { $('#dv_c_mem').html(empty('This host does not expose memory figures.', 'solar:ssd-round-linear')); }
        var kv = function (rows) { return rows.map(function (r) { return '<dt>' + esc(r[0]) + '</dt><dd>' + r[1] + '</dd>'; }).join(''); };
        $('#dv_srv_kv').html(kv([['Host', esc(srv.host)], ['System', esc(srv.os + ' · ' + srv.arch)], ['Web server', esc(srv.software)], ['Up for', esc(dur(srv.uptime_s))],
            ['CPU', esc(srv.cores + ' cores · ' + (srv.cpu_pct != null ? srv.cpu_pct + '%' : '–'))], ['Swap', esc(srv.swap_total ? bytes(srv.swap_used) + ' of ' + bytes(srv.swap_total) : 'None')],
            ['Network', esc('In ' + rate(srv.net_rx_bps) + ' · out ' + rate(srv.net_tx_bps))]]));
        $('#dv_db_kv').html(kv([['Database', esc(db.version || '–')], ['Status', state(db.up ? 'operational' : 'down', db.up ? 'Up ' + dur(db.uptime_s) : 'Down')], ['Ping', esc(n(db.ping_ms, 2) + ' ms')],
            ['Size', esc(bytes(db.size_bytes) + ' · ' + (db.tables || 0) + ' tables')], ['Slow queries', esc(n(db.slow_queries, 0))], ['PHP', esc(php.version + ' (' + php.sapi + ')')],
            ['Memory limit', esc(php.memory_limit)], ['OPcache', php.opcache ? state('operational', 'On') : state('degraded', 'Off')],
            ['Extensions', esc(php.extensions.join(', ')) + (php.missing.length ? '<div class="swl-dev-note">Missing: ' + esc(php.missing.join(', ')) + '</div>' : '')]]));
    }

    // ---------------------------------------------------------------- alerts
    function loadAlerts() {
        get({ action: 'alerts', status: 'all' }).done(function (r) {
            if (!r || !r.ok) { return; }
            S.alerts = r.alerts;
            drawAlerts();
        }).fail(fail);
    }
    function drawAlerts() {
        var a = S.alerts, open = a.filter(function (x) { return x.status !== 'resolved'; });
        var day = Date.now() - 86400000;
        var res24 = a.filter(function (x) { return x.status === 'resolved' && toDate(x.resolved_at) >= day; }).length;
        $('#dv_al_kpis').html([
            ['Open alerts', open.length, 'solar:bell-bing-linear', '', 'Active and acknowledged'],
            ['Critical', open.filter(function (x) { return x.severity === 'critical'; }).length, 'solar:danger-triangle-linear', 'is-red', 'Needs action now'],
            ['Warning', open.filter(function (x) { return x.severity === 'warning'; }).length, 'solar:danger-circle-linear', 'is-amber', 'Watch these'],
            ['Resolved, 24 h', res24, 'solar:check-circle-linear', 'is-green', 'Closed by themselves or by hand']
        ].map(function (x) { return '<div class="swl-dev-card swl-dev-kpi ' + x[3] + '"><div class="l">' + ico(x[2]) + esc(x[0]) + '</div><div class="v">' + x[1] + '</div><div class="s"><span>' + esc(x[4]) + '</span></div></div>'; }).join(''));
        $('#dv_alert_n').text(open.length).prop('hidden', !open.length);
        alertCharts(a);
        var stf = S.alStatus, q = ($('#dv_al_q').val() || '').toLowerCase();
        var rows = a.filter(function (x) {
            return (stf === 'all' || (stf === 'open' ? x.status !== 'resolved' : x.status === 'resolved')) && (!q || (x.title + ' ' + x.service + ' ' + x.detail).toLowerCase().indexOf(q) > -1);
        });
        $('#dv_al_rows').html(rows.length ? rows.map(function (x) {
            var ctx = Object.keys(x.context || {}).map(function (k) { return esc(k.replace(/_/g, ' ')) + ': ' + esc(x.context[k]); }).join(' · ');
            var btns = x.status === 'resolved' ? '' : (x.status === 'active' ? '<button type="button" class="btn btn-secondary btn-sm" data-ack="' + x.id + '">' + ico('solar:hand-shake-linear') + ' Acknowledge</button> ' : '')
                + '<button type="button" class="btn btn-secondary btn-sm" data-resolve="' + x.id + '">' + ico('solar:check-circle-linear') + ' Resolve</button>';
            return '<tr><td class="wrap"><b>' + esc(x.title) + '</b><div class="swl-dev-note">' + esc(x.detail) + '</div></td><td>' + esc(x.service) + '</td><td>' + state(x.status) + '</td>'
                + '<td><span class="swl-dev-badge is-' + esc(x.severity) + '">' + esc(label(x.severity)) + '</span></td><td>' + esc(ago(x.first_seen)) + '<div class="swl-dev-note">' + esc(stamp(x.first_seen)) + '</div></td>'
                + '<td>' + esc(dur(x.duration_s)) + '</td><td class="wrap swl-dev-note">' + (ctx || '–') + '</td><td>' + btns + '</td></tr>';
        }).join('') : '<tr><td colspan="8">' + empty(stf === 'open' ? 'No open alerts. Every check is within its limits.' : 'No alerts match.', 'solar:check-circle-linear') + '</td></tr>');
    }

    // ---------------------------------------------------------------- access
    function loadAccess() { get({ action: 'access' }).done(function (r) { if (r && r.ok) { S.access = r; drawAccess(); } }).fail(fail); }
    function drawAccess() {
        var a = S.access;
        $('#dv_acc_rows').html(a.users.length ? a.users.map(function (u) {
            return '<tr><td><div class="swl-dev-svc">' + ico('solar:user-id-linear') + '<div><b>' + esc(u.name) + (u.id === a.me ? ' (you)' : '') + '</b><small>' + esc(u.username) + '</small></div></div></td><td>' + esc(u.email) + '</td>'
                + '<td>' + (u.assigned ? state('operational', 'Assigned') : state('not_configured', 'Not assigned')) + '</td><td>' + esc(u.granted_at ? stamp(u.granted_at) : '–') + '</td>'
                + '<td><button type="button" class="btn btn-secondary btn-sm" data-acc="' + u.id + '" data-on="' + (u.assigned ? 0 : 1) + '">' + ico(u.assigned ? 'solar:user-minus-linear' : 'solar:user-plus-linear') + (u.assigned ? ' Remove' : ' Assign') + '</button></td></tr>';
        }).join('') : '<tr><td colspan="5">' + empty('No super admins found.') + '</td></tr>');
    }


    // ---------------------------------------------------------------- extra charts
    function dayKeys(n) {
        var out = [], d = new Date();
        d.setHours(0, 0, 0, 0);
        for (var i = n - 1; i >= 0; i--) { var x = new Date(d); x.setDate(d.getDate() - i); out.push(x); }
        return out;
    }
    function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function dlabel(d) { return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' }); }
    function donut(key, el, labels, series, colors, total, height) {
        if (!series.reduce(function (a, b) { return a + b; }, 0)) { $('#' + el).html(empty('Nothing to show yet.', 'solar:pie-chart-2-linear')); return; }
        chart(key, el, {
            chart: { type: 'donut', height: height || 240 }, series: series, labels: labels, colors: colors, legend: { position: 'bottom' },
            stroke: { width: 2, colors: [css('--white')] },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: total || 'Total', formatter: function (w) { return w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0).toLocaleString(); } } } } } }
        });
    }
    function hbar(key, el, labels, data, color, name, height) {
        if (!data.length) { $('#' + el).html(empty('Nothing to show yet.', 'solar:chart-2-linear')); return; }
        chart(key, el, {
            chart: { type: 'bar', height: height || Math.max(180, data.length * 32 + 40) }, series: [{ name: name || 'Count', data: data }], colors: [color],
            plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '62%' } }, xaxis: { categories: labels }, dataLabels: { enabled: true, offsetX: 4, style: { fontSize: '11px' } }, grid: { show: false }
        });
    }

    function drawLogHist() {
        if (!S.logs.length) { $('#dv_c_loghist').html(''); return; }
        var b = {}, lv = ['INFO', 'WARN', 'ERROR'];
        var times = S.logs.map(function (x) { return toDate(x.ts).getTime(); });
        var span = Math.max.apply(null, times) - Math.min.apply(null, times), step = span > 86400000 ? 3600000 : (span > 7200000 ? 600000 : 60000);
        S.logs.forEach(function (x) { var k = Math.floor(toDate(x.ts).getTime() / step) * step; b[k] = b[k] || { INFO: 0, WARN: 0, ERROR: 0 }; b[k][x.level] = (b[k][x.level] || 0) + 1; });
        var keys = Object.keys(b).map(Number).sort();
        chart('loghist', 'dv_c_loghist', {
            chart: { type: 'bar', height: 150, stacked: true }, colors: [PAL.blue, PAL.amber, PAL.red],
            series: lv.map(function (l) { return { name: l, data: keys.map(function (k) { return [k, b[k][l]]; }) }; }),
            plotOptions: { bar: { columnWidth: '70%', borderRadius: 2 } }, xaxis: { type: 'datetime', labels: { datetimeUTC: false } },
            yaxis: { labels: { formatter: function (v) { return Math.round(v); } } }, legend: { position: 'top', horizontalAlign: 'left' }
        });
    }

    function metricTileCharts() {
        var ser = S.mSeries || S.series || [];
        if (!$('#dv_mt_mem').length) {
            $('#dv_metric_tiles').append('<div class="swl-dev-card" style="grid-column: 1 / -1"><div class="swl-dev-grid is-4">'
                + '<div><div class="swl-dev-note">Memory now</div><div id="dv_mt_mem"></div></div><div><div class="swl-dev-note">Network I/O</div><div id="dv_mt_net"></div></div>'
                + '<div><div class="swl-dev-note">Disk I/O</div><div id="dv_mt_dio"></div></div><div><div class="swl-dev-note">Request rate</div><div id="dv_mt_apm"></div></div></div></div>');
        }
        var m = S.ov ? S.ov.snapshot.server.mem_pct : null;
        chart('mt_mem', 'dv_mt_mem', { chart: { type: 'radialBar', height: 170 }, series: [m || 0], labels: ['Memory'], colors: [PAL.teal],
            plotOptions: { radialBar: { hollow: { size: '58%' }, track: { background: css('--fill-100') }, dataLabels: { name: { show: false }, value: { fontSize: '20px', offsetY: 6, formatter: function (v) { return Math.round(v) + '%'; } } } } } });
        chart('mt_net', 'dv_mt_net', areaOpts([pts(ser, 'rx'), pts(ser, 'tx')], ['In', 'Out'], [PAL.cyan, PAL.violet], 'B/s', 170));
        chart('mt_dio', 'dv_mt_dio', areaOpts([pts(ser, 'dr'), pts(ser, 'dw')], ['Read', 'Write'], [PAL.teal, PAL.amber], 'B/s', 170));
        chart('mt_apm', 'dv_mt_apm', areaOpts([pts(ser, 'apm')], ['Actions/min'], [PAL.amber], '', 170));
    }

    function infraExtras() {
        var c = counts();
        donut('health', 'dv_c_health', ['Healthy', 'Degraded', 'Down', 'Not set up / idle'], [c.operational, c.degraded, c.down, c.other], [PAL.green, PAL.amber, PAL.red, '#94A3B8'], 'Services');
        var ev = (S.ov.alerts || []).map(function (a) { return { t: a.first_seen, text: a.title, icon: a.severity === 'critical' ? 'solar:danger-triangle-bold' : 'solar:danger-circle-bold', cls: a.severity === 'critical' ? 'bad' : 'warn' }; })
            .concat((S.ov.deploys || []).map(function (d) { return { t: d.ts, text: 'Deploy ' + d.hash + ': ' + d.message, icon: 'solar:rocket-2-linear', cls: 'ok' }; }))
            .sort(function (a, b) { return toDate(b.t) - toDate(a.t); }).slice(0, 8);
        $('#dv_inf_events').html(ev.length ? ev.map(function (e) { return '<li>' + ico(e.icon, e.cls) + '<span class="nm" title="' + esc(e.text) + '">' + esc(e.text) + '</span><span></span><span class="ms">' + esc(ago(e.t)) + '</span></li>'; }).join('') : '<li>' + empty('No events yet.') + '</li>');
        var ser = S.series || [];
        ['cpu', 'mem', 'disk'].forEach(function (k, i) {
            var card = $('#dv_inf_kpis .swl-dev-kpi').eq(i);
            if (!card.find('.swl-dev-spark').length) { card.append('<div class="swl-dev-spark" id="dv_isp_' + k + '"></div>'); }
            spark('isp_' + k, 'dv_isp_' + k, pts(ser, k), [PAL.green, PAL.blue, PAL.amber][i]);
        });
    }
    function loadProcs() {
        get({ action: 'processes' }).done(function (r) {
            if (!r || !r.ok) { return; }
            $('#dv_proc_rows').html(r.processes.length ? r.processes.map(function (p) {
                return '<tr><td><b>' + esc(p.name) + '</b> <span class="swl-dev-note">' + esc(p.state) + '</span></td><td class="swl-dev-mono">' + p.pid + '</td><td>' + bytes(p.rss) + '</td><td>' + (p.cpu_avg == null ? '–' : n(p.cpu_avg, 1) + '%') + '</td></tr>';
            }).join('') : '<tr><td colspan="4">' + empty('This host does not list its processes to PHP.', 'solar:cpu-bolt-linear') + '</td></tr>');
        });
    }

    function deployCharts(h) {
        var days = dayKeys(30), by = {}, kinds = {};
        h.forEach(function (x) { var k = x.ts.slice(0, 10); by[k] = (by[k] || 0) + 1; kinds[x.kind] = (kinds[x.kind] || 0) + 1; });
        chart('deploys', 'dv_c_deploys', { chart: { type: 'bar', height: 240 }, series: [{ name: 'Changes', data: days.map(function (d) { return by[ymd(d)] || 0; }) }], colors: [PAL.blue],
            plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } }, xaxis: { categories: days.map(dlabel), tickAmount: 8, labels: { rotate: 0, hideOverlappingLabels: true } },
            yaxis: { labels: { formatter: function (v) { return Math.round(v); } } } });
        var kk = Object.keys(kinds);
        donut('depkinds', 'dv_c_depkinds', kk, kk.map(function (k) { return kinds[k]; }), [PAL.green, PAL.blue, PAL.violet, PAL.amber, PAL.red, PAL.cyan, PAL.teal], 'Changes');
    }

    function alertCharts(a) {
        var days = dayKeys(14), crit = {}, warn = {}, svc = {};
        a.forEach(function (x) { var k = String(x.first_seen).slice(0, 10); (x.severity === 'critical' ? crit : warn)[k] = ((x.severity === 'critical' ? crit : warn)[k] || 0) + 1; svc[x.service] = (svc[x.service] || 0) + 1; });
        chart('alday', 'dv_c_alday', { chart: { type: 'bar', height: 240, stacked: true }, colors: [PAL.red, PAL.amber],
            series: [{ name: 'Critical', data: days.map(function (d) { return crit[ymd(d)] || 0; }) }, { name: 'Warning', data: days.map(function (d) { return warn[ymd(d)] || 0; }) }],
            plotOptions: { bar: { borderRadius: 3, columnWidth: '55%' } }, xaxis: { categories: days.map(dlabel), labels: { rotate: 0, hideOverlappingLabels: true } },
            yaxis: { labels: { formatter: function (v) { return Math.round(v); } } }, legend: { position: 'top', horizontalAlign: 'left' } });
        var sk = Object.keys(svc).sort(function (x, y) { return svc[y] - svc[x]; }).slice(0, 8);
        hbar('alsvc', 'dv_c_alsvc', sk, sk.map(function (k) { return svc[k]; }), PAL.violet, 'Alerts');
    }

    // ---------------------------------------------------------------- users
    var U = { data: null, user: 0 };
    function uParams() { return { action: 'users', module: $('#dv_u_mod').val(), outcome: $('#dv_u_out').val(), q: $('#dv_u_q').val(), user_id: U.user || '' }; }
    function loadUsers(quiet) {
        if (S.busy.u) { return; }
        S.busy.u = true;
        get(uParams()).done(function (r) {
            if (!r || !r.ok) { return; }
            U.data = r.users;
            drawUsers(quiet);
        }).fail(fail).always(function () { S.busy.u = false; });
    }
    function drawUsers(quiet) {
        var u = U.data;
        if (!u.available) { $('#dv_p_users .swl-dev-grid, #dv_p_users .swl-dev-card').first().before(empty('The activity log is not available on this database.', 'solar:users-group-two-rounded-linear')); return; }
        var k = u.kpis || {};
        var tiles = [['Online now', k.now5, 'solar:user-check-linear', 'is-green', 'Active in the last 5 minutes'], ['People today', k.today_users, 'solar:users-group-two-rounded-linear', '', (k.week_users || 0) + ' in 7 days'],
            ['Actions today', k.today_actions, 'solar:pulse-2-linear', 'is-violet', (k.signins || 0) + ' sign-ins' + (k.signin_fail ? ' · ' + k.signin_fail + ' failed' : '')],
            ['Problems today', (k.today_failed || 0) + (k.today_denied || 0), 'solar:danger-triangle-linear', 'is-red', (k.today_failed || 0) + ' failed · ' + (k.today_denied || 0) + ' denied' + (k.view_as ? ' · ' + k.view_as + ' using View as' : '')]];
        $('#dv_u_kpis').html(tiles.map(function (x) { return '<div class="swl-dev-card swl-dev-kpi ' + x[3] + '"><div class="l">' + ico(x[2]) + esc(x[0]) + '</div><div class="v">' + n(x[1] || 0, 0) + '</div><div class="s"><span>' + esc(x[4]) + '</span></div></div>'; }).join(''));
        if ($('#dv_u_mod option').length <= 1) { $('#dv_u_mod').append((u.all_modules || []).map(function (m) { return '<option value="' + esc(m) + '">' + esc(m) + '</option>'; }).join('')); }
        if (!quiet || S.tab === 'users') {
            var hrs = Object.keys(u.hourly).sort(), mods = (u.modules || []).concat(['other']);
            var MC = [PAL.blue, PAL.teal, PAL.violet, PAL.amber, PAL.cyan, PAL.green, '#94A3B8'];
            chart('uhour', 'dv_c_uhour', { chart: { type: 'bar', height: 300, stacked: true }, colors: MC,
                series: mods.map(function (m) { return { name: m, data: hrs.map(function (h) { return [toDate(h).getTime(), u.hourly[h][m] || 0]; }) }; }).filter(function (sr) { return sr.data.some(function (d) { return d[1]; }); }),
                plotOptions: { bar: { columnWidth: '75%', borderRadius: 2 } }, xaxis: { type: 'datetime', labels: { datetimeUTC: false } }, legend: { position: 'top', horizontalAlign: 'left' },
                yaxis: { labels: { formatter: function (v) { return Math.round(v); } } }, noData: { text: 'No activity in 24 hours' } });
            donut('urole', 'dv_c_urole', u.roles.map(function (r) { return r.label; }), u.roles.map(function (r) { return r.n; }), MC, 'Actions', 300);
            var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], grid = {};
            u.heat.forEach(function (h) { grid[h.dw + '_' + h.h] = h.n; });
            chart('uheat', 'dv_c_uheat', { chart: { type: 'heatmap', height: 280 }, colors: [PAL.blue], dataLabels: { enabled: false },
                series: [2, 3, 4, 5, 6, 7, 1].reverse().map(function (dw) { return { name: days[dw - 1], data: Array.apply(null, Array(24)).map(function (_, h) { return { x: String(h).padStart(2, '0'), y: grid[dw + '_' + h] || 0 }; }) }; }),
                plotOptions: { heatmap: { radius: 3, enableShades: true, shadeIntensity: 0.6 } }, xaxis: { labels: { rotate: 0, hideOverlappingLabels: true } } });
            var dd = dayKeys(30), dm = {};
            u.daily.forEach(function (x) { dm[x.d] = x; });
            chart('uday', 'dv_c_uday', { chart: { type: 'line', height: 280 }, colors: [PAL.blue, PAL.green, PAL.red],
                series: [{ name: 'Actions', type: 'column', data: dd.map(function (d) { return (dm[ymd(d)] || {}).n || 0; }) },
                    { name: 'People', type: 'line', data: dd.map(function (d) { return (dm[ymd(d)] || {}).u || 0; }) },
                    { name: 'Failed or denied', type: 'line', data: dd.map(function (d) { var x = dm[ymd(d)] || {}; return (x.f || 0) + (x.dn || 0); }) }],
                stroke: { width: [0, 2.5, 2.5], curve: 'smooth' }, plotOptions: { bar: { columnWidth: '60%', borderRadius: 2 } }, labels: dd.map(dlabel),
                xaxis: { labels: { rotate: 0, hideOverlappingLabels: true } },
                yaxis: [{ title: { text: 'Actions' }, labels: { formatter: function (v) { return Math.round(v); } } }, { opposite: true, title: { text: 'People / problems' }, labels: { formatter: function (v) { return Math.round(v); } } }, { show: false }],
                legend: { position: 'top', horizontalAlign: 'left' } });
            hbar('utop', 'dv_c_utop', u.top.slice(0, 8).map(function (t) { return t.name || ('#' + t.id); }), u.top.slice(0, 8).map(function (t) { return t.n; }), PAL.teal, 'Actions', 300);
            var OC = { success: PAL.green, failure: PAL.red, denied: PAL.amber };
            donut('uout', 'dv_c_uout', u.outcomes.map(function (o) { return o.label; }), u.outcomes.map(function (o) { return o.n; }), u.outcomes.map(function (o) { return OC[o.label] || PAL.violet; }), 'Actions', 300);
            $('#dv_u_failing').html(u.failing.length ? u.failing.map(function (f) { return '<li>' + ico('solar:danger-circle-bold', 'warn') + '<span class="nm" title="' + esc(f.label) + '">' + esc(f.label) + '<small class="d-block swl-dev-note">' + esc(f.module) + ' · last ' + esc(ago(f.last_at)) + '</small></span><span></span><span class="ms">' + n(f.n, 0) + '×</span></li>'; }).join('') : '<li>' + empty('Nothing failed in 7 days.', 'solar:check-circle-linear') + '</li>');
            $('#dv_u_ips').html(u.ips.length ? u.ips.map(function (x) { return '<li>' + ico('solar:global-linear', x.f ? 'warn' : 'ok') + '<span class="nm swl-dev-mono">' + esc(x.ip) + '</span><span class="swl-dev-note">' + x.users + ' people</span><span class="ms">' + n(x.n, 0) + (x.f ? ' · ' + x.f + ' failed' : '') + '</span></li>'; }).join('') : '<li>' + empty('No requests recorded.') + '</li>');
        }
        $('#dv_u_online').html(u.online.length ? u.online.map(function (o) {
            return '<tr><td><button type="button" class="swl-dev-linkbtn" data-uid="' + o.id + '">' + esc(o.name || ('#' + o.id)) + '</button></td><td>' + esc(o.role) + '</td><td class="wrap">' + esc(o.doing) + '</td><td class="swl-dev-mono">' + esc(o.endpoint) + '</td><td>' + esc(o.agent) + '</td><td class="swl-dev-mono">' + esc(o.ip) + '</td><td>' + o.n + '</td><td>' + esc(ago(o.last_at)) + '</td></tr>';
        }).join('') : '<tr><td colspan="8">' + empty('Nobody has done anything in the last 15 minutes.', 'solar:user-check-linear') + '</td></tr>');
        drawFeed(u.feed);
    }
    function drawFeed(f) {
        $('#dv_u_feed_n').text(f.length + ' most recent' + (U.user ? ' for this person' : ''));
        var OB = { success: 'success', denied: 'warning', failure: 'critical' };
        $('#dv_u_feed').html(f.length ? f.map(function (x) {
            return '<tr><td>' + esc(stamp(x.at)) + '</td><td><button type="button" class="swl-dev-linkbtn" data-uid="' + x.user_id + '">' + esc(x.name || ('#' + x.user_id)) + '</button>' + (x.view_as ? ' <span class="swl-dev-badge is-warning" title="Done while viewing as this person">View as</span>' : '') + '<div class="swl-dev-note">' + esc(x.role) + '</div></td>'
                + '<td>' + esc(x.module) + '</td><td class="wrap">' + esc(x.summary || x.action) + '</td><td><span class="swl-dev-badge is-' + (OB[x.outcome] || 'info') + '">' + esc(x.outcome) + '</span></td>'
                + '<td class="wrap swl-dev-note">' + esc(x.entity) + '</td><td class="swl-dev-mono">' + esc(x.request) + '</td><td class="swl-dev-mono">' + esc(x.ip) + '</td></tr>';
        }).join('') : '<tr><td colspan="8">' + empty('No activity matches.') + '</td></tr>');
    }
    function openUser(id) {
        U.user = id;
        $('#dv_u_clear').prop('hidden', false);
        get({ action: 'user', id: id }).done(function (r) {
            if (!r || !r.ok) { return; }
            var d = r.detail, u = d.user;
            $('#dv_u_detail').html('<div class="swl-dev-user"><div class="swl-dev-card-h">' + ico('solar:user-id-linear') + '<h2>' + esc(u.name) + '</h2><div class="r swl-dev-note">' + esc(u.username || '') + (u.last_login ? ' · last sign-in ' + esc(stamp(u.last_login)) : '') + '</div></div>'
                + '<div class="swl-dev-grid is-3"><div id="dv_c_udaily" class="swl-dev-chart" style="min-height:180px"></div><div id="dv_c_umods" class="swl-dev-chart" style="min-height:180px"></div>'
                + '<ul class="swl-dev-status-list">' + d.ips.map(function (x) { return '<li>' + ico('solar:global-linear', 'ok') + '<span class="nm swl-dev-mono">' + esc(x.ip) + '</span><span class="swl-dev-note">' + x.n + ' actions</span><span class="ms">' + esc(ago(x.last_at)) + '</span></li>'; }).join('') + '</ul></div></div>');
            var dd = dayKeys(14), dm = {};
            d.daily.forEach(function (x) { dm[x.d] = x; });
            chart('udaily', 'dv_c_udaily', { chart: { type: 'bar', height: 180, stacked: true }, colors: [PAL.blue, PAL.red],
                series: [{ name: 'Succeeded', data: dd.map(function (x) { var r2 = dm[ymd(x)] || {}; return (r2.n || 0) - (r2.f || 0); }) }, { name: 'Problems', data: dd.map(function (x) { return (dm[ymd(x)] || {}).f || 0; }) }],
                xaxis: { categories: dd.map(dlabel), labels: { rotate: 0, hideOverlappingLabels: true } }, plotOptions: { bar: { columnWidth: '60%', borderRadius: 2 } }, legend: { show: false } });
            donut('umods', 'dv_c_umods', d.modules.map(function (m) { return m.label; }), d.modules.map(function (m) { return m.n; }), [PAL.blue, PAL.teal, PAL.violet, PAL.amber, PAL.cyan, PAL.green, PAL.red, '#94A3B8'], 'Actions', 200);
            drawFeed(d.feed);
            document.getElementById('dv_u_detail').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }).fail(fail);
    }

    // ---------------------------------------------------------------- system data
    function loadData() {
        get({ action: 'data' }).done(function (r) {
            if (!r || !r.ok) { return; }
            var d = r.data, A = d.areas;
            var ic = { shipments: 'solar:box-linear', pickups: 'solar:delivery-linear', packages: 'solar:bag-4-linear', consolidations: 'solar:layers-linear', users: 'solar:users-group-rounded-linear', tickets: 'solar:ticket-linear', messages: 'solar:letter-linear' };
            $('#dv_d_kpis').html(A.length ? A.slice(0, 8).map(function (a, i) {
                var ch = a.prev7 ? Math.round((a.last7 - a.prev7) / a.prev7 * 100) : null;
                return '<div class="swl-dev-card swl-dev-kpi ' + ['', 'is-green', 'is-violet', 'is-amber'][i % 4] + '"><div class="l">' + ico(ic[a.key] || 'solar:database-linear') + esc(a.label) + '</div><div class="v">' + n(a.total, 0) + '<small>total</small></div>'
                    + '<div class="s"><span>' + n(a.last7, 0) + ' this week</span>' + (ch == null ? '' : '<span class="' + (ch >= 0 ? 'up' : 'down') + '">' + (ch >= 0 ? '+' : '') + ch + '%</span>') + '</div><div class="swl-dev-spark" id="dv_dsp_' + a.key + '"></div></div>';
            }).join('') : empty('No business tables found on this database.', 'solar:database-linear'));
            A.slice(0, 8).forEach(function (a, i) { spark('dsp_' + a.key, 'dv_dsp_' + a.key, a.series.map(function (x) { return [toDate(x.d).getTime(), x.n]; }), [PAL.blue, PAL.green, PAL.violet, PAL.amber][i % 4]); });
            var days = (A[0] ? A[0].series : []).map(function (x) { return dlabel(toDate(x.d)); });
            chart('dareas', 'dv_c_dareas', { chart: { type: 'bar', height: 320, stacked: true }, colors: [PAL.blue, PAL.teal, PAL.violet, PAL.amber, PAL.cyan, PAL.green, PAL.red],
                series: A.map(function (a) { return { name: a.label, data: a.series.map(function (x) { return x.n; }) }; }), xaxis: { categories: days, labels: { rotate: 0, hideOverlappingLabels: true } },
                plotOptions: { bar: { columnWidth: '65%', borderRadius: 2 } }, legend: { position: 'top', horizontalAlign: 'left' }, yaxis: { labels: { formatter: function (v) { return Math.round(v); } } } });
            chart('dmsg', 'dv_c_dmsg', { chart: { type: 'bar', height: 260, stacked: true }, colors: [PAL.green, PAL.red, PAL.amber],
                series: [{ name: 'Sent', data: d.channels.map(function (c) { return c.sent; }) }, { name: 'Failed', data: d.channels.map(function (c) { return c.failed; }) }, { name: 'Skipped', data: d.channels.map(function (c) { return c.skipped; }) }],
                xaxis: { categories: d.channels.map(function (c) { return c.channel; }) }, plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: '60%' } }, legend: { position: 'top', horizontalAlign: 'left' },
                noData: { text: 'No messages in 30 days' } });
            chart('dai', 'dv_c_dai', { chart: { type: 'line', height: 260 }, colors: [PAL.violet, PAL.amber],
                series: [{ name: 'Calls', type: 'column', data: d.ai.map(function (x) { return [toDate(x.d).getTime(), x.calls]; }) }, { name: 'Tokens', type: 'line', data: d.ai.map(function (x) { return [toDate(x.d).getTime(), x.tokens]; }) }],
                stroke: { width: [0, 2.5] }, xaxis: { type: 'datetime', labels: { datetimeUTC: false } }, yaxis: [{ labels: { formatter: function (v) { return Math.round(v); } } }, { opposite: true, labels: { formatter: function (v) { return n(v, 0); } } }],
                legend: { position: 'top', horizontalAlign: 'left' }, noData: { text: 'No AI calls in 30 days' } });
            var t = d.tables.slice(0, 8);
            donut('dsize', 'dv_c_dsize', t.map(function (x) { return x.name; }), t.map(function (x) { return Math.round((x.data + x.index) / 1024); }), [PAL.blue, PAL.teal, PAL.violet, PAL.amber, PAL.cyan, PAL.green, PAL.red, '#94A3B8'], 'KB', 260);
            $('#dv_d_tables').html(d.tables.length ? d.tables.map(function (x) {
                return '<tr><td class="swl-dev-mono">' + esc(x.name) + '</td><td>' + n(x.rows, 0) + '</td><td>' + bytes(x.data) + '</td><td>' + bytes(x.index) + '</td><td>' + esc(x.engine) + '</td><td>' + esc(x.updated ? ago(x.updated) : '–') + '</td></tr>';
            }).join('') : '<tr><td colspan="6">' + empty('No tables listed.') + '</td></tr>');
        }).fail(fail);
    }

    // ---------------------------------------------------------------- AI diagnostics
    function mdHtml(src) {
        var lines = esc(String(src || '')).replace(/\r\n?/g, '\n').split('\n'), out = [], i = 0, para = [];
        var inline = function (t) { return t.replace(/`([^`]+)`/g, '<code>$1</code>').replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>'); };
        var flush = function () { if (para.length) { out.push('<p>' + inline(para.join(' ')) + '</p>'); para = []; } };
        while (i < lines.length) {
            var l = lines[i];
            if (/^```/.test(l)) { flush(); var code = []; i++; while (i < lines.length && !/^```/.test(lines[i])) { code.push(lines[i]); i++; } i++; out.push('<pre><code>' + code.join('\n') + '</code></pre>'); continue; }
            var h = /^(#{1,4})\s+(.*)$/.exec(l);
            if (h) { flush(); var lv = Math.min(3, h[1].length); out.push('<h' + lv + '>' + inline(h[2]) + '</h' + lv + '>'); i++; continue; }
            if (/^\s*(-{3,}|\*{3,})\s*$/.test(l)) { flush(); out.push('<hr>'); i++; continue; }
            if (/^&gt;\s?/.test(l)) { flush(); var q = []; while (i < lines.length && /^&gt;\s?/.test(lines[i])) { q.push(lines[i].replace(/^&gt;\s?/, '')); i++; } out.push('<blockquote>' + inline(q.join(' ')) + '</blockquote>'); continue; }
            if (/^\s*([-*+]|\d+[.)])\s+/.test(l)) {
                flush(); var ord = /^\s*\d+[.)]/.test(l), items = [];
                while (i < lines.length && /^\s*([-*+]|\d+[.)])\s+/.test(lines[i])) { items.push('<li>' + inline(lines[i].replace(/^\s*([-*+]|\d+[.)])\s+/, '')) + '</li>'); i++; }
                out.push(ord ? '<ol>' + items.join('') + '</ol>' : '<ul>' + items.join('') + '</ul>'); continue;
            }
            if (/^\s*$/.test(l)) { flush(); i++; continue; }
            para.push(l.trim()); i++;
        }
        flush();
        return out.join('');
    }
    function showReport(id, title, md) {
        $('#dv_ai_out_card').prop('hidden', false);
        $('#dv_ai_title').text(title);
        $('#dv_ai_out').html(mdHtml(md));
        $('#dv_ai_dl').attr('href', URL_ + '?action=diag_download&id=' + id);
        $('#dv_ai_dle').attr('href', URL_ + '?action=diag_download&what=evidence&id=' + id);
    }
    function drawDiagList(list) {
        $('#dv_ai_list').html(list.length ? list.map(function (r) {
            return '<li>' + ico(r.status === 'analysed' ? 'solar:magic-stick-3-bold' : 'solar:document-text-linear', r.status === 'analysed' ? 'ok' : 'na')
                + '<span class="nm"><button type="button" class="swl-dev-linkbtn" data-rep="' + r.id + '">' + esc(r.title) + '</button><small class="d-block swl-dev-note">' + esc(r.by) + ' · ' + esc(stamp(r.created_at)) + ' · ' + r.findings + ' findings</small></span>'
                + '<a class="btn btn-secondary btn-sm" href="' + URL_ + '?action=diag_download&id=' + r.id + '" aria-label="Download ' + esc(r.title) + '">' + ico('solar:download-linear') + '</a>'
                + '<button type="button" class="btn btn-secondary btn-sm" data-repdel="' + r.id + '" aria-label="Delete ' + esc(r.title) + '">' + ico('solar:trash-bin-trash-linear') + '</button></li>';
        }).join('') : '<li>' + empty('No reports yet. Generate one on the left.', 'solar:folder-with-files-linear') + '</li>');
    }
    function loadDiagList() {
        get({ action: 'diag_list' }).done(function (r) {
            if (!r || !r.ok) { return; }
            var a = r.ai_ready || {};
            $('#dv_ai_state').html(a.ready ? state('operational', 'AI: ' + (a.provider || '') + ' ' + (a.model || '')) : state('not_configured', 'AI not set up: evidence and automatic findings only'));
            drawDiagList(r.reports);
        }).fail(fail);
    }

    // ---------------------------------------------------------------- wiring
    $(function () {
        var h = (location.hash || '').replace('#', '');
        $('#dv_tabs').on('click', '[data-tab]', function () { show($(this).data('tab')); });
        $('#dv_tabs').on('keydown', '[data-tab]', function (e) {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') { return; }
            var b = $('#dv_tabs [data-tab]'), i = b.index(this) + (e.key === 'ArrowRight' ? 1 : -1);
            b.eq((i + b.length) % b.length).trigger('focus').trigger('click');
        });
        $(document).on('click', '[data-go]', function () { show($(this).data('go')); });
        $('#dv_live').on('click', function () {
            S.live = !S.live;
            $(this).toggleClass('is-on', S.live).attr('aria-pressed', S.live ? 'true' : 'false').text(S.live ? 'Live' : 'Paused');
            schedule();
        });
        $('#dv_refresh').on('click', function () { load(S.tab); });
        $('#dv_rt_range').on('change', function () {
            S.range = this.value;
            get({ action: 'series', range: S.range }).done(function (r) { if (r && r.ok) { S.series = r.series; drawOvCharts(); } }).fail(fail);
        });
        $('#dv_svc_q, #dv_svc_layer, #dv_svc_status').on('input change', function () { if (S.ov) { drawServices(); } });
        var lt = null;
        $('#dv_log_src, #dv_log_lvl, #dv_log_win').on('change', function () { loadLogs(true); });
        $('#dv_log_q').on('input', function () { clearTimeout(lt); lt = setTimeout(function () { loadLogs(true); }, 300); });
        $('#dv_tail').on('click', function () {
            S.tail = !S.tail;
            $(this).toggleClass('is-on', S.tail).attr('aria-pressed', S.tail ? 'true' : 'false');
            schedule();
        });
        $('#dv_log_rows').on('click keydown', 'tr[data-log]', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter') { return; }
            logDetail(S.logs[+$(this).data('log')]);
        });
        $('#dv_metric_nav').on('click', '[data-metric]', function () { S.metric = $(this).data('metric'); drawMetricNav(); drawMetric(); });
        $('#dv_m_range').on('change', function () { S.mRange = this.value; loadMetric(); });
        $('#dv_al_status').on('change', function () { S.alStatus = this.value; drawAlerts(); });
        $('#dv_al_q').on('input', drawAlerts);
        $('#dv_al_rows').on('click', '[data-ack], [data-resolve]', function () {
            var ack = $(this).is('[data-ack]');
            post({ action: ack ? 'alert_ack' : 'alert_resolve', id: $(this).data(ack ? 'ack' : 'resolve') }).done(function (r) {
                if (r && r.ok) { S.alerts = r.alerts; drawAlerts(); toast(ack ? 'Acknowledged' : 'Resolved', 'success'); }
            }).fail(fail);
        });
        $('#dv_acc_rows').on('click', '[data-acc]', function () {
            post({ action: 'access_set', user_id: $(this).data('acc'), on: $(this).data('on') }).done(function (r) {
                if (r && r.ok) { S.access = r; drawAccess(); toast('Saved', 'success'); }
            }).fail(fail);
        });
        var ut = null;
        $('#dv_u_mod, #dv_u_out').on('change', function () { loadUsers(true); });
        $('#dv_u_q').on('input', function () { clearTimeout(ut); ut = setTimeout(function () { loadUsers(true); }, 300); });
        $(document).on('click', '[data-uid]', function () { if (S.tab !== 'users') { show('users'); } openUser(+$(this).data('uid')); });
        $('#dv_u_clear').on('click', function () { U.user = 0; $(this).prop('hidden', true); $('#dv_u_detail').empty(); loadUsers(true); });
        $('#dv_ai_form').on('submit', function (e) {
            e.preventDefault();
            var scope = $('#dv_ai_form input[name="scope"]:checked').map(function () { return this.value; }).get();
            if (!scope.length) { toast('Pick at least one kind of evidence.'); return; }
            var btn = $('#dv_ai_go').prop('disabled', true);
            $('#dv_ai_out_card').prop('hidden', false);
            $('#dv_ai_title').text('Working…');
            $('#dv_ai_out').html('<span class="swl-dev-busy">Collecting evidence, reading code and asking the AI. This can take up to a minute.</span>');
            post({ action: 'diag_run', question: $('#dv_ai_q').val(), scope: scope, window: $('#dv_ai_win').val(), paths: $('#dv_ai_paths').val() }).done(function (r) {
                if (!r || !r.ok) { toast(r && r.error ? r.error : 'The report could not be made.'); return; }
                showReport(r.id, r.ai_ok ? 'Diagnosis' : 'Diagnosis (evidence only)', r.markdown);
                drawDiagList(r.reports);
                if (!r.saved) { toast('Shown here, but the file could not be saved: make dev_reports/ writable.', 'warning'); }
            }).fail(function (x) { fail(x); $('#dv_ai_out').html(empty('The report could not be made.')); }).always(function () { btn.prop('disabled', false); });
        });
        $('#dv_ai_list').on('click', '[data-rep]', function () {
            var id = $(this).data('rep');
            get({ action: 'diag_get', id: id }).done(function (r) { if (r && r.ok) { showReport(r.id, r.title, r.markdown); document.getElementById('dv_ai_out_card').scrollIntoView({ behavior: 'smooth' }); } }).fail(fail);
        });
        $('#dv_ai_list').on('click', '[data-repdel]', function () {
            var id = $(this).data('repdel');
            var go = function () { post({ action: 'diag_delete', id: id }).done(function (r) { if (r && r.ok) { drawDiagList(r.reports); } }).fail(fail); };
            if (window.Swal) { Swal.fire({ title: 'Delete this report?', text: 'The report and its evidence file are removed.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete' }).then(function (x) { if (x.isConfirmed) { go(); } }); }
            else if (window.confirm('Delete this report?')) { go(); }
        });
        $(document).on('swl:theme', function () {
            if (S.tab === 'users' && U.data) { drawUsers(); }
            if (S.tab === 'data') { loadData(); }
            if (S.tab === 'deployments' && S.deploy) { drawDeploys(); }
            if (S.tab === 'alerts') { drawAlerts(); }
            if (S.tab === 'logs') { drawLogHist(); }
            if (S.ov) { drawKpis(); }
            if (S.tab === 'overview') { drawOvCharts(); }
            if (S.tab === 'metrics') { drawMetric(); }
            if (S.tab === 'infrastructure' && S.ov) { drawInfra(); }
        });
        show(h || 'overview');
        $(window).on('hashchange', function () { var t = (location.hash || '').replace('#', ''); if (t && t !== S.tab) { show(t); } });
    });
})(window.jQuery);
