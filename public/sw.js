const CACHE_NAME = 'mci-test-series-v1';
const STATIC_ASSETS = ['/images/mci-app-icon-192.png', '/images/mci-app-icon-512.png', '/manifest.webmanifest'];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(
    keys.filter(key => key.startsWith('mci-test-series-') && key !== CACHE_NAME).map(key => caches.delete(key))
  )));
  self.clients.claim();
});

// Authenticated test pages and student data are deliberately never cached.
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET' || event.request.mode === 'navigate') return;
  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin || !STATIC_ASSETS.includes(url.pathname)) return;
  event.respondWith(caches.match(event.request).then(cached => cached || fetch(event.request)));
});
