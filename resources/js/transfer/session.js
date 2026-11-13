import { IncomingFile } from './chunker.js';
import { ACK_EVERY, CHUNK_SIZE, MAX_TEXT, parseMessage, randomId } from './protocol.js';
import { OutgoingFile } from './sender.js';
import { request, Signaling } from './signaling.js';
import { extractFingerprint, safetyCode } from './safety.js';
import { connectionKind } from './stats.js';
import { formatBytes } from './storage.js';

/** Thử nối thẳng trong ngần này; chưa được thì đi vòng qua máy chủ trung chuyển (nếu có). */
const DIRECT_TIMEOUT = 10000;

/** Thời gian chờ thêm khi đã đi vòng qua trung chuyển. */
const RELAY_TIMEOUT = 20000;

/** Chập chờn quá ngần này thì coi như rớt và nối lại. */
const UNSTABLE_TIMEOUT = 8000;

/** Chờ nối lại tối đa ngần này rồi mới báo mất kết nối hẳn. */
const RECONNECT_TIMEOUT = 120000;

/** Đang nối lại mà chưa được: máy gửi thử lại với kết nối mới sau ngần này. */
const RECONNECT_RETRY = 20000;

/** Đã nối: hỏi server ngần này một lần để giữ phòng (dùng khi cần nối lại). */
const KEEPALIVE_INTERVAL = 15000;

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
 * onOffer(offer), onOfferGone(id), onFile(fid, patch), onSafety(code).
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
        this.iceServers = room.iceServers ?? [];

        // Nối lại sau khi rớt mạng: mỗi lần tạo kết nối mới tăng gen, tin bắt tay của lần cũ bị bỏ.
        this.gen = 0;
        this.everConnected = false;
        this.reconnecting = false;
        this.reconnectDeadline = null;
        this.unstableTimer = null;
        this.outbox = [];

        // Gửi đi
        this.outgoing = new Map();
        this.sendQueue = [];
        this.sending = null;
        this.paused = null;
        this.waitingResume = false;

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
            onError: (error) => {
                // Đang nối thì phòng chỉ để dự phòng: mất phòng không làm hỏng kết nối đang chạy.
                if (this.channel?.readyState === 'open' && ! this.reconnecting) {
                    return;
                }
                this.reconnecting ? this.giveUp() : this.fail(error.message);
            },
        });

        if (this.room.role === 'receiver') {
            this.armConnectTimer();
        }
    }

    createPeer() {
        const pc = new RTCPeerConnection({ iceServers: this.iceServers });
        this.pc = pc;
        this.pendingCandidates = [];
        this.remoteDescriptionSet = false;

        pc.onicecandidate = ({ candidate }) => {
            if (candidate) {
                this.signal({ type: 'candidate', candidate: candidate.toJSON() }).catch(() => {});
            }
        };

        pc.onconnectionstatechange = () => {
            if (pc !== this.pc) {
                return;
            }
            const state = pc.connectionState;
            clearTimeout(this.unstableTimer);

            if (state === 'failed' && ! this.everConnected && ! this.usingRelay) {
                // Chưa từng nối được: thử đi vòng ngay, không chờ hết giờ.
                this.fallbackToRelay();
            } else if (state === 'failed' || state === 'closed') {
                this.everConnected ? this.reconnect() : this.lost();
            } else if (state === 'disconnected') {
                this.on.onState('unstable');
                // Chập chờn quá lâu thì coi như rớt và nối lại.
                this.unstableTimer = setTimeout(() => this.reconnect(), UNSTABLE_TIMEOUT);
            } else if (state === 'connected' && this.channel?.readyState === 'open') {
                this.on.onState('connected');
                this.refreshKind();
            }
        };

        if (this.room.role === 'sender') {
            this.attachChannel(pc.createDataChannel('xfer', { ordered: true }));
        } else {
            pc.ondatachannel = ({ channel }) => this.attachChannel(channel);
        }
    }

    /** Gửi tin bắt tay kèm số thứ tự kết nối hiện tại. */
    signal(payload) {
        return this.signaling.send({ ...payload, gen: this.gen });
    }

    async makeOffer(options = {}) {
        try {
            const offer = await this.pc.createOffer(options);
            await this.pc.setLocalDescription(offer);
            await this.signal({ type: 'description', description: this.pc.localDescription.toJSON() });
        } catch (error) {
            this.reconnecting ? null : this.fail(error.message ?? 'Không tạo được kết nối.');
        }
    }

    async handleSignal(payload) {
        const gen = Number.isSafeInteger(payload.gen) ? payload.gen : 0;

        if (gen < this.gen) {
            return;
        }
        if (gen > this.gen) {
            // Máy kia đã nối lại trước: dựng kết nối mới theo.
            this.startReconnect(gen);

            if (this.room.role === 'sender' && payload.type === 'reconnect') {
                await this.makeOffer();
            }
        }
        if (payload.type === 'reconnect') {
            return;
        }

        if (payload.type === 'description') {
            await this.pc.setRemoteDescription(payload.description);
            this.remoteDescriptionSet = true;

            for (const candidate of this.pendingCandidates.splice(0)) {
                await this.pc.addIceCandidate(candidate).catch(() => {});
            }

            if (payload.description.type === 'offer') {
                const answer = await this.pc.createAnswer();
                await this.pc.setLocalDescription(answer);
                await this.signal({ type: 'description', description: this.pc.localDescription.toJSON() });
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
     * Không nối thẳng được: xin cấu hình TURN từ server rồi bắt tay lại (ICE restart) để đi vòng.
     * Server giới hạn số lượt; hết lượt thì báo rõ và gợi ý dùng chung wifi.
     */
    async fallbackToRelay() {
        if (this.usingRelay || this.closed) {
            return;
        }
        this.usingRelay = true;
        clearTimeout(this.connectTimer);

        if (! this.room.turnAvailable) {
            if (this.reconnecting) {
                // Đang nối lại: cứ chờ đường thẳng tới hạn chót.
                return;
            }
            // Hết hạn mức trung chuyển của tháng (turnNotice) hoặc chưa bật: chỉ có kết nối thẳng.
            this.fail(this.room.turnNotice ?? NO_DIRECT_MESSAGE, this.room.turnNotice ? 'turn_monthly_limit' : 'no_direct');

            return;
        }

        if (! this.reconnecting) {
            this.on.onState('relaying');
        }

        try {
            const data = await request((...args) => fetch(...args), `${this.apiBase}/${this.room.code}/turn`, { method: 'POST', token: this.room.token });
            this.room.maxRelayBytes = data.maxRelayBytes;
            this.iceServers = data.iceServers;
            this.pc.setConfiguration({ iceServers: data.iceServers });

            if (this.room.role === 'sender') {
                await this.makeOffer({ iceRestart: true });
            }
        } catch (error) {
            if (error.reason === 'turn_monthly_limit') {
                this.room.turnAvailable = false;
                this.room.turnNotice = error.message;
            }
            if (! this.reconnecting) {
                this.fail(error.message ?? NO_DIRECT_MESSAGE, error.reason ?? 'relay_failed');
            }

            return;
        }

        if (! this.reconnecting) {
            this.connectTimer = setTimeout(() => {
                if (this.channel?.readyState !== 'open') {
                    this.fail(NO_DIRECT_MESSAGE, 'connect_timeout');
                }
            }, RELAY_TIMEOUT);
        }
    }

    // ----- Nối lại khi rớt mạng -----

    /**
     * Phát hiện rớt mạng ở máy này: dựng kết nối mới. Máy gửi chủ động bắt tay lại; máy nhận báo
     * qua server để máy gửi biết (có khi chỉ một bên thấy rớt).
     */
    reconnect() {
        if (this.closed || this.reconnecting) {
            return;
        }
        this.startReconnect(this.gen + 1);

        if (this.room.role === 'sender') {
            this.makeOffer();
        } else {
            this.signal({ type: 'reconnect' }).catch(() => {});
        }
    }

    startReconnect(gen) {
        const first = ! this.reconnecting;
        this.reconnecting = true;
        this.gen = gen;
        clearTimeout(this.unstableTimer);
        clearTimeout(this.connectTimer);
        clearInterval(this.kindTimer);
        this.teardownPeer();

        if (first) {
            this.on.onState('reconnecting');
            this.pauseTransfers();
            this.reconnectDeadline = setTimeout(() => this.giveUp(), RECONNECT_TIMEOUT);

            // Tin bắt tay có thể rơi: máy gửi thử lại với kết nối mới tới khi nối được.
            if (this.room.role === 'sender') {
                this.retryTimer = setInterval(() => {
                    if (this.reconnecting && this.channel?.readyState !== 'open') {
                        this.startReconnect(this.gen + 1);
                        this.makeOffer();
                    }
                }, RECONNECT_RETRY);
            }
        }

        // Hỏi server nhanh lại để nhận tin bắt tay mới.
        this.signaling.restart();
        this.signaling.setPace(1000);
        this.createPeer();

        // Đường thẳng không lên thì thử trung chuyển (nếu trước đó chưa dùng).
        this.connectTimer = setTimeout(() => {
            if (this.channel?.readyState !== 'open' && ! this.usingRelay) {
                this.fallbackToRelay();
            }
        }, DIRECT_TIMEOUT);
    }

    teardownPeer() {
        const { pc, channel } = this;
        this.pc = null;
        this.channel = null;
        if (channel) {
            channel.onopen = channel.onclose = channel.onmessage = null;
        }
        if (pc) {
            pc.onicecandidate = pc.onconnectionstatechange = pc.ondatachannel = null;
            pc.close();
        }
    }

    /** Dừng file đang gửi/nhận nhưng giữ nguyên tiến độ để gửi tiếp. */
    pauseTransfers() {
        if (this.sending) {
            this.paused = { fid: this.sending.fid, outgoing: this.sending };
            this.sending.pause();
        }
        this.waitingResume = true;

        for (const [fid, entry] of this.outgoing) {
            if (['sending', 'queued'].includes(entry.status)) {
                this.on.onFile(fid, { status: 'paused' });
            }
        }
        if (this.receiving) {
            this.on.onFile(this.receiving.fid, { status: 'paused' });
        }
    }

    /** Nối lại xong: bên nhận báo đã ghi tới đâu để bên gửi gửi tiếp từ đó. */
    async resumeAfterReconnect() {
        clearTimeout(this.reconnectDeadline);
        clearInterval(this.retryTimer);
        this.reconnecting = false;
        this.waitingResume = true;
        this.dropping = false;

        let receiving = null;
        if (this.receiving) {
            receiving = { fid: this.receiving.fid, offset: await this.receiving.incoming.settle() };
            this.on.onFile(this.receiving.fid, { status: 'receiving' });
        }
        this.sendControl({ t: 'resume', receiving });

        for (const message of this.outbox.splice(0)) {
            this.sendControl(message);
        }
    }

    /** Máy kia báo đã ghi tới đâu: xếp file đang dở lên đầu hàng đợi, gửi tiếp từ đúng byte đó. */
    handleResume(message) {
        const receiving = message.receiving && typeof message.receiving.fid === 'string' && Number.isSafeInteger(message.receiving.offset) ? message.receiving : null;
        const paused = this.paused;
        this.paused = null;

        if (paused) {
            const entry = this.outgoing.get(paused.fid);
            if (entry && ! ['cancelled', 'failed', 'done'].includes(entry.status)) {
                const offset = receiving?.fid === paused.fid ? receiving.offset : 0;
                entry.resume = { outgoing: paused.outgoing, offset };
                entry.status = 'queued';
                this.sendQueue.unshift(paused.fid);
            }
        } else if (receiving) {
            // Bên gửi đã gửi xong nhưng tin "end" bị rơi: gửi lại.
            const entry = this.outgoing.get(receiving.fid);
            if (entry?.status === 'done' && entry.sha256) {
                this.sendControl({ t: 'start', fid: receiving.fid, size: entry.file.size, offset: entry.file.size });
                this.sendControl({ t: 'end', fid: receiving.fid, sha256: entry.sha256 });
            }
        }

        this.waitingResume = false;
        for (const fid of this.sendQueue) {
            this.on.onFile(fid, { status: 'queued' });
        }
        this.pumpQueue();
    }

    /** Hết hạn chờ nối lại: dừng hẳn. */
    giveUp() {
        this.reconnecting = false;
        this.lost('Mất kết nối với máy kia và không nối lại được. Kiểm tra mạng trên cả hai máy rồi tạo mã mới.');
    }

    attachChannel(channel) {
        this.channel = channel;
        channel.binaryType = 'arraybuffer';

        channel.onopen = () => {
            clearTimeout(this.connectTimer);
            const isReconnect = this.everConnected;
            this.everConnected = true;
            this.on.onState('connected');
            // Đã nối: chỉ hỏi server thưa thớt để giữ phòng, dùng khi cần nối lại.
            this.signaling.setPace(KEEPALIVE_INTERVAL);
            this.sendControl({ t: 'hello', name: this.me.name, device: this.me.device });
            this.refreshKind();
            clearInterval(this.kindTimer);
            this.kindTimer = setInterval(() => this.refreshKind(), 5000);
            this.showSafetyCode();

            if (isReconnect) {
                this.resumeAfterReconnect();
            }
        };

        channel.onclose = () => {
            if (channel === this.channel) {
                this.reconnect();
            }
        };
        channel.onmessage = ({ data }) => (typeof data === 'string' ? this.handleControl(data) : this.handleChunk(data));
    }

    /** Mã xác minh từ khóa mã hóa của hai máy (xem safety.js). */
    async showSafetyCode() {
        const mine = extractFingerprint(this.pc?.localDescription?.sdp);
        const theirs = extractFingerprint(this.pc?.remoteDescription?.sdp);

        if (mine && theirs) {
            this.on.onSafety?.(await safetyCode(mine, theirs).catch(() => null));
        }
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
        } else if (this.reconnecting) {
            // Đang nối lại: giữ tin (đồng ý nhận, hủy, văn bản…) gửi khi nối xong.
            this.outbox.push(message);
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
        if (this.sending || this.sendQueue.length === 0 || this.reconnecting || this.waitingResume || this.channel?.readyState !== 'open') {
            return;
        }

        const fid = this.sendQueue.shift();
        const entry = this.outgoing.get(fid);
        const resume = entry.resume ?? null;
        entry.resume = null;
        entry.status = 'sending';
        entry.started = true;
        this.on.onFile(fid, { status: 'sending' });

        let outgoing = resume?.outgoing;
        let offset = resume?.offset ?? 0;
        if (outgoing && ! outgoing.canResumeAt(offset)) {
            // Không lùi được đúng chỗ (hiếm): gửi lại từ đầu, bên nhận sẽ báo lỗi nếu đã ghi dở.
            offset = 0;
        }
        outgoing ??= new OutgoingFile({
            file: entry.file,
            fid,
            channel: this.channel,
            chunkSize: this.maxChunk,
            onProgress: (done) => this.on.onFile(fid, { done }),
        });
        outgoing.chunkSize = this.maxChunk;
        this.sending = outgoing;

        try {
            const result = await outgoing.run(this.channel, offset);
            if (result === true) {
                entry.status = 'done';
                entry.sha256 = outgoing.sha256;
                this.on.onFile(fid, { status: 'done', done: entry.file.size });
            }
        } catch {
            if (! this.reconnecting && ! this.closed) {
                entry.status = 'failed';
                this.on.onFile(fid, { status: 'failed', note: 'Mất kết nối khi đang gửi' });
            } else if (this.reconnecting && ! this.paused) {
                // Kênh đóng trước khi kịp tạm dừng: vẫn giữ để gửi tiếp.
                this.paused = { fid, outgoing };
                this.on.onFile(fid, { status: 'paused' });
            }
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
        const offset = Number.isSafeInteger(message.offset) ? message.offset : 0;

        if (! accepted || accepted.finished || message.size !== accepted.size) {
            // Không đồng ý file này: bỏ mọi mảnh tới khi có file khác.
            this.dropping = true;
            this.receiving = null;

            return;
        }

        if (this.receiving?.fid === message.fid) {
            // Gửi tiếp sau khi nối lại: phải đúng chỗ đã ghi.
            if (offset !== this.receiving.incoming.written) {
                this.dropping = true;
                this.cancelIncoming(message.fid);
                this.on.onFile(message.fid, { status: 'failed', note: 'Không gửi tiếp được đúng chỗ, hãy gửi lại' });

                return;
            }
            this.dropping = false;
            this.on.onFile(message.fid, { status: 'receiving' });

            return;
        }

        if (offset !== 0) {
            this.dropping = true;

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
            const result = await current.incoming.finish(typeof message.sha256 === 'string' ? message.sha256 : null);
            accepted.finished = true;
            this.sendControl({ t: 'done', fid: message.fid, verified: result.verified });

            if (result.verified === false) {
                this.on.onFile(message.fid, { status: 'failed', done: accepted.size, verified: false, note: 'File nhận được không khớp bản gốc, hãy xóa và gửi lại' });
            } else {
                this.on.onFile(message.fid, { status: 'done', done: accepted.size, verified: result.verified, result });
            }
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
            case 'done': {
                // Bên nhận báo kết quả so mã SHA-256.
                const entry = this.outgoing.get(message.fid);
                if (entry && typeof message.verified === 'boolean') {
                    entry.verified = message.verified;
                    if (! message.verified) {
                        entry.status = 'failed';
                    }
                    this.on.onFile(message.fid, message.verified
                        ? { verified: true }
                        : { status: 'failed', verified: false, note: 'Máy kia nhận được file không khớp bản gốc, hãy gửi lại' });
                }
                break;
            }
            case 'cancel':
                this.peerCancelled(message.fid);
                break;
            case 'bye':
                this.lost('Máy kia đã thoát.');
                break;
            case 'resume':
                this.handleResume(message);
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
        return Boolean(this.sending || this.receiving || this.sendQueue.length || this.paused || this.reconnecting);
    }

    lost(message = 'Mất kết nối với máy kia.') {
        if (this.closed) {
            return;
        }
        this.closed = true;
        clearTimeout(this.connectTimer);
        clearTimeout(this.reconnectDeadline);
        clearInterval(this.retryTimer);
        clearTimeout(this.unstableTimer);
        clearInterval(this.kindTimer);
        this.signaling.close();
        this.sending?.cancel();
        this.receiving?.incoming.abort();

        for (const [fid, entry] of this.outgoing) {
            if (['waiting', 'queued', 'sending', 'paused'].includes(entry.status)) {
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
        this.teardownPeer();
    }

    fail(message, reason = 'error') {
        if (this.closed) {
            return;
        }
        this.closed = true;
        clearTimeout(this.connectTimer);
        clearTimeout(this.reconnectDeadline);
        clearInterval(this.retryTimer);
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
