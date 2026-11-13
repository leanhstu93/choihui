import { IncomingFile } from './chunker.js';
import { ACK_EVERY, CHUNK_SIZE, MAX_TEXT, parseMessage, randomId } from './protocol.js';
import { OutgoingFile } from './sender.js';
import { request, Signaling } from './signaling.js';
import { connectionKind } from './stats.js';
import { formatBytes } from './storage.js';

/** Thử nối thẳng trong ngần này; chưa được thì đi vòng qua máy chủ trung chuyển (nếu có). */
const DIRECT_TIMEOUT = 10000;

/** Thời gian chờ thêm khi đã đi vòng qua trung chuyển. */
const RELAY_TIMEOUT = 20000;

/** Chờ mật khẩu trung chuyển mới tạo lan truyền tối đa ngần này giây. */
const MAX_RELAY_WAIT = 150;

const NO_DIRECT_MESSAGE = 'Không kết nối được hai máy với nhau. Mạng có thể đang chặn kết nối trực tiếp: hãy cho hai máy dùng chung một wifi rồi thử lại.';

/**
 * Câu báo khi một lượt gửi qua trung chuyển vượt giới hạn, hoặc null nếu gửi được.
 */
export function relayLimitMessage(total, relayed, maxRelayBytes) {
    if (! relayed || ! maxRelayBytes || total <= maxRelayBytes) {
        return null;
    }

    return `Hai máy đang kết nối qua máy chủ trung chuyển nên mỗi lượt gửi tối đa ${formatBytes(maxRelayBytes)} (lượt này ${formatBytes(total)}). Hãy cho hai máy dùng chung một wifi để gửi trực tiếp, không giới hạn dung lượng, rồi kết nối lại.`;
}

/**
 * Một phiên gửi file giữa hai thiết bị: bắt tay WebRTC qua server mai mối, rồi mọi thứ đi trên một
 * DataChannel mã hóa (DTLS) giữa hai máy. Không gửi byte file nào khi bên kia chưa bấm Nhận.
 *
 * Báo ra ngoài qua các hàm on*: onState(phase, detail), onPeer(info), onKind(kind), onText(text),
 * onOffer(offer), onOfferGone(id), onFile(fid, patch).
 */
export class TransferSession {
    constructor({ room, apiBase, me, handlers }) {
        this.room = room;
        this.me = me;
        this.on = handlers;
        this.signaling = new Signaling({ baseUrl: apiBase, code: room.code, token: room.token });
        this.pc = null;
        this.channel = null;
        this.pendingCandidates = [];
        this.remoteDescriptionSet = false;
        this.connectTimer = null;
        this.closed = false;
        this.usingRelay = false;
        this.apiBase = apiBase;
        this.kind = null;

        // Gửi đi
        this.outgoing = new Map();
        this.sendQueue = [];
        this.sending = null;

        // Nhận về
        this.offers = new Map();
        this.accepted = new Map();
        this.receiving = null;
        this.dropping = false;
    }

    start() {
        this.on.onState(this.room.role === 'sender' && ! this.room.peerJoined ? 'waiting' : 'connecting');
        this.createPeer();

        this.signaling.start({
            onSignal: (payload) => this.handleSignal(payload),
            onPeer: () => {
                if (this.room.role === 'sender') {
                    this.on.onState('connecting');
                    this.makeOffer();
                }
                this.armConnectTimer();
            },
            onError: (error) => this.fail(error.message),
        });

        if (this.room.role === 'receiver') {
            this.armConnectTimer();
        }
    }

    createPeer() {
        this.pc = new RTCPeerConnection({ iceServers: this.room.iceServers ?? [] });

        this.pc.onicecandidate = ({ candidate }) => {
            if (candidate) {
                this.signaling.send({ type: 'candidate', candidate: candidate.toJSON() }).catch(() => {});
            }
        };

        this.pc.onconnectionstatechange = () => {
            const state = this.pc.connectionState;
            if (state === 'failed' && this.channel?.readyState !== 'open' && ! this.usingRelay) {
                // Chưa từng nối được: thử đi vòng ngay, không chờ hết giờ.
                this.fallbackToRelay();
            } else if (state === 'failed' || state === 'closed') {
                this.lost();
            } else if (state === 'disconnected') {
                this.on.onState('unstable');
            } else if (state === 'connected' && this.channel?.readyState === 'open') {
                this.on.onState('connected');
                this.refreshKind();
            }
        };

        if (this.room.role === 'sender') {
            this.attachChannel(this.pc.createDataChannel('xfer', { ordered: true }));
        } else {
            this.pc.ondatachannel = ({ channel }) => this.attachChannel(channel);
        }
    }

