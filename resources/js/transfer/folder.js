import { makeZip, predictLength } from 'client-zip';
import { safeFileName } from './protocol.js';

/**
 * Gửi cả thư mục: bên gửi lấy đường dẫn tương đối của từng file ("Ảnh/2024/a.jpg"), bên nhận dựng
 * lại cây thư mục (Chrome/Edge máy tính) hoặc gói thành một file .zip trong lúc nhận (trình duyệt
 * khác). Đường dẫn từ máy kia luôn được làm sạch: không có "..", không có đường dẫn tuyệt đối.
 */

export const MAX_PATH_DEPTH = 20;

/**
 * Các đoạn an toàn của một đường dẫn tương đối (đoạn cuối là tên file); [] nếu không dùng được.
 */
export function safePathSegments(path) {
    const parts = String(path ?? '').split(/[\\/]+/).filter((part) => part !== '' && part !== '.' && part !== '..');

    if (parts.length === 0 || parts.length > MAX_PATH_DEPTH) {
        return [];
    }

    return parts.map((part) => safeFileName(part));
}

/** Tên thư mục gốc chung của cả lời mời, hoặc null nếu không phải gửi thư mục. */
export function folderName(files) {
    const roots = new Set(files.map((file) => (file.path ? safePathSegments(file.path) : [])).filter((parts) => parts.length > 1).map((parts) => parts[0]));

    return roots.size === 1 ? [...roots][0] : (roots.size > 1 ? 'Thư mục' : null);
}

/**
 * Lấy file kèm đường dẫn từ một lượt kéo thả (có thể có thư mục). Phải gọi ngay trong sự kiện
 * drop: danh sách item hết hiệu lực sau đó.
 *
 * @returns {Promise<Array<{file: File, path: string|null}>>}
 */
export function collectDropped(dataTransfer) {
    const entries = [...(dataTransfer?.items ?? [])]
        .filter((item) => item.kind === 'file')
        .map((item) => item.webkitGetAsEntry?.() ?? null);

    if (entries.length === 0 || entries.some((entry) => entry === null)) {
        // Trình duyệt không cho duyệt thư mục: chỉ lấy file thường.
        return Promise.resolve([...(dataTransfer?.files ?? [])].map((file) => ({ file, path: null })));
    }

    return Promise.all(entries.map((entry) => walk(entry, ''))).then((lists) => lists.flat());
}

async function walk(entry, prefix) {
    if (entry.isFile) {
        const file = await new Promise((resolve, reject) => entry.file(resolve, reject));

        return [{ file, path: prefix ? `${prefix}/${file.name}` : null }];
    }

    const reader = entry.createReader();
    const children = [];
    // readEntries trả từng đợt (Chrome: 100 mục), gọi tới khi rỗng.
    for (;;) {
        const batch = await new Promise((resolve, reject) => reader.readEntries(resolve, reject));
        if (batch.length === 0) {
            break;
        }
        children.push(...batch);
    }

    const path = prefix ? `${prefix}/${entry.name}` : entry.name;
    const lists = [];
    for (const child of children) {
        lists.push(await walk(child, path));
    }

    return lists.flat();
}

/** File chọn bằng ô chọn thư mục (webkitdirectory) đã có sẵn webkitRelativePath. */
export function fromDirectoryInput(fileList) {
    return [...fileList].map((file) => ({ file, path: file.webkitRelativePath || null }));
}

/** Lấy (hoặc tạo) thư mục con theo đường dẫn trong thư mục người dùng đã chọn. */
export async function directoryFor(root, segments) {
    let directory = root;
    for (const segment of segments) {
        directory = await directory.getDirectoryHandle(segment, { create: true });
    }

    return directory;
}

/**
 * Gói các file của một lời mời thành một .zip (không nén) ngay trong lúc nhận. Các file đến lần
 * lượt; mỗi file là một entry. Ghi ra $target (sink bất kỳ) với áp lực ngược đầy đủ: bên nhận chỉ
 * nhận tiếp khi zip đã ghi xong phần trước.
 */
export class ZipAssembler {
    /**
     * @param {{ files: Array<{fid: string, name: string, path?: string, size: number}>, openTarget: (size: number) => Promise<any>, onDone?: Function, onError?: Function }} options
     */
    constructor({ files, openTarget, onDone = () => {}, onError = () => {} }) {
        this.entries = files.map((file) => ({ fid: file.fid, name: zipPath(file), size: file.size }));
        this.length = Number(predictLength(this.entries.map(({ name, size }) => ({ name, size }))));
        this.waiting = new Map();
        this.pendingInputs = new Map();
        this.queue = this.entries.map((entry) => entry.fid);
        this.remaining = new Set(this.queue);
        this.onDone = onDone;
        this.onError = onError;
        this.failed = false;
        this.failure = new Promise((resolve) => { this.signalFailure = resolve; });
        this.done = this.run(openTarget);
    }

    /** Sink cho một file trong zip; IncomingFile ghi vào đây. */
    sinkFor(fid) {
        const entry = this.entries.find((item) => item.fid === fid);
        const { readable, writable } = new TransformStream({}, { highWaterMark: 4 }, { highWaterMark: 4 });
        const writer = writable.getWriter();
        const input = { name: entry.name, size: entry.size, input: readable };

        if (this.waiting.has(fid)) {
            this.waiting.get(fid)(input);
            this.waiting.delete(fid);
        } else {
            this.pendingInputs.set(fid, input);
        }

        return {
            write: async (data) => {
                await writer.ready;
                await writer.write(new Uint8Array(data));
            },
            close: async () => {
                await writer.close();
                this.remaining.delete(fid);

                return {};
            },
            abort: async () => {
                await writer.abort(new Error('cancelled')).catch(() => {});
                this.fail(new Error('Một file trong thư mục bị hủy nên file .zip không đầy đủ.'));
            },
        };
    }

    /** Một file bị hủy trước khi bắt đầu: zip đã khai báo kích thước nên không còn đúng nữa. */
    skip(fid) {
        if (this.remaining.has(fid)) {
            this.fail(new Error('Một file trong thư mục bị hủy nên file .zip không đầy đủ.'));
        }
    }

    fail(error) {
        if (! this.failed) {
            this.failed = true;
            this.signalFailure(null);
            this.onError(error);
        }
    }

    async *inputs() {
        while (this.queue.length > 0) {
            const fid = this.queue.shift();
            const ready = this.pendingInputs.get(fid);
            this.pendingInputs.delete(fid);
            const input = ready ?? await Promise.race([new Promise((resolve) => this.waiting.set(fid, resolve)), this.failure]);
            if (! input || this.failed) {
                return;
            }
            yield input;
        }
    }

    async run(openTarget) {
        try {
            const target = await openTarget(this.length);
            const reader = makeZip(this.inputs(), { buffersAreUTF8: true }).getReader();

            for (;;) {
                const { value, done } = await reader.read();
                if (done) {
                    break;
                }
                if (this.failed) {
                    // Không cắt ngang thư viện zip (nó báo lỗi không ai bắt): đọc nốt rồi bỏ.
                    continue;
                }
                // Chép ra ArrayBuffer riêng: sink có thể chuyển (transfer) buffer đi.
                await target.write(value.slice().buffer);
            }

            if (this.failed) {
                await target.abort?.();

                return;
            }
            this.onDone(await target.close());
        } catch (error) {
            this.fail(error);
        }
    }
}

function zipPath(file) {
    const segments = file.path ? safePathSegments(file.path) : [];

    return segments.length > 0 ? segments.join('/') : safeFileName(file.name);
}
