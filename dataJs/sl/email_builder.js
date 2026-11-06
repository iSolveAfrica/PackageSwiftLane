/* Email builder (views/sl/pages/EmailBuilder.php). Plain JS, no jQuery.
 * Design JSON ({ settings, blocks:[{id,type,props}] }) and the e-mail HTML renderer are the
 * same as the previous builder (dataJs/newsletter.js), so saved designs in
 * cdb_newsletter_designs open unchanged. Server: ajax/tools/newsletter_ajax.php. */
(function () {
    'use strict';
    var CFG = window.NLB_CONFIG || {};
    var AJAX = CFG.ajax || 'ajax/tools/newsletter_ajax.php';
    var DRAFT_KEY = 'nlb_draft_v1';
    var WIDTH = 600;
    var S = { design: null, selected: null, designId: 0, name: '', undo: [], drag: null };
    var $ = function (i) { return document.getElementById(i); };
    function uid() { return 'b' + Math.random().toString(36).slice(2, 10); }
    function clone(o) { return JSON.parse(JSON.stringify(o)); }
    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function num(v, d) { var n = parseFloat(v); return isNaN(n) ? d : n; }
    function onColor(bg) { var h = String(bg || '').replace('#', ''); if (h.length === 3) h = h.replace(/(.)/g, '$1$1'); if (h.length < 6) return '#192A3E'; var r = parseInt(h.substr(0, 2), 16), g = parseInt(h.substr(2, 2), 16), b = parseInt(h.substr(4, 2), 16); return (r * 299 + g * 587 + b * 114) / 1000 > 150 ? '#192A3E' : '#FFFFFF'; }
    function isColor(c) { return /^#[0-9a-f]{3,8}$/i.test(String(c || '')); }
    function col(c, d) { return isColor(c) ? c : d; }
    function absUrl(u) { u = String(u || '').trim(); if (!u || /^([a-z][a-z0-9+.-]*:|\/\/|#|\[)/i.test(u)) return u; return (CFG.siteUrl || '') + '/' + u.replace(/^\.?\/+/, ''); }
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
    function ajax(data) { var fd = new FormData(); Object.keys(data).forEach(function (k) { fd.append(k, data[k]); }); return SL.post(AJAX, fd); }
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

    function mk(type, props, n) {
        var t = TYPES[type];
        var p = Object.assign({}, clone(t.d), clone(props || {}));
        if (type === 'columns' && !p.cols) p.cols = defaultCols(n || 2);
        if (type === 'logo' && !p.src) p.src = CFG.logo || '';
        if (type === 'logo' && !p.alt) p.alt = CFG.siteName || '';
        return { id: uid(), type: type, props: p };
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

    function settings() { return S.design.settings; }

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
                    : (mode === 'edit' ? '<div class="nlb-ph">Add your logo in the block settings</div>' : '<span style="font-family:' + st.font + ';font-size:22px;font-weight:700;color:' + st.headingColor + ';">' + esc(CFG.siteName) + '</span>');
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
        var st = S.design ? settings() : blankSettings();
        return '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' +
            '<meta http-equiv="X-UA-Compatible" content="IE=edge"><meta name="x-apple-disable-message-reformatting">' +
            '<title>' + esc(title || '') + '</title><style>' + EMAIL_CSS + 'a{color:' + st.linkColor + ';}</style>';
    }

    /** The complete e-mail, ready to send. */
    function exportHtml(subject, preheader, mode) {
        var st = settings();
        var body = S.design.blocks.map(function (b) { return renderBlock(b, mode || 'export'); }).join('\n');
        var pre = preheader ? '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">' + esc(preheader) + '&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>' : '';
        return '<!DOCTYPE html>\n<html lang="en">' + emailHead(subject) + '</head>' +
            '<body style="margin:0;padding:0;background-color:' + st.bodyBg + ';">' + pre +
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' + st.bodyBg + '" style="background-color:' + st.bodyBg + ';">' +
            '<tr><td align="center" style="padding:24px 10px;">' +
            '<table role="presentation" class="nl-container" width="' + WIDTH + '" cellpadding="0" cellspacing="0" border="0" bgcolor="' + st.contentBg + '" style="width:' + WIDTH + 'px;max-width:' + WIDTH + 'px;background-color:' + st.contentBg + ';">' +
            '<tr><td style="padding:0;">\n' + body + '\n</td></tr></table>' +
            '</td></tr></table></body></html>';
    }

    /** Same e-mail with relative image paths (for in-page previews). */
    function previewHtml(subject, preheader) {
        return exportHtml(subject, preheader, 'preview').replace('<head>', '<head><base href="' + esc(location.href.replace(/[^/]*$/, '')) + '" target="_blank">');
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

    function load(design, meta) {
        meta = meta || {};
        S.design = { settings: Object.assign(blankSettings(), design.settings || {}), blocks: (design.blocks || []).filter(function (b) { return b && TYPES[b.type]; }) };
        S.design.blocks.forEach(function (b) { b.id = b.id || uid(); b.props = Object.assign({}, clone(TYPES[b.type].d), b.props || {}); if (b.type === 'columns' && !b.props.cols) b.props.cols = defaultCols(2); });
        S.selected = null; S.designId = meta.id || 0; S.name = meta.name || ''; S.undo = [];
        if (meta.subject !== undefined) $('eb-subj').value = meta.subject || '';
        if (meta.preheader !== undefined) $('eb-pre').value = meta.preheader || '';
        draw(); glob(); meta_();
    }
    function meta_() { $('eb-name').textContent = S.name || 'New design'; var s = $('eb-subj').value.trim(), p = $('eb-pre').value.trim(); $('eb-subj-shown').textContent = s || '(none yet)'; $('eb-ib-s').textContent = s || 'Your subject line'; $('eb-ib-p').textContent = p || 'Preview text'; }
    function snap() { S.undo.push(JSON.stringify(S.design)); if (S.undo.length > 60) S.undo.shift(); }
    function saveDraft() { try { localStorage.setItem(DRAFT_KEY, JSON.stringify({ design: S.design, id: S.designId, name: S.name, subject: $('eb-subj').value, preheader: $('eb-pre').value })); } catch (e) {} }
    function idx(id) { for (var i = 0; i < S.design.blocks.length; i++) if (S.design.blocks[i].id === id) return i; return -1; }
    function sel() { var i = idx(S.selected); return i >= 0 ? S.design.blocks[i] : null; }

    function draw() {
        var w = +(document.querySelector('#eb-dev .on') || { getAttribute: function () { return 600; } }).getAttribute('data-w');
        var st = S.design.settings, m = $('eb-mail');
        m.style.width = w + 'px'; m.style.background = st.contentBg; $('eb-stage').style.background = st.bodyBg;
        m.innerHTML = S.design.blocks.map(function (b) {
            return '<div class="eb-drop" data-at="' + idx(b.id) + '"></div><div class="eb-b' + (b.id === S.selected ? ' sel' : '') + '" data-id="' + b.id + '" draggable="true"><span class="eb-tag">' + esc(TYPES[b.type].label) + '</span><span class="eb-tb"><button type="button" data-op="up" aria-label="Move up"><i class="ic ic-sm i-up" aria-hidden="true"></i></button><button type="button" data-op="down" aria-label="Move down"><i class="ic ic-sm i-down" aria-hidden="true"></i></button><button type="button" data-op="dup" aria-label="Duplicate"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button><button type="button" data-op="del" aria-label="Delete block"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></span>' + renderBlock(b, 'preview') + '</div>';
        }).join('') + '<div class="eb-drop" data-at="' + S.design.blocks.length + '" style="margin-bottom:16px">' + (S.design.blocks.length ? '' : '<div class="sl-empty mut sm" style="padding:60px 12px;text-align:center">Drag a block here, or click one in the palette</div>') + '</div>';
        m.querySelectorAll('a').forEach(function (a) { a.removeAttribute('href'); });
        $('eb-count').textContent = S.design.blocks.length + ' blocks · ' + w + ' px';
        insp(); saveDraft();
    }
    function glob() { var st = S.design.settings; $('eb-glob').innerHTML = GLOB.map(function (g) { return '<label class="row g6 sm"><input type="color" data-glob="' + g[0] + '" value="' + (isColor(st[g[0]]) && st[g[0]].length === 7 ? st[g[0]] : '#ffffff') + '" aria-label="' + g[1] + ' colour"> ' + g[1] + '</label>'; }).join(''); }
    $('eb-glob').addEventListener('input', function (e) { var k = e.target.getAttribute('data-glob'); if (!k) return; S.design.settings[k] = e.target.value; draw(); });

    function field(f, val, path) {
        var data = ' data-path="' + esc(path || f.k) + '"', lab = f.l ? '<span class="sl-lab">' + esc(f.l) + '</span>' : '';
        switch (f.t) {
            case 'note': return '<span class="mut sm">' + esc(f.l) + '</span>';
            case 'text': return '<label class="sl-field">' + lab + '<input class="sl-in"' + data + ' value="' + esc(val || '') + '"></label>';
            case 'textarea': return '<label class="sl-field">' + lab + '<textarea class="eb-ta mono" rows="' + (f.rows || 4) + '"' + data + '>' + esc(val || '') + '</textarea></label>';
            case 'range': return '<label class="sl-field">' + lab + '<span class="row g8"><input type="range" class="grow"' + data + ' min="' + f.min + '" max="' + f.max + '" step="' + (f.step || 1) + '" value="' + esc(val) + '"><output class="mono sm">' + esc(val) + '</output></span></label>';
            case 'color': return '<div class="sl-field">' + lab + '<span class="row g8"><input type="color"' + data + ' value="' + (isColor(val) && val.length === 7 ? val : '#ffffff') + '" aria-label="' + esc(f.l) + '"><input class="sl-in mono grow"' + data + ' data-hex value="' + esc(isColor(val) ? val : '') + '" placeholder="' + (f.clear ? 'Default' : '#000000') + '" maxlength="7">' + (f.clear ? '<button type="button" class="sl-btn ghost sm"' + data + ' data-clear aria-label="Use the default"><i class="ic ic-sm i-x" aria-hidden="true"></i></button>' : '') + '</span></div>';
            case 'align': var o = ['left', 'center', 'right']; if (f.justify) o.push('justify'); return '<div class="sl-field"><span class="sl-lab">Align</span><div class="sl-seg boxed">' + o.map(function (a) { return '<button type="button"' + data + ' data-val="' + a + '" class="' + (val === a ? 'on' : '') + '">' + a.charAt(0).toUpperCase() + a.slice(1) + '</button>'; }).join('') + '</div></div>';
            case 'select': return '<label class="sl-field">' + lab + '<span class="sl-sel native"><select' + data + '>' + f.o.map(function (x) { return '<option value="' + esc(x[0]) + '"' + (String(val) === String(x[0]) ? ' selected' : '') + '>' + esc(x[1]) + '</option>'; }).join('') + '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>';
            case 'check': return '<label class="row g8 sm"><input type="checkbox"' + data + (val ? ' checked' : '') + '> ' + esc(f.l) + '</label>';
            case 'image': return '<div class="sl-field">' + lab + '<input class="sl-in"' + data + ' value="' + esc(val || '') + '" placeholder="https://... or assets/..."><div class="row g6 wrap"><button type="button" class="sl-btn ghost sm"' + data + ' data-upload><i class="ic ic-sm i-upload" aria-hidden="true"></i>Upload</button>' + STOCK.concat(CFG.logo ? [null] : []).map(function (s) { var u = s ? 'assets/images/newsletter/' + s : CFG.logo; return '<button type="button"' + data + ' data-val="' + esc(u) + '" style="width:40px;height:30px;padding:0;border:1px solid var(--line);border-radius:6px;background:center/cover url(\'' + esc(u) + '\');cursor:pointer" aria-label="Use ' + esc(s || 'logo') + '"></button>'; }).join('') + '</div></div>';
            case 'social': return '<div class="col g6"><span class="sl-lab">Links</span>' + (val || []).map(function (l, i) { return '<span class="row g6"><span class="sl-sel native" style="width:130px"><select data-path="links.' + i + '.net">' + Object.keys(SOCIAL).map(function (k) { return '<option value="' + k + '"' + (l.net === k ? ' selected' : '') + '>' + SOCIAL[k] + '</option>'; }).join('') + '</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span><input class="sl-in grow" data-path="links.' + i + '.url" value="' + esc(l.url) + '" placeholder="https://"><button type="button" class="sl-btn ghost sm" data-soc-del="' + i + '" aria-label="Remove link"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></span>'; }).join('') + '<button type="button" class="sl-btn ghost sm" data-soc-add><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add link</button></div>';
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
        var b = sel(), box = $('eb-insp');
        if (!b) { box.hidden = true; box.innerHTML = ''; return; }
        box.hidden = false;
        box.innerHTML = '<div class="sl-ch" style="margin:0"><h2 class="sl-ct">' + esc(TYPES[b.type].label) + '</h2><button type="button" class="sl-ib" style="width:32px;height:32px" aria-label="Close" data-close><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>' +
            (FIELDS[b.type] || []).map(function (f) { return field(f, f.k ? b.props[f.k] : (f.t === 'social' ? b.props.links : null)); }).join('') +
            (b.type !== 'spacer' ? '<details class="col g8"><summary class="sm fw5">Spacing and background</summary>' + COMMON.map(function (f) { return field(f, b.props[f.k]); }).join('') + '</details>' : '');
    }
    function setPath(obj, path, val) { var ks = path.split('.'), o = obj; for (var i = 0; i < ks.length - 1; i++) o = o[isNaN(ks[i]) ? ks[i] : +ks[i]]; o[ks[ks.length - 1]] = val; }
    var softT;
    function setVal(path, val, redrawInsp) { var b = sel(); if (!b) return; setPath(b.props, path, val); clearTimeout(softT); var m = $('eb-mail'), el = m.querySelector('.eb-b[data-id="' + b.id + '"]'); if (el) { var tmp = document.createElement('div'); tmp.innerHTML = renderBlock(b, 'preview'); tmp.querySelectorAll('a').forEach(function (a) { a.removeAttribute('href'); }); Array.prototype.slice.call(el.children, 2).forEach(function (x) { x.remove(); }); while (tmp.firstChild) el.appendChild(tmp.firstChild); } softT = setTimeout(saveDraft, 500); if (redrawInsp) insp(); }
    $('eb-insp').addEventListener('focusin', function () { if (!S._snapped) { snap(); S._snapped = true; } });
    $('eb-insp').addEventListener('focusout', function () { S._snapped = false; });
    $('eb-insp').addEventListener('input', function (e) { var t = e.target, p = t.getAttribute('data-path'); if (!p) return;
        if (t.type === 'checkbox') return;
        if (t.type === 'range') { t.nextElementSibling.textContent = t.value; setVal(p, num(t.value, 0)); return; }
        if (t.hasAttribute('data-hex')) { if (isColor(t.value) || t.value === '') { setVal(p, t.value); var c = t.parentNode.querySelector('input[type=color]'); if (isColor(t.value) && t.value.length === 7) c.value = t.value; } return; }
        if (t.type === 'color') { var hx = t.parentNode.querySelector('[data-hex]'); if (hx) hx.value = t.value; }
        setVal(p, t.value); });
    $('eb-insp').addEventListener('change', function (e) { var t = e.target, p = t.getAttribute('data-path'); if (!p) return; if (t.type === 'checkbox') { snap(); setVal(p, t.checked, /\.img$/.test(p)); } else if (t.tagName === 'SELECT') { snap(); setVal(p, t.value); } });
    var upPath = null;
    $('eb-insp').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; var p = b.getAttribute('data-path'), blk = sel();
        if (b.hasAttribute('data-close')) { S.selected = null; draw(); return; }
        if (b.hasAttribute('data-clear')) { snap(); setVal(p, '', true); return; }
        if (b.hasAttribute('data-upload')) { upPath = p; $('eb-file').click(); return; }
        if (b.hasAttribute('data-ncols')) { snap(); var n = +b.getAttribute('data-ncols'), cs = blk.props.cols, d = defaultCols(n); blk.props.cols = d.map(function (x, i) { return cs[i] || x; }); draw(); return; }
        if (b.hasAttribute('data-soc-add')) { snap(); blk.props.links.push({ net: 'website', url: '' }); setVal('links', blk.props.links, true); return; }
        if (b.hasAttribute('data-soc-del')) { snap(); blk.props.links.splice(+b.getAttribute('data-soc-del'), 1); setVal('links', blk.props.links, true); return; }
        if (p && b.hasAttribute('data-val')) { snap(); setVal(p, b.getAttribute('data-val'), true); } });
    $('eb-file').addEventListener('change', function () { var f = this.files[0]; this.value = ''; if (!f || !upPath) return; SL.toast('Uploading…');
        ajax({ action: 'upload', image: f }).then(function (r) { if (!r.success) { SL.toast(errs(r, 'Upload failed'), true); return; } snap(); setVal(upPath, r.path, true); SL.toast('Image uploaded'); }); });

    /* canvas: select, toolbar, drag and drop */
    function insertAt(i, b) { snap(); S.design.blocks.splice(i, 0, b); S.selected = b.id; draw(); }
    function add(type, n) { var b = mk(type, null, n), i = idx(S.selected); insertAt(i >= 0 ? i + 1 : S.design.blocks.length, b); }
    $('eb-mail').addEventListener('click', function (e) { var op = e.target.closest('[data-op]'), el = e.target.closest('.eb-b'); if (!el) return; var id = el.getAttribute('data-id'), i = idx(id), bl = S.design.blocks;
        if (op) { snap(); var o = op.getAttribute('data-op');
            if (o === 'del') { bl.splice(i, 1); if (S.selected === id) S.selected = null; }
            else if (o === 'dup') { var c = clone(bl[i]); c.id = uid(); bl.splice(i + 1, 0, c); S.selected = c.id; }
            else if (o === 'up' && i > 0) { bl.splice(i - 1, 0, bl.splice(i, 1)[0]); }
            else if (o === 'down' && i < bl.length - 1) { bl.splice(i + 1, 0, bl.splice(i, 1)[0]); }
            draw(); return; }
        S.selected = id; $('eb-mail').querySelectorAll('.eb-b').forEach(function (x) { x.classList.toggle('sel', x === el); }); insp(); });
    $('eb-mail').addEventListener('dragstart', function (e) { var el = e.target.closest && e.target.closest('.eb-b'); if (!el) return; S.drag = { kind: 'move', id: el.getAttribute('data-id') }; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', 'm'); } catch (x) {} });
    function dropAt(y) { var drops = $('eb-mail').querySelectorAll('.eb-drop'), best = null, bd = 1e9; drops.forEach(function (d) { var r = d.getBoundingClientRect(), dd = Math.abs(r.top + r.height / 2 - y); if (dd < bd) { bd = dd; best = d; } }); return best; }
    $('eb-stage').addEventListener('dragover', function (e) { if (!S.drag) return; e.preventDefault(); var d = dropAt(e.clientY); $('eb-mail').querySelectorAll('.eb-drop').forEach(function (x) { x.classList.toggle('on', x === d); }); });
    $('eb-stage').addEventListener('drop', function (e) { if (!S.drag) return; e.preventDefault(); var d = dropAt(e.clientY), at = d ? +d.getAttribute('data-at') : S.design.blocks.length, g = S.drag; S.drag = null;
        if (g.kind === 'new') insertAt(at, mk(g.type, null, g.n));
        else { var i = idx(g.id); if (i < 0) return; snap(); var b = S.design.blocks.splice(i, 1)[0]; if (i < at) at--; S.design.blocks.splice(at, 0, b); draw(); } });
    document.addEventListener('dragend', function () { S.drag = null; document.querySelectorAll('.eb-drop.on').forEach(function (x) { x.classList.remove('on'); }); });

    /* palette, templates, saved designs, variables */
    $('eb-pal').innerHTML = PALETTE.map(function (p) { return '<button type="button" class="eb-blk" draggable="true" data-type="' + p[0] + '"' + (p[1] ? ' data-n="' + p[1] + '"' : '') + '><i class="ic ' + (ICON[p[0]] || 'i-grid') + '" aria-hidden="true"></i>' + esc(p[2] || TYPES[p[0]].label) + '</button>'; }).join('');
    $('eb-pal').addEventListener('click', function (e) { var b = e.target.closest('[data-type]'); if (b) add(b.getAttribute('data-type'), +(b.getAttribute('data-n') || 0)); });
    $('eb-pal').addEventListener('dragstart', function (e) { var b = e.target.closest('[data-type]'); if (!b) return; S.drag = { kind: 'new', type: b.getAttribute('data-type'), n: +(b.getAttribute('data-n') || 0) }; try { e.dataTransfer.setData('text/plain', 'n'); } catch (x) {} });
    $('eb-ltabs').addEventListener('click', function (e) { var b = e.target.closest('[data-t]'); if (!b) return; this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); document.querySelectorAll('[data-lt]').forEach(function (p) { p.hidden = p.getAttribute('data-lt') !== b.getAttribute('data-t'); }); if (b.getAttribute('data-t') === 'saved') loadSaved(); });
    var TPLS = typeof window.NLB_TEMPLATES === 'function' ? window.NLB_TEMPLATES(CFG, mk) : [];
    $('eb-tpls').innerHTML = TPLS.map(function (t, i) { return '<button type="button" class="eb-tpl" data-tpl="' + i + '"><i class="ic ic-sm i-mail mut" aria-hidden="true"></i><span class="grow col" style="text-align:left"><span>' + esc(t.name) + '</span><small class="mut">' + esc(t.description || '') + '</small></span></button>'; }).join('');
    $('eb-tpls').addEventListener('click', function (e) { var b = e.target.closest('[data-tpl]'); if (!b) return; if (S.design.blocks.length && !confirm('Replace the current design with this template?')) return; var t = TPLS[+b.getAttribute('data-tpl')]; snap(); load(clone(t.design), { id: 0, name: '', subject: t.subject || '', preheader: t.preheader || '' }); });
    function loadSaved() { ajax({ action: 'designs' }).then(function (r) { var l = (r && r.designs) || [];
        $('eb-saved').innerHTML = l.length ? l.map(function (x) { return '<div class="row g6"><button type="button" class="eb-tpl grow" data-load="' + (+x.id) + '"><i class="ic ic-sm i-file mut" aria-hidden="true"></i><span class="grow col" style="text-align:left"><span>' + esc(x.name) + '</span><small class="mut">' + esc(x.subject || '') + ' · ' + esc(x.updated_at || '') + (x.last_sent_at ? ' · sent ' + esc(x.last_sent_at) : '') + '</small></span></button><button type="button" class="sl-btn ghost sm" data-del="' + (+x.id) + '" data-name="' + esc(x.name) + '" aria-label="Delete design"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></div>'; }).join('') : '<span class="mut sm">No saved designs yet.</span>'; }); }
    $('eb-saved').addEventListener('click', function (e) { var b = e.target.closest('[data-load],[data-del]'); if (!b) return;
        if (b.hasAttribute('data-del')) { if (!confirm('Delete the design "' + b.getAttribute('data-name') + '"?')) return; ajax({ action: 'delete', id: b.getAttribute('data-del') }).then(function (r) { if (!r.success) { SL.toast(errs(r), true); return; } if (+b.getAttribute('data-del') === S.designId) { S.designId = 0; S.name = ''; meta_(); } SL.toast('Design deleted'); loadSaved(); }); return; }
        if (S.design.blocks.length && !confirm('Open this design? Unsaved changes to the current one are lost.')) return;
        ajax({ action: 'load', id: b.getAttribute('data-load') }).then(function (r) { if (!r.success) { SL.toast(errs(r), true); return; } var d; try { d = JSON.parse(r.design.json); } catch (x) { SL.toast('That design could not be read.', true); return; } load(d, { id: r.design.id, name: r.design.name, subject: r.design.subject, preheader: r.design.preheader }); SL.toast('Opened "' + r.design.name + '"'); }); });
    document.querySelector('[data-lt="vars"]').addEventListener('click', function (e) { var b = e.target.closest('[data-var]'); if (!b) return; var v = b.getAttribute('data-var'), ta = $('eb-insp').querySelector('textarea, input.sl-in:not([data-hex])');
        if (!ta) { navigator.clipboard && navigator.clipboard.writeText(v); SL.toast(v + ' copied: select a text block to insert it'); return; }
        var s = ta.selectionStart || ta.value.length; ta.value = ta.value.slice(0, s) + v + ta.value.slice(ta.selectionEnd || s); ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus(); });

    /* toolbar */
    $('eb-dev').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); draw(); });
    $('eb-undo').addEventListener('click', function () { var s = S.undo.pop(); if (!s) { SL.toast('Nothing to undo'); return; } S.design = JSON.parse(s); draw(); glob(); });
    $('eb-new').addEventListener('click', function () { if (S.design.blocks.length && !confirm('Start a new, empty design?')) return; var blank = TPLS.filter(function (t) { return t.id === 'blank'; })[0]; load(blank ? clone(blank.design) : { blocks: [] }, { id: 0, name: '', subject: '', preheader: '' }); });
    $('eb-save').addEventListener('click', function () {
        if (!S.design.blocks.length) { SL.toast('Add some content blocks first.', true); return; }
        var name = prompt(S.designId ? 'Design name (change it to save a copy as a new design)' : 'Name this design', S.name || $('eb-subj').value || 'Untitled design'); if (name === null) return; name = name.trim(); if (!name) return;
        var asNew = S.designId && name !== S.name && confirm('Save as a new design? Cancel updates "' + S.name + '" and renames it.');
        ajax({ action: 'save', id: asNew ? 0 : S.designId, name: name, subject: $('eb-subj').value, preheader: $('eb-pre').value, design_json: JSON.stringify(S.design), html: exportHtml($('eb-subj').value, $('eb-pre').value) }).then(function (r) {
            if (!r.success) { SL.toast(errs(r, 'Not saved'), true); return; } S.designId = r.id; S.name = name; meta_(); saveDraft(); SL.toast('Design saved'); });
    });
    function preview() { $('eb-pv-s').textContent = $('eb-subj').value || '(no subject)'; $('eb-pv-p').textContent = $('eb-pre').value; $('eb-frame').srcdoc = previewHtml($('eb-subj').value, $('eb-pre').value); $('eb-dlg').hidden = false; }
    $('eb-pv').addEventListener('click', preview); $('eb-pv2').addEventListener('click', preview);
    $('eb-pvdev').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); $('eb-frame').style.width = b.getAttribute('data-w'); });

    /* steps and sending */
    function step(s) { document.querySelectorAll('.eb-steps button').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-step') === s); }); $('eb-design').hidden = s !== 'design'; $('eb-design').style.display = s === 'design' ? '' : 'none'; $('eb-send').hidden = s !== 'send'; $('eb-send').style.display = s === 'send' ? '' : 'none'; if (s === 'send') { count(); history_(); } }
    document.querySelector('.eb-steps').addEventListener('click', function (e) { var b = e.target.closest('[data-step]'); if (b) step(b.getAttribute('data-step')); });
    $('eb-back').addEventListener('click', function () { step('design'); });
    ['eb-subj', 'eb-pre'].forEach(function (i) { $(i).addEventListener('input', function () { meta_(); saveDraft(); }); });
    var reach = 0, ct;
    function count() { clearTimeout(ct); var aud = $('eb-aud').value; $('eb-emails-f').style.display = aud === 'emails' ? '' : 'none';
        ct = setTimeout(function () { ajax({ action: 'count', audience: aud, emails: $('eb-emails').value }).then(function (r) { if (!r.success) { $('eb-reach').textContent = errs(r); reach = 0; return; } reach = r.total; $('eb-reach').textContent = r.total + ' recipient(s)' + (r.sample && r.sample.length ? ', e.g. ' + r.sample.join(', ') : '') + (r.invalid && r.invalid.length ? '. Not valid: ' + r.invalid.join(', ') : '') + '.'; $('eb-send-btn').querySelector('span').textContent = 'Send to ' + r.total; }); }, 250); }
    $('eb-aud').addEventListener('change', count); $('eb-emails').addEventListener('input', count);
    $('eb-test-btn').addEventListener('click', function () { var s = $('eb-subj').value.trim(), btn = this; if (!S.design.blocks.length) { SL.toast('The design is empty.', true); return; } btn.disabled = true;
        ajax({ action: 'test', emails: $('eb-test').value, subject: s, html: exportHtml(s, $('eb-pre').value) }).then(function (r) { btn.disabled = false; $('eb-test-res').innerHTML = (r.lines || []).map(function (l) { return '<span>' + esc(l) + '</span>'; }).join('') + (r.errors && r.errors.length ? '<span>' + esc(r.errors.join(' ')) + '</span>' : ''); SL.toast(r.success ? 'Test sent to ' + r.sent : errs(r, 'Test not sent'), !r.success); }); });
    $('eb-send-btn').addEventListener('click', function () {
        var subj = $('eb-subj').value.trim(), aud = $('eb-aud').value, emails = $('eb-emails').value, btn = this;
        if (!subj) { SL.toast('Enter a subject line.', true); $('eb-subj').focus(); return; } if (!S.design.blocks.length) { SL.toast('The design is empty.', true); return; } if (!reach) { SL.toast('Nobody to send to.', true); return; }
        if (!confirm('Send "' + subj + '" to ' + reach + ' recipient(s) now?')) return;
        var html = exportHtml(subj, $('eb-pre').value), tot = { done: 0, sent: 0, failed: 0, skipped: 0, total: reach, batch: '', lines: [] }, retries = 0; btn.disabled = true; $('eb-prog').hidden = false;
        function prog() { $('eb-bar').style.width = Math.round(tot.done / Math.max(1, tot.total) * 100) + '%'; $('eb-prog-t').textContent = tot.done + ' / ' + tot.total + ' · delivered ' + tot.sent + ' · failed ' + tot.failed + ' · skipped ' + tot.skipped + (tot.batch ? ' · batch ' + tot.batch : ''); $('eb-lines').innerHTML = tot.lines.slice(-200).map(function (l) { return '<span>' + esc(l) + '</span>'; }).join(''); }
        function fin(problem) { btn.disabled = false; prog(); SL.toast(problem || (tot.sent ? 'Newsletter sent: ' + tot.sent + ' delivered' : 'Nothing was delivered'), !!problem || !tot.sent); history_(); }
        (function go(offset) { ajax({ action: 'send', audience: aud, emails: emails, subject: subj, html: html, design_id: S.designId, offset: offset, batch_id: tot.batch }).then(function (r) {
            if (!r.success || !r.summary) { fin((tot.done ? 'Stopped early: ' : '') + errs(r)); return; } retries = 0; var s = r.summary;
            tot.batch = s.batch_id; tot.total = s.total; tot.done += s.recipients; tot.sent += s.sent; tot.failed += s.failed; tot.skipped += s.skipped; tot.lines = tot.lines.concat(s.lines || []);
            if (s.done) fin(''); else { prog(); go(s.next_offset); } }).catch(function () { if (retries < 2 && tot.batch) { retries++; setTimeout(function () { go(offset); }, 3000); return; } fin('The connection failed after ' + tot.done + ' recipient(s). Check Message logs for batch ' + (tot.batch || '-') + ' before sending again.'); }); })(0);
    });
    function history_() { ajax({ action: 'history' }).then(function (r) { var l = (r && r.history) || []; $('eb-hist').innerHTML = l.length ? l.map(function (h) { return '<tr><td>' + esc(h.started_at) + '</td><td class="wrap">' + esc(h.subject) + '</td><td>' + esc(h.audience) + '</td><td>' + esc(h.sent_by) + '</td><td>' + (+h.sent || 0) + '</td><td>' + (+h.failed || 0) + '</td></tr>'; }).join('') : '<tr><td colspan="6" class="mut">No campaigns sent yet.</td></tr>'; }); }

    /* start: the unsaved draft from this browser, else the first template */
    var dr = null; try { dr = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) {}
    if (dr && dr.design && dr.design.blocks) load(dr.design, { id: dr.id || 0, name: dr.name || '', subject: dr.subject || '', preheader: dr.preheader || '' });
    else if (TPLS.length) load(clone(TPLS[0].design), { id: 0, name: '', subject: TPLS[0].subject || '', preheader: TPLS[0].preheader || '' });
    else load({ blocks: [] }, {});
    if ((CFG.preset || []).length) { $('eb-aud').value = 'emails'; step('send'); } else step('design');
})();
