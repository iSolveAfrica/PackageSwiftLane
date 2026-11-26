/* Fingerprint / face sign-in (passkeys). Server: ajax/sl/passkey_ajax.php, helpers/webauthn.php.
 * Login page: [data-pk-login] button.  Signed in: #pk-manage dialog, [data-pk-add], and a one-time
 * "use your fingerprint next time?" card (#pk-offer) on phones that support it. */
(function () {
  'use strict';
  var EP = 'ajax/sl/passkey_ajax.php';
  var SL = window.SL || {};
  var supported = !!(window.PublicKeyCredential && navigator.credentials && window.isSecureContext);

  function toBuf(s) { s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '='; var b = atob(s), a = new Uint8Array(b.length); for (var i = 0; i < b.length; i++) a[i] = b.charCodeAt(i); return a.buffer; }
  function toB64u(buf) { var a = new Uint8Array(buf), s = ''; for (var i = 0; i < a.length; i++) s += String.fromCharCode(a[i]); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  function post(op, extra) { var f = new FormData(); f.append('op', op); Object.keys(extra || {}).forEach(function (k) { f.append(k, extra[k]); }); return SL.post(EP, f); }
  function toast(m, bad) { if (SL.toast) SL.toast(m, bad); else alert(m); }
  function friendly(err) {
    var n = err && err.name;
    if (n === 'NotAllowedError') return 'Fingerprint check was cancelled or timed out.';
    if (n === 'InvalidStateError') return 'This device is already set up.';
    if (n === 'SecurityError') return 'Fingerprint sign-in needs the secure (https) address of this site.';
    return (err && err.message) || 'Something went wrong. Please try again.';
  }

  function platformReady() {
    if (!supported || !PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) return Promise.resolve(false);
    return PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(function () { return false; });
  }

  /* ---- sign in ---- */
  function signIn(btn) {
    btn.classList.add('loading'); btn.disabled = true;
    var next = (document.querySelector('#login-form input[name=next]') || {}).value || '';
    post('login_options').then(function (j) {
      if (!j.success) throw new Error(j.message);
      var o = j.publicKey;
      o.challenge = toBuf(o.challenge);
      o.allowCredentials = (o.allowCredentials || []).map(function (c) { return { type: c.type, id: toBuf(c.id) }; });
      return navigator.credentials.get({ publicKey: o });
    }).then(function (cred) {
      var r = cred.response;
      return post('login_verify', { next: next, data: JSON.stringify({ id: toB64u(cred.rawId), clientDataJSON: toB64u(r.clientDataJSON), authenticatorData: toB64u(r.authenticatorData), signature: toB64u(r.signature) }) });
    }).then(function (j) {
      if (!j.success) throw new Error(j.message);
      try { localStorage.setItem('sl.pk.used', '1'); } catch (e) {}
      location.href = j.redirect || 'index.php';
    }).catch(function (e) { toast(friendly(e), true); btn.classList.remove('loading'); btn.disabled = false; });
  }

  /* ---- set up on this device ---- */
  function register(btn) {
    if (btn) { btn.classList.add('loading'); btn.disabled = true; }
    return post('reg_options').then(function (j) {
      if (!j.success) throw new Error(j.message);
      var o = j.publicKey;
      o.challenge = toBuf(o.challenge);
      o.user.id = toBuf(o.user.id);
      o.excludeCredentials = (o.excludeCredentials || []).map(function (c) { return { type: c.type, id: toBuf(c.id) }; });
      return navigator.credentials.create({ publicKey: o });
    }).then(function (cred) {
      var r = cred.response;
      return post('reg_verify', { data: JSON.stringify({ clientDataJSON: toB64u(r.clientDataJSON), attestationObject: toB64u(r.attestationObject) }) });
    }).then(function (j) {
      if (!j.success) throw new Error(j.message);
      toast(j.message);
      try { localStorage.setItem('sl.pk.offer', 'done'); } catch (e) {}
      var offer = document.getElementById('pk-offer'); if (offer) offer.hidden = true;
      loadList();
    }).catch(function (e) { toast(friendly(e), true); })
      .finally(function () { if (btn) { btn.classList.remove('loading'); btn.disabled = false; } });
  }

  /* ---- manage dialog ---- */
  function loadList() {
    var box = document.getElementById('pk-list'); if (!box) return;
    post('list').then(function (j) {
      if (!j.success) return;
      box.innerHTML = '';
      if (!j.keys.length) { box.innerHTML = '<div class="mut sm">No device is set up yet.</div>'; return; }
      j.keys.forEach(function (k) {
        var row = document.createElement('div'); row.className = 'pk-row';
        var t = document.createElement('div'); t.className = 'col grow';
        var b = document.createElement('b'); b.className = 'fw5'; b.textContent = k.label;
        var s = document.createElement('span'); s.className = 'mut sm'; s.textContent = 'Added ' + k.added + ' · last used ' + k.used;
        t.appendChild(b); t.appendChild(s);
        var x = document.createElement('button'); x.type = 'button'; x.className = 'sl-btn ghost sm'; x.textContent = 'Remove';
        x.addEventListener('click', function () { if (!confirm('Remove fingerprint sign-in for ' + k.label + '?')) return; post('delete', { id: k.id }).then(function (r) { toast(r.message, !r.success); loadList(); }); });
        row.appendChild(t); row.appendChild(x); box.appendChild(row);
      });
    });
  }

  document.addEventListener('click', function (e) {
    var l = e.target.closest('[data-pk-login]'); if (l) { e.preventDefault(); signIn(l); return; }
    var a = e.target.closest('[data-pk-add]'); if (a) { e.preventDefault(); register(a); return; }
    if (e.target.closest('[data-sl-open="pk-manage"]')) loadList();
    var no = e.target.closest('[data-pk-later]'); if (no) { try { localStorage.setItem('sl.pk.offer', 'later:' + Date.now()); } catch (er) {} document.getElementById('pk-offer').hidden = true; }
  });

  document.addEventListener('DOMContentLoaded', function () {
    platformReady().then(function (ok) {
      // login page: show the fingerprint button only where it can work
      document.querySelectorAll('[data-pk-login]').forEach(function (b) { b.hidden = !supported; });
      document.querySelectorAll('[data-pk-needs]').forEach(function (b) { b.hidden = !ok; });
      document.querySelectorAll('[data-pk-unsupported]').forEach(function (b) { b.hidden = ok; });
      // signed in: offer it once on phones / tablets without a passkey (again 14 days after "Not now")
      var offer = document.getElementById('pk-offer');
      if (!offer || !ok || offer.getAttribute('data-has') === '1') return;
      if (!matchMedia('(pointer: coarse)').matches) return;
      var v = ''; try { v = localStorage.getItem('sl.pk.offer') || ''; } catch (e) {}
      if (v === 'done' || (v.indexOf('later:') === 0 && Date.now() - parseInt(v.slice(6), 10) < 14 * 864e5)) return;
      offer.hidden = false;
    });
  });
})();
