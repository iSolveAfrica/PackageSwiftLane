/* Drag-and-drop e-mail canvas + the Email builder page. Plain JS, no jQuery.
 *
 * window.SLEmailCanvas.mount(rootEl, options) mounts one canvas on the markup of
 * views/sl/inc/email_canvas.php (elements found by data-ec="..."), so a page can
 * hold several canvases (the comms editor drawer, dataJs/sl/comms_editor.js, uses it).
 * The Email builder page (views/sl/pages/EmailBuilder.php, newsletter.php) is the
 * same canvas plus subject, saving and sending; it auto-mounts when #eb-send exists.
 *
 * Design JSON ({ settings, blocks:[{id,type,props}] }) and the e-mail HTML renderer are the
 * same as the previous builder (dataJs/newsletter.js), so saved designs in
 * cdb_newsletter_designs open unchanged. Server: ajax/tools/newsletter_ajax.php.
 * The full API (options, methods, events) is documented above window.SLEmailCanvas below. */
(function () {
    'use strict';
    var GCFG = window.NLB_CONFIG || window.SL_EMAIL_CANVAS_CFG || {};
    var WIDTH = 600;
    /* Render context. Rendering is synchronous, so each canvas sets it (use()) right before it renders. */
    var R = { settings: null, cfg: GCFG };
    function uid() { return 'b' + Math.random().toString(36).slice(2, 10); }
    function clone(o) { return JSON.parse(JSON.stringify(o)); }
    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function num(v, d) { var n = parseFloat(v); return isNaN(n) ? d : n; }
    function onColor(bg) { var h = String(bg || '').replace('#', ''); if (h.length === 3) h = h.replace(/(.)/g, '$1$1'); if (h.length < 6) return '#192A3E'; var r = parseInt(h.substr(0, 2), 16), g = parseInt(h.substr(2, 2), 16), b = parseInt(h.substr(4, 2), 16); return (r * 299 + g * 587 + b * 114) / 1000 > 150 ? '#192A3E' : '#FFFFFF'; }
    function isColor(c) { return /^#[0-9a-f]{3,8}$/i.test(String(c || '')); }
    function col(c, d) { return isColor(c) ? c : d; }
    function absUrl(u) { u = String(u || '').trim(); if (!u || /^([a-z][a-z0-9+.-]*:|\/\/|#|\[|\{)/i.test(u)) return u; return (R.cfg.siteUrl || '') + '/' + u.replace(/^\.?\/+/, ''); }
    /** Remove anything active from HTML typed or pasted into a design. */
    function clean(html) {
        var doc = new DOMParser().parseFromString('<body>' + String(html || '') + '</body>', 'text/html');
        doc.body.querySelectorAll('script,iframe,object,embed,form,input,button,textarea,select,link,meta,base,style').forEach(function (x) { x.remove(); });
        doc.body.querySelectorAll('*').forEach(function (el) {
            Array.prototype.slice.call(el.attributes).forEach(function (a) {
                var n = a.name.toLowerCase(), v = String(a.value || '').replace(/[\x00-\x20]+/g, '').toLowerCase();
                if (n.indexOf('on') === 0 || n === 'contenteditable' || n.indexOf('data-nlb') === 0 || ((n === 'href' || n === 'src' || n === 'background') && /^(javascript|vbscript|data):/.test(v) && !/^data:image\//.test(v))) el.removeAttribute(a.name);
            });
        });
        return doc.body.innerHTML;
    }
    function blankSettings() { return { bodyBg: '#F6F7F9', contentBg: '#FFFFFF', font: 'Arial, Helvetica, sans-serif', textColor: '#3D4B5C', headingColor: '#192A3E', linkColor: '#0077B6', accent: '#FFCB01' }; }
    function errs(r, d) { return (r && r.errors && r.errors.length) ? r.errors.join(' ') : (r && r.message) || d || 'Something went wrong.'; }

    /* ---------- block types and renderer (unchanged from the previous builder) ---------- */
    var SOCIAL = {
        facebook: 'Facebook', instagram: 'Instagram', x: 'X', linkedin: 'LinkedIn',
        youtube: 'YouTube', tiktok: 'TikTok', whatsapp: 'WhatsApp', website: 'Website'
    };

    var TYPES = {
        logo:      { label: 'Logo',          icon: 'solar:crown-minimalistic-linear', d: { src: '', alt: '', width: 160, align: 'center', href: '[URL]', bg: '', pt: 24, pb: 24, px: 32 } },
        heading:   { label: 'Heading',       icon: 'solar:text-bold-linear',          d: { text: 'Your headline here', size: 28, color: '', align: 'left', weight: '700', bg: '', pt: 16, pb: 8, px: 32 } },
        text:      { label: 'Text',          icon: 'solar:text-field-linear',         d: { html: '<p>Write your message here. Click to edit, select words to make them <b>bold</b> or add a link.</p>', size: 16, color: '', align: 'left', lh: 1.6, bg: '', pt: 8, pb: 8, px: 32 } },
        image:     { label: 'Image',         icon: 'solar:gallery-linear',            d: { src: 'assets/images/newsletter/hero-shipping.jpg', alt: '', width: 100, align: 'center', href: '', radius: 0, bg: '', pt: 0, pb: 0, px: 0 } },
        button:    { label: 'Button',        icon: 'solar:cursor-linear',             d: { label: 'Click here', href: '[URL]', color: '', textColor: '', align: 'center', radius: 6, size: 16, full: false, bg: '', pt: 16, pb: 16, px: 32 } },
        imagetext: { label: 'Image + Text',  icon: 'solar:posts-carousel-horizontal-linear', d: { src: 'assets/images/newsletter/card-air.jpg', side: 'left', title: 'A short title', html: '<p>A few lines about this picture.</p>', btn: 'Read more', href: '[URL]', bg: '', pt: 16, pb: 16, px: 32 } },
        columns:   { label: 'Columns',       icon: 'solar:widget-linear',             d: { cols: null, align: 'left', bg: '', pt: 16, pb: 16, px: 32 } },
        video:     { label: 'Video',         icon: 'solar:videocamera-record-linear', d: { url: 'https://www.youtube.com/', thumb: 'assets/images/newsletter/video-thumb.jpg', caption: 'Watch the video', bg: '', pt: 16, pb: 16, px: 32 } },
        divider:   { label: 'Divider',       icon: 'solar:minus-square-linear',       d: { color: '#E1E5EA', thickness: 1, bg: '', pt: 16, pb: 16, px: 32 } },
        spacer:    { label: 'Spacer',        icon: 'solar:square-transfer-vertical-linear', d: { height: 32, bg: '' } },
        social:    { label: 'Social Links',  icon: 'solar:share-linear',              d: { links: [{ net: 'facebook', url: '' }, { net: 'instagram', url: '' }, { net: 'x', url: '' }], align: 'center', color: '#192A3E', bg: '', pt: 16, pb: 16, px: 32 } },
        footer:    { label: 'Footer',        icon: 'solar:document-text-linear',      d: { html: '<p>[SITE_NAME]</p><p><a href="[UNSUBSCRIBE_URL]">Unsubscribe</a></p>', size: 12, color: '#8392A5', align: 'center', bg: '', pt: 24, pb: 24, px: 32 } },
        html:      { label: 'Custom HTML',   icon: 'solar:code-linear',               d: { code: '<p style="margin:0">Your own HTML</p>', bg: '', pt: 16, pb: 16, px: 32 } }
    };

    // Palette entries (two column layouts share the "columns" type).
    var PALETTE = [
        ['heading'], ['text'], ['image'], ['button'], ['imagetext'],
        ['columns', 2, '2 Columns', 'solar:sidebar-minimalistic-linear'],
        ['columns', 3, '3 Columns', 'solar:widget-linear'],
        ['video'], ['logo'], ['social'], ['divider'], ['spacer'], ['footer'], ['html']
    ];

    function defaultCols(n) {
        var c = [];
        for (var i = 0; i < n; i++) {
            c.push({ img: true, src: 'assets/images/newsletter/' + ['card-air.jpg', 'card-sea.jpg', 'card-warehouse.jpg'][i % 3], title: 'Column title', html: '<p>Some text for this column.</p>', btn: 'Read more', href: '[URL]' });
        }
        return c;
    }

    /** A new block of a type with its defaults; conv() rewrites [TAGS] for the canvas tag syntax. */
    function mkBlock(type, props, n, cfg, conv) {
        var t = TYPES[type];
        var p = Object.assign({}, conv ? convTags(clone(t.d), conv) : clone(t.d), clone(props || {}));
        if (type === 'columns' && !p.cols) p.cols = conv ? convTags(defaultCols(n || 2), conv) : defaultCols(n || 2);
        if (type === 'logo' && !p.src) p.src = (cfg || {}).logo || '';
        if (type === 'logo' && !p.alt) p.alt = (cfg || {}).siteName || '';
        return { id: uid(), type: type, props: p };
    }
    /** Deep copy of o with conv() applied to every string (used to turn [URL] into {track_url}, ...). */
    function convTags(o, conv) {
        if (typeof o === 'string') return conv(o);
        if (Array.isArray(o)) return o.map(function (x) { return convTags(x, conv); });
        if (o && typeof o === 'object') { var r = {}; Object.keys(o).forEach(function (k) { r[k] = convTags(o[k], conv); }); return r; }
        return o;
    }

    // =======================================================================
    // Rendering (e-mail HTML)
    // =======================================================================

    var EMAIL_CSS =
        'body{margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}' +
        'table{border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;}' +
        'img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;}' +
        'p{margin:0 0 12px 0;}' +
        'h1,h2,h3{margin:0;}' +
        '@media only screen and (max-width:620px){' +
        '.nl-container{width:100%!important;max-width:100%!important;}' +
        '.nl-col{display:block!important;width:100%!important;max-width:100%!important;padding-left:0!important;padding-right:0!important;padding-bottom:18px!important;}' +
        '.nl-img{width:100%!important;max-width:100%!important;height:auto!important;}' +
        '.nl-px{padding-left:20px!important;padding-right:20px!important;}' +
        '.nl-btn{display:block!important;}' +
        '}';

    function settings() { return R.settings || blankSettings(); }

    /** Inner content width of a block in px (for image sizes). */
    function innerW(p) { return Math.max(100, WIDTH - 2 * num(p.px, 0)); }

    function wrap(p, inner, extraTd) {
        var bg = isColor(p.bg) ? p.bg : '';
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"' + (bg ? ' bgcolor="' + bg + '" style="background-color:' + bg + ';"' : '') + '>' +
            '<tr><td class="nl-px" ' + (extraTd || '') + ' style="padding:' + num(p.pt, 0) + 'px ' + num(p.px, 0) + 'px ' + num(p.pb, 0) + 'px ' + num(p.px, 0) + 'px;">' +
            inner + '</td></tr></table>';
    }

    function link(href, inner, style) {
        href = String(href || '').trim();
        if (!href) return inner;
        return '<a href="' + esc(href) + '" target="_blank" style="' + (style || 'text-decoration:none;') + '">' + inner + '</a>';
    }

    function textStyle(p, fallbackColor, size) {
        var st = settings();
        return 'font-family:' + st.font + ';font-size:' + num(size || p.size, 16) + 'px;line-height:' + num(p.lh, 1.6) + ';color:' + col(p.color, fallbackColor || st.textColor) + ';text-align:' + (p.align || 'left') + ';';
    }

    /** ed: attribute that makes an element inline-editable in the canvas. */
    function ed(mode, field, rich) {
        return mode === 'edit' ? ' contenteditable="true" data-nlb-edit="' + field + '"' + (rich ? ' data-nlb-rich="1"' : '') : '';
    }

    function imgTag(mode, src, alt, w, extra) {
        var s = mode === 'export' ? absUrl(src) : src;
        if (!s) {
            return mode === 'edit' ? '<div class="nlb-ph">Choose an image in the block settings</div>' : '';
        }
        return '<img src="' + esc(s) + '" alt="' + esc(alt || '') + '" width="' + Math.round(w) + '" class="nl-img" style="display:block;width:100%;max-width:' + Math.round(w) + 'px;height:auto;border:0;' + (extra || '') + '">';
    }

    function buttonHtml(mode, label, href, opts) {
        var st = settings();
        var bg = col(opts.color, st.accent);
        var fg = col(opts.textColor, onColor(bg));
        var r = num(opts.radius, 6);
        var size = num(opts.size, 16);
        var align = opts.align || 'center';
        var a = '<a href="' + esc(href || '#') + '" target="_blank" class="nl-btn" style="display:' + (opts.full ? 'block' : 'inline-block') + ';padding:' + Math.round(size * 0.8) + 'px ' + Math.round(size * 1.7) + 'px;font-family:' + st.font + ';font-size:' + size + 'px;font-weight:700;line-height:1.2;color:' + fg + ';text-decoration:none;border-radius:' + r + 'px;text-align:center;"' + ed(mode, opts.field || 'label') + '>' + label + '</a>';
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="' + align + '"' + (opts.full ? ' width="100%"' : '') + ' style="' + (align === 'center' ? 'margin:0 auto;' : '') + '">' +
            '<tr><td align="center" bgcolor="' + bg + '" style="background-color:' + bg + ';border-radius:' + r + 'px;">' + a + '</td></tr></table>';
    }

    function youtubeThumb(url) {
        var m = String(url || '').match(/(?:youtu\.be\/|v=|embed\/|shorts\/)([A-Za-z0-9_-]{11})/);
        return m ? 'https://img.youtube.com/vi/' + m[1] + '/hqdefault.jpg' : '';
    }

    function renderBlock(b, mode) {
        var p = b.props, st = settings(), w;
        switch (b.type) {
            case 'logo':
                w = Math.min(num(p.width, 160), innerW(p));
                var logoImg = p.src
                    ? '<img src="' + esc(mode === 'export' ? absUrl(p.src) : p.src) + '" alt="' + esc(p.alt) + '" width="' + w + '" style="display:inline-block;width:' + w + 'px;max-width:100%;height:auto;border:0;">'
                    : (mode === 'edit' ? '<div class="nlb-ph">Add your logo in the block settings</div>' : '<span style="font-family:' + st.font + ';font-size:22px;font-weight:700;color:' + st.headingColor + ';">' + esc(R.cfg.siteName) + '</span>');
                return wrap(p, link(p.href, logoImg), 'align="' + p.align + '"');

            case 'heading':
                return wrap(p, '<h2 style="margin:0;font-family:' + st.font + ';font-size:' + num(p.size, 28) + 'px;line-height:1.25;font-weight:' + (p.weight || '700') + ';color:' + col(p.color, st.headingColor) + ';text-align:' + p.align + ';"' + ed(mode, 'text') + '>' + clean(p.text) + '</h2>');

            case 'text':
            case 'footer':
                return wrap(p, '<div style="' + textStyle(p, b.type === 'footer' ? '#8392A5' : '') + '"' + ed(mode, 'html', true) + '>' + clean(p.html) + '</div>');

            case 'image':
                w = innerW(p) * Math.min(100, Math.max(10, num(p.width, 100))) / 100;
                return wrap(p, link(p.href, imgTag(mode, p.src, p.alt, w, p.radius ? 'border-radius:' + num(p.radius, 0) + 'px;' : '') ), 'align="' + p.align + '"');

            case 'button':
                return wrap(p, buttonHtml(mode, clean(p.label), p.href, p), 'align="' + p.align + '"');

            case 'divider':
                return wrap(p, '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top:' + num(p.thickness, 1) + 'px solid ' + col(p.color, '#E1E5EA') + ';font-size:0;line-height:0;height:1px;">&nbsp;</td></tr></table>');

            case 'spacer':
                var h = num(p.height, 32);
                var sbg = isColor(p.bg) ? p.bg : '';
                return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"' + (sbg ? ' bgcolor="' + sbg + '" style="background-color:' + sbg + ';"' : '') + '><tr><td style="height:' + h + 'px;line-height:' + h + 'px;font-size:1px;">&nbsp;</td></tr></table>';

            case 'imagetext':
                var iw = Math.round(innerW(p) * 0.44);
                var imgTd = '<td class="nl-col" width="44%" valign="top" style="width:44%;padding-' + (p.side === 'left' ? 'right' : 'left') + ':12px;">' + link(p.href, imgTag(mode, p.src, p.title, iw, 'border-radius:6px;')) + '</td>';
                var txtTd = '<td class="nl-col" width="56%" valign="top" style="width:56%;padding-' + (p.side === 'left' ? 'left' : 'right') + ':12px;">' + columnText(mode, p, '') + '</td>';
                return wrap(p, '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' + (p.side === 'left' ? imgTd + txtTd : txtTd + imgTd) + '</tr></table>');

            case 'columns':
                var cols = p.cols || [];
                var n = Math.max(1, cols.length), gap = 16;
                var cw = Math.round((innerW(p) - gap * (n - 1)) / n);
                var tds = cols.map(function (c, i) {
                    var pad = 'padding-left:' + (i === 0 ? 0 : gap / 2) + 'px;padding-right:' + (i === n - 1 ? 0 : gap / 2) + 'px;';
                    var inner = (c.img ? link(c.href, imgTag(mode, c.src, c.title, cw, 'border-radius:6px;')) + '<div style="height:12px;line-height:12px;font-size:1px;">&nbsp;</div>' : '') +
                        columnText(mode, Object.assign({}, c, { align: p.align }), 'cols.' + i + '.');
                    return '<td class="nl-col" width="' + Math.floor(100 / n) + '%" valign="top" style="width:' + Math.floor(100 / n) + '%;' + pad + '">' + inner + '</td>';
                });
                return wrap(p, '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' + tds.join('') + '</tr></table>');

            case 'video':
                var thumb = p.thumb || youtubeThumb(p.url);
                var vimg = imgTag(mode, thumb, p.caption || 'Video', innerW(p), 'border-radius:6px;');
                var cap = p.caption ? '<div style="padding-top:10px;font-family:' + st.font + ';font-size:15px;font-weight:700;text-align:center;">' + link(p.url, '&#9654;&nbsp; ' + esc(p.caption), 'color:' + st.linkColor + ';text-decoration:none;') + '</div>' : '';
                return wrap(p, link(p.url, vimg) + cap, 'align="center"');

            case 'social':
                var pills = (p.links || []).filter(function (l) { return l.url; }).map(function (l) {
                    return '<a href="' + esc(l.url) + '" target="_blank" style="display:inline-block;margin:0 4px 8px 4px;padding:8px 16px;border-radius:999px;background-color:' + col(p.color, '#192A3E') + ';color:#FFFFFF;font-family:' + st.font + ';font-size:13px;font-weight:700;text-decoration:none;">' + esc(SOCIAL[l.net] || l.net) + '</a>';
                }).join('');
                if (!pills && mode === 'edit') pills = '<div class="nlb-ph">Add your social pages in the block settings</div>';
                return wrap(p, pills, 'align="' + p.align + '"');

            case 'html':
                return wrap(p, clean(p.code));
        }
        return '';
    }

    /** Title, text and a small link button: used by columns and image+text. */
    function columnText(mode, c, prefix) {
        var st = settings();
        var out = '';
        if (c.title !== undefined) {
            out += '<h3 style="margin:0 0 8px 0;font-family:' + st.font + ';font-size:18px;line-height:1.3;font-weight:700;color:' + st.headingColor + ';text-align:' + (c.align || 'left') + ';"' + ed(mode, prefix + 'title') + '>' + clean(c.title) + '</h3>';
        }
        out += '<div style="font-family:' + st.font + ';font-size:15px;line-height:1.55;color:' + st.textColor + ';text-align:' + (c.align || 'left') + ';"' + ed(mode, prefix + 'html', true) + '>' + clean(c.html) + '</div>';
        if (c.btn) {
            out += '<div style="padding-top:6px;text-align:' + (c.align || 'left') + ';">' + link(c.href || '#', '<span style="font-family:' + st.font + ';font-size:15px;font-weight:700;color:' + st.linkColor + ';"' + ed(mode, prefix + 'btn') + '>' + clean(c.btn) + ' &rarr;</span>') + '</div>';
        }
        return out;
    }

    function emailHead(title) {
        var st = settings();
        return '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' +
            '<meta http-equiv="X-UA-Compatible" content="IE=edge"><meta name="x-apple-disable-message-reformatting">' +
            '<title>' + esc(title || '') + '</title><style>' + EMAIL_CSS + 'a{color:' + st.linkColor + ';}</style>';
    }

    /** The complete e-mail of a design. mode 'export' (absolute image URLs, ready to send) or 'preview'. */
    function renderDesign(design, cfg, subject, preheader, mode) {
        var prev = R;
        R = { settings: safeSettings((design || {}).settings), cfg: cfg || GCFG };
        try {
            var st = settings();
            var body = ((design || {}).blocks || []).filter(function (b) { return b && TYPES.hasOwnProperty(b.type); })
                .map(function (b) { return renderBlock(b, mode || 'export'); }).join('\n');
            var pre = preheader ? '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">' + esc(preheader) + '&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>' : '';
            return '<!DOCTYPE html>\n<html lang="en">' + emailHead(subject) + '</head>' +
                '<body style="margin:0;padding:0;background-color:' + st.bodyBg + ';">' + pre +
                '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' + st.bodyBg + '" style="background-color:' + st.bodyBg + ';">' +
                '<tr><td align="center" style="padding:24px 10px;">' +
                '<table role="presentation" class="nl-container" width="' + WIDTH + '" cellpadding="0" cellspacing="0" border="0" bgcolor="' + st.contentBg + '" style="width:' + WIDTH + 'px;max-width:' + WIDTH + 'px;background-color:' + st.contentBg + ';">' +
                '<tr><td style="padding:0;">\n' + body + '\n</td></tr></table>' +
                '</td></tr></table></body></html>';
        } finally { R = prev; }
    }

    /** Same e-mail with relative image paths resolved against this page (for in-page previews). */
    function previewDoc(design, cfg, subject, preheader) {
        return renderDesign(design, cfg, subject, preheader, 'preview').replace('<head>', '<head><base href="' + esc(location.href.replace(/[^/]*$/, '')) + '" target="_blank">');
    }

    /* ---------- design-system UI ---------- */
    var ICON = { logo: 'i-star', heading: 'i-type', text: 'i-note', image: 'i-image', button: 'i-play', imagetext: 'i-layers', columns: 'i-grid', video: 'i-play', divider: 'i-minus', spacer: 'i-expand', social: 'i-users', footer: 'i-file', html: 'i-terminal' };
    var STOCK = ['hero-shipping.jpg', 'hero-promo.jpg', 'hero-welcome.jpg', 'hero-holiday.jpg', 'card-air.jpg', 'card-sea.jpg', 'card-warehouse.jpg', 'card-delivery.jpg', 'video-thumb.jpg'];
    var COMMON = [{ k: 'bg', t: 'color', l: 'Background', clear: true }, { k: 'pt', t: 'range', l: 'Space above', min: 0, max: 80 }, { k: 'pb', t: 'range', l: 'Space below', min: 0, max: 80 }, { k: 'px', t: 'range', l: 'Side padding', min: 0, max: 80 }];
    var FIELDS = {
        logo: [{ k: 'src', t: 'image', l: 'Logo image' }, { k: 'alt', t: 'text', l: 'Alt text' }, { k: 'width', t: 'range', l: 'Width (px)', min: 40, max: 600 }, { k: 'href', t: 'text', l: 'Link' }, { k: 'align', t: 'align' }],
        heading: [{ k: 'text', t: 'textarea', l: 'Heading', rows: 2 }, { k: 'size', t: 'range', l: 'Size', min: 14, max: 56 }, { k: 'weight', t: 'select', l: 'Weight', o: [['400', 'Regular'], ['700', 'Bold'], ['900', 'Heavy']] }, { k: 'color', t: 'color', l: 'Colour', clear: true }, { k: 'align', t: 'align' }],
        text: [{ k: 'html', t: 'textarea', l: 'Text (HTML allowed: <b>, <a href>, <p>)', rows: 6 }, { k: 'size', t: 'range', l: 'Text size', min: 10, max: 28 }, { k: 'lh', t: 'range', l: 'Line height', min: 1, max: 2.4, step: 0.1 }, { k: 'color', t: 'color', l: 'Text colour', clear: true }, { k: 'align', t: 'align', justify: true }],
        footer: [{ t: 'note', l: 'Keep the [UNSUBSCRIBE_URL] link: bulk e-mail must let people opt out.' }, { k: 'html', t: 'textarea', l: 'Footer (HTML)', rows: 5 }, { k: 'size', t: 'range', l: 'Text size', min: 10, max: 18 }, { k: 'color', t: 'color', l: 'Text colour', clear: true }, { k: 'align', t: 'align' }],
        image: [{ k: 'src', t: 'image', l: 'Image' }, { k: 'alt', t: 'text', l: 'Alt text (shown when images are off)' }, { k: 'href', t: 'text', l: 'Link when clicked' }, { k: 'width', t: 'range', l: 'Width (%)', min: 10, max: 100 }, { k: 'radius', t: 'range', l: 'Rounded corners', min: 0, max: 40 }, { k: 'align', t: 'align' }],
        button: [{ k: 'label', t: 'text', l: 'Button text' }, { k: 'href', t: 'text', l: 'Link' }, { k: 'color', t: 'color', l: 'Button colour', clear: true }, { k: 'textColor', t: 'color', l: 'Text colour', clear: true }, { k: 'size', t: 'range', l: 'Text size', min: 12, max: 26 }, { k: 'radius', t: 'range', l: 'Rounded corners', min: 0, max: 40 }, { k: 'full', t: 'check', l: 'Full width' }, { k: 'align', t: 'align' }],
        imagetext: [{ k: 'src', t: 'image', l: 'Image' }, { k: 'side', t: 'select', l: 'Image side', o: [['left', 'Left'], ['right', 'Right']] }, { k: 'title', t: 'text', l: 'Title' }, { k: 'html', t: 'textarea', l: 'Text (HTML)', rows: 4 }, { k: 'btn', t: 'text', l: 'Link text (empty for none)' }, { k: 'href', t: 'text', l: 'Link' }],
        columns: [{ t: 'cols' }, { k: 'align', t: 'align' }],
        video: [{ k: 'url', t: 'text', l: 'Video link (YouTube, Vimeo, Facebook...)' }, { k: 'thumb', t: 'image', l: 'Thumbnail (empty = YouTube picture)' }, { k: 'caption', t: 'text', l: 'Caption' }, { t: 'note', l: 'E-mail cannot play video: readers see the picture and open the video when they click it.' }],
        divider: [{ k: 'color', t: 'color', l: 'Line colour' }, { k: 'thickness', t: 'range', l: 'Thickness', min: 1, max: 10 }],
        spacer: [{ k: 'height', t: 'range', l: 'Height', min: 4, max: 120 }, { k: 'bg', t: 'color', l: 'Background', clear: true }],
        social: [{ t: 'social' }, { k: 'color', t: 'color', l: 'Button colour' }, { k: 'align', t: 'align' }],
        html: [{ k: 'code', t: 'textarea', l: 'HTML', rows: 10 }, { t: 'note', l: 'Scripts, forms and frames are removed. Use inline styles: many mail clients ignore others.' }]
    };
    var GLOB = [['bodyBg', 'Page'], ['contentBg', 'E-mail'], ['textColor', 'Text'], ['headingColor', 'Headings'], ['linkColor', 'Links'], ['accent', 'Buttons']];

    /* A saved design (server, draft or template) is untrusted JSON. The renderer puts the block id, align,
       weight and the global font/colours into HTML attributes unescaped, so force them to safe values here. */
    var ALIGNS = { left: 1, center: 1, right: 1, justify: 1 };
    function safeSettings(st) {
        var d = blankSettings(), out = Object.assign({}, d, st || {});
        Object.keys(d).forEach(function (k) { if (k !== 'font') out[k] = col(out[k], d[k]); });
        out.font = /^[A-Za-z0-9 ,'-]{1,120}$/.test(String(out.font || '')) ? out.font : d.font;
        return out;
    }
    function safeProps(p, d) {
        if (p.align !== undefined && !ALIGNS.hasOwnProperty(String(p.align))) p.align = d.align || 'left';
        if (p.weight !== undefined && !/^[1-9]00$/.test(String(p.weight))) p.weight = d.weight || '700';
        if (p.side !== undefined && p.side !== 'left' && p.side !== 'right') p.side = 'left';
        return p;
    }
    /** A saved design (server, draft, template or AI proposal) made safe to render. */
    function normalise(design) {
        design = design && typeof design === 'object' ? design : {};
        var d = { settings: safeSettings(design.settings), blocks: (Array.isArray(design.blocks) ? design.blocks : []).filter(function (b) { return b && typeof b.type === 'string' && TYPES.hasOwnProperty(b.type); }).map(clone) };
        d.blocks.forEach(function (b) { b.id = /^[A-Za-z0-9_-]{1,40}$/.test(String(b.id || '')) ? b.id : uid(); b.props = safeProps(Object.assign({}, clone(TYPES[b.type].d), b.props || {}), TYPES[b.type].d); if (b.type === 'columns' && !b.props.cols) b.props.cols = defaultCols(2); });
        return d;
    }

    /* [TAGS] of the newsletter defaults and templates, as comms {placeholders} (tagSyntax 'brace'). */
    var BRACE_MAP = { '[NAME]': '{name}', '[FIRST_NAME]': '{first_name}', '[LAST_NAME]': '', '[EMAIL]': '', '[SITE_NAME]': '{company}', '[URL]': '{track_url}', '[TRACK_URL]': '{track_url}', '[UNSUBSCRIBE_URL]': '{prefs_url}', '[YEAR]': String(new Date().getFullYear()) };

    // =======================================================================
    // One canvas
    // =======================================================================
    function mount(root, opts) {
        opts = opts || {};
        if (typeof root === 'string') root = document.querySelector(root);
        if (!root) throw new Error('SLEmailCanvas.mount: root element not found');
        if (!root.hasAttribute('data-ec-root')) {
            var inner = root.querySelector('[data-ec-root]');
            if (!inner) {
                var tpl = typeof opts.template === 'string' ? document.querySelector(opts.template) : (opts.template || document.querySelector('template[data-ec-template]'));
                if (tpl && tpl.content) { root.appendChild(tpl.content.cloneNode(true)); inner = root.querySelector('[data-ec-root]'); }
            }
            if (!inner) throw new Error('SLEmailCanvas.mount: no canvas markup (include views/sl/inc/email_canvas.php)');
            root = inner;
        }
        if (root.__slCanvas) return root.__slCanvas;

        var cfg = Object.assign({}, GCFG, opts.cfg || {});
        var brace = opts.tagSyntax === 'brace';
        var tmap = brace ? Object.assign({}, BRACE_MAP, opts.tagMap || {}) : (opts.tagMap || null);
        var conv = tmap ? function (s) { return String(s).replace(/\[[A-Z_]+\]/g, function (m) { return tmap.hasOwnProperty(m) ? tmap[m] : m; }); } : null;
        var tx = function (s) { return conv ? conv(s) : s; };
        var ajaxUrl = opts.ajax === undefined ? (cfg.ajax || 'ajax/tools/newsletter_ajax.php') : opts.ajax;
        var uploadUrl = opts.uploadUrl === undefined ? ajaxUrl : opts.uploadUrl;
        var savedOn = opts.saved !== false && !!ajaxUrl;
        var S = { design: normalise({}), selected: null, undo: [], drag: null, snapped: false, lastField: null };
        var q = function (n) { return root.querySelector('[data-ec="' + n + '"]'); };
        var devEl = opts.devEl || q('dev'), undoEl = opts.undoEl || q('undo');
        var listeners = {}, offs = [], changeT = null, softT = null, upPath = null;

        function on(el, ev, fn) { if (!el) return; el.addEventListener(ev, fn); offs.push(function () { el.removeEventListener(ev, fn); }); }
        function emit(name, detail) {
            (listeners[name] || []).slice().forEach(function (fn) { try { fn(detail, api); } catch (e) { if (window.console) console.error(e); } });
            var cb = opts['on' + name.charAt(0).toUpperCase() + name.slice(1)];
            if (typeof cb === 'function') { try { cb(detail, api); } catch (e) { if (window.console) console.error(e); } }
            root.dispatchEvent(new CustomEvent('ec:' + name, { detail: detail, bubbles: true }));
        }
        function changed(reason) { clearTimeout(changeT); changeT = setTimeout(function () { emit('change', { design: clone(S.design), reason: reason || 'edit' }); }, reason === 'load' ? 0 : 150); }
        function mk(type, props, n) { return mkBlock(type, props, n, cfg, conv); }
        function use() { R = { settings: S.design.settings, cfg: cfg }; }
        function ajax(url, data) { var fd = new FormData(); Object.keys(data).forEach(function (k) { fd.append(k, data[k]); }); return SL.post(url, fd); }
        function snap() { S.undo.push(JSON.stringify(S.design)); if (S.undo.length > 60) S.undo.shift(); }
        function idx(id) { for (var i = 0; i < S.design.blocks.length; i++) if (S.design.blocks[i].id === id) return i; return -1; }
        function sel() { var i = idx(S.selected); return i >= 0 ? S.design.blocks[i] : null; }
        function width() { var b = devEl && devEl.querySelector('.on'); return b ? (+b.getAttribute('data-w') || WIDTH) : WIDTH; }

        function setDesign(design, silent) {
            var d = normalise(design);
            if (conv) d = convTags(d, conv);
            S.design = d; S.selected = null; S.undo = []; S.lastField = null;
            draw(true); glob();
            if (!silent) changed('load');
        }
        function draw(quiet) {
            use();
            var w = width(), st = S.design.settings, m = q('mail'), stage = q('stage');
            m.style.width = w + 'px'; m.style.background = st.contentBg; stage.style.background = st.bodyBg;
            m.innerHTML = S.design.blocks.map(function (b) {
                return '<div class="eb-drop" data-at="' + idx(b.id) + '"></div><div class="eb-b' + (b.id === S.selected ? ' sel' : '') + '" data-id="' + b.id + '" draggable="true" tabindex="0" role="button" aria-label="' + esc(TYPES[b.type].label) + ' block"><span class="eb-tag">' + esc(TYPES[b.type].label) + '</span><span class="eb-tb"><button type="button" data-op="up" aria-label="Move up"><i class="ic ic-sm i-up" aria-hidden="true"></i></button><button type="button" data-op="down" aria-label="Move down"><i class="ic ic-sm i-down" aria-hidden="true"></i></button><button type="button" data-op="dup" aria-label="Duplicate"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button><button type="button" data-op="del" aria-label="Delete block"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></span>' + renderBlock(b, 'preview') + '</div>';
            }).join('') + '<div class="eb-drop" data-at="' + S.design.blocks.length + '" style="margin-bottom:16px">' + (S.design.blocks.length ? '' : '<div class="sl-empty mut sm" style="padding:60px 12px;text-align:center">Drag a block here, or click one in the palette</div>') + '</div>';
            m.querySelectorAll('a').forEach(function (a) { a.removeAttribute('href'); });
            var c = q('count'); if (c) c.textContent = S.design.blocks.length + ' block' + (S.design.blocks.length === 1 ? '' : 's') + ' · ' + w + ' px';
            insp();
            if (!quiet) changed('edit');
        }
        function glob() { var st = S.design.settings, g = q('glob'); if (!g) return; g.innerHTML = GLOB.map(function (x) { return '<label class="row g6 sm"><input type="color" data-glob="' + x[0] + '" value="' + (isColor(st[x[0]]) && st[x[0]].length === 7 ? st[x[0]] : '#ffffff') + '" aria-label="' + x[1] + ' colour"> ' + x[1] + '</label>'; }).join(''); }
        on(q('glob'), 'input', function (e) { var k = e.target.getAttribute('data-glob'); if (!k) return; S.design.settings[k] = e.target.value; draw(); });

        function field(f, val, path) {
            var data = ' data-path="' + esc(path || f.k) + '"', lab = f.l ? '<span class="sl-lab">' + esc(tx(f.l)) + '</span>' : '';
            switch (f.t) {
                case 'note': return '<span class="mut sm">' + esc(tx(f.l)) + '</span>';
                case 'text': return '<label class="sl-field">' + lab + '<input class="sl-in"' + data + ' value="' + esc(val || '') + '"></label>';
                case 'textarea': return '<label class="sl-field">' + lab + '<textarea class="eb-ta mono" rows="' + (f.rows || 4) + '"' + data + '>' + esc(val || '') + '</textarea></label>';
                case 'range': return '<label class="sl-field">' + lab + '<span class="row g8"><input type="range" class="grow"' + data + ' min="' + f.min + '" max="' + f.max + '" step="' + (f.step || 1) + '" value="' + esc(val) + '"><output class="mono sm">' + esc(val) + '</output></span></label>';
                case 'color': return '<div class="sl-field">' + lab + '<span class="row g8"><input type="color"' + data + ' value="' + (isColor(val) && val.length === 7 ? val : '#ffffff') + '" aria-label="' + esc(f.l) + '"><input class="sl-in mono grow"' + data + ' data-hex value="' + esc(isColor(val) ? val : '') + '" placeholder="' + (f.clear ? 'Default' : '#000000') + '" maxlength="7" aria-label="' + esc(f.l) + ' (hex)">' + (f.clear ? '<button type="button" class="sl-btn ghost sm"' + data + ' data-clear aria-label="Use the default"><i class="ic ic-sm i-x" aria-hidden="true"></i></button>' : '') + '</span></div>';
                case 'align': var o = ['left', 'center', 'right']; if (f.justify) o.push('justify'); return '<div class="sl-field"><span class="sl-lab">Align</span><div class="sl-seg boxed">' + o.map(function (a) { return '<button type="button"' + data + ' data-val="' + a + '" class="' + (val === a ? 'on' : '') + '">' + a.charAt(0).toUpperCase() + a.slice(1) + '</button>'; }).join('') + '</div></div>';
                case 'select': return '<label class="sl-field">' + lab + '<span class="sl-sel native"><select' + data + '>' + f.o.map(function (x) { return '<option value="' + esc(x[0]) + '"' + (String(val) === String(x[0]) ? ' selected' : '') + '>' + esc(x[1]) + '</option>'; }).join('') + '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>';
                case 'check': return '<label class="row g8 sm"><input type="checkbox"' + data + (val ? ' checked' : '') + '> ' + esc(f.l) + '</label>';
                case 'image': return '<div class="sl-field">' + lab + '<input class="sl-in"' + data + ' value="' + esc(val || '') + '" placeholder="https://... or assets/..."><div class="row g6 wrap">' + (uploadUrl ? '<button type="button" class="sl-btn ghost sm"' + data + ' data-upload><i class="ic ic-sm i-upload" aria-hidden="true"></i>Upload</button>' : '') + STOCK.concat(cfg.logo ? [null] : []).map(function (s) { var u = s ? 'assets/images/newsletter/' + s : cfg.logo; return '<button type="button"' + data + ' data-val="' + esc(u) + '" style="width:40px;height:30px;padding:0;border:1px solid var(--line);border-radius:6px;background:center/cover url(\'' + esc(u) + '\');cursor:pointer" aria-label="Use ' + esc(s || 'logo') + '"></button>'; }).join('') + '</div></div>';
                case 'social': return '<div class="col g6"><span class="sl-lab">Links</span>' + (val || []).map(function (l, i) { return '<span class="row g6"><span class="sl-sel native" style="width:130px"><select data-path="links.' + i + '.net" aria-label="Network">' + Object.keys(SOCIAL).map(function (k) { return '<option value="' + k + '"' + (l.net === k ? ' selected' : '') + '>' + SOCIAL[k] + '</option>'; }).join('') + '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span><input class="sl-in grow" data-path="links.' + i + '.url" value="' + esc(l.url) + '" placeholder="https://" aria-label="Link"><button type="button" class="sl-btn ghost sm" data-soc-del="' + i + '" aria-label="Remove link"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></span>'; }).join('') + '<button type="button" class="sl-btn ghost sm" data-soc-add><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add link</button></div>';
                case 'cols':
                    var p = sel().props;
                    return '<div class="sl-field"><span class="sl-lab">Columns</span><div class="sl-seg boxed">' + [1, 2, 3].map(function (n) { return '<button type="button" data-ncols="' + n + '" class="' + (p.cols.length === n ? 'on' : '') + '">' + n + '</button>'; }).join('') + '</div></div>' + p.cols.map(function (c, i) {
                        var pre = 'cols.' + i + '.';
                        return '<details class="sl-card col g8" style="padding:10px"' + (i === 0 ? ' open' : '') + '><summary class="fw5 sm">Column ' + (i + 1) + '</summary>' + field({ k: 'img', t: 'check', l: 'Show an image' }, c.img, pre + 'img') + (c.img ? field({ t: 'image', l: 'Image' }, c.src, pre + 'src') : '') + field({ t: 'text', l: 'Title' }, c.title, pre + 'title') + field({ t: 'textarea', l: 'Text (HTML)', rows: 3 }, c.html, pre + 'html') + field({ t: 'text', l: 'Link text (empty for none)' }, c.btn, pre + 'btn') + field({ t: 'text', l: 'Link' }, c.href, pre + 'href') + '</details>';
                    }).join('');
            }
            return '';
        }
        function insp() {
            var b = sel(), box = q('insp');
            S.lastField = null;
            if (!b) { box.hidden = true; box.innerHTML = ''; return; }
            box.hidden = false;
            box.innerHTML = '<div class="sl-ch" style="margin:0"><h2 class="sl-ct">' + esc(TYPES[b.type].label) + '</h2><button type="button" class="sl-ib" style="width:32px;height:32px" aria-label="Close block settings" data-close><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>' +
                (FIELDS[b.type] || []).map(function (f) { return field(f, f.k ? b.props[f.k] : (f.t === 'social' ? b.props.links : null)); }).join('') +
                (b.type !== 'spacer' ? '<details class="col g8"><summary class="sm fw5">Spacing and background</summary>' + COMMON.map(function (f) { return field(f, b.props[f.k]); }).join('') + '</details>' : '');
        }
        function setPath(obj, path, val) { var ks = path.split('.'), o = obj; for (var i = 0; i < ks.length - 1; i++) o = o[isNaN(ks[i]) ? ks[i] : +ks[i]]; o[ks[ks.length - 1]] = val; }
        function setVal(path, val, redrawInsp) {
            var b = sel(); if (!b) return; setPath(b.props, path, val); use();
            var el = q('mail').querySelector('.eb-b[data-id="' + b.id + '"]');
            if (el) { var tmp = document.createElement('div'); tmp.innerHTML = renderBlock(b, 'preview'); tmp.querySelectorAll('a').forEach(function (a) { a.removeAttribute('href'); }); Array.prototype.slice.call(el.children, 2).forEach(function (x) { x.remove(); }); while (tmp.firstChild) el.appendChild(tmp.firstChild); }
            clearTimeout(softT); softT = setTimeout(function () { changed('edit'); }, 300);
            if (redrawInsp) insp();
        }
        var box = q('insp');
        on(box, 'focusin', function (e) { if (!S.snapped) { snap(); S.snapped = true; } var t = e.target; if (t.matches && t.matches('textarea, input.sl-in:not([data-hex])')) S.lastField = t; });
        on(box, 'focusout', function () { S.snapped = false; });
        on(box, 'input', function (e) { var t = e.target, p = t.getAttribute('data-path'); if (!p) return;
            if (t.type === 'checkbox') return;
            if (t.type === 'range') { t.nextElementSibling.textContent = t.value; setVal(p, num(t.value, 0)); return; }
            if (t.hasAttribute('data-hex')) { if (isColor(t.value) || t.value === '') { setVal(p, t.value); var c = t.parentNode.querySelector('input[type=color]'); if (isColor(t.value) && t.value.length === 7) c.value = t.value; } return; }
            if (t.type === 'color') { var hx = t.parentNode.querySelector('[data-hex]'); if (hx) hx.value = t.value; }
            setVal(p, t.value); });
        on(box, 'change', function (e) { var t = e.target, p = t.getAttribute('data-path'); if (!p) return; if (t.type === 'checkbox') { snap(); setVal(p, t.checked, /\.img$/.test(p)); } else if (t.tagName === 'SELECT') { snap(); setVal(p, t.value); } });
        on(box, 'click', function (e) { var b = e.target.closest('button'); if (!b) return; var p = b.getAttribute('data-path'), blk = sel();
            if (b.hasAttribute('data-close')) { S.selected = null; draw(true); return; }
            if (b.hasAttribute('data-clear')) { snap(); setVal(p, '', true); return; }
            if (b.hasAttribute('data-upload')) { upPath = p; q('file').click(); return; }
            if (b.hasAttribute('data-ncols')) { snap(); var n = +b.getAttribute('data-ncols'), cs = blk.props.cols, d = defaultCols(n); if (conv) d = convTags(d, conv); blk.props.cols = d.map(function (x, i) { return cs[i] || x; }); draw(); return; }
            if (b.hasAttribute('data-soc-add')) { snap(); blk.props.links.push({ net: 'website', url: '' }); setVal('links', blk.props.links, true); return; }
            if (b.hasAttribute('data-soc-del')) { snap(); blk.props.links.splice(+b.getAttribute('data-soc-del'), 1); setVal('links', blk.props.links, true); return; }
            if (p && b.hasAttribute('data-val')) { snap(); setVal(p, b.getAttribute('data-val'), true); } });
        on(q('file'), 'change', function () { var f = this.files[0]; this.value = ''; if (!f || !upPath || !uploadUrl) return; SL.toast('Uploading…');
            ajax(uploadUrl, { action: 'upload', image: f }).then(function (r) { if (!r.success) { SL.toast(errs(r, 'Upload failed'), true); return; } snap(); setVal(upPath, r.path || r.url, true); SL.toast('Image uploaded'); }).catch(function (err) { SL.toast((err && err.message) || 'Upload failed', true); }); });

        /* canvas: select, toolbar, drag and drop */
        function insertAt(i, b) { snap(); S.design.blocks.splice(Math.max(0, Math.min(i, S.design.blocks.length)), 0, b); S.selected = b.id; draw(); }
        function add(type, n, props) { var b = mk(type, props, n), i = idx(S.selected); insertAt(i >= 0 ? i + 1 : S.design.blocks.length, b); return b.id; }
        function pick(el) { S.selected = el.getAttribute('data-id'); q('mail').querySelectorAll('.eb-b').forEach(function (x) { x.classList.toggle('sel', x === el); }); insp(); }
        on(q('mail'), 'click', function (e) { var op = e.target.closest('[data-op]'), el = e.target.closest('.eb-b'); if (!el) return; var id = el.getAttribute('data-id'), i = idx(id), bl = S.design.blocks;
            if (op) { snap(); var o = op.getAttribute('data-op');
                if (o === 'del') { bl.splice(i, 1); if (S.selected === id) S.selected = null; }
                else if (o === 'dup') { var c = clone(bl[i]); c.id = uid(); bl.splice(i + 1, 0, c); S.selected = c.id; }
                else if (o === 'up' && i > 0) { bl.splice(i - 1, 0, bl.splice(i, 1)[0]); }
                else if (o === 'down' && i < bl.length - 1) { bl.splice(i + 1, 0, bl.splice(i, 1)[0]); }
                draw(); return; }
            pick(el); });
        on(q('mail'), 'keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('eb-b')) { e.preventDefault(); pick(e.target); } });
        on(q('mail'), 'dragstart', function (e) { var el = e.target.closest && e.target.closest('.eb-b'); if (!el) return; S.drag = { kind: 'move', id: el.getAttribute('data-id') }; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', 'm'); } catch (x) {} });
        function dropAt(y) { var drops = q('mail').querySelectorAll('.eb-drop'), best = null, bd = 1e9; drops.forEach(function (d) { var r = d.getBoundingClientRect(), dd = Math.abs(r.top + r.height / 2 - y); if (dd < bd) { bd = dd; best = d; } }); return best; }
        on(q('stage'), 'dragover', function (e) { if (!S.drag) return; e.preventDefault(); var d = dropAt(e.clientY); q('mail').querySelectorAll('.eb-drop').forEach(function (x) { x.classList.toggle('on', x === d); }); });
        on(q('stage'), 'drop', function (e) { if (!S.drag) return; e.preventDefault(); var d = dropAt(e.clientY), at = d ? +d.getAttribute('data-at') : S.design.blocks.length, g = S.drag; S.drag = null;
            if (g.kind === 'new') insertAt(at, mk(g.type, null, g.n));
            else { var i = idx(g.id); if (i < 0) return; snap(); var b = S.design.blocks.splice(i, 1)[0]; if (i < at) at--; S.design.blocks.splice(at, 0, b); draw(); } });
        on(document, 'dragend', function () { S.drag = null; root.querySelectorAll('.eb-drop.on').forEach(function (x) { x.classList.remove('on'); }); });

        /* palette, templates, saved designs, tags */
        q('pal').innerHTML = PALETTE.map(function (p) { return '<button type="button" class="eb-blk" draggable="true" data-type="' + p[0] + '"' + (p[1] ? ' data-n="' + p[1] + '"' : '') + '><i class="ic ' + (ICON[p[0]] || 'i-grid') + '" aria-hidden="true"></i>' + esc(p[2] || TYPES[p[0]].label) + '</button>'; }).join('');
        on(q('pal'), 'click', function (e) { var b = e.target.closest('[data-type]'); if (b) add(b.getAttribute('data-type'), +(b.getAttribute('data-n') || 0)); });
        on(q('pal'), 'dragstart', function (e) { var b = e.target.closest('[data-type]'); if (!b) return; S.drag = { kind: 'new', type: b.getAttribute('data-type'), n: +(b.getAttribute('data-n') || 0) }; try { e.dataTransfer.setData('text/plain', 'n'); } catch (x) {} });
        function showTab(name) {
            var tabs = q('ltabs'); if (!tabs) return;
            tabs.querySelectorAll('[data-t]').forEach(function (x) { var onT = x.getAttribute('data-t') === name; x.classList.toggle('on', onT); x.setAttribute('aria-selected', String(onT)); });
            root.querySelectorAll('[data-lt]').forEach(function (p) { p.hidden = p.getAttribute('data-lt') !== name; });
            if (name === 'saved') loadSaved();
        }
        on(q('ltabs'), 'click', function (e) { var b = e.target.closest('[data-t]'); if (b) showTab(b.getAttribute('data-t')); });
        function hideTab(name) { var t = q('ltabs') && q('ltabs').querySelector('[data-t="' + name + '"]'), p = root.querySelector('[data-lt="' + name + '"]'); if (t) t.remove(); if (p) p.remove(); }

        var TPLS = [];
        if (opts.templates !== false) {
            var src = opts.templates !== undefined ? opts.templates : window.NLB_TEMPLATES;
            try { TPLS = typeof src === 'function' ? src(cfg, mk) : (Array.isArray(src) ? src : []); } catch (e) { TPLS = []; }
            if (conv) TPLS = TPLS.map(function (t) { return convTags(t, conv); });
        }
        if (!TPLS.length) hideTab('tpl');
        else if (q('tpls')) q('tpls').innerHTML = TPLS.map(function (t, i) { return '<button type="button" class="eb-tpl" data-tpl="' + i + '"><i class="ic ic-sm i-mail mut" aria-hidden="true"></i><span class="grow col" style="text-align:left"><span>' + esc(t.name) + '</span><small class="mut">' + esc(t.description || '') + '</small></span></button>'; }).join('');
        on(q('tpls'), 'click', function (e) { var b = e.target.closest('[data-tpl]'); if (!b) return; if (S.design.blocks.length && !confirm('Replace the current design with this template?')) return; var t = TPLS[+b.getAttribute('data-tpl')]; setDesign(clone(t.design)); emit('template', { template: clone(t), subject: t.subject || '', preheader: t.preheader || '' }); });

        if (!savedOn) hideTab('saved');
        function loadSaved() { if (!savedOn) return; ajax(ajaxUrl, { action: 'designs' }).then(function (r) { var l = (r && r.designs) || [];
            q('saved').innerHTML = (r && r.success === false) ? '<span class="mut sm">' + esc(errs(r, 'Saved designs could not be loaded.')) + '</span>' : (l.length ? l.map(function (x) { return '<div class="row g6"><button type="button" class="eb-tpl grow" data-load="' + (+x.id) + '"><i class="ic ic-sm i-file mut" aria-hidden="true"></i><span class="grow col" style="text-align:left"><span>' + esc(x.name) + '</span><small class="mut">' + esc(x.subject || '') + ' · ' + esc(x.updated_at || '') + (x.last_sent_at ? ' · sent ' + esc(x.last_sent_at) : '') + '</small></span></button>' + (opts.savedDelete === false ? '' : '<button type="button" class="sl-btn ghost sm" data-del="' + (+x.id) + '" data-name="' + esc(x.name) + '" aria-label="Delete design"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button>') + '</div>'; }).join('') : '<span class="mut sm">No saved designs yet.</span>'); })
            .catch(function () { q('saved').innerHTML = '<span class="mut sm">Saved designs could not be loaded.</span>'; }); }
        on(q('saved'), 'click', function (e) { var b = e.target.closest('[data-load],[data-del]'); if (!b) return;
            if (b.hasAttribute('data-del')) { if (!confirm('Delete the design "' + b.getAttribute('data-name') + '"?')) return; ajax(ajaxUrl, { action: 'delete', id: b.getAttribute('data-del') }).then(function (r) { if (!r.success) { SL.toast(errs(r), true); return; } SL.toast('Design deleted'); emit('delete', { id: +b.getAttribute('data-del') }); loadSaved(); }); return; }
            if (S.design.blocks.length && !confirm('Open this design? Unsaved changes to the current one are lost.')) return;
            ajax(ajaxUrl, { action: 'load', id: b.getAttribute('data-load') }).then(function (r) { if (!r.success) { SL.toast(errs(r), true); return; } var d; try { d = JSON.parse(r.design.json); } catch (x) { SL.toast('That design could not be read.', true); return; } setDesign(d); emit('open', { id: +r.design.id, name: r.design.name, subject: r.design.subject, preheader: r.design.preheader }); SL.toast('Opened "' + r.design.name + '"'); }); });

        function setTags(tags) {
            var wrap = q('vars'); if (!wrap) return;
            var row = wrap.querySelector('.row'); if (!row) { row = document.createElement('div'); row.className = 'row wrap g6'; wrap.appendChild(row); }
            var keys = Object.keys(tags || {});
            row.innerHTML = keys.map(function (k) { var l = tags[k], label = Array.isArray(l) ? l[0] : l, tag = /^[\[{]/.test(k) ? k : (brace ? '{' + k + '}' : '[' + k + ']'); return '<button type="button" class="eb-var" title="' + esc(label || '') + '" data-var="' + esc(tag) + '">' + esc(tag) + '</button>'; }).join('') || '<span class="mut sm">No tags for this message.</span>';
        }
        if (opts.tags) setTags(opts.tags);
        function insertTag(v) {
            var ta = S.lastField && S.lastField.isConnected ? S.lastField : q('insp').querySelector('textarea, input.sl-in:not([data-hex])');
            if (!ta) { if (navigator.clipboard) navigator.clipboard.writeText(v).catch(function () {}); SL.toast(v + ' copied: select a text block to insert it'); return false; }
            var s = typeof ta.selectionStart === 'number' ? ta.selectionStart : ta.value.length, en = typeof ta.selectionEnd === 'number' ? ta.selectionEnd : s;
            ta.value = ta.value.slice(0, s) + v + ta.value.slice(en); ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus();
            try { ta.setSelectionRange(s + v.length, s + v.length); } catch (x) {}
            return true;
        }
        on(q('vars'), 'mousedown', function (e) { if (e.target.closest('[data-var]')) e.preventDefault(); });   // keep the caret in the field
        on(q('vars'), 'click', function (e) { var b = e.target.closest('[data-var]'); if (b) insertTag(b.getAttribute('data-var')); });

        /* toolbar */
        on(devEl, 'click', function (e) { var b = e.target.closest('button'); if (!b) return; devEl.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); draw(true); });
        function undo() { var s = S.undo.pop(); if (!s) { SL.toast('Nothing to undo'); return false; } S.design = JSON.parse(s); draw(); glob(); return true; }
        on(undoEl, 'click', undo);

        var api = {
            root: root,
            /** A copy of the current design JSON ({settings, blocks}). */
            getDesign: function () { return clone(S.design); },
            /** Replace the design (normalised; [TAGS] converted for brace canvases). Emits load-change. */
            setDesign: function (d, o) { setDesign(d, o && o.silent); return api; },
            /** The complete e-mail HTML (absolute image URLs, placeholders left as typed). */
            getHtml: function (subject, preheader) { return renderDesign(S.design, cfg, subject, preheader, 'export'); },
            /** The e-mail for an in-page iframe preview (relative images resolved). */
            getPreviewHtml: function (subject, preheader) { return previewDoc(S.design, cfg, subject, preheader); },
            /** The visible words of the e-mail, for AI context or a plain-text part. */
            getText: function () { var doc = new DOMParser().parseFromString(renderDesign(S.design, cfg, '', '', 'preview'), 'text/html'); doc.querySelectorAll('style,title').forEach(function (x) { x.remove(); }); doc.querySelectorAll('p,div,h1,h2,h3,td,tr,br').forEach(function (x) { x.insertAdjacentText('afterend', '\n'); }); return (doc.body.textContent || '').replace(/[ \t ]+/g, ' ').replace(/\s*\n\s*/g, '\n').trim(); },
            isEmpty: function () { return !S.design.blocks.length; },
            blockCount: function () { return S.design.blocks.length; },
            addBlock: function (type, props, n) { return TYPES.hasOwnProperty(type) ? add(type, n || 0, props) : null; },
            select: function (id) { S.selected = idx(id) >= 0 ? id : null; draw(true); return api; },
            insertTag: insertTag,
            setTags: function (t) { setTags(t); return api; },
            templates: function () { return TPLS.slice(); },
            showTab: function (n) { showTab(n); return api; },
            undo: undo,
            refresh: function () { draw(true); glob(); return api; },
            mk: mk,
            on: function (n, fn) { (listeners[n] = listeners[n] || []).push(fn); return api; },
            off: function (n, fn) { listeners[n] = (listeners[n] || []).filter(function (x) { return x !== fn; }); return api; },
            destroy: function () { clearTimeout(changeT); clearTimeout(softT); offs.forEach(function (f) { f(); }); offs = []; listeners = {}; delete root.__slCanvas; }
        };
        root.__slCanvas = api;
        if (opts.design) setDesign(opts.design, true);
        else if (opts.start === 'template' && TPLS.length) setDesign(clone(TPLS[0].design), true);
        else { draw(true); glob(); }
        return api;
    }

    /**
     * window.SLEmailCanvas
     *   mount(rootEl | selector, options) -> canvas
     *     rootEl   the [data-ec-root] element of views/sl/inc/email_canvas.php, or any container:
     *              if it has no canvas inside, the first <template data-ec-template> (or options.template)
     *              is cloned into it.
     *     options  design      initial design JSON ({settings, blocks}); default empty (start:'template' = first template)
     *              tags        {'{first_name}': 'First name', ...} or {first_name: 'First name'} for the Tags tab
     *              tagSyntax   'bracket' (newsletter [TAGS], default) | 'brace' (comms {placeholders}: block
     *                          defaults and starter templates are converted, see tagMap)
     *              tagMap      extra/overriding {'[URL]': '{track_url}'} conversions
     *              cfg         {siteUrl, siteName, logo} (default window.NLB_CONFIG / SL_EMAIL_CANVAS_CFG)
     *              ajax        newsletter_ajax-compatible endpoint for designs/load/delete/upload; null = none
     *              uploadUrl   endpoint for action=upload (field image, answers {success, path}); null hides Upload
     *              saved       false hides the Saved tab; savedDelete false hides its delete buttons
     *              templates   false | array of {name, description, design, subject, preheader} | fn(cfg, mk)
     *                          (default window.NLB_TEMPLATES when loaded)
     *              devEl, undoEl  outside device switch / Undo button (default the canvas toolbar)
     *              onChange({design, reason}), onTemplate({template, subject, preheader}),
     *              onOpen({id, name, subject, preheader}), onDelete({id})
     *   Events (also DOM CustomEvents 'ec:change', 'ec:template', 'ec:open', 'ec:delete' on the root, bubbling).
     *   render(design, cfg?, subject?, preheader?) -> e-mail HTML; normalise(design); types() -> block types.
     */
    window.SLEmailCanvas = {
        mount: mount,
        render: function (design, cfg, subject, preheader) { return renderDesign(normalise(design), Object.assign({}, GCFG, cfg || {}), subject, preheader, 'export'); },
        previewHtml: function (design, cfg, subject, preheader) { return previewDoc(normalise(design), Object.assign({}, GCFG, cfg || {}), subject, preheader); },
        normalise: normalise,
        types: function () { return Object.keys(TYPES).map(function (k) { return { type: k, label: TYPES[k].label }; }); }
    };

    // =======================================================================
    // The Email builder page (newsletter.php): canvas + subject, save, send
    // =======================================================================
    function newsletterApp() {
        var CFG = GCFG, DRAFT_KEY = 'nlb_draft_v1';
        var $ = function (i) { return document.getElementById(i); };
        var P = { designId: 0, name: '' };
        var canvas;
        function meta_() { $('eb-name').textContent = P.name || 'New design'; var s = $('eb-subj').value.trim(), p = $('eb-pre').value.trim(); $('eb-subj-shown').textContent = s || '(none yet)'; $('eb-ib-s').textContent = s || 'Your subject line'; $('eb-ib-p').textContent = p || 'Preview text'; }
        function saveDraft() { if (!canvas) return; try { localStorage.setItem(DRAFT_KEY, JSON.stringify({ design: canvas.getDesign(), id: P.designId, name: P.name, subject: $('eb-subj').value, preheader: $('eb-pre').value })); } catch (e) {} }
        function load(design, meta) {
            meta = meta || {};
            P.designId = meta.id || 0; P.name = meta.name || '';
            if (meta.subject !== undefined) $('eb-subj').value = meta.subject || '';
            if (meta.preheader !== undefined) $('eb-pre').value = meta.preheader || '';
            canvas.setDesign(design); meta_();
        }
        canvas = mount($('eb-design'), {
            cfg: CFG, ajax: CFG.ajax || 'ajax/tools/newsletter_ajax.php', devEl: $('eb-dev'), undoEl: $('eb-undo'),
            onChange: saveDraft,
            onTemplate: function (d) { P.designId = 0; P.name = ''; $('eb-subj').value = d.subject; $('eb-pre').value = d.preheader; meta_(); },
            onOpen: function (d) { P.designId = d.id; P.name = d.name; $('eb-subj').value = d.subject || ''; $('eb-pre').value = d.preheader || ''; meta_(); },
            onDelete: function (d) { if (d.id === P.designId) { P.designId = 0; P.name = ''; meta_(); } }
        });
        var TPLS = canvas.templates();
        var html = function (s, p) { return canvas.getHtml(s, p); };

        $('eb-new').addEventListener('click', function () { if (!canvas.isEmpty() && !confirm('Start a new, empty design?')) return; var blank = TPLS.filter(function (t) { return t.id === 'blank'; })[0]; load(blank ? clone(blank.design) : { blocks: [] }, { id: 0, name: '', subject: '', preheader: '' }); });
        $('eb-save').addEventListener('click', function () {
            if (canvas.isEmpty()) { SL.toast('Add some content blocks first.', true); return; }
            var name = prompt(P.designId ? 'Design name (change it to save a copy as a new design)' : 'Name this design', P.name || $('eb-subj').value || 'Untitled design'); if (name === null) return; name = name.trim(); if (!name) return;
            var asNew = P.designId && name !== P.name && confirm('Save as a new design? Cancel updates "' + P.name + '" and renames it.');
            var fd = new FormData(), d = { action: 'save', id: asNew ? 0 : P.designId, name: name, subject: $('eb-subj').value, preheader: $('eb-pre').value, design_json: JSON.stringify(canvas.getDesign()), html: html($('eb-subj').value, $('eb-pre').value) };
            Object.keys(d).forEach(function (k) { fd.append(k, d[k]); });
            SL.post(CFG.ajax || 'ajax/tools/newsletter_ajax.php', fd).then(function (r) {
                if (!r.success) { SL.toast(errs(r, 'Not saved'), true); return; } P.designId = r.id; P.name = name; meta_(); saveDraft(); SL.toast('Design saved'); });
        });
        function ajax(data) { var fd = new FormData(); Object.keys(data).forEach(function (k) { fd.append(k, data[k]); }); return SL.post(CFG.ajax || 'ajax/tools/newsletter_ajax.php', fd); }
        function preview() { $('eb-pv-s').textContent = $('eb-subj').value || '(no subject)'; $('eb-pv-p').textContent = $('eb-pre').value; $('eb-frame').srcdoc = canvas.getPreviewHtml($('eb-subj').value, $('eb-pre').value); $('eb-dlg').hidden = false; }
        $('eb-pv').addEventListener('click', preview); $('eb-pv2').addEventListener('click', preview);
        $('eb-pvdev').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); $('eb-frame').style.width = b.getAttribute('data-w'); });

        /* steps and sending */
        function step(s) { document.querySelectorAll('.eb-steps button').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-step') === s); }); $('eb-design').hidden = s !== 'design'; $('eb-design').style.display = s === 'design' ? '' : 'none'; $('eb-send').hidden = s !== 'send'; $('eb-send').style.display = s === 'send' ? '' : 'none'; if (s === 'send') { count(); history_(); } }
        document.querySelector('.eb-steps').addEventListener('click', function (e) { var b = e.target.closest('[data-step]'); if (b) step(b.getAttribute('data-step')); });
        $('eb-back').addEventListener('click', function () { step('design'); });
        ['eb-subj', 'eb-pre'].forEach(function (i) { $(i).addEventListener('input', function () { meta_(); saveDraft(); }); });
        var reach = 0, ct;
        function count() { clearTimeout(ct); var aud = $('eb-aud').value; $('eb-emails-f').style.display = aud === 'emails' ? '' : 'none';
            ct = setTimeout(function () { ajax({ action: 'count', audience: aud, emails: $('eb-emails').value }).then(function (r) { if (!r.success) { $('eb-reach').textContent = errs(r); reach = 0; return; } reach = r.total; $('eb-reach').textContent = r.total.toLocaleString() + ' recipient' + (r.total === 1 ? '' : 's') + (r.invalid && r.invalid.length ? ' · not valid: ' + r.invalid.join(', ') : '');
                    $('eb-people').innerHTML = (r.people || []).map(function (p) { var n = p.name || p.email; var ini = n.split(/\s+/).map(function (w) { return w.charAt(0); }).join('').slice(0, 2).toUpperCase(); return '<div class="eb-pp"><span class="eb-av">' + esc(ini) + '</span><span class="col" style="min-width:0"><b>' + esc(p.name || '—') + '</b><small>' + esc(p.email) + '</small></span></div>'; }).join('') + (r.total > 5 ? '<div class="eb-more">+ ' + (r.total - 5).toLocaleString() + ' more</div>' : '');
                    $('eb-all').hidden = !r.total;
                    var c = r.cost || {}; $('eb-cost').textContent = (c.currency ? c.currency + ' ' : '') + (+c.total || 0).toFixed(2); $('eb-cost-d').textContent = r.total.toLocaleString() + ' e-mails × ' + (c.currency ? c.currency + ' ' : '') + (+c.rate || 0) + (c.rate ? '' : ' · set the e-mail price in Message Costs'); $('eb-send-btn').querySelector('span').textContent = 'Send to ' + r.total; }); }, 250); }
        $('eb-aud').addEventListener('change', count); $('eb-emails').addEventListener('input', count);
        $('eb-test-btn').addEventListener('click', function () { var s = $('eb-subj').value.trim(), btn = this; if (canvas.isEmpty()) { SL.toast('The design is empty.', true); return; } btn.disabled = true;
            ajax({ action: 'test', emails: $('eb-test').value, subject: s, html: html(s, $('eb-pre').value) }).then(function (r) { btn.disabled = false; $('eb-test-res').innerHTML = (r.lines || []).map(function (l) { return '<span>' + esc(l) + '</span>'; }).join('') + (r.errors && r.errors.length ? '<span>' + esc(r.errors.join(' ')) + '</span>' : ''); SL.toast(r.success ? 'Test sent to ' + r.sent : errs(r, 'Test not sent'), !r.success); }); });
        $('eb-send-btn').addEventListener('click', function () {
            var subj = $('eb-subj').value.trim(), aud = $('eb-aud').value, emails = $('eb-emails').value, btn = this;
            if (!subj) { SL.toast('Enter a subject line.', true); $('eb-subj').focus(); return; } if (canvas.isEmpty()) { SL.toast('The design is empty.', true); return; } if (!reach) { SL.toast('Nobody to send to.', true); return; }
            if (!confirm('Send "' + subj + '" to ' + reach + ' recipient(s) now?')) return;
            var body = html(subj, $('eb-pre').value), tot = { done: 0, sent: 0, failed: 0, skipped: 0, total: reach, batch: '', lines: [] }, retries = 0; btn.disabled = true; $('eb-prog').hidden = false;
            function prog() { $('eb-bar').style.width = Math.round(tot.done / Math.max(1, tot.total) * 100) + '%'; $('eb-prog-t').textContent = tot.done + ' / ' + tot.total + ' · delivered ' + tot.sent + ' · failed ' + tot.failed + ' · skipped ' + tot.skipped + (tot.batch ? ' · batch ' + tot.batch : ''); $('eb-lines').innerHTML = tot.lines.slice(-200).map(function (l) { return '<span>' + esc(l) + '</span>'; }).join(''); }
            function fin(problem) { btn.disabled = false; prog(); SL.toast(problem || (tot.sent ? 'Newsletter sent: ' + tot.sent + ' delivered' : 'Nothing was delivered'), !!problem || !tot.sent); history_(); }
            (function go(offset) { ajax({ action: 'send', audience: aud, emails: emails, subject: subj, html: body, design_id: P.designId, offset: offset, batch_id: tot.batch }).then(function (r) {
                if (!r.success || !r.summary) { fin((tot.done ? 'Stopped early: ' : '') + errs(r)); return; } retries = 0; var s = r.summary;
                tot.batch = s.batch_id; tot.total = s.total; tot.done += s.recipients; tot.sent += s.sent; tot.failed += s.failed; tot.skipped += s.skipped; tot.lines = tot.lines.concat(s.lines || []);
                if (s.done) fin(''); else { prog(); go(s.next_offset); } }).catch(function () { if (retries < 2 && tot.batch) { retries++; setTimeout(function () { go(offset); }, 3000); return; } fin('The connection failed after ' + tot.done + ' recipient(s). Check Message logs for batch ' + (tot.batch || '-') + ' before sending again.'); }); })(0);
        });
        function history_() { ajax({ action: 'history' }).then(function (r) { var l = (r && r.history) || []; $('eb-hist').innerHTML = l.length ? l.map(function (h) { var sent = +h.sent || 0, pc = function (n) { return sent ? ' <span class="mut sm">' + Math.round(n / sent * 100) + '%</span>' : ''; }; return '<tr><td><a class="col" href="campaigns.php?batch=' + encodeURIComponent(h.batch_id) + '#campaign"><b class="fw5">' + esc(h.subject) + '</b><span class="mut sm">' + esc(h.started_at) + ' · ' + esc(h.audience) + ' · ' + esc(h.sent_by) + '</span></a></td><td class="num">' + sent + '</td><td class="num">' + (+h.opened || 0) + pc(+h.opened || 0) + '</td><td class="num">' + (+h.clicked || 0) + pc(+h.clicked || 0) + '</td><td class="num">' + (+h.failed || 0) + '</td><td class="num">' + esc(h.cost || '') + '</td></tr>'; }).join('') : '<tr><td colspan="6" class="mut">No campaigns sent yet.</td></tr>'; }); }

        /* all recipients: search, page, edit one person */
        var pp = { page: 1, q: '', t: null };
        function people() { ajax({ action: 'people', audience: $('eb-aud').value, emails: $('eb-emails').value, q: pp.q, page: pp.page }).then(function (r) {
            if (!r.success) { $('eb-plist').innerHTML = '<p class="mut">' + esc(errs(r)) + '</p>'; return; }
            var pages = Math.max(1, Math.ceil(r.total / r.per)); $('eb-p-n').textContent = '· ' + r.total.toLocaleString(); $('eb-p-pg').textContent = 'Page ' + r.page + ' of ' + pages; $('eb-p-prev').disabled = r.page <= 1; $('eb-p-next').disabled = r.page >= pages;
            $('eb-plist').innerHTML = r.people.map(function (p) { return '<div class="eb-pr" data-id="' + p.id + '"><div class="eb-prv"><span class="eb-av">' + esc(((p.fname || p.email).charAt(0) + (p.lname || '').charAt(0)).toUpperCase()) + '</span><span class="col grow" style="min-width:0"><b>' + esc((p.fname + ' ' + p.lname).trim() || '—') + (p.locker ? ' <span class="mut sm">· ' + esc(p.locker) + '</span>' : '') + '</b><small>' + esc(p.email) + (p.phone ? ' · ' + esc(p.phone) : '') + (p.news ? '' : ' · <em>not subscribed</em>') + '</small></span>' + (r.canEdit && p.id ? '<button type="button" class="sl-btn ghost sm" data-pe>Edit</button>' : '') + (p.id ? '<a class="sl-btn ghost sm" href="customer_view.php?id=' + p.id + '" target="_blank" rel="noopener">Profile</a>' : '') + '</div>'
                + (r.canEdit && p.id ? '<form class="eb-pf" hidden><label><span>First name</span><input name="fname" value="' + esc(p.fname) + '"></label><label><span>Last name</span><input name="lname" value="' + esc(p.lname) + '"></label><label><span>E-mail</span><input name="email" type="email" value="' + esc(p.email) + '" required></label><label><span>Phone</span><input name="phone" value="' + esc(p.phone) + '"></label><label class="ck"><input type="checkbox" name="news" value="1"' + (p.news ? ' checked' : '') + '> Gets newsletters</label><div class="row g8"><button class="sl-btn sm" type="submit">Save</button><button class="sl-btn ghost sm" type="button" data-pc>Cancel</button></div></form>' : '') + '</div>'; }).join('') || '<p class="mut">Nobody matches.</p>';
        }); }
        $('eb-all').addEventListener('click', function () { pp.page = 1; pp.q = ''; $('eb-p-q').value = ''; $('eb-pdlg').hidden = false; document.body.style.overflow = 'hidden'; people(); });
        $('eb-p-q').addEventListener('input', function () { clearTimeout(pp.t); pp.t = setTimeout(function () { pp.q = $('eb-p-q').value.trim(); pp.page = 1; people(); }, 250); });
        $('eb-p-prev').addEventListener('click', function () { if (pp.page > 1) { pp.page--; people(); } });
        $('eb-p-next').addEventListener('click', function () { pp.page++; people(); });
        $('eb-plist').addEventListener('click', function (e) { var row = e.target.closest('.eb-pr'); if (!row) return; var f = row.querySelector('.eb-pf'); if (!f) return; if (e.target.closest('[data-pe]')) f.hidden = !f.hidden; if (e.target.closest('[data-pc]')) f.hidden = true; });
        $('eb-plist').addEventListener('submit', function (e) { var f = e.target.closest('.eb-pf'); if (!f) return; e.preventDefault(); var row = f.closest('.eb-pr'), d = { action: 'person_save', id: row.getAttribute('data-id') }; new FormData(f).forEach(function (v, k) { d[k] = v; }); if (!d.news) d.news = 0;
            ajax(d).then(function (r) { SL.toast(r.success ? 'Saved' : errs(r), !r.success); if (r.success) { people(); count(); } }); });

        /* start: the unsaved draft from this browser, else the first template */
        var dr = null; try { dr = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) {}
        if (dr && dr.design && dr.design.blocks) load(dr.design, { id: dr.id || 0, name: dr.name || '', subject: dr.subject || '', preheader: dr.preheader || '' });
        else if (TPLS.length) load(clone(TPLS[0].design), { id: 0, name: '', subject: TPLS[0].subject || '', preheader: TPLS[0].preheader || '' });
        else load({ blocks: [] }, {});
        if ((CFG.preset || []).length) { $('eb-aud').value = 'emails'; step('send'); } else step('design');
    }

    if (document.getElementById('eb-design') && document.getElementById('eb-send')) newsletterApp();
})();
