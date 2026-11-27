(async function () {
  'use strict';
  const meta = document.querySelector('meta[name="swiftlane-pwa"]');
  if (!meta) return;
  const role = meta.content, root = new URL(meta.dataset.root, location.origin), scope = new URL(meta.dataset.scope, location.origin);
  const inside = meta.dataset.context === 'app';
  const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  let framed = false; try { framed = window.top !== window.self; } catch (e) { framed = true; }
  // inside the PC app window (views/sl/inc/app_frame.php) the page is phone-width but on a computer: no app redirect, no install screen
  const phone = !framed && matchMedia('(max-width:767px), (max-height:500px) and (max-width:950px) and (pointer:coarse)').matches;
  const name = meta.dataset.name || (role === 'rider' ? 'Swift Lane Rider' : (role === 'staff' ? 'Swift Lane Staff' : 'Swift Lane'));
  const force = meta.dataset.force === '1';
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
  addEventListener('beforeinstallprompt', event => { if (!inside) return; event.preventDefault(); promptEvent = event; refreshInstall(); gateReady(); });
  addEventListener('appinstalled', () => { promptEvent = null; remember('installed', '1'); installButtons().forEach(button => { button.hidden = true; }); gateInstalled(); });

  /* ---------- install screen on phones ----------
   * A phone browser that is not running the installed app gets a full-screen "install the app" screen
   * (Settings > Modules > Install the phone app; off = a slim banner instead). Android / Chrome / Edge /
   * Samsung use the browser's own install prompt; iPhone shows the Share > Add to Home Screen steps.
   * A browser cannot be forced to install, so "Continue in the browser" appears after a few seconds and
   * holds the screen back for 6 hours; the screen comes back until the app is installed. */
  function remember(k, v) { try { localStorage.setItem('slpwa.' + role + '.' + k, v); } catch (e) {} }
  function recall(k) { try { return localStorage.getItem('slpwa.' + role + '.' + k); } catch (e) { return null; } }
  if (standalone) remember('installed', '1');
  const ua = navigator.userAgent;
  const isIOS = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const iosSafari = isIOS && /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS|OPiOS|GSA|FBAN|FBAV|Instagram|Line\//.test(ua);
  const inAppBrowser = /FBAN|FBAV|Instagram|Line\/|WhatsApp|Snapchat|TikTok/.test(ua);
  let gate = null;
  function el(tag, cls, text) { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function shareIcon() { return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M8 7l4-4 4 4"/><path d="M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1"/></svg>'; }
  function plusIcon() { return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M12 8v8M8 12h8"/></svg>'; }
  function dotsIcon() { return '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>'; }
  function steps(list) {
    const ol = el('ol', 'pwa-gate-steps');
    list.forEach((s, i) => { const li = el('li'); li.innerHTML = '<span class="pwa-gate-n">' + (i + 1) + '</span><span class="pwa-gate-st">' + s + '</span>'; ol.append(li); });
    return ol;
  }
  function gateReady() {
    if (!gate) return;
    const b = gate.querySelector('[data-gate-install]'); if (b) { b.hidden = false; b.disabled = false; b.querySelector('span').textContent = 'Install the app'; }
    const help = gate.querySelector('[data-gate-help]'); if (help) help.hidden = true;
  }
  function gateInstalled() {
    if (!gate) return;
    gate.querySelector('.pwa-gate-body').innerHTML = '<div class="pwa-gate-done"><span class="pwa-gate-tick" aria-hidden="true">✓</span><h2>Installed</h2><p>Open ' + name.replace(/</g, '&lt;') + ' from your home screen. You can close this browser tab.</p></div>';
    setTimeout(closeGate, 4000);
  }
  function closeGate() { if (!gate) return; gate.remove(); gate = null; document.documentElement.classList.remove('pwa-gated'); }
  function openGate() {
    if (gate || document.getElementById('pwa-gate')) return;
    gate = el('div', 'pwa-gate'); gate.id = 'pwa-gate'; gate.setAttribute('role', 'dialog'); gate.setAttribute('aria-modal', 'true'); gate.setAttribute('aria-labelledby', 'pwa-gate-title');
    const body = el('div', 'pwa-gate-body');
    const icon = el('img', 'pwa-gate-icon'); icon.src = meta.dataset.icon || ''; icon.alt = ''; icon.width = 88; icon.height = 88;
    const eb = el('span', 'pwa-gate-eb', 'Get the app');
    const h = el('h2', null, 'Install ' + name); h.id = 'pwa-gate-title';
    const p = el('p', 'pwa-gate-lede', role === 'rider' ? 'Your stops, navigation and handovers open full screen, straight from your home screen.' : (role === 'staff' ? 'Every screen of the system, full screen on your phone: shipments, warehouse, consolidations, customers and reports.' : 'Track parcels, see pickup codes, pay bills and chat with us, full screen from your home screen.'));
    const perks = el('ul', 'pwa-gate-perks');
    [['Opens in one tap', 'like any app on your phone'], ['Full screen', 'no browser bars, more room for your work'], ['Stays signed in', 'same account as the website']].forEach(x => { const li = el('li'); li.innerHTML = '<b>' + x[0] + '</b><span>' + x[1] + '</span>'; perks.append(li); });
    body.append(icon, eb, h, p, perks);
    if (isIOS && !iosSafari) {
      body.append(el('p', 'pwa-gate-note', inAppBrowser ? 'This page is open inside another app. Open it in Safari to install.' : 'On iPhone the app installs from Safari.'));
      const copy = el('button', 'pwa-gate-btn'); copy.type = 'button'; copy.innerHTML = '<span>Copy link to open in Safari</span>';
      copy.addEventListener('click', async () => { try { await navigator.clipboard.writeText(location.href); copy.querySelector('span').textContent = 'Link copied: paste it in Safari'; } catch (e) { copy.querySelector('span').textContent = location.href; } });
      body.append(copy, steps(['Open <b>Safari</b> and paste the link', 'Tap Share ' + shareIcon(), 'Choose <b>Add to Home Screen</b> ' + plusIcon()]));
    } else if (isIOS) {
      body.append(steps(['Tap Share ' + shareIcon() + ' in the bar below', 'Scroll and choose <b>Add to Home Screen</b> ' + plusIcon(), 'Tap <b>Add</b>. The app appears on your home screen']));
      body.append(el('div', 'pwa-gate-arrow'));
    } else {
      const b = el('button', 'pwa-gate-btn'); b.type = 'button'; b.dataset.gateInstall = '1'; b.innerHTML = '<span>' + (promptEvent ? 'Install the app' : 'Preparing install…') + '</span>'; b.disabled = !promptEvent;
      b.addEventListener('click', async () => { if (!promptEvent) return; const pending = promptEvent; promptEvent = null; await pending.prompt(); const r = await pending.userChoice; if (r && r.outcome === 'accepted') { remember('installed', '1'); gateInstalled(); } else { b.querySelector('span').textContent = 'Install the app'; } });
      const help = el('div', 'pwa-gate-help'); help.dataset.gateHelp = '1'; help.hidden = !!promptEvent;
      help.append(el('p', 'pwa-gate-note', inAppBrowser ? 'This page is open inside another app. Open it in Chrome to install.' : 'Install it from your browser menu:'));
      help.append(steps(['Tap the menu ' + dotsIcon() + ' at the top', 'Choose <b>Install app</b> or <b>Add to Home screen</b>', 'Confirm <b>Install</b>']));
      body.append(b, help);
      setTimeout(() => { if (!promptEvent && help) { help.hidden = false; b.hidden = true; } }, 2500);
    }
    const later = el('button', 'pwa-gate-later', 'Continue in the browser for now'); later.type = 'button'; later.hidden = true;
    later.addEventListener('click', () => { remember('snooze', String(Date.now() + 6 * 3600e3)); closeGate(); });
    body.append(later);
    gate.append(body); document.body.append(gate); document.documentElement.classList.add('pwa-gated');
    setTimeout(() => { if (later.isConnected) later.hidden = false; }, 6000);
    const first = gate.querySelector('button:not([disabled])'); if (first) first.focus();
  }
  function bannerOpen(installed) {
    if (document.getElementById('pwa-banner')) return;
    const b = el('div', 'pwa-banner'); b.id = 'pwa-banner';
    b.innerHTML = '<img alt="" width="36" height="36"><span class="grow"><b></b><small>Faster, full screen, from your home screen</small></span><button type="button" class="pwa-banner-go">Install</button><button type="button" class="pwa-banner-x" aria-label="Not now">×</button>';
    b.querySelector('img').src = meta.dataset.icon || ''; b.querySelector('b').textContent = name;
    if (installed) { b.querySelector('small').textContent = 'Installed: open it from your home screen'; b.querySelector('.pwa-banner-go').remove(); }
    b.querySelector('.pwa-banner-go').addEventListener('click', () => { b.remove(); openGate(); });
    b.querySelector('.pwa-banner-x').addEventListener('click', () => { remember('snooze', String(Date.now() + 24 * 3600e3)); b.remove(); });
    document.body.append(b);
  }
  if (inside && phone && !standalone) {
    const snoozed = +(recall('snooze') || 0) > Date.now();
    const installed = recall('installed') === '1';
    if (!snoozed) {
      if (installed) bannerOpen(true); // installed earlier: remind them the app is on the home screen, never block
      else if (force) openGate();
      else bannerOpen();
    }
  }
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
    text.textContent = ios ? 'Open this page in Safari. Tap Share, choose Add to Home Screen, then tap Add. This installs the ' + (role === 'rider' ? 'rider' : (role === 'staff' ? 'staff' : 'customer')) + ' app.' : 'Open your browser menu and choose Install app or Add to Home screen. Installation requires HTTPS and a supported browser.';
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
    // Inside the app every page has <base href=".../app.php/">, which would send "#section" links to the app root.
    // Keep them on the current page.
    document.addEventListener('click', event => {
      const a = event.target.closest && event.target.closest('a[href^="#"]');
      if (!a || event.defaultPrevented || a.hasAttribute('data-sl-open') || a.getAttribute('href') === '#') return;
      event.preventDefault();
      const id = decodeURIComponent(a.getAttribute('href').slice(1));
      history.pushState(null, '', location.pathname + location.search + '#' + id);
      const target = document.getElementById(id) || document.querySelector('[name="' + CSS.escape(id) + '"]');
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      dispatchEvent(new HashChangeEvent('hashchange'));
    });
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
