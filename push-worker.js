self.addEventListener('push', event => {
  event.waitUntil(self.registration.showNotification('DALOY', {
    body: 'You have a new workspace notification.',
    tag: 'daloy-inbox'
  }));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const inbox = new URL('?page=notifications', self.registration.scope).href;
  event.waitUntil(self.clients.matchAll({type:'window'}).then(windows => {
    const existing = windows.find(window => window.url === inbox);
    return existing ? existing.focus() : self.clients.openWindow(inbox);
  }));
});
