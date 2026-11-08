/* Interactions for the design system's SVG, bar, heatmap and progress charts.
 * Values are supplied by PHP or existing point titles, never inferred from pixels. */
(function () {
  'use strict';
  var tip = document.createElement('div'); tip.id = 'sl-chart-tooltip'; tip.className = 'sl-chart-tooltip'; tip.hidden = true;
  (document.getElementById('sl-app') || document.body).appendChild(tip);
  var active = null, pinned = false, focusBefore = null, restoringFocus = false;
  function restoreFocus(target) { if (!target) return; restoringFocus = true; target.focus(); restoringFocus = false; }
  function el(tag, text) { var n = document.createElement(tag); if (text != null) n.textContent = text; return n; }
  function hide() {
    tip.hidden = true; pinned = false;
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
    tip.appendChild(el('strong', chart.title));
    tip.appendChild(el('div', chart.labels[index]));
    if (chart.titles) tip.appendChild(el('p', chart.titles[index]));
    else chart.series.forEach(function (s) {
      var v = s.values[index]; tip.appendChild(el('p', (s.name || 'Value') + ': ' + (v == null ? 'Not recorded' : typeof v === 'number' ? v.toLocaleString(undefined, { maximumFractionDigits: 4 }) + (s.unit || '') : v)));
    });
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
    position(chart.root);
    if (chart.live) chart.live.textContent = spoken;
  }
  function bind(root, data, points) {
    if (root.dataset.chartReady) return;
    var labels = data.labels || [], series = data.series || [];
    var count = data.titles ? data.titles.length : Math.max(0, ...series.map(function (s) { return s.values.length; }));
    if (!count) return;
    labels = Array.from({ length: count }, function (_, i) { return labels[i] == null ? 'Point ' + (i + 1) : String(labels[i]); });
    var scope = root.closest('section, .sl-card, .hb-k, .pr-k, .dv-k'), heading = scope && scope.querySelector('h2,.sl-ml,.hb-kl');
    var chart = { root: root, labels: labels, series: series, titles: data.titles, points: points || [], title: data.title || root.getAttribute('aria-label') || (heading ? heading.textContent.trim() : 'Chart'), index: 0 };
    // Existing linked metric cards keep their navigation and keyboard focus.
    var focus = root.closest('a[href]') || root; chart.focus = focus;
    if (!focus.matches('a,button')) { focus.tabIndex = 0; focus.setAttribute('role', 'group'); }
    root.removeAttribute('aria-hidden'); root.querySelectorAll('[aria-hidden="true"]').forEach(function (n) { n.removeAttribute('aria-hidden'); });
    var hidden = root.closest('[aria-hidden="true"]'); if (hidden) hidden.removeAttribute('aria-hidden');
    root.dataset.chartReady = 'true'; root.classList.add('sl-interactive-chart');
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
        var vb = root.viewBox.baseVal, x = f * vb.width + vb.x;
        var start = data.start == null ? vb.x : data.start, end = data.end == null ? vb.x + vb.width : data.end;
        f = (x - start) / Math.max(1, end - start);
      }
      return Math.round(Math.max(0, Math.min(1, f)) * (count - 1));
    }
    root.addEventListener('pointermove', function (event) { if (event.pointerType !== 'touch') show(chart, indexAt(event), false); });
    root.addEventListener('pointerleave', function () { if (!pinned && active && active.chart === chart) hide(); });
    root.addEventListener('click', function (event) {
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
    document.querySelectorAll('svg, [role="img"], .sl-bars, .hb-mini, .hb-sp, .at-sp, .lg-spark, .sl-heat, .ov-hm, .sa-hm, .wd-hm, .mn-fc, .pr-tl').forEach(function (root) {
      if (root.closest('[data-sl-chart]') || root.querySelector('[data-chart-ready]')) return;
      var points = Array.from(new Set(Array.from(root.querySelectorAll('[title], title')).map(function (p) { return p.tagName.toLowerCase() === 'title' ? p.parentElement : p; })));
      if (!points.length) return;
      points = points.filter(function (point) { return point !== root; });
      if (!points.length) return;
      var titles = points.map(function (p) { var t = p.querySelector('title'); return p.getAttribute('title') || (t && t.textContent) || ''; });
      bind(root, { titles: titles, labels: points.map(function (_, i) { return 'Point ' + (i + 1); }) }, points);
    });
    document.querySelectorAll('.sl-ticks, .ai-bar2').forEach(function (root) {
      if (root.closest('[data-sl-chart]')) return;
      var parent = root.parentElement, label = root.getAttribute('aria-label') || parent.innerText.trim();
      if (label) bind(root, { title: 'Summary', titles: [label], labels: ['Current value'] }, [root.firstElementChild || root]);
    });
  }
  document.addEventListener('pointerdown', function (event) {
    if (pinned && !tip.contains(event.target) && (!active || !active.root.contains(event.target))) hide();
  });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !tip.hidden) { var focus = active && active.chart.focus || focusBefore; hide(); restoreFocus(focus); } });
  window.addEventListener('resize', function () { if (active) position(active.root); });
  window.addEventListener('scroll', function () { if (active) position(active.root); }, true);
  (window.SL = window.SL || {}).initCharts = init;
  init();
})();