    async makeOffer() {
        try {
            const offer = await this.pc.createOffer();
            await this.pc.setLocalDescription(offer);
            await this.signaling.send({ type: 'description', description: this.pc.localDescription.toJSON() });
        } catch (error) {
            this.fail(error.message ?? 'Không tạo được kết nối.');
        }
    }

    async handleSignal(payload) {
        if (payload.type === 'description') {
            await this.pc.setRemoteDescription(payload.description);
            this.remoteDescriptionSet = true;

            for (const candidate of this.pendingCandidates.splice(0)) {
                await this.pc.addIceCandidate(candidate).catch(() => {});
            }

            if (payload.description.type === 'offer') {
                const answer = await this.pc.createAnswer();
                await this.pc.setLocalDescription(answer);
                await this.signaling.send({ type: 'description', description: this.pc.localDescription.toJSON() });
            }
        } else if (payload.type === 'candidate') {
            if (this.remoteDescriptionSet) {
                await this.pc.addIceCandidate(payload.candidate).catch(() => {});
            } else {
                this.pendingCandidates.push(payload.candidate);
            }
        }
    }

    armConnectTimer() {
        clearTimeout(this.connectTimer);
        this.connectTimer = setTimeout(() => {
            if (this.channel?.readyState !== 'open') {
                this.fallbackToRelay();
            }
        }, DIRECT_TIMEOUT);
    }

    /**
     * Không nối thẳng được: xin mật khẩu TURN tạm thời rồi bắt tay lại (ICE restart) để đi vòng.
     * Server giới hạn số lượt; hết lượt thì báo rõ và gợi ý dùng chung wifi.
     */
    async fallbackToRelay() {
        if (this.usingRelay || this.closed) {
            return;
        }
        this.usingRelay = true;
        clearTimeout(this.connectTimer);

        if (! this.room.turnAvailable) {
            // Hết hạn mức trung chuyển của tháng (turnNotice) hoặc chưa bật: chỉ có kết nối thẳng.
            this.fail(this.room.turnNotice ?? NO_DIRECT_MESSAGE, this.room.turnNotice ? 'turn_monthly_limit' : 'no_direct');

            return;
        }

        this.on.onState('relaying');
        let readyIn = 0;

        try {
            const data = await request((...args) => fetch(...args), `${this.apiBase}/${this.room.code}/turn`, { method: 'POST', token: this.room.token });
            this.room.maxRelayBytes = data.maxRelayBytes;
            readyIn = Math.min(MAX_RELAY_WAIT, Math.max(0, Number(data.readyIn) || 0));

            if (readyIn > 0) {
                // Mật khẩu vừa tạo, các máy chủ trung chuyển chưa nhận: chờ rồi mới bắt tay lại.
                this.on.onState('relaying', `Đang chuẩn bị máy chủ trung chuyển, khoảng ${readyIn} giây nữa…`);
                await new Promise((resolve) => setTimeout(resolve, readyIn * 1000));
                if (this.closed || this.channel?.readyState === 'open') {
                    return;
                }
                this.on.onState('relaying');
            }

            this.pc.setConfiguration({ iceServers: data.iceServers });

            if (this.room.role === 'sender') {
                const offer = await this.pc.createOffer({ iceRestart: true });
                await this.pc.setLocalDescription(offer);
                await this.signaling.send({ type: 'description', description: this.pc.localDescription.toJSON() });
            }
        } catch (error) {
            if (error.reason === 'turn_monthly_limit') {
                this.room.turnAvailable = false;
                this.room.turnNotice = error.message;
            }
            this.fail(error.message ?? NO_DIRECT_MESSAGE, error.reason ?? 'relay_failed');

            return;
        }

        this.connectTimer = setTimeout(() => {
            if (this.channel?.readyState !== 'open') {
                this.fail(NO_DIRECT_MESSAGE, 'connect_timeout');
            }
        }, RELAY_TIMEOUT);
    }

