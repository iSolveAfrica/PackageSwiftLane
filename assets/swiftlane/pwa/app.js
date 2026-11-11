(async function () {
  'use strict';
  const meta = document.querySelector('meta[name="swiftlane-pwa"]');
  if (!meta) return;
  const role = meta.content, root = new URL(meta.dataset.root, location.origin), scope = new URL(meta.dataset.scope, location.origin);
  const inside = meta.dataset.context === 'app';
  const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  const phone = matchMedia('(max-width:767px), (max-height:500px) and (max-width:950px) and (pointer:coarse)').matches;
  const name = role === 'rider' ? 'Swift Lane Rider' : 'Swift Lane';
  const home = role === 'rider' ? 'driver_app.php' : 'index.php';
  const entry = new URL('app.php/' + home, scope);
  async function supportsAppRoutes() {
    try { const response = await fetch(new URL('app.php/pwa-check.php', scope), { cache: 'no-store' }); const result = await response.json(); return response.ok && result.routing === true && result.role === role; }
    catch (e) { return false; }
  }
  // Signed-in phone browsers enter the correct app, preserving the current page/query.
  // Auth pages have no mobile role until login; print documents remain outside it.
  if (!inside && phone && document.body.dataset.mobileRole === role && !/^print_|^download/.test(location.pathname.split('/').pop())) {
    const page = location.pathname.slice(root.pathname.length);
    if (/^[a-z0-9_-]+\.php$/i.test(page) && await supportsAppRoutes()) {
      location.replace(new URL('app.php/' + (role === 'rider' && page === 'index.php' ? home : page) + location.search + location.hash, scope));
      return;
    }
  }
  if (inside) {
    document.body.dataset.pwaApp = role;
    document.body.classList.toggle('sl-installed', standalone);
  }
  let promptEvent, registration, askedToUpdate = false;
  const installButtons = () => document.querySelectorAll('[data-pwa-install]');
  function refreshInstall() {
    installButtons().forEach(button => { button.hidden = standalone; button.textContent = inside ? 'Install ' + name : 'Open ' + name + ' app'; });
  }
  refreshInstall();
  addEventListener('beforeinstallprompt', event => { if (!inside) return; event.preventDefault(); promptEvent = event; refreshInstall(); });
  addEventListener('appinstalled', () => { promptEvent = null; installButtons().forEach(button => { button.hidden = true; }); });
  document.addEventListener('click', async event => {
    const install = event.target.closest('[data-pwa-install]');
    if (install) {
      if (!inside) { if (await supportsAppRoutes()) location.href = entry.href; else if (window.SL) SL.toast('The installed app is not available on this server yet. You can keep using the website.', true); return; }
      if (promptEvent) {
        const pending = promptEvent; promptEvent = null;
        await pending.prompt(); await pending.userChoice; return;
      }
      showInstallInstructions();
    }
    const update = event.target.closest('[data-pwa-update]');
    if (update && registration && registration.waiting) {
      if (document.querySelector('[aria-busy="true"]')) { window.SL && SL.toast('Finish the current action before updating.'); return; }
      // An explicit update click protects unsent forms; never reload while the user is working.
      if (!window.confirm('Update the app now? Save or finish any form first.')) return;
      askedToUpdate = true; registration.waiting.postMessage({ type: 'APPLY_UPDATE' });
    }
  });
  function showInstallInstructions() {
    const existing = document.getElementById('pwa-install-dialog');
    if (existing) { existing.hidden = false; document.body.style.overflow = 'hidden'; return; }
    const wrapper = document.createElement('div'); wrapper.className = 'sl-dialog'; wrapper.id = 'pwa-install-dialog';
    const panel = document.createElement('section'); panel.className = 'sl-card sl-modal'; panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-modal', 'true'); panel.setAttribute('aria-labelledby', 'pwa-install-title');
    const title = document.createElement('h2'); title.id = 'pwa-install-title'; title.className = 'sl-ct'; title.textContent = 'Keep ' + name + ' on your home screen';
    const text = document.createElement('p');
    const ios = /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    text.textContent = ios ? 'Open this page in Safari. Tap Share, choose Add to Home Screen, then tap Add. This installs the ' + (role === 'rider' ? 'rider' : 'customer') + ' app.' : 'Open your browser menu and choose Install app or Add to Home screen. Installation requires HTTPS and a supported browser.';
    const close = document.createElement('button'); close.type = 'button'; close.className = 'sl-btn'; close.textContent = 'Got it'; close.dataset.slClose = 'pwa-install-dialog';
    close.addEventListener('click', () => { wrapper.hidden = true; });
    document.body.style.overflow = 'hidden';
    panel.append(title, text, close); wrapper.append(panel); wrapper.hidden = true; document.body.append(wrapper); wrapper.hidden = false;
    const previous = document.activeElement;
    requestAnimationFrame(() => close.focus());
    // Shared runtime handles Escape; restore focus for either dismissal path.
    new MutationObserver(() => { if (wrapper.hidden) { document.body.style.overflow = ''; if (previous && previous.isConnected) previous.focus(); } }).observe(wrapper, { attributes: true, attributeFilter: ['hidden'] });
  }
  if (inside && 'serviceWorker' in navigator && isSecureContext) {
    navigator.serviceWorker.register(new URL('sw.js', scope), { scope: scope.pathname, updateViaCache: 'none' }).then(reg => {
      registration = reg;
      function updateReady() { document.querySelectorAll('[data-pwa-update]').forEach(button => { button.hidden = !reg.waiting; }); }
      updateReady();
      reg.addEventListener('updatefound', () => {
        const installing = reg.installing;
        if (installing) installing.addEventListener('statechange', () => { if (installing.state === 'installed') updateReady(); });
      });
      navigator.serviceWorker.addEventListener('controllerchange', () => { if (askedToUpdate) location.reload(); });
      // Existing registrations update on app launch. No polling while the rider is using a form.
    }).catch(() => { /* The online app remains usable if the browser blocks installation. */ });
  }
  if (inside) {
    // Same-origin apps share the site's session. Revalidate history restores after account changes.
    addEventListener('pagehide', () => {
      document.querySelectorAll('input[type="password"],input[name="code"],input[type="file"]').forEach(input => { input.value = ''; });
      const cover = document.createElement('div'); cover.className = 'sl-session-cover'; cover.textContent = 'Opening ' + name + '…'; cover.id = 'pwa-session-cover'; document.body.append(cover);
    });
    addEventListener('pageshow', event => { if (event.persisted) location.reload(); });
    document.querySelectorAll('[data-pwa-back]').forEach(button => button.addEventListener('click', () => {
      const referrer = document.referrer;
      if (referrer && new URL(referrer).origin === location.origin && new URL(referrer).pathname.startsWith(scope.pathname)) history.back();
      else location.href = entry.href;
    }));
  }
})();
