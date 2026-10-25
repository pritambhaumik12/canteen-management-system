const STATIC_CACHE = 'canteen-static-v6';
const DYNAMIC_CACHE = 'canteen-dynamic-v6';
const OFFLINE_URL = '/canteen/offline.php';

// Cache all essential UI elements and the offline fallback page
const coreUrlsToCache = [
    '/canteen/index.php',
    '/canteen/manifest.json',
    '/canteen/assets/css/style.css',
    '/canteen/assets/js/script.js',
    '/canteen/assets/images/logos/university.png',
    // Add other common logos/images here
    OFFLINE_URL
];

// Install Event: Cache Core Assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[Service Worker] Pre-caching offline page and core assets');
                return cache.addAll(coreUrlsToCache);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate Event: Clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                        console.log('[Service Worker] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    return self.clients.claim();
});

// Fetch Event: Handle Requests
self.addEventListener('fetch', event => {
    // Ignore non-GET requests (POST requests like form submissions cannot be cached this way)
    if (event.request.method !== 'GET') return;

    // 1. For HTML pages (PHP outputs HTML) -> Network First, Fallback to Cache, Fallback to Offline Page
    if (event.request.headers.get('accept').includes('text/html')) {
        event.respondWith(
            fetch(event.request)
                .then(networkResponse => {
                    // Cache the latest version dynamically
                    return caches.open(DYNAMIC_CACHE).then(cache => {
                        cache.put(event.request.url, networkResponse.clone());
                        return networkResponse;
                    });
                })
                .catch(() => {
                    // Network failed, try to serve from dynamic cache
                    return caches.match(event.request).then(cachedResponse => {
                        if (cachedResponse) {
                            return cachedResponse;
                        }
                        // If not in cache, show the Offline Fallback Page
                        return caches.match(OFFLINE_URL);
                    });
                })
        );
    } 
    // 2. For Static Assets (CSS, JS, Images) -> Cache First, Fallback to Network
    else {
        event.respondWith(
            caches.match(event.request).then(cachedResponse => {
                return cachedResponse || fetch(event.request).then(networkResponse => {
                    // Dynamically cache new images/assets as they are loaded
                    return caches.open(STATIC_CACHE).then(cache => {
                        cache.put(event.request.url, networkResponse.clone());
                        return networkResponse;
                    });
                });
            })
        );
    }
});