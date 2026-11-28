/* Phone support screens (ph-desk): ticket fields, reply / note, resolve, and the settings / rule / AI switches.
 * Talks to the existing endpoints through CS.post (dataJs/sl/cs_common.js): desk_ajax.php update / send,
 * admin_ajax.php settings / rule_status. Same field names as the web pages. */
(function () {
  'use strict';
  if (!window.CS) return;
  function reload() { setTimeout(function () { location.reload(); }, 300); }
  function toast(m, bad) { if (window.SL && SL.toast) SL.toast(m, bad); }

  /* ---- ticket ---- */
  var tk = document.getElementById('pd-tk');
  if (tk) {
    var id = tk.getAttribute('data-id'), version = parseInt(tk.getAttribute('data-version'), 10) || 0, lastId = parseInt(tk.getAttribute('data-last'), 10) || 0;
    var resolveSheet = document.getElementById('pd-resolve');
    var update = function (fields) {
      return CS.post(CS.desk, 'update', Object.assign({ id: id, version: version }, fields)).then(function (j) {
        if (j.need_resolution) { if (resolveSheet) resolveSheet.hidden = false; return j; }
        toast(j.success ? 'Saved' : j.message, !j.success);
        if (j.success || j.conflict) reload();
        return j;
      }).catch(function () { toast('Network error', true); });
    };
    document.addEventListener('change', function (e) {
      var s = e.target.closest('select[data-pd-field]');
      if (!s) return;
      var f = {}; f[s.getAttribute('data-pd-field')] = s.value;
      update(f);
    });
    document.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-pd-field]');
      if (!b) return;
      var f = {}; f[b.getAttribute('data-pd-field')] = b.getAttribute('data-value');
      if (b.getAttribute('data-value') === 'open') f.quick = 1;
      update(f);
    });
    var rf = document.getElementById('pd-resolve-form');
    if (rf) rf.addEventListener('submit', function (e) {
      e.preventDefault();
      update({ status: 'resolved', resolution_code: rf.resolution_code.value, resolution_summary: rf.resolution_summary.value });
    });
    var body = document.getElementById('pd-body');
    var send = function (mode, force) {
      var text = body.value.trim();
      if (!text) { toast('Write something first', true); body.focus(); return; }
      var after = document.getElementById('pd-after');
      var data = { id: id, mode: mode, body: text, last_seen_id: lastId, client_msg_id: 'p' + Date.now(), set_status: mode === 'reply' && after ? after.value : '', ai_assisted: 0 };
      if (force) data.force = 1;
      CS.post(CS.desk, 'send', data).then(function (j) {
        if (!j.success && j.collision) { if (confirm(j.message + '\n\nSend anyway?')) send(mode, true); return; }
        toast(j.success ? (mode === 'note' ? 'Note added' : 'Reply sent') : j.message, !j.success);
        if (j.success) reload();
      }).catch(function () { toast('Network error', true); });
    };
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-pd-send]');
      if (b && body) send(b.getAttribute('data-pd-send'));
      var r = e.target.closest('[data-pd-reply]');
      if (r && body) { e.preventDefault(); body.scrollIntoView({ block: 'center', behavior: 'smooth' }); body.focus(); }
    });
  }

  /* ---- search box filtering a list on the page: input[data-pd-filter] hides [data-pd-item] that do not match ---- */
  document.addEventListener('input', function (e) {
    var q = e.target.closest('[data-pd-filter]');
    if (!q) return;
    var v = q.value.trim().toLowerCase();
    document.querySelectorAll('[data-pd-item]').forEach(function (it) { it.hidden = v !== '' && it.textContent.toLowerCase().indexOf(v) === -1; });
  });

  /* ---- switches (mx.js flips them and fires mx:switch) ---- */
  function settings(data, sw, on) {
    CS.post(CS.admin, 'settings', data).then(function (j) {
      toast(j.success ? 'Saved' : j.message, !j.success);
      if (j.success) reload(); else if (sw) { sw.classList.toggle('on', !on); sw.setAttribute('aria-checked', String(!on)); }
    }).catch(function () { toast('Network error', true); });
  }
  document.addEventListener('mx:switch', function (e) {
    var sw = e.target, on = e.detail.on;
    if (sw.hasAttribute('data-pd-rule')) {
      CS.post(CS.admin, 'rule_status', { id: sw.getAttribute('data-pd-rule'), status: on ? 'published' : 'paused' }).then(function (j) {
        toast(j.success ? (on ? 'Rule on' : 'Rule paused') : j.message, !j.success);
        if (j.success) reload(); else { sw.classList.toggle('on', !on); sw.setAttribute('aria-checked', String(!on)); }
      });
    } else if (sw.hasAttribute('data-pd-notify')) {
      var list = [];
      document.querySelectorAll('[data-pd-notify]').forEach(function (x) { if (x.classList.contains('on')) list.push(x.getAttribute('data-pd-notify')); });
      settings({ notify_channels: list.join(',') }, sw, on);
    } else if (sw.hasAttribute('data-pd-ai')) {
      var key = sw.getAttribute('data-pd-ai');
      if (key === 'agent') settings({ king_mode: on ? 'customer' : 'copilot' }, sw, on);
      else settings(on ? { copilot_enabled: 1, king_mode: sw.getAttribute('data-mode') || 'copilot' } : { copilot_enabled: 0 }, sw, on);
    }
  });
})();
