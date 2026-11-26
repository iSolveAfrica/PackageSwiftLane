/* Security Centre (security_center.php, views/sl/pages/SecurityCenter.php).
 * - "Devices and log-ins": search, filter chips, first 25 rows + "Show all".
 * - Person pop-up (#sc-user): loads ajax/sl/security_ajax.php?op=user_detail&id=N (GET, JSON) and
 *   draws devices, passkeys and sign-ins with text nodes only. Actions inside it are ordinary
 *   data-sl-action buttons (swiftlane.js) sent with from=detail: the pop-up refreshes in place and
 *   the page reloads when it is closed, so the list shows the new counts.
 * - Deep links: #sc-d-<check> opens that check's pop-up, #user-<id> opens a person.
 * Needs swiftlane.js (window.SL). */
(function () {
  'use strict';
  var SL = window.SL || {};

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined && text !== null) e.textContent = String(text);
    return e;
  }
  function icon(name, sm) { var i = el('i', 'ic ' + (sm === false ? '' : 'ic-sm ') + name); i.setAttribute('aria-hidden', 'true'); return i; }
  function pill(text, cls) { return el('span', 'sl-pill ' + (cls || 'plain'), text); }
  function add(parent) { for (var i = 1; i < arguments.length; i++) if (arguments[i]) parent.appendChild(arguments[i]); return parent; }
  function setHash(h) { try { history.replaceState(null, '', h ? '#' + h : location.pathname + location.search); } catch (e) {} }
  function closeOpenDialogs(except) { document.querySelectorAll('.sl-dialog:not([hidden])').forEach(function (d) { if (d !== except) d.hidden = true; }); }

  /* ---------- staff list: search, chips, show more ---------- */
  var people = document.getElementById('sc-devices');
  if (people) {
    var items = Array.prototype.slice.call(people.querySelectorAll('[data-sc-li]'));
    var q = people.querySelector('[data-sc-q]'), more = people.querySelector('[data-sc-more]');
    var shown = people.querySelector('[data-sc-shown]'), empty = people.querySelector('[data-sc-empty]');
    var size = parseInt(people.getAttribute('data-page-size'), 10) || 25, showAll = false, filter = 'all';
    var apply = function () {
      var text = ((q && q.value) || '').trim().toLowerCase(), match = 0, visible = 0;
      items.forEach(function (li) {
        var ok = (filter === 'all' || (' ' + (li.getAttribute('data-tags') || '') + ' ').indexOf(' ' + filter + ' ') >= 0) &&
          (!text || (li.getAttribute('data-q') || '').indexOf(text) >= 0);
        if (ok) match++;
        var vis = ok && (showAll || match <= size);
        li.hidden = !vis;
        if (vis) visible++;
      });
      if (more) { more.hidden = visible >= match; more.textContent = 'Show all ' + match; }
      if (shown) shown.textContent = match > size || filter !== 'all' || text ? 'Showing ' + visible + ' of ' + match + (filter !== 'all' || text ? ' matching' : '') + ' · ' + items.length + ' staff in all' : '';
      if (empty) empty.hidden = match > 0 || !items.length;
    };
    people.addEventListener('click', function (e) {
      var b = e.target.closest('[data-sc-f]');
      if (b) {
        filter = b.getAttribute('data-sc-f');
        people.querySelectorAll('[data-sc-f]').forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', String(x === b)); });
        apply();
      }
      if (e.target.closest('[data-sc-more]')) { showAll = true; apply(); }
    });
    if (q) q.addEventListener('input', apply);
    apply();
  }

  /* ---------- KPI "Open security items": show only the checks to look at ---------- */
  document.addEventListener('click', function (e) {
    var k = e.target.closest('[data-sc-filter]');
    if (!k) return;
    var b = document.querySelector('#sc-checks [data-sl-filter] [data-f="' + k.getAttribute('data-sc-filter') + '"]');
    if (b) b.click();
  });

  /* ---------- person pop-up ---------- */
  var dlg = document.getElementById('sc-user');
  var EP = dlg ? dlg.getAttribute('data-ep') : 'ajax/sl/security_ajax.php';
  var current = 0, dirty = false, seq = 0;

  function stat(value, label, bad) { var d = el('div', bad ? 'bad' : ''); add(d, el('b', '', value), el('span', '', label)); return d; }
  function actBtn(label, iconName, op, id, extra, confirmText) {
    var b = el('button', 'sl-btn ghost sm');
    b.type = 'button';
    b.setAttribute('data-sl-action', EP);
    b.setAttribute('data-p-op', op);
    b.setAttribute('data-p-id', String(id));
    b.setAttribute('data-p-from', 'detail');
    Object.keys(extra || {}).forEach(function (k) { b.setAttribute('data-p-' + k, String(extra[k])); });
    if (confirmText) b.setAttribute('data-sl-confirm', confirmText);
    add(b, icon(iconName));
    b.appendChild(document.createTextNode(label));
    return b;
  }
  function linkBtn(label, iconName, href) { var a = el('a', 'sl-btn ghost sm'); a.href = href; add(a, icon(iconName)); a.appendChild(document.createTextNode(label)); return a; }
  function section(title, count, extra) {
    var s = el('section', 'sc-sec'), h = el('div', 'row g8 wrap');
    add(h, el('h3', 'up', title + (count !== null && count !== undefined ? ' · ' + count : '')), extra || null);
    s.appendChild(h);
    return s;
  }

  function render(j) {
    var u = j.user, can = j.can || {}, body = dlg.querySelector('[data-u-body]');
    dlg.querySelector('[data-u-ini]').textContent = u.ini;
    dlg.querySelector('[data-u-name]').textContent = u.name;
    dlg.querySelector('[data-u-sub]').textContent = [u.role, u.username, u.email].filter(Boolean).join(' · ');
    var pills = dlg.querySelector('[data-u-pills]');
    pills.textContent = '';
    if (u.super) pills.appendChild(pill('Super admin', 'sorting'));
    if (u.fr) pills.appendChild(pill('Must reset password', 'pending'));
    else if (u.pwOld) pills.appendChild(pill('Password over 180 days', 'onway'));
    if (u.f30 >= 5) pills.appendChild(pill(u.f30 + ' failed sign-ins in 30 days', 'onway'));

    body.textContent = '';
    var st = el('div', 'sc-ust');
    add(st, stat(j.devices.length, j.devices.length === 1 ? 'Remembered device' : 'Remembered devices'),
      stat(j.passkeys ? j.passkeys.length : '—', 'Passkeys'),
      stat(u.f30, 'Failed sign-ins · 30 d', u.f30 >= 5),
      stat(u.pw, 'Password changed', u.pwOld));
    body.appendChild(st);

    var last = el('p', 'sm', null);
    last.style.margin = '0';
    var when = u.lastAt ? u.lastAt + (/ago|just now/.test(u.last) ? ' (' + u.last + ')' : '') : u.last;
    add(last, el('span', 'mut', 'Last sign-in: '), document.createTextNode(when + (u.lastIp ? ' from ' + u.lastIp : '')));
    body.appendChild(last);

    var acts = el('div', 'sc-acts');
    if (can.act) {
      if (j.devices.length) acts.appendChild(actBtn('Forget all devices', 'i-trash', 'revoke_devices', u.id, null, 'Forget all ' + j.devices.length + ' remembered devices of ' + u.name + '? A code is asked at their next sign-in on each of them.'));
      if (u.frAvailable) acts.appendChild(u.fr ? actBtn('Clear password reset', 'i-x', 'clear_reset', u.id) : actBtn('Force password reset', 'i-lock', 'force_reset', u.id, null, 'Ask ' + u.name + ' to choose a new password at the next sign-in?'));
    }
    // Links only to pages the viewer may open (the server leaves them empty otherwise).
    add(acts, u.edit ? linkBtn('Open account', 'i-user', u.edit) : null, u.logs ? linkBtn('All activity', 'i-activity', u.logs) : null);
    body.appendChild(acts);
    if (can.why) { var why = el('div', 'sl-banner info'); add(why, icon('i-info'), el('span', '', can.why)); body.appendChild(why); }

    // remembered devices
    var ds = section('Remembered devices', j.devices.length);
    if (!j.devices.length) ds.appendChild(el('div', 'sc-emp', 'No remembered devices. When one-time codes are on, a code is asked at every sign-in.'));
    else {
      var dl = el('div', 'sc-dl');
      j.devices.forEach(function (d) {
        var row = el('div', 'dev'), tile = el('span', 'sl-tile');
        tile.appendChild(icon(d.icon));
        var main = el('div', 'col grow sc-min'), head = el('span', 'row g6 wrap');
        add(head, el('b', 'fw5', d.label), d.current ? pill('This browser', 'transit') : null, d.guess ? el('span', 'mut sm', d.ipGuess ? '(browser and address from the sign-in at that time)' : '(browser from the sign-in at that time)') : null);
        var meta = [d.ip ? (d.ipGuess ? 'sign-in address ' : 'IP ') + d.ip : '', 'remembered ' + d.first, 'last used ' + d.used, 'expires ' + d.expires].filter(Boolean).join(' · ');
        add(main, head, el('span', 'mut sm', meta));
        add(row, tile, main, can.act ? actBtn('Forget', 'i-x', 'revoke_device', u.id, { device: d.id }, 'Forget ' + d.label + (d.ip ? ' (' + d.ip + ')' : '') + '? A code is asked at the next sign-in from it.') : null);
        dl.appendChild(row);
      });
      ds.appendChild(dl);
    }
    body.appendChild(ds);

    // passkeys
    if (j.passkeys) {
      var ps = section('Passkeys (fingerprint or face sign-in)', j.passkeys.length);
      if (!j.passkeys.length) ps.appendChild(el('div', 'sc-emp', 'No passkeys registered. People add one from their profile menu: Fingerprint sign-in.'));
      else {
        var pl = el('div', 'sc-dl');
        j.passkeys.forEach(function (p) {
          var row = el('div'), tile = el('span', 'sl-tile');
          tile.appendChild(icon('i-key'));
          var main = el('div', 'col grow sc-min');
          add(main, el('b', 'fw5', p.label), el('span', 'mut sm', 'added ' + p.added + ' · last used ' + p.used));
          add(row, tile, main);
          pl.appendChild(row);
        });
        ps.appendChild(pl);
      }
      body.appendChild(ps);
    }

    // sign-ins
    var ls = section('Last sign-ins', j.logins.length, u.signins ? (function () { var a = el('a', 'sc-a sm', 'All sign-ins in Activity logs'); a.href = u.signins; return a; })() : null);
    if (!j.logins.length) ls.appendChild(el('div', 'sc-emp', 'No sign-ins logged for this account.'));
    else {
      var ll = el('div', 'sc-dl');
      j.logins.forEach(function (r) {
        var row = el('div'), main = el('div', 'col grow sc-min'), head = el('span', 'row g6 wrap');
        add(head, el('b', 'fw5 ' + (r.ok ? 'ok' : 'bad'), r.what));
        add(main, head, el('span', 'mut sm', [r.dev, r.ip].filter(Boolean).join(' · ')));
        var when = el('span', 'mut sm nw', r.ago);
        when.title = r.at;
        add(row, el('span', 'sc-dot' + (r.ok ? '' : ' bad')), main, when);
        ll.appendChild(row);
      });
      ls.appendChild(ll);
    }
    body.appendChild(ls);
  }

  function failed(msg) {
    var body = dlg.querySelector('[data-u-body]'), box = el('div', 'sl-banner bad'), retry = el('button', 'sl-btn ghost sm', 'Try again');
    retry.type = 'button';
    retry.addEventListener('click', function () { load(current); });
    body.textContent = '';
    add(box, icon('i-alert'), el('span', 'grow', msg), retry);
    body.appendChild(box);
  }

  function load(id, quiet) {
    var mine = ++seq, body = dlg.querySelector('[data-u-body]');
    if (!quiet) {
      body.textContent = '';
      var sk = el('div', 'sc-load'); add(sk, el('i'), el('i'), el('i'));
      body.appendChild(sk);
    }
    body.setAttribute('aria-busy', 'true');
    var url = new URL(EP, location.href);
    url.searchParams.set('op', 'user_detail');
    url.searchParams.set('id', String(id));
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.text().then(function (t) { var j; try { j = JSON.parse(t); } catch (e) { j = { success: false, message: r.redirected ? 'Your session expired. Sign in again.' : 'The server sent an unexpected answer.' }; } return j; }); })
      .then(function (j) {
        if (mine !== seq) return;
        if (j && j.success && j.user) render(j); else failed((j && j.message) || 'Could not load this person.');
      })
      .catch(function () { if (mine === seq) failed('Network error. Check the connection and try again.'); })
      .finally(function () { if (mine === seq) body.removeAttribute('aria-busy'); });
  }

  function openUser(id) {
    if (!dlg || !id) return;
    closeOpenDialogs(dlg);
    current = id;
    dlg.querySelector('[data-u-ini]').textContent = '…';
    dlg.querySelector('[data-u-name]').textContent = 'Loading…';
    dlg.querySelector('[data-u-sub]').textContent = '';
    dlg.querySelector('[data-u-pills]').textContent = '';
    dlg.hidden = false;
    setHash('user-' + id);
    load(id);
  }

  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-sc-user]');
    if (!t) return;
    e.preventDefault();
    openUser(parseInt(t.getAttribute('data-sc-user'), 10));
  });

  if (dlg) {
    dlg.addEventListener('sl:done', function (e) {
      var j = e.detail || {};
      if (j.success === true || j.status === 'success') { dirty = true; load(current, true); }
    });
    new MutationObserver(function () {
      if (!dlg.hidden) return;
      seq++;
      if (location.hash === '#user-' + current) setHash('');
      if (dirty) { dirty = false; location.reload(); }
    }).observe(dlg, { attributes: true, attributeFilter: ['hidden'] });
  }

  /* ---------- check pop-ups: remember the open one in the address (survives the reload after an action) ---------- */
  document.querySelectorAll('.sl-dialog[id^="sc-d-"]').forEach(function (d) {
    new MutationObserver(function () {
      if (!d.hidden) setHash(d.id);
      else if (location.hash === '#' + d.id) setHash('');
    }).observe(d, { attributes: true, attributeFilter: ['hidden'] });
  });

  /* ---------- deep links: on load and whenever the address hash changes on this page ---------- */
  function followHash() {
    var m = /^#user-(\d+)$/.exec(location.hash);
    if (m) {
      var id = parseInt(m[1], 10);
      if (!dlg || dlg.hidden || id !== current) openUser(id);
    } else if (/^#sc-d-[a-z0-9_-]+$/i.test(location.hash)) {
      var target = document.getElementById(location.hash.slice(1));
      if (target && target.classList.contains('sl-dialog') && target.hidden) { closeOpenDialogs(target); target.hidden = false; }
    }
  }
  followHash();
  window.addEventListener('hashchange', followHash);
  if (SL && !SL.securityCenter) SL.securityCenter = { openUser: openUser };
})();
