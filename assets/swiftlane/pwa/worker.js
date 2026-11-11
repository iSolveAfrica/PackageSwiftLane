/* Private screens, PINs, API data, photos and mutations are NEVER cached/queued. */
'use strict';
const scopeURL = new URL(self.registration.scope);
const rootURL = new URL('../../', scopeURL);
const prefix = 'swiftlane-' + self.APP_ROLE + '-' + scopeURL.pathname.replace(/[^a-z0-9]/gi, '_') + '-';
const cacheName = prefix + '20261111-v1';
const offlineURL = new URL('offline.html', scopeURL).href;
const staticFiles = [offlineURL, new URL('assets/swiftlane/pwa/' + self.APP_ROLE + '-192.png', rootURL).href];
self.addEventListener('install', event => {
  event.waitUntil(caches.open(cacheName).then(cache => cache.addAll(staticFiles)));
});
self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    const names = await caches.keys();
    await Promise.all(names.filter(name => name.startsWith(prefix) && name !== cacheName).map(name => caches.delete(name)));
    await self.clients.claim();
  })());
});
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'APPLY_UPDATE') self.skipWaiting();
});
function publicAsset(url) {
  if (url.origin !== rootURL.origin || !url.pathname.startsWith(rootURL.pathname)) return false;
  const path = url.pathname.slice(rootURL.pathname.length);
  // Only immutable first-party UI resources, never uploads or customer media.
  return /^(?:assets\/swiftlane\/|assets\/css_main_swiftlane\/|dataJs\/sl\/)/.test(path)
    && /\.(?:css|js|svg|png|woff2?)$/.test(path)
    && url.searchParams.has('v');
}
self.addEventListener('fetch', event => {
  const request = event.request, url = new URL(request.url);
  if (request.method !== 'GET' || url.origin !== scopeURL.origin) return;
  if (request.mode === 'navigate') {
    if (!url.pathname.startsWith(scopeURL.pathname)) return;
    event.respondWith(fetch(request).catch(async () => {
      const cached = await caches.match(offlineURL, { cacheName });
      return cached || new Response('You are offline. Reconnect to continue.', { status: 503, headers: { 'Content-Type': 'text/plain' } });
    }));
    return;
  }
  if (!publicAsset(url)) return;
  event.respondWith((async () => {
    const cache = await caches.open(cacheName), cached = await cache.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok && response.type === 'basic' && !/no-store|private/i.test(response.headers.get('Cache-Control') || '')) {
      await cache.put(request, response.clone());
      const keys = await cache.keys();
      const removable = keys.filter(key => !staticFiles.includes(key.url));
      if (removable.length > 100) await cache.delete(removable[0]);
    }
    return response;
  })());
});
