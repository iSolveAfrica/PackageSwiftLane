/* Presentation only: format already-authorized replies and export their visible
 * contents locally. This module makes no requests to an AI provider. */
(function () {
  'use strict';
  var SL = window.SL = window.SL || {};
  function element(tag, text, cls) {
    var el = document.createElement(tag);
    if (text != null) el.textContent = text;
    if (cls) el.className = cls;
    return el;
  }
  function button(label, action) {
    var b = element('button', label, 'sl-btn ghost sm'); b.type = 'button'; b.addEventListener('click', action); return b;
  }
  function download(content, type, filename) {
    var url = URL.createObjectURL(new Blob([content], { type: type }));
    var a = element('a'); a.href = url; a.download = filename; a.click();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }
  function csv(table) {
    return '\uFEFF' + Array.from(table.rows).map(function (row) {
      return Array.from(row.cells).map(function (cell) {
        var text = cell.textContent.trim();
        // A spreadsheet must treat customer-supplied names as text, not formulas.
        if (/^[\s]*[=+@-]/.test(text) || /^[\t\r\n]/.test(text)) text = "'" + text;
        return '"' + text.replace(/"/g, '""') + '"';
      }).join(',');
    }).join('\r\n');
  }
  var documentStyle = 'body{font:15px/1.65 system-ui,sans-serif;color:#24231f;max-width:960px;margin:40px auto;padding:0 24px}h1,h2,h3,h4{line-height:1.3;margin:1.6em 0 .6em}table{border-collapse:collapse;width:100%;font-size:13px}th,td{border:1px solid #d9d7d0;padding:10px;text-align:left;vertical-align:top}th{background:#f2f1ed}a{color:#315a9c}pre{white-space:pre-wrap;background:#f2f1ed;padding:16px}blockquote{border-left:3px solid #d9d7d0;margin-left:0;padding-left:16px}.report-meta{color:#716e67;font-size:12px;border-bottom:1px solid #d9d7d0;padding-bottom:12px}@media print{body{margin:0;max-width:none}tr,blockquote{break-inside:avoid}thead{display:table-header-group}a{color:inherit}}';
  function report(content, title) {
    var doc = document.implementation.createHTMLDocument(title);
    doc.documentElement.lang = document.documentElement.lang || 'en';
    var meta = element('meta'); meta.name = 'viewport'; meta.content = 'width=device-width,initial-scale=1'; doc.head.appendChild(meta);
    meta = element('meta'); meta.setAttribute('charset', 'utf-8'); doc.head.appendChild(meta);
    doc.head.appendChild(element('style', documentStyle));
    doc.body.appendChild(element('h1', title));
    doc.body.appendChild(element('p', 'Swift Lane AI · ' + new Date().toLocaleDateString() + ' · Verify important figures in the linked records.', 'report-meta'));
    var clone = content.cloneNode(true);
    clone.querySelectorAll('a[href]').forEach(function (a) { a.href = new URL(a.getAttribute('href'), location.href).href; });
    doc.body.appendChild(clone);
    return '<!doctype html>\n' + doc.documentElement.outerHTML;
  }
  function friendlyTools(container) {
    var tools = Array.from(container.querySelectorAll(':scope > .ai-tool'));
    if (!tools.length) return;
    var labels = { list_pages: 'Found related pages', count_records: 'Checked record counts', list_records: 'Reviewed records', suggest_page: 'Saved a page idea', system_overview: 'Checked the overview', track_shipment: 'Checked tracking', get_record: 'Read record details', remember_fact: 'Saved a fact' };
    var details = element('details', null, 'ai-response-sources');
    details.appendChild(element('summary', 'Sources checked · ' + tools.length));
    tools.forEach(function (tool) {
      var text = tool.textContent.trim(), parts = text.split(' · '), name = parts.shift();
      details.appendChild(element('div', (labels[name] || name.replace(/_/g, ' ')) + (parts.length ? ' · ' + parts.join(' · ') : '')));
      tool.remove();
    });
    container.prepend(details);
  }
  function render(target, text) {
    friendlyTools(target.parentElement);
    text = String(text || ''); target.replaceChildren(); target.classList.add('ai-response');
    var content = element('div', null, 'ai-response-content');
    if (window.marked && window.DOMPurify) {
      content.innerHTML = DOMPurify.sanitize(marked.parse(text, { gfm: true, breaks: false }), {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'del', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'a', 'hr'],
        ALLOWED_ATTR: ['href', 'title', 'scope', 'start'], ALLOW_DATA_ATTR: false, ALLOW_ARIA_ATTR: false
      });
      content.querySelectorAll('a[href]').forEach(function (a) {
        var url;
        try { url = new URL(a.getAttribute('href'), location.href); } catch (e) { a.removeAttribute('href'); return; }
        if (!/^https?:$/.test(url.protocol) || url.username || url.password) { a.removeAttribute('href'); return; }
        if (url.origin !== location.origin) { a.target = '_blank'; a.rel = 'noopener noreferrer'; }
      });
    } else { content.textContent = text; content.classList.add('ai-response-plain'); }
    target.appendChild(content);
    var actions = element('div', null, 'ai-response-actions');
    var copy = button('Copy answer', function () {
      if (!navigator.clipboard) { copy.textContent = 'Copy unavailable'; return; }
      navigator.clipboard.writeText(content.innerText).then(function () {
        copy.textContent = 'Copied'; setTimeout(function () { copy.textContent = 'Copy answer'; }, 2000);
      }).catch(function () { copy.textContent = 'Copy unavailable'; });
    });
    actions.appendChild(copy);
    var titleNode = content.querySelector('h1,h2,h3,h4');
    var title = titleNode ? titleNode.textContent : 'Swift Lane AI report';
    var filename = (title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 70) || 'swift-lane-ai-report');
    if (text.length >= 500 || content.querySelector('table') || content.querySelectorAll('li').length >= 3) {
      actions.appendChild(button('Download report (.html)', function () { download(report(content, title), 'text/html;charset=utf-8', filename + '.html'); }));
      actions.appendChild(button('Print / save PDF', function () {
        var popup = window.open('', '_blank');
        if (!popup) { if (SL.toast) SL.toast('Allow the print window to open, then try again.', true); return; }
        popup.opener = null; popup.document.write(report(content, title)); popup.document.close();
        popup.addEventListener('load', function () { popup.focus(); popup.print(); }, { once: true });
      }));
    }
    content.querySelectorAll('table').forEach(function (table, i) {
      table.querySelectorAll('th').forEach(function (th) { th.scope = 'col'; });
      var wrap = element('div', null, 'ai-response-table'); wrap.tabIndex = 0;
      wrap.setAttribute('role', 'region'); wrap.setAttribute('aria-label', 'Report table ' + (i + 1));
      table.replaceWith(wrap); wrap.appendChild(table);
      actions.appendChild(button('Download ' + (i ? 'table ' + (i + 1) : 'table') + ' (.csv)', function () { download(csv(table), 'text/csv;charset=utf-8', filename + '-table-' + (i + 1) + '.csv'); }));
    });
    target.appendChild(actions);
  }
  SL.aiResponse = { render: render };
  document.querySelectorAll('[data-ai-answer]').forEach(function (target) { render(target, target.textContent); });
})();
