import { describe, expect, it } from 'vitest';
import { IncomingFile, readChunks, SendWindow } from './chunker.js';
import { MemorySink } from './sinks.js';

const randomBytes = (size) => {
    const bytes = new Uint8Array(size);
    for (let i = 0; i < size; i += 65536) {
        crypto.getRandomValues(bytes.subarray(i, Math.min(size, i + 65536)));
    }

    return bytes;
};

describe('readChunks', () => {
    it('covers every byte in order with chunks no bigger than the chunk size', async () => {
        const bytes = randomBytes(300_001);
        const parts = [];

        for await (const { offset, data } of readChunks(new Blob([bytes]), { chunkSize: 1000, readBlock: 64_000 })) {
            expect(data.byteLength).toBeLessThanOrEqual(1000);
            expect(offset).toBe(parts.reduce((sum, part) => sum + part.length, 0));
            parts.push(new Uint8Array(data));
        }

        const joined = new Uint8Array(await new Blob(parts).arrayBuffer());
        expect(joined).toEqual(bytes);
    });

    it('starts from an offset (resume) and handles empty files', async () => {
        const blob = new Blob([new Uint8Array([1, 2, 3, 4, 5])]);
        const chunks = [];
        for await (const chunk of readChunks(blob, { start: 3, chunkSize: 1 })) {
            chunks.push([chunk.offset, new Uint8Array(chunk.data)[0]]);
        }

        expect(chunks).toEqual([[3, 4], [4, 5]]);

        let count = 0;
        for await (const ignored of readChunks(new Blob([]))) {
            count++;
        }
        expect(count).toBe(0);
    });

    it('never reads more than one block from the file at a time', async () => {
        const blob = new Blob([randomBytes(10_000)]);
        const sizes = [];
        const spy = { size: blob.size, slice: (start, end) => { sizes.push(end - start); return blob.slice(start, end); } };

        for await (const ignored of readChunks(spy, { chunkSize: 100, readBlock: 1024 })) {
            // đọc hết
        }

        expect(Math.max(...sizes)).toBeLessThanOrEqual(1024);
        expect(sizes.reduce((a, b) => a + b, 0)).toBe(10_000);
    });
});

describe('SendWindow', () => {
    it('blocks once the unacknowledged bytes reach the window and resumes on ack', async () => {
        const window = new SendWindow(100);
        window.add(60);
        expect(window.canSend(40)).toBe(true);
        window.add(40);
        expect(window.canSend(1)).toBe(false);

        let released = false;
        const waiting = window.wait(50).then(() => { released = true; });
        await Promise.resolve();
        expect(released).toBe(false);

        window.ack(60);
        await waiting;
        expect(released).toBe(true);
        expect(window.inFlight).toBe(40);
    });

    it('rejects waiters when aborted', async () => {
        const window = new SendWindow(10);
        window.add(10);
        const waiting = window.wait(5);
        window.abort(new Error('cancelled'));
        await expect(waiting).rejects.toThrow('cancelled');
    });
});

describe('IncomingFile', () => {
    it('writes chunks in arrival order even when the disk is slow, and acknowledges progress', async () => {
        const written = [];
        const slowSink = {
            async write(data) {
                await new Promise((resolve) => setTimeout(resolve, Math.random() * 3));
                written.push(new Uint8Array(data)[0]);
            },
            async close() { return {}; },
        };
        const acks = [];
        const incoming = new IncomingFile({ size: 10, sink: slowSink, ackEvery: 4, onAck: (bytes) => acks.push(bytes) });

        for (let i = 0; i < 10; i++) {
            incoming.push(new Uint8Array([i]).buffer);
        }
        await incoming.finish();

        expect(written).toEqual([0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);
        expect(acks).toEqual([4, 8, 10]);
    });

    it('counts bytes even when the sink transfers the buffer away (service worker, worker)', async () => {
        const transferringSink = {
            async write(data) {
                structuredClone(data, { transfer: [data] });
            },
            async close() { return {}; },
        };
        const acks = [];
        const incoming = new IncomingFile({ size: 8, sink: transferringSink, ackEvery: 4, onAck: (bytes) => acks.push(bytes) });
        incoming.push(new Uint8Array(4).buffer);
        incoming.push(new Uint8Array(4).buffer);
        await incoming.finish();

        expect(acks).toEqual([4, 8]);
    });

    it('fails when bytes are missing or extra', async () => {
        const short = new IncomingFile({ size: 5, sink: new MemorySink('a'), ackEvery: 1 });
        short.push(new Uint8Array(3).buffer);
        await expect(short.finish()).rejects.toThrow('Thiếu dữ liệu');

        const long = new IncomingFile({ size: 2, sink: new MemorySink('a'), ackEvery: 1 });
        long.push(new Uint8Array(3).buffer);
        await expect(long.finish()).rejects.toThrow('nhiều dữ liệu hơn');
    });

    it('aborting discards the data', async () => {
        const sink = new MemorySink('a');
        const incoming = new IncomingFile({ size: 4, sink, ackEvery: 1 });
        incoming.push(new Uint8Array(2).buffer);
        await incoming.abort();
        incoming.push(new Uint8Array(2).buffer);

        expect(sink.parts).toEqual([]);
        await expect(incoming.finish()).rejects.toThrow('Đã hủy');
    });
});
