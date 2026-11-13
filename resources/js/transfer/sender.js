import { readChunks, SendWindow } from './chunker.js';
import { createHasher } from './hashing.js';
import { BUFFER_HIGH, BUFFER_LOW, SEND_WINDOW } from './protocol.js';

/**
 * Đẩy một file qua DataChannel: đọc dần, dừng khi bộ đệm của kênh đầy hoặc bên nhận chưa ghi kịp
 * (cửa sổ gửi). RAM bên gửi chỉ chứa vài MB dù file bao nhiêu GB. Tính SHA-256 trong lúc đọc và
 * gửi kèm tin end để bên nhận so.
 *
 * Rớt mạng giữa chừng thì pause(); nối lại xong gọi run(kênh mới, offset) với offset là số byte bên
 * nhận đã ghi. Trạng thái băm tại mỗi ranh giới mảnh chưa được xác nhận được giữ lại (vài trăm
 * byte mỗi mảnh) để băm tiếp đúng từ offset đó.
 */
export class OutgoingFile {
    constructor({ file, fid, channel, chunkSize, onProgress = () => {}, window = SEND_WINDOW }) {
        this.file = file;
        this.fid = fid;
        this.channel = channel;
        this.chunkSize = chunkSize;
        this.onProgress = onProgress;
        this.windowSize = window;
        this.window = new SendWindow(window);
        this.cancelled = false;
        this.paused = false;
        this.hasher = null;
        this.checkpoints = new Map();
        this.sha256 = null;
    }

    ack(bytes) {
        this.window.ack(bytes);
        this.onProgress(this.window.acked);

        // Bên nhận đã ghi chắc tới đây: không bao giờ phải lùi về trước nữa.
        for (const offset of this.checkpoints.keys()) {
            if (offset < this.window.acked) {
                this.checkpoints.delete(offset);
            }
        }
    }

    cancel() {
        this.cancelled = true;
        this.window.abort(new Error('cancelled'));
    }

    /** Tạm dừng vì rớt mạng; run() trả về 'paused'. */
    pause() {
        this.paused = true;
        this.window.abort(new Error('paused'));
    }

    /** Vị trí gửi tiếp được (có trạng thái băm tại đó). */
    canResumeAt(offset) {
        return offset === 0 || this.checkpoints.has(offset);
    }

    /**
     * Gửi tin start, các mảnh từ $offset, tin end, rồi chờ bên nhận xác nhận đã ghi đủ. Trả về true
     * khi xong, false nếu bị hủy, 'paused' nếu tạm dừng; báo lỗi nếu mất kết nối.
     */
    async run(channel = this.channel, offset = 0) {
        this.channel = channel;
        this.paused = false;
        await this.prepareHasher(offset);
        this.window = new SendWindow(this.windowSize);
        this.window.sent = offset;
        this.window.acked = offset;
        this.onProgress(offset);

        const onClose = () => this.window.abort(new Error('channel-closed'));
        channel.bufferedAmountLowThreshold = BUFFER_LOW;
        channel.addEventListener('close', onClose);
        channel.send(JSON.stringify({ t: 'start', fid: this.fid, size: this.file.size, offset }));

        try {
            for await (const { data } of readChunks(this.file, { start: offset, chunkSize: this.chunkSize })) {
                if (this.cancelled || this.paused) {
                    return this.cancelled ? false : 'paused';
                }

                await this.window.wait(data.byteLength);

                if (channel.bufferedAmount > BUFFER_HIGH) {
                    await waitForLowBuffer(channel);
                }

                if (this.cancelled || this.paused) {
                    return this.cancelled ? false : 'paused';
                }
                if (channel.readyState !== 'open') {
                    throw new Error('channel-closed');
                }

                this.hasher.update(data);
                channel.send(data);
                this.window.add(data.byteLength);
                this.checkpoints.set(this.window.sent, this.hasher.save());
            }

            this.sha256 ??= this.hasher.hex();
            channel.send(JSON.stringify({ t: 'end', fid: this.fid, sha256: this.sha256 }));
            await this.window.drained();
        } catch (error) {
            if (this.cancelled) {
                return false;
            }
            if (this.paused) {
                return 'paused';
            }
            throw error;
        } finally {
            channel.removeEventListener('close', onClose);
        }

        return this.cancelled ? false : true;
    }

    async prepareHasher(offset) {
        this.hasher ??= await createHasher();

        if (offset === 0) {
            this.hasher = await createHasher();
            this.checkpoints = new Map([[0, this.hasher.save()]]);
            this.sha256 = null;

            return;
        }
        if (! this.checkpoints.has(offset)) {
            throw new Error('resume-offset-unknown');
        }

        this.hasher.load(this.checkpoints.get(offset));
        if (offset < this.file.size) {
            this.sha256 = null;
        }
    }
}

function waitForLowBuffer(channel) {
    return new Promise((resolve, reject) => {
        const onLow = () => {
            cleanup();
            resolve();
        };
        const onClose = () => {
            cleanup();
            reject(new Error('channel-closed'));
        };
        const cleanup = () => {
            channel.removeEventListener('bufferedamountlow', onLow);
            channel.removeEventListener('close', onClose);
        };

        channel.addEventListener('bufferedamountlow', onLow);
        channel.addEventListener('close', onClose);
    });
}
