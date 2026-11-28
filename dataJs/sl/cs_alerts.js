/* Customer care alerts on every staff page (views/sl/inc/cs_alerts.php): a pop-up at the side with a chime when a
   customer chat is waiting for a person, and when a customer writes on a chat you have taken.
   Source: ajax/support/desk_ajax.php?action=alerts. Polls every 12 s (60 s while the tab is hidden). */
(function () {
  var CFG = window.SL_CSA || {}; if (!CFG.url) return;
  var box = document.getElementById('csa'); if (!box) return;
  var list = box.querySelector('[data-csa-list]');
  var ss = { get: function (k) { try { return sessionStorage.getItem('csa.' + k); } catch (e) { return null; } }, set: function (k, v) { try { sessionStorage.setItem('csa.' + k, v); } catch (e) {} } };
  var ls = { get: function (k) { try { return localStorage.getItem('csa.' + k); } catch (e) { return null; } }, set: function (k, v) { try { localStorage.setItem('csa.' + k, v); } catch (e) {} } };
  var seen = {}; try { seen = JSON.parse(ss.get('seen') || '{}') || {}; } catch (e) { seen = {}; }
  var after = ss.get('after'), timer = null, here = (location.pathname.indexOf('support_ticket.php') !== -1 && (location.search.match(/[?&]id=(\d+)/) || [])[1]) || '';
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /* ---------- sound: a two-note chime made in the browser (no file to load) ---------- */
  var ac = null;
  function unlock() { if (ac) { if (ac.state === 'suspended') ac.resume(); return; } try { ac = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { ac = null; } }
  ['pointerdown', 'keydown'].forEach(function (ev) { document.addEventListener(ev, unlock, { once: false, passive: true }); });
  function muted() { return ls.get('mute') === '1'; }
  function chime(urgent) {
    if (muted()) return;
    unlock(); if (!ac || ac.state !== 'running') return;
    var t = ac.currentTime, notes = urgent ? [880, 1175, 880, 1175] : [880, 1320];
    notes.forEach(function (f, i) {
      var o = ac.createOscillator(), g = ac.createGain(), at = t + i * 0.16;
      o.type = 'sine'; o.frequency.value = f; o.connect(g); g.connect(ac.destination);
      g.gain.setValueAtTime(0.0001, at); g.gain.exponentialRampToValueAtTime(0.28, at + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, at + 0.42);
      o.start(at); o.stop(at + 0.45);
    });
  }

  /* ---------- the pop-ups ---------- */
  var baseTitle = document.title, flash = null;
  function titleFlash(n) {
    clearInterval(flash); if (!n) { document.title = baseTitle; return; }
    var on = false; flash = setInterval(function () { on = !on; document.title = on ? '(' + n + ') New chat · ' + baseTitle : baseTitle; }, 1200);
  }
  function count() { var n = list.children.length; box.hidden = !n; box.querySelector('[data-csa-n]').textContent = n > 1 ? n + ' waiting' : ''; if (!n) titleFlash(0); }
  function remove(card) { card.classList.add('out'); setTimeout(function () { card.remove(); count(); }, 180); }
  function card(it, kind) {
    var el = document.createElement('div'); el.className = 'csa-card ' + kind; el.setAttribute('data-key', it.key); el.setAttribute('role', 'alert');
    var href = CFG.ticket + it.ticket;
    el.innerHTML = '<span class="csa-ic" aria-hidden="true"><i class="ic ' + (kind === 'queue' ? 'i-user' : 'i-message') + '"></i></span>'
      + '<div class="csa-b"><small>' + (kind === 'queue' ? 'New chat waiting for a person' : 'New message on your chat') + (it.ref ? ' · ' + esc(it.ref) : '') + '</small>'
      + '<b>' + esc(it.name) + '</b>' + (it.text ? '<p>“' + esc(it.text) + '”</p>' : '')
      + '<div class="csa-acts">' + (kind === 'queue' && CFG.canAccept ? '<button type="button" class="csa-go" data-csa-take>Take chat</button><a class="csa-alt" href="' + esc(href) + '">View</a>' : '<a class="csa-go" href="' + esc(href) + '">Reply</a>')
      + '<button type="button" class="csa-alt" data-csa-later>Later</button></div></div>'
      + '<button type="button" class="csa-x" aria-label="Dismiss" data-csa-later><i class="ic ic-sm i-x" aria-hidden="true"></i></button>';
    el.querySelectorAll('[data-csa-later]').forEach(function (b) { b.addEventListener('click', function () { remove(el); }); });
    var take = el.querySelector('[data-csa-take]');
    if (take) take.addEventListener('click', function () {
      take.disabled = true; take.textContent = 'Taking…';
      SL.post(CFG.url, { action: 'accept', id: it.ticket }).then(function (r) {
        if (r && r.ok) location.href = href; else { SL.toast((r && r.error) || 'Someone else may have taken it.', true); remove(el); }
      }).catch(function () { location.href = href; });
    });
    return el;
  }
  function show(items, kind) {
    var fresh = 0;
    items.forEach(function (it) {
      if (seen[it.key] || String(it.ticket) === here) return;
      seen[it.key] = Date.now(); fresh++;
      list.insertBefore(card(it, kind), list.firstChild);
      if (document.hidden && window.Notification && Notification.permission === 'granted') {
        try { var n = new Notification(kind === 'queue' ? 'New chat: ' + it.name : it.name + ' wrote', { body: it.text || '', tag: it.key }); n.onclick = function () { window.focus(); location.href = CFG.ticket + it.ticket; }; } catch (e) {}
      }
    });
    while (list.children.length > 4) list.lastChild.remove();
    if (fresh) { chime(kind === 'queue'); count(); if (document.hidden || !document.hasFocus()) titleFlash(list.children.length); }
    return fresh;
  }
  function prune() { var now = Date.now(); Object.keys(seen).forEach(function (k) { if (now - seen[k] > 864e5) delete seen[k]; }); ss.set('seen', JSON.stringify(seen)); }

  /* ---------- polling ---------- */
  function poll() {
    clearTimeout(timer);
    var params = { action: 'alerts' }; if (after !== null) params.after = after;
    SL.get(CFG.url, params).then(function (r) {
      if (!r || !r.ok) return;
      show(r.queue || [], 'queue'); show(r.mine || [], 'mine');
      after = String(Math.max(Number(after || 0), Number(r.last_id || 0))); ss.set('after', after); prune();
    }).catch(function () {}).then(function () { timer = setTimeout(poll, document.hidden ? 60000 : 12000); });
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { titleFlash(0); poll(); } });
  window.addEventListener('focus', function () { titleFlash(0); });

  /* ---------- header: mute, desktop alerts ---------- */
  var mute = box.querySelector('[data-csa-mute]');
  function muteUi() { mute.setAttribute('aria-pressed', muted() ? 'true' : 'false'); mute.title = muted() ? 'Sound off: tap to turn on' : 'Sound on: tap to mute'; mute.querySelector('span').textContent = muted() ? 'Sound off' : 'Sound on'; }
  mute.addEventListener('click', function () { ls.set('mute', muted() ? '0' : '1'); muteUi(); if (!muted()) chime(false); });
  muteUi();
  var desk = box.querySelector('[data-csa-desk]');
  if (desk) {
    desk.hidden = !window.Notification || Notification.permission !== 'default';
    desk.addEventListener('click', function () { Notification.requestPermission().then(function () { desk.hidden = true; }); });
  }
  setTimeout(poll, 1500);
})();
