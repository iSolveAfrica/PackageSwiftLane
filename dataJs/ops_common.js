"use strict";

// ============================================================================
// Shared helpers for the operations pages: Communication Center, WhatsApp
// Inbox, Gerald Master View, Staff & Branches, Workspace, Agent Portal.
// jQuery's global prefilter (views/inc/footer.php) adds the CSRF token to
// every POST, so these helpers only build requests and render markup.
// Everything that reaches the page is escaped with OPS.esc first.
// ============================================================================

(function ($) {
    var OPS = window.OPS = window.OPS || {};

    // The Swift Lane shell (views/sl/inc) has no jQuery prefilter of its own:
    // every POST from these pages carries the session's CSRF token here.
    $.ajaxPrefilter(function (options, original, xhr) {
        var method = String(options.type || 'GET').toUpperCase();
        if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) === -1) { return; }
        var t = document.querySelector('meta[name="csrf-token"]'), p = document.querySelector('meta[name="csrf-param"]');
        if (!t) { return; }
        xhr.setRequestHeader('X-CSRF-Token', t.content);
        if (typeof options.data === 'string') {
            options.data += (options.data ? '&' : '') + encodeURIComponent(p ? p.content : '_csrf_token') + '=' + encodeURIComponent(t.content);
        }
    });

    OPS.esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };

    OPS.num = function (n, d) {
        if (n === null || n === undefined || n === '' || isNaN(n)) { return '—'; }
        return Number(n).toLocaleString(undefined, { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 });
    };

    OPS.get = function (url, data) {
        return $.ajax({ url: url, type: 'GET', data: data || {}, dataType: 'json' }).then(null, OPS.failed);
    };

    OPS.post = function (url, data) {
        return $.ajax({ url: url, type: 'POST', data: data || {}, dataType: 'json' }).then(null, OPS.failed);
    };

    OPS.failed = function (xhr) {
        var msg = (xhr && xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) || 'The request failed. Please try again.';
        OPS.toast(msg, 'error');
        return $.Deferred().reject(msg).promise();
    };

    OPS.toast = function (msg, icon) {
        if (window.SL && SL.toast && document.getElementById('sl-toast')) {
            SL.toast(String(msg), icon === 'error');
        } else if (window.Swal && Swal.fire) {
            Swal.fire({ toast: true, position: 'top-end', timer: 3500, showConfirmButton: false, icon: icon || 'success', title: String(msg) });
        } else {
            window.alert(msg);
        }
    };

    OPS.result = function (r, okMsg) {
        if (r && r.ok) { OPS.toast(okMsg || 'Saved'); return true; }
        OPS.toast((r && r.error) || 'Not saved', 'error');
        return false;
    };

    OPS.confirm = function (text) {
        if (!(window.SL && document.getElementById('sl-toast')) && window.Swal && Swal.fire) {
            return Swal.fire({ text: text, icon: 'question', showCancelButton: true, confirmButtonText: 'Yes', cancelButtonText: 'Cancel' })
                .then(function (r) { return r.isConfirmed ? true : $.Deferred().reject().promise(); });
        }
        return window.confirm(text) ? $.Deferred().resolve(true).promise() : $.Deferred().reject().promise();
    };

    /** Accessible tabs: buttons [data-tab] control panes #<prefix>_pane_<key>. */
    OPS.tabs = function (prefix, onShow) {
        var $tabs = $('[role="tablist"] [data-tab]');
        function show(key, focus) {
            $tabs.each(function () {
                var on = $(this).data('tab') === key;
                $(this).attr('aria-selected', on ? 'true' : 'false').attr('tabindex', on ? '0' : '-1');
                $('#' + prefix + '_pane_' + $(this).data('tab')).prop('hidden', !on);
                if (on && focus) { this.focus(); }
            });
            try { window.history.replaceState(null, '', '#' + key); } catch (e) { /* ignore */ }
            if (onShow) { onShow(key); }
        }
        $tabs.on('click', function () { show($(this).data('tab'), false); });
        $tabs.on('keydown', function (ev) {
            var keys = $tabs.map(function () { return $(this).data('tab'); }).get();
            var i = keys.indexOf($(this).data('tab'));
            if (ev.key === 'ArrowRight') { show(keys[(i + 1) % keys.length], true); ev.preventDefault(); }
            if (ev.key === 'ArrowLeft') { show(keys[(i - 1 + keys.length) % keys.length], true); ev.preventDefault(); }
        });
        var start = (window.location.hash || '').replace('#', '');
        show($tabs.filter('[data-tab="' + start + '"]').length ? start : $tabs.first().data('tab'), false);
        return show;
    };

    OPS.kpis = function (items) {
        return '<div class="swl-ops-kpis">' + items.map(function (k) {
            var d = '';
            if (k.delta !== undefined && k.delta !== null && k.delta !== '') {
                var up = Number(k.delta) >= 0;
                var good = k.invert ? !up : up;
                d = ' <span class="' + (good ? 'swl-ops-kpi__d--up' : 'swl-ops-kpi__d--down') + '">' + (up ? '▲ ' : '▼ ') + OPS.esc(Math.abs(k.delta)) + '%</span>';
            }
            return '<div class="swl-ops-kpi"><div class="swl-ops-kpi__l">' + OPS.esc(k.label) + '</div><div class="swl-ops-kpi__n">' + OPS.esc(k.value) + '</div>'
                + '<div class="swl-ops-kpi__s">' + OPS.esc(k.sub || '') + d + '</div></div>';
        }).join('') + '</div>';
    };

    /** cols: [{key, label, render(row) -> html}] ; rows: objects */
    OPS.table = function (cols, rows, empty) {
        if (!rows || !rows.length) { return '<p class="swl-ops-muted">' + OPS.esc(empty || 'Nothing to show yet.') + '</p>'; }
        return '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr>' + cols.map(function (c) { return '<th scope="col">' + OPS.esc(c.label) + '</th>'; }).join('')
            + '</tr></thead><tbody>' + rows.map(function (r) {
                return '<tr>' + cols.map(function (c) { return '<td>' + (c.render ? c.render(r) : OPS.esc(r[c.key])) + '</td>'; }).join('') + '</tr>';
            }).join('') + '</tbody></table></div>';
    };

    /** rows: [[label, value, pct]] */
    OPS.hbars = function (rows) {
        if (!rows || !rows.length) { return '<p class="swl-ops-muted">No data yet.</p>'; }
        return rows.map(function (r) {
            return '<div class="swl-ops-hbar"><div class="swl-ops-hbar__row"><span>' + OPS.esc(r[0]) + '</span><span>' + OPS.esc(r[1]) + '</span></div>'
                + '<div class="swl-ops-hbar__track"><div class="swl-ops-hbar__fill" style="width:' + Math.max(0, Math.min(100, Number(r[2]) || 0)) + '%"></div></div></div>';
        }).join('');
    };

    /** Vertical mini bars. values: numbers; labels: first/last shown on the axis. */
    OPS.vbars = function (values, labels, title) {
        var max = Math.max.apply(null, (values || []).concat([1]));
        var html = '<div class="swl-ops-bars" role="img" aria-label="' + OPS.esc(title || 'Chart') + '">' + (values || []).map(function (v, i) {
            return '<span style="height:' + Math.max(1, Math.round(100 * v / max)) + '%" title="' + OPS.esc((labels && labels[i] ? labels[i] + ': ' : '') + v) + '"></span>';
        }).join('') + '</div>';
        if (labels && labels.length) {
            html += '<div class="swl-ops-axis"><span>' + OPS.esc(labels[0]) + '</span><span>' + OPS.esc(labels[labels.length - 1]) + '</span></div>';
        }
        return html;
    };

    OPS.chip = function (text, tone) {
        return '<span class="swl-ops-chip' + (tone ? ' is-' + tone : '') + '">' + OPS.esc(text) + '</span>';
    };

    OPS.statusTone = function (s) {
        s = String(s || '').toLowerCase();
        if (['sent', 'ok', 'approved', 'done', 'booked', 'active', 'received', 'completed', 'present'].indexOf(s) !== -1) { return 'ok'; }
        if (['failed', 'rejected', 'cancelled', 'absent', 'overdue', 'high'].indexOf(s) !== -1) { return 'bad'; }
        if (['queued', 'pending', 'draft', 'scheduled', 'sending', 'open', 'late', 'medium', 'in_transit'].indexOf(s) !== -1) { return 'warn'; }
        return 'info';
    };

    OPS.field = function (id, label, inputHtml, col) {
        return '<div class="' + (col || 'col-md-6') + '"><div class="form-group"><label for="' + id + '">' + OPS.esc(label) + '</label>' + inputHtml + '</div></div>';
    };

    OPS.input = function (id, value, attrs) {
        return '<input id="' + id + '" name="' + id + '" class="form-control" value="' + OPS.esc(value == null ? '' : value) + '" ' + (attrs || '') + '>';
    };

    OPS.select = function (id, options, value, attrs) {
        return '<select id="' + id + '" name="' + id + '" class="custom-select" ' + (attrs || '') + '>' + options.map(function (o) {
            var v = Array.isArray(o) ? o[0] : o, l = Array.isArray(o) ? o[1] : o;
            return '<option value="' + OPS.esc(v) + '"' + (String(v) === String(value) ? ' selected' : '') + '>' + OPS.esc(l) + '</option>';
        }).join('') + '</select>';
    };

    OPS.debounce = function (fn, ms) {
        var t;
        return function () { var a = arguments, self = this; clearTimeout(t); t = setTimeout(function () { fn.apply(self, a); }, ms || 300); };
    };

    OPS.download = function (url) {
        window.location.href = url;
    };
})(window.jQuery);
