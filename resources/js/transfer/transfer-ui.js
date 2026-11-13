import { collectDropped, directoryFor, folderName, fromDirectoryInput, safePathSegments, ZipAssembler } from './folder.js';
import { randomId, safeFileName } from './protocol.js';
import { request } from './signaling.js';
import { clearOpfs, FsSink, MemorySink, OpfsSink, StreamSink } from './sinks.js';
import { CONNECTION_LABELS } from './stats.js';
import { checkCapacity, chooseStorage, detectEnvironment, formatBytes, formatDuration, SpeedMeter } from './storage.js';
import { TransferSession } from './session.js';

const COLORS = ['Xanh', 'Đỏ', 'Vàng', 'Tím', 'Cam', 'Hồng', 'Bạc', 'Nâu', 'Lục', 'Lam'];
const ANIMALS = ['Mèo', 'Cá', 'Chim', 'Thỏ', 'Gấu', 'Hổ', 'Voi', 'Cáo', 'Sóc', 'Rùa', 'Ngựa', 'Công'];

const pick = (list) => list[Math.floor(Math.random() * list.length)];

function deviceLabel(agent) {
    if (/iPhone/.test(agent)) return 'iPhone';
    if (/iPad/.test(agent)) return 'iPad';
    if (/Android/.test(agent)) return /Mobile/.test(agent) ? 'Điện thoại Android' : 'Máy tính bảng Android';
    if (/Macintosh/.test(agent)) return 'Máy Mac';
    if (/Windows/.test(agent)) return 'Máy Windows';

    return 'Máy tính';
}

const STATUS_LABELS = {
    waiting: 'Chờ máy kia đồng ý',
    queued: 'Đang chờ lượt',
    sending: 'Đang gửi',
    receiving: 'Đang nhận',
    paused: 'Tạm dừng, sẽ gửi tiếp khi nối lại',
    zipping: 'Đang gói thành .zip',
    rejected: 'Bị từ chối',
    cancelled: 'Đã hủy',
    failed: 'Lỗi',
};

const isHttpLink = (text) => /^https?:\/\/\S+$/i.test(text.trim());

/** Tìm tên chưa có trong thư mục: "a.txt", "a (1).txt", ... */
async function uniqueFileHandle(directory, name) {
    const safe = safeFileName(name);
    const dot = safe.lastIndexOf('.');
    const [base, ext] = dot > 0 ? [safe.slice(0, dot), safe.slice(dot)] : [safe, ''];

    for (let index = 0; index < 1000; index++) {
        const candidate = index === 0 ? safe : `${base} (${index})${ext}`;
        try {
            await directory.getFileHandle(candidate);
        } catch {
            return directory.getFileHandle(candidate, { create: true });
        }
    }

    return directory.getFileHandle(`${base}-${randomId()}${ext}`, { create: true });
}

/** Tên thư mục chưa có: "Ảnh", "Ảnh (1)", ... */
async function uniqueDirectoryName(parent, name) {
    for (let index = 0; index < 1000; index++) {
        const candidate = index === 0 ? name : `${name} (${index})`;
        try {
            await parent.getDirectoryHandle(candidate);
        } catch {
            return candidate;
        }
    }

    return `${name}-${randomId()}`;
}

/**
 * Trang gửi file trực tiếp (/gui-file). config: { apiBase, joinCode }.
 */
