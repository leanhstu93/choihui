/*
 * Service worker tối giản để trình duyệt cho phép cài app như ứng dụng điện thoại (PWA).
 * Không lưu cache: mọi request đi thẳng lên máy chủ, tránh hiển thị số tiền cũ.
 */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

/*
 * "Chia sẻ" từ app khác (thư viện ảnh trên Android) vào Sổ tay: hệ điều hành POST file tới
 * /gui-file/chia-se. Cất tạm file vào Cache của trình duyệt (trên máy, không lên server) rồi mở
 * /gui-file?chia-se=1; trang lấy ra, chờ kết nối với máy kia rồi gửi và xóa ngay.
 */
const SHARE_CACHE = 'gui-file-chia-se';

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'POST' || url.pathname !== '/gui-file/chia-se') {
        return;
    }

    event.respondWith((async () => {
        try {
            const form = await event.request.formData();
            const files = form.getAll('files').filter((file) => file instanceof File && file.size > 0);
            const text = ['title', 'text', 'url'].map((key) => form.get(key)).filter((value) => typeof value === 'string' && value.trim() !== '');
            const cache = await caches.open(SHARE_CACHE);

            for (const key of await cache.keys()) {
                await cache.delete(key);
            }
            const meta = [];
            for (const [index, file] of files.entries()) {
                await cache.put(`/__chia-se/${index}`, new Response(file));
                meta.push({ index, name: file.name, type: file.type, lastModified: file.lastModified });
            }
            await cache.put('/__chia-se/meta', new Response(JSON.stringify({ files: meta, text: [...new Set(text)].join('\n') })));

            return Response.redirect('/gui-file?chia-se=1', 303);
        } catch {
            return Response.redirect('/gui-file?chia-se=loi', 303);
        }
    })());
});

/*
 * Gửi file trực tiếp (/gui-file): trang chuyển từng mảnh file nhận được qua MessagePort, service
 * worker phát thành một lượt tải về của trình duyệt (ghi thẳng xuống đĩa, không gom trong RAM).
 * Chỉ trả "pulled" khi hàng đợi còn chỗ, để trang không đẩy nhanh hơn đĩa ghi.
 */
const downloads = new Map();

self.addEventListener('message', (event) => {
    const data = event.data;
    if (data?.type !== 'transfer-download' || ! event.ports[0]) {
        return;
    }
    downloads.set(data.id, { port: event.ports[0], name: data.name, size: data.size });
    event.ports[0].postMessage({ type: 'ready' });
});

self.addEventListener('fetch', (event) => {
    const match = new URL(event.request.url).pathname.match(/^\/gui-file\/tai\/([a-f0-9]+)$/);
    const entry = match ? downloads.get(match[1]) : null;
    if (! entry) {
        return;
    }
    downloads.delete(match[1]);

    const { port } = entry;
    let release = null;
    const stream = new ReadableStream({
        start(controller) {
            port.onmessage = ({ data }) => {
                if (data.type === 'chunk') {
                    controller.enqueue(new Uint8Array(data.chunk));
                    if (controller.desiredSize > 0) {
                        port.postMessage({ type: 'pulled' });
                    } else {
                        release = () => port.postMessage({ type: 'pulled' });
                    }
                } else if (data.type === 'end') {
                    controller.close();
                } else if (data.type === 'abort') {
                    controller.error(new Error('aborted'));
                }
            };
        },
        pull() {
            if (release) {
                const pending = release;
                release = null;
                pending();
            }
        },
        cancel() {
            port.postMessage({ type: 'cancelled' });
        },
    }, new CountQueuingStrategy({ highWaterMark: 16 }));

    event.respondWith(new Response(stream, {
        headers: {
            'Content-Type': 'application/octet-stream',
            'Content-Disposition': `attachment; filename*=UTF-8''${encodeURIComponent(entry.name)}`,
            'Content-Length': String(entry.size),
        },
    }));
});
