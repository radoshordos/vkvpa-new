/*
 * Service worker VKV PA – network-first s běhovým cachováním a offline zálohou.
 *
 * Strategie: pro GET požadavky se nejdřív zkusí síť (čerstvá data), odpověď se
 * uloží do cache; při výpadku sítě se vrátí poslední uložená verze, jinak
 * offline záloha (úvodní stránka). Vhodné pro web s často se měnícími výsledky.
 */
const CACHE = 'vkvpa-v1';
const OFFLINE_URL = '/';

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE)
      .then((cache) => cache.add(OFFLINE_URL))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Cachujeme jen GET; POST/PUT (formuláře, API zápisy) jdou rovnou na síť.
  if (request.method !== 'GET') {
    return;
  }

  event.respondWith(
    fetch(request)
      .then((response) => {
        const copy = response.clone();
        caches.open(CACHE).then((cache) => cache.put(request, copy)).catch(() => {});
        return response;
      })
      .catch(() => caches.match(request).then((cached) => cached || caches.match(OFFLINE_URL))),
  );
});
