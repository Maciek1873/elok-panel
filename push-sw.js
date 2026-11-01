// ELOK – service worker tylko do powiadomień (niczego nie zapisuje w pamięci podręcznej)
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('push', function (e) {
  var d = {};
  try { d = e.data ? e.data.json() : {}; } catch (x) { d = { body: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(d.title || 'ELOK', {
    body: d.body || '', icon: 'ikona_elok.png?v=2', badge: 'ikona_elok.png?v=2', data: { url: d.url || './' }, tag: 'elok-' + Date.now()
  }));
});
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = (e.notification.data && e.notification.data.url) || './';
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (cls) {
    for (var i = 0; i < cls.length; i++) { if ('focus' in cls[i]) { cls[i].navigate(url); return cls[i].focus(); } }
    return self.clients.openWindow(url);
  }));
});
