import { randomId, safeFileName } from './protocol.js';

/**
 * Nơi bên nhận ghi dữ liệu. Mọi sink có write(ArrayBuffer), close() → { url?, file? }, abort().
 */

/** Giữ trong RAM (dự phòng cuối, chỉ cho file nhỏ). */
export class MemorySink {
    constructor(name, type = '') {
        this.name = safeFileName(name);
        this.type = type;
        this.parts = [];
    }

    async write(data) {
        this.parts.push(data);
    }

    async close() {
        const file = new File(this.parts, this.name, { type: this.type || 'application/octet-stream' });
        this.parts = [];

        return { file };
    }

    async abort() {
        this.parts = [];
    }
}

/** File System Access: ghi thẳng vào file người dùng đã chọn. */
export class FsSink {
    constructor(writable) {
        this.writable = writable;
    }

    static async fromHandle(handle) {
        return new FsSink(await handle.createWritable());
    }

    async write(data) {
        await this.writable.write(data);
    }

    async close() {
        await this.writable.close();

        return {};
    }

    async abort() {
        await this.writable.abort().catch(() => {});
    }
}

/**
 * Service Worker (/sw.js) phát dòng dữ liệu thành một lượt tải về của trình duyệt. Mỗi write chờ
 * Service Worker báo còn chỗ trong hàng đợi, nên RAM không phình khi đĩa ghi chậm.
 */
export class StreamSink {
    constructor(port, iframe) {
        this.port = port;
        this.iframe = iframe;
        this.waiting = null;
        this.cancelled = false;
        port.onmessage = ({ data }) => {
            if (data.type === 'pulled' && this.waiting) {
                const resolve = this.waiting;
                this.waiting = null;
                resolve();
            }
            if (data.type === 'cancelled') {
                this.cancelled = true;
                this.waiting?.();
            }
        };
    }

    static async open(name, size) {
        const registration = await navigator.serviceWorker.ready;
        const id = randomId();
        const channel = new MessageChannel();

        await new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('service-worker-timeout')), 5000);
            channel.port1.onmessage = ({ data }) => {
                if (data.type === 'ready') {
                    clearTimeout(timer);
                    resolve();
                }
            };
            registration.active.postMessage({ type: 'transfer-download', id, name: safeFileName(name), size }, [channel.port2]);
        });

        const iframe = document.createElement('iframe');
        iframe.hidden = true;
        iframe.src = `/gui-file/tai/${id}`;
        document.body.appendChild(iframe);

        return new StreamSink(channel.port1, iframe);
    }

    async write(data) {
        if (this.cancelled) {
            throw new Error('Đã hủy lượt tải trên trình duyệt.');
        }

        const pulled = new Promise((resolve) => { this.waiting = resolve; });
        this.port.postMessage({ type: 'chunk', chunk: data }, [data]);
        await pulled;
    }

    async close() {
        this.port.postMessage({ type: 'end' });
        setTimeout(() => this.iframe.remove(), 60000);

        return {};
    }

    async abort() {
        this.port.postMessage({ type: 'abort' });
        setTimeout(() => this.iframe.remove(), 1000);
    }
}

/** Bộ nhớ riêng của trang (OPFS) qua worker: ghi xuống đĩa, xong thì đưa File để người dùng lưu. */
export class OpfsSink {
    constructor(worker, name, type) {
        this.worker = worker;
        this.name = name;
        this.type = type;
        this.pending = new Map();
        this.sequence = 0;
        worker.onmessage = ({ data }) => {
            const entry = this.pending.get(data.seq);
            if (! entry) {
                return;
            }
            this.pending.delete(data.seq);
            data.error ? entry.reject(new Error(data.error)) : entry.resolve(data);
        };
    }

    static async open(name, type) {
        const worker = new Worker(new URL('./opfs-worker.js', import.meta.url), { type: 'module' });
        const sink = new OpfsSink(worker, `${randomId()}-${safeFileName(name)}`, type);
        await sink.call({ op: 'open', name: sink.name });

        return sink;
    }

    call(message, transfer = []) {
        const seq = ++this.sequence;

        return new Promise((resolve, reject) => {
            this.pending.set(seq, { resolve, reject });
            this.worker.postMessage({ ...message, seq }, transfer);
        });
    }

    async write(data) {
        await this.call({ op: 'write', data }, [data]);
    }

    async close() {
        await this.call({ op: 'close' });
        this.worker.terminate();
        const directory = await navigator.storage.getDirectory();
        const folder = await directory.getDirectoryHandle('gui-file', { create: true });
        const stored = await (await folder.getFileHandle(this.name)).getFile();
        const displayName = this.name.slice(this.name.indexOf('-') + 1);

        return { file: new File([stored], displayName, { type: this.type || stored.type }), opfsName: this.name };
    }

    async abort() {
        await this.call({ op: 'abort' }).catch(() => {});
        this.worker.terminate();
    }
}

/** Dọn file tạm OPFS của những lần trước (sau khi người dùng đã lưu hoặc rời trang). */
export async function clearOpfs() {
    try {
        const directory = await navigator.storage.getDirectory();
        await directory.removeEntry('gui-file', { recursive: true });
    } catch {
        // Chưa có thư mục, hoặc trình duyệt không hỗ trợ.
    }
}