    attachChannel(channel) {
        this.channel = channel;
        channel.binaryType = 'arraybuffer';

        channel.onopen = () => {
            clearTimeout(this.connectTimer);
            this.on.onState('connected');
            // Đã nối thẳng: không cần server nữa. Máy tạo phòng xóa phòng, máy kia chỉ thôi hỏi.
            this.room.role === 'sender' ? this.signaling.close() : this.signaling.stop();
            this.sendControl({ t: 'hello', name: this.me.name, device: this.me.device });
            this.refreshKind();
            this.kindTimer = setInterval(() => this.refreshKind(), 5000);
        };

        channel.onclose = () => this.lost();
        channel.onmessage = ({ data }) => (typeof data === 'string' ? this.handleControl(data) : this.handleChunk(data));
    }

    async refreshKind() {
        try {
            const stats = await this.pc.getStats();
            const kind = connectionKind([...stats.values()]);
            if (kind) {
                this.kind = kind;
                this.on.onKind(kind);
            }
        } catch {
            // Bỏ qua: lần sau đọc lại.
        }
    }

    /** Đang đi vòng qua trung chuyển (chưa đọc được loại kết nối thì dựa vào việc đã xin TURN). */
    get isRelayed() {
        return this.kind === 'relay' || (this.kind === null && this.usingRelay);
    }

    get maxChunk() {
        const limit = this.pc?.sctp?.maxMessageSize;

        return limit && limit > 0 ? Math.min(CHUNK_SIZE, limit) : CHUNK_SIZE;
    }

    sendControl(message) {
        if (this.channel?.readyState === 'open') {
            this.channel.send(JSON.stringify(message));
        }
    }

    // ----- Văn bản -----

    sendText(text) {
        const trimmed = text.slice(0, MAX_TEXT);
        this.sendControl({ t: 'text', text: trimmed });

        return trimmed;
    }

    // ----- Gửi file -----

    /**
     * Mời bên kia nhận các file này. Chưa gửi byte nào cho tới khi bên kia bấm Nhận.
     * Đang đi vòng qua trung chuyển thì mỗi lượt gửi chỉ được tới maxRelayBytes: trả về câu báo lỗi.
     */
    offerFiles(files) {
        const total = files.reduce((sum, file) => sum + file.size, 0);
        const message = relayLimitMessage(total, this.isRelayed, this.room.maxRelayBytes);

        if (message) {
            return message;
        }

        const id = randomId();
        const list = files.map((file) => ({ fid: randomId(), file }));

        for (const { fid, file } of list) {
            this.outgoing.set(fid, { file, offerId: id, status: 'waiting' });
            this.on.onFile(fid, { direction: 'out', name: file.name, size: file.size, status: 'waiting', done: 0 });
        }

        this.sendControl({ t: 'offer', id, files: list.map(({ fid, file }) => ({ fid, name: file.name, size: file.size, type: file.type })) });

        return null;
    }

    cancelOutgoing(fid) {
        const entry = this.outgoing.get(fid);
        if (! entry || ['done', 'cancelled', 'rejected', 'failed'].includes(entry.status)) {
            return;
        }

        // Chưa được đồng ý: rút khỏi lời mời. Đã đồng ý (đang chờ lượt / đang gửi): hủy.
        const notAcceptedYet = entry.status === 'waiting';

        if (this.sending?.fid === fid) {
            this.sending.cancel();
        }
        this.sendQueue = this.sendQueue.filter((queued) => queued !== fid);
        entry.status = 'cancelled';
        this.on.onFile(fid, { status: 'cancelled', note: 'Bạn đã hủy' });
        this.sendControl(notAcceptedYet ? { t: 'withdraw', id: entry.offerId, fid } : { t: 'cancel', fid });
    }

    async pumpQueue() {
        if (this.sending || this.sendQueue.length === 0) {
            return;
        }

        const fid = this.sendQueue.shift();
        const entry = this.outgoing.get(fid);
        entry.status = 'sending';
        entry.started = true;
        this.on.onFile(fid, { status: 'sending' });

        const outgoing = new OutgoingFile({
            file: entry.file,
            fid,
            channel: this.channel,
            chunkSize: this.maxChunk,
            onProgress: (done) => this.on.onFile(fid, { done }),
        });
        this.sending = outgoing;

        try {
            const finished = await outgoing.run();
            if (finished) {
                entry.status = 'done';
                this.on.onFile(fid, { status: 'done', done: entry.file.size });
            }
        } catch {
            entry.status = 'failed';
            this.on.onFile(fid, { status: 'failed', note: 'Mất kết nối khi đang gửi' });
        } finally {
            this.sending = null;
            this.pumpQueue();
        }
    }

