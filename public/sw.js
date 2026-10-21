/*
 * Service worker tối giản để trình duyệt cho phép cài Sổ hụi như app (PWA).
 * Không lưu cache: mọi request đi thẳng lên máy chủ, tránh hiển thị số tiền cũ.
 */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
