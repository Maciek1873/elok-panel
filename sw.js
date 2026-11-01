// Wyłącznik starego service workera (stary panel cache'ował strony w przeglądarce).
// Czyści cache, wyrejestrowuje się i odświeża otwarte karty – wtedy ładuje się nowy panel.
self.addEventListener('install', function (e) { self.skipWaiting(); });
self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys()
      .then(function (keys) { return Promise.all(keys.map(function (k) { return caches.delete(k); })); })
      .then(function () { return self.registration.unregister(); })
      .then(function () { return self.clients.matchAll({ type: 'window' }); })
      .then(function (cls) { cls.forEach(function (c) { c.navigate(c.url); }); })
  );
});
self.addEventListener('fetch', function () { /* nic nie przechwytuje */ });
