// Retirement endpoint for browsers that still have the former push worker.
// Keep this URL deployed so existing installations can update and unregister.
'use strict';
self.addEventListener('install', event => {
  event.waitUntil(self.skipWaiting());
});
self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    try {
      const notifications = await self.registration.getNotifications();
      notifications.forEach(notification => notification.close());
      const subscription = await self.registration.pushManager.getSubscription();
      if (subscription) await subscription.unsubscribe();
    } finally {
      await self.registration.unregister();
    }
  })());
});
