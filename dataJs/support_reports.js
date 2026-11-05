"use strict";

// ============================================================================
// Customer Service reports. One call returns the period and the previous
// period of the same length; deltas compare the two. Empty periods show
// zeros and dashes, never invented numbers.
// ============================================================================

(function ($) {
    var V = window.CDP_CS_VOCAB || {};
    var CS = window.CS;
    var URL_ = 'ajax/support/reports_ajax.php';

    $(function () {
        var o = function (obj) { return Object.keys(obj).map(function (k) { return '<option value="' + CS.esc(k) + '">' + CS.esc(obj[k]) + '</option>'; }).join(''); };
        $('[data-rf="channel"]').append(o(V.channels));
        $('[data-rf="category"]').append(o(V.categories));
        $('[data-rf="priority"]').append(o(V.priorities));
        $('[data-rf="team_id"]').append(V.teams.map(function (t) { return '<option value="' + t.id + '">' + CS.esc(t.name) + '</option>'; }).join(''));
        $('[data-rf="assignee_id"]').append(V.agents.map(function (a) { return '<option value="' + a.id + '">' + CS.esc(a.name) + '</option>'; }).join(''));
        $('#cs_rep_range').on('change', function () {
            var custom = this.value === 'custom';
            $('#cs_rep_from, #cs_rep_to').prop('hidden', !custom);
            if (!custom) { load(); }
        });
        $('#cs_rep_from, #cs_rep_to, [data-rf]').on('change', load);
        $('#cs_export').on('click', function () { location.href = URL_ + '?' + $.param($.extend({ action: 'export' }, params())); });
        load();
    });

    function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }

    function params() {
        var r = $('#cs_rep_range').val(), now = new Date(), p = {};
        if (r === 'custom') { p.from = $('#cs_rep_from').val(); p.to = $('#cs_rep_to').val(); }
        else if (r === 'month') { p.from = ymd(new Date(now.getFullYear(), now.getMonth(), 1)); p.to = ymd(now); }
        else { var f = new Date(now); f.setDate(now.getDate() - (+r - 1)); p.from = ymd(f); p.to = ymd(now); }
        $('[data-rf]').each(function () { if ($(this).val()) { p[$(this).data('rf')] = $(this).val(); } });
        return p;
    }

    function load() {
        $('#cs_kpis').css('opacity', 0.5);
        CS.get(URL_, $.extend({ action: 'report' }, params())).done(function (r) {
            if (!r || !r.ok) { CS.toast(r && r.error ? r.error : 'Could not load.'); return; }
            draw(r.report, r.previous);
        }).fail(CS.fail).always(function () { $('#cs_kpis').css('opacity', 1); });
    }

    function pct(m, e) { return e ? Math.round(m / e * 100) : null; }
    function delta(cur, prev, lowerIsBetter, unit) {
        if (cur == null || prev == null || prev === 0) { return ''; }
        var d = cur - prev;
        if (Math.abs(d) < 0.5) { return '<span class="swl-cs-kpi-s">Same as before</span>'; }
        var good = lowerIsBetter ? d < 0 : d > 0;
        var txt = unit === 'pts' ? Math.abs(Math.round(d)) + ' pts' : (unit === 'min' ? CS.dur(Math.abs(d)) : Math.abs(Math.round(d / prev * 100)) + '%');
        return '<span class="swl-cs-kpi-s" style="color: var(' + (good ? '--teal-700' : '--red-500') + ')">' + (d > 0 ? '▲ ' : '▼ ') + txt + ' vs previous</span>';
    }
    function kpi(icon, label, value, sub, d) {
        return '<div class="swl-cs-kpi"><div class="swl-cs-kpi-l"><iconify-icon icon="' + icon + '" aria-hidden="true"></iconify-icon> ' + CS.esc(label) + '</div><div class="swl-cs-kpi-n">' + value + '</div><div class="swl-cs-kpi-s">' + sub + '</div>' + (d || '') + '</div>';
    }

    function draw(r, p) {
        $('#cs_rep_period').text(CS.full(r.period.from + ' 00:00').split(',')[0] + ' to ' + CS.full(r.period.to + ' 00:00').split(',')[0] + ' · compared with ' + p.from + ' to ' + p.to);
        $('#cs_basis').text(r.period.basis + '.');
        var slaR = pct(r.sla_resolve.met, r.sla_resolve.eligible), slaRp = pct(p.sla_resolve.met, p.sla_resolve.eligible);
        var slaF = pct(r.sla_first.met, r.sla_first.eligible);
        var cs = pct(r.csat.positive, r.csat.responses), csp = pct(p.csat.positive, p.csat.responses);
        var fcr = pct(r.fcr.met, r.fcr.eligible);
        var kingRate = pct(r.ai.contained, r.ai.engaged), kingPrev = pct(p.ai.contained, p.ai.engaged);
        $('#cs_kpis').html([
            kpi('solar:ticket-linear', 'Tickets', r.tickets, r.resolved + ' resolved · ' + r.reopened + ' reopened', delta(r.tickets, p.tickets, false)),
            kpi('solar:stopwatch-linear', 'First reply (median)', CS.dur(r.human_first_response.median), '90% within ' + CS.dur(r.human_first_response.p90) + ' · ' + r.human_first_response.n + ' tickets', delta(r.human_first_response.median, p.human_first_median, true, 'min')),
            kpi('solar:check-circle-linear', 'Resolution (median)', CS.dur(r.resolution.median), '90% within ' + CS.dur(r.resolution.p90), delta(r.resolution.median, p.resolution_median, true, 'min')),
            kpi('solar:shield-check-linear', 'Resolution SLA met', slaR == null ? '–' : slaR + '%', r.sla_resolve.met + ' of ' + r.sla_resolve.eligible + (slaF == null ? '' : ' · first reply ' + slaF + '%'), delta(slaR, slaRp, false, 'pts')),
            kpi('solar:star-linear', 'Solved first time', fcr == null ? '–' : fcr + '%', r.fcr.met + ' of ' + r.fcr.eligible + ' resolved'),
            kpi('solar:like-linear', 'Customer rating', cs == null ? '–' : cs + '% happy', r.csat.responses + ' ratings from ' + r.csat.asked + ' resolved', delta(cs, csp, false, 'pts')),
            kpi('solar:hand-shake-linear', 'Wait for a person (median)', CS.dur(r.handover_wait.median), 'From handover to a person replying · ' + r.handover_wait.n + ' chats'),
            kpi('solar:magic-stick-3-linear', 'King solved', kingRate == null ? '–' : kingRate + '%', r.ai.contained + ' of ' + r.ai.engaged + ' chats King answered', delta(kingRate, kingPrev, false, 'pts'))
        ].join(''));

        drawCharts(r, p);

        $('#cs_by_channel').html(bars(r.by_channel));
        $('#cs_by_category').html(bars(r.by_category));
        $('#cs_by_team').html(bars(r.by_team));
        $('#cs_reasons').html(bars(r.handover_reasons));
        var ages = {}; r.backlog.forEach(function (b) { ages[b.age] = (ages[b.age] || 0) + b.n; });
        $('#cs_backlog').html(bars(Object.keys(ages).map(function (k) { return { label: k, n: ages[k] }; }), 'Nothing open.'));
        $('#cs_ops').html('<p class="swl-cs-small mb-1"><b>Customer updates sent</b></p>' + bars(r.notifications, 'None in this period.') + '<p class="swl-cs-small mb-1 mt-2"><b>Automation runs</b></p>' + bars(r.automation, 'None in this period.'));

        var ai = r.ai, runs = r.ai_runs;
        $('#cs_king').html(ai.conversations ? '<dl class="swl-cs-kv mb-2">'
            + '<dt>Chats started</dt><dd>' + ai.conversations + '</dd><dt>King answered</dt><dd>' + ai.engaged + '</dd>'
            + '<dt>Solved, confirmed</dt><dd>' + ai.confirmed + '</dd><dt>Solved, not confirmed</dt><dd>' + (ai.contained - ai.confirmed - ai.abandoned) + '</dd>'
            + '<dt>Left mid-chat</dt><dd>' + ai.abandoned + '</dd><dt>Handed to a person</dt><dd>' + ai.handed_over + '</dd></dl>'
            + '<p class="swl-cs-small swl-cs-muted mb-0">' + runs.n + ' AI calls · ' + (runs.latency_ms ? (Math.round(runs.latency_ms / 100) / 10) + ' s average' : '–') + ' · ' + runs.errors + ' errors'
            + (runs.discarded ? ' · ' + runs.discarded + ' late answers discarded after a person took over' : '') + ' · ' + (runs.tokens_in + runs.tokens_out).toLocaleString() + ' tokens</p>'
            : '<div class="swl-cs-empty">No chats in this period.</div>');

        $('#cs_agents').html(r.agents.length ? r.agents.map(function (a) {
            return '<tr><td>' + CS.esc(a.name) + '</td><td>' + a.assigned + '</td><td>' + a.resolved + '</td><td>' + CS.dur(a.first_median) + '</td><td>' + (a.csat == null ? '–' : a.csat + '/5 (' + a.csat_n + ')') + '</td></tr>';
        }).join('') : '<tr><td colspan="5"><div class="swl-cs-empty">No tickets in this period.</div></td></tr>');
    }

    // ---- Charts (ApexCharts, already vendored for the dashboards) ----------
    var charts = {};
    var PAL = { blue: '#2563eb', teal: '#0f766e', violet: '#7c3aed', amber: '#d97706', red: '#dc2626', slate: '#94a3b8' };

    function ymdLabel(k) {
        var d = new Date(k + 'T00:00');
        return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
    }
    function humanize(code) {
        var t = String(code || '').replace(/[_-]+/g, ' ').trim();
        return t ? t.charAt(0).toUpperCase() + t.slice(1) : 'Not given';
    }
    function pctOr0(v) { return v == null ? 0 : v; }

    function chart(key, el, opts) {
        var box = document.getElementById(el);
        if (!box) { return; }
        if (typeof window.ApexCharts === 'undefined') {
            box.innerHTML = '<p class="swl-cs-small swl-cs-muted">The chart library did not load. The numbers above are still correct.</p>';
            return;
        }
        if (charts[key]) { charts[key].destroy(); }
        opts.chart = $.extend({ type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'inherit', animations: { speed: 450 }, background: 'transparent' }, opts.chart || {});
        opts.grid = $.extend({ borderColor: '#e5e7eb', strokeDashArray: 3 }, opts.grid || {});
        opts.tooltip = $.extend({ theme: 'light' }, opts.tooltip || {});
        charts[key] = new window.ApexCharts(box, opts);
        charts[key].render();
    }

    function drawCharts(r, p) {
        // 1. Daily volume and outcomes.
        var daily = r.daily || [];
        var cats = daily.map(function (x) { return ymdLabel(x.day); });
        var total = daily.reduce(function (a, x) { return a + x.created; }, 0);
        $('#cs_daily_note').text(total ? total + ' new in this period' : '');
        if (!daily.length) { $('#cs_chart_daily').html('<div class="swl-cs-empty">No tickets in this period.</div>'); }
        else {
            chart('daily', 'cs_chart_daily', {
                chart: { type: 'area', height: 320 },
                series: [
                    { name: 'New tickets', data: daily.map(function (x) { return x.created; }) },
                    { name: 'Resolved', data: daily.map(function (x) { return x.resolved; }) },
                    { name: 'Solved by King', data: daily.map(function (x) { return x.king; }) },
                    { name: 'Handed to a person', data: daily.map(function (x) { return x.handed; }) }
                ],
                colors: [PAL.blue, PAL.teal, PAL.violet, PAL.amber],
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 0.2, opacityFrom: 0.28, opacityTo: 0.02 } },
                dataLabels: { enabled: false },
                xaxis: { categories: cats, tickAmount: Math.min(10, cats.length), labels: { style: { colors: '#6b7280' } }, axisBorder: { show: false }, tooltip: { enabled: false } },
                yaxis: { min: 0, forceNiceScale: true, decimalsInFloat: 0, labels: { style: { colors: '#6b7280' } } },
                legend: { position: 'top', horizontalAlign: 'left', markers: { radius: 12 } },
                markers: { size: 0, hover: { size: 5 } }
            });
        }

        // 2. Where King's chats ended.
        var ai = r.ai || {};
        var left = ai.abandoned || 0, handed = ai.handed_over || 0, conf = ai.confirmed || 0;
        var solvedNot = Math.max(0, (ai.contained || 0) - conf - left);
        var ends = [conf, solvedNot, left, handed];
        if (ends.reduce(function (a, b) { return a + b; }, 0) === 0) { $('#cs_chart_ends').html('<div class="swl-cs-empty">No King chats in this period.</div>'); }
        else {
            chart('ends', 'cs_chart_ends', {
                chart: { type: 'donut', height: 240 },
                series: ends,
                labels: ['Solved, confirmed', 'Solved, not confirmed', 'Left mid-chat', 'Handed to a person'],
                colors: [PAL.teal, PAL.blue, PAL.slate, PAL.amber],
                dataLabels: { enabled: false },
                legend: { position: 'bottom', fontSize: '12px' },
                plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Chats', formatter: function (w) { return w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0); } } } } } },
                stroke: { width: 2, colors: ['#fff'] }
            });
        }

        // 3. Service health, as percentages with a hollow radial each.
        var health = [
            ['Resolution SLA', r.sla_resolve.eligible ? pctOr0(pct(r.sla_resolve.met, r.sla_resolve.eligible)) : 0, r.sla_resolve.eligible],
            ['Solved first time', r.fcr.eligible ? pct(r.fcr.met, r.fcr.eligible) : 0, r.fcr.eligible],
            ['Happy customers', r.csat.responses ? pct(r.csat.positive, r.csat.responses) : 0, r.csat.responses],
            ['King solved', ai.engaged ? pct(ai.contained, ai.engaged) : 0, ai.engaged]
        ];
        chart('health', 'cs_chart_health', {
            chart: { type: 'radialBar', height: 260 },
            series: health.map(function (h) { return h[1]; }),
            labels: health.map(function (h) { return h[0] + (h[2] ? '' : ' (no data)'); }),
            colors: [PAL.blue, PAL.teal, PAL.violet, PAL.amber],
            plotOptions: { radialBar: { hollow: { size: '34%' }, track: { background: '#eef2f7' }, dataLabels: { name: { fontSize: '12px' }, value: { fontSize: '14px', fontWeight: 700, formatter: function (v) { return Math.round(v) + '%'; } }, total: { show: false } } } },
            legend: { show: true, position: 'right', fontSize: '12px' }
        });

        // 4. Why chats went to a person.
        var reasons = (r.handover_reasons || []).slice(0, 8);
        if (!reasons.length) { $('#cs_chart_reasons').html('<div class="swl-cs-empty">No chats went to a person in this period.</div>'); }
        else {
            chart('reasons', 'cs_chart_reasons', {
                chart: { type: 'bar', height: Math.max(180, reasons.length * 38 + 40) },
                series: [{ name: 'Chats', data: reasons.map(function (x) { return x.n; }) }],
                colors: [PAL.amber],
                plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '60%' } },
                xaxis: { categories: reasons.map(function (x) { return humanize(x.label); }), labels: { style: { colors: '#6b7280' } } },
                dataLabels: { enabled: true, style: { fontSize: '12px' }, offsetX: 6 },
                yaxis: { labels: { style: { fontSize: '12px' } } },
                grid: { show: false }
            });
        }
    }

    function bars(list, empty) {
        if (!list || !list.length) { return '<p class="swl-cs-small swl-cs-muted">' + CS.esc(empty || 'No data in this period.') + '</p>'; }
        var max = Math.max.apply(null, list.map(function (x) { return x.n; }).concat([1]));
        return list.map(function (x) {
            return '<div class="swl-cs-hbar"><span class="l" title="' + CS.esc(x.label) + '">' + CS.esc(x.label || '–') + '</span><span class="t"><span style="width:' + (x.n / max * 100) + '%"></span></span><span class="n">' + x.n + '</span></div>';
        }).join('');
    }
})(window.jQuery);