export function fileTransfer(config) {
    let session = null;
    let wakeLock = null;
    const live = new Map();

    return {
        phase: 'start',
        message: '',
        errorReason: '',
        room: null,
        joinInput: '',
        joining: false,
        me: { name: `${pick(ANIMALS)} ${pick(COLORS)}`, device: deviceLabel(navigator.userAgent) },
        peer: null,
        kind: null,
        safety: null,
        safetyOpen: false,
        shared: [],
        sharedText: false,
        nearby: [],
        nearbyInvite: null,
        nearbyInviting: null,
        nearbyNote: '',
        onCellular: (navigator.connection?.type ?? '') === 'cellular',
        shareNotice: '',
        files: [],
        offers: [],
        texts: [],
        textDraft: '',
        dragging: false,
        env: detectEnvironment(),
        storage: 'memory',
        copiedCode: false,

        init() {
            this.storage = chooseStorage(this.env);
            clearOpfs();

            if (config.share === '1') {
                this.loadShared();
            } else if (config.share === 'loi') {
                this.shareNotice = 'Chưa nhận được file từ Chia sẻ. Hãy kết nối rồi bấm "Chọn file để gửi".';
            }

            const code = (config.joinCode ?? '').trim();
            if (code) {
                this.joinInput = code;
                this.join();
            } else {
                this.createRoom();
            }

            setInterval(() => this.flush(), 300);
            // Đóng trang: báo máy kia để nó không ngồi chờ nối lại.
            window.addEventListener('pagehide', () => session?.sendControl({ t: 'bye' }));
            window.addEventListener('beforeunload', (event) => {
                if (session?.busy) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        },

        // ----- Phòng -----

        async createRoom(keepMessage = false) {
            const message = this.message;
            this.reset();
            if (keepMessage) {
                this.message = message;
            }
            this.phase = 'creating';

            try {
                this.room = await request(fetch, config.apiBase, { method: 'POST' });
                this.startSession();
            } catch (error) {
                this.showError(error.message, error.reason);
            }
        },

        async join() {
            const code = this.joinInput.replace(/[\s-]/g, '').toUpperCase();
            if (code.length !== 6) {
                this.message = 'Mã phòng gồm 6 ký tự, ví dụ K7P2QX.';

                return;
            }

            const hadOwnRoom = this.phase === 'waiting';
            this.joining = true;
            this.message = '';
            // Đang chờ trong phòng của mình thì bỏ phòng đó.
            session?.signaling.close();
            session = null;

            try {
                const room = await request(fetch, `${config.apiBase}/${code}/vao`, { method: 'POST' });
                this.reset();
                this.room = room;
                this.startSession();
            } catch (error) {
                this.message = error.message;
                if (hadOwnRoom) {
                    // Vẫn mở phòng của mình để máy khác gửi sang được.
                    await this.createRoom(true);
                } else {
                    this.phase = 'join-failed';
                }
            } finally {
                this.joining = false;
            }
        },

        startSession() {
            session = new TransferSession({
                room: this.room,
                apiBase: config.apiBase,
                me: this.me,
                handlers: {
                    onState: (phase, message = '', reason = '') => {
                        this.phase = phase;
                        if (phase === 'connected' && this.shared.length > 0) {
                            // File chia sẻ từ app khác: kết nối xong là mời máy kia nhận luôn.
                            const items = this.shared;
                            this.shared = [];
                            this.sendFiles(items);
                        }
                        if (phase === 'error' || phase === 'disconnected') {
                            this.message = message;
                            this.errorReason = reason;
                            this.releaseWakeLock();
                        }
                    },
                    onPeer: (peer) => { this.peer = peer; },
                    onKind: (kind) => { this.kind = kind; },
                    onNearby: (nearby) => this.updateNearby(nearby),
                    onSafety: (code) => { this.safety = code; },
                    onText: (text) => this.texts.unshift({ id: randomId(), from: 'peer', text, link: isHttpLink(text), copied: false }),
                    onOffer: (offer) => this.addOffer(offer),
                    onOfferGone: (id) => { this.offers = this.offers.filter((offer) => offer.id !== id); },
                    onFile: (fid, patch) => this.patchFile(fid, patch),
                },
            });
            session.setNearby(! this.onCellular);
            session.start();
        },

        // ----- Máy cùng wifi -----

        updateNearby({ devices, invite, declinedBy }) {
            this.nearby = devices ?? [];
            // Lời mời đang chờ tự hết khi máy mời không còn trong danh sách.
            this.nearbyInvite = invite && ! this.joining ? invite : null;
            if (this.nearbyInviting && ! this.nearby.some((device) => device.id === this.nearbyInviting.id)) {
                this.nearbyInviting = null;
            }
            if (declinedBy) {
                this.nearbyInviting = null;
                this.nearbyNote = `${declinedBy} đã từ chối kết nối.`;
            }
        },

        async invite(device) {
            this.nearbyNote = '';
            this.nearbyInviting = device;
            try {
                await session.inviteNearby(device.id);
            } catch (error) {
                this.nearbyInviting = null;
                this.nearbyNote = error.message;
            }
        },

        acceptInvite() {
            const invite = this.nearbyInvite;
            this.nearbyInvite = null;
            this.joinInput = invite.code;
            this.join();
        },

        declineInvite() {
            this.nearbyInvite = null;
            session?.declineNearby();
        },

        deviceIcon(device) {
            return /iPhone|Android|điện thoại/i.test(device ?? '') ? '📱' : (/bảng|iPad/i.test(device ?? '') ? '📲' : '💻');
        },

        reset() {
            this.nearby = [];
            this.nearbyInvite = null;
            this.nearbyInviting = null;
            this.nearbyNote = '';
            this.room = null;
            this.peer = null;
            this.kind = null;
            this.safety = null;
            this.safetyOpen = false;
            this.offers = [];
            this.message = '';
            this.errorReason = '';
        },

        disconnect() {
            session?.leave();
        },

        startOver() {
            session?.signaling.close();
            session = null;
            this.files = [];
            this.texts = [];
            live.clear();
            this.createRoom();
        },

        copyCode() {
            navigator.clipboard?.writeText(this.room.joinUrl).then(() => {
                this.copiedCode = true;
                setTimeout(() => { this.copiedCode = false; }, 2000);
            }).catch(() => {});
        },

        get safetyWords() {
            return (this.safety ?? []).map((item) => item.name).join(' · ');
        },

        get kindLabel() {
            return this.kind ? CONNECTION_LABELS[this.kind] : 'Đang kiểm tra loại kết nối…';
        },

        get storageNote() {
            return {
                fs: 'File được ghi thẳng vào thư mục bạn chọn.',
                stream: 'File được tải thẳng vào thư mục Tải về của trình duyệt.',
                opfs: 'File được lưu tạm trong trình duyệt, nhận xong bấm "Lưu" để cất vào máy.',
                memory: 'Trình duyệt này chỉ nhận được file nhỏ (dưới 500 MB).',
            }[this.storage];
        },

        // ----- File chia sẻ từ app khác (PWA share target, xem sw.js) -----

        async loadShared() {
            history.replaceState(null, '', location.pathname);

            try {
                const cache = await caches.open('gui-file-chia-se');
                const meta = await (await cache.match('/__chia-se/meta'))?.json();
                if (! meta) {
                    return;
                }
                const items = [];
                for (const entry of meta.files ?? []) {
                    const blob = await (await cache.match(`/__chia-se/${entry.index}`))?.blob();
                    if (blob) {
                        items.push({ file: new File([blob], entry.name || 'file', { type: entry.type || blob.type, lastModified: entry.lastModified }), path: null });
                    }
                }
                await caches.delete('gui-file-chia-se');

                this.shared = items;
                if (meta.text) {
                    this.textDraft = meta.text;
                    this.sharedText = true;
                }
                if (items.length === 0 && ! meta.text) {
                    this.shareNotice = 'Không đọc được file vừa chia sẻ. Hãy kết nối rồi bấm "Chọn file để gửi".';
                }
            } catch {
                this.shareNotice = 'Không đọc được file vừa chia sẻ. Hãy kết nối rồi bấm "Chọn file để gửi".';
            }
        },

        get sharedSummary() {
            const total = this.shared.reduce((sum, { file }) => sum + file.size, 0);

            return `${this.shared.length} file · ${formatBytes(total)}`;
        },

        dropShared() {
            this.shared = [];
        },

        // ----- Gửi -----

        pickFiles(event) {
            this.sendFiles([...event.target.files]);
            event.target.value = '';
        },

        pickFolder(event) {
            const items = fromDirectoryInput(event.target.files);
            event.target.value = '';
            if (items.length === 0) {
                this.message = 'Thư mục này trống, chưa có file nào để gửi.';

                return;
            }
            this.sendFiles(items);
        },

        async dropFiles(event) {
            this.dragging = false;
            // Phải đọc ngay trong sự kiện drop (kể cả thư mục bên trong).
            const pending = collectDropped(event.dataTransfer);
            try {
                const items = await pending;
                if (items.length === 0) {
                    this.message = 'Không đọc được file vừa thả. Hãy bấm "Chọn file" hoặc "Chọn thư mục".';

                    return;
                }
                this.sendFiles(items);
            } catch {
                this.message = 'Không đọc được thư mục vừa thả. Hãy bấm "Chọn thư mục" để chọn lại.';
            }
        },

        get canPickFolder() {
            return ! this.env.isMobile && 'webkitdirectory' in document.createElement('input');
        },

        sendFiles(files) {
            if (files.length === 0 || this.phase !== 'connected') {
                return;
            }
            const problem = session.offerFiles(files);
            this.message = problem ?? '';
            if (! problem) {
                this.requestWakeLock();
            }
        },

        sendText() {
            const text = this.textDraft.trim();
            if (! text || this.phase !== 'connected') {
                return;
            }
            const sent = session.sendText(text);
            this.texts.unshift({ id: randomId(), from: 'me', text: sent, link: isHttpLink(sent), copied: false });
            this.textDraft = '';
        },

        copyText(entry) {
            navigator.clipboard?.writeText(entry.text).then(() => {
                entry.copied = true;
                setTimeout(() => { entry.copied = false; }, 2000);
            }).catch(() => {});
        },

        // ----- Nhận -----

        async addOffer(offer) {
            const existing = this.offers.find((item) => item.id === offer.id);
            const estimate = this.storage === 'opfs' ? await navigator.storage?.estimate?.().catch(() => null) : null;
            const check = checkCapacity({ storage: this.storage, totalSize: offer.totalSize, estimate, isIos: this.env.isIos });
            const card = { ...offer, files: [...offer.files], check, busy: false, folder: folderName(offer.files) };

            if (existing) {
                Object.assign(existing, card);
            } else {
                this.offers.push(card);
            }
        },

        async accept(offer) {
            offer.busy = true;
            let openSink;

            try {
                if (this.storage === 'fs') {
                    if (offer.folder) {
                        // Dựng lại cây thư mục trong chỗ người dùng chọn; trùng tên thì thêm (1), (2)…
                        const root = await window.showDirectoryPicker({ id: 'gui-file', mode: 'readwrite' });
                        const roots = new Map();
                        openSink = async (file) => {
                            const segments = safePathSegments(file.path);
                            const parts = segments.length > 0 ? segments : [safeFileName(file.name)];
                            if (parts.length > 1) {
                                if (! roots.has(parts[0])) {
                                    roots.set(parts[0], uniqueDirectoryName(root, parts[0]));
                                }
                                parts[0] = await roots.get(parts[0]);
                            }
                            const directory = await directoryFor(root, parts.slice(0, -1));

                            return FsSink.fromHandle(await uniqueFileHandle(directory, parts.at(-1)));
                        };
                    } else if (offer.files.length === 1) {
                        const handle = await window.showSaveFilePicker({ suggestedName: safeFileName(offer.files[0].name) });
                        openSink = () => FsSink.fromHandle(handle);
                    } else {
                        const directory = await window.showDirectoryPicker({ id: 'gui-file', mode: 'readwrite' });
                        openSink = (file) => uniqueFileHandle(directory, file.name).then((handle) => FsSink.fromHandle(handle));
                    }
                } else if (offer.folder) {
                    openSink = this.zipSinks(offer);
                } else if (this.storage === 'stream') {
                    openSink = (file) => StreamSink.open(file.name, file.size);
                } else if (this.storage === 'opfs') {
                    openSink = (file) => OpfsSink.open(file.name, file.type);
                } else {
                    openSink = (file) => Promise.resolve(new MemorySink(file.name, file.type));
                }
            } catch (error) {
                offer.busy = false;
                if (error?.name !== 'AbortError') {
                    this.message = 'Không mở được nơi lưu file. Thử lại, hoặc chọn thư mục khác.';
                }

                return;
            }

            session.accept(offer.id, openSink);
            if (offer.folder && this.storage !== 'fs') {
                for (const file of offer.files) {
                    this.patchFile(file.fid, { note: `trong ${offer.folder}.zip` });
                }
            }
            this.requestWakeLock();
        },

        /**
         * Trình duyệt không ghi thẳng vào thư mục được: gói cả thư mục thành một .zip trong lúc nhận.
         * Thêm một dòng "Tên.zip" vào danh sách, xong thì lưu được như file thường.
         */
        zipSinks(offer) {
            const zipFid = `zip-${offer.id}`;
            const zipName = `${offer.folder}.zip`;
            const zip = new ZipAssembler({
                files: offer.files,
                openTarget: (size) => {
                    if (this.storage === 'stream') {
                        return StreamSink.open(zipName, size);
                    }
                    if (this.storage === 'opfs') {
                        return OpfsSink.open(zipName, 'application/zip');
                    }

                    return Promise.resolve(new MemorySink(zipName, 'application/zip'));
                },
                onDone: (result) => this.patchFile(zipFid, { status: 'done', done: zip.length, result, note: '' }),
                onError: (error) => this.patchFile(zipFid, { status: 'failed', note: error.message }),
            });
            this.patchFile(zipFid, { direction: 'in', name: zipName, size: zip.length, status: 'zipping', done: 0, note: `Gói ${offer.files.length} file của thư mục` });

            const openSink = (file) => Promise.resolve(zip.sinkFor(file.fid));
            openSink.skip = (fid) => zip.skip(fid);

            return openSink;
        },

        reject(offer) {
            session.reject(offer.id);
        },

        async save(entry) {
            const file = entry.result?.file;
            if (! file) {
                return;
            }

            try {
                if (this.env.isIos && navigator.canShare?.({ files: [file] })) {
                    await navigator.share({ files: [file] });
                } else {
                    const url = URL.createObjectURL(file);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = file.name;
                    link.click();
                    setTimeout(() => URL.revokeObjectURL(url), 60000);
                }
                entry.saved = true;
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    this.message = 'Không lưu được file. Thử lại.';
                }
            }
        },

        // ----- Danh sách file -----

        cancel(entry) {
            entry.direction === 'out' ? session?.cancelOutgoing(entry.fid) : session?.cancelIncoming(entry.fid);
        },

        patchFile(fid, patch) {
            let entry = this.files.find((file) => file.fid === fid);

            if (! entry) {
                entry = { fid, direction: patch.direction, name: patch.name, size: patch.size, status: 'waiting', done: 0, speedText: '', etaText: '', note: '', result: null, saved: false, verified: null };
                this.files.unshift(entry);
                live.set(fid, { meter: new SpeedMeter(), done: 0 });
            }

            // Tiến trình đổi liên tục: gom lại, mỗi 300 ms mới vẽ một lần.
            if (Object.keys(patch).length === 1 && 'done' in patch) {
                live.get(fid).done = patch.done;

                return;
            }

            const { done, ...rest } = patch;
            Object.assign(entry, rest);
            if (done !== undefined) {
                live.get(fid).done = done;
                entry.done = done;
            }
            if (! ['sending', 'receiving', 'queued', 'waiting', 'paused'].includes(entry.status)) {
                entry.speedText = '';
                entry.etaText = '';
                this.updateWakeLock();
            }
        },

        flush() {
            for (const entry of this.files) {
                if (entry.status !== 'sending' && entry.status !== 'receiving') {
                    continue;
                }
                const state = live.get(entry.fid);
                state.meter.update(state.done);
                entry.done = state.done;
                const speed = state.meter.bytesPerSecond;
                entry.speedText = speed > 0 ? `${formatBytes(speed)}/giây` : '';
                entry.etaText = speed > 0 ? `còn ${formatDuration((entry.size - entry.done) / speed)}` : '';
            }
        },

        statusLabel(entry) {
            if (entry.status === 'done') {
                const label = entry.direction === 'out' ? 'Đã gửi xong' : 'Đã nhận xong';

                return entry.verified ? `${label} · ✓ giống hệt bản gốc` : label;
            }

            return STATUS_LABELS[entry.status] ?? entry.status;
        },

        percent(entry) {
            return entry.size > 0 ? Math.min(100, Math.floor((entry.done / entry.size) * 100)) : (entry.status === 'done' ? 100 : 0);
        },

        isActive(entry) {
            return ['waiting', 'queued', 'sending', 'receiving', 'paused'].includes(entry.status);
        },

        formatBytes,

        // ----- Giữ màn hình sáng khi đang truyền -----

        async requestWakeLock() {
            try {
                wakeLock ??= await navigator.wakeLock?.request('screen');
            } catch {
                wakeLock = null;
            }
        },

        updateWakeLock() {
            if (! this.files.some((entry) => this.isActive(entry))) {
                this.releaseWakeLock();
            }
        },

        releaseWakeLock() {
            wakeLock?.release().catch(() => {});
            wakeLock = null;
        },

        showError(message, reason = '') {
            this.phase = 'error';
            this.message = message;
            this.errorReason = reason;
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('fileTransfer', fileTransfer);
});
