/*
 * Customer history (views/sl/pages/CommsCustomers.php): customer search,
 * history filter, preference saving. The message drawer is dataJs/sl/comms_outbox.js.
 * Endpoint: ajax/comms/outbox_ajax.php (customers, save_prefs).
 */
(function () {
    'use strict';
    var B = window.SL_HISTORY || {};
    var AJ = B.ajax || 'ajax/comms/outbox_ajax.php';
    function $(id) { return document.getElementById(id); }
    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function ini(n) { var p = String(n || '?').trim().split(/\s+/); return ((p[0] || '?')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase(); }

    /* ---------------- search ---------------- */
    var q = $('hs-q'), box = $('hs-sug'), rows = [], act = -1, timer = null, seq = 0;
    function go(id) { location.href = (B.self || 'comms_customers.php') + '?user=' + encodeURIComponent(id); }
    function close() { box.hidden = true; q.setAttribute('aria-expanded', 'false'); act = -1; q.removeAttribute('aria-activedescendant'); }
    function paint() {
        if (!rows.length) { box.innerHTML = '<div class="hs-none mut sm">No customer matches “' + esc(q.value.trim()) + '”.</div>'; }
        else {
            box.innerHTML = rows.map(function (r, i) {
                return '<button type="button" class="hs-opt' + (i === act ? ' act' : '') + '" id="hs-o' + i + '" role="option" aria-selected="' + (i === act) + '" data-id="' + r.id + '">'
                    + '<span class="sl-av sm">' + esc(ini(r.name)) + '</span><span class="col"><span class="fw5">' + esc(r.name || ('Customer #' + r.id)) + '</span>'
                    + '<span class="sub">' + esc([r.locker, r.phone, r.email].filter(Boolean).join(' · ')) + '</span></span></button>';
            }).join('');
        }
        box.hidden = false; q.setAttribute('aria-expanded', 'true');
        if (act >= 0) q.setAttribute('aria-activedescendant', 'hs-o' + act); else q.removeAttribute('aria-activedescendant');
    }
    if (q) {
        q.addEventListener('input', function () {
            clearTimeout(timer);
            var v = q.value.trim();
            if (v.length < 2) { close(); return; }
            timer = setTimeout(function () {
                var my = ++seq;
                box.innerHTML = '<div class="hs-none mut sm">Searching…</div>'; box.hidden = false;
                SL.get(AJ, { action: 'customers', q: v }).then(function (j) {
                    if (my !== seq) return;
                    rows = (j && j.rows) || []; act = rows.length ? 0 : -1; paint();
                }).catch(function () { if (my === seq) { box.innerHTML = '<div class="hs-none bad sm">Search failed. Please try again.</div>'; box.hidden = false; } });
            }, 250);
        });
        q.addEventListener('keydown', function (e) {
            if (box.hidden || !rows.length) { if (e.key === 'Escape') close(); return; }
            if (e.key === 'ArrowDown') { e.preventDefault(); act = (act + 1) % rows.length; paint(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); act = (act - 1 + rows.length) % rows.length; paint(); }
            else if (e.key === 'Enter') { e.preventDefault(); if (rows[act]) go(rows[act].id); }
            else if (e.key === 'Escape') { e.stopPropagation(); close(); }
        });
        box.addEventListener('click', function (e) { var b = e.target.closest('[data-id]'); if (b) go(b.getAttribute('data-id')); });
        document.addEventListener('click', function (e) { if (!e.target.closest('.hs-find')) close(); });
        if (!B.uid) q.focus();
    }

    /* ---------------- history filter ---------------- */
    var seg = $('hs-filter');
    if (seg) {
        seg.addEventListener('click', function (e) {
            var b = e.target.closest('[data-hs-f]');
            if (!b) return;
            var f = b.getAttribute('data-hs-f'), shown = 0;
            seg.querySelectorAll('[data-hs-f]').forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', String(x === b)); });
            document.querySelectorAll('.hs-it').forEach(function (li) {
                var ok = !f || (f === 'failed' ? li.getAttribute('data-hs-prob') === '1' : li.getAttribute('data-hs-dir') === f);
                li.hidden = !ok; if (ok) shown++;
            });
            var em = document.querySelector('[data-hs-empty]'); if (em) em.hidden = shown > 0;
        });
    }

    /* ---------------- preferences ---------------- */
    var pf = $('hs-prefs');
    if (pf) {
        pf.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = pf.querySelector('[type="submit"]');
            if (!btn || btn.disabled) return;
            var data = {};
            Array.prototype.forEach.call(pf.elements, function (el) { if (el.name) data[el.name] = el.value; });
            if (data.opted_out_all === '1' && !confirm('Opt this customer out of all messages? Their waiting status updates, Support desk notices and delivery notices are cancelled.')) return;
            btn.disabled = true; btn.classList.add('loading');
            data.action = 'save_prefs';
            SL.post(AJ, data).then(function (j) {
                var ok = j.success === true || j.ok === true;
                SL.toast(j.message || (ok ? 'Saved' : 'Not saved'), !ok);
                if (ok) setTimeout(function () { location.reload(); }, 500);
            }).catch(function (err) { SL.toast(err.message || 'Network error', true); })
                .finally(function () { btn.disabled = false; btn.classList.remove('loading'); });
        });
    }
})();
