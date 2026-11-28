/* Communications > Automations (views/sl/pages/CommsAutomations.php) and
 * Communications > Status messages (views/sl/pages/CommsStatuses.php).
 * Needs swiftlane.js (SL.post, SL.toast, sl-toggle saves), dataJs/sl/email_builder.js
 * and dataJs/sl/comms_editor.js (SLCommsEditor). Endpoint: ajax/comms/automation_ajax.php.
 *
 * - search and filter of the trigger / status rows
 * - channel switches post through swiftlane.js (data-sl-save); this file keeps the
 *   Drafted / Custom / Off chips and the counts in step with them
 * - Edit opens the shared editor drawer: status and event texts (template_get /
 *   template_save / template_reset), old-screen texts (legacy_get / legacy_save)
 * - the in-app bell line of a status, and "Redraft all with King"
 */
(function () {
    'use strict';
    function start() {
        var cfgEl = document.getElementById('ca-cfg');
        var root = document.getElementById('ca-root');
        if (!cfgEl || !root) return;
        var cfg; try { cfg = JSON.parse(cfgEl.textContent); } catch (e) { return; }
        var CE = window.SLCommsEditor;
        var AJAX = cfg.ajax, AI = cfg.ai || {}, KING = AI.name || 'King';
        var CH = { whatsapp: 'WhatsApp', sms: 'SMS', email: 'E-mail', inapp: 'In-app' };
        var STATE = { drafted: 'Drafted', shared: 'Shared', custom: 'Custom', off: 'Off' };
        var STATES = ['drafted', 'shared', 'custom', 'off'];
        window.SL_KING_NAME = KING;

        function $(s, r) { return (r || document).querySelector(s); }
        function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
        function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
        function toast(m, bad) { if (window.SL && SL.toast) SL.toast(m, bad); }
        function post(data) { return SL.post(AJAX, data); }
        function fail(j, d) { return (j && j.message) || d || 'Something went wrong. Please try again.'; }

        /* ---------- search and filter ---------- */
        var q = $('[data-ca-q]', root), filt = 'all';
        var segBtns = $$('[data-ca-f]', root);
        segBtns.forEach(function (b) { if (b.classList.contains('on')) filt = b.getAttribute('data-ca-f'); });
        function apply() {
            var term = (q && q.value || '').trim().toLowerCase(), shown = 0;
            $$('[data-ca-row]', root).forEach(function (r) {
                var tags = ' ' + (r.getAttribute('data-f') || '') + ' ';
                var ok = (filt === 'all' || tags.indexOf(' ' + filt + ' ') >= 0) && (!term || r.textContent.toLowerCase().indexOf(term) >= 0);
                r.hidden = !ok; if (ok) shown++;
            });
            $$('[data-ca-group]', root).forEach(function (g) { g.hidden = !$$('[data-ca-row]', g).some(function (r) { return !r.hidden; }); });
            var em = $('[data-ca-empty]', root); if (em) em.hidden = shown > 0;
        }
        if (q) q.addEventListener('input', apply);
        segBtns.forEach(function (b) {
            b.addEventListener('click', function () {
                filt = b.getAttribute('data-ca-f');
                segBtns.forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', String(x === b)); });
                apply();
            });
        });
        var clr = $('[data-ca-clear]', root);
        if (clr) clr.addEventListener('click', function () { if (q) q.value = ''; segBtns[0] && segBtns[0].click(); apply(); });
        apply();

        /* ---------- switches: keep chips, rows and counts in step ---------- */
        function wasOf(chip) { return chip.classList.contains('custom') ? 'custom' : (chip.classList.contains('shared') ? 'shared' : (chip.classList.contains('drafted') ? 'drafted' : '')); }
        function chipFor(toggle) { var td = toggle.closest('[data-cell]'); return td ? $('.cs-st', td) : null; }
        function rowTags(tr) {
            if (!tr || cfg.page !== 'statuses') {
                if (tr && cfg.page === 'automations') {
                    var anyOn = $$('.sl-toggle[role="switch"]', tr).some(function (t) { return t.getAttribute('aria-checked') === 'true'; });
                    var f = (tr.getAttribute('data-f') || '').split(' ').filter(function (x) { return x && x !== 'on'; });
                    if (anyOn) f.unshift('on');
                    tr.setAttribute('data-f', f.join(' '));
                }
                return;
            }
            var f2 = {};
            $$('.cs-st', tr).forEach(function (c) { STATES.forEach(function (s) { if (c.classList.contains(s)) f2[s] = 1; }); });
            if ((tr.getAttribute('data-f') || '').indexOf('urgent') >= 0) f2.urgent = 1;
            tr.setAttribute('data-f', Object.keys(f2).join(' '));
        }
        function counts() {
            if (cfg.page !== 'statuses') return;
            STATES.forEach(function (s) { var el = $('[data-cs-count="' + s + '"]', root); if (el) el.textContent = $$('.cs-st.' + s, $('.cs-grid', root)).length; });
        }
        function setChip(chip, state, via) {
            if (!chip) return;
            STATES.forEach(function (x) { chip.classList.toggle(x, x === state); });
            chip.textContent = STATE[state] + (via ? '*' : '');
        }
        $$('.sl-toggle[role="switch"][data-key]', root).forEach(function (t) {
            var chip = chipFor(t);
            if (chip) chip.setAttribute('data-was', wasOf(chip));
            new MutationObserver(function () {
                var on = t.getAttribute('aria-checked') === 'true';
                if (chip) {
                    var was = chip.getAttribute('data-was') || 'drafted';
                    if (on && !chip.classList.contains('off')) return;
                    setChip(chip, on ? was : 'off');
                }
                rowTags(t.closest('[data-ca-row]')); counts();
            }).observe(t, { attributes: true, attributeFilter: ['aria-checked'] });
            t.addEventListener('click', function () {
                if (!chip || t.hasAttribute('data-static')) return;
                if (!chip.classList.contains('off')) chip.setAttribute('data-was', wasOf(chip));
            }, true);
        });
        var master = $('[data-ca-master]', root);
        if (master) new MutationObserver(function () {
            var on = master.getAttribute('aria-checked') === 'true', s = $('[data-ca-master-state]', root), d = $('[data-ca-master-text]', root);
            if (s) s.textContent = on ? 'on' : 'off';
            if (d && cfg.masterText) d.textContent = cfg.masterText[on ? 'on' : 'off'];
        }).observe(master, { attributes: true, attributeFilter: ['aria-checked'] });

        /* Saving switches: handled here (not by swiftlane.js) so the answer's cells reach the grid,
           and posted one after another so two quick switches never overwrite each other. */
        var queue = Promise.resolve();
        root.addEventListener('click', function (e) {
            var sw = e.target.closest('.sl-toggle[role="switch"][data-sl-save]');
            if (!sw || !root.contains(sw)) return;
            e.stopPropagation(); e.preventDefault();
            if (sw.hasAttribute('data-static') || sw.getAttribute('aria-busy') === 'true') return;
            var on = !sw.classList.contains('on');
            if (sw === master && on && !confirm('Switch automations on? Every trigger switched on below starts sending messages to customers.')) return;
            sw.classList.toggle('on', on); sw.setAttribute('aria-checked', on ? 'true' : 'false'); sw.setAttribute('aria-busy', 'true');
            var data = { key: sw.getAttribute('data-key') || '', value: on ? 1 : 0 };
            Array.prototype.forEach.call(sw.attributes, function (at) { if (at.name.indexOf('data-p-') === 0) data[at.name.slice(7)] = at.value; });
            function rollback(msg) { sw.classList.toggle('on', !on); sw.setAttribute('aria-checked', String(!on)); toast(msg || 'Not saved', true); }
            queue = queue.then(function () {
                return post(data).then(function (j) {
                    if (!j || j.success !== true) { rollback(fail(j, 'Not saved')); return; }
                    if (j.message) toast(j.message);
                    if (j.cells) applyCells(data.key, j.cells);
                    if (j.rows) Object.keys(j.rows).forEach(function (k) { applyCells(k, j.rows[k]); });
                }).catch(function (err) { rollback((err && err.message) || 'Network error. Please try again.'); })
                  .then(function () { sw.removeAttribute('aria-busy'); });
            });
        }, true);

        function setRowToggle(key, ch, on) {
            $$('.sl-toggle[data-key="' + key.replace(/"/g, '') + '"][data-p-channel="' + ch + '"]', root).forEach(function (t) {
                if ((t.getAttribute('aria-checked') === 'true') === !!on) return;
                t.classList.toggle('on', !!on); t.setAttribute('aria-checked', on ? 'true' : 'false');
            });
        }
        function applyCells(key, cells) {
            if (!cells) return;
            var tr = $('[data-ca-row][data-key="' + key + '"]', root); if (!tr) return;
            Object.keys(cells).forEach(function (ch) {
                var td = $('[data-cell="' + ch + '"]', tr); if (!td) return;
                var t = $('.sl-toggle', td), c = cells[ch], chip = $('.cs-st', td);
                if (chip && c.state !== 'off') chip.setAttribute('data-was', c.state);
                if (t) { t.classList.toggle('on', !!c.on); t.setAttribute('aria-checked', c.on ? 'true' : 'false'); }
                setChip(chip, c.state, c.via === 'any');
            });
            rowTags(tr); counts(); apply();
        }

        /* ---------- King settings for the editors ---------- */
        function kingOpts() {
            if (!CE) return false;
            return { url: AJAX, params: { action: 'ai' }, name: KING, tones: AI.tones && AI.tones.length ? AI.tones : undefined,
                enabled: !!AI.ok && !!cfg.canEdit, message: !cfg.canEdit ? 'Only people who can edit messages write them with ' + KING + '.' : (AI.message || KING + ' is not set up yet.'), setupUrl: cfg.canEdit ? (AI.setup_url || '') : '' };
        }
        function canvasCfg() { return { tagSyntax: 'brace', saved: false, ajax: null, uploadUrl: null, cfg: { siteUrl: location.origin + location.pathname.replace(/[^/]*$/, '') } }; }

        /* ---------- the editor for a status or event key ---------- */
        function waExtra(get) {
            return function (box, ed) {
                var v = get();
                box.innerHTML = '<details class="ca-cloud"><summary class="sm">WhatsApp Cloud API template <span class="mut">(only with the Meta Cloud API provider)</span></summary>' +
                    '<p class="mut sm">Outside the 24-hour window Meta only delivers an approved template. Name it here and list the placeholders that fill its {{1}}, {{2}}… in order.</p>' +
                    '<div class="ca-cloud-g"><label class="sl-field"><span class="sl-lab">Template name</span><input class="sl-in" data-wa="wa_template_name" maxlength="120" placeholder="shipment_update"></label>' +
                    '<label class="sl-field"><span class="sl-lab">Language</span><input class="sl-in" data-wa="wa_template_lang" maxlength="12" placeholder="en_US"></label>' +
                    '<label class="sl-field ca-cloud-p"><span class="sl-lab">Parameters</span><input class="sl-in" data-wa="wa_template_params" maxlength="255" placeholder="first_name,tracking,status"></label></div></details>';
                $$('[data-wa]', box).forEach(function (i) { i.value = v[i.getAttribute('data-wa')] || ''; });
                if (v.wa_template_name) $('details', box).open = true;
            };
        }
        /* Read-only people can look, not type (the canvas has no save button for them). */
        function lockInputs(ed) {
            if (cfg.canEdit) return;
            var lock = function () { $$('textarea, input:not([type=hidden]):not([type=checkbox])', ed.el).forEach(function (f) { f.readOnly = true; }); $$('input[type=checkbox]', ed.el).forEach(function (f) { f.disabled = true; }); };
            lock(); ed.on('tab', function () { setTimeout(lock, 0); });
        }
        /* An SMS carries the short, unformatted pickup code line: its phone and count use those values. */
        function smsSample(ed, sample, sms) {
            if (!sms) return;
            /* setSample re-renders the preview and character count without marking the message changed. */
            function pick(c) { ed.setSample(c === 'sms' ? sms : sample); }
            ed.on('tab', function (ev) { pick((ev && ev.channel) || ed.channel()); });
            pick(ed.channel());
        }
        function waFields(ed) { var o = {}; $$('[data-wa]', ed.el).forEach(function (i) { o[i.getAttribute('data-wa')] = i.value.trim(); }); return o; }
        function sourceNote(src) { return { saved: 'Custom', saved_en: 'Custom (English)', draft: 'Drafted for this status', drafted: 'Drafted', 'default': 'Built-in', none: 'No text' }[src] || ''; }

        function openTrigger(key, label, ch) {
            if (!CE) { toast('The editor could not load. Reload the page.', true); return; }
            var lang = 'en';
            post({ action: 'template_get', key: key, language: lang }).then(function (j) {
                if (!j || !j.success) { toast(fail(j, 'Could not open the message.'), true); return; }
                var isStatus = /^status:\d+$/.test(key), waVals = j.values.whatsapp || {};
                var sub = isStatus ? 'Status · ' + (j.status ? j.status.kind_label : '') + (j.status && j.status.urgent ? ' · urgent, ignores quiet hours' : '')
                    : 'Automation Center message';
                var ed = CE.open({
                    title: j.label || label, subtitle: sub, key: key, channels: ['whatsapp', 'sms', 'email'], channel: ch || 'whatsapp',
                    values: j.values, enabled: j.enabled, tags: j.tags, tagSyntax: 'brace', sample: j.sample,
                    contact: { name: (j.sample && (j.sample.first_name || j.sample.name)) || 'Customer', phone: 'online' }, sender: { sms: 'SWIFTLANE' },
                    canvas: canvasCfg(), king: kingOpts(), extra: { whatsapp: waExtra(function () { return waVals; }) },
                    labels: { reset: isStatus ? 'Reset to predefined' : 'Reset to built-in' },
                    onSave: cfg.canEdit ? function (v) { return save(key, v, ed, function () { return lang; }); } : null,
                    onTest: cfg.canEdit ? function (v) { return test(key, v); } : null,
                    onReset: cfg.canEdit ? function (c) {
                        return post({ action: 'template_reset', key: key, channel: c, language: lang }).then(function (r) {
                            if (r && r.success) { applyCells(key, r.cells); showSources(ed, r.sources); }
                            return r;
                        });
                    } : null
                });
                ed.el.classList.add('ca-ed');
                showSources(ed, j.sources);
                smsSample(ed, j.sample, j.sample_sms);
                lockInputs(ed);
                langPicker(ed, j, function (nl) {
                    if (ed.isDirty() && !confirm('Switch language? Unsaved changes in this editor are lost.')) return false;
                    return post({ action: 'template_get', key: key, language: nl }).then(function (r) {
                        if (!r || !r.success) { toast(fail(r), true); return false; }
                        lang = nl;
                        ['whatsapp', 'sms', 'email'].forEach(function (c) { ed.setValue(c, r.values[c]); });
                        waVals = r.values.whatsapp || {}; $$('[data-wa]', ed.el).forEach(function (i) { i.value = waVals[i.getAttribute('data-wa')] || ''; });
                        ed.markClean(); showSources(ed, r.sources);
                        ed.status(nl === 'en' ? 'English text.' : (cfg.langs[nl] || nl) + ': ' + (r.sources.whatsapp === 'saved' ? 'saved text.' : 'no own text yet; the English text is shown and sent until you save one.'));
                        return true;
                    });
                });
                if (!cfg.canEdit) ed.status('You can read these messages; an administrator changes them.');
            }).catch(function (e) { toast(e.message || 'Network error', true); });
        }
        function showSources(ed, sources) {
            if (!sources) return;
            Object.keys(sources).forEach(function (c) {
                var tab = $('.ce-tab[data-ch="' + c + '"]', ed.el); if (!tab) return;
                var s = $('[data-src]', tab); if (!s) { s = document.createElement('span'); s.setAttribute('data-src', ''); s.className = 'ca-src'; tab.appendChild(s); }
                s.textContent = sourceNote(sources[c]); s.title = 'Where this text comes from';
            });
        }
        function langPicker(ed, j, onChange) {
            var langs = cfg.langs || {}; if (Object.keys(langs).length < 2) return;
            var hd = $('.ce-hd', ed.el); if (!hd) return;
            var w = document.createElement('label'); w.className = 'ca-lang';
            w.innerHTML = '<i class="ic ic-sm i-lang" aria-hidden="true"></i><span class="ca-vh">Language</span><select class="sl-in sm" aria-label="Language of this message">' + Object.keys(langs).map(function (k) { return '<option value="' + esc(k) + '"' + (k === j.language ? ' selected' : '') + '>' + esc(langs[k]) + '</option>'; }).join('') + '</select>';
            var x = $('.ce-x', hd); hd.insertBefore(w, x);
            var sel = $('select', w), prev = sel.value;
            sel.addEventListener('change', function () {
                Promise.resolve(onChange(sel.value)).then(function (ok) { if (ok === false) sel.value = prev; else prev = sel.value; });
            });
        }
        function save(key, v, ed, langOf) {
            var data = { action: 'template_save', key: key, channel: v.channel, language: langOf(), subject: v.subject || '', via_ai: v.via_ai ? 1 : 0,
                enabled: v.enabled === null || v.enabled === undefined ? '' : (v.enabled ? 1 : 0) };
            if (v.channel === 'email') { data.html = v.html || ''; data.design_json = v.design_json || ''; if (!data.html) return { success: false, message: 'Open the e-mail tab and change something before saving it.' }; }
            else data.body = v.body || '';
            if (v.channel === 'whatsapp') Object.assign(data, waFields(ed));
            return post(data).then(function (r) {
                if (r && r.success) {
                    applyCells(key, r.cells);
                    if (r.enabled && cfg.page === 'automations') Object.keys(r.enabled).forEach(function (c) { setRowToggle(key, c, r.enabled[c]); });
                    showSources(ed, r.sources);
                }
                return r;
            });
        }
        function test(key, v, fixHtml) {
            var data = { action: 'test_send', key: key, channel: v.channel, subject: v.subject || '', body: v.body || '', html: v.channel === 'email' ? (v.html || '') : '' };
            if (v.channel === 'email' && !data.html) return { success: false, message: 'Open the e-mail first.' };
            if (v.channel !== 'email' && !String(data.body).trim()) return { success: false, message: 'Write the message first.' };
            if (fixHtml && data.html) data.html = fixHtml(data.html);
            if (v.channel !== 'email' && !confirm('Send this ' + CH[v.channel] + ' test to your own phone number? Providers charge for it.')) return { success: false, message: 'Test not sent.' };
            return post(data);
        }

        /* ---------- the editor for an old-screen message (legacy template rows) ---------- */
        /* Old e-mails load images from [URL]/uploads/...: shown from this site in the editor, saved back as [URL]. */
        var SITE = (location.origin + location.pathname.replace(/[^/]*$/, '')).replace(/\/$/, '');
        var LOGO = '';   // [URL_LINK] in the old e-mails is the logo file under assets/
        function urlIn(t) { t = String(t || '').replace(/(src=\\?["'])\[URL\]/g, '$1' + SITE); return LOGO ? t.split(SITE + '/assets/[URL_LINK]').join(SITE + '/assets/' + LOGO) : t; }
        function urlOut(t) { t = String(t || ''); if (LOGO) t = t.split(SITE + '/assets/' + LOGO).join(SITE + '/assets/[URL_LINK]'); return t.split(SITE + '/').join('[URL]/').split(SITE + '"').join('[URL]"'); }
        function designIn(d) { if (!d) return d; try { return JSON.parse(urlIn(JSON.stringify(d))); } catch (e) { return d; } }

        function openLegacy(key, label, ch0, rowId) {
            if (!CE) { toast('The editor could not load. Reload the page.', true); return; }
            post({ action: 'legacy_get', key: key }).then(function (j) {
                if (!j || !j.success) { toast(fail(j, 'Could not open the message.'), true); return; }
                var tpls = j.templates || {}, chans = ['whatsapp', 'sms', 'email'].filter(function (c) { return tpls[c] && tpls[c].length; });
                if (!chans.length) { toast('This message has no editable text: its wording is built into the screen.'); return; }
                LOGO = String((j.sample && j.sample.URL_LINK) || '').replace(/[^\w.\-\/]/g, '');
                var builtIn = (j.channels || []).filter(function (c) { return chans.indexOf(c) < 0; });
                var pick = {}, values = {}, enabled = {}, startEnabled = {};
                chans.forEach(function (c) {
                    var rows = tpls[c], r = (rowId && rows.filter(function (x) { return x.id === rowId; })[0]) || rows.filter(function (x) { return !x.missing; })[0] || rows[0];
                    pick[c] = r.id; values[c] = valueOf(c, r);
                    if (!j.locked) { enabled[c] = j.enabled[c] !== false; startEnabled[c] = enabled[c]; }
                });
                if (rowId && !ch0) chans.forEach(function (c) { if (pick[c] === rowId && !ch0) ch0 = c; });
                function valueOf(c, r) {
                    if (c === 'email') return { subject: r.subject || '', preheader: '', design: designIn(r.design) || (r.missing ? { settings: {}, blocks: [] } : { settings: {}, blocks: [{ id: 'b1', type: 'html', props: { code: urlIn(r.body || ''), bg: '', pt: 0, pb: 0, px: 0 } }] }) };
                    return { body: r.body || '' };
                }
                function rowOf(c, id) { return (tpls[c] || []).filter(function (x) { return x.id === id; })[0]; }
                var extra = {};
                chans.forEach(function (c) {
                    extra[c] = function (box, ed) {
                        function draw() {
                            var rows = tpls[c], r = rowOf(c, pick[c]);
                            box.innerHTML = '<div class="ca-leg">' +
                                (rows.length > 1 ? '<label class="sl-field"><span class="sl-lab">Template row</span><select class="sl-in" data-leg-pick>' + rows.map(function (x) { return '<option value="' + x.id + '"' + (x.id === pick[c] ? ' selected' : '') + '>#' + x.id + ' ' + esc(x.missing ? '(missing)' : x.name) + '</option>'; }).join('') + '</select></label>' : '') +
                                '<p class="mut sm">Saved in the old ' + CH[c] + ' template #' + pick[c] + (r && r.name ? ' “' + esc(r.name) + '”' : '') + '. The screen fills the [TAGS]; keep them as they are.' + (c === 'whatsapp' ? '' : '') + '</p>' +
                                (r && r.missing ? '<div class="sl-banner"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>This template row is missing, so this ' + CH[c] + ' message is not sent. ' + (cfg.canEdit ? (r.draft ? 'A drafted message is filled in: check it and save to create it' : 'Write it here and save to create it') + (AI.ok ? ', or let ' + esc(KING) + ' draft it' : '') + '.' : 'An administrator can write it here.') + '</span></div>' : '') +
                                (c === chans[0] && builtIn.length ? '<p class="mut sm">' + builtIn.map(function (b) { return CH[b]; }).join(' and ') + ' wording is built into this screen: only its switch is here, on the Automations list.</p>' : '') +
                                '</div>';
                            var src = {}; src[c] = r && r.missing && r.draft ? 'drafted' : ''; showSources(ed, src);
                            var sel = $('[data-leg-pick]', box);
                            if (sel) sel.addEventListener('change', function () {
                                var id = +sel.value;
                                if (ed.isDirty(c) && !confirm('Switch template? Unsaved changes are lost.')) { sel.value = pick[c]; return; }
                                pick[c] = id; ed.setValue(c, valueOf(c, rowOf(c, id))); ed.markClean(c); draw();
                            });
                        }
                        draw();
                        box.__draw = draw;
                    };
                });
                var ed = CE.open({
                    title: j.label, subtitle: 'Old screen · ' + (j.desc || ''), key: key, channels: chans, channel: chans.indexOf(ch0) >= 0 ? ch0 : chans[0],
                    values: values, enabled: j.locked ? undefined : enabled, tags: j.tags, tagSyntax: 'bracket', sample: j.sample,
                    contact: { name: (j.sample && j.sample.NAME) || 'Customer', phone: 'online' }, sender: { sms: 'SWIFTLANE' },
                    canvas: Object.assign(canvasCfg(), { tagSyntax: 'bracket' }), king: kingOpts(), extra: extra,
                    labels: { reset: 'Undo changes' },
                    onSave: cfg.canEdit ? function (v) {
                        var data = { action: 'legacy_save', key: key, channel: v.channel, id: pick[v.channel], subject: v.subject || '' };
                        if (v.channel === 'email') { data.html = urlOut(v.html || ''); data.design_json = urlOut(v.design_json || ''); if (!data.html) return { success: false, message: 'Open the e-mail first.' }; }
                        else data.body = v.body || '';
                        var rule = (!j.locked && v.enabled !== null && v.enabled !== undefined && v.enabled !== startEnabled[v.channel])
                            ? post({ action: 'save_rule', key: key, channel: v.channel, value: v.enabled ? 1 : 0 }) : Promise.resolve({ success: true });
                        return rule.then(function (rr) {
                            if (!rr || !rr.success) return rr;
                            startEnabled[v.channel] = v.enabled; setRowToggle(key, v.channel, v.enabled);
                            return post(data).then(function (r) {
                                if (r && r.success && r.templates) { tpls = r.templates; var b = $('.ce-p-' + v.channel + ' [data-extra]', ed.el); if (b && b.__draw) b.__draw(); }
                                return r;
                            });
                        });
                    } : null,
                    onTest: cfg.canEdit ? function (v) { return test(key, v, urlOut); } : null,
                    onReset: cfg.canEdit ? function (c) { return { success: true, message: 'Back to the saved text.', value: valueOf(c, rowOf(c, pick[c])) }; } : null
                });
                ed.el.classList.add('ca-ed');
                var drafted = {}; chans.forEach(function (c) { var r = rowOf(c, pick[c]); drafted[c] = r && r.missing && r.draft ? 'drafted' : ''; }); showSources(ed, drafted);
                lockInputs(ed);
                if (j.unassigned) ed.status('No trigger on this page uses these rows; a screen may still send one. Choose the row, edit and save.');
                else if (j.locked) ed.status('Security messages are always sent; only their wording can change.');
                if (!cfg.canEdit) ed.status('You can read these messages; an administrator changes them.');
            }).catch(function (e) { toast(e.message || 'Network error', true); });
        }

        /* ---------- wiring: Automations ---------- */
        root.addEventListener('click', function (e) {
            var b = e.target.closest('[data-ca-edit]');
            if (b) { var k = b.getAttribute('data-ca-edit'); (k.indexOf('legacy:') === 0 ? openLegacy : openTrigger)(k, b.getAttribute('data-label') || k); return; }
            var tr, ch;
            var o = e.target.closest('[data-cs-open]');
            if (o) { tr = o.closest('[data-ca-row]'); ch = o.getAttribute('data-cs-open'); }
            var ed = e.target.closest('[data-cs-edit]');
            if (ed) tr = ed.closest('[data-ca-row]');
            if (!tr) return;
            if (ch === 'inapp') openInapp(tr); else openTrigger(tr.getAttribute('data-key'), '', ch);
        });
        if (cfg.page === 'automations' && (cfg.edit || cfg.editAsked)) {
            var m = /^status:(\d+)$/.exec(cfg.edit);
            if (m) { location.replace(cfg.statusesUrl + '?status=' + m[1]); return; }
            var btn = cfg.edit ? $('[data-ca-edit="' + cfg.edit + '"]', root) : null;
            var trow = cfg.edit ? $('[data-ca-row][data-key="' + cfg.edit + '"]', root) : null;
            if (btn) {
                var gp = btn.closest('[data-ca-group]'); if (gp && gp.hidden) { segBtns[0] && segBtns[0].click(); }
                btn.closest('[data-ca-row]').scrollIntoView({ block: 'center' });
                (cfg.edit.indexOf('legacy:') === 0 ? openLegacy : openTrigger)(cfg.edit, btn.getAttribute('data-label') || cfg.edit, cfg.ch || undefined, cfg.tplId || 0);
            } else if (trow) {
                if (trow.hidden) { segBtns[0] && segBtns[0].click(); }
                trow.scrollIntoView({ block: 'center' }); trow.classList.add('ca-hl');
                toast($('.ca-nt', trow).textContent.trim() + ': its wording is set elsewhere (see the row); only its switches are here.');
            } else {
                toast('No message called “' + (cfg.editAsked || cfg.edit) + '” was found. Showing all automations.', true);
            }
        }

        /* ---------- Status messages: in-app line ---------- */
        var inDlg = document.getElementById('cs-inapp'), inKey = '', inKing = null, inChips = null;
        function inSample() { return inDlg.__sample || {}; }
        function inPreview() {
            var ta = $('[data-inapp-body]', inDlg), txt = ta.value;
            $('[data-inapp-pv]', inDlg).textContent = CE ? CE.fill(txt, inSample()) : txt;
            $('[data-inapp-n]', inDlg).textContent = txt.length + ' / 200';
        }
        function openInapp(tr) {
            if (!inDlg) return;
            inKey = tr.getAttribute('data-key');
            post({ action: 'template_get', key: inKey }).then(function (j) {
                if (!j || !j.success) { toast(fail(j, 'Could not open the in-app line.'), true); return; }
                inDlg.__sample = j.sample || {};
                $('[data-inapp-sub]', inDlg).textContent = (j.status ? j.status.label : '') + ' · shown in the notification bell';
                var ta = $('[data-inapp-body]', inDlg); ta.value = (j.inapp && j.inapp.body) || '';
                $('[data-inapp-on]', inDlg).checked = !j.inapp || j.inapp.on;
                ta.readOnly = !cfg.canEdit; $('[data-inapp-on]', inDlg).disabled = !cfg.canEdit;
                ta.setSelectionRange && ta.setSelectionRange(ta.value.length, ta.value.length);
                var tags = {}; Object.keys((j.tags && j.tags.sms) || {}).forEach(function (k) { if (!/pickup_code|_html$|items/.test(k)) tags[k] = j.tags.sms[k]; });
                if (CE) {
                    if (!inChips) inChips = CE.tagChips($('[data-inapp-chips]', inDlg), tags, { syntax: 'brace', target: function () { return ta; },
                        onInsert: function (tag, f) {
                            if (!f || f.readOnly) return false;
                            var at = typeof f.selectionStart === 'number' ? f.selectionStart : f.value.length, before = f.value.slice(0, at);
                            CE.insertAtCursor(f, (before && !/\s$/.test(before) ? ' ' : '') + tag);
                            return false;
                        } }); else inChips.set(tags);
                    var kb = $('[data-inapp-king]', inDlg); kb.innerHTML = ''; var host = document.createElement('div'); kb.appendChild(host);
                    inKing = CE.king(host, Object.assign({}, kingOpts(), { key: inKey, channel: 'inapp', sample: j.sample,
                        getCurrent: function () { return { body: ta.value }; },
                        onAccept: function (p) { ta.value = String(p.body != null ? p.body : (p.text || '')).replace(/\{pickup_code(_html)?\}/g, '').replace(/ {2,}/g, ' ').trim(); inPreview(); } }));
                }
                $('[data-inapp-st]', inDlg).textContent = '';
                inPreview(); inDlg.hidden = false; ta.focus();
            });
        }
        if (inDlg) {
            $('[data-inapp-body]', inDlg).addEventListener('input', inPreview);
            var sv = $('[data-inapp-save]', inDlg), rs = $('[data-inapp-reset]', inDlg), st = $('[data-inapp-st]', inDlg);
            if (sv) sv.addEventListener('click', function () {
                sv.disabled = true; st.textContent = 'Saving…';
                post({ action: 'inapp_save', key: inKey, body: $('[data-inapp-body]', inDlg).value, enabled: $('[data-inapp-on]', inDlg).checked ? 1 : 0 }).then(function (r) {
                    st.textContent = fail(r, r && r.success ? 'Saved' : 'Not saved'); toast(st.textContent, !(r && r.success));
                    if (r && r.success) { applyCells(inKey, r.cells); inDlg.hidden = true; }
                }).catch(function (e) { st.textContent = e.message; }).finally(function () { sv.disabled = false; });
            });
            if (rs) rs.addEventListener('click', function () {
                if (!confirm('Go back to the predefined in-app line?')) return;
                post({ action: 'inapp_reset', key: inKey }).then(function (r) {
                    if (r && r.success) { $('[data-inapp-body]', inDlg).value = r.body || ''; $('[data-inapp-on]', inDlg).checked = true; inPreview(); applyCells(inKey, r.cells); }
                    st.textContent = fail(r, 'Done'); toast(st.textContent, !(r && r.success));
                });
            });
        }

        /* ---------- Status messages: redraft all with King ---------- */
        var rd = document.getElementById('cs-redraft');
        var rdBtn = $('[data-cs-redraft]', root);
        if (rd && rdBtn) {
            var go = $('[data-rd-go]', rd), stop = $('[data-rd-stop]', rd), accAll = $('[data-rd-accept-all]', rd), list = $('[data-rd-list]', rd),
                prog = $('[data-rd-prog]', rd), bar = $('[data-rd-bar]', rd), nEl = $('[data-rd-n]', rd), stEl = $('[data-rd-st]', rd), running = false, halt = false, jobs = [];
            rdBtn.addEventListener('click', function () { rd.hidden = false; });
        }
        if (rd && rdBtn && go) {
            function item(job) {
                var li = document.createElement('li'); li.className = 'cs-rd-it';
                li.innerHTML = '<div class="row between g8 wrap"><b class="fw5 sm">' + esc(job.label) + ' · ' + CH[job.ch] + '</b><span class="row g6" data-acts></span></div><div class="cs-rd-pv" data-pv></div>';
                job.li = li; list.appendChild(li); return li;
            }
            function render(job) {
                var pv = $('[data-pv]', job.li), acts = $('[data-acts]', job.li), p = job.proposal;
                if (job.error) { pv.innerHTML = '<span class="sm cs-rd-bad">' + esc(job.error) + '</span>'; acts.innerHTML = ''; return; }
                if (!p) { pv.innerHTML = '<span class="mut sm">Waiting…</span>'; return; }
                if (job.ch === 'email') {
                    pv.innerHTML = '<span class="sm"><span class="mut">Subject</span> ' + esc(CE ? CE.fill(p.subject || '', job.sample) : p.subject) + '</span><span class="mut sm">' + (p.blocks || []).length + ' blocks</span>';
                } else {
                    pv.innerHTML = '<div class="cs-rd-bub ' + job.ch + '">' + (CE ? (job.ch === 'sms' ? CE.smsHtml(p.body, job.sample) : CE.waHtml(p.body, job.sample)) : esc(p.body)) + '</div>';
                }
                if ((p.warnings || []).length) pv.innerHTML += '<ul class="cs-rd-w sm">' + p.warnings.map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('') + '</ul>';
                acts.innerHTML = job.done ? '<span class="sl-badge ' + (job.done === 'saved' ? 'ok' : '') + '">' + (job.done === 'saved' ? 'Saved' : 'Skipped') + '</span>'
                    : '<button type="button" class="sl-btn sm" data-acc>Accept</button><button type="button" class="sl-btn ghost sm" data-skip>Skip</button>';
                var a = $('[data-acc]', acts), s = $('[data-skip]', acts);
                if (a) a.addEventListener('click', function () { accept(job); });
                if (s) s.addEventListener('click', function () { job.done = 'skipped'; render(job); });
            }
            function accept(job) {
                var p = job.proposal, data = { action: 'template_save', key: job.key, channel: job.ch, language: 'en', subject: p.subject || '', via_ai: 1, enabled: '' };
                if (job.ch === 'email') data.blocks = JSON.stringify(p.blocks || []); else data.body = p.body || '';
                return post(data).then(function (r) {
                    if (r && r.success) { job.done = 'saved'; applyCells(job.key, r.cells); } else job.error = fail(r, 'Not saved');
                    render(job);
                });
            }
            function finish() {
                running = false; go.disabled = false; stop.hidden = true;
                var waiting = jobs.filter(function (x) { return x.proposal && !x.done && !x.error; }).length;
                accAll.hidden = !waiting;
                stEl.textContent = halt ? 'Stopped.' : 'Done: ' + jobs.filter(function (x) { return x.proposal; }).length + ' proposals, ' + jobs.filter(function (x) { return x.error; }).length + ' failed.';
            }
            go.addEventListener('click', function () {
                if (running) return;
                var chans = $$('[data-rd-ch]', rd).filter(function (i) { return i.checked; }).map(function (i) { return i.value; });
                if (!chans.length) { stEl.textContent = 'Choose at least one channel.'; return; }
                var which = ($$('[data-rd-which]', rd).filter(function (i) { return i.checked; })[0] || {}).value || 'all';
                var rows = $$('[data-ca-row]', root).filter(function (r) { return which === 'all' || !r.hidden; });
                var tone = $('[data-rd-tone]', rd).value, ins = $('[data-rd-ins]', rd).value.trim();
                if (rows.length * chans.length > 20 && !confirm(KING + ' will write ' + rows.length * chans.length + ' proposals, one request each. Continue?')) return;
                jobs = []; list.innerHTML = ''; list.hidden = false; prog.hidden = false; halt = false; running = true; go.disabled = true; stop.hidden = false; accAll.hidden = true;
                rows.forEach(function (r) { chans.forEach(function (c) { jobs.push({ key: r.getAttribute('data-key'), label: $('.ca-nt', r).textContent.trim(), ch: c, sample: null }); }); });
                jobs.forEach(function (jb) { item(jb); render(jb); });
                var i = 0;
                (function next() {
                    if (halt || i >= jobs.length) { finish(); return; }
                    var jb = jobs[i++];
                    nEl.textContent = 'Writing ' + i + ' of ' + jobs.length + ': ' + jb.label + ' (' + CH[jb.ch] + ')';
                    bar.style.width = Math.round(100 * (i - 1) / jobs.length) + '%';
                    post({ action: 'ai', key: jb.key, channel: jb.ch, tone: tone, instruction: ins }).then(function (r) {
                        if (r && r.success) { jb.proposal = (r.proposals || {})[jb.ch]; if (!jb.proposal) jb.error = KING + ' answered without a proposal.'; }
                        else {
                            jb.error = fail(r, KING + ' could not write this one.');
                            if (r && (r.code === 'not_configured' || r.code === 'not_allowed')) halt = true;
                        }
                        render(jb);
                    }).catch(function (e) { jb.error = e.message || 'Network error'; render(jb); }).then(function () { bar.style.width = Math.round(100 * i / jobs.length) + '%'; next(); });
                })();
            });
            stop.addEventListener('click', function () { halt = true; stEl.textContent = 'Stopping after this one…'; });
            accAll.addEventListener('click', function () {
                var todo = jobs.filter(function (x) { return x.proposal && !x.done && !x.error; });
                if (!todo.length) return;
                accAll.disabled = true; stEl.textContent = 'Saving ' + todo.length + '…';
                todo.reduce(function (p, jb) { return p.then(function () { return accept(jb); }); }, Promise.resolve())
                    .then(function () { stEl.textContent = 'Saved ' + todo.filter(function (x) { return x.done === 'saved'; }).length + ' messages.'; accAll.hidden = true; })
                    .finally(function () { accAll.disabled = false; });
            });
        }
        if (cfg.page === 'statuses' && cfg.open) {
            var tr0 = $('[data-ca-row][data-status="' + cfg.open + '"]', root);
            if (tr0) { tr0.scrollIntoView({ block: 'center' }); openTrigger(tr0.getAttribute('data-key'), ''); }
        } else if (cfg.page === 'statuses' && cfg.openAsked) {
            toast('Status #' + cfg.openAsked + ' was not found. It may have been deleted.', true);
        }
        if (cfg.page === 'statuses' && location.hash === '#inapp') {
            var th = $('th.ca-chh:nth-child(5)', root); if (th) th.scrollIntoView({ block: 'nearest', inline: 'center' });
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
