"use strict";

// ============================================================================
// Customer Service — shared helpers for the support pages and the chat button.
// Escaping first, then a small Markdown subset: no HTML from a customer, an
// agent or King ever reaches the page as HTML.
// ============================================================================

(function ($) {
    var CS = window.CS = window.CS || {};

    CS.esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };

    // Links: pages of this app (foo.php?…) or http(s) addresses only.
    CS.safeUrl = function (u) {
        u = String(u || '').trim();
        if (/^[a-z0-9_\-]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/i.test(u)) { return u; }
        if (/^https?:\/\/[^\s"'<>]+$/i.test(u)) { return u; }
        return null;
    };

    function inline(s) {
        var codes = [];
        s = s.replace(/`([^`]+)`/g, function (m, c) { codes.push(c); return '\u0000' + (codes.length - 1) + '\u0000'; });
        s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, label, url) {
            var safe = CS.safeUrl(url.replace(/&amp;/g, '&'));
            if (!safe) { return label; }
            return '<a href="' + CS.esc(safe) + '"' + (/^https?:/i.test(safe) ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' + label + '</a>';
        });
        s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
             .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
             .replace(/~~([^~]+)~~/g, '<del>$1</del>');
        return s.replace(/\u0000(\d+)\u0000/g, function (m, i) { return '<code>' + codes[+i] + '</code>'; });
    }

    CS.md = function (md) {
        var lines = CS.esc(String(md == null ? '' : md).replace(/\r\n?/g, '\n')).split('\n');
        var out = [], i = 0, para = [];
        var flush = function () { if (para.length) { out.push('<p>' + inline(para.join('<br>')) + '</p>'); para = []; } };
        var isRow = function (l) { return /^\s*\|.*\|\s*$/.test(l); };
        var cells = function (l) { return l.trim().replace(/^\||\|$/g, '').split('|').map(function (c) { return c.trim(); }); };
        while (i < lines.length) {
            var l = lines[i];
            var h = /^(#{1,4})\s+(.*)$/.exec(l);
            if (h) { flush(); out.push('<p><strong>' + inline(h[2]) + '</strong></p>'); i++; continue; }
            if (isRow(l) && i + 1 < lines.length && /^\s*\|?\s*:?-{2,}/.test(lines[i + 1])) {
                flush();
                var head = cells(l), body = [];
                i += 2;
                while (i < lines.length && isRow(lines[i])) { body.push(cells(lines[i])); i++; }
                out.push('<div class="table-responsive"><table class="table table-sm"><thead><tr>' + head.map(function (c) { return '<th>' + inline(c) + '</th>'; }).join('')
                    + '</tr></thead><tbody>' + body.map(function (r) { return '<tr>' + r.map(function (c) { return '<td>' + inline(c) + '</td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table></div>');
                continue;
            }
            if (/^\s*([-*+]|\d+[.)])\s+/.test(l)) {
                flush();
                var ordered = /^\s*\d+[.)]/.test(l), items = [];
                while (i < lines.length && /^\s*([-*+]|\d+[.)])\s+/.test(lines[i])) {
                    items.push('<li>' + inline(lines[i].replace(/^\s*([-*+]|\d+[.)])\s+/, '')) + '</li>');
                    i++;
                }
                out.push(ordered ? '<ol>' + items.join('') + '</ol>' : '<ul>' + items.join('') + '</ul>');
                continue;
            }
            if (/^\s*$/.test(l)) { flush(); i++; continue; }
            para.push(l.trim());
            i++;
        }
        flush();
        return out.join('');
    };

    // Markdown to one line of plain text (list previews).
    CS.plain = function (md) {
        return String(md == null ? '' : md).replace(/\[([^\]]+)\]\([^)]*\)/g, '$1').replace(/[*_`~#>|]+/g, '').replace(/\s+/g, ' ').trim();
    };

    CS.when = function (dt) {
        if (!dt) { return ''; }
        var d = new Date(String(dt).replace(' ', 'T'));
        if (isNaN(d)) { return dt; }
        var now = new Date();
        var diff = (now - d) / 1000;
        if (diff >= 0 && diff < 60) { return 'just now'; }
        if (diff >= 0 && diff < 3600) { return Math.floor(diff / 60) + ' min ago'; }
        if (d.toDateString() === now.toDateString()) { return d.toTimeString().slice(0, 5); }
        var y = new Date(now); y.setDate(now.getDate() - 1);
        if (d.toDateString() === y.toDateString()) { return 'Yesterday ' + d.toTimeString().slice(0, 5); }
        return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' }) + (d.getFullYear() !== now.getFullYear() ? ' ' + d.getFullYear() : '');
    };

    CS.full = function (dt) {
        if (!dt) { return ''; }
        var d = new Date(String(dt).replace(' ', 'T'));
        return isNaN(d) ? dt : d.toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    };

    // Minutes as "2 h 05 min" / "3 d 4 h".
    CS.dur = function (m) {
        if (m == null || isNaN(m)) { return '–'; }
        m = Math.round(m);
        if (m < 60) { return m + ' min'; }
        if (m < 1440) { return Math.floor(m / 60) + ' h ' + String(m % 60).padStart(2, '0') + ' min'; }
        return Math.floor(m / 1440) + ' d ' + Math.floor((m % 1440) / 60) + ' h';
    };

    CS.uid = function () {
        return 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
    };

    CS.get = function (url, data) { return $.ajax({ url: url, type: 'GET', data: data, dataType: 'json' }); };
    CS.post = function (url, data) { return $.ajax({ url: url, type: 'POST', data: data, dataType: 'json', traditional: false }); };

    CS.upload = function (url, file) {
        var fd = new FormData();
        fd.append('action', 'upload');
        fd.append('file', file);
        return $.ajax({ url: url, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' });
    };

    CS.toast = function (msg, icon) {
        if (window.Swal) { Swal.fire({ toast: true, position: 'top-end', icon: icon || 'error', title: msg, showConfirmButton: false, timer: 3800 }); }
        else { window.alert(msg); }
    };

    CS.confirm = function (title, text, button, fn) {
        if (window.Swal) {
            Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true, confirmButtonText: button }).then(function (r) { if (r.isConfirmed) { fn(); } });
        } else if (window.confirm(title + '\n\n' + text)) { fn(); }
    };

    CS.fail = function (xhr) {
        var r = xhr && xhr.responseJSON;
        CS.toast(r && r.error ? r.error : 'The server did not answer. Check your connection and try again.');
    };

    CS.attachments = function (list) {
        if (!list || !list.length) { return ''; }
        return '<div class="swl-cs-att">' + list.map(function (a) {
            var url = 'ajax/support/file.php?t=' + encodeURIComponent(a.token);
            if (/^image\//.test(a.mime)) {
                return '<a href="' + url + '" target="_blank" rel="noopener"><img src="' + url + '" alt="' + CS.esc(a.name) + '"></a>';
            }
            return '<a href="' + url + '&dl=1"><iconify-icon icon="solar:paperclip-linear"></iconify-icon> ' + CS.esc(a.name) + '</a>';
        }).join('') + '</div>';
    };

    CS.labels = {
        status: { 'new': 'New', open: 'Open', pending_customer: 'Waiting on customer', pending_internal: 'Waiting on our team', on_hold: 'On hold', resolved: 'Resolved', closed: 'Closed' },
        owner: { ai_active: 'King is answering', handoff_queued: 'Waiting for a person', human_active: 'With a person', resolved: 'Chat resolved' },
        sla: { ok: 'On track', warning: 'Due soon', breached: 'Missed', paused: 'Paused', met: 'Met' },
        priority: { low: 'Low', normal: 'Normal', high: 'High', urgent: 'Urgent' }
    };

    // One icon per meaning (Solar set, the same family the rest of the app uses).
    CS.icons = {
        status: { 'new': 'solar:stars-minimalistic-linear', open: 'solar:play-circle-linear', pending_customer: 'solar:clock-circle-linear', pending_internal: 'solar:users-group-rounded-linear', on_hold: 'solar:pause-circle-linear', resolved: 'solar:check-circle-linear', closed: 'solar:lock-keyhole-linear' },
        owner: { ai_active: 'solar:magic-stick-3-linear', handoff_queued: 'solar:bell-ringing-linear', human_active: 'solar:user-linear', resolved: 'solar:check-read-linear' },
        sla: { ok: 'solar:stopwatch-linear', warning: 'solar:hourglass-linear', breached: 'solar:danger-triangle-linear', paused: 'solar:pause-linear', met: 'solar:check-circle-linear' },
        priority: { low: 'solar:arrow-down-linear', normal: 'solar:minus-circle-linear', medium: 'solar:minus-circle-linear', high: 'solar:arrow-up-linear', urgent: 'solar:double-alt-arrow-up-linear' },
        channel: { web: 'solar:monitor-linear', portal: 'solar:global-linear', email: 'solar:letter-linear', phone: 'solar:phone-linear', whatsapp: 'solar:chat-round-dots-linear', sms: 'solar:chat-square-linear', walk_in: 'solar:user-speak-linear', staff: 'solar:user-id-linear', chat: 'solar:chat-round-line-linear' },
        category: { customs: 'solar:shield-check-linear', delivery: 'solar:box-linear', pickup: 'solar:map-point-linear', invoice: 'solar:bill-list-linear', refund: 'solar:wallet-money-linear', locker: 'solar:lock-linear', account: 'solar:user-circle-linear' },
        view: { all: 'solar:inbox-linear', mine: 'solar:user-linear', unassigned: 'solar:user-rounded-linear', queued: 'solar:bell-ringing-linear', ai: 'solar:magic-stick-3-linear', followed: 'solar:bell-linear', internal: 'solar:users-group-rounded-linear', sla_risk: 'solar:stopwatch-linear', overdue: 'solar:danger-triangle-linear', archived: 'solar:archive-linear', spam: 'solar:shield-warning-linear', trash: 'solar:trash-bin-trash-linear' },
        action: { reply: 'solar:reply-linear', note: 'solar:notes-linear', hand: 'solar:hand-shake-linear', takeover: 'solar:user-hand-up-linear', release: 'solar:undo-left-linear', resolve: 'solar:check-circle-linear', follow: 'solar:bell-linear', ai: 'solar:magic-stick-3-linear', refund: 'solar:wallet-money-linear', address: 'solar:map-point-linear', resend: 'solar:restart-linear', clock: 'solar:calendar-linear', shield: 'solar:shield-check-linear', tag: 'solar:tag-linear', link: 'solar:link-linear', search: 'solar:magnifer-linear', dashboard: 'solar:chart-2-linear', download: 'solar:download-linear', settings: 'solar:settings-linear', king: 'solar:magic-stick-3-linear', person: 'solar:user-linear', mail: 'solar:letter-linear', chat: 'solar:chat-round-dots-linear', sms: 'solar:chat-square-linear', wa: 'solar:chat-round-dots-linear', ok: 'solar:check-circle-bold', warn: 'solar:danger-circle-linear', info: 'solar:info-circle-linear', star: 'solar:star-bold', flag: 'solar:flag-linear', clock2: 'solar:history-linear', pulse: 'solar:pulse-2-linear', ticket: 'solar:ticket-linear', handover: 'solar:transfer-horizontal-linear', tokens: 'solar:cpu-bolt-linear' }
    };

    // Icon for a key of a group (status, owner, sla, priority, channel, category, view, action).
    CS.ico = function (group, key, extra) {
        var map = CS.icons[group] || {};
        var name = map[key];
        if (!name) { return ''; }
        return '<iconify-icon icon="' + name + '" class="swl-cs-ico' + (extra ? ' ' + extra : '') + '" aria-hidden="true"></iconify-icon>';
    };

    CS.pill = function (kind, key, label) {
        var text = label || (CS.labels[kind] && CS.labels[kind][key]) || key || '';
        return text ? '<span class="swl-cs-pill is-' + CS.esc(key) + '">' + CS.ico(kind, key) + CS.esc(text) + '</span>' : '';
    };

    // Chart options that follow the light/dark theme (helpers/theme.php).
    CS.chartTheme = function (opts) {
        var root = getComputedStyle(document.documentElement), dark = document.documentElement.getAttribute('data-swl-theme') === 'dark';
        var v = function (n, d) { return (root.getPropertyValue(n) || '').trim() || d; };
        opts.chart = $.extend({}, opts.chart || {}, { foreColor: v('--slate-600', '#59697D'), background: 'transparent' });
        opts.theme = { mode: dark ? 'dark' : 'light' };
        opts.grid = $.extend({}, opts.grid || {}, { borderColor: v('--line-200', '#e5e7eb') });
        opts.tooltip = $.extend({}, opts.tooltip || {}, { theme: dark ? 'dark' : 'light' });
        if (opts.stroke && opts.stroke.colors && opts.stroke.colors[0] === '#fff') { opts.stroke.colors = [v('--white', '#fff')]; }
        return opts;
    };

    CS.grow = function (el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 160) + 'px';
    };
})(window.jQuery);
