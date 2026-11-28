/* Send page (comms_send.php, views/sl/pages/CommsSend.php): one person, several people or a
 * campaign. Uses the shared editor kit (dataJs/sl/comms_editor.js: WhatsApp and SMS phones, the
 * e-mail canvas, Write with King) and posts to ajax/comms/send_ajax.php. Plain JS, needs swiftlane.js. */
(function () {
    'use strict';
    var AJAX = 'ajax/comms/send_ajax.php';
    var CH = { whatsapp: ['WhatsApp', 'i-wa'], sms: ['SMS', 'i-message'], email: ['E-mail', 'i-mail'], inapp: ['In-app', 'i-bell'] };
    var SHORT = { ok: 'Gets it', no_address: 'No address', opted_out: 'Opted out', no_consent: 'No consent', channel_off: 'Channel off', unsubscribed: 'Unsubscribed',
        contact_opted_out: 'Replied STOP', customer_contact: 'Reached as customer', not_found: 'Not found' };

    function $(id) { return document.getElementById(id); }
    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function num(n) { return (+n || 0).toLocaleString(); }
    function toast(m, bad) { if (window.SL && SL.toast) SL.toast(m, bad); }
    function post(data) { return SL.post(AJAX, data); }
    function debounce(fn, ms) { var t; return function () { var a = arguments, s = this; clearTimeout(t); t = setTimeout(function () { fn.apply(s, a); }, ms); }; }

    function start() {
        var bootEl = $('snd-boot');
        if (!bootEl || !window.SLCommsEditor) return;
        var B = JSON.parse(bootEl.textContent || '{}');
        var R = B.reasons || {};
        var S = {
            mode: B.mode || 'one', one: null, picked: (B.pre || []).slice(), channels: {}, when: 'now', campaignId: 0, campaignStatus: '',
            audience: { base: (B.audience && B.audience.base) || 'all', consolidation: (B.audience && B.audience.consolidation) || '', match: 'all', rules: [], customers: true, contacts: { on: false, source: '' } },
            reach: null, wa: { template: '', lang: 'en', params: '' }, cfilter: '', outbox: []
        };
        if (S.mode === 'one' && S.picked.length) { S.one = S.picked[0]; }
        (B.channel ? [B.channel] : ['whatsapp']).forEach(function (c) { S.channels[c] = true; });

        /* ---------------- editor ---------------- */
        var tags = {}; Object.keys(B.tags || {}).forEach(function (k) { tags[k] = B.tags[k]; });
        var cv = window.SL_EMAIL_CANVAS_CFG || {};
        var editor = SLCommsEditor.mount('#snd-editor', {
            key: 'campaign',
            channels: ['whatsapp', 'sms', 'email'],
            channel: ['whatsapp', 'sms', 'email'].filter(function (c) { return S.channels[c]; })[0] || 'whatsapp',
            enabled: { whatsapp: !!S.channels.whatsapp, sms: !!S.channels.sms, email: !!S.channels.email },
            values: { whatsapp: { body: 'Hi {first_name},\n\n' }, sms: { body: 'Hi {first_name}, ' }, email: { subject: '' } },
            tags: tags, tagSyntax: 'brace', sample: B.sample || {},
            contact: { name: (B.sample && B.sample.name) || 'Customer' }, sender: { sms: B.senderSms || 'SWIFTLANE' },
            media: B.media ? { uploadUrl: B.media, field: 'media' } : undefined,
            canvas: { tagSyntax: 'brace', saved: !!cv.ajax, ajax: cv.ajax || null, uploadUrl: cv.ajax || null, start: 'template', cfg: cv },
            king: { url: AJAX, params: { action: 'ai' }, name: B.ai.name, enabled: !!B.ai.ok, message: B.ai.message, setupUrl: B.ai.setup,
                payload: function () { return { name: S.mode === 'campaign' ? ($('snd-name') || {}).value || '' : '', goal: $('snd-goal').value, mode: S.mode, audience_label: audienceLabel() }; } },
            extra: { whatsapp: waExtra, email: mailExtra }
        });
        editor.on('change', function (d) {
            if (d.value && d.value.enabled != null && !!d.value.enabled !== !!S.channels[d.channel]) { setChannel(d.channel, !!d.value.enabled, true); }
            if (d.channel === 'sms') costLater();
        });

        function waExtra(box) {
            if (!B.waCloud) return;
            box.innerHTML = '<div class="snd-cloud col g8"><b class="fw5 sm">WhatsApp Cloud API template</b><span class="mut sm">Campaigns reach people outside the 24-hour window only as a template Meta approved. Direct messages use it when the window is closed.</span>' +
                '<div class="grid cols3 snd-g3"><label class="sl-field"><span class="sl-lab">Template name</span><input class="sl-in" data-wa="template" placeholder="december_offer"></label><label class="sl-field"><span class="sl-lab">Language</span><input class="sl-in" data-wa="lang" value="en"></label><label class="sl-field"><span class="sl-lab">Parameters</span><input class="sl-in" data-wa="params" placeholder="first_name,company"></label></div></div>';
            $$('[data-wa]', box).forEach(function (i) { i.value = S.wa[i.getAttribute('data-wa')] || i.value; i.addEventListener('input', function () { S.wa[i.getAttribute('data-wa')] = i.value.trim(); }); });
        }
        function mailExtra(box) {
            box.innerHTML = '<div class="snd-subj col g8"><div class="row g8 wrap"><button type="button" class="sl-btn ghost sm" data-subj-go' + (B.ai.ok ? '' : ' disabled') + '><span aria-hidden="true">&#10022;</span>Suggest subject lines with ' + esc(B.ai.name) + '</button>' +
                (B.ai.ok ? '' : '<span class="mut sm">' + esc(B.ai.message) + ' <a href="' + esc(B.ai.setup) + '">Open the AI centre</a></span>') + '</div><div class="row g6 wrap" data-subj-list></div></div>';
            var go = box.querySelector('[data-subj-go]'), list = box.querySelector('[data-subj-list]');
            go.addEventListener('click', function () {
                var v = editor.getValue('email');
                go.disabled = true; go.classList.add('loading'); list.innerHTML = '<span class="mut sm">' + esc(B.ai.name) + ' is thinking…</span>';
                post({ action: 'subjects', name: ($('snd-name') || {}).value || '', goal: $('snd-goal').value, text: (v.text || '').slice(0, 1500) }).then(function (j) {
                    if (!j.success) { list.innerHTML = '<span class="snd-err sm">' + esc(j.message || 'No subject lines came back.') + '</span>'; return; }
                    list.innerHTML = (j.subjects || []).map(function (s) { return '<button type="button" class="snd-sopt" data-subj="' + esc(s) + '">' + esc(s) + '</button>'; }).join('');
                }).catch(function (e) { list.innerHTML = '<span class="snd-err sm">' + esc(e.message || 'Network error') + '</span>'; })
                    .finally(function () { go.disabled = false; go.classList.remove('loading'); });
            });
            list.addEventListener('click', function (e) { var b = e.target.closest('[data-subj]'); if (!b) return; editor.setValue('email', { subject: b.getAttribute('data-subj') }); toast('Subject line used'); });
        }

        /* ---------------- channels ---------------- */
        function setChannel(c, on, fromEditor) {
            S.channels[c] = on;
            var b = document.querySelector('.snd-ch[data-ch="' + c + '"]');
            if (b) { b.classList.toggle('on', on); b.setAttribute('aria-pressed', String(on)); }
            if (c === 'inapp') { $('snd-inapp').hidden = !on; }
            else {
                if (!fromEditor) editor.setEnabled(c, on);
                if (on && !fromEditor) editor.show(c);
            }
            refreshReach(); notes();
        }
        $$('.snd-ch').forEach(function (b) { b.addEventListener('click', function () { setChannel(b.getAttribute('data-ch'), !S.channels[b.getAttribute('data-ch')]); }); });
        Object.keys(CH).forEach(function (c) { var b = document.querySelector('.snd-ch[data-ch="' + c + '"]'); if (b) { b.classList.toggle('on', !!S.channels[c]); b.setAttribute('aria-pressed', String(!!S.channels[c])); } });
        $('snd-inapp').hidden = !S.channels.inapp;
        function chosen() { return Object.keys(CH).filter(function (c) { return S.channels[c]; }); }

        /* in-app text */
        var inT = $('snd-inapp-t');
        function inPv() { $('snd-inapp-pv').innerHTML = SLCommsEditor.smsHtml(inT.value || 'Your message', B.sampleNow || B.sample); }
        inT.addEventListener('input', inPv); inPv();
        SLCommsEditor.tagChips($('snd-inapp-tags'), tags, { syntax: 'brace', target: function () { return inT; } });

        /* ---------------- mode ---------------- */
        function setMode(m) {
            if (m === 'campaign' && !B.canCampaign) m = 'several';
            S.mode = m;
            $$('.snd-mode [data-mode]').forEach(function (b) { var on = b.getAttribute('data-mode') === m; b.classList.toggle('on', on); b.setAttribute('aria-selected', String(on)); });
            $$('.snd-pane').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== m; });
            var camp = m === 'campaign';
            if ($('snd-draft')) { $('snd-draft').hidden = !camp; $('snd-new').hidden = !camp || (!S.campaignId && !S.locked); }
            $('snd-go').disabled = camp && !!S.locked;   // a started campaign stays locked until New campaign
            $('snd-test').hidden = false;
            sampleFromPick();
            refreshReach(); notes(); goLabel();
        }
        $$('.snd-mode [data-mode]').forEach(function (b) { b.addEventListener('click', function () { setMode(b.getAttribute('data-mode')); }); });

        /* ---------------- search (one / several) ---------------- */
        function suggest(input, box, onPick) {
            var rows = [], act = -1;
            var run = debounce(function () {
                var q = input.value.trim();
                if (q.length < 2) { box.hidden = true; box.innerHTML = ''; return; }
                post({ action: 'search', q: q }).then(function (j) {
                    rows = j.rows || []; act = -1;
                    box.innerHTML = rows.length ? rows.map(function (r, i) { return '<button type="button" role="option" class="snd-opt" data-i="' + i + '"><span class="snd-av" aria-hidden="true">' + esc(initials(r.name)) + '</span><span class="col grow"><b class="fw5">' + esc(r.name) + '</b><small class="mut">' + esc([r.locker, r.phone, r.email].filter(Boolean).join(' · ')) + '</small></span></button>'; }).join('')
                        : '<span class="mut sm snd-none">No customer matches “' + esc(q) + '”.</span>';
                    box.hidden = false;
                });
            }, 220);
            input.addEventListener('input', run);
            input.addEventListener('keydown', function (e) {
                var opts = $$('.snd-opt', box);
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { if (!opts.length) return; e.preventDefault(); act = (act + (e.key === 'ArrowDown' ? 1 : -1) + opts.length) % opts.length; opts.forEach(function (o, i) { o.classList.toggle('act', i === act); }); opts[act].scrollIntoView({ block: 'nearest' }); }
                else if (e.key === 'Enter') { e.preventDefault(); var r = rows[act >= 0 ? act : 0]; if (r) { onPick(r); input.value = ''; box.hidden = true; } }
                else if (e.key === 'Escape') { box.hidden = true; }
            });
            box.addEventListener('click', function (e) { var b = e.target.closest('[data-i]'); if (!b) return; onPick(rows[+b.getAttribute('data-i')]); input.value = ''; box.hidden = true; input.focus(); });
            document.addEventListener('click', function (e) { if (!box.contains(e.target) && e.target !== input) box.hidden = true; });
        }
        function initials(n) { return String(n || '').trim().split(/\s+/).map(function (w) { return w.charAt(0); }).join('').slice(0, 2).toUpperCase() || '?'; }
        suggest($('snd-q1'), $('snd-sug1'), function (r) { S.one = r; drawOne(); sampleFromPick(); refreshReach(); });
        suggest($('snd-q2'), $('snd-sug2'), function (r) { if (!S.picked.some(function (p) { return p.id === r.id; })) S.picked.push(r); drawChips(); refreshReach(); });
        function drawOne() {
            var box = $('snd-one');
            if (!S.one) { box.hidden = true; box.innerHTML = ''; return; }
            box.hidden = false;
            box.innerHTML = '<span class="snd-av lg" aria-hidden="true">' + esc(initials(S.one.name)) + '</span><span class="col grow" style="min-width:0"><b class="fw5">' + esc(S.one.name) + '</b><small class="mut">' + esc([S.one.locker, S.one.city].filter(Boolean).join(' · ')) + '</small><small class="mut">' + esc([S.one.phone, S.one.email].filter(Boolean).join(' · ')) + '</small></span>' +
                '<a class="sl-btn ghost sm" href="' + esc((B.consentUrl || 'comms_customers.php') + (B.consentUrl && B.consentUrl.indexOf('?') >= 0 ? '&' : '?') + 'user=' + S.one.id) + '">History</a><button type="button" class="sl-ib" data-one-x aria-label="Choose someone else"><i class="ic ic-sm i-x" aria-hidden="true"></i></button>';
            box.querySelector('[data-one-x]').addEventListener('click', function () { S.one = null; drawOne(); sampleFromPick(); refreshReach(); $('snd-q1').focus(); });
        }
        function drawChips() {
            var box = $('snd-chips');
            $$('.snd-chip', box).forEach(function (c) { c.remove(); });
            S.picked.forEach(function (p, i) {
                var s = document.createElement('span'); s.className = 'snd-chip';
                s.innerHTML = esc(p.name) + '<button type="button" data-rm="' + i + '" aria-label="Remove ' + esc(p.name) + '"><i class="ic ic-sm i-x" aria-hidden="true"></i></button>';
                box.insertBefore(s, $('snd-q2'));
            });
        }
        $('snd-chips').addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (b) { S.picked.splice(+b.getAttribute('data-rm'), 1); drawChips(); refreshReach(); } else if (e.target === this) $('snd-q2').focus(); });
        // A pasted list is resolved on the server: count it when typing pauses, and on leaving the box.
        var listSeen = $('snd-list').value;
        function listChanged() { if ($('snd-list').value !== listSeen) { listSeen = $('snd-list').value; refreshReach(); } }
        $('snd-list').addEventListener('input', debounce(listChanged, 1200));
        $('snd-list').addEventListener('blur', listChanged);
        function sampleFromPick() {
            var p = S.mode === 'one' ? S.one : null, s = Object.assign({}, B.sample || {});
            if (p) { s.name = p.name; s.first_name = String(p.name).split(/\s+/)[0]; s.locker = p.locker || s.locker; }
            editor.setSample(s);
            if (editor.phones.whatsapp) editor.phones.whatsapp.setContact(p ? p.name : (B.sample.name || 'Customer'), p ? (p.phone || 'online') : 'online');
            B.sampleNow = s; inT.dispatchEvent(new Event('input'));
        }

        /* ---------------- campaign audience ---------------- */
        var F = B.fields || {};
        function ruleRow(r) {
            var f = F[r.field] || { type: 'text' }, t = f.type;
            var ops = t === 'number' ? [['gte', 'at least'], ['lte', 'at most'], ['gt', 'over'], ['lt', 'under'], ['eq', 'exactly']] : t === 'text' ? [['contains', 'contains'], ['eq', 'is'], ['ne', 'is not']] : [['eq', 'is']];
            var val = t === 'bool' ? '<span class="sl-sel native"><select data-r="value" aria-label="Value"><option value="1"' + (r.value !== '0' ? ' selected' : '') + '>Yes</option><option value="0"' + (r.value === '0' ? ' selected' : '') + '>No</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span>'
                : t === 'channel' ? '<span class="sl-sel native"><select data-r="value" aria-label="Channel">' + ['whatsapp', 'sms', 'email'].map(function (c) { return '<option value="' + c + '"' + (r.value === c ? ' selected' : '') + '>' + CH[c][0] + '</option>'; }).join('') + '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span>'
                    : '<input class="sl-in" data-r="value" aria-label="Value" ' + (t === 'number' ? 'type="number" min="0" step="1"' : '') + ' value="' + esc(r.value) + '" placeholder="' + (t === 'number' ? '0' : 'e.g. Accra') + '">';
            return '<div class="snd-rule" data-rule><span class="sl-sel native"><select data-r="field" aria-label="Filter">' + Object.keys(F).map(function (k) { return '<option value="' + esc(k) + '"' + (k === r.field ? ' selected' : '') + '>' + esc(F[k].label) + '</option>'; }).join('') + '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span>' +
                (ops.length > 1 ? '<span class="sl-sel native"><select data-r="op" aria-label="Comparison">' + ops.map(function (o) { return '<option value="' + o[0] + '"' + (o[0] === r.op ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span>' : '<input type="hidden" data-r="op" value="eq">') +
                val + '<button type="button" class="sl-ib" data-rule-x aria-label="Remove filter"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></div>';
        }
        function drawRules() {
            var box = $('snd-rules'); if (!box) return;
            box.innerHTML = S.audience.rules.map(ruleRow).join('') || '<span class="mut sm">No filters: everyone in “' + esc(($('snd-base').selectedOptions[0] || {}).textContent || '') + '”.</span>';
        }
        function readRules() {
            S.audience.rules = $$('[data-rule]', $('snd-rules')).map(function (row) {
                var g = function (k) { var e = row.querySelector('[data-r="' + k + '"]'); return e ? e.value : ''; };
                return { field: g('field'), op: g('op'), value: g('value') };
            });
        }
        if ($('snd-rules')) {
            $('snd-addrule').addEventListener('click', function () { readRules(); S.audience.rules.push({ field: 'city', op: 'contains', value: '' }); drawRules(); var l = $$('[data-rule]', $('snd-rules')).pop(); if (l) l.querySelector('[data-r="value"]').focus(); });
            $('snd-rules').addEventListener('change', function (e) {
                if (e.target.getAttribute('data-r') === 'field') { readRules(); var i = $$('[data-rule]', this).indexOf(e.target.closest('[data-rule]')); var t = (F[S.audience.rules[i].field] || {}).type; S.audience.rules[i].op = t === 'number' ? 'gte' : t === 'text' ? 'contains' : 'eq'; S.audience.rules[i].value = t === 'bool' ? '1' : t === 'channel' ? 'whatsapp' : ''; drawRules(); }
                else readRules();
                refreshReach();
            });
            $('snd-rules').addEventListener('input', debounce(function (e) { if (e.target.getAttribute('data-r') === 'value') { readRules(); refreshReach(); } }, 450));
            $('snd-rules').addEventListener('click', function (e) { var b = e.target.closest('[data-rule-x]'); if (!b) return; readRules(); S.audience.rules.splice($$('[data-rule]', this).indexOf(b.closest('[data-rule]')), 1); drawRules(); refreshReach(); });
            $('snd-base').value = S.audience.base;
            if (S.audience.consolidation) $('snd-cons').value = S.audience.consolidation;
            var syncBase = function () { S.audience.base = $('snd-base').value; $('snd-cons-f').hidden = S.audience.base !== 'consolidation'; S.audience.consolidation = S.audience.base === 'consolidation' ? $('snd-cons').value : ''; drawRules(); refreshReach(); };
            $('snd-base').addEventListener('change', syncBase); $('snd-cons').addEventListener('change', syncBase);
            $('snd-cons-f').hidden = S.audience.base !== 'consolidation';
            $('snd-match').addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (!b) return; $$('button', this).forEach(function (x) { x.classList.toggle('on', x === b); }); S.audience.match = b.getAttribute('data-v'); refreshReach(); });
            if ($('snd-con')) {
                var syncC = function () { S.audience.contacts.on = $('snd-con').checked; S.audience.customers = $('snd-ccust').checked; $('snd-csrc-f').hidden = !S.audience.contacts.on; S.audience.contacts.source = $('snd-csrc').value; refreshReach(); };
                ['snd-con', 'snd-ccust', 'snd-csrc'].forEach(function (id) { $(id).addEventListener('change', syncC); });
            }
            $('snd-name').addEventListener('input', debounce(goLabel, 200));
            drawRules();
        }
        function audienceLabel() {
            if (S.mode === 'one') return S.one ? 'one customer, ' + S.one.name : 'one customer';
            if (S.mode === 'several') return 'a few chosen customers';
            return S.reach && S.reach.label ? S.reach.label : 'customers who agreed to receive offers';
        }

        /* ---------------- reach ---------------- */
        function reachData() {
            var d = { action: 'reach', mode: S.mode, channels: chosen().join(',') };
            if (S.mode === 'campaign') d.audience = JSON.stringify(S.audience);
            else if (S.mode === 'one') d.ids = S.one ? String(S.one.id) : '';
            else { d.ids = S.picked.map(function (p) { return p.id; }).join(','); d.list = $('snd-list').value; }
            return d;
        }
        var reqN = 0;
        var refreshReach = debounce(function () {
            var n = ++reqN; $('snd-spin').hidden = false;
            post(reachData()).then(function (j) {
                if (n !== reqN) return;
                if (!j.success) { $('snd-reach-ch').innerHTML = '<span class="snd-err sm">' + esc(j.message || 'Could not count the audience.') + '</span>'; return; }
                S.reach = j.reach; S.reach.label = j.label || ''; S.reach.kind = j.kind; S.reach.tooMany = !!j.too_many; S.sample = j.sample || [];
                drawReach(); notes(); goLabel(); cost();
            }).catch(function (e) { if (n === reqN) $('snd-reach-ch').innerHTML = '<span class="snd-err sm">' + esc(e.message || 'Network error') + '</span>'; })
                .finally(function () { if (n === reqN) $('snd-spin').hidden = true; });
        }, 250);
        function drawReach() {
            var r = S.reach || { total: 0, channels: {} }, chs = chosen();
            $('snd-total').textContent = num(r.total);
            $('snd-total-l').textContent = S.mode === 'campaign' ? (r.total === 1 ? 'person in this audience' : 'people in this audience') + (r.contacts ? ' (' + num(r.customers) + ' customers, ' + num(r.contacts) + ' WhatsApp contacts)' : '') : (r.total === 1 ? 'person chosen' : 'people chosen');
            $('snd-reach-ch').innerHTML = chs.length ? chs.map(function (c) {
                var row = r.channels[c] || { ok: 0 }, skip = Object.keys(row).filter(function (k) { return k !== 'ok' && row[k] > 0; });
                return '<div class="snd-rc"><div class="row between g8"><span class="row g6"><i class="ic ic-sm ' + CH[c][1] + '" aria-hidden="true"></i><b class="fw5">' + CH[c][0] + '</b></span><span><b>' + num(row.ok) + '</b> <span class="mut sm">will get it</span></span></div>' +
                    (skip.length ? '<ul class="snd-skip">' + skip.map(function (k) { return '<li><b>' + num(row[k]) + '</b> skipped · ' + esc(R[k] || k) + '</li>'; }).join('') + '</ul>' : '') + '</div>';
            }).join('') : '<span class="mut sm">Switch on at least one channel under Message.</span>';
            var sample = S.sample || [];
            $('snd-sample').innerHTML = sample.length ? '<span class="sl-lab">' + (S.mode === 'campaign' ? 'First ' + sample.length : 'Chosen') + '</span>' + sample.map(personRow).join('') : '';
            $('snd-all').hidden = !(r.total > sample.length);
            $('snd-all').lastChild.textContent = 'See everyone (' + num(r.total) + ')';
            if (r.bad && r.bad.length) $('snd-sample').insertAdjacentHTML('beforeend', '<span class="snd-err sm">Not recognised: ' + esc(r.bad.slice(0, 5).join(', ')) + (r.bad.length > 5 ? '…' : '') + '</span>');
        }
        function personRow(p) {
            var st = p.state || {};
            return '<div class="snd-pr"><span class="snd-av" aria-hidden="true">' + esc(initials(p.name)) + '</span><span class="col grow" style="min-width:0"><b class="fw5 snd-ell">' + esc(p.name) + '</b><small class="mut snd-ell">' + esc([p.locker, p.city, p.kind === 'address' ? 'not a customer' : ''].filter(Boolean).join(' · ')) + '</small></span><span class="row g4 wrap snd-st">' +
                Object.keys(st).map(function (c) { var ok = st[c] === 'ok'; return '<span class="snd-sc ' + (ok ? 'ok' : 'no') + '" title="' + esc(CH[c][0] + ': ' + (ok ? 'gets it' : (R[st[c]] || st[c]))) + '"><i class="ic ic-sm ' + CH[c][1] + '" aria-hidden="true"></i><span class="sr-only">' + esc(CH[c][0] + ': ' + (ok ? 'gets it' : (SHORT[st[c]] || st[c]))) + '</span></span>'; }).join('') + '</span></div>';
        }

        /* see everyone */
        var dl = { off: 0, q: '' };
        function loadPeople(reset) {
            if (reset) { dl.off = 0; $('snd-dlist').innerHTML = ''; }
            var d = reachData(); d.action = 'people'; d.offset = dl.off; d.q = dl.q;
            post(d).then(function (j) {
                if (!j.success) { $('snd-dlist').innerHTML = '<span class="snd-err sm">' + esc(j.message || 'Not loaded') + '</span>'; return; }
                $('snd-dlist').insertAdjacentHTML('beforeend', (j.rows || []).map(personRow).join('') || (dl.off ? '' : '<span class="mut sm">Nobody matches.</span>'));
                dl.off += (j.rows || []).length; $('snd-dmore').hidden = !j.more;
                $('snd-dcount').textContent = 'Showing ' + num(dl.off) + (S.reach && !dl.q ? ' of ' + num(S.reach.total) : '') + (dl.q ? ' matching “' + dl.q + '”' : '');
            });
        }
        $('snd-all').addEventListener('click', function () { $('snd-dlg').hidden = false; $('snd-dq').value = ''; dl.q = ''; loadPeople(true); });
        $('snd-dmore').addEventListener('click', function () { loadPeople(false); });
        $('snd-dq').addEventListener('input', debounce(function () { dl.q = $('snd-dq').value.trim(); loadPeople(true); }, 300));
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !$('snd-dlg').hidden) $('snd-dlg').hidden = true; });

        /* ---------------- cost ---------------- */
        var costLater = debounce(cost, 300);
        function cost() {
            var rt = B.rates || {}, r = S.reach, box = $('snd-cost');
            if (!rt.available || !r) { box.innerHTML = ''; return; }
            var lines = [], tot = 0;
            chosen().forEach(function (c) {
                if (c === 'inapp') return;
                var n = (r.channels[c] || {}).ok || 0, u = +rt[c] || 0, parts = 1;
                if (c === 'sms' && rt.sms_per_part) { var v = editor.getValue('sms'); parts = (v.count && v.count.parts) || 1; }
                var x = n * u * parts; tot += x;
                lines.push('<div><span>' + CH[c][0] + ' · ' + num(n) + (parts > 1 ? ' × ' + parts + ' parts' : '') + '</span><span>' + esc(rt.currency) + ' ' + x.toFixed(2) + '</span></div>');
            });
            box.innerHTML = lines.length ? '<div class="sl-kv">' + lines.join('') + '<div><b>Estimated cost</b><b>' + esc(rt.currency) + ' ' + tot.toFixed(2) + '</b></div></div>' : '';
        }

        /* ---------------- notes: consent, approval, timing ---------------- */
        function notes() {
            var n = [], camp = S.mode === 'campaign', chs = chosen();
            if (camp) {
                n.push(['info', 'Campaigns are marketing: a customer only gets one on a channel where they agreed to receive offers (their message preferences). E-mail addresses that unsubscribed are left out, and an opt-out line is added automatically.']);
                if (S.audience.contacts.on) n.push(['info', 'WhatsApp contacts saved from chats and groups count as reachable (your choice); anyone who replied STOP is left out, and a contact who is also a customer follows the customer\'s own choices.']);
                if (B.quiet && B.quiet[0] && B.quiet[0] !== B.quiet[1]) n.push(['info', 'Campaign messages wait for the end of quiet hours (' + B.quiet[0] + '–' + B.quiet[1] + ') and go out in batches of 500.']);
                if (B.needsApproval) n.push(B.selfApprove ? ['info', 'Approval is on. As a super administrator, your campaigns go out without a second approval.'] : ['warn', 'Approval is on: an administrator other than you approves the campaign before it goes out.']);
                if (S.reach && S.reach.total && Object.keys(S.reach.channels).every(function (c) { return c === 'inapp' || !(S.reach.channels[c] || {}).ok; }) && chs.some(function (c) { return c !== 'inapp'; })) n.push(['warn', 'Nobody in this audience has agreed to receive offers on the chosen channels yet. Customers give consent on their message preferences page, or staff record it in Customer history.']);
            } else {
                n.push(['info', 'Direct messages are service messages: they go to everyone chosen, except on a channel the person switched off or when they opted out of all messages.']);
                if (S.reach && S.reach.tooMany) n.push(['bad', 'More than ' + B.directMax + ' people: send it as a campaign instead.']);
            }
            if (B.waCloud && chs.indexOf('whatsapp') >= 0) n.push(['warn', 'WhatsApp uses the Cloud API: outside the 24-hour window only an approved template is delivered (fields under the WhatsApp message).']);
            chs.forEach(function (c) { var h = (B.health || {})[c]; if (h && !h.ok) n.push(['bad', CH[c][0] + ' is not set up (' + h.state + '): its messages will fail until it is connected in Settings.']); });
            if (chs.indexOf('whatsapp') >= 0 && editor.getValue('whatsapp').media) n.push(['info', 'The WhatsApp attachment is sent as a link under the text.']);
            $('snd-notes').innerHTML = n.map(function (x) { return '<div class="sl-banner ' + x[0] + '"><i class="ic ic-sm ' + (x[0] === 'bad' || x[0] === 'warn' ? 'i-alert' : 'i-check') + '" aria-hidden="true"></i><span>' + esc(x[1]) + '</span></div>'; }).join('');
            $('snd-kind').textContent = camp ? 'Marketing message: consent rules apply.' : 'Service message.';
        }

        /* ---------------- when ---------------- */
        $('snd-whenseg').addEventListener('click', function (e) {
            var b = e.target.closest('[data-v]'); if (!b) return;
            $$('button', this).forEach(function (x) { x.classList.toggle('on', x === b); });
            S.when = b.getAttribute('data-v'); $('snd-at').hidden = S.when !== 'later';
            if (S.when === 'later' && !$('snd-at').value) { var d = new Date(Date.now() + 3600e3); d.setMinutes(0, 0, 0); $('snd-at').value = new Date(d.getTime() - d.getTimezoneOffset() * 6e4).toISOString().slice(0, 16); }
            if (S.when === 'later') $('snd-at').focus();
            goLabel();
        });
        function goLabel() {
            var b = $('snd-go').querySelector('span'), r = S.reach, later = S.when === 'later';
            if (S.mode === 'campaign') {
                b.textContent = B.needsApproval && !B.selfApprove ? 'Send for approval' : later ? 'Schedule campaign' : 'Start campaign';
            } else {
                var n = r ? r.total : 0;
                b.textContent = (later ? 'Schedule for ' : 'Send to ') + num(n) + ' ' + (n === 1 ? 'person' : 'people');
            }
        }

        /* ---------------- collect and send ---------------- */
        function collect() {
            var chs = chosen(), b = {}, cur = editor.channel();
            if (chs.indexOf('email') >= 0 && editor.getValue('email').untouched) { editor.show('email'); editor.show(cur); }
            var v = editor.getValues();
            if (S.channels.whatsapp) b.whatsapp = { body: v.whatsapp.body || '', media: v.whatsapp.media || null };
            if (S.channels.sms) b.sms = { body: v.sms.body || '' };
            if (S.channels.email) b.email = { subject: v.email.subject || '', preheader: v.email.preheader || '', html: v.email.html || '', design_json: v.email.design_json || '' };
            if (S.channels.inapp) b.inapp = { body: inT.value };
            b._meta = { goal: $('snd-goal').value };
            var d = { channels: chs.join(','), bodies: JSON.stringify(b), when: S.when, at: $('snd-at').value, wa_template: S.wa.template, wa_lang: S.wa.lang, wa_params: S.wa.params };
            return d;
        }
        function result(html, kind) { var r = $('snd-result'); r.hidden = !html; r.className = 'snd-result ' + (kind || ''); r.innerHTML = html || ''; }
        function busy(btn, on) { btn.disabled = on; btn.classList.toggle('loading', on); }
        $('snd-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = $('snd-go'), d = collect();
            if (!chosen().length) { toast('Switch on at least one channel.', true); return; }
            if (S.mode === 'campaign') {
                if (!$('snd-name').value.trim()) { toast('Give the campaign a name.', true); $('snd-name').focus(); return; }
                d.action = 'campaign_submit'; d.id = S.campaignId || ''; d.name = $('snd-name').value; d.audience = JSON.stringify(S.audience);
                var ppl = S.reach ? (S.reach.people || 0) : 0;
                if (S.reach && !ppl) { var why = 'Nobody in this audience can receive it on the chosen channels. See the reasons under Who will get it.'; result(esc(why), 'bad'); toast(why, true); return; }
                if (!confirm((B.needsApproval && !B.selfApprove ? 'Send this campaign for approval? About ' + num(ppl) + ' people can receive it.' : (S.when === 'later' ? 'Schedule' : 'Start') + ' this campaign? About ' + num(ppl) + ' people can receive it' + (S.reach && S.reach.total > ppl ? ' (' + num(S.reach.total - ppl) + ' in the audience are skipped)' : '') + '.') + ' People who did not agree to offers are not sent anything.')) return;
            } else {
                var n = S.reach ? S.reach.total : 0;
                if (!n) { toast(S.mode === 'one' ? 'Choose a customer first.' : 'Add at least one person.', true); return; }
                d.action = 'send';
                if (S.mode === 'one') d.ids = String(S.one.id);
                else { d.ids = S.picked.map(function (p) { return p.id; }).join(','); d.list = $('snd-list').value; }
                if (n > 1 && !confirm((S.when === 'later' ? 'Schedule' : 'Send') + ' this message to ' + n + ' people?')) return;
            }
            busy(btn, true); result('');
            post(d).then(function (j) {
                if (!j.success) { result(esc(j.message || 'Not sent.'), 'bad'); toast(j.message || 'Not sent.', true); if (j.id) { S.campaignId = j.id; if ($('snd-new')) $('snd-new').hidden = false; loadCampaigns(); } return; }
                toast(j.message || 'Done');
                if (S.mode === 'campaign') {
                    // Locked until "New campaign": a second press must not start the same campaign again.
                    result('<b>' + esc(j.message) + '</b> <a href="#campaigns" data-snd-jump="campaigns">See it in the campaign list</a><span class="sm">To send another, choose New campaign.</span>', 'ok');
                    S.campaignId = 0; S.campaignStatus = ''; editor.markClean(); lockForm(true); loadCampaigns();
                } else {
                    var skipped = Object.keys(j.skipped || {}).map(function (k) { return j.skipped[k] + ' · ' + k; });
                    result('<b>' + esc(j.message) + '</b>' + (skipped.length ? '<span class="sm">Not sent: ' + esc(skipped.join('; ')) + '</span>' : '') + '<span class="sm" data-st></span><a class="sm" href="' + esc(B.outboxUrl) + '">Open the Outbox</a>', 'ok');
                    S.outbox = j.outbox_ids || []; editor.markClean();
                    if (S.outbox.length && !j.scheduled) poll(0);
                }
            }).catch(function (err) { result(esc(err.message || 'Network error'), 'bad'); }).finally(function () { busy(btn, false); if (S.locked && S.mode === 'campaign') btn.disabled = true; });
        });
        function lockForm(on) {
            S.locked = on;
            $('snd-go').disabled = on && S.mode === 'campaign'; if ($('snd-draft')) $('snd-draft').disabled = on;
            if ($('snd-new')) $('snd-new').hidden = !on && !S.campaignId;
            $('snd-form').classList.toggle('snd-locked', on);
        }
        function poll(i) {
            setTimeout(function () {
                post({ action: 'status', outbox_ids: S.outbox.join(',') }).then(function (j) {
                    var c = j.counts || {}, el = document.querySelector('#snd-result [data-st]'); if (!el) return;
                    var wait = (c.queued || 0) + (c.sending || 0), errs = j.errors || [];
                    el.textContent = [c.sent ? c.sent + ' sent' : '', c.failed ? c.failed + ' failed' : '', wait ? wait + (errs.length ? ' not delivered yet (retrying)' : ' on the way') : '', c.cancelled ? c.cancelled + ' cancelled' : ''].filter(Boolean).join(' · ') + (errs.length ? ' — ' + errs.join('; ') : '');
                    if (wait && i < 5) poll(i + 1);
                });
            }, i ? 3000 : 1500);
        }
        $('snd-test').addEventListener('click', function () {
            var btn = this, ch = S.channels.inapp && !S.channels[editor.channel()] ? 'inapp' : editor.channel();
            if (!S.channels[ch]) { toast('Switch ' + CH[ch][0] + ' on first, or open the tab of a channel that is on.', true); return; }
            var d = collect(); d.action = 'test'; d.channel = ch;
            busy(btn, true);
            post(d).then(function (j) { toast(j.message || (j.success ? 'Test sent' : 'Test not sent'), !j.success); result(esc(j.message || ''), j.success ? 'ok' : 'bad'); })
                .catch(function (err) { toast(err.message || 'Network error', true); }).finally(function () { busy(btn, false); });
        });
        if ($('snd-draft')) {
            $('snd-draft').addEventListener('click', function () {
                var btn = this, d = collect();
                if (!$('snd-name').value.trim()) { toast('Give the campaign a name.', true); $('snd-name').focus(); return; }
                d.action = 'campaign_save'; d.id = S.campaignId || ''; d.name = $('snd-name').value; d.audience = JSON.stringify(S.audience);
                busy(btn, true);
                post(d).then(function (j) { toast(j.message || (j.success ? 'Saved' : 'Not saved'), !j.success); if (j.success) { S.campaignId = j.id; $('snd-new').hidden = false; editor.markClean(); loadCampaigns(); } })
                    .catch(function (err) { toast(err.message || 'Network error', true); }).finally(function () { busy(btn, false); });
            });
            $('snd-new').addEventListener('click', function () {
                if (editor.isDirty() && !confirm('Start a new campaign? Unsaved changes are lost.')) return;
                location.href = location.pathname + '?mode=campaign';
            });
        }

        /* ---------------- campaign list ---------------- */
        function loadCampaigns() {
            if (!$('snd-clist')) return Promise.resolve();
            return post({ action: 'campaigns', status: S.cfilter }).then(function (j) { if (j.success) drawCampaigns(j.rows || []); });
        }
        function drawCampaigns(rows) {
            var tb = $('snd-clist'); if (!tb) return;
            $('snd-cempty').hidden = rows.length > 0;
            tb.innerHTML = rows.map(function (c) {
                var acts = [], started = ['sending', 'sent', 'cancelled'].indexOf(c.status) >= 0 || c.queued > 0;
                if (started || c.status === 'sent') acts.push('<a class="sl-btn ghost sm" href="' + esc(B.reportUrl) + '?batch=' + encodeURIComponent(c.batch) + '">Report</a>');
                if (['draft', 'pending', 'scheduled'].indexOf(c.status) >= 0 && !c.queued) acts.push('<button type="button" class="sl-btn ghost sm" data-cact="edit" data-id="' + c.id + '">' + (c.status === 'draft' ? 'Edit' : 'Open') + '</button>');
                if (c.status === 'pending' && B.isAdmin && (c.created_by !== B.uid || B.rank >= 4)) acts.push('<button type="button" class="sl-btn sm" data-cact="campaign_approve" data-id="' + c.id + '">Approve</button>');
                if ((c.status === 'pending' || c.status === 'scheduled') && !c.queued) acts.push('<button type="button" class="sl-btn ghost sm" data-cact="campaign_unschedule" data-id="' + c.id + '">Back to draft</button>');
                acts.push('<button type="button" class="sl-btn ghost sm" data-cact="campaign_duplicate" data-id="' + c.id + '">Duplicate</button>');
                if (B.isAdmin && c.status !== 'draft' && c.status !== 'cancelled' && (c.status !== 'sent' || c.waiting > 0)) acts.push('<button type="button" class="sl-btn ghost sm snd-danger" data-cact="campaign_cancel" data-id="' + c.id + '">Cancel</button>');
                if (c.status === 'draft') acts.push('<button type="button" class="sl-ib" data-cact="campaign_delete" data-id="' + c.id + '" aria-label="Delete draft ' + esc(c.name) + '"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button>');
                var when = c.status === 'scheduled' && c.scheduled_at ? 'Goes out ' + c.scheduled_at.slice(0, 16) : c.launched_at ? 'Started ' + c.launched_at.slice(0, 16) : 'Created ' + c.created_at.slice(0, 16);
                return '<tr><td class="snd-cn"><b class="fw5">' + esc(c.name) + '</b><span class="sub snd-ell" title="' + esc(c.audience) + '">' + esc(c.audience || '') + '</span><span class="sub">' + esc(when) + (c.created_by_name ? ' · by ' + esc(c.created_by_name) : '') + (c.approved_by_name && c.status !== 'draft' ? ' · approved by ' + esc(c.approved_by_name) : '') + '</span></td>' +
                    '<td><span class="sl-pill ' + esc(c.pill) + '">' + esc(c.status_label) + '</span></td>' +
                    '<td><span class="row g4">' + c.channels.map(function (ch) { return CH[ch] ? '<i class="ic ic-sm ' + CH[ch][1] + '" title="' + CH[ch][0] + '" aria-label="' + CH[ch][0] + '"></i>' : ''; }).join('') + '</span></td>' +
                    '<td class="num">' + num(c.queued) + (c.excluded ? '<span class="sub" title="Skipped: no consent, no address or opted out">' + num(c.excluded) + ' skipped</span>' : '') + '</td><td class="num">' + num(c.sent) + (c.waiting ? '<span class="sub">' + num(c.waiting) + ' waiting</span>' : '') + '</td><td class="num">' + num(c.failed) + '</td><td class="num">' + (c.channels.indexOf('email') >= 0 ? num(c.opens) : '—') + '</td><td class="num">' + (c.channels.indexOf('email') >= 0 ? num(c.clicks) : '—') + '</td>' +
                    '<td><span class="row g6 snd-cacts">' + acts.join('') + '</span></td></tr>';
            }).join('');
        }
        if ($('snd-clist')) {
            drawCampaigns(B.campaigns || []);
            $('snd-cf').addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (!b) return; $$('button', this).forEach(function (x) { x.classList.toggle('on', x === b); }); S.cfilter = b.getAttribute('data-v'); loadCampaigns(); });
            $('snd-clist').addEventListener('click', function (e) {
                var b = e.target.closest('[data-cact]'); if (!b) return;
                var act = b.getAttribute('data-cact'), id = b.getAttribute('data-id');
                if (act === 'edit') { openCampaign(+id); return; }
                var ask = { campaign_cancel: 'Cancel this campaign? Messages not yet sent will not go out.', campaign_delete: 'Delete this draft?', campaign_approve: 'Approve this campaign? It goes out at its time (now when none is set).' }[act];
                if (ask && !confirm(ask)) return;
                busy(b, true);
                post({ action: act, id: id }).then(function (j) {
                    toast(j.message || (j.success ? 'Done' : 'Failed'), !j.success);
                    if (!j.success) return;
                    if (act === 'campaign_delete' && +id === S.campaignId) { editor.markClean(); history.replaceState(null, '', location.pathname + '?mode=campaign#campaigns'); location.reload(); return; }
                    if (act === 'campaign_duplicate' && j.id) openCampaign(j.id);
                    loadCampaigns();
                })
                    .catch(function (err) { toast(err.message || 'Network error', true); }).finally(function () { busy(b, false); });
            });
            setInterval(function () { if (document.visibilityState === 'visible' && $$('#snd-clist .sl-pill.sorting, #snd-clist .sl-pill.transit').length) loadCampaigns(); }, 15000);
        }
        function openCampaign(id) {
            post({ action: 'campaign_get', id: id }).then(function (j) {
                if (!j.success) { toast(j.message || 'Not found', true); return; }
                var c = j.campaign;
                if (!c.editable) { toast('This campaign has started: duplicate it to change it.', true); return; }
                S.campaignId = c.id; S.campaignStatus = c.status;
                setMode('campaign');
                $('snd-name').value = c.name; $('snd-goal').value = c.goal || '';
                S.audience = Object.assign({ base: 'all', consolidation: '', match: 'all', rules: [], customers: true, contacts: { on: false, source: '' } }, c.audience || {});
                $('snd-base').value = S.audience.base; if (S.audience.consolidation) $('snd-cons').value = S.audience.consolidation;
                $('snd-cons-f').hidden = S.audience.base !== 'consolidation';
                $$('#snd-match button').forEach(function (x) { x.classList.toggle('on', x.getAttribute('data-v') === S.audience.match); });
                if ($('snd-con')) { $('snd-con').checked = !!S.audience.contacts.on; $('snd-ccust').checked = S.audience.customers !== false; $('snd-csrc').value = S.audience.contacts.source || ''; $('snd-csrc-f').hidden = !S.audience.contacts.on; }
                drawRules();
                var b = c.bodies || {};
                Object.keys(CH).forEach(function (ch) { var on = c.channels.indexOf(ch) >= 0; S.channels[ch] = on; var el = document.querySelector('.snd-ch[data-ch="' + ch + '"]'); if (el) { el.classList.toggle('on', on); el.setAttribute('aria-pressed', String(on)); } if (ch !== 'inapp') editor.setEnabled(ch, on); });
                $('snd-inapp').hidden = !S.channels.inapp;
                if (b.whatsapp) editor.setValue('whatsapp', { body: b.whatsapp.body || '', media: b.whatsapp.media || null });
                if (b.sms) editor.setValue('sms', { body: b.sms.body || '' });
                if (b.email) {
                    var ev = { subject: b.email.subject || '', preheader: b.email.preheader || '' };
                    if (b.email.design_json) ev.design_json = b.email.design_json;
                    else if (b.email.html) {
                        // Written before the builder: its text comes in as a text block so nothing is lost.
                        var tmp = new DOMParser().parseFromString(String(b.email.html).replace(/<(br|\/p|\/div|\/h[1-6]|\/tr)[^>]*>/gi, '$&\n'), 'text/html').body;
                        ev.blocks = [{ type: 'text', text: (tmp.textContent || '').replace(/\n{3,}/g, '\n\n').trim() }];
                        toast('This e-mail was written before the builder: its text was loaded as a text block. Check the layout before sending.');
                    }
                    editor.setValue('email', ev);
                }
                inT.value = (b.inapp && b.inapp.body) || ''; inPv();
                S.wa = { template: c.wa_template || '', lang: c.wa_lang || 'en', params: c.wa_params || '' };
                $$('[data-wa]').forEach(function (i) { i.value = S.wa[i.getAttribute('data-wa')] || ''; });
                if (c.scheduled_at) { $$('#snd-whenseg button')[1].click(); $('snd-at').value = c.scheduled_at.replace(' ', 'T'); }
                else if (S.when !== 'now') $$('#snd-whenseg button')[0].click();
                lockForm(false);
                var first = c.channels.filter(function (x) { return x !== 'inapp'; })[0]; if (first) editor.show(first);
                editor.markClean(); $('snd-new').hidden = false;
                result(c.status === 'draft' ? 'Editing draft “' + esc(c.name) + '”.' : 'Editing “' + esc(c.name) + '”: saving or sending puts it back through ' + (B.needsApproval ? 'approval' : 'the send step') + '.', '');
                $('snd-who').scrollIntoView({ behavior: 'smooth', block: 'start' });
                refreshReach();
            });
        }
        document.addEventListener('click', function (e) { var a = e.target.closest('[data-snd-jump]'); if (!a) return; var t = $(a.getAttribute('data-snd-jump')); if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); } });
        window.addEventListener('beforeunload', function (e) { if (editor.isDirty() && !$('snd-go').disabled) { e.preventDefault(); e.returnValue = ''; } });

        /* ---------------- first paint ---------------- */
        drawOne(); drawChips(); setMode(S.mode);
        if (B.edit) openCampaign(B.edit);
        if (location.hash === '#campaigns' && $('campaigns')) setTimeout(function () { $('campaigns').scrollIntoView({ block: 'start' }); }, 50);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
