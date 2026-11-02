/* TWMap service worker - app shell cache (installability + offline fallback) */
var CACHE_NAME = 'twmap4-shell-v1';

function cacheAbsolutePath(path) {
  return new URL(path, self.location.href).href;
}

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll([
        cacheAbsolutePath('manifest.json'),
        cacheAbsolutePath('css/twmap4.css'),
        cacheAbsolutePath('icons/twmap_192.png'),
        cacheAbsolutePath('icons/twmap_512.png'),
        cacheAbsolutePath('icons/twmap_maskable_512.png')
      ]);
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (key) {
          return key !== CACHE_NAME;
        }).map(function (key) {
          return caches.delete(key);
        })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

function isCacheablePath(pathname) {
  return pathname === '/' ||
    pathname === '/index.php' ||
    pathname === '/about.php' ||
    pathname.indexOf('/css/') === 0 ||
    pathname.indexOf('/js/') === 0 ||
    pathname.indexOf('/icons/') === 0 ||
    pathname.indexOf('/icon/') === 0 ||
    pathname === '/manifest.json' ||
    pathname === '/sw.js';
}

function offlineResponse() {
  return new Response('TWMap offline', {
    status: 503,
    statusText: 'Service Unavailable',
    headers: { 'Content-Type': 'text/plain' }
  });
}

function handleFetch(request) {
  return Promise.resolve().then(function () {
    if (request.mode === 'navigate') {
      var shellKey = new Request(cacheAbsolutePath('./'));
      return fetch(request).then(function (networkResponse) {
        cache.put(shellKey, networkResponse.clone());
        return networkResponse;
      }).catch(function () {
        return cache.match(shellKey).then(function (cachedResponse) {
          if (cachedResponse) {
            return cachedResponse;
          }
          return offlineResponse();
        });
      });
    }

    return cache.match(request, { ignoreSearch: true }).then(function (cached) {
      if (cached) {
        return cached;
      }
      return fetch(request).then(function (response) {
        if (response && response.ok) {
          cache.put(request, response.clone());
        }
        return response;
      });
    }).catch(function () {
      return offlineResponse();
    });
  });
}

self.addEventListener('fetch', function (event) {
  var request = event.request;
  var url = new URL(request.url);
  if (request.method !== 'GET') {
    return;
  }
  if (url.origin !== self.location.origin) {
    return;
  }
  if (url.pathname.indexOf('/gen/') === 0) {
    return;
  }
  if (!isCacheablePath(url.pathname)) {
    return;
  }
  event.respondWith(handleFetch(request));
});
