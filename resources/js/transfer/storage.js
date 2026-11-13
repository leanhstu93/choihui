/**
 * Chọn cách bên nhận ghi file xuống máy, theo khả năng của trình duyệt (thứ tự ưu tiên):
 *  - fs:     File System Access (Chrome/Edge máy tính): ghi thẳng vào file người dùng chọn.
 *  - stream: Service Worker chuyển dòng dữ liệu vào trình tải về (Firefox, Chrome Android).
 *  - opfs:   bộ nhớ riêng của trang (Safari, iPhone): ghi xuống đĩa rồi bấm Lưu.
 *  - memory: giữ trong RAM rồi tải về (trình duyệt cũ): chỉ an toàn với file nhỏ.
 */
export const MEMORY_LIMIT = 500 * 1024 * 1024;

export function detectEnvironment(nav = globalThis.navigator, win = globalThis.window) {
    const agent = nav?.userAgent ?? '';
    const isIos = /iPhone|iPad|iPod/.test(agent) || (agent.includes('Macintosh') && (nav?.maxTouchPoints ?? 0) > 1);
    const isAndroid = /Android/.test(agent);

    return {
        isIos,
        isAndroid,
        isMobile: isIos || isAndroid,
        hasFsAccess: typeof win?.showSaveFilePicker === 'function' && typeof win?.showDirectoryPicker === 'function',
        hasServiceWorker: Boolean(nav?.serviceWorker) && typeof win?.ReadableStream === 'function',
        hasOpfs: typeof nav?.storage?.getDirectory === 'function' && typeof win?.Worker === 'function',
    };
}

export function chooseStorage(env) {
    if (env.hasFsAccess && ! env.isMobile) {
        return 'fs';
    }
    // Safari (iPhone) không tải được từ dòng Service Worker một cách ổn định.
    if (env.hasServiceWorker && ! env.isIos) {
        return 'stream';
    }
    if (env.hasOpfs) {
        return 'opfs';
    }

    return 'memory';
}

/**
 * Kiểm tra trước khi bấm Nhận: có đủ chỗ không. Trả về { ok, warning }.
 * $estimate là kết quả navigator.storage.estimate() (opfs), hoặc null.
 */
export function checkCapacity({ storage, totalSize, estimate = null, isIos = false }) {
    if (storage === 'memory' && totalSize > MEMORY_LIMIT) {
        return {
            ok: false,
            warning: `Trình duyệt này phải giữ file trong bộ nhớ nên chỉ nhận an toàn tới ${formatBytes(MEMORY_LIMIT)}. Hãy mở trang bằng Chrome hoặc Edge để nhận file lớn.`,
        };
    }

    if (storage === 'opfs' && estimate) {
        const free = Math.max(0, (estimate.quota ?? 0) - (estimate.usage ?? 0));
        // Trên iPhone file nằm trong trang trước rồi mới chép ra Tệp: cần chỗ gấp đôi.
        const needed = isIos ? totalSize * 2 : totalSize;

        if (needed > free) {
            return {
                ok: false,
                warning: `Máy này chỉ cho trang dùng khoảng ${formatBytes(free)}, chưa đủ để nhận ${formatBytes(totalSize)}${isIos ? ' (iPhone cần chỗ trống gấp đôi file)' : ''}. Hãy giải phóng bộ nhớ, gửi ít file hơn, hoặc nhận trên máy tính.`,
            };
        }
    }

    if (isIos) {
        return { ok: true, warning: 'Giữ màn hình sáng và không chuyển sang app khác khi đang nhận, nếu không iPhone sẽ dừng việc nhận.' };
    }

    return { ok: true, warning: null };
}

export function formatBytes(bytes) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unit = -1;

    do {
        value /= 1024;
        unit++;
    } while (value >= 1024 && unit < units.length - 1);

    return `${value.toLocaleString('vi-VN', { maximumFractionDigits: value < 10 ? 1 : 0 })} ${units[unit]}`;
}

export function formatDuration(seconds) {
    if (! Number.isFinite(seconds) || seconds < 0) {
        return '';
    }
    if (seconds < 60) {
        return `${Math.max(1, Math.round(seconds))} giây`;
    }
    if (seconds < 3600) {
        return `${Math.round(seconds / 60)} phút`;
    }

    const hours = Math.floor(seconds / 3600);

    return `${hours} giờ ${Math.round((seconds - hours * 3600) / 60)} phút`;
}

/** Tốc độ trung bình trượt (byte/giây) từ các mốc tiến trình. */
export class SpeedMeter {
    constructor(windowMs = 3000, now = () => Date.now()) {
        this.windowMs = windowMs;
        this.now = now;
        this.samples = [];
    }

    update(bytes) {
        const time = this.now();
        this.samples.push([time, bytes]);
        while (this.samples.length > 2 && time - this.samples[0][0] > this.windowMs) {
            this.samples.shift();
        }
    }

    get bytesPerSecond() {
        if (this.samples.length < 2) {
            return 0;
        }

        const [firstTime, firstBytes] = this.samples[0];
        const [lastTime, lastBytes] = this.samples[this.samples.length - 1];

        return lastTime > firstTime ? ((lastBytes - firstBytes) * 1000) / (lastTime - firstTime) : 0;
    }
}
