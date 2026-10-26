/* Frikip – service worker : notifications sur le téléphone même quand le site est fermé (messages, appels…). */
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });

self.addEventListener('push', function (e) {
  var d = {};
  try { d = e.data ? e.data.json() : {}; } catch (err) { d = { title: 'Frikip', body: e.data ? e.data.text() : '' }; }
  var call = d.type === 'call';
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    // Site ouvert et visible : la page affiche déjà l'alerte (sauf pour un appel, toujours affiché)
    var visible = list.some(function (c) { return c.visibilityState === 'visible'; });
    if (visible && !call && d.type !== 'test') return;
    return self.registration.showNotification(d.title || 'Frikip', {
      body: d.body || '', icon: d.icon || '/icons/icon-192.png', badge: '/icons/badge-96.png',
      tag: d.tag || d.type || 'terrain', renotify: true, data: { url: d.url || '/' },
      requireInteraction: call, vibrate: call ? [400, 200, 400, 200, 400] : [120, 60, 120]
    });
  }));
});

self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    for (var i = 0; i < list.length; i++) {
      var c = list[i];
      if (c.url.indexOf(self.location.origin) === 0 && 'focus' in c) { return c.focus().then(function (w) { return w && w.navigate ? w.navigate(url) : null; }); }
    }
    return self.clients.openWindow(url);
  }));
});
