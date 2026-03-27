const CACHE_NAME = 'photon-gallery-v1';
const ASSETS_TO_CACHE = [
  './',
  './index.html',
  './gallery.html',
  './css/portal.css',
  './js/client-area.js',
  './manifest.json',
  '../css/style.css',
  '../assets/favicon.svg'
];

// Install Event - Cache App Shell
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        return cache.addAll(ASSETS_TO_CACHE);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate Event - Clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event - Network First for API, Cache First for Static Assets
self.addEventListener('fetch', (event) => {
  const requestUrl = new URL(event.request.url);

  // Skip caching for API calls, data files, and images
  if (
    requestUrl.pathname.includes('/api/') || 
    requestUrl.pathname.includes('/data/') ||
    requestUrl.pathname.includes('/assets/') && !requestUrl.pathname.endsWith('favicon.svg')
  ) {
    return; // Let the browser handle these normally (Network First)
  }

  // Cache First Strategy for Static Shell
  event.respondWith(
    caches.match(event.request)
      .then((cachedResponse) => {
        // Return cached version if found
        if (cachedResponse) {
          return cachedResponse;
        }
        
        // Otherwise fetch from network
        return fetch(event.request).then((networkResponse) => {
          // Don't cache if not a valid successful response or not a same-origin request
          if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
            return networkResponse;
          }

          // Cache the new resource for future
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME)
            .then((cache) => {
              cache.put(event.request, responseToCache);
            });

          return networkResponse;
        });
      })
  );
});
