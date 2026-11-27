/*
 * Developer console, deploy pages (DevDeployments, DevIntegrations, DevIssues).
 *   - filter forms that submit on change, clickable table rows
 *   - database dump upload with a progress bar (ajax/sl/dev_db_upload_ajax.php op=stage)
 *   - import form: the button unlocks only when the typed confirmation matches
 *   - backup download / delete / move behind a password dialog; a download gets a single-use link
 *     from the server and the browser saves the file straight to disk (never held in memory)
 *   - copy-to-clipboard for the cron line
 * Every POST carries the CSRF token (window.SL.post, or the meta tag for XHR uploads).
 */
(function () {
  'use strict';
  var SL = window.SL || {};
  function toast(m, bad) { if (SL.toast) SL.toast(m, bad); }
  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]'), p = document.querySelector('meta[name="csrf-param"]');
    return { token: m ? m.content : '', param: p ? p.content : 'csrf_token' };
  }
  function bytes(n) {
    var u = ['B', 'KB', 'MB', 'GB'], i = 0;
    while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
    return (i ? n.toFixed(1) : n) + ' ' + u[i];
  }

  /* filters: submit on change */
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.matches('form[data-dp-autosubmit] select') || t.matches('[data-dp-submit]')) {
      var f = t.form;
      if (f) { if (f.requestSubmit) f.requestSubmit(); else f.submit(); }
    }
  });

  /* clickable rows (links and buttons inside keep their own behaviour) */
  document.addEventListener('click', function (e) {
    var tr = e.target.closest('tr.dp-click[data-href]');
    if (!tr || e.target.closest('a,button,input,select,label,summary')) return;
    location.href = tr.getAttribute('data-href');
  });

  /* copy blocks */
  document.querySelectorAll('[data-dp-copy]').forEach(function (el) {
    el.setAttribute('tabindex', '0');
    el.setAttribute('title', 'Click to copy');
    el.addEventListener('click', function () {
      var txt = el.textContent.trim();
      if (navigator.clipboard) navigator.clipboard.writeText(txt).then(function () { toast('Copied'); }, function () {});
    });
  });

  /* ---------------- upload a dump (XHR for progress) ---------------- */
  var up = document.querySelector('form[data-dp-upload]');
  if (up) {
    var file = up.querySelector('input[type=file]'), label = up.querySelector('[data-dp-filename]');
    var prog = up.querySelector('[data-dp-upprog]'), bar = prog ? prog.querySelector('span') : null, msg = up.querySelector('[data-dp-upmsg]');
    var max = parseInt(file.getAttribute('data-max') || '0', 10);
    file.addEventListener('change', function () {
      var f = file.files[0];
      up.querySelectorAll('.sl-err').forEach(function (x) { x.remove(); });
      if (msg) msg.textContent = '';
      if (!f) return;
      var bad = !/\.sql(\.gz)?$/i.test(f.name) ? 'Only .sql and .sql.gz files' : (max && f.size > max ? 'Larger than the limit of ' + bytes(max) : '');
      label.textContent = f.name + ' · ' + bytes(f.size) + (bad ? ' · ' + bad : '');
      label.classList.toggle('dp-warn', !!bad);
    });
    up.addEventListener('submit', function (e) {
      e.preventDefault();
      var f = file.files[0];
      if (msg) msg.textContent = '';
      if (!f) { toast('Choose a .sql or .sql.gz file', true); return; }
      if (!/\.sql(\.gz)?$/i.test(f.name)) { toast('Only .sql and .sql.gz files are accepted', true); return; }
      if (max && f.size > max) { toast('The file is larger than ' + bytes(max), true); return; }
      var btn = up.querySelector('[type=submit]'), c = csrf(), fd = new FormData(up);
      fd.append(c.param, c.token);
      btn.disabled = true; btn.classList.add('loading');
      if (prog) { prog.hidden = false; bar.style.width = '0%'; }
      var xhr = new XMLHttpRequest();
      xhr.open('POST', up.getAttribute('action'));
      xhr.setRequestHeader('X-CSRF-Token', c.token);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.upload.onprogress = function (ev) {
        if (!ev.lengthComputable || !prog) return;
        var p = Math.round(100 * ev.loaded / ev.total);
        bar.style.width = p + '%'; prog.setAttribute('aria-valuenow', p);
        if (msg) msg.textContent = p < 100 ? 'Uploading ' + p + '%' : 'Checking the dump against the database…';
      };
      xhr.onload = function () {
        var j; try { j = JSON.parse(xhr.responseText); } catch (er) { j = { success: false, message: xhr.status === 413 ? 'The file is larger than the web server allows' : 'Unexpected answer from the server (' + xhr.status + ')' }; }
        var ok = xhr.status < 400 && j.success === true;
        toast(j.message || (ok ? 'Uploaded' : 'Upload failed'), !ok);
        if (msg) msg.textContent = ok ? 'Checked. Loading the plan…' : (j.message || '');
        if (ok) { setTimeout(function () { location.reload(); }, 400); return; }
        btn.disabled = false; btn.classList.remove('loading'); if (prog) prog.hidden = true;
      };
      xhr.onerror = function () { toast('Network error during upload', true); btn.disabled = false; btn.classList.remove('loading'); if (prog) prog.hidden = true; };
      xhr.send(fd);
    });
  }

  /* ---------------- apply: typed confirmation ---------------- */
  var ap = document.querySelector('form[data-dp-apply]');
  if (ap) {
    var conf = ap.querySelector('[data-dp-confirm]'), go = ap.querySelector('[data-dp-apply-btn]'), pr = ap.querySelector('[data-dp-progress]');
    var check = function () { go.disabled = conf.value.trim() !== conf.getAttribute('data-dp-confirm') || !ap.querySelector('[name=password]').value; };
    ap.addEventListener('input', check);
    check();
    ap.addEventListener('submit', function () { if (pr) pr.hidden = false; }, true);
    ap.addEventListener('sl:done', function (e) {
      if (pr) pr.hidden = true;
      var j = e.detail || {};
      if (j.success === true || j.reload) setTimeout(function () { location.reload(); }, 900);
      check();
    });
  }

  /* ---------------- backups: password dialog ---------------- */
  var dlg = document.getElementById('dp-pw');
  if (dlg) {
    var pf = dlg.querySelector('form[data-dp-pwform]');
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-dp-pw]');
      if (!b) return;
      e.preventDefault();
      var op = b.getAttribute('data-dp-pw');
      pf.elements.op.value = op;
      pf.elements.file.value = b.getAttribute('data-file');
      pf.elements.password.value = '';
      dlg.querySelector('[data-dp-pwtitle]').textContent = op === 'download' ? 'Download this backup' : (op === 'move_legacy' ? 'Move backups out of the web root' : 'Delete this backup');
      dlg.querySelector('[data-dp-pwfile]').textContent = b.getAttribute('data-label') || b.getAttribute('data-file');
      var sb = pf.querySelector('[type=submit]');
      sb.textContent = op === 'download' ? 'Download' : (op === 'move_legacy' ? 'Move' : 'Delete');
      sb.classList.toggle('dp-danger', op === 'delete_backup');
      pf.querySelectorAll('.sl-err').forEach(function (x) { x.remove(); });
      dlg.hidden = false;
    });
    pf.addEventListener('submit', function (e) {
      e.preventDefault();
      var c = csrf(), fd = new FormData(pf), sb = pf.querySelector('[type=submit]');
      fd.append(c.param, c.token);
      sb.disabled = true;
      pf.querySelectorAll('.sl-err').forEach(function (x) { x.remove(); });
      fetch(pf.getAttribute('action'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': c.token, 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) {
          return r.text().then(function (t) {
            var j; try { j = JSON.parse(t); } catch (er) { j = { success: false, message: 'Unexpected answer (' + r.status + ')' }; }
            var ok = r.ok && j.success === true;
            if (!ok && j.errors && j.errors.password) {
              var s = document.createElement('span'); s.className = 'sl-err'; s.textContent = j.errors.password;
              pf.elements.password.closest('.sl-field').appendChild(s); pf.elements.password.focus();
            }
            if (ok && j.url && /^ajax\/sl\/dev_db_upload_ajax\.php\?op=fetch&t=[0-9a-f]{48}$/.test(j.url)) {
              // single-use link: the browser streams the file to disk
              var a = document.createElement('a');
              a.href = j.url; a.setAttribute('download', ''); document.body.appendChild(a); a.click();
              setTimeout(function () { a.remove(); }, 1000);
              dlg.hidden = true; toast('Download started');
              return;
            }
            toast(j.message || (ok ? 'Done' : 'Failed'), !ok);
            if (ok) { dlg.hidden = true; if (j.reload) setTimeout(function () { location.reload(); }, 300); }
          });
        })
        .catch(function () { toast('Network error', true); })
        .finally(function () { sb.disabled = false; pf.elements.password.value = ''; });
    });
  }
})();
