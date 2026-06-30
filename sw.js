const CACHE_NAME = 'telefonos-directory-v1';
const ASSETS_TO_CACHE = [
  'mobile',
  'css/style.css',
  'images/escudo.svg',
  'images/icon-192.png',
  'images/icon-512.png',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
  'https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js',
  'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap'
];

// Install Event - Caching App Shell
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => self.skipWaiting())
  );
});

// Activate Event - Clean Up Old Caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event - Stale-While-Revalidate / Network Fallback for assets
self.addEventListener('fetch', (event) => {
  // Avoid caching POST requests or non-HTTP/HTTPS URLs
  if (event.request.method !== 'GET' || !event.request.url.startsWith('http')) {
    return;
  }

  const url = new URL(event.request.url);

  // For dynamic search requests (e.g. mobile?btnBuscar=...), do Network Only
  if (url.searchParams.has('btnBuscar') || url.pathname.endsWith('ldap_') || (url.pathname.endsWith('.php') && url.search !== '')) {
    event.respondWith(
      fetch(event.request).catch(() => {
        // If search fails offline, respond with the cached mobile shell
        return caches.match('mobile');
      })
    );
    return;
  }

  // Stale-While-Revalidate or Network-First for core shell assets
  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      if (cachedResponse) {
        // Fetch in background to update cache (Stale-While-Revalidate)
        fetch(event.request).then((networkResponse) => {
          if (networkResponse.status === 200) {
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, networkResponse));
          }
        }).catch(() => {/* ignore background fetch errors */});
        return cachedResponse;
      }

      return fetch(event.request).then((networkResponse) => {
        if (!networkResponse || networkResponse.status !== 200) {
          return networkResponse;
        }

        const responseToCache = networkResponse.clone();
        caches.open(CACHE_NAME).then((cache) => {
          cache.put(event.request, responseToCache);
        });

        return networkResponse;
      });
    })
  );
});
