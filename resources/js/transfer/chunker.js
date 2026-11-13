import { CHUNK_SIZE, READ_BLOCK } from './protocol.js';

/**
 * Đọc dần một File/Blob thành các mảnh ArrayBuffer, bắt đầu từ $start. Mỗi lần chỉ giữ trong RAM
 * một khối READ_BLOCK, không bao giờ đọc cả file.
 */
export async function* readChunks(blob, { start = 0, chunkSize = CHUNK_SIZE, readBlock = READ_BLOCK } = {}) {
    for (let blockStart = start; blockStart < blob.size; blockStart += readBlock) {
        const block = await blob.slice(blockStart, Math.min(blob.size, blockStart + readBlock)).arrayBuffer();

        for (let offset = 0; offset < block.byteLength; offset += chunkSize) {
            yield { offset: blockStart + offset, data: block.slice(offset, Math.min(block.byteLength, offset + chunkSize)) };
        }
    }
}

/**
 * Cửa sổ gửi: chỉ cho đẩy thêm khi phần chưa được bên nhận xác nhận còn dưới $window byte.
 */
export class SendWindow {
    constructor(window) {
        this.window = window;
        this.sent = 0;
        this.acked = 0;
        this.waiters = [];
    }

    get inFlight() {
        return this.sent - this.acked;
    }

    canSend(bytes) {
        return this.inFlight + bytes <= this.window || this.inFlight === 0;
    }

    /** Chờ tới khi gửi được $bytes. Trả về ngay nếu đã được. */
    wait(bytes) {
        if (this.canSend(bytes)) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => this.waiters.push({ bytes, resolve, reject }));
    }

    add(bytes) {
        this.sent += bytes;
    }

    ack(total) {
        this.acked = Math.max(this.acked, Math.min(total, this.sent));
        this.waiters = this.waiters.filter((waiter) => {
            if (waiter.all ? this.inFlight === 0 : this.canSend(waiter.bytes)) {
                waiter.resolve();

                return false;
            }

            return true;
        });
    }

    /** Chờ bên nhận xác nhận hết mọi byte đã gửi. */
    drained() {
        return this.inFlight === 0 ? Promise.resolve() : new Promise((resolve, reject) => this.waiters.push({ bytes: this.window + 1, resolve, reject, all: true }));
    }

    /** Hủy mọi lượt chờ (khi hủy file hoặc mất kết nối). */
    abort(error) {
        this.waiters.forEach((waiter) => waiter.reject(error));
        this.waiters = [];
    }
}

/**
 * Ghi các mảnh xuống sink lần lượt theo đúng thứ tự đến, báo số byte đã ghi xong sau mỗi
 * $ackEvery byte để bên gửi mở cửa sổ. Sai số byte so với khai báo thì báo lỗi.
 */
export class IncomingFile {
    constructor({ size, sink, ackEvery, onAck = () => {}, onProgress = () => {} }) {
        this.size = size;
        this.sink = sink;
        this.ackEvery = ackEvery;
        this.onAck = onAck;
        this.onProgress = onProgress;
        this.queued = 0;
        this.written = 0;
        this.lastAck = 0;
        this.chain = Promise.resolve();
        this.failed = null;
    }

    push(data) {
        if (this.failed) {
            return;
        }

        // Ghi lại kích thước trước: sink có thể chuyển (transfer) buffer sang luồng khác, làm byteLength về 0.
        const size = data.byteLength;
        this.queued += size;

        if (this.queued > this.size) {
            this.failed = new Error('Nhận nhiều dữ liệu hơn kích thước file.');

            return;
        }

        this.chain = this.chain.then(async () => {
            if (this.failed) {
                return;
            }

            await this.sink.write(data);
            this.written += size;
            this.onProgress(this.written);

            if (this.written - this.lastAck >= this.ackEvery || this.written === this.size) {
                this.lastAck = this.written;
                this.onAck(this.written);
            }
        }).catch((error) => {
            this.failed = error;
        });
    }

    /** Chờ ghi hết rồi đóng sink; lỗi nếu thiếu byte. */
    async finish() {
        await this.chain;

        if (this.failed) {
            throw this.failed;
        }
        if (this.written !== this.size) {
            throw new Error(`Thiếu dữ liệu: nhận ${this.written}/${this.size} byte.`);
        }

        return this.sink.close();
    }

    async abort() {
        this.failed = this.failed ?? new Error('Đã hủy.');
        await this.chain.catch(() => {});
        await this.sink.abort?.();
    }
}
