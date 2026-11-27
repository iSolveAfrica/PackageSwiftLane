/*
 * Outbox (views/sl/pages/CommsOutbox.php) and the message drawer it shares
 * with Customer history (views/sl/pages/CommsCustomers.php).
 *
 * - filters: selects submit at once, quick date ranges;
 * - a row (click, Enter) opens the drawer: full message, provider response,
 *   attempts, cost, opens and clicks, links to the customer, record and
 *   campaign; Retry, Cancel, Send again; "Explain this failure" with King;
 * - ?open=<ref> opens a row on load, and the address follows the drawer.
 * Endpoint: ajax/comms/outbox_ajax.php. Public: window.SLOutbox.open(ref).
 */
(function () {
    'use strict';
    var B = window.SL_OUTBOX || window.SL_HISTORY || {};
    var AJ = B.ajax || 'ajax/comms/outbox_ajax.php';
    var AI = B.ai || { ok: false, name: 'King' };
    function $(id) { return document.getElementById(id); }
    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function toast(m, bad) { if (window.SL && SL.toast) SL.toast(m, bad); }
    var CH_ICON = { whatsapp: 'i-wa', sms: 'i-message', email: 'i-mail' };

    /* ---------------- filters ---------------- */
    var form = $('ob-filters');
    if (form) {
        form.addEventListener('change', function (e) {
            if (e.target.matches('[data-ob-auto]')) { form.requestSubmit ? form.requestSubmit() : form.submit(); }
        });
        form.addEventListener('submit', function () {
            // empty fields stay out of the address
            Array.prototype.forEach.call(form.elements, function (el) { if (el.name && el.value === '') el.disabled = true; });
        });
        form.addEventListener('click', function (e) {
            var b = e.target.closest('[data-ob-range]');
            if (!b) return;
            var v = b.getAttribute('data-ob-range'), from = form.elements.from, to = form.elements.to;
            if (v === '') { from.value = ''; to.value = ''; }
            else {
                var d = new Date(), t = d.toISOString().slice(0, 10);
                d.setDate(d.getDate() - Number(v));
                from.value = d.toISOString().slice(0, 10); to.value = t;
            }
            form.requestSubmit ? form.requestSubmit() : form.submit();
        });
    }

    /* ---------------- drawer ---------------- */
    var dlg = $('ob-dlg');
    if (!dlg) return;
    var body = $('ob-d-body'), foot = $('ob-d-actions'), cur = null, seq = 0;

    function setUrl(ref) {
        try {
            var u = new URL(location.href);
            if (ref) u.searchParams.set('open', ref); else u.searchParams.delete('open');
            history.replaceState(null, '', u.pathname.split('/').pop() + (u.search ? u.search : '') + u.hash);
        } catch (e) { /* old browsers: the address just does not follow */ }
    }
    new MutationObserver(function () { if (dlg.hidden) { setUrl(''); cur = null; } }).observe(dlg, { attributes: true, attributeFilter: ['hidden'] });

    function fact(label, html) { return html ? '<div class="ob-fact"><dt>' + esc(label) + '</dt><dd>' + html + '</dd></div>' : ''; }
    function link(href, text, ext) { return href ? '<a href="' + esc(href) + '"' + (ext ? ' target="_blank" rel="noopener"' : '') + '>' + esc(text) + '</a>' : esc(text); }
    function money(n, c) { return n == null ? '' : esc((c ? c + ' ' : '') + Number(n).toFixed(3)); }

    function render(r) {
        $('ob-d-title').textContent = r.what || 'Message';
        $('ob-d-sub').textContent = [r.origin_label, r.when].filter(Boolean).join(' · ');
        $('ob-d-ch').innerHTML = '<i class="ic ' + (CH_ICON[r.channel] || 'i-send') + '"></i>';
        $('ob-d-ch').className = 'ob-d-ch ' + (r.channel || 'all');
        var lk = $('ob-d-link');
        if (lk && B.outboxUrl) { lk.href = B.outboxUrl + '?open=' + encodeURIComponent(r.ref); lk.hidden = false; }
        var h = '';
        var bad = r.status === 'failed', warn = r.status === 'skipped' || r.status === 'cancelled' || r.status === 'retrying';
        h += '<div class="ob-state ' + (bad ? 'bad' : warn ? 'warn' : r.status === 'sent' || r.status === 'received' ? 'ok' : 'info') + '"><span class="sl-pill ' + esc(r.cls) + '">' + esc(r.status_label) + '</span>'
            + '<span>' + esc(r.detail || (r.status === 'sent' ? 'Accepted by the provider.' : r.status === 'received' ? 'The customer sent this to us.' : r.status === 'scheduled' ? 'Goes out ' + (r.send_after_label || 'later') + '.' : '')) + '</span></div>';
        if (r.can_explain) {
            h += '<section class="ob-king" aria-label="Explain this failure"><div class="row g8 wrap between"><span class="row g6"><span class="ob-k-mark" aria-hidden="true">&#10022;</span><b class="fw5">Why did this happen?</b></span>'
                + (AI.ok ? '<button type="button" class="sl-btn ghost sm" data-ob-do="explain"><span aria-hidden="true">&#10022;</span>Explain with ' + esc(AI.name) + '</button>'
                    : '<button type="button" class="sl-btn ghost sm" disabled><span aria-hidden="true">&#10022;</span>Explain with ' + esc(AI.name) + '</button>') + '</div>'
                + (AI.ok ? '<div class="ob-k-out" data-ob-kout hidden></div><form class="ob-k-ask" data-ob-ask hidden><label class="sr-only" for="ob-k-q">Ask a follow-up question</label><input class="sl-in" id="ob-k-q" maxlength="300" placeholder="Ask a follow-up, e.g. how do I fix the SMTP password?"><button class="sl-btn ghost sm" type="submit">Ask</button></form>'
                    : '<p class="mut sm">' + esc(AI.message || (AI.name + ' is not set up yet.')) + (AI.setup_url ? ' <a href="' + esc(AI.setup_url) + '">Open the AI centre</a>' : '') + '</p>')
                + '</section>';
        }
        var facts = '';
        facts += fact(r.kind === 'in' ? 'From' : 'To', esc(r.to || '—'));
        if (r.uid) facts += fact('Customer', link(r.customer_link, r.name || ('Customer #' + r.uid)) + (r.profile_link ? ' · <a href="' + esc(r.profile_link) + '">Profile</a>' : ''));
        else if (r.name) facts += fact('Name', esc(r.name));
        facts += fact('Channel', esc(r.channel_label));
        facts += fact('Origin', esc(r.origin_label));
        facts += fact('Trigger', (r.trigger || r.source) ? '<span class="mono sm">' + esc(r.trigger || r.source) + '</span>' : '');
        if (r.campaign) facts += fact('Campaign', esc(r.campaign) + (r.campaign_link ? ' · <a href="' + esc(r.campaign_link) + '">Report</a>' : '') + (r.batch_link ? ' · <a href="' + esc(r.batch_link) + '">All its messages</a>' : ''));
        else if (r.batch) facts += fact('Batch', r.batch_link ? link(r.batch_link, r.batch) : esc(r.batch));
        if (r.record) facts += fact('About', r.record_link ? '<a class="mono" href="' + esc(r.record_link) + '">' + esc(r.record) + '</a>' : '<span class="mono">' + esc(r.record) + '</span>');
        if (r.kind_label) facts += fact('Type', esc(r.kind_label));
        facts += fact('Sent by', esc(r.sent_by || r.by || ''));
        if (r.queued_at) facts += fact('Queued', esc(r.queued_at));
        if (r.send_after_label && ['queued', 'scheduled', 'retrying'].indexOf(r.status) >= 0) facts += fact('Goes out', esc(r.send_after_label));
        if (r.sent_at) facts += fact('Sent', esc(r.sent_at));
        if (r.kind !== 'in') facts += fact('Attempts', esc(String(r.attempts || (r.attempt_list || []).length || 0)));
        facts += fact('Provider', esc(r.provider || ''));
        if (r.cost != null) facts += fact('Cost', money(r.cost, r.currency));
        if (r.opens != null) facts += fact('Opens / clicks', esc(r.opens + ' / ' + r.clicks));
        if (r.template_id) facts += fact('Template', '#' + esc(r.template_id));
        if (r.wa_template) facts += fact('WhatsApp template', '<span class="mono">' + esc(r.wa_template) + '</span>');
        h += '<dl class="ob-facts">' + facts + '</dl>';

        h += '<section class="ob-sec"><h3 class="ob-h3">Message</h3>';
        if (r.subject) h += '<p class="ob-subj"><span class="mut">Subject</span> ' + esc(r.subject) + '</p>';
        if (r.is_html) h += '<iframe class="ob-mail" title="The e-mail as sent" sandbox="" referrerpolicy="no-referrer" loading="lazy"></iframe>';
        else h += '<div class="ob-bubble ' + esc(r.kind === 'in' ? 'in' : (r.channel || 'all')) + '">' + (esc(r.body || '(no text)')) + '</div>';
        if (r.media_url) h += '<p class="sm"><a href="' + esc(r.media_url) + '" target="_blank" rel="noopener">Open the attachment</a></p>';
        h += '</section>';

        if (r.events && r.events.length) {
            h += '<section class="ob-sec"><h3 class="ob-h3">Opens and clicks</h3><ul class="ob-evs">' + r.events.map(function (e) {
                return '<li><span class="sl-pill ' + (e.event === 'click' ? 'transit' : 'plain') + '">' + esc(e.event === 'click' ? 'Click' : 'Open') + '</span><span class="mono sm">' + esc(e.when) + '</span>' + (e.url ? '<span class="ob-url">' + esc(e.url) + '</span>' : '') + '</li>';
            }).join('') + '</ul></section>';
        }
        var att = r.attempt_list || [];
        if (att.length) {
            h += '<section class="ob-sec"><h3 class="ob-h3">' + (att.length === 1 ? 'Attempt' : att.length + ' attempts') + '</h3><ol class="ob-atts">' + att.map(function (a) {
                return '<li><div class="row g8 wrap"><span class="sl-pill ' + esc(a.cls) + '">' + esc(a.status_label) + '</span><span class="mono sm">' + esc(a.when) + '</span>' + (a.provider ? '<span class="mut sm">' + esc(a.provider) + '</span>' : '') + '</div>'
                    + (a.detail ? '<p class="sm">' + esc(a.detail) + '</p>' : '')
                    + (a.response ? '<details class="ob-resp"><summary>Provider response</summary><pre>' + esc(a.response) + '</pre><button type="button" class="sl-btn ghost sm" data-sl-copy="' + esc(a.response) + '"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy</button></details>' : '')
                    + ((a.endpoint || a.ip) ? '<p class="mut sm">' + esc([a.endpoint, a.ip].filter(Boolean).join(' · ')) + '</p>' : '') + '</li>';
            }).join('') + '</ol></section>';
        } else if (r.kind === 'queue' && r.status !== 'sent' && !r.attempts) {
            h += '<p class="mut sm">No attempt yet' + (r.status === 'cancelled' ? '; it was cancelled before it went out.' : '; the worker sends it in its next pass.') + '</p>';
        }
        if (r.response_json) h += '<details class="ob-resp"><summary>Result per channel</summary><pre>' + esc(r.response_json) + '</pre></details>';
        if (r.note) h += '<p class="mut sm">' + esc(r.note) + '</p>';
        h += '<p class="mut sm ob-ref">Reference <span class="mono">' + esc(r.ref) + '</span></p>';
        body.innerHTML = h;
        var fr = body.querySelector('.ob-mail');
        if (fr) fr.srcdoc = r.body || '';

        var a = '';
        if (B.canAct !== false) {
            if (r.can_retry) a += '<button type="button" class="sl-btn" data-ob-do="retry"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Retry</button>';
            if (r.can_resend) a += '<button type="button" class="sl-btn" data-ob-do="resend"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send again</button>';
            if (r.can_cancel) a += '<button type="button" class="sl-btn ghost danger" data-ob-do="cancel"><i class="ic ic-sm i-ban" aria-hidden="true"></i>Cancel sending</button>';
        }
        if (r.kind === 'in' && r.record_link) a += '<a class="sl-btn ghost" href="' + esc(r.record_link) + '"><i class="ic ic-sm i-reply" aria-hidden="true"></i>Reply in the inbox</a>';
        else if (r.record_link) a += '<a class="sl-btn ghost" href="' + esc(r.record_link) + '"><i class="ic ic-sm i-ext" aria-hidden="true"></i>Open ' + esc(r.record || 'record') + '</a>';
        if (r.customer_link && !document.querySelector('[data-hs-uid="' + r.uid + '"]')) a += '<a class="sl-btn ghost" href="' + esc(r.customer_link) + '"><i class="ic ic-sm i-user" aria-hidden="true"></i>Customer history</a>';
        foot.innerHTML = a;
        foot.hidden = a === '';
    }

    function open(ref) {
        if (!ref) return;
        cur = ref;
        var my = ++seq;
        $('ob-d-title').textContent = 'Message';
        $('ob-d-sub').textContent = '';
        body.innerHTML = '<div class="ob-load"><span class="ob-spin" aria-hidden="true"></span>Loading…</div>';
        foot.hidden = true;
        dlg.hidden = false;
        setUrl(ref);
        fetch(AJ + '?action=detail&ref=' + encodeURIComponent(ref), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (res) { return res.json().catch(function () { return { ok: false, message: 'The server returned an unexpected response.' }; }); })
            .then(function (j) {
                if (my !== seq) return;
                if (!j.ok) { body.innerHTML = '<div class="sl-banner bad"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>' + esc(j.message || 'Could not load this message.') + '</span></div>'; return; }
                cur = j.row; render(j.row);
            })
            .catch(function () { if (my === seq) body.innerHTML = '<div class="sl-banner bad"><span>Network error. Please try again.</span></div>'; });
    }

    function act(what, btn, extra) {
        if (!cur || typeof cur !== 'object') return;
        if (what === 'cancel' && !confirm('Stop this message? It will not be sent.')) return;
        if (what === 'resend' && !confirm('Send the same text to ' + (cur.to || 'this address') + ' again?')) return;
        var data = Object.assign({ action: what, ref: cur.ref }, extra || {});
        btn.disabled = true; btn.classList.add('loading');
        var kout = body.querySelector('[data-ob-kout]');
        if (what === 'explain' && kout) { kout.hidden = false; kout.innerHTML = '<span class="mut sm">' + esc(AI.name) + ' is looking at this message…</span>'; }
        SL.post(AJ, data).then(function (j) {
            var ok = j.success === true || j.ok === true;
            if (what === 'explain') {
                if (!kout) return;
                if (ok) {
                    kout.innerHTML = '<p>' + esc(j.text) + '</p><span class="mut sm">' + esc((j.assistant || AI.name) + ' can be wrong. Check the provider response below before you act.') + '</span>';
                    var ask = body.querySelector('[data-ob-ask]'); if (ask) ask.hidden = false;
                } else {
                    kout.innerHTML = '<p class="ob-err">' + esc(j.message || 'No answer came back.') + (j.setup_url ? ' <a href="' + esc(j.setup_url) + '">Open the AI centre</a>' : '') + '</p>';
                }
                return;
            }
            toast(j.message || (ok ? 'Done' : 'Failed'), !ok);
            if (ok) setTimeout(function () {
                if (j.ref) { location.href = (B.outboxUrl || 'comms_outbox.php') + '?open=' + encodeURIComponent(j.ref); return; }
                var u = new URL(location.href); u.searchParams.set('open', cur.ref); location.href = u.toString();
            }, 400);
        }).catch(function (e) { toast(e.message || 'Network error', true); if (kout) kout.innerHTML = '<p class="ob-err">' + esc(e.message || 'Network error') + '</p>'; })
            .finally(function () { btn.disabled = false; btn.classList.remove('loading'); });
    }

    dlg.addEventListener('click', function (e) {
        var b = e.target.closest('[data-ob-do]');
        if (b) { e.preventDefault(); act(b.getAttribute('data-ob-do'), b); }
    });
    dlg.addEventListener('submit', function (e) {
        var f = e.target.closest('[data-ob-ask]');
        if (!f) return;
        e.preventDefault();
        var q = f.querySelector('input').value.trim();
        if (!q) return;
        act('explain', f.querySelector('button'), { question: q });
    });

    /* rows */
    document.addEventListener('click', function (e) {
        var o = e.target.closest('[data-ob-open]');
        if (o) { e.preventDefault(); open(o.getAttribute('data-ob-open')); return; }
        var r = e.target.closest('[data-ob-ref]');
        if (r && !e.target.closest('a,button,input,select,label,summary')) open(r.getAttribute('data-ob-ref'));
    });
    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('[data-ob-ref]')) { e.preventDefault(); open(e.target.getAttribute('data-ob-ref')); }
    });

    window.SLOutbox = { open: open };
    if (B.open) open(B.open);
})();
