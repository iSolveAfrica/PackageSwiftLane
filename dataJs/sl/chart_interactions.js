/* Interactions for the design system's SVG, bar, heatmap and progress charts.
 * Values are supplied by PHP or existing point titles, never inferred from pixels. */
(function () {
  'use strict';
  var tip = document.createElement('div'); tip.id = 'sl-chart-tooltip'; tip.className = 'sl-chart-tooltip'; tip.hidden = true;
  (document.getElementById('sl-app') || document.body).appendChild(tip);
  var guide = document.createElement('div'); guide.className = 'sl-chart-guide'; guide.hidden = true; document.body.appendChild(guide);
  var active = null, pinned = false, focusBefore = null, restoringFocus = false;
  function restoreFocus(target) { if (!target) return; restoringFocus = true; target.focus(); restoringFocus = false; }
  function el(tag, text) { var n = document.createElement(tag); if (text != null) n.textContent = text; return n; }
  function hide() {
    tip.hidden = true; guide.hidden = true; pinned = false;
    if (active) active.root.querySelectorAll('[data-chart-selected]').forEach(function (n) { n.removeAttribute('data-chart-selected'); });
    active = null;
  }
  function position(box) {
    var r = box.getBoundingClientRect(), w = Math.min(340, innerWidth - 24);
    tip.style.width = w + 'px';
    tip.style.left = Math.max(12, Math.min(innerWidth - w - 12, r.left + r.width / 2 - w / 2)) + 'px';
    var h = tip.getBoundingClientRect().height;
    tip.style.top = Math.max(12, r.top > h + 20 ? r.top - h - 10 : Math.min(innerHeight - h - 12, r.bottom + 10)) + 'px';
  }
  function positionGuide(chart, index) {
    if (chart.kind === 'donut' || chart.labels.length < 2 || chart.points.length) { guide.hidden = true; return; }
    var r = chart.root.getBoundingClientRect(), x = r.left + r.width * index / (chart.labels.length - 1);
    if (chart.root instanceof SVGElement && chart.root.viewBox.baseVal.width) {
      var vb = chart.root.viewBox.baseVal, start = chart.start == null ? vb.x : chart.start, end = chart.end == null ? vb.x + vb.width : chart.end;
      x = r.left + r.width * (start + (end - start) * index / (chart.labels.length - 1) - vb.x) / vb.width;
      var matrix = chart.root.getScreenCTM();
      if (matrix) { var point = chart.root.createSVGPoint(); point.x = start + (end - start) * index / (chart.labels.length - 1); point.y = vb.y; x = point.matrixTransform(matrix).x; }
    }
    guide.hidden = false; guide.style.left = Math.round(x) + 'px'; guide.style.top = Math.max(0, r.top) + 'px';
    guide.style.height = Math.max(0, Math.min(innerHeight, r.bottom) - Math.max(0, r.top)) + 'px';
  }
  function saveCsv(chart) {
    var rows = [['Category'].concat(chart.series.map(function (s) { return s.name || 'Value'; }))];
    chart.labels.forEach(function (label, i) { rows.push([label].concat(chart.series.map(function (s) { return s.values[i] == null ? '' : s.values[i]; }))); });
    if (chart.titles) rows = [['Point', 'Details']].concat(chart.titles.map(function (s, i) { return [i + 1, s]; }));
    var csv = '\uFEFF' + rows.map(function (row) { return row.map(function (value) {
      var t = String(value); if (/^[\s]*[=+@-]/.test(t)) t = "'" + t;
      return '"' + t.replace(/"/g, '""') + '"';
    }).join(','); }).join('\r\n');
    var url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })), a = el('a');
    a.href = url; a.download = 'chart-data.csv'; a.click(); setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
  }
  function show(chart, index, pin) {
    if (pinned && (!pin || (active && active.chart !== chart))) return;
    index = Math.max(0, Math.min(chart.labels.length - 1, index)); if (index < 0 || !chart.labels.length) return;
    active = { chart: chart, root: chart.root, index: index }; chart.index = index;
    pinned = !!pin; tip.replaceChildren(); tip.hidden = false; tip.setAttribute('role', pinned ? 'dialog' : 'tooltip');
    tip.setAttribute('aria-label', chart.title + ' values');
    var heading = el('div', chart.title); heading.className = 'sl-chart-tip-heading'; tip.appendChild(heading);
    var label = chart.labels[index], details = chart.titles && chart.titles[index];
    if (details && /^Point \d+$/.test(label)) {
      var parts = details.match(/^([^:]{1,70}):\s*(.+)$/);
      if (parts) { label = parts[1]; details = parts[2]; }
      else { label = details; details = ''; }
    }
    var period = el('div', label); period.className = 'sl-chart-tip-period'; tip.appendChild(period);
    if (chart.titles && details) { var note = el('p', details); note.className = 'sl-chart-tip-note'; tip.appendChild(note); }
    if (!chart.titles) {
      var values = el('div'); values.className = 'sl-chart-tip-values';
      chart.series.forEach(function (s, i) {
        var v = s.values[index], row = el('div'); row.className = 'sl-chart-tip-value';
        var name = el('span', (s.name || 'Value') + ': '); name.className = 'sl-chart-tip-name';
        var dot = el('i'); dot.className = 'sl-chart-tip-dot'; dot.style.background = s.color || ['#656473', '#d8793d', '#397fba', '#0e9a70', '#8865b2'][i % 5]; name.prepend(dot);
        row.appendChild(name); row.appendChild(el('b', v == null ? 'Not recorded' : typeof v === 'number' ? v.toLocaleString(undefined, { maximumFractionDigits: 4 }) + (s.unit || '') : v)); values.appendChild(row);
      });
      tip.appendChild(values);
    }
    chart.root.querySelectorAll('[data-chart-selected]').forEach(function (n) { n.removeAttribute('data-chart-selected'); });
    if (chart.points[index]) chart.points[index].setAttribute('data-chart-selected', 'true');
    var spoken = tip.textContent;
    chart.focus.setAttribute('aria-label', chart.title + '. ' + spoken + '. Use left and right arrows for values; Space to keep details.');
    if (pinned) {
      var actions = el('div'); actions.className = 'sl-chart-tip-actions';
      var download = el('button', 'Download CSV'); download.type = 'button'; download.addEventListener('click', function () { saveCsv(chart); }); actions.appendChild(download);
      var link = chart.root.closest('a[href]');
      if (link) { var open = el('a', 'Open related view'); open.href = link.href; actions.appendChild(open); }
      var close = el('button', 'Close'); close.type = 'button'; close.addEventListener('click', function () { hide(); restoreFocus(chart.focus); }); actions.appendChild(close);
      tip.appendChild(actions);
    }
    position(chart.root); positionGuide(chart, index);
    if (chart.live) chart.live.textContent = spoken;
  }
  function bind(root, data, points) {
    if (root.dataset.chartReady) return;
    var labels = data.labels || [], series = data.series || [];
    var count = data.titles ? data.titles.length : Math.max(0, ...series.map(function (s) { return s.values.length; }));
    if (!count) return;
    labels = Array.from({ length: count }, function (_, i) { return labels[i] == null ? 'Point ' + (i + 1) : String(labels[i]); });
    var scope = root.closest('section, .sl-card, .hb-k, .pr-k, .dv-k'), heading = scope && scope.querySelector('h2,.sl-ml,.hb-kl');
    var chart = { root: root, labels: labels, series: series, titles: data.titles, points: points || [], title: data.title || root.getAttribute('aria-label') || (heading ? heading.textContent.trim() : 'Chart'), index: 0, start: data.start, end: data.end, kind: data.kind };
    // Existing linked metric cards keep their navigation and keyboard focus.
    var focus = root.closest('a[href]') || root; chart.focus = focus;
    if (!focus.matches('a,button')) { focus.tabIndex = 0; focus.setAttribute('role', 'group'); }
    root.removeAttribute('aria-hidden'); root.querySelectorAll('[aria-hidden="true"]').forEach(function (n) { n.removeAttribute('aria-hidden'); });
    var hidden = root.closest('[aria-hidden="true"]'); if (hidden) hidden.removeAttribute('aria-hidden');
    root.dataset.chartReady = 'true'; root.classList.add('sl-interactive-chart');
    if (root instanceof SVGElement && root.viewBox && root.viewBox.baseVal.width) {
      var hit = document.createElementNS('http://www.w3.org/2000/svg', 'rect'), bounds = root.viewBox.baseVal;
      hit.setAttribute('x', bounds.x); hit.setAttribute('y', bounds.y); hit.setAttribute('width', bounds.width); hit.setAttribute('height', bounds.height);
      hit.setAttribute('fill', 'transparent'); hit.setAttribute('pointer-events', 'all'); hit.setAttribute('aria-hidden', 'true'); hit.classList.add('sl-chart-hit-area');
      root.insertBefore(hit, root.firstChild);
      root.style.pointerEvents = 'all';
    }
    focus.setAttribute('aria-label', chart.title + '. Interactive chart: use left and right arrows to inspect values.');
    var live = el('span'); live.className = 'sl-chart-sr'; live.setAttribute('role', 'status'); live.setAttribute('aria-live', 'polite');
    root.insertAdjacentElement('afterend', live); chart.live = live;
    if (chart.points.length !== count) chart.points = [];
    function indexAt(event) {
      if (data.kind === 'donut') {
        var box = root.getBoundingClientRect(), angle = (Math.atan2(event.clientX - box.left - box.width / 2, -(event.clientY - box.top - box.height / 2)) + Math.PI * 2) % (Math.PI * 2);
        var values = series[0].values, total = values.reduce(function (a, b) { return a + Number(b || 0); }, 0), sum = 0;
        for (var j = 0; j < values.length; j++) { sum += Number(values[j] || 0); if (angle / (Math.PI * 2) < sum / total) return j; }
        return 0;
      }
      if (chart.points.length) {
        var nearest = 0, distance = Infinity;
        chart.points.forEach(function (p, i) { var b = p.getBoundingClientRect(), dx = event.clientX - (b.left + b.width / 2), dy = event.clientY - (b.top + b.height / 2), d = dx * dx + dy * dy; if (d < distance) { nearest = i; distance = d; } });
        return nearest;
      }
      var r = root.getBoundingClientRect(), f = (event.clientX - r.left) / Math.max(1, r.width);
      if (root instanceof SVGElement && root.viewBox.baseVal.width) {
        var vb = root.viewBox.baseVal, x = f * vb.width + vb.x, matrix = root.getScreenCTM();
        if (matrix) { var point = root.createSVGPoint(); point.x = event.clientX; point.y = event.clientY; x = point.matrixTransform(matrix.inverse()).x; }
        var start = data.start == null ? vb.x : data.start, end = data.end == null ? vb.x + vb.width : data.end;
        f = (x - start) / Math.max(1, end - start);
      }
      return Math.round(Math.max(0, Math.min(1, f)) * (count - 1));
    }
    root.addEventListener('pointerenter', function (event) { if (event.pointerType !== 'touch') show(chart, indexAt(event), false); });
    root.addEventListener('pointermove', function (event) { if (event.pointerType !== 'touch') show(chart, indexAt(event), false); });
    root.addEventListener('pointerleave', function () { if (!pinned && active && active.chart === chart) hide(); });
    root.addEventListener('click', function (event) {
      if (root === focus && root.matches('a[href]')) return;
      if (event.target.closest('button,a') && !root.closest('a[href]')) return;
      event.preventDefault(); event.stopPropagation(); show(chart, indexAt(event), true);
    });
    focus.addEventListener('focus', function () { if (!restoringFocus) { if (active && active.chart !== chart) hide(); show(chart, chart.index, false); } });
    focus.addEventListener('blur', function () { if (!pinned && active && active.chart === chart) hide(); });
    focus.addEventListener('keydown', function (event) {
      var i = chart.index;
      if (event.key === 'ArrowRight') i++;
      else if (event.key === 'ArrowLeft') i--;
      else if (event.key === 'Home') i = 0;
      else if (event.key === 'End') i = count - 1;
      else if (event.key === ' ' || (event.key === 'Enter' && focus === root && !focus.matches('a'))) {
        event.preventDefault(); show(chart, i, true); focusBefore = focus; tip.querySelector('button').focus(); return;
      } else return;
      event.preventDefault(); show(chart, i, pinned);
    });
  }
  function init() {
    document.querySelectorAll('[data-sl-chart]').forEach(function (root) {
      try { bind(root, JSON.parse(root.getAttribute('data-sl-chart')), root.matches('svg') ? [] : Array.from(root.children)); } catch (e) { /* malformed chart data leaves its original display intact */ }
    });
    // Existing point titles already contain exact dates/counts/currency. Make
    // them available to touch and keyboard users without changing their data.
    document.querySelectorAll('svg, [role="img"], .sl-bars, .hb-mini, .hb-sp, .at-sp, .lg-spark, .sl-heat, .ov-hm, .sa-hm, .wd-hm, .mn-fc, .pr-tl, .gbars, .da-bars, .ops-bar').forEach(function (root) {
      if (root.closest('[data-sl-chart]') || root.querySelector('[data-chart-ready]')) return;
      var points = Array.from(new Set(Array.from(root.querySelectorAll('[title], title')).map(function (p) { return p.tagName.toLowerCase() === 'title' ? p.parentElement : p; })));
      if (!points.length) return;
      points = points.filter(function (point) { return point !== root; });
      if (!points.length) return;
      var titles = points.map(function (p) { var t = p.querySelector('title'); return p.getAttribute('title') || (t && t.textContent) || ''; });
      bind(root, { titles: titles, labels: points.map(function (_, i) { return 'Point ' + (i + 1); }) }, points);
    });
    document.querySelectorAll('.sl-ticks, .ai-bar2, .fr-bar, .sa-bar, .cd-bar, .pk-seg, .cx-bar, .sf-bar, .cf-b').forEach(function (root) {
      if (root.closest('[data-sl-chart]') || root.dataset.chartReady) return;
      var parent = root.parentElement, label = root.getAttribute('aria-label') || root.innerText.trim() || parent.innerText.trim().replace(/\s+/g, ' ').slice(0, 180);
      var section = root.closest('section, .sl-card, .ov-card'), heading = section && section.querySelector('h2,.sl-ct,.hd,.sl-ml');
      if (label) bind(root, { title: heading ? heading.textContent.trim() : 'Progress', titles: [label], labels: ['Current value'] }, [root.firstElementChild || root]);
    });
  }
  document.addEventListener('pointerdown', function (event) {
    if (pinned && !tip.contains(event.target) && (!active || !active.root.contains(event.target))) hide();
  });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !tip.hidden) { var focus = active && active.chart.focus || focusBefore; hide(); restoreFocus(focus); } });
  window.addEventListener('resize', function () { if (active) { position(active.root); positionGuide(active.chart, active.index); } });
  window.addEventListener('scroll', function () { if (active) { position(active.root); positionGuide(active.chart, active.index); } }, true);
  (window.SL = window.SL || {}).initCharts = init;
  init();
})();