    // ----- Nhận file -----

    /**
     * Đồng ý nhận một lời mời. $openSink(file) trả về Promise của sink cho từng file; gọi lúc file
     * bắt đầu đến (đã có quyền ghi nếu cần, vì người dùng vừa bấm Nhận).
     */
    accept(offerId, openSink) {
        const offer = this.offers.get(offerId);
        if (! offer) {
            return;
        }

        for (const file of offer.files) {
            this.accepted.set(file.fid, { ...file, openSink });
            this.on.onFile(file.fid, { direction: 'in', name: file.name, size: file.size, status: 'queued', done: 0 });
        }
        this.offers.delete(offerId);
        this.on.onOfferGone(offerId);
        this.sendControl({ t: 'accept', id: offerId, fids: offer.files.map((file) => file.fid) });
    }

    reject(offerId) {
        this.offers.delete(offerId);
        this.on.onOfferGone(offerId);
        this.sendControl({ t: 'reject', id: offerId });
    }

    cancelIncoming(fid) {
        const accepted = this.accepted.get(fid);
        if (! accepted || accepted.finished) {
            return;
        }

        accepted.finished = true;
        if (this.receiving?.fid === fid) {
            this.receiving.incoming.abort();
            this.receiving = null;
            this.dropping = true;
        }
        this.on.onFile(fid, { status: 'cancelled', note: 'Bạn đã hủy' });
        this.sendControl({ t: 'cancel', fid });
    }

    handleChunk(data) {
        if (this.dropping || ! this.receiving) {
            return;
        }

        this.receiving.incoming.push(data);
    }

    startReceiving(message) {
        const accepted = this.accepted.get(message.fid);

        if (! accepted || accepted.finished || message.size !== accepted.size) {
            // Không đồng ý file này: bỏ mọi mảnh tới khi có file khác.
            this.dropping = true;
            this.receiving = null;

            return;
        }

        this.dropping = false;
        const sinkPromise = accepted.openSink(accepted);
        // Mảnh có thể đến trước khi mở xong file: ghi lần lượt sau khi sink sẵn sàng.
        const lazySink = {
            write: async (chunk) => (await sinkPromise).write(chunk),
            close: async () => (await sinkPromise).close(),
            abort: async () => (await sinkPromise.catch(() => null))?.abort?.(),
        };

        this.on.onFile(message.fid, { status: 'receiving' });
        this.receiving = {
            fid: message.fid,
            incoming: new IncomingFile({
                size: accepted.size,
                sink: lazySink,
                ackEvery: ACK_EVERY,
                onAck: (written) => this.sendControl({ t: 'ack', fid: message.fid, bytes: written }),
                onProgress: (done) => this.on.onFile(message.fid, { done }),
            }),
        };
    }

    async finishReceiving(message) {
        const current = this.receiving;
        if (! current || current.fid !== message.fid) {
            return;
        }

        this.receiving = null;
        const accepted = this.accepted.get(message.fid);

        try {
            const result = await current.incoming.finish();
            accepted.finished = true;
            this.sendControl({ t: 'done', fid: message.fid });
            this.on.onFile(message.fid, { status: 'done', done: accepted.size, result });
        } catch (error) {
            accepted.finished = true;
            this.sendControl({ t: 'cancel', fid: message.fid });
            this.on.onFile(message.fid, { status: 'failed', note: error.message ?? 'Không ghi được file' });
        }
    }

    // ----- Tin điều khiển -----

