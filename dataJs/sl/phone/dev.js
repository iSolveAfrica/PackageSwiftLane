/* Developer console phone screens: live refresh from the same read-only endpoint as the web pages
 * (ajax/sl/dev_observe_ajax.php, GET). Overview: KPI values and component health (op=pulse).
 * Logs: new lines at the top (op=tail). Alerts: e-mail switches save the notification form.
 * Text from the server is always set with textContent. */
(function () {
  'use strict';
  var SL = window.SL || {};
  var root = document.querySelector('[data-ph-dev]');

  function get(ajax, params) {
    var u = new URL(ajax, location.href);
    Object.keys(params).forEach(function (k) { if (params[k] != null && params[k] !== '') u.searchParams.set(k, params[k]); });
    return fetch(u, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
      if (r.status === 401 || r.status === 403 || r.status === 423) { var e = new Error('stop'); e.stop = true; throw e; }
      return r.json();
    });
  }
  function loop(fn, every) {
    var t = null;
    function run() {
      fn().then(function () { t = setTimeout(run, document.hidden ? every * 3 : every); })
        .catch(function (e) { if (!e || !e.stop) t = setTimeout(run, every * 3); });
    }
    t = setTimeout(run, every);
  }

  if (root && root.getAttribute('data-ph-dev') === 'overview') {
    var ajax = root.getAttribute('data-ph-ajax');
    var pill = { ok: 'delivered', warn: 'pending', bad: 'overdue', idle: 'transit' };
    loop(function () {
      return get(ajax, { op: 'pulse', sec: 'overview' }).then(function (j) {
        if (!j || !j.success) return;
        (j.kpis || []).forEach(function (k) {
          var b = root.querySelector('[data-ph-kpi="' + k.k + '"]');
          if (b) b.textContent = String(k.v) + (k.k === 'throughput' ? '' : (k.u || ''));
        });
        (j.components || []).forEach(function (c) {
          var p = root.querySelector('[data-ph-svc="' + c.key + '"]');
          if (p) { p.className = 'sl-pill ' + (pill[c.cls] || 'plain'); p.textContent = c.st; }
          var s = root.querySelector('[data-ph-svc-sub="' + c.key + '"]');
          if (s && c.sub) s.textContent = c.sub;
        });
      });
    }, 20000);
  }

  if (root && root.getAttribute('data-ph-dev') === 'logs') {
    var box = root.querySelector('[data-ph-tail]');
    if (box) {
      var since = box.getAttribute('data-since') || '';
      var seen = {};
      box.querySelectorAll('[data-id]').forEach(function (n) { seen[n.getAttribute('data-id')] = 1; });
      var lc = { e: 'var(--red-ink)', w: 'var(--amber-ink)', i: 'var(--green-ink)' };
      loop(function () {
        return get(root.getAttribute('data-ph-ajax'), { op: 'tail', sec: 'logs', since: since, source: box.getAttribute('data-source'), level: box.getAttribute('data-level'), q: box.getAttribute('data-q'), view: box.getAttribute('data-view') }).then(function (j) {
          if (!j || !j.success) return;
          if (j.newest) since = j.newest;
          var rows = (j.rows || []).filter(function (r) { return !seen[r.id]; });
          rows.reverse().forEach(function (r) {
            seen[r.id] = 1;
            var a = document.createElement('a');
            a.href = r.href || '#'; a.setAttribute('data-id', r.id); a.style.color = 'inherit';
            var ts = document.createElement('span'); ts.style.color = 'var(--muted)'; ts.textContent = (r.ts && r.ts.length >= 19) ? r.ts.slice(11, 19) : (r.time || '');
            var lv = document.createElement('b'); lv.style.color = lc[r.lc] || lc.i; lv.textContent = r.level || 'INFO';
            a.appendChild(ts); a.appendChild(document.createTextNode(' ')); a.appendChild(lv);
            a.appendChild(document.createTextNode(' ' + (r.service || '') + ' ' + (r.message || '')));
            box.insertBefore(a, box.firstChild);
          });
          var empty = box.querySelector('[data-ph-empty]'); if (empty && rows.length) empty.remove();
          var all = box.querySelectorAll('[data-id]');
          for (var i = 200; i < all.length; i++) all[i].remove();
        });
      }, 8000);
    }
  }

  /* Alerts: a switch with data-ph-form saves its whole settings form (fields unchanged except the switch). */
  document.addEventListener('mx:switch', function (e) {
    var sw = e.target, f = sw.getAttribute('data-ph-form') && document.getElementById(sw.getAttribute('data-ph-form'));
    if (!f || !SL.post) return;
    var on = e.detail && e.detail.on;
    sw.setAttribute('aria-busy', 'true');
    SL.post(f.getAttribute('action'), new FormData(f)).then(function (j) {
      var ok = j.success === true || j.status === 'success';
      if (!ok) {
        sw.classList.toggle('on', !on); sw.setAttribute('aria-checked', String(!on));
        var i = sw.getAttribute('data-input') && document.querySelector(sw.getAttribute('data-input')); if (i) i.value = on ? '0' : '1';
      }
      if (SL.toast) SL.toast(j.message || (ok ? 'Saved' : 'Not saved'), !ok);
    }).catch(function (err) { if (SL.toast) SL.toast(err.message || 'Network error', true); }).finally(function () { sw.removeAttribute('aria-busy'); });
  });
})();
