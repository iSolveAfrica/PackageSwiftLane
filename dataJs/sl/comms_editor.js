/* Shared comms editor kit (WP-B): WhatsApp / SMS / e-mail message editors used by
 * Automations, Status messages and Send. Plain JS, no jQuery. Needs swiftlane.js (SL.post,
 * SL.toast); the e-mail tab also needs dataJs/sl/email_builder.js and the markup of
 * views/sl/inc/email_canvas.php included with $ec['template'] = true. Styles:
 * assets/swiftlane/comms_editor.css. Phone markup: views/sl/inc/comms_phone.php (or built here).
 *
 * window.SLCommsEditor
 *   open(options)            -> editor   the editor in a right-hand drawer (overlay, Esc closes)
 *   mount(el, options)       -> editor   the same editor inline in el (no overlay, no close button)
 *   phone(el, options)       -> phone    a WhatsApp or SMS phone preview (fills or adopts the markup)
 *   tagChips(el, tags, opts) -> chips    placeholder chips that insert at the cursor of a field
 *   king(el, options)        -> panel    "Write with King": instruction, tone, proposal, Accept / Try again / Discard
 *   smsCount(text)           -> {chars, length, parts, perPart, remaining, encoding:'GSM-7'|'Unicode', unicodeChars}
 *   waHtml(text, sample)     -> safe HTML of a WhatsApp message (*bold* _italic_ ~strike~ ```mono```, links)
 *   fill(text, sample)       -> text with {placeholders} / [TAGS] replaced by sample values (unknown ones kept)
 *   insertAtCursor(field, s)    put s at the caret of an input/textarea (fires 'input')
 *   blocksToDesign(blocks)   -> canvas design from Notification Studio blocks (mirror of cdp_commBlocksToCanvas)
 *   designToBlocks(design)   -> studio blocks from a canvas design (AI context for the studio endpoint)
 *
 * Editor options (open / mount):
 *   title, subtitle            drawer heading
 *   key                        trigger key ('status:12', 'claim_reminder', 'manual', ...): posted to King
 *   channels                   ['whatsapp', 'sms', 'email'] (tab order; default all three)
 *   channel                    the tab to show first
 *   values                     { whatsapp: {body, media:{file,kind,name,url}}, sms: {body},
 *                                email: {subject, preheader, design (object) | design_json (string)} }
 *   enabled                    { whatsapp: true, ... }: shows a "Send this channel" switch per tab
 *   tags                       { tracking: 'Tracking number', ... } for every channel, or
 *                              { whatsapp: {...}, sms: {...}, email: {...} }; keys with or without braces.
 *                              *_html tags are offered on e-mail only.
 *   tagSyntax                  'brace' (default, {tracking}) | 'bracket' ([TRACKING])
 *   sample                     { name: 'Ama Mensah', tracking: 'SL123', ... } fills previews
 *   contact                    { name, phone } the WhatsApp header (default 'Customer')
 *   sender                     { name, sms: 'SWIFTLANE' } the SMS sender ID / e-mail From name
 *   media                      { uploadUrl: 'ajax/tools/push_media_upload_ajax.php', field: 'media' } WhatsApp attachment
 *                              (answer {success, file, kind, name, url}); omitted = no attachment
 *   canvas                     options for SLEmailCanvas.mount (default {tagSyntax:'brace', saved:false})
 *   king                       false | { url, params: {op:'ai'} | {action:'ai'}, name, tones: [[key,label],...],
 *                                enabled: bool, message, setupUrl, payload: fn(channel, value, editor) -> extra fields }
 *                              POST: params + key, channel, tone, instruction, subject, body, preheader,
 *                                    design_json, blocks (studio JSON), text (visible words of the e-mail)
 *                              answer: {success, proposals: {<channel>: {subject, body|text, design|design_json|blocks,
 *                                       warnings[], preview{subject, html}}}, assistant, code, message, setup_url}
 *                              code 'not_configured' / 'not_allowed' disables the panel with the message.
 *   extra                      { whatsapp: fn(panelEl, editor), ... } add page fields (e.g. Cloud template name)
 *   onSave(payload, editor)    -> Promise|result. Shows "Save"; result {success:false, message} = error.
 *                              payload: {channel, enabled, subject, preheader, body, design_json, html, text, media,
 *                                        via_ai}
 *   onSaveAll(payloads, editor) optional: "Save all" saves every channel at once (payloads by channel)
 *   onTest(payload, editor)    optional "Send test"
 *   onReset(channel, editor)   optional "Reset": may resolve to {value: {...}} to load into the tab
 *   onClose(editor)            after the drawer closed
 *   labels                     { save, saveAll, test, reset, close }
 *
 * Editor methods: el, channel(), show(ch), getValue(ch), getValues(), setValue(ch, value),
 *   setEnabled(ch, bool), isDirty(ch?), markClean(ch?), canvas (the e-mail SLEmailCanvas, null until the
 *   e-mail tab is first shown), phones {whatsapp, sms}, on(event, fn), off, close(force), destroy().
 * Events (editor.on and DOM CustomEvents 'ce:<name>' on editor.el): change {channel, value}, tab {channel},
 *   save {channel, result}, accept {channel, proposal}, close {}.
 */
