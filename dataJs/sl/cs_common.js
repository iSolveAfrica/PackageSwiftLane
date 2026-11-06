/* Customer service pages: talk to ajax/support/*.php (which answer {ok, error}) through SL.post. */
(function () {
  'use strict';
  var CS = window.CS = window.CS || {};
  CS.desk = 'ajax/support/desk_ajax.php';
  CS.admin = 'ajax/support/admin_ajax.php';
  /** POST to a support endpoint; resolves with {success, message, ...payload}. */
  CS.post = function (url, action, data) {
    var d;
    if (data instanceof FormData) { d = data; d.set('action', action); } else d = Object.assign({ action: action }, data || {});
    return SL.post(url, d).then(function (j) {
      j.success = j.ok === true || j.success === true;
      if (!j.success && !j.message) j.message = j.error || 'Something went wrong';
      return j;
    });
  };
  CS.get = function (url, params) {
    return SL.get(url, params).then(function (j) { j.success = j.ok === true; if (!j.success && !j.message) j.message = j.error || 'Could not load'; return j; });
  };
  /** Forms with data-cs-form="action" post to the desk (or data-cs-url) and reload / redirect. */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches('form[data-cs-form]')) return;
    e.preventDefault();
    var btn = f.querySelector('[type="submit"]');
    if (btn) { btn.classList.add('loading'); btn.disabled = true; }
    CS.post(f.getAttribute('data-cs-url') || CS.desk, f.getAttribute('data-cs-form'), new FormData(f)).then(function (j) {
      SL.toast(j.message || (j.success ? 'Saved' : 'Failed'), !j.success);
      f.dispatchEvent(new CustomEvent('cs:done', { detail: j }));
      if (j.success && f.hasAttribute('data-cs-redirect')) location.href = f.getAttribute('data-cs-redirect').replace('{id}', j.id || j.saved_id || '');
      else if (j.success && f.hasAttribute('data-cs-reload')) setTimeout(function () { location.reload(); }, 300);
    }).catch(function () { SL.toast('Network error', true); }).finally(function () { if (btn) { btn.classList.remove('loading'); btn.disabled = false; } });
  });
  /** One-click actions: data-cs-action="send" data-p-id="5" [data-cs-url] [data-sl-confirm] [data-cs-reload]. */
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-cs-action]');
    if (!a) return;
    e.preventDefault();
    var msg = a.getAttribute('data-sl-confirm');
    if (msg && !confirm(msg)) return;
    var data = {};
    Array.prototype.forEach.call(a.attributes, function (at) { if (at.name.indexOf('data-p-') === 0) data[at.name.slice(7)] = at.value; });
    var prompt = a.getAttribute('data-cs-prompt');
    if (prompt) { var v = window.prompt(prompt, a.getAttribute('data-cs-default') || ''); if (v === null) return; data[a.getAttribute('data-cs-prompt-key') || 'value'] = v; }
    a.classList.add('loading');
    CS.post(a.getAttribute('data-cs-url') || CS.desk, a.getAttribute('data-cs-action'), data).then(function (j) {
      SL.toast(j.message || (j.success ? 'Done' : 'Failed'), !j.success);
      a.dispatchEvent(new CustomEvent('cs:done', { detail: j, bubbles: true }));
      if (j.success && a.hasAttribute('data-cs-reload')) setTimeout(function () { location.reload(); }, 300);
      if (j.success && a.hasAttribute('data-cs-redirect')) location.href = a.getAttribute('data-cs-redirect').replace('{id}', j.id || j.target_id || '');
    }).catch(function () { SL.toast('Network error', true); }).finally(function () { a.classList.remove('loading'); });
  });
  /** Customer search box for the new-ticket dialog: input[data-cs-custq] + hidden[data-cs-custid] + list. */
  var timer;
  document.addEventListener('input', function (e) {
    var q = e.target.closest('[data-cs-custq]');
    if (!q) return;
    var list = document.getElementById(q.getAttribute('data-cs-custq'));
    clearTimeout(timer);
    timer = setTimeout(function () {
      if (q.value.trim().length < 2) { list.innerHTML = ''; return; }
      CS.get(CS.desk, { action: 'customers', q: q.value.trim() }).then(function (j) {
        list.innerHTML = '';
        (j.customers || []).forEach(function (c) {
          var b = document.createElement('button'); b.type = 'button'; b.className = 'cs-pick';
          b.innerHTML = '<span class="sl-av sm"></span><span class="col"><b class="fw5 sm"></b><span class="mut" style="font-size:12px"></span></span>';
          b.querySelector('.sl-av').textContent = (c.name || '?').split(' ').map(function (p) { return p[0]; }).join('').slice(0, 2).toUpperCase();
          b.querySelector('b').textContent = c.name; b.querySelector('.mut').textContent = (c.email || '') + (c.locker ? ' · ' + c.locker : '');
          b.addEventListener('click', function () { document.querySelector(q.getAttribute('data-cs-target')).value = c.id; q.value = c.name; list.innerHTML = ''; });
          list.appendChild(b);
        });
      });
    }, 250);
  });
})();
