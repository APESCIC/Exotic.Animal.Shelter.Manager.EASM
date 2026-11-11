/*
 * EASM service worker: keeps the app shell and visited mobile pages (/m) available
 * offline. Writes are not handled here; offline-queue.js stores and replays them.
 */
'use strict';

var SHELL_CACHE = 'easm-shell-v3';
var PAGE_CACHE = 'easm-pages';
var BASE = self.location.pathname.replace(/sw\.js$/, '');
var SHELL = [
    'css/easm.css',
    'js/easm.js',
    'js/offline-queue.js',
    'js/signature-pad.js',
    'js/qr-scan.js',
    'images/pwa/easm.svg',
    'images/pwa/easm-192.png',
    'offline.html',
].map(function (path) {
    return BASE + path;
});

self.addEventListener('install', function (event) {
    event.waitUntil(caches.open(SHELL_CACHE).then(function (cache) {
        return cache.addAll(SHELL);
    }).then(function () {
        return self.skipWaiting();
    }));
});

self.addEventListener('activate', function (event) {
    event.waitUntil(caches.keys().then(function (keys) {
        return Promise.all(keys.filter(function (key) {
            return key.indexOf('easm-shell-') === 0 && key !== SHELL_CACHE;
        }).map(function (key) {
            return caches.delete(key);
        }));
    }).then(function () {
        return self.clients.claim();
    }));
});

function isMobilePage(url) {
    return url.pathname === BASE + 'm' || url.pathname.indexOf(BASE + 'm/') === 0;
}

function isShellAsset(url) {
    return SHELL.indexOf(url.pathname) !== -1;
}

self.addEventListener('fetch', function (event) {
    var request = event.request;
    var url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).then(function (response) {
            if (response.ok && isMobilePage(url)) {
                var copy = response.clone();
                caches.open(PAGE_CACHE).then(function (cache) {
                    cache.put(request, copy);
                });
            }
            return response;
        }).catch(function () {
            return caches.match(request).then(function (cached) {
                return cached || caches.match(BASE + 'offline.html');
            });
        }));
        return;
    }

    if (isShellAsset(url)) {
        event.respondWith(caches.open(SHELL_CACHE).then(function (cache) {
            return cache.match(request, { ignoreSearch: true }).then(function (cached) {
                var network = fetch(request).then(function (response) {
                    if (response.ok) {
                        cache.put(url.pathname, response.clone());
                    }
                    return response;
                }).catch(function () {
                    return cached;
                });
                return cached || network;
            });
        }));
    }
});
