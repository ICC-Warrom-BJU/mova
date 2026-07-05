// MOVA Service Worker — versi SELF-DESTRUCT.
//
// Service worker sempat menyebabkan CSS basi tersaji dari cache & mengganggu
// pemuatan Chart.js. Selama fase pengembangan UI, SW dinonaktifkan total:
// versi ini menghapus semua cache, melepas registrasi dirinya sendiri, lalu
// memuat ulang halaman supaya browser lepas dari kendali SW lama secara otomatis
// (tanpa perlu Unregister manual di DevTools).
//
// Registrasi SW di layout juga sudah dihapus, jadi tidak akan terdaftar lagi.
// Nanti sebelum production, PWA/offline bisa diaktifkan kembali dengan strategi
// network-first bila diperlukan.

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map(k => caches.delete(k)));
    await self.registration.unregister();
    const clients = await self.clients.matchAll({ type: 'window' });
    for (const client of clients) {
      client.navigate(client.url);
    }
  })());
});
