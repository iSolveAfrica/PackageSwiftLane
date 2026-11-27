/* Developer console, observe package: live parts of dev_overview.php, dev_services.php, dev_logs.php
 * and dev_metrics.php. Reads ajax/sl/dev_observe_ajax.php (GET, read-only). Every page works without
 * this file (links and GET forms); this adds live tail, live health, range switching without reload,
 * the service drawer, the log detail panel and copy. Text from the server is always set with
 * textContent, never as HTML, except chart markup built and escaped by PHP (sl_dvxChartBody). */
(function () {
  'use strict';
  var page = document.querySelector('[data-dvx-page]');
  if (!page) return;
  var kind = page.getAttribute('data-dvx-page');
  var AJAX = page.getAttribute('data-dvx-ajax') || 'ajax/sl/dev_observe_ajax.php';
  var SL = window.SL || {};

  function toast(msg, bad) { if (SL.toast) SL.toast(msg, bad); }
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function get(params) {
    var u = new URL(AJAX, location.href);
    Object.keys(params).forEach(function (k) { if (params[k] != null && params[k] !== '') u.searchParams.set(k, params[k]); });
    return fetch(u, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
      if (r.status === 401 || r.status === 403) { var e = new Error(r.status === 401 ? 'Signed out' : 'Not allowed'); e.stop = true; throw e; }
      return r.json();
    });
  }
  function copy(text) {
    if (!navigator.clipboard) { toast('Clipboard access is unavailable in this browser.', true); return; }
    navigator.clipboard.writeText(text).then(function () { toast('Copied'); }, function () { toast('Could not copy. Select the text and copy it.', true); });
  }
  function live(state, text) {
    var b = document.querySelector('[data-dvx-live]');
    if (!b) return;
    b.classList.toggle('off', state === 'off');
    b.classList.toggle('err', state === 'err');
    var t = b.querySelector('[data-dvx-live-t]'); if (t) t.textContent = text;
  }

  /* Raw line and pretty JSON of an event (the server sends compact data; the raw line only for PHP log lines). */
  function evRaw(ev) { return ev.raw || ('[' + (ev.ts || '') + '] ' + (ev.level || '') + ' ' + (ev.source || '') + '/' + (ev.service || '') + ': ' + (ev.message || '')); }
  function evJson(ev) {
    return JSON.stringify({ id: ev.id, timestamp: ev.ts, source: ev.source, service: ev.service, level: ev.level, message: ev.message, detail: ev.detail || {} }, null, 4);
  }

  /* ---------- log line (overview tail, logs explorer, service drawer) ---------- */
  function logLine(r, opts) {
    var a = el('a', 'dvx-ln');
    a.href = r.href || '#';
    a.setAttribute('data-dvx-id', r.id);
    a.appendChild(el('span', 'ts', r.time || r.ts || ''));
    a.appendChild(el('span', 'lv ' + (r.lc || 'i'), r.level || 'INFO'));
    if (!opts || !opts.noService) {
      var sv = el('span', 'sv');
      if (opts && opts.srcDot) { var dot = el('span', 'dvx-src s-' + String(r.source || '').replace(/[^a-z]/g, '')); dot.setAttribute('aria-hidden', 'true'); sv.appendChild(dot); }
      sv.appendChild(document.createTextNode(r.service || ''));
      a.appendChild(sv);
    }
    a.appendChild(el('span', 'msg', r.message || ''));
    return a;
  }

  /* ---------- live tail (overview + logs) ---------- */
  function Tail(box, params, opts) {
    this.box = box; this.params = params; this.opts = opts || {};
    this.since = box.getAttribute('data-dvx-since') || '';
    this.max = parseInt(box.getAttribute('data-dvx-max') || '200', 10);
    this.seen = {};
    var self = this;
    box.querySelectorAll('[data-dvx-id], .dvx-ln[href]').forEach(function (n) { var id = n.getAttribute('data-dvx-id') || ''; if (id) self.seen[id] = 1; });
    this.timer = null; this.running = false; this.busy = false; this.fails = 0;
  }
  Tail.prototype.start = function () { if (this.running) return; this.running = true; this.tick(); };
  Tail.prototype.stop = function () { this.running = false; clearTimeout(this.timer); };
  Tail.prototype.next = function () {
    var self = this; clearTimeout(this.timer);
    if (this.running) this.timer = setTimeout(function () { self.tick(); }, document.hidden ? 15000 : (this.opts.every || 5000));
  };
  Tail.prototype.tick = function () {
    var self = this;
    if (this.busy) return this.next();
    this.busy = true;
    var q = Object.assign({ op: 'tail', since: this.since }, this.params);
    get(q).then(function (j) {
      self.fails = 0;
      if (!j || !j.success) throw new Error((j && j.message) || 'Live tail failed');
      var fresh = (j.rows || []).filter(function (r) { return !self.seen[r.id]; });
      if (fresh.length) {
        var empty = self.box.querySelector('[data-dvx-empty]'); if (empty) empty.remove();
        var stick = self.box.scrollTop < 8;
        for (var i = fresh.length - 1; i >= 0; i--) {
          var r = fresh[i]; self.seen[r.id] = 1;
          var line = self.opts.render ? self.opts.render(r) : logLine(r);
          line.classList.add('new');
          self.box.insertBefore(line, self.box.firstChild);
        }
        var lines = self.box.querySelectorAll('.dvx-ln'); for (var k = self.max; k < lines.length; k++) lines[k].remove();
        if (stick) self.box.scrollTop = 0;
        if (self.opts.onNew) self.opts.onNew(fresh.length);
      }
      if (j.newest && j.newest > self.since) self.since = j.newest;
      if (self.opts.onTick) self.opts.onTick(j.at, fresh.length);
    }).catch(function (e) {
      self.fails++;
      if (e.stop) { self.stop(); live('err', 'Stopped: ' + e.message); return; }
      live('err', 'Reconnecting…');
    }).finally(function () { self.busy = false; self.next(); });
  };

  /* ---------- Overview ---------- */
  function overview() {
    var box = page.querySelector('[data-dvx-tail]');
    var paused = false, tail = null;
    if (box) {
      tail = new Tail(box, { sec: 'overview' }, { every: 5000, onTick: function (at) { if (!paused) live('on', 'Live · ' + at); } });
      tail.start();
      var pb = page.querySelector('[data-dvx-pause]');
      if (pb) pb.addEventListener('click', function () {
        paused = !paused;
        pb.setAttribute('aria-pressed', String(paused));
        pb.querySelector('span').textContent = paused ? 'Resume' : 'Pause';
        if (paused) { tail.stop(); live('off', 'Paused'); } else { tail.start(); live('on', 'Live'); }
      });
    }
    // KPI values and component health every 30 s
    function pulse() {
      get({ op: 'pulse', sec: 'overview' }).then(function (j) {
        if (!j || !j.success) return;
        (j.kpis || []).forEach(function (k) {
          var card = page.querySelector('[data-dvx-kpi="' + k.k + '"]'); if (!card) return;
          var v = card.querySelector('[data-dvx-v]'); if (v && v.textContent !== k.v) { v.textContent = k.v; card.classList.remove('pop'); void card.offsetWidth; card.classList.add('pop'); }
          var u = card.querySelector('.dvx-kpi-v small'); if (u) u.textContent = k.u;
          var d = card.querySelector('.dvx-delta'); if (d && k.delta) { d.textContent = k.delta.t; d.className = 'dvx-delta ' + k.delta.c; }
          var s = card.querySelector('.dvx-kpi-s'); if (s) s.textContent = k.sub;
        });
        (j.components || []).forEach(function (c) {
          page.querySelectorAll('a[href*="svc=' + c.key + '#"]').forEach(function (a) {
            var dot = a.querySelector('.dvx-dot'); if (dot) { dot.className = 'dvx-dot ' + c.cls; dot.setAttribute('aria-label', c.st); }
            if (a.classList.contains('dvx-node')) { a.className = 'dvx-node ' + c.cls; var sm = a.querySelector('small'); if (sm) sm.textContent = c.sub; }
          });
        });
      }).catch(function () {}).finally(function () { setTimeout(pulse, document.hidden ? 60000 : 30000); });
    }
    setTimeout(pulse, 30000);
    // Range switch for the two charts without reloading the page
    page.addEventListener('click', function (e) {
      var a = e.target.closest('[data-dvx-range]');
      if (!a || e.metaKey || e.ctrlKey || e.shiftKey) return;
      e.preventDefault();
      var range = a.getAttribute('data-dvx-range');
      var bodies = page.querySelectorAll('[data-dvx-chart]');
      bodies.forEach(function (b) { b.setAttribute('aria-busy', 'true'); b.style.opacity = '.55'; });
      get({ op: 'charts', sec: 'overview', range: range }).then(function (j) {
        if (!j || !j.success) throw new Error('Could not load the chart');
        j.charts.forEach(function (c) { var b = page.querySelector('[data-dvx-chart="' + c.id + '"]'); if (b) b.innerHTML = c.html; });
        page.querySelectorAll('[data-dvx-range]').forEach(function (x) {
          var on = x.getAttribute('data-dvx-range') === range;
          x.classList.toggle('on', on); if (on) x.setAttribute('aria-current', 'true'); else x.removeAttribute('aria-current');
        });
        var u = new URL(location.href); u.searchParams.set('range', range); history.replaceState(null, '', u.pathname + u.search + '#charts');
        if (SL.initCharts) SL.initCharts();
      }).catch(function (err) { location.href = a.href; toast(err.message, true); })
        .finally(function () { bodies.forEach(function (b) { b.removeAttribute('aria-busy'); b.style.opacity = ''; }); });
    });
  }

  /* ---------- Services: filter, search, drawer ---------- */
  function services() {
    var table = page.querySelector('[data-dvx-rows]');
    var none = page.querySelector('[data-dvx-none]');
    var qIn = page.querySelector('[data-dvx-q]');
    var tab = (new URL(location.href)).searchParams.get('filter') || 'all';
    function apply() {
      var q = (qIn ? qIn.value : '').trim().toLowerCase(), shown = 0;
      table.querySelectorAll('[data-dvx-row]').forEach(function (tr) {
        var ok = (tab === 'all' || tr.getAttribute('data-grp') === tab) && (!q || tr.getAttribute('data-text').indexOf(q) !== -1);
        tr.hidden = !ok; if (ok) shown++;
      });
      if (none) none.hidden = shown > 0;
    }
    page.querySelectorAll('[data-dvx-tab]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault(); close(); tab = a.getAttribute('data-dvx-tab');
        page.querySelectorAll('[data-dvx-tab]').forEach(function (x) { x.classList.toggle('on', x === a); });
        var u = new URL(location.href); if (tab === 'all') u.searchParams.delete('filter'); else u.searchParams.set('filter', tab); u.searchParams.delete('svc');
        history.replaceState(null, '', u.pathname + u.search);
        apply();
      });
    });
    if (qIn) {
      qIn.addEventListener('input', apply);
      qIn.form.addEventListener('submit', function (e) { e.preventDefault(); apply(); });
    }
    var scrim = page.querySelector('[data-dvx-scrim]');
    var opener = null, current = null;
    function close() {
      if (!current) return;
      current.hidden = true; if (scrim) scrim.hidden = true;
      var u = new URL(location.href); u.searchParams.delete('svc'); history.replaceState(null, '', u.pathname + u.search);
      table.querySelectorAll('tr.on').forEach(function (tr) { tr.classList.remove('on'); });
      current = null;
      if (opener) opener.focus();
    }
    function open(key, from) {
      var dr = page.querySelector('[data-dvx-drawer="' + key + '"]'); if (!dr) return;
      if (current && current !== dr) current.hidden = true;
      opener = from || null; current = dr; dr.hidden = false; if (scrim) scrim.hidden = false;
      table.querySelectorAll('tr').forEach(function (tr) { tr.classList.toggle('on', tr.getAttribute('data-dvx-row') === key); });
      var u = new URL(location.href); u.searchParams.set('svc', key); history.replaceState(null, '', u.pathname + u.search);
      var x = dr.querySelector('[data-dvx-close]'); if (x) x.focus();
      var slot = dr.querySelector('[data-dvx-recent]');
      if (slot && !slot.getAttribute('data-loaded')) {
        slot.setAttribute('data-loaded', '1'); slot.textContent = 'Loading recent events…';
        get({ op: 'svc', sec: 'services', key: key }).then(function (j) {
          if (!j || !j.success) throw new Error('');
          if (!j.rows.length) { slot.textContent = j.none || 'Nothing logged in 24 h.'; return; }
          var h = el('h3', 'dvx-h3', 'Recent events · 24 h'), term = el('div', 'dvx-term sm');
          j.rows.forEach(function (r) { term.appendChild(logLine(r, { noService: true })); });
          var wrap = document.createDocumentFragment(); wrap.appendChild(h); wrap.appendChild(term);
          slot.replaceWith(wrap);
        }).catch(function () { slot.textContent = 'Recent events could not be loaded.'; });
      }
    }
    page.addEventListener('click', function (e) {
      if (e.metaKey || e.ctrlKey || e.shiftKey) return;
      var c = e.target.closest('[data-dvx-close]'); if (c) { e.preventDefault(); close(); return; }
      if (scrim && e.target === scrim) { close(); return; }
      var a = e.target.closest('[data-dvx-open]');
      var tr = !a && e.target.closest('[data-dvx-row]');
      if (tr && e.target.closest('a,button')) return;
      if (a || tr) { e.preventDefault(); open((a || tr).getAttribute(a ? 'data-dvx-open' : 'data-dvx-row'), a || tr.querySelector('[data-dvx-open]')); }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && current) close(); });
    current = page.querySelector('[data-dvx-drawer]:not([hidden])');
  }

  /* ---------- Logs explorer: detail panel, raw / JSON, copy, live tail ---------- */
  function logs() {
    var list = page.querySelector('[data-dvx-list]');
    var panel = page.querySelector('[data-dvx-detail]');
    var view = 'raw', sel = null;
    function setView(v) {
      view = v;
      panel.querySelectorAll('[data-dvx-view]').forEach(function (b) { var on = b.getAttribute('data-dvx-view') === v; b.classList.toggle('on', on); b.setAttribute('aria-selected', String(on)); });
      panel.querySelectorAll('[data-dvx-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-dvx-pane') !== v; });
    }
    function ensurePanel() {
      if (panel.querySelector('[data-dvx-pane]')) return;
      var empty = panel.querySelector('.dvx-empty'); if (empty) empty.remove();
      var dl = el('dl', 'dvx-dl'); dl.setAttribute('data-dvx-meta', '');
      [['Time', 'time', 'mono'], ['Level', 'level', ''], ['Source', 'source', ''], ['Service', 'service', '']].forEach(function (f) {
        dl.appendChild(el('dt', '', f[0])); var dd = el('dd', f[2]); dd.setAttribute('data-f', f[1]); dl.appendChild(dd);
      });
      var p = el('p', 'dvx-p'); p.setAttribute('data-f', 'message');
      var raw = el('pre', 'dvx-code wrap'); raw.setAttribute('data-dvx-pane', 'raw');
      var js = el('pre', 'dvx-code'); js.setAttribute('data-dvx-pane', 'json'); js.hidden = true;
      var row = el('div', 'row g8 wrap');
      [['raw', 'Copy raw'], ['json', 'Copy JSON']].forEach(function (b) { var x = el('button', 'dvx-btn sm', b[1]); x.type = 'button'; x.setAttribute('data-dvx-copy', b[0]); row.appendChild(x); });
      [dl, p, raw, js, row].forEach(function (n) { panel.appendChild(n); });
    }
    var evData = {};
    var evNode = page.querySelector('[data-dvx-evdata]');
    if (evNode) { try { evData = JSON.parse(evNode.textContent || '{}') || {}; } catch (e) { evData = {}; } }
    function show(a, focusPanel) {
      var ev = evData[a.getAttribute('data-dvx-id')] || null;
      if (!ev) { try { ev = JSON.parse(a.getAttribute('data-dvx-ev')); } catch (e) { return false; } }
      if (!ev) return false;
      ensurePanel();
      sel = ev;
      list.querySelectorAll('.dvx-ln.on').forEach(function (n) { n.classList.remove('on'); });
      a.classList.add('on');
      var f = function (k) { return panel.querySelector('[data-f="' + k + '"]'); };
      f('time').textContent = ev.ts || ev.time || '';
      var lv = f('level'); lv.textContent = ''; lv.appendChild(el('span', 'lv ' + (ev.level === 'ERROR' ? 'e' : ev.level === 'WARN' ? 'w' : 'i'), ev.level));
      f('source').textContent = ev.srcLabel || ev.source || ''; f('service').textContent = ev.service || ''; f('message').textContent = ev.message || '';
      panel.querySelector('[data-dvx-pane="raw"]').textContent = evRaw(ev);
      panel.querySelector('[data-dvx-pane="json"]').textContent = evJson(ev);
      var note = panel.querySelector('[data-dvx-selnote]'); if (note) note.remove();
      var t = panel.querySelector('[data-dvx-ticket]');
      if (t) {
        // the event id and its message only: the full line stays on the server (no log content in URLs)
        var u = new URL(t.href, location.href), msg = (ev.message || '');
        var lu0 = new URL(location.href); lu0.searchParams.set('row', ev.id); lu0.searchParams.set('source', ev.source || ''); lu0.searchParams.set('window', '10080');
        u.searchParams.set('title', msg.slice(0, 120)); u.searchParams.set('page', (ev.service || '').slice(0, 60));
        u.searchParams.set('log', 'Log event ' + ev.id + ' (' + (ev.srcLabel || ev.source || '') + ', ' + (ev.ts || '') + '): ' + msg.slice(0, 300) + '\nFull entry: dev_logs.php' + lu0.search);
        t.href = u.pathname + u.search;
      }
      var lu = new URL(location.href); lu.searchParams.set('row', ev.id); history.replaceState(null, '', lu.pathname + lu.search);
      setView(view);
      if (focusPanel && window.matchMedia('(max-width: 1100px)').matches) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return true;
    }
    list.addEventListener('click', function (e) {
      var a = e.target.closest('.dvx-ln[data-dvx-ev]');
      if (!a || e.metaKey || e.ctrlKey || e.shiftKey) return;
      if (show(a, true)) e.preventDefault();
    });
    list.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
      var cur = document.activeElement && document.activeElement.closest('.dvx-ln[data-dvx-ev]'); if (!cur) return;
      var nx = e.key === 'ArrowDown' ? cur.nextElementSibling : cur.previousElementSibling;
      if (nx && nx.matches('.dvx-ln[data-dvx-ev]')) { e.preventDefault(); nx.focus(); show(nx, false); }
    });
    panel.addEventListener('click', function (e) {
      var v = e.target.closest('[data-dvx-view]'); if (v) { setView(v.getAttribute('data-dvx-view')); return; }
      var c = e.target.closest('[data-dvx-copy]');
      if (c) { var pane = panel.querySelector('[data-dvx-pane="' + c.getAttribute('data-dvx-copy') + '"]'); if (pane) copy(pane.textContent); }
    });
    // live tail
    var btn = page.querySelector('[data-dvx-tailbtn]');
    var info = page.querySelector('[data-dvx-tailinfo]');
    var tail = new Tail(list, { sec: 'logs', source: list.getAttribute('data-dvx-source'), level: list.getAttribute('data-dvx-level'), q: list.getAttribute('data-dvx-q'), view: list.getAttribute('data-dvx-view') }, {
      every: 3000,
      render: function (r) {
        var a = logLine(r, { srcDot: true });
        a.setAttribute('data-dvx-ev', JSON.stringify({ id: r.id, ts: r.ts, level: r.level, source: r.source, srcLabel: r.srcLabel, service: r.service, message: r.message, detail: r.detail || {}, raw: r.raw || null }));
        return a;
      },
      onTick: function (at, n) { live('on', 'Tailing · ' + at); if (info && n) info.textContent = n + ' new at ' + at; },
    });
    function setTail(on) {
      if (on) { tail.start(); live('on', 'Tailing…'); } else { tail.stop(); live('off', 'Live tail paused'); }
      btn.setAttribute('aria-pressed', String(on));
      btn.querySelector('span').textContent = on ? 'Pause live tail' : 'Start live tail';
      var i = btn.querySelector('.ic'); if (i) i.className = 'ic ic-sm ' + (on ? 'i-clock' : 'i-play');
      try { sessionStorage.setItem('dvx.tail', on ? '1' : '0'); } catch (e) {}
    }
    if (btn) {
      btn.addEventListener('click', function () { setTail(btn.getAttribute('aria-pressed') !== 'true'); });
      var want = false; try { want = sessionStorage.getItem('dvx.tail') === '1'; } catch (e) {}
      if (want) setTail(true);
    }
  }

  if (kind === 'overview') overview();
  else if (kind === 'services') services();
  else if (kind === 'logs') logs();
})();
