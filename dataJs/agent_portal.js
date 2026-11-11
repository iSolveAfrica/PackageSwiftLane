"use strict";

// ============================================================================
// Partner Portal (views/sl/pages/AgentPortal.php): the agent's shipments,
// commission rule, monthly statements and announcements.
// ============================================================================

(function ($) {
    var OPS = window.OPS, esc = OPS.esc;
    $(function () {
        var agent = Number($('#ap_body').data('agent')) || 0;
        OPS.get('ajax/staffops/staffops_ajax.php', { action: 'agent', agent_id: agent }).then(function (r) {
            var d = r.data, h = '';
            $('#ap_lede').text(d.agent.name + ' · ' + d.rule);
            if (!d.agent.linked) { h += '<div class="swl-ops-banner w-full">This account is not linked to an agency yet, so no shipments are counted. An administrator links it under Staff &amp; Branches.</div>'; }
            h += '<section class="sl-card w-full">' + OPS.kpis(d.kpis) + '</section>';
            h += '<section class="sl-card w-full"><h2 class="card-title">Shipments</h2>' + OPS.table([
                { label: 'Tracking', render: function (s) { return '<a href="track.php?order_track=' + encodeURIComponent(s.trk) + '">' + esc(s.trk) + '</a>'; } },
                { key: 'order_date', label: 'Date' }, { key: 'customer', label: 'Customer' },
                { label: 'Status', render: function (s) { return '<span class="label" style="background:' + esc(s.color || '') + '">' + esc(s.status) + '</span>'; } },
                { label: 'Charges', render: function (s) { return OPS.num(s.total, 2); } }], d.shipments, 'No shipments yet.') + '</section>';
            h += '<section class="sl-card w-half"><h2 class="card-title">Commission statements</h2>' + OPS.table([{ key: 'period', label: 'Month' }, { label: 'Shipments', render: function (s) { return OPS.num(s.items); } },
                { label: 'Pending', render: function (s) { return OPS.num(s.pending, 2); } }, { label: 'Approved', render: function (s) { return OPS.num(s.approved, 2); } }, { label: 'Paid', render: function (s) { return OPS.num(s.paid, 2); } }],
                d.statements, 'No commission yet.') + '</section>';
            h += '<section class="sl-card w-half"><h2 class="card-title">Announcements</h2>' + (d.announcements.map(function (a) {
                return '<div class="swl-ops-insight"><b>' + esc(a.title) + '</b><div class="mt-2">' + esc(a.body).replace(/\n/g, '<br>') + '</div></div>';
            }).join('') || '<p class="swl-ops-muted">Nothing new.</p>') + '</section>';
            $('#ap_body').html(h);
        });
    });
})(window.jQuery);
