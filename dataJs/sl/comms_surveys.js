/* Communications > Surveys (views/sl/pages/CommsSurveys.php).
 * Needs swiftlane.js (SL.post, SL.toast, dialogs, tabs, sl-toggle) and, for the
 * invitation text, dataJs/sl/email_builder.js + dataJs/sl/comms_editor.js (SLCommsEditor).
 * Endpoints: ajax/comms/surveys_ajax.php (forms, King questions, one survey's answers),
 * ajax/comms/automation_ajax.php (the "survey" invitation text, key survey).
 *
 * - the survey builder: name, intro, questions (rating 1-5, recommend 0-10,
 *   one choice, several choices, free text; required; order), thank-you line,
 *   when it is sent (statuses, delay, once per N days), channels, live preview,
 *   Write questions with King
 * - "All answers" of one survey
 * - the invitation message editor (shared editor drawer)
 */
(function () {
    'use strict';
    function start() {
        var cfgEl = document.getElementById('sv-cfg');
        var root = document.getElementById('sv-root');
        if (!cfgEl || !root || !window.SL) return;
        var cfg; try { cfg = JSON.parse(cfgEl.textContent); } catch (e) { return; }
        var AJAX = cfg.ajax, AI = cfg.ai || {}, KING = AI.name || 'King', CAN = !!cfg.canEdit;
        var TYPES = cfg.types || {}, CHS = cfg.channels || {}, INV = cfg.invite || {};
        var CHI = { inapp: 'i-bell', whatsapp: 'i-wa', sms: 'i-message', email: 'i-mail' };
        window.SL_KING_NAME = KING;
        var FLASH = 'sv-flash';

        // A message carried across the reload that follows a save, copy, delete or archive.
        function flashLater(m, warn) { try { sessionStorage.setItem(FLASH, m); if (warn) sessionStorage.setItem(FLASH + '-w', warn); } catch (e) {} }
        (function () {
            var m = null, w = null; try { m = sessionStorage.getItem(FLASH); w = sessionStorage.getItem(FLASH + '-w'); sessionStorage.removeItem(FLASH); sessionStorage.removeItem(FLASH + '-w'); } catch (e) {}
            if (m) setTimeout(function () { SL.toast(m); }, 150);
            // Two active surveys on the same status: saved, but say which one customers get.
            var pf = w && document.getElementById('sv-p-forms');
            if (pf) { var b = document.createElement('div'); b.className = 'sl-banner'; b.setAttribute('role', 'alert'); b.setAttribute('data-sv-overlap', ''); b.innerHTML = '<i class="ic ic-sm i-alert" aria-hidden="true"></i><span></span>'; b.lastChild.textContent = w; pf.insertBefore(b, pf.firstChild); }
        })();

        function $(s, r) { return (r || document).querySelector(s); }
        function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
        function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
        function toast(m, bad) { SL.toast(m, bad); }
        function post(data) { return SL.post(AJAX, data); }
        function clone(o) { return JSON.parse(JSON.stringify(o)); }

        /* ================================================================ builder */
        var dlg = document.getElementById('sv-builder'), box = dlg ? $('[data-sv-builder]', dlg) : null;
        var st = null, start0 = '', defaults = (cfg.statuses || []).filter(function (s) { return s.default; });

        function blank() {
            return { id: 0, name: '', active: true, intro: 'Tell us in a few seconds how it went.', thanks: 'Thank you! Your feedback helps us serve you better.',
                questions: [{ key: 'q1', type: 'rating', label: 'How would you rate your experience with this delivery?', required: true, options: [] },
                    { key: 'q2', type: 'text', label: 'Anything you would like to tell us?', required: false, options: [] }],
                statuses: [], delay_hours: 2, channels: ['inapp', 'whatsapp'], frequency_days: 7 };
        }
        function dirty() { return st && JSON.stringify(st) !== start0; }
        function nextKey() { var n = 1, used = {}; st.questions.forEach(function (q) { used[q.key] = 1; }); while (used['q' + n]) n++; return 'q' + n; }

        function open(form) {
            if (!dlg) return;
            st = clone(form); st.statuses = st.statuses || []; st.channels = st.channels || [];
            st.questions.forEach(function (q) { q.options = q.options || []; q.required = !!q.required; });
            start0 = JSON.stringify(st);
            draw();
            dlg.hidden = false;
        }
        function close(force) {
            if (!force && CAN && dirty() && !confirm('Close the survey without saving your changes?')) return false;
            st = null; dlg.hidden = true; return true;
        }

        function draw() {
            var ro = CAN ? '' : ' disabled';
            var chosen = st.statuses.length > 0;
            box.innerHTML =
                '<div class="sv-bh"><div class="col grow"><h2 id="sv-b-title">' + (st.id ? (CAN ? 'Edit survey' : 'Survey') : 'New survey') + '</h2>' +
                '<span class="mut sm">' + (CAN ? 'Write the questions, then choose when and how it is sent. The preview shows what customers see.' : 'Only an administrator changes surveys.') + '</span></div>' +
                '<button type="button" class="sl-btn ghost sm sv-bx" data-b-close aria-label="Close">&times;</button></div>' +
                '<div class="sv-bgrid"><div class="sv-bmain">' +
                '<label class="sl-field"><span class="sl-lab">Name</span><input class="sl-in" data-b="name" maxlength="120" value="' + esc(st.name) + '" placeholder="How did we do?"' + ro + '></label>' +
                '<label class="sl-field"><span class="sl-lab">Intro <span class="mut">(shown above the questions)</span></span><textarea class="sl-in" data-b="intro" rows="2" maxlength="500"' + ro + '>' + esc(st.intro) + '</textarea></label>' +
                kingBox() +
                '<div class="sv-bqhd"><h3>Questions</h3><span class="mut sm" data-b-qn></span></div>' +
                '<ol class="sv-bqs" data-b-qs></ol>' +
                (CAN ? '<div class="sv-badd" role="group" aria-label="Add a question"><span class="mut sm">Add</span>' + Object.keys(TYPES).map(function (t) {
                    return '<button type="button" class="sl-btn ghost sm" data-b-add="' + t + '" title="' + esc(TYPES[t][1]) + '"><i class="ic ic-sm i-plus" aria-hidden="true"></i>' + esc(TYPES[t][0]) + '</button>';
                }).join('') + '</div>' : '') +
                '<label class="sl-field"><span class="sl-lab">Thank-you line <span class="mut">(after they answer)</span></span><input class="sl-in" data-b="thanks" maxlength="500" value="' + esc(st.thanks) + '"' + ro + '></label>' +
                '</div><aside class="sv-bside">' +
                '<section class="sv-bsec"><h3>When it is sent</h3>' +
                '<div class="sv-radio" role="radiogroup" aria-label="Statuses">' +
                '<label><input type="radio" name="sv-bst" value="default"' + (chosen ? '' : ' checked') + ro + '><span>After delivered or picked up <span class="mut sm">(' + esc(defaults.map(function (s) { return s.label; }).join(', ') || 'none found') + ')</span></span></label>' +
                '<label><input type="radio" name="sv-bst" value="pick"' + (chosen ? ' checked' : '') + ro + '><span>After the statuses I choose</span></label></div>' +
                '<div class="sv-stlist" data-b-stlist' + (chosen ? '' : ' hidden') + '>' + (cfg.statuses || []).map(function (s) {
                    return '<label' + (s.blocked ? ' class="sv-stblk"' : '') + '><input type="checkbox" value="' + s.id + '"' + (st.statuses.indexOf(s.id) >= 0 ? ' checked' : '') + ro + '><span>' + esc(s.label) + (s.default ? ' <span class="mut">·&nbsp;default</span>' : '') +
                        (s.blocked ? ' <em class="sv-boff">never asks for a rating: untick it</em>' : '') + '</span></label>';
                }).join('') + '</div>' +
                '<div class="sv-g2"><label class="sl-field"><span class="sl-lab">Wait before asking</span><span class="sv-unit"><input class="sl-in" type="number" min="0" max="336" step="1" inputmode="numeric" data-b="delay_hours" value="' + (+st.delay_hours || 0) + '"' + ro + '><span>hours</span></span></label>' +
                '<label class="sl-field"><span class="sl-lab">Ask a customer at most</span><span class="sv-unit"><span class="mut sm">every</span><input class="sl-in" type="number" min="0" max="365" step="1" inputmode="numeric" data-b="frequency_days" value="' + (+st.frequency_days || 0) + '"' + ro + '><span>days</span></span></label></div>' +
                '<p class="mut sm" data-b-when></p></section>' +
                '<section class="sv-bsec"><h3>How it is asked</h3><div class="sv-bchs">' + Object.keys(CHS).map(function (c) {
                    return '<label class="sv-bch"><input type="checkbox" value="' + c + '"' + (st.channels.indexOf(c) >= 0 ? ' checked' : '') + ro + '><span><i class="ic ic-sm ' + (CHI[c] || '') + '" aria-hidden="true"></i>' + esc(CHS[c]) +
                        (c !== 'inapp' && INV[c] === false ? ' <em class="sv-boff">off on Automations</em>' : '') +
                        (c === 'inapp' && !cfg.popupOn ? ' <em class="sv-boff">pop-up switched off</em>' : '') + '</span></label>';
                }).join('') + '</div><p class="mut sm">WhatsApp, SMS and e-mail send the invitation message with the survey link. The pop-up asks when the customer next opens the portal.' +
                (INV.whatsapp === false || INV.sms === false || INV.email === false ? ' A channel marked “off on Automations” sends nothing until it is switched on there: <a href="' + esc(cfg.automationsUrl || 'comms_automations.php') + '">Automations</a>.' : '') +
                (!cfg.popupOn ? ' The pop-up is switched off for every survey under Pop-up &amp; invitation.' : '') + '</p></section>' +
                '<section class="sv-bsec sv-bact"><button type="button" class="sl-toggle' + (st.active ? ' on' : '') + '" role="switch" aria-checked="' + (st.active ? 'true' : 'false') + '" data-b-active tabindex="0" aria-labelledby="sv-b-al"' + (CAN ? '' : ' data-static') + '></button><span id="sv-b-al">Send this survey</span></section>' +
                '<section class="sv-bsec sv-bpv"><h3>Preview</h3><div class="sv-pv" data-b-pv aria-live="off"></div></section>' +
                '</aside></div>' +
                '<div class="sv-bft"><span class="sv-bmsg" data-b-msg role="status"></span><span class="grow"></span>' +
                '<button type="button" class="sl-btn ghost" data-b-close>' + (CAN ? 'Cancel' : 'Close') + '</button>' +
                (CAN ? '<button type="button" class="sl-btn" data-b-save>Save survey</button>' : '') + '</div>';
            drawQs(); preview(); when();
        }

        function kingBox() {
            if (!CAN) return '';
            if (!AI.ok) return '<div class="sv-king off"><span class="sv-kk" aria-hidden="true">&#10022;</span><span class="sm">' + esc(AI.message || (KING + ' is not set up yet.')) +
                (AI.setup_url ? ' <a href="' + esc(AI.setup_url) + '">AI centre</a>' : '') + '</span></div>';
            return '<div class="sv-king"><div class="sv-kh"><span class="sv-kk" aria-hidden="true">&#10022;</span><b class="fw5">Write questions with ' + esc(KING) + '</b></div>' +
                '<div class="sv-kf"><label class="sl-field grow"><span class="sl-lab">What do you want to learn?</span><input class="sl-in" data-k-purpose maxlength="400" placeholder="e.g. how our riders and the pickup at the Accra office went"></label>' +
                '<label class="sl-field"><span class="sl-lab">Questions</span><span class="sl-sel native"><select data-k-count aria-label="How many questions">' + [2, 3, 4, 5, 6].map(function (n) { return '<option' + (n === 3 ? ' selected' : '') + '>' + n + '</option>'; }).join('') + '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>' +
                '<button type="button" class="sl-btn" data-k-run>Write</button></div><div data-k-out></div></div>';
        }

        function drawQs() {
            var ol = $('[data-b-qs]', box), ro = CAN ? '' : ' disabled', n = st.questions.length;
            $('[data-b-qn]', box).textContent = n + ' question' + (n === 1 ? '' : 's') + (n > 5 ? ' · shorter surveys get more answers' : '');
            ol.innerHTML = st.questions.map(function (q, i) {
                var opts = q.type === 'choice' || q.type === 'multi';
                return '<li class="sv-bq" data-qi="' + i + '"><div class="sv-bqh"><span class="sv-bqn" aria-hidden="true">' + (i + 1) + '</span>' +
                    '<span class="sl-sel native sv-bqt"><select data-q="type" aria-label="Type of question ' + (i + 1) + '"' + ro + '>' + Object.keys(TYPES).map(function (t) {
                        return '<option value="' + t + '"' + (t === q.type ? ' selected' : '') + '>' + esc(TYPES[t][0]) + '</option>'; }).join('') + '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span>' +
                    '<label class="sv-req"><input type="checkbox" data-q="required"' + (q.required ? ' checked' : '') + ro + '><span>Required</span></label><span class="grow"></span>' +
                    (CAN ? '<button type="button" class="sl-btn ghost sm sv-ib" data-q-move="-1" aria-label="Move question ' + (i + 1) + ' up"' + (i === 0 ? ' disabled' : '') + '>&uarr;</button>' +
                        '<button type="button" class="sl-btn ghost sm sv-ib" data-q-move="1" aria-label="Move question ' + (i + 1) + ' down"' + (i === n - 1 ? ' disabled' : '') + '>&darr;</button>' +
                        '<button type="button" class="sl-btn ghost sm sv-ib" data-q-del aria-label="Remove question ' + (i + 1) + '"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button>' : '') + '</div>' +
                    '<input class="sl-in" data-q="label" maxlength="200" value="' + esc(q.label) + '" placeholder="Your question" aria-label="Question ' + (i + 1) + '"' + ro + '>' +
                    (opts ? '<div class="sv-bopts">' + q.options.map(function (o, j) {
                        return '<div class="sv-bopt"><span class="sv-bdot ' + (q.type === 'multi' ? 'sq' : '') + '" aria-hidden="true"></span><input class="sl-in" data-o="' + j + '" maxlength="80" value="' + esc(o) + '" aria-label="Answer ' + (j + 1) + ' of question ' + (i + 1) + '"' + ro + '>' +
                            (CAN ? '<button type="button" class="sl-btn ghost sm sv-ib" data-o-del="' + j + '" aria-label="Remove answer ' + (j + 1) + '">&times;</button>' : '') + '</div>';
                    }).join('') + (CAN && q.options.length < 8 ? '<button type="button" class="sl-btn ghost sm sv-oadd" data-o-add><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add answer</button>' : '') + '</div>' : '') +
                    '</li>';
            }).join('');
        }

        function qHtml(qs) {
            return qs.map(function (q, i) {
                var k = 'pv-' + q.key, body = '';
                if (q.type === 'rating' || q.type === 'nps') {
                    var lo = q.type === 'nps' ? 0 : 1, hi = q.type === 'nps' ? 10 : 5, s = '';
                    for (var n = lo; n <= hi; n++) s += '<label class="sq-opt"><input type="radio" name="' + k + '" value="' + n + '"><span>' + (q.type === 'rating' ? '<i aria-hidden="true">★</i>' : '') + n + '</span></label>';
                    body = '<div class="sq-scale">' + s + '</div><div class="sq-ends" aria-hidden="true"><span>' + (q.type === 'nps' ? 'Not likely' : 'Poor') + '</span><span>' + (q.type === 'nps' ? 'Very likely' : 'Excellent') + '</span></div>';
                } else if (q.type === 'choice' || q.type === 'multi') {
                    body = '<div class="sq-list">' + (q.options.length ? q.options : ['…']).map(function (o) {
                        return '<label class="sq-li"><input type="' + (q.type === 'multi' ? 'checkbox' : 'radio') + '" name="' + k + '"><span>' + esc(o || '…') + '</span></label>'; }).join('') + '</div>' +
                        (q.type === 'multi' ? '<p class="sq-hint">Tick all that apply.</p>' : '');
                } else body = '<textarea class="sq-text" rows="2" aria-label="' + esc(q.label) + '"></textarea>';
                return '<fieldset class="sq-q sq-' + q.type + '"><legend><span class="sq-n">' + (i + 1) + '.</span> ' + esc(q.label || 'Your question') + (q.required ? ' <span class="sq-req" aria-hidden="true">*</span>' : '') + '</legend>' + body + '</fieldset>';
            }).join('');
        }
        var pvT;
        function preview() {
            clearTimeout(pvT);
            pvT = setTimeout(function () {
                var pv = st && $('[data-b-pv]', box); if (!pv) return;
                pv.innerHTML = '<div class="sv-pvh"><span class="sv-pvi" aria-hidden="true">&#9733;</span><div><b>' + esc(st.name || 'How did we do?') + '</b><p>' + esc(st.intro || '') + '</p></div></div>' +
                    qHtml(st.questions) + '<div class="sv-pvf"><span class="sl-btn ghost sm" aria-hidden="true">Ask me later</span><span class="sl-btn sm" aria-hidden="true">Send feedback</span></div>';
            }, 120);
        }
        function when() {
            var el = st && $('[data-b-when]', box); if (!el) return;
            var names = st.statuses.length ? (cfg.statuses || []).filter(function (s) { return st.statuses.indexOf(s.id) >= 0; }).map(function (s) { return s.label; }) : defaults.map(function (s) { return s.label; });
            var d = +st.delay_hours || 0, f = +st.frequency_days || 0;
            el.textContent = 'Sent ' + (d ? d + ' hour' + (d === 1 ? '' : 's') + ' after ' : 'right after ') + (names.length ? names.join(' or ') : 'no status yet') +
                (f ? ', at most once every ' + f + ' day' + (f === 1 ? '' : 's') + ' per customer' : ', after every matching status') + '. Quiet hours are respected.';
        }

        if (box) {
            box.addEventListener('input', function (e) {
                if (!st) return;
                var t = e.target, k = t.getAttribute('data-b'), li = t.closest('[data-qi]');
                if (k) { st[k] = t.type === 'number' ? (t.value === '' ? 0 : +t.value) : t.value; if (k === 'delay_hours' || k === 'frequency_days') when(); preview(); return; }
                if (li) {
                    var q = st.questions[+li.getAttribute('data-qi')];
                    if (t.getAttribute('data-q') === 'label') q.label = t.value;
                    if (t.hasAttribute('data-o')) q.options[+t.getAttribute('data-o')] = t.value;
                    preview();
                }
            });
            box.addEventListener('change', function (e) {
                if (!st) return;
                var t = e.target, li = t.closest('[data-qi]');
                if (li) {
                    var q = st.questions[+li.getAttribute('data-qi')];
                    if (t.getAttribute('data-q') === 'required') q.required = t.checked;
                    if (t.getAttribute('data-q') === 'type') {
                        q.type = t.value;
                        if ((q.type === 'choice' || q.type === 'multi') && q.options.length < 2) q.options = q.options.concat(['', '']).slice(0, Math.max(2, q.options.length));
                        if (q.type === 'text') q.required = false;
                        drawQs();
                        var sel = $('[data-qi="' + li.getAttribute('data-qi') + '"] [data-q="type"]', box); if (sel) sel.focus();
                    }
                    preview(); return;
                }
                if (t.name === 'sv-bst') {
                    var pick = t.value === 'pick', list = $('[data-b-stlist]', box);
                    list.hidden = !pick;
                    if (!pick) { st.statuses = []; $$('input', list).forEach(function (c) { c.checked = false; }); }
                    else if (!st.statuses.length) { st.statuses = defaults.map(function (s) { return s.id; }); $$('input', list).forEach(function (c) { c.checked = st.statuses.indexOf(+c.value) >= 0; }); }
                    when(); return;
                }
                if (t.closest('[data-b-stlist]')) { st.statuses = $$('[data-b-stlist] input:checked', box).map(function (c) { return +c.value; }); when(); return; }
                if (t.closest('.sv-bchs')) { st.channels = $$('.sv-bchs input:checked', box).map(function (c) { return c.value; }); }
            });
            box.addEventListener('click', function (e) {
                var b = e.target.closest('button'); if (!b || !st) return;
                if (b.hasAttribute('data-b-close')) { close(); return; }
                if (b.hasAttribute('data-b-active')) { setTimeout(function () { st.active = b.getAttribute('aria-checked') === 'true'; }, 0); return; }
                if (b.hasAttribute('data-b-save')) { save(b); return; }
                if (b.hasAttribute('data-k-run')) { king(b); return; }
                if (b.hasAttribute('data-b-add')) {
                    if (st.questions.length >= 12) { msg('Keep a survey to 12 questions or fewer.', true); return; }
                    var ty = b.getAttribute('data-b-add');
                    st.questions.push({ key: nextKey(), type: ty, label: '', required: ty !== 'text', options: ty === 'choice' || ty === 'multi' ? ['', ''] : [] });
                    drawQs(); preview();
                    var inp = $$('[data-q="label"]', box).pop(); if (inp) inp.focus();
                    return;
                }
                var li = b.closest('[data-qi]'); if (!li) return;
                var i = +li.getAttribute('data-qi'), q = st.questions[i];
                if (b.hasAttribute('data-q-del')) {
                    if (st.questions.length === 1) { msg('A survey needs at least one question.', true); return; }
                    st.questions.splice(i, 1); drawQs(); preview();
                    var nx = $$('[data-q="label"]', box)[Math.min(i, st.questions.length - 1)]; if (nx) nx.focus();
                } else if (b.hasAttribute('data-q-move')) {
                    var j = i + (+b.getAttribute('data-q-move')); if (j < 0 || j >= st.questions.length) return;
                    st.questions.splice(j, 0, st.questions.splice(i, 1)[0]); drawQs(); preview();
                    var mv = $('[data-qi="' + j + '"] [data-q-move="' + b.getAttribute('data-q-move') + '"]', box) || $('[data-qi="' + j + '"] [data-q="label"]', box); if (mv) mv.focus();
                } else if (b.hasAttribute('data-o-add')) {
                    q.options.push(''); drawQs(); preview();
                    var oi = $$('[data-qi="' + i + '"] [data-o]', box).pop(); if (oi) oi.focus();
                } else if (b.hasAttribute('data-o-del')) {
                    q.options.splice(+b.getAttribute('data-o-del'), 1); drawQs(); preview();
                }
            });
        }
        function msg(t, bad) { var m = $('[data-b-msg]', box); if (m) { m.textContent = t || ''; m.classList.toggle('bad', !!bad); } }

        function check() {
            if (!st.name.trim()) return ['Give the survey a name.', '[data-b="name"]'];
            for (var i = 0; i < st.questions.length; i++) {
                var q = st.questions[i];
                if (!q.label.trim()) return ['Question ' + (i + 1) + ' needs its question text.', '[data-qi="' + i + '"] [data-q="label"]'];
                if (q.type === 'choice' || q.type === 'multi') {
                    var seen = {}, filled = 0;
                    for (var j = 0; j < q.options.length; j++) {
                        var o = (q.options[j] || '').trim().replace(/\s+/g, ' ');
                        if (!o) continue;
                        if (seen[o.toLowerCase()]) return ['Question ' + (i + 1) + ' has the same answer twice (“' + o + '”). Change or remove one.', '[data-qi="' + i + '"] [data-o="' + j + '"]'];
                        seen[o.toLowerCase()] = 1; filled++;
                    }
                    if (filled < 2) return ['Question ' + (i + 1) + ' needs at least two answers to choose from.', '[data-qi="' + i + '"] [data-o]'];
                }
            }
            var dh = Number(st.delay_hours), fd = Number(st.frequency_days);
            if (!isFinite(dh) || dh < 0 || dh > 336 || Math.floor(dh) !== dh) return ['Wait a whole number of hours, from 0 to 336 (14 days).', '[data-b="delay_hours"]'];
            if (!isFinite(fd) || fd < 0 || fd > 365 || Math.floor(fd) !== fd) return ['Ask at most once every 0 to 365 whole days.', '[data-b="frequency_days"]'];
            var blk = (cfg.statuses || []).filter(function (s) { return s.blocked && st.statuses.indexOf(s.id) >= 0; })[0];
            if (blk) return ['“' + blk.label + '” never asks for a rating. Untick it.', '[data-b-stlist] input[value="' + blk.id + '"]'];
            if (st.active && !st.channels.length) return ['Choose at least one way to ask: the pop-up, WhatsApp, SMS or e-mail.', '.sv-bchs input'];
            if ($('input[name="sv-bst"][value="pick"]', box).checked && !st.statuses.length) return ['Choose at least one status, or send after delivered or picked up.', '[data-b-stlist] input'];
            return null;
        }
        function save(btn) {
            var bad = check();
            if (bad) { msg(bad[0], true); var f = $(bad[1], box); if (f) f.focus(); return; }
            var data = clone(st);
            data.questions.forEach(function (q) { q.options = (q.options || []).map(function (o) { return o.trim(); }).filter(Boolean); });
            btn.disabled = true; btn.classList.add('loading'); msg('Saving…');
            post({ action: 'form_save', form: JSON.stringify(data) }).then(function (j) {
                if (!j || !j.success) { msg((j && j.message) || 'Not saved. Please try again.', true); return; }
                start0 = JSON.stringify(st);
                flashLater(j.message || 'Survey saved', j.warning || '');
                close(true);
                location.href = location.pathname + '?tab=forms';
            }).catch(function (e) { msg(e.message || 'Network error. Please try again.', true); })
              .finally(function () { btn.disabled = false; btn.classList.remove('loading'); });
        }

        function king(btn) {
            var out = $('[data-k-out]', box);
            btn.disabled = true; btn.classList.add('loading'); out.innerHTML = '<p class="mut sm">' + esc(KING) + ' is writing…</p>';
            post({ action: 'ai', name: st.name || 'How did we do?', purpose: $('[data-k-purpose]', box).value, count: $('[data-k-count]', box).value }).then(function (j) {
                if (!j || !j.success) {
                    out.innerHTML = '<p class="sv-bmsg bad sm" role="alert">' + esc((j && j.message) || 'Nothing came back.') + (j && j.setup_url ? ' <a href="' + esc(j.setup_url) + '">AI centre</a>' : '') + '</p>';
                    return;
                }
                var qs = j.questions || [];
                out.innerHTML = '<div class="sv-kp"><p class="sm"><b class="fw5">' + esc(KING) + ' suggests</b>' + (j.intro ? ' · intro: “' + esc(j.intro) + '”' : '') + '</p><ol>' + qs.map(function (q) {
                    return '<li><span class="sv-qt">' + esc((TYPES[q.type] || [q.type])[0]) + '</span> ' + esc(q.label) + (q.options && q.options.length ? ' <span class="mut">(' + esc(q.options.join(' / ')) + ')</span>' : '') + '</li>'; }).join('') + '</ol>' +
                    ((j.warnings || []).length ? '<p class="mut sm">' + esc(j.warnings.join(' ')) + '</p>' : '') +
                    '<div class="row g8 wrap"><button type="button" class="sl-btn sm" data-k-use="replace">Use these questions</button><button type="button" class="sl-btn ghost sm" data-k-use="add">Add to mine</button><button type="button" class="sl-btn ghost sm" data-k-use="no">Discard</button></div></div>';
                $$('[data-k-use]', out).forEach(function (b) {
                    b.addEventListener('click', function () {
                        var how = b.getAttribute('data-k-use');
                        if (how !== 'no') {
                            var add = qs.map(function (q) { return { key: '', type: q.type, label: q.label, required: !!q.required, options: q.options || [] }; });
                            if (how === 'replace') { st.questions = []; if (j.intro) st.intro = j.intro; if (j.thanks) st.thanks = j.thanks; }
                            add.forEach(function (q) { if (st.questions.length < 12) { q.key = nextKey(); st.questions.push(q); } });
                            var iv = $('[data-b="intro"]', box), tv = $('[data-b="thanks"]', box); if (iv) iv.value = st.intro; if (tv) tv.value = st.thanks;
                            drawQs(); preview(); msg('Questions from ' + KING + ' added. Check them, then save.');
                        }
                        out.innerHTML = '';
                    });
                });
            }).catch(function (e) { out.innerHTML = '<p class="sv-bmsg bad sm" role="alert">' + esc(e.message || 'Network error') + '</p>'; })
              .finally(function () { btn.disabled = false; btn.classList.remove('loading'); });
        }

        // Escape (swiftlane.js closes every dialog) must not lose unsaved work.
        window.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !dlg || dlg.hidden) return;
            e.stopImmediatePropagation(); e.preventDefault();
            close();
        }, true);
        if (dlg) dlg.addEventListener('click', function (e) { if (e.target === dlg) { e.stopPropagation(); close(); } }, true);

        $$('[data-sv-new]').forEach(function (b) { b.addEventListener('click', function () { open(blank()); }); });
        root.addEventListener('click', function (e) {
            var b = e.target.closest('[data-sv-edit]'); if (!b) return;
            var id = +b.getAttribute('data-sv-edit'), f = (cfg.forms || []).filter(function (x) { return x.id === id; })[0];
            if (f) open(f);
        });
        if (cfg.edit) { var f0 = (cfg.forms || []).filter(function (x) { return x.id === cfg.edit; })[0]; if (f0) open(f0); }

        /* ================================================================ copy, delete, archive */
        root.addEventListener('click', function (e) {
            var b = e.target.closest('[data-sv-act]'); if (!b || b.disabled) return;
            var cm = b.getAttribute('data-sv-confirm'); if (cm && !confirm(cm)) return;
            b.disabled = true; b.classList.add('loading');
            post({ action: b.getAttribute('data-sv-act'), id: b.getAttribute('data-sv-id') }).then(function (j) {
                if (!j || !j.success) { toast((j && j.message) || 'Not done. Please try again.', true); b.disabled = false; b.classList.remove('loading'); return; }
                flashLater(j.message || 'Done');
                location.href = location.pathname + '?tab=forms';
            }).catch(function (er) { toast(er.message || 'Network error', true); b.disabled = false; b.classList.remove('loading'); });
        });

        /* ================================================================ one survey's answers */
        var rd = document.getElementById('sv-resp');
        root.addEventListener('click', function (e) {
            var b = e.target.closest('[data-sv-resp]'); if (!b || !rd) return;
            var body = $('[data-sv-resp-body]', rd);
            body.innerHTML = '<p class="mut">Loading…</p>'; rd.hidden = false;
            post({ action: 'response', id: b.getAttribute('data-sv-resp') }).then(function (j) {
                if (!j || !j.success) { body.innerHTML = '<p class="sv-bmsg bad">' + esc((j && j.message) || 'Could not load the answers.') + '</p>'; return; }
                var s = j.survey;
                body.innerHTML = '<p class="mut sm"><a href="' + esc(s.customer_link) + '">' + esc(s.customer || 'Customer') + '</a>' +
                    (s.ref ? ' · ' + (s.record_link ? '<a href="' + esc(s.record_link) + '">' + esc(s.ref) + '</a>' : esc(s.ref)) : '') + (s.form ? ' · ' + esc(s.form) : '') +
                    '</p><p class="mut sm">Sent ' + esc(s.sent_label || '—') + (s.channel_label ? ' by ' + esc(s.channel_label) : '') + ' · answered ' + esc(s.responded_label || '—') +
                    (s.ticket_link ? ' · <a href="' + esc(s.ticket_link) + '">Follow-up ticket</a>' : '') + '</p><dl class="sv-ans">' + j.answers.map(function (a) {
                        var v = a.type === 'rating' ? '<span class="sv-stars">' + '★'.repeat(+a.values[0] || 0) + '<i>' + '★'.repeat(5 - (+a.values[0] || 0)) + '</i></span> ' + esc(a.values[0]) + ' / 5'
                            : a.type === 'nps' ? esc(a.values[0]) + ' / 10' : a.values.map(function (x) { return esc(x); }).join('<br>');
                        return '<dt>' + esc(a.label) + '</dt><dd>' + v + '</dd>';
                    }).join('') + '</dl>';
            }).catch(function (er) { body.innerHTML = '<p class="sv-bmsg bad">' + esc(er.message || 'Network error') + '</p>'; });
        });

        /* ================================================================ invitation message */
        $$('[data-sv-invite]').forEach(function (b) {
            b.addEventListener('click', function () {
                var CE = window.SLCommsEditor;
                if (!CE) { toast('The editor could not load. Reload the page.', true); return; }
                SL.post(cfg.tplAjax, { action: 'template_get', key: 'survey', language: 'en' }).then(function (j) {
                    if (!j || !j.success) { toast((j && j.message) || 'Could not open the message.', true); return; }
                    var tpl = function (d) { return SL.post(cfg.tplAjax, d); };
                    // The preview shows a real-length survey link (signed: id.expiry.hmac), not the tracking link.
                    var sample = j.sample || {};
                    var site = String(sample.track_url || '').replace(/[?#].*$/, '').replace(/[^/]*$/, '') || (location.origin + '/');
                    sample.survey_url = site + 'survey.php?t=123.1798410854.' + new Array(33).join('a');
                    var ed = CE.open({
                        title: 'Survey invitation', subtitle: 'Sent with the survey link on the channels each survey uses', key: 'survey',
                        channels: ['whatsapp', 'sms', 'email'], channel: 'whatsapp', values: j.values, enabled: j.enabled, tags: j.tags, tagSyntax: 'brace', sample: sample,
                        contact: { name: (j.sample && (j.sample.first_name || j.sample.name)) || 'Customer', phone: 'online' }, sender: { sms: 'SWIFTLANE' },
                        canvas: { tagSyntax: 'brace', saved: false, ajax: null, uploadUrl: null, cfg: { siteUrl: location.origin + location.pathname.replace(/[^/]*$/, '') } },
                        king: { url: cfg.tplAjax, params: { action: 'ai' }, name: KING, tones: AI.tones && AI.tones.length ? AI.tones : undefined, enabled: !!AI.ok && CAN,
                            message: !CAN ? 'Only administrators write messages with ' + KING + '.' : (AI.message || KING + ' is not set up yet.'), setupUrl: AI.setup_url || '' },
                        labels: { reset: 'Reset to built-in' },
                        onSave: CAN ? function (v) {
                            var data = { action: 'template_save', key: 'survey', channel: v.channel, language: 'en', subject: v.subject || '', via_ai: v.via_ai ? 1 : 0,
                                enabled: v.enabled === null || v.enabled === undefined ? '' : (v.enabled ? 1 : 0) };
                            if (v.channel === 'email') {
                                data.html = v.html || ''; data.design_json = v.design_json || (v.design ? JSON.stringify(v.design) : '');
                                if (v.untouched && !data.html) return { success: false, message: 'Open the e-mail tab and change something before saving it.' };
                            } else data.body = v.body || '';
                            return tpl(data);
                        } : null,
                        onReset: CAN ? function (c) {
                            // E-mail goes back to its own designed invitation (button to the survey), not the WhatsApp text.
                            return c === 'email' ? post({ action: 'invite_email_reset' }) : tpl({ action: 'template_reset', key: 'survey', channel: c, language: 'en' });
                        } : null
                    });
                    if (!CAN) ed.status('You can read this message; an administrator changes it.');
                }).catch(function (e) { toast(e.message || 'Network error', true); });
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
