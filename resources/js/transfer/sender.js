import { readChunks, SendWindow } from './chunker.js';
import { BUFFER_HIGH, BUFFER_LOW, SEND_WINDOW } from './protocol.js';

/**
 * Đẩy một file qua DataChannel: đọc dần, dừng khi bộ đệm của kênh đầy hoặc bên nhận chưa ghi kịp
 * (cửa sổ gửi). RAM bên gửi chỉ chứa vài MB dù file bao nhiêu GB.
 */
export class OutgoingFile {
    constructor({ file, fid, channel, chunkSize, onProgress = () => {}, window = SEND_WINDOW }) {
        this.file = file;
        this.fid = fid;
        this.channel = channel;
        this.chunkSize = chunkSize;
        this.onProgress = onProgress;
        this.window = new SendWindow(window);
        this.cancelled = false;
    }

    ack(bytes) {
        this.window.ack(bytes);
        this.onProgress(this.window.acked);
    }

    cancel() {
        this.cancelled = true;
        this.window.abort(new Error('cancelled'));
    }

    /**
     * Gửi tin start, toàn bộ mảnh, tin end, rồi chờ bên nhận xác nhận đã ghi đủ. Trả về false nếu
     * bị hủy; báo lỗi nếu mất kết nối trước khi bên nhận ghi xong.
     */
    async run() {
        const channel = this.channel;
        const onClose = () => this.window.abort(new Error('channel-closed'));
        channel.bufferedAmountLowThreshold = BUFFER_LOW;
        channel.addEventListener('close', onClose);
        channel.send(JSON.stringify({ t: 'start', fid: this.fid, size: this.file.size }));

        try {
            for await (const { data } of readChunks(this.file, { chunkSize: this.chunkSize })) {
                if (this.cancelled) {
                    return false;
                }

                await this.window.wait(data.byteLength);

                if (channel.bufferedAmount > BUFFER_HIGH) {
                    await waitForLowBuffer(channel);
                }

                if (this.cancelled) {
                    return false;
                }
                if (channel.readyState !== 'open') {
                    throw new Error('channel-closed');
                }

                channel.send(data);
                this.window.add(data.byteLength);
            }
            channel.send(JSON.stringify({ t: 'end', fid: this.fid }));
            await this.window.drained();
        } catch (error) {
            if (this.cancelled) {
                return false;
            }
            throw error;
        } finally {
            channel.removeEventListener('close', onClose);
        }

        return ! this.cancelled;
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
