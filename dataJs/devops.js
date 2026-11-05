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
            series: [{ name: '', data: data }], colors: [color], stroke: { width: 2, curve: 'smooth' },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } }, tooltip: { enabled: false }, xaxis: { type: 'datetime' }
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
    }

    function schedule() {
        Object.keys(timers).forEach(function (k) { clearInterval(timers[k]); });
        timers = {};
        if (!S.live) { return; }
        timers.ov = setInterval(function () { if (!document.hidden) { loadOverview(); } }, 5000);
        if (S.tab === 'logs' && S.tail) { timers.logs = setInterval(function () { if (!document.hidden) { loadLogs(false); } }, 3000); }
        if (S.tab === 'alerts') { timers.al = setInterval(function () { if (!document.hidden) { loadAlerts(); } }, 10000); }
        if (S.tab === 'metrics') { timers.m = setInterval(function () { if (!document.hidden) { loadMetric(); } }, 30000); }
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
        $(document).on('swl:theme', function () {
            if (S.ov) { drawKpis(); }
            if (S.tab === 'overview') { drawOvCharts(); }
            if (S.tab === 'metrics') { drawMetric(); }
            if (S.tab === 'infrastructure' && S.ov) { drawInfra(); }
        });
        show(h || 'overview');
    });
})(window.jQuery);
