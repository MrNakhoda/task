'use strict';

const CACHE_VERSION = 'taskflow-static-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/icons/taskflow-192.png',
    '/icons/taskflow-512.png',
    '/icons/taskflow-maskable-192.png',
    '/icons/taskflow-maskable-512.png',
    '/icons/apple-touch-icon.png',
    '/assets/fonts/Vazirmatn-Regular.woff2',
    '/assets/fonts/Vazirmatn-SemiBold.woff2',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_VERSION).then((cache) => cache.addAll(PRECACHE)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(Promise.all([
        caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key)))),
        self.clients.claim(),
    ]));
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }
    if (url.pathname.startsWith('/api/')) return;
    const cacheable = url.pathname.startsWith('/assets/')
        || url.pathname.startsWith('/icons/')
        || url.pathname === '/manifest.webmanifest'
        || url.pathname === OFFLINE_URL;
    if (!cacheable) return;

    event.respondWith(caches.match(request).then((cached) => {
        const network = fetch(request).then((response) => {
            if (response.ok && response.type === 'basic') {
                caches.open(CACHE_VERSION).then((cache) => cache.put(request, response.clone()));
            }
            return response;
        });
        return cached || network;
    }));
});

self.addEventListener('push', (event) => {
    let payload = {};
    try { payload = event.data ? event.data.json() : {}; } catch { payload = { body: event.data?.text() || '' }; }
    const title = String(payload.title || 'TaskFlow');
    const target = new URL(String(payload.url || '/workspace#notifications'), self.location.origin);
    const safeUrl = target.origin === self.location.origin ? target.href : new URL('/workspace#notifications', self.location.origin).href;
    event.waitUntil(self.registration.showNotification(title, {
        body: String(payload.body || ''),
        icon: '/icons/taskflow-192.png',
        badge: '/icons/taskflow-192.png',
        dir: 'rtl',
        lang: 'fa',
        tag: String(payload.tag || 'taskflow-notification'),
        renotify: false,
        data: { url: safeUrl },
    }).then(() => self.clients.matchAll({ type: 'window', includeUncontrolled: true }))
        .then((clients) => clients.forEach((client) => client.postMessage({ type: 'TASKFLOW_NOTIFICATION' }))));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(String(event.notification.data?.url || '/workspace#notifications'), self.location.origin);
    const safeUrl = target.origin === self.location.origin ? target.href : new URL('/workspace#notifications', self.location.origin).href;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (clients) => {
        for (const client of clients) {
            if (new URL(client.url).origin === self.location.origin) {
                await client.navigate(safeUrl);
                return client.focus();
            }
        }
        return self.clients.openWindow(safeUrl);
    }));
});
