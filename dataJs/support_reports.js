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
        $('.swl-rp-seg').on('click', '[data-range]', function () {
            $('.swl-rp-seg [data-range]').removeClass('is-on').attr('aria-pressed', 'false');
            $(this).addClass('is-on').attr('aria-pressed', 'true');
            $('#cs_rep_range').val(String($(this).data('range'))).trigger('change');
        });
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
        if (Math.abs(d) < 0.5) { return '<span class="swl-rp-delta">same</span>'; }
        var good = lowerIsBetter ? d < 0 : d > 0;
        var txt = unit === 'pts' ? Math.abs(Math.round(d)) + ' pts' : (unit === 'min' ? CS.dur(Math.abs(d)) : Math.abs(Math.round(d / prev * 100)) + '%');
        return '<span class="swl-rp-delta ' + (good ? 'is-good' : 'is-bad') + '" title="Compared with the previous period"><iconify-icon icon="' + (d > 0 ? 'solar:arrow-up-linear' : 'solar:arrow-down-linear') + '" aria-hidden="true"></iconify-icon>' + txt + '</span>';
    }
    function kpi(icon, label, value, sub, d) {
        return '<div class="swl-rp-kpi" title="' + CS.esc(sub.replace(/<[^>]+>/g, '')) + '"><div class="l"><span class="ic"><iconify-icon icon="' + icon + '" aria-hidden="true"></iconify-icon></span>' + CS.esc(label) + '</div>'
            + '<div class="v"><b>' + value + '</b>' + (d || '') + '</div><div class="swl-rp-spark" data-spark="' + CS.esc(label) + '"></div></div>';
    }

    var last = null;
    $(document).on('swl:theme', function () { if (last) { drawCharts(last[0], last[1]); } });

    function draw(r, p) {
        last = [r, p];
        $('#cs_rep_period').text(CS.full(r.period.from + ' 00:00').split(',')[0] + ' to ' + CS.full(r.period.to + ' 00:00').split(',')[0] + ' · compared with ' + p.from + ' to ' + p.to);
        $('#cs_basis').text(r.period.basis + '.');
        var slaR = pct(r.sla_resolve.met, r.sla_resolve.eligible), slaRp = pct(p.sla_resolve.met, p.sla_resolve.eligible);
        var slaF = pct(r.sla_first.met, r.sla_first.eligible);
        var cs = pct(r.csat.positive, r.csat.responses), csp = pct(p.csat.positive, p.csat.responses);
        var fcr = pct(r.fcr.met, r.fcr.eligible);
        var kingRate = pct(r.ai.contained, r.ai.engaged), kingPrev = pct(p.ai.contained, p.ai.engaged);
        $('#cs_kpis').html([
            kpi('solar:ticket-linear', 'New tickets', r.tickets.toLocaleString(), r.resolved + ' resolved · ' + r.reopened + ' reopened', delta(r.tickets, p.tickets, false)),
            kpi('solar:stopwatch-linear', 'First reply', CS.dur(r.human_first_response.median), 'Median · 90% within ' + CS.dur(r.human_first_response.p90), delta(r.human_first_response.median, p.human_first_median, true, 'min')),
            kpi('solar:shield-check-linear', 'SLA met', slaR == null ? '–' : slaR + '%', r.sla_resolve.met + ' of ' + r.sla_resolve.eligible + (slaF == null ? '' : ' · first reply ' + slaF + '%'), delta(slaR, slaRp, false, 'pts')),
            kpi('solar:magic-stick-3-linear', 'Solved by King', kingRate == null ? '–' : kingRate + '%', r.ai.contained + ' of ' + r.ai.engaged + ' chats King answered', delta(kingRate, kingPrev, false, 'pts')),
            kpi('solar:smile-circle-linear', 'Happy customers', cs == null ? '–' : cs + '%', r.csat.responses + ' ratings from ' + r.csat.asked + ' resolved', delta(cs, csp, false, 'pts')),
            kpi('solar:hand-shake-linear', 'Wait for a person', CS.dur(r.handover_wait.median), 'Median from handover to a person replying · ' + r.handover_wait.n + ' chats')
        ].join(''));
        var sparkData = {
            'New tickets': (r.daily || []).map(function (x) { return x.created; }), 'First reply': (r.daily || []).map(function (x) { return x.resolved; }),
            'SLA met': (r.daily || []).map(function (x) { return x.resolved; }), 'Solved by King': (r.daily || []).map(function (x) { return x.king; }),
            'Happy customers': (r.daily || []).map(function (x) { return x.resolved; }), 'Wait for a person': (r.daily || []).map(function (x) { return x.handed; })
        };
        var SC = ['#2563eb', '#0e7490', '#047857', '#7c3aed', '#d97706', '#b91c1c'];
        $('#cs_kpis [data-spark]').each(function (i) {
            var data = sparkData[$(this).data('spark')] || [];
            var mx = Math.max.apply(null, data.concat([1])), mn = Math.min.apply(null, data.concat([0]));
            var pts = data.map(function (v, j) { return (data.length > 1 ? j * 120 / (data.length - 1) : 0).toFixed(1) + ',' + (27 - (v - mn) / ((mx - mn) || 1) * 24).toFixed(1); }).join(' ');
            $(this).html(data.length > 1 ? '<svg viewBox="0 0 120 30" preserveAspectRatio="none" aria-hidden="true"><polyline points="' + pts + '" fill="none" stroke="' + SC[i % 6] + '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"></polyline></svg>' : '');
        });

        drawCharts(r, p);

        var chMax = Math.max.apply(null, (r.by_channel || []).map(function (x) { return x.n; }).concat([1]));
        $('#cs_by_channel').html((r.by_channel || []).length ? r.by_channel.map(function (x) {
            return '<div class="swl-rp-chrow is-' + CS.esc(x.key) + '">' + CS.ico('channel', x.key) + '<span class="l">' + CS.esc(x.label) + '</span><span class="t"><span style="width:' + (x.n / chMax * 100).toFixed(0) + '%"></span></span><b>' + x.n + '</b></div>';
        }).join('') : '<p class="swl-cs-small swl-cs-muted">No tickets in this period.</p>');
        var rs = r.handover_reasons || [], rMax = Math.max.apply(null, rs.map(function (x) { return x.n; }).concat([1]));
        $('#cs_reasons_bars').html(rs.length ? rs.map(function (x) {
            return '<div class="swl-rp-bar"><div class="h"><span>' + CS.esc(humanize(x.label)) + '</span><b>' + x.n + '</b></div><span class="t"><span style="width:' + (x.n / rMax * 100).toFixed(0) + '%"></span></span></div>';
        }).join('') : '<p class="swl-cs-small swl-cs-muted">No chats went to a person in this period.</p>');
        var rt = r.ratings || {}, rn = ['5', '4', '3', '2', '1'].reduce(function (a, k) { return a + (rt[k] || 0); }, 0);
        $('#cs_rating_n').text(rn + ' ratings');
        $('#cs_ratings').html(['5', '4', '3', '2', '1'].map(function (k) {
            var pc = rn ? Math.round((rt[k] || 0) / rn * 100) : 0;
            return '<div class="swl-rp-starrow is-s' + k + '"><span class="l">' + k + (k === '1' ? ' star' : ' stars') + '</span><span class="t"><span style="width:' + pc + '%"></span></span><b>' + pc + '%</b></div>';
        }).join(''));
        $('#cs_by_category').html(bars(r.by_category));
        $('#cs_by_team').html(bars(r.by_team));
        $('#cs_reasons').html(bars(r.handover_reasons));
        var ages = {}; r.backlog.forEach(function (b) { ages[b.age] = (ages[b.age] || 0) + b.n; });
        var order = ['< 1 day', '1-3 days', '3-7 days', '> 7 days'], aMax = Math.max.apply(null, order.map(function (k) { return ages[k] || 0; }).concat([1]));
        $('#cs_backlog').html('<div class="cols">' + order.map(function (k, i) {
            return '<div class="col-b is-a' + i + '"><b>' + (ages[k] || 0) + '</b><span class="bar" style="height:' + Math.max(6, Math.round((ages[k] || 0) / aMax * 96)) + 'px"></span></div>';
        }).join('') + '</div><div class="lbls">' + order.map(function (k) { return '<span>' + CS.esc(k.replace('-', '–')) + '</span>'; }).join('') + '</div>');
        $('#cs_ops').html('<p class="swl-cs-small mb-1"><b>Customer updates sent</b></p>' + bars(r.notifications, 'None in this period.') + '<p class="swl-cs-small mb-1 mt-2"><b>Automation runs</b></p>' + bars(r.automation, 'None in this period.'));

        var ai = r.ai, runs = r.ai_runs;
        $('#cs_king').html(ai.conversations ? '<p class="mb-2">' + CS.esc(V.king) + ' answered ' + ai.engaged + ' chats. ' + (ai.engaged ? Math.round(ai.confirmed / ai.engaged * 100) : 0) + '% of customers confirmed ' + CS.esc(V.king) + ' solved it; ' + (ai.engaged ? Math.round(ai.handed_over / ai.engaged * 100) : 0) + '% went to a person.</p><dl class="swl-cs-kv mb-2">'
            + '<dt>Chats started</dt><dd>' + ai.conversations + '</dd><dt>King answered</dt><dd>' + ai.engaged + '</dd>'
            + '<dt>Solved, confirmed</dt><dd>' + ai.confirmed + '</dd><dt>Solved, not confirmed</dt><dd>' + (ai.contained - ai.confirmed - ai.abandoned) + '</dd>'
            + '<dt>Left mid-chat</dt><dd>' + ai.abandoned + '</dd><dt>Handed to a person</dt><dd>' + ai.handed_over + '</dd></dl>'
            + '<p class="swl-cs-small swl-cs-muted mb-0">' + runs.n + ' AI calls · ' + (runs.latency_ms ? (Math.round(runs.latency_ms / 100) / 10) + ' s average' : '–') + ' · ' + runs.errors + ' errors'
            + (runs.discarded ? ' · ' + runs.discarded + ' late answers discarded after a person took over' : '') + ' · ' + (runs.tokens_in + runs.tokens_out).toLocaleString() + ' tokens</p>'
            : '<div class="swl-cs-empty">No chats in this period.</div>');

        $('#cs_agents').html(r.agents.length ? r.agents.map(function (a, i) {
            var pc = a.assigned ? Math.round(a.resolved / a.assigned * 100) : 0;
            var ini = String(a.name || '?').split(/\s+/).slice(0, 2).map(function (q) { return q.charAt(0); }).join('').toUpperCase();
            return '<tr><td><span class="swl-rp-agent"><span class="swl-cs-av is-sm t' + (i % 6) + '" aria-hidden="true">' + CS.esc(ini) + '</span><b>' + CS.esc(a.name) + '</b></span></td>'
                + '<td><span class="swl-rp-res"><span class="t"><span style="width:' + pc + '%"></span></span><span class="n">' + a.resolved + ' / ' + a.assigned + '</span></span></td>'
                + '<td>' + CS.dur(a.first_median) + '</td><td><b class="' + (a.sla_met == null ? '' : (a.sla_met >= 90 ? 'is-good' : 'is-warn')) + '">' + (a.sla_met == null ? '–' : a.sla_met + '%') + '</b></td>'
                + '<td>' + (a.csat == null ? '–' : '<span class="swl-rp-rating"><iconify-icon icon="solar:star-bold" aria-hidden="true"></iconify-icon>' + a.csat + '</span>') + '</td></tr>';
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
        opts.grid = $.extend({ strokeDashArray: 3 }, opts.grid || {});
        opts.tooltip = $.extend({ theme: 'light' }, opts.tooltip || {});
        charts[key] = new window.ApexCharts(box, CS.chartTheme(opts));
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

        // 4. Agents: assigned against resolved, the top eight by volume.
        var agents = (r.agents || []).slice(0, 8);
        if (!agents.length) { $('#cs_chart_agents').html('<div class="swl-cs-empty">No tickets in this period.</div>'); }
        else {
            chart('agents', 'cs_chart_agents', {
                chart: { type: 'bar', height: 280 },
                series: [{ name: 'Assigned', data: agents.map(function (a) { return a.assigned; }) }, { name: 'Resolved', data: agents.map(function (a) { return a.resolved; }) }],
                colors: [PAL.blue, PAL.teal],
                plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
                dataLabels: { enabled: false },
                xaxis: { categories: agents.map(function (a) { return a.name; }), labels: { style: { colors: '#6b7280' } } },
                yaxis: { min: 0, forceNiceScale: true, decimalsInFloat: 0 },
                legend: { position: 'top', horizontalAlign: 'left' }
            });
        }

        // 5. Why chats went to a person.
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