(function () {
    'use strict';

    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function clone(o) { return JSON.parse(JSON.stringify(o)); }
    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
    function toast(m, bad) { if (window.SL && SL.toast) SL.toast(m, bad); }
    function post(url, data) {
        if (window.SL && SL.post) return SL.post(url, data);
        return Promise.reject(new Error('swiftlane.js is not loaded'));
    }
    function errText(r, d) { return (r && r.errors && r.errors.length && r.errors.join) ? r.errors.join(' ') : (r && r.message) || d || 'Something went wrong.'; }
    var CH = {
        whatsapp: { label: 'WhatsApp', icon: 'i-wa' },
        sms: { label: 'SMS', icon: 'i-message' },
        email: { label: 'E-mail', icon: 'i-mail' }
    };
    var KING = function (o) { return (o && o.name) || window.SL_KING_NAME || 'King'; };
    var TONES = [['keep', 'Keep tone'], ['warmer', 'Warmer'], ['formal', 'More formal'], ['shorter', 'Shorter'], ['urgent', 'More urgent'], ['reassuring', 'Reassuring']];

    // =====================================================================
    // Text helpers: SMS counter, placeholders, WhatsApp formatting
    // =====================================================================
    var GSM = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
    var GSM_EXT = '^{}\\[~]|€\f';
    function smsCount(text) {
        text = String(text == null ? '' : text);
        var units = 0, gsm = true, odd = [];
        Array.from(text).forEach(function (c) {
            if (GSM.indexOf(c) >= 0) units += 1;
            else if (GSM_EXT.indexOf(c) >= 0) units += 2;
            else { gsm = false; if (odd.indexOf(c) < 0 && odd.length < 8) odd.push(c); }
        });
        if (!gsm) units = text.length;              // UCS-2: UTF-16 code units (an emoji counts 2)
        var single = gsm ? 160 : 70, multi = gsm ? 153 : 67;
        var parts = units === 0 ? 0 : (units <= single ? 1 : Math.ceil(units / multi));
        var per = parts > 1 ? multi : single;
        return { chars: units, length: text.length, parts: parts, perPart: per, remaining: parts ? per * parts - units : single, encoding: gsm ? 'GSM-7' : 'Unicode', unicodeChars: odd };
    }
    var PH_RE = /\{([a-z][a-z0-9_]{0,39})\}|\[([A-Z][A-Z0-9_]{1,39})\]/g;
    function sampleOf(sample, m, brace, bracket) {
        if (!sample) return null;
        var k = brace || bracket, v = sample[k];
        if (v == null && bracket) v = sample[bracket.toLowerCase()];
        if (v == null && brace) v = sample[brace.toUpperCase()];
        return v == null || v === '' ? null : String(v);
    }
    function fill(text, sample) { return String(text == null ? '' : text).replace(PH_RE, function (m, a, b) { var v = sampleOf(sample, m, a, b); return v == null ? m : v; }); }
    /** Escaped text with placeholders as chips; fmt(htmlEscaped) adds formatting. Placeholders are parked first so formatting never breaks them. */
    function richText(text, sample, fmt) {
        var parked = [];
        var s = String(text == null ? '' : text).replace(PH_RE, function (m, a, b) { parked.push([m, sampleOf(sample, m, a, b)]); return '' + (parked.length - 1) + ''; });
        s = esc(s);
        if (fmt) s = fmt(s);
        s = s.replace(/\n/g, '<br>');
        return s.replace(/(\d+)/g, function (x, i) { var p = parked[+i]; return p[1] == null ? '<span class="cp-ph empty" title="Filled in for each customer">' + esc(p[0]) + '</span>' : '<span class="cp-ph" title="' + esc(p[0]) + '">' + esc(p[1]) + '</span>'; });
    }
    function waFormat(s) {
        var code = [];
        s = s.replace(/```([\s\S]+?)```/g, function (m, c) { code.push(c); return '' + (code.length - 1) + ''; });
        var L = '(^|[\\s(\\uE001>])', Rt = '(?=$|[\\s.,!?:;)\\uE000<])';
        s = s.replace(new RegExp(L + '\\*(?!\\s)([^*\\n]+?)\\*' + Rt, 'g'), '$1<b>$2</b>');
        s = s.replace(new RegExp(L + '_(?!\\s)([^_\\n]+?)_' + Rt, 'g'), '$1<i>$2</i>');
        s = s.replace(new RegExp(L + '~(?!\\s)([^~\\n]+?)~' + Rt, 'g'), '$1<s>$2</s>');
        s = s.replace(/(^|[\s(])((?:https?:\/\/|www\.)[^\s<]+[^\s<.,!?:;)])/g, '$1<span class="cp-link">$2</span>');
        return s.replace(/(\d+)/g, function (m, i) { return '<code>' + code[+i] + '</code>'; });
    }
    function waHtml(text, sample) { return richText(text, sample, waFormat); }
    function smsHtml(text, sample) { return richText(text, sample, function (s) { return s.replace(/(^|[\s(])((?:https?:\/\/|www\.)[^\s<]+[^\s<.,!?:;)])/g, '$1<span class="cp-link">$2</span>'); }); }
    function fillHtml(html, sample) {
        return String(html || '').replace(PH_RE, function (m, a, b) { var v = sampleOf(sample, m, a, b); if (v == null) return m; return /_html$/.test(a || '') ? v : esc(v); });
    }
    function insertAtCursor(f, text) {
        if (!f) return false;
        if (f.isContentEditable) { f.focus(); document.execCommand('insertText', false, text); return true; }
        var s = typeof f.selectionStart === 'number' ? f.selectionStart : f.value.length, e = typeof f.selectionEnd === 'number' ? f.selectionEnd : s;
        f.value = f.value.slice(0, s) + text + f.value.slice(e);
        f.focus();
        try { f.setSelectionRange(s + text.length, s + text.length); } catch (x) {}
        f.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }
    /** Wrap the selection of a textarea in a WhatsApp marker (*, _, ~, ```). */
    function wrapSel(f, mark) {
        var s = f.selectionStart, e = f.selectionEnd, v = f.value, mid = v.slice(s, e) || (mark === '```' ? 'code' : 'text');
        f.value = v.slice(0, s) + mark + mid + mark + v.slice(e);
        f.focus(); try { f.setSelectionRange(s + mark.length, s + mark.length + mid.length); } catch (x) {}
        f.dispatchEvent(new Event('input', { bubbles: true }));
    }
    function initials(n) { return String(n || '').trim().split(/\s+/).map(function (w) { return w.charAt(0); }).join('').slice(0, 2).toUpperCase() || '?'; }
    function clock() { var d = new Date(); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }

    // =====================================================================
    // Phone preview
    // =====================================================================
    var STATUS_BAR = '<div class="cp-st" aria-hidden="true"><span data-cp-time></span><span class="cp-ic"><i class="sig"><b></b><b></b><b></b><b></b></i><i class="wifi"></i><i class="bat"><b></b></i></span></div>';
    function phoneHtml(ch, o) {
        var wa = ch !== 'sms', name = (o && o.name) || (wa ? 'Customer' : 'SWIFTLANE'), sub = (o && o.sub != null) ? o.sub : (wa ? 'online' : 'Text message');
        if (wa) {
            return '<div class="cp cp-wa" data-cp="whatsapp" role="figure" aria-label="WhatsApp preview"><div class="cp-sc">' + STATUS_BAR +
                '<div class="cp-hd"><span class="cp-back" aria-hidden="true">&#8249;</span><span class="cp-av" data-cp-av>' + esc(initials(name)) + '</span><span class="cp-who"><b data-cp-name>' + esc(name) + '</b><small data-cp-sub>' + esc(sub) + '</small></span><span class="cp-acts" aria-hidden="true"><i class="cp-i-vid"></i><i class="cp-i-call"></i><i class="cp-i-dots"></i></span></div>' +
                '<div class="cp-bd" data-cp-body><span class="cp-day">Today</span><span class="cp-e2e">Messages and calls are end-to-end encrypted.</span>' +
                '<div class="cp-bub out" data-cp-bubble><div class="cp-media" data-cp-media hidden></div><div class="cp-txt" data-cp-text></div><span class="cp-meta"><span data-cp-clock></span><span class="cp-ticks read" data-cp-ticks aria-label="Read">&#10003;&#10003;</span></span></div>' +
                '<div class="cp-btns" data-cp-btns hidden></div></div>' +
                '<div class="cp-in" aria-hidden="true"><span class="cp-field"><i class="cp-i-emoji"></i>Message<i class="cp-i-clip"></i></span><span class="cp-mic"><i class="cp-i-mic"></i></span></div></div></div>';
        }
        return '<div class="cp cp-sms" data-cp="sms" role="figure" aria-label="SMS preview"><div class="cp-sc">' + STATUS_BAR +
            '<div class="cp-hd"><span class="cp-av" data-cp-av>' + esc(initials(name)) + '</span><b data-cp-name>' + esc(name) + '</b><small data-cp-sub>' + esc(sub) + '</small></div>' +
            '<div class="cp-bd" data-cp-body><span class="cp-day">Text Message · Today <span data-cp-clock></span></span><div class="cp-bub in" data-cp-bubble><div class="cp-txt" data-cp-text></div></div><div class="cp-parts" data-cp-parts hidden></div></div>' +
            '<div class="cp-in" aria-hidden="true"><span class="cp-field">Text Message</span><span class="cp-mic"><i class="cp-i-up"></i></span></div></div>' +
            ((o && o.counter === false) ? '' : '<div class="cp-count" data-cp-count aria-live="polite"></div>') + '</div>';
    }
    var TICKS = { pending: ['&#128339;', 'Pending'], sent: ['&#10003;', 'Sent'], delivered: ['&#10003;&#10003;', 'Delivered'], read: ['&#10003;&#10003;', 'Read'] };
    function mediaHtml(m) {
        if (!m) return '';
        var kind = m.kind || (/\.(jpe?g|png|gif|webp)$/i.test(m.name || m.url || '') ? 'image' : (/\.(mp4|3gp|mov)$/i.test(m.name || m.url || '') ? 'video' : 'document'));
        if (kind === 'image' && m.url) return '<img src="' + esc(m.url) + '" alt="' + esc(m.name || 'Image') + '">';
        if (kind === 'image' || kind === 'video') return '<div class="cp-mph ' + kind + '"><span>' + (kind === 'video' ? '&#9654; Video' : 'Image') + '</span>' + (m.name ? '<small>' + esc(m.name) + '</small>' : '') + '</div>';
        var ext = String(m.name || 'file').split('.').pop().toUpperCase().slice(0, 4);
        return '<div class="cp-doc"><span class="cp-doc-ic">' + esc(ext) + '</span><span class="cp-doc-n">' + esc(m.name || 'Document') + '</span></div>';
    }
    function phone(target, o) {
        o = o || {};
        if (typeof target === 'string') target = document.querySelector(target);
        if (!target) throw new Error('SLCommsEditor.phone: element not found');
        var root = target.matches('[data-cp]') ? target : target.querySelector('[data-cp]');
        if (!root) { target.innerHTML = phoneHtml(o.channel || 'whatsapp', o); root = target.querySelector('[data-cp]'); }
        var ch = root.getAttribute('data-cp'), q = function (a) { return root.querySelector('[' + a + ']'); };
        var st = { text: o.text || '', sample: o.sample || null, media: o.media || null, buttons: o.buttons || null };
        var t = clock();
        $$('[data-cp-time]', root).forEach(function (x) { if (!x.textContent) x.textContent = t; });
        $$('[data-cp-clock]', root).forEach(function (x) { if (!x.textContent) x.textContent = t; });
        function render() {
            var txt = q('data-cp-text'), empty = !String(st.text || '').trim() && !st.media;
            txt.innerHTML = empty ? '<span class="cp-empty">' + esc(o.placeholder || 'Your message appears here') + '</span>' : (ch === 'sms' ? smsHtml(st.text, st.sample) : waHtml(st.text, st.sample));
            var m = q('data-cp-media'); if (m) { m.hidden = !st.media; m.innerHTML = mediaHtml(st.media); }
            var b = q('data-cp-btns'); if (b) { b.hidden = !(st.buttons && st.buttons.length); b.innerHTML = (st.buttons || []).map(function (x) { return '<span>' + esc(x) + '</span>'; }).join(''); }
            if (ch === 'sms') {
                var c = smsCount(fill(st.text, st.sample)), cnt = q('data-cp-count'), parts = q('data-cp-parts');
                if (cnt) cnt.innerHTML = c.parts ? '<b>' + c.chars + '</b> characters · <b>' + c.parts + '</b> part' + (c.parts === 1 ? '' : 's') + ' · <span class="cp-enc ' + (c.encoding === 'GSM-7' ? 'gsm' : 'uni') + '">' + c.encoding + '</span>' + (c.encoding === 'Unicode' ? '<span class="cp-uni">Unicode because of ' + c.unicodeChars.map(function (x) { return '“' + esc(x) + '”'; }).join(' ') + ': 70 characters per part, not 160.</span>' : '') + (st.sample ? '<small>Counted with the sample values filled in.</small>' : '') : '<span class="mut">Empty message</span>';
                if (parts) { parts.hidden = c.parts < 2; parts.textContent = c.parts > 1 ? 'Sent as ' + c.parts + ' joined messages (' + c.perPart + ' characters each)' : ''; }
            }
        }
        var api = {
            el: root, channel: ch,
            set: function (text, opts) { st.text = text == null ? '' : String(text); if (opts) { if ('media' in opts) st.media = opts.media; if ('buttons' in opts) st.buttons = opts.buttons; if ('sample' in opts) st.sample = opts.sample; } render(); return api; },
            setSample: function (s) { st.sample = s; render(); return api; },
            setMedia: function (m) { st.media = m || null; render(); return api; },
            setContact: function (name, sub) { var n = q('data-cp-name'), s = q('data-cp-sub'), a = q('data-cp-av'); if (name != null) { n.textContent = name; a.textContent = initials(name); } if (sub != null && s) s.textContent = sub; return api; },
            setTicks: function (state) { var tk = q('data-cp-ticks'), d = TICKS[state] || TICKS.read; if (tk) { tk.innerHTML = d[0]; tk.className = 'cp-ticks ' + (state || 'read'); tk.setAttribute('aria-label', d[1]); } return api; },
            count: function () { return smsCount(fill(st.text, st.sample)); }
        };
        render();
        return api;
    }

    // =====================================================================
    // Tag chips
    // =====================================================================
    function tagKey(k, bracket) { k = String(k); if (/^[\[{]/.test(k)) return k; return bracket ? '[' + k.toUpperCase() + ']' : '{' + k + '}'; }
    function tagChips(container, tags, o) {
        o = o || {};
        if (typeof container === 'string') container = document.querySelector(container);
        var api, cur = tags || {};
        function draw() {
            var keys = Object.keys(cur || {});
            container.classList.add('ce-chips');
            container.innerHTML = keys.length ? keys.map(function (k) { var l = cur[k], label = Array.isArray(l) ? l[0] : l, tag = tagKey(k, o.syntax === 'bracket'); return '<button type="button" class="ce-chip" data-tag="' + esc(tag) + '" title="' + esc((label || '') + ' — click to insert') + '"><code>' + esc(tag) + '</code>' + (o.labels && label ? '<span>' + esc(label) + '</span>' : '') + '</button>'; }).join('') : '<span class="mut sm">' + esc(o.empty || 'No placeholders for this message.') + '</span>';
        }
        container.addEventListener('mousedown', function (e) { if (e.target.closest('[data-tag]')) e.preventDefault(); });   // keep the caret where it is
        container.addEventListener('click', function (e) {
            var b = e.target.closest('[data-tag]'); if (!b) return;
            var tag = b.getAttribute('data-tag'), f = typeof o.target === 'function' ? o.target() : o.target;
            if (o.onInsert && o.onInsert(tag, f) === false) return;
            if (f && f.nodeType === 1) insertAtCursor(f, tag);
            else { if (navigator.clipboard) navigator.clipboard.writeText(tag).catch(function () {}); toast(tag + ' copied'); }
        });
        draw();
        api = { el: container, set: function (t) { cur = t || {}; draw(); return api; } };
        return api;
    }

    // =====================================================================
    // Studio blocks <-> canvas design
    // =====================================================================
    function paras(t) { var out = String(t || '').replace(/\r\n/g, '\n').split(/\n{2,}/).filter(function (p) { return p.trim() !== ''; }).map(function (p) { return '<p>' + esc(p).replace(/\n/g, '<br>') + '</p>'; }).join(''); return out || '<p></p>'; }
    function safeUrl(u) { u = String(u || '').trim(); return /^(https?:\/\/|\{[a-z_]+\}$|\/|assets\/)/i.test(u) && !/^javascript:/i.test(u) ? u : ''; }
    function blocksToDesign(blocks) {
        var n = 0, id = function () { return 'b' + Date.now().toString(36).slice(-4) + (++n); }, pad = { bg: '', pt: 8, pb: 8, px: 32 }, out = [];
        var A = function (a, b) { return Object.assign({}, a, b); };
        (Array.isArray(blocks) ? blocks : []).forEach(function (b) {
            if (!b || typeof b !== 'object') return;
            var t = String(b.text == null ? '' : b.text);
            switch (b.type) {
                case 'header': out.push({ id: id(), type: 'heading', props: { text: esc(t || '{company}'), size: 20, color: '', align: 'left', weight: '700', bg: '', pt: 24, pb: 12, px: 32 } }); break;
                case 'heading': out.push({ id: id(), type: 'heading', props: { text: esc(t), size: 26, color: '', align: 'left', weight: '700', bg: '', pt: 16, pb: 8, px: 32 } }); break;
                case 'text': out.push({ id: id(), type: 'text', props: A({ html: paras(t), size: 16, color: '', align: 'left', lh: 1.6 }, pad) }); break;
                case 'footer': out.push({ id: id(), type: 'footer', props: { html: paras(t), size: 12, color: '#8392A5', align: 'center', bg: '', pt: 24, pb: 24, px: 32 } }); break;
                case 'button': out.push({ id: id(), type: 'button', props: { label: esc(t || 'Track your shipment'), href: safeUrl(b.url) || '{track_url}', color: '', textColor: '', align: 'left', radius: 8, size: 16, full: false, bg: '', pt: 16, pb: 16, px: 32 } }); break;
                case 'image': if (safeUrl(b.url)) out.push({ id: id(), type: 'image', props: { src: safeUrl(b.url), alt: String(b.alt || ''), width: 100, align: 'center', href: '', radius: 8, bg: '', pt: 8, pb: 8, px: 32 } }); break;
                case 'divider': out.push({ id: id(), type: 'divider', props: { color: '#E1E5EA', thickness: 1, bg: '', pt: 16, pb: 16, px: 32 } }); break;
                case 'spacer': out.push({ id: id(), type: 'spacer', props: { height: 24, bg: '' } }); break;
                case 'status_card':
                    var line = function (k, v, opt) { return '<tr' + (opt ? ' data-ns-opt="row"' : '') + '><td style="padding:6px 0;font-size:13px;color:#77736B;width:42%">' + esc(k) + '</td><td style="padding:6px 0;font-size:15px;font-weight:600;color:#1E1D1A">' + v + '</td></tr>'; };
                    out.push({ id: id(), type: 'html', props: A({ code: '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F5F0;border-radius:10px;padding:8px 16px">' + line('Tracking', '{tracking}') + line('Status', '{status}') + line('Location', '{location}', 1) + line('Carrier tracking', '{carrier_tracking}', 1) + line('Weight', '{weight}', 1) + line('Estimated arrival', '{eta}', 1) + '</table>', slBlock: 'status_card' }, pad) });
                    break;
                case 'items': out.push({ id: id(), type: 'html', props: A({ code: '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr data-ns-opt="items"><td><div style="font-size:13px;color:#77736B;margin-bottom:4px">Items</div>{items_html}</td></tr></table>', slBlock: 'items' }, pad) }); break;
                case 'pickup_code': out.push({ id: id(), type: 'html', props: A({ code: '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr data-ns-opt="code"><td>{pickup_code_html}</td></tr></table>', slBlock: 'pickup_code' }, pad) }); break;
            }
        });
        return { settings: { bodyBg: '#F4F2EE', contentBg: '#FFFFFF', font: 'Arial, Helvetica, sans-serif', textColor: '#3A3935', headingColor: '#1E1D1A', linkColor: '#0077B6', accent: '#1E1D1A' }, blocks: out };
    }
    function plain(html) { var d = document.createElement('div'); d.innerHTML = String(html || '').replace(/<\/p>\s*<p[^>]*>/gi, '\n\n').replace(/<br\s*\/?>/gi, '\n'); return (d.textContent || '').trim(); }
    function designToBlocks(design) {
        var out = [];
        ((design && design.blocks) || []).forEach(function (b, i) {
            var p = b.props || {};
            switch (b.type) {
                case 'heading': out.push({ type: i === 0 && +p.size <= 22 ? 'header' : 'heading', text: plain(p.text) }); break;
                case 'text': out.push({ type: 'text', text: plain(p.html) }); break;
                case 'footer': out.push({ type: 'footer', text: plain(p.html) }); break;
                case 'button': out.push({ type: 'button', text: plain(p.label), url: p.href || '{track_url}' }); break;
                case 'image': out.push({ type: 'image', url: p.src || '', alt: p.alt || '' }); break;
                case 'divider': out.push({ type: 'divider' }); break;
                case 'spacer': out.push({ type: 'spacer' }); break;
                case 'html': if (p.slBlock) out.push({ type: p.slBlock }); else out.push({ type: 'text', text: plain(p.code) }); break;
                case 'imagetext': out.push({ type: 'text', text: plain((p.title ? p.title + '\n\n' : '') + p.html) }); break;
                case 'columns': (p.cols || []).forEach(function (c) { out.push({ type: 'text', text: plain((c.title ? c.title + '\n\n' : '') + c.html) }); }); break;
            }
        });
        return out.filter(function (b) { return !('text' in b) || b.text !== '' || b.type === 'button'; });
    }

    // =====================================================================
    // Write with King
    // =====================================================================
    function king(container, o) {
        o = o || {};
        if (typeof container === 'string') container = document.querySelector(container);
        var name = KING(o), tones = o.tones || TONES, busy = false, ctrl = null, last = null, disabled = o.enabled === false;
        container.classList.add('ce-king');
        container.innerHTML =
            '<div class="ce-k-hd"><span class="ce-k-av" aria-hidden="true">&#10022;</span><b>Write with ' + esc(name) + '</b><span class="ce-k-ai">AI</span></div>' +
            '<p class="ce-k-intro mut sm">Tell ' + esc(name) + ' what to change, pick a tone, and review the proposal before it goes into the editor. Nothing is saved until you save.</p>' +
            '<label class="sl-field"><span class="sl-lab">Instruction <span class="mut">(optional)</span></span><textarea class="ce-ta ce-k-ins" rows="2" maxlength="400" placeholder="e.g. Mention our opening hours and keep it under two sentences"></textarea></label>' +
            '<div class="ce-k-tones" role="radiogroup" aria-label="Tone">' + tones.map(function (t, i) { return '<button type="button" role="radio" class="ce-tone' + (i === 0 ? ' on' : '') + '" aria-checked="' + (i === 0) + '" data-tone="' + esc(t[0]) + '">' + esc(t[1]) + '</button>'; }).join('') + '</div>' +
            '<div class="row g8 wrap"><button type="button" class="sl-btn ce-k-go"><span class="ce-k-spark" aria-hidden="true">&#10022;</span><span>Write with ' + esc(name) + '</span></button><button type="button" class="sl-btn ghost sm ce-k-stop" hidden>Stop</button></div>' +
            '<div class="ce-k-msg" role="status" aria-live="polite" hidden></div>' +
            '<div class="ce-k-prop" hidden><div class="ce-k-ph"><b>' + esc(name) + '\'s proposal</b><span class="mut sm ce-k-meta"></span></div><div class="ce-k-pv"></div><ul class="ce-k-warn" hidden></ul>' +
            '<div class="row g8 wrap"><button type="button" class="sl-btn sm ce-k-acc"><i class="ic ic-sm i-check" aria-hidden="true"></i>Accept</button><button type="button" class="sl-btn ghost sm ce-k-again"><i class="ic ic-sm i-reply" aria-hidden="true"></i>Try again</button><button type="button" class="sl-btn ghost sm ce-k-dis"><i class="ic ic-sm i-x" aria-hidden="true"></i>Discard</button></div></div>';
        var q = function (s) { return container.querySelector(s); }, go = q('.ce-k-go'), msg = q('.ce-k-msg'), prop = q('.ce-k-prop');
        function say(text, kind, link) { msg.hidden = !text; msg.className = 'ce-k-msg' + (kind ? ' ' + kind : ''); msg.innerHTML = text ? esc(text) + (link ? ' <a href="' + esc(link) + '">Open the AI centre</a>' : '') : ''; }
        function setDisabled(text, link) { disabled = true; go.disabled = true; say(text, 'warn', link); }
        if (disabled) setDisabled(o.message || name + ' is not set up yet.', o.setupUrl);
        if (!o.url) setDisabled('No AI endpoint was given for this editor.');
        q('.ce-k-tones').addEventListener('click', function (e) { var b = e.target.closest('[data-tone]'); if (!b) return; $$('[data-tone]', container).forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-checked', String(x === b)); }); });
        function tone() { var b = q('.ce-tone.on'); return b ? b.getAttribute('data-tone') : 'keep'; }
        function channel() { return typeof o.channel === 'function' ? o.channel() : (o.channel || 'whatsapp'); }
        function showProposal(p, ch) {
            last = { proposal: p, channel: ch };
            var pv = q('.ce-k-pv'), w = p.warnings || [];
            if (ch === 'email') {
                var design = p.design || (p.design_json ? (function () { try { return JSON.parse(p.design_json); } catch (e) { return null; } })() : null) || (p.blocks ? blocksToDesign(p.blocks) : null);
                p._design = design;
                var html = (p.preview && p.preview.html) || (design && window.SLEmailCanvas ? fillHtml(SLEmailCanvas.previewHtml(design, null, p.subject || ''), o.sample) : '');
                pv.innerHTML = '<div class="ce-k-subj"><span class="mut sm">Subject</span><b></b></div><iframe class="ce-k-frame" sandbox="" title="Proposed e-mail"></iframe>';
                pv.querySelector('b').textContent = fill((p.preview && p.preview.subject) || p.subject || '', o.sample) || '(no subject)';
                pv.querySelector('iframe').srcdoc = html || '<p style="font:14px sans-serif;color:#777">No preview</p>';
            } else {
                var text = p.body != null ? p.body : (p.text || '');
                var c = smsCount(fill(text, o.sample));
                pv.innerHTML = '<div class="ce-k-bub ' + (ch === 'sms' ? 'sms' : 'wa') + '">' + (ch === 'sms' ? smsHtml(text, o.sample) : waHtml(text, o.sample)) + '</div>' + (ch === 'sms' ? '<span class="mut sm">' + c.chars + ' characters · ' + c.parts + ' part' + (c.parts === 1 ? '' : 's') + ' · ' + c.encoding + '</span>' : '');
            }
            var wl = q('.ce-k-warn'); wl.hidden = !w.length; wl.innerHTML = w.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('');
            q('.ce-k-meta').textContent = (p.model ? p.model + ' · ' : '') + 'not saved';
            prop.hidden = false;
            prop.scrollIntoView && prop.scrollIntoView({ block: 'nearest' });
        }
        function run() {
            if (busy || disabled) return;
            var ch = channel(), cur = typeof o.getCurrent === 'function' ? (o.getCurrent(ch) || {}) : {};
            var data = Object.assign({}, o.params || {}, { key: o.key || '', channel: ch, tone: tone(), instruction: q('.ce-k-ins').value.trim() });
            ['subject', 'body', 'preheader', 'design_json', 'blocks', 'text'].forEach(function (k) { if (cur[k] != null) data[k] = typeof cur[k] === 'object' ? JSON.stringify(cur[k]) : cur[k]; });
            if (typeof o.payload === 'function') Object.assign(data, o.payload(ch, cur) || {});
            busy = true; go.disabled = true; go.classList.add('loading'); q('.ce-k-stop').hidden = false; prop.hidden = true;
            say(name + ' is writing…', 'busy');
            var timedOut = false; ctrl = window.AbortController ? new AbortController() : null;
            var timer = setTimeout(function () { timedOut = true; if (ctrl) ctrl.abort(); }, o.timeout || 90000);
            var req = ctrl ? fetchJson(o.url, data, ctrl.signal) : post(o.url, data);
            req.then(function (j) {
                if (!j || !j.success) {
                    if (j && (j.code === 'not_configured' || j.code === 'not_allowed')) { setDisabled(j.message || name + ' is not available.', j.setup_url); return; }
                    say(errText(j, name + ' could not write a proposal.'), 'bad'); return;
                }
                var p = (j.proposals && (j.proposals[ch] || j.proposals[Object.keys(j.proposals)[0]])) || j.proposal;
                if (!p) { say(name + ' answered without a proposal. Try again.', 'bad'); return; }
                if (j.model && !p.model) p.model = j.model;
                say('');
                showProposal(p, ch);
            }).catch(function (err) {
                if (err && err.name === 'AbortError') say(timedOut ? name + ' did not answer in time. Try again.' : 'Stopped.', timedOut ? 'bad' : '');
                else say((err && err.message) || 'Network error. Try again.', 'bad');
            }).finally(function () { clearTimeout(timer); busy = false; ctrl = null; if (!disabled) go.disabled = false; go.classList.remove('loading'); q('.ce-k-stop').hidden = true; });
        }
        go.addEventListener('click', run);
        q('.ce-k-stop').addEventListener('click', function () { if (ctrl) ctrl.abort(); });
        q('.ce-k-again').addEventListener('click', run);
        q('.ce-k-dis').addEventListener('click', function () { prop.hidden = true; last = null; say('Proposal discarded.'); });
        q('.ce-k-acc').addEventListener('click', function () {
            if (!last) return;
            var p = last.proposal;
            if (typeof o.onAccept === 'function') o.onAccept(p, last.channel);
            prop.hidden = true; last = null; say('Accepted into the editor. Review it, then save.', 'ok');
        });
        return {
            el: container,
            run: run,
            disable: setDisabled,
            proposal: function () { return last; },
            setSample: function (s) { o.sample = s; }
        };
    }
    function fetchJson(url, data, signal) {
        var m = document.querySelector('meta[name="csrf-token"]'), pm = document.querySelector('meta[name="csrf-param"]'), token = m ? m.content : '';
        var body = new URLSearchParams(); Object.keys(data).forEach(function (k) { body.append(k, data[k]); }); body.append(pm ? pm.content : 'csrf_token', token);
        return fetch(url, { method: 'POST', body: body, signal: signal, credentials: 'same-origin', headers: { 'X-CSRF-Token': token, 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) {
            if (r.redirected && /\/(login|auth-otp)\.php(?:[?#]|$)/.test(r.url)) throw new Error('Your session expired. Sign in again.');
            return r.text().then(function (t) { var j; try { j = JSON.parse(t); } catch (e) { j = null; } if (!j || typeof j !== 'object') j = { success: false, message: 'The server returned an unexpected response.' }; if (!r.ok) j.success = false; return j; });
        });
    }

    // =====================================================================
    // The editor (drawer or inline)
    // =====================================================================
    function tagsFor(tags, ch) {
        tags = tags || {};
        var per = ['whatsapp', 'sms', 'email'].some(function (c) { return tags[c] && typeof tags[c] === 'object' && !Array.isArray(tags[c]); });
        var t = per ? (tags[ch] || {}) : tags, out = {};
        Object.keys(t).forEach(function (k) { var bare = k.replace(/^[{\[]|[}\]]$/g, ''); if (ch !== 'email' && /_html$/i.test(bare)) return; out[k] = t[k]; });
        return out;
    }
    var uidN = 0;
    function build(host, o, drawer) {
        o = o || {};
        var channels = (o.channels || ['whatsapp', 'sms', 'email']).filter(function (c) { return CH.hasOwnProperty(c); });
        if (!channels.length) throw new Error('SLCommsEditor: no channels');
        var id = 'ce' + (++uidN), kingName = KING(o.king || {}), L = Object.assign({ save: 'Save', saveAll: 'Save all channels', test: 'Send test', reset: 'Reset to predefined', close: 'Close' }, o.labels || {});
        var values = {}, saved = {}, enabled = {}, dirty = {}, listeners = {}, phones = {}, fields = {}, kings = {}, lastField = {}, canvas = null, cur = null, viaAi = {};
        var syntax = o.tagSyntax === 'bracket' ? 'bracket' : 'brace';
        channels.forEach(function (c) { values[c] = Object.assign({}, (o.values || {})[c] || {}); enabled[c] = o.enabled && c in o.enabled ? !!o.enabled[c] : null; dirty[c] = false; });

        var root = el('section', 'ce' + (drawer ? ' ce-drawer' : ' ce-inline'));
        root.id = id;
        if (drawer) { root.setAttribute('role', 'dialog'); root.setAttribute('aria-modal', 'true'); root.setAttribute('aria-labelledby', id + '-t'); }
        root.innerHTML =
            (o.title || drawer ? '<header class="ce-hd"><div class="col" style="min-width:0"><h2 class="ce-title" id="' + id + '-t">' + esc(o.title || 'Edit message') + '</h2>' + (o.subtitle ? '<span class="mut sm ce-sub">' + esc(o.subtitle) + '</span>' : '') + '</div>' + (drawer ? '<button type="button" class="sl-ib ce-x" aria-label="' + esc(L.close) + '"><i class="ic ic-sm i-x" aria-hidden="true"></i></button>' : '') + '</header>' : '') +
            '<div class="ce-tabs" role="tablist" aria-label="Channels">' + channels.map(function (c) { return '<button type="button" role="tab" class="ce-tab" id="' + id + '-tab-' + c + '" aria-controls="' + id + '-p-' + c + '" aria-selected="false" data-ch="' + c + '"><i class="ic ic-sm ' + CH[c].icon + '" aria-hidden="true"></i><span>' + CH[c].label + '</span><span class="ce-state" data-state></span><span class="ce-dot" data-dirty hidden title="Unsaved changes"></span></button>'; }).join('') + '</div>' +
            '<div class="ce-body">' + channels.map(function (c) { return '<div class="ce-panel ce-p-' + c + '" role="tabpanel" id="' + id + '-p-' + c + '" aria-labelledby="' + id + '-tab-' + c + '" data-panel="' + c + '" hidden></div>'; }).join('') + '</div>' +
            ((o.onSave || o.onTest || o.onReset || drawer) ? '<footer class="ce-ft"><span class="mut sm ce-status" role="status" aria-live="polite"></span><span class="row g8 wrap">' +
                (o.onReset ? '<button type="button" class="sl-btn ghost ce-reset">' + esc(L.reset) + '</button>' : '') +
                (o.onTest ? '<button type="button" class="sl-btn ghost ce-test"><i class="ic ic-sm i-send" aria-hidden="true"></i>' + esc(L.test) + '</button>' : '') +
                (drawer ? '<button type="button" class="sl-btn ghost ce-cancel">' + esc(L.close) + '</button>' : '') +
                (o.onSaveAll && channels.length > 1 ? '<button type="button" class="sl-btn ghost ce-saveall">' + esc(L.saveAll) + '</button>' : '') +
                (o.onSave ? '<button type="button" class="sl-btn ce-save"><i class="ic ic-sm i-check" aria-hidden="true"></i>' + esc(L.save) + '</button>' : '') +
                '</span></footer>' : '');
        var q = function (s) { return root.querySelector(s); };
        var panel = function (c) { return q('[data-panel="' + c + '"]'); };
        var overlay = null, prevFocus = document.activeElement;

        function emit(n, d) { (listeners[n] || []).slice().forEach(function (f) { try { f(d, api); } catch (e) { if (window.console) console.error(e); } }); root.dispatchEvent(new CustomEvent('ce:' + n, { detail: d, bubbles: true })); }
        function status(t, bad) { var s = q('.ce-status'); if (s) { s.textContent = t || ''; s.classList.toggle('bad', !!bad); } }
        function setDirty(c, v) { dirty[c] = v; var d = q('#' + id + '-tab-' + c + ' [data-dirty]'); if (d) d.hidden = !v; }
        function stateBadge(c) { var s = q('#' + id + '-tab-' + c + ' [data-state]'); if (!s) return; if (enabled[c] === null) { s.textContent = ''; s.className = 'ce-state'; return; } s.textContent = enabled[c] ? 'On' : 'Off'; s.className = 'ce-state ' + (enabled[c] ? 'on' : 'off'); }
        function changed(c) { setDirty(c, true); emit('change', { channel: c, value: getValue(c) }); }

        /* ---------- shared bits of a panel ---------- */
        function switchRow(c) {
            if (enabled[c] === null) return '';
            return '<label class="ce-switch"><input type="checkbox" data-enabled' + (enabled[c] ? ' checked' : '') + '><span class="ce-sw" aria-hidden="true"></span><span><b class="fw5">Send by ' + CH[c].label + '</b><small class="mut">' + (c === 'email' ? 'Off: no e-mail is sent for this message.' : 'Off: no ' + CH[c].label + ' is sent for this message.') + '</small></span></label>';
        }
        function kingBox(c, p) {
            if (o.king === false || !o.king) return;
            var box = el('div'); p.querySelector('.ce-ed').appendChild(box);
            kings[c] = king(box, Object.assign({}, o.king, {
                key: o.key, channel: c, sample: o.sample,
                getCurrent: function () { var v = getValue(c), out = { subject: v.subject, body: v.body, preheader: v.preheader }; if (c === 'email') { out.design_json = v.design_json; out.blocks = designToBlocks(canvas ? canvas.getDesign() : null); out.text = v.text; } return out; },
                onAccept: function (pr) { acceptProposal(c, pr); }
            }));
        }
        function acceptProposal(c, pr) {
            if (c === 'email') {
                var d = pr._design || pr.design || (pr.design_json ? JSON.parse(pr.design_json) : null) || (pr.blocks ? blocksToDesign(pr.blocks) : null);
                if (pr.subject != null && fields.email && fields.email.subject) fields.email.subject.value = pr.subject;
                if (d && canvas) canvas.setDesign(d);
            } else {
                fields[c].body.value = pr.body != null ? pr.body : (pr.text || '');
                renderPhone(c);
            }
            viaAi[c] = true; changed(c);
            emit('accept', { channel: c, proposal: pr });
        }
        function renderPhone(c) { if (phones[c]) phones[c].set(fields[c].body.value, { media: c === 'whatsapp' ? values.whatsapp.media || null : undefined }); }

        /* ---------- WhatsApp and SMS ---------- */
        function textPanel(c) {
            var p = panel(c), v = values[c], wa = c === 'whatsapp';
            p.innerHTML = '<div class="ce-grid"><div class="ce-ed col g12">' + switchRow(c) +
                '<div class="sl-field"><span class="sl-lab" id="' + id + '-' + c + '-l">' + (wa ? 'WhatsApp message' : 'SMS text') + '</span>' +
                (wa ? '<div class="ce-fmt" role="toolbar" aria-label="Formatting"><button type="button" data-wrap="*" aria-label="Bold" title="Bold: *text*"><b>B</b></button><button type="button" data-wrap="_" aria-label="Italic" title="Italic: _text_"><i>I</i></button><button type="button" data-wrap="~" aria-label="Strikethrough" title="Strikethrough: ~text~"><s>S</s></button><button type="button" data-wrap="```" aria-label="Monospace" title="Monospace: ```text```"><code>{}</code></button><span class="mut sm">Select words, then a style</span></div>' : '') +
                '<textarea class="ce-ta ce-body-f" rows="' + (wa ? 9 : 6) + '" aria-labelledby="' + id + '-' + c + '-l"' + (c === 'sms' ? ' maxlength="1600"' : ' maxlength="4096"') + '></textarea>' +
                (c === 'sms' ? '<div class="ce-count" data-count aria-live="polite"></div>' : '') + '</div>' +
                '<div class="col g6"><span class="sl-lab">Placeholders <span class="mut">click to insert at the cursor</span></span><div data-chips></div></div>' +
                (wa && o.media && o.media.uploadUrl ? '<div class="ce-media row g8 wrap"><button type="button" class="sl-btn ghost sm" data-attach><i class="ic ic-sm i-upload" aria-hidden="true"></i>Attach image or document</button><span class="mut sm" data-media-n></span><button type="button" class="sl-btn ghost sm" data-unattach hidden aria-label="Remove attachment"><i class="ic ic-sm i-x" aria-hidden="true"></i></button><input type="file" data-file hidden accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/3gpp,video/quicktime,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt" aria-label="Attachment"></div>' : '') +
                '<div data-extra></div></div>' +
                '<aside class="ce-side"><div data-phone></div></aside></div>';
            var ta = p.querySelector('.ce-body-f');
            ta.value = v.body || '';
            fields[c] = { body: ta };
            phones[c] = phone(p.querySelector('[data-phone]'), { channel: c, name: wa ? ((o.contact && o.contact.name) || 'Customer') : ((o.sender && (o.sender.sms || o.sender.name)) || 'SWIFTLANE'), sub: wa ? ((o.contact && o.contact.phone) || 'online') : null, sample: o.sample, counter: false });
            tagChips(p.querySelector('[data-chips]'), tagsFor(o.tags, c), { syntax: syntax, target: function () { return ta; } });
            function count() {
                if (c !== 'sms') return;
                var raw = smsCount(ta.value), filled = smsCount(fill(ta.value, o.sample)), box = p.querySelector('[data-count]');
                box.innerHTML = '<span><b>' + raw.chars + '</b> characters as typed</span><span><b>' + filled.chars + '</b> with sample values · <b>' + filled.parts + '</b> part' + (filled.parts === 1 ? '' : 's') + ' (' + filled.perPart + ' per part)</span><span class="cp-enc ' + (filled.encoding === 'GSM-7' ? 'gsm' : 'uni') + '">' + filled.encoding + '</span>' + (filled.encoding === 'Unicode' ? '<span class="ce-uni">Unicode because of ' + filled.unicodeChars.map(function (x) { return '“' + esc(x) + '”'; }).join(' ') + ': each part holds 70 characters (67 when split) instead of 160. Replace ' + (filled.unicodeChars.length === 1 ? 'it' : 'them') + ' to fit more per part.</span>' : '');
                box.classList.toggle('warn', filled.parts > 1);
            }
            ta.addEventListener('input', function () { renderPhone(c); count(); changed(c); });
            ta.addEventListener('focus', function () { lastField[c] = ta; });
            if (wa) p.querySelector('.ce-fmt').addEventListener('click', function (e) { var b = e.target.closest('[data-wrap]'); if (b) wrapSel(ta, b.getAttribute('data-wrap')); });
            if (wa) p.querySelector('.ce-fmt').addEventListener('mousedown', function (e) { if (e.target.closest('[data-wrap]')) e.preventDefault(); });
            var sw = p.querySelector('[data-enabled]'); if (sw) sw.addEventListener('change', function () { enabled[c] = sw.checked; stateBadge(c); changed(c); });
            if (wa && o.media && o.media.uploadUrl) {
                var fi = p.querySelector('[data-file]'), nm = p.querySelector('[data-media-n]'), un = p.querySelector('[data-unattach]');
                var showM = function () { var m = values.whatsapp.media; nm.textContent = m ? (m.name || m.file || 'Attachment') : 'No attachment'; un.hidden = !m; renderPhone('whatsapp'); };
                p.querySelector('[data-attach]').addEventListener('click', function () { fi.click(); });
                un.addEventListener('click', function () { values.whatsapp.media = null; showM(); changed('whatsapp'); });
                fi.addEventListener('change', function () {
                    var f = fi.files[0]; fi.value = ''; if (!f) return;
                    var fd = new FormData(); fd.append(o.media.field || 'media', f); nm.textContent = 'Uploading…';
                    post(o.media.uploadUrl, fd).then(function (r) { if (!r || !r.success) { toast(errText(r, 'Upload failed'), true); showM(); return; } values.whatsapp.media = { file: r.file || '', kind: r.kind || '', name: r.name || f.name, url: r.url || '' }; showM(); changed('whatsapp'); })
                        .catch(function (err) { toast((err && err.message) || 'Upload failed', true); showM(); });
                });
                showM();
            }
            renderPhone(c); count();
            if (o.extra && typeof o.extra[c] === 'function') o.extra[c](p.querySelector('[data-extra]'), api);
            kingBox(c, p);
        }

        /* ---------- E-mail ---------- */
        function emailPanel() {
            var p = panel('email'), v = values.email;
            p.innerHTML = '<div class="ce-mail col g12">' + switchRow('email') +
                '<div class="ce-mhead"><label class="sl-field grow"><span class="sl-lab">Subject</span><input class="sl-in" data-subject maxlength="255" placeholder="What is this e-mail about?"></label>' +
                '<label class="sl-field grow"><span class="sl-lab">Preview text <span class="mut">(optional)</span></span><input class="sl-in" data-pre maxlength="255" placeholder="Shown after the subject in most inboxes"></label>' +
                '<button type="button" class="sl-btn ghost ce-pvb" data-preview><i class="ic ic-sm i-eye" aria-hidden="true"></i>Preview</button></div>' +
                '<div class="col g6"><span class="sl-lab">Placeholders <span class="mut">insert into the subject, or into the selected block\'s text</span></span><div data-chips></div></div>' +
                '<div data-canvas class="ce-canvas"></div><div data-extra></div><div class="ce-mking"></div></div>' +
                '<div class="ce-pv" data-pvbox hidden><div class="ce-pv-in sl-card col g12"><div class="row between wrap g8"><div class="col" style="min-width:0"><b class="fw5" data-pv-s></b><span class="mut sm" data-pv-p></span></div><div class="row g8"><div class="sl-seg boxed" data-pv-dev><button type="button" class="on" data-w="100%">Desktop</button><button type="button" data-w="375px">Phone</button></div><button type="button" class="sl-btn ghost sm" data-pv-x aria-label="Close preview"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div></div><div class="ce-pv-stage"><iframe data-pv-f sandbox="allow-popups allow-popups-to-escape-sandbox" title="E-mail preview"></iframe></div><span class="mut sm">Placeholders show the sample values; each customer gets their own.</span></div></div>';
            var subj = p.querySelector('[data-subject]'), pre = p.querySelector('[data-pre]');
            subj.value = v.subject || ''; pre.value = v.preheader || '';
            fields.email = { subject: subj, preheader: pre };
            [subj, pre].forEach(function (f) { f.addEventListener('focus', function () { lastField.email = f; }); f.addEventListener('input', function () { changed('email'); }); });
            var tags = tagsFor(o.tags, 'email');
            tagChips(p.querySelector('[data-chips]'), tags, { syntax: syntax, onInsert: function (tag) {
                var f = lastField.email;
                if (f && (f === subj || f === pre)) { insertAtCursor(f, tag); return false; }
                if (canvas) { canvas.insertTag(tag); return false; }
            } });
            var sw = p.querySelector('[data-enabled]'); if (sw) sw.addEventListener('change', function () { enabled.email = sw.checked; stateBadge('email'); changed('email'); });
            var host = p.querySelector('[data-canvas]');
            if (!window.SLEmailCanvas || !document.querySelector('template[data-ec-template]')) {
                host.innerHTML = '<div class="sl-banner warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The e-mail builder is not on this page (include views/sl/inc/email_canvas.php with template on, and dataJs/sl/email_builder.js).</span></div>';
            } else {
                var design = v.design || null;
                if (!design && v.design_json) { try { design = JSON.parse(v.design_json); } catch (e) { design = null; } }
                if (!design && v.blocks) design = blocksToDesign(v.blocks);
                canvas = SLEmailCanvas.mount(host, Object.assign({ tagSyntax: syntax, saved: false, tags: tags, design: design || undefined, start: design ? undefined : 'blank' }, o.canvas || {}));
                canvas.__ceLoaded = true;   // a later template / AI design counts as a change; setValue() loads quietly
                canvas.on('change', function (d) { if (d.reason !== 'load' || canvas.__ceLoaded) changed('email'); canvas.__ceLoaded = true; });
                api.canvas = canvas;
            }
            var box = p.querySelector('[data-pvbox]'), fr = p.querySelector('[data-pv-f]');
            p.querySelector('[data-preview]').addEventListener('click', function () {
                if (!canvas) return;
                box.querySelector('[data-pv-s]').textContent = fill(subj.value, o.sample) || '(no subject)';
                box.querySelector('[data-pv-p]').textContent = fill(pre.value, o.sample);
                fr.srcdoc = fillHtml(canvas.getPreviewHtml(subj.value, pre.value), o.sample);
                box.hidden = false; box.querySelector('[data-pv-x]').focus();
            });
            box.querySelector('[data-pv-x]').addEventListener('click', function () { box.hidden = true; });
            box.addEventListener('click', function (e) { if (e.target === box) box.hidden = true; });
            box.querySelector('[data-pv-dev]').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; $$('button', this).forEach(function (x) { x.classList.toggle('on', x === b); }); fr.style.width = b.getAttribute('data-w'); });
            if (o.extra && typeof o.extra.email === 'function') o.extra.email(p.querySelector('[data-extra]'), api);
            if (o.king && o.king !== false) { var kb = el('div'); p.querySelector('.ce-mking').appendChild(kb); p.querySelector('.ce-mking').classList.add('ce-ed');
                kings.email = king(kb, Object.assign({}, o.king, { key: o.key, channel: 'email', sample: o.sample,
                    getCurrent: function () { var val = getValue('email'); return { subject: val.subject, preheader: val.preheader, design_json: val.design_json, blocks: designToBlocks(canvas ? canvas.getDesign() : null), text: val.text }; },
                    onAccept: function (pr) { acceptProposal('email', pr); } })); }
        }

        var built = {};
        function show(c) {
            if (channels.indexOf(c) < 0) c = channels[0];
            if (!built[c]) { built[c] = true; if (c === 'email') emailPanel(); else textPanel(c); }
            cur = c;
            channels.forEach(function (x) { var t = q('#' + id + '-tab-' + x), on = x === c; t.classList.toggle('on', on); t.setAttribute('aria-selected', String(on)); t.tabIndex = on ? 0 : -1; panel(x).hidden = !on; });
            var r = q('.ce-reset'); if (r) r.hidden = false;
            status('');
            emit('tab', { channel: c });
        }
        q('.ce-tabs').addEventListener('click', function (e) { var b = e.target.closest('[data-ch]'); if (b) show(b.getAttribute('data-ch')); });
        q('.ce-tabs').addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
            var i = channels.indexOf(cur) + (e.key === 'ArrowRight' ? 1 : -1); i = (i + channels.length) % channels.length;
            show(channels[i]); q('#' + id + '-tab-' + channels[i]).focus(); e.preventDefault();
        });

        function getValue(c) {
            c = c || cur;
            var out = { channel: c, enabled: enabled[c], via_ai: !!viaAi[c] };
            if (!built[c]) { return Object.assign(out, clone(values[c] || {}), { untouched: true }); }
            if (c === 'email') {
                out.subject = fields.email.subject.value; out.preheader = fields.email.preheader.value;
                if (canvas) { var d = canvas.getDesign(); out.design = d; out.design_json = JSON.stringify(d); out.html = canvas.getHtml(out.subject, out.preheader); out.text = canvas.getText(); }
                out.body = out.html || '';
            } else {
                out.body = fields[c].body.value;
                if (c === 'whatsapp') out.media = values.whatsapp.media || null;
                if (c === 'sms') out.count = smsCount(fill(out.body, o.sample));
            }
            return out;
        }
        function setValue(c, v) {
            values[c] = Object.assign({}, values[c] || {}, v || {});
            if (!built[c]) return api;
            if (c === 'email') {
                if (v.subject != null) fields.email.subject.value = v.subject;
                if (v.preheader != null) fields.email.preheader.value = v.preheader;
                var d = v.design || (v.design_json ? JSON.parse(v.design_json) : null) || (v.blocks ? blocksToDesign(v.blocks) : null);
                if (d && canvas) { canvas.__ceLoaded = false; canvas.setDesign(d); }
            } else if (v.body != null) {
                fields[c].body.value = v.body; fields[c].body.dispatchEvent(new Event('input'));
            }
            if (c === 'whatsapp' && 'media' in (v || {})) renderPhone('whatsapp');
            return api;
        }
        function busy(btn, on) { if (!btn) return; btn.disabled = on; btn.classList.toggle('loading', on); }
        function settle(res, okMsg, c) {
            return Promise.resolve(res).then(function (r) {
                if (r && (r.success === false || r.ok === false)) { status(errText(r, 'Not saved'), true); toast(errText(r, 'Not saved'), true); return r; }
                status((r && r.message) || okMsg); toast((r && r.message) || okMsg);
                return r || { success: true };
            });
        }
        function wire(sel, fn) { var b = q(sel); if (b) b.addEventListener('click', function () { fn(b); }); }
        wire('.ce-save', function (b) {
            var c = cur, v = getValue(c);
            if (c === 'email' && v.enabled !== false && !String(v.subject || '').trim()) { status('Enter a subject line.', true); fields.email.subject.focus(); return; }
            if (c !== 'email' && v.enabled !== false && !String(v.body || '').trim() && !(v.media)) { status('Write the message first.', true); fields[c].body.focus(); return; }
            busy(b, true); status('Saving…');
            settle(o.onSave(v, api), 'Saved', c).then(function (r) { if (!r || (r.success !== false && r.ok !== false)) { setDirty(c, false); viaAi[c] = false; } emit('save', { channel: c, result: r }); })
                .catch(function (err) { status((err && err.message) || 'Not saved', true); toast((err && err.message) || 'Not saved', true); }).finally(function () { busy(b, false); });
        });
        wire('.ce-saveall', function (b) {
            var all = {}; channels.forEach(function (c) { all[c] = getValue(c); });
            busy(b, true); status('Saving…');
            settle(o.onSaveAll(all, api), 'All channels saved').then(function (r) { if (!r || (r.success !== false && r.ok !== false)) channels.forEach(function (c) { setDirty(c, false); viaAi[c] = false; }); emit('save', { channel: 'all', result: r }); })
                .catch(function (err) { status((err && err.message) || 'Not saved', true); }).finally(function () { busy(b, false); });
        });
        wire('.ce-test', function (b) {
            busy(b, true); status('Sending a test…');
            settle(o.onTest(getValue(cur), api), 'Test sent').catch(function (err) { status((err && err.message) || 'Test not sent', true); }).finally(function () { busy(b, false); });
        });
        wire('.ce-reset', function (b) {
            var c = cur;
            if (!confirm('Go back to the predefined ' + CH[c].label + ' message? Your changes to it are replaced.')) return;
            busy(b, true);
            Promise.resolve(o.onReset(c, api)).then(function (r) {
                if (r && (r.success === false || r.ok === false)) { status(errText(r, 'Not reset'), true); return; }
                if (r && r.value) { setValue(c, r.value); }
                setDirty(c, false); status((r && r.message) || 'Back to the predefined message'); toast((r && r.message) || 'Back to the predefined message');
            }).catch(function (err) { status((err && err.message) || 'Not reset', true); }).finally(function () { busy(b, false); });
        });

        function isDirty(c) { return c ? !!dirty[c] : channels.some(function (x) { return dirty[x]; }); }
        function onKey(e) {
            if (e.key === 'Escape') { var pv = q('[data-pvbox]:not([hidden])'); if (pv) { pv.hidden = true; e.stopPropagation(); return; } close(); }
            if (e.key === 'Tab') {
                var f = $$('a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select,textarea,[tabindex]:not([tabindex="-1"])', root).filter(function (x) { return x.offsetParent !== null; });
                if (!f.length) return;
                if (e.shiftKey && document.activeElement === f[0]) { f[f.length - 1].focus(); e.preventDefault(); }
                else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { f[0].focus(); e.preventDefault(); }
            }
        }
        function close(force) {
            if (!drawer || !overlay) return true;
            if (!force && isDirty() && !confirm('Close without saving your changes?')) return false;
            overlay.classList.remove('in');
            document.removeEventListener('keydown', onKey, true);
            setTimeout(function () { destroy(); if (prevFocus && prevFocus.isConnected) prevFocus.focus(); }, 180);
            document.documentElement.classList.remove('ce-lock');
            emit('close', {}); if (typeof o.onClose === 'function') o.onClose(api);
            return true;
        }
        function destroy() { if (canvas) canvas.destroy(); if (overlay) overlay.remove(); else root.remove(); }
        if (drawer) {
            overlay = el('div', 'ce-ov');
            overlay.appendChild(root);
            (document.querySelector('.sl-app') || document.body).appendChild(overlay);
            overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) close(); });
            wire('.ce-x', function () { close(); }); wire('.ce-cancel', function () { close(); });
            document.addEventListener('keydown', onKey, true);
            document.documentElement.classList.add('ce-lock');
            requestAnimationFrame(function () { overlay.classList.add('in'); });
        } else {
            if (typeof host === 'string') host = document.querySelector(host);
            host.appendChild(root);
        }
        var api = {
            el: root, canvas: null, phones: phones, kings: kings,
            channel: function () { return cur; },
            show: function (c) { show(c); return api; },
            getValue: getValue,
            getValues: function () { var r = {}; channels.forEach(function (c) { r[c] = getValue(c); }); return r; },
            setValue: setValue,
            setEnabled: function (c, v) { enabled[c] = !!v; stateBadge(c); var s = built[c] && panel(c).querySelector('[data-enabled]'); if (s) s.checked = !!v; return api; },
            setSample: function (s) { o.sample = s; channels.forEach(function (c) { if (phones[c]) phones[c].setSample(s); if (kings[c]) kings[c].setSample(s); }); return api; },
            isDirty: isDirty,
            markClean: function (c) { (c ? [c] : channels).forEach(function (x) { setDirty(x, false); }); return api; },
            status: status,
            on: function (n, f) { (listeners[n] = listeners[n] || []).push(f); return api; },
            off: function (n, f) { listeners[n] = (listeners[n] || []).filter(function (x) { return x !== f; }); return api; },
            close: close,
            destroy: destroy
        };
        channels.forEach(stateBadge);
        show(o.channel || channels[0]);
        if (drawer) setTimeout(function () { var f = panel(cur).querySelector('textarea, input.sl-in'); (f || q('.ce-tab.on')).focus(); }, 60);
        return api;
    }

    window.SLCommsEditor = {
        open: function (o) { return build(null, o, true); },
        mount: function (host, o) { if (typeof host === 'string') host = document.querySelector(host); if (!host) throw new Error('SLCommsEditor.mount: element not found'); return build(host, o, false); },
        phone: phone,
        tagChips: tagChips,
        king: king,
        smsCount: smsCount,
        waHtml: waHtml,
        smsHtml: smsHtml,
        fill: fill,
        fillHtml: fillHtml,
        insertAtCursor: insertAtCursor,
        blocksToDesign: blocksToDesign,
        designToBlocks: designToBlocks,
        tones: TONES
    };
})();