    handleControl(text) {
        const message = parseMessage(text);
        if (! message) {
            return;
        }

        switch (message.t) {
            case 'hello':
                this.on.onPeer({ name: String(message.name ?? 'Máy kia').slice(0, 40), device: String(message.device ?? '').slice(0, 40) });
                break;
            case 'text':
                this.on.onText(message.text);
                break;
            case 'offer': {
                const offer = { id: message.id, files: message.files.map((file) => ({ fid: file.fid, name: String(file.name), size: file.size, type: String(file.type ?? '') })) };
                offer.totalSize = offer.files.reduce((sum, file) => sum + file.size, 0);
                this.offers.set(offer.id, offer);
                this.on.onOffer(offer);
                break;
            }
            case 'accept':
                for (const fid of message.fids ?? []) {
                    const entry = this.outgoing.get(fid);
                    if (entry?.status === 'waiting') {
                        entry.status = 'queued';
                        this.sendQueue.push(fid);
                        this.on.onFile(fid, { status: 'queued' });
                    }
                }
                this.pumpQueue();
                break;
            case 'reject':
                for (const [fid, entry] of this.outgoing) {
                    if (entry.offerId === message.id && entry.status === 'waiting') {
                        entry.status = 'rejected';
                        this.on.onFile(fid, { status: 'rejected', note: 'Máy kia đã từ chối' });
                    }
                }
                break;
            case 'withdraw': {
                const offer = this.offers.get(message.id);
                if (offer) {
                    offer.files = offer.files.filter((file) => file.fid !== message.fid);
                    offer.totalSize = offer.files.reduce((sum, file) => sum + file.size, 0);
                    if (offer.files.length === 0) {
                        this.offers.delete(message.id);
                        this.on.onOfferGone(message.id);
                    } else {
                        this.on.onOffer(offer);
                    }
                }
                break;
            }
            case 'start':
                this.startReceiving(message);
                break;
            case 'end':
                this.finishReceiving(message);
                break;
            case 'ack':
                if (this.sending?.fid === message.fid && Number.isSafeInteger(message.bytes)) {
                    this.sending.ack(message.bytes);
                }
                break;
            case 'cancel':
                this.peerCancelled(message.fid);
                break;
            case 'bye':
                this.lost('Máy kia đã thoát.');
                break;
            default:
                break;
        }
    }

    peerCancelled(fid) {
        const outgoing = this.outgoing.get(fid);
        if (outgoing && ! ['done', 'cancelled', 'rejected', 'failed'].includes(outgoing.status)) {
            if (this.sending?.fid === fid) {
                this.sending.cancel();
            }
            this.sendQueue = this.sendQueue.filter((queued) => queued !== fid);
            outgoing.status = 'cancelled';
            this.on.onFile(fid, { status: 'cancelled', note: 'Máy kia đã hủy' });
        }

        const accepted = this.accepted.get(fid);
        if (accepted && ! accepted.finished) {
            accepted.finished = true;
            if (this.receiving?.fid === fid) {
                this.receiving.incoming.abort();
                this.receiving = null;
                this.dropping = true;
            }
            this.on.onFile(fid, { status: 'cancelled', note: 'Máy gửi đã hủy' });
        }
    }

    // ----- Kết thúc -----

    get busy() {
        return Boolean(this.sending || this.receiving || this.sendQueue.length);
    }

    lost(message = 'Mất kết nối với máy kia.') {
        if (this.closed) {
            return;
        }
        this.closed = true;
        clearTimeout(this.connectTimer);
        clearInterval(this.kindTimer);
        this.signaling.stop();
        this.sending?.cancel();
        this.receiving?.incoming.abort();

        for (const [fid, entry] of this.outgoing) {
            if (['waiting', 'queued', 'sending'].includes(entry.status)) {
                entry.status = 'failed';
                this.on.onFile(fid, { status: 'failed', note: 'Mất kết nối' });
            }
        }
        for (const [fid, accepted] of this.accepted) {
            if (! accepted.finished) {
                accepted.finished = true;
                this.on.onFile(fid, { status: 'failed', note: 'Mất kết nối' });
            }
        }

        this.on.onState('disconnected', message);
        this.pc?.close();
    }

    fail(message, reason = 'error') {
        if (this.closed) {
            return;
        }
        this.closed = true;
        clearTimeout(this.connectTimer);
        this.signaling.close();
        this.pc?.close();
        this.on.onState('error', message, reason);
    }

    /** Người dùng bấm Ngắt kết nối. */
    leave() {
        this.sendControl({ t: 'bye' });
        this.signaling.close();
        this.lost('Bạn đã ngắt kết nối.');
    }
}
